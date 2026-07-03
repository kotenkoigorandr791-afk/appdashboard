<div class="error-view">
<svg class="error-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 7V3.5L18.5 9H13zm-2 9H8v-2h3v2zm0-4H8v-2h3v2z"/></svg>
<div style="font-size:16px;font-weight:500;margin-bottom:8px;"><?php echo htmlspecialchars($translations['archive_file'] ?? 'Archive file'); ?></div>
<div style="margin-bottom:16px;">(<?php echo htmlspecialchars(formatFileSize($fileSize)); ?>)</div>
<a class="btn download-file-btn" href="<?php echo htmlspecialchars($downloadUrl); ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg><?php echo htmlspecialchars($translations['download_file']); ?></a>
</div>
