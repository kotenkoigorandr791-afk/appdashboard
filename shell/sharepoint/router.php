<?php
require_once __DIR__ . '/bootstrap.php';

$action = strtolower(trim((string)($_GET['share_action'] ?? '')));
switch ($action) {
    case 'unlock':
        require __DIR__ . '/unlock.php';
        break;
    case 'content':
        require __DIR__ . '/content.php';
        break;
    case 'download':
        require __DIR__ . '/download.php';
        break;
    case 'actions':
        require __DIR__ . '/actions.php';
        break;
    case 'admin':
        require __DIR__ . '/admin.php';
        break;
    default:
        require __DIR__ . '/index.php';
        break;
}
