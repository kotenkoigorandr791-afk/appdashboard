/**
 * Backup Manager — panel behaviour (list of backups, wizard, snapshots, jobs).
 * Scoped to #bdp-app so it can live inside the Admin Panel or standalone page.
 */
(function () {
    'use strict';

    var BASE = (document.getElementById('bdp-app') && document.getElementById('bdp-app').dataset.bdpBase) || '/project/backup/index.php';

    var state = {
        root: null,
        config: null,
        backups: [],
        activeRepo: '',
        snapshots: [],
        jobs: [],
        schedule: {},
        repoOk: false,
        nextRun: 0,
        hostRoot: '/',
        loadSeq: 0,
        pollTimer: null,
        logTimer: null,
        browsePath: '',
        browseParent: '',
        trees: {
            dest: { path: '', parent: '', filter: '' },
            src: { path: '', parent: '', filter: '' }
        },
        wizard: { open: false, step: 0, editing: null }
    };

    function ready(fn) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn, { once: true });
        else fn();
    }

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function el(scope, selector) {
        return (scope || document).querySelector(selector);
    }

    function csrfToken() {
        try {
            if (typeof window.apCsrfToken === 'function') return window.apCsrfToken();
        } catch (e) {}
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? (meta.getAttribute('content') || '') : '';
    }

    function t(key, vars) {
        var dict = (state.root && state.root._bdpI18n) || {};
        var str = dict[key] || key;
        if (vars) {
            Object.keys(vars).forEach(function (k) {
                str = str.replace(new RegExp('\\{' + k + '\\}', 'g'), String(vars[k]));
            });
        }
        return str;
    }

    function api(action, opts) {
        opts = opts || {};
        var url = new URL(BASE, window.location.origin);
        url.searchParams.set('api', action);
        if (opts.query) {
            Object.keys(opts.query).forEach(function (k) {
                var v = opts.query[k];
                if (v !== undefined && v !== null) url.searchParams.set(k, String(v));
            });
        }
        var headers = {};
        var token = csrfToken();
        if (token) headers['X-CSRF-TOKEN'] = token;
        var init = { method: opts.method || 'GET', credentials: 'same-origin', headers: headers, cache: 'no-store' };
        if (opts.body !== undefined && opts.body !== null) {
            headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(opts.body);
        }
        return fetch(url.toString(), init).then(function (res) {
            return res.text().then(function (text) {
                var data = null;
                try { data = text ? JSON.parse(text) : {}; }
                catch (e) { throw new Error(text ? text.substring(0, 200) : 'Invalid server response'); }
                if (!res.ok || (data && data.ok === false)) {
                    throw new Error((data && (data.error || data.message)) || ('HTTP ' + res.status));
                }
                return data || {};
            });
        });
    }

    function fmtSize(bytes) {
        var num = Number(bytes) || 0;
        var units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
        var i = 0;
        while (num >= 1024 && i < units.length - 1) { num /= 1024; i++; }
        return (i === 0 ? num : num.toFixed(2)).toString().replace(/\.00$/, '') + ' ' + units[i];
    }

    function fmtDate(value) {
        if (!value) return '—';
        var ts = typeof value === 'number' ? value * 1000 : Date.parse(value);
        if (!isFinite(ts) || ts <= 0) return '—';
        try {
            return new Date(ts).toLocaleString(undefined, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
        } catch (e) {
            return new Date(ts).toISOString();
        }
    }

    function fmtDuration(seconds) {
        seconds = Math.max(0, Math.round(Number(seconds) || 0));
        if (seconds < 60) return seconds + 's';
        var m = Math.floor(seconds / 60);
        var s = seconds % 60;
        if (m < 60) return m + 'm ' + s + 's';
        return Math.floor(m / 60) + 'h ' + (m % 60) + 'm';
    }

    function setAlert(kind, message) {
        var node = el(state.root, '#bdp-alert');
        if (!node) return;
        node.className = 'bdp-alert' + (kind ? ' ' + kind : '');
        node.textContent = message || '';
        node.hidden = !message;
    }

    /* ------------------------------------------------------------------ *
     * Repositories (one per backup)
     * ------------------------------------------------------------------ */

    function profileById(id) {
        if (!id) return null;
        return (state.backups || []).filter(function (p) { return p.id === id; })[0] || null;
    }

    function activeRepoProfile() {
        return profileById(state.activeRepo);
    }

    function activeQuery() {
        return state.activeRepo ? { profile: state.activeRepo } : {};
    }

    function renderRepoSelectors() {
        var list = state.backups || [];
        var value = state.activeRepo;
        if (!profileById(value)) value = list.length ? (list[0].id || '') : '';
        state.activeRepo = value;
        state.hostRoot = (state.config && state.config.host_root) || state.hostRoot;
    }

    function setActiveRepo(id) {
        if (!profileById(id)) id = ((state.backups || [])[0] || {}).id || '';
        state.activeRepo = id || '';
        state.repoOk = false;
        state.snapshots = [];
        renderRepoSelectors();
        renderSnapshots();
        updateStatus();
        loadSnapshots();
        loadStats();
        loadDisk();
    }

    function loadSnapshots() {
        if (!state.activeRepo) {
            state.snapshots = [];
            state.repoOk = false;
            renderSnapshots();
            updateStatus();
            return Promise.resolve();
        }
        return api('snapshots', { query: activeQuery() }).then(function (data) {
            state.snapshots = data.snapshots || [];
            state.repoOk = true;
            renderSnapshots();
            updateStatus();
        }).catch(function (err) {
            state.snapshots = [];
            state.repoOk = false;
            renderSnapshots();
            updateStatus();
            setAlert('warn', err.message);
        });
    }

    function loadStats() {
        if (!state.activeRepo) { renderStats(null); return Promise.resolve(); }
        return api('stats', { query: activeQuery() }).then(function (data) {
            renderStats(data.stats);
        }).catch(function () { renderStats(null); });
    }

    /* ------------------------------------------------------------------ *
     * Backups list
     * ------------------------------------------------------------------ */

    function profileJobs(profileId) {
        return (state.jobs || []).filter(function (j) {
            return (j.type === 'backup') && ((j.params || {}).profile || '') === profileId;
        });
    }

    function profileStatus(profileId) {
        var jobs = profileJobs(profileId).slice().sort(function (a, b) {
            return (b.created_at || 0) - (a.created_at || 0);
        });
        return jobs[0] || null;
    }

    function scheduleSummary(schedule) {
        if (!schedule || !schedule.enabled) return t('manual');
        var freq = schedule.frequency || 'daily';
        var label = t('freq_' + freq);
        if (freq === 'hourly') label = t('freq_hourly') + ' ' + (schedule.interval_hours || 6);
        var out = t('scheduled') + ' · ' + label + ' · ' + (schedule.time || '03:00');
        if (freq === 'weekly') out += ' (' + t('weekday') + ' ' + (schedule.weekday || 1) + ')';
        if (freq === 'monthly') out += ' (' + t('monthday') + ' ' + (schedule.monthday || 1) + ')';
        return out;
    }

    function retentionSummary(retention) {
        if (!retention) return t('keep_all');
        if (retention.keep_within) {
            return t('delete_older') + ' ' + retention.keep_within.replace(/^(\d+)([dmy])$/, '$1 $2');
        }
        var parts = ['last', 'hourly', 'daily', 'weekly', 'monthly', 'yearly'].map(function (k) {
            var n = Number(retention['keep_' + k]) || 0;
            return n > 0 ? (k + ':' + n) : '';
        }).filter(Boolean);
        return parts.length ? parts.join(', ') : t('keep_all');
    }

    function profileNextRun(profile) {
        var map = (state.schedule || {}).profiles || {};
        var ts = Number(map[profile.id]) || 0;
        return ts > 0 ? ts : 0;
    }

    function renderBackups() {
        var root = state.root;
        var body = el(root, '#bdp-backups-body');
        var empty = el(root, '#bdp-backups-empty');
        if (!body) return;
        var list = state.backups || [];
        if (!list.length) {
            body.innerHTML = '<tr><td colspan="7"><div class="bdp-empty">' + esc(t('no_backups')) + '</div></td></tr>';
            if (empty) empty.hidden = false;
            return;
        }
        if (empty) empty.hidden = true;
        body.innerHTML = list.map(function (p) {
            var sources = (p.sources || []).slice(0, 2).map(function (s) {
                return '<span class="bdp-mono">' + esc(s) + '</span>';
            }).join('<br>');
            var more = (p.sources || []).length > 2
                ? '<div class="bdp-muted">+' + ((p.sources || []).length - 2) + '</div>' : '';
            var job = profileStatus(p.id || '');
            var status = '<span class="bdp-badge queued">—</span>';
            var lastRun = t('never_run');
            if (job) {
                status = '<span class="bdp-badge ' + esc(job.status || 'queued') + '">' + esc(t('status_' + (job.status || 'queued'))) + '</span>';
                if (job.finished_at) lastRun = fmtDate(job.finished_at);
            }
            var nextRun = p.enabled ? (profileNextRun(p) ? fmtDate(profileNextRun(p)) : '—') : t('manual');
            return '<tr data-bdp-profile="' + esc(p.id) + '">'
                + '<td data-label="' + esc(t('name')) + '"><strong>' + esc(p.name || '') + '</strong>'
                + (p.description ? '<div class="bdp-muted">' + esc(p.description) + '</div>' : '')
                + (p.enabled ? '' : '<div class="bdp-muted">' + esc(t('manual')) + '</div>') + '</td>'
                + '<td class="bdp-paths" data-label="' + esc(t('sources')) + '">' + (sources || '<span class="bdp-muted">—</span>') + more + '</td>'
                + '<td data-label="' + esc(t('schedule')) + '">' + esc(scheduleSummary(p.schedule)) + '<div class="bdp-muted">' + esc(retentionSummary(p.retention)) + '</div></td>'
                + '<td data-label="' + esc(t('job_status')) + '">' + status + '</td>'
                + '<td class="bdp-muted" data-label="' + esc(t('last_run')) + '">' + esc(lastRun) + '</td>'
                + '<td class="bdp-muted" data-label="' + esc(t('next_run')) + '">' + esc(nextRun) + '</td>'
                + '<td data-label="' + esc(t('actions')) + '"><div class="bdp-actions">'
                + '<button type="button" class="bdp-btn bdp-btn-sm bdp-btn-primary" data-bdp-action="run_backup" data-profile="' + esc(p.id) + '">' + esc(t('run_now')) + '</button>'
                + '<button type="button" class="bdp-btn bdp-btn-sm" data-bdp-action="edit_backup" data-profile="' + esc(p.id) + '">' + esc(t('edit')) + '</button>'
                + '<button type="button" class="bdp-btn bdp-btn-sm bdp-btn-danger" data-bdp-action="delete_backup" data-profile="' + esc(p.id) + '">' + esc(t('delete')) + '</button>'
                + '</div></td>'
                + '</tr>';
        }).join('');
    }

    function runBackup(profileId) {
        startJob(api('backup', { method: 'POST', body: { profile: profileId } }));
    }

    /* ------------------------------------------------------------------ *
     * Snapshots
     * ------------------------------------------------------------------ */

    function renderSnapshots() {
        var root = state.root;
        var body = el(root, '#bdp-snapshots-body');
        var meta = el(root, '#bdp-snapshot-meta');
        if (!body) return;
        if (meta) meta.textContent = (state.snapshots || []).length + ' ' + t('snapshot_count');
        if (!(state.snapshots || []).length) {
            body.innerHTML = '<tr><td colspan="6"><div class="bdp-empty">' + esc(t('no_snapshots')) + '</div></td></tr>';
            return;
        }
        body.innerHTML = state.snapshots.map(function (snap) {
            var paths = (snap.paths || []).map(function (p) { return esc(p); }).join('<br>');
            var tags = (snap.tags || []).map(function (tg) { return '<span class="bdp-badge">' + esc(tg) + '</span>'; }).join(' ');
            return '<tr>'
                + '<td class="bdp-mono" data-label="' + esc(t('snapshot_id')) + '" title="' + esc(snap.id) + '">' + esc(snap.short_id || snap.id || '') + '<div>' + tags + '</div></td>'
                + '<td data-label="' + esc(t('snapshot_time')) + '">' + esc(fmtDate(snap.time)) + '</td>'
                + '<td data-label="' + esc(t('snapshot_host')) + '">' + esc(snap.hostname || '') + '</td>'
                + '<td class="bdp-paths" data-label="' + esc(t('snapshot_paths')) + '">' + paths + '</td>'
                + '<td data-label="' + esc(t('snapshot_size')) + '">' + esc(fmtSize(snap.size)) + '<div class="bdp-muted">' + (snap.files || 0) + ' ' + esc(t('files_done')) + '</div></td>'
                + '<td data-label="' + esc(t('actions')) + '"><div class="bdp-actions">'
                + '<button type="button" class="bdp-btn bdp-btn-sm" data-snap-browse="' + esc(snap.id) + '">' + esc(t('browse')) + '</button>'
                + '<button type="button" class="bdp-btn bdp-btn-sm" data-snap-restore="' + esc(snap.id) + '">' + esc(t('restore')) + '</button>'
                + '<button type="button" class="bdp-btn bdp-btn-sm bdp-btn-danger" data-snap-delete="' + esc(snap.id) + '">' + esc(t('delete_snapshot')) + '</button>'
                + '</div></td>'
                + '</tr>';
        }).join('');
    }

    /* ------------------------------------------------------------------ *
     * Jobs
     * ------------------------------------------------------------------ */

    function renderJobs() {
        var root = state.root;
        var body = el(root, '#bdp-jobs-body');
        var meta = el(root, '#bdp-job-meta');
        if (!body) return;
        var jobs = state.jobs || [];
        if (meta) meta.textContent = jobs.length ? (jobs.length + ' ' + t('total')) : '';
        if (!jobs.length) {
            body.innerHTML = '<tr><td colspan="6"><div class="bdp-empty">' + esc(t('no_jobs')) + '</div></td></tr>';
            return;
        }
        body.innerHTML = jobs.map(function (job) {
            var duration = '—';
            if (job.started_at && job.finished_at) duration = fmtDuration(job.finished_at - job.started_at);
            else if (job.status === 'running' && job.started_at) duration = fmtDuration(Date.now() / 1000 - job.started_at);
            var pct = Math.max(0, Math.min(100, Number((job.progress || {}).percent) || 0));
            var active = job.status === 'running' || job.status === 'queued';
            var label = t('type_' + job.type) !== ('type_' + job.type) ? t('type_' + job.type) : job.type;
            var profileName = (job.params || {}).profile_name;
            return '<tr>'
                + '<td data-label="' + esc(t('job_type')) + '">' + esc(label) + (profileName ? '<div class="bdp-muted">' + esc(profileName) + '</div>' : '') + '</td>'
                + '<td data-label="' + esc(t('job_status')) + '"><span class="bdp-badge ' + esc(job.status) + '">' + esc(t('status_' + job.status)) + '</span></td>'
                + '<td class="bdp-muted" data-label="' + esc(t('job_started')) + '">' + esc(fmtDate(job.started_at || job.created_at)) + '</td>'
                + '<td class="bdp-muted" data-label="' + esc(t('job_duration')) + '">' + esc(duration) + '</td>'
                + '<td data-label="' + esc(t('progress')) + '"><div class="bdp-progress"><span style="width:' + pct + '%"></span></div>'
                + '<div class="bdp-progress-label">' + pct.toFixed(0) + '% · ' + esc(fmtSize((job.progress || {}).bytes_done)) + '</div></td>'
                + '<td data-label="' + esc(t('actions')) + '"><div class="bdp-actions">'
                + '<button type="button" class="bdp-btn bdp-btn-sm" data-job-log="' + esc(job.id) + '">' + esc(t('view_log')) + '</button>'
                + (active ? '<button type="button" class="bdp-btn bdp-btn-sm bdp-btn-danger" data-job-cancel="' + esc(job.id) + '">' + esc(t('cancel')) + '</button>' : '')
                + '</div></td>'
                + '</tr>';
        }).join('');
    }

    function startJob(request) {
        return request.then(function (data) {
            setAlert('ok', data.message || t('job_queued'));
            if (data.job) {
                state.jobs.unshift(data.job);
                renderJobs();
                updateStatus();
                schedulePoll();
            }
            return data;
        }).catch(function (err) {
            setAlert('error', err.message);
            throw err;
        });
    }

    /* ------------------------------------------------------------------ *
     * Status / stats
     * ------------------------------------------------------------------ */

    function updateStatus() {
        var pill = el(state.root, '#bdp-status-pill');
        var text = el(state.root, '#bdp-status-text');
        if (!pill || !text) return;
        pill.classList.remove('ok', 'error', 'running');
        var active = (state.jobs || []).some(function (j) { return j.status === 'running' || j.status === 'queued'; });
        var activeRepo = activeRepoProfile();
        var configured = !!(activeRepo && activeRepo.repository);
        if (active) {
            pill.classList.add('running');
            text.textContent = t('status_running');
        } else if (configured && state.repoOk) {
            pill.classList.add('ok');
            text.textContent = t('repo_ok');
        } else if (configured) {
            text.textContent = t('repository');
        } else {
            pill.classList.add('error');
            text.textContent = t('repo_not_configured');
        }
    }

    function updateDisk(data) {
        var meta = el(state.root, '#bdp-disk-meta');
        if (!meta || !data) return;
        meta.textContent = t('disk_space') + ': ' + fmtSize(data.free) + ' ' + t('free') + ' / ' + fmtSize(data.total);
    }

    function renderStats(stats) {
        var box = el(state.root, '#bdp-stats');
        if (!box) return;
        if (!stats) { box.innerHTML = ''; return; }
        box.innerHTML = [
            ['snapshot_count', stats.snapshots_count],
            ['total_size', fmtSize(stats.total_size)],
            ['total_files', stats.total_file_count]
        ].map(function (pair) {
            return '<div class="bdp-stat"><span>' + esc(t(pair[0])) + '</span><strong>' + esc(String(pair[1] == null ? '—' : pair[1])) + '</strong></div>';
        }).join('');
    }

    /* ------------------------------------------------------------------ *
     * Host folder trees (wizard destination + sources)
     * ------------------------------------------------------------------ */

    function treeContainer(kind) {
        return el(state.root, '[data-bdp-tree="' + kind + '"]');
    }

    function treePathNode(kind) {
        var panel = kind === 'dest' ? el(state.root, '#bdp-wz-dest-tree') : el(state.root, '#bdp-wz-source-tree');
        return panel ? el(panel, '.bdp-tree-path') : null;
    }

    function loadTree(kind) {
        var container = treeContainer(kind);
        if (!container) return Promise.resolve();
        var tree = state.trees[kind];
        container.innerHTML = '<div class="bdp-empty">' + esc(t('loading')) + '</div>';
        return api('host_fs', { query: { path: tree.path || state.hostRoot || '/', onlyFolders: 'true' } })
            .then(function (data) {
                tree.path = data.path || tree.path;
                tree.parent = data.parent || data.path;
                state.hostRoot = data.root || state.hostRoot;
                var pathNode = treePathNode(kind);
                if (pathNode) pathNode.textContent = tree.path;
                var filter = (tree.filter || '').toLowerCase();
                var entries = (data.entries || []).filter(function (entry) {
                    return !filter || entry.name.toLowerCase().indexOf(filter) !== -1;
                });
                if (!entries.length) {
                    container.innerHTML = '<div class="bdp-empty">' + esc(t('no_folders')) + '</div>';
                    return;
                }
                container.innerHTML = entries.map(function (entry) {
                    var right = kind === 'src'
                        ? '<button type="button" class="bdp-btn bdp-btn-sm" data-bdp-add-source="' + esc(entry.path) + '">' + esc(t('add_source')) + '</button>'
                        : '';
                    return '<div class="bdp-browse-item">'
                        + '<span class="bdp-browse-name" data-bdp-enter-kind="' + kind + '" data-bdp-enter="' + esc(entry.path) + '">📁 ' + esc(entry.name) + '</span>'
                        + right + '</div>';
                }).join('');
            })
            .catch(function (err) {
                container.innerHTML = '<div class="bdp-empty">' + esc(err.message || t('error')) + '</div>';
            });
    }

    function treeMkdir(kind) {
        var name = window.prompt(t('new_folder'));
        if (!name) return;
        var tree = state.trees[kind];
        api('host_fs_mkdir', { method: 'POST', body: { parent: tree.path, name: name } })
            .then(function () { loadTree(kind); })
            .catch(function (err) { setAlert('error', err.message); });
    }

    function addSourceLine(path) {
        var area = el(state.root, '#bdp-wz-sources');
        if (!area) return;
        var lines = area.value.split(/\r?\n/).map(function (s) { return s.trim(); }).filter(Boolean);
        if (lines.indexOf(path) === -1) lines.push(path);
        area.value = lines.join('\n');
    }

    /* ------------------------------------------------------------------ *
     * Wizard (step by step)
     * ------------------------------------------------------------------ */

    var WIZARD_STEPS = 5;

    function wizardScheduleFieldsVisibility() {
        var root = state.root;
        var freq = (el(root, '#bdp-wz-frequency') || {}).value || 'daily';
        el(root, '#bdp-wz-weekday-wrap').hidden = freq !== 'weekly';
        el(root, '#bdp-wz-monthday-wrap').hidden = freq !== 'monthly';
        el(root, '#bdp-wz-interval-wrap').hidden = freq !== 'hourly';
        var preview = el(root, '#bdp-wz-schedule-preview');
        if (preview) {
            var enabled = (el(root, '#bdp-wz-schedule-enabled') || {}).checked;
            preview.textContent = enabled
                ? t('schedule_preview', { when: scheduleSummary({
                    enabled: true,
                    frequency: freq,
                    time: (el(root, '#bdp-wz-time') || {}).value || '03:00',
                    weekday: (el(root, '#bdp-wz-weekday') || {}).value || 1,
                    monthday: (el(root, '#bdp-wz-monthday') || {}).value || 1,
                    interval_hours: (el(root, '#bdp-wz-interval') || {}).value || 6
                }) })
                : t('manual');
        }
    }

    function wizardRetentionVisibility() {
        var root = state.root;
        var within = el(root, 'input[name="bdp-wz-retention"][value="within"]');
        var days = el(root, '#bdp-wz-retention-days');
        if (days) days.disabled = !(within && within.checked);
    }

    function wizardRenderSummary() {
        var root = state.root;
        var box = el(root, '#bdp-wizard-summary');
        if (!box) return;
        var rows = [
            [t('name'), (el(root, '#bdp-wz-name') || {}).value || '—'],
            [t('repository'), (el(root, '#bdp-wz-repository') || {}).value || '—'],
            [t('sources'), ((el(root, '#bdp-wz-sources') || {}).value || '').split(/\r?\n/).filter(Boolean).join(', ') || '—'],
            [t('schedule'), scheduleSummary({
                enabled: (el(root, '#bdp-wz-schedule-enabled') || {}).checked,
                frequency: (el(root, '#bdp-wz-frequency') || {}).value,
                time: (el(root, '#bdp-wz-time') || {}).value,
                weekday: (el(root, '#bdp-wz-weekday') || {}).value,
                monthday: (el(root, '#bdp-wz-monthday') || {}).value,
                interval_hours: (el(root, '#bdp-wz-interval') || {}).value
            })]
        ];
        box.innerHTML = rows.map(function (r) {
            return '<div class="bdp-summary-row"><span>' + esc(r[0]) + '</span><strong>' + esc(r[1]) + '</strong></div>';
        }).join('');
    }

    function wizardGoTo(step) {
        var root = state.root;
        step = Math.max(0, Math.min(WIZARD_STEPS - 1, step));
        state.wizard.step = step;
        Array.prototype.forEach.call(root.querySelectorAll('.bdp-wizard-step'), function (node) {
            var idx = Number(node.getAttribute('data-wizard-step'));
            node.classList.toggle('active', idx === step);
            node.classList.toggle('completed', idx < step);
        });
        Array.prototype.forEach.call(root.querySelectorAll('.bdp-wizard-panel'), function (node) {
            node.classList.toggle('active', Number(node.getAttribute('data-wizard-panel')) === step);
        });
        var back = el(root, '#bdp-wz-back');
        var next = el(root, '#bdp-wz-next');
        var submit = el(root, '#bdp-wz-submit');
        if (back) back.hidden = step === 0;
        if (next) next.hidden = step === WIZARD_STEPS - 1;
        if (submit) submit.hidden = step !== WIZARD_STEPS - 1;
        if (step === 1) loadTree('dest');
        if (step === 2) loadTree('src');
        if (step === 3) wizardScheduleFieldsVisibility();
        if (step === 4) wizardRetentionVisibility();
        if (step === 4) wizardRenderSummary();
    }

    function wizardValidate(step) {
        var root = state.root;
        if (step === 0) {
            var ok = !!(el(root, '#bdp-wz-name') || {}).value.trim();
            var err = el(root, '#bdp-wz-name-error');
            if (err) err.hidden = ok;
            return ok;
        }
        if (step === 1) {
            var repoOk = !!(el(root, '#bdp-wz-repository') || {}).value.trim();
            var repoErr = el(root, '#bdp-wz-repository-error');
            if (repoErr) repoErr.hidden = repoOk;
            return repoOk;
        }
        if (step === 2) {
            var sources = ((el(root, '#bdp-wz-sources') || {}).value || '').split(/\r?\n/).map(function (s) { return s.trim(); }).filter(Boolean);
            var srcErr = el(root, '#bdp-wz-sources-error');
            if (srcErr) srcErr.hidden = sources.length > 0;
            return sources.length > 0;
        }
        return true;
    }

    function wizardProfilePayload() {
        var root = state.root;
        var sources = ((el(root, '#bdp-wz-sources') || {}).value || '').split(/\r?\n/).map(function (s) { return s.trim(); }).filter(Boolean);
        var excludes = ((el(root, '#bdp-wz-excludes') || {}).value || '').split(/\r?\n/).map(function (s) { return s.trim(); }).filter(Boolean);
        var within = el(root, 'input[name="bdp-wz-retention"][value="within"]');
        var retention;
        if (within && within.checked) {
            var days = Math.max(1, Number((el(root, '#bdp-wz-retention-days') || {}).value) || 30);
            retention = {
                keep_last: 0, keep_hourly: 0, keep_daily: 0, keep_weekly: 0, keep_monthly: 0, keep_yearly: 0,
                keep_within: days + 'd'
            };
        } else {
            retention = {
                keep_last: Number((el(root, '#bdp-wz-keep-last') || {}).value) || 0,
                keep_hourly: Number((el(root, '#bdp-wz-keep-hourly') || {}).value) || 0,
                keep_daily: Number((el(root, '#bdp-wz-keep-daily') || {}).value) || 0,
                keep_weekly: Number((el(root, '#bdp-wz-keep-weekly') || {}).value) || 0,
                keep_monthly: Number((el(root, '#bdp-wz-keep-monthly') || {}).value) || 0,
                keep_yearly: Number((el(root, '#bdp-wz-keep-yearly') || {}).value) || 0,
                keep_within: ''
            };
        }
        retention.prune = !!(el(root, '#bdp-wz-prune') || {}).checked;
        return {
            id: state.wizard.editing || undefined,
            name: (el(root, '#bdp-wz-name') || {}).value.trim(),
            description: (el(root, '#bdp-wz-description') || {}).value.trim(),
            enabled: !!(el(root, '#bdp-wz-enabled') || {}).checked,
            repository: (el(root, '#bdp-wz-repository') || {}).value.trim(),
            password: (el(root, '#bdp-wz-password') || {}).value || '',
            host: (el(root, '#bdp-wz-host') || {}).value.trim(),
            sources: sources,
            excludes: excludes,
            schedule: {
                enabled: !!(el(root, '#bdp-wz-schedule-enabled') || {}).checked,
                frequency: (el(root, '#bdp-wz-frequency') || {}).value || 'daily',
                time: (el(root, '#bdp-wz-time') || {}).value || '03:00',
                weekday: Number((el(root, '#bdp-wz-weekday') || {}).value) || 1,
                monthday: Number((el(root, '#bdp-wz-monthday') || {}).value) || 1,
                interval_hours: Number((el(root, '#bdp-wz-interval') || {}).value) || 6
            },
            retention: retention
        };
    }

    function openWizard(profileId) {
        var root = state.root;
        state.wizard.open = true;
        state.wizard.editing = profileId || null;
        var profile = null;
        if (profileId) {
            profile = (state.backups || []).filter(function (p) { return p.id === profileId; })[0] || null;
        }
        el(root, '#bdp-wizard-title').textContent = profile ? t('edit_backup') : t('wizard');
        var submit = el(root, '#bdp-wz-submit');
        if (submit) submit.textContent = profile ? t('save_changes') : t('create_backup');
        el(root, '#bdp-wz-name').value = profile ? (profile.name || '') : '';
        el(root, '#bdp-wz-description').value = profile ? (profile.description || '') : '';
        el(root, '#bdp-wz-enabled').checked = profile ? !!profile.enabled : true;
        var cfg = state.config || {};
        el(root, '#bdp-wz-repository').value = profile ? (profile.repository || '') : '';
        el(root, '#bdp-wz-password').value = '';
        el(root, '#bdp-wz-password-wrap').hidden = !!(profile && profile.repository && profile.has_password);
        var hostInput = el(root, '#bdp-wz-host');
        if (hostInput) hostInput.value = (profile && profile.host) || cfg.host || 'appdashboard';
        el(root, '#bdp-wz-rw-result').textContent = '';
        el(root, '#bdp-wz-sources').value = profile ? (profile.sources || []).join('\n') : '';
        el(root, '#bdp-wz-excludes').value = profile ? (profile.excludes || []).join('\n') : '';
        var sched = (profile && profile.schedule) || {};
        el(root, '#bdp-wz-schedule-enabled').checked = !!sched.enabled;
        el(root, '#bdp-wz-frequency').value = sched.frequency || 'daily';
        el(root, '#bdp-wz-time').value = sched.time || '03:00';
        el(root, '#bdp-wz-weekday').value = String(sched.weekday || 1);
        el(root, '#bdp-wz-monthday').value = String(sched.monthday || 1);
        el(root, '#bdp-wz-interval').value = String(sched.interval_hours || 6);
        var retention = (profile && profile.retention) || {};
        var withinRadio = el(root, 'input[name="bdp-wz-retention"][value="within"]');
        var keepAllRadio = el(root, 'input[name="bdp-wz-retention"][value="keep_all"]');
        var daysInput = el(root, '#bdp-wz-retention-days');
        if (retention.keep_within) {
            var m = /^(\d+)[dmy]$/.exec(retention.keep_within);
            withinRadio.checked = true;
            daysInput.value = m ? m[1] : '30';
        } else {
            var hasRules = ['last', 'hourly', 'daily', 'weekly', 'monthly', 'yearly'].some(function (k) {
                return Number(retention['keep_' + k]) > 0;
            });
            if (hasRules) {
                keepAllRadio.checked = false;
                withinRadio.checked = false;
                daysInput.disabled = true;
                el(root, '#bdp-wz-keep-last').value = String(retention.keep_last || 0);
                el(root, '#bdp-wz-keep-hourly').value = String(retention.keep_hourly || 0);
                el(root, '#bdp-wz-keep-daily').value = String(retention.keep_daily || 0);
                el(root, '#bdp-wz-keep-weekly').value = String(retention.keep_weekly || 0);
                el(root, '#bdp-wz-keep-monthly').value = String(retention.keep_monthly || 0);
                el(root, '#bdp-wz-keep-yearly').value = String(retention.keep_yearly || 0);
                el(root, '#bdp-wz-advanced').hidden = false;
            } else {
                keepAllRadio.checked = true;
            }
        }
        el(root, '#bdp-wz-prune').checked = retention.prune !== false;
        Array.prototype.forEach.call(root.querySelectorAll('#bdp-wizard .bdp-field-error'), function (n) { n.hidden = true; });
        el(root, '#bdp-wizard').hidden = false;
        // Start the destination tree next to the configured repository so the
        // user sees its parent folder instead of an empty directory.
        var repo = String((profile && profile.repository) || '');
        var slash = repo.lastIndexOf('/');
        state.trees.dest.path = slash > 0 ? repo.substring(0, slash) : '';
        state.trees.src.path = (profile && (profile.sources || [])[0]) || '';
        wizardGoTo(0);
    }

    function closeWizard() {
        state.wizard.open = false;
        state.wizard.editing = null;
        var modal = el(state.root, '#bdp-wizard');
        if (modal) modal.hidden = true;
    }

    function wizardSubmit() {
        var root = state.root;
        for (var step = 0; step < WIZARD_STEPS - 1; step++) {
            if (!wizardValidate(step)) { wizardGoTo(step); return; }
        }
        var payload = wizardProfilePayload();
        var submit = el(root, '#bdp-wz-submit');
        if (submit) submit.disabled = true;
        var editing = !!state.wizard.editing;
        state.loadSeq = (state.loadSeq || 0) + 1;
        api(editing ? 'backup_update' : 'backup_create', { method: 'POST', body: payload })
            .then(function (data) {
                state.backups = data.backups || [];
                if (data.backup && data.backup.id) {
                    state.activeRepo = data.backup.id;
                    var scheduleMap = (state.schedule || {}).profiles || (state.schedule.profiles = {});
                    scheduleMap[data.backup.id] = data.backup.schedule && data.backup.schedule.enabled
                        ? scheduleMap[data.backup.id] || 0 : 0;
                }
                renderBackups();
                renderRepoSelectors();
                setAlert('ok', data.message || (editing ? t('updated') : t('created')));
                closeWizard();
                loadAll(true);
            })
            .catch(function (err) { setAlert('error', err.message); })
            .then(function () {
                if (submit) submit.disabled = false;
            });
    }

    /* ------------------------------------------------------------------ *
     * Snapshot browse / restore / logs
     * ------------------------------------------------------------------ */

    function openModal(title, bodyHtml, footHtml) {
        var modal = el(state.root, '#bdp-modal');
        if (!modal) return;
        el(state.root, '#bdp-modal-title').textContent = title || '';
        el(state.root, '#bdp-modal-body').innerHTML = bodyHtml || '';
        el(state.root, '#bdp-modal-foot').innerHTML = footHtml || '';
        modal.hidden = false;
    }

    function closeModal() {
        var modal = el(state.root, '#bdp-modal');
        if (modal) modal.hidden = true;
        if (state.logTimer) { window.clearInterval(state.logTimer); state.logTimer = null; }
        var body = el(state.root, '#bdp-modal-body');
        if (body) body.innerHTML = '';
    }

    function openLog(jobId) {
        api('job', { query: { id: jobId } }).then(function (data) {
            var job = data.job || {};
            var log = data.log || '';
            openModal(t('job_details') + ' · ' + (job.type || ''), '<pre class="bdp-log">' + esc(log || t('job_log_empty')) + '</pre>',
                '<button type="button" class="bdp-btn" data-bdp-action="modal_close">' + esc(t('close')) + '</button>');
            if (job.status === 'running' || job.status === 'queued') {
                state.logTimer = window.setInterval(function () {
                    api('job', { query: { id: jobId } }).then(function (d) {
                        var pre = el(state.root, '#bdp-modal-body .bdp-log');
                        if (pre && !el(state.root, '#bdp-modal').hidden) pre.textContent = d.log || t('job_log_empty');
                        if (d.job && d.job.status !== 'running' && d.job.status !== 'queued') {
                            window.clearInterval(state.logTimer);
                            state.logTimer = null;
                        }
                    }).catch(function () {});
                }, 2500);
            }
        }).catch(function (err) { setAlert('error', err.message); });
    }

    function openRestore(snapshotId, include) {
        var body = ''
            + '<label class="bdp-field"><span>' + esc(t('restore_target')) + '</span>'
            + '<input type="text" id="bdp-restore-target" value="/restore" spellcheck="false"></label>'
            + '<div class="bdp-note">' + esc(t('restore_target_hint')) + '</div>'
            + '<label class="bdp-field"><span>' + esc(t('restore_include')) + '</span>'
            + '<input type="text" id="bdp-restore-include" value="' + esc(include || '') + '" spellcheck="false"></label>'
            + '<div class="bdp-note">' + esc(t('restore_include_hint')) + '</div>'
            + '<div class="bdp-note">' + esc(t('restore_warning')) + '</div>';
        var foot = '<button type="button" class="bdp-btn" data-bdp-action="modal_close">' + esc(t('cancel')) + '</button>'
            + '<button type="button" class="bdp-btn bdp-btn-primary" data-bdp-action="restore_submit" data-snapshot="' + esc(snapshotId) + '">' + esc(t('restore')) + '</button>';
        openModal(t('restore_snapshot') + ' · ' + String(snapshotId).substring(0, 8), body, foot);
    }

    function openBrowseSnapshot(snapshot, path) {
        var basePath = path || ((snapshot.paths || [])[0] || '/');
        api('ls', { query: { snapshot: snapshot.id, path: basePath } }).then(function (data) {
            var current = data.path || basePath;
            var sourceRoot = (snapshot.paths || [])[0] || '/';
            var rootSegments = sourceRoot.split('/').filter(Boolean);
            var crumbs = '<div class="bdp-breadcrumb">';
            var rootPath = '/' + (rootSegments[0] || '');
            crumbs += '<button type="button" data-bdp-ls-path="' + esc(rootPath) + '">' + esc(rootPath) + '</button>';
            var parts = current.split('/').filter(Boolean);
            var acc = rootPath;
            parts.slice(rootSegments.length ? 1 : 0).forEach(function (part) {
                acc += '/' + part;
                crumbs += '<span>/</span><button type="button" data-bdp-ls-path="' + esc(acc) + '">' + esc(part) + '</button>';
            });
            crumbs += '</div>';
            var rows = (data.entries || []).map(function (entry) {
                if (entry.type === 'dir') {
                    return '<div class="bdp-file-row"><span class="bdp-file-name">📁 <button type="button" class="bdp-btn bdp-btn-sm" data-bdp-ls-path="' + esc(entry.path) + '">' + esc(entry.name) + '</button></span></div>';
                }
                return '<div class="bdp-file-row"><span class="bdp-file-name">📄 ' + esc(entry.name) + '</span>'
                    + '<span class="bdp-file-meta">' + esc(fmtSize(entry.size)) + '</span>'
                    + '<button type="button" class="bdp-btn bdp-btn-sm" data-snap-restore-file="' + esc(entry.path) + '">' + esc(t('restore')) + '</button></div>';
            }).join('');
            if (!rows) rows = '<div class="bdp-empty">' + esc(t('empty_folder')) + '</div>';
            var body = crumbs + '<div class="bdp-scroll">' + rows + '</div>'
                + '<div class="bdp-note">' + esc(t('restore_include_hint')) + '</div>';
            var foot = '<button type="button" class="bdp-btn" data-bdp-action="modal_close">' + esc(t('close')) + '</button>'
                + '<button type="button" class="bdp-btn bdp-btn-primary" data-bdp-action="restore_form" data-snapshot="' + esc(snapshot.id) + '" data-path="' + esc(current) + '">' + esc(t('restore')) + '</button>';
            openModal(t('browse_snapshot') + ' · ' + (snapshot.short_id || snapshot.id), body, foot);
            el(state.root, '#bdp-modal')._bdpSnapshot = snapshot;
        }).catch(function (err) { setAlert('error', err.message); });
    }

    function findSnapshot(id) {
        return (state.snapshots || []).filter(function (s) { return s.id === id; })[0] || null;
    }

    /* ------------------------------------------------------------------ *
     * Data loading
     * ------------------------------------------------------------------ */

    function loadAll(showErrors) {
        var root = state.root;
        var seq = state.loadSeq = (state.loadSeq || 0) + 1;
        return api('bootstrap').then(function (data) {
            if (seq !== state.loadSeq) return data;
            state.config = data.config;
            state.backups = (data.config && data.config.backups) || [];
            if (!profileById(state.activeRepo)) {
                state.activeRepo = data.active_profile || (state.backups[0] || {}).id || '';
            }
            state.snapshots = data.snapshots || [];
            state.jobs = data.jobs || [];
            state.schedule = data.schedule_state || {};
            state.nextRun = state.schedule.next_run || 0;
            var loadActive = activeRepoProfile();
            state.repoOk = !data.snapshots_error && !!(loadActive && loadActive.repository) && loadActive.has_password !== false;
            renderRepoSelectors();
            renderBackups();
            renderSnapshots();
            renderJobs();
            updateStatus();
            loadStats();
            if (data.snapshots_error && showErrors) setAlert('warn', data.snapshots_error);
            schedulePoll();
            loadDisk();
            return data;
        }).catch(function (err) {
            if (showErrors) setAlert('error', err.message);
            updateStatus();
            throw err;
        });
    }

    function loadDisk() {
        var active = activeRepoProfile();
        var repo = active && active.repository ? String(active.repository) : '';
        var path = (repo && repo.charAt(0) === '/') ? repo : (state.hostRoot || '/');
        return api('disk', { query: { path: path } }).then(updateDisk).catch(function () {});
    }

    function schedulePoll() {
        if (state.pollTimer) return;
        state.pollTimer = window.setInterval(function () {
            var active = (state.jobs || []).some(function (j) { return j.status === 'running' || j.status === 'queued'; });
            if (!active) return;
            api('jobs').then(function (data) {
                var previous = state.jobs || [];
                state.jobs = data.jobs || [];
                renderJobs();
                renderBackups();
                updateStatus();
                var finished = previous.some(function (p) {
                    return (p.status === 'running' || p.status === 'queued') && state.jobs.every(function (j) {
                        return j.id !== p.id || (j.status !== 'running' && j.status !== 'queued');
                    });
                });
                if (finished) {
                    var seq = state.loadSeq;
                    api('bootstrap', { query: activeQuery() }).then(function (b) {
                        if (seq !== state.loadSeq) return;
                        state.snapshots = b.snapshots || [];
                        state.jobs = b.jobs || state.jobs;
                        state.config = b.config || state.config;
                        state.backups = (b.config && b.config.backups) || state.backups;
                        state.schedule = b.schedule_state || state.schedule;
                        var pollActive = activeRepoProfile();
                        state.repoOk = !b.snapshots_error && !!(pollActive && pollActive.repository) && pollActive.has_password !== false;
                        renderRepoSelectors();
                        renderSnapshots();
                        renderJobs();
                        renderBackups();
                        updateStatus();
                        loadDisk();
                    }).catch(function () {});
                }
            }).catch(function () {});
        }, 4000);
    }

    /* ------------------------------------------------------------------ *
     * Actions
     * ------------------------------------------------------------------ */

    function doAction(action, dataset) {
        var root = state.root;
        switch (action) {
            case 'refresh':
                setAlert('', '');
                loadAll(true);
                return;
            case 'add_backup':
                openWizard(null);
                return;
            case 'edit_backup':
                openWizard(dataset.profile || null);
                return;
            case 'delete_backup': {
                var id = dataset.profile || '';
                var profile = (state.backups || []).filter(function (p) { return p.id === id; })[0];
                var message = profile
                    ? t('confirm_delete_profile', { name: profile.name || id })
                    : t('confirm') + '?';
                if (!window.confirm(message)) return;
                api('backup_delete', { method: 'POST', body: { id: id } }).then(function (data) {
                    state.backups = data.backups || [];
                    renderBackups();
                    setAlert('ok', data.message || t('profile_deleted'));
                }).catch(function (err) { setAlert('error', err.message); });
                return;
            }
            case 'run_backup':
                runBackup(dataset.profile || '');
                return;
            case 'forget':
                if (!window.confirm(t('confirm') + '?')) return;
                startJob(api('forget', { method: 'POST', body: { profile: state.activeRepo } }));
                return;
            case 'check':
                startJob(api('check', { method: 'POST', body: { read_data: !!(el(root, '#bdp-check-read-data') || {}).checked, profile: state.activeRepo } }));
                return;
            case 'prune':
                startJob(api('prune', { method: 'POST', body: { profile: state.activeRepo } }));
                return;
            case 'unlock':
                startJob(api('unlock', { method: 'POST', body: { profile: state.activeRepo } }));
                return;
            case 'rebuild_index':
                startJob(api('rebuild_index', { method: 'POST', body: { profile: state.activeRepo } }));
                return;
            case 'stats':
                api('stats', { query: activeQuery() }).then(function (data) { renderStats(data.stats); }).catch(function (err) { setAlert('error', err.message); });
                return;
            case 'toggle_password': {
                var pwd = el(root, '#' + (dataset.target || 'bdp-wz-password'));
                if (pwd) pwd.type = pwd.type === 'password' ? 'text' : 'password';
                return;
            }
            case 'modal_close':
                closeModal();
                return;
            case 'restore_submit':
                var target = (el(root, '#bdp-restore-target') || {}).value || '';
                var include = (el(root, '#bdp-restore-include') || {}).value || '';
                if (!target.trim()) { setAlert('error', t('target_required')); return; }
                startJob(api('restore', { method: 'POST', body: { snapshot: dataset.snapshot, target: target.trim(), include: include.trim(), profile: state.activeRepo } }))
                    .then(function () { closeModal(); });
                return;
            case 'restore_form':
                openRestore(dataset.snapshot, dataset.path || '');
                return;
            /* Wizard */
            case 'wizard_close':
                closeWizard();
                return;
            case 'wizard_back':
                wizardGoTo(state.wizard.step - 1);
                return;
            case 'wizard_next':
                if (!wizardValidate(state.wizard.step)) return;
                wizardGoTo(state.wizard.step + 1);
                return;
            case 'wizard_submit':
                wizardSubmit();
                return;
            case 'wz_toggle_advanced': {
                var adv = el(root, '#bdp-wz-advanced');
                if (adv) adv.hidden = !adv.hidden;
                return;
            }
            case 'wz_dest_up':
                loadTree('dest').then(function () { state.trees.dest.path = state.trees.dest.parent; return loadTree('dest'); });
                return;
            case 'wz_src_up':
                state.trees.src.path = state.trees.src.parent;
                loadTree('src');
                return;
            case 'wz_dest_mkdir':
                treeMkdir('dest');
                return;
            case 'wz_src_mkdir':
                treeMkdir('src');
                return;
            case 'wz_test_rw': {
                var repo = ((el(root, '#bdp-wz-repository') || {}).value || '').trim();
                var result = el(root, '#bdp-wz-rw-result');
                if (result) result.textContent = t('loading');
                api('path_rw_test', { method: 'POST', body: { path: repo } }).then(function (data) {
                    if (result) result.textContent = (data.ok ? t('rw_ok') : t('rw_failed')) + ' · ' + (data.path || repo);
                }).catch(function (err) {
                    if (result) result.textContent = t('rw_failed') + ': ' + err.message;
                });
                return;
            }
            case 'wz_init': {
                var initRepo = ((el(root, '#bdp-wz-repository') || {}).value || '').trim();
                var initPassword = (el(root, '#bdp-wz-password') || {}).value || '';
                if (!initRepo) { setAlert('error', t('repo_not_configured')); return; }
                if (!window.confirm(t('init_confirm'))) return;
                api('init', { method: 'POST', body: { repository: initRepo, password: initPassword, host: ((el(root, '#bdp-wz-host') || {}).value || '').trim() || (state.config || {}).host || 'appdashboard' } })
                    .then(function (data) {
                        var res = el(root, '#bdp-wz-rw-result');
                        if (res) res.textContent = data.message || t('repo_initialized');
                        loadAll(false);
                    })
                    .catch(function (err) { setAlert('error', err.message); });
                return;
            }
        }
    }

    function bind(root) {
        if (root._bdpBound) return;
        root.addEventListener('click', function (event) {
            var target = event.target.closest('[data-bdp-action]');
            if (target && root.contains(target)) {
                event.preventDefault();
                doAction(target.getAttribute('data-bdp-action'), target.dataset || {});
                return;
            }
            var enter = event.target.closest('[data-bdp-enter]');
            if (enter && root.contains(enter)) {
                var kind = enter.getAttribute('data-bdp-enter-kind') || 'src';
                state.trees[kind].path = enter.getAttribute('data-bdp-enter');
                loadTree(kind);
                return;
            }
            var add = event.target.closest('[data-bdp-add-source]');
            if (add && root.contains(add)) {
                addSourceLine(add.getAttribute('data-bdp-add-source'));
                return;
            }
            var browse = event.target.closest('[data-snap-browse]');
            if (browse && root.contains(browse)) {
                var snap = findSnapshot(browse.getAttribute('data-snap-browse'));
                if (snap) openBrowseSnapshot(snap, (snap.paths || [])[0] || '/');
                return;
            }
            var restore = event.target.closest('[data-snap-restore]');
            if (restore && root.contains(restore)) {
                openRestore(restore.getAttribute('data-snap-restore'), '');
                return;
            }
            var restoreFile = event.target.closest('[data-snap-restore-file]');
            if (restoreFile && root.contains(restoreFile)) {
                var currentSnap = el(root, '#bdp-modal')._bdpSnapshot;
                if (currentSnap) openRestore(currentSnap.id, restoreFile.getAttribute('data-snap-restore-file'));
                return;
            }
            var del = event.target.closest('[data-snap-delete]');
            if (del && root.contains(del)) {
                var id = del.getAttribute('data-snap-delete');
                if (!window.confirm(t('confirm_delete_snapshot', { id: String(id).substring(0, 8) }))) return;
                startJob(api('delete_snapshot', { method: 'POST', body: { snapshot: id, profile: state.activeRepo } }));
                return;
            }
            var log = event.target.closest('[data-job-log]');
            if (log && root.contains(log)) { openLog(log.getAttribute('data-job-log')); return; }
            var cancel = event.target.closest('[data-job-cancel]');
            if (cancel && root.contains(cancel)) {
                api('job_cancel', { method: 'POST', body: { id: cancel.getAttribute('data-job-cancel') } }).then(function (data) {
                    setAlert('ok', data.message || t('job_cancel_requested'));
                    return api('jobs');
                }).then(function (data) {
                    state.jobs = data.jobs || state.jobs;
                    renderJobs();
                    renderBackups();
                    updateStatus();
                }).catch(function (err) { setAlert('error', err.message); });
                return;
            }
            var lsPath = event.target.closest('[data-bdp-ls-path]');
            if (lsPath && root.contains(lsPath)) {
                var snap2 = el(root, '#bdp-modal')._bdpSnapshot;
                if (snap2) openBrowseSnapshot(snap2, lsPath.getAttribute('data-bdp-ls-path'));
                return;
            }
            var wizardStep = event.target.closest('[data-wizard-step]');
            if (wizardStep && root.contains(wizardStep)) {
                var target2 = Number(wizardStep.getAttribute('data-wizard-step'));
                if (target2 > state.wizard.step) {
                    for (var s = state.wizard.step; s < target2; s++) {
                        if (!wizardValidate(s)) { wizardGoTo(s); return; }
                    }
                }
                wizardGoTo(target2);
                return;
            }
        });

        root.addEventListener('change', function (event) {
            var target = event.target;
            if (!target || !root.contains(target)) return;
            if (target.id === 'bdp-wz-frequency') wizardScheduleFieldsVisibility();
            if (target.id === 'bdp-wz-schedule-enabled') wizardScheduleFieldsVisibility();
            if (target.name === 'bdp-wz-retention') wizardRetentionVisibility();
        });

        root.addEventListener('input', function (event) {
            var target = event.target;
            if (!target || !root.contains(target)) return;
            if (target.id === 'bdp-wz-dest-filter') { state.trees.dest.filter = target.value; loadTree('dest'); return; }
            if (target.id === 'bdp-wz-src-filter') { state.trees.src.filter = target.value; loadTree('src'); return; }
        });

        var modal = el(root, '#bdp-modal');
        if (modal) modal.addEventListener('click', function (event) { if (event.target === modal) closeModal(); });
        var wizardModal = el(root, '#bdp-wizard');
        if (wizardModal) wizardModal.addEventListener('click', function (event) { if (event.target === wizardModal) closeWizard(); });
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            if (modal && !modal.hidden) closeModal();
            if (wizardModal && !wizardModal.hidden) closeWizard();
        });
        root._bdpBound = true;
    }

    function init(container) {
        var scope = container || document;
        var root = scope.querySelector ? scope.querySelector('#bdp-app') : null;
        if (!root && scope.id === 'bdp-app') root = scope;
        if (!root) return;
        state.root = root;
        if (root._bdpInit) return;
        root._bdpInit = true;
        state._bdpI18n = null;
        root._bdpI18n = JSON.parse(root.getAttribute('data-bdp-i18n') || '{}');
        applyI18n(root);
        bind(root);
        loadAll(true);
        setInterval(function () {
            if (!document.body.contains(root)) return;
            if (state.pollTimer) return;
            var active = (state.jobs || []).some(function (j) { return j.status === 'running' || j.status === 'queued'; });
            if (active) schedulePoll();
        }, 15000);
    }

    function applyI18n(root) {
        var dict = root._bdpI18n || {};
        Array.prototype.forEach.call(root.querySelectorAll('[data-bdp-i18n]'), function (node) {
            var key = node.getAttribute('data-bdp-i18n');
            if (dict[key]) node.textContent = dict[key];
        });
        Array.prototype.forEach.call(root.querySelectorAll('[data-bdp-i18n-ph]'), function (node) {
            var key = node.getAttribute('data-bdp-i18n-ph');
            if (dict[key]) node.setAttribute('placeholder', dict[key]);
        });
    }

    window.BackupPanel = { init: init, refresh: function () { if (state.root) loadAll(true); } };
    ready(function () { init(document); });
})();
