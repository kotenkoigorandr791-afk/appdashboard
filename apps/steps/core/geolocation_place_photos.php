<?php
require_once __DIR__ . '/user_helpers.php';
require_once __DIR__ . '/geolocation_place_config.php';

/**
 * Local WebP storage for OSM place photos. Discovery never mutates geo_osm_places
 * identities; photos live beside them in geo_osm_place_photos + appdata files.
 */
final class StepsGeolocationPlacePhotos
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_READY = 'ready';
    public const STATUS_MISSING = 'missing';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFRESH = 'refresh';

    private const UA = 'AppDashboardSteps/1.0 (https://localhost; place-photos; contact: root@localhost)';
    private const MAX_EDGE = 256;
    private const WEBP_QUALITY = 70;
    private const MAX_ATTEMPTS = 6;
    private const MAX_DOWNLOAD_BYTES = 8_000_000;
    /** Wikimedia-friendly soft cap; 200/min ≈ 300ms between requests. */
    private const MAX_REQUESTS_PER_MIN = 200;
    private const HTTP_RETRIES = 8;

    private static float $nextRequestAt = 0.0;

    public static function storageDir(): string
    {
        $dir = steps_appdata_dir('place-photos');
        if ($dir === null || $dir === '') {
            throw new RuntimeException('place-photos storage unavailable');
        }
        foreach (['node', 'way', 'relation'] as $t) {
            $sub = $dir . '/' . $t;
            if (!is_dir($sub)) @mkdir($sub, 0775, true);
        }
        return $dir;
    }

    public static function relativePath(string $osmType, int $osmId): string
    {
        $type = self::normalizeType($osmType);
        return $type . '/' . $osmId . '.webp';
    }

    public static function absolutePath(string $osmType, int $osmId): string
    {
        return rtrim(self::storageDir(), '/') . '/' . self::relativePath($osmType, $osmId);
    }

    /** Legacy / alternate APPDATA trees the hostnet worker may have written to. */
    private static function alternateAbsolutePaths(string $osmType, int $osmId): array
    {
        $rel = self::relativePath($osmType, $osmId);
        $out = [];
        foreach (['/opt/ApplicationData/steps/place-photos', '/storage/applications/steps/place-photos'] as $base) {
            $path = rtrim($base, '/') . '/' . $rel;
            if ($path !== self::absolutePath($osmType, $osmId)) $out[] = $path;
        }
        return $out;
    }

    /** Prefer an on-disk file already fetched for this OSM id (any APPDATA tree). */
    private static function existingPhotoPath(string $osmType, int $osmId): ?string
    {
        $primary = self::absolutePath($osmType, $osmId);
        if (is_file($primary) && filesize($primary) > 0) return $primary;
        foreach (self::alternateAbsolutePaths($osmType, $osmId) as $alt) {
            if (is_file($alt) && filesize($alt) > 0) return $alt;
        }
        return null;
    }

    public static function publicUrl(string $osmType, int $osmId): string
    {
        return '/apps/steps/api/session_gateway.php?endpoint=geolocation_place_photo&osm_type='
            . rawurlencode(self::normalizeType($osmType)) . '&osm_id=' . $osmId;
    }

    public static function apiUrl(string $osmType, int $osmId): string
    {
        return '/api/steps/geolocation_place_photo?osm_type='
            . rawurlencode(self::normalizeType($osmType)) . '&osm_id=' . $osmId;
    }

    public static function ensurePending(PDO $pdo, string $osmType, int $osmId): void
    {
        $pdo->prepare(
            "INSERT INTO geo_osm_place_photos(osm_type, osm_id, status)
             VALUES(:t, :i, 'pending')
             ON CONFLICT (osm_type, osm_id) DO NOTHING"
        )->execute([':t' => self::normalizeType($osmType), ':i' => $osmId]);
    }

    public static function ensureAllPending(PDO $pdo): int
    {
        return (int)$pdo->exec(
            "INSERT INTO geo_osm_place_photos(osm_type, osm_id, status)
             SELECT osm_type, osm_id, 'pending' FROM geo_osm_places
             ON CONFLICT DO NOTHING"
        );
    }

    public static function requestRefresh(PDO $pdo, string $osmType, int $osmId): void
    {
        $type = self::normalizeType($osmType);
        self::ensurePending($pdo, $type, $osmId);
        $pdo->prepare(
            "UPDATE geo_osm_place_photos
                SET status = 'refresh', retry_after = NULL, last_error = '', updated_at = now()
              WHERE osm_type = :t AND osm_id = :i"
        )->execute([':t' => $type, ':i' => $osmId]);
    }

    /** @return array{ready:int,missing:int,failed:int,skipped:int} */
    public static function processBatch(PDO $pdo, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        self::ensureAllPending($pdo);
        $stmt = $pdo->prepare(
            "SELECT p.osm_type, p.osm_id, p.name, p.category, p.subcategory, p.tags_json,
                    ST_Y(p.position) AS lat, ST_X(p.position) AS lon,
                    ph.status, ph.attempts, ph.local_path, ph.checksum
               FROM geo_osm_place_photos ph
               JOIN geo_osm_places p ON p.osm_type = ph.osm_type AND p.osm_id = ph.osm_id
              WHERE ph.status IN ('pending','failed','refresh')
                AND (ph.retry_after IS NULL OR ph.retry_after <= now())
                AND ph.attempts < :max
              ORDER BY CASE ph.status WHEN 'refresh' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END, ph.updated_at ASC
              LIMIT $limit"
        );
        $stmt->execute([':max' => self::MAX_ATTEMPTS]);
        $out = ['ready' => 0, 'missing' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result = self::processOne($pdo, $row);
            $out[$result] = ($out[$result] ?? 0) + 1;
        }
        return $out;
    }

    /** @param array<string,mixed> $row */
    private static function processOne(PDO $pdo, array $row): string
    {
        $type = self::normalizeType((string)$row['osm_type']);
        $id = (int)$row['osm_id'];
        $attempts = (int)$row['attempts'] + 1;
        $force = (string)$row['status'] === self::STATUS_REFRESH;
        $existingPath = self::existingPhotoPath($type, $id);
        if (!$force && $existingPath !== null) {
            $dest = self::absolutePath($type, $id);
            if ($existingPath !== $dest) {
                @mkdir(dirname($dest), 0775, true);
                @copy($existingPath, $dest);
            }
            $checksum = (string)$row['checksum'];
            if ($checksum === '' && is_file($dest)) $checksum = hash_file('sha256', $dest) ?: '';
            self::markReadyFromExisting($pdo, $type, $id, self::relativePath($type, $id), $checksum !== '' ? $checksum : (string)$row['checksum']);
            return 'skipped';
        }
        $tags = is_array($row['tags_json'] ?? null) ? $row['tags_json'] : (json_decode((string)($row['tags_json'] ?? '{}'), true) ?: []);
        $place = [
            'osm_type' => $type,
            'osm_id' => $id,
            'name' => (string)$row['name'],
            'category' => (string)$row['category'],
            'subcategory' => (string)$row['subcategory'],
            'lat' => (float)$row['lat'],
            'lon' => (float)$row['lon'],
            'tags' => $tags,
        ];
        try {
            $candidate = self::resolveCandidate($place);
            if ($candidate === null) {
                self::markStatus($pdo, $type, $id, self::STATUS_MISSING, $attempts, '', 'no suitable image');
                return 'missing';
            }
            $bytes = self::download($candidate['source_url']);
            if ($bytes === null) {
                throw new RuntimeException('download failed');
            }
            $sha = hash('sha256', $bytes);
            $dup = self::findByChecksum($pdo, $sha);
            if ($dup !== null && !$force) {
                $src = self::absolutePath($dup['osm_type'], (int)$dup['osm_id']);
                if (is_file($src) && filesize($src) > 0) {
                    $dest = self::absolutePath($type, $id);
                    if ($src !== $dest) @copy($src, $dest);
                    self::saveMeta($pdo, $type, $id, array_merge($candidate, [
                        'status' => self::STATUS_READY,
                        'local_path' => self::relativePath($type, $id),
                        'checksum' => $sha,
                        'width_px' => (int)$dup['width_px'],
                        'height_px' => (int)$dup['height_px'],
                        'file_bytes' => (int)$dup['file_bytes'],
                        'attempts' => $attempts,
                    ]));
                    return 'ready';
                }
            }
            $converted = self::convertToWebp($bytes);
            if ($converted === null) {
                throw new RuntimeException('webp conversion failed');
            }
            $path = self::absolutePath($type, $id);
            $tmp = $path . '.tmp';
            if (file_put_contents($tmp, $converted['bytes']) === false) {
                throw new RuntimeException('write failed');
            }
            rename($tmp, $path);
            @chmod($path, 0664);
            self::saveMeta($pdo, $type, $id, array_merge($candidate, [
                'status' => self::STATUS_READY,
                'local_path' => self::relativePath($type, $id),
                'checksum' => hash('sha256', $converted['bytes']),
                'width_px' => $converted['width'],
                'height_px' => $converted['height'],
                'file_bytes' => strlen($converted['bytes']),
                'attempts' => $attempts,
            ]));
            return 'ready';
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            $backoff = min(86400, (int)pow(2, min(10, $attempts)) * 30);
            if (preg_match('/^rate_limited:(\d+)/', $msg, $m)) {
                $backoff = max($backoff, (int)$m[1]);
                $msg = 'rate limited, retry after ' . $backoff . 's';
            } elseif (stripos($msg, 'timed out') !== false || stripos($msg, 'Could not resolve') !== false || stripos($msg, 'network') !== false) {
                $backoff = max($backoff, 900);
            }
            self::markStatus($pdo, $type, $id, self::STATUS_FAILED, $attempts, $msg, '', $backoff);
            return 'failed';
        }
    }

    /** @param array<string,mixed> $place @return ?array{source_provider:string,source_url:string,source_page_url:string,author:string,license_name:string,license_url:string,attribution:string} */
    private static function resolveCandidate(array $place): ?array
    {
        $fromTags = self::fromOsmTags($place);
        if ($fromTags !== null) return $fromTags;
        $fromCommons = self::fromWikimediaSearch($place);
        if ($fromCommons !== null) return $fromCommons;
        return self::fromPanoramax($place);
    }

    /** @param array<string,mixed> $place */
    private static function fromOsmTags(array $place): ?array
    {
        $tags = (array)$place['tags'];
        foreach (['wikimedia_commons', 'image', 'panoramax'] as $key) {
            $raw = trim((string)($tags[$key] ?? ''));
            if ($raw === '') continue;
            if ($key === 'panoramax') {
                $meta = self::panoramaxById($raw);
                if ($meta !== null) return $meta;
                continue;
            }
            if (preg_match('#^https?://#i', $raw)) {
                if (stripos($raw, 'wikimedia.org') !== false || stripos($raw, 'wikipedia.org') !== false) {
                    $file = self::commonsFileFromUrl($raw);
                    if ($file !== null) {
                        $meta = self::commonsFileMeta($file);
                        if ($meta !== null) return $meta;
                    }
                }
                return [
                    'source_provider' => 'osm_tag',
                    'source_url' => $raw,
                    'source_page_url' => $raw,
                    'author' => '',
                    'license_name' => (string)($tags['license'] ?? ''),
                    'license_url' => '',
                    'attribution' => trim($place['name'] . ' (OSM ' . $key . ')'),
                ];
            }
            if (preg_match('#^Category:#i', $raw)) {
                $meta = self::commonsCategoryImage($raw);
                if ($meta !== null) {
                    $meta['source_provider'] = 'osm_tag';
                    return $meta;
                }
                continue;
            }
            if (preg_match('#^Category:#i', $raw)) {
                $meta = self::commonsCategoryImage($raw);
                if ($meta !== null) {
                    $meta['source_provider'] = 'osm_tag';
                    return $meta;
                }
                continue;
            }
            $file = preg_replace('#^File:#i', '', $raw) ?? $raw;
            $meta = self::commonsFileMeta($file);
            if ($meta !== null) {
                $meta['source_provider'] = 'osm_tag';
                return $meta;
            }
        }
        $wikidata = trim((string)($tags['wikidata'] ?? ''));
        if ($wikidata !== '' && preg_match('/^Q\d+$/i', $wikidata)) {
            $meta = self::wikidataImage($wikidata);
            if ($meta !== null) return $meta;
        }
        return null;
    }

    /** @param array<string,mixed> $place */
    private static function fromWikimediaSearch(array $place): ?array
    {
        $name = trim((string)$place['name']);
        if ($name === '' || mb_strlen($name) < 3) return null;
        $lat = (float)$place['lat'];
        $lon = (float)$place['lon'];
        $category = trim((string)$place['category'] . ' ' . (string)$place['subcategory']);
        $query = trim($name . ' ' . $category);
        $url = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
            'action' => 'query',
            'format' => 'json',
            'generator' => 'geosearch',
            'ggscoord' => $lat . '|' . $lon,
            'ggsradius' => 120,
            'ggslimit' => 12,
            'ggsnamespace' => 6,
            'prop' => 'imageinfo|coordinates',
            'iiprop' => 'url|extmetadata|size|mime',
            'iiurlwidth' => 512,
        ]);
        $json = self::httpJson($url);
        $pages = $json['query']['pages'] ?? null;
        if (!is_array($pages) || $pages === []) {
            $url = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
                'action' => 'query',
                'format' => 'json',
                'list' => 'search',
                'srsearch' => $query,
                'srnamespace' => 6,
                'srlimit' => 8,
            ]);
            $json = self::httpJson($url);
            $titles = [];
            foreach (($json['query']['search'] ?? []) as $hit) {
                $title = (string)($hit['title'] ?? '');
                if ($title !== '' && self::nameLooksRelated($name, $title)) $titles[] = $title;
            }
            if ($titles === []) return null;
            return self::commonsFileMeta(preg_replace('#^File:#i', '', $titles[0]) ?? $titles[0]);
        }
        $best = null;
        $bestScore = 0.0;
        foreach ($pages as $page) {
            $title = (string)($page['title'] ?? '');
            $info = $page['imageinfo'][0] ?? null;
            if (!is_array($info)) continue;
            $mime = (string)($info['mime'] ?? '');
            if ($mime !== '' && !str_starts_with($mime, 'image/')) continue;
            $score = self::nameLooksRelated($name, $title) ? 2.0 : 0.5;
            $coords = $page['coordinates'][0] ?? null;
            if (is_array($coords)) {
                $d = self::haversine($lat, $lon, (float)$coords['lat'], (float)$coords['lon']);
                if ($d > 150) continue;
                $score += max(0.0, 1.5 - $d / 100.0);
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $page;
            }
        }
        if ($best === null || $bestScore < 1.2) return null;
        $file = preg_replace('#^File:#i', '', (string)$best['title']) ?? '';
        return $file !== '' ? self::commonsFileMeta($file) : null;
    }

    /** @param array<string,mixed> $place */
    private static function fromPanoramax(array $place): ?array
    {
        $lat = (float)$place['lat'];
        $lon = (float)$place['lon'];
        $url = 'https://api.panoramax.xyz/api/search?' . http_build_query([
            'place_position' => $lon . ',' . $lat,
            'limit' => 5,
        ]);
        $json = self::httpJson($url);
        $features = $json['features'] ?? [];
        if (!is_array($features) || $features === []) return null;
        $best = null;
        $bestDist = PHP_FLOAT_MAX;
        foreach ($features as $f) {
            if (!is_array($f)) continue;
            $coords = $f['geometry']['coordinates'] ?? null;
            if (!is_array($coords) || count($coords) < 2) continue;
            $d = self::haversine($lat, $lon, (float)$coords[1], (float)$coords[0]);
            if ($d > 40) continue;
            $thumb = (string)(($f['assets']['thumb']['href'] ?? '') ?: ($f['assets']['sd']['href'] ?? '') ?: ($f['assets']['hd']['href'] ?? ''));
            if ($thumb === '') continue;
            if ($d < $bestDist) {
                $bestDist = $d;
                $best = $f;
            }
        }
        if ($best === null) return null;
        $href = (string)(($best['assets']['thumb']['href'] ?? '') ?: ($best['assets']['sd']['href'] ?? ''));
        $page = (string)($best['links'][0]['href'] ?? $best['id'] ?? '');
        $author = (string)($best['providers'][0]['name'] ?? $best['properties']['authors'][0]['name'] ?? 'Panoramax');
        $license = (string)($best['properties']['license'] ?? 'CC-BY-SA');
        return [
            'source_provider' => 'panoramax',
            'source_url' => $href,
            'source_page_url' => $page,
            'author' => $author,
            'license_name' => $license,
            'license_url' => '',
            'attribution' => trim($author . ' / Panoramax / ' . $license),
        ];
    }

    private static function panoramaxById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') return null;
        if (preg_match('#^https?://#i', $id)) {
            return [
                'source_provider' => 'osm_tag',
                'source_url' => $id,
                'source_page_url' => $id,
                'author' => '',
                'license_name' => 'CC-BY-SA',
                'license_url' => '',
                'attribution' => 'Panoramax',
            ];
        }
        $url = 'https://api.panoramax.xyz/api/pictures/' . rawurlencode($id);
        $json = self::httpJson($url);
        if (!is_array($json) || empty($json['assets'])) return null;
        $href = (string)(($json['assets']['thumb']['href'] ?? '') ?: ($json['assets']['sd']['href'] ?? ''));
        if ($href === '') return null;
        return [
            'source_provider' => 'osm_tag',
            'source_url' => $href,
            'source_page_url' => 'https://panoramax.xyz/#focus=picture&pic=' . rawurlencode($id),
            'author' => (string)($json['providers'][0]['name'] ?? 'Panoramax'),
            'license_name' => (string)($json['properties']['license'] ?? 'CC-BY-SA'),
            'license_url' => '',
            'attribution' => 'Panoramax',
        ];
    }

    private static function wikidataImage(string $qid): ?array
    {
        $url = 'https://www.wikidata.org/w/api.php?' . http_build_query([
            'action' => 'wbgetclaims',
            'format' => 'json',
            'entity' => strtoupper($qid),
            'property' => 'P18',
        ]);
        $json = self::httpJson($url);
        $file = $json['claims']['P18'][0]['mainsnak']['datavalue']['value'] ?? null;
        if (!is_string($file) || $file === '') return null;
        $meta = self::commonsFileMeta($file);
        if ($meta !== null) $meta['source_provider'] = 'wikidata';
        return $meta;
    }

    private static function commonsCategoryImage(string $category): ?array
    {
        $category = trim($category);
        if ($category === '') return null;
        if (!str_starts_with(strtolower($category), 'category:')) $category = 'Category:' . $category;
        $url = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
            'action' => 'query',
            'format' => 'json',
            'generator' => 'categorymembers',
            'gcmtitle' => $category,
            'gcmtype' => 'file',
            'gcmlimit' => 8,
            'prop' => 'imageinfo',
            'iiprop' => 'url|extmetadata|size|mime',
            'iiurlwidth' => 512,
        ]);
        $json = self::httpJson($url);
        $pages = $json['query']['pages'] ?? [];
        if (!is_array($pages)) return null;
        foreach ($pages as $page) {
            $title = (string)($page['title'] ?? '');
            if ($title === '') continue;
            $meta = self::commonsFileMeta(preg_replace('#^File:#i', '', $title) ?? $title);
            if ($meta !== null) return $meta;
        }
        return null;
    }

    private static function commonsFileMeta(string $file): ?array
    {
        $file = trim(str_replace('_', ' ', $file));
        if ($file === '') return null;
        if (!str_starts_with(strtolower($file), 'file:')) $file = 'File:' . $file;
        $url = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
            'action' => 'query',
            'format' => 'json',
            'titles' => $file,
            'prop' => 'imageinfo',
            'iiprop' => 'url|extmetadata|size|mime',
            'iiurlwidth' => 512,
        ]);
        $json = self::httpJson($url);
        $pages = $json['query']['pages'] ?? [];
        if (!is_array($pages)) return null;
        $page = reset($pages);
        if (!is_array($page) || empty($page['imageinfo'][0])) return null;
        $info = $page['imageinfo'][0];
        $mime = (string)($info['mime'] ?? '');
        if ($mime !== '' && !str_starts_with($mime, 'image/')) return null;
        $src = (string)($info['thumburl'] ?? $info['url'] ?? '');
        if ($src === '') return null;
        $meta = is_array($info['extmetadata'] ?? null) ? $info['extmetadata'] : [];
        $author = trim(strip_tags((string)($meta['Artist']['value'] ?? '')));
        $license = trim(strip_tags((string)($meta['LicenseShortName']['value'] ?? $meta['License']['value'] ?? '')));
        $licenseUrl = trim(strip_tags((string)($meta['LicenseUrl']['value'] ?? '')));
        $credit = trim(strip_tags((string)($meta['Credit']['value'] ?? '')));
        $pageUrl = (string)($info['descriptionurl'] ?? ('https://commons.wikimedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $file))));
        $attr = trim(implode(' / ', array_filter([$author !== '' ? $author : $credit, $license, 'Wikimedia Commons'])));
        return [
            'source_provider' => 'wikimedia',
            'source_url' => $src,
            'source_page_url' => $pageUrl,
            'author' => $author,
            'license_name' => $license,
            'license_url' => $licenseUrl,
            'attribution' => $attr,
        ];
    }

    private static function commonsFileFromUrl(string $url): ?string
    {
        if (preg_match('~/wiki/([^?\#]+)~', $url, $m)) {
            $title = urldecode(str_replace('_', ' ', $m[1]));
            if (stripos($title, 'File:') === 0) return substr($title, 5);
        }
        if (preg_match('~/wikipedia/.+/([^/]+)$~', $url, $m)) {
            return urldecode($m[1]);
        }
        return null;
    }

    /** @return ?array{bytes:string,width:int,height:int} */
    private static function convertToWebp(string $bytes): ?array
    {
        $src = tempnam(sys_get_temp_dir(), 'placeimg_');
        $dst = $src . '.webp';
        if ($src === false) return null;
        try {
            file_put_contents($src, $bytes);
            $cmd = sprintf(
                'convert %s -auto-orient -strip -resize %dx%d\\> -quality %d %s 2>/dev/null',
                escapeshellarg($src),
                self::MAX_EDGE,
                self::MAX_EDGE,
                self::WEBP_QUALITY,
                escapeshellarg($dst)
            );
            exec($cmd, $out, $code);
            if ($code !== 0 || !is_file($dst) || filesize($dst) < 32) return null;
            $webp = (string)file_get_contents($dst);
            $size = @getimagesizefromstring($webp);
            return [
                'bytes' => $webp,
                'width' => is_array($size) ? (int)$size[0] : self::MAX_EDGE,
                'height' => is_array($size) ? (int)$size[1] : self::MAX_EDGE,
            ];
        } finally {
            @unlink($src);
            @unlink($dst);
        }
    }

    private static function throttle(): void
    {
        $interval = 60.0 / self::MAX_REQUESTS_PER_MIN;
        $now = microtime(true);
        $wait = self::$nextRequestAt - $now;
        if ($wait > 0) {
            usleep((int)ceil($wait * 1_000_000));
        }
        self::$nextRequestAt = microtime(true) + $interval;
    }

    /** @return array{code:int,body:string,retry_after:int,error:string} */
    private static function httpRequest(string $url, array $headers, int $timeout = 20): array
    {
        $last = ['code' => 0, 'body' => '', 'retry_after' => 0, 'error' => 'no response'];
        for ($try = 0; $try < self::HTTP_RETRIES; $try++) {
            self::throttle();
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_USERAGENT => self::UA,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_HEADER => true,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ]);
            $raw = curl_exec($ch);
            $errno = curl_errno($ch);
            $err = $errno ? (string)curl_error($ch) : '';
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headerBlob = is_string($raw) ? substr($raw, 0, $headerSize) : '';
            $body = is_string($raw) ? substr($raw, $headerSize) : '';
            $retryAfter = self::parseRetryAfter($headerBlob);
            $last = ['code' => $code, 'body' => is_string($body) ? $body : '', 'retry_after' => $retryAfter, 'error' => $err];
            if ($code === 429) {
                $wait = $retryAfter > 0 ? $retryAfter : min(300, (int)pow(2, $try) * 5);
                fwrite(STDERR, "place-photos: HTTP 429, waiting {$wait}s before retry\n");
                sleep(max(1, $wait));
                continue;
            }
            if ($errno === CURLE_OPERATION_TIMEDOUT || $errno === CURLE_COULDNT_CONNECT || $errno === CURLE_COULDNT_RESOLVE_HOST) {
                $wait = min(60, (int)pow(2, $try) * 2);
                fwrite(STDERR, "place-photos: network error ({$err}), waiting {$wait}s\n");
                sleep($wait);
                continue;
            }
            return $last;
        }
        if ($last['code'] === 429) {
            throw new RuntimeException('rate_limited:' . max(60, (int)$last['retry_after']));
        }
        return $last;
    }

    private static function parseRetryAfter(string $headers): int
    {
        if (preg_match('/^Retry-After:\s*(\d+)\s*$/mi', $headers, $m)) {
            return max(1, min(3600, (int)$m[1]));
        }
        if (preg_match('/^Retry-After:\s*(.+)$/mi', $headers, $m)) {
            $ts = strtotime(trim($m[1]));
            if ($ts !== false) return max(1, min(3600, $ts - time()));
        }
        return 0;
    }

    private static function download(string $url): ?string
    {
        if (!preg_match('#^https?://#i', $url)) return null;
        $res = self::httpRequest($url, ['Accept: image/*,*/*;q=0.8'], 25);
        $body = $res['body'];
        $code = $res['code'];
        if ($code < 200 || $code >= 300 || $body === '' || strlen($body) < 64) return null;
        if (strlen($body) > self::MAX_DOWNLOAD_BYTES) return null;
        return $body;
    }

    /** @return array<string,mixed> */
    private static function httpJson(string $url): array
    {
        $res = self::httpRequest($url, ['Accept: application/json'], 20);
        if ($res['code'] < 200 || $res['code'] >= 300 || $res['body'] === '') return [];
        $json = json_decode($res['body'], true);
        return is_array($json) ? $json : [];
    }

    /** @return ?array{osm_type:string,osm_id:int,width_px:?int,height_px:?int,file_bytes:?int} */
    private static function findByChecksum(PDO $pdo, string $sha): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT osm_type, osm_id, width_px, height_px, file_bytes
               FROM geo_osm_place_photos
              WHERE checksum = :c AND status = 'ready' AND local_path <> ''
              LIMIT 1"
        );
        $stmt->execute([':c' => $sha]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** @param array<string,mixed> $meta */
    private static function saveMeta(PDO $pdo, string $type, int $id, array $meta): void
    {
        $pdo->prepare(
            "UPDATE geo_osm_place_photos SET
                status = :st, local_path = :path, source_provider = :prov, source_url = :surl,
                source_page_url = :purl, author = :author, license_name = :lic, license_url = :lurl,
                attribution = :attr, fetched_at = now(), width_px = :w, height_px = :h,
                file_bytes = :bytes, checksum = :sum, attempts = :att, retry_after = NULL,
                last_error = '', updated_at = now()
              WHERE osm_type = :t AND osm_id = :i"
        )->execute([
            ':st' => (string)$meta['status'],
            ':path' => (string)$meta['local_path'],
            ':prov' => (string)($meta['source_provider'] ?? ''),
            ':surl' => (string)($meta['source_url'] ?? ''),
            ':purl' => (string)($meta['source_page_url'] ?? ''),
            ':author' => (string)($meta['author'] ?? ''),
            ':lic' => (string)($meta['license_name'] ?? ''),
            ':lurl' => (string)($meta['license_url'] ?? ''),
            ':attr' => (string)($meta['attribution'] ?? ''),
            ':w' => $meta['width_px'] ?? null,
            ':h' => $meta['height_px'] ?? null,
            ':bytes' => $meta['file_bytes'] ?? null,
            ':sum' => (string)($meta['checksum'] ?? ''),
            ':att' => (int)($meta['attempts'] ?? 0),
            ':t' => $type,
            ':i' => $id,
        ]);
    }

    private static function markReadyFromExisting(PDO $pdo, string $type, int $id, string $path, string $checksum): void
    {
        $pdo->prepare(
            "UPDATE geo_osm_place_photos
                SET status = 'ready', local_path = COALESCE(NULLIF(local_path,''), :path),
                    updated_at = now(), retry_after = NULL, last_error = ''
              WHERE osm_type = :t AND osm_id = :i"
        )->execute([':path' => $path !== '' ? $path : self::relativePath($type, $id), ':t' => $type, ':i' => $id]);
    }

    private static function markStatus(PDO $pdo, string $type, int $id, string $status, int $attempts, string $error, string $note = '', int $backoffS = 0): void
    {
        $pdo->prepare(
            "UPDATE geo_osm_place_photos
                SET status = :st, attempts = :att, last_error = :err,
                    retry_after = CASE WHEN :backoff > 0 THEN now() + make_interval(secs => :backoff2) ELSE NULL END,
                    updated_at = now()
              WHERE osm_type = :t AND osm_id = :i"
        )->execute([
            ':st' => $status,
            ':att' => $attempts,
            ':err' => mb_substr($error !== '' ? $error : $note, 0, 500),
            ':backoff' => $backoffS,
            ':backoff2' => $backoffS,
            ':t' => $type,
            ':i' => $id,
        ]);
    }

    public static function metaFor(PDO $pdo, string $osmType, int $osmId): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT osm_type, osm_id, status, local_path, source_provider, source_url, source_page_url,
                    author, license_name, license_url, attribution, fetched_at, width_px, height_px,
                    file_bytes, checksum
               FROM geo_osm_place_photos
              WHERE osm_type = :t AND osm_id = :i"
        );
        $stmt->execute([':t' => self::normalizeType($osmType), ':i' => $osmId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** @param list<array{0:string,1:int}> $refs @return array<string,array<string,mixed>> */
    public static function metasFor(PDO $pdo, array $refs): array
    {
        if ($refs === []) return [];
        $parts = [];
        $params = [];
        foreach ($refs as $i => [$t, $id]) {
            $parts[] = "(osm_type = :t$i AND osm_id = :i$i)";
            $params[":t$i"] = self::normalizeType($t);
            $params[":i$i"] = (int)$id;
        }
        $stmt = $pdo->prepare(
            'SELECT osm_type, osm_id, status, local_path, source_provider, attribution, license_name, author
               FROM geo_osm_place_photos WHERE ' . implode(' OR ', $parts)
        );
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = $row['osm_type'] . ':' . $row['osm_id'];
            $out[$key] = $row;
        }
        return $out;
    }

    public static function serveFile(string $osmType, int $osmId): void
    {
        $path = self::existingPhotoPath($osmType, $osmId);
        if ($path === null) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'not_found']);
            return;
        }
        $primary = self::absolutePath($osmType, $osmId);
        if ($path !== $primary) {
            @mkdir(dirname($primary), 0775, true);
            @copy($path, $primary);
            if (is_file($primary) && filesize($primary) > 0) $path = $primary;
        }
        $etag = '"' . hash_file('sha256', $path) . '"';
        header('Content-Type: image/webp');
        header('Cache-Control: private, max-age=604800, immutable');
        header('ETag: ' . $etag);
        header('X-Content-Type-Options: nosniff');
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim((string)$_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            http_response_code(304);
            return;
        }
        header('Content-Length: ' . (string)filesize($path));
        readfile($path);
    }

    private static function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        return in_array($type, ['node', 'way', 'relation'], true) ? $type : 'node';
    }

    private static function nameLooksRelated(string $placeName, string $title): bool
    {
        $a = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $placeName) ?? '');
        $b = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $title) ?? '');
        if ($a === '' || $b === '') return false;
        if (str_contains($b, $a) || str_contains($a, $b)) return true;
        $tokens = array_values(array_filter(explode(' ', $a), static fn(string $t): bool => mb_strlen($t) >= 3));
        if ($tokens === []) return false;
        $hits = 0;
        foreach ($tokens as $t) if (str_contains($b, $t)) $hits++;
        return $hits >= max(1, (int)ceil(count($tokens) * 0.5));
    }

    private static function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371000.0;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dp = deg2rad($lat2 - $lat1);
        $dl = deg2rad($lon2 - $lon1);
        $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
        return 2 * $r * asin(min(1.0, sqrt($a)));
    }
}
