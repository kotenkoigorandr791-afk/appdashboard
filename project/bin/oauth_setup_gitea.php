<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('cli_only'); }
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/db.php';
require APP_ROOT . '/project/oauth.php';
$clientId = 'gitea';
$issuer = trim((string)($argv[1] ?? (function_exists('app_public_url') ? app_public_url('') : 'http://localhost')), '/');
$giteaUrl = trim((string)($argv[2] ?? (function_exists('app_public_url') ? app_public_url('git') : 'http://git.localhost')), '/');
$redirectUris = [
    $giteaUrl . '/user/oauth2/AppDashboard/callback',
    $giteaUrl . '/user/oauth2/appdashboard/callback',
];
$db = getDB();
ensureOAuthProviderSchema($db);
$existing = oauth_find_client($db, $clientId);
if ($existing) {
    echo "OAuth client '{$clientId}' already registered (secret unchanged).\n";
    echo "Re-run with --reset flag to regenerate secret: php oauth_setup_gitea.php [issuer] [gitea_url] --reset\n";
    if (in_array('--reset', $argv, true)) {
        $clientSecret = bin2hex(random_bytes(24));
        oauth_upsert_client($clientId, $clientSecret, 'Gitea', $redirectUris);
        echo "Secret REGENERATED — update Gitea OAuth app with new secret.\n";
    } else {
        echo "discovery_url = {$issuer}/.well-known/openid-configuration\n";
        echo "client_id     = {$clientId}\n";
        exit(0);
    }
} else {
    $clientSecret = bin2hex(random_bytes(24));
    oauth_upsert_client($clientId, $clientSecret, 'Gitea', $redirectUris);
}
echo "client_id              = {$clientId}\n";
echo "client_secret          = {$clientSecret}\n";
echo "discovery_url          = {$issuer}/.well-known/openid-configuration\n";
echo "redirect_uri (Gitea)   = {$giteaUrl}/user/oauth2/AppDashboard/callback\n";
