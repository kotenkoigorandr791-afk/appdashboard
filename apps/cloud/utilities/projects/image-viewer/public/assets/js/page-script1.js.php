        document.addEventListener('DOMContentLoaded', function () {
            const image = document.getElementById('image');
            if (!image) return;

            const images = JSON.parse(document.body.dataset.images || '[]');
            const folder = document.body.dataset.folder;
            const fullPath = document.body.dataset.fullPath;
            let currentIndex = parseInt(document.body.dataset.currentIndex || '0');
            let currentGpsData = null;
            let currentObjectUrl = null;
            const accentColor = document.body.dataset.accentColor || '85b503';
            const fileProgress = new FileLoadProgress({
                theme: document.body.dataset.theme || 'light',
                color: '#' + accentColor.replace(/^#/, '')
            });
            const navButtons = document.querySelector('.nav-buttons');
            if (images.length <= 1 && navButtons) navButtons.classList.add('is-hidden');

            // Extract the relative path for the API
            let apiBasePath = fullPath;
            const pathMatch = fullPath.match(/^storage\/[^/]+\/(?:\.[^/]+\/)?(.+)$/);
            if (pathMatch) {
                apiBasePath = pathMatch[1];
            }

            // Function to load GPS metadata via the API
            function loadGpsMetadata(apiPath, callback) {
                fetch(`<?= htmlspecialchars($apiUrl) ?>?action=metadata&path=${encodeURIComponent(apiPath)}&root=<?= rawurlencode($root) ?>`)
                    .then(res => res.json())
                    .then(data => {
                        const gps = (data && data.gps) ? data.gps : null;
                        callback(gps);
                    })
                    .catch(() => callback(null));
            }

            // Initialize the map if GPS data exists
            function initMap(lat, lon, theme) {
                const mapContainer = document.getElementById('locationMap');
                if (!lat || !lon) {
                    mapContainer.style.display = 'none';
                    return;
                }

                mapContainer.style.display = 'block';
                
                const embedUrl = `https://www.openstreetmap.org/export/embed.html?bbox=${lon-0.005},${lat-0.005},${lon+0.005},${lat+0.005}&layer=mapnik&marker=${lat},${lon}`;
                
                mapContainer.innerHTML = `<iframe name="cloud-map-embed" title="Map Location" src="${embedUrl}" loading="lazy"></iframe>`;
            }

            // Extract base path from fullPath (e.g., "storage/root_root/123/image1.jpg" -> "storage/root_root/123")
            let basePath = '';
            if (fullPath && folder) {
                const lastSlash = fullPath.lastIndexOf('/');
                if (lastSlash !== -1) {
                    basePath = fullPath.substring(0, lastSlash);
                }
            }
            
            let viewer = null;

            function createViewer() {
                if (viewer) {
                    viewer.destroy();
                    viewer = null;
                }
                viewer = new Viewer(image, {
                    inline: true,
                    button: false,
                    navbar: false,
                    title: false,
                    toolbar: {
                        zoomIn: 4,
                        zoomOut: 4,
                        oneToOne: 4,
                        reset: 4,
                        prev: false,
                        next: false,
                        rotateLeft: 4,
                        rotateRight: 4,
                        flipHorizontal: 4,
                        flipVertical: 4
                    },
                    tooltip: false,
                    movable: true,
                    zoomable: true,
                    rotatable: true,
                    scalable: true,
                    transition: true,
                    fullscreen: false,
                    keyboard: true,
                    backdrop: true,
                    viewed: function() {
                        setTimeout(() => {
                            if (this.zoomable && typeof this.zoomTo === 'function') {
                                this.zoomTo(0.95);
                            }
                        }, 100);
                    }
                });
            }

            function fetchImageToElement(url) {
                return fileProgress.fetchBlob(url, { downloadWeight: 96 }).then(blob => {
                    if (currentObjectUrl) URL.revokeObjectURL(currentObjectUrl);
                    currentObjectUrl = URL.createObjectURL(blob);
                    return new Promise((resolve, reject) => {
                        image.onload = () => resolve();
                        image.onerror = () => reject(new Error('Image decode failed'));
                        image.src = currentObjectUrl;
                    });
                });
            }

            // Function to load a new image
            async function loadImage(index) {
                if (index < 0 || index >= images.length) return;

                const fileName = images[index];
                // Construct full path using basePath from data attribute
                const fullPathForImage = basePath ? `${basePath}/${fileName}` : (folder ? `${folder}/${fileName}` : fileName);

                // Extract relative path for API (remove storage/{user}/ prefix)
                let apiPath = fullPathForImage;
                const pathMatch2 = fullPathForImage.match(/^storage\/[^/]+\/(?:\.[^/]+\/)?(.+)$/);
                if (pathMatch2) {
                    apiPath = pathMatch2[1];
                }

                const imageUrl = `<?= htmlspecialchars($apiUrl) ?>?action=raw&path=${encodeURIComponent(apiPath)}&root=<?= rawurlencode($root) ?>`;
                try {
                    await fetchImageToElement(imageUrl);
                    fileProgress.complete();
                } catch (e) {
                    fileProgress.fail('Error');
                    return;
                }

                // Update the indicators
                document.querySelector('.position-indicator').textContent = fileName;
                document.querySelector('.image-counter').textContent = `${index + 1} / ${images.length}`;

                // Update the navigation button state
                const prevBtn = document.querySelector('.prev-btn');
                const nextBtn = document.querySelector('.next-btn');
                if (prevBtn && nextBtn) {
                    if (index <= 0) {
                        prevBtn.classList.add('disabled');
                        prevBtn.disabled = true;
                    } else {
                        prevBtn.classList.remove('disabled');
                        prevBtn.disabled = false;
                    }
                    if (index >= images.length - 1) {
                        nextBtn.classList.add('disabled');
                        nextBtn.disabled = true;
                    } else {
                        nextBtn.classList.remove('disabled');
                        nextBtn.disabled = false;
                    }
                }

                // Load GPS metadata via the API
                loadGpsMetadata(apiPath, function(gps) {
                    if (gps) {
                        currentGpsData = gps;
                        const theme = document.body.getAttribute('data-theme');
                        initMap(gps.lat, gps.lon, theme);
                    } else {
                        currentGpsData = null;
                        document.getElementById('locationMap').style.display = 'none';
                    }
                });

                // Update the current index
                currentIndex = index;
                
                // Update the tab title in the parent (without emoji)
                if (window.parent && window.parent.postMessage) {
                    window.parent.postMessage({ 
                        type: 'UPDATE_TAB_TITLE', 
                        title: fileName
                    }, '*');
                }
                
                createViewer();
            }

            const initialSrc = image.getAttribute('data-initial-src');
            if (initialSrc) {
                fetchImageToElement(initialSrc).then(() => {
                    fileProgress.complete();
                    createViewer();
                    if (apiBasePath) {
                        loadGpsMetadata(apiBasePath, function(gps) {
                            if (gps) {
                                currentGpsData = gps;
                                initMap(gps.lat, gps.lon, document.body.dataset.theme);
                            }
                        });
                    }
                }).catch(() => fileProgress.fail('Error'));
            }

            // Navigation handlers
            const prevNav = document.querySelector('.prev-btn');
            const nextNav = document.querySelector('.next-btn');
            if (prevNav) prevNav.addEventListener('click', () => {
                if (currentIndex > 0) loadImage(currentIndex - 1);
            });
            if (nextNav) nextNav.addEventListener('click', () => {
                if (currentIndex < images.length - 1) loadImage(currentIndex + 1);
            });

            // Keyboard navigation
            document.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    if (currentIndex > 0) {
                        loadImage(currentIndex - 1);
                    }
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    if (currentIndex < images.length - 1) {
                        loadImage(currentIndex + 1);
                    }
                }
            });

            // Sync the theme with the parent
            window.addEventListener('message', function(event) {
                if (event.data.type === 'THEME_UPDATE') {
                    const newTheme = event.data.theme;
                    document.body.setAttribute('data-theme', newTheme);

                    // Update the map style if present
                    const mapContainer = document.getElementById('locationMap');
                    if (mapContainer && mapContainer.style.display !== 'none' && currentGpsData) {
                        initMap(currentGpsData.lat, currentGpsData.lon, newTheme);
                    }
                }
            });

            // Request the theme from the parent on load
            if (window.parent && window.parent.postMessage) {
                window.parent.postMessage({ type: 'REQUEST_THEME' }, '*');
            }

            // Auto-hide the toolbar with a delay
            let hideTimeout;
            document.addEventListener('mousemove', () => {
                clearTimeout(hideTimeout);
                const toolbar = document.querySelector('.viewer-toolbar');
                if (toolbar) toolbar.style.opacity = '0.95';
                hideTimeout = setTimeout(() => {
                    if (toolbar) toolbar.style.opacity = '0.7';
                }, 5000);
            });

            // Block Esc and the context menu
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') e.preventDefault();
            }, true);
            document.addEventListener('contextmenu', (e) => e.preventDefault());
        });
    