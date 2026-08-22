<body>
    <div class="loading" id="loading" style="display: none;"><?php echo $translations[$lang]['translating']; ?></div>
    <div class="loading ai-loading" id="aiLoading" style="display: none;"><?php echo $translations[$lang]['ai_generating']; ?></div>
    <div class="notification-container" id="notification-container"></div>
    
    <header class="app-top-bar">
        <div class="recipe-search-container">
            <div class="recipe-search-wrapper">
                <svg class="recipe-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="<?php echo $theme === 'dark' ? '#ffffff' : '#000000'; ?>" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="M21 21l-4.35-4.35"></path>
                </svg>
                <input type="text" id="recipeSearchInput" class="recipe-search-input" placeholder="<?php echo $translations[$lang]['search_recipe']; ?>" oninput="filterRecipes()">
            </div>
        </div>
        <div class="sidebar">
            <button onclick="openAddRecipeChoice()" title="<?php echo $translations[$lang]['add_recipe']; ?>">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="<?php echo $theme === 'dark' ? '#ffffff' : '#000000'; ?>" stroke-width="2">
                    <path d="M12 4v16m8-8H4"></path>
                </svg>
            </button>
            <button onclick="openModal('productModal')" title="<?php echo $translations[$lang]['open_products']; ?>">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="<?php echo $theme === 'dark' ? '#ffffff' : '#000000'; ?>" stroke-width="2">
                    <path d="M3 3h18v18H3z"></path>
                    <path d="M12 8v8m-4-4h8"></path>
                </svg>
            </button>
            <button onclick="openModal('searchRecipeModal')" title="<?php echo $translations[$lang]['search_recipe']; ?>">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="<?php echo $theme === 'dark' ? '#ffffff' : '#000000'; ?>" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="M21 21l-4.35-4.35"></path>
                </svg>
            </button>
            <button onclick="openModal('settingsModal')" title="<?php echo $translations[$lang]['settings']; ?>">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="<?php echo $theme === 'dark' ? '#ffffff' : '#000000'; ?>" stroke-width="2">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
            </button>
            <button id="continueRecipeBtn" onclick="continueRecipe()" title="<?php echo $translations[$lang]['continue_recipe']; ?>" style="display: <?php echo $current_recipe ? 'inline-flex' : 'none'; ?>;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="<?php echo $theme === 'dark' ? '#ffffff' : '#000000'; ?>" stroke-width="2">
                    <path d="M5 4v16l11-8z"></path>
                </svg>
            </button>
        </div>
    </header>

    <div class="main-content">
        <div class="recipe-grid" id="recipeGrid">
            <?php 
            $page = isset($_GET['recipe_page']) ? (int)$_GET['recipe_page'] : 1;
            $items_per_page = $config['items_per_page'];
            $total_recipes = count($recipes);
            $total_pages = ceil($total_recipes / $items_per_page);
            $start = ($page - 1) * $items_per_page;
            $paged_recipes = array_slice($recipes, $start, $items_per_page);
            foreach ($paged_recipes as $recipe): 
                $image_src = isset($recipe['image']) && is_file(__DIR__ . '/../' . $recipes_dir . '/' . $recipe['id'] . '/' . $recipe['image']) ? $recipes_dir . '/' . $recipe['id'] . '/' . $recipe['image'] : 'default_recipe.jpg';
            ?>
                <div class="recipe-card" onclick="showRecipe('<?php echo $recipe['id']; ?>')">
                    <img src="<?php echo $image_src; ?>" alt="<?php echo htmlspecialchars($recipe['title'][$lang]); ?>">
                    <h3><?php echo htmlspecialchars($recipe['title'][$lang]); ?></h3>
                    <div class="card-actions">
                        <button onclick="shareRecipe('<?php echo $recipe['id']; ?>', event);" title="<?php echo $translations[$lang]['share']; ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="<?php echo $theme === 'dark' ? '#ffffff' : '#000000'; ?>" stroke-width="2">
                                <circle cx="18" cy="5" r="3"></circle>
                                <circle cx="6" cy="12" r="3"></circle>
                                <circle cx="18" cy="19" r="3"></circle>
                                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                            </svg>
                        </button>
                        <?php if ($userRole >= 1): ?>
                            <button onclick="editRecipe('<?php echo $recipe['id']; ?>'); event.stopPropagation();" title="<?php echo $translations[$lang]['edit']; ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="<?php echo $theme === 'dark' ? '#ffffff' : '#000000'; ?>" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </button>
                        <?php endif; ?>
                        <?php if ($userRole >= 2): ?>
                            <button onclick="deleteRecipe('<?php echo $recipe['id']; ?>'); event.stopPropagation();" title="<?php echo $translations[$lang]['delete']; ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="<?php echo $theme === 'dark' ? '#ffffff' : '#000000'; ?>" stroke-width="2">
                                    <path d="M3 6h18"></path>
                                    <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                                    <path d="M10 11v6"></path>
                                    <path d="M14 11v6"></path>
                                </svg>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($total_pages > 1): ?>
        <div class="pagination" id="recipePagination">
            <?php if ($page > 1): ?>
                <a href="?lang=<?php echo $lang; ?>&theme=<?php echo $theme; ?>&color=<?php echo urlencode($color); ?>&recipe_page=<?php echo $page - 1; ?>">&laquo; <?php echo $translations[$lang]['previous']; ?></a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?lang=<?php echo $lang; ?>&theme=<?php echo $theme; ?>&color=<?php echo urlencode($color); ?>&recipe_page=<?php echo $i; ?>" <?php echo $i === $page ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?lang=<?php echo $lang; ?>&theme=<?php echo $theme; ?>&color=<?php echo urlencode($color); ?>&recipe_page=<?php echo $page + 1; ?>"><?php echo $translations[$lang]['next']; ?> &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="modal" id="recipeViewModal">
        <div class="modal-content recipe-view" id="recipeView">
            <button class="modal-close" onclick="backToGrid()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2">
                    <path d="M18 6L6 18"></path>
                    <path d="M6 6l12 12"></path>
                </svg>
            </button>
            <div id="recipeViewContent"></div>
        </div>
    </div>

    <!-- Share Modal -->
    <div class="modal" id="shareModal">
        <div class="modal-content share-modal">
            <button class="modal-close" onclick="closeModal('shareModal')">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2">
                    <path d="M18 6L6 18"></path>
                    <path d="M6 6l12 12"></path>
                </svg>
            </button>
            <h2 style="text-align: center;"><?php echo $translations[$lang]['share_recipe']; ?></h2>

            <div class="share-controls">
                <div class="share-row">
                    <label><?php echo $translations[$lang]['language']; ?>:</label>
                    <select id="shareLangSelect" class="custom-select">
                        <option value="en">English</option>
                        <option value="uk">Українська</option>
                        <option value="ru">Русский</option>
                        <option value="be">Беларуская</option>
                    </select>
                </div>

                <div class="share-row">
                    <label><?php echo $translations[$lang]['theme']; ?>:</label>
                    <select id="shareThemeSelect" class="custom-select">
                        <option value="light"><?php echo $translations[$lang]['light']; ?></option>
                        <option value="dark"><?php echo $translations[$lang]['dark']; ?></option>
                    </select>
                </div>

                <button class="share-generate-btn" onclick="generateShareImage()"><?php echo $translations[$lang]['generate_image']; ?></button>
            </div>
            
            <!-- In the shareModal modal, replace the preview block with this one -->
            <div class="share-preview-container" id="sharePreviewContainer" style="display: none;">
                <h3><?php echo $translations[$lang]['preview']; ?></h3>
                <div class="share-preview-wrapper" id="sharePreviewWrapper">
                    <div id="shareRecipePreview" class="share-recipe-preview"></div>
                </div>
                <div class="share-actions">
                    <button onclick="copyShareImage()"><?php echo $translations[$lang]['copy']; ?></button>
                    <button onclick="downloadShareImage()"><?php echo $translations[$lang]['download']; ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Recipe Modal -->
    <div class="modal" id="searchRecipeModal">
        <div class="modal-content product-modal">
            <button class="modal-close" onclick="closeModal('searchRecipeModal')">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2">
                    <path d="M18 6L6 18"></path>
                    <path d="M6 6l12 12"></path>
                </svg>
            </button>
            <h2 style="text-align: center;"><?php echo $translations[$lang]['search_recipe']; ?></h2>
            <div class="product-grid-container">
                <div class="product-grid" id="searchProductGrid">
                    <?php 
                    $search_page = isset($_GET['search_page']) ? (int)$_GET['search_page'] : 1;
                    $start = ($search_page - 1) * $items_per_page;
                    $paged_products = array_slice($products, $start, $items_per_page);
                    foreach ($paged_products as $product): ?>
                        <div class="product-card" data-id="<?php echo $product['id']; ?>" onclick="toggleProductSelection(this)">
                            <img src="<?php echo htmlspecialchars(recipes_product_thumb_public_path($product, $products_dir)); ?>" 
                                alt="<?php echo htmlspecialchars($product['name'][$lang]); ?>">
                            <p><?php echo htmlspecialchars($product['name'][$lang]); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="pagination">
                    <?php 
                    $total_search_pages = ceil(count($products) / $items_per_page);
                    if ($search_page > 1): ?>
                        <a href="#" onclick="changeSearchPage(<?php echo $search_page - 1; ?>); return false;">&laquo; <?php echo $translations[$lang]['previous']; ?></a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_search_pages; $i++): ?>
                        <a href="#" onclick="changeSearchPage(<?php echo $i; ?>); return false;" <?php echo $i === $search_page ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($search_page < $total_search_pages): ?>
                        <a href="#" onclick="changeSearchPage(<?php echo $search_page + 1; ?>); return false;"><?php echo $translations[$lang]['next']; ?> &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="selected-products" id="selectedProducts"></div>
            <button class="search-button" onclick="searchRecipesByProducts()"><?php echo $translations[$lang]['search']; ?></button>
            <div id="searchResults"></div>
        </div>
    </div>

    <!-- Product Modal -->
    <div class="modal" id="productModal">
        <div class="modal-content product-modal">
            <button class="modal-close" onclick="closeModal('productModal')">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2">
                    <path d="M18 6L6 18"></path>
                    <path d="M6 6l12 12"></path>
                </svg>
            </button>
            <h2 style="text-align: center;"><?php echo $translations[$lang]['products_management']; ?></h2>
            <div class="product-management-toolbar">
                <div class="product-management-actions">
                    <button id="sortProductsBtn" class="product-management-btn" onclick="toggleProductsSort()" title="<?php echo $translations[$lang]['sort_by_name']; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m3 16 4 4 4-4"/>
                            <path d="M7 20V4"/>
                            <path d="m21 8-4-4-4 4"/>
                            <path d="M17 4v16"/>
                        </svg>
                        <span id="sortProductsText"><?php echo $translations[$lang]['sort_by_name']; ?></span>
                    </button>
                    <button type="button" class="product-management-btn product-management-btn-primary" onclick="openModal('addProductModal')"><?php echo htmlspecialchars($translations[$lang]['add_new_product']); ?></button>
                </div>

                <div class="product-search product-management-search">
                    <div class="search-container">
                        <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--text-color)" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="M21 21l-4.35-4.35"></path>
                        </svg>
                        <input type="text" id="productSearch" placeholder="<?php echo $translations[$lang]['search_products']; ?>" 
                            oninput="filterProducts(this.value)">
                    </div>
                </div>
            </div>
            
            <!-- Product Grid -->
            <div class="product-grid-container">
                <div class="product-grid" id="productGrid">
                    <?php 
                    $product_page = isset($_GET['product_page']) ? (int)$_GET['product_page'] : 1;
                    $start = ($product_page - 1) * $items_per_page;
                    $paged_products = array_slice($products, $start, $items_per_page);
                    foreach ($paged_products as $product): 
                        $image_src = recipes_product_thumb_public_path($product, $products_dir);
                    ?>
                        <div class="product-card" data-id="<?php echo $product['id']; ?>" data-name="<?php echo htmlspecialchars(strtolower($product['name'][$lang])); ?>">
                            <img src="<?php echo htmlspecialchars($image_src); ?>" alt="<?php echo htmlspecialchars($product['name'][$lang]); ?>">
                            <p><?php echo htmlspecialchars($product['name'][$lang]); ?></p>
                            <div class="product-card-actions">
                                <?php if ($userRole >= 1): ?>
                                    <button onclick="editProductName('<?php echo $product['id']; ?>'); event.stopPropagation();" title="<?php echo $translations[$lang]['edit_name']; ?>">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="<?php echo $theme === 'dark' ? '#ffffff' : '#000000'; ?>" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </button>
                                <?php endif; ?>
                                <?php if ($userRole >= 2): ?>
                                    <button onclick="deleteProduct('<?php echo $product['id']; ?>'); event.stopPropagation();" title="<?php echo $translations[$lang]['delete']; ?>">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="<?php echo $theme === 'dark' ? '#ffffff' : '#000000'; ?>" stroke-width="2">
                                            <path d="M3 6h18"></path>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="pagination">
                    <?php 
                    $total_product_pages = ceil(count($products) / $items_per_page);
                    if ($product_page > 1): ?>
                        <a href="#" onclick="changeProductPage(<?php echo $product_page - 1; ?>); return false;">&laquo; <?php echo $translations[$lang]['previous']; ?></a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_product_pages; $i++): ?>
                        <a href="#" onclick="changeProductPage(<?php echo $i; ?>); return false;" <?php echo $i === $product_page ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($product_page < $total_product_pages): ?>
                        <a href="#" onclick="changeProductPage(<?php echo $product_page + 1; ?>); return false;"><?php echo $translations[$lang]['next']; ?> &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="productViewModal" style="z-index: 2200;">
        <div class="modal-content product-preview-modal">
            <button class="modal-close" onclick="closeModal('productViewModal')">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2">
                    <path d="M18 6L6 18"></path>
                    <path d="M6 6l12 12"></path>
                </svg>
            </button>
            <h2 style="text-align: center;" id="productViewModalTitle"><?php echo htmlspecialchars($translations[$lang]['product_view']); ?></h2>
            <div class="product-preview-modal-body">
                <img id="productViewModalImg" src="" alt="" class="product-view-detail-img" data-pid="">
                <p id="productViewModalName" class="product-preview-modal-name"></p>
            </div>
        </div>
    </div>

    <div class="modal" id="addProductModal" style="z-index: 2100;">
        <div class="modal-content product-modal add-product-modal-content">
            <button class="modal-close" onclick="closeModal('addProductModal')">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2">
                    <path d="M18 6L6 18"></path>
                    <path d="M6 6l12 12"></path>
                </svg>
            </button>
            <div class="add-product-modal-body">
                <h2 class="add-product-modal-heading"><?php echo htmlspecialchars($translations[$lang]['add_new_product']); ?></h2>
                <form id="productForm" class="add-product-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_product">
                    <input type="hidden" name="lang" id="productFormLang">
                    <div class="add-product-stack">
                        <section class="add-product-panel" aria-label="<?php echo htmlspecialchars($translations[$lang]['language']); ?>">
                            <h3 class="add-product-panel-title"><?php echo htmlspecialchars($translations[$lang]['language']); ?></h3>
                            <div class="add-product-field-block">
                                <select id="productLang" class="custom-select add-product-control" aria-label="<?php echo htmlspecialchars($translations[$lang]['language']); ?>">
                                    <option value="en">English</option>
                                    <option value="uk">Українська</option>
                                    <option value="ru">Русский</option>
                                    <option value="be">Беларуская</option>
                                </select>
                            </div>
                        </section>
                        <section class="add-product-panel" aria-label="<?php echo htmlspecialchars($translations[$lang]['product_form_section_basic']); ?>">
                            <h3 class="add-product-panel-title"><?php echo htmlspecialchars($translations[$lang]['product_form_section_basic']); ?></h3>
                            <div class="add-product-fields-grid">
                                <div class="add-product-field-block">
                                    <label class="add-product-field-label" for="addProductNameInput"><?php echo htmlspecialchars($translations[$lang]['product_name']); ?></label>
                                    <input type="text" name="name" id="addProductNameInput" class="add-product-control" placeholder="<?php echo htmlspecialchars($translations[$lang]['product_name']); ?>" required>
                                </div>
                                <div class="add-product-field-block">
                                    <label class="add-product-field-label" for="productCategory"><?php echo htmlspecialchars($translations[$lang]['category']); ?></label>
                                    <select name="category" id="productCategory" class="custom-select add-product-control" required>
                                        <option value="">-- <?php echo htmlspecialchars($translations[$lang]['category']); ?> --</option>
                                        <?php foreach ($translations[$lang]['categories'] as $catKey => $catName): ?>
                                            <option value="<?php echo htmlspecialchars($catKey); ?>"><?php echo htmlspecialchars($catName); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </section>
                        <section class="add-product-panel" aria-label="<?php echo htmlspecialchars($translations[$lang]['product_form_section_image']); ?>">
                            <h3 class="add-product-panel-title"><?php echo htmlspecialchars($translations[$lang]['product_form_section_image']); ?></h3>
                            <div class="add-product-image-structure">
                                <div class="add-product-inner add-product-inner--switch">
                                    <div class="image-source-switch-row add-product-image-source">
                                        <span class="image-source-label"><?php echo htmlspecialchars($translations[$lang]['image_source_file']); ?></span>
                                        <label class="form-switch">
                                            <input type="checkbox" id="productImageUseUrl" aria-label="<?php echo htmlspecialchars($translations[$lang]['image_source_url']); ?>">
                                            <span class="form-switch-slider"></span>
                                        </label>
                                        <span class="image-source-label"><?php echo htmlspecialchars($translations[$lang]['image_source_url']); ?></span>
                                    </div>
                                </div>
                                <div class="add-product-inner add-product-inner--inputs">
                                    <div id="productImageFileRow" class="image-file-row add-product-file-slot">
                                        <input type="file" name="image" accept="image/*" required id="productImageInput" class="add-product-control">
                                    </div>
                                    <div id="productImageUrlRow" class="image-url-row add-product-url-slot" style="display:none;">
                                        <input type="url" id="productImageUrlInput" placeholder="<?php echo htmlspecialchars($translations[$lang]['image_url_placeholder']); ?>" autocomplete="off" class="image-url-input add-product-control">
                                    </div>
                                </div>
                                <div class="add-product-inner add-product-inner--preview">
                                    <div class="image-preview-container add-product-preview" id="productImagePreview" style="display:none;">
                                        <div class="image-preview-overlay" id="productImageOverlay" style="display:none;">
                                            <div class="image-preview-spinner"></div>
                                            <span>Removing background...</span>
                                        </div>
                                        <img class="image-preview" id="productPreviewImg" alt="<?php echo htmlspecialchars($translations[$lang]['click_to_edit_image']); ?>">
                                        <span class="image-preview-hint"><?php echo htmlspecialchars($translations[$lang]['click_to_edit_image']); ?></span>
                                    </div>
                                    <div class="image-tool-actions" id="productBgActions" style="display:none;">
                                        <button type="button" class="image-tool-btn image-tool-btn-secondary" id="productBgUndoBtn" onclick="undoBackgroundRemoval('product')">Undo background removal</button>
                                    </div>
                                </div>
                            </div>
                        </section>
                        <footer class="add-product-panel add-product-panel-actions">
                            <button type="submit" id="productFormSubmit" class="add-product-submit-btn"><?php echo htmlspecialchars($translations[$lang]['save']); ?></button>
                        </footer>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Product Selection Modal -->
    <div class="modal" id="productSelectionModal" style="z-index: 2000;">
        <div class="modal-content product-modal">
            <button class="modal-close" onclick="closeModal('productSelectionModal')">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2">
                    <path d="M18 6L6 18"></path>
                    <path d="M6 6l12 12"></path>
                </svg>
            </button>
            <h2 style="text-align: center;"><?php echo $translations[$lang]['select_product']; ?></h2>
            
            <div class="product-search">
                <div class="search-container">
                    <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--text-color)" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="M21 21l-4.35-4.35"></path>
                    </svg>
                    <input type="text" id="productSelectionSearch" 
                        placeholder="<?php echo $translations[$lang]['search_products']; ?>" 
                        oninput="filterSelectionProducts(this.value)">
                </div>
            </div>
            
            <div class="product-grid-container">
                <div class="product-grid" id="productSelectionGrid">
                    <?php 
                    $select_page = isset($_GET['select_page']) ? (int)$_GET['select_page'] : 1;
                    $start = ($select_page - 1) * $items_per_page;
                    $paged_products = array_slice($products, $start, $items_per_page);
                    foreach ($paged_products as $product): ?>
                        <div class="product-card" data-id="<?php echo $product['id']; ?>" 
                            data-name="<?php echo htmlspecialchars(strtolower($product['name'][$lang])); ?>">
                            <img src="<?php echo htmlspecialchars(recipes_product_thumb_public_path($product, $products_dir)); ?>" 
                                alt="<?php echo htmlspecialchars($product['name'][$lang]); ?>">
                            <p><?php echo htmlspecialchars($product['name'][$lang]); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="pagination">
                    <?php 
                    $total_select_pages = ceil(count($products) / $items_per_page);
                    if ($select_page > 1): ?>
                        <a href="#" onclick="changeSelectPage(<?php echo $select_page - 1; ?>); return false;">&laquo; <?php echo $translations[$lang]['previous']; ?></a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_select_pages; $i++): ?>
                        <a href="#" onclick="changeSelectPage(<?php echo $i; ?>); return false;" <?php echo $i === $select_page ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($select_page < $total_select_pages): ?>
                        <a href="#" onclick="changeSelectPage(<?php echo $select_page + 1; ?>); return false;"><?php echo $translations[$lang]['next']; ?> &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Recipe Choice Modal -->
    <div class="modal" id="addRecipeChoiceModal">
        <div class="modal-content add-recipe-choice-modal">
            <button class="modal-close" onclick="closeModal('addRecipeChoiceModal')">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2">
                    <path d="M18 6L6 18"></path>
                    <path d="M6 6l12 12"></path>
                </svg>
            </button>
            <h2><?php echo htmlspecialchars($translations[$lang]['add_recipe']); ?></h2>
            <div class="add-recipe-choice-grid">
                <button type="button" class="add-recipe-choice-btn" onclick="openManualRecipeForm()">
                    <span class="add-recipe-choice-icon">✏️</span>
                    <span class="add-recipe-choice-label"><?php echo htmlspecialchars($translations[$lang]['add_recipe_manual']); ?></span>
                    <span class="add-recipe-choice-desc"><?php echo htmlspecialchars($translations[$lang]['add_recipe_manual_desc']); ?></span>
                </button>
                <button type="button" class="add-recipe-choice-btn add-recipe-choice-ai" onclick="openAiRecipeWorkspace()">
                    <span class="add-recipe-choice-icon">✨</span>
                    <span class="add-recipe-choice-label"><?php echo htmlspecialchars($translations[$lang]['add_recipe_from_prompt']); ?></span>
                    <span class="add-recipe-choice-desc"><?php echo htmlspecialchars($translations[$lang]['add_recipe_from_prompt_desc']); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- AI Recipe Workspace -->
    <div class="modal" id="aiRecipeWorkspaceModal">
        <div class="modal-content ai-recipe-workspace" id="aiRecipeWorkspaceContent">
            <button class="modal-close" onclick="closeModal('aiRecipeWorkspaceModal')">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2">
                    <path d="M18 6L6 18"></path>
                    <path d="M6 6l12 12"></path>
                </svg>
            </button>
            <header class="ai-workspace-header">
                <h2 id="aiWorkspaceTitle"><?php echo htmlspecialchars($translations[$lang]['ai_recipe_workspace']); ?></h2>
                <?php if ($userRole >= 1): ?>
                    <div class="ai-model-field">
                        <span><?php echo htmlspecialchars($translations[$lang]['ai_model_label'] ?? 'AI model'); ?></span>
                        <div class="ai-model-dropdown" id="aiModelDropdown">
                            <select id="aiRecipeModel" class="custom-select ai-model-select"></select>
                            <button type="button" class="ai-model-toggle" id="aiModelToggle" aria-expanded="false" aria-haspopup="listbox">
                                <svg class="ai-model-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3-1.9 5.1L5 10l5.1 1.9L12 17l1.9-5.1L19 10l-5.1-1.9L12 3Z"></path><path d="m19 15-.9 2.1L16 18l2.1.9L19 21l.9-2.1L22 18l-2.1-.9L19 15Z"></path></svg>
                                <span class="ai-model-value"></span>
                                <svg class="ai-model-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                            </button>
                            <div class="ai-model-menu" id="aiModelMenu" role="listbox"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </header>
            <div class="ai-workspace-body">
                <aside class="ai-workspace-side ai-workspace-zone ai-zone-prompt" id="aiRecipePromptZone">
                    <div id="aiPromptPanel">
                        <h3 class="ai-zone-title" id="aiInputZoneTitle"><?php echo htmlspecialchars($translations[$lang]['ai_prompt_label']); ?></h3>
                        <textarea id="aiRecipeInput" rows="10" placeholder="<?php echo htmlspecialchars($translations[$lang]['ai_prompt_placeholder']); ?>"></textarea>
                        <button type="button" class="ai-submit-btn" id="aiRecipeSubmitBtn" onclick="aiRecipeSubmit()"><?php echo htmlspecialchars($translations[$lang]['ai_generate']); ?></button>
                        <div class="ai-activity-feed" id="aiActivityFeed"></div>
                    </div>
                    <div id="aiManualPanel" style="display:none;">
                        <h3 class="ai-zone-title"><?php echo htmlspecialchars($translations[$lang]['ai_manual_zone_title']); ?></h3>
                        <p class="ai-manual-hint"><?php echo htmlspecialchars($translations[$lang]['ai_manual_zone_hint']); ?></p>
                        <button type="button" class="ai-manual-btn ai-manual-btn-ingredient" onclick="aiManualAddIngredient()"><?php echo htmlspecialchars($translations[$lang]['ai_add_ingredient']); ?></button>
                        <button type="button" class="ai-manual-btn ai-manual-btn-step" onclick="aiManualAddInstruction()"><?php echo htmlspecialchars($translations[$lang]['ai_add_instruction_step']); ?></button>
                    </div>
                </aside>
                <main class="ai-workspace-main ai-workspace-zone ai-zone-preview">
                    <h3 class="ai-zone-title"><?php echo htmlspecialchars($translations[$lang]['ai_preview']); ?></h3>
                    <div id="aiRecipePreviewEmpty" class="ai-preview-empty"><?php echo htmlspecialchars($translations[$lang]['ai_preview_empty']); ?></div>
                    <div id="aiRecipePreviewForm" style="display:none;">
                        <input type="text" id="aiPreviewTitle" placeholder="<?php echo htmlspecialchars($translations[$lang]['recipe_title']); ?>">
                        <div class="ai-preview-image-block ai-zone-image">
                            <div class="ai-preview-image-row">
                                <img id="aiPreviewImage" alt="" class="ai-preview-image">
                                <div class="ai-preview-image-meta">
                                    <span id="aiPreviewImageSource" class="ai-image-source-label"></span>
                                    <button type="button" class="ai-choose-image-btn" id="aiChooseImageBtn" onclick="aiRecipeRefreshImage()"><?php echo htmlspecialchars($translations[$lang]['ai_choose_image'] ?? 'Choose photo'); ?></button>
                                    <button type="button" class="ai-refresh-image-btn" onclick="aiRecipeRefreshImage()"><?php echo htmlspecialchars($translations[$lang]['ai_refresh_image']); ?></button>
                                </div>
                            </div>
                            <input type="url" id="aiPreviewImageUrl" placeholder="<?php echo htmlspecialchars($translations[$lang]['image_url_placeholder']); ?>" class="image-url-input">
                        </div>
                        <div id="aiPreviewSteps" class="ai-preview-steps"></div>
                        <div id="aiPreviewManualToolbar" class="ai-preview-manual-toolbar" style="display:none;">
                            <button type="button" class="ai-manual-btn ai-manual-btn-ingredient" onclick="aiManualAddIngredient()"><?php echo htmlspecialchars($translations[$lang]['ai_add_ingredient']); ?></button>
                            <button type="button" class="ai-manual-btn ai-manual-btn-step" onclick="aiManualAddInstruction()"><?php echo htmlspecialchars($translations[$lang]['ai_add_instruction_step']); ?></button>
                        </div>
                        <div id="aiPendingProducts" class="ai-pending-products ai-zone-pending" style="display:none;"></div>
                        <div id="aiSaveTranslationProgress" class="ai-save-progress" style="display:none;">
                            <div class="ai-save-progress-track">
                                <div id="aiSaveTranslationProgressBar" class="ai-save-progress-bar"></div>
                                <span id="aiSaveTranslationProgressText" class="ai-save-progress-text"></span>
                            </div>
                        </div>
                        <div class="ai-workspace-save-row">
                            <button type="button" id="aiRecipeSaveBtn" onclick="aiRecipeSave()"><?php echo htmlspecialchars($translations[$lang]['save_recipe']); ?></button>
                            <button type="button" class="ai-open-manual-btn" id="aiOpenInFormBtn" onclick="aiRecipeOpenInForm()"><?php echo htmlspecialchars($translations[$lang]['ai_open_in_form']); ?></button>
                        </div>
                    </div>
                </main>
            </div>
            <div class="ai-workspace-status" id="aiRecipeStatus"></div>
        </div>
    </div>

    <!-- AI Image Picker Modal -->
    <div class="modal" id="aiImagePickerModal" style="z-index: 2300;">
        <div class="modal-content ai-image-picker-modal">
            <button class="modal-close" onclick="closeAiImagePicker()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2">
                    <path d="M18 6L6 18"></path>
                    <path d="M6 6l12 12"></path>
                </svg>
            </button>
            <h2 id="aiImagePickerTitle"><?php echo htmlspecialchars($translations[$lang]['ai_pick_image_title']); ?></h2>
            <p class="ai-image-picker-hint" id="aiImagePickerHint"><?php echo htmlspecialchars($translations[$lang]['ai_pick_image_hint']); ?></p>
            <div id="aiImagePickerLoading" class="ai-image-picker-loading" style="display:none;"><?php echo htmlspecialchars($translations[$lang]['ai_pick_image_loading']); ?></div>
            <div id="aiImagePickerGrid" class="ai-image-picker-grid"></div>
            <div class="ai-image-picker-actions">
                <button type="button" class="ai-image-picker-cancel" onclick="closeAiImagePicker()"><?php echo htmlspecialchars($translations[$lang]['cancel']); ?></button>
            </div>
        </div>
    </div>

    <!-- Delete Confirm Modal -->
    <div class="modal" id="deleteConfirmModal" style="z-index: 2200;">
        <div class="modal-content delete-confirm-modal">
            <h2 id="deleteConfirmTitle"><?php echo htmlspecialchars($translations[$lang]['delete_confirm_title']); ?></h2>
            <p id="deleteConfirmMessage"></p>
            <div class="delete-confirm-actions">
                <button type="button" class="delete-confirm-cancel" onclick="closeDeleteConfirm(false)"><?php echo htmlspecialchars($translations[$lang]['cancel']); ?></button>
                <button type="button" class="delete-confirm-ok" onclick="closeDeleteConfirm(true)"><?php echo htmlspecialchars($translations[$lang]['delete']); ?></button>
            </div>
        </div>
    </div>

    <!-- Recipe Modal -->
    <div class="modal" id="recipeModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('recipeModal')">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2">
                    <path d="M18 6L6 18"></path>
                    <path d="M6 6l12 12"></path>
                </svg>
            </button>
            <h2 id="recipeModalTitle"><?php echo $translations[$lang]['add_recipe']; ?></h2>
            <select id="recipeLang" class="custom-select">
                <option value="en">English</option>
                <option value="uk">Українська</option>
                <option value="ru">Русский</option>
                <option value="be">Беларуская</option>
            </select>
            <form id="recipeForm" enctype="multipart/form-data">
                <input type="hidden" name="action" id="recipeFormAction" value="add_recipe">
                <input type="hidden" name="lang" id="recipeFormLang">
                <input type="hidden" name="recipe_id" id="recipeFormId">
                <input type="hidden" name="clear_recipe_image" id="recipeClearImageFlag" value="0">
                <input type="text" name="title" id="recipeTitle" placeholder="<?php echo $translations[$lang]['recipe_title']; ?>" required>
                <div class="image-source-switch-row recipe-form-image-source" id="recipeImageSourceSwitchRow">
                    <span class="image-source-label"><?php echo htmlspecialchars($translations[$lang]['image_source_file']); ?></span>
                    <label class="form-switch">
                        <input type="checkbox" id="recipeImageUseUrl" aria-label="<?php echo htmlspecialchars($translations[$lang]['image_source_url']); ?>">
                        <span class="form-switch-slider"></span>
                    </label>
                    <span class="image-source-label"><?php echo htmlspecialchars($translations[$lang]['image_source_url']); ?></span>
                </div>
                <div id="recipeImageFileRow" class="image-file-row recipe-image-file-row">
                <input type="file" name="image" accept="image/*" id="recipeImageInput">
                </div>
                <div id="recipeImageUrlRow" class="image-url-row recipe-image-url-row" style="display:none;">
                    <input type="url" id="recipeImageUrlInput" placeholder="<?php echo htmlspecialchars($translations[$lang]['image_url_placeholder']); ?>" autocomplete="off" class="image-url-input">
                </div>
                <div class="image-preview-container recipe-image-preview" id="recipeImagePreview" style="display:none;">
                    <div class="image-preview-overlay" id="recipeImageOverlay" style="display:none;">
                        <div class="image-preview-spinner"></div>
                        <span>Removing background...</span>
                    </div>
                    <div class="image-preview-media">
                        <img class="image-preview" id="recipePreviewImg" alt="<?php echo $translations[$lang]['click_to_edit_image']; ?>">
                        <button type="button" class="image-preview-clear" id="recipeImageClearBtn" onclick="clearExistingRecipeImage(event)" aria-label="Remove image">×</button>
                    </div>
                    <span class="image-preview-hint"><?php echo $translations[$lang]['click_to_edit_image']; ?></span>
                </div>
                <div class="image-tool-actions" id="recipeBgActions" style="display:none;">
                    <button type="button" class="image-tool-btn" id="recipeBgRemoveBtn" onclick="removeBackgroundForPrefix('recipe')">Remove background</button>
                    <button type="button" class="image-tool-btn image-tool-btn-secondary" id="recipeBgUndoBtn" onclick="undoBackgroundRemoval('recipe')" style="display:none;">Undo background removal</button>
                </div>
                <div id="steps" class="recipe-steps-block" style="margin-bottom: 20px;">
                    <button type="button" onclick="addStep()"><?php echo $translations[$lang]['add_step']; ?></button>
                </div>
                <div id="recipeTranslationProgress" style="display: none; margin: 10px 0;">
                    <div style="background: rgba(128,128,128,0.2); border-radius: 10px; height: 24px; position: relative; overflow: hidden;">
                        <div id="recipeTranslationProgressBar" style="background: var(--primary-color); height: 100%; width: 0%; border-radius: 10px; transition: width 0.3s ease;"></div>
                        <span id="recipeTranslationProgressText" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 12px; font-weight: bold; color: var(--text-color);"></span>
                    </div>
                </div>
                <button type="submit" id="recipeFormSubmit"><?php echo $translations[$lang]['save']; ?></button>
            </form>
        </div>
    </div>

    <!-- Edit Product Name Modal -->
    <div class="modal" id="editProductModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('editProductModal')">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2">
                    <path d="M18 6L6 18"></path>
                    <path d="M6 6l12 12"></path>
                </svg>
            </button>
            <h2><?php echo $translations[$lang]['edit_product_name']; ?></h2>
            <form id="editProductForm" enctype="multipart/form-data">
                <input type="hidden" id="editProductId" name="product_id">
                <input type="hidden" id="editProductClearImageFlag" name="clear_product_image" value="0">
                <select id="editProductCategory" name="category" class="custom-select" required>
                    <option value="">-- <?php echo $translations[$lang]['category']; ?> --</option>
                    <?php foreach ($translations[$lang]['categories'] as $catKey => $catName): ?>
                        <option value="<?php echo $catKey; ?>"><?php echo $catName; ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="image-source-switch-row" id="editProductImageSourceSwitchRow">
                    <span class="image-source-label"><?php echo htmlspecialchars($translations[$lang]['image_source_file']); ?></span>
                    <label class="form-switch">
                        <input type="checkbox" id="editProductImageUseUrl" aria-label="<?php echo htmlspecialchars($translations[$lang]['image_source_url']); ?>">
                        <span class="form-switch-slider"></span>
                    </label>
                    <span class="image-source-label"><?php echo htmlspecialchars($translations[$lang]['image_source_url']); ?></span>
                </div>
                <div id="editProductImageFileRow" class="image-file-row">
                <input type="file" name="image" accept="image/*" id="editProductImageInput">
                </div>
                <div id="editProductImageUrlRow" class="image-url-row" style="display:none;">
                    <input type="url" id="editProductImageUrlInput" placeholder="<?php echo htmlspecialchars($translations[$lang]['image_url_placeholder']); ?>" autocomplete="off" class="image-url-input">
                </div>
                <div class="image-preview-container" id="editProductImagePreview" style="display:none;">
                    <div class="image-preview-overlay" id="editProductImageOverlay" style="display:none;">
                        <div class="image-preview-spinner"></div>
                        <span>Removing background...</span>
                    </div>
                    <div class="image-preview-media">
                        <img class="image-preview" id="editProductPreviewImg" alt="<?php echo $translations[$lang]['click_to_edit_image']; ?>">
                        <button type="button" class="image-preview-clear" id="editProductImageClearBtn" onclick="clearExistingImage('editProduct', event)" aria-label="Remove image">×</button>
                    </div>
                    <span class="image-preview-hint"><?php echo $translations[$lang]['click_to_edit_image']; ?></span>
                </div>
                <div class="image-tool-actions" id="editProductBgActions" style="display:none;">
                    <button type="button" class="image-tool-btn" id="editProductBgRemoveBtn" onclick="removeBackgroundForPrefix('editProduct')">Remove background</button>
                    <button type="button" class="image-tool-btn image-tool-btn-secondary" id="editProductBgUndoBtn" onclick="undoBackgroundRemoval('editProduct')" style="display:none;">Undo background removal</button>
                </div>
                <table class="edit-product-table">
                    <thead>
                        <tr>
                            <th><?php echo $translations[$lang]['language']; ?></th>
                            <th><?php echo $translations[$lang]['product_name']; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>English</td>
                            <td><input type="text" id="name_en" name="name_en" required></td>
                        </tr>
                        <tr>
                            <td>Українська</td>
                            <td><input type="text" id="name_uk" name="name_uk" required></td>
                        </tr>
                        <tr>
                            <td>Русский</td>
                            <td><input type="text" id="name_ru" name="name_ru" required></td>
                        </tr>
                        <tr>
                            <td>Беларуская</td>
                            <td><input type="text" id="name_be" name="name_be" required></td>
                        </tr>
                    </tbody>
                </table>
                <button type="submit" id="editProductFormSubmit"><?php echo $translations[$lang]['save']; ?></button>
            </form>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal" id="settingsModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('settingsModal')">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2">
                    <path d="M18 6L6 18"></path>
                    <path d="M6 6l12 12"></path>
                </svg>
            </button>
            <h2 style="text-align: center;"><?php echo $translations[$lang]['settings']; ?></h2>
            <form id="settingsForm">
                <label><?php echo $translations[$lang]['items_per_page']; ?>:</label>
                <input type="number" id="itemsPerPage" name="items_per_page" min="1" value="<?php echo $config['items_per_page']; ?>" required>
                <button type="submit"><?php echo $translations[$lang]['save']; ?></button>
            </form>
        </div>
    </div>

    <!-- Image Cropper Modal -->
    <div class="modal" id="imageCropperModal" style="z-index: 2500;">
        <div class="modal-content cropper-modal">
            <button class="modal-close" onclick="closeCropper()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2">
                    <path d="M18 6L6 18"></path>
                    <path d="M6 6l12 12"></path>
                </svg>
            </button>
            <h2 style="text-align: center;"><?php echo $translations[$lang]['crop_image']; ?></h2>
            <div id="cropper" class="cropper-container">
                <img id="cropperImage" src="" alt="Crop image">
                <div id="cropSelection">
                    <div class="resize-handle" data-direction="top-left"></div>
                    <div class="resize-handle" data-direction="top-right"></div>
                    <div class="resize-handle" data-direction="bottom-left"></div>
                    <div class="resize-handle" data-direction="bottom-right"></div>
                    <div class="resize-handle" data-direction="top"></div>
                    <div class="resize-handle" data-direction="bottom"></div>
                    <div class="resize-handle" data-direction="left"></div>
                    <div class="resize-handle" data-direction="right"></div>
                </div>
            </div>
            <div class="cropper-actions">
                <button class="btn-apply" onclick="applyCrop()"><?php echo $translations[$lang]['apply']; ?></button>
                <button class="btn-cancel" onclick="closeCropper()"><?php echo $translations[$lang]['cancel']; ?></button>
            </div>
        </div>
    </div>
