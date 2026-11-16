<?php
/**
 * Encryption for the Steps Immich adapter. The key follows the same protected
 * file convention as other application secrets; only ciphertext is stored in
 * PostgreSQL and no key material is ever returned by an API.
 */
function steps_geo_secret_key(): string
{
    static $key = null;
    if ($key !== null) return $key;
    $file = (string)(getenv('STEPS_GEOLOCATION_KEY_FILE') ?: '/opt/AppDashboard/data/secrets/steps-geolocation.key');
    $encoded = is_file($file) ? trim((string)file_get_contents($file)) : trim((string)getenv('STEPS_GEOLOCATION_KEY'));
    if ($encoded === '') {
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('geolocation_key_directory_unavailable');
        $encoded = base64_encode(random_bytes(32));
        if (file_put_contents($file, $encoded . "\n", LOCK_EX) === false) throw new RuntimeException('geolocation_key_unavailable');
        @chmod($file, 0600);
    }
    $decoded = base64_decode($encoded, true);
    if ($decoded === false || strlen($decoded) < 32) throw new RuntimeException('geolocation_key_invalid');
    return $key = substr($decoded, 0, 32);
}

function steps_geo_encrypt(string $value, string $userId, string $purpose): string
{
    if ($value === '') return '';
    $nonce = random_bytes(12);
    $tag = '';
    $key = hash_hkdf('sha256', steps_geo_secret_key(), 32, 'steps-geolocation:' . $purpose, $userId);
    $cipher = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, $userId . '|' . $purpose);
    if ($cipher === false) throw new RuntimeException('geolocation_encrypt_failed');
    return 'steps-geo:v1:' . rtrim(strtr(base64_encode($nonce . $tag . $cipher), '+/', '-_'), '=');
}

function steps_geo_decrypt(string $value, string $userId, string $purpose): string
{
    if (!str_starts_with($value, 'steps-geo:v1:')) return '';
    $raw = base64_decode(strtr(substr($value, 13), '-_', '+/'), true);
    if ($raw === false || strlen($raw) < 29) return '';
    $key = hash_hkdf('sha256', steps_geo_secret_key(), 32, 'steps-geolocation:' . $purpose, $userId);
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), $userId . '|' . $purpose);
    return $plain === false ? '' : $plain;
}
