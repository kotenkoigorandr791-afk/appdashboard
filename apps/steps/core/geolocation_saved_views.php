<?php
/**
 * Private saved views (task 35).
 *
 * A saved view stores only a validated query and presentation. It never stores
 * result ids, coordinates, secrets, copied family data or grants access by
 * itself: every apply call re-authorizes and resolves relative periods again.
 */
require_once __DIR__ . '/geolocation_search.php';
require_once __DIR__ . '/geolocation_map.php';
require_once __DIR__ . '/geolocation_matching.php';

final class StepsGeolocationSavedViewError extends RuntimeException
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

final class StepsGeolocationSavedViews
{
    public const CONTEXTS = ['dashboard', 'map', 'timeline', 'trips', 'places', 'quality', 'jobs', 'analytics'];
    public const PERIODS = ['today', 'yesterday', 'last_7_days', 'last_30_days', 'last_90_days', 'this_week', 'last_week', 'this_month', 'last_month', 'custom'];
    public const MAX_NAME = 80;
    public const MAX_DESCRIPTION = 300;

    public static function templates(): array
    {
        return [
            ['key' => 'today', 'name' => 'Today', 'context' => 'dashboard', 'date_period' => 'today'],
            ['key' => 'last_7_days', 'name' => 'Last 7 Days', 'context' => 'dashboard', 'date_period' => 'last_7_days'],
            ['key' => 'trips', 'name' => 'Trips', 'context' => 'trips', 'date_period' => 'last_30_days', 'query' => ['types' => ['trip']]],
            ['key' => 'places', 'name' => 'Places', 'context' => 'places', 'date_period' => 'last_90_days', 'query' => ['types' => ['place']]],
            ['key' => 'new_places', 'name' => 'New Places', 'context' => 'places', 'date_period' => 'last_30_days', 'query' => ['types' => ['place'], 'sort' => 'date_desc']],
            ['key' => 'quality_issues', 'name' => 'Quality Issues', 'context' => 'quality', 'date_period' => 'last_30_days', 'query' => ['types' => ['quality']]],
            ['key' => 'pending_sync', 'name' => 'Pending Sync', 'context' => 'jobs', 'date_period' => 'last_7_days', 'query' => ['types' => ['job', 'import']]],
            ['key' => 'geofence_events', 'name' => 'Geofence Events', 'context' => 'map', 'date_period' => 'last_30_days', 'query' => ['layers' => ['geofences']]],
            ['key' => 'import_history', 'name' => 'Import History', 'context' => 'jobs', 'date_period' => 'last_90_days', 'query' => ['types' => ['import']]],
            ['key' => 'data_for_export', 'name' => 'Data for Export', 'context' => 'jobs', 'date_period' => 'last_30_days', 'query' => ['types' => ['export']]],
        ];
    }

    public static function list(PDO $pdo, string $owner): array
    {
        $stmt = $pdo->prepare('SELECT * FROM geo_saved_views WHERE user_email = :owner ORDER BY is_default DESC, is_pinned DESC, sort_order ASC, updated_at DESC');
        $stmt->execute([':owner' => $owner]);
        $views = array_map([self::class, 'shape'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        return ['views' => $views, 'templates' => self::templates()];
    }

    public static function get(PDO $pdo, string $owner, string $id): array
    {
        if (!self::isUuid($id)) throw new StepsGeolocationSavedViewError('invalid_view');
        $stmt = $pdo->prepare('SELECT * FROM geo_saved_views WHERE id = :id AND user_email = :owner');
        $stmt->execute([':id' => $id, ':owner' => $owner]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new StepsGeolocationSavedViewError('view_not_found', 404);
        return self::shape($row);
    }

    public static function create(PDO $pdo, string $owner, array $input): array
    {
        $view = self::validate($pdo, $owner, $input);
        $id = self::uuid();
        $pdo->beginTransaction();
        try {
            if ($view['is_default']) $pdo->prepare('UPDATE geo_saved_views SET is_default = false WHERE user_email = :owner')->execute([':owner' => $owner]);
            $stmt = $pdo->prepare(
                "INSERT INTO geo_saved_views(id, user_email, name, description, icon, color, context, query_json, date_period, date_from, date_to, timezone, is_pinned, is_default, sort_order, template_key)
                 VALUES(:id, :owner, :name, :description, :icon, :color, :context, :query, :period, :from, :to, :tz, :pinned, :default, :sort, :template)"
            );
            $stmt->execute([
                ':id' => $id, ':owner' => $owner, ':name' => $view['name'], ':description' => $view['description'],
                ':icon' => $view['icon'], ':color' => $view['color'], ':context' => $view['context'],
                ':query' => json_encode($view['query'], JSON_UNESCAPED_SLASHES), ':period' => $view['date_period'],
                ':from' => $view['date_from'], ':to' => $view['date_to'], ':tz' => $view['timezone'],
                ':pinned' => $view['is_pinned'] ? 1 : 0, ':default' => $view['is_default'] ? 1 : 0,
                ':sort' => $view['sort_order'], ':template' => $view['template_key'],
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        return self::get($pdo, $owner, $id);
    }

    public static function update(PDO $pdo, string $owner, string $id, array $input): array
    {
        $existing = self::get($pdo, $owner, $id);
        $merged = array_merge($existing, $input);
        $view = self::validate($pdo, $owner, $merged);
        $pdo->beginTransaction();
        try {
            if ($view['is_default']) $pdo->prepare('UPDATE geo_saved_views SET is_default = false WHERE user_email = :owner AND id <> :id')->execute([':owner' => $owner, ':id' => $id]);
            $stmt = $pdo->prepare(
                "UPDATE geo_saved_views SET name = :name, description = :description, icon = :icon, color = :color, context = :context, query_json = :query, date_period = :period, date_from = :from, date_to = :to, timezone = :tz, is_pinned = :pinned, is_default = :default, sort_order = :sort, template_key = :template, version = version + 1, updated_at = now() WHERE id = :id AND user_email = :owner"
            );
            $stmt->execute([
                ':name' => $view['name'], ':description' => $view['description'], ':icon' => $view['icon'], ':color' => $view['color'],
                ':context' => $view['context'], ':query' => json_encode($view['query'], JSON_UNESCAPED_SLASHES),
                ':period' => $view['date_period'], ':from' => $view['date_from'], ':to' => $view['date_to'], ':tz' => $view['timezone'],
                ':pinned' => $view['is_pinned'] ? 1 : 0, ':default' => $view['is_default'] ? 1 : 0,
                ':sort' => $view['sort_order'], ':template' => $view['template_key'], ':id' => $id, ':owner' => $owner,
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        return self::get($pdo, $owner, $id);
    }

    public static function delete(PDO $pdo, string $owner, string $id): void
    {
        self::get($pdo, $owner, $id);
        $pdo->prepare('DELETE FROM geo_saved_views WHERE id = :id AND user_email = :owner')->execute([':id' => $id, ':owner' => $owner]);
    }

    public static function duplicate(PDO $pdo, string $owner, string $id): array
    {
        $existing = self::get($pdo, $owner, $id);
        $existing['name'] = mb_substr($existing['name'] . ' copy', 0, self::MAX_NAME);
        $existing['is_default'] = false;
        $existing['is_pinned'] = false;
        unset($existing['id'], $existing['created_at'], $existing['updated_at']);
        return self::create($pdo, $owner, $existing);
    }

    public static function apply(PDO $pdo, string $owner, string $id, string $timezone = 'UTC'): array
    {
        $view = self::get($pdo, $owner, $id);
        $range = self::resolvePeriod($view['date_period'], $view['date_from'], $view['date_to'], $timezone);
        return ['view' => $view, 'range' => $range, 'filters_removed' => false, 'access_state' => 'authorized'];
    }

    public static function resolvePeriod(string $period, ?string $from, ?string $to, string $timezone = 'UTC'): array
    {
        $timezone = StepsGeolocationSearch::timezone($timezone);
        $zone = new DateTimeZone($timezone);
        $now = new DateTimeImmutable('now', $zone);
        $start = null;
        $end = null;
        if ($period === 'today') { $start = $now->setTime(0, 0); $end = $now->setTime(23, 59, 59); }
        elseif ($period === 'yesterday') { $start = $now->modify('-1 day')->setTime(0, 0); $end = $start->setTime(23, 59, 59); }
        elseif ($period === 'last_7_days') { $start = $now->modify('-6 days')->setTime(0, 0); $end = $now->setTime(23, 59, 59); }
        elseif ($period === 'last_90_days') { $start = $now->modify('-89 days')->setTime(0, 0); $end = $now->setTime(23, 59, 59); }
        elseif ($period === 'this_week') { $start = $now->modify('monday this week')->setTime(0, 0); $end = $start->modify('+6 days')->setTime(23, 59, 59); }
        elseif ($period === 'last_week') { $start = $now->modify('monday this week')->modify('-7 days')->setTime(0, 0); $end = $start->modify('+6 days')->setTime(23, 59, 59); }
        elseif ($period === 'this_month') { $start = $now->modify('first day of this month')->setTime(0, 0); $end = $start->modify('last day of this month')->setTime(23, 59, 59); }
        elseif ($period === 'last_month') { $start = $now->modify('first day of last month')->setTime(0, 0); $end = $start->modify('last day of this month')->setTime(23, 59, 59); }
        elseif ($period === 'custom') {
            if ($from === null || $to === null) throw new StepsGeolocationSavedViewError('invalid_range');
            $start = (new DateTimeImmutable($from, $zone))->setTime(0, 0);
            $end = (new DateTimeImmutable($to, $zone))->setTime(23, 59, 59);
            if ($end < $start || $end->getTimestamp() - $start->getTimestamp() > StepsGeolocationSearch::MAX_RANGE_DAYS * 86400) throw new StepsGeolocationSavedViewError('invalid_range');
        } else {
            $start = $now->modify('-29 days')->setTime(0, 0);
            $end = $now->setTime(23, 59, 59);
            $period = 'last_30_days';
        }
        if ($start === null || $end === null) throw new StepsGeolocationSavedViewError('invalid_range');
        return [
            'from' => $start->format('Y-m-d'), 'to' => $end->format('Y-m-d'),
            'from_at' => $start->setTimezone(new DateTimeZone('UTC'))->format('c'),
            'to_at' => $end->setTimezone(new DateTimeZone('UTC'))->format('c'),
            'label' => $start->format('Y-m-d') . ' – ' . $end->format('Y-m-d'),
            'timezone' => $timezone, 'period' => $period,
        ];
    }

    public static function validate(PDO $pdo, string $owner, array $input): array
    {
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > self::MAX_NAME) throw new StepsGeolocationSavedViewError('invalid_name');
        $description = mb_substr(trim((string)($input['description'] ?? '')), 0, self::MAX_DESCRIPTION);
        $context = strtolower(trim((string)($input['context'] ?? 'dashboard')));
        if (!in_array($context, self::CONTEXTS, true)) throw new StepsGeolocationSavedViewError('invalid_context');
        $period = strtolower(trim((string)($input['date_period'] ?? 'last_30_days')));
        if (!in_array($period, self::PERIODS, true)) throw new StepsGeolocationSavedViewError('invalid_period');
        $timezone = StepsGeolocationSearch::timezone((string)($input['timezone'] ?? 'UTC'));
        $from = $period === 'custom' ? trim((string)($input['date_from'] ?? '')) : null;
        $to = $period === 'custom' ? trim((string)($input['date_to'] ?? '')) : null;
        if ($period === 'custom') { self::resolvePeriod($period, $from, $to, $timezone); }
        $icon = mb_substr(trim((string)($input['icon'] ?? '')), 0, 8);
        $color = trim((string)($input['color'] ?? ''));
        if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) throw new StepsGeolocationSavedViewError('invalid_color');
        $queryInput = $input['query'] ?? [];
        if (is_string($queryInput)) $queryInput = json_decode($queryInput, true) ?: [];
        if (!is_array($queryInput)) $queryInput = [];
        $query = self::validateQuery($queryInput);
        return [
            'name' => $name, 'description' => $description, 'icon' => $icon, 'color' => $color,
            'context' => $context, 'query' => $query, 'date_period' => $period,
            'date_from' => $from, 'date_to' => $to, 'timezone' => $timezone,
            'is_pinned' => !empty($input['is_pinned']), 'is_default' => !empty($input['is_default']),
            'sort_order' => max(0, min(9999, (int)($input['sort_order'] ?? 0))),
            'template_key' => mb_substr(trim((string)($input['template_key'] ?? '')), 0, 40),
        ];
    }

    public static function validateQuery(array $input): array
    {
        $out = [];
        if (isset($input['q'])) { $q = mb_substr(trim((string)$input['q']), 0, StepsGeolocationSearch::MAX_QUERY); if ($q !== '') $out['q'] = $q; }
        if (isset($input['types'])) $out['types'] = StepsGeolocationSearch::types($input['types']);
        if (isset($input['device_id'])) { $id = trim((string)$input['device_id']); if ($id !== '' && !self::isUuid($id)) throw new StepsGeolocationSavedViewError('invalid_device'); if ($id !== '') $out['device_id'] = $id; }
        if (isset($input['place_id'])) { $id = trim((string)$input['place_id']); if ($id !== '' && !self::isUuid($id)) throw new StepsGeolocationSavedViewError('invalid_place'); if ($id !== '') $out['place_id'] = $id; }
        if (isset($input['tag'])) { $tag = preg_replace('/[^a-z0-9_-]+/', '', strtolower((string)$input['tag'])) ?? ''; if ($tag !== '') $out['tag'] = substr($tag, 0, 32); }
        if (isset($input['source_type']) && in_array($input['source_type'], ['mobile', 'import', 'api'], true)) $out['source_type'] = $input['source_type'];
        if (isset($input['quality']) && in_array($input['quality'], ['accepted', 'suspect', 'flagged', 'rejected', 'excluded_by_user', 'private_redacted', 'not_enough_evidence'], true)) $out['quality'] = $input['quality'];
        foreach (['has_photo', 'has_render', 'has_gap', 'corrected', 'family_scope'] as $flag) if (isset($input[$flag])) $out[$flag] = (bool)$input[$flag];
        foreach (['min_distance_m', 'max_distance_m', 'min_duration_s', 'max_duration_s'] as $number) {
            if (isset($input[$number]) && is_numeric($input[$number])) $out[$number] = (float)max(0, min(100000000, (float)$input[$number]));
        }
        if (isset($input['geometry_mode']) && in_array($input['geometry_mode'], StepsGeolocationMatching::GEOMETRY_MODES, true)) $out['geometry_mode'] = $input['geometry_mode'];
        if (isset($input['sort']) && in_array($input['sort'], StepsGeolocationSearch::SORTS, true)) $out['sort'] = $input['sort'];
        if (isset($input['view']) && in_array($input['view'], StepsGeolocationTimeline::VIEWS, true)) $out['view'] = $input['view'];
        if (isset($input['layers']) && is_array($input['layers'])) {
            $allowed = array_merge(StepsGeolocationMap::DEFAULT_LAYERS, ['points', 'heatmap', 'explored', 'photos', 'speed', 'improved']);
            $layers = array_values(array_intersect(array_map('strval', $input['layers']), $allowed));
            if ($layers !== []) $out['layers'] = $layers;
        }
        return $out;
    }

    public static function exportDefinition(PDO $pdo, string $owner, string $id): array
    {
        $view = self::get($pdo, $owner, $id);
        unset($view['id'], $view['created_at'], $view['updated_at'], $view['user_email'], $view['is_default']);
        return ['format' => 'steps-saved-view', 'version' => 1, 'view' => $view];
    }

    public static function importDefinition(PDO $pdo, string $owner, array $input): array
    {
        if (($input['format'] ?? '') !== 'steps-saved-view' || (int)($input['version'] ?? 0) !== 1 || !is_array($input['view'] ?? null)) {
            throw new StepsGeolocationSavedViewError('invalid_definition');
        }
        $view = $input['view'];
        unset($view['id'], $view['user_email'], $view['created_at'], $view['updated_at']);
        $view['is_default'] = false;
        return self::create($pdo, $owner, $view);
    }

    public static function shape(array $row): array
    {
        $row['query'] = json_decode((string)($row['query_json'] ?? '{}'), true) ?: [];
        unset($row['query_json'], $row['user_email']);
        $row['version'] = (int)($row['version'] ?? 1);
        $row['is_pinned'] = !empty($row['is_pinned']);
        $row['is_default'] = !empty($row['is_default']);
        $row['sort_order'] = (int)($row['sort_order'] ?? 0);
        return $row;
    }

    public static function isUuid(string $value): bool
    {
        return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
    }

    public static function uuid(): string
    {
        $b = bin2hex(random_bytes(16));
        return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
    }
}
