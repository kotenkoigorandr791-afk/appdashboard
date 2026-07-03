<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../models/ViewerModel.php';

function viewerJsonError(string $msg): void {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

function handleViewerApi(int $userRole): bool {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        header('Content-Type: application/json; charset=UTF-8');
        $db = getDB();
        if (!$db) viewerJsonError('Database unavailable');
        if ($_GET['action'] === 'load_config') {
            viewerEnsureToken($db);
            $currentConfig = viewerLoadConfigArray($db);
            echo json_encode(['success' => true, 'config' => $currentConfig, 'user_role' => $userRole]);
            exit;
        }
        if ($_GET['action'] === 'get_token') {
            echo json_encode(['success' => true, 'token' => viewerEnsureToken($db)]);
            exit;
        }
        viewerJsonError('Unknown action');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=UTF-8');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) viewerJsonError('Invalid JSON data');
        $db = getDB();
        if (!$db) viewerJsonError('Database unavailable');
        viewerEnsureToken($db);
        $currentConfig = viewerLoadConfigArray($db);
        $action = $data['action'] ?? '';
        switch ($action) {
            case 'save_config':
                handleSaveConfig($db, $data, $currentConfig);
                break;
            case 'add_site':
                handleAddSite($db, $data, $currentConfig);
                break;
            case 'edit_site':
                handleEditSite($db, $data, $currentConfig);
                break;
            case 'delete_site':
                handleDeleteSite($db, $data, $currentConfig);
                break;
            case 'toggle_hidden':
                handleToggleHidden($db, $data, $currentConfig);
                break;
            case 'update_token':
                handleUpdateToken($db, $data, $userRole);
                break;
            default:
                viewerJsonError('Unknown action');
        }
        exit;
    }
    return false;
}

function handleSaveConfig(AppDatabase $db, array $data, array $currentConfig): void {
    if (!isset($data['config'])) viewerJsonError('Config data missing');
    $newConfig = $data['config'];
    if (!is_array($newConfig)) viewerJsonError('Invalid config');
    $newConfig['token'] = $currentConfig['token'];
    $sites = isset($newConfig['sites']) && is_array($newConfig['sites']) ? $newConfig['sites'] : [];
    try {
        viewerSaveMetaFromArray($db, $newConfig);
        viewerReplaceSites($db, $sites);
        echo json_encode(['success' => true, 'config' => viewerLoadConfigArray($db)]);
    } catch (Exception $e) {
        error_log('viewer save_config: ' . $e->getMessage());
        viewerJsonError('Failed to save config');
    }
}

function handleAddSite(AppDatabase $db, array $data, array $currentConfig): void {
    if (!isset($data['site'])) viewerJsonError('Site data missing');
    $newSite = $data['site'];
    if (empty($newSite['name']) || empty($newSite['url'])) viewerJsonError('Site name or URL missing');
    $newSite['priority'] = isset($newSite['priority']) ? (int)$newSite['priority'] : 0;
    $newSite['hidden'] = isset($newSite['hidden']) ? (bool)$newSite['hidden'] : false;
    $newSite['use_token'] = isset($newSite['use_token']) ? (bool)$newSite['use_token'] : false;
    $sites = $currentConfig['sites'];
    $existingIndex = array_search($newSite['url'], array_column($sites, 'url'), true);
    if ($existingIndex !== false) {
        $sites[$existingIndex] = $newSite;
    } else {
        $sites[] = $newSite;
    }
    try {
        viewerReplaceSites($db, $sites);
        echo json_encode(['success' => true, 'config' => viewerLoadConfigArray($db)]);
    } catch (Exception $e) {
        error_log('viewer add_site: ' . $e->getMessage());
        viewerJsonError('Failed to save config');
    }
}

function handleEditSite(AppDatabase $db, array $data, array $currentConfig): void {
    if (!isset($data['site']) || !isset($data['index'])) viewerJsonError('Site data or index missing');
    $index = (int)$data['index'];
    if (!isset($currentConfig['sites'][$index])) viewerJsonError('Invalid index');
    if (empty($data['site']['name']) || empty($data['site']['url'])) viewerJsonError('Site name or URL missing');
    $data['site']['priority'] = isset($data['site']['priority']) ? (int)$data['site']['priority'] : 0;
    $data['site']['hidden'] = isset($data['site']['hidden']) ? (bool)$data['site']['hidden'] : false;
    $data['site']['use_token'] = isset($data['site']['use_token']) ? (bool)$data['site']['use_token'] : false;
    $sites = $currentConfig['sites'];
    $sites[$index] = $data['site'];
    try {
        viewerReplaceSites($db, $sites);
        echo json_encode(['success' => true, 'config' => viewerLoadConfigArray($db)]);
    } catch (Exception $e) {
        error_log('viewer edit_site: ' . $e->getMessage());
        viewerJsonError('Failed to save config');
    }
}

function handleDeleteSite(AppDatabase $db, array $data, array $currentConfig): void {
    if (!isset($data['index'])) viewerJsonError('Index missing');
    $index = (int)$data['index'];
    if (!isset($currentConfig['sites'][$index])) viewerJsonError('Invalid index');
    $sites = $currentConfig['sites'];
    array_splice($sites, $index, 1);
    try {
        viewerReplaceSites($db, $sites);
        echo json_encode(['success' => true, 'config' => viewerLoadConfigArray($db)]);
    } catch (Exception $e) {
        error_log('viewer delete_site: ' . $e->getMessage());
        viewerJsonError('Failed to save config');
    }
}

function handleToggleHidden(AppDatabase $db, array $data, array $currentConfig): void {
    if (!isset($data['index'])) viewerJsonError('Index missing');
    $index = (int)$data['index'];
    if (!isset($currentConfig['sites'][$index])) viewerJsonError('Invalid index');
    $sites = $currentConfig['sites'];
    $sites[$index]['hidden'] = !$sites[$index]['hidden'];
    try {
        viewerReplaceSites($db, $sites);
        echo json_encode(['success' => true, 'config' => viewerLoadConfigArray($db)]);
    } catch (Exception $e) {
        error_log('viewer toggle_hidden: ' . $e->getMessage());
        viewerJsonError('Failed to save config');
    }
}

function handleUpdateToken(AppDatabase $db, array $data, int $userRole): void {
    if ($userRole < 2) viewerJsonError('Access denied');
    if (!isset($data['token'])) viewerJsonError('Token missing');
    $newToken = viewerUpdateToken($db, $data['token']);
    echo json_encode(['success' => true, 'token' => $newToken]);
}
