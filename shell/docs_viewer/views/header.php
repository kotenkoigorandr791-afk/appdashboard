<header class="doc-header">
    <div class="doc-header-left">
        <div>
            <div class="doc-title"><?php echo htmlspecialchars(t('documentation', 'Documentation')); ?></div>
            <div class="doc-subtitle"><?php echo htmlspecialchars(t('documentation_subtitle', 'Browse and read project documentation')); ?></div>
        </div>
    </div>
    <div class="doc-header-actions">
        <div class="search-box" id="searchBox">
            <button type="button" class="search-toggle" aria-label="Search" onclick="toggleSearch()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35"/>
                </svg>
            </button>
            <input type="text" id="searchDocs" placeholder="<?php echo htmlspecialchars(t('search_docs', 'Search documentation...')); ?>" oninput="filterDocs()" onblur="collapseSearch()">
        </div>
        <button class="btn" onclick="showGridView()" id="backToGridBtn" style="display: none;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m15 18-6-6 6-6"/>
            </svg>
            <span data-i18n="back_to_docs"><?php echo htmlspecialchars(t('back_to_docs', 'Back to List')); ?></span>
        </button>
    </div>
</header>
