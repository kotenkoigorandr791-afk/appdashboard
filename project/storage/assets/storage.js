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
        activeTab: 'overview',
        i18n: {}
    };

    function el(sel, root) { return (root || state.root || document).querySelector(sel); }
    function els(sel, root) { return Array.prototype.slice.call((root || state.root || document).querySelectorAll(sel)); }

    function t(key, vars) {
        var str = (state.i18n && state.i18n[key]) || key;
        if (vars) {
            Object.keys(vars).forEach(function (k) {
                str = str.replace(new RegExp('\\{' + k + '\\}', 'g'), String(vars[k]));
            });
        }
        return str;
    }

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function icon(name, size) {
        var paths = {
            disk: '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/><path d="M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/>',
            check: '<path d="M20 6 9 17l-5-5"/>',
            alert: '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
            clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
            trash: '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/>',
            scan: '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/><path d="M8 11h6"/>',
            arrow: '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
            server: '<rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01"/><path d="M7 17h.01"/>'
        };
        var body = paths[name] || paths.server;
        var s = size || 15;
        return '<svg width="' + s + '" height="' + s + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + body + '</svg>';
    }

    function fmtBytes(value) {
        var bytes = Number(value) || 0;
        var units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
        var i = 0;
        while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
        return (i === 0 ? Math.round(bytes) : bytes.toFixed(bytes >= 100 ? 0 : 1)) + ' ' + units[i];
    }

    function sparkline(points, diskId) {
        var rows = Array.isArray(points) ? points : [];
        if (rows.length === 1) rows = [rows[0], rows[0]];
        if (rows.length < 2) {
            return '<div class="stg-spark-empty">' + esc(t('usage_pending')) + '</div>';
        }
        var width = 240;
        var height = 44;
        var coords = rows.map(function (p, i) {
            var total = Number(p.total) || 1;
            var pct = Math.max(0, Math.min(100, (Number(p.used) || 0) / total * 100));
            var x = rows.length === 1 ? width : (i / (rows.length - 1)) * width;
            var y = height - (pct / 100) * (height - 4) - 2;
            return [x, y];
        });
        var line = coords.map(function (c) { return c[0].toFixed(1) + ',' + c[1].toFixed(1); }).join(' ');
        var area = '0,' + height + ' ' + line + ' ' + width + ',' + height;
        var gid = 'stg-spark-' + String(diskId).replace(/[^a-zA-Z0-9_-]/g, '');
        return '<svg class="stg-spark" viewBox="0 0 ' + width + ' ' + height + '" preserveAspectRatio="none" role="img" aria-label="' + esc(t('usage_history')) + '">' +
            '<defs><linearGradient id="' + gid + '" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="currentColor" stop-opacity=".35"/><stop offset="100%" stop-color="currentColor" stop-opacity="0"/></linearGradient></defs>' +
            '<polygon points="' + area + '" fill="url(#' + gid + ')"></polygon>' +
            '<polyline points="' + line + '" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"></polyline>' +
            '</svg>';
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

    function statusLabel(status) {
        var value = String(status || 'unknown');
        var key = 'status_' + value;
        var label = t(key);
        return label === key ? value.replace(/_/g, ' ') : label;
    }

    function badge(status) {
        var value = String(status || 'unknown');
        return '<span class="stg-badge ' + esc(value) + '">' + esc(statusLabel(value)) + '</span>';
    }

    function setText(selector, value) {
        var node = el(selector);
        if (node) node.textContent = value;
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
            showAlert(t('load_failed', { error: err.message }), 'error');
            var pill = el('#stg-pill');
            if (pill) { pill.className = 'stg-pill err'; el('#stg-pill-text').textContent = t('not_mounted'); }
        });
    }

    function renderAll() {
        renderSummary();
        renderDisks();
        renderMigrations();
        renderIndex();
        renderEvents();
        renderSettings();
    }

    function fmtTime(ts) {
        var n = Number(ts) || 0;
        if (!n) return t('index_never');
        try { return new Date(n * 1000).toLocaleString(); } catch (e) { return String(n); }
    }

    function renderIndex() {
        var d = state.data || {};
        var idx = d.file_index || {};
        var disks = d.disks || [];
        var files = Number(idx.files) || 0;
        var dirs = Number(idx.directories) || 0;
        setText('#stg-idx-files', files.toLocaleString());
        setText('#stg-idx-bytes', fmtBytes(idx.bytes || 0));
        setText('#stg-idx-directories', dirs.toLocaleString());
        setText('#stg-idx-missing', t('index_missing') + ': ' + (Number(idx.missing) || 0).toLocaleString());
        setText('#stg-idx-users', (Number(idx.users) || 0).toLocaleString());
        setText('#stg-idx-apps', t('indexed_apps') + ': ' + (Number(idx.apps) || 0).toLocaleString());
        setText('#stg-idx-pending', (Number(idx.pending_events) || 0).toLocaleString());
        setText('#stg-idx-failed', t('index_failed_events') + ': ' + (Number(idx.failed_events) || 0).toLocaleString());
        setText('#stg-idx-last', t('index_last_run') + ': ' + fmtTime(idx.last_index_at));
        setText('#stg-idx-reconcile', t('index_last_reconcile') + ': ' + fmtTime(idx.last_reconcile_at));
        setText('#stg-idx-jobs', t('index_active_jobs') + ': ' + (Number(idx.active_jobs) || 0));
        setText('#stg-tab-count-index', (files + dirs) > 0 ? String(Number(idx.active_jobs) || 0) : '0');

        var appsBody = el('#stg-index-apps');
        if (appsBody) {
            var appRows = Array.isArray(idx.by_app) ? idx.by_app : [];
            appsBody.innerHTML = appRows.length ? appRows.map(function (row) {
                return '<tr><td>' + esc(row.app_key) + '</td><td>' + (Number(row.objects) || 0).toLocaleString() + '</td><td>' + fmtBytes(row.bytes || 0) + '</td></tr>';
            }).join('') : '<tr><td colspan="3" class="stg-muted">' + esc(t('no_data')) + '</td></tr>';
        }
        var disksBody = el('#stg-index-disks');
        if (disksBody) {
            var diskRows = Array.isArray(idx.by_disk) ? idx.by_disk : [];
            var names = {};
            disks.forEach(function (disk) { names[String(disk.id)] = disk.name || ('#' + disk.id); });
            disksBody.innerHTML = diskRows.length ? diskRows.map(function (row) {
                var label = names[String(row.disk_id)] || ('#' + row.disk_id);
                return '<tr><td>' + esc(label) + '</td><td>' + (Number(row.objects) || 0).toLocaleString() + '</td><td>' + fmtBytes(row.bytes || 0) + '</td></tr>';
            }).join('') : '<tr><td colspan="3" class="stg-muted">' + esc(t('no_data')) + '</td></tr>';
        }
        var jobsBody = el('#stg-index-jobs');
        if (jobsBody) {
            var jobs = Array.isArray(idx.jobs) ? idx.jobs : [];
            jobsBody.innerHTML = jobs.length ? jobs.map(function (job) {
                return '<tr><td>' + esc(job.id) + '</td><td>' + esc(job.app_key) + '</td><td>' + esc(job.user_id) + '</td><td>' + esc(job.status) + '</td><td>' + (Number(job.scanned_files) || 0).toLocaleString() + '</td><td>' + (Number(job.scanned_directories) || 0).toLocaleString() + '</td><td>' + fmtTime(job.updated_at) + '</td></tr>';
            }).join('') : '<tr><td colspan="7" class="stg-muted">' + esc(t('no_data')) + '</td></tr>';
        }
        setText('#stg-index-generated', idx.generated_at ? fmtTime(idx.generated_at) : '');
    }

    function showTab(name) {
        var valid = ['overview', 'disks', 'migrations', 'file_index', 'backup', 'settings'];
        if (valid.indexOf(name) === -1) name = 'overview';
        state.activeTab = name;
        els('[data-stg-tab]').forEach(function (tab) {
            var active = tab.getAttribute('data-stg-tab') === name;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        els('[data-stg-panel]').forEach(function (panel) {
            var active = panel.getAttribute('data-stg-panel') === name;
            panel.classList.toggle('active', active);
            panel.hidden = !active;
        });
        try { localStorage.setItem('stg.activeTab', name); } catch (e) {}
    }

    function renderSummary() {
        var d = state.data || {};
        var m = d.mergerfs || {};
        var r = d.restic || {};
        var disks = d.disks || [];
        var total = Number(d.total_bytes) || 0;
        var used = Number(d.used_bytes) || 0;
        var free = Number(d.free_bytes) || 0;
        var pct = Math.max(0, Math.min(100, Number(d.percent_used) || 0));

        setText('#stg-total', fmtBytes(total));
        setText('#stg-used', fmtBytes(used));
        setText('#stg-free', fmtBytes(free));
        setText('#stg-kpi-total', fmtBytes(total));
        setText('#stg-kpi-used', fmtBytes(used));
        setText('#stg-kpi-used-sub', t('percent_of_pool', { pct: pct.toFixed(1) }));
        setText('#stg-kpi-free', fmtBytes(free));
        setText('#stg-kpi-disks', (d.online_count || 0) + ' / ' + (d.disk_count || 0));
        setText('#stg-kpi-disks-sub', (d.disk_count || 0) === 1 ? t('disk_registered') : t('disks_registered'));
        setText('#stg-disk-count', t('online_registered', { online: d.online_count || 0, total: d.disk_count || 0 }));

        var bar = el('#stg-used-bar');
        bar.style.width = pct + '%';
        bar.className = pct >= 90 ? 'danger' : (pct >= 75 ? 'warn' : '');
        var ring = el('#stg-ring');
        if (ring) {
            ring.style.setProperty('--pct', String(pct));
            ring.className = 'stg-ring' + (pct >= 90 ? ' danger' : (pct >= 75 ? ' warn' : ''));
        }
        setText('#stg-ring-pct', pct.toFixed(0) + '%');
        setText('#stg-used-pct', t('percent_used', { pct: pct.toFixed(1) }));
        setText('#stg-free-sub', t('pool_prefix', { path: d.storage_root || '/storage' }));

        setText('#stg-mergerfs', m.mounted ? (m.writable ? t('label_online') : t('label_readonly')) : t('not_mounted'));
        setText('#stg-mergerfs-sub', m.mounted
            ? (t('mergerfs_branches', { count: (m.branches || []).length }) + (m.runtime_api ? t('mergerfs_runtime_suffix') : ''))
            : (m.binary_available ? t('waiting_for_disks') : t('mergerfs_missing')));
        setText('#stg-restic', r.configured ? t('label_configured') : t('not_configured'));
        setText('#stg-last-backup', r.last_backup_at ? fmtDate(r.last_backup_at) : t('never'));
        var backupState = el('#stg-backup-state');
        if (backupState) {
            backupState.className = 'stg-badge ' + (r.configured ? 'online' : 'offline');
            backupState.textContent = r.configured ? t('repository_configured') : t('not_configured');
        }
        var backupPath = el('#stg-backup-path');
        if (backupPath) backupPath.textContent = d.storage_root || '/storage';

        renderPoolMap(disks, total);
        renderHealth(m, r, disks);

        var pill = el('#stg-pill');
        var pillText = el('#stg-pill-text');
        if (d.active_migration) {
            pill.className = 'stg-pill warn';
            pillText.textContent = t('migration_word') + ' ' + String(d.active_migration.status || '').replace(/_/g, ' ');
        } else if (!m.mounted) {
            pill.className = 'stg-pill';
            pillText.textContent = (d.disk_count || 0) > 0 ? t('pool_not_mounted') : t('no_disks_short');
        } else if (!m.writable) {
            pill.className = 'stg-pill err';
            pillText.textContent = t('pool_readonly');
        } else {
            pill.className = 'stg-pill ok';
            pillText.textContent = t('pool_online');
        }
        setText('#stg-disks-hint', (d.disks_root || '/disks') + ' · ' + t('auto_scanned'));
        var countDisks = el('#stg-tab-count-disks');
        if (countDisks) countDisks.textContent = String((d.disk_count || 0));
        var countMigrations = el('#stg-tab-count-migrations');
        if (countMigrations) countMigrations.textContent = String((d.migrations || []).length);
    }

    function renderPoolMap(disks, total) {
        var map = el('#stg-pool-map');
        var legend = el('#stg-pool-legend');
        if (!map || !legend) return;
        var active = disks.filter(function (d) { return ['online', 'initializing', 'error'].indexOf(String(d.status)) !== -1 && Number(d.total_bytes) > 0; });
        if (!active.length || total <= 0) {
            map.innerHTML = '<div class="stg-empty">' + esc(t('no_disks_short')) + '</div>';
            legend.innerHTML = '';
            return;
        }
        var colors = ['#3b82f6', '#22c55e', '#f59e0b', '#a855f7', '#06b6d4', '#ef4444'];
        map.innerHTML = '<div class="stg-pool-bar">' + active.map(function (disk, i) {
            var share = Math.max(1, Number(disk.total_bytes) / total * 100);
            return '<span class="stg-pool-seg" style="width:' + share.toFixed(2) + '%;background:' + colors[i % colors.length] + '" title="' + esc(disk.name) + ' · ' + fmtBytes(disk.total_bytes) + '"></span>';
        }).join('') + '</div>';
        legend.innerHTML = active.map(function (disk, i) {
            return '<div class="stg-legend-item"><span class="stg-legend-dot" style="background:' + colors[i % colors.length] + '"></span><span class="stg-legend-name">' + esc(disk.name) + '</span><strong>' + fmtBytes(disk.total_bytes) + '</strong><small>' + esc(t('legend_used', { pct: (Number(disk.percent_used) || 0).toFixed(0) })) + '</small></div>';
        }).join('');
    }

    function renderHealth(m, r, disks) {
        var list = el('#stg-health-list');
        var score = el('#stg-health-score');
        if (!list) return;
        var online = disks.filter(function (d) { return String(d.status) === 'online'; }).length;
        var issues = [];
        if (!m.mounted) issues.push(t('pool_not_mounted'));
        if (m.mounted && !m.writable) issues.push(t('pool_readonly'));
        if (!m.fuse_device) issues.push(t('fuse_missing'));
        if (!r.configured) issues.push(t('repository_not_configured'));
        if (online < disks.length) issues.push(t('online_total', { online: online, total: disks.length }));
        if (score) {
            score.className = 'stg-badge ' + (issues.length === 0 ? 'online' : (online > 0 ? 'initializing' : 'error'));
            score.textContent = issues.length === 0 ? t('healthy') : t('checks', { count: issues.length });
        }
        var rows = [
            { icon: 'server', title: t('health_pool'), value: m.mounted ? (m.writable ? t('mounted_writable') : t('mounted_readonly')) : t('not_mounted'), ok: !!(m.mounted && m.writable), detail: t('branch_count', { count: (m.branches || []).length }) },
            { icon: 'shield', title: t('health_fuse'), value: m.fuse_device ? t('fuse_available') : t('fuse_missing'), ok: !!m.fuse_device, detail: m.runtime_api ? t('runtime_api') : t('runtime_unavailable') },
            { icon: 'disk', title: t('health_disks'), value: t('online_total', { online: online, total: disks.length }), ok: online > 0 && online === disks.length, detail: (state.data && state.data.disks_root) || '/disks' },
            { icon: 'check', title: t('health_restic'), value: r.configured ? t('repository_configured') : t('not_configured'), ok: !!r.configured, detail: r.last_backup_at ? t('last_prefix', { time: fmtDate(r.last_backup_at) }) : t('no_snapshot_yet') }
        ];
        list.innerHTML = rows.map(function (row) {
            return '<div class="stg-health-row">' +
                '<span class="stg-health-icon ' + (row.ok ? 'ok' : 'warn') + '">' + icon(row.ok ? 'check' : 'alert', 15) + '</span>' +
                '<div class="stg-health-copy"><strong>' + esc(row.title) + '</strong><small>' + esc(row.detail) + '</small></div>' +
                '<span class="stg-health-value">' + esc(row.value) + '</span></div>';
        }).join('');
        var system = el('#stg-system-status');
        if (system) system.innerHTML = list.innerHTML;
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
                actions = '<span class="stg-muted">' + esc(t('add_another_disk_text')) + '</span>';
            } else {
                actions += '<button type="button" class="stg-btn stg-btn-sm" data-stg-action="disk_check" data-stg-disk="' + esc(disk.id) + '" aria-label="' + esc(t('check_disk_btn')) + '">' + icon('scan', 13) + '<span>' + esc(t('check_disk_btn')) + '</span></button>';
                actions += '<button type="button" class="stg-btn stg-btn-sm stg-btn-danger" data-stg-action="remove_disk" data-stg-disk="' + esc(disk.id) + '" aria-label="' + esc(t('safely_remove')) + '" ' + (canRemove ? '' : ' disabled') + '>' + icon('trash', 13) + '<span>' + esc(t('safely_remove')) + '</span></button>';
            }
            var total = Number(disk.total_bytes) || 0;
            var used = Number(disk.used_bytes) || 0;
            var free = Number(disk.free_bytes) || 0;
            var history = (state.data && state.data.usage_history && state.data.usage_history[disk.id]) || [];
            return '<article class="stg-disk-card' + (status === 'ready_to_remove' ? ' stg-disk-ready' : '') + '">' +
                '<div class="stg-disk-card-head">' +
                '<span class="stg-disk-icon">' + icon('disk', 22) + '</span>' +
                '<div class="stg-disk-ident"><div class="stg-disk-name">' + esc(disk.name) + '</div><div class="stg-disk-path">' + esc(disk.mount_path) + '</div></div>' +
                badge(status) + '</div>' +
                '<div class="stg-disk-usage"><div class="stg-disk-usage-line"><strong>' + fmtBytes(used) + '</strong><span>/ ' + fmtBytes(total) + '</span><span class="stg-disk-free">' + fmtBytes(free) + ' ' + esc(t('free')) + '</span></div><div class="stg-bar"><span class="' + barClass + '" style="width:' + pct + '%"></span></div><div class="stg-disk-pct">' + esc(t('percent_used', { pct: pct.toFixed(1) })) + '</div></div>' +
                '<div class="stg-disk-history"><div class="stg-disk-history-head"><span>' + esc(t('usage_history')) + '</span><span>' + esc(t('usage_hours')) + '</span></div>' + sparkline(history, disk.id) + '</div>' +
                '<div class="stg-disk-facts">' +
                '<div><span>' + esc(t('filesystem')) + '</span><strong>' + esc(disk.filesystem || '—') + '</strong></div>' +
                '<div><span>' + esc(t('identity')) + '</span><strong class="stg-uuid" title="' + esc(disk.uuid) + '">' + esc(disk.uuid) + '</strong></div>' +
                '<div><span>' + esc(t('last_check')) + '</span><strong>' + fmtDate(disk.last_health_check_at) + '</strong></div>' +
                '<div><span>' + esc(t('last_backup')) + '</span><strong>' + fmtDate(disk.last_backup_at) + '</strong></div>' +
                '</div>' +
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
            el('#stg-active-state').textContent = diskName(active.source_disk_id) + ' · ' + String(active.status || '').replace(/_/g, ' ');
            updateProgress(active);
        }

        var rows = (state.data && state.data.migrations) || [];
        var body = el('#stg-history-body');
        var chart = el('#stg-chart-bars');
        if (chart) {
            var recent = rows.slice(0, 8).reverse();
            if (!recent.length) {
                chart.innerHTML = '<div class="stg-empty">' + esc(t('no_migrations')) + '</div>';
            } else {
                chart.innerHTML = recent.map(function (m) {
                    var pct = Math.max(0, Math.min(100, Number(m.progress) || 0));
                    var cls = m.status === 'completed' ? 'ok' : (m.status === 'failed' ? 'err' : 'warn');
                    return '<div class="stg-chart-row"><span class="stg-chart-label">' + esc(diskName(m.source_disk_id)) + '</span><div class="stg-chart-track"><span class="' + cls + '" style="width:' + pct.toFixed(1) + '%"></span></div><span class="stg-chart-value">' + pct.toFixed(0) + '%</span></div>';
                }).join('');
            }
        }
        var hint = el('#stg-history-hint');
        if (hint) hint.textContent = rows.length ? t('operations_count', { count: rows.length }) : '';
        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="8"><div class="stg-empty">' + esc(t('no_migrations')) + '</div></td></tr>';
            return;
        }
        body.innerHTML = rows.map(function (m) {
            var pct = Math.max(0, Math.min(100, Number(m.progress) || 0));
            var err = m.error ? '<span class="stg-conflict">' + esc(m.error) + '</span>' : '—';
            var actions = '<button type="button" class="stg-btn stg-btn-sm" data-stg-action="progress_details" data-stg-migration="' + esc(m.id) + '">' + esc(t('details')) + '</button>';
            if (m.status === 'paused' || m.status === 'failed') {
                actions += ' <button type="button" class="stg-btn stg-btn-sm" data-stg-action="migration_resume" data-stg-migration="' + esc(m.id) + '">' + esc(t('resume')) + '</button>';
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
        el('#stg-migration-eta').textContent = remaining > 0 ? (t('eta') + ' ' + fmtDuration(remaining)) : '';
        el('#stg-migration-errors').textContent = Number(m.error_count) > 0 ? t('errors', { count: m.error_count }) : '';
        el('#stg-migration-current').textContent = m.current_file ? t('current_file', { file: m.current_file }) : (m.error ? m.error : '');
    }

    function renderEvents() {
        var box = el('#stg-events');
        var events = (state.data && state.data.events) || [];
        if (!events.length) {
            box.innerHTML = '<div class="stg-empty">' + esc(t('no_events')) + '</div>';
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
        el('#stg-max-parallel').value = s.max_copy_parallel == null ? 1 : s.max_copy_parallel;
        el('#stg-backup-required').checked = !!s.backup_required;
        el('#stg-delete-source').checked = !!s.delete_source_after_migration;
        var policy = el('#stg-placement-policy');
        if (policy) policy.value = s.placement_policy || 'mfs';
        var select = el('#stg-backup-profile');
        var profiles = (state.data && state.data.backup_profiles) || [];
        var current = s.backup_profile || '';
        select.innerHTML = '<option value="">' + esc(t('not_configured')) + '</option>' + profiles.map(function (p) {
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
        el('#stg-remove-body').innerHTML = '<div class="stg-empty">' + esc(t('checking_space')) + '</div>';
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
            return '<div class="stg-target"><span>' + esc(t.name) + '</span><span>' + esc(t('usable_of', { usable: fmtBytes(t.usable_bytes), free: fmtBytes(t.free_bytes) })) + '</span></div>';
        }).join('') || '<div class="stg-empty">' + esc(t('no_target_disks')) + '</div>';
        var conflictHtml = '';
        if (conflicts > 0) {
            conflictHtml = '<div><div class="stg-warn-text">' + esc(t('conflicting_files', { count: conflicts })) + '</div><ul class="stg-list">' +
                (p.conflicts || []).slice(0, 12).map(function (c) {
                    return '<li><span class="stg-conflict">' + esc(c.rel_path) + '</span> ' + esc(t('conflict_on', { target: c.target })) + '</li>';
                }).join('') +
                '</ul><div class="stg-note">' + esc(t('conflicts_hint')) + '</div></div>';
        }
        var html =
            '<div class="stg-check-grid">' +
            '<div class="stg-check-box"><span>' + esc(t('disk')) + '</span><strong>' + esc(disk.name || '') + '</strong></div>' +
            '<div class="stg-check-box"><span>' + esc(t('data_move')) + '</span><strong>' + fmtBytes(p.source_bytes) + '</strong></div>' +
            '<div class="stg-check-box"><span>' + esc(t('files')) + '</span><strong>' + (p.source_files || 0) + '</strong></div>' +
            '<div class="stg-check-box"><span>' + esc(t('required_headroom')) + '</span><strong>' + fmtBytes(p.required_bytes) + '</strong></div>' +
            '<div class="stg-check-box"><span>' + esc(t('free_on_targets')) + '</span><strong>' + fmtBytes(p.total_usable_bytes) + '</strong></div>' +
            '<div class="stg-check-box"><span>' + esc(t('additional_space')) + '</span><strong class="' + (enough ? 'stg-ok-text' : 'stg-warn-text') + '">' + fmtBytes(p.shortfall_bytes) + '</strong></div>' +
            '</div>' +
            '<div><div class="stg-muted">' + esc(t('target_disks')) + '</div>' + targets + '</div>' +
            (enough ? '<div class="stg-ok-text">' + esc(t('enough_space')) + '</div>' : '<div class="stg-warn-text">' + esc(t('not_enough_space')) + '</div>') +
            conflictHtml +
            '<div class="stg-note">' + esc(t('removal_note')) + '</div>';
        el('#stg-remove-body').innerHTML = html;
        el('#stg-remove-confirm').disabled = !(p.can_start && enough && conflicts === 0);
    }

    function startMigration() {
        if (!state.removeDiskId) return;
        var check = state.removeCheck;
        if (!check || !check.can_start) return;
        var msg = t('confirm_remove', {
            size: fmtBytes(check.source_bytes),
            disk: (check.disk ? check.disk.name : t('disk')),
            count: check.targets.length
        });
        if (!window.confirm(msg)) return;
        el('#stg-remove-confirm').disabled = true;
        api('migration_start', { method: 'POST', body: { id: state.removeDiskId, confirm: true } })
            .then(function () {
                el('#stg-remove-overlay').hidden = true;
                showAlert(t('migration_started'), 'ok');
                showTab('migrations');
                return refresh(true);
            })
            .catch(function (err) {
                showAlert(t('migration_start_failed', { error: err.message }), 'error');
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
            '<div class="stg-check-box"><span>' + esc(t('status')) + '</span><strong>' + badge(m.status) + '</strong></div>' +
            '<div class="stg-check-box"><span>' + esc(t('progress')) + '</span><strong>' + pct.toFixed(1) + '%</strong></div>' +
            '<div class="stg-check-box"><span>' + esc(t('data')) + '</span><strong>' + fmtBytes(m.migrated_bytes) + ' / ' + fmtBytes(m.total_bytes) + '</strong></div>' +
            '<div class="stg-check-box"><span>' + esc(t('files')) + '</span><strong>' + (m.migrated_files || 0) + ' / ' + (m.total_files || 0) + '</strong></div>' +
            '<div class="stg-check-box"><span>' + esc(t('speed')) + '</span><strong>' + (Number(m.speed_bytes) > 0 ? fmtBytes(m.speed_bytes) + '/s' : '—') + '</strong></div>' +
            '<div class="stg-check-box"><span>' + esc(t('error')) + '</span><strong>' + (m.error_count || 0) + '</strong></div>' +
            '</div>' +
            '<div class="stg-progress"><span style="width:' + pct + '%"></span></div>' +
            '<div class="stg-current">' + esc(m.current_file || '') + '</div>' +
            (m.error ? '<div class="stg-alert error">' + esc(m.error) + '</div>' : '') +
            '<div class="stg-muted">' + esc(t('files_breakdown', { list: Object.keys(counts).map(function (k) { return esc(k) + ' ' + counts[k]; }).join(' · ') })) + '</div>' +
            (events.length ? '<div class="stg-events">' + events.slice(0, 12).map(function (e) {
                return '<div class="stg-event"><div class="stg-event-time">' + fmtDate(e.created_at) + '</div><div><div class="stg-event-msg">' + esc(e.message) + '</div></div></div>';
            }).join('') + '</div>' : '');
        el('#stg-progress-body').innerHTML = html;
    }

    function openProgressDialog(migrationId) {
        var active = migrationId ? { id: migrationId } : (state.data && state.data.active_migration);
        if (!active) {
            var latest = state.data && state.data.migrations && state.data.migrations[0];
            if (!latest) { showAlert(t('no_migration'), 'warn'); return; }
            active = latest;
        }
        el('#stg-progress-overlay').hidden = false;
        el('#stg-progress-body').innerHTML = '<div class="stg-empty">' + esc(t('loading')) + '</div>';
        api('migration_status?id=' + encodeURIComponent(active.id)).then(renderProgressDialog).catch(function (err) {
            el('#stg-progress-body').innerHTML = '<div class="stg-alert error">' + esc(err.message) + '</div>';
        });
    }

    function saveSettings() {
        var body = {
            reserve_percent: Number(el('#stg-reserve').value),
            min_free_gb: Number(el('#stg-minfree').value),
            watch_interval_seconds: Number(el('#stg-interval').value),
            max_copy_parallel: Number(el('#stg-max-parallel').value),
            placement_policy: el('#stg-placement-policy').value,
            backup_required: el('#stg-backup-required').checked,
            delete_source_after_migration: el('#stg-delete-source').checked,
            backup_profile: el('#stg-backup-profile').value
        };
        api('settings_save', { method: 'POST', body: body }).then(function () {
            showAlert(t('settings_saved'), 'ok');
            return refresh(true);
        }).catch(function (err) { showAlert(t('settings_failed', { error: err.message }), 'error'); });
    }

    function attachBackup() {
        var profile = el('#stg-backup-profile').value;
        if (!profile) { showAlert(t('select_profile'), 'warn'); return; }
        api('backup_attach', { method: 'POST', body: { profile: profile } }).then(function () {
            showAlert(t('profile_added'), 'ok');
            return refresh(true);
        }).catch(function (err) { showAlert(t('profile_failed', { error: err.message }), 'error'); });
    }

    function backupNow() {
        api('backup_now', { method: 'POST', body: {} }).then(function (res) {
            showAlert(t('backup_queued', { job: (res.job && res.job.id) || '' }), 'ok');
        }).catch(function (err) { showAlert(t('backup_failed', { error: err.message }), 'error'); });
    }

    function runDiskCheck(diskId) {
        showAlert(t('checking_disk'));
        api('disk_check', { method: 'POST', body: { id: diskId } }).then(function (res) {
            var report = (res.result && res.result.report) || {};
            var errors = (report.errors || []).join(', ');
            showAlert(t('disk_check_report', { detail: errors || (res.result.status || 'ok') }), errors ? 'warn' : 'ok');
            return refresh(true);
        }).catch(function (err) { showAlert(t('disk_check_failed', { error: err.message }), 'error'); });
    }

    function cancelMigration() {
        var active = state.data && state.data.active_migration;
        if (!active) return;
        if (!window.confirm(t('cancel_confirm'))) return;
        api('migration_cancel', { method: 'POST', body: { id: active.id } }).then(function () {
            showAlert(t('cancellation_requested'), 'warn');
            return refresh(true);
        }).catch(function (err) { showAlert(t('cancel_failed', { error: err.message }), 'error'); });
    }

    function onClick(event) {
        var tip = event.target.closest('.stg-tip');
        if (tip && state.root.contains(tip)) {
            event.preventDefault();
            var open = tip.classList.contains('is-open');
            els('.stg-tip.is-open').forEach(function (node) { node.classList.remove('is-open'); });
            if (!open) tip.classList.add('is-open');
            return;
        }
        var tab = event.target.closest('[data-stg-tab]');
        if (tab && state.root.contains(tab)) {
            showTab(tab.getAttribute('data-stg-tab'));
            return;
        }
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
            showAlert(t('scanning_disks'));
            api('scan').then(function () { showAlert(t('scan_completed'), 'ok'); return refresh(true); })
                .catch(function (err) { showAlert(t('scan_failed', { error: err.message }), 'error'); });
            return;
        }
        if (action === 'disk_check') { runDiskCheck(diskId); return; }
        if (action === 'remove_disk') { openRemoveDialog(diskId); return; }
        if (action === 'migration_start') { startMigration(); return; }
        if (action === 'migration_cancel') { cancelMigration(); return; }
        if (action === 'progress_details') { openProgressDialog(migrationId); return; }
        if (action === 'migration_resume') {
            if (!window.confirm(t('resume_confirm'))) return;
            api('migration_resume', { method: 'POST', body: { id: migrationId } })
                .then(function () { showAlert(t('migration_resumed'), 'ok'); return refresh(true); })
                .catch(function (err) { showAlert(t('resume_failed', { error: err.message }), 'error'); });
            return;
        }
        if (action === 'index_start') {
            api('index_start', { method: 'POST', body: {} })
                .then(function () { showAlert(t('index_started'), 'ok'); return refresh(true); })
                .catch(function (err) { showAlert(t('index_start_failed', { error: err.message }), 'error'); });
            return;
        }
        if (action === 'index_reconcile') {
            api('index_reconcile', { method: 'POST', body: {} })
                .then(function () { showAlert(t('index_started'), 'ok'); return refresh(true); })
                .catch(function (err) { showAlert(t('index_start_failed', { error: err.message }), 'error'); });
            return;
        }
        if (action === 'save_settings') { saveSettings(); return; }
        if (action === 'backup_attach') { attachBackup(); return; }
        if (action === 'backup_now') { backupNow(); return; }
    }

    function init(root) {
        root = root && root.querySelector ? (root.id === 'stg-app' ? root : (root.querySelector('#stg-app') || root)) : document.getElementById('stg-app');
        if (!root) return;
        try { state.i18n = JSON.parse(root.getAttribute('data-stg-i18n') || '{}') || {}; } catch (e) { state.i18n = {}; }
        if (root.dataset.stgReady === '1') { state.root = root; refresh(true); return; }
        root.dataset.stgReady = '1';
        state.root = root;
        state.base = root.getAttribute('data-stg-base') || '/project/storage/index.php';
        state.csrf = root.getAttribute('data-stg-csrf') || '';
        var saved = 'overview';
        try { saved = localStorage.getItem('stg.activeTab') || 'overview'; } catch (e) {}
        showTab(saved);
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
        init(root);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoInit);
    } else {
        autoInit();
    }
})();
