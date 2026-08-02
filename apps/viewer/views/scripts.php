    <script>
        const translations = <?php echo json_encode($viewerI18n, JSON_UNESCAPED_UNICODE); ?>;

        let config = { 
            theme: '<?php echo $theme; ?>', 
            language: '<?php echo $lang; ?>',
            sites: [],
            token: ''
        };

        function t(key, params = {}) {
            const lang = config.language || 'en';
            const dict = translations[lang] || translations.en || {};
            let str = dict[key];
            if (str === undefined) {
                console.warn('Translation missing for key: "' + key + '"');
                str = key;
            }
            Object.keys(params).forEach(p => {
                str = str.replaceAll('{' + p + '}', params[p]);
            });
            return str;
        }

        let currentSite = null;
        let editIndex = null;
        let actionModalCallback = null;
        let userRole = <?php echo $userRole; ?>;
        
        const elements = {
            menuToggle: document.getElementById('menuToggle'),
            closeSidebar: document.getElementById('closeSidebar'),
            sidebar: document.getElementById('sidebar'),
            content: document.getElementById('content'),
            addSiteBtn: document.getElementById('addSiteBtn'),
            siteList: document.getElementById('siteList'),
            siteTableBody: document.getElementById('siteTableBody'),
            addSiteModal: document.getElementById('addSiteModal'),
            closeModal: document.querySelector('.close-modal'),
            siteName: document.getElementById('siteName'),
            siteUrl: document.getElementById('siteUrl'),
            sitePriority: document.getElementById('sitePriority'),
            saveSiteBtn: document.getElementById('saveSiteBtn'),
            modalTitle: document.getElementById('modalTitle'),
            searchBar: document.getElementById('searchBar'),
            currentSiteTitle: document.getElementById('currentSiteTitle'),
            viewerIframe: document.getElementById('viewerIframe'),
            refreshBtn: document.getElementById('refreshBtn'),
            openInModal: document.getElementById('openInModal'),
            pageTitle: document.getElementById('pageTitle'),
            headerTitle: document.getElementById('headerTitle'),
            settingsTitle: document.getElementById('settingsTitle'),
            addSiteTitle: document.getElementById('addSiteTitle'),
            sitesTitle: document.getElementById('sitesTitle'),
            siteNameHeader: document.getElementById('siteNameHeader'),
            actionsHeader: document.getElementById('actionsHeader'),
            siteNameLabel: document.getElementById('siteNameLabel'),
            siteUrlLabel: document.getElementById('siteUrlLabel'),
            sitePriorityLabel: document.getElementById('sitePriorityLabel'),
            fullscreenModal: document.getElementById('fullscreenModal'),
            fullscreenSiteTitle: document.getElementById('fullscreenSiteTitle'),
            closeFullscreenModal: document.getElementById('closeFullscreenModal'),
            fullscreenIframe: document.getElementById('fullscreenIframe'),
            actionModal: document.getElementById('actionModal'),
            actionModalTitle: document.getElementById('actionModalTitle'),
            actionModalBody: document.getElementById('actionModalBody'),
            closeActionModal: document.getElementById('closeActionModal'),
            actionModalCancel: document.getElementById('actionModalCancel'),
            actionModalConfirm: document.getElementById('actionModalConfirm'),
            useTokenCheckbox: document.getElementById('useTokenCheckbox'),
            useTokenLabel: document.getElementById('useTokenLabel'),
            tokenBadge: document.getElementById('tokenBadge'),
            manageTokenBtn: document.getElementById('manageTokenBtn'),
            tokenModal: document.getElementById('tokenModal'),
            closeTokenModal: document.getElementById('closeTokenModal'),
            tokenModalTitle: document.getElementById('tokenModalTitle'),
            currentTokenInput: document.getElementById('currentTokenInput'),
            newTokenInput: document.getElementById('newTokenInput'),
            updateTokenBtn: document.getElementById('updateTokenBtn'),
            generateTokenBtn: document.getElementById('generateTokenBtn'),
            currentTokenLabel: document.getElementById('currentTokenLabel'),
            newTokenLabel: document.getElementById('newTokenLabel'),
            tokenManagementTitle: document.getElementById('tokenManagementTitle'),
            infoToggle: document.getElementById('infoToggle'),
            infoModal: document.getElementById('infoModal'),
            infoModalTitle: document.getElementById('infoModalTitle'),
            infoModalDescription: document.getElementById('infoModalDescription'),
            closeInfoModal: document.getElementById('closeInfoModal'),
            infoModalBody: document.getElementById('infoModalBody'),
            userRoleLabel: document.getElementById('userRoleLabel')
        };

        function showActionModal(title, message, confirmCallback) {
            const lang = config.language || 'en';
            const t = translations[lang] || translations.en;
            
            elements.actionModalTitle.textContent = title;
            elements.actionModalBody.textContent = message;
            elements.actionModalConfirm.textContent = t.confirm;
            elements.actionModalCancel.textContent = t.cancel;
            
            actionModalCallback = confirmCallback;
            elements.actionModal.classList.add('active');
        }

        function updateTranslations() {
            const lang = config.language || 'en';
            const t = translations[lang] || translations.en;
            
            document.title = t.title;
            elements.pageTitle.textContent = t.title;
            elements.headerTitle.textContent = t.title;
            elements.settingsTitle.textContent = t.settings;
            elements.addSiteTitle.textContent = t.addSite;
            elements.addSiteBtn.textContent = t.addSiteBtn;
            elements.sitesTitle.textContent = t.mySites;
            elements.siteNameHeader.textContent = t.siteName;
            elements.actionsHeader.textContent = t.actions;
            elements.siteNameLabel.textContent = t.siteName;
            elements.siteUrlLabel.textContent = t.siteUrl;
            elements.sitePriorityLabel.textContent = t.sitePriority;
            elements.modalTitle.textContent = editIndex !== null ? t.editSite : t.addNewSite;
            elements.saveSiteBtn.textContent = t.saveSite;
            elements.searchBar.placeholder = t.searchPlaceholder;
            elements.refreshBtn.title = t.refreshPage;
            elements.openInModal.title = t.openInModal;
            elements.closeFullscreenModal.title = t.closeModal;
            elements.currentSiteTitle.textContent = currentSite ? currentSite.name : t.selectSite;
            elements.fullscreenSiteTitle.textContent = currentSite ? currentSite.name : t.selectSite;
            elements.useTokenLabel.textContent = t.useToken;
            elements.tokenBadge.textContent = t.tokenBadge;
            elements.tokenManagementTitle.textContent = t.tokenManagementTitle;
            elements.tokenModalTitle.textContent = t.tokenTitle;
            elements.manageTokenBtn.textContent = t.manageTokenBtn;
            elements.updateTokenBtn.textContent = t.updateToken;
            elements.generateTokenBtn.textContent = t.generateToken;
            elements.currentTokenLabel.textContent = t.currentToken;
            elements.newTokenLabel.textContent = t.newToken;
            elements.infoModalTitle.textContent = t.infoTitle;
            elements.infoToggle.title = t.infoTitle;
            elements.infoModalDescription.textContent = t.infoDescription;
            
            const roleTranslations = {
                0: t.guestRole,
                1: t.managerRole,
                2: t.adminRole
            };
            elements.userRoleLabel.textContent = roleTranslations[userRole] || t.guestRole;
            
            const infoFeatures = [
                t.infoFeature1,
                t.infoFeature2,
                t.infoFeature3,
                t.infoFeature4,
                t.infoFeature5,
                t.infoFeature6,
                t.infoFeature7,
                t.infoFeature8
            ];
            
            let featuresHTML = '<ul>';
            infoFeatures.forEach(feature => {
                featuresHTML += `<li>${feature}</li>`;
            });
            featuresHTML += '</ul>';
            elements.infoModalBody.innerHTML = `<p>${t.infoDescription}</p>${featuresHTML}`;
        }

        function loadConfig() {
            // Async load - don't block rendering
            fetch('?action=load_config', { signal: AbortSignal.timeout(3000) })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        config = data.config;
                        config.language = '<?php echo $lang; ?>';
                        config.theme = '<?php echo $theme; ?>';
                        userRole = data.user_role || 0;

                        document.body.className = config.theme === 'dark' ? 'dark-theme' : '';

                        // Async token load
                        if (config.token) {
                            elements.currentTokenInput.value = config.token;
                        } else {
                            fetch('?action=get_token', { signal: AbortSignal.timeout(2000) })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        elements.currentTokenInput.value = data.token;
                                    }
                                })
                                .catch(() => {});
                        }

                        renderSites();
                        renderSiteTable();
                        updateTranslations();

                        if (config.sites.length > 0) {
                            const firstSite = config.sites
                                .filter(site => !site.hidden)
                                .sort((a, b) => b.priority - a.priority)[0];
                            if (firstSite) {
                                const index = config.sites.indexOf(firstSite);
                                selectSite(firstSite, index);
                            }
                        }
                    }
                })
                .catch(error => {
                    console.warn('Config load timeout, using defaults:', error.message);
                    // Use defaults
                    document.body.className = '<?php echo $theme; ?>' === 'dark' ? 'dark-theme' : '';
                });
        }

        function saveConfig() {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'save_config', config: config })
            }).catch(error => console.error('Error saving config:', error));
        }

        function renderSites() {
            elements.siteList.innerHTML = '';
            const searchTerm = elements.searchBar.value.toLowerCase();
            
            const filteredSites = config.sites
                .filter(site => !site.hidden && (
                    site.name.toLowerCase().includes(searchTerm) || 
                    site.url.toLowerCase().includes(searchTerm)
                ))
                .sort((a, b) => b.priority - a.priority);

            filteredSites.forEach((site, index) => {
                const siteItem = document.createElement('div');
                siteItem.className = 'site-item';
                let faviconUrl = site.url.startsWith('http') ? site.url : `http://${site.url}`;
                if (!faviconUrl.endsWith('/')) faviconUrl += '/';
                faviconUrl += 'favicon.ico';
                
                const lang = config.language || 'en';
                const t = translations[lang] || translations.en;
                
                siteItem.innerHTML = `
                    <img src="${faviconUrl}" alt="favicon" onerror="this.style.display='none'">
                    <div style="flex: 1;">
                        <div style="font-weight: bold;">${site.name} ${site.use_token ? `<span class="token-badge">${t.tokenBadge}</span>` : ''}</div>
                        <div style="font-size: 0.9rem; opacity: 0.7;">${site.url}</div>
                    </div>
                `;
                
                siteItem.addEventListener('click', () => {
                    selectSite(site, index);
                });
                
                elements.siteList.appendChild(siteItem);
            });
        }

        function renderSiteTable() {
            elements.siteTableBody.innerHTML = '';
            config.sites.forEach((site, index) => {
                const tr = document.createElement('tr');
                const lang = config.language || 'en';
                const t = translations[lang] || translations.en;
                tr.innerHTML = `
                    <td>${site.name} ${site.use_token ? `<span class="token-badge">${t.tokenBadge}</span>` : ''}</td>
                    <td class="site-actions">
                        ${userRole >= 2 ? `<button class="edit-site" data-index="${index}" title="${t.editSite}">
                            <i class="fas fa-edit"></i>
                        </button>` : ''}
                        ${userRole >= 2 ? `<button class="delete-site" data-index="${index}" title="${t.deleteConfirm}">
                            <i class="fas fa-trash"></i>
                        </button>` : ''}
                        <button class="toggle-hidden" data-index="${index}" title="${site.hidden ? t.showSite : t.hideSite}">
                            <i class="fas ${site.hidden ? 'fa-eye-slash' : 'fa-eye'}"></i>
                        </button>
                    </td>
                `;
                elements.siteTableBody.appendChild(tr);
            });

            document.querySelectorAll('.edit-site').forEach(button => {
                button.addEventListener('click', () => {
                    editIndex = parseInt(button.dataset.index);
                    const site = config.sites[editIndex];
                    elements.modalTitle.textContent = translations[config.language].editSite;
                    elements.siteName.value = site.name;
                    elements.siteUrl.value = site.url;
                    elements.sitePriority.value = site.priority;
                    elements.useTokenCheckbox.checked = site.use_token || false;
                    elements.addSiteModal.classList.add('active');
                });
            });

            function getNextSite(direction) {
                if (!config.sites || config.sites.length === 0) return null;
                
                const searchTerm = elements.searchBar.value.toLowerCase();
                const visibleSites = config.sites
                    .filter(site => !site.hidden && (
                        site.name.toLowerCase().includes(searchTerm) || 
                        site.url.toLowerCase().includes(searchTerm)
                    ))
                    .sort((a, b) => b.priority - a.priority);
                
                if (visibleSites.length === 0) return null;
                
                let currentIndex = -1;
                if (currentSite) {
                    currentIndex = visibleSites.findIndex(site => 
                        site.url === currentSite.url && site.name === currentSite.name
                    );
                }
                
                if (currentIndex === -1) {
                    // If no current site, return first
                    return { site: visibleSites[0], index: config.sites.indexOf(visibleSites[0]) };
                }
                
                let nextIndex;
                if (direction === 'next') {
                    nextIndex = (currentIndex + 1) % visibleSites.length;
                } else if (direction === 'prev') {
                    nextIndex = (currentIndex - 1 + visibleSites.length) % visibleSites.length;
                } else {
                    return null;
                }
                
                const nextSite = visibleSites[nextIndex];
                return { 
                    site: nextSite, 
                    index: config.sites.indexOf(nextSite) 
                };
            }

            function handleKeyNavigation(event) {
                // Check that not in input field
                const activeElement = document.activeElement;
                const isInputFocused = activeElement.tagName === 'INPUT' || 
                                    activeElement.tagName === 'TEXTAREA' ||
                                    activeElement.isContentEditable;
                
                if (isInputFocused) return;
                
                switch(event.key) {
                    case 'ArrowDown':
                    case 'ArrowRight':
                        event.preventDefault();
                        const next = getNextSite('next');
                        if (next) {
                            selectSite(next.site, next.index);
                        }
                        break;
                        
                    case 'ArrowUp':
                    case 'ArrowLeft':
                        event.preventDefault();
                        const prev = getNextSite('prev');
                        if (prev) {
                            selectSite(prev.site, prev.index);
                        }
                        break;
                        
                    case 'Enter':
                        if (currentSite && !elements.fullscreenModal.classList.contains('active')) {
                            openSiteInModal();
                        }
                        break;
                        
                    case 'Escape':
                        if (elements.fullscreenModal.classList.contains('active')) {
                            elements.fullscreenModal.classList.remove('active');
                            // Don't clear iframe src to preserve state
                        }
                        break;
                }
            }



            // Add keyboard handler
            document.addEventListener('keydown', handleKeyNavigation);

            // Update selectSite function to highlight active item in list
            function selectSite(site, index) {
                document.querySelectorAll('.site-item').forEach((item, i) => {
                    item.classList.toggle('active', i === index);
                });
                
                currentSite = site;
                elements.currentSiteTitle.textContent = site.name;
                elements.fullscreenSiteTitle.textContent = site.name;
                loadSiteContent();
                
                // Scroll to active item in list
                const activeItem = document.querySelector('.site-item.active');
                if (activeItem) {
                    activeItem.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest'
                    });
                }
            }



            document.querySelectorAll('.delete-site').forEach(button => {
                button.addEventListener('click', () => {
                    const index = parseInt(button.dataset.index);
                    const lang = config.language || 'en';
                    const t = translations[lang] || translations.en;
                    
                    showActionModal(t.confirm, t.deleteConfirm, () => {
                        fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'delete_site', index })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                config = data.config;
                                renderSites();
                                renderSiteTable();
                                if (currentSite && config.sites.indexOf(currentSite) === -1) {
                                    currentSite = null;
                                    elements.currentSiteTitle.textContent = translations[config.language].selectSite;
                                    elements.viewerIframe.src = 'about:blank';
                                    elements.fullscreenSiteTitle.textContent = translations[config.language].selectSite;
                                    elements.fullscreenIframe.src = 'about:blank';
                                }
                                showMessage(t.saveSuccess, 'success');
                            } else {
                                showMessage(data.message || t.errorSaving, 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error deleting site:', error);
                            showMessage(t.errorSaving, 'error');
                        });
                    });
                });
            });

            document.querySelectorAll('.toggle-hidden').forEach(button => {
                button.addEventListener('click', () => {
                    const index = parseInt(button.dataset.index);
                    fetch('', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'toggle_hidden', index })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            config = data.config;
                            renderSites();
                            renderSiteTable();
                            if (currentSite && config.sites.indexOf(currentSite) === -1) {
                                currentSite = null;
                                elements.currentSiteTitle.textContent = translations[config.language].selectSite;
                                elements.viewerIframe.src = 'about:blank';
                                elements.fullscreenSiteTitle.textContent = translations[config.language].selectSite;
                                elements.fullscreenIframe.src = 'about:blank';
                            }
                        }
                    });
                });
            });
        }

        function showMessage(message, type = 'info') {
            const lang = config.language || 'en';
            const t = translations[lang] || translations.en;
            
            showActionModal(type === 'success' ? t.saveSuccess : t.errorSaving, message, () => {
            });
        }

        function selectSite(site, index) {
            document.querySelectorAll('.site-item').forEach((item, i) => {
                item.classList.toggle('active', i === index);
            });

            currentSite = site;
            elements.currentSiteTitle.textContent = site.name;
            elements.fullscreenSiteTitle.textContent = site.name;
            loadSiteContent();
            
            // If fullscreen is active, update the fullscreen iframe too
            if (elements.fullscreenModal.classList.contains('active')) {
                elements.fullscreenIframe.src = elements.viewerIframe.src;
            }
        }

        function loadSiteContent() {
            if (!currentSite) return;
            
            let url = currentSite.url;
            if (!url.startsWith('http://') && !url.startsWith('https://')) {
                url = 'http://' + url;
            }
            
            if (currentSite.use_token && config.token) {
                const separator = url.includes('?') ? '&' : '?';
                url = url + separator + 'token=' + encodeURIComponent(config.token);
            }
            
            elements.viewerIframe.src = url;
        }

        function openSiteInModal() {
            if (!currentSite) return;

            let url = currentSite.url;
            if (!url.startsWith('http://') && !url.startsWith('https://')) {
                url = 'http://' + url;
            }

            if (currentSite.use_token && config.token) {
                const separator = url.includes('?') ? '&' : '?';
                url = url + separator + 'token=' + encodeURIComponent(config.token);
            }

            // Sync iframe content with current viewer state
            if (elements.viewerIframe.src && elements.viewerIframe.src !== 'about:blank') {
                elements.fullscreenIframe.src = elements.viewerIframe.src;
            } else {
                elements.fullscreenIframe.src = url;
            }
            
            // Activate modal with animation
            elements.fullscreenModal.classList.add('active');
        }

        elements.menuToggle.addEventListener('click', () => {
            elements.sidebar.classList.add('active');
            elements.content.classList.add('shifted');
        });

        elements.closeSidebar.addEventListener('click', () => {
            elements.sidebar.classList.remove('active');
            elements.content.classList.remove('shifted');
        });

        elements.infoToggle.addEventListener('click', () => {
            elements.infoModal.classList.add('active');
        });

        elements.closeInfoModal.addEventListener('click', () => {
            elements.infoModal.classList.remove('active');
        });

        elements.infoModal.addEventListener('click', (e) => {
            if (e.target === elements.infoModal) {
                elements.infoModal.classList.remove('active');
            }
        });

        elements.addSiteBtn.addEventListener('click', () => {
            editIndex = null;
            elements.modalTitle.textContent = translations[config.language].addNewSite;
            elements.siteName.value = '';
            elements.siteUrl.value = '';
            elements.sitePriority.value = '0';
            elements.useTokenCheckbox.checked = false;
            elements.addSiteModal.classList.add('active');
        });

        elements.closeModal.addEventListener('click', () => {
            elements.addSiteModal.classList.remove('active');
        });

        elements.saveSiteBtn.addEventListener('click', () => {
            const name = elements.siteName.value.trim();
            const url = elements.siteUrl.value.trim();
            const priority = parseInt(elements.sitePriority.value);
            const useToken = elements.useTokenCheckbox.checked;
            const lang = config.language || 'en';
            const t = translations[lang] || translations.en;
            
            if (!name || !url) {
                showMessage(t.fillAllFields, 'error');
                return;
            }

            if (!url.startsWith('http://') && !url.startsWith('https://') && !url.match(/^192\.168\./)) {
                showMessage(t.invalidUrl, 'error');
                return;
            }

            const newSite = { name, url, priority, hidden: false, use_token: useToken };
            const action = editIndex !== null ? 'edit_site' : 'add_site';
            const body = editIndex !== null ? 
                { action, site: newSite, index: editIndex } : 
                { action, site: newSite };
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    config = data.config;
                    renderSites();
                    renderSiteTable();
                    elements.addSiteModal.classList.remove('active');
                    elements.siteName.value = '';
                    elements.siteUrl.value = '';
                    elements.sitePriority.value = '0';
                    elements.useTokenCheckbox.checked = false;
                    
                    if (editIndex !== null && currentSite && config.sites[editIndex]) {
                        selectSite(config.sites[editIndex], editIndex);
                    }
                    
                    showMessage(t.saveSuccess, 'success');
                } else {
                    showMessage(data.message || t.errorSaving, 'error');
                }
            })
            .catch(error => {
                console.error('Error saving site:', error);
                showMessage(t.errorSaving, 'error');
            });
        });

        elements.searchBar.addEventListener('input', renderSites);

        elements.refreshBtn.addEventListener('click', () => {
            if (currentSite) {
                loadSiteContent();
            }
        });

        elements.openInModal.addEventListener('click', () => {
            openSiteInModal();
        });

        elements.closeFullscreenModal.addEventListener('click', () => {
            // Animate closing
            elements.fullscreenModal.classList.remove('active');
            // Don't clear iframe src to preserve state
        });

        elements.closeTokenModal.addEventListener('click', () => {
            elements.tokenModal.classList.remove('active');
        });

        elements.manageTokenBtn.addEventListener('click', () => {
            if (userRole >= 2) {
                elements.currentTokenInput.value = config.token || '';
                elements.newTokenInput.value = '';
                elements.tokenModal.classList.add('active');
            }
        });

        elements.updateTokenBtn.addEventListener('click', () => {
            const token = elements.newTokenInput.value.trim();
            const lang = config.language || 'en';
            const t = translations[lang] || translations.en;
            
            if (!token) {
                showMessage('Please enter token', 'error');
                return;
            }
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update_token', token })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    config.token = data.token;
                    elements.currentTokenInput.value = data.token;
                    elements.newTokenInput.value = '';
                    showMessage(t.tokenUpdated, 'success');
                } else {
                    showMessage(data.message || 'Error updating token', 'error');
                }
            })
            .catch(error => {
                console.error('Error updating token:', error);
                showMessage('Error updating token', 'error');
            });
        });

        elements.generateTokenBtn.addEventListener('click', () => {
            const lang = config.language || 'en';
            const t = translations[lang] || translations.en;
            
            const array = new Uint8Array(16);
            window.crypto.getRandomValues(array);
            const newToken = Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
            
            elements.newTokenInput.value = newToken;
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update_token', token: newToken })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    config.token = data.token;
                    elements.currentTokenInput.value = data.token;
                    showMessage(t.tokenGenerated, 'success');
                }
            })
            .catch(error => {
                console.error('Error generating token:', error);
            });
        });

        elements.closeActionModal.addEventListener('click', () => {
            elements.actionModal.classList.remove('active');
            actionModalCallback = null;
        });

        elements.actionModalCancel.addEventListener('click', () => {
            elements.actionModal.classList.remove('active');
            actionModalCallback = null;
        });

        elements.actionModalConfirm.addEventListener('click', () => {
            elements.actionModal.classList.remove('active');
            if (actionModalCallback) {
                actionModalCallback();
                actionModalCallback = null;
            }
        });

        elements.actionModal.addEventListener('click', (e) => {
            if (e.target === elements.actionModal) {
                elements.actionModal.classList.remove('active');
                actionModalCallback = null;
            }
        });

        elements.addSiteModal.addEventListener('click', (e) => {
            if (e.target === elements.addSiteModal) {
            }
        });

        elements.tokenModal.addEventListener('click', (e) => {
            if (e.target === elements.tokenModal) {
            }
        });

        document.addEventListener('click', (e) => {
            if (!elements.sidebar.contains(e.target) && !elements.menuToggle.contains(e.target)) {
                elements.sidebar.classList.remove('active');
                elements.content.classList.remove('shifted');
            }
        });

        loadConfig();
        
        // Spider Web Background Animation - start immediately
        (function() {
            const canvas = document.getElementById('networkCanvas');
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            let animationId;
            let time = 0;
            
            // Spider web configuration - initialize immediately
            let radialLines = [];
            let spiralLines = [];
            let connectionPoints = [];
            let dewDrops = [];
            let smallSpiders = [];
            
            // Resize canvas to window size
            function resizeCanvas() {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
            }
            
            function initWeb() {
                const centerX = canvas.width / 2;
                const centerY = canvas.height / 2;
                const maxRadius = Math.max(canvas.width, canvas.height) * 0.7;
                const numRadial = 24; // Number of radial lines
                const numSpirals = 15; // Number of spiral rings
                
                radialLines = [];
                spiralLines = [];
                connectionPoints = [];
                dewDrops = [];
                smallSpiders = [];
                
                // Create radial lines
                for (let i = 0; i < numRadial; i++) {
                    const angle = (Math.PI * 2 * i) / numRadial;
                    radialLines.push({
                        angle: angle,
                        baseAngle: angle,
                        swaySpeed: 0.3 + Math.random() * 0.2,
                        swayAmount: (Math.random() - 0.5) * 0.02,
                        segments: []
                    });
                    
                    // Create segments along each radial line
                    const numSegments = 20;
                    for (let j = 1; j <= numSegments; j++) {
                        const distance = (maxRadius * j) / numSegments;
                        radialLines[i].segments.push({
                            distance: distance,
                            x: centerX + Math.cos(angle) * distance,
                            y: centerY + Math.sin(angle) * distance
                        });
                    }
                }
                
                // Create spiral connections
                for (let i = 0; i < numSpirals; i++) {
                    const baseRadius = (maxRadius * (i + 1)) / numSpirals;
                    spiralLines.push({
                        baseRadius: baseRadius,
                        radius: baseRadius,
                        pulseSpeed: 0.5 + Math.random() * 0.5,
                        pulseAmount: 3 + Math.random() * 5,
                        points: []
                    });
                    
                    // Create points along spiral
                    const numPoints = 48;
                    for (let j = 0; j < numPoints; j++) {
                        const angle = (Math.PI * 2 * j) / numPoints;
                        spiralLines[i].points.push({
                            angle: angle,
                            x: centerX + Math.cos(angle) * baseRadius,
                            y: centerY + Math.sin(angle) * baseRadius
                        });
                    }
                }
                
                // Create connection points at intersections
                for (let i = 0; i < numRadial; i++) {
                    for (let j = 0; j < numSpirals; j++) {
                        const distance = spiralLines[j].baseRadius;
                        const angle = radialLines[i].baseAngle;
                        connectionPoints.push({
                            x: centerX + Math.cos(angle) * distance,
                            y: centerY + Math.sin(angle) * distance,
                            baseX: centerX + Math.cos(angle) * distance,
                            baseY: centerY + Math.sin(angle) * distance,
                            pulsePhase: Math.random() * Math.PI * 2,
                            size: 1 + Math.random() * 2
                        });
                    }
                }
                
                // Create dew drops (decorative elements)
                const numDewDrops = 30;
                for (let i = 0; i < numDewDrops; i++) {
                    const angle = Math.random() * Math.PI * 2;
                    const distance = Math.random() * maxRadius;
                    dewDrops.push({
                        x: centerX + Math.cos(angle) * distance,
                        y: centerY + Math.sin(angle) * distance,
                        baseX: centerX + Math.cos(angle) * distance,
                        baseY: centerY + Math.sin(angle) * distance,
                        size: 2 + Math.random() * 4,
                        swaySpeed: 0.2 + Math.random() * 0.3,
                        swayAmount: 2 + Math.random() * 3,
                        phase: Math.random() * Math.PI * 2,
                        opacity: 0.3 + Math.random() * 0.5
                    });
                }
                
                // Create small decorative spiders
                const numSpiders = 5;
                for (let i = 0; i < numSpiders; i++) {
                    const angle = Math.random() * Math.PI * 2;
                    const distance = 100 + Math.random() * (maxRadius - 100);
                    smallSpiders.push({
                        x: centerX + Math.cos(angle) * distance,
                        y: centerY + Math.sin(angle) * distance,
                        baseX: centerX + Math.cos(angle) * distance,
                        baseY: centerY + Math.sin(angle) * distance,
                        size: 5 + Math.random() * 8,
                        speed: 0.3 + Math.random() * 0.4,
                        phase: Math.random() * Math.PI * 2,
                        legPhase: Math.random() * Math.PI * 2,
                        angle: angle
                    });
                }
            }
            
            function getWebColors(isDark) {
                if (isDark) {
                    return {
                        primary: 'rgba(120, 200, 255, 0.6)',
                        secondary: 'rgba(100, 180, 240, 0.5)',
                        accent: 'rgba(140, 220, 255, 0.7)',
                        point: 'rgba(170, 230, 255, 0.9)',
                        dew: 'rgba(170, 240, 255, 0.4)',
                        spider: 'rgba(200, 240, 255, 0.95)'
                    };
                } else {
                    return {
                        primary: 'rgba(60, 120, 180, 0.45)',
                        secondary: 'rgba(80, 140, 200, 0.35)',
                        accent: 'rgba(50, 110, 170, 0.55)',
                        point: 'rgba(60, 120, 180, 0.7)',
                        dew: 'rgba(80, 150, 210, 0.35)',
                        spider: 'rgba(70, 130, 190, 0.8)'
                    };
                }
            }
            
            function drawWeb(currentTime) {
                const centerX = canvas.width / 2;
                const centerY = canvas.height / 2;
                const isDark = document.body.classList.contains('dark-theme');
                const colors = getWebColors(isDark);
                
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                // Update time
                time = currentTime * 0.001; // Convert to seconds
                
                // Draw spiral connections first
                spiralLines.forEach((spiral, spiralIndex) => {
                    const pulse = Math.sin(time * spiral.pulseSpeed) * spiral.pulseAmount;
                    const radius = spiral.baseRadius + pulse;
                    
                    ctx.strokeStyle = colors.secondary;
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    
                    for (let i = 0; i < spiral.points.length; i++) {
                        const angle = spiral.points[i].angle;
                        const x = centerX + Math.cos(angle) * radius;
                        const y = centerY + Math.sin(angle) * radius;
                        spiral.points[i].x = x;
                        spiral.points[i].y = y;
                        
                        if (i === 0) {
                            ctx.moveTo(x, y);
                        } else {
                            ctx.lineTo(x, y);
                        }
                    }
                    ctx.closePath();
                    ctx.stroke();
                });
                
                // Draw radial lines
                radialLines.forEach((radial, radialIndex) => {
                    const sway = Math.sin(time * radial.swaySpeed + radialIndex) * radial.swayAmount;
                    const angle = radial.baseAngle + sway;
                    radial.angle = angle;
                    
                    ctx.strokeStyle = colors.primary;
                    ctx.lineWidth = 1.5;
                    ctx.beginPath();
                    ctx.moveTo(centerX, centerY);
                    
                    radial.segments.forEach((segment, segIndex) => {
                        const x = centerX + Math.cos(angle) * segment.distance;
                        const y = centerY + Math.sin(angle) * segment.distance;
                        segment.x = x;
                        segment.y = y;
                        
                        if (segIndex === 0) {
                            ctx.lineTo(x, y);
                        } else {
                            ctx.lineTo(x, y);
                        }
                    });
                    ctx.stroke();
                });
                
                // Draw connection points
                connectionPoints.forEach((point, index) => {
                    const pulse = Math.sin(time * 2 + point.pulsePhase) * 0.5 + 0.5;
                    
                    // Find corresponding radial and spiral
                    const radialIndex = index % radialLines.length;
                    const spiralIndex = Math.floor(index / radialLines.length);
                    
                    if (radialLines[radialIndex] && spiralLines[spiralIndex]) {
                        const angle = radialLines[radialIndex].angle;
                        const radius = spiralLines[spiralIndex].radius;
                        point.x = centerX + Math.cos(angle) * radius;
                        point.y = centerY + Math.sin(angle) * radius;
                    }
                    
                    // Draw point
                    ctx.fillStyle = colors.point;
                    ctx.beginPath();
                    ctx.arc(point.x, point.y, point.size * (0.8 + pulse * 0.2), 0, Math.PI * 2);
                    ctx.fill();
                });
                
                // Draw dew drops
                dewDrops.forEach((drop) => {
                    const swayX = Math.sin(time * drop.swaySpeed + drop.phase) * drop.swayAmount;
                    const swayY = Math.cos(time * drop.swaySpeed * 0.7 + drop.phase) * drop.swayAmount;
                    drop.x = drop.baseX + swayX;
                    drop.y = drop.baseY + swayY;
                    
                    // Draw dew drop
                    const gradient = ctx.createRadialGradient(
                        drop.x, drop.y, 0,
                        drop.x, drop.y, drop.size
                    );
                    gradient.addColorStop(0, colors.dew);
                    gradient.addColorStop(1, 'transparent');
                    
                    ctx.fillStyle = gradient;
                    ctx.beginPath();
                    ctx.arc(drop.x, drop.y, drop.size, 0, Math.PI * 2);
                    ctx.fill();
                });
                
                // Draw small spiders
                smallSpiders.forEach((spider) => {
                    const moveX = Math.sin(time * spider.speed + spider.phase) * 30;
                    const moveY = Math.cos(time * spider.speed * 0.8 + spider.phase) * 20;
                    spider.x = spider.baseX + moveX;
                    spider.y = spider.baseY + moveY;
                    
                    const legWave = Math.sin(time * 5 + spider.legPhase) * 2;
                    
                    ctx.strokeStyle = colors.spider;
                    ctx.lineWidth = 1.5;
                    
                    // Draw body
                    ctx.fillStyle = colors.spider;
                    ctx.beginPath();
                    ctx.ellipse(spider.x, spider.y, spider.size * 0.6, spider.size * 0.4, 0, 0, Math.PI * 2);
                    ctx.fill();
                    
                    // Draw legs (4 on each side)
                    for (let side = -1; side <= 1; side += 2) {
                        for (let leg = 0; leg < 4; leg++) {
                            const legAngle = (leg - 1.5) * 0.4 * side;
                            const legLength = spider.size * 0.8;
                            const waveOffset = Math.sin(time * 3 + leg * 0.5 + spider.legPhase) * 3;
                            
                            const startX = spider.x + Math.cos(legAngle) * spider.size * 0.4;
                            const startY = spider.y + Math.sin(legAngle) * spider.size * 0.2;
                            const endX = startX + Math.cos(legAngle) * legLength;
                            const endY = startY + Math.sin(legAngle) * legLength + waveOffset;
                            
                            ctx.beginPath();
                            ctx.moveTo(startX, startY);
                            ctx.lineTo(endX, endY);
                            ctx.stroke();
                        }
                    }
                });
            }
            
            // Animation loop
            function animate(currentTime) {
                drawWeb(currentTime);
                animationId = requestAnimationFrame(animate);
            }
            
            // Start animation
            resizeCanvas();
            window.addEventListener('resize', () => {
                clearTimeout(window.resizeTimer);
                window.resizeTimer = setTimeout(() => {
                    resizeCanvas();
                    initWeb();
                }, 200);
            });
            
            initWeb();
            animate(0);
            
            // Pause animation when tab is not visible
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    cancelAnimationFrame(animationId);
                } else {
                    animate(0);
                }
            });
        })();
    </script>
