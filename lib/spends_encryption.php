<?php
declare(strict_types=1);
function spends_encryption_key(): string {
    static $key = null;
    if ($key !== null) return $key;
    $file = (string)(getenv('SPENDS_ENCRYPTION_KEY_FILE') ?: '/opt/AppDashboard/data/secrets/spends-encryption.key');
    $encoded = is_file($file) ? trim((string)file_get_contents($file)) : trim((string)getenv('SPENDS_ENCRYPTION_KEY'));
    if ($encoded === '') {
        if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0700, true) && !is_dir(dirname($file))) throw new RuntimeException('spends_key_directory_unavailable');
        $encoded = base64_encode(random_bytes(32));
        if (file_put_contents($file, $encoded . "\n", LOCK_EX) === false) throw new RuntimeException('spends_key_unavailable');
        @chmod($file, 0600);
    }
    $decoded = base64_decode($encoded, true);
    if ($decoded === false || strlen($decoded) < 32) throw new RuntimeException('spends_key_invalid');
    return $key = substr($decoded, 0, 32);
}
function spends_encrypt(string $value, string $userId, string $purpose): string {
    if ($value === '' || str_starts_with($value, 'spends:v1:')) return $value;
    $nonce = random_bytes(12); $tag = '';
    $key = hash_hkdf('sha256', spends_encryption_key(), 32, 'spends:' . $purpose, $userId);
    $cipher = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, $userId . '|' . $purpose);
    if ($cipher === false) throw new RuntimeException('spends_encrypt_failed');
    return 'spends:v1:' . rtrim(strtr(base64_encode($nonce . $tag . $cipher), '+/', '-_'), '=');
}
function spends_decrypt(?string $value, string $userId, string $purpose): string {
    $value = (string)$value;
    if (!str_starts_with($value, 'spends:v1:')) return $value;
    $raw = base64_decode(strtr(substr($value, 10), '-_', '+/'), true);
    if ($raw === false || strlen($raw) < 29) return '';
    $key = hash_hkdf('sha256', spends_encryption_key(), 32, 'spends:' . $purpose, $userId);
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), $userId . '|' . $purpose);
    return $plain === false ? '' : $plain;
}
function spends_db_user_id(AppDatabase $db): string { return (string)$db->querySingle("SELECT current_setting('spends.user_id', true)"); }
function app_spends_encrypt_existing(AppDatabase $db, string $uid): void {
    static $done = []; if ($uid === '' || isset($done[$uid])) return;
    $db->exec('BEGIN');
    try {
        $db->query("SELECT pg_advisory_xact_lock(hashtext('spends-encryption:" . AppDatabase::escapeString($uid) . "'))");
        foreach ([['settings','value','setting'],['table_settings','value','table-setting'],['expenses','ocr_raw_store','ocr'],['ocr_entries','ocr_raw_store','ocr'],['goals','description','goal-description']] as [$table,$column,$purpose]) {
            $rows = $db->prepare("SELECT ctid, $column FROM spends_shared.$table WHERE user_id=:user AND $column IS NOT NULL AND $column <> '' AND $column NOT LIKE 'spends:v1:%'");
            $rows->bindValue(':user', $uid); $rows->execute(); $update = $db->prepare("UPDATE spends_shared.$table SET $column=:value WHERE ctid=:ctid");
            while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                $update->bindValue(':value', spends_encrypt((string)$row[$column], $uid, $purpose));
                $update->bindValue(':ctid', $row['ctid']);
                $update->execute();
            }
        }
        $db->exec('COMMIT');
    } catch (Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
    $done[$uid] = true;
}
