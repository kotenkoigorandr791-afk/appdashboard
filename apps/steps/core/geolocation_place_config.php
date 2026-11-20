<?php
final class StepsGeolocationPlaceConfig
{
    public const DEFAULT_PROVIDERS = [
        'https://overpass-api.de/api/interpreter',
        'https://overpass.private.coffee/api/interpreter',
        'https://ovp.dresel.systems/api/interpreter',
    ];

    private static function env(string $key, float $default, float $min, float $max): float
    {
        $raw = getenv($key);
        if (!is_string($raw) || trim($raw) === '' || !is_numeric($raw)) return $default;
        return max($min, min($max, (float)$raw));
    }

    /** @return array<string,float> */
    public static function stop(): array
    {
        return [
            'radius_m' => self::env('GEO_STOP_RADIUS_M', 50.0, 10.0, 2000.0),
            'min_duration_s' => self::env('GEO_STOP_MIN_DURATION_S', 300.0, 60.0, 86400.0),
            'min_points' => self::env('GEO_STOP_MIN_POINTS', 3.0, 2.0, 100.0),
            'preferred_accuracy_m' => self::env('GEO_STOP_PREFERRED_ACCURACY_M', 50.0, 5.0, 5000.0),
            'max_accuracy_m' => self::env('GEO_STOP_MAX_ACCURACY_M', 200.0, 5.0, 5000.0),
            'max_gap_s' => self::env('GEO_STOP_MAX_GAP_S', 900.0, 60.0, 86400.0),
            'merge_gap_s' => self::env('GEO_STOP_MERGE_GAP_S', 600.0, 0.0, 86400.0),
            'merge_radius_m' => self::env('GEO_STOP_MERGE_RADIUS_M', 80.0, 5.0, 5000.0),
            'confirm_after_s' => self::env('GEO_STOP_CONFIRM_AFTER_S', 600.0, 0.0, 86400.0),
        ];
    }

    /** @return array<string,float|int|list<string>> */
    public static function osm(): array
    {
        $providers = getenv('GEO_OVERPASS_PROVIDERS');
        $list = is_string($providers) && trim($providers) !== ''
            ? array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $providers) ?: []), static fn(string $u): bool => preg_match('#^https?://#i', $u) === 1))
            : self::DEFAULT_PROVIDERS;
        return [
            'providers' => $list ?: self::DEFAULT_PROVIDERS,
            'search_radius_m' => self::env('GEO_OSM_SEARCH_RADIUS_M', 100.0, 20.0, 1000.0),
            'timeout_s' => (int)self::env('GEO_OVERPASS_TIMEOUT_S', 20.0, 3.0, 120.0),
            'max_attempts' => (int)self::env('GEO_OVERPASS_MAX_ATTEMPTS', 4.0, 1.0, 10.0),
            'backoff_base_ms' => (int)self::env('GEO_OVERPASS_BACKOFF_MS', 500.0, 0.0, 60000.0),
            'provider_cooldown_s' => (int)self::env('GEO_OVERPASS_COOLDOWN_S', 120.0, 0.0, 3600.0),
            'min_interval_ms' => (int)self::env('GEO_OVERPASS_MIN_INTERVAL_MS', 1000.0, 0.0, 60000.0),
            'cache_ttl_s' => (int)self::env('GEO_OSM_CACHE_TTL_S', 30.0 * 86400.0, 60.0, 365.0 * 86400.0),
            'negative_cache_ttl_s' => (int)self::env('GEO_OSM_NEGATIVE_TTL_S', 3600.0, 30.0, 86400.0),
            'cache_grid_deg' => self::env('GEO_OSM_CACHE_GRID_DEG', 0.0005, 0.0001, 0.01),
            'max_elements' => (int)self::env('GEO_OSM_MAX_ELEMENTS', 150.0, 10.0, 1000.0),
        ];
    }

    /** @return array<string,float> */
    public static function weights(): array
    {
        return [
            'distance' => self::env('GEO_RANK_W_DISTANCE', 3.0, 0.0, 20.0),
            'inside' => self::env('GEO_RANK_W_INSIDE', 3.0, 0.0, 20.0),
            'accuracy' => self::env('GEO_RANK_W_ACCURACY', 1.0, 0.0, 20.0),
            'duration' => self::env('GEO_RANK_W_DURATION', 1.0, 0.0, 20.0),
            'distribution' => self::env('GEO_RANK_W_DISTRIBUTION', 1.0, 0.0, 20.0),
            'category' => self::env('GEO_RANK_W_CATEGORY', 1.5, 0.0, 20.0),
            'profile' => self::env('GEO_RANK_W_PROFILE', 1.5, 0.0, 20.0),
            'name' => self::env('GEO_RANK_W_NAME', 1.0, 0.0, 20.0),
        ];
    }

    /** @return array<string,float> */
    public static function thresholds(): array
    {
        return [
            'min_confidence' => self::env('GEO_RANK_MIN_CONFIDENCE', 0.45, 0.0, 1.0),
            'confirmed_confidence' => self::env('GEO_RANK_CONFIRMED_CONFIDENCE', 0.62, 0.0, 1.0),
            'ambiguous_cap' => self::env('GEO_RANK_AMBIGUOUS_CAP', 0.55, 0.0, 1.0),
            'max_candidates' => self::env('GEO_RANK_MAX_CANDIDATES', 6.0, 1.0, 20.0),
        ];
    }
}
