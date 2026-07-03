<?php
function getFavorites() {
    global $FAVORITES_FILE;
    if (file_exists($FAVORITES_FILE)) {
        return json_decode(file_get_contents($FAVORITES_FILE), true) ?: [];
    }
    return [];
}

function saveFavorites($favorites) {
    global $FAVORITES_FILE;
    file_put_contents($FAVORITES_FILE, json_encode($favorites));
}

function getRoutes() {
    global $ROUTES_FILE, $UPLOAD_DIR;
    $routes = [];
    if (file_exists($ROUTES_FILE)) {
        $file = fopen($ROUTES_FILE, 'r');
        while (($row = fgetcsv($file)) !== FALSE) {
            if (count($row) >= 4) {
                $filePath = $UPLOAD_DIR . $row[2];
                $fileSize = file_exists($filePath) ? filesize($filePath) : 0;
                $fileDate = file_exists($filePath) ? filemtime($filePath) : time();
                
                $routes[] = [
                    'id' => $row[0],
                    'name' => $row[1],
                    'filename' => $row[2],
                    'points' => json_decode($row[3], true),
                    'size' => $fileSize,
                    'date' => $fileDate
                ];
            }
        }
        fclose($file);
    }
    return $routes;
}

function getPaginatedRoutes($routes, $page, $perPage, $view_mode) {
    if ($view_mode === 'favorites') {
        $favorites = getFavorites();
        $routes = array_filter($routes, function($route) use ($favorites) {
            return in_array($route['id'], $favorites);
        });
    }
    
    $total = count($routes);
    $totalPages = ceil($total / $perPage);
    $offset = ($page - 1) * $perPage;
    $paginatedRoutes = array_slice($routes, $offset, $perPage);
    
    return [
        'routes' => $paginatedRoutes,
        'total' => $total,
        'total_pages' => $totalPages,
        'current_page' => $page,
        'per_page' => $perPage
    ];
}

function saveRoute($id, $name, $filename, $points) {
    global $ROUTES_FILE;
    $routeData = [$id, $name, $filename, json_encode($points)];
    $file = fopen($ROUTES_FILE, 'a');
    fputcsv($file, $routeData);
    fclose($file);
}

function deleteRoute($id) {
    global $ROUTES_FILE, $UPLOAD_DIR, $FAVORITES_FILE;
    $routes = [];
    $deleted = false;
    
    if (file_exists($ROUTES_FILE)) {
        $file = fopen($ROUTES_FILE, 'r');
        while (($row = fgetcsv($file)) !== FALSE) {
            if ($row[0] === $id) {
                if (file_exists($UPLOAD_DIR . $row[2])) {
                    unlink($UPLOAD_DIR . $row[2]);
                }
                $deleted = true;
            } else {
                $routes[] = $row;
            }
        }
        fclose($file);
    }
    
    if ($deleted) {
        $file = fopen($ROUTES_FILE, 'w');
        foreach ($routes as $route) {
            fputcsv($file, $route);
        }
        fclose($file);
        
        $favorites = getFavorites();
        if (($key = array_search($id, $favorites)) !== false) {
            unset($favorites[$key]);
            saveFavorites(array_values($favorites));
        }
    }
    
    return $deleted;
}

function renameRoute($id, $newName) {
    global $ROUTES_FILE;
    $routes = [];
    $renamed = false;
    
    if (file_exists($ROUTES_FILE)) {
        $file = fopen($ROUTES_FILE, 'r');
        while (($row = fgetcsv($file)) !== FALSE) {
            if ($row[0] === $id) {
                $row[1] = $newName;
                $renamed = true;
            }
            $routes[] = $row;
        }
        fclose($file);
    }
    
    if ($renamed) {
        $file = fopen($ROUTES_FILE, 'w');
        foreach ($routes as $route) {
            fputcsv($file, $route);
        }
        fclose($file);
    }
    
    return $renamed;
}

function parseKml($filePath) {
    $points = [];
    $kml = simplexml_load_file($filePath);
    
    if ($kml && isset($kml->Document->Placemark->LineString->coordinates)) {
        $coordinates = (string)$kml->Document->Placemark->LineString->coordinates;
        $coordsArray = explode(' ', $coordinates);
        
        foreach ($coordsArray as $coord) {
            $coord = trim($coord);
            if (!empty($coord)) {
                $parts = explode(',', $coord);
                if (count($parts) >= 2) {
                    $points[] = [(float)$parts[1], (float)$parts[0]];
                }
            }
        }
    }
    
    return $points;
}

function parseCsv($filePath) {
    $points = [];
    $csv = fopen($filePath, 'r');
    while (($row = fgetcsv($csv)) !== FALSE) {
        if (count($row) >= 2) {
            $points[] = [(float)$row[0], (float)$row[1]];
        }
    }
    fclose($csv);
    return $points;
}

function parseHtml($filePath) {
    $points = [];
    $content = file_get_contents($filePath);
    
    if (preg_match('/const points = (\[\[.*?\]\])/s', $content, $matches)) {
        $pointsArray = $matches[1];
        $pointsArray = preg_replace('/\s+/', '', $pointsArray);
        
        if (preg_match_all('/\[([\d\.]+),([\d\.]+)\]/', $pointsArray, $coordMatches)) {
            for ($i = 0; $i < count($coordMatches[1]); $i++) {
                $lat = (float)$coordMatches[1][$i];
                $lng = (float)$coordMatches[2][$i];
                $points[] = [$lat, $lng];
            }
        }
    }
    
    return $points;
}

function parseGpx($filePath) {
    $points = [];
    $gpx = simplexml_load_file($filePath);
    
    if ($gpx && isset($gpx->trk->trkseg)) {
        foreach ($gpx->trk->trkseg->trkpt as $pt) {
            $lat = (float)$pt['lat'];
            $lon = (float)$pt['lon'];
            $points[] = [$lat, $lon];
        }
    }
    
    return $points;
}

function generateCsvFromPoints($points, $filename) {
    global $UPLOAD_DIR;
    $csvPath = $UPLOAD_DIR . $filename;
    $file = fopen($csvPath, 'w');
    foreach ($points as $point) {
        fputcsv($file, $point);
    }
    fclose($file);
    return $csvPath;
}

// New download functions

function generateKmlFromPoints($points, $routeName) {
    $kml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $kml .= '<kml xmlns="http://www.opengis.net/kml/2.2">' . "\n";
    $kml .= '  <Document>' . "\n";
    $kml .= '    <name>' . htmlspecialchars($routeName) . '</name>' . "\n";
    $kml .= '    <Placemark>' . "\n";
    $kml .= '      <name>' . htmlspecialchars($routeName) . '</name>' . "\n";
    $kml .= '      <LineString>' . "\n";
    $kml .= '        <coordinates>' . "\n";
    
    foreach ($points as $point) {
        $kml .= '          ' . $point[1] . ',' . $point[0] . ',0' . "\n";
    }
    
    $kml .= '        </coordinates>' . "\n";
    $kml .= '      </LineString>' . "\n";
    $kml .= '    </Placemark>' . "\n";
    $kml .= '  </Document>' . "\n";
    $kml .= '</kml>';
    
    return $kml;
}

function generateGpxFromPoints($points, $routeName) {
    $gpx = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $gpx .= '<gpx version="1.1" creator="Routes Manager" xmlns="http://www.topografix.com/GPX/1/1">' . "\n";
    $gpx .= '  <metadata>' . "\n";
    $gpx .= '    <name>' . htmlspecialchars($routeName) . '</name>' . "\n";
    $gpx .= '  </metadata>' . "\n";
    $gpx .= '  <trk>' . "\n";
    $gpx .= '    <name>' . htmlspecialchars($routeName) . '</name>' . "\n";
    $gpx .= '    <trkseg>' . "\n";
    
    foreach ($points as $point) {
        $gpx .= '      <trkpt lat="' . $point[0] . '" lon="' . $point[1] . '"></trkpt>' . "\n";
    }
    
    $gpx .= '    </trkseg>' . "\n";
    $gpx .= '  </trk>' . "\n";
    $gpx .= '</gpx>';
    
    return $gpx;
}

function generateJsonFromPoints($points, $routeName) {
    $data = [
        'name' => $routeName,
        'points' => $points,
        'count' => count($points),
        'generated' => date('Y-m-d H:i:s')
    ];
    return json_encode($data, JSON_PRETTY_PRINT);
}

function downloadRoute($id, $format) {
    $routes = getRoutes(); // Get fresh routes data
    
    $route = null;
    foreach ($routes as $r) {
        if ($r['id'] === $id) {
            $route = $r;
            break;
        }
    }
    
    if (!$route) {
        return false;
    }
    
    $filename = preg_replace('/[^a-zA-Z0-9]/', '_', $route['name']) . '.' . $format;
    $content = '';
    $contentType = '';
    
    switch ($format) {
        case 'kml':
            $content = generateKmlFromPoints($route['points'], $route['name']);
            $contentType = 'application/vnd.google-earth.kml+xml';
            break;
        case 'gpx':
            $content = generateGpxFromPoints($route['points'], $route['name']);
            $contentType = 'application/gpx+xml';
            break;
        case 'csv':
            $content = '';
            foreach ($route['points'] as $point) {
                $content .= $point[0] . ',' . $point[1] . "\n";
            }
            $contentType = 'text/csv';
            break;
        case 'json':
            $content = generateJsonFromPoints($route['points'], $route['name']);
            $contentType = 'application/json';
            break;
        default:
            return false;
    }
    
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit;
}

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    $sizes = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $sizes[$i];
}

function formatDate($timestamp) {
    return date('Y-m-d H:i', $timestamp);
}
