// assets/ai-assistant.js
// AI Assistant Module for File Space - FIXED VERSION

(function() {
    'use strict';

    // Configuration
    const AI_CONFIG = {
        apiEndpoint: window.APP_CONFIG?.aiApiBase || 'ai.php',
        fileApiEndpoint: window.APP_CONFIG?.apiBase || 'api.php',
        defaultModel: 'github/gpt-4o-mini',
        defaultRole: 'general',
        configuredModels: null,
        directApi: {
            configured: false
        }
    };
    const LOCAL_HISTORY_KEY = 'cloud_ai_chat_history_v2';

    const CONTEXT_LIMITS = {
        maxMentionedFiles: 8,
        maxMentionedChars: 45000,
        agentOpenFileChars: 8000,
        agentContextTokenBudget: 200000
    };
    function truncateForAgentContext(text, maxChars, label) {
        const s = String(text || '');
        if (s.length <= maxChars) return s;
        return s.slice(0, maxChars) + `\n\n[... ${label || 'content'} truncated for 200k token budget; use read_file with offset_line or search_in_files ...]`;
    }

    const MAX_EXTERNAL_ATTACHMENTS = 4;
    let aiTreeDragFallbackPaths = [];
    const AI_TEXT_FILE_ACCEPT =
        'text/plain,text/css,text/html,text/xml,text/csv,text/markdown,text/x-yaml,application/json,application/javascript,application/xml,.txt,.md,.markdown,.json,.csv,.xml,.html,.htm,.css,.js,.mjs,.cjs,.ts,.tsx,.jsx,.vue,.php,.py,.rb,.go,.java,.cs,.c,.cc,.cpp,.h,.hpp,.sh,.yaml,.yml,.sql,.log,.ini,.toml,.cfg,.env,.rst,.tex,.bat,.ps1,.properties,.gradle,.kt,.swift,.rs,.lua,.pl,.r,.dockerfile';
    /** Photos only (no SVG/heic/etc.); picker + runtime check via isAllowedImageFile */
    const AI_IMAGE_ACCEPT = 'image/jpeg,image/png,image/gif,image/webp,image/bmp,image/tiff';
    const AI_DOCUMENT_ACCEPT =
        'application/pdf,.pdf,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,.xls,.xlsx,.ods,application/vnd.oasis.opendocument.spreadsheet';
    const AI_EXTRACTABLE_EXT = new Set(['pdf', 'xls', 'xlsx', 'ods', 'ppt', 'pptx']);

    const MENTION_CONFIG = {
        maxSuggestions: 14,
        folderCacheMs: 15000
    };
    const AI_MODAL_WIDTH = {
        default: 33,
        minPx: 360,
        maxPx: 1200,
        storageKey: 'ai_modal_width_px'
    };

    // Role configurations
    const roleConfigs = {
        general: { 
            system: "You are a helpful assistant. Provide clear, concise responses. If providing code, format it properly and make it easy to copy."
        },
        programmer: { 
            system: "You are an expert programmer. Provide code solutions, debugging help, and programming advice. Format code with language specification and explanations."
        },
        analyst: { 
            system: "You are a data analyst. Help analyze data, create reports, and provide insights. Use tables for structured data when appropriate."
        }
    };

    // State
    let aiState = {
        chatHistory: [],
        isGenerating: false,
        currentMessageId: null,
        abortController: null,
        currentFileContent: null,
        currentFileName: null,
        lastMessageStats: null,
        isLoadingHistory: false,
        includeFileContext: false,
        /** @type {Array<{ id: string, kind: 'text'|'image', name: string, content: string, file?: File, objectUrl?: string|null, ocrConfidence?: number|null, ocrLang?: string, ocrInProgress?: boolean, autoProcessQueued?: boolean, autoProcessDone?: boolean, _ocrGen?: number, _ocrUiPct?: number }>} */
        externalAttachments: [],
        ocrModalAttachmentId: null,
        ocrLang: 'auto',
        selectedModel: null,
        selectedRole: null,
        processedMessageIds: new Set(), // Track processed message IDs to prevent duplicates
        mentionSuggestions: [],
        mentionSelectedIndex: 0,
        mentionQuery: '',
        mentionRange: null,
        selectedMentionFiles: [],
        mentionedFileRoots: {},
        mentionFolderCache: {
            root: 'main',
            path: '',
            files: [],
            loadedAt: 0
        },
        mentionRequestId: 0,
        // Composer / UX state
        userDisplayName: null,
        rememberPrevious: false,
        notifyOnComplete: false,
        historyOffset: 0,
        historyHasMore: true,
        isLoadingMore: false,
        activeChatId: localStorage.getItem('cloud_ai_active_chat_id') || '',
        chatSessions: [],
        mentionPlacements: [], // [{ name, index }]
        chatMode: 'chat',
        pendingAgentPatch: null,
        pendingAgentPatches: {},
        lastAppliedAgentPatch: null,
        lastAgentUndo: null,
        agentPlanSteps: [],
        agentRunMessageId: null,
        /** @type {'success'|'error'|'stopped'|null} outcome of the finished tool-loop run */
        agentRunOutcome: null,
        agentUserRole: null,
        agentMentionPaths: [],
        agentNewPaths: new Set(),
        agentRunStartedAt: null,
        agentTimerInterval: null,
        serverRunJobId: '',
        serverRunMessageId: '',
        serverRunStopping: false,
        /** @type {'chat'|'agent'|null} UI labels for tool-loop runs */
        toolRunMode: null,
        editorHighlights: [],
        previewHighlightTabId: null
    };
    let modelDropdownState = { wrapper: null, button: null, menu: null, open: false };
    const unavailableModels = new Set();
    let autoOcrProcessTimer = null;

    // DOM Elements cache
    let elements = {};
    let aiModalResizeState = {
        active: false,
        startX: 0,
        startWidth: 0
    };
    let aiModalCloseTimer = null;
    let lastSavedUserSettingsSnapshot = '';

    function getUserSettingsPayload() {
        const hasAttachedFiles =
            aiState.externalAttachments.length > 0 ||
            (Array.isArray(aiState.selectedMentionFiles) && aiState.selectedMentionFiles.length > 0);
        return {
            selected_model: String(aiState.selectedModel || ''),
            selected_role: String(aiState.selectedRole || ''),
            chat_mode: String(aiState.chatMode || 'chat'),
            remember_previous: '0',
            notify_on_complete: aiState.notifyOnComplete ? '1' : '0',
            has_attached_files: hasAttachedFiles ? '1' : '0',
            attached_files_count: String(aiState.externalAttachments.length || 0),
            mentioned_files_count: String(Array.isArray(aiState.selectedMentionFiles) ? aiState.selectedMentionFiles.length : 0)
        };
    }

    function rememberCurrentUserSettingsSnapshot() {
        lastSavedUserSettingsSnapshot = JSON.stringify(getUserSettingsPayload());
    }

    function tr(key, fallback, params = null) {
        let text = (window.i18n && window.i18n[key]) || fallback || key;
        if (params && typeof text === 'string') {
            Object.keys(params).forEach((p) => {
                text = text.replace(new RegExp(`\\{${p}\\}`, 'g'), String(params[p]));
            });
        }
        return text;
    }

    function escapeRegExp(value) {
        return String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function logAiError(context, err) {
        if (err instanceof Error) {
            console.error(`[AI] ${context}: ${err.message}`, err.stack || '');
        } else {
            console.error(`[AI] ${context}:`, err);
        }
    }

    function isAiTabHiddenForNotify() {
        return document.visibilityState === 'hidden' || !document.hasFocus();
    }

    function getNotificationIconUrl() {
        const link = document.querySelector('link[rel="icon"]') || document.querySelector('link[rel="shortcut icon"]');
        const href = link?.href;
        if (!href) return undefined;
        try {
            return new URL(href, window.location.href).href;
        } catch (e) {
            return undefined;
        }
    }

    async function ensureBrowserNotificationPermission() {
        if (!('Notification' in window)) return false;
        if (Notification.permission === 'granted') return true;
        if (Notification.permission === 'denied') return false;
        try {
            const result = await Notification.requestPermission();
            return result === 'granted';
        } catch (e) {
            return false;
        }
    }

    async function setNotifyOnCompleteEnabled(enabled) {
        const want = !!enabled;
        if (!want) {
            aiState.notifyOnComplete = false;
            if (elements.notifyCheckbox) elements.notifyCheckbox.checked = false;
            saveSettings();
            return true;
        }
        const ok = await ensureBrowserNotificationPermission();
        if (!ok) {
            aiState.notifyOnComplete = false;
            if (elements.notifyCheckbox) elements.notifyCheckbox.checked = false;
            saveSettings();
            flashStatus(tr('aiNotifyPermissionDenied', 'Browser notifications are blocked'));
            return false;
        }
        aiState.notifyOnComplete = true;
        if (elements.notifyCheckbox) elements.notifyCheckbox.checked = true;
        saveSettings();
        return true;
    }

    function maybeShowAiCompletionNotification(ctx) {
        if (!aiState.notifyOnComplete || !ctx || ctx.skip) return;
        if (!isAiTabHiddenForNotify()) return;
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        const isAgent = ctx.mode === 'agent';
        const isError = ctx.outcome === 'error';
        let title = tr('aiNotifyCompleteTitle', 'AI assistant');
        let body;
        if (isError) {
            body = isAgent
                ? tr('aiNotifyAgentErrorBody', 'Agent finished with an error')
                : tr('aiNotifyChatErrorBody', 'Response failed');
        } else {
            body = isAgent
                ? tr('aiNotifyAgentCompleteBody', 'Agent finished the task')
                : tr('aiNotifyChatCompleteBody', 'Response is ready');
        }
        try {
            const n = new Notification(title, { body, icon: getNotificationIconUrl(), tag: 'ai-assistant-complete' });
            n.onclick = () => {
                try { window.focus(); } catch (e) {}
                n.close();
            };
        } catch (e) {}
    }

    function makeAttachmentId() {
        return 'a_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10);
    }

    function getAttachmentById(id) {
        return aiState.externalAttachments.find((a) => a.id === id) || null;
    }

    /** Tesseract may return 0–1, 0–100, or mis-scaled values; always end with 0–100 for display. */
    function normalizeOcrConfidence(raw) {
        if (raw == null || !Number.isFinite(Number(raw))) return null;
        let c = Number(raw);
        if (c < 0) return null;
        if (c <= 1) return Math.min(100, Math.round(c * 1000) / 10);
        if (c <= 100) return Math.round(c * 10) / 10;
        if (c <= 10000) {
            const d = c / 100;
            if (d <= 100) return Math.min(100, Math.round(d * 10) / 10);
        }
        while (c > 100 && c >= 1000) c = c / 100;
        if (c > 100) c = c / 100;
        return Math.min(100, Math.max(0, Math.round(c * 10) / 10));
    }

    function extractOcrConfidenceFromData(data) {
        if (!data || typeof data !== 'object') return null;
        const v = data.confidence;
        if (v != null && Number.isFinite(Number(v))) return Number(v);
        return null;
    }

    function isAllowedTextFile(file) {
        if (!file) return false;
        const t = (file.type || '').toLowerCase();
        if (t === 'application/octet-stream') {
            return /\.(txt|md|markdown|json|csv|xml|html?|css|js|mjs|cjs|ts|tsx|jsx|vue|php|py|rb|go|java|cs|c|cc|cpp|h|hpp|hxx|sh|yaml|yml|sql|log|ini|toml|cfg|env|rst|tex|bat|ps1|properties|gradle|kt|swift|rs|lua|pl|r|dockerfile)$/i.test(
                file.name || ''
            );
        }
        if (t.startsWith('text/')) return true;
        if (
            t === 'application/json' ||
            t === 'application/xml' ||
            t === 'application/javascript' ||
            t === 'application/x-yaml' ||
            t === 'application/sql' ||
            t === 'application/x-sh' ||
            t === 'application/x-httpd-php'
        ) {
            return true;
        }
        return /\.(txt|md|markdown|json|csv|xml|html?|css|js|mjs|cjs|ts|tsx|jsx|vue|php|py|rb|go|java|cs|c|cc|cpp|h|hpp|hxx|sh|yaml|yml|sql|log|ini|toml|cfg|env|rst|tex|bat|ps1|properties|gradle|kt|swift|rs|lua|pl|r|dockerfile)$/i.test(
            file.name || ''
        );
    }

    function isAllowedImageFile(file) {
        if (!file) return false;
        const t = (file.type || '').toLowerCase();
        if (t.startsWith('image/')) {
            return /^(image\/(jpeg|png|gif|webp|bmp|tiff|x-ms-bmp))$/i.test(t);
        }
        return /\.(jpe?g|png|gif|webp|bmp|tiff?)$/i.test(file.name || '');
    }
    function isExtractableDocumentExt(name) {
        const ext = String(name || '').split('.').pop().toLowerCase();
        return AI_EXTRACTABLE_EXT.has(ext);
    }
    function isAllowedDocumentFile(file) {
        if (!file) return false;
        const t = (file.type || '').toLowerCase();
        if (t === 'application/pdf' || t.includes('spreadsheet') || t.includes('excel') || t.includes('presentation') || t.includes('powerpoint')) {
            return isExtractableDocumentExt(file.name);
        }
        return isExtractableDocumentExt(file.name);
    }
    function isExtractableDocumentPath(path) {
        const ext = String(path || '').split('.').pop().toLowerCase();
        return AI_EXTRACTABLE_EXT.has(ext);
    }
    function scrapeTextFromPresentationViewer() {
        try {
            const viewer = document.getElementById('viewer');
            const iframe = viewer?.querySelector('iframe');
            const doc = iframe?.contentDocument;
            if (!doc?.body) return null;
            const text = String(doc.body.innerText || doc.body.textContent || '').replace(/\s+\n/g, '\n').trim();
            return text.length > 30 ? text : null;
        } catch (e) {
            return null;
        }
    }
    async function extractUploadedDocumentText(file) {
        const fd = new FormData();
        fd.append('action', 'ai_extract_upload');
        fd.append('file', file, file.name || 'document');
        const res = await fetch(AI_CONFIG.fileApiEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok || typeof data.content !== 'string') {
            throw new Error(data.error || tr('aiDocumentExtractFailed', 'Could not extract text from document'));
        }
        return data.content;
    }

    // Load saved settings from localStorage
    function loadSavedSettings() {
        try {
            const savedModel = localStorage.getItem('ai_selected_model');
            const savedRole = localStorage.getItem('ai_selected_role');
            const notifyComplete = localStorage.getItem('ai_notify_on_complete');
            const savedChatMode = localStorage.getItem('ai_chat_mode');
            
            if (savedModel) aiState.selectedModel = savedModel;
            if (savedRole) aiState.selectedRole = savedRole;
            aiState.rememberPrevious = false;
            if (notifyComplete !== null) aiState.notifyOnComplete = notifyComplete === '1';
            if (savedChatMode === 'agent' || savedChatMode === 'chat') aiState.chatMode = savedChatMode;
        } catch (e) {
            console.warn('Failed to load saved settings:', e);
        }
    }
    async function loadUserSettingsFromDb() {
        try {
            const response = await fetch(AI_CONFIG.apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'ai_user_settings_get' })
            });
            const data = await response.json();
            if (!data?.success || !data?.settings) return;
            const settings = data.settings;
            if (typeof settings.selected_model === 'string' && settings.selected_model) aiState.selectedModel = settings.selected_model;
            if (typeof settings.selected_role === 'string' && settings.selected_role) aiState.selectedRole = settings.selected_role;
            if (settings.chat_mode === 'chat' || settings.chat_mode === 'agent') aiState.chatMode = settings.chat_mode;
            aiState.includeFileContext = false;
            aiState.rememberPrevious = false;
            if (typeof settings.notify_on_complete === 'boolean') aiState.notifyOnComplete = settings.notify_on_complete;
            rememberCurrentUserSettingsSnapshot();
        } catch (e) {
            console.warn('Failed to load user settings from DB:', e);
        }
    }
    async function saveUserSettingsToDb() {
        try {
            const payload = getUserSettingsPayload();
            const snapshot = JSON.stringify(payload);
            if (snapshot === lastSavedUserSettingsSnapshot) return;
            lastSavedUserSettingsSnapshot = snapshot;
            const response = await fetch(AI_CONFIG.apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'ai_user_settings_set', ...payload })
            });
            const data = await response.json().catch(() => null);
            if (!response.ok || !data?.success) {
                throw new Error(data?.error || 'Failed to save settings');
            }
        } catch (e) {
            lastSavedUserSettingsSnapshot = '';
            console.warn('Failed to save user settings to DB:', e);
        }
    }

    // Save settings to localStorage
    function saveSettings() {
        try {
            if (elements.modelSelect) {
                aiState.selectedModel = elements.modelSelect.value;
                localStorage.setItem('ai_selected_model', aiState.selectedModel);
            }
            if (elements.roleSelect) {
                aiState.selectedRole = elements.roleSelect.value;
                localStorage.setItem('ai_selected_role', aiState.selectedRole);
            }
            aiState.rememberPrevious = false;
            localStorage.removeItem('ai_remember_previous');
            if (elements.notifyCheckbox) {
                aiState.notifyOnComplete = elements.notifyCheckbox.checked;
                localStorage.setItem('ai_notify_on_complete', aiState.notifyOnComplete ? '1' : '0');
            }
            if (elements.modeSelect) {
                aiState.chatMode = elements.modeSelect.checked ? 'agent' : 'chat';
                localStorage.setItem('ai_chat_mode', aiState.chatMode);
            }
            saveUserSettingsToDb();
        } catch (e) {
            console.warn('Failed to save settings:', e);
        }
    }
    function getAiProxyUrl(action) {
        const url = new URL(AI_CONFIG.apiEndpoint, window.location.href);
        url.searchParams.set('action', action);
        return url.toString();
    }
    async function ensureDirectApiKeyLoaded() {
        if (AI_CONFIG.directApi.configured) return;
        const configResponse = await fetch(getAiProxyUrl('ai_runtime_config'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
            credentials: 'same-origin',
            body: new URLSearchParams({ action: 'ai_runtime_config' })
        });
        if (!configResponse.ok) throw new Error(`Failed to load AI configuration: HTTP ${configResponse.status}`);
        const config = await configResponse.json();
        if (!config?.success && config?.error) throw new Error(config.error);
        if (Array.isArray(config.models) && config.models.length) AI_CONFIG.configuredModels = config.models.slice();
        if (config.default_model) AI_CONFIG.defaultModel = String(config.default_model);
        if (!config.success) throw new Error(config.error || 'AI is not configured');
        if (!config.configured) throw new Error('AI API key is not configured');
        AI_CONFIG.directApi.configured = true;
    }
    const AI_RETRYABLE_HTTP_STATUSES = new Set([408, 500, 502, 503, 504, 520, 521, 522, 523, 524]);
    const AI_COMPLETIONS_MAX_ATTEMPTS = 3;
    function gatewayErrorMessage(status) {
        if (status === 524 || status === 504 || status === 408) {
            return tr('aiGatewayTimeout', 'The model did not answer in time (gateway timeout {status}). Please try again or pick a faster model.', { status });
        }
        return tr('aiGatewayError', 'The AI gateway is temporarily unavailable (HTTP {status}). Please try again.', { status });
    }
    function waitBeforeRetry(attempt) {
        return new Promise((resolve) => setTimeout(resolve, 800 * Math.pow(2, attempt)));
    }
    async function fetchAiCompletions(payload, signal, attempt = 0) {
        let response;
        try {
            response = await fetch(getAiProxyUrl('ai_completions'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream, application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
                signal
            });
        } catch (err) {
            if (err?.name === 'AbortError' || signal?.aborted || attempt >= AI_COMPLETIONS_MAX_ATTEMPTS - 1) throw err;
            await waitBeforeRetry(attempt);
            return fetchAiCompletions(payload, signal, attempt + 1);
        }
        if (response.ok) return response;
        if (AI_RETRYABLE_HTTP_STATUSES.has(response.status) && attempt < AI_COMPLETIONS_MAX_ATTEMPTS - 1 && !signal?.aborted) {
            try { await response.body?.cancel?.(); } catch (e) {}
            await waitBeforeRetry(attempt);
            return fetchAiCompletions(payload, signal, attempt + 1);
        }
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('json')) {
            try { await response.body?.cancel?.(); } catch (e) {}
            return new Response(JSON.stringify({ error: { message: gatewayErrorMessage(response.status) } }), {
                status: response.status,
                headers: { 'Content-Type': 'application/json; charset=utf-8' }
            });
        }
        return response;
    }
    function formatModelLabel(modelId) {
        const id = String(modelId || '');
        if (!id) return 'AI model';
        const parts = id.split('/');
        if (parts.length > 1) return `${parts[0]}: ${parts.slice(1).join('/')}`;
        return id;
    }
    function syncModelOptionMeta(option, unavailable) {
        if (!option) return;
        option.dataset.unavailable = unavailable ? '1' : '0';
    }
    function getCurrentModelDisplayName() {
        if (!elements.modelSelect) return 'AI';
        const selectedOption = elements.modelSelect.options[elements.modelSelect.selectedIndex];
        const fallback = selectedOption?.text || elements.modelSelect.value || 'AI';
        return fallback.split(':')[0] || fallback.split('/').pop() || 'AI';
    }
    function getPreferredModelCandidates() {
        const fromCfg = Array.isArray(AI_CONFIG.configuredModels) ? AI_CONFIG.configuredModels : [];
        return [AI_CONFIG.defaultModel, ...fromCfg, 'github/gpt-4o-mini', 'github/gpt-4o'].filter(Boolean);
    }
    function resolveModelForRequest() {
        if (!elements.modelSelect) return AI_CONFIG.defaultModel;
        const options = Array.from(elements.modelSelect.options || []);
        const availableOptions = options.filter((o) => o.value && o.dataset.unavailable !== '1');
        const availableValues = new Set(availableOptions.map((o) => o.value));
        const selectedValue = String(elements.modelSelect.value || '').trim();
        if (selectedValue && availableValues.has(selectedValue)) return selectedValue;
        for (const preferred of getPreferredModelCandidates()) {
            if (preferred && availableValues.has(preferred)) {
                elements.modelSelect.value = preferred;
                aiState.selectedModel = preferred;
                updateModelDropdownLabel();
                saveSettings();
                return preferred;
            }
        }
        const fallback = availableOptions[0]?.value || options[0]?.value || AI_CONFIG.defaultModel;
        if (fallback) {
            elements.modelSelect.value = fallback;
            aiState.selectedModel = fallback;
            updateModelDropdownLabel();
            saveSettings();
        }
        return fallback || AI_CONFIG.defaultModel;
    }
    function renderModelDropdownOptions() {
        if (!modelDropdownState.menu || !elements.modelSelect) return;
        modelDropdownState.menu.innerHTML = '';
        const unavailable = [];
        const available = [];
        Array.from(elements.modelSelect.options).forEach((option) => {
            const isUnavailable = option.dataset.unavailable === '1';
            (isUnavailable ? unavailable : available).push(option);
        });
        const all = available.concat(unavailable);
        all.forEach((option, idx) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'ai-model-dropdown-item';
            if (option.value === elements.modelSelect.value) item.classList.add('is-selected');
            if (option.dataset.unavailable === '1') item.classList.add('is-unavailable');
            item.setAttribute('data-value', option.value);
            item.textContent = option.text;
            modelDropdownState.menu.appendChild(item);
            if (idx === available.length - 1 && unavailable.length > 0) {
                const divider = document.createElement('div');
                divider.className = 'ai-model-dropdown-divider';
                modelDropdownState.menu.appendChild(divider);
            }
        });
    }
    function updateModelDropdownLabel() {
        if (!modelDropdownState.button || !elements.modelSelect) return;
        const selectedOption = elements.modelSelect.options[elements.modelSelect.selectedIndex];
        modelDropdownState.button.querySelector('.ai-model-dropdown-text').textContent = selectedOption?.text || formatModelLabel(elements.modelSelect.value || AI_CONFIG.defaultModel);
    }
    function closeModelDropdown() {
        if (!modelDropdownState.wrapper) return;
        modelDropdownState.open = false;
        modelDropdownState.wrapper.classList.remove('open');
    }
    function openModelDropdown() {
        if (!modelDropdownState.wrapper) return;
        const menu = modelDropdownState.menu;
        const btn = modelDropdownState.button;
        if (menu && btn) {
            modelDropdownState.wrapper.classList.remove('drop-up');
            const rect = btn.getBoundingClientRect();
            const viewportH = window.innerHeight || document.documentElement.clientHeight || 0;
            const spaceBelow = Math.max(0, viewportH - rect.bottom - 8);
            const spaceAbove = Math.max(0, rect.top - 8);
            const menuHeight = Math.min(280, menu.scrollHeight || 280);
            if (spaceBelow < Math.min(180, menuHeight) && spaceAbove > spaceBelow) modelDropdownState.wrapper.classList.add('drop-up');
        }
        modelDropdownState.open = true;
        modelDropdownState.wrapper.classList.add('open');
    }
    function markModelUnavailable(modelId) {
        const id = String(modelId || '').trim();
        if (!id || !elements.modelSelect) return;
        unavailableModels.add(id);
        const option = Array.from(elements.modelSelect.options).find((o) => o.value === id);
        if (!option) return;
        syncModelOptionMeta(option, true);
        renderModelDropdownOptions();
        updateModelDropdownLabel();
    }
    function shouldMarkModelUnavailable(errorText) {
        const text = String(errorText || '').toLowerCase();
        return text.includes('insufficient') || text.includes('quota') || text.includes('balance') || text.includes('not enough') || text.includes('model is not available') || text.includes('model not available') || text.includes('invalid model') || text.includes('model not found') || text.includes('does not exist') || text.includes('unavailable') || text.includes('rate-limit') || text.includes('queue budget') || text.includes('maximum combo retry');
    }
    function initCustomModelDropdown() {
        if (!elements.modelSelect) return;
        const wrap = elements.modelSelect.closest('.select-wrapper');
        if (!wrap) return;
        if (!modelDropdownState.wrapper) {
            const dd = document.createElement('div');
            dd.className = 'ai-model-dropdown';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ai-model-dropdown-btn';
            btn.innerHTML = '<span class="ai-model-dropdown-text"></span><span class="ai-model-dropdown-arrow">▾</span>';
            const menu = document.createElement('div');
            menu.className = 'ai-model-dropdown-menu';
            dd.appendChild(btn);
            dd.appendChild(menu);
            wrap.appendChild(dd);
            modelDropdownState.wrapper = dd;
            modelDropdownState.button = btn;
            modelDropdownState.menu = menu;
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (modelDropdownState.open) closeModelDropdown();
                else openModelDropdown();
            });
            menu.addEventListener('click', (e) => {
                const item = e.target.closest('.ai-model-dropdown-item');
                if (!item) return;
                const value = item.getAttribute('data-value');
                if (!value || !elements.modelSelect) return;
                elements.modelSelect.value = value;
                elements.modelSelect.dispatchEvent(new Event('change', { bubbles: true }));
                closeModelDropdown();
            });
        }
        renderModelDropdownOptions();
        updateModelDropdownLabel();
    }
    async function loadModelsFromApi() {
        if (!elements.modelSelect) return;
        await ensureDirectApiKeyLoaded();
        let ids = Array.isArray(AI_CONFIG.configuredModels) ? AI_CONFIG.configuredModels.filter(Boolean) : [];
        if (ids.length === 0) {
            const response = await fetch(getAiProxyUrl('ai_models'), { method: 'GET', credentials: 'same-origin' });
            if (!response.ok) throw new Error(`Failed to fetch models: HTTP ${response.status}`);
            const payload = await response.json();
            const models = Array.isArray(payload?.data) ? payload.data : [];
            ids = models.map((m) => String(m?.id || '').trim()).filter(Boolean).sort((a, b) => a.localeCompare(b));
        }
        if (ids.length === 0) throw new Error('No models available');
        const previous = aiState.selectedModel || elements.modelSelect.value || AI_CONFIG.defaultModel;
        elements.modelSelect.innerHTML = '';
        ids.forEach((id) => {
            const option = document.createElement('option');
            option.value = id;
            option.textContent = formatModelLabel(id);
            syncModelOptionMeta(option, unavailableModels.has(id));
            elements.modelSelect.appendChild(option);
        });
        const preferredModels = [previous, ...getPreferredModelCandidates()];
        const pickedPreferred = preferredModels.find((id) => ids.includes(id));
        elements.modelSelect.value = pickedPreferred || ids[0];
        aiState.selectedModel = elements.modelSelect.value;
        renderModelDropdownOptions();
        updateModelDropdownLabel();
    }

    // Add visual fixes
    function addVisualFixes() {
        const styleId = 'ai-assistant-visual-fixes';
        if (document.getElementById(styleId)) return;

        const style = document.createElement('style');
        style.id = styleId;
        style.textContent = `
            /* Fix for selects in one line */
            .ai-controls {
                display: flex !important;
                gap: 8px !important;
                align-items: center !important;
                flex-wrap: nowrap !important;
            }
            
            .ai-controls .select-wrapper {
                flex: 0 1 auto !important;
                min-width: 0 !important;
            }
            .ai-controls .select-wrapper:has(#ai-model-select) {
                flex: 0 0 35% !important;
                max-width: 35% !important;
            }
            #ai-model-select {
                position: absolute !important;
                opacity: 0 !important;
                pointer-events: none !important;
                width: 0 !important;
                height: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .ai-model-dropdown {
                position: relative !important;
                width: 100% !important;
            }
            .select-wrapper:has(#ai-model-select) > svg {
                display: none !important;
            }
            .select-wrapper:has(#ai-model-select)::after {
                display: none !important;
                content: none !important;
                background-image: none !important;
            }
            .ai-model-dropdown-btn {
                width: 100% !important;
                border: 1px solid var(--border) !important;
                background: var(--bg) !important;
                color: var(--fg) !important;
                border-radius: 999px !important;
                min-height: 34px !important;
                padding: 8px 32px 8px 12px !important;
                text-align: left !important;
                font-size: 12px !important;
                cursor: pointer !important;
                position: relative !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }
            .ai-model-dropdown-arrow {
                position: absolute !important;
                right: 10px !important;
                top: 50% !important;
                transform: translateY(-50%) !important;
                opacity: .75 !important;
            }
            .ai-model-dropdown-menu {
                position: absolute !important;
                top: calc(100% + 6px) !important;
                left: 0 !important;
                right: 0 !important;
                max-height: 280px !important;
                overflow-y: auto !important;
                background: var(--panel) !important;
                border: 1px solid var(--border) !important;
                border-radius: 10px !important;
                padding: 6px !important;
                z-index: 120 !important;
                display: none !important;
                box-shadow: 0 8px 18px rgba(0,0,0,.18) !important;
                scrollbar-width: thin !important;
                scrollbar-color: rgba(160,160,170,.75) transparent !important;
            }
            .ai-model-dropdown.drop-up .ai-model-dropdown-menu {
                top: auto !important;
                bottom: calc(100% + 6px) !important;
            }
            .ai-model-dropdown.open .ai-model-dropdown-menu {
                display: block !important;
            }
            .ai-model-dropdown-menu::-webkit-scrollbar {
                width: 6px !important;
                height: 6px !important;
                background: transparent !important;
            }
            .ai-model-dropdown-menu::-webkit-scrollbar-track {
                background: transparent !important;
            }
            .ai-model-dropdown-menu::-webkit-scrollbar-thumb {
                background: rgba(160,160,170,.75) !important;
                border-radius: 999px !important;
            }
            .ai-model-dropdown-item {
                width: 100% !important;
                border: 0 !important;
                background: transparent !important;
                color: var(--fg) !important;
                text-align: left !important;
                padding: 7px 8px !important;
                border-radius: 7px !important;
                cursor: pointer !important;
                font-size: 12px !important;
            }
            .ai-model-dropdown-item:hover,.ai-model-dropdown-item.is-selected {
                background: var(--bg) !important;
            }
            .ai-model-dropdown-divider {
                height: 1px !important;
                background: var(--border) !important;
                margin: 6px 2px !important;
            }
            .ai-model-dropdown-item.is-unavailable {
                color: #ff6b6b !important;
            }
            .ai-controls .ai-mode-switch {
                width: 22% !important;
                flex: 0 0 22% !important;
                min-width: 120px !important;
            }
            #ai-model-select,
            #ai-role-select {
                border-radius: 999px !important;
            }
            #ai-model-select {
                appearance: none !important;
                -webkit-appearance: none !important;
                background-image: none !important;
                padding-right: 10px !important;
            }
            .ai-controls .ai-context-switch {
                display: none !important;
            }
            .ai-input-action-stack {
                display: inline-flex !important;
                flex-direction: column !important;
                align-items: center !important;
                gap: 6px !important;
                position: static !important;
                order: 1 !important;
            }
            .ai-input-action-stack .icon-btn.small,
            .ai-input-action-stack .ai-attach-wrap > .icon-btn.small {
                width: 32px !important;
                height: 32px !important;
                min-width: 32px !important;
                min-height: 32px !important;
                padding: 0 !important;
                border-radius: 999px !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            .ai-input-action-stack .icon-btn.small svg,
            .ai-input-action-stack .ai-attach-wrap > .icon-btn.small svg {
                margin: 0 !important;
                display: block !important;
            }
            
            /* Attached file + @mentions chips live in quick actions row */
            .ai-input {
                position: relative !important;
                display: flex !important;
                flex-wrap: wrap !important;
                align-items: center !important;
                gap: 6px !important;
                padding: 6px 8px !important;
            }
            .ai-input.ai-tree-drop-active {
                outline: 2px dashed var(--accent) !important;
                outline-offset: 3px !important;
                background: color-mix(in srgb, var(--accent) 10%, transparent) !important;
            }
            .ai-input-selectors-row {
                width: 100% !important;
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                order: 0 !important;
                margin: 0 0 4px 0 !important;
            }
            .ai-input-selectors-row .select-wrapper {
                flex: 0 0 28% !important;
                max-width: 28% !important;
                min-width: 0 !important;
            }
            .ai-input-selectors-row .ai-mode-switch {
                flex: 0 0 112px !important;
                width: 112px !important;
                min-width: 112px !important;
            }
            #ai-input-text {
                min-width: 0 !important;
                flex: 1 1 auto !important;
                order: 2 !important;
            }
            #btn-ai-send {
                border-radius: 999px !important;
                width: 38px !important;
                height: 38px !important;
                min-width: 38px !important;
                min-height: 38px !important;
                align-self: center !important;
                margin: 0 !important;
                order: 3 !important;
            }

            .ai-attachments-row {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                width: 100% !important;
                flex: 0 0 100% !important;
                order: -1 !important;
                margin: 0 0 8px 0 !important;
                overflow-x: auto !important;
                overflow-y: hidden !important;
                scrollbar-width: thin !important;
            }

            .ai-attachments-row.hidden {
                display: none !important;
            }
            
            .attached-file-info {
                position: relative !important;
                margin: 0 !important;
                display: inline-flex !important;
                align-items: center !important;
                gap: 8px !important;
                padding: 6px 10px !important;
                background: var(--panel) !important;
                border: 1px solid var(--border) !important;
                border-radius: 8px !important;
                font-size: 11px !important;
                z-index: 10 !important;
                box-shadow: none !important;
                width: 100% !important;
                min-width: 0 !important;
                max-height: 64px !important;
                overflow: hidden !important;
                transition: opacity 0.18s ease, transform 0.18s ease, max-height 0.18s ease, margin 0.18s ease, padding 0.18s ease !important;
            }
            .attached-file-info.is-removing {
                opacity: 0 !important;
                transform: translateY(8px) scale(0.98) !important;
                max-height: 0 !important;
                margin-top: 0 !important;
                margin-bottom: 0 !important;
                padding-top: 0 !important;
                padding-bottom: 0 !important;
            }
            
            .attached-file-info.hidden {
                display: none !important;
            }

            #ai-attached-files-list {
                display: grid !important;
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 8px !important;
                width: 100% !important;
            }
            #ai-attached-files-list.hidden {
                display: none !important;
            }

            .attached-mentions-info {
                position: relative !important;
                margin: 0 !important;
                display: flex !important;
                flex-wrap: nowrap !important;
                gap: 6px !important;
                padding: 0 !important;
                background: transparent !important;
                border: none !important;
                border-radius: 8px !important;
                font-size: 12px !important;
                z-index: 11 !important;
                box-shadow: none !important;
                overflow-x: auto !important;
                overflow-y: hidden !important;
                white-space: nowrap !important;
                min-width: 0 !important;
                max-width: 100% !important;
            }

            /* Sidebar animation for AI panel */
            #ai-modal {
                display: flex !important;
                align-items: stretch !important;
                justify-content: flex-end !important;
                position: fixed !important;
                inset: 0 !important;
                width: 100vw !important;
                height: 100vh !important;
                height: 100dvh !important;
                min-height: 100vh !important;
                min-height: 100dvh !important;
                max-height: 100vh !important;
                max-height: 100dvh !important;
                padding: 0 !important;
                background: rgba(0, 0, 0, 0.35) !important;
                opacity: 0 !important;
                pointer-events: none !important;
                z-index: 20000 !important;
                transition: opacity 0.26s ease !important;
            }
            #ai-modal:not(.hidden) {
                opacity: 1 !important;
                pointer-events: none !important;
            }
            #ai-modal .ai-modal-content {
                position: relative !important;
                width: clamp(360px, 33vw, 1200px);
                height: 100vh !important;
                height: 100dvh !important;
                min-height: 100vh !important;
                min-height: 100dvh !important;
                max-width: 90vw !important;
                max-height: 100vh !important;
                max-height: 100dvh !important;
                border-radius: 16px 0 0 16px !important;
                margin: 0 !important;
                right: auto !important;
                left: auto !important;
                transform: translateX(100%) !important;
                transition: transform 0.26s ease !important;
                will-change: auto !important;
                backface-visibility: hidden !important;
                -webkit-font-smoothing: antialiased !important;
                background: var(--bg) !important;
            }
            @media (min-width: 769px) {
                #ai-modal {
                    background: transparent !important;
                }
            }
            @media (max-width: 768px) {
                #ai-modal {
                    justify-content: stretch !important;
                }
                #ai-modal:not(.hidden) {
                    pointer-events: auto !important;
                }
                #ai-modal .ai-modal-content {
                    position: fixed !important;
                    top: 0 !important;
                    left: 0 !important;
                    right: auto !important;
                    bottom: 0 !important;
                    width: 100% !important;
                    height: 100dvh !important;
                    max-width: 100% !important;
                    min-width: 0 !important;
                    min-height: 0 !important;
                    max-height: 100dvh !important;
                    border-radius: 0 !important;
                    transform: none !important;
                }
                #ai-modal .ai-resize-handle {
                    display: none !important;
                }
                #ai-modal .ai-modal-body {
                    flex: 1 1 0% !important;
                    display: flex !important;
                    flex-direction: column !important;
                    min-height: 0 !important;
                    height: 0 !important;
                    overflow: hidden !important;
                }
                #ai-modal .ai-chat {
                    flex: 1 1 0% !important;
                    min-height: 0 !important;
                    height: 0 !important;
                    max-height: none !important;
                    overflow-y: auto !important;
                    overflow-x: hidden !important;
                    padding-bottom: 0 !important;
                }
                #ai-modal .ai-input {
                    flex-shrink: 0 !important;
                    padding-bottom: 8px !important;
                    margin-top: auto !important;
                }
                #ai-modal .ai-input textarea {
                    resize: none !important;
                    max-height: 80px !important;
                }
                #ai-modal .modal-header {
                    position: relative !important;
                    padding-left: calc(var(--menu-button-mobile-reserve, 50px) + 8px) !important;
                }
                #ai-modal .modal-header h3 {
                    position: static !important;
                    left: auto !important;
                    transform: none !important;
                    text-align: left !important;
                    width: auto !important;
                    flex: 0 0 auto;
                }
                #ai-modal .modal-header .modal-actions {
                    margin-left: auto !important;
                }
                #ai-modal .ai-chat-session-controls {
                    padding-left: 0 !important;
                }
            }
            #ai-modal .ai-resize-handle {
                position: absolute !important;
                top: 0 !important;
                left: -4px !important;
                width: 8px !important;
                height: 100% !important;
                cursor: ew-resize !important;
                z-index: 3 !important;
                background: transparent !important;
            }
            #ai-modal .ai-resize-handle:hover {
                background: rgba(255, 255, 255, 0.08) !important;
            }
            #ai-modal .ai-chat {
                padding-bottom: 0 !important;
                padding-top: 10px !important;
                max-height: none !important;
                position: relative !important;
                isolation: isolate !important;
                background: transparent !important;
            }
            #ai-modal .ai-modal-body {
                position: relative !important;
            }
            #ai-modal .ai-modal-body > .dot-field-canvas {
                position: absolute !important;
                display: block !important;
                margin: 0 !important;
                pointer-events: none !important;
                z-index: 0 !important;
                opacity: 1 !important;
            }
            #ai-modal .ai-chat .message,
            #ai-modal .ai-chat .typing-indicator {
                position: relative !important;
                z-index: 1 !important;
            }
            #ai-modal.ai-open .ai-modal-content {
                transform: none !important;
            }

            /* AI horizontal rules */
            .message-content hr.ai-hr {
                border: none;
                border-top: 1px solid var(--border);
                margin: 10px 0;
                opacity: 0.85;
            }

            /* Simple tables in responses */
            .message-content table.ai-table {
                width: 100%;
                border-collapse: collapse;
                margin: 8px 0;
                font-size: 13px;
                table-layout: fixed;
                max-width: 100%;
            }
            .message-content table.ai-table th,
            .message-content table.ai-table td {
                border: 1px solid var(--border);
                padding: 6px 8px;
                vertical-align: top;
                word-break: break-word;
                overflow-wrap: anywhere;
            }
            .message-content table.ai-table th {
                background: var(--bg-secondary);
                font-weight: 600;
            }

            .ai-table-wrap {
                max-width: 100%;
                overflow-x: auto;
            }

            /* Markdown lists in responses */
            .message-content ul.ai-list,
            .message-content ol.ai-list {
                margin: 6px 0;
                padding-left: 22px;
            }
            .message-content ul.ai-list { list-style: disc; }
            .message-content ol.ai-list { list-style: decimal; }
            .message-content .ai-list li {
                margin: 2px 0;
                line-height: 1.55;
                overflow-wrap: anywhere;
            }
            .message-content .ai-list .ai-list { margin: 2px 0; }

            /* Remember toggle (icon only) */
            .ai-remember-wrap {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                margin-left: 6px;
                cursor: pointer;
                width: 28px;
                height: 28px;
                border-radius: 8px;
                border: 1px solid var(--border);
                background: var(--panel);
                transition: background 0.15s ease, border-color 0.15s ease, transform 0.05s ease;
                user-select: none;
            }
            .ai-remember-wrap:hover {
                background: var(--bg-secondary);
            }
            .ai-remember-wrap:active {
                transform: scale(0.98);
            }
            .ai-remember-wrap input {
                position: absolute;
                opacity: 0;
                pointer-events: none;
                width: 0;
                height: 0;
            }
            .ai-remember-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                opacity: 0.65;
            }
            .ai-remember-wrap input:checked + .ai-remember-icon {
                opacity: 1;
                color: var(--accent);
            }
            .ai-remember-wrap input:not(:checked) + .ai-remember-icon {
                opacity: 1;
                color: #d14a4a;
            }
            .ai-controls-modal {
                position: absolute !important;
                inset: 0 !important;
                background: rgba(0, 0, 0, 0.5) !important;
                backdrop-filter: blur(8px) saturate(1.1) !important;
                -webkit-backdrop-filter: blur(8px) saturate(1.1) !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                z-index: 22000 !important;
            }
            .ai-controls-modal.hidden {
                display: none !important;
            }
            .ai-controls-modal-card {
                width: min(420px, 94%) !important;
                max-height: 80% !important;
                overflow: auto !important;
                background: var(--panel) !important;
                border: 1px solid var(--border) !important;
                border-radius: 10px !important;
                padding: 10px !important;
            }
            .ai-controls-modal-head {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                margin-bottom: 10px !important;
            }
            .ai-controls-modal-body {
                display: flex !important;
                flex-direction: column !important;
                gap: 8px !important;
            }
            .ai-setting-row {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 10px !important;
                padding: 6px 0 !important;
                min-height: 40px !important;
            }
            .ai-setting-label {
                font-size: 12px !important;
                color: var(--text-secondary) !important;
            }
            .ai-controls-modal .ai-setting-row > :last-child {
                width: 164px !important;
                min-width: 164px !important;
                max-width: 164px !important;
            }
            .ai-controls-modal .ai-setting-row .ai-mode-switch {
                width: 164px !important;
            }
            .ai-controls-modal .ai-setting-row select,
            .ai-controls-modal .ai-setting-row button {
                height: 36px !important;
                border-radius: 999px !important;
            }
            .ai-controls-modal .ai-setting-row button {
                width: 164px !important;
                justify-content: center !important;
                display: inline-flex !important;
                align-items: center !important;
            }
            .ai-setting-label .ai-setting-inline-state {
                margin-left: 6px !important;
                font-size: 11px !important;
                opacity: 0.85 !important;
            }
            .ai-mode-switch {
                display: flex !important;
                width: 112px !important;
                cursor: pointer !important;
                flex: 0 0 112px !important;
            }
            .ai-mode-switch input {
                position: absolute !important;
                opacity: 0 !important;
                pointer-events: none !important;
            }
            .ai-mode-switch-track {
                position: relative !important;
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                width: 112px !important;
                height: 30px !important;
                border: 1px solid var(--border) !important;
                border-radius: 999px !important;
                background: var(--bg-secondary) !important;
                padding: 2px !important;
            }
            .ai-mode-switch-thumb {
                position: absolute !important;
                top: 2px !important;
                left: 2px !important;
                width: 52px !important;
                height: 24px !important;
                bottom: 2px !important;
                border-radius: 999px !important;
                background: var(--accent) !important;
                transition: transform .18s ease !important;
            }
            .ai-mode-switch input:checked + .ai-mode-switch-track .ai-mode-switch-thumb {
                transform: translateX(54px) !important;
            }
            .ai-mode-switch-label {
                position: relative !important;
                z-index: 1 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                font-size: 12px !important;
            }
            .ai-agent-replaced {
                background: rgba(133,181,3,.18) !important;
            }
            .ai-agent-line-remove {
                background: rgba(209,74,74,.22) !important;
            }
            .ai-agent-inline-add {
                color: #85b503 !important;
                background: rgba(133,181,3,.16) !important;
            }
            .ai-agent-patch-preview {
                border: 1px solid var(--border) !important;
                border-radius: 8px !important;
                overflow: hidden !important;
                background: var(--bg-secondary, var(--panel)) !important;
                font-family: 'JetBrains Mono', 'Fira Code', 'Menlo', monospace !important;
                font-size: 12px !important;
                line-height: 1.45 !important;
                white-space: pre-wrap !important;
            }
            .ai-agent-patch-title {
                padding: 8px 12px !important;
                border-bottom: 1px solid var(--border) !important;
                color: var(--muted) !important;
                font-family: inherit !important;
                font-size: 11px !important;
                text-transform: uppercase !important;
                letter-spacing: .04em !important;
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 8px !important;
            }
            .ai-agent-patch-title-label {
                min-width: 0 !important;
            }
            .ai-agent-patch-toggle {
                border: 1px solid var(--border) !important;
                background: transparent !important;
                color: var(--muted) !important;
                border-radius: 6px !important;
                padding: 2px 8px !important;
                font-family: inherit !important;
                font-size: 11px !important;
                line-height: 1.4 !important;
                text-transform: none !important;
                letter-spacing: 0 !important;
                cursor: pointer !important;
                flex: 0 0 auto !important;
                display: inline-flex !important;
                align-items: center !important;
                gap: 4px !important;
                transition: color .15s ease, border-color .15s ease !important;
            }
            .ai-agent-patch-toggle:hover {
                color: var(--fg) !important;
                border-color: var(--accent) !important;
            }
            .ai-agent-patch-toggle-icon {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                transition: transform .15s ease !important;
            }
            .ai-agent-patch-toggle-icon svg {
                display: block !important;
            }
            .ai-agent-patch-preview:not(.collapsed) .ai-agent-patch-toggle-icon {
                transform: rotate(180deg) !important;
            }
            .ai-agent-patch-row.ai-agent-patch-row-hidden {
                display: none !important;
            }
            .ai-agent-patch-row {
                display: grid !important;
                grid-template-columns: 32px minmax(0, 1fr) !important;
                gap: 8px !important;
                padding: 2px 10px !important;
                overflow-wrap: anywhere !important;
            }
            .ai-agent-patch-row.eq { color: var(--fg) !important; }
            .ai-agent-patch-row.rm { background: rgba(209,74,74,.18) !important; color: #ff8a8a !important; }
            .ai-agent-patch-row.add { background: rgba(133,181,3,.16) !important; color: #9fe870 !important; }
            #ai-agent-activity { display: none !important; }
            .ai-agent-plan {
                border: 1px solid var(--border) !important;
                border-radius: 10px !important;
                background: var(--bg-secondary, var(--panel)) !important;
                margin-bottom: 12px !important;
                overflow: hidden !important;
                font-size: 12px !important;
            }
            .ai-agent-plan-head {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                padding: 10px 12px !important;
                border-bottom: 1px solid var(--border) !important;
                font-weight: 600 !important;
                color: var(--fg) !important;
            }
            .ai-agent-plan-head .ai-agent-plan-pulse {
                width: 8px !important;
                height: 8px !important;
                border-radius: 50% !important;
                background: var(--accent) !important;
                animation: ai-agent-pulse 1.2s ease-in-out infinite !important;
            }
            .ai-agent-plan.running .ai-agent-plan-pulse { display: inline-block !important; }
            .ai-agent-plan.done .ai-agent-plan-pulse {
                display: none !important;
                animation: none !important;
                opacity: 1 !important;
                transform: none !important;
            }
            .ai-agent-plan.done.outcome-success .ai-agent-plan-pulse,
            .ai-agent-plan.done.outcome-error .ai-agent-plan-pulse,
            .ai-agent-plan.done.outcome-stopped .ai-agent-plan-pulse { display: inline-block !important; }
            .ai-agent-plan.done.outcome-success .ai-agent-plan-pulse { background: #6fbf3f !important; }
            .ai-agent-plan.done.outcome-error .ai-agent-plan-pulse { background: #d14a4a !important; }
            .ai-agent-plan.done.outcome-stopped .ai-agent-plan-pulse { background: color-mix(in srgb, var(--fg) 35%, transparent) !important; }
            .ai-agent-plan.ai-agent-plan-empty { display: none !important; }
            .ai-agent-stream-shell {
                display: none !important;
                margin: 0 12px 10px !important;
                padding: 10px 12px !important;
                border-radius: 8px !important;
                background: rgba(0,0,0,.35) !important;
                border: 1px solid color-mix(in srgb, var(--border) 60%, transparent) !important;
                max-height: 180px !important;
                overflow: auto !important;
            }
            .ai-agent-stream-shell.visible { display: block !important; }
            .ai-agent-stream-text {
                margin: 0 !important;
                font-family: 'JetBrains Mono', 'Fira Code', monospace !important;
                font-size: 11px !important;
                line-height: 1.45 !important;
                color: color-mix(in srgb, var(--fg) 85%, transparent) !important;
                white-space: pre-wrap !important;
                word-break: break-word !important;
            }
            @keyframes ai-agent-pulse {
                0%, 100% { opacity: .35; transform: scale(.9); }
                50% { opacity: 1; transform: scale(1.1); }
            }
            .ai-agent-plan-steps { padding: 6px 0 !important; }
            .ai-agent-plan-step {
                display: grid !important;
                grid-template-columns: 22px minmax(0, 3fr) minmax(0, 2fr) !important;
                gap: 8px !important;
                align-items: start !important;
                padding: 7px 12px !important;
                border-left: 3px solid transparent !important;
            }
            .ai-agent-plan-step.running { border-left-color: var(--accent) !important; background: rgba(133,181,3,.06) !important; }
            .ai-agent-plan-step.done { border-left-color: #85b503 !important; }
            .ai-agent-plan-step.error { border-left-color: #d14a4a !important; background: rgba(209,74,74,.08) !important; }
            .ai-agent-plan-step.pending { opacity: .55 !important; }
            .ai-agent-plan-icon {
                width: 18px !important;
                height: 18px !important;
                border-radius: 50% !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                font-size: 10px !important;
                font-weight: 700 !important;
                background: var(--border) !important;
                color: var(--muted) !important;
            }
            .ai-agent-plan-step.running .ai-agent-plan-icon { background: var(--accent) !important; color: #fff !important; }
            .ai-agent-plan-step.done .ai-agent-plan-icon { background: #85b503 !important; color: #111 !important; }
            .ai-agent-plan-step.error .ai-agent-plan-icon { background: #d14a4a !important; color: #fff !important; }
            .ai-agent-plan-label {
                display: inline-flex !important;
                align-items: center !important;
                gap: 6px !important;
                font-family: inherit !important;
                font-style: normal !important;
                color: var(--fg) !important;
                word-break: break-word !important;
                min-width: 0 !important;
            }
            .ai-agent-plan-tool-svg {
                width: 16px !important;
                height: 16px !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                flex: 0 0 16px !important;
                color: color-mix(in srgb, var(--accent) 78%, var(--fg)) !important;
            }
            .ai-agent-plan-tool-svg svg {
                width: 14px !important;
                height: 14px !important;
                display: block !important;
            }
            .ai-agent-plan-label-text {
                min-width: 0 !important;
                overflow-wrap: anywhere !important;
                word-break: normal !important;
            }
            .ai-agent-ask-user {
                margin: 8px 12px 10px !important;
                padding: 10px 12px !important;
                border-radius: 8px !important;
                border: 1px solid var(--accent) !important;
                background: rgba(133,181,3,.08) !important;
            }
            .ai-agent-ask-user-title {
                font-size: 11px !important;
                font-weight: 600 !important;
                color: var(--accent) !important;
                margin-bottom: 6px !important;
            }
            .ai-agent-ask-user-q {
                font-size: 13px !important;
                color: var(--fg) !important;
                margin-bottom: 8px !important;
            }
            .ai-agent-ask-user-options {
                display: flex !important;
                flex-wrap: wrap !important;
                gap: 6px !important;
            }
            .ai-agent-ask-user-btn {
                padding: 6px 10px !important;
                border-radius: 6px !important;
                border: 1px solid var(--border) !important;
                background: var(--panel) !important;
                color: var(--fg) !important;
                font-size: 12px !important;
                cursor: pointer !important;
            }
            .ai-agent-ask-user-btn:hover {
                border-color: var(--accent) !important;
                background: rgba(133,181,3,.12) !important;
            }
            .ai-agent-plan-result {
                font-size: 11px !important;
                color: var(--muted) !important;
                text-align: right !important;
                white-space: normal !important;
                overflow-wrap: anywhere !important;
            }
            .ai-agent-plan-result-wrap {
                grid-column: 1 / -1 !important;
                text-align: left !important;
                min-width: 0 !important;
                width: 100% !important;
            }
            .ai-agent-plan-result-body {
                font-size: 11px !important;
                color: var(--muted) !important;
                white-space: pre-wrap !important;
                word-break: normal !important;
                overflow-wrap: anywhere !important;
                line-height: 1.45 !important;
            }
            .ai-agent-plan-result-body.collapsed {
                display: -webkit-box !important;
                -webkit-box-orient: vertical !important;
                -webkit-line-clamp: 2 !important;
                overflow: hidden !important;
            }
            .ai-agent-plan-step.error .ai-agent-plan-result-body { color: #ff8a8a !important; }
            .ai-agent-plan-step.done .ai-agent-plan-result-body { color: #9fe870 !important; }
            .ai-agent-plan-result-expand {
                display: none !important;
                margin-top: 4px !important;
                padding: 0 !important;
                border: none !important;
                background: none !important;
                font-size: 10px !important;
                color: var(--muted) !important;
                opacity: .55 !important;
                cursor: pointer !important;
                text-decoration: underline !important;
                text-underline-offset: 2px !important;
            }
            .ai-agent-plan-result-expand.visible { display: inline-block !important; }
            .ai-agent-plan-step.error .ai-agent-plan-result { color: #ff8a8a !important; }
            .ai-agent-plan-step.done .ai-agent-plan-result { color: #9fe870 !important; }
            .ai-agent-patch-row.skip {
                color: var(--muted) !important;
                font-style: italic !important;
                opacity: .75 !important;
            }
            .ai-agent-undo-last-footer {
                margin-top: 8px !important;
                padding-top: 8px !important;
                border-top: 1px solid var(--border) !important;
            }
            .ai-agent-undo-last-btn {
                font-size: 12px !important;
                color: var(--muted) !important;
                opacity: .85 !important;
            }
            .ai-agent-final-answer { margin-top: 4px !important; }
            .ai-agent-staged-files {
                margin-top: 8px !important;
                font-size: 12px !important;
                color: var(--muted) !important;
            }
            .ai-agent-patch-sign {
                user-select: none !important;
                opacity: .8 !important;
            }

            .attached-mentions-info.hidden {
                display: none !important;
            }

            .mentioned-file-chip {
                display: inline-flex !important;
                flex: 0 0 auto !important;
                align-items: center !important;
                gap: 6px !important;
                min-height: 30px !important;
                padding: 5px 10px !important;
                border: 1px solid color-mix(in srgb, var(--accent) 52%, var(--border)) !important;
                border-radius: 999px !important;
                background: color-mix(in srgb, var(--accent) 16%, var(--panel)) !important;
                color: var(--fg) !important;
                max-width: 100% !important;
                box-shadow: 0 1px 6px rgba(0,0,0,.12) !important;
            }

            .attached-file-info .attached-filename {
                background: rgba(133, 181, 3, 0.08) !important;
                border: 1px solid rgba(133, 181, 3, 0.18) !important;
                border-radius: 999px !important;
                padding: 5px 10px !important;
                white-space: nowrap !important;
            }

            .ai-attach-wrap {
                position: relative !important;
                display: inline-flex !important;
                align-items: center !important;
            }
            .ai-attach-dropdown {
                position: absolute !important;
                left: 0 !important;
                bottom: calc(100% + 6px) !important;
                min-width: 200px !important;
                border: 1px solid var(--border) !important;
                border-radius: 10px !important;
                background: var(--panel) !important;
                box-shadow: 0 10px 28px rgba(0, 0, 0, 0.25) !important;
                z-index: 25 !important;
                overflow: hidden !important;
                padding: 4px 0 !important;
            }
            .ai-attach-dropdown.hidden {
                display: none !important;
            }
            .ai-attach-dropdown-item {
                display: flex !important;
                align-items: center !important;
                gap: 10px !important;
                width: 100% !important;
                padding: 10px 12px !important;
                border: none !important;
                background: transparent !important;
                color: var(--text-primary) !important;
                font-size: 13px !important;
                cursor: pointer !important;
                text-align: left !important;
            }
            .ai-attach-dropdown-item:hover {
                background: var(--bg-secondary) !important;
            }
            .ai-attach-dropdown-item svg {
                flex-shrink: 0 !important;
                opacity: 0.85 !important;
            }
            .attached-file-thumb-wrap {
                position: relative !important;
                width: 38px !important;
                height: 38px !important;
                border-radius: 8px !important;
                overflow: hidden !important;
                flex-shrink: 0 !important;
                border: 1px solid var(--border) !important;
                cursor: pointer !important;
            }
            .attached-file-thumb-wrap.hidden {
                display: none !important;
            }
            .attached-file-thumb-wrap img.attached-file-thumb {
                width: 100% !important;
                height: 100% !important;
                object-fit: cover !important;
                display: block !important;
            }
            .attached-file-icon-wrap {
                width: 38px !important;
                height: 38px !important;
                border-radius: 8px !important;
                overflow: hidden !important;
                flex-shrink: 0 !important;
                border: 1px solid var(--border) !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                background: var(--bg) !important;
                padding: 5px !important;
                box-sizing: border-box !important;
            }
            .attached-file-icon-wrap .app-file-icon {
                width: 26px !important;
                height: 26px !important;
            }
            .attached-file-ocr-progress {
                position: absolute !important;
                inset: 0 !important;
                pointer-events: none !important;
                opacity: 0 !important;
                transition: opacity 0.2s ease !important;
                overflow: hidden !important;
            }
            .attached-file-ocr-progress.active {
                opacity: 1 !important;
            }
            .attached-file-ocr-progress-fill {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                bottom: 0 !important;
                width: 0% !important;
                background: linear-gradient(
                    90deg,
                    rgba(133, 181, 3, 0.35) 0%,
                    rgba(133, 181, 3, 0.55) 100%
                ) !important;
                transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1) !important;
                border-right: 2px solid rgba(133, 181, 3, 0.7) !important;
            }
            #ai-ocr-edit-modal {
                position: absolute !important;
                inset: 0 !important;
                z-index: 26000 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                background: rgba(0, 0, 0, 0.5) !important;
                backdrop-filter: blur(8px) saturate(1.1) !important;
                -webkit-backdrop-filter: blur(8px) saturate(1.1) !important;
                padding: 16px !important;
                box-sizing: border-box !important;
                transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }
            #ai-ocr-edit-modal.hidden {
                display: none !important;
            }
            .ai-ocr-edit-dialog {
                width: min(560px, 100%) !important;
                max-height: min(680px, 100%) !important;
                box-sizing: border-box !important;
                display: flex !important;
                flex-direction: column !important;
                background: var(--panel) !important;
                background-image: radial-gradient(circle at 1px 1px, color-mix(in srgb, var(--text-secondary) 8%, transparent) 1px, transparent 0) !important;
                background-size: 16px 16px !important;
                border: 1px solid color-mix(in srgb, var(--border) 85%, transparent) !important;
                border-radius: 16px !important;
                box-shadow: 0 24px 64px rgba(0, 0, 0, 0.32), 0 0 0 1px color-mix(in srgb, var(--accent) 10%, transparent) inset, 0 0 80px color-mix(in srgb, var(--accent) 8%, transparent) !important;
                overflow: hidden !important;
                animation: ocr-dialog-enter 0.32s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
            }
            @keyframes ocr-dialog-enter {
                from {
                    opacity: 0 !important;
                    transform: scale(0.96) translateY(12px) !important;
                }
                to {
                    opacity: 1 !important;
                    transform: scale(1) translateY(0) !important;
                }
            }
            .ai-ocr-edit-header {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                padding: 16px 18px !important;
                border-bottom: 1px solid color-mix(in srgb, var(--border) 70%, transparent) !important;
                font-weight: 600 !important;
                font-size: 15px !important;
                color: var(--text-primary) !important;
                background: linear-gradient(180deg, color-mix(in srgb, var(--bg-secondary) 40%, transparent) 0%, transparent 100%) !important;
                letter-spacing: 0.01em !important;
            }
            .ai-ocr-edit-body {
                padding: 16px 18px !important;
                display: flex !important;
                flex-direction: column !important;
                gap: 14px !important;
                overflow: auto !important;
                background: color-mix(in srgb, var(--bg-secondary) 22%, transparent) !important;
                /* Custom scrollbar styling */
                scrollbar-width: thin !important;
                scrollbar-color: rgba(133, 181, 3, 0.5) rgba(30, 30, 30, 0.3) !important;
            }
            .ai-ocr-edit-body::-webkit-scrollbar {
                width: 10px !important;
            }
            .ai-ocr-edit-body::-webkit-scrollbar-track {
                background: rgba(30, 30, 30, 0.3) !important;
                border-radius: 999px !important;
                margin: 4px 0 !important;
            }
            .ai-ocr-edit-body::-webkit-scrollbar-thumb {
                background: rgba(133, 181, 3, 0.5) !important;
                border-radius: 999px !important;
                border: 2px solid rgba(30, 30, 30, 0.5) !important;
                background-clip: padding-box !important;
                transition: background 0.2s ease !important;
            }
            .ai-ocr-edit-body::-webkit-scrollbar-thumb:hover {
                background: rgba(133, 181, 3, 0.7) !important;
                border: 2px solid rgba(30, 30, 30, 0.5) !important;
                background-clip: padding-box !important;
            }
            .ai-ocr-edit-row {
                display: flex !important;
                flex-direction: column !important;
                gap: 8px !important;
                background: color-mix(in srgb, var(--bg-secondary) 36%, transparent) !important;
                border: 1px solid color-mix(in srgb, var(--border) 75%, transparent) !important;
                border-radius: 12px !important;
                padding: 10px !important;
            }
            .ai-ocr-edit-row label {
                font-size: 12px !important;
                font-weight: 500 !important;
                color: var(--text-secondary) !important;
                letter-spacing: 0.02em !important;
            }
            .ai-ocr-lang-select,
            .ai-ocr-textarea {
                width: 100% !important;
                border-radius: 12px !important;
                border: 1.5px solid color-mix(in srgb, var(--border) 80%, transparent) !important;
                background: color-mix(in srgb, var(--panel) 88%, #000 12%) !important;
                color: var(--text-primary) !important;
                font-size: 13px !important;
                padding: 10px 12px !important;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }
            .ai-ocr-lang-select {
                cursor: pointer !important;
                appearance: none !important;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
                background-repeat: no-repeat !important;
                background-position: right 12px center !important;
                padding-right: 32px !important;
            }
            .ai-ocr-lang-select:focus,
            .ai-ocr-textarea:focus {
                outline: none !important;
                border-color: color-mix(in srgb, var(--accent) 50%, var(--border)) !important;
                box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 15%, transparent) !important;
                background: color-mix(in srgb, var(--panel) 92%, #000 8%) !important;
            }
            .ai-ocr-textarea {
                min-height: 200px !important;
                max-height: 320px !important;
                resize: vertical !important;
                font-family: 'JetBrains Mono', 'Fira Code', 'Consolas', monospace !important;
                line-height: 1.6 !important;
                letter-spacing: 0.01em !important;
            }
            /* Text validation styles */
            .ai-ocr-textarea.valid-text {
                border-color: color-mix(in srgb, #22c55e 50%, var(--border)) !important;
                background: color-mix(in srgb, #22c55e 6%, var(--bg-secondary)) !important;
            }
            .ai-ocr-textarea.valid-text:focus {
                box-shadow: 0 0 0 4px color-mix(in srgb, #22c55e 18%, transparent) !important;
            }
            .ai-ocr-textarea.invalid-text {
                border-color: color-mix(in srgb, #ef4444 45%, var(--border)) !important;
                background: color-mix(in srgb, #ef4444 4%, var(--bg-secondary)) !important;
            }
            .ai-ocr-textarea.invalid-text:focus {
                box-shadow: 0 0 0 4px color-mix(in srgb, #ef4444 15%, transparent) !important;
            }
            .ai-ocr-text-validation-hint {
                font-size: 11px !important;
                color: var(--text-secondary) !important;
                margin-top: 4px !important;
                display: flex !important;
                align-items: center !important;
                gap: 6px !important;
                transition: color 0.2s ease !important;
            }
            .ai-ocr-text-validation-hint.valid {
                color: color-mix(in srgb, #22c55e 85%, var(--text-secondary)) !important;
            }
            .ai-ocr-text-validation-hint.invalid {
                color: color-mix(in srgb, #ef4444 85%, var(--text-secondary)) !important;
            }
            .ai-ocr-text-validation-hint svg {
                width: 14px !important;
                height: 14px !important;
                flex-shrink: 0 !important;
            }
            .ai-ocr-progress-wrap {
                display: flex !important;
                flex-direction: column !important;
                gap: 6px !important;
            }
            .ai-ocr-progress-wrap.hidden {
                display: none !important;
            }
            .ai-ocr-progress-label {
                font-size: 11px !important;
                letter-spacing: 0.02em !important;
                color: var(--text-secondary) !important;
                font-weight: 500 !important;
            }
            .ai-ocr-progress-track {
                height: 10px !important;
                border-radius: 999px !important;
                background: rgba(30, 30, 30, 0.4) !important;
                border: 1px solid rgba(133, 181, 3, 0.15) !important;
                box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2) !important;
                overflow: hidden !important;
                position: relative !important;
            }
            .ai-ocr-progress-fill {
                height: 100% !important;
                width: 0%;
                border-radius: 999px !important;
                background: linear-gradient(
                    90deg,
                    #22c55e 0%,
                    #16a34a 50%,
                    var(--accent) 100%
                ) !important;
                box-shadow: 0 0 12px rgba(133, 181, 3, 0.4), 0 0 6px rgba(133, 181, 3, 0.3) inset !important;
                transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1) !important;
                position: relative !important;
                overflow: hidden !important;
            }
            .ai-ocr-edit-footer {
                display: flex !important;
                flex-wrap: wrap !important;
                gap: 8px !important;
                justify-content: flex-end !important;
                padding: 12px 16px !important;
                border-top: 1px solid color-mix(in srgb, var(--border) 70%, transparent) !important;
                background: linear-gradient(0deg, color-mix(in srgb, var(--bg-secondary) 35%, transparent) 0%, transparent 100%) !important;
            }
            .ai-ocr-edit-footer .btn-ocr-secondary {
                border: 1px solid var(--border) !important;
                background: var(--bg-secondary) !important;
                color: var(--text-primary) !important;
                border-radius: 8px !important;
                padding: 8px 14px !important;
                font-size: 13px !important;
                cursor: pointer !important;
            }
            .ai-ocr-edit-footer .btn-ocr-primary {
                border: none !important;
                background: var(--accent) !important;
                color: #fff !important;
                border-radius: 8px !important;
                padding: 8px 14px !important;
                font-size: 13px !important;
                cursor: pointer !important;
            }
            .ai-ocr-confidence {
                font-size: 12px !important;
                color: var(--text-secondary) !important;
            }

            .mentioned-file-chip-name {
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                max-width: 240px !important;
            }
            .mentioned-file-chip-icon {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 16px !important;
                height: 16px !important;
                flex: 0 0 16px !important;
            }
            .mentioned-file-chip-icon .app-file-icon,
            .mentioned-file-chip-icon img {
                width: 16px !important;
                height: 16px !important;
            }

            .remove-mentioned-file {
                border: none !important;
                background: transparent !important;
                color: var(--text-secondary) !important;
                cursor: pointer !important;
                font-size: 14px !important;
                line-height: 1 !important;
                padding: 0 !important;
            }

            .mention-suggestions {
                position: absolute !important;
                left: 20px !important;
                bottom: calc(100% + 8px) !important;
                display: flex !important;
                flex-direction: column !important;
                max-height: 220px !important;
                overflow-y: auto !important;
                width: max-content !important;
                min-width: 240px !important;
                max-width: min(560px, calc(100% - 40px)) !important;
                border: 1px solid var(--border) !important;
                border-radius: 10px !important;
                background: var(--panel) !important;
                box-shadow: 0 10px 28px rgba(0, 0, 0, 0.25) !important;
                z-index: 20 !important;
            }

            .mention-suggestions.hidden {
                display: none !important;
            }

            .mention-suggestion-item {
                padding: 8px 12px !important;
                font-size: 12px !important;
                border-bottom: 1px solid var(--border) !important;
                cursor: pointer !important;
                color: var(--text-primary) !important;
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
            }

            .mention-suggestion-item:last-child {
                border-bottom: none !important;
            }

            .mention-suggestion-item.active {
                background: rgba(133, 181, 3, 0.18) !important;
                color: var(--fg) !important;
            }

            .mention-suggestion-icon {
                opacity: 0.8 !important;
                font-size: 13px !important;
            }

            .mention-suggestion-icon .app-file-icon,
            .mention-suggestion-icon .app-file-icon img {
                width: 14px !important;
                height: 14px !important;
            }

            .mention-suggestion-name {
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }

            .mention-suggestion-empty {
                padding: 8px 12px !important;
                font-size: 12px !important;
                color: var(--text-secondary) !important;
            }
            
            /* Context indicator */
            .context-indicator {
                font-size: 14px !important;
                margin-left: 4px !important;
                transition: opacity 0.2s ease !important;
            }
            
            .context-indicator.active {
                opacity: 1 !important;
            }
            
            .context-indicator.inactive {
                opacity: 0.5 !important;
            }
            
            /* Think block styles */
            .think-block {
                margin: 10px 0;
                border: 1px solid rgba(255,255,255,0.08);
                border-radius: 12px;
                background: color-mix(in srgb,var(--panel) 82%,var(--bg));
                overflow: hidden;
                transition: border-color 0.25s ease;
            }
            
            .think-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 12px 14px;
                cursor: pointer;
                user-select: none;
            }
            
            .think-block.open {
                border-color: rgba(255,255,255,0.2);
            }
            
            .think-header-left {
                display: flex;
                align-items: center;
                gap: 6px;
                font-size: 12px;
                font-weight: 500;
                color: color-mix(in srgb,var(--fg) 50%,transparent);
            }
            
            .think-header-left svg {
                width: 16px;
                height: 16px;
            }
            
            .think-header-right {
                font-size: 18px;
                color: color-mix(in srgb,var(--fg) 30%,transparent);
                line-height: 1;
                transition: transform 0.3s ease,color 0.3s ease;
            }
            
            .think-block.open .think-header-right {
                transform: rotate(45deg);
                color: var(--fg);
            }
            
            .think-content {
                max-height: 0;
                overflow: hidden;
                padding: 0 14px;
                font-size: 13px;
                line-height: 1.7;
                color: color-mix(in srgb,var(--fg) 45%,transparent);
                white-space: normal;
                font-style: normal;
                font-family: inherit;
                transition: max-height 0.35s ease,padding 0.35s ease;
            }

            .think-content .message-content-inner > *:first-child {
                margin-top: 0;
            }

            .think-content p {
                margin: 0 0 8px;
            }

            .think-content ul,
            .think-content ol {
                margin: 0 0 8px;
                padding-left: 18px;
            }

            .think-content pre,
            .think-content .code-block {
                margin: 8px 0;
            }

            .think-block.open .think-content {
                max-height: 360px;
                padding: 0 14px 14px;
                overflow-y: auto;
            }
            
            /* Message stats */
            .ai-chat .message-footer {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 6px;
                margin-top: 2px;
                padding: 0 4px;
            }
            .ai-chat .message-stats,
            .ai-chat .stat-item {
                display: none !important;
            }

            .ai-chat .message-actions {
                display: flex;
                align-items: center;
                gap: 8px;
                white-space: nowrap;
                flex: 0 0 auto; /* move as one block to next row if needed */
                margin-left: auto;
            }
            .ai-chat .action-button {
                display: inline-flex;
                align-items: center;
                white-space: nowrap;
                font-size: 9px;
                padding: 4px 8px;
                gap: 5px;
            }
            .ai-chat .action-button svg {
                flex: 0 0 auto;
            }
            .ai-chat .action-button span {
                white-space: nowrap;
            }

            /* Chat scroll navigation rail */
            .ai-chat-scrollbar {
                position: absolute !important;
                top: 0 !important;
                width: 10px !important;
                border-radius: 6px !important;
                background: transparent !important;
                z-index: 5 !important;
                opacity: .3 !important;
                transition: opacity .15s ease !important;
            }
            .ai-chat-scrollbar:hover {
                opacity: 1 !important;
                background: color-mix(in srgb, var(--muted) 14%, transparent) !important;
            }
            .ai-chat-scroll-marker {
                position: absolute !important;
                left: 2px !important;
                right: 2px !important;
                height: 3px !important;
                min-height: 3px !important;
                border-radius: 3px !important;
                background: var(--accent) !important;
                opacity: .55 !important;
                cursor: pointer !important;
                transition: opacity .12s ease, height .12s ease !important;
            }
            .ai-chat-scroll-marker:hover {
                opacity: 1 !important;
            }
            .ai-chat-scroll-marker.active {
                opacity: 1 !important;
                height: 5px !important;
            }

            /* Live think indicator */
            .think-live-indicator {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                margin-left: 8px;
                font-size: 11px;
                color: var(--accent);
                animation: pulse 1.5s infinite;
            }
            
            @keyframes pulse {
                0%, 100% { opacity: 0.6; }
                50% { opacity: 1; }
            }
            
            /* Markdown headers */
            .message-content h1 {
                font-size: 2em !important;
                margin: 0.67em 0 !important;
                font-weight: bold !important;
            }
            
            .message-content h2 {
                font-size: 1.5em !important;
                margin: 0.83em 0 !important;
                font-weight: bold !important;
            }
            
            .message-content h3 {
                font-size: 1.17em !important;
                margin: 1em 0 !important;
                font-weight: bold !important;
            }
            
            /* Code blocks */
            .code-block {
                margin: 1em 0 !important;
                border: 1px solid var(--border) !important;
                border-radius: 8px !important;
                position: relative !important;
                max-height: none !important;
                overflow: visible !important;
            }
            
            .code-header {
                padding: 8px 12px !important;
                background: var(--panel-header, var(--panel)) !important;
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                border-bottom: 1px solid var(--border) !important;
                border-radius: 8px 8px 0 0 !important;
                position: sticky !important;
                top: -32px !important;
                z-index: 5 !important;
            }

            .code-header.is-sticky::before {
                content: "";
                position: absolute;
                left: -1px;
                right: -1px;
                top: -32px;
                height: 32px;
                background: var(--panel-header, var(--panel));
                pointer-events: none;
            }

            /* Kept for compatibility with older rendered messages */
            .code-header.is-sticky {
                background: var(--panel-header, var(--panel)) !important;
                position: sticky !important;
                top: -32px !important;
                z-index: 5 !important;
                border-radius: 8px 8px 0 0 !important;
                box-shadow: none !important;
            }
            
            .code-language {
                font-size: 12px;
                color: var(--text-secondary);
                text-transform: uppercase;
            }
            
            .code-copy-btn {
                padding: 4px 8px;
            }
            
            .code-block pre {
                margin: 0 !important;
                padding: 0 !important;
                overflow-x: auto !important;
                overflow-y: visible !important;
                background: transparent !important;
                border-radius: 0 0 8px 8px !important;
            }

            /* highlight.js code element — adapts to site theme */
            .code-block pre code.hljs {
                display: block !important;
                padding: 12px !important;
                border-radius: 0 0 8px 8px !important;
                font-family: 'Consolas', 'JetBrains Mono', monospace !important;
                font-size: 13px !important;
                line-height: 1.5 !important;
                background: var(--code-bg) !important;
                color: var(--fg) !important;
            }

            /* fallback without hljs */
            .code-block pre code:not(.hljs) {
                display: block !important;
                padding: 12px !important;
                background: var(--code-bg) !important;
                color: var(--fg) !important;
                border-radius: 0 0 8px 8px !important;
                font-family: 'Consolas', monospace !important;
                font-size: 13px !important;
                line-height: 1.5 !important;
            }
            
            /* Math styles */
            .math-inline {
                font-family: 'Times New Roman', serif;
                font-style: italic;
                padding: 0 2px;
            }
            
            .math-display {
                display: block;
                text-align: center;
                margin: 1em 0;
                padding: 0.5em;
                font-family: 'Times New Roman', serif;
                font-style: italic;
                background: var(--bg-secondary);
                border-radius: 4px;
            }
        `;
        
        document.head.appendChild(style);
    }

    function getHljsThemeUrl() {
        const isDark = document.body.getAttribute('data-theme') === 'dark';
        const theme = isDark ? 'atom-one-dark' : 'atom-one-light';
        return `https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/${theme}.min.css`;
    }

    function updateHljsTheme() {
        const link = document.getElementById('hljs-theme-link');
        if (link) {
            link.href = getHljsThemeUrl();
        }
    }

    // Load highlight.js from CDN
    function loadHighlightJS() {
        if (window.hljs) return;
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.id = 'hljs-theme-link';
        link.href = getHljsThemeUrl();
        document.head.appendChild(link);

        const themeObserver = new MutationObserver(updateHljsTheme);
        themeObserver.observe(document.body, { attributes: true, attributeFilter: ['data-theme'] });

        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js';
        script.onload = () => {
            window._hljsReady = true;
            // Re-highlight any already-rendered code blocks
            document.querySelectorAll('.code-block pre code[data-lang]').forEach(el => {
                const lang = el.getAttribute('data-lang');
                if (lang && window.hljs.getLanguage(lang)) {
                    el.innerHTML = window.hljs.highlight(el.textContent, { language: lang }).value;
                } else {
                    el.innerHTML = window.hljs.highlightAuto(el.textContent).value;
                }
                el.classList.add('hljs');
            });
            // Load extra languages
            ['matlab', 'tex', 'latex'].forEach(lang => {
                const s = document.createElement('script');
                s.src = `https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/${lang}.min.js`;
                document.head.appendChild(s);
            });
        };
        document.head.appendChild(script);
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', async function() {
        loadSavedSettings();
        await loadUserSettingsFromDb();
        rememberCurrentUserSettingsSnapshot();
        addVisualFixes();
        loadHighlightJS();
        await initAIAssistant();
        rememberCurrentUserSettingsSnapshot();
    });

    async function reloadAiAssistantConfig() {
        AI_CONFIG.directApi.configured = false;
        AI_CONFIG.configuredModels = null;
        await ensureDirectApiKeyLoaded();
        await loadModelsFromApi();
    }
    window.reloadAiAssistantConfig = reloadAiAssistantConfig;
    async function initAIAssistant() {
        cacheElements();
        attachEventListeners();
        applySavedSettings();
        initCustomModelDropdown();
        initChatScrollbar();
        try {
            await loadModelsFromApi();
        } catch (e) {
            console.warn('Failed to load models from API:', e);
            renderModelDropdownOptions();
            updateModelDropdownLabel();
        }
        refreshAttachI18n();
        await loadAiChatSessions();
        if (elements.chat && !elements.chat.querySelector('.message')) {
            await loadChatHistoryPage(false);
            await resumeServerAiRunIfNeeded();
        }
        
        // Make functions globally available
        window.toggleAIModal = toggleAIModal;
        window.sendAIMessage = sendAIMessage;
        window.clearChatHistory = clearChatHistory;
        window.regenerateResponse = regenerateResponse;
        window.attachExternalFile = attachExternalFile;
    }

    function applySavedSettings() {
        if (elements.modelSelect && aiState.selectedModel) {
            let optionExists = false;
            for (let i = 0; i < elements.modelSelect.options.length; i++) {
                if (elements.modelSelect.options[i].value === aiState.selectedModel) {
                    optionExists = true;
                    break;
                }
            }
            if (optionExists) {
                elements.modelSelect.value = aiState.selectedModel;
            }
            updateModelDropdownLabel();
        }
        
        if (elements.roleSelect && aiState.selectedRole) {
            let optionExists = false;
            for (let i = 0; i < elements.roleSelect.options.length; i++) {
                if (elements.roleSelect.options[i].value === aiState.selectedRole) {
                    optionExists = true;
                    break;
                }
            }
            if (optionExists) {
                elements.roleSelect.value = aiState.selectedRole;
            }
        }
        if (elements.modeSelect) elements.modeSelect.checked = (aiState.chatMode || 'chat') === 'agent';
        if (elements.notifyCheckbox) elements.notifyCheckbox.checked = !!aiState.notifyOnComplete;
        updateAgentModeAvailability();
    }

    function cacheElements() {
        elements = {
            modal: document.getElementById('ai-modal'),
            closeBtn: document.getElementById('btn-close-ai'),
            sendBtn: document.getElementById('btn-ai-send'),
            input: document.getElementById('ai-input-text'),
            chat: document.getElementById('ai-chat'),
            agentActivity: document.getElementById('ai-agent-activity'),
            modelSelect: document.getElementById('ai-model-select'),
            modeSelect: document.getElementById('ai-mode-select'),
            roleSelect: document.getElementById('ai-role-select'),
            clearHistoryBtn: document.getElementById('btn-clear-ai-history'),
            chatSelect: document.getElementById('ai-chat-select'),
            newChatBtn: document.getElementById('btn-ai-new-chat'),
            toggleBtn: document.getElementById('btn-ai'),
            attachFileBtn: document.getElementById('btn-attach-file'),
            settingsBtn: document.getElementById('btn-ai-settings'),
            settingsModal: document.getElementById('ai-controls-modal'),
            attachWrap: document.querySelector('.ai-attach-wrap'),
            attachDropdown: document.getElementById('ai-attach-dropdown'),
            fileInputImage: document.getElementById('ai-file-input-image'),
            toggleContextBtn: null,
            contextIndicator: document.getElementById('context-indicator'),
            attachedFilesList: document.getElementById('ai-attached-files-list'),
            attachedMentionsInfo: document.getElementById('attached-mentions-info'),
            mentionSuggestions: document.getElementById('mention-suggestions'),
            rememberCheckbox: document.getElementById('ai-remember-prev'),
            notifyCheckbox: document.getElementById('ai-notify-complete'),
            attachmentsRow: document.getElementById('ai-attachments-row')
        };
        
        ensureElementsExist();

        elements.attachFileBtn = document.getElementById('btn-attach-file') || elements.attachFileBtn;
        elements.attachWrap = document.querySelector('#ai-modal .ai-attach-wrap') || elements.attachWrap;
        elements.attachDropdown = document.getElementById('ai-attach-dropdown') || elements.attachDropdown;
        elements.fileInputImage = document.getElementById('ai-file-input-image') || elements.fileInputImage;
        elements.attachedFilesList = document.getElementById('ai-attached-files-list') || elements.attachedFilesList;
        elements.modeSelect = document.getElementById('ai-mode-select') || elements.modeSelect;
        elements.settingsBtn = document.getElementById('btn-ai-settings') || elements.settingsBtn;
        elements.settingsModal = document.getElementById('ai-controls-modal') || elements.settingsModal;
        ensureChatSessionControls();
        
        if (elements.modelSelect) {
            elements.modelSelect.addEventListener('change', () => {
                saveSettings();
                updateModelDropdownLabel();
                renderModelDropdownOptions();
            });
        }
        if (elements.roleSelect) {
            elements.roleSelect.addEventListener('change', saveSettings);
        }
    }

    function ensureElementsExist() {
        if (!elements.modal) return;
        
        const modalContent = elements.modal.querySelector('.ai-modal-content');
        if (!modalContent) return;
        ensureModalResizeHandle(modalContent);
        const aiControls = modalContent.querySelector('.ai-controls');

        if (!elements.attachmentsRow) {
            const aiInput = modalContent.querySelector('.ai-input');
            if (aiInput && elements.input) {
                const attachmentsRow = document.createElement('div');
                attachmentsRow.id = 'ai-attachments-row';
                attachmentsRow.className = 'ai-attachments-row hidden';
                aiInput.insertBefore(attachmentsRow, elements.input);
                elements.attachmentsRow = attachmentsRow;
            }
        }
        
        modalContent.querySelector('.ai-context-switch')?.remove();
        elements.toggleContextBtn = null;

        elements.rememberCheckbox?.closest('.ai-setting-row, .ai-remember-switch')?.remove();
        elements.rememberCheckbox = null;
        elements.clearHistoryBtn?.closest('.ai-setting-row')?.remove();

        if (!elements.notifyCheckbox) {
            if (modalContent) {
                const wrap = document.createElement('label');
                wrap.className = 'ai-mode-switch ai-notify-switch';
                wrap.title = tr('aiNotifyOnComplete', 'Notify when assistant finishes');
                wrap.innerHTML = '<input type="checkbox" id="ai-notify-complete"><span class="ai-mode-switch-track"><span class="ai-mode-switch-label">Off</span><span class="ai-mode-switch-label">On</span><span class="ai-mode-switch-thumb"></span></span>';
                modalContent.appendChild(wrap);
                elements.notifyCheckbox = wrap.querySelector('#ai-notify-complete');
                if (elements.notifyCheckbox) {
                    elements.notifyCheckbox.checked = !!aiState.notifyOnComplete;
                    elements.notifyCheckbox.addEventListener('change', () => {
                        void setNotifyOnCompleteEnabled(elements.notifyCheckbox.checked);
                    });
                }
            }
        }
        
        if (!elements.attachFileBtn) {
            const aiInput = modalContent.querySelector('.ai-input');
            if (aiInput) {
                const wrap = document.createElement('div');
                wrap.className = 'ai-attach-wrap';

                const attachBtn = document.createElement('button');
                attachBtn.id = 'btn-attach-file';
                attachBtn.type = 'button';
                attachBtn.className = 'icon-btn small';
                attachBtn.title = tr('aiAttachExternalFile', 'Attach external file');
                attachBtn.innerHTML = `
                    <svg width="18" height="18" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
                    </svg>
                `;

                const dropdown = document.createElement('div');
                dropdown.id = 'ai-attach-dropdown';
                dropdown.className = 'ai-attach-dropdown hidden';
                dropdown.setAttribute('role', 'menu');
                dropdown.innerHTML = `
                    <button type="button" class="ai-attach-dropdown-item" data-ai-attach-mode="image" role="menuitem">
                        <svg width="18" height="18" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="14" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="10.5" r="1.5" fill="currentColor" stroke="none"></circle>
                            <path d="M21 15l-5-5L5 21"></path>
                        </svg>
                        <span data-i18n-ai="aiAttachMenuPhoto">${escapeHtml(tr('aiAttachMenuPhoto', 'Photo'))}</span>
                    </button>
                    <button type="button" class="ai-attach-dropdown-item" data-ai-attach-mode="text" role="menuitem">
                        <svg width="18" height="18" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" aria-hidden="true">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                        <span data-i18n-ai="aiAttachMenuTextFiles">${escapeHtml(tr('aiAttachMenuTextFiles', 'Text files'))}</span>
                    </button>
                `;

                wrap.appendChild(attachBtn);
                wrap.appendChild(dropdown);
                aiInput.insertBefore(wrap, elements.input);

                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.id = 'ai-file-input';
                fileInput.style.display = 'none';
                fileInput.multiple = true;
                fileInput.accept = AI_TEXT_FILE_ACCEPT + ',' + AI_DOCUMENT_ACCEPT;
                aiInput.appendChild(fileInput);

                const fileInputImage = document.createElement('input');
                fileInputImage.type = 'file';
                fileInputImage.id = 'ai-file-input-image';
                fileInputImage.style.display = 'none';
                fileInputImage.multiple = true;
                fileInputImage.accept = AI_IMAGE_ACCEPT;
                aiInput.appendChild(fileInputImage);

                const fileInfo = document.createElement('div');
                fileInfo.id = 'ai-attached-files-list';
                fileInfo.className = 'attached-files-list hidden';
                if (elements.attachmentsRow) {
                    elements.attachmentsRow.appendChild(fileInfo);
                } else {
                    aiInput.insertBefore(fileInfo, elements.input);
                }

                elements.attachFileBtn = attachBtn;
                elements.attachWrap = wrap;
                elements.attachDropdown = dropdown;
                elements.attachedFilesList = fileInfo;
            }
        } else if (elements.attachFileBtn && !elements.attachWrap) {
            const aiInput = modalContent.querySelector('.ai-input');
            if (aiInput && !document.getElementById('ai-attach-dropdown')) {
                const btn = elements.attachFileBtn;
                const wrap = document.createElement('div');
                wrap.className = 'ai-attach-wrap';
                btn.parentNode.insertBefore(wrap, btn);
                wrap.appendChild(btn);

                const dropdown = document.createElement('div');
                dropdown.id = 'ai-attach-dropdown';
                dropdown.className = 'ai-attach-dropdown hidden';
                dropdown.setAttribute('role', 'menu');
                dropdown.innerHTML = `
                    <button type="button" class="ai-attach-dropdown-item" data-ai-attach-mode="image" role="menuitem">
                        <svg width="18" height="18" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="14" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="10.5" r="1.5" fill="currentColor" stroke="none"></circle>
                            <path d="M21 15l-5-5L5 21"></path>
                        </svg>
                        <span data-i18n-ai="aiAttachMenuPhoto">${escapeHtml(tr('aiAttachMenuPhoto', 'Photo'))}</span>
                    </button>
                    <button type="button" class="ai-attach-dropdown-item" data-ai-attach-mode="text" role="menuitem">
                        <svg width="18" height="18" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" aria-hidden="true">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                        <span data-i18n-ai="aiAttachMenuTextFiles">${escapeHtml(tr('aiAttachMenuTextFiles', 'Text files'))}</span>
                    </button>
                `;
                wrap.appendChild(dropdown);

                if (!document.getElementById('ai-file-input-image')) {
                    const fileInputImage = document.createElement('input');
                    fileInputImage.type = 'file';
                    fileInputImage.id = 'ai-file-input-image';
                    fileInputImage.style.display = 'none';
                    fileInputImage.multiple = true;
                    fileInputImage.accept = AI_IMAGE_ACCEPT;
                    aiInput.appendChild(fileInputImage);
                }
                const fi = document.getElementById('ai-file-input');
                if (fi) {
                    fi.multiple = true;
                    fi.accept = AI_TEXT_FILE_ACCEPT + ',' + AI_DOCUMENT_ACCEPT;
                }
                const fii = document.getElementById('ai-file-input-image');
                if (fii) {
                    fii.multiple = true;
                    fii.accept = AI_IMAGE_ACCEPT;
                }
                if (!document.getElementById('ai-attached-files-list') && aiInput) {
                    const list = document.createElement('div');
                    list.id = 'ai-attached-files-list';
                    list.className = 'attached-files-list hidden';
                    if (elements.attachmentsRow) elements.attachmentsRow.appendChild(list);
                    else aiInput.insertBefore(list, elements.input);
                    elements.attachedFilesList = list;
                }

                elements.attachWrap = wrap;
                elements.attachDropdown = dropdown;
            }
        }

        if (!elements.mentionSuggestions) {
            const aiInput = modalContent.querySelector('.ai-input');
            if (aiInput) {
                const suggestions = document.createElement('div');
                suggestions.id = 'mention-suggestions';
                suggestions.className = 'mention-suggestions hidden';
                aiInput.appendChild(suggestions);
                elements.mentionSuggestions = suggestions;
            }
        }

        if (!elements.attachedMentionsInfo) {
            const aiInput = modalContent.querySelector('.ai-input');
            if (aiInput) {
                const mentionsInfo = document.createElement('div');
                mentionsInfo.id = 'attached-mentions-info';
                mentionsInfo.className = 'attached-mentions-info hidden';
                if (elements.attachmentsRow) {
                    elements.attachmentsRow.appendChild(mentionsInfo);
                } else {
                    aiInput.insertBefore(mentionsInfo, elements.input);
                }
                elements.attachedMentionsInfo = mentionsInfo;
            }
        }

        // Copy button is now rendered per user message (not in composer)
        if (elements.attachmentsRow) {
            if (elements.attachedMentionsInfo && elements.attachedMentionsInfo.parentElement !== elements.attachmentsRow) {
                elements.attachmentsRow.appendChild(elements.attachedMentionsInfo);
            }
            if (elements.attachedFilesList && elements.attachedFilesList.parentElement !== elements.attachmentsRow) {
                elements.attachmentsRow.appendChild(elements.attachedFilesList);
            }
        }
        ensureControlsModal(modalContent);
        if (aiControls) aiControls.remove();
    }

    function ensureControlsModal(modalContent) {
        if (!modalContent) return;
        const aiInput = modalContent.querySelector('.ai-input');
        if (!elements.modeSelect) {
            const modeWrap = document.createElement('label');
            modeWrap.className = 'ai-mode-switch';
            modeWrap.innerHTML = '<input type="checkbox" id="ai-mode-select"><span class="ai-mode-switch-track"><span class="ai-mode-switch-label ai-mode-chat" data-i18n-ai="aiModeChat">Chat</span><span class="ai-mode-switch-label ai-mode-agent" data-i18n-ai="aiModeAgent">Agent</span><span class="ai-mode-switch-thumb"></span></span>';
            elements.modeSelect = modeWrap.querySelector('#ai-mode-select');
        }
        let selectorsRow = aiInput?.querySelector('.ai-input-selectors-row') || null;
        if (!selectorsRow && aiInput && (elements.modelSelect?.parentElement || elements.modeSelect?.closest('.ai-mode-switch'))) {
            selectorsRow = document.createElement('div');
            selectorsRow.className = 'ai-input-selectors-row';
            aiInput.insertBefore(selectorsRow, aiInput.firstChild);
        }
        const modelWrap = elements.modelSelect?.parentElement || null;
        const modeWrap = elements.modeSelect?.closest('.ai-mode-switch') || null;
        if (selectorsRow && modelWrap && modelWrap.parentElement !== selectorsRow) {
            selectorsRow.appendChild(modelWrap);
        }
        if (selectorsRow && modeWrap && modeWrap.parentElement !== selectorsRow) {
            selectorsRow.appendChild(modeWrap);
        }
        if (!elements.settingsBtn && elements.attachWrap) {
            const btn = document.createElement('button');
            btn.id = 'btn-ai-settings';
            btn.type = 'button';
            btn.className = 'icon-btn small';
            btn.title = tr('aiSettings', 'Settings');
            btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V22a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H2a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0a1.65 1.65 0 0 0 1-1.51V2a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v0a1.65 1.65 0 0 0 1.51 1H22a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>';
            elements.attachWrap.insertAdjacentElement('afterend', btn);
            elements.settingsBtn = btn;
        }
        let inputActionStack = aiInput?.querySelector('.ai-input-action-stack') || null;
        if (!inputActionStack && aiInput && (elements.settingsBtn || elements.attachWrap)) {
            inputActionStack = document.createElement('div');
            inputActionStack.className = 'ai-input-action-stack';
            aiInput.insertBefore(inputActionStack, elements.input || aiInput.firstChild);
        }
        if (inputActionStack && elements.settingsBtn && elements.settingsBtn.parentElement !== inputActionStack) {
            inputActionStack.appendChild(elements.settingsBtn);
        }
        if (inputActionStack && elements.attachWrap && elements.attachWrap.parentElement !== inputActionStack) {
            inputActionStack.appendChild(elements.attachWrap);
        }
        if (!elements.settingsModal) {
            const modal = document.createElement('div');
            modal.id = 'ai-controls-modal';
            modal.className = 'ai-controls-modal hidden';
            modal.innerHTML = '<div class="ai-controls-modal-card"><div class="ai-controls-modal-head"><strong data-i18n-ai="aiSettings">Settings</strong><button type="button" class="icon-btn small ai-controls-modal-close" aria-label="close"><svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button></div><div class="ai-controls-modal-body"></div></div>';
            modalContent.appendChild(modal);
            elements.settingsModal = modal;
        }
        const body = elements.settingsModal?.querySelector('.ai-controls-modal-body');
        if (!body) return;
        const movable = [];
        if (elements.notifyCheckbox?.parentElement) movable.push(elements.notifyCheckbox.parentElement);
        if (elements.roleSelect?.parentElement) movable.push(elements.roleSelect.parentElement);
        movable.forEach((el) => {
            if (!el || body.contains(el)) return;
            body.appendChild(el);
        });
        const ensureRow = (el, key, fallback) => {
            if (!el) return;
            if (el === body || el === elements.settingsModal || el === elements.settingsModal?.parentElement) return;
            if (el.parentElement?.classList.contains('ai-setting-row')) return;
            const row = document.createElement('div');
            row.className = 'ai-setting-row';
            const label = document.createElement('span');
            label.className = 'ai-setting-label';
            label.setAttribute('data-i18n-ai', key);
            label.textContent = tr(key, fallback);
            row.appendChild(label);
            row.appendChild(el);
            if (row.contains(body) || el.contains(body)) return;
            body.appendChild(row);
        };
        ensureRow(elements.notifyCheckbox?.parentElement, 'aiNotifyOnComplete', 'Notify when finished');
        ensureRow(elements.roleSelect?.parentElement, 'aiRole', 'Role');
    }

    function attachEventListeners() {
        if (elements.toggleBtn) {
            elements.toggleBtn.addEventListener('click', toggleAIModal);
        }

        if (elements.closeBtn) {
            elements.closeBtn.addEventListener('click', closeAIModal);
        }

        if (elements.sendBtn) {
            elements.sendBtn.addEventListener('click', () => {
                if (aiState.isGenerating) {
                    stopGeneration();
                    return;
                }
                sendAIMessage();
            });
        }
        if (elements.modeSelect) {
            elements.modeSelect.addEventListener('change', () => {
                aiState.chatMode = elements.modeSelect.checked ? 'agent' : 'chat';
                saveSettings();
            });
        }
        if (elements.settingsBtn) {
            elements.settingsBtn.addEventListener('click', () => {
                elements.settingsModal?.classList.remove('hidden');
            });
        }
        elements.settingsModal?.querySelector('.ai-controls-modal-close')?.addEventListener('click', () => {
            elements.settingsModal?.classList.add('hidden');
        });
        elements.settingsModal?.addEventListener('click', (e) => {
            if (e.target === elements.settingsModal) elements.settingsModal.classList.add('hidden');
        });

        if (elements.input) {
            elements.input.addEventListener('keydown', handleInputKeydown);
            elements.input.addEventListener('input', handleMentionInput);
            setupAiTreeDropZone();
        }

        if (elements.clearHistoryBtn) {
            elements.clearHistoryBtn.addEventListener('click', clearChatHistory);
        }

        // Removed: fixProblems / calculateData quick buttons

        if (elements.attachFileBtn) {
            elements.attachFileBtn.addEventListener('click', (ev) => {
                ev.preventDefault();
                ev.stopPropagation();
                toggleAttachDropdown();
            });
        }

        if (elements.attachDropdown) {
            elements.attachDropdown.addEventListener('click', (ev) => ev.stopPropagation());
            elements.attachDropdown.addEventListener('click', (ev) => {
                const item = ev.target.closest('[data-ai-attach-mode]');
                if (!item) return;
                ev.preventDefault();
                ev.stopPropagation();
                const mode = item.getAttribute('data-ai-attach-mode');
                closeAttachDropdown();
                if (mode === 'text') {
                    document.getElementById('ai-file-input')?.click();
                } else if (mode === 'image') {
                    document.getElementById('ai-file-input-image')?.click();
                }
            });
        }

        const fileInput = document.getElementById('ai-file-input');
        if (fileInput) {
            fileInput.addEventListener('change', handleFileAttach);
        }

        const fileInputImage = document.getElementById('ai-file-input-image');
        if (fileInputImage) {
            fileInputImage.addEventListener('change', handleImageFileAttach);
        }

        document.addEventListener('click', async (e) => {
            if (modelDropdownState.wrapper && !modelDropdownState.wrapper.contains(e.target)) closeModelDropdown();
            if (elements.attachWrap && elements.attachWrap.contains(e.target)) {
                // keep dropdown open until toggle or outside click
            } else {
                closeAttachDropdown();
            }

            const rmAtt = e.target.closest('.remove-attached-file');
            if (rmAtt && rmAtt.dataset.attachmentId) {
                removeExternalAttachment(rmAtt.dataset.attachmentId);
            }
            const removeMentionBtn = e.target.closest('.remove-mentioned-file');
            if (removeMentionBtn) {
                const name = removeMentionBtn.dataset.name || '';
                removeMentionedAttachment(name);
            }
            const confirmBtnClicked = e.target.closest('.ai-agent-confirm-btn');
            if (confirmBtnClicked) {
                const msg = confirmBtnClicked.closest('.message');
                const patchId = msg?.dataset.agentPatchId;
                if (!patchId) return;
                try {
                    if (Object.keys(aiState.pendingAgentPatches).length > 0) {
                        await applyAllPendingAgentChanges();
                    } else if (aiState.pendingAgentPatch && aiState.pendingAgentPatch.id === patchId) {
                        const beforeText = String(aiState.pendingAgentPatch.beforeText || '');
                        const applied = applyOpsToText(beforeText, aiState.pendingAgentPatch.ops);
                        if (!applied) {
                            flashStatus(tr('aiAgentPatchApplyFailed', 'Could not apply agent patch'));
                            return;
                        }
                        const ok = setActiveEditorContent(applied.text, []);
                        if (!ok) {
                            flashStatus(tr('aiAgentNoActiveEditor', 'No active editor for patch apply'));
                            return;
                        }
                        if (typeof window.saveFile === 'function') {
                            try { await window.saveFile(); } catch (e) { logAiError('Agent apply: saveFile', e); }
                        } else {
                            try { await sendIframeMonacoAiAction('save'); } catch (e) { logAiError('Agent apply: iframe save', e); }
                        }
                        clearEditorHighlights();
                        aiState.lastAppliedAgentPatch = { beforeText, afterText: applied.text, id: patchId };
                        aiState.pendingAgentPatch = null;
                    }
                    removeAgentActionFooter(msg);
                    flashStatus(tr('aiAgentChangesApplied', 'Agent changes applied'));
                } catch (err) {
                    logAiError('Agent patch apply', err);
                    flashStatus(err.message || tr('aiAgentApplyFailed', 'Apply failed'));
                }
                return;
            }
            const undoLastBtn = e.target.closest('.ai-agent-undo-last-btn');
            if (undoLastBtn) {
                const msg = undoLastBtn.closest('.message');
                if (!msg) return;
                try {
                    await revertLastAgentRun();
                    undoLastBtn.closest('.ai-agent-undo-last-footer')?.remove();
                    flashStatus(tr('aiAgentReverted', 'Changes reverted'));
                } catch (err) {
                    logAiError('Agent revert', err);
                    flashStatus(err.message || tr('aiAgentRevertFailed', 'Revert failed'));
                }
                return;
            }
            const undoBtnClicked = e.target.closest('.ai-agent-undo-btn');
            if (undoBtnClicked) {
                const msg = undoBtnClicked.closest('.message');
                if (!msg) return;
                const patchId = msg.dataset.agentPatchId;
                if (Object.keys(aiState.pendingAgentPatches).length > 0) {
                    cancelAllPendingAgentChanges();
                } else {
                    const pending = aiState.pendingAgentPatch;
                    const applied = pending && pending.id === patchId
                        ? { beforeText: pending.beforeText, isPending: true }
                        : aiState.lastAppliedAgentPatch;
                    if (!applied || (!applied.isPending && applied.id !== patchId)) return;
                    setActiveEditorContent(applied.beforeText, []);
                    if (pending && pending.id === patchId) pending.previewApplied = false;
                }
                clearEditorHighlights();
                removeAgentActionFooter(msg);
                flashStatus(tr('aiAgentChangesReverted', 'Agent changes reverted'));
                return;
            }

            if (elements.mentionSuggestions && !elements.mentionSuggestions.contains(e.target) && e.target !== elements.input) {
                hideMentionSuggestions();
            }

            const thumb = e.target.closest('.attached-file-thumb-wrap');
            const attRow = thumb?.closest('[data-attachment-id]');
            if (thumb && attRow && elements.attachedFilesList && elements.attachedFilesList.contains(thumb)) {
                if (e.target.closest('.remove-attached-file')) return;
                const id = attRow.dataset.attachmentId;
                const a = getAttachmentById(id);
                if (a?.kind === 'image') {
                    e.preventDefault();
                    openOcrEditModal(id);
                }
            }
        });

        if (elements.modal) {
            elements.modal.addEventListener('click', (e) => {
                if (e.target === elements.modal) {
                    closeAIModal();
                }
            });
        }
        setupModalResize();
        
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            const ocrModal = document.getElementById('ai-ocr-edit-modal');
            if (ocrModal && !ocrModal.classList.contains('hidden')) {
                e.preventDefault();
                e.stopPropagation();
                closeOcrEditModal();
                return;
            }
            if (isConfirmDialogOpen()) {
                return;
            }
            if (elements.settingsModal && !elements.settingsModal.classList.contains('hidden')) {
                e.preventDefault();
                elements.settingsModal.classList.add('hidden');
                return;
            }
            if (elements.modal && !elements.modal.classList.contains('hidden')) {
                if (!elements.attachDropdown?.classList.contains('hidden')) {
                    e.preventDefault();
                    closeAttachDropdown();
                    return;
                }
                e.preventDefault();
                closeAIModal();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Delete') return;
            if (!elements.modal || elements.modal.classList.contains('hidden')) return;
            const ocrModal = document.getElementById('ai-ocr-edit-modal');
            if (ocrModal && !ocrModal.classList.contains('hidden')) return;
            if (isConfirmDialogOpen()) return;

            e.preventDefault();
            e.stopPropagation();
            clearChatHistory();
        });

        setTimeout(() => {
            const viewer = document.getElementById('viewer');
            if (viewer) {
                try {
                    const observer = new MutationObserver(updateQuickActions);
                    observer.observe(viewer, { attributes: true, attributeFilter: ['data-path', 'data-edit-mode'] });
                } catch (e) {
                    console.warn('Failed to observe viewer:', e);
                }
            }
        }, 500);
    }

    function handleInputKeydown(e) {
        const suggestionsVisible = isMentionSuggestionsVisible();
        if (suggestionsVisible) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                moveMentionSelection(1);
                return;
            }

            if (e.key === 'ArrowUp') {
                e.preventDefault();
                moveMentionSelection(-1);
                return;
            }

            if (e.key === 'Enter' || e.key === 'Tab') {
                e.preventDefault();
                applyMentionSuggestion(aiState.mentionSelectedIndex);
                return;
            }

            if (e.key === 'Escape') {
                e.preventDefault();
                hideMentionSuggestions();
                return;
            }
        }

        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendAIMessage();
        }
    }

    function getActiveFilePath() {
        const currentRoot = getRootForFileContext();
        let activeTab = null;
        if (window.tabsState?.activeTabId && Array.isArray(window.tabsState.tabs)) {
            activeTab = window.tabsState.tabs.find(tab => tab.id === window.tabsState.activeTabId);
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

    async function getCurrentFolderFiles(currentFilePath) {
        const root = getRootForFileContext();
        const folderPath = currentFilePath ? getDirectoryPath(currentFilePath) : '';
        const now = Date.now();
        const cache = aiState.mentionFolderCache;

        if (cache.root === root && cache.path === folderPath && (now - cache.loadedAt) < MENTION_CONFIG.folderCacheMs) {
            return cache.files;
        }

        try {
            const response = await fetch(buildApiUrl('list', folderPath, { root }), { method: 'GET' });
            if (!response.ok) return [];
            const data = await response.json();
            const files = (data?.items || [])
                .filter(item => item.type === 'file' && typeof item.name === 'string')
                .map(item => normalizeMentionedFilePath(`${folderPath ? `${folderPath}/` : ''}${item.name}`));

            aiState.mentionFolderCache = {
                root,
                path: folderPath,
                files,
                loadedAt: now
            };
            return files;
        } catch (err) {
            console.warn('Failed to fetch folder files for @mentions:', err);
            return [];
        }
    }

    function getMentionContext(value, cursorPos) {
        const beforeCursor = value.slice(0, cursorPos);
        const match = beforeCursor.match(/(^|\s)@([^\s@]*)$/);
        if (!match) return null;

        const query = match[2] || '';
        const start = cursorPos - query.length - 1;
        return {
            start,
            end: cursorPos,
            query
        };
    }

    function isMentionSuggestionsVisible() {
        return !!(elements.mentionSuggestions && !elements.mentionSuggestions.classList.contains('hidden') && aiState.mentionSuggestions.length > 0);
    }

    function hideMentionSuggestions() {
        aiState.mentionSuggestions = [];
        aiState.mentionSelectedIndex = 0;
        aiState.mentionQuery = '';
        aiState.mentionRange = null;
        if (elements.mentionSuggestions) {
            elements.mentionSuggestions.classList.add('hidden');
            elements.mentionSuggestions.innerHTML = '';
        }
    }

    function renderMentionSuggestions() {
        if (!elements.mentionSuggestions) return;

        const list = aiState.mentionSuggestions;
        if (list.length === 0) {
            elements.mentionSuggestions.innerHTML = `<div class="mention-suggestion-empty">${escapeHtml(tr('aiNoMatchingFilesInFolder', 'No matching files in current folder'))}</div>`;
            elements.mentionSuggestions.classList.remove('hidden');
            return;
        }

        elements.mentionSuggestions.innerHTML = '';
        list.forEach((name, index) => {
            const displayName = name.split('/').pop() || name;
            const item = document.createElement('div');
            item.className = `mention-suggestion-item ${index === aiState.mentionSelectedIndex ? 'active' : ''}`;
            item.dataset.index = String(index);
            item.title = name;
            item.innerHTML = `<span class="mention-suggestion-icon">${getAttachmentFileIconMarkup(name)}</span><span class="mention-suggestion-name">${escapeHtml(displayName)}</span>`;
            item.addEventListener('mousedown', (e) => {
                e.preventDefault();
                applyMentionSuggestion(index);
            });
            elements.mentionSuggestions.appendChild(item);
        });
        elements.mentionSuggestions.classList.remove('hidden');
    }

    function moveMentionSelection(delta) {
        if (aiState.mentionSuggestions.length === 0) return;
        const last = aiState.mentionSuggestions.length - 1;
        let nextIndex = aiState.mentionSelectedIndex + delta;
        if (nextIndex < 0) nextIndex = last;
        if (nextIndex > last) nextIndex = 0;
        aiState.mentionSelectedIndex = nextIndex;
        renderMentionSuggestions();
    }

    function applyMentionSuggestion(index) {
        if (!elements.input || !aiState.mentionRange) return;
        const selected = aiState.mentionSuggestions[index];
        if (!selected) return;

        const value = elements.input.value;
        const prefix = value.slice(0, aiState.mentionRange.start);
        const suffix = value.slice(aiState.mentionRange.end);
        const insertionIndex = prefix.length;
        elements.input.value = `${prefix}${suffix}`.replace(/\s{2,}/g, ' ');
        addMentionedFileAttachment(selected, insertionIndex, getRootForFileContext());

        const newCursorPos = Math.min(insertionIndex, elements.input.value.length);
        elements.input.selectionStart = newCursorPos;
        elements.input.selectionEnd = newCursorPos;
        elements.input.focus();
        hideMentionSuggestions();
    }

    function normalizeMentionedFilePath(path) {
        return String(path || '').replace(/\\/g, '/').replace(/^\/+/, '').trim();
    }

    function addMentionedFileAttachment(path, insertionIndex = null, root = null) {
        const normalized = normalizeMentionedFilePath(path);
        if (!normalized || normalized.includes('..')) return false;
        if (!Array.isArray(aiState.selectedMentionFiles)) aiState.selectedMentionFiles = [];
        if (!Array.isArray(aiState.mentionPlacements)) aiState.mentionPlacements = [];
        if (!aiState.mentionedFileRoots || typeof aiState.mentionedFileRoots !== 'object') aiState.mentionedFileRoots = {};
        if (!aiState.selectedMentionFiles.includes(normalized)) {
            aiState.selectedMentionFiles.push(normalized);
        }
        const rootKey = String(root || '').trim();
        if (rootKey) aiState.mentionedFileRoots[normalized] = rootKey;
        const idx = Number.isFinite(insertionIndex)
            ? insertionIndex
            : Math.max(0, Number(elements.input?.selectionStart ?? String(elements.input?.value || '').length));
        if (!aiState.mentionPlacements.some((p) => p?.name === normalized)) {
            aiState.mentionPlacements.push({ name: normalized, index: idx });
        }
        renderMentionedAttachments();
        elements.input?.focus();
        return true;
    }

    function getTreeFilePathFromDragEvent(event) {
        return getTreeFilePathsFromDragEvent(event)[0] || '';
    }

    function getTreeFilePathsFromDragEvent(event) {
        const dt = event?.dataTransfer;
        if (!dt) return [];
        const hasCloudType = hasTreeFileDragEvent(event);
        if (!hasCloudType && aiTreeDragFallbackPaths.length === 0) return [];
        const type = dt.getData('application/x-cloud-file-type');
        if (type && type !== 'file') return [];
        const paths = [];
        try {
            const many = JSON.parse(dt.getData('application/x-cloud-file-paths') || '[]');
            if (Array.isArray(many)) many.forEach(path => paths.push(path));
        } catch (_) { logAiError('Parse drag JSON', _); }
        paths.push(dt.getData('application/x-cloud-file-path'));
        String(dt.getData('text/plain') || '').split(/\r?\n/).forEach(path => paths.push(path));
        aiTreeDragFallbackPaths.forEach(path => paths.push(path));
        return Array.from(new Set(paths.map(normalizeMentionedFilePath).filter(path => path && !path.includes('..'))));
    }

    function getTreeFileRootFromDragEvent(event) {
        const dt = event?.dataTransfer;
        if (!dt) return '';
        return String(dt.getData('application/x-cloud-source-root') || dt.getData('application/x-cloud-file-root') || '').trim();
    }

    function hasTreeFileDragEvent(event) {
        const types = Array.from(event?.dataTransfer?.types || []).map(type => String(type).toLowerCase());
        return types.includes('application/x-cloud-file-path') || types.includes('application/x-cloud-file-paths') || aiTreeDragFallbackPaths.length > 0;
    }

    function hasExternalFileDragEvent(event) {
        return Array.from(event?.dataTransfer?.types || []).map(type => String(type).toLowerCase()).includes('files');
    }

    async function attachDroppedExternalFiles(fileList) {
        const files = Array.from(fileList || []);
        if (!files.length) return 0;
        let added = 0;
        let skippedInvalid = false;
        for (const file of files) {
            if (aiState.externalAttachments.length >= MAX_EXTERNAL_ATTACHMENTS) {
                flashStatus(tr('aiAttachLimit', 'You can attach up to {n} files', { n: MAX_EXTERNAL_ATTACHMENTS }));
                break;
            }
            try {
                if (isAllowedImageFile(file)) {
                    const id = makeAttachmentId();
                    const url = URL.createObjectURL(file);
                    aiState.externalAttachments.push({
                        id,
                        kind: 'image',
                        name: file.name,
                        content: '',
                        file,
                        objectUrl: url,
                        ocrConfidence: null,
                        ocrLang: 'auto',
                        ocrInProgress: false,
                        autoProcessQueued: false,
                        autoProcessDone: false,
                        _ocrGen: 0,
                        _ocrUiPct: 0
                    });
                    runOcrForAttachment(id);
                    added++;
                } else if (isAllowedDocumentFile(file)) {
                    const text = await extractUploadedDocumentText(file);
                    aiState.externalAttachments.push({ id: makeAttachmentId(), kind: 'text', name: file.name, content: text, file });
                    added++;
                } else if (isAllowedTextFile(file)) {
                    const text = await readFileAsTextPromise(file);
                    aiState.externalAttachments.push({ id: makeAttachmentId(), kind: 'text', name: file.name, content: text, file });
                    added++;
                } else {
                    skippedInvalid = true;
                }
            } catch (err) {
                logAiError('Attach text file read', err);
                skippedInvalid = true;
            }
        }
        renderExternalAttachmentsUI();
        if (added > 0) {
            saveUserSettingsToDb();
            flashStatus(tr('aiTextFilesAttached', '{count} file(s) attached', { count: added }));
        } else if (skippedInvalid) {
            flashStatus(tr('aiAttachUnsupported', 'Unsupported file type'));
        }
        return added;
    }

    function setupAiTreeDropZone() {
        const dropTarget = elements.input?.closest('.ai-input') || elements.chat || elements.modal?.querySelector('.ai-modal-content');
        if (!dropTarget || dropTarget.dataset.aiTreeDropBound === '1') return;
        dropTarget.dataset.aiTreeDropBound = '1';

        const clear = () => dropTarget.classList.remove('ai-tree-drop-active');
        const isAiDrop = (event) => !!event.target?.closest?.('#ai-modal:not(.hidden)');
        const handleDrop = async (event) => {
            if (!isAiDrop(event)) return false;
            if (hasExternalFileDragEvent(event) && event.dataTransfer?.files?.length) {
                event.preventDefault();
                event.stopPropagation();
                clear();
                await attachDroppedExternalFiles(event.dataTransfer.files);
                return true;
            }
            const paths = getTreeFilePathsFromDragEvent(event);
            if (paths.length === 0) return false;
            event.preventDefault();
            event.stopPropagation();
            clear();
            let added = 0;
            const root = getTreeFileRootFromDragEvent(event);
            const insertionIndex = Math.max(0, Number(elements.input?.selectionStart ?? String(elements.input?.value || '').length));
            paths.forEach(path => {
                if (addMentionedFileAttachment(path, insertionIndex, root)) added++;
            });
            aiTreeDragFallbackPaths = [];
            renderMentionedAttachments();
            if (added > 0) flashStatus(tr('aiFilesAdded', 'Added {count} @file(s) to context', { count: added }));
            return true;
        };
        document.addEventListener('dragstart', (event) => {
            const node = event.target?.closest?.('#tree .node[data-path], #tree .advanced-search-result[data-path]');
            const path = normalizeMentionedFilePath(node?.dataset?.path || '');
            const type = String(node?.dataset?.type || 'file');
            const root = String(node?.dataset?.root || (typeof window.getCurrentCloudRoot === 'function' ? window.getCurrentCloudRoot() : getRootForFileContext()) || '').trim();
            aiTreeDragFallbackPaths = path && type === 'file' ? [path] : [];
            if (event.dataTransfer && path && type === 'file' && root) {
                event.dataTransfer.setData('application/x-cloud-source-root', root);
            }
        }, true);
        document.addEventListener('dragend', () => { aiTreeDragFallbackPaths = []; }, true);
        document.addEventListener('dragover', (event) => {
            if (!isAiDrop(event) || (!hasTreeFileDragEvent(event) && !hasExternalFileDragEvent(event))) return;
            event.preventDefault();
            event.stopPropagation();
            event.dataTransfer.dropEffect = 'copy';
            dropTarget.classList.add('ai-tree-drop-active');
        }, true);
        document.addEventListener('drop', (event) => { void handleDrop(event); }, true);
        dropTarget.addEventListener('dragenter', (event) => {
            if (!hasTreeFileDragEvent(event) && !hasExternalFileDragEvent(event)) return;
            event.preventDefault();
            dropTarget.classList.add('ai-tree-drop-active');
        });
        dropTarget.addEventListener('dragover', (event) => {
            if (!hasTreeFileDragEvent(event) && !hasExternalFileDragEvent(event)) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'copy';
            dropTarget.classList.add('ai-tree-drop-active');
        });
        dropTarget.addEventListener('dragleave', (event) => {
            if (dropTarget.contains(event.relatedTarget)) return;
            clear();
        });
        dropTarget.addEventListener('drop', (event) => {
            void handleDrop(event);
        }, true);
    }

    function renderMentionedAttachments() {
        if (!elements.attachedMentionsInfo) return;

        const files = aiState.selectedMentionFiles;
        if (!Array.isArray(files) || files.length === 0) {
            elements.attachedMentionsInfo.classList.add('hidden');
            elements.attachedMentionsInfo.innerHTML = '';
            updateAttachmentsRowVisibility();
            saveUserSettingsToDb();
            return;
        }

        elements.attachedMentionsInfo.innerHTML = files.map(name => `
            <span class="mentioned-file-chip">
                <span class="mentioned-file-chip-icon">${getAttachmentFileIconMarkup(name)}</span>
                <span class="mentioned-file-chip-name">@${escapeHtml(name)}</span>
                <button class="remove-mentioned-file" data-name="${escapeHtml(name)}" title="${escapeHtml(tr('remove', 'Remove'))} @${escapeHtml(name)}">&times;</button>
            </span>
        `).join('');
        elements.attachedMentionsInfo.classList.remove('hidden');
        updateAttachmentsRowVisibility();
        saveUserSettingsToDb();
    }

    function updateAttachmentsRowVisibility() {
        if (!elements.attachmentsRow) return;
        const hasAttachedFile = aiState.externalAttachments.length > 0;
        const hasMentionedFiles = Array.isArray(aiState.selectedMentionFiles) && aiState.selectedMentionFiles.length > 0;
        elements.attachmentsRow.classList.toggle('hidden', !(hasAttachedFile || hasMentionedFiles));
    }

    function getAttachmentFileIconMarkup(filename) {
        if (typeof window.getFileIconMarkup === 'function') {
            return window.getFileIconMarkup(filename, 'ai-attachment-file-icon');
        }
        const ext = String(filename || '').split('.').pop().toLowerCase() || 'txt';
        const src = `icons/${encodeURIComponent(ext)}.svg`;
        const fallback = 'icons/txt.svg';
        return `<span class="app-file-icon app-file-icon-file ai-attachment-file-icon" aria-hidden="true"><img src="${escapeHtml(src)}" alt="" loading="lazy" onerror="this.onerror=null;this.src='${fallback}';"></span>`;
    }

    function renderExternalAttachmentsUI(prevRects = null) {
        const list = elements.attachedFilesList;
        if (!list) return;
        list.replaceChildren();
        const total = aiState.externalAttachments.length;
        const rows = Math.max(1, Math.ceil(total / 2));
        for (let i = 0; i < total; i++) {
            const att = aiState.externalAttachments[i];
            const row = document.createElement('div');
            row.className = 'attached-file-info';
            row.dataset.attachmentId = att.id;
            const pair = Math.floor(i / 2);
            row.style.gridColumn = String((i % 2) + 1);
            row.style.gridRow = String(rows - pair);

            if (att.kind === 'image') {
                const thumbWrap = document.createElement('div');
                thumbWrap.className = 'attached-file-thumb-wrap' + (att.objectUrl ? '' : ' hidden');
                thumbWrap.title = tr('aiOcrOpenEditorHint', 'Click to view or edit recognized text');
                const img = document.createElement('img');
                img.className = 'attached-file-thumb';
                img.alt = att.name;
                if (att.objectUrl) img.src = att.objectUrl;
                const prog = document.createElement('div');
                prog.className = 'attached-file-ocr-progress' + (att.ocrInProgress ? ' active' : '');
                prog.setAttribute('aria-hidden', 'true');
                const progFill = document.createElement('div');
                progFill.className = 'attached-file-ocr-progress-fill';
                progFill.style.width = `${att._ocrUiPct || 0}%`;
                prog.appendChild(progFill);
                thumbWrap.appendChild(img);
                thumbWrap.appendChild(prog);
                const fn = document.createElement('span');
                fn.className = 'attached-filename';
                fn.textContent = `${att.name} (${formatFileSize(att.file?.size || 0)})`;
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'remove-attached-file';
                btn.dataset.attachmentId = att.id;
                btn.setAttribute('aria-label', 'remove');
                btn.textContent = '×';
                row.appendChild(thumbWrap);
                row.appendChild(fn);
                row.appendChild(btn);
            } else {
                const iconWrap = document.createElement('div');
                iconWrap.className = 'attached-file-icon-wrap';
                iconWrap.innerHTML = getAttachmentFileIconMarkup(att.name);
                const fn = document.createElement('span');
                fn.className = 'attached-filename';
                const sz =
                    att.file != null && att.file.size != null
                        ? att.file.size
                        : new Blob([att.content || '']).size;
                fn.textContent = `${att.name} (${formatFileSize(sz)})`;
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'remove-attached-file';
                btn.dataset.attachmentId = att.id;
                btn.setAttribute('aria-label', 'remove');
                btn.textContent = '×';
                row.appendChild(iconWrap);
                row.appendChild(fn);
                row.appendChild(btn);
            }
            list.appendChild(row);
        }
        if (prevRects) {
            const nextRows = Array.from(list.querySelectorAll('.attached-file-info'));
            nextRows.forEach((el) => {
                const id = el.dataset.attachmentId;
                const prev = id ? prevRects.get(id) : null;
                if (!prev) return;
                const next = el.getBoundingClientRect();
                const dx = prev.left - next.left;
                const dy = prev.top - next.top;
                if (!dx && !dy) return;
                el.style.transform = `translate(${dx}px, ${dy}px)`;
                el.style.transition = 'none';
                requestAnimationFrame(() => {
                    el.style.transition = '';
                    el.style.transform = '';
                });
            });
        }
        list.classList.toggle('hidden', aiState.externalAttachments.length === 0);
        updateAttachmentsRowVisibility();
    }

    function removeExternalAttachment(id) {
        const prevRects = new Map(
            Array.from(elements.attachedFilesList?.querySelectorAll('.attached-file-info') || []).map((el) => [
                el.dataset.attachmentId,
                el.getBoundingClientRect()
            ])
        );
        const idx = aiState.externalAttachments.findIndex((a) => a.id === id);
        if (idx === -1) return;
        const att = aiState.externalAttachments[idx];
        const row = elements.attachedFilesList?.querySelector(`[data-attachment-id="${id}"]`);
        if (att._ocrGen != null) att._ocrGen++;
        if (att.objectUrl) {
            try {
                URL.revokeObjectURL(att.objectUrl);
            } catch (e) { logAiError('Revoke attachment object URL', e); }
        }
        if (aiState.ocrModalAttachmentId === id) {
            const ocrModal = document.getElementById('ai-ocr-edit-modal');
            if (ocrModal) ocrModal.classList.add('hidden');
            aiState.ocrModalAttachmentId = null;
        }
        aiState.externalAttachments.splice(idx, 1);
        if (row) {
            requestAnimationFrame(() => {
                row.classList.add('is-removing');
            });
            setTimeout(() => {
                renderExternalAttachmentsUI(prevRects);
            }, 190);
        } else {
            renderExternalAttachmentsUI(prevRects);
        }
        saveUserSettingsToDb();
        flashStatus(tr('aiAttachedFileRemoved', 'Attached file removed'));
    }

    function removeAllExternalAttachments() {
        for (const att of aiState.externalAttachments) {
            if (att._ocrGen != null) att._ocrGen++;
            if (att.objectUrl) {
                try {
                    URL.revokeObjectURL(att.objectUrl);
                } catch (e) { logAiError('Revoke attachment object URL', e); }
            }
        }
        aiState.externalAttachments = [];
        aiState.ocrModalAttachmentId = null;
        const ocrModal = document.getElementById('ai-ocr-edit-modal');
        if (ocrModal) ocrModal.classList.add('hidden');
        renderExternalAttachmentsUI();
        saveUserSettingsToDb();
    }

    function syncMentionedAttachmentsFromInput(inputValue) {
        renderMentionedAttachments();
    }

    function removeMentionedAttachment(fileName) {
        if (!fileName) return;
        aiState.selectedMentionFiles = (aiState.selectedMentionFiles || []).filter(n => n !== fileName);
        aiState.mentionPlacements = (aiState.mentionPlacements || []).filter(p => p?.name !== fileName);
        if (aiState.mentionedFileRoots) delete aiState.mentionedFileRoots[fileName];
        renderMentionedAttachments();
        elements.input?.focus();
    }

    async function handleMentionInput() {
        if (!elements.input) return;
        syncMentionedAttachmentsFromInput(elements.input.value);
        const cursorPos = elements.input.selectionStart || 0;
        const context = getMentionContext(elements.input.value, cursorPos);

        if (!context) {
            hideMentionSuggestions();
            return;
        }

        const requestId = ++aiState.mentionRequestId;
        const currentFilePath = getActiveFilePath();
        const files = await getCurrentFolderFiles(currentFilePath);
        if (requestId !== aiState.mentionRequestId) return;

        const queryLower = context.query.toLowerCase();
        const filtered = files
            .filter(name => !queryLower || name.toLowerCase().includes(queryLower))
            .slice(0, MENTION_CONFIG.maxSuggestions);

        aiState.mentionSuggestions = filtered;
        aiState.mentionSelectedIndex = 0;
        aiState.mentionQuery = context.query;
        aiState.mentionRange = { start: context.start, end: context.end };
        renderMentionSuggestions();
    }

    function closeAttachDropdown() {
        if (elements.attachDropdown) elements.attachDropdown.classList.add('hidden');
    }

    function toggleAttachDropdown() {
        if (!elements.attachDropdown) return;
        elements.attachDropdown.classList.toggle('hidden');
    }

    function tesseractLangFromUi(uiLang) {
        const map = {
            auto: 'rus+eng',
            'rus+eng': 'rus+eng',
            rus: 'rus',
            eng: 'eng',
            ukr: 'ukr',
            pol: 'pol',
            deu: 'deu',
            fra: 'fra',
            spa: 'spa',
            ita: 'ita',
            chi_sim: 'chi_sim',
            jpn: 'jpn'
        };
        return map[uiLang] || 'rus+eng';
    }

    /** Tesseract.js v6 examples use createWorker(['eng','rus']); string "rus+eng" can yield empty text in some builds. */
    function langsArgForCreateWorker(langSpec) {
        if (!langSpec || typeof langSpec !== 'string') return 'eng';
        if (langSpec.includes('+')) {
            return langSpec.split('+').map((s) => s.trim()).filter(Boolean);
        }
        return langSpec;
    }

    function lineTextFromBlock(block) {
        if (!block) return '';
        if (typeof block.text === 'string' && block.text.trim()) return block.text.trim();
        const paras = block.paragraphs;
        if (!Array.isArray(paras)) return '';
        return paras
            .map((p) => {
                if (!p) return '';
                if (typeof p.text === 'string' && p.text.trim()) return p.text.trim();
                const lines = p.lines;
                if (!Array.isArray(lines)) return '';
                return lines.map((ln) => (ln && typeof ln.text === 'string' ? ln.text : '')).join('\n');
            })
            .filter(Boolean)
            .join('\n');
    }

    function pageTextFromOcrData(data) {
        if (!data) return '';
        let text = typeof data.text === 'string' ? data.text : '';
        text = text.trim();
        if (text) return text;
        if (Array.isArray(data.blocks) && data.blocks.length) {
            const fromBlocks = data.blocks.map((b) => lineTextFromBlock(b)).filter(Boolean).join('\n').trim();
            if (fromBlocks) return fromBlocks;
        }
        return '';
    }

    /** Same global API as in the standalone OCR demo: <script src=".../tesseract.min.js"> then Tesseract.createWorker(lang) */
    function getTesseractGlobal() {
        const tryApi = (T) => (T && typeof T.createWorker === 'function' ? T : null);
        let T = tryApi(window.Tesseract);
        if (T) return T;
        const g = typeof globalThis !== 'undefined' ? globalThis : window;
        T = tryApi(g.Tesseract);
        if (T) return T;
        return null;
    }

    let tesseractReadyPromise = null;
    function ensureTesseractReady() {
        const ready = getTesseractGlobal();
        if (ready) return Promise.resolve(ready);
        if (tesseractReadyPromise) return tesseractReadyPromise;
        tesseractReadyPromise = new Promise((resolve, reject) => {
            const existing = document.querySelector('script[src*="tesseract.js"]');
            let poll = null;
            const clearPoll = () => {
                if (poll != null) {
                    clearInterval(poll);
                    poll = null;
                }
            };
            const finishOk = () => {
                clearPoll();
                const g = getTesseractGlobal();
                if (g) {
                    resolve(g);
                } else {
                    tesseractReadyPromise = null;
                    reject(new Error('Tesseract is not available (createWorker missing)'));
                }
            };
            const finishErr = () => {
                clearPoll();
                tesseractReadyPromise = null;
                reject(new Error('Tesseract script failed to load'));
            };

            if (existing) {
                if (getTesseractGlobal()) {
                    finishOk();
                    return;
                }
                existing.addEventListener('load', finishOk);
                existing.addEventListener('error', finishErr);
                queueMicrotask(() => {
                    if (getTesseractGlobal()) finishOk();
                });
                setTimeout(() => {
                    if (getTesseractGlobal()) finishOk();
                }, 0);
                const deadline = Date.now() + 20000;
                poll = setInterval(() => {
                    if (getTesseractGlobal()) {
                        finishOk();
                    } else if (Date.now() > deadline) {
                        clearPoll();
                        tesseractReadyPromise = null;
                        reject(new Error('Tesseract script did not expose API in time'));
                    }
                }, 100);
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@6/dist/tesseract.min.js';
            script.async = false;
            script.onload = finishOk;
            script.onerror = finishErr;
            document.head.appendChild(script);
        });
        return tesseractReadyPromise;
    }

    function setOcrProgressForAttachment(attachmentId, active) {
        const row = Array.from(elements.attachedFilesList?.querySelectorAll('[data-attachment-id]') || []).find(
            (r) => r.dataset.attachmentId === attachmentId
        );
        const el = row?.querySelector('.attached-file-ocr-progress');
        if (el) {
            el.classList.toggle('active', !!active);
            const fillEl = el.querySelector('.attached-file-ocr-progress-fill');
            const att = getAttachmentById(attachmentId);
            if (fillEl && att) {
                fillEl.style.width = `${att._ocrUiPct || 0}%`;
            }
        }
        const att = getAttachmentById(attachmentId);
        if (att) att.ocrInProgress = !!active;
    }
    function syncOcrThumbProgressWidth(attachmentId) {
        const row = Array.from(elements.attachedFilesList?.querySelectorAll('[data-attachment-id]') || []).find(
            (r) => r.dataset.attachmentId === attachmentId
        );
        const fillEl = row?.querySelector('.attached-file-ocr-progress-fill');
        const att = getAttachmentById(attachmentId);
        if (fillEl && att) {
            const p = Math.max(0, Math.min(100, Number(att._ocrUiPct) || 0));
            fillEl.style.width = `${p}%`;
        }
    }
    function setOcrModalProgressVisible(visible) {
        const modal = document.getElementById('ai-ocr-edit-modal');
        const wrap = modal?.querySelector('.ai-ocr-progress-wrap');
        if (wrap) wrap.classList.toggle('hidden', !visible);
    }

    function setOcrModalProgress(percent) {
        const modal = document.getElementById('ai-ocr-edit-modal');
        const fill = modal?.querySelector('.ai-ocr-progress-fill');
        const label = modal?.querySelector('.ai-ocr-progress-label');
        const p = Math.max(0, Math.min(100, Number(percent) || 0));
        if (fill) fill.style.width = `${p}%`;
        if (label) {
            label.textContent = tr('aiOcrProgressPercent', 'Recognition: {percent}%', { percent: Math.round(p) });
        }
    }

    function fillOcrLangSelect(selectEl) {
        if (!selectEl) return;
        const opts = [
            { v: 'auto', k: 'aiOcrLangAuto' },
            { v: 'rus+eng', k: 'aiOcrLangRusEng' },
            { v: 'rus', k: 'aiOcrLangRus' },
            { v: 'eng', k: 'aiOcrLangEng' },
            { v: 'ukr', k: 'aiOcrLangUkr' },
            { v: 'pol', k: 'aiOcrLangPol' },
            { v: 'deu', k: 'aiOcrLangDeu' },
            { v: 'fra', k: 'aiOcrLangFra' },
            { v: 'spa', k: 'aiOcrLangSpa' },
            { v: 'ita', k: 'aiOcrLangIta' },
            { v: 'chi_sim', k: 'aiOcrLangChiSim' },
            { v: 'jpn', k: 'aiOcrLangJpn' }
        ];
        const cur = selectEl.value || aiState.ocrLang || 'auto';
        selectEl.innerHTML = opts.map((o) =>
            `<option value="${o.v}">${escapeHtml(tr(o.k, o.v))}</option>`
        ).join('');
        const allowed = opts.some((o) => o.v === cur);
        selectEl.value = allowed ? cur : 'auto';
    }

    function mountOcrModalInAssistant(modal) {
        const aiModal = elements.modal || document.getElementById('ai-modal');
        const aiContent = aiModal?.querySelector('.ai-modal-content');
        if (!modal || !aiContent) return false;
        aiContent.style.position = 'relative';
        if (modal.parentElement !== aiContent) aiContent.appendChild(modal);
        return true;
    }

    function ensureOcrModal() {
        let modal = document.getElementById('ai-ocr-edit-modal');
        if (modal) {
            mountOcrModalInAssistant(modal);
            return modal;
        }
        modal = document.createElement('div');
        modal.id = 'ai-ocr-edit-modal';
        modal.className = 'hidden';
        modal.innerHTML = `
            <div class="ai-ocr-edit-dialog" role="dialog" aria-modal="true">
                <div class="ai-ocr-edit-header">
                    <span class="ai-ocr-edit-title">${escapeHtml(tr('aiOcrModalTitle', 'Text from image'))}</span>
                    <button type="button" class="icon-btn small ai-ocr-close" aria-label="close">&times;</button>
                </div>
                <div class="ai-ocr-edit-body">
                    <div class="ai-ocr-edit-row">
                        <label class="ai-ocr-lang-label" for="ai-ocr-lang-select">${escapeHtml(tr('aiOcrLanguage', 'Recognition language'))}</label>
                        <select id="ai-ocr-lang-select" class="ai-ocr-lang-select"></select>
                    </div>
                    <div class="ai-ocr-progress-wrap hidden">
                        <div class="ai-ocr-progress-label"></div>
                        <div class="ai-ocr-progress-track"><div class="ai-ocr-progress-fill"></div></div>
                    </div>
                    <div class="ai-ocr-edit-row">
                        <label class="ai-ocr-text-label" for="ai-ocr-textarea">${escapeHtml(tr('aiOcrRecognizedText', 'Recognized text'))}</label>
                        <textarea id="ai-ocr-textarea" class="ai-ocr-textarea"></textarea>
                        <div class="ai-ocr-text-validation-hint" id="ai-ocr-validation-hint">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <span>${escapeHtml(tr('aiOcrValidationHint', 'Start typing to recognize text'))}</span>
                        </div>
                    </div>
                    <div class="ai-ocr-confidence hidden"></div>
                </div>
                <div class="ai-ocr-edit-footer">
                    <button type="button" class="btn-ocr-secondary ai-ocr-recognize-again">${escapeHtml(tr('aiOcrRecognizeAgain', 'Recognize again'))}</button>
                    <button type="button" class="btn-ocr-primary ai-ocr-done">${escapeHtml(tr('aiOcrApplyClose', 'Apply and close'))}</button>
                </div>
            </div>
        `;
        if (!mountOcrModalInAssistant(modal)) document.body.appendChild(modal);

        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeOcrEditModal();
        });
        modal.querySelector('.ai-ocr-close')?.addEventListener('click', () => closeOcrEditModal());
        modal.querySelector('.ai-ocr-done')?.addEventListener('click', () => {
            const ta = modal.querySelector('#ai-ocr-textarea');
            const id = aiState.ocrModalAttachmentId;
            const att = id ? getAttachmentById(id) : null;
            if (ta && att && (!att.ocrInProgress || (ta.value || '').trim())) att.content = ta.value;
            closeOcrEditModal();
            flashStatus(tr('aiOcrTextUpdated', 'Recognized text updated'));
        });
        modal.querySelector('.ai-ocr-recognize-again')?.addEventListener('click', async () => {
            const sel = modal.querySelector('#ai-ocr-lang-select');
            if (sel) aiState.ocrLang = sel.value || 'auto';
            const id = aiState.ocrModalAttachmentId;
            const attBefore = id ? getAttachmentById(id) : null;
            if (attBefore) attBefore.ocrLang = aiState.ocrLang;
            await runOcrForAttachment(id);
            const att = id ? getAttachmentById(id) : null;
            const ta = modal.querySelector('#ai-ocr-textarea');
            if (ta && att) ta.value = att.content || '';
            updateOcrModalConfidence(modal, att?.ocrConfidence);
            updateOcrTextValidation(ta);
        });

        const sel = modal.querySelector('#ai-ocr-lang-select');
        if (sel) {
            sel.addEventListener('change', () => {
                aiState.ocrLang = sel.value || 'auto';
                const oid = aiState.ocrModalAttachmentId;
                const oatt = oid ? getAttachmentById(oid) : null;
                if (oatt) oatt.ocrLang = aiState.ocrLang;
            });
        }

        // Add text validation on input
        const textarea = modal.querySelector('#ai-ocr-textarea');
        if (textarea) {
            textarea.addEventListener('input', () => {
                updateOcrTextValidation(textarea);
            });
        }

        fillOcrLangSelect(modal.querySelector('#ai-ocr-lang-select'));
        return modal;
    }

    function updateOcrModalConfidence(modal, confidence) {
        const confEl = modal?.querySelector('.ai-ocr-confidence');
        if (!confEl) return;
        const c = confidence != null && Number.isFinite(confidence) ? Number(confidence) : null;
        if (c != null) {
            const pct = Math.min(100, Math.max(0, c));
            confEl.textContent = tr('aiOcrConfidence', 'Confidence: {percent}%', { percent: pct.toFixed(1) });
            confEl.classList.remove('hidden');
        } else {
            confEl.textContent = '';
            confEl.classList.add('hidden');
        }
    }

    function updateOcrTextValidation(textarea) {
        if (!textarea) return;
        const hintEl = document.getElementById('ai-ocr-validation-hint');
        if (!hintEl) return;

        const text = textarea.value || '';
        const textLen = text.trim().length;

        // Remove existing classes
        textarea.classList.remove('valid-text', 'invalid-text');
        hintEl.classList.remove('valid', 'invalid');

        if (textLen === 0) {
            // Empty state
            hintEl.querySelector('span').textContent = tr('aiOcrValidationHint', 'Start typing to recognize text');
            hintEl.querySelector('svg').innerHTML = `
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            `;
        } else if (textLen < 10) {
            // Too short - invalid
            textarea.classList.add('invalid-text');
            hintEl.classList.add('invalid');
            hintEl.querySelector('span').textContent = tr('aiOcrValidationTooShort', 'Text is too short (minimum 10 characters)');
            hintEl.querySelector('svg').innerHTML = `
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            `;
        } else {
            // Valid text
            textarea.classList.add('valid-text');
            hintEl.classList.add('valid');
            hintEl.querySelector('span').textContent = tr('aiOcrValidationValid', 'Text looks good ({chars} characters)', { chars: textLen });
            hintEl.querySelector('svg').innerHTML = `
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            `;
        }
    }

    function openOcrEditModal(attachmentId) {
        const att = getAttachmentById(attachmentId);
        if (!att || att.kind !== 'image') return;
        aiState.ocrModalAttachmentId = attachmentId;
        const modal = ensureOcrModal();
        const ta = modal.querySelector('#ai-ocr-textarea');
        const sel = modal.querySelector('#ai-ocr-lang-select');
        if (ta) ta.value = att.content || '';
        if (sel) {
            fillOcrLangSelect(sel);
            sel.value = att.ocrLang || aiState.ocrLang || 'auto';
        }
        modal.querySelector('.ai-ocr-edit-title').textContent = tr('aiOcrModalTitle', 'Text from image');
        modal.querySelector('.ai-ocr-lang-label').textContent = tr('aiOcrLanguage', 'Recognition language');
        modal.querySelector('.ai-ocr-text-label').textContent = tr('aiOcrRecognizedText', 'Recognized text');
        const again = modal.querySelector('.ai-ocr-recognize-again');
        const done = modal.querySelector('.ai-ocr-done');
        if (again) again.textContent = tr('aiOcrRecognizeAgain', 'Recognize again');
        if (done) done.textContent = tr('aiOcrApplyClose', 'Apply and close');
        if (att.ocrInProgress) {
            setOcrModalProgressVisible(true);
            setOcrModalProgress(typeof att._ocrUiPct === 'number' ? att._ocrUiPct : 0);
        } else {
            setOcrModalProgressVisible(false);
            setOcrModalProgress(0);
        }
        updateOcrModalConfidence(modal, att.ocrConfidence);
        updateOcrTextValidation(ta);
        modal.classList.remove('hidden');
    }

    function closeOcrEditModal() {
        const modal = document.getElementById('ai-ocr-edit-modal');
        if (modal) {
            const ta = modal.querySelector('#ai-ocr-textarea');
            const id = aiState.ocrModalAttachmentId;
            const att = id ? getAttachmentById(id) : null;
            if (ta && att && (!att.ocrInProgress || (ta.value || '').trim())) att.content = ta.value;
            modal.classList.add('hidden');
            setOcrModalProgressVisible(false);
            aiState.ocrModalAttachmentId = null;
        }
    }
    function hasPendingImageOcr() {
        return aiState.externalAttachments.some((att) => att.kind === 'image' && att.ocrInProgress);
    }
    function shouldAutoProcessImageAttachments() {
        const inputText = String(elements.input?.value || '').trim();
        return !aiState.isGenerating &&
            !inputText &&
            !aiState.externalAttachments.some((att) => att.kind === 'text') &&
            (!Array.isArray(aiState.selectedMentionFiles) || aiState.selectedMentionFiles.length === 0);
    }
    function buildAutoOcrPrompt(attachments) {
        const blocks = attachments
            .map((att, index) => {
                const confidence = att.ocrConfidence != null ? `\nConfidence: ${att.ocrConfidence}%` : '';
                return `Image ${index + 1}: ${att.name}${confidence}\nRecognized text:\n${att.content}`;
            })
            .join('\n\n---\n\n');
        return [
            'Process the OCR text from the uploaded image.',
            'Correct obvious OCR mistakes, preserve the original meaning, and format the result clearly.',
            'If the text looks like a document, keep headings, lists, numbers, and structure readable.',
            'Return only the processed result without explanations.',
            '',
            blocks
        ].join('\n');
    }
    function syncOcrModalTextForAttachment(attachmentId) {
        if (aiState.ocrModalAttachmentId !== attachmentId) return;
        const modal = document.getElementById('ai-ocr-edit-modal');
        const ta = modal?.querySelector('#ai-ocr-textarea');
        const att = getAttachmentById(attachmentId);
        if (ta && att) {
            ta.value = att.content || '';
            updateOcrTextValidation(ta);
        }
        if (modal) updateOcrModalConfidence(modal, att?.ocrConfidence);
    }
    async function runBackgroundOcrPostProcess(att) {
        if (!att || !String(att.content || '').trim()) return '';
        await ensureDirectApiKeyLoaded();
        const requestModel = resolveModelForRequest();
        const response = await fetchAiCompletions({
            model: requestModel,
            messages: [{ role: 'user', content: buildAutoOcrPrompt([att]) }],
            max_tokens: 4096,
            temperature: 0.2,
            stream: false
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const apiErrorMessage = payload.error?.message || payload?.message || `HTTP error ${response.status}`;
            if (shouldMarkModelUnavailable(apiErrorMessage)) markModelUnavailable(requestModel);
            throw new Error(apiErrorMessage);
        }
        const content = String(payload?.choices?.[0]?.message?.content || payload?.choices?.[0]?.message?.reasoning_content || '').trim();
        if (!content) throw new Error('Empty OCR post-processing response');
        return content;
    }
    function queueAutoProcessRecognizedImages() {
        if (autoOcrProcessTimer) {
            clearTimeout(autoOcrProcessTimer);
            autoOcrProcessTimer = null;
        }
        autoOcrProcessTimer = setTimeout(async () => {
            autoOcrProcessTimer = null;
            if (!shouldAutoProcessImageAttachments()) return;
            if (hasPendingImageOcr()) return;

            const imageAttachments = aiState.externalAttachments.filter((att) => att.kind === 'image' && !att.autoProcessDone);
            if (imageAttachments.length === 0) return;

            const recognized = imageAttachments.filter((att) => String(att.content || '').trim());
            if (recognized.length === 0) {
                imageAttachments.forEach((att) => {
                    att.autoProcessDone = true;
                });
                flashStatus(tr('aiOcrNoTextAuto', 'Text in the uploaded image was not recognized.'));
                return;
            }

            imageAttachments.forEach((att) => {
                att.autoProcessQueued = true;
            });

            for (const att of recognized) {
                try {
                    const processed = await runBackgroundOcrPostProcess(att);
                    const liveAtt = getAttachmentById(att.id);
                    if (!liveAtt) continue;
                    liveAtt.content = processed;
                    liveAtt.autoProcessDone = true;
                    liveAtt.autoProcessQueued = false;
                    renderExternalAttachmentsUI();
                    syncOcrModalTextForAttachment(liveAtt.id);
                } catch (err) {
                    const liveAtt = getAttachmentById(att.id);
                    if (liveAtt) liveAtt.autoProcessQueued = false;
                    console.error('Auto OCR processing failed:', err);
                }
            }
            imageAttachments
                .filter((att) => !String(att.content || '').trim())
                .forEach((att) => {
                    att.autoProcessDone = true;
                });
        }, 250);
    }

    async function runOcrForAttachment(attachmentId) {
        const att = getAttachmentById(attachmentId);
        if (!att || att.kind !== 'image' || !att.file) return;

        att._ocrGen = (att._ocrGen || 0) + 1;
        const myGen = att._ocrGen;
        att._ocrUiPct = 0;

        setOcrProgressForAttachment(attachmentId, true);
        const ocrModalWantsProgress = () => aiState.ocrModalAttachmentId === attachmentId;
        if (ocrModalWantsProgress()) {
            setOcrModalProgressVisible(true);
            setOcrModalProgress(0);
        }

        let worker = null;
        try {
            const Tesseract = await ensureTesseractReady();
            if (att._ocrGen !== myGen) return;

            const langSpec = tesseractLangFromUi(att.ocrLang || aiState.ocrLang || 'auto');
            const langsForWorker = langsArgForCreateWorker(langSpec);

            const ocrLogger = (m) => {
                if (att._ocrGen !== myGen) return;
                let pct = 0;
                if (typeof m?.progress === 'number') pct = m.progress <= 1 ? m.progress * 100 : m.progress;
                else if (m?.status === 'loading tesseract core') pct = 5;
                else if (m?.status === 'initializing tesseract') pct = 15;
                else if (m?.status === 'loading language traineddata') pct = 35;
                else if (m?.status === 'initializing api') pct = 50;
                else if (m?.status === 'recognizing text') pct = 70;
                att._ocrUiPct = pct;
                syncOcrThumbProgressWidth(attachmentId);
                if (ocrModalWantsProgress()) {
                    setOcrModalProgressVisible(true);
                    setOcrModalProgress(pct);
                }
            };

            worker = await Tesseract.createWorker(langsForWorker, 1, { logger: ocrLogger });
            if (att._ocrGen !== myGen) return;

            await worker.setParameters({
                tessedit_pageseg_mode: '6',
                preserve_interword_spaces: '1'
            });

            const output = { text: true, blocks: true };
            const emptyRecOpts = {};

            let data;
            try {
                ({ data } = await worker.recognize(att.file, emptyRecOpts, output));
            } catch (recErr) {
                if (att.objectUrl && att._ocrGen === myGen) {
                    ({ data } = await worker.recognize(att.objectUrl, emptyRecOpts, output));
                } else {
                    throw recErr;
                }
            }

            if (att._ocrGen !== myGen) return;

            if (!pageTextFromOcrData(data) && att.objectUrl) {
                try {
                    const second = await worker.recognize(att.objectUrl, emptyRecOpts, output);
                    data = second.data;
                } catch (e2) {
                    console.warn('OCR second pass (blob URL) failed:', e2);
                }
            }

            if (att._ocrGen !== myGen) return;

            att.content = pageTextFromOcrData(data);
            att.ocrConfidence = normalizeOcrConfidence(extractOcrConfidenceFromData(data));

            if (!att.content) {
                flashStatus(tr('aiOcrNoText', 'No text found on the image'));
            }

            renderExternalAttachmentsUI();
            queueAutoProcessRecognizedImages();
            if (ocrModalWantsProgress() && att._ocrGen === myGen) {
                const modal = document.getElementById('ai-ocr-edit-modal');
                const ta = modal?.querySelector('#ai-ocr-textarea');
                if (ta) {
                    ta.value = att.content || '';
                    updateOcrTextValidation(ta);
                }
                updateOcrModalConfidence(modal, att.ocrConfidence);
                setOcrModalProgress(100);
                setTimeout(() => setOcrModalProgressVisible(false), 400);
            }
        } catch (err) {
            console.error('OCR error:', err);
            if (att._ocrGen === myGen) {
                att.content = '';
                att.ocrConfidence = null;
                flashStatus(tr('aiOcrFailed', 'Recognition failed. Try another language or image.'));
                renderExternalAttachmentsUI();
                queueAutoProcessRecognizedImages();
                if (ocrModalWantsProgress()) {
                    setOcrModalProgressVisible(false);
                    setOcrModalProgress(0);
                }
            }
        } finally {
            if (worker) {
                try {
                    await worker.terminate();
                } catch (termErr) {
                    console.warn(termErr);
                }
            }
            if (att._ocrGen === myGen) {
                setOcrProgressForAttachment(attachmentId, false);
                renderExternalAttachmentsUI();
            }
        }
    }

    function handleImageFileAttach(event) {
        const files = Array.from(event.target.files || []);
        event.target.value = '';
        if (!files.length) return;

        let started = 0;
        let skippedInvalid = false;
        for (const file of files) {
            if (aiState.externalAttachments.length >= MAX_EXTERNAL_ATTACHMENTS) {
                flashStatus(
                    tr('aiAttachLimit', 'You can attach up to {n} files', { n: MAX_EXTERNAL_ATTACHMENTS })
                );
                break;
            }
            if (!isAllowedImageFile(file)) {
                skippedInvalid = true;
                continue;
            }
            const id = makeAttachmentId();
            const url = URL.createObjectURL(file);
            aiState.externalAttachments.push({
                id,
                kind: 'image',
                name: file.name,
                content: '',
                file,
                objectUrl: url,
                ocrConfidence: null,
                ocrLang: 'auto',
                ocrInProgress: false,
                autoProcessQueued: false,
                autoProcessDone: false,
                _ocrGen: 0,
                _ocrUiPct: 0
            });
            renderExternalAttachmentsUI();
            runOcrForAttachment(id);
            started++;
        }
        if (started > 0) {
            saveUserSettingsToDb();
            flashStatus(tr('aiOcrInProgress', 'Recognizing text from image…'));
        }
        if (skippedInvalid) {
            flashStatus(tr('aiAttachInvalidImage', 'Only image files are allowed (JPEG, PNG, GIF, WebP, BMP, TIFF)'));
        }
    }

    function readFileAsTextPromise(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(typeof reader.result === 'string' ? reader.result : '');
            reader.onerror = () => reject(new Error('read'));
            reader.readAsText(file);
        });
    }

    async function handleFileAttach(event) {
        const files = Array.from(event.target.files || []);
        event.target.value = '';
        if (!files.length) return;

        let added = 0;
        let skippedInvalid = false;
        for (const file of files) {
            if (aiState.externalAttachments.length >= MAX_EXTERNAL_ATTACHMENTS) {
                flashStatus(
                    tr('aiAttachLimit', 'You can attach up to {n} files', { n: MAX_EXTERNAL_ATTACHMENTS })
                );
                break;
            }
            try {
                let text = '';
                if (isAllowedDocumentFile(file)) {
                    flashStatus(tr('aiDocumentExtracting', 'Extracting text from document…'));
                    text = await extractUploadedDocumentText(file);
                } else if (isAllowedTextFile(file)) {
                    text = await readFileAsTextPromise(file);
                } else {
                    skippedInvalid = true;
                    continue;
                }
                const id = makeAttachmentId();
                aiState.externalAttachments.push({
                    id,
                    kind: 'text',
                    name: file.name,
                    content: text,
                    file
                });
                added++;
            } catch (err) {
                logAiError('Attach text file read', err);
                flashStatus(err?.message || tr('aiAttachReadError', 'Could not read file'));
            }
        }
        renderExternalAttachmentsUI();
        if (added > 0) saveUserSettingsToDb();
        if (added > 0) {
            flashStatus(tr('aiTextFilesAttached', '{count} file(s) attached', { count: added }));
        }
        if (skippedInvalid) {
            flashStatus(
                tr(
                    'aiAttachInvalidText',
                    'Allowed: text/source files, PDF, Excel (.xls, .xlsx, .ods), or presentations (.ppt, .pptx)'
                )
            );
        }
    }

    function refreshAttachI18n() {
        if (elements.attachFileBtn) {
            elements.attachFileBtn.title = tr('aiAttachExternalFile', 'Attach external file');
        }
        if (elements.settingsBtn) {
            elements.settingsBtn.title = tr('aiSettings', 'Settings');
        }
        document.querySelectorAll('.ai-mode-chat').forEach((el) => { el.textContent = tr('aiModeChat', 'Chat'); });
        document.querySelectorAll('.ai-mode-agent').forEach((el) => { el.textContent = tr('aiModeAgent', 'Agent'); });
        const contextLabels = document.querySelectorAll('.ai-context-switch .ai-mode-switch-label');
        if (contextLabels[0]) contextLabels[0].textContent = tr('aiOff', 'Off');
        if (contextLabels[1]) contextLabels[1].textContent = tr('aiOn', 'On');
        document.querySelectorAll('#ai-modal [data-i18n-ai]').forEach((el) => {
            const key = el.getAttribute('data-i18n-ai');
            if (key) el.textContent = tr(key, el.textContent);
        });
        const modal = document.getElementById('ai-ocr-edit-modal');
        if (modal) {
            const sel = modal.querySelector('#ai-ocr-lang-select');
            const prev = sel ? sel.value : 'auto';
            if (sel) fillOcrLangSelect(sel);
            if (sel) sel.value = prev;
            modal.querySelector('.ai-ocr-edit-title').textContent = tr('aiOcrModalTitle', 'Text from image');
            modal.querySelector('.ai-ocr-lang-label').textContent = tr('aiOcrLanguage', 'Recognition language');
            modal.querySelector('.ai-ocr-text-label').textContent = tr('aiOcrRecognizedText', 'Recognized text');
            const again = modal.querySelector('.ai-ocr-recognize-again');
            const done = modal.querySelector('.ai-ocr-done');
            if (again) again.textContent = tr('aiOcrRecognizeAgain', 'Recognize again');
            if (done) done.textContent = tr('aiOcrApplyClose', 'Apply and close');
            const oid = aiState.ocrModalAttachmentId;
            const oatt = oid ? getAttachmentById(oid) : null;
            updateOcrModalConfidence(modal, oatt?.ocrConfidence);
        }
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function buildApiUrl(action, path, params = null) {
        const url = new URL(AI_CONFIG.fileApiEndpoint, window.location.href);
        url.searchParams.set('action', action);
        if (path) {
            url.searchParams.set('path', path);
        }
        if (params && typeof params === 'object') {
            Object.entries(params).forEach(([key, value]) => {
                if (value !== undefined && value !== null && value !== '') {
                    url.searchParams.set(key, value);
                }
            });
        }
        return url.toString();
    }

    function getRootForFileContext(tab = null) {
        if (tab?.root) return tab.root;
        if (typeof window.getActiveTabRoot === 'function') {
            try {
                const root = window.getActiveTabRoot();
                if (root) return root;
            } catch (err) {
                // Ignore and use the persisted root below.
            }
        }
        try {
            return localStorage.getItem('cloudRoot') || 'main';
        } catch (err) {
            return 'main';
        }
    }

    function getRootForToolRun() {
        const firstMention = Array.isArray(aiState.selectedMentionFiles) ? aiState.selectedMentionFiles[0] : '';
        const mentionRoot = firstMention ? aiState.mentionedFileRoots?.[normalizeMentionedFilePath(firstMention)] : '';
        return mentionRoot || getRootForFileContext();
    }

    function getRootForAgentPath(path) {
        const normalized = normalizeMentionedFilePath(path);
        return aiState.mentionedFileRoots?.[normalized] || getRootForToolRun();
    }

    function resolveRootForToolPath(path, pathRoots, fallbackRoot) {
        const normalized = normalizeMentionedFilePath(path);
        if (!normalized) return fallbackRoot;
        const known = pathRoots?.[normalized];
        if (known) return known;
        const match = Object.keys(pathRoots || {}).find((p) => pathsMatchAgent(p, normalized));
        return (match && pathRoots[match]) || fallbackRoot;
    }

    function getIframeEditorContent(tabId) {
        if (!tabId) return null;
        const iframe = Array.from(document.querySelectorAll('#viewer iframe[data-tab-id]'))
            .find((node) => node.getAttribute('data-tab-id') === String(tabId));
        if (!iframe?.contentWindow) return null;
        try {
            const models = iframe.contentWindow.monaco?.editor?.getModels?.();
            const model = Array.isArray(models) ? models[0] : null;
            if (model && typeof model.getValue === 'function') {
                return model.getValue();
            }
        } catch (err) {
            return null;
        }
        return null;
    }

    function isAgentEditorSupported() {
        return true;
    }

    function updateAgentModeAvailability() {
        if (!elements.modeSelect) return;
        elements.modeSelect.disabled = false;
        if (aiState.chatMode === 'agent' && !elements.modeSelect.checked) {
            elements.modeSelect.checked = true;
        }
    }

    function parseAgentPatch(rawText) {
        const text = normalizeAgentPatchText(rawText);
        const ops = [];
        if (/^[[{]/.test(text)) {
            try {
                const parsed = JSON.parse(text);
                const list = Array.isArray(parsed) ? parsed : (Array.isArray(parsed?.ops) ? parsed.ops : []);
                for (const item of list) {
                    const search = normalizePatchChunk(typeof item?.s === 'string' ? item.s : (typeof item?.search === 'string' ? item.search : ''));
                    const replace = normalizePatchChunk(typeof item?.r === 'string' ? item.r : (typeof item?.replace === 'string' ? item.replace : ''));
                    if (search) ops.push({ search, replace });
                }
                if (ops.length > 0) return ops;
            } catch (e) { /* not a JSON patch payload */ }
        }
        const looseJsonOps = parseLooseJsonPatchOps(text);
        if (looseJsonOps.length > 0) return looseJsonOps;
        const cursorRegex = /<{7}\s*SEARCH\s*\n([\s\S]*?)\n={7}\n([\s\S]*?)\n>{7}\s*REPLACE/g;
        const simpleRegex = /(?:^|\n)(?:Change\s+\d+\s*\n+)?SEARCH:\s*\n?([\s\S]*?)\nREPLACE:\s*\n?([\s\S]*?)(?=\n+(?:Change\s+\d+\s*\n+)?SEARCH:|$)/gi;
        let m = null;
        while ((m = cursorRegex.exec(text)) !== null) {
            const search = normalizePatchChunk(m[1]);
            const replace = normalizePatchChunk(m[2]);
            if (search) ops.push({ search, replace });
        }
        if (ops.length === 0) {
            while ((m = simpleRegex.exec(text)) !== null) {
                const search = normalizePatchChunk(m[1]);
                const replace = normalizePatchChunk(m[2]);
                if (search) ops.push({ search, replace });
            }
        }
        return ops;
    }
    function parseLooseJsonPatchOps(text) {
        const ops = [];
        const pairRegex = /"(?:s|search)"\s*:\s*"((?:\\.|[^"\\])*)"\s*,\s*"(?:r|replace)"\s*:\s*"((?:\\.|[^"\\])*)"/g;
        let m = null;
        while ((m = pairRegex.exec(String(text || ''))) !== null) {
            const search = normalizePatchChunk(m[1]);
            const replace = normalizePatchChunk(m[2]);
            if (search) ops.push({ search, replace });
        }
        return ops;
    }

    function normalizeAgentPatchText(rawText) {
        let text = String(rawText || '').replace(/\r\n/g, '\n');
        text = text.replace(/```(?:diff|patch|text|txt|[a-z0-9_-]+)?\s*\n([\s\S]*?)```/gi, '$1');
        text = text.replace(/^\s*(?:Here(?:'|’)s|Here is).*?(?=\n(?:Change\s+\d+\s*\n+)?SEARCH:|\n<{7}\s*SEARCH)/is, '');
        return text.trim();
    }

    function normalizePatchChunk(value) {
        let text = String(value || '').replace(/\r\n/g, '\n');
        text = text.replace(/^\s*```[a-z0-9_-]*\s*\n?/i, '').replace(/\n?```\s*$/i, '');
        const trimmed = text.trim();
        if (/^\\text\{[\s\S]*\}$/.test(trimmed) && /\\n/.test(trimmed)) {
            text = trimmed.replace(/^\\text\{([\s\S]*)\}$/, '$1');
        }
        text = text.replace(/\\n/g, '\n').replace(/\\t/g, '\t').replace(/\\"/g, '"').replace(/\\'/g, "'");
        return text.replace(/^\n+|\n+$/g, '');
    }

    function normalizeAgentPath(path) {
        return String(path || '').replace(/^\//, '').trim();
    }
    function pathsMatchAgent(a, b) {
        const na = normalizeAgentPath(a);
        const nb = normalizeAgentPath(b);
        return na !== '' && (na === nb || na.endsWith('/' + nb) || nb.endsWith('/' + na));
    }
    async function resolveAgentUserRole() {
        if (aiState.agentUserRole != null) return aiState.agentUserRole;
        try {
            if (typeof getUserRole === 'function') {
                aiState.agentUserRole = Number(await getUserRole()) || 0;
                return aiState.agentUserRole;
            }
        } catch (e) { logAiError('Resolve user role', e); }
        aiState.agentUserRole = 0;
        return 0;
    }
    function getAgentPlanMessageEl() {
        if (!aiState.agentRunMessageId) return null;
        return document.getElementById(aiState.agentRunMessageId);
    }
    function ensureAgentPlanContainer(msgEl) {
        if (!msgEl) return null;
        const content = msgEl.querySelector('.message-content');
        if (!content) return null;
        let plan = content.querySelector('.ai-agent-plan');
        if (!plan) {
            plan = document.createElement('div');
            plan.className = 'ai-agent-plan running ai-agent-plan-empty';
            const head = document.createElement('div');
            head.className = 'ai-agent-plan-head';
            head.innerHTML = '<span class="ai-agent-plan-pulse"></span><span class="ai-agent-plan-head-text"></span>';
            const steps = document.createElement('div');
            steps.className = 'ai-agent-plan-steps';
            const streamShell = document.createElement('div');
            streamShell.className = 'ai-agent-stream-shell';
            streamShell.innerHTML = '<pre class="ai-agent-stream-text"></pre>';
            plan.appendChild(head);
            plan.appendChild(steps);
            plan.appendChild(streamShell);
            const answer = document.createElement('div');
            answer.className = 'ai-agent-final-answer';
            content.replaceChildren(plan, answer);
        } else if (!plan.querySelector('.ai-agent-stream-shell')) {
            const streamShell = document.createElement('div');
            streamShell.className = 'ai-agent-stream-shell';
            streamShell.innerHTML = '<pre class="ai-agent-stream-text"></pre>';
            plan.appendChild(streamShell);
        }
        return plan;
    }
    function setAgentStreamText(text) {
        const shell = ensureAgentPlanContainer(getAgentPlanMessageEl())?.querySelector('.ai-agent-stream-shell');
        const pre = shell?.querySelector('.ai-agent-stream-text');
        if (!shell || !pre) return;
        const t = String(text ?? '');
        if (!t.trim()) {
            shell.classList.remove('visible');
            pre.textContent = '';
            return;
        }
        shell.classList.add('visible');
        pre.textContent = t;
    }
    function hideAgentStreamShell() {
        setAgentStreamText('');
    }
    function formatAgentElapsed(ms) {
        const sec = Math.max(0, Math.floor(ms / 1000));
        if (sec < 60) return `${sec}s`;
        const min = Math.floor(sec / 60);
        const rem = sec % 60;
        return rem > 0 ? `${min}m ${rem}s` : `${min}m`;
    }
    function isChatToolRunMode() {
        return aiState.toolRunMode === 'chat';
    }
    function getToolRunRoleLabel() {
        if (isChatToolRunMode()) return tr('aiModeChat', 'Chat');
        if (aiState.toolRunMode === 'agent') return tr('aiModeAgent', 'Agent');
        return getCurrentModelDisplayName();
    }
    function getToolRunWorkingLabel() {
        return isChatToolRunMode()
            ? tr('aiChatWorking', 'Chat: working on your request…')
            : tr('aiAgentWorking', 'Agent is working…');
    }
    function getToolRunPlanDoneLabel() {
        const outcome = aiState.agentRunOutcome;
        if (outcome === 'success') {
            return isChatToolRunMode()
                ? tr('aiChatPlanSuccess', 'Chat: request completed')
                : tr('aiAgentPlanSuccess', 'Agent finished successfully');
        }
        if (outcome === 'error') {
            return isChatToolRunMode()
                ? tr('aiChatPlanFailed', 'Chat: request failed')
                : tr('aiAgentPlanFailed', 'Agent finished with an error');
        }
        if (outcome === 'stopped') {
            return isChatToolRunMode()
                ? tr('aiChatPlanStopped', 'Chat: request stopped')
                : tr('aiAgentPlanStopped', 'Agent run stopped');
        }
        return isChatToolRunMode()
            ? tr('aiChatPlanDone', 'Chat steps')
            : tr('aiAgentPlanDone', 'Agent plan');
    }
    function getToolRunThinkingLabel() {
        return isChatToolRunMode()
            ? tr('aiChatThinking', 'Thinking…')
            : tr('aiAgentThinking', 'Thinking…');
    }
    function applyToolRunRoleToMessage(messageId) {
        if (!messageId) return;
        const roleEl = document.getElementById(messageId)?.querySelector('.message-role');
        if (roleEl) roleEl.textContent = getToolRunRoleLabel();
    }
    function updateAgentElapsedStreamText() {
        if (!aiState.agentRunStartedAt) return;
        const elapsed = formatAgentElapsed(Date.now() - aiState.agentRunStartedAt);
        setAgentStreamText(`${getToolRunThinkingLabel()} (${elapsed})`);
    }
    function startAgentRunTimer() {
        stopAgentRunTimer();
        aiState.agentRunStartedAt = Date.now();
        updateAgentElapsedStreamText();
        aiState.agentTimerInterval = setInterval(updateAgentElapsedStreamText, 1000);
    }
    function stopAgentRunTimer() {
        if (aiState.agentTimerInterval) {
            clearInterval(aiState.agentTimerInterval);
            aiState.agentTimerInterval = null;
        }
    }
    function cleanupAgentAskUser() {
        const box = getAgentPlanMessageEl()?.querySelector('.ai-agent-ask-user');
        if (box) box.remove();
        aiState.pendingAskUserResolve = null;
        aiState.pendingAskUserReject = null;
    }
    function agentPlanToolLabel(toolName, args = {}) {
        const p = args.path || '';
        const off = args.offset_line ? `, ${tr('aiPlanLine', 'line')} ${args.offset_line}` : '';
        const labels = {
            read_file: () => `${tr('aiPlanReadFile', 'Read file')}: ${p}${off}`,
            write_file: () => `${tr('aiPlanWriteFile', 'Write file')}: ${p}`,
            create_file: () => `${tr('aiPlanCreateFile', 'Create file')}: ${p}`,
            make_directory: () => `${tr('aiPlanCreateDir', 'Create folder')}: ${p}`,
            copy_file: () => `${tr('aiPlanCopyFile', 'Copy file')}: ${args.source_path || ''} -> ${args.target_path || ''}`,
            rename_file: () => `${tr('aiPlanRenameFile', 'Rename file')}: ${args.old_path || ''} -> ${args.new_path || ''}`,
            move_path: () => `${tr('aiPlanMovePath', 'Move path')}: ${args.old_path || ''} -> ${args.new_path || ''}`,
            delete_file: () => `${tr('aiPlanDeleteFile', 'Delete file')}: ${p}`,
            delete_directory: () => `${tr('aiPlanDeleteDir', 'Delete folder')}: ${p}`,
            search_replace: () => `${tr('aiPlanEditFile', 'Edit file')}: ${p}`,
            search_by_regex: () => {
                const n = Array.isArray(args.paths) ? args.paths.length : (args.path ? 1 : 0);
                const scope = n
                    ? tr('aiPlanRegexInFiles', 'in {count} file(s)', { count: n })
                    : tr('aiPlanRegexOpenFile', 'open file');
                return `${tr('aiPlanSearchRegex', 'Regex search')}: "${String(args.pattern || '').slice(0, 40)}" (${scope})`;
            },
            replace_by_regex: () => `${tr('aiPlanReplaceRegex', 'Regex replace')}: ${p}`,
            verify_syntax: () => `${tr('aiPlanCheckSyntax', 'Check syntax')}: ${p}`,
            compile_and_check: () => `${tr('aiPlanCompile', 'Compile')}: ${p}`,
            search_in_files: () => {
                const n = Array.isArray(args.paths) ? args.paths.length : 0;
                return `${tr('aiPlanSearch', 'Search in files')}: "${String(args.query || '').slice(0, 40)}" (${n})`;
            },
            web_search: () => `${tr('aiPlanWebSearch', 'Web search')}: "${String(args.query || '').slice(0, 40)}"`,
            image_search: () => `${tr('aiPlanImageSearch', 'Image search')}: "${String(args.query || '').slice(0, 40)}"`,
            open_url_fetch: () => `${tr('aiPlanFetchUrl', 'Fetch URL')}: ${String(args.url || '').slice(0, 80)}`,
            download_file: () => `${tr('aiPlanDownloadFile', 'Download file')}: ${args.target_path || ''}`,
            diff_file: () => `${tr('aiPlanDiffFile', 'Diff file')}: ${p}`,
            stat_path: () => `${tr('aiPlanStatPath', 'Path info')}: ${p}`,
            json_edit: () => `${tr('aiPlanJsonEdit', 'Edit JSON')}: ${p}`,
            csv_preview: () => `${tr('aiPlanCsvPreview', 'Preview CSV')}: ${p}`,
            archive_extract: () => `${tr('aiPlanArchiveExtract', 'Extract archive')}: ${args.archive_path || ''}`,
            archive_create: () => `${tr('aiPlanArchiveCreate', 'Create archive')}: ${args.archive_path || ''}`,
            extract_document_text: () => `${tr('aiPlanExtract', 'Extract text')}: ${p}`,
            list_directory: () => `${tr('aiPlanListDir', 'List folder')}: ${args.path || '.'}`,
            apply_editor_patch: () => `${tr('aiPlanPatchEditor', 'Edit in editor')} (${Array.isArray(args.patches) ? args.patches.length : 0})`,
            run_command: () => `${tr('aiPlanRunCommand', 'Run command')}: ${Array.isArray(args.command) ? args.command.join(' ') : ''}`,
            run_script: () => `${tr('aiPlanRunScript', 'Run script')}: ${args.language || ''}`,
            delegate_subagent: () => `${tr('aiPlanSubagent', 'Subagent')}: ${String(args.task || '').slice(0, 60)}`,
            save_open_document: () => tr('aiPlanSaveOpen', 'Save document'),
            export_markdown_pdf: () => `${tr('aiPlanExportMdPdf', 'Export PDF (Markdown)')}: ${p || '…'}`,
            export_latex_pdf: () => `${tr('aiPlanExportLatexPdf', 'Export PDF (LaTeX)')}: ${p || '…'}`,
            generate_speech: () => `${tr('aiPlanGenerateSpeech', 'Generate speech')}: ${String(args.text || '').slice(0, 40)}`,
            get_speech_progress: () => tr('aiPlanSpeechProgress', 'Speech progress'),
            transcribe_audio: () => `${tr('aiPlanTranscribeAudio', 'Transcribe audio')}: ${args.audio_path || ''}`,
            get_transcription_progress: () => tr('aiPlanTranscriptionProgress', 'Transcription progress'),
            compile_open_document: () => tr('aiPlanPreviewDoc', 'Preview document'),
            get_open_file: () => tr('aiPlanGetOpen', 'Open file'),
            ask_user: () => tr('aiPlanAskUser', 'Ask user'),
            staged: () => `${tr('aiPlanEdited', 'Edited file')}: ${p}`,
            save: () => tr('aiAgentSaveServer', 'Save to server')
        };
        return labels[toolName] ? labels[toolName]() : String(toolName || '').replace(/_/g, ' ');
    }
    function agentPlanToolIconMarkup(toolName) {
        const attrs = 'width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
        const svg = {
            read_file: `<svg ${attrs}><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"/><path d="M14 2v6h6"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>`,
            write_file: `<svg ${attrs}><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>`,
            create_file: `<svg ${attrs}><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="M9 15h6"/></svg>`,
            search_replace: `<svg ${attrs}><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M8 11h6"/></svg>`,
            replace_by_regex: `<svg ${attrs}><path d="m3 17 2 2 4-4"/><path d="M13 6h8"/><path d="M13 12h8"/><path d="M13 18h8"/></svg>`,
            search_by_regex: `<svg ${attrs}><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M8 11h.01"/><path d="M11 8v6"/></svg>`,
            compile_and_check: `<svg ${attrs}><path d="m8 6 4-4 4 4"/><path d="M12 2v10"/><path d="M20 12v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8"/></svg>`,
            compile_open_document: `<svg ${attrs}><polygon points="5 3 19 12 5 21 5 3"/></svg>`,
            export_markdown_pdf: `<svg ${attrs}><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="m9 15 3 3 3-3"/></svg>`,
            export_latex_pdf: `<svg ${attrs}><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15h6"/><path d="M12 12v6"/></svg>`,
            generate_speech: `<svg ${attrs}><path d="M11 5 6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M19 5a9 9 0 0 1 0 14"/></svg>`,
            transcribe_audio: `<svg ${attrs}><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><path d="M12 19v3"/></svg>`,
            run_command: `<svg ${attrs}><path d="m4 17 6-6-6-6"/><path d="M12 19h8"/></svg>`,
            ask_user: `<svg ${attrs}><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 1 1 5.8 1c0 2-3 2-3 4"/><path d="M12 17h.01"/></svg>`
        };
        return svg[toolName] || `<svg ${attrs}><circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 3"/></svg>`;
    }
    function agentPlanResultSummary(toolName, result) {
        if (!result || result.error) return String(result?.error || tr('aiPlanResultFailed', 'Error'));
        if (toolName === 'read_file') {
            const lines = result.returned_lines ?? result.line_count ?? '?';
            const total = result.total_lines ?? '?';
            let msg = tr('aiPlanResultLines', 'OK · lines {lines}/{total}', { lines, total });
            if (result.truncated) msg += tr('aiPlanResultMoreLines', ' · more available');
            return msg;
        }
        if (toolName === 'extract_document_text') {
            return tr('aiPlanResultDone', 'Done') + ` · ${result.format || 'markdown'}`;
        }
        if (toolName === 'diff_file') {
            return result.changed
                ? tr('aiPlanResultDone', 'Done') + ' · ' + tr('aiPlanResultDiffChanged', 'changed')
                : tr('aiPlanResultDone', 'Done') + ' · ' + tr('aiPlanResultDiffNone', 'no changes');
        }
        if (toolName === 'search_in_files') {
            return tr('aiPlanResultMatches', 'OK · {count} match(es)', { count: result.match_count ?? 0 });
        }
        if (toolName === 'search_by_regex') {
            return tr('aiPlanResultMatches', 'OK · {count} match(es)', { count: result.match_count ?? 0 });
        }
        if (toolName === 'web_search') {
            return tr('aiPlanResultMatches', 'OK · {count} match(es)', { count: result.count ?? 0 });
        }
        if (toolName === 'image_search') {
            return tr('aiPlanResultMatches', 'OK · {count} match(es)', { count: result.count ?? 0 });
        }
        if (toolName === 'csv_preview') {
            return tr('aiPlanResultMatches', 'OK · {count} match(es)', { count: result.rows_returned ?? 0 });
        }
        if (toolName === 'verify_syntax') {
            if (result.blocked_security) return tr('aiPlanResultBlocked', 'Blocked · unsafe code');
            return result.success ? tr('aiPlanResultSyntaxOk', 'OK · syntax valid') : tr('aiPlanResultSyntaxErr', 'Syntax error');
        }
        if (toolName === 'rename_file' && result.path) {
            return tr('aiPlanResultDone', 'Done') + ` · ${result.path}`;
        }
        if (toolName === 'move_path' && result.path) {
            return tr('aiPlanResultDone', 'Done') + ` · ${result.path}`;
        }
        if (toolName === 'delete_file' && result.deleted_path) {
            return tr('aiPlanResultDone', 'Done') + ` · ${result.deleted_path}`;
        }
        if (toolName === 'delete_directory' && result.deleted_path) {
            return tr('aiPlanResultDone', 'Done') + ` · ${result.deleted_path}`;
        }
        if (toolName === 'compile_and_check') {
            if (result.blocked_security) return tr('aiPlanResultBlocked', 'Blocked · unsafe code');
            return result.success ? tr('aiPlanResultCompiled', 'OK · compiled') : tr('aiPlanResultSyntaxErr', 'Syntax error');
        }
        if (toolName === 'export_markdown_pdf' || toolName === 'export_latex_pdf') {
            return result.success
                ? tr('aiPlanResultPdf', 'OK · PDF: {path}', { path: result.pdf_path || '…' })
                : tr('aiPlanResultFailed', 'Error');
        }
        if (toolName === 'ask_user' && result.choice_label) return result.choice_label;
        if (result.staged) return tr('aiPlanResultStaged', 'Staged · awaiting confirm');
        if (result.persisted) return tr('aiAgentSavedShort', 'Saved');
        if (result.success === true) return tr('aiPlanResultDone', 'Done');
        return tr('aiPlanResultDone', 'Done');
    }
    function serverRunEventTool(event) {
        const text = String(event?.text || '').toLowerCase();
        if (/read file/.test(text)) return 'read_file';
        if (/write file/.test(text)) return 'write_file';
        if (/export pdf|fallback pdf/.test(text)) return 'export_latex_pdf';
        if (/compiler|compile/.test(text)) return 'compile_and_check';
        if (/queued|started|finished/.test(text)) return 'run_command';
        return String(event?.data?.tool || event?.type || 'run_command');
    }
    function serverRunEventStatus(event, index, total, jobStatus) {
        if (event?.type === 'error') return 'error';
        if (event?.type === 'compile_error') return 'error';
        if (index === total - 1 && jobStatus !== 'done' && jobStatus !== 'error') return 'running';
        return 'done';
    }
    function serverRunEventResult(event) {
        const data = event?.data || {};
        if (event?.type === 'compile_error') return String(data.error || tr('aiPlanResultFailed', 'Error'));
        if (data.path) return String(data.path);
        if (data.error) return String(data.error);
        return '';
    }
    function renderServerRunPlan(messageId, job) {
        aiState.agentRunMessageId = messageId;
        aiState.toolRunMode = (job?.chat_mode === 'agent') ? 'agent' : 'chat';
        const events = Array.isArray(job?.events) ? job.events : [];
        aiState.agentPlanSteps = events.map((event, index) => ({
            id: `server_${index}`,
            tool: serverRunEventTool(event),
            label: String(event?.text || event?.type || ''),
            status: serverRunEventStatus(event, index, events.length, job?.status || ''),
            result: serverRunEventResult(event),
            resources: Array.isArray(event?.data?.resources) ? event.data.resources : []
        }));
        renderAgentPlanInMessage();
    }
    function appendAgentResourcePreview(host, step) {
        const result = step?.rawResult || {};
        const items = Array.isArray(result.results) ? result.results.slice(0, 6) : [];
        if (!items.length || !['web_search', 'image_search'].includes(step.tool)) return;
        const wrap = document.createElement('div');
        wrap.className = step.tool === 'image_search' ? 'ai-agent-resource-grid' : 'ai-agent-resource-list';
        items.forEach((item) => {
            const link = document.createElement('a');
            link.href = item.url || item.source || '#';
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.className = 'ai-agent-resource-item';
            if (step.tool === 'image_search') {
                const img = document.createElement('img');
                img.src = item.thumbnail || item.url || '';
                img.alt = item.title || '';
                link.appendChild(img);
            }
            const title = document.createElement('span');
            title.textContent = item.title || item.url || item.source || '';
            link.appendChild(title);
            wrap.appendChild(link);
        });
        host.appendChild(wrap);
    }
    function planLabelFromStoredStep(step) {
        if (!step) return '';
        if (step.tool && step.tool !== 'unknown') {
            const m = String(step.label || '').match(/\(([^)]+)\)/);
            const path = m ? m[1].replace(/ @.*$/, '').trim() : '';
            return agentPlanToolLabel(step.tool, { path, patches: [] });
        }
        return step.label || '';
    }
    function waitForAgentUserChoice(question, options) {
        return new Promise((resolve) => {
            const opts = (Array.isArray(options) ? options : []).map((o, i) => {
                if (typeof o === 'string') return { id: String(i), label: o };
                return { id: String(o?.id ?? i), label: String(o?.label ?? o?.id ?? i) };
            }).filter((o) => o.label);
            if (!opts.length) {
                resolve({ ok: false, error: 'No options' });
                return;
            }
            const plan = ensureAgentPlanContainer(getAgentPlanMessageEl());
            if (!plan) {
                resolve({ ok: false, error: 'No plan UI' });
                return;
            }
            let box = plan.querySelector('.ai-agent-ask-user');
            if (!box) {
                box = document.createElement('div');
                box.className = 'ai-agent-ask-user';
                plan.appendChild(box);
            }
            box.replaceChildren();
            const title = document.createElement('div');
            title.className = 'ai-agent-ask-user-title';
            title.textContent = tr('aiAskUserPrompt', 'Your choice is needed');
            box.appendChild(title);
            const q = document.createElement('div');
            q.className = 'ai-agent-ask-user-q';
            q.textContent = String(question || '');
            box.appendChild(q);
            const btns = document.createElement('div');
            btns.className = 'ai-agent-ask-user-options';
            opts.forEach((o) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ai-agent-ask-user-btn';
                btn.textContent = o.label;
                btn.addEventListener('click', () => {
                    cleanupAgentAskUser();
                    resolve({ ok: true, choice_id: o.id, choice_label: o.label });
                });
                btns.appendChild(btn);
            });
            box.appendChild(btns);
        });
    }
    function resetAgentRun(messageId, mentionPaths = [], toolRunMode = null) {
        aiState.toolRunMode = toolRunMode === 'chat' || toolRunMode === 'agent'
            ? toolRunMode
            : (aiState.chatMode === 'agent' ? 'agent' : 'chat');
        stopAgentRunTimer();
        cleanupAgentAskUser();
        aiState.agentPlanSteps = [];
        aiState.agentRunOutcome = null;
        aiState.agentNewPaths = new Set();
        aiState.pendingAgentPatch = null;
        aiState.pendingAgentPatches = {};
        aiState.agentRunMessageId = messageId || null;
        startAgentRunTimer();
        document.querySelectorAll('.ai-agent-undo-last-footer').forEach((el) => {
            if (el.closest('.message')?.id !== messageId) el.remove();
        });
        aiState.agentMentionPaths = Array.isArray(mentionPaths) ? mentionPaths.map(normalizeAgentPath).filter(Boolean) : [];
        const msgEl = getAgentPlanMessageEl();
        ensureAgentPlanContainer(msgEl);
        renderAgentPlanInMessage();
    }
    function finishAgentRun(outcome) {
        aiState.agentRunOutcome = outcome;
        aiState.agentPlanSteps.forEach((step) => {
            if (step.status === 'running') {
                step.status = outcome === 'success' ? 'done' : 'error';
                if (!step.result && outcome !== 'success') step.result = tr('aiAgentInterrupted', 'Assistant action interrupted');
            }
        });
        stopAgentRunTimer();
        hideAgentStreamShell();
        renderAgentPlanInMessage();
    }
    function upsertAgentPlanStep(stepId, patch) {
        const idx = aiState.agentPlanSteps.findIndex((s) => s.id === stepId);
        const row = { id: stepId, ...(idx === -1 ? {} : aiState.agentPlanSteps[idx]), ...patch };
        if (idx === -1) aiState.agentPlanSteps.push(row);
        else aiState.agentPlanSteps[idx] = row;
        renderAgentPlanInMessage();
        return row;
    }
    function planResultLineCount(text) {
        const t = String(text ?? '');
        if (!t.trim()) return 0;
        return t.split(/\r\n|\r|\n/).length;
    }
    function isLongPlanResult(text) {
        return planResultLineCount(text) > 2;
    }
    function appendPlanStepRow(stepsHost, step) {
        const row = document.createElement('div');
        row.className = `ai-agent-plan-step ${step.status || 'pending'}`;
        const icon = document.createElement('span');
        icon.className = 'ai-agent-plan-icon';
        icon.textContent = step.status === 'done' ? '✓' : (step.status === 'error' ? '!' : (step.status === 'running' ? '…' : '·'));
        const label = document.createElement('span');
        label.className = 'ai-agent-plan-label';
        const toolIcon = document.createElement('span');
        toolIcon.className = 'ai-agent-plan-tool-svg';
        toolIcon.innerHTML = agentPlanToolIconMarkup(step.tool || '');
        const labelText = document.createElement('span');
        labelText.className = 'ai-agent-plan-label-text';
        labelText.textContent = step.label || step.tool || '';
        label.appendChild(toolIcon);
        label.appendChild(labelText);
        const resultText = String(step.result || '');
        row.appendChild(icon);
        if (isLongPlanResult(resultText)) {
            icon.style.gridRow = '1 / span 2';
            row.appendChild(label);
            const wrap = document.createElement('div');
            wrap.className = 'ai-agent-plan-result-wrap';
            const body = document.createElement('div');
            body.className = 'ai-agent-plan-result-body collapsed';
            body.textContent = resultText;
            const expandBtn = document.createElement('button');
            expandBtn.type = 'button';
            expandBtn.className = 'ai-agent-plan-result-expand visible';
            expandBtn.textContent = tr('aiExpand', 'развернуть');
            expandBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const collapsed = body.classList.toggle('collapsed');
                expandBtn.textContent = collapsed ? tr('aiExpand', 'развернуть') : tr('aiCollapse', 'свернуть');
            });
            wrap.appendChild(body);
            wrap.appendChild(expandBtn);
            row.appendChild(wrap);
            appendAgentResourcePreview(row, step);
        } else {
            row.appendChild(label);
            const result = document.createElement('span');
            result.className = 'ai-agent-plan-result';
            result.textContent = resultText;
            row.appendChild(result);
            appendAgentResourcePreview(row, step);
        }
        stepsHost.appendChild(row);
    }
    function renderAgentPlanInMessage() {
        const msgEl = getAgentPlanMessageEl();
        const plan = ensureAgentPlanContainer(msgEl);
        if (!plan) return;
        const stepsHost = plan.querySelector('.ai-agent-plan-steps');
        if (!stepsHost) return;
        const visibleSteps = aiState.agentPlanSteps;
        const running = aiState.agentPlanSteps.some((s) => s.status === 'running')
            || (!!aiState.agentRunMessageId && aiState.isGenerating);
        const hasSteps = visibleSteps.length > 0;
        plan.classList.toggle('running', running);
        plan.classList.toggle('done', !running && hasSteps);
        plan.classList.toggle('ai-agent-plan-empty', !hasSteps && !running);
        const outcome = running ? null : aiState.agentRunOutcome;
        plan.classList.toggle('outcome-success', outcome === 'success');
        plan.classList.toggle('outcome-error', outcome === 'error');
        plan.classList.toggle('outcome-stopped', outcome === 'stopped');
        const headText = plan.querySelector('.ai-agent-plan-head-text');
        if (headText) {
            headText.textContent = running ? getToolRunWorkingLabel() : getToolRunPlanDoneLabel();
        }
        stepsHost.replaceChildren();
        visibleSteps.forEach((step) => appendPlanStepRow(stepsHost, step));
    }
    function resolveAgentSearchPaths() {
        const paths = new Set(aiState.agentMentionPaths || []);
        const open = normalizeAgentPath(aiState.currentFileName);
        if (open) paths.add(open);
        return Array.from(paths);
    }
    function removeAgentActionFooter(msg) {
        if (!msg) return;
        msg.querySelector('.message-footer')?.remove();
        delete msg.dataset.agentPatchId;
        delete msg.dataset.agentApplied;
    }
    function getStagedAgentContent(path) {
        const key = normalizeAgentPath(path);
        if (aiState.pendingAgentPatches[key]) return aiState.pendingAgentPatches[key].after;
        const found = Object.entries(aiState.pendingAgentPatches).find(([p]) => pathsMatchAgent(key, p));
        return found ? found[1].after : null;
    }
    function findStagedPatchForOpenFile() {
        const openFile = normalizeAgentPath(aiState.currentFileName);
        if (!openFile) return null;
        if (aiState.pendingAgentPatches[openFile]) return aiState.pendingAgentPatches[openFile];
        const found = Object.entries(aiState.pendingAgentPatches).find(([p]) => pathsMatchAgent(openFile, p));
        return found ? found[1] : null;
    }
    function stageAgentFileChange(filePath, beforeText, afterText, meta = {}) {
        const norm = normalizeAgentPath(filePath);
        if (!norm) return;
        aiState.pendingAgentPatches[norm] = {
            before: String(beforeText ?? ''),
            after: String(afterText ?? ''),
            tool: meta.tool || '',
            root: meta.root || getRootForAgentPath(norm) || ''
        };
        const openName = normalizeAgentPath(aiState.currentFileName);
        if (openName && pathsMatchAgent(openName, norm)) {
            showAgentPreviewInEditor(aiState.pendingAgentPatches[norm].before, aiState.pendingAgentPatches[norm].after);
        }
        void persistSingleStagedAgentChange(norm).catch((err) => flashStatus(err.message || 'Save failed'));
    }
    async function persistSingleStagedAgentChange(path) {
        const patch = aiState.pendingAgentPatches[normalizeAgentPath(path)];
        if (!patch) return;
        const body = new URLSearchParams({
            action: 'ai_agent_tool',
            tool: 'write_file',
            args: JSON.stringify({ path: normalizeAgentPath(path), content: patch.after, persist: true }),
            root: patch.root || getRootForFileContext() || ''
        });
        const res = await fetch(AI_CONFIG.fileApiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) throw new Error(data.error || `Failed to save staged change: ${path}`);
        void highlightNewFilesInTree([{ path: data.path || normalizeAgentPath(path), type: 'file', root: data.root || patch.root || '' }]);
    }
    async function applyAllPendingAgentChanges() {
        const entries = Object.entries(aiState.pendingAgentPatches);
        for (const [path, patch] of entries) {
            const body = new URLSearchParams({
                action: 'ai_agent_tool',
                tool: 'write_file',
                args: JSON.stringify({ path, content: patch.after, persist: true }),
                root: patch.root || getRootForAgentPath(path) || ''
            });
            const res = await fetch(AI_CONFIG.fileApiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) throw new Error(data.error || `Failed to apply changes: ${path}`);
        }
        const openPatch = findStagedPatchForOpenFile();
        if (openPatch) {
            setActiveEditorContent(openPatch.after, []);
        }
        await saveActiveEditorToServer();
        clearEditorHighlights();
        const paths = entries.map(([p]) => p);
        aiState.pendingAgentPatches = {};
        return paths;
    }
    function normalizeTreePath(path) {
        return String(path || '').replace(/^\/+/, '').trim();
    }
    function normalizeVisibleTreePath(path, root = '') {
        let p = normalizeTreePath(path);
        const prefixes = {
            clientsync: ['clientsync/', '.clientsync/'],
            documents: ['documents/', '.documents/'],
            photos: ['photos/', '.photos/'],
            pdf: ['pdf/', '.pdf/'],
            archive: ['archive/', '.archive/'],
            trash: ['trash/', '.trash/'],
            temp: ['temp/', '.temp_files/']
        }[String(root || '').trim()] || [];
        prefixes.forEach((prefix) => {
            if (p.startsWith(prefix)) p = p.slice(prefix.length);
        });
        return p;
    }
    function isVisibleUserTreePath(path) {
        const p = normalizeTreePath(path);
        if (!p || p.includes('..')) return false;
        if (/^(?:tmp|var|run|dev|proc|sys|home|workspace)\//i.test(p)) return false;
        if (/(?:^|\/)(?:cloud_ai_latex_|cloud_ai_pdf_)/i.test(p)) return false;
        if (/(?:^|\/)input_[a-z0-9_.-]*\.(?:aux|log|out|toc|fls|fdb_latexmk|synctex\.gz)$/i.test(p)) return false;
        return true;
    }
    function treeNodeExists(path) {
        const p = normalizeTreePath(path);
        if (!p) return false;
        const esc = typeof CSS !== 'undefined' && CSS.escape ? CSS.escape(p) : p.replace(/"/g, '\\"');
        return !!document.querySelector(`#tree .node[data-path="${esc}"]`);
    }
    async function rebuildFileTree() {
        try {
            await fetch(new URL(AI_CONFIG.fileApiEndpoint, window.location.href), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: new URLSearchParams({ action: 'invalidate_file_tree' })
            });
        } catch (e) { logAiError('Invalidate file tree', e); }
        if (typeof window.buildTree === 'function') await window.buildTree('');
    }
    async function highlightNewFilesInTree(paths = [], { openLastPdf = false } = {}) {
        const items = [];
        const seen = new Set();
        for (const raw of Array.isArray(paths) ? paths : []) {
            const item = typeof raw === 'string'
                ? { path: normalizeVisibleTreePath(raw), type: 'file', root: '' }
                : {
                    path: normalizeVisibleTreePath(raw?.path || '', raw?.root || ''),
                    type: raw?.type === 'dir' ? 'dir' : 'file',
                    root: String(raw?.root || '').trim()
                };
            const key = `${item.root}:${item.path}`;
            if (!isVisibleUserTreePath(item.path) || seen.has(key)) continue;
            seen.add(key);
            items.push(item);
        }
        if (!items.length) return;
        items.forEach((item) => aiState.agentNewPaths.add(item.path));
        let root = getRootForFileContext();
        const targetRoot = items.find((item) => item.root)?.root || root;
        if (targetRoot && targetRoot !== root && typeof window.switchCloudRoot === 'function') {
            await window.switchCloudRoot(targetRoot, { keepSelection: true, silent: true });
            root = targetRoot;
        }
        try {
            if (typeof window.insertTreeItems === 'function') {
                const grouped = new Map();
                items.forEach((item) => {
                    const itemRoot = item.root || root;
                    if (!grouped.has(itemRoot)) grouped.set(itemRoot, []);
                    grouped.get(itemRoot).push(item);
                });
                for (const [itemRoot, group] of grouped.entries()) {
                    await window.insertTreeItems(group.map((item) => ({
                        name: item.path.split('/').pop(),
                        path: item.path,
                        type: item.type,
                        size: item.type === 'dir' ? null : 0,
                        mtime: Math.floor(Date.now() / 1000)
                    })), itemRoot);
                }
            } else if (typeof window.buildTree === 'function') {
                await window.buildTree('');
            }
        } catch (e) {
            console.warn('insertTreeItems failed:', e);
        }
        const missing = items.filter((item) => !treeNodeExists(item.path));
        if (missing.length && typeof window.buildTree === 'function') {
            await rebuildFileTree();
        }
        if (openLastPdf) {
            const pdfItem = items.filter((item) => /\.pdf$/i.test(item.path)).pop();
            const pdf = pdfItem?.path || '';
            if (pdf && typeof openFileInTab === 'function') {
                try { await openFileInTab(pdf, false, pdfItem?.root || root); } catch (e) { logAiError('Open last PDF in tab', e); }
            }
        }
    }
    async function syncTreeForMoveOrDelete(addPaths = [], removePaths = []) {
        const adds = [...new Set(addPaths.map(normalizeTreePath).filter(Boolean))];
        const removes = [...new Set(removePaths.map(normalizeTreePath).filter(Boolean))];
        const root = getRootForFileContext();
        if (root === 'main' && typeof window.patchTreeCache === 'function') {
            const ops = [];
            removes.forEach((path) => ops.push({ op: 'remove', path }));
            adds.forEach((path) => ops.push({
                op: 'add',
                item: { name: path.split('/').pop(), path, type: 'file', size: 0, mtime: Math.floor(Date.now() / 1000) }
            }));
            try { await window.patchTreeCache(ops); } catch (e) { logAiError('Patch tree cache', e); }
            removes.forEach((path) => {
                if (typeof window.treeNodeRemove === 'function') window.treeNodeRemove(path);
            });
        }
        await rebuildFileTree();
        if (adds.length) await highlightNewFilesInTree(adds);
    }
    async function refreshFileTreeAfterAgent(paths = [], opts = {}) {
        const merged = [...paths, ...(aiState.agentNewPaths ? [...aiState.agentNewPaths] : [])];
        await highlightNewFilesInTree(merged, opts);
    }
    async function persistAgentWorkAfterRun() {
        const paths = await applyAllPendingAgentChanges();
        await refreshFileTreeAfterAgent(paths);
        if (isOpenLatexEditorTab()) {
            const openName = normalizeAgentPath(aiState.currentFileName);
            if (openName && paths.some((p) => pathsMatchAgent(p, openName))) {
                void compileLatexInIframe(false);
            }
        }
        return paths;
    }
    async function saveActiveEditorToServer() {
        const activeTab = window.tabsState?.tabs?.find((tab) => tab.id === window.tabsState?.activeTabId);
        if (!activeTab?.path) return false;
        let content = '';
        if (activeTab.markdownEditor || activeTab.latexEditor) {
            const iframeContent = getIframeEditorContent(activeTab.id);
            if (iframeContent != null) content = iframeContent;
        }
        if (!content && window.monacoManager?.getContent) {
            const mc = window.monacoManager.getContent(activeTab.id);
            if (mc != null && mc !== '') content = mc;
        }
        if (!content && window.editorsMap?.has(activeTab.id)) {
            content = window.editorsMap.get(activeTab.id)?.getValue?.() || '';
        }
        if (activeTab.markdownEditor || activeTab.latexEditor) {
            try {
                const saveRes = await sendIframeMonacoAiAction('save');
                if (typeof window.markTabAsModified === 'function') window.markTabAsModified(activeTab.id, false);
                return !!saveRes.ok;
            } catch (e) {
                console.warn('Iframe save failed:', e);
            }
        }
        if (typeof window.saveFile === 'function') {
            try { return await window.saveFile(); } catch (e) { logAiError('Save active editor', e); }
        }
        if (content !== '') {
            try {
                const root = typeof getTabRoot === 'function' ? getTabRoot(activeTab) : getRootForFileContext();
                const body = new URLSearchParams({ action: 'save_file', path: activeTab.path, content, root: root || '' });
                const res = await fetch(AI_CONFIG.fileApiEndpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    credentials: 'same-origin',
                    body
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.error) throw new Error(data.error || 'save failed');
                if (typeof window.markTabAsModified === 'function') window.markTabAsModified(activeTab.id, false);
                activeTab.content = content;
                return true;
            } catch (e) {
                console.warn('Direct save_file failed:', e);
            }
        }
        return false;
    }
    function cancelAllPendingAgentChanges() {
        const openPatch = findStagedPatchForOpenFile();
        if (openPatch) {
            setActiveEditorContent(openPatch.before, []);
        }
        clearEditorHighlights();
        aiState.pendingAgentPatches = {};
    }
    function discardAgentPreviewState() {
        cancelAllPendingAgentChanges();
        if (aiState.pendingAgentPatch?.previewApplied) {
            setActiveEditorContent(String(aiState.pendingAgentPatch.beforeText || ''), []);
        }
        aiState.pendingAgentPatch = null;
        clearEditorHighlights();
    }
    function snapshotAgentPatchesForUndo() {
        const out = {};
        Object.entries(aiState.pendingAgentPatches).forEach(([path, patch]) => {
            out[path] = { before: patch.before, after: patch.after };
        });
        return out;
    }
    async function revertLastAgentRun() {
        const snap = aiState.lastAgentUndo;
        if (!snap?.paths || !Object.keys(snap.paths).length) return;
        const root = getRootForFileContext() || '';
        for (const [path, patch] of Object.entries(snap.paths)) {
            const body = new URLSearchParams({
                action: 'ai_agent_tool',
                tool: 'write_file',
                args: JSON.stringify({ path, content: patch.before, persist: true }),
                root
            });
            const res = await fetch(AI_CONFIG.fileApiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) throw new Error(data.error || `Revert failed: ${path}`);
            const open = normalizeAgentPath(aiState.currentFileName);
            if (open && pathsMatchAgent(open, path)) {
                setActiveEditorContent(patch.before, []);
                await saveActiveEditorToServer();
            }
        }
        clearEditorHighlights();
        await refreshFileTreeAfterAgent(Object.keys(snap.paths));
        aiState.lastAgentUndo = null;
    }
    function attachAgentUndoFooter(messageDiv, messageId) {
        if (!messageDiv) return;
        messageDiv.querySelector('.ai-agent-undo-last-footer')?.remove();
        document.querySelectorAll('.ai-agent-undo-last-footer').forEach((el) => {
            if (el.closest('.message')?.id !== messageId) el.remove();
        });
        const host = messageDiv.querySelector('.message-content') || messageDiv;
        const bar = document.createElement('div');
        bar.className = 'ai-agent-undo-last-footer';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'action-button ai-agent-undo-last-btn';
        btn.textContent = tr('aiUndoLastAgent', 'Отменить изменения');
        btn.dataset.messageId = messageId;
        bar.appendChild(btn);
        host.appendChild(bar);
    }
    function attachAgentConfirmFooter(messageDiv, messageId) {
        if (!messageDiv || messageDiv.querySelector('.ai-agent-confirm-btn')) return;
        const footer = document.createElement('div');
        footer.className = 'message-footer';
        const actions = document.createElement('div');
        actions.className = 'message-actions';
        const confirmBtn = document.createElement('button');
        confirmBtn.className = 'action-button ai-agent-confirm-btn';
        confirmBtn.textContent = tr('ok', 'OK');
        const undoBtn = document.createElement('button');
        undoBtn.className = 'action-button ai-agent-undo-btn';
        undoBtn.textContent = tr('undo', 'Undo');
        actions.appendChild(confirmBtn);
        actions.appendChild(undoBtn);
        footer.appendChild(actions);
        messageDiv.appendChild(footer);
        messageDiv.dataset.agentPatchId = messageId;
        messageDiv.dataset.agentApplied = '0';
    }
    function renderAgentStagedSummary(container) {
        const paths = Object.keys(aiState.pendingAgentPatches);
        if (!paths.length) return;
        const note = document.createElement('div');
        note.className = 'ai-agent-staged-files';
        note.textContent = `Pending changes (${paths.length}): ${paths.join(', ')}`;
        container.appendChild(note);
        paths.forEach((path) => {
            const patch = aiState.pendingAgentPatches[path];
            container.appendChild(createAgentPatchPreviewElement(patch.before, patch.after));
        });
    }
    function buildAgentPreview(ops) {
        if (!Array.isArray(ops) || ops.length === 0) return '';
        return ops.map((op, idx) => `Change ${idx + 1}\nSEARCH:\n${op.search}\nREPLACE:\n${op.replace}`).join('\n\n');
    }

    function sliceDiffOpsWithContext(diffOps, contextLines = 2) {
        if (!Array.isArray(diffOps) || !diffOps.length) return diffOps;
        let min = diffOps.length;
        let max = -1;
        diffOps.forEach((d, i) => {
            if (d.t !== 'eq') {
                min = Math.min(min, i);
                max = Math.max(max, i);
            }
        });
        if (max < 0) return diffOps.slice(0, Math.min(diffOps.length, contextLines * 2 + 3));
        const start = Math.max(0, min - contextLines);
        const end = Math.min(diffOps.length, max + contextLines + 1);
        const out = [];
        if (start > 0) out.push({ t: 'skip', v: `… ${start} lines above …` });
        out.push(...diffOps.slice(start, end));
        if (end < diffOps.length) out.push({ t: 'skip', v: `… ${diffOps.length - end} lines below …` });
        return out;
    }
    function createAgentPatchPreviewElement(beforeText, afterText, ops = null) {
        const wrapper = document.createElement('div');
        wrapper.className = 'ai-agent-patch-preview';
        const title = document.createElement('div');
        title.className = 'ai-agent-patch-title';
        const titleLabel = document.createElement('span');
        titleLabel.className = 'ai-agent-patch-title-label';
        titleLabel.textContent = afterText != null ? tr('aiPatchPreview', 'Agent patch preview') : tr('aiPatch', 'Agent patch');
        title.appendChild(titleLabel);
        wrapper.appendChild(title);

        const body = document.createElement('div');
        body.className = 'ai-agent-patch-body';

        if (afterText != null) {
            sliceDiffOpsWithContext(buildLineDiff(beforeText, afterText), 2).forEach((d) => appendAgentPatchRow(body, d.t, d.v));
        } else {
            (ops || []).forEach((op, index) => {
                if (index > 0) {
                    const sep = document.createElement('div');
                    sep.className = 'ai-agent-patch-title';
                    sep.textContent = `Change ${index + 1}`;
                    body.appendChild(sep);
                }
                sliceDiffOpsWithContext(buildLineDiff(op.search || '', op.replace || ''), 2).forEach((d) => appendAgentPatchRow(body, d.t, d.v));
            });
        }

        wrapper.appendChild(body);
        setupAgentPatchCollapse(wrapper, title, body);
        return wrapper;
    }

    function setupAgentPatchCollapse(wrapper, title, body) {
        const PATCH_COLLAPSED_ROW_LIMIT = 10;
        const rows = Array.from(body.querySelectorAll('.ai-agent-patch-row'));
        if (rows.length <= PATCH_COLLAPSED_ROW_LIMIT) return;

        const hiddenRows = rows.slice(PATCH_COLLAPSED_ROW_LIMIT);
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'ai-agent-patch-toggle';
        const toggleIcon = document.createElement('span');
        toggleIcon.className = 'ai-agent-patch-toggle-icon';
        toggleIcon.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';
        const toggleLabel = document.createElement('span');
        toggleLabel.className = 'ai-agent-patch-toggle-label';
        toggle.appendChild(toggleIcon);
        toggle.appendChild(toggleLabel);

        function setCollapsed(collapsed) {
            wrapper.classList.toggle('collapsed', collapsed);
            hiddenRows.forEach((row) => row.classList.toggle('ai-agent-patch-row-hidden', collapsed));
            toggleLabel.textContent = collapsed
                ? `${tr('aiShowMore', 'Show more')} (${hiddenRows.length})`
                : tr('aiShowLess', 'Show less');
        }

        toggle.addEventListener('click', () => setCollapsed(!wrapper.classList.contains('collapsed')));
        title.appendChild(toggle);
        setCollapsed(true);
    }

    function appendAgentPatchRow(parent, type, text) {
        const row = document.createElement('div');
        const cls = type === 'rm' ? 'rm' : (type === 'add' ? 'add' : (type === 'skip' ? 'skip' : 'eq'));
        row.className = `ai-agent-patch-row ${cls}`;
        const sign = document.createElement('span');
        sign.className = 'ai-agent-patch-sign';
        sign.textContent = type === 'rm' ? '-' : (type === 'add' ? '+' : (type === 'skip' ? '…' : ' '));
        const code = document.createElement('span');
        code.textContent = text;
        row.appendChild(sign);
        row.appendChild(code);
        parent.appendChild(row);
    }

    function buildLineDiff(beforeText, afterText) {
        const a = String(beforeText || '').replace(/\r\n/g, '\n').split('\n');
        const b = String(afterText || '').replace(/\r\n/g, '\n').split('\n');
        const n = a.length;
        const m = b.length;
        const dp = Array.from({ length: n + 1 }, () => new Array(m + 1).fill(0));
        for (let i = n - 1; i >= 0; i--) {
            for (let j = m - 1; j >= 0; j--) {
                dp[i][j] = a[i] === b[j] ? dp[i + 1][j + 1] + 1 : Math.max(dp[i + 1][j], dp[i][j + 1]);
            }
        }
        let i = 0;
        let j = 0;
        const ops = [];
        while (i < n && j < m) {
            if (a[i] === b[j]) {
                ops.push({ t: 'eq', v: a[i] });
                i++;
                j++;
            } else if (dp[i + 1][j] >= dp[i][j + 1]) {
                ops.push({ t: 'rm', v: a[i++] });
            } else {
                ops.push({ t: 'add', v: b[j++] });
            }
        }
        while (i < n) ops.push({ t: 'rm', v: a[i++] });
        while (j < m) ops.push({ t: 'add', v: b[j++] });
        return ops;
    }

    function buildAgentDiffPreview(beforeText, afterText) {
        const diffOps = buildLineDiff(beforeText, afterText);
        const lines = ['```diff'];
        diffOps.forEach((d) => {
            if (d.t === 'eq') lines.push(` ${d.v}`);
            if (d.t === 'rm') lines.push(`-${d.v}`);
            if (d.t === 'add') lines.push(`+${d.v}`);
        });
        lines.push('```');
        return lines.join('\n');
    }

    function showAgentPreviewInEditor(beforeText, afterText) {
        const tabId = window.tabsState?.activeTabId;
        const after = String(afterText ?? '');
        const before = String(beforeText ?? '');
        clearEditorHighlights();
        const preview = buildEditorDiffPreview(before, after);
        const managerEditor = window.monacoManager && tabId && typeof window.monacoManager.getEditor === 'function'
            ? window.monacoManager.getEditor(tabId)
            : null;
        if (managerEditor && typeof window.monacoManager.setContent === 'function') {
            window.monacoManager.setContent(tabId, after);
            if (preview?.rows?.length) decorateEditorDiffPreview(managerEditor, window.monaco, preview.rows);
            aiState.previewHighlightTabId = tabId;
            return true;
        }
        const handle = getActiveEditorHandle();
        if (!handle) return false;
        if (handle.type === 'monaco' && handle.editor?.setValue) {
            handle.editor.setValue(after);
            if (preview?.rows?.length) decorateEditorDiffPreview(handle.editor, window.monaco, preview.rows);
            return true;
        }
        if (handle.type === 'iframeEditor' || handle.type === 'iframeModel') {
            void sendIframeMonacoAiAction('applyPreview', { newContent: after });
            void sendIframeMonacoAiAction('highlightPreview', { oldText: before, newText: after });
            return true;
        }
        return false;
    }

    function buildEditorDiffPreview(beforeText, afterText) {
        const diffOps = buildLineDiff(beforeText, afterText);
        const lines = [];
        const rows = [];
        diffOps.forEach((d) => {
            lines.push(d.v);
            rows.push({ type: d.t, lineNumber: lines.length });
        });
        return { text: lines.join('\n'), rows };
    }

    function decorateEditorDiffPreview(editor, monacoRef, rows) {
        if (!editor || !monacoRef?.Range || typeof editor.deltaDecorations !== 'function' || !Array.isArray(rows)) return;
        const model = editor.getModel?.();
        if (!model) return;
        const decorations = rows
            .filter((row) => row.type === 'rm' || row.type === 'add')
            .map((row) => ({
                range: new monacoRef.Range(
                    row.lineNumber,
                    1,
                    row.lineNumber,
                    Math.max(1, model.getLineMaxColumn(Math.min(row.lineNumber, model.getLineCount())))
                ),
                options: {
                    isWholeLine: true,
                    className: row.type === 'rm' ? 'ai-agent-line-remove' : 'ai-agent-inline-add',
                    linesDecorationsClassName: row.type === 'rm' ? 'ai-agent-line-remove' : 'ai-agent-inline-add'
                }
            }));
        const ids = editor.deltaDecorations([], decorations);
        aiState.editorHighlights.push({ editor, ids });
    }

    function applyOpsToText(sourceText, ops) {
        let nextText = String(sourceText || '').replace(/\r\n/g, '\n');
        const appliedRanges = [];
        for (const op of ops) {
            const searchRaw = String(op.search || '').replace(/\r\n/g, '\n');
            const replace = String(op.replace || '').replace(/\r\n/g, '\n');
            let start = nextText.indexOf(searchRaw);
            let search = searchRaw;
            if (start === -1) {
                search = searchRaw.trim();
                if (!search) return null;
                start = nextText.indexOf(search);
            }
            if (start === -1) return null;
            const end = start + search.length;
            nextText = nextText.slice(0, start) + replace + nextText.slice(end);
            appliedRanges.push({ start, end: start + replace.length });
        }
        return { text: nextText, ranges: appliedRanges };
    }

    function clearEditorHighlights() {
        if (window.monacoManager && aiState.previewHighlightTabId && typeof window.monacoManager.clearAiEditHighlight === 'function') {
            window.monacoManager.clearAiEditHighlight(aiState.previewHighlightTabId);
        }
        void sendIframeMonacoAiAction('clearHighlight');
        aiState.previewHighlightTabId = null;
        aiState.editorHighlights.forEach((entry) => {
            if (entry?.editor && entry.ids && typeof entry.editor.deltaDecorations === 'function') {
                try { entry.editor.deltaDecorations(entry.ids, []); } catch (e) {}
            }
        });
        aiState.editorHighlights = [];
    }

    function decorateMonacoRanges(editor, ranges) {
        if (!editor || typeof editor.deltaDecorations !== 'function' || !Array.isArray(ranges) || ranges.length === 0) return;
        const model = editor.getModel?.();
        if (!model || !window.monaco?.Range) return;
        const decorations = ranges.map((r) => {
            const s = model.getPositionAt(r.start);
            const e = model.getPositionAt(r.end);
            return { range: new window.monaco.Range(s.lineNumber, s.column, e.lineNumber, e.column), options: { inlineClassName: 'ai-agent-replaced' } };
        });
        const ids = editor.deltaDecorations([], decorations);
        aiState.editorHighlights.push({ editor, ids });
    }

    function getActiveEditorHandle() {
        const tabId = window.tabsState?.activeTabId;
        if (window.editorsMap?.has(tabId)) return { type: 'monaco', editor: window.editorsMap.get(tabId) };
        const viewer = document.getElementById('viewer');
        if (viewer?.monacoEditor) return { type: 'monaco', editor: viewer.monacoEditor };
        if (window.monacoManager && tabId && typeof window.monacoManager.getEditor === 'function' && window.monacoManager.getEditor(tabId)) {
            return { type: 'monacoManager', tabId };
        }
        if (tabId) {
            const iframe = Array.from(document.querySelectorAll('#viewer iframe[data-tab-id]')).find((n) => n.getAttribute('data-tab-id') === String(tabId));
            const model = iframe?.contentWindow?.monaco?.editor?.getModels?.()?.[0];
            const editor = iframe?.contentWindow?.monaco?.editor?.getEditors?.()?.[0];
            if (editor && typeof editor.setValue === 'function') return { type: 'iframeEditor', editor, monaco: iframe.contentWindow.monaco };
            if (model && typeof model.getValue === 'function' && typeof model.setValue === 'function') return { type: 'iframeModel', model };
        }
        return null;
    }
    function getActiveEditorIframe() {
        const tabId = window.tabsState?.activeTabId;
        if (!tabId) return null;
        return Array.from(document.querySelectorAll('#viewer iframe[data-tab-id]')).find((n) => n.getAttribute('data-tab-id') === String(tabId)) || null;
    }
    async function sendIframeMonacoAiAction(action, payload = {}, timeoutMs = 15000) {
        const iframe = getActiveEditorIframe();
        const target = iframe?.contentWindow;
        if (!target) return { ok: false, error: 'no iframe' };
        const reqId = `ai_${Date.now()}_${Math.random().toString(36).slice(2)}`;
        return await new Promise((resolve) => {
            let done = false;
            const timer = setTimeout(() => {
                if (done) return;
                done = true;
                window.removeEventListener('message', onMessage);
                resolve({ ok: false, error: 'timeout' });
            }, timeoutMs);
            function onMessage(ev) {
                const d = ev?.data;
                if (!d || d.source !== 'cloudIframeMonacoAi' || d.reqId !== reqId) return;
                if (ev.source !== target || done) return;
                done = true;
                clearTimeout(timer);
                window.removeEventListener('message', onMessage);
                resolve(d.ok ? { ok: true, result: d.result || {} } : { ok: false, error: d.error || 'failed' });
            }
            window.addEventListener('message', onMessage);
            target.postMessage({ source: 'cloudParent', type: 'cloudIframeMonacoAi', reqId, action, ...payload }, '*');
        });
    }
    function isOpenLatexEditorTab() {
        const activeTab = window.tabsState?.tabs?.find((tab) => tab.id === window.tabsState?.activeTabId);
        return !!activeTab?.latexEditor;
    }
    async function compileLatexInIframe(savePdf = false) {
        if (!isOpenLatexEditorTab()) return { success: false, error: 'LaTeX editor is not open' };
        await saveActiveEditorToServer();
        const res = await sendIframeMonacoAiAction('compileLatex', { savePdf: !!savePdf }, savePdf ? 120000 : 90000);
        if (!res.ok) return { success: false, error: res.error || 'Compile failed' };
        if (res.result?.success === false) {
            return { success: false, error: res.result?.error || 'Compile failed' };
        }
        const pdfPath = res.result?.pdf_path;
        if (savePdf && !pdfPath) {
            return { success: false, error: 'PDF was not created' };
        }
        if (savePdf && pdfPath) {
            try { await highlightNewFilesInTree([pdfPath], { openLastPdf: true }); } catch (e) {}
        }
        return { success: true, pdf_path: pdfPath, message: savePdf ? (pdfPath || 'PDF saved') : 'Compilation succeeded' };
    }
    async function fetchAgentCompletionStream(payload, signal, onContentDelta) {
        const res = await fetchAiCompletions({ ...payload, stream: true }, signal);
        if (!res.ok) {
            const errPayload = await res.json().catch(() => ({}));
            throw new Error(errPayload.error?.message || errPayload?.message || `HTTP ${res.status}`);
        }
        const ct = res.headers.get('content-type') || '';
        if (!ct.includes('text/event-stream') && !ct.includes('stream')) {
            return res.json();
        }
        const reader = res.body?.getReader?.();
        if (!reader) return res.json();
        const decoder = new TextDecoder();
        let buffer = '';
        let content = '';
        const toolCalls = {};
        let finishReason = null;
        let usage = null;
        let streamError = null;
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop() || '';
            for (const line of lines) {
                if (!line.startsWith('data: ')) continue;
                const data = line.slice(6).trim();
                if (data === '[DONE]') continue;
                try {
                    const parsed = JSON.parse(data);
                    if (parsed.error) {
                        streamError = new Error(parsed.error.message || String(parsed.error));
                        break;
                    }
                    if (parsed.usage) usage = parsed.usage;
                    const choice = parsed.choices?.[0];
                    if (choice?.finish_reason) finishReason = choice.finish_reason;
                    const delta = choice?.delta;
                    const deltaText = delta?.content || delta?.text || '';
                    if (deltaText) {
                        content += deltaText;
                        onContentDelta?.(content);
                    }
                    if (Array.isArray(delta?.tool_calls)) {
                        delta.tool_calls.forEach((tc) => {
                            const idx = tc.index ?? 0;
                            if (!toolCalls[idx]) {
                                toolCalls[idx] = {
                                    id: tc.id || ('call_' + idx + '_' + Date.now().toString(36).slice(2, 10)),
                                    type: 'function',
                                    function: { name: '', arguments: '' }
                                };
                            }
                            if (tc.id) toolCalls[idx].id = tc.id;
                            if (tc.function?.name) toolCalls[idx].function.name += tc.function.name;
                            if (tc.function?.arguments) toolCalls[idx].function.arguments += tc.function.arguments;
                        });
                    }
                } catch (e) { logAiError('Parse agent stream chunk', e); }
            }
            if (streamError) break;
        }
        if (streamError && !content) throw streamError;
        const trimmed = String(content || '').trim();
        const message = { role: 'assistant', content: trimmed || null };
        const tcArr = Object.keys(toolCalls).sort((a, b) => Number(a) - Number(b)).map((k) => toolCalls[k]).filter((tc) => tc.function?.name);
        tcArr.forEach((tc, i) => {
            if (!tc.id) tc.id = 'call_' + i + '_' + Date.now().toString(36).slice(2, 10);
        });
        if (tcArr.length) message.tool_calls = tcArr;
        return { choices: [{ message, finish_reason: finishReason }], usage };
    }

    function setActiveEditorContent(nextText, ranges) {
        const handle = getActiveEditorHandle();
        if (!handle) return false;
        clearEditorHighlights();
        if (handle.type === 'monaco' && handle.editor?.setValue) {
            handle.editor.setValue(nextText);
            decorateMonacoRanges(handle.editor, ranges);
            return true;
        }
        if (handle.type === 'iframeModel') {
            handle.model.setValue(nextText);
            return true;
        }
        if (handle.type === 'iframeEditor') {
            handle.editor.setValue(nextText);
            if (handle.monaco?.Range && typeof handle.editor.deltaDecorations === 'function' && Array.isArray(ranges) && ranges.length) {
                const model = handle.editor.getModel?.();
                if (model) {
                    const decorations = ranges.map((r) => {
                        const s = model.getPositionAt(r.start);
                        const e = model.getPositionAt(r.end);
                        return { range: new handle.monaco.Range(s.lineNumber, s.column, e.lineNumber, e.column), options: { inlineClassName: 'ai-agent-replaced' } };
                    });
                    const ids = handle.editor.deltaDecorations([], decorations);
                    aiState.editorHighlights.push({ editor: handle.editor, ids });
                }
            }
            return true;
        }
        if (handle.type === 'monacoManager') {
            if (
                typeof window.monacoManager.setContent === 'function' &&
                typeof window.monacoManager.getEditor === 'function' &&
                window.monacoManager.getEditor(handle.tabId)
            ) {
                window.monacoManager.setContent(handle.tabId, nextText);
                return true;
            }
        }
        return false;
    }

    async function fetchFileContentFromApi(path, root = null) {
        if (!path) return null;
        const rootKey = root || getRootForFileContext();
        const ext = String(path).split('.').pop().toLowerCase();
        try {
            if (AI_EXTRACTABLE_EXT.has(ext)) {
                const extractUrl = buildApiUrl('ai_extract_text', path, { root: rootKey });
                const exRes = await fetch(extractUrl, { method: 'GET', credentials: 'same-origin' });
                const exData = exRes.ok ? await exRes.json().catch(() => null) : null;
                if (exData?.ok && typeof exData.content === 'string' && exData.content.trim()) {
                    return { name: path, content: exData.content, extracted: true };
                }
                if (['ppt', 'pptx'].includes(ext)) {
                    const scraped = scrapeTextFromPresentationViewer();
                    if (scraped) {
                        return { name: path, content: scraped, extracted: true, scrapedFromViewer: true };
                    }
                }
                if (exData?.error) {
                    console.warn('ai_extract_text:', path, exData.error);
                }
            }
            const response = await fetch(buildApiUrl('open', path, { root: rootKey }), { method: 'GET', credentials: 'same-origin' });
            if (!response.ok) return null;
            const data = await response.json();
            if (data?.previewType === 'pdf' || data?.previewType === 'presentation' || data?.previewType === 'excel') {
                const extractUrl = buildApiUrl('ai_extract_text', path, { root: rootKey });
                const exRes = await fetch(extractUrl, { method: 'GET', credentials: 'same-origin' });
                const exData = exRes.ok ? await exRes.json() : null;
                if (exData?.ok && typeof exData.content === 'string' && exData.content.trim()) {
                    return { name: path, content: exData.content, extracted: true };
                }
                if (['ppt', 'pptx'].includes(ext)) {
                    const scraped = scrapeTextFromPresentationViewer();
                    if (scraped) {
                        return { name: path, content: scraped, extracted: true, scrapedFromViewer: true };
                    }
                }
                return null;
            }
            if (data && !data.binary && typeof data.content === 'string') {
                return { name: path, content: data.content };
            }
            return null;
        } catch (err) {
            console.warn('Failed to fetch file content for AI context:', path, err);
            return null;
        }
    }

    function getDirectoryPath(filePath) {
        if (!filePath) return '';
        const slashIndex = filePath.lastIndexOf('/');
        return slashIndex === -1 ? '' : filePath.substring(0, slashIndex);
    }

    function extractMentionedFileNames(message) {
        if (!message) return [];
        const mentions = [];
        const seen = new Set();
        const mentionRegex = /(^|\s)@([^\s@]+)/g;
        const knownExts = '(?:md|markdown|tex|latex|txt|json|csv|xml|html?|css|js|mjs|cjs|ts|tsx|jsx|vue|php|py|rb|go|java|cs|c|cc|cpp|h|hpp|sh|ya?ml|sql|log|ini|toml|cfg|env|rst|pdf|docx?|xlsx?|pptx?)';
        let match;

        while ((match = mentionRegex.exec(message)) !== null) {
            let token = (match[2] || '').trim();
            token = token.replace(/^[("'`\[]+/, '').replace(/[)"'`\].,;:!?]+$/, '');
            const extMatch = token.match(new RegExp(`^(.+?\\.${knownExts})(?=[^/._-].*)`, 'i'));
            if (extMatch) token = extMatch[1];
            if (!token) continue;

            // Disallow traversal. Drag-and-drop may provide a safe full tree path with slashes.
            if (token.includes('\\') || token.includes('..')) continue;

            if (!seen.has(token)) {
                seen.add(token);
                mentions.push(token);
            }
        }

        return mentions;
    }

    async function loadMentionedFilesContext(message, currentFilePath, selectedFiles = null, selectedRoots = null) {
        const seenMentions = new Set();
        const mentions = [];
        const addMention = (name, root = null) => {
            const normalized = normalizeMentionedFilePath(name);
            if (!normalized || normalized.includes('..') || seenMentions.has(normalized)) return;
            seenMentions.add(normalized);
            mentions.push({ path: normalized, root: String(root || '').trim() });
        };
        const selected = Array.isArray(selectedFiles) ? selectedFiles : (aiState.selectedMentionFiles || []);
        const roots = selectedRoots && typeof selectedRoots === 'object' ? selectedRoots : aiState.mentionedFileRoots;
        selected.forEach((name) => addMention(name, roots?.[normalizeMentionedFilePath(name)] || ''));
        if (selected.length === 0) extractMentionedFileNames(message).forEach((name) => addMention(name));
        mentions.splice(CONTEXT_LIMITS.maxMentionedFiles);
        if (mentions.length === 0) {
            return { files: [], skipped: [] };
        }

        const contextFilePath = getActiveFilePath() || currentFilePath || '';
        const currentDir = contextFilePath ? getDirectoryPath(contextFilePath) : '';
        const files = [];
        const skipped = [];
        let usedChars = 0;
        const root = getRootForFileContext();

        const resolvedPaths = [];
        for (const mention of mentions) {
            const fileName = mention.path;
            const candidatePath = normalizeMentionedFilePath(
                fileName.includes('/') ? fileName : (currentDir ? `${currentDir}/${fileName}` : fileName)
            );
            const fileRoot = mention.root || root;
            const fileData = await fetchFileContentFromApi(candidatePath, fileRoot);
            const resolvedName = normalizeMentionedFilePath(fileData?.name || candidatePath);
            resolvedPaths.push({ path: resolvedName, root: fileRoot });
            if (fileRoot) {
                if (!aiState.mentionedFileRoots) aiState.mentionedFileRoots = {};
                aiState.mentionedFileRoots[resolvedName] = fileRoot;
                if (resolvedName !== fileName) aiState.mentionedFileRoots[fileName] = fileRoot;
            }

            if (!fileData || typeof fileData.content !== 'string') {
                skipped.push({ name: fileName, reason: 'not_found_or_binary' });
                continue;
            }

            const nextSize = usedChars + fileData.content.length;
            if (nextSize > CONTEXT_LIMITS.maxMentionedChars) {
                skipped.push({ name: fileName, reason: 'size_limit' });
                continue;
            }

            usedChars = nextSize;
            files.push({ ...fileData, name: resolvedName });
        }

        return { files, skipped, resolvedPaths };
    }

    function toggleAIModal() {
        if (!elements.modal) return;
        
        const isHidden = elements.modal.classList.contains('hidden');
        
        if (isHidden) {
            openAIModal();
        } else {
            closeAIModal();
        }
    }

    function openAIModal() {
        if (!elements.modal) return;
        if (aiModalCloseTimer) {
            clearTimeout(aiModalCloseTimer);
            aiModalCloseTimer = null;
        }
        applySavedModalWidth();
        setMobilePageScrollLocked(true);
        scheduleModalViewportHeightSync();
        elements.modal.classList.remove('hidden');
        elements.modal.classList.remove('ai-open');
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                if (!elements.modal || elements.modal.classList.contains('hidden')) return;
                elements.modal.classList.add('ai-open');
            });
        });

        ensureUserDisplayName();
        
        loadCurrentFileContent().then(() => {
            updateQuickActions();
            updateAgentModeAvailability();
        });
        
        setTimeout(() => {
            if (elements.input) elements.input.focus();
            setupStickyCodeHeaders();
        }, 100);
        
        initDotField(elements.chat);

        if (elements.chat && !elements.chat.querySelector('.message')) {
            resetChatPagination();
            loadAiChatSessions().then(() => loadChatHistoryPage(false)).then(() => resumeServerAiRunIfNeeded());
        } else {
            scrollChatToBottom();
            resumeServerAiRunIfNeeded();
        }
        scheduleChatScrollbarRebuild();
    }

    function closeAIModal() {
        if (!elements.modal) return;

        // Prevent closing AI modal when OCR editing modal is open
        const ocrModal = document.getElementById('ai-ocr-edit-modal');
        if (ocrModal && !ocrModal.classList.contains('hidden')) {
            return;
        }

        elements.modal.classList.remove('ai-open');
        elements.settingsModal?.classList.add('hidden');
        stopModalResize();
        setMobilePageScrollLocked(false);
        resetMobileAssistantLayout();
        if (aiModalCloseTimer) clearTimeout(aiModalCloseTimer);
        aiModalCloseTimer = setTimeout(() => {
            elements.modal.classList.add('hidden');
            aiModalCloseTimer = null;
        }, 280);
    }
    function getModalContent() {
        return elements.modal?.querySelector('.ai-modal-content') || null;
    }
    function shouldSyncModalKeyboardViewport() {
        return window.matchMedia('(max-width: 768px)').matches;
    }
    function setMobilePageScrollLocked(locked) {
        if (!shouldSyncModalKeyboardViewport()) return;
        const value = locked ? 'hidden' : '';
        document.documentElement.style.overflow = value;
        document.body.style.overflow = value;
        if (locked) window.scrollTo(0, 0);
    }
    const MOBILE_INPUT_KEYBOARD_LIFT_RATIO = 0.06;
    function getMobileKeyboardInset() {
        const vv = window.visualViewport;
        if (!vv) return 0;
        const layoutHeight = window.innerHeight || document.documentElement.clientHeight || 0;
        return Math.max(0, Math.round(layoutHeight - vv.height - vv.offsetTop));
    }
    function isMobileKeyboardOpen() {
        return getMobileKeyboardInset() > 50;
    }
    function syncMobileInputKeyboardLift() {
        const aiInput = elements.input?.closest('.ai-input');
        if (!aiInput || !shouldSyncModalKeyboardViewport()) return;
        aiInput.style.removeProperty('transform');
    }
    function getVisualViewportRect() {
        const vv = window.visualViewport;
        const fallbackWidth = document.documentElement.clientWidth || window.innerWidth || 1;
        const fallbackHeight = document.documentElement.clientHeight || window.innerHeight || 1;
        if (!vv) {
            return { top: 0, left: 0, width: fallbackWidth, height: fallbackHeight };
        }
        return {
            top: Math.max(0, vv.offsetTop),
            left: Math.max(0, vv.offsetLeft),
            width: Math.max(1, Math.round(vv.width)),
            height: Math.max(1, Math.round(vv.height))
        };
    }
    function applyViewportBox(el, rect) {
        if (!el) return;
        el.style.setProperty('position', 'fixed', 'important');
        el.style.setProperty('top', `${rect.top}px`, 'important');
        el.style.setProperty('left', `${rect.left}px`, 'important');
        el.style.setProperty('width', `${rect.width}px`, 'important');
        el.style.setProperty('height', `${rect.height}px`, 'important');
        el.style.setProperty('min-height', `${rect.height}px`, 'important');
        el.style.setProperty('max-height', `${rect.height}px`, 'important');
        el.style.setProperty('bottom', 'auto', 'important');
        el.style.setProperty('right', 'auto', 'important');
    }
    function clearMobileComposerOverrides() {
        const aiInput = elements.input?.closest('.ai-input');
        const modalBody = elements.modal?.querySelector('.ai-modal-body');
        ['position', 'left', 'right', 'width', 'bottom', 'z-index', 'box-sizing', 'transform'].forEach((prop) => aiInput?.style.removeProperty(prop));
        elements.chat?.style.removeProperty('padding-bottom');
        modalBody?.style.removeProperty('padding-bottom');
    }
    function resetMobileAssistantLayout() {
        const modalContent = getModalContent();
        const props = ['position', 'left', 'right', 'width', 'bottom', 'top', 'height', 'min-height', 'max-height', 'max-width', 'min-width', 'transform', 'border-radius'];
        props.forEach((prop) => {
            modalContent?.style.removeProperty(prop);
            elements.modal?.style.removeProperty(prop);
        });
        clearMobileComposerOverrides();
    }
    function enforceModalViewportHeight() {
        if (!elements.modal) return;
        const mobile = shouldSyncModalKeyboardViewport();
        const modalContent = getModalContent();
        const chatScroll = elements.chat?.scrollTop ?? 0;
        if (mobile) {
            const rect = getVisualViewportRect();
            applyViewportBox(elements.modal, rect);
            if (modalContent) {
                applyViewportBox(modalContent, rect);
                modalContent.style.setProperty('max-width', '100%', 'important');
                modalContent.style.setProperty('min-width', '0', 'important');
                modalContent.style.setProperty('transform', 'none', 'important');
                modalContent.style.setProperty('border-radius', '0', 'important');
            }
            clearMobileComposerOverrides();
            syncMobileInputKeyboardLift();
            if (elements.chat) {
                requestAnimationFrame(() => {
                    elements.chat.scrollTop = chatScroll;
                });
            }
            return;
        }
        const viewportHeight = Math.max(1, Math.round(window.visualViewport?.height || window.innerHeight || document.documentElement.clientHeight || 0));
        elements.modal.style.setProperty('position', 'fixed', 'important');
        elements.modal.style.setProperty('top', '0', 'important');
        elements.modal.style.setProperty('bottom', '0', 'important');
        elements.modal.style.removeProperty('left');
        elements.modal.style.removeProperty('right');
        elements.modal.style.removeProperty('width');
        elements.modal.style.setProperty('height', `${viewportHeight}px`, 'important');
        elements.modal.style.setProperty('min-height', `${viewportHeight}px`, 'important');
        elements.modal.style.setProperty('max-height', `${viewportHeight}px`, 'important');
        if (!modalContent) return;
        modalContent.style.removeProperty('top');
        modalContent.style.removeProperty('bottom');
        modalContent.style.removeProperty('left');
        modalContent.style.removeProperty('right');
        modalContent.style.removeProperty('transform');
        modalContent.style.removeProperty('border-radius');
        modalContent.style.removeProperty('max-width');
        modalContent.style.removeProperty('min-width');
        modalContent.style.setProperty('height', `${viewportHeight}px`, 'important');
        modalContent.style.setProperty('min-height', `${viewportHeight}px`, 'important');
        modalContent.style.setProperty('max-height', `${viewportHeight}px`, 'important');
        clearMobileComposerOverrides();
    }
    function scheduleModalViewportHeightSync() {
        enforceModalViewportHeight();
        requestAnimationFrame(enforceModalViewportHeight);
        setTimeout(enforceModalViewportHeight, 120);
        setTimeout(enforceModalViewportHeight, 320);
        setTimeout(enforceModalViewportHeight, 520);
    }
    function ensureModalResizeHandle(modalContent) {
        if (!modalContent || modalContent.querySelector('.ai-resize-handle')) return;
        const handle = document.createElement('div');
        handle.className = 'ai-resize-handle';
        handle.setAttribute('role', 'separator');
        handle.setAttribute('aria-label', 'Resize assistant');
        modalContent.appendChild(handle);
    }
    function getModalWidthBounds() {
        const maxByViewport = Math.floor(window.innerWidth * 0.9);
        return { min: AI_MODAL_WIDTH.minPx, max: Math.max(AI_MODAL_WIDTH.minPx, Math.min(AI_MODAL_WIDTH.maxPx, maxByViewport)) };
    }
    function clampModalWidth(width) {
        const bounds = getModalWidthBounds();
        return Math.max(bounds.min, Math.min(bounds.max, Math.round(width)));
    }
    function applyModalWidth(widthPx) {
        const modalContent = getModalContent();
        if (!modalContent) return;
        if (shouldSyncModalKeyboardViewport()) {
            modalContent.style.setProperty('width', '100%', 'important');
            modalContent.style.setProperty('max-width', '100%', 'important');
            modalContent.style.setProperty('min-width', '0', 'important');
            return;
        }
        const width = clampModalWidth(widthPx);
        modalContent.style.setProperty('width', `${width}px`, 'important');
    }
    function getDefaultModalWidth() {
        return clampModalWidth(window.innerWidth * (AI_MODAL_WIDTH.default / 100));
    }
    function applySavedModalWidth() {
        if (shouldSyncModalKeyboardViewport()) {
            applyModalWidth(window.innerWidth);
            return;
        }
        const raw = Number(localStorage.getItem(AI_MODAL_WIDTH.storageKey));
        applyModalWidth(Number.isFinite(raw) && raw > 0 ? raw : getDefaultModalWidth());
    }
    function saveModalWidth(widthPx) {
        localStorage.setItem(AI_MODAL_WIDTH.storageKey, String(clampModalWidth(widthPx)));
    }
    function handleModalResizeMove(ev) {
        if (!aiModalResizeState.active) return;
        const delta = aiModalResizeState.startX - ev.clientX;
        const width = clampModalWidth(aiModalResizeState.startWidth + delta);
        applyModalWidth(width);
    }
    function stopModalResize() {
        if (!aiModalResizeState.active) return;
        aiModalResizeState.active = false;
        document.body.style.userSelect = '';
        const modalContent = getModalContent();
        if (modalContent) saveModalWidth(modalContent.getBoundingClientRect().width);
    }
    function startModalResize(ev) {
        const modalContent = getModalContent();
        if (!modalContent || ev.button !== 0) return;
        ev.preventDefault();
        aiModalResizeState.active = true;
        aiModalResizeState.startX = ev.clientX;
        aiModalResizeState.startWidth = modalContent.getBoundingClientRect().width;
        document.body.style.userSelect = 'none';
        const dragStart = window.cloudPointerDragStart;
        if (typeof dragStart === 'function') dragStart('ew-resize', handleModalResizeMove, stopModalResize);
    }
    function setupModalResize() {
        const modalContent = getModalContent();
        if (!modalContent || modalContent.dataset.resizeBound === '1') return;
        ensureModalResizeHandle(modalContent);
        const handle = modalContent.querySelector('.ai-resize-handle');
        if (!handle) return;
        handle.addEventListener('mousedown', startModalResize);
        modalContent.dataset.resizeBound = '1';
        window.addEventListener('resize', () => {
            scheduleModalViewportHeightSync();
            if (!shouldSyncModalKeyboardViewport()) {
                const current = modalContent.getBoundingClientRect().width;
                applyModalWidth(current || getDefaultModalWidth());
            }
        });
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', scheduleModalViewportHeightSync);
            window.visualViewport.addEventListener('scroll', scheduleModalViewportHeightSync);
        }
        if (elements.input && elements.input.dataset.keyboardViewportBound !== '1') {
            elements.input.addEventListener('focus', () => {
                if (!shouldSyncModalKeyboardViewport()) return;
                window.scrollTo(0, 0);
                scheduleModalViewportHeightSync();
            });
            elements.input.addEventListener('blur', () => {
                scheduleModalViewportHeightSync();
                setTimeout(syncMobileInputKeyboardLift, 80);
                setTimeout(syncMobileInputKeyboardLift, 280);
            });
            elements.input.dataset.keyboardViewportBound = '1';
        }
        const virtualKeyboard = navigator.virtualKeyboard;
        if (virtualKeyboard && modalContent.dataset.virtualKeyboardBound !== '1') {
            try { virtualKeyboard.overlaysContent = true; } catch (_) {}
            virtualKeyboard.addEventListener('geometrychange', scheduleModalViewportHeightSync);
            modalContent.dataset.virtualKeyboardBound = '1';
        }
        window.addEventListener('orientationchange', scheduleModalViewportHeightSync);
    }

    function scrollChatToBottom() {
        if (elements.chat) {
            elements.chat.scrollTop = elements.chat.scrollHeight;
        }
    }

    const chatScrollbarState = { bar: null, markers: [], mutObserver: null, resizeObserver: null };
    let chatScrollbarRebuildTimer = null;

    function initChatScrollbar() {
        const body = elements.modal?.querySelector('.ai-modal-body');
        const chat = elements.chat;
        if (!body || !chat || chatScrollbarState.bar) return;

        const bar = document.createElement('div');
        bar.className = 'ai-chat-scrollbar';
        body.appendChild(bar);
        chatScrollbarState.bar = bar;

        if (typeof ResizeObserver === 'function') {
            chatScrollbarState.resizeObserver = new ResizeObserver(() => scheduleChatScrollbarRebuild());
            chatScrollbarState.resizeObserver.observe(body);
            chatScrollbarState.resizeObserver.observe(chat);
        }
        window.addEventListener('resize', scheduleChatScrollbarRebuild);
        chat.addEventListener('scroll', updateActiveChatScrollMarker, { passive: true });
        chatScrollbarState.mutObserver = new MutationObserver(scheduleChatScrollbarRebuild);
        chatScrollbarState.mutObserver.observe(chat, { childList: true, subtree: true, characterData: true });

        rebuildChatScrollbar();
    }

    function scheduleChatScrollbarRebuild() {
        if (chatScrollbarRebuildTimer) return;
        chatScrollbarRebuildTimer = setTimeout(() => {
            chatScrollbarRebuildTimer = null;
            rebuildChatScrollbar();
        }, 200);
    }

    function rebuildChatScrollbar() {
        const chat = elements.chat;
        const bar = chatScrollbarState.bar;
        if (!chat || !bar) return;
        positionChatScrollbar();
        const hasOverflow = chat.scrollHeight > chat.clientHeight + 4;
        bar.style.display = hasOverflow ? '' : 'none';
        bar.replaceChildren();
        chatScrollbarState.markers = [];
        if (!hasOverflow) return;

        const chatRect = chat.getBoundingClientRect();
        chat.querySelectorAll('.message.user-message').forEach((msg) => {
            const rect = msg.getBoundingClientRect();
            const contentTop = (rect.top - chatRect.top) + chat.scrollTop + rect.height / 2;
            const pct = chat.scrollHeight > 0 ? contentTop / chat.scrollHeight : 0;
            const marker = document.createElement('div');
            marker.className = 'ai-chat-scroll-marker';
            marker.style.top = `${Math.max(0, Math.min(100, pct * 100))}%`;
            const preview = String(msg.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 120);
            marker.title = preview || tr('aiUserMessage', 'User message');
            marker.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                scrollChatToMessage(msg);
            });
            bar.appendChild(marker);
            chatScrollbarState.markers.push({ marker, msg });
        });
        updateActiveChatScrollMarker();
    }

    function positionChatScrollbar() {
        const chat = elements.chat;
        const bar = chatScrollbarState.bar;
        if (!chat || !bar) return;
        const body = elements.modal?.querySelector('.ai-modal-body');
        if (!body) return;
        const bodyRect = body.getBoundingClientRect();
        const chatRect = chat.getBoundingClientRect();
        const scrollbarWidth = Math.max(0, chat.offsetWidth - chat.clientWidth);
        bar.style.top = `${chatRect.top - bodyRect.top}px`;
        bar.style.height = `${chat.clientHeight}px`;
        bar.style.right = `${scrollbarWidth > 0 ? scrollbarWidth + 4 : 2}px`;
    }

    function scrollChatToMessage(msg) {
        const chat = elements.chat;
        if (!chat || !msg) return;
        const chatRect = chat.getBoundingClientRect();
        const rect = msg.getBoundingClientRect();
        const contentTop = (rect.top - chatRect.top) + chat.scrollTop;
        chat.scrollTop = Math.max(0, contentTop - 12);
    }

    function updateActiveChatScrollMarker() {
        const chat = elements.chat;
        const markers = chatScrollbarState.markers || [];
        if (!chat || markers.length === 0) return;
        const chatRect = chat.getBoundingClientRect();
        const threshold = chat.scrollTop + chatRect.height * 0.35;
        let activeIdx = -1;
        for (let i = 0; i < markers.length; i++) {
            const rect = markers[i].msg.getBoundingClientRect();
            const contentTop = (rect.top - chatRect.top) + chat.scrollTop;
            if (contentTop <= threshold) activeIdx = i;
            else break;
        }
        markers.forEach((m, i) => m.marker.classList.toggle('active', i === activeIdx));
    }

    // JS-based sticky code headers — works regardless of parent overflow settings
    function setupStickyCodeHeaders() {
        if (!elements.chat || elements.chat.dataset.stickyCodeHeadersBound === '1') return;

        const updateStickyCodeHeaders = () => {
            const chatRect = elements.chat.getBoundingClientRect();

            elements.chat.querySelectorAll('.code-header').forEach((header) => {
                const codeBlock = header.closest('.code-block');
                if (!codeBlock) return;

                const headerRect = header.getBoundingClientRect();
                const blockRect = codeBlock.getBoundingClientRect();
                const isSticky =
                    headerRect.top <= chatRect.top + 1 &&
                    blockRect.bottom > chatRect.top + header.offsetHeight;

                header.classList.toggle('is-sticky', isSticky);
            });
        };

        elements.chat.addEventListener('scroll', updateStickyCodeHeaders, { passive: true });
        window.addEventListener('resize', updateStickyCodeHeaders);
        elements.chat.dataset.stickyCodeHeadersBound = '1';
        updateStickyCodeHeaders();
    }

    function resetChatPagination() {
        aiState.historyOffset = 0;
        aiState.historyHasMore = true;
        aiState.isLoadingMore = false;
        aiState.chatHistory = [];
        aiState.processedMessageIds.clear();
        clearChatMessages();
    }

    function clearChatMessages() {
        if (!elements.chat) return;
        elements.chat.replaceChildren();
        const host = elements.chat.parentElement;
        if (!host?.querySelector(':scope > .dot-field-canvas')) delete elements.chat.dataset.dotField;
        initDotField(elements.chat);
    }

    function bindChatSessionControls() {
        const menu = document.getElementById('ai-chat-menu');
        const wrap = elements.chatSelect?.closest('.ai-chat-session-controls');
        if (elements.chatSelect && menu && elements.chatSelect.dataset.aiChatSelectBound !== '1') {
            elements.chatSelect.dataset.aiChatSelectBound = '1';
            elements.chatSelect.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                menu.classList.toggle('hidden');
                if (!menu.classList.contains('hidden')) {
                    const pr = menu.parentElement.getBoundingClientRect();
                    menu.style.left = '0';
                    menu.style.right = 'auto';
                    const mr = menu.getBoundingClientRect();
                    const excess = mr.right - window.innerWidth + 10;
                    if (excess > 0) {
                        menu.style.left = Math.max(8 - pr.left, -excess) + 'px';
                    }
                }
            });
        }
        if (wrap && wrap.dataset.aiChatOutsideBound !== '1') {
            wrap.dataset.aiChatOutsideBound = '1';
            document.addEventListener('click', (e) => {
                if (!wrap.contains(e.target)) menu?.classList.add('hidden');
            });
        }
        if (elements.newChatBtn && elements.newChatBtn.dataset.aiNewChatBound !== '1') {
            elements.newChatBtn.dataset.aiNewChatBound = '1';
            elements.newChatBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                createAiChat();
            });
        }
    }

    function ensureChatSessionControls() {
        if (!elements.modal) return;
        if (elements.chatSelect) {
            bindChatSessionControls();
            return;
        }
        const host = elements.modal.querySelector('.modal-header') || elements.modal.querySelector('.ai-controls') || elements.modal.querySelector('.ai-modal-content');
        if (!host) return;
        const wrap = document.createElement('div');
        wrap.className = 'ai-chat-session-controls';
        const dropdown = document.createElement('div');
        dropdown.className = 'ai-chat-dropdown';
        const current = document.createElement('button');
        current.id = 'ai-chat-select';
        current.type = 'button';
        current.className = 'ai-chat-current';
        current.innerHTML = '<span class="ai-chat-current-title">New chat</span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>';
        const menu = document.createElement('div');
        menu.id = 'ai-chat-menu';
        menu.className = 'ai-chat-menu hidden';
        dropdown.append(current, menu);
        const newBtn = document.createElement('button');
        newBtn.id = 'btn-ai-new-chat';
        newBtn.type = 'button';
        newBtn.className = 'icon-btn ai-chat-icon-btn';
        newBtn.title = tr('aiNewChat', 'New chat');
        newBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>';
        wrap.append(dropdown, newBtn);
        const actions = host.querySelector('.modal-actions');
        if (actions) host.insertBefore(wrap, actions);
        else host.insertBefore(wrap, host.firstChild);
        elements.chatSelect = current;
        elements.newChatBtn = newBtn;
        bindChatSessionControls();
    }

    function renderAiChatSessions() {
        if (!elements.chatSelect) return;
        const titleEl = elements.chatSelect.querySelector('.ai-chat-current-title');
        const menu = document.getElementById('ai-chat-menu');
        if (!menu) return;
        menu.replaceChildren();
        const active = aiState.chatSessions.find((chat) => String(chat.id) === String(aiState.activeChatId)) || aiState.chatSessions[0] || null;
        if (titleEl) titleEl.textContent = active?.title || 'New chat';
        aiState.chatSessions.forEach((chat) => {
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'ai-chat-menu-item' + (String(chat.id) === String(aiState.activeChatId) ? ' active' : '');
            row.dataset.chatId = String(chat.id || '');
            const date = chat.updated_at ? new Date(Number(chat.updated_at) * 1000).toLocaleDateString() : '';
            row.innerHTML = `<span class="ai-chat-menu-title"></span><span class="ai-chat-menu-date"></span><span class="ai-chat-menu-delete" title="${escapeHtml(tr('aiDeleteChat', 'Delete chat'))}"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#d92d20" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg></span>`;
            row.querySelector('.ai-chat-menu-title').textContent = chat.title || 'New chat';
            row.querySelector('.ai-chat-menu-date').textContent = date;
            row.addEventListener('click', (e) => {
                if (e.target.closest('.ai-chat-menu-delete')) {
                    e.stopPropagation();
                    deleteAiChat(chat.id);
                    return;
                }
                menu.classList.add('hidden');
                switchAiChat(chat.id);
            });
            menu.appendChild(row);
        });
    }

    async function loadAiChatSessions() {
        try {
            const res = await fetch(AI_CONFIG.apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'ai_chat_sessions' })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'Failed to load chats');
            const activeBeforeLoad = aiState.activeChatId;
            aiState.chatSessions = Array.isArray(data.items) ? data.items : [];
            if (activeBeforeLoad && String(activeBeforeLoad).startsWith('tmp_') && !aiState.chatSessions.some((c) => String(c.id) === String(activeBeforeLoad))) {
                aiState.chatSessions.unshift({ id: activeBeforeLoad, title: 'New chat', updated_at: Math.floor(Date.now() / 1000) });
            }
            if (!aiState.activeChatId || !aiState.chatSessions.some((c) => String(c.id) === String(aiState.activeChatId))) {
                aiState.activeChatId = String(aiState.chatSessions[0]?.id || '');
                localStorage.setItem('cloud_ai_active_chat_id', aiState.activeChatId);
            }
            renderAiChatSessions();
        } catch (e) { logAiError('Load AI chat sessions', e); }
    }

    async function switchAiChat(chatId) {
        if (!chatId || chatId === aiState.activeChatId) return;
        aiState.activeChatId = chatId;
        localStorage.setItem('cloud_ai_active_chat_id', chatId);
        resetChatPagination();
        await loadChatHistoryPage(false);
    }

    async function createAiChat() {
        const previousActiveChatId = aiState.activeChatId;
        const tempChatId = 'tmp_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 7);
        aiState.activeChatId = tempChatId;
        localStorage.setItem('cloud_ai_active_chat_id', tempChatId);
        resetChatPagination();
        aiState.selectedMentionFiles = [];
        aiState.mentionedFileRoots = {};
        aiState.mentionPlacements = [];
        removeAllExternalAttachments();
        if (elements.input) elements.input.value = '';
        renderMentionedAttachments();
        aiState.chatSessions = aiState.chatSessions.filter((chat) => String(chat.id) !== tempChatId);
        aiState.chatSessions.unshift({ id: tempChatId, title: 'New chat', updated_at: Math.floor(Date.now() / 1000) });
        renderAiChatSessions();
        let data = {};
        try {
            const res = await fetch(AI_CONFIG.apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'ai_chat_create' })
            });
            data = await res.json().catch(() => ({}));
        } catch (e) { logAiError('Create AI chat', e); }
        if (!data.success) {
            aiState.chatSessions = aiState.chatSessions.filter((chat) => String(chat.id) !== tempChatId);
            aiState.activeChatId = previousActiveChatId;
            localStorage.setItem('cloud_ai_active_chat_id', aiState.activeChatId);
            renderAiChatSessions();
            flashStatus(tr('aiChatCreateFailed', 'Could not create chat'));
            return;
        }
        aiState.activeChatId = String(data.chat_id || '');
        localStorage.setItem('cloud_ai_active_chat_id', aiState.activeChatId);
        const chatId = aiState.activeChatId;
        const tempChat = aiState.chatSessions.find((chat) => String(chat.id) === tempChatId);
        if (tempChat && chatId) {
            tempChat.id = chatId;
            tempChat.updated_at = Math.floor(Date.now() / 1000);
        } else if (chatId && !aiState.chatSessions.some((chat) => String(chat.id) === chatId)) {
            aiState.chatSessions.unshift({ id: chatId, title: 'New chat', updated_at: Math.floor(Date.now() / 1000) });
        }
        renderAiChatSessions();
        renderAiChatSessions();
    }

    async function deleteAiChat(chatId) {
        if (!chatId) return;
        const deletedActive = String(chatId) === String(aiState.activeChatId);
        const res = await fetch(AI_CONFIG.apiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'ai_chat_delete', chat_id: chatId })
        });
        const data = await res.json().catch(() => ({}));
        aiState.chatSessions = Array.isArray(data.items)
            ? data.items
            : aiState.chatSessions.filter((chat) => String(chat.id) !== String(chatId));
        if (!deletedActive) {
            renderAiChatSessions();
            return;
        }
        if (!aiState.chatSessions.length) {
            await createAiChat();
            return;
        }
        aiState.activeChatId = String(data.chat_id || aiState.chatSessions[0]?.id || '');
        localStorage.setItem('cloud_ai_active_chat_id', aiState.activeChatId);
        resetChatPagination();
        renderAiChatSessions();
        clearChatMessages();
        if (aiState.activeChatId) await loadChatHistoryPage(false);
    }

    async function loadChatHistoryPage(prepend) {
        if (aiState.isLoadingHistory || aiState.isLoadingMore) return;
        if (!aiState.historyHasMore && aiState.historyOffset > 0) return;

        if (aiState.historyOffset > 0) aiState.isLoadingMore = true;
        else aiState.isLoadingHistory = true;

        const limit = 5;
        const offset = aiState.historyOffset;

        try {
            const response = await fetch(AI_CONFIG.apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'ai_chat_load',
                    chat_id: aiState.activeChatId || '',
                    offset: String(offset),
                    limit: String(limit)
                })
            });

            const raw = await response.text();
            let data = {};
            try {
                data = JSON.parse(raw);
            } catch (parseErr) {
                throw new Error(`Invalid chat JSON response: ${raw.slice(0, 160)}`);
            }
            if (!data.success) {
                throw new Error(data.error || 'Failed to load chat');
            }

            const items = Array.isArray(data.items) ? data.items : [];
            if (!prepend && offset === 0 && items.length === 0 && !aiState.activeChatId) {
                const fallback = await loadFallbackChatHistory();
                if (fallback.length > 0) {
                    aiState.chatHistory = fallback;
                    aiState.historyHasMore = false;
                    aiState.historyOffset = 0;
                    renderChatHistory();
                    setTimeout(setupStickyCodeHeaders, 100);
                    scrollChatToBottom();
                    attachInfiniteScroll();
                    return;
                }
            }
            aiState.historyHasMore = !!data.has_more;
            aiState.historyOffset = offset + items.length;

            // Items come newest->older; we want chronological for UI
            const chronological = items.slice().reverse();

            const newMsgs = [];
            for (const it of chronological) {
                const q = String(it.question || '');
                const a = String(it.answer || '');
                const baseId = `pair_${it.id || (Date.now() + Math.random())}`;
                const chatMeta = parseChatMetaJson(it.meta_json);
                newMsgs.push({ id: baseId + '_q', role: 'user', content: q, timestamp: new Date((Number(it.created_at) || 0) * 1000).toISOString(), attachments: chatMeta?.attachments || [], mentionedFiles: chatMeta?.mentionedFiles || [] });
                const t = Number(it.time_seconds);
                const tok = Number(it.tokens);
                const sp = Number(it.speed);
                const stats = (Number.isFinite(t) || Number.isFinite(tok) || Number.isFinite(sp))
                    ? {
                        time: Number.isFinite(t) ? t : 0,
                        tokens: Number.isFinite(tok) ? tok : estimateTokens(a),
                        speed: Number.isFinite(sp) ? sp : ((Number.isFinite(t) && t > 0) ? (tok / t) : 0)
                    }
                    : null;
                const agentMeta = parseAgentMetaJson(it.meta_json);
                if (agentMeta) {
                    newMsgs[newMsgs.length - 1].attachments = agentMeta.attachments || newMsgs[newMsgs.length - 1].attachments || [];
                    newMsgs[newMsgs.length - 1].mentionedFiles = agentMeta.mentionedFiles || newMsgs[newMsgs.length - 1].mentionedFiles || [];
                }
                const answerContent = agentMeta ? (agentMeta.answer || a) : a;
                newMsgs.push({
                    id: baseId + '_a',
                    role: 'assistant',
                    content: answerContent,
                    timestamp: new Date((Number(it.created_at) || 0) * 1000).toISOString(),
                    stats,
                    isAgent: !!agentMeta,
                    agentMeta
                });
            }

            if (!prepend) {
                aiState.chatHistory = aiState.chatHistory.concat(newMsgs);
                renderChatHistory();
                setTimeout(setupStickyCodeHeaders, 100);
                scrollChatToBottom();
                attachInfiniteScroll();
                return;
            }

            const chat = elements.chat;
            const prevHeight = chat ? chat.scrollHeight : 0;

            aiState.chatHistory = newMsgs.concat(aiState.chatHistory);
            renderChatHistory();
            setTimeout(setupStickyCodeHeaders, 100);

            if (chat) {
                const newHeight = chat.scrollHeight;
                chat.scrollTop = (newHeight - prevHeight) + chat.scrollTop;
            }
        } catch (err) {
            console.error('Failed to load chat page:', err);
        } finally {
            aiState.isLoadingHistory = false;
            aiState.isLoadingMore = false;
        }
    }

    async function loadFallbackChatHistory() {
        const local = loadLocalChatHistory();
        try {
            const response = await fetch(AI_CONFIG.apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'load_history' })
            });
            const data = await response.json();
            const serverHistory = Array.isArray(data?.history) ? normalizeHistoryMessages(data.history) : [];
            return serverHistory.length > 0 ? serverHistory : local;
        } catch (e) {
            logAiError('Load fallback chat history', e);
            return local;
        }
    }

    function normalizeHistoryMessages(history) {
        return history
            .filter((m) => m && (m.role === 'user' || m.role === 'assistant') && typeof m.content === 'string' && m.content.trim() !== '')
            .map((m, i) => ({
                id: String(m.id || `legacy_${i}_${Date.now()}`),
                role: m.role,
                content: m.content,
                timestamp: m.timestamp || new Date().toISOString(),
                fileName: m.fileName || '',
                stats: m.stats || null,
                isAgent: !!m.isAgent,
                agentMeta: m.agentMeta || null,
                attachments: Array.isArray(m.attachments) ? m.attachments : [],
                mentionedFiles: Array.isArray(m.mentionedFiles) ? m.mentionedFiles : []
            }));
    }

    function loadLocalChatHistory() {
        try {
            return normalizeHistoryMessages(JSON.parse(localStorage.getItem(LOCAL_HISTORY_KEY) || '[]'));
        } catch (e) {
            logAiError('Load local chat history', e);
            return [];
        }
    }

    function attachInfiniteScroll() {
        const chat = elements.chat;
        if (!chat || chat._aiScrollPaginationAttached) return;
        chat._aiScrollPaginationAttached = true;
        chat.addEventListener('scroll', () => {
            if (aiState.isGenerating) return;
            if (!aiState.historyHasMore) return;
            if (chat.scrollTop <= 40) {
                loadChatHistoryPage(true);
            }
        }, { passive: true });
    }

    async function saveChatHistory() {
        try {
            const compact = aiState.chatHistory.slice(-120).map((m) => ({
                id: m.id,
                role: m.role,
                content: m.content,
                timestamp: m.timestamp,
                fileName: m.fileName || '',
                stats: m.stats || null,
                isAgent: !!m.isAgent,
                agentMeta: m.agentMeta || null,
                attachments: Array.isArray(m.attachments) ? m.attachments : [],
                mentionedFiles: Array.isArray(m.mentionedFiles) ? m.mentionedFiles : []
            }));
            localStorage.setItem(LOCAL_HISTORY_KEY, JSON.stringify(compact));
        } catch (err) {
            console.error('Failed to save chat history:', err);
        }
    }

    function serializePendingAttachments(list) {
        return (Array.isArray(list) ? list : []).map((a) => ({
            id: String(a?.id || ''),
            kind: a?.kind === 'image' ? 'image' : 'text',
            name: String(a?.name || ''),
            content: String(a?.content || ''),
            ocrConfidence: a?.ocrConfidence ?? null,
            ocrLang: String(a?.ocrLang || '')
        })).filter((a) => a.name || a.content);
    }

    function buildMessageMemory(content, attachments, mentionedFiles) {
        const blocks = [];
        const attached = serializePendingAttachments(attachments || []).slice(0, 4);
        if (attached.length) {
            blocks.push('[Files attached to this message]');
            attached.forEach((file) => {
                const body = String(file.content || '').slice(0, 6000);
                blocks.push(`${file.name}${body ? `\n${body}` : ''}`);
            });
        }
        const mentions = (Array.isArray(mentionedFiles) ? mentionedFiles : []).filter(Boolean);
        if (mentions.length) blocks.push(`[Referenced file paths]\n${mentions.join('\n')}`);
        return [String(content || ''), ...blocks].filter(Boolean).join('\n\n');
    }

    function buildRecentChatContext() {
        return (aiState.chatHistory || [])
            .filter((m) => m && (m.role === 'user' || m.role === 'assistant') && typeof m.content === 'string' && m.content.trim())
            .slice(-8)
            .map((m) => ({
                role: m.role,
                content: m.role === 'user' ? buildMessageMemory(m.content, m.attachments, m.mentionedFiles) : m.content
            }));
    }

    async function ensureAiChatSessionForRequest() {
        if (aiState.activeChatId && !String(aiState.activeChatId).startsWith('tmp_')) return aiState.activeChatId;
        try {
            const tempChatId = aiState.activeChatId && String(aiState.activeChatId).startsWith('tmp_') ? String(aiState.activeChatId) : '';
            const res = await fetch(AI_CONFIG.apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'ai_chat_create' })
            });
            const data = await res.json().catch(() => ({}));
            if (!data.success || !data.chat_id) return aiState.activeChatId || '';
            const chatId = String(data.chat_id);
            aiState.activeChatId = chatId;
            localStorage.setItem('cloud_ai_active_chat_id', chatId);
            const tempChat = tempChatId ? aiState.chatSessions.find((chat) => String(chat.id) === tempChatId) : null;
            if (tempChat) {
                tempChat.id = chatId;
                tempChat.updated_at = Math.floor(Date.now() / 1000);
            } else if (!aiState.chatSessions.some((chat) => String(chat.id) === chatId)) {
                aiState.chatSessions.unshift({ id: chatId, title: 'New chat', updated_at: Math.floor(Date.now() / 1000) });
            }
            renderAiChatSessions();
            return chatId;
        } catch (e) {
            return aiState.activeChatId || '';
        }
    }


    async function clearChatHistory() {
        try {
            const response = await fetch(AI_CONFIG.apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'ai_chat_clear', chat_id: aiState.activeChatId || '' })
            });
            
            const data = await response.json();
            
            if (data.success) {
                aiState.chatHistory = [];
                aiState.processedMessageIds.clear();
                localStorage.removeItem(LOCAL_HISTORY_KEY);
                clearChatMessages();
                elements.settingsModal?.classList.add('hidden');
                flashStatus(window.i18n?.historyCleared || 'History cleared');
                resetChatPagination();
                await loadAiChatSessions();
                requestAnimationFrame(() => {
                    try {
                        elements.chat?.scrollIntoView({ block: 'nearest' });
                    } catch (e) {}
                    if (elements.chat) elements.chat.scrollTop = 0;
                    elements.input?.focus();
                });
            } else {
                throw new Error(data.error || 'Failed to clear history');
            }
        } catch (err) {
            flashStatus((window.i18n?.clearHistoryError || 'Error: ') + err.message);
        }
    }

    function getCurrentTime() {
        return new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    function formatTime(seconds) {
        if (seconds < 60) return `${seconds.toFixed(2)}s`;
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = (seconds % 60).toFixed(1);
        return `${minutes}m ${remainingSeconds}s`;
    }

    function formatTokens(tokens) {
        if (tokens >= 1000) {
            return `${(tokens / 1000).toFixed(1)}k`;
        }
        return tokens.toString();
    }

    function calculateSpeed(tokens, timeSeconds) {
        const speed = tokens / timeSeconds;
        if (speed >= 1000) {
            return `${(speed / 1000).toFixed(1)}k t/s`;
        }
        return `${speed.toFixed(1)} t/s`;
    }

    function estimateTokens(text) {
        return Math.round((text?.length || 0) / 3.5);
    }
    function buildAgentStats(elapsedSec, usage, answerText, promptText) {
        const apiTotal = Number(usage?.total) || (Number(usage?.prompt) || 0) + (Number(usage?.completion) || 0);
        const tokens = apiTotal > 0 ? apiTotal : estimateTokens(String(promptText || '') + String(answerText || ''));
        const time = Number(elapsedSec) || 0;
        const speed = time > 0 ? tokens / time : 0;
        return { tokens, time, speed };
    }
    function buildChatMetaJson(attachments, mentionedFiles) {
        return JSON.stringify({
            v: 1,
            type: 'chat',
            attachments: serializePendingAttachments(attachments || []),
            mentionedFiles: Array.isArray(mentionedFiles) ? mentionedFiles.slice() : []
        });
    }
    function buildAgentMetaJson(agentText, attachments, mentionedFiles) {
        const patches = Object.entries(aiState.pendingAgentPatches || {}).map(([path, p]) => ({
            path,
            before: p.before,
            after: p.after,
            tool: p.tool || ''
        }));
        return JSON.stringify({
            v: 1,
            type: 'agent',
            answer: String(agentText || ''),
            plan: (aiState.agentPlanSteps || []).filter((s) => s.id !== '_agent_boot').map((s) => ({
                tool: s.tool,
                label: s.label,
                status: s.status,
                result: s.result
            })),
            patches,
            attachments: serializePendingAttachments(attachments || []),
            mentionedFiles: Array.isArray(mentionedFiles) ? mentionedFiles.slice() : []
        });
    }
    function parseChatMetaJson(raw) {
        if (!raw) return null;
        try {
            const m = typeof raw === 'string' ? JSON.parse(raw) : raw;
            return m && m.type === 'chat' ? m : null;
        } catch (e) {
            return null;
        }
    }
    function parseAgentMetaJson(raw) {
        if (!raw) return null;
        try {
            const m = typeof raw === 'string' ? JSON.parse(raw) : raw;
            return m && m.type === 'agent' ? m : null;
        } catch (e) {
            return null;
        }
    }
    function renderAgentPlanFromMeta(planHost, steps) {
        if (!planHost || !Array.isArray(steps) || !steps.length) return;
        planHost.replaceChildren();
        const plan = document.createElement('div');
        plan.className = 'ai-agent-plan done';
        const head = document.createElement('div');
        head.className = 'ai-agent-plan-head';
        head.innerHTML = `<span class="ai-agent-plan-pulse"></span><span class="ai-agent-plan-head-text">${escapeHtml(tr('aiAgentPlanDone', 'Agent plan'))}</span>`;
        const stepsEl = document.createElement('div');
        stepsEl.className = 'ai-agent-plan-steps';
        steps.forEach((step) => appendPlanStepRow(stepsEl, { ...step, label: planLabelFromStoredStep(step) || step.label }));
        plan.appendChild(head);
        plan.appendChild(stepsEl);
        planHost.appendChild(plan);
    }
    function renderAgentHistoryBlock(messageId, meta, answerText, stats) {
        const msgEl = document.createElement('div');
        msgEl.className = 'message ai-message';
        msgEl.id = messageId;
        const header = document.createElement('div');
        header.className = 'message-header';
        header.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"/></svg><span class="message-role">${tr('aiModeAgent', 'Agent')}</span>`;
        msgEl.appendChild(header);
        const outer = document.createElement('div');
        outer.className = 'message-content';
        const planWrap = document.createElement('div');
        renderAgentPlanFromMeta(planWrap, meta.plan);
        outer.appendChild(planWrap);
        const answerHost = document.createElement('div');
        answerHost.className = 'ai-agent-final-answer';
        answerHost.appendChild(formatResponseInner(answerText || ''));
        renderMath(answerHost);
        outer.appendChild(answerHost);
        if (Array.isArray(meta.patches) && meta.patches.length) {
            meta.patches.forEach((p) => {
                if (p.before != null && p.after != null) {
                    outer.appendChild(createAgentPatchPreviewElement(p.before, p.after));
                }
            });
        }
        msgEl.appendChild(outer);
        elements.chat.appendChild(msgEl);
        if (stats) appendMessageStatsFooter(msgEl, stats, answerText);
        return msgEl;
    }
    function notifyEditorCompileStatus(success, message) {
        const iframe = document.querySelector('#viewer iframe[data-tab-id]');
        const win = iframe?.contentWindow;
        if (!win) return;
        const cls = success ? 'status-ok' : 'status-error';
        const text = message || (success ? 'OK' : 'Error');
        try {
            if (typeof win.setStatus === 'function') {
                win.setStatus(text, cls);
            }
            const indicator = win.document?.getElementById('status-indicator');
            if (indicator) {
                indicator.textContent = text;
                indicator.className = cls;
            }
            const compileBtn = win.document?.getElementById('compile-btn');
            if (compileBtn) {
                compileBtn.classList.remove('status-compiling', 'status-ok', 'status-error');
                compileBtn.classList.add(cls);
            }
            const mobileBtn = win.document?.getElementById('mobile-compile-btn');
            if (mobileBtn) {
                mobileBtn.classList.remove('status-compiling', 'status-ok', 'status-error');
                mobileBtn.classList.add(cls);
            }
        } catch (e) {}
    }

    function renderChatHistory() {
        if (!elements.chat) return;
        clearChatMessages();
        aiState.chatHistory.forEach((message) => {
            if (message.role === 'user') {
                addMessageToUI('user', message.content, false, message.id, false);
            } else if (message.isAgent && message.agentMeta) {
                renderAgentHistoryBlock(message.id, message.agentMeta, message.content, message.stats);
            } else {
                addMessageWithFooter('assistant', message.content, message.id, message.stats, false);
            }
        });
    }
    function appendMessageStatsFooter(msgEl, stats, copyText) {
        if (!msgEl || msgEl.querySelector('.message-footer')) return;
        const footer = document.createElement('div');
        footer.className = 'message-footer';
        const actions = document.createElement('div');
        actions.className = 'message-actions';
        actions.appendChild(createCopyButton(copyText || '', true));
        const regenerateBtn = document.createElement('button');
        regenerateBtn.className = 'action-button';
        regenerateBtn.setAttribute('aria-label', tr('regenerateResponse', 'Regenerate response'));
        regenerateBtn.title = tr('regenerateResponse', 'Regenerate response');
        regenerateBtn.innerHTML = `
            <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M1 4v6h6M23 20v-6h-6" fill="none" />
                <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15" fill="none" />
            </svg>
        `;
        regenerateBtn.onclick = () => regenerateResponse(msgEl.id);
        actions.appendChild(regenerateBtn);
        footer.appendChild(actions);
        msgEl.appendChild(footer);
    }

    async function copyToClipboard(text) {
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(text);
                return true;
            }
            
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            
            try {
                const success = document.execCommand('copy');
                document.body.removeChild(textarea);
                return success;
            } catch (err) {
                document.body.removeChild(textarea);
                console.error('Fallback copy failed:', err);
                return false;
            }
        } catch (err) {
            console.error('Clipboard write failed:', err);
            return false;
        }
    }

    function createCopyButton(text, iconOnly = false) {
        const button = document.createElement('button');
        button.className = 'action-button';
        button.setAttribute('aria-label', tr('copy', 'Copy'));
        button.title = tr('copy', 'Copy');
        const getCopyHtml = () => `
            <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2" fill="none" />
                <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" fill="none" />
            </svg>
        `;
        const getCopiedHtml = () => `
            <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12" fill="none" />
            </svg>
        `;
        button.innerHTML = iconOnly ? getCopyHtml() : `${getCopyHtml()}<span>${escapeHtml(tr('copy', 'Copy'))}</span>`;
        
        button.onclick = async (e) => {
            e.stopPropagation();
            const success = await copyToClipboard(text);
            if (success) {
                button.innerHTML = iconOnly ? getCopiedHtml() : `${getCopiedHtml()}<span>${escapeHtml(tr('messageCopied', 'Copied'))}</span>`;
                button.classList.add('copied');
                setTimeout(() => {
                    button.innerHTML = iconOnly ? getCopyHtml() : `${getCopyHtml()}<span>${escapeHtml(tr('copy', 'Copy'))}</span>`;
                    button.classList.remove('copied');
                }, 2000);
            }
        };
        
        return button;
    }

    function parseThinkBlock(content) {
        const thinkRegex = /<think>([\s\S]*?)<\/think>/g;
        let match;
        let lastIndex = 0;
        const parts = [];
        
        while ((match = thinkRegex.exec(content)) !== null) {
            if (match.index > lastIndex) {
                parts.push({
                    type: 'text',
                    content: content.substring(lastIndex, match.index)
                });
            }
            
            parts.push({
                type: 'think',
                content: match[1].trim()
            });
            
            lastIndex = match.index + match[0].length;
        }
        
        if (lastIndex < content.length) {
            parts.push({
                type: 'text',
                content: content.substring(lastIndex)
            });
        }
        
        return parts;
    }

    // FIXED: Proper LaTeX formatting without escaping
    const _mathQueue = [];
    const _mathDelimiters = [
        {left: '\\[', right: '\\]', display: true},
        {left: '\\(', right: '\\)', display: false},
        {left: '$$', right: '$$', display: true},
        {left: '$', right: '$', display: false}
    ];
    function _doRenderMath(el) {
        window.renderMathInElement(el, {delimiters: _mathDelimiters, throwOnError: false, strict: 'ignore'});
    }
    function _flushMathQueue() {
        const pending = _mathQueue.splice(0);
        for (const el of pending) { if (document.contains(el)) _doRenderMath(el); }
    }
    function _pollKatex() {
        if (window.renderMathInElement) { _flushMathQueue(); }
        else { setTimeout(_pollKatex, 100); }
    }
    function renderMath(element) {
        if (window.renderMathInElement) { _doRenderMath(element); }
        else { _mathQueue.push(element); if (_mathQueue.length === 1) _pollKatex(); }
    }

    function formatInlineMarkdownSafe(text) {
        // text is expected to be HTML-escaped already
        text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');
        text = text.replace(/`(.*?)`/g, '<code>$1</code>');
        return text;
    }

    function formatMarkdown(text) {
        // Escape HTML first, then apply limited markdown formatting
        const safe = escapeHtml(String(text ?? ''));

        let out = safe;
        out = out.replace(/^### (.*?)$/gm, '<h3>$1</h3>');
        out = out.replace(/^## (.*?)$/gm, '<h2>$1</h2>');
        out = out.replace(/^# (.*?)$/gm, '<h1>$1</h1>');
        out = formatInlineMarkdownSafe(out);

        return out;
    }

    function isHrLine(line) {
        const trimmed = String(line ?? '').trim();
        return trimmed === '---' || trimmed === '***' || trimmed === '___' || /^-{3,}$/.test(trimmed);
    }

    function parseTableBlock(lines, startIndex) {
        const headerLine = lines[startIndex] || '';
        const sepLine = lines[startIndex + 1] || '';
        const hasPipe = headerLine.includes('|');
        const sepOk = /\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?/.test(String(sepLine).trim());
        if (!hasPipe || !sepOk) return null;

        const splitRow = (row) => {
            let r = String(row ?? '').trim();
            if (r.startsWith('|')) r = r.slice(1);
            if (r.endsWith('|')) r = r.slice(0, -1);
            return r.split('|').map(c => c.trim());
        };

        const headers = splitRow(headerLine);
        const rows = [];
        let i = startIndex + 2;
        while (i < lines.length) {
            const ln = lines[i];
            if (ln == null) break;
            if (String(ln).trim() === '') break;
            if (isHrLine(ln)) break;
            if (!String(ln).includes('|')) break;
            rows.push(splitRow(ln));
            i++;
        }

        if (headers.length === 0) return null;
        return { headers, rows, nextIndex: i };
    }

    function parseListItemLine(line) {
        const m = /^(\s*)([-*+]|\d+[.)])\s+(.*)$/.exec(String(line ?? ''));
        if (!m) return null;
        return { indent: m[1].replace(/\t/g, '  ').length, ordered: !/^[-*+]$/.test(m[2]), text: m[3] };
    }

    function parseListBlock(lines, startIndex) {
        const first = parseListItemLine(lines[startIndex]);
        if (!first) return null;
        const items = [];
        let i = startIndex;
        while (i < lines.length) {
            const item = parseListItemLine(lines[i]);
            if (!item) break;
            items.push(item);
            i++;
        }
        const build = (start, indent) => {
            const list = document.createElement(items[start].ordered ? 'ol' : 'ul');
            list.className = 'ai-list';
            let idx = start;
            while (idx < items.length && items[idx].indent >= indent) {
                if (items[idx].indent > indent) {
                    const nested = build(idx, items[idx].indent);
                    (list.lastElementChild || list).appendChild(nested.list);
                    idx = nested.nextIndex;
                    continue;
                }
                if (items[idx].ordered !== items[start].ordered) break;
                const li = document.createElement('li');
                li.innerHTML = formatInlineMarkdownSafe(escapeHtml(items[idx].text));
                list.appendChild(li);
                idx++;
            }
            return { list, nextIndex: idx };
        };
        const built = build(0, items[0].indent);
        return { list: built.list, nextIndex: startIndex + built.nextIndex };
    }

    function createThinkBlock(content, isLive = false) {
        const thinkDiv = document.createElement('div');
        thinkDiv.className = 'think-block';
        
        const header = document.createElement('div');
        header.className = 'think-header';
        
        const headerLeft = document.createElement('div');
        headerLeft.className = 'think-header-left';
        headerLeft.innerHTML = `
            <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" fill="none" />
                <path d="M12 16v-4M12 8h.01" fill="none" />
            </svg>
            ${escapeHtml(tr('aiThink', 'think'))}
        `;
        
        if (isLive) {
            const liveIndicator = document.createElement('span');
            liveIndicator.className = 'think-live-indicator';
            liveIndicator.innerHTML = `<svg width="10" height="10" viewBox="0 0 10 10" fill="currentColor" aria-hidden="true"><circle cx="5" cy="5" r="4"></circle></svg> ${escapeHtml(tr('aiGenerating', 'generating...'))}`;
            headerLeft.appendChild(liveIndicator);
        }
        
        const headerRight = document.createElement('div');
        headerRight.className = 'think-header-right';
        headerRight.textContent = '+';
        
        header.appendChild(headerLeft);
        header.appendChild(headerRight);
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'think-content';
        renderThinkContent(contentDiv, content, isLive);
        
        thinkDiv.appendChild(header);
        thinkDiv.appendChild(contentDiv);
        
        header.addEventListener('click', () => {
            thinkDiv.classList.toggle('open');
        });
        
        return thinkDiv;
    }

    function renderThinkContent(contentDiv, content, isLive = false) {
        const text = String(content ?? '');
        contentDiv.dataset.raw = text;
        contentDiv.replaceChildren(formatResponseInner(text.replace(/<\/?think>/g, '')));
        if (!isLive) renderMath(contentDiv);
    }

    function updateThinkBlockLive(thinkDiv, content) {
        const contentDiv = thinkDiv.querySelector('.think-content');
        if (contentDiv) {
            renderThinkContent(contentDiv, content, true);
        }
    }

    // FIXED: Proper content rendering without nested message-content wrappers
    function createMathDisplayBlock(mathContent) {
        const item = document.createElement('div');
        item.className = 'math-display';
        item.textContent = mathContent;
        return item;
    }

    function formatResponseInner(content, liveThinkBlocks = null) {
        const container = document.createElement('div');
        container.className = 'message-content-inner';
        
        if (!content) return container;
        
        // First parse think blocks
        const parts = parseThinkBlock(content);
        
        for (const part of parts) {
            if (part.type === 'think') {
                const existingThink = liveThinkBlocks?.get(part.content);
                const thinkText = existingThink?.querySelector('.think-content')?.dataset.raw || part.content;
                container.appendChild(createThinkBlock(thinkText));
            } else {
                // Process the text part
                const text = part.content;
                const segments = text.split(/(```[\s\S]*?```|\$\$[\s\S]*?\$\$|\\\[[\s\S]*?\\\])/g);
                
                for (const segment of segments) {
                    if (/^\$\$[\s\S]*\$\$$/.test(segment.trim()) || /^\\\[[\s\S]*\\\]$/.test(segment.trim())) {
                        container.appendChild(createMathDisplayBlock(segment.trim()));
                    } else if (segment.startsWith('```') && segment.endsWith('```')) {
                        // Code block
                        const firstLineEnd = segment.indexOf('\n');
                        let language = '';
                        let code = '';
                        
                        if (firstLineEnd > 3) {
                            language = segment.substring(3, firstLineEnd).trim();
                            code = segment.substring(firstLineEnd + 1, segment.length - 3).trim();
                        } else {
                            code = segment.substring(3, segment.length - 3).trim();
                        }
                        
                        const codeBlock = document.createElement('div');
                        codeBlock.className = 'code-block';
                        
                        const header = document.createElement('div');
                        header.className = 'code-header';
                        
                        const langSpan = document.createElement('span');
                        langSpan.className = 'code-language';
                        langSpan.textContent = language || 'code';
                        
                        header.appendChild(langSpan);
                        
                        const copyBtn = createCopyButton(code);
                        copyBtn.classList.add('code-copy-btn');
                        header.appendChild(copyBtn);
                        
                        const pre = document.createElement('pre');
                        const codeEl = document.createElement('code');
                        codeEl.setAttribute('data-lang', language || '');

                        // Apply syntax highlighting
                        if (window.hljs) {
                            const lang = language ? language.toLowerCase() : '';
                            // Alias mapping
                            const aliases = { latex: 'tex', latex2e: 'tex', octave: 'matlab' };
                            const hljsLang = aliases[lang] || lang;
                            if (hljsLang && window.hljs.getLanguage(hljsLang)) {
                                codeEl.innerHTML = window.hljs.highlight(code, { language: hljsLang }).value;
                            } else {
                                codeEl.innerHTML = window.hljs.highlightAuto(code).value;
                            }
                            codeEl.classList.add('hljs');
                        } else {
                            codeEl.textContent = code;
                        }

                        pre.appendChild(codeEl);
                        
                        codeBlock.appendChild(header);
                        codeBlock.appendChild(pre);
                        container.appendChild(codeBlock);
                    } else if (segment.trim()) {
                        // Regular text - apply markdown formatting (incl. HR and tables)
                        const rawLines = String(segment).split('\n');
                        let i = 0;

                        while (i < rawLines.length) {
                            const rawLine = rawLines[i];
                            const line = rawLine == null ? '' : String(rawLine);

                            if (line.trim() === '') { i++; continue; }

                            // Horizontal rule: only outside code blocks (we are)
                            if (isHrLine(line)) {
                                const hr = document.createElement('hr');
                                hr.className = 'ai-hr';
                                container.appendChild(hr);
                                i++;
                                continue;
                            }

                            // Table block
                            const table = parseTableBlock(rawLines, i);
                            if (table) {
                                const wrap = document.createElement('div');
                                wrap.className = 'ai-table-wrap';
                                const tbl = document.createElement('table');
                                tbl.className = 'ai-table';

                                const thead = document.createElement('thead');
                                const trh = document.createElement('tr');
                                for (const h of table.headers) {
                                    const th = document.createElement('th');
                                    th.innerHTML = formatInlineMarkdownSafe(escapeHtml(h));
                                    trh.appendChild(th);
                                }
                                thead.appendChild(trh);
                                tbl.appendChild(thead);

                                const tbody = document.createElement('tbody');
                                for (const r of table.rows) {
                                    const tr = document.createElement('tr');
                                    for (let ci = 0; ci < table.headers.length; ci++) {
                                        const td = document.createElement('td');
                                        td.innerHTML = formatInlineMarkdownSafe(escapeHtml(r[ci] ?? ''));
                                        tr.appendChild(td);
                                    }
                                    tbody.appendChild(tr);
                                }
                                tbl.appendChild(tbody);
                                wrap.appendChild(tbl);
                                container.appendChild(wrap);
                                i = table.nextIndex;
                                continue;
                            }

                            // List block
                            const list = parseListBlock(rawLines, i);
                            if (list) {
                                container.appendChild(list.list);
                                i = list.nextIndex;
                                continue;
                            }

                            // Normal line -> markdown -> block
                            const formattedLine = formatMarkdown(line);
                            if (formattedLine.startsWith('<h')) {
                                const temp = document.createElement('div');
                                temp.innerHTML = formattedLine;
                                if (temp.firstChild) container.appendChild(temp.firstChild);
                            } else {
                                const p = document.createElement('p');
                                p.innerHTML = formattedLine;
                                container.appendChild(p);
                            }
                            i++;
                        }
                    }
                }
            }
        }
        
        return container;
    }

    function addMessageToUI(role, content, saveToHistory = true, messageId = null, autoScroll = true) {
        if (!elements.chat) return null;
        
        const id = messageId || 'msg_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${role === 'user' ? 'user-message' : 'ai-message'}`;
        messageDiv.id = id;
        
        if (role !== 'user') {
            const header = document.createElement('div');
            header.className = 'message-header';
            const roleLabel = aiState.chatMode === 'agent'
                ? tr('aiModeAgent', 'Agent')
                : tr('aiModeChat', 'Chat');
            header.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"/></svg>
                <span class="message-role">${roleLabel}</span>
            `;
            messageDiv.appendChild(header);
        }
        
        if (role === 'user') {
            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content';
            contentDiv.textContent = content;
            messageDiv.appendChild(contentDiv);
            const footer = document.createElement('div');
            footer.className = 'message-footer';
            const actions = document.createElement('div');
            actions.className = 'message-actions';
            actions.appendChild(createCopyButton(content, true));
            footer.appendChild(actions);
            messageDiv.appendChild(footer);
        } else {
            const outer = document.createElement('div');
            outer.className = 'message-content';
            const ops = parseAgentPatch(content);
            if (ops.length > 0) {
                outer.appendChild(createAgentPatchPreviewElement(null, null, ops));
            } else {
                outer.appendChild(formatResponseInner(content));
                renderMath(outer);
            }
            messageDiv.appendChild(outer);
        }
        
        elements.chat.appendChild(messageDiv);
        if (autoScroll) scrollChatToBottom();
        
        if (saveToHistory) {
            const currentFile = document.getElementById('viewer')?.dataset.path;
            
            const existingIndex = aiState.chatHistory.findIndex(msg => msg.id === id);
            if (existingIndex === -1 && !aiState.processedMessageIds.has(id)) {
                aiState.chatHistory.push({
                    id: id,
                    role: role,
                    content: content,
                    timestamp: new Date().toISOString(),
                    fileName: currentFile
                });
                aiState.processedMessageIds.add(id);
                saveChatHistory();
            }
        }
        
        return id;
    }

    function addMessageWithFooter(role, content, messageId = null, stats = null, autoScroll = true) {
        const id = addMessageToUI(role, content, true, messageId, autoScroll);
        if (!id || role === 'user') return id;
        
        const messageElement = document.getElementById(id);
        if (!messageElement) return id;
        
        const footer = document.createElement('div');
        footer.className = 'message-footer';
        
        const actions = document.createElement('div');
        actions.className = 'message-actions';
        
        const copyBtn = createCopyButton(content, true);
        actions.appendChild(copyBtn);
        
        const regenerateBtn = document.createElement('button');
        regenerateBtn.className = 'action-button';
        regenerateBtn.setAttribute('aria-label', tr('regenerateResponse', 'Regenerate response'));
        regenerateBtn.title = tr('regenerateResponse', 'Regenerate response');
        regenerateBtn.innerHTML = `
            <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M1 4v6h6M23 20v-6h-6" fill="none" />
                <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15" fill="none" />
            </svg>
        `;
        regenerateBtn.onclick = () => regenerateResponse(id);
        actions.appendChild(regenerateBtn);
        
        footer.appendChild(actions);
        messageElement.appendChild(footer);
        
        return id;
    }

    function showTypingIndicator() {
        if (!elements.chat) return;
        
        removeTypingIndicator();
        
        const indicator = document.createElement('div');
        indicator.className = 'message ai-message typing';
        indicator.id = 'typingIndicator';
        indicator.innerHTML = `
            <div class="typing-indicator">
                <span></span>
                <span></span>
                <span></span>
            </div>
        `;
        elements.chat.appendChild(indicator);
        scrollChatToBottom();
    }

    function removeTypingIndicator() {
        const indicator = document.getElementById('typingIndicator');
        if (indicator) indicator.remove();
    }

    function setSendButtonGenerating(isGenerating) {
        if (!elements.sendBtn) return;
        if (isGenerating) {
            elements.sendBtn.classList.add('is-stop');
            elements.sendBtn.title = tr('stop', 'Stop');
            elements.sendBtn.innerHTML = `
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"></circle>
                    <rect x="8" y="8" width="8" height="8" rx="2.2" fill="currentColor"></rect>
                </svg>
            `;
        } else {
            elements.sendBtn.classList.remove('is-stop');
            elements.sendBtn.title = tr('sendMessage', 'Send');
            elements.sendBtn.innerHTML = `
                <svg width="18" height="18" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
            `;
        }
    }

    function stopGeneration() {
        if (aiState.serverRunJobId) {
            aiState.serverRunStopping = true;
            flashStatus(tr('aiStopping', 'Stopping assistant...'));
            void cancelServerAiRun(aiState.serverRunJobId);
        }
        try { aiState.abortController?.abort(); } catch (e) {}
    }

    function getUserLabel() {
        if (aiState.userDisplayName) return aiState.userDisplayName;
        return window.i18n?.user || 'user';
    }
    function refreshUserMessageLabels() {
        const label = getUserLabel();
        document.querySelectorAll('#ai-chat .user-message .message-role').forEach((el) => {
            el.textContent = label;
        });
    }

    async function ensureUserDisplayName() {
        if (aiState.userDisplayName) return;
        const cachedEmail = window.cloudCurrentUserDisplayEmail || window.cloudCurrentUser;
        if (typeof cachedEmail === 'string' && cachedEmail) {
            aiState.userDisplayName = cachedEmail.includes('@') ? (cachedEmail.split('@')[0] || 'user') : cachedEmail;
            refreshUserMessageLabels();
            return;
        }
        try {
            const url = new URL(AI_CONFIG.fileApiEndpoint, window.location.href);
            url.searchParams.set('action', 'get_state');
            const res = await fetch(url.toString(), { method: 'GET' });
            const data = await res.json().catch(() => null);
            const email = data?.active_email || data?.activeEmail || data?.active || '';
            if (typeof email === 'string' && email.includes('@')) {
                aiState.userDisplayName = email.split('@')[0] || 'user';
                refreshUserMessageLabels();
            }
        } catch (e) { logAiError('Fetch user display name', e); }
    }

    function rebuildMessageWithMentions(cleanMessage, placements) {
        const msg = String(cleanMessage || '');
        const list = Array.isArray(placements) ? placements.slice() : [];
        const grouped = [];
        const byIndex = new Map();
        list.forEach((p) => {
            const name = String(p?.name || '').trim();
            const rawIdx = Number(p?.index);
            if (!name || !Number.isFinite(rawIdx)) return;
            if (new RegExp(`(^|\\s)@${escapeRegExp(name)}(?=\\s|$)`).test(msg)) return;
            const idx = Math.max(0, Math.min(msg.length, rawIdx));
            if (!byIndex.has(idx)) {
                const group = { index: idx, names: [] };
                byIndex.set(idx, group);
                grouped.push(group);
            }
            byIndex.get(idx).names.push(name);
        });
        grouped.sort((a, b) => b.index - a.index);
        let out = msg;
        for (const group of grouped) {
            const before = out.slice(0, group.index);
            const after = out.slice(group.index);
            const prefix = before && !/\s$/.test(before) ? ' ' : '';
            const suffix = after && !/^\s/.test(after) ? ' ' : '';
            out = before + prefix + group.names.map((name) => '@' + name).join(' ') + suffix + after;
        }
        return out;
    }

    async function appendChatPairToDb(question, answer, model, fileName, stats, metaJson) {
        try {
            const t = Number(stats?.time);
            const tokens = Number(stats?.tokens);
            const speed = Number(stats?.speed);
            const params = {
                action: 'ai_chat_append',
                question: String(question || ''),
                answer: String(answer || ''),
                model: String(model || ''),
                file_name: String(fileName || ''),
                time_seconds: Number.isFinite(t) ? String(t) : '',
                tokens: Number.isFinite(tokens) ? String(tokens) : '',
                speed: Number.isFinite(speed) ? String(speed) : ''
            };
            if (aiState.activeChatId) params.chat_id = aiState.activeChatId;
            if (metaJson) params.meta_json = String(metaJson);
            const res = await fetch(AI_CONFIG.apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(params)
            });
            const data = await res.json().catch(() => null);
            if (data?.chat_id && !aiState.activeChatId) {
                aiState.activeChatId = String(data.chat_id);
                localStorage.setItem('cloud_ai_active_chat_id', aiState.activeChatId);
            }
            await loadAiChatSessions();
        } catch (e) {
            console.warn('Failed to append chat pair:', e);
        }
    }

    async function startServerAiRun(payload) {
        const params = new URLSearchParams({
            action: 'ai_run_start',
            chat_id: aiState.activeChatId || '',
            message: payload.message || '',
            user_facing_message: payload.userFacingMessage || payload.message || '',
            model: payload.model || '',
            role: payload.role || '',
            chat_mode: payload.chatMode || 'chat',
            root: payload.root || '',
            mentioned_files: JSON.stringify(payload.mentionedFiles || []),
            mentioned_roots: JSON.stringify(payload.mentionedRoots || {}),
            attachments: JSON.stringify(serializePendingAttachments(payload.attachments || [])),
            history: JSON.stringify(buildRecentChatContext())
        });
        const res = await fetch(AI_CONFIG.apiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: params
        });
        const data = await res.json().catch(() => ({}));
        if (!data.success) throw new Error(data.error || 'Failed to start worker');
        if (data.chat_id) {
            aiState.activeChatId = String(data.chat_id);
            localStorage.setItem('cloud_ai_active_chat_id', aiState.activeChatId);
        }
        await loadAiChatSessions();
        return data;
    }

    async function cancelServerAiRun(jobId) {
        if (!jobId) return;
        try {
            await fetch(AI_CONFIG.apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: new URLSearchParams({ action: 'ai_run_cancel', job_id: jobId, chat_id: aiState.activeChatId || '' })
            });
        } catch (e) { logAiError('Cancel server AI run', e); }
    }

    function renderServerRunEvents(messageId, job) {
        const msgEl = document.getElementById(messageId);
        const host = msgEl?.querySelector('.message-content');
        if (!host) return;
        const events = Array.isArray(job?.events) ? job.events : [];
        renderServerRunPlan(messageId, job);
        const last = events[events.length - 1];
        const path = last?.data?.path;
        if (path && (last.type === 'tool' || last.type === 'done')) {
            void highlightNewFilesInTree([{ path, root: last?.data?.root || '', type: last?.data?.type || 'file' }], { openLastPdf: /\.pdf$/i.test(path) });
        }
    }

    async function pollServerAiRun(jobId, messageId, startTime, userFacingMessage, modelName) {
        let finalJob = null;
        for (;;) {
            if (aiState.abortController?.signal?.aborted) {
                throw new DOMException('Request aborted', 'AbortError');
            }
            const res = await fetch(AI_CONFIG.apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: new URLSearchParams({ action: 'ai_run_status', job_id: jobId, chat_id: aiState.activeChatId || '' })
            });
            const data = await res.json().catch(() => ({}));
            if (!data.success) throw new Error(data.error || 'Failed to read worker status');
            const job = data.job;
            if (!job) throw new Error('Worker job not found');
            renderServerRunEvents(messageId, job);
            if (job.status === 'done' || job.status === 'error' || job.status === 'cancelled') {
                finalJob = job;
                break;
            }
            await new Promise((resolve) => setTimeout(resolve, 1200));
        }
        if (finalJob.status === 'cancelled') throw new DOMException('Request aborted', 'AbortError');
        if (finalJob.status === 'error') {
            logAiError('Server AI run error', finalJob.error || 'Worker failed');
            throw new Error(finalJob.error || 'Worker failed');
        }
        (finalJob.events || [])
            .filter((e) => e?.type === 'error' || e?.type === 'compile_error' || e?.data?.error)
            .forEach((e) => logAiError(`Server tool ${serverRunEventTool(e)} failed`, e?.data?.error || e?.error || e?.text || String(e)));
        const answer = String(finalJob.answer || '').trim() || tr('aiEmptyResponse', 'Empty response');
        const msgEl = document.getElementById(messageId);
        const host = msgEl?.querySelector('.message-content');
        if (host) {
            const plan = host.querySelector('.ai-agent-plan');
            if (plan) {
                let finalAnswer = host.querySelector('.ai-agent-final-answer');
                if (!finalAnswer) {
                    finalAnswer = document.createElement('div');
                    finalAnswer.className = 'ai-agent-final-answer';
                    host.appendChild(finalAnswer);
                }
                finalAnswer.replaceChildren(formatResponseInner(answer));
                renderMath(finalAnswer);
            } else {
                host.replaceChildren(formatResponseInner(answer));
                renderMath(host);
            }
        }
        const elapsed = (Date.now() - startTime) / 1000;
        const stats = { time: elapsed, tokens: estimateTokens(answer), speed: elapsed > 0 ? estimateTokens(answer) / elapsed : 0 };
        if (msgEl) appendMessageStatsFooter(msgEl, stats, answer);
        aiState.chatHistory.push({ id: messageId + '_u', role: 'user', content: userFacingMessage, timestamp: new Date().toISOString(), fileName: '', attachments: serializePendingAttachments(finalJob.attachments || []), mentionedFiles: Array.isArray(finalJob.mentioned_files) ? finalJob.mentioned_files.slice() : [] });
        aiState.chatHistory.push({ id: messageId, role: 'assistant', content: answer, timestamp: new Date().toISOString(), fileName: '', stats });
        saveChatHistory();
        await loadAiChatSessions();
        return finalJob;
    }

    async function resumeServerAiRunIfNeeded() {
        if (aiState.isGenerating || !aiState.activeChatId) return;
        const res = await fetch(AI_CONFIG.apiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: new URLSearchParams({ action: 'ai_run_status', chat_id: aiState.activeChatId })
        });
        const data = await res.json().catch(() => ({}));
        const job = data?.job;
        if (!data?.success || !job) return;
        if (String(job.status || '') === 'done') {
            if (!elements.chat?.querySelector('.message')) {
                addMessageToUI('user', job.user_facing_message || job.message || '', false);
                addMessageToUI('assistant', job.answer || tr('aiEmptyResponse', 'Empty response'), false);
            }
            return;
        }
        if (String(job.status || '') === 'error') {
            logAiError('Server AI run error', job.error || 'Worker failed');
            if (!elements.chat?.querySelector('.message')) {
                addMessageToUI('user', job.user_facing_message || job.message || '', false);
                addMessageToUI('error', job.error || 'Worker failed', false);
            }
            return;
        }
        if (!['queued', 'running'].includes(String(job.status || ''))) return;
        aiState.isGenerating = true;
        setInputState(true);
        setSendButtonGenerating(true);
        if (!elements.chat?.querySelector('.message')) addMessageToUI('user', job.user_facing_message || job.message || '', false);
        const messageId = 'msg_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
        addMessageToUI('assistant', '', false, messageId, false);
        try {
            await pollServerAiRun(job.id, messageId, Date.now(), job.user_facing_message || job.message || '', job.model || 'AI');
        } catch (e) {
            addMessageToUI('error', e.message || String(e));
        } finally {
            aiState.isGenerating = false;
            setInputState(false);
            setSendButtonGenerating(false);
            aiState.abortController = null;
        }
    }

    async function compileOpenDocument(format) {
        const activeTab = window.tabsState?.tabs?.find((tab) => tab.id === window.tabsState?.activeTabId);
        const fmt = String(format || 'auto').toLowerCase();
        const isLatex = fmt === 'latex' || (fmt === 'auto' && activeTab?.latexEditor);
        const isMd = fmt === 'markdown' || (fmt === 'auto' && activeTab?.markdownEditor);
        const iframe = document.querySelector('#viewer iframe[data-tab-id]');
        try {
            if (isLatex) {
                return await compileLatexInIframe(false);
            }
            if (isMd && iframe?.contentWindow?.document) {
                iframe.contentWindow.document.getElementById('refreshPreviewBtn')?.click();
                return { message: 'Markdown preview refreshed' };
            }
        } catch (e) { logAiError('Compile open document', e); }
        return { message: 'No compile target for open document' };
    }
    async function agentCompileCheck(path, content) {
        const body = new URLSearchParams({
            action: 'ai_agent_tool',
            tool: 'compile_and_check',
            args: JSON.stringify({ path, content }),
            root: getRootForAgentPath(path) || ''
        });
        const res = await fetch(AI_CONFIG.fileApiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) throw new Error(data.error || 'Compile check failed');
        return data;
    }
    async function exportMarkdownPdfForAgent(pathArg) {
        const activeTab = window.tabsState?.tabs?.find((tab) => tab.id === window.tabsState?.activeTabId);
        let path = String(pathArg || activeTab?.path || aiState.currentFileName || '').trim();
        if (!path) return { success: false, error: 'No markdown file path' };
        if (!/\.md$/i.test(path)) return { success: false, error: 'Only .md files support PDF export' };
        if (activeTab && normalizeTreePath(activeTab.path) === normalizeTreePath(path)) {
            await saveActiveEditorToServer();
        }
        const root = activeTab && normalizeTreePath(activeTab.path) === normalizeTreePath(path)
            ? (typeof getTabRoot === 'function' ? getTabRoot(activeTab) : getRootForFileContext())
            : getRootForFileContext();
        const res = await fetch(AI_CONFIG.fileApiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: new URLSearchParams({ action: 'export_pdf', path, root: root || '' })
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.error) {
            return { success: false, error: data.error || `HTTP ${res.status}` };
        }
        if (data.success) {
            const pdfPath = normalizeTreePath(data.pdf_path || path.replace(/\.md$/i, '.pdf'));
            try { await highlightNewFilesInTree([pdfPath], { openLastPdf: true }); } catch (e) {}
            return { success: true, pdf_path: pdfPath, message: pdfPath || 'PDF saved' };
        }
        return { success: false, error: data.error || 'Export failed' };
    }
    async function exportLatexPdfOnServer(path) {
        const res = await fetch(AI_CONFIG.fileApiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: new URLSearchParams({
                action: 'ai_agent_tool',
                tool: 'export_latex_pdf_file',
                args: JSON.stringify({ path }),
                root: getRootForAgentPath(path) || ''
            })
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) {
            return { success: false, error: data.error || `HTTP ${res.status}` };
        }
        if (data.success && data.pdf_path) {
            const pdfPath = normalizeTreePath(data.pdf_path);
            try { await highlightNewFilesInTree([pdfPath], { openLastPdf: true }); } catch (e) {}
            return { ...data, success: true, pdf_path: pdfPath };
        }
        return { ...data, success: false, error: data.error || 'PDF was not created' };
    }
    async function exportLatexPdfForAgent(pathArg) {
        const activeTab = window.tabsState?.tabs?.find((tab) => tab.id === window.tabsState?.activeTabId);
        const path = String(pathArg || activeTab?.path || aiState.currentFileName || '').trim();
        if (!path) return { success: false, error: 'No LaTeX file path' };
        if (!/\.(tex|latex)$/i.test(path)) return { success: false, error: 'Only .tex/.latex files support PDF export' };
        if (!isOpenLatexEditorTab() || normalizeTreePath(activeTab?.path || '') !== normalizeTreePath(path)) {
            return exportLatexPdfOnServer(path);
        }
        const result = await compileLatexInIframe(true);
        if (!result.success || !result.pdf_path) {
            return { success: false, error: result.error || 'PDF was not created' };
        }
        return result;
    }
    async function compileOpenDocumentForAgent(format) {
        const fileData = await loadCurrentFileContent();
        const ext = String(fileData?.name || '').split('.').pop().toLowerCase();
        if (isOpenLatexEditorTab() && ['tex', 'latex'].includes(ext)) {
            const result = await compileLatexInIframe(false);
            notifyEditorCompileStatus(result.success, result.message || result.error || '');
            return result;
        }
        const compileExts = ['tex', 'latex', 'py', 'c', 'cpp', 'java', 'm'];
        if (fileData?.name && compileExts.includes(ext)) {
            const content = getStagedAgentContent(fileData.name) ?? fileData.content ?? '';
            const data = await agentCompileCheck(fileData.name, content);
            const ok = !!data.success;
            const msg = ok ? (data.output || 'Compilation succeeded') : (data.error || 'Compilation failed');
            notifyEditorCompileStatus(ok, msg);
            return { success: ok, output: data.output || '', error: data.error || '', message: msg };
        }
        const fallback = await compileOpenDocument(format);
        notifyEditorCompileStatus(true, fallback?.message || 'OK');
        return { success: true, message: fallback?.message || 'OK' };
    }
    function buildAssistantToolLoopBase(messageWithMentions, contextMessages, openPath = '') {
        const openPathForPrompts = normalizeMentionedFilePath(openPath || aiState.currentFileName || getActiveFilePath() || '');
        const historyMessages = (contextMessages || []).filter((m) => m && (m.role === 'user' || m.role === 'assistant') && typeof m.content === 'string');
        return { openPathForPrompts, historyMessages };
    }

    function buildWorkspaceContextHint(mentionedFiles, mentionedRoots, toolRoot, openPath, attachments) {
        const lines = [];
        const mentions = (mentionedFiles || []).map((p) => normalizeMentionedFilePath(p)).filter(Boolean);
        const activeRoot = String(toolRoot || 'main');
        if (mentions.length) {
            const list = mentions.map((p) => {
                const root = mentionedRoots?.[p] || activeRoot;
                return `${p} (root: ${root})`;
            }).join(', ');
            lines.push(`Workspace files attached with @ (use these paths verbatim in tool calls): ${list}.`);
        }
        const openNorm = normalizeMentionedFilePath(openPath);
        if (openNorm) lines.push(`File open in the editor: ${openNorm} (root: ${activeRoot}).`);
        const attachmentNames = (attachments || []).map((a) => a?.name).filter(Boolean);
        if (attachmentNames.length) {
            lines.push(`Uploaded attachments pasted into this conversation as text (they are NOT workspace paths, do not read or write them): ${attachmentNames.join(', ')}.`);
        }
        const folderSource = mentions[0] || openNorm || '';
        const folder = folderSource.includes('/') ? folderSource.slice(0, folderSource.lastIndexOf('/')) : '';
        lines.push(`Active root for tool calls: ${activeRoot}. Paths are relative to that root, without a leading slash.`);
        lines.push(`Folder for new files unless the user names another: ${folder || '.' } (i.e. ${folder ? folder + '/<name>' : '<name>'}).`);
        lines.push('Decide yourself which of these files the request is about and where new files belong; use exact paths in tool calls.');
        return `\n\n[workspace context]\n${lines.join('\n')}`;
    }

    function getToolLoopHistory(messages, currentTask) {
        const task = String(currentTask || '').trim();
        return (messages || [])
            .filter((m) => m && (m.role === 'system' || m.role === 'user' || m.role === 'assistant') && typeof m.content === 'string')
            .filter((m) => !(m.role === 'user' && String(m.content || '').trim() === task));
    }

    async function sendChatWithTools(messageWithMentions, contextMessages, requestModel, rootOverride = '', pathRoots = null, openPath = '') {
        if (!window.AIAgentSkeleton?.runAgentLoop) {
            throw new Error('Agent tools module is not loaded');
        }
        if (window.AIAgentSkeleton.clearAgentPromptCache) window.AIAgentSkeleton.clearAgentPromptCache();
        const base = buildAssistantToolLoopBase(messageWithMentions, contextMessages, openPath);
        await window.AIAgentSkeleton.loadAgentPrompts(AI_CONFIG.fileApiEndpoint, base.openPathForPrompts, 'chat');
        const userRole = await resolveAgentUserRole();
        const sk = window.AIAgentSkeleton;
        const runRoot = rootOverride || getRootForToolRun();
        const chatResult = await sk.runAgentLoop({
            readOnly: true,
            apiBase: AI_CONFIG.fileApiEndpoint,
            root: runRoot,
            rootForPath: (p) => resolveRootForToolPath(p, pathRoots, runRoot),
            openPath: base.openPathForPrompts,
            defaultPath: base.openPathForPrompts || (Array.isArray(aiState.agentMentionPaths) ? aiState.agentMentionPaths[0] : '') || '',
            userRole,
            userTask: messageWithMentions,
            historyMessages: base.historyMessages,
            maxEditSteps: 16,
            tr: (key, fallback, params) => tr(key, fallback, params),
            toolLabel: (name, args) => agentPlanToolLabel(name, args || {}),
            toolResultSummary: (name, result) => agentPlanResultSummary(name, result),
            signal: aiState.abortController?.signal,
            onStatus: (phase, detail) => {
                if (phase === 'thinking') updateAgentElapsedStreamText();
                else if (phase === 'tool') {
                    const label = agentPlanToolLabel(detail, {}) || detail;
                    setAgentStreamText(`${tr('aiChatTool', 'Step')}: ${label}`);
                }
            },
            onToolStart: (name, detail, args) => {
                const label = agentPlanToolLabel(name, args || {});
                setAgentStreamText(`${tr('aiChatTool', 'Step')}: ${label}`);
                const stepId = 'step_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7);
                upsertAgentPlanStep(stepId, { tool: name, label, status: 'running', result: '' });
                return stepId;
            },
            onToolEnd: (stepId, name, _detail, result) => {
                const summary = agentPlanResultSummary(name, result);
                const isErr = !!(result?.error || result?.success === false);
                if (isErr) logAiError(`Tool ${name} failed`, result?.error || result?.output || result);
                upsertAgentPlanStep(stepId, { status: isErr ? 'error' : 'done', result: isErr && result?.error ? String(result.error) : summary, rawResult: result });
            },
            createCompletion: async ({ messages, tools, tool_choice, forceAnswerOnly }) => {
                const payload = {
                    model: requestModel,
                    messages,
                    max_tokens: 8192,
                    temperature: 0.3
                };
                if (tools && tools.length) {
                    payload.tools = tools;
                    payload.tool_choice = tool_choice || 'auto';
                }
                return fetchAgentCompletionStream(payload, aiState.abortController?.signal, (partial) => {
                    if (partial && partial.trim()) setAgentStreamText(partial);
                });
            },
            hooks: {
                getOpenFile: async () => loadCurrentFileContent(),
                resolveSearchPaths: resolveAgentSearchPaths
            }
        });
        return { content: String(chatResult?.content || '').trim(), usage: chatResult?.usage || null };
    }
    async function sendAgentWithTools(messageWithMentions, contextMessages, requestModel, rootOverride = '', pathRoots = null, openPath = '') {
        if (!window.AIAgentSkeleton?.runAgentLoop) {
            throw new Error('Agent tools module is not loaded');
        }
        if (window.AIAgentSkeleton.clearAgentPromptCache) window.AIAgentSkeleton.clearAgentPromptCache();
        const base = buildAssistantToolLoopBase(messageWithMentions, contextMessages, openPath);
        await window.AIAgentSkeleton.loadAgentPrompts(AI_CONFIG.fileApiEndpoint, base.openPathForPrompts, 'agent');
        const userRole = await resolveAgentUserRole();
        const sk = window.AIAgentSkeleton;
        const runRoot = rootOverride || getRootForToolRun();
        const agentResult = await sk.runAgentLoop({
            apiBase: AI_CONFIG.fileApiEndpoint,
            root: runRoot,
            rootForPath: (p) => resolveRootForToolPath(p, pathRoots, runRoot),
            openPath: base.openPathForPrompts,
            defaultPath: base.openPathForPrompts || (Array.isArray(aiState.agentMentionPaths) ? aiState.agentMentionPaths[0] : '') || '',
            userRole,
            userTask: messageWithMentions,
            historyMessages: base.historyMessages,
            maxEditSteps: 14,
            tr: (key, fallback, params) => tr(key, fallback, params),
            toolLabel: (name, args) => agentPlanToolLabel(name, args || {}),
            toolResultSummary: (name, result) => agentPlanResultSummary(name, result),
            signal: aiState.abortController?.signal,
            onStatus: (phase, detail) => {
                if (phase === 'thinking') {
                    updateAgentElapsedStreamText();
                } else if (phase === 'tool') {
                    const label = agentPlanToolLabel(detail, {}) || detail;
                    setAgentStreamText(`${tr('aiAgentTool', 'Tool')}: ${label}`);
                    flashStatus(`${tr('aiAgentTool', 'Tool')}: ${label}`);
                }
            },
            onToolStart: (name, detail, args) => {
                const label = name === 'ask_user'
                    ? `${tr('aiPlanAskUser', 'Ask user')}: ${String(args?.question || '').slice(0, 80)}`
                    : agentPlanToolLabel(name, args || {});
                setAgentStreamText(`${tr('aiAgentTool', 'Tool')}: ${label}`);
                const stepId = 'step_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7);
                upsertAgentPlanStep(stepId, { tool: name, label, status: 'running', result: '' });
                flashStatus(`${tr('aiAgentTool', 'Tool')}: ${label}`);
                return stepId;
            },
            onToolEnd: (stepId, name, detail, result) => {
                const summary = agentPlanResultSummary(name, result);
                const isErr = !!(result?.error || result?.success === false);
                if (isErr) logAiError(`Tool ${name} failed`, result?.error || result?.output || result);
                let planResult = summary;
                if ((name === 'compile_and_check' || name === 'verify_syntax') && isErr) {
                    planResult = String(result?.error || result?.output || summary).trim();
                } else if (isErr && result?.error) {
                    planResult = String(result.error);
                }
                upsertAgentPlanStep(stepId, { status: isErr ? 'error' : 'done', result: planResult, rawResult: result });
                if ((name === 'export_markdown_pdf' || name === 'export_latex_pdf') && !isErr && result?.pdf_path) {
                    void highlightNewFilesInTree([result.pdf_path], { openLastPdf: true });
                }
                if ((name === 'write_file' || name === 'search_replace' || name === 'create_file' || name === 'copy_file' || name === 'download_file' || name === 'archive_create') && result?.path && result?.persisted) {
                    void highlightNewFilesInTree([{ path: result.path, type: 'file', root: result.root || '' }]);
                }
                if (name === 'make_directory' && !isErr && result?.path) {
                    void highlightNewFilesInTree([{ path: result.path, type: 'dir', root: result.root || '' }]);
                }
                if (name === 'rename_file' && !isErr && result?.path) {
                    void syncTreeForMoveOrDelete([result.path], [result.old_path || '']);
                }
                if (name === 'move_path' && !isErr && result?.path) {
                    void syncTreeForMoveOrDelete([], [result.old_path || '']).then(() => rebuildFileTree());
                }
                if (name === 'delete_file' && !isErr && result?.deleted_path) {
                    void syncTreeForMoveOrDelete([], [result.deleted_path]);
                }
                if (name === 'delete_directory' && !isErr && result?.deleted_path) {
                    void syncTreeForMoveOrDelete([], [result.deleted_path]);
                }
                if (name === 'archive_extract' && !isErr) {
                    void rebuildFileTree();
                }
                if (name === 'compile_and_check' || name === 'compile_open_document') {
                    if (Array.isArray(result?.fileChanges) && typeof window.applyCompileFileChangesToTree === 'function') {
                        void window.applyCompileFileChangesToTree(result.fileChanges, {
                            root: getRootForFileContext(),
                            sourcePath: aiState.currentFileName || ''
                        });
                    }
                }
                if ((name === 'compile_and_check' || name === 'compile_open_document') && !isErr && result?.success !== false) {
                    const ok = true;
                    notifyEditorCompileStatus(ok, result?.message || result?.error || summary);
                    if (isOpenLatexEditorTab()) void compileLatexInIframe(false);
                } else if (name === 'compile_and_check' || name === 'compile_open_document') {
                    notifyEditorCompileStatus(false, result?.message || result?.error || summary);
                }
                if ((name === 'write_file' || name === 'search_replace' || name === 'apply_editor_patch') && (result?.staged || result?.changed)) {
                    const p = result.path || aiState.currentFileName;
                    upsertAgentPlanStep('staged_' + normalizeAgentPath(p), {
                        tool: 'staged',
                        label: agentPlanToolLabel('staged', { path: p }),
                        status: 'done',
                        result: tr('aiAgentPreviewEditor', 'Preview in editor')
                    });
                }
            },
            createCompletion: async ({ messages, tools, tool_choice, forceAnswerOnly }) => {
                const payload = {
                    model: requestModel,
                    messages,
                    max_tokens: 8192,
                    temperature: 0.2
                };
                if (tools && tools.length) {
                    payload.tools = tools;
                    payload.tool_choice = tool_choice || 'auto';
                }
                return fetchAgentCompletionStream(payload, aiState.abortController?.signal, (partial) => {
                    if (partial && partial.trim()) setAgentStreamText(partial);
                });
            },
            hooks: {
                getOpenFile: async () => loadCurrentFileContent(),
                getStagedContent: getStagedAgentContent,
                resolveSearchPaths: resolveAgentSearchPaths,
                onFileStaged: (path, before, after, meta) => stageAgentFileChange(path, before, after, meta),
                saveOpenDocument: async () => {
                    const ok = await saveActiveEditorToServer();
                    return { saved: ok, message: ok ? 'File saved to server' : 'Save failed' };
                },
                applyEditorPatches: async (patches) => {
                    const fileData = await loadCurrentFileContent();
                    if (!fileData?.content && fileData?.content !== '') return { ok: false, error: 'No open file' };
                    const ops = (patches || []).map((p) => ({
                        search: normalizePatchChunk(p?.s || p?.search || ''),
                        replace: normalizePatchChunk(p?.r || p?.replace || '')
                    })).filter((p) => p.search);
                    const applied = applyOpsToText(fileData.content ?? '', ops);
                    if (!applied || applied.text === fileData.content) {
                        return { ok: true, path: fileData.name, changed: false, staged: false };
                    }
                    stageAgentFileChange(fileData.name, fileData.content ?? '', applied.text, { tool: 'apply_editor_patch' });
                    return { ok: true, path: fileData.name, changed: true, staged: true, before: fileData.content, after: applied.text };
                },
                compileOpenDocument: compileOpenDocumentForAgent,
                exportMarkdownPdf: (path) => exportMarkdownPdfForAgent(path),
                exportLatexPdf: (path) => exportLatexPdfForAgent(path),
                askUser: (args) => waitForAgentUserChoice(args?.question, args?.options)
            }
        });
        return {
            content: String(agentResult?.content || '').trim(),
            usage: agentResult?.usage || null
        };
    }
    // FIXED: Main send function with proper streaming and stable rendering
    async function sendAIMessage(options = {}) {
            const messageSource = typeof options.messageOverride === 'string' ? options.messageOverride : elements.input?.value;
            const message = String(messageSource || '').trim();
            if (!message || aiState.isGenerating) return;
            clearEditorHighlights();
            
            await ensureDirectApiKeyLoaded();
            await ensureAiChatSessionForRequest();

        aiState.abortController = new AbortController();

        // UI removes @tokens; prompt must still preserve their positions
        const userFacingMessage = String(options.userFacingMessage || message).trim();
        const messageWithMentions =
            typeof options.messageOverride === 'string'
                ? message
                : rebuildMessageWithMentions(message, aiState.mentionPlacements || []);
        const attachmentsForRequest = Array.isArray(options.attachmentsOverride) ? options.attachmentsOverride : aiState.externalAttachments;
        const mentionedFilesForRequest = Array.isArray(options.mentionedFilesOverride) ? options.mentionedFilesOverride.slice() : (Array.isArray(aiState.selectedMentionFiles) ? aiState.selectedMentionFiles.slice() : []);
        const mentionedRootsForRequest = options.mentionedRootsOverride && typeof options.mentionedRootsOverride === 'object'
            ? { ...options.mentionedRootsOverride }
            : { ...(aiState.mentionedFileRoots || {}) };
        const toolRootForRequest = mentionedFilesForRequest
            .map((p) => mentionedRootsForRequest[normalizeMentionedFilePath(p)] || '')
            .find(Boolean) || getRootForToolRun();

        if (!options.skipUserMessageUI) addMessageToUI('user', userFacingMessage, false);
        if (!options.skipUserInputReset && elements.input) {
            elements.input.value = '';
            aiState.mentionPlacements = [];
            aiState.selectedMentionFiles = [];
            aiState.mentionedFileRoots = {};
            renderMentionedAttachments();
            removeAllExternalAttachments();
        }

        showTypingIndicator();
        hideMentionSuggestions();

        aiState.isGenerating = true;
        setInputState(true);
        setSendButtonGenerating(true);
        
        const startTime = Date.now();
        let tokenCount = 0;
        let lastFullResponse = '';
        let streamMessageId = '';
        let streamModelName = '';
        const completionNotify = { mode: 'chat', outcome: 'success', skip: false };
        const shouldUseServerWorker = aiState.chatMode === 'agent' && !window.AIAgentSkeleton?.runAgentLoop && options.useServerWorker !== false;
        if (shouldUseServerWorker) {
            const requestModel = resolveModelForRequest();
            const workerMessageId = 'msg_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
            removeTypingIndicator();
            addMessageToUI('assistant', '', false, workerMessageId, false);
            try {
                const started = await startServerAiRun({
                    message: messageWithMentions,
                    userFacingMessage,
                    model: requestModel,
                    role: elements.roleSelect?.value || aiState.selectedRole || AI_CONFIG.defaultRole,
                    chatMode: aiState.chatMode || 'chat',
                    root: toolRootForRequest,
                    mentionedFiles: mentionedFilesForRequest,
                    mentionedRoots: mentionedRootsForRequest,
                    attachments: attachmentsForRequest
                });
                aiState.serverRunJobId = started.job_id || '';
                aiState.serverRunMessageId = workerMessageId;
                aiState.serverRunStopping = false;
                flashStatus(tr('aiAgentWorkerStarted', 'Assistant worker started'));
                await pollServerAiRun(started.job_id, workerMessageId, startTime, userFacingMessage, requestModel);
            } catch (err) {
                if (err.name === 'AbortError') {
                    completionNotify.skip = true;
                    flashStatus(tr('aiAgentInterrupted', 'Assistant action interrupted'));
                } else {
                    completionNotify.outcome = 'error';
                    addMessageToUI('error', err.message || String(err));
                }
                removeTypingIndicator();
            } finally {
                aiState.isGenerating = false;
                aiState.currentMessageId = null;
                aiState.abortController = null;
                aiState.serverRunJobId = '';
                aiState.serverRunMessageId = '';
                aiState.serverRunStopping = false;
                setInputState(false);
                setSendButtonGenerating(false);
                if (options.cleanupAttachments !== false) removeAllExternalAttachments();
                maybeShowAiCompletionNotification(completionNotify);
            }
            return;
        }
        
        try {
            const fileData = await loadCurrentFileContent();
            const openDocPath = fileData?.name || aiState.currentFileName || document.getElementById('viewer')?.dataset?.path || '';
            if (isExtractableDocumentPath(openDocPath) && !(fileData?.content || '').trim()) {
                flashStatus(tr('aiDocumentExtractEmpty', 'Could not extract text from the open document. Try read_file in chat/agent mode.'));
            }
            const mentionedContext = await loadMentionedFilesContext(messageWithMentions, fileData?.name || aiState.currentFileName, mentionedFilesForRequest, mentionedRootsForRequest);
            if (Array.isArray(mentionedContext.resolvedPaths) && mentionedContext.resolvedPaths.length) {
                mentionedFilesForRequest.length = 0;
                mentionedContext.resolvedPaths.forEach((item) => {
                    const p = normalizeMentionedFilePath(item.path);
                    if (!p) return;
                    mentionedFilesForRequest.push(p);
                    if (item.root) mentionedRootsForRequest[p] = item.root;
                });
            }
            const resolvedToolRoot = mentionedFilesForRequest
                .map((p) => mentionedRootsForRequest[normalizeMentionedFilePath(p)] || '')
                .find(Boolean) || toolRootForRequest;

            const messages = [];
            const role = elements.roleSelect?.value || AI_CONFIG.defaultRole;

            const isAgentMode = aiState.chatMode === 'agent' && isAgentEditorSupported();
            const useAgentTools = isAgentMode && !!window.AIAgentSkeleton?.runAgentLoop;
            const useChatTools = !useAgentTools && !!window.AIAgentSkeleton?.runAgentLoop;
            const useFileToolContext = useAgentTools || useChatTools;
            const openDocForTools = normalizeMentionedFilePath(
                mentionedFilesForRequest[0] || fileData?.name || aiState.currentFileName || openDocPath || ''
            );
            completionNotify.mode = useAgentTools ? 'agent' : 'chat';
            if (!isAgentMode && role !== 'general') {
                messages.push({
                    role: 'system',
                    content: roleConfigs[role]?.system || roleConfigs.general.system
                });
            }

            if (mentionedContext.files.length > 0) {
                const filesPayload = mentionedContext.files
                    .map((file) => {
                        const body = useFileToolContext
                            ? truncateForAgentContext(file.content, 6000, file.name)
                            : file.content;
                        return `[Referenced file: ${file.name}]\n${body}`;
                    })
                    .join('\n\n---\n\n');

                messages.push({
                    role: 'user',
                    content: `Additional referenced files from the same folder:\n\n${filesPayload}`
                });
            }
            
            if (!options.skipAttachmentsContext && attachmentsForRequest.length > 0) {
                const extBlocks = attachmentsForRequest
                    .map((a) => `[Attached external file: ${a.name}]\n\n${a.content || ''}`)
                    .join('\n\n---\n\n');
                messages.push({
                    role: 'user',
                    content: extBlocks
                });
            }

            // Include last exchange if enabled
            const recent = buildRecentChatContext();
            for (const r of recent) {
                if (typeof r.content === 'string' && r.content.trim()) {
                    messages.push({ role: r.role, content: r.content });
                }
            }

            const workspaceHint = buildWorkspaceContextHint(mentionedFilesForRequest, mentionedRootsForRequest, resolvedToolRoot, openDocForTools, attachmentsForRequest);
            const userContentWithMentions = messageWithMentions + (useFileToolContext ? workspaceHint : '');
            messages.push({ role: 'user', content: userContentWithMentions });

            if (useAgentTools) {
                const requestModel = resolveModelForRequest();
                const agentMessageId = 'msg_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
                const mentionPaths = extractMentionedFileNames(messageWithMentions);
                mentionedFilesForRequest.forEach((p) => mentionPaths.push(p));
                if (fileData?.name) mentionPaths.push(fileData.name);
                removeTypingIndicator();
                addMessageToUI('assistant', '', false, agentMessageId, false);
                resetAgentRun(agentMessageId, mentionPaths, 'agent');
                applyToolRunRoleToMessage(agentMessageId);
                let runOutcome = 'success';
                try {
                    const agentResult = await sendAgentWithTools(userContentWithMentions, getToolLoopHistory(messages, userContentWithMentions), requestModel, resolvedToolRoot, mentionedRootsForRequest, openDocForTools);
                    const agentText = String(agentResult?.content || '').trim();
                    const elapsed = (Date.now() - startTime) / 1000;
                    const stats = buildAgentStats(elapsed, agentResult?.usage, agentText, messageWithMentions);
                    const msgEl = document.getElementById(agentMessageId);
                    if (msgEl) {
                        applyToolRunRoleToMessage(agentMessageId);
                        const answerHost = msgEl.querySelector('.ai-agent-final-answer') || msgEl.querySelector('.message-content');
                        if (answerHost) {
                            answerHost.replaceChildren();
                            answerHost.appendChild(formatResponseInner(agentText || tr('aiEmptyResponse', 'Empty response')));
                            renderMath(answerHost);
                            renderAgentStagedSummary(answerHost);
                        }
                        renderAgentPlanInMessage();
                        appendMessageStatsFooter(msgEl, stats, agentText);
                        const hadStaged = Object.keys(aiState.pendingAgentPatches).length > 0;
                        if (hadStaged) {
                            const undoSnapshot = snapshotAgentPatchesForUndo();
                            try {
                                await persistAgentWorkAfterRun();
                                aiState.lastAgentUndo = { messageId: agentMessageId, paths: undoSnapshot };
                                upsertAgentPlanStep('persist_' + agentMessageId, {
                                    tool: 'save',
                                    label: tr('aiAgentSaveServer', 'Save to server'),
                                    status: 'done',
                                    result: tr('aiAgentSavedShort', 'Saved')
                                });
                                renderAgentPlanInMessage();
                                attachAgentUndoFooter(msgEl, agentMessageId);
                                flashStatus(tr('aiAgentSaved', 'Agent changes saved to disk'));
                            } catch (saveErr) {
                                upsertAgentPlanStep('persist_' + agentMessageId, {
                                    tool: 'save',
                                    label: tr('aiAgentSaveServer', 'Save to server'),
                                    status: 'error',
                                    result: saveErr.message || 'Save failed'
                                });
                                attachAgentConfirmFooter(msgEl, agentMessageId);
                                setTimeout(async () => {
                                    if (!Object.keys(aiState.pendingAgentPatches).length || isConfirmDialogOpen()) return;
                                    const ok = await showConfirmDialog(
                                        escapeHtml(tr('aiAgentConfirmTitle', 'Apply assistant changes?')),
                                        escapeHtml(saveErr.message || tr('aiAgentConfirmMessage', 'The assistant staged file changes but could not save them automatically. Apply them now?'))
                                    );
                                    if (ok && Object.keys(aiState.pendingAgentPatches).length > 0) {
                                        try {
                                            await persistAgentWorkAfterRun();
                                            aiState.lastAgentUndo = { messageId: agentMessageId, paths: undoSnapshot };
                                            attachAgentUndoFooter(msgEl, agentMessageId);
                                        } catch (e) {
                                            flashStatus(e.message || 'Save failed');
                                        }
                                    }
                                }, 0);
                            }
                        }
                        hideAgentStreamShell();
                        renderAgentPlanInMessage();
                    } else {
                        addMessageToUI('assistant', agentText || tr('aiEmptyResponse', 'Empty response'));
                    }
                    const metaJson = buildAgentMetaJson(agentText, attachmentsForRequest, mentionedFilesForRequest);
                    const agentMeta = parseAgentMetaJson(metaJson);
                    const currentFile = document.getElementById('viewer')?.dataset.path || fileData?.name || '';
                    await appendChatPairToDb(userFacingMessage, agentText, requestModel, currentFile, stats, metaJson);
                    aiState.chatHistory.push({ id: agentMessageId + '_u', role: 'user', content: userFacingMessage, timestamp: new Date().toISOString(), fileName: currentFile, attachments: serializePendingAttachments(attachmentsForRequest), mentionedFiles: mentionedFilesForRequest.slice() });
                    aiState.chatHistory.push({
                        id: agentMessageId,
                        role: 'assistant',
                        content: agentText,
                        timestamp: new Date().toISOString(),
                        fileName: currentFile,
                        stats,
                        isAgent: true,
                        agentMeta
                    });
                    saveChatHistory();
                } catch (agentErr) {
                    hideAgentStreamShell();
                    removeTypingIndicator();
                    discardAgentPreviewState();
                    if (agentErr?.code === 'agent_interrupted_by_user') {
                        runOutcome = 'stopped';
                        completionNotify.skip = true;
                        flashStatus(tr('aiAgentInterrupted', 'Assistant action interrupted'));
                    } else if (agentErr.name === 'AbortError') {
                        runOutcome = 'stopped';
                        completionNotify.skip = true;
                    } else {
                        runOutcome = 'error';
                        completionNotify.outcome = 'error';
                        addMessageToUI('error', agentErr.message || String(agentErr));
                    }
                } finally {
                    cleanupAgentAskUser();
                    aiState.isGenerating = false;
                    setInputState(false);
                    setSendButtonGenerating(false);
                    finishAgentRun(runOutcome);
                    aiState.abortController = null;
                    maybeShowAiCompletionNotification(completionNotify);
                }
                return;
            }

            if (useChatTools) {
                const requestModel = resolveModelForRequest();
                const chatMessageId = 'msg_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
                const mentionPaths = extractMentionedFileNames(messageWithMentions);
                mentionedFilesForRequest.forEach((p) => mentionPaths.push(p));
                if (fileData?.name) mentionPaths.push(fileData.name);
                removeTypingIndicator();
                addMessageToUI('assistant', '', false, chatMessageId, false);
                resetAgentRun(chatMessageId, mentionPaths, 'chat');
                applyToolRunRoleToMessage(chatMessageId);
                let runOutcome = 'success';
                try {
                    const chatResult = await sendChatWithTools(userContentWithMentions, getToolLoopHistory(messages, userContentWithMentions), requestModel, resolvedToolRoot, mentionedRootsForRequest, openDocForTools);
                    let chatText = String(chatResult?.content || '').trim();
                    if (!chatText && aiState.agentPlanSteps.some((s) => s.status === 'done')) {
                        chatText = tr('aiChatAnswerFailed', 'The model did not produce a text answer after reading the file. Try again or narrow your request.');
                    }
                    const elapsed = (Date.now() - startTime) / 1000;
                    const stats = buildAgentStats(elapsed, chatResult?.usage, chatText, messageWithMentions);
                    const msgEl = document.getElementById(chatMessageId);
                    if (msgEl) {
                        applyToolRunRoleToMessage(chatMessageId);
                        const answerHost = msgEl.querySelector('.ai-agent-final-answer') || msgEl.querySelector('.message-content');
                        if (answerHost) {
                            answerHost.replaceChildren();
                            answerHost.appendChild(formatResponseInner(chatText || tr('aiEmptyResponse', 'Empty response')));
                            renderMath(answerHost);
                        }
                        renderAgentPlanInMessage();
                        appendMessageStatsFooter(msgEl, stats, chatText);
                        hideAgentStreamShell();
                        renderAgentPlanInMessage();
                    } else {
                        addMessageToUI('assistant', chatText || tr('aiEmptyResponse', 'Empty response'));
                    }
                    const currentFile = document.getElementById('viewer')?.dataset.path || fileData?.name || '';
                    await appendChatPairToDb(userFacingMessage, chatText, requestModel, currentFile, stats, buildChatMetaJson(attachmentsForRequest, mentionedFilesForRequest));
                    aiState.chatHistory.push({ id: chatMessageId + '_u', role: 'user', content: userFacingMessage, timestamp: new Date().toISOString(), fileName: currentFile, attachments: serializePendingAttachments(attachmentsForRequest), mentionedFiles: mentionedFilesForRequest.slice() });
                    aiState.chatHistory.push({
                        id: chatMessageId,
                        role: 'assistant',
                        content: chatText,
                        timestamp: new Date().toISOString(),
                        fileName: currentFile,
                        stats
                    });
                    saveChatHistory();
                } catch (chatErr) {
                    hideAgentStreamShell();
                    removeTypingIndicator();
                    if (chatErr?.code === 'agent_interrupted_by_user') {
                        runOutcome = 'stopped';
                        completionNotify.skip = true;
                        flashStatus(tr('aiAgentInterrupted', 'Assistant action interrupted'));
                    } else if (chatErr.name === 'AbortError') {
                        runOutcome = 'stopped';
                        completionNotify.skip = true;
                    } else {
                        runOutcome = 'error';
                        completionNotify.outcome = 'error';
                        addMessageToUI('error', chatErr.message || String(chatErr));
                    }
                } finally {
                    cleanupAgentAskUser();
                    aiState.isGenerating = false;
                    setInputState(false);
                    setSendButtonGenerating(false);
                    finishAgentRun(runOutcome);
                    aiState.abortController = null;
                    maybeShowAiCompletionNotification(completionNotify);
                }
                return;
            }

            if (mentionedContext.skipped.length > 0) {
                const skippedNames = mentionedContext.skipped.map(item => item.name).join(', ');
                flashStatus(tr('aiFilesSkipped', 'Some @files were skipped: {names}', { names: skippedNames }));
            } else if (mentionedContext.files.length > 0) {
                flashStatus(tr('aiFilesAdded', 'Added {count} @file(s) to context', { count: mentionedContext.files.length }));
            }

            const requestModel = resolveModelForRequest();
            const response = await fetchAiCompletions({
                model: requestModel,
                messages: messages,
                max_tokens: 4096,
                temperature: 0.7,
                stream: true
            }, aiState.abortController.signal);
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                const apiErrorMessage = errorData.error?.message || errorData?.message || `HTTP error ${response.status}`;
                if (shouldMarkModelUnavailable(apiErrorMessage)) markModelUnavailable(requestModel);
                throw new Error(errorData.error?.message || `HTTP error ${response.status}`);
            }
            
            const messageId = 'msg_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            aiState.currentMessageId = messageId;
            streamMessageId = messageId;
            
            // Create message container
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message ai-message';
            messageDiv.id = messageId;
            
            const header = document.createElement('div');
            header.className = 'message-header';
            const modelName = getCurrentModelDisplayName();
            streamModelName = modelName;
            header.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"/></svg>
                <span class="message-role">${modelName}</span>
            `;
            messageDiv.appendChild(header);
            
            const contentContainer = document.createElement('div');
            contentContainer.className = 'message-content';
            messageDiv.appendChild(contentContainer);
            
            elements.chat.appendChild(messageDiv);
            
            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let fullResponse = '';
            let buffer = '';
            let thinkBuffer = '';
            let inThinkBlock = false;
            let liveThinkBlocks = new Map();
            let currentThinkDiv = null;
            let gotFirstToken = false;
            let streamError = null;
            
            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                
                buffer += decoder.decode(value, { stream: true });
                
                const lines = buffer.split('\n');
                buffer = lines.pop() || '';
                
                for (const line of lines) {
                    if (line.startsWith('data: ')) {
                        const data = line.slice(6);
                        if (data === '[DONE]') continue;
                        
                        try {
                            const parsed = JSON.parse(data);
                            if (parsed.error) {
                                throw new Error(parsed.error.message || parsed.error || 'AI request failed');
                            }
                            const delta = parsed.choices[0]?.delta;
                            const content = delta?.content || delta?.text || '';
                            
                            if (content) {
                                if (!gotFirstToken) {
                                    gotFirstToken = true;
                                    removeTypingIndicator();
                                }
                                fullResponse += content;
                                lastFullResponse = fullResponse;
                                tokenCount = estimateTokens(fullResponse);
                                
                                if (content.includes('<think>')) {
                                    inThinkBlock = true;
                                    thinkBuffer = '';
                                    currentThinkDiv = createThinkBlock('', true);
                                    liveThinkBlocks.set(thinkBuffer, currentThinkDiv);
                                }
                                
                                if (inThinkBlock) {
                                    thinkBuffer += content;
                                    if (currentThinkDiv) {
                                        updateThinkBlockLive(currentThinkDiv, thinkBuffer.replace(/<\/?think>/g, ''));
                                    }
                                }
                                
                                if (content.includes('</think>')) {
                                    inThinkBlock = false;
                                    if (currentThinkDiv) {
                                        const finalContent = thinkBuffer.replace(/<\/?think>/g, '');
                                        updateThinkBlockLive(currentThinkDiv, finalContent);
                                        const liveIndicator = currentThinkDiv.querySelector('.think-live-indicator');
                                        if (liveIndicator) liveIndicator.remove();
                                    }
                                    currentThinkDiv = null;
                                }
                                
                                const newInner = formatResponseInner(fullResponse, liveThinkBlocks);
                                contentContainer.replaceChildren(newInner);
                                renderMath(contentContainer);
                                
                                const wasAtBottom = elements.chat.scrollHeight - elements.chat.scrollTop <= elements.chat.clientHeight + 100;
                                if (wasAtBottom) {
                                    scrollChatToBottom();
                                }
                // Keep sticky header positions updated while streaming
                try { elements.chat?._stickyHandler?.(); } catch (e) {}
                            }
                        } catch (e) {
                            streamError = e;
                            console.warn('Failed to parse chunk:', line, e);
                        }
                    }
                    if (streamError) break;
                }
                if (streamError) break;
            }
            if (streamError) throw streamError;
            if (!String(fullResponse || '').trim()) {
                throw new Error('Empty AI response');
            }

            if (isAgentMode) {
                const ops = parseAgentPatch(fullResponse);
                if (ops.length > 0) {
                    const beforeText = String(fileData?.content || '');
                    const appliedPreview = applyOpsToText(beforeText, ops);
                    aiState.pendingAgentPatch = { id: messageId, ops, beforeText, afterText: appliedPreview?.text || '', previewApplied: false };
                    contentContainer.replaceChildren(
                        appliedPreview
                            ? createAgentPatchPreviewElement(beforeText, appliedPreview.text)
                            : createAgentPatchPreviewElement(null, null, ops)
                    );
                    if (appliedPreview) {
                        aiState.pendingAgentPatch.previewApplied = showAgentPreviewInEditor(beforeText, appliedPreview.text);
                    }
                    const footer = document.createElement('div');
                    footer.className = 'message-footer';
                    const actions = document.createElement('div');
                    actions.className = 'message-actions';
                    const confirmBtn = document.createElement('button');
                    confirmBtn.className = 'action-button ai-agent-confirm-btn';
                    confirmBtn.textContent = 'Confirm';
                    const undoBtn = document.createElement('button');
                    undoBtn.className = 'action-button ai-agent-undo-btn';
                    undoBtn.textContent = 'Undo';
                    undoBtn.disabled = !appliedPreview;
                    actions.appendChild(confirmBtn);
                    actions.appendChild(undoBtn);
                    footer.appendChild(actions);
                    messageDiv.appendChild(footer);
                    messageDiv.dataset.agentPatchId = messageId;
                    messageDiv.dataset.agentApplied = '0';
                }
            }
            const endTime = Date.now();
            const timeSeconds = (endTime - startTime) / 1000;
            
            const stats = {
                tokens: tokenCount,
                time: timeSeconds,
                speed: tokenCount / timeSeconds
            };
            
            aiState.lastMessageStats = stats;
            
            if (!messageDiv.dataset.agentPatchId) {
            const footer = document.createElement('div');
            footer.className = 'message-footer';
            
            const actions = document.createElement('div');
            actions.className = 'message-actions';
            
            const copyBtn = createCopyButton(fullResponse, true);
            actions.appendChild(copyBtn);
            
            const regenerateBtn = document.createElement('button');
            regenerateBtn.className = 'action-button';
            regenerateBtn.setAttribute('aria-label', tr('regenerateResponse', 'Regenerate response'));
            regenerateBtn.title = tr('regenerateResponse', 'Regenerate response');
            regenerateBtn.innerHTML = `
                <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path d="M1 4v6h6M23 20v-6h-6" fill="none" />
                    <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15" fill="none" />
                </svg>
            `;
            regenerateBtn.onclick = () => regenerateResponse(messageId);
            actions.appendChild(regenerateBtn);
            
            footer.appendChild(actions);
            messageDiv.appendChild(footer);
            }
            if (!messageDiv.dataset.agentPatchId) renderMath(contentContainer);
            
            // Re-attach sticky scroll handler now that new code blocks exist
            setupStickyCodeHeaders();
            // Trigger one immediate calculation
            try { elements.chat?._stickyHandler?.(); } catch (e) {}

            const currentFile = document.getElementById('viewer')?.dataset.path;

            await appendChatPairToDb(userFacingMessage, fullResponse, modelName, currentFile || '', stats, buildChatMetaJson(attachmentsForRequest, mentionedFilesForRequest));

            // Keep minimal local history for "remember previous" feature
            aiState.chatHistory.push({ id: messageId + '_u', role: 'user', content: userFacingMessage, timestamp: new Date().toISOString(), fileName: currentFile, attachments: serializePendingAttachments(attachmentsForRequest), mentionedFiles: mentionedFilesForRequest.slice() });
            aiState.chatHistory.push({ id: messageId, role: 'assistant', content: fullResponse, timestamp: new Date().toISOString(), fileName: currentFile, stats: stats });
            saveChatHistory();
            
        } catch (err) {
            removeTypingIndicator();
            
            if (err.name === 'AbortError') {
                completionNotify.skip = true;
                console.log('Request aborted');

                // Persist partial response if we have any content
                const currentFile = document.getElementById('viewer')?.dataset.path || '';
                if (lastFullResponse && lastFullResponse.trim()) {
                    try {
                        const elapsed = (Date.now() - startTime) / 1000;
                        const partialTokens = estimateTokens(lastFullResponse);
                        const partialStats = {
                            time: elapsed,
                            tokens: partialTokens,
                            speed: elapsed > 0 ? (partialTokens / elapsed) : 0
                        };
                        await appendChatPairToDb(userFacingMessage, lastFullResponse, streamModelName || 'AI', currentFile, partialStats);
                    } catch (e) { logAiError('Persist partial response', e); }

                    // Add a minimal footer if missing
                    const msgEl = streamMessageId ? document.getElementById(streamMessageId) : null;
                    if (msgEl && !msgEl.querySelector('.message-footer')) {
                        const footer = document.createElement('div');
                        footer.className = 'message-footer';

                        const actions = document.createElement('div');
                        actions.className = 'message-actions';
                        actions.appendChild(createCopyButton(lastFullResponse, true));

                        const regen = document.createElement('button');
                        regen.className = 'action-button';
                        regen.setAttribute('aria-label', tr('regenerateResponse', 'Regenerate response'));
                        regen.title = tr('regenerateResponse', 'Regenerate response');
                        regen.innerHTML = `
                            <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path d="M1 4v6h6M23 20v-6h-6" fill="none" />
                                <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15" fill="none" />
                            </svg>
                        `;
                        regen.onclick = () => regenerateResponse(streamMessageId);
                        actions.appendChild(regen);

                        footer.appendChild(actions);
                        msgEl.appendChild(footer);
                    }
                } else {
                    // No content received -> remove empty assistant message if it exists
                    const msgEl = streamMessageId ? document.getElementById(streamMessageId) : null;
                    if (msgEl) msgEl.remove();
                }
            } else {
                completionNotify.outcome = 'error';
                console.error('AI error:', err);
                if (shouldMarkModelUnavailable(err?.message || '')) markModelUnavailable(resolveModelForRequest());
                
                const errorId = addMessageToUI('assistant', 
                    `${window.i18n?.errorPrefix || 'Error: '}${err.message}`, 
                    false
                );
                
                addRetryButton(errorId, message);
            }
        } finally {
            aiState.isGenerating = false;
            aiState.currentMessageId = null;
            aiState.abortController = null;
            setInputState(false);
            setSendButtonGenerating(false);
            if (options.cleanupAttachments !== false) removeAllExternalAttachments();
            
            flashStatus(tr('aiResponseReady', 'AI response is ready'));
            maybeShowAiCompletionNotification(completionNotify);
        }
    }

    function setInputState(disabled) {
        if (elements.input) elements.input.disabled = false;
        // Keep send button clickable while generating (acts as Stop)
        if (elements.sendBtn) elements.sendBtn.disabled = false;
        if (elements.attachFileBtn) elements.attachFileBtn.disabled = false;
        if (elements.settingsBtn) elements.settingsBtn.disabled = false;
    }

    function addRetryButton(messageId, originalMessage) {
        const messageElement = document.getElementById(messageId);
        if (!messageElement) return;
        
        const footer = document.createElement('div');
        footer.className = 'message-footer';
        
        const retryBtn = document.createElement('button');
        retryBtn.className = 'action-button';
        retryBtn.innerHTML = `
            <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M1 4v6h6M23 20v-6h-6" fill="none" />
                <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15" fill="none" />
            </svg>
            <span>${window.i18n?.retry || 'retry'}</span>
        `;
        
        retryBtn.onclick = async () => {
            retryBtn.disabled = true;
            retryBtn.innerHTML = `
                <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="spinning">
                    <path d="M1 4v6h6M23 20v-6h-6" fill="none" />
                    <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15" fill="none" />
                </svg>
                <span>${window.i18n?.retrying || 'retrying...'}</span>
            `;
            
            messageElement.remove();
            elements.input.value = originalMessage;
            await sendAIMessage();
        };
        
        footer.appendChild(retryBtn);
        messageElement.appendChild(footer);
    }

    async function regenerateResponse(messageId) {
        if (aiState.isGenerating) return;
        
        const messageElement = document.getElementById(messageId);
        if (!messageElement) return;
        
        const messages = Array.from(elements.chat?.children || []);
        const currentIndex = messages.findIndex(msg => msg.id === messageId);
        
        if (currentIndex === -1) return;
        
        let userMessage = null;
        for (let i = currentIndex - 1; i >= 0; i--) {
            if (messages[i].classList.contains('user-message')) {
                const contentDiv = messages[i].querySelector('.message-content');
                userMessage = contentDiv?.textContent || '';
                break;
            }
        }
        
        if (!userMessage) {
            flashStatus(window.i18n?.noUserMessageFound || 'No user message found');
            return;
        }
        
        messageElement.remove();
        
        aiState.chatHistory = aiState.chatHistory.filter(msg => msg.id !== messageId && msg.id !== `${messageId}_u`);
        aiState.processedMessageIds.delete(messageId);
        saveChatHistory();
        if (elements.input) elements.input.value = '';
        
        await sendAIMessage({
            messageOverride: userMessage,
            userFacingMessage: userMessage,
            skipUserInputReset: true,
            skipUserMessageUI: true
        });
    }

    async function loadCurrentFileContent() {
        let attemptedActiveApiPath = null;
        let attemptedActiveApiRoot = null;

        if (window.tabsState?.activeTabId && window.tabsState?.tabs) {
            const activeTab = window.tabsState.tabs.find(tab => tab.id === window.tabsState.activeTabId);
            if (activeTab) {
                aiState.currentFileName = activeTab.path;
                const activeRoot = getRootForFileContext(activeTab);

                if (activeTab.markdownEditor || activeTab.latexEditor) {
                    const iframeEditorContent = getIframeEditorContent(activeTab.id);
                    if (iframeEditorContent != null) {
                        aiState.currentFileContent = iframeEditorContent;
                        return { name: aiState.currentFileName, content: aiState.currentFileContent };
                    }
                }
                
                if (window.monacoManager) {
                    const monacoContent = window.monacoManager.getContent(activeTab.id);
                    if (monacoContent && monacoContent !== '') {
                        aiState.currentFileContent = monacoContent;
                        return { name: aiState.currentFileName, content: aiState.currentFileContent };
                    }
                }

                if (window.editorsMap?.has(activeTab.id)) {
                    const editor = window.editorsMap.get(activeTab.id);
                    if (editor && editor.getValue) {
                        aiState.currentFileContent = editor.getValue();
                        return { name: aiState.currentFileName, content: aiState.currentFileContent };
                    }
                }

                if (activeTab.content) {
                    aiState.currentFileContent = activeTab.content;
                    return { name: aiState.currentFileName, content: aiState.currentFileContent };
                }

                attemptedActiveApiPath = activeTab.path;
                attemptedActiveApiRoot = activeRoot;
                const apiContent = await fetchFileContentFromApi(activeTab.path, activeRoot);
                if (apiContent?.content) {
                    aiState.currentFileContent = apiContent.content;
                    return { name: aiState.currentFileName, content: aiState.currentFileContent };
                }
                if (isExtractableDocumentPath(activeTab.path)) {
                    const scraped = scrapeTextFromPresentationViewer();
                    if (scraped) {
                        aiState.currentFileContent = scraped;
                        return { name: aiState.currentFileName, content: scraped, scrapedFromViewer: true };
                    }
                }
            }
        }

        const viewer = document.getElementById('viewer');
        if (viewer?.dataset.path) {
            aiState.currentFileName = viewer.dataset.path;
            const viewerRoot = attemptedActiveApiRoot || getRootForFileContext();

            if (viewer.dataset.markdownEditor === 'true' || viewer.dataset.latexEditor === 'true') {
                const iframeEditorContent = getIframeEditorContent(window.tabsState?.activeTabId);
                if (iframeEditorContent != null) {
                    aiState.currentFileContent = iframeEditorContent;
                    return { name: aiState.currentFileName, content: aiState.currentFileContent };
                }
            }

            if (viewer.monacoEditor && viewer.monacoEditor.getValue) {
                aiState.currentFileContent = viewer.monacoEditor.getValue();
                return { name: aiState.currentFileName, content: aiState.currentFileContent };
            }

            const monacoContainer = viewer.querySelector('.monaco-editor-container');
            if (monacoContainer && window.monacoManager && window.tabsState?.activeTabId) {
                const monacoContent = window.monacoManager.getContent(window.tabsState.activeTabId);
                if (monacoContent && monacoContent !== '') {
                    aiState.currentFileContent = monacoContent;
                    return { name: aiState.currentFileName, content: aiState.currentFileContent };
                }
            }

            const pre = viewer.querySelector('pre');
            if (pre) {
                aiState.currentFileContent = pre.textContent || '';
                return { name: aiState.currentFileName, content: aiState.currentFileContent };
            }

            const apiContent = attemptedActiveApiPath === viewer.dataset.path && attemptedActiveApiRoot === viewerRoot
                ? null
                : await fetchFileContentFromApi(viewer.dataset.path, viewerRoot);
            if (apiContent?.content) {
                aiState.currentFileContent = apiContent.content;
                return { name: aiState.currentFileName, content: aiState.currentFileContent };
            }
            if (isExtractableDocumentPath(viewer.dataset.path)) {
                const scraped = scrapeTextFromPresentationViewer();
                if (scraped) {
                    aiState.currentFileContent = scraped;
                    return { name: aiState.currentFileName, content: scraped, scrapedFromViewer: true };
                }
            }
        }

        return null;
    }

    async function fixProblems() {
        const fileData = await loadCurrentFileContent();
        if (!fileData) {
            flashStatus(window.i18n?.noFileOpen || 'No file open');
            return;
        }
        
        const prompt = (window.i18n?.fixProblemsPrompt || 'Fix problems in this code:\n\n') + fileData.content;
        
        if (elements.input) {
            elements.input.value = prompt;
            await sendAIMessage();
        }
    }

    async function calculateData() {
        const fileData = await loadCurrentFileContent();
        if (!fileData) {
            flashStatus(window.i18n?.noFileOpen || 'No file open');
            return;
        }
        
        const prompt = (window.i18n?.calculateDataPrompt || 'Calculate/analyze this data:\n\n') + fileData.content;
        
        if (elements.input) {
            elements.input.value = prompt;
            await sendAIMessage();
        }
    }

    function attachExternalFile(content, filename) {
        if (aiState.externalAttachments.length >= MAX_EXTERNAL_ATTACHMENTS) {
            flashStatus(tr('aiAttachLimit', 'You can attach up to {n} files', { n: MAX_EXTERNAL_ATTACHMENTS }));
            return;
        }
        const id = makeAttachmentId();
        aiState.externalAttachments.push({
            id,
            kind: 'text',
            name: filename,
            content: typeof content === 'string' ? content : ''
        });
        renderExternalAttachmentsUI();
        saveUserSettingsToDb();
    }

    function updateQuickActions() {
        updateAgentModeAvailability();
    }

    function isTextFile(path) {
        if (!path) return false;
        const ext = path.split('.').pop().toLowerCase();
        const textExts = ['txt', 'md', 'js', 'ts', 'json', 'html', 'css', 'php', 'py', 'rb', 'go', 'java', 'cs', 'cpp', 'c', 'h', 'hpp', 'sh', 'yml', 'yaml', 'sql', 'kt', 'swift', 'rs', 'lua', 'pl', 'r', 'toml', 'ini', 'dockerfile', 'vue', 'm', 'tex', 'latex'];
        return textExts.includes(ext);
    }

    function showConfirmDialog(title, message) {
        return new Promise((resolve) => {
            const aiModal = document.getElementById('ai-modal');
            const aiContent = aiModal?.querySelector('.ai-modal-content');

            const overlay = document.createElement('div');
            overlay.className = 'ai-confirm-overlay';
            overlay.style.cssText = `
                position: absolute;
                inset: 0;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(8px) saturate(1.1);
                -webkit-backdrop-filter: blur(8px) saturate(1.1);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 23000;
                animation: ai-fadeIn 0.2s ease;
            `;

            const modal = document.createElement('div');
            modal.className = 'ai-confirm-dialog';
            modal.style.cssText = `
                background: var(--panel, #f4f6f8);
                border: 1px solid var(--border, #e0e3e7);
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
                max-width: 380px;
                width: 90%;
                color: var(--fg, #111111);
                animation: ai-scaleIn 0.2s ease;
            `;

            modal.innerHTML = `
                <div style="padding: 20px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                        <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: var(--fg, #111111);">${title}</h3>
                        <button class="ai-confirm-close" style="background: none; border: none; color: var(--muted, #666666); font-size: 24px; cursor: pointer; padding: 0; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 6px; transition: background 0.15s, color 0.15s;">&times;</button>
                    </div>
                    <div style="color: var(--fg, #111111); font-size: 14px; line-height: 1.5; margin-bottom: 24px;">
                        ${message}
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button class="ai-confirm-cancel" style="padding: 8px 16px; border-radius: 8px; border: 1px solid var(--border, #e0e3e7); background: color-mix(in srgb, var(--panel, #f4f6f8) 78%, var(--bg, #ffffff)); color: var(--fg, #111111); font-size: 14px; cursor: pointer; transition: background 0.15s, border-color 0.15s;">${window.i18n?.cancel || 'Cancel'}</button>
                        <button class="ai-confirm-ok" style="padding: 8px 16px; border-radius: 8px; border: none; background: #d14a4a; color: #fff; font-size: 14px; cursor: pointer; transition: background 0.15s; font-weight: 500;">${window.i18n?.ok || 'OK'}</button>
                    </div>
                </div>
            `;

            overlay.appendChild(modal);

            if (aiContent) {
                aiContent.appendChild(overlay);
            } else if (aiModal) {
                aiModal.appendChild(overlay);
            } else {
                document.body.appendChild(overlay);
            }

            // Add animations
            const styleId = 'ai-confirm-animations';
            if (!document.getElementById(styleId)) {
                const style = document.createElement('style');
                style.id = styleId;
                style.textContent = `
                    @keyframes ai-fadeIn {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    @keyframes ai-scaleIn {
                        from { opacity: 0; transform: scale(0.95); }
                        to { opacity: 1; transform: scale(1); }
                    }
                `;
                document.head.appendChild(style);
            }

            let isSettled = false;
            let escapeHandler = null;

            const closeModal = (result = false) => {
                if (isSettled) return;
                isSettled = true;
                overlay.style.animation = 'ai-fadeIn 0.15s ease reverse';
                modal.style.animation = 'ai-scaleIn 0.15s ease reverse';
                if (escapeHandler) {
                    document.removeEventListener('keydown', escapeHandler);
                }
                setTimeout(() => overlay.remove(), 150);
                resolve(result);
            };

            modal.querySelector('.ai-confirm-close').addEventListener('click', () => closeModal(false));
            modal.querySelector('.ai-confirm-cancel').addEventListener('click', () => closeModal(false));
            modal.querySelector('.ai-confirm-ok').addEventListener('click', () => {
                closeModal(true);
            });

            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) closeModal();
            });

            escapeHandler = function(e) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    closeModal();
                    return;
                }
                if (e.key === 'Delete') {
                    e.preventDefault();
                    e.stopPropagation();
                    closeModal(true);
                    return;
                }
                if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
                    e.preventDefault();
                    const cancelBtn = modal.querySelector('.ai-confirm-cancel');
                    const okBtn = modal.querySelector('.ai-confirm-ok');
                    const focused = document.activeElement;
                    if (e.key === 'ArrowLeft') (focused === okBtn ? cancelBtn : okBtn).focus();
                    else (focused === cancelBtn ? okBtn : cancelBtn).focus();
                    return;
                }
                if (e.key === 'Enter') {
                    const cancelBtn = modal.querySelector('.ai-confirm-cancel');
                    const okBtn = modal.querySelector('.ai-confirm-ok');
                    if (document.activeElement === cancelBtn || document.activeElement === okBtn) return;
                    e.preventDefault();
                    e.stopPropagation();
                    closeModal(true);
                }
            };
            document.addEventListener('keydown', escapeHandler);

            // Hover effects
            const closeBtn = modal.querySelector('.ai-confirm-close');
            const cancelBtn = modal.querySelector('.ai-confirm-cancel');
            const okBtn = modal.querySelector('.ai-confirm-ok');

            closeBtn.addEventListener('mouseenter', () => closeBtn.style.background = 'rgba(127, 127, 127, 0.14)');
            closeBtn.addEventListener('mouseleave', () => closeBtn.style.background = 'none');

            cancelBtn.addEventListener('mouseenter', () => cancelBtn.style.background = 'color-mix(in srgb, var(--panel, #f4f6f8) 62%, var(--bg, #ffffff))');
            cancelBtn.addEventListener('mouseleave', () => cancelBtn.style.background = 'color-mix(in srgb, var(--panel, #f4f6f8) 78%, var(--bg, #ffffff))');

            okBtn.addEventListener('mouseenter', () => okBtn.style.background = '#c43d3d');
            okBtn.addEventListener('mouseleave', () => okBtn.style.background = '#d14a4a');
            });
    }

    function isConfirmDialogOpen() {
        return !!document.querySelector('.ai-confirm-overlay');
    }

    function flashStatus(text) {
        if (typeof window.flashStatus === 'function') {
            window.flashStatus(text);
            return;
        }
        const el = document.getElementById('status-right-content') || document.getElementById('status-right');
        if (el) {
            el.textContent = text;
            setTimeout(() => {
                if (el.textContent === text) el.textContent = '';
            }, 5000);
        }
    }

    function initDotField(container) {
        if (!container || container.dataset.dotField === '1') return;
        const host = container.parentElement || container;
        if (getComputedStyle(host).position === 'static') host.style.position = 'relative';
        container.dataset.dotField = '1';

        const canvas = document.createElement('canvas');
        canvas.className = 'dot-field-canvas';
        host.prepend(canvas);

        const ctx = canvas.getContext('2d');
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        let dots = [];
        let w = 0, h = 0;
        const mouse = { x: -9999, y: -9999, prevX: -9999, prevY: -9999, speed: 0 };
        let raf = null;

        function resize() {
            const hostRect = host.getBoundingClientRect();
            const rect = container.getBoundingClientRect();
            w = container.clientWidth || rect.width;
            h = container.clientHeight || rect.height;
            if (w <= 0 || h <= 0) return;
            canvas.width = Math.round(w * dpr);
            canvas.height = Math.round(h * dpr);
            canvas.style.width = w + 'px';
            canvas.style.height = h + 'px';
            canvas.style.left = Math.round(rect.left - hostRect.left) + 'px';
            canvas.style.top = Math.round(rect.top - hostRect.top) + 'px';
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            buildDots();
        }

        function buildDots() {
            const step = 22;
            const cols = Math.floor(w / step);
            const rows = Math.floor(h / step);
            const padX = (w % step) / 2;
            const padY = (h % step) / 2;
            const arr = new Array(rows * cols);
            let idx = 0;
            for (let r = 0; r < rows; r++) {
                for (let c = 0; c < cols; c++) {
                    const ax = padX + c * step + step / 2;
                    const ay = padY + r * step + step / 2;
                    arr[idx++] = { ax, ay, sx: ax, sy: ay };
                }
            }
            dots = arr;
        }

        function onMouseMove(e) {
            const rect = canvas.getBoundingClientRect();
            mouse.x = e.clientX - rect.left;
            mouse.y = e.clientY - rect.top;
        }

        function onMouseLeave() {
            mouse.x = -9999;
            mouse.y = -9999;
            mouse.speed = 0;
        }

        function getFgColor() {
            return getComputedStyle(container).color;
        }

        let fgColor = getFgColor();
        const colorObserver = new MutationObserver(() => { fgColor = getFgColor(); });
        colorObserver.observe(document.body, { attributes: true, attributeFilter: ['data-theme'] });

        let tick = 0;
        function animate() {
            if (w <= 0 || h <= 0 || !container.isConnected || !container.offsetParent) {
                raf = requestAnimationFrame(animate);
                return;
            }
            tick++;
            const cr = 200;
            const crSq = cr * cr;
            const eng = Math.min(mouse.speed / 3, 1);

            ctx.clearRect(0, 0, w, h);
            ctx.beginPath();

            const isDark = document.body.dataset.theme === 'dark';
            const baseAlpha = isDark ? 0.25 : 0.2;
            const activeAlpha = isDark ? 0.45 : 0.4;

            for (const d of dots) {
                const dx = mouse.x - d.ax;
                const dy = mouse.y - d.ay;
                const distSq = dx * dx + dy * dy;

                if (distSq < crSq && eng > 0.01) {
                    const dist = Math.sqrt(distSq);
                    const t = 1 - dist / cr;
                    const push = t * t * 30 * eng;
                    const angle = Math.atan2(dy, dx);
                    d.sx += (d.ax - Math.cos(angle) * push - d.sx) * 0.12;
                    d.sy += (d.ay - Math.sin(angle) * push - d.sy) * 0.12;
                    ctx.globalAlpha = activeAlpha;
                } else {
                    d.sx += (d.ax - d.sx) * 0.06;
                    ctx.globalAlpha = baseAlpha;
                }

                ctx.moveTo(d.sx + 0.8, d.sy);
                ctx.arc(d.sx, d.sy, 0.8, 0, Math.PI * 2);
            }

            ctx.fillStyle = fgColor;
            ctx.fill();
            ctx.globalAlpha = 1;

            const dx = mouse.x - mouse.prevX;
            const dy = mouse.y - mouse.prevY;
            const dist = Math.sqrt(dx * dx + dy * dy);
            mouse.speed += (dist - mouse.speed) * 0.3;
            if (mouse.speed < 0.001) mouse.speed = 0;
            mouse.prevX = mouse.x;
            mouse.prevY = mouse.y;

            raf = requestAnimationFrame(animate);
        }

        resize();
        window.addEventListener('resize', resize);
        if (typeof ResizeObserver === 'function') {
            const observer = new ResizeObserver(() => resize());
            observer.observe(container);
            if (host !== container) observer.observe(host);
        }
        container.addEventListener('mousemove', onMouseMove, { passive: true });
        container.addEventListener('mouseleave', onMouseLeave, { passive: true });
        raf = requestAnimationFrame(animate);
    }

    // Export for global use
    window.aiAssistant = {
        toggleModal: toggleAIModal,
        sendMessage: sendAIMessage,
        clearHistory: clearChatHistory,
        fixProblems: fixProblems,
        calculateData: calculateData,
        regenerate: regenerateResponse,
        attachFile: attachExternalFile,
        refreshI18n: refreshAttachI18n,
        highlightNewFiles: highlightNewFilesInTree
    };
    window.highlightNewFilesInTree = highlightNewFilesInTree;
})();
