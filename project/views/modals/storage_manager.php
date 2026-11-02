<?php
/**
 * Storage Manager panel — shared markup (Admin Panel + standalone page).
 */
$stgBase = '/project/storage/index.php';
$stgCss = dirname(__DIR__, 2) . '/storage/assets/storage.css';
$stgJs = dirname(__DIR__, 2) . '/storage/assets/storage.js';
$stgCsrf = (string)($GLOBALS['storage_csrf'] ?? ($_SESSION['_csrf_token'] ?? ''));
$stgVersion = static fn(string $file): string => is_file($file) ? (string)filemtime($file) : '1';
?>
<link rel="stylesheet" href="/project/storage/assets/storage.css?v=<?php echo $stgVersion($stgCss); ?>">
<script src="/project/storage/assets/storage.js?v=<?php echo $stgVersion($stgJs); ?>" defer></script>
<div id="stg-app" class="stg-app" data-stg-base="<?php echo htmlspecialchars($stgBase, ENT_QUOTES, 'UTF-8'); ?>" data-stg-csrf="<?php echo htmlspecialchars($stgCsrf, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="stg-header">
        <div class="stg-header-main">
            <div class="stg-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"/></svg>
                <span>Storage</span>
            </div>
            <div class="stg-subtitle">Dynamic disk pool managed inside the container</div>
        </div>
        <div class="stg-header-actions">
            <span id="stg-pill" class="stg-pill"><span class="stg-dot"></span><span id="stg-pill-text">Loading…</span></span>
            <button type="button" class="stg-btn stg-btn-ghost" data-stg-action="scan">Scan disks</button>
            <button type="button" class="stg-btn stg-btn-ghost" data-stg-action="refresh">Refresh</button>
        </div>
    </div>

    <div id="stg-alert" class="stg-alert" hidden></div>

    <section class="stg-hero" id="stg-summary">
        <div class="stg-ring" id="stg-ring"><div><strong id="stg-ring-pct">—</strong><span>used</span></div></div>
        <div class="stg-hero-main">
            <div class="stg-eyebrow">Merged storage pool</div>
            <div class="stg-hero-value"><strong id="stg-total">—</strong><span id="stg-disk-count">—</span></div>
            <div class="stg-bar stg-bar-lg"><span id="stg-used-bar"></span></div>
            <div class="stg-hero-meta"><span id="stg-used-pct">—</span><span id="stg-free-sub">—</span></div>
        </div>
        <div class="stg-hero-side">
            <div class="stg-mini"><span>Used</span><strong id="stg-used">—</strong></div>
            <div class="stg-mini"><span>Free</span><strong id="stg-free">—</strong></div>
            <div class="stg-mini"><span>MergerFS</span><strong id="stg-mergerfs">—</strong><small id="stg-mergerfs-sub">—</small></div>
            <div class="stg-mini"><span>Restic</span><strong id="stg-restic">—</strong><small id="stg-last-backup">—</small></div>
        </div>
    </section>

    <div class="stg-guide">
        <strong>One pool, all disks.</strong> New files are placed on the disk with the most free space. Add a disk with one line in <code>docker-compose.yml</code> and recreate the container — detection, registration and the MergerFS mount happen automatically.
    </div>

    <section class="stg-card">
        <div class="stg-card-head">
            <div class="stg-card-title">Disks</div>
            <span class="stg-muted" id="stg-disks-hint"></span>
        </div>
        <div class="stg-disks-grid" id="stg-disks-grid">
            <div class="stg-empty">Loading…</div>
        </div>
        <div class="stg-empty" id="stg-disks-empty" hidden>
            <div>No disks detected. Add a bind mount under <code>/disks</code> in docker-compose.yml and recreate the container:</div>
            <pre class="stg-code">- /mnt/hdd1:/disks/hdd1:rw</pre>
        </div>
    </section>

    <section class="stg-card" id="stg-active-card" hidden>
        <div class="stg-card-head">
            <div class="stg-card-title">Migration</div>
            <span class="stg-muted" id="stg-active-state"></span>
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

    <section class="stg-card">
        <div class="stg-card-head">
            <div class="stg-card-title">Operation history</div>
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

    <div class="stg-grid-2">
        <section class="stg-card">
            <div class="stg-card-head"><div class="stg-card-title">Events</div></div>
            <div class="stg-events" id="stg-events"><div class="stg-empty">No events.</div></div>
        </section>

        <section class="stg-card">
            <div class="stg-card-head"><div class="stg-card-title">Settings</div></div>
            <div class="stg-form">
                <label class="stg-field"><span>Reserve free space (%)</span><input type="number" id="stg-reserve" min="0" max="50" step="1"></label>
                <label class="stg-field"><span>Minimum free space per disk (GB)</span><input type="number" id="stg-minfree" min="0" max="100000" step="1"></label>
                <label class="stg-field"><span>Discovery interval (seconds)</span><input type="number" id="stg-interval" min="5" max="3600" step="5"></label>
                <label class="stg-check"><input type="checkbox" id="stg-backup-required"><span>Require a Restic snapshot before and after migration</span></label>
                <label class="stg-check"><input type="checkbox" id="stg-delete-source"><span>Delete original files after verified migration (off by default)</span></label>

                <label class="stg-field"><span>Backup profile for the pool</span>
                    <select id="stg-backup-profile"><option value="">Not configured</option></select>
                </label>
                <div class="stg-row">
                    <button type="button" class="stg-btn stg-btn-primary" data-stg-action="save_settings">Save settings</button>
                    <button type="button" class="stg-btn" data-stg-action="backup_attach">Add /storage to profile</button>
                    <button type="button" class="stg-btn" data-stg-action="backup_now">Back up pool now</button>
                </div>
                <div class="stg-note">The pool is always snapshotted through <code>/storage</code>, so moving a file between disks does not break snapshot history. Scheduled backups are skipped while an expected disk is offline.</div>
            </div>
        </section>
    </div>

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
                <button type="button" class="stg-btn stg-btn-danger" data-stg-action="migration_start" id="stg-remove-confirm" disabled>Safely remove disk</button>
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
