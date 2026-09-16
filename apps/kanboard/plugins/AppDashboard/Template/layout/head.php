<?php
$color = (string)($GLOBALS['APPDASHBOARD_KB_COLOR'] ?? '#2563eb');
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
    $color = '#2563eb';
}
$theme = (string)($GLOBALS['APPDASHBOARD_KB_THEME'] ?? 'light');
?>
<style id="appdashboard-kb-vars">
:root {
  --primary-color: <?= htmlspecialchars($color, ENT_QUOTES, 'UTF-8') ?>;
  --kb-shell-theme: <?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>;
}
</style>
