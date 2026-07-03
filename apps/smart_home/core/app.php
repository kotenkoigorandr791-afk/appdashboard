<?php
$__d = __DIR__;
for ($__n = 0; $__n < 16; $__n++) {
    if (is_file($__d . '/smart_log_boot.php')) {
        require_once $__d . '/smart_log_boot.php';
        break;
    }
    $__p = dirname($__d);
    if ($__p === $__d) break;
    $__d = $__p;
}

session_start();
header('Content-Type: text/html; charset=UTF-8');
define('SH_APP_DIR', dirname(__DIR__));
require_once dirname(SH_APP_DIR, 2) . '/lib/app_db.php';
function sh_db(): AppDatabase {
    static $db = null;
    if ($db === null) {
        $db = app_main_db();
        $db->enableExceptions(true);
        sh_init_tables($db);
    }
    return $db;
}

function sh_init_tables(AppDatabase $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS smarthome_settings (
        user_email TEXT PRIMARY KEY,
        server_domain TEXT NOT NULL DEFAULT 'serverkherson.xyz',
        ap_domain TEXT NOT NULL DEFAULT 'serverkherson.xyz',
        updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS smarthome_devices (
        id TEXT PRIMARY KEY,
        user_email TEXT NOT NULL,
        name TEXT NOT NULL,
        type TEXT NOT NULL DEFAULT 'generic_sensor',
        room TEXT NOT NULL DEFAULT 'Living room',
        status TEXT NOT NULL DEFAULT 'unknown',
        local_url TEXT NOT NULL DEFAULT '',
        server_domain TEXT NOT NULL DEFAULT 'serverkherson.xyz',
        ap_domain TEXT NOT NULL DEFAULT 'serverkherson.xyz',
        mqtt_topic TEXT NOT NULL DEFAULT '',
        device_token TEXT NOT NULL DEFAULT '',
        notes TEXT NOT NULL DEFAULT '',
        icon TEXT NOT NULL DEFAULT 'smart-device',
        controls TEXT NOT NULL DEFAULT '[]',
        params TEXT NOT NULL DEFAULT '[]',
        files TEXT NOT NULL DEFAULT '[]',
        displayed_params TEXT NOT NULL DEFAULT '[]',
        created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS smarthome_token_index (
        token TEXT PRIMARY KEY,
        user_email TEXT NOT NULL,
        device_id TEXT NOT NULL,
        updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS smarthome_groups (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        user_email TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS smarthome_group_members (
        group_id TEXT NOT NULL,
        member_email TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'guest' CHECK(role IN ('admin','guest')),
        added_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
        PRIMARY KEY (group_id, member_email)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS smarthome_monitor_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_email TEXT NOT NULL,
        device_id TEXT,
        event_type TEXT NOT NULL,
        event_data TEXT NOT NULL DEFAULT '{}',
        created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS smarthome_params_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        device_id TEXT NOT NULL,
        param_id TEXT NOT NULL,
        param_label TEXT NOT NULL,
        param_value TEXT NOT NULL,
        recorded_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS smarthome_floor_plans (
        user_email TEXT PRIMARY KEY,
        elements_json TEXT NOT NULL DEFAULT '[]',
        updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS smarthome_ws_tokens (
        token TEXT PRIMARY KEY,
        user_email TEXT NOT NULL,
        device_id TEXT NOT NULL,
        expires_at INTEGER NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS smarthome_control_queue (
        id TEXT PRIMARY KEY,
        user_email TEXT NOT NULL,
        device_id TEXT NOT NULL,
        control_id TEXT NOT NULL,
        value_json TEXT NOT NULL,
        created_at REAL NOT NULL,
        expires_at REAL NOT NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS smarthome_control_results (
        id TEXT PRIMARY KEY,
        result_json TEXT NOT NULL,
        updated_at REAL NOT NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS smarthome_errors (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_email TEXT NOT NULL DEFAULT '',
        device_id TEXT NOT NULL DEFAULT '',
        device_name TEXT NOT NULL DEFAULT '',
        source TEXT NOT NULL DEFAULT '',
        message TEXT NOT NULL,
        context_json TEXT NOT NULL DEFAULT '{}',
        created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    )");
    sh_ensure_column($db, 'smarthome_devices', 'displayed_controls', "TEXT NOT NULL DEFAULT '[]'");
    sh_ensure_column($db, 'smarthome_devices', 'category', "TEXT NOT NULL DEFAULT ''");
    sh_ensure_column($db, 'smarthome_devices', 'group_id', "TEXT NOT NULL DEFAULT ''");
    sh_ensure_column($db, 'smarthome_settings', 'home_scope', "TEXT NOT NULL DEFAULT 'own'");
    sh_ensure_column($db, 'smarthome_settings', 'home_group_id', "TEXT NOT NULL DEFAULT ''");
    sh_ensure_column($db, 'smarthome_settings', 'home_view', "TEXT NOT NULL DEFAULT 'tiles'");
    sh_ensure_column($db, 'smarthome_devices', 'icon_locked', "INTEGER NOT NULL DEFAULT 0");
}

function sh_ensure_column(AppDatabase $db, string $table, string $column, string $def): void {
    $res = $db->query('PRAGMA table_info(' . $table . ')');
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        if (($row['name'] ?? '') === $column) return;
    }
    $db->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $def);
}

// ─── Helpers ────────────────────────────────────────────────────────────────────
function sh_h(string $v): string { return htmlspecialchars($v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

function sh_user_email(): string {
    // Check $_SESSION['email'] first (set by parent app login)
    if (isset($_SESSION['email']) && !empty($_SESSION['email'])) {
        return (string)$_SESSION['email'];
    }
    // Check GET ?user= param for testing
    if (isset($_GET['user']) && !empty($_GET['user'])) {
        return (string)$_GET['user'];
    }
    $sid = session_id();
    if ($sid === '') return 'guest@local';
    try {
        $db = sh_db();
        $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :sid LIMIT 1');
        $stmt->bindValue(':sid', $sid, SQLITE3_TEXT);
        $r = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($r && !empty($r['email'])) return (string)$r['email'];
    } catch (Throwable $e) { error_log('sh user lookup: '.$e->getMessage()); }
    return 'guest@local';
}

function sh_user_service_role(): int {
    static $cached = null;
    if ($cached !== null) return $cached;
    $email = sh_user_email();
    if ($email === 'guest@local') { $cached = 0; return 0; }
    try {
        $db = sh_db();
        $stmt = $db->prepare('SELECT role FROM users WHERE email = :em LIMIT 1');
        $stmt->bindValue(':em', $email, SQLITE3_TEXT);
        $r = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $cached = $r ? (int)($r['role'] ?? 0) : 0;
    } catch (Throwable $e) { $cached = 0; }
    return $cached;
}

function sh_can_edit_endpoints(): bool {
    $r = sh_user_service_role();
    return $r === 1 || $r === 2;
}

function sh_settings_defaults(): array {
    return ['server_domain' => 'serverkherson.xyz', 'ap_domain' => 'serverkherson.xyz', 'home_scope' => 'own', 'home_group_id' => '', 'home_view' => 'tiles'];
}

function sh_load_user_settings(AppDatabase $db, string $userEmail): array {
    $stmt = $db->prepare('SELECT server_domain, ap_domain, home_scope, home_group_id, home_view FROM smarthome_settings WHERE user_email = :em');
    $stmt->bindValue(':em', $userEmail, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return array_merge(sh_settings_defaults(), is_array($row) ? $row : []);
}

function sh_group_row(AppDatabase $db, string $gid): ?array {
    $stmt = $db->prepare('SELECT * FROM smarthome_groups WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $gid, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ?: null;
}

function sh_group_member_role(AppDatabase $db, string $gid, string $userEmail): ?string {
    $stmt = $db->prepare('SELECT role FROM smarthome_group_members WHERE group_id = :gid AND member_email = :em LIMIT 1');
    $stmt->bindValue(':gid', $gid, SQLITE3_TEXT);
    $stmt->bindValue(':em', $userEmail, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ? (string)($row['role'] ?? '') : null;
}

function sh_group_access(AppDatabase $db, string $gid, string $userEmail): ?array {
    $g = sh_group_row($db, $gid);
    if (!$g) return null;
    $isOwner = ($g['user_email'] ?? '') === $userEmail;
    $memberRole = sh_group_member_role($db, $gid, $userEmail);
    $canManage = $isOwner || $memberRole === 'admin';
    return [
        'group' => $g,
        'is_owner' => $isOwner,
        'member_role' => $isOwner ? 'admin' : $memberRole,
        'can_manage' => $canManage,
        'can_see_members' => $canManage,
    ];
}

function sh_user_in_group(AppDatabase $db, string $gid, string $userEmail): bool {
    return sh_group_access($db, $gid, $userEmail) !== null;
}

function sh_user_is_member_of_owner(AppDatabase $db, string $userEmail, string $ownerEmail): bool {
    if ($userEmail === $ownerEmail) return true;
    $stmt = $db->prepare('SELECT 1 FROM smarthome_groups g INNER JOIN smarthome_group_members gm ON gm.group_id = g.id WHERE g.user_email = :owner AND gm.member_email = :em LIMIT 1');
    $stmt->bindValue(':owner', $ownerEmail, SQLITE3_TEXT);
    $stmt->bindValue(':em', $userEmail, SQLITE3_TEXT);
    return (bool)$stmt->execute()->fetchArray();
}

function sh_device_can_configure(AppDatabase $db, string $deviceId, string $userEmail): bool {
    $row = sh_device_row_for_user($db, $deviceId, $userEmail);
    if (!$row) return false;
    return ($row['user_email'] ?? '') === $userEmail;
}

function sh_user_can_assign_group(AppDatabase $db, string $gid, string $userEmail): bool {
    $acc = sh_group_access($db, $gid, $userEmail);
    return $acc && $acc['can_manage'];
}

function sh_resolve_home_context(AppDatabase $db, string $userEmail, array $settings): array {
    $scope = (($settings['home_scope'] ?? 'own') === 'group') ? 'group' : 'own';
    $gid = trim((string)($settings['home_group_id'] ?? ''));
    $ctx = ['scope' => 'own', 'group_id' => '', 'group' => null, 'can_edit_map' => true, 'floor_owner' => $userEmail];
    if ($scope === 'group' && $gid !== '') {
        $acc = sh_group_access($db, $gid, $userEmail);
        if ($acc) {
            $ctx = [
                'scope' => 'group',
                'group_id' => $gid,
                'group' => $acc['group'],
                'can_edit_map' => $acc['can_manage'],
                'floor_owner' => (string)($acc['group']['user_email'] ?? $userEmail),
            ];
        }
    }
    return $ctx;
}

function sh_fetch_scoped_devices(AppDatabase $db, string $userEmail, array $ctx): array {
    $devices = [];
    $seen = [];
    if (($ctx['scope'] ?? 'own') === 'group' && !empty($ctx['group_id'])) {
        $owner = (string)($ctx['floor_owner'] ?? '');
        $r = $db->prepare('SELECT * FROM smarthome_devices WHERE group_id = :gid OR user_email = :owner ORDER BY room, name');
        $r->bindValue(':gid', $ctx['group_id'], SQLITE3_TEXT);
        $r->bindValue(':owner', $owner, SQLITE3_TEXT);
    } else {
        $r = $db->prepare("SELECT * FROM smarthome_devices WHERE user_email = :em AND (group_id IS NULL OR group_id = '') ORDER BY room, name");
        $r->bindValue(':em', $userEmail, SQLITE3_TEXT);
    }
    $res = $r->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $id = (string)($row['id'] ?? '');
        if ($id === '' || isset($seen[$id])) continue;
        $seen[$id] = true;
        $devices[] = sh_device_row_decode($row);
    }
    return $devices;
}

function sh_fetch_my_devices(AppDatabase $db, string $userEmail): array {
    $devices = [];
    $r = $db->prepare('SELECT * FROM smarthome_devices WHERE user_email = :em ORDER BY room, name');
    $r->bindValue(':em', $userEmail, SQLITE3_TEXT);
    $res = $r->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) $devices[] = sh_device_row_decode($row);
    return $devices;
}

function sh_fetch_floor_plan(AppDatabase $db, string $ownerEmail): array {
    $fpStmt = $db->prepare('SELECT elements_json FROM smarthome_floor_plans WHERE user_email = :em');
    $fpStmt->bindValue(':em', $ownerEmail, SQLITE3_TEXT);
    $fpRow = $fpStmt->execute()->fetchArray(SQLITE3_ASSOC);
    if ($fpRow) return json_decode($fpRow['elements_json'] ?: '[]', true) ?: [];
    return [];
}

function sh_device_row_for_user(AppDatabase $db, string $deviceId, string $userEmail): ?array {
    $stmt = $db->prepare('SELECT * FROM smarthome_devices WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $deviceId, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    if (($row['user_email'] ?? '') === $userEmail) return $row;
    $gid = trim((string)($row['group_id'] ?? ''));
    if ($gid !== '' && sh_user_in_group($db, $gid, $userEmail)) return $row;
    $owner = (string)($row['user_email'] ?? '');
    if ($owner !== '' && sh_user_is_member_of_owner($db, $userEmail, $owner)) return $row;
    return null;
}

function sh_enrich_groups_list(AppDatabase $db, array $groups, string $userEmail): array {
    foreach ($groups as &$g) {
        $isOwner = ($g['user_email'] ?? '') === $userEmail;
        $mr = $g['member_role'] ?? null;
        if ($isOwner) $mr = 'admin';
        $g['is_owner'] = $isOwner;
        $g['can_manage'] = $isOwner || $mr === 'admin';
        $g['can_see_members'] = $g['can_manage'];
    }
    unset($g);
    return $groups;
}

function sh_slug(string $v): string {
    $s = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $v), '-'));
    return $s !== '' ? $s : 'device';
}

function sh_token(int $len = 20): string {
    return substr(bin2hex(random_bytes($len)), 0, $len * 2);
}

function sh_local_ws_host(): string {
    return (string)(getenv('SMART_HOME_WS_BIND') ?: '127.0.0.1');
}

function sh_local_ws_port(): int {
    return (int)(getenv('SMART_HOME_WS_PORT') ?: 8766);
}

function sh_store_dashboard_ws_token(string $userEmail, string $deviceId): array {
    $token = sh_token(18);
    $db = sh_db();
    $exp = time() + 300;
    $stmt = $db->prepare('INSERT INTO smarthome_ws_tokens (token, user_email, device_id, expires_at) VALUES (:tok, :em, :did, :exp)');
    $stmt->bindValue(':tok', $token, SQLITE3_TEXT);
    $stmt->bindValue(':em', $userEmail, SQLITE3_TEXT);
    $stmt->bindValue(':did', $deviceId, SQLITE3_TEXT);
    $stmt->bindValue(':exp', $exp, SQLITE3_INTEGER);
    $stmt->execute();
    $db->exec("DELETE FROM smarthome_ws_tokens WHERE expires_at < " . time());
    return [
        'token' => $token,
        'ws_url' => 'wss://'.($_SERVER['HTTP_HOST']??'serverkherson.xyz').'/smart-home-ws',
    ];
}

function sh_enqueue_control_request(string $userEmail, string $deviceId, string $controlId, $value): string {
    $db = sh_db();
    $requestId = sh_token(12);
    $now = microtime(true);
    $stmt = $db->prepare('INSERT INTO smarthome_control_queue (id, user_email, device_id, control_id, value_json, created_at, expires_at) VALUES (:id, :em, :did, :cid, :val, :ca, :exp)');
    $stmt->bindValue(':id', $requestId, SQLITE3_TEXT);
    $stmt->bindValue(':em', $userEmail, SQLITE3_TEXT);
    $stmt->bindValue(':did', $deviceId, SQLITE3_TEXT);
    $stmt->bindValue(':cid', $controlId, SQLITE3_TEXT);
    $stmt->bindValue(':val', json_encode($value, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
    $stmt->bindValue(':ca', $now, SQLITE3_FLOAT);
    $stmt->bindValue(':exp', $now + 120.0, SQLITE3_FLOAT);
    $stmt->execute();
    $db->exec('DELETE FROM smarthome_control_queue WHERE expires_at < ' . $now);
    return $requestId;
}

function sh_wait_for_control_result(string $requestId, float $timeoutSeconds = 7.0): ?array {
    $db = sh_db();
    $deadline = microtime(true) + $timeoutSeconds;
    while (microtime(true) < $deadline) {
        $stmt = $db->prepare('SELECT result_json FROM smarthome_control_results WHERE id = :id');
        $stmt->bindValue(':id', $requestId, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($row) {
            $del = $db->prepare('DELETE FROM smarthome_control_results WHERE id = :id');
            $del->bindValue(':id', $requestId, SQLITE3_TEXT);
            $del->execute();
            return json_decode($row['result_json'] ?: '{}', true) ?: [];
        }
        usleep(100000);
    }
    return null;
}

function sh_json_field(mixed $v, array $default = []): array {
    if (is_array($v)) return $v;
    if (!is_string($v) || $v === '') return $default;
    $d = json_decode($v, true);
    return is_array($d) ? $d : $default;
}

function sh_device_row_decode(array $row): array {
    $row['controls'] = sh_json_field($row['controls'] ?? '[]');
    $row['params'] = sh_json_field($row['params'] ?? '[]');
    $row['files'] = sh_json_field($row['files'] ?? '[]');
    $row['displayed_params'] = sh_json_field($row['displayed_params'] ?? '[]');
    $row['displayed_controls'] = sh_json_field($row['displayed_controls'] ?? '[]');
    if (!empty($row['displayed_params']) && !empty($row['params'])) {
        $paramById = [];
        foreach ($row['params'] as $param) {
            $pid = (string)($param['id'] ?? '');
            if ($pid !== '') $paramById[$pid] = $param;
        }
        foreach ($row['displayed_params'] as &$displayed) {
            $pid = (string)($displayed['id'] ?? '');
            if ($pid !== '' && isset($paramById[$pid])) {
                $displayed = array_merge($displayed, $paramById[$pid]);
            }
        }
        unset($displayed);
    }
    return $row;
}

function sh_normalize_control_value(array $control, mixed $value): mixed {
    if (($control['type'] ?? '') === 'switch') return (bool)$value;
    if (($control['type'] ?? '') === 'range') return (int)$value;
    return (string)$value;
}

function sh_controls_to_map(array $controls): array {
    $map = [];
    foreach ($controls as $c) {
        $id = (string)($c['id'] ?? '');
        if ($id !== '') $map[$id] = $c['value'] ?? null;
    }
    return $map;
}

function sh_should_apply_definition(array $device): bool {
    return ((string)($device['type'] ?? '')) === 'pending';
}

function sh_apply_device_definition(array $device, array $definition): array {
    $sectionLabels = [];
    if (!empty($definition['sections']) && is_array($definition['sections'])) {
        foreach ($definition['sections'] as $section) {
            if (!is_array($section)) continue;
            $sid = trim((string)($section['id'] ?? ''));
            if ($sid === '') continue;
            $sectionLabels[$sid] = trim((string)($section['label'] ?? $sid));
        }
    }
    if (($definition['type'] ?? '') !== '') {
        $device['type'] = (string)$definition['type'];
    }
    $iconLocked = !empty($device['icon_locked']);
    if (!$iconLocked) {
        if (($definition['icon'] ?? '') !== '') {
            $device['icon'] = (string)$definition['icon'];
        } elseif (($definition['type'] ?? '') !== '' && ($device['type'] ?? '') !== 'pending') {
            $tpl = (sh_templates())[$device['type']] ?? null;
            if ($tpl && !empty($tpl['icon'])) {
                $device['icon'] = (string)$tpl['icon'];
            }
        }
    }
    if (!empty($definition['controls']) && is_array($definition['controls'])) {
        $existingValues = [];
        foreach (($device['controls'] ?? []) as $control) {
            $id = (string)($control['id'] ?? '');
            if ($id !== '') {
                $existingValues[$id] = $control['value'] ?? null;
            }
        }
        $controls = [];
        foreach ($definition['controls'] as $control) {
            if (!is_array($control)) continue;
            $id = trim((string)($control['id'] ?? ''));
            if ($id === '') continue;
            if (!array_key_exists('value', $control) && array_key_exists($id, $existingValues)) {
                $control['value'] = $existingValues[$id];
            }
            $sid = trim((string)($control['section'] ?? ''));
            if ($sid !== '' && isset($sectionLabels[$sid])) {
                $control['section_label'] = $sectionLabels[$sid];
            }
            $controls[] = $control;
        }
        $device['controls'] = $controls;
    }
    if (!empty($definition['params']) && is_array($definition['params'])) {
        $existingValues = [];
        foreach (($device['params'] ?? []) as $param) {
            $id = (string)($param['id'] ?? '');
            if ($id !== '') {
                $existingValues[$id] = $param['value'] ?? null;
            }
        }
        $params = [];
        foreach ($definition['params'] as $param) {
            if (!is_array($param)) continue;
            $id = trim((string)($param['id'] ?? ''));
            if ($id === '') continue;
            if (!array_key_exists('value', $param) && array_key_exists($id, $existingValues)) {
                $param['value'] = $existingValues[$id];
            }
            $sid = trim((string)($param['section'] ?? ''));
            if ($sid !== '' && isset($sectionLabels[$sid])) {
                $param['section_label'] = $sectionLabels[$sid];
            }
            $params[] = $param;
        }
        $device['params'] = $params;
    }
    if (!empty($definition['files']) && is_array($definition['files'])) {
        $device['files'] = array_values(array_filter($definition['files'], 'is_array'));
    }
    return $device;
}

function sh_pending_control_ids_for_device(AppDatabase $db, string $deviceId): array {
    $ids = [];
    $now = microtime(true);
    $stmt = $db->prepare('SELECT control_id FROM smarthome_control_queue WHERE device_id = :did AND expires_at > :now');
    $stmt->bindValue(':did', $deviceId, SQLITE3_TEXT);
    $stmt->bindValue(':now', $now, SQLITE3_FLOAT);
    $res = $stmt->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $ids[] = (string)($row['control_id'] ?? '');
    }
    return $ids;
}

function sh_clear_control_queue_for_device(AppDatabase $db, string $deviceId): void {
    $stmt = $db->prepare('DELETE FROM smarthome_control_queue WHERE device_id = :did');
    $stmt->bindValue(':did', $deviceId, SQLITE3_TEXT);
    $stmt->execute();
}

function sh_trigger_ws_dispatch(): void {
    $script = SH_APP_DIR . '/ws_dispatch_tick.php';
    if (!is_file($script)) return;
    $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        @pclose(@popen('start /B ' . escapeshellarg($php) . ' ' . escapeshellarg($script), 'r'));
    } else {
        @exec(escapeshellarg($php) . ' ' . escapeshellarg($script) . ' > /dev/null 2>&1 &');
    }
}

function sh_gp_hardware_name(string $deviceType, string $controlId, array $controls = []): string {
    foreach ($controls as $c) {
        if (($c['id'] ?? '') === $controlId && !empty($c['gp_id'])) {
            return (string)$c['gp_id'];
        }
    }
    static $map = [
        'smart_torch' => ['relay_1' => 'switch_1', 'relay_2' => 'switch_2'],
        'sonoff_relay' => ['relay_1' => 'relay_1'],
    ];
    return $map[$deviceType][$controlId] ?? $controlId;
}

function sh_gp_encode_value(array $control, mixed $value): string {
    if (($control['type'] ?? '') === 'switch') {
        return ($value === true || $value === 1 || $value === '1' || $value === 'true') ? '1' : '0';
    }
    if (($control['type'] ?? '') === 'range') {
        return (string)(int)$value;
    }
    return (string)$value;
}

function sh_control_supports_local_gp(array $device, string $controlId): bool {
    $controls = is_array($device['controls'] ?? null) ? $device['controls'] : sh_json_field($device['controls'] ?? '[]');
    foreach ($controls as $c) {
        if (($c['id'] ?? '') === $controlId && !empty($c['gp_id'])) {
            return true;
        }
    }
    static $map = ['smart_torch' => ['relay_1', 'relay_2'], 'sonoff_relay' => ['relay_1']];
    $type = (string)($device['type'] ?? '');
    return in_array($controlId, $map[$type] ?? [], true);
}

function sh_push_local_control(array $device, string $controlId, mixed $value): bool {
    if (!sh_control_supports_local_gp($device, $controlId)) {
        return false;
    }
    $localUrl = rtrim(trim((string)($device['local_url'] ?? '')), '/');
    if ($localUrl === '' || !preg_match('#^https?://#i', $localUrl)) {
        return false;
    }
    $controls = is_array($device['controls'] ?? null) ? $device['controls'] : sh_json_field($device['controls'] ?? '[]');
    $ctrl = null;
    foreach ($controls as $c) {
        if (($c['id'] ?? '') === $controlId) { $ctrl = $c; break; }
    }
    if (!$ctrl) return false;
    $type = (string)($device['type'] ?? '');
    $encoded = sh_gp_encode_value($ctrl, $value);
    $body = '';
    $path = '';
    if ($type === 'sonoff_relay' && $controlId === 'relay_1') {
        $path = '/api/relay';
        $body = 'state=' . rawurlencode($encoded);
    } elseif ($type === 'smart_torch' && ($controlId === 'relay_1' || $controlId === 'relay_2')) {
        $path = '/api/relays';
        $body = 'channel=' . rawurlencode($controlId === 'relay_1' ? '1' : '2') . '&state=' . rawurlencode($encoded);
    } else {
        $hw = sh_gp_hardware_name($type, $controlId, $controls);
        $path = '/GP_click?' . rawurlencode($hw) . '=' . rawurlencode($encoded);
    }
    $url = $localUrl . $path;
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $ctx = stream_context_create(['http' => [
            'timeout' => 4,
            'ignore_errors' => true,
            'method' => $body !== '' ? 'POST' : 'GET',
            'header' => ($body !== '' ? "Content-Type: application/x-www-form-urlencoded\r\n" : '') . "Connection: close\r\n",
            'content' => $body,
            'protocol_version' => 1.0,
        ]]);
        $resp = @file_get_contents($url, false, $ctx);
        if (is_string($resp) && str_contains($resp, '"ok":true')) {
            return true;
        }
        usleep(150000);
    }
    return false;
}

function sh_push_local_gp(array $device, string $controlId, mixed $value): bool {
    return sh_push_local_control($device, $controlId, $value);
}

function sh_deliver_control(AppDatabase $db, string $userEmail, string $deviceId, string $controlId, mixed $value, array $device): array {
    $delivered = ['ws' => false, 'local' => false];
    if (sh_push_local_control($device, $controlId, $value)) {
        $delivered['local'] = true;
        sh_clear_control_queue_for_device($db, $deviceId);
        return $delivered;
    }
    try {
        sh_ws_dispatch_control($userEmail, $deviceId, $controlId, $value);
        $delivered['ws'] = true;
        sh_clear_control_queue_for_device($db, $deviceId);
        return $delivered;
    } catch (Throwable $e) {
        sh_log_error($userEmail, $deviceId, (string)($device['name'] ?? ''), 'control_ws', $e->getMessage(), [
            'control_id' => $controlId,
            'value' => $value,
        ]);
    }
    sh_trigger_ws_dispatch();
    return $delivered;
}

function sh_api_docs_markdown(string $host): string {
    $base = 'https://' . $host . '/apps/smart_home/?api=';
    return <<<MD
# Smart Home REST API

Base URL: `{$base}`

---

## 🔐 Authentication

| Method | Auth | Description |
|--------|------|-------------|
| Device → Server | `device_token` | Every request includes `device_token` from dashboard |
| Dashboard → Server | Session | Browser session cookie or `?user=` param |
| WebSocket (device) | `{"type":"auth","role":"device","device_token":"…"}` | Authenticate as device |
| WebSocket (dashboard) | `{"type":"auth","role":"dashboard","session_token":"…"}` | Use `issue_ws_token` to get token |

---

## 1. `device_sync` — Device telemetry & state sync

**POST** `{$base}device_sync`

Register telemetry, push/receive control state. Used by ESP/Arduino firmware every 30–60s.

### Request
```json
{
  "device_token": "abc123…",
  "name": "Kitchen lamp",
  "local_url": "http://192.168.1.50",
  "telemetry": { "temperature": "22.1", "humidity": "48" },
  "controls": { "power": true, "brightness": 80 }
}
```

### Response
```json
{
  "ok": true,
  "device_id": "kitchen-lamp-abc123",
  "device_name": "Kitchen lamp",
  "controls": { "power": true, "brightness": 80, "mode": "warm" },
  "apply_controls": true,
  "server_time": "2025-05-21T12:00:00Z"
}
```

> **⚠️ Important:** The device **must** apply `controls` from the response to hardware (relays, PWM, etc.). Do not only push local state — always reconcile with server.

---

## 2. `control` — Send command to device

**POST** `{$base}control`

Queue a control command. Device picks it up on next `device_sync` or via WebSocket.

### Request
```json
{
  "device_id": "kitchen-lamp-abc123",
  "control_id": "power",
  "value": true
}
```

### Response
```json
{
  "ok": true,
  "queued": true,
  "delivered_local": false,
  "request_id": "a1b2c3d4e5f6",
  "device": { "id": "kitchen-lamp-abc123", "controls": […] },
  "device_status": "online"
}
```

| Field | Type | Description |
|-------|------|-------------|
| `queued` | bool | True if sent via dispatch queue |
| `delivered_local` | bool | True if delivered directly via GPIO/local HTTP |
| `device` | object | Full device object with updated controls |

---

## 3. `issue_ws_token` — Get WebSocket session token

**POST** `{$base}issue_ws_token`

### Request
```json
{
  "device_id": "kitchen-lamp-abc123"
}
```

### Response
```json
{
  "ok": true,
  "token": "aabbccddee…",
  "ws_url": "wss://{$host}/smart-home-ws"
}
```

---

## 4. `save_device` — Create / update device

**POST** `{$base}save_device`

Create a new device (requires `device_token`) or update an existing one (requires `id` + ownership).

### Create
```json
{
  "device_token": "abc…",
  "name": "Bedroom sensor",
  "type": "generic_sensor",
  "room": "Bedroom"
}
```

### Update
```json
{
  "id": "kitchen-lamp-abc123",
  "name": "Kitchen lamp v2",
  "room": "Kitchen",
  "icon": "lamp"
}
```

---

## 5. `state` — Get full dashboard state

**POST** `{$base}state`

Returns all devices, floor plan, groups, settings, and API docs for the authenticated user.

### Response
```json
{
  "ok": true,
  "settings": { "server_domain": "{$host}", … },
  "devices": […],
  "groups": […],
  "floor_plan": […],
  "home_scope": "own",
  "can_edit_map": true,
  "can_edit_endpoints": false,
  "service_role": 0
}
```

---

## 6. WebSocket — Real-time communication

**Endpoint:** `wss://{$host}/smart-home-ws`

### Flow

1. Dashboard calls `issue_ws_token` → gets a `session_token`
2. Dashboard connects to WebSocket → sends auth:
```json
{ "type": "auth", "role": "dashboard", "session_token": "…" }
```
3. Device connects → sends auth:
```json
{ "type": "auth", "role": "device", "device_token": "…" }
```
4. Device pushes state update:
```json
{ "type": "state", "controls": { "power": true }, "telemetry": { "temperature": "22.5" } }
```
5. Dashboard sends control:
```json
{ "type": "control", "control_id": "power", "value": true }
```
6. Device receives command:
```json
{ "type": "command", "control_id": "power", "value": true, "controls": { … } }
```

---

## 7. `save_settings` — User preferences

**POST** `{$base}save_settings`

```json
{
  "server_domain": "{$host}",
  "ap_domain": "{$host}",
  "home_scope": "own",
  "home_view": "tiles"
}
```

---

## 8. `delete_device` — Remove a device

**POST** `{$base}delete_device`

```json
{ "device_id": "kitchen-lamp-abc123" }
```

---

## 9. Groups API

| Action | Endpoint | Description |
|--------|----------|-------------|
| `create_group` | `{$base}create_group` | Create group (requires `name`) |
| `delete_group` | `{$base}delete_group` | Delete group (requires `group_id`) |
| `group_members` | `{$base}group_members` | List members (requires `group_id`) |
| `add_member` | `{$base}add_member` | Add user (requires `group_id`, `member_email`) |
| `remove_member` | `{$base}remove_member` | Remove user (requires `group_id`, `member_email`) |
| `update_member_role` | `{$base}update_member_role` | Change role (requires `group_id`, `member_email`, `role`) |

---

## 10. ESP / Arduino Quick Start

```cpp
#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>

void loop() {
  if (WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  http.begin("{$base}device_sync");
  http.addHeader("Content-Type", "application/json");

  String body = "{\"device_token\":\"YOUR_TOKEN\",\"telemetry\":{\"temperature\":\"22.1\"}}";
  int code = http.POST(body);

  if (code == 200) {
    String resp = http.getString();
    // Parse JSON, apply controls to relays/GPIO
  }
  http.end();
  delay(30000);
}
```

> For instant control, add WebSocket support using the `WebSocketClient` library.

---

## 📋 Monitor & Logs

| Action | Description |
|--------|-------------|
| `monitor_stats` | Get device list, events, and param history for a period (default 24h) |
| `record_param_history` | Record parameter snapshots for charting |
| `update_displayed_params` | Choose which params to show on dashboard |
| `update_displayed_controls` | Choose which controls to show on dashboard |
| `save_floor_plan` | Save floor plan elements |

MD;
}

function sh_migrate_json_runtime_data(AppDatabase $db): void {
    $wsFile = SH_APP_DIR . '/data/global/ws_tokens.json';
    if (is_file($wsFile)) {
        $tokens = json_decode((string)file_get_contents($wsFile), true) ?: [];
        foreach ($tokens as $tok => $meta) {
            if (!is_array($meta)) continue;
            $ins = $db->prepare('INSERT OR IGNORE INTO smarthome_ws_tokens (token, user_email, device_id, expires_at) VALUES (:tok, :em, :did, :exp)');
            $ins->bindValue(':tok', (string)$tok, SQLITE3_TEXT);
            $ins->bindValue(':em', (string)($meta['user_email'] ?? ''), SQLITE3_TEXT);
            $ins->bindValue(':did', (string)($meta['device_id'] ?? ''), SQLITE3_TEXT);
            $ins->bindValue(':exp', (int)($meta['expires_at'] ?? 0), SQLITE3_INTEGER);
            $ins->execute();
        }
        @unlink($wsFile);
    }
    foreach (['control_queue.json' => 'queue', 'control_results.json' => 'results'] as $file => $kind) {
        $path = SH_APP_DIR . '/data/global/' . $file;
        if (!is_file($path)) continue;
        $data = json_decode((string)file_get_contents($path), true) ?: [];
        if ($kind === 'queue') {
            foreach ($data as $id => $item) {
                if (!is_array($item)) continue;
                $ins = $db->prepare('INSERT OR IGNORE INTO smarthome_control_queue (id, user_email, device_id, control_id, value_json, created_at, expires_at) VALUES (:id, :em, :did, :cid, :val, :ca, :exp)');
                $ins->bindValue(':id', (string)$id, SQLITE3_TEXT);
                $ins->bindValue(':em', (string)($item['user_email'] ?? ''), SQLITE3_TEXT);
                $ins->bindValue(':did', (string)($item['device_id'] ?? ''), SQLITE3_TEXT);
                $ins->bindValue(':cid', (string)($item['control_id'] ?? ''), SQLITE3_TEXT);
                $ins->bindValue(':val', json_encode($item['value'] ?? null, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
                $ins->bindValue(':ca', (float)($item['created_at'] ?? microtime(true)), SQLITE3_FLOAT);
                $ins->bindValue(':exp', (float)($item['expires_at'] ?? microtime(true)), SQLITE3_FLOAT);
                $ins->execute();
            }
        } else {
            foreach ($data as $id => $item) {
                if (!is_array($item)) continue;
                $ins = $db->prepare('INSERT OR IGNORE INTO smarthome_control_results (id, result_json, updated_at) VALUES (:id, :rj, :ua)');
                $ins->bindValue(':id', (string)$id, SQLITE3_TEXT);
                $ins->bindValue(':rj', json_encode($item, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
                $ins->bindValue(':ua', (float)($item['updated_at'] ?? microtime(true)), SQLITE3_FLOAT);
                $ins->execute();
            }
        }
        @unlink($path);
    }
}

function sh_update_device_control(AppDatabase $db, string $deviceId, string $controlId, $value): ?array {
    $stmt = $db->prepare('SELECT * FROM smarthome_devices WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $deviceId, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) {
        return null;
    }
    $controls = sh_json_field($row['controls'] ?? '[]');
    $matched = false;
    foreach ($controls as &$c) {
        if (($c['id'] ?? '') !== $controlId) continue;
        $matched = true;
        if (($c['type'] ?? '') === 'switch') $c['value'] = (bool)$value;
        elseif (($c['type'] ?? '') === 'range') $c['value'] = (int)$value;
        else $c['value'] = (string)$value;
    }
    unset($c);
    if (!$matched) {
        return false;
    }
    $upd = $db->prepare("UPDATE smarthome_devices SET controls=:ctrls, updated_at=datetime('now','localtime') WHERE id=:id");
    $upd->bindValue(':ctrls', json_encode($controls, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
    $upd->bindValue(':id', $deviceId, SQLITE3_TEXT);
    $upd->execute();

    $row['controls'] = $controls;
    return sh_device_row_decode($row);
}

function sh_ws_read_exact($socket, int $length): string {
    $data = '';
    while (strlen($data) < $length && !feof($socket)) {
        $chunk = fread($socket, $length - strlen($data));
        if ($chunk === false || $chunk === '') {
            $meta = stream_get_meta_data($socket);
            if (!empty($meta['timed_out'])) {
                throw new RuntimeException('WebSocket read timeout');
            }
            if ($chunk === '') {
                usleep(10000);
                continue;
            }
            throw new RuntimeException('WebSocket read failed');
        }
        $data .= $chunk;
    }
    if (strlen($data) !== $length) {
        throw new RuntimeException('Unexpected end of WebSocket stream');
    }
    return $data;
}

function sh_ws_wait_for_data($socket, float $timeoutSeconds): bool {
    $read = [$socket];
    $write = [];
    $except = [];
    $seconds = (int)floor($timeoutSeconds);
    $microseconds = (int)(($timeoutSeconds - $seconds) * 1000000);
    $changed = @stream_select($read, $write, $except, $seconds, $microseconds);
    if ($changed === false) {
        throw new RuntimeException('WebSocket wait failed');
    }
    return $changed > 0;
}

function sh_ws_encode_frame(string $payload, int $opcode = 0x1): string {
    $finOpcode = 0x80 | ($opcode & 0x0F);
    $length = strlen($payload);
    $mask = random_bytes(4);
    if ($length < 126) {
        $header = pack('CC', $finOpcode, 0x80 | $length);
    } elseif ($length <= 0xFFFF) {
        $header = pack('CCn', $finOpcode, 0x80 | 126, $length);
    } else {
        $header = pack('CCNN', $finOpcode, 0x80 | 127, 0, $length);
    }
    $masked = '';
    for ($i = 0; $i < $length; $i++) {
        $masked .= $payload[$i] ^ $mask[$i % 4];
    }
    return $header . $mask . $masked;
}

function sh_ws_decode_frame($socket, float $timeoutSeconds = 2.0): ?array {
    if (!sh_ws_wait_for_data($socket, $timeoutSeconds)) {
        return null;
    }
    $header = sh_ws_read_exact($socket, 2);
    $first = ord($header[0]);
    $second = ord($header[1]);
    $opcode = $first & 0x0F;
    $masked = ($second & 0x80) !== 0;
    $length = $second & 0x7F;
    if ($length === 126) {
        $length = unpack('n', sh_ws_read_exact($socket, 2))[1];
    } elseif ($length === 127) {
        $parts = unpack('Nhigh/Nlow', sh_ws_read_exact($socket, 8));
        $length = ((int)$parts['high'] << 32) | (int)$parts['low'];
    }
    $mask = $masked ? sh_ws_read_exact($socket, 4) : '';
    $payload = $length > 0 ? sh_ws_read_exact($socket, $length) : '';
    if ($masked) {
        $decoded = '';
        for ($i = 0; $i < $length; $i++) {
            $decoded .= $payload[$i] ^ $mask[$i % 4];
        }
        $payload = $decoded;
    }
    return ['opcode' => $opcode, 'payload' => $payload];
}

function sh_ws_send_json($socket, array $payload): void {
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        throw new RuntimeException('Failed to encode WebSocket payload');
    }
    fwrite($socket, sh_ws_encode_frame($json, 0x1));
}

function sh_ws_extract_frame_from_buffer(string &$buffer): ?array {
    if (strlen($buffer) < 2) {
        return null;
    }
    $first = ord($buffer[0]);
    $second = ord($buffer[1]);
    $opcode = $first & 0x0F;
    $masked = ($second & 0x80) !== 0;
    $length = $second & 0x7F;
    $offset = 2;
    if ($length === 126) {
        if (strlen($buffer) < $offset + 2) return null;
        $length = unpack('n', substr($buffer, $offset, 2))[1];
        $offset += 2;
    } elseif ($length === 127) {
        if (strlen($buffer) < $offset + 8) return null;
        $parts = unpack('Nhigh/Nlow', substr($buffer, $offset, 8));
        $length = ((int)$parts['high'] << 32) | (int)$parts['low'];
        $offset += 8;
    }
    $mask = '';
    if ($masked) {
        if (strlen($buffer) < $offset + 4) return null;
        $mask = substr($buffer, $offset, 4);
        $offset += 4;
    }
    if (strlen($buffer) < $offset + $length) {
        return null;
    }
    $payload = substr($buffer, $offset, $length);
    $buffer = (string)substr($buffer, $offset + $length);
    if ($masked) {
        $decoded = '';
        for ($i = 0; $i < $length; $i++) {
            $decoded .= $payload[$i] ^ $mask[$i % 4];
        }
        $payload = $decoded;
    }
    return ['opcode' => $opcode, 'payload' => $payload];
}

function sh_ws_dispatch_control(string $userEmail, string $deviceId, string $controlId, $value): array {
    $host = sh_local_ws_host();
    $port = sh_local_ws_port();
    $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 2);
    if (!$socket) {
        throw new RuntimeException("Local WebSocket unavailable: {$errstr} ({$errno})");
    }
    stream_set_timeout($socket, 2);
    $key = base64_encode(random_bytes(16));
    $request = "GET / HTTP/1.1\r\n"
        . "Host: {$host}:{$port}\r\n"
        . "Upgrade: websocket\r\n"
        . "Connection: Upgrade\r\n"
        . "Sec-WebSocket-Version: 13\r\n"
        . "Sec-WebSocket-Key: {$key}\r\n\r\n";
    fwrite($socket, $request);
    $response = '';
    while (!str_contains($response, "\r\n\r\n")) {
        $chunk = fread($socket, 1024);
        if ($chunk === false || $chunk === '') {
            break;
        }
        $response .= $chunk;
    }
    if (!str_starts_with($response, 'HTTP/1.1 101')) {
        fclose($socket);
        throw new RuntimeException('Local WebSocket handshake failed');
    }
    $accept = null;
    foreach (explode("\r\n", trim($response)) as $line) {
        if (stripos($line, 'Sec-WebSocket-Accept:') === 0) {
            $accept = trim(substr($line, strlen('Sec-WebSocket-Accept:')));
            break;
        }
    }
    $expectedAccept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    if ($accept !== $expectedAccept) {
        fclose($socket);
        throw new RuntimeException('Invalid WebSocket accept header');
    }

    $issued = sh_store_dashboard_ws_token($userEmail, $deviceId);
    sh_ws_send_json($socket, ['type' => 'auth', 'role' => 'dashboard', 'session_token' => $issued['token']]);
    stream_set_blocking($socket, false);

    $connected = false;
    $ackDevice = null;
    $buffer = '';
    $deadline = microtime(true) + 3.0;
    $seenTypes = [];

    while (microtime(true) < $deadline) {
        $chunk = fread($socket, 8192);
        if ($chunk !== false && $chunk !== '') {
            $buffer .= $chunk;
        }
        while (($frame = sh_ws_extract_frame_from_buffer($buffer)) !== null) {
            if ($frame['opcode'] === 0x8) {
                break 2;
            }
            if ($frame['opcode'] === 0x9) {
                fwrite($socket, sh_ws_encode_frame($frame['payload'], 0xA));
                continue;
            }
            if ($frame['opcode'] !== 0x1) {
                continue;
            }
            $msg = json_decode($frame['payload'], true);
            if (!is_array($msg)) {
                continue;
            }
            $seenTypes[] = (string)($msg['type'] ?? '?');
            if (($msg['type'] ?? '') === 'error') {
                fclose($socket);
                throw new RuntimeException((string)($msg['message'] ?? 'WebSocket error'));
            }
            if (($msg['type'] ?? '') === 'connected') {
                $connected = true;
                sh_ws_send_json($socket, ['type' => 'control', 'control_id' => $controlId, 'value' => $value]);
                continue;
            }
            if (($msg['type'] ?? '') === 'ack') {
                $ackDevice = $msg['device'] ?? null;
                $matched = (int)($msg['matched'] ?? 0);
                if ($matched < 1) {
                    fwrite($socket, sh_ws_encode_frame('', 0x8));
                    fclose($socket);
                    throw new RuntimeException('Device tunnel not connected (matched=0)');
                }
                break 2;
            }
        }
        usleep(20000);
    }

    fwrite($socket, sh_ws_encode_frame('', 0x8));
    fclose($socket);

    if (!$connected) {
        throw new RuntimeException('Dashboard WebSocket auth timeout; seen=' . implode(',', $seenTypes) . '; buffered=' . strlen($buffer));
    }
    if (!is_array($ackDevice)) {
        throw new RuntimeException('Device did not acknowledge control command; seen=' . implode(',', $seenTypes) . '; buffered=' . strlen($buffer));
    }
    return $ackDevice;
}

function sh_ws_route_request(string $ownerEmail, string $deviceId, string $kind, array $payload): array {
    $host = sh_local_ws_host();
    $port = sh_local_ws_port();
    $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 2);
    if (!$socket) {
        throw new RuntimeException("Local WebSocket unavailable: {$errstr} ({$errno})");
    }
    stream_set_timeout($socket, 2);
    $key = base64_encode(random_bytes(16));
    $request = "GET / HTTP/1.1\r\n"
        . "Host: {$host}:{$port}\r\n"
        . "Upgrade: websocket\r\n"
        . "Connection: Upgrade\r\n"
        . "Sec-WebSocket-Version: 13\r\n"
        . "Sec-WebSocket-Key: {$key}\r\n\r\n";
    fwrite($socket, $request);
    $response = '';
    while (!str_contains($response, "\r\n\r\n")) {
        $chunk = fread($socket, 1024);
        if ($chunk === false || $chunk === '') break;
        $response .= $chunk;
    }
    if (!str_starts_with($response, 'HTTP/1.1 101')) {
        fclose($socket);
        throw new RuntimeException('Local WebSocket handshake failed');
    }

    $issued = sh_store_dashboard_ws_token($ownerEmail, $deviceId);
    sh_ws_send_json($socket, ['type' => 'auth', 'role' => 'dashboard', 'session_token' => $issued['token']]);
    stream_set_blocking($socket, false);

    $requestId = bin2hex(random_bytes(8));
    $payload['type'] = $kind;
    $payload['request_id'] = $requestId;
    $buffer = '';
    $connected = false;
    $files = [];
    $html = '';
    $meta = [];
    $deadline = microtime(true) + ($kind === 'route_file' ? 60.0 : 8.0);
    $seenTypes = [];

    while (microtime(true) < $deadline) {
        $chunk = fread($socket, 8192);
        if ($chunk !== false && $chunk !== '') $buffer .= $chunk;
        while (($frame = sh_ws_extract_frame_from_buffer($buffer)) !== null) {
            if ($frame['opcode'] === 0x8) break 2;
            if ($frame['opcode'] === 0x9) {
                fwrite($socket, sh_ws_encode_frame($frame['payload'], 0xA));
                continue;
            }
            if ($frame['opcode'] !== 0x1) continue;
            $msg = json_decode($frame['payload'], true);
            if (!is_array($msg)) continue;
            $type = (string)($msg['type'] ?? '?');
            $seenTypes[] = $type;
            if ($type === 'error' || $type === 'route_error') {
                fclose($socket);
                throw new RuntimeException((string)($msg['message'] ?? $msg['error'] ?? 'WebSocket error'));
            }
            if ($type === 'connected') {
                $connected = true;
                sh_ws_send_json($socket, $payload);
                continue;
            }
            if (($msg['request_id'] ?? '') !== $requestId) continue;
            if ($type === 'route_list_result') {
                foreach (($msg['files'] ?? []) as $file) {
                    if (is_array($file)) $files[] = $file;
                }
                continue;
            }
            if ($type === 'route_list_end') {
                fwrite($socket, sh_ws_encode_frame('', 0x8));
                fclose($socket);
                return ['ok'=>true, 'files'=>$files];
            }
            if ($type === 'route_file_begin') {
                $meta = $msg;
                continue;
            }
            if ($type === 'route_file_chunk') {
                $html .= (string)($msg['data'] ?? '');
                continue;
            }
            if ($type === 'route_file_end') {
                fwrite($socket, sh_ws_encode_frame('', 0x8));
                fclose($socket);
                return ['ok'=>true, 'html'=>$html, 'meta'=>$meta];
            }
            if ($type === 'route_file_error') {
                fclose($socket);
                throw new RuntimeException((string)($msg['error'] ?? 'Route file error'));
            }
            if ($type === 'route_action_result') {
                fwrite($socket, sh_ws_encode_frame('', 0x8));
                fclose($socket);
                return $msg;
            }
        }
        usleep(15000);
    }

    fwrite($socket, sh_ws_encode_frame('', 0x8));
    fclose($socket);
    if (!$connected) {
        throw new RuntimeException('Dashboard WebSocket auth timeout; seen=' . implode(',', $seenTypes));
    }
    throw new RuntimeException('Route tunnel response timeout; seen=' . implode(',', $seenTypes));
}

function sh_route_maps_page(string $deviceId, array $files): string {
    $base = strtok($_SERVER['REQUEST_URI'] ?? '/apps/smart_home/', '?') ?: '/apps/smart_home/';
    usort($files, fn($a, $b) => strcmp((string)($b['name'] ?? ''), (string)($a['name'] ?? '')));
    $safeFiles = [];
    foreach ($files as $f) {
        $name = (string)($f['name'] ?? '');
        if ($name === '') continue;
        $safeFiles[] = [
            'name' => $name,
            'date' => (string)($f['date'] ?? substr($name, 0, strpos($name, '_') ?: 10)),
            'size' => (int)($f['size'] ?? 0),
            'url' => $base . '?api=route_map_file&device_id=' . rawurlencode($deviceId) . '&name=' . rawurlencode($name),
            'download' => $base . '?api=route_map_file&download=1&device_id=' . rawurlencode($deviceId) . '&name=' . rawurlencode($name),
        ];
    }
    $json = json_encode($safeFiles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $html = "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>Route maps</title>";
    $html .= "<style>html,body{height:100%;overflow:hidden}body{margin:0;background:#101418;color:#e7edf2;font-family:Arial,sans-serif}.wrap{display:grid;grid-template-columns:340px 1fr;height:100dvh}.side{background:#151b22;border-right:1px solid #2b3540;display:flex;flex-direction:column;min-height:0}.head{padding:14px;border-bottom:1px solid #2b3540}.title{font-size:18px;margin:0 0 12px}.top{display:flex;gap:8px;margin-bottom:10px}.top a,.top button,.actions a,.actions button{padding:8px 10px;border:1px solid #3a4a59;border-radius:5px;background:#263544;color:#edf5fb;text-decoration:none;cursor:pointer}.search{width:100%;box-sizing:border-box;padding:10px;border:1px solid #3a4a59;border-radius:5px;background:#101820;color:#edf5fb}.list{overflow:auto;padding:8px 10px 16px;min-height:0}.group{border-top:1px solid #2b3540;padding:10px 0}.group h2{font-size:13px;margin:0 0 8px;color:#9fb0bf}.tile{display:block;width:100%;margin:6px 0;padding:10px;border:1px solid #3a4a59;border-radius:6px;background:#1a2430;color:#edf5fb;text-align:left;cursor:pointer}.tile.active{background:#1f6f45;border-color:#2ea865}.meta{font-size:11px;color:#9fb0bf;margin-top:3px}.actions{display:flex;gap:6px;flex-wrap:wrap;padding:10px 14px;border-top:1px solid #2b3540}.viewer{height:100dvh;background:#fff}.viewer iframe{width:100%;height:100%;border:0;display:block}.empty{padding:24px;color:#9fb0bf}@media(max-width:820px){html,body{overflow:auto}.wrap{display:block;height:auto}.side{height:48dvh}.viewer{height:52dvh}}</style></head><body>";
    $html .= "<div class='wrap'><aside class='side'><div class='head'><h1 class='title'>Route maps</h1><div class='top'><a href='" . htmlspecialchars($base, ENT_QUOTES) . "'>Home</a><button onclick='refreshList()'>Refresh</button></div><input id='q' class='search' placeholder='Search date or place' oninput='renderList()'></div><div id='list' class='list'></div><div class='actions'><a id='downloadBtn' href='#'>Download</a><button onclick='renameCurrent()'>Rename</button><button onclick='deleteCurrent()'>Delete</button></div></aside><main class='viewer'><iframe id='previewFrame'></iframe></main></div>";
    $html .= "<script>const DEVICE_ID=" . json_encode($deviceId) . ",BASE=" . json_encode($base) . ",FILES=" . ($json ?: '[]') . ";let current=null;function esc(s){return String(s||'').replace(/[&<>\"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',\"'\":'&#39;'}[m]));}function grouped(list){let g={};list.forEach(f=>{(g[f.date]||(g[f.date]=[])).push(f)});return g}function renderList(){let term=q.value.toLowerCase();let list=FILES.filter(f=>(f.name+' '+f.date).toLowerCase().includes(term));let g=grouped(list),h='';Object.keys(g).sort().reverse().forEach(d=>{h+='<div class=group><h2>'+esc(d)+'</h2>';g[d].forEach((f,i)=>{h+='<button class=\"tile '+(current&&current.name===f.name?'active':'')+'\" onclick=\"openFile(\\''+esc(f.name)+'\\')\"><b>'+esc(f.name)+'</b><div class=meta>'+Math.round((f.size||0)/1024)+' KB</div></button>';});h+='</div>';});listEl=document.getElementById('list');listEl.innerHTML=h||'<div class=empty>No maps found</div>';}function openFile(name){current=FILES.find(f=>f.name===name);if(!current)return;previewFrame.src=current.url;downloadBtn.href=current.download;renderList();}async function post(action,payload){let r=await fetch(BASE+'?api='+action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({device_id:DEVICE_ID},payload))});let j=await r.json();if(!j.ok)throw new Error((j.result&&j.result.error)||j.error||'Failed');return j;}async function deleteCurrent(){if(!current||!confirm('Delete '+current.name+'?'))return;await post('route_delete',{name:current.name});location.reload();}async function renameCurrent(){if(!current)return;let n=prompt('New file name',current.name);if(!n||n===current.name)return;if(!n.endsWith('.html'))n+='.html';await post('route_rename',{old_name:current.name,new_name:n});location.reload();}function refreshList(){location.reload();}window.addEventListener('load',()=>{renderList();if(FILES[0])openFile(FILES[0].name);});</script></body></html>";
    return $html;
}

function sh_templates(): array {
    return [
        'generic_sensor' => [
            'icon' => 'sensor',
            'controls' => [['id'=>'telemetry_enabled','label'=>'Telemetry','type'=>'switch','value'=>true,'primary'=>true],['id'=>'sample_interval','label'=>'Interval','type'=>'range','value'=>30,'min'=>5,'max'=>300,'step'=>5,'primary'=>false]],
            'params' => [['id'=>'temperature','label'=>'Temperature','value'=>'22.6 C'],['id'=>'humidity','label'=>'Humidity','value'=>'48 %'],['id'=>'signal','label'=>'Wi-Fi RSSI','value'=>'-63 dBm']],
            'files' => [['id'=>'config','label'=>'server-config.json','kind'=>'config'],['id'=>'topics','label'=>'mqtt-topics.json','kind'=>'config']],
        ],
        'smart_torch' => [
            'icon' => 'torch',
            'controls' => [['id'=>'relay_1','gp_id'=>'switch_1','label'=>'Main lamp','type'=>'switch','value'=>false,'primary'=>true],['id'=>'relay_2','gp_id'=>'switch_2','label'=>'Small lamp','type'=>'switch','value'=>false,'primary'=>true]],
            'params' => [['id'=>'firmware','label'=>'Firmware','value'=>'smart_torch.ino'],['id'=>'ap_domain','label'=>'AP domain','value'=>'serverkherson.xyz'],['id'=>'mqtt_transport','label'=>'Transport','value'=>'MQTT over WSS']],
            'files' => [['id'=>'config','label'=>'server-config.json','kind'=>'config'],['id'=>'topics','label'=>'mqtt-topics.json','kind'=>'config'],['id'=>'readme','label'=>'README.txt','kind'=>'text']],
        ],
        'smart_lamp' => [
            'icon' => 'lamp',
            'controls' => [['id'=>'power','label'=>'Power','type'=>'switch','value'=>true,'primary'=>true],['id'=>'brightness','label'=>'Brightness','type'=>'range','value'=>12,'min'=>0,'max'=>20,'step'=>1,'primary'=>true],['id'=>'mode','label'=>'Mode','type'=>'select','value'=>'breathing','options'=>['warm','cool','rainbow','breathing','meteor'],'primary'=>true]],
            'params' => [['id'=>'firmware','label'=>'Firmware','value'=>'lamp_esp.ino'],['id'=>'ntp','label'=>'NTP','value'=>'pool.ntp.org']],
            'files' => [['id'=>'config','label'=>'server-config.json','kind'=>'config'],['id'=>'readme','label'=>'README.txt','kind'=>'text']],
        ],
        'smart_socket' => [
            'icon' => 'smart-plug',
            'controls' => [['id'=>'power','label'=>'Power','type'=>'switch','value'=>false,'primary'=>true],['id'=>'auto_off_minutes','label'=>'Auto off','type'=>'range','value'=>15,'min'=>0,'max'=>180,'step'=>5,'primary'=>false]],
            'params' => [['id'=>'load','label'=>'Load','value'=>'0.0 W'],['id'=>'voltage','label'=>'Voltage','value'=>'230 V']],
            'files' => [['id'=>'config','label'=>'server-config.json','kind'=>'config'],['id'=>'topics','label'=>'mqtt-topics.json','kind'=>'config']],
        ],
        'sonoff_relay' => [
            'icon' => 'light-bulb',
            'controls' => [['id'=>'relay_1','label'=>'Relay','type'=>'switch','value'=>false,'primary'=>true]],
            'params' => [['id'=>'firmware','label'=>'Firmware','value'=>'sonoff.ino'],['id'=>'ip','label'=>'IP','value'=>'-']],
            'files' => [['id'=>'config','label'=>'server-config.json','kind'=>'config']],
        ],
        'pending' => [
            'icon' => 'smart-device',
            'controls' => [],
            'params' => [],
            'files' => [],
        ],
    ];
}

function sh_auto_mqtt(string $name, string $id): string {
    return 'devices/' . strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $name), '-')) . '-' . substr($id, -6);
}

// ─── API Handlers ───────────────────────────────────────────────────────────────
$userEmail = sh_user_email();
$db = sh_db();
sh_migrate_json_runtime_data($db);
require_once dirname(SH_APP_DIR, 2) . '/project/i18n_helpers.php';
$lang = isset($_GET['lang']) && ap_i18n_lang_exists_in_dir(SH_APP_DIR, $_GET['lang']) ? ap_normalize_i18n_lang($_GET['lang']) : 'en';
$themeRaw = strtolower((string)($_GET['theme'] ?? 'dark'));
$theme = $themeRaw === 'light' ? 'light' : 'dark';
$color = trim((string)($_GET['color'] ?? '#59b300'));

// Load user settings from DB
$settings = sh_load_user_settings($db, $userEmail);
$canEditEndpoints = sh_can_edit_endpoints();
$homeCtx = sh_resolve_home_context($db, $userEmail, $settings);

// API routing
$action = $_GET['api'] ?? $_POST['api'] ?? '';
if ($action !== '') {
    header('Content-Type: application/json; charset=UTF-8');
    $raw = file_get_contents('php://input');
    $jsonPayload = json_decode(is_string($raw) && $raw !== '' ? $raw : '', true);
    $payload = is_array($jsonPayload) ? $jsonPayload : $_POST;

    try {
        if ($action === 'route_maps') {
            $deviceId = trim((string)($_GET['device_id'] ?? $payload['device_id'] ?? ''));
            $row = sh_device_row_for_user($db, $deviceId, $userEmail);
            if (!$row) { http_response_code(404); header('Content-Type: text/plain; charset=UTF-8'); echo 'Device not found'; exit; }
            $ownerEmail = (string)($row['user_email'] ?? $userEmail);
            $result = sh_ws_route_request($ownerEmail, $deviceId, 'route_list', []);
            header('Content-Type: text/html; charset=UTF-8');
            echo sh_route_maps_page($deviceId, $result['files'] ?? []);
            exit;
        }

        if ($action === 'route_map_file') {
            $deviceId = trim((string)($_GET['device_id'] ?? $payload['device_id'] ?? ''));
            $name = trim((string)($_GET['name'] ?? $payload['name'] ?? ''));
            if ($name === '' || str_contains($name, '/') || !str_ends_with($name, '.html')) {
                http_response_code(400); header('Content-Type: text/plain; charset=UTF-8'); echo 'Invalid route file'; exit;
            }
            $row = sh_device_row_for_user($db, $deviceId, $userEmail);
            if (!$row) { http_response_code(404); header('Content-Type: text/plain; charset=UTF-8'); echo 'Device not found'; exit; }
            $ownerEmail = (string)($row['user_email'] ?? $userEmail);
            $result = sh_ws_route_request($ownerEmail, $deviceId, 'route_file', ['name'=>$name]);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store');
            if (!empty($_GET['download']) || !empty($payload['download'])) {
                header('Content-Disposition: attachment; filename="' . basename($name) . '"');
            }
            echo (string)($result['html'] ?? '');
            exit;
        }

        if ($action === 'route_delete') {
            $deviceId = trim((string)($payload['device_id'] ?? $_GET['device_id'] ?? ''));
            $name = trim((string)($payload['name'] ?? $_GET['name'] ?? ''));
            $row = sh_device_row_for_user($db, $deviceId, $userEmail);
            if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Device not found']); exit; }
            $ownerEmail = (string)($row['user_email'] ?? $userEmail);
            $result = sh_ws_route_request($ownerEmail, $deviceId, 'route_delete', ['name'=>$name]);
            echo json_encode(['ok'=>!empty($result['ok']), 'result'=>$result], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'route_rename') {
            $deviceId = trim((string)($payload['device_id'] ?? $_GET['device_id'] ?? ''));
            $oldName = trim((string)($payload['old_name'] ?? $_GET['old_name'] ?? ''));
            $newName = trim((string)($payload['new_name'] ?? $_GET['new_name'] ?? ''));
            $row = sh_device_row_for_user($db, $deviceId, $userEmail);
            if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Device not found']); exit; }
            $ownerEmail = (string)($row['user_email'] ?? $userEmail);
            $result = sh_ws_route_request($ownerEmail, $deviceId, 'route_rename', ['old_name'=>$oldName, 'new_name'=>$newName]);
            echo json_encode(['ok'=>!empty($result['ok']), 'result'=>$result], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'state') {
            $settings = sh_load_user_settings($db, $userEmail);
            $homeCtx = sh_resolve_home_context($db, $userEmail, $settings);
            $devices = sh_fetch_scoped_devices($db, $userEmail, $homeCtx);
            $myDevices = sh_fetch_my_devices($db, $userEmail);
            $floorPlan = sh_fetch_floor_plan($db, $homeCtx['floor_owner']);
            $groups = [];
            $gr = $db->prepare('SELECT g.*, gm.role as member_role FROM smarthome_groups g LEFT JOIN smarthome_group_members gm ON gm.group_id = g.id AND gm.member_email = :em WHERE g.user_email = :em2 OR gm.member_email = :em3 ORDER BY g.name');
            $gr->bindValue(':em', $userEmail, SQLITE3_TEXT);
            $gr->bindValue(':em2', $userEmail, SQLITE3_TEXT);
            $gr->bindValue(':em3', $userEmail, SQLITE3_TEXT);
            $gres = $gr->execute();
            $seen = [];
            while ($grow = $gres->fetchArray(SQLITE3_ASSOC)) {
                if (!isset($seen[$grow['id']])) {
                    $seen[$grow['id']] = true;
                    $grow['member_role'] = $grow['member_role'] ?? ($grow['user_email'] === $userEmail ? 'admin' : null);
                    $groups[] = $grow;
                }
            }
            $groups = sh_enrich_groups_list($db, $groups, $userEmail);
            echo json_encode([
                'ok'=>true,
                'settings'=>$settings,
                'devices'=>$devices,
                'my_devices'=>$myDevices,
                'groups'=>$groups,
                'floor_plan'=>$floorPlan,
                'home_scope'=>$homeCtx['scope'],
                'home_group_id'=>$homeCtx['group_id'],
                'can_edit_map'=>$homeCtx['can_edit_map'],
                'can_edit_endpoints'=>sh_can_edit_endpoints(),
                'service_role'=>sh_user_service_role(),
                'api_docs'=>sh_api_docs_markdown((string)($_SERVER['HTTP_HOST']??'serverkherson.xyz')),
            ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'save_floor_plan') {
            $settings = sh_load_user_settings($db, $userEmail);
            $homeCtx = sh_resolve_home_context($db, $userEmail, $settings);
            if (!$homeCtx['can_edit_map']) {
                echo json_encode(['ok'=>false,'error'=>'No permission to edit map'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                exit;
            }
            $elements = $payload['elements'] ?? [];
            $owner = $homeCtx['floor_owner'];
            $stmt = $db->prepare("INSERT OR REPLACE INTO smarthome_floor_plans (user_email, elements_json, updated_at) VALUES (:em, :el, datetime('now','localtime'))");
            $stmt->bindValue(':em', $owner, SQLITE3_TEXT);
            $stmt->bindValue(':el', json_encode($elements, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
            $stmt->execute();
            echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'update_displayed_controls') {
            $deviceId = trim((string)($payload['device_id'] ?? ''));
            if (!sh_device_can_configure($db, $deviceId, $userEmail)) {
                echo json_encode(['ok'=>false,'error'=>'No permission']); exit;
            }
            $displayed = $payload['displayed_controls'] ?? [];
            $upd = $db->prepare('UPDATE smarthome_devices SET displayed_controls=:dc WHERE id=:id');
            $upd->bindValue(':dc', json_encode($displayed, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
            $upd->bindValue(':id', $deviceId, SQLITE3_TEXT);
            $upd->execute();
            echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'save_settings') {
            $cur = sh_load_user_settings($db, $userEmail);
            $sd = trim((string)($cur['server_domain'] ?? 'serverkherson.xyz')) ?: 'serverkherson.xyz';
            $ad = trim((string)($cur['ap_domain'] ?? 'serverkherson.xyz')) ?: 'serverkherson.xyz';
            if (sh_can_edit_endpoints()) {
                $sd = trim((string)($payload['server_domain'] ?? $sd)) ?: 'serverkherson.xyz';
                $ad = trim((string)($payload['ap_domain'] ?? $ad)) ?: 'serverkherson.xyz';
            }
            $homeScope = (($payload['home_scope'] ?? $cur['home_scope'] ?? 'own') === 'group') ? 'group' : 'own';
            $homeGroupId = trim((string)($payload['home_group_id'] ?? $cur['home_group_id'] ?? ''));
            if ($homeScope === 'group') {
                if ($homeGroupId === '' || !sh_user_in_group($db, $homeGroupId, $userEmail)) {
                    echo json_encode(['ok'=>false,'error'=>'Invalid group'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                    exit;
                }
            } else {
                $homeGroupId = '';
            }
            $homeView = (($payload['home_view'] ?? $cur['home_view'] ?? 'tiles') === 'map') ? 'map' : 'tiles';
            $stmt = $db->prepare('INSERT OR REPLACE INTO smarthome_settings (user_email, server_domain, ap_domain, home_scope, home_group_id, home_view, updated_at) VALUES (:em, :sd, :ad, :hs, :hg, :hv, datetime(\'now\',\'localtime\'))');
            $stmt->bindValue(':em', $userEmail, SQLITE3_TEXT);
            $stmt->bindValue(':sd', $sd, SQLITE3_TEXT);
            $stmt->bindValue(':ad', $ad, SQLITE3_TEXT);
            $stmt->bindValue(':hs', $homeScope, SQLITE3_TEXT);
            $stmt->bindValue(':hg', $homeGroupId, SQLITE3_TEXT);
            $stmt->bindValue(':hv', $homeView, SQLITE3_TEXT);
            $stmt->execute();
            $saved = sh_load_user_settings($db, $userEmail);
            echo json_encode(['ok'=>true, 'settings'=>$saved, 'can_edit_endpoints'=>sh_can_edit_endpoints()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'save_device') {
            $deviceId = trim((string)($payload['id'] ?? ''));
            if ($deviceId === '') {
                $token = trim((string)($payload['device_token'] ?? ''));
                if ($token === '') {
                    echo json_encode(['ok'=>false,'error'=>'Device token required'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                    exit;
                }
                $ti = $db->prepare('SELECT * FROM smarthome_token_index WHERE token = :tok');
                $ti->bindValue(':tok', $token, SQLITE3_TEXT);
                $tiRow = $ti->execute()->fetchArray(SQLITE3_ASSOC);
                if ($tiRow) {
                    if (($tiRow['user_email'] ?? '') !== $userEmail) {
                        echo json_encode(['ok'=>false,'error'=>'Token already registered'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                        exit;
                    }
                    $settings = sh_load_user_settings($db, $userEmail);
                    $homeCtx = sh_resolve_home_context($db, $userEmail, $settings);
                    echo json_encode([
                        'ok'=>true,
                        'devices'=>sh_fetch_scoped_devices($db, $userEmail, $homeCtx),
                        'my_devices'=>sh_fetch_my_devices($db, $userEmail),
                    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                    exit;
                }
                $type = trim((string)($payload['type'] ?? ''));
                if ($type === '') {
                    $type = 'pending';
                }
                $name = trim((string)($payload['name'] ?? '')) ?: 'Device';
                $room = trim((string)($payload['room'] ?? 'Living room'));
                $slug = sh_slug($name . '-' . substr(bin2hex(random_bytes(4)), 0, 6));
                $tpl = (sh_templates())[$type] ?? (sh_templates())['pending'];
                $now = gmdate('c');
                $mqtt = sh_auto_mqtt($name, $slug);
                $category = trim((string)($payload['category'] ?? ''));
                $stmt = $db->prepare("INSERT INTO smarthome_devices (id, user_email, name, type, room, status, local_url, server_domain, ap_domain, mqtt_topic, device_token, notes, icon, category, controls, params, files, displayed_params, created_at, updated_at) VALUES (:id, :em, :name, :type, :room, 'unknown', :local, :sd, :ad, :mqtt, :token, :notes, :icon, :cat, :ctrls, :params, :files, '[]', :now, :now)");
                $stmt->bindValue(':id', $slug, SQLITE3_TEXT);
                $stmt->bindValue(':em', $userEmail, SQLITE3_TEXT);
                $stmt->bindValue(':name', $name, SQLITE3_TEXT);
                $stmt->bindValue(':type', $type, SQLITE3_TEXT);
                $stmt->bindValue(':room', $room, SQLITE3_TEXT);
                $stmt->bindValue(':local', '', SQLITE3_TEXT);
                $stmt->bindValue(':sd', trim((string)($payload['server_domain'] ?? $settings['server_domain'])) ?: 'serverkherson.xyz', SQLITE3_TEXT);
                $stmt->bindValue(':ad', trim((string)($payload['ap_domain'] ?? $settings['ap_domain'])) ?: 'serverkherson.xyz', SQLITE3_TEXT);
                $stmt->bindValue(':mqtt', $mqtt, SQLITE3_TEXT);
                $stmt->bindValue(':token', $token, SQLITE3_TEXT);
                $stmt->bindValue(':notes', trim((string)($payload['notes'] ?? '')), SQLITE3_TEXT);
                $stmt->bindValue(':icon', $tpl['icon'], SQLITE3_TEXT);
                $stmt->bindValue(':cat', $category, SQLITE3_TEXT);
                $stmt->bindValue(':ctrls', json_encode($tpl['controls'], JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
                $stmt->bindValue(':params', json_encode($tpl['params'], JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
                $stmt->bindValue(':files', json_encode($tpl['files'], JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
                $stmt->bindValue(':now', $now, SQLITE3_TEXT);
                $stmt->execute();
                $gidNew = trim((string)($payload['group_id'] ?? ''));
                if ($gidNew !== '' && sh_user_can_assign_group($db, $gidNew, $userEmail)) {
                    $gu = $db->prepare('UPDATE smarthome_devices SET group_id=:gid WHERE id=:id AND user_email=:em');
                    $gu->bindValue(':gid', $gidNew, SQLITE3_TEXT);
                    $gu->bindValue(':id', $slug, SQLITE3_TEXT);
                    $gu->bindValue(':em', $userEmail, SQLITE3_TEXT);
                    $gu->execute();
                }
                // Add token index
                $ti = $db->prepare('INSERT OR REPLACE INTO smarthome_token_index (token, user_email, device_id, updated_at) VALUES (:tok, :em, :did, :now)');
                $ti->bindValue(':tok', $token, SQLITE3_TEXT);
                $ti->bindValue(':em', $userEmail, SQLITE3_TEXT);
                $ti->bindValue(':did', $slug, SQLITE3_TEXT);
                $ti->bindValue(':now', $now, SQLITE3_TEXT);
                $ti->execute();
                // Log
                sh_monitor_log($userEmail, $slug, 'device_created', ['name'=>$name, 'type'=>$type]);
            } else {
                if (!sh_device_can_configure($db, $deviceId, $userEmail)) {
                    echo json_encode(['ok'=>false,'error'=>'No permission'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                    exit;
                }
                $stmt = $db->prepare("UPDATE smarthome_devices SET name=:name, room=:room, local_url=:local, server_domain=:sd, ap_domain=:ad, mqtt_topic=:mqtt, device_token=:token, notes=:notes, icon=:icon, icon_locked=1, category=:cat, status=:status, updated_at=datetime('now','localtime') WHERE id=:id AND user_email=:em");
                $stmt->bindValue(':id', $deviceId, SQLITE3_TEXT);
                $stmt->bindValue(':em', $userEmail, SQLITE3_TEXT);
                $stmt->bindValue(':name', trim((string)($payload['name'] ?? '')), SQLITE3_TEXT);
                $stmt->bindValue(':room', trim((string)($payload['room'] ?? '')), SQLITE3_TEXT);
                $stmt->bindValue(':local', trim((string)($payload['local_url'] ?? '')), SQLITE3_TEXT);
                $stmt->bindValue(':sd', trim((string)($payload['server_domain'] ?? $settings['server_domain'])) ?: 'serverkherson.xyz', SQLITE3_TEXT);
                $stmt->bindValue(':ad', trim((string)($payload['ap_domain'] ?? $settings['ap_domain'])) ?: 'serverkherson.xyz', SQLITE3_TEXT);
                $stmt->bindValue(':mqtt', trim((string)($payload['mqtt_topic'] ?? '')), SQLITE3_TEXT);
                $stmt->bindValue(':token', trim((string)($payload['device_token'] ?? '')), SQLITE3_TEXT);
                $stmt->bindValue(':notes', trim((string)($payload['notes'] ?? '')), SQLITE3_TEXT);
                $stmt->bindValue(':icon', trim((string)($payload['icon'] ?? 'smart-device')), SQLITE3_TEXT);
                $stmt->bindValue(':cat', trim((string)($payload['category'] ?? '')), SQLITE3_TEXT);
                $stmt->bindValue(':status', trim((string)($payload['status'] ?? 'unknown')), SQLITE3_TEXT);
                $stmt->execute();
                if (array_key_exists('group_id', $payload)) {
                    $gidSet = trim((string)($payload['group_id'] ?? ''));
                    if ($gidSet !== '' && !sh_user_can_assign_group($db, $gidSet, $userEmail)) {
                        echo json_encode(['ok'=>false,'error'=>'No permission for group'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                        exit;
                    }
                    $gu = $db->prepare('UPDATE smarthome_devices SET group_id=:gid WHERE id=:id AND user_email=:em');
                    $gu->bindValue(':gid', $gidSet, SQLITE3_TEXT);
                    $gu->bindValue(':id', $deviceId, SQLITE3_TEXT);
                    $gu->bindValue(':em', $userEmail, SQLITE3_TEXT);
                    $gu->execute();
                }
                // Update token index if token changed
                if (!empty($payload['device_token'])) {
                    $ti = $db->prepare('INSERT OR REPLACE INTO smarthome_token_index (token, user_email, device_id, updated_at) VALUES (:tok, :em, :did, datetime(\'now\',\'localtime\'))');
                    $ti->bindValue(':tok', trim((string)$payload['device_token']), SQLITE3_TEXT);
                    $ti->bindValue(':em', $userEmail, SQLITE3_TEXT);
                    $ti->bindValue(':did', $deviceId, SQLITE3_TEXT);
                    $ti->execute();
                }
            }
            $settings = sh_load_user_settings($db, $userEmail);
            $homeCtx = sh_resolve_home_context($db, $userEmail, $settings);
            echo json_encode([
                'ok'=>true,
                'devices'=>sh_fetch_scoped_devices($db, $userEmail, $homeCtx),
                'my_devices'=>sh_fetch_my_devices($db, $userEmail),
            ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'control') {
            $deviceId = trim((string)($payload['device_id'] ?? ''));
            $controlId = trim((string)($payload['control_id'] ?? ''));
            $devRow = sh_device_row_for_user($db, $deviceId, $userEmail);
            if (!$devRow) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Device not found']); exit; }
            $ownerEmail = (string)($devRow['user_email'] ?? $userEmail);
            $stmt = $db->prepare('SELECT controls, status FROM smarthome_devices WHERE id = :id');
            $stmt->bindValue(':id', $deviceId, SQLITE3_TEXT);
            $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Device not found']); exit; }
            $controls = sh_json_field($row['controls'] ?? '[]');
            $controlType = null;
            foreach ($controls as $c) {
                if (($c['id'] ?? '') === $controlId) {
                    $controlType = (string)($c['type'] ?? '');
                    break;
                }
            }
            if ($controlType === null) {
                http_response_code(400);
                echo json_encode(['ok'=>false,'error'=>'Unknown control'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                exit;
            }
            $value = $payload['value'] ?? null;
            if ($controlType === 'switch') $value = (bool)$value;
            elseif ($controlType === 'range') $value = (int)$value;
            elseif ($controlType === 'select') $value = (string)$value;
            $device = sh_update_device_control($db, $deviceId, $controlId, $value);
            if ($device === null) {
                http_response_code(404);
                echo json_encode(['ok'=>false,'error'=>'Device not found'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                exit;
            }
            if ($device === false) {
                http_response_code(400);
                echo json_encode(['ok'=>false,'error'=>'Unknown control'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                exit;
            }

            $requestId = sh_enqueue_control_request($ownerEmail, $deviceId, $controlId, $value);
            $delivery = sh_deliver_control($db, $ownerEmail, $deviceId, $controlId, $value, $device);
            $device['controls'] = $device['controls'] ?? [];
            $device['params'] = $device['params'] ?? [];
            $device['files'] = $device['files'] ?? [];
            $device['displayed_params'] = $device['displayed_params'] ?? [];
            sh_monitor_log($userEmail, $deviceId, 'control', [
                'device_name' => (string)($device['name'] ?? ''),
                'control_id' => $controlId,
                'value' => $value,
                'delivery' => $delivery,
            ]);
            echo json_encode([
                'ok'=>true,
                'queued'=>!$delivery['local'] && !$delivery['ws'],
                'delivered_local'=>$delivery['local'],
                'delivered_ws'=>$delivery['ws'],
                'request_id'=>$requestId,
                'device'=>$device,
                'device_status'=>(string)($row['status'] ?? 'unknown'),
            ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'update_displayed_params') {
            $deviceId = trim((string)($payload['device_id'] ?? ''));
            if (!sh_device_can_configure($db, $deviceId, $userEmail)) {
                echo json_encode(['ok'=>false,'error'=>'No permission']); exit;
            }
            $displayed = $payload['displayed_params'] ?? [];
            $upd = $db->prepare('UPDATE smarthome_devices SET displayed_params=:dp WHERE id=:id');
            $upd->bindValue(':dp', json_encode($displayed, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
            $upd->bindValue(':id', $deviceId, SQLITE3_TEXT);
            $upd->execute();
            echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'delete_device') {
            $deviceId = trim((string)($payload['device_id'] ?? ''));
            $db->exec('BEGIN IMMEDIATE');
            $st1 = $db->prepare('DELETE FROM smarthome_token_index WHERE device_id=:id');
            $st1->bindValue(':id', $deviceId, SQLITE3_TEXT);
            $st1->execute();
            $st2 = $db->prepare('DELETE FROM smarthome_devices WHERE id=:id AND user_email=:em');
            $st2->bindValue(':id', $deviceId, SQLITE3_TEXT);
            $st2->bindValue(':em', $userEmail, SQLITE3_TEXT);
            $st2->execute();
            $db->exec('COMMIT');
            sh_monitor_log($userEmail, $deviceId, 'device_deleted', []);
            $settings = sh_load_user_settings($db, $userEmail);
            $homeCtx = sh_resolve_home_context($db, $userEmail, $settings);
            echo json_encode([
                'ok'=>true,
                'devices'=>sh_fetch_scoped_devices($db, $userEmail, $homeCtx),
                'my_devices'=>sh_fetch_my_devices($db, $userEmail),
            ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'device_sync') {
            $token = trim((string)($payload['device_token'] ?? ''));
            if ($token === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Missing token']); exit; }
            $ti = $db->prepare('SELECT * FROM smarthome_token_index WHERE token = :tok');
            $ti->bindValue(':tok', $token, SQLITE3_TEXT);
            $tiRow = $ti->execute()->fetchArray(SQLITE3_ASSOC);
            if (!$tiRow) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Unknown token']); exit; }
            $devStmt = $db->prepare('SELECT * FROM smarthome_devices WHERE id = :id');
            $devStmt->bindValue(':id', $tiRow['device_id'], SQLITE3_TEXT);
            $device = $devStmt->execute()->fetchArray(SQLITE3_ASSOC);
            if (!$device) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Device not found']); exit; }
            $decodedDevice = sh_device_row_decode($device);
            if (!empty($payload['definition']) && is_array($payload['definition'])) {
                $decodedDevice = sh_apply_device_definition($decodedDevice, $payload['definition']);
                $defUpd = $db->prepare("UPDATE smarthome_devices SET type=:type, icon=:icon, controls=:ctrls, params=:params, files=:files, updated_at=datetime('now','localtime') WHERE id=:id");
                $defUpd->bindValue(':type', (string)($decodedDevice['type'] ?? ($device['type'] ?? 'generic_sensor')), SQLITE3_TEXT);
                $defUpd->bindValue(':icon', (string)($decodedDevice['icon'] ?? ($device['icon'] ?? 'smart-device')), SQLITE3_TEXT);
                $defUpd->bindValue(':ctrls', json_encode($decodedDevice['controls'] ?? [], JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
                $defUpd->bindValue(':params', json_encode($decodedDevice['params'] ?? [], JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
                $defUpd->bindValue(':files', json_encode($decodedDevice['files'] ?? [], JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
                $defUpd->bindValue(':id', $device['id'], SQLITE3_TEXT);
                $defUpd->execute();
                $device['type'] = $decodedDevice['type'] ?? ($device['type'] ?? 'generic_sensor');
                $device['icon'] = $decodedDevice['icon'] ?? ($device['icon'] ?? 'smart-device');
                $device['controls'] = json_encode($decodedDevice['controls'] ?? [], JSON_UNESCAPED_UNICODE);
                $device['params'] = json_encode($decodedDevice['params'] ?? [], JSON_UNESCAPED_UNICODE);
                $device['files'] = json_encode($decodedDevice['files'] ?? [], JSON_UNESCAPED_UNICODE);
            }
            // Update
            $upd = $db->prepare("UPDATE smarthome_devices SET status='online', updated_at=datetime('now','localtime'), local_url=COALESCE(NULLIF(:local,''), local_url), name=COALESCE(NULLIF(:name,''), name) WHERE id=:id");
            $upd->bindValue(':local', trim((string)($payload['local_url'] ?? '')), SQLITE3_TEXT);
            $upd->bindValue(':name', trim((string)($payload['name'] ?? '')), SQLITE3_TEXT);
            $upd->bindValue(':id', $device['id'], SQLITE3_TEXT);
            $upd->execute();
            // Update params from telemetry
            if (!empty($payload['telemetry']) && is_array($payload['telemetry'])) {
                $params = json_decode($device['params'] ?: '[]', true) ?: [];
                foreach ($params as &$p) {
                    if (array_key_exists($p['id'], $payload['telemetry'])) {
                        $p['value'] = (string)$payload['telemetry'][$p['id']];
                    }
                }
                unset($p);
                $pupd = $db->prepare("UPDATE smarthome_devices SET params=:params WHERE id=:id");
                $pupd->bindValue(':params', json_encode($params, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
                $pupd->bindValue(':id', $device['id'], SQLITE3_TEXT);
                $pupd->execute();
            }
            $deviceId = (string)$device['id'];
            $pendingIds = sh_pending_control_ids_for_device($db, $deviceId);
            if (!empty($payload['controls']) && is_array($payload['controls'])) {
                $controls = sh_json_field($device['controls'] ?? '[]');
                $changed = false;
                foreach ($controls as &$c) {
                    $cid = (string)($c['id'] ?? '');
                    if ($cid === '' || in_array($cid, $pendingIds, true)) continue;
                    if (!array_key_exists($cid, $payload['controls'])) continue;
                    $nv = sh_normalize_control_value($c, $payload['controls'][$cid]);
                    if ($c['value'] !== $nv) { $c['value'] = $nv; $changed = true; }
                }
                unset($c);
                if ($changed) {
                    $cupd = $db->prepare('UPDATE smarthome_devices SET controls=:ctrls, updated_at=datetime(\'now\',\'localtime\') WHERE id=:id');
                    $cupd->bindValue(':ctrls', json_encode($controls, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
                    $cupd->bindValue(':id', $deviceId, SQLITE3_TEXT);
                    $cupd->execute();
                }
            }
            sh_clear_control_queue_for_device($db, $deviceId);
            sh_trigger_ws_dispatch();
            sh_record_device_events($db, (string)$tiRow['user_email'], $deviceId, (string)($device['name'] ?? ''), $payload);
            $devStmt2 = $db->prepare('SELECT * FROM smarthome_devices WHERE id = :id');
            $devStmt2->bindValue(':id', $deviceId, SQLITE3_TEXT);
            $updatedDevice = $devStmt2->execute()->fetchArray(SQLITE3_ASSOC);
            $respControls = sh_controls_to_map(sh_json_field($updatedDevice['controls'] ?? '[]'));
            echo json_encode([
                'ok'=>true,
                'device_id'=>$updatedDevice['id'],
                'device_name'=>$updatedDevice['name'],
                'controls'=>$respControls,
                'apply_controls'=>true,
                'server_time'=>gmdate('c'),
            ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'issue_ws_token') {
            $deviceId = trim((string)($payload['device_id'] ?? ''));
            if (!sh_device_row_for_user($db, $deviceId, $userEmail)) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Not found']); exit; }
            $issued = sh_store_dashboard_ws_token($userEmail, $deviceId);
            echo json_encode(['ok'=>true, 'token'=>$issued['token'], 'ws_url'=>$issued['ws_url']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        // ─── Groups API ────────────────────────────────────────────────────────────
        if ($action === 'create_group') {
            $name = trim((string)($payload['name'] ?? ''));
            if ($name === '') { echo json_encode(['ok'=>false,'error'=>'Name required']); exit; }
            $id = sh_slug($name . '-' . substr(bin2hex(random_bytes(4)), 0, 6));
            $stmt = $db->prepare('INSERT INTO smarthome_groups (id, name, user_email) VALUES (:id, :name, :em)');
            $stmt->bindValue(':id', $id, SQLITE3_TEXT);
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':em', $userEmail, SQLITE3_TEXT);
            $stmt->execute();
            // Add creator as admin
            $stmt2 = $db->prepare('INSERT INTO smarthome_group_members (group_id, member_email, role) VALUES (:gid, :em, :role)');
            $stmt2->bindValue(':gid', $id, SQLITE3_TEXT);
            $stmt2->bindValue(':em', $userEmail, SQLITE3_TEXT);
            $stmt2->bindValue(':role', 'admin', SQLITE3_TEXT);
            $stmt2->execute();
            sh_monitor_log($userEmail, null, 'group_created', ['name'=>$name, 'group_id'=>$id]);
            echo json_encode(['ok'=>true, 'group'=>['id'=>$id, 'name'=>$name, 'user_email'=>$userEmail]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'delete_group') {
            $gid = trim((string)($payload['group_id'] ?? ''));
            $stmt = $db->prepare('DELETE FROM smarthome_groups WHERE id=:id AND user_email=:em');
            $stmt->bindValue(':id', $gid, SQLITE3_TEXT);
            $stmt->bindValue(':em', $userEmail, SQLITE3_TEXT);
            $stmt->execute();
            echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'group_members') {
            $gid = trim((string)($payload['group_id'] ?? ''));
            $acc = sh_group_access($db, $gid, $userEmail);
            if (!$acc) { echo json_encode(['ok'=>false,'error'=>'Group not found']); exit; }
            if (!$acc['can_see_members']) {
                echo json_encode(['ok'=>true, 'can_manage'=>false, 'owner_email'=>$acc['group']['user_email']??'', 'members'=>[]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                exit;
            }
            $ownerEmail = (string)($acc['group']['user_email'] ?? '');
            $members = [];
            $ownerName = '';
            $onStmt = $db->prepare('SELECT name FROM users WHERE email = :em LIMIT 1');
            $onStmt->bindValue(':em', $ownerEmail, SQLITE3_TEXT);
            $onRow = $onStmt->execute()->fetchArray(SQLITE3_ASSOC);
            if ($onRow) $ownerName = (string)($onRow['name'] ?? '');
            $members[] = ['member_email'=>$ownerEmail, 'member_name'=>$ownerName, 'role'=>'owner', 'is_owner'=>true, 'added_at'=>''];
            $stmt = $db->prepare('SELECT gm.*, u.name as member_name FROM smarthome_group_members gm LEFT JOIN users u ON u.email = gm.member_email WHERE gm.group_id = :gid AND gm.member_email != :owner ORDER BY gm.added_at');
            $stmt->bindValue(':gid', $gid, SQLITE3_TEXT);
            $stmt->bindValue(':owner', $ownerEmail, SQLITE3_TEXT);
            $res = $stmt->execute();
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                $row['is_owner'] = false;
                $members[] = $row;
            }
            echo json_encode(['ok'=>true, 'can_manage'=>$acc['can_manage'], 'owner_email'=>$ownerEmail, 'members'=>$members], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'add_member') {
            $gid = trim((string)($payload['group_id'] ?? ''));
            $acc = sh_group_access($db, $gid, $userEmail);
            if (!$acc || !$acc['can_manage']) { echo json_encode(['ok'=>false,'error'=>'No permission']); exit; }
            $memberEmail = trim((string)($payload['member_email'] ?? ''));
            $role = ($payload['role'] ?? 'guest') === 'admin' ? 'admin' : 'guest';
            if ($memberEmail === '') { echo json_encode(['ok'=>false,'error'=>'Email required']); exit; }
            $grow = $acc['group'];
            if (($grow['user_email'] ?? '') === $memberEmail) { echo json_encode(['ok'=>false,'error'=>'Already a member']); exit; }
            $userCheck = $db->prepare('SELECT email FROM users WHERE email = :em');
            $userCheck->bindValue(':em', $memberEmail, SQLITE3_TEXT);
            if (!$userCheck->execute()->fetchArray()) { echo json_encode(['ok'=>false,'error'=>'User not found']); exit; }
            // Check already member
            $mcheck = $db->prepare('SELECT * FROM smarthome_group_members WHERE group_id=:gid AND member_email=:em');
            $mcheck->bindValue(':gid', $gid, SQLITE3_TEXT);
            $mcheck->bindValue(':em', $memberEmail, SQLITE3_TEXT);
            if ($mcheck->execute()->fetchArray()) { echo json_encode(['ok'=>false,'error'=>'Already a member']); exit; }
            $stmt = $db->prepare('INSERT INTO smarthome_group_members (group_id, member_email, role) VALUES (:gid, :em, :role)');
            $stmt->bindValue(':gid', $gid, SQLITE3_TEXT);
            $stmt->bindValue(':em', $memberEmail, SQLITE3_TEXT);
            $stmt->bindValue(':role', $role, SQLITE3_TEXT);
            $stmt->execute();
            sh_monitor_log($userEmail, null, 'member_added', ['group_id'=>$gid, 'member_email'=>$memberEmail, 'role'=>$role]);
            echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'remove_member') {
            $gid = trim((string)($payload['group_id'] ?? ''));
            $acc = sh_group_access($db, $gid, $userEmail);
            if (!$acc || !$acc['can_manage']) { echo json_encode(['ok'=>false,'error'=>'No permission']); exit; }
            $memberEmail = trim((string)($payload['member_email'] ?? ''));
            $grow = $acc['group'];
            if (($grow['user_email'] ?? '') === $memberEmail) { echo json_encode(['ok'=>false,'error'=>'Cannot remove group creator']); exit; }
            $stmt = $db->prepare('DELETE FROM smarthome_group_members WHERE group_id=:gid AND member_email=:em');
            $stmt->bindValue(':gid', $gid, SQLITE3_TEXT);
            $stmt->bindValue(':em', $memberEmail, SQLITE3_TEXT);
            $stmt->execute();
            echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'update_member_role') {
            $gid = trim((string)($payload['group_id'] ?? ''));
            $acc = sh_group_access($db, $gid, $userEmail);
            if (!$acc || !$acc['can_manage']) { echo json_encode(['ok'=>false,'error'=>'No permission']); exit; }
            $memberEmail = trim((string)($payload['member_email'] ?? ''));
            $role = ($payload['role'] ?? 'guest') === 'admin' ? 'admin' : 'guest';
            $grow = $acc['group'];
            if (($grow['user_email'] ?? '') === $memberEmail) { echo json_encode(['ok'=>false,'error'=>'Cannot change creator role']); exit; }
            $stmt = $db->prepare('UPDATE smarthome_group_members SET role=:role WHERE group_id=:gid AND member_email=:em');
            $stmt->bindValue(':role', $role, SQLITE3_TEXT);
            $stmt->bindValue(':gid', $gid, SQLITE3_TEXT);
            $stmt->bindValue(':em', $memberEmail, SQLITE3_TEXT);
            $stmt->execute();
            echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        // ─── Monitor / Stats API ──────────────────────────────────────────────────
        if ($action === 'monitor_stats') {
            $period = (int)($payload['period'] ?? 24); // hours
            $devices = [];
            $r = $db->prepare('SELECT * FROM smarthome_devices WHERE user_email = :em');
            $r->bindValue(':em', $userEmail, SQLITE3_TEXT);
            $res = $r->execute();
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                $row['params'] = json_decode($row['params'] ?: '[]', true) ?: [];
                $devices[] = $row;
            }
            // Get recent events
            $events = [];
            $estmt = $db->prepare("SELECT * FROM smarthome_monitor_logs WHERE user_email = :em AND created_at >= datetime('now', '-' || :period || ' hours') ORDER BY created_at DESC LIMIT 200");
            $estmt->bindValue(':em', $userEmail, SQLITE3_TEXT);
            $estmt->bindValue(':period', $period, SQLITE3_TEXT);
            $eres = $estmt->execute();
            while ($erow = $eres->fetchArray(SQLITE3_ASSOC)) {
                $edata = json_decode($erow['event_data'] ?? '{}', true) ?: [];
                $erow['device_name'] = (string)($edata['device_name'] ?? '');
                $erow['event_label'] = sh_event_label((string)($erow['event_type'] ?? ''), $edata);
                $events[] = $erow;
            }
            // Get param history for devices
            $paramHistory = [];
            foreach ($devices as $d) {
                $ph = $db->prepare("SELECT * FROM smarthome_params_history WHERE device_id = :did AND recorded_at >= datetime('now', '-' || :period || ' hours') ORDER BY recorded_at DESC LIMIT 100");
                $ph->bindValue(':did', $d['id'], SQLITE3_TEXT);
                $ph->bindValue(':period', $period, SQLITE3_TEXT);
                $phres = $ph->execute();
                while ($phrow = $phres->fetchArray(SQLITE3_ASSOC)) $paramHistory[] = $phrow;
            }
            echo json_encode(['ok'=>true, 'devices'=>$devices, 'events'=>$events, 'paramHistory'=>$paramHistory], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'monitor_errors') {
            $period = (int)($payload['period'] ?? 168);
            if ($period < 1) $period = 168;
            if ($period > 720) $period = 720;
            $errors = [];
            $stmt = $db->prepare("SELECT * FROM smarthome_errors WHERE user_email = :em AND created_at >= datetime('now', '-' || :period || ' hours', 'localtime') ORDER BY created_at DESC LIMIT 300");
            $stmt->bindValue(':em', $userEmail, SQLITE3_TEXT);
            $stmt->bindValue(':period', $period, SQLITE3_TEXT);
            $res = $stmt->execute();
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) $errors[] = $row;
            echo json_encode(['ok'=>true, 'errors'=>$errors], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'record_param_history') {
            $deviceId = trim((string)($payload['device_id'] ?? ''));
            foreach (($payload['params'] ?? []) as $p) {
                $stmt = $db->prepare('INSERT INTO smarthome_params_history (device_id, param_id, param_label, param_value) VALUES (:did, :pid, :pl, :pv)');
                $stmt->bindValue(':did', $deviceId, SQLITE3_TEXT);
                $stmt->bindValue(':pid', $p['id'], SQLITE3_TEXT);
                $stmt->bindValue(':pl', $p['label'], SQLITE3_TEXT);
                $stmt->bindValue(':pv', $p['value'], SQLITE3_TEXT);
                $stmt->execute();
            }
            echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'Unknown action']);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
        exit;
    }
}

function sh_monitor_purge(): void {
    try {
        $db = sh_db();
        $db->exec("DELETE FROM smarthome_monitor_logs WHERE created_at < datetime('now', '-30 days', 'localtime')");
        $db->exec("DELETE FROM smarthome_errors WHERE created_at < datetime('now', '-30 days', 'localtime')");
    } catch (Throwable $e) {
        error_log('sh_monitor_purge: ' . $e->getMessage());
    }
}

function sh_log_error(string $email, ?string $deviceId, string $deviceName, string $source, string $message, array $context = []): void {
    try {
        $db = sh_db();
        if ($deviceName === '' && $deviceId) {
            $stmt = $db->prepare('SELECT name FROM smarthome_devices WHERE id = :id LIMIT 1');
            $stmt->bindValue(':id', $deviceId, SQLITE3_TEXT);
            $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            if ($row) $deviceName = (string)($row['name'] ?? '');
        }
        $stmt = $db->prepare('INSERT INTO smarthome_errors (user_email, device_id, device_name, source, message, context_json) VALUES (:em, :did, :dn, :src, :msg, :ctx)');
        $stmt->bindValue(':em', $email, SQLITE3_TEXT);
        $stmt->bindValue(':did', $deviceId ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':dn', $deviceName, SQLITE3_TEXT);
        $stmt->bindValue(':src', $source, SQLITE3_TEXT);
        $stmt->bindValue(':msg', $message, SQLITE3_TEXT);
        $stmt->bindValue(':ctx', json_encode($context, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('sh_log_error: ' . $e->getMessage());
    }
}

function sh_source_label(string $source): string {
    $source = trim($source);
    return match ($source) {
        'local_button' => 'Physical button',
        'web_ui' => 'Web UI',
        'server' => 'Server',
        'device' => 'Device',
        default => $source !== '' ? ucwords(str_replace('_', ' ', $source)) : 'Device',
    };
}

function sh_event_label(string $eventType, array $data): string {
    $name = trim((string)($data['device_name'] ?? ''));
    $prefix = $name !== '' ? $name . ': ' : '';
    switch ($eventType) {
        case 'control':
            $cid = trim((string)($data['control_label'] ?? $data['control_id'] ?? ''));
            $val = $data['value'] ?? '';
            $on = ($val === true || $val === 1 || $val === '1' || $val === 'true');
            return $prefix . 'Control ' . $cid . ' → ' . ($on ? 'ON' : 'OFF');
        case 'device_toggle':
            $cid = trim((string)($data['control_label'] ?? $data['control_id'] ?? ''));
            $val = $data['value'] ?? false;
            $src = sh_source_label((string)($data['source'] ?? 'device'));
            $on = ($val === true || $val === 1 || $val === '1' || $val === 'true');
            return $prefix . $src . ' toggled ' . $cid . ' → ' . ($on ? 'ON' : 'OFF');
        case 'device_settings':
            $fields = $data['fields'] ?? [];
            if (is_array($fields) && $fields) {
                return $prefix . 'Settings changed: ' . implode(', ', array_map('strval', $fields));
            }
            return $prefix . 'Settings changed on device';
        case 'device_created':
            return 'Device created: ' . (string)($data['name'] ?? $name);
        case 'device_deleted':
            return $prefix . 'Device deleted';
        default:
            return $prefix . $eventType;
    }
}

function sh_record_device_events(AppDatabase $db, string $userEmail, string $deviceId, string $deviceName, array $payload): void {
    if (empty($payload['events']) || !is_array($payload['events'])) {
        return;
    }
    $controlLabels = [];
    if (!empty($payload['controls']) && is_array($payload['controls'])) {
        foreach ($payload['controls'] as $controlId => $_value) {
            if (is_string($controlId) && $controlId !== '') {
                $controlLabels[$controlId] = $controlId;
            }
        }
    }
    $device = sh_device_row_for_user($db, $deviceId, $userEmail);
    if ($device) {
        foreach (sh_json_field($device['controls'] ?? '[]') as $control) {
            $controlId = (string)($control['id'] ?? '');
            if ($controlId === '') continue;
            $controlLabels[$controlId] = trim((string)($control['label'] ?? $controlId));
        }
    }
    foreach ($payload['events'] as $event) {
        if (!is_array($event)) continue;
        $eventType = trim((string)($event['event'] ?? $event['type'] ?? 'device_event'));
        if ($eventType === '') $eventType = 'device_event';
        $entry = $event;
        if ($deviceName !== '') $entry['device_name'] = $deviceName;
        $controlId = trim((string)($entry['control_id'] ?? ''));
        if ($controlId !== '' && empty($entry['control_label']) && !empty($controlLabels[$controlId])) {
            $entry['control_label'] = $controlLabels[$controlId];
        }
        sh_monitor_log($userEmail, $deviceId, $eventType, $entry);
    }
}

function sh_monitor_log(string $email, ?string $deviceId, string $eventType, array $data): void {
    try {
        sh_monitor_purge();
        if ($deviceId && empty($data['device_name'])) {
            $db = sh_db();
            $stmt = $db->prepare('SELECT name FROM smarthome_devices WHERE id = :id LIMIT 1');
            $stmt->bindValue(':id', $deviceId, SQLITE3_TEXT);
            $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            if ($row) $data['device_name'] = (string)($row['name'] ?? '');
        }
        $db = sh_db();
        $stmt = $db->prepare('INSERT INTO smarthome_monitor_logs (user_email, device_id, event_type, event_data) VALUES (:em, :did, :et, :ed)');
        $stmt->bindValue(':em', $email, SQLITE3_TEXT);
        $stmt->bindValue(':did', $deviceId ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':et', $eventType, SQLITE3_TEXT);
        $stmt->bindValue(':ed', json_encode($data, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
        $stmt->execute();
    } catch (Throwable $e) { error_log('sh_monitor_log: '.$e->getMessage()); }
}

// ─── Migration from JSON files ─────────────────────────────────────────────────
function sh_migrate_from_json(): void {
    $db = sh_db();
    $dataDir = SH_APP_DIR . '/data';
    if (!is_dir($dataDir)) return;
    $usersDir = $dataDir . '/users';
    if (!is_dir($usersDir)) return;
    $users = scandir($usersDir);
    foreach ($users as $ukey) {
        if ($ukey === '.' || $ukey === '..') continue;
        $devicesFile = $usersDir . '/' . $ukey . '/devices.json';
        $settingsFile = $usersDir . '/' . $ukey . '/settings.json';
        // Map user_key to email
        $email = 'guest@local';
        if (preg_match('/^root_root$/', $ukey)) $email = 'root@root';
        elseif ($ukey !== 'guest_local') {
            // Try to find from sessions or token index
            $email = str_replace('_at_', '@', str_replace('_dot_', '.', $ukey));
        }
        // Migrate settings
        if (is_file($settingsFile)) {
            $s = json_decode((string)file_get_contents($settingsFile), true) ?: [];
            $stmt = $db->prepare('INSERT OR IGNORE INTO smarthome_settings (user_email, server_domain, ap_domain) VALUES (:em, :sd, :ad)');
            $stmt->bindValue(':em', $email, SQLITE3_TEXT);
            $stmt->bindValue(':sd', $s['server_domain'] ?? 'serverkherson.xyz', SQLITE3_TEXT);
            $stmt->bindValue(':ad', $s['ap_domain'] ?? 'serverkherson.xyz', SQLITE3_TEXT);
            $stmt->execute();
        }
        // Migrate devices
        if (is_file($devicesFile)) {
            $devices = json_decode((string)file_get_contents($devicesFile), true) ?: [];
            foreach ($devices as $d) {
                $id = $d['id'] ?? '';
                if ($id === '') continue;
                $existing = $db->prepare('SELECT id FROM smarthome_devices WHERE id = :id');
                $existing->bindValue(':id', $id, SQLITE3_TEXT);
                if ($existing->execute()->fetchArray()) continue;
                $stmt = $db->prepare("INSERT INTO smarthome_devices (id, user_email, name, type, room, status, local_url, server_domain, ap_domain, mqtt_topic, device_token, notes, icon, controls, params, files, displayed_params, created_at, updated_at) VALUES (:id, :em, :name, :type, :room, :status, :local, :sd, :ad, :mqtt, :tok, :notes, :icon, :ctrls, :params, :files, '[]', :ca, :ua)");
                $stmt->bindValue(':id', $id, SQLITE3_TEXT);
                $stmt->bindValue(':em', $email, SQLITE3_TEXT);
                $stmt->bindValue(':name', $d['name'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(':type', $d['type'] ?? 'generic_sensor', SQLITE3_TEXT);
                $stmt->bindValue(':room', $d['room'] ?? 'Home', SQLITE3_TEXT);
                $stmt->bindValue(':status', $d['status'] ?? 'unknown', SQLITE3_TEXT);
                $stmt->bindValue(':local', $d['local_url'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(':sd', $d['server_domain'] ?? 'serverkherson.xyz', SQLITE3_TEXT);
                $stmt->bindValue(':ad', $d['ap_domain'] ?? 'serverkherson.xyz', SQLITE3_TEXT);
                $stmt->bindValue(':mqtt', $d['mqtt_topic'] ?? sh_auto_mqtt($d['name']??'', $id), SQLITE3_TEXT);
                $stmt->bindValue(':tok', $d['device_token'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(':notes', $d['notes'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(':icon', (sh_templates())[$d['type']??'']['icon'] ?? 'smart-device', SQLITE3_TEXT);
                $stmt->bindValue(':ctrls', json_encode($d['controls']??[], JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
                $stmt->bindValue(':params', json_encode($d['params']??[], JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
                $stmt->bindValue(':files', json_encode($d['files']??[], JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
                $stmt->bindValue(':ca', $d['created_at'] ?? gmdate('c'), SQLITE3_TEXT);
                $stmt->bindValue(':ua', $d['updated_at'] ?? gmdate('c'), SQLITE3_TEXT);
                $stmt->execute();
                // Token index
                if (!empty($d['device_token'])) {
                    $ti = $db->prepare('INSERT OR IGNORE INTO smarthome_token_index (token, user_email, device_id) VALUES (:tok, :em, :did)');
                    $ti->bindValue(':tok', $d['device_token'], SQLITE3_TEXT);
                    $ti->bindValue(':em', $email, SQLITE3_TEXT);
                    $ti->bindValue(':did', $id, SQLITE3_TEXT);
                    $ti->execute();
                }
            }
        }
    }
}

// Run migration once
$migrationFlag = SH_APP_DIR . '/.migrated_to_db';
if (!is_file($migrationFlag)) {
    sh_migrate_from_json();
    // Delete JSON data files after successful migration
    $dataDir = SH_APP_DIR . '/data';
    if (is_dir($dataDir)) {
        $it = new RecursiveDirectoryIterator($dataDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $f) {
            if ($f->isFile()) @unlink($f->getRealPath());
            elseif ($f->isDir()) @rmdir($f->getRealPath());
        }
    }
    file_put_contents($migrationFlag, gmdate('c'));
}

// ─── HTML Template ──────────────────────────────────────────────────────────────
$t = ap_load_i18n_from_dir(SH_APP_DIR, $lang);
$availableIcons = ['smart-device','light-bulb','torch','lamp','smart-plug','sensor','climate','fan','kettle','security'];
$availableTypes = ['pending','generic_sensor','smart_torch','smart_lamp','smart_socket','sonoff_relay'];
$typeNames = [
    'generic_sensor'=>$t['type_generic_sensor'],
    'smart_torch'=>$t['type_smart_torch'],
    'smart_lamp'=>$t['type_smart_lamp'],
    'smart_socket'=>$t['type_smart_socket'],
    'sonoff_relay'=>$t['type_sonoff_relay'],
    'pending'=>$t['type_pending'],
];
?>
