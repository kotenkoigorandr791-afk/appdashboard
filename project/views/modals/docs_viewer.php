<?php
$docLang = $language ?? 'en';
$docTheme = ($themeForAppLink ?? 'dark') === 'light' ? 'light' : 'dark';
$docColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($config['color'] ?? '')) ? $config['color'] : '#007bff';
$docsDir = dirname(__DIR__, 3) . '/docs';
$docsFiles = [];
if (is_dir($docsDir)) {
    foreach (scandir($docsDir) ?: [] as $file) {
        if (!preg_match('/^\d+_[a-z0-9_]+\.md$/i', $file)) continue;
        $filePath = $docsDir . '/' . $file;
        $content = @file_get_contents($filePath);
        if (!is_string($content)) continue;
        $title = $file;
        if (preg_match('/^#\s+(.+)$/m', $content, $m)) $title = trim($m[1]);
        $description = '';
        if (preg_match('/^##\s+(.+)$/m', $content, $m)) $description = trim($m[1]);
        $docsFiles[] = [
            'filename' => $file,
            'title' => $title,
            'description' => $description,
            'modified' => @filemtime($filePath) ?: time(),
            'readTime' => (int)ceil(max(1, str_word_count($content)) / 200),
        ];
    }
    usort($docsFiles, static fn($a, $b) => strcmp($a['filename'], $b['filename']));
}
$docT = static function (string $key, string $default = '') use ($translations): string {
    return (string)($translations[$key] ?? $default);
};
$c = $docColor;
$r = max(0, min(255, hexdec(substr($c, 1, 2)) - 30));
$g = max(0, min(255, hexdec(substr($c, 3, 2)) - 30));
$b = max(0, min(255, hexdec(substr($c, 5, 2)) - 30));
$docAccentHover = sprintf('#%02x%02x%02x', $r, $g, $b);
?>
<style>
#doc-app{
  --accent:<?php echo htmlspecialchars($docColor, ENT_QUOTES, 'UTF-8'); ?>;
  --accent-hover:<?php echo htmlspecialchars($docAccentHover, ENT_QUOTES, 'UTF-8'); ?>;
  --accent-dim:<?php echo htmlspecialchars($docColor, ENT_QUOTES, 'UTF-8'); ?>2e;
  display:flex;flex-direction:column;height:100%;min-height:0;overflow:hidden;
  font-family:'DM Sans',Segoe UI,Tahoma,sans-serif;line-height:1.6;
}
#doc-app[data-theme="dark"]{
  --bg-base:#0f1419;--bg-raised:rgba(18,24,32,.92);--bg-overlay:rgba(255,255,255,.08);--bg-hover:rgba(255,255,255,.12);
  --border:rgba(255,255,255,.14);--border-lit:rgba(255,255,255,.22);
  --text-primary:#f4f7fb;--text-secondary:rgba(255,255,255,.65);--text-muted:rgba(255,255,255,.42);
  --code-bg:rgba(18,24,32,.95);--table-border:rgba(255,255,255,.14);--table-header-bg:rgba(255,255,255,.08);
}
#doc-app[data-theme="light"]{
  --bg-base:#f5f5f5;--bg-raised:#fff;--bg-overlay:#f0f0f0;--bg-hover:#e8e8e8;
  --border:#e0e0e0;--border-lit:#c0c0c0;
  --text-primary:#1a1a1a;--text-secondary:#555;--text-muted:#888;
  --code-bg:#f6f8fa;--table-border:#e0e0e0;--table-header-bg:#f6f8fa;
}
#doc-app{background:var(--bg-base);color:var(--text-primary)}
#doc-app .doc-header{flex:0 0 auto;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 20px;background:var(--bg-raised);border-bottom:1px solid var(--border);backdrop-filter:blur(12px)}
#doc-app .doc-header-left{min-width:0;flex:1}
#doc-app .doc-title{font-size:18px;font-weight:700;background:linear-gradient(135deg,var(--text-primary),var(--accent));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;white-space:nowrap}
#doc-app .doc-subtitle{font-size:12px;color:var(--text-secondary);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
#doc-app .doc-header-actions{display:flex;align-items:center;gap:8px;flex-shrink:0}
#doc-app .doc-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;border:1px solid var(--border);background:var(--bg-overlay);color:var(--text-primary);transition:all .2s}
#doc-app .doc-btn:hover{background:var(--bg-hover);border-color:var(--border-lit);transform:translateY(-1px)}
#doc-app .search-box{position:relative;width:40px;height:40px;flex-shrink:0;transition:width .3s ease}
#doc-app .search-box.expanded{width:280px}
#doc-app .search-toggle{position:absolute;inset:0;width:40px;height:40px;border:1px solid var(--border);border-radius:50%;background:var(--bg-overlay);color:var(--text-primary);display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:2;padding:0}
#doc-app .search-toggle:hover{background:var(--bg-hover);border-color:var(--border-lit)}
#doc-app .search-box.expanded .search-toggle{background:transparent;border-color:transparent;color:var(--text-muted);pointer-events:none}
#doc-app .search-box input{position:absolute;inset:0;width:100%;height:40px;padding:0 14px 0 40px;border:1px solid var(--border);border-radius:10px;background:var(--bg-base);color:var(--text-primary);font-size:13px;opacity:0;pointer-events:none;box-sizing:border-box}
#doc-app .search-box.expanded input{opacity:1;pointer-events:auto;background:var(--bg-raised)}
#doc-app .search-box input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-dim)}
#doc-app .doc-container{flex:1;min-height:0;overflow:auto;padding:20px 24px;width:100%;box-sizing:border-box}
#doc-app .docs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;width:100%}
#doc-app .docs-grid.is-hidden,#doc-app .doc-view.is-hidden,#doc-app .doc-btn.is-hidden,#doc-app .docs-grid[hidden],#doc-app .doc-view[hidden],#doc-app .doc-btn[hidden]{display:none!important}
#doc-app .doc-tile{position:relative;text-align:left;display:flex;flex-direction:column;gap:12px;padding:20px;border:1px solid var(--border);border-radius:14px;background:var(--bg-raised);color:inherit;cursor:pointer;transition:all .25s cubic-bezier(.4,0,.2,1);overflow:hidden;font:inherit}
#doc-app .doc-tile::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--accent),var(--accent-dim));opacity:0;transition:opacity .25s}
#doc-app .doc-tile:hover{border-color:var(--accent);transform:translateY(-3px);box-shadow:0 12px 28px rgba(0,0,0,.16)}
#doc-app .doc-tile:hover::before{opacity:1}
#doc-app .doc-tile-icon{width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,var(--accent-dim),transparent);display:flex;align-items:center;justify-content:center;color:var(--accent)}
#doc-app .doc-tile:hover .doc-tile-icon{transform:scale(1.05) rotate(-3deg)}
#doc-app .doc-tile-badge{position:absolute;top:14px;right:14px;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;background:var(--accent-dim);color:var(--accent)}
#doc-app .doc-tile-title{font-size:15px;font-weight:650;color:var(--text-primary);line-height:1.35;padding-right:70px}
#doc-app .doc-tile-desc{font-size:13px;color:var(--text-secondary);line-height:1.55;flex:1;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
#doc-app .doc-tile-meta{display:flex;align-items:center;gap:14px;font-size:11px;color:var(--text-muted);padding-top:10px;border-top:1px solid var(--border)}
#doc-app .doc-empty{opacity:.6;padding:48px;text-align:center}
#doc-app .doc-view{width:100%;animation:docFadeIn .28s ease}
@keyframes docFadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
#doc-app .doc-view-header{padding:8px 0 18px;border-bottom:1px solid var(--border);margin-bottom:20px;display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap}
#doc-app .doc-view-title{margin:0;font-size:22px;font-weight:700;line-height:1.25;color:var(--text-primary)}
#doc-app .doc-view-meta{display:flex;gap:16px;font-size:12px;color:var(--text-muted)}
#doc-app .doc-view-content{display:grid;grid-template-columns:210px minmax(0,1fr);gap:24px;width:100%}
#doc-app .doc-toc{position:sticky;top:0;align-self:start;max-height:calc(100% - 8px);overflow:auto;padding:14px;border:1px solid var(--border);border-radius:12px;background:var(--bg-raised)}
#doc-app .doc-toc-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;color:var(--text-muted)}
#doc-app .doc-toc-list{list-style:none;margin:0;padding:0}
#doc-app .doc-toc-link{display:block;padding:7px 8px;border-radius:7px;color:var(--text-secondary);text-decoration:none;font-size:13px}
#doc-app .doc-toc-link:hover{background:var(--accent-dim);color:var(--accent)}
#doc-app .doc-content-body{min-width:0;font-size:15px;color:var(--text-primary)}
#doc-app .doc-content-body h1,#doc-app .doc-content-body h2,#doc-app .doc-content-body h3{margin:1.3em 0 .55em;line-height:1.25;color:var(--text-primary)}
#doc-app .doc-content-body h1{font-size:26px}#doc-app .doc-content-body h2{font-size:20px}#doc-app .doc-content-body h3{font-size:17px}
#doc-app .doc-content-body p{margin:0 0 1em}
#doc-app .doc-content-body a{color:var(--accent);text-decoration:none;border-bottom:1px solid transparent}
#doc-app .doc-content-body a:hover{border-bottom-color:var(--accent)}
#doc-app .doc-content-body code{font-family:'JetBrains Mono',ui-monospace,monospace;font-size:13px;background:var(--code-bg);padding:2px 6px;border-radius:5px;border:1px solid var(--border)}
#doc-app .doc-content-body pre{background:var(--code-bg);border:1px solid var(--border);border-radius:12px;padding:16px;overflow:auto;margin:16px 0}
#doc-app .doc-content-body pre code{background:none;border:none;padding:0}
#doc-app .doc-content-body ul,#doc-app .doc-content-body ol{margin:0 0 1em;padding-left:24px}
#doc-app .doc-content-body blockquote{border-left:4px solid var(--accent);margin:16px 0;padding:12px 16px;background:var(--bg-overlay);border-radius:0 8px 8px 0;color:var(--text-secondary)}
#doc-app .doc-content-body table{width:100%;border-collapse:collapse;margin:16px 0;border:1px solid var(--table-border);border-radius:8px;overflow:hidden}
#doc-app .doc-content-body th,#doc-app .doc-content-body td{border:1px solid var(--table-border);padding:10px 12px;text-align:left}
#doc-app .doc-content-body th{background:var(--table-header-bg);font-size:12px;text-transform:uppercase;letter-spacing:.4px}
#doc-app .doc-content-body hr{border:none;border-top:1px solid var(--border);margin:24px 0}
#doc-app .doc-content-body img{max-width:100%;border-radius:8px;margin:12px 0}
@media (max-width:900px){
  #doc-app .doc-view-content{grid-template-columns:1fr}
  #doc-app .doc-toc{position:static;max-height:none}
  #doc-app .doc-container{padding:14px 16px}
  #doc-app .docs-grid{grid-template-columns:1fr}
}
</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<div id="doc-app" class="doc-app" data-theme="<?php echo htmlspecialchars($docTheme, ENT_QUOTES, 'UTF-8'); ?>">
    <header class="doc-header">
        <div class="doc-header-left">
            <div class="doc-title"><?php echo htmlspecialchars($docT('documentation', 'Documentation')); ?></div>
            <div class="doc-subtitle"><?php echo htmlspecialchars($docT('documentation_subtitle', 'Browse and read project documentation')); ?></div>
        </div>
        <div class="doc-header-actions">
            <div class="search-box" id="docSearchBox">
                <button type="button" class="search-toggle" data-doc-action="toggle-search" aria-label="Search">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </button>
                <input type="text" id="docSearchInput" placeholder="<?php echo htmlspecialchars($docT('search_docs', 'Search documentation...')); ?>">
            </div>
            <button type="button" class="doc-btn is-hidden" id="docBackToGridBtn" data-doc-action="show-grid">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
                <span><?php echo htmlspecialchars($docT('back_to_docs', 'Back to List')); ?></span>
            </button>
        </div>
    </header>
    <main class="doc-container">
        <div id="docsGrid" class="docs-grid">
            <?php if (!$docsFiles): ?>
                <div class="doc-empty"><?php echo htmlspecialchars($docT('no_docs_found', 'No documentation files found')); ?></div>
            <?php else: foreach ($docsFiles as $index => $doc): ?>
                <button type="button" class="doc-tile" data-doc-action="open" data-filename="<?php echo htmlspecialchars($doc['filename'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if ($index < 3): ?><span class="doc-tile-badge">Popular</span><?php endif; ?>
                    <div class="doc-tile-icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    </div>
                    <div class="doc-tile-title"><?php echo htmlspecialchars($doc['title']); ?></div>
                    <div class="doc-tile-desc"><?php echo htmlspecialchars($doc['description']); ?></div>
                    <div class="doc-tile-meta"><?php echo (int)$doc['readTime']; ?> <?php echo htmlspecialchars($docT('minutes', 'min')); ?> · <?php echo date('M d, Y', (int)$doc['modified']); ?></div>
                </button>
            <?php endforeach; endif; ?>
        </div>
        <div id="docView" class="doc-view is-hidden">
            <div class="doc-view-header">
                <h1 class="doc-view-title" id="docViewTitle"></h1>
                <div class="doc-view-meta"><span id="docViewReadTime"></span><span id="docViewModified"></span></div>
            </div>
            <div class="doc-view-content">
                <nav class="doc-toc" id="docTOC"><div class="doc-toc-title"><?php echo htmlspecialchars($docT('table_of_contents', 'Table of Contents')); ?></div><ul class="doc-toc-list" id="docTOCList"></ul></nav>
                <article class="doc-content-body" id="docContent"></article>
            </div>
        </div>
    </main>
</div>
<script>
window.__DOC_APP_DATA__ = <?php echo json_encode(['files' => $docsFiles, 'i18n' => ['minutes' => $docT('minutes', 'min'), 'updated' => $docT('last_updated', 'Updated'), 'error' => $docT('error_loading_documentation', 'Error loading documentation')], 'api' => '/doc_viewer.php'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
(function(){
    function setHidden(el, on){
        if (!el) return;
        el.classList.toggle('is-hidden', !!on);
        if (on) el.setAttribute('hidden', '');
        else el.removeAttribute('hidden');
    }
    function ensureMarkdown(cb){
        if (window.SimpleMarkdown && typeof window.SimpleMarkdown.parse === 'function') { cb(); return; }
        if (window.__DOC_MD_WAITERS__) { window.__DOC_MD_WAITERS__.push(cb); return; }
        window.__DOC_MD_WAITERS__ = [cb];
        var prevDefine = window.define;
        try { window.define = undefined; } catch (e) {}
        var s = document.createElement('script');
        s.src = '/assets/simple-markdown.js?v=docs-inline2';
        s.async = true;
        s.dataset.docMd = '1';
        s.onload = function(){
            try { window.define = prevDefine; } catch (e) {}
            var waiters = window.__DOC_MD_WAITERS__ || [];
            window.__DOC_MD_WAITERS__ = null;
            if (!window.SimpleMarkdown || typeof window.SimpleMarkdown.parse !== 'function') {
                console.error('SimpleMarkdown missing after script load');
                return;
            }
            waiters.forEach(function(fn){ try { fn(); } catch (err) { console.error(err); } });
        };
        s.onerror = function(){
            try { window.define = prevDefine; } catch (e) {}
            window.__DOC_MD_WAITERS__ = null;
            alert('Failed to load markdown parser');
        };
        document.head.appendChild(s);
    }
    function showGrid(){
        setHidden(document.getElementById('docsGrid'), false);
        setHidden(document.getElementById('docView'), true);
        setHidden(document.getElementById('docBackToGridBtn'), true);
    }
    function openDoc(filename){
        var data = window.__DOC_APP_DATA__ || {};
        var files = data.files || [];
        var i18n = data.i18n || {};
        ensureMarkdown(function(){
            fetch((data.api || '/doc_viewer.php') + '?file=' + encodeURIComponent(filename), { credentials: 'same-origin' })
                .then(function(r){ if (!r.ok) throw new Error('File not found'); return r.json(); })
                .then(function(res){
                    var md = res.content || '';
                    document.getElementById('docContent').innerHTML = window.SimpleMarkdown.parse(md);
                    var first = md.split('\n').find(function(l){ return l.indexOf('# ') === 0; });
                    document.getElementById('docViewTitle').textContent = first ? first.replace(/^#\s+/, '') : filename;
                    var meta = files.find(function(d){ return d.filename === filename; });
                    if (meta) {
                        document.getElementById('docViewReadTime').textContent = meta.readTime + ' ' + (i18n.minutes || 'min');
                        document.getElementById('docViewModified').textContent = (i18n.updated || 'Updated') + ': ' + new Date(meta.modified * 1000).toLocaleDateString();
                    }
                    var tocList = document.getElementById('docTOCList');
                    tocList.innerHTML = '';
                    (window.SimpleMarkdown.extractTOC(md) || []).forEach(function(item){
                        var li = document.createElement('li');
                        var a = document.createElement('a');
                        a.href = '#' + item.id;
                        a.className = 'doc-toc-link';
                        a.textContent = item.text;
                        a.addEventListener('click', function(e){
                            e.preventDefault();
                            var el = document.getElementById(item.id);
                            if (el) el.scrollIntoView({ behavior: 'smooth' });
                        });
                        li.appendChild(a);
                        tocList.appendChild(li);
                    });
                    setHidden(document.getElementById('docsGrid'), true);
                    setHidden(document.getElementById('docView'), false);
                    setHidden(document.getElementById('docBackToGridBtn'), false);
                    document.querySelectorAll('#docContent a').forEach(function(link){
                        var href = link.getAttribute('href') || '';
                        if (href.slice(-3) === '.md') {
                            link.addEventListener('click', function(e){
                                e.preventDefault();
                                var target = href.replace(/^\.?\//, '');
                                var hit = files.find(function(d){ return d.filename === target || d.filename.indexOf(target) !== -1; });
                                if (hit) openDoc(hit.filename);
                            });
                        }
                    });
                })
                .catch(function(err){ console.error(err); alert((i18n.error || 'Error') + ': ' + err.message); });
        });
    }
    function bind(){
        var root = document.getElementById('doc-app');
        if (!root || root.dataset.docBound) return;
        root.dataset.docBound = '1';
        root.addEventListener('click', function(e){
            var el = e.target.closest('[data-doc-action]');
            if (!el || !root.contains(el)) return;
            var action = el.getAttribute('data-doc-action');
            if (action === 'open') openDoc(el.getAttribute('data-filename') || '');
            else if (action === 'show-grid') showGrid();
            else if (action === 'toggle-search') {
                var box = document.getElementById('docSearchBox');
                var input = document.getElementById('docSearchInput');
                if (box) box.classList.add('expanded');
                if (input) input.focus();
            }
        });
        var input = document.getElementById('docSearchInput');
        if (input) {
            input.addEventListener('input', function(){
                var q = input.value.toLowerCase();
                root.querySelectorAll('.doc-tile').forEach(function(tile){
                    var t = (tile.querySelector('.doc-tile-title') || {}).textContent || '';
                    var d = (tile.querySelector('.doc-tile-desc') || {}).textContent || '';
                    var f = tile.getAttribute('data-filename') || '';
                    tile.style.display = (!q || t.toLowerCase().indexOf(q) !== -1 || d.toLowerCase().indexOf(q) !== -1 || f.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
                });
            });
            input.addEventListener('blur', function(){
                if (!input.value) {
                    var box = document.getElementById('docSearchBox');
                    if (box) box.classList.remove('expanded');
                }
            });
        }
    }
    window.DocsPanel = {
        init: function(){ bind(); showGrid(); },
        refresh: function(){ bind(); }
    };
    bind();
})();
</script>
