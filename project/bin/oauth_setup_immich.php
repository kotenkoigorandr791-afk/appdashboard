<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('cli_only'); }
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/db.php';
require APP_ROOT . '/project/oauth.php';
$clientId = 'immich';
$issuer = trim((string)($argv[1] ?? 'https://serverkherson.xyz'), '/');
$immichUrl = trim((string)($argv[2] ?? 'https://photo.serverkherson.xyz'), '/');
$db = getDB();
ensureOAuthProviderSchema($db);
$existing = oauth_find_client($db, $clientId);
if ($existing) {
    echo "OAuth client '{$clientId}' already registered (secret unchanged).\n";
    echo "Re-run with --reset flag to regenerate secret: php oauth_setup_immich.php [issuer] [immich_url] --reset\n";
    if (in_array('--reset', $argv, true)) {
        $clientSecret = bin2hex(random_bytes(24));
        oauth_upsert_client($clientId, $clientSecret, 'Immich', [$immichUrl . '/auth/login']);
        echo "Secret REGENERATED — update Immich OAuth settings with new secret.\n";
    } else {
        echo "discovery_url = {$issuer}/.well-known/openid-configuration\n";
        echo "client_id     = {$clientId}\n";
        exit(0);
    }
} else {
    $clientSecret = bin2hex(random_bytes(24));
    oauth_upsert_client($clientId, $clientSecret, 'Immich', [$immichUrl . '/auth/login']);
}
echo "client_id              = {$clientId}\n";
echo "client_secret          = {$clientSecret}\n";
echo "discovery_url          = {$issuer}/.well-known/openid-configuration\n";
echo "redirect_uri (Immich)  = {$immichUrl}/auth/login\n";
