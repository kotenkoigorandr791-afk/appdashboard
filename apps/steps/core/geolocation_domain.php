<?php
final class StepsGeolocationDomain {
    public const SOURCE_TYPES = ['mobile', 'import', 'api'];
    public const POINT_STATES = ['pending', 'accepted', 'flagged', 'rejected'];
    public const DERIVED_STATES = ['pending', 'processing', 'ready', 'failed', 'stale'];
    public const FAMILY_ACCESS = ['none', 'current', 'recent', 'range', 'places'];

    public static function normalizeOwner(string $email): string {
        return strtolower(trim($email));
    }

    public static function isUuid(string $value): bool {
        return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
    }

    public static function isCoordinate(float $latitude, float $longitude): bool {
        return is_finite($latitude) && is_finite($longitude) && $latitude >= -90.0 && $latitude <= 90.0 && $longitude >= -180.0 && $longitude <= 180.0;
    }

    public static function isSourceType(string $sourceType): bool {
        return in_array($sourceType, self::SOURCE_TYPES, true);
    }

    public static function isPointState(string $state): bool {
        return in_array($state, self::POINT_STATES, true);
    }

    public static function isDerivedState(string $state): bool {
        return in_array($state, self::DERIVED_STATES, true);
    }

    public static function canTransitionDerivedState(string $from, string $to): bool {
        $transitions = [
            'pending' => ['processing', 'failed'],
            'processing' => ['ready', 'failed', 'stale'],
            'ready' => ['stale'],
            'failed' => ['pending'],
            'stale' => ['pending'],
        ];
        return isset($transitions[$from]) && in_array($to, $transitions[$from], true);
    }

    public static function isFamilyAccess(string $access): bool {
        return in_array($access, self::FAMILY_ACCESS, true);
    }
}
