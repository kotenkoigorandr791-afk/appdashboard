<?php
define('APPS_DATA_DIR', APP_ROOT . '/apps/map/user_data/');
require_once APP_ROOT . '/apps/map/core/config.php';
require_once APP_ROOT . '/apps/map/core/db.php';
require_once APP_ROOT . '/apps/map/models/User.php';
require_once APP_ROOT . '/apps/map/models/Location.php';
require_once APP_ROOT . '/apps/map/models/Route.php';
require_once APP_ROOT . '/apps/map/controllers/LocationController.php';
require_once APP_ROOT . '/apps/map/controllers/RouteController.php';
if (isset($parts[1]) && $parts[1] === 'location') {
    if (handleLocationAPI()) exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require APP_ROOT . '/apps/map/index.php';
    exit;
}
public_api_json(['ok' => false, 'error' => 'unsupported_map_request'], 400);
