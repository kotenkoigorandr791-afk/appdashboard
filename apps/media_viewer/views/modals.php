<div id="userInfoModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><?php echo $translations['user_info'] ?? 'User Information'; ?></h2>
            <button class="close-button" onclick="closeModal('userInfoModal')">&times;</button>
        </div>
        <div style="padding: 20px; background: var(--surface-2); border-radius: 12px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                <div style="width: 60px; height: 60px; border-radius: 50%; overflow: hidden; flex-shrink: 0;">
                    <?php if ($currentUserPhoto): ?>
                    <img src="<?php echo htmlspecialchars($currentUserPhoto); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                    <div style="width: 100%; height: 100%; background: var(--primary-color); opacity: 0.8; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                        <i class="fas fa-user"></i>
                    </div>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 style="margin: 0 0 5px 0; font-size: 20px; color: var(--text);">
                        <?php echo htmlspecialchars($currentUser); ?>
                    </h3>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr; gap: 15px;">
                <div style="background: var(--surface-muted); padding: 12px; border-radius: 8px;">
                    <div style="font-size: 12px; color: var(--text-soft); margin-bottom: 4px;">
                        <?php echo $translations['role'] ?? 'Role'; ?>
                    </div>
                    <div style="font-weight: 600; color: var(--primary-color);">
                        <?php echo $roleText; ?>
                    </div>
                </div>
            </div>
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--line);">
                <div style="font-size: 12px; color: var(--text-soft); margin-bottom: 8px;">
                    <?php echo $translations['access_level'] ?? 'Access Level'; ?>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <?php if ($userRole >= 2): ?>
                    <span style="background: <?php echo $color; ?>c8; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px;">
                        <i class="fas fa-crown"></i> <?php echo $translations['full_access'] ?? 'Full Access'; ?>
                    </span>
                    <?php elseif ($userRole == 1): ?>
                    <span style="background: #4CAF50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px;">
                        <i class="fas fa-user-shield"></i> <?php echo $translations['manager_access'] ?? 'Manager Access'; ?>
                    </span>
                    <?php else: ?>
                    <span style="background: #757575; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px;">
                        <i class="fas fa-user"></i> <?php echo $translations['read_only'] ?? 'Read Only'; ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div style="display: flex; gap: 15px; margin-top: 20px;">
            <button class="button" onclick="closeModal('userInfoModal')" style="flex: 1;">
                <?php echo $translations['close'] ?? 'Close'; ?>
            </button>
        </div>
    </div>
</div>
<div id="addAlbumModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><?php echo $translations['add_new_album'] ?? 'Add New Album'; ?></h2>
            <button class="close-button" onclick="closeModal('addAlbumModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data" id="addAlbumForm">
            <input type="hidden" name="add_album" value="1">
            <div class="form-group">
                <label class="form-label"><?php echo $translations['album_name'] ?? 'Album Name'; ?></label>
                <input type="text" name="album_name" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo $translations['album_url'] ?? 'Album URL'; ?></label>
                <input type="url" name="album_url" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo $translations['access_level'] ?? 'Access Level'; ?></label>
                <select name="min_role" class="form-input" required>
                    <option value="0"><?php echo $translations['guest'] ?? 'Guest'; ?> (<?php echo $translations['all_users'] ?? 'All users'; ?>)</option>
                    <option value="1"><?php echo $translations['manager'] ?? 'Manager'; ?>+</option>
                    <option value="2"><?php echo $translations['admin'] ?? 'Admin'; ?>+</option>
                </select>
                <div style="font-size: 12px; color: var(--text-soft); margin-top: 5px;">
                    <?php echo $translations['access_hint'] ?? 'Users with equal or higher role can see this album'; ?>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo $translations['preview_image'] ?? 'Preview Image'; ?></label>
                <div class="drop-area" id="dropArea">
                    <div class="drag-text"><?php echo $translations['drag_drop'] ?? 'Drag & drop image here'; ?></div>
                    <div><?php echo $translations['or'] ?? 'or'; ?></div>
                    <label class="button" style="margin-top: 15px;">
                        <i class="fas fa-upload"></i> <?php echo $translations['choose_file'] ?? 'Choose File'; ?>
                        <input type="file" name="preview_file" accept="image/*" style="display: none;" id="fileInput" onchange="previewImage(this)">
                    </label>
                    <button type="button" class="button paste-button" onclick="pasteFromClipboard()">
                        <i class="fas fa-paste"></i> <?php echo $translations['paste_clipboard'] ?? 'Paste from Clipboard'; ?>
                    </button>
                </div>
                <img id="imagePreview" class="preview-image" src="" alt="Preview">
                <input type="hidden" name="preview_base64" id="previewBase64">
            </div>
            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="submit" class="button" style="flex: 1;">
                    <i class="fas fa-save"></i> <?php echo $translations['save'] ?? 'Save Album'; ?>
                </button>
                <button type="button" class="button" style="background: var(--surface-2); color: var(--text); border: 1px solid var(--line);" onclick="closeModal('addAlbumModal')">
                    <?php echo $translations['cancel'] ?? 'Cancel'; ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php
$totalAlbums = count($albums);
$totalHidden = count($hiddenAlbums);
$totalVisible = $totalAlbums - $totalHidden;
$totalViewsAll = array_sum($stats);
?>
<?php if ($userRole >= 1): ?>
<div id="editAlbumModal" class="modal">
    <div class="modal-content modal-content--wide">
        <div class="modal-header">
            <h2 class="modal-title"><?php echo $translations['edit_album'] ?? 'Edit Album'; ?></h2>
            <button class="close-button" onclick="closeModal('editAlbumModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data" id="editAlbumForm" onsubmit="prepareEditPreviewForSubmit()">
            <input type="hidden" name="edit_album" value="1">
            <input type="hidden" name="album_id" id="editAlbumId">
            <input type="hidden" name="edit_preview_base64" id="editPreviewBase64">

            <div class="edit-album-grid">
                <div>
                    <div class="form-group">
                        <label class="form-label"><?php echo $translations['album_name'] ?? 'Album Name'; ?></label>
                        <input type="text" name="edit_album_name" id="editAlbumName" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo $translations['album_url'] ?? 'Album URL'; ?></label>
                        <input type="url" name="edit_album_url" id="editAlbumUrl" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo $translations['created'] ?? 'Created'; ?></label>
                        <input type="datetime-local" name="edit_album_created" id="editAlbumCreated" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo $translations['access_level'] ?? 'Access Level'; ?></label>
                        <select name="edit_min_role" id="editMinRole" class="form-input" required>
                            <option value="0"><?php echo $translations['guest'] ?? 'Guest'; ?> (<?php echo $translations['all_users'] ?? 'All users'; ?>)</option>
                            <option value="1"><?php echo $translations['manager'] ?? 'Manager'; ?>+</option>
                            <option value="2"><?php echo $translations['admin'] ?? 'Admin'; ?>+</option>
                        </select>
                    </div>
                </div>

                <div class="crop-panel">
                    <label class="form-label"><?php echo $translations['preview_image'] ?? 'Preview Image'; ?></label>
                    <canvas id="editCropCanvas" class="crop-canvas" width="640" height="420"></canvas>
                    <div class="crop-controls">
                        <label class="crop-control">
                            <span><?php echo $translations['zoom'] ?? 'Zoom'; ?></span>
                            <input type="range" min="1" max="3" step="0.01" value="1" id="editCropZoom">
                        </label>
                        <label class="crop-control">
                            <span>X</span>
                            <input type="range" min="-100" max="100" step="1" value="0" id="editCropX">
                        </label>
                        <label class="crop-control">
                            <span>Y</span>
                            <input type="range" min="-100" max="100" step="1" value="0" id="editCropY">
                        </label>
                    </div>
                    <label class="button crop-upload-button">
                        <i class="fas fa-upload"></i> <?php echo $translations['choose_file'] ?? 'Choose File'; ?>
                        <input type="file" accept="image/*" style="display:none" id="editPreviewFile" onchange="loadEditPreviewFile(this)">
                    </label>
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="submit" class="button" style="flex: 1;">
                    <i class="fas fa-save"></i> <?php echo $translations['save'] ?? 'Save'; ?>
                </button>
                <button type="button" class="button" style="background: var(--surface-2); color: var(--text); border: 1px solid var(--line);" onclick="closeModal('editAlbumModal')">
                    <?php echo $translations['cancel'] ?? 'Cancel'; ?>
                </button>
            </div>
        </form>
    </div>
</div>
<div id="adminModal" class="modal">
    <div class="modal-content modal-content--admin">
        <div class="modal-header">
            <h2 class="modal-title">
                <span class="admin-modal-icon"><i class="fas fa-crown"></i></span>
                <?php echo $translations['admin_panel'] ?? 'Admin Panel'; ?>
                <span class="admin-modal-user"><?php echo htmlspecialchars($currentUser); ?></span>
            </h2>
            <button class="close-button" onclick="closeModal('adminModal')">&times;</button>
        </div>

        <div class="admin-scroll-body">
        <div class="admin-stats">
            <div class="admin-stat-card">
                <div class="admin-stat-icon admin-stat-icon--albums"><i class="fas fa-images"></i></div>
                <div class="admin-stat-body">
                    <span class="admin-stat-value"><?php echo $totalAlbums; ?></span>
                    <span class="admin-stat-label"><?php echo $translations['album_name'] ?? 'Albums'; ?></span>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-icon admin-stat-icon--visible"><i class="fas fa-eye"></i></div>
                <div class="admin-stat-body">
                    <span class="admin-stat-value"><?php echo $totalVisible; ?></span>
                    <span class="admin-stat-label"><?php echo $translations['visible'] ?? 'Visible'; ?></span>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-icon admin-stat-icon--hidden"><i class="fas fa-eye-slash"></i></div>
                <div class="admin-stat-body">
                    <span class="admin-stat-value"><?php echo $totalHidden; ?></span>
                    <span class="admin-stat-label"><?php echo $translations['hidden'] ?? 'Hidden'; ?></span>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-icon admin-stat-icon--views"><i class="fas fa-chart-bar"></i></div>
                <div class="admin-stat-body">
                    <span class="admin-stat-value"><?php echo $totalViewsAll; ?></span>
                    <span class="admin-stat-label"><?php echo $translations['views'] ?? 'Views'; ?></span>
                </div>
            </div>
        </div>

        <div class="admin-section">
            <h3 class="admin-section-title">
                <i class="fas fa-list"></i>
                <?php echo $translations['album_management'] ?? 'Album Management'; ?>
            </h3>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th><?php echo $translations['album_name'] ?? 'Album Name'; ?></th>
                            <th><?php echo $translations['access'] ?? 'Access'; ?></th>
                            <th><?php echo $translations['views'] ?? 'Views'; ?></th>
                            <th><?php echo $translations['created'] ?? 'Created'; ?></th>
                            <th><?php echo $translations['status'] ?? 'Status'; ?></th>
                            <th class="admin-th-actions"><?php echo $translations['actions'] ?? 'Actions'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($albums as $album):
                            $views = $stats[$album['id']] ?? 0;
                            $isHidden = isset($hiddenAlbums[$album['id']]);
                            $minRole = $album['min_role'] ?? 0;
                            $accessText = $translations['guest'] ?? 'Guest';
                            if ($minRole == 1) $accessText = $translations['manager'] ?? 'Manager';
                            elseif ($minRole >= 2) $accessText = $translations['admin'] ?? 'Admin';
                        ?>
                        <tr class="<?php echo $isHidden ? 'admin-row-hidden' : ''; ?>">
                            <td class="admin-td-name">
                                <span class="admin-album-name"><?php echo htmlspecialchars($album['name']); ?></span>
                            </td>
                            <td><span class="access-badge <?php echo $minRole == 0 ? 'access-guest' : ($minRole == 1 ? 'access-manager' : 'access-admin'); ?>"><?php echo $accessText; ?>+</span></td>
                            <td class="admin-td-views"><?php echo $views; ?></td>
                            <td class="admin-td-date"><?php echo date('d.m.Y H:i', strtotime($album['created'])); ?></td>
                            <td>
                                <?php if ($isHidden): ?>
                                <span class="admin-status-badge admin-status-badge--hidden">
                                    <i class="fas fa-eye-slash"></i>
                                    <?php echo $translations['hidden'] ?? 'Hidden'; ?>
                                </span>
                                <?php else: ?>
                                <span class="admin-status-badge admin-status-badge--visible">
                                    <i class="fas fa-eye"></i>
                                    <?php echo $translations['visible'] ?? 'Visible'; ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn edit-btn" title="<?php echo $translations['edit'] ?? 'Edit'; ?>" onclick="editAlbum('<?php echo $album['id']; ?>')">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>
                                    <button class="action-btn delete-btn" title="<?php echo $translations['delete'] ?? 'Delete'; ?>" onclick="deleteAlbum('<?php echo $album['id']; ?>')">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                    <button class="action-btn hide-btn" title="<?php echo $isHidden ? ($translations['show'] ?? 'Show') : ($translations['hide'] ?? 'Hide'); ?>" onclick="toggleHideAlbum('<?php echo $album['id']; ?>')">
                                        <?php if ($isHidden): ?>
                                        <i class="fas fa-eye"></i>
                                        <?php else: ?>
                                        <i class="fas fa-eye-slash"></i>
                                        <?php endif; ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($albums)): ?>
                        <tr>
                            <td colspan="6" class="admin-empty">
                                <i class="fas fa-folder-open"></i>
                                <?php echo $translations['no_albums_yet'] ?? 'No albums yet'; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="admin-section">
            <h3 class="admin-section-title">
                <i class="fas fa-bolt"></i>
                <?php echo $translations['quick_actions'] ?? 'Quick Actions'; ?>
            </h3>
            <div class="admin-actions-grid">
                <button class="admin-action-card" onclick="exportData()">
                    <span class="admin-action-icon admin-action-icon--export"><i class="fas fa-download"></i></span>
                    <span class="admin-action-label"><?php echo $translations['export_data'] ?? 'Export Data'; ?></span>
                    <span class="admin-action-desc"><?php echo $translations['export_data_desc'] ?? 'Download albums and stats as JSON'; ?></span>
                </button>
                <button class="admin-action-card" onclick="clearStats()">
                    <span class="admin-action-icon admin-action-icon--clear"><i class="fas fa-chart-bar"></i></span>
                    <span class="admin-action-label"><?php echo $translations['clear_stats'] ?? 'Clear Statistics'; ?></span>
                    <span class="admin-action-desc"><?php echo $translations['clear_stats_desc'] ?? 'Reset all view counters to zero'; ?></span>
                </button>
            </div>
        </div>

        <div class="admin-footer">
            <button class="button" onclick="closeModal('adminModal')" style="flex: 1;">
                <i class="fas fa-times"></i> <?php echo $translations['close'] ?? 'Close'; ?>
            </button>
        </div>
        </div>
    </div>
</div>
<?php endif; ?>
<div id="settingsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><?php echo $translations['settings'] ?? 'Settings'; ?></h2>
            <button class="close-button" onclick="closeModal('settingsModal')">&times;</button>
        </div>
        <div class="settings-grid">
            <div class="setting-item">
                <label class="setting-label"><?php echo $translations['grid_columns'] ?? 'Grid Columns'; ?></label>
                <input type="range" min="2" max="6" value="<?php echo $_COOKIE['grid_columns'] ?? 4; ?>" class="setting-input" id="gridColumns">
                <div style="text-align: center; font-size: 12px; color: var(--text-soft); margin-top: 5px;">
                    <span id="gridColumnsValue"><?php echo $_COOKIE['grid_columns'] ?? 4; ?></span>
                </div>
            </div>
            <div class="setting-item">
                <label class="setting-label"><?php echo $translations['font_size'] ?? 'Font Size (px)'; ?></label>
                <input type="number" min="12" max="32" value="<?php echo $_COOKIE['font_size'] ?? 18; ?>" class="setting-input" id="fontSize">
            </div>
            <div class="setting-item">
                <label class="setting-label"><?php echo $translations['albums_per_page'] ?? 'Albums per Page'; ?></label>
                <input type="number" min="4" max="48" value="<?php echo $_COOKIE['albums_per_page'] ?? 12; ?>" class="setting-input" id="albumsPerPage">
            </div>
        </div>
        <div style="display: flex; gap: 15px; margin-top: 25px;">
            <button class="button" onclick="saveSettings()" style="flex: 1;">
                <i class="fas fa-save"></i> <?php echo $translations['save_settings'] ?? 'Save Settings'; ?>
            </button>
            <button class="button" style="background: var(--surface-2); color: var(--text); border: 1px solid var(--line);" onclick="closeModal('settingsModal')" style="flex: 1;">
                <?php echo $translations['close'] ?? 'Close'; ?>
            </button>
        </div>
    </div>
</div>
<div id="statsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><?php echo $translations['statistics'] ?? 'Statistics'; ?></h2>
            <button class="close-button" onclick="closeModal('statsModal')">&times;</button>
        </div>
        <div class="stats-container">
            <canvas id="statsChart"></canvas>
        </div>
        <div style="margin-top: 20px; text-align: center;">
            <button class="button" onclick="closeModal('statsModal')">
                <?php echo $translations['close'] ?? 'Close'; ?>
            </button>
        </div>
    </div>
</div>
<div id="downloadNotification" class="download-toast">
    <div class="toast-grip"></div>
    <div class="toast-header">
        <div class="toast-icon"><i class="fas fa-mobile-alt"></i></div>
        <div class="toast-header-text">
            <h4><?php echo $translations['notification_title'] ?? 'Mobile App'; ?></h4>
            <p><?php echo $translations['notification_desc'] ?? 'Download our app for the best experience'; ?></p>
        </div>
        <button class="toast-close" onclick="dismissNotification()" aria-label="<?php echo $translations['dismiss'] ?? 'Dismiss'; ?>">&times;</button>
    </div>
    <div class="toast-divider"></div>
    <div class="toast-actions">
        <button class="toast-btn toast-btn-primary" onclick="openInstallModal(); dismissNotification()">
            <i class="fas fa-download"></i> <?php echo $translations['download'] ?? 'Download'; ?>
        </button>
        <button class="toast-btn toast-btn-secondary" onclick="dismissNotification()">
            <i class="fas fa-eye-slash"></i> <?php echo $translations['dont_show_again'] ?? "Don't show again"; ?>
        </button>
    </div>
</div>
<div id="installModal" class="install-modal">
    <div class="container">
        <div class="install-header">
            <h1 style="margin: 0; color: var(--primary-color);"><?php echo $translations['download_app'] ?? 'Download Immich App'; ?></h1>
            <button class="close-button" onclick="closeModal('installModal')">&times;</button>
        </div>
        <div class="install-grid" id="installGrid">
            <div class="install-card" onclick="showInstallInstructions('ios')">
                <div class="install-icon"><i class="fab fa-apple"></i></div>
                <h3 class="install-title"><?php echo $translations['ios_title'] ?? 'iOS'; ?></h3>
                <p class="install-desc"><?php echo $translations['ios_desc'] ?? 'For iPhone and iPad'; ?></p>
                <a href="https://apps.apple.com/us/app/immich/id1613945652" target="_blank" class="download-btn" onclick="event.stopPropagation()">
                    <i class="fas fa-external-link-alt"></i> <?php echo $translations['download'] ?? 'Download'; ?>
                </a>
                <div class="install-card-hint"><i class="fas fa-arrow-right"></i> <?php echo $translations['install_instructions'] ?? 'Installation Instructions'; ?></div>
            </div>
            <div class="install-card" onclick="showInstallInstructions('android')">
                <div class="install-icon"><i class="fab fa-android"></i></div>
                <h3 class="install-title"><?php echo $translations['android_title'] ?? 'Android'; ?></h3>
                <p class="install-desc"><?php echo $translations['android_desc'] ?? 'APK file for Android devices'; ?></p>
                <a href="install/app-immich.apk" class="download-btn" onclick="event.stopPropagation()">
                    <i class="fas fa-download"></i> <?php echo $translations['download'] ?? 'Download'; ?>
                </a>
                <div class="install-card-hint"><i class="fas fa-arrow-right"></i> <?php echo $translations['install_instructions'] ?? 'Installation Instructions'; ?></div>
            </div>
            <div class="install-card" onclick="showInstallInstructions('win_installer')">
                <div class="install-icon"><i class="fab fa-windows"></i></div>
                <h3 class="install-title"><?php echo $translations['win_installer_title'] ?? 'Windows (Installer)'; ?></h3>
                <p class="install-desc"><?php echo $translations['win_installer_desc'] ?? 'Setup file for Windows'; ?></p>
                <a href="install/Immich Desktop Setup 1.1.3.exe" class="download-btn" onclick="event.stopPropagation()">
                    <i class="fas fa-download"></i> <?php echo $translations['download'] ?? 'Download'; ?>
                </a>
                <div class="install-card-hint"><i class="fas fa-arrow-right"></i> <?php echo $translations['install_instructions'] ?? 'Installation Instructions'; ?></div>
            </div>
            <div class="install-card" onclick="showInstallInstructions('win_portable')">
                <div class="install-icon"><i class="fab fa-windows"></i></div>
                <h3 class="install-title"><?php echo $translations['win_portable_title'] ?? 'Windows (Portable)'; ?></h3>
                <p class="install-desc"><?php echo $translations['win_portable_desc'] ?? 'Portable version for Windows'; ?></p>
                <a href="install/ImmichDesktop-Portable-1.1.2.exe" class="download-btn" onclick="event.stopPropagation()">
                    <i class="fas fa-download"></i> <?php echo $translations['download'] ?? 'Download'; ?>
                </a>
                <div class="install-card-hint"><i class="fas fa-arrow-right"></i> <?php echo $translations['install_instructions'] ?? 'Installation Instructions'; ?></div>
            </div>
            <div class="install-card" onclick="showInstallInstructions('linux_archive')">
                <div class="install-icon"><i class="fab fa-linux"></i></div>
                <h3 class="install-title"><?php echo $translations['linux_archive_title'] ?? 'Linux (Archive)'; ?></h3>
                <p class="install-desc"><?php echo $translations['linux_archive_desc'] ?? 'Compressed archive for Linux'; ?></p>
                <a href="install/ImmichDesktop-1.1.2-Archive.zip" class="download-btn" onclick="event.stopPropagation()">
                    <i class="fas fa-download"></i> <?php echo $translations['download'] ?? 'Download'; ?>
                </a>
                <div class="install-card-hint"><i class="fas fa-arrow-right"></i> <?php echo $translations['install_instructions'] ?? 'Installation Instructions'; ?></div>
            </div>
        </div>

        <div class="install-detail" id="installIosDetail" style="display:none">
            <button class="install-back-btn" onclick="backToInstallGrid()">
                <i class="fas fa-arrow-left"></i> <?php echo $translations['back_to_list'] ?? 'Back to list'; ?>
            </button>
            <div class="install-detail-content">
                <div class="install-detail-icon"><i class="fab fa-apple"></i></div>
                <h2 class="install-detail-title"><?php echo $translations['ios_title'] ?? 'iOS'; ?></h2>
                <h3 class="install-detail-subtitle"><?php echo $translations['install_instructions'] ?? 'Installation Instructions'; ?></h3>
                <ol class="install-steps">
                    <li><?php echo $translations['ios_instructions_1'] ?? ''; ?></li>
                    <li><?php echo $translations['ios_instructions_2'] ?? ''; ?></li>
                    <li><?php echo $translations['ios_instructions_3'] ?? ''; ?></li>
                    <li><?php echo $translations['ios_instructions_4'] ?? ''; ?></li>
                </ol>
                <a href="https://apps.apple.com/us/app/immich/id1613945652" target="_blank" class="download-btn">
                    <i class="fas fa-external-link-alt"></i> <?php echo $translations['download'] ?? 'Download'; ?>
                </a>
            </div>
        </div>

        <div class="install-detail" id="installAndroidDetail" style="display:none">
            <button class="install-back-btn" onclick="backToInstallGrid()">
                <i class="fas fa-arrow-left"></i> <?php echo $translations['back_to_list'] ?? 'Back to list'; ?>
            </button>
            <div class="install-detail-content">
                <div class="install-detail-icon"><i class="fab fa-android"></i></div>
                <h2 class="install-detail-title"><?php echo $translations['android_title'] ?? 'Android'; ?></h2>
                <h3 class="install-detail-subtitle"><?php echo $translations['install_instructions'] ?? 'Installation Instructions'; ?></h3>
                <ol class="install-steps">
                    <li><?php echo $translations['android_instructions_1'] ?? ''; ?></li>
                    <li><?php echo $translations['android_instructions_2'] ?? ''; ?></li>
                    <li><?php echo $translations['android_instructions_3'] ?? ''; ?></li>
                    <li><?php echo $translations['android_instructions_4'] ?? ''; ?></li>
                    <li><?php echo $translations['android_instructions_5'] ?? ''; ?></li>
                </ol>
                <a href="install/app-immich.apk" class="download-btn">
                    <i class="fas fa-download"></i> <?php echo $translations['download'] ?? 'Download'; ?>
                </a>
            </div>
        </div>

        <div class="install-detail" id="installWinInstallerDetail" style="display:none">
            <button class="install-back-btn" onclick="backToInstallGrid()">
                <i class="fas fa-arrow-left"></i> <?php echo $translations['back_to_list'] ?? 'Back to list'; ?>
            </button>
            <div class="install-detail-content">
                <div class="install-detail-icon"><i class="fab fa-windows"></i></div>
                <h2 class="install-detail-title"><?php echo $translations['win_installer_title'] ?? 'Windows (Installer)'; ?></h2>
                <h3 class="install-detail-subtitle"><?php echo $translations['install_instructions'] ?? 'Installation Instructions'; ?></h3>
                <ol class="install-steps">
                    <li><?php echo $translations['win_installer_instructions_1'] ?? ''; ?></li>
                    <li><?php echo $translations['win_installer_instructions_2'] ?? ''; ?></li>
                    <li><?php echo $translations['win_installer_instructions_3'] ?? ''; ?></li>
                    <li><?php echo $translations['win_installer_instructions_4'] ?? ''; ?></li>
                    <li><?php echo $translations['win_installer_instructions_5'] ?? ''; ?></li>
                </ol>
                <a href="install/Immich Desktop Setup 1.1.3.exe" class="download-btn">
                    <i class="fas fa-download"></i> <?php echo $translations['download'] ?? 'Download'; ?>
                </a>
            </div>
        </div>

        <div class="install-detail" id="installWinPortableDetail" style="display:none">
            <button class="install-back-btn" onclick="backToInstallGrid()">
                <i class="fas fa-arrow-left"></i> <?php echo $translations['back_to_list'] ?? 'Back to list'; ?>
            </button>
            <div class="install-detail-content">
                <div class="install-detail-icon"><i class="fab fa-windows"></i></div>
                <h2 class="install-detail-title"><?php echo $translations['win_portable_title'] ?? 'Windows (Portable)'; ?></h2>
                <h3 class="install-detail-subtitle"><?php echo $translations['install_instructions'] ?? 'Installation Instructions'; ?></h3>
                <ol class="install-steps">
                    <li><?php echo $translations['win_portable_instructions_1'] ?? ''; ?></li>
                    <li><?php echo $translations['win_portable_instructions_2'] ?? ''; ?></li>
                    <li><?php echo $translations['win_portable_instructions_3'] ?? ''; ?></li>
                    <li><?php echo $translations['win_portable_instructions_4'] ?? ''; ?></li>
                </ol>
                <a href="install/ImmichDesktop-Portable-1.1.2.exe" class="download-btn">
                    <i class="fas fa-download"></i> <?php echo $translations['download'] ?? 'Download'; ?>
                </a>
            </div>
        </div>

        <div class="install-detail" id="installLinuxArchiveDetail" style="display:none">
            <button class="install-back-btn" onclick="backToInstallGrid()">
                <i class="fas fa-arrow-left"></i> <?php echo $translations['back_to_list'] ?? 'Back to list'; ?>
            </button>
            <div class="install-detail-content">
                <div class="install-detail-icon"><i class="fab fa-linux"></i></div>
                <h2 class="install-detail-title"><?php echo $translations['linux_archive_title'] ?? 'Linux (Archive)'; ?></h2>
                <h3 class="install-detail-subtitle"><?php echo $translations['install_instructions'] ?? 'Installation Instructions'; ?></h3>
                <ol class="install-steps">
                    <li><?php echo $translations['linux_archive_instructions_1'] ?? ''; ?></li>
                    <li><?php echo $translations['linux_archive_instructions_2'] ?? ''; ?></li>
                    <li><?php echo $translations['linux_archive_instructions_3'] ?? ''; ?></li>
                    <li><?php echo $translations['linux_archive_instructions_4'] ?? ''; ?></li>
                    <li><?php echo $translations['linux_archive_instructions_5'] ?? ''; ?></li>
                </ol>
                <a href="install/ImmichDesktop-1.1.2-Archive.zip" class="download-btn">
                    <i class="fas fa-download"></i> <?php echo $translations['download'] ?? 'Download'; ?>
                </a>
            </div>
        </div>
    </div>
</div>
<div id="frameOverlay" class="frame-overlay">
    <div class="frame-header">
        <button class="frame-close" onclick="closeFrame()">&times;</button>
        <div class="frame-title" id="frameAlbumTitle"></div>
    </div>
    <iframe id="albumFrame" name="media-album" title="Album Viewer" class="frame-content"></iframe>
</div>
<div id="appDialog" class="modal modal--dialog" role="dialog" aria-modal="true" aria-labelledby="appDialogTitle">
    <div class="modal-content dialog-content">
        <div class="dialog-icon" id="appDialogIcon"><i class="fas fa-info"></i></div>
        <h2 class="modal-title dialog-title" id="appDialogTitle"></h2>
        <p class="dialog-message" id="appDialogMessage"></p>
        <div class="dialog-actions">
            <button type="button" class="button dialog-cancel" id="appDialogCancel"></button>
            <button type="button" class="button dialog-confirm" id="appDialogConfirm"></button>
        </div>
    </div>
</div>
</div>
