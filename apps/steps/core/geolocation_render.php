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
            'title' => mb_substr(trim((string)($input['title'] ?? '')), 0, 80),
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
        $expires = gmdate('c', time() + 72 * 3600);
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
        $stmt = $pdo->prepare('SELECT id, kind, state, progress, error_code, artifact_id, created_at, expires_at FROM geo_render_jobs WHERE user_email = :owner ORDER BY created_at DESC LIMIT 20');
        $stmt->execute([':owner' => $owner]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function cancel(PDO $pdo, string $owner, string $id): void
    {
        $stmt = $pdo->prepare("UPDATE geo_render_jobs SET cancel_requested = true, updated_at = now() WHERE id = :id AND user_email = :owner AND state IN ('pending','processing')");
        $stmt->execute([':id' => $id, ':owner' => $owner]);
        if ($stmt->rowCount() === 0) throw new StepsGeolocationRenderError('render_not_found', 404);
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
            if (time() - $started > 180) throw new StepsGeolocationRenderError('render_timeout');
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

    public static function pngBytes(array $snapshot, array $config): string
    {
        if (!function_exists('imagecreatetruecolor')) throw new StepsGeolocationRenderError('renderer_unavailable', 503);
        [$w, $h] = self::size((string)$config['format'], (int)($config['edge'] ?? 800));
        $img = imagecreatetruecolor($w, $h);
        $theme = self::theme((string)($config['theme'] ?? 'dark'));
        $bg = imagecolorallocate($img, ...$theme['bg']);
        $ink = imagecolorallocate($img, ...$theme['ink']);
        $route = imagecolorallocate($img, ...$theme['route']);
        $muted = imagecolorallocate($img, ...$theme['muted']);
        $marker = imagecolorallocate($img, ...$theme['marker']);
        imagefilledrectangle($img, 0, 0, $w, $h, $bg);
        $project = self::projector($snapshot, 48, 36, $w - 96, $h - 150);
        self::stroke($img, $snapshot['lines'] ?? [], $project, $route, 4, 1.0);
        foreach ($snapshot['markers'] ?? [] as $point) {
            [$x, $y] = $project($point['lon'], $point['lat']);
            imagefilledellipse($img, $x, $y, 10, 10, $marker);
        }
        $title = (string)($snapshot['title'] ?: 'Route');
        imagestring($img, 5, 48, 28, self::ascii($title), $ink);
        $km = number_format(((float)($snapshot['distance_m'] ?? 0)) / 1000, 2, '.', '');
        $when = substr((string)($snapshot['started_at'] ?? $snapshot['from'] ?? ''), 0, 10);
        imagestring($img, 3, 48, $h - 72, self::ascii($when . '  ' . $km . ' km'), $ink);
        imagestring($img, 2, 48, $h - 40, self::ascii((string)($config['attribution'] ?? '')), $muted);
        ob_start();
        imagepng($img);
        $bytes = ob_get_clean();
        if (!is_string($bytes) || $bytes === '') throw new StepsGeolocationRenderError('render_failed');
        return $bytes;
    }

    public static function pdfBytes(array $snapshot, array $config): string
    {
        [$w, $h] = self::size((string)$config['format'], (int)($config['edge'] ?? 800));
        $pageW = 595;
        $pageH = max(400, (int)round($pageW * $h / max(1, $w)));
        $theme = self::theme((string)($config['theme'] ?? 'dark'));
        $rgb = static fn(array $c): string => sprintf('%.3f %.3f %.3f', $c[0] / 255, $c[1] / 255, $c[2] / 255);
        $project = self::projector($snapshot, 48, 36, $pageW - 96, $pageH - 120);
        $ops = $rgb($theme['bg']) . " rg 0 0 $pageW $pageH re f\n";
        $ops .= $rgb($theme['route']) . " RG 2 w 1 J 1 j\n";
        foreach ($snapshot['lines'] ?? [] as $line) {
            $first = true;
            foreach ($line as $pair) {
                [$x, $y] = $project((float)$pair[0], (float)$pair[1]);
                $py = $pageH - $y;
                $ops .= sprintf('%.2f %.2f %s ', $x, $py, $first ? 'm' : 'l');
                $first = false;
            }
            $ops .= "S\n";
        }
        $ops .= $rgb($theme['ink']) . " rg\nBT /F1 14 Tf 48 " . ($pageH - 36) . " Td (" . self::pdfText((string)($snapshot['title'] ?: 'Route')) . ") Tj ET\n";
        $km = number_format(((float)($snapshot['distance_m'] ?? 0)) / 1000, 2, '.', '');
        $when = substr((string)($snapshot['started_at'] ?? ''), 0, 10);
        $ops .= "BT /F1 10 Tf 48 48 Td (" . self::pdfText($when . '  ' . $km . ' km') . ") Tj ET\n";
        $ops .= "BT /F1 8 Tf 48 28 Td (" . self::pdfText((string)($config['attribution'] ?? '')) . ") Tj ET\n";
        $objects = [
            "<< /Type /Catalog /Pages 2 0 R >>",
            "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $pageW $pageH] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>",
            "<< /Length " . strlen($ops) . " >>\nstream\n$ops\nendstream",
            "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
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
        $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
        return $pdf;
    }

    public static function videoBytes(PDO $pdo, string $jobId, array $snapshot, array $config, int $started): string
    {
        $bin = self::ffmpegBin();
        if ($bin === null) throw new StepsGeolocationRenderError('encoder_unavailable', 503);
        $fps = (int)$config['fps'];
        $frames = (int)$config['duration'] * $fps;
        $dir = sys_get_temp_dir() . '/steps-render-' . $jobId;
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new StepsGeolocationRenderError('render_failed');
        try {
            for ($i = 1; $i <= $frames; $i++) {
                if ($i % 8 === 0) {
                    if (self::cancelled($pdo, $jobId)) throw new StepsGeolocationRenderError('cancelled');
                    $pdo->prepare('UPDATE geo_render_jobs SET progress = :progress, updated_at = now() WHERE id = :id')
                        ->execute([':progress' => (int)floor(90 * $i / $frames), ':id' => $jobId]);
                }
                if (time() - $started > 150) throw new StepsGeolocationRenderError('render_timeout');
                $frame = self::frameBytes($snapshot, $config, $i / $frames);
                file_put_contents(sprintf('%s/f%04d.png', $dir, $i), $frame);
            }
            $out = $dir . '/route.mp4';
            $cmd = escapeshellarg($bin)
                . ' -y -hide_banner -loglevel error -framerate ' . $fps
                . ' -i ' . escapeshellarg($dir . '/f%04d.png')
                . ' -c:v ' . self::VIDEO_CODEC . ' -profile:v ' . self::VIDEO_PROFILE
                . ' -pix_fmt ' . self::VIDEO_PIX_FMT . ' -movflags +faststart '
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
        if (!function_exists('imagecreatetruecolor')) throw new StepsGeolocationRenderError('renderer_unavailable', 503);
        [$w, $h] = self::size((string)$config['format'], (int)($config['edge'] ?? 640));
        $img = imagecreatetruecolor($w, $h);
        $theme = self::theme((string)($config['theme'] ?? 'dark'));
        $bg = imagecolorallocate($img, ...$theme['bg']);
        $ink = imagecolorallocate($img, ...$theme['ink']);
        $route = imagecolorallocate($img, ...$theme['route']);
        $muted = imagecolorallocate($img, ...$theme['muted']);
        imagefilledrectangle($img, 0, 0, $w, $h, $bg);
        $flat = [];
        foreach ($snapshot['lines'] ?? [] as $line) foreach ($line as $pair) $flat[] = $pair;
        $keep = max(2, (int)ceil(count($flat) * max(0.02, min(1.0, $progress))));
        $shown = array_slice($flat, 0, $keep);
        $project = self::projector($snapshot, 36, 28, $w - 72, $h - 110);
        if (count($shown) >= 2) self::stroke($img, [$shown], $project, $route, 4, 1.0);
        $km = number_format(((float)($snapshot['distance_m'] ?? 0)) * $progress / 1000, 2, '.', '');
        $title = (string)($snapshot['title'] ?: 'Route');
        imagestring($img, 5, 36, 16, self::ascii($title), $ink);
        imagestring($img, 3, 36, $h - 48, self::ascii($km . ' km'), $ink);
        imagestring($img, 2, 36, $h - 24, self::ascii((string)($config['attribution'] ?? '')), $muted);
        ob_start();
        imagepng($img);
        $bytes = ob_get_clean();
        return is_string($bytes) ? $bytes : '';
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
        $helper = dirname(__DIR__, 3) . '/storage_helper.php';
        if (is_file($helper)) require_once $helper;
        $base = function_exists('appdata_app_base_dir') ? rtrim(appdata_app_base_dir('steps'), '/') . '/geo_renders' : dirname(__DIR__) . '/upload_sessions/geo_renders';
        $dir = $base . '/' . (preg_replace('/[^a-zA-Z0-9._@-]/', '_', $owner) ?: 'user');
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new StepsGeolocationRenderError('render_failed');
        $name = $jobId . '.' . $ext;
        if (file_put_contents($dir . '/' . $name, $bytes) === false) throw new StepsGeolocationRenderError('render_failed');
        $id = self::uuid();
        $types = ['png' => 'image/png', 'pdf' => 'application/pdf', 'mp4' => 'video/mp4'];
        $pdo->prepare(
            "INSERT INTO geo_artifacts(id, user_email, kind, storage_path, checksum, expires_at, size_bytes, format, filename, content_type)
             VALUES(:id, :owner, :kind, :path, :checksum, now() + interval '72 hours', :size, :format, :filename, :type)"
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
        $php = PHP_BINARY ?: 'php';
        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen('start /B "" ' . escapeshellarg($php) . ' ' . escapeshellarg($script), 'r'));
            return;
        }
        exec(escapeshellarg($php) . ' ' . escapeshellarg($script) . ' >/dev/null 2>&1 &');
    }
}
