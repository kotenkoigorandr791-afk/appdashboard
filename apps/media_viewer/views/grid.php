<div class="grid" id="albumsGrid" style="--grid-columns: <?php echo (int)($_COOKIE['grid_columns'] ?? 4); ?>;">
    <?php
    $albumsPerPage = $_COOKIE['albums_per_page'] ?? 12;
    $currentPage = $_GET['page'] ?? 1;
    $filteredAlbums = filterAlbumsForUser($albums, $hiddenAlbums, $userRole);
    $start = ($currentPage - 1) * $albumsPerPage;
    $paginatedAlbums = array_slice($filteredAlbums, $start, $albumsPerPage);
    foreach ($paginatedAlbums as $album):
        $views = $stats[$album['id']] ?? 0;
        $isHidden = isset($hiddenAlbums[$album['id']]);
        $minRole = $album['min_role'] ?? 0;
        $accessClass = 'access-guest';
        $accessText = $translations['guest'] ?? 'Guest';
        if ($minRole == 1) {
            $accessClass = 'access-manager';
            $accessText = $translations['manager'] ?? 'Manager';
        } elseif ($minRole >= 2) {
            $accessClass = 'access-admin';
            $accessText = $translations['admin'] ?? 'Admin';
        }
    ?>
    <div class="album-card" data-album-id="<?php echo htmlspecialchars($album['id']); ?>" onclick="viewAlbum('<?php echo $album['id']; ?>', '<?php echo addslashes($album['name']); ?>', '<?php echo addslashes($album['url']); ?>')">
        <?php if ($isHidden): ?>
        <div class="album-chip album-chip-warning">
            <svg viewBox="0 0 24 24"><path d="M4 12s3-5 8-5 8 5 8 5-3 5-8 5-8-5-8-5Z"></path><path d="M3 3l18 18"></path></svg>
            <?php echo $translations['hidden'] ?? 'Hidden'; ?>
        </div>
        <?php endif; ?>
        <div class="album-topbar">
            <span class="access-badge <?php echo $accessClass; ?>">
                <?php echo $accessText; ?>
            </span>
        </div>
        <img src="<?php echo $album['preview'] ?: 'https://via.placeholder.com/300x200/'.substr($color,1).'/ffffff?text=' . urlencode($album['name']); ?>"
             alt="<?php echo htmlspecialchars($album['name']); ?>" class="album-preview" loading="lazy">
        <div class="album-info">
            <div class="album-copy">
                <h3 class="album-title" style="font-size: <?php echo $_COOKIE['font_size'] ?? '16'; ?>px;">
                    <?php echo htmlspecialchars($album['name']); ?>
                </h3>
                <span class="album-open-indicator">
                    <svg viewBox="0 0 24 24"><use href="#icon-arrow"></use></svg>
                </span>
            </div>
            <div class="album-stats">
                <span class="album-stat">
                    <svg viewBox="0 0 24 24"><path d="M7 3v3"></path><path d="M17 3v3"></path><rect x="4" y="5" width="16" height="15" rx="3"></rect><path d="M4 10h16"></path></svg>
                    <?php echo date('d.m.Y', strtotime($album['created'])); ?>
                </span>
                <span class="album-stat">
                    <svg viewBox="0 0 24 24"><path d="M2 12s4-6 10-6 10 6 10 6-4 6-10 6S2 12 2 12Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
                    <span><span class="album-views-count"><?php echo $views; ?></span> <?php echo $translations['views'] ?? 'views'; ?></span>
                </span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php if (count($filteredAlbums) > $albumsPerPage): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= ceil(count($filteredAlbums) / $albumsPerPage); $i++): ?>
        <button class="page-button <?php echo $i == $currentPage ? 'active' : ''; ?>"
                onclick="window.location.href='?app=<?php echo $app; ?>&lang=<?php echo $lang; ?>&theme=<?php echo $theme; ?>&color=<?php echo urlencode($color); ?>&page=<?php echo $i; ?>'">
            <?php echo $i; ?>
        </button>
    <?php endfor; ?>
</div>
<?php endif; ?>
