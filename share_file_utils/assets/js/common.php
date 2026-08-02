<?php
/** @var array $translations @var string $color @var bool $isFullscreen @var string $fileType @var mixed $fileContent */
?>
<script>
// Global variables
let editor = null;
let currentZoom = 1;
let currentRotation = 0;
let isZoomed = false;

// Global functions that need to be accessible from HTML
function toggleFullscreen() {
    const url = new URL(window.location.href);
    if (<?php echo $isFullscreen ? 'true' : 'false'; ?>) {
        url.searchParams.delete('fullscreen');
    } else {
        url.searchParams.set('fullscreen', '1');
    }
    window.location.href = url.toString();
}

function copyAllContent() {
    <?php if (($fileData['disable_copy'] ?? 0) === 1): ?>
    showToast('Copy disabled for this shared link', 3000);
    return;
    <?php endif; ?>
    // Priority 1: If Monaco editor is already loaded
    if (window.editor && typeof window.editor.getValue === 'function') {
        const content = window.editor.getValue();
        copyToClipboard(content);
        return;
    }
    
    // Priority 2: If this is a simple text file (e.g., .txt) and Monaco not loaded yet
    // Try to get content from DOM or PHP variable
    <?php if ($fileType === 'text' && $fileContent !== false && $fileContent !== 'FILE_TOO_LARGE'): ?>
    const fallbackContent = <?php echo json_encode($fileContent); ?>;
    if (fallbackContent) {
        copyToClipboard(fallbackContent);
        return;
    }
    <?php endif; ?>
    
    // If nothing worked
    showToast('Content not available for copying', 3000);
}

function copyToClipboard(text) {
    <?php if (($fileData['disable_copy'] ?? 0) === 1): ?>
    showToast('Copy disabled for this shared link', 3000);
    return;
    <?php endif; ?>
    // Use modern Clipboard API
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('<?php echo htmlspecialchars($translations['content_copied']); ?>', 3000);
        }).catch(err => {
            // Fallback to old method
            fallbackCopy(text);
        });
    } else {
        // Fallback for old browsers
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.opacity = '0';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showToast('<?php echo htmlspecialchars($translations['content_copied']); ?>', 3000);
        } else {
            showToast('Failed to copy content', 3000);
        }
    } catch (err) {
        showToast('Failed to copy content', 3000);
    }
    
    document.body.removeChild(textArea);
}

function handleMediaError(element) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-view';
    errorDiv.innerHTML = `<svg class="error-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><div><?php echo htmlspecialchars($translations['file_not_accessible']); ?></div>`;
    element.parentNode.replaceChild(errorDiv, element);
}

function openInfo() {
    document.getElementById('info-modal').style.display = 'flex';
}

function closeInfo() {
    document.getElementById('info-modal').style.display = 'none';
}

function openSettings() {
    document.getElementById('settings-modal').style.display = 'flex';
}

function closeSettings() {
    document.getElementById('settings-modal').style.display = 'none';
}

function applySettings() {
    const theme = document.getElementById('theme-select').value;
    const color = document.getElementById('color-picker').value.replace('#', '');
    const language = document.getElementById('language-select').value;
    const url = new URL(window.location.href);
    url.searchParams.set('theme', theme);
    url.searchParams.set('color', color);
    url.searchParams.set('lang', language);
    window.location.href = url.toString();
}

// Image manipulation functions
function zoomIn() {
    const image = document.getElementById('viewer-image');
    if (image) {
        currentZoom += 0.2;
        applyImageTransform();
    }
}

function zoomOut() {
    const image = document.getElementById('viewer-image');
    if (image) {
        currentZoom = Math.max(0.2, currentZoom - 0.2);
        applyImageTransform();
    }
}

function resetZoom() {
    const image = document.getElementById('viewer-image');
    if (image) {
        currentZoom = 1;
        currentRotation = 0;
        applyImageTransform();
    }
}

function rotateLeft() {
    const image = document.getElementById('viewer-image');
    if (image) {
        currentRotation -= 90;
        applyImageTransform();
    }
}

function rotateRight() {
    const image = document.getElementById('viewer-image');
    if (image) {
        currentRotation += 90;
        applyImageTransform();
    }
}

function applyImageTransform() {
    const image = document.getElementById('viewer-image');
    if (image) {
        image.style.transform = `scale(${currentZoom}) rotate(${currentRotation}deg)`;
        image.style.transition = 'transform 0.3s ease';
    }
}

function toggleZoom() {
    const image = document.getElementById('viewer-image');
    if (image) {
        if (!isZoomed) {
            image.dataset.originalWidth = image.offsetWidth;
            image.dataset.originalHeight = image.offsetHeight;
            image.style.position = 'fixed';
            image.style.top = '50%';
            image.style.left = '50%';
            image.style.transform = 'translate(-50%,-50%) scale(2)';
            image.style.zIndex = '1001';
            image.style.maxWidth = '90vw';
            image.style.maxHeight = '90vh';
            image.style.cursor = 'zoom-out';
            image.classList.add('zoomed');
            const overlay = document.createElement('div');
            overlay.id = 'image-overlay';
            overlay.style.position = 'fixed';
            overlay.style.top = '0';
            overlay.style.left = '0';
            overlay.style.width = '100%';
            overlay.style.height = '100%';
            overlay.style.background = 'rgba(0,0,0,0.8)';
            overlay.style.zIndex = '1000';
            overlay.style.cursor = 'zoom-out';
            overlay.onclick = toggleZoom;
            document.body.appendChild(overlay);
            isZoomed = true;
        } else {
            image.style.position = '';
            image.style.top = '';
            image.style.left = '';
            image.style.transform = `scale(${currentZoom}) rotate(${currentRotation}deg)`;
            image.style.zIndex = '';
            image.style.maxWidth = '';
            image.style.maxHeight = '';
            image.style.cursor = 'zoom-in';
            image.classList.remove('zoomed');
            const overlay = document.getElementById('image-overlay');
            if (overlay) {
                overlay.remove();
            }
            isZoomed = false;
        }
    }
}

// PDF functions
let pdfDoc = null;
let pdfScale = 1.0;
let pdfRotation = 0;
let pdfPages = [];
let pdfTotalPages = 0;
let pdfRenderedPages = 0;
let pdfIsRendering = false;
let pdfRenderQueue = [];
let pdfPendingRender = null;

function initPDFViewer() {
    const url = <?php echo json_encode($fileDisplayUrl); ?>;
    const viewer = document.getElementById('pdf-viewer');
    const container = document.getElementById('pdf-viewer-container');
    const loadingDiv = document.getElementById('pdf-loading');
    const progressBar = document.getElementById('pdf-progress-bar');
    const progressText = document.getElementById('pdf-progress-text');
    const progressContainer = document.getElementById('pdf-progress-container');
    
    // Hide controls until loaded
    document.getElementById('pdf-controls').style.opacity = '0.5';
    document.getElementById('pdf-controls').style.pointerEvents = 'none';
    
    const loadingTask = pdfjsLib.getDocument({
        url,
        useSystemFonts: true,
        nativeImageDecoderSupport: 'always',
        cMapUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/cmaps/',
        cMapPacked: true
    });
    
    loadingTask.onProgress = function(progressData) {
        if (progressData.total > 0) {
            const percent = Math.round((progressData.loaded / progressData.total) * 100);
            progressBar.style.width = percent + '%';
            progressText.textContent = percent + '%';
            progressText.style.opacity = '0.8';
        }
    };
    
    loadingTask.promise.then(pdf => {
        pdfDoc = pdf;
        pdfTotalPages = pdf.numPages;
        document.getElementById('pdf-page-count').textContent = pdfTotalPages;
        
        // Hide loading indicator
        loadingDiv.style.display = 'none';
        progressContainer.style.opacity = '0';
        progressText.style.opacity = '0';
        setTimeout(() => {
            progressContainer.style.display = 'none';
            progressText.style.display = 'none';
        }, 300);
        
        // Show controls
        document.getElementById('pdf-controls').style.opacity = '1';
        document.getElementById('pdf-controls').style.pointerEvents = 'auto';
        
        // Auto zoom for mobile
        if (window.innerWidth <= 768) {
            const containerWidth = container.clientWidth - 40;
            pdfScale = containerWidth / 612;
            updatePDFZoomSelect(pdfScale);
        }
        
        // Render all pages
        renderAllPDFPages();
    }).catch(error => {
        console.error('Error loading PDF:', error);
        loadingDiv.innerHTML = '<div class="error-view"><svg class="error-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><?php echo htmlspecialchars($translations['file_not_accessible']); ?></div>';
    });
}

function updatePDFZoomSelect(newScale) {
    const zoomSelect = document.getElementById('pdf-zoom-select');
    const percent = Math.round(newScale * 100);
    let found = false;
    for (let i = 0; i < zoomSelect.options.length; i++) {
        if (Math.abs(parseFloat(zoomSelect.options[i].value) - newScale) < 0.01) {
            zoomSelect.selectedIndex = i;
            found = true;
            break;
        }
    }
    if (!found) {
        const option = document.createElement('option');
        option.value = newScale.toFixed(2);
        option.text = percent + '%';
        option.selected = true;
        zoomSelect.add(option, zoomSelect.options[0]);
    }
}

function renderAllPDFPages() {
    if (pdfIsRendering) {
        pdfRenderQueue.push({type: 'full'});
        return;
    }
    pdfIsRendering = true;
    const viewer = document.getElementById('pdf-viewer');
    viewer.innerHTML = '';
    pdfPages = [];
    pdfRenderedPages = 0;
    for (let i = 1; i <= pdfTotalPages; i++) {
        renderPDFPage(i);
    }
}

function renderPDFPage(num) {
    pdfDoc.getPage(num).then(page => {
        const viewport = page.getViewport({scale: pdfScale, rotation: pdfRotation});
        const wrapper = document.createElement('div');
        wrapper.className = 'page-wrapper';
        wrapper.style.width = viewport.width + 'px';
        wrapper.style.height = viewport.height + 'px';
        
        const canvas = document.createElement('canvas');
        const outputScale = Math.max(window.devicePixelRatio || 1, 2);
        canvas.width = viewport.width * outputScale;
        canvas.height = viewport.height * outputScale;
        canvas.style.width = '100%';
        canvas.style.height = '100%';
        canvas.style.display = 'block';
        
        wrapper.appendChild(canvas);
        document.getElementById('pdf-viewer').appendChild(wrapper);
        
        const ctx = canvas.getContext('2d', {alpha: false, willReadFrequently: false});
        ctx.scale(outputScale, outputScale);
        
        const renderContext = {
            canvasContext: ctx,
            viewport: viewport,
            enableWebGL: true,
            renderInteractiveForms: true
        };
        
        page.render(renderContext).promise.then(() => {
            pdfRenderedPages++;
            pdfPages[num] = wrapper;
            if (pdfRenderedPages === pdfTotalPages) {
                pdfIsRendering = false;
                if (pdfRenderQueue.length > 0) {
                    pdfRenderQueue = [];
                    renderAllPDFPages();
                }
            }
        });
    });
}

function updatePDFScale(newScale, immediate = true) {
    newScale = Math.max(0.25, Math.min(3, newScale));
    if (Math.abs(newScale - pdfScale) < 0.01) return;
    
    const oldScale = pdfScale;
    pdfScale = newScale;
    updatePDFZoomSelect(pdfScale);
    
    const container = document.getElementById('pdf-viewer-container');
    const scrollTop = container.scrollTop;
    const scrollLeft = container.scrollLeft;
    
    if (pdfPendingRender) {
        cancelAnimationFrame(pdfPendingRender);
    }
    
    pdfPendingRender = requestAnimationFrame(() => {
        renderAllPDFPages();
        pdfPendingRender = null;
        requestAnimationFrame(() => {
            container.scrollTop = scrollTop * (pdfScale / oldScale);
            container.scrollLeft = scrollLeft * (pdfScale / oldScale);
        });
    });
}

function pdfPrevPage() {
    const currentPage = parseInt(document.getElementById('pdf-page-input').value);
    if (currentPage > 1 && pdfPages[currentPage - 1]) {
        pdfPages[currentPage - 1].scrollIntoView({behavior: 'smooth', block: 'start'});
    }
}

function pdfNextPage() {
    const currentPage = parseInt(document.getElementById('pdf-page-input').value);
    if (currentPage < pdfTotalPages && pdfPages[currentPage + 1]) {
        pdfPages[currentPage + 1].scrollIntoView({behavior: 'smooth', block: 'start'});
    }
}

function initSidebarResize() {
    const sidebar = document.querySelector('.folder-sidebar');
    const resizer = document.querySelector('.sidebar-resizer');
    if (!sidebar || !resizer) return;
    const storageKey = 'fileViewerSidebarWidth';
    const isMobile = () => window.matchMedia('(max-width: 560px)').matches;
    const clampWidth = value => Math.max(240, Math.min(Math.round(value), Math.min(560, window.innerWidth - 260)));
    const setWidth = value => {
        if (isMobile()) {
            sidebar.style.width = '';
            sidebar.style.flexBasis = '';
            return;
        }
        const width = clampWidth(value);
        sidebar.style.width = width + 'px';
        sidebar.style.flexBasis = width + 'px';
    };
    const savedWidth = parseInt(localStorage.getItem(storageKey), 10);
    if (savedWidth) setWidth(savedWidth);
    let startX = 0;
    let startWidth = 0;
    const onMove = event => setWidth(startWidth + event.clientX - startX);
    const onUp = () => {
        document.removeEventListener('pointermove', onMove);
        document.removeEventListener('pointerup', onUp);
        resizer.classList.remove('dragging');
        document.body.style.userSelect = '';
        localStorage.setItem(storageKey, Math.round(sidebar.getBoundingClientRect().width));
    };
    resizer.addEventListener('pointerdown', event => {
        if (isMobile()) return;
        startX = event.clientX;
        startWidth = sidebar.getBoundingClientRect().width;
        resizer.classList.add('dragging');
        document.body.style.userSelect = 'none';
        document.addEventListener('pointermove', onMove);
        document.addEventListener('pointerup', onUp);
    });
    window.addEventListener('resize', () => {
        const saved = parseInt(localStorage.getItem(storageKey), 10);
        if (saved) setWidth(saved);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initSidebarResize();
    // Modal click outside to close
    document.getElementById('settings-modal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeSettings();
        }
    });
    
    document.getElementById('info-modal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeInfo();
        }
    });
    
    // Image click handlers
    const image = document.getElementById('viewer-image');
    if (image) {
        image.addEventListener('click', function(e) {
            if (!isZoomed) {
                toggleZoom();
                e.stopPropagation();
            }
        });
        document.addEventListener('click', function(e) {
            if (isZoomed && e.target.id === 'image-overlay') {
                toggleZoom();
            }
        });
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'c' && editor) {
            <?php if (($fileData['disable_copy'] ?? 0) === 1): ?>e.preventDefault();showToast('Copy disabled for this shared link',3000);return;<?php endif; ?>
            copyAllContent();
        }
        if (e.key === 'Escape') {
            if (isZoomed) {
                toggleZoom();
            } else {
                closeSettings();
                closeInfo();
            }
        }
    });
    
    // Color buttons styling
    const colorButtons = document.querySelectorAll('.btn:not(.btn-outline)');
    colorButtons.forEach(btn => {
        btn.style.background = `linear-gradient(135deg, #<?php echo $color; ?> 0%, #<?php echo $color; ?> 100%)`;
    });
    
    // Fullscreen controls
    if (<?php echo $isFullscreen ? 'true' : 'false'; ?>) {
        const imageControls = document.querySelector('.image-controls');
        if (imageControls) {
            let controlsTimeout;
            imageControls.style.opacity = '0.7';
            
            function hideControls() {
                imageControls.style.opacity = '0';
                imageControls.style.pointerEvents = 'none';
            }
            
            function showControls() {
                imageControls.style.opacity = '0.7';
                imageControls.style.pointerEvents = 'auto';
                clearTimeout(controlsTimeout);
                controlsTimeout = setTimeout(hideControls, 3000);
            }
            
            controlsTimeout = setTimeout(hideControls, 3000);
            document.addEventListener('mousemove', function() {
                showControls();
            });
            document.addEventListener('click', function() {
                showControls();
            });
        }
    }
    
        // Initialize PDF if needed
    <?php if ($fileType === 'pdf'): ?>
    initPDFViewer();
    <?php endif; ?>
});

// ONLYOFFICE message handler
<?php if(isset($docConfig)): ?>
window.addEventListener('message', function(event) {
    if (event.data.type === 'closeOnlyOfficeEditor' || event.data.type === 'closeOnlyOffice') {
        if (document.referrer) {
            window.location.href = document.referrer;
        } else {
            window.history.back();
        }
    }
});
<?php endif; ?>

if (typeof require !== 'undefined' && require.config) {
    require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.34.1/min/vs' } });
}

// Markdown view switching - declare early for onclick handlers
var markdownViewMode = 'preview';

function switchToPreview() {
    markdownViewMode = 'preview';
    document.getElementById('markdown-preview').style.display = 'block';
    document.getElementById('monaco-editor').style.display = 'none';
    document.getElementById('preview-mode-btn').classList.add('active');
    document.getElementById('editor-mode-btn').classList.remove('active');
}

function switchToEditor() {
    markdownViewMode = 'editor';
    document.getElementById('markdown-preview').style.display = 'none';
    document.getElementById('monaco-editor').style.display = 'block';
    document.getElementById('preview-mode-btn').classList.remove('active');
    document.getElementById('editor-mode-btn').classList.add('active');

    // Ensure Monaco editor is initialized
    if (!window.editor && window.initMonacoForMarkdown) {
        window.initMonacoForMarkdown();
    }
}

// Markdown processing functions
function processLatex(text) {
    const blocks = [];
    let processed = text;

    // Process display math blocks ($$...$$)
    processed = processed.replace(/\$\$([\s\S]*?)\$\$/g, function(match) {
        blocks.push(match);
        return `%%LATEX_BLOCK_${blocks.length-1}%%`;
    });

    // Process display math blocks (\[...\])
    processed = processed.replace(/\\\[([\s\S]*?)\\\]/g, function(match) {
        blocks.push(match);
        return `%%LATEX_BLOCK_${blocks.length-1}%%`;
    });

    // Process inline math ($...$)
    processed = processed.replace(/\$([^\$\n]+?)\$/g, function(match) {
        blocks.push(match);
        return `%%LATEX_BLOCK_${blocks.length-1}%%`;
    });

    // Process inline math (\(...\))
    processed = processed.replace(/\\\((.*?)\\\)/g, function(match) {
        blocks.push(match);
        return `%%LATEX_BLOCK_${blocks.length-1}%%`;
    });

    return { processed, blocks };
}

function restoreLatex(html, blocks) {
    let restored = html;
    blocks.forEach((block, index) => {
        restored = restored.replace(`%%LATEX_BLOCK_${index}%%`, block);
    });
    return restored;
}

function renderMarkdown(markdownText) {
    // Use markedLib (protected from AMD) or fallback to marked
    const markedParser = window.markedLib || window.marked;

    // Configure marked
    if (markedParser) {
        markedParser.setOptions({
            breaks: true,
            gfm: true,
            headerIds: false,
            mangle: false
        });
    } else {
        console.error('marked.js not loaded');
        return;
    }

    // Process LaTeX before Markdown
    const { processed, blocks } = processLatex(markdownText);

    // Convert Markdown to HTML
    let html = markedParser.parse(processed);

    // Restore LaTeX
    html = restoreLatex(html, blocks);

    // Insert into preview
    const previewContent = document.getElementById('markdown-content');
    if (previewContent) {
        previewContent.innerHTML = html;

        // Render math with MathJax
        if (window.MathJax && window.MathJax.typesetPromise) {
            window.MathJax.typesetPromise([previewContent]).catch((err) => {
                console.error('MathJax rendering error:', err);
            });
        } else if (window.MathJax) {
            // MathJax 2 fallback
            window.MathJax.Hub.Queue(['Typeset', window.MathJax.Hub, previewContent]);
        }
    } else {
        console.error('markdown-content element not found');
    }
}


</script>
