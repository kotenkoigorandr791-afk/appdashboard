<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('cli_only'); }
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/db.php';
require APP_ROOT . '/project/oauth.php';
$clientId = 'plane';
$issuer = trim((string)($argv[1] ?? 'https://serverkherson.xyz/plane-oauth'), '/');
$planeUrl = trim((string)($argv[2] ?? 'https://plane.serverkherson.xyz'), '/');
$db = getDB();
ensureOAuthProviderSchema($db);
$existing = oauth_find_client($db, $clientId);
if ($existing) {
    echo "OAuth client '{$clientId}' already registered (secret unchanged).\n";
    echo "Re-run with --reset flag to regenerate secret: php oauth_setup_plane.php [issuer] [plane_url] --reset\n";
    if (in_array('--reset', $argv, true)) {
        $clientSecret = bin2hex(random_bytes(24));
        oauth_upsert_client($clientId, $clientSecret, 'Plane', [$planeUrl . '/auth/gitea/callback/']);
        echo "Secret REGENERATED - update Plane OAuth settings with new secret.\n";
    } else {
        echo "gitea_host = {$issuer}\n";
        echo "client_id  = {$clientId}\n";
        exit(0);
    }
} else {
    $clientSecret = bin2hex(random_bytes(24));
    oauth_upsert_client($clientId, $clientSecret, 'Plane', [$planeUrl . '/auth/gitea/callback/']);
}
echo "client_id             = {$clientId}\n";
echo "client_secret         = {$clientSecret}\n";
echo "gitea_host            = {$issuer}\n";
echo "redirect_uri (Plane)  = {$planeUrl}/auth/gitea/callback/\n";
