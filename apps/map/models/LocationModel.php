<?php
function initLocationSharingDB() {
    try {
        $db = getDB();
        if (!$db) {
            return null;
        }

        // Create tokens table with map_ prefix
        $db->exec("
            CREATE TABLE IF NOT EXISTS map_location_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token TEXT UNIQUE NOT NULL,
                user_email TEXT UNIQUE NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                is_active INTEGER DEFAULT 1
            )
        ");
        
        // Create locations table with map_ prefix
        $db->exec("
            CREATE TABLE IF NOT EXISTS map_location_data (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token TEXT NOT NULL,
                latitude REAL NOT NULL,
                longitude REAL NOT NULL,
                accuracy REAL,
                message TEXT,
                photo_path TEXT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (token) REFERENCES map_location_tokens(token)
            )
        ");

        ensureLocationTelemetrySchema($db);
        
        return $db;
    } catch (Exception $e) {
        error_log("Location sharing DB initialization failed: " . $e->getMessage());
        return null;
    }
}

function locationColumnExists(AppDatabase $db, string $table, string $column): bool {
    $result = $db->query("PRAGMA table_info($table)");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if (($row['name'] ?? '') === $column) {
            return true;
        }
    }
    return false;
}

function ensureLocationTelemetrySchema(AppDatabase $db): void {
    $columns = [
        'device_name' => 'TEXT',
        'device_type' => 'TEXT',
        'speed' => 'REAL',
        'altitude' => 'REAL',
        'satellites' => 'INTEGER',
        'telemetry_json' => 'TEXT',
    ];

    foreach ($columns as $name => $type) {
        if (!locationColumnExists($db, 'map_location_data', $name)) {
            $db->exec("ALTER TABLE map_location_data ADD COLUMN $name $type");
        }
    }
}

function pruneLocationHistory(AppDatabase $db, string $token, int $keep = 50): void {
    $keep = max(1, $keep);
    $userEmailStmt = $db->prepare("SELECT user_email FROM map_location_tokens WHERE token = :token LIMIT 1");
    $userEmailStmt->bindValue(':token', $token, SQLITE3_TEXT);
    $userEmailResult = $userEmailStmt->execute();
    $userRow = $userEmailResult ? $userEmailResult->fetchArray(SQLITE3_ASSOC) : false;
    $userEmail = $userRow['user_email'] ?? null;

    if (!$userEmail) {
        return;
    }

    $deleteStmt = $db->prepare("
        DELETE FROM map_location_data
        WHERE token IN (
            SELECT token FROM map_location_tokens WHERE user_email = :email
        )
        AND id NOT IN (
            SELECT id FROM map_location_data
            WHERE token IN (
                SELECT token FROM map_location_tokens WHERE user_email = :email_keep
            )
            ORDER BY timestamp DESC, id DESC
            LIMIT :keep
        )
    ");
    $deleteStmt->bindValue(':email', $userEmail, SQLITE3_TEXT);
    $deleteStmt->bindValue(':email_keep', $userEmail, SQLITE3_TEXT);
    $deleteStmt->bindValue(':keep', $keep, SQLITE3_INTEGER);
    $deleteStmt->execute();
}

function getLocationApiPayload(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
    }
    return [];
}

function getLocationField(array $payload, string $field) {
    if (array_key_exists($field, $payload)) {
        return $payload[$field];
    }
    if (isset($_POST[$field])) {
        return $_POST[$field];
    }
    if (isset($_GET[$field])) {
        return $_GET[$field];
    }
    return null;
}

// Initialize DB on page load and get existing token
initLocationSharingDB();
$existingToken = null;
$userEmail = getCurrentUserEmail();
if ($userEmail) {
    try {
        $locDb = initLocationSharingDB();
        if ($locDb) {
            $stmt = $locDb->prepare("SELECT token, expires_at FROM map_location_tokens WHERE user_email = :email AND is_active = 1 AND expires_at > CURRENT_TIMESTAMP ORDER BY created_at DESC LIMIT 1");
            $stmt->bindValue(':email', $userEmail, SQLITE3_TEXT);
            $result = $stmt->execute();
            $existingToken = $result->fetchArray(SQLITE3_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Existing token lookup failed: " . $e->getMessage());
    }
}

// Handle API requests for location sharing
