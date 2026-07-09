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

// Load AI models from server config
async function loadAiModels() {
    const modelSelect = document.getElementById('npo-ai-model');
    if (!modelSelect) return;
    
    try {
        const response = await fetch('/main?api=cloud_ai_config');
        const data = await response.json();
        const models = data.config?.models || [];
        const savedModel = localStorage.getItem('ai_model_news_ai');
        
        modelSelect.innerHTML = '<option value="">-- Select Model --</option>';
        
        models.forEach(model => {
            const option = document.createElement('option');
            option.value = model;
            option.textContent = model;
            if (savedModel === model) option.selected = true;
            modelSelect.appendChild(option);
        });
    } catch (error) {
        console.error('Failed to load AI models:', error);
    }
}

// Save selected model
function saveSelectedModel() {
    const modelSelect = document.getElementById('npo-ai-model');
    if (!modelSelect) return;
    
    modelSelect.addEventListener('change', () => {
        localStorage.setItem('ai_model_news_ai', modelSelect.value);
        markAsChanged();
    });
}

// Generate content with selected AI model
async function generateWithAi() {
    const prompt = document.getElementById('npo-ai-prompt').value.trim();
    const modelSelect = document.getElementById('npo-ai-model');
    const selectedModel = modelSelect ? modelSelect.value : '';
    const statusEl = document.getElementById('npo-ai-status');
    const currentTitle = document.getElementById('npo-edit-title').value.trim();
    const currentBody = document.getElementById('npo-edit-body').value.trim();
    
    if (!prompt) {
        statusEl.textContent = 'Please enter a prompt';
        return;
    }
    
    statusEl.textContent = 'Generating...';
    
    try {
        const requestData = {
            prompt,
            model: selectedModel,
            current_title: currentTitle,
            current_body: currentBody
        };
        
        const response = await fetch('/news?api=news&action=ai_generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
        });
        const data = await response.json();
        
        if (data.ok) {
            document.getElementById('npo-edit-title').value = data.title || currentTitle;
            document.getElementById('npo-edit-body').value = data.body || currentBody;
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
    originalBody = document.getElementById('npo-edit-body').value.trim();
    originalPhoto = document.getElementById('npo-edit-photo').value.trim();
    
    const saveBtn = document.getElementById('npo-save-btn');
    if (saveBtn) saveBtn.style.display = 'none';
}

// Check if content has changed
function checkChanges() {
    const currentTitle = document.getElementById('npo-edit-title').value.trim();
    const currentBody = document.getElementById('npo-edit-body').value.trim();
    const currentPhoto = document.getElementById('npo-edit-photo').value.trim();
    
    if (currentTitle !== originalTitle || currentBody !== originalBody || currentPhoto !== originalPhoto) {
        markAsChanged();
    }
}

// Initialize news editor
function initNewsEditor() {
    const titleEl = document.getElementById('npo-edit-title');
    const bodyEl = document.getElementById('npo-edit-body');
    const photoEl = document.getElementById('npo-edit-photo');
    const saveBtn = document.getElementById('npo-save-btn');
    
    if (!titleEl || !bodyEl || !saveBtn) {
        console.error('News editor elements not found');
        return;
    }
    
    // Set initial values
    originalTitle = titleEl.value.trim();
    originalBody = bodyEl.value.trim();
    originalPhoto = photoEl ? photoEl.value.trim() : '';
    
    // Hide save button initially
    saveBtn.style.display = 'none';
    
    // Add event listeners for changes
    titleEl.addEventListener('input', checkChanges);
    bodyEl.addEventListener('input', checkChanges);
    if (photoEl) photoEl.addEventListener('change', markAsChanged);
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
#news-portal-overlay{display:none;position:fixed;inset:0;z-index:12000;background:var(--modal-bg,#0f172a);color:var(--text-color,#e2e8f0);flex-direction:column;overflow:hidden;}
#news-portal-overlay.active{display:flex;}
/* Header */
#npo-header{display:flex;align-items:center;gap:0;padding:0;border-bottom:1px solid var(--border-color);background:var(--group-block-bg);flex-shrink:0;height:56px;}
#npo-back{display:flex;align-items:center;gap:8px;padding:0 20px;height:100%;border:none;background:none;color:var(--text-color);font-size:14px;font-weight:600;cursor:pointer;border-right:1px solid var(--border-color);transition:background .15s;white-space:nowrap;}
#npo-back:hover{background:var(--app-item-bg);}
#npo-back svg{opacity:.7;}
#npo-title{padding:0 20px;font-size:17px;font-weight:700;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
#npo-tabs{display:flex;height:100%;gap:0;}
.npo-tab{height:100%;padding:0 22px;border:none;background:none;color:var(--text-color);font-size:14px;font-weight:600;cursor:pointer;border-bottom:3px solid transparent;opacity:.6;transition:opacity .15s,border-color .15s;white-space:nowrap;}
.npo-tab:hover{opacity:.85;}
.npo-tab.active{opacity:1;border-bottom-color:var(--primary-color,#38bdf8);}
#npo-add-btn{display:flex;align-items:center;gap:7px;margin:0 16px;padding:8px 18px;background:var(--primary-color,#38bdf8);color:#fff;border:none;border-radius:20px;cursor:pointer;font-weight:600;font-size:13px;transition:opacity .15s;white-space:nowrap;flex-shrink:0;}
#npo-add-btn:hover{opacity:.88;}
/* Body */
#npo-body{display:flex;flex:1;min-height:0;overflow:hidden;}
/* Sidebar – news list */
#npo-sidebar{width:320px;flex-shrink:0;border-right:1px solid var(--border-color);display:flex;flex-direction:column;overflow:hidden;background:color-mix(in srgb,var(--modal-bg) 88%,var(--primary-color) 12%);}
#npo-sidebar-header{padding:14px 16px 10px;border-bottom:1px solid var(--border-color);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;opacity:.55;}
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
#npo-save-btn{padding:11px 28px;background:var(--primary-color,#38bdf8);color:#fff;border:none;border-radius:20px;font-weight:700;font-size:14px;cursor:pointer;transition:opacity .15s;}
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
#npo-canvas-save-btn{padding:8px 20px;background:var(--primary-color,#38bdf8);color:#fff;border:none;border-radius:20px;font-size:13px;font-weight:700;cursor:pointer;transition:opacity .15s;}
#npo-canvas-save-btn:hover{opacity:.88;}
#npo-canvas-status{font-size:12px;min-height:18px;}
/* Empty state */
#npo-editor-empty{display:flex;align-items:center;justify-content:center;flex:1;min-height:0;text-align:center;opacity:.45;font-size:15px;padding:48px;}
/* Responsive */
@media(max-width:760px){
  #npo-sidebar{width:100%;max-height:200px;border-right:none;border-bottom:1px solid var(--border-color);}
  #npo-body{flex-direction:column;}
  .npo-pane{padding:18px 14px;}
}
</style>

<div id="news-portal-overlay">
  <div id="npo-header">
    <button id="npo-back" type="button">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
      <?php echo my_htmlspecialchars($translations['close'] ?? 'Close'); ?>
    </button>
    <div id="npo-title"><?php echo my_htmlspecialchars($translations['news_portal_title'] ?? 'News Portal'); ?></div>
    <div id="npo-tabs">
      <button class="npo-tab active" data-pane="editor" type="button"><?php echo my_htmlspecialchars($translations['news_add'] ?? 'Editor'); ?></button>
    </div>
    <?php if ($newsCanEdit): ?>
    <button id="npo-add-btn" type="button">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <?php echo my_htmlspecialchars($translations['news_add'] ?? 'Add news'); ?>
    </button>
    <?php endif; ?>
  </div>
  <div id="npo-body">
    <div id="npo-sidebar">
      <div id="npo-sidebar-header"><?php echo my_htmlspecialchars($translations['news_portal_title'] ?? 'Articles'); ?></div>
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
               <button type="button" id="npo-ai-generate-btn" onclick="generateWithAi()">
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
          <div class="npo-field">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
              <label style="margin-bottom:0"><?php echo my_htmlspecialchars($translations['news_body_label'] ?? 'Content'); ?></label>
              <button type="button" id="npo-ai-toggle-btn" style="padding:4px 12px;border:1px solid var(--border-color);border-radius:20px;background:var(--group-block-bg);color:var(--text-color);font-size:11px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:5px;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.26L22 9.27l-5 5.14 1.18 7.09L12 18.27l-6.18 3.23L7 14.41 2 9.27l7.1-1.01z"/></svg>
                AI
              </button>
            </div>
            <textarea id="npo-edit-body" placeholder="<?php echo my_htmlspecialchars($translations['news_body_placeholder'] ?? 'Article text...'); ?>"></textarea>
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
          <label class="npo-toggle">
            <input type="checkbox" id="npo-published">
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

<script>
(function(){
  const canEdit = <?php echo $newsCanEdit ? 'true' : 'false'; ?>;
  const overlay = document.getElementById('news-portal-overlay');
  if (!overlay) return;

  const listEl = document.getElementById('npo-list');
  const editorEmpty = document.getElementById('npo-editor-empty');
  const editorForm = document.getElementById('npo-editor-form');
  const aiPanel = document.getElementById('npo-ai-panel');

  let newsItems = [];
  let newsLayout = {};
  let selectedId = null;
  let uploadingPhoto = false;

  // ── Open / Close ───────────────────────────────────────────────────────────
  function openNewsPortal() {
    overlay.classList.add('active');
    loadNews();
  }
  function closeNewsPortal() { overlay.classList.remove('active'); }

  document.getElementById('npo-back').addEventListener('click', closeNewsPortal);

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

  // ── Load news ──────────────────────────────────────────────────────────────
  function loadNews() {
    fetch('/main?api=news&action=list')
      .then(r => r.json())
      .then(data => {
        if (!data.ok) return;
        newsItems = data.items || [];
        newsLayout = data.layout || {};
        renderList();
        if (selectedId) {
          const item = newsItems.find(n => n.id === selectedId);
          if (item) openEditor(item); else closeEditor();
        }
      });
  }

  function escH(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function fmtDate(ts) {
    if (!ts) return '';
    return new Date(ts * 1000).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
  }

  // ── Sidebar list ───────────────────────────────────────────────────────────
  function renderList() {
    if (!newsItems.length) {
      listEl.innerHTML = '<div style="padding:24px;text-align:center;opacity:.45;font-size:13px;"><?php echo my_htmlspecialchars($translations['news_empty'] ?? 'No news yet.'); ?></div>';
      return;
    }
    listEl.innerHTML = '';
    newsItems.forEach(item => {
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
      listEl.appendChild(el);
    });
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
    document.getElementById('npo-published').checked = item ? !!item.published : false;
    document.getElementById('npo-photo-url').value = item ? (item.photo || '') : '';
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
    const body = document.getElementById('npo-edit-body').value.trim();
    const published = document.getElementById('npo-published').checked;
    const photo = document.getElementById('npo-photo-url').value.trim();
    if (!title) { setStatus('<?php echo my_htmlspecialchars($translations['news_title_required'] ?? 'Title is required'); ?>'); return; }
    setSaving(true);
    setStatus('<?php echo my_htmlspecialchars($translations['saving'] ?? 'Saving...'); ?>');
    try {
      const res = await fetch('/main?api=news&action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ id, title, body, published, photo })
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
    const btn = document.getElementById('npo-ai-generate-btn');
    const aiStatus = document.getElementById('npo-ai-status');
    btn.disabled = true;
    aiStatus.textContent = '<?php echo my_htmlspecialchars($translations['generating'] ?? 'Generating...'); ?>';
    try {
      const res = await fetch('/main?api=news&action=ai_generate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ prompt })
      });
      const data = await res.json();
      if (data.ok) {
        if (data.title) document.getElementById('npo-edit-title').value = data.title;
        if (data.body) document.getElementById('npo-edit-body').value = data.body;
        aiStatus.textContent = '<?php echo my_htmlspecialchars($translations['done'] ?? 'Done!'); ?>';
        setTimeout(() => { aiStatus.textContent = ''; aiPanel.classList.remove('open'); }, 1200);
      } else {
        aiStatus.textContent = data.error || 'AI error';
      }
    } catch { aiStatus.textContent = 'Request failed'; }
    btn.disabled = false;
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

  window.openNewsPortal = openNewsPortal;
})();
</script>
