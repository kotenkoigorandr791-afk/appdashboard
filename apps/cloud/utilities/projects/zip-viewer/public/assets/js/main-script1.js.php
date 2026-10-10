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
let scale = 1.0;
let pageRendering = false;
let pageNumPending = null;

const pdfViewer = document.getElementById('pdf-viewer');
const prevBtn = document.getElementById('pdf-prev');
const nextBtn = document.getElementById('pdf-next');
const currentPageSpan = document.getElementById('pdf-current-page');
const totalPagesSpan = document.getElementById('pdf-total-pages');
const scaleSelect = document.getElementById('pdf-scale');

function renderPage(num) {
    pageRendering = true;
    pdfDoc.getPage(num).then(function(page) {
        const viewport = page.getViewport({ scale: scale });
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        canvas.height = viewport.height;
        canvas.width = viewport.width;
        canvas.className = 'pdf-page';
        
        const renderContext = {
            canvasContext: ctx,
            viewport: viewport
        };
        
        const renderTask = page.render(renderContext);
        renderTask.promise.then(function() {
            pageRendering = false;
            if (pageNumPending !== null) {
                renderPage(pageNumPending);
                pageNumPending = null;
            }
        });
        
        pdfViewer.innerHTML = '';
        pdfViewer.appendChild(canvas);
        currentPageSpan.textContent = num;
        
        prevBtn.disabled = (num <= 1);
        nextBtn.disabled = (num >= totalPages);
    });
}

function queueRenderPage(num) {
    if (pageRendering) {
        pageNumPending = num;
    } else {
        renderPage(num);
    }
}

function onPrevPage() {
    if (currentPage > 1) {
        currentPage--;
        queueRenderPage(currentPage);
    }
}

function onNextPage() {
    if (currentPage < totalPages) {
        currentPage++;
        queueRenderPage(currentPage);
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
    const value = e.target.value;
    if (value === 'auto') {
        const containerWidth = pdfViewer.clientWidth;
        scale = containerWidth / 800;
    } else if (value === 'page-width') {
        scale = pdfViewer.clientWidth / 800;
    } else if (value === 'page-height') {
        scale = pdfViewer.clientHeight / 1100;
    } else {
        scale = parseFloat(value);
    }
    renderPage(currentPage);
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
