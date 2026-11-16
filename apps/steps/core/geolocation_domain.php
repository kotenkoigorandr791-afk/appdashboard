<?php
final class StepsGeolocationDomain {
    public const SOURCE_TYPES = ['mobile', 'import', 'api'];
    public const POINT_STATES = ['pending', 'accepted', 'flagged', 'rejected'];
    public const DERIVED_STATES = ['pending', 'processing', 'ready', 'failed', 'stale'];
    public const FAMILY_ACCESS = ['none', 'current', 'recent', 'range', 'places'];
    public const DEVICE_STATUSES = ['active', 'paused', 'revoked', 'deleted'];
    public const DEVICE_STATUS_TRANSITIONS = [
        'active' => ['paused', 'revoked', 'deleted'],
        'paused' => ['active', 'revoked', 'deleted'],
        'revoked' => ['active'], // explicit re-enrollment only
        'deleted' => [],
    ];

    public static function isDeviceStatus(string $status): bool {
        return in_array($status, self::DEVICE_STATUSES, true);
    }

    public static function canTransitionDevice(string $from, string $to): bool {
        return isset(self::DEVICE_STATUS_TRANSITIONS[$from])
            && in_array($to, self::DEVICE_STATUS_TRANSITIONS[$from], true);
    }

    /** Trim, drop control characters, collapse whitespace and cap the label. */
    public static function normalizeDeviceName(string $name): string {
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name) ?? '';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '') return '';
        return function_exists('mb_substr') ? mb_substr($name, 0, 64) : substr($name, 0, 64);
    }

    /** Ingestion is allowed only for devices the owner has not paused or revoked. */
    public static function deviceAcceptsPoints(string $status): bool {
        return $status === 'active';
    }

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
