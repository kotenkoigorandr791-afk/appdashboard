<?php
require_once APP_ROOT . '/apps/viewer/core/db.php';
require_once APP_ROOT . '/apps/viewer/core/session.php';
require_once APP_ROOT . '/apps/viewer/models/ViewerModel.php';
require_once APP_ROOT . '/apps/viewer/controllers/ApiController.php';
handleViewerApi((int)($_SESSION['user']['role'] ?? 0));
public_api_json(['ok' => false, 'error' => 'unknown_viewer_action'], 404);
