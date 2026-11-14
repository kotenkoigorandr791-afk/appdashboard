<?php
/**
 * Unit test for the tile cache (no network access).
 *   php apps/steps/tests/geolocation_tiles_test.php
 */
require_once dirname(__DIR__) . '/core/geolocation_tiles.php';

function geo_tiles_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$tmp = sys_get_temp_dir() . '/steps_tiles_test_' . bin2hex(random_bytes(4));
putenv('GEO_TILE_CACHE_DIR=' . $tmp);

try {
    geo_tiles_assert(StepsGeolocationTiles::provider('esri_dark') !== null, 'known provider rejected');
    geo_tiles_assert(StepsGeolocationTiles::provider('unknown') === null, 'unknown provider accepted');

    geo_tiles_assert(StepsGeolocationTiles::tile('12', '2134', '1377') === ['z' => 12, 'x' => 2134, 'y' => 1377], 'valid tile rejected');
    geo_tiles_assert(StepsGeolocationTiles::tile('20', '1', '1') === null, 'zoom above limit accepted');
    geo_tiles_assert(StepsGeolocationTiles::tile('2', '4', '1') === null, 'tile outside world accepted');
    geo_tiles_assert(StepsGeolocationTiles::tile('x', '1', '1') === null, 'non numeric zoom accepted');
    geo_tiles_assert(StepsGeolocationTiles::tile('1', '-1', '1') === null, 'negative coordinate accepted');

    $esri = StepsGeolocationTiles::upstreamUrl('esri_dark', 3, 4, 5);
    geo_tiles_assert($esri !== null && str_contains($esri, '/tile/3/5/4'), 'esri url order wrong: ' . var_export($esri, true));
    $osm = StepsGeolocationTiles::upstreamUrl('osm', 3, 4, 5);
    geo_tiles_assert($osm === 'https://tile.openstreetmap.org/3/4/5.png', 'osm url wrong: ' . var_export($osm, true));
    $carto = StepsGeolocationTiles::upstreamUrl('carto_dark', 3, 4, 5);
    geo_tiles_assert($carto !== null && !str_contains($carto, '{') && str_contains($carto, 'cdn'), 'carto template not resolved: ' . var_export($carto, true));
    geo_tiles_assert(StepsGeolocationTiles::upstreamUrl('unknown', 3, 4, 5) === null, 'unknown provider produced url');

    geo_tiles_assert(StepsGeolocationTiles::mimeFromBytes("\x89PNG\r\n\x1a\n") === 'image/png', 'png not detected');
    geo_tiles_assert(StepsGeolocationTiles::mimeFromBytes("\xFF\xD8\xFF\xE0") === 'image/jpeg', 'jpeg not detected');
    geo_tiles_assert(StepsGeolocationTiles::mimeFromBytes('hello') === 'application/octet-stream', 'garbage detected as image');

    $path = StepsGeolocationTiles::path('esri_dark', 3, 4, 5);
    geo_tiles_assert(str_starts_with($path, $tmp . '/esri_dark/3/4/'), 'cache path outside cache dir: ' . $path);
    geo_tiles_assert(StepsGeolocationTiles::read($path) === null, 'missing tile returned data');

    $png = "\x89PNG\r\n\x1a\n" . random_bytes(32);
    geo_tiles_assert(StepsGeolocationTiles::store($path, $png, 'image/png') === true, 'tile not stored');
    $read = StepsGeolocationTiles::read($path);
    geo_tiles_assert($read !== null && $read['body'] === $png && $read['mime'] === 'image/png', 'stored tile not readable');

    // A fresh cache entry is served without contacting the network.
    $hot = StepsGeolocationTiles::get('esri_dark', 3, 4, 5);
    geo_tiles_assert($hot !== null && $hot['cached'] === true && $hot['stale'] === false, 'fresh cache entry bypassed');

    putenv('GEO_TILE_CACHE_TTL=60');
    $expiredPath = StepsGeolocationTiles::path('osm', 2, 1, 1);
    StepsGeolocationTiles::store($expiredPath, $png, 'image/png');
    @file_put_contents($expiredPath . '.json', json_encode(['mime' => 'image/png', 'fetched_at' => time() - 7200]), LOCK_EX);
    $expired = StepsGeolocationTiles::read($expiredPath);
    geo_tiles_assert($expired !== null && (time() - $expired['fetched_at']) > 60, 'ttl fixture not expired');
    putenv('GEO_TILE_CACHE_TTL');

    echo "ok\n";
} finally {
    $rm = static function (string $dir) use (&$rm): void {
        foreach (glob($dir . '/*') ?: [] as $entry) {
            if (is_dir($entry)) $rm($entry); else @unlink($entry);
        }
        @rmdir($dir);
    };
    if (is_dir($tmp)) $rm($tmp);
    putenv('GEO_TILE_CACHE_DIR');
}
