<?php
require_once dirname(__DIR__) . '/core/geolocation_stops.php';
require_once dirname(__DIR__) . '/core/geolocation_osm.php';
require_once dirname(__DIR__) . '/core/geolocation_place_ranking.php';

function geo_pd_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$lat = 50.0647;
$lon = 19.9450;
$base = 1_800_000_000;
$t = StepsGeolocationStops::thresholds();
$make = static function (int $offset, float $dLat, float $dLon, ?float $acc = 8.0) use ($base, $lat, $lon): array {
    return ['id' => 'p' . $offset, 'source_at' => gmdate('c', $base + $offset), 'latitude' => $lat + $dLat, 'longitude' => $lon + $dLon, 'accuracy_m' => $acc, 'quality_state' => 'accepted'];
};
$dwell = static function (int $count, int $step, ?float $acc = 8.0) use ($make): array {
    $pts = [];
    for ($i = 0; $i < $count; $i++) $pts[] = $make($i * $step, ($i % 3) * 0.00001, ($i % 2) * 0.00001, $acc);
    return $pts;
};
$old = $base + 100000;

geo_pd_assert($t['radius_m'] === 50.0 && $t['min_duration_s'] === 300.0 && $t['min_points'] === 3.0 && $t['preferred_accuracy_m'] === 50.0, 'default thresholds');
geo_pd_assert(StepsGeolocationPlaceConfig::osm()['search_radius_m'] === 100.0, 'default osm radius');

$short = StepsGeolocationStops::detect($dwell(6, 30), $t, $old);
geo_pd_assert($short['stops'] === [], 'a 2.5 minute stay must not be a stop');
$fewPoints = StepsGeolocationStops::detect([$make(0, 0, 0), $make(400, 0, 0)], $t, $old);
geo_pd_assert($fewPoints['stops'] === [], 'two observations must not be a stop');
$long = StepsGeolocationStops::detect($dwell(20, 120), $t, $old);
geo_pd_assert(count($long['stops']) === 1 && $long['stops'][0]['duration_s'] >= 2000, 'long stop');
geo_pd_assert($long['stops'][0]['provisional'] === false, 'old stop must be confirmed');
$fresh = StepsGeolocationStops::detect($dwell(10, 60), $t, $base + 600);
geo_pd_assert(count($fresh['stops']) === 1 && $fresh['stops'][0]['provisional'] === true, 'recent stop must be provisional');

$noisy = $dwell(15, 60);
$noisy[5] = $make(300, 0.002, 0.002, 180.0);
$noisy[6] = $make(360, 0.0, 0.0, 400.0);
$det = StepsGeolocationStops::detect($noisy, $t, $old);
geo_pd_assert(count($det['stops']) === 1, 'inaccurate outliers must not split or break the stop');
geo_pd_assert(abs($det['stops'][0]['latitude'] - $lat) < 0.0001, 'inaccurate fixes must not shift the centre');

$gap = array_merge($dwell(6, 60), array_map(static fn(array $p): array => array_merge($p, ['id' => 'g' . $p['id'], 'source_at' => gmdate('c', strtotime($p['source_at']) + 1100)]), $dwell(6, 60)));
$gapDet = StepsGeolocationStops::detect($gap, $t, $old);
geo_pd_assert(count($gapDet['stops']) === 1, 'a short signal loss inside the same place keeps one stop, got ' . count($gapDet['stops']));

$twoPlaces = array_merge($dwell(8, 60), array_map(static fn(array $p): array => array_merge($p, ['id' => 'b' . $p['id'], 'source_at' => gmdate('c', strtotime($p['source_at']) + 5000), 'latitude' => $p['latitude'] + 0.0009]), $dwell(8, 60)));
geo_pd_assert(count(StepsGeolocationStops::detect($twoPlaces, $t, $old)['stops']) === 2, 'separate stops must stay separate');

[$cLat, $cLon] = StepsGeolocationStops::center([[50.0, 20.0, 5.0], [50.0001, 20.0001, 8.0], [50.01, 20.01, 150.0]], 50.0);
geo_pd_assert(abs($cLat - 50.00005) < 0.0001, 'weighted centre ignores inaccurate fix');

$square = static function (float $cLat, float $cLon, float $half): array {
    $dLat = $half / 111320.0;
    $dLon = $half / (111320.0 * cos(deg2rad($cLat)));
    return ['type' => 'Polygon', 'coordinates' => [[[$cLon - $dLon, $cLat - $dLat], [$cLon + $dLon, $cLat - $dLat], [$cLon + $dLon, $cLat + $dLat], [$cLon - $dLon, $cLat + $dLat], [$cLon - $dLon, $cLat - $dLat]]]];
};
$el = static function (string $type, int $id, string $name, array $tags, float $eLat, float $eLon, ?array $geometry = null): array {
    [$category, $sub] = StepsGeolocationOsm::categorize($tags);
    return ['osm_type' => $type, 'osm_id' => $id, 'name' => $name, 'category' => $category, 'subcategory' => $sub, 'address' => '', 'lat' => $eLat, 'lon' => $eLon, 'geometry' => $geometry, 'tags' => $tags, 'fetched_at' => ''];
};
$obs = [];
for ($i = 0; $i < 12; $i++) $obs[] = ['lat' => $lat + ($i % 3) * 0.000008, 'lon' => $lon + ($i % 2) * 0.000008, 'accuracy_m' => 10.0];
$stop = ['lat' => $lat, 'lon' => $lon, 'radius_m' => 50.0, 'duration_s' => 2400];

$office = $el('way', 1, 'Biuro Alfa', ['building' => 'office', 'office' => 'company', 'name' => 'Biuro Alfa'], $lat, $lon, $square($lat, $lon, 20));
$cafe = $el('node', 2, 'Kawiarnia Beta', ['amenity' => 'cafe', 'name' => 'Kawiarnia Beta'], $lat + 0.0006, $lon + 0.0006);
$uni = $el('way', 3, 'Uczelnia Gamma', ['amenity' => 'university', 'name' => 'Uczelnia Gamma'], $lat, $lon, $square($lat, $lon, 20));
$neighbour = $el('way', 4, 'Dom Delta', ['building' => 'residential', 'name' => 'Dom Delta'], $lat + 0.0004, $lon, $square($lat + 0.0004, $lon, 15));

$near = StepsGeolocationPlaceRanking::rank($stop, $obs, [$neighbour, $office, $cafe], 'prefer_not_to_say');
geo_pd_assert($near['selected']['element']['osm_id'] === 1, 'exact building containing the observations must win over nearer-by-name neighbours');
geo_pd_assert($near['status'] === 'confirmed', 'exact building should be confirmed, got ' . $near['status'] . ' ' . $near['confidence']);
geo_pd_assert(count($near['candidates']) >= 2 && $near['candidates'][0]['rank'] === 1, 'alternatives are preserved');

$both = [$office, $uni];
$asStudent = StepsGeolocationPlaceRanking::rank($stop, $obs, $both, 'student');
$asOffice = StepsGeolocationPlaceRanking::rank($stop, $obs, $both, 'office_worker');
$neutral = StepsGeolocationPlaceRanking::rank($stop, $obs, $both, 'prefer_not');
geo_pd_assert($asStudent['selected']['element']['osm_id'] === 3, 'student profile prefers the university');
geo_pd_assert($asOffice['selected']['element']['osm_id'] === 1, 'office profile prefers the office');
geo_pd_assert(StepsGeolocationPlaceProfiles::normalize('nonsense') === 'prefer_not_to_say' && StepsGeolocationPlaceProfiles::relevance('nonsense', ['amenity' => 'university']) === 0.5, 'unknown profile is neutral');
$retiredUni = StepsGeolocationPlaceRanking::rank($stop, $obs, [$uni], 'retired');
geo_pd_assert($retiredUni['selected'] !== null && $retiredUni['selected']['element']['osm_id'] === 3, 'a profile must never exclude a category');
geo_pd_assert(count(array_unique(array_map(static fn(string $p): string => implode(',', StepsGeolocationPlaceProfiles::patterns($p)), ['working_student']))) === 1 && in_array('amenity=university', StepsGeolocationPlaceProfiles::patterns('working_student'), true) && in_array('office=*', StepsGeolocationPlaceProfiles::patterns('working_student'), true), 'working_studying combines student and office');
geo_pd_assert(count(StepsGeolocationPlaceProfiles::ids()) === 23, 'all 23 profiles exist');

$mall = $el('way', 10, 'Galeria', ['building' => 'retail'], $lat, $lon, $square($lat, $lon, 40));
$shopA = $el('node', 11, 'Sklep A', ['shop' => 'clothes', 'name' => 'Sklep A'], $lat + 0.00005, $lon, null);
$shopB = $el('node', 12, 'Sklep B', ['shop' => 'shoes', 'name' => 'Sklep B'], $lat - 0.00005, $lon, null);
$multi = StepsGeolocationPlaceRanking::rank($stop, $obs, [$mall, $shopA, $shopB], 'prefer_not_to_say');
geo_pd_assert($multi['ambiguous'] === true || $multi['confidence'] <= StepsGeolocationPlaceConfig::thresholds()['ambiguous_cap'], 'several businesses in one building must not be claimed with certainty');
geo_pd_assert($multi['status'] !== 'confirmed', 'ambiguous building is not confirmed');
geo_pd_assert(count($multi['candidates']) === 3, 'all businesses stay as candidates');

$unnamed = $el('way', 20, '', ['building' => 'yes'], $lat, $lon, $square($lat, $lon, 15));
$noName = StepsGeolocationPlaceRanking::rank($stop, $obs, [$unnamed], 'prefer_not_to_say');
geo_pd_assert($noName['selected'] !== null && $noName['selected']['element']['name'] === '' && $noName['selected']['match_type'] === 'building', 'unnamed building is still a valid exact building candidate');

$far = $el('node', 30, 'Daleko', ['shop' => 'bakery', 'name' => 'Daleko'], $lat + 0.0013, $lon);
$none = StepsGeolocationPlaceRanking::rank($stop, $obs, [$far], 'prefer_not_to_say');
geo_pd_assert($none['status'] === 'unidentified' && $none['selected'] === null, 'distant object leaves the place unidentified');
$empty = StepsGeolocationPlaceRanking::rank($stop, $obs, [], 'student');
geo_pd_assert($empty['status'] === 'unidentified' && $empty['candidates'] === [], 'no OSM data yields a neutral unidentified result');

$blocked = StepsGeolocationPlaceRanking::rank($stop, $obs, [$office, $uni], 'prefer_not_to_say', null, null, ['way:1' => true, 'way:3' => true]);
geo_pd_assert($blocked['selected'] === null, 'blocked objects are never assigned');

$park = $el('way', 40, 'Park Jordana', ['leisure' => 'park', 'name' => 'Park Jordana'], $lat, $lon, $square($lat, $lon, 150));
$parkRank = StepsGeolocationPlaceRanking::rank($stop, $obs, [$park], 'retired');
geo_pd_assert($parkRank['selected']['match_type'] === 'park', 'parks are classified separately');
$station = $el('node', 41, 'Kraków Główny', ['railway' => 'station', 'name' => 'Kraków Główny'], $lat, $lon);
geo_pd_assert(StepsGeolocationPlaceRanking::rank($stop, $obs, [$station], 'frequent_traveler')['selected']['match_type'] === 'transport', 'transport classified');

$norm = StepsGeolocationOsm::normalize([
    ['type' => 'node', 'id' => 5, 'lat' => $lat, 'lon' => $lon, 'tags' => ['amenity' => 'cafe', 'name' => 'X', 'addr:street' => 'Floriańska', 'addr:housenumber' => '3', 'addr:city' => 'Kraków']],
    ['type' => 'way', 'id' => 6, 'bounds' => ['minlat' => $lat - 0.0001, 'maxlat' => $lat + 0.0001, 'minlon' => $lon - 0.0001, 'maxlon' => $lon + 0.0001], 'geometry' => [['lat' => $lat, 'lon' => $lon], ['lat' => $lat + 0.0001, 'lon' => $lon], ['lat' => $lat + 0.0001, 'lon' => $lon + 0.0001], ['lat' => $lat, 'lon' => $lon]], 'tags' => ['building' => 'yes']],
    ['type' => 'node', 'id' => 7, 'lat' => $lat, 'lon' => $lon],
    ['type' => 'relation', 'id' => 8, 'center' => ['lat' => $lat, 'lon' => $lon], 'tags' => ['tourism' => 'museum']],
], $lat, $lon);
geo_pd_assert(count($norm) === 3, 'tagless elements dropped');
$byId = array_column($norm, null, 'osm_id');
geo_pd_assert($byId[5]['address'] === 'Floriańska 3, Kraków' && $byId[6]['geometry']['type'] === 'Polygon' && $byId[8]['category'] === 'tourism' && $byId[6]['name'] === '', 'normalization');
$q = StepsGeolocationOsm::buildQuery($lat, $lon, 100, 20);
geo_pd_assert(str_contains($q, '[out:json][timeout:20]') && str_contains($q, 'nwr(around:100,') && !str_contains($q, 'profile'), 'overpass query shape');
echo "ok\n";
