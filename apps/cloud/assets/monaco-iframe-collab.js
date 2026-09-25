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
    let sockReady = false;
    let pending = [];
    let clientId = '';
    const remoteColors = new Map();
    const cursorWidgets = new Map(); // clientId -> widget
    let styleInjected = false;

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
                if (cfg && cfg.collabWsUrl) {
                    const u = new URL(cfg.collabWsUrl, window.parent.location.origin);
                    u.protocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
                    return u.href.replace(/\/$/, '');
                }
            }
        } catch (_) { /* cross-origin parent – fall through */ }
        const proto = location.protocol === 'https:' ? 'wss:' : 'ws:';
        // iframe editors are served from utilities/projects/... under the cloud app
        const seg = location.pathname.split('/');
        const idx = seg.indexOf('utilities');
        const base = idx > 0 ? seg.slice(0, idx).join('/') : '';
        return `${proto}//${location.host}${base}/cloud-collab-ws`;
    }

    function connect(explicit) {
        if (sock && (sock.readyState === WebSocket.OPEN || sock.readyState === WebSocket.CONNECTING)) return;
        try { sock = new WebSocket(wsUrl(explicit)); } catch (e) { setTimeout(() => connect(explicit), 2000); return; }
        sock.onopen = () => {
            sockReady = true;
            if (state) send({ type: 'join', ...joinPayload() });
            flush();
        };
        sock.onmessage = (ev) => {
            let msg = null;
            try { msg = JSON.parse(ev.data); } catch (_) { return; }
            if (!msg || !msg.type) return;
            if (msg.clientId === clientId) return;
            if (!state || msg.roomKey !== roomKey()) return;
            if (msg.type === 'sync' || msg.type === 'content' || msg.type === 'content_snapshot') {
                if (typeof msg.content === 'string' && state.onRemoteContent) {
                    state.applying = true;
                    try { state.onRemoteContent(msg.content); } finally { state.applying = false; }
                }
                return;
            }
            if (msg.type === 'cursor') { setCursor(msg.clientId, msg.position, msg.name); return; }
            if (msg.type === 'leave') { removeCursor(msg.clientId); return; }
            if (msg.type === 'latex_compiling' && state.onCompiling) { state.onCompiling(msg); return; }
            if (msg.type === 'latex_compiled' && state.onRemoteCompile) { state.onRemoteCompile(msg); return; }
        };
        sock.onclose = () => { sockReady = false; sock = null; setTimeout(() => connect(explicit), 1500); };
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
        try {
            const pos = state.editor.getModel().validatePosition(position);
            const coords = state.editor.getScrolledVisiblePosition(pos);
            if (!coords) { w.node.style.display = 'none'; return; }
            const lh = state.editor.getOption(monaco.editor.EditorOption.lineHeight);
            // Badge goes BELOW the caret on the first line so it is not clipped
            const labelTop = pos.lineNumber <= 1 ? '0.2em' : '-1.4em';
            w.node.style.display = '';
            w.node.style.left = coords.left + 'px';
            w.node.style.top = coords.top + 'px';
            w.caret.style.background = color;
            w.label.style.background = color;
            w.label.style.top = labelTop;
            w.label.textContent = name || 'user';
        } catch (_) { /* editor disposed */ }
    }

    function removeCursor(rcId) {
        const w = cursorWidgets.get(rcId);
        if (w) { w.node.remove(); cursorWidgets.delete(rcId); }
    }

    function clearAllCursors() { cursorWidgets.forEach((w) => w.node.remove()); cursorWidgets.clear(); }

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
            applying: false
        };
        remoteColors.clear();
        clearAllCursors();
        connect(opts.wsUrl);

        let lastSent = 0;
        state.disposables = [
            opts.editor.onDidChangeModelContent(() => {
                if (state.applying) return;
                const now = Date.now();
                if (now - lastSent < 250) return;
                lastSent = now;
                send({ type: 'content', roomKey: roomKey(), path: state.filePath, root: state.root, clientId, content: state.getLocalContent ? state.getLocalContent() : '' });
            }),
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
            send({ type: 'leave', roomKey: roomKey(), clientId });
            state = null;
        }
        clearAllCursors();
    }

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
