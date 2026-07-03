<div class="header<?php echo $isFullscreen ? ' fullscreen' : ''; ?>">
<div class="title-section">
<div class="file-title"><?php echo htmlspecialchars($isFolder && $selectedFile ? $selectedFile['name'] : $fileName); ?></div>
<div class="file-badge"><?php echo strtoupper($isFolder && !$selectedFile ? 'FOLDER' : $fileType); ?></div>
</div>
<div class="actions">
<?php if ($fileType !== 'protected' && ($fileData['disable_copy'] ?? 0) === 0 && $fileType === 'text' && $fileContent !== false && $fileContent !== 'FILE_TOO_LARGE'): ?>
<button class="btn" onclick="copyAllContent()" title="<?php echo htmlspecialchars($translations['copy_content']); ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg></button>
<?php endif; ?>
<button class="btn-outline btn" onclick="openInfo()" title="<?php echo htmlspecialchars($translations['information']); ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg></button>
<button class="btn-outline btn" onclick="openSettings()" title="Settings"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.488.488 0 0 0-.59-.22l-2.39.96c-.3-.18-.63-.3-1-.37l-.35-2.38a.496.496 0 0 0-.48-.41h-3.84a.49.49 0 0 0-.49.41l-.35 2.38c-.17.07-.4.19-.68.37l-2.4-.96a.49.49 0 0 0-.61.23l-1.92 3.32c-.12.22-.07.49.12.61l2.04 1.58c-.04.3-.06.62-.06.94 0 .32.02.64.07.94L3.03 15.05a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.3.18.64.28 1.03.37l.35 2.38c.05.31.25.51.48.51h3.84c.24 0 .44-.2.48-.51l.35-2.38c.17-.08.4-.19.68-.37l2.4.96c.24.1.47 0 .59-.22l1.92-3.32a.48.48 0 0 0-.12-.61l-2.04-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg></button>
<button class="btn-outline btn" onclick="window.history.back()" title="Back"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg></button>
<?php if ($fileType !== 'protected' && ($fileData['disable_download'] ?? 0) === 0): ?><a class="btn" href="<?php echo htmlspecialchars($downloadUrl); ?>" title="Download"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg></a><?php endif; ?>
</div>
</div>
<div class="file-info"><div class="info-group"><div><span class="info-label"><?php echo htmlspecialchars($translations['file_size']); ?>: </span><span><?php echo htmlspecialchars(formatFileSize($fileSize)); ?></span></div><div><span class="info-label"><?php echo htmlspecialchars($translations['file_type']); ?>: </span><span><?php echo htmlspecialchars(strtoupper($fileType)); ?></span></div></div></div>
