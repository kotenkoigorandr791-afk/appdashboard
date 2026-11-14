<?php
/**
 * Central privacy and security primitives for Steps geolocation (task 30).
 *
 * This file intentionally has no UI or request dependencies: every endpoint,
 * worker and integration that handles location data funnels tenant checks,
 * outbound-request checks and audit redaction through these helpers.
 */
require_once __DIR__ . '/geolocation_domain.php';

final class StepsGeolocationSecurityError extends RuntimeException
{
    public $errorCode;
    public $status;

    public function __construct(string $errorCode, int $status = 400)
    {
        $this->errorCode = $errorCode;
        $this->status = $status;
        parent::__construct($errorCode);
    }
}

final class StepsGeolocationSecurity
{
    /** Keys that must never be written to logs or returned in diagnostics. */
    public const SENSITIVE_KEYS = [
        'latitude', 'longitude', 'lat', 'lon', 'lng', 'position', 'geometry',
        'coordinates', 'coords', 'bbox', 'minlat', 'minlon', 'maxlat', 'maxlon',
        'server_url', 'serverurl', 'api_key', 'apikey', 'token', 'secret',
        'password', 'ciphertext', 'credential', 'authorization', 'cookie',
    ];

    public static function uuid(): string
    {
        $b = bin2hex(random_bytes(16));
        return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
    }

    public static function normalizeOwner(?string $owner): string
    {
        return StepsGeolocationDomain::normalizeOwner((string)$owner);
    }

    /**
     * Tenant predicate guard for rows that have already been loaded. Queries
     * must still always filter by owner; this is a final fail-closed check.
     */
    public static function requireOwner(?string $rowOwner, string $owner): void
    {
        $rowOwner = self::normalizeOwner($rowOwner);
        $owner = self::normalizeOwner($owner);
        if ($owner === '' || $rowOwner === '' || !hash_equals($owner, $rowOwner)) {
            throw new StepsGeolocationSecurityError('not_found', 404);
        }
    }

    /**
     * Recursively remove coordinates, geometry and credential material.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public static function redactContext(array $context): array
    {
        $clean = [];
        foreach ($context as $key => $value) {
            $normalized = strtolower(preg_replace('/[^a-z0-9_]+/i', '', (string)$key) ?? '');
            if (in_array($normalized, self::SENSITIVE_KEYS, true)) continue;
            if (is_array($value)) {
                $clean[$key] = self::redactContext($value);
            } elseif (is_string($value)) {
                // Never keep long bearer/API secrets in free-form error context.
                $clean[$key] = preg_match('/^(stp_|Bearer\s)/i', $value) ? '[redacted]' : $value;
            } else {
                $clean[$key] = $value;
            }
        }
        return $clean;
    }

    public static function audit(PDO $pdo, string $owner, string $actor, string $event, array $context = []): void
    {
        try {
            $pdo->prepare('INSERT INTO geo_audit_events(id, user_email, actor_email, event_type, context_json) VALUES(:id, :owner, :actor, :event, :context)')
                ->execute([
                    ':id' => self::uuid(),
                    ':owner' => self::normalizeOwner($owner),
                    ':actor' => self::normalizeOwner($actor !== '' ? $actor : $owner),
                    ':event' => preg_replace('/[^a-z0-9._-]+/i', '_', $event) ?: 'unknown',
                    ':context' => json_encode(self::redactContext($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
        } catch (Throwable $e) {
            error_log('geolocation audit failed: ' . $e->getMessage());
        }
    }

    public static function auditRead(PDO $pdo, string $owner, string $resource, int $count, array $extra = []): void
    {
        self::audit($pdo, $owner, $owner, 'access.read', array_merge([
            'resource' => $resource,
            'result_count' => $count,
        ], $extra));
    }

    /** Round a coordinate to a precision that a capability permits. */
    public static function roundCoordinate($value, int $decimals): ?float
    {
        if (!is_numeric($value)) return null;
        return round((float)$value, max(0, min(7, $decimals)));
    }

    /**
     * Round common coordinate fields in a payload in place.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function applyCoordinatePrecision(array $payload, int $decimals): array
    {
        foreach ($payload as $key => $value) {
            $normalized = strtolower((string)$key);
            if (in_array($normalized, ['latitude', 'longitude', 'lat', 'lon', 'lng'], true) && is_numeric($value)) {
                $payload[$key] = round((float)$value, max(0, min(7, $decimals)));
            } elseif (is_array($value)) {
                $payload[$key] = self::applyCoordinatePrecision($value, $decimals);
            }
        }
        return $payload;
    }

    /**
     * Validate a server-side outbound URL and resolve it, blocking SSRF into
     * loopback, link-local, private and reserved networks unless the host is
     * explicitly allowlisted by an operator (needed for a LAN Immich server).
     */
    public static function validateOutboundUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) throw new StepsGeolocationSecurityError('invalid_server_url');
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) throw new StepsGeolocationSecurityError('invalid_server_url');
        if (isset($parts['user']) || isset($parts['pass'])) throw new StepsGeolocationSecurityError('invalid_server_url');
        if (isset($parts['port']) && ((int)$parts['port'] < 1 || (int)$parts['port'] > 65535)) throw new StepsGeolocationSecurityError('invalid_server_url');
        if (isset($parts['query']) || isset($parts['fragment'])) throw new StepsGeolocationSecurityError('invalid_server_url');

        $host = strtolower(trim((string)$parts['host'], '[] '));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new StepsGeolocationSecurityError('invalid_server_url');
        }

        $allow = array_values(array_filter(array_map(
            static fn(string $value): string => strtolower(trim($value)),
            explode(',', (string)getenv('GEO_SSRF_ALLOW_PRIVATE_HOSTS'))
        )));
        $allowlisted = in_array($host, $allow, true);

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (!empty($record['ip'])) $ips[] = (string)$record['ip'];
                    if (!empty($record['ipv6'])) $ips[] = (string)$record['ipv6'];
                }
            }
            if ($ips === []) {
                $resolved = @gethostbynamel($host);
                if (is_array($resolved)) $ips = $resolved;
            }
        }
        $ips = array_values(array_unique($ips));
        if ($ips === []) throw new StepsGeolocationSecurityError('outbound_host_unresolved');
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) throw new StepsGeolocationSecurityError('invalid_server_url');
            $public = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
            if (!$public && !$allowlisted) throw new StepsGeolocationSecurityError('outbound_private_address_denied');
            // Explicitly reject the cloud metadata endpoint even when allowlisted.
            if ($ip === '169.254.169.254' || $ip === '100.100.100.200') throw new StepsGeolocationSecurityError('outbound_private_address_denied');
        }
        return $url;
    }

    /** Hard cap for location uploads; nginx has a matching body limit. */
    public static function maxUploadBytes(): int
    {
        $max = 268435456; // 256 MB
        if (class_exists('StepsGeolocationImports')) {
            try { $max = StepsGeolocationImports::maxBytes(); } catch (Throwable $e) {}
        }
        return max(1048576, min(2147483648, (int)$max));
    }

    public static function assertUploadSize(?int $contentLength = null): void
    {
        if ($contentLength === null && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] !== '') {
            $contentLength = (int)$_SERVER['CONTENT_LENGTH'];
        }
        if ($contentLength !== null && $contentLength > self::maxUploadBytes()) {
            throw new StepsGeolocationSecurityError('payload_too_large', 413);
        }
    }

    /**
     * @param array<string,mixed> $input
     * @return array{summary_precision_decimals:int,retention_days:int,audit_retention_days:int,updated_at:?string}
     */
    public static function policy(PDO $pdo, string $owner): array
    {
        $defaults = ['summary_precision_decimals' => 2, 'retention_days' => 0, 'audit_retention_days' => 365, 'updated_at' => null];
        try {
            $stmt = $pdo->prepare('SELECT summary_precision_decimals, retention_days, audit_retention_days, updated_at FROM geo_privacy_settings WHERE user_email = :owner');
            $stmt->execute([':owner' => $owner]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return $defaults;
            return [
                'summary_precision_decimals' => (int)$row['summary_precision_decimals'],
                'retention_days' => (int)$row['retention_days'],
                'audit_retention_days' => (int)$row['audit_retention_days'],
                'updated_at' => $row['updated_at'],
            ];
        } catch (Throwable $e) {
            return $defaults;
        }
    }

    public static function savePolicy(PDO $pdo, string $owner, array $input): array
    {
        $current = self::policy($pdo, $owner);
        $precision = array_key_exists('summary_precision_decimals', $input) ? (int)$input['summary_precision_decimals'] : $current['summary_precision_decimals'];
        $retention = array_key_exists('retention_days', $input) ? (int)$input['retention_days'] : $current['retention_days'];
        $audit = array_key_exists('audit_retention_days', $input) ? (int)$input['audit_retention_days'] : $current['audit_retention_days'];
        if ($precision < 0 || $precision > 4) throw new StepsGeolocationSecurityError('invalid_precision');
        if ($retention < 0 || $retention > 3650) throw new StepsGeolocationSecurityError('invalid_retention');
        if ($audit < 30 || $audit > 3650) throw new StepsGeolocationSecurityError('invalid_audit_retention');
        $pdo->prepare(
            'INSERT INTO geo_privacy_settings(user_email, summary_precision_decimals, retention_days, audit_retention_days, updated_at)
             VALUES(:owner, :precision, :retention, :audit, now())
             ON CONFLICT (user_email) DO UPDATE SET summary_precision_decimals = EXCLUDED.summary_precision_decimals,
                 retention_days = EXCLUDED.retention_days, audit_retention_days = EXCLUDED.audit_retention_days, updated_at = now()'
        )->execute([':owner' => $owner, ':precision' => $precision, ':retention' => $retention, ':audit' => $audit]);
        self::audit($pdo, $owner, $owner, 'privacy.policy_updated', [
            'summary_precision_decimals' => $precision,
            'retention_days' => $retention,
            'audit_retention_days' => $audit,
        ]);
        return self::policy($pdo, $owner);
    }
}
