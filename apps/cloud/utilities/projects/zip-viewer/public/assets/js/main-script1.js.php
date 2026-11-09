document.addEventListener('keydown', function(e) {
    if (e.target.matches('input, textarea, [contenteditable]')) return;
    
    if (e.altKey && e.key === 'r') { 
        e.preventDefault(); 
        window.location.href = '<?= buildUrl(['path' => $currentPath, 'view' => null]) ?>';
    }
    
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        const items = document.querySelectorAll('.file-item');
        if (items.length === 0) return;
        
        const current = document.querySelector('.file-item.selected');
        let index = Array.from(items).indexOf(current);
        
        if (e.key === 'ArrowDown') {
            index = Math.min(index + 1, items.length - 1);
        } else {
            index = Math.max(index - 1, 0);
        }
        
        if (index >= 0 && items[index]) {
            items[index].scrollIntoView({ block: 'nearest' });
            items[index].click();
        }
    }
    
    if (e.key === 'Enter') { 
        const selected = document.querySelector('.file-item.selected'); 
        if (selected) selected.click(); 
    }
    
    if (e.key === ' ' || e.key === 'Space') { 
        e.preventDefault(); 
        const selected = document.querySelector('.file-item.selected'); 
        if (selected && selected.dataset.type === 'file') selected.click(); 
    }
    
    if (e.key === 'Escape') { 
        <?php if ($viewFile): ?>
            window.location.href = '<?= buildUrl(['view' => null, 'path' => $currentPath]) ?>'; 
        <?php endif; ?>
    }
    
    if (e.key === 'Backspace' && !e.target.matches('input, textarea')) {
        e.preventDefault();
        <?php if ($currentPath !== ''): ?>
            window.location.href = '<?= buildUrl(['path' => getParentPath($currentPath), 'view' => null]) ?>';
        <?php elseif (count($archiveChain) > 1): ?>
            <?php 
            $parentArchive = $archiveChain[count($archiveChain) - 2];
            $parentChain = array_slice($archiveChain, 0, -1);
            ?>
            window.location.href = '?archive=<?= urlencode($parentArchive['path']) ?>&parent=<?= urlencode(json_encode(array_slice($parentChain, 0, -1))) ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&folderColor=<?= urlencode($folderColor) ?>&path=<?= urlencode($parentArchive['internal_path'] ?? '') ?>';
        <?php endif; ?>
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const image = document.getElementById('preview-image');
    if (image) {
        new Viewer(image, { 
            inline: false, 
            toolbar: true, 
            title: false, 
            navbar: false, 
            movable: true, 
            scalable: true, 
            rotatable: true 
        });
    }
    
    const video = document.getElementById('video-player');
    if (video) {
        new Plyr('#video-player', { 
            controls: ['play', 'progress', 'current-time', 'mute', 'volume', 'fullscreen'], 
            ratio: '16:9' 
        });
    }
    
    const audio = document.getElementById('audio-player');
    if (audio) {
        new Plyr('#audio-player', { 
            controls: ['play', 'progress', 'current-time', 'mute', 'volume'], 
            volume: 0.5 
        });
    }
    
    const items = document.querySelectorAll('.file-item');
    if (items.length > 0 && !document.querySelector('.file-item.selected')) {
        items[0].classList.add('selected');
    }
    document.querySelectorAll('.password-toggle').forEach((button) => {
        button.addEventListener('click', function() {
            const input = this.closest('form')?.querySelector('.archive-password-input');
            const icon = this.querySelector('i');
            if (!input || !icon) return;
            const visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            icon.className = visible ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
            this.title = visible ? 'Show password' : 'Hide password';
            input.focus();
        });
    });
});

<?php if ($previewType === 'monaco'): ?>
const shared = window.cloudMonacoShared;
shared.loadMonaco({
    lang: <?= json_encode($lang) ?>,
    i18nUrl: <?= json_encode(cloud_url('i18n/load.php')) ?>
}).then(function () {
    shared.defineAppThemes(monaco, {});
    const container = document.getElementById('monaco-container');
    if (container) {
        window.editor = monaco.editor.create(container, Object.assign({}, shared.standardEditorOptions(), {
            value: <?= json_encode($previewContent) ?>,
            language: '<?= $monacoLanguage ?>',
            theme: '<?= $theme === 'dark' ? 'custom-dark' : 'custom-light' ?>',
            automaticLayout: true,
            readOnly: true,
            minimap: { enabled: true },
            scrollBeyondLastLine: false,
            fontSize: 13,
            lineNumbers: 'on',
            wordWrap: 'on',
            tabSize: 4,
            padding: { top: 8, bottom: 8 },
            scrollbar: {
                vertical: 'auto',        // show only when scrolling
                horizontal: 'auto',      // show only when scrolling
                useShadows: false,
                verticalHasArrows: false,
                horizontalHasArrows: false,
                verticalScrollbarSize: 0, // zero size
                horizontalScrollbarSize: 0, // zero size
                arrowSize: 0,
                alwaysConsumeMouseWheel: false // allow wheel scrolling
            }
        }));

        // Same transliteration rules as the other Monaco editors.
        shared.installTransliteration({
            editor: window.editor,
            container: container,
            monaco: monaco,
            getLang: () => shared.resolveLang(<?= json_encode($lang) ?>)
        });
        shared.installStandardActions({
            editor: window.editor,
            monaco: monaco,
            container: container,
            aiEndpoint: false
        });
    }
}).catch(function (e) {
    console.error('Monaco failed to load:', e);
});

function copyToClipboard() { 
    const text = <?= json_encode($previewContent) ?>; 
    navigator.clipboard.writeText(text).then(() => {
        showAppModal('<?= htmlspecialchars($i18n['copy'] . '!', ENT_QUOTES) ?>', '<?= htmlspecialchars($i18n['copy'], ENT_QUOTES) ?>');
    });
}

<?php endif; ?>

<?php if ($previewType === 'pdf'): ?>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

const pdfData = atob('<?= $pdfData ?>');
const loadingTask = pdfjsLib.getDocument({ data: pdfData });
let pdfDoc = null;
let currentPage = 1;
let totalPages = 0;
let pdfScaleMode = 'auto';
let pdfRenderToken = 0;
let activeRenderTask = null;

const pdfViewer = document.getElementById('pdf-viewer');
const prevBtn = document.getElementById('pdf-prev');
const nextBtn = document.getElementById('pdf-next');
const currentPageSpan = document.getElementById('pdf-current-page');
const totalPagesSpan = document.getElementById('pdf-total-pages');
const scaleSelect = document.getElementById('pdf-scale');

function resolvePdfScale(page) {
    const base = page.getViewport({ scale: 1 });
    const availableWidth = Math.max(240, pdfViewer.clientWidth - 32);
    const availableHeight = Math.max(240, pdfViewer.clientHeight - 32);
    if (pdfScaleMode === 'auto' || pdfScaleMode === 'page-width') {
        return Math.max(0.1, availableWidth / base.width);
    }
    if (pdfScaleMode === 'page-height') {
        return Math.max(0.1, availableHeight / base.height);
    }
    return Math.max(0.1, parseFloat(pdfScaleMode) || 1);
}

async function renderPage(num) {
    const token = ++pdfRenderToken;
    if (activeRenderTask) {
        try { activeRenderTask.cancel(); } catch (_) {}
        activeRenderTask = null;
    }

    let page;
    try {
        page = await pdfDoc.getPage(num);
    } catch (err) {
        console.error('PDF page load failed:', err);
        return;
    }
    if (token !== pdfRenderToken) return;

    const cssScale = resolvePdfScale(page);
    const pixelRatio = Math.min(Math.max(window.devicePixelRatio || 1, 1), 2);
    const textViewport = page.getViewport({ scale: cssScale });
    const renderViewport = page.getViewport({ scale: cssScale * pixelRatio });
    const cssWidth = Math.max(1, Math.floor(textViewport.width));
    const cssHeight = Math.max(1, Math.floor(textViewport.height));

    const pageEl = document.createElement('div');
    pageEl.className = 'pdf-page';
    pageEl.style.width = cssWidth + 'px';
    pageEl.style.height = cssHeight + 'px';

    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.floor(renderViewport.width));
    canvas.height = Math.max(1, Math.floor(renderViewport.height));
    canvas.style.width = cssWidth + 'px';
    canvas.style.height = cssHeight + 'px';

    const textLayer = document.createElement('div');
    textLayer.className = 'textLayer';

    pageEl.appendChild(canvas);
    pageEl.appendChild(textLayer);
    pdfViewer.replaceChildren(pageEl);

    currentPageSpan.textContent = num;
    prevBtn.disabled = (num <= 1);
    nextBtn.disabled = (num >= totalPages);

    const renderTask = page.render({ canvasContext: canvas.getContext('2d'), viewport: renderViewport });
    activeRenderTask = renderTask;
    try {
        await renderTask.promise;
    } catch (err) {
        if (err && err.name === 'RenderingCancelledException') return;
        console.error('PDF page render failed:', err);
        return;
    }
    if (token !== pdfRenderToken) return;

    // Invisible text layer keeps the page selectable and searchable.
    try {
        const textContent = await page.getTextContent();
        if (token !== pdfRenderToken) return;
        const textLayerTask = pdfjsLib.renderTextLayer({
            textContentSource: textContent,
            container: textLayer,
            viewport: textViewport,
            textDivs: []
        });
        if (textLayerTask && textLayerTask.promise) await textLayerTask.promise;
    } catch (err) {
        console.error('PDF text layer failed:', err);
    }
}

function onPrevPage() {
    if (currentPage > 1) {
        currentPage--;
        renderPage(currentPage);
    }
}

function onNextPage() {
    if (currentPage < totalPages) {
        currentPage++;
        renderPage(currentPage);
    }
}

loadingTask.promise.then(function(pdf) {
    pdfDoc = pdf;
    totalPages = pdf.numPages;
    totalPagesSpan.textContent = totalPages;
    renderPage(1);
});

prevBtn.addEventListener('click', onPrevPage);
nextBtn.addEventListener('click', onNextPage);

scaleSelect.addEventListener('change', function(e) {
    pdfScaleMode = e.target.value;
    renderPage(currentPage);
});

let pdfResizeTimer = null;
window.addEventListener('resize', function() {
    if (!pdfDoc) return;
    if (!['auto', 'page-width', 'page-height'].includes(pdfScaleMode)) return;
    clearTimeout(pdfResizeTimer);
    pdfResizeTimer = setTimeout(function() { renderPage(currentPage); }, 150);
});

document.addEventListener('keydown', function(e) {
    if (e.target.matches('input, textarea, [contenteditable]')) return;
    if (!pdfDoc) return;
    
    if (e.key === 'ArrowLeft') {
        e.preventDefault();
        onPrevPage();
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        onNextPage();
    }
});
<?php endif; ?>

function checkMobile() { 
    const isMobile = window.innerWidth <= 768; 
    document.body.classList.toggle('mobile', isMobile); 
}

function showAppModal(message, title = '') {
    const overlay = document.getElementById('app-modal');
    const titleNode = document.getElementById('app-modal-title');
    const messageNode = document.getElementById('app-modal-message');
    const closeBtn = document.getElementById('app-modal-close');
    const okBtn = document.getElementById('app-modal-ok');
    if (!overlay || !titleNode || !messageNode || !closeBtn || !okBtn) return;

    titleNode.textContent = title || '<?= addslashes($i18n['title']) ?>';
    messageNode.textContent = message;
    overlay.classList.add('visible');
    overlay.setAttribute('aria-hidden', 'false');

    const handleEscape = (event) => {
        if (event.key === 'Escape') closeModal();
    };
    const closeModal = () => {
        overlay.classList.remove('visible');
        overlay.setAttribute('aria-hidden', 'true');
        document.removeEventListener('keydown', handleEscape);
    };

    closeBtn.onclick = closeModal;
    okBtn.onclick = closeModal;
    overlay.onclick = (event) => {
        if (event.target === overlay) closeModal();
    };
    document.addEventListener('keydown', handleEscape);
    okBtn.focus();
}

window.addEventListener('load', checkMobile);
window.addEventListener('resize', checkMobile);
