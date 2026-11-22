<?php
require_once __DIR__ . '/geolocation_osm.php';
require_once __DIR__ . '/geolocation_place_ranking.php';
require_once __DIR__ . '/geolocation_place_profiles.php';
require_once __DIR__ . '/geolocation_places.php';

final class StepsGeolocationPlaceResolver
{
    public const MAX_ATTEMPTS = 6;
    public const STATUSES = ['pending', 'provisional', 'confirmed', 'manual', 'unidentified'];

    public const PROCESSOR_VERSION = 'places-1';
    public const KIND = 'osm_places';

    public static function enqueue(PDO $pdo, string $owner, bool $requeue = false): array
    {
        $queued = 0;
        if ($requeue) {
            $stmt = $pdo->prepare("UPDATE geo_visits SET detection_status = 'pending', detection_method = '', retry_after = NULL, resolve_attempts = 0, osm_type = NULL, osm_id = NULL, display_name = CASE WHEN place_id IS NULL THEN '' ELSE display_name END, category = CASE WHEN place_id IS NULL THEN '' ELSE category END, address = CASE WHEN place_id IS NULL THEN '' ELSE address END, resolved_at = NULL WHERE user_email = :o AND COALESCE(override_json->>'manual','false') <> 'true' AND place_id IS NULL AND arrived_at > now() - interval '90 days'");
            $stmt->execute([':o' => $owner]);
            $queued = $stmt->rowCount();
            $pdo->prepare("DELETE FROM geo_visit_candidates WHERE user_email = :o AND visit_id IN (SELECT id FROM geo_visits WHERE user_email = :o2 AND detection_status = 'pending')")->execute([':o' => $owner, ':o2' => $owner]);
        }
        $ins = $pdo->prepare(
            "INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version, details_json)
             SELECT :id, :owner, :kind, 'pending', :version, CAST(:details AS jsonb)
              WHERE NOT EXISTS (
                    SELECT 1 FROM geo_processor_runs
                     WHERE user_email = :owner AND kind = :kind2 AND state IN ('pending','processing')
              )"
        );
        $ins->execute([
            ':id' => StepsGeolocationPlaces::uuid(),
            ':owner' => $owner,
            ':kind' => self::KIND,
            ':kind2' => self::KIND,
            ':version' => self::PROCESSOR_VERSION,
            ':details' => json_encode(['requeue' => $requeue, 'queued_visits' => $queued], JSON_UNESCAPED_SLASHES),
        ]);
        return ['job' => $ins->rowCount() > 0, 'visits' => $queued, 'pending' => self::pendingCount($pdo, $owner)];
    }

    public static function pendingCount(PDO $pdo, string $owner): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM geo_visits WHERE user_email = :o AND place_id IS NULL AND COALESCE(override_json->>'manual','false') <> 'true' AND (detection_status = 'pending' OR (detection_status = 'provisional' AND detection_method = 'stop_open'))");
        $stmt->execute([':o' => $owner]);
        return (int)$stmt->fetchColumn();
    }

    public static function lastRun(PDO $pdo, string $owner): ?array
    {
        $stmt = $pdo->prepare('SELECT id, state, details_json, created_at, updated_at FROM geo_processor_runs WHERE user_email = :o AND kind = :k ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([':o' => $owner, ':k' => self::KIND]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['details_json'] = json_decode((string)$row['details_json'], true) ?: [];
        return $row;
    }

    public static function profile(PDO $pdo, string $owner): string
    {
        $stmt = $pdo->prepare("SELECT value FROM steps_settings WHERE user_email = :o AND key = 'occupation'");
        $stmt->execute([':o' => $owner]);
        return StepsGeolocationPlaceProfiles::normalize((string)($stmt->fetchColumn() ?: ''));
    }

    public static function setProfile(PDO $pdo, string $owner, string $profile): string
    {
        $profile = StepsGeolocationPlaceProfiles::normalize($profile);
        $pdo->prepare("INSERT INTO steps_settings(user_email, key, value) VALUES(:o, 'occupation', :p) ON CONFLICT (user_email, key) DO UPDATE SET value = EXCLUDED.value")
            ->execute([':o' => $owner, ':p' => $profile]);
        return $profile;
    }

    /** @return array<string,bool> */
    public static function blocked(PDO $pdo, string $owner): array
    {
        $stmt = $pdo->prepare('SELECT osm_type, osm_id FROM geo_place_blocks WHERE user_email = :o');
        $stmt->execute([':o' => $owner]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $out[$r['osm_type'] . ':' . $r['osm_id']] = true;
        return $out;
    }

    /** @return list<array{lat:float,lon:float,accuracy_m:?float}> */
    public static function observations(PDO $pdo, string $owner, string $from, string $to, int $limit = 300): array
    {
        $stmt = $pdo->prepare("SELECT ST_Y(position) AS lat, ST_X(position) AS lon, accuracy_m FROM geo_location_points WHERE user_email = :o AND quality_state = 'accepted' AND source_at BETWEEN :f AND :t ORDER BY source_at LIMIT $limit");
        $stmt->execute([':o' => $owner, ':f' => $from, ':t' => $to]);
        return array_map(static fn(array $r): array => ['lat' => (float)$r['lat'], 'lon' => (float)$r['lon'], 'accuracy_m' => $r['accuracy_m'] !== null ? (float)$r['accuracy_m'] : null], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param array<string,mixed> $visit joined visit/stop row
     * @return array{status:string,detail:string}
     */
    public static function resolve(PDO $pdo, StepsGeolocationOsm $osm, array $visit, ?array $weights = null, ?array $thresholds = null): array
    {
        $id = (string)$visit['id'];
        $owner = (string)$visit['user_email'];
        $stopCfg = StepsGeolocationPlaceConfig::stop();
        $confirmAfter = (int)$stopCfg['confirm_after_s'];
        $endedAt = strtotime((string)($visit['ended_at'] ?? $visit['departed_at'] ?? '')) ?: 0;
        if ($endedAt > 0 && time() - $endedAt < $confirmAfter) {
            $pdo->prepare("UPDATE geo_visits SET detection_status = 'provisional', detection_method = 'stop_open', retry_after = to_timestamp(:t), updated_at = now() WHERE id = :id")
                ->execute([':t' => $endedAt + $confirmAfter, ':id' => $id]);
            return ['status' => 'provisional', 'detail' => 'stop_open'];
        }
        $lat = (float)$visit['lat'];
        $lon = (float)$visit['lon'];
        $lookup = $osm->lookup($lat, $lon);
        if ($lookup['status'] === 'busy') {
            $pdo->prepare("UPDATE geo_visits SET retry_after = now() + interval '30 seconds' WHERE id = :id")->execute([':id' => $id]);
            return ['status' => 'pending', 'detail' => 'busy'];
        }
        if ($lookup['status'] !== 'ok') {
            $attempts = (int)$visit['resolve_attempts'] + 1;
            if ($attempts >= self::MAX_ATTEMPTS) {
                self::store($pdo, $id, $owner, null, 'unidentified', 'osm_unavailable', 0.0, []);
                return ['status' => 'unidentified', 'detail' => 'osm_unavailable'];
            }
            $delay = min(3600, 60 * (1 << $attempts));
            $pdo->prepare("UPDATE geo_visits SET resolve_attempts = :a, retry_after = now() + make_interval(secs => :d), updated_at = now() WHERE id = :id")
                ->execute([':a' => $attempts, ':d' => $delay, ':id' => $id]);
            return ['status' => 'pending', 'detail' => 'retry'];
        }
        $override = json_decode((string)($visit['stop_override'] ?? '{}'), true) ?: [];
        $stop = [
            'lat' => $lat, 'lon' => $lon, 'radius_m' => (float)($visit['radius_m'] ?? $stopCfg['radius_m']),
            'duration_s' => (float)($override['duration_s'] ?? max(0, $endedAt - (strtotime((string)$visit['started_at']) ?: $endedAt))),
        ];
        $obs = self::observations($pdo, $owner, (string)$visit['started_at'], (string)$visit['ended_at']);
        $ranked = StepsGeolocationPlaceRanking::rank($stop, $obs, $lookup['elements'], self::profile($pdo, $owner), $weights, $thresholds, self::blocked($pdo, $owner));
        self::store($pdo, $id, $owner, $ranked['selected'], $ranked['status'], 'osm_overpass', $ranked['confidence'], $ranked['candidates']);
        return ['status' => $ranked['status'], 'detail' => $lookup['from_cache'] ? 'cache' : 'network'];
    }

    /** @param list<array<string,mixed>> $candidates */
    public static function store(PDO $pdo, string $visitId, string $owner, ?array $selected, string $status, string $method, float $confidence, array $candidates): void
    {
        $el = $selected['element'] ?? null;
        $placeId = null;
        if ($status === 'confirmed' && is_array($el) && trim((string)($el['name'] ?? '')) !== '' && isset($el['lat'], $el['lon'])) {
            $placeId = self::upsertSavedPlace($pdo, $owner, $el);
        }
        $pdo->prepare(
            "UPDATE geo_visits SET place_id = COALESCE(:place, place_id), osm_type = :t, osm_id = :i, display_name = :n, category = :c, address = :a, detection_status = :s, detection_method = :m, confidence = :conf, resolved_at = now(), retry_after = NULL, resolve_attempts = 0, updated_at = now() WHERE id = :id AND user_email = :o"
        )->execute([
            ':place' => $placeId, ':t' => $el['osm_type'] ?? null, ':i' => $el['osm_id'] ?? null, ':n' => $el['name'] ?? '', ':c' => $el ? self::categoryLabel($el) : '',
            ':a' => $el['address'] ?? '', ':s' => $status, ':m' => $method, ':conf' => $confidence, ':id' => $visitId, ':o' => $owner,
        ]);
        $pdo->prepare('DELETE FROM geo_visit_candidates WHERE visit_id = :id')->execute([':id' => $visitId]);
        $ins = $pdo->prepare('INSERT INTO geo_visit_candidates(id, visit_id, user_email, osm_type, osm_id, rank, score, selected, details_json) VALUES(:id, :v, :o, :t, :i, :r, :s, :sel, CAST(:d AS jsonb)) ON CONFLICT (visit_id, osm_type, osm_id) DO NOTHING');
        foreach ($candidates as $c) {
            $e = $c['element'];
            $ins->execute([
                ':id' => StepsGeolocationPlaces::uuid(), ':v' => $visitId, ':o' => $owner, ':t' => $e['osm_type'], ':i' => $e['osm_id'], ':r' => $c['rank'], ':s' => $c['confidence'],
                ':sel' => $selected !== null && $selected['element']['osm_type'] === $e['osm_type'] && $selected['element']['osm_id'] === $e['osm_id'] ? 'true' : 'false',
                ':d' => json_encode([
                    'name' => $e['name'], 'category' => self::categoryLabel($e), 'address' => $e['address'], 'lat' => $e['lat'], 'lon' => $e['lon'],
                    'distance_m' => $c['distance_m'], 'match_type' => $c['match_type'], 'ambiguous' => $c['ambiguous'], 'parts' => array_map(static fn(float $v): float => round($v, 3), $c['parts']),
                ], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    /** Mirror a confirmed OSM match into the owner's saved Places catalog. */
    private static function upsertSavedPlace(PDO $pdo, string $owner, array $el): ?string
    {
        $name = trim((string)($el['name'] ?? ''));
        if ($name === '') return null;
        $lat = (float)$el['lat'];
        $lon = (float)$el['lon'];
        $tag = substr(preg_replace('/[^a-z0-9_]+/i', '_', self::categoryLabel($el)) ?: 'place', 0, 32);
        $existing = $pdo->prepare(
            "SELECT id FROM geo_places
              WHERE user_email = :o AND deleted_at IS NULL
                AND ST_DWithin(position::geography, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326)::geography, 40)
                AND lower(name) = lower(:n)
              ORDER BY ST_Distance(position::geography, ST_SetSRID(ST_MakePoint(:lon2, :lat2), 4326)::geography)
              LIMIT 1"
        );
        $existing->execute([':o' => $owner, ':lon' => $lon, ':lat' => $lat, ':n' => $name, ':lon2' => $lon, ':lat2' => $lat]);
        $id = $existing->fetchColumn();
        if ($id) return (string)$id;
        $id = StepsGeolocationPlaces::uuid();
        $pdo->prepare(
            "INSERT INTO geo_places(id, user_email, name, tag, position, radius_m, notes, timezone, is_active)
             VALUES(:id, :o, :n, :t, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), 80, :notes, 'UTC', true)"
        )->execute([
            ':id' => $id, ':o' => $owner, ':n' => $name, ':t' => $tag ?: 'place',
            ':lon' => $lon, ':lat' => $lat,
            ':notes' => 'osm:' . (string)($el['osm_type'] ?? '') . ':' . (string)($el['osm_id'] ?? ''),
        ]);
        return $id;
    }

    public static function categoryLabel(array $el): string
    {
        return $el['category'] !== '' ? ($el['subcategory'] !== '' && $el['subcategory'] !== 'yes' ? $el['category'] . '=' . $el['subcategory'] : $el['category']) : '';
    }

    /** @return list<array<string,mixed>> */
    public static function candidates(PDO $pdo, string $owner, string $visitId): array
    {
        $stmt = $pdo->prepare(
            "SELECT c.id, c.osm_type, c.osm_id, c.rank, c.score, c.selected, c.details_json, p.tags_json
               FROM geo_visit_candidates c LEFT JOIN geo_osm_places p ON p.osm_type = c.osm_type AND p.osm_id = c.osm_id
              WHERE c.visit_id = :v AND c.user_email = :o ORDER BY c.rank"
        );
        $stmt->execute([':v' => $visitId, ':o' => $owner]);
        return array_map(static function (array $r): array {
            $d = json_decode((string)$r['details_json'], true) ?: [];
            return ['id' => (string)$r['id'], 'osm_type' => $r['osm_type'], 'osm_id' => (int)$r['osm_id'], 'rank' => (int)$r['rank'], 'score' => (float)$r['score'], 'selected' => (bool)$r['selected']] + $d;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private static function markManual(PDO $pdo, string $owner, string $visitId, array $extra = []): void
    {
        $row = $pdo->prepare('SELECT override_json, stop_id FROM geo_visits WHERE id = :id AND user_email = :o');
        $row->execute([':id' => $visitId, ':o' => $owner]);
        $r = $row->fetch(PDO::FETCH_ASSOC);
        if (!$r) throw new RuntimeException('visit_not_found');
        $override = json_decode((string)$r['override_json'], true) ?: [];
        $override = array_merge($override, ['manual' => true, 'review' => 'corrected'], $extra);
        $pdo->prepare('UPDATE geo_visits SET override_json = CAST(:j AS jsonb) WHERE id = :id')->execute([':j' => json_encode($override, JSON_UNESCAPED_UNICODE), ':id' => $visitId]);
        if ($r['stop_id']) {
            $pdo->prepare("UPDATE geo_stops SET override_json = COALESCE(override_json, '{}'::jsonb) || '{\"manual\": true}'::jsonb WHERE id = :s AND user_email = :o")->execute([':s' => $r['stop_id'], ':o' => $owner]);
        }
    }

    public static function selectCandidate(PDO $pdo, string $owner, string $visitId, string $candidateId): array
    {
        $stmt = $pdo->prepare("SELECT c.osm_type, c.osm_id, c.score, c.details_json FROM geo_visit_candidates c WHERE c.id = :c AND c.visit_id = :v AND c.user_email = :o");
        $stmt->execute([':c' => $candidateId, ':v' => $visitId, ':o' => $owner]);
        $c = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$c) throw new RuntimeException('candidate_not_found');
        $d = json_decode((string)$c['details_json'], true) ?: [];
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE geo_visits SET place_id = NULL, osm_type = :t, osm_id = :i, display_name = :n, category = :cat, address = :a, detection_status = 'manual', detection_method = 'manual_candidate', retry_after = NULL, updated_at = now() WHERE id = :id AND user_email = :o")
                ->execute([':t' => $c['osm_type'], ':i' => $c['osm_id'], ':n' => (string)($d['name'] ?? ''), ':cat' => (string)($d['category'] ?? ''), ':a' => (string)($d['address'] ?? ''), ':id' => $visitId, ':o' => $owner]);
            $pdo->prepare('UPDATE geo_visit_candidates SET selected = (id = :c) WHERE visit_id = :v')->execute([':c' => $candidateId, ':v' => $visitId]);
            self::markManual($pdo, $owner, $visitId);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        return ['status' => 'manual'];
    }

    /**
     * Custom label / category, optionally remembered as a personal place
     * (Home, Work or any custom tag). Personal places win over OSM names.
     *
     * @param array{name?:string,category?:string,tag?:string,remember?:bool,radius_m?:float} $input
     */
    public static function assign(PDO $pdo, string $owner, string $visitId, array $input): array
    {
        $name = trim((string)($input['name'] ?? ''));
        $category = trim((string)($input['category'] ?? ''));
        $tag = StepsGeolocationPlaces::normalizeTag((string)($input['tag'] ?? ''));
        $remember = !empty($input['remember']) || ($tag !== '' && StepsGeolocationPlaces::isBuiltinTag($tag));
        if (mb_strlen($name) > 80 || mb_strlen($category) > 60) throw new InvalidArgumentException('invalid_name');
        $stmt = $pdo->prepare("SELECT v.id, v.display_name, v.category, ST_Y(s.position) AS lat, ST_X(s.position) AS lon, s.radius_m FROM geo_visits v LEFT JOIN geo_stops s ON s.id = v.stop_id WHERE v.id = :id AND v.user_email = :o");
        $stmt->execute([':id' => $visitId, ':o' => $owner]);
        $v = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$v) throw new RuntimeException('visit_not_found');
        if ($name === '') $name = (string)$v['display_name'];
        if ($name === '' && $tag !== '') $name = ucfirst($tag);
        if ($name === '') throw new InvalidArgumentException('invalid_name');
        $placeId = null;
        $pdo->beginTransaction();
        try {
            if ($remember && $v['lat'] !== null) {
                if ($tag === '') $tag = 'custom';
                $radius = isset($input['radius_m']) ? (float)$input['radius_m'] : max(StepsGeolocationPlaces::MIN_RADIUS_M, min(300.0, (float)($v['radius_m'] ?? 60.0)));
                $existing = null;
                if (StepsGeolocationPlaces::isSingletonTag($tag)) {
                    $q = $pdo->prepare('SELECT id FROM geo_places WHERE user_email = :o AND tag = :t AND is_active AND deleted_at IS NULL LIMIT 1');
                    $q->execute([':o' => $owner, ':t' => $tag]);
                    $existing = $q->fetchColumn() ?: null;
                }
                if ($existing) {
                    $pdo->prepare('UPDATE geo_places SET name = :n, position = ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), radius_m = :r, updated_at = now() WHERE id = :id AND user_email = :o')
                        ->execute([':n' => $name, ':lon' => $v['lon'], ':lat' => $v['lat'], ':r' => $radius, ':id' => $existing, ':o' => $owner]);
                    $placeId = (string)$existing;
                } else {
                    $placeId = StepsGeolocationPlaces::uuid();
                    $pdo->prepare("INSERT INTO geo_places(id, user_email, name, tag, position, radius_m) VALUES(:id, :o, :n, :t, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), :r)")
                        ->execute([':id' => $placeId, ':o' => $owner, ':n' => $name, ':t' => $tag, ':lon' => $v['lon'], ':lat' => $v['lat'], ':r' => $radius]);
                }
            }
            $pdo->prepare("UPDATE geo_visits SET place_id = :p, osm_type = CASE WHEN :p2 IS NULL THEN osm_type ELSE NULL END, osm_id = CASE WHEN :p3 IS NULL THEN osm_id ELSE NULL END, display_name = :n, category = :c, detection_status = 'manual', detection_method = :m, retry_after = NULL, updated_at = now() WHERE id = :id AND user_email = :o")
                ->execute([':p' => $placeId, ':p2' => $placeId, ':p3' => $placeId, ':n' => $name, ':c' => $category !== '' ? $category : ($tag !== '' ? $tag : (string)$v['category']), ':m' => $placeId ? 'manual_place' : 'manual_label', ':id' => $visitId, ':o' => $owner]);
            self::markManual($pdo, $owner, $visitId, ['custom_name' => $name]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        return ['status' => 'manual', 'place_id' => $placeId];
    }

    public static function block(PDO $pdo, string $owner, string $visitId): array
    {
        $stmt = $pdo->prepare('SELECT osm_type, osm_id FROM geo_visits WHERE id = :id AND user_email = :o');
        $stmt->execute([':id' => $visitId, ':o' => $owner]);
        $v = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$v) throw new RuntimeException('visit_not_found');
        $pdo->beginTransaction();
        try {
            if ($v['osm_id'] !== null) {
                $pdo->prepare('INSERT INTO geo_place_blocks(user_email, osm_type, osm_id) VALUES(:o, :t, :i) ON CONFLICT DO NOTHING')->execute([':o' => $owner, ':t' => $v['osm_type'], ':i' => $v['osm_id']]);
                $pdo->prepare('DELETE FROM geo_visit_candidates WHERE user_email = :o AND osm_type = :t AND osm_id = :i')->execute([':o' => $owner, ':t' => $v['osm_type'], ':i' => $v['osm_id']]);
            }
            $pdo->prepare("UPDATE geo_visits SET osm_type = NULL, osm_id = NULL, place_id = NULL, display_name = '', category = '', address = '', detection_status = 'unidentified', detection_method = 'manual_block', updated_at = now() WHERE id = :id AND user_email = :o")
                ->execute([':id' => $visitId, ':o' => $owner]);
            self::markManual($pdo, $owner, $visitId, ['blocked' => true]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        return ['status' => 'unidentified'];
    }

    public static function retry(PDO $pdo, string $owner, string $visitId): void
    {
        $pdo->prepare("UPDATE geo_visits SET detection_status = 'pending', detection_method = '', retry_after = NULL, resolve_attempts = 0, override_json = override_json - 'manual' - 'review' - 'blocked' - 'custom_name', updated_at = now() WHERE id = :id AND user_email = :o AND place_id IS NULL")
            ->execute([':id' => $visitId, ':o' => $owner]);
    }
}
