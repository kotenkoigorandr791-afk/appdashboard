<?php
/**
 * Personal settings + profile helpers for the mobile Settings screen.
 *
 * Kept free of side effects at include time so the test suite
 * (tests/php) can load the real production code without running a route.
 */

/** Read the caller's profile row with an index-friendly lookup. */
function steps_read_profile(?string $email): array {
    $profile = ['email' => $email ?: '', 'name' => $email ?: 'StepMonitor', 'photo' => ''];
    if (!$email || !function_exists('app_main_db')) return $profile;
    try {
        $mainDb = app_main_db();
        $stmt = $mainDb->prepare('SELECT name, photo FROM users WHERE email = :email LIMIT 1');
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            // Fallback for accounts whose stored casing differs from the session.
            $stmt = $mainDb->prepare('SELECT name, photo FROM users WHERE lower(email) = :email LIMIT 1');
            $stmt->bindValue(':email', strtolower($email), PDO::PARAM_STR);
            $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        }
        if ($row) {
            $profile['name'] = (string)($row['name'] ?: $profile['name']);
            $profile['photo'] = function_exists('app_avatar_src') ? app_avatar_src($row['photo'] ?? '') : (string)($row['photo'] ?? '');
        }
    } catch (Throwable $ignored) {
    }
    return $profile;
}

/**
 * Whitelist + bounds for the personal settings a client may write.
 * Anything outside this list is ignored, so a payload can never reach
 * arbitrary keys (role, password, other users' rows, ...).
 */
function steps_settings_validators(): array {
    return [
        'goal_steps' => static function ($v) {
            if (!preg_match('/^\d+$/', (string)$v)) return null;
            $n = (int)$v;
            return $n >= 1000 && $n <= 200000 ? (string)$n : null;
        },
        'height' => static fn($v) => preg_match('/^\d+$/', (string)$v) && (int)$v >= 50 && (int)$v <= 260 ? (string)(int)$v : null,
        'weight' => static fn($v) => preg_match('/^\d+$/', (string)$v) && (int)$v >= 20 && (int)$v <= 400 ? (string)(int)$v : null,
        'stride_length' => static fn($v) => preg_match('/^\d+$/', (string)$v) && (int)$v >= 20 && (int)$v <= 250 ? (string)(int)$v : null,
        'birth_date' => static function ($v) {
            if (!is_string($v) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) return null;
            if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1])) return null;
            if ($v < '1900-01-01' || $v > date('Y-m-d')) return null;
            return $v;
        },
    ];
}

/** Defaults used when the user has never stored a value. */
function steps_settings_defaults(): array {
    global $defaultSettings;
    if (is_array($defaultSettings ?? null) && $defaultSettings !== []) return $defaultSettings;
    return ['goal_steps' => 10000, 'height' => 170, 'weight' => 70, 'stride_length' => 70, 'birth_date' => '1994-01-01'];
}

/**
 * Validates and persists the personal settings from a client payload.
 * Only whitelisted keys that actually changed are written, which keeps the
 * database load proportional to real edits instead of to request volume.
 */
function steps_update_settings(AppDatabase $db, array $payload): array {
    $defaults = steps_settings_defaults();
    $validators = steps_settings_validators();
    $current = loadUserSettings($db, $defaults);
    $accepted = [];
    $errors = [];
    foreach ($validators as $key => $validate) {
        if (!array_key_exists($key, $payload)) continue;
        $value = $validate($payload[$key]);
        if ($value === null) {
            $errors[$key] = 'invalid_value';
            continue;
        }
        if ((string)$current[$key] === $value) continue;
        saveUserSetting($db, $key, $value);
        $accepted[$key] = $value;
    }
    if ($errors) public_api_json(['status' => 'error', 'message' => 'invalid_settings', 'errors' => $errors], 400);
    return ['status' => 'success', 'updated' => array_keys($accepted), 'settings' => loadUserSettings($db, $defaults)];
}

/** Renames the calling user's profile. Email stays the immutable identity. */
function steps_update_profile(array $payload): array {
    $email = get_current_user_email();
    if (!$email) public_api_json(['status' => 'error', 'message' => 'unauthorized'], 401);
    $name = trim((string)($payload['name'] ?? ''));
    $name = preg_replace('/[\x{0000}-\x{001F}]/u', '', $name) ?? $name;
    if ($name === '' || mb_strlen($name) > 80) {
        public_api_json(['status' => 'error', 'message' => 'invalid_name'], 400);
    }
    try {
        $mainDb = app_main_db();
        $stmt = $mainDb->prepare('UPDATE users SET name = :n WHERE email = :e');
        $stmt->bindValue(':n', $name, PDO::PARAM_STR);
        $stmt->bindValue(':e', $email, PDO::PARAM_STR);
        $stmt->execute();
        if (function_exists('userCacheBust')) userCacheBust($email);
    } catch (Throwable $e) {
        public_api_json(['status' => 'error', 'message' => 'profile_update_failed'], 500);
    }
    return ['status' => 'success', 'profile' => steps_read_profile($email)];
}
