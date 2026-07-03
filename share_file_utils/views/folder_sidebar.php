<?php if ($isFolder && !empty($folderFiles)): ?>
    <div class="folder-sidebar">
        <div class="sidebar-header">
            <h3><?php echo htmlspecialchars($fileName); ?></h3>
            <?php if ($currentSubPath): ?>
            <div class="breadcrumb">
                <a href="?sharedFile=<?php echo urlencode($fileId); ?>&theme=<?php echo urlencode($theme); ?>&lang=<?php echo urlencode($language); ?>&color=<?php echo urlencode($color); ?>">
                    <?php echo htmlspecialchars($fileName); ?>
                </a>
                <?php
                $pathParts = explode('/', $currentSubPath);
                $accumulatedPath = '';
                foreach ($pathParts as $part):
                    $accumulatedPath .= ($accumulatedPath ? '/' : '') . $part;
                ?>
                <span class="breadcrumb-separator">/</span>
                <a href="?sharedFile=<?php echo urlencode($fileId); ?>&subpath=<?php echo urlencode($accumulatedPath); ?>&theme=<?php echo urlencode($theme); ?>&lang=<?php echo urlencode($language); ?>&color=<?php echo urlencode($color); ?>">
                    <?php echo htmlspecialchars($part); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="file-count"><?php echo count($folderFiles); ?> items</div>
        </div>
        <div class="file-list">
            <table class="file-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Size</th>
                        <th>Modified</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($folderFiles as $file): ?>
                    <?php
                    $newSubPath = $currentSubPath ? $currentSubPath . '/' . $file['name'] : $file['name'];
                    $itemUrl = '?sharedFile=' . urlencode($fileId) . '&theme=' . urlencode($theme) . '&lang=' . urlencode($language) . '&color=' . urlencode($color);
                    if ($file['is_dir']) {
                        $itemUrl .= '&subpath=' . urlencode($newSubPath);
                    } else {
                        if ($currentSubPath) {
                            $itemUrl .= '&subpath=' . urlencode($currentSubPath);
                        }
                        $itemUrl .= '&file=' . urlencode($file['name']);
                    }
                    ?>
                    <tr class="file-row <?php echo $file['is_dir'] ? 'is-folder' : 'is-file'; ?> <?php echo $selectedFile && $selectedFile['name'] === $file['name'] ? 'selected' : ''; ?>"
                        onclick="window.location.href='<?php echo htmlspecialchars($itemUrl); ?>'">
                        <td class="file-name-cell">
                            <div class="file-icon">
                                <?php if ($file['is_dir']): ?>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                                <?php else: ?>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                                <?php endif; ?>
                            </div>
                            <span class="file-name"><?php echo htmlspecialchars($file['name']); ?></span>
                        </td>
                        <td class="file-size-cell">
                            <?php echo !$file['is_dir'] ? formatFileSize($file['size']) : '—'; ?>
                        </td>
                        <td class="file-date-cell">
                            <?php echo date('Y-m-d H:i', $file['mtime']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="sidebar-footer">
            <div class="footer-text">Created by Ihor Kotenko © <?php echo date('Y'); ?></div>
            <div class="footer-links">
                <a href="https://www.instagram.com/igor.ktn?igsh=MWdoZjBxenkzZGNkYQ==" target="_blank" class="footer-link" title="Instagram">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    Instagram
                </a>
                <a href="mailto:home260khersonserver@gmail.com?subject=File%20Viewer%20Feedback&body=Hello%20Ihor," class="footer-link" title="Email">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                    Email
                </a>
            </div>
        </div>
    </div>
    <div class="sidebar-resizer" aria-hidden="true"></div>
    <?php endif; ?>
