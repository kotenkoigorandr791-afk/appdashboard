<?php
$apkRoot = dirname(__DIR__, 6);
$apkPath = $apkRoot . '/downloads/kanboard-mobile.apk';
$apkMetaFile = $apkRoot . '/downloads/kanboard-mobile.json';
$apkMeta = is_file($apkMetaFile) ? (json_decode((string) file_get_contents($apkMetaFile), true) ?: []) : [];
$apkVersion = (string) ($apkMeta['version'] ?? '');
$apkBuiltAt = (int) ($apkMeta['built_at'] ?? 0);
$apkAvailable = is_file($apkPath);
$serverIp = (string) ($_SERVER['SERVER_ADDR'] ?? '');
if ($serverIp === '' || str_starts_with($serverIp, '127.') || $serverIp === '::1') {
    $hostIps = function_exists('gethostbynamel') ? (gethostbynamel(gethostname()) ?: []) : [];
    foreach ($hostIps as $candidateIp) {
        $candidateIp = (string) $candidateIp;
        if (str_starts_with($candidateIp, '192.168.') || str_starts_with($candidateIp, '10.') || preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $candidateIp)) {
            $serverIp = $candidateIp;
            break;
        }
    }
}
if ($serverIp === '' || str_starts_with($serverIp, '127.') || str_starts_with($serverIp, '172.17.') || str_starts_with($serverIp, '172.18.')) {
    $serverIp = '10.0.2.2';
}
// External port: prefer the port the browser actually used. SERVER_PORT is the
// container port (8080) and must not leak into pairing links behind a proxy.
$hostHeader = (string) ($_SERVER['HTTP_HOST'] ?? '');
$port = '';
if (preg_match('/:(\d+)$/', $hostHeader, $portMatch)) {
    $port = $portMatch[1];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PORT'])) {
    $port = (string) $_SERVER['HTTP_X_FORWARDED_PORT'];
}
$fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$scheme = ($fwd === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $port === '443') ? 'https' : 'http';
$portPart = ($port !== '' && !in_array($port, ['80', '443'], true)) ? ':' . $port : '';
$lanEndpoint = $serverIp !== '' ? $scheme . '://' . $serverIp . $portPart . '/api/kanboard' : '';
$displayName = (string) $this->user->getFullname();
if ($displayName === '') {
    $displayName = (string) $this->user->getUsername();
}
$bridgeUser = function_exists('kanboard_bridge_user') ? kanboard_bridge_user() : null;
$accountEmail = (string) ($bridgeUser['email'] ?? ($_SESSION['user']['email'] ?? ''));
$roleValue = (int) $this->user->getRole();
$roleLabel = $roleValue >= 2 ? t('Administrator') : ($roleValue === 1 ? t('Manager') : t('Member'));
$initial = mb_strtoupper(mb_substr($displayName !== '' ? $displayName : 'U', 0, 1));
$tokens = $this->MobilePairingHelper->tokens();
?>
<div id="kb-mobile-app-modal" class="kb-mobile-modal" style="display:none;" aria-hidden="true"
     data-endpoint="<?= $this->text->e($lanEndpoint) ?>"
     data-account="<?= $this->text->e($accountEmail) ?>"
     data-no-token-msg="<?= $this->text->e(t('Generate a QR code first.')) ?>"
     data-no-phones-msg="<?= $this->text->e(t('No phones connected yet')) ?>"
     data-no-phones-note="<?= $this->text->e(t('Generate a pairing code to connect the Android app.')) ?>">
    <div class="kb-settings-content">
        <button type="button" class="kb-mobile-close" id="kb-mobile-app-close" aria-label="<?= t('Close') ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
        <div class="kb-ma-header">
            <h1><?= t('Settings') ?></h1>
            <p><?= t('Manage your account, appearance of the workspace and the phone connection.') ?></p>
        </div>
        <div class="kb-settings-tabs" role="tablist">
            <button type="button" class="kb-settings-tab is-active" data-kb-settings-tab="profile" role="tab" aria-selected="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"></circle><path d="M4.5 20a7.5 7.5 0 0 1 15 0"></path></svg>
                <?= t('Profile') ?>
            </button>
            <button type="button" class="kb-settings-tab" data-kb-settings-tab="mobile" role="tab" aria-selected="false">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"></rect><path d="M12 18h.01"></path></svg>
                <?= t('Mobile app') ?>
            </button>
            <button type="button" class="kb-settings-tab" data-kb-settings-tab="connection" role="tab" aria-selected="false">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 15l6-6"></path><path d="M11 6l1-1a4 4 0 0 1 5.7 5.7l-1 1"></path><path d="M13 18l-1 1a4 4 0 0 1-5.7-5.7l1-1"></path></svg>
                <?= t('Connection') ?>
            </button>
        </div>

        <div class="kb-settings-panel is-active" id="kb-settings-panel-profile" role="tabpanel">
            <div class="kb-settings-profile">
                <span class="kb-settings-avatar" aria-hidden="true"><?= $this->avatar->currentUserSmall('avatar-inline') ?></span>
                <div class="kb-settings-profile-meta">
                    <strong><?= $this->text->e($displayName) ?></strong>
                    <span><?= $this->text->e($accountEmail) ?></span>
                    <span class="kb-settings-role"><?= $this->text->e($roleLabel) ?></span>
                </div>
            </div>
            <div class="kb-settings-cards">
                <a class="kb-settings-card" href="<?= $this->url->href('DashboardController', 'tasks', ['user_id' => $this->user->getId()]) ?>">
                    <span class="kb-settings-card-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"></circle><path d="m9.2 12.2 1.9 1.9 3.8-4"></path></svg>
                    </span>
                    <span class="kb-settings-card-text">
                        <strong><?= t('My tasks') ?></strong>
                        <small><?= t('Everything assigned to you') ?></small>
                    </span>
                </a>
                <a class="kb-settings-card" href="<?= $this->url->href('DashboardController', 'projects', ['user_id' => $this->user->getId()]) ?>">
                    <span class="kb-settings-card-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8.2A1.7 1.7 0 0 1 5.7 6.5H10l1.8 2H18.3A1.7 1.7 0 0 1 20 10.2v7.1A1.7 1.7 0 0 1 18.3 19H5.7A1.7 1.7 0 0 1 4 17.3Z"></path></svg>
                    </span>
                    <span class="kb-settings-card-text">
                        <strong><?= t('Projects') ?></strong>
                        <small><?= t('Browse every board in this workspace') ?></small>
                    </span>
                </a>
                <button type="button" class="kb-settings-card" data-kb-open-tab="connection">
                    <span class="kb-settings-card-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"></rect><path d="M12 18h.01"></path></svg>
                    </span>
                    <span class="kb-settings-card-text">
                        <strong><?= t('Phone connection') ?></strong>
                        <small><?= count($tokens) > 0 ? t('Linked devices') . ': ' . count($tokens) : t('Connect the Android app') ?></small>
                    </span>
                </button>
            </div>
            <p class="kb-settings-note"><?= t('Appearance and language follow the dashboard shell. Use the header controls to switch theme or language.') ?></p>
        </div>

        <div class="kb-settings-panel" id="kb-settings-panel-mobile" role="tabpanel">
            <div class="kb-ma-grid">
                <div class="kb-ma-action">
                    <button type="button" id="kb-mobile-manual-hint" class="kb-ma-qr-hint" title="<?= t('Manual pairing') ?>" aria-label="<?= t('Manual pairing') ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    </button>
                    <div class="kb-ma-qr-frame">
                        <div class="kb-ma-qr-placeholder" aria-hidden="true">
                            <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect width="100" height="100" fill="white"/>
                                <path d="M10 10H30V30H10V10ZM70 10H90V30H70V10ZM10 70H30V90H10V70ZM40 40H60V60H40V40ZM40 10H60V20H40V10ZM10 40H20V60H10V40ZM80 40H90V60H80V40ZM40 80H60V90H40V80ZM80 80H90V90H80V80Z" fill="#121212"/>
                            </svg>
                        </div>
                        <div class="kb-ma-qr-code" id="kb-mobile-app-qr-code"></div>
                        <button type="button" id="kb-mobile-app-qr-generate" class="kb-ma-qr-generate">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path><line x1="12" y1="2" x2="12" y2="12"></line></svg>
                            <?= t('Generate') ?>
                        </button>
                    </div>
                    <div class="kb-ma-action-buttons">
                        <button type="button" id="kb-mobile-token-revoke" class="kb-ma-btn kb-ma-btn-danger" style="display:none;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path></svg>
                            <?= t('Revoke token') ?>
                        </button>
                    </div>
                    <h3><?= t('Scan to pair') ?></h3>
                    <p><?= t('Open the Tasks app and scan this code to sync instantly.') ?></p>
                    <p class="kb-ma-qr-error" id="kb-mobile-app-qr-error" style="display:none;"></p>
                    <?php if ($apkAvailable): ?>
                    <a class="kb-ma-download" href="/downloads/kanboard-mobile.apk" download="kanboard-mobile.apk">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        <?= t('Download mobile app') ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($apkVersion !== '' || $apkBuiltAt > 0): ?>
                    <div class="kb-ma-meta">
                        <?php if ($apkVersion !== ''): ?><span>v<?= $this->text->e($apkVersion) ?></span><?php endif; ?>
                        <?php if ($apkVersion !== '' && $apkBuiltAt > 0): ?><span>•</span><?php endif; ?>
                        <?php if ($apkBuiltAt > 0): ?><span><?= t('Updated') ?> <?= $this->text->e(date('d.m.Y', $apkBuiltAt)) ?></span><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="kb-ma-features">
                    <div class="kb-ma-feature">
                        <div class="kb-ma-feature-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                        </div>
                        <div class="kb-ma-feature-text">
                            <h4><?= t('QR pairing') ?></h4>
                            <p><?= t('Scan a QR code to pair the app with this dashboard in seconds.') ?></p>
                        </div>
                    </div>
                    <div class="kb-ma-feature">
                        <div class="kb-ma-feature-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        </div>
                        <div class="kb-ma-feature-text">
                            <h4><?= t('Secure token') ?></h4>
                            <p><?= t('Use a generated token for secure, password-free authentication.') ?></p>
                        </div>
                    </div>
                    <div class="kb-ma-feature">
                        <div class="kb-ma-feature-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path><line x1="12" y1="2" x2="12" y2="12"></line></svg>
                        </div>
                        <div class="kb-ma-feature-text">
                            <h4><?= t('Manual sync') ?></h4>
                            <p><?= t('Refresh only when you need updates — keeps your phone cool and quiet.') ?></p>
                        </div>
                    </div>
                    <div class="kb-ma-feature">
                        <div class="kb-ma-feature-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"></rect><path d="M12 18h.01"></path></svg>
                        </div>
                        <div class="kb-ma-feature-text">
                            <h4><?= t('Offline cache') ?></h4>
                            <p><?= t('Projects and tasks stay available offline from the local cache.') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="kb-settings-panel" id="kb-settings-panel-connection" role="tabpanel">
            <div class="kb-connection-status">
                <span class="kb-connection-dot" aria-hidden="true"></span>
                <div>
                    <strong><?= count($tokens) > 0 ? t('Phone connected') : t('No phones connected yet') ?></strong>
                    <small><?= count($tokens) > 0 ? t('The Android app can read and update your tasks.') : t('Generate a pairing code to connect the Android app.') ?></small>
                </div>
            </div>
            <div class="kb-connection-grid">
                <div class="kb-connection-field">
                    <label><?= t('Server endpoint') ?></label>
                    <pre id="kb-connection-endpoint"><?= $this->text->e($lanEndpoint !== '' ? $lanEndpoint : '/api/kanboard') ?></pre>
                </div>
                <div class="kb-connection-field">
                    <label><?= t('Account') ?></label>
                    <pre id="kb-connection-account"><?= $this->text->e($accountEmail) ?></pre>
                </div>
            </div>
            <div class="kb-connection-actions">
                <button type="button" class="kb-connection-btn is-primary" data-kb-open-tab="mobile"><?= t('Pair a phone') ?></button>
                <button type="button" class="kb-connection-btn" id="kb-connection-manual"><?= t('Manual pairing') ?></button>
                <button type="button" class="kb-connection-btn is-danger" id="kb-connection-revoke"><?= t('Revoke all tokens') ?></button>
            </div>
            <h3 class="kb-connection-heading"><?= t('Linked devices') ?></h3>
            <?php if (empty($tokens)): ?>
                <p class="kb-settings-note"><?= t('Nothing linked yet. Pair the Android app to see it here.') ?></p>
            <?php else: ?>
                <ul class="kb-connection-devices">
                    <?php foreach ($tokens as $token): ?>
                        <li>
                            <span class="kb-connection-device-icon" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"></rect><path d="M12 18h.01"></path></svg>
                            </span>
                            <span class="kb-connection-device-text">
                                <strong><?= $this->text->e($token['label']) ?></strong>
                                <small>
                                    <?= t('Last used') ?>: <?= $token['last_used_at'] > 0 ? $this->text->e(date('d.m.Y H:i', $token['last_used_at'])) : '—' ?>
                                    · <?= t('Expires') ?>: <?= $token['expires_at'] > 0 ? $this->text->e(date('d.m.Y', $token['expires_at'])) : '—' ?>
                                </small>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p class="kb-settings-note"><?= t('Tokens are stored only as hashes. Revoking a token signs the phone out immediately.') ?></p>
        </div>
    </div>
</div>
<div id="kb-manual-pair-modal" class="kb-mobile-modal" style="display:none;" aria-hidden="true">
    <div class="kb-manual-pair-content">
        <button type="button" id="kb-manual-pair-close" class="kb-mobile-close" aria-label="<?= t('Close') ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
        <div class="kb-ma-header">
            <h1><?= t('Manual pairing') ?></h1>
            <p><?= t('If QR scanning is not available, connect the app manually:') ?></p>
        </div>
        <ol class="kb-manual-pair-steps">
            <li><?= t('Generate a pairing token with the Generate button.') ?></li>
            <li><?= t('Copy the connection link below.') ?></li>
            <li><?= t('Paste it into the Tasks Android app and tap Connect.') ?></li>
        </ol>
        <div class="kb-manual-pair-fields">
            <div class="kb-manual-pair-row">
                <div class="kb-manual-pair-field">
                    <label><?= t('Server endpoint') ?></label>
                    <pre id="kb-manual-pair-endpoint"></pre>
                </div>
                <button type="button" class="kb-ma-copy-btn" data-copy-target="kb-manual-pair-endpoint" aria-label="<?= t('Copy') ?>">
                    <svg class="kb-ma-copy-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                    <svg class="kb-ma-copy-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </button>
            </div>
            <div class="kb-manual-pair-row">
                <div class="kb-manual-pair-field">
                    <label><?= t('Connection link') ?></label>
                    <pre id="kb-manual-pair-link"></pre>
                </div>
                <button type="button" class="kb-ma-copy-btn" data-copy-target="kb-manual-pair-link" aria-label="<?= t('Copy') ?>">
                    <svg class="kb-ma-copy-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                    <svg class="kb-ma-copy-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </button>
            </div>
            <div class="kb-manual-pair-row">
                <div class="kb-manual-pair-field">
                    <label><?= t('Token') ?></label>
                    <pre id="kb-manual-pair-token"><?= t('Generate a QR code first.') ?></pre>
                </div>
                <button type="button" class="kb-ma-copy-btn" data-copy-target="kb-manual-pair-token" aria-label="<?= t('Copy') ?>">
                    <svg class="kb-ma-copy-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                    <svg class="kb-ma-copy-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </button>
            </div>
        </div>
        <div class="kb-manual-pair-footer">
            <button type="button" id="kb-manual-pair-regenerate" class="kb-manual-pair-regen-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"></path><polyline points="21 3 21 9 15 9"></polyline></svg>
                <?= t('Regenerate') ?>
            </button>
        </div>
    </div>
</div>
