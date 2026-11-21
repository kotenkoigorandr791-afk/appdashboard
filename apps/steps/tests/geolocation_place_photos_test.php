<?php
require_once dirname(__DIR__) . '/core/geolocation_place_photos.php';

function geo_photos_assert(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "ok: $msg\n";
}

geo_photos_assert(StepsGeolocationPlacePhotos::relativePath('NODE', 42) === 'node/42.webp', 'relative path');
$src = tempnam(sys_get_temp_dir(), 'geo_ph_');
$jpg = $src . '.jpg';
exec('convert -size 400x300 gradient:blue-white -quality 90 ' . escapeshellarg($jpg));
geo_photos_assert(is_file($jpg) && filesize($jpg) > 100, 'source image created');
$ref = new ReflectionClass(StepsGeolocationPlacePhotos::class);
$m = $ref->getMethod('convertToWebp');
$out = $m->invoke(null, (string)file_get_contents($jpg));
geo_photos_assert(is_array($out), 'webp conversion');
geo_photos_assert(($out['width'] ?? 0) <= 256 && ($out['height'] ?? 0) <= 256, 'max edge');
geo_photos_assert(strlen((string)$out['bytes']) > 50 && strlen((string)$out['bytes']) < 50000, 'file size band');
geo_photos_assert(substr((string)$out['bytes'], 0, 4) === 'RIFF', 'webp container');
@unlink($jpg);
@unlink($src);
echo "geolocation_place_photos_test: pass\n";
