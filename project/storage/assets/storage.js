/**
 * Storage Manager — panel behaviour.
 * Works both inside the Admin Panel and on the standalone page.
 */
(function () {
    'use strict';

    var state = {
        root: null,
        base: '/project/storage/index.php',
        csrf: '',
        data: null,
        poll: null,
        removeDiskId: 0,
        removeCheck: null,
        lastStatus: null
    };

    function el(sel, root) { return (root || state.root || document).querySelector(sel); }
    function els(sel, root) { return Array.prototype.slice.call((root || state.root || document).querySelectorAll(sel)); }

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function fmtBytes(value) {
        var bytes = Number(value) || 0;
        var units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
        var i = 0;
        while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
        return (i === 0 ? Math.round(bytes) : bytes.toFixed(bytes >= 100 ? 0 : 1)) + ' ' + units[i];
    }

    function fmtDate(ts) {
        var n = Number(ts) || 0;
        if (n <= 0) return '—';
        try { return new Date(n * 1000).toLocaleString(); } catch (e) { return '—'; }
    }

    function fmtDuration(seconds) {
        var s = Math.max(0, Math.round(Number(seconds) || 0));
        if (s < 60) return s + 's';
        var m = Math.floor(s / 60);
        if (m < 60) return m + 'm ' + (s % 60) + 's';
        return Math.floor(m / 60) + 'h ' + (m % 60) + 'm';
    }

    function badge(status) {
        var value = String(status || 'unknown');
        return '<span class="stg-badge ' + esc(value) + '">' + esc(value.replace(/_/g, ' ')) + '</span>';
    }

    function showAlert(message, type) {
        var box = el('#stg-alert');
        if (!box) return;
        if (!message) { box.hidden = true; box.textContent = ''; return; }
        box.hidden = false;
        box.className = 'stg-alert ' + (type || '');
        box.textContent = message;
    }

    function api(action, options) {
        options = options || {};
        var url = state.base + '?api=' + encodeURIComponent(action);
        var init = {
            method: options.method || 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-Token': state.csrf }
        };
        if (options.method === 'POST') {
            init.headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(options.body || {});
        }
        return fetch(url, init).then(function (res) {
            return res.json().catch(function () { return { ok: false, error: 'invalid_response' }; })
                .then(function (data) {
                    if (!res.ok || !data.ok) {
                        var err = new Error((data && data.error) || ('HTTP ' + res.status));
                        err.payload = data;
                        throw err;
                    }
                    return data;
                });
        });
    }

    function refresh(silent) {
        if (!state.root) return Promise.resolve();
        if (!silent) showAlert('');
        return api('bootstrap').then(function (data) {
            state.data = data;
            renderAll();
            managePolling();
        }).catch(function (err) {
            showAlert('Failed to load storage status: ' + err.message, 'error');
            var pill = el('#stg-pill');
            if (pill) { pill.className = 'stg-pill err'; el('#stg-pill-text').textContent = 'Unavailable'; }
        });
    }

    function renderAll() {
        renderSummary();
        renderDisks();
        renderMigrations();
        renderEvents();
        renderSettings();
    }

    function renderSummary() {
        var d = state.data || {};
        var m = d.mergerfs || {};
        var r = d.restic || {};
        el('#stg-total').textContent = fmtBytes(d.total_bytes);
        el('#stg-used').textContent = fmtBytes(d.used_bytes);
        el('#stg-free').textContent = fmtBytes(d.free_bytes);
        el('#stg-disk-count').textContent = (d.online_count || 0) + ' online / ' + (d.disk_count || 0) + ' registered';
        var pct = Math.max(0, Math.min(100, Number(d.percent_used) || 0));
        var bar = el('#stg-used-bar');
        bar.style.width = pct + '%';
        bar.className = pct >= 90 ? 'danger' : (pct >= 75 ? 'warn' : '');
        var ring = el('#stg-ring');
        if (ring) ring.style.setProperty('--pct', String(pct));
        el('#stg-ring-pct').textContent = pct.toFixed(0) + '%';
        el('#stg-used-pct').textContent = pct.toFixed(1) + '% used';
        el('#stg-free-sub').textContent = 'pool: ' + (d.storage_root || '/storage');
        el('#stg-mergerfs').textContent = m.mounted ? (m.writable ? 'Online' : 'Read only') : 'Not mounted';
        el('#stg-mergerfs-sub').textContent = m.mounted
            ? ((m.branches || []).length + ' branch(es)' + (m.runtime_api ? ' · runtime API' : ''))
            : (m.binary_available ? 'waiting for disks' : 'mergerfs not installed');
        el('#stg-restic').textContent = r.configured ? 'Configured' : 'Not configured';
        el('#stg-last-backup').textContent = r.last_backup_at ? ('Last backup: ' + fmtDate(r.last_backup_at)) : 'No storage backup yet';

        var pill = el('#stg-pill');
        var pillText = el('#stg-pill-text');
        if (d.active_migration) {
            pill.className = 'stg-pill';
            pillText.textContent = 'Migration ' + (d.active_migration.status || '').replace(/_/g, ' ');
        } else if (!d.mergerfs || !d.mergerfs.mounted) {
            pill.className = 'stg-pill';
            pillText.textContent = (d.disk_count || 0) > 0 ? 'Pool not mounted' : 'No disks';
        } else if (!d.mergerfs.writable) {
            pill.className = 'stg-pill err';
            pillText.textContent = 'Pool is not writable';
        } else {
            pill.className = 'stg-pill ok';
            pillText.textContent = 'Pool online';
        }
        el('#stg-disks-hint').textContent = (d.disks_root || '/disks') + ' · scanned by the watcher';
    }

    function renderDisks() {
        var body = el('#stg-disks-grid');
        var empty = el('#stg-disks-empty');
        var disks = (state.data && state.data.disks ? state.data.disks : []);
        if (!body) return;
        if (!disks.length) {
            body.innerHTML = '';
            if (empty) empty.hidden = false;
            return;
        }
        if (empty) empty.hidden = true;
        body.innerHTML = disks.map(function (disk) {
            var pct = Math.max(0, Math.min(100, Number(disk.percent_used) || 0));
            var barClass = pct >= 90 ? 'danger' : (pct >= 75 ? 'warn' : '');
            var status = String(disk.status || '');
            var canRemove = status === 'online' && !(state.data && state.data.active_migration);
            var actions = '';
            if (status === 'ready_to_remove') {
                actions = '<span class="stg-muted">Remove its compose line, then recreate the container.</span>';
            } else {
                actions += '<button type="button" class="stg-btn stg-btn-sm" data-stg-action="disk_check" data-stg-disk="' + esc(disk.id) + '">Check</button>';
                actions += '<button type="button" class="stg-btn stg-btn-sm stg-btn-danger" data-stg-action="remove_disk" data-stg-disk="' + esc(disk.id) + '"' + (canRemove ? '' : ' disabled') + '>Safely remove</button>';
            }
            return '<article class="stg-disk-card' + (status === 'ready_to_remove' ? ' stg-disk-ready' : '') + '">' +
                '<div class="stg-disk-card-head"><div><div class="stg-disk-name">' + esc(disk.name) + '</div><div class="stg-disk-path">' + esc(disk.mount_path) + '</div></div>' + badge(status) + '</div>' +
                '<div class="stg-disk-usage"><div class="stg-disk-usage-line"><strong>' + fmtBytes(disk.used_bytes) + '</strong><span>of ' + fmtBytes(disk.total_bytes) + '</span><span class="stg-disk-free">' + fmtBytes(disk.free_bytes) + ' free</span></div><div class="stg-bar"><span class="' + barClass + '" style="width:' + pct + '%"></span></div><div class="stg-disk-pct">' + pct.toFixed(1) + '% used</div></div>' +
                '<div class="stg-disk-facts"><div><span>Filesystem</span><strong>' + esc(disk.filesystem || '—') + '</strong></div><div><span>Identity</span><strong class="stg-uuid" title="' + esc(disk.uuid) + '">' + esc(disk.uuid) + '</strong></div><div><span>Last check</span><strong>' + fmtDate(disk.last_health_check_at) + '</strong></div><div><span>Last backup</span><strong>' + fmtDate(disk.last_backup_at) + '</strong></div></div>' +
                '<div class="stg-disk-actions">' + actions + '</div></article>';
        }).join('');
    }

    function diskName(id) {
        var disks = (state.data && state.data.disks) || [];
        for (var i = 0; i < disks.length; i++) {
            if (Number(disks[i].id) === Number(id)) return disks[i].name;
        }
        return '#' + id;
    }

    function renderMigrations() {
        var card = el('#stg-active-card');
        var active = state.data && state.data.active_migration;
        if (!active) {
            card.hidden = true;
        } else {
            card.hidden = false;
            el('#stg-active-state').textContent = diskName(active.source_disk_id) + ' · ' + (active.status || '').replace(/_/g, ' ');
            updateProgress(active);
        }

        var rows = (state.data && state.data.migrations) || [];
        var body = el('#stg-history-body');
        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="8"><div class="stg-empty">No migrations yet.</div></td></tr>';
            return;
        }
        body.innerHTML = rows.map(function (m) {
            var pct = Math.max(0, Math.min(100, Number(m.progress) || 0));
            var err = m.error ? '<span class="stg-conflict">' + esc(m.error) + '</span>' : '—';
            var actions = '<button type="button" class="stg-btn stg-btn-sm" data-stg-action="progress_details" data-stg-migration="' + esc(m.id) + '">Details</button>';
            if (m.status === 'paused' || m.status === 'failed') {
                actions += ' <button type="button" class="stg-btn stg-btn-sm" data-stg-action="migration_resume" data-stg-migration="' + esc(m.id) + '">Resume</button>';
            }
            return '<tr>' +
                '<td>' + fmtDate(m.started_at || m.created_at) + '</td>' +
                '<td>' + esc(diskName(m.source_disk_id)) + '</td>' +
                '<td>' + badge(m.status) + '</td>' +
                '<td>' + pct.toFixed(1) + '%</td>' +
                '<td>' + (m.migrated_files || 0) + ' / ' + (m.total_files || 0) + '</td>' +
                '<td>' + fmtBytes(m.migrated_bytes) + ' / ' + fmtBytes(m.total_bytes) + '</td>' +
                '<td>' + err + '</td>' +
                '<td><div class="stg-actions">' + actions + '</div></td>' +
                '</tr>';
        }).join('');
    }

    function updateProgress(m) {
        var pct = Math.max(0, Math.min(100, Number(m.progress) || 0));
        el('#stg-migration-bar').style.width = pct + '%';
        el('#stg-migration-pct').textContent = pct.toFixed(1) + '%';
        el('#stg-migration-bytes').textContent = fmtBytes(m.migrated_bytes) + ' / ' + fmtBytes(m.total_bytes);
        var speed = Number(m.speed_bytes) || 0;
        el('#stg-migration-speed').textContent = speed > 0 ? (fmtBytes(speed) + '/s') : '';
        var remaining = speed > 0 ? ((Number(m.total_bytes) || 0) - (Number(m.migrated_bytes) || 0)) / speed : 0;
        el('#stg-migration-eta').textContent = remaining > 0 ? ('ETA ' + fmtDuration(remaining)) : '';
        el('#stg-migration-errors').textContent = Number(m.error_count) > 0 ? (m.error_count + ' error(s)') : '';
        el('#stg-migration-current').textContent = m.current_file ? ('Current: ' + m.current_file) : (m.error ? m.error : '');
    }

    function renderEvents() {
        var box = el('#stg-events');
        var events = (state.data && state.data.events) || [];
        if (!events.length) {
            box.innerHTML = '<div class="stg-empty">No events.</div>';
            return;
        }
        box.innerHTML = events.slice(0, 40).map(function (e) {
            return '<div class="stg-event">' +
                '<div class="stg-event-time">' + fmtDate(e.created_at) + '</div>' +
                '<div><div class="stg-event-type">' + esc(String(e.event_type || '').replace(/_/g, ' ')) + '</div>' +
                '<div class="stg-event-msg">' + esc(e.message) + '</div>' +
                (e.disk_name ? '<div class="stg-event-disk">' + esc(e.disk_name) + '</div>' : '') +
                '</div></div>';
        }).join('');
    }

    function renderSettings() {
        var s = (state.data && state.data.settings) || {};
        el('#stg-reserve').value = s.reserve_percent == null ? 12 : s.reserve_percent;
        el('#stg-minfree').value = s.min_free_gb == null ? 10 : s.min_free_gb;
        el('#stg-interval').value = s.watch_interval_seconds == null ? 30 : s.watch_interval_seconds;
        el('#stg-backup-required').checked = !!s.backup_required;
        el('#stg-delete-source').checked = !!s.delete_source_after_migration;
        var select = el('#stg-backup-profile');
        var profiles = (state.data && state.data.backup_profiles) || [];
        var current = s.backup_profile || '';
        select.innerHTML = '<option value="">Not configured</option>' + profiles.map(function (p) {
            return '<option value="' + esc(p.id) + '">' + esc(p.name || p.id) + (p.repository ? ' · ' + esc(p.repository) : '') + '</option>';
        }).join('');
        if (current && profiles.some(function (p) { return p.id === current; })) select.value = current;
    }

    function managePolling() {
        var active = state.data && state.data.active_migration;
        if (!active) {
            if (state.poll) { clearInterval(state.poll); state.poll = null; }
            return;
        }
        if (state.poll) return;
        state.poll = setInterval(function () {
            if (!state.data || !state.data.active_migration) {
                clearInterval(state.poll); state.poll = null;
                return;
            }
            api('migration_status?id=' + encodeURIComponent(state.data.active_migration.id))
                .then(function (res) {
                    var m = res.migration;
                    if (!state.data.active_migration || state.data.active_migration.id !== m.id) return;
                    state.data.active_migration = m;
                    renderMigrations();
                    if (el('#stg-progress-overlay') && !el('#stg-progress-overlay').hidden) renderProgressDialog(res);
                    if (['completed', 'failed', 'paused'].indexOf(String(m.status)) !== -1) {
                        clearInterval(state.poll); state.poll = null;
                        setTimeout(function () { refresh(true); }, 400);
                    }
                })
                .catch(function () { /* transient */ });
        }, 2000);
    }

    function openRemoveDialog(diskId) {
        state.removeDiskId = Number(diskId);
        state.removeCheck = null;
        var overlay = el('#stg-remove-overlay');
        overlay.hidden = false;
        el('#stg-remove-confirm').disabled = true;
        el('#stg-remove-body').innerHTML = '<div class="stg-empty">Checking free space and conflicts…</div>';
        api('migration_check?id=' + encodeURIComponent(diskId)).then(function (res) {
            if (state.removeDiskId !== Number(diskId)) return;
            state.removeCheck = res.precheck;
            renderRemoveDialog(res.precheck);
        }).catch(function (err) {
            el('#stg-remove-body').innerHTML = '<div class="stg-alert error">' + esc(err.message) + '</div>';
        });
    }

    function renderRemoveDialog(p) {
        var disk = p.disk || {};
        var enough = !!p.enough_space;
        var conflicts = p.conflicts_count || 0;
        var targets = (p.targets || []).map(function (t) {
            return '<div class="stg-target"><span>' + esc(t.name) + '</span><span>' + fmtBytes(t.usable_bytes) + ' usable of ' + fmtBytes(t.free_bytes) + '</span></div>';
        }).join('') || '<div class="stg-empty">No online target disks.</div>';
        var conflictHtml = '';
        if (conflicts > 0) {
            conflictHtml = '<div><div class="stg-warn-text">' + conflicts + ' conflicting file(s)</div><ul class="stg-list">' +
                (p.conflicts || []).slice(0, 12).map(function (c) {
                    return '<li><span class="stg-conflict">' + esc(c.rel_path) + '</span> on ' + esc(c.target) + '</li>';
                }).join('') +
                '</ul><div class="stg-note">Conflicting files are never overwritten. Resolve them manually and retry.</div></div>';
        }
        var html =
            '<div class="stg-check-grid">' +
            '<div class="stg-check-box"><span>Disk</span><strong>' + esc(disk.name || '') + '</strong></div>' +
            '<div class="stg-check-box"><span>Data to move</span><strong>' + fmtBytes(p.source_bytes) + '</strong></div>' +
            '<div class="stg-check-box"><span>Files</span><strong>' + (p.source_files || 0) + '</strong></div>' +
            '<div class="stg-check-box"><span>Required headroom</span><strong>' + fmtBytes(p.required_bytes) + '</strong></div>' +
            '<div class="stg-check-box"><span>Free on targets</span><strong>' + fmtBytes(p.total_usable_bytes) + '</strong></div>' +
            '<div class="stg-check-box"><span>Additional space needed</span><strong class="' + (enough ? 'stg-ok-text' : 'stg-warn-text') + '">' + fmtBytes(p.shortfall_bytes) + '</strong></div>' +
            '</div>' +
            '<div><div class="stg-muted">Target disks</div>' + targets + '</div>' +
            (enough ? '<div class="stg-ok-text">There is enough free space to evacuate this disk.</div>' : '<div class="stg-warn-text">Not enough free space. Removal is blocked.</div>') +
            conflictHtml +
            '<div class="stg-note">A Restic snapshot is created before copying. Files are copied to explicit target disks and verified by SHA-256. Original files are kept unless deletion is enabled in settings.</div>';
        el('#stg-remove-body').innerHTML = html;
        el('#stg-remove-confirm').disabled = !(p.can_start && enough && conflicts === 0);
    }

    function startMigration() {
        if (!state.removeDiskId) return;
        var check = state.removeCheck;
        if (!check || !check.can_start) return;
        var msg = 'Copy ' + fmtBytes(check.source_bytes) + ' from ' + (check.disk ? check.disk.name : 'the disk') +
            ' to ' + check.targets.length + ' disk(s)? The disk will be removed from the pool after verification.';
        if (!window.confirm(msg)) return;
        el('#stg-remove-confirm').disabled = true;
        api('migration_start', { method: 'POST', body: { id: state.removeDiskId, confirm: true } })
            .then(function () {
                el('#stg-remove-overlay').hidden = true;
                showAlert('Migration started. You can close this page; the worker continues in the background.', 'ok');
                return refresh(true);
            })
            .catch(function (err) {
                showAlert('Could not start migration: ' + err.message, 'error');
                el('#stg-remove-confirm').disabled = false;
            });
    }

    function renderProgressDialog(res) {
        var m = res.migration || {};
        var counts = res.file_counts || {};
        var events = res.events || [];
        var pct = Math.max(0, Math.min(100, Number(m.progress) || 0));
        var html =
            '<div class="stg-check-grid">' +
            '<div class="stg-check-box"><span>Status</span><strong>' + badge(m.status) + '</strong></div>' +
            '<div class="stg-check-box"><span>Progress</span><strong>' + pct.toFixed(1) + '%</strong></div>' +
            '<div class="stg-check-box"><span>Data</span><strong>' + fmtBytes(m.migrated_bytes) + ' / ' + fmtBytes(m.total_bytes) + '</strong></div>' +
            '<div class="stg-check-box"><span>Files</span><strong>' + (m.migrated_files || 0) + ' / ' + (m.total_files || 0) + '</strong></div>' +
            '<div class="stg-check-box"><span>Speed</span><strong>' + (Number(m.speed_bytes) > 0 ? fmtBytes(m.speed_bytes) + '/s' : '—') + '</strong></div>' +
            '<div class="stg-check-box"><span>Errors</span><strong>' + (m.error_count || 0) + '</strong></div>' +
            '</div>' +
            '<div class="stg-progress"><span style="width:' + pct + '%"></span></div>' +
            '<div class="stg-current">' + esc(m.current_file || '') + '</div>' +
            (m.error ? '<div class="stg-alert error">' + esc(m.error) + '</div>' : '') +
            '<div class="stg-muted">Files: ' + Object.keys(counts).map(function (k) { return esc(k) + ' ' + counts[k]; }).join(' · ') + '</div>' +
            (events.length ? '<div class="stg-events">' + events.slice(0, 12).map(function (e) {
                return '<div class="stg-event"><div class="stg-event-time">' + fmtDate(e.created_at) + '</div><div><div class="stg-event-msg">' + esc(e.message) + '</div></div></div>';
            }).join('') + '</div>' : '');
        el('#stg-progress-body').innerHTML = html;
    }

    function openProgressDialog(migrationId) {
        var active = null;
        if (migrationId) {
            active = { id: migrationId };
        } else {
            active = state.data && state.data.active_migration;
        }
        if (!active) {
            var latest = state.data && state.data.migrations && state.data.migrations[0];
            if (!latest) { showAlert('No migration to show.', 'warn'); return; }
            active = latest;
        }
        el('#stg-progress-overlay').hidden = false;
        el('#stg-progress-body').innerHTML = '<div class="stg-empty">Loading…</div>';
        api('migration_status?id=' + encodeURIComponent(active.id)).then(renderProgressDialog).catch(function (err) {
            el('#stg-progress-body').innerHTML = '<div class="stg-alert error">' + esc(err.message) + '</div>';
        });
    }

    function saveSettings() {
        var body = {
            reserve_percent: Number(el('#stg-reserve').value),
            min_free_gb: Number(el('#stg-minfree').value),
            watch_interval_seconds: Number(el('#stg-interval').value),
            backup_required: el('#stg-backup-required').checked,
            delete_source_after_migration: el('#stg-delete-source').checked,
            backup_profile: el('#stg-backup-profile').value
        };
        api('settings_save', { method: 'POST', body: body }).then(function () {
            showAlert('Storage settings saved.', 'ok');
            return refresh(true);
        }).catch(function (err) { showAlert('Could not save settings: ' + err.message, 'error'); });
    }

    function attachBackup() {
        var profile = el('#stg-backup-profile').value;
        if (!profile) { showAlert('Select a backup profile first.', 'warn'); return; }
        api('backup_attach', { method: 'POST', body: { profile: profile } }).then(function () {
            showAlert('The /storage path was added to the backup profile.', 'ok');
            return refresh(true);
        }).catch(function (err) { showAlert('Could not update the profile: ' + err.message, 'error'); });
    }

    function backupNow() {
        api('backup_now', { method: 'POST', body: {} }).then(function (res) {
            showAlert('Storage backup queued (job ' + ((res.job && res.job.id) || '') + '). Track it in the Backup manager.', 'ok');
        }).catch(function (err) { showAlert('Could not start backup: ' + err.message, 'error'); });
    }

    function runDiskCheck(diskId) {
        showAlert('Checking disk…');
        api('disk_check', { method: 'POST', body: { id: diskId } }).then(function (res) {
            var report = (res.result && res.result.report) || {};
            var errors = (report.errors || []).join(', ');
            showAlert(errors ? ('Disk check: ' + errors) : ('Disk check: ' + (res.result.status || 'ok')), errors ? 'warn' : 'ok');
            return refresh(true);
        }).catch(function (err) { showAlert('Disk check failed: ' + err.message, 'error'); });
    }

    function cancelMigration() {
        var active = state.data && state.data.active_migration;
        if (!active) return;
        if (!window.confirm('Cancel the running migration? Already copied and verified files are kept; the source disk stays in the pool.')) return;
        api('migration_cancel', { method: 'POST', body: { id: active.id } }).then(function () {
            showAlert('Cancellation requested.', 'warn');
            return refresh(true);
        }).catch(function (err) { showAlert('Could not cancel: ' + err.message, 'error'); });
    }

    function onClick(event) {
        var close = event.target.closest('[data-stg-close]');
        if (close) {
            var which = close.getAttribute('data-stg-close');
            if (which === 'remove') el('#stg-remove-overlay').hidden = true;
            if (which === 'progress') el('#stg-progress-overlay').hidden = true;
            return;
        }
        var trigger = event.target.closest('[data-stg-action]');
        if (!trigger || !state.root.contains(trigger)) return;
        var action = trigger.getAttribute('data-stg-action');
        var diskId = Number(trigger.getAttribute('data-stg-disk') || 0);
        var migrationId = trigger.getAttribute('data-stg-migration') || '';
        if (action === 'refresh') { refresh(); return; }
        if (action === 'scan') {
            showAlert('Scanning /disks…');
            api('scan').then(function () { showAlert('Disk scan completed.', 'ok'); return refresh(true); })
                .catch(function (err) { showAlert('Scan failed: ' + err.message, 'error'); });
            return;
        }
        if (action === 'disk_check') { runDiskCheck(diskId); return; }
        if (action === 'remove_disk') { openRemoveDialog(diskId); return; }
        if (action === 'migration_start') { startMigration(); return; }
        if (action === 'migration_cancel') { cancelMigration(); return; }
        if (action === 'progress_details') { openProgressDialog(migrationId); return; }
        if (action === 'migration_resume') {
            if (!window.confirm('Resume this migration from its last verified checkpoint?')) return;
            api('migration_resume', { method: 'POST', body: { id: migrationId } })
                .then(function () { showAlert('Migration resumed.', 'ok'); return refresh(true); })
                .catch(function (err) { showAlert('Could not resume: ' + err.message, 'error'); });
            return;
        }
        if (action === 'save_settings') { saveSettings(); return; }
        if (action === 'backup_attach') { attachBackup(); return; }
        if (action === 'backup_now') { backupNow(); return; }
    }

    function init(root) {
        root = root && root.querySelector ? root : document.getElementById('stg-app');
        if (!root) return;
        if (root.dataset.stgReady === '1') { state.root = root; return; }
        root.dataset.stgReady = '1';
        state.root = root;
        state.base = root.getAttribute('data-stg-base') || '/project/storage/index.php';
        state.csrf = root.getAttribute('data-stg-csrf') || '';
        root.addEventListener('click', onClick);
        refresh();
    }

    window.StoragePanel = {
        init: init,
        refresh: function () { return refresh(true); },
        openProgress: function () { openProgressDialog(); }
    };

    function autoInit() {
        var root = document.getElementById('stg-app');
        if (!root) return;
        // Inside the Admin Panel the panel code calls init() explicitly; this
        // covers the standalone page and dynamic re-insertion.
        init(root);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoInit);
    } else {
        autoInit();
    }
})();
