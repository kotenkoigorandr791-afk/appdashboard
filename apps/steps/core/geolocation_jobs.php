<?php
/**
 * Durable geolocation job queue (task 31).
 *
 * PostgreSQL is the source of truth. Workers claim jobs with SKIP LOCKED,
 * renew a lease while running, retry with exponential backoff, and can cancel.
 * Redis is only used for metrics/cache elsewhere, never for queue state.
 */
require_once __DIR__ . '/geolocation_security.php';

final class StepsGeolocationJobsError extends RuntimeException
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

final class StepsGeolocationJobs
{
    public const LEASE_SECONDS = 300;
    public const PRIORITIES = ['critical' => 0, 'interactive' => 10, 'normal' => 50, 'bulk' => 100];
    public const TERMINAL = ['ready', 'failed', 'cancelled'];

    public static function uuid(): string
    {
        return StepsGeolocationSecurity::uuid();
    }

    public static function workerId(): string
    {
        $host = function_exists('gethostname') ? (string)gethostname() : 'worker';
        return substr(preg_replace('/[^a-zA-Z0-9._-]/', '_', $host) ?: 'worker', 0, 40) . ':' . getmypid() . ':' . bin2hex(random_bytes(3));
    }

    public static function priority($value): int
    {
        if (is_numeric($value)) return max(0, min(999, (int)$value));
        $name = strtolower(trim((string)$value));
        return self::PRIORITIES[$name] ?? self::PRIORITIES['normal'];
    }

    public static function perUserLimit(): int
    {
        $raw = getenv('GEO_MAX_ACTIVE_JOBS_PER_USER');
        return is_string($raw) && is_numeric($raw) ? max(1, min(20, (int)$raw)) : 2;
    }

    public static function maxPendingPerUser(): int
    {
        $raw = getenv('GEO_MAX_PENDING_JOBS_PER_USER');
        return is_string($raw) && is_numeric($raw) ? max(1, min(500, (int)$raw)) : 20;
    }

    /**
     * @param array{priority?:mixed,input_from?:?string,input_to?:?string,details?:array,max_attempts?:int,dedupe_key?:string,correlation_id?:string} $opts
     */
    public static function enqueue(PDO $pdo, string $owner, string $kind, array $opts = []): string
    {
        $owner = StepsGeolocationSecurity::normalizeOwner($owner);
        $kind = strtolower(trim($kind));
        if ($owner === '') throw new StepsGeolocationJobsError('invalid_owner');
        if (!preg_match('/^[a-z][a-z0-9_]{1,39}$/', $kind)) throw new StepsGeolocationJobsError('invalid_job_kind');
        $dedupe = trim((string)($opts['dedupe_key'] ?? ''));
        if ($dedupe !== '') {
            $stmt = $pdo->prepare("SELECT id FROM geo_processor_runs WHERE user_email = :owner AND kind = :kind AND state = 'pending' AND details_json->>'dedupe_key' = :dedupe LIMIT 1");
            $stmt->execute([':owner' => $owner, ':kind' => $kind, ':dedupe' => $dedupe]);
            $existing = $stmt->fetchColumn();
            if ($existing) return (string)$existing;
        }
        $active = $pdo->prepare("SELECT COUNT(*) FROM geo_processor_runs WHERE user_email = :owner AND state IN ('pending','processing')");
        $active->execute([':owner' => $owner]);
        if ((int)$active->fetchColumn() >= self::maxPendingPerUser()) {
            throw new StepsGeolocationJobsError('queue_full', 429);
        }
        $id = self::uuid();
        $details = is_array($opts['details'] ?? null) ? $opts['details'] : [];
        if ($dedupe !== '') $details['dedupe_key'] = $dedupe;
        $stmt = $pdo->prepare(
            "INSERT INTO geo_processor_runs
                (id, user_email, kind, state, processor_version, input_from, input_to, details_json,
                 priority, attempts, max_attempts, next_attempt_at, correlation_id)
             VALUES(:id, :owner, :kind, 'pending', :version, :from, :to, :details,
                    :priority, 0, :max_attempts, now(), :correlation)"
        );
        $stmt->execute([
            ':id' => $id,
            ':owner' => $owner,
            ':kind' => $kind,
            ':version' => substr((string)($details['processor_version'] ?? 'jobs-1'), 0, 40),
            ':from' => isset($opts['input_from']) && $opts['input_from'] !== '' ? gmdate('c', strtotime((string)$opts['input_from'])) : null,
            ':to' => isset($opts['input_to']) && $opts['input_to'] !== '' ? gmdate('c', strtotime((string)$opts['input_to'])) : null,
            ':details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':priority' => self::priority($opts['priority'] ?? 'normal'),
            ':max_attempts' => max(1, min(20, (int)($opts['max_attempts'] ?? 5))),
            ':correlation' => substr(preg_replace('/[^A-Za-z0-9._:-]/', '', (string)($opts['correlation_id'] ?? '')) ?: '', 0, 80),
        ]);
        return $id;
    }

    /**
     * Claim up to $limit jobs for this worker.
     *
     * @param list<string> $kinds
     * @return list<array<string,mixed>>
     */
    public static function claim(PDO $pdo, string $workerId, array $kinds = [], int $limit = 1, ?string $user = null): array
    {
        $limit = max(1, min(20, $limit));
        $where = ["r.state = 'pending'", 'r.next_attempt_at <= now()', 'r.cancel_requested = false',
            'r.attempts < r.max_attempts', '(r.lease_until IS NULL OR r.lease_until < now())'];
        $params = [':worker' => $workerId, ':lease_seconds' => self::LEASE_SECONDS, ':per_user' => self::perUserLimit()];
        $kinds = array_values(array_filter(array_map('strval', $kinds), static fn(string $kind): bool => preg_match('/^[a-z][a-z0-9_]{1,39}$/', $kind) === 1));
        if ($kinds !== []) {
            $places = [];
            foreach ($kinds as $i => $kind) {
                $key = ':kind' . $i;
                $places[] = $key;
                $params[$key] = $kind;
            }
            $where[] = 'r.kind IN (' . implode(',', $places) . ')';
        }
        if ($user !== null && trim($user) !== '') {
            $where[] = 'r.user_email = :owner';
            $params[':owner'] = StepsGeolocationSecurity::normalizeOwner($user);
        }
        // Rank in a subquery: a plain COUNT in the WHERE clause sees the
        // snapshot before the UPDATE and would let one claim start many jobs.
        $sql = "WITH ranked AS (
                    SELECT r.id, r.priority, r.created_at,
                           row_number() OVER (PARTITION BY r.user_email ORDER BY r.priority ASC, r.created_at ASC) AS rn,
                           (SELECT COUNT(*) FROM geo_processor_runs a
                             WHERE a.user_email = r.user_email AND a.state = 'processing'
                               AND a.cancel_requested = false
                               AND a.lease_until IS NOT NULL AND a.lease_until > now()) AS active
                      FROM geo_processor_runs r
                     WHERE " . implode(' AND ', $where) . "
                ), candidate AS (
                    SELECT id FROM ranked
                     WHERE rn <= GREATEST(0, :per_user - active)
                     ORDER BY priority ASC, created_at ASC
                     FOR UPDATE SKIP LOCKED
                    LIMIT " . $limit . "
                )
                UPDATE geo_processor_runs r
                   SET state = 'processing',
                       attempts = r.attempts + 1,
                       worker_id = :worker,
                       lease_until = now() + make_interval(secs => :lease_seconds),
                       heartbeat_at = now(),
                       started_at = COALESCE(r.started_at, now()),
                       updated_at = now()
                  FROM candidate c
                 WHERE r.id = c.id
             RETURNING r.*";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function heartbeat(PDO $pdo, string $id, string $workerId, ?int $seconds = null): bool
    {
        $seconds = max(30, min(3600, $seconds ?? self::LEASE_SECONDS));
        $stmt = $pdo->prepare(
            "UPDATE geo_processor_runs
                SET heartbeat_at = now(), lease_until = now() + make_interval(secs => :seconds), updated_at = now()
              WHERE id = :id AND state = 'processing' AND worker_id = :worker AND cancel_requested = false"
        );
        $stmt->execute([':seconds' => $seconds, ':id' => $id, ':worker' => $workerId]);
        return $stmt->rowCount() > 0;
    }

    public static function isCancelled(PDO $pdo, string $id): bool
    {
        try {
            $stmt = $pdo->prepare('SELECT cancel_requested FROM geo_processor_runs WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetchColumn();
            return $row === true || $row === 't' || $row === 1 || $row === '1';
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function complete(PDO $pdo, string $id, array $details = []): void
    {
        $stmt = $pdo->prepare(
            "UPDATE geo_processor_runs
                SET state = 'ready', lease_until = NULL, heartbeat_at = now(), finished_at = now(),
                    details_json = COALESCE(details_json, '{}'::jsonb) || CAST(:details AS jsonb),
                    updated_at = now()
              WHERE id = :id AND state = 'processing'"
        );
        $stmt->execute([':details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ':id' => $id]);
    }

    public static function fail(PDO $pdo, string $id, string $error, bool $retryable = true): string
    {
        $stmt = $pdo->prepare('SELECT attempts, max_attempts FROM geo_processor_runs WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return 'missing';
        $attempts = (int)$row['attempts'];
        $max = max(1, (int)$row['max_attempts']);
        $safeError = substr(preg_replace('/[^a-zA-Z0-9._:-]/', '_', $error) ?: 'job_failed', 0, 200);
        if ($retryable && $attempts < $max) {
            $delay = min(3600, 15 * (2 ** max(0, $attempts - 1)));
            $pdo->prepare(
                "UPDATE geo_processor_runs
                    SET state = 'pending', lease_until = NULL, worker_id = '', last_error = :error,
                        next_attempt_at = now() + make_interval(secs => :delay), updated_at = now()
                  WHERE id = :id"
            )->execute([':error' => $safeError, ':delay' => $delay, ':id' => $id]);
            return 'retry';
        }
        $pdo->prepare(
            "UPDATE geo_processor_runs
                SET state = 'failed', lease_until = NULL, last_error = :error, finished_at = now(), updated_at = now()
              WHERE id = :id"
        )->execute([':error' => $safeError, ':id' => $id]);
        return 'failed';
    }

    public static function cancel(PDO $pdo, string $id, string $owner): bool
    {
        $stmt = $pdo->prepare(
            "UPDATE geo_processor_runs
                SET cancel_requested = true,
                    state = CASE WHEN state = 'pending' THEN 'cancelled' ELSE state END,
                    lease_until = CASE WHEN state = 'pending' THEN NULL ELSE lease_until END,
                    finished_at = CASE WHEN state = 'pending' THEN now() ELSE finished_at END,
                    updated_at = now()
              WHERE id = :id AND user_email = :owner AND state IN ('pending','processing')"
        );
        $stmt->execute([':id' => $id, ':owner' => StepsGeolocationSecurity::normalizeOwner($owner)]);
        return $stmt->rowCount() > 0;
    }

    /** Requeue expired leases; fail jobs that exhausted attempts. */
    public static function reap(PDO $pdo): array
    {
        $failed = $pdo->prepare(
            "UPDATE geo_processor_runs
                SET state = 'failed', lease_until = NULL, last_error = 'lease_expired',
                    finished_at = now(), updated_at = now()
              WHERE state = 'processing' AND lease_until IS NOT NULL AND lease_until < now()
                AND attempts >= max_attempts"
        );
        $failed->execute();
        $retried = $pdo->prepare(
            "UPDATE geo_processor_runs
                SET state = CASE WHEN cancel_requested THEN 'cancelled' ELSE 'pending' END,
                    lease_until = NULL, worker_id = '', last_error = 'lease_expired',
                    next_attempt_at = now() + make_interval(secs => LEAST(3600, (15 * POWER(2, GREATEST(0, attempts - 1)))::int)),
                    finished_at = CASE WHEN cancel_requested THEN now() ELSE NULL END,
                    updated_at = now()
              WHERE state = 'processing' AND lease_until IS NOT NULL AND lease_until < now()
                AND attempts < max_attempts"
        );
        $retried->execute();
        return ['failed' => $failed->rowCount(), 'requeued' => $retried->rowCount()];
    }

    /** @return array<string,mixed> */
    public static function backlog(PDO $pdo): array
    {
        $states = [];
        foreach ($pdo->query("SELECT state, COUNT(*) AS c, MIN(created_at) AS oldest FROM geo_processor_runs GROUP BY state")->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $states[(string)$row['state']] = ['count' => (int)$row['c'], 'oldest' => $row['oldest']];
        }
        $oldest = $pdo->query("SELECT EXTRACT(EPOCH FROM (now() - MIN(created_at)))::int FROM geo_processor_runs WHERE state = 'pending'")->fetchColumn();
        $stalled = $pdo->query("SELECT COUNT(*) FROM geo_processor_runs WHERE state = 'processing' AND lease_until IS NOT NULL AND lease_until < now()")->fetchColumn();
        return [
            'states' => $states,
            'pending' => (int)($states['pending']['count'] ?? 0),
            'processing' => (int)($states['processing']['count'] ?? 0),
            'pending_age_s' => max(0, (int)$oldest),
            'stalled' => (int)$stalled,
        ];
    }

    /**
     * Remove old terminal rows while keeping a small diagnostic tail.
     *
     * @return array{deleted:int,geofence_duplicates:int,notifications:int}
     */
    public static function cleanup(PDO $pdo, ?int $retentionDays = null): array
    {
        $days = max(1, min(365, $retentionDays ?? (int)(getenv('GEO_JOB_RETENTION_DAYS') ?: 30)));
        $deleted = $pdo->prepare(
            "DELETE FROM geo_processor_runs
              WHERE id IN (
                  SELECT id FROM (
                      SELECT id, row_number() OVER (PARTITION BY user_email, kind ORDER BY created_at DESC) AS rn
                        FROM geo_processor_runs
                       WHERE state IN ('ready','failed','cancelled')
                         AND created_at < now() - make_interval(days => :days)
                  ) ranked WHERE rn > 20
              )"
        );
        $deleted->execute([':days' => $days]);
        $dup = 0;
        try {
            $d = $pdo->prepare(
                "DELETE FROM geo_geofence_events WHERE id IN (
                    SELECT id FROM (
                        SELECT id, row_number() OVER (PARTITION BY user_email, geofence_id, event_type, occurred_at ORDER BY id) AS rn
                          FROM geo_geofence_events
                    ) ranked WHERE rn > 1
                )"
            );
            $d->execute();
            $dup = $d->rowCount();
        } catch (Throwable $e) {}
        $notifications = 0;
        try {
            $n = $pdo->prepare("DELETE FROM geo_notification_outbox WHERE state = 'pending' AND event_type LIKE 'geofence_%' AND created_at < now() - interval '7 days'");
            $n->execute();
            $notifications = $n->rowCount();
        } catch (Throwable $e) {}
        return ['deleted' => $deleted->rowCount(), 'geofence_duplicates' => $dup, 'notifications' => $notifications];
    }

    /**
     * Backpressure for API enqueue paths.
     */
    public static function assertCapacity(PDO $pdo, string $owner, string $kind, int $limit = 5): void
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM geo_processor_runs WHERE user_email = :owner AND kind = :kind AND state IN ('pending','processing')");
        $stmt->execute([':owner' => StepsGeolocationSecurity::normalizeOwner($owner), ':kind' => $kind]);
        if ((int)$stmt->fetchColumn() >= max(1, $limit)) throw new StepsGeolocationJobsError('queue_full', 429);
    }
}
