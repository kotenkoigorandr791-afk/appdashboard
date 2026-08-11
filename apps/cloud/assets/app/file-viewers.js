function finalizeTabFileLoad(fileContainer, path, tabId, loadToken) {
    if (tabsState.openFileTokens.get(tabId) !== loadToken) return;
    if (fileContainer) {
        fileContainer.dataset.loadedPath = path;
        delete fileContainer.dataset.loadingPath;
    }
    const tab = tabsState.tabs.find((t) => t.id === tabId);
    if (tab) {
        if (typeof stashTabView === 'function') stashTabView(tab);
        else setTimeout(() => cacheTabContent(tab), 50);
    }
}
async function openFile(path, editMode = false, restoredContent = null, rootOverride = null) {
    try {
        const isMobile = isMobileDevice();
        if (isMobile && !editMode) {
        } else if (isMobile && editMode) {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                localStorage.setItem('sidebarVisible', 'false');
            }
        }
        const rootKey = rootOverride || getActiveTabRoot();
        let resp;
        resp = await api('open', { path, root: rootKey }, 'GET');
        const viewer = document.getElementById('viewer');
        const fileName = document.getElementById('file-name');
        fileName.textContent = path.split('/').pop();
        viewer.dataset.path = path;
        viewer.dataset.editMode = editMode ? 'true' : 'false';
        const saveBtn = document.getElementById('btn-save');
        const codeActionsBtn = document.getElementById('btn-code-actions');
        const filePath = path;
        saveBtn.classList.toggle('hidden', !editMode);
        codeActionsBtn.classList.toggle('hidden', !editMode);
        
        // Show/hide compile button based on file type
        const compileMenuItem = document.getElementById('code-action-compile');
        if (compileMenuItem) {
            const fileExt = path.split('.').pop().toLowerCase();
            const supportedLangs = ['java', 'c', 'cpp', 'py', 'm'];
            compileMenuItem.style.display = (editMode && supportedLangs.includes(fileExt)) ? 'flex' : 'none';
        }
        
        if (window.innerWidth <= 768) {
            document.querySelector('.sidebar').classList.remove('active');
            localStorage.setItem('sidebarVisible', 'false');
        }
        const fileExt = path.split('.').pop().toLowerCase();
        const activeTab = tabsState.tabs.find(tab => tab.id === tabsState.activeTabId);
        if (activeTab && !resp.binary && typeof resp.content === 'string') {
            activeTab.content = restoredContent !== null ? restoredContent : resp.content;
        }
        if (activeTab && (activeTab.photoEditor || activeTab.latexEditor || 
                          activeTab.transcriptionEditor || activeTab.onlyOfficeEditor)) {
            return;
        }
        const tabId = tabsState.activeTabId;
        const loadToken = (tabsState.openFileTokens.get(tabId) || 0) + 1;
        tabsState.openFileTokens.set(tabId, loadToken);
        const samePathKey = tabId + ':' + path + ':' + (rootKey || 'main');
        if (typeof hideAllTabPanels === 'function') {
            hideAllTabPanels(tabId);
        } else {
            viewer.querySelectorAll('.file-content-container').forEach((container) => {
                container.style.display = container.getAttribute('data-tab-id') === String(tabId) ? 'block' : 'none';
            });
            viewer.querySelectorAll('iframe[data-tab-id]').forEach((iframe) => {
                iframe.style.display = iframe.getAttribute('data-tab-id') === String(tabId) ? 'block' : 'none';
            });
        }
        let fileContainer = typeof getOrCreateFileContainer === 'function'
            ? getOrCreateFileContainer(tabId)
            : viewer.querySelector('.file-content-container[data-tab-id="' + tabId + '"]');
        if (!fileContainer) {
            fileContainer = document.createElement('div');
            fileContainer.className = 'file-content-container';
            fileContainer.setAttribute('data-tab-id', String(tabId));
            fileContainer.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;display:block;z-index:5;';
            viewer.appendChild(fileContainer);
        }
        fileContainer.style.display = 'block';
        const existingContent = fileContainer.querySelector('iframe, img, video, audio, canvas');
        if (existingContent && fileContainer.dataset.loadedPath === path) {
            return;
        }
        if (existingContent && fileContainer.dataset.loadingPath === path) {
            return;
        }
        fileContainer.dataset.loadingPath = path;
        delete fileContainer.dataset.loadedPath;
        fileContainer.innerHTML = '';
        const isStaleLoad = () => tabsState.openFileTokens.get(tabId) !== loadToken;
        viewer.style.cssText = `
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
        `;
        if ((fileExt === 'tex' || fileExt === 'latex') && editMode) {
            openLatexEditor(path);
            return;
        }
        else if (resp.mime.startsWith('image/') || 
        path.toLowerCase().endsWith('.tiff') || 
        path.toLowerCase().endsWith('.tif') ||
        ['jpg','jpeg','png','webp','bmp','gif','tiff','tif'].includes(fileExt)) {
    
            const container = document.createElement('div');
            container.className = 'image-viewer-container';
            container.style.cssText = `
                width: 100%; height: 100%; overflow: hidden;
                position: relative; background: var(--panel);
            `;
            
            const iframe = document.createElement('iframe');
            iframe.style.cssText = `width: 100%; height: 100%; border: none; background: var(--panel);`;
            iframe.setAttribute('data-tab-id', tabsState.activeTabId);

            // Get all images from the same folder
            const folderPath = path.substring(0, path.lastIndexOf('/'));
            const imagesInFolder = await getImagesInFolder(folderPath, rootKey);
            if (isStaleLoad()) return;

            const urlParams = new URLSearchParams(window.location.search);
            const lang = urlParams.get('lang') || 'en';
            const theme = document.body.getAttribute('data-theme') || 'light';
            const color = urlParams.get('color') || '2385b503';
            
            const currentUser = currentUserState.user || 'default';
            const root = rootKey;
            const fullPath = buildEditorStoragePath(currentUser, path, root);
            const imageViewerUrl = `utilities/projects/image-viewer/public/index.php?path=${encodeURIComponent(fullPath)}&theme=${theme}&user=${encodeURIComponent(currentUser)}&lang=${lang}&color=${encodeURIComponent(color)}&root=${root}&folder=${encodeURIComponent(folderPath)}&images=${encodeURIComponent(JSON.stringify(imagesInFolder))}`;
            
            iframe.src = imageViewerUrl;
            
            const themeObserver = new MutationObserver(() => {
                if (iframe.contentWindow) {
                    iframe.contentWindow.postMessage({
                        type: 'THEME_UPDATE',
                        theme: document.body.getAttribute('data-theme') || 'light'
                    }, '*');
                }
            });
            themeObserver.observe(document.body, { attributes: true, attributeFilter: ['data-theme'] });
            
            container.appendChild(iframe);
            fileContainer.appendChild(container);
            finalizeTabFileLoad(fileContainer, path, tabId, loadToken);
            
            const activeTab = tabsState.tabs.find(tab => tab.id === tabsState.activeTabId);
            if (activeTab) {
                activeTab.isImage = true;
                activeTab.title = path.split('/').pop();
                renderTabs();
            }
            return;
        }
        // Office files always open in OnlyOffice edit mode regardless of editMode
        if (isOfficeFile(filePath)) {
            openOnlyOfficeEditor(filePath);
            return;
        }
        else if (resp.preview && resp.previewType === 'video') {
            
            // Create a container for the video
            const container = document.createElement('div');
            container.className = 'video-player-container';
            container.style.cssText = `
                width: 100%;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #000;
                position: relative;
                min-width: 0;
                overflow: hidden;
            `;
            
            // Create the video element
            const video = document.createElement('video');
            video.src = `api.php?action=raw_video&path=${encodeURIComponent(path)}&root=${rootKey}`;
            video.controls = true;
            video.style.maxWidth = '100%';
            video.style.maxHeight = '100%';
            video.style.objectFit = 'contain';
            
            // Add the plyr class
            video.className = 'js-plyr';
            
            // Create a loading indicator (numbers only)
            const loader = document.createElement('div');
            loader.className = 'video-loader';
            loader.textContent = '0%'; // Initial value
            loader.style.cssText = `
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                color: white;
                font-size: 32px;
                font-weight: bold;
                font-family: 'Courier New', monospace;
                text-shadow: 0 0 10px rgba(0,0,0,0.7);
                z-index: 10;
                background: rgba(0,0,0,0.5);
                padding: 15px 25px;
                border-radius: 50px;
                border: 2px solid rgba(255,255,255,0.3);
                backdrop-filter: blur(5px);
                transition: opacity 0.3s ease;
            `;
            
            container.appendChild(video);
            container.appendChild(loader);
            fileContainer.appendChild(container);
            
            // Load Plyr CSS if not loaded yet
            if (!document.querySelector('link[href*="plyr"]')) {
                const plyrCSS = document.createElement('link');
                plyrCSS.rel = 'stylesheet';
                plyrCSS.href = 'https://cdnjs.cloudflare.com/ajax/libs/plyr/3.7.8/plyr.min.css';
                document.head.appendChild(plyrCSS);
            }
            
            const resolvePlyrCtor = () => {
                const g = typeof globalThis !== 'undefined' ? globalThis : window;
                if (g && typeof g.Plyr === 'function') return g.Plyr;
                if (typeof window !== 'undefined' && typeof window.Plyr === 'function') return window.Plyr;
                return null;
            };

            const loadPlyrScript = (sources) => new Promise((resolve, reject) => {
                const trySource = (idx) => {
                    if (idx >= sources.length) {
                        reject(new Error('Plyr script is unavailable on all configured CDNs'));
                        return;
                    }

                    const src = sources[idx];
                    const existing = document.querySelector(`script[src="${src}"]`);
                    if (existing) {
                        if (resolvePlyrCtor()) {
                            resolve();
                            return;
                        }
                        existing.addEventListener('load', () => {
                            if (resolvePlyrCtor()) resolve();
                            else trySource(idx + 1);
                        }, { once: true });
                        existing.addEventListener('error', () => trySource(idx + 1), { once: true });
                        return;
                    }

                    const script = document.createElement('script');
                    script.src = src;
                    script.onload = () => {
                        if (resolvePlyrCtor()) resolve();
                        else trySource(idx + 1);
                    };
                    script.onerror = () => trySource(idx + 1);
                    document.head.appendChild(script);
                };

                trySource(0);
            });

            const plyrCtor = resolvePlyrCtor();
            if (plyrCtor) {
                initPlyrWithLoader(video, loader, plyrCtor);
            } else {
                loadPlyrScript([
                    'https://cdnjs.cloudflare.com/ajax/libs/plyr/3.7.8/plyr.polyfilled.min.js',
                    'https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.polyfilled.min.js',
                    'https://unpkg.com/plyr@3.7.8/dist/plyr.polyfilled.min.js'
                ]).then(() => {
                    const loadedCtor = resolvePlyrCtor();
                    if (loadedCtor) {
                        initPlyrWithLoader(video, loader, loadedCtor);
                    } else {
                        console.warn('Plyr script loaded but constructor is not available, falling back to native controls');
                        loader.style.display = 'none';
                    }
                }).catch(() => {
                    console.warn('Failed to load Plyr, falling back to native controls');
                    loader.style.display = 'none';
                });
            }
            
            // Function to initialize the player with loading tracking
            function initPlyrWithLoader(videoElement, loaderElement, PlyrCtor) {
                // Track loading progress
                let lastProgress = 0;
                
                const updateLoader = () => {
                    if (videoElement.buffered.length > 0) {
                        const bufferedEnd = videoElement.buffered.end(videoElement.buffered.length - 1);
                        const duration = videoElement.duration || 1;
                        const progress = Math.min(100, Math.round((bufferedEnd / duration) * 100));
                        
                        if (progress > lastProgress) {
                            lastProgress = progress;
                            loaderElement.textContent = progress + '%';
                        }
                        
                        // If the video is fully loaded or enough for smooth playback
                        if (progress >= 95 || (progress > 30 && videoElement.readyState >= 3)) {
                            loaderElement.style.opacity = '0';
                            setTimeout(() => {
                                if (loaderElement.parentNode) {
                                    loaderElement.style.display = 'none';
                                }
                            }, 500);
                        }
                    }
                };
                
                // Update progress every 200ms
                const interval = setInterval(updateLoader, 200);
                
                // Create the Plyr player
                const player = new PlyrCtor(videoElement, {
                    controls: [
                        'play-large', 'play', 'progress', 'current-time',
                        'mute', 'volume', 'captions', 'settings', 'pip', 'airplay', 'fullscreen'
                    ],
                    settings: ['captions', 'quality', 'speed'],
                    i18n: {
                        restart: t(i18n, 'restart') || 'Restart',
                        rewind: t(i18n, 'rewind') || 'Rewind {seektime}s',
                        play: t(i18n, 'play') || 'Play',
                        pause: t(i18n, 'pause') || 'Pause',
                        fastForward: t(i18n, 'fastForward') || 'Forward {seektime}s',
                        seek: t(i18n, 'seek') || 'Seek',
                        seekLabel: '{currentTime} of {duration}',
                        played: t(i18n, 'played') || 'Played',
                        buffered: t(i18n, 'buffered') || 'Buffered',
                        currentTime: t(i18n, 'currentTime') || 'Current time',
                        duration: t(i18n, 'duration') || 'Duration',
                        volume: t(i18n, 'volume') || 'Volume',
                        mute: t(i18n, 'mute') || 'Mute',
                        unmute: t(i18n, 'unmute') || 'Unmute',
                        enableCaptions: t(i18n, 'enableCaptions') || 'Enable captions',
                        disableCaptions: t(i18n, 'disableCaptions') || 'Disable captions',
                        download: t(i18n, 'download') || 'Download',
                        enterFullscreen: t(i18n, 'enterFullscreen') || 'Enter fullscreen',
                        exitFullscreen: t(i18n, 'exitFullscreen') || 'Exit fullscreen',
                        frameTitle: t(i18n, 'frameTitle') || 'Player for {title}',
                        captions: t(i18n, 'captions') || 'Captions',
                        settings: t(i18n, 'settings') || 'Settings',
                        menuBack: t(i18n, 'menuBack') || 'Go back to previous menu',
                        speed: t(i18n, 'speed') || 'Speed',
                        normal: t(i18n, 'normal') || 'Normal',
                        quality: t(i18n, 'quality') || 'Quality',
                        loop: t(i18n, 'loop') || 'Loop'
                    }
                });
                
                // When the video is ready to play, hide the loader
                videoElement.addEventListener('canplay', () => {
                    setTimeout(() => {
                        loaderElement.style.opacity = '0';
                        setTimeout(() => {
                            if (loaderElement.parentNode) {
                                loaderElement.style.display = 'none';
                            }
                            clearInterval(interval);
                        }, 500);
                    }, 1000);
                });
                
                // If the video is already loaded
                if (videoElement.readyState >= 3) {
                    loaderElement.style.opacity = '0';
                    setTimeout(() => {
                        if (loaderElement.parentNode) {
                            loaderElement.style.display = 'none';
                        }
                        clearInterval(interval);
                    }, 500);
                }
                
                // Clear the interval on tab switch or close
                videoElement.addEventListener('destroy', () => {
                    clearInterval(interval);
                });
            }
        }
        else if (resp.preview && resp.previewType === 'audio') {
            const audioInfo = await getAudioFileInfo(path, rootKey);
            const container = document.createElement('div');
            container.className = 'audio-player-container';
            container.style.cssText = `
                width: 100%;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: var(--panel);
            `;
            const playerWrapper = document.createElement('div');
            playerWrapper.className = 'audio-player-wrapper';
            playerWrapper.style.cssText = `
                width: min(90%, 800px);
                max-width: 100%;
                background: var(--bg);
                border-radius: 12px;
                padding: 20px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.15);
                box-sizing: border-box;
            `;
            const infoDiv = document.createElement('div');
            infoDiv.className = 'audio-info';
            infoDiv.innerHTML = `
                <div class="audio-info-row">
                    <div class="audio-info-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="white">
                            <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
                        </svg>
                    </div>
                    <div class="audio-info-text">
                        <h3 class="audio-file-title">${path.split('/').pop()}</h3>
                        <p class="audio-file-info">
                            ${formatBytes(audioInfo.size || resp.size || 0)} • ${audioInfo.duration ? formatTime(audioInfo.duration) : 'Loading duration...'}
                        </p>
                    </div>
                </div>
            `;
            const playerDiv = document.createElement('div');
            playerDiv.className = 'howler-player';
            playerDiv.dataset.audioUrl = `api.php?action=raw_audio&path=${encodeURIComponent(path)}&root=${rootKey}`;
            playerDiv.dataset.audioPath = path;
            playerDiv.dataset.filename = path.split('/').pop();
            const progressContainer = document.createElement('div');
            progressContainer.style.cssText = `
                margin: 20px 0;
                position: relative;
            `;
            const progressBackground = document.createElement('div');
            progressBackground.style.cssText = `
                width: 100%;
                height: 6px;
                background: var(--border);
                border-radius: 3px;
                position: relative;
                cursor: pointer;
            `;
            const progressBar = document.createElement('div');
            progressBar.className = 'audio-progress-bar';
            progressBar.style.cssText = `
                width: 0%;
                height: 100%;
                background: var(--accent);
                border-radius: 3px;
                transition: width 0.1s linear;
            `;
            const progressHandle = document.createElement('div');
            progressHandle.className = 'audio-progress-handle';
            progressHandle.style.cssText = `
                width: 16px;
                height: 16px;
                background: var(--accent);
                border-radius: 50%;
                position: absolute;
                top: 50%;
                transform: translate(-50%, -50%);
                left: 0%;
                cursor: pointer;
                box-shadow: 0 0 8px rgba(0,0,0,0.3);
                display: none;
            `;
            progressBackground.appendChild(progressBar);
            progressBackground.appendChild(progressHandle);
            progressContainer.appendChild(progressBackground);
            const timeDisplay = document.createElement('div');
            timeDisplay.style.cssText = `
                display: flex;
                justify-content: space-between;
                color: var(--muted);
                font-size: 12px;
                margin-top: 5px;
            `;
            timeDisplay.innerHTML = `
                <span class="current-time">0:00</span>
                <span class="duration">${resp.duration ? formatTime(resp.duration) : '0:00'}</span>
            `;
            const controlsDiv = document.createElement('div');
            controlsDiv.className = 'audio-player-controls';
            controlsDiv.style.cssText = `
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 25px;
                margin-top: 20px;
                flex-wrap: wrap;
                max-width: 100%;
            `;
            const rewindBtn = document.createElement('button');
            rewindBtn.className = 'audio-control-btn rewind';
            rewindBtn.title = 'Rewind 10 seconds';
            rewindBtn.innerHTML = `
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M17 7L12 12L17 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M11 7L6 12L11 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            `;
            rewindBtn.style.cssText = `
                background: var(--panel);
                border: 2px solid var(--border);
                color: var(--fg);
                cursor: pointer;
                width: 48px;
                height: 48px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s;
            `;
            const playPauseBtn = document.createElement('button');
            playPauseBtn.className = 'audio-control-btn play-pause';
            playPauseBtn.innerHTML = `
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 5V19L19 12L8 5Z" fill="currentColor"/>
                </svg>
            `;
            playPauseBtn.style.cssText = `
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: var(--accent);
                color: white;
                border: none;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: transform 0.2s;
            `;
            const forwardBtn = document.createElement('button');
            forwardBtn.className = 'audio-control-btn forward';
            forwardBtn.title = 'Forward 10 seconds';
            forwardBtn.innerHTML = `
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M7 7L12 12L7 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M13 7L18 12L13 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            `;
            forwardBtn.style.cssText = `
                background: var(--panel);
                border: 2px solid var(--border);
                color: var(--fg);
                cursor: pointer;
                width: 48px;
                height: 48px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s;
            `;
            const volumeDiv = document.createElement('div');
            volumeDiv.className = 'volume-control-container';
            volumeDiv.style.cssText = `
                display: flex;
                align-items: center;
                gap: 8px;
                margin-top: 20px;
                padding: 8px;
                background: var(--panel);
                border-radius: 8px;
                min-width: 0;
                max-width: 100%;
                flex-wrap: nowrap;
            `;
            const volumeIcon = document.createElement('div');
            volumeIcon.innerHTML = `
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 9V15H7L12 20V4L7 9H3Z" fill="currentColor"/>
                </svg>
            `;
            const volumeSlider = document.createElement('input');
            volumeSlider.type = 'range';
            volumeSlider.min = '0';
            volumeSlider.max = '100';
            volumeSlider.value = '80';
            volumeSlider.className = 'volume-slider';
            volumeSlider.style.cssText = `
                flex: 1;
                min-width: 0;
                max-width: 100%;
                height: 4px;
                background: var(--border);
                border-radius: 2px;
                outline: none;
                -webkit-appearance: none;
                margin: 0;
            `;
            volumeDiv.appendChild(volumeIcon);
            volumeDiv.appendChild(volumeSlider);
            playerDiv.appendChild(infoDiv);
            playerDiv.appendChild(progressContainer);
            playerDiv.appendChild(timeDisplay);
            playerDiv.appendChild(controlsDiv);
            playerDiv.appendChild(volumeDiv);
            controlsDiv.appendChild(rewindBtn);
            controlsDiv.appendChild(playPauseBtn);
            controlsDiv.appendChild(forwardBtn);
            playerWrapper.appendChild(playerDiv);
            container.appendChild(playerWrapper);
            fileContainer.appendChild(container);
            setTimeout(() => {
                initHowlerPlayer(playerDiv);
                if (typeof syncViewerLayout === 'function') syncViewerLayout();
            }, 100);
        }
        else if (resp.preview && resp.previewType === '3d') {
            const iframe = document.createElement('iframe');
            const urlParams = new URLSearchParams(window.location.search);
            const theme = document.body.getAttribute('data-theme') || 'dark';
            const accentColor = urlParams.get('color') || window.APP_CONFIG.color || '#85b503';
            const lang = window.APP_CONFIG.lang || urlParams.get('lang') || 'en';
            const sceneColor = theme === 'light' ? '#f4f6f8' : '#525150';
            const partColor = theme === 'light' ? '#2e2e2d' : '#dbe2ea';
            iframe.src = `3mf-viewer.php?file=${encodeURIComponent(path)}&sceneColor=${encodeURIComponent(sceneColor)}&partColor=${encodeURIComponent(partColor)}&accentColor=${encodeURIComponent(accentColor)}&theme=${encodeURIComponent(theme)}&lang=${encodeURIComponent(lang)}&root=${rootKey}`;
            iframe.style.cssText = `
                width: 100%;
                height: 100%;
                border: none;
                background: ${sceneColor};
            `;
            iframe.setAttribute('allow', 'fullscreen');
            iframe.setAttribute('webkitallowfullscreen', 'true');
            iframe.setAttribute('mozallowfullscreen', 'true');
            iframe.setAttribute('frameborder', '0');
            iframe.setAttribute('data-tab-id', tabsState.activeTabId);
            fileContainer.appendChild(iframe);
        }
        else if (path.toLowerCase().endsWith('.3mf')) {
            const iframe = document.createElement('iframe');
            const urlParams = new URLSearchParams(window.location.search);
            const theme = document.body.getAttribute('data-theme') || 'dark';
            const accentColor = urlParams.get('color') || window.APP_CONFIG.color || '#85b503';
            const lang = window.APP_CONFIG.lang || urlParams.get('lang') || 'en';
            const sceneColor = theme === 'light' ? '#f4f6f8' : '#525150';
            const partColor = theme === 'light' ? '#2e2e2d' : '#dbe2ea';
            iframe.src = `3mf-viewer.php?file=${encodeURIComponent(path)}&sceneColor=${encodeURIComponent(sceneColor)}&partColor=${encodeURIComponent(partColor)}&accentColor=${encodeURIComponent(accentColor)}&theme=${encodeURIComponent(theme)}&lang=${encodeURIComponent(lang)}&root=${rootKey}`;
            iframe.style.cssText = `
                width: 100%;
                height: 100%;
                border: none;
                background: ${sceneColor};
            `;
            iframe.setAttribute('allow', 'fullscreen');
            iframe.setAttribute('webkitallowfullscreen', 'true');
            iframe.setAttribute('mozallowfullscreen', 'true');
            iframe.setAttribute('frameborder', '0');
            iframe.setAttribute('data-tab-id', tabsState.activeTabId);
            fileContainer.appendChild(iframe);
        }
        else if (resp.preview && resp.previewType === 'pdf') {
            const urlParams = new URLSearchParams(window.location.search);
            const theme = document.body.getAttribute('data-theme') || 'dark';
            const color = urlParams.get('color') || window.APP_CONFIG.color || '#85b503';
            const lang = urlParams.get('lang') || 'en';
            const iframe = document.createElement('iframe');
            iframe.src = `api.php?action=view_pdf&path=${encodeURIComponent(path)}&theme=${encodeURIComponent(theme)}&color=${encodeURIComponent(color.replace('#', ''))}&lang=${lang}&root=${rootKey}`;
            iframe.style.width = '100%';
            iframe.style.height = '100%';
            iframe.style.border = 'none';
            iframe.style.background = 'white';
            if (isMobileDevice()) {
                iframe.style.minHeight = '500px';
                iframe.setAttribute('scrolling', 'yes');
            }
            iframe.setAttribute('allow', 'fullscreen');
            iframe.setAttribute('webkitallowfullscreen', 'true');
            iframe.setAttribute('mozallowfullscreen', 'true');
            iframe.setAttribute('data-tab-id', tabsState.activeTabId);
            fileContainer.appendChild(iframe);
        } 
        else if (resp.preview) {
            const iframe = document.createElement('iframe');
            iframe.className = 'preview-iframe';
            iframe.style.width = '100%';
            iframe.style.height = '100%';
            iframe.style.border = 'none';
            iframe.style.background = 'var(--bg)';
            iframe.scrolling = 'no';
            iframe.style.overflow = 'hidden';
            let endpoint = '';
            const urlParams = new URLSearchParams(window.location.search);
            const theme = document.body.getAttribute('data-theme') || 'dark';
            const color = urlParams.get('color') || window.APP_CONFIG.color || '#85b503';
            const lang = urlParams.get('lang') || 'en';
            const root = rootKey;
            switch(resp.previewType) {
                case 'pdf':
                    endpoint = `api.php?action=view_pdf&path=${encodeURIComponent(path)}&theme=${encodeURIComponent(theme)}&color=${encodeURIComponent(color.replace('#', ''))}&lang=${lang}&root=${root}`;
                    break;
                case 'word':
                    endpoint = `api.php?action=view_word&path=${encodeURIComponent(path)}&theme=${encodeURIComponent(theme)}&color=${encodeURIComponent(color.replace('#', ''))}&lang=${lang}&root=${root}`;
                    break;
                case 'excel':
                    endpoint = `api.php?action=view_excel&path=${encodeURIComponent(path)}&theme=${encodeURIComponent(theme)}&color=${encodeURIComponent(color.replace('#', ''))}&lang=${lang}&root=${root}`;
                    break;
                case 'presentation':
                    endpoint = `api.php?action=view_presentation&path=${encodeURIComponent(path)}&theme=${encodeURIComponent(theme)}&color=${encodeURIComponent(color.replace('#', ''))}&lang=${lang}&root=${root}`;
                    break;
                default:
                    endpoint = `api.php?action=raw&path=${encodeURIComponent(path)}&root=${root}`;
            }
            iframe.src = endpoint;
            iframe.setAttribute('data-tab-id', tabsState.activeTabId);
            fileContainer.appendChild(iframe);
            const scrollbarStyle = document.createElement('style');
            const loadingOverlay = document.createElement('div');
            loadingOverlay.className = 'iframe-loading';
            const currentLang = window.APP_CONFIG.lang || 'en';
            const loadingTexts = {
                'en': {
                    'loading': 'Loading',
                    'document': 'document',
                    'spreadsheet': 'spreadsheet',
                    'error': 'File loading error'
                },
                'ru': {
                    'loading': 'Загрузка',
                    'document': 'документа',
                    'spreadsheet': 'таблицы',
                    'error': 'Ошибка загрузки файла'
                },
                'uk': {
                    'loading': 'Завантаження',
                    'document': 'документа',
                    'spreadsheet': 'таблиці',
                    'error': 'Помилка завантаження файлу'
                },
                'be': {
                    'loading': 'Загрузка',
                    'document': 'дакумента',
                    'spreadsheet': 'табліцы',
                    'error': 'Памылка загрузкі файла'
                }
            };
            const texts = loadingTexts[currentLang] || loadingTexts['en'];
            const fileTypeText = resp.previewType === 'excel' ? texts.spreadsheet : texts.document;
            loadingOverlay.innerHTML = `
                <div style="
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    text-align: center;
                    z-index: 10;
                ">
                    <div class="spinner" style="
                        width: 40px;
                        height: 40px;
                        margin: 0 auto 15px;
                        animation: spin 1s linear infinite;
                    ">
                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="20" cy="20" r="18" stroke="var(--border)" stroke-width="4" opacity="0.3"/>
                            <path d="M20 2a18 18 0 0 1 18 18" stroke="var(--accent)" stroke-width="4" stroke-linecap="round" opacity="0.8"/>
                        </svg>
                    </div>
                    <div style="color: var(--muted);">${texts.loading} ${fileTypeText}...</div>
                </div>
            `;
            loadingOverlay.style.cssText = `
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: var(--bg);
                z-index: 5;
                display: flex;
                align-items: center;
                justify-content: center;
            `;
            scrollbarStyle.textContent = `
                .preview-iframe {
                    scrollbar-width: thin;
                    scrollbar-color: var(--scrollbar-thumb, #c1c1c1) var(--scrollbar-track, #f1f1f1);
                }
                .preview-iframe::-webkit-scrollbar {
                    width: 8px;
                    height: 8px;
                }
                .preview-iframe::-webkit-scrollbar-track {
                    background: var(--scrollbar-track, #f1f1f1);
                    border-radius: 4px;
                }
                .preview-iframe::-webkit-scrollbar-thumb {
                    background: var(--scrollbar-thumb, #c1c1c1);
                    border-radius: 4px;
                    border: 2px solid var(--scrollbar-track, #f1f1f1);
                }
                .preview-iframe::-webkit-scrollbar-thumb:hover {
                    background: var(--scrollbar-thumb-hover, #a8a8a8);
                }
                .preview-iframe[src*="action=view_excel"] {
                    overflow: auto !important;
                }
                .preview-iframe[src*="action=view_excel"]::-webkit-scrollbar {
                    width: 8px;
                    height: 8px;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            `;
            iframe.onload = function() {
                loadingOverlay.style.display = 'none';
            };
            iframe.onerror = function() {
                loadingOverlay.innerHTML = `
                    <div style="
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        text-align: center;
                        color: var(--muted);
                    ">
                        <div style="font-size: 48px; margin-bottom: 15px;">❌</div>
                        <div>${texts.error}</div>
                    </div>
                `;
            };
            fileContainer.appendChild(loadingOverlay);
            document.head.appendChild(scrollbarStyle);
        } 
        else if (!resp.binary && editMode) {
            
            const tabId = tabsState.activeTabId;
            const editorContainer = document.createElement('div');
            editorContainer.style.cssText = `
                width: 100%;
                height: 100%;
                position: absolute;
                top: 0;
                left: 0;
                border: none;
                overflow: hidden;
            `;
            editorContainer.setAttribute('data-tab-id', tabId);
            editorContainer.className = 'monaco-editor-container';
            fileContainer.appendChild(editorContainer);
            
            const content = restoredContent !== null ? restoredContent : resp.content;
            
            // Use the Monaco Editor
            setTimeout(async () => {
                try {
                    const editor = await window.monacoManager.createEditor(
                        editorContainer, 
                        content, 
                        path, 
                        tabId
                    );
                    
                    // Save the editor reference
                    editorsMap.set(tabId, editor);
                    initMonacoAutoSave(editor, path, tabId);
                    if (typeof isMonacoCollabEligiblePath === 'function' && isMonacoCollabEligiblePath(path)) {
                        startMonacoCollabSession(tabId, path, getResolvedTabRoot(tabsState.tabs.find(t => t.id === tabId)), content, resp.revision || '');
                    }
                    
                    if (tabsState.activeTabId === tabId) {
                        viewer.dataset.editor = 'monaco';
                        viewer.monacoEditor = editor;
                    }
                    
                    editor.focus();
                    
                } catch (err) {
                    console.error('Failed to create Monaco editor:', err);
                }
            }, 10);
        }
        else if (isZipFile(path)) {
            
            let currentUser = currentUserState.user || 'default';
            currentUser = currentUser.replace(/\s+/g, '_');
            const activeTab = tabsState.tabs.find(tab => tab.id === tabsState.activeTabId);
            const root = getTabRoot(activeTab);
            const fullZipPath = buildEditorStoragePath(currentUser, path, root);
            if (activeTab) {
                activeTab.zipViewer = true;
                activeTab.fullPath = fullZipPath; // CRITICALLY IMPORTANT!
            }
            const container = document.createElement('div');
            container.style.cssText = `
                width: 100%;
                height: 100%;
                position: relative;
                background: var(--bg);
                overflow: hidden;
            `;
            const iframe = document.createElement('iframe');
            iframe.setAttribute('data-tab-id', tabsState.activeTabId);
            iframe.dataset.archivePath = fullZipPath; // Save to the data attribute
            const urlParams = new URLSearchParams(window.location.search);
            const lang = urlParams.get('lang') || 'en';
            const theme = document.body.getAttribute('data-theme') || 'dark';
            const folderColor = window.APP_CONFIG.color || '#85b503';
            const zipPath = encodeURIComponent(fullZipPath);
            const zipViewerUrl = `utilities/projects/zip-viewer/public/index.php?archive=${zipPath}&path=${encodeURIComponent(path)}&user=${encodeURIComponent(currentUser)}&root=${encodeURIComponent(root)}&lang=${lang}&theme=${theme}&folderColor=${encodeURIComponent(folderColor)}`;
            iframe.src = zipViewerUrl;
            iframe.style.cssText = `
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                border: none;
                background: var(--bg);
            `;
            iframe.setAttribute('allow', 'fullscreen');
            iframe.setAttribute('webkitallowfullscreen', 'true');
            iframe.setAttribute('mozallowfullscreen', 'true');
            iframe.onerror = function() {
                console.error('Failed to load ZIP viewer');
                const errorDiv = document.createElement('div');
                errorDiv.style.cssText = `
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    color: var(--muted);
                    text-align: center;
                    z-index: 100;
                `;
                errorDiv.innerHTML = `
                    <i class="fa-solid fa-circle-exclamation" style="font-size: 48px; margin-bottom: 16px; color: #e74c3c;"></i>
                    <p>Failed to load archive. Please try again.</p>
                    <button onclick="location.reload()" class="btn primary" style="margin-top: 16px;">Reload</button>
                `;
                container.appendChild(errorDiv);
            };
            container.appendChild(iframe);
            fileContainer.appendChild(container);
            saveTabsState();
        }
        else if (!resp.binary) {
            if (isHtmlFile(path)) {
                renderHtmlFilePreview(fileContainer, path, tabsState.activeTabId);
                viewer.dataset.editor = '';
                viewer.monacoEditor = null;
            } else {
                const editorContainer = document.createElement('div');
                editorContainer.style.cssText = `
                    width: 100%;
                    height: 100%;
                    border: 1px solid var(--border);
                `;
                editorContainer.setAttribute('data-tab-id', tabsState.activeTabId);
                fileContainer.appendChild(editorContainer);
                const content = restoredContent !== null ? restoredContent : resp.content;
                
                // Use Monaco editor in read-only mode
                if (window.monacoManager) {
                    const editor = await window.monacoManager.createEditor(editorContainer, content, path, tabsState.activeTabId, true);
                    editorsMap.set(tabsState.activeTabId, editor);
                    viewer.dataset.editor = 'monaco';
                } else {
                    console.error('Monaco manager not available');
                    flashStatus(t(i18n, 'editorLoadError'));
                }
            }
        } 
        else {
            const container = document.createElement('div');
            container.style.padding = '20px';
            container.style.textAlign = 'center';
            container.style.height = '100%';
            container.style.display = 'flex';
            container.style.flexDirection = 'column';
            container.style.alignItems = 'center';
            container.style.justifyContent = 'center';
            const icon = document.createElement('div');
            icon.style.fontSize = '48px';
            icon.style.marginBottom = '16px';
            icon.innerHTML = getFileIconMarkup(path, 'app-file-icon-xl');
            const title = document.createElement('h3');
            title.textContent = t(i18n, 'binaryFile');
            title.style.marginBottom = '8px';
            let fileSize = '0 B';
            try {
                const fileInfo = await api('file_info', { path: path }, 'GET');
                fileSize = formatBytes(fileInfo.size || 0);
            } catch (err) {
                console.error('Failed to get file size:', err);
                fileSize = formatBytes(resp.size || 0);
            }
            const fileInfoText = document.createElement('p');
            fileInfoText.textContent = `${path.split('/').pop()} (${fileSize})`;
            fileInfoText.style.color = 'var(--muted)';
            fileInfoText.style.marginBottom = '20px';
            const downloadBtn = document.createElement('button');
            downloadBtn.className = 'btn primary';
            downloadBtn.textContent = t(i18n, 'download');
            downloadBtn.onclick = () => downloadPath(path);
            container.appendChild(icon);
            container.appendChild(title);
            container.appendChild(fileInfoText);
            container.appendChild(downloadBtn);
            fileContainer.appendChild(container);
        } 
        finalizeTabFileLoad(fileContainer, path, tabId, loadToken);
        if (typeof syncViewerLayout === 'function') syncViewerLayout();
        } catch (err) {
        console.error('Error opening file:', err);
        
        // Create a container for the error message
        const viewer = document.getElementById('viewer');
        const fileName = document.getElementById('file-name');
        
        // Show that the file could not be loaded
        document.getElementById('file-view').classList.remove('hidden');
        fileName.textContent = path.split('/').pop();
        viewer.dataset.path = path;
        viewer.dataset.editMode = 'false';
        
        // Hide the edit buttons
        const saveBtn = document.getElementById('btn-save');
        const codeActionsBtn = document.getElementById('btn-code-actions');
        saveBtn.classList.add('hidden');
        codeActionsBtn.classList.add('hidden');
        
        // Hide compile button
        const compileMenuItem = document.getElementById('code-action-compile');
        if (compileMenuItem) {
            compileMenuItem.style.display = 'none';
        }
        
        const errTabId = tabsState.activeTabId;
        const errContainer = typeof getOrCreateFileContainer === 'function'
            ? getOrCreateFileContainer(errTabId)
            : null;
        if (errContainer) {
            errContainer.innerHTML = '';
            errContainer.style.display = 'block';
            if (typeof hideAllTabPanels === 'function') hideAllTabPanels(errTabId);
        } else {
            viewer.innerHTML = '';
        }
        
        // Create a container for the error message
        const errorContainer = document.createElement('div');
        errorContainer.style.cssText = `
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg);
            position: relative;
        `;
        
        // Determine the error type
        let errorMessage = '';
        let errorSvg = '';

        if (err.message.includes('404')) {
            errorMessage = t(i18n, 'fileNotFoundError');
            errorSvg = `
                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#f1c40f" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8" stroke="#f1c40f"/>
                    <path d="m21 21-4.3-4.3" stroke="#f1c40f"/>
                    <path d="m13.5 8.5-5 5" stroke="#f1c40f"/>
                    <path d="m8.5 8.5 5 5" stroke="#f1c40f"/>
                </svg>
            `;
        } else if (err.message.includes('403')) {
            errorMessage = t(i18n, 'fileAccessDeniedError');
            errorSvg = `
                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#f1c40f" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke="#f1c40f"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" stroke="#f1c40f"/>
                    <circle cx="12" cy="16" r="1" fill="#f1c40f"/>
                </svg>
            `;
        } else if (err.message.includes('500')) {
            errorMessage = t(i18n, 'serverError');
            errorSvg = `
                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#f1c40f" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 7v6" stroke="#f1c40f"/>
                    <path d="M12 17h.01" stroke="#f1c40f"/>
                    <circle cx="12" cy="12" r="10" stroke="#f1c40f"/>
                </svg>
            `;
        } else {
            errorMessage = t(i18n, 'openFileError') + ': ' + err.message;
            errorSvg = `
                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#e74c3c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" stroke="#e74c3c"/>
                    <line x1="12" y1="8" x2="12" y2="12" stroke="#e74c3c"/>
                    <line x1="12" y1="16" x2="12.01" y2="16" stroke="#e74c3c"/>
                </svg>
            `;
        }
        
        // Create the error content
        errorContainer.innerHTML = `
            <div style="
                max-width: 500px;
                padding: 30px;
                text-align: center;
                background: var(--panel);
                border: 2px solid #f1c40f;
                border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            ">
                <div style="font-size: 64px; margin-bottom: 20px; line-height: 1;">
                    ${errorSvg}
                </div>
                <h3 style="
                    margin: 0 0 10px 0;
                    color: #f1c40f;
                    font-size: 20px;
                ">
                    ⚠️ ${t(i18n, 'fileLoadError')}
                </h3>
                <div style="
                    margin-bottom: 20px;
                    color: var(--fg);
                    font-size: 16px;
                    padding: 10px;
                    background: var(--bg);
                    border-radius: 6px;
                    word-break: break-word;
                ">
                    ${errorMessage}
                </div>
                <div style="
                    display: flex;
                    gap: 10px;
                    justify-content: center;
                ">
                    <button class="btn secondary" onclick="location.reload()">
                        <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" style="vertical-align: middle; margin-right: 6px;">
                            <path d="M23 4v6h-6"></path>
                            <path d="M1 20v-6h6"></path>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"></path>
                            <path d="M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                        </svg>
                        ${t(i18n, 'refreshPage')}
                    </button>
                    <button class="btn primary" onclick="buildTree('')" style="display: inline-flex; align-items: center; justify-content: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px; display: block;">
                            <path d="M20 10a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1h-2.5a1 1 0 0 1-.8-.4l-.9-1.2A1 1 0 0 0 15 3h-2a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1Z"/>
                            <path d="M20 21a1 1 0 0 0 1-1v-3a1 1 0 0 0-1-1h-2.9a1 1 0 0 1-.88-.55l-.42-.85a1 1 0 0 0-.92-.6H13a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1Z"/>
                            <path d="M3 5a2 2 0 0 0 2 2h3"/>
                            <path d="M3 3v13a2 2 0 0 0 2 2h3"/>
                        </svg>
                        ${t(i18n, 'refreshTree')}
                    </button>
                </div>
            </div>
        `;
        
        (errContainer || viewer).appendChild(errorContainer);
        
        flashStatus(t(i18n, 'openFileError') + err.message);
        
        // Update the tab state if it exists
        const activeTab = tabsState.tabs.find(tab => tab.id === tabsState.activeTabId);
        if (activeTab) {
            activeTab.content = null;
            activeTab.error = true;
            saveTabsState();
        }
    }
}
