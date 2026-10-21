<?php
/**
 * Backup Manager panel — shared markup.
 *
 * Included directly by project/post_handlers.php inside #ap-backup (no iframe)
 * and by project/backup/views.php for the standalone /backup_dashboard.php page.
 * All behaviour lives in project/backup/assets/backup.js.
 */
if (!function_exists('backup_load_i18n')) {
    require_once dirname(__DIR__, 2) . '/backup/i18n.php';
}
$bkpLang = $GLOBALS['bkpLang'] ?? ($GLOBALS['backupLang'] ?? ($_GET['lang'] ?? ($GLOBALS['language'] ?? 'en')));
if (!isset($GLOBALS['backupI18n'])) {
    $bkpLang = backup_boot_i18n($bkpLang);
} else {
    $bkpLang = backup_normalize_lang($bkpLang);
}
$bkpPayload = backup_i18n_payload($bkpLang);
$bkpI18nJson = htmlspecialchars((string)json_encode($bkpPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
?>
<div id="bdp-app" class="bdp-app" data-bdp-lang="<?php echo htmlspecialchars($bkpLang, ENT_QUOTES, 'UTF-8'); ?>" data-bdp-i18n="<?php echo $bkpI18nJson; ?>">
    <div class="bdp-header">
        <div class="bdp-header-main">
            <div class="bdp-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <span data-bdp-i18n="title">Backup</span>
            </div>
            <div class="bdp-subtitle" data-bdp-i18n="subtitle">Restic backups of this container and the host system</div>
        </div>
        <div class="bdp-header-actions">
            <span id="bdp-status-pill" class="bdp-pill"><span class="bdp-dot"></span><span id="bdp-status-text">—</span></span>
            <button type="button" class="bdp-btn bdp-btn-ghost" data-bdp-action="refresh">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/></svg>
                <span data-bdp-i18n="refresh">Refresh</span>
            </button>
        </div>
    </div>

    <div id="bdp-alert" class="bdp-alert" hidden></div>

    <section class="bdp-card" id="bdp-backups-card">
        <div class="bdp-card-head">
            <div class="bdp-card-title" data-bdp-i18n="backups">Backups</div>
            <button type="button" class="bdp-btn bdp-btn-primary bdp-btn-sm" data-bdp-action="add_backup">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span data-bdp-i18n="add">Add backup</span>
            </button>
        </div>
        <div class="bdp-table-wrap">
            <table class="bdp-table">
                <thead>
                    <tr>
                        <th data-bdp-i18n="name">Backup name</th>
                        <th data-bdp-i18n="sources">Backup sources</th>
                        <th data-bdp-i18n="schedule">Schedule</th>
                        <th data-bdp-i18n="job_status">Status</th>
                        <th data-bdp-i18n="last_run">Last run</th>
                        <th data-bdp-i18n="next_run">Next run</th>
                        <th data-bdp-i18n="actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="bdp-backups-body">
                    <tr><td colspan="7"><div class="bdp-empty" data-bdp-i18n="loading">Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="bdp-empty" id="bdp-backups-empty" hidden>
            <span data-bdp-i18n="no_backups">No backups yet. Create the first one.</span>
            <button type="button" class="bdp-btn bdp-btn-primary bdp-btn-sm" data-bdp-action="add_backup">
                <span data-bdp-i18n="add_first">Add the first backup</span>
            </button>
        </div>
    </section>

    <section class="bdp-card">
        <div class="bdp-card-head">
            <div class="bdp-card-title" data-bdp-i18n="maintenance">Maintenance</div>
            <span class="bdp-muted" id="bdp-disk-meta"></span>
        </div>
        <div class="bdp-row">
            <button type="button" class="bdp-btn" data-bdp-action="check"><span data-bdp-i18n="check">Check repository</span></button>
            <label class="bdp-check bdp-check-inline"><input type="checkbox" id="bdp-check-read-data"><span data-bdp-i18n="check_read_data">Read data during check</span></label>
            <button type="button" class="bdp-btn" data-bdp-action="prune"><span data-bdp-i18n="prune">Prune</span></button>
            <button type="button" class="bdp-btn" data-bdp-action="unlock"><span data-bdp-i18n="unlock">Unlock</span></button>
            <button type="button" class="bdp-btn" data-bdp-action="rebuild_index"><span data-bdp-i18n="rebuild_index">Rebuild index</span></button>
            <button type="button" class="bdp-btn bdp-btn-ghost bdp-ml-auto" data-bdp-action="stats"><span data-bdp-i18n="stats">Repository statistics</span></button>
        </div>
        <div class="bdp-stats" id="bdp-stats"></div>
        <div class="bdp-note" data-bdp-i18n="check_hint">Verifies repository structure and metadata.</div>
    </section>

    <section class="bdp-card">
        <div class="bdp-card-head">
            <div class="bdp-card-title" data-bdp-i18n="snapshots">Snapshots</div>
            <span class="bdp-muted" id="bdp-snapshot-meta"></span>
        </div>
        <div class="bdp-table-wrap">
            <table class="bdp-table">
                <thead>
                    <tr>
                        <th data-bdp-i18n="snapshot_id">Snapshot</th>
                        <th data-bdp-i18n="snapshot_time">Time</th>
                        <th data-bdp-i18n="snapshot_host">Host</th>
                        <th data-bdp-i18n="snapshot_paths">Paths</th>
                        <th data-bdp-i18n="snapshot_size">Size</th>
                        <th data-bdp-i18n="actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="bdp-snapshots-body">
                    <tr><td colspan="6"><div class="bdp-empty" data-bdp-i18n="loading">Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="bdp-card">
        <div class="bdp-card-head">
            <div class="bdp-card-title" data-bdp-i18n="jobs">Jobs</div>
            <span class="bdp-muted" id="bdp-job-meta"></span>
        </div>
        <div class="bdp-table-wrap">
            <table class="bdp-table">
                <thead>
                    <tr>
                        <th data-bdp-i18n="job_type">Type</th>
                        <th data-bdp-i18n="job_status">Status</th>
                        <th data-bdp-i18n="job_started">Started</th>
                        <th data-bdp-i18n="job_duration">Duration</th>
                        <th data-bdp-i18n="progress">Progress</th>
                        <th data-bdp-i18n="actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="bdp-jobs-body">
                    <tr><td colspan="6"><div class="bdp-empty" data-bdp-i18n="no_jobs">No backup jobs yet</div></td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Backup wizard (step by step) -->
    <div class="bdp-modal" id="bdp-wizard" hidden>
        <div class="bdp-modal-box bdp-wizard-box" role="dialog" aria-modal="true">
            <div class="bdp-modal-head">
                <h3 id="bdp-wizard-title" data-bdp-i18n="wizard">Backup wizard</h3>
                <button type="button" class="bdp-modal-close" data-bdp-action="wizard_close" aria-label="Close">×</button>
            </div>
            <div class="bdp-wizard-steps" id="bdp-wizard-steps">
                <button type="button" class="bdp-wizard-step active" data-wizard-step="0"><span class="bdp-wizard-dot">1</span><span class="bdp-wizard-label" data-bdp-i18n="general">General</span></button>
                <button type="button" class="bdp-wizard-step" data-wizard-step="1"><span class="bdp-wizard-dot">2</span><span class="bdp-wizard-label" data-bdp-i18n="destination">Destination</span></button>
                <button type="button" class="bdp-wizard-step" data-wizard-step="2"><span class="bdp-wizard-dot">3</span><span class="bdp-wizard-label" data-bdp-i18n="source_data">Source data</span></button>
                <button type="button" class="bdp-wizard-step" data-wizard-step="3"><span class="bdp-wizard-dot">4</span><span class="bdp-wizard-label" data-bdp-i18n="schedule">Schedule</span></button>
                <button type="button" class="bdp-wizard-step" data-wizard-step="4"><span class="bdp-wizard-dot">5</span><span class="bdp-wizard-label" data-bdp-i18n="options">Options</span></button>
            </div>
            <div class="bdp-modal-body" id="bdp-wizard-body">
                <div class="bdp-wizard-panel active" data-wizard-panel="0">
                    <div class="bdp-grid">
                        <label class="bdp-field">
                            <span data-bdp-i18n="name">Backup name</span>
                            <input type="text" id="bdp-wz-name" spellcheck="false" data-bdp-i18n-ph="name_placeholder" placeholder="My documents backup">
                            <small class="bdp-field-error" id="bdp-wz-name-error" hidden data-bdp-i18n="name_required">Backup name is required.</small>
                        </label>
                        <label class="bdp-field">
                            <span data-bdp-i18n="description">Description</span>
                            <input type="text" id="bdp-wz-description" spellcheck="false" placeholder="">
                        </label>
                        <div class="bdp-field bdp-span-2">
                            <label class="bdp-check"><input type="checkbox" id="bdp-wz-enabled" checked><span data-bdp-i18n="enabled">Enabled</span></label>
                            <small class="bdp-muted" data-bdp-i18n="auto_run">Automatically run backups</small>
                        </div>
                    </div>
                </div>

                <div class="bdp-wizard-panel" data-wizard-panel="1">
                    <div class="bdp-note" data-bdp-i18n="repo_private">Each backup keeps its own repository: the path, password and host are private to this backup.</div>
                    <div class="bdp-grid">
                        <label class="bdp-field bdp-span-2">
                            <span data-bdp-i18n="repository_path">Repository path or URL</span>
                            <input type="text" id="bdp-wz-repository" spellcheck="false" autocomplete="off" placeholder="/mnt/main_backup/restic">
                            <small class="bdp-field-error" id="bdp-wz-repository-error" hidden data-bdp-i18n="repo_not_configured">Configure a repository first.</small>
                        </label>
                        <label class="bdp-field" id="bdp-wz-password-wrap">
                            <span data-bdp-i18n="repository_password">Repository password</span>
                            <input type="password" id="bdp-wz-password" autocomplete="new-password">
                            <small data-bdp-i18n="password_keep_hint">Leave empty to keep the stored password.</small>
                        </label>
                        <label class="bdp-field">
                            <span data-bdp-i18n="snapshot_host">Host</span>
                            <input type="text" id="bdp-wz-host" spellcheck="false" autocomplete="off" placeholder="appdashboard">
                            <small data-bdp-i18n="host_hint">Snapshot host label stored with this backup.</small>
                        </label>
                    </div>
                    <div class="bdp-host-browser" id="bdp-wz-dest-tree">
                        <div class="bdp-card-subtitle" data-bdp-i18n="dest_tree_caption">Destination folder</div>
                        <div class="bdp-browse-bar">
                            <button type="button" class="bdp-btn bdp-btn-sm" data-bdp-action="wz_dest_up"><span data-bdp-i18n="up">Up</span></button>
                            <button type="button" class="bdp-btn bdp-btn-sm" data-bdp-action="wz_dest_mkdir"><span data-bdp-i18n="new_folder">New folder</span></button>
                            <input type="search" id="bdp-wz-dest-filter" class="bdp-tree-filter" data-bdp-i18n-ph="filter_folders" placeholder="Filter folders…" autocomplete="off">
                            <code class="bdp-tree-path">/</code>
                        </div>
                        <div class="bdp-browse-list" data-bdp-tree="dest"><div class="bdp-empty" data-bdp-i18n="loading">Loading…</div></div>
                        <small data-bdp-i18n="repo_step_hint">Pick a folder on the host or type a path/URL manually.</small>
                    </div>
                    <div class="bdp-row">
                        <button type="button" class="bdp-btn bdp-btn-sm" data-bdp-action="wz_test_rw"><span data-bdp-i18n="test_rw">Test read/write</span></button>
                        <button type="button" class="bdp-btn bdp-btn-sm" data-bdp-action="wz_init"><span data-bdp-i18n="init">Initialize repository</span></button>
                        <span class="bdp-muted" id="bdp-wz-rw-result"></span>
                    </div>
                </div>

                <div class="bdp-wizard-panel" data-wizard-panel="2">
                    <label class="bdp-field">
                        <span data-bdp-i18n="sources">Backup sources</span>
                        <textarea id="bdp-wz-sources" rows="4" spellcheck="false" data-bdp-i18n-ph="source_placeholder" placeholder="/host/home"></textarea>
                        <small class="bdp-field-error" id="bdp-wz-sources-error" hidden data-bdp-i18n="no_sources">Add at least one source folder.</small>
                        <small data-bdp-i18n="sources_step_hint">Tick folders in the tree or type absolute paths, one per line.</small>
                    </label>
                    <div class="bdp-host-browser" id="bdp-wz-source-tree">
                        <div class="bdp-card-subtitle" data-bdp-i18n="source_tree_caption">Folder list</div>
                        <div class="bdp-browse-bar">
                            <button type="button" class="bdp-btn bdp-btn-sm" data-bdp-action="wz_src_up"><span data-bdp-i18n="up">Up</span></button>
                            <button type="button" class="bdp-btn bdp-btn-sm" data-bdp-action="wz_src_mkdir"><span data-bdp-i18n="new_folder">New folder</span></button>
                            <input type="search" id="bdp-wz-src-filter" class="bdp-tree-filter" data-bdp-i18n-ph="filter_folders" placeholder="Filter folders…" autocomplete="off">
                            <code class="bdp-tree-path">/</code>
                        </div>
                        <div class="bdp-browse-list" data-bdp-tree="src"><div class="bdp-empty" data-bdp-i18n="loading">Loading…</div></div>
                    </div>
                    <label class="bdp-field">
                        <span data-bdp-i18n="excludes">Exclude patterns</span>
                        <textarea id="bdp-wz-excludes" rows="2" spellcheck="false" data-bdp-i18n-ph="exclude_placeholder" placeholder="*.tmp"></textarea>
                        <small data-bdp-i18n="excludes_hint">One pattern per line, for example *.log or /host/var/cache.</small>
                    </label>
                </div>

                <div class="bdp-wizard-panel" data-wizard-panel="3">
                    <label class="bdp-check"><input type="checkbox" id="bdp-wz-schedule-enabled"><span data-bdp-i18n="auto_run">Automatically run backups</span></label>
                    <div class="bdp-schedule-preview" id="bdp-wz-schedule-preview">—</div>
                    <div class="bdp-grid">
                        <label class="bdp-field">
                            <span data-bdp-i18n="frequency">Frequency</span>
                            <select id="bdp-wz-frequency">
                                <option value="hourly" data-bdp-i18n="freq_hourly">Every N hours</option>
                                <option value="daily" data-bdp-i18n="freq_daily">Daily</option>
                                <option value="weekly" data-bdp-i18n="freq_weekly">Weekly</option>
                                <option value="monthly" data-bdp-i18n="freq_monthly">Monthly</option>
                            </select>
                        </label>
                        <label class="bdp-field" id="bdp-wz-time-wrap">
                            <span data-bdp-i18n="time">Time</span>
                            <input type="time" id="bdp-wz-time" value="03:00">
                        </label>
                        <label class="bdp-field" id="bdp-wz-weekday-wrap" hidden>
                            <span data-bdp-i18n="weekday">Day of week</span>
                            <select id="bdp-wz-weekday">
                                <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option>
                            </select>
                        </label>
                        <label class="bdp-field" id="bdp-wz-monthday-wrap" hidden>
                            <span data-bdp-i18n="monthday">Day of month</span>
                            <input type="number" id="bdp-wz-monthday" min="1" max="28" value="1">
                        </label>
                        <label class="bdp-field" id="bdp-wz-interval-wrap" hidden>
                            <span data-bdp-i18n="interval_hours">Interval (hours)</span>
                            <input type="number" id="bdp-wz-interval" min="1" max="168" value="6">
                        </label>
                    </div>
                    <div class="bdp-note" data-bdp-i18n="schedule_hint">The scheduler runs every minute inside the container.</div>
                </div>

                <div class="bdp-wizard-panel" data-wizard-panel="4">
                    <div class="bdp-retention-box">
                        <label class="bdp-check"><input type="radio" name="bdp-wz-retention" value="keep_all"><span data-bdp-i18n="keep_all">Keep all backups</span></label>
                        <label class="bdp-check"><input type="radio" name="bdp-wz-retention" value="within" checked><span data-bdp-i18n="delete_older">Delete backups older than</span></label>
                        <div class="bdp-retention-inline">
                            <input type="number" id="bdp-wz-retention-days" min="1" max="3650" value="30">
                            <span data-bdp-i18n="days">days</span>
                        </div>
                    </div>
                    <button type="button" class="bdp-btn bdp-btn-sm bdp-btn-ghost" data-bdp-action="wz_toggle_advanced"><span data-bdp-i18n="advanced_retention">Advanced retention</span></button>
                    <div class="bdp-grid bdp-grid-3" id="bdp-wz-advanced" hidden>
                        <label class="bdp-field"><span data-bdp-i18n="keep_last">Keep last</span><input type="number" id="bdp-wz-keep-last" min="0" max="10000" value="0"></label>
                        <label class="bdp-field"><span data-bdp-i18n="keep_hourly">Keep hourly</span><input type="number" id="bdp-wz-keep-hourly" min="0" max="10000" value="0"></label>
                        <label class="bdp-field"><span data-bdp-i18n="keep_daily">Keep daily</span><input type="number" id="bdp-wz-keep-daily" min="0" max="10000" value="0"></label>
                        <label class="bdp-field"><span data-bdp-i18n="keep_weekly">Keep weekly</span><input type="number" id="bdp-wz-keep-weekly" min="0" max="10000" value="0"></label>
                        <label class="bdp-field"><span data-bdp-i18n="keep_monthly">Keep monthly</span><input type="number" id="bdp-wz-keep-monthly" min="0" max="10000" value="0"></label>
                        <label class="bdp-field"><span data-bdp-i18n="keep_yearly">Keep yearly</span><input type="number" id="bdp-wz-keep-yearly" min="0" max="10000" value="0"></label>
                    </div>
                    <label class="bdp-check"><input type="checkbox" id="bdp-wz-prune" checked><span data-bdp-i18n="prune_after">Prune after forgetting</span></label>
                    <div class="bdp-wizard-summary" id="bdp-wizard-summary"></div>
                </div>
            </div>
            <div class="bdp-modal-foot">
                <button type="button" class="bdp-btn" data-bdp-action="wizard_close" data-bdp-i18n="cancel">Cancel</button>
                <button type="button" class="bdp-btn" data-bdp-action="wizard_back" id="bdp-wz-back" data-bdp-i18n="go_back">Go back</button>
                <button type="button" class="bdp-btn bdp-btn-primary" data-bdp-action="wizard_next" id="bdp-wz-next" data-bdp-i18n="continue">Continue</button>
                <button type="button" class="bdp-btn bdp-btn-primary" data-bdp-action="wizard_submit" id="bdp-wz-submit" hidden data-bdp-i18n="create_backup">Create backup</button>
            </div>
        </div>
    </div>

    <div class="bdp-modal" id="bdp-modal" hidden>
        <div class="bdp-modal-box" role="dialog" aria-modal="true">
            <div class="bdp-modal-head">
                <h3 id="bdp-modal-title"></h3>
                <button type="button" class="bdp-modal-close" data-bdp-action="modal_close" aria-label="Close">×</button>
            </div>
            <div class="bdp-modal-body" id="bdp-modal-body"></div>
            <div class="bdp-modal-foot" id="bdp-modal-foot"></div>
        </div>
    </div>
</div>
