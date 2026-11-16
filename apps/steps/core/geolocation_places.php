<?php
/**
 * Personal places (task 13).
 *
 * Places are private to their owner: no public directory and no Google API.
 * "Google-style" only means familiar semantic tags (Home, Work, Gym, ...),
 * which are versioned here and labelled through the existing i18n files.
 */
final class StepsGeolocationPlaces
{
    public const TAG_VERSION = 'tags-1';
    public const BUILTIN_TAGS = ['home', 'work', 'gym', 'parents', 'school', 'favorite'];
    /** Tags that may exist only once per owner while active. */
    public const SINGLETON_TAGS = ['home', 'work'];
    public const MAX_RADIUS_M = 5000.0;
    public const MIN_RADIUS_M = 25.0;

    public static function normalizeTag(string $tag): string
    {
        $tag = strtolower(trim($tag));
        $tag = preg_replace('/[^a-z0-9_-]+/', '', $tag) ?? '';
        return substr($tag, 0, 32);
    }

    public static function isBuiltinTag(string $tag): bool
    {
        return in_array(self::normalizeTag($tag), self::BUILTIN_TAGS, true);
    }

    public static function isSingletonTag(string $tag): bool
    {
        return in_array(self::normalizeTag($tag), self::SINGLETON_TAGS, true);
    }

    /**
     * @return array{place:?array<string,mixed>,errors:list<string>}
     */
    public static function validate(array $input, bool $partial = false): array
    {
        $errors = [];
        $place = [];

        if (array_key_exists('name', $input) || !$partial) {
            $name = trim((string)($input['name'] ?? ''));
            if ($name === '') $errors[] = 'invalid_name';
            elseif (function_exists('mb_strlen') ? mb_strlen($name) > 80 : strlen($name) > 80) $errors[] = 'name_too_long';
            $place['name'] = $name;
        }

        if (array_key_exists('tag', $input) || !$partial) {
            $tag = self::normalizeTag((string)($input['tag'] ?? ''));
            if ($tag === '') $errors[] = 'invalid_tag';
            $place['tag'] = $tag;
        }

        if (array_key_exists('latitude', $input) || array_key_exists('longitude', $input) || !$partial) {
            $lat = $input['latitude'] ?? null;
            $lon = $input['longitude'] ?? null;
            if (!is_numeric($lat) || !is_numeric($lon)
                || (float)$lat < -90 || (float)$lat > 90 || (float)$lon < -180 || (float)$lon > 180) {
                $errors[] = 'invalid_coordinates';
            }
            $place['latitude'] = (float)$lat;
            $place['longitude'] = (float)$lon;
        }

        if (array_key_exists('radius_m', $input) || !$partial) {
            $radius = $input['radius_m'] ?? 120;
            if (!is_numeric($radius) || (float)$radius < self::MIN_RADIUS_M || (float)$radius > self::MAX_RADIUS_M) {
                $errors[] = 'invalid_radius';
            }
            $place['radius_m'] = (float)$radius;
        }

        if (array_key_exists('color', $input)) {
            $color = trim((string)$input['color']);
            if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $errors[] = 'invalid_color';
            $place['color'] = $color;
        }
        if (array_key_exists('notes', $input)) {
            $notes = trim((string)$input['notes']);
            if (function_exists('mb_strlen') ? mb_strlen($notes) > 500 : strlen($notes) > 500) $errors[] = 'notes_too_long';
            $place['notes'] = $notes;
        }
        if (array_key_exists('timezone', $input)) {
            $timezone = trim((string)$input['timezone']);
            if ($timezone === '') $timezone = 'UTC';
            if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) $errors[] = 'invalid_timezone';
            $place['timezone'] = $timezone;
        }
        if (array_key_exists('is_active', $input)) {
            $place['is_active'] = (bool)$input['is_active'];
        }

        return ['place' => $errors === [] ? $place : null, 'errors' => $errors];
    }

    public static function uuid(): string
    {
        $b = bin2hex(random_bytes(16));
        return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
    }
}
