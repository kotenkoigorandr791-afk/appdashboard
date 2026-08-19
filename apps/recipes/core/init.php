<?php
// Default parameters
require_once dirname(__DIR__, 3) . '/project/i18n_helpers.php';
$appDir = dirname(__DIR__);
$lang = isset($_GET['lang']) && ap_i18n_lang_exists_in_dir($appDir, $_GET['lang']) ? ap_normalize_i18n_lang($_GET['lang']) : 'en';
$theme = isset($_GET['theme']) && in_array($_GET['theme'], ['light', 'dark']) ? $_GET['theme'] : 'light';
$color = isset($_GET['color']) && preg_match('/^#[0-9A-Fa-f]{6}$/', $_GET['color']) ? $_GET['color'] : '#78a300';

// Load translations and config with UTF-8
$translations = ap_load_i18n_all_langs($appDir);
$GLOBALS['_t_i18n'] = $translations[$lang] ?? [];

// Load config from database or fallback to default
$db = getDB();
$config = ['items_per_page' => 12]; // Default
if ($db) {
    try {
        $config_result = $db->query("SELECT key, value FROM " . RECIPES_DB_TABLE_PREFIX . "config");
        while ($row = $config_result->fetch(PDO::FETCH_ASSOC)) {
            $value = $row['value'];
            // Try to decode JSON values
            $decoded = json_decode($value, true);
            if ($decoded !== null) {
                $config[$row['key']] = $decoded;
            } else {
                $config[$row['key']] = $value;
            }
        }
    } catch (Exception $e) {
        error_log("Failed to load config from database: " . $e->getMessage());
    }
}

$userRole = 0; // Guest by default
$currentUser = 'Guest';

if (!function_exists('recipes_getActiveSessionEmail')) {
    function recipes_getActiveSessionEmail(?string $sessionId)
    {
        require_once __DIR__ . '/../../../lib/redis.php';
        $sessionId = trim((string)$sessionId);
        if ($sessionId === '') {
            return null;
        }
        if (function_exists('app_redis')) {
            try {
                $redis = app_redis();
                if ($redis) {
                    $raw = $redis->get(app_redis_key('active_session:' . $sessionId));
                    if ($raw) {
                        $row = json_decode((string)$raw, true);
                        if (is_array($row) && !empty($row['email'])) {
                            return (string)$row['email'];
                        }
                    }
                }
            } catch (Throwable $e) {
            }
        }
        $db = getDB();
        if (!$db) {
            return null;
        }
        try {
            $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :session_id LIMIT 1');
            $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_STR);
            $result = $stmt->execute();
            $row = $result->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['email'])) {
                return (string)$row['email'];
            }
        } catch (Throwable $e) {
        }
        return null;
    }
}

// Get user data from Redis-backed sessions (fallback to SQL)
$db = getDB();
$session_id = session_id();

if ($db && $session_id) {
    try {
        $email = recipes_getActiveSessionEmail($session_id);
        if ($email) {
            $currentUser = explode('@', $email)[0] ?? 'user';
            $currentUser = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $currentUser);
            $userRole = getUserRoleByEmail($email);
        }
    } catch (Exception $e) {
        error_log("Failed to get user session data: " . $e->getMessage());
    }
}

// If still no user found, try with cookie session ID
if ($userRole === 0 && $db && isset($_COOKIE['PHPSESSID'])) {
    try {
        $cookie_session = (string)$_COOKIE['PHPSESSID'];
        $email = recipes_getActiveSessionEmail($cookie_session);
        if ($email) {
            $currentUser = explode('@', $email)[0] ?? 'user';
            $currentUser = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $currentUser);
            $userRole = getUserRoleByEmail($email);
        }
    } catch (Exception $e) {
        error_log("Failed to get user session data from cookie: " . $e->getMessage());
    }
}

// Directory setup
$recipes_dir = 'recipes';
$products_dir = 'products';
if (!is_dir($recipes_dir)) mkdir($recipes_dir, 0777, true);
if (!is_dir($products_dir)) mkdir($products_dir, 0777, true);

// Load recipe state from database
$recipe_state = ['current_recipe' => null, 'completed_steps' => []];
if ($db) {
    try {
        $state_result = $db->query("SELECT key, value FROM " . RECIPES_DB_TABLE_PREFIX . "state");
        while ($row = $state_result->fetch(PDO::FETCH_ASSOC)) {
            $value = $row['value'];
            $decoded = json_decode($value, true);
            $recipe_state[$row['key']] = $decoded !== null ? $decoded : $value;
        }
        // Decode completed_steps if it's a JSON string
        if (isset($recipe_state['completed_steps']) && is_string($recipe_state['completed_steps'])) {
            $recipe_state['completed_steps'] = json_decode($recipe_state['completed_steps'], true) ?: [];
        }
    } catch (Exception $e) {
        error_log("Failed to load recipe state from database: " . $e->getMessage());
    }
}
