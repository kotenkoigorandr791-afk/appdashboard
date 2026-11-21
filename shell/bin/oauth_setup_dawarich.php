<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('cli_only'); }
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/db.php';
require APP_ROOT . '/project/oauth.php';
$clientId = 'dawarich';
$issuer = trim((string)($argv[1] ?? (function_exists('app_public_url') ? app_public_url('') : 'http://localhost')), '/');
$dawarichUrl = trim((string)($argv[2] ?? (function_exists('app_public_url') ? app_public_url('dawarich') : 'http://dawarich.localhost')), '/');
$redirectUri = $dawarichUrl . '/users/auth/openid_connect/callback';
$db = getDB();
ensureOAuthProviderSchema($db);
$existing = oauth_find_client($db, $clientId);
if ($existing) {
    echo "OAuth client '{$clientId}' already registered (secret unchanged).\n";
    echo "Re-run with --reset flag to regenerate secret: php oauth_setup_dawarich.php [issuer] [dawarich_url] --reset\n";
    if (in_array('--reset', $argv, true)) {
        $clientSecret = bin2hex(random_bytes(24));
        oauth_upsert_client($clientId, $clientSecret, 'Dawarich', [$redirectUri]);
        echo "Secret REGENERATED — update Dawarich OIDC settings with new secret.\n";
    } else {
        echo "discovery_url = {$issuer}/.well-known/openid-configuration\n";
        echo "client_id     = {$clientId}\n";
        exit(0);
    }
} else {
    $clientSecret = bin2hex(random_bytes(24));
    oauth_upsert_client($clientId, $clientSecret, 'Dawarich', [$redirectUri]);
}
echo "client_id               = {$clientId}\n";
echo "client_secret           = {$clientSecret}\n";
echo "discovery_url           = {$issuer}/.well-known/openid-configuration\n";
echo "redirect_uri (Dawarich) = {$redirectUri}\n";
echo "OIDC_ISSUER             = {$issuer}\n";
echo "OIDC_PROVIDER_NAME      = AppDashboard\n";
