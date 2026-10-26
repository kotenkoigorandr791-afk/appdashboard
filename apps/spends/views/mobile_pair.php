<?php
/**
 * Mobile app pairing modal.
 *
 * Mirrors the Steps app flow: generate a device token for this dashboard user,
 * render it as a QR code the mobile client can scan, and offer a manual
 * pairing screen (endpoint + deep link + token) when scanning is impossible.
 *
 * The token is app-scoped (`token_app=spends`) so it can only reach
 * /api/spends even if it leaks into another integration.
 */
$mobilePairRoot = dirname(__DIR__, 3);
$mobilePairServerIp = (string)($_SERVER['SERVER_ADDR'] ?? '');
if ($mobilePairServerIp === '' || str_starts_with($mobilePairServerIp, '127.') || $mobilePairServerIp === '::1') {
    $mobilePairHostIps = function_exists('gethostbynamel') ? (gethostbynamel(gethostname()) ?: []) : [];
    foreach ($mobilePairHostIps as $candidateIp) {
        $candidateIp = (string)$candidateIp;
        if (str_starts_with($candidateIp, '192.168.') || str_starts_with($candidateIp, '10.') || preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $candidateIp)) {
            $mobilePairServerIp = $candidateIp;
            break;
        }
    }
    foreach ($mobilePairHostIps as $candidateIp) {
        if ($mobilePairServerIp === '' && !str_starts_with((string)$candidateIp, '127.')) {
            $mobilePairServerIp = (string)$candidateIp;
            break;
        }
    }
}
$mobilePairPort = (string)($_SERVER['SERVER_PORT'] ?? '');
$mobilePairForwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$mobilePairScheme = ($mobilePairForwardedProto === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $mobilePairPort === '443') ? 'https' : 'http';
$mobilePairPortPart = ($mobilePairPort !== '' && !in_array($mobilePairPort, ['80', '443'], true)) ? ':' . $mobilePairPort : '';
$mobilePairLanEndpoint = $mobilePairServerIp !== '' ? $mobilePairScheme . '://' . $mobilePairServerIp . $mobilePairPortPart . '/api/spends' : '';

$mobilePairExistingToken = '';
try {
    $mobilePairEmail = (string)($currentUserEmail ?? ($_SESSION['user']['email'] ?? ''));
    $mobilePairTokenDb = function_exists('getDB') ? getDB() : null;
    if ($mobilePairEmail !== '' && $mobilePairTokenDb) {
        $mobilePairStmt = $mobilePairTokenDb->prepare("SELECT token_plain FROM client_auth_tokens WHERE email = :e AND app_id = 'spends' AND revoked = 0 AND expires_at > :t AND token_plain <> '' ORDER BY created_at DESC LIMIT 1");
        $mobilePairStmt->bindValue(':e', $mobilePairEmail, PDO::PARAM_STR);
        $mobilePairStmt->bindValue(':t', time(), PDO::PARAM_INT);
        $mobilePairRow = $mobilePairStmt->execute()->fetch(PDO::FETCH_ASSOC);
        if ($mobilePairRow && (string)($mobilePairRow['token_plain'] ?? '') !== '') {
            $mobilePairExistingToken = (string)$mobilePairRow['token_plain'];
        }
    }
} catch (Throwable $e) {
    $mobilePairExistingToken = '';
}
?>
<style>
    /* Mobile pairing modal — same visual language as the Steps companion modal */
    #mobile-app-modal.modal .modal-content.mobile-app-content { max-width:800px; width:min(800px,calc(100vw - 32px)); padding:26px 26px 22px; }
    .mobile-app-content .modal-close { position:absolute; top:16px; right:16px; }
    .mobile-app-content .ma-header { margin:0 0 20px; padding-right:44px; }
    .mobile-app-content .ma-header h1 { font-size:22px; margin:0 0 6px; font-weight:700; letter-spacing:-.3px; color:var(--text-color); }
    .mobile-app-content .ma-header p { margin:0; font-size:14px; line-height:1.5; color:var(--muted-text); }
    .mobile-app-content .ma-grid { display:grid; grid-template-columns:1fr 1.15fr; gap:20px; }
    .mobile-app-content .ma-action { position:relative; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:20px; border:1px solid var(--border-color); border-radius:var(--radius-md); background:var(--group-block-bg); }
    .mobile-app-content .ma-qr-frame { position:relative; width:220px; height:220px; margin:0 0 16px; padding:0; box-sizing:border-box; background:#fff; border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .mobile-app-content .ma-qr-placeholder { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; filter:blur(6px); pointer-events:none; }
    .mobile-app-content .ma-qr-placeholder svg { width:220px; height:220px; }
    .mobile-app-content .ma-qr-code { position:absolute; inset:0; display:none; align-items:center; justify-content:center; }
    .mobile-app-content .ma-qr-code > div, .mobile-app-content .ma-qr-code canvas, .mobile-app-content .ma-qr-code svg { width:220px !important; height:220px !important; max-width:100%; max-height:100%; display:block; }
    .mobile-app-content .ma-qr-generate { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:9px 14px; border:0; border-radius:var(--radius-sm); background:var(--primary-fill); color:var(--primary-ink); font-family:inherit; font-size:13px; font-weight:700; cursor:pointer; white-space:nowrap; box-shadow:0 6px 18px rgba(0,0,0,.28); z-index:1; }
    .mobile-app-content .ma-qr-generate:disabled { opacity:.6; cursor:wait; }
    .mobile-app-content .ma-qr-actions { display:none; flex-direction:column; align-items:stretch; gap:8px; width:min(220px,100%); margin:0 0 14px; }
    .mobile-app-content .ma-qr-actions.is-visible { display:flex; }
    .mobile-app-content .ma-qr-revoke { display:inline-flex; align-items:center; justify-content:center; gap:6px; margin:0; padding:8px 14px; border:1px solid color-mix(in srgb,#ef4444 45%,var(--border-color)); border-radius:var(--radius-sm); background:color-mix(in srgb,#ef4444 12%,var(--surface-solid)); color:var(--danger-text); font-family:inherit; font-size:13px; font-weight:700; cursor:pointer; }
    .mobile-app-content .ma-qr-revoke:disabled { opacity:.6; cursor:wait; }
    .mobile-app-content .ma-copy-link-btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:8px 14px; border:1px solid var(--border-color); border-radius:var(--radius-sm); background:var(--surface-solid); color:var(--primary-text); font-family:inherit; font-size:13px; font-weight:700; cursor:pointer; }
    .mobile-app-content .ma-copy-link-btn:hover { background:color-mix(in srgb,var(--primary-color) 12%,var(--surface-solid)); }
    .mobile-app-content .ma-copy-link-btn.is-copied { background:#166534; border-color:#166534; color:#fff; }
    .mobile-app-content .ma-qr-hint { position:absolute; top:10px; right:10px; width:28px; height:28px; padding:0; border:1px solid var(--border-color); border-radius:50%; background:var(--surface-solid); color:var(--primary-text); display:inline-flex; align-items:center; justify-content:center; cursor:pointer; z-index:2; }
    .mobile-app-content .ma-qr-hint:hover { background:color-mix(in srgb,var(--primary-color) 12%,var(--surface-solid)); }
    .mobile-app-content .ma-action h3 { margin:0 0 6px; font-size:16px; color:var(--text-color); }
    .mobile-app-content .ma-action > p { margin:0 0 6px; font-size:13px; line-height:1.45; color:var(--muted-text); }
    .mobile-app-content .ma-qr-error { margin:6px 0 0; font-size:12px; color:var(--danger-text); }
    .mobile-app-content .ma-features { display:flex; flex-direction:column; gap:10px; }
    .mobile-app-content .ma-feature { display:flex; align-items:flex-start; gap:14px; padding:14px; border:1px solid var(--border-color); border-radius:var(--radius-md); transition:background .2s,border-color .2s; }
    .mobile-app-content .ma-feature:hover { background:color-mix(in srgb,var(--surface-solid) 55%,transparent); border-color:color-mix(in srgb,var(--primary-color) 26%,var(--border-color)); }
    .mobile-app-content .ma-feature-icon { display:flex; align-items:center; justify-content:center; flex:0 0 38px; width:38px; height:38px; border-radius:var(--radius-sm); background:color-mix(in srgb,var(--primary-color) 14%,transparent); color:var(--primary-text); }
    .mobile-app-content .ma-feature-text { min-width:0; text-align:left; }
    .mobile-app-content .ma-feature-text h4 { margin:0 0 3px; font-size:13.5px; font-weight:700; color:var(--text-color); }
    .mobile-app-content .ma-feature-text p { margin:0; font-size:12px; line-height:1.4; color:var(--muted-text); }

    #manual-pair-modal { position:fixed; inset:0; display:none; align-items:center; justify-content:center; padding:16px; z-index:10030; background:rgba(10,14,18,.48); -webkit-backdrop-filter:blur(14px); backdrop-filter:blur(14px); box-sizing:border-box; }
    #manual-pair-modal .manual-pair-content { position:relative; width:min(560px,100%); max-height:calc(100dvh - 32px); padding:24px; overflow:auto; box-sizing:border-box; border:1px solid var(--border-color); border-radius:18px; background:color-mix(in srgb,var(--modal-bg) 92%,var(--surface-solid)); box-shadow:var(--shadow-soft); }
    #manual-pair-modal .manual-pair-steps { margin:0 0 16px; padding-left:20px; display:grid; gap:7px; font-size:13.5px; line-height:1.5; color:var(--text-color); }
    #manual-pair-modal .manual-pair-fields { display:grid; gap:12px; }
    #manual-pair-modal .manual-pair-row { display:flex; gap:10px; align-items:stretch; }
    #manual-pair-modal .manual-pair-field { flex:1; min-width:0; }
    #manual-pair-modal .manual-pair-field label { display:block; margin:0 0 6px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--muted-text); }
    #manual-pair-modal .manual-pair-field pre { margin:0; padding:10px 12px; min-height:42px; box-sizing:border-box; border:1px solid var(--border-color); border-radius:10px; background:var(--group-block-bg); color:var(--text-color); font:12.5px/1.45 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; word-break:break-all; white-space:pre-wrap; }
    #manual-pair-modal .manual-pair-footer { display:flex; justify-content:flex-end; margin-top:16px; }
    #manual-pair-modal .manual-pair-regen-btn { display:inline-flex; align-items:center; gap:8px; padding:10px 16px; border:0; border-radius:10px; background:var(--primary-fill); color:var(--primary-ink); font-family:inherit; font-size:13.5px; font-weight:700; cursor:pointer; }
    #manual-pair-modal .manual-pair-regen-btn:disabled { opacity:.6; cursor:wait; }
    #manual-pair-modal .manual-pair-regen-btn.is-busy svg { animation:maSpin .9s linear infinite; }
    @keyframes maSpin { from { transform:rotate(0); } to { transform:rotate(360deg); } }
    .ma-copy-btn { position:relative; flex:0 0 auto; align-self:flex-end; width:42px; height:42px; border:1px solid var(--border-color); border-radius:10px; background:var(--group-block-bg); color:var(--primary-text); display:inline-flex; align-items:center; justify-content:center; cursor:pointer; transition:background .2s,color .2s,border-color .2s,transform .12s; }
    .ma-copy-btn:hover { background:color-mix(in srgb,var(--primary-color) 12%,var(--surface-solid)); border-color:color-mix(in srgb,var(--primary-color) 40%,var(--border-color)); }
    .ma-copy-btn:active { transform:scale(.94); }
    .ma-copy-btn .ma-copy-check { position:absolute; opacity:0; transform:scale(.4); transition:opacity .18s,transform .22s; }
    .ma-copy-btn.is-copied { background:#166534; border-color:#166534; color:#fff; }
    .ma-copy-btn.is-copied .ma-copy-icon { opacity:0; }
    .ma-copy-btn.is-copied .ma-copy-check { opacity:1; transform:scale(1); }
    @media (max-width:700px) {
        .mobile-app-content .ma-grid { grid-template-columns:1fr; }
        #manual-pair-modal .manual-pair-row { flex-direction:column; }
        #manual-pair-modal .ma-copy-btn { align-self:stretch; width:100%; }
    }
</style>

<div id="mobile-app-modal" class="modal">
    <div class="modal-content mobile-app-content">
        <button class="modal-close" type="button" onclick="closeMobileAppModal()" aria-label="<?= htmlspecialchars($translations['close'] ?? 'Close') ?>">×</button>

        <div class="ma-header">
            <h1><?= htmlspecialchars($translations['mobile_app_modal_title'] ?? 'Mobile app') ?></h1>
            <p><?= htmlspecialchars($translations['mobile_app_modal_desc'] ?? 'Connect the companion mobile app and log expenses straight from your phone.') ?></p>
        </div>

        <div class="ma-grid">
            <div class="ma-action">
                <button type="button" id="mobile-app-manual-hint" class="ma-qr-hint" title="<?= htmlspecialchars($translations['mobile_app_manual_hint_title'] ?? 'Manual pairing') ?>" aria-label="<?= htmlspecialchars($translations['mobile_app_manual_hint_title'] ?? 'Manual pairing') ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </button>
                <div class="ma-qr-frame">
                    <div class="ma-qr-placeholder" aria-hidden="true">
                        <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="100" height="100" fill="white"/>
                            <path d="M10 10H30V30H10V10ZM70 10H90V30H70V10ZM10 70H30V90H10V70ZM40 40H60V60H40V40ZM40 10H60V20H40V10ZM10 40H20V60H10V40ZM80 40H90V60H80V40ZM40 80H60V90H40V80ZM80 80H90V90H80V80Z" fill="#121212"/>
                        </svg>
                    </div>
                    <div class="ma-qr-code" id="mobile-app-qr-code"></div>
                    <button type="button" id="mobile-app-qr-generate" class="ma-qr-generate">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                        <?= htmlspecialchars($translations['mobile_app_qr_generate'] ?? 'Generate') ?>
                    </button>
                </div>
                <div class="ma-qr-actions" id="mobile-app-qr-actions">
                    <button type="button" id="mobile-app-copy-link" class="ma-copy-link-btn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        <span><?= htmlspecialchars($translations['mobile_app_copy_link'] ?? 'Copy pairing link') ?></span>
                    </button>
                    <button type="button" id="mobile-app-token-revoke" class="ma-qr-revoke">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg>
                        <?= htmlspecialchars($translations['mobile_app_token_revoke'] ?? 'Revoke token') ?>
                    </button>
                </div>
                <h3><?= htmlspecialchars($translations['mobile_app_scan_title'] ?? 'Scan to pair') ?></h3>
                <p><?= htmlspecialchars($translations['mobile_app_scan_desc'] ?? 'Open the app and scan this code to sync instantly.') ?></p>
                <p class="ma-qr-error" id="mobile-app-qr-error" hidden></p>
            </div>

            <div class="ma-features">
                <div class="ma-feature">
                    <div class="ma-feature-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    </div>
                    <div class="ma-feature-text">
                        <h4><?= htmlspecialchars($translations['mobile_app_feature_log_title'] ?? 'Quick expense logging') ?></h4>
                        <p><?= htmlspecialchars($translations['mobile_app_feature_log_desc'] ?? 'Add an expense in a couple of taps, even offline.') ?></p>
                    </div>
                </div>
                <div class="ma-feature">
                    <div class="ma-feature-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M9 15h6"/></svg>
                    </div>
                    <div class="ma-feature-text">
                        <h4><?= htmlspecialchars($translations['mobile_app_feature_receipts_title'] ?? 'Receipt OCR') ?></h4>
                        <p><?= htmlspecialchars($translations['mobile_app_feature_receipts_desc'] ?? 'Photograph a receipt and let the app fill in store and amount.') ?></p>
                    </div>
                </div>
                <div class="ma-feature">
                    <div class="ma-feature-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                    <div class="ma-feature-text">
                        <h4><?= htmlspecialchars($translations['mobile_app_feature_budget_title'] ?? 'Subscriptions & budget') ?></h4>
                        <p><?= htmlspecialchars($translations['mobile_app_feature_budget_desc'] ?? 'See upcoming recurring charges and how much is left in the budget.') ?></p>
                    </div>
                </div>
                <div class="ma-feature">
                    <div class="ma-feature-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <div class="ma-feature-text">
                        <h4><?= htmlspecialchars($translations['mobile_app_feature_secure_title'] ?? 'Secure token auth') ?></h4>
                        <p><?= htmlspecialchars($translations['mobile_app_feature_secure_desc'] ?? 'A generated token scoped to this app — revoke it any time.') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="manual-pair-modal" aria-hidden="true">
    <div class="manual-pair-content">
        <button type="button" id="manual-pair-close" class="modal-close" aria-label="<?= htmlspecialchars($translations['close'] ?? 'Close') ?>">×</button>
        <div class="ma-header">
            <h1><?= htmlspecialchars($translations['manual_pair_title'] ?? 'Manual pairing') ?></h1>
            <p><?= htmlspecialchars($translations['manual_pair_intro'] ?? 'If QR scanning is not available, connect the app manually:') ?></p>
        </div>
        <ol class="manual-pair-steps">
            <li><?= htmlspecialchars($translations['manual_pair_step1'] ?? 'Install the companion app on your phone.') ?></li>
            <li><?= htmlspecialchars($translations['manual_pair_step2'] ?? 'Open "Pair with server" and paste the endpoint below.') ?></li>
            <li><?= htmlspecialchars($translations['manual_pair_step3'] ?? 'Paste the access token and confirm.') ?></li>
        </ol>
        <div class="manual-pair-fields">
            <div class="manual-pair-row">
                <div class="manual-pair-field">
                    <label><?= htmlspecialchars($translations['manual_pair_endpoint_label'] ?? 'Server endpoint') ?></label>
                    <pre id="manual-pair-endpoint"></pre>
                </div>
                <button type="button" class="ma-copy-btn" data-copy-target="manual-pair-endpoint" aria-label="<?= htmlspecialchars($translations['copy'] ?? 'Copy') ?>">
                    <svg class="ma-copy-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    <svg class="ma-copy-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </button>
            </div>
            <div class="manual-pair-row">
                <div class="manual-pair-field">
                    <label><?= htmlspecialchars($translations['manual_pair_link_label'] ?? 'Connection link') ?></label>
                    <pre id="manual-pair-link"></pre>
                </div>
                <button type="button" class="ma-copy-btn" data-copy-target="manual-pair-link" aria-label="<?= htmlspecialchars($translations['copy'] ?? 'Copy') ?>">
                    <svg class="ma-copy-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    <svg class="ma-copy-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </button>
            </div>
            <div class="manual-pair-row">
                <div class="manual-pair-field">
                    <label><?= htmlspecialchars($translations['manual_pair_token_label'] ?? 'Access token') ?></label>
                    <pre id="manual-pair-token"></pre>
                </div>
                <button type="button" class="ma-copy-btn" data-copy-target="manual-pair-token" aria-label="<?= htmlspecialchars($translations['copy'] ?? 'Copy') ?>">
                    <svg class="ma-copy-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    <svg class="ma-copy-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </button>
            </div>
        </div>
        <div class="manual-pair-footer">
            <button type="button" id="manual-pair-regenerate" class="manual-pair-regen-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                <?= htmlspecialchars($translations['manual_pair_regenerate'] ?? 'Generate a new token') ?>
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('mobile-app-modal');
    const qrFrame = document.getElementById('mobile-app-qr-code');
    const placeholder = modal ? modal.querySelector('.ma-qr-placeholder') : null;
    const genBtn = document.getElementById('mobile-app-qr-generate');
    const revokeBtn = document.getElementById('mobile-app-token-revoke');
    const copyLinkBtn = document.getElementById('mobile-app-copy-link');
    const qrActions = document.getElementById('mobile-app-qr-actions');
    const hintBtn = document.getElementById('mobile-app-manual-hint');
    const errEl = document.getElementById('mobile-app-qr-error');
    const hintModal = document.getElementById('manual-pair-modal');
    const hintClose = document.getElementById('manual-pair-close');
    const hintEndpoint = document.getElementById('manual-pair-endpoint');
    const hintLink = document.getElementById('manual-pair-link');
    const hintToken = document.getElementById('manual-pair-token');
    const regenBtn = document.getElementById('manual-pair-regenerate');

    const lanEndpoint = <?= json_encode($mobilePairLanEndpoint, JSON_HEX_TAG) ?>;
    const currentHost = window.location.hostname;
    const endpoint = (lanEndpoint && (currentHost === 'localhost' || currentHost === '127.0.0.1' || currentHost === '::1'))
        ? lanEndpoint
        : window.location.origin + '/api/spends';
    const noTokenMsg = <?= json_encode($translations['manual_pair_generate_first'] ?? 'Generate a QR code first.', JSON_HEX_TAG) ?>;
    const copyLabel = <?= json_encode($translations['copy'] ?? 'Copy', JSON_HEX_TAG) ?>;
    const copiedLabel = <?= json_encode($translations['copied'] ?? 'Copied', JSON_HEX_TAG) ?>;

    let pairing = { token: '', link: '' };
    let cachedToken = <?= json_encode($mobilePairExistingToken, JSON_HEX_TAG) ?>;

    function linkFor(token) {
        return token ? ('spends://auth?endpoint=' + encodeURIComponent(endpoint) + '&token=' + encodeURIComponent(token)) : '';
    }
    function setPairingToken(token) {
        cachedToken = String(token || '');
        pairing = { token: cachedToken, link: linkFor(cachedToken) };
    }
    function refreshHintFields() {
        if (hintEndpoint) hintEndpoint.textContent = endpoint;
        if (hintLink) hintLink.textContent = pairing.link || '—';
        if (hintToken) hintToken.textContent = pairing.token || noTokenMsg;
    }
    async function copyText(text, btn) {
        if (!text || text === '—' || text === noTokenMsg) return false;
        try {
            await navigator.clipboard.writeText(text);
        } catch (error) {
            const area = document.createElement('textarea');
            area.value = text;
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            try { document.execCommand('copy'); } catch (e) {}
            area.remove();
        }
        if (btn) {
            btn.classList.add('is-copied');
            setTimeout(function () { btn.classList.remove('is-copied'); }, 1600);
        }
        return true;
    }
    function showError(message) {
        if (!errEl) return;
        errEl.textContent = message || '';
        errEl.hidden = !message;
    }
    async function ensureQrStyling() {
        if (typeof window.apEnsureQrCodeStyling === 'function') return window.apEnsureQrCodeStyling();
        if (typeof QRCodeStyling !== 'undefined') return QRCodeStyling;
        await new Promise(function (resolve, reject) {
            const script = document.createElement('script');
            script.src = '/project/assets/qr-code-loader.js?v=1';
            script.async = true;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
        if (typeof window.apEnsureQrCodeStyling === 'function') return window.apEnsureQrCodeStyling();
        throw new Error('qr-loader-missing');
    }
    async function renderQr(hostEl, data) {
        try {
            const QRCodeStylingCtor = await ensureQrStyling();
            hostEl.innerHTML = '';
            const qr = new QRCodeStylingCtor({
                width: 220, height: 220, type: 'svg', data: data, margin: 0,
                qrOptions: { errorCorrectionLevel: 'H' },
                dotsOptions: { type: 'rounded', gradient: { type: 'linear', rotation: Math.PI / 4, colorStops: [
                    { offset: 0, color: '#7c3aed' }, { offset: 0.5, color: '#2563eb' }, { offset: 1, color: '#06b6d4' }
                ] } },
                cornersSquareOptions: { type: 'extra-rounded', color: '#2563eb' },
                cornersDotOptions: { type: 'dot', color: '#7c3aed' },
                backgroundOptions: { color: 'rgba(255,255,255,0)' }
            });
            qr.append(hostEl);
        } catch (error) {
            hostEl.innerHTML = '<p style="color:#b91c1c;padding:12px;font-size:12px;">' + <?= json_encode($translations['mobile_app_qr_unavailable'] ?? 'QR code unavailable', JSON_HEX_TAG) ?> + '</p>';
        }
    }
    async function showPairingQr() {
        if (!pairing.token || !qrFrame) return;
        if (placeholder) placeholder.style.display = 'none';
        qrFrame.style.display = 'flex';
        await renderQr(qrFrame, pairing.link);
        if (genBtn) genBtn.style.display = 'none';
        if (qrActions) qrActions.classList.add('is-visible');
        refreshHintFields();
    }
    function clearPairingUi() {
        setPairingToken('');
        if (qrFrame) { qrFrame.innerHTML = ''; qrFrame.style.display = 'none'; }
        if (placeholder) placeholder.style.display = '';
        if (genBtn) genBtn.style.display = '';
        if (qrActions) qrActions.classList.remove('is-visible');
        refreshHintFields();
    }
    async function shellCsrf() {
        try {
            const res = await fetch('/csrf', { method: 'GET', credentials: 'same-origin', cache: 'no-store' });
            const data = await res.json().catch(function () { return null; });
            return data && data.csrf ? String(data.csrf) : '';
        } catch (error) {
            return '';
        }
    }
    async function tokenRequest(action, extra) {
        const csrf = await shellCsrf();
        const form = new FormData();
        form.append('action', action);
        form.append(action, '1');
        form.append('ajax', '1');
        form.append('token_app', 'spends');
        if (csrf) form.append('_csrf', csrf);
        Object.keys(extra || {}).forEach(function (key) { form.append(key, String(extra[key])); });
        const res = await fetch('/main', { method: 'POST', body: form, credentials: 'same-origin' });
        return res.json().catch(function () { return null; });
    }
    async function generateToken(forceNew) {
        showError('');
        const data = await tokenRequest('generate_mcp_token', {
            mcp_token_name: 'Spends mobile',
            force_new: forceNew ? '1' : ''
        });
        if (!data || !data.success || !data.token) {
            throw new Error((data && (data.error || data.message)) || <?= json_encode($translations['mobile_app_token_error'] ?? 'Token generation failed', JSON_HEX_TAG) ?>);
        }
        setPairingToken(data.token);
        await showPairingQr();
        return data.token;
    }
    async function revokeToken() {
        const data = await tokenRequest('revoke_mcp_token', {});
        if (!data || !data.success) {
            throw new Error((data && (data.error || data.message)) || <?= json_encode($translations['mobile_app_token_error'] ?? 'Token generation failed', JSON_HEX_TAG) ?>);
        }
        clearPairingUi();
    }

    window.openMobileAppModal = function () {
        if (typeof showModal === 'function') showModal('mobile-app-modal');
        else if (modal) { modal.style.display = 'flex'; document.body.classList.add('modal-open'); }
        if (cachedToken) {
            setPairingToken(cachedToken);
            showPairingQr().catch(function () {});
        } else {
            clearPairingUi();
        }
    };
    window.closeMobileAppModal = function () {
        if (typeof closeModal === 'function') closeModal('mobile-app-modal');
        else if (modal) { modal.style.display = 'none'; document.body.classList.remove('modal-open'); }
    };

    function openHint() {
        if (!hintModal) return;
        refreshHintFields();
        hintModal.style.display = 'flex';
        hintModal.setAttribute('aria-hidden', 'false');
    }
    function closeHint() {
        if (!hintModal) return;
        hintModal.style.display = 'none';
        hintModal.setAttribute('aria-hidden', 'true');
    }
    hintBtn && hintBtn.addEventListener('click', openHint);
    hintClose && hintClose.addEventListener('click', closeHint);
    hintModal && hintModal.addEventListener('click', function (event) { if (event.target === hintModal) closeHint(); });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        if (hintModal && hintModal.style.display === 'flex') { closeHint(); return; }
        if (modal && modal.style.display === 'flex') window.closeMobileAppModal();
    });

    document.querySelectorAll('#manual-pair-modal .ma-copy-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const target = document.getElementById(btn.getAttribute('data-copy-target'));
            const text = target ? target.textContent.trim() : '';
            const ok = await copyText(text, btn);
            if (!ok) return;
            btn.setAttribute('aria-label', copiedLabel);
            setTimeout(function () { btn.setAttribute('aria-label', copyLabel); }, 1600);
        });
    });
    copyLinkBtn && copyLinkBtn.addEventListener('click', async function () {
        await copyText(pairing.link, copyLinkBtn);
    });

    genBtn && genBtn.addEventListener('click', async function () {
        genBtn.disabled = true;
        try { await generateToken(false); }
        catch (error) { showError(String(error && error.message ? error.message : error)); }
        finally { genBtn.disabled = false; }
    });
    revokeBtn && revokeBtn.addEventListener('click', async function () {
        revokeBtn.disabled = true;
        try { await revokeToken(); }
        catch (error) { showError(String(error && error.message ? error.message : error)); }
        finally { revokeBtn.disabled = false; }
    });
    regenBtn && regenBtn.addEventListener('click', async function () {
        regenBtn.disabled = true;
        regenBtn.classList.add('is-busy');
        try { await generateToken(true); refreshHintFields(); }
        catch (error) { showError(String(error && error.message ? error.message : error)); }
        finally {
            regenBtn.disabled = false;
            regenBtn.classList.remove('is-busy');
        }
    });
})();
</script>
