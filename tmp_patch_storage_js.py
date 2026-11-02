#!/usr/bin/env python3
from pathlib import Path
js = Path('project/storage/assets/storage.js').read_text()
pairs = [
("""    function badge(status) {
        var value = String(status || 'unknown');
        return '<span class="stg-badge ' + esc(value) + '">' + esc(value.replace(/_/g, ' ')) + '</span>';
    }""", """    function statusLabel(status) {
        var value = String(status || 'unknown');
        var key = 'status_' + value;
        var label = t(key);
        return label === key ? value.replace(/_/g, ' ') : label;
    }

    function badge(status) {
        var value = String(status || 'unknown');
        return '<span class="stg-badge ' + esc(value) + '">' + esc(statusLabel(value)) + '</span>';
    }"""),
("setText('#stg-kpi-used-sub', pct.toFixed(1) + '% of the pool');", "setText('#stg-kpi-used-sub', t('percent_of_pool', { pct: pct.toFixed(1) }));"),
("setText('#stg-kpi-disks-sub', (d.disk_count || 0) === 1 ? 'disk registered' : 'disks registered');", "setText('#stg-kpi-disks-sub', (d.disk_count || 0) === 1 ? t('disk_registered') : t('disks_registered'));"),
("setText('#stg-disk-count', (d.online_count || 0) + ' online / ' + (d.disk_count || 0) + ' registered');", "setText('#stg-disk-count', t('online_registered', { online: d.online_count || 0, total: d.disk_count || 0 }));"),
("setText('#stg-used-pct', pct.toFixed(1) + '% used');", "setText('#stg-used-pct', t('percent_used', { pct: pct.toFixed(1) }));"),
("setText('#stg-free-sub', 'pool: ' + (d.storage_root || '/storage'));", "setText('#stg-free-sub', t('pool_prefix', { path: d.storage_root || '/storage' }));"),
("setText('#stg-mergerfs', m.mounted ? (m.writable ? 'Online' : 'Read only') : 'Not mounted');", "setText('#stg-mergerfs', m.mounted ? (m.writable ? t('label_online') : t('label_readonly')) : t('not_mounted'));"),
("""        setText('#stg-mergerfs-sub', m.mounted
            ? ((m.branches || []).length + ' branch(es)' + (m.runtime_api ? ' · runtime API' : ''))
            : (m.binary_available ? 'waiting for disks' : 'mergerfs not installed'));""", """        setText('#stg-mergerfs-sub', m.mounted
            ? (t('mergerfs_branches', { count: (m.branches || []).length }) + (m.runtime_api ? t('mergerfs_runtime_suffix') : ''))
            : (m.binary_available ? t('waiting_for_disks') : t('mergerfs_missing')));"""),
("setText('#stg-restic', r.configured ? 'Configured' : 'Not configured');", "setText('#stg-restic', r.configured ? t('label_configured') : t('not_configured'));"),
("setText('#stg-last-backup', r.last_backup_at ? fmtDate(r.last_backup_at) : 'Never');", "setText('#stg-last-backup', r.last_backup_at ? fmtDate(r.last_backup_at) : t('never'));"),
("setText('#stg-disks-hint', (d.disks_root || '/disks') + ' · scanned automatically');", "setText('#stg-disks-hint', (d.disks_root || '/disks') + ' · ' + t('auto_scanned'));"),
("+ '% used</small></div>';", "+ esc(t('legend_used', { pct: (Number(disk.percent_used) || 0).toFixed(0) })) + '</small></div>';"),
("esc(t('checking_disk').replace('…', ''))", "esc(t('check_disk_btn'))"),
("<span>24 h</span>", "<span>' + esc(t('usage_hours')) + '</span>"),
("chart.innerHTML = '<div class=\"stg-empty\">No migrations yet.</div>';", "chart.innerHTML = '<div class=\"stg-empty\">' + esc(t('no_migrations')) + '</div>';"),
("hint.textContent = rows.length ? rows.length + ' operation(s)' : '';", "hint.textContent = rows.length ? t('operations_count', { count: rows.length }) : '';"),
("body.innerHTML = '<tr><td colspan=\"8\"><div class=\"stg-empty\">No migrations yet.</div></td></tr>';", "body.innerHTML = '<tr><td colspan=\"8\"><div class=\"stg-empty\">' + esc(t('no_migrations')) + '</div></td></tr>';"),
("'>Details</button>';", "">' + esc(t('details')) + '</button>';"),
("'>Resume</button>';", "">' + esc(t('resume')) + '</button>';"),
("('ETA ' + fmtDuration(remaining))", "(t('eta') + ' ' + fmtDuration(remaining))"),
("(m.error_count + ' error(s)')", "t('errors', { count: m.error_count })"),
("('Current: ' + m.current_file)", "t('current_file', { file: m.current_file })"),
("box.innerHTML = '<div class=\"stg-empty\">No events.</div>';", "box.innerHTML = '<div class=\"stg-empty\">' + esc(t('no_events')) + '</div>';"),
("select.innerHTML = '<option value=\"\">Not configured</option>'", "select.innerHTML = '<option value=\"\">' + esc(t('not_configured')) + '</option>'"),
("Checking free space and conflicts…", "' + esc(t('checking_space')) + '"),
("fmtBytes(t.usable_bytes) + ' usable of ' + fmtBytes(t.free_bytes)", "esc(t('usable_of', { usable: fmtBytes(t.usable_bytes), free: fmtBytes(t.free_bytes) }))"),
("|| '<div class=\"stg-empty\">No online target disks.</div>';", "|| '<div class=\"stg-empty\">' + esc(t('no_target_disks')) + '</div>';"),
("conflicts + ' conflicting file(s)'", "esc(t('conflicting_files', { count: conflicts }))"),
("' on ' + esc(c.target)", "' + esc(t('conflict_on', { target: c.target }))"),
("Conflicting files are never overwritten. Resolve them manually and retry.", "' + esc(t('conflicts_hint')) + '"),
("<span>Disk</span>", "<span>' + esc(t('disk')) + '</span>"),
("<span>Data to move</span>", "<span>' + esc(t('data_move')) + '</span>"),
("<span>Files</span>", "<span>' + esc(t('files')) + '</span>"),
("<span>Required headroom</span>", "<span>' + esc(t('required_headroom')) + '</span>"),
("<span>Free on targets</span>", "<span>' + esc(t('free_on_targets')) + '</span>"),
("<span>Additional space needed</span>", "<span>' + esc(t('additional_space')) + '</span>"),
("Target disks</div>", "' + esc(t('target_disks')) + '</div>"),
("There is enough free space to evacuate this disk.", "' + esc(t('enough_space')) + '"),
("Not enough free space. Removal is blocked.", "' + esc(t('not_enough_space')) + '"),
("A Restic snapshot is created before copying. Files are copied to explicit target disks and verified by SHA-256. Original files are kept unless deletion is enabled in settings.", "' + esc(t('removal_note')) + '"),
("showAlert('Migration started. You can close this page; the worker continues in the background.', 'ok');", "showAlert(t('migration_started'), 'ok');"),
("showAlert('Could not start migration: ' + err.message, 'error');", "showAlert(t('migration_start_failed', { error: err.message }), 'error');"),
("<span>Status</span>", "<span>' + esc(t('status')) + '</span>"),
("<span>Progress</span>", "<span>' + esc(t('progress')) + '</span>"),
("<span>Data</span>", "<span>' + esc(t('data')) + '</span>"),
("<span>Speed</span>", "<span>' + esc(t('speed')) + '</span>"),
("<span>Errors</span>", "<span>' + esc(t('error')) + '</span>"),
("'Files: ' + Object.keys(counts)", "esc(t('files_breakdown', { list: Object.keys(counts)"),
("showAlert('No migration to show.', 'warn');", "showAlert(t('no_migration'), 'warn');"),
("Loading…", "' + esc(t('loading')) + '"),
("showAlert('Storage settings saved.', 'ok');", "showAlert(t('settings_saved'), 'ok');"),
("showAlert('Could not save settings: ' + err.message, 'error');", "showAlert(t('settings_failed', { error: err.message }), 'error');"),
("showAlert('Select a backup profile first.', 'warn');", "showAlert(t('select_profile'), 'warn');"),
("showAlert('The /storage path was added to the backup profile.', 'ok');", "showAlert(t('profile_added'), 'ok');"),
("showAlert('Could not update the profile: ' + err.message, 'error');", "showAlert(t('profile_failed', { error: err.message }), 'error');"),
("showAlert('Storage backup queued (job ' + ((res.job && res.job.id) || '') + '). Track it in the Backup manager.', 'ok');", "showAlert(t('backup_queued', { job: (res.job && res.job.id) || '' }), 'ok');"),
("showAlert('Could not start backup: ' + err.message, 'error');", "showAlert(t('backup_failed', { error: err.message }), 'error');"),
("showAlert('Checking disk…');", "showAlert(t('checking_disk'));"),
("showAlert(errors ? ('Disk check: ' + errors) : ('Disk check: ' + (res.result.status || 'ok')), errors ? 'warn' : 'ok');", "showAlert(t('disk_check_report', { detail: errors || (res.result.status || 'ok') }), errors ? 'warn' : 'ok');"),
("showAlert('Disk check failed: ' + err.message, 'error');", "showAlert(t('disk_check_failed', { error: err.message }), 'error');"),
("window.confirm('Cancel the running migration? Already copied and verified files are kept; the source disk stays in the pool.')", "window.confirm(t('cancel_confirm'))"),
("showAlert('Cancellation requested.', 'warn');", "showAlert(t('cancellation_requested'), 'warn');"),
("showAlert('Could not cancel: ' + err.message, 'error');", "showAlert(t('cancel_failed', { error: err.message }), 'error');"),
("showAlert('Scanning /disks…');", "showAlert(t('scanning_disks'));"),
("showAlert('Disk scan completed.', 'ok');", "showAlert(t('scan_completed'), 'ok');"),
("showAlert('Scan failed: ' + err.message, 'error');", "showAlert(t('scan_failed', { error: err.message }), 'error');"),
("window.confirm('Resume this migration from its last verified checkpoint?')", "window.confirm(t('resume_confirm'))"),
("showAlert('Migration resumed.', 'ok');", "showAlert(t('migration_resumed'), 'ok');"),
("showAlert('Could not resume: ' + err.message, 'error');", "showAlert(t('resume_failed', { error: err.message }), 'error');"),
("if (root.dataset.stgReady === '1') { state.root = root; return; }", "if (root.dataset.stgReady === '1') { state.root = root; refresh(true); return; }"),
]
for old, new in pairs:
    if old not in js:
        raise SystemExit('missing: ' + old[:100])
    js = js.replace(old, new, 1)
# confirm_remove migration start msg
old_msg = """        var msg = 'Copy ' + fmtBytes(check.source_bytes) + ' from ' + (check.disk ? check.disk.name : 'the disk') +
            ' to ' + check.targets.length + ' disk(s)? The disk will be removed from the pool after verification.';"""
new_msg = """        var msg = t('confirm_remove', {
            size: fmtBytes(check.source_bytes),
            disk: (check.disk ? check.disk.name : t('disk')),
            count: check.targets.length
        });"""
if old_msg in js:
    js = js.replace(old_msg, new_msg, 1)
# files_breakdown closing paren fix - the partial replace may be broken
if "esc(t('files_breakdown', { list: Object.keys(counts)" in js and "}).join(' · ') }))" not in js:
    js = js.replace(
        "esc(t('files_breakdown', { list: Object.keys(counts).map(function (k) { return esc(k) + ' ' + counts[k]; }).join(' · ') + '</div>' +",
        "esc(t('files_breakdown', { list: Object.keys(counts).map(function (k) { return esc(k) + ' ' + counts[k]; }).join(' · ') })) + '</div>' +",
        1,
    )
Path('project/storage/assets/storage.js').write_text(js)
print('done')
