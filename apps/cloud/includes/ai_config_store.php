<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../lib/app_db.php';

const APP_AI_CONFIG_TABLE = 'app_ai_config';
const CLOUD_AI_CONFIG_TABLE = 'cloud_ai_config';
const CLOUD_AI_CFG_ENDPOINT = 'api_endpoint';
const CLOUD_AI_CFG_KEY = 'api_key';
const CLOUD_AI_CFG_MODELS = 'models';
const CLOUD_AI_CFG_DEFAULT_MODEL = 'default_model';
const CLOUD_AI_LEGACY_KEYS = [
    'api_endpoint' => 'cloud_ai_api_endpoint',
    'api_key' => 'cloud_ai_api_key',
    'models' => 'cloud_ai_models',
    'default_model' => 'cloud_ai_default_model',
];
const CLOUD_AI_DEFAULT_ENDPOINT = ''; // filled by cloud_ai_default_endpoint()
const CLOUD_AI_DEFAULT_MODEL = 'github/gpt-4o-mini';

function cloud_ai_default_endpoint(): string
{
    if (!function_exists('app_public_url')) {
        require_once dirname(__DIR__, 3) . '/lib/app_domains.php';
    }
    $fromEnv = trim((string)(getenv('AI_PUBLIC_URL') ?: ''));
    if ($fromEnv !== '') {
        return rtrim($fromEnv, '/') . '/v1/chat/completions';
    }
    return rtrim(app_public_url('ai'), '/') . '/v1/chat/completions';
}

const CLOUD_AI_DEFAULT_MODELS = [
    'github/gpt-4o-mini',
    'github/gpt-4o',
    'github/gpt-5-mini',
    'github/gpt-5.4-mini',
    'github/claude-haiku-4.5',
];

function cloud_ai_db(): ?AppDatabase
{
    static $db = null;
    if ($db instanceof AppDatabase) {
        return $db;
    }
    try {
        $db = app_main_db();
        $db->enableExceptions(true);
        cloud_ai_ensure_table($db);
        return $db;
    } catch (Throwable $e) {
        cloud_safe_error_log('cloud_ai_db: ' . $e->getMessage());
        return null;
    }
}

function cloud_ai_ensure_table(AppDatabase $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $table = APP_AI_CONFIG_TABLE;
    $db->exec("CREATE TABLE IF NOT EXISTS $table (key TEXT PRIMARY KEY, value TEXT NOT NULL DEFAULT '')");
    cloud_ai_migrate_legacy_config($db);
    $done = true;
}

function cloud_ai_table_has_rows(AppDatabase $db, string $table): bool
{
    try {
        $stmt = $db->prepare("SELECT COUNT(*) AS c FROM $table");
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        return $row && (int)$row['c'] > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function cloud_ai_table_exists(AppDatabase $db, string $table): bool
{
    try {
        $stmt = $db->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name = :n LIMIT 1");
        $stmt->bindValue(':n', $table, PDO::PARAM_STR);
        return (bool)$stmt->execute()->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
}

function cloud_ai_copy_config_rows(AppDatabase $db, string $fromTable, string $toTable): void
{
    if (!cloud_ai_table_exists($db, $fromTable)) {
        return;
    }
    $rows = $db->query("SELECT key, value FROM $fromTable");
    while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
        $key = trim((string)($row['key'] ?? ''));
        $value = (string)($row['value'] ?? '');
        if ($key === '' || $value === '') {
            continue;
        }
        $ins = $db->prepare("INSERT INTO $toTable(key, value) VALUES(:k, :v) ON CONFLICT (key) DO UPDATE SET value=excluded.value");
        $ins->bindValue(':k', $key, PDO::PARAM_STR);
        $ins->bindValue(':v', $value, PDO::PARAM_STR);
        $ins->execute();
    }
}

function cloud_ai_migrate_legacy_config(AppDatabase $db): void
{
    static $migrated = false;
    if ($migrated) {
        return;
    }
    $table = APP_AI_CONFIG_TABLE;
    try {
        if (!cloud_ai_table_has_rows($db, $table)) {
            cloud_ai_copy_config_rows($db, CLOUD_AI_CONFIG_TABLE, $table);
        }
        if (!cloud_ai_table_has_rows($db, $table)) {
            foreach (CLOUD_AI_LEGACY_KEYS as $newKey => $legacyKey) {
                $stmt = $db->prepare('SELECT value FROM config WHERE key = :k LIMIT 1');
                $stmt->bindValue(':k', $legacyKey, PDO::PARAM_STR);
                $legacyRow = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
                if (!$legacyRow) {
                    continue;
                }
                $value = (string)$legacyRow['value'];
                if ($value === '') {
                    continue;
                }
                $ins = $db->prepare("INSERT INTO $table(key, value) VALUES(:k, :v)");
                $ins->bindValue(':k', $newKey, PDO::PARAM_STR);
                $ins->bindValue(':v', $value, PDO::PARAM_STR);
                $ins->execute();
            }
        }
        foreach (CLOUD_AI_LEGACY_KEYS as $legacyKey) {
            $del = $db->prepare('DELETE FROM config WHERE key = :k');
            $del->bindValue(':k', $legacyKey, PDO::PARAM_STR);
            $del->execute();
        }
        if (cloud_ai_table_exists($db, CLOUD_AI_CONFIG_TABLE)) {
            $db->exec('DROP TABLE ' . CLOUD_AI_CONFIG_TABLE);
        }
    } catch (Throwable $e) {
        cloud_safe_error_log('cloud_ai_migrate_legacy_config: ' . $e->getMessage());
    }
    $migrated = true;
}

function cloud_ai_get_config(string $key, string $default = ''): string
{
    $db = cloud_ai_db();
    if (!$db) {
        return $default;
    }
    try {
        $table = APP_AI_CONFIG_TABLE;
        $stmt = $db->prepare("SELECT value FROM $table WHERE key = :k LIMIT 1");
        $stmt->bindValue(':k', $key, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        return $row ? (string)$row['value'] : $default;
    } catch (Throwable $e) {
        cloud_safe_error_log('cloud_ai_get_config: ' . $e->getMessage());
        return $default;
    }
}

function cloud_ai_set_config(string $key, string $value): bool
{
    $db = cloud_ai_db();
    if (!$db) {
        return false;
    }
    try {
        $table = APP_AI_CONFIG_TABLE;
        $stmt = $db->prepare("INSERT INTO $table(key, value) VALUES(:k, :v)");
        $stmt->bindValue(':k', $key, PDO::PARAM_STR);
        $stmt->bindValue(':v', $value, PDO::PARAM_STR);
        $stmt->execute();
        return true;
    } catch (Throwable $e) {
        cloud_safe_error_log('cloud_ai_set_config: ' . $e->getMessage());
        return false;
    }
}

function cloud_ai_normalize_endpoint(string $endpoint): string
{
    $endpoint = trim($endpoint);
    if ($endpoint === '') {
        return cloud_ai_default_endpoint();
    }
    if (!preg_match('#/chat/completions#i', $endpoint)) {
        $endpoint = rtrim($endpoint, '/') . '/chat/completions';
    }
    return $endpoint;
}

function cloud_ai_parse_models_list($raw): array
{
    if (is_array($raw)) {
        $items = $raw;
    } else {
        $text = trim((string)$raw);
        if ($text === '') {
            return [];
        }
        if ($text[0] === '[') {
            $decoded = json_decode($text, true);
            $items = is_array($decoded) ? $decoded : preg_split('/[\r\n,]+/', $text);
        } else {
            $items = preg_split('/[\r\n,]+/', $text);
        }
    }
    $out = [];
    foreach ($items as $item) {
        $id = trim((string)$item);
        if ($id !== '' && !in_array($id, $out, true)) {
            $out[] = $id;
        }
    }
    return $out;
}

function cloud_ai_models_to_json(array $models): string
{
    return json_encode(array_values($models), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function cloud_ai_seed_defaults(): void
{
    if (cloud_ai_get_config(CLOUD_AI_CFG_ENDPOINT, '') === '') {
        cloud_ai_set_config(CLOUD_AI_CFG_ENDPOINT, cloud_ai_default_endpoint());
    }
    if (cloud_ai_get_config(CLOUD_AI_CFG_DEFAULT_MODEL, '') === '') {
        cloud_ai_set_config(CLOUD_AI_CFG_DEFAULT_MODEL, CLOUD_AI_DEFAULT_MODEL);
    }
    if (cloud_ai_get_config(CLOUD_AI_CFG_MODELS, '') === '') {
        cloud_ai_set_config(CLOUD_AI_CFG_MODELS, cloud_ai_models_to_json(CLOUD_AI_DEFAULT_MODELS));
    }
}

function cloud_ai_load_config(): array
{
    cloud_ai_seed_defaults();
    $models = cloud_ai_parse_models_list(cloud_ai_get_config(CLOUD_AI_CFG_MODELS, ''));
    if ($models === []) {
        $models = CLOUD_AI_DEFAULT_MODELS;
    }
    $defaultModel = trim(cloud_ai_get_config(CLOUD_AI_CFG_DEFAULT_MODEL, CLOUD_AI_DEFAULT_MODEL));
    if ($defaultModel === '' || !in_array($defaultModel, $models, true)) {
        $defaultModel = $models[0] ?? CLOUD_AI_DEFAULT_MODEL;
    }
    return [
        'api_key' => trim(cloud_ai_get_config(CLOUD_AI_CFG_KEY, '')),
        'api_endpoint' => cloud_ai_normalize_endpoint(cloud_ai_get_config(CLOUD_AI_CFG_ENDPOINT, cloud_ai_default_endpoint())),
        'default_model' => $defaultModel,
        'models' => $models,
    ];
}

function cloud_ai_validate_api_key(string $apiKey): ?string
{
    $apiKey = trim($apiKey);
    if ($apiKey === '') {
        return 'API key is required';
    }
    if (preg_match('/^(sk-|io-v2-)/', $apiKey)) {
        return null;
    }
    return 'Invalid API key format. Key should start with "sk-" or "io-v2-"';
}

function cloud_ai_save_config(array $newConfig): array
{
    $current = cloud_ai_load_config();
    if (isset($newConfig['api_key'])) {
        $apiKey = trim((string)$newConfig['api_key']);
        $keyError = cloud_ai_validate_api_key($apiKey);
        if ($keyError !== null) {
            return ['error' => $keyError, 'code' => 400];
        }
        cloud_ai_set_config(CLOUD_AI_CFG_KEY, $apiKey);
        $current['api_key'] = $apiKey;
    }
    if (isset($newConfig['api_endpoint'])) {
        $endpoint = cloud_ai_normalize_endpoint((string)$newConfig['api_endpoint']);
        cloud_ai_set_config(CLOUD_AI_CFG_ENDPOINT, $endpoint);
        $current['api_endpoint'] = $endpoint;
    }
    if (isset($newConfig['default_model'])) {
        $defaultModel = trim((string)$newConfig['default_model']);
        if ($defaultModel !== '') {
            cloud_ai_set_config(CLOUD_AI_CFG_DEFAULT_MODEL, $defaultModel);
            $current['default_model'] = $defaultModel;
        }
    }
    if (isset($newConfig['models'])) {
        $models = cloud_ai_parse_models_list($newConfig['models']);
        if ($models === []) {
            return ['error' => 'At least one model is required', 'code' => 400];
        }
        cloud_ai_set_config(CLOUD_AI_CFG_MODELS, cloud_ai_models_to_json($models));
        $current['models'] = $models;
        if (!in_array($current['default_model'], $models, true)) {
            $current['default_model'] = $models[0];
            cloud_ai_set_config(CLOUD_AI_CFG_DEFAULT_MODEL, $current['default_model']);
        }
    }
    return ['success' => true, 'message' => 'AI configuration updated', 'config' => $current];
}
