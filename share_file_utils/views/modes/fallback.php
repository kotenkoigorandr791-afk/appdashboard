<div class="error-view"><svg class="error-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><?php
$msg = $translations['unsupported_file'];
if ($fileType === 'folder') {
    $msg = $translations['no_file_selected'];
} elseif (in_array($fileType, ['archive', 'binary'], true)) {
    $msg = $translations['binary_file'];
}
echo htmlspecialchars($msg);
if (in_array($fileType, ['archive', 'binary'], true) && $fileSize > 0): ?>
<div style="margin-top:12px;"><a class="btn" href="<?php echo htmlspecialchars($downloadUrl); ?>" style="text-decoration:none;"><?php echo htmlspecialchars($translations['download_file']); ?></a></div>
<?php endif; ?></div>
