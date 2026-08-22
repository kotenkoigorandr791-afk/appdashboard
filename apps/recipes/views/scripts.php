    <script>
        const products = <?php echo json_encode($products, JSON_UNESCAPED_UNICODE); ?>;
        const productImagePaths = <?php
            $productImagePaths = [];
            foreach ($products as $productPathItem) {
                $productImagePaths[$productPathItem['id']] = recipes_product_thumb_public_path($productPathItem, $products_dir);
            }
            echo json_encode($productImagePaths, JSON_UNESCAPED_UNICODE);
        ?>;
        const recipeImagePaths = <?php
            $recipeImagePaths = [];
            foreach ($recipes as $recipePathItem) {
                $recipeImagePaths[$recipePathItem['id']] = isset($recipePathItem['image']) && is_file(__DIR__ . '/../' . $recipes_dir . '/' . $recipePathItem['id'] . '/' . $recipePathItem['image']) ? $recipes_dir . '/' . $recipePathItem['id'] . '/' . $recipePathItem['image'] : 'default_recipe.jpg';
            }
            echo json_encode($recipeImagePaths, JSON_UNESCAPED_UNICODE);
        ?>;
        const PRODUCT_IMG_WIDE_MIN = 900;
        function recipeImg(recipe) {
            return recipeImagePaths[recipe?.id] || 'default_recipe.jpg';
        }
        function productImgTile(p) {
            if (p && productImagePaths[p.id]) return productImagePaths[p.id];
            if (!p || !p.image) return 'default_recipe.jpg';
            const v = encodeURIComponent(p.updated_at || '');
            if (p.image_preview) return 'products/' + p.id + '/' + p.image_preview + (v ? ('?v=' + v) : '');
            return 'products/' + p.id + '/' + p.image + (v ? ('?v=' + v) : '');
        }
        function productImgFull(p) {
            if (p && productImagePaths[p.id]) return productImagePaths[p.id];
            if (!p || !p.image) return 'default_recipe.jpg';
            const v = encodeURIComponent(p.updated_at || '');
            return 'products/' + p.id + '/' + p.image + (v ? ('?v=' + v) : '');
        }
        function syncIngredientProductImages() {
            const wide = window.innerWidth >= PRODUCT_IMG_WIDE_MIN;
            document.querySelectorAll('#recipeViewContent .ingredient-image[data-src-full]').forEach(img => {
                const url = wide ? img.getAttribute('data-src-full') : img.getAttribute('data-src-preview');
                if (url) img.src = url;
            });
        }
        function syncProductViewModalImg() {
            const modal = document.getElementById('productViewModal');
            const im = document.getElementById('productViewModalImg');
            if (!modal || !im || modal.style.display !== 'flex' || !im.dataset.pid) return;
            const p = products.find(x => x.id === im.dataset.pid);
            if (!p) return;
            im.src = window.innerWidth >= PRODUCT_IMG_WIDE_MIN ? productImgFull(p) : productImgTile(p);
        }
        function openProductViewModal(productId) {
            const p = products.find(x => x.id === productId);
            if (!p) return;
            const productName = p.name[currentLang] || p.name.en || translations.product_view;
            document.getElementById('productViewModalTitle').textContent = translations.product_view;
            document.getElementById('productViewModalName').textContent = productName;
            const im = document.getElementById('productViewModalImg');
            im.dataset.pid = productId;
            im.alt = productName;
            im.src = window.innerWidth >= PRODUCT_IMG_WIDE_MIN ? productImgFull(p) : productImgTile(p);
            openModal('productViewModal');
        }
        const recipes = <?php echo json_encode($recipes, JSON_UNESCAPED_UNICODE); ?>;
        const translations = <?php echo json_encode($translations[$lang] ?? $translations['en'] ?? [], JSON_UNESCAPED_UNICODE); ?>;
        const currentLang = '<?php echo $lang; ?>';
        window.RecipeColorTone?.apply({ color: '<?php echo $color; ?>', theme: '<?php echo $theme; ?>' });
        const itemsPerPage = <?php echo $config['items_per_page']; ?>;
        
        const userRole = <?php echo $userRole; ?>;
        const recipeSearchCache = new Map();
        let steps = [];
        let selectedSearchProducts = [];
        const recipeState = <?php echo json_encode($recipe_state, JSON_UNESCAPED_UNICODE); ?>;
        let currentStepIndex = null;
        let currentEditingRecipeSnapshot = null;
        let isTranslating = false;
        let currentShareImage = null;
        let currentShareRecipeId = null;
        let recipeImagePendingFile = null;
        let productImagePendingFile = null;
        let editProductImagePendingFile = null;
        const imageUrlLoadTimers = {};
        const backgroundRemovalState = {
            product: { originalFile: null, removed: false, busy: false },
            recipe: { originalFile: null, removed: false, busy: false },
            editProduct: { originalFile: null, removed: false, busy: false }
        };
        let cropperUiAbort = null;
        async function parseJsonResponse(response) {
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (error) {
                throw new Error(response.ok ? 'Invalid JSON response' : `Request failed (${response.status})`);
            }
        }
        
        // Languages for switching
        const languages = {
            'en': 'English',
            'uk': 'Українська',
            'ru': 'Русский',
            'be': 'Беларуская'
        };

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.lang-btn');
            if (!btn) return;
            const pills = btn.closest('.lang-pills');
            if (!pills) return;
            pills.querySelectorAll('.lang-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        });

        function getCurrentProductPage() {
            const urlParams = new URLSearchParams(window.location.search);
            return parseInt(urlParams.get('product_page') || '<?php echo $product_page; ?>', 10) || 1;
        }

        function buildManagementProductCard(product) {
            const editButton = userRole >= 1 ? `
                    <button onclick="editProductName('${product.id}'); event.stopPropagation();" title="${translations.edit_name}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="<?php echo $theme === 'dark' ? '#ffffff' : '#000000'; ?>" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    </button>` : '';
            const deleteButton = userRole >= 2 ? `
                    <button onclick="deleteProduct('${product.id}'); event.stopPropagation();" title="${translations.delete}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="<?php echo $theme === 'dark' ? '#ffffff' : '#000000'; ?>" stroke-width="2">
                            <path d="M3 6h18"></path>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>` : '';
            return `
                <img src="${productImgTile(product)}" alt="${product.name[currentLang]}">
                <p>${product.name[currentLang]}</p>
                <div class="product-card-actions">
                    ${editButton}
                    ${deleteButton}
                </div>
            `;
        }

        async function ensureRecipeSteps(recipe) {
            if (!recipe || !recipe.id) return null;
            if (Array.isArray(recipe.steps) && recipe.steps.length > 0) return recipe;
            if (recipeSearchCache.has(recipe.id)) {
                const cachedRecipe = recipeSearchCache.get(recipe.id);
                recipe.steps = cachedRecipe.steps || [];
                return recipe;
            }

            const response = await fetch(`?get_recipe_full=1&recipe_id=${encodeURIComponent(recipe.id)}`);
            const result = await parseJsonResponse(response);
            if (!result || !result.success || !result.data) {
                throw new Error('Failed to load recipe steps');
            }

            recipe.steps = Array.isArray(result.data.steps) ? result.data.steps : [];
            recipeSearchCache.set(recipe.id, result.data);
            return recipe;
        }

        function getComparableProductIds(recipe) {
            if (!recipe || !Array.isArray(recipe.steps)) return [];
            const requiredProducts = recipe.steps
                .filter(step => step.type === 'product' && step.content)
                .map(step => step.content);
            return [...new Set(requiredProducts)];
        }

        function getRecipeLocalizedText(value, langCode) {
            if (typeof value === 'string') return value;
            if (!value || typeof value !== 'object') return '';
            return value[langCode] || value.en || '';
        }

        function cloneJsonData(value) {
            return value ? JSON.parse(JSON.stringify(value)) : value;
        }

        async function translateTextToAllLanguages(text, sourceLang) {
            const translated = {
                en: text,
                uk: text,
                ru: text,
                be: text
            };
            const targets = ['en', 'uk', 'ru', 'be'].filter(langCode => langCode !== sourceLang);
            if (targets.length === 0 || !text) {
                return translated;
            }

            const response = await fetch('translate_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    text,
                    source_lang: sourceLang,
                    target_langs: targets,
                    context: 'food'
                })
            });
            const data = await parseJsonResponse(response);
            if (!data || !data.success) {
                throw new Error('Translation request failed');
            }
            targets.forEach(langCode => {
                if (data.translations && data.translations[langCode]) {
                    translated[langCode] = data.translations[langCode];
                }
            });
            return translated;
        }

        function filterRecipes() {
            const searchTerm = document.getElementById('recipeSearchInput').value.toLowerCase();
            const recipeCards = document.querySelectorAll('#recipeGrid .recipe-card');
            
            recipeCards.forEach(card => {
                const title = card.querySelector('h3').textContent.toLowerCase();
                if (title.includes(searchTerm)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function showNotification(message, type = 'info') {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <div class="notification-bar"></div>
                    <span class="notification-text">${message}</span>
                </div>
                <button class="notification-close" onclick="this.parentElement.remove()">×</button>
            `;
            container.appendChild(notification);
            setTimeout(() => notification.remove(), 5000);
        }

        function disableBodyScroll() {
            const scrollY = window.scrollY;
            document.body.classList.add('modal-open');
            document.body.style.overflow = 'hidden';
            document.documentElement.style.overflow = 'hidden';
            document.body.setAttribute('data-scroll-y', scrollY);
        }

        function enableBodyScroll() {
            const scrollY = document.body.getAttribute('data-scroll-y');
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.documentElement.style.overflow = '';
            document.body.removeAttribute('data-scroll-y');
            if (scrollY !== null) {
                window.scrollTo(0, parseInt(scrollY));
            }
        }

        function continueRecipe() {
            if (recipeState.current_recipe) {
                showRecipe(recipeState.current_recipe);
            }
        }

        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'flex';
            window.location.hash = '#' + modalId;
            
            disableBodyScroll();
            
            if (window.innerWidth <= 768) {
                modal.classList.add('mobile-fullscreen');
                modal.style.alignItems = 'flex-start';
                modal.style.padding = '0';
                
                const modalContent = modal.querySelector('.modal-content');
                if (modalContent) {
                    modalContent.style.width = '100vw';
                    modalContent.style.height = '100vh';
                    modalContent.style.maxWidth = 'none';
                    modalContent.style.maxHeight = 'none';
                    modalContent.style.borderRadius = '0';
                    modalContent.style.padding = '50px 15px 15px 15px';
                }
            }
            
            if (modalId === 'recipeModal') {
                if (!skipRecipeModalReset) {
                document.getElementById('recipeModalTitle').textContent = translations.add_recipe;
                document.getElementById('recipeFormAction').value = 'add_recipe';
                document.getElementById('recipeFormId').value = '';
                setRecipeImageCleared(false);
                document.getElementById('recipeClearImageFlag').value = '0';
                document.getElementById('recipeTitle').value = '';
                document.getElementById('steps').innerHTML = `<button type="button" onclick="addStep()">${translations.add_step}</button>`;
                steps = [];
                currentEditingRecipeSnapshot = null;
                resetBackgroundRemovalState('recipe');
                recipeImagePendingFile = null;
                const _rurl = document.getElementById('recipeImageUrlInput');
                const _ruse = document.getElementById('recipeImageUseUrl');
                if (_ruse) { _ruse.checked = false; syncImageSourceMode('recipe'); }
                if (_rurl) _rurl.value = '';
                const _rip = document.getElementById('recipeImagePreview');
                const _rif = document.getElementById('recipeImageInput');
                if (_rip) { _rip.style.display = 'none'; document.getElementById('recipePreviewImg').src = ''; }
                delete document.getElementById('recipePreviewImg').dataset.clickAction;
                delete document.getElementById('recipePreviewImg').dataset.existingSrc;
                if (_rif) { _rif.value = ''; _rif.style.display = 'block'; }
                setImageSourceSwitchVisible('recipe', true);
                setRecipeUploadControlsVisible(true);
                
                if (window.innerWidth <= 768) {
                    const inputs = document.querySelectorAll('#recipeForm input[type="text"], #recipeForm textarea');
                    inputs.forEach(input => {
                        input.style.width = '100%';
                        input.style.boxSizing = 'border-box';
                    });
                    
                    const fileInput = document.querySelector('#recipeForm input[type="file"]');
                    if (fileInput) {
                        fileInput.style.width = '100%';
                    }
                }
                }
            }
            if (modalId === 'aiRecipeWorkspaceModal') {
                modal.classList.add('mobile-fullscreen');
                modal.style.alignItems = 'stretch';
                modal.style.padding = '0';
                const mc = modal.querySelector('.modal-content');
                if (mc) {
                    mc.style.width = '100vw';
                    mc.style.height = '100vh';
                    mc.style.maxWidth = 'none';
                    mc.style.maxHeight = 'none';
                    mc.style.borderRadius = '0';
                }
            }
            if (modalId === 'productModal') {
                document.getElementById('productSearch').value = '';
                refreshProductGrid();
                const pagination = document.querySelector('#productModal .pagination');
                if (pagination) {
                    pagination.style.display = 'flex';
                }
            }
            if (modalId === 'addProductModal') {
                resetBackgroundRemovalState('product');
                productImagePendingFile = null;
                const _puse = document.getElementById('productImageUseUrl');
                const _purl = document.getElementById('productImageUrlInput');
                if (_puse) { _puse.checked = false; }
                if (_purl) _purl.value = '';
                const _prip = document.getElementById('productImagePreview');
                const _pfin = document.getElementById('productImageInput');
                if (_prip) { _prip.style.display = 'none'; document.getElementById('productPreviewImg').src = ''; }
                delete document.getElementById('productPreviewImg').dataset.clickAction;
                delete document.getElementById('productPreviewImg').dataset.existingSrc;
                if (_pfin) { _pfin.value = ''; _pfin.style.display = 'block'; }
                const _pform = document.getElementById('productForm');
                if (_pform) _pform.reset();
                document.getElementById('productLang').value = currentLang;
                document.getElementById('productFormLang').value = currentLang;
                syncImageSourceMode('product');
                if (_pfin && document.getElementById('productImageUseUrl') && !document.getElementById('productImageUseUrl').checked) {
                    _pfin.required = true;
                }
            }
            if (modalId === 'productSelectionModal') {
                document.getElementById('productSelectionSearch').value = '';
                refreshSelectionProductGrid();
                const pagination = document.querySelector('#productSelectionModal .pagination');
                if (pagination) {
                    pagination.style.display = 'flex';
                }
            }
            if (modalId === 'searchRecipeModal') {
                selectedSearchProducts = [];
                updateSelectedProducts();
                document.querySelectorAll('#searchProductGrid .product-card').forEach(card => {
                    card.classList.remove('selected');
                });
            }
            if (modalId === 'shareModal') {
                document.getElementById('sharePreviewContainer').style.display = 'none';
                document.getElementById('shareLangSelect').value = currentLang;
                document.getElementById('shareThemeSelect').value = '<?php echo $theme; ?>';
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'none';

            const openModals = Array.from(document.querySelectorAll('.modal'))
                .filter(openModal => openModal.style.display === 'flex');
            const lastOpenModal = openModals[openModals.length - 1];
            const newHash = lastOpenModal ? `#${lastOpenModal.id}` : '';
            window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}${newHash}`);
            
            if (!lastOpenModal) {
                enableBodyScroll();
            }
            
            modal.classList.remove('mobile-fullscreen');
            modal.style.alignItems = '';
            modal.style.padding = '';
            
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
                modalContent.style.width = '';
                modalContent.style.height = '';
                modalContent.style.maxWidth = '';
                modalContent.style.maxHeight = '';
                modalContent.style.borderRadius = '';
                modalContent.style.padding = '';
            }
            
            if (modalId === 'recipeModal') {
                steps = [];
                currentEditingRecipeSnapshot = null;
                resetBackgroundRemovalState('recipe');
                isTranslating = false;
                enableRecipeFormButton();
                
                if (window.innerWidth <= 768) {
                    const inputs = document.querySelectorAll('#recipeForm input[type="text"], #recipeForm textarea');
                    inputs.forEach(input => {
                        input.style.width = '';
                        input.style.boxSizing = '';
                    });
                    
                    const fileInput = document.querySelector('#recipeForm input[type="file"]');
                    if (fileInput) {
                        fileInput.style.width = '';
                    }
                }
            } else if (modalId === 'recipeViewModal') {
                document.getElementById('recipeGrid').style.display = 'grid';
            } else if (modalId === 'searchRecipeModal') {
                selectedSearchProducts = [];
            } else if (modalId === 'shareModal') {
                currentShareImage = null;
            }
        }

        function disableRecipeFormButton() {
            const submitButton = document.getElementById('recipeFormSubmit');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = translations.translating;
                submitButton.style.opacity = '0.7';
                submitButton.style.cursor = 'not-allowed';
            }
        }

        function enableRecipeFormButton() {
            const submitButton = document.getElementById('recipeFormSubmit');
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = translations.save;
                submitButton.style.opacity = '1';
                submitButton.style.cursor = 'pointer';
            }
        }

        function disableProductFormButton() {
            const submitButton = document.getElementById('productFormSubmit');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = translations.translating;
                submitButton.style.opacity = '0.7';
                submitButton.style.cursor = 'not-allowed';
            }
        }

        function enableProductFormButton() {
            const submitButton = document.getElementById('productFormSubmit');
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = translations.save;
                submitButton.style.opacity = '1';
                submitButton.style.cursor = 'pointer';
            }
        }

        function renderProductGrid(productsList) {
            const grid = document.getElementById('productGrid');
            grid.innerHTML = '';
            
            const sortedProducts = getSortedProducts(productsList);
            const totalPages = Math.max(1, Math.ceil(sortedProducts.length / itemsPerPage));
            const page = Math.min(getCurrentProductPage(), totalPages);
            const start = (page - 1) * itemsPerPage;
            const pagedProducts = sortedProducts.slice(start, start + itemsPerPage);
            pagedProducts.forEach(product => {
                const card = document.createElement('div');
                card.className = 'product-card';
                card.setAttribute('data-id', product.id);
                card.setAttribute('data-name', product.name[currentLang].toLowerCase());
                card.innerHTML = buildManagementProductCard(product);
                grid.appendChild(card);
            });
            bindProductModalEvents();
            renderProductPagination(page, totalPages);
        }

        function renderProductPagination(page, totalPages) {
            const pagination = document.querySelector('#productModal .pagination');
            if (!pagination) return;
            if (totalPages <= 1) {
                pagination.style.display = 'none';
                return;
            }

            let html = '';
            if (page > 1) {
                html += `<a href="#" onclick="changeProductPage(${page - 1}); return false;">&laquo; ${translations.previous}</a>`;
            }
            for (let i = 1; i <= totalPages; i++) {
                html += `<a href="#" onclick="changeProductPage(${i}); return false;" ${i === page ? 'class="active"' : ''}>${i}</a>`;
            }
            if (page < totalPages) {
                html += `<a href="#" onclick="changeProductPage(${page + 1}); return false;">${translations.next} &raquo;</a>`;
            }
            pagination.innerHTML = html;
            pagination.style.display = 'flex';
        }

        function refreshProducts() {
            fetch('?get_products=1')
                .then(response => parseJsonResponse(response))
                .then(productsList => {
                    products.splice(0, products.length, ...productsList);
                    renderProductGrid(productsList);
                    filterProducts(document.getElementById('productSearch').value);
                })
                .catch(error => {
                    console.error('Error refreshing products:', error);
                    showNotification(translations.error_occurred, 'error');
                });
        }

        function refreshRecipesGrid() {
            // Reload recipes from server and update the grid
            const urlParams = new URLSearchParams(window.location.search);
            const lang = urlParams.get('lang') || currentLang;
            const themeParam = urlParams.get('theme') || 'light';
            const colorParam = urlParams.get('color') || '#78a300';

            fetch(`?get_recipes=1&lang=${lang}&theme=${themeParam}&color=${encodeURIComponent(colorParam)}`)
                .then(response => parseJsonResponse(response))
                .then(data => {
                    if (data.recipes) {
                        // Update the global recipes array
                        recipes.splice(0, recipes.length, ...data.recipes);

                        // Update the grid
                        const recipeGrid = document.getElementById('recipeGrid');
                        recipeGrid.innerHTML = '';

                        const itemsPerPageCount = window.itemsPerPageConfig || itemsPerPage;
                        const page = data.page || 1;
                        const start = (page - 1) * itemsPerPageCount;
                        const pagedRecipes = data.recipes.slice(start, start + itemsPerPageCount);

                        pagedRecipes.forEach(recipe => {
                            const imageSrc = recipeImg(recipe);
                            const card = document.createElement('div');
                            card.className = 'recipe-card';
                            card.onclick = () => showRecipe(recipe.id);

                            let actionsHtml = `
                                <div class="card-actions">
                                    <button onclick="shareRecipe('${recipe.id}', event);" title="${translations.share}">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="${themeParam === 'dark' ? '#ffffff' : '#000000'}" stroke-width="2">
                                            <circle cx="18" cy="5" r="3"></circle>
                                            <circle cx="6" cy="12" r="3"></circle>
                                            <circle cx="18" cy="19" r="3"></circle>
                                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                                            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                                        </svg>
                                    </button>`;

                            if (userRole >= 1) {
                                actionsHtml += `
                                    <button onclick="editRecipe('${recipe.id}'); event.stopPropagation();" title="${translations.edit}">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="${themeParam === 'dark' ? '#ffffff' : '#000000'}" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </button>`;
                            }

                            if (userRole >= 2) {
                                actionsHtml += `
                                    <button onclick="deleteRecipe('${recipe.id}'); event.stopPropagation();" title="${translations.delete}">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="${themeParam === 'dark' ? '#ffffff' : '#000000'}" stroke-width="2">
                                            <path d="M3 6h18"></path>
                                            <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                                            <path d="M10 11v6"></path>
                                            <path d="M14 11v6"></path>
                                        </svg>
                                    </button>`;
                            }

                            actionsHtml += '</div>';

                            card.innerHTML = `
                                <img src="${imageSrc}" alt="${recipe.title[currentLang]}">
                                <h3>${recipe.title[currentLang]}</h3>
                                ${actionsHtml}
                            `;

                            recipeGrid.appendChild(card);
                        });

                        // Update pagination
                        const totalPages = Math.ceil(data.recipes.length / itemsPerPageCount);
                        const paginationDiv = document.getElementById('recipePagination');
                        if (paginationDiv) {
                            if (totalPages > 1) {
                                let paginationHtml = '';
                                if (page > 1) {
                                    paginationHtml += `<a href="?lang=${lang}&theme=${themeParam}&color=${encodeURIComponent(colorParam)}&recipe_page=${page - 1}">&laquo; ${translations.previous}</a>`;
                                }
                                for (let i = 1; i <= totalPages; i++) {
                                    paginationHtml += `<a href="?lang=${lang}&theme=${themeParam}&color=${encodeURIComponent(colorParam)}&recipe_page=${i}" ${i === page ? 'class="active"' : ''}>${i}</a>`;
                                }
                                if (page < totalPages) {
                                    paginationHtml += `<a href="?lang=${lang}&theme=${themeParam}&color=${encodeURIComponent(colorParam)}&recipe_page=${page + 1}">${translations.next} &raquo;</a>`;
                                }
                                paginationDiv.innerHTML = paginationHtml;
                                paginationDiv.style.display = 'flex';
                            } else {
                                paginationDiv.style.display = 'none';
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error refreshing recipes:', error);
                    showNotification(translations.error_occurred, 'error');
                });
        }

        function addStep(data = { type: 'instruction', content: '', timer: null, quantity: null }) {
            const stepsDiv = document.getElementById('steps');
            const stepIndex = steps.length;
            const stepDiv = document.createElement('div');
            stepDiv.className = 'step';
            stepDiv.innerHTML = `
                <div class="step-header">
                    <h4>${translations.step} ${stepIndex + 1}</h4>
                    <button type="button" class="remove-step" onclick="removeStep(${stepIndex})" title="${translations.delete_step}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2">
                            <path d="M18 6L6 18"></path>
                            <path d="M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <button type="button" onclick="openProductSelection(${stepIndex})">${translations.select_product}</button>
                <textarea placeholder="${translations.instruction}">${data.type === 'instruction' ? data.content : ''}</textarea>
                <label><input type="checkbox" ${data.timer ? 'checked' : ''} onchange="toggleTimer(this, ${stepIndex})">${translations.set_timer}</label>
                <div class="timer-input" style="display: ${data.timer ? 'flex' : 'none'};" id="timer-step${stepIndex}">
                    <input type="number" class="timer-hours" min="0" max="99" placeholder="${translations.hours}" value="${data.timer ? data.timer.hours : '0'}" onkeydown="handleTimerKeydown(event, ${stepIndex})" onchange="updateTimer(${stepIndex})">
                    <span>:</span>
                    <input type="number" class="timer-minutes" min="0" max="59" placeholder="${translations.minutes}" value="${data.timer ? data.timer.minutes : '0'}" onkeydown="handleTimerKeydown(event, ${stepIndex})" onchange="updateTimer(${stepIndex})">
                </div>
            `;
            stepsDiv.insertBefore(stepDiv, stepsDiv.lastElementChild);
            steps.push(data);

            if (data.type === 'product') {
                const product = products.find(p => p.id === data.content);
                if (product) {
                    stepDiv.querySelector('textarea').value = product.name[currentLang];
                }
                const qtyContainer = document.createElement('div');
                qtyContainer.className = 'quantity-container';
                qtyContainer.innerHTML = `
                    <label>${translations.quantity}</label>
                    <input type="number" class="quantity-input" min="0" step="0.1" value="${data.quantity?.value || 100}" placeholder="100">
                    <select class="unit-select">
                        <option value="grams" ${data.quantity?.unit === 'grams' ? 'selected' : ''}>${translations.grams}</option>
                        <option value="pieces" ${data.quantity?.unit === 'pieces' ? 'selected' : ''}>${translations.pieces}</option>
                        <option value="teaspoon" ${data.quantity?.unit === 'teaspoon' ? 'selected' : ''}>${translations.teaspoon}</option>
                        <option value="tablespoon" ${data.quantity?.unit === 'tablespoon' ? 'selected' : ''}>${translations.tablespoon}</option>
                    </select>
                `;
                const textarea = stepDiv.querySelector('textarea');
                textarea.parentNode.insertBefore(qtyContainer, textarea.nextSibling);

                if (data.quantity) {
                    qtyContainer.querySelector('.quantity-input').value = data.quantity.value;
                    qtyContainer.querySelector('.unit-select').value = data.quantity.unit || 'grams';
                }
            }

            updateStepIndices();
        }

        function getRecipeFormStepDivs() {
            const stepsDiv = document.getElementById('steps');
            return stepsDiv ? Array.from(stepsDiv.getElementsByClassName('step')) : [];
        }

        function updateStepIndices() {
            const stepDivs = getRecipeFormStepDivs();
            stepDivs.forEach((stepDiv, index) => {
                const header = stepDiv.querySelector('h4');
                if (header) {
                    header.textContent = `${translations.step} ${index + 1}`;
                }

                const selectProductButton = stepDiv.querySelector('button[onclick^="openProductSelection"]');
                if (selectProductButton) {
                    selectProductButton.setAttribute('onclick', `openProductSelection(${index})`);
                }

                const timerCheckbox = stepDiv.querySelector('input[type="checkbox"]');
                if (timerCheckbox) {
                    timerCheckbox.setAttribute('onchange', `toggleTimer(this, ${index})`);
                }
                
                const timerDiv = stepDiv.querySelector('.timer-input');
                if (timerDiv) {
                    timerDiv.id = `timer-step${index}`;
                    const hoursInput = timerDiv.querySelector('.timer-hours');
                    const minutesInput = timerDiv.querySelector('.timer-minutes');
                    if (hoursInput) {
                        hoursInput.setAttribute('onkeydown', `handleTimerKeydown(event, ${index})`);
                        hoursInput.setAttribute('onchange', `updateTimer(${index})`);
                    }
                    if (minutesInput) {
                        minutesInput.setAttribute('onkeydown', `handleTimerKeydown(event, ${index})`);
                        minutesInput.setAttribute('onchange', `updateTimer(${index})`);
                    }
                }
                
                const removeButton = stepDiv.querySelector('.remove-step');
                if (removeButton) {
                    removeButton.setAttribute('onclick', `removeStep(${index})`);
                }
            });
        }

        function handleTimerKeydown(event, stepIndex) {
            const hoursInput = document.querySelector(`#timer-step${stepIndex} .timer-hours`);
            const minutesInput = document.querySelector(`#timer-step${stepIndex} .timer-minutes`);

            if (event.target.classList.contains('timer-hours') && event.key === 'ArrowRight') {
                event.preventDefault();
                minutesInput.focus();
            } else if (event.target.classList.contains('timer-minutes') && event.key === 'ArrowLeft') {
                event.preventDefault();
                hoursInput.focus();
            }
        }

        function openProductSelection(stepIndex) {
            currentStepIndex = stepIndex;
            openModal('productSelectionModal');
            document.getElementById('productSelectionSearch').value = '';
            filterSelectionProducts('');
        }

        function toggleTimer(checkbox, stepIndex) {
            const timerDiv = document.getElementById(`timer-step${stepIndex}`);
            if (checkbox.checked) {
                timerDiv.style.display = 'flex';
                const hoursInput = timerDiv.querySelector('.timer-hours');
                const minutesInput = timerDiv.querySelector('.timer-minutes');
                if (!hoursInput.value) hoursInput.value = 0;
                if (!minutesInput.value) minutesInput.value = 0;
                updateTimer(stepIndex);
            } else {
                timerDiv.style.display = 'none';
                steps[stepIndex].timer = null;
            }
        }

        function editProductName(productId) {
            const product = products.find(p => p.id === productId);
            if (product) {
                resetBackgroundRemovalState('editProduct');
                document.getElementById('editProductId').value = productId;
                document.getElementById('name_en').value = product.name.en;
                document.getElementById('name_uk').value = product.name.uk;
                document.getElementById('name_ru').value = product.name.ru;
                document.getElementById('name_be').value = product.name.be;
                document.getElementById('editProductCategory').value = product.category || '';
                editProductImagePendingFile = null;
                document.getElementById('editProductImageInput').value = '';
                const _epu = document.getElementById('editProductImageUseUrl');
                const _epurl = document.getElementById('editProductImageUrlInput');
                if (_epu) { _epu.checked = false; syncImageSourceMode('editProduct'); }
                if (_epurl) _epurl.value = '';
                setEditProductImageCleared(true);
                setImageSourceSwitchVisible('editProduct', true);
                const previewContainer = document.getElementById('editProductImagePreview');
                const previewImg = document.getElementById('editProductPreviewImg');
                const epFileIn = document.getElementById('editProductImageInput');
                if (product.image) {
                    previewImg.src = productImgFull(product);
                    previewContainer.style.display = 'block';
                    previewImg.dataset.existingSrc = previewImg.src;
                    previewImg.dataset.clickAction = 'crop';
                    previewImg.style.cursor = 'pointer';
                    previewImg.onclick = (event) => {
                        event.stopPropagation();
                        openImageCropper(previewImg.src, epFileIn);
                    };
                    epFileIn.style.display = 'none';
                    setEditProductImageCleared(false);
                    setImageSourceSwitchVisible('editProduct', false);
                } else {
                    previewContainer.style.display = 'none';
                    previewImg.src = '';
                    delete previewImg.dataset.clickAction;
                    delete previewImg.dataset.existingSrc;
                    previewImg.onclick = null;
                    previewImg.style.cursor = '';
                    epFileIn.style.display = 'block';
                    setEditProductImageCleared(true);
                    setImageSourceSwitchVisible('editProduct', true);
                }
                updateBackgroundButtons('editProduct');
                openModal('editProductModal');
            } else {
                showNotification(translations.product_not_found, 'error');
            }
        }

        document.getElementById('editProductForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'edit_product_name');
            if (editProductImagePendingFile) {
                formData.delete('image');
                formData.append('image', editProductImagePendingFile, editProductImagePendingFile.name || 'image.jpg');
            }

            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const result = await parseJsonResponse(response);
                showNotification(result.message, result.success ? 'success' : 'error');
                closeModal('editProductModal');
                if (result.success) {
                    refreshProducts();
                }
            } catch (error) {
                showNotification(translations.error_occurred, 'error');
                closeModal('editProductModal');
            }
        });

        function updateTimer(stepIndex) {
            const timerDiv = document.getElementById(`timer-step${stepIndex}`);
            const hoursInput = timerDiv.querySelector('.timer-hours');
            const minutesInput = timerDiv.querySelector('.timer-minutes');
            
            let hours = parseInt(hoursInput.value) || 0;
            let minutes = parseInt(minutesInput.value) || 0;
            
            hours = Math.max(0, Math.min(hours, 99));
            minutes = Math.max(0, Math.min(minutes, 59));
            
            hoursInput.value = hours;
            minutesInput.value = minutes;
            
            steps[stepIndex].timer = (hours > 0 || minutes > 0) ? { hours, minutes } : null;
        }

        function toggleStepCompletion(recipeId, stepIndex) {
            if (!recipeState.completed_steps[recipeId]) {
                recipeState.completed_steps[recipeId] = [];
            }
            const index = recipeState.completed_steps[recipeId].indexOf(stepIndex);
            if (index === -1) {
                recipeState.completed_steps[recipeId].push(stepIndex);
            } else {
                recipeState.completed_steps[recipeId].splice(index, 1);
            }
            saveRecipeState();
            showRecipe(recipeId);
        }

        function saveRecipeState() {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=save_state&state=${encodeURIComponent(JSON.stringify(recipeState))}`
            })
            .catch(error => {
                console.error('Error saving recipe state:', error);
            });
        }

        function formatTimerDisplay(timer) {
            if (!timer || (timer.hours === 0 && timer.minutes === 0)) return '';
            if (timer.hours === 0) return `(${timer.minutes} ${translations.minutes})`;
            if (timer.minutes === 0) return `(${timer.hours} ${translations.hours})`;
            return `(${timer.hours} ${translations.hours} ${timer.minutes} ${translations.minutes})`;
        }

        function showRecipe(recipeId) {
            recipeState.current_recipe = recipeId;
            saveRecipeState();

            // Show the continue recipe button
            const continueBtn = document.getElementById('continueRecipeBtn');
            if (continueBtn) {
                continueBtn.style.display = 'inline-flex';
            }

            // Fetch full recipe from database
            fetch(`?get_recipe_full=1&recipe_id=${recipeId}`)
                .then(response => parseJsonResponse(response))
                .then(recipe => {
                    if (!recipe || !recipe.success) {
                        showNotification('Failed to load recipe', 'error');
                        return;
                    }
                    
                    const recipeData = recipe.data;
                    const recipeView = document.getElementById('recipeViewContent');
                    document.getElementById('recipeViewModal').style.display = 'flex';
                    document.getElementById('recipeGrid').style.display = 'none';

                    disableBodyScroll();

                    if (window.innerWidth <= 768) {
                        const modal = document.getElementById('recipeViewModal');
                        modal.classList.add('mobile-fullscreen');
                        modal.style.alignItems = 'flex-start';
                        modal.style.padding = '0';

                        const modalContent = modal.querySelector('.modal-content');
                        if (modalContent) {
                            modalContent.style.width = '100vw';
                            modalContent.style.height = '100vh';
                            modalContent.style.maxWidth = 'none';
                            modalContent.style.maxHeight = 'none';
                            modalContent.style.borderRadius = '0';
                            modalContent.style.padding = '50px 15px 15px 15px';
                        }
                    }

                    // Language switch buttons
                    let langButtons = '<div class="language-switcher">';
                    langButtons += '<span>Язык/Language:</span>';
                    langButtons += '<div class="lang-pills">';
                    for (const [langCode, langName] of Object.entries(languages)) {
                        langButtons += `<button class="lang-btn ${currentLang === langCode ? 'active' : ''}" onclick="switchRecipeLanguage('${recipeId}', '${langCode}')">${langName}</button>`;
                    }
                    langButtons += '</div>';
                    langButtons += '</div>';

                    let html = langButtons;
                    html += `<h2>${recipeData.title[currentLang]}</h2>`;

                    const imageSrc = recipeImg(recipeData);
                    html += `<img src="${imageSrc}" alt="${recipeData.title[currentLang]}" class="recipe-image">`;

                    // Ingredients section
                    const ingredients = recipeData.steps.filter(step => step.type === 'product');
                    if (ingredients.length > 0) {
                        html += `<h3>${translations.ingredients}</h3>`;
                        ingredients.forEach((step, ingredientIndex) => {
                            const index = recipeData.steps.indexOf(step);
                            const isCompleted = recipeState.completed_steps[recipeId]?.includes(index);
                            html += `<div class="step" style="display: flex; align-items: flex-start; gap: 10px;">`;
                            html += `<div style="flex-grow: 1;">`;

                            const product = products.find(p => p.id === step.content);
                            if (product) {
                                let productSpan = `<span style="${isCompleted ? 'text-decoration: line-through;' : ''}">${product.name[currentLang]}`;
                                if (step.quantity) {
                                    let unitText = '';
                                    switch(step.quantity.unit) {
                                        case 'grams': unitText = translations.grams; break;
                                        case 'pieces': unitText = translations.pieces; break;
                                        case 'teaspoon': unitText = translations.teaspoon; break;
                                        case 'tablespoon': unitText = translations.tablespoon; break;
                                        default: unitText = step.quantity.unit;
                                    }
                                    productSpan += ` <span class="quantity">(${step.quantity.value} ${unitText})</span>`;
                                }
                                productSpan += `</span>`;
                                const _ipt = productImgTile(product);
                                const _ipf = productImgFull(product);
                                html += `<div class="ingredient-container">
                                    <img src="${_ipt}" data-src-preview="${_ipt}" data-src-full="${_ipf}" alt="${product.name[currentLang]}" class="ingredient-image ${isCompleted ? 'faded' : ''}">
                                    ${productSpan}
                                </div>`;
                            }

                            html += `</div>`;
                            html += `<button class="complete-button" onclick="toggleStepCompletion('${recipeId}', ${index})">`;
                            html += isCompleted
                                ? `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"></path></svg> ${translations.undo}`
                                : `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"></path></svg> ${translations.complete}`;
                            html += `</button>`;
                            html += `</div>`;
                        });
                    }

                    // Instructions section
                    const instructions = recipeData.steps.filter(step => step.type === 'instruction');
                    if (instructions.length > 0) {
                        html += `<h3>${translations.instructions_title}</h3>`;
                        instructions.forEach((step, instructionIndex) => {
                            const index = recipeData.steps.indexOf(step);
                            const isCompleted = recipeState.completed_steps[recipeId]?.includes(index);
                            html += `<div class="step" style="display: flex; align-items: flex-start; gap: 10px;">`;
                            html += `<div style="flex-grow: 1;">`;
                            html += `<span style="${isCompleted ? 'text-decoration: line-through;' : ''}">${translations.step} ${instructionIndex + 1}: </span>`;

                            const instructionText = typeof step.content === 'object' ? (step.content[currentLang] || step.content.en || '') : step.content;
                            html += `<span style="${isCompleted ? 'text-decoration: line-through;' : ''}">${instructionText}</span>`;

                            if (step.timer && (step.timer.hours > 0 || step.timer.minutes > 0)) {
                                html += `<div class="timer-display">${formatTimerDisplay(step.timer)}</div>`;
                            }
                            html += `</div>`;
                            html += `<button class="complete-button" onclick="toggleStepCompletion('${recipeId}', ${index})">`;
                            html += isCompleted
                                ? `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"></path></svg> ${translations.undo}`
                                : `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"></path></svg> ${translations.complete}`;
                            html += `</button>`;
                            html += `</div>`;
                        });
                    }
                    recipeView.innerHTML = html;
                    syncIngredientProductImages();
                })
                .catch(error => {
                    showNotification(translations.error_occurred, 'error');
                });
        }

        function switchRecipeLanguage(recipeId, langCode) {
            fetch(`?get_recipe_full=1&recipe_id=${recipeId}`)
                .then(response => parseJsonResponse(response))
                .then(responseData => {
                    const recipe = responseData.success ? responseData.data : responseData;
                    const recipeView = document.getElementById('recipeViewContent');
                    
                    let langButtons = '<div class="language-switcher">';
                    langButtons += '<span>Язык/Language:</span>';
                    langButtons += '<div class="lang-pills">';
                    for (const [lc, langName] of Object.entries(languages)) {
                        langButtons += `<button class="lang-btn ${langCode === lc ? 'active' : ''}" onclick="switchRecipeLanguage('${recipe.id}', '${lc}')">${langName}</button>`;
                    }
                    langButtons += '</div>';
                    langButtons += '</div>';
                    
                    let html = langButtons;
                    html += `<h2>${recipe.title[langCode] || recipe.title.en}</h2>`;
                    
                    const imageSrc = recipeImg(recipe);
                    html += `<img src="${imageSrc}" alt="${recipe.title[langCode] || recipe.title.en}" class="recipe-image">`;

                    // Ingredients section
                    const ingredients = recipe.steps.filter(step => step.type === 'product');
                    if (ingredients.length > 0) {
                        html += `<h3>${translations.ingredients}</h3>`;
                        ingredients.forEach((step, ingredientIndex) => {
                            const index = recipe.steps.indexOf(step);
                            const isCompleted = recipeState.completed_steps[recipeId]?.includes(index);
                            html += `<div class="step" style="display: flex; align-items: flex-start; gap: 10px;">`;
                            html += `<div style="flex-grow: 1;">`;

                            const product = products.find(p => p.id === step.content);
                            if (product) {
                                let productSpan = `<span style="${isCompleted ? 'text-decoration: line-through;' : ''}">${product.name[langCode] || product.name.en}`;
                                if (step.quantity) {
                                    let unitText = '';
                                    switch(step.quantity.unit) {
                                        case 'grams': unitText = translations.grams; break;
                                        case 'pieces': unitText = translations.pieces; break;
                                        case 'teaspoon': unitText = translations.teaspoon; break;
                                        case 'tablespoon': unitText = translations.tablespoon; break;
                                        default: unitText = step.quantity.unit;
                                    }
                                    productSpan += ` <span class="quantity">(${step.quantity.value} ${unitText})</span>`;
                                }
                                productSpan += `</span>`;
                                const _ipt2 = productImgTile(product);
                                const _ipf2 = productImgFull(product);
                                html += `<div class="ingredient-container">
                                    <img src="${_ipt2}" data-src-preview="${_ipt2}" data-src-full="${_ipf2}" alt="${product.name[langCode] || product.name.en}" class="ingredient-image ${isCompleted ? 'faded' : ''}">
                                    ${productSpan}
                                </div>`;
                            }

                            html += `</div>`;
                            html += `<button class="complete-button" onclick="toggleStepCompletion('${recipeId}', ${index})">`;
                            html += isCompleted
                                ? `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"></path></svg> ${translations.undo}`
                                : `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"></path></svg> ${translations.complete}`;
                            html += `</button>`;
                            html += `</div>`;
                        });
                    }

                    // Instructions section
                    const instructions = recipe.steps.filter(step => step.type === 'instruction');
                    if (instructions.length > 0) {
                        html += `<h3>${translations.instructions_title}</h3>`;
                        instructions.forEach((step, instructionIndex) => {
                            const index = recipe.steps.indexOf(step);
                            const isCompleted = recipeState.completed_steps[recipeId]?.includes(index);
                            html += `<div class="step" style="display: flex; align-items: flex-start; gap: 10px;">`;
                            html += `<div style="flex-grow: 1;">`;
                            html += `<span style="${isCompleted ? 'text-decoration: line-through;' : ''}">${translations.step} ${instructionIndex + 1}: </span>`;

                            const instructionText = typeof step.content === 'object' ? (step.content[langCode] || step.content.en || '') : step.content;
                            html += `<span style="${isCompleted ? 'text-decoration: line-through;' : ''}">${instructionText}</span>`;

                            if (step.timer && (step.timer.hours > 0 || step.timer.minutes > 0)) {
                                html += `<div class="timer-display">${formatTimerDisplay(step.timer)}</div>`;
                            }
                            html += `</div>`;
                            html += `<button class="complete-button" onclick="toggleStepCompletion('${recipeId}', ${index})">`;
                            html += isCompleted
                                ? `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"></path></svg> ${translations.undo}`
                                : `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"></path></svg> ${translations.complete}`;
                            html += `</button>`;
                            html += `</div>`;
                        });
                    }
                    recipeView.innerHTML = html;
                    syncIngredientProductImages();
                });
        }

        function shareRecipe(recipeId, event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            currentShareRecipeId = recipeId;
            openModal('shareModal');
        }

        function editRecipe(recipeId) {
            // Close recipe view modal if open
            const recipeViewModal = document.getElementById('recipeViewModal');
            if (recipeViewModal && recipeViewModal.style.display === 'flex') {
                backToGrid();
            }

            // Clear steps before editing
            steps = [];

            // Fetch full recipe from database
            fetch(`?get_recipe_full=1&recipe_id=${recipeId}`)
                .then(response => parseJsonResponse(response))
                .then(recipe => {
                    if (!recipe || !recipe.success) {
                        showNotification('Failed to load recipe for editing', 'error');
                        return;
                    }
                    
                    const recipeData = recipe.data;
                    currentEditingRecipeSnapshot = cloneJsonData(recipeData);
                    resetBackgroundRemovalState('recipe');
                    setRecipeImageCleared(false);
                    recipeImagePendingFile = null;
                    const _rue = document.getElementById('recipeImageUseUrl');
                    const _rurlEd = document.getElementById('recipeImageUrlInput');
                    if (_rue) { _rue.checked = false; syncImageSourceMode('recipe'); }
                    if (_rurlEd) _rurlEd.value = '';
                    setImageSourceSwitchVisible('recipe', true);
                    setRecipeUploadControlsVisible(true);
                    const _rifEd = document.getElementById('recipeImageInput');
                    const _ripEd = document.getElementById('recipeImagePreview');
                    const _rpiEd = document.getElementById('recipePreviewImg');
                    if (_rifEd) { _rifEd.value = ''; _rifEd.style.display = 'block'; }
                    if (_ripEd && _rpiEd) {
                        if (recipeData.image) {
                            const existingImageSrc = recipeImg(recipeData);
                            _ripEd.style.display = 'block';
                            _rpiEd.src = existingImageSrc;
                            _rpiEd.dataset.existingSrc = existingImageSrc;
                            _rpiEd.dataset.clickAction = 'pick';
                            _rpiEd.style.cursor = 'default';
                            _rpiEd.onclick = null;
                            setRecipeImageCleared(false);
                            setImageSourceSwitchVisible('recipe', false);
                            setRecipeUploadControlsVisible(false);
                        } else {
                            _ripEd.style.display = 'none';
                            _rpiEd.src = '';
                            delete _rpiEd.dataset.clickAction;
                            delete _rpiEd.dataset.existingSrc;
                            setRecipeImageCleared(true);
                            setImageSourceSwitchVisible('recipe', true);
                            setRecipeUploadControlsVisible(true);
                        }
                    }
                    updateBackgroundButtons('recipe');
                    
                    document.getElementById('recipeModal').style.display = 'flex';
                    document.getElementById('recipeModalTitle').textContent = translations.edit_recipe;
                    document.getElementById('recipeFormAction').value = 'edit_recipe';
                    document.getElementById('recipeFormId').value = recipeId;
                    document.getElementById('recipeTitle').value = recipeData.title[currentLang];
                    document.getElementById('recipeLang').value = currentLang;
                    document.getElementById('steps').innerHTML = `<button type="button" onclick="addStep()">${translations.add_step}</button>`;
                    recipeData.steps.forEach(step => {
                        let stepData = { ...step };
                        if (step.type === 'instruction') {
                            stepData.content = typeof step.content === 'object' ? (step.content[currentLang] || step.content.en || step.content) : step.content;
                        }
                        addStep(stepData);
                    });

                    disableBodyScroll();
                })
                .catch(error => {
                    console.error('Error loading recipe for editing:', error);
                    showNotification(translations.error_occurred, 'error');
                });
        }

        function deleteRecipe(recipeId) {
            showDeleteConfirm(translations.confirm_delete_recipe || translations.confirm_delete, () => {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete_recipe&recipe_id=${recipeId}`
            })
            .then(response => parseJsonResponse(response))
            .then(result => {
                if (result.success) {
                    showNotification(result.message, 'success');
                    refreshRecipesGrid();
                } else {
                    showNotification(result.message, 'error');
                }
            })
            .catch(error => {
                showNotification(translations.error_occurred, 'error');
            });
            });
        }

        function backToGrid() {
            const modal = document.getElementById('recipeViewModal');
            modal.style.display = 'none';
            document.getElementById('recipeGrid').style.display = 'grid';
            
            enableBodyScroll();
            
            modal.classList.remove('mobile-fullscreen');
            modal.style.alignItems = '';
            modal.style.padding = '';
            
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
                modalContent.style.width = '';
                modalContent.style.height = '';
                modalContent.style.maxWidth = '';
                modalContent.style.maxHeight = '';
                modalContent.style.borderRadius = '';
                modalContent.style.padding = '';
            }
        }
        
        window.addEventListener('resize', function() {
            const openModals = document.querySelectorAll('.modal[style*="display: flex"]');
            openModals.forEach(modal => {
                if (window.innerWidth <= 768) {
                    modal.classList.add('mobile-fullscreen');
                    modal.style.alignItems = 'flex-start';
                    modal.style.padding = '0';
                    
                    const modalContent = modal.querySelector('.modal-content');
                    if (modalContent) {
                        modalContent.style.width = '100vw';
                        modalContent.style.height = '100vh';
                        modalContent.style.maxWidth = 'none';
                        modalContent.style.maxHeight = 'none';
                        modalContent.style.borderRadius = '0';
                        modalContent.style.padding = '50px 15px 15px 15px';
                    }
                    
                    if (modal.id === 'recipeModal') {
                        const inputs = document.querySelectorAll('#recipeForm input[type="text"], #recipeForm textarea');
                        inputs.forEach(input => {
                            input.style.width = '100%';
                            input.style.boxSizing = 'border-box';
                        });
                        
                        const fileInput = document.querySelector('#recipeForm input[type="file"]');
                        if (fileInput) {
                            fileInput.style.width = '100%';
                        }
                    }
                } else {
                    modal.classList.remove('mobile-fullscreen');
                    modal.style.alignItems = '';
                    modal.style.padding = '';
                    
                    const modalContent = modal.querySelector('.modal-content');
                    if (modalContent) {
                        modalContent.style.width = '';
                        modalContent.style.height = '';
                        modalContent.style.maxWidth = '';
                        modalContent.style.maxHeight = '';
                        modalContent.style.borderRadius = '';
                        modalContent.style.padding = '';
                    }
                    
                    if (modal.id === 'recipeModal') {
                        const inputs = document.querySelectorAll('#recipeForm input[type="text"], #recipeForm textarea');
                        inputs.forEach(input => {
                            input.style.width = '';
                            input.style.boxSizing = '';
                        });
                        
                        const fileInput = document.querySelector('#recipeForm input[type="file"]');
                        if (fileInput) {
                            fileInput.style.width = '';
                        }
                    }
                }
            });
        }); 

        document.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('.modal[style*="display: flex"]');
            modals.forEach(modal => {
                if (event.target === modal && window.innerWidth <= 768) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            });
        }, true);

        function deleteProduct(productId) {
            showDeleteConfirm(translations.confirm_delete, () => {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete_product&product_id=${productId}`
            })
            .then(response => parseJsonResponse(response))
            .then(result => {
                if (result.success) {
                    showNotification(result.message, 'success');
                    refreshProducts();
                } else {
                    showNotification(result.message, 'error');
                }
            })
            .catch(error => {
                showNotification(translations.error_occurred, 'error');
            });
            });
        }

        let productsSortMode = 0; // 0 = no sort, 1 = sort by name, 2 = sort by category then name

        function toggleProductsSort() {
            productsSortMode = (productsSortMode + 1) % 3;
            
            // Update button text
            const sortBtn = document.getElementById('sortProductsBtn');
            const sortText = document.getElementById('sortProductsText');
            const sortModes = ['sort_no', 'sort_by_name', 'sort_by_category'];
            sortText.textContent = translations[sortModes[productsSortMode]] || translations.sort_by_name;
            
            refreshProductGrid();
        }

        function getSortedProducts(productsList) {
            if (productsSortMode === 0) {
                return productsList;
            } else if (productsSortMode === 1) {
                // Sort by name only
                return [...productsList].sort((a, b) => {
                    const nameA = a.name[currentLang].toLowerCase();
                    const nameB = b.name[currentLang].toLowerCase();
                    return nameA.localeCompare(nameB);
                });
            } else if (productsSortMode === 2) {
                // Sort by category then name
                return [...productsList].sort((a, b) => {
                    const catA = (a.category || '').toLowerCase();
                    const catB = (b.category || '').toLowerCase();
                    
                    if (catA !== catB) {
                        return catA.localeCompare(catB);
                    }
                    
                    const nameA = a.name[currentLang].toLowerCase();
                    const nameB = b.name[currentLang].toLowerCase();
                    return nameA.localeCompare(nameB);
                });
            }
            
            return productsList;
        }

        function filterProducts(searchTerm) {
            const productGrid = document.getElementById('productGrid');
            const searchLower = searchTerm.toLowerCase();
            
            if (searchTerm === '') {
                refreshProductGrid();
                const pagination = document.querySelector('#productModal .pagination');
                if (pagination) {
                    pagination.style.display = 'flex';
                }
                return;
            }
            
            const filteredProducts = products.filter(product => {
                const productName = product.name[currentLang].toLowerCase();
                return productName.includes(searchLower);
            });
            
            productGrid.innerHTML = '';
            
            filteredProducts.forEach(product => {
                const card = document.createElement('div');
                card.className = 'product-card';
                card.setAttribute('data-id', product.id);
                card.setAttribute('data-name', product.name[currentLang].toLowerCase());
                card.innerHTML = buildManagementProductCard(product);
                productGrid.appendChild(card);
            });
            
            const pagination = document.querySelector('#productModal .pagination');
            if (pagination) {
                pagination.style.display = 'none';
            }
            bindProductModalEvents();
        }

        function refreshProductGrid() {
            renderProductGrid(products);
        }

        function toggleProductSelection(card) {
            const productId = card.getAttribute('data-id');
            if (selectedSearchProducts.includes(productId)) {
                selectedSearchProducts = selectedSearchProducts.filter(id => id !== productId);
                card.classList.remove('selected');
            } else {
                selectedSearchProducts.push(productId);
                card.classList.add('selected');
            }
            updateSelectedProducts();
        }

        function updateSelectedProducts() {
            const selectedContainer = document.getElementById('selectedProducts');
            selectedContainer.innerHTML = `<h3>${translations.selected_products}</h3>`;
            const grid = document.createElement('div');
            grid.className = 'selected-products-grid';
            selectedSearchProducts.forEach(productId => {
                const product = products.find(p => p.id === productId);
                if (product) {
                    const productDiv = document.createElement('div');
                    productDiv.className = 'product-card';
                    productDiv.innerHTML = `
                        <img src="${productImgTile(product)}" alt="${product.name[currentLang]}">
                        <p>${product.name[currentLang]}</p>
                    `;
                    grid.appendChild(productDiv);
                }
            });
            selectedContainer.appendChild(grid);
        }
        
        function changeSearchPage(page) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('search_page', page);
            
            fetch(`${window.location.pathname}?${urlParams}`)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    const newGrid = doc.getElementById('searchProductGrid');
                    const newPagination = doc.querySelector('#searchRecipeModal .pagination');
                    
                    if (newGrid && newPagination) {
                        document.getElementById('searchProductGrid').innerHTML = newGrid.innerHTML;
                        document.querySelector('#searchRecipeModal .pagination').innerHTML = newPagination.innerHTML;
                        
                        // Restore selection
                        const productCards = document.querySelectorAll('#searchProductGrid .product-card');
                        productCards.forEach(card => {
                            const productId = card.getAttribute('data-id');
                            if (selectedSearchProducts.includes(productId)) {
                                card.classList.add('selected');
                            }
                            card.onclick = () => toggleProductSelection(card);
                        });
                        
                        window.history.pushState({}, '', `${window.location.pathname}?${urlParams}`);
                    }
                })
                .catch(error => {
                    console.error('Error changing search page:', error);
                    showNotification(translations.error_occurred, 'error');
                });
        }

        function changeProductPage(page) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('product_page', page);
            window.history.replaceState({}, '', `${window.location.pathname}?${urlParams}`);
            renderProductGrid(products);
        }

        function changeSelectPage(page) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('select_page', page);
            
            const searchInput = document.getElementById('productSelectionSearch');
            const searchTerm = searchInput ? searchInput.value : '';
            
            fetch(`${window.location.pathname}?${urlParams}`)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    const newGrid = doc.getElementById('productSelectionGrid');
                    const newPagination = doc.querySelector('#productSelectionModal .pagination');
                    
                    if (newGrid && newPagination) {
                        document.getElementById('productSelectionGrid').innerHTML = newGrid.innerHTML;
                        document.querySelector('#productSelectionModal .pagination').innerHTML = newPagination.innerHTML;
                        
                        if (searchInput && searchTerm) {
                            searchInput.value = searchTerm;
                            filterSelectionProducts(searchTerm);
                        } else {
                            bindSelectionModalEvents();
                        }
                        
                        window.history.pushState({}, '', `${window.location.pathname}?${urlParams}`);
                    }
                })
                .catch(error => {
                    console.error('Error changing select page:', error);
                    showNotification(translations.error_occurred, 'error');
                });
        }

        function loadModalContent(modalId, pageParam, page, gridId) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set(pageParam, page);
            
            const grid = document.getElementById(gridId);
            const oldContent = grid.innerHTML;
            grid.innerHTML = '<div class="loading">Загрузка...</div>';
            
            fetch(`${window.location.pathname}?${urlParams}`)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    const newGrid = doc.getElementById(gridId);
                    const newPagination = doc.querySelector(`.modal#${modalId} .pagination`);
                    
                    if (newGrid && newPagination) {
                        grid.innerHTML = newGrid.innerHTML;
                        
                        // RESTORE SELECTION FOR SELECTED PRODUCTS
                        if (modalId === 'searchRecipeModal') {
                            const productCards = document.querySelectorAll('#searchProductGrid .product-card');
                            productCards.forEach(card => {
                                const productId = card.getAttribute('data-id');
                                if (selectedSearchProducts.includes(productId)) {
                                    card.classList.add('selected');
                                }
                                
                                // Override click handler
                                card.onclick = () => toggleProductSelection(card);
                            });
                        }
                        
                        const paginationContainer = document.querySelector(`.modal#${modalId} .pagination`);
                        if (paginationContainer) {
                            paginationContainer.innerHTML = newPagination.innerHTML;
                        }
                        
                        if (modalId === 'searchRecipeModal') {
                            bindSearchModalEvents();
                        } else if (modalId === 'productModal') {
                            bindProductModalEvents();
                        } else if (modalId === 'productSelectionModal') {
                            bindSelectionModalEvents();
                            const newSearchInput = doc.getElementById('productSelectionSearch');
                            if (newSearchInput) {
                                const currentSearchInput = document.getElementById('productSelectionSearch');
                                if (currentSearchInput) {
                                    currentSearchInput.value = newSearchInput.value || '';
                                }
                            }
                        }
                    } else {
                        grid.innerHTML = oldContent;
                        showNotification('Ошибка загрузки данных', 'error');
                    }
                })
                .catch(error => {
                    grid.innerHTML = oldContent;
                    showNotification('Ошибка загрузки данных', 'error');
                    console.error('Error loading modal content:', error);
                });
        }

        function bindSearchModalEvents() {
            const productCards = document.querySelectorAll('#searchProductGrid .product-card');
            productCards.forEach(card => {
                // Restore selected class when binding events
                const productId = card.getAttribute('data-id');
                if (selectedSearchProducts.includes(productId)) {
                    card.classList.add('selected');
                }
                
                card.onclick = () => toggleProductSelection(card);
            });
        }

        function bindProductModalEvents() {
            const deleteButtons = document.querySelectorAll('#productGrid .product-card-actions button[title="' + translations.delete + '"]');
            deleteButtons.forEach(button => {
                const productId = button.closest('.product-card').getAttribute('data-id');
                button.onclick = (e) => {
                    e.stopPropagation();
                    deleteProduct(productId);
                };
            });

            const editButtons = document.querySelectorAll('#productGrid .product-card-actions button[title="' + translations.edit_name + '"]');
            editButtons.forEach(button => {
                const productId = button.closest('.product-card').getAttribute('data-id');
                button.onclick = (e) => {
                    e.stopPropagation();
                    editProductName(productId);
                };
            });

            document.querySelectorAll('#productGrid .product-card').forEach(card => {
                card.onclick = (ev) => {
                    if (ev.target.closest('.product-card-actions')) return;
                    const id = card.getAttribute('data-id');
                    if (id) openProductViewModal(id);
                };
            });
        }

        function filterSelectionProducts(searchTerm) {
            const productGrid = document.getElementById('productSelectionGrid');
            const searchLower = searchTerm.toLowerCase();
            
            if (!searchTerm.trim()) {
                refreshSelectionProductGrid();
                const pagination = document.querySelector('#productSelectionModal .pagination');
                if (pagination) {
                    pagination.style.display = 'flex';
                }
                return;
            }
            
            const filteredProducts = products.filter(product => {
                const productName = product.name[currentLang].toLowerCase();
                return productName.includes(searchLower);
            });
            
            productGrid.innerHTML = '';
            
            filteredProducts.forEach(product => {
                const image_src = productImgTile(product);
                const card = document.createElement('div');
                card.className = 'product-card';
                card.setAttribute('data-id', product.id);
                card.setAttribute('data-name', product.name[currentLang].toLowerCase());
                card.innerHTML = `
                    <img src="${image_src}" alt="${product.name[currentLang]}">
                    <p>${product.name[currentLang]}</p>
                `;
                
                card.onclick = () => applyProductSelection(product);
                
                productGrid.appendChild(card);
            });
            
            const pagination = document.querySelector('#productSelectionModal .pagination');
            if (pagination) {
                pagination.style.display = 'none';
            }
        }

        function refreshSelectionProductGrid() {
            const productGrid = document.getElementById('productSelectionGrid');
            const select_page = <?php echo $select_page; ?>;
            const start = (select_page - 1) * itemsPerPage;
            const paged_products = products.slice(start, start + itemsPerPage);
            
            productGrid.innerHTML = '';
            
            paged_products.forEach(product => {
                const image_src = productImgTile(product);
                const card = document.createElement('div');
                card.className = 'product-card';
                card.setAttribute('data-id', product.id);
                card.setAttribute('data-name', product.name[currentLang].toLowerCase());
                card.innerHTML = `
                    <img src="${image_src}" alt="${product.name[currentLang]}">
                    <p>${product.name[currentLang]}</p>
                `;
                
                card.onclick = () => applyProductSelection(product);
                
                productGrid.appendChild(card);
            });
        }

        function bindSelectionModalEvents() {
            const productCards = document.querySelectorAll('#productSelectionGrid .product-card');
            productCards.forEach(card => {
                const productId = card.getAttribute('data-id');
                const product = products.find(p => p.id === productId);
                
                card.onclick = () => applyProductSelection(product);
            });
            
            const searchInput = document.getElementById('productSelectionSearch');
            if (searchInput) {
                searchInput.oninput = function() {
                    filterSelectionProducts(this.value);
                };
            }
        }

        function removeStep(stepIndex) {
            steps.splice(stepIndex, 1);
            
            const stepDivs = getRecipeFormStepDivs();
            if (stepDivs[stepIndex]) {
                stepDivs[stepIndex].remove();
            }
            
            updateStepIndices();
        }

        async function searchRecipesByProducts() {
            const resultsDiv = document.getElementById('searchResults');
            resultsDiv.innerHTML = '<div class="loading">Загрузка...</div>';
            const matchingRecipes = [];
            const partialRecipes = [];

            if (selectedSearchProducts.length === 0) {
                resultsDiv.innerHTML = `<p>${translations.no_matching_recipes}</p>`;
                return;
            }

            try {
                for (const recipe of recipes) {
                    await ensureRecipeSteps(recipe);
                    const uniqueRequiredProducts = getComparableProductIds(recipe);
                    const missingProducts = uniqueRequiredProducts.filter(productId => !selectedSearchProducts.includes(productId));

                    if (uniqueRequiredProducts.length === 0) {
                        continue;
                    }

                    if (missingProducts.length === 0) {
                        matchingRecipes.push(recipe);
                    } else if (missingProducts.length <= 2) {
                        partialRecipes.push({ recipe, missingProducts });
                    }
                }
            } catch (error) {
                console.error('Error searching recipes by products:', error);
                resultsDiv.innerHTML = '';
                showNotification(translations.error_occurred, 'error');
                return;
            }

            resultsDiv.innerHTML = '';

            if (matchingRecipes.length > 0) {
                resultsDiv.innerHTML = `<h3>${translations.matching_recipes}</h3>`;
                const table = document.createElement('table');
                table.className = 'results-table';
                table.innerHTML = `<thead><tr><th>${translations.recipe_title}</th></tr></thead><tbody>`;
                matchingRecipes.forEach(recipe => {
                    table.innerHTML += `
                        <tr>
                            <td><a href="#" onclick="closeModal('searchRecipeModal'); showRecipe('${recipe.id}'); return false;">${recipe.title[currentLang]}</a></td>
                        </tr>
                    `;
                });
                table.innerHTML += `</tbody>`;
                resultsDiv.appendChild(table);
            } else {
                resultsDiv.innerHTML = `<p>${translations.no_matching_recipes}</p>`;
            }

            if (partialRecipes.length > 0) {
                resultsDiv.innerHTML += `<h3>${translations.partial_matches}</h3>`;
                const table = document.createElement('table');
                table.className = 'results-table';
                table.innerHTML = `<thead><tr><th>${translations.recipe_title}</th><th>${translations.missing}</th></tr></thead><tbody>`;
                partialRecipes.forEach(({ recipe, missingProducts }) => {
                    const missingNames = missingProducts.map(id => {
                        const product = products.find(p => p.id === id);
                        return product ? product.name[currentLang] : id;
                    });
                    table.innerHTML += `
                        <tr>
                            <td><a href="#" onclick="closeModal('searchRecipeModal'); showRecipe('${recipe.id}'); return false;">${recipe.title[currentLang]}</a></td>
                            <td>${missingNames.join(', ')}</td>
                        </tr>
                    `;
                });
                table.innerHTML += `</tbody>`;
                resultsDiv.appendChild(table);
            }
        }

        document.getElementById('productForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (isTranslating) return;
            
            isTranslating = true;
            document.getElementById('loading').style.display = 'block';
            disableProductFormButton();
            
            const formData = new FormData(e.target);
            document.getElementById('productFormLang').value = document.getElementById('productLang').value;
            const _pfi = document.getElementById('productImageInput');
            const _hasPi = productImagePendingFile || (_pfi && _pfi.files && _pfi.files.length > 0);
            if (!_hasPi) {
                showNotification(translations.image_required, 'error');
                document.getElementById('loading').style.display = 'none';
                enableProductFormButton();
                isTranslating = false;
                return;
            }
            if (productImagePendingFile) {
                formData.delete('image');
                formData.append('image', productImagePendingFile, productImagePendingFile.name || 'image.jpg');
            }
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const result = await parseJsonResponse(response);
                
                if (result.success) {
                    showNotification(result.message, 'success');
                    e.target.reset();
                    productImagePendingFile = null;
                    const _pcu = document.getElementById('productImageUseUrl');
                    const _purlv = document.getElementById('productImageUrlInput');
                    if (_pcu) { _pcu.checked = false; syncImageSourceMode('product'); }
                    if (_purlv) _purlv.value = '';
                    document.getElementById('productImagePreview').style.display = 'none';
                    document.getElementById('productPreviewImg').src = '';
                    if (_pfi) { _pfi.style.display = 'block'; }
                    closeModal('addProductModal');
                    refreshProducts();
                } else {
                    if (result.duplicate) {
                        // Show warning in a styled blockquote
                        const warningDiv = document.createElement('div');
                        warningDiv.style.cssText = `
                            background-color: rgba(255, 255, 0, 0.3);
                            border-left: 5px solid #ff0;
                            padding: 10px;
                            margin: 10px 0;
                            font-style: italic;
                        `;
                        warningDiv.innerHTML = `<blockquote>${result.message}</blockquote>`;
                        const form = e.target;
                        const submitBtn = form.querySelector('button[type="submit"]');
                        form.insertBefore(warningDiv, submitBtn);
                        setTimeout(() => warningDiv.remove(), 5000);
                    } else {
                        showNotification(result.message, 'error');
                    }
                }
            } catch (error) {
                showNotification(translations.error_occurred, 'error');
            } finally {
                document.getElementById('loading').style.display = 'none';
                enableProductFormButton();
                isTranslating = false;
            }
        });

        document.getElementById('settingsForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const itemsPerPage = document.getElementById('itemsPerPage').value;
            const config = { items_per_page: parseInt(itemsPerPage) };
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=save_config&config=${encodeURIComponent(JSON.stringify(config))}`
                });
                const result = await parseJsonResponse(response);
                if (result.success) {
                    showNotification(result.message, 'success');
                    closeModal('settingsModal');
                    // Update itemsPerPage variable without reload
                    window.itemsPerPageConfig = parseInt(itemsPerPage);
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                showNotification(translations.error_occurred, 'error');
            }
        });

        document.getElementById('productLang').addEventListener('change', function() {
            document.getElementById('productFormLang').value = this.value;
        });

        document.getElementById('recipeLang').addEventListener('change', function() {
            document.getElementById('recipeFormLang').value = this.value;
        });

        function cropperPrefixFromInput(input) {
            const id = input && input.id;
            if (id === 'recipeImageInput') return 'recipe';
            if (id === 'productImageInput') return 'product';
            if (id === 'editProductImageInput') return 'editProduct';
            return 'recipe';
        }

        const imageFormPreviewIds = {
            recipe: ['recipeImagePreview', 'recipePreviewImg'],
            product: ['productImagePreview', 'productPreviewImg'],
            editProduct: ['editProductImagePreview', 'editProductPreviewImg']
        };

        function resetBackgroundRemovalState(prefix) {
            const state = backgroundRemovalState[prefix];
            if (!state) return;
            state.originalFile = null;
            state.removed = false;
            state.busy = false;
            updateBackgroundButtons(prefix);
        }

        function updateBackgroundButtons(prefix) {
            const state = backgroundRemovalState[prefix];
            const ids = imageFormPreviewIds[prefix];
            if (!state || !ids) return;
            const previewBox = document.getElementById(ids[0]);
            const previewVisible = !!(previewBox && previewBox.style.display !== 'none');
            const actions = document.getElementById(prefix + 'BgActions');
            const removeBtn = document.getElementById(prefix + 'BgRemoveBtn');
            const undoBtn = document.getElementById(prefix + 'BgUndoBtn');

            if (removeBtn) removeBtn.style.display = state.removed ? 'none' : 'inline-flex';
            if (undoBtn) undoBtn.style.display = state.removed ? 'inline-flex' : 'none';
            if (removeBtn) removeBtn.disabled = state.busy;
            if (undoBtn) undoBtn.disabled = state.busy;
            const hasVisibleAction = (removeBtn && removeBtn.style.display !== 'none') || (undoBtn && undoBtn.style.display !== 'none');
            if (actions) actions.style.display = previewVisible && hasVisibleAction ? 'flex' : 'none';
        }

        async function getCurrentImageFileForPrefix(prefix) {
            if (prefix === 'recipe' && recipeImagePendingFile) return recipeImagePendingFile;
            if (prefix === 'product' && productImagePendingFile) return productImagePendingFile;
            if (prefix === 'editProduct' && editProductImagePendingFile) return editProductImagePendingFile;

            const ids = imageFormPreviewIds[prefix];
            if (!ids) return null;
            const previewImg = document.getElementById(ids[1]);
            if (!previewImg || !previewImg.src) return null;
            const response = await fetch(previewImg.src);
            const blob = await response.blob();
            const ext = blob.type === 'image/png' ? 'png' : blob.type === 'image/webp' ? 'webp' : 'jpg';
            return new File([blob], `${prefix}-image.${ext}`, { type: blob.type || 'image/jpeg' });
        }

        function base64ToFile(base64, mime, filename) {
            const bin = atob(base64);
            const bytes = new Uint8Array(bin.length);
            for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
            const blob = new Blob([bytes], { type: mime });
            return new File([blob], filename, { type: mime });
        }

        function setImageOverlay(prefix, visible, text = 'Removing background...') {
            const overlay = document.getElementById(prefix + 'ImageOverlay');
            if (!overlay) return;
            const label = overlay.querySelector('span');
            if (label) label.textContent = text;
            overlay.style.display = visible ? 'flex' : 'none';
        }

        function setRecipeImageCleared(cleared) {
            const flag = document.getElementById('recipeClearImageFlag');
            if (flag) flag.value = cleared ? '1' : '0';
            const btn = document.getElementById('recipeImageClearBtn');
            if (btn) btn.style.display = cleared ? 'none' : 'inline-flex';
        }

        function setEditProductImageCleared(cleared) {
            const flag = document.getElementById('editProductClearImageFlag');
            if (flag) flag.value = cleared ? '1' : '0';
            const btn = document.getElementById('editProductImageClearBtn');
            if (btn) btn.style.display = cleared ? 'none' : 'inline-flex';
        }

        function setImageSourceSwitchVisible(prefix, visible) {
            const map = {
                recipe: 'recipeImageSourceSwitchRow',
                editProduct: 'editProductImageSourceSwitchRow'
            };
            const rowId = map[prefix];
            if (!rowId) return;
            const row = document.getElementById(rowId);
            if (prefix === 'recipe') {
                const isEdit = document.getElementById('recipeFormAction')?.value === 'edit_recipe';
                const hasExisting = !!document.getElementById('recipePreviewImg')?.dataset.existingSrc;
                const isCleared = document.getElementById('recipeClearImageFlag')?.value === '1';
                if (isEdit && hasExisting && !isCleared) visible = false;
            }
            if (row) row.style.display = visible ? 'flex' : 'none';
        }
        function setRecipeUploadControlsVisible(visible) {
            const fileRow = document.getElementById('recipeImageFileRow');
            const urlRow = document.getElementById('recipeImageUrlRow');
            if (fileRow) fileRow.style.display = visible ? 'block' : 'none';
            if (urlRow) urlRow.style.display = 'none';
            if (!visible) {
                const useUrl = document.getElementById('recipeImageUseUrl');
                if (useUrl) useUrl.checked = false;
                const urlInput = document.getElementById('recipeImageUrlInput');
                if (urlInput) urlInput.value = '';
            }
        }

        function triggerImagePicker(prefix) {
            const useUrl = !!document.getElementById(prefix + 'ImageUseUrl')?.checked;
            if (useUrl) return;
            const input = document.getElementById(prefix + 'ImageInput');
            if (input) input.click();
        }

        function syncImageSourceMode(prefix) {
            const cb = document.getElementById(prefix + 'ImageUseUrl');
            if (!cb) return;
            const useUrl = cb.checked;
            const fr = document.getElementById(prefix + 'ImageFileRow');
            const ur = document.getElementById(prefix + 'ImageUrlRow');
            if (fr) fr.style.display = useUrl ? 'none' : 'block';
            if (ur) ur.style.display = useUrl ? 'block' : 'none';
            const fin = document.getElementById(prefix + 'ImageInput');
            if (fin && prefix === 'product') fin.required = !useUrl;
        }

        function clearImageForm(prefix) {
            const fin = document.getElementById(prefix + 'ImageInput');
            const useUrl = !!document.getElementById(prefix + 'ImageUseUrl')?.checked;
            if (fin) { fin.value = ''; fin.style.display = useUrl ? 'none' : 'block'; }
            const ids = imageFormPreviewIds[prefix];
            if (ids) {
                const previewBox = document.getElementById(ids[0]);
                const previewImg = document.getElementById(ids[1]);
                const existingSrc = previewImg ? previewImg.dataset.existingSrc || '' : '';
                const isEditingRecipe = prefix === 'recipe' && document.getElementById('recipeFormAction')?.value === 'edit_recipe';
                if (isEditingRecipe && existingSrc) {
                    previewBox.style.display = 'block';
                    previewImg.src = existingSrc;
                    previewImg.style.cursor = 'default';
                    previewImg.onclick = null;
                    previewImg.dataset.clickAction = 'pick';
                    setRecipeImageCleared(false);
                    setImageSourceSwitchVisible('recipe', false);
                    setRecipeUploadControlsVisible(false);
                } else {
                    previewBox.style.display = 'none';
                    previewImg.src = '';
                    previewImg.onclick = null;
                    delete previewImg.dataset.clickAction;
                    delete previewImg.dataset.existingSrc;
                    if (prefix === 'recipe') {
                        setRecipeImageCleared(false);
                        setImageSourceSwitchVisible('recipe', true);
                        setRecipeUploadControlsVisible(true);
                    }
                    if (prefix === 'editProduct') {
                        setEditProductImageCleared(true);
                        setImageSourceSwitchVisible('editProduct', true);
                    }
                }
            }
            if (prefix === 'recipe') recipeImagePendingFile = null;
            else if (prefix === 'product') productImagePendingFile = null;
            else if (prefix === 'editProduct') editProductImagePendingFile = null;
            setImageOverlay(prefix, false);
            updateBackgroundButtons(prefix);
        }

        function applyImageFileToForm(file, prefix, options = {}) {
            const fileInput = document.getElementById(prefix + 'ImageInput');
            const ids = imageFormPreviewIds[prefix];
            const dt = new DataTransfer();
            dt.items.add(file);
            fileInput.files = dt.files;
            if (prefix === 'recipe') recipeImagePendingFile = file;
            else if (prefix === 'product') productImagePendingFile = file;
            else if (prefix === 'editProduct') editProductImagePendingFile = file;
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(ids[0]).style.display = 'block';
                const img = document.getElementById(ids[1]);
                img.src = e.target.result;
                delete img.dataset.existingSrc;
                img.dataset.clickAction = 'crop';
                img.style.cursor = 'pointer';
                img.onclick = (event) => {
                    event.stopPropagation();
                    openImageCropper(e.target.result, fileInput);
                };
                fileInput.style.display = 'none';
                if (prefix === 'recipe') setRecipeImageCleared(false);
                if (prefix === 'editProduct') setEditProductImageCleared(false);
                if (prefix === 'recipe' || prefix === 'editProduct') setImageSourceSwitchVisible(prefix, true);
                updateBackgroundButtons(prefix);
                if (prefix === 'product' && !options.fromBgRemoval && canAutoRemoveBackground(file)) {
                    removeBackgroundForPrefix(prefix, { automatic: true, originalFile: file });
                }
            };
            reader.readAsDataURL(file);
        }

        function canAutoRemoveBackground(file) {
            if (!file || !file.type) return false;
            return ['image/jpeg', 'image/png', 'image/webp'].includes(file.type) && file.size <= 5 * 1024 * 1024;
        }

        function handleImagePreview(input) {
            const prefix = cropperPrefixFromInput(input);
            const file = input.files[0];
            if (!file) {
                clearImageForm(prefix);
                return;
            }
            applyImageFileToForm(file, prefix);
        }

        async function loadImageFromUrl(prefix) {
            const urlEl = document.getElementById(prefix + 'ImageUrlInput');
            const url = (urlEl && urlEl.value || '').trim();
            if (!url) {
                showNotification(translations.image_url_invalid, 'error');
                return;
            }
            document.getElementById('loading').style.display = 'block';
            try {
                const r = await fetch('fetch_image_url.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ url: url }),
                    credentials: 'same-origin'
                });
                const j = await parseJsonResponse(r);
                if (!j.success) {
                    let msg = translations.image_url_fetch_failed;
                    if (j.message === 'invalid_url') msg = translations.image_url_invalid;
                    else if (j.message === 'not_image') msg = translations.image_url_not_image;
                    showNotification(msg, 'error');
                    return;
                }
                const bin = atob(j.data);
                const bytes = new Uint8Array(bin.length);
                for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
                const blob = new Blob([bytes], { type: j.mime || 'image/jpeg' });
                const file = new File([blob], j.filename || 'fetched.jpg', { type: j.mime || 'image/jpeg' });
                applyImageFileToForm(file, prefix);
            } catch (err) {
                showNotification(translations.image_url_fetch_failed, 'error');
            } finally {
                document.getElementById('loading').style.display = 'none';
            }
        }

        async function removeBackgroundForPrefix(prefix, options = {}) {
            const state = backgroundRemovalState[prefix];
            if (!state || state.busy) return;
            const file = options.originalFile || await getCurrentImageFileForPrefix(prefix);
            if (!file) {
                showNotification(translations.error_occurred, 'error');
                return;
            }

            if (!state.originalFile || !state.removed) {
                state.originalFile = file;
            }
            state.busy = true;
            updateBackgroundButtons(prefix);
            setImageOverlay(prefix, true);

            try {
                const formData = new FormData();
                formData.append('image', file, file.name || 'image.jpg');
                const response = await fetch('remove_background.php', {
                    method: 'POST',
                    body: formData
                });
                const rawText = await response.text();
                let result;
                try {
                    result = JSON.parse(rawText);
                } catch (e) {
                    throw new Error(`background_remove_invalid_response: ${rawText.slice(0, 300)}`);
                }
                if (!result || !result.success || !result.data) {
                    throw new Error(result?.details || result?.message || 'background_remove_failed');
                }

                const processedFile = base64ToFile(result.data, result.mime || 'image/png', result.filename || 'background-removed.png');
                applyImageFileToForm(processedFile, prefix, { fromBgRemoval: true });
                state.removed = true;
                if (!options.automatic) {
                    showNotification('Background removed', 'success');
                }
            } catch (error) {
                console.error('Background removal failed:', error);
                if (!options.automatic) {
                    showNotification(translations.error_occurred, 'error');
                }
            } finally {
                state.busy = false;
                setImageOverlay(prefix, false);
                updateBackgroundButtons(prefix);
            }
        }

        function undoBackgroundRemoval(prefix) {
            const state = backgroundRemovalState[prefix];
            if (!state || !state.originalFile) return;
            applyImageFileToForm(state.originalFile, prefix, { fromBgRemoval: true });
            state.removed = false;
            updateBackgroundButtons(prefix);
        }

        function clearExistingImage(prefix, event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            const ids = imageFormPreviewIds[prefix];
            if (!ids) return;
            const preview = document.getElementById(ids[0]);
            const previewImg = document.getElementById(ids[1]);
            const fileInput = document.getElementById(prefix + 'ImageInput');
            const useUrl = !!document.getElementById(prefix + 'ImageUseUrl')?.checked;
            if (preview) preview.style.display = 'none';
            if (previewImg) {
                previewImg.src = '';
                delete previewImg.dataset.existingSrc;
                delete previewImg.dataset.clickAction;
                previewImg.onclick = null;
            }
            if (fileInput) {
                fileInput.value = '';
                fileInput.style.display = useUrl ? 'none' : 'block';
            }
            if (prefix === 'recipe') {
                recipeImagePendingFile = null;
                setRecipeImageCleared(true);
                setImageSourceSwitchVisible('recipe', true);
                setRecipeUploadControlsVisible(true);
            } else if (prefix === 'editProduct') {
                editProductImagePendingFile = null;
                setEditProductImageCleared(true);
                setImageSourceSwitchVisible('editProduct', true);
            } else if (prefix === 'product') {
                productImagePendingFile = null;
            }
            resetBackgroundRemovalState(prefix);
            updateBackgroundButtons(prefix);
        }

        function clearExistingRecipeImage(event) {
            clearExistingImage('recipe', event);
        }

        function handleImageDrop(event, prefix) {
            event.preventDefault();
            event.stopPropagation();
            const container = event.currentTarget;
            if (container) container.classList.remove('dragover');
            const files = Array.from(event.dataTransfer?.files || []);
            const file = files.find(f => f.type && f.type.startsWith('image/'));
            if (!file) return;
            applyImageFileToForm(file, prefix);
        }

        function bindImageDropzone(prefix) {
            const ids = imageFormPreviewIds[prefix];
            const fileRow = document.getElementById(prefix + 'ImageFileRow');
            const previewBox = ids ? document.getElementById(ids[0]) : null;
            [fileRow, previewBox].filter(Boolean).forEach(container => {
                container.addEventListener('dragover', function(event) {
                    event.preventDefault();
                    container.classList.add('dragover');
                });
                container.addEventListener('dragleave', function() {
                    container.classList.remove('dragover');
                });
                container.addEventListener('drop', function(event) {
                    handleImageDrop(event, prefix);
                });
            });
        }

        function modalIsOpen(modalId) {
            return document.getElementById(modalId)?.style.display === 'flex';
        }

        function hasImageInDataTransfer(dt) {
            const files = Array.from(dt?.files || []);
            return files.some(f => f.type && f.type.startsWith('image/'));
        }

        function getActiveImageDropPrefix() {
            if (modalIsOpen('editProductModal') && !document.getElementById('editProductImageUseUrl')?.checked) return 'editProduct';
            if (modalIsOpen('addProductModal') && !document.getElementById('productImageUseUrl')?.checked) return 'product';
            if (modalIsOpen('recipeModal') && !document.getElementById('recipeImageUseUrl')?.checked) return 'recipe';
            return null;
        }

        function handleGlobalImageDrop(event) {
            if (!hasImageInDataTransfer(event.dataTransfer)) return;
            event.preventDefault();
            event.stopPropagation();
            const prefix = getActiveImageDropPrefix();
            if (!prefix) return;
            const file = Array.from(event.dataTransfer.files || []).find(f => f.type && f.type.startsWith('image/'));
            if (!file) return;
            applyImageFileToForm(file, prefix);
        }

        function bindImageSourceControls(prefix) {
            const cb = document.getElementById(prefix + 'ImageUseUrl');
            const btn = document.getElementById(prefix + 'ImageUrlLoadBtn');
            const urlIn = document.getElementById(prefix + 'ImageUrlInput');
            if (cb) {
                cb.addEventListener('change', function() {
                    if (cb.checked) {
                        clearImageForm(prefix);
                    } else if (urlIn) {
                        urlIn.value = '';
                        clearImageForm(prefix);
                    }
                    syncImageSourceMode(prefix);
                });
            }
            if (btn) btn.addEventListener('click', function() { loadImageFromUrl(prefix); });
            if (urlIn) {
                urlIn.addEventListener('paste', function() {
                    setTimeout(function() { loadImageFromUrl(prefix); }, 0);
                });
                urlIn.addEventListener('input', function() {
                    clearTimeout(imageUrlLoadTimers[prefix]);
                    const value = (urlIn.value || '').trim();
                    if (!value) return;
                    imageUrlLoadTimers[prefix] = setTimeout(function() {
                        loadImageFromUrl(prefix);
                    }, 450);
                });
                urlIn.addEventListener('change', function() {
                    const value = (urlIn.value || '').trim();
                    if (value) loadImageFromUrl(prefix);
                });
            }
        }

        function openImageCropper(imageSrc, fileInput) {
            document.getElementById('cropperImage').src = imageSrc;
            window.cropperFileInput = fileInput;
            window.currentCropperTarget = cropperPrefixFromInput(fileInput);
            openModal('imageCropperModal');
            setTimeout(() => initCropperInstance(), 120);
        }

        function initCropperInstance() {
            const cropperImage = document.getElementById('cropperImage');
            const selection = document.getElementById('cropSelection');
            if (!cropperImage || !selection) return;
            if (cropperUiAbort) {
                cropperUiAbort.abort();
                cropperUiAbort = null;
            }
            const ac = new AbortController();
            cropperUiAbort = ac;
            const signal = ac.signal;
            const crop = { x: 0, y: 0, w: 0, h: 0 };
            let isDragging = false;
            let d0x, d0y, c0x, c0y;

            function updateCropSelection() {
                selection.style.left = crop.x + 'px';
                selection.style.top = crop.y + 'px';
                selection.style.width = crop.w + 'px';
                selection.style.height = crop.h + 'px';
            }

            function layoutFullImage() {
                const iw = cropperImage.offsetWidth;
                const ih = cropperImage.offsetHeight;
                crop.x = 0;
                crop.y = 0;
                crop.w = iw;
                crop.h = ih;
                updateCropSelection();
            }

            function onReady() {
                requestAnimationFrame(layoutFullImage);
            }
            cropperImage.addEventListener('load', onReady, { once: true });
            if (cropperImage.complete && cropperImage.naturalWidth) onReady();

            selection.addEventListener('mousedown', function(e) {
                isDragging = true;
                d0x = e.clientX;
                d0y = e.clientY;
                c0x = crop.x;
                c0y = crop.y;
                e.preventDefault();
            }, { signal });

            document.addEventListener('mousemove', function(e) {
                if (!isDragging) return;
                const dx = e.clientX - d0x;
                const dy = e.clientY - d0y;
                const maxX = cropperImage.offsetWidth - crop.w;
                const maxY = cropperImage.offsetHeight - crop.h;
                crop.x = Math.max(0, Math.min(c0x + dx, maxX));
                crop.y = Math.max(0, Math.min(c0y + dy, maxY));
                updateCropSelection();
            }, { signal });

            document.addEventListener('mouseup', function() {
                isDragging = false;
            }, { signal });

            const handles = selection.querySelectorAll('.resize-handle');
            handles.forEach(handle => {
                handle.addEventListener('mousedown', function(e) {
                    e.stopPropagation();
                    const direction = this.dataset.direction;
                    let isResizing = true;
                    const sw = crop.w;
                    const sh = crop.h;
                    const sx = e.clientX;
                    const sy = e.clientY;
                    const cx = crop.x;
                    const cy = crop.y;

                    function onMouseMove(e) {
                        if (!isResizing) return;
                        const dx = e.clientX - sx;
                        const dy = e.clientY - sy;
                        if (direction.includes('right')) {
                            crop.w = Math.max(20, Math.min(sw + dx, cropperImage.offsetWidth - crop.x));
                        }
                        if (direction.includes('left')) {
                            const nw = Math.max(20, sw - dx);
                            crop.x = Math.min(cx + sw - 20, cx + dx);
                            crop.w = nw;
                        }
                        if (direction.includes('bottom')) {
                            crop.h = Math.max(20, Math.min(sh + dy, cropperImage.offsetHeight - crop.y));
                        }
                        if (direction.includes('top')) {
                            const nh = Math.max(20, sh - dy);
                            crop.y = Math.min(cy + sh - 20, cy + dy);
                            crop.h = nh;
                        }
                        updateCropSelection();
                    }

                    function onMouseUp() {
                        isResizing = false;
                        document.removeEventListener('mousemove', onMouseMove);
                        document.removeEventListener('mouseup', onMouseUp);
                    }

                    document.addEventListener('mousemove', onMouseMove);
                    document.addEventListener('mouseup', onMouseUp);
                }, { signal });
            });

            window.applyCrop = function() {
                const img = cropperImage;
                const scaleX = img.naturalWidth / img.offsetWidth;
                const scaleY = img.naturalHeight / img.offsetHeight;
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(crop.w * scaleX));
                canvas.height = Math.max(1, Math.round(crop.h * scaleY));
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, crop.x * scaleX, crop.y * scaleY, canvas.width, canvas.height, 0, 0, canvas.width, canvas.height);
                canvas.toBlob(function(blob) {
                    const prefix = window.currentCropperTarget;
                    const previewImg = document.getElementById(prefix + 'PreviewImg');
                    const fileInput = document.getElementById(prefix + 'ImageInput');
                    const dataUrl = canvas.toDataURL('image/png');
                    if (previewImg) previewImg.src = dataUrl;
                    if (fileInput) {
                        const file = new File([blob], 'cropped-image.png', { type: 'image/png' });
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        fileInput.files = dt.files;
                        if (prefix === 'recipe') recipeImagePendingFile = file;
                        else if (prefix === 'product') productImagePendingFile = file;
                        else if (prefix === 'editProduct') editProductImagePendingFile = file;
                    }
                    closeCropper();
                }, 'image/png');
            };
        }

        function closeCropper() {
            if (cropperUiAbort) {
                cropperUiAbort.abort();
                cropperUiAbort = null;
            }
            closeModal('imageCropperModal');
            const ci = document.getElementById('cropperImage');
            if (ci) ci.src = '';
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('productFormLang').value = currentLang;
            document.getElementById('recipeFormLang').value = currentLang;
            document.getElementById('productLang').value = currentLang;
            document.getElementById('recipeLang').value = currentLang;

            // Image preview handlers
            document.getElementById('productImageInput').addEventListener('change', function(e) {
                handleImagePreview(e.target);
            });
            document.getElementById('recipeImageInput').addEventListener('change', function(e) {
                handleImagePreview(e.target);
            });
            document.getElementById('editProductImageInput').addEventListener('change', function(e) {
                handleImagePreview(e.target);
            });
            bindImageSourceControls('recipe');
            bindImageSourceControls('product');
            bindImageSourceControls('editProduct');
            bindImageDropzone('recipe');
            bindImageDropzone('product');
            bindImageDropzone('editProduct');
            document.addEventListener('dragover', function(event) {
                if (!hasImageInDataTransfer(event.dataTransfer)) return;
                event.preventDefault();
            });
            document.addEventListener('drop', handleGlobalImageDrop);
            syncImageSourceMode('recipe');
            syncImageSourceMode('product');
            syncImageSourceMode('editProduct');

            // Auto-capitalize recipe title: first letter uppercase, rest lowercase
            const recipeTitleInput = document.getElementById('recipeTitle');
            if (recipeTitleInput) {
                recipeTitleInput.addEventListener('input', function(e) {
                    const value = e.target.value;
                    if (value.length > 0) {
                        e.target.value = value.charAt(0).toUpperCase() + value.slice(1).toLowerCase();
                    }
                });
            }

            const urlParams = new URLSearchParams(window.location.search);

            if (urlParams.has('search_page')) {
                openModal('searchRecipeModal');
            }

            if (urlParams.has('product_page')) {
                openModal('productModal');
            }

            if (urlParams.has('select_page')) {
                openModal('productSelectionModal');
            }

            // Handle hash navigation for modals
            const handleHashChange = () => {
                const hash = window.location.hash.replace('#', '');
                const validModals = ['recipeModal', 'addRecipeChoiceModal', 'aiRecipeWorkspaceModal', 'aiImagePickerModal', 'deleteConfirmModal', 'productModal', 'productViewModal', 'addProductModal', 'searchRecipeModal', 'editProductModal', 'settingsModal', 'shareModal', 'productSelectionModal'];
                
                if (hash && validModals.includes(hash)) {
                    openModal(hash);
                } else if (!hash) {
                    const currentModals = document.querySelectorAll('.modal[style*="display: flex"]');
                    currentModals.forEach(modal => {
                        const modalId = modal.id;
                        closeModal(modalId);
                    });
                }
            };

            // Check for hash on page load
            handleHashChange();

            // Listen for hash changes
            window.addEventListener('hashchange', handleHashChange);
            bindProductModalEvents();
            let _imgResizeT = null;
            window.addEventListener('resize', () => {
                clearTimeout(_imgResizeT);
                _imgResizeT = setTimeout(() => {
                    syncIngredientProductImages();
                    syncProductViewModalImg();
                }, 120);
            });
        });

        document.getElementById('recipeForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            if (isTranslating) return;

            isTranslating = true;
            document.getElementById('loading').style.display = 'block';
            disableRecipeFormButton();

            // Show translation progress
            const progressContainer = document.getElementById('recipeTranslationProgress');
            const progressBar = document.getElementById('recipeTranslationProgressBar');
            const progressText = document.getElementById('recipeTranslationProgressText');
            progressContainer.style.display = 'block';
            progressBar.style.width = '0%';
            progressText.textContent = '0%';

            const formData = new FormData(e.target);
            if (recipeImagePendingFile) {
                formData.delete('image');
                formData.append('image', recipeImagePendingFile, recipeImagePendingFile.name || 'image.jpg');
            }
            const sourceLang = document.getElementById('recipeLang').value;
            document.getElementById('recipeFormLang').value = sourceLang;
            const stepDivs = getRecipeFormStepDivs();

            // Build steps array
            const currentSteps = stepDivs.map((stepDiv, index) => {
                const type = steps[index]?.type || 'instruction';
                let content;

                if (type === 'product') {
                    content = steps[index]?.content || '';
                } else {
                    const textarea = stepDiv.querySelector('textarea');
                    content = textarea ? textarea.value : '';
                }

                let newStep = {
                    type: type,
                    content: content,
                    timer: null
                };

                const checkbox = stepDiv.querySelector('input[type="checkbox"]');
                if (checkbox && checkbox.checked) {
                    const hoursInput = stepDiv.querySelector('.timer-hours');
                    const minutesInput = stepDiv.querySelector('.timer-minutes');
                    const hours = hoursInput ? parseInt(hoursInput.value) || 0 : 0;
                    const minutes = minutesInput ? parseInt(minutesInput.value) || 0 : 0;
                    if (hours > 0 || minutes > 0) {
                        newStep.timer = { hours, minutes };
                    }
                }

                if (type === 'product') {
                    const qtyInput = stepDiv.querySelector('.quantity-input');
                    const unitSelect = stepDiv.querySelector('.unit-select');
                    if (qtyInput && unitSelect) {
                        newStep.quantity = {
                            value: parseFloat(qtyInput.value) || 100,
                            unit: unitSelect.value || 'grams'
                        };
                    }
                }

                return newStep;
            });

            const titleText = document.getElementById('recipeTitle').value;
            const isEditing = formData.get('action') === 'edit_recipe';
            const sourceRecipe = isEditing ? currentEditingRecipeSnapshot : null;
            const sourceLangTitle = sourceRecipe ? getRecipeLocalizedText(sourceRecipe.title, sourceLang) : '';
            const titleChanged = !sourceRecipe || titleText !== sourceLangTitle;
            const instructionTranslationIndexes = currentSteps
                .map((step, index) => {
                    if (step.type !== 'instruction') return -1;
                    const originalStep = sourceRecipe && Array.isArray(sourceRecipe.steps) ? sourceRecipe.steps[index] : null;
                    const originalText = originalStep && originalStep.type === 'instruction'
                        ? getRecipeLocalizedText(originalStep.content, sourceLang)
                        : '';
                    const changed = !originalStep || originalStep.type !== 'instruction' || step.content !== originalText;
                    return (changed && step.content) ? index : -1;
                })
                .filter(index => index !== -1);
            const totalItemsToTranslate = (titleChanged ? 1 : 0) + instructionTranslationIndexes.length;
            let translatedItems = 0;

            const updateProgress = () => {
                translatedItems++;
                const progress = totalItemsToTranslate > 0
                    ? Math.round((translatedItems / totalItemsToTranslate) * 100)
                    : 100;
                progressBar.style.width = `${progress}%`;
                progressText.textContent = totalItemsToTranslate > 0
                    ? `${translations.translating} (${translatedItems}/${totalItemsToTranslate})`
                    : `${translations.translating} (0/0)`;
            };

            try {
                progressBar.style.width = totalItemsToTranslate > 0 ? '0%' : '100%';
                progressText.textContent = totalItemsToTranslate > 0
                    ? `${translations.translating} (0/${totalItemsToTranslate})`
                    : `${translations.translating} (0/0)`;

                const translatedTitle = titleChanged
                    ? await translateTextToAllLanguages(titleText, sourceLang)
                    : cloneJsonData(sourceRecipe?.title) || {
                        en: titleText,
                        uk: titleText,
                        ru: titleText,
                        be: titleText
                    };
                if (titleChanged) {
                    updateProgress();
                }

                for (let i = 0; i < currentSteps.length; i++) {
                    if (currentSteps[i].type !== 'instruction') {
                        continue;
                    }

                    const originalStep = sourceRecipe && Array.isArray(sourceRecipe.steps) ? sourceRecipe.steps[i] : null;
                    const stepContent = currentSteps[i].content;

                    const originalText = originalStep && originalStep.type === 'instruction'
                        ? getRecipeLocalizedText(originalStep.content, sourceLang)
                        : '';
                    const isChangedInstruction = !originalStep || originalStep.type !== 'instruction' || stepContent !== originalText;

                    if (!stepContent && isChangedInstruction) {
                        currentSteps[i].content = {
                            en: '',
                            uk: '',
                            ru: '',
                            be: ''
                        };
                        continue;
                    }

                    if (instructionTranslationIndexes.includes(i)) {
                        currentSteps[i].content = await translateTextToAllLanguages(stepContent, sourceLang);
                        updateProgress();
                    } else {
                        currentSteps[i].content = cloneJsonData(originalStep?.content) || {
                            en: stepContent,
                            uk: stepContent,
                            ru: stepContent,
                            be: stepContent
                        };
                    }
                }

                formData.set('steps', JSON.stringify(currentSteps, null, 2));
                formData.set('translated_title', JSON.stringify(translatedTitle));
                formData.set('action', formData.get('action') + '_pretranslated');

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const result = await parseJsonResponse(response);

                if (result.success) {
                    showNotification(result.message, 'success');
                    closeModal('recipeModal');
                    refreshRecipesGrid();
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                console.error('Translation error:', error);
                showNotification(translations.error_occurred, 'error');
            } finally {
                document.getElementById('loading').style.display = 'none';
                enableRecipeFormButton();
                isTranslating = false;
                // Hide progress after a short delay
                setTimeout(() => {
                    progressContainer.style.display = 'none';
                    progressBar.style.width = '0%';
                    progressText.textContent = '';
                }, 500);
            }
        });

       // Functions for image generation
        // Functions for image generation
        async function generateShareImage() {
            const recipeId = currentShareRecipeId;
            if (!recipeId) {
                showNotification(translations.select_recipe_first, 'error');
                return;
            }

            const shareLang = document.getElementById('shareLangSelect').value;
            const shareTheme = document.getElementById('shareThemeSelect').value;

            try {
                document.getElementById('loading').style.display = 'block';

                // Fetch full recipe from database
                const response = await fetch(`?get_recipe_full=1&recipe_id=${recipeId}`);
                const recipeData = await parseJsonResponse(response);
                
                if (!recipeData || !recipeData.success) {
                    throw new Error('Failed to load recipe');
                }
                
                const recipe = recipeData.data;
                
                // Create temporary container for preview
                const previewContainer = document.getElementById('shareRecipePreview');
                previewContainer.innerHTML = '';
                
                // Set theme for preview
                previewContainer.className = `share-preview ${shareTheme}`;
                
                // Initial width and height
                let containerWidth = 800;
                let containerHeight = 600;
                
                // Function to create HTML content
                function createPreviewHTML(currentWidth) {
                    // Ingredients
                    const ingredients = recipe.steps.filter(step => step.type === 'product');
                    
                    // Steps (instructions only)
                    const instructionSteps = recipe.steps.filter(step => step.type === 'instruction');
                    
                    let html = `<div style="width: ${currentWidth}px; padding: 30px; box-sizing: border-box; background-color: ${shareTheme === 'light' ? '#ffffff' : '#333333'}; color: ${shareTheme === 'light' ? '#000000' : '#ffffff'}; border-radius: 16px;">`;
                    
                    // Header
                    html += `<div style="text-align: center; margin-bottom: 25px;">`;
                    html += `<h2 style="color: var(--primary-color); margin: 0 0 15px 0; font-size: 28px;">${recipe.title[shareLang] || recipe.title.en}</h2>`;
                    
                    if (recipe.image) {
                        html += `<img src="${recipeImg(recipe)}" alt="${recipe.title[shareLang] || recipe.title.en}" style="max-width: 250px; max-height: 200px; object-fit: cover; border-radius: 12px; border: 3px solid var(--primary-color); box-shadow: 0 5px 15px rgba(0,0,0,0.2);">`;
                    }
                    html += `</div>`;
                    
                    // Two-column layout
                    html += `<div style="display: grid; grid-template-columns: 300px 1fr; gap: 25px;">`;
                    
                    // Left column - Ingredients
                    html += `<div style="background: ${shareTheme === 'light' ? 'rgba(137, 137, 137, 0.1)' : 'rgba(88, 88, 88, 0.3)'}; padding: 20px; border-radius: 12px;">`;
                    html += `<h3 style="color: var(--primary-color); border-bottom: 2px solid var(--primary-color); padding-bottom: 8px; margin-top: 0; margin-bottom: 15px;">📋 ${translations.ingredients}</h3>`;
                    html += `<div style="display: flex; flex-direction: column; gap: 12px;">`;
                    
                    ingredients.forEach(step => {
                        const product = products.find(p => p.id === step.content);
                        if (product) {
                            let unitText = '';
                            switch(step.quantity?.unit) {
                                case 'grams': unitText = translations.grams; break;
                                case 'pieces': unitText = translations.pieces; break;
                                case 'teaspoon': unitText = translations.teaspoon; break;
                                case 'tablespoon': unitText = translations.tablespoon; break;
                                default: unitText = step.quantity?.unit || '';
                            }

                            html += `<div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: ${shareTheme === 'light' ? '#ffffff' : '#444444'}; border-radius: 8px; border: 1px solid var(--primary-color);">`;
                            if (product.image) {
                                html += `<img src="${productImgTile(product)}" alt="${product.name[shareLang] || product.name.en}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid var(--primary-color);">`;
                            }
                            html += `<div style="flex-grow: 1;">`;
                            html += `<div style="font-weight: bold;">${product.name[shareLang] || product.name.en}</div>`;
                            if (step.quantity) {
                                html += `<div style="font-size: 14px; color: var(--primary-color);">${step.quantity.value} ${unitText}</div>`;
                            }
                            html += `</div></div>`;
                        }
                    });
                    html += `</div></div>`;
                    
                    // Right column - Steps (vertical)
                    html += `<div style="background: ${shareTheme === 'light' ? 'rgba(137, 137, 137, 0.1)' : 'rgba(88, 88, 88, 0.3)'}; padding: 20px; border-radius: 12px;">`;
                    html += `<h3 style="color: var(--primary-color); border-bottom: 2px solid var(--primary-color); padding-bottom: 8px; margin-top: 0; margin-bottom: 15px;">👨‍🍳 ${translations.steps}</h3>`;
                    html += `<div style="display: flex; flex-direction: column; gap: 15px;">`;
                    
                    instructionSteps.forEach((step, index) => {
                        const instructionText = typeof step.content === 'object' ? (step.content[shareLang] || step.content.en || '') : step.content;
                        
                        html += `<div style="background: ${shareTheme === 'light' ? '#ffffff' : '#444444'}; padding: 15px; border-radius: 8px; border-left: 4px solid var(--primary-color);">`;
                        html += `<div style="display: flex; align-items: flex-start; gap: 12px;">`;
                        html += `<span style="background: var(--primary-color); color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0;">${index + 1}</span>`;
                        html += `<div style="flex-grow: 1;">`;
                        html += `<div>${instructionText}</div>`;
                        
                        if (step.timer && (step.timer.hours > 0 || step.timer.minutes > 0)) {
                            let timerText = '';
                            if (step.timer.hours > 0) timerText += `${step.timer.hours}ч `;
                            if (step.timer.minutes > 0) timerText += `${step.timer.minutes}м`;
                            html += `<div style="margin-top: 8px;"><span style="background: var(--primary-color); color: white; padding: 4px 10px; border-radius: 16px; font-size: 12px; display: inline-block;">⏱️ ${timerText}</span></div>`;
                        }
                        html += `</div></div></div>`;
                    });
                    
                    html += `</div></div>`; // Close the right column
                    html += `</div>`; // Close the grid
                    html += `</div>`; // Close the main container
                    
                    return html;
                }
                
                // Function to measure content height
                function measureContentHeight(html, width) {
                    const tempDiv = document.createElement('div');
                    tempDiv.style.position = 'absolute';
                    tempDiv.style.left = '-9999px';
                    tempDiv.style.top = '-9999px';
                    tempDiv.style.width = width + 'px';
                    tempDiv.innerHTML = html;
                    document.body.appendChild(tempDiv);
                    const height = tempDiv.scrollHeight;
                    document.body.removeChild(tempDiv);
                    return height;
                }
                
                // Find optimal height
                let optimalWidth = 800;
                let optimalHeight;
                let contentHTML;
                let maxAttempts = 5;
                let attempt = 0;
                
                while (attempt < maxAttempts) {
                    contentHTML = createPreviewHTML(optimalWidth);
                    optimalHeight = measureContentHeight(contentHTML, optimalWidth);
                    
                    // If height too large, increase width
                    if (optimalHeight > 1400 && optimalWidth < 1200) {
                        optimalWidth += 100;
                        attempt++;
                    } else {
                        break;
                    }
                }
                
                // Add small height margin
                optimalHeight += 60;
                
                // Set final HTML
                previewContainer.innerHTML = contentHTML;
                previewContainer.style.width = optimalWidth + 'px';
                previewContainer.style.maxWidth = optimalWidth + 'px';
                previewContainer.style.padding = '0';
                previewContainer.style.boxSizing = 'border-box';
                previewContainer.style.margin = '0 auto';
                
                document.getElementById('sharePreviewContainer').style.display = 'block';
                
                // Wait for rendering and image loading
                await new Promise(resolve => setTimeout(resolve, 800));
                
                // Load all images
                const images = previewContainer.getElementsByTagName('img');
                const imagePromises = Array.from(images).map(img => {
                    if (img.complete) return Promise.resolve();
                    return new Promise(resolve => {
                        img.onload = resolve;
                        img.onerror = resolve;
                    });
                });
                await Promise.all(imagePromises);
                
                // Generate image with maximum quality
                const canvas = await html2canvas(previewContainer, {
                    scale: 3, // Maximum sharpness
                    backgroundColor: shareTheme === 'light' ? '#ffffff' : '#333333',
                    allowTaint: true,
                    useCORS: true,
                    logging: false,
                    width: optimalWidth,
                    height: optimalHeight,
                    windowWidth: optimalWidth,
                    windowHeight: optimalHeight,
                    onclone: (clonedDoc, element) => {
                        // Apply styles to cloned element
                        element.style.width = optimalWidth + 'px';
                        element.style.height = optimalHeight + 'px';
                        element.style.overflow = 'hidden';
                    }
                });
                
                // Add shadow
                previewContainer.style.boxShadow = '0 10px 30px rgba(0,0,0,0.3)';
                previewContainer.style.borderRadius = '16px';
                
                currentShareImage = canvas.toDataURL('image/png');
                
                // Show preview with image
                previewContainer.innerHTML = `<img src="${currentShareImage}" alt="Generated Recipe" style="width: 100%; max-width: ${optimalWidth}px; height: auto; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">`;
                
                document.getElementById('loading').style.display = 'none';
                showNotification(translations.image_generated, 'success');
                
            } catch (error) {
                console.error('Error generating image:', error);
                document.getElementById('loading').style.display = 'none';
                showNotification(translations.error_occurred, 'error');
            }
        }

        function copyShareImage() {
            if (!currentShareImage) {
                showNotification(translations.generate_image_first, 'error');
                return;
            }
            
            // Create temporary element for copying
            const img = new Image();
            img.src = currentShareImage;
            img.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = img.width;
                canvas.height = img.height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                
                canvas.toBlob(async (blob) => {
                    try {
                        await navigator.clipboard.write([
                            new ClipboardItem({
                                [blob.type]: blob
                            })
                        ]);
                        showNotification(translations.image_copied, 'success');
                    } catch (err) {
                        console.error('Error copying image:', err);
                        showNotification(translations.copy_failed, 'error');
                    }
                });
            };
        }

        function downloadShareImage() {
            if (!currentShareImage) {
                showNotification(translations.generate_image_first, 'error');
                return;
            }

            const link = document.createElement('a');
            link.download = `recipe-${new Date().getTime()}.png`;
            link.href = currentShareImage;
            link.click();
        }

        let deleteConfirmCallback = null;
        function showDeleteConfirm(message, onConfirm) {
            document.getElementById('deleteConfirmMessage').textContent = message;
            deleteConfirmCallback = onConfirm;
            openModal('deleteConfirmModal');
        }
        function closeDeleteConfirm(confirmed) {
            closeModal('deleteConfirmModal');
            if (confirmed && deleteConfirmCallback) deleteConfirmCallback();
            deleteConfirmCallback = null;
        }

        function openAddRecipeChoice() {
            if (userRole < 1) {
                showNotification(translations.error_occurred, 'error');
                return;
            }
            openModal('addRecipeChoiceModal');
        }

        function openManualRecipeForm() {
            closeModal('addRecipeChoiceModal');
            openAiRecipeWorkspaceManual();
        }

        async function loadAiRecipeConfig() {
            if (aiRecipeConfig) return aiRecipeConfig;
            const r = await fetch('recipes_ai.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'config' })
            });
            const j = await parseJsonResponse(r);
            if (!j.success || !j.config) throw new Error('config');
            aiRecipeConfig = j.config;
            const sel = document.getElementById('aiRecipeModel');
            if (sel) {
                sel.innerHTML = '';
                (j.config.models || []).forEach(m => {
                    const o = document.createElement('option');
                    o.value = m;
                    o.textContent = m;
                    if (m === j.config.default_model) o.selected = true;
                    sel.appendChild(o);
                });
                setupAiModelDropdown(j.config.models || []);
            }
            if (!j.config.has_key) {
                setAiRecipeStatus(translations.ai_config_missing, true);
            }
            return j.config;
        }

        function setupAiModelDropdown(models) {
            const dropdown = document.getElementById('aiModelDropdown');
            const select = document.getElementById('aiRecipeModel');
            const toggle = document.getElementById('aiModelToggle');
            const value = dropdown?.querySelector('.ai-model-value');
            const menu = document.getElementById('aiModelMenu');
            if (!dropdown || !select || !toggle || !value || !menu) return;

            const close = () => {
                dropdown.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
            };
            const selectModel = model => {
                select.value = model;
                value.textContent = model;
                menu.querySelectorAll('.ai-model-option').forEach(option => {
                    option.classList.toggle('selected', option.dataset.model === model);
                });
                close();
            };

            menu.innerHTML = '';
            models.forEach(model => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'ai-model-option';
                option.dataset.model = model;
                option.setAttribute('role', 'option');
                option.innerHTML = '<svg class="ai-model-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3-1.9 5.1L5 10l5.1 1.9L12 17l1.9-5.1L19 10l-5.1-1.9L12 3Z"></path><path d="m19 15-.9 2.1L16 18l2.1.9L19 21l.9-2.1L22 18l-2.1-.9L19 15Z"></path></svg>';
                const name = document.createElement('span');
                name.textContent = model;
                option.appendChild(name);
                option.addEventListener('click', () => selectModel(model));
                menu.appendChild(option);
            });
            selectModel(select.value || models[0] || '');

            if (!dropdown.dataset.initialized) {
                toggle.addEventListener('click', () => {
                    const open = dropdown.classList.toggle('open');
                    toggle.setAttribute('aria-expanded', String(open));
                });
                document.addEventListener('click', event => {
                    if (!dropdown.contains(event.target)) close();
                });
                dropdown.dataset.initialized = 'true';
            }
        }

        function setAiRecipeStatus(msg, isError) {
            const el = document.getElementById('aiRecipeStatus');
            if (el) {
                el.textContent = msg || '';
                el.style.color = isError ? '#c0392b' : 'var(--primary-color)';
            }
        }

        let aiRecipeDraft = null;
        let aiRecipeConfig = null;
        let aiRecipeBusy = false;
        let aiHasDraft = false;
        let aiPendingProducts = [];
        let skipRecipeModalReset = false;
        let aiWorkspaceManual = false;
        let productSelectionTarget = 'recipe';
        let currentAiPreviewStepIndex = null;

        function productObjFromCandidate(c) {
            if (!c) return null;
            if (c.id && products.find(x => x.id === c.id)) return products.find(x => x.id === c.id);
            if (!c.image) return null;
            return { id: c.id, image: c.image, image_preview: c.image_preview, name: c.names || { en: c.name } };
        }

        function aiProductThumbHtml(productOrCandidate, alt) {
            const p = productOrCandidate?.id ? (products.find(x => x.id === productOrCandidate.id) || productOrCandidate) : productOrCandidate;
            if (p?.image) {
                return `<img src="${productImgTile(p)}" alt="${escapeHtml(alt || '')}" class="ai-step-product-thumb">`;
            }
            return '<span class="ai-step-product-thumb-placeholder" aria-hidden="true"></span>';
        }

        function setAiWorkspaceMode(manual) {
            aiWorkspaceManual = manual;
            const content = document.getElementById('aiRecipeWorkspaceContent');
            const promptPanel = document.getElementById('aiPromptPanel');
            const manualPanel = document.getElementById('aiManualPanel');
            const modelSel = document.getElementById('aiRecipeModel');
            const modelField = document.querySelector('.ai-model-field');
            const titleEl = document.getElementById('aiWorkspaceTitle');
            const emptyEl = document.getElementById('aiRecipePreviewEmpty');
            const openFormBtn = document.getElementById('aiOpenInFormBtn');
            if (content) content.classList.toggle('ai-workspace-manual', manual);
            if (promptPanel) promptPanel.style.display = manual ? 'none' : 'flex';
            if (manualPanel) manualPanel.style.display = manual ? 'flex' : 'none';
            if (modelSel) modelSel.style.display = manual ? 'none' : '';
            if (modelField) modelField.style.display = manual ? 'none' : 'flex';
            if (titleEl) titleEl.textContent = manual ? translations.ai_manual_workspace : translations.ai_recipe_workspace;
            if (emptyEl) emptyEl.textContent = manual ? translations.ai_manual_preview_empty : translations.ai_preview_empty;
            if (openFormBtn) openFormBtn.style.display = manual ? 'none' : '';
            const previewToolbar = document.getElementById('aiPreviewManualToolbar');
            if (previewToolbar) previewToolbar.style.display = manual ? 'flex' : 'none';
        }

        let aiImagePickerResolve = null;
        let aiImagePickerReject = null;

        function closeAiImagePicker() {
            const reject = aiImagePickerReject;
            aiImagePickerResolve = null;
            aiImagePickerReject = null;
            closeModal('aiImagePickerModal');
            if (reject) reject(new Error('cancelled'));
        }

        function applyRecipeImageToPreview(opt) {
            if (!opt?.url) return;
            const urlEl = document.getElementById('aiPreviewImageUrl');
            const imgEl = document.getElementById('aiPreviewImage');
            if (urlEl) urlEl.value = opt.url;
            if (imgEl) {
                imgEl.src = opt.url;
                imgEl.style.display = 'block';
            }
            const srcEl = document.getElementById('aiPreviewImageSource');
            if (srcEl) srcEl.textContent = opt.title ? `${translations.ai_image_source}: ${opt.title}` : '';
            if (aiRecipeDraft) {
                aiRecipeDraft.image_url = opt.url;
                aiRecipeDraft.image_source = opt.title || '';
                aiRecipeDraft.image_pending = false;
            }
            updateAiImageActions(true);
        }

        function updateAiImageActions(hasImage) {
            const chooseBtn = document.getElementById('aiChooseImageBtn');
            const refreshBtn = document.querySelector('.ai-refresh-image-btn');
            if (chooseBtn) chooseBtn.style.display = hasImage ? 'none' : '';
            if (refreshBtn) refreshBtn.style.display = hasImage ? '' : 'none';
        }

        async function openAiImagePicker({ kind, title, query }) {
            const grid = document.getElementById('aiImagePickerGrid');
            const loading = document.getElementById('aiImagePickerLoading');
            const titleEl = document.getElementById('aiImagePickerTitle');
            const hintEl = document.getElementById('aiImagePickerHint');
            if (titleEl) titleEl.textContent = kind === 'product' ? `${translations.ai_pick_image_title} — ${title}` : translations.ai_pick_image_title;
            if (hintEl) hintEl.textContent = translations.ai_pick_image_hint;
            if (grid) grid.innerHTML = '';
            if (loading) loading.style.display = 'block';
            openModal('aiImagePickerModal');
            return new Promise((resolve, reject) => {
                aiImagePickerResolve = resolve;
                aiImagePickerReject = reject;
                aiRecipeApiCall('image_options', { title, image_query: query || title, kind }).then(result => {
                    if (loading) loading.style.display = 'none';
                    const options = result.options || [];
                    if (!result.success || !options.length) {
                        showNotification(result.message || translations.ai_pick_image_none, 'error');
                        closeAiImagePicker();
                        return;
                    }
                    options.forEach((opt, i) => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'ai-image-picker-option';
                        const img = document.createElement('img');
                        img.src = opt.url;
                        img.alt = opt.title || '';
                        const cap = document.createElement('span');
                        cap.className = 'ai-image-picker-caption';
                        cap.textContent = opt.title || (`Photo ${i + 1}`);
                        btn.appendChild(img);
                        btn.appendChild(cap);
                        btn.onclick = () => {
                            aiImagePickerResolve = null;
                            aiImagePickerReject = null;
                            closeModal('aiImagePickerModal');
                            resolve(opt);
                        };
                        grid.appendChild(btn);
                    });
                }).catch(err => {
                    if (loading) loading.style.display = 'none';
                    showNotification(err.message || translations.error_occurred, 'error');
                    closeAiImagePicker();
                });
            });
        }

        async function promptAiRecipeImageIfNeeded(draft) {
            if (!draft || draft.image_url) return draft;
            const title = draft.title || document.getElementById('aiPreviewTitle')?.value?.trim();
            const query = draft.recipe_image_query || title;
            if (!title) return draft;
            try {
                const opt = await openAiImagePicker({ kind: 'recipe', title, query });
                applyRecipeImageToPreview(opt);
                draft.image_url = opt.url;
                draft.image_source = opt.title || '';
                draft.image_pending = false;
            } catch (_) {}
            return draft;
        }

        function applyProductSelection(product) {
            if (!product) return;
            if (productSelectionTarget === 'ai' && currentAiPreviewStepIndex !== null) {
                const idx = currentAiPreviewStepIndex;
                const pname = product.name[currentLang] || product.name.en;
                if (!aiRecipeDraft) aiRecipeDraft = { title: '', steps: [], lang: currentLang };
                while ((aiRecipeDraft.steps || []).length <= idx) aiRecipeDraft.steps.push({ type: 'instruction', content: '' });
                aiRecipeDraft.steps[idx] = {
                    type: 'product',
                    product_id: product.id,
                    content: product.id,
                    product_name: pname,
                    quantity: { value: 100, unit: 'grams' }
                };
                showAiPreviewShell();
                rerenderAllAiSteps(idx);
                currentAiPreviewStepIndex = null;
                productSelectionTarget = 'recipe';
                closeModal('productSelectionModal');
                return;
            }
            if (currentStepIndex !== null) {
                steps[currentStepIndex] = {
                    type: 'product',
                    content: product.id,
                    timer: steps[currentStepIndex]?.timer || null,
                    quantity: { value: 100, unit: 'grams' }
                };
                const stepDiv = getRecipeFormStepDivs()[currentStepIndex];
                if (!stepDiv) return;
                stepDiv.querySelector('textarea').value = product.name[currentLang];
                let qtyContainer = stepDiv.querySelector('.quantity-container');
                if (!qtyContainer) {
                    qtyContainer = document.createElement('div');
                    qtyContainer.className = 'quantity-container';
                    qtyContainer.innerHTML = `
                        <label>${translations.quantity}</label>
                        <input type="number" class="quantity-input" min="0" step="0.1" value="100" placeholder="100">
                        <select class="unit-select">
                            <option value="grams">${translations.grams}</option>
                            <option value="pieces">${translations.pieces}</option>
                            <option value="teaspoon">${translations.teaspoon}</option>
                            <option value="tablespoon">${translations.tablespoon}</option>
                        </select>
                    `;
                    const textarea = stepDiv.querySelector('textarea');
                    textarea.parentNode.insertBefore(qtyContainer, textarea.nextSibling);
                }
                closeModal('productSelectionModal');
            }
        }

        function openAiProductSelection(stepIndex) {
            productSelectionTarget = 'ai';
            currentAiPreviewStepIndex = stepIndex;
            currentStepIndex = null;
            openModal('productSelectionModal');
            document.getElementById('productSelectionSearch').value = '';
            filterSelectionProducts('');
        }

        function aiRemovePreviewStep(index) {
            if (aiRecipeDraft?.steps) aiRecipeDraft.steps.splice(index, 1);
            rerenderAllAiSteps(-1);
        }

        function aiManualAddIngredient() {
            if (!aiRecipeDraft) aiRecipeDraft = { title: '', steps: [], lang: currentLang };
            showAiPreviewShell();
            const idx = (aiRecipeDraft.steps || []).length;
            aiRecipeDraft.steps.push({ type: 'product', product_id: '', product_name: '', quantity: { value: 100, unit: 'grams' } });
            rerenderAllAiSteps(idx);
            openAiProductSelection(idx);
        }

        function aiManualAddInstruction() {
            if (!aiRecipeDraft) aiRecipeDraft = { title: '', steps: [], lang: currentLang };
            showAiPreviewShell();
            const idx = (aiRecipeDraft.steps || []).length;
            aiRecipeDraft.steps.push({ type: 'instruction', content: '' });
            rerenderAllAiSteps(idx);
        }

        function openAiRecipeWorkspaceManual() {
            closeModal('addRecipeChoiceModal');
            aiRecipeDraft = { title: '', steps: [], lang: currentLang };
            aiPendingProducts = [];
            aiHasDraft = false;
            productSelectionTarget = 'recipe';
            currentAiPreviewStepIndex = null;
            document.getElementById('aiRecipeInput').value = '';
            clearActivityFeed();
            document.getElementById('aiPreviewSteps').innerHTML = '';
            document.getElementById('aiPreviewTitle').value = '';
            renderPendingProducts([]);
            setAiInputMode(false);
            setAiRecipeStatus('');
            const saveProg = document.getElementById('aiSaveTranslationProgress');
            if (saveProg) saveProg.style.display = 'none';
            setAiWorkspaceMode(true);
            showAiPreviewShell();
            document.getElementById('aiRecipePreviewEmpty').style.display = 'none';
            openModal('aiRecipeWorkspaceModal');
            loadAiRecipeConfig().catch(() => {});
        }

        function tReplace(key, vars) {
            let s = translations[key] || key;
            if (vars) Object.keys(vars).forEach(k => { s = s.replace('{' + k + '}', vars[k]); });
            return s;
        }

        function setAiInputMode(hasDraft) {
            aiHasDraft = hasDraft;
            const titleEl = document.getElementById('aiInputZoneTitle');
            const inputEl = document.getElementById('aiRecipeInput');
            const btnEl = document.getElementById('aiRecipeSubmitBtn');
            if (titleEl) titleEl.textContent = hasDraft ? translations.ai_edit_label : translations.ai_prompt_label;
            if (inputEl) inputEl.placeholder = hasDraft ? translations.ai_edit_placeholder : translations.ai_prompt_placeholder;
            if (btnEl) btnEl.textContent = hasDraft ? translations.ai_edit_apply : translations.ai_generate;
        }

        function clearActivityFeed() {
            const el = document.getElementById('aiActivityFeed');
            if (el) el.innerHTML = '';
        }

        function appendActivity(kind, data) {
            const feed = document.getElementById('aiActivityFeed');
            if (!feed) return;
            const div = document.createElement('div');
            div.className = 'ai-activity-item ai-activity-' + kind;
            let icon = '•';
            let text = '';
            if (kind === 'search') {
                icon = '🔍';
                text = tReplace('ai_activity_search', { query: data.query || '', count: data.count ?? 0 });
            } else if (kind === 'found') {
                icon = '✓';
                text = tReplace('ai_activity_found', { name: data.name || '' });
            } else if (kind === 'pending') {
                icon = '⚠';
                text = tReplace('ai_activity_pending', { name: data.name || '' });
            } else if (kind === 'recipe') {
                icon = '✨';
                text = tReplace('ai_activity_recipe', { title: data.title || '' });
            }
            div.innerHTML = `<span class="ai-activity-icon">${icon}</span><span>${escapeHtml(text)}</span>`;
            feed.appendChild(div);
            requestAnimationFrame(() => {
                div.scrollIntoView({ behavior: 'smooth', block: 'end' });
                feed.scrollTop = feed.scrollHeight;
            });
        }

        function showAiPreviewShell() {
            document.getElementById('aiRecipePreviewEmpty').style.display = 'none';
            document.getElementById('aiRecipePreviewForm').style.display = 'flex';
            updateAiImageActions(!!document.getElementById('aiPreviewImageUrl')?.value?.trim());
        }

        function focusNewAiStep(el) {
            if (!el) return;
            el.classList.add('ai-step-new-highlight');
            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            const main = document.querySelector('.ai-workspace-main');
            if (main) main.scrollTop = Math.min(main.scrollHeight, el.offsetTop);
            setTimeout(() => el.classList.remove('ai-step-new-highlight'), 2200);
        }

        function syncAiStepInsertZones() {
            const c = document.getElementById('aiPreviewSteps');
            if (!c) return;
            c.querySelectorAll('.ai-step-insert-zone').forEach(z => z.remove());
            const hasSteps = (aiRecipeDraft?.steps?.length > 0) || c.querySelectorAll('.ai-preview-step').length > 0;
            if (!aiWorkspaceManual && !hasSteps) return;
            const steps = [...c.querySelectorAll('.ai-preview-step')];
            const mk = (at) => {
                const z = document.createElement('div');
                z.className = 'ai-step-insert-zone';
                z.dataset.at = String(at);
                z.setAttribute('role', 'button');
                z.tabIndex = 0;
                z.title = translations.ai_insert_step || 'Insert step';
                z.innerHTML = '<span class="ai-step-insert-line" aria-hidden="true"></span>';
                z.onclick = (event) => openAiInsertMenu(event, at);
                z.onkeydown = (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        openAiInsertMenu(event, at);
                    }
                };
                return z;
            };
            if (!steps.length) {
                c.appendChild(mk(0));
                return;
            }
            c.insertBefore(mk(0), steps[0]);
            steps.forEach((step, i) => step.after(mk(i + 1)));
        }

        function closeAiInsertMenu() {
            document.querySelectorAll('.ai-insert-menu').forEach(menu => menu.remove());
            document.querySelectorAll('.ai-step-insert-zone.is-open').forEach(zone => zone.classList.remove('is-open'));
        }

        function openAiInsertMenu(event, at) {
            event.preventDefault();
            event.stopPropagation();
            const zone = event.currentTarget?.closest?.('.ai-step-insert-zone') || event.currentTarget;
            const existing = zone?.querySelector('.ai-insert-menu');
            closeAiInsertMenu();
            if (existing) return;
            const menu = document.createElement('div');
            menu.className = 'ai-insert-menu';
            menu.innerHTML = `
                <button type="button" data-type="product">${escapeHtml(translations.ai_add_ingredient)}</button>
                <button type="button" data-type="instruction">${escapeHtml(translations.ai_add_instruction_step)}</button>
            `;
            menu.querySelectorAll('button').forEach(btn => {
                btn.onclick = (e) => {
                    e.stopPropagation();
                    const type = btn.dataset.type === 'product' ? 'product' : 'instruction';
                    closeAiInsertMenu();
                    aiInsertStepAt(at, type);
                };
            });
            zone.classList.add('is-open');
            zone.appendChild(menu);
            const first = menu.querySelector('button');
            if (first) first.focus();
        }

        document.addEventListener('click', (event) => {
            if (!event.target.closest?.('.ai-step-insert-zone')) {
                closeAiInsertMenu();
            }
        });

        function renumberAiPreviewSteps() {
            document.querySelectorAll('#aiPreviewSteps .ai-preview-step').forEach((row, i) => {
                row.dataset.stepIndex = String(i);
                const isProduct = row.dataset.type === 'product';
                const label = isProduct ? translations.ingredients : translations.instruction;
                const removePart = aiWorkspaceManual ? `<button type="button" class="ai-step-remove-btn" onclick="aiRemovePreviewStep(${i})">${escapeHtml(translations.ai_remove_step)}</button>` : '';
                const head = row.querySelector('.ai-preview-step-head');
                if (!head) return;
                const pendingEl = head.querySelector('span[style]');
                const pending = pendingEl ? pendingEl.outerHTML : '';
                head.innerHTML = `${translations.step} ${i + 1} — ${label}${pending}${removePart}`;
                const pickBtn = row.querySelector('.ai-step-pick-product-btn');
                if (pickBtn) pickBtn.setAttribute('onclick', `openAiProductSelection(${i})`);
            });
            syncAiStepInsertZones();
        }

        function buildAiPreviewStepEl(step, i, animate, highlight) {
            const div = document.createElement('div');
            const stepType = step.type === 'product' ? 'product' : 'instruction';
            div.className = 'ai-preview-step ai-step-' + stepType + (animate ? ' ai-step-appear' : '') + (highlight ? ' ai-step-new-highlight' : '');
            div.dataset.type = stepType;
            div.dataset.stepIndex = String(i);
            const removeBtn = aiWorkspaceManual ? `<button type="button" class="ai-step-remove-btn" onclick="aiRemovePreviewStep(${i})">${escapeHtml(translations.ai_remove_step)}</button>` : '';
            if (step.type === 'product') {
                div.dataset.productId = step.product_id || step.content || '';
                const p = products.find(x => x.id === (step.product_id || step.content));
                const pname = p ? (p.name[currentLang] || p.name.en) : (step.product_name || step.content || '');
                const pending = step.pending ? ' <span style="opacity:0.7">(pending)</span>' : '';
                const pickBtn = (!p && !step.product_id && aiWorkspaceManual) ? `<button type="button" class="ai-step-pick-product-btn" onclick="openAiProductSelection(${i})">${escapeHtml(translations.ai_pick_product)}</button>` : '';
                div.innerHTML = `<div class="ai-preview-step-head">${translations.step} ${i + 1} — ${translations.ingredients}${pending}${removeBtn}</div>
                    <div class="ai-preview-step-product-row">
                        ${aiProductThumbHtml(p, pname)}
                        <div class="ai-step-product-info">
                            <span class="ai-step-product-name">${escapeHtml(pname)}</span>
                            ${pickBtn}
                        </div>
                        <input type="number" class="ai-step-qty" min="0" step="0.1" value="${step.quantity?.value ?? step.quantity_value ?? 100}">
                        <select class="ai-step-unit">
                            <option value="grams" ${(step.quantity?.unit || step.quantity_unit) === 'grams' ? 'selected' : ''}>${translations.grams}</option>
                            <option value="pieces" ${(step.quantity?.unit || step.quantity_unit) === 'pieces' ? 'selected' : ''}>${translations.pieces}</option>
                            <option value="teaspoon" ${(step.quantity?.unit || step.quantity_unit) === 'teaspoon' ? 'selected' : ''}>${translations.teaspoon}</option>
                            <option value="tablespoon" ${(step.quantity?.unit || step.quantity_unit) === 'tablespoon' ? 'selected' : ''}>${translations.tablespoon}</option>
                        </select>
                    </div>`;
            } else {
                const th = step.timer?.hours ?? step.timer_hours ?? 0;
                const tm = step.timer?.minutes ?? step.timer_minutes ?? 0;
                const hasTimer = th > 0 || tm > 0;
                div.innerHTML = `<div class="ai-preview-step-head">${translations.step} ${i + 1} — ${translations.instruction}${removeBtn}</div>
                    <textarea class="ai-step-instruction" rows="2">${escapeHtml(typeof step.content === 'string' ? step.content : '')}</textarea>
                    <label><input type="checkbox" class="ai-step-timer-cb" ${hasTimer ? 'checked' : ''}> ${translations.set_timer}</label>
                    <div class="ai-step-timer-row" style="display:${hasTimer ? 'flex' : 'none'};gap:6px;">
                        <input type="number" class="ai-step-hours" min="0" max="99" value="${th}" placeholder="${translations.hours}">
                        <input type="number" class="ai-step-minutes" min="0" max="59" value="${tm}" placeholder="${translations.minutes}">
                    </div>`;
                const cb = div.querySelector('.ai-step-timer-cb');
                const tr = div.querySelector('.ai-step-timer-row');
                cb.onchange = () => { tr.style.display = cb.checked ? 'flex' : 'none'; };
            }
            return div;
        }

        function insertAiPreviewStepEl(c, el, i) {
            const next = [...c.querySelectorAll('.ai-preview-step')].find(s => parseInt(s.dataset.stepIndex, 10) > i);
            if (next) c.insertBefore(el, next);
            else c.appendChild(el);
        }

        function upsertAiPreviewStep(step, i, animate, scrollTo) {
            if (!aiRecipeDraft) aiRecipeDraft = { title: '', steps: [], lang: currentLang };
            while ((aiRecipeDraft.steps || []).length <= i) aiRecipeDraft.steps.push(step);
            aiRecipeDraft.steps[i] = step;
            const c = document.getElementById('aiPreviewSteps');
            const existing = c.querySelector(`.ai-preview-step[data-step-index="${i}"]`);
            if (existing) existing.remove();
            const el = buildAiPreviewStepEl(step, i, animate, false);
            insertAiPreviewStepEl(c, el, i);
            renumberAiPreviewSteps();
            setAiInputMode(true);
            if (scrollTo) {
                const fresh = c.querySelector(`.ai-preview-step[data-step-index="${i}"]`);
                focusNewAiStep(fresh);
            }
        }

        function rerenderAllAiSteps(highlightAt = -1) {
            const c = document.getElementById('aiPreviewSteps');
            c.innerHTML = '';
            (aiRecipeDraft?.steps || []).forEach((step, i) => {
                const el = buildAiPreviewStepEl(step, i, false, i === highlightAt);
                c.appendChild(el);
            });
            syncAiStepInsertZones();
            if (highlightAt >= 0) {
                const el = c.querySelector(`.ai-preview-step[data-step-index="${highlightAt}"]`);
                focusNewAiStep(el);
            }
        }

        function aiInsertStepAt(at, type) {
            if (!aiRecipeDraft) aiRecipeDraft = { title: '', steps: [], lang: currentLang };
            showAiPreviewShell();
            const step = type === 'product'
                ? { type: 'product', product_id: '', product_name: '', quantity: { value: 100, unit: 'grams' } }
                : { type: 'instruction', content: '' };
            aiRecipeDraft.steps.splice(at, 0, step);
            rerenderAllAiSteps(at);
            if (type === 'product') openAiProductSelection(at);
        }

        function appendAiPreviewStep(step, i, animate, scrollTo) {
            upsertAiPreviewStep(step, i, animate, scrollTo);
        }

        function renderPendingProducts(pending) {
            aiPendingProducts = pending || [];
            const el = document.getElementById('aiPendingProducts');
            if (!el) return;
            if (!aiPendingProducts.length) {
                el.style.display = 'none';
                el.innerHTML = '';
                return;
            }
            el.style.display = 'block';
            el.innerHTML = `<h4>${escapeHtml(translations.ai_pending_products_title)}</h4>`;
            aiPendingProducts.forEach(item => {
                const row = document.createElement('div');
                row.className = 'ai-pending-item';
                row.dataset.key = item.key;
                let opts = `<option value="">—</option>`;
                (item.candidates || []).forEach(c => {
                    opts += `<option value="${escapeHtml(c.id)}">${escapeHtml(c.name)}</option>`;
                });
                const firstCand = (item.candidates || [])[0];
                const headImg = firstCand ? aiProductThumbHtml(productObjFromCandidate(firstCand) || firstCand, item.name) : '<span class="ai-step-product-thumb-placeholder"></span>';
                row.innerHTML = `<div class="ai-pending-item-head">${headImg}<div class="ai-pending-name">${escapeHtml(item.name)}</div></div>
                    <div class="ai-pending-candidates"><select class="ai-pending-select">${opts}</select></div>
                    <div class="ai-pending-preview-img"></div>
                    <div class="ai-pending-actions">
                        <button type="button" class="ai-pending-link" data-action="link">${escapeHtml(translations.ai_link_existing)}</button>
                        <button type="button" class="ai-pending-create" data-action="create">${escapeHtml(translations.ai_create_new)}</button>
                        <button type="button" class="ai-pending-skip" data-action="skip">${escapeHtml(translations.ai_skip)}</button>
                    </div>`;
                const sel = row.querySelector('.ai-pending-select');
                const previewImg = row.querySelector('.ai-pending-preview-img');
                const updateCandImg = () => {
                    const c = (item.candidates || []).find(x => x.id === sel.value);
                    if (c?.image && previewImg) {
                        const po = productObjFromCandidate(c) || c;
                        previewImg.innerHTML = aiProductThumbHtml(po, c.name);
                    } else if (previewImg) previewImg.innerHTML = '';
                };
                sel.onchange = updateCandImg;
                updateCandImg();
                row.querySelectorAll('button').forEach(btn => {
                    btn.onclick = () => aiResolvePending(item.key, btn.dataset.action, row);
                });
                el.appendChild(row);
            });
        }

        async function aiResolvePending(key, action, rowEl) {
            const item = aiPendingProducts.find(p => p.key === key);
            if (!item) return;
            const decision = { key, action };
            if (action === 'link') {
                const sel = rowEl.querySelector('.ai-pending-select');
                if (!sel?.value) {
                    showNotification(translations.select_product || 'Select product', 'error');
                    return;
                }
                decision.product_id = sel.value;
            } else if (action === 'create') {
                decision.name = item.name;
                decision.category = item.category || 'vegetables';
                rowEl.style.opacity = '0.5';
                rowEl.querySelectorAll('button').forEach(b => { b.disabled = true; });
                try {
                    const opt = await openAiImagePicker({ kind: 'product', title: item.name, query: item.name });
                    decision.image_url = opt.url;
                } catch (e) {
                    rowEl.style.opacity = '1';
                    rowEl.querySelectorAll('button').forEach(b => { b.disabled = false; });
                    return;
                }
            }
            if (action !== 'create') {
                rowEl.style.opacity = '0.5';
                rowEl.querySelectorAll('button').forEach(b => { b.disabled = true; });
            }
            try {
                const draft = collectAiDraftFromPreview();
                const result = await aiRecipeApiCall('confirm_products', { draft, decisions: [decision] });
                if (!result.success) throw new Error(result.message || 'failed');
                if (Array.isArray(result.products)) {
                    result.products.forEach(p => {
                        const idx = products.findIndex(x => x.id === p.id);
                        if (idx >= 0) products[idx] = p;
                        else products.push(p);
                    });
                }
                renderAiRecipePreview(result.draft, false);
                renderPendingProducts(result.pending_products || []);
            } catch (e) {
                showNotification(e.message || translations.error_occurred, 'error');
                rowEl.style.opacity = '1';
                rowEl.querySelectorAll('button').forEach(b => { b.disabled = false; });
            }
        }

        function collectAiDraftFromPreview() {
            if (!aiRecipeDraft) return null;
            const title = document.getElementById('aiPreviewTitle')?.value?.trim() || '';
            const imageUrl = document.getElementById('aiPreviewImageUrl')?.value?.trim() || '';
            const steps = [];
            document.querySelectorAll('#aiPreviewSteps .ai-preview-step').forEach((row, idx) => {
                const type = row.dataset.type;
                if (type === 'product') {
                    const orig = (aiRecipeDraft.steps || [])[idx];
                    steps.push({
                        type: 'product',
                        content: row.dataset.productId,
                        product_id: row.dataset.productId,
                        product_name: row.querySelector('.ai-step-product-name')?.textContent?.replace(/\s*\(pending\)$/, '') || '',
                        pending: orig?.pending || false,
                        _pending_key: orig?._pending_key,
                        category: orig?.category,
                        quantity: {
                            value: parseFloat(row.querySelector('.ai-step-qty')?.value) || 100,
                            unit: row.querySelector('.ai-step-unit')?.value || 'grams'
                        }
                    });
                } else {
                    const timerCb = row.querySelector('.ai-step-timer-cb');
                    const step = {
                        type: 'instruction',
                        content: row.querySelector('.ai-step-instruction')?.value || '',
                        timer: null
                    };
                    if (timerCb?.checked) {
                        step.timer = {
                            hours: parseInt(row.querySelector('.ai-step-hours')?.value) || 0,
                            minutes: parseInt(row.querySelector('.ai-step-minutes')?.value) || 0
                        };
                    }
                    steps.push(step);
                }
            });
            return { title, steps, image_url: imageUrl, recipe_image_query: aiRecipeDraft?.recipe_image_query || title, lang: currentLang };
        }

        function renderAiRecipePreview(draft, animateSteps) {
            aiRecipeDraft = draft;
            showAiPreviewShell();
            document.getElementById('aiPreviewTitle').value = draft.title || '';
            const imgEl = document.getElementById('aiPreviewImage');
            const urlEl = document.getElementById('aiPreviewImageUrl');
            if (draft.image_url) {
                urlEl.value = draft.image_url;
                imgEl.src = draft.image_url;
                imgEl.style.display = 'block';
                updateAiImageActions(true);
            } else {
                urlEl.value = '';
                imgEl.style.display = 'none';
                updateAiImageActions(false);
            }
            const srcEl = document.getElementById('aiPreviewImageSource');
            if (srcEl) {
                if (draft.image_source) {
                    srcEl.textContent = `${translations.ai_image_source}: ${draft.image_source}`;
                } else if (draft.image_verified) {
                    srcEl.textContent = translations.ai_image_source + ' ✓';
                } else {
                    srcEl.textContent = '';
                }
            }
            urlEl.onchange = () => {
                const u = urlEl.value.trim();
                if (u) { imgEl.src = u; imgEl.style.display = 'block'; updateAiImageActions(true); }
                else { imgEl.style.display = 'none'; updateAiImageActions(false); }
            };
            const stepsEl = document.getElementById('aiPreviewSteps');
            stepsEl.innerHTML = '';
            (draft.steps || []).forEach((step, i) => {
                const el = buildAiPreviewStepEl(step, i, animateSteps, false);
                stepsEl.appendChild(el);
            });
            syncAiStepInsertZones();
            setAiInputMode(true);
        }

        function escapeHtml(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        async function openAiRecipeWorkspace() {
            closeModal('addRecipeChoiceModal');
            aiRecipeDraft = null;
            aiPendingProducts = [];
            aiHasDraft = false;
            productSelectionTarget = 'recipe';
            currentAiPreviewStepIndex = null;
            setAiWorkspaceMode(false);
            document.getElementById('aiRecipeInput').value = '';
            clearActivityFeed();
            document.getElementById('aiRecipePreviewEmpty').style.display = 'block';
            document.getElementById('aiRecipePreviewForm').style.display = 'none';
            document.getElementById('aiPreviewSteps').innerHTML = '';
            renderPendingProducts([]);
            setAiInputMode(false);
            setAiRecipeStatus('');
            const saveProg = document.getElementById('aiSaveTranslationProgress');
            if (saveProg) saveProg.style.display = 'none';
            openModal('aiRecipeWorkspaceModal');
            try {
                await loadAiRecipeConfig();
            } catch (e) {
                setAiRecipeStatus(translations.ai_config_missing, true);
            }
        }

        async function aiRecipeApiCall(action, extra) {
            const model = document.getElementById('aiRecipeModel')?.value || '';
            const body = Object.assign({ action, lang: currentLang, model }, extra || {});
            const r = await fetch('recipes_ai.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(body)
            });
            return parseJsonResponse(r);
        }

        function parseSseBlock(block) {
            let event = 'message';
            let data = '';
            block.split('\n').forEach(line => {
                if (line.startsWith('event:')) event = line.slice(6).trim();
                else if (line.startsWith('data:')) data += line.slice(5).trim();
            });
            if (!data) return null;
            try { return { event, data: JSON.parse(data) }; } catch (e) { return null; }
        }

        async function aiRecipeStream(action, payload) {
            const model = document.getElementById('aiRecipeModel')?.value || '';
            const body = Object.assign({ action, lang: currentLang, model }, payload);
            const r = await fetch('recipes_ai.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(body)
            });
            if (!r.ok || !r.body) {
                throw new Error(translations.error_occurred);
            }
            const reader = r.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let donePayload = null;
            while (true) {
                const { done, value } = await reader.read();
                if (done) {
                    break;
                }
                buffer += decoder.decode(value, { stream: true });
                const blocks = buffer.split('\n\n');
                buffer = blocks.pop() || '';
                for (const block of blocks) {
                    const parsed = parseSseBlock(block);
                    if (!parsed) {
                        continue;
                    }
                    const { event, data } = parsed;
                    if (event === 'status') {
                        setAiRecipeStatus(tReplace(data.text || 'ai_thinking'));
                    } else if (event === 'activity') {
                        appendActivity(data.kind || 'search', data);
                    } else if (event === 'recipe_image_query') {
                        if (!aiRecipeDraft) aiRecipeDraft = { title: '', steps: [], lang: currentLang };
                        aiRecipeDraft.recipe_image_query = data.query || '';
                    } else if (event === 'steps_clear') {
                        document.getElementById('aiPreviewSteps').innerHTML = '';
                        if (aiRecipeDraft) aiRecipeDraft.steps = [];
                    } else if (event === 'title') {
                        showAiPreviewShell();
                        document.getElementById('aiPreviewTitle').value = data.title || '';
                        if (!aiRecipeDraft) aiRecipeDraft = { title: '', steps: [], lang: currentLang };
                        aiRecipeDraft.title = data.title || '';
                        setAiInputMode(true);
                    } else if (event === 'step') {
                        showAiPreviewShell();
                        upsertAiPreviewStep(data.step, data.index ?? 0, true, false);
                    } else if (event === 'pending_products') {
                        renderPendingProducts(data.pending_products || data.items || []);
                    } else if (event === 'done') {
                        donePayload = data;
                    } else if (event === 'error') {
                        throw new Error(data.message || translations.error_occurred);
                    }
                }
            }
            return donePayload;
        }

        async function aiRecipeSubmit() {
            if (aiRecipeBusy) return;
            const text = document.getElementById('aiRecipeInput')?.value?.trim();
            if (!text) return;
            aiRecipeBusy = true;
            const btn = document.getElementById('aiRecipeSubmitBtn');
            if (btn) btn.disabled = true;
            setAiRecipeStatus(translations.ai_generating);
            try {
                await loadAiRecipeConfig();
                let result;
                if (aiHasDraft && aiRecipeDraft) {
                    const draft = collectAiDraftFromPreview();
                    clearActivityFeed();
                    setAiRecipeStatus(translations.ai_editing);
                    result = await aiRecipeStream('edit_stream', { instruction: text, draft });
                    if (!result?.draft) throw new Error(translations.ai_no_draft);
                    aiRecipeDraft = result.draft;
                    document.getElementById('aiPreviewTitle').value = result.draft.title || '';
                    if (result.draft.recipe_image_query) aiRecipeDraft.recipe_image_query = result.draft.recipe_image_query;
                    renderPendingProducts(result.pending_products || []);
                    setAiInputMode(true);
                } else {
                    clearActivityFeed();
                    showAiPreviewShell();
                    document.getElementById('aiPreviewSteps').innerHTML = '';
                    aiRecipeDraft = { title: '', steps: [], lang: currentLang };
                    renderPendingProducts([]);
                    result = await aiRecipeStream('generate_stream', { prompt: text });
                    if (!result?.draft) throw new Error(translations.ai_no_draft);
                    aiRecipeDraft = result.draft;
                    document.getElementById('aiPreviewTitle').value = result.draft.title || '';
                    if (result.draft.recipe_image_query) aiRecipeDraft.recipe_image_query = result.draft.recipe_image_query;
                    renderPendingProducts(result.pending_products || []);
                    setAiInputMode(true);
                }
                document.getElementById('aiRecipeInput').value = '';
                setAiRecipeStatus('');
            } catch (e) {
                showNotification(e.message || translations.error_occurred, 'error');
                setAiRecipeStatus(e.message || translations.error_occurred, true);
            } finally {
                aiRecipeBusy = false;
                if (btn) btn.disabled = false;
            }
        }

        async function aiRecipeFetchImageFile(url) {
            const r = await fetch('fetch_image_url.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ url })
            });
            const j = await parseJsonResponse(r);
            if (!j.success) return null;
            const bin = atob(j.data);
            const bytes = new Uint8Array(bin.length);
            for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
            return new File([bytes], j.filename || 'image.jpg', { type: j.mime || 'image/jpeg' });
        }

        async function aiRecipeRefreshImage() {
            const title = document.getElementById('aiPreviewTitle')?.value?.trim();
            if (!title) return;
            setAiRecipeStatus(translations.ai_generating);
            try {
                const query = aiRecipeDraft?.recipe_image_query || title;
                const opt = await openAiImagePicker({ kind: 'recipe', title, query });
                applyRecipeImageToPreview(opt);
            } catch (e) {
                if (e?.message !== 'cancelled') showNotification(e.message || translations.error_occurred, 'error');
            } finally {
                setAiRecipeStatus('');
            }
        }

        async function applyAiDraftToRecipeForm(draft) {
            skipRecipeModalReset = true;
            openModal('recipeModal');
            skipRecipeModalReset = false;
            document.getElementById('recipeModalTitle').textContent = translations.add_recipe;
            document.getElementById('recipeFormAction').value = 'add_recipe';
            document.getElementById('recipeFormId').value = '';
            document.getElementById('recipeLang').value = currentLang;
            document.getElementById('recipeTitle').value = draft.title || '';
            document.getElementById('steps').innerHTML = `<button type="button" onclick="addStep()">${translations.add_step}</button>`;
            steps = [];
            (draft.steps || []).forEach(step => addStep(step));
            if (draft.image_url) {
                const file = await aiRecipeFetchImageFile(draft.image_url);
                if (file) {
                    recipeImagePendingFile = file;
                    const preview = document.getElementById('recipeImagePreview');
                    const img = document.getElementById('recipePreviewImg');
                    img.src = URL.createObjectURL(file);
                    preview.style.display = 'block';
                    document.getElementById('recipeImageInput').style.display = 'none';
                    setRecipeImageCleared(false);
                } else {
                    const useUrl = document.getElementById('recipeImageUseUrl');
                    if (useUrl) { useUrl.checked = true; syncImageSourceMode('recipe'); }
                    document.getElementById('recipeImageUrlInput').value = draft.image_url;
                }
            }
        }

        function aiRecipeOpenInForm() {
            const draft = collectAiDraftFromPreview();
            if (!draft?.title) {
                showNotification(translations.ai_no_draft, 'error');
                return;
            }
            closeModal('aiRecipeWorkspaceModal');
            applyAiDraftToRecipeForm(draft);
        }

        async function aiRecipeSave() {
            if (aiPendingProducts.length) {
                showNotification(translations.ai_pending_required, 'error');
                return;
            }
            const draft = collectAiDraftFromPreview();
            if (!draft?.title || !draft.steps?.length) {
                showNotification(translations.ai_no_draft, 'error');
                return;
            }
            const missingProduct = draft.steps.some(s => s.type === 'product' && !(s.product_id || s.content));
            if (missingProduct) {
                showNotification(translations.select_product || translations.ai_pick_product, 'error');
                return;
            }
            const saveBtn = document.getElementById('aiRecipeSaveBtn');
            const progContainer = document.getElementById('aiSaveTranslationProgress');
            const progBar = document.getElementById('aiSaveTranslationProgressBar');
            const progText = document.getElementById('aiSaveTranslationProgressText');
            if (saveBtn) saveBtn.disabled = true;
            if (progContainer) progContainer.style.display = 'block';
            if (progBar) progBar.style.width = '0%';
            if (progText) progText.textContent = translations.translating + ' (0/0)';
            setAiRecipeStatus(translations.ai_saving);
            try {
                const model = document.getElementById('aiRecipeModel')?.value || '';
                const r = await fetch('recipes_ai.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ action: 'save_stream', lang: currentLang, model, draft })
                });
                if (!r.ok || !r.body) throw new Error(translations.error_occurred);
                const reader = r.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                let result = null;
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    buffer += decoder.decode(value, { stream: true });
                    const blocks = buffer.split('\n\n');
                    buffer = blocks.pop() || '';
                    for (const block of blocks) {
                        const parsed = parseSseBlock(block);
                        if (!parsed) continue;
                        if (parsed.event === 'progress') {
                            const d = parsed.data;
                            const total = d.total || 1;
                            const doneN = d.done || 0;
                            const pct = Math.round((doneN / total) * 100);
                            if (progBar) progBar.style.width = pct + '%';
                            if (progText) progText.textContent = `${translations.translating} (${doneN}/${total})`;
                        } else if (parsed.event === 'done') {
                            result = parsed.data;
                        } else if (parsed.event === 'error') {
                            throw new Error(parsed.data.message || translations.error_occurred);
                        }
                    }
                }
                if (!result?.success) throw new Error(result?.message || 'failed');
                showNotification(translations.recipe_added, 'success');
                closeModal('aiRecipeWorkspaceModal');
                refreshRecipesGrid();
            } catch (e) {
                showNotification(e.message || translations.error_occurred, 'error');
                setAiRecipeStatus(e.message || translations.error_occurred, true);
            } finally {
                if (saveBtn) saveBtn.disabled = false;
                setAiRecipeStatus('');
                setTimeout(() => {
                    if (progContainer) progContainer.style.display = 'none';
                    if (progBar) progBar.style.width = '0%';
                    if (progText) progText.textContent = '';
                }, 600);
            }
        }


    </script>
