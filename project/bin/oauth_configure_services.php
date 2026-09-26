<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('cli_only'); }
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}
if (!function_exists('oauth_issuer')) {
    require APP_ROOT . '/lib/app_domains.php';
    require APP_ROOT . '/db.php';
    require APP_ROOT . '/project/oauth.php';
}

function oauth_cfg_echo(string $line): void {
    echo $line . PHP_EOL;
}

function oauth_cfg_gitea_container(): string {
    foreach (['gitea', 'Gitea'] as $name) {
        $running = trim((string)shell_exec('docker inspect -f {{.State.Running}} ' . escapeshellarg($name) . ' 2>/dev/null'));
        if ($running === 'true') {
            return $name;
        }
    }
    return '';
}

function oauth_cfg_shell(string $cmd): string {
    return trim((string)shell_exec($cmd . ' 2>&1'));
}

function oauth_cfg_ensure_client(string $clientId, string $name, array $redirectUris): string {
    $db = getDB();
    ensureOAuthProviderSchema($db);
    $metaKey = 'oauth_client_secret:' . $clientId;
    $stored = oauth_meta_get($metaKey);
    $existing = oauth_find_client($db, $clientId);
    if ($existing && $stored !== '') {
        oauth_upsert_client($clientId, $stored, $name, $redirectUris);
        return $stored;
    }
    $secret = bin2hex(random_bytes(24));
    oauth_upsert_client($clientId, $secret, $name, $redirectUris);
    oauth_meta_set($metaKey, $secret);
    return $secret;
}

function oauth_cfg_gitea_source_id(string $container): ?int {
    $pdo = oauth_gitea_db();
    if ($pdo) {
        try {
            $id = $pdo->query("SELECT id FROM login_source WHERE lower(name) = 'appdashboard' ORDER BY id ASC LIMIT 1")->fetchColumn();
            if ($id) {
                return (int)$id;
            }
        } catch (Throwable $e) {
        }
    }
    $list = oauth_cfg_shell('docker exec -u git ' . escapeshellarg($container) . ' gitea admin auth list');
    if (preg_match('/\|\s*(\d+)\s*\|\s*AppDashboard\s*\|/i', $list, $m)) {
        return (int)$m[1];
    }
    return null;
}

function oauth_cfg_gitea_source_cfg(string $secret, string $discovery): string {
    return json_encode([
        'Provider' => 'openidConnect',
        'ClientID' => 'gitea',
        'ClientSecret' => $secret,
        'OpenIDConnectAutoDiscoveryURL' => $discovery,
        'CustomURLMapping' => null,
        'IconURL' => '',
        'Scopes' => ['openid', 'email', 'profile'],
        'RequiredClaimName' => '',
        'RequiredClaimValue' => '',
        'GroupClaimName' => '',
        'AdminGroup' => '',
        'GroupTeamMap' => '',
        'GroupTeamMapRemoval' => false,
        'RestrictedGroup' => '',
        'SSHPublicKeyClaimName' => '',
        'FullNameClaimName' => '',
        'ExternalIDClaim' => '',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function oauth_cfg_restart_container(string $name): void {
    $running = trim((string)shell_exec('docker inspect -f {{.State.Running}} ' . escapeshellarg($name) . ' 2>/dev/null'));
    if ($running === 'true') {
        oauth_cfg_shell('docker restart ' . escapeshellarg($name));
    }
}

function oauth_cfg_configure_gitea_db(string $secret, string $discovery): bool {
    $pdo = oauth_gitea_db();
    if (!$pdo) {
        return false;
    }
    $cfg = oauth_cfg_gitea_source_cfg($secret, $discovery);
    $now = time();
    try {
        $id = $pdo->query("SELECT id FROM login_source WHERE lower(name) = 'appdashboard' ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($id) {
            $stmt = $pdo->prepare('UPDATE login_source SET type = 6, is_active = true, cfg = :cfg, updated_unix = :now WHERE id = :id');
            $stmt->execute([':cfg' => $cfg, ':now' => $now, ':id' => (int)$id]);
            oauth_cfg_echo('gitea: updated OAuth source AppDashboard in DB (id=' . (int)$id . ')');
            oauth_cfg_restart_container('gitea');
            return true;
        }
        $stmt = $pdo->prepare('INSERT INTO login_source (type, name, is_active, is_sync_enabled, two_factor_policy, cfg, created_unix, updated_unix)
            VALUES (6, :name, true, false, :policy, :cfg, :now, :now)');
        $stmt->execute([
            ':name' => 'AppDashboard',
            ':policy' => '',
            ':cfg' => $cfg,
            ':now' => $now,
        ]);
        oauth_cfg_echo('gitea: created OAuth source AppDashboard in DB');
        oauth_cfg_restart_container('gitea');
        return true;
    } catch (Throwable $e) {
        oauth_cfg_echo('gitea: DB configure failed: ' . $e->getMessage());
        return false;
    }
}

function oauth_cfg_configure_gitea(string $issuer, string $giteaUrl, string $secret): bool {
    $discovery = oauth_internal_discovery_url();
    $container = oauth_cfg_gitea_container();
    if ($container !== '') {
        $existingId = oauth_cfg_gitea_source_id($container);
        if ($existingId !== null) {
            $cmd = 'docker exec -u git ' . escapeshellarg($container)
                . ' gitea admin auth update-oauth --id ' . (int)$existingId
                . ' --key gitea --secret ' . escapeshellarg($secret)
                . ' --auto-discover-url ' . escapeshellarg($discovery);
            $out = oauth_cfg_shell($cmd);
            if ($out === '' || !str_contains(strtolower($out), 'error')) {
                oauth_cfg_echo('gitea: updated OAuth source AppDashboard (id=' . $existingId . ')');
                return true;
            }
        }
        $cmd = 'docker exec -u git ' . escapeshellarg($container)
            . ' gitea admin auth add-oauth --name AppDashboard --provider openidConnect'
            . ' --key gitea --secret ' . escapeshellarg($secret)
            . ' --auto-discover-url ' . escapeshellarg($discovery)
            . ' --scopes openid --scopes email --scopes profile';
        $out = oauth_cfg_shell($cmd);
        if ($out === '' || !str_contains(strtolower($out), 'error')) {
            oauth_cfg_echo('gitea: OAuth source AppDashboard ready');
            return true;
        }
        oauth_cfg_echo('gitea: CLI configure failed, trying DB fallback: ' . $out);
    }
    if (oauth_cfg_configure_gitea_db($secret, $discovery)) {
        return true;
    }
    oauth_cfg_echo('gitea: unavailable, skip');
    return false;
}

function oauth_cfg_app_oauth_bases(string $app): array {
    $external = rtrim(oauth_external_app_url($app), '/');
    $iframe = rtrim(oauth_iframe_app_url($app), '/');
    $bases = [$external];
    if ($iframe !== $external) {
        $bases[] = $iframe;
    }
    return array_values(array_unique($bases));
}

function oauth_cfg_configure_immich(string $issuer, string $immichUrl, string $secret): bool {
    $pdo = oauth_immich_db();
    if (!$pdo) {
        oauth_cfg_echo('immich: database unavailable, skip');
        return false;
    }
    $discovery = oauth_internal_discovery_url();
    $allowInsecure = str_starts_with(strtolower($issuer), 'http://') || str_starts_with(strtolower($discovery), 'http://');
    $oauth = [
        'enabled' => true,
        'autoLaunch' => true,
        'autoRegister' => true,
        'buttonText' => 'Login with AppDashboard',
        'clientId' => 'immich',
        'clientSecret' => $secret,
        'issuerUrl' => $discovery,
        'scope' => 'openid email profile',
        'signingAlgorithm' => 'RS256',
        'profileSigningAlgorithm' => 'none',
        'tokenEndpointAuthMethod' => 'client_secret_post',
        'storageLabelClaim' => 'preferred_username',
        'roleClaim' => 'immich_role',
        'timeout' => 30000,
        'mobileOverrideEnabled' => false,
        'mobileRedirectUri' => '',
        'allowInsecureRequests' => $allowInsecure,
    ];
    try {
        $stmt = $pdo->prepare("SELECT value FROM system_metadata WHERE key = 'system-config' LIMIT 1");
        $stmt->execute();
        $raw = $stmt->fetchColumn();
        $config = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $config = $decoded;
            }
        }
        $config['oauth'] = array_merge(is_array($config['oauth'] ?? null) ? $config['oauth'] : [], $oauth);
        $iframeUrl = rtrim(oauth_iframe_app_url('immich'), '/');
        $config['server'] = array_merge(is_array($config['server'] ?? null) ? $config['server'] : [], [
            'externalDomain' => $iframeUrl !== '' ? $iframeUrl : rtrim($immichUrl, '/'),
        ]);
        $payload = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $upsert = $pdo->prepare("INSERT INTO system_metadata (key, value) VALUES ('system-config', CAST(:value AS jsonb))
            ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value");
        $upsert->execute([':value' => $payload]);
        oauth_cfg_echo('immich: OAuth configured (issuer=' . $issuer . ')');
        oauth_cfg_restart_container('immich-server');
        oauth_cfg_restart_container('immich_server');
        return true;
    } catch (Throwable $e) {
        oauth_cfg_echo('immich: configure failed: ' . $e->getMessage());
        return false;
    }
}

function oauth_configure_services(): int {
    $issuer = rtrim(oauth_issuer(), '/');
    $giteaUrl = rtrim(oauth_external_app_url('gitea'), '/');
    $immichUrl = rtrim(oauth_external_app_url('immich'), '/');
    $giteaIframeUrl = rtrim(oauth_iframe_app_url('gitea'), '/');
    $immichIframeUrl = rtrim(oauth_iframe_app_url('immich'), '/');
    oauth_cfg_echo('issuer=' . $issuer);
    oauth_cfg_echo('discovery_internal=' . oauth_internal_discovery_url());
    oauth_cfg_echo('gitea_url=' . $giteaUrl);
    oauth_cfg_echo('gitea_iframe_url=' . $giteaIframeUrl);
    oauth_cfg_echo('immich_url=' . $immichUrl);
    oauth_cfg_echo('immich_iframe_url=' . $immichIframeUrl);
    $giteaRedirects = [];
    foreach (oauth_cfg_app_oauth_bases('gitea') as $base) {
        $giteaRedirects[] = $base . '/user/oauth2/AppDashboard/callback';
        $giteaRedirects[] = $base . '/user/oauth2/appdashboard/callback';
    }
    $immichRedirects = array_map(static fn(string $base): string => $base . '/auth/login', oauth_cfg_app_oauth_bases('immich'));
    $giteaSecret = oauth_cfg_ensure_client('gitea', 'Gitea', array_values(array_unique($giteaRedirects)));
    $immichSecret = oauth_cfg_ensure_client('immich', 'Immich', array_values(array_unique($immichRedirects)));
    oauth_cfg_configure_gitea($issuer, $giteaUrl, $giteaSecret);
    oauth_cfg_configure_immich($issuer, $immichUrl, $immichSecret);
    return 0;
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    exit(oauth_configure_services());
}
