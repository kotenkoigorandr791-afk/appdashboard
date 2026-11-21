<?php
/**
 * Storage Manager panel — shared markup (Admin Panel + standalone page).
 */
$stgBase = '/shell/storage/index.php';
$stgCss = dirname(__DIR__, 2) . '/storage/assets/storage.css';
$stgJs = dirname(__DIR__, 2) . '/storage/assets/storage.js';
$stgCsrf = (string)($GLOBALS['storage_csrf'] ?? ($_SESSION['_csrf_token'] ?? ''));
require_once dirname(__DIR__, 2) . '/storage/i18n.php';
$stgLang = storage_boot_i18n($GLOBALS['language'] ?? ($_SESSION['user']['language'] ?? ($_COOKIE['sk_language'] ?? ($_GET['lang'] ?? 'en'))));
$stgI18nJson = htmlspecialchars((string)json_encode(storage_i18n_payload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
$stgE = static fn(string $key): string => htmlspecialchars(stg_t($key), ENT_QUOTES, 'UTF-8');
$stgVersion = static fn(string $file): string => is_file($file) ? (string)filemtime($file) : '1';
$stgIcon = static function (string $name, int $size = 16): string {
    $paths = [
        'storage' => '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="14" width="18" height="7" rx="2"/><path d="M7 7.5h.01"/><path d="M7 17.5h.01"/><path d="M11 7.5h6"/><path d="M11 17.5h6"/>',
        'disk' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/><path d="M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'migrate' => '<path d="M4 7h12"/><path d="m13 4 3 3-3 3"/><path d="M20 17H8"/><path d="m11 14-3 3 3 3"/>',
        'settings' => '<path d="M4 21v-7"/><path d="M4 10V3"/><path d="M12 21v-9"/><path d="M12 8V3"/><path d="M20 21v-5"/><path d="M20 12V3"/><path d="M1 14h6"/><path d="M9 8h6"/><path d="M17 16h6"/>',
        'backup' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
        'refresh' => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/>',
        'scan' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/><path d="M8 11h6"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'trash' => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/>',
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>',
        'server' => '<rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01"/><path d="M7 17h.01"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'arrow' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
        'warning' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    ];
    $body = $paths[$name] ?? $paths['info'];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
};
?>
<link rel="stylesheet" href="/shell/storage/assets/storage.css?v=<?php echo $stgVersion($stgCss); ?>">
<script src="/shell/storage/assets/storage.js?v=<?php echo $stgVersion($stgJs); ?>" defer></script>
<div id="stg-app" class="stg-app" data-stg-lang="<?php echo htmlspecialchars($stgLang, ENT_QUOTES, 'UTF-8'); ?>" data-stg-i18n="<?php echo $stgI18nJson; ?>" data-stg-base="<?php echo htmlspecialchars($stgBase, ENT_QUOTES, 'UTF-8'); ?>" data-stg-csrf="<?php echo htmlspecialchars($stgCsrf, ENT_QUOTES, 'UTF-8'); ?>">

    <header class="stg-header">
        <div class="stg-header-main">
            <div class="stg-title">
                <span class="stg-title-icon"><?php echo $stgIcon('storage', 22); ?></span>
                <span><?php echo $stgE('title'); ?></span>
            </div>
            <div class="stg-subtitle"><?php echo $stgE('subtitle'); ?></div>
        </div>
        <div class="stg-header-actions">
            <span id="stg-pill" class="stg-pill"><span class="stg-dot"></span><span id="stg-pill-text"><?php echo $stgE('loading'); ?></span></span>
            <button type="button" class="stg-btn stg-btn-ghost" data-stg-action="scan">
                <?php echo $stgIcon('scan', 15); ?><span><?php echo $stgE('scan_disks'); ?></span>
            </button>
            <button type="button" class="stg-btn stg-btn-ghost" data-stg-action="refresh" aria-label="<?php echo $stgE('refresh_aria'); ?>">
                <?php echo $stgIcon('refresh', 15); ?><span><?php echo $stgE('refresh'); ?></span>
            </button>
        </div>
    </header>

    <div id="stg-alert" class="stg-alert" hidden></div>

    <nav class="stg-tabs" role="tablist" aria-label="Storage sections">
        <button type="button" class="stg-tab active" role="tab" data-stg-tab="overview" aria-selected="true">
            <?php echo $stgIcon('grid', 15); ?><span><?php echo $stgE('overview'); ?></span>
        </button>
        <button type="button" class="stg-tab" role="tab" data-stg-tab="disks" aria-selected="false">
            <?php echo $stgIcon('disk', 15); ?><span><?php echo $stgE('disks'); ?></span><span class="stg-tab-count" id="stg-tab-count-disks">0</span>
        </button>
        <button type="button" class="stg-tab" role="tab" data-stg-tab="migrations" aria-selected="false">
            <?php echo $stgIcon('migrate', 15); ?><span><?php echo $stgE('migrations'); ?></span><span class="stg-tab-count" id="stg-tab-count-migrations">0</span>
        </button>
        <button type="button" class="stg-tab" role="tab" data-stg-tab="file_index" aria-selected="false">
            <?php echo $stgIcon('server', 15); ?><span><?php echo $stgE('file_index'); ?></span><span class="stg-tab-count" id="stg-tab-count-index">0</span>
        </button>
        <button type="button" class="stg-tab" role="tab" data-stg-tab="backup" aria-selected="false">
            <?php echo $stgIcon('backup', 15); ?><span><?php echo $stgE('backup'); ?></span>
        </button>
        <button type="button" class="stg-tab" role="tab" data-stg-tab="settings" aria-selected="false">
            <?php echo $stgIcon('settings', 15); ?><span><?php echo $stgE('settings'); ?></span>
        </button>
    </nav>

    <!-- Overview -->
    <section class="stg-panel active" data-stg-panel="overview" role="tabpanel">
        <div class="stg-overview-grid">
            <article class="stg-hero">
                <div class="stg-ring" id="stg-ring"><div><strong id="stg-ring-pct">—</strong><span><?php echo $stgE('used'); ?></span></div></div>
                <div class="stg-hero-main">
                    <div class="stg-eyebrow"><?php echo $stgE('merged_pool'); ?></div>
                    <div class="stg-hero-value"><strong id="stg-total">—</strong><span id="stg-disk-count">—</span></div>
                    <div class="stg-bar stg-bar-lg"><span id="stg-used-bar"></span></div>
                    <div class="stg-hero-meta">
                        <span id="stg-used-pct">—</span>
                        <span id="stg-free-sub">—</span>
                    </div>
                </div>
            </article>
            <div class="stg-kpi-grid">
                <div class="stg-kpi">
                    <span class="stg-kpi-icon"><?php echo $stgIcon('server', 17); ?></span>
                    <span class="stg-kpi-label"><?php echo $stgE('total_capacity'); ?></span>
                    <strong id="stg-kpi-total">—</strong>
                    <small><?php echo $stgE('all_pooled_disks'); ?></small>
                </div>
                <div class="stg-kpi">
                    <span class="stg-kpi-icon is-warn"><?php echo $stgIcon('chart', 17); ?></span>
                    <span class="stg-kpi-label"><?php echo $stgE('used'); ?></span>
                    <strong id="stg-kpi-used">—</strong>
                    <small id="stg-kpi-used-sub">—</small>
                </div>
                <div class="stg-kpi">
                    <span class="stg-kpi-icon is-ok"><?php echo $stgIcon('shield', 17); ?></span>
                    <span class="stg-kpi-label"><?php echo $stgE('free'); ?></span>
                    <strong id="stg-kpi-free">—</strong>
                    <small><?php echo $stgE('after_reserve'); ?></small>
                </div>
                <div class="stg-kpi">
                    <span class="stg-kpi-icon"><?php echo $stgIcon('disk', 17); ?></span>
                    <span class="stg-kpi-label"><?php echo $stgE('disks_online'); ?></span>
                    <strong id="stg-kpi-disks">—</strong>
                    <small id="stg-kpi-disks-sub"><?php echo $stgE('registered'); ?></small>
                </div>
            </div>
        </div>

        <div class="stg-grid-2">
            <section class="stg-card">
                <div class="stg-card-head">
                    <div class="stg-card-title"><?php echo $stgIcon('chart', 15); ?><span><?php echo $stgE('pool_composition'); ?></span>
                        <button type="button" class="stg-tip" data-tip="<?php echo $stgE('tip_pool'); ?>" aria-label="<?php echo $stgE('pool_composition'); ?>">?</button>
                    </div>
                    <span class="stg-muted"><?php echo $stgE('by_capacity'); ?></span>
                </div>
                <div class="stg-pool-map" id="stg-pool-map"><div class="stg-empty"><?php echo $stgE('loading'); ?></div></div>
                <div class="stg-pool-legend" id="stg-pool-legend"></div>
            </section>

            <section class="stg-card">
                <div class="stg-card-head">
                    <div class="stg-card-title"><?php echo $stgIcon('shield', 15); ?><span><?php echo $stgE('health_check'); ?></span></div>
                    <span class="stg-badge" id="stg-health-score">—</span>
                </div>
                <div class="stg-health-list" id="stg-health-list"><div class="stg-empty"><?php echo $stgE('loading'); ?></div></div>
                <details class="stg-details">
                    <summary><?php echo $stgE('how_removal'); ?></summary>
                    <p><?php echo $stgE('how_removal_text'); ?></p>
                </details>
            </section>
        </div>

        <section class="stg-card">
            <div class="stg-card-head">
                <div class="stg-card-title"><?php echo $stgIcon('clock', 15); ?><span><?php echo $stgE('recent_activity'); ?></span></div>
                <span class="stg-muted"><?php echo $stgE('latest_events'); ?></span>
            </div>
            <div class="stg-events stg-events-compact" id="stg-events"><div class="stg-empty"><?php echo $stgE('no_events'); ?></div></div>
        </section>
    </section>

    <!-- File Index -->
    <section class="stg-panel" data-stg-panel="file_index" role="tabpanel" hidden>
        <section class="stg-card">
            <div class="stg-card-head">
                <div class="stg-card-title"><?php echo $stgIcon('server', 15); ?><span><?php echo $stgE('file_index'); ?></span></div>
                <span class="stg-muted"><?php echo $stgE('file_index_subtitle'); ?></span>
            </div>
            <div class="stg-kpi-grid">
                <div class="stg-kpi">
                    <span class="stg-kpi-icon"><?php echo $stgIcon('chart', 17); ?></span>
                    <span class="stg-kpi-label"><?php echo $stgE('indexed_files'); ?></span>
                    <strong id="stg-idx-files">—</strong><small id="stg-idx-bytes">—</small>
                </div>
                <div class="stg-kpi">
                    <span class="stg-kpi-icon"><?php echo $stgIcon('grid', 17); ?></span>
                    <span class="stg-kpi-label"><?php echo $stgE('indexed_directories'); ?></span>
                    <strong id="stg-idx-directories">—</strong><small id="stg-idx-missing">—</small>
                </div>
                <div class="stg-kpi">
                    <span class="stg-kpi-icon"><?php echo $stgIcon('shield', 17); ?></span>
                    <span class="stg-kpi-label"><?php echo $stgE('indexed_users'); ?></span>
                    <strong id="stg-idx-users">—</strong><small id="stg-idx-apps">—</small>
                </div>
                <div class="stg-kpi">
                    <span class="stg-kpi-icon is-warn"><?php echo $stgIcon('clock', 17); ?></span>
                    <span class="stg-kpi-label"><?php echo $stgE('index_pending_events'); ?></span>
                    <strong id="stg-idx-pending">—</strong><small id="stg-idx-failed">—</small>
                </div>
            </div>
            <div class="stg-hero-meta">
                <span id="stg-idx-last">—</span>
                <span id="stg-idx-reconcile">—</span>
                <span id="stg-idx-jobs">—</span>
            </div>
            <div class="stg-card-actions" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                <button type="button" class="stg-btn stg-btn-primary" data-stg-action="index_start">
                    <?php echo $stgIcon('scan', 15); ?><span><?php echo $stgE('index_start'); ?></span>
                </button>
                <button type="button" class="stg-btn stg-btn-ghost" data-stg-action="index_reconcile">
                    <?php echo $stgIcon('refresh', 15); ?><span><?php echo $stgE('index_reconcile'); ?></span>
                </button>
            </div>
        </section>

        <div class="stg-grid-2">
            <section class="stg-card">
                <div class="stg-card-head"><div class="stg-card-title"><?php echo $stgE('index_by_app'); ?></div></div>
                <div class="stg-table-wrap"><table class="stg-table"><thead><tr><th><?php echo $stgE('indexed_apps'); ?></th><th><?php echo $stgE('indexed_objects'); ?></th><th><?php echo $stgE('indexed_bytes'); ?></th></tr></thead><tbody id="stg-index-apps"></tbody></table></div>
            </section>
            <section class="stg-card">
                <div class="stg-card-head"><div class="stg-card-title"><?php echo $stgE('index_by_disk'); ?></div></div>
                <div class="stg-table-wrap"><table class="stg-table"><thead><tr><th><?php echo $stgE('disks'); ?></th><th><?php echo $stgE('indexed_objects'); ?></th><th><?php echo $stgE('indexed_bytes'); ?></th></tr></thead><tbody id="stg-index-disks"></tbody></table></div>
            </section>
        </div>

        <section class="stg-card">
            <div class="stg-card-head"><div class="stg-card-title"><?php echo $stgE('index_status'); ?></div><span class="stg-muted" id="stg-index-generated"></span></div>
            <div class="stg-table-wrap"><table class="stg-table"><thead><tr><th>#</th><th><?php echo $stgE('indexed_apps'); ?></th><th><?php echo $stgE('indexed_users'); ?></th><th><?php echo $stgE('status_unknown'); ?></th><th><?php echo $stgE('indexed_files'); ?></th><th><?php echo $stgE('indexed_directories'); ?></th><th><?php echo $stgE('index_last_run'); ?></th></tr></thead><tbody id="stg-index-jobs"></tbody></table></div>
        </section>
    </section>

    <!-- Disks -->
    <section class="stg-panel" data-stg-panel="disks" role="tabpanel" hidden>
        <section class="stg-card">
            <div class="stg-card-head">
                <div class="stg-card-title"><?php echo $stgIcon('disk', 15); ?><span><?php echo $stgE('physical_disks'); ?></span>
                    <button type="button" class="stg-tip" data-tip="<?php echo $stgE('tip_disks'); ?>" aria-label="<?php echo $stgE('physical_disks'); ?>">?</button>
                </div>
                <span class="stg-muted" id="stg-disks-hint"></span>
            </div>
            <div class="stg-disks-grid" id="stg-disks-grid">
                <div class="stg-empty"><?php echo $stgE('loading'); ?></div>
            </div>
            <div class="stg-empty" id="stg-disks-empty" hidden>
                <div><?php echo $stgE('no_disks_detected'); ?></div>
                <pre class="stg-code">- /mnt/hdd1:/disks/hdd1:rw</pre>
            </div>
            <details class="stg-details">
                <summary><?php echo $stgE('add_another_disk'); ?></summary>
                <p><?php echo $stgE('add_another_disk_text'); ?></p>
            </details>
        </section>
    </section>

    <!-- Migrations -->
    <section class="stg-panel" data-stg-panel="migrations" role="tabpanel" hidden>
        <section class="stg-card" id="stg-active-card" hidden>
            <div class="stg-card-head">
                <div class="stg-card-title"><?php echo $stgIcon('migrate', 15); ?><span><?php echo $stgE('migration_in_progress'); ?></span></div>
                <span class="stg-badge" id="stg-active-state"></span>
            </div>
            <div class="stg-migration">
                <div class="stg-migration-main">
                    <div class="stg-progress"><span id="stg-migration-bar"></span></div>
                    <div class="stg-progress-meta">
                        <strong id="stg-migration-pct">0%</strong>
                        <span id="stg-migration-bytes"></span>
                        <span id="stg-migration-speed"></span>
                        <span id="stg-migration-eta"></span>
                        <span id="stg-migration-errors"></span>
                    </div>
                    <div class="stg-current" id="stg-migration-current"></div>
                </div>
                <div class="stg-migration-actions">
                    <button type="button" class="stg-btn stg-btn-ghost" data-stg-action="progress_details"><?php echo $stgE('details'); ?></button>
                    <button type="button" class="stg-btn stg-btn-danger" data-stg-action="migration_cancel"><?php echo $stgE('cancel'); ?></button>
                </div>
            </div>
        </section>

        <div class="stg-grid-2">
            <section class="stg-card">
                <div class="stg-card-head">
                    <div class="stg-card-title"><?php echo $stgIcon('chart', 15); ?><span><?php echo $stgE('migration_progress'); ?></span>
                        <button type="button" class="stg-tip" data-tip="<?php echo $stgE('tip_recovery'); ?>" aria-label="<?php echo $stgE('migration_progress'); ?>">?</button>
                    </div>
                    <span class="stg-muted"><?php echo $stgE('last_runs'); ?></span>
                </div>
                <div class="stg-chart-bars" id="stg-chart-bars"><div class="stg-empty"><?php echo $stgE('no_migrations'); ?></div></div>
            </section>

            <section class="stg-card">
                <div class="stg-card-head">
                    <div class="stg-card-title"><?php echo $stgIcon('settings', 15); ?><span><?php echo $stgE('migration_profile'); ?></span>
                        <button type="button" class="stg-tip" data-tip="<?php echo $stgE('tip_migration'); ?>" aria-label="<?php echo $stgE('migration_profile'); ?>">?</button>
                    </div>
                </div>
                <div class="stg-profile">
                    <div class="stg-profile-step"><span class="stg-profile-num">1</span><div><strong><?php echo $stgE('snapshot'); ?></strong><small><?php echo $stgE('snapshot_before'); ?></small></div></div>
                    <div class="stg-profile-step"><span class="stg-profile-num">2</span><div><strong><?php echo $stgE('verified_copy'); ?></strong><small><?php echo $stgE('verified_copy_text'); ?></small></div></div>
                    <div class="stg-profile-step"><span class="stg-profile-num">3</span><div><strong><?php echo $stgE('detach'); ?></strong><small><?php echo $stgE('detach_text'); ?></small></div></div>
                </div>
                <div class="stg-row">
                    <a class="stg-btn stg-btn-sm" href="/shell/storage/index.php" target="_blank" rel="noopener"><?php echo $stgE('open_full_page'); ?></a>
                    <a class="stg-btn stg-btn-sm stg-btn-ghost" href="/shell/backup/index.php" target="_blank" rel="noopener"><?php echo $stgE('backup_manager'); ?></a>
                </div>
            </section>
        </div>

        <section class="stg-card">
            <div class="stg-card-head">
                <div class="stg-card-title"><?php echo $stgIcon('migrate', 15); ?><span><?php echo $stgE('operation_history'); ?></span></div>
                <span class="stg-muted" id="stg-history-hint"></span>
            </div>
            <div class="stg-table-wrap">
                <table class="stg-table">
                    <thead>
                        <tr><th><?php echo $stgE('started'); ?></th><th><?php echo $stgE('disk'); ?></th><th><?php echo $stgE('status'); ?></th><th><?php echo $stgE('progress'); ?></th><th><?php echo $stgE('files'); ?></th><th><?php echo $stgE('data'); ?></th><th><?php echo $stgE('error'); ?></th><th><?php echo $stgE('actions'); ?></th></tr>
                    </thead>
                    <tbody id="stg-history-body"><tr><td colspan="8"><div class="stg-empty"><?php echo $stgE('no_migrations'); ?></div></td></tr></tbody>
                </table>
            </div>
        </section>
    </section>

    <!-- Backup -->
    <section class="stg-panel" data-stg-panel="backup" role="tabpanel" hidden>
        <div class="stg-grid-2">
            <section class="stg-card">
                <div class="stg-card-head">
                    <div class="stg-card-title"><?php echo $stgIcon('backup', 15); ?><span><?php echo $stgE('restic_protection'); ?></span>
                        <button type="button" class="stg-tip" data-tip="<?php echo $stgE('tip_protection'); ?>" aria-label="<?php echo $stgE('restic_protection'); ?>">?</button>
                    </div>
                    <span class="stg-badge" id="stg-backup-state">—</span>
                </div>
                <div class="stg-stat-grid">
                    <div class="stg-stat"><span><?php echo $stgE('repository'); ?></span><strong id="stg-restic">—</strong><small><?php echo $stgE('configured_profile'); ?></small></div>
                    <div class="stg-stat"><span><?php echo $stgE('last_backup'); ?></span><strong id="stg-last-backup">—</strong><small><?php echo $stgE('snapshot_time'); ?></small></div>
                    <div class="stg-stat"><span><?php echo $stgE('pool_path'); ?></span><strong id="stg-backup-path">/storage</strong><small><?php echo $stgE('always_snapshotted'); ?></small></div>
                </div>
                <details class="stg-details">
                    <summary><?php echo $stgE('why_storage'); ?></summary>
                    <p><?php echo $stgE('why_storage_text'); ?></p>
                </details>
            </section>

            <section class="stg-card">
                <div class="stg-card-head">
                    <div class="stg-card-title"><?php echo $stgIcon('server', 15); ?><span><?php echo $stgE('backup_profile'); ?></span></div>
                </div>
                <div class="stg-form">
                    <label class="stg-field"><span><?php echo $stgE('backup_profile_pool'); ?></span>
                        <select id="stg-backup-profile"><option value=""><?php echo $stgE('not_configured'); ?></option></select>
                    </label>
                    <div class="stg-row" role="group" aria-label="<?php echo $stgE('backup_actions'); ?>">
                        <button type="button" class="stg-btn stg-btn-primary" data-stg-action="backup_attach" aria-label="<?php echo $stgE('add_storage_profile'); ?>"><?php echo $stgIcon('plus', 14); ?><span><?php echo $stgE('add_storage_profile'); ?></span></button>
                        <button type="button" class="stg-btn" data-stg-action="backup_now" aria-label="<?php echo $stgE('back_up_pool'); ?>"><?php echo $stgIcon('backup', 14); ?><span><?php echo $stgE('back_up_pool'); ?></span></button>
                    </div>
                    <div class="stg-note"><?php echo $stgE('scheduled_skipped'); ?></div>
                </div>
            </section>
        </div>
    </section>

    <!-- Settings -->
    <section class="stg-panel" data-stg-panel="settings" role="tabpanel" hidden>
        <div class="stg-grid-2">
            <section class="stg-card">
                <div class="stg-card-head">
                    <div class="stg-card-title"><?php echo $stgIcon('settings', 15); ?><span><?php echo $stgE('placement_safety'); ?></span></div>
                </div>
                <div class="stg-form">
                    <label class="stg-field"><span><?php echo $stgE('new_file_placement'); ?>
                        <button type="button" class="stg-tip" data-tip="<?php echo $stgE('tip_placement'); ?>" aria-label="<?php echo $stgE('new_file_placement'); ?>">?</button>
                    </span>
                        <select id="stg-placement-policy">
                            <option value="mfs"><?php echo $stgE('most_free'); ?></option>
                            <option value="lfs"><?php echo $stgE('least_free'); ?></option>
                            <option value="epmfs"><?php echo $stgE('existing_path'); ?></option>
                            <option value="ff"><?php echo $stgE('first_found'); ?></option>
                        </select>
                    </label>
                    <div class="stg-field-row">
                        <label class="stg-field"><span><?php echo $stgE('reserve_percent'); ?></span><input type="number" id="stg-reserve" min="0" max="50" step="1"></label>
                        <label class="stg-field"><span><?php echo $stgE('min_free_gb'); ?></span><input type="number" id="stg-minfree" min="0" max="100000" step="1"></label>
                    </div>
                    <div class="stg-field-row">
                        <label class="stg-field"><span><?php echo $stgE('discovery_interval'); ?></span><input type="number" id="stg-interval" min="5" max="3600" step="5"></label>
                        <label class="stg-field"><span><?php echo $stgE('parallel_jobs'); ?></span><input type="number" id="stg-max-parallel" min="1" max="8" step="1"></label>
                    </div>
                    <label class="stg-check"><input type="checkbox" id="stg-backup-required"><span class="stg-check-ui" aria-hidden="true"></span><span><?php echo $stgE('require_snapshot'); ?></span></label>
                    <label class="stg-check"><input type="checkbox" id="stg-delete-source"><span class="stg-check-ui" aria-hidden="true"></span><span><?php echo $stgE('delete_source'); ?></span></label>
                    <div class="stg-row" role="group">
                        <button type="button" class="stg-btn stg-btn-primary" data-stg-action="save_settings" aria-label="<?php echo $stgE('save_settings'); ?>"><?php echo $stgIcon('check', 14); ?><span><?php echo $stgE('save_settings'); ?></span></button>
                    </div>
                </div>
            </section>

            <section class="stg-card">
                <div class="stg-card-head">
                    <div class="stg-card-title"><?php echo $stgIcon('info', 15); ?><span><?php echo $stgE('system_status'); ?></span></div>
                </div>
                <div class="stg-health-list" id="stg-system-status"><div class="stg-empty"><?php echo $stgE('loading'); ?></div></div>
                <details class="stg-details">
                    <summary><?php echo $stgE('docker_requirements'); ?></summary>
                    <p><?php echo $stgE('docker_requirements_text'); ?></p>
                </details>
            </section>
        </div>
    </section>

    <!-- Safe removal dialog -->
    <div class="stg-overlay" id="stg-remove-overlay" hidden>
        <div class="stg-dialog" role="dialog" aria-modal="true" aria-labelledby="stg-remove-title">
            <div class="stg-dialog-head">
                <div class="stg-dialog-title" id="stg-remove-title"><?php echo $stgE('safely_remove'); ?></div>
                <button type="button" class="stg-icon-btn" data-stg-close="remove" aria-label="Close">×</button>
            </div>
            <div class="stg-dialog-body" id="stg-remove-body"><div class="stg-empty"><?php echo $stgE('checking_space'); ?></div></div>
            <div class="stg-dialog-foot">
                <button type="button" class="stg-btn" data-stg-close="remove"><?php echo $stgE('cancel'); ?></button>
                <button type="button" class="stg-btn stg-btn-danger" data-stg-action="migration_start" id="stg-remove-confirm" disabled><?php echo $stgIcon('trash', 14); ?><span><?php echo $stgE('safely_remove'); ?></span></button>
            </div>
        </div>
    </div>

    <!-- Progress dialog -->
    <div class="stg-overlay" id="stg-progress-overlay" hidden>
        <div class="stg-dialog" role="dialog" aria-modal="true" aria-labelledby="stg-progress-title">
            <div class="stg-dialog-head">
                <div class="stg-dialog-title" id="stg-progress-title"><?php echo $stgE('migration_progress'); ?></div>
                <button type="button" class="stg-icon-btn" data-stg-close="progress" aria-label="Close">×</button>
            </div>
            <div class="stg-dialog-body" id="stg-progress-body"><div class="stg-empty"><?php echo $stgE('loading'); ?></div></div>
            <div class="stg-dialog-foot">
                <a class="stg-btn stg-btn-ghost" href="/shell/backup/index.php" target="_blank" rel="noopener"><?php echo $stgE('open_backup_manager'); ?></a>
                <button type="button" class="stg-btn" data-stg-close="progress"><?php echo $stgE('close'); ?></button>
            </div>
        </div>
    </div>
</div>
