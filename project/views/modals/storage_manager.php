<?php
/**
 * Storage Manager panel — shared markup (Admin Panel + standalone page).
 */
$stgBase = '/project/storage/index.php';
$stgCss = dirname(__DIR__, 2) . '/storage/assets/storage.css';
$stgJs = dirname(__DIR__, 2) . '/storage/assets/storage.js';
$stgCsrf = (string)($GLOBALS['storage_csrf'] ?? ($_SESSION['_csrf_token'] ?? ''));
$stgVersion = static fn(string $file): string => is_file($file) ? (string)filemtime($file) : '1';
$stgIcon = static function (string $name, int $size = 16): string {
    $paths = [
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
<link rel="stylesheet" href="/project/storage/assets/storage.css?v=<?php echo $stgVersion($stgCss); ?>">
<script src="/project/storage/assets/storage.js?v=<?php echo $stgVersion($stgJs); ?>" defer></script>
<div id="stg-app" class="stg-app" data-stg-base="<?php echo htmlspecialchars($stgBase, ENT_QUOTES, 'UTF-8'); ?>" data-stg-csrf="<?php echo htmlspecialchars($stgCsrf, ENT_QUOTES, 'UTF-8'); ?>">

    <header class="stg-header">
        <div class="stg-header-main">
            <div class="stg-title">
                <span class="stg-title-icon"><?php echo $stgIcon('disk', 22); ?></span>
                <span>Storage</span>
            </div>
            <div class="stg-subtitle">One pool across every disk — managed inside this container.</div>
        </div>
        <div class="stg-header-actions">
            <span id="stg-pill" class="stg-pill"><span class="stg-dot"></span><span id="stg-pill-text">Loading…</span></span>
            <button type="button" class="stg-btn stg-btn-ghost" data-stg-action="scan">
                <?php echo $stgIcon('scan', 15); ?><span>Scan disks</span>
            </button>
            <button type="button" class="stg-btn stg-btn-ghost" data-stg-action="refresh" aria-label="Refresh">
                <?php echo $stgIcon('refresh', 15); ?><span>Refresh</span>
            </button>
        </div>
    </header>

    <div id="stg-alert" class="stg-alert" hidden></div>

    <nav class="stg-tabs" role="tablist" aria-label="Storage sections">
        <button type="button" class="stg-tab active" role="tab" data-stg-tab="overview" aria-selected="true">
            <?php echo $stgIcon('grid', 15); ?><span>Overview</span>
        </button>
        <button type="button" class="stg-tab" role="tab" data-stg-tab="disks" aria-selected="false">
            <?php echo $stgIcon('disk', 15); ?><span>Disks</span><span class="stg-tab-count" id="stg-tab-count-disks">0</span>
        </button>
        <button type="button" class="stg-tab" role="tab" data-stg-tab="migrations" aria-selected="false">
            <?php echo $stgIcon('migrate', 15); ?><span>Migrations</span><span class="stg-tab-count" id="stg-tab-count-migrations">0</span>
        </button>
        <button type="button" class="stg-tab" role="tab" data-stg-tab="backup" aria-selected="false">
            <?php echo $stgIcon('backup', 15); ?><span>Backup</span>
        </button>
        <button type="button" class="stg-tab" role="tab" data-stg-tab="settings" aria-selected="false">
            <?php echo $stgIcon('settings', 15); ?><span>Settings</span>
        </button>
    </nav>

    <!-- Overview -->
    <section class="stg-panel active" data-stg-panel="overview" role="tabpanel">
        <div class="stg-overview-grid">
            <article class="stg-hero">
                <div class="stg-ring" id="stg-ring"><div><strong id="stg-ring-pct">—</strong><span>used</span></div></div>
                <div class="stg-hero-main">
                    <div class="stg-eyebrow">Merged storage pool</div>
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
                    <span class="stg-kpi-label">Total capacity</span>
                    <strong id="stg-kpi-total">—</strong>
                    <small>all pooled disks</small>
                </div>
                <div class="stg-kpi">
                    <span class="stg-kpi-icon is-warn"><?php echo $stgIcon('chart', 17); ?></span>
                    <span class="stg-kpi-label">Used</span>
                    <strong id="stg-kpi-used">—</strong>
                    <small id="stg-kpi-used-sub">—</small>
                </div>
                <div class="stg-kpi">
                    <span class="stg-kpi-icon is-ok"><?php echo $stgIcon('shield', 17); ?></span>
                    <span class="stg-kpi-label">Free</span>
                    <strong id="stg-kpi-free">—</strong>
                    <small>after reserve</small>
                </div>
                <div class="stg-kpi">
                    <span class="stg-kpi-icon"><?php echo $stgIcon('disk', 17); ?></span>
                    <span class="stg-kpi-label">Disks online</span>
                    <strong id="stg-kpi-disks">—</strong>
                    <small id="stg-kpi-disks-sub">registered</small>
                </div>
            </div>
        </div>

        <div class="stg-grid-2">
            <section class="stg-card">
                <div class="stg-card-head">
                    <div class="stg-card-title"><?php echo $stgIcon('chart', 15); ?><span>Pool composition</span>
                        <button type="button" class="stg-tip" data-tip="Each segment is one physical disk. MergerFS writes new files to the disk with the most free space." aria-label="About pool composition">?</button>
                    </div>
                    <span class="stg-muted">by capacity</span>
                </div>
                <div class="stg-pool-map" id="stg-pool-map"><div class="stg-empty">Loading…</div></div>
                <div class="stg-pool-legend" id="stg-pool-legend"></div>
            </section>

            <section class="stg-card">
                <div class="stg-card-head">
                    <div class="stg-card-title"><?php echo $stgIcon('shield', 15); ?><span>Health check</span></div>
                    <span class="stg-badge" id="stg-health-score">—</span>
                </div>
                <div class="stg-health-list" id="stg-health-list"><div class="stg-empty">Loading…</div></div>
                <details class="stg-details">
                    <summary>How does the safe removal work?</summary>
                    <p>A Restic snapshot is created, files are copied directly to the other physical disks, every copy is verified by size and SHA-256, and only then is the disk detached from the pool. The original files stay until verification passes.</p>
                </details>
            </section>
        </div>

        <section class="stg-card">
            <div class="stg-card-head">
                <div class="stg-card-title"><?php echo $stgIcon('clock', 15); ?><span>Recent activity</span></div>
                <span class="stg-muted">latest events</span>
            </div>
            <div class="stg-events stg-events-compact" id="stg-events"><div class="stg-empty">No events.</div></div>
        </section>
    </section>

    <!-- Disks -->
    <section class="stg-panel" data-stg-panel="disks" role="tabpanel" hidden>
        <section class="stg-card">
            <div class="stg-card-head">
                <div class="stg-card-title"><?php echo $stgIcon('disk', 15); ?><span>Physical disks</span>
                    <button type="button" class="stg-tip" data-tip="Only real bind mounts under /disks are pooled. A plain directory is never treated as a disk." aria-label="About disk detection">?</button>
                </div>
                <span class="stg-muted" id="stg-disks-hint"></span>
            </div>
            <div class="stg-disks-grid" id="stg-disks-grid">
                <div class="stg-empty">Loading…</div>
            </div>
            <div class="stg-empty" id="stg-disks-empty" hidden>
                <div>No disks detected. Add a bind mount under <code>/disks</code> in docker-compose.yml and recreate the container:</div>
                <pre class="stg-code">- /mnt/hdd1:/disks/hdd1:rw</pre>
            </div>
            <details class="stg-details">
                <summary>Add another disk</summary>
                <p>Add one bind-mount line to <code>docker-compose.yml</code>, recreate the container, and the disk is detected, registered and attached to the pool automatically. No MergerFS setup is required on the host.</p>
            </details>
        </section>
    </section>

    <!-- Migrations -->
    <section class="stg-panel" data-stg-panel="migrations" role="tabpanel" hidden>
        <section class="stg-card" id="stg-active-card" hidden>
            <div class="stg-card-head">
                <div class="stg-card-title"><?php echo $stgIcon('migrate', 15); ?><span>Migration in progress</span></div>
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
                    <button type="button" class="stg-btn stg-btn-ghost" data-stg-action="progress_details">Details</button>
                    <button type="button" class="stg-btn stg-btn-danger" data-stg-action="migration_cancel">Cancel</button>
                </div>
            </div>
        </section>

        <div class="stg-grid-2">
            <section class="stg-card">
                <div class="stg-card-head">
                    <div class="stg-card-title"><?php echo $stgIcon('chart', 15); ?><span>Migration progress</span></div>
                    <span class="stg-muted">last runs</span>
                </div>
                <div class="stg-chart-bars" id="stg-chart-bars"><div class="stg-empty">No migrations yet.</div></div>
            </section>

            <section class="stg-card">
                <div class="stg-card-head">
                    <div class="stg-card-title"><?php echo $stgIcon('settings', 15); ?><span>Migration profile</span>
                        <button type="button" class="stg-tip" data-tip="Verified copy: every file is copied directly to another physical disk, then checked by size and SHA-256 before the source branch is removed." aria-label="About migration profile">?</button>
                    </div>
                </div>
                <div class="stg-profile">
                    <div class="stg-profile-step"><span class="stg-profile-num">1</span><div><strong>Snapshot</strong><small>Restic snapshot before copying</small></div></div>
                    <div class="stg-profile-step"><span class="stg-profile-num">2</span><div><strong>Verified copy</strong><small>Size + SHA-256 for every file</small></div></div>
                    <div class="stg-profile-step"><span class="stg-profile-num">3</span><div><strong>Detach</strong><small>Disk leaves the pool only after verification</small></div></div>
                </div>
                <div class="stg-row">
                    <a class="stg-btn stg-btn-sm" href="/project/storage/index.php" target="_blank" rel="noopener">Open full page</a>
                    <a class="stg-btn stg-btn-sm stg-btn-ghost" href="/backup_dashboard.php" target="_blank" rel="noopener">Backup manager</a>
                </div>
            </section>
        </div>

        <section class="stg-card">
            <div class="stg-card-head">
                <div class="stg-card-title"><?php echo $stgIcon('migrate', 15); ?><span>Operation history</span></div>
                <span class="stg-muted" id="stg-history-hint"></span>
            </div>
            <div class="stg-table-wrap">
                <table class="stg-table">
                    <thead>
                        <tr><th>Started</th><th>Disk</th><th>Status</th><th>Progress</th><th>Files</th><th>Data</th><th>Error</th><th>Actions</th></tr>
                    </thead>
                    <tbody id="stg-history-body"><tr><td colspan="8"><div class="stg-empty">No migrations yet.</div></td></tr></tbody>
                </table>
            </div>
        </section>
    </section>

    <!-- Backup -->
    <section class="stg-panel" data-stg-panel="backup" role="tabpanel" hidden>
        <div class="stg-grid-2">
            <section class="stg-card">
                <div class="stg-card-head">
                    <div class="stg-card-title"><?php echo $stgIcon('backup', 15); ?><span>Restic protection</span></div>
                    <span class="stg-badge" id="stg-backup-state">—</span>
                </div>
                <div class="stg-stat-grid">
                    <div class="stg-stat"><span>Repository</span><strong id="stg-restic">—</strong><small>configured profile</small></div>
                    <div class="stg-stat"><span>Last backup</span><strong id="stg-last-backup">—</strong><small>snapshot time</small></div>
                    <div class="stg-stat"><span>Pool path</span><strong id="stg-backup-path">/storage</strong><small>always snapshotted as one pool</small></div>
                </div>
                <details class="stg-details">
                    <summary>Why back up /storage instead of each disk?</summary>
                    <p>Snapshots stay continuous when files move between disks. Before a migration, the pool is snapshotted; if an expected disk is offline, scheduled backups are skipped instead of treating missing files as deletions.</p>
                </details>
            </section>

            <section class="stg-card">
                <div class="stg-card-head">
                    <div class="stg-card-title"><?php echo $stgIcon('server', 15); ?><span>Backup profile</span></div>
                </div>
                <div class="stg-form">
                    <label class="stg-field"><span>Backup profile for the pool</span>
                        <select id="stg-backup-profile"><option value="">Not configured</option></select>
                    </label>
                    <div class="stg-row">
                        <button type="button" class="stg-btn stg-btn-primary" data-stg-action="backup_attach"><?php echo $stgIcon('plus', 14); ?><span>Add /storage to profile</span></button>
                        <button type="button" class="stg-btn" data-stg-action="backup_now"><?php echo $stgIcon('backup', 14); ?><span>Back up pool now</span></button>
                    </div>
                    <div class="stg-note">Scheduled backups are skipped while an expected disk is offline.</div>
                </div>
            </section>
        </div>
    </section>

    <!-- Settings -->
    <section class="stg-panel" data-stg-panel="settings" role="tabpanel" hidden>
        <div class="stg-grid-2">
            <section class="stg-card">
                <div class="stg-card-head">
                    <div class="stg-card-title"><?php echo $stgIcon('settings', 15); ?><span>Placement &amp; safety</span></div>
                </div>
                <div class="stg-form">
                    <label class="stg-field"><span>New file placement
                        <button type="button" class="stg-tip" data-tip="MergerFS stores each whole file on one disk. This policy chooses which disk receives a new file." aria-label="About placement policy">?</button>
                    </span>
                        <select id="stg-placement-policy">
                            <option value="mfs">Most free space (recommended)</option>
                            <option value="lfs">Least free space</option>
                            <option value="epmfs">Existing path, most free space</option>
                            <option value="ff">First found</option>
                        </select>
                    </label>
                    <div class="stg-field-row">
                        <label class="stg-field"><span>Reserve free space (%)</span><input type="number" id="stg-reserve" min="0" max="50" step="1"></label>
                        <label class="stg-field"><span>Min free per disk (GB)</span><input type="number" id="stg-minfree" min="0" max="100000" step="1"></label>
                    </div>
                    <div class="stg-field-row">
                        <label class="stg-field"><span>Discovery interval (seconds)</span><input type="number" id="stg-interval" min="5" max="3600" step="5"></label>
                        <label class="stg-field"><span>Parallel copy jobs</span><input type="number" id="stg-max-parallel" min="1" max="8" step="1"></label>
                    </div>
                    <label class="stg-check"><input type="checkbox" id="stg-backup-required"><span>Require a Restic snapshot before and after migration</span></label>
                    <label class="stg-check"><input type="checkbox" id="stg-delete-source"><span>Delete original files after verified migration (off by default)</span></label>
                    <div class="stg-row">
                        <button type="button" class="stg-btn stg-btn-primary" data-stg-action="save_settings"><?php echo $stgIcon('check', 14); ?><span>Save settings</span></button>
                    </div>
                </div>
            </section>

            <section class="stg-card">
                <div class="stg-card-head">
                    <div class="stg-card-title"><?php echo $stgIcon('info', 15); ?><span>System status</span></div>
                </div>
                <div class="stg-health-list" id="stg-system-status"><div class="stg-empty">Loading…</div></div>
                <details class="stg-details">
                    <summary>Docker requirements</summary>
                    <p>The container needs <code>/dev/fuse</code> and the <code>/disks/*</code> bind mounts. MergerFS runs inside the container; no host-side setup is required.</p>
                </details>
            </section>
        </div>
    </section>

    <!-- Safe removal dialog -->
    <div class="stg-overlay" id="stg-remove-overlay" hidden>
        <div class="stg-dialog" role="dialog" aria-modal="true" aria-labelledby="stg-remove-title">
            <div class="stg-dialog-head">
                <div class="stg-dialog-title" id="stg-remove-title">Safely remove disk</div>
                <button type="button" class="stg-icon-btn" data-stg-close="remove" aria-label="Close">×</button>
            </div>
            <div class="stg-dialog-body" id="stg-remove-body"><div class="stg-empty">Checking free space…</div></div>
            <div class="stg-dialog-foot">
                <button type="button" class="stg-btn" data-stg-close="remove">Cancel</button>
                <button type="button" class="stg-btn stg-btn-danger" data-stg-action="migration_start" id="stg-remove-confirm" disabled><?php echo $stgIcon('trash', 14); ?><span>Safely remove disk</span></button>
            </div>
        </div>
    </div>

    <!-- Progress dialog -->
    <div class="stg-overlay" id="stg-progress-overlay" hidden>
        <div class="stg-dialog" role="dialog" aria-modal="true" aria-labelledby="stg-progress-title">
            <div class="stg-dialog-head">
                <div class="stg-dialog-title" id="stg-progress-title">Migration progress</div>
                <button type="button" class="stg-icon-btn" data-stg-close="progress" aria-label="Close">×</button>
            </div>
            <div class="stg-dialog-body" id="stg-progress-body"><div class="stg-empty">Loading…</div></div>
            <div class="stg-dialog-foot">
                <a class="stg-btn stg-btn-ghost" href="/backup_dashboard.php" target="_blank" rel="noopener">Open Backup manager</a>
                <button type="button" class="stg-btn" data-stg-close="progress">Close</button>
            </div>
        </div>
    </div>
</div>
