<?php
/**
 * Unified, permission-aware private search (task 35).
 *
 * Search only owner-scoped metadata plus explicitly authorized family grants.
 * It never searches raw point coordinates, never returns protected exact
 * locations, and uses bounded date ranges and cursor pagination. Query strings
 * are not logged.
 */
require_once __DIR__ . '/geolocation_timeline.php';

final class StepsGeolocationSearch
{
    public const MAX_QUERY = 120;
    public const MAX_LIMIT = 50;
    public const MAX_RANGE_DAYS = 3660;
    public const TYPES = ['trip', 'place', 'visit', 'geofence', 'device', 'import', 'export', 'achievement', 'quality', 'job', 'photo', 'family'];
    public const SORTS = ['relevance', 'date_desc', 'date_asc', 'name', 'distance_desc', 'duration_desc'];

    public static function timezone(string $timezone): string
    {
        return StepsGeolocationTimeline::timezone($timezone);
    }

    /**
     * Resolve explicit or natural-language date shortcuts locally. The caller
     * always receives the resolved range and timezone.
     *
     * @return array{from:string,to:string,from_at:string,to_at:string,label:string,timezone:string,text:string,shortcuts:list<string>}
     */
    public static function parse(string $query, string $timezone = 'UTC', ?string $from = null, ?string $to = null, ?string $now = null): array
    {
        $timezone = self::timezone($timezone);
        $zone = new DateTimeZone($timezone);
        $current = $now !== null ? new DateTimeImmutable($now, $zone) : new DateTimeImmutable('now', $zone);
        $text = trim($query);
        $lower = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
        $shortcuts = [];
        $start = null;
        $end = null;
        $label = '';
        $consume = static function (string $needle) use (&$lower, &$text): void {
            $position = strpos($lower, $needle);
            if ($position === false) return;
            $lower = trim(substr_replace($lower, '', $position, strlen($needle)));
            $text = trim(substr_replace($text, '', $position, strlen($needle)));
        };

        if (preg_match('/\b(today|yesterday)\b/u', $lower, $match)) {
            $day = $match[1] === 'yesterday' ? $current->modify('-1 day') : $current;
            $start = $day->setTime(0, 0);
            $end = $start->modify('+1 day')->modify('-1 second');
            $label = $start->format('Y-m-d');
            $shortcuts[] = $match[1];
            $consume($match[0]);
        } elseif (preg_match('/\blast\s+(7|30|90)\s+days?\b/u', $lower, $match)) {
            $days = (int)$match[1];
            $start = $current->modify('-' . ($days - 1) . ' days')->setTime(0, 0);
            $end = $current->setTime(23, 59, 59);
            $label = $start->format('Y-m-d') . ' – ' . $end->format('Y-m-d');
            $shortcuts[] = 'last_' . $days . '_days';
            $consume($match[0]);
        } elseif (preg_match('/\b(this|last)\s+week\b/u', $lower, $match)) {
            $weekStart = $current->modify('monday this week')->setTime(0, 0);
            if ($match[1] === 'last') $weekStart = $weekStart->modify('-7 days');
            $start = $weekStart;
            $end = $weekStart->modify('+6 days')->setTime(23, 59, 59);
            $label = $start->format('Y-m-d') . ' – ' . $end->format('Y-m-d');
            $shortcuts[] = $match[1] . '_week';
            $consume($match[0]);
        } elseif (preg_match('/\b(this|last)\s+month\b/u', $lower, $match)) {
            $monthStart = $current->modify('first day of this month')->setTime(0, 0);
            if ($match[1] === 'last') $monthStart = $monthStart->modify('-1 month');
            $start = $monthStart;
            $end = $monthStart->modify('last day of this month')->setTime(23, 59, 59);
            $label = $start->format('Y-m');
            $shortcuts[] = $match[1] . '_month';
            $consume($match[0]);
        } elseif (preg_match('/\b(january|february|march|april|may|june|july|august|september|october|november|december)\s+(\d{4})\b/u', $lower, $match)) {
            $month = (int)date('n', strtotime($match[1] . ' 1 2000'));
            $start = (new DateTimeImmutable(sprintf('%04d-%02d-01', (int)$match[2], $month), $zone))->setTime(0, 0);
            $end = $start->modify('last day of this month')->setTime(23, 59, 59);
            $label = $start->format('Y-m');
            $shortcuts[] = 'month';
            $consume($match[0]);
        } elseif (preg_match('/\b(\d{4})-(\d{2})-(\d{2})\b/u', $lower, $match)) {
            $start = (new DateTimeImmutable($match[0], $zone))->setTime(0, 0);
            $end = $start->setTime(23, 59, 59);
            $label = $start->format('Y-m-d');
            $shortcuts[] = 'day';
            $consume($match[0]);
        } elseif (preg_match('/\b(\d{4})-(\d{2})\b/u', $lower, $match)) {
            $start = (new DateTimeImmutable($match[1] . '-' . $match[2] . '-01', $zone))->setTime(0, 0);
            $end = $start->modify('last day of this month')->setTime(23, 59, 59);
            $label = $start->format('Y-m');
            $shortcuts[] = 'month';
            $consume($match[0]);
        }

        if ($from !== null && $to !== null && trim($from) !== '' && trim($to) !== '') {
            $fromDate = new DateTimeImmutable(trim($from), $zone);
            $toDate = new DateTimeImmutable(trim($to), $zone);
            if ($toDate < $fromDate) throw new InvalidArgumentException('invalid_range');
            if ($toDate->getTimestamp() - $fromDate->getTimestamp() > self::MAX_RANGE_DAYS * 86400) throw new InvalidArgumentException('range_too_large');
            $start = $fromDate->setTime(0, 0);
            $end = $toDate->setTime(23, 59, 59);
            $label = $start->format('Y-m-d') . ' – ' . $end->format('Y-m-d');
            $shortcuts[] = 'explicit_range';
        }
        if ($start === null || $end === null) {
            $start = $current->modify('-89 days')->setTime(0, 0);
            $end = $current->setTime(23, 59, 59);
            $label = 'Last 90 days';
            $shortcuts[] = 'default_last_90_days';
        }
        return [
            'from' => $start->format('Y-m-d'),
            'to' => $end->format('Y-m-d'),
            'from_at' => $start->setTimezone(new DateTimeZone('UTC'))->format('c'),
            'to_at' => $end->setTimezone(new DateTimeZone('UTC'))->format('c'),
            'label' => $label,
            'timezone' => $timezone,
            'text' => trim($text),
            'shortcuts' => array_values(array_unique($shortcuts)),
        ];
    }

    /**
     * @return array{results:list<array<string,mixed>>,suggestions:list<string>,next_cursor:?string,range:array<string,mixed>,types:list<string>,access_state:string}
     */
    public static function search(PDO $pdo, string $owner, array $opts = []): array
    {
        $timezone = self::timezone((string)($opts['timezone'] ?? 'UTC'));
        $query = mb_substr(trim((string)($opts['q'] ?? '')), 0, self::MAX_QUERY);
        $parsed = self::parse($query, $timezone, $opts['from'] ?? null, $opts['to'] ?? null);
        $text = $parsed['text'];
        $types = self::types($opts['types'] ?? null);
        $limit = max(1, min(self::MAX_LIMIT, (int)($opts['limit'] ?? 20)));
        $cursor = self::cursorDecode((string)($opts['cursor'] ?? ''));
        $offset = $cursor;
        $sortInput = (string)($opts['sort'] ?? 'relevance');
        $sort = in_array($sortInput, self::SORTS, true) ? $sortInput : 'relevance';
        $lower = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
        if (str_contains($lower, 'longest trips')) { $sort = 'distance_desc'; $text = trim(str_replace('longest trips', '', $lower)); }
        if (str_contains($lower, 'quality issues')) { $types = ['quality']; $text = trim(str_replace('quality issues', '', $lower)); }
        if (str_contains($lower, 'pending imports')) { $types = ['import', 'job']; $text = trim(str_replace('pending imports', '', $lower)); }
        if (str_contains($lower, 'offline device')) { $types = ['device']; $text = trim(str_replace('offline device', '', $lower)); }
        if (str_contains($lower, 'new places')) { $types = ['place']; $sort = 'date_desc'; $text = trim(str_replace('new places', '', $lower)); }
        $results = [];
        $add = static function (string $type, string $id, string $title, string $summary, ?string $at, ?string $endAt, string $status, float $score = 0.0, array $extra = []) use (&$results): void {
            if ($id === '' || $title === '') return;
            $results[] = array_merge([
                'type' => $type,
                'id' => $id,
                'title' => $title,
                'summary' => $summary,
                'started_at' => $at,
                'ended_at' => $endAt,
                'status' => $status,
                'access_state' => 'authorized',
                'score' => $score,
            ], $extra);
        };
        $like = '%' . $lower . '%';
        $dateParams = [':owner' => $owner, ':from' => $parsed['from_at'], ':to' => $parsed['to_at']];
        $qWhere = static function (string $columns) use ($like): array {
            return $like === '%%' ? ['', []] : [" AND lower(concat_ws(' ', $columns)) LIKE :like", [':like' => $like]];
        };

        if (in_array('trip', $types, true)) {
            [$where, $params] = $qWhere("coalesce(title,'') || ' ' || coalesce(notes,'')");
            $stmt = $pdo->prepare("SELECT id, title, notes, started_at, ended_at, distance_m, duration_s, state FROM geo_trips WHERE user_email = :owner AND deleted_at IS NULL AND started_at <= :to AND ended_at >= :from$where ORDER BY started_at DESC LIMIT " . ($offset + $limit + 1));
            $stmt->execute($dateParams + $params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $add('trip', (string)$row['id'], (string)($row['title'] !== '' ? $row['title'] : 'Trip'), self::distanceSummary($row), $row['started_at'], $row['ended_at'], (string)$row['state'], self::matchScore($lower, (string)$row['title'] . ' ' . (string)$row['notes']), ['distance_m' => (float)$row['distance_m'], 'duration_s' => (int)$row['duration_s']]);
            }
        }
        if (in_array('place', $types, true)) {
            [$where, $params] = $qWhere("coalesce(name,'') || ' ' || coalesce(tag,'') || ' ' || coalesce(notes,'')");
            $stmt = $pdo->prepare("SELECT id, name, tag, notes, is_active, created_at, updated_at FROM geo_places WHERE user_email = :owner AND deleted_at IS NULL AND created_at <= :to$where ORDER BY is_active DESC, updated_at DESC LIMIT " . ($offset + $limit + 1));
            $stmt->execute([':owner' => $owner, ':to' => $parsed['to_at']] + $params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $add('place', (string)$row['id'], (string)$row['name'], (string)$row['tag'] . ($row['notes'] !== '' ? ' · ' . mb_substr((string)$row['notes'], 0, 80) : ''), $row['created_at'], $row['updated_at'], $row['is_active'] ? 'active' : 'archived', self::matchScore($lower, (string)$row['name'] . ' ' . (string)$row['tag']));
            }
        }
        if (in_array('visit', $types, true)) {
            [$where, $params] = $qWhere("coalesce(p.name,'') || ' ' || coalesce(p.tag,'')");
            $stmt = $pdo->prepare("SELECT v.id, v.arrived_at, v.departed_at, v.state, v.confidence, p.name, p.tag FROM geo_visits v LEFT JOIN geo_places p ON p.id = v.place_id WHERE v.user_email = :owner AND v.arrived_at BETWEEN :from AND :to$where ORDER BY v.arrived_at DESC LIMIT " . ($offset + $limit + 1));
            $stmt->execute($dateParams + $params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $add('visit', (string)$row['id'], (string)($row['name'] ?? 'Visit'), (string)($row['tag'] ?? ''), $row['arrived_at'], $row['departed_at'], (string)$row['state'], self::matchScore($lower, (string)($row['name'] ?? '')), ['confidence' => $row['confidence'] !== null ? (float)$row['confidence'] : null]);
            }
        }
        if (in_array('geofence', $types, true)) {
            [$where, $params] = $qWhere("coalesce(name,'')");
            $stmt = $pdo->prepare("SELECT id, name, is_active, created_at, updated_at FROM geo_geofences WHERE user_email = :owner AND created_at <= :to$where ORDER BY is_active DESC, name ASC LIMIT " . ($offset + $limit + 1));
            $stmt->execute([':owner' => $owner, ':to' => $parsed['to_at']] + $params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $add('geofence', (string)$row['id'], (string)$row['name'], 'Private geofence', $row['created_at'], $row['updated_at'], $row['is_active'] ? 'active' : 'disabled', self::matchScore($lower, (string)$row['name']));
            }
        }
        if (in_array('device', $types, true)) {
            [$where, $params] = $qWhere("coalesce(name,'') || ' ' || coalesce(platform,'') || ' ' || coalesce(app_version,'')");
            $stmt = $pdo->prepare("SELECT id, name, platform, app_version, status, last_seen_at, created_at FROM geo_devices WHERE user_email = :owner AND created_at <= :to$where ORDER BY last_seen_at DESC NULLS LAST LIMIT " . ($offset + $limit + 1));
            $stmt->execute([':owner' => $owner, ':to' => $parsed['to_at']] + $params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $add('device', (string)$row['id'], (string)$row['name'], trim((string)$row['platform'] . ' ' . (string)$row['app_version']), $row['last_seen_at'], $row['created_at'], (string)$row['status'], self::matchScore($lower, (string)$row['name']));
            }
        }
        if (in_array('import', $types, true)) {
            [$where, $params] = $qWhere("coalesce(filename,'') || ' ' || coalesce(source_format,'')");
            $stmt = $pdo->prepare("SELECT f.id, f.filename, f.source_format, f.state, f.created_at, f.processed_at FROM geo_import_files f WHERE f.user_email = :owner AND f.created_at <= :to$where ORDER BY f.created_at DESC LIMIT " . ($offset + $limit + 1));
            $stmt->execute([':owner' => $owner, ':to' => $parsed['to_at']] + $params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $add('import', (string)$row['id'], (string)$row['filename'], (string)$row['source_format'], $row['created_at'], $row['processed_at'], (string)$row['state'], self::matchScore($lower, (string)$row['filename']));
            }
        }
        if (in_array('export', $types, true)) {
            $stmt = $pdo->prepare("SELECT id, format, scope_json, state, created_at FROM geo_exports WHERE user_email = :owner AND created_at <= :to ORDER BY created_at DESC LIMIT " . ($offset + $limit + 1));
            $stmt->execute([':owner' => $owner, ':to' => $parsed['to_at']]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $add('export', (string)$row['id'], strtoupper((string)$row['format']) . ' export', 'Bounded account export', $row['created_at'], null, (string)$row['state'], self::matchScore($lower, (string)$row['format'] . ' export'));
            }
        }
        if (in_array('achievement', $types, true)) {
            [$where, $params] = $qWhere("coalesce(achievement_id,'') || ' ' || coalesce(family,'')");
            $stmt = $pdo->prepare("SELECT user_email, rule_version, achievement_id, family, earned, progress, target, recalculated_at FROM geo_achievements WHERE user_email = :owner AND recalculated_at <= :to$where ORDER BY earned DESC, recalculated_at DESC LIMIT " . ($offset + $limit + 1));
            $stmt->execute([':owner' => $owner, ':to' => $parsed['to_at']] + $params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $add('achievement', (string)$row['achievement_id'], ucwords(str_replace('_', ' ', (string)$row['achievement_id'])), (string)$row['family'], $row['recalculated_at'], null, $row['earned'] ? 'earned' : 'in_progress', self::matchScore($lower, (string)$row['achievement_id']), ['progress' => (float)$row['progress'], 'target' => (float)$row['target']]);
            }
        }
        if (in_array('quality', $types, true)) {
            [$where, $params] = $qWhere("coalesce(quality_reason,'') || ' ' || quality_state");
            $stmt = $pdo->prepare("SELECT id, source_at, quality_state, quality_reason, quality_score, quality_severity, accuracy_m FROM geo_location_points WHERE user_email = :owner AND source_at BETWEEN :from AND :to AND quality_state NOT IN ('accepted','pending')$where ORDER BY source_at DESC LIMIT " . ($offset + $limit + 1));
            $stmt->execute($dateParams + $params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $add('quality', (string)$row['id'], 'Quality: ' . (string)$row['quality_reason'], (string)$row['quality_state'] . ($row['accuracy_m'] !== null ? ' · ' . round((float)$row['accuracy_m']) . ' m' : ''), $row['source_at'], null, (string)$row['quality_severity'], self::matchScore($lower, (string)$row['quality_reason']), ['quality_score' => $row['quality_score'] !== null ? (float)$row['quality_score'] : null]);
            }
        }
        if (in_array('job', $types, true)) {
            [$where, $params] = $qWhere("coalesce(kind,'') || ' ' || coalesce(state,'') || ' ' || coalesce(last_error,'')");
            $stmt = $pdo->prepare("SELECT id, kind, state, processor_version, created_at, updated_at FROM geo_processor_runs WHERE user_email = :owner AND created_at <= :to$where ORDER BY created_at DESC LIMIT " . ($offset + $limit + 1));
            $stmt->execute([':owner' => $owner, ':to' => $parsed['to_at']] + $params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $add('job', (string)$row['id'], ucfirst((string)$row['kind']) . ' job', (string)$row['processor_version'], $row['created_at'], $row['updated_at'], (string)$row['state'], self::matchScore($lower, (string)$row['kind']));
            }
        }
        if (in_array('photo', $types, true)) {
            [$where, $params] = $qWhere("coalesce(original_filename,'')");
            $stmt = $pdo->prepare("SELECT id, original_filename, captured_at, state FROM geo_immich_assets WHERE user_email = :owner AND deleted_at IS NULL AND captured_at BETWEEN :from AND :to$where ORDER BY captured_at DESC LIMIT " . ($offset + $limit + 1));
            $stmt->execute($dateParams + $params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $add('photo', (string)$row['id'], (string)$row['original_filename'], 'Immich match', $row['captured_at'], null, (string)$row['state'], self::matchScore($lower, (string)$row['original_filename']));
            }
        }
        if (in_array('family', $types, true)) {
            $stmt = $pdo->prepare("SELECT id, owner_email, access_level, starts_at, ends_at, created_at FROM geo_location_grants WHERE viewer_email = :owner AND revoked_at IS NULL ORDER BY created_at DESC LIMIT " . ($offset + $limit + 1));
            $stmt->execute([':owner' => $owner]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $add('family', (string)$row['id'], 'Family access', (string)$row['access_level'], $row['created_at'], $row['ends_at'], 'authorized', self::matchScore($lower, 'family access ' . (string)$row['access_level']));
            }
        }

        usort($results, static function (array $a, array $b) use ($sort): int {
            if ($sort === 'distance_desc') return [(float)($b['distance_m'] ?? 0), (string)$b['started_at']] <=> [(float)($a['distance_m'] ?? 0), (string)$a['started_at']];
            if ($sort === 'duration_desc') return [(int)($b['duration_s'] ?? 0), (string)$b['started_at']] <=> [(int)($a['duration_s'] ?? 0), (string)$a['started_at']];
            if ($sort === 'name') return strcmp((string)$a['title'], (string)$b['title']);
            if ($sort === 'date_asc') return strcmp((string)($a['started_at'] ?? ''), (string)($b['started_at'] ?? ''));
            if ($sort === 'relevance' && abs((float)$a['score'] - (float)$b['score']) > 0.001) return (float)$b['score'] <=> (float)$a['score'];
            return strcmp((string)($b['started_at'] ?? $b['ended_at'] ?? ''), (string)($a['started_at'] ?? $a['ended_at'] ?? ''));
        });
        $results = array_values(array_filter($results, static fn(array $row): bool => $row['id'] !== ''));
        $total = count($results);
        $page = array_slice($results, $offset, $limit);
        $next = $total > $offset + $limit ? self::cursorEncode($offset + $limit) : null;
        $suggestions = [];
        foreach (array_slice($page, 0, 8) as $row) {
            if (!in_array($row['title'], $suggestions, true)) $suggestions[] = $row['title'];
        }
        return ['results' => $page, 'suggestions' => $suggestions, 'next_cursor' => $next, 'range' => $parsed, 'types' => $types, 'access_state' => 'authorized'];
    }

    public static function suggest(PDO $pdo, string $owner, array $opts = []): array
    {
        $opts['limit'] = min(8, max(1, (int)($opts['limit'] ?? 8)));
        $result = self::search($pdo, $owner, $opts);
        return ['suggestions' => $result['suggestions'], 'range' => $result['range']];
    }

    /** @return list<string> */
    public static function types($raw): array
    {
        if (is_string($raw)) $raw = array_filter(array_map('trim', explode(',', strtolower($raw))));
        if (!is_array($raw)) return self::TYPES;
        $types = array_values(array_intersect(array_map('strtolower', array_map('strval', $raw)), self::TYPES));
        return $types === [] ? self::TYPES : $types;
    }

    public static function cursorEncode(int $offset): string
    {
        return rtrim(strtr(base64_encode(json_encode(['o' => $offset])), '+/', '-_'), '=');
    }

    public static function cursorDecode(string $cursor): int
    {
        if ($cursor === '') return 0;
        $raw = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($raw === false) return 0;
        $data = json_decode($raw, true);
        return is_array($data) && isset($data['o']) ? max(0, min(10000, (int)$data['o'])) : 0;
    }

    private static function matchScore(string $query, string $text): float
    {
        if ($query === '') return 0.5;
        $text = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
        if ($text === $query) return 1.0;
        if (str_starts_with($text, $query)) return 0.85;
        if (str_contains($text, $query)) return 0.65;
        return 0.3;
    }

    private static function distanceSummary(array $row): string
    {
        $distance = (float)($row['distance_m'] ?? 0);
        $duration = (int)($row['duration_s'] ?? 0);
        $parts = [];
        if ($distance > 0) $parts[] = $distance >= 1000 ? round($distance / 1000, 1) . ' km' : round($distance) . ' m';
        if ($duration > 0) $parts[] = intdiv($duration, 3600) > 0 ? intdiv($duration, 3600) . 'h ' . intdiv($duration % 3600, 60) . 'm' : intdiv($duration, 60) . 'm';
        return implode(' · ', $parts);
    }
}
