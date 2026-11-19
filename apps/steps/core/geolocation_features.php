<?php
/**
 * Tenant-scoped geolocation feature flags (task 32).
 *
 * Defaults are enabled; a global env kill switch or a tenant row can disable a
 * capability independently. Flags are additive and never delete data.
 */
require_once __DIR__ . '/geolocation_security.php';

final class StepsGeolocationFeatureError extends RuntimeException
{
    public $errorCode;
    public $status;

    public function __construct(string $errorCode, int $status = 403)
    {
        $this->errorCode = $errorCode;
        $this->status = $status;
        parent::__construct($errorCode);
    }
}

final class StepsGeolocationFeatures
{
    public const FLAGS = ['capture', 'family', 'immich', 'video', 'achievements', 'map', 'mcp'];

    public static function disabledGlobally(): array
    {
        $raw = strtolower((string)getenv('GEO_FEATURES_DISABLED'));
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public static function enabled(PDO $pdo, string $owner, string $flag): bool
    {
        $flag = strtolower(trim($flag));
        if (!in_array($flag, self::FLAGS, true)) return false;
        if (in_array($flag, self::disabledGlobally(), true)) return false;
        $env = getenv('GEO_FEATURE_' . strtoupper($flag));
        if (is_string($env) && in_array(strtolower($env), ['0', 'false', 'off', 'no'], true)) return false;
        try {
            $stmt = $pdo->prepare('SELECT enabled FROM geo_feature_flags WHERE user_email = :owner AND flag = :flag');
            $stmt->execute([':owner' => StepsGeolocationSecurity::normalizeOwner($owner), ':flag' => $flag]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            // fetchColumn() cannot distinguish false from "no tenant row".
            if (!$row) return true;
            $value = $row['enabled'];
            return $value === true || $value === 't' || $value === 1 || $value === '1';
        } catch (Throwable $e) {
            return true;
        }
    }

    public static function requireEnabled(PDO $pdo, string $owner, string $flag): void
    {
        if (!self::enabled($pdo, $owner, $flag)) throw new StepsGeolocationFeatureError('feature_disabled', 403);
    }

    /** @return array<string,bool> */
    public static function all(PDO $pdo, string $owner): array
    {
        $out = [];
        foreach (self::FLAGS as $flag) $out[$flag] = self::enabled($pdo, $owner, $flag);
        return $out;
    }

    public static function set(PDO $pdo, string $owner, string $flag, bool $enabled): array
    {
        $flag = strtolower(trim($flag));
        if (!in_array($flag, self::FLAGS, true)) throw new StepsGeolocationFeatureError('invalid_feature', 400);
        $owner = StepsGeolocationSecurity::normalizeOwner($owner);
        $pdo->prepare(
            "INSERT INTO geo_feature_flags(user_email, flag, enabled, updated_at) VALUES(:owner, :flag, :enabled, now())
             ON CONFLICT (user_email, flag) DO UPDATE SET enabled = EXCLUDED.enabled, updated_at = now()"
        )->execute([':owner' => $owner, ':flag' => $flag, ':enabled' => $enabled ? 'true' : 'false']);
        StepsGeolocationSecurity::audit($pdo, $owner, $owner, 'features.updated', ['flag' => $flag, 'enabled' => $enabled]);
        return self::all($pdo, $owner);
    }
}
