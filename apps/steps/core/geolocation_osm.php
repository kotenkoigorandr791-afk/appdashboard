<?php
require_once __DIR__ . '/geolocation_place_config.php';
require_once __DIR__ . '/geolocation_observability.php';

final class StepsGeolocationOsm
{
    public const CATEGORY_KEYS = ['amenity', 'shop', 'office', 'healthcare', 'tourism', 'leisure', 'craft', 'man_made', 'railway', 'aeroway', 'public_transport', 'military', 'industrial', 'landuse', 'building', 'sport'];
    public const QUERY_KEYS = ['name', 'shop', 'amenity', 'office', 'building', 'leisure', 'tourism', 'healthcare', 'craft', 'man_made', 'military', 'industrial', 'landuse', 'aeroway', 'public_transport'];

    private PDO $pdo;
    /** @var array<string,mixed> */
    private array $config;
    /** @var callable */
    private $transport;
    /** @var callable */
    private $sleeper;
    private float $lastRequestAt = 0.0;
    /** @var array<string,int> */
    private array $cooldowns = [];
    /** @var array<string,mixed> */
    public array $stats = ['requests' => 0, 'cache_hits' => 0, 'failures' => 0, 'failovers' => 0];

    public function __construct(PDO $pdo, ?array $config = null, ?callable $transport = null, ?callable $sleeper = null)
    {
        $this->pdo = $pdo;
        $this->config = $config ?? StepsGeolocationPlaceConfig::osm();
        $this->transport = $transport ?? [self::class, 'curlTransport'];
        $this->sleeper = $sleeper ?? static function (int $ms): void { if ($ms > 0) usleep($ms * 1000); };
    }

    public static function buildQuery(float $lat, float $lon, float $radius, int $timeout = 20): string
    {
        $around = sprintf('(around:%d,%.6F,%.6F)', (int)round($radius), $lat, $lon);
        $parts = [];
        foreach (self::QUERY_KEYS as $key) $parts[] = 'nwr' . $around . '["' . $key . '"];';
        $parts[] = 'nwr' . $around . '["railway"="station"];';
        return '[out:json][timeout:' . $timeout . '];(' . implode('', $parts) . ')->.c;(way.c;)->.w;(.c; - .w;)->.o;.o out tags center bb;.w out tags geom bb;';
    }

    public static function cacheKey(float $lat, float $lon, float $radius, float $grid): string
    {
        $snap = static fn(float $v): string => number_format(round($v / $grid) * $grid, 5, '.', '');
        return 'q:' . $snap($lat) . ':' . $snap($lon) . ':' . (int)round($radius);
    }

    /** @return array{0:int,1:string,2:array<string,string>} */
    public static function curlTransport(string $url, string $query, int $timeoutS): array
    {
        $ch = curl_init($url);
        $headers = [];
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['data' => $query]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $timeoutS + 5,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: AppDashboard-Steps/1.0 (place-detection)'],
            CURLOPT_HEADERFUNCTION => static function ($c, string $line) use (&$headers): int {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $headers[strtolower(trim($k))] = trim($v);
                }
                return strlen($line);
            },
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        return [$body === false ? 0 : $code, $body === false ? '' : (string)$body, $headers];
    }

    private function log(string $event, array $context = []): void
    {
        try { StepsGeolocationObservability::log('info', 'osm.' . $event, $context); } catch (Throwable $e) {}
    }

    private function providerCooling(string $url): bool
    {
        $until = $this->cooldowns[$url] ?? 0;
        if ($until > time()) return true;
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM geo_osm_query_cache WHERE cache_key = :k AND expires_at > now()');
            $stmt->execute([':k' => 'cooldown:' . sha1($url)]);
            if ($stmt->fetchColumn()) return true;
        } catch (Throwable $e) {}
        return false;
    }

    private function coolProvider(string $url, int $seconds): void
    {
        if ($seconds <= 0) return;
        $this->cooldowns[$url] = time() + $seconds;
        try {
            $this->pdo->prepare("INSERT INTO geo_osm_query_cache(cache_key, provider, status, expires_at) VALUES(:k, :p, 'cooldown', now() + make_interval(secs => :s)) ON CONFLICT (cache_key) DO UPDATE SET expires_at = EXCLUDED.expires_at, fetched_at = now()")
                ->execute([':k' => 'cooldown:' . sha1($url), ':p' => $url, ':s' => $seconds]);
        } catch (Throwable $e) {}
    }

    /**
     * @return array{status:string,elements:list<array<string,mixed>>,from_cache:bool,provider:string}
     *   status: ok | busy | unavailable
     */
    public function lookup(float $lat, float $lon, ?float $radius = null): array
    {
        $radius = $radius ?? (float)$this->config['search_radius_m'];
        $key = self::cacheKey($lat, $lon, $radius, (float)$this->config['cache_grid_deg']);
        $cached = $this->readCache($key);
        if ($cached !== null) {
            $this->stats['cache_hits']++;
            return ['status' => 'ok', 'elements' => $cached, 'from_cache' => true, 'provider' => 'cache'];
        }
        $lockId = (int)sprintf('%u', crc32($key)) & 0x7fffffff;
        $lock = $this->pdo->prepare('SELECT pg_try_advisory_lock(:ns, :id)');
        $lock->execute([':ns' => 710103, ':id' => $lockId]);
        if (!$lock->fetchColumn()) return ['status' => 'busy', 'elements' => [], 'from_cache' => false, 'provider' => ''];
        try {
            $cached = $this->readCache($key);
            if ($cached !== null) {
                $this->stats['cache_hits']++;
                return ['status' => 'ok', 'elements' => $cached, 'from_cache' => true, 'provider' => 'cache'];
            }
            [$elements, $provider] = $this->fetchRemote($lat, $lon, $radius);
            if ($elements === null) {
                $this->writeCache($key, [], 'error', (int)$this->config['negative_cache_ttl_s'], '');
                return ['status' => 'unavailable', 'elements' => [], 'from_cache' => false, 'provider' => ''];
            }
            $this->storeElements($elements);
            $this->writeCache($key, $elements, 'ok', (int)$this->config['cache_ttl_s'], $provider);
            return ['status' => 'ok', 'elements' => $elements, 'from_cache' => false, 'provider' => $provider];
        } finally {
            $unlock = $this->pdo->prepare('SELECT pg_advisory_unlock(:ns, :id)');
            $unlock->execute([':ns' => 710103, ':id' => $lockId]);
        }
    }

    /** @return list<array<string,mixed>>|null */
    private function readCache(string $key): ?array
    {
        $stmt = $this->pdo->prepare('SELECT status, element_refs FROM geo_osm_query_cache WHERE cache_key = :k AND expires_at > now()');
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        if ($row['status'] === 'error') return null;
        $refs = json_decode((string)$row['element_refs'], true) ?: [];
        if ($refs === []) return [];
        $out = [];
        $load = $this->pdo->prepare("SELECT osm_type, osm_id, name, category, subcategory, address, ST_Y(position) AS lat, ST_X(position) AS lon, ST_AsGeoJSON(geometry) AS geometry, tags_json, fetched_at FROM geo_osm_places WHERE osm_type = :t AND osm_id = :i");
        foreach ($refs as $ref) {
            $load->execute([':t' => $ref[0], ':i' => $ref[1]]);
            $r = $load->fetch(PDO::FETCH_ASSOC);
            if ($r) $out[] = self::rowToElement($r);
        }
        return $out;
    }

    public static function rowToElement(array $r): array
    {
        return [
            'osm_type' => (string)$r['osm_type'], 'osm_id' => (int)$r['osm_id'], 'name' => (string)$r['name'],
            'category' => (string)$r['category'], 'subcategory' => (string)$r['subcategory'], 'address' => (string)$r['address'],
            'lat' => (float)$r['lat'], 'lon' => (float)$r['lon'],
            'geometry' => isset($r['geometry']) && $r['geometry'] !== null ? json_decode((string)$r['geometry'], true) : null,
            'tags' => is_array($r['tags_json'] ?? null) ? $r['tags_json'] : (json_decode((string)($r['tags_json'] ?? '{}'), true) ?: []),
            'fetched_at' => (string)($r['fetched_at'] ?? ''),
        ];
    }

    private function writeCache(string $key, array $elements, string $status, int $ttl, string $provider): void
    {
        $refs = array_map(static fn(array $e): array => [$e['osm_type'], $e['osm_id']], $elements);
        $this->pdo->prepare("INSERT INTO geo_osm_query_cache(cache_key, provider, element_refs, status, fetched_at, expires_at) VALUES(:k, :p, CAST(:r AS jsonb), :s, now(), now() + make_interval(secs => :ttl)) ON CONFLICT (cache_key) DO UPDATE SET provider = EXCLUDED.provider, element_refs = EXCLUDED.element_refs, status = EXCLUDED.status, fetched_at = now(), expires_at = EXCLUDED.expires_at")
            ->execute([':k' => $key, ':p' => $provider, ':r' => json_encode($refs), ':s' => $status, ':ttl' => $ttl]);
    }

    /** @return array{0:?list<array<string,mixed>>,1:string} */
    private function fetchRemote(float $lat, float $lon, float $radius): array
    {
        $query = self::buildQuery($lat, $lon, $radius, (int)$this->config['timeout_s']);
        $providers = (array)$this->config['providers'];
        $attempts = (int)$this->config['max_attempts'];
        $backoff = (int)$this->config['backoff_base_ms'];
        $tried = 0;
        $index = 0;
        $lastProvider = '';
        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $url = null;
            for ($i = 0; $i < count($providers); $i++) {
                $candidate = $providers[($index + $i) % count($providers)];
                if (!$this->providerCooling($candidate)) { $url = $candidate; $index = ($index + $i) % count($providers); break; }
            }
            if ($url === null) break;
            if ($lastProvider !== '' && $lastProvider !== $url) $this->stats['failovers']++;
            $lastProvider = $url;
            $wait = (int)($this->config['min_interval_ms']) - (int)((microtime(true) - $this->lastRequestAt) * 1000);
            if ($wait > 0) ($this->sleeper)($wait);
            $this->lastRequestAt = microtime(true);
            $this->stats['requests']++;
            $tried++;
            try {
                [$code, $body, $headers] = ($this->transport)($url, $query, (int)$this->config['timeout_s']);
            } catch (Throwable $e) {
                [$code, $body, $headers] = [0, '', []];
            }
            if ($code === 200) {
                $data = json_decode($body, true);
                if (is_array($data) && isset($data['elements']) && is_array($data['elements']) && empty($data['remark'])) {
                    $this->log('fetched', ['provider' => $url, 'elements' => count($data['elements'])]);
                    return [self::normalize($data['elements'], $lat, $lon, (int)$this->config['max_elements']), $url];
                }
                $this->coolProvider($url, 30);
                $this->log('bad_payload', ['provider' => $url]);
            } elseif ($code === 429) {
                $retryAfter = isset($headers['retry-after']) && is_numeric($headers['retry-after']) ? (int)$headers['retry-after'] : (int)$this->config['provider_cooldown_s'];
                $this->coolProvider($url, max($retryAfter, 5));
                $this->log('rate_limited', ['provider' => $url, 'retry_after' => $retryAfter]);
            } elseif ($code === 504 || $code === 503 || $code === 502 || $code === 500 || $code === 0) {
                $this->coolProvider($url, (int)min(300, max(15, (int)$this->config['provider_cooldown_s'] / 2)));
                $this->log('provider_error', ['provider' => $url, 'status' => $code]);
            } else {
                $this->coolProvider($url, 60);
                $this->log('unexpected_status', ['provider' => $url, 'status' => $code]);
            }
            $this->stats['failures']++;
            $index = ($index + 1) % count($providers);
            if ($attempt + 1 < $attempts) ($this->sleeper)($backoff * (1 << $attempt));
        }
        $this->log('unavailable', ['attempts' => $tried]);
        return [null, ''];
    }

    /**
     * @param list<array<string,mixed>> $raw
     * @return list<array<string,mixed>>
     */
    public static function normalize(array $raw, float $lat, float $lon, int $limit = 150): array
    {
        $now = gmdate('c');
        $out = [];
        foreach ($raw as $el) {
            $type = (string)($el['type'] ?? '');
            $id = (int)($el['id'] ?? 0);
            if (!in_array($type, ['node', 'way', 'relation'], true) || $id <= 0) continue;
            $tags = [];
            foreach ((array)($el['tags'] ?? []) as $k => $v) $tags[(string)$k] = (string)$v;
            if ($tags === []) continue;
            $geometry = null;
            $elLat = isset($el['lat']) ? (float)$el['lat'] : (isset($el['center']['lat']) ? (float)$el['center']['lat'] : null);
            $elLon = isset($el['lon']) ? (float)$el['lon'] : (isset($el['center']['lon']) ? (float)$el['center']['lon'] : null);
            if (!empty($el['geometry']) && is_array($el['geometry'])) {
                $ring = [];
                foreach ($el['geometry'] as $p) if (isset($p['lat'], $p['lon'])) $ring[] = [(float)$p['lon'], (float)$p['lat']];
                if (count($ring) >= 4 && $ring[0] === $ring[count($ring) - 1]) $geometry = ['type' => 'Polygon', 'coordinates' => [$ring]];
                elseif (count($ring) >= 2) $geometry = ['type' => 'LineString', 'coordinates' => $ring];
            }
            if (($elLat === null || $elLon === null) && isset($el['bounds'])) {
                $b = $el['bounds'];
                $elLat = ((float)$b['minlat'] + (float)$b['maxlat']) / 2;
                $elLon = ((float)$b['minlon'] + (float)$b['maxlon']) / 2;
            }
            if ($elLat === null || $elLon === null || $elLat < -90 || $elLat > 90 || $elLon < -180 || $elLon > 180) continue;
            [$category, $sub] = self::categorize($tags);
            if ($category === '' && ($tags['name'] ?? '') === '') continue;
            $out[] = [
                'osm_type' => $type, 'osm_id' => $id, 'name' => $tags['name'] ?? ($tags['name:en'] ?? ($tags['brand'] ?? '')),
                'category' => $category, 'subcategory' => $sub, 'address' => self::address($tags),
                'lat' => $elLat, 'lon' => $elLon, 'geometry' => $geometry, 'tags' => $tags, 'fetched_at' => $now,
            ];
        }
        usort($out, static fn(array $a, array $b): int => self::approxDistance($lat, $lon, $a['lat'], $a['lon']) <=> self::approxDistance($lat, $lon, $b['lat'], $b['lon']));
        return array_slice($out, 0, $limit);
    }

    /** @return array{0:string,1:string} */
    public static function categorize(array $tags): array
    {
        foreach (self::CATEGORY_KEYS as $key) {
            if (isset($tags[$key]) && $tags[$key] !== '' && !($key === 'building' && isset($tags['amenity']))) {
                return [$key, (string)$tags[$key]];
            }
        }
        return ['', ''];
    }

    public static function address(array $tags): string
    {
        if (!empty($tags['addr:full'])) return (string)$tags['addr:full'];
        $street = trim(($tags['addr:street'] ?? '') . ' ' . ($tags['addr:housenumber'] ?? ''));
        $city = trim(($tags['addr:postcode'] ?? '') . ' ' . ($tags['addr:city'] ?? ''));
        return trim(implode(', ', array_filter([$street, $city], static fn(string $s): bool => $s !== '')));
    }

    private static function approxDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dy = ($lat2 - $lat1) * 111320.0;
        $dx = ($lon2 - $lon1) * 111320.0 * cos(deg2rad($lat1));
        return sqrt($dx * $dx + $dy * $dy);
    }

    /** @param list<array<string,mixed>> $elements */
    private function storeElements(array $elements): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO geo_osm_places(osm_type, osm_id, name, category, subcategory, address, position, geometry, tags_json, fetched_at)
             VALUES(:t, :i, :n, :c, :s, :a, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), CASE WHEN CAST(:g AS text) IS NULL THEN NULL ELSE ST_SetSRID(ST_GeomFromGeoJSON(CAST(:g2 AS text)), 4326) END, CAST(:tags AS jsonb), now())
             ON CONFLICT (osm_type, osm_id) DO UPDATE SET name = EXCLUDED.name, category = EXCLUDED.category, subcategory = EXCLUDED.subcategory, address = EXCLUDED.address, position = EXCLUDED.position, geometry = EXCLUDED.geometry, tags_json = EXCLUDED.tags_json, fetched_at = now()"
        );
        foreach ($elements as $e) {
            $g = $e['geometry'] !== null ? json_encode($e['geometry']) : null;
            $stmt->execute([
                ':t' => $e['osm_type'], ':i' => $e['osm_id'], ':n' => $e['name'], ':c' => $e['category'], ':s' => $e['subcategory'], ':a' => $e['address'],
                ':lon' => $e['lon'], ':lat' => $e['lat'], ':g' => $g, ':g2' => $g, ':tags' => json_encode($e['tags'], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function purgeExpired(): int
    {
        $n = $this->pdo->exec("DELETE FROM geo_osm_query_cache WHERE expires_at < now() - interval '1 day'");
        return (int)$n;
    }
}
