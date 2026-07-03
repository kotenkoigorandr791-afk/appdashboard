<?php
function handleRoutePostActions($lang, $theme, $color, $view_mode, $display_mode): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) return;
    switch ($_POST['action']) {
        case 'add_route':
            $name = $_POST['name'];
            $file = $_FILES['route_file'];
            global $UPLOAD_DIR;
            if ($file['error'] === UPLOAD_ERR_OK) {
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = uniqid() . '.' . $extension;
                $uploadPath = $UPLOAD_DIR . $filename;
                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $points = [];
                    $originalExtension = $extension;
                    if ($extension === 'kml') $points = parseKml($uploadPath);
                    elseif ($extension === 'csv') $points = parseCsv($uploadPath);
                    elseif ($extension === 'html') $points = parseHtml($uploadPath);
                    elseif ($extension === 'gpx') $points = parseGpx($uploadPath);
                    if (!empty($points)) {
                        if ($originalExtension !== 'csv') {
                            $csvFilename = uniqid() . '.csv';
                            generateCsvFromPoints($points, $csvFilename);
                            unlink($uploadPath);
                            $filename = $csvFilename;
                        }
                        saveRoute(uniqid(), $name, $filename, $points);
                        header("Location: ?lang=$lang&theme=$theme&color=" . urlencode($color) . "&view_mode=$view_mode&display_mode=$display_mode");
                        exit;
                    }
                }
            }
            break;
        case 'add_folder':
            if (isset($_FILES['folder_files'])) {
                $name = $_POST['name'];
                $files = $_FILES['folder_files'];
                $processedCount = 0;
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $extension = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                    if (!in_array($extension, ['kml', 'csv', 'html', 'gpx'])) continue;
                    $tempPath = $files['tmp_name'][$i];
                    $points = [];
                    if ($extension === 'kml') $points = parseKml($tempPath);
                    elseif ($extension === 'csv') $points = parseCsv($tempPath);
                    elseif ($extension === 'html') $points = parseHtml($tempPath);
                    elseif ($extension === 'gpx') $points = parseGpx($tempPath);
                    if (empty($points)) continue;
                    $processedCount++;
                    $filename = $name . '_' . $processedCount . '.csv';
                    generateCsvFromPoints($points, $filename);
                    saveRoute(uniqid(), $name . ' ' . $processedCount, $filename, $points);
                }
                header("Location: ?lang=$lang&theme=$theme&color=" . urlencode($color) . "&view_mode=$view_mode&display_mode=$display_mode&processed=$processedCount");
                exit;
            }
            break;
        case 'rename_route':
            echo json_encode(['success' => renameRoute($_POST['id'], $_POST['name'])]);
            exit;
        case 'delete_route':
            echo json_encode(['success' => deleteRoute($_POST['id'])]);
            exit;
        case 'toggle_favorite':
            $id = $_POST['id'];
            $favorites = getFavorites();
            if (in_array($id, $favorites)) $favorites = array_diff($favorites, [$id]); else $favorites[] = $id;
            saveFavorites(array_values($favorites));
            echo json_encode(['success' => true]);
            exit;
    }
}
function handleRouteAjaxActions(): void {
    if (!isset($_GET['ajax'])) return;
    header('Content-Type: application/json');
    if ($_GET['ajax'] === 'rename' && isset($_POST['id'], $_POST['name'])) {
        echo json_encode(['success' => renameRoute($_POST['id'], $_POST['name'])]);
        exit;
    }
    if ($_GET['ajax'] === 'delete' && isset($_POST['id'])) {
        echo json_encode(['success' => deleteRoute($_POST['id'])]);
        exit;
    }
    if ($_GET['ajax'] === 'toggle_favorite' && isset($_POST['id'])) {
        $id = $_POST['id'];
        $favorites = getFavorites();
        if (in_array($id, $favorites)) $favorites = array_diff($favorites, [$id]); else $favorites[] = $id;
        saveFavorites(array_values($favorites));
        echo json_encode(['success' => true]);
        exit;
    }
    if ($_GET['ajax'] === 'download_selected' && isset($_POST['ids'], $_POST['format'])) {
        $ids = json_decode($_POST['ids'], true);
        $format = $_POST['format'];
        $routes = getRoutes();
        $selectedRoutes = array_filter($routes, function($route) use ($ids) { return in_array($route['id'], $ids); });
        if ($format === 'csv') {
            $content = "ID,Name,Points,Size,Date\n";
            foreach ($selectedRoutes as $route) $content .= $route['id'] . ',' . $route['name'] . ',' . count($route['points']) . ',' . $route['size'] . ',' . date('Y-m-d H:i', $route['date']) . "\n";
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="selected_routes.csv"');
            echo $content;
            exit;
        }
        if ($format === 'json') {
            $content = json_encode(array_values($selectedRoutes), JSON_PRETTY_PRINT);
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="selected_routes.json"');
            echo $content;
            exit;
        }
    }
}
function resolveRouteContent($action, $display_mode, $pagination, $id) {
    switch ($action) {
        case 'add': return renderAddForm();
        case 'add_folder': return renderAddFolderForm();
        case 'view': return renderViewRoute($id);
        case 'view_all_map': return renderAllRoutesMap();
        case 'api_docs': return renderApiDocs();
        default: return $display_mode === 'table' ? renderTablePage($pagination) : renderMainPage($pagination);
    }
}
