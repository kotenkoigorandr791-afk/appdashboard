<?php
/**
 * Map tile cache.
 *
 * Leaflet used to hit third-party tile servers for every pan/zoom, which is
 * slow on first paint and re-downloads the same tiles on every visit. This
 * class proxies allow-listed providers through the app: tiles are validated,
 * stored on disk once and served with long-lived HTTP caching, so repeated
 * views never touch the upstream tile server.
 */
require_once __DIR__ . '/geolocation_map.php';

final class StepsGeolocationTiles
{
    public const DEFAULT_TTL = 259200; // 3 days — short enough to refresh, long enough for day flips
    public const MAX_BYTES = 2097152;   // 2 MB per tile

    public static function cacheDir(): string
    {
        $dir = getenv('GEO_TILE_CACHE_DIR');
        if (!is_string($dir) || trim($dir) === '') {
            $base = function_exists('appdata_data_dir') ? appdata_data_dir() : sys_get_temp_dir();
            $dir = rtrim($base, '/') . '/steps_tiles';
        }
        return rtrim($dir, '/');
    }

    public static function ttl(): int
    {
        $raw = getenv('GEO_TILE_CACHE_TTL');
        if (is_string($raw) && is_numeric($raw)) return max(60, min(31536000, (int)$raw));
        return self::DEFAULT_TTL;
    }

    public static function provider(string $key): ?array
    {
        $key = strtolower(trim($key));
        return StepsGeolocationMap::PROVIDERS[$key] ?? null;
    }

    /** @return array{z:int,x:int,y:int}|null */
    public static function tile(string $rawZ, string $rawX, string $rawY): ?array
    {
        if (!preg_match('/^\d{1,2}$/', $rawZ) || !preg_match('/^\d{1,8}$/', $rawX) || !preg_match('/^\d{1,8}$/', $rawY)) return null;
        $z = (int)$rawZ;
        $x = (int)$rawX;
        $y = (int)$rawY;
        $maxZoom = 19;
        if ($z < 0 || $z > $maxZoom) return null;
        $max = (2 ** $z) - 1;
        if ($x < 0 || $x > $max || $y < 0 || $y > $max) return null;
        return ['z' => $z, 'x' => $x, 'y' => $y];
    }

    public static function upstreamUrl(string $provider, int $z, int $x, int $y): ?string
    {
        $conf = self::provider($provider);
        if ($conf === null) return null;
        $template = (string)($conf['url'] ?? '');
        if ($template === '') return null;
        $subdomains = ['a', 'b', 'c', 'd'];
        $url = str_replace(
            ['{s}', '{z}', '{x}', '{y}', '{r}', '{ratio}'],
            [$subdomains[($x + $y) % count($subdomains)], (string)$z, (string)$x, (string)$y, '', ''],
            $template
        );
        if (!preg_match('#^https://[a-z0-9.\-]+/#i', $url)) return null;
        return $url;
    }

    public static function path(string $provider, int $z, int $x, int $y): string
    {
        $key = preg_replace('/[^a-z0-9_\-]/', '', strtolower($provider));
        return self::cacheDir() . '/' . $key . '/' . $z . '/' . $x . '/' . $y . '.tile';
    }

    /** @return array{path:string,body:string,mime:string,fetched_at:int}|null */
    public static function read(string $path): ?array
    {
        if (!is_file($path)) return null;
        $body = @file_get_contents($path);
        if (!is_string($body) || $body === '') return null;
        $meta = @json_decode((string)@file_get_contents($path . '.json'), true);
        return [
            'path' => $path,
            'body' => $body,
            'mime' => is_array($meta) && is_string($meta['mime'] ?? null) ? (string)$meta['mime'] : self::mimeFromBytes($body),
            'fetched_at' => is_array($meta) && isset($meta['fetched_at']) ? (int)$meta['fetched_at'] : (int)@filemtime($path),
        ];
    }

    public static function store(string $path, string $body, string $mime): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return false;
        $tmp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($tmp, $body, LOCK_EX) === false) return false;
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
        @file_put_contents($path . '.json', json_encode(['mime' => $mime, 'fetched_at' => time()]), LOCK_EX);
        return true;
    }

    public static function mimeFromBytes(string $body): string
    {
        if (str_starts_with($body, "\x89PNG")) return 'image/png';
        if (str_starts_with($body, "\xFF\xD8\xFF")) return 'image/jpeg';
        if (str_starts_with($body, 'RIFF') && substr($body, 8, 4) === 'WEBP') return 'image/webp';
        if (str_starts_with($body, 'GIF8')) return 'image/gif';
        return 'application/octet-stream';
    }

    /**
     * @return array{body:string,mime:string,status:int}|null
     */
    public static function fetch(string $url): ?array
    {
        $headers = [
            'User-Agent: StepsMap/1.0 (self-hosted location journal)',
            'Accept: image/png,image/jpeg,image/webp,image/*;q=0.8',
        ];
        $body = null;
        $mime = '';
        $status = 0;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            ];
            // Prefer IPv4: some Docker setups resolve AAAA but cannot route IPv6.
            if (defined('CURL_IPRESOLVE_V4')) $opts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
            curl_setopt_array($ch, $opts);
            $raw = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $mime = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            if (is_string($raw)) $body = $raw;
        } else {
            $context = stream_context_create([
                'http' => ['method' => 'GET', 'header' => implode("\r\n", $headers), 'timeout' => 15, 'follow_location' => 1],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $raw = @file_get_contents($url, false, $context);
            if (is_string($raw)) {
                $body = $raw;
                $status = 200;
            }
        }
        if (!is_string($body) || $body === '' || strlen($body) > self::MAX_BYTES) return null;
        if ($status !== 0 && ($status < 200 || $status >= 300)) return null;
        $detected = self::mimeFromBytes($body);
        if ($detected === 'application/octet-stream') return null;
        return ['body' => $body, 'mime' => str_starts_with($mime, 'image/') ? $mime : $detected, 'status' => $status];
    }

    /**
     * Read from disk or download once; stale tiles are kept as a fallback when
     * the upstream server is unavailable.
     *
     * @return array{body:string,mime:string,cached:bool,fetched_at:int,stale:bool}|null
     */
    public static function get(string $provider, int $z, int $x, int $y): ?array
    {
        $url = self::upstreamUrl($provider, $z, $x, $y);
        if ($url === null) return null;
        $path = self::path($provider, $z, $x, $y);
        $cached = self::read($path);
        $fresh = $cached !== null && (time() - $cached['fetched_at']) < self::ttl();
        if ($fresh) {
            return ['body' => $cached['body'], 'mime' => $cached['mime'], 'cached' => true, 'fetched_at' => $cached['fetched_at'], 'stale' => false];
        }
        $downloaded = self::fetch($url);
        if ($downloaded !== null) {
            self::store($path, $downloaded['body'], $downloaded['mime']);
            return ['body' => $downloaded['body'], 'mime' => $downloaded['mime'], 'cached' => false, 'fetched_at' => time(), 'stale' => false];
        }
        if ($cached !== null) {
            return ['body' => $cached['body'], 'mime' => $cached['mime'], 'cached' => true, 'fetched_at' => $cached['fetched_at'], 'stale' => true];
        }
        return null;
    }
}
