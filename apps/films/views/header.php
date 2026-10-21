<body>
<!-- Slide-out Navigation -->
<div class="nav-overlay" id="navOverlay"></div>
<nav class="side-nav" id="sideNav">
    <div class="nav-header">
        <div class="nav-logo"><?php echo $t['app_title']; ?></div>
        <button class="nav-close" id="navClose">
            <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
            </svg>
        </button>
    </div>
    <div class="nav-menu">
        <a class="nav-item active" href="#">
            <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            <span>Home</span>
        </a>
        <?php if ($user_access_level >= 1): ?>
        <a class="nav-item" href="#" id="navAddMovie">
            <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            <span><?php echo $t['add_movie']; ?></span>
        </a>
        <?php endif; ?>
        <?php if ($user_access_level >= 2): ?>
        <a class="nav-item" href="#" id="navSettings">
            <svg viewBox="0 0 24 24"><path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24 0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.82,11.69,4.82,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24 0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47 0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98 0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6 12,15.6z"/></svg>
            <span><?php echo $t['settings']; ?></span>
        </a>
        <?php endif; ?>
    </div>
    <div class="nav-footer">
        <div class="user-info">
            <div class="user-avatar">
                <img src="<?php echo htmlspecialchars($user_photo); ?>" alt="<?php echo htmlspecialchars($current_user); ?>">
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($current_user); ?></div>
                <?php if ($user_access_level > 0): ?>
                <div class="user-level">Access Level <?php echo $user_access_level; ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Floating Menu Button -->
<button class="menu-toggle floating" id="menuToggle" title="Menu" aria-label="Open menu">
    <svg viewBox="0 0 24 24">
        <circle cx="12" cy="5" r="2.5"/>
        <circle cx="12" cy="12" r="2.5"/>
        <circle cx="12" cy="19" r="2.5"/>
    </svg>
</button>

<header class="header">
    <div class="header-content">
        <h1 class="app-title"><?php echo $t['app_title']; ?></h1>
    </div>
</header>

<div class="container">
    <div class="search-sort-container">
        <div class="search-box">
            <input type="text" class="search-input" id="searchInput" placeholder="<?php echo $t['search_placeholder'] ?? 'Search movies...'; ?>">
            <div class="search-icon">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                    <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                </svg>
            </div>
        </div>
        <div class="sort-container">
            <button class="sort-btn" id="sortBtn" title="<?php echo $t['sort_by_genre'] ?? 'Sort by genre'; ?>">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                    <path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/>
                </svg>
            </button>
            <div class="sort-dropdown" id="sortDropdown" style="display: none;">
                <div class="sort-option active" data-sort="all"><?php echo $t['all_genres'] ?? 'All genres'; ?></div>
                <?php foreach (array_keys($t['categories']) as $category): ?>
                <div class="sort-option" data-sort="<?php echo $category; ?>"><?php echo $t['categories'][$category]; ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<main class="main-content">
    <div class="movies-grid" id="moviesGrid">
        <?php if (empty($movies)): ?>
        <div class="empty-state">
            <h3><?php echo $t['no_movies']; ?></h3>
            <p><?php echo $t['add_first_movie']; ?></p>
        </div>
        <?php else: ?>
            <?php foreach ($movies as $movie): ?>
            <div class="movie-card">
                <div class="content-type-icon" title="<?php echo (isset($movie['is_local']) && $movie['is_local']) ? $t['local_file'] : $t['online_link']; ?>">
                    <?php if (isset($movie['is_local']) && $movie['is_local']): ?>
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/>
                    </svg>
                    <?php else: ?>
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.22-1.78L9 14v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1h-6v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z" />
                    </svg>
                    <?php endif; ?>
                </div>
                <h3 class="movie-title"><?php echo htmlspecialchars($movie['title'][$lang] ?? $movie['title']['en']); ?></h3>
                <div class="overall-rating">
                    <span class="overall-rating-text"><?php echo $t['overall_rating'] ?? 'Overall Rating'; ?>:</span>
                    <div class="rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star"><?php echo $i <= round($movie['overall_rating']) ? '★' : '☆'; ?></span>
                        <?php endfor; ?>
                    </div>
                    <span class="rating-value"><?php echo number_format($movie['overall_rating'], 1); ?></span>
                </div>
                <div class="movie-info">
                    <span style="color: var(--text-secondary);"><?php echo $movie['year']; ?></span>
                    <span class="movie-category"><?php echo htmlspecialchars($t['categories'][$movie['category']] ?? $movie['category']); ?></span>
                </div>
                <div class="rating">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="star"><?php echo $i <= $movie['rating'] ? '★' : '☆'; ?></span>
                    <?php endfor; ?>
                </div>
                <div class="movie-actions">
                    <?php if (isset($movie['is_local']) && $movie['is_local'] && !empty($movie['file_path'])): ?>
                    <button class="watch-btn" onclick="showVideoPlayer('<?php echo htmlspecialchars(basename($movie['file_path'])); ?>', '<?php echo htmlspecialchars($movie['title'][$lang] ?? $movie['title']['en']); ?>')">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="margin-right: 6px;">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        <?php echo $t['watch']; ?>
                    </button>
                    <?php else: ?>
                    <button class="watch-btn" onclick="window.open('<?php echo htmlspecialchars($movie['link'] ?? ''); ?>', '_blank')">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="margin-right: 6px;">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        <?php echo $t['watch']; ?>
                    </button>
                    <button class="btn btn--icon copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($movie['link'] ?? ''); ?>')" title="<?php echo $t['copy_link']; ?>">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                        </svg>
                    </button>
                    <?php endif; ?>
                    <button class="comments-btn" onclick="showComments(<?php echo htmlspecialchars(json_encode((string)$movie['id']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode((string)($movie['title'][$lang] ?? $movie['title']['en'])), ENT_QUOTES, 'UTF-8'); ?>)">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" style="margin-right: 4px;">
                            <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
                        </svg>
                        <?php echo $t['comments'] ?? 'Comments'; ?>
                    </button>
                    <button class="comments-btn" onclick="shareFilm(<?php echo htmlspecialchars(json_encode((string)$movie['id']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode((string)($movie['title'][$lang] ?? $movie['title']['en'])), ENT_QUOTES, 'UTF-8'); ?>)">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" style="margin-right: 4px;"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11A2.99 2.99 0 1 0 15 5c0 .24.04.47.09.7L8.04 9.81A3 3 0 1 0 8.04 14.19l7.12 4.16c-.05.21-.08.43-.08.65A2.92 2.92 0 1 0 18 16.08z"/></svg>
                        <?php echo $t['share'] ?? 'Share'; ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="loading" id="loading">
        <p><?php echo $t['translating']; ?></p>
    </div>
</main>

