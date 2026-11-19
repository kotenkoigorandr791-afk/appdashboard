// assets/monaco-iframe-collab.js
// Shared realtime-collab module for iframe editors (markdown / latex / html).
// Connects to the same /cloud-collab-ws tunnel as the main Monaco editor,
// shows remote cursors with the same name badges, and broadcasts LaTeX
// compilations so every viewer's PDF preview refreshes.
//
// Usage inside the iframe page:
//   window.cloudIframeCollab.enable({
//       editor,               // monaco editor instance
//       container,            // DOM element hosting the editor
//       filePath,             // cloud-relative file path (from URL ?path=)
//       root,                 // cloud root (from URL ?root=)
//       user,                 // current user name (from URL ?user=)
//       userName,             // display name
//       wsUrl,                // optional explicit WS url
//       onRemoteContent(content),       // apply remote snapshot
//       getLocalContent(),              // read current editor text
//       onRemoteCompile(info),          // latex: re-render pdf preview
//       onCompiling(info)               // latex: show "compiling..." state
//   });
(function () {
    'use strict';

    const COLORS = ['#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#ec4899', '#14b8a6', '#a855f7', '#f97316'];

    let state = null; // current session
    let sock = null;
    let sockReady = false; // true only after the server accepts the ticket
    let pending = [];
    let retries = 0;
    let clientId = '';
    const remoteColors = new Map();
    const cursorWidgets = new Map(); // clientId -> widget
    let styleInjected = false;

    // URL of this module. Editor pages are served from utilities/projects/*/public
    // and can also be opened standalone (no cloud shell parent), so the cloud API
    // endpoint is derived from the script location instead of the page location.
    const scriptUrl = (function () {
        try {
            if (document.currentScript && document.currentScript.src) return document.currentScript.src;
            const el = document.querySelector('script[src*="monaco-iframe-collab.js"]');
            return el ? el.src : '';
        } catch (_) { return ''; }
    })();

    function cloudApiUrl() {
        try {
            if (scriptUrl) return new URL('../api.php', scriptUrl).href;
        } catch (_) { /* fall through */ }
        return new URL('api.php', location.href).href;
    }

    function colorForClient(id) {
        if (remoteColors.has(id)) return remoteColors.get(id);
        let hash = 0;
        const s = String(id);
        for (let i = 0; i < s.length; i++) hash = ((hash << 5) - hash) + s.charCodeAt(i);
        const c = COLORS[Math.abs(hash) % COLORS.length];
        remoteColors.set(id, c);
        return c;
    }

    function ensureStyles() {
        if (styleInjected) return;
        styleInjected = true;
        const st = document.createElement('style');
        st.textContent = [
            '.icw-root{position:absolute;pointer-events:none;z-index:60;}',
            '.icw-caret{width:2px;height:1.2em;margin-left:-1px;}',
            '.icw-label{position:absolute;left:0;color:#fff;border-radius:999px;padding:1px 6px;margin-left:4px;font-size:11px;line-height:16px;white-space:nowrap;box-shadow:0 1px 4px rgba(0,0,0,.25);}',
            '.icw-flash{animation:icwFlash 1.6s ease-out;}',
            '@keyframes icwFlash{0%{background:var(--icw-c,transparent)}100%{background:transparent}}'
        ].join('');
        document.head.appendChild(st);
    }

    function wsUrl(explicit) {
        if (explicit) return explicit;
        try {
            if (window.parent && window.parent !== window) {
                const cfg = window.parent.window.APP_CONFIG;
                if (cfg && cfg.collabWsUrl) return new URL(cfg.collabWsUrl, window.parent.location.origin).href.replace(/\/$/, '');
            }
        } catch (_) { /* cross-origin parent – fall through */ }
        const proto = location.protocol === 'https:' ? 'wss:' : 'ws:';
        return `${proto}//${location.host}/cloud-collab-ws`;
    }

    async function fetchTicket() {
        // Inside the cloud shell, reuse its API helper so the request carries the
        // same headers as the shell itself. When the editor page is opened on its
        // own there is no parent helper, so ask the cloud API directly (the
        // session cookies are the same origin).
        try {
            const parentApi = window.parent && window.parent !== window ? window.parent.api : null;
            if (typeof parentApi === 'function') {
                const result = await parentApi('monaco_collab_ticket', {}, 'POST');
                if (result?.ok && result.ticket) return result.ticket;
            }
        } catch (_) { /* cross-origin parent or helper failure – ask directly */ }
        const res = await fetch(cloudApiUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'monaco_collab_ticket' })
        });
        const text = await res.text();
        let data = null;
        try { data = text ? JSON.parse(text) : null; } catch (_) {}
        if (res.ok && data?.ok && data.ticket) return data.ticket;
        throw new Error(data?.error || `Collaboration ticket request failed (${res.status})`);
    }

    let heartbeatTimer = null;
    let lastMessageAt = 0;

    function startHeartbeat() {
        stopHeartbeat();
        lastMessageAt = Date.now();
        // Detect half-open connections (sleep, network change) that never fire close.
        heartbeatTimer = setInterval(() => {
            if (!sock || sock.readyState !== WebSocket.OPEN || !sockReady) return;
            if (Date.now() - lastMessageAt > 65000) {
                try { sock.close(); } catch (_) {}
                return;
            }
            try { sock.send(JSON.stringify({ type: 'ping', at: Date.now() })); } catch (_) {}
        }, 20000);
    }

    function stopHeartbeat() {
        if (heartbeatTimer) { clearInterval(heartbeatTimer); heartbeatTimer = null; }
    }

    function connect(explicit) {
        if (sock && (sock.readyState === WebSocket.OPEN || sock.readyState === WebSocket.CONNECTING)) return;
        try { sock = new WebSocket(wsUrl(explicit)); } catch (_) { setTimeout(() => connect(explicit), 2000); return; }
        sock.onopen = async () => {
            retries = 0;
            try {
                const ticket = await fetchTicket();
                if (sock && sock.readyState === WebSocket.OPEN) sock.send(JSON.stringify({ type: 'auth', ticket }));
            } catch (error) {
                console.error('Iframe collaboration auth ticket failed:', error);
                try { sock?.close(); } catch (_) {}
            }
        };
        sock.onmessage = (ev) => {
            lastMessageAt = Date.now();
            let msg = null;
            try { msg = JSON.parse(ev.data); } catch (_) { return; }
            if (!msg || !msg.type) return;
            if (msg.type === 'pong') return;
            if (msg.type === 'auth_ok') {
                sockReady = true;
                startHeartbeat();
                // Join and pull the current room state, so a client that joined
                // late or reconnected catches up without reloading the file.
                if (state) {
                    send({ type: 'join', ...joinPayload() });
                    send({ type: 'sync_request', roomKey: roomKey(), clientId });
                }
                flush();
                return;
            }
            if (msg.type === 'auth_error') { try { sock?.close(); } catch (_) {} return; }
            if (!state || msg.roomKey !== roomKey()) return;
            if (msg.clientId === clientId) return;
            if (msg.type === 'sync' || msg.type === 'content' || msg.type === 'content_snapshot') {
                if (typeof msg.content === 'string' && state.onRemoteContent) {
                    const local = state.getLocalContent ? state.getLocalContent() : null;
                    // Do not overwrite the buffer of the person who is typing right
                    // now: a stale echo used to truncate their text mid-word. They
                    // converge again when typing pauses (see the resync timer).
                    if (local !== null && local !== msg.content && document.hasFocus() && Date.now() - (state.lastLocalInputAt || 0) < 1500) {
                        return;
                    }
                    state.applying = true;
                    state.lastRemoteContent = msg.content;
                    try { state.onRemoteContent(msg.content); } finally { state.applying = false; }
                }
                return;
            }
            if (msg.type === 'cursor') { setCursor(msg.clientId, msg.position, msg.name); return; }
            if (msg.type === 'leave') { removeCursor(msg.clientId); return; }
            if (msg.type === 'latex_compiling' && state.onCompiling) { state.onCompiling(msg); return; }
            if (msg.type === 'latex_compiled' && state.onRemoteCompile) { state.onRemoteCompile(msg); return; }
        };
        sock.onclose = () => { stopHeartbeat(); sockReady = false; sock = null; retries = Math.min(retries + 1, 6); setTimeout(() => connect(explicit), 1500 * retries); };
        sock.onerror = () => { try { sock && sock.close(); } catch (_) {} };
    }

    function joinPayload() {
        return {
            roomKey: roomKey(),
            path: state.filePath,
            root: state.root,
            clientId,
            name: state.userName || state.user || 'user',
            color: state.color,
            content: state.getLocalContent ? state.getLocalContent() : '',
            revision: 0,
            updatedAt: Date.now()
        };
    }

    function roomKey() {
        return `${state.root || 'main'}::${state.filePath || ''}`;
    }

    function send(obj) {
        if (sockReady && sock && sock.readyState === WebSocket.OPEN) sock.send(JSON.stringify(obj));
        else { pending.push(obj); }
    }
    function flush() { while (pending.length && sockReady) sock.send(JSON.stringify(pending.shift())); }

    // ---- remote cursors -------------------------------------------------
    function ensureRoot(container) {
        let root = container.querySelector('.icw-root');
        if (!root) {
            root = document.createElement('div');
            root.className = 'icw-root';
            root.style.cssText = 'top:0;left:0;width:100%;height:100%;overflow:hidden;';
            const dom = container.querySelector('.monaco-editor') || container;
            if (dom && getComputedStyle(dom).position === 'static') dom.style.position = 'relative';
            (dom === container ? container : dom).appendChild(root);
        }
        return root;
    }

    // Last known position of every remote cursor. The badge is drawn inside the
    // editor container, so it has to be re-projected whenever the text scrolls,
    // the editor is resized or local edits shift lines; otherwise it stays
    // frozen on screen while the text moves under it.
    const cursorPositions = new Map();
    let repositionRaf = 0;

    function positionCursor(rcId) {
        if (!state) return;
        const entry = cursorPositions.get(rcId);
        const w = cursorWidgets.get(rcId);
        if (!entry || !w || !state.editor.getModel()) return;
        try {
            const pos = state.editor.getModel().validatePosition(entry.position);
            const coords = state.editor.getScrolledVisiblePosition(pos);
            if (!coords) { w.node.style.display = 'none'; return; }
            // Badge goes BELOW the caret on the first line so it is not clipped
            const labelTop = pos.lineNumber <= 1 ? '0.2em' : '-1.4em';
            w.node.style.display = '';
            w.node.style.left = coords.left + 'px';
            w.node.style.top = coords.top + 'px';
            w.label.style.top = labelTop;
        } catch (_) { /* editor disposed */ }
    }

    function repositionAllCursors() {
        cursorPositions.forEach((_entry, rcId) => positionCursor(rcId));
    }

    function scheduleReposition() {
        if (repositionRaf) return;
        repositionRaf = requestAnimationFrame(() => {
            repositionRaf = 0;
            repositionAllCursors();
        });
    }

    function setCursor(rcId, position, name) {
        if (!state || !position) return;
        ensureStyles();
        const container = state.container;
        const root = ensureRoot(container);
        const color = colorForClient(rcId);
        let w = cursorWidgets.get(rcId);
        if (!w) {
            const node = document.createElement('div');
            node.className = 'icw-root-item';
            node.style.cssText = 'position:absolute;pointer-events:none;';
            const caret = document.createElement('div');
            caret.className = 'icw-caret';
            caret.style.background = color;
            const label = document.createElement('div');
            label.className = 'icw-label';
            label.style.background = color;
            label.textContent = name || 'user';
            node.appendChild(caret); node.appendChild(label);
            root.appendChild(node);
            w = { node, caret, label };
            cursorWidgets.set(rcId, w);
        }
        w.caret.style.background = color;
        w.label.style.background = color;
        w.label.textContent = name || 'user';
        cursorPositions.set(rcId, { position: { lineNumber: position.lineNumber, column: position.column } });
        positionCursor(rcId);
    }

    function removeCursor(rcId) {
        cursorPositions.delete(rcId);
        const w = cursorWidgets.get(rcId);
        if (w) { w.node.remove(); cursorWidgets.delete(rcId); }
    }

    function clearAllCursors() {
        cursorPositions.clear();
        cursorWidgets.forEach((w) => w.node.remove());
        cursorWidgets.clear();
    }

    // Participant names are shown only by the label that follows each remote
    // cursor; the old top-right presence badges were removed.

    // ---- public API -----------------------------------------------------
    function enable(opts) {
        if (!opts || !opts.editor || typeof monaco === 'undefined') return null;
        disable();
        clientId = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : ('c' + Date.now() + Math.random().toString(16).slice(2));
        state = {
            editor: opts.editor,
            container: opts.container,
            filePath: String(opts.filePath || ''),
            root: String(opts.root || 'main'),
            user: String(opts.user || 'default'),
            userName: String(opts.userName || opts.user || 'user'),
            color: COLORS[Math.abs(hashStr(String(opts.filePath))) % COLORS.length],
            onRemoteContent: typeof opts.onRemoteContent === 'function' ? opts.onRemoteContent : null,
            getLocalContent: typeof opts.getLocalContent === 'function' ? opts.getLocalContent : null,
            onRemoteCompile: typeof opts.onRemoteCompile === 'function' ? opts.onRemoteCompile : null,
            onCompiling: typeof opts.onCompiling === 'function' ? opts.onCompiling : null,
            applying: false,
            lastRemoteContent: null,
            lastLocalInputAt: 0,
            resyncTimer: null,
            wsUrl: opts.wsUrl,
            snapshotTimer: null
        };
        remoteColors.clear();
        clearAllCursors();
        connect(opts.wsUrl);
        // connect() reuses an already authenticated socket (the editor can be
        // enabled again for another file); in that case no new auth_ok arrives,
        // so join the room right away instead of waiting forever.
        if (sockReady) {
            send({ type: 'join', ...joinPayload() });
            flush();
        }

        const pushSnapshot = () => {
            if (!state) return;
            // Never broadcast content that was just received from the room: an
            // echo of another client's (possibly partial) snapshot overwrote
            // the author's buffer while they were still typing.
            const content = state.getLocalContent ? state.getLocalContent() : '';
            if (content === state.lastRemoteContent) return;
            // A snapshot (not a transient diff) keeps the room state fresh for
            // users who join the file later.
            send({ type: 'content_snapshot', roomKey: roomKey(), path: state.filePath, root: state.root, clientId, content, revision: 0, updatedAt: Date.now() });
        };
        state.disposables = [
            opts.editor.onDidChangeModelContent(() => {
                if (!state || state.applying) return;
                // Remote content may be applied asynchronously by the host page,
                // after state.applying was reset; compare with the last content
                // received from the room so its echo is not broadcast back.
                const localContent = state.getLocalContent ? state.getLocalContent() : null;
                if (localContent !== null && localContent === state.lastRemoteContent) return;
                state.lastRemoteContent = null;
                state.lastLocalInputAt = Date.now();
                // When typing pauses, pull the room state once so all clients
                // converge even if a remote update was skipped while typing.
                if (state.resyncTimer) clearTimeout(state.resyncTimer);
                state.resyncTimer = setTimeout(() => {
                    if (!state) return;
                    state.resyncTimer = null;
                    if (sockReady) send({ type: 'sync_request', roomKey: roomKey(), clientId });
                }, 2000);
                // Debounce instead of throttling: a throttle dropped the trailing
                // keystrokes, so other clients were stuck on a truncated snapshot.
                if (state.snapshotTimer) clearTimeout(state.snapshotTimer);
                state.snapshotTimer = setTimeout(() => {
                    if (!state) return;
                    state.snapshotTimer = null;
                    pushSnapshot();
                }, 120);
            }),
            opts.editor.onDidScrollChange(scheduleReposition),
            opts.editor.onDidLayoutChange(scheduleReposition),
            opts.editor.onDidChangeModelContent(scheduleReposition),
            opts.editor.onDidChangeCursorPosition((e) => {
                if (state.applying || !e || !e.position) return;
                const now = Date.now();
                if (now - (state.lastCursor || 0) < 120) return;
                state.lastCursor = now;
                send({ type: 'cursor', roomKey: roomKey(), path: state.filePath, root: state.root, clientId, name: state.userName, color: state.color, position: e.position });
            })
        ];
        return state;
    }

    function disable() {
        if (state) {
            (state.disposables || []).forEach((d) => { try { d.dispose(); } catch (_) {} });
            if (state.snapshotTimer) clearTimeout(state.snapshotTimer);
            send({ type: 'leave', roomKey: roomKey(), clientId });
            state = null;
        }
        if (repositionRaf) { cancelAnimationFrame(repositionRaf); repositionRaf = 0; }
        if (repositionRaf) { cancelAnimationFrame(repositionRaf); repositionRaf = 0; }
        clearAllCursors();
    }

    // A user coming back to the tab (or regaining the network) must catch up with
    // the room instead of reloading the file.
    function resume() {
        if (!state) return;
        connect(state.wsUrl);
        if (sockReady && sock && sock.readyState === WebSocket.OPEN) {
            send({ type: 'join', ...joinPayload() });
            send({ type: 'sync_request', roomKey: roomKey(), clientId });
        }
    }
    window.addEventListener('online', resume);
    window.addEventListener('focus', resume);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) resume();
    });

    // Broadcast that a LaTeX compile finished; viewers re-render the PDF.
    function notifyLatexCompiled(info) {
        if (!state) return;
        send({ type: 'latex_compiled', roomKey: roomKey(), path: state.filePath, root: state.root, clientId, name: state.userName, info: info || {}, at: Date.now() });
    }
    function notifyLatexCompiling() {
        if (!state) return;
        send({ type: 'latex_compiling', roomKey: roomKey(), path: state.filePath, root: state.root, clientId, name: state.userName, at: Date.now() });
    }

    function hashStr(s) {
        let h = 0;
        for (let i = 0; i < s.length; i++) h = ((h << 5) - h) + s.charCodeAt(i);
        return h;
    }

    window.cloudIframeCollab = {
        enable,
        disable,
        notifyLatexCompiled,
        notifyLatexCompiling,
        getSession: () => state
    };
})();
