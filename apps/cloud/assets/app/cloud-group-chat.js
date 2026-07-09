(function () {
    const state = {
        open: false,
        detached: false,
        groupId: null,
        messages: [],
        maxId: 0,
        minId: null,
        hasMoreOlder: false,
        loadingOlder: false,
        pollTimer: null,
        editingId: null,
        mentionFiles: [],
        mentionSuggestions: [],
        mentionSelectedIndex: 0,
        mentionRange: null,
        mentionRequestId: 0,
        mentionFolderCache: { root: '', path: '', files: [], loadedAt: 0 },
        audioPlayers: [],
        userEmail: '',
        width: 320,
        detachedX: null,
        detachedY: null,
        detachedW: 360,
        detachedH: 520
    };
    let mediaRecorder = null;
    let audioChunks = [];
    let isRecording = false;
    const panel = () => document.getElementById('group-chat-panel');
    const messagesEl = () => document.getElementById('group-chat-messages');
    const inputEl = () => document.getElementById('group-chat-input');
    const fileInput = () => document.getElementById('group-chat-file');
    const groupSelect = () => document.getElementById('group-chat-group-select');
    function t(key, params) {
        return typeof window.t === 'function' ? window.t(window.i18n || {}, key, params) : key;
    }
    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }
    function escAttr(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;');
    }
    function groups() {
        return (window.cloudGroupsState && window.cloudGroupsState.groups) || [];
    }
    function activeGroupId() {
        const g = groups();
        if (!g.length) return null;
        if (state.groupId && g.some((group) => group.id === state.groupId)) return state.groupId;
        if (state.groupId) {
            state.groupId = null;
            savePrefs();
        }
        const root = typeof getCurrentCloudRoot === 'function' ? getCurrentCloudRoot() : '';
        if (String(root).startsWith('group:')) {
            const rootGroupId = String(root).slice(6);
            if (g.some((group) => group.id === rootGroupId)) return rootGroupId;
        }
        return g[0].id;
    }
    function groupName(id) {
        const g = groups().find((x) => x.id === id);
        return g ? g.name : id;
    }
    function isCurrentUserAdmin() {
        const gid = activeGroupId();
        if (!gid) return false;
        const g = groups().find((x) => x.id === gid);
        return g ? !!g.is_admin : false;
    }
    function updateDeleteButton() {
        const btn = document.getElementById('group-chat-delete');
        if (!btn) return;
        btn.classList.toggle('hidden', !isCurrentUserAdmin());
    }
    async function deleteChat() {
        const gid = activeGroupId();
        if (!gid || !isCurrentUserAdmin()) return;
        const confirmFn = typeof window.showConfirmModal === 'function' ? window.showConfirmModal : null;
        if (confirmFn) {
            const name = groupName(gid);
            const { confirmed } = await confirmFn(t('groupChatDeleteConfirmTitle'), t('groupChatDeleteConfirmText', { name: name }));
            if (!confirmed) return;
        } else {
            if (!confirm(t('groupChatDeleteConfirmSimple'))) return;
        }
        try {
            await api('groups_delete', { group_id: gid });
            if (typeof window.cloudGroups !== 'undefined' && window.cloudGroups.loadGroups) {
                await window.cloudGroups.loadGroups();
            }
            state.groupId = null;
            state.messages = [];
            state.maxId = 0;
            savePrefs();
            closePanel();
            if (typeof flashStatus === 'function') flashStatus(t('groupChatDeleted'));
        } catch (e) {
            if (typeof flashStatus === 'function') flashStatus(t('errorPrefix') + e.message);
        }
    }
    function loadPrefs() {
        try {
            const raw = localStorage.getItem('cloudGroupChatPrefs');
            if (!raw) return;
            const p = JSON.parse(raw);
            if (p.width) state.width = Math.max(260, Math.min(560, +p.width || 320));
            state.detached = !!p.detached;
            if (p.detachedX != null) state.detachedX = +p.detachedX;
            if (p.detachedY != null) state.detachedY = +p.detachedY;
            if (p.detachedW) state.detachedW = Math.max(280, Math.min(720, +p.detachedW));
            if (p.detachedH) state.detachedH = Math.max(320, Math.min(900, +p.detachedH));
            if (p.groupId) state.groupId = p.groupId;
            state.open = !!p.open;
        } catch (_) {}
    }
    function savePrefs() {
        try {
            localStorage.setItem('cloudGroupChatPrefs', JSON.stringify({
                open: state.open,
                detached: state.detached,
                width: state.width,
                groupId: state.groupId,
                detachedX: state.detachedX,
                detachedY: state.detachedY,
                detachedW: state.detachedW,
                detachedH: state.detachedH
            }));
        } catch (_) {}
    }
    function isAllowedMediaFile(file) {
        if (!file) return false;
        const type = String(file.type || '').toLowerCase();
        if (type.startsWith('image/') || type.startsWith('video/') || type.startsWith('audio/')) return true;
        return false;
    }
    function applyLayout() {
        const el = panel();
        if (!el) return;
        const g = groups();
        const groupsReady = !!(window.cloudGroupsState && window.cloudGroupsState.loaded);
        el.classList.toggle('hidden', !state.open || (groupsReady && !g.length));
        el.classList.toggle('group-chat-panel--detached', state.detached);
        el.classList.toggle('group-chat-panel--docked', !state.detached);
        const resizeH = document.getElementById('group-chat-resize-h');
        if (resizeH) resizeH.classList.toggle('hidden', !state.detached);
        if (state.detached) {
            el.style.width = state.detachedW + 'px';
            el.style.height = state.detachedH + 'px';
            if (state.detachedX != null) el.style.left = state.detachedX + 'px';
            if (state.detachedY != null) el.style.top = state.detachedY + 'px';
            el.style.right = 'auto';
        } else {
            el.style.width = state.width + 'px';
            el.style.height = '';
            el.style.left = '';
            el.style.top = '';
            el.style.right = '';
        }
        document.body.classList.toggle('group-chat-open', state.open && !state.detached && (!groupsReady || g.length > 0));
        savePrefs();
    }
    function updateChatButton(count) {
        const btn = document.getElementById('btn-group-chat');
        if (!btn) return;
        const hasGroups = groups().length > 0;
        btn.classList.toggle('hidden', !hasGroups);
        btn.classList.toggle('has-unread', (count || 0) > 0);
        const badge = btn.querySelector('.group-chat-unread-badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.classList.remove('hidden');
            } else badge.classList.add('hidden');
        }
    }
    async function refreshUnread() {
        if (!groups().length) { updateChatButton(0); return; }
        try {
            const resp = await api('group_chat_unread', {}, 'GET');
            updateChatButton(resp && resp.ok ? (resp.count || 0) : 0);
        } catch (_) { updateChatButton(0); }
    }
    const MENTION_MAX = 12;
    const MENTION_CACHE_MS = 15000;
    function getMentionRoot() {
        return typeof getCurrentCloudRoot === 'function' ? getCurrentCloudRoot() : 'main';
    }
    function getDirectoryPath(filePath) {
        if (!filePath) return '';
        const p = String(filePath).replace(/\\/g, '/');
        const i = p.lastIndexOf('/');
        return i >= 0 ? p.slice(0, i) : '';
    }
    function getActiveFilePath() {
        const currentRoot = getMentionRoot();
        let activeTab = null;
        if (window.tabsState?.activeTabId && Array.isArray(window.tabsState.tabs)) {
            activeTab = window.tabsState.tabs.find((tab) => tab.id === window.tabsState.activeTabId);
            const activeTabRoot = typeof window.getTabRoot === 'function' ? window.getTabRoot(activeTab) : activeTab?.root;
            if (activeTab?.path && (!activeTabRoot || activeTabRoot === currentRoot)) return activeTab.path;
        }
        const viewerPath = document.getElementById('viewer')?.dataset?.path;
        if (viewerPath) {
            const activeTabRoot = typeof window.getTabRoot === 'function' ? window.getTabRoot(activeTab) : activeTab?.root;
            if (!activeTab || !activeTabRoot || activeTabRoot === currentRoot) return viewerPath;
        }
        return '';
    }
    function normalizeMentionPath(path) {
        return String(path || '').replace(/\\/g, '/').replace(/^\/+/, '').trim();
    }
    async function getCurrentFolderFiles(currentFilePath) {
        const root = getMentionRoot();
        if (!root || !String(root).startsWith('group:')) return [];
        const folderPath = currentFilePath ? getDirectoryPath(currentFilePath) : '';
        const now = Date.now();
        const cache = state.mentionFolderCache;
        if (cache.root === root && cache.path === folderPath && (now - cache.loadedAt) < MENTION_CACHE_MS) return cache.files;
        try {
            const data = await api('list', { path: folderPath, root }, 'GET');
            const files = (data?.items || [])
                .filter((item) => item.type === 'file' && typeof item.name === 'string')
                .map((item) => normalizeMentionPath(item.path || item.name));
            state.mentionFolderCache = { root, path: folderPath, files, loadedAt: now };
            return files;
        } catch (_) {
            return [];
        }
    }
    function getMentionContext(value, cursorPos) {
        const beforeCursor = value.slice(0, cursorPos);
        const match = beforeCursor.match(/(^|\s)@([^\s@]*)$/);
        if (!match) return null;
        const query = match[2] || '';
        return { start: cursorPos - query.length - 1, end: cursorPos, query };
    }
    function mentionsEl() { return document.getElementById('group-chat-mentions'); }
    function mentionSuggestionsEl() { return document.getElementById('group-chat-mention-suggestions'); }
    function isMentionSuggestionsVisible() {
        const el = mentionSuggestionsEl();
        return !!(el && !el.classList.contains('hidden') && state.mentionSuggestions.length > 0);
    }
    function hideMentionSuggestions() {
        state.mentionSuggestions = [];
        state.mentionSelectedIndex = 0;
        state.mentionRange = null;
        const el = mentionSuggestionsEl();
        if (el) { el.classList.add('hidden'); el.innerHTML = ''; }
    }
    function renderMentionSuggestions() {
        const el = mentionSuggestionsEl();
        if (!el) return;
        const list = state.mentionSuggestions;
        if (!list.length) {
            el.innerHTML = '<div class="group-chat-mention-suggestion-empty">' + esc(t('aiNoMatchingFilesInFolder')) + '</div>';
            el.classList.remove('hidden');
            return;
        }
        el.innerHTML = list.map((name, index) => {
            const label = name.split('/').pop() || name;
            return '<button type="button" class="group-chat-mention-suggestion' + (index === state.mentionSelectedIndex ? ' active' : '') + '" data-index="' + index + '">' + esc(label) + '</button>';
        }).join('');
        el.classList.remove('hidden');
        el.querySelectorAll('.group-chat-mention-suggestion').forEach((btn) => {
            btn.addEventListener('mousedown', (e) => {
                e.preventDefault();
                applyMentionSuggestion(+btn.dataset.index);
            });
        });
    }
    function moveMentionSelection(delta) {
        if (!state.mentionSuggestions.length) return;
        const last = state.mentionSuggestions.length - 1;
        let next = state.mentionSelectedIndex + delta;
        if (next < 0) next = last;
        if (next > last) next = 0;
        state.mentionSelectedIndex = next;
        renderMentionSuggestions();
    }
    function applyMentionSuggestion(index) {
        const input = inputEl();
        if (!input || !state.mentionRange) return;
        const selected = state.mentionSuggestions[index];
        if (!selected) return;
        const value = input.value;
        const prefix = value.slice(0, state.mentionRange.start);
        const suffix = value.slice(state.mentionRange.end);
        input.value = (prefix + suffix).replace(/\s{2,}/g, ' ');
        const pos = prefix.length;
        input.selectionStart = pos;
        input.selectionEnd = pos;
        if (!state.mentionFiles.includes(selected)) state.mentionFiles.push(selected);
        renderMentionChips();
        hideMentionSuggestions();
        input.focus();
    }
    function addMentionFile(path) {
        const root = getMentionRoot();
        if (!root || !String(root).startsWith('group:')) return false;
        const normalized = normalizeMentionPath(path);
        if (!normalized || normalized.includes('..')) return false;
        if (!state.mentionFiles.includes(normalized)) state.mentionFiles.push(normalized);
        renderMentionChips();
        inputEl()?.focus();
        return true;
    }
    function removeMentionFile(path) {
        state.mentionFiles = state.mentionFiles.filter((p) => p !== path);
        renderMentionChips();
    }
    function clearMentionFiles() {
        state.mentionFiles = [];
        renderMentionChips();
        hideMentionSuggestions();
    }
    function renderMentionChips() {
        const el = mentionsEl();
        if (!el) return;
        if (!state.mentionFiles.length) {
            el.classList.add('hidden');
            el.innerHTML = '';
            return;
        }
        el.innerHTML = state.mentionFiles.map((path) => {
            const label = path.split('/').pop() || path;
            return '<span class="group-chat-mention-chip">' +
                '<button type="button" class="group-chat-mention-chip-open" data-path="' + escAttr(path) + '" title="' + escAttr(path) + '">@' + esc(label) + '</button>' +
                '<button type="button" class="group-chat-mention-chip-remove" data-path="' + escAttr(path) + '" aria-label="Remove">&times;</button>' +
                '</span>';
        }).join('');
        el.classList.remove('hidden');
    }
    function openMentionFile(path) {
        if (!path || typeof openFileInTab !== 'function') return;
        openFileInTab(path, false, getMentionRoot());
    }
    async function handleMentionInput() {
        const root = getMentionRoot();
        if (!root || !String(root).startsWith('group:')) { hideMentionSuggestions(); return; }
        const input = inputEl();
        if (!input) return;
        const cursorPos = input.selectionStart || 0;
        const context = getMentionContext(input.value, cursorPos);
        if (!context) { hideMentionSuggestions(); return; }
        const requestId = ++state.mentionRequestId;
        const files = await getCurrentFolderFiles(getActiveFilePath());
        if (requestId !== state.mentionRequestId) return;
        const queryLower = context.query.toLowerCase();
        state.mentionSuggestions = files
            .filter((name) => !queryLower || name.toLowerCase().includes(queryLower))
            .slice(0, MENTION_MAX);
        state.mentionSelectedIndex = 0;
        state.mentionRange = { start: context.start, end: context.end };
        renderMentionSuggestions();
    }
    function getTreeFilePathFromDragEvent(event) {
        const root = getMentionRoot();
        if (!root || !String(root).startsWith('group:')) return '';
        const dt = event?.dataTransfer;
        if (!dt) return '';
        const hasCloudType = Array.from(dt.types || []).includes('application/x-cloud-file-path');
        if (!hasCloudType) return '';
        const type = dt.getData('application/x-cloud-file-type');
        if (type && type !== 'file') return '';
        return normalizeMentionPath(dt.getData('application/x-cloud-file-path'));
    }
    function splitMessageParts(text) {
        if (!text) return { body: '', files: [] };
        const bodyLines = [];
        const files = [];
        String(text).split('\n').forEach((line) => {
            const m = line.match(/^@(\S.+)$/);
            if (m) {
                const path = normalizeMentionPath(m[1]);
                if (path && !path.includes('..')) files.push(path);
                else bodyLines.push(line);
            } else bodyLines.push(line);
        });
        return { body: bodyLines.join('\n').trim(), files };
    }
    function formatMessage(text) {
        const { body, files } = splitMessageParts(text);
        let html = '';
        if (files.length) {
            html += '<div class="group-chat-file-refs">' + files.map((p) => {
                const label = p.split('/').pop() || p;
                return '<button type="button" class="group-chat-file-ref" data-path="' + escAttr(p) + '" title="' + escAttr(p) + '">@' + esc(label) + '</button>';
            }).join('') + '</div>';
        }
        if (body) {
            let s = esc(body);
            s = s.replace(/`([^`]+)`/g, '<code class="gchat-inline-code">$1</code>');
            s = s.replace(/\*\*([^\*]+)\*\*/g, '<strong>$1</strong>');
            s = s.replace(/\*([^\*]+)\*/g, '<em>$1</em>');
            s = s.replace(/\n/g, '<br>');
            html += '<div class="group-chat-text">' + s + '</div>';
        }
        return html;
    }
    function setSendButtonMode(edit) {
        const sendBtn = document.getElementById('group-chat-send');
        if (!sendBtn) return;
        const label = edit ? t('save') : t('groupChatSend');
        sendBtn.title = label;
        sendBtn.setAttribute('aria-label', label);
    }
    function showMessagesLoading() {
        const box = messagesEl();
        if (box) box.innerHTML = '<p class="group-chat-empty">' + esc(t('loading')) + '…</p>';
    }
    function destroyAudioPlayers() {
        state.audioPlayers.forEach((player) => {
            try { player.destroy(); } catch (_) {}
        });
        state.audioPlayers = [];
    }
    function wireAudioPlayers(box) {
        if (!box) return;
        box.querySelectorAll('.group-chat-media--audio').forEach((el) => {
            if (el.dataset.audioBound) return;
            const src = el.dataset.audioSrc;
            if (!src || typeof CustomAudioPlayer !== 'function') return;
            try {
                const player = new CustomAudioPlayer(el, src, { showVolume: false, variant: 'voice', barCount: 64 });
                el.dataset.audioBound = '1';
                state.audioPlayers.push(player);
            } catch (_) {}
        });
    }
    function bindMediaPreviews(box) {
        if (!box) return;
        box.querySelectorAll('.group-chat-lightbox-img').forEach((img) => {
            const wrap = img.closest('.group-chat-media');
            if (wrap) wrap.classList.add('group-chat-media--loading');
            const done = () => { wrap?.classList.remove('group-chat-media--loading'); };
            if (img.complete) done();
            else {
                img.addEventListener('load', done, { once: true });
                img.addEventListener('error', done, { once: true });
            }
            img.addEventListener('click', () => openLightbox(img.src, 'image'));
        });
        box.querySelectorAll('.group-chat-video-thumb').forEach((btn) => {
            const url = btn.dataset.url || '';
            const preview = btn.querySelector('video');
            const wrap = btn.closest('.group-chat-media');
            if (wrap) wrap.classList.add('group-chat-media--loading');
            const done = () => { wrap?.classList.remove('group-chat-media--loading'); };
            if (preview) {
                if (preview.readyState >= 1) done();
                else {
                    preview.addEventListener('loadeddata', done, { once: true });
                    preview.addEventListener('error', done, { once: true });
                }
            } else done();
            btn.addEventListener('click', () => { if (url) openLightbox(url, 'video'); });
        });
    }
    function scrollBottom(aggressive) {
        const box = messagesEl();
        if (!box) return;
        const run = () => { box.scrollTop = box.scrollHeight; };
        run();
        requestAnimationFrame(run);
        if (aggressive) [40, 120].forEach((ms) => setTimeout(run, ms));
    }
    function renderMessages(opts) {
        const box = messagesEl();
        if (!box) return;
        opts = opts || {};
        const msgs = state.messages;
        if (!msgs.length) {
            box.innerHTML = '<p class="group-chat-empty">' + esc(t('groupChatNoMessages')) + '</p>';
            return;
        }
        const wasBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 80;
        destroyAudioPlayers();
        box.innerHTML = msgs.map((msg) => {
            const own = !!msg.is_own;
            const time = new Date((msg.created_at || 0) * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            let body = '';
            if (msg.is_deleted) {
                body = '<em class="group-chat-deleted">' + esc(t('groupChatDeleted')) + '</em>';
            } else {
                if (msg.media_url && msg.media_type === 'image') {
                    body += '<div class="group-chat-media group-chat-media--image"><img class="group-chat-lightbox-img group-chat-media-thumb" loading="lazy" decoding="async" src="' + escAttr(msg.media_url) + '" alt=""></div>';
                } else if (msg.media_url && msg.media_type === 'video') {
                    body += '<div class="group-chat-media group-chat-media--video"><button type="button" class="group-chat-video-thumb" data-url="' + escAttr(msg.media_url) + '" aria-label="Play video"><video class="group-chat-media-preview" muted playsinline preload="metadata" src="' + escAttr(msg.media_url) + '#t=0.1"></video><span class="group-chat-media-play" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span></button></div>';
                } else if (msg.media_url && msg.media_type === 'audio') {
                    body += '<div class="group-chat-media group-chat-media--audio" data-audio-src="' + escAttr(msg.media_url) + '"></div>';
                } else if (msg.media_url && msg.media_type === 'text') {
                    body += '<a class="group-chat-text-file" href="' + escAttr(msg.media_url) + '" target="_blank" rel="noopener">' + esc(msg.media_file || 'file') + '</a>';
                }
                if (msg.message) body += formatMessage(msg.message);
                if (!own && msg.sender_email) body = '<div class="group-chat-sender">' + esc(msg.sender_email) + '</div>' + body;
            }
            const edited = msg.is_edited ? '<span class="group-chat-edited">' + esc(t('groupChatEdited')) + '</span>' : '';
            return '<div class="group-chat-row' + (own ? ' group-chat-row--own' : '') + '">' +
                '<div class="group-chat-bubble" data-msg-id="' + msg.id + '">' + body +
                '<div class="group-chat-meta"><span>' + time + edited + '</span></div></div></div>';
        }).join('');
        box.querySelectorAll('.group-chat-bubble').forEach((bubble) => {
            bubble.addEventListener('contextmenu', (e) => {
                const id = +bubble.dataset.msgId;
                const msg = state.messages.find((m) => m.id === id);
                if (!msg || msg.is_deleted || !msg.is_own) return;
                e.preventDefault();
                showContextMenu(e, id);
            });
        });
        bindMediaPreviews(box);
        wireAudioPlayers(box);
        box.querySelectorAll('.group-chat-file-ref').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                openMentionFile(btn.dataset.path);
            });
        });
        if (opts.preserveScroll && opts.prevHeight != null) {
            requestAnimationFrame(() => { box.scrollTop = box.scrollHeight - opts.prevHeight + (opts.prevTop || 0); });
        } else if (opts.forceScroll || wasBottom) scrollBottom(!!opts.forceScroll);
    }
    function showContextMenu(e, msgId) {
        const menu = document.getElementById('group-chat-context-menu');
        if (!menu) return;
        menu.style.display = 'block';
        menu.style.left = e.pageX + 'px';
        menu.style.top = e.pageY + 'px';
        menu.dataset.msgId = String(msgId);
    }
    function hideContextMenu() {
        const menu = document.getElementById('group-chat-context-menu');
        if (menu) menu.style.display = 'none';
    }
    function cancelEdit() {
        state.editingId = null;
        setSendButtonMode(false);
        if (inputEl()) inputEl().value = '';
        clearMentionFiles();
        const prev = document.getElementById('group-chat-edit-preview');
        if (prev) prev.remove();
    }
    function startEdit(msgId) {
        const msg = state.messages.find((m) => m.id === msgId);
        if (!msg) return;
        state.editingId = msgId;
        const parts = splitMessageParts(msg.message || '');
        if (inputEl()) inputEl().value = parts.body;
        state.mentionFiles = parts.files.slice();
        renderMentionChips();
        setSendButtonMode(true);
    }
    async function deleteMessage(msgId) {
        const gid = activeGroupId();
        if (!gid) return;
        try {
            await api('group_chat_delete', { group_id: gid, message_id: msgId }, 'POST');
            await loadMessages(false, { full: true });
        } catch (e) { console.error(e); }
    }
    async function markRead() {
        const gid = activeGroupId();
        if (!gid || !state.maxId) return;
        try { await api('group_chat_mark_read', { group_id: gid, last_id: state.maxId }, 'POST'); } catch (_) {}
        refreshUnread();
    }
    async function loadMessages(silent, opts) {
        const gid = activeGroupId();
        if (!gid || !state.open) return;
        opts = opts || {};
        let url = 'group_chat_messages';
        const params = { group_id: gid };
        if (opts.full) params.full = 1;
        else if (silent && state.maxId > 0) params.since_id = state.maxId;
        else if (!silent) params.limit = 30;
        else return;
        try {
            const data = await api(url, params, 'GET');
            if (!data || !data.ok) return;
            if (opts.full || data.mode === 'full') {
                state.messages = data.messages || [];
                state.minId = state.messages.length ? state.messages[0].id : null;
                state.maxId = 0;
                state.messages.forEach((m) => { state.maxId = Math.max(state.maxId, m.id); });
                state.hasMoreOlder = false;
                renderMessages({ forceScroll: true });
                if (!silent) markRead();
                return;
            }
            if (silent && data.mode === 'delta') {
                if (!data.messages || !data.messages.length) return;
                const seen = {};
                state.messages.forEach((m) => { seen[m.id] = true; });
                const box = messagesEl();
                const wasBottom = box && box.scrollHeight - box.scrollTop - box.clientHeight < 80;
                data.messages.forEach((m) => { if (!seen[m.id]) state.messages.push(m); });
                state.messages.sort((a, b) => a.id - b.id);
                state.maxId = Math.max(state.maxId, ...data.messages.map((m) => m.id));
                renderMessages({ forceScroll: wasBottom });
                if (wasBottom) markRead();
                return;
            }
            if (!silent && data.mode === 'initial') {
                state.messages = data.messages || [];
                state.minId = data.min_id != null ? data.min_id : null;
                state.maxId = data.max_id != null ? data.max_id : 0;
                state.hasMoreOlder = !!data.has_more_older;
                renderMessages({ forceScroll: true });
                markRead();
            }
            if (data.mode === 'older') {
                const box = messagesEl();
                const prevH = box ? box.scrollHeight : 0;
                const prevT = box ? box.scrollTop : 0;
                const byId = {};
                (data.messages || []).forEach((m) => { byId[m.id] = m; });
                state.messages.forEach((m) => { byId[m.id] = m; });
                state.messages = Object.keys(byId).map((k) => byId[k]).sort((a, b) => a.id - b.id);
                state.minId = state.messages.length ? state.messages[0].id : null;
                state.hasMoreOlder = !!data.has_more_older;
                renderMessages({ preserveScroll: true, prevHeight: prevH, prevTop: prevT });
            }
        } catch (e) { console.error('group chat load failed', e); }
    }
    async function loadOlder() {
        const gid = activeGroupId();
        if (!gid || state.loadingOlder || !state.hasMoreOlder || !state.minId) return;
        state.loadingOlder = true;
        try {
            const data = await api('group_chat_messages', { group_id: gid, before_id: state.minId, limit: 30 }, 'GET');
            state.loadingOlder = false;
            if (!data || !data.ok || data.mode !== 'older') return;
            const box = messagesEl();
            const prevH = box ? box.scrollHeight : 0;
            const prevT = box ? box.scrollTop : 0;
            const byId = {};
            state.messages.forEach((m) => { byId[m.id] = m; });
            (data.messages || []).forEach((m) => { byId[m.id] = m; });
            state.messages = Object.keys(byId).map((k) => byId[k]).sort((a, b) => a.id - b.id);
            state.minId = state.messages.length ? state.messages[0].id : null;
            state.hasMoreOlder = !!data.has_more_older;
            renderMessages({ preserveScroll: true, prevHeight: prevH, prevTop: prevT });
        } catch (e) {
            state.loadingOlder = false;
        }
    }
    async function sendMessage(file) {
        const gid = activeGroupId();
        if (!gid) return;
        if (file && !isAllowedMediaFile(file)) {
            if (typeof flashStatus === 'function') flashStatus(t('groupChatMediaOnly'));
            return;
        }
        let message = inputEl() ? inputEl().value.trim() : '';
        const mentionRefs = state.mentionFiles.map((p) => '@' + p);
        if (mentionRefs.length) message = message ? message + '\n' + mentionRefs.join('\n') : mentionRefs.join('\n');
        if (!message && !file) return;
        if (state.editingId) {
            const fd = new FormData();
            fd.append('action', 'group_chat_edit');
            fd.append('group_id', gid);
            fd.append('message_id', String(state.editingId));
            fd.append('message', message);
            if (file) fd.append('media', file);
            try {
                const base = (window.APP_CONFIG && window.APP_CONFIG.apiBase) ? window.APP_CONFIG.apiBase : 'api.php';
                const r = await fetch(base, { method: 'POST', body: fd });
                const data = await r.json();
                if (data.ok) { cancelEdit(); await loadMessages(false, { full: true }); }
            } catch (e) { console.error(e); }
            return;
        }
        const fd = new FormData();
        fd.append('action', 'group_chat_send');
        fd.append('group_id', gid);
        fd.append('message', message);
        if (file) fd.append('media', file);
        try {
            const base = (window.APP_CONFIG && window.APP_CONFIG.apiBase) ? window.APP_CONFIG.apiBase : 'api.php';
            const r = await fetch(base, { method: 'POST', body: fd });
            const data = await r.json();
            if (data.ok) {
                if (inputEl()) inputEl().value = '';
                clearMentionFiles();
                if (fileInput()) fileInput().value = '';
                if (state.maxId > 0) await loadMessages(true);
                else await loadMessages(false);
                scrollBottom(true);
            }
        } catch (e) { console.error(e); }
    }
    function startPolling() {
        stopPolling();
        state.pollTimer = setInterval(() => {
            if (state.open && !document.hidden) loadMessages(true);
            refreshUnread();
        }, 4000);
    }
    function stopPolling() {
        if (state.pollTimer) { clearInterval(state.pollTimer); state.pollTimer = null; }
    }
    function renderGroupSelect() {
        const sel = groupSelect();
        const title = document.getElementById('group-chat-title');
        const gid = activeGroupId();
        if (sel) {
            sel.innerHTML = groups().map((g) =>
                '<option value="' + g.id + '"' + (g.id === gid ? ' selected' : '') + '>' + esc(g.name) + '</option>'
            ).join('');
            sel.classList.toggle('hidden', groups().length < 2);
        }
        if (title) {
            title.classList.toggle('hidden', groups().length > 1);
            if (groups().length === 1 && gid) title.textContent = groupName(gid);
            else title.textContent = t('groupChatTitle');
        }
        updateDeleteButton();
    }
    function restoreOpenPanel() {
        if (!groups().length) return;
        state.open = true;
        const savedId = state.groupId;
        const validSaved = savedId && groups().some((g) => g.id === savedId);
        state.groupId = validSaved ? savedId : activeGroupId();
        applyLayout();
        renderGroupSelect();
        showMessagesLoading();
        loadMessages(false);
        if (!state.pollTimer) startPolling();
    }
    function openPanel() {
        if (!groups().length) return;
        state.open = true;
        state.groupId = activeGroupId();
        applyLayout();
        renderGroupSelect();
        state.messages = [];
        state.maxId = 0;
        showMessagesLoading();
        loadMessages(false);
        startPolling();
    }
    function closePanel() {
        state.open = false;
        stopPolling();
        applyLayout();
    }
    function togglePanel() {
        if (state.open) closePanel();
        else openPanel();
    }
    function toggleDetach() {
        state.detached = !state.detached;
        const el = panel();
        if (state.detached && el) {
            const w = Math.max(280, Math.min(480, state.detachedW || 360));
            const h = Math.max(320, Math.min(620, state.detachedH || 480));
            state.detachedW = w;
            state.detachedH = h;
            state.detachedX = Math.max(8, window.innerWidth - w - 16);
            state.detachedY = Math.max(48, Math.min(window.innerHeight - h - 16, (window.innerHeight - h) * 0.15));
        }
        applyLayout();
    }
    function openLightbox(url, type) {
        const lb = document.getElementById('group-chat-lightbox');
        const inner = document.getElementById('group-chat-lightbox-inner');
        if (!lb || !inner) return;
        inner.innerHTML = type === 'image' ? '<img src="' + escAttr(url) + '" alt="">' : '<video controls autoplay src="' + escAttr(url) + '"></video>';
        lb.classList.remove('hidden');
    }
    function closeLightbox() {
        document.getElementById('group-chat-lightbox')?.classList.add('hidden');
        const inner = document.getElementById('group-chat-lightbox-inner');
        if (inner) inner.innerHTML = '';
    }
    function wireVoice() {
        const btn = document.getElementById('group-chat-voice');
        if (!btn) return;
        const reset = () => {
            isRecording = false;
            btn.classList.remove('recording');
        };
        const start = () => {
            if (isRecording || !navigator.mediaDevices) return;
            navigator.mediaDevices.getUserMedia({ audio: true }).then((stream) => {
                isRecording = true;
                audioChunks = [];
                btn.classList.add('recording');
                const mime = MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus' : '';
                mediaRecorder = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
                mediaRecorder.ondataavailable = (e) => audioChunks.push(e.data);
                mediaRecorder.onstop = () => stream.getTracks().forEach((t) => t.stop());
                mediaRecorder.start();
            }).catch(() => reset());
        };
        const stop = () => {
            if (!isRecording || !mediaRecorder) return;
            mediaRecorder.stop();
            reset();
            setTimeout(() => {
                if (!audioChunks.length) return;
                const mime = (mediaRecorder && mediaRecorder.mimeType) || 'audio/webm';
                const blob = new Blob(audioChunks, { type: mime.split(';')[0] });
                sendMessage(new File([blob], 'voice-' + Date.now() + '.weba', { type: mime.split(';')[0] }));
                audioChunks = [];
            }, 80);
        };
        btn.addEventListener('mousedown', (e) => { e.preventDefault(); start(); });
        btn.addEventListener('mouseup', (e) => { e.preventDefault(); stop(); });
        btn.addEventListener('mouseleave', () => { if (isRecording) { mediaRecorder?.stop(); reset(); audioChunks = []; } });
        btn.addEventListener('touchstart', (e) => { e.preventDefault(); start(); }, { passive: false });
        btn.addEventListener('touchend', (e) => { e.preventDefault(); stop(); }, { passive: false });
    }
    function wireResize() {
        const handle = document.getElementById('group-chat-resize');
        const handleH = document.getElementById('group-chat-resize-h');
        const el = panel();
        if (!handle || !el) return;
        const startPointerDrag = (cursor, onMove, onEnd) => {
            if (typeof window.cloudPointerDragStart === 'function') {
                window.cloudPointerDragStart(cursor, onMove, onEnd);
                return;
            }
            const finish = (ev) => {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', finish);
                document.body.style.userSelect = '';
                onEnd?.(ev);
            };
            document.body.style.userSelect = 'none';
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', finish);
        };
        handle.addEventListener('mousedown', (e) => {
            e.preventDefault();
            const startX = e.clientX;
            const startY = e.clientY;
            const startW = state.detached ? state.detachedW : state.width;
            const startH = state.detachedH;
            const onMove = (ev) => {
                const dx = ev.clientX - startX;
                const dy = ev.clientY - startY;
                if (state.detached) {
                    state.detachedW = Math.max(280, Math.min(720, startW + dx));
                    state.detachedH = Math.max(280, Math.min(720, startH + dy));
                } else {
                    state.width = Math.max(260, Math.min(560, startW - dx));
                }
                applyLayout();
            };
            const onUp = () => {
                savePrefs();
            };
            startPointerDrag(state.detached ? 'nwse-resize' : 'ew-resize', onMove, onUp);
        });
        if (handleH) {
            handleH.addEventListener('mousedown', (e) => {
                if (!state.detached) return;
                e.preventDefault();
                e.stopPropagation();
                const startY = e.clientY;
                const startH = state.detachedH;
                const onMove = (ev) => {
                    state.detachedH = Math.max(280, Math.min(720, startH + (ev.clientY - startY)));
                    applyLayout();
                };
                const onUp = () => {
                    savePrefs();
                };
                startPointerDrag('ns-resize', onMove, onUp);
            });
        }
        const dragHandle = document.getElementById('group-chat-drag-handle');
        if (dragHandle) {
            if (!dragHandle.querySelector('.group-chat-header-resize-zone')) {
                dragHandle.insertAdjacentHTML('afterbegin', '<div class="group-chat-header-resize-zone" title="Resize"></div>');
            }
            const headerResize = dragHandle.querySelector('.group-chat-header-resize-zone');
            if (headerResize) {
                headerResize.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const startX = e.clientX;
                    const startY = e.clientY;
                    const startW = state.detached ? state.detachedW : state.width;
                    const startH = state.detachedH;
                    const onMove = (ev) => {
                        if (state.detached) {
                            state.detachedW = Math.max(280, Math.min(720, startW + (ev.clientX - startX)));
                            state.detachedH = Math.max(280, Math.min(720, startH + (ev.clientY - startY)));
                        } else {
                            state.width = Math.max(260, Math.min(560, startW - (ev.clientX - startX)));
                        }
                        applyLayout();
                    };
                    const onUp = () => {
                        savePrefs();
                    };
                    startPointerDrag(state.detached ? 'nwse-resize' : 'ew-resize', onMove, onUp);
                });
            }
            let dragX, dragY, origX, origY;
            dragHandle.addEventListener('mousedown', (e) => {
                if (!state.detached || e.target.closest('button') || e.target.closest('select')) return;
                e.preventDefault();
                dragX = e.clientX;
                dragY = e.clientY;
                origX = state.detachedX ?? el.offsetLeft;
                origY = state.detachedY ?? el.offsetTop;
                const onMove = (ev) => {
                    state.detachedX = Math.max(0, origX + (ev.clientX - dragX));
                    state.detachedY = Math.max(40, origY + (ev.clientY - dragY));
                    el.style.left = state.detachedX + 'px';
                    el.style.top = state.detachedY + 'px';
                };
                const onUp = () => {
                    savePrefs();
                };
                startPointerDrag('move', onMove, onUp);
            });
        }
    }
    function injectChatStyles() {
        if (document.getElementById('gchat-audio-styles')) return;
        const style = document.createElement('style');
        style.id = 'gchat-audio-styles';
        style.textContent = `
            .group-chat-media--audio {
                max-width: min(300px, 100%);
            }
            .group-chat-media--audio .custom-audio-player:not(.cap-voice-message) {
                padding: 6px 10px;
                border-radius: 8px;
                min-height: 40px;
                gap: 6px;
                background: color-mix(in srgb, var(--bg, #1a1a1a) 70%, var(--panel, #2a2a2a));
            }
            .group-chat-media--audio .cap-voice-message {
                width: min(300px, calc(100vw - 96px));
            }
            .group-chat-row--own .group-chat-media--audio .cap-voice-message {
                background: color-mix(in srgb, var(--accent, #85b503) 18%, var(--panel, #2a2a2a));
                box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--accent, #85b503) 24%, transparent);
            }
            .group-chat-media--audio .custom-audio-player:not(.cap-voice-message) .cap-btn {
                width: 28px;
                height: 28px;
                border-radius: 6px;
            }
            .group-chat-media--audio .custom-audio-player:not(.cap-voice-message) .cap-time {
                font-size: 10px;
                min-width: 28px;
            }
            .group-chat-media--audio .custom-audio-player:not(.cap-voice-message) .cap-seek-bar {
                height: 28px;
                border-radius: 8px;
            }
            .group-chat-media--audio .custom-audio-player .cap-wave-bar {
                min-width: 1.5px;
            }
            .group-chat-media--audio .custom-audio-player .cap-vol-slider {
                display: none !important;
            }
            .group-chat-delete-btn {
                color: var(--muted, #888);
                transition: color 0.15s, background 0.15s, border-color 0.15s;
            }
            .group-chat-delete-btn:hover {
                color: var(--danger, #e74c3c);
                background: color-mix(in srgb, var(--danger, #e74c3c) 12%, transparent);
                border-color: color-mix(in srgb, var(--danger, #e74c3c) 40%, transparent);
            }
        `;
        document.head.appendChild(style);
    }
    function init() {
        loadPrefs();
        state.userEmail = (window.cloudUserEmail || '').toLowerCase();
        injectChatStyles();
        document.getElementById('btn-group-chat')?.addEventListener('click', togglePanel);
        document.getElementById('group-chat-close')?.addEventListener('click', closePanel);
        document.getElementById('group-chat-detach')?.addEventListener('click', toggleDetach);
        document.getElementById('group-chat-delete')?.addEventListener('click', deleteChat);
        document.getElementById('group-chat-send')?.addEventListener('click', () => sendMessage());
        inputEl()?.addEventListener('keydown', (e) => {
            if (isMentionSuggestionsVisible()) {
                if (e.key === 'ArrowDown') { e.preventDefault(); moveMentionSelection(1); return; }
                if (e.key === 'ArrowUp') { e.preventDefault(); moveMentionSelection(-1); return; }
                if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); applyMentionSuggestion(state.mentionSelectedIndex); return; }
                if (e.key === 'Escape') { e.preventDefault(); hideMentionSuggestions(); return; }
            }
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
        });
        inputEl()?.addEventListener('input', () => { handleMentionInput(); });
        mentionsEl()?.addEventListener('click', (e) => {
            const removeBtn = e.target.closest('.group-chat-mention-chip-remove');
            if (removeBtn) {
                e.preventDefault();
                removeMentionFile(removeBtn.dataset.path || '');
                return;
            }
            const openBtn = e.target.closest('.group-chat-mention-chip-open');
            if (openBtn) {
                e.preventDefault();
                openMentionFile(openBtn.dataset.path || '');
            }
        });
        const composeRow = document.querySelector('.group-chat-compose-row');
        if (composeRow && composeRow.dataset.treeDropBound !== '1') {
            composeRow.dataset.treeDropBound = '1';
            const clearDrop = () => composeRow.classList.remove('group-chat-tree-drop-active');
            composeRow.addEventListener('dragenter', (event) => {
                if (!getTreeFilePathFromDragEvent(event)) return;
                event.preventDefault();
                composeRow.classList.add('group-chat-tree-drop-active');
            });
            composeRow.addEventListener('dragover', (event) => {
                if (!getTreeFilePathFromDragEvent(event)) return;
                event.preventDefault();
                event.dataTransfer.dropEffect = 'copy';
                composeRow.classList.add('group-chat-tree-drop-active');
            });
            composeRow.addEventListener('dragleave', (event) => {
                if (composeRow.contains(event.relatedTarget)) return;
                clearDrop();
            });
            composeRow.addEventListener('drop', (event) => {
                const path = getTreeFilePathFromDragEvent(event);
                if (!path) return;
                event.preventDefault();
                event.stopPropagation();
                clearDrop();
                addMentionFile(path);
            });
        }
        document.getElementById('group-chat-upload')?.addEventListener('click', () => fileInput()?.click());
        fileInput()?.addEventListener('change', function () {
            if (!this.files || !this.files[0]) return;
            if (!isAllowedMediaFile(this.files[0])) {
                if (typeof flashStatus === 'function') flashStatus(t('groupChatMediaOnly'));
                this.value = '';
                return;
            }
            sendMessage(this.files[0]);
            this.value = '';
        });
        groupSelect()?.addEventListener('change', function () {
            state.groupId = this.value;
            savePrefs();
            state.messages = [];
            state.maxId = 0;
            showMessagesLoading();
            loadMessages(false);
            updateDeleteButton();
        });
        messagesEl()?.addEventListener('scroll', function () {
            if (this.scrollTop < 100 && state.hasMoreOlder) loadOlder();
        });
        document.getElementById('group-chat-context-edit')?.addEventListener('click', () => {
            const id = +(document.getElementById('group-chat-context-menu')?.dataset.msgId || 0);
            hideContextMenu();
            if (id) startEdit(id);
        });
        document.getElementById('group-chat-context-delete')?.addEventListener('click', () => {
            const id = +(document.getElementById('group-chat-context-menu')?.dataset.msgId || 0);
            hideContextMenu();
            if (id) deleteMessage(id);
        });
        document.addEventListener('click', hideContextMenu);
        document.getElementById('group-chat-lightbox-close')?.addEventListener('click', closeLightbox);
        document.getElementById('group-chat-lightbox')?.addEventListener('click', (e) => {
            if (e.target.id === 'group-chat-lightbox') closeLightbox();
        });
        wireVoice();
        wireResize();
        updateDeleteButton();
        applyLayout();
        if (state.open) showMessagesLoading();
        if (state.open && groups().length) restoreOpenPanel();
        else updateChatButton(0);
        refreshUnread();
        setInterval(refreshUnread, 30000);
    }
    window.cloudGroupChat = {
        refreshUnread,
        updateChatButton,
        onGroupsLoaded() {
            if (state.groupId && !groups().some((g) => g.id === state.groupId)) {
                state.groupId = null;
                state.messages = [];
                state.maxId = 0;
                state.minId = null;
                savePrefs();
            }
            renderGroupSelect();
            refreshUnread();
            if (!groups().length) {
                if (state.open) { state.open = false; savePrefs(); }
                closePanel();
                updateChatButton(0);
                return;
            }
            if (state.open) restoreOpenPanel();
            else {
                applyLayout();
                updateChatButton(0);
            }
        }
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
