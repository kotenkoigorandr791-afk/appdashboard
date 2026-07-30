<?php
require_once dirname(__DIR__) . '/db.php';
function botBackupCryptKeyBinary(): string {
    $env = getenv('BACKUP_DASHBOARD_CRYPT_KEY');
    if ($env !== false && $env !== '') return hash('sha256', $env, true);
    return hash('sha256', getConfig('backup_dashboard_data_key', ''), true);
}
function botBackupDecryptPayload(string $json): ?string {
    $w = json_decode($json, true);
    if (!is_array($w) || !isset($w['iv'], $w['tag']) || !array_key_exists('ct', $w)) return null;
    $iv = base64_decode((string)$w['iv'], true);
    $ct = base64_decode((string)$w['ct'], true);
    $tag = base64_decode((string)$w['tag'], true);
    if ($iv === false || $ct === false || $tag === false) return null;
    $pt = openssl_decrypt($ct, 'aes-256-gcm', botBackupCryptKeyBinary(), OPENSSL_RAW_DATA, $iv, $tag, '');
    return $pt === false ? null : $pt;
}
function botBackupCredentials(): array {
    $row = getConfig('dup_backup_secrets_enc', '');
    if ($row === '') return ['host' => '', 'token' => ''];
    $inner = botBackupDecryptPayload($row);
    $obj = is_string($inner) ? json_decode($inner, true) : null;
    if (!is_array($obj)) return ['host' => '', 'token' => ''];
    return [
        'host' => rtrim((string)($obj['host'] ?? ''), '/'),
        'token' => trim((string)($obj['token'] ?? '')),
    ];
}
$cmd = (string)($argv[1] ?? '');
if ($cmd === 'backup-credentials') {
    echo json_encode(botBackupCredentials(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
if ($cmd === 'docker-config') {
    echo json_encode(loadDockerConfig(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
fwrite(STDERR, "Unknown command\n");
exit(2);
