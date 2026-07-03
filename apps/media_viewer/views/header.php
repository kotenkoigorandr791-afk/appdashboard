<?php
$visibleAlbums = filterAlbumsForUser($albums, $hiddenAlbums, $userRole);
$totalViews = array_sum($stats);
?>
<svg width="0" height="0" style="position:absolute">
    <symbol id="icon-grid" viewBox="0 0 24 24">
        <rect x="4" y="4" width="7" height="7" rx="2"></rect>
        <rect x="13" y="4" width="7" height="7" rx="2"></rect>
        <rect x="4" y="13" width="7" height="7" rx="2"></rect>
        <rect x="13" y="13" width="7" height="7" rx="2"></rect>
    </symbol>
    <symbol id="icon-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="6"></circle>
        <path d="M20 20l-4.2-4.2"></path>
    </symbol>
    <symbol id="icon-user" viewBox="0 0 24 24">
        <circle cx="12" cy="8" r="3.5"></circle>
        <path d="M5 19c1.8-3 4.2-4.5 7-4.5S17.2 16 19 19"></path>
    </symbol>
    <symbol id="icon-settings" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="3"></circle>
        <path d="M19.4 15a1 1 0 0 0 .2 1.1l.1.1a2 2 0 0 1 0 2.8 2 2 0 0 1-2.8 0l-.1-.1a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.9V20a2 2 0 0 1-4 0v-.2a1 1 0 0 0-.7-.9 1 1 0 0 0-1.1.2l-.1.1a2 2 0 0 1-2.8 0 2 2 0 0 1 0-2.8l.1-.1a1 1 0 0 0 .2-1.1 1 1 0 0 0-.9-.6H4a2 2 0 0 1 0-4h.2a1 1 0 0 0 .9-.7 1 1 0 0 0-.2-1.1l-.1-.1a2 2 0 0 1 0-2.8 2 2 0 0 1 2.8 0l.1.1a1 1 0 0 0 1.1.2 1 1 0 0 0 .6-.9V4a2 2 0 0 1 4 0v.2a1 1 0 0 0 .7.9 1 1 0 0 0 1.1-.2l.1-.1a2 2 0 0 1 2.8 0 2 2 0 0 1 0 2.8l-.1.1a1 1 0 0 0-.2 1.1 1 1 0 0 0 .9.6h.2a2 2 0 0 1 0 4h-.2a1 1 0 0 0-.9.7Z"></path>
    </symbol>
    <symbol id="icon-stats" viewBox="0 0 24 24">
        <path d="M5 19V9"></path>
        <path d="M12 19V5"></path>
        <path d="M19 19v-7"></path>
    </symbol>
    <symbol id="icon-admin" viewBox="0 0 24 24">
        <path d="M6 17h12"></path>
        <path d="M7 17l-2-8 4 2 3-5 3 5 4-2-2 8"></path>
    </symbol>
    <symbol id="icon-plus" viewBox="0 0 24 24">
        <path d="M12 5v14"></path>
        <path d="M5 12h14"></path>
    </symbol>
    <symbol id="icon-device" viewBox="0 0 24 24">
        <rect x="7" y="3" width="10" height="18" rx="2"></rect>
        <path d="M11 18h2"></path>
    </symbol>
    <symbol id="icon-arrow" viewBox="0 0 24 24">
        <path d="M5 12h14"></path>
        <path d="M13 6l6 6-6 6"></path>
    </symbol>
</svg>
<div class="container">
    <button class="mobile-menu-button" onclick="toggleMobileMenu()" aria-label="<?php echo $translations['user_info'] ?? 'User info'; ?>">
        <span class="icon glyph"><svg viewBox="0 0 24 24"><use href="#icon-user"></use></svg></span>
    </button>
    <aside id="mobileMenu" class="mobile-menu">
        <div class="mobile-menu-header">
            <h3 class="mobile-menu-title"><?php echo $translations['user_info'] ?? 'User Info'; ?></h3>
            <button class="mobile-menu-close" onclick="toggleMobileMenu()" aria-label="<?php echo $translations['close'] ?? 'Close'; ?>">&times;</button>
        </div>
        <div class="mobile-menu-content">
            <div class="user-summary-card">
                <div class="user-summary-top">
                    <span class="user-avatar">
                        <?php if ($currentUserPhoto): ?>
                        <img src="<?php echo htmlspecialchars($currentUserPhoto); ?>" alt="Avatar" class="user-avatar-img">
                        <?php else: ?>
                        <svg viewBox="0 0 24 24"><use href="#icon-user"></use></svg>
                        <?php endif; ?>
                    </span>
                    <div>
                        <div class="user-summary-label"><?php echo $translations['current_user'] ?? 'User'; ?></div>
                        <div class="user-summary-name"><?php echo htmlspecialchars($currentUser); ?></div>
                    </div>
                </div>
                <div class="user-summary-role"><?php echo $roleText; ?></div>
            </div>
            <button class="icon-button rail-button" onclick="toggleMobileMenu(); openModal('userInfoModal');">
                <span class="icon"><svg viewBox="0 0 24 24"><use href="#icon-user"></use></svg></span>
                <span><?php echo $translations['user_info'] ?? 'User Info'; ?></span>
            </button>
            <button class="icon-button rail-button" onclick="toggleMobileMenu(); openModal('settingsModal');">
                <span class="icon"><svg viewBox="0 0 24 24"><use href="#icon-settings"></use></svg></span>
                <span><?php echo $translations['settings'] ?? 'Settings'; ?></span>
            </button>
            <button class="icon-button rail-button" onclick="toggleMobileMenu(); openModal('statsModal');">
                <span class="icon"><svg viewBox="0 0 24 24"><use href="#icon-stats"></use></svg></span>
                <span><?php echo $translations['statistics'] ?? 'Statistics'; ?></span>
            </button>
            <?php if ($userRole >= 1): ?>
            <button class="icon-button rail-button" onclick="toggleMobileMenu(); openModal('adminModal');">
                <span class="icon"><svg viewBox="0 0 24 24"><use href="#icon-admin"></use></svg></span>
                <span><?php echo $translations['admin_panel'] ?? 'Admin Panel'; ?></span>
            </button>
            <button class="icon-button rail-button" onclick="toggleMobileMenu(); openModal('addAlbumModal');">
                <span class="icon"><svg viewBox="0 0 24 24"><use href="#icon-plus"></use></svg></span>
                <span><?php echo $translations['add_album'] ?? 'Add Album'; ?></span>
            </button>
            <?php endif; ?>
            <button class="icon-button rail-button" onclick="toggleMobileMenu(); openInstallModal();">
                <span class="icon"><svg viewBox="0 0 24 24"><use href="#icon-device"></use></svg></span>
                <span><?php echo $translations['install_app'] ?? 'Get App'; ?></span>
            </button>
        </div>
    </aside>

    <aside class="side-rail desktop-rail">
        <button class="rail-user-button" onclick="openModal('userInfoModal')" title="<?php echo htmlspecialchars($currentUser); ?>">
            <span class="user-avatar">
                <?php if ($currentUserPhoto): ?>
                <img src="<?php echo htmlspecialchars($currentUserPhoto); ?>" alt="Avatar" class="user-avatar-img">
                <?php else: ?>
                <svg viewBox="0 0 24 24"><use href="#icon-user"></use></svg>
                <?php endif; ?>
            </span>
            <span class="rail-user-meta">
                <span class="rail-user-name"><?php echo htmlspecialchars($currentUser); ?></span>
                <span class="rail-user-role"><?php echo $roleText; ?></span>
            </span>
        </button>
        <div class="rail-actions">
            <?php if ($userRole >= 1): ?>
            <button class="icon-button rail-button" onclick="openModal('adminModal')" title="<?php echo $translations['admin_panel'] ?? 'Admin Panel'; ?>">
                <span class="icon"><svg viewBox="0 0 24 24"><use href="#icon-admin"></use></svg></span>
                <span><?php echo $translations['admin_panel'] ?? 'Admin Panel'; ?></span>
            </button>
            <button class="icon-button rail-button" onclick="openModal('addAlbumModal')" title="<?php echo $translations['add_album'] ?? 'Add Album'; ?>">
                <span class="icon"><svg viewBox="0 0 24 24"><use href="#icon-plus"></use></svg></span>
                <span><?php echo $translations['add_album'] ?? 'Add Album'; ?></span>
            </button>
            <?php endif; ?>
            <button class="icon-button rail-button" onclick="openModal('settingsModal')" title="<?php echo $translations['settings'] ?? 'Settings'; ?>">
                <span class="icon"><svg viewBox="0 0 24 24"><use href="#icon-settings"></use></svg></span>
                <span><?php echo $translations['settings'] ?? 'Settings'; ?></span>
            </button>
            <button class="icon-button rail-button" onclick="openModal('statsModal')" title="<?php echo $translations['statistics'] ?? 'Statistics'; ?>">
                <span class="icon"><svg viewBox="0 0 24 24"><use href="#icon-stats"></use></svg></span>
                <span><?php echo $translations['statistics'] ?? 'Statistics'; ?></span>
            </button>
            <button class="icon-button rail-button" onclick="openInstallModal()" title="<?php echo $translations['install_app'] ?? 'Get App'; ?>">
                <span class="icon"><svg viewBox="0 0 24 24"><use href="#icon-device"></use></svg></span>
                <span><?php echo $translations['install_app'] ?? 'Get App'; ?></span>
            </button>
        </div>
    </aside>

    <div class="header">
        <div class="header-left">
            <div class="app-mark">
                <span class="app-mark-icon">
                    <svg viewBox="0 0 24 24"><use href="#icon-grid"></use></svg>
                </span>
                <div class="app-mark-copy">
                    <div class="app-kicker"><?php echo $translations['app_title'] ?? 'Photo Albums'; ?></div>
                    <h1>
                        <span class="app-title"><?php echo $translations['app_title'] ?? 'Photo Albums'; ?></span>
                    </h1>
                </div>
            </div>
            <p class="header-description">Browse albums in a cleaner gallery layout with fast search and access-aware collections.</p>
        </div>
        <div class="header-center">
            <div class="search-bar">
                <span class="search-icon">
                    <svg viewBox="0 0 24 24"><use href="#icon-search"></use></svg>
                </span>
                <input type="text" id="searchInput" placeholder="<?php echo $translations['search_placeholder'] ?? 'Search albums...'; ?>">
            </div>
            <div class="header-metrics">
                <div class="metric-pill">
                    <span class="metric-value"><?php echo count($visibleAlbums); ?></span>
                    <span class="metric-label"><?php echo $translations['album_name'] ?? 'Albums'; ?></span>
                </div>
                <div class="metric-pill">
                    <span class="metric-value"><?php echo $totalViews; ?></span>
                    <span class="metric-label"><?php echo $translations['views'] ?? 'Views'; ?></span>
                </div>
                <div class="metric-pill">
                    <span class="metric-value"><?php echo $roleText; ?></span>
                    <span class="metric-label"><?php echo $translations['access_level'] ?? 'Access'; ?></span>
                </div>
            </div>
        </div>
    </div>
