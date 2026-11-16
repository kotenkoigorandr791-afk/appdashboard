<?php
/**
 * Import a versioned country/region/city boundary dataset (GeoJSON).
 *
 *   php apps/steps/bin/geolocation_boundaries_import.php \
 *       --file=/path/ne_10m_admin_0_countries.geojson \
 *       --kind=country --version=natural-earth-5.1.2 \
 *       --code-property=ISO_A2 --name-property=NAME
 *
 *   php apps/steps/bin/geolocation_boundaries_import.php \
 *       --file=/path/cities.geojson --kind=city --version=cities-2025-01 \
 *       --code-property=id --name-property=name --parent-property=country_code
 *
 * There is deliberately no live geocoding service: statistics can only name a
 * country or city after an explicitly versioned dataset has been imported.
 * Re-importing the same version replaces its features atomically.
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_statistics.php';

$options = getopt('', ['file:', 'kind:', 'version:', 'code-property::', 'name-property::', 'parent-property::']);
$file = (string)($options['file'] ?? '');
$kind = strtolower(trim((string)($options['kind'] ?? 'country')));
$version = trim((string)($options['version'] ?? ''));
$codeProperty = (string)($options['code-property'] ?? 'ISO_A2');
$nameProperty = (string)($options['name-property'] ?? 'NAME');
$parentProperty = (string)($options['parent-property'] ?? '');
if ($file === '' || !is_file($file)) { fwrite(STDERR, "boundaries: file not found\n"); exit(2); }
if (!in_array($kind, ['country', 'region', 'city'], true)) { fwrite(STDERR, "boundaries: kind must be country, region or city\n"); exit(2); }
if ($version === '' || !preg_match('/^[A-Za-z0-9._:-]{1,80}$/', $version)) { fwrite(STDERR, "boundaries: invalid version\n"); exit(2); }

$raw = file_get_contents($file);
$data = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($data)) { fwrite(STDERR, "boundaries: invalid GeoJSON\n"); exit(2); }
$features = ($data['type'] ?? '') === 'FeatureCollection' ? ($data['features'] ?? []) : (($data['type'] ?? '') === 'Feature' ? [$data] : []);
if (!is_array($features) || $features === []) { fwrite(STDERR, "boundaries: no features\n"); exit(2); }

$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();
$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO geo_boundary_datasets(version, kind, source, feature_count, imported_at) VALUES(:version, :kind, :source, :count, now())
                   ON CONFLICT (version, kind) DO UPDATE SET source = EXCLUDED.source, feature_count = EXCLUDED.feature_count, imported_at = now()")
        ->execute([':version' => $version, ':kind' => $kind, ':source' => basename($file), ':count' => count($features)]);
    $pdo->prepare('DELETE FROM geo_boundary_areas WHERE dataset_version = :version AND kind = :kind')->execute([':version' => $version, ':kind' => $kind]);
    $insert = $pdo->prepare(
        "INSERT INTO geo_boundary_areas(dataset_version, kind, code, name, parent_code, geometry)
         VALUES(:version, :kind, :code, :name, :parent, ST_SetSRID(ST_GeomFromGeoJSON(:geometry), 4326))
         ON CONFLICT (dataset_version, kind, code) DO UPDATE SET name = EXCLUDED.name, parent_code = EXCLUDED.parent_code, geometry = EXCLUDED.geometry"
    );
    $imported = 0;
    foreach ($features as $feature) {
        $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
        $code = trim((string)($props[$codeProperty] ?? $props['code'] ?? $props['id'] ?? ''));
        $name = trim((string)($props[$nameProperty] ?? $props['name'] ?? ''));
        $parent = $parentProperty !== '' ? trim((string)($props[$parentProperty] ?? '')) : '';
        $geometry = $feature['geometry'] ?? null;
        if ($code === '' || $name === '' || !is_array($geometry)) continue;
        if (strlen($code) > 40) $code = substr($code, 0, 40);
        try {
            $insert->execute([':version' => $version, ':kind' => $kind, ':code' => $code, ':name' => $name, ':parent' => $parent, ':geometry' => json_encode($geometry)]);
            $imported++;
        } catch (Throwable $e) {
            error_log('boundary feature skipped: ' . $e->getMessage());
        }
    }
    $pdo->prepare("UPDATE geo_boundary_datasets SET feature_count = :count WHERE version = :version AND kind = :kind")
        ->execute([':count' => $imported, ':version' => $version, ':kind' => $kind]);
    $pdo->commit();
    // Boundary data changes every owner's country/city results.
    $pdo->exec('DELETE FROM geo_statistics_cache');
    $owners = $pdo->query("SELECT DISTINCT user_email FROM geo_location_points")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($owners as $owner) StepsGeolocationStatistics::enqueue($pdo, (string)$owner);
    fwrite(STDOUT, "boundaries: imported {$imported} {$kind} feature(s) as {$version}\n");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'boundaries: ' . $e->getMessage() . "\n");
    exit(1);
}
