<?php
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/smart_log.php';
smart_log_bootstrap('dashboard');
define('SFU_DIR', __DIR__);
define('SFU_ASSET_BASE', 'share_file_utils/assets/');
$translationsFile = dirname(__DIR__) . '/translations.json';
$allTranslations = file_exists($translationsFile) ? json_decode(file_get_contents($translationsFile), true) : [];
$language = $_GET['lang'] ?? 'en';
$theme = $_GET['theme'] ?? 'light';
$color = $_GET['color'] ?? '4299e1';
$color = rawurldecode($color);
$color = str_replace('#', '', $color);
if (!preg_match('/^[0-9a-fA-F]{6}$/', $color)) {
    $color = '4299e1';
}
$isFullscreen = isset($_GET['fullscreen']) && $_GET['fullscreen'] === '1';
$fileViewerTranslations = [
    'file_not_found' => 'File not found',
    'file_not_accessible' => 'File is not accessible',
    'download_file' => 'Download',
    'copy_content' => 'Copy All',
    'content_copied' => 'Copied!',
    'binary_file' => 'Binary file',
    'unsupported_file' => 'Unsupported format',
    'no_file_selected' => 'No file selected',
    'close' => 'Close',
    'file_size' => 'Size',
    'file_type' => 'Type',
    'light_theme' => 'Light',
    'dark_theme' => 'Dark',
    'apply_settings' => 'Apply',
    'change_color' => 'Color',
    'theme' => 'Theme',
    'language' => 'Language',
    'view_image' => 'View Image',
    'zoom_in' => 'Zoom In',
    'zoom_out' => 'Zoom Out',
    'reset_zoom' => 'Reset Zoom',
    'rotate_left' => 'Rotate Left',
    'rotate_right' => 'Rotate Right',
    'information' => 'Information',
    'app_features' => 'Application Features',
    'text_features' => 'Text Files Features',
    'image_features' => 'Image Files Features',
    'pdf_features' => 'PDF Files Features',
    'media_features' => 'Media Files Features',
    'office_features' => 'Office Files Features',
    'feature_monaco_editor' => 'Syntax highlighting for 20+ languages',
    'feature_search_replace' => 'Find and replace text',
    'feature_copy_all' => 'Copy all content with one click',
    'feature_line_numbers' => 'Line numbers',
    'feature_zoom' => 'Zoom in/out and rotate',
    'feature_fullscreen' => 'Fullscreen view',
    'feature_pdf_view' => 'Built-in PDF viewer',
    'feature_media_controls' => 'Playback controls',
    'feature_download' => 'Download original file',
    'feature_office_note' => 'Built-in ONLYOFFICE viewer (read-only)',
    'feature_3d_view' => '3D model viewer with rotation, pan, zoom',
    'ok' => 'OK',
    'fullscreen' => 'Fullscreen',
    'exit_fullscreen' => 'Exit Fullscreen',
    'back' => 'Back',
    '3d_features' => '3D Models Features',
    'rotate_model' => 'Rotate',
    'pan_model' => 'Pan',
    'zoom_model' => 'Zoom',
    'fit_model' => 'Fit to view',
    'archive_file' => 'Archive file',
];
$translations = $fileViewerTranslations;
if (isset($allTranslations[$language])) {
    $translations = array_merge($translations, $allTranslations[$language]);
}
