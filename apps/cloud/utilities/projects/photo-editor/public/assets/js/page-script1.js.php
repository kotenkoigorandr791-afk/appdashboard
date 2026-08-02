        const canvas = new fabric.Canvas('canvas', { 
            selection: true,
            selectionColor: 'rgba(100, 100, 255, 0.3)',
            selectionBorderColor: '#007bff',
            selectionLineWidth: 2
        });
        
        let imgInstance = null, isCropping = false, cropRect = null;
        const undoStack = [];
        let imagePath = <?php echo json_encode($path, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const imageSourceUrl = <?php echo json_encode($imageUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const galleryImages = JSON.parse(document.body.dataset.images || '[]');
        let galleryIndex = parseInt(document.body.dataset.currentIndex || '0', 10);
        const galleryFullPath = document.body.dataset.fullPath || '';
        const galleryApiUrl = document.body.dataset.apiUrl || '';
        const galleryRoot = document.body.dataset.root || 'main';
        const galleryFolderDir = galleryFullPath.includes('/') ? galleryFullPath.substring(0, galleryFullPath.lastIndexOf('/')) : '';
        const fileProgress = new FileLoadProgress({
            theme: document.body.dataset.theme || 'light',
            color: '#' + (document.body.dataset.accentColor || '85b503').replace(/^#/, '')
        });
              currentUser = <?php echo json_encode($currentUser, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>,
              lang = <?php echo json_encode($lang, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>, 
              translations = <?php echo json_encode($t); ?>;
        
        // Zoom and pan variables
        let zoomLevel = 1;
        let minZoom = 0.1;
        let maxZoom = 10;
        let isPanning = false;
        let lastPosX = 0;
        let lastPosY = 0;
        let canvasOffset = { x: 0, y: 0 };
        
                // Global drawing settings
        let currentColor = '<?php echo $theme === 'dark' ? '#ffffff' : '#000000'; ?>';
        let currentLineWidth = 3;
        let isDrawingShapeMode = false;
        let currentShape = null;
        let startPoint = null;
        let activeShapeType = null;
        
        // Filter history for undo functionality
        let filterHistory = [];
        let currentFilterState = {};

        // Context menu variables
        let contextMenuTarget = null;

        console.log('Photo Editor loaded:', { imagePath, currentUser });

        function pushState() { 
            const json = canvas.toJSON(['selectable', 'evented', 'id']); 
            undoStack.push(JSON.stringify(json)); 
            if (undoStack.length > 50) undoStack.shift(); 
        }
        
        function undo() { 
            if (undoStack.length < 2) return; 
            undoStack.pop(); 
            const state = undoStack[undoStack.length - 1]; 
            canvas.loadFromJSON(state, () => { 
                canvas.renderAll(); 
                imgInstance = canvas.getObjects().find(o => o.id === 'mainImage'); 
            }); 
        }
        
                // Function to resize the canvas based on the image
        function resizeCanvasToFitImage(imgWidth, imgHeight, keepZoomAndPan = false) {
            const container = document.getElementById('canvas-container');
            const maxW = container.clientWidth * 0.9;
            const maxH = container.clientHeight * 0.9;
            
            if (!keepZoomAndPan) {
                // Reset zoom and pan only if not keeping them
                zoomLevel = 1;
                canvasOffset = { x: 0, y: 0 };
                updateZoomDisplay();
            }
            
            let w = imgWidth, h = imgHeight;
            
                        // Scale so the image fits the container
            const scale = Math.min(maxW / w, maxH / h, 1);
            w *= scale;
            h *= scale;
            
            canvas.setWidth(w); 
            canvas.setHeight(h);
            
                        // Apply scale and pan
            applyZoomAndPan();
            
            return scale;
        }

        function applyImageToCanvas(img) {
            canvas.clear();
            undoStack.length = 0;
            imgInstance = img;
            imgInstance.set({
                left: 0,
                top: 0,
                selectable: false,
                evented: false,
                id: 'mainImage',
                originX: 'left',
                originY: 'top'
            });
            const container = document.getElementById('canvas-container');
            const maxW = container.clientWidth * 0.9;
            const maxH = container.clientHeight * 0.9;
            const scale = Math.min(maxW / img.width, maxH / img.height, 1);
            imgInstance.scale(scale);
            canvas.setWidth(img.width * scale);
            canvas.setHeight(img.height * scale);
            canvas.add(imgInstance).sendToBack(imgInstance).renderAll();
            resetFilters();
            pushState();
        }
        function loadImage(src) {
            return new Promise((resolve, reject) => {
                fabric.Image.fromURL(src, img => {
                    applyImageToCanvas(img);
                    resolve();
                }, { crossOrigin: 'anonymous' });
            });
        }
        async function loadImageFromApiUrl(url, relativePath) {
            try {
                const blob = await fileProgress.fetchBlob(url, { downloadWeight: 96 });
                const objUrl = URL.createObjectURL(blob);
                await loadImage(objUrl);
                URL.revokeObjectURL(objUrl);
                imagePath = relativePath;
                fileProgress.complete();
            } catch (e) {
                fileProgress.fail(translations.error || 'Error');
                throw e;
            }
        }
        function relativePathForGalleryIndex(index) {
            const fileName = galleryImages[index];
            if (!fileName) return imagePath;
            return galleryFolderDir ? galleryFolderDir + '/' + fileName : fileName;
        }
        function apiPathFromRelative(relativePath) {
            let apiPath = relativePath;
            const m = String(relativePath).match(/^storage\/[^/]+\/(?:\.[^/]+\/)?(.+)$/);
            if (m) apiPath = m[1];
            return apiPath;
        }
        function updateGalleryNavButtons() {
            const prevBtn = document.getElementById('photo-prev-btn');
            const nextBtn = document.getElementById('photo-next-btn');
            if (!prevBtn || !nextBtn) return;
            prevBtn.disabled = galleryIndex <= 0;
            nextBtn.disabled = galleryIndex >= galleryImages.length - 1;
        }
        async function loadGalleryIndex(index) {
            if (index < 0 || index >= galleryImages.length) return;
            galleryIndex = index;
            const relativePath = relativePathForGalleryIndex(index);
            const apiPath = apiPathFromRelative(relativePath);
            const url = galleryApiUrl + '?action=raw&path=' + encodeURIComponent(apiPath) + '&root=' + encodeURIComponent(galleryRoot);
            await loadImageFromApiUrl(url, relativePath);
            updateGalleryNavButtons();
            if (window.parent && window.parent.postMessage) {
                window.parent.postMessage({ type: 'UPDATE_TAB_TITLE', title: galleryImages[index] }, '*');
            }
        }
        if (imageSourceUrl) {
            loadImageFromApiUrl(imageSourceUrl, imagePath).catch(() => {});
        } else {
            console.error('No image path provided');
        }
        const photoPrevBtn = document.getElementById('photo-prev-btn');
        const photoNextBtn = document.getElementById('photo-next-btn');
        if (photoPrevBtn) photoPrevBtn.onclick = () => { if (galleryIndex > 0) loadGalleryIndex(galleryIndex - 1); };
        if (photoNextBtn) photoNextBtn.onclick = () => { if (galleryIndex < galleryImages.length - 1) loadGalleryIndex(galleryIndex + 1); };
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            if (galleryImages.length <= 1) return;
            if (e.key === 'ArrowLeft' && galleryIndex > 0) { e.preventDefault(); loadGalleryIndex(galleryIndex - 1); }
            if (e.key === 'ArrowRight' && galleryIndex < galleryImages.length - 1) { e.preventDefault(); loadGalleryIndex(galleryIndex + 1); }
        });

        window.onresize = () => { 
            if (imgInstance) { 
                                // Get the current image dimensions accounting for scale
                const imgWidth = imgInstance.width * imgInstance.scaleX;
                const imgHeight = imgInstance.height * imgInstance.scaleY;
                
                                // Resize the canvas to the new container dimensions
                const container = document.getElementById('canvas-container');
                const maxW = container.clientWidth * 0.9;
                const maxH = container.clientHeight * 0.9;
                
                const scale = Math.min(maxW / imgWidth, maxH / imgHeight, 1);
                
                canvas.setWidth(imgWidth * scale); 
                canvas.setHeight(imgHeight * scale);
                
                                // Scale the image
                imgInstance.scale(imgInstance.scaleX * scale / (imgInstance.scaleX));
                
                                // Apply the current zoom and pan
                applyZoomAndPan();
            } 
        };

        // Zoom and Pan Functions
        function applyZoomAndPan() {
            const viewportTransform = canvas.viewportTransform;
            if (viewportTransform) {
                viewportTransform[0] = zoomLevel;
                viewportTransform[3] = zoomLevel;
                viewportTransform[4] = canvasOffset.x;
                viewportTransform[5] = canvasOffset.y;
                canvas.setViewportTransform(viewportTransform);
                canvas.renderAll();
                updateZoomDisplay();
            }
        }

        function zoom(amount, centerX = canvas.width / 2, centerY = canvas.height / 2) {
            const oldZoom = zoomLevel;
            zoomLevel *= amount;
            zoomLevel = Math.max(minZoom, Math.min(maxZoom, zoomLevel));
            
            // Adjust offset to zoom around center point
            const zoomRatio = zoomLevel / oldZoom;
            canvasOffset.x = centerX - (centerX - canvasOffset.x) * zoomRatio;
            canvasOffset.y = centerY - (centerY - canvasOffset.y) * zoomRatio;
            
            applyZoomAndPan();
        }

        function updateZoomDisplay() {
            document.getElementById('zoom-display').textContent = Math.round(zoomLevel * 100) + '%';
        }

        function resetView() {
            zoomLevel = 1;
            canvasOffset = { x: 0, y: 0 };
            applyZoomAndPan();
        }

        // Mouse wheel zoom
        canvas.wrapperEl.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? 0.9 : 1.1;
            const rect = canvas.wrapperEl.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            zoom(delta, x, y);
        });

        // Mouse panning
        canvas.on('mouse:down', (opt) => {
            if (isPanning && opt.e.button === 0) { // Left mouse button
                lastPosX = opt.e.clientX;
                lastPosY = opt.e.clientY;
                canvas.selection = false;
                canvas.defaultCursor = 'grabbing';
            }
        });

        canvas.on('mouse:move', (opt) => {
            if (isPanning && opt.e.buttons === 1) { // Left mouse button pressed
                const dx = opt.e.clientX - lastPosX;
                const dy = opt.e.clientY - lastPosY;
                
                canvasOffset.x += dx;
                canvasOffset.y += dy;
                
                lastPosX = opt.e.clientX;
                lastPosY = opt.e.clientY;
                
                applyZoomAndPan();
            }
        });

        canvas.on('mouse:up', () => {
            if (isPanning) {
                canvas.selection = true;
                canvas.defaultCursor = 'default';
            }
        });

        // Pan mode toggle
        document.getElementById('pan-btn').onclick = () => {
            isPanning = !isPanning;
            const panText = document.getElementById('pan-text');
            const panIndicator = document.getElementById('pan-indicator');
            
            if (isPanning) {
                panText.textContent = translations['disable_draw'];
                panIndicator.style.display = 'block';
                canvas.selection = false;
                canvas.defaultCursor = 'grab';
            } else {
                panText.textContent = translations['pan_mode'];
                panIndicator.style.display = 'none';
                canvas.selection = true;
                canvas.defaultCursor = 'default';
            }
        };

        // Zoom buttons
        document.getElementById('zoom-in-btn').onclick = () => zoom(1.2);
        document.getElementById('zoom-out-btn').onclick = () => zoom(0.8);
        document.getElementById('zoom-in-btn-top').onclick = () => zoom(1.2);
        document.getElementById('zoom-out-btn-top').onclick = () => zoom(0.8);
        document.getElementById('reset-view-btn').onclick = resetView;

        // Modal functions
        function showModal(title, message, isError = false) {
            document.getElementById('modal-title').textContent = title;
            document.getElementById('modal-body').textContent = message;
            document.getElementById('modal').style.display = 'flex';
        }

        function hideModal() {
            document.getElementById('modal').style.display = 'none';
        }

        document.getElementById('modal-close').onclick = hideModal;
        document.getElementById('modal-ok').onclick = hideModal;

        document.getElementById('crop-btn').onclick = () => {
            const cropText = document.getElementById('crop-text');
            if (!isCropping) {
                isCropping = true;
                cropRect = new fabric.Rect({ 
                    fill: 'rgba(0,0,0,0.3)', 
                    stroke: '#fff', 
                    strokeWidth: 2, 
                    strokeDashArray: [5, 5], 
                    left: canvas.width*0.1, 
                    top: canvas.height*0.1, 
                    width: canvas.width*0.8, 
                    height: canvas.height*0.8, 
                    hasControls: true, 
                    hasBorders: true, 
                    lockRotation: true 
                });
                canvas.add(cropRect).setActiveObject(cropRect); 
                cropText.textContent = translations['apply_crop'];
            } else {
                isCropping = false;
                const dataURL = canvas.toDataURL({ 
                    left: cropRect.left, 
                    top: cropRect.top, 
                    width: cropRect.getScaledWidth(), 
                    height: cropRect.getScaledHeight(), 
                    format: 'png' 
                });
                canvas.remove(cropRect); 
                cropRect = null; 
                loadImage(dataURL); 
                cropText.textContent = translations['crop'];
            }
        };

                // Completely reworked rotation function
        const rotate = (angle) => { 
            if (!imgInstance) return; 
            
            pushState(); // Save state before rotating
            
                        // Get the current dimensions and position
            const oldWidth = canvas.width;
            const oldHeight = canvas.height;
            const oldScaleX = imgInstance.scaleX;
            const oldScaleY = imgInstance.scaleY;
            
                        // Rotate the image
            imgInstance.rotate((imgInstance.angle || 0) + angle);
            
                        // Get the image dimensions after rotation
            const imgWidth = imgInstance.width * oldScaleX;
            const imgHeight = imgInstance.height * oldScaleY;
            
                        // Normalize the angle to 0-360
            let normalizedAngle = imgInstance.angle % 360;
            if (normalizedAngle < 0) normalizedAngle += 360;
            
                        // Determine whether the canvas size needs changing
            let newWidth, newHeight;
            
            if (normalizedAngle === 90 || normalizedAngle === 270) {
                                // Swap the width and height
                newWidth = imgHeight;
                newHeight = imgWidth;
            } else if (normalizedAngle === 180) {
                                // Dimensions stay the same but the image is flipped
                newWidth = imgWidth;
                newHeight = imgHeight;
            } else {
                                // For arbitrary angles, compute the bounding rectangle
                const radians = (normalizedAngle * Math.PI) / 180;
                const sin = Math.abs(Math.sin(radians));
                const cos = Math.abs(Math.cos(radians));
                
                newWidth = imgWidth * cos + imgHeight * sin;
                newHeight = imgWidth * sin + imgHeight * cos;
            }
            
                        // Resize the canvas
            canvas.setWidth(newWidth);
            canvas.setHeight(newHeight);
            
                        // Center the image on the new canvas
            imgInstance.set({
                left: canvas.width / 2,
                top: canvas.height / 2,
                originX: 'center',
                originY: 'center'
            });
            
            canvas.renderAll(); 
        };
        
        document.getElementById('rotate-left').onclick = () => rotate(-90);
        document.getElementById('rotate-right').onclick = () => rotate(90);
        
        const drawBtn = document.getElementById('draw-btn'), 
              drawText = document.getElementById('draw-text');
              
        drawBtn.onclick = () => {
            canvas.isDrawingMode = !canvas.isDrawingMode;
            drawText.textContent = canvas.isDrawingMode ? translations['disable_draw'] : translations['draw'];
            isDrawingShapeMode = false;
            canvas.defaultCursor = 'default';
            canvas.selection = true;
        };
        
                // Initialize the drawing brush
        canvas.freeDrawingBrush = new fabric.PencilBrush(canvas);
        canvas.freeDrawingBrush.color = currentColor;
        canvas.freeDrawingBrush.width = currentLineWidth;
        
                // Update global color and thickness settings
        document.getElementById('drawing-color').oninput = e => { 
            currentColor = e.target.value;
            canvas.freeDrawingBrush.color = currentColor;
        };
        
        document.getElementById('line-width').oninput = e => { 
            currentLineWidth = parseInt(e.target.value, 10);
            canvas.freeDrawingBrush.width = currentLineWidth;
            document.getElementById('line-width-val').textContent = e.target.value;
        };
        
        canvas.on('path:created', pushState);

        // Improved shape drawing functionality
        const shapeButtons = document.querySelectorAll('button[data-shape]');
        
        shapeButtons.forEach(btn => {
            btn.onclick = () => {
                isDrawingShapeMode = true;
                activeShapeType = btn.dataset.shape;
                canvas.isDrawingMode = false;
                drawText.textContent = translations['draw'];
                canvas.selection = false;
                canvas.defaultCursor = 'crosshair';
            };
        });

                // Completely reworked arrow class
        class CustomArrow {
            static create(x1, y1, x2, y2, options = {}) {
                const stroke = options.stroke || '#000000';
                const strokeWidth = options.strokeWidth || 3;
                const headLength = options.headLength || 20;
                const headWidth = options.headWidth || 15;
                
                                // Compute the angle and line length
                const dx = x2 - x1;
                const dy = y2 - y1;
                const angle = Math.atan2(dy, dx);
                const length = Math.sqrt(dx * dx + dy * dy);
                
                                // If the length is too small, return a plain line
                if (length < headLength * 1.5) {
                    return new fabric.Line([x1, y1, x2, y2], {
                        stroke: stroke,
                        strokeWidth: strokeWidth,
                        strokeLineCap: 'round'
                    });
                }
                
                                // Create a group for the arrow
                const objects = [];
                
                                // Main line
                const line = new fabric.Line([x1, y1, x2, y2], {
                    stroke: stroke,
                    strokeWidth: strokeWidth,
                    strokeLineCap: 'round',
                    evented: false
                });
                objects.push(line);
                
                                // Arrowhead (triangle)
                const arrowAngle = (angle * 180 / Math.PI) - 35;
                
                const arrowHead = new fabric.Triangle({
                    left: x2,
                    top: y2,
                    width: headLength,
                    height: headWidth,
                    fill: stroke,
                    angle: arrowAngle,
                    originX: 'center',
                    originY: 'center',
                    evented: false
                });
                objects.push(arrowHead);
                
                                // Create the group
                const group = new fabric.Group(objects, {
                    left: Math.min(x1, x2),
                    top: Math.min(y1, y2),
                    selectable: true,
                    hasControls: true,
                    hasBorders: true,
                    lockRotation: false,
                    lockScalingX: false,
                    lockScalingY: false,
                    subTargetCheck: false
                });
                
                                // Save the original points for later editing
                group.set('x1', x1);
                group.set('y1', y1);
                group.set('x2', x2);
                group.set('y2', y2);
                group.set('customType', 'arrow');
                
                return group;
            }
        }

        canvas.on('mouse:down', (o) => {
            if (!isDrawingShapeMode || o.target) return;
            
            const pointer = canvas.getPointer(o.e);
            startPoint = pointer;
            
            switch (activeShapeType) {
                case 'line':
                    currentShape = new fabric.Line([pointer.x, pointer.y, pointer.x, pointer.y], {
                        stroke: currentColor,
                        strokeWidth: currentLineWidth,
                        strokeLineCap: 'round'
                    });
                    break;
                case 'rectangle':
                    currentShape = new fabric.Rect({
                        left: pointer.x,
                        top: pointer.y,
                        width: 0,
                        height: 0,
                        fill: 'transparent',
                        stroke: currentColor,
                        strokeWidth: currentLineWidth
                    });
                    break;
                case 'circle':
                    currentShape = new fabric.Circle({
                        left: pointer.x,
                        top: pointer.y,
                        radius: 1,
                        fill: 'transparent',
                        stroke: currentColor,
                        strokeWidth: currentLineWidth
                    });
                    break;
                case 'arrow':
                                        // Use the new arrow class
                    currentShape = CustomArrow.create(pointer.x, pointer.y, pointer.x, pointer.y, {
                        stroke: currentColor,
                        strokeWidth: currentLineWidth,
                        headLength: 20,
                        headWidth: 15
                    });
                    break;
            }
            
            if (currentShape) {
                canvas.add(currentShape);
            }
        });

        canvas.on('mouse:move', (o) => {
            if (!isDrawingShapeMode || !startPoint || !currentShape) return;
            
            const pointer = canvas.getPointer(o.e);
            
            switch (activeShapeType) {
                case 'line':
                    currentShape.set({ x2: pointer.x, y2: pointer.y });
                    break;
                case 'rectangle':
                    currentShape.set({
                        width: pointer.x - startPoint.x,
                        height: pointer.y - startPoint.y
                    });
                    break;
                case 'circle':
                    const radius = Math.sqrt(
                        Math.pow(pointer.x - startPoint.x, 2) + 
                        Math.pow(pointer.y - startPoint.y, 2)
                    );
                    currentShape.set({ radius: radius });
                    break;
                case 'arrow':
                                        // Update the arrow - remove the old one and create a new one
                    canvas.remove(currentShape);
                    currentShape = CustomArrow.create(startPoint.x, startPoint.y, pointer.x, pointer.y, {
                        stroke: currentColor,
                        strokeWidth: currentLineWidth,
                        headLength: 20,
                        headWidth: 15
                    });
                    canvas.add(currentShape);
                    break;
            }
            
            canvas.renderAll();
        });

        canvas.on('mouse:up', () => {
            if (!isDrawingShapeMode || !currentShape) return;
            
                        // Check the minimum size
            let shouldRemove = false;
            
            if (activeShapeType === 'line' || activeShapeType === 'arrow') {
                const dx = currentShape.x2 - currentShape.x1;
                const dy = currentShape.y2 - currentShape.y1;
                const distance = Math.sqrt(dx * dx + dy * dy);
                shouldRemove = distance < 10;
            } else if (activeShapeType === 'rectangle') {
                shouldRemove = Math.abs(currentShape.width) < 10 || Math.abs(currentShape.height) < 10;
            } else if (activeShapeType === 'circle') {
                shouldRemove = currentShape.radius < 10;
            }
            
            if (shouldRemove) {
                canvas.remove(currentShape);
            } else {
                pushState();
            }
            
            // Reset state
            startPoint = null;
            currentShape = null;
            isDrawingShapeMode = false;
            activeShapeType = null;
            canvas.selection = true;
            canvas.defaultCursor = 'default';
        });

        document.getElementById('add-text').onclick = () => {
            const text = document.getElementById('text-input').value || translations['text_placeholder'];
            const txtObj = new fabric.IText(text, { 
                left: canvas.width / 2, 
                top: canvas.height / 2, 
                fill: currentColor,
                fontSize: 40, 
                fontFamily: 'Inter',
                textAlign: 'center',
                originX: 'center',
                originY: 'center'
            });
            canvas.add(txtObj).setActiveObject(txtObj).renderAll(); 
            pushState();
            document.getElementById('text-input').value = '';
        };

        // Context Menu Functions
        function showContextMenu(x, y, target) {
            contextMenuTarget = target;
            
            const contextMenu = document.getElementById('context-menu');
            const textItem = document.getElementById('context-change-text');
            
            // Show/hide text edit option
            if (target && target.type === 'i-text') {
                textItem.style.display = 'block';
            } else {
                textItem.style.display = 'none';
            }
            
            contextMenu.style.left = x + 'px';
            contextMenu.style.top = y + 'px';
            contextMenu.style.display = 'block';
            
            // Hide context menu when clicking elsewhere
            setTimeout(() => {
                document.addEventListener('click', hideContextMenu);
            }, 10);
        }

        function hideContextMenu() {
            const contextMenu = document.getElementById('context-menu');
            contextMenu.style.display = 'none';
            document.removeEventListener('click', hideContextMenu);
        }

        // Right click for context menu
        canvas.on('mouse:down', (opt) => {
            if (opt.e.button === 2) { // Right click
                opt.e.preventDefault();
                if (opt.target) {
                    showContextMenu(opt.e.clientX, opt.e.clientY, opt.target);
                }
            }
        });

        // Context menu actions
        document.getElementById('context-delete').onclick = () => {
            if (contextMenuTarget) {
                canvas.remove(contextMenuTarget);
                pushState();
                hideContextMenu();
            }
        };

        document.getElementById('context-change-color').onclick = () => {
            if (contextMenuTarget) {
                const colorPicker = document.createElement('input');
                colorPicker.type = 'color';
                colorPicker.value = currentColor;
                
                colorPicker.onchange = (e) => {
                    const newColor = e.target.value;
                    
                    if (contextMenuTarget.type === 'i-text' || contextMenuTarget.type === 'text') {
                        contextMenuTarget.set('fill', newColor);
                    } else if (contextMenuTarget.customType === 'arrow') {
                                                // For the arrow, change the color of all elements
                        contextMenuTarget.forEachObject(obj => {
                            if (obj.type === 'line') {
                                obj.set('stroke', newColor);
                            } else if (obj.type === 'triangle') {
                                obj.set('fill', newColor);
                            }
                        });
                    } else {
                        contextMenuTarget.set('stroke', newColor);
                    }
                    canvas.renderAll();
                    pushState();
                };
                
                colorPicker.click();
                hideContextMenu();
            }
        };

        document.getElementById('context-change-text').onclick = () => {
            if (contextMenuTarget && contextMenuTarget.type === 'i-text') {
                canvas.setActiveObject(contextMenuTarget);
                contextMenuTarget.enterEditing();
                contextMenuTarget.hiddenTextarea.focus();
                hideContextMenu();
            }
        };

        document.getElementById('context-bring-front').onclick = () => {
            if (contextMenuTarget) {
                canvas.bringToFront(contextMenuTarget);
                canvas.renderAll();
                pushState();
                hideContextMenu();
            }
        };

        document.getElementById('context-send-back').onclick = () => {
            if (contextMenuTarget) {
                canvas.sendToBack(contextMenuTarget);
                canvas.renderAll();
                pushState();
                hideContextMenu();
            }
        };

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Delete selected object
            if ((e.key === 'Delete' || e.key === 'Backspace') && canvas.getActiveObject()) {
                canvas.remove(canvas.getActiveObject());
                pushState();
            }
            
            // Ctrl+Z for undo
            if ((e.ctrlKey || e.metaKey) && e.key === 'z') {
                e.preventDefault();
                undo();
            }
            
            // Zoom with keyboard
            if (e.ctrlKey && e.key === '+') {
                e.preventDefault();
                zoom(1.2);
            }
            if (e.ctrlKey && e.key === '-') {
                e.preventDefault();
                zoom(0.8);
            }
            if (e.ctrlKey && e.key === '0') {
                e.preventDefault();
                resetView();
            }
        });

        const emojiData = {
            'People': ['😀','😂','😍','🤔','😎','😭','😱','🥳','👍','👎','💖','🕶️','💪','👀','🧠'], 
            'Nature': ['🐶','🐱','🐭','🦊','🐻','🐼','🐨','🐵','🌳','🌵','🌷','🌸','☀️','🌙','⭐'],
            'Food': ['🍏','🍊','🍓','🍉','🍇','🍕','🍔','🍟','🍣','🍩','☕','🍺','🍷'], 
            'Activities': ['⚽','🏀','🏈','🎾','🎳','🎮','🎨','🎬','🎤','🎧','🎸','✈️','🚀'],
            'Objects': ['💻','📱','⌚','📷','💡','💣','🎉','🎁','🔑','💰','💎','💯','🎯','⚡']
        };
        
        const accordionContainer = document.getElementById('emoji-accordion');
        for (const category in emojiData) {
            const categoryDiv = document.createElement('div'); 
            categoryDiv.className = 'emoji-category';
            const header = document.createElement('div'); 
            header.className = 'emoji-header'; 
            header.textContent = category;
            const content = document.createElement('div'); 
            content.className = 'emoji-content';
            
            emojiData[category].forEach(emoji => {
                const span = document.createElement('span'); 
                span.textContent = emoji; 
                span.className = 'emoji';
                span.onclick = () => { 
                    const emojiObj = new fabric.Text(emoji, { 
                        left: canvas.width / 2, 
                        top: canvas.height / 2, 
                        fontSize: 50,
                        fill: currentColor,
                        originX: 'center',
                        originY: 'center'
                    }); 
                    canvas.add(emojiObj).setActiveObject(emojiObj).renderAll(); 
                    pushState(); 
                };
                content.appendChild(span);
            });
            
            header.onclick = () => { 
                header.classList.toggle('active'); 
                content.style.display = content.style.display === 'block' ? 'none' : 'block'; 
            };
            categoryDiv.appendChild(header); 
            categoryDiv.appendChild(content); 
            accordionContainer.appendChild(categoryDiv);
        }

        const sliders = [
            { id: 'brightness', inputId: 'brightness-input', min: -100, max: 100, step: 1 },
            { id: 'contrast', inputId: 'contrast-input', min: -100, max: 100, step: 1 },
            { id: 'saturation', inputId: 'saturation-input', min: -100, max: 100, step: 1 },
            { id: 'hue', inputId: 'hue-input', min: -180, max: 180, step: 1 },
            { id: 'blur', inputId: 'blur-input', min: 0, max: 1, step: 0.01 }
        ];
        
        function saveFilterState() {
            const state = {};
            sliders.forEach(slider => {
                state[slider.id] = document.getElementById(slider.id).value;
            });
            state.filter = document.getElementById('filter-select').value;
            filterHistory.push(JSON.stringify(state));
            if (filterHistory.length > 20) filterHistory.shift();
            currentFilterState = state;
        }
        
        function applyFilters() {
            if (!imgInstance) return; 
            imgInstance.filters = [];
            sliders.forEach(slider => {
                const val = parseFloat(document.getElementById(slider.id).value);
                if (val === 0 && slider.id !== 'hue') return;
                let filter;
                if (slider.id === 'brightness') filter = new fabric.Image.filters.Brightness({ brightness: val / 100 });
                else if (slider.id === 'contrast') filter = new fabric.Image.filters.Contrast({ contrast: val / 100 });
                else if (slider.id === 'saturation') filter = new fabric.Image.filters.Saturation({ saturation: val / 100 });
                else if (slider.id === 'hue') filter = new fabric.Image.filters.HueRotation({ rotation: val });
                else if (slider.id === 'blur' && val > 0) filter = new fabric.Image.filters.Blur({ blur: val });
                if(filter) imgInstance.filters.push(filter);
            });
            
            const selectedFilter = document.getElementById('filter-select').value;
            if (selectedFilter) { 
                const FilterClass = fabric.Image.filters[selectedFilter.charAt(0).toUpperCase() + selectedFilter.slice(1)]; 
                if (FilterClass) imgInstance.filters.push(new FilterClass()); 
            }
            imgInstance.applyFilters(); 
            canvas.renderAll();
        }
        
        function updateFilterInputs() { 
            sliders.forEach(slider => { 
                const sliderEl = document.getElementById(slider.id);
                const inputEl = document.getElementById(slider.inputId);
                inputEl.value = sliderEl.value;
            }); 
        }
        
        function resetFilters() { 
            sliders.forEach(slider => { 
                document.getElementById(slider.id).value = 0; 
                document.getElementById(slider.inputId).value = 0;
            }); 
            document.getElementById('filter-select').value = ''; 
            updateFilterInputs(); 
            applyFilters(); 
            saveFilterState();
        }
        
        // Initialize filter history
        saveFilterState();
        
        document.getElementById('filter-select').onchange = () => {
            applyFilters();
            saveFilterState();
        };
        
        sliders.forEach(slider => { 
            const sliderEl = document.getElementById(slider.id);
            const inputEl = document.getElementById(slider.inputId);
            
            sliderEl.oninput = () => { 
                inputEl.value = sliderEl.value;
                applyFilters(); 
                saveFilterState();
            };
            
            inputEl.oninput = () => {
                let value = parseFloat(inputEl.value);
                if (isNaN(value)) value = 0;
                value = Math.max(slider.min, Math.min(slider.max, value));
                inputEl.value = value;
                sliderEl.value = value;
                applyFilters();
                saveFilterState();
            };
        });
        
        canvas.on('object:modified', pushState);

        document.getElementById('undo-btn').onclick = () => {
            if (filterHistory.length > 1) {
                filterHistory.pop();
                const prevState = JSON.parse(filterHistory[filterHistory.length - 1]);
                
                sliders.forEach(slider => {
                    document.getElementById(slider.id).value = prevState[slider.id];
                    document.getElementById(slider.inputId).value = prevState[slider.id];
                });
                document.getElementById('filter-select').value = prevState.filter;
                
                applyFilters();
                currentFilterState = prevState;
            } else {
                undo();
            }
        };
        
        document.getElementById('save-btn').onclick = () => {
            // Temporarily reset viewport for saving
            const tempTransform = canvas.viewportTransform.slice();
            const tempZoom = zoomLevel;
            const tempOffset = { ...canvasOffset };
            
            resetView();
            
            setTimeout(() => {
                const dataURL = canvas.toDataURL({ format: 'png', quality: 1 });
                console.log('Saving image to:', imagePath);
                
                if (imagePath) {
                    fetch(window.location.href, { 
                        method: 'POST', 
                        headers: { 'Content-Type': 'application/json' }, 
                        body: JSON.stringify({ 
                            path: imagePath, 
                            data: dataURL 
                        }) 
                    })
                    .then(res => res.json())
                    .then(data => {
                        console.log('Save response:', data);
                        if (data.success) {
                            showModal(translations['success'], translations['image_saved']);
                                                        // Send a message to the parent window
                            if (window.parent) {
                                window.parent.postMessage({
                                    type: 'photoEditorSaved',
                                    relativePath: data.relative_path,
                                    fullPath: data.full_path
                                }, '*');
                            }
                        } else {
                            showModal(translations['error'], translations['save_error'] + ' ' + (data.error || 'Unknown error'), true);
                        }
                        
                        // Restore viewport
                        zoomLevel = tempZoom;
                        canvasOffset = tempOffset;
                        applyZoomAndPan();
                    })
                    .catch(error => {
                        console.error('Save error:', error);
                        showModal(translations['error'], translations['save_error'] + ' ' + error.message, true);
                        
                        // Restore viewport
                        zoomLevel = tempZoom;
                        canvasOffset = tempOffset;
                        applyZoomAndPan();
                    });
                } else { 
                    const link = document.createElement('a'); 
                    link.href = dataURL; 
                    link.download = 'edited-photo.png'; 
                    link.click(); 
                    
                    // Restore viewport
                    zoomLevel = tempZoom;
                    canvasOffset = tempOffset;
                    applyZoomAndPan();
                }
            }, 100);
        };
        
        updateFilterInputs();
        updateZoomDisplay();
        
        // Initialize canvas viewport
        applyZoomAndPan();
    