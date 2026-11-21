<?php
final class StepsGeolocationPlaceProfiles
{
    public const NEUTRAL = ['other', 'prefer_not_to_say'];
    public const DEFAULT_PROFILE = 'prefer_not_to_say';

    public const PROFILES = [
        'student' => ['amenity=university', 'amenity=college', 'amenity=library', 'amenity=school', 'leisure=sports_centre', 'amenity=cafeteria'],
        'school_student' => ['amenity=school', 'amenity=library', 'leisure=playground', 'leisure=sports_centre'],
        'office_worker' => ['office=*', 'building=office', 'landuse=commercial', 'amenity=restaurant', 'amenity=cafe'],
        'remote_worker' => ['amenity=cafe', 'amenity=library', 'office=coworking', 'amenity=coworking_space'],
        'freelancer' => ['amenity=cafe', 'amenity=library', 'office=*', 'amenity=restaurant'],
        'entrepreneur' => ['office=*', 'shop=*', 'landuse=commercial', 'amenity=bank'],
        'self_employed' => ['office=*', 'shop=*', 'craft=*', 'building=commercial'],
        'industrial_worker' => ['landuse=industrial', 'building=industrial', 'industrial=*', 'man_made=works', 'man_made=warehouse'],
        'driver_courier' => ['amenity=parking', 'amenity=fuel', 'amenity=restaurant', 'amenity=fast_food', 'amenity=loading_dock'],
        'taxi_driver' => ['amenity=parking', 'amenity=fuel', 'amenity=taxi', 'amenity=car_wash'],
        'retail_worker' => ['shop=*', 'building=retail', 'landuse=retail', 'amenity=marketplace'],
        'teacher' => ['amenity=school', 'amenity=university', 'amenity=college', 'amenity=library'],
        'healthcare_worker' => ['amenity=hospital', 'amenity=clinic', 'amenity=doctors', 'healthcare=*'],
        'military' => ['landuse=military', 'military=*', 'building=military'],
        'public_servant' => ['office=government', 'amenity=townhall', 'amenity=courthouse'],
        'retired' => ['amenity=park', 'leisure=garden', 'amenity=pharmacy', 'amenity=clinic', 'shop=supermarket', 'amenity=library'],
        'unemployed' => ['amenity=library', 'amenity=park', 'amenity=community_centre', 'amenity=jobcentre', 'amenity=cafe'],
        'homemaker' => ['building=residential', 'building=apartments', 'shop=supermarket', 'shop=convenience', 'amenity=school', 'amenity=kindergarten', 'amenity=pharmacy'],
        'athlete' => ['leisure=sports_centre', 'leisure=fitness_centre', 'leisure=stadium', 'leisure=pitch', 'leisure=swimming_pool', 'sport=*'],
        'frequent_traveler' => ['tourism=hotel', 'tourism=hostel', 'aeroway=aerodrome', 'railway=station', 'amenity=bus_station', 'tourism=attraction', 'tourism=museum'],
        'working_student' => ['@student', '@office_worker'],
        'other' => [],
        'prefer_not_to_say' => [],
    ];

    /** @return list<string> */
    public static function ids(): array
    {
        return array_keys(self::PROFILES);
    }

    public static function normalize(?string $profile): string
    {
        $profile = strtolower(trim((string)$profile));
        $profile = ['unspecified' => 'prefer_not_to_say', 'retail_employee' => 'retail_worker', 'government_employee' => 'public_servant', 'working_studying' => 'working_student'][$profile] ?? $profile;
        return isset(self::PROFILES[$profile]) ? $profile : self::DEFAULT_PROFILE;
    }

    /** @return list<string> */
    public static function patterns(string $profile): array
    {
        $profile = self::normalize($profile);
        $out = [];
        foreach (self::PROFILES[$profile] as $entry) {
            if ($entry[0] === '@') {
                foreach (self::patterns(substr($entry, 1)) as $p) $out[$p] = true;
            } else {
                $out[$entry] = true;
            }
        }
        return array_keys($out);
    }

    public static function isNeutral(string $profile): bool
    {
        return in_array(self::normalize($profile), self::NEUTRAL, true);
    }

    /**
     * Relevance in [0,1]. Neutral profiles and non-matching tags both yield a
     * mid score, so a profile can only raise a candidate, never rule one out.
     *
     * @param array<string,string> $tags
     */
    public static function relevance(string $profile, array $tags): float
    {
        if (self::isNeutral($profile)) return 0.5;
        foreach (self::patterns($profile) as $pattern) {
            [$key, $value] = array_pad(explode('=', $pattern, 2), 2, '*');
            if (!isset($tags[$key])) continue;
            if ($value === '*' || strcasecmp((string)$tags[$key], $value) === 0) return 1.0;
        }
        return 0.35;
    }
}
