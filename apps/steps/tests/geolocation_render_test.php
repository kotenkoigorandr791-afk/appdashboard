<?php
require_once dirname(__DIR__) . '/core/geolocation_render.php';
$snapshot = [
    'title' => 'November walk',
    'from' => '2026-11-12T00:00:00Z',
    'to' => '2026-11-13T00:00:00Z',
    'started_at' => '2026-11-12T08:00:00Z',
    'ended_at' => '2026-11-12T09:10:00Z',
    'distance_m' => 4200,
    'bbox' => ['minLon' => 27.5, 'minLat' => 53.8, 'maxLon' => 27.7, 'maxLat' => 53.95],
    'lines' => [[[27.55, 53.90], [27.57, 53.91], [27.60, 53.88], [27.62, 53.86]]],
    'markers' => [['lon' => 27.57, 'lat' => 53.91, 'name' => 'Stop']],
];
$config = ['format' => 'square', 'theme' => 'dark', 'attribution' => 'OSM', 'edge' => 320, 'file' => 'png'];
$a = StepsGeolocationRender::pngBytes($snapshot, $config);
$b = StepsGeolocationRender::pngBytes($snapshot, $config);
if ($a !== $b || !str_starts_with($a, "\x89PNG")) {
    fwrite(STDERR, "poster png is not stable\n");
    exit(1);
}
$pdf = StepsGeolocationRender::pdfBytes($snapshot, $config + ['file' => 'pdf']);
if (!str_starts_with($pdf, '%PDF') || $pdf !== StepsGeolocationRender::pdfBytes($snapshot, $config + ['file' => 'pdf'])) {
    fwrite(STDERR, "poster pdf is not stable\n");
    exit(1);
}
$frame = StepsGeolocationRender::frameBytes($snapshot, $config + ['edge' => 320], 0.5);
if (!str_starts_with($frame, "\x89PNG") || strlen($frame) < 100) {
    fwrite(STDERR, "video frame missing\n");
    exit(1);
}
$encoder = StepsGeolocationRender::encoderStatus();
if ($encoder['available']) {
    $dir = sys_get_temp_dir() . '/steps-render-golden';
    @mkdir($dir, 0700, true);
    for ($i = 1; $i <= 8; $i++) file_put_contents(sprintf('%s/f%04d.png', $dir, $i), StepsGeolocationRender::frameBytes($snapshot, $config, $i / 8));
    $out = $dir . '/out.mp4';
    $cmd = escapeshellarg(StepsGeolocationRender::ffmpegBin()) . ' -y -hide_banner -loglevel error -framerate 8 -i ' . escapeshellarg($dir . '/f%04d.png')
        . ' -c:v libx264 -profile:v high -pix_fmt yuv420p -movflags +faststart ' . escapeshellarg($out);
    exec($cmd, $log, $code);
    $bytes = is_file($out) ? file_get_contents($out) : '';
    foreach (glob($dir . '/*') ?: [] as $file) @unlink($file);
    @rmdir($dir);
    if ($code !== 0 || !is_string($bytes) || !str_contains(substr($bytes, 0, 64), 'ftyp')) {
        fwrite(STDERR, "mp4 encode failed\n");
        exit(1);
    }
}
echo "ok\n";
