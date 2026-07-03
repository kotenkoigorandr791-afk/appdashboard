<?php
require_once APP_ROOT . '/apps/tft-generator/core/db.php';
require_once APP_ROOT . '/apps/tft-generator/core/user.php';
require_once APP_ROOT . '/apps/tft-generator/core/init.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && ($parts[1] ?? '') === 'config') {
    public_api_json(['ok' => true, 'config' => $tftConfig ?? [], 'canvas' => $tftCanvas ?? [], 'gears' => $tftGears ?? []]);
}
require APP_ROOT . '/apps/tft-generator/controllers/PostHandler.php';
