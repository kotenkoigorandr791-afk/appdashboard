<?php
require_once __DIR__ . '/../core/geolocation_places.php';

function geo_places_assert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$valid = [
    'name' => 'Home',
    'tag' => 'Home',
    'latitude' => 50.4501,
    'longitude' => 30.5234,
    'radius_m' => 120,
    'color' => '#22aa66',
    'notes' => 'Front door',
    'timezone' => 'Europe/Kyiv',
    'is_active' => true,
];
$result = StepsGeolocationPlaces::validate($valid);
geo_places_assert($result['place'] !== null, 'valid place rejected: ' . implode(',', $result['errors']));
geo_places_assert($result['place']['tag'] === 'home', 'tag not normalized');
geo_places_assert(StepsGeolocationPlaces::isSingletonTag('HOME'), 'home must be a singleton');
geo_places_assert(!StepsGeolocationPlaces::isSingletonTag('gym'), 'gym must allow many');
geo_places_assert(StepsGeolocationPlaces::isBuiltinTag('favorite'), 'favorite tag missing');
geo_places_assert(StepsGeolocationPlaces::normalizeTag('  My Gym!! '), 'invalid tag normalization');

foreach ([
    ['radius_m' => 1, 'error' => 'invalid_radius'],
    ['radius_m' => 99999, 'error' => 'invalid_radius'],
    ['latitude' => 95, 'error' => 'invalid_coordinates'],
    ['tag' => '', 'error' => 'invalid_tag'],
    ['timezone' => 'Mars/Olympus', 'error' => 'invalid_timezone'],
    ['color' => 'green', 'error' => 'invalid_color'],
    ['name' => '', 'error' => 'invalid_name'],
] as $case) {
    $error = $case['error'];
    unset($case['error']);
    $checked = StepsGeolocationPlaces::validate(array_merge($valid, $case));
    geo_places_assert($checked['place'] === null && in_array($error, $checked['errors'], true), 'case not rejected: ' . $error);
}

// Custom user tags are accepted, not limited to the built-ins.
$custom = StepsGeolocationPlaces::validate(array_merge($valid, ['name' => 'Gym', 'tag' => 'gym', 'radius_m' => 60]));
geo_places_assert($custom['place'] !== null, 'custom tag rejected');
$customTag = StepsGeolocationPlaces::validate(array_merge($valid, ['name' => 'Parents', 'tag' => 'parents house']));
geo_places_assert($customTag['place'] !== null && $customTag['place']['tag'] === 'parentshouse', 'free-form tag not normalized');

// Partial updates keep untouched fields out so the API can merge them.
$partial = StepsGeolocationPlaces::validate(['name' => 'Office'], true);
geo_places_assert($partial['place'] !== null && !isset($partial['place']['latitude']), 'partial update touched other fields');

echo "ok\n";
