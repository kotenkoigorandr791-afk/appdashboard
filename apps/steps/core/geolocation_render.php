<?php
/**
 * Route posters and route videos (tasks 25 and 26).
 *
 * Both run as jobs in this process. The geometry snapshot is taken at job
 * creation from simplified PostGIS lines, so a retry draws the same picture
 * and a later permission change can invalidate a job that has not finished.
 * Files stay on the existing data volume. Video uses the image's ffmpeg
 * (libx264, High, yuv420p, +faststart). No extra container.
 */
require_once __DIR__ . '/geolocation_domain.php';
require_once __DIR__ . '/geolocation_map.php';
require_once __DIR__ . '/geolocation_permissions.php';

final class StepsGeolocationRenderError extends RuntimeException
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

final class StepsGeolocationRender
{
    public const PROCESSOR_VERSION = 'render-1';
    public const VIDEO_CODEC = 'libx264';
    public const VIDEO_PROFILE = 'high';
    public const VIDEO_PIX_FMT = 'yuv420p';
    public const FORMATS = ['portrait', 'landscape', 'square'];
    public const THEMES = ['light', 'dark', 'paper'];
    public const MAX_USER_JOBS = 1;
    public const MAX_VIDEO_SECONDS = 45;
    public const MAX_FPS = 12;
    public const MAX_EDGE = 960;

    public static function uuid(): string
    {
        $b = bin2hex(random_bytes(16));
        return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
    }

    public static function encoderStatus(): array
    {
        $bin = self::ffmpegBin();
        if ($bin === null) return ['available' => false, 'reason' => 'encoder_unavailable'];
        $out = [];
        $code = 1;
        exec(escapeshellarg($bin) . ' -hide_banner -encoders 2>&1', $out, $code);
        $text = implode("\n", $out);
        $ok = $code === 0 && str_contains($text, 'libx264');
        return ['available' => $ok, 'reason' => $ok ? '' : 'encoder_unavailable', 'codec' => self::VIDEO_CODEC, 'profile' => self::VIDEO_PROFILE, 'pix_fmt' => self::VIDEO_PIX_FMT];
    }

    public static function ffmpegBin(): ?string
    {
        foreach (['ffmpeg', '/usr/bin/ffmpeg'] as $bin) {
            $out = [];
            $code = 1;
            exec(escapeshellarg($bin) . ' -version 2>&1', $out, $code);
            if ($code === 0 && isset($out[0]) && str_starts_with($out[0], 'ffmpeg')) return $bin;
        }
        return null;
    }

    public static function size(string $format, int $edge): array
    {
        $edge = max(320, min(self::MAX_EDGE, $edge));
        if ($edge % 2 !== 0) $edge--;
        return match ($format) {
            'portrait' => [$edge, (int)round($edge * 1.25 / 2) * 2],
            'landscape' => [(int)round($edge * 1.25 / 2) * 2, $edge],
            default => [$edge, $edge],
        };
    }

    public static function theme(string $name): array
    {
        return match ($name) {
            'light' => ['bg' => [244, 246, 248], 'ink' => [23, 32, 42], 'route' => [37, 99, 235], 'muted' => [90, 100, 112], 'marker' => [220, 80, 50]],
            'paper' => ['bg' => [245, 236, 220], 'ink' => [62, 42, 28], 'route' => [166, 60, 36], 'muted' => [120, 90, 70], 'marker' => [40, 90, 70]],
            default => ['bg' => [16, 22, 32], 'ink' => [236, 240, 245], 'route' => [96, 165, 250], 'muted' => [160, 174, 192], 'marker' => [251, 146, 60]],
        };
    }

    /** @return array<string,mixed> */
    public static function snapshot(PDO $pdo, string $viewer, array $input): array
    {
        $from = (string)($input['from'] ?? '');
        $to = (string)($input['to'] ?? '');
        if (strtotime($from) === false || strtotime($to) === false || strtotime($from) >= strtotime($to)) {
            throw new StepsGeolocationRenderError('invalid_range');
        }
        $family = isset($input['family_email']) ? (string)$input['family_email'] : null;
        $auth = StepsGeolocationMap::authorize($pdo, $viewer, $family, $from, $to);
        if ($auth['error'] !== null) throw new StepsGeolocationRenderError((string)$auth['error'], 403);
        $owner = (string)$auth['owner'];
        $scope = is_array($auth['scope'] ?? null) ? $auth['scope'] : null;
        if ($scope !== null && !empty($scope['places_only'])) throw new StepsGeolocationRenderError('family_access_denied', 403);
        $bbox = StepsGeolocationMap::dataBbox($pdo, $owner, $from, $to);
        if ($bbox === null) throw new StepsGeolocationRenderError('no_geometry');
        $span = max($bbox['maxLon'] - $bbox['minLon'], $bbox['maxLat'] - $bbox['minLat']);
        if ($span > StepsGeolocationMap::MAX_SPAN_DEG) {
            $bbox['maxLon'] = min(180.0, $bbox['minLon'] + StepsGeolocationMap::MAX_SPAN_DEG);
            $bbox['maxLat'] = min(90.0, $bbox['minLat'] + StepsGeolocationMap::MAX_SPAN_DEG);
        }
        $result = StepsGeolocationMap::features($pdo, $owner, $bbox, [
            'zoom' => 14,
            'from' => $from,
            'to' => $to,
            'layers' => ['trips', 'places'],
            'limit' => 200,
        ]);
        $lines = [];
        $markers = [];
        $distance = 0.0;
        $started = null;
        $ended = null;
        $ended = null;
        foreach ($result['features'] as $feature) {
            $props = $feature['properties'] ?? [];
            $geom = $feature['geometry'] ?? [];
            if (($props['layer'] ?? '') === 'trip' && ($geom['type'] ?? '') === 'LineString') {
                $coords = [];
                foreach ($geom['coordinates'] as $pair) {
                    if (!is_array($pair) || count($pair) < 2) continue;
                    $coords[] = [round((float)$pair[0], 5), round((float)$pair[1], 5)];
                }
                if (count($coords) >= 2) {
                    $lines[] = $coords;
                    $distance += (float)($props['distance_m'] ?? 0);
                    $started = $started === null ? $props['started_at'] : min($started, $props['started_at']);
                    if (!empty($props['ended_at'])) $ended = $ended === null ? $props['ended_at'] : max($ended, $props['ended_at']);
                    $ended = $ended === null ? $props['ended_at'] : max($ended, $props['ended_at']);
                }
            }
            if (($props['layer'] ?? '') === 'place' && ($geom['type'] ?? '') === 'Point' && $owner === $viewer) {
                $c = $geom['coordinates'];
                $markers[] = ['lon' => round((float)$c[0], 5), 'lat' => round((float)$c[1], 5), 'name' => (string)($props['name'] ?? '')];
            }
        }
        if ($lines === []) throw new StepsGeolocationRenderError('no_geometry');
        $generation = $scope['generation'] ?? 0;
        return [
            'owner' => $owner,
            'viewer' => $viewer,
            'generation' => (int)$generation,
            'from' => $from,
            'to' => $to,
            'bbox' => $bbox,
            'lines' => $lines,
            'markers' => array_slice($markers, 0, 24),
            'distance_m' => round($distance, 1),
            'started_at' => $started,
            'ended_at' => $ended,
            'ended_at' => $ended,
            'title' => mb_substr(trim((string)($input['title'] ?? '')), 0, 80),
            'provider' => in_array((string)($input['provider'] ?? ''), self::MAP_PROVIDERS, true) ? (string)$input['provider'] : 'esri_dark',
            'tint' => preg_match('/^#[0-9a-f]{6}$/i', (string)($input['tint'] ?? '')) ? (string)$input['tint'] : '#000000',
            'tint_opacity' => max(0, min(80, (int)($input['tint_opacity'] ?? 0))),
            'route_color' => preg_match('/^#[0-9a-f]{6}$/i', (string)($input['route_color'] ?? '')) ? (string)$input['route_color'] : '',
            'camera' => ((string)($input['camera'] ?? 'follow')) === 'overview' ? 'overview' : 'follow',
        ];
    }

    public static function normalizeConfig(string $kind, array $input): array
    {
        $format = strtolower((string)($input['format'] ?? 'portrait'));
        if (!in_array($format, self::FORMATS, true)) throw new StepsGeolocationRenderError('invalid_format');
        $theme = strtolower((string)($input['theme'] ?? 'dark'));
        if (!in_array($theme, self::THEMES, true)) $theme = 'dark';
        $config = [
            'format' => $format,
            'theme' => $theme,
            'attribution' => mb_substr(trim((string)($input['attribution'] ?? '© OpenStreetMap contributors')), 0, 80),
            'title' => mb_substr(trim((string)($input['title'] ?? '')), 0, 80),
            'provider' => in_array((string)($input['provider'] ?? ''), self::MAP_PROVIDERS, true) ? (string)$input['provider'] : 'esri_dark',
            'tint' => preg_match('/^#[0-9a-f]{6}$/i', (string)($input['tint'] ?? '')) ? (string)$input['tint'] : '#000000',
            'tint_opacity' => max(0, min(80, (int)($input['tint_opacity'] ?? 0))),
            'route_color' => preg_match('/^#[0-9a-f]{6}$/i', (string)($input['route_color'] ?? '')) ? (string)$input['route_color'] : '',
            'camera' => ((string)($input['camera'] ?? 'follow')) === 'overview' ? 'overview' : 'follow',
        ];
        if ($kind === 'poster') {
            $file = strtolower((string)($input['file'] ?? 'png'));
            if (!in_array($file, ['png', 'pdf'], true)) throw new StepsGeolocationRenderError('invalid_format');
            $config['file'] = $file;
            $config['edge'] = (int)($input['edge'] ?? 800);
        } else {
            $duration = (int)($input['duration'] ?? 12);
            $fps = (int)($input['fps'] ?? 8);
            $config['duration'] = max(4, min(self::MAX_VIDEO_SECONDS, $duration));
            $config['fps'] = max(4, min(self::MAX_FPS, $fps));
            $config['edge'] = (int)($input['edge'] ?? 640);
        }
        return $config;
    }

    public static function create(PDO $pdo, string $viewer, string $kind, array $input): array
    {
        if (!in_array($kind, ['poster', 'video'], true)) throw new StepsGeolocationRenderError('invalid_kind');
        if ($kind === 'video') {
            $encoder = self::encoderStatus();
            if (!$encoder['available']) throw new StepsGeolocationRenderError('encoder_unavailable', 503);
        }
        $busy = $pdo->prepare("SELECT COUNT(*) FROM geo_render_jobs WHERE user_email = :owner AND state = 'processing'");
        $busy->execute([':owner' => $viewer]);
        if ((int)$busy->fetchColumn() >= self::MAX_USER_JOBS) throw new StepsGeolocationRenderError('render_busy', 429);
        $config = self::normalizeConfig($kind, $input);
        $snapshot = self::snapshot($pdo, $viewer, $input);
        if ($config['title'] !== '') $snapshot['title'] = $config['title'];
        $id = self::uuid();
        // Rendered videos/posters live for one hour only; the UI offers an
        // explicit delete button before that.
        $expires = gmdate('c', time() + 3600);
        $pdo->prepare(
            "INSERT INTO geo_render_jobs(id, user_email, kind, state, processor_version, config_json, snapshot_json, expires_at)
             VALUES(:id, :owner, :kind, 'pending', :version, :config, :snapshot, :expires)"
        )->execute([
            ':id' => $id,
            ':owner' => $viewer,
            ':kind' => $kind,
            ':version' => self::PROCESSOR_VERSION,
            ':config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':expires' => $expires,
        ]);
        return self::find($pdo, $viewer, $id);
    }

    public static function find(PDO $pdo, string $owner, string $id): ?array
    {
        $stmt = $pdo->prepare('SELECT id, kind, state, progress, error_code, artifact_id, processor_version, created_at, updated_at, expires_at FROM geo_render_jobs WHERE id = :id AND user_email = :owner');
        $stmt->execute([':id' => $id, ':owner' => $owner]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function list(PDO $pdo, string $owner): array
    {
        $stmt = $pdo->prepare("SELECT j.id, j.kind, j.state, j.progress, j.error_code, j.artifact_id, j.created_at, j.expires_at, a.format AS file_format, a.size_bytes, a.expires_at AS artifact_expires_at, j.config_json->>'title' AS title, j.config_json->>'format' AS orientation, j.snapshot_json->>'from' AS range_from, j.snapshot_json->>'to' AS range_to FROM geo_render_jobs j LEFT JOIN geo_artifacts a ON a.id = j.artifact_id WHERE j.user_email = :owner AND (j.artifact_id IS NULL OR a.deleted_at IS NULL) AND (j.artifact_id IS NULL OR a.expires_at IS NULL OR a.expires_at > now()) ORDER BY j.created_at DESC LIMIT 20");
        $stmt->execute([':owner' => $owner]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function cancel(PDO $pdo, string $owner, string $id): void
    {
        $stmt = $pdo->prepare("UPDATE geo_render_jobs SET cancel_requested = true, updated_at = now() WHERE id = :id AND user_email = :owner AND state IN ('pending','processing')");
        $stmt->execute([':id' => $id, ':owner' => $owner]);
        if ($stmt->rowCount() === 0) throw new StepsGeolocationRenderError('render_not_found', 404);
    }

    /** Manually delete a finished render and its artifact file. */
    public static function delete(PDO $pdo, string $owner, string $id): void
    {
        $job = self::find($pdo, $owner, $id);
        if (!$job) throw new StepsGeolocationRenderError('render_not_found', 404);
        if (in_array((string)$job['state'], ['pending', 'processing'], true)) {
            throw new StepsGeolocationRenderError('render_running', 409);
        }
        $pdo->beginTransaction();
        try {
            if (!empty($job['artifact_id'])) {
                $artifact = $pdo->prepare('SELECT storage_path FROM geo_artifacts WHERE id = :id AND user_email = :owner');
                $artifact->execute([':id' => $job['artifact_id'], ':owner' => $owner]);
                $path = (string)($artifact->fetchColumn() ?: '');
                if ($path !== '' && is_file($path)) @unlink($path);
                $pdo->prepare('UPDATE geo_artifacts SET deleted_at = now() WHERE id = :id AND user_email = :owner')
                    ->execute([':id' => $job['artifact_id'], ':owner' => $owner]);
            }
            $pdo->prepare('DELETE FROM geo_render_jobs WHERE id = :id AND user_email = :owner')
                ->execute([':id' => $id, ':owner' => $owner]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function claim(PDO $pdo): ?array
    {
        $pdo->beginTransaction();
        try {
            $row = $pdo->query("SELECT id FROM geo_render_jobs WHERE state = 'pending' AND cancel_requested = false ORDER BY created_at LIMIT 1 FOR UPDATE SKIP LOCKED")->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $pdo->commit();
                return null;
            }
            $pdo->prepare("UPDATE geo_render_jobs SET state = 'processing', progress = 1, updated_at = now() WHERE id = :id")->execute([':id' => $row['id']]);
            $full = $pdo->prepare('SELECT * FROM geo_render_jobs WHERE id = :id');
            $full->execute([':id' => $row['id']]);
            $job = $full->fetch(PDO::FETCH_ASSOC);
            $pdo->commit();
            return $job ?: null;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function runJob(PDO $pdo, array $job): void
    {
        $id = (string)$job['id'];
        $owner = (string)$job['user_email'];
        $config = json_decode((string)$job['config_json'], true) ?: [];
        $snapshot = json_decode((string)$job['snapshot_json'], true) ?: [];
        $started = time();
        try {
            if (self::cancelled($pdo, $id)) throw new StepsGeolocationRenderError('cancelled');
            if ((int)($snapshot['generation'] ?? 0) > 0) {
                $check = $pdo->prepare('SELECT generation FROM geo_location_grants WHERE owner_email = :owner AND viewer_email = :viewer AND revoked_at IS NULL ORDER BY updated_at DESC LIMIT 1');
                $check->execute([':owner' => $snapshot['owner'], ':viewer' => $owner]);
                $generation = $check->fetchColumn();
                if ($generation === false || (int)$generation !== (int)$snapshot['generation']) {
                    throw new StepsGeolocationRenderError('permission_changed');
                }
            }
            $kind = (string)$job['kind'];
            if ($kind === 'poster') {
                $bytes = ($config['file'] ?? 'png') === 'pdf' ? self::pdfBytes($snapshot, $config) : self::pngBytes($snapshot, $config);
                $ext = ($config['file'] ?? 'png') === 'pdf' ? 'pdf' : 'png';
            } else {
                $bytes = self::videoBytes($pdo, $id, $snapshot, $config, $started);
                $ext = 'mp4';
            }
            if (time() - $started > 300) throw new StepsGeolocationRenderError('render_timeout');
            $artifact = self::store($pdo, $owner, $id, $ext, $bytes);
            $pdo->prepare("UPDATE geo_render_jobs SET state = 'ready', progress = 100, artifact_id = :artifact, updated_at = now() WHERE id = :id")
                ->execute([':artifact' => $artifact, ':id' => $id]);
        } catch (StepsGeolocationRenderError $e) {
            $state = $e->errorCode === 'cancelled' ? 'cancelled' : 'failed';
            $pdo->prepare('UPDATE geo_render_jobs SET state = :state, error_code = :error, updated_at = now() WHERE id = :id')
                ->execute([':state' => $state, ':error' => $e->errorCode, ':id' => $id]);
        } catch (Throwable $e) {
            error_log('render job: ' . $e->getMessage());
            $pdo->prepare("UPDATE geo_render_jobs SET state = 'failed', error_code = 'render_failed', updated_at = now() WHERE id = :id")->execute([':id' => $id]);
        }
    }

    private static function cancelled(PDO $pdo, string $id): bool
    {
        $stmt = $pdo->prepare('SELECT cancel_requested FROM geo_render_jobs WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return (bool)$stmt->fetchColumn();
    }

    public const MAP_PROVIDERS = ['none', 'esri_dark', 'esri_imagery', 'esri_street', 'osm', 'opentopomap'];
    private const TILE_LIMIT = 260;
    private static $tileMem = [];

    private static function dataDir(string $sub): string
    {
        $helper = dirname(__DIR__, 3) . '/shell/storage_helper.php';
        if (is_file($helper)) require_once $helper;
        $base = function_exists('appdata_app_base_dir') ? rtrim(appdata_app_base_dir('steps'), '/') : rtrim(sys_get_temp_dir(), '/') . '/appdashboard_steps/upload_sessions';
        return $base . '/' . $sub;
    }

    private static function hexColor(string $hex, array $fallback): array
    {
        if (preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)) return [hexdec($m[1]), hexdec($m[2]), hexdec($m[3])];
        return $fallback;
    }

    private static function worldPx(float $lon, float $lat, int $z): array
    {
        $size = 256 * (2 ** $z);
        $sin = sin(deg2rad(max(-85.0511, min(85.0511, $lat))));
        return [($lon + 180) / 360 * $size, (0.5 - log((1 + $sin) / (1 - $sin)) / (4 * M_PI)) * $size];
    }

    private static function providerMaxZoom(string $provider): int
    {
        $p = StepsGeolocationMap::provider($provider);
        return max(3, min(17, (int)($p['max_zoom'] ?? 17)));
    }

    private static function lineBounds(array $snapshot): array
    {
        $minLon = 180.0; $minLat = 90.0; $maxLon = -180.0; $maxLat = -90.0;
        foreach ($snapshot['lines'] ?? [] as $line) {
            foreach ($line as $pair) {
                $minLon = min($minLon, (float)$pair[0]); $maxLon = max($maxLon, (float)$pair[0]);
                $minLat = min($minLat, (float)$pair[1]); $maxLat = max($maxLat, (float)$pair[1]);
            }
        }
        return [$minLon, $minLat, $maxLon, $maxLat];
    }

    private static function fitZoom(array $bounds, int $w, int $h, string $provider): int
    {
        for ($z = self::providerMaxZoom($provider); $z > 1; $z--) {
            [$x0, $y0] = self::worldPx($bounds[0], $bounds[3], $z);
            [$x1, $y1] = self::worldPx($bounds[2], $bounds[1], $z);
            if (($x1 - $x0) <= $w && ($y1 - $y0) <= $h) return $z;
        }
        return 1;
    }

    private static function tilesFor(float $cx, float $cy, int $z, int $w, int $h): array
    {
        $n = 2 ** $z;
        $out = [];
        for ($ty = (int)floor(($cy - $h / 2) / 256); $ty <= (int)floor(($cy + $h / 2) / 256); $ty++) {
            if ($ty < 0 || $ty >= $n) continue;
            for ($tx = (int)floor(($cx - $w / 2) / 256); $tx <= (int)floor(($cx + $w / 2) / 256); $tx++) {
                $out[$z . '/' . ((($tx % $n) + $n) % $n) . '/' . $ty] = [$z, ((($tx % $n) + $n) % $n), $ty];
            }
        }
        return $out;
    }

    private static function tilePath(string $provider, int $z, int $x, int $y): string
    {
        return self::dataDir('geo_tiles') . "/$provider/$z/$x-$y.img";
    }

    public static function ensureTiles(string $provider, array $tiles, ?callable $tick = null): int
    {
        if ($provider === 'none') return 0;
        $missing = [];
        foreach ($tiles as $t) {
            $path = self::tilePath($provider, $t[0], $t[1], $t[2]);
            if (is_file($path) && filesize($path) > 0 && time() - filemtime($path) < 30 * 86400) continue;
            $missing[] = $t;
        }
        $total = count($missing);
        $done = 0;
        $failed = 0;
        $tpl = StepsGeolocationMap::provider($provider)['url'];
        foreach (array_chunk($missing, 6) as $batch) {
            $mh = curl_multi_init();
            $handles = [];
            foreach ($batch as $t) {
                $ch = curl_init(str_replace(['{z}', '{x}', '{y}', '{s}', '{r}'], [$t[0], $t[1], $t[2], 'a', ''], $tpl));
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => 'AppDashboardSteps/1.0 (route renderer)']);
                curl_multi_add_handle($mh, $ch);
                $handles[] = [$ch, $t];
            }
            do {
                $status = curl_multi_exec($mh, $running);
                if ($running) curl_multi_select($mh, 1.0);
            } while ($running && $status === CURLM_OK);
            foreach ($handles as [$ch, $t]) {
                $body = curl_multi_getcontent($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                curl_multi_remove_handle($mh, $ch);
                $ok = $code === 200 && is_string($body) && strlen($body) > 100 && @imagecreatefromstring($body) !== false;
                if ($ok) {
                    $path = self::tilePath($provider, $t[0], $t[1], $t[2]);
                    if (!is_dir(dirname($path))) @mkdir(dirname($path), 0775, true);
                    file_put_contents($path, $body);
                } else {
                    $failed++;
                }
                $done++;
            }
            if ($tick) $tick($done, $total);
        }
        return $failed;
    }

    private static function tileImg(string $provider, int $z, int $x, int $y)
    {
        $key = "$provider/$z/$x/$y";
        if (isset(self::$tileMem[$key])) {
            $im = self::$tileMem[$key];
            unset(self::$tileMem[$key]);
            self::$tileMem[$key] = $im;
            return $im;
        }
        $path = self::tilePath($provider, $z, $x, $y);
        if (!is_file($path)) return null;
        $im = @imagecreatefromstring((string)file_get_contents($path));
        if (!$im) return null;
        self::$tileMem[$key] = $im;
        if (count(self::$tileMem) > 80) unset(self::$tileMem[array_key_first(self::$tileMem)]);
        return $im;
    }

    private static function flatPoints(array $snapshot): array
    {
        $flat = [];
        foreach ($snapshot['lines'] ?? [] as $line) foreach ($line as $pair) $flat[] = [(float)$pair[0], (float)$pair[1]];
        return $flat;
    }

    private static function headPoint(array $flat, float $head): array
    {
        $last = count($flat) - 1;
        $head = max(0.0, min((float)$last, $head));
        $i = (int)floor($head);
        $f = $head - $i;
        $a = $flat[$i];
        $b = $flat[min($last, $i + 1)];
        return [$a[0] + ($b[0] - $a[0]) * $f, $a[1] + ($b[1] - $a[1]) * $f];
    }

    /** @return array{z:int,centers:list<array{0:float,1:float}>,tiles:array<string,array>} */
    public static function plan(array $snapshot, array $config, int $w, int $h, int $frames, int $panel): array
    {
        $provider = (string)($config['provider'] ?? 'none');
        $bounds = self::lineBounds($snapshot);
        $fit = self::fitZoom($bounds, $w - 80, $h - $panel - 60, $provider);
        $follow = ($config['camera'] ?? 'follow') === 'follow' && $frames > 1;
        $flat = self::flatPoints($snapshot);
        $z = $follow ? min(self::providerMaxZoom($provider), 16, max($fit + 2, 11)) : $fit;
        while (true) {
            $centers = [];
            $tiles = [];
            if ($follow) {
                $cam = null;
                for ($f = 1; $f <= $frames; $f++) {
                    $head = self::headPoint($flat, ($f / $frames) * (count($flat) - 1));
                    [$hx, $hy] = self::worldPx($head[0], $head[1], $z);
                    $cam = $cam === null ? [$hx, $hy] : [$cam[0] + ($hx - $cam[0]) * 0.22, $cam[1] + ($hy - $cam[1]) * 0.22];
                    $centers[] = [$cam[0], $cam[1] + $panel / 2];
                    $tiles += self::tilesFor($cam[0], $cam[1] + $panel / 2, $z, $w, $h);
                }
            } else {
                [$x0, $y0] = self::worldPx($bounds[0], $bounds[3], $z);
                [$x1, $y1] = self::worldPx($bounds[2], $bounds[1], $z);
                $c = [($x0 + $x1) / 2, ($y0 + $y1) / 2 + $panel / 2];
                for ($f = 0; $f < max(1, $frames); $f++) $centers[] = $c;
                $tiles = self::tilesFor($c[0], $c[1], $z, $w, $h);
            }
            if (count($tiles) <= self::TILE_LIMIT || $z <= max(3, $fit)) break;
            $z--;
        }
        return ['z' => $z, 'centers' => $centers, 'tiles' => $tiles];
    }

    private static function font(bool $bold): ?string
    {
        $path = '/usr/share/fonts/truetype/dejavu/' . ($bold ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf');
        return function_exists('imagettftext') && is_file($path) ? $path : null;
    }

    private static function text($img, float $size, int $x, int $y, int $color, bool $bold, string $text): void
    {
        $font = self::font($bold);
        if ($font !== null) {
            imagettftext($img, $size, 0, $x, $y, $color, $font, $text);
            return;
        }
        imagestring($img, $size > 14 ? 5 : 3, $x, $y - 12, self::ascii($text), $color);
    }

    /** @return \GdImage */
    public static function compose(array $snapshot, array $config, int $w, int $h, float $cx, float $cy, int $z, ?float $head, float $progress, int $panel)
    {
        if (!function_exists('imagecreatetruecolor')) throw new StepsGeolocationRenderError('renderer_unavailable', 503);
        $provider = (string)($config['provider'] ?? 'none');
        $theme = self::theme((string)($config['theme'] ?? 'dark'));
        $img = imagecreatetruecolor($w, $h);
        imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocate($img, ...$theme['bg']));
        $left = $cx - $w / 2;
        $top = $cy - $h / 2;
        if ($provider !== 'none') {
            foreach (self::tilesFor($cx, $cy, $z, $w, $h) as $t) {
                $tile = self::tileImg($provider, $t[0], $t[1], $t[2]);
                if ($tile === null) continue;
                $n = 2 ** $z;
                $baseX = $t[1] * 256;
                $rawX = (int)floor(($cx - $w / 2) / 256);
                $shift = 0;
                for ($tx = $rawX; $tx <= (int)floor(($cx + $w / 2) / 256); $tx++) {
                    if ((($tx % $n) + $n) % $n === $t[1]) {
                        imagecopy($img, $tile, (int)round($tx * 256 - $left), (int)round($t[2] * 256 - $top), 0, 0, 256, 256);
                    }
                }
                unset($baseX, $shift);
            }
        }
        $tintOpacity = max(0, min(80, (int)($config['tint_opacity'] ?? 0)));
        if ($tintOpacity > 0) {
            $tint = self::hexColor((string)($config['tint'] ?? '#000000'), [0, 0, 0]);
            imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocatealpha($img, $tint[0], $tint[1], $tint[2], 127 - (int)round($tintOpacity / 100 * 127)));
        }
        $routeRgb = self::hexColor((string)($config['route_color'] ?? ''), $theme['route']);
        $glow = imagecolorallocatealpha($img, $routeRgb[0], $routeRgb[1], $routeRgb[2], 100);
        $solid = imagecolorallocate($img, ...$routeRgb);
        $proj = static function (float $lon, float $lat) use ($z, $left, $top): array {
            [$px, $py] = self::worldPx($lon, $lat, $z);
            return [(int)round($px - $left), (int)round($py - $top)];
        };
        $thick = max(3, (int)round($w / 180));
        $idx = 0;
        $headXY = null;
        foreach (['glow', 'solid'] as $pass) {
            $idx = 0;
            imagesetthickness($img, $pass === 'glow' ? $thick * 3 : $thick);
            foreach ($snapshot['lines'] ?? [] as $line) {
                $prev = null;
                foreach ($line as $pair) {
                    if ($head !== null && $idx > $head + 1) break;
                    $lon = (float)$pair[0];
                    $lat = (float)$pair[1];
                    $cut = false;
                    if ($head !== null && $idx > $head) {
                        $a = $line[max(0, array_search($pair, $line, true) - 1)];
                        $f = $head - ($idx - 1);
                        $lon = (float)$a[0] + ($lon - (float)$a[0]) * $f;
                        $lat = (float)$a[1] + ($lat - (float)$a[1]) * $f;
                        $cut = true;
                    }
                    $xy = $proj($lon, $lat);
                    if ($prev !== null) imageline($img, $prev[0], $prev[1], $xy[0], $xy[1], $pass === 'glow' ? $glow : $solid);
                    $prev = $xy;
                    $headXY = $xy;
                    $idx++;
                    if ($cut) break;
                }
                if ($head !== null && $idx > $head + 1) break;
            }
        }
        imagesetthickness($img, 1);
        $r = max(6, (int)round($w / 80));
        $markerRgb = $theme['marker'];
        if ($head === null) {
            foreach ($snapshot['markers'] ?? [] as $point) {
                [$x, $y] = $proj((float)$point['lon'], (float)$point['lat']);
                imagefilledellipse($img, $x, $y, $r * 2, $r * 2, imagecolorallocate($img, 255, 255, 255));
                imagefilledellipse($img, $x, $y, $r * 2 - 4, $r * 2 - 4, imagecolorallocate($img, ...$markerRgb));
            }
        } elseif ($headXY !== null) {
            imagefilledellipse($img, $headXY[0], $headXY[1], $r * 2 + 4, $r * 2 + 4, imagecolorallocate($img, 255, 255, 255));
            imagefilledellipse($img, $headXY[0], $headXY[1], $r * 2 - 2, $r * 2 - 2, $solid);
        }
        imagefilledrectangle($img, 0, $h - $panel, $w, $h, imagecolorallocatealpha($img, 0, 0, 0, 38));
        $white = imagecolorallocate($img, 245, 247, 250);
        $soft = imagecolorallocate($img, 190, 198, 210);
        $title = (string)($snapshot['title'] ?: 'Route');
        $size = max(11, $w / 34);
        self::text($img, $size, 24, $h - $panel + (int)($panel * 0.42), $white, true, mb_substr($title, 0, 48));
        $km = number_format(((float)($snapshot['distance_m'] ?? 0)) * ($head === null ? 1 : $progress) / 1000, 2, '.', '');
        $parts = [substr((string)($snapshot['started_at'] ?? $snapshot['from'] ?? ''), 0, 10)];
        $t0 = strtotime((string)($snapshot['started_at'] ?? ''));
        $t1 = strtotime((string)($snapshot['ended_at'] ?? ''));
        if ($t0 && $t1 && $t1 > $t0) {
            $sec = (int)round(($t1 - $t0) * ($head === null ? 1 : $progress));
            $parts[] = sprintf('%d:%02d:%02d', intdiv($sec, 3600), intdiv($sec % 3600, 60), $sec % 60);
        }
        $parts[] = $km . ' km';
        self::text($img, max(9, $w / 52), 24, $h - $panel + (int)($panel * 0.74), $soft, false, implode('   ·   ', array_filter($parts)));
        $attr = $provider === 'none' ? '' : (string)(StepsGeolocationMap::provider($provider)['attribution'] ?? '');
        if ($attr !== '') self::text($img, max(7, $w / 80), 24, $h - 8, $soft, false, $attr);
        return $img;
    }

    private static function panelHeight(int $h): int
    {
        return max(64, (int)round($h * 0.16));
    }

    public static function pngBytes(array $snapshot, array $config, ?callable $tick = null): string
    {
        [$w, $h] = self::size((string)$config['format'], (int)($config['edge'] ?? 800));
        $panel = self::panelHeight($h);
        $plan = self::plan($snapshot, array_merge($config, ['camera' => 'overview']), $w, $h, 1, $panel);
        self::ensureTiles((string)($config['provider'] ?? 'none'), $plan['tiles'], $tick);
        $img = self::compose($snapshot, $config, $w, $h, $plan['centers'][0][0], $plan['centers'][0][1], $plan['z'], null, 1.0, $panel);
        ob_start();
        imagepng($img);
        $bytes = ob_get_clean();
        if (!is_string($bytes) || $bytes === '') throw new StepsGeolocationRenderError('render_failed');
        return $bytes;
    }

    public static function pdfBytes(array $snapshot, array $config, ?callable $tick = null): string
    {
        [$w, $h] = self::size((string)$config['format'], (int)($config['edge'] ?? 800));
        $panel = self::panelHeight($h);
        $plan = self::plan($snapshot, array_merge($config, ['camera' => 'overview']), $w, $h, 1, $panel);
        self::ensureTiles((string)($config['provider'] ?? 'none'), $plan['tiles'], $tick);
        $img = self::compose($snapshot, $config, $w, $h, $plan['centers'][0][0], $plan['centers'][0][1], $plan['z'], null, 1.0, $panel);
        ob_start();
        imagejpeg($img, null, 92);
        $jpg = (string)ob_get_clean();
        $pageW = 595;
        $pageH = max(400, (int)round($pageW * $h / max(1, $w)));
        $content = "q $pageW 0 0 $pageH 0 0 cm /Im0 Do Q";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $pageW $pageH] /Contents 4 0 R /Resources << /XObject << /Im0 5 0 R >> >> >>",
            '<< /Length ' . strlen($content) . " >>\nstream\n$content\nendstream",
            "<< /Type /XObject /Subtype /Image /Width $w /Height $h /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpg) . " >>\nstream\n" . $jpg . "\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n$body\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
        return $pdf;
    }

    public static function videoBytes(PDO $pdo, string $jobId, array $snapshot, array $config, int $started): string
    {
        $bin = self::ffmpegBin();
        if ($bin === null) throw new StepsGeolocationRenderError('encoder_unavailable', 503);
        $fps = (int)$config['fps'];
        $frames = (int)$config['duration'] * $fps;
        [$w, $h] = self::size((string)$config['format'], (int)($config['edge'] ?? 640));
        $panel = self::panelHeight($h);
        $provider = (string)($config['provider'] ?? 'none');
        $plan = self::plan($snapshot, $config, $w, $h, $frames, $panel);
        $progress = static function (int $value) use ($pdo, $jobId): void {
            $pdo->prepare('UPDATE geo_render_jobs SET progress = :p, updated_at = now() WHERE id = :id')->execute([':p' => $value, ':id' => $jobId]);
        };
        $failed = self::ensureTiles($provider, $plan['tiles'], function (int $done, int $total) use ($progress, $pdo, $jobId, $started): void {
            if (self::cancelled($pdo, $jobId)) throw new StepsGeolocationRenderError('cancelled');
            if (time() - $started > 240) throw new StepsGeolocationRenderError('render_timeout');
            $progress(2 + (int)floor(23 * $done / max(1, $total)));
        });
        if ($provider !== 'none' && count($plan['tiles']) > 0 && $failed >= count($plan['tiles'])) throw new StepsGeolocationRenderError('tiles_unavailable', 503);
        $dir = sys_get_temp_dir() . '/steps-render-' . $jobId;
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new StepsGeolocationRenderError('render_failed');
        try {
            $total = max(1, count(self::flatPoints($snapshot)) - 1);
            for ($i = 1; $i <= $frames; $i++) {
                if ($i % 6 === 0) {
                    if (self::cancelled($pdo, $jobId)) throw new StepsGeolocationRenderError('cancelled');
                    $progress(25 + (int)floor(65 * $i / $frames));
                }
                if (time() - $started > 240) throw new StepsGeolocationRenderError('render_timeout');
                $p = $i / $frames;
                [$cx, $cy] = $plan['centers'][$i - 1];
                $img = self::compose($snapshot, $config, $w, $h, $cx, $cy, $plan['z'], $p * $total, $p, $panel);
                imagepng($img, sprintf('%s/f%04d.png', $dir, $i), 1);
            }
            $out = $dir . '/route.mp4';
            $cmd = escapeshellarg($bin)
                . ' -y -hide_banner -loglevel error -framerate ' . $fps
                . ' -i ' . escapeshellarg($dir . '/f%04d.png')
                . ' -c:v ' . self::VIDEO_CODEC . ' -profile:v ' . self::VIDEO_PROFILE
                . ' -pix_fmt ' . self::VIDEO_PIX_FMT . ' -crf 23 -movflags +faststart '
                . escapeshellarg($out);
            $log = [];
            $code = 1;
            exec($cmd . ' 2>&1', $log, $code);
            if ($code !== 0 || !is_file($out)) throw new StepsGeolocationRenderError('encoder_failed');
            $bytes = file_get_contents($out);
            if (!is_string($bytes) || strlen($bytes) < 32 || !str_contains(substr($bytes, 0, 64), 'ftyp')) {
                throw new StepsGeolocationRenderError('encoder_failed');
            }
            return $bytes;
        } finally {
            foreach (glob($dir . '/*') ?: [] as $file) @unlink($file);
            @rmdir($dir);
        }
    }

    public static function frameBytes(array $snapshot, array $config, float $progress): string
    {
        [$w, $h] = self::size((string)$config['format'], (int)($config['edge'] ?? 640));
        $panel = self::panelHeight($h);
        $plan = self::plan($snapshot, $config, $w, $h, 8, $panel);
        self::ensureTiles((string)($config['provider'] ?? 'none'), $plan['tiles']);
        $i = max(0, min(7, (int)round($progress * 8) - 1));
        [$cx, $cy] = $plan['centers'][$i];
        $img = self::compose($snapshot, $config, $w, $h, $cx, $cy, $plan['z'], $progress * max(1, count(self::flatPoints($snapshot)) - 1), $progress, $panel);
        ob_start();
        imagepng($img);
        return (string)ob_get_clean();
    }
    private static function projector(array $snapshot, int $padX, int $padY, int $width, int $height): callable
    {
        $bbox = $snapshot['bbox'] ?? ['minLon' => 0, 'minLat' => 0, 'maxLon' => 1, 'maxLat' => 1];
        $minLon = (float)$bbox['minLon'];
        $minLat = (float)$bbox['minLat'];
        $spanX = max(0.0001, (float)$bbox['maxLon'] - $minLon);
        $spanY = max(0.0001, (float)$bbox['maxLat'] - $minLat);
        return static function (float $lon, float $lat) use ($padX, $padY, $width, $height, $minLon, $minLat, $spanX, $spanY): array {
            $x = $padX + (int)round((($lon - $minLon) / $spanX) * $width);
            $y = $padY + (int)round((1 - (($lat - $minLat) / $spanY)) * $height);
            return [$x, $y];
        };
    }

    private static function stroke($img, array $lines, callable $project, int $color, int $width, float $alpha): void
    {
        imagesetthickness($img, $width);
        foreach ($lines as $line) {
            $prev = null;
            foreach ($line as $pair) {
                [$x, $y] = $project((float)$pair[0], (float)$pair[1]);
                if ($prev !== null) imageline($img, $prev[0], $prev[1], $x, $y, $color);
                $prev = [$x, $y];
            }
        }
        unset($alpha);
    }

    private static function pdfText(string $text): string
    {
        $text = self::ascii($text);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private static function ascii(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        return substr(preg_replace('/[^\x20-\x7E]/', '', $text) ?: '', 0, 64);
    }

    private static function store(PDO $pdo, string $owner, string $jobId, string $ext, string $bytes): string
    {
        $helper = dirname(__DIR__, 3) . '/shell/storage_helper.php';
        if (is_file($helper)) require_once $helper;
        $base = function_exists('appdata_app_base_dir') ? rtrim(appdata_app_base_dir('steps'), '/') . '/geo_renders' : rtrim(sys_get_temp_dir(), '/') . '/appdashboard_steps/geo_renders';
        $dir = $base . '/' . (preg_replace('/[^a-zA-Z0-9._@-]/', '_', $owner) ?: 'user');
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new StepsGeolocationRenderError('render_failed');
        $name = $jobId . '.' . $ext;
        if (file_put_contents($dir . '/' . $name, $bytes) === false) throw new StepsGeolocationRenderError('render_failed');
        $id = self::uuid();
        $types = ['png' => 'image/png', 'pdf' => 'application/pdf', 'mp4' => 'video/mp4'];
        $pdo->prepare(
            "INSERT INTO geo_artifacts(id, user_email, kind, storage_path, checksum, expires_at, size_bytes, format, filename, content_type)
             VALUES(:id, :owner, :kind, :path, :checksum, now() + interval '1 hour', :size, :format, :filename, :type)"
        )->execute([
            ':id' => $id,
            ':owner' => $owner,
            ':kind' => 'render_' . $ext,
            ':path' => $dir . '/' . $name,
            ':checksum' => hash('sha256', $bytes),
            ':size' => strlen($bytes),
            ':format' => $ext,
            ':filename' => $name,
            ':type' => $types[$ext] ?? 'application/octet-stream',
        ]);
        return $id;
    }

    public static function artifactFor(PDO $pdo, string $owner, string $jobId): ?array
    {
        $job = self::find($pdo, $owner, $jobId);
        if (!$job || empty($job['artifact_id'])) return null;
        $stmt = $pdo->prepare('SELECT * FROM geo_artifacts WHERE id = :id AND user_email = :owner AND deleted_at IS NULL AND (expires_at IS NULL OR expires_at > now())');
        $stmt->execute([':id' => $job['artifact_id'], ':owner' => $owner]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function cleanup(PDO $pdo): int
    {
        $stmt = $pdo->query("SELECT id, storage_path FROM geo_artifacts WHERE kind LIKE 'render_%' AND expires_at IS NOT NULL AND expires_at < now() AND deleted_at IS NULL");
        $n = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (is_file((string)$row['storage_path'])) @unlink((string)$row['storage_path']);
            $pdo->prepare('UPDATE geo_artifacts SET deleted_at = now() WHERE id = :id')->execute([':id' => $row['id']]);
            $n++;
        }
        return $n;
    }

    public static function kick(): void
    {
        $script = dirname(__DIR__) . '/bin/geolocation_render_run.php';
        if (!is_file($script)) return;
        $php = is_executable('/usr/local/bin/php') ? '/usr/local/bin/php' : 'php';
        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen('start /B "" ' . escapeshellarg($php) . ' ' . escapeshellarg($script), 'r'));
            return;
        }
        exec(escapeshellarg($php) . ' ' . escapeshellarg($script) . ' >/dev/null 2>&1 &');
    }
}
