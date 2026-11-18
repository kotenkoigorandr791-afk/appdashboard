<?php
/**
 * Mobile app pairing modal.
 *
 * The pairing flow is manual only: the dashboard issues an app-scoped device
 * token and shows the endpoint, connection link and token for the phone.
 * Several phones can be paired at the same time; every active token is listed
 * under "Connected devices" and can be disconnected individually.
 */
$mobilePairRoot = dirname(__DIR__, 3);
// The endpoint must be reachable from the phone, not from inside the container:
// SERVER_ADDR/SERVER_PORT are the Docker bridge IP and nginx's internal port.
require_once dirname(__DIR__, 3) . '/lib/mobile_pair_endpoint.php';
$mobilePairLanEndpoint = mobile_pair_endpoint('spends');

// Release APK of the companion app.
$mobilePairApkFile = $mobilePairRoot . '/downloads/spends-mobile.apk';
$mobilePairApkMetaFile = $mobilePairRoot . '/downloads/spends-mobile.json';
$mobilePairApkAvailable = is_file($mobilePairApkFile);
$mobilePairApkMeta = $mobilePairApkAvailable && is_file($mobilePairApkMetaFile)
    ? (json_decode((string)file_get_contents($mobilePairApkMetaFile), true) ?: [])
    : [];
$mobilePairApkVersion = (string)($mobilePairApkMeta['version'] ?? '');
$mobilePairApkBuiltAt = (int)($mobilePairApkMeta['built_at'] ?? 0);
$mobilePairApkPath = '/downloads/spends-mobile.apk';
?>
<style>
    /* Mobile pairing modal — shared layout with the Steps companion modal */
    #mobile-app-modal.modal .modal-content.mobile-app-content { max-width:800px; width:min(800px,calc(100vw - 32px)); padding:26px 26px 22px; }
    .mobile-app-content .modal-close { position:absolute; top:16px; right:16px; }
    .mobile-app-content .ma-header { margin:0 0 20px; padding-right:44px; }
    .mobile-app-content .ma-header h1 { font-size:22px; margin:0 0 6px; font-weight:700; letter-spacing:-.3px; color:var(--text-color); }
    .mobile-app-content .ma-header p { margin:0; font-size:14px; line-height:1.5; color:var(--muted-text); }
    .mobile-app-content .ma-panel { margin:0 0 18px; padding:14px 16px; border:1px solid var(--border-color); border-radius:var(--radius-md); background:var(--group-block-bg); }
    .mobile-app-content .ma-panel-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin:0 0 6px; }
    .mobile-app-content .ma-panel-head h3 { margin:0; font-size:15px; color:var(--text-color); }
    .mobile-app-content .ma-empty { margin:4px 0 0; font-size:12px; opacity:.7; }
    .mobile-app-content .ma-device-list { list-style:none; margin:0; padding:0; }
    .mobile-app-content .ma-device { padding:10px 0; border-top:1px solid color-mix(in srgb,var(--border-color) 70%,transparent); }
    .mobile-app-content .ma-device-top { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .mobile-app-content .ma-device-name { font-size:13px; font-weight:700; color:var(--text-color); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .mobile-app-content .ma-device-state { flex:0 0 auto; font-size:11px; opacity:.75; }
    .mobile-app-content .ma-device-meta { font-size:11px; opacity:.7; margin:2px 0 8px; }
    .mobile-app-content .ma-btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:6px 12px; border:1px solid var(--border-color); border-radius:var(--radius-sm); background:var(--surface-solid); color:var(--primary-text); font-family:inherit; font-size:12px; font-weight:700; cursor:pointer; }
    .mobile-app-content .ma-btn:hover { background:color-mix(in srgb,var(--primary-color) 12%,var(--surface-solid)); }
    .mobile-app-content .ma-btn:disabled { opacity:.6; cursor:wait; }
    .mobile-app-content .ma-btn-danger { border-color:color-mix(in srgb,#ef4444 45%,var(--border-color)); background:color-mix(in srgb,#ef4444 12%,var(--surface-solid)); color:var(--danger-text); }
    .mobile-app-content .ma-grid { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1.15fr); gap:20px; align-items:stretch; }
    .mobile-app-content .ma-action { display:flex; flex-direction:column; align-items:center; gap:10px; min-width:0; text-align:center; padding:20px; border:1px solid var(--border-color); border-radius:var(--radius-md); background:var(--group-block-bg); }
    .mobile-app-content .ma-action-icon { display:flex; align-items:center; justify-content:center; width:64px; height:64px; border-radius:50%; background:color-mix(in srgb,var(--primary-color) 14%,transparent); color:var(--primary-text); }
    .mobile-app-content .ma-action h3 { margin:0; font-size:16px; color:var(--text-color); }
    .mobile-app-content .ma-action > p { margin:0; font-size:13px; line-height:1.45; color:var(--muted-text); }
    .mobile-app-content .ma-primary { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:10px 16px; border:0; border-radius:var(--radius-sm); background:var(--primary-fill); color:var(--primary-ink); font-family:inherit; font-size:13px; font-weight:700; cursor:pointer; }
    .mobile-app-content .ma-primary:disabled { opacity:.6; cursor:wait; }
    .mobile-app-content .ma-pair-fields { display:grid; gap:10px; width:100%; margin:4px 0 0; text-align:left; }
    .mobile-app-content .ma-pair-row { display:flex; gap:8px; align-items:stretch; }
    .mobile-app-content .ma-pair-field { flex:1; min-width:0; }
    .mobile-app-content .ma-pair-field label { display:block; margin:0 0 4px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--muted-text); }
    .mobile-app-content .ma-pair-field pre { margin:0; padding:9px 11px; min-height:40px; box-sizing:border-box; border:1px solid var(--border-color); border-radius:10px; background:var(--surface-solid); color:var(--text-color); font:12px/1.45 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; word-break:break-all; white-space:pre-wrap; }
    .mobile-app-content .ma-error { margin:6px 0 0; font-size:12px; color:var(--danger-text); }
    .mobile-app-content .ma-download { display:inline-flex; align-items:center; gap:8px; margin:6px 0 0; padding:10px 14px; border:1px solid color-mix(in srgb,var(--primary-color) 45%,var(--border-color)); border-radius:var(--radius-sm); background:color-mix(in srgb,var(--primary-color) 12%,var(--surface-solid)); color:var(--primary-text); font-size:13px; font-weight:700; text-decoration:none; }
    .mobile-app-content .ma-download:hover { background:color-mix(in srgb,var(--primary-color) 22%,var(--surface-solid)); }
    .mobile-app-content .ma-meta { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--muted-text); }
    .mobile-app-content .ma-features { display:flex; flex-direction:column; justify-content:space-between; gap:10px; min-width:0; min-height:100%; }
    .mobile-app-content .ma-feature { display:flex; flex:1 1 0; align-items:center; gap:14px; min-height:76px; padding:14px; border:1px solid var(--border-color); border-radius:var(--radius-md); box-sizing:border-box; }
    .mobile-app-content .ma-feature:hover { background:color-mix(in srgb,var(--surface-solid) 55%,transparent); border-color:color-mix(in srgb,var(--primary-color) 26%,var(--border-color)); }
    .mobile-app-content .ma-feature-icon { display:flex; align-items:center; justify-content:center; flex:0 0 38px; width:38px; height:38px; border-radius:var(--radius-sm); background:color-mix(in srgb,var(--primary-color) 14%,transparent); color:var(--primary-text); }
    .mobile-app-content .ma-feature-text { min-width:0; text-align:left; }
    .mobile-app-content .ma-feature-text h4 { margin:0 0 3px; font-size:13.5px; font-weight:700; color:var(--text-color); }
    .mobile-app-content .ma-feature-text p { margin:0; font-size:12px; line-height:1.4; color:var(--muted-text); }
    .mobile-app-content .ma-copy-btn { flex:0 0 auto; align-self:flex-end; width:40px; height:40px; border:1px solid var(--border-color); border-radius:10px; background:var(--surface-solid); color:var(--primary-text); display:inline-flex; align-items:center; justify-content:center; cursor:pointer; }
    .mobile-app-content .ma-copy-btn:hover { background:color-mix(in srgb,var(--primary-color) 12%,var(--surface-solid)); }
    .mobile-app-content .ma-copy-btn.is-copied { background:#166534; border-color:#166534; color:#fff; }
    @media (max-width:700px) {
        .mobile-app-content .ma-grid { grid-template-columns:minmax(0,1fr); }
        .mobile-app-content .ma-features { min-height:0; }
        .mobile-app-content .ma-feature { flex:0 0 auto; }
        .mobile-app-content .ma-pair-row { flex-direction:column; }
        .mobile-app-content .ma-copy-btn { width:100%; height:36px; }
    }
</style>

<div id="mobile-app-modal" class="modal">
    <div class="modal-content mobile-app-content">
        <button class="modal-close" type="button" onclick="closeMobileAppModal()" aria-label="<?= htmlspecialchars($translations['close'] ?? 'Close') ?>">×</button>

        <div class="ma-header">
            <h1><?= htmlspecialchars($translations['mobile_app_modal_title'] ?? 'Mobile app') ?></h1>
            <p><?= htmlspecialchars($translations['mobile_app_modal_desc'] ?? 'Connect the companion mobile app and log expenses straight from your phone.') ?></p>
        </div>

        <section class="ma-panel" id="mobile-app-devices">
            <div class="ma-panel-head">
                <h3><?= htmlspecialchars($translations['mobile_app_devices_title'] ?? 'Connected devices') ?></h3>
                <button type="button" id="mobile-app-devices-refresh" class="ma-btn ma-btn-sm"><?= htmlspecialchars($translations['mobile_app_devices_refresh'] ?? 'Refresh') ?></button>
            </div>
            <p class="ma-empty" id="mobile-app-devices-empty"><?= htmlspecialchars($translations['mobile_app_devices_empty'] ?? 'No devices connected yet.') ?></p>
            <ul class="ma-device-list" id="mobile-app-devices-list"></ul>
            <p class="ma-error" id="mobile-app-devices-error" hidden></p>
        </section>

        <div class="ma-grid">
            <div class="ma-action">
                <div class="ma-action-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                </div>
                <h3><?= htmlspecialchars($translations['mobile_app_connect_title'] ?? 'Connect a new device') ?></h3>
                <p><?= htmlspecialchars($translations['mobile_app_connect_desc'] ?? 'Create a connection link and open it on your phone. The app connects automatically.') ?></p>
                <button type="button" id="mobile-app-connect" class="ma-primary"><?= htmlspecialchars($translations['mobile_app_connect_button'] ?? 'Create a pairing token') ?></button>

                <div class="ma-pair-fields" id="mobile-app-pair-fields" hidden>
                    <div class="ma-pair-row">
                        <div class="ma-pair-field">
                            <label><?= htmlspecialchars($translations['manual_pair_link_label'] ?? 'Connection link') ?></label>
                            <pre id="mobile-app-pair-link"></pre>
                        </div>
                        <button type="button" class="ma-copy-btn" data-copy-target="mobile-app-pair-link" aria-label="<?= htmlspecialchars($translations['copy'] ?? 'Copy') ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </button>
                    </div>
                </div>

                <p class="ma-error" id="mobile-app-pair-error" hidden></p>

                <?php if ($mobilePairApkAvailable): ?>
                <a class="ma-download" href="<?= htmlspecialchars($mobilePairApkPath) ?>" download="spends-mobile.apk">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <?= htmlspecialchars($translations['mobile_app_download'] ?? 'Download mobile app') ?>
                </a>
                <?php if ($mobilePairApkVersion !== '' || $mobilePairApkBuiltAt > 0): ?>
                <div class="ma-meta">
                    <?php if ($mobilePairApkVersion !== ''): ?><span>v<?= htmlspecialchars($mobilePairApkVersion) ?></span><?php endif; ?>
                    <?php if ($mobilePairApkVersion !== '' && $mobilePairApkBuiltAt > 0): ?><span>•</span><?php endif; ?>
                    <?php if ($mobilePairApkBuiltAt > 0): ?><span><?= htmlspecialchars($translations['mobile_app_updated'] ?? 'Updated') ?> <?= htmlspecialchars(date('d.m.Y', $mobilePairApkBuiltAt)) ?></span><?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
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

<script>
(function () {
    const modal = document.getElementById('mobile-app-modal');
    if (!modal) return;
    const connectBtn = document.getElementById('mobile-app-connect');
    const fields = document.getElementById('mobile-app-pair-fields');
    const linkEl = document.getElementById('mobile-app-pair-link');
    const pairError = document.getElementById('mobile-app-pair-error');
    const devicesList = document.getElementById('mobile-app-devices-list');
    const devicesEmpty = document.getElementById('mobile-app-devices-empty');
    const devicesError = document.getElementById('mobile-app-devices-error');
    const devicesRefresh = document.getElementById('mobile-app-devices-refresh');
    const endpoint = <?= json_encode($mobilePairLanEndpoint, JSON_HEX_TAG) ?> || (window.location.origin + '/api/spends');
    const t = {
        copy: <?= json_encode($translations['copy'] ?? 'Copy', JSON_HEX_TAG) ?>,
        copied: <?= json_encode($translations['copied'] ?? 'Copied', JSON_HEX_TAG) ?>,
        tokenError: <?= json_encode($translations['mobile_app_token_error'] ?? 'Token generation failed', JSON_HEX_TAG) ?>,
        devicesError: <?= json_encode($translations['mobile_app_devices_error'] ?? 'Could not load devices.', JSON_HEX_TAG) ?>,
        connected: <?= json_encode($translations['mobile_app_devices_state_active'] ?? 'Connected', JSON_HEX_TAG) ?>,
        waiting: <?= json_encode($translations['mobile_app_devices_state_pending'] ?? 'Waiting for the phone', JSON_HEX_TAG) ?>,
        created: <?= json_encode($translations['mobile_app_devices_created'] ?? 'Added {t}', JSON_HEX_TAG) ?>,
        lastUsed: <?= json_encode($translations['mobile_app_devices_last_used'] ?? 'Last used {t}', JSON_HEX_TAG) ?>,
        disconnect: <?= json_encode($translations['mobile_app_devices_disconnect'] ?? 'Disconnect', JSON_HEX_TAG) ?>
    };

    function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
    function fmt(unix) { return unix ? new Date(unix * 1000).toLocaleString() : ''; }
    function showPairError(message) { pairError.textContent = message || ''; pairError.hidden = !message; }
    function showDevicesError(message) { devicesError.textContent = message || ''; devicesError.hidden = !message; }

    async function copyText(text, btn) {
        if (!text) return;
        try { await navigator.clipboard.writeText(text); }
        catch (error) {
            const area = document.createElement('textarea');
            area.value = text; area.style.position = 'fixed'; area.style.opacity = '0';
            document.body.appendChild(area); area.select();
            try { document.execCommand('copy'); } catch (e) {}
            area.remove();
        }
        btn.classList.add('is-copied');
        setTimeout(function () { btn.classList.remove('is-copied'); }, 1600);
    }

    async function csrf() {
        try {
            const res = await fetch('/csrf', { method: 'GET', credentials: 'same-origin', cache: 'no-store' });
            const data = await res.json().catch(function () { return null; });
            return data && data.csrf ? String(data.csrf) : '';
        } catch (error) { return ''; }
    }
    async function request(action, extra) {
        const token = await csrf();
        const form = new FormData();
        form.append('action', action);
        form.append(action, '1');
        form.append('ajax', '1');
        form.append('token_app', 'spends');
        if (token) form.append('_csrf', token);
        Object.keys(extra || {}).forEach(function (key) { form.append(key, String(extra[key])); });
        const res = await fetch('/main', { method: 'POST', body: form, credentials: 'same-origin' });
        return res.json().catch(function () { return null; });
    }

    function renderDevices(devices) {
        devicesList.innerHTML = '';
        devicesEmpty.style.display = devices.length ? 'none' : 'block';
        devices.forEach(function (device) {
            const li = document.createElement('li');
            li.className = 'ma-device';
            const top = document.createElement('div');
            top.className = 'ma-device-top';
            const name = document.createElement('strong');
            name.className = 'ma-device-name';
            name.textContent = device.name || 'Mobile app';
            const state = document.createElement('span');
            state.className = 'ma-device-state';
            state.textContent = device.claimed ? t.connected : t.waiting;
            top.appendChild(name); top.appendChild(state);

            const meta = document.createElement('div');
            meta.className = 'ma-device-meta';
            const parts = [];
            if (device.created_at) parts.push(t.created.replace('{t}', fmt(device.created_at)));
            if (device.claimed && device.last_used_at) parts.push(t.lastUsed.replace('{t}', fmt(device.last_used_at)));
            meta.textContent = parts.join(' · ');

            const row = document.createElement('div');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ma-btn ma-btn-danger';
            btn.textContent = t.disconnect;
            btn.addEventListener('click', async function () {
                btn.disabled = true;
                showDevicesError('');
                try {
                    const data = await request('revoke_mcp_token', { token_hash: device.token_hash });
                    if (!data || !data.success) throw new Error((data && (data.error || data.message)) || t.devicesError);
                    await fetchDevices();
                } catch (error) {
                    showDevicesError(String(error && error.message ? error.message : error));
                } finally { btn.disabled = false; }
            });
            row.appendChild(btn);
            li.appendChild(top); li.appendChild(meta); li.appendChild(row);
            devicesList.appendChild(li);
        });
    }
    async function fetchDevices() {
        showDevicesError('');
        try {
            const data = await request('list_mobile_tokens', {});
            if (!data || !data.success) throw new Error((data && (data.error || data.message)) || t.devicesError);
            renderDevices(Array.isArray(data.devices) ? data.devices : []);
        } catch (error) {
            showDevicesError(String(error && error.message ? error.message : error));
        }
    }

    connectBtn.addEventListener('click', async function () {
        connectBtn.disabled = true;
        showPairError('');
        try {
            const data = await request('generate_mcp_token', { mcp_token_name: 'Spends mobile' });
            if (!data || !data.success || !data.token) throw new Error((data && (data.error || data.message)) || t.tokenError);
            linkEl.textContent = 'spends://auth?endpoint=' + encodeURIComponent(endpoint) + '&token=' + encodeURIComponent(data.token);
            fields.hidden = false;
            await fetchDevices();
        } catch (error) {
            showPairError(String(error && error.message ? error.message : error));
        } finally { connectBtn.disabled = false; }
    });

    modal.querySelectorAll('.ma-copy-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = document.getElementById(btn.getAttribute('data-copy-target'));
            copyText(target ? target.textContent.trim() : '', btn);
        });
    });
    devicesRefresh.addEventListener('click', fetchDevices);

    window.openMobileAppModal = function () {
        if (typeof showModal === 'function') showModal('mobile-app-modal');
        else { modal.style.display = 'flex'; document.body.classList.add('modal-open'); }
        fetchDevices();
    };
    window.closeMobileAppModal = function () {
        if (typeof closeModal === 'function') closeModal('mobile-app-modal');
        else { modal.style.display = 'none'; document.body.classList.remove('modal-open'); }
    };
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.style.display === 'flex') window.closeMobileAppModal();
    });
})();
</script>
