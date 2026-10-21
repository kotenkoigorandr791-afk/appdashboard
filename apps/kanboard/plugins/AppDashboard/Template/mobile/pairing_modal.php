<?php
$apkRoot = dirname(__DIR__, 5);
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
$port = (string) ($_SERVER['SERVER_PORT'] ?? '');
$fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$scheme = ($fwd === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $port === '443') ? 'https' : 'http';
$portPart = ($port !== '' && !in_array($port, ['80', '443'], true)) ? ':' . $port : '';
$lanEndpoint = $serverIp !== '' ? $scheme . '://' . $serverIp . $portPart . '/api/kanboard' : '';
?>
<div id="kb-mobile-app-modal" class="kb-mobile-modal" style="display:none;" aria-hidden="true">
    <div class="kb-mobile-app-content">
        <button type="button" class="kb-mobile-close" id="kb-mobile-app-close" aria-label="<?= t('Close') ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
        <div class="kb-ma-header">
            <h1><?= t('Mobile app') ?></h1>
            <p><?= t('Install the companion Android app to sync your tasks with this workspace. Sync only when you refresh — nothing runs in the background.') ?></p>
        </div>
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
                    <button type="button" id="kb-mobile-token-revoke" class="kb-ma-qr-generate" style="display:none;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path></svg>
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
            <button type="button" id="kb-manual-pair-regenerate" class="kb-manual-pair-regen-btn"><?= t('Regenerate') ?></button>
        </div>
    </div>
</div>
<script>
window.__kbMobileLanEndpoint = <?= json_encode($lanEndpoint, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
</script>
