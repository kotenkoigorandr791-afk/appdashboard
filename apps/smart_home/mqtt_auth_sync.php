<?php
if (php_sapi_name() !== 'cli') {
    exit(1);
}
require_once dirname(__DIR__, 2) . '/lib/app_db.php';
$appDir = __DIR__;
$dataDir = getenv('APPDATA_DATA_DIR') ?: dirname($appDir, 2) . '/data';
$passwdFile = getenv('SH_MQTT_PASSWD_FILE') ?: '/etc/mosquitto/smarthome.passwd';
$aclFile = getenv('SH_MQTT_ACL_FILE') ?: '/etc/mosquitto/smarthome.acl';
$dirtyFile = rtrim($dataDir, '/') . '/smarthome_mqtt_auth_dirty';
function shma_db(): AppDatabase {
    $db = app_main_db();
    $db->enableExceptions(true);
    $db->exec("CREATE TABLE IF NOT EXISTS smarthome_token_index (
        token TEXT PRIMARY KEY,
        user_email TEXT NOT NULL,
        device_id TEXT NOT NULL,
        updated_at TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS smarthome_mqtt_auth (
        username TEXT PRIMARY KEY,
        password TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'backend',
        updated_at TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP)
    )");
    return $db;
}
function shma_backend_credentials(AppDatabase $db): array {
    $username = 'appdashboard_backend';
    $stmt = $db->prepare('SELECT password FROM smarthome_mqtt_auth WHERE username = :u LIMIT 1');
    $stmt->bindValue(':u', $username, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if ($row && (string)($row['password'] ?? '') !== '') return [$username, (string)$row['password']];
    $password = bin2hex(random_bytes(24));
    $ins = $db->prepare("INSERT INTO smarthome_mqtt_auth (username, password, role, updated_at) VALUES (:u, :p, 'backend', CURRENT_TIMESTAMP) ON CONFLICT (username) DO UPDATE SET password = EXCLUDED.password, updated_at = CURRENT_TIMESTAMP");
    $ins->bindValue(':u', $username, PDO::PARAM_STR);
    $ins->bindValue(':p', $password, PDO::PARAM_STR);
    $ins->execute();
    return [$username, $password];
}
function shma_run(string $passwdFile, string $aclFile): void {
    $db = shma_db();
    [$backendUser, $backendPass] = shma_backend_credentials($db);
    $tmpPasswd = $passwdFile . '.tmp';
    $tmpAcl = $aclFile . '.tmp';
    @unlink($tmpPasswd);
    $cmd = 'mosquitto_passwd -b -c ' . escapeshellarg($tmpPasswd) . ' ' . escapeshellarg($backendUser) . ' ' . escapeshellarg($backendPass);
    exec($cmd, $out, $code);
    if ($code !== 0) throw new RuntimeException('mosquitto_passwd failed for backend');
    $acl = "user {$backendUser}\ntopic readwrite home/#\n\n";
    $res = $db->query('SELECT token, device_id FROM smarthome_token_index ORDER BY device_id');
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $deviceId = trim((string)($row['device_id'] ?? ''));
        $token = (string)($row['token'] ?? '');
        if ($deviceId === '' || $token === '') continue;
        $cmd = 'mosquitto_passwd -b ' . escapeshellarg($tmpPasswd) . ' ' . escapeshellarg($deviceId) . ' ' . escapeshellarg($token);
        exec($cmd, $out, $code);
        if ($code !== 0) throw new RuntimeException('mosquitto_passwd failed for ' . $deviceId);
        $acl .= "user {$deviceId}\ntopic readwrite home/{$deviceId}/#\n\n";
    }
    if (file_put_contents($tmpAcl, $acl) === false) throw new RuntimeException('ACL write failed');
    chmod($tmpPasswd, 0600);
    chmod($tmpAcl, 0600);
    rename($tmpPasswd, $passwdFile);
    rename($tmpAcl, $aclFile);
    exec('chown mosquitto:mosquitto ' . escapeshellarg($passwdFile) . ' ' . escapeshellarg($aclFile) . ' 2>/dev/null || true');
    exec('pkill -HUP mosquitto >/dev/null 2>&1 || true');
}
$loop = in_array('--loop', $argv, true);
$synced = false;
do {
    try {
        if (!$loop || !$synced || (is_file($dirtyFile) && (time() - filemtime($dirtyFile)) >= 2)) {
            shma_run($passwdFile, $aclFile);
            $synced = true;
            @unlink($dirtyFile);
        }
    } catch (Throwable $e) {
        fwrite(STDERR, 'mqtt_auth_sync: ' . $e->getMessage() . PHP_EOL);
    }
    if (!$loop) break;
    sleep(10);
} while (true);
