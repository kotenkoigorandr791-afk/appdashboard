<?php
require_once __DIR__ . '/schema_once.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/pgsql_compat.php';
require_once __DIR__ . '/db_auth_security.php';
function app_main_db(): AppDatabase {
    return app_open_main_db();
}
function app_notifications_ensure(AppDatabase $db): void {
    app_schema_once('app_notif', __FILE__, fn() => app_notifications_ensure_impl($db));
}
function app_notifications_ensure_impl(AppDatabase $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS app_notifications (
        id BIGSERIAL PRIMARY KEY,
        user_email TEXT NOT NULL DEFAULT '',
        app_id TEXT NOT NULL DEFAULT '',
        severity TEXT NOT NULL DEFAULT 'info',
        title TEXT NOT NULL,
        message TEXT NOT NULL DEFAULT '',
        event_type TEXT NOT NULL DEFAULT '',
        context_json TEXT NOT NULL DEFAULT '{}',
        is_read INTEGER NOT NULL DEFAULT 0,
        created_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint),
        read_at BIGINT
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS app_metrics (
        id BIGSERIAL PRIMARY KEY,
        app_id TEXT NOT NULL DEFAULT '',
        metric TEXT NOT NULL,
        value DOUBLE PRECISION NOT NULL DEFAULT 0,
        unit TEXT NOT NULL DEFAULT '',
        user_email TEXT NOT NULL DEFAULT '',
        context_json TEXT NOT NULL DEFAULT '{}',
        created_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint)
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_app_notifications_user_read ON app_notifications(user_email, is_read, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_app_metrics_app_created ON app_metrics(app_id, created_at)");
}
function app_notify(string $email, string $appId, string $severity, string $title, string $message = '', string $eventType = '', array $context = []): void {
    try {
        $db = app_main_db();
        app_notifications_ensure($db);
        $severity = in_array($severity, ['info','success','warning','error','critical'], true) ? $severity : 'info';
        $st = $db->prepare("INSERT INTO app_notifications(user_email,app_id,severity,title,message,event_type,context_json,is_read,created_at) VALUES(:u,:a,:s,:t,:m,:e,:c,0,:n)");
        $st->bindValue(':u', $email, PDO::PARAM_STR);
        $st->bindValue(':a', $appId, PDO::PARAM_STR);
        $st->bindValue(':s', $severity, PDO::PARAM_STR);
        $st->bindValue(':t', $title, PDO::PARAM_STR);
        $st->bindValue(':m', $message, PDO::PARAM_STR);
        $st->bindValue(':e', $eventType, PDO::PARAM_STR);
        $st->bindValue(':c', json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
        $st->bindValue(':n', time(), PDO::PARAM_INT);
        $st->execute();
    } catch (Throwable $e) {
        error_log('app_notify: ' . $e->getMessage());
    }
}
function app_weather_ensure(AppDatabase $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS app_weather_settings (
        user_email TEXT PRIMARY KEY,
        use_current_location BOOLEAN NOT NULL DEFAULT true,
        selected_country TEXT,
        selected_country_code TEXT,
        selected_region TEXT,
        selected_city TEXT,
        lat DOUBLE PRECISION,
        lon DOUBLE PRECISION,
        updated_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint)
    )");
    try {
        $db->exec("ALTER TABLE app_weather_settings ADD COLUMN selected_country_code TEXT");
    } catch (Throwable $e) {
    }
}

function app_get_weather_settings(string $email): array {
    try {
        $db = app_main_db();
        app_weather_ensure($db);
        $st = $db->prepare("SELECT * FROM app_weather_settings WHERE user_email = :email");
        $st->bindValue(':email', $email, PDO::PARAM_STR);
        $result = $st->execute()->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            return [
                'use_current_location' => (bool)$result['use_current_location'],
                'selected_country' => $result['selected_country'],
                'selected_country_code' => $result['selected_country_code'] ?? null,
                'selected_region' => $result['selected_region'],
                'selected_city' => $result['selected_city'],
                'lat' => $result['lat'] !== null ? (float)$result['lat'] : null,
                'lon' => $result['lon'] !== null ? (float)$result['lon'] : null
            ];
        }
    } catch (Throwable $e) {
        error_log('app_get_weather_settings: ' . $e->getMessage());
    }
    return [
        'use_current_location' => true,
        'selected_country' => null,
        'selected_country_code' => null,
        'selected_region' => null,
        'selected_city' => null,
        'lat' => null,
        'lon' => null
    ];
}

function app_set_weather_settings(string $email, array $settings): void {
    try {
        $db = app_main_db();
        app_weather_ensure($db);

        $existing = app_get_weather_settings($email);
        $use_current = array_key_exists('use_current_location', $settings) ? $settings['use_current_location'] : $existing['use_current_location'];
        $country = array_key_exists('selected_country', $settings) ? $settings['selected_country'] : $existing['selected_country'];
        $countryCode = array_key_exists('selected_country_code', $settings) ? $settings['selected_country_code'] : ($existing['selected_country_code'] ?? null);
        $region = array_key_exists('selected_region', $settings) ? $settings['selected_region'] : $existing['selected_region'];
        $city = array_key_exists('selected_city', $settings) ? $settings['selected_city'] : $existing['selected_city'];
        $lat = array_key_exists('lat', $settings) ? $settings['lat'] : $existing['lat'];
        $lon = array_key_exists('lon', $settings) ? $settings['lon'] : $existing['lon'];

        $st = $db->prepare("INSERT INTO app_weather_settings (user_email, use_current_location, selected_country, selected_country_code, selected_region, selected_city, lat, lon, updated_at)
                          VALUES (:email, :use_current, :country, :country_code, :region, :city, :lat, :lon, :updated_at)
                          ON CONFLICT (user_email) DO UPDATE SET
                              use_current_location = EXCLUDED.use_current_location,
                              selected_country = EXCLUDED.selected_country,
                              selected_country_code = EXCLUDED.selected_country_code,
                              selected_region = EXCLUDED.selected_region,
                              selected_city = EXCLUDED.selected_city,
                              lat = EXCLUDED.lat,
                              lon = EXCLUDED.lon,
                              updated_at = EXCLUDED.updated_at");
        $st->bindValue(':email', $email, PDO::PARAM_STR);
        $st->bindValue(':use_current', $use_current ? 1 : 0, PDO::PARAM_BOOL);
        $st->bindValue(':country', $country, $country === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $st->bindValue(':country_code', $countryCode, $countryCode === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $st->bindValue(':region', $region, $region === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $st->bindValue(':city', $city, $city === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $st->bindValue(':lat', $lat, $lat === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $st->bindValue(':lon', $lon, $lon === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $st->bindValue(':updated_at', time(), PDO::PARAM_INT);
        $st->execute();
    } catch (Throwable $e) {
        error_log('app_set_weather_settings: ' . $e->getMessage());
    }
}

function app_metric(string $email, string $appId, string $metric, float $value, string $unit = '', array $context = []): void {
    try {
        $db = app_main_db();
        app_notifications_ensure($db);
        $st = $db->prepare("INSERT INTO app_metrics(app_id,metric,value,unit,user_email,context_json,created_at) VALUES(:a,:m,:v,:u,:e,:c,:t)");
        $st->bindValue(':a', $appId, PDO::PARAM_STR);
        $st->bindValue(':m', $metric, PDO::PARAM_STR);
        $st->bindValue(':v', $value, PDO::PARAM_STR);
        $st->bindValue(':u', $unit, PDO::PARAM_STR);
        $st->bindValue(':e', $email, PDO::PARAM_STR);
        $st->bindValue(':c', json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
        $st->bindValue(':t', time(), PDO::PARAM_INT);
        $st->execute();
    } catch (Throwable $e) {
        error_log('app_metric: ' . $e->getMessage());
    }
}
