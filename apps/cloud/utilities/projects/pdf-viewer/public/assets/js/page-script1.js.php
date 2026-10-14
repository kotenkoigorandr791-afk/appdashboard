// Translations for JavaScript
const jsTranslations = {
    password_error: '<?php echo $t['password_error']; ?>',
    password_attempts: '<?php echo $t['password_attempts']; ?>',
    password_max_attempts: '<?php echo $t['password_max_attempts']; ?>',
    error: '<?php echo $t['error']; ?>',
    cancelled: '<?php echo $t['cancelled']; ?>',
    page: '<?php echo $t['page']; ?>',
    of: '<?php echo $t['of']; ?>',
    draw: '<?php echo $t['draw']; ?>',
    draw_color: '<?php echo $t['draw_color']; ?>',
    draw_size: '<?php echo $t['draw_size']; ?>',
    clear_drawings: '<?php echo $t['clear_drawings']; ?>',
    undo: '<?php echo $t['undo']; ?>',
    eraser: '<?php echo $t['eraser']; ?>'
};

pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
if (pdfjsLib.VerbosityLevel) pdfjsLib.verbosity = pdfjsLib.VerbosityLevel.ERRORS;
const url = '?action=pdf_stream&token=<?php echo $token; ?>';
let pdfDoc = null, scale = 1.0, rotation = 0;
let pages = [];
let totalPages = 0;
let renderedPages = 0;
let isRendering = false;
let renderQueue = [];
let pendingRender = null;
let renderGeneration = 0;
let renderedScale = 1.0;
let passwordAttempts = 0;
const MAX_PASSWORD_ATTEMPTS = 3;

// Drawing variables
let isDrawingMode = false;
let isDrawing = false;
let currentColor = '#ff0000';
let currentSize = 3;
let currentPage = 1;
let drawingStates = new Map(); // Store drawing history per page

// Rendering state
let renderAborted = false;
let currentRenderId = 0;

const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
const viewer = document.getElementById('viewer');
const viewerContainer = document.getElementById('viewer-container');
const pageInput = document.getElementById('page-input');
const pageCount = document.getElementById('page-count');
const zoomSelect = document.getElementById('zoom-select');
const progressBar = document.getElementById('progress-bar');
const progressText = document.getElementById('progress-text');
const progressContainer = document.getElementById('progress-container');
const passwordDialog = document.getElementById('password-dialog');
const passwordInput = document.getElementById('password-input');
const passwordError = document.getElementById('password-error');
const passwordSubmit = document.getElementById('password-submit');
const passwordCancel = document.getElementById('password-cancel');

// Drawing elements
const drawToggle = document.getElementById('draw-toggle');
const drawControls = document.getElementById('draw-controls');
const drawColor = document.getElementById('draw-color');
const drawSize = document.getElementById('draw-size');
const drawUndo = document.getElementById('draw-undo');
const drawClear = document.getElementById('draw-clear');

let devicePixelRatio = window.devicePixelRatio || 1;
const PDF_DOWNLOAD_WEIGHT = 38;
const fileProgress = new FileLoadProgress({
    theme: <?php echo json_encode($theme); ?>,
    color: <?php echo json_encode('#' . $color); ?>,
    barEl: progressBar,
    textEl: progressText,
    trackEl: progressContainer
});
progressContainer.style.opacity = '1';
progressText.style.opacity = '0.8';

// Drawing functions
function createDrawCanvas(wrapper, width, height, pageNum) {
    const canvasContainer = wrapper.querySelector('.canvas-container');
    if (!canvasContainer) return null;
    
    // Remove existing draw canvas if any
    const oldCanvas = wrapper.querySelector('.draw-canvas');
    if (oldCanvas) oldCanvas.remove();
    
    const drawCanvas = document.createElement('canvas');
    drawCanvas.className = 'draw-canvas';
    drawCanvas.width = width * devicePixelRatio;
    drawCanvas.height = height * devicePixelRatio;
    drawCanvas.style.width = width + 'px';
    drawCanvas.style.height = height + 'px';
    
    canvasContainer.appendChild(drawCanvas);
    
    const ctx = drawCanvas.getContext('2d');
    ctx.scale(devicePixelRatio, devicePixelRatio);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    
    // Initialize drawing state for this page
    if (!drawingStates.has(pageNum)) {
        drawingStates.set(pageNum, []);
    }
    
    // Restore previous drawings
    restoreDrawings(pageNum, ctx);
    
    if (isDrawingMode) {
        drawCanvas.classList.add('active');
    }
    
    return {canvas: drawCanvas, ctx: ctx};
}

function restoreDrawings(pageNum, ctx) {
    const paths = drawingStates.get(pageNum) || [];
    if (paths.length === 0) return;
    
    // canvas logical size = canvas.width / devicePixelRatio
    const canvas = ctx.canvas;
    const logicalW = canvas.width / devicePixelRatio;
    const logicalH = canvas.height / devicePixelRatio;
    
    paths.forEach(path => {
        if (path.points.length < 2) return;
        
        ctx.beginPath();
        ctx.strokeStyle = path.color;
        // scale lineWidth proportionally: stored relative to baseScale
        const scaleFactor = logicalW / (path.baseW || logicalW);
        ctx.lineWidth = path.size * scaleFactor;
        
        // points stored as normalized (0-1)
        ctx.moveTo(path.points[0].x * logicalW, path.points[0].y * logicalH);
        for (let i = 1; i < path.points.length; i++) {
            ctx.lineTo(path.points[i].x * logicalW, path.points[i].y * logicalH);
        }
        ctx.stroke();
    });
}

function saveDrawingPath(pageNum, points, color, size, logicalW, logicalH) {
    if (!drawingStates.has(pageNum)) {
        drawingStates.set(pageNum, []);
    }
    
    // Normalize points to 0-1 range so they scale correctly on re-render
    const normalizedPoints = points.map(p => ({
        x: p.x / logicalW,
        y: p.y / logicalH
    }));
    
    drawingStates.get(pageNum).push({
        points: normalizedPoints,
        color: color,
        size: size,
        baseW: logicalW  // store the logical width at draw time for lineWidth scaling
    });
}

function startDrawing(e, canvas, ctx, pageNum) {
    if (!isDrawingMode || !canvas || !ctx) return false;
    
    e.preventDefault();
    e.stopPropagation();
    
    isDrawing = true;
    
    const rect = canvas.getBoundingClientRect();
    const logicalW = canvas.width / devicePixelRatio;
    const logicalH = canvas.height / devicePixelRatio;
    const scaleX = logicalW / rect.width;
    const scaleY = logicalH / rect.height;
    
    let clientX, clientY;
    if (e.touches) {
        clientX = e.touches[0].clientX;
        clientY = e.touches[0].clientY;
    } else {
        clientX = e.clientX;
        clientY = e.clientY;
    }
    
    const x = (clientX - rect.left) * scaleX;
    const y = (clientY - rect.top) * scaleY;
    
    ctx.beginPath();
    ctx.moveTo(x, y);
    ctx.strokeStyle = currentColor;
    ctx.lineWidth = currentSize;
    
    // Start new path
    canvas.currentPath = {
        points: [{x, y}],
        color: currentColor,
        size: currentSize,
        logicalW: logicalW,
        logicalH: logicalH
    };
    
    return true;
}

function draw(e, canvas, ctx, pageNum) {
    if (!isDrawing || !isDrawingMode || !canvas || !ctx) return;
    
    e.preventDefault();
    e.stopPropagation();
    
    const rect = canvas.getBoundingClientRect();
    const logicalW = canvas.width / devicePixelRatio;
    const logicalH = canvas.height / devicePixelRatio;
    const scaleX = logicalW / rect.width;
    const scaleY = logicalH / rect.height;
    
    let clientX, clientY;
    if (e.touches) {
        clientX = e.touches[0].clientX;
        clientY = e.touches[0].clientY;
    } else {
        clientX = e.clientX;
        clientY = e.clientY;
    }
    
    const x = (clientX - rect.left) * scaleX;
    const y = (clientY - rect.top) * scaleY;
    
    ctx.lineTo(x, y);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(x, y);
    
    // Add point to current path
    if (canvas.currentPath) {
        canvas.currentPath.points.push({x, y});
    }
}

function stopDrawing(e, canvas, pageNum) {
    if (isDrawing && canvas && canvas.currentPath) {
        // Save the complete path with logical dimensions for normalization
        saveDrawingPath(pageNum, canvas.currentPath.points, canvas.currentPath.color, canvas.currentPath.size,
            canvas.currentPath.logicalW, canvas.currentPath.logicalH);
        canvas.currentPath = null;
    }
    isDrawing = false;
}

function undoLastDrawing() {
    if (!currentPage) return;
    
    const paths = drawingStates.get(currentPage) || [];
    if (paths.length === 0) return;
    
    // Remove last path
    paths.pop();
    
    // Redraw all remaining paths
    const wrapper = pages[currentPage];
    if (wrapper) {
        const drawCanvas = wrapper.querySelector('.draw-canvas');
        if (drawCanvas) {
            const ctx = drawCanvas.getContext('2d');
            ctx.clearRect(0, 0, drawCanvas.width, drawCanvas.height);
            restoreDrawings(currentPage, ctx);
        }
    }
}

function clearDrawings() {
    drawingStates.forEach((paths, pageNum) => {
        drawingStates.set(pageNum, []);
        const wrapper = pages[pageNum];
        if (wrapper) {
            const drawCanvas = wrapper.querySelector('.draw-canvas');
            if (drawCanvas) {
                const ctx = drawCanvas.getContext('2d');
                ctx.clearRect(0, 0, drawCanvas.width, drawCanvas.height);
            }
        }
    });
}

// Drawing event listeners
drawToggle.addEventListener('click', () => {
    isDrawingMode = !isDrawingMode;
    drawToggle.classList.toggle('active', isDrawingMode);
    drawControls.style.display = isDrawingMode ? 'inline-flex' : 'none';
    
    // Update all drawing canvases
    document.querySelectorAll('.draw-canvas').forEach(canvas => {
        canvas.classList.toggle('active', isDrawingMode);
    });
    
    // While drawing, let strokes through instead of selecting the text layer
    document.querySelectorAll('.text-layer').forEach(layer => {
        layer.classList.toggle('no-select', isDrawingMode);
    });
});

drawColor.addEventListener('input', (e) => {
    currentColor = e.target.value;
});
// Fix tooltips positioning
document.querySelectorAll('[data-tooltip]').forEach(btn => {
    btn.addEventListener('mouseenter', function(e) {
        const tooltip = this.getAttribute('data-tooltip');
        if (!tooltip) return;
        
        // Remove existing tooltips
        document.querySelectorAll('.dynamic-tooltip').forEach(el => el.remove());
        
        // Create tooltip
        const tip = document.createElement('div');
        tip.className = 'dynamic-tooltip';
        tip.textContent = tooltip;
        tip.style.cssText = `
            position: fixed;
            background: <?php echo $theme === 'light' ? '#333' : '#fff'; ?>;
            color: <?php echo $theme === 'light' ? '#fff' : '#333'; ?>;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 2147483647;
            pointer-events: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        `;
        
        const rect = this.getBoundingClientRect();
        tip.style.top = rect.bottom + 8 + 'px';
        
        // Add small arrow
        const arrow = document.createElement('div');
        arrow.style.cssText = `
            position: fixed;
            top: ${rect.bottom + 2}px;
            width: 0;
            height: 0;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            border-bottom: 4px solid <?php echo $theme === 'light' ? '#333' : '#fff'; ?>;
            z-index: 2147483647;
            pointer-events: none;
        `;
        arrow.className = 'dynamic-tooltip-arrow';
        
        document.body.appendChild(tip);
        document.body.appendChild(arrow);

        const margin = 8;
        const tipRect = tip.getBoundingClientRect();
        const centerX = rect.left + (rect.width / 2);
        const desiredTop = rect.bottom + 8;
        const placeAbove = desiredTop + tipRect.height + margin > window.innerHeight;
        const minLeft = margin;
        const maxLeft = Math.max(margin, window.innerWidth - tipRect.width - margin);
        const desiredLeft = centerX - (tipRect.width / 2);
        const clampedLeft = Math.min(maxLeft, Math.max(minLeft, desiredLeft));
        const arrowLeft = Math.min(
            window.innerWidth - margin - 4,
            Math.max(margin + 4, centerX)
        );

        tip.style.left = clampedLeft + 'px';
        tip.style.top = (placeAbove ? rect.top - tipRect.height - 8 : desiredTop) + 'px';
        tip.style.transform = 'none';
        arrow.style.left = arrowLeft + 'px';
        arrow.style.top = (placeAbove ? rect.top - 6 : rect.bottom + 2) + 'px';
        arrow.style.transform = 'translateX(-50%)';
        if (placeAbove) {
            arrow.style.borderLeft = '4px solid transparent';
            arrow.style.borderRight = '4px solid transparent';
            arrow.style.borderTop = '4px solid <?php echo $theme === 'light' ? '#333' : '#fff'; ?>';
            arrow.style.borderBottom = '0';
        }
    });
    
    btn.addEventListener('mouseleave', function() {
        document.querySelectorAll('.dynamic-tooltip, .dynamic-tooltip-arrow').forEach(el => el.remove());
    });
});
drawSize.addEventListener('input', (e) => {
    currentSize = parseInt(e.target.value);
});

drawUndo.addEventListener('click', () => {
    undoLastDrawing();
});

drawClear.addEventListener('click', () => {
    clearDrawings();
});

function loadPDF(password) {
    fileProgress.show();
    fileProgress.reset();
    fileProgress.startCreep(PDF_DOWNLOAD_WEIGHT - 4);
    progressContainer.style.display = '';
    progressText.style.display = '';
    progressContainer.style.opacity = '1';
    progressText.style.opacity = '0.8';
    const loadingTask = pdfjsLib.getDocument({
        url,
        password: password,
        useSystemFonts: false,
        standardFontDataUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/standard_fonts/',
        nativeImageDecoderSupport: 'always',
        cMapUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/cmaps/',
        cMapPacked: true,
        stopAtErrors: false
    });

    loadingTask.onProgress = function(progressData) {
        if (progressData.total > 0) {
            fileProgress.stopCreep();
            const percent = (progressData.loaded / progressData.total) * PDF_DOWNLOAD_WEIGHT;
            fileProgress.set(percent);
        }
    };

    loadingTask.promise.then(pdf => {
        pdfDoc = pdf;
        totalPages = pdf.numPages;
        pageCount.textContent = totalPages;
        passwordDialog.style.display = 'none';
        passwordInput.value = '';
        passwordError.style.display = 'none';
        passwordAttempts = 0;
        
        if (isMobile) {
            const containerWidth = viewerContainer.clientWidth - 20;
            scale = containerWidth / 612;
            updateZoomSelect(scale);
        }
        // Small delay before starting render to let UI settle
        requestAnimationFrame(() => {
            renderAllPages();
        });
    }).catch(error => {
        console.error('Error loading PDF:', error);
        
        if (error.name === 'PasswordException') {
            if (error.code === 1) { // Needs password
                fileProgress.stopCreep();
                showPasswordDialog();
            } else if (error.code === 2) { // Incorrect password
                passwordAttempts++;
                if (passwordAttempts >= MAX_PASSWORD_ATTEMPTS) {
                    passwordError.textContent = jsTranslations.password_max_attempts;
                    passwordError.style.display = 'block';
                    setTimeout(() => {
                        passwordDialog.style.display = 'none';
                        fileProgress.fail(jsTranslations.error);
                    }, 2000);
                } else {
                    passwordError.textContent = jsTranslations.password_error + '. ' + 
                        jsTranslations.password_attempts + ' ' + (MAX_PASSWORD_ATTEMPTS - passwordAttempts);
                    passwordError.style.display = 'block';
                    passwordInput.value = '';
                    passwordInput.focus();
                }
            }
        } else {
            fileProgress.fail(jsTranslations.error);
            passwordDialog.style.display = 'none';
        }
    });
}

function showPasswordDialog() {
    passwordDialog.style.display = 'flex';
    passwordInput.focus();
    passwordError.style.display = 'none';
}

passwordSubmit.addEventListener('click', () => {
    const password = passwordInput.value;
    if (password) {
        loadPDF(password);
    }
});

passwordCancel.addEventListener('click', () => {
    passwordDialog.style.display = 'none';
    passwordInput.value = '';
    passwordError.style.display = 'none';
    fileProgress.fail(jsTranslations.cancelled);
});

passwordInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        passwordSubmit.click();
    }
});

// Start loading PDF
loadPDF();

function updateZoomSelect(newScale) {
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

function setViewerPreviewScale(nextScale, immediate = false) {
    if (!viewer || !Number.isFinite(nextScale) || !Number.isFinite(renderedScale) || renderedScale <= 0) return;
    const ratio = nextScale / renderedScale;
    viewer.classList.add('is-zooming');
    viewer.style.transition = immediate ? 'none' : 'transform 180ms ease';
    viewer.style.transformOrigin = 'top center';
    viewer.style.transform = `scale(${ratio})`;
}

function clearViewerPreviewScale() {
    if (!viewer) return;
    viewer.classList.remove('is-zooming');
    viewer.style.transition = 'transform 140ms ease, opacity 160ms ease';
    viewer.style.transform = 'scale(1)';
}

// Build one selectable span per visual line instead of one span per PDF text
// item. pdf.js's per-item text layer leaves the gaps between words unhighlighted,
// so a drag-select looks like a grid of separate boxes. Grouping text items into
// lines makes the selection highlight continuous, line by line, and still copies
// the page text in the right order.
function getTextAscent(ctx, fontSize) {
    ctx.font = `${fontSize}px sans-serif`;
    const metrics = ctx.measureText('g');
    const ascent = metrics.fontBoundingBoxAscent;
    const descent = metrics.fontBoundingBoxDescent;
    if (ascent && ascent + descent > 0) {
        return ascent / (ascent + descent);
    }
    return 0.8;
}

async function renderLineTextLayer(page, viewport, container) {
    const textContent = await page.getTextContent();
    const items = [];

    for (const item of textContent.items) {
        if (!item || typeof item.str !== 'string' || item.str.length === 0) continue;
        const tx = pdfjsLib.Util.transform(viewport.transform, item.transform);
        const fontHeight = Math.hypot(tx[2], tx[3]) || item.height * viewport.scale;
        if (!fontHeight) continue;
        items.push({
            str: item.str,
            x: tx[4],
            baseline: tx[5],
            width: item.width * viewport.scale,
            fontHeight: fontHeight
        });
    }

    if (items.length === 0) return;

    items.sort((a, b) => (a.baseline - b.baseline) || (a.x - b.x));

    // Group items that share a baseline (and are close horizontally, so that
    // separate columns are not merged into one line).
    const lines = [];
    const baselineTolerance = 1.5;
    for (const item of items) {
        const line = lines[lines.length - 1];
        const maxGap = item.fontHeight * 1.5;
        if (!line || Math.abs(item.baseline - line.baseline) > baselineTolerance || item.x - line.right > maxGap) {
            lines.push({
                baseline: item.baseline,
                fontHeight: item.fontHeight,
                left: item.x,
                right: item.x + item.width,
                items: [item]
            });
        } else {
            line.items.push(item);
            line.left = Math.min(line.left, item.x);
            line.right = Math.max(line.right, item.x + item.width);
            line.fontHeight = Math.max(line.fontHeight, item.fontHeight);
        }
    }

    const measureCtx = document.createElement('canvas').getContext('2d');

    for (const line of lines) {
        let text = '';
        let previousEnd = null;
        for (const item of line.items) {
            if (previousEnd !== null) {
                const gap = item.x - previousEnd;
                if (gap > item.fontHeight * 0.15 && !text.endsWith(' ') && !item.str.startsWith(' ')) {
                    text += ' ';
                }
            }
            text += item.str;
            previousEnd = item.x + item.width;
        }
        text = text.replace(/\s+$/, '');
        if (!text.trim()) continue;

        const fontSize = line.fontHeight;
        const span = document.createElement('span');
        span.textContent = text;
        span.style.left = line.left.toFixed(2) + 'px';
        span.style.top = (line.baseline - getTextAscent(measureCtx, fontSize) * fontSize).toFixed(2) + 'px';
        span.style.fontSize = fontSize.toFixed(2) + 'px';
        span.style.fontFamily = 'sans-serif';
        span.style.lineHeight = '1';

        measureCtx.font = `${fontSize}px sans-serif`;
        const naturalWidth = measureCtx.measureText(text).width;
        const lineWidth = Math.max(1, line.right - line.left);
        if (naturalWidth > 0) {
            span.style.transform = `scaleX(${lineWidth / naturalWidth})`;
        }
        container.appendChild(span);
    }
}

async function buildRenderedPage(num, renderId) {
    // Check if this render is still current
    if (renderId !== currentRenderId) return null;
    
    const page = await pdfDoc.getPage(num);
    
    // Yield to event loop after getting page
    await new Promise(r => setTimeout(r, 0));
    if (renderId !== currentRenderId) return null;
    
    const viewport = page.getViewport({scale, rotation});
    const wrapper = document.createElement('div');
    wrapper.className = 'page-wrapper';
    wrapper.style.width = viewport.width + 'px';
    wrapper.style.height = viewport.height + 'px';
    wrapper.style.margin = '0 auto 10px auto';
    
    const canvasContainer = document.createElement('div');
    canvasContainer.className = 'canvas-container';
    canvasContainer.style.width = '100%';
    canvasContainer.style.height = '100%';
    canvasContainer.style.position = 'relative';
    
    const pdfCanvas = document.createElement('canvas');
    pdfCanvas.className = 'pdf-canvas';
    const outputScale = Math.max(devicePixelRatio, 2);
    pdfCanvas.width = viewport.width * outputScale;
    pdfCanvas.height = viewport.height * outputScale;
    pdfCanvas.style.width = viewport.width + 'px';
    pdfCanvas.style.height = viewport.height + 'px';
    
    canvasContainer.appendChild(pdfCanvas);
    wrapper.appendChild(canvasContainer);
    
    const ctx = pdfCanvas.getContext('2d', {alpha: false});
    ctx.scale(outputScale, outputScale);
    
    const renderContext = {
        canvasContext: ctx,
        viewport: viewport,
        enableWebGL: true,
        renderInteractiveForms: true
    };
    
    // Render PDF page with abort check
    await page.render(renderContext).promise;
    
    // Check if render was aborted
    if (renderId !== currentRenderId) return null;
    
    // Yield before text layer
    await new Promise(r => setTimeout(r, 0));
    if (renderId !== currentRenderId) return null;

    // Create an invisible, selectable text layer on top of the canvas.
    const textLayerDiv = document.createElement('div');
    textLayerDiv.className = 'text-layer';
    if (isDrawingMode) textLayerDiv.classList.add('no-select');
    canvasContainer.appendChild(textLayerDiv);
    
    // Yield before text content
    await new Promise(r => setTimeout(r, 0));
    if (renderId !== currentRenderId) return null;
    
    // One span per visual line, so selection is continuous line-by-line.
    await renderLineTextLayer(page, viewport, textLayerDiv);
    
    // Yield after text layer render
    await new Promise(r => setTimeout(r, 0));
    if (renderId !== currentRenderId) return null;

    // Create drawing canvas with saved state
    const drawData = createDrawCanvas(wrapper, viewport.width, viewport.height, num);
    if (drawData) {
        const drawCanvas = drawData.canvas;
        const drawCtx = drawData.ctx;
        
        // Add drawing event listeners
        const startHandler = (e) => {
            startDrawing(e, drawCanvas, drawCtx, num);
        };
        
        const moveHandler = (e) => {
            draw(e, drawCanvas, drawCtx, num);
        };
        
        const stopHandler = (e) => {
            stopDrawing(e, drawCanvas, num);
        };
        
        drawCanvas.addEventListener('mousedown', startHandler);
        drawCanvas.addEventListener('mousemove', moveHandler);
        drawCanvas.addEventListener('mouseup', stopHandler);
        drawCanvas.addEventListener('mouseleave', stopHandler);
        
        drawCanvas.addEventListener('touchstart', startHandler, {passive: false});
        drawCanvas.addEventListener('touchmove', moveHandler, {passive: false});
        drawCanvas.addEventListener('touchend', stopHandler);
        drawCanvas.addEventListener('touchcancel', stopHandler);
    }

    return { num, wrapper };
}

async function renderAllPages() {
    if (!pdfDoc) return;
    
    if (isRendering) {
        renderQueue = [{type: 'full'}];
        return;
    }
    
    isRendering = true;
    const renderId = ++renderGeneration;
    currentRenderId = renderId;
    renderAborted = false;
    renderedPages = 0;

    try {
        fileProgress.stopCreep();
        fileProgress.set(PDF_DOWNLOAD_WEIGHT);
        const renderSpan = 100 - PDF_DOWNLOAD_WEIGHT;
        
        const nextPages = [];
        
        // Render pages SEQUENTIALLY with yields to prevent freezing
        for (let i = 1; i <= totalPages; i++) {
            if (renderId !== currentRenderId || renderAborted) return;
            
            const result = await buildRenderedPage(i, renderId);
            
            if (renderId !== currentRenderId || renderAborted || !result) return;
            
            renderedPages++;
            nextPages[result.num] = result.wrapper;
            viewer.appendChild(result.wrapper);
            fileProgress.set(PDF_DOWNLOAD_WEIGHT + (renderedPages / totalPages) * renderSpan);
            
            // Small yield every page to keep UI responsive
            await new Promise(r => setTimeout(r, 0));
        }
        
        if (renderId !== currentRenderId || renderAborted) return;
        
        pages = nextPages;
        renderedScale = scale;
        clearViewerPreviewScale();
        fileProgress.complete();
        setTimeout(() => {
            progressContainer.style.display = 'none';
            progressText.style.display = 'none';
        }, 600);
    } finally {
        isRendering = false;
        if (renderQueue.length > 0) {
            renderQueue = [];
            renderAllPages();
        }
    }
}

function updateScale(newScale, immediate = true) {
    newScale = Math.max(0.25, Math.min(2.5, newScale));
    if (Math.abs(newScale - scale) < 0.01 || !pdfDoc) return;
    
    const oldScale = scale;
    scale = newScale;
    updateZoomSelect(scale);
    
    const scrollTop = viewerContainer.scrollTop;
    const scrollLeft = viewerContainer.scrollLeft;
    
    if (pendingRender) {
        cancelAnimationFrame(pendingRender);
    }
    
    // Mark current render as aborted
    renderAborted = true;
    currentRenderId++;
    
    setViewerPreviewScale(newScale, !immediate);
    pendingRender = requestAnimationFrame(async () => {
        await renderAllPages();
        pendingRender = null;
        requestAnimationFrame(() => {
            viewerContainer.scrollTop = scrollTop * (scale / oldScale);
            viewerContainer.scrollLeft = scrollLeft * (scale / oldScale);
        });
    });
}

document.getElementById('zoom-in').addEventListener('click', () => updateScale(scale + 0.25));
document.getElementById('zoom-out').addEventListener('click', () => updateScale(scale - 0.25));
zoomSelect.addEventListener('change', (e) => updateScale(parseFloat(e.target.value)));

document.getElementById('fit').addEventListener('click', async () => {
    if (!pdfDoc) {
        const containerWidth = viewerContainer.clientWidth - 40;
        updateScale(containerWidth / 612);
        return;
    }
    // Get first page dimensions to determine orientation
    const page = await pdfDoc.getPage(currentPage || 1);
    const baseViewport = page.getViewport({scale: 1, rotation});
    const pageW = baseViewport.width;
    const pageH = baseViewport.height;
    const containerWidth = viewerContainer.clientWidth - 40;
    const containerHeight = viewerContainer.clientHeight;
    
    let newScale;
    if (pageW > pageH) {
        // Landscape: fit by height so top/bottom edges touch container
        const scaleByHeight = containerHeight / pageH;
        const scaleByWidth = containerWidth / pageW;
        newScale = Math.max(scaleByHeight, scaleByWidth);
    } else {
        // Portrait: fit by width as before
        newScale = containerWidth / pageW;
    }
    updateScale(newScale);
});

let wheelTimeout;
viewerContainer.addEventListener('wheel', (e) => {
    if (e.ctrlKey) {
        e.preventDefault();
        const delta = e.deltaY > 0 ? -0.1 : 0.1;
        const newScale = Math.max(0.25, Math.min(2.5, scale + delta));
        if (Math.abs(newScale - scale) > 0.01) {
            if (wheelTimeout) clearTimeout(wheelTimeout);
            wheelTimeout = setTimeout(() => {
                updateScale(newScale);
                wheelTimeout = null;
            }, 10);
        }
    }
}, {passive: false});

document.getElementById('rotate-left').addEventListener('click', () => {
    if (pdfDoc) {
        rotation = (rotation - 90) % 360;
        renderAborted = true;
        currentRenderId++;
        renderAllPages();
    }
});

document.getElementById('rotate-right').addEventListener('click', () => {
    if (pdfDoc) {
        rotation = (rotation + 90) % 360;
        renderAborted = true;
        currentRenderId++;
        renderAllPages();
    }
});

// Page navigation
document.getElementById('prev').addEventListener('click', () => {
    if (!pdfDoc) return;
    const currentPage = parseInt(pageInput.value);
    if (currentPage > 1) {
        goToPage(currentPage - 1);
    }
});

document.getElementById('next').addEventListener('click', () => {
    if (!pdfDoc) return;
    const currentPage = parseInt(pageInput.value);
    if (currentPage < totalPages) {
        goToPage(currentPage + 1);
    }
});

function goToPage(pageNum) {
    if (!pages[pageNum]) return;
    
    const pageElement = pages[pageNum];
    const pageTop = pageElement.offsetTop;
    
    viewerContainer.scrollTo({
        top: pageTop - 10,
        behavior: 'smooth'
    });
    
    pageInput.value = pageNum;
    currentPage = pageNum;
}

pageInput.addEventListener('change', function() {
    if (!pdfDoc) return;
    const num = parseInt(this.value);
    if (num >= 1 && num <= totalPages && pages[num]) {
        goToPage(num);
    } else {
        this.value = currentPage;
    }
});

// Scroll detection
let scrollTimeout;
viewerContainer.addEventListener('scroll', () => {
    if (!pdfDoc) return;
    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(() => {
        if (pages.length) {
            const containerTop = viewerContainer.scrollTop;
            const containerHeight = viewerContainer.clientHeight;
            
            let bestPage = 1;
            let maxVisibleArea = 0;
            
            for (let i = 1; i <= totalPages; i++) {
                if (pages[i]) {
                    const pageTop = pages[i].offsetTop;
                    const pageBottom = pageTop + pages[i].offsetHeight;
                    
                    const visibleTop = Math.max(containerTop, pageTop);
                    const visibleBottom = Math.min(containerTop + containerHeight, pageBottom);
                    const visibleArea = Math.max(0, visibleBottom - visibleTop);
                    
                    if (visibleArea > maxVisibleArea) {
                        maxVisibleArea = visibleArea;
                        bestPage = i;
                    }
                }
            }
            
            if (bestPage !== currentPage) {
                pageInput.value = bestPage;
                currentPage = bestPage;
            }
        }
    }, 50);
});

// Keyboard navigation
document.addEventListener('keydown', e => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    if (!pdfDoc) return;
    
    if (e.key === 'ArrowLeft') {
        e.preventDefault();
        document.getElementById('prev').click();
    }
    if (e.key === 'ArrowRight') {
        e.preventDefault();
        document.getElementById('next').click();
    }
    if (e.key === '+' || e.key === '=') {
        e.preventDefault();
        updateScale(scale + 0.25);
    }
    if (e.key === '-') {
        e.preventDefault();
        updateScale(scale - 0.25);
    }
    if (e.key === '0') {
        e.preventDefault();
        document.getElementById('fit').click();
    }
    if (e.key === 'd' && !e.ctrlKey && !e.metaKey) {
        e.preventDefault();
        drawToggle.click();
    }
    if (e.key === 'z' && (e.ctrlKey || e.metaKey) && isDrawingMode) {
        e.preventDefault();
        undoLastDrawing();
    }
});

// Window resize
window.addEventListener('resize', () => {
    devicePixelRatio = window.devicePixelRatio || 1;
    if (isMobile && pdfDoc) {
        const containerWidth = viewerContainer.clientWidth - 20;
        updateScale(containerWidth / 612);
    }
});

// Prevent pinch-zoom on touch devices (allow single-finger scroll)
document.addEventListener('touchstart', (e) => {
    if (e.touches.length > 1) {
        e.preventDefault();
    }
}, {passive: false});

document.addEventListener('touchmove', (e) => {
    if (e.touches.length > 1) {
        e.preventDefault();
    }
}, {passive: false});

// Also block gesture events (Safari)
document.addEventListener('gesturestart', (e) => e.preventDefault(), {passive: false});
document.addEventListener('gesturechange', (e) => e.preventDefault(), {passive: false});
document.addEventListener('gestureend', (e) => e.preventDefault(), {passive: false});

// Prevent zoom on double-tap for mobile, but keep the native double-tap-to-
// select-word behaviour on the selectable text layer.
let lastTouchEnd = 0;
document.addEventListener('touchend', (e) => {
    const now = Date.now();
    if (now - lastTouchEnd <= 300 && !(e.target instanceof Element && e.target.closest('.text-layer'))) {
        e.preventDefault();
    }
    lastTouchEnd = now;
}, {passive: false});
