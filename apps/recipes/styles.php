<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
header("Content-type: text/css; charset: UTF-8");

// Get parameters from URL
$theme = isset($_GET['theme']) && in_array($_GET['theme'], ['light', 'dark']) ? $_GET['theme'] : 'light';
$color = isset($_GET['color']) && preg_match('/^#[0-9A-Fa-f]{6}$/', $_GET['color']) ? $_GET['color'] : '#78a300';

function adjust_color_brightness($hex, $factor) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $r = min(255, max(0, round($r * $factor)));
    $g = min(255, max(0, round($g * $factor)));
    $b = min(255, max(0, round($b * $factor)));
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

function color_mix_hex($base, $mix, $amount) {
    $base = ltrim($base, '#');
    $mix = ltrim($mix, '#');
    $r = round(hexdec(substr($base, 0, 2)) * (1 - $amount) + hexdec(substr($mix, 0, 2)) * $amount);
    $g = round(hexdec(substr($base, 2, 2)) * (1 - $amount) + hexdec(substr($mix, 2, 2)) * $amount);
    $b = round(hexdec(substr($base, 4, 2)) * (1 - $amount) + hexdec(substr($mix, 4, 2)) * $amount);
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

$photo_border_color = color_mix_hex(adjust_color_brightness($color, 1.12), $theme === 'dark' ? '#ffd166' : '#0f8b8d', 0.28);

$btn_border_color = adjust_color_brightness($color, 0.58);

$btn_gradient_end = adjust_color_brightness($color, 0.80);

// Determine colors for contrast
$text_color = $theme === 'light' ? '#000000' : '#ffffff';
$scrollbar_thumb = $theme === 'light' ? '#b8b8b8' : '#4a4a4a';
$scrollbar_thumb_hover = $theme === 'light' ? '#9f9f9f' : '#5a5a5a';
$scrollbar_track = $theme === 'light' ? '#ececec' : '#1e1e1e';
$background_image = $theme === 'light' ? 'url("light-background.jpg")' : 'url("dark-background.jpg")';
$grouping_bg = $theme === 'light' ? 'rgba(137, 137, 137, 0.7)' : 'rgba(88, 88, 88, 0.77)';

$nameData = is_file(__DIR__ . '/name.json') ? json_decode((string)file_get_contents(__DIR__ . '/name.json'), true) : [];
if (!is_array($nameData)) {
    $nameData = [];
}
$parseMenuBtn = static function (array $data, string $key, array $fallback): array {
    $btn = isset($data[$key]) && is_array($data[$key]) ? $data[$key] : $fallback;
    $parsed = $fallback;
    foreach (['left', 'top', 'width', 'height'] as $k) {
        if (isset($btn[$k]) && is_numeric($btn[$k])) {
            $parsed[$k] = max(0, (int)$btn[$k]);
        }
    }
    return $parsed;
};
$defaultMenuBtn = ['left' => 16, 'top' => 16, 'width' => 40, 'height' => 40];
$menuBtn = $parseMenuBtn($nameData, 'menu_button', $defaultMenuBtn);
$menuBtnMobile = $parseMenuBtn($nameData, 'menu_button_mobile', $menuBtn);
$menuBtnReserve = $menuBtn['left'] + $menuBtn['width'] + 12;
$menuBtnMobileReserve = $menuBtnMobile['left'] + $menuBtnMobile['width'] + 10;
?>

:root {
    --primary-color: <?php echo $color; ?>;
    --photo-border-color: <?php echo $photo_border_color; ?>;
    --recipe-line-border-color: <?php echo color_mix_hex($color, $theme === 'dark' ? '#8fd3ff' : '#006d77', 0.34); ?>;
    --recipe-item-border-color: <?php echo color_mix_hex($color, $theme === 'dark' ? '#ffb86b' : '#b45f06', 0.28); ?>;
    --btn-border-color: <?php echo $btn_border_color; ?>;
    --btn-gradient-end: <?php echo $btn_gradient_end; ?>;
    --bg-color: <?php echo $theme === 'light' ? '#ffffff' : '#333333'; ?>;
    --text-color: <?php echo $text_color; ?>;
    --btn-bg-color: <?php echo adjust_color_brightness($color, $theme === 'dark' ? 0.70 : 0.65); ?>;
    --btn-hover-bg: <?php echo adjust_color_brightness($color, $theme === 'dark' ? 0.60 : 0.55); ?>;
    --border-radius: 16px;
    --btn-border-radius: 24px;
    --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    --hover-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
    --glow: 0 0 8px var(--primary-color);
    --background-image: <?php echo $background_image; ?>;
    --grouping-bg: <?php echo $grouping_bg; ?>;
    --ai-prompt-border: <?php echo $theme === 'dark' ? '#4ea3a3' : '#0f8b8d'; ?>;
    --ai-prompt-bg: <?php echo $theme === 'dark' ? '#132626' : '#f0fbfa'; ?>;
    --ai-preview-border: <?php echo $theme === 'dark' ? '#d99a4e' : '#c47f1e'; ?>;
    --ai-preview-bg: <?php echo $theme === 'dark' ? '#2a2118' : '#fff7ea'; ?>;
    --ai-image-border: <?php echo $theme === 'dark' ? '#c7a86a' : '#b3892d'; ?>;
    --ai-image-bg: <?php echo $theme === 'dark' ? '#272318' : '#fff9e8'; ?>;
    --ai-pending-border: <?php echo $theme === 'dark' ? '#e07a5f' : '#d65a31'; ?>;
    --ai-pending-bg: <?php echo $theme === 'dark' ? '#2a1d19' : '#fff1eb'; ?>;
    --ai-manual-border: <?php echo $theme === 'dark' ? '#8fb3d9' : '#3a6ea5'; ?>;
    --ai-manual-bg: <?php echo $theme === 'dark' ? '#172230' : '#f2f7fd'; ?>;
    --ai-surface: <?php echo $theme === 'dark' ? '#20252a' : '#ffffff'; ?>;
    --ai-surface-border: <?php echo $theme === 'dark' ? '#424b55' : '#d8e0e6'; ?>;
    --menu-button-reserve: <?php echo (int)$menuBtnReserve; ?>px;
    --menu-button-mobile-reserve: <?php echo (int)$menuBtnMobileReserve; ?>px;
    --app-top-bar-height: 72px;
}
* {
    scrollbar-width: thin;
    scrollbar-color: <?php echo $scrollbar_thumb; ?> <?php echo $scrollbar_track; ?>;
}
*::-webkit-scrollbar {
    width: 7px;
    height: 7px;
}
*::-webkit-scrollbar-track {
    background: <?php echo $scrollbar_track; ?>;
}
*::-webkit-scrollbar-thumb {
    background: <?php echo $scrollbar_thumb; ?>;
    border-radius: 999px;
}
*::-webkit-scrollbar-thumb:hover {
    background: <?php echo $scrollbar_thumb_hover; ?>;
}

body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background-color: var(--bg-color);
    background-image: var(--background-image);
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
    color: var(--text-color);
    margin: 0;
    padding: 0;
    min-height: 100vh;
}

/* Block main page scroll when a modal is open */
body.modal-open {
    overflow: hidden !important;
}

body::-webkit-scrollbar {
    width: 7px;
}

body::-webkit-scrollbar-track {
    background: var(--bg-color);
}

body::-webkit-scrollbar-thumb {
    background: <?php echo $scrollbar_thumb; ?>;
    border-radius: var(--border-radius);
}

body::-webkit-scrollbar-thumb:hover {
    background: <?php echo $scrollbar_thumb_hover; ?>;
}

.app-top-bar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 12px;
    min-height: var(--app-top-bar-height);
    box-sizing: border-box;
    padding: 10px 20px 10px max(16px, var(--menu-button-reserve));
    background: color-mix(in srgb, var(--bg-color) 18%, transparent);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border-bottom: 1px solid color-mix(in srgb, var(--primary-color) 14%, transparent);
    box-shadow: none;
}

.sidebar {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
    margin-left: auto;
    height: 44px;
}

.sidebar button {
    background-color: var(--btn-bg-color);
    color: var(--text-color);
    border: none;
    width: 44px;
    height: 44px;
    padding: 0;
    cursor: pointer;
    border-radius: var(--btn-border-radius);
    transition: all 0.3s ease;
    box-shadow: var(--shadow);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}



.sidebar button:hover {
    background-color: var(--btn-hover-bg) !important;
    transform: translateY(-2px);
    box-shadow: var(--hover-shadow);
}

.sidebar button:focus {
    outline: none;
    box-shadow: var(--glow);
}

.main-content {
    display: flex;
    flex-direction: column;
    padding: calc(var(--app-top-bar-height) + 16px) 20px 20px;
    box-sizing: border-box;
}

.recipe-search-container {
    flex: 1;
    min-width: 0;
    max-width: 640px;
    margin: 0;
    padding: 0;
    display: flex;
    align-items: center;
    height: 44px;
}

.recipe-search-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
    height: 44px;
}

.recipe-search-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
}

.recipe-search-input {
    width: 100%;
    height: 44px;
    box-sizing: border-box;
    padding: 0 12px 0 45px;
    border: 2px solid var(--primary-color);
    border-radius: 25px;
    background-color: color-mix(in srgb, var(--bg-color) 72%, transparent);
    color: var(--text-color);
    font-size: 16px;
    box-shadow: var(--shadow);
    transition: all 0.3s ease;
}

.recipe-search-input:focus {
    outline: none;
    box-shadow: var(--glow);
    border-color: var(--primary-color);
}

.recipe-search-input::placeholder {
    color: var(--text-color);
    opacity: 0.6;
}

.recipe-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 25px;
    padding: 0 0 20px;
    flex-grow: 1;
}

.recipe-card {
    position: relative;
    border: 1px solid var(--primary-color);
    padding: 15px;
    border-radius: var(--border-radius);
    text-align: center;
    background-color: var(--bg-color);
    box-shadow: var(--shadow);
    transition: transform 0.2s, box-shadow 0.2s;
    height: 300px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.recipe-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--hover-shadow);
}

.recipe-card img {
    max-width: 100%;
    max-height: 200px;
    object-fit: cover;
    border-radius: var(--border-radius);
}

.recipe-card h3 {
    color: var(--text-color);
    margin: 10px 0;
    font-size: 16px;
}

.card-actions {
    position: absolute;
    top: 5px;
    right: 5px;
    display: flex;
    gap: 3px;
}

.card-actions button {
    background-color: var(--btn-bg-color);
    color: var(--text-color);
    border: none;
    padding: 5px;
    border-radius: var(--btn-border-radius);
    cursor: pointer;
    box-shadow: var(--shadow);
    transition: all 0.3s ease;
    background-color: var(--btn-bg-color);
    border: none;
}



.card-actions button:hover {
    background-color: var(--btn-hover-bg) !important;
    transform: scale(1.1);
    box-shadow: var(--hover-shadow);
}

.card-actions button:focus {
    outline: none;
    box-shadow: var(--glow);
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.modal-content {
    background-color: var(--bg-color);
    padding: 30px;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    position: relative;
    overflow-y: auto;
    max-height: 80vh;
}

.modal-content::-webkit-scrollbar {
    width: 7px;
}

.modal-content::-webkit-scrollbar-track {
    background: transparent;
    margin: 16px 0;
}

.modal-content::-webkit-scrollbar-thumb {
    background: <?php echo $scrollbar_thumb; ?>;
    border-radius: var(--border-radius);
    border-top: 16px solid transparent;
    border-bottom: 16px solid transparent;
    background-clip: padding-box;
}

.modal-content::-webkit-scrollbar-thumb:hover {
    background: <?php echo $scrollbar_thumb_hover; ?>;
}

.recipe-view {
    max-width: 66.67%;
    width: 100%;
}

.recipe-view .recipe-image {
    max-width: 200px;
    max-height: 200px;
    object-fit: contain;
    border-radius: var(--border-radius);
    border: 2px solid var(--photo-border-color);
}

.recipe-view h3 {
    margin-top: 25px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--recipe-line-border-color);
    color: var(--primary-color);
    font-size: 1.3em;
}

.recipe-view h3:first-of-type {
    margin-top: 15px;
}

.ingredient-container {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
}

.ingredient-image {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: var(--border-radius);
    border: 1px solid var(--recipe-item-border-color);
}

.ingredient-image.faded {
    opacity: 0.5;
}

.quantity {
    font-weight: bold;
    color: var(--primary-color);
}

.timer-display {
    font-weight: bold;
    color: var(--primary-color);
    margin-top: 10px;
    display: block;
}

.complete-button {
    background-color: var(--btn-bg-color);
    color: var(--text-color);
    border: none;
    padding: 8px 16px;
    border-radius: var(--btn-border-radius);
    cursor: pointer;
    box-shadow: var(--shadow);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 5px;
}



.complete-button:hover {
    background-color: var(--btn-hover-bg) !important;
    box-shadow: var(--hover-shadow);
}

.complete-button:focus {
    outline: none;
    box-shadow: var(--glow);
}

.complete-button svg {
    width: 16px;
    height: 16px;
}

.modal-close {
    position: absolute;
    top: 20px;
    right: 20px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 24px;
    color: var(--text-color);
}

.modal-content h2 {
    margin-top: 0;
    color: var(--text-color);
}

.custom-select {
    appearance: none;
    background-color: var(--bg-color);
    border: 1px solid var(--primary-color);
    padding: 10px;
    border-radius: var(--border-radius);
    color: var(--text-color);
    font-size: 16px;
    width: 200px;
    cursor: pointer;
    box-shadow: var(--shadow), var(--glow);
    transition: border-color 0.2s;
}

.custom-select:hover {
    border-color: var(--text-color);
}

.custom-select:focus {
    outline: none;
    border-color: var(--primary-color);
}

form input[type="text"],
form input[type="file"],
form textarea {
    width: 25%;
    padding: 10px;
    margin: 10px 0;
    border: 1px solid var(--primary-color);
    border-radius: var(--border-radius);
    background-color: var(--bg-color);
    color: var(--text-color);
    box-shadow: var(--shadow);
}

form input[type="file"] {
    width: auto;
}

form textarea {
    width: 100%;
    min-height: 60px;
}

form button {
    background-color: var(--btn-bg-color);
    color: var(--text-color);
    border: none;
    padding: 10px 20px;
    border-radius: var(--btn-border-radius);
    cursor: pointer;
    box-shadow: var(--shadow), var(--glow);
    transition: transform 0.2s;
    position: relative;
    overflow: hidden;
    background-color: var(--btn-bg-color);
    border: none;
}



form button:hover {
    background-color: var(--btn-hover-bg) !important;
    transform: translateY(-2px);
}

.product-grid-container {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 20px;
    margin-top: 20px;
    flex-grow: 1;
}

.product-card {
    border: 2px solid var(--primary-color);
    padding: 8px;
    border-radius: var(--border-radius);
    text-align: center;
    box-shadow: var(--shadow);
    transition: all 0.3s ease;
    width: 120px;
    height: 160px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
}

.product-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--hover-shadow);
}

.product-card:focus {
    outline: none;
    box-shadow: var(--glow);
}

.product-card.selected {
    border-color: var(--text-color);
    background-color: var(--grouping-bg);
}

.product-card-actions {
    position: absolute;
    top: 5px;
    right: 5px;
    display: flex;
    gap: 3px;
}

.product-card-actions button {
    background-color: var(--btn-bg-color);
    color: var(--text-color);
    border: none;
    padding: 3px;
    border-radius: var(--btn-border-radius);
    cursor: pointer;
    box-shadow: var(--shadow);
    transition: all 0.3s ease;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: var(--btn-bg-color);
    border: none;
}



.product-card-actions button:hover {
    background-color: var(--btn-hover-bg) !important;
    transform: scale(1.1);
    box-shadow: var(--hover-shadow);
}

.product-card-actions button:focus {
    outline: none;
    box-shadow: var(--glow);
}

.product-card img {
    width: 100%;
    height: 80px;
    object-fit: cover;
    border-radius: var(--border-radius);
}

.product-card p {
    margin-top: auto;
    color: var(--text-color);
    margin: 10px 0 0 0;
    font-size: 14px;
    text-align: center;
}

/* Cropper Modal Styles */
.cropper-modal .modal-content {
    max-width: 90vw;
    max-height: 90vh;
    width: auto;
    height: auto;
    text-align: center;
}

.cropper-container {
    position: relative;
    display: inline-block;
    max-width: 85vw;
    max-height: calc(90vh - 200px);
    overflow: visible;
    box-sizing: border-box;
    vertical-align: middle;
    background: #000;
    border-radius: var(--border-radius);
}
.cropper-container img {
    display: block;
    width: auto;
    height: auto;
    max-width: 100%;
    max-height: calc(90vh - 200px);
    object-fit: contain;
    vertical-align: middle;
}

#cropSelection {
    position: absolute;
    top: 20%;
    left: 20%;
    width: 60%;
    height: 60%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);
    cursor: move;
}

.resize-handle {
    position: absolute;
    width: 10px;
    height: 10px;
    background: #fff;
    border: 1px solid #000;
}

.resize-handle[data-direction="top-left"] {
    top: -5px;
    left: -5px;
    cursor: nw-resize;
}

.resize-handle[data-direction="top-right"] {
    top: -5px;
    right: -5px;
    cursor: ne-resize;
}

.resize-handle[data-direction="bottom-left"] {
    bottom: -5px;
    left: -5px;
    cursor: sw-resize;
}

.resize-handle[data-direction="bottom-right"] {
    bottom: -5px;
    right: -5px;
    cursor: se-resize;
}

.resize-handle[data-direction="top"] {
    top: -5px;
    left: 50%;
    transform: translateX(-50%);
    cursor: n-resize;
}

.resize-handle[data-direction="bottom"] {
    bottom: -5px;
    left: 50%;
    transform: translateX(-50%);
    cursor: s-resize;
}

.resize-handle[data-direction="left"] {
    top: 50%;
    left: -5px;
    transform: translateY(-50%);
    cursor: w-resize;
}

.resize-handle[data-direction="right"] {
    top: 50%;
    right: -5px;
    transform: translateY(-50%);
    cursor: e-resize;
}

.cropper-actions {
    text-align: center;
    margin-top: 20px;
}

.cropper-actions button {
    margin: 0 10px;
    background-color: var(--btn-bg-color);
    color: var(--text-color);
    border: none;
    padding: 10px 20px;
    border-radius: var(--btn-border-radius);
    cursor: pointer;
    box-shadow: var(--shadow), var(--glow);
    transition: transform 0.2s;
    position: relative;
    overflow: hidden;
    background-color: var(--btn-bg-color);
    border: none;
}

.cropper-actions button:hover {
    background-color: var(--btn-hover-bg) !important;
    transform: translateY(-2px);
    box-shadow: var(--hover-shadow);
}

.cropper-actions button:focus {
    outline: none;
    box-shadow: var(--glow);
}


.image-source-switch-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin: 12px 0;
    flex-wrap: wrap;
}
.image-source-label {
    font-size: 14px;
    color: var(--text-color);
}
.form-switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 26px;
    flex-shrink: 0;
}
.form-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.form-switch-slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background: rgba(128,128,128,0.35);
    border-radius: 26px;
    transition: 0.2s;
    border: 1px solid var(--primary-color);
}
.form-switch-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 2px;
    background: var(--text-color);
    border-radius: 50%;
    transition: 0.2s;
}
.form-switch input:checked + .form-switch-slider {
    background: var(--primary-color);
}
.form-switch input:checked + .form-switch-slider:before {
    transform: translateX(22px);
}
.image-url-row {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 10px 0;
}
.image-url-input {
    width: 100%;
    box-sizing: border-box;
    padding: 10px;
    border-radius: var(--border-radius);
    border: 1px solid var(--primary-color);
    background: var(--bg-color);
    color: var(--text-color);
}
.btn-load-image-url {
    padding: 10px 16px;
    border-radius: var(--btn-border-radius);
    border: none;
    background-color: var(--btn-bg-color);
    border: none;
    color: var(--text-color);
    cursor: pointer;
    align-self: flex-start;
}
.image-file-row {
    margin: 8px 0;
    min-height: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: none;
    border-radius: var(--border-radius);
    background: transparent;
    cursor: default;
    transition: box-shadow 0.25s ease;
}

.image-file-row:hover,
.image-preview-container:hover {
    background: transparent;
    box-shadow: none;
}

.image-file-row.dragover {
    box-shadow: none;
    background: transparent;
}

.image-file-row input[type="file"] {
    width: 100%;
    cursor: pointer;
}

.step {
    border: 1px solid var(--primary-color);
    padding: 15px;
    margin: 10px 0;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    background-color: var(--bg-color);
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.step h4 {
    color: var(--text-color);
    margin-top: 0;
}

.step button {
    background-color: var(--btn-bg-color);
    color: var(--text-color);
    border: none;
    padding: 8px 16px;
    border-radius: var(--btn-border-radius);
    cursor: pointer;
    box-shadow: var(--shadow);
    transition: all 0.3s ease;
    background-color: var(--btn-bg-color);
    border: none;
}

.search-button {
    background-color: var(--btn-bg-color);
    color: var(--text-color);
    border: none;
    padding: 10px 20px;
    border-radius: var(--btn-border-radius);
    cursor: pointer;
    box-shadow: var(--shadow), var(--glow);
    transition: transform 0.2s;
    position: relative;
    overflow: hidden;
    background-color: var(--btn-bg-color);
    border: none;
}

.search-button:hover {
    background-color: var(--btn-hover-bg) !important;
    transform: translateY(-2px);
    box-shadow: var(--hover-shadow);
}

.search-button:focus {
    outline: none;
    box-shadow: var(--glow);
}

.image-tool-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 12px;
}

#recipeModal #recipeBgActions {
    margin-bottom: 16px;
}

.image-tool-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 10px 16px;
    border-radius: var(--btn-border-radius);
    border: none;
    background-color: var(--btn-bg-color);
    border: none;
    color: var(--text-color);
    cursor: pointer;
    box-shadow: var(--shadow);
}

.image-tool-btn:disabled {
    opacity: 0.65;
    cursor: wait;
}

.image-tool-btn-secondary {
    background: rgba(<?php echo implode(',', sscanf($color, '#%02x%02x%02x')); ?>, 0.12);
}



.step button:hover {
    background-color: var(--btn-hover-bg) !important;
    box-shadow: var(--hover-shadow);
}

.step button:focus {
    outline: none;
    box-shadow: var(--glow);
}

.step textarea {
    width: calc(100% - 30px);
    min-height: 60px;
    color: var(--text-color);
    background-color: var(--bg-color);
}

.quantity-container {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
}

.quantity-container label {
    color: var(--text-color);
    font-weight: bold;
}

.quantity-container input[type="number"] {
    width: 80px;
    padding: 5px;
    border: 1px solid var(--primary-color);
    border-radius: var(--border-radius);
    background-color: var(--bg-color);
    color: var(--text-color);
}

.quantity-container select {
    padding: 5px;
    border: 1px solid var(--primary-color);
    border-radius: var(--border-radius);
    background-color: var(--bg-color);
    color: var(--text-color);
}

.timer-input {
  display: flex;
  align-items: center;
  gap: 5px;
}
.timer-hours, .timer-minutes {
  width: 50px;
  padding: 5px;
  text-align: center;
}

.timer-input.active {
    display: flex;
    gap: 5px;
    align-items: center;
}

.timer-input input {
    width: 45px;
    padding: 5px;
    border: 1px solid var(--primary-color);
    border-radius: var(--border-radius);
    background-color: var(--bg-color);
    color: var(--text-color);
}
.product-card-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
.edit-product-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
}
.edit-product-table th, .edit-product-table td {
    padding: 8px;
    text-align: left;
    border: 1px solid #ddd;
}

.edit-product-table input[type="text"] {
    width: 450px;
    max-width: 100%;
    box-sizing: border-box;
}
.step-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.remove-step {
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px;
}

.remove-step svg {
    vertical-align: middle;
}

.notification-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.notification {
    background: <?php echo $theme === 'light' ? 'rgba(255, 255, 255, 0.95)' : 'rgba(40, 40, 40, 0.95)'; ?>;
    backdrop-filter: blur(10px);
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    border-left: 4px solid var(--primary-color);
    padding: 0;
    min-width: 320px;
    max-width: 450px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    animation: slideIn 0.3s ease-out;
    position: relative;
    overflow: hidden;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.notification-content {
    display: flex;
    align-items: stretch;
    flex: 1;
    padding: 16px 20px;
}

.notification-bar {
    width: 4px;
    background: var(--primary-color);
    border-radius: 2px;
    margin-right: 12px;
    flex-shrink: 0;
}

.notification.success {
    border-left-color: #4CAF50;
}

.notification.success .notification-bar {
    background: #4CAF50;
}

.notification.error {
    border-left-color: #f44336;
}

.notification.error .notification-bar {
    background: #f44336;
}

.notification.warning {
    border-left-color: #FF9800;
}

.notification.warning .notification-bar {
    background: #FF9800;
}

.notification.info {
    border-left-color: var(--primary-color);
}

.notification.info .notification-bar {
    background: var(--primary-color);
}

.notification-text {
    color: <?php echo $theme === 'light' ? '#1a1a1a' : '#e0e0e0'; ?>;
    font-size: 14px;
    line-height: 1.5;
    flex: 1;
    padding-right: 10px;
    font-weight: 400;
    letter-spacing: 0.2px;
}

.notification-close {
    background: none;
    border: none;
    color: <?php echo $theme === 'light' ? '#999' : '#888'; ?>;
    cursor: pointer;
    font-size: 20px;
    padding: 8px 12px;
    margin: 0;
    transition: color 0.2s ease;
    align-self: flex-start;
    line-height: 1;
}

.notification-close:hover {
    color: <?php echo $theme === 'light' ? '#666' : '#aaa'; ?>;
}

.loading {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background-color: var(--primary-color);
    color: var(--text-color);
    padding: 20px 30px;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    border: 2px solid var(--btn-border-color);
    font-size: 16px;
    z-index: 9999;
}

.loading::after {
    content: '';
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid var(--text-color);
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s linear infinite;
    margin-left: 10px;
    vertical-align: middle;
}

.product-modal {
    width: 95vw;
    height: 95vh;
    max-width: none;
    max-height: none;
    display: flex;
    flex-direction: column;
}

.product-management-toolbar {
    width: min(920px, calc(100% - 32px));
    margin: 0 auto 18px;
    padding: 16px 18px;
    border: 1px solid var(--primary-color);
    border-radius: calc(var(--border-radius) + 2px);
    background: linear-gradient(180deg, rgba(<?php echo implode(',', sscanf($color, '#%02x%02x%02x')); ?>, 0.12), rgba(<?php echo implode(',', sscanf($color, '#%02x%02x%02x')); ?>, 0.04));
    box-shadow: var(--shadow);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    box-sizing: border-box;
}

.product-management-actions {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-bottom: 14px;
    flex-wrap: wrap;
}

.product-management-btn {
    background: rgba(<?php echo implode(',', sscanf($color, '#%02x%02x%02x')); ?>, 0.12);
    color: var(--text-color);
    border: none;
    padding: 10px 18px;
    border-radius: var(--btn-border-radius);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: var(--shadow);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.product-management-btn:hover {
    background-color: var(--btn-hover-bg) !important;
    transform: translateY(-1px);
    box-shadow: var(--hover-shadow);
}

.product-management-btn-primary {
    background-color: var(--btn-bg-color);
    border: none;
    border-color: transparent;
}

.product-management-search {
    width: 100%;
    margin: 0;
}

#productViewModal.modal {
    z-index: 2200;
}
#productModal #productGrid .product-card {
    cursor: pointer;
}
.product-preview-modal {
    width: min(420px, calc(100vw - 32px));
    max-width: 420px;
    max-height: min(78vh, 560px);
    padding: 24px 20px 20px;
}

.product-preview-modal-body {
    text-align: center;
    padding: 4px 0 0;
}

.product-view-detail-img {
    max-width: 100%;
    max-height: min(42vh, 280px);
    width: auto;
    height: auto;
    object-fit: contain;
    border-radius: var(--border-radius);
    display: block;
    margin: 12px auto;
}

.product-preview-modal-name {
    margin: 8px 0 0;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-color);
}

.add-product-section {
    background-color: var(--grouping-bg);
    backdrop-filter: blur(10px);
    padding: 20px;
    border-radius: var(--border-radius);
    margin-bottom: 20px;
    box-shadow: var(--shadow);
}

.add-product-section h3 {
    margin-top: 0;
    color: var(--text-color);
    border-bottom: 2px solid var(--primary-color);
    padding-bottom: 10px;
}



#addProductModal.modal {
    align-items: flex-start;
    justify-content: center;
    padding: 20px 12px;
    box-sizing: border-box;
}
#addProductModal .modal-content.product-modal.add-product-modal-content {
    width: min(520px, calc(100vw - 24px)) !important;
    height: auto !important;
    max-height: min(92vh, 900px) !important;
    flex: 0 1 auto;
    display: flex;
    flex-direction: column;
}
#addProductModal .add-product-modal-body {
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: 100%;
    padding: 14px 12px 16px;
    box-sizing: border-box;
    border-radius: var(--border-radius);
    border: 1px solid rgba(<?php echo implode(',', sscanf($color, '#%02x%02x%02x')); ?>, 0.35);
    background: rgba(<?php echo implode(',', sscanf($color, '#%02x%02x%02x')); ?>, 0.06);
}
#addProductModal .add-product-form {
    display: flex;
    flex-direction: column;
    width: 100%;
    margin: 0;
}
#addProductModal .add-product-modal-content {
    max-width: 520px;
    width: min(520px, calc(100vw - 24px));
    max-height: min(92vh, 900px);
    height: auto;
    margin: 0 auto;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 22px 20px 24px;
    box-sizing: border-box;
}
#addProductModal .add-product-modal-heading {
    text-align: center;
    margin: 0 0 18px 0;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--primary-color);
    color: var(--text-color);
    font-size: 1.35rem;
}
#addProductModal .add-product-stack {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

#addProductModal .add-product-fields-grid {
    display: flex;
    flex-direction: column;
    gap: 14px;
}
#addProductModal .add-product-field-block {
    display: flex;
    flex-direction: column;
    gap: 0;
}
#addProductModal .add-product-image-structure {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
#addProductModal .add-product-inner {
    background: rgba(<?php echo implode(',', sscanf($color, '#%02x%02x%02x')); ?>, 0.1);
    border-radius: var(--border-radius);
    padding: 12px 14px;
    border: 1px solid rgba(<?php echo implode(',', sscanf($color, '#%02x%02x%02x')); ?>, 0.35);
    box-sizing: border-box;
}
#addProductModal .add-product-inner--switch {
    padding: 10px 14px;
}
#addProductModal .add-product-inner--inputs .image-file-row {
    margin-bottom: 0;
}
#addProductModal .add-product-inner--inputs .image-url-row {
    margin-bottom: 0;
}
#addProductModal .add-product-inner--inputs .btn-load-image-url {
    margin-top: 10px;
}
#addProductModal .add-product-inner--preview {
    padding: 12px;
}
#addProductModal .add-product-inner .image-source-switch-row.add-product-image-source {
    margin: 0;
}
#addProductModal .add-product-panel .image-preview-container.add-product-preview {
    margin-top: 0;
}

#addProductModal .add-product-panel {
    background-color: var(--grouping-bg);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: var(--border-radius);
    padding: 16px 18px;
    box-shadow: var(--shadow);
    border: 1px solid var(--primary-color);
}
#addProductModal .add-product-panel-title {
    margin: 0 0 14px 0;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--primary-color);
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-color);
}
#addProductModal .add-product-field-label {
    display: block;
    margin: 0 0 6px 0;
    font-size: 0.9rem;
    color: var(--text-color);
    opacity: 0.92;
}
#addProductModal .add-product-control {
    width: 100%;
    box-sizing: border-box;
}
#addProductModal .add-product-panel-actions {
    text-align: center;
    padding: 18px;
    border: 1px solid var(--primary-color);
    background-color: var(--grouping-bg);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
}
#addProductModal .add-product-submit-btn {
    min-width: 160px;
    padding: 12px 28px;
    font-size: 16px;
    border-radius: var(--btn-border-radius);
    border: none;
    background-color: var(--btn-bg-color);
    border: none;
    color: var(--text-color);
    cursor: pointer;
    box-shadow: var(--shadow), var(--glow);
}
#addProductModal .add-product-submit-btn:hover {
    background-color: var(--btn-hover-bg) !important;
    transform: translateY(-1px);
    box-shadow: var(--hover-shadow);
}

.product-search {
    width: 66.67%;
    margin: 0 auto 20px auto;
}

.search-container {
    position: relative;
    display: flex;
    align-items: center;
}

.search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
}

.product-search input {
    width: 100%;
    padding: 12px 12px 12px 40px;
    border: 2px solid var(--primary-color);
    border-radius: var(--border-radius);
    background-color: var(--bg-color);
    color: var(--text-color);
    font-size: 16px;
    box-shadow: var(--shadow);
}

.product-search input:focus {
    outline: none;
    box-shadow: var(--glow);
}

input[type="file"]:required {
    border: 2px solid var(--primary-color);
}

.selected-products {
    background-color: var(--grouping-bg);
    backdrop-filter: blur(10px);
    padding: 15px;
    border-radius: var(--border-radius);
    margin: 20px auto;
    box-shadow: var(--shadow);
    width: 66.67%;
    flex-shrink: 0;
}

.selected-products h3 {
    margin-top: 0;
    margin-bottom: 10px;
    color: var(--text-color);
}

.selected-products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 15px;
    margin-top: 10px;
}

.selected-products .product-card {
    width: 120px;
    height: 160px;
}
#searchRecipeModal .modal-content {
    display: flex;
    flex-direction: column;
    width: 100vw;
    height: 100vh;
    max-width: none;
    max-height: none;
    border-radius: 0;
    padding: 56px 24px 24px;
    overflow-y: auto !important;
    box-sizing: border-box;
}
#searchRecipeModal .search-button {
    font-weight: bold;
    width: fit-content;
    min-width: 180px;
    min-height: 46px;
    max-width: min(100%, 320px);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    white-space: normal;
    line-height: 1.25;
    text-align: center;
    margin: 20px auto 20px auto;
    box-sizing: border-box;
    background-color: var(--btn-bg-color);
    color: var(--text-color);
    border: none;
    padding: 10px 20px;
    border-radius: var(--btn-border-radius);
    cursor: pointer;
    box-shadow: var(--shadow), var(--glow);
    transition: transform 0.2s;
    font-size: 16px;
}

.search-button:hover {
    background-color: var(--btn-hover-bg) !important;
    transform: translateY(-2px);
}

.results-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    border: 1px solid var(--primary-color);
}

.results-table th,
.results-table td {
    border: 1px solid var(--primary-color);
    padding: 10px;
    text-align: left;
    color: var(--text-color);
}

.results-table th {
    background-color: var(--grouping-bg);
    font-weight: bold;
}

.results-table a {
    color: var(--primary-color);
    text-decoration: none;
}

.results-table a:hover {
    text-decoration: underline;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 10px;
    padding: 5px 0;
}

.pagination a {
    padding: 6px 10px;
    border: 1px solid var(--primary-color);
    border-radius: var(--border-radius);
    color: var(--text-color);
    text-decoration: none;
    font-size: 14px;
    transition: all 0.3s ease;
}

.pagination a:hover {
    background-color: var(--primary-color);
    color: var(--bg-color);
}

.pagination a.active {
    background-color: var(--primary-color);
    color: var(--bg-color);
    font-weight: bold;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

#steps {
    margin-bottom: 2%;
}

#recipeModal .recipe-form-image-source,
#recipeModal .recipe-image-file-row,
#recipeModal .recipe-image-url-row,
#recipeModal .recipe-image-preview {
    margin-bottom: 16px;
}

#recipeModal .recipe-steps-block {
    margin-top: 12px;
}

.timer-input input::placeholder {
    text-align: center;
}
.step-header .remove-step {
    background: none;
    border: none;
    color: red;
    font-size: 16px;
    cursor: pointer;
    padding: 0 5px;
}

#settingsForm input[type="number"] {
    width: 100%;
    padding: 12px;
    margin: 10px 0 20px 0;
    border: 2px solid var(--primary-color);
    border-radius: var(--border-radius);
    background-color: var(--bg-color);
    color: var(--text-color);
    font-size: 16px;
    box-shadow: var(--shadow);
    box-sizing: border-box;
}

#settingsForm input[type="number"]:focus {
    outline: none;
    box-shadow: var(--glow);
    border-color: var(--primary-color);
}

#settingsForm button {
    width: 100%;
    margin-top: 10px;
    padding: 12px;
    font-size: 16px;
}

/* Styles for the language switcher in recipe view */
.language-switcher {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    align-items: center;
    background-color: var(--grouping-bg);
    padding: 10px;
    border-radius: var(--border-radius);
    backdrop-filter: blur(10px);
}

.language-switcher span {
    color: var(--text-color);
    font-weight: bold;
}

.lang-btn {
    background-color: var(--bg-color);
    color: var(--text-color);
    border: 2px solid var(--btn-border-color);
    padding: 5px 10px;
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
}

.lang-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--hover-shadow);
}

.lang-btn.active {
    background-color: var(--primary-color);
    color: var(--text-color);
    font-weight: bold;
    box-shadow: var(--glow);
}

/* Styles for the share modal */
.share-modal {
    width: 80vw;
    max-width: 800px;
    max-height: 90vh;
}

.share-controls {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin: 20px 0;
    padding: 20px;
    background-color: var(--grouping-bg);
    border-radius: var(--border-radius);
    backdrop-filter: blur(10px);
}

.share-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.share-row label {
    min-width: 120px;
    color: var(--text-color);
    font-weight: bold;
}

.share-row select {
    flex-grow: 1;
    max-width: 300px;
}

.share-generate-btn {
    width: 100%;
    margin-top: 10px;
    padding: 12px;
    font-size: 16px;
    background-color: var(--primary-color);
    color: var(--text-color);
    border: 1px solid var(--btn-border-color);
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: bold;
}

.share-generate-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--hover-shadow);
}

.share-preview-container {
    margin-top: 20px;
    padding: 20px;
    background-color: var(--grouping-bg);
    border-radius: var(--border-radius);
    backdrop-filter: blur(10px);
}

.share-preview-container h3 {
    color: var(--text-color);
    margin-top: 0;
    margin-bottom: 15px;
    border-bottom: 2px solid var(--primary-color);
    padding-bottom: 10px;
}

.share-recipe-preview {
    max-height: 400px;
    overflow-y: auto;
    padding: 15px;
    background-color: var(--bg-color);
    border-radius: var(--border-radius);
    margin-bottom: 15px;
    border: 1px solid var(--primary-color);
}

.share-recipe-preview.light {
    background-color: #ffffff;
    color: #000000;
}

.share-recipe-preview.dark {
    background-color: #333333;
    color: #ffffff;
}

.share-header {
    text-align: center;
    margin-bottom: 20px;
}

.share-header h2 {
    color: var(--primary-color);
    margin: 0 0 10px 0;
}

.share-main-image {
    max-width: 200px;
    max-height: 200px;
    object-fit: cover;
    border-radius: var(--border-radius);
    border: 2px solid var(--primary-color);
}

.share-section {
    margin-bottom: 20px;
}

.share-section h3 {
    color: var(--primary-color);
    border-bottom: 2px solid var(--primary-color);
    padding-bottom: 5px;
    margin-bottom: 15px;
}

.share-ingredients {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
}

.share-ingredient {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    background-color: var(--grouping-bg);
    border-radius: var(--border-radius);
}

.share-ingredient-image {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: var(--border-radius);
    border: 1px solid var(--primary-color);
}

.share-steps {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.share-step {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px;
    background-color: var(--grouping-bg);
    border-radius: var(--border-radius);
    border-left: 4px solid var(--primary-color);
}

.share-step-number {
    font-weight: bold;
    color: var(--primary-color);
    min-width: 25px;
}

.share-timer {
    margin-left: auto;
    font-weight: bold;
    color: var(--primary-color);
    background-color: var(--grouping-bg);
    padding: 2px 8px;
    border-radius: var(--border-radius);
    font-size: 12px;
}

.share-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.share-actions button {
    padding: 10px 20px;
    font-size: 14px;
    background-color: var(--btn-bg-color);
    color: var(--text-color);
    border: none;
    border-radius: var(--btn-border-radius);
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: bold;
    min-width: 100px;
}

.share-actions button:hover {
    background-color: var(--btn-hover-bg) !important;
    transform: translateY(-2px);
    box-shadow: var(--hover-shadow);
}

/* Improved styles for the image preview */
.share-preview-wrapper {
    width: 100%;
    max-height: 60vh;
    overflow: auto;
    border-radius: var(--border-radius);
    background-color: var(--bg-color);
    border: 2px solid var(--primary-color);
    margin-bottom: 15px;
    position: relative;
}

.share-recipe-preview {
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    min-height: 200px;
}

.share-recipe-preview img {
    max-width: 100%;
    height: auto;
    display: block;
    border-radius: var(--border-radius);
}

/* Custom scroll for the preview */
.share-preview-wrapper::-webkit-scrollbar {
    width: 7px;
    height: 7px;
}

.share-preview-wrapper::-webkit-scrollbar-track {
    background: transparent;
    margin: 16px 0;
}

.share-preview-wrapper::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: var(--border-radius);
    border-top: 16px solid transparent;
    border-bottom: 16px solid transparent;
    background-clip: padding-box;
}

.share-preview-wrapper::-webkit-scrollbar-thumb:hover {
    background: var(--text-color);
}

/* Improved styles for the close cross in the recipe view modal */
#recipeViewModal .modal-close {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 10000 !important;
    background-color: var(--btn-bg-color);
    border: 2px solid var(--btn-border-color);
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    transition: all 0.3s ease;
}

#recipeViewModal .modal-close:hover {
    transform: scale(1.1);
    background-color: #ff3333;
    border-color: #ff3333;
}

#recipeViewModal .modal-close svg {
    width: 24px;
    height: 24px;
    stroke: white !important;
}

#recipeViewModal .modal-content.recipe-view {
    position: relative;
    overflow-y: auto;
    max-height: 80vh;
    padding-top: 60px;
}

/* ==================== MOBILE VERSION ==================== */
@media (max-width: 768px) {
    :root {
        --app-top-bar-height: 68px;
    }
    .app-top-bar {
        padding: 8px 10px 8px max(10px, var(--menu-button-mobile-reserve));
        gap: 8px;
    }
    .main-content {
        padding: calc(var(--app-top-bar-height) + 12px) 12px 16px;
    }
    .recipe-search-container {
        max-width: none;
    }
    .sidebar {
        gap: 6px;
        overflow-x: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .sidebar::-webkit-scrollbar {
        display: none;
    }
    .sidebar button {
        width: 40px;
        height: 40px;
        padding: 0;
        flex-shrink: 0;
    }
    .sidebar {
        height: 40px;
    }
    .recipe-search-container,
    .recipe-search-wrapper {
        height: 40px;
    }
    .recipe-search-input {
        height: 40px;
    }

    /* Base styles for mobile modals */
    .modal {
        padding: 0 !important;
        align-items: flex-start !important;
        background-color: var(--bg-color);
    }
    
    .modal-content {
        width: 100vw !important;
        height: 100vh !important;
        max-width: none !important;
        max-height: none !important;
        border-radius: 0 !important;
        padding: 50px 15px 15px 15px !important;
        overflow-y: auto !important;
    }
    
    .modal-close {
        top: 10px !important;
        right: 10px !important;
        z-index: 1001;
        background: var(--btn-bg-color);
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
    }
    
    /* ========== RECIPE SEARCH WINDOW STYLES - UNIFIED SCROLL ========== */
    #searchRecipeModal .modal-content {
        display: flex;
        flex-direction: column;
        padding-top: 60px !important;
        height: 100vh !important;
        overflow-y: auto !important; /* SINGLE SCROLL - ENTIRE CONTAINER */
        overflow-x: hidden !important;
        max-height: 100vh;
    }
    
    /* Remove individual scrolls from inner blocks */
    #searchRecipeModal .product-grid-container {
        flex-grow: 0;
        flex-shrink: 0;
        height: auto !important;
        max-height: none !important;
        overflow-y: visible !important;
        overflow-x: hidden !important;
        margin-bottom: 15px;
        width: 100%;
    }
    
    #searchProductGrid {
        overflow-y: visible !important;
        overflow-x: hidden !important;
        padding: 5px;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)) !important;
        gap: 10px;
        width: 100%;
        box-sizing: border-box;
    }
    
    #searchProductGrid .product-card {
        width: 100% !important;
        max-width: 100% !important;
        height: 130px;
        margin: 0 auto;
        box-sizing: border-box;
    }
    
    #searchProductGrid .product-card img {
        width: 100%;
        height: 70px;
        object-fit: cover;
    }
    
    #searchProductGrid .product-card p {
        font-size: 12px;
        margin: 5px 0 0 0;
        line-height: 1.2;
        word-wrap: break-word;
    }
    
    #searchRecipeModal .pagination {
        margin-top: 10px;
        margin-bottom: 15px;
        padding: 10px 0;
        background-color: var(--grouping-bg);
        border-radius: var(--border-radius);
        flex-shrink: 0;
        display: flex !important;
        justify-content: center;
        flex-wrap: wrap;
        gap: 5px;
        width: 100%;
        box-sizing: border-box;
    }
    
    .selected-products {
        width: 100% !important;
        max-height: none !important;
        overflow-y: visible !important;
        overflow-x: hidden !important;
        margin: 10px 0 !important;
        flex-shrink: 0;
        background-color: var(--grouping-bg);
        backdrop-filter: blur(10px);
        padding: 15px;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        box-sizing: border-box;
    }
    
    .selected-products h3 {
        font-size: 16px;
        margin-top: 0;
        margin-bottom: 10px;
        color: var(--text-color);
    }
    
    .selected-products-grid {
        display: grid !important;
        grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)) !important;
        gap: 10px;
        margin-top: 5px;
        width: 100%;
        box-sizing: border-box;
    }
    
    .selected-products .product-card {
        width: 100% !important;
        max-width: 100% !important;
        height: 130px;
        box-sizing: border-box;
    }
    
    .search-button {
        width: 100% !important;
        margin: 10px 0 15px 0 !important;
        padding: 12px;
        font-size: 16px;
        flex-shrink: 0;
        box-sizing: border-box;
    }
    
    #searchResults {
        flex-grow: 0;
        overflow-y: visible !important;
        overflow-x: hidden !important;
        margin-top: 10px;
        padding: 10px;
        background-color: var(--grouping-bg);
        border-radius: var(--border-radius);
        max-height: none !important;
        display: block !important;
        width: 100%;
        box-sizing: border-box;
    }
    
    #searchResults h3 {
        font-size: 16px;
        margin-top: 0;
        margin-bottom: 10px;
        color: var(--text-color);
    }
    
    #searchResults table {
        width: 100%;
        font-size: 14px;
        table-layout: fixed;
        word-wrap: break-word;
    }
    
    #searchResults table th,
    #searchResults table td {
        padding: 8px;
        word-break: break-word;
    }
    
    #searchResults a {
        color: var(--primary-color);
        text-decoration: none;
    }
    
    /* ========== ADD/EDIT RECIPE WINDOW STYLES ========== */
    #recipeModal .modal-content {
        overflow-x: hidden !important;
        overflow-y: auto !important;
        padding: 50px 15px 15px 15px !important;
        width: 100vw !important;
        box-sizing: border-box !important;
    }
    
    #recipeModal h2 {
        font-size: 20px;
        margin-top: 0;
        margin-bottom: 15px;
        padding-right: 40px;
        word-wrap: break-word;
        width: 100%;
        box-sizing: border-box;
    }
    
    #recipeModal .custom-select {
        width: 100% !important;
        max-width: 100%;
        margin-bottom: 15px;
        box-sizing: border-box;
    }
    
    #recipeModal form {
        width: 100%;
        box-sizing: border-box;
    }
    
    #recipeModal form input[type="text"],
    #recipeModal form input[type="file"],
    #recipeModal form textarea {
        width: 100% !important;
        max-width: 100%;
        box-sizing: border-box;
        margin: 10px 0;
    }
    
    #recipeModal .step {
        width: 100%;
        box-sizing: border-box;
        padding: 12px;
        margin: 10px 0;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    
    #recipeModal .step-header {
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    #recipeModal .step-header h4 {
        margin: 0;
        font-size: 16px;
    }
    
    #recipeModal .step button {
        width: 100%;
        margin: 5px 0;
        box-sizing: border-box;
    }
    
    #recipeModal .step textarea {
        min-width: 100% !important;
        width: 100% !important;
        max-width: 100%;
        min-height: 80px;
        box-sizing: border-box;
        resize: vertical;
    }
    
    #recipeModal .quantity-container {
        width: 100%;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        box-sizing: border-box;
    }
    
    #recipeModal .quantity-container label {
        width: 100%;
        margin-bottom: 5px;
    }
    
    #recipeModal .quantity-container input[type="number"] {
        width: calc(50% - 5px) !important;
        flex: 1;
        min-width: 80px;
        box-sizing: border-box;
    }
    
    #recipeModal .quantity-container select {
        width: calc(50% - 5px) !important;
        flex: 1;
        min-width: 80px;
        box-sizing: border-box;
    }
    
    #recipeModal .timer-input {
        width: 100%;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        box-sizing: border-box;
    }
    
    #recipeModal .timer-input input {
        width: calc(50% - 5px) !important;
        flex: 1;
        min-width: 70px;
        box-sizing: border-box;
    }
    
    #recipeModal .timer-input span {
        display: none;
    }
    
    #recipeModal .step label {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        margin: 5px 0;
    }
    
    #recipeModal .step input[type="checkbox"] {
        width: auto;
        margin: 0;
    }
    
    #recipeModal .step .remove-step {
        width: auto;
        padding: 5px;
        margin: 0;
    }
    
    #recipeModal .step .remove-step svg {
        width: 20px;
        height: 20px;
    }
    
    #steps {
        width: 100%;
        box-sizing: border-box;
    }
    
    #steps > button {
        width: 100%;
        margin: 15px 0;
        padding: 12px;
        font-size: 16px;
        box-sizing: border-box;
    }
    
    #recipeModal form > button[type="submit"] {
        width: 100%;
        margin: 20px 0;
        padding: 14px;
        font-size: 16px;
        box-sizing: border-box;
    }
    
    /* Hide body scroll when a modal is open */
    body.modal-open {
        overflow: hidden !important;
        position: fixed;
        width: 100%;
        height: 100%;
    }
    
    /* Prevent closing on background click on mobile */
    #searchRecipeModal.modal {
        touch-action: none;
    }
    
    #searchRecipeModal.modal:active {
        background-color: var(--bg-color);
    }
    
    /* Improve rendering of other modals */
    #productModal .modal-content,
    #productSelectionModal .modal-content {
        width: 100vw !important;
        height: 100vh !important;
        padding: 50px 10px 10px 10px !important;
        overflow-x: hidden !important;
    }
    #addProductModal .modal-content.add-product-modal-content {
        width: calc(100vw - 16px) !important;
        max-width: 520px !important;
        height: auto !important;
        max-height: calc(100vh - 24px) !important;
        margin: 12px auto !important;
        padding: 48px 14px 20px !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
    }
    
    #productModal .modal-content {
        display: flex;
        flex-direction: column;
        overflow-y: auto !important;
        overflow-x: hidden !important;
    }
    
    .product-grid-container {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        height: calc(100vh - 250px);
        overflow-x: hidden !important;
    }
    
    .product-grid {
        overflow-y: auto;
        overflow-x: hidden !important;
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)) !important;
        width: 100%;
        box-sizing: border-box;
    }
    
    .product-card {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box;
    }
}

@media (max-width: 768px) and (orientation: portrait) {
    #searchRecipeModal .product-grid-container {
        max-height: 35vh;
    }
    
    #searchResults {
        max-height: 25vh;
    }
}

@media (max-width: 768px) and (orientation: landscape) {
    #searchRecipeModal .modal-content {
        padding-top: 50px !important;
    }
    
    #searchRecipeModal .product-grid-container {
        max-height: 30vh;
    }
    
    .selected-products {
        max-height: 100px !important;
    }
    
    #searchResults {
        max-height: 20vh;
    }
}

@media (max-width: 480px) {
    /* Even smaller screens */
    #recipeModal .modal-content {
        padding: 50px 10px 10px 10px !important;
    }
    
    .step {
        padding: 10px;
    }
    
    .step textarea {
        min-height: 70px;
        font-size: 16px;
    }
    
    #recipeModalTitle {
        font-size: 18px;
    }
    
    .product-grid {
        grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)) !important;
        gap: 8px;
    }
    
    .product-card {
        height: 120px;
    }
    
    .product-card img {
        height: 60px;
    }
    
    .product-card p {
        font-size: 11px;
    }
    
    .selected-products-grid {
        grid-template-columns: repeat(auto-fit, minmax(70px, 1fr)) !important;
    }
    
    .selected-products .product-card {
        height: 110px;
    }
}

/* Mobile styles for the share modal */
@media (max-width: 768px) {
    .share-modal {
        width: 100vw;
        padding: 15px !important;
        overflow-x: hidden !important;
    }
    
    .share-row {
        flex-direction: column;
        align-items: flex-start;
        width: 100%;
    }
    
    .share-row label {
        min-width: auto;
        width: 100%;
        margin-bottom: 5px;
    }
    
    .share-row select {
        width: 100%;
        max-width: none;
    }
    
    .share-generate-btn {
        width: 100%;
    }
    
    .share-ingredients {
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    }
    
    .share-step {
        flex-wrap: wrap;
    }
    
    .share-actions {
        flex-direction: row;
        justify-content: center;
        align-items: center;
        flex-wrap: nowrap;
    }
    
    .share-actions button {
        width: auto;
    }
    
    .share-preview-wrapper {
        max-height: 50vh;
        overflow-x: auto !important;
    }
}

/* Image Preview Styles */
.image-preview-container {
    margin-top: 10px;
    text-align: center;
    cursor: pointer;
    position: relative;
    min-height: 240px;
    padding: 14px;
    background: rgba(128, 128, 128, 0.1);
    border-radius: var(--border-radius);
    border: 1px dashed var(--primary-color);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.image-preview-container.dragover {
    border-style: solid;
    box-shadow: var(--glow);
    background: rgba(<?php echo implode(',', sscanf($color, '#%02x%02x%02x')); ?>, 0.12);
}

.image-preview {
    max-width: 200px;
    max-height: 200px;
    width: auto;
    height: auto;
    object-fit: contain;
    border-radius: var(--border-radius);
    border: 2px solid var(--primary-color);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
    cursor: pointer;
    display: inline-block;
}
.image-preview-media {
    position: relative;
    display: inline-block;
    line-height: 0;
}

.image-preview:hover {
    transform: scale(1.05);
    box-shadow: var(--hover-shadow), 0 0 12px var(--primary-color);
}

.image-preview-hint {
    display: block;
    font-size: 12px;
    color: var(--text-color);
    opacity: 0.7;
    margin-top: 8px;
    font-style: italic;
}

.image-preview-clear {
    position: absolute;
    top: 4px;
    right: 4px;
    width: auto;
    height: auto;
    border-radius: 0;
    border: none;
    background: transparent;
    color: #e53935;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    z-index: 3;
    font-size: 24px;
    line-height: 1;
    font-weight: 700;
    padding: 0;
    box-shadow: none;
    outline: none;
    transform: none;
    filter: none;
}

.image-preview-clear:hover {
    background: transparent;
    color: #e53935;
    box-shadow: none;
    transform: none;
    filter: none;
}

.image-preview-clear:focus,
.image-preview-clear:active {
    background: transparent;
    color: #e53935;
    box-shadow: none;
    outline: none;
    transform: none;
    filter: none;
}

#editProductModal #editProductBgActions {
    margin-bottom: 16px;
}

.image-preview-overlay {
    position: absolute;
    inset: 10px;
    border-radius: var(--border-radius);
    background: rgba(0, 0, 0, 0.58);
    color: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    z-index: 2;
    pointer-events: none;
}

.image-preview-spinner {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: 3px solid rgba(255,255,255,0.28);
    border-top-color: #fff;
    animation: spin 0.8s linear infinite;
}

@media (max-width: 768px) {
    #recipeViewModal .modal-close {
        top: 10px;
        right: 10px;
        width: 35px;
        height: 35px;
        z-index: 10001 !important;
    }
    
    #recipeViewModal .modal-content.recipe-view {
        padding-top: 70px;
        overflow-x: hidden !important;
    }

    /* Image Preview Styles */
    .image-preview-container {
        margin-top: 10px;
        text-align: center;
        cursor: pointer;
        position: relative;
    }

    .image-preview {
        max-width: 200px;
        max-height: 200px;
        border-radius: var(--border-radius);
        border: 2px solid var(--primary-color);
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .image-preview:hover {
        transform: scale(1.05);
        box-shadow: var(--hover-shadow);
    }

    .image-preview-hint {
        display: block;
        font-size: 12px;
        color: var(--text-color);
        opacity: 0.7;
        margin-top: 5px;
    }

    /* Cropper Modal Styles */
    .cropper-modal {
        max-width: 90vw;
        max-height: 90vh;
    }
    .cropper-modal .modal-content {
        text-align: center;
    }
    .cropper-container {
        position: relative;
        display: inline-block;
        max-width: 85vw;
        max-height: calc(90vh - 200px);
        overflow: visible;
        margin: 20px 0;
        background: #000;
        border-radius: var(--border-radius);
        box-sizing: border-box;
        vertical-align: middle;
    }

    .cropper-container img {
        display: block;
        width: auto;
        height: auto;
        max-width: 100%;
        max-height: calc(90vh - 200px);
        object-fit: contain;
        user-select: none;
    }

    #cropSelection {
        position: absolute;
        border: 2px dashed #fff;
        box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);
        cursor: move;
        z-index: 10;
    }

    .resize-handle {
        position: absolute;
        background: var(--primary-color);
        border: 1px solid #fff;
        z-index: 20;
    }

    .resize-handle[data-direction="top-left"] {
        width: 12px;
        height: 12px;
        top: -6px;
        left: -6px;
        cursor: nwse-resize;
    }

    .resize-handle[data-direction="top-right"] {
        width: 12px;
        height: 12px;
        top: -6px;
        right: -6px;
        cursor: nesw-resize;
    }

    .resize-handle[data-direction="bottom-left"] {
        width: 12px;
        height: 12px;
        bottom: -6px;
        left: -6px;
        cursor: nesw-resize;
    }

    .resize-handle[data-direction="bottom-right"] {
        width: 12px;
        height: 12px;
        bottom: -6px;
        right: -6px;
        cursor: nwse-resize;
    }

    .resize-handle[data-direction="top"] {
        width: calc(100% - 24px);
        height: 6px;
        top: -3px;
        left: 12px;
        cursor: ns-resize;
    }

    .resize-handle[data-direction="bottom"] {
        width: calc(100% - 24px);
        height: 6px;
        bottom: -3px;
        left: 12px;
        cursor: ns-resize;
    }

    .resize-handle[data-direction="left"] {
        width: 6px;
        height: calc(100% - 24px);
        top: 12px;
        left: -3px;
        cursor: ew-resize;
    }

    .resize-handle[data-direction="right"] {
        width: 6px;
        height: calc(100% - 24px);
        top: 12px;
        right: -3px;
        cursor: ew-resize;
    }

    .cropper-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin-top: 20px;
    }

    .cropper-actions button {
        padding: 10px 30px;
        border: none;
        border-radius: var(--btn-border-radius);
        cursor: pointer;
        font-size: 16px;
        transition: all 0.3s ease;
    }

    .btn-apply {
        background-color: var(--primary-color);
        color: white;
        border: 2px solid var(--btn-border-color);
    }

    .btn-apply:hover {
        transform: translateY(-2px);
        box-shadow: var(--hover-shadow);
    }

    .btn-cancel {
        background-color: #999;
        color: white;
    }

    .btn-cancel:hover {
        background-color: #777;
    }
}

.ai-loading {
    z-index: 10000;
}
.add-recipe-choice-modal {
    max-width: 520px;
    text-align: center;
}
.add-recipe-choice-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-top: 20px;
}
.add-recipe-choice-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 24px 16px;
    border: 2px solid color-mix(in srgb, var(--primary-color) 72%, var(--border-color));
    border-radius: var(--border-radius);
    background: var(--bg-color);
    color: var(--text-color);
    cursor: pointer;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.add-recipe-choice-btn:hover {
    border-color: var(--primary-color);
    box-shadow: var(--hover-shadow);
}
.add-recipe-choice-ai {
    border-color: var(--primary-color);
}
.add-recipe-choice-icon {
    font-size: 28px;
}
.add-recipe-choice-label {
    font-weight: 700;
    font-size: 16px;
}
.add-recipe-choice-desc {
    font-size: 12px;
    opacity: 0.75;
    line-height: 1.4;
}
#aiRecipeWorkspaceModal.modal {
    padding: 0;
    align-items: stretch;
    justify-content: stretch;
}
#aiRecipeWorkspaceModal .modal-content.ai-recipe-workspace {
    width: 100vw;
    height: 100vh;
    max-width: none;
    max-height: none;
    border-radius: 0;
    margin: 0;
    padding: 56px 20px 16px 20px;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    gap: 18px;
    background:
        linear-gradient(180deg, color-mix(in srgb, var(--bg-color) 96%, var(--ai-prompt-border)), var(--bg-color));
}
#aiRecipeWorkspaceModal .modal-close {
    top: 12px;
    right: 12px;
}
.ai-workspace-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    flex-shrink: 0;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-color);
}
.ai-workspace-header h2 {
    margin: 0;
    font-size: 22px;
    letter-spacing: 0;
}
.ai-model-field {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border: 1px solid var(--ai-surface-border);
    border-radius: var(--border-radius);
    background: var(--ai-surface);
}
.ai-model-field span {
    font-size: 12px;
    font-weight: 700;
    color: color-mix(in srgb, var(--text-color) 75%, var(--ai-prompt-border));
    white-space: nowrap;
}
.ai-model-select {
    min-width: 220px;
}
.ai-workspace-zone {
    display: flex;
    flex-direction: column;
    gap: 18px;
    min-height: 0;
    overflow-y: auto;
    padding: 22px;
    border-radius: var(--border-radius);
    box-shadow: 0 10px 28px rgba(0,0,0,0.08), inset 0 1px 0 rgba(255,255,255,0.08);
}
.ai-zone-prompt {
    border: 2px solid var(--ai-prompt-border);
    background: var(--ai-prompt-bg);
}
.ai-zone-preview {
    border: 2px solid var(--ai-preview-border);
    background: var(--ai-preview-bg);
}
.ai-zone-image {
    border: 1px solid var(--ai-image-border) !important;
    background: var(--ai-image-bg) !important;
}
.ai-zone-pending {
    border: 2px solid var(--ai-pending-border) !important;
    background: var(--ai-pending-bg) !important;
}
.ai-workspace-manual .ai-zone-prompt {
    border-color: var(--ai-manual-border);
    background: var(--ai-manual-bg);
}
.ai-manual-hint {
    margin: 0;
    font-size: 13px;
    opacity: 0.85;
    line-height: 1.45;
}
.ai-manual-btn {
    padding: 12px 16px;
    border: none;
    border-radius: var(--border-radius);
    cursor: pointer;
    font-weight: 600;
    width: 100%;
    text-align: left;
    margin: 0;
    transition: transform 0.16s ease, box-shadow 0.16s ease, filter 0.16s ease;
}
.ai-manual-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 18px rgba(0,0,0,0.14);
    filter: brightness(1.03);
}
.ai-manual-btn-ingredient {
    background: color-mix(in srgb, #27ae60 85%, #000);
    color: #fff;
}
.ai-manual-btn-step {
    background: color-mix(in srgb, #2980b9 85%, #000);
    color: #fff;
}
.ai-save-progress {
    margin: 0;
}
.ai-save-progress-track {
    background: rgba(128,128,128,0.2);
    border-radius: 10px;
    height: 26px;
    position: relative;
    overflow: hidden;
}
.ai-save-progress-bar {
    background: var(--primary-color);
    height: 100%;
    width: 0%;
    border-radius: 10px;
    transition: width 0.3s ease;
}
.ai-save-progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 12px;
    font-weight: bold;
    color: var(--text-color);
    white-space: nowrap;
}
.ai-preview-step.ai-step-product {
    border-left: 4px solid var(--ai-preview-border);
    background: var(--ai-surface);
    border-color: var(--ai-surface-border);
}
.ai-preview-step.ai-step-instruction {
    border-left: 4px solid var(--ai-prompt-border);
    background: var(--ai-surface);
    border-color: var(--ai-surface-border);
}
.ai-step-product-thumb {
    width: 44px;
    height: 44px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    flex-shrink: 0;
    background: rgba(128,128,128,0.15);
}
.ai-step-product-thumb-placeholder {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    border: 1px dashed var(--border-color);
    flex-shrink: 0;
    background: rgba(128,128,128,0.1);
}
.ai-pending-candidate-opt {
    display: flex;
    align-items: center;
    gap: 8px;
}
.ai-pending-candidate-img {
    width: 28px;
    height: 28px;
    object-fit: cover;
    border-radius: 4px;
    flex-shrink: 0;
}
.ai-step-remove-btn {
    margin-left: auto;
    padding: 4px 8px;
    font-size: 12px;
    background: transparent;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    color: var(--text-color);
    cursor: pointer;
}
.ai-step-product-info {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
}
.ai-step-product-name {
    font-weight: 500;
}
.ai-step-pick-product-btn {
    padding: 6px 10px;
    font-size: 12px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--bg-color);
    color: var(--text-color);
    cursor: pointer;
    align-self: flex-start;
}
#aiPromptPanel,
#aiManualPanel {
    display: flex;
    flex-direction: column;
    gap: 16px;
    min-height: 0;
}
#aiRecipePreviewForm {
    display: flex;
    flex-direction: column;
    gap: 18px;
}
.ai-workspace-body {
    display: grid;
    grid-template-columns: minmax(240px, 1fr) minmax(0, 2fr);
    gap: 20px;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}
.ai-workspace-side {
    max-height: 100%;
    min-width: 0;
}
.ai-workspace-main {
    max-height: 100%;
    min-width: 0;
}
.ai-zone-title {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: var(--primary-color);
    padding-bottom: 12px;
    margin-bottom: 2px;
    border-bottom: 1px solid color-mix(in srgb, var(--primary-color) 25%, var(--border-color));
}
#aiRecipeInput {
    width: 100%;
    min-height: 160px;
    box-sizing: border-box;
    padding: 14px;
    margin: 0;
    border: 1px solid var(--ai-surface-border);
    border-radius: var(--border-radius);
    background: var(--ai-surface);
    color: var(--text-color);
    font-size: 15px;
    line-height: 1.45;
    resize: vertical;
}
#aiRecipeInput:focus,
#aiPreviewTitle:focus,
#aiPreviewImageUrl:focus,
.ai-preview-step textarea:focus,
.ai-preview-step input:focus,
.ai-preview-step select:focus {
    outline: none;
    border-color: var(--ai-prompt-border);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--ai-prompt-border) 22%, transparent);
}
.ai-submit-btn {
    padding: 12px 20px;
    border: none;
    border-radius: var(--border-radius);
    background: linear-gradient(135deg, #0f8b8d, #3a6ea5);
    color: #fff;
    cursor: pointer;
    font-weight: 600;
    width: 100%;
    margin: 0;
    transition: transform 0.16s ease, box-shadow 0.16s ease, filter 0.16s ease;
}
.ai-submit-btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 10px 22px rgba(15,139,141,0.26);
    filter: brightness(1.04);
}
.ai-submit-btn:disabled {
    cursor: not-allowed;
    opacity: 0.65;
}
.ai-activity-feed {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin: 0;
    max-height: 34vh;
    overflow-y: auto;
    padding-right: 2px;
    scroll-behavior: smooth;
}
.ai-activity-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 12px;
    border-radius: var(--border-radius);
    background: var(--ai-surface);
    border: 1px solid var(--ai-surface-border);
    font-size: 13px;
    line-height: 1.4;
    animation: aiFadeIn 0.35s ease;
}
.ai-activity-item.ai-activity-found { border-left: 3px solid #2ecc71; }
.ai-activity-item.ai-activity-pending { border-left: 3px solid #f39c12; }
.ai-activity-item.ai-activity-search { border-left: 3px solid var(--primary-color); }
.ai-activity-icon { flex-shrink: 0; font-size: 16px; }
@keyframes aiFadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}
.ai-preview-step.ai-step-appear { animation: aiFadeIn 0.4s ease; }
.ai-open-manual-btn {
    background: transparent !important;
    color: var(--text-color) !important;
    border: 1px solid var(--border-color) !important;
}
#aiPreviewTitle {
    width: 100%;
    box-sizing: border-box;
    padding: 12px;
    border: 1px solid var(--ai-surface-border);
    border-radius: var(--border-radius);
    background: var(--ai-surface);
    color: var(--text-color);
    font-size: 16px;
    margin: 0;
}
#aiPreviewImageUrl {
    width: 100%;
    box-sizing: border-box;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    background: var(--bg-color);
    color: var(--text-color);
}
.ai-workspace-save-row button {
    padding: 12px 20px;
    border: none;
    border-radius: var(--btn-border-radius);
    background: linear-gradient(135deg, #c47f1e, #0f8b8d);
    color: #fff;
    cursor: pointer;
    font-weight: 600;
    margin: 0;
}
.ai-refresh-image-btn,
.ai-choose-image-btn {
    padding: 8px 14px !important;
    font-size: 13px;
    background: transparent !important;
    color: var(--text-color) !important;
    border: 1px solid var(--border-color) !important;
}
.ai-choose-image-btn {
    background: color-mix(in srgb, var(--ai-prompt-border) 12%, var(--ai-surface)) !important;
    border-color: var(--ai-prompt-border) !important;
    font-weight: 700;
}
#aiManualPanel {
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.ai-preview-manual-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 16px;
}
.ai-preview-image-block {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 0;
    padding: 14px;
    border: 1px solid var(--ai-image-border);
    border-radius: var(--border-radius);
    background: var(--ai-image-bg);
}
.ai-preview-steps {
    margin-top: 0;
}
.ai-pending-products {
    margin-top: 12px;
}
.ai-save-progress {
    margin: 20px 0 0;
}
.ai-step-insert-zone {
    position: relative;
    min-height: 18px;
    margin: 2px 0;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.ai-step-insert-zone:focus {
    outline: none;
}
.ai-step-insert-line {
    display: block;
    width: 100%;
    height: 1px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--ai-surface-border) 70%, transparent);
    transition: height 0.16s ease, background 0.16s ease, box-shadow 0.16s ease;
}
.ai-step-insert-zone:hover .ai-step-insert-line,
.ai-step-insert-zone:focus .ai-step-insert-line,
.ai-step-insert-zone.is-open .ai-step-insert-line {
    height: 2px;
    background: linear-gradient(90deg, transparent, #0f8b8d, #c47f1e, transparent);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--ai-prompt-border) 10%, transparent);
}
.ai-insert-menu {
    position: absolute;
    z-index: 30;
    top: 50%;
    left: 50%;
    display: flex;
    gap: 8px;
    padding: 8px;
    border: 1px solid var(--ai-surface-border);
    border-radius: var(--border-radius);
    background: var(--ai-surface);
    box-shadow: 0 14px 35px rgba(0,0,0,0.2);
    transform: translate(-50%, 12px);
}
.ai-insert-menu button {
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    background: var(--bg-color);
    color: var(--text-color);
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}
.ai-insert-menu button:hover,
.ai-insert-menu button:focus {
    outline: none;
    border-color: transparent;
    background: color-mix(in srgb, var(--ai-prompt-border) 10%, var(--bg-color));
}
.ai-preview-step.ai-step-new-highlight {
    box-shadow: 0 0 0 2px var(--primary-color), 0 0 16px color-mix(in srgb, var(--primary-color) 45%, transparent);
    animation: aiStepHighlight 2.2s ease;
}
@keyframes aiStepHighlight {
    0% { background: color-mix(in srgb, var(--primary-color) 28%, var(--ai-surface)); }
    100% { background: var(--ai-surface); }
}
#aiImagePickerModal .modal-content {
    background: var(--bg-color);
    color: var(--text-color);
}
#aiImagePickerModal .modal-content,
#aiImagePickerModal .modal-content h2,
#aiImagePickerModal .ai-image-picker-hint,
#aiImagePickerModal .ai-image-picker-loading,
#aiImagePickerModal .ai-image-picker-caption,
#aiImagePickerModal .ai-image-picker-cancel {
    color: var(--text-color);
}
#aiImagePickerModal .ai-image-picker-option {
    color: var(--text-color);
}
.ai-preview-image-row {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.ai-preview-image-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
}
.ai-image-source-label {
    font-size: 12px;
    opacity: 0.8;
    flex: 1;
}
.ai-preview-image {
    max-width: 100%;
    max-height: 200px;
    object-fit: cover;
    border-radius: var(--border-radius);
    display: none;
    border: 1px solid var(--border-color);
}
.ai-preview-steps {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.ai-preview-step {
    padding: 14px;
    border: 1px solid var(--ai-surface-border);
    border-radius: var(--border-radius);
    background: var(--ai-surface);
}
.ai-preview-step-head {
    font-weight: 600;
    font-size: 13px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.ai-preview-step label {
    display: block;
    margin-top: 10px;
}
.ai-preview-step textarea,
.ai-preview-step input[type="number"]:not(.ai-step-qty):not(.ai-step-hours):not(.ai-step-minutes),
.ai-preview-step select:not(.ai-step-unit) {
    width: 100%;
    box-sizing: border-box;
    margin-top: 8px;
    padding: 8px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    background: var(--bg-color);
    color: var(--text-color);
}
.ai-preview-step-product-row .ai-step-qty,
.ai-preview-step-product-row .ai-step-unit {
    width: 100%;
    box-sizing: border-box;
    margin: 0;
    padding: 8px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    background: var(--bg-color);
    color: var(--text-color);
}
.ai-preview-step .ai-step-timer-row {
    display: flex;
    gap: 8px;
    margin-top: 8px;
}
.ai-preview-step .ai-step-timer-row input {
    width: auto;
    flex: 1;
    margin: 0;
    padding: 8px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    background: var(--bg-color);
    color: var(--text-color);
}
.ai-preview-step-product-row {
    display: grid;
    grid-template-columns: 44px 1fr 80px 100px;
    gap: 12px;
    align-items: center;
    margin-top: 4px;
}
.ai-workspace-save-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin: 4px 0 0;
    padding-top: 18px;
    border-top: 1px solid var(--border-color);
}
.ai-workspace-status {
    font-size: 13px;
    min-height: 20px;
    color: var(--primary-color);
    flex-shrink: 0;
}
.ai-workspace-log {
    font-size: 12px;
    opacity: 0.85;
    max-height: 100px;
    overflow-y: auto;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    background: var(--bg-color);
    margin-top: 4px;
}
.ai-preview-empty {
    opacity: 0.6;
    padding: 32px 16px;
    text-align: center;
}
.ai-pending-products {
    margin: 0;
    padding: 16px;
    border: 1px solid #f39c12;
    border-radius: var(--border-radius);
    background: color-mix(in srgb, #f39c12 8%, var(--bg-color));
}
.ai-pending-products h4 {
    margin: 0 0 14px 0;
    font-size: 14px;
    color: #d68910;
}
.ai-pending-item {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 14px;
    margin-bottom: 12px;
    border: 1px solid var(--ai-surface-border);
    border-radius: var(--border-radius);
    background: var(--ai-surface);
}
.ai-pending-item:last-child { margin-bottom: 0; }
.ai-pending-item-head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 0;
}
.ai-pending-name { font-weight: 600; font-size: 14px; }
.ai-pending-preview-img {
    min-height: 0;
}
.ai-pending-candidates select {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    background: var(--bg-color);
    color: var(--text-color);
}
.ai-pending-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 2px;
}
.ai-pending-actions button {
    padding: 8px 14px;
    border: none;
    border-radius: var(--btn-border-radius);
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    margin: 0;
}
.ai-pending-link { background: var(--primary-color); color: #fff; }
.ai-pending-create { background: #27ae60; color: #fff; }
.ai-pending-skip { background: var(--ai-surface); color: var(--text-color); border: 1px solid var(--ai-surface-border) !important; }
#aiImagePickerModal .ai-image-picker-modal {
    max-width: 720px;
    width: calc(100vw - 32px);
}
.ai-image-picker-hint {
    margin: 0 0 16px;
    opacity: 0.85;
    line-height: 1.45;
    font-size: 14px;
}
.ai-image-picker-loading {
    padding: 24px;
    text-align: center;
    opacity: 0.8;
}
.ai-image-picker-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 16px;
}
.ai-image-picker-option {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 10px;
    border: 2px solid var(--ai-surface-border);
    border-radius: var(--border-radius);
    background: var(--ai-surface);
    cursor: pointer;
    text-align: left;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.ai-image-picker-option:hover,
.ai-image-picker-option:focus {
    border-color: var(--primary-color);
    box-shadow: var(--glow);
    outline: none;
}
.ai-image-picker-option img {
    width: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
    border-radius: 6px;
    background: rgba(128,128,128,0.15);
}
.ai-image-picker-caption {
    font-size: 12px;
    line-height: 1.35;
    opacity: 0.85;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.ai-image-picker-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 8px;
    border-top: 1px solid var(--ai-surface-border);
}
.ai-image-picker-cancel {
    padding: 10px 18px;
    border: 1px solid var(--ai-surface-border);
    border-radius: var(--border-radius);
    background: var(--ai-surface);
    color: var(--text-color);
    cursor: pointer;
    font-weight: 600;
}
.delete-confirm-modal {
    max-width: 420px;
    text-align: center;
}
.delete-confirm-modal h2 { margin-top: 0; }
.delete-confirm-modal p { opacity: 0.85; line-height: 1.5; }
.delete-confirm-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-top: 20px;
}
.delete-confirm-cancel {
    padding: 10px 24px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    background: transparent;
    color: var(--text-color);
    cursor: pointer;
    font-weight: 600;
}
.delete-confirm-ok {
    padding: 10px 24px;
    border: none;
    border-radius: var(--border-radius);
    background: #c0392b;
    color: #fff;
    cursor: pointer;
    font-weight: 600;
}
@media (max-width: 900px) {
    .add-recipe-choice-grid {
        grid-template-columns: 1fr;
    }
    .ai-workspace-body {
        grid-template-columns: 1fr;
        grid-template-rows: auto minmax(0, 1fr);
        overflow-y: auto;
    }
    .ai-workspace-side {
        max-height: none;
    }
    .ai-workspace-main {
        max-height: none;
    }
    #aiRecipeWorkspaceModal .modal-content.ai-recipe-workspace {
        padding: 50px 12px 12px 12px;
        height: 100dvh;
    }
    .ai-preview-step-product-row {
        grid-template-columns: 44px 1fr;
        grid-template-areas:
            "thumb info"
            "qty qty"
            "unit unit";
    }
    .ai-preview-step-product-row .ai-step-product-thumb,
    .ai-preview-step-product-row .ai-step-product-thumb-placeholder {
        grid-area: thumb;
    }
    .ai-preview-step-product-row .ai-step-product-info {
        grid-area: info;
    }
    .ai-preview-step-product-row .ai-step-qty {
        grid-area: qty;
    }
    .ai-preview-step-product-row .ai-step-unit {
        grid-area: unit;
    }
    .ai-image-picker-grid {
        grid-template-columns: 1fr;
    }
    .ai-workspace-save-row {
        flex-direction: column;
    }
    .ai-workspace-save-row button {
        width: 100%;
    }
    .ai-pending-actions {
        flex-direction: column;
    }
    .ai-pending-actions button {
        width: 100%;
    }
}
@media (max-width: 768px) {
    .add-recipe-choice-grid {
        grid-template-columns: 1fr;
    }
    #aiRecipeWorkspaceModal .modal-content.ai-recipe-workspace {
        width: 100vw !important;
        max-width: none !important;
        height: 100dvh !important;
        max-height: none !important;
        padding: 50px 12px 12px 12px;
        box-sizing: border-box;
        overflow-x: hidden;
    }
    .ai-workspace-header,
    .ai-workspace-body,
    .ai-workspace-zone,
    #aiRecipePreviewForm,
    .ai-preview-image-block,
    .ai-preview-image-row,
    .ai-preview-image-meta,
    #aiPreviewImageUrl {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        min-width: 0;
    }
    .ai-workspace-zone {
        padding: 14px;
    }
    .ai-preview-image-meta {
        flex-direction: column;
        align-items: stretch;
    }
    .ai-image-source-label,
    .ai-refresh-image-btn,
    .ai-choose-image-btn {
        width: 100%;
        box-sizing: border-box;
    }
    .ai-image-source-label {
        overflow-wrap: anywhere;
    }
    #aiPreviewImageUrl {
        min-width: 0;
    }
}
