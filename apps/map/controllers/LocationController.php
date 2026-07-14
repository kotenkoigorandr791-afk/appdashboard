<?php
function handleLocationAPI() {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];

    // Check if this is a location API request
    if (strpos($uri, '/map/api/') === false) {
        return false;
    }

    // Set JSON content type for all API responses
    header('Content-Type: application/json');

    $db = initLocationSharingDB();
    if (!$db) {
        http_response_code(500);
        echo json_encode(['error' => 'Database initialization failed']);
        return true;
    }

    // Generate token - requires authentication (one token per user)
    if ($method === 'POST' && preg_match('#/map/api/token$#', $uri)) {
        $email = getCurrentUserEmail();
        if (!$email) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            return true;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+365 days'));

        // Use INSERT OR REPLACE to ensure only one token per user
        $stmt = $db->prepare("
            INSERT OR REPLACE INTO map_location_tokens (token, user_email, expires_at, is_active)
            VALUES (:token, :email, :expires, 1)
        ");
        $stmt->bindValue(':token', $token, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':expires', $expiresAt, SQLITE3_TEXT);
        try {
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Token generation failed: " . $e->getMessage());
            http_response_code(stripos($e->getMessage(), 'locked') !== false ? 503 : 500);
            echo json_encode(['error' => 'Token generation failed', 'details' => $e->getMessage()]);
            return true;
        }
        
        echo json_encode([
            'success' => true,
            'token' => $token,
            'expires_at' => $expiresAt
        ]);
        return true;
    }
    
    // Get user's token - requires authentication
    if ($method === 'GET' && preg_match('#/map/api/tokens$#', $uri)) {
        $email = getCurrentUserEmail();
        if (!$email) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            return true;
        }

        $stmt = $db->prepare("SELECT token, created_at, expires_at, is_active FROM map_location_tokens WHERE user_email = :email ORDER BY created_at DESC LIMIT 1");
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        try {
            $result = $stmt->execute();
        } catch (Exception $e) {
            error_log("Token fetch failed: " . $e->getMessage());
            http_response_code(stripos($e->getMessage(), 'locked') !== false ? 503 : 500);
            echo json_encode(['error' => 'Token fetch failed', 'details' => $e->getMessage()]);
            return true;
        }

        $tokens = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $tokens[] = $row;
        }
        
        echo json_encode(['success' => true, 'tokens' => $tokens]);
        return true;
    }
    
    // Receive location data - uses token authentication
    if ($method === 'POST' && preg_match('#/map/api/location$#', $uri)) {
        $payload = getLocationApiPayload();
        $token = getLocationField($payload, 'token');
        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'Token required']);
            return true;
        }
        
        // Validate token
        $stmt = $db->prepare("SELECT id,user_email FROM map_location_tokens WHERE token = :token AND is_active = 1 AND expires_at > CURRENT_TIMESTAMP");
        $stmt->bindValue(':token', $token, SQLITE3_TEXT);
        $result = $stmt->execute();
        $tokenRow = $result->fetchArray(SQLITE3_ASSOC);
        if (!$tokenRow) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired token']);
            return true;
        }
        
        // Get location data
        $latitude = getLocationField($payload, 'latitude');
        $longitude = getLocationField($payload, 'longitude');
        $accuracy = getLocationField($payload, 'accuracy');
        $message = getLocationField($payload, 'message');
        $deviceName = getLocationField($payload, 'device_name');
        $deviceType = getLocationField($payload, 'device_type');
        $speed = getLocationField($payload, 'speed');
        $altitude = getLocationField($payload, 'altitude');
        $satellites = getLocationField($payload, 'satellites');
        $telemetry = getLocationField($payload, 'telemetry');
        
        if ($latitude === null || $longitude === null || $latitude === '' || $longitude === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Latitude and longitude required']);
            return true;
        }

        $telemetryJson = null;
        if (is_array($telemetry)) {
            $telemetryJson = json_encode($telemetry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif (is_string($telemetry) && $telemetry !== '') {
            $telemetryJson = $telemetry;
        }
        
        $photoPath = null;
        // Handle photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/location_photos/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $photoExt = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($photoExt, $allowedExts)) {
                $photoFilename = uniqid('photo_') . '.' . $photoExt;
                $photoPath = 'location_photos/' . $photoFilename;
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $photoFilename)) {
                    $photoPath = $photoPath;
                }
            }
        }
        
        // Insert location
        $stmt = $db->prepare("
            INSERT INTO map_location_data (
                token, latitude, longitude, accuracy, message, photo_path,
                device_name, device_type, speed, altitude, satellites, telemetry_json
            )
            VALUES (
                :token, :lat, :lon, :accuracy, :message, :photo,
                :device_name, :device_type, :speed, :altitude, :satellites, :telemetry_json
            )
        ");
        $stmt->bindValue(':token', $token, SQLITE3_TEXT);
        $stmt->bindValue(':lat', (float)$latitude, SQLITE3_FLOAT);
        $stmt->bindValue(':lon', (float)$longitude, SQLITE3_FLOAT);
        $stmt->bindValue(':accuracy', ($accuracy !== null && $accuracy !== '') ? (float)$accuracy : null, SQLITE3_FLOAT);
        $stmt->bindValue(':message', $message, SQLITE3_TEXT);
        $stmt->bindValue(':photo', $photoPath, SQLITE3_TEXT);
        $stmt->bindValue(':device_name', $deviceName, SQLITE3_TEXT);
        $stmt->bindValue(':device_type', $deviceType, SQLITE3_TEXT);
        $stmt->bindValue(':speed', ($speed !== null && $speed !== '') ? (float)$speed : null, SQLITE3_FLOAT);
        $stmt->bindValue(':altitude', ($altitude !== null && $altitude !== '') ? (float)$altitude : null, SQLITE3_FLOAT);
        $stmt->bindValue(':satellites', ($satellites !== null && $satellites !== '') ? (int)$satellites : null, SQLITE3_INTEGER);
        $stmt->bindValue(':telemetry_json', $telemetryJson, SQLITE3_TEXT);
        $stmt->execute();
        $insertId = $db->lastInsertRowID();
        pruneLocationHistory($db, $token, 50);
        app_metric((string)($tokenRow['user_email'] ?? ''), 'map', 'location_update', 1, 'point', ['device_name' => (string)$deviceName, 'has_photo' => $photoPath !== null]);
        
        // Return full photo path URL if photo was uploaded
        $responsePhotoPath = $photoPath ? '/apps/map/' . $photoPath : null;
        
        echo json_encode(['success' => true, 'id' => $insertId, 'photo_path' => $responsePhotoPath]);
        return true;
    }
    
    // Get location data - uses token authentication
    if ($method === 'GET' && preg_match('#/map/api/location$#', $uri)) {
        $token = $_GET['token'] ?? null;
        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'Token required']);
            return true;
        }
        
        // Validate token
        $stmt = $db->prepare("SELECT id FROM map_location_tokens WHERE token = :token AND is_active = 1 AND expires_at > CURRENT_TIMESTAMP");
        $stmt->bindValue(':token', $token, SQLITE3_TEXT);
        $result = $stmt->execute();
        $isValidToken = (bool)$result->fetchArray();
        if (!$isValidToken) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired token']);
            return true;
        }
        
        // Get latest location
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 1;
        $limit = max(1, min(50, $limit));
        $stmt = $db->prepare("
            SELECT latitude, longitude, accuracy, message, photo_path, timestamp,
                   device_name, device_type, speed, altitude, satellites, telemetry_json
            FROM map_location_data
            WHERE token = :token
            ORDER BY timestamp DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':token', $token, SQLITE3_TEXT);
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        try {
            $result = $stmt->execute();
        } catch (Throwable $e) {
            error_log('Location fetch failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Location fetch failed', 'details' => $e->getMessage()]);
            return true;
        }
        
        $locations = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if (!empty($row['photo_path']) && strpos($row['photo_path'], '/apps/map/') !== 0) {
                $row['photo_path'] = '/apps/map/' . ltrim($row['photo_path'], '/');
            }
            if (!empty($row['telemetry_json'])) {
                $decoded = json_decode($row['telemetry_json'], true);
                if (is_array($decoded)) {
                    $row['telemetry'] = $decoded;
                }
            }
            $locations[] = $row;
        }
        
        echo json_encode(['success' => true, 'locations' => $locations]);
        return true;
    }
    
    // Delete token - requires authentication
    if ($method === 'DELETE' && preg_match('#/map/api/token/([^/]+)$#', $uri, $matches)) {
        $email = getCurrentUserEmail();
        if (!$email) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            return true;
        }
        
        $tokenToDelete = $matches[1];
        $stmt = $db->prepare("DELETE FROM map_location_tokens WHERE token = :token AND user_email = :email");
        $stmt->bindValue(':token', $tokenToDelete, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        return true;
    }
    
    return false;
}
