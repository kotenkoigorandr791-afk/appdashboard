(function (global) {
    'use strict';
    const ALL_SERVER_TOOLS = new Set([
        'read_file', 'write_file', 'create_file', 'rename_file', 'move_path', 'copy_file',
        'delete_file', 'delete_directory', 'make_directory', 'list_directory', 'stat_path',
        'search_replace', 'search_by_regex', 'replace_by_regex', 'extract_document_text',
        'verify_syntax', 'compile_and_check', 'apply_changes', 'search_in_files',
        'web_search', 'open_url_fetch', 'download_file', 'archive_extract', 'archive_create',
        'json_edit', 'csv_preview', 'diff_file', 'run_command',
        'generate_speech', 'get_speech_progress', 'transcribe_audio', 'get_transcription_progress'
    ]);
    const ROOT_ONLY_SERVER = new Set(['extract_document_text']);
    const CLIENT_TOOLS = new Set(['get_open_file', 'apply_editor_patch', 'compile_open_document', 'save_open_document', 'export_markdown_pdf', 'export_latex_pdf', 'ask_user']);
    const EDIT_TOOLS = new Set(['write_file', 'create_file', 'search_replace', 'replace_by_regex', 'json_edit', 'apply_editor_patch']);
    const COMPILE_TOOLS = new Set(['compile_and_check', 'compile_open_document']);
    const READ_SEARCH_TOOLS = new Set([
        'read_file', 'get_open_file', 'search_in_files', 'search_by_regex',
        'list_directory', 'stat_path', 'web_search', 'open_url_fetch',
        'extract_document_text', 'diff_file', 'csv_preview'
    ]);
    const CHAT_CLIENT_TOOLS = new Set(['get_open_file']);
    function isChatAllowedTool(name, ctx) {
        if (CHAT_CLIENT_TOOLS.has(name)) return true;
        if (!READ_SEARCH_TOOLS.has(name)) return false;
        if (name === 'extract_document_text' && Number(ctx?.userRole ?? 0) !== 2) return false;
        return true;
    }
    function agentTr(ctx, key, fallback, params) {
        if (typeof ctx?.tr === 'function') return ctx.tr(key, fallback, params);
        let text = fallback || key;
        if (params && typeof text === 'string') {
            Object.keys(params).forEach((p) => {
                text = text.replace(new RegExp(`\\{${p}\\}`, 'g'), String(params[p]));
            });
        }
        return text;
    }
    function toolLabelFor(ctx, name, args) {
        if (typeof ctx?.toolLabel === 'function') return ctx.toolLabel(name, args || {});
        return String(name || '').replace(/_/g, ' ');
    }
    const TOOL_ORDER = [
        'read_file', 'get_open_file', 'write_file', 'create_file', 'make_directory', 'copy_file',
        'rename_file', 'move_path', 'delete_file', 'delete_directory', 'search_replace',
        'search_by_regex', 'replace_by_regex', 'apply_editor_patch', 'search_in_files',
        'web_search', 'open_url_fetch', 'download_file', 'diff_file', 'stat_path',
        'json_edit', 'csv_preview', 'archive_extract', 'archive_create',
        'export_markdown_pdf', 'export_latex_pdf', 'verify_syntax', 'compile_and_check',
        'compile_open_document', 'run_command', 'save_open_document', 'ask_user',
        'generate_speech', 'get_speech_progress', 'transcribe_audio', 'get_transcription_progress',
        'list_directory', 'extract_document_text'
    ];
    const BASE_TOOL_DEFINITIONS = [
        { type: 'function', function: { name: 'read_file', description: 'Read part of a text file. Use offset_line and line_count for large files (do not read whole file at once).', parameters: { type: 'object', properties: { path: { type: 'string' }, offset_line: { type: 'integer', description: '1-based start line (default 1)' }, line_count: { type: 'integer', description: 'Lines to read (default 200, max 500)' }, max_chars: { type: 'integer', description: 'Max characters in response (default 32000)' } }, required: ['path'] } } },
        { type: 'function', function: { name: 'get_open_file', description: 'Get open file path and content from the editor.', parameters: { type: 'object', properties: {} } } },
        { type: 'function', function: { name: 'write_file', description: 'REQUIRED for new/changed file content. Stage full file content (preview in editor, saved after agent run). Use this to add sections, paragraphs, or rewrite the file.', parameters: { type: 'object', properties: { path: { type: 'string' }, content: { type: 'string' } }, required: ['path', 'content'] } } },
        { type: 'function', function: { name: 'create_file', description: 'Create a new file. Fails if the file already exists. Use for explicit file creation requests.', parameters: { type: 'object', properties: { path: { type: 'string' }, content: { type: 'string', description: 'Optional initial content' } }, required: ['path'] } } },
        { type: 'function', function: { name: 'make_directory', description: 'Create a directory, including parent directories when needed.', parameters: { type: 'object', properties: { path: { type: 'string' } }, required: ['path'] } } },
        { type: 'function', function: { name: 'copy_file', description: 'Copy a file to a new path. Fails for directories.', parameters: { type: 'object', properties: { source_path: { type: 'string' }, target_path: { type: 'string' } }, required: ['source_path', 'target_path'] } } },
        { type: 'function', function: { name: 'rename_file', description: 'Rename or move a file within the workspace.', parameters: { type: 'object', properties: { old_path: { type: 'string' }, new_path: { type: 'string' } }, required: ['old_path', 'new_path'] } } },
        { type: 'function', function: { name: 'move_path', description: 'Move or rename a file or directory within the workspace.', parameters: { type: 'object', properties: { old_path: { type: 'string' }, new_path: { type: 'string' } }, required: ['old_path', 'new_path'] } } },
        { type: 'function', function: { name: 'delete_file', description: 'Delete a file. This always requires a user confirmation dialog before execution.', parameters: { type: 'object', properties: { path: { type: 'string' } }, required: ['path'] } } },
        { type: 'function', function: { name: 'delete_directory', description: 'Delete a directory recursively. This always requires a user confirmation dialog before execution.', parameters: { type: 'object', properties: { path: { type: 'string' } }, required: ['path'] } } },
        { type: 'function', function: { name: 'search_replace', description: 'REQUIRED for targeted edits. Copy old_string verbatim from read_file "content" (LF newlines). Stages replacement for preview before disk save.', parameters: { type: 'object', properties: { path: { type: 'string' }, old_string: { type: 'string' }, new_string: { type: 'string' } }, required: ['path', 'old_string', 'new_string'] } } },
        { type: 'function', function: { name: 'search_by_regex', description: 'Search for a regular expression. paths defaults to open file and @mentions if omitted.', parameters: { type: 'object', properties: { paths: { type: 'array', items: { type: 'string' } }, path: { type: 'string', description: 'Single-file shortcut when paths omitted' }, pattern: { type: 'string' }, flags: { type: 'string' }, max_results: { type: 'integer' } }, required: ['pattern'] } } },
        { type: 'function', function: { name: 'replace_by_regex', description: 'Stage regex-based replacements in a text file. Use for structured edits that need patterns.', parameters: { type: 'object', properties: { path: { type: 'string' }, pattern: { type: 'string' }, replacement: { type: 'string' }, flags: { type: 'string' } }, required: ['path', 'pattern', 'replacement'] } } },
        { type: 'function', function: { name: 'apply_editor_patch', description: 'REQUIRED for edits on the open file. Stage search/replace patches in the editor.', parameters: { type: 'object', properties: { patches: { type: 'array', items: { type: 'object', properties: { s: { type: 'string' }, r: { type: 'string' } }, required: ['s', 'r'] } } }, required: ['patches'] } } },
        { type: 'function', function: { name: 'search_in_files', description: 'Search for a text query in one or more files (paths from @mentions or open file). Returns matching lines.', parameters: { type: 'object', properties: { paths: { type: 'array', items: { type: 'string' } }, query: { type: 'string' }, max_results: { type: 'integer' } }, required: ['paths', 'query'] } } },
        { type: 'function', function: { name: 'web_search', description: 'Search the web and return a short list of results with titles, links, and snippets.', parameters: { type: 'object', properties: { query: { type: 'string' }, max_results: { type: 'integer' } }, required: ['query'] } } },
        { type: 'function', function: { name: 'open_url_fetch', description: 'Fetch a specific URL and return text content, status, and content type.', parameters: { type: 'object', properties: { url: { type: 'string' }, max_chars: { type: 'integer' } }, required: ['url'] } } },
        { type: 'function', function: { name: 'download_file', description: 'Download a file from a URL into the workspace.', parameters: { type: 'object', properties: { url: { type: 'string' }, target_path: { type: 'string' }, overwrite: { type: 'boolean' } }, required: ['url', 'target_path'] } } },
        { type: 'function', function: { name: 'diff_file', description: 'Show a unified diff between a file and provided content, or between two files.', parameters: { type: 'object', properties: { path: { type: 'string' }, compare_path: { type: 'string' }, content: { type: 'string' }, max_chars: { type: 'integer' } }, required: ['path'] } } },
        { type: 'function', function: { name: 'stat_path', description: 'Return metadata about a file or directory.', parameters: { type: 'object', properties: { path: { type: 'string' } }, required: ['path'] } } },
        { type: 'function', function: { name: 'json_edit', description: 'Update JSON keys safely without rewriting manually. Supports set/remove operations by dotted path.', parameters: { type: 'object', properties: { path: { type: 'string' }, edits: { type: 'array', items: { type: 'object', properties: { op: { type: 'string', enum: ['set', 'remove'] }, key_path: { type: 'string' }, value: {} }, required: ['op', 'key_path'] } } }, required: ['path', 'edits'] } } },
        { type: 'function', function: { name: 'csv_preview', description: 'Preview CSV or TSV rows with headers.', parameters: { type: 'object', properties: { path: { type: 'string' }, max_rows: { type: 'integer' }, delimiter: { type: 'string' } }, required: ['path'] } } },
        { type: 'function', function: { name: 'archive_extract', description: 'Extract a ZIP archive into a folder in the workspace.', parameters: { type: 'object', properties: { archive_path: { type: 'string' }, extract_to: { type: 'string' } }, required: ['archive_path'] } } },
        { type: 'function', function: { name: 'archive_create', description: 'Create a ZIP archive from one or more files or folders.', parameters: { type: 'object', properties: { items: { type: 'array', items: { type: 'string' } }, archive_path: { type: 'string' } }, required: ['items', 'archive_path'] } } },
        { type: 'function', function: { name: 'verify_syntax', description: 'Local syntax check AFTER editing (py_compile, gcc -fsyntax-only, javac, or LaTeX). Also blocks unsafe code patterns. Run before compile_and_check.', parameters: { type: 'object', properties: { path: { type: 'string' }, content: { type: 'string', description: 'Optional staged content' } }, required: ['path'] } } },
        { type: 'function', function: { name: 'compile_and_check', description: 'Compile or run AFTER verify_syntax passes. Does not replace write_file/search_replace/apply_editor_patch.', parameters: { type: 'object', properties: { path: { type: 'string' }, content: { type: 'string', description: 'Optional; do not use instead of an edit tool.' } }, required: ['path'] } } },
        { type: 'function', function: { name: 'run_command', description: 'Run a whitelisted command in the workspace and return stdout/stderr.', parameters: { type: 'object', properties: { command: { type: 'array', items: { type: 'string' } }, cwd: { type: 'string' }, timeout_seconds: { type: 'integer' } }, required: ['command'] } } },
        { type: 'function', function: { name: 'compile_open_document', description: 'LaTeX/Markdown preview AFTER editing. Not a substitute for edit tools.', parameters: { type: 'object', properties: { format: { type: 'string', enum: ['markdown', 'latex', 'auto'] } } } } },
        { type: 'function', function: { name: 'save_open_document', description: 'Save the open LaTeX or Markdown document to the server.', parameters: { type: 'object', properties: {} } } },
        { type: 'function', function: { name: 'ask_user', description: 'Ask the user to choose one option when required info is missing (e.g. PDF font). Use sparingly — only when truly ambiguous.', parameters: { type: 'object', properties: { question: { type: 'string' }, options: { type: 'array', items: { type: 'object', properties: { id: { type: 'string' }, label: { type: 'string' } }, required: ['id', 'label'] } } }, required: ['question', 'options'] } } },
        { type: 'function', function: { name: 'export_markdown_pdf', description: 'Export open Markdown (.md) to PDF and save PDF on the server in the same folder (pandoc). Use when user asks to save/export as PDF.', parameters: { type: 'object', properties: { path: { type: 'string', description: 'Optional .md path; defaults to open file' } } } } },
        { type: 'function', function: { name: 'export_latex_pdf', description: 'Export LaTeX (.tex) to PDF and save PDF on the server in the same folder. Works with a closed file when path is provided; otherwise uses the open file.', parameters: { type: 'object', properties: { path: { type: 'string', description: 'Optional .tex path; defaults to open file' } } } } },
        { type: 'function', function: { name: 'generate_speech', description: 'Generate speech audio from text on the server. Does not require opening the TTS editor.', parameters: { type: 'object', properties: { text: { type: 'string' }, language: { type: 'string', description: 'Language code, e.g. en, ru, pl' }, speed: { type: 'number', description: 'Speech speed, default 1.0' } }, required: ['text'] } } },
        { type: 'function', function: { name: 'get_speech_progress', description: 'Check server speech generation progress.', parameters: { type: 'object', properties: { progress_id: { type: 'string' } }, required: ['progress_id'] } } },
        { type: 'function', function: { name: 'transcribe_audio', description: 'Transcribe an audio file on the server. Does not require opening the transcription editor.', parameters: { type: 'object', properties: { audio_path: { type: 'string' }, language: { type: 'string', description: 'Language code, default pl' } }, required: ['audio_path'] } } },
        { type: 'function', function: { name: 'get_transcription_progress', description: 'Check server audio transcription progress.', parameters: { type: 'object', properties: { progress_id: { type: 'string' } }, required: ['progress_id'] } } },
        { type: 'function', function: { name: 'list_directory', description: 'List files in the current folder or a subfolder. Can return recursive results.', parameters: { type: 'object', properties: { path: { type: 'string' }, recursive: { type: 'boolean' }, max_entries: { type: 'integer' } } } } },
        { type: 'function', function: { name: 'extract_document_text', description: 'Extract text from PDF, Excel, or presentation. Root only.', parameters: { type: 'object', properties: { path: { type: 'string' } }, required: ['path'] } } }
    ];
    let cachedSystemPrompt = null;
    let cachedChatSystemPrompt = null;
    function agentSystemPrompt() {
        return cachedSystemPrompt || 'You are a coding agent. Use tools and stay on the user task.';
    }
    function chatSystemPrompt() {
        return cachedChatSystemPrompt || 'You are a helpful read-only assistant. Use tools to inspect files; never modify the workspace.';
    }
    function clearAgentPromptCache() {
        cachedSystemPrompt = null;
        cachedChatSystemPrompt = null;
        cachedPromptOpenPath = null;
        cachedPromptMode = null;
    }
    let cachedPromptOpenPath = null;
    let cachedPromptMode = null;
    async function loadAgentPrompts(apiBase, openPath, mode) {
        const pathKey = String(openPath || '');
        const promptMode = mode === 'chat' ? 'chat' : 'agent';
        if (promptMode === 'chat' && cachedChatSystemPrompt && cachedPromptOpenPath === pathKey && cachedPromptMode === 'chat') {
            return cachedChatSystemPrompt;
        }
        if (promptMode === 'agent' && cachedSystemPrompt && cachedPromptOpenPath === pathKey && cachedPromptMode === 'agent') {
            return cachedSystemPrompt;
        }
        try {
            const url = new URL(apiBase, global.location.href);
            url.searchParams.set('action', 'ai_agent_prompts');
            url.searchParams.set('mode', promptMode);
            if (pathKey) url.searchParams.set('open_path', pathKey);
            const res = await fetch(url.toString(), { credentials: 'same-origin' });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.ok && data.system) {
                if (promptMode === 'chat') {
                    cachedChatSystemPrompt = String(data.system);
                } else {
                    cachedSystemPrompt = String(data.system);
                }
                cachedPromptOpenPath = pathKey;
                cachedPromptMode = promptMode;
                return String(data.system);
            }
        } catch (e) {}
        return promptMode === 'chat' ? chatSystemPrompt() : agentSystemPrompt();
    }
    function getToolDefinitions(ctx) {
        const role = Number(ctx?.userRole ?? 0);
        const isRoot = role === 2;
        const allowed = new Set();
        BASE_TOOL_DEFINITIONS.forEach((def) => {
            const name = def.function.name;
            if (CLIENT_TOOLS.has(name)) {
                allowed.add(name);
                return;
            }
            if (!ALL_SERVER_TOOLS.has(name)) return;
            if (ROOT_ONLY_SERVER.has(name) && !isRoot) return;
            allowed.add(name);
        });
        const list = BASE_TOOL_DEFINITIONS.filter((d) => allowed.has(d.function.name));
        const rank = (name) => {
            const i = TOOL_ORDER.indexOf(name);
            return i === -1 ? 999 : i;
        };
        return list.sort((a, b) => rank(a.function.name) - rank(b.function.name));
    }
    function getChatToolDefinitions(ctx) {
        const allowed = new Set();
        READ_SEARCH_TOOLS.forEach((name) => {
            if (isChatAllowedTool(name, ctx)) allowed.add(name);
        });
        CHAT_CLIENT_TOOLS.forEach((name) => allowed.add(name));
        const list = BASE_TOOL_DEFINITIONS.filter((d) => allowed.has(d.function.name));
        const rank = (name) => {
            const i = TOOL_ORDER.indexOf(name);
            return i === -1 ? 999 : i;
        };
        return list.sort((a, b) => rank(a.function.name) - rank(b.function.name));
    }
    function detectExportPdfIntent(text) {
        const s = String(text || '');
        return /(?:pdf|пдф)|export\s+(?:to\s+)?pdf|save\s+(?:as\s+)?pdf|сохран(?:ить|и)[^\n]{0,40}(?:pdf|пдф)|экспорт[^\n]{0,20}(?:pdf|пдф)/iu.test(s);
    }
    function exportPdfTaskSystemHint(userTask) {
        return `\n\n[PDF EXPORT ON SERVER]
User wants PDF saved on the server.
- For .md files: save_open_document if needed, then export_markdown_pdf.
- For .tex files: call export_latex_pdf with path when the file is mentioned/attached or closed; it saves the PDF next to the source file.
Do not ask for clarification.
Request: «${String(userTask || '').slice(0, 400)}»`;
    }
    function detectSummarizeIntent(text) {
        const s = String(text || '');
        return /(?:конспект|кратк(?:ое|ий|о)|резюме|summary|summarize|outline|пересказ|обзор|brief|thesis|саммари)/iu.test(s);
    }
    function extractAssistantMessageText(msg) {
        if (!msg || typeof msg !== 'object') return '';
        const c = msg.content;
        if (typeof c === 'string' && c.trim() !== '') return c.trim();
        if (Array.isArray(c)) {
            return c.map((p) => (typeof p === 'string' ? p : (p?.text || ''))).join('').trim();
        }
        return '';
    }
    function looksLikeToolPlanningText(text) {
        const s = String(text || '').trim();
        if (!s) return false;
        return /(?:^|\n)\s*(?:We need to|Let's|I need to|The assistant should|Нужно|Давайте)\b/i.test(s)
            && /\b(?:read_file|get_open_file|compile_and_check|export_latex_pdf|export_markdown_pdf|offset_line|line_count|call the tool|call read_file)\b/i.test(s);
    }
    function messagesIncludeToolResults(messages) {
        return Array.isArray(messages) && messages.some((m) => m && m.role === 'tool');
    }
    function chatAnswerNudgeText(ctx) {
        return agentTr(
            ctx,
            'aiChatAnswerNudge',
            'You used tools but returned no visible answer. Write a complete response for the user now (summary, analysis, or explanation). Use the file text already retrieved. Do not call more tools unless one short read is absolutely required.'
        );
    }
    function detectEditIntent(text) {
        const s = String(text || '');
        if (!s.trim()) return false;
        const patterns = [
            /(?:^|\s)(сделай|сделать|добавь|добавить|измени|изменить|исправь|исправить|напиши|написать|вставь|вставить|убери|удали|удалить|перепиши|обнови|замени|поправь|дополни|опиши|описать)(?:\s|$)/iu,
            /чтобы\s+(?:тут|здесь|было|был|написано|содержал)/iu,
            /(?:^|\s)(add|change|edit|fix|write|insert|remove|delete|update|replace|modify|implement|rewrite)(?:\s|$)/i,
            /(?:make|let)\s+(?:it|this|the\s+file)/i
        ];
        return patterns.some((re) => re.test(s));
    }
    function editTaskSystemHint(userTask) {
        return `\n\n[CONTENT EDIT REQUIRED — mandatory workflow]
The user wants the file content changed. User request: «${String(userTask || '').slice(0, 500)}»
1) Use write_file, create_file, search_replace, replace_by_regex, json_edit, or apply_editor_patch to apply the requested text.
2) compile_and_check is BLOCKED until step 1 succeeds.
3) Do not finish with only compilation — that does not fulfill the request.`;
    }
    function markEditDone(ctx, name, result) {
        if (!EDIT_TOOLS.has(name) || !result || result.error) return;
        if (result.staged || result.replacements > 0 || result.persisted) {
            ctx.hasFileEdit = true;
            return;
        }
        if (name === 'apply_editor_patch' && result.changed) {
            ctx.hasFileEdit = true;
        }
    }
    function parseToolArgs(raw) {
        if (raw == null) return {};
        if (typeof raw === 'object') return raw;
        try { return JSON.parse(String(raw)); } catch (e) { return {}; }
    }
    function parseXmlishToolCalls(text) {
        const out = [];
        const src = String(text || '');
        const re = /<tool_call(?:\s+name=["']?([a-zA-Z0-9_:-]+)["']?)?\s*>([\s\S]*?)<\/tool_call>/gi;
        let m;
        while ((m = re.exec(src)) !== null) {
            const body = String(m[2] || '').trim();
            const firstLine = body.split(/\r?\n/, 1)[0].trim();
            const name = (m[1] || firstLine || '').replace(/[^a-zA-Z0-9_:-].*$/, '').trim();
            if (!name) continue;
            const args = {};
            const argRe = /<arg_key>([\s\S]*?)<\/arg_key>\s*<arg_value>([\s\S]*?)<\/arg_value>/gi;
            let a;
            while ((a = argRe.exec(body)) !== null) {
                const key = String(a[1] || '').trim();
                if (key) args[key] = String(a[2] || '').trim();
            }
            out.push({ id: 'xml_' + out.length + '_' + Math.random().toString(36).slice(2, 10), name, args, synthetic: true });
        }
        return out;
    }
    function stripXmlishToolCalls(text) {
        return String(text || '').replace(/<tool_call[\s\S]*?<\/tool_call>/gi, '').trim();
    }
    function collectToolCalls(message) {
        const out = [];
        if (!message) return out;
        if (Array.isArray(message.tool_calls)) {
            message.tool_calls.forEach((tc) => {
                if (!tc?.function?.name) return;
                out.push({ id: tc.id || ('tc_' + out.length), name: tc.function.name, args: parseToolArgs(tc.function.arguments) });
            });
        }
        if (out.length === 0 && typeof message.content === 'string') {
            out.push(...parseXmlishToolCalls(message.content));
        }
        return out;
    }
    function agentPathsMatch(a, b) {
        const na = String(a || '').replace(/^\//, '').trim();
        const nb = String(b || '').replace(/^\//, '').trim();
        return na !== '' && (na === nb || na.endsWith('/' + nb) || nb.endsWith('/' + na));
    }
    async function resolveAgentEditBaseContent(hooks, path) {
        if (!path || !hooks) return null;
        const staged = hooks.getStagedContent?.(path);
        if (staged != null) return staged;
        if (typeof hooks.getOpenFile !== 'function') return null;
        try {
            const open = await hooks.getOpenFile();
            if (!open?.name || open.content == null) return null;
            if (!agentPathsMatch(path, open.name)) return null;
            return open.content;
        } catch (e) {
            return null;
        }
    }
    async function executeServerTool(ctx, apiBase, tool, args, root, hooks) {
        const payload = { ...(args || {}) };
        if (tool === 'search_in_files') {
            if ((!payload.paths || !payload.paths.length) && payload.path) {
                payload.paths = [payload.path];
            }
            if (hooks?.resolveSearchPaths && (!payload.paths || !payload.paths.length)) {
                payload.paths = hooks.resolveSearchPaths();
            }
        }
        if (tool === 'search_by_regex') {
            if ((!payload.paths || !payload.paths.length) && payload.path) {
                payload.paths = [payload.path];
            }
            if (hooks?.resolveSearchPaths && (!payload.paths || !payload.paths.length)) {
                payload.paths = hooks.resolveSearchPaths();
            }
        }
        if ((tool === 'search_replace' || tool === 'replace_by_regex') && payload.path) {
            const base = await resolveAgentEditBaseContent(hooks, payload.path);
            if (base != null) payload.base_content = base;
        }
        if ((tool === 'compile_and_check' || tool === 'verify_syntax') && hooks?.getStagedContent && payload.path && payload.content == null) {
            const staged = hooks.getStagedContent(payload.path);
            if (staged != null) payload.content = staged;
        }
        const body = new URLSearchParams({ action: 'ai_agent_tool', tool, args: JSON.stringify(payload), root: root || '' });
        const res = await fetch(apiBase, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, credentials: 'same-origin', body });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) {
            throw new Error(data.error || agentTr(ctx, 'aiAgentToolFailed', 'Tool {tool} failed', { tool }));
        }
        if (data.staged && data.path && hooks?.onFileStaged) {
            hooks.onFileStaged(data.path, data.before ?? '', data.after ?? '', { tool });
        }
        if (tool === 'compile_and_check' && payload.content != null && payload.path && hooks?.onFileStaged && hooks?.getOpenFile) {
            try {
                const open = await hooks.getOpenFile();
                const p = String(payload.path || '').replace(/^\//, '');
                const on = String(open?.name || '').replace(/^\//, '');
                if (on && (on === p || on.endsWith('/' + p) || p.endsWith('/' + on))) {
                    const before = hooks.getStagedContent?.(payload.path) ?? open.content ?? '';
                    const after = String(payload.content);
                    if (after !== before) {
                        hooks.onFileStaged(payload.path, before, after, { tool: 'compile_and_check' });
                    }
                }
            } catch (e) {}
        }
        return data;
    }
    async function executeClientTool(ctx, name, args, hooks) {
        if (name === 'get_open_file') {
            const file = await hooks.getOpenFile();
            if (!file?.name) return { ok: true, message: agentTr(ctx, 'aiAgentNoFileOpen', 'No file open') };
            return { ok: true, path: file.name, content: file.content ?? '' };
        }
        if (name === 'apply_editor_patch') {
            const patches = Array.isArray(args.patches) ? args.patches : [];
            const result = await hooks.applyEditorPatches(patches);
            return { ok: true, ...result };
        }
        if (name === 'compile_open_document') {
            const result = await hooks.compileOpenDocument(args.format || 'auto');
            return { ok: true, ...result };
        }
        if (name === 'save_open_document') {
            const result = await hooks.saveOpenDocument();
            return { ok: true, ...result };
        }
        if (name === 'export_markdown_pdf') {
            const result = await hooks.exportMarkdownPdf(args.path);
            return { ok: true, ...result };
        }
        if (name === 'export_latex_pdf') {
            const result = await hooks.exportLatexPdf(args.path);
            return { ok: true, ...result };
        }
        if (name === 'ask_user') {
            if (typeof hooks.askUser !== 'function') {
                return { ok: false, error: agentTr(ctx, 'aiAgentAskUserUnavailable', 'ask_user is not available in this session') };
            }
            const result = await hooks.askUser(args);
            return { ok: true, ...result };
        }
        throw new Error(agentTr(ctx, 'aiAgentUnknownClientTool', 'Unknown client tool: {tool}', { tool: name }));
    }
    async function executeTool(ctx, name, args) {
        if (ctx.readOnly && !isChatAllowedTool(name, ctx)) {
            throw new Error(agentTr(ctx, 'aiChatToolReadOnly', 'Tool not available in chat mode (read-only): {tool}', { tool: name }));
        }
        if (ctx.requiresContentEdit && !ctx.hasFileEdit && COMPILE_TOOLS.has(name)) {
            return {
                ok: false,
                error: agentTr(ctx, 'aiAgentEditBlockedCompile', 'Blocked: user asked to change file content. Call a file-edit tool first (write_file, create_file, search_replace, replace_by_regex, json_edit, or apply_editor_patch), then compile.')
            };
        }
        if (ALL_SERVER_TOOLS.has(name)) {
            if (name === 'delete_file' || name === 'delete_directory') {
                if (typeof ctx.hooks?.askUser !== 'function') {
                    return { ok: false, error: agentTr(ctx, 'aiAgentDeleteBlockedNoUi', 'Delete is blocked because confirmation UI is unavailable.') };
                }
                const isDir = name === 'delete_directory';
                const confirm = await ctx.hooks.askUser({
                    question: agentTr(ctx, isDir ? 'aiAgentDeleteConfirmFolder' : 'aiAgentDeleteConfirmFile', isDir ? 'Delete folder "{path}"?' : 'Delete file "{path}"?', { path: String(args?.path || '') }),
                    options: [
                        { id: 'delete', label: agentTr(ctx, isDir ? 'aiAgentDeleteFolder' : 'aiAgentDeleteFile', isDir ? 'Delete folder' : 'Delete file') },
                        { id: 'cancel', label: agentTr(ctx, 'aiAgentCancel', 'Cancel') }
                    ]
                });
                if (!confirm?.ok || confirm.choice_id !== 'delete') {
                    return { ok: false, error: agentTr(ctx, 'aiAgentDeletionCancelled', 'Deletion cancelled by user.') };
                }
                args = { ...(args || {}), confirmed: true };
            }
            if (Number(ctx.userRole) !== 2 && ROOT_ONLY_SERVER.has(name)) {
                throw new Error(agentTr(ctx, 'aiAgentAdminRequired', 'Tool requires administrator access (role 2)'));
            }
            if (name === 'extract_document_text') {
                const url = new URL(ctx.apiBase, global.location.href);
                url.searchParams.set('action', 'ai_extract_text');
                url.searchParams.set('path', String(args.path || ''));
                if (ctx.root) url.searchParams.set('root', ctx.root);
                const res = await fetch(url.toString(), { credentials: 'same-origin' });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.ok) throw new Error(data.error || 'extract failed');
                return { path: data.path, content: data.content };
            }
            const data = await executeServerTool(ctx, ctx.apiBase, name, args, ctx.root, ctx.hooks);
            markEditDone(ctx, name, data);
            return data;
        }
        if (CLIENT_TOOLS.has(name)) {
            const data = await executeClientTool(ctx, name, args, ctx.hooks);
            markEditDone(ctx, name, data);
            return data;
        }
        throw new Error(agentTr(ctx, 'aiAgentUnknownTool', 'Unknown tool: {tool}', { tool: name }));
    }
    function addUsageTotals(totals, payload) {
        const u = payload?.usage;
        if (!u) return totals;
        totals.prompt += Number(u.prompt_tokens) || 0;
        totals.completion += Number(u.completion_tokens) || 0;
        totals.total += Number(u.total_tokens) || (Number(u.prompt_tokens) || 0) + (Number(u.completion_tokens) || 0);
        return totals;
    }
    function replyLanguageHint(lang) {
        const code = String(lang || '').toLowerCase();
        if (code === 'ru') {
            return '\n\n[MANDATORY] The user writes in Russian. Every chat reply MUST be in Russian. Do not switch to English.';
        }
        if (code === 'en') {
            return '\n\n[MANDATORY] The user writes in English. Every chat reply MUST be in English unless they switch language.';
        }
        return '';
    }
    function buildStepLimitPrompt(ctx) {
        return {
            question: agentTr(ctx, 'aiAgentStepLimitQuestion', 'The assistant reached the edit step limit for this pass. Continue or interrupt?'),
            options: [
                { id: 'continue', label: agentTr(ctx, 'aiAgentStepLimitContinue', 'Continue') },
                { id: 'interrupt', label: agentTr(ctx, 'aiAgentStepLimitInterrupt', 'Interrupt assistant') }
            ]
        };
    }
    function repeatedCompileFailureText(ctx, errorText) {
        const err = errorText || agentTr(ctx, 'aiPlanResultFailed', 'Error');
        if (String(ctx.replyLanguage || '').toLowerCase() === 'ru') {
            return `Компиляция дважды завершилась одной и той же ошибкой. Я остановился, чтобы не повторять одну и ту же правку. Оставшаяся проблема: ${err}`;
        }
        return agentTr(
            ctx,
            'aiAgentRepeatedCompileFailure',
            'Compilation failed with the same error twice. I stopped to avoid repeating the same edit. The remaining problem is: {error}',
            { error: err }
        );
    }
    async function ensureEditStepBudget(ctx) {
        const chunk = ctx.maxEditSteps ?? ctx.maxSteps ?? 14;
        if (ctx.maxEditStepsBudget == null) ctx.maxEditStepsBudget = chunk;
        if (ctx.editStepsUsed < ctx.maxEditStepsBudget) {
            ctx.editStepsUsed += 1;
            return;
        }
        if (typeof ctx.hooks?.askUser !== 'function') {
            throw new Error(agentTr(ctx, 'aiAgentEditStepLimit', 'Edit step limit reached'));
        }
        const choice = await ctx.hooks.askUser(buildStepLimitPrompt(ctx));
        if (!choice?.ok || choice.choice_id !== 'continue') {
            const interrupted = new Error(agentTr(ctx, 'aiAgentInterrupted', 'Assistant action interrupted'));
            interrupted.code = 'agent_interrupted_by_user';
            throw interrupted;
        }
        ctx.maxEditStepsBudget += chunk;
        ctx.editStepsUsed += 1;
    }
    async function runAgentLoop(ctx) {
        const readOnly = !!ctx.readOnly;
        await loadAgentPrompts(ctx.apiBase, ctx.openPath, readOnly ? 'chat' : 'agent');
        ctx.hasFileEdit = false;
        ctx.wantsExportPdf = !readOnly && ctx.wantsExportPdf !== false && detectExportPdfIntent(ctx.userTask);
        ctx.requiresContentEdit = !readOnly && ctx.requiresContentEdit !== false && detectEditIntent(ctx.userTask) && !ctx.wantsExportPdf;
        const tools = readOnly ? getChatToolDefinitions(ctx) : getToolDefinitions(ctx);
        let systemContent = (readOnly ? chatSystemPrompt() : agentSystemPrompt()) + replyLanguageHint(ctx.replyLanguage);
        if (ctx.wantsExportPdf) systemContent += exportPdfTaskSystemHint(ctx.userTask);
        if (ctx.requiresContentEdit) systemContent += editTaskSystemHint(ctx.userTask);
        if (readOnly && detectSummarizeIntent(ctx.userTask)) {
            systemContent += '\n\n[DOCUMENT SUMMARY]\nThe user wants a summary or conspect. Read the file if needed, then write a full structured answer in the user language. Never finish without written text for the user.';
        }
        const messages = [{ role: 'system', content: systemContent }].concat(ctx.historyMessages || []);
        const currentTask = String(ctx.userTask || '').trim();
        const lastMessage = messages[messages.length - 1];
        if (currentTask && !(lastMessage && lastMessage.role === 'user' && String(lastMessage.content || '').trim() === currentTask)) {
            messages.push({ role: 'user', content: currentTask });
        }
        const usageTotals = { prompt: 0, completion: 0, total: 0 };
        ctx.editStepsUsed = 0;
        ctx.maxEditStepsBudget = ctx.maxEditSteps ?? ctx.maxSteps ?? 14;
        let editNudges = 0;
        let answerNudges = 0;
        let forceAnswerOnly = false;
        let safetyRounds = 0;
        const repeatedToolFailures = new Map();
        const maxSafetyRounds = 200;
        for (;;) {
            if (ctx.signal?.aborted) throw new Error(agentTr(ctx, 'aiAgentAborted', 'Aborted'));
            if (safetyRounds++ >= maxSafetyRounds) {
                throw new Error(agentTr(ctx, 'aiAgentEditStepLimit', 'Edit step limit reached'));
            }
            ctx.onStatus?.('thinking', safetyRounds);
            const useForceAnswer = forceAnswerOnly;
            forceAnswerOnly = false;
            const completion = await ctx.createCompletion({
                messages,
                tools: useForceAnswer ? [] : tools,
                tool_choice: useForceAnswer ? 'none' : 'auto',
                forceAnswerOnly: useForceAnswer
            });
            addUsageTotals(usageTotals, completion);
            const choice = completion?.choices?.[0];
            const msg = choice?.message;
            if (!msg) throw new Error(agentTr(ctx, 'aiAgentEmptyResponse', 'Empty model response'));
            const toolCalls = collectToolCalls(msg);
            if (toolCalls.length === 0) {
                const text = extractAssistantMessageText(msg);
                if (!useForceAnswer && looksLikeToolPlanningText(text) && answerNudges < 4) {
                    answerNudges++;
                    messages.push({ role: 'assistant', content: null });
                    messages.push({
                        role: 'user',
                        content: 'Do not narrate internal steps. If you need a file or compiler result, call the tool now. If the task is complete, give only a short final answer to the user in their language.'
                    });
                    continue;
                }
                if (ctx.requiresContentEdit && !ctx.hasFileEdit && editNudges < 4) {
                    editNudges++;
                    messages.push({ role: 'assistant', content: msg.content || null });
                    messages.push({
                        role: 'user',
                        content: agentTr(ctx, 'aiAgentEditNudge', 'You have not edited the file yet. The user requested content changes. Call a file-edit tool now (write_file, create_file, search_replace, replace_by_regex, json_edit, or apply_editor_patch), then compile if needed.')
                    });
                    continue;
                }
                if (!text && messagesIncludeToolResults(messages) && answerNudges < 3) {
                    answerNudges++;
                    forceAnswerOnly = true;
                    messages.push({ role: 'assistant', content: extractAssistantMessageText(msg) || null });
                    messages.push({ role: 'user', content: chatAnswerNudgeText(ctx) });
                    continue;
                }
                return { content: text, messages, usage: { ...usageTotals } };
            }
            messages.push({
                role: 'assistant',
                content: msg.tool_calls ? (msg.content || null) : (stripXmlishToolCalls(msg.content) || null),
                tool_calls: msg.tool_calls || toolCalls.map((tc) => ({
                    id: tc.id,
                    type: 'function',
                    function: { name: tc.name, arguments: JSON.stringify(tc.args || {}) }
                }))
            });
            for (const tc of toolCalls) {
                if (!tc.id) tc.id = 'call_' + Math.random().toString(36).slice(2, 14);
                if (!readOnly && EDIT_TOOLS.has(tc.name)) {
                    await ensureEditStepBudget(ctx);
                }
                const detail = toolLabelFor(ctx, tc.name, tc.args || {});
                const stepId = ctx.onToolStart?.(tc.name, detail, tc.args || {}) ?? tc.id;
                ctx.onStatus?.('tool', tc.name);
                let result;
                try {
                    result = await executeTool(ctx, tc.name, tc.args);
                } catch (err) {
                    result = { ok: false, error: err.message || String(err) };
                }
                ctx.onToolEnd?.(stepId, tc.name, detail, result);
                if (!readOnly && (tc.name === 'compile_and_check' || tc.name === 'verify_syntax') && result && result.success === false) {
                    const errText = String(result.error || result.output || '').replace(/\s+/g, ' ').slice(0, 500);
                    const sig = `${tc.name}:${String(tc.args?.path || '')}:${errText}`;
                    const count = (repeatedToolFailures.get(sig) || 0) + 1;
                    repeatedToolFailures.set(sig, count);
                    if (count >= 2) {
                        messages.push({
                            role: 'tool',
                            tool_call_id: tc.id,
                            content: JSON.stringify({ ok: false, success: false, error: errText })
                        });
                        messages.push({
                            role: 'user',
                            content: agentTr(ctx, 'aiAgentRepeatedCompileContinue', 'The same compiler error repeated. Do not stop. Inspect the relevant source around the reported line or search for the real cause, then apply a different fix and compile again.')
                        });
                        continue;
                    }
                }
                const slim = { ...result };
                if (slim.content && String(slim.content).length > 12000) {
                    slim.content = String(slim.content).slice(0, 12000) + '\n[... truncated for context ...]';
                }
                if (slim.output && String(slim.output).length > 8000) {
                    slim.output = String(slim.output).slice(0, 8000) + '\n[... truncated ...]';
                }
                messages.push({ role: 'tool', tool_call_id: tc.id, content: JSON.stringify(slim) });
            }
        }
    }
    global.AIAgentSkeleton = {
        BASE_TOOL_DEFINITIONS,
        getToolDefinitions,
        getChatToolDefinitions,
        agentSystemPrompt,
        chatSystemPrompt,
        loadAgentPrompts,
        clearAgentPromptCache,
        runAgentLoop,
        executeTool,
        ALL_SERVER_TOOLS,
        ROOT_ONLY_SERVER,
        CLIENT_TOOLS,
        agentTr,
        toolLabelFor,
        READ_SEARCH_TOOLS,
        isChatAllowedTool,
        detectEditIntent,
        detectExportPdfIntent,
        EDIT_TOOLS,
        COMPILE_TOOLS
    };
})(typeof window !== 'undefined' ? window : globalThis);
