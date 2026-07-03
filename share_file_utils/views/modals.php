<!-- Settings Modal -->
<div class="settings-modal" id="settings-modal">
    <div class="settings-content">
        <div class="settings-title"><?php echo htmlspecialchars($translations['theme']); ?> / <?php echo htmlspecialchars($translations['language']); ?></div>
        <div class="settings-row">
            <label><?php echo htmlspecialchars($translations['theme']); ?></label>
            <select id="theme-select">
                <option value="light"<?php echo $theme==='light'?' selected':''; ?>><?php echo htmlspecialchars($translations['light_theme']); ?></option>
                <option value="dark"<?php echo $theme==='dark'?' selected':''; ?>><?php echo htmlspecialchars($translations['dark_theme']); ?></option>
            </select>
        </div>
        <div class="settings-row">
            <label><?php echo htmlspecialchars($translations['change_color']); ?></label>
            <input type="color" id="color-picker" value="#<?php echo htmlspecialchars($color); ?>">
        </div>
        <div class="settings-row">
            <label><?php echo htmlspecialchars($translations['language']); ?></label>
            <select id="language-select">
                <?php 
                $langs = array_keys($allTranslations ?: ['en' => 'EN', 'ru' => 'РУ', 'pl' => 'PL']);
                foreach($langs as $langCode): ?>
                <option value="<?php echo htmlspecialchars($langCode); ?>"<?php echo $language === $langCode ? ' selected' : ''; ?>><?php echo htmlspecialchars(strtoupper($langCode)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="settings-actions">
            <button class="btn-outline btn" type="button" onclick="closeSettings()"><?php echo htmlspecialchars($translations['close']); ?></button>
            <button class="btn" type="button" onclick="applySettings()"><?php echo htmlspecialchars($translations['apply_settings']); ?></button>
        </div>
    </div>
</div>

<!-- Info Modal -->
<div class="info-modal" id="info-modal">
    <div class="info-content">
        <div class="info-title"><?php echo htmlspecialchars($translations['app_features']); ?></div>
        <div class="features-list">
            <?php if ($fileType === 'text'): ?>
            <div class="feature-category"><?php echo htmlspecialchars($translations['text_features']); ?></div>
            <div class="feature-item"><svg class="feature-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4z"/></svg><div class="feature-text"><?php echo htmlspecialchars($translations['feature_monaco_editor']); ?></div></div>
            <div class="feature-item"><svg class="feature-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5z"/></svg><div class="feature-text"><?php echo htmlspecialchars($translations['feature_search_replace']); ?></div></div>
            <div class="feature-item"><svg class="feature-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2z"/></svg><div class="feature-text"><?php echo htmlspecialchars($translations['feature_copy_all']); ?></div></div>
            <div class="feature-item"><svg class="feature-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M4 10h12v2H4zm0-4h12v2H4zm0 8h8v2H4zm10 0v6l5-3z"/></svg><div class="feature-text"><?php echo htmlspecialchars($translations['feature_line_numbers']); ?></div></div>
            <?php elseif ($fileType === 'image'): ?>
            <div class="feature-category"><?php echo htmlspecialchars($translations['image_features']); ?></div>
            <div class="feature-item"><svg class="feature-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5z"/></svg><div class="feature-text"><?php echo htmlspecialchars($translations['feature_zoom']); ?></div></div>
            <div class="feature-item"><svg class="feature-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg><div class="feature-text"><?php echo htmlspecialchars($translations['feature_fullscreen']); ?></div></div>
            <div class="feature-item"><svg class="feature-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg><div class="feature-text"><?php echo htmlspecialchars($translations['feature_download']); ?></div></div>
            <?php elseif ($fileType === '3dmodel'): ?>
            <div class="feature-category"><?php echo htmlspecialchars($translations['3d_features'] ?? '3D Models Features'); ?></div>
            <div class="feature-item"><svg class="feature-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/><circle cx="12" cy="12" r="3"/></svg><div class="feature-text"><?php echo htmlspecialchars($translations['feature_3d_view'] ?? '3D model viewer with rotation, pan, zoom'); ?></div></div>
            <div class="feature-item"><svg class="feature-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M7.11 8.53L5.7 7.11C4.8 8.27 4.24 9.61 4.07 11h2.02c.14-.87.49-1.72 1.02-2.47zM6.09 13H4.07c.17 1.39.72 2.73 1.62 3.89l1.41-1.42c-.52-.75-.87-1.59-1.01-2.47zm1.01 5.32c1.16.9 2.51 1.44 3.9 1.61V17.9c-.87-.15-1.71-.49-2.46-1.03L7.1 18.32zM13 4.07V1L8.45 5.55 13 10V6.09c2.84.48 5 2.94 5 5.91s-2.16 5.43-5 5.91v2.02c3.95-.49 7-3.85 7-7.93s-3.05-7.44-7-7.93z"/></svg><div class="feature-text"><?php echo htmlspecialchars($translations['rotate_model'] ?? 'Rotate model'); ?></div></div>
            <div class="feature-item"><svg class="feature-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 5V1L7 6l5 5V7c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z"/></svg><div class="feature-text"><?php echo htmlspecialchars($translations['fit_model'] ?? 'Fit to view'); ?></div></div>
            <?php elseif ($fileType === 'pdf'): ?>
            <div class="feature-category"><?php echo htmlspecialchars($translations['pdf_features']); ?></div>
            <div class="feature-item"><svg class="feature-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm10 5.5h1v-3h-1v3z"/></svg><div class="feature-text"><?php echo htmlspecialchars($translations['feature_pdf_view']); ?></div></div>
            <div class="feature-item"><svg class="feature-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg><div class="feature-text"><?php echo htmlspecialchars($translations['feature_download']); ?></div></div>
            <?php elseif ($fileType === 'video' || $fileType === 'audio'): ?>
            <div class="feature-category"><?php echo htmlspecialchars($translations['media_features']); ?></div>
            <div class="feature-item"><svg class="feature-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3v9.28c-.47-.17-.97-.28-1.5-.28C8.01 12 6 14.01 6 16.5S8.01 21 10.5 21c2.31 0 4.2-1.75 4.45-4H15V6h4V3h-7z"/></svg><div class="feature-text"><?php echo htmlspecialchars($translations['feature_media_controls']); ?></div></div>
            <div class="feature-item"><svg class="feature-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg><div class="feature-text"><?php echo htmlspecialchars($translations['feature_download']); ?></div></div>
            <?php elseif ($fileType === 'office'): ?>
            <div class="feature-category"><?php echo htmlspecialchars($translations['office_features']); ?></div>
            <div class="feature-item"><svg class="feature-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zM7 10h2v7H7zm4-3h2v10h-2zm4-3h2v13h-2z"/></svg><div class="feature-text"><?php echo htmlspecialchars($translations['feature_office_note']); ?> (read-only)</div></div>
            <div class="feature-item"><svg class="feature-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg><div class="feature-text"><?php echo htmlspecialchars($translations['feature_download']); ?></div></div>
            <?php endif; ?>
        </div>
        <div class="info-actions">
            <button class="btn" type="button" onclick="closeInfo()"><?php echo htmlspecialchars($translations['ok']); ?></button>
        </div>
    </div>
</div>
