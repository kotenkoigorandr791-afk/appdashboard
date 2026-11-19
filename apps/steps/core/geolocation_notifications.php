<?php
/**
 * Notification outbox, preferences and signed webhooks (task 24).
 *
 * In-app notifications reuse the shell's app_notifications table through
 * app_notify(), so they show up in the dashboard bell without a second
 * notification service. Webhooks are opt-in, HTTPS-only, re-resolved and pinned
 * at send time (no private/reserved addresses), HMAC-signed with a timestamp
 * and nonce, and retried with a bounded backoff from the existing worker path.
 */
require_once __DIR__ . '/geolocation_secrets.php';

final class StepsGeolocationNotifications
{
    public const EVENTS = [
        'geofence_enter' => 'Geofence entry',
        'geofence_exit' => 'Geofence exit',
        'device_offline' => 'Device offline',
        'sync_failure' => 'Sync failure',
        'import_complete' => 'Import complete',
        'family_permission_changed' => 'Family permission changed',
        'webhook_test' => 'Webhook test',
    ];
    public const MAX_ATTEMPTS = 5;
    public const LEASE_SECONDS = 120;

    public static function uuid(): string
    {
        $b = bin2hex(random_bytes(16));
        return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
    }

    public static function defaultPreferences(): array
    {
        return [
            'in_app' => true,
            'webhook' => true,
            'events' => array_fill_keys(array_keys(self::EVENTS), true),
            'quiet_hours' => ['enabled' => false, 'start' => '22:00', 'end' => '07:00'],
        ];
    }

    public static function preferences(PDO $pdo, string $owner): array
    {
        $stmt = $pdo->prepare('SELECT preferences_json FROM geo_notification_preferences WHERE user_email = :owner');
        $stmt->execute([':owner' => $owner]);
        $raw = $stmt->fetchColumn();
        $stored = is_string($raw) ? json_decode($raw, true) : null;
        $prefs = self::defaultPreferences();
        if (is_array($stored)) {
            if (array_key_exists('in_app', $stored)) $prefs['in_app'] = (bool)$stored['in_app'];
            if (array_key_exists('webhook', $stored)) $prefs['webhook'] = (bool)$stored['webhook'];
            if (isset($stored['events']) && is_array($stored['events'])) {
                foreach ($prefs['events'] as $event => $enabled) $prefs['events'][$event] = (bool)($stored['events'][$event] ?? $enabled);
            }
            if (isset($stored['quiet_hours']) && is_array($stored['quiet_hours'])) {
                $prefs['quiet_hours']['enabled'] = (bool)($stored['quiet_hours']['enabled'] ?? false);
                foreach (['start', 'end'] as $key) {
                    $value = (string)($stored['quiet_hours'][$key] ?? $prefs['quiet_hours'][$key]);
                    if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) $prefs['quiet_hours'][$key] = $value;
                }
            }
        }
        return $prefs;
    }

    public static function savePreferences(PDO $pdo, string $owner, array $input): array
    {
        $prefs = self::preferences($pdo, $owner);
        if (array_key_exists('in_app', $input)) $prefs['in_app'] = (bool)$input['in_app'];
        if (array_key_exists('webhook', $input)) $prefs['webhook'] = (bool)$input['webhook'];
        if (isset($input['events']) && is_array($input['events'])) {
            foreach ($prefs['events'] as $event => $enabled) {
                if (array_key_exists($event, $input['events'])) $prefs['events'][$event] = (bool)$input['events'][$event];
            }
        }
        if (isset($input['quiet_hours']) && is_array($input['quiet_hours'])) {
            $prefs['quiet_hours']['enabled'] = (bool)($input['quiet_hours']['enabled'] ?? $prefs['quiet_hours']['enabled']);
            foreach (['start', 'end'] as $key) {
                $value = trim((string)($input['quiet_hours'][$key] ?? ''));
                if ($value !== '') {
                    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) throw new InvalidArgumentException('invalid_quiet_hours');
                    $prefs['quiet_hours'][$key] = $value;
                }
            }
        }
        $pdo->prepare(
            'INSERT INTO geo_notification_preferences(user_email, preferences_json, updated_at) VALUES(:owner, :prefs, now())
             ON CONFLICT (user_email) DO UPDATE SET preferences_json = EXCLUDED.preferences_json, updated_at = now()'
        )->execute([':owner' => $owner, ':prefs' => json_encode($prefs, JSON_UNESCAPED_SLASHES)]);
        return self::preferences($pdo, $owner);
    }

    public static function quietNow(array $prefs, ?string $nowHm = null): bool
    {
        $quiet = $prefs['quiet_hours'] ?? [];
        if (!($quiet['enabled'] ?? false)) return false;
        $nowHm = $nowHm ?? gmdate('H:i');
        $start = (string)($quiet['start'] ?? '22:00');
        $end = (string)($quiet['end'] ?? '07:00');
        if ($start === $end) return true;
        if ($start < $end) return $nowHm >= $start && $nowHm < $end;
        return $nowHm >= $start || $nowHm < $end;
    }

    /** Record an event once. Dedupe keys make worker retries idempotent. */
    public static function enqueuePublic(string $owner, string $event, string $title, string $message, array $context = [], string $severity = 'info', string $dedupe = ''): bool
    {
        try {
            return self::enqueue(app_main_db()->getPdo(), $owner, $event, $title, $message, $context, $severity, $dedupe);
        } catch (Throwable $e) {
            error_log('geolocation notification enqueue: ' . $e->getMessage());
            return false;
        }
    }

    public static function enqueue(PDO $pdo, string $owner, string $event, string $title, string $message, array $context = [], string $severity = 'info', string $dedupe = ''): bool
    {
        if (!array_key_exists($event, self::EVENTS)) $event = 'sync_failure';
        if (!in_array($severity, ['info', 'success', 'warning', 'error', 'critical'], true)) $severity = 'info';
        $stmt = $pdo->prepare(
            "INSERT INTO geo_notification_outbox(id, user_email, event_type, severity, title, message, context_json, dedupe_key)
             VALUES(:id, :owner, :event, :severity, :title, :message, :context, :dedupe)
             ON CONFLICT (user_email, dedupe_key) WHERE dedupe_key <> '' DO NOTHING"
        );
        $stmt->execute([
            ':id' => self::uuid(),
            ':owner' => strtolower(trim($owner)),
            ':event' => $event,
            ':severity' => $severity,
            ':title' => mb_substr($title, 0, 160),
            ':message' => mb_substr($message, 0, 2000),
            ':context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':dedupe' => substr($dedupe, 0, 160),
        ]);
        return $stmt->rowCount() > 0;
    }

    /** @return list<array<string,mixed>> */
    public static function webhooks(PDO $pdo, string $owner): array
    {
        $stmt = $pdo->prepare('SELECT id, description, event_types, is_active, last_delivery_at, failure_count, created_at, updated_at, url_ciphertext FROM geo_webhooks WHERE user_email = :owner ORDER BY created_at DESC');
        $stmt->execute([':owner' => $owner]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['event_types'] = json_decode((string)$row['event_types'], true) ?: [];
            $decrypted = '';
            try {
                $decrypted = steps_geo_decrypt((string)$row['url_ciphertext'], $owner, 'webhook_url');
            } catch (Throwable $e) {
                $decrypted = '';
            }
            $row['url_host'] = $decrypted !== '' ? (string)(parse_url($decrypted, PHP_URL_HOST) ?: '') : '';
            unset($row['url_ciphertext']);
        }
        unset($row);
        return $rows;
    }

    /**
     * @param array{url?:string,secret?:string,description?:string,event_types?:array} $input
     * @return array{webhook:array<string,mixed>,secret:string}
     */
    public static function saveWebhook(PDO $pdo, string $owner, array $input): array
    {
        $url = trim((string)($input['url'] ?? ''));
        if ($url === '' || !self::urlAllowed($url)) throw new InvalidArgumentException('invalid_webhook_url');
        $secret = trim((string)($input['secret'] ?? ''));
        if ($secret === '') $secret = bin2hex(random_bytes(32));
        if (strlen($secret) < 32 || strlen($secret) > 200) throw new InvalidArgumentException('invalid_webhook_secret');
        $events = [];
        foreach ((array)($input['event_types'] ?? []) as $event) {
            $event = strtolower(trim((string)$event));
            if (array_key_exists($event, self::EVENTS)) $events[] = $event;
        }
        $events = array_values(array_unique($events));
        $description = mb_substr(trim((string)($input['description'] ?? '')), 0, 120);

        $count = $pdo->prepare('SELECT COUNT(*) FROM geo_webhooks WHERE user_email = :owner');
        $count->execute([':owner' => $owner]);
        if ((int)$count->fetchColumn() >= 20) throw new InvalidArgumentException('webhook_limit_reached');

        $id = self::uuid();
        $pdo->prepare(
            'INSERT INTO geo_webhooks(id, user_email, url_ciphertext, secret_ciphertext, event_types, description, is_active)
             VALUES(:id, :owner, :url, :secret, :events, :description, true)'
        )->execute([
            ':id' => $id,
            ':owner' => $owner,
            ':url' => steps_geo_encrypt($url, $owner, 'webhook_url'),
            ':secret' => steps_geo_encrypt($secret, $owner, 'webhook_secret'),
            ':events' => json_encode($events),
            ':description' => $description,
        ]);
        $webhook = null;
        foreach (self::webhooks($pdo, $owner) as $row) if ($row['id'] === $id) $webhook = $row;
        return ['webhook' => $webhook ?? [], 'secret' => $secret];
    }

    public static function deleteWebhook(PDO $pdo, string $owner, string $id): void
    {
        $stmt = $pdo->prepare('DELETE FROM geo_webhooks WHERE user_email = :owner AND id = :id');
        $stmt->execute([':owner' => $owner, ':id' => $id]);
        if ($stmt->rowCount() === 0) throw new InvalidArgumentException('webhook_not_found');
    }

    /** @return list<array<string,mixed>> */
    public static function deliveries(PDO $pdo, string $owner, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $pdo->prepare("SELECT d.id, d.event_type, d.state, d.attempts, d.next_attempt_at, d.response_code, d.last_error, d.created_at, d.delivered_at, w.description, w.id AS webhook_id
                                 FROM geo_webhook_deliveries d JOIN geo_webhooks w ON w.id = d.webhook_id
                                WHERE d.user_email = :owner ORDER BY d.created_at DESC LIMIT $limit");
        $stmt->execute([':owner' => $owner]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function sign(string $secret, string $timestamp, string $nonce, string $body): string
    {
        return 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . self::canonical($body), $secret);
    }

    public static function canonical(string $json): string
    {
        $data = json_decode($json, true);
        if (!is_array($data)) return $json;
        $sort = static function (&$value) use (&$sort): void {
            if (!is_array($value)) return;
            $isList = array_keys($value) === range(0, count($value) - 1);
            if (!$isList) ksort($value);
            foreach ($value as &$child) $sort($child);
        };
        $sort($data);
        return (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function urlAllowed(string $url): bool
    {
        if (strlen($url) > 2048) return false;
        $parts = parse_url($url);
        if (!is_array($parts)) return false;
        if (strtolower((string)($parts['scheme'] ?? '')) !== 'https') return false;
        $host = strtolower((string)($parts['host'] ?? ''));
        if ($host === '' || isset($parts['user']) || isset($parts['pass'])) return false;
        if (!empty($parts['port']) && (int)$parts['port'] !== 443) return false;
        if (str_ends_with($host, '.local') || str_ends_with($host, '.internal') || str_ends_with($host, '.localhost')) return false;
        if ($host === 'localhost' || $host === '0.0.0.0' || $host === '::1') return false;
        if (filter_var($host, FILTER_VALIDATE_IP)) return self::publicIp($host);
        $addresses = gethostbynamel($host);
        if (!is_array($addresses) || $addresses === []) return false;
        foreach ($addresses as $address) if (!self::publicIp($address)) return false;
        return true;
    }

    private static function publicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * Deliver pending outbox events and webhook retries.
     *
     * @return array{events:int,delivered:int,failed:int}
     */
    public static function dispatch(PDO $pdo, int $limit = 50): array
    {
        $stats = ['events' => 0, 'delivered' => 0, 'failed' => 0];
        $claimed = [];
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM geo_notification_outbox
                  WHERE state IN ('pending','failed') AND next_attempt_at <= now()
                    AND (lease_until IS NULL OR lease_until < now())
                    AND attempts < " . self::MAX_ATTEMPTS . "
               ORDER BY created_at ASC LIMIT $limit FOR UPDATE SKIP LOCKED"
            );
            $stmt->execute();
            $claimed = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($claimed as $row) {
                $pdo->prepare("UPDATE geo_notification_outbox SET state = 'processing', attempts = attempts + 1, lease_until = now() + interval '" . self::LEASE_SECONDS . " seconds', updated_at = now() WHERE id = :id")
                    ->execute([':id' => $row['id']]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        foreach ($claimed as $row) {
            $stats['events']++;
            $event = (string)$row['event_type'];
            $prefs = self::preferences($pdo, (string)$row['user_email']);
            $enabled = (bool)($prefs['events'][$event] ?? true);
            $quiet = self::quietNow($prefs);
            $context = json_decode((string)$row['context_json'], true) ?: [];
            $error = '';
            try {
                if (!$enabled) {
                    $error = '';
                } else {
                    if ($prefs['in_app'] && (!$quiet || strtolower((string)$row['severity']) === 'critical')) {
                        if (function_exists('app_notify')) {
                            app_notify((string)$row['user_email'], 'steps', (string)$row['severity'], (string)$row['title'], (string)$row['message'], $event, $context);
                            $stats['delivered']++;
                        }
                    }
                    if ($prefs['webhook'] && !$quiet) {
                        self::queueDeliveries($pdo, $row, $context);
                    }
                }
                $pdo->prepare("UPDATE geo_notification_outbox SET state = 'ready', delivered_at = now(), lease_until = NULL, last_error = :error, updated_at = now() WHERE id = :id")
                    ->execute([':error' => $error, ':id' => $row['id']]);
            } catch (Throwable $e) {
                $stats['failed']++;
                self::scheduleRetry($pdo, 'geo_notification_outbox', (string)$row['id'], (int)$row['attempts'] + 1, 'dispatch_failed');
            }
        }

        $stats['failed'] += self::deliverWebhooks($pdo, $limit);
        return $stats;
    }

    private static function queueDeliveries(PDO $pdo, array $outbox, array $context): void
    {
        $stmt = $pdo->prepare('SELECT id, event_types, url_ciphertext, secret_ciphertext FROM geo_webhooks WHERE user_email = :owner AND is_active');
        $stmt->execute([':owner' => $outbox['user_email']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $hook) {
            $events = json_decode((string)$hook['event_types'], true) ?: [];
            if ($events !== [] && !in_array((string)$outbox['event_type'], $events, true)) continue;
            $key = (string)$outbox['id'] . ':' . (string)$hook['id'];
            $pdo->prepare(
                "INSERT INTO geo_webhook_deliveries(id, webhook_id, user_email, event_type, state, attempts, next_attempt_at, idempotency_key)
                 VALUES(:id, :hook, :owner, :event, 'pending', 0, now(), :key)
                 ON CONFLICT (webhook_id, idempotency_key) WHERE idempotency_key <> '' DO NOTHING"
            )->execute([
                ':id' => self::uuid(),
                ':hook' => $hook['id'],
                ':owner' => $outbox['user_email'],
                ':event' => (string)$outbox['event_type'],
                ':key' => $key,
            ]);
        }
    }

    public static function deliverWebhooks(PDO $pdo, int $limit = 50): int
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT d.*, w.url_ciphertext, w.secret_ciphertext, w.event_types
                   FROM geo_webhook_deliveries d JOIN geo_webhooks w ON w.id = d.webhook_id
                  WHERE d.state IN ('pending','failed') AND (d.next_attempt_at IS NULL OR d.next_attempt_at <= now())
                    AND (d.lease_until IS NULL OR d.lease_until < now())
                    AND d.attempts < " . self::MAX_ATTEMPTS . " AND w.is_active
               ORDER BY d.created_at ASC LIMIT $limit FOR UPDATE OF d SKIP LOCKED"
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $pdo->prepare("UPDATE geo_webhook_deliveries SET state = 'processing', attempts = attempts + 1, lease_until = now() + interval '" . self::LEASE_SECONDS . " seconds', updated_at = now() WHERE id = :id")
                    ->execute([':id' => $row['id']]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        $failed = 0;
        foreach ($rows as $row) {
            $owner = (string)$row['user_email'];
            $url = '';
            $secret = '';
            try {
                $url = steps_geo_decrypt((string)$row['url_ciphertext'], $owner, 'webhook_url');
                $secret = steps_geo_decrypt((string)$row['secret_ciphertext'], $owner, 'webhook_secret');
            } catch (Throwable $e) {
                $failed++;
                self::scheduleRetry($pdo, 'geo_webhook_deliveries', (string)$row['id'], (int)$row['attempts'] + 1, 'secret_unavailable');
                continue;
            }
            $outboxStmt = $pdo->prepare("SELECT title, message, context_json, severity FROM geo_notification_outbox WHERE user_email = :owner AND event_type = :event ORDER BY created_at DESC LIMIT 1");
            $outboxStmt->execute([':owner' => $owner, ':event' => (string)$row['event_type']]);
            $source = $outboxStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $payload = [
                'event' => (string)$row['event_type'],
                'delivery_id' => (string)$row['id'],
                'occurred_at' => gmdate('c'),
                'title' => (string)($source['title'] ?? ''),
                'message' => (string)($source['message'] ?? ''),
                'severity' => (string)($source['severity'] ?? 'info'),
                'context' => json_decode((string)($source['context_json'] ?? '{}'), true) ?: [],
            ];
            $body = self::canonical((string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $timestamp = (string)time();
            $nonce = bin2hex(random_bytes(12));
            $signature = self::sign($secret, $timestamp, $nonce, $body);
            $result = self::post($url, $body, [
                'Content-Type: application/json',
                'User-Agent: StepsWebhook/1.0',
                'X-Steps-Event: ' . $row['event_type'],
                'X-Steps-Delivery: ' . $row['id'],
                'X-Steps-Timestamp: ' . $timestamp,
                'X-Steps-Nonce: ' . $nonce,
                'X-Steps-Signature: ' . $signature,
            ]);
            if ($result['ok']) {
                $pdo->prepare("UPDATE geo_webhook_deliveries SET state = 'ready', response_code = :code, delivered_at = now(), lease_until = NULL, last_error = '', updated_at = now() WHERE id = :id")
                    ->execute([':code' => $result['code'], ':id' => $row['id']]);
                $pdo->prepare('UPDATE geo_webhooks SET last_delivery_at = now(), failure_count = 0, updated_at = now() WHERE id = :id AND user_email = :owner')
                    ->execute([':id' => $row['webhook_id'], ':owner' => $owner]);
            } else {
                $failed++;
                self::scheduleRetry($pdo, 'geo_webhook_deliveries', (string)$row['id'], (int)$row['attempts'] + 1, $result['error'], $result['code']);
                $pdo->prepare('UPDATE geo_webhooks SET failure_count = failure_count + 1, updated_at = now() WHERE id = :id AND user_email = :owner')
                    ->execute([':id' => $row['webhook_id'], ':owner' => $owner]);
            }
        }
        return $failed;
    }

    private static function scheduleRetry(PDO $pdo, string $table, string $id, int $attempts, string $error, ?int $code = null): void
    {
        $state = $attempts >= self::MAX_ATTEMPTS ? 'stale' : 'failed';
        $delay = min(86400, max(30, 30 * (2 ** max(0, $attempts - 1))));
        $extra = $table === 'geo_webhook_deliveries' && $code !== null ? ', response_code = :code' : '';
        $stmt = $pdo->prepare("UPDATE $table SET state = :state, attempts = :attempts, next_attempt_at = now() + interval '$delay seconds', lease_until = NULL, last_error = :error$extra, updated_at = now() WHERE id = :id");
        $params = [':state' => $state, ':attempts' => $attempts, ':error' => mb_substr($error, 0, 300), ':id' => $id];
        if ($extra !== '') $params[':code'] = $code;
        $stmt->execute($params);
    }

    /** @return array{ok:bool,code:int,error:string} */
    private static function post(string $url, string $body, array $headers): array
    {
        if (!self::urlAllowed($url)) return ['ok' => false, 'code' => 0, 'error' => 'endpoint_blocked'];
        $parts = parse_url($url);
        $host = (string)($parts['host'] ?? '');
        $path = (string)($parts['path'] ?? '/');
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        if (!function_exists('curl_init')) return ['ok' => false, 'code' => 0, 'error' => 'curl_unavailable'];

        $resolve = [];
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses = gethostbynamel($host) ?: [];
            $public = array_values(array_filter($addresses, static fn(string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false));
            if ($public === []) return ['ok' => false, 'code' => 0, 'error' => 'endpoint_blocked'];
            $resolve = [$host . ':443:' . $public[0]];
        }

        $ch = curl_init('https://' . $host . $path . $query);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        if ($resolve !== []) curl_setopt($ch, CURLOPT_RESOLVE, $resolve);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = (int)curl_errno($ch);
        unset($ch);
        if ($errno !== 0) return ['ok' => false, 'code' => $code, 'error' => 'network_error_' . $errno];
        return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'error' => $code >= 200 && $code < 300 ? '' : 'http_' . $code];
    }

    /** Emit a one-off offline notice per device per day. */
    public static function enqueueDeviceOffline(PDO $pdo): int
    {
        $stmt = $pdo->query("SELECT id, user_email, name FROM geo_devices WHERE status = 'active' AND source_type = 'mobile' AND last_seen_at IS NOT NULL AND last_seen_at < now() - interval '24 hours'");
        $count = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $device) {
            $dedupe = 'device_offline:' . $device['id'] . ':' . gmdate('Y-m-d');
            if (self::enqueue($pdo, (string)$device['user_email'], 'device_offline', 'Device offline', $device['name'] . ' has not sent a location for 24 hours.', ['device_id' => (string)$device['id']], 'warning', $dedupe)) $count++;
        }
        return $count;
    }

    public static function importComplete(PDO $pdo, string $owner, string $importId, int $accepted, int $duplicate, int $rejected): bool
    {
        $message = $accepted . ' point(s) imported' . ($duplicate > 0 ? ', ' . $duplicate . ' duplicate(s)' : '') . ($rejected > 0 ? ', ' . $rejected . ' rejected' : '') . '.';
        return self::enqueue($pdo, $owner, 'import_complete', 'Location import finished', $message, ['import_id' => $importId, 'accepted' => $accepted, 'duplicate' => $duplicate, 'rejected' => $rejected], 'success', 'import_complete:' . $importId);
    }
}
