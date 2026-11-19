<?php
/**
 * Timeline and history browsing (task 16).
 *
 * Ranges are computed in an explicit IANA display timezone, so a day view
 * follows daylight-saving changes instead of assuming 24 hours. Entries are
 * tenant-scoped and paginated with an opaque cursor; map links are bounded
 * geometry, never unscoped coordinates.
 */
require_once __DIR__ . '/geolocation_quality.php';

final class StepsGeolocationTimeline
{
    public const VIEWS = ['day', 'month', 'year', 'interval'];
    public const MAX_LIMIT = 200;
    public const MAX_GAP_POINTS = 20000;

    public static function timezone(string $timezone): string
    {
        $timezone = trim($timezone);
        if ($timezone === '') return 'UTC';
        try {
            new DateTimeZone($timezone);
            return $timezone;
        } catch (Throwable $e) {
            return 'UTC';
        }
    }

    /**
     * @return array{from:string,to:string,from_local:string,to_local:string,label:string,view:string,timezone:string}
     */
    public static function range(string $view, ?string $anchor, ?string $from, ?string $to, string $timezone): array
    {
        $timezone = self::timezone($timezone);
        $zone = new DateTimeZone($timezone);
        if (!in_array($view, self::VIEWS, true)) $view = 'day';

        if ($view === 'interval') {
            $start = new DateTimeImmutable((string)($from ?? 'now'), $zone);
            $end = new DateTimeImmutable((string)($to ?? 'now'), $zone);
            if ($end <= $start) throw new InvalidArgumentException('invalid_interval');
            return [
                'from' => $start->setTimezone(new DateTimeZone('UTC'))->format('c'),
                'to' => $end->setTimezone(new DateTimeZone('UTC'))->format('c'),
                'from_local' => $start->format('c'),
                'to_local' => $end->format('c'),
                'label' => $start->format('Y-m-d H:i') . ' – ' . $end->format('Y-m-d H:i'),
                'view' => $view,
                'timezone' => $timezone,
            ];
        }

        $date = $anchor !== null && trim($anchor) !== '' && strtolower(trim($anchor)) !== 'now'
            ? new DateTimeImmutable(trim($anchor), $zone)
            : new DateTimeImmutable('now', $zone);
        $start = match ($view) {
            'month' => $date->setDate((int)$date->format('Y'), (int)$date->format('n'), 1)->setTime(0, 0),
            'year' => $date->setDate((int)$date->format('Y'), 1, 1)->setTime(0, 0),
            default => $date->setTime(0, 0),
        };
        $end = match ($view) {
            'month' => $start->modify('+1 month'),
            'year' => $start->modify('+1 year'),
            default => $start->modify('+1 day'),
        };
        return [
            'from' => $start->setTimezone(new DateTimeZone('UTC'))->format('c'),
            'to' => $end->setTimezone(new DateTimeZone('UTC'))->format('c'),
            'from_local' => $start->format('c'),
            'to_local' => $end->format('c'),
            'label' => $view === 'day' ? $start->format('Y-m-d') : ($view === 'month' ? $start->format('Y-m') : $start->format('Y')),
            'view' => $view,
            'timezone' => $timezone,
        ];
    }

    public static function localTime(string $utc, string $timezone): string
    {
        try {
            return (new DateTimeImmutable($utc))->setTimezone(new DateTimeZone(self::timezone($timezone)))->format('c');
        } catch (Throwable $e) {
            return $utc;
        }
    }

    public static function cursorEncode(string $at, string $kind, string $id): string
    {
        return rtrim(strtr(base64_encode(json_encode([$at, $kind, $id])), '+/', '-_'), '=');
    }

    /** @return array{at:string,kind:string,id:string}|null */
    public static function cursorDecode(string $cursor): ?array
    {
        if ($cursor === '') return null;
        $raw = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($raw === false) return null;
        $parts = json_decode($raw, true);
        if (!is_array($parts) || count($parts) !== 3 || !is_string($parts[0])) return null;
        return ['at' => $parts[0], 'kind' => (string)$parts[1], 'id' => (string)$parts[2]];
    }

    /**
     * Canonical gaps between consecutive accepted points.
     *
     * @param list<string> $times UTC timestamps in ascending order
     * @return list<array{started_at:string,ended_at:string,duration_s:int}>
     */
    public static function gaps(array $times, int $thresholdS): array
    {
        $gaps = [];
        $previous = null;
        foreach ($times as $time) {
            $epoch = strtotime((string)$time);
            if ($epoch === false) continue;
            if ($previous !== null && ($epoch - $previous) >= $thresholdS) {
                $gaps[] = [
                    'started_at' => gmdate('c', $previous),
                    'ended_at' => gmdate('c', $epoch),
                    'duration_s' => $epoch - $previous,
                ];
            }
            $previous = $epoch;
        }
        return $gaps;
    }

    public static function gapThresholdS(): int
    {
        $raw = getenv('GEO_TIMELINE_GAP_S');
        if (is_string($raw) && is_numeric($raw)) return max(300, min(86400, (int)$raw));
        return 1800;
    }

    /**
     * @return array{entries:list<array<string,mixed>>,next_cursor:?string,processing:array<string,mixed>}
     */
    public static function entries(PDO $pdo, string $owner, array $opts): array
    {
        $timezone = self::timezone((string)($opts['timezone'] ?? 'UTC'));
        $range = self::range((string)($opts['view'] ?? 'day'), $opts['at'] ?? null, $opts['from'] ?? null, $opts['to'] ?? null, $timezone);
        $limit = max(1, min(self::MAX_LIMIT, (int)($opts['limit'] ?? 50)));
        $cursor = isset($opts['cursor']) && is_string($opts['cursor']) ? self::cursorDecode($opts['cursor']) : null;
        $wanted = is_array($opts['include'] ?? null) ? $opts['include'] : ['trips', 'stops', 'visits', 'gaps'];
        $query = trim((string)($opts['q'] ?? ''));
        $query = function_exists('mb_strtolower') ? mb_strtolower($query) : strtolower($query);
        $placeId = trim((string)($opts['place_id'] ?? ''));
        $deviceId = trim((string)($opts['device_id'] ?? ''));
        $entries = [];

        $base = [
            ':owner' => $owner,
            ':from' => $range['from'],
            ':to' => $range['to'],
        ];
        $cursorSqlFor = static function (string $timeColumn, string $idColumn) use ($cursor): string {
            if ($cursor === null) return '';
            return " AND ($timeColumn < :cursor_at OR ($timeColumn = :cursor_at AND $idColumn::text < :cursor_id))";
        };

        if (in_array('trips', $wanted, true)) {
            $stmt = $pdo->prepare(
                "SELECT id, started_at, ended_at, distance_m, title, boundary_reason, processor_version, state,
                        ST_XMin(geometry) AS min_lon, ST_YMin(geometry) AS min_lat,
                        ST_XMax(geometry) AS max_lon, ST_YMax(geometry) AS max_lat
                   FROM geo_trips
                  WHERE user_email = :owner AND deleted_at IS NULL
                    AND started_at >= :from AND started_at < :to" . $cursorSqlFor('started_at', 'id') .
                " ORDER BY started_at DESC, id DESC LIMIT " . ($limit + 1)
            );
            $params = $base;
            if ($cursor !== null) { $params[':cursor_at'] = $cursor['at']; $params[':cursor_id'] = $cursor['id']; }
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $entries[] = [
                    'kind' => 'trip',
                    'id' => (string)$row['id'],
                    'started_at' => $row['started_at'],
                    'ended_at' => $row['ended_at'],
                    'title' => (string)($row['title'] !== '' ? $row['title'] : $row['boundary_reason']),
                    'detail' => $row['boundary_reason'],
                    'distance_m' => (float)$row['distance_m'],
                    'processor_version' => (string)$row['processor_version'],
                    'state' => (string)$row['state'],
                    'bbox' => self::bbox($row),
                ];
            }
        }

        if (in_array('stops', $wanted, true)) {
            $placeFilter = $placeId !== ''
                ? " AND EXISTS (SELECT 1 FROM geo_places p WHERE p.id = :place AND p.user_email = :owner AND p.deleted_at IS NULL AND ST_DWithin(p.position::geography, s.position::geography, p.radius_m))"
                : '';
            $stmt = $pdo->prepare(
                "SELECT s.id, s.started_at, s.ended_at, s.radius_m, s.confidence, s.state,
                        ST_Y(s.position) AS lat, ST_X(s.position) AS lon
                   FROM geo_stops s
                  WHERE s.user_email = :owner AND s.deleted_at IS NULL
                    AND s.started_at >= :from AND s.started_at < :to" . $cursorSqlFor('s.started_at', 's.id') . $placeFilter .
                " ORDER BY s.started_at DESC, s.id DESC LIMIT " . ($limit + 1)
            );
            $params = $base;
            if ($cursor !== null) { $params[':cursor_at'] = $cursor['at']; $params[':cursor_id'] = $cursor['id']; }
            if ($placeId !== '') $params[':place'] = $placeId;
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $pad = ((float)$row['radius_m']) / 111320.0;
                $entries[] = [
                    'kind' => 'stop',
                    'id' => (string)$row['id'],
                    'started_at' => $row['started_at'],
                    'ended_at' => $row['ended_at'],
                    'title' => 'stop',
                    'detail' => null,
                    'radius_m' => (float)$row['radius_m'],
                    'confidence' => $row['confidence'] !== null ? (float)$row['confidence'] : null,
                    'state' => (string)$row['state'],
                    'bbox' => [
                        'minLon' => (float)$row['lon'] - $pad,
                        'minLat' => (float)$row['lat'] - $pad,
                        'maxLon' => (float)$row['lon'] + $pad,
                        'maxLat' => (float)$row['lat'] + $pad,
                    ],
                ];
            }
        }

        if (in_array('visits', $wanted, true)) {
            $placeFilter = $placeId !== '' ? ' AND v.place_id = :place' : '';
            $stmt = $pdo->prepare(
                "SELECT v.id, v.arrived_at AS started_at, v.departed_at AS ended_at, v.place_id, v.confidence, v.state,
                        p.name AS place_name, p.tag AS place_tag, p.color AS place_color, p.radius_m,
                        ST_Y(s.position) AS lat, ST_X(s.position) AS lon
                   FROM geo_visits v
              LEFT JOIN geo_places p ON p.id = v.place_id
              LEFT JOIN geo_stops s ON s.id = v.stop_id
                  WHERE v.user_email = :owner
                    AND v.arrived_at >= :from AND v.arrived_at < :to" . $cursorSqlFor('v.arrived_at', 'v.id') . $placeFilter .
                " ORDER BY v.arrived_at DESC, v.id DESC LIMIT " . ($limit + 1)
            );
            $params = $base;
            if ($cursor !== null) { $params[':cursor_at'] = $cursor['at']; $params[':cursor_id'] = $cursor['id']; }
            if ($placeId !== '') $params[':place'] = $placeId;
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $pad = ((float)($row['radius_m'] ?? 0)) / 111320.0;
                $entries[] = [
                    'kind' => 'visit',
                    'id' => (string)$row['id'],
                    'started_at' => $row['started_at'],
                    'ended_at' => $row['ended_at'],
                    'title' => (string)($row['place_name'] ?? 'visit'),
                    'detail' => (string)($row['place_tag'] ?? ''),
                    'place_id' => $row['place_id'] !== null ? (string)$row['place_id'] : null,
                    'color' => (string)($row['place_color'] ?? ''),
                    'confidence' => $row['confidence'] !== null ? (float)$row['confidence'] : null,
                    'state' => (string)$row['state'],
                    'bbox' => isset($row['lat'], $row['lon'])
                        ? ['minLon' => (float)$row['lon'] - $pad, 'minLat' => (float)$row['lat'] - $pad, 'maxLon' => (float)$row['lon'] + $pad, 'maxLat' => (float)$row['lat'] + $pad]
                        : null,
                ];
            }
        }

        if (in_array('gaps', $wanted, true) && $placeId === '' && $deviceId === '') {
            $stmt = $pdo->prepare(
                "SELECT source_at FROM geo_location_points
                  WHERE user_email = :owner AND quality_state = 'accepted'
                    AND source_at >= :from AND source_at < :to
               ORDER BY source_at ASC LIMIT " . self::MAX_GAP_POINTS
            );
            $stmt->execute([':owner' => $owner, ':from' => $range['from'], ':to' => $range['to']]);
            $times = array_map(static fn(array $row): string => (string)$row['source_at'], $stmt->fetchAll(PDO::FETCH_ASSOC));
            $threshold = self::gapThresholdS();
            $previous = null;
            foreach ($times as $time) {
                $epoch = strtotime((string)$time);
                if ($epoch === false) continue;
                if ($previous !== null && ($epoch - $previous) >= $threshold) {
                    $entries[] = [
                        'kind' => 'gap',
                        'id' => 'gap-' . $previous . '-' . $epoch,
                        'started_at' => gmdate('c', $previous),
                        'ended_at' => gmdate('c', $epoch),
                        'title' => 'gap',
                        'detail' => null,
                        'duration_s' => $epoch - $previous,
                        'state' => 'ready',
                        'bbox' => null,
                    ];
                }
                $previous = $epoch;
            }
        }

        if ($query !== '') {
            $entries = array_values(array_filter($entries, static function (array $entry) use ($query): bool {
                $haystack = mb_strtolower(trim((string)$entry['title'] . ' ' . (string)($entry['detail'] ?? '')));
                return $haystack !== '' && str_contains($haystack, $query);
            }));
        }

        usort($entries, static fn(array $a, array $b): int => [$b['started_at'], (string)$b['kind'], (string)$b['id']] <=> [$a['started_at'], (string)$a['kind'], (string)$a['id']]);
        if ($cursor !== null) {
            $entries = array_values(array_filter($entries, static fn(array $entry): bool => ([$entry['started_at'], (string)$entry['kind'], (string)$entry['id']] <=> [$cursor['at'], $cursor['kind'], $cursor['id']]) < 0));
            usort($entries, static fn(array $a, array $b): int => [$b['started_at'], (string)$b['kind'], (string)$b['id']] <=> [$a['started_at'], (string)$a['kind'], (string)$a['id']]);
        }
        $next = null;
        if (count($entries) > $limit) {
            $entries = array_slice($entries, 0, $limit);
            $last = $entries[count($entries) - 1];
            $next = self::cursorEncode((string)$last['started_at'], (string)$last['kind'], (string)$last['id']);
        }
        foreach ($entries as &$entry) {
            $entry['local_started_at'] = self::localTime((string)$entry['started_at'], $timezone);
            $entry['local_ended_at'] = $entry['ended_at'] !== null ? self::localTime((string)$entry['ended_at'], $timezone) : null;
            $entry['timezone'] = $timezone;
        }
        unset($entry);

        $runs = $pdo->prepare(
            "SELECT DISTINCT ON (kind) kind, state, processor_version, updated_at
               FROM geo_processor_runs WHERE user_email = :owner ORDER BY kind, created_at DESC"
        );
        $runs->execute([':owner' => $owner]);
        $processing = ['runs' => $runs->fetchAll(PDO::FETCH_ASSOC)];
        $quality = $pdo->prepare(
            "SELECT quality_state, COUNT(*) AS total FROM geo_location_points
              WHERE user_email = :owner AND source_at >= :from AND source_at < :to GROUP BY quality_state"
        );
        $quality->execute([':owner' => $owner, ':from' => $range['from'], ':to' => $range['to']]);
        $processing['quality'] = array_column($quality->fetchAll(PDO::FETCH_ASSOC), 'total', 'quality_state');

        return ['entries' => $entries, 'next_cursor' => $next, 'processing' => $processing, 'range' => $range];
    }

    private static function bbox(array $row): ?array
    {
        if (!isset($row['min_lon'], $row['min_lat'], $row['max_lon'], $row['max_lat']) || $row['min_lon'] === null) return null;
        return [
            'minLon' => (float)$row['min_lon'],
            'minLat' => (float)$row['min_lat'],
            'maxLon' => (float)$row['max_lon'],
            'maxLat' => (float)$row['max_lat'],
        ];
    }
}
