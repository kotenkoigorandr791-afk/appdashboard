<?php
$newsCanEdit = isset($_SESSION['user']) && (int)($_SESSION['user']['role'] ?? 0) >= 1;
$newsIsAdmin = isset($_SESSION['user']) && (int)($_SESSION['user']['role'] ?? 0) >= 2;
?>
<!-- ── News Portal Overlay (full-screen workflow) ─────────────────────────── -->
<script>
// Track changes in news content
let hasChanges = false;
let originalTitle = '';
let originalBody = '';
let originalPhoto = '';
let originalDate = '';

// Convert a unix timestamp to YYYY-MM-DD for the date input
function dateInputValue(ts) {
    if (!ts) return '';
    const d = new Date(ts * 1000);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

// Load AI models from server config
async function loadAiModels() {
    const modelSelects = document.querySelectorAll('#npo-ai-model, #npo-settings-ai-model');
    if (!modelSelects.length) return;
    
    try {
        const response = await fetch('/main?api=cloud_ai_config');
        const data = await response.json();
        const models = data.config?.models || [];
        const savedModel = localStorage.getItem('ai_model_news_ai');
        
        modelSelects.forEach(modelSelect => {
            modelSelect.innerHTML = '<option value="">-- Select Model --</option>';
            models.forEach(model => {
                const option = document.createElement('option');
                option.value = model;
                option.textContent = model;
                if (savedModel === model) option.selected = true;
                modelSelect.appendChild(option);
            });
        });
    } catch (error) {
        if (error && error.name === 'AbortError') return;
        console.error('Failed to load AI models:', error);
    }
}

// Save selected model
function saveSelectedModel() {
    document.querySelectorAll('#npo-ai-model, #npo-settings-ai-model').forEach(modelSelect => {
        if (modelSelect.dataset.bound === '1') return;
        modelSelect.dataset.bound = '1';
        modelSelect.addEventListener('change', () => {
            localStorage.setItem('ai_model_news_ai', modelSelect.value);
            document.querySelectorAll('#npo-ai-model, #npo-settings-ai-model').forEach(sel => {
                if (sel !== modelSelect) sel.value = modelSelect.value;
            });
            markAsChanged();
        });
    });
}

// Generate content with selected AI model
async function generateWithAi() {
    const prompt = document.getElementById('npo-ai-prompt').value.trim();
    const modelSelect = document.getElementById('npo-ai-model');
    const selectedModel = modelSelect ? modelSelect.value : '';
    const systemPrompt = document.getElementById('npo-system-prompt')?.value.trim() || '';
    const statusEl = document.getElementById('npo-ai-status');
    const currentTitle = document.getElementById('npo-edit-title').value.trim();
    const currentBody = (window.getBodyValue ? window.getBodyValue() : document.getElementById('npo-edit-body').value).trim();
    
    if (!prompt) {
        statusEl.textContent = 'Please enter a prompt';
        return;
    }
    
    statusEl.textContent = 'Generating...';
    
    try {
        const requestData = {
            prompt,
            model: selectedModel,
            system_prompt: systemPrompt,
            current_title: currentTitle,
            current_body: currentBody
        };
        
        const response = await fetch('/main?api=news&action=ai_generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
        });
        const data = await response.json();
        
        if (data.ok) {
            document.getElementById('npo-edit-title').value = data.title || currentTitle;
            if (window.setBodyValue) window.setBodyValue(data.body || currentBody);
            else document.getElementById('npo-edit-body').value = data.body || currentBody;
            const npoDate = document.getElementById('npo-edit-date');
            if (data.created_at && npoDate) npoDate.value = dateInputValue(data.created_at);
            statusEl.textContent = 'Generated successfully!';
            markAsChanged();
        } else {
            statusEl.textContent = data.error || 'Failed to generate';
        }
    } catch (error) {
        statusEl.textContent = 'Error: ' + error.message;
    }
}

// Mark content as changed
function markAsChanged() {
    hasChanges = true;
    const saveBtn = document.getElementById('npo-save-btn');
    if (saveBtn) saveBtn.style.display = 'block';
}

// Reset changes after save
function resetChanges() {
    hasChanges = false;
    originalTitle = document.getElementById('npo-edit-title').value.trim();
    originalBody = (window.getBodyValue ? window.getBodyValue() : document.getElementById('npo-edit-body').value).trim();
    originalPhoto = document.getElementById('npo-photo-url')?.value.trim() || '';
    originalDate = document.getElementById('npo-edit-date')?.value || '';
    
    const saveBtn = document.getElementById('npo-save-btn');
    if (saveBtn) saveBtn.style.display = 'none';
}

// Check if content has changed
function checkChanges() {
    const currentTitle = document.getElementById('npo-edit-title').value.trim();
    const currentBody = (window.getBodyValue ? window.getBodyValue() : document.getElementById('npo-edit-body').value).trim();
    const currentPhoto = document.getElementById('npo-photo-url')?.value.trim() || '';
    const currentDate = document.getElementById('npo-edit-date')?.value || '';
    
    if (currentTitle !== originalTitle || currentBody !== originalBody || currentPhoto !== originalPhoto || currentDate !== originalDate) {
        markAsChanged();
    }
}

// Initialize news editor
function initNewsEditor() {
    const titleEl = document.getElementById('npo-edit-title');
    const bodyEl = document.getElementById('npo-edit-body');
    const photoEl = document.getElementById('npo-photo-url');
    const dateEl = document.getElementById('npo-edit-date');
    const saveBtn = document.getElementById('npo-save-btn');

    if (!titleEl || !bodyEl || !saveBtn) {
        console.error('News editor elements not found');
        return;
    }

    // Set initial values
    originalTitle = titleEl.value.trim();
    originalBody = (window.getBodyValue ? window.getBodyValue() : bodyEl.value).trim();
    originalPhoto = photoEl ? photoEl.value.trim() : '';
    originalDate = dateEl ? dateEl.value : '';
    
    // Hide save button initially
    saveBtn.style.display = 'none';
    
    // Add event listeners for changes
    titleEl.addEventListener('input', checkChanges);
    bodyEl.addEventListener('input', checkChanges);
    if (photoEl) photoEl.addEventListener('change', markAsChanged);
    if (dateEl) dateEl.addEventListener('change', checkChanges);
}

// Initialize when news portal modal is opened
function initializeNewsPortal() {
    loadAiModels();
    saveSelectedModel();
    
    // Check if news editor elements exist
    if (document.getElementById('npo-edit-title') && 
        document.getElementById('npo-edit-body') &&
        document.getElementById('npo-save-btn')) {
        initNewsEditor();
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', initializeNewsPortal);

// Re-initialize when modal is re-opened (e.g., after closing and opening again)
window.addEventListener('newsPortalOpened', initializeNewsPortal);
</script>
<style>
#news-portal-overlay{display:none;position:fixed;inset:0;z-index:12000;background:#0f1419;color:#f4f7fb;flex-direction:column;overflow:hidden;backdrop-filter:none;-webkit-backdrop-filter:none;}
body.theme-light #news-portal-overlay{background:#eef2f7;color:#1a2332;}
#news-portal-overlay{--modal-bg:#1a2332;--group-block-bg:#252d3a;--app-item-bg:#2f3847;--border-color:rgba(255,255,255,0.12);--text-color:#f4f7fb;--scrollbar-track:#141b24;--scrollbar-thumb:#3d4a5c;}
body.theme-light #news-portal-overlay{--modal-bg:#ffffff;--group-block-bg:#e8edf3;--app-item-bg:#dde4ec;--border-color:#cfd8e3;--text-color:#1a2332;--scrollbar-track:#e8edf3;--scrollbar-thumb:#b0bcc9;}
#news-portal-overlay.active{display:flex;}
/* Header */
#npo-header{display:flex;align-items:center;gap:0;padding:0;border-bottom:1px solid var(--border-color);background:var(--group-block-bg);flex-shrink:0;height:56px;}
#npo-title{padding:0 20px;font-size:17px;font-weight:700;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
#npo-header-actions{display:flex;align-items:center;gap:10px;margin-left:auto;margin-right:16px;}
#npo-tabs{display:flex;height:100%;gap:0;}
.npo-tab{height:100%;padding:0 22px;border:none;background:none;color:var(--text-color);font-size:14px;font-weight:600;cursor:pointer;border-bottom:3px solid transparent;opacity:.6;transition:opacity .15s,border-color .15s;white-space:nowrap;}
.npo-tab:hover{opacity:.85;}
.npo-tab.active{opacity:1;border-bottom-color:var(--primary-color,#38bdf8);}
#npo-add-btn{display:flex;align-items:center;gap:7px;margin:0 12px 0 16px;padding:8px 18px;background:var(--primary-color,#38bdf8);color:#fff;border:none;border-radius:20px;cursor:pointer;font-weight:600;font-size:13px;transition:opacity .15s;white-space:nowrap;flex-shrink:0;}
#npo-add-btn:hover{opacity:.88;}
#npo-settings-btn,#npo-prompt-btn,#npo-menu-btn{width:34px;height:34px;border:1px solid var(--border-color);border-radius:50%;background:var(--modal-bg);color:var(--text-color);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s,border-color .15s;}
#npo-menu-btn{display:none;}
#npo-settings-btn:hover,#npo-prompt-btn:hover,#npo-menu-btn:hover{background:var(--app-item-bg);border-color:var(--primary-color,#38bdf8);}
#npo-settings-popover{display:none;position:absolute;right:58px;top:48px;width:min(420px,calc(100vw - 24px));padding:14px;border:1px solid var(--border-color);border-radius:12px;background:var(--modal-bg);box-shadow:0 16px 48px rgba(0,0,0,.28);z-index:12002;}
#npo-settings-popover.open{display:block;}
.npo-settings-field{margin-bottom:12px;}
.npo-settings-field label{display:block;font-size:11px;font-weight:700;color:color-mix(in srgb,var(--primary-color) 72%,var(--text-color));margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;}
#npo-settings-ai-model,#npo-system-prompt{width:100%;box-sizing:border-box;border:1px solid var(--border-color);border-radius:8px;background:var(--modal-bg);color:var(--text-color);font-size:13px;}
#npo-settings-ai-model{padding:8px 34px 8px 12px;appearance:none;background-image:url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;background-size:16px;}
#npo-system-prompt{padding:10px 12px;min-height:130px;resize:vertical;font-family:inherit;line-height:1.45;overflow-y:scroll;scrollbar-width:thin;scrollbar-color:var(--scrollbar-thumb) var(--scrollbar-track);}
#npo-system-prompt::-webkit-scrollbar{width:10px;}
#npo-system-prompt::-webkit-scrollbar-track{background:var(--scrollbar-track);border-radius:6px;}
#npo-system-prompt::-webkit-scrollbar-thumb{background:var(--scrollbar-thumb);border-radius:6px;border:2px solid var(--scrollbar-track);}
/* Body */
#npo-body{display:flex;flex:1;min-height:0;overflow:hidden;}
/* Sidebar – news list */
#npo-sidebar{width:320px;flex-shrink:0;border-right:1px solid var(--border-color);display:flex;flex-direction:column;overflow:hidden;background:color-mix(in srgb,var(--modal-bg) 88%,var(--primary-color) 12%);}
#npo-sidebar-header{padding:10px 12px;border-bottom:1px solid var(--border-color);display:flex;align-items:center;gap:8px;}
#npo-sidebar-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;opacity:.55;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
#npo-news-count{font-size:11px;font-weight:700;opacity:.55;flex-shrink:0;white-space:nowrap;}
#npo-refresh-list-btn{width:30px;height:30px;border:1px solid var(--border-color);border-radius:50%;background:var(--modal-bg);color:var(--text-color);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .15s,border-color .15s,transform .15s;}
#npo-refresh-list-btn:hover{background:var(--app-item-bg);border-color:var(--primary-color,#38bdf8);}
#npo-refresh-list-btn.loading{transform:rotate(90deg);opacity:.65;}
#npo-list{flex:1;overflow-y:auto;padding:10px 12px;}
.npo-list-item{display:flex;gap:10px;padding:10px 8px;border-radius:10px;cursor:pointer;transition:background .15s,border-color .15s;align-items:flex-start;margin-bottom:6px;border:1px solid color-mix(in srgb,var(--border-color) 72%,transparent);}
.npo-list-item:nth-child(3n+1){background:color-mix(in srgb,var(--group-block-bg) 82%,var(--primary-color) 18%);}
.npo-list-item:nth-child(3n+2){background:color-mix(in srgb,var(--group-block-bg) 88%,#22c55e 12%);}
.npo-list-item:nth-child(3n){background:color-mix(in srgb,var(--group-block-bg) 88%,#f59e0b 12%);}
.npo-list-item:hover,.npo-list-item.selected{border-color:color-mix(in srgb,var(--primary-color) 65%,var(--border-color));background:color-mix(in srgb,var(--app-item-bg) 76%,var(--primary-color) 24%);}
.npo-list-thumb{width:56px;height:40px;border-radius:6px;object-fit:cover;flex-shrink:0;background:var(--group-block-bg);}
.npo-list-thumb-ph{width:56px;height:40px;border-radius:6px;flex-shrink:0;background:var(--group-block-bg);display:flex;align-items:center;justify-content:center;opacity:.35;}
.npo-list-meta{flex:1;min-width:0;}
.npo-list-title{font-size:13px;font-weight:600;line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:3px;}
.npo-list-row{display:flex;align-items:center;gap:6px;}
.npo-list-status{font-size:10px;font-weight:700;padding:1px 7px;border-radius:8px;}
.npo-list-status.published{background:rgba(34,197,94,.18);color:#16a34a;}
.npo-list-status.draft{background:rgba(148,163,184,.15);color:#94a3b8;}
.npo-list-date{font-size:10px;opacity:.45;}
.npo-date-divider{display:flex;align-items:center;gap:12px;margin:14px 2px 6px;user-select:none;}
.npo-date-divider:first-child{margin-top:2px;}
.npo-date-line{flex:1;height:1px;background:var(--border-color);opacity:.55;}
.npo-date-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;opacity:.6;white-space:nowrap;color:color-mix(in srgb,var(--text-color) 80%,var(--primary-color));}
/* Main panel */
#npo-main{flex:1;min-width:0;display:flex;flex-direction:column;overflow:hidden;}
/* Tab panes */
.npo-pane{flex:1;min-height:0;overflow-y:auto;padding:28px 32px;display:none;}
.npo-pane.active{display:block;}
/* Editor fields */
.npo-field{margin-bottom:18px;padding:14px;border:1px solid color-mix(in srgb,var(--border-color) 74%,transparent);border-radius:12px;background:color-mix(in srgb,var(--modal-bg) 90%,var(--primary-color) 10%);}
.npo-field:nth-of-type(odd){background:color-mix(in srgb,var(--modal-bg) 92%,#22c55e 8%);}
.npo-field label{display:block;font-size:11px;font-weight:700;color:color-mix(in srgb,var(--primary-color) 72%,var(--text-color));margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;}
.npo-field input,.npo-field textarea{width:100%;padding:10px 13px;border:1px solid var(--border-color);border-radius:10px;background:var(--modal-bg);color:var(--text-color);font-size:14px;box-sizing:border-box;font-family:inherit;resize:vertical;transition:border-color .15s;}
.npo-field textarea{min-height:160px;}
.npo-field input:focus,.npo-field textarea:focus{outline:none;border-color:var(--primary-color,#38bdf8);}
.npo-field-row{display:flex;gap:10px;align-items:flex-start;}
.npo-field-row .npo-field{flex:1;}
/* AI prompt */
#npo-ai-panel{background:color-mix(in srgb,var(--group-block-bg) 78%,#7c3aed 22%);border:1px solid color-mix(in srgb,var(--primary-color) 46%,var(--border-color));border-radius:12px;padding:16px;margin-bottom:18px;display:none;}
#npo-ai-panel.open{display:block;}
.npo-ai-label{font-size:11px;font-weight:700;color:color-mix(in srgb,#7c3aed 74%,var(--text-color));text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;}
#npo-ai-prompt{width:100%;padding:10px 13px;border:1px solid var(--border-color);border-radius:8px;background:var(--modal-bg);color:var(--text-color);font-size:13px;font-family:inherit;resize:vertical;min-height:72px;box-sizing:border-box;}
#npo-ai-prompt:focus{outline:none;border-color:var(--primary-color,#38bdf8);}
.npo-ai-actions{display:flex;gap:10px;margin-top:10px;align-items:center;flex-wrap:wrap;}
#npo-ai-generate-btn{padding:8px 18px;background:linear-gradient(135deg,#7c3aed,#2563eb);color:#fff;border:none;border-radius:20px;font-weight:700;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:7px;transition:opacity .15s;}
#npo-ai-generate-btn:hover{opacity:.88;}
#npo-ai-generate-btn:disabled{opacity:.5;cursor:not-allowed;}
#npo-ai-target{font-size:12px;opacity:.6;}
#npo-ai-status{font-size:12px;min-height:18px;margin-top:4px;}
/* Photo */
.npo-photo-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:8px;}
.npo-btn-sm{padding:7px 14px;border:1px solid var(--border-color);border-radius:8px;background:var(--group-block-bg);color:var(--text-color);font-size:13px;cursor:pointer;transition:background .15s;}
.npo-btn-sm:hover{background:var(--app-item-bg);}
#npo-photo-preview-wrap{display:none;margin-top:8px;position:relative;display:inline-block;}
#npo-photo-preview{max-width:340px;width:100%;border-radius:10px;border:1px solid var(--border-color);display:block;}
.npo-photo-remove{position:absolute;top:6px;right:6px;background:rgba(0,0,0,.6);border:none;color:#fff;border-radius:50%;width:26px;height:26px;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;}
/* Toggle */
.npo-toggle{display:flex;align-items:center;gap:10px;cursor:pointer;user-select:none;font-size:14px;font-weight:600;margin-bottom:18px;}
.npo-toggle input{width:18px;height:18px;accent-color:var(--primary-color,#38bdf8);}
/* Actions row */
.npo-actions{display:flex;gap:10px;align-items:center;padding-top:6px;flex-wrap:wrap;}
#npo-save-btn{padding:11px 28px;background:var(--primary-color,#38bdf8);color:#fff;border:none;border-radius:50px;font-weight:700;font-size:14px;cursor:pointer;transition:opacity .15s;}
#npo-save-btn:hover{opacity:.88;}
#npo-save-btn:disabled{opacity:.5;cursor:not-allowed;}
#npo-cancel-btn{padding:11px 20px;background:var(--group-block-bg);color:var(--text-color);border:1px solid var(--border-color);border-radius:20px;cursor:pointer;font-size:14px;}
#npo-cancel-btn:hover{background:var(--app-item-bg);}
#npo-delete-btn{padding:11px 16px;background:none;color:#ef4444;border:1px solid rgba(239,68,68,.4);border-radius:20px;cursor:pointer;font-size:14px;margin-left:auto;}
#npo-delete-btn:hover{background:rgba(239,68,68,.1);}
#npo-status{font-size:13px;min-height:20px;padding-top:4px;}
/* Canvas / Layout tab */
#npo-canvas-container{position:relative;min-height:600px;background:repeating-linear-gradient(0deg,transparent,transparent 39px,var(--border-color) 39px,var(--border-color) 40px),repeating-linear-gradient(90deg,transparent,transparent 39px,var(--border-color) 39px,var(--border-color) 40px);border-radius:12px;border:1px solid var(--border-color);overflow:hidden;}
.npo-canvas-card{position:absolute;width:260px;background:var(--group-block-bg);border:1px solid var(--border-color);border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.18);cursor:default;}
.npo-canvas-card.dragging{box-shadow:0 16px 48px rgba(0,0,0,.4);opacity:.93;z-index:50;}
.npo-canvas-drag-handle{display:flex;align-items:center;gap:6px;padding:8px 12px;cursor:grab;border-bottom:1px solid var(--border-color);font-size:11px;opacity:.55;user-select:none;}
.npo-canvas-drag-handle:active{cursor:grabbing;}
.npo-canvas-card img{width:100%;height:120px;object-fit:cover;display:block;}
.npo-canvas-card-body{padding:10px 12px;}
.npo-canvas-card-title{font-size:13px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.npo-canvas-hint{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;opacity:.35;pointer-events:none;font-size:14px;}
/* Toolbar */
#npo-canvas-toolbar{display:flex;gap:10px;align-items:center;padding:14px 0 10px;}
#npo-canvas-save-btn{padding:8px 20px;background:var(--primary-color,#38bdf8);color:#fff;border:none;border-radius:50px;font-size:13px;font-weight:700;cursor:pointer;transition:opacity .15s;}
#npo-canvas-save-btn:hover{opacity:.88;}
#npo-canvas-status{font-size:12px;min-height:18px;}
/* Empty state */
#npo-editor-empty{display:flex;align-items:center;justify-content:center;flex:1;min-height:0;text-align:center;opacity:.45;font-size:15px;padding:48px;}
/* Prompt modal */
#npo-prompt-toolbar{display:flex;align-items:center;gap:8px;flex-shrink:0;flex-wrap:wrap;}
.npo-prompt-toggle{display:flex;border:1px solid var(--border-color);border-radius:8px;overflow:hidden;flex-shrink:0;}
.npo-prompt-toggle-btn{padding:4px 10px;border:none;background:none;color:var(--text-color);font-size:11px;font-weight:600;cursor:pointer;transition:background .15s,color .15s;white-space:nowrap;display:flex;align-items:center;gap:4px;}
.npo-prompt-toggle-btn.active{background:var(--primary-color,#38bdf8);color:#fff;}
.npo-prompt-toggle-btn:first-child{border-right:1px solid var(--border-color);}
.npo-prompt-toggle-btn svg{width:13px;height:13px;flex-shrink:0;}
#npo-prompt-code-wrap pre{background:#1a1d24!important;border:1px solid var(--border-color);border-radius:10px;padding:14px!important;overflow-x:auto;margin:0!important;min-height:320px;font-size:13px;line-height:1.55;}
body.theme-light #npo-prompt-code-wrap pre{background:#f4f6f8!important;}
#npo-prompt-code-wrap pre code.hljs{padding:0!important;background:transparent!important;overflow:visible!important;}
#npo-prompt-preview{display:none;width:100%;min-height:320px;padding:14px;border:1px solid var(--border-color);border-radius:10px;background:var(--modal-bg);color:var(--text-color);font-size:14px;line-height:1.65;box-sizing:border-box;overflow-y:auto;white-space:normal;word-wrap:break-word;}
#npo-prompt-preview a{color:var(--primary-color,#38bdf8);text-decoration:underline;}
#npo-prompt-preview h1,#npo-prompt-preview h2,#npo-prompt-preview h3,#npo-prompt-preview h4{margin:16px 0 8px;font-weight:700;}
#npo-prompt-preview h1{font-size:20px;color:var(--primary-color,#38bdf8);}
#npo-prompt-preview h2{font-size:17px;color:color-mix(in srgb,var(--primary-color) 80%,var(--text-color));}
#npo-prompt-preview h3{font-size:15px;}
#npo-prompt-preview p{margin:6px 0;}
#npo-prompt-preview code{background:color-mix(in srgb,#7c3aed 14%,transparent);padding:2px 6px;border-radius:4px;font-size:13px;font-family:'Courier New',monospace;}
#npo-prompt-preview pre{background:#1a1d24;border:1px solid var(--border-color);border-radius:8px;padding:12px;overflow-x:auto;margin:10px 0;}
body.theme-light #npo-prompt-preview pre{background:#f4f6f8;}
#npo-prompt-preview pre code{padding:0!important;background:transparent!important;font-size:13px;line-height:1.5;display:block;}
#npo-prompt-preview ul,#npo-prompt-preview ol{padding-left:22px;margin:6px 0;}
#npo-prompt-preview li{margin:3px 0;}
#npo-prompt-preview blockquote{border-left:3px solid var(--primary-color,#38bdf8);margin:8px 0;padding:4px 14px;opacity:.8;background:color-mix(in srgb,var(--group-block-bg) 50%,transparent);border-radius:0 6px 6px 0;}
#npo-prompt-preview hr{border:none;border-top:1px solid var(--border-color);margin:12px 0;}
#npo-prompt-preview table{border-collapse:collapse;width:100%;margin:10px 0;}
#npo-prompt-preview table td,#npo-prompt-preview table th{border:1px solid var(--border-color);padding:6px 10px;text-align:left;}
#npo-prompt-preview table th{background:var(--group-block-bg);font-weight:700;}
#npo-prompt-preview img{max-width:100%;border-radius:8px;margin:8px 0;}
/* Responsive */
@media(max-width:760px){
  #npo-menu-btn{display:flex;}
  #npo-sidebar{position:fixed;top:56px;left:0;bottom:0;width:280px;max-width:85vw;max-height:none;height:auto;border-right:1px solid var(--border-color);border-bottom:none;transform:translateX(-100%);transition:transform .25s ease;z-index:12001;box-shadow:0 0 24px rgba(0,0,0,.3);}
  #npo-sidebar.open{transform:translateX(0);}
  #npo-sidebar-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:12000;opacity:0;transition:opacity .2s;}
  #npo-sidebar-backdrop.open{display:block;opacity:1;}
  #npo-body{flex-direction:column;}
  .npo-pane{padding:18px 14px;}
  #npo-prompt-modal > div{width:100vw !important;max-width:100vw !important;border-radius:0 !important;height:100vh;max-height:100vh;margin:0;box-shadow:none;}
  }
    .check-label {
      display: flex;
      align-items: center;
      gap: 8px;
      color: inherit;
      font-size: inherit;
      cursor: pointer;
      position: relative;
    }

    .checkbox {
      width: 20px;
      height: 20px;
      position: absolute;
      opacity: 0;
      margin: 0;
      cursor: pointer;
    }

    .checkbox-box {
      width: 20px;
      height: 20px;
      border-radius: 4px;
      border: 2px solid currentColor;
      background: transparent;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: background 0.2s ease, border-color 0.2s ease, transform 0.15s ease;
    }

    .checkbox-box svg {
      width: 14px;
      height: 11px;
    }

    .checkbox-box svg path {
      stroke-dasharray: 20;
      stroke-dashoffset: 20;
      transition: stroke-dashoffset 0.4s ease 0.05s;
    }

    .checkbox:checked ~ .checkbox-box {
      background: #22c55e;
      border-color: #22c55e;
      transform: scale(1.05);
    }

    .checkbox:checked ~ .checkbox-box svg path {
      stroke-dashoffset: 0;
    }

    .checkbox:active ~ .checkbox-box {
      transform: scale(0.9);
    }
</style>

<div id="news-portal-overlay">
  <div id="npo-header">
    <button id="npo-menu-btn" type="button" aria-label="<?php echo my_htmlspecialchars($translations['menu'] ?? 'Menu'); ?>" title="<?php echo my_htmlspecialchars($translations['menu'] ?? 'Menu'); ?>">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div id="npo-title"><?php echo my_htmlspecialchars($translations['news_portal_title'] ?? 'News Portal'); ?></div>
    <?php if ($newsCanEdit): ?>
    <button id="npo-add-btn" type="button">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <?php echo my_htmlspecialchars($translations['news_add'] ?? 'Add news'); ?>
    </button>
    <?php endif; ?>
    <div id="npo-header-actions">
      <?php if ($newsCanEdit): ?>
      <button id="npo-settings-btn" type="button" aria-label="<?php echo my_htmlspecialchars($translations['settings_title'] ?? 'Settings'); ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M19.14 12.94a7.43 7.43 0 0 0 .05-.94 7.43 7.43 0 0 0-.05-.94l2.03-1.58a.5.5 0 0 0 .12-.64l-1.92-3.32a.5.5 0 0 0-.6-.22l-2.39.96a7.18 7.18 0 0 0-1.63-.94L14.39 2.8a.5.5 0 0 0-.5-.42h-3.84a.5.5 0 0 0-.5.42l-.36 2.54a7.18 7.18 0 0 0-1.63.94l-2.39-.96a.5.5 0 0 0-.6.22L2.65 8.86a.5.5 0 0 0 .12.64l2.03 1.58a7.43 7.43 0 0 0-.05.94c0 .32.02.63.05.94l-2.03 1.58a.5.5 0 0 0-.12.64l1.92 3.32a.5.5 0 0 0 .6.22l2.39-.96c.5.39 1.05.71 1.63.94l.36 2.54a.5.5 0 0 0 .5.42h3.84a.5.5 0 0 0 .5-.42l.36-2.54c.58-.23 1.13-.55 1.63-.94l2.39.96a.5.5 0 0 0 .6-.22l1.92-3.32a.5.5 0 0 0-.12-.64l-2.03-1.58zM12 15.5A3.5 3.5 0 1 1 12 8a3.5 3.5 0 0 1 0 7.5z"/></svg>
      </button>
      <button id="npo-prompt-btn" type="button" title="<?php echo my_htmlspecialchars($translations['news_writing_prompt'] ?? 'News writing prompt'); ?>" aria-label="<?php echo my_htmlspecialchars($translations['news_writing_prompt'] ?? 'News writing prompt'); ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      </button>
      <?php endif; ?>
      <button id="npo-back" type="button" class="ap-close-btn-style">&times;</button>
    </div>
    <?php if ($newsCanEdit): ?>
    <div id="npo-settings-popover">
      <div class="npo-settings-field">
        <label><?php echo my_htmlspecialchars($translations['ai_default_model_label'] ?? 'Model'); ?></label>
        <select id="npo-settings-ai-model">
          <option value=""><?php echo my_htmlspecialchars($translations['select_model_placeholder'] ?? '-- Select Model --'); ?></option>
        </select>
      </div>
      <div class="npo-settings-field" style="margin-bottom:0;">
        <label><?php echo my_htmlspecialchars($translations['system_prompt'] ?? 'System prompt'); ?></label>
        <textarea id="npo-system-prompt">You are a news article writer. Given a topic or prompt, improve or rewrite the article. Respond with valid JSON only: {"title":"...","body":"...","created_at":<optional publication date as a unix timestamp in seconds>}. The body should be written in Markdown format. Use headings, lists, bold, italic, code blocks, and other Markdown syntax as appropriate for the content.</textarea>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <div id="npo-sidebar-backdrop" aria-hidden="true"></div>
  <div id="npo-body">
    <div id="npo-sidebar">
      <div id="npo-sidebar-header">
        <div id="npo-sidebar-title"><?php echo my_htmlspecialchars($translations['news_portal_title'] ?? 'Articles'); ?></div>
        <span id="npo-news-count" aria-hidden="true"></span>
        <button id="npo-refresh-list-btn" type="button" title="<?php echo my_htmlspecialchars($translations['refresh'] ?? 'Refresh'); ?>" aria-label="<?php echo my_htmlspecialchars($translations['refresh'] ?? 'Refresh'); ?>">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>
        </button>
      </div>
      <div id="npo-list"></div>
    </div>
    <div id="npo-main">
      <!-- Editor pane -->
      <div id="npo-pane-editor" class="npo-pane active">
        <div id="npo-editor-empty"><?php echo my_htmlspecialchars($translations['news_select_or_add'] ?? 'Select an article or click "Add news" to start'); ?></div>
        <div id="npo-editor-form" style="display:none">
          <input type="hidden" id="npo-edit-id">
           <div id="npo-ai-panel">
             <div class="npo-ai-label">
               <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:5px"><path d="M12 2l2.9 6.26L22 9.27l-5 5.14 1.18 7.09L12 18.27l-6.18 3.23L7 14.41 2 9.27l7.1-1.01z"/></svg>
               <?php echo my_htmlspecialchars($translations['news_ai_generate'] ?? 'Generate with AI'); ?>
             </div>
             <div style="margin-bottom: 10px;">
               <select id="npo-ai-model" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--modal-bg); color: var(--text-color); font-size: 13px;">
                 <option value=""><?php echo my_htmlspecialchars($translations['select_model_placeholder'] ?? '-- Select Model --'); ?></option>
               </select>
             </div>
             <textarea id="npo-ai-prompt" placeholder="<?php echo my_htmlspecialchars($translations['news_ai_prompt_ph'] ?? 'Describe what you want: topic, tone, length...'); ?>"></textarea>
             <div class="npo-ai-actions">
               <button type="button" id="npo-ai-generate-btn">
                 <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3l14 9-14 9V3z"/></svg>
                 <?php echo my_htmlspecialchars($translations['generate'] ?? 'Generate'); ?>
               </button>
               <span id="npo-ai-target"><?php echo my_htmlspecialchars($translations['news_ai_fills'] ?? 'Fills title + content'); ?></span>
             </div>
             <div id="npo-ai-status"></div>
           </div>
          <div class="npo-field">
            <label><?php echo my_htmlspecialchars($translations['news_title_label'] ?? 'Title'); ?></label>
            <input type="text" id="npo-edit-title" maxlength="200" placeholder="<?php echo my_htmlspecialchars($translations['news_title_placeholder'] ?? 'Article title...'); ?>">
          </div>
          <div class="npo-field" style="padding:0;border:none;background:none;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;padding:0 2px;">
              <label style="margin-bottom:0"><?php echo my_htmlspecialchars($translations['news_body_label'] ?? 'Content'); ?></label>
              <div style="display:flex;align-items:center;gap:8px;">
                <button type="button" id="npo-ai-toggle-btn" style="padding:4px 12px;border:1px solid var(--border-color);border-radius:20px;background:var(--group-block-bg);color:var(--text-color);font-size:11px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:5px;">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.26L22 9.27l-5 5.14 1.18 7.09L12 18.27l-6.18 3.23L7 14.41 2 9.27l7.1-1.01z"/></svg>
                  AI
                </button>
              </div>
            </div>
            <div class="npo-md-editor-wrap" id="npo-body-editor-wrap">
              <div class="npo-md-toolbar" id="npo-body-toolbar">
                <div class="npo-md-toggle">
                  <button type="button" class="npo-md-toggle-btn active" data-view="code" title="Edit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                    Edit
                  </button>
                  <button type="button" class="npo-md-toggle-btn" data-view="preview" title="Preview">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    Preview
                  </button>
                </div>
                <div class="sep"></div>
                <button type="button" data-md-action="bold" title="Bold (Ctrl+B)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/><path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/></svg></button>
                <button type="button" data-md-action="italic" title="Italic (Ctrl+I)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg></button>
                <button type="button" data-md-action="strike" title="Strikethrough"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.3 4.9c-1.2-1.3-3-1.9-4.8-1.7-2.9.3-4.8 2.2-4.9 4.6 0 1.6.9 2.8 2.4 3.4"/><line x1="4" y1="12" x2="20" y2="12"/><path d="M17.5 13.1c.2 1.6.1 3.1-.8 4.3-1.2 1.5-3.2 2.3-5.2 2.1-1.8-.2-3.4-1.1-4.5-2.5"/></svg></button>
                <div class="sep"></div>
                <button type="button" data-md-action="h1" title="Heading 1"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><text x="3" y="17" font-size="14" font-weight="700" fill="currentColor" stroke="none" font-family="sans-serif">H1</text></svg></button>
                <button type="button" data-md-action="h2" title="Heading 2"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><text x="3" y="17" font-size="14" font-weight="700" fill="currentColor" stroke="none" font-family="sans-serif">H2</text></svg></button>
                <button type="button" data-md-action="h3" title="Heading 3"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><text x="3" y="17" font-size="14" font-weight="700" fill="currentColor" stroke="none" font-family="sans-serif">H3</text></svg></button>
                <div class="sep"></div>
                <button type="button" data-md-action="ul" title="Bullet list"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4.5" cy="6" r="1.5" fill="currentColor" stroke="none"/><circle cx="4.5" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="4.5" cy="18" r="1.5" fill="currentColor" stroke="none"/></svg></button>
                <button type="button" data-md-action="ol" title="Numbered list"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="10" y1="6" x2="20" y2="6"/><line x1="10" y1="12" x2="20" y2="12"/><line x1="10" y1="18" x2="20" y2="18"/><text x="2" y="8" font-size="8" fill="currentColor" stroke="none" font-family="sans-serif">1</text><text x="2" y="14" font-size="8" fill="currentColor" stroke="none" font-family="sans-serif">2</text><text x="2" y="20" font-size="8" fill="currentColor" stroke="none" font-family="sans-serif">3</text></svg></button>
                <button type="button" data-md-action="checklist" title="Checklist"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="6" height="6" rx="1"/><path d="M5 8l1.5 1.5L9 6"/><line x1="12" y1="8" x2="20" y2="8"/><rect x="3" y="14" width="6" height="6" rx="1"/><line x1="12" y1="17" x2="20" y2="17"/></svg></button>
                <div class="sep"></div>
                <button type="button" data-md-action="quote" title="Blockquote"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V21z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 5v3c0 .001 0 1.004 1 1.004z"/></svg></button>
                <button type="button" data-md-action="code" title="Inline code"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg></button>
                <button type="button" data-md-action="codeblock" title="Code block"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><polyline points="9 8 5 12 9 16"/><polyline points="15 8 19 12 15 16"/></svg></button>
                <div class="sep"></div>
                <button type="button" data-md-action="link" title="Link"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></button>
                <button type="button" data-md-action="image" title="Image"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg></button>
                <button type="button" data-md-action="hr" title="Horizontal rule"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="12" x2="21" y2="12"/></svg></button>
                <button type="button" data-md-action="table" title="Table"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg></button>
              </div>
              <div class="npo-md-editor" id="npo-body-editor">
                <div id="npo-body-monaco" style="min-height:360px;"></div>
                <textarea id="npo-edit-body" style="display:none;"></textarea>
              </div>
              <div id="npo-body-preview" class="npo-md-preview"></div>
            </div>
          </div>
          <div class="npo-field">
            <label><?php echo my_htmlspecialchars($translations['news_photo_label'] ?? 'Photo'); ?></label>
            <div class="npo-photo-actions">
              <button type="button" id="npo-photo-pick-btn" class="npo-btn-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                <?php echo my_htmlspecialchars($translations['news_photo_pick'] ?? 'Select photo'); ?>
              </button>
              <button type="button" id="npo-photo-remove-btn" class="npo-btn-sm" style="color:#ef4444;border-color:rgba(239,68,68,.35);display:none"><?php echo my_htmlspecialchars($translations['remove'] ?? 'Remove'); ?></button>
            </div>
            <input type="file" id="npo-photo-file" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">
            <div id="npo-photo-preview-wrap" style="display:none">
              <img id="npo-photo-preview" src="" alt="">
            </div>
            <input type="hidden" id="npo-photo-url">
          </div>
          <div class="npo-field">
            <label><?php echo my_htmlspecialchars($translations['news_publication_date'] ?? 'Publication date'); ?></label>
            <input type="date" id="npo-edit-date">
          </div>
          <label class="npo-toggle check-label">
            <input type="checkbox" id="npo-published" class="checkbox">
            <span class="checkbox-box"><svg width="14" height="11" viewBox="0 0 24 18" fill="none"><path d="M2 9L8.5 15.5L22 2" stroke="#111113" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
            <?php echo my_htmlspecialchars($translations['news_published'] ?? 'Published (visible to all)'); ?>
          </label>
          <div class="npo-actions">
            <button type="button" id="npo-save-btn"><?php echo my_htmlspecialchars($translations['save'] ?? 'Save'); ?></button>
            <button type="button" id="npo-cancel-btn"><?php echo my_htmlspecialchars($translations['cancel'] ?? 'Cancel'); ?></button>
            <button type="button" id="npo-delete-btn" style="display:none">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
              <?php echo my_htmlspecialchars($translations['delete'] ?? 'Delete'); ?>
            </button>
          </div>
          <div id="npo-status"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── News Writing Prompt Modal ──────────────────────────────────────────── -->
<div id="npo-prompt-modal" style="display:none;position:fixed;inset:0;z-index:13000;background:rgba(6,10,16,0.48);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);justify-content:center;align-items:center;">
  <div style="background:var(--modal-bg);border:1px solid var(--border-color);border-radius:16px;width:min(700px,calc(100vw - 32px));max-height:90vh;display:flex;flex-direction:column;box-shadow:0 24px 80px rgba(0,0,0,.38);overflow:hidden;">
    <div style="display:flex;align-items:center;gap:12px;padding:16px 20px;border-bottom:1px solid var(--border-color);flex-shrink:0;">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color,#38bdf8)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      <span style="font-size:16px;font-weight:700;flex:1;"><?php echo my_htmlspecialchars($translations['news_writing_prompt'] ?? 'News Writing Prompt'); ?></span>
      <button type="button" id="npo-prompt-refresh-btn" style="padding:6px 12px;border:1px solid var(--border-color);border-radius:8px;background:var(--group-block-bg);color:var(--text-color);font-size:12px;cursor:pointer;display:flex;align-items:center;gap:6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        <?php echo my_htmlspecialchars($translations['refresh_token'] ?? 'Refresh token'); ?>
      </button>
      <button type="button" id="npo-prompt-copy-btn" style="padding:6px 14px;background:var(--primary-color,#38bdf8);color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        <?php echo my_htmlspecialchars($translations['copy'] ?? 'Copy'); ?>
      </button>
      <button type="button" id="npo-prompt-close" style="width:32px;height:32px;border:none;background:none;color:var(--text-color);font-size:24px;cursor:pointer;display:flex;align-items:center;justify-content:center;opacity:.55;">&times;</button>
    </div>
    <div id="npo-prompt-toolbar" style="padding:12px 20px 0;flex-shrink:0;">
      <div class="npo-prompt-toggle">
        <button type="button" class="npo-prompt-toggle-btn" data-view="code">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;flex-shrink:0"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
          Code
        </button>
        <button type="button" class="npo-prompt-toggle-btn active" data-view="preview">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;flex-shrink:0"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          Preview
        </button>
      </div>
    </div>
    <div style="padding:12px 20px;flex:1;overflow-y:auto;min-height:200px;">
      <div id="npo-prompt-loading" style="display:flex;align-items:center;justify-content:center;padding:48px;opacity:.5;">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite;margin-right:10px;"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg>
        <?php echo my_htmlspecialchars($translations['loading'] ?? 'Loading...'); ?>
      </div>
      <div id="npo-prompt-code-wrap" style="display:none;"><pre><code id="npo-prompt-code" class="hljs" style="white-space:pre-wrap;word-wrap:break-word;"></code></pre></div>
      <div id="npo-prompt-preview" style="display:block;"></div>
    </div>
    <div id="npo-prompt-status" style="padding:6px 20px 10px;font-size:12px;min-height:16px;opacity:.6;"></div>
  </div>
</div>

<style>
@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
/* ── Markdown Editor ──────────────────────────────────────────────────────── */
.npo-md-editor-wrap{border:1px solid var(--border-color);border-radius:10px;background:var(--modal-bg);overflow:hidden;transition:border-color .15s;}
.npo-md-editor-wrap:focus-within{border-color:var(--primary-color,#38bdf8);}
.npo-md-toolbar{display:flex;align-items:center;gap:4px;padding:6px 8px;border-bottom:1px solid color-mix(in srgb,var(--border-color) 72%,transparent);background:color-mix(in srgb,var(--group-block-bg) 92%,var(--primary-color) 8%);overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch;scrollbar-width:thin;}
.npo-md-toolbar::-webkit-scrollbar{height:4px;}
.npo-md-toolbar::-webkit-scrollbar-thumb{background:var(--border-color);border-radius:4px;}
.npo-md-toolbar button{width:38px;height:36px;border:none;background:none;color:var(--text-color);border-radius:7px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .12s,color .12s;flex-shrink:0;}
.npo-md-toolbar button:hover{background:color-mix(in srgb,var(--primary-color) 16%,transparent);color:var(--primary-color,#38bdf8);}
.npo-md-toolbar .sep{width:1px;height:24px;background:var(--border-color);margin:0 4px;flex-shrink:0;}
.npo-md-editor{position:relative;min-height:360px;}
.npo-md-editor textarea.npo-md-textarea{width:100%;min-height:360px;padding:12px 14px;border:none;background:color-mix(in srgb,var(--modal-bg) 94%,#1a1d24 6%);color:var(--text-color);font-size:13px;font-family:'SF Mono','Fira Code','Cascadia Code','JetBrains Mono',Consolas,'Courier New',monospace;line-height:1.65;resize:vertical;box-sizing:border-box;outline:none;white-space:pre;overflow-x:auto;tab-size:2;}
body.theme-light .npo-md-editor textarea.npo-md-textarea{background:color-mix(in srgb,#ffffff 94%,#f4f6f8 6%);}
.npo-md-editor textarea.npo-md-textarea::placeholder{opacity:.35;font-family:inherit;}
#npo-body-monaco{width:100%;min-height:360px;border:none;}
.npo-md-preview{display:none;width:100%;min-height:180px;padding:14px;border:none;background:transparent;color:var(--text-color);font-size:14px;line-height:1.65;box-sizing:border-box;overflow-y:auto;white-space:normal;word-wrap:break-word;}
.npo-md-preview a{color:var(--primary-color,#38bdf8);text-decoration:underline;}
.npo-md-preview h1,.npo-md-preview h2,.npo-md-preview h3,.npo-md-preview h4{margin:14px 0 6px;font-weight:700;}
.npo-md-preview h1{font-size:20px;color:var(--primary-color,#38bdf8);}
.npo-md-preview h2{font-size:17px;color:color-mix(in srgb,var(--primary-color) 80%,var(--text-color));}
.npo-md-preview h3{font-size:15px;}
.npo-md-preview p{margin:6px 0;}
.npo-md-preview code{background:color-mix(in srgb,#7c3aed 14%,transparent);padding:2px 6px;border-radius:4px;font-size:13px;font-family:'Courier New',monospace;}
.npo-md-preview pre{background:#1a1d24;border:1px solid var(--border-color);border-radius:8px;padding:12px;overflow-x:auto;margin:10px 0;}
body.theme-light .npo-md-preview pre{background:#f4f6f8;}
.npo-md-preview pre code{padding:0!important;background:transparent!important;font-size:13px;line-height:1.5;display:block;}
.npo-md-preview ul,.npo-md-preview ol{padding-left:22px;margin:6px 0;}
.npo-md-preview li{margin:3px 0;}
.npo-md-preview blockquote{border-left:3px solid var(--primary-color,#38bdf8);margin:8px 0;padding:4px 14px;opacity:.8;background:color-mix(in srgb,var(--group-block-bg) 50%,transparent);border-radius:0 6px 6px 0;}
.npo-md-preview hr{border:none;border-top:1px solid var(--border-color);margin:12px 0;}
.npo-md-preview table{border-collapse:collapse;width:100%;margin:10px 0;}
.npo-md-preview table td,.npo-md-preview table th{border:1px solid var(--border-color);padding:6px 10px;text-align:left;}
.npo-md-preview table th{background:var(--group-block-bg);font-weight:700;}
.npo-md-preview img{max-width:100%;border-radius:8px;margin:8px 0;}
.npo-md-toggle{display:flex;border:1px solid var(--border-color);border-radius:7px;overflow:hidden;flex-shrink:0;margin-right:6px;}
.npo-md-toggle-btn{padding:4px 10px;border:none;background:none;color:var(--text-color);font-size:11px;font-weight:600;cursor:pointer;transition:background .12s,color .12s;white-space:nowrap;display:flex;align-items:center;gap:4px;height:30px;}
.npo-md-toggle-btn.active{background:var(--primary-color,#38bdf8);color:#fff;}
.npo-md-toggle-btn:first-child{border-right:1px solid var(--border-color);}
.npo-md-toggle-btn svg{width:13px;height:13px;flex-shrink:0;}
.npo-md-toolbar .npo-md-toggle-btn{width:auto;padding:4px 10px;height:30px;}
</style>

<script>
(function(){
  const canEdit = <?php echo $newsCanEdit ? 'true' : 'false'; ?>;
  const overlay = document.getElementById('news-portal-overlay');
  if (!overlay) return;

  const listEl = document.getElementById('npo-list');
  const editorEmpty = document.getElementById('npo-editor-empty');
  const editorForm = document.getElementById('npo-editor-form');
  const aiPanel = document.getElementById('npo-ai-panel');
  const settingsBtn = document.getElementById('npo-settings-btn');
  const settingsPopover = document.getElementById('npo-settings-popover');
  const systemPromptEl = document.getElementById('npo-system-prompt');
  const refreshListBtn = document.getElementById('npo-refresh-list-btn');
  const menuBtn = document.getElementById('npo-menu-btn');
  const sidebar = document.getElementById('npo-sidebar');
  const sidebarBackdrop = document.getElementById('npo-sidebar-backdrop');

  let newsItems = [];
  let newsLayout = {};
  let selectedId = null;
  let uploadingPhoto = false;
  let refreshTimer = null;
  if (systemPromptEl) {
    const savedPrompt = localStorage.getItem('news_ai_system_prompt');
    if (savedPrompt) systemPromptEl.value = savedPrompt;
    systemPromptEl.addEventListener('input', () => localStorage.setItem('news_ai_system_prompt', systemPromptEl.value));
  }

  // ── Open / Close ───────────────────────────────────────────────────────────
  function openNewsPortal() {
    if (!overlay.classList.contains('active')) {
      overlay.classList.add('active');
      if (window.location.hash !== '#news-workflow') history.pushState(null, '', '#news-workflow');
      loadNews();
      startAutoRefresh();
    }
  }
  function closeNewsPortal() {
    overlay.classList.remove('active');
    stopAutoRefresh();
    if (window.location.hash === '#news-workflow') history.pushState(null, '', window.location.pathname + window.location.search);
  }
  function startAutoRefresh() {
    stopAutoRefresh();
    refreshTimer = setInterval(() => {
      if (overlay.classList.contains('active') && listEl.scrollTop < 80) loadNews();
    }, 20000);
  }
  function stopAutoRefresh() {
    if (refreshTimer) clearInterval(refreshTimer);
    refreshTimer = null;
  }

  document.getElementById('npo-back').addEventListener('click', closeNewsPortal);
  refreshListBtn?.addEventListener('click', () => loadNews());
  // Load more news when scrolled near the bottom of the list
  listEl.addEventListener('scroll', () => {
    if (listEl.scrollTop + listEl.clientHeight >= listEl.scrollHeight - 160) {
      if (hasMore && !loadingMore) loadNews(false);
    }
  });
  settingsBtn?.addEventListener('click', e => {
    e.stopPropagation();
    settingsPopover?.classList.toggle('open');
  });
  document.addEventListener('click', e => {
    if (settingsPopover?.classList.contains('open') && !settingsPopover.contains(e.target) && e.target !== settingsBtn) {
      settingsPopover.classList.remove('open');
    }
  });

  // ── Mobile sidebar drawer ────────────────────────────────────────────────────
  function closeSidebar() {
    sidebar?.classList.remove('open');
    sidebarBackdrop?.classList.remove('open');
  }
  function openSidebar() {
    sidebar?.classList.add('open');
    sidebarBackdrop?.classList.add('open');
  }
  function toggleSidebar() {
    if (sidebar?.classList.contains('open')) closeSidebar(); else openSidebar();
  }
  menuBtn?.addEventListener('click', toggleSidebar);
  sidebarBackdrop?.addEventListener('click', closeSidebar);
  // Close sidebar when an article is selected on mobile
  listEl?.addEventListener('click', e => {
    const item = e.target.closest('.npo-list-item');
    if (item && window.innerWidth <= 760) closeSidebar();
  });
  // Close sidebar on resize to desktop
  window.addEventListener('resize', () => {
    if (window.innerWidth > 760) closeSidebar();
  });

  // ── Tabs ───────────────────────────────────────────────────────────────────
  document.querySelectorAll('.npo-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.npo-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      document.querySelectorAll('.npo-pane').forEach(p => p.classList.remove('active'));
      const pane = document.getElementById('npo-pane-' + tab.dataset.pane);
      if (pane) {
        pane.classList.add('active');
        if (tab.dataset.pane === 'layout') renderCanvas();
      }
    });
  });

  // ── Load news (paginated) ──────────────────────────────────────────────────
  let listOffset = 0;
  let hasMore = true;
  let loadingMore = false;
  let lastGroupKey = null;
  const LIST_PAGE_SIZE = 20;

  function dateDividerLabel(ts) {
    if (!ts) return '';
    const now = new Date();
    const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
    const dayStart = new Date(ts * 1000);
    dayStart.setHours(0, 0, 0, 0);
    const diff = Math.round((todayStart - dayStart.getTime()) / 86400000);
    if (diff === 0) return '<?php echo my_htmlspecialchars($translations['news_today'] ?? 'Today'); ?>';
    if (diff === 1) return '<?php echo my_htmlspecialchars($translations['news_yesterday'] ?? 'Yesterday'); ?>';
    return fmtDate(ts);
  }

  function loadNews(reset = true) {
    if (reset) {
      listOffset = 0;
      hasMore = true;
      lastGroupKey = null;
    }
    if (!hasMore || loadingMore) return;
    loadingMore = true;
    refreshListBtn?.classList.add('loading');
    fetch('/main?api=news&action=list&limit=' + LIST_PAGE_SIZE + '&offset=' + listOffset)
      .then(r => r.json())
      .then(data => {
        if (!data.ok) return;
        const more = data.items || [];
        const countEl = document.getElementById('npo-news-count');
        if (countEl && typeof data.total === 'number') countEl.textContent = String(data.total);
        newsLayout = data.layout || {};
        hasMore = !!data.has_more;
        listOffset += more.length;
        if (reset) {
          newsItems = more;
          renderList();
        } else {
          const existing = new Set(newsItems.map(n => n.id));
          const fresh = more.filter(n => !existing.has(n.id));
          newsItems = newsItems.concat(fresh);
          appendListItems(fresh);
        }
        if (reset && selectedId) {
          const item = newsItems.find(n => n.id === selectedId);
          if (item) openEditor(item); else closeEditor();
        }
      })
      .finally(() => { loadingMore = false; refreshListBtn?.classList.remove('loading'); });
  }

  function escH(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function fmtDate(ts) {
    if (!ts) return '';
    return new Date(ts * 1000).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
  }
  function dateInputValue(ts) {
    if (!ts) return '';
    const d = new Date(ts * 1000);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }
  function todayDateString() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }
  function dateInputTimestamp(value) {
    if (!value) return 0;
    const d = new Date(value + 'T00:00:00');
    const ts = Math.floor(d.getTime() / 1000);
    return Number.isFinite(ts) ? ts : 0;
  }

  // ── Sidebar list (grouped by day) ─────────────────────────────────────────
  function buildListItem(item) {
    const el = document.createElement('div');
    el.className = 'npo-list-item' + (item.id === selectedId ? ' selected' : '');
    el.dataset.id = item.id;
    el.innerHTML = `
${item.photo
  ? `<img class="npo-list-thumb" src="/${escH(item.photo)}" alt="" loading="lazy">`
  : `<div class="npo-list-thumb-ph"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg></div>`}
<div class="npo-list-meta">
  <div class="npo-list-title">${escH(item.title)}</div>
  <div class="npo-list-row">
    <span class="npo-list-status ${item.published ? 'published' : 'draft'}">${item.published ? '<?php echo my_htmlspecialchars($translations['news_status_published'] ?? 'Published'); ?>' : '<?php echo my_htmlspecialchars($translations['news_status_draft'] ?? 'Draft'); ?>'}</span>
    <span class="npo-list-date">${fmtDate(item.created_at)}</span>
  </div>
</div>`;
    el.addEventListener('click', () => { openEditor(item); switchToEditorTab(); });
    return el;
  }

  function appendListItems(items) {
    items.forEach(item => {
      const key = dateInputValue(item.created_at);
      if (key !== lastGroupKey) {
        lastGroupKey = key;
        const div = document.createElement('div');
        div.className = 'npo-date-divider';
        div.innerHTML = `<div class="npo-date-line"></div><div class="npo-date-label">${escH(dateDividerLabel(item.created_at))}</div><div class="npo-date-line"></div>`;
        listEl.appendChild(div);
      }
      listEl.appendChild(buildListItem(item));
    });
  }

  function renderList() {
    if (!newsItems.length) {
      listEl.innerHTML = '<div style="padding:24px;text-align:center;opacity:.45;font-size:13px;"><?php echo my_htmlspecialchars($translations['news_empty'] ?? 'No news yet.'); ?></div>';
      lastGroupKey = null;
      return;
    }
    listEl.innerHTML = '';
    lastGroupKey = null;
    appendListItems(newsItems);
  }

  function switchToEditorTab() {
    document.querySelectorAll('.npo-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.npo-pane').forEach(p => p.classList.remove('active'));
    const tab = document.querySelector('.npo-tab[data-pane="editor"]');
    if (tab) tab.classList.add('active');
    document.getElementById('npo-pane-editor')?.classList.add('active');
  }

  // ── Editor ─────────────────────────────────────────────────────────────────
  function openEditor(item) {
    selectedId = item ? item.id : null;
    document.querySelectorAll('.npo-list-item').forEach(el => el.classList.toggle('selected', el.dataset.id === selectedId));
    editorEmpty.style.display = 'none';
    editorForm.style.display = '';
    document.getElementById('npo-edit-id').value = item ? item.id : '';
    document.getElementById('npo-edit-title').value = item ? item.title : '';
    document.getElementById('npo-edit-body').value = item ? (item.body || '') : '';
    if (bodyMonaco) bodyMonaco.setValue(item ? (item.body || '') : '');
    document.getElementById('npo-published').checked = item ? !!item.published : false;
    document.getElementById('npo-photo-url').value = item ? (item.photo || '') : '';
    document.getElementById('npo-edit-date').value = item ? dateInputValue(item.created_at) : todayDateString();
    document.getElementById('npo-status').textContent = '';
    const pp = document.getElementById('npo-photo-preview');
    const ppw = document.getElementById('npo-photo-preview-wrap');
    const removeBtn = document.getElementById('npo-photo-remove-btn');
    if (item && item.photo) {
      pp.src = '/' + item.photo;
      ppw.style.display = 'block';
      if (removeBtn) removeBtn.style.display = '';
    } else {
      pp.src = '';
      ppw.style.display = 'none';
      if (removeBtn) removeBtn.style.display = 'none';
    }
    document.getElementById('npo-delete-btn').style.display = item ? '' : 'none';
    aiPanel.classList.remove('open');
    document.getElementById('npo-ai-status').textContent = '';
    document.getElementById('npo-ai-prompt').value = '';
    // Reset body editor to code view
    const bodyPrev = document.getElementById('npo-body-preview');
    const monacoEl = document.getElementById('npo-body-monaco');
    if (monacoEl) monacoEl.style.display = '';
    if (bodyPrev) bodyPrev.style.display = 'none';
    bodyToggleBtns.forEach(b => b.classList.remove('active'));
    bodyToolbar?.querySelector('[data-view="code"]')?.classList.add('active');
    if (bodyMonaco) bodyMonaco.layout();
    resetChanges();
    document.getElementById('npo-edit-title').focus();
  }

  function closeEditor() {
    selectedId = null;
    document.querySelectorAll('.npo-list-item').forEach(el => el.classList.remove('selected'));
    editorEmpty.style.display = '';
    editorForm.style.display = 'none';
    aiPanel.classList.remove('open');
  }

  document.getElementById('npo-add-btn')?.addEventListener('click', () => { openEditor(null); switchToEditorTab(); });
  document.getElementById('npo-cancel-btn')?.addEventListener('click', closeEditor);

  // ── Photo ──────────────────────────────────────────────────────────────────
  const photoFile = document.getElementById('npo-photo-file');
  document.getElementById('npo-photo-pick-btn')?.addEventListener('click', () => photoFile?.click());
  document.getElementById('npo-photo-remove-btn')?.addEventListener('click', () => {
    document.getElementById('npo-photo-url').value = '';
    document.getElementById('npo-photo-preview').src = '';
    document.getElementById('npo-photo-preview-wrap').style.display = 'none';
    document.getElementById('npo-photo-remove-btn').style.display = 'none';
    if (photoFile) photoFile.value = '';
  });

  photoFile?.addEventListener('change', async () => {
    const file = photoFile.files[0];
    if (!file) return;
    uploadingPhoto = true;
    setStatus('<?php echo my_htmlspecialchars($translations['uploading'] ?? 'Uploading...'); ?>');
    try {
      const fd = new FormData();
      fd.append('photo', file);
      const res = await fetch('/main?api=news&action=upload_photo', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok) {
        document.getElementById('npo-photo-url').value = data.url;
        document.getElementById('npo-photo-preview').src = '/' + data.url;
        document.getElementById('npo-photo-preview-wrap').style.display = 'block';
        document.getElementById('npo-photo-remove-btn').style.display = '';
        setStatus('');
      } else { setStatus('<?php echo my_htmlspecialchars($translations['upload_failed'] ?? 'Upload failed'); ?>'); }
    } catch { setStatus('<?php echo my_htmlspecialchars($translations['upload_failed'] ?? 'Upload failed'); ?>'); }
    uploadingPhoto = false;
  });

  // ── Save ───────────────────────────────────────────────────────────────────
  document.getElementById('npo-save-btn')?.addEventListener('click', async () => {
    const id = document.getElementById('npo-edit-id').value;
    const title = document.getElementById('npo-edit-title').value.trim();
    const body = getBodyValue().trim();
    const published = document.getElementById('npo-published').checked;
    const photo = document.getElementById('npo-photo-url').value.trim();
    const editDate = document.getElementById('npo-edit-date').value;
    if (!title) { setStatus('<?php echo my_htmlspecialchars($translations['news_title_required'] ?? 'Title is required'); ?>'); return; }
    setSaving(true);
    setStatus('<?php echo my_htmlspecialchars($translations['saving'] ?? 'Saving...'); ?>');
    try {
      const payload = { id, title, body, published, photo };
      const createdAt = dateInputTimestamp(editDate);
      if (createdAt > 0) payload.created_at = createdAt;
      const res = await fetch('/main?api=news&action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.ok) {
        setStatus('<?php echo my_htmlspecialchars($translations['saved'] ?? 'Saved!'); ?>');
        selectedId = data.id || id || selectedId;
        setTimeout(() => { setStatus(''); loadNews(); }, 700);
      } else { setStatus(data.error || 'error'); }
    } catch { setStatus('error'); }
    setSaving(false);
  });

  // ── Delete ─────────────────────────────────────────────────────────────────
  document.getElementById('npo-delete-btn')?.addEventListener('click', async () => {
    if (!confirm('<?php echo my_htmlspecialchars($translations['news_delete_confirm'] ?? 'Delete this article?'); ?>')) return;
    const id = document.getElementById('npo-edit-id').value;
    await fetch('/main?api=news&action=delete', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ id })
    });
    closeEditor();
    loadNews();
  });

  // ── AI generate ────────────────────────────────────────────────────────────
  document.getElementById('npo-ai-toggle-btn')?.addEventListener('click', () => {
    aiPanel.classList.toggle('open');
    if (aiPanel.classList.contains('open')) document.getElementById('npo-ai-prompt')?.focus();
  });

  document.getElementById('npo-ai-generate-btn')?.addEventListener('click', async () => {
    const prompt = document.getElementById('npo-ai-prompt').value.trim();
    if (!prompt) return;
    const selectedModel = document.getElementById('npo-ai-model')?.value || '';
    const systemPrompt = document.getElementById('npo-system-prompt')?.value.trim() || '';
    const currentTitle = document.getElementById('npo-edit-title').value.trim();
    const currentBody = getBodyValue().trim();
    const btn = document.getElementById('npo-ai-generate-btn');
    const aiStatus = document.getElementById('npo-ai-status');
    btn.disabled = true;
    aiStatus.textContent = '<?php echo my_htmlspecialchars($translations['generating'] ?? 'Generating...'); ?>';
    try {
      const res = await fetch('/main?api=news&action=ai_generate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ prompt, model: selectedModel, system_prompt: systemPrompt, current_title: currentTitle, current_body: currentBody })
      });
      const data = await res.json();
      if (data.ok) {
        if (data.title) document.getElementById('npo-edit-title').value = data.title;
        if (data.body) setBodyValue(data.body);
        if (data.created_at) document.getElementById('npo-edit-date').value = dateInputValue(data.created_at);
        markAsChanged();
        aiStatus.textContent = '<?php echo my_htmlspecialchars($translations['done'] ?? 'Done!'); ?>';
      } else {
        aiStatus.textContent = data.error || 'AI error';
      }
    } catch { aiStatus.textContent = 'Request failed'; }
    btn.disabled = false;
  });

  // ── Markdown Body Editor ──────────────────────────────────────────────────
  const bodyTextarea = document.getElementById('npo-edit-body');
  const bodyEditorWrap = document.getElementById('npo-body-editor-wrap');
  const bodyPreview = document.getElementById('npo-body-preview');
  const bodyToolbar = document.getElementById('npo-body-toolbar');
  const bodyToggleBtns = bodyToolbar ? bodyToolbar.querySelectorAll('.npo-md-toggle-btn') : [];
  let bodyMonaco = null;

  function getBodyValue() {
    return bodyMonaco ? bodyMonaco.getValue() : (bodyTextarea ? bodyTextarea.value : '');
  }
  function setBodyValue(val) {
    if (bodyMonaco) bodyMonaco.setValue(val || '');
    else if (bodyTextarea) bodyTextarea.value = val || '';
  }

  function applyMdAction(action) {
    const ed = bodyMonaco;
    if (!ed) return;
    ed.focus();
    const sel = ed.getSelection();
    const model = ed.getModel();
    const selText = model.getValueInRange(sel);
    let replacement = '';
    switch (action) {
      case 'bold': replacement = `**${selText || 'bold text'}**`; break;
      case 'italic': replacement = `*${selText || 'italic text'}*`; break;
      case 'strike': replacement = `~~${selText || 'strikethrough'}~~`; break;
      case 'h1': replacement = `# ${selText || 'Heading 1'}`; break;
      case 'h2': replacement = `## ${selText || 'Heading 2'}`; break;
      case 'h3': replacement = `### ${selText || 'Heading 3'}`; break;
      case 'ul': replacement = selText ? selText.split('\n').map(l => `- ${l}`).join('\n') : '- List item'; break;
      case 'ol': replacement = selText ? selText.split('\n').map((l, i) => `${i+1}. ${l}`).join('\n') : '1. List item'; break;
      case 'checklist': replacement = selText ? selText.split('\n').map(l => `- [ ] ${l}`).join('\n') : '- [ ] Task'; break;
      case 'quote': replacement = selText ? selText.split('\n').map(l => `> ${l}`).join('\n') : '> Blockquote'; break;
      case 'code': replacement = `\`${selText || 'code'}\``; break;
      case 'codeblock': replacement = `\`\`\`\n${selText || 'code block'}\n\`\`\``; break;
      case 'link': replacement = `[${selText || 'link text'}](url)`; break;
      case 'image': replacement = `![${selText || 'alt text'}](url)`; break;
      case 'hr': replacement = `\n---\n`; break;
      case 'table':
        replacement = `| Header 1 | Header 2 | Header 3 |\n| -------- | -------- | -------- |\n| Cell 1   | Cell 2   | Cell 3   |`;
        break;
    }
    const op = { range: sel, text: replacement, forceMoveMarkers: true };
    ed.executeEdits('md-toolbar', [op]);

  }

  if (bodyToolbar) {
    bodyToolbar.querySelectorAll('[data-md-action]').forEach(btn => {
      btn.addEventListener('click', () => applyMdAction(btn.dataset.mdAction));
    });
  }

  bodyToggleBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      bodyToggleBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const monacoEl = document.getElementById('npo-body-monaco');
      if (btn.dataset.view === 'preview') {
        bodyPreview.innerHTML = renderMarkdown(getBodyValue());
        if (monacoEl) monacoEl.style.display = 'none';
        bodyPreview.style.display = 'block';
        if (typeof hljs !== 'undefined' && hljs.highlightElement) {
          bodyPreview.querySelectorAll('pre code').forEach(b => hljs.highlightElement(b));
        }
      } else {
        if (monacoEl) monacoEl.style.display = '';
        bodyPreview.style.display = 'none';
        if (bodyMonaco) bodyMonaco.layout();
      }
    });
  });

  // ── Canvas / Layout ────────────────────────────────────────────────────────
  function renderCanvas() {
    const container = document.getElementById('npo-canvas-container');
    if (!container) return;
    container.querySelectorAll('.npo-canvas-card').forEach(c => c.remove());
    if (!newsItems.length) return;
    newsItems.forEach((item, idx) => {
      const pos = newsLayout[item.id] || { x: 20 + (idx % 3) * 290, y: 20 + Math.floor(idx / 3) * 200 };
      const card = document.createElement('div');
      card.className = 'npo-canvas-card';
      card.dataset.id = item.id;
      card.style.left = pos.x + 'px';
      card.style.top = pos.y + 'px';
      card.innerHTML = `
<div class="npo-canvas-drag-handle">
  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="5" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="19" r="1"/></svg>
  ${escH(item.title).slice(0, 26)}${item.title.length > 26 ? '…' : ''}
</div>
${item.photo ? `<img src="/${escH(item.photo)}" alt="" loading="lazy">` : ''}
<div class="npo-canvas-card-body"><div class="npo-canvas-card-title">${escH(item.title)}</div></div>`;
      enableCanvasDrag(card, container);
      container.appendChild(card);
      newsLayout[item.id] = { x: pos.x, y: pos.y };
    });
    expandContainerHeight(container);
  }

  function enableCanvasDrag(card, container) {
    const handle = card.querySelector('.npo-canvas-drag-handle');
    if (!handle) return;
    let sx, sy, sl, st;
    function dragStart(cx, cy) {
      const cr = container.getBoundingClientRect();
      const r = card.getBoundingClientRect();
      sx = cx; sy = cy;
      sl = r.left - cr.left + container.scrollLeft;
      st = r.top - cr.top + container.scrollTop;
      card.style.left = sl + 'px';
      card.style.top = st + 'px';
      card.classList.add('dragging');
    }
    function dragMove(cx, cy) {
      const nl = Math.max(0, sl + cx - sx);
      const nt = Math.max(0, st + cy - sy);
      card.style.left = nl + 'px';
      card.style.top = nt + 'px';
      expandContainerHeight(container);
    }
    function dragEnd(cx, cy) {
      const nl = Math.max(0, sl + cx - sx);
      const nt = Math.max(0, st + cy - sy);
      newsLayout[card.dataset.id] = { x: Math.round(nl), y: Math.round(nt) };
      card.classList.remove('dragging');
    }
    handle.addEventListener('mousedown', e => {
      e.preventDefault();
      dragStart(e.clientX, e.clientY);
      const mm = ev => dragMove(ev.clientX, ev.clientY);
      const mu = ev => { dragEnd(ev.clientX, ev.clientY); document.removeEventListener('mousemove', mm); document.removeEventListener('mouseup', mu); };
      document.addEventListener('mousemove', mm);
      document.addEventListener('mouseup', mu);
    });
    handle.addEventListener('touchstart', e => {
      const t = e.touches[0];
      dragStart(t.clientX, t.clientY);
      const tm = ev => { ev.preventDefault(); const tt = ev.touches[0]; dragMove(tt.clientX, tt.clientY); };
      const tu = ev => { const tt = ev.changedTouches[0]; dragEnd(tt.clientX, tt.clientY); handle.removeEventListener('touchmove', tm); handle.removeEventListener('touchend', tu); };
      handle.addEventListener('touchmove', tm, { passive: false });
      handle.addEventListener('touchend', tu);
    }, { passive: true });
  }

  function expandContainerHeight(container) {
    let max = 400;
    container.querySelectorAll('.npo-canvas-card').forEach(c => {
      max = Math.max(max, parseInt(c.style.top || 0) + c.offsetHeight + 24);
    });
    container.style.minHeight = max + 'px';
  }

  document.getElementById('npo-canvas-save-btn')?.addEventListener('click', async () => {
    const st = document.getElementById('npo-canvas-status');
    st.textContent = '<?php echo my_htmlspecialchars($translations['saving'] ?? 'Saving...'); ?>';
    try {
      const res = await fetch('/main?api=news&action=save_layout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ layout: newsLayout })
      });
      const data = await res.json();
      st.textContent = data.ok ? '<?php echo my_htmlspecialchars($translations['saved'] ?? 'Saved!'); ?>' : 'error';
    } catch { st.textContent = 'error'; }
    setTimeout(() => { st.textContent = ''; }, 2000);
  });

  // ── Helpers ────────────────────────────────────────────────────────────────
  function setStatus(msg) { const el = document.getElementById('npo-status'); if (el) el.textContent = msg; }
  function setSaving(on) {
    const btn = document.getElementById('npo-save-btn');
    if (btn) btn.disabled = on;
  }

  // ── News Writing Prompt Modal ──────────────────────────────────────────────
  const promptModal = document.getElementById('npo-prompt-modal');
  const promptBtn = document.getElementById('npo-prompt-btn');
  const promptClose = document.getElementById('npo-prompt-close');
  const promptCode = document.getElementById('npo-prompt-code');
  const promptCodeWrap = document.getElementById('npo-prompt-code-wrap');
  const promptPreview = document.getElementById('npo-prompt-preview');
  const promptLoading = document.getElementById('npo-prompt-loading');
  const promptStatus = document.getElementById('npo-prompt-status');
  const promptCopyBtn = document.getElementById('npo-prompt-copy-btn');
  const promptRefreshBtn = document.getElementById('npo-prompt-refresh-btn');
  const promptToggleBtns = document.querySelectorAll('.npo-prompt-toggle-btn');

  let promptRawText = '';

  function renderMarkdown(md) {
    const escHtml = (s) => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const blocks = [];
    const inline = [];
    let html = String(md).replace(/```([\w-]*)\n([\s\S]*?)```/g, (m, lang, code) => {
      const key = `{{NPOCODEBLOCK${blocks.length}}}`;
      const langAttr = lang ? ` class="language-${escHtml(lang)}"` : '';
      blocks.push(`<pre><code${langAttr}>${escHtml(code.trimEnd())}</code></pre>`);
      return key;
    });
    html = escHtml(html).replace(/`([^`\n]+)`/g, (m, code) => {
      const key = `{{NPOINLINECODE${inline.length}}}`;
      inline.push(`<code>${code}</code>`);
      return key;
    });
    html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
    html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
    html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');
    html = html.replace(/^(?:---|\*\*\*|___)\s*$/gm, '<hr>');
    html = html.replace(/^&gt; (.+)$/gm, '<blockquote>$1</blockquote>');
    html = html.replace(/^\d+\. (.+)$/gm, '<oli>$1</oli>');
    html = html.replace(/(<oli>.*<\/oli>\n?)+/g, '<ol>$&</ol>').replace(/<oli>/g,'<li>').replace(/<\/oli>/g,'</li>');
    html = html.replace(/^[\*\-\+] (.+)$/gm, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>\n?)+/g, '<ul>$&</ul>');
    html = html.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
    html = html.replace(/___(.+?)___/g, '<strong><em>$1</em></strong>');
    html = html.replace(/__(.+?)__/g, '<strong>$1</strong>');
    html = html.replace(/_(.+?)_/g, '<em>$1</em>');
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
    html = html.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1" loading="lazy">');
    html = html.replace(/~~(.+?)~~/g, '<del>$1</del>');
    html = html.replace(/^(?!\s*$)(?!<\/?(?:h[1-6]|ul|ol|li|pre|blockquote|hr|table|img|p)(?:\s|>))(.+)$/gm, '<p>$1</p>');
    html = html.replace(/<p><\/p>/g, '');
    html = html.replace(/<p><li>/g, '<li>');
    html = html.replace(/<\/li><\/p>/g, '</li>');
    html = html.replace(/{{NPOINLINECODE(\d+)}}/g, (m, i) => inline[Number(i)] || '');
    html = html.replace(/<p>{{NPOCODEBLOCK(\d+)}}<\/p>/g, (m, i) => blocks[Number(i)] || '');
    html = html.replace(/{{NPOCODEBLOCK(\d+)}}/g, (m, i) => blocks[Number(i)] || '');
    return html;
  }

  function applyHighlighting(codeEl) {
    if (typeof hljs !== 'undefined' && hljs.highlightElement) {
      document.querySelectorAll('#npo-prompt-code-wrap pre code').forEach((block) => {
        hljs.highlightElement(block);
      });
    }
  }

  function updatePromptView(viewMode) {
    if (viewMode === 'preview') {
      promptCodeWrap.style.display = 'none';
      promptPreview.style.display = 'block';
      promptPreview.innerHTML = renderMarkdown(promptRawText);
      if (typeof hljs !== 'undefined' && hljs.highlightElement) {
        promptPreview.querySelectorAll('pre code').forEach((block) => {
          hljs.highlightElement(block);
        });
      }
    } else {
      promptCodeWrap.style.display = 'block';
      promptPreview.style.display = 'none';
      promptCode.textContent = promptRawText;
      applyHighlighting(promptCode);
    }
  }

  promptToggleBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      promptToggleBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      updatePromptView(btn.dataset.view);
    });
  });

  async function loadWritingPrompt() {
    if (!promptModal) return;
    promptLoading.style.display = 'flex';
    promptCodeWrap.style.display = 'none';
    promptPreview.style.display = 'none';
    promptStatus.textContent = '';
    try {
      const res = await fetch('/main?api=news&action=get_writing_prompt', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const data = await res.json();
      if (data.ok) {
        promptRawText = data.prompt || '';
        promptLoading.style.display = 'none';
        const activeView = document.querySelector('.npo-prompt-toggle-btn.active');
        updatePromptView(activeView ? activeView.dataset.view : 'preview');
        promptStatus.textContent = 'Token expires in ' + Math.round(data.token_expires_in / 60) + ' min. API: ' + data.api_url;
      } else {
        promptStatus.textContent = 'Failed to load prompt: ' + (data.error || 'unknown error');
        promptLoading.style.display = 'none';
      }
    } catch (e) {
      promptStatus.textContent = 'Error loading prompt: ' + e.message;
      promptLoading.style.display = 'none';
    }
  }

  promptBtn?.addEventListener('click', () => {
    promptModal.style.display = 'flex';
    // Default to preview view
    promptToggleBtns.forEach(b => b.classList.remove('active'));
    document.querySelector('.npo-prompt-toggle-btn[data-view="preview"]')?.classList.add('active');
    loadWritingPrompt();
  });

  promptClose?.addEventListener('click', () => {
    promptModal.style.display = 'none';
  });

  promptModal?.addEventListener('click', (e) => {
    if (e.target === promptModal) promptModal.style.display = 'none';
  });

  promptCopyBtn?.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(promptRawText);
      promptCopyBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Copied!';
      setTimeout(() => {
        promptCopyBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copy';
      }, 2000);
    } catch {
      // Fallback: create temporary textarea
      const ta = document.createElement('textarea');
      ta.value = promptRawText;
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      ta.remove();
      promptCopyBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Copied!';
      setTimeout(() => {
        promptCopyBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copy';
      }, 2000);
    }
  });

  promptRefreshBtn?.addEventListener('click', () => {
    loadWritingPrompt();
  });

  // Load highlight.js from CDN
  (function() {
    if (typeof hljs !== 'undefined') return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css';
    document.head.appendChild(link);
    
    // Also add light theme that can be overridden
    const linkLight = document.createElement('link');
    linkLight.rel = 'stylesheet';
    linkLight.href = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css';
    linkLight.media = '(prefers-color-scheme: light)';
    document.head.appendChild(linkLight);
    
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js';
    script.onload = () => {
      // If prompt is already loaded, re-highlight
      if (promptRawText) {
        applyHighlighting();
      }
    };
    document.body.appendChild(script);
  })();

  // Expose helpers for outer scope
  window.getBodyValue = getBodyValue;
  window.setBodyValue = setBodyValue;

  // ── Load Monaco Editor ────────────────────────────────────────────────────
  (function() {
    const monacoContainer = document.getElementById('npo-body-monaco');
    if (!monacoContainer) return;
    const loadMonaco = window.apLoadMonaco || function(){
      if (window.monaco) return Promise.resolve(window.monaco);
      if (window.__apMonacoPromise) return window.__apMonacoPromise;
      window.__apMonacoPromise = new Promise((resolve, reject) => {
        const localVs = '/project/assets/monaco-0.45.0/vs';
        const loadEditor = (vsPath) => {
          try {
            require.config({ paths: { vs: vsPath } });
            require(['vs/editor/editor.main'], () => {
              if (typeof define === 'function' && (!require.defined || !require.defined('vs/editor/editor.api'))) define('vs/editor/editor.api', [], () => window.monaco);
              resolve(window.monaco);
            }, reject);
          } catch (e) {
            reject(e);
          }
        };
        const loadScript = (src, vsPath) => {
          const existing = document.querySelector('script[src="' + src + '"]');
          const script = existing || document.createElement('script');
          script.addEventListener('load', () => loadEditor(vsPath), { once: true });
          script.addEventListener('error', reject, { once: true });
          if (!existing) {
            script.src = src;
            document.head.appendChild(script);
          }
        };
        if (typeof require === 'function') {
          loadEditor(localVs);
          return;
        }
        loadScript(localVs + '/loader.js', localVs);
      });
      return window.__apMonacoPromise;
    };
    loadMonaco().then(() => {
        const isDark = !document.body.classList.contains('theme-light');
        bodyMonaco = monaco.editor.create(monacoContainer, {
          value: bodyTextarea ? bodyTextarea.value : '',
          language: 'markdown',
          theme: isDark ? 'vs-dark' : 'vs',
          minimap: { enabled: true, maxColumn: 80, renderCharacters: false },
          fontSize: 13,
          fontFamily: "'SF Mono','Fira Code','Cascadia Code','JetBrains Mono',Consolas,'Courier New',monospace",
          lineHeight: 22,
          padding: { top: 12, bottom: 12 },
          scrollBeyondLastLine: false,
          wordWrap: 'on',
          automaticLayout: true,
          tabSize: 2,
          renderLineHighlight: 'gutter',
          scrollbar: { vertical: 'auto', horizontal: 'auto', verticalScrollbarSize: 8, horizontalScrollbarSize: 8 },
          overviewRulerLanes: 0,
          hideCursorInOverviewRuler: true,
          overviewRulerBorder: false
        });
        bodyMonaco.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyB, () => applyMdAction('bold'));
        bodyMonaco.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyI, () => applyMdAction('italic'));
        bodyMonaco.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyK, () => applyMdAction('link'));
        bodyMonaco.onDidChangeModelContent(() => {
          if (bodyTextarea) bodyTextarea.value = bodyMonaco.getValue();
        });
        // Observe theme changes
        const observer = new MutationObserver(() => {
          const dark = !document.body.classList.contains('theme-light');
          monaco.editor.setTheme(dark ? 'vs-dark' : 'vs');
        });
        observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
    }).catch(error => console.error('Failed to load Monaco:', error));
  })();

  window.openNewsPortal = openNewsPortal;
})();
</script>
