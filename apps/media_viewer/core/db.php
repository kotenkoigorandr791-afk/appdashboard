<?php
require_once __DIR__ . '/../../../lib/app_db.php';
function getDB(): ?AppDatabase {
    static $db = null;
    if ($db === null) {
        try {
            $db = app_main_db();
            initGalleryTables($db);
        } catch (Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            return null;
        }
    }
    return $db;
}

function initGalleryTables(AppDatabase $db): void {
    try {
        $db->exec('
            CREATE TABLE IF NOT EXISTS gallery_download_notification (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id TEXT NOT NULL,
                dismissed INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_gallery_notif_session ON gallery_download_notification(session_id)');
        $db->exec('
            CREATE TABLE IF NOT EXISTS gallery_albums (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                url TEXT NOT NULL,
                preview TEXT NOT NULL DEFAULT "",
                min_role INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');
        $db->exec('
            CREATE TABLE IF NOT EXISTS gallery_hidden_albums (
                album_id TEXT PRIMARY KEY,
                hidden INTEGER NOT NULL DEFAULT 1,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (album_id) REFERENCES gallery_albums(id) ON DELETE CASCADE
            )
        ');
        $db->exec('
            CREATE TABLE IF NOT EXISTS gallery_album_stats (
                album_id TEXT PRIMARY KEY,
                views INTEGER NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (album_id) REFERENCES gallery_albums(id) ON DELETE CASCADE
            )
        ');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_gallery_albums_created ON gallery_albums(created_at)');
    } catch (Exception $e) {
        error_log("Failed to init gallery tables: " . $e->getMessage());
    }
}

function getGalleryAlbums(): array {
    $db = getDB();
    if (!$db) return [];

    try {
        $result = $db->query('
            SELECT a.id, a.name, a.url, a.preview, a.min_role, a.created_at, COALESCE(s.views, 0) AS views
            FROM gallery_albums a
            LEFT JOIN gallery_album_stats s ON s.album_id = a.id
            ORDER BY a.created_at ASC, a.id ASC
        ');
        $albums = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $albums[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'url' => $row['url'],
                'preview' => $row['preview'],
                'created' => $row['created_at'],
                'min_role' => (int) $row['min_role'],
            ];
        }
        return $albums;
    } catch (Exception $e) {
        error_log("Failed to load gallery albums: " . $e->getMessage());
        return [];
    }
}

function getGalleryHiddenAlbums(): array {
    $db = getDB();
    if (!$db) return [];

    try {
        $result = $db->query('SELECT album_id FROM gallery_hidden_albums WHERE hidden = 1');
        $hidden = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $hidden[$row['album_id']] = true;
        }
        return $hidden;
    } catch (Exception $e) {
        error_log("Failed to load hidden gallery albums: " . $e->getMessage());
        return [];
    }
}

function getGalleryStats(): array {
    $db = getDB();
    if (!$db) return [];

    try {
        $result = $db->query('
            SELECT a.id AS album_id, COALESCE(s.views, 0) AS views
            FROM gallery_albums a
            LEFT JOIN gallery_album_stats s ON s.album_id = a.id
        ');
        $stats = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $stats[$row['album_id']] = (int) $row['views'];
        }
        return $stats;
    } catch (Exception $e) {
        error_log("Failed to load gallery stats: " . $e->getMessage());
        return [];
    }
}

function addGalleryAlbum(array $album): bool {
    $db = getDB();
    if (!$db) return false;

    try {
        $stmt = $db->prepare('
            INSERT INTO gallery_albums (id, name, url, preview, min_role, created_at)
            VALUES (:id, :name, :url, :preview, :min_role, :created_at)
        ');
        $stmt->bindValue(':id', $album['id'], SQLITE3_TEXT);
        $stmt->bindValue(':name', $album['name'], SQLITE3_TEXT);
        $stmt->bindValue(':url', $album['url'], SQLITE3_TEXT);
        $stmt->bindValue(':preview', $album['preview'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':min_role', (int) ($album['min_role'] ?? 0), SQLITE3_INTEGER);
        $stmt->bindValue(':created_at', $album['created'] ?? date('Y-m-d H:i:s'), SQLITE3_TEXT);
        $stmt->execute();
        return true;
    } catch (Exception $e) {
        error_log("Failed to add gallery album: " . $e->getMessage());
        return false;
    }
}

function getGalleryAlbumById(string $albumId): ?array {
    $db = getDB();
    if (!$db) return null;

    try {
        $stmt = $db->prepare('SELECT id, name, url, preview, min_role, created_at FROM gallery_albums WHERE id = :id');
        $stmt->bindValue(':id', $albumId, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$row) return null;
        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'url' => $row['url'],
            'preview' => $row['preview'],
            'created' => $row['created_at'],
            'min_role' => (int) $row['min_role'],
        ];
    } catch (Exception $e) {
        error_log("Failed to load gallery album: " . $e->getMessage());
        return null;
    }
}

function updateGalleryAlbum(array $album): bool {
    $db = getDB();
    if (!$db || empty($album['id'])) return false;

    try {
        $stmt = $db->prepare('
            UPDATE gallery_albums
            SET name = :name,
                url = :url,
                preview = :preview,
                min_role = :min_role,
                created_at = :created_at
            WHERE id = :id
        ');
        $stmt->bindValue(':id', $album['id'], SQLITE3_TEXT);
        $stmt->bindValue(':name', $album['name'], SQLITE3_TEXT);
        $stmt->bindValue(':url', $album['url'], SQLITE3_TEXT);
        $stmt->bindValue(':preview', $album['preview'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':min_role', (int) ($album['min_role'] ?? 0), SQLITE3_INTEGER);
        $stmt->bindValue(':created_at', $album['created'] ?? date('Y-m-d H:i:s'), SQLITE3_TEXT);
        $stmt->execute();
        return $db->changes() > 0;
    } catch (Exception $e) {
        error_log("Failed to update gallery album: " . $e->getMessage());
        return false;
    }
}

function deleteGalleryAlbum(string $albumId): ?array {
    $db = getDB();
    if (!$db) return null;

    $album = getGalleryAlbumById($albumId);
    if (!$album) return null;

    try {
        $db->exec('BEGIN');
        $stmt = $db->prepare('DELETE FROM gallery_album_stats WHERE album_id = :id');
        $stmt->bindValue(':id', $albumId, SQLITE3_TEXT);
        $stmt->execute();
        $stmt = $db->prepare('DELETE FROM gallery_hidden_albums WHERE album_id = :id');
        $stmt->bindValue(':id', $albumId, SQLITE3_TEXT);
        $stmt->execute();
        $stmt = $db->prepare('DELETE FROM gallery_albums WHERE id = :id');
        $stmt->bindValue(':id', $albumId, SQLITE3_TEXT);
        $stmt->execute();
        $db->exec('COMMIT');
        return $album;
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        error_log("Failed to delete gallery album: " . $e->getMessage());
        return null;
    }
}

function toggleGalleryAlbumHidden(string $albumId): void {
    $db = getDB();
    if (!$db) return;

    try {
        $stmt = $db->prepare('SELECT album_id FROM gallery_hidden_albums WHERE album_id = :id AND hidden = 1');
        $stmt->bindValue(':id', $albumId, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($row) {
            $stmt = $db->prepare('DELETE FROM gallery_hidden_albums WHERE album_id = :id');
        } else {
            $stmt = $db->prepare('
                INSERT INTO gallery_hidden_albums (album_id, hidden, updated_at)
                VALUES (:id, 1, CURRENT_TIMESTAMP)
                ON CONFLICT(album_id) DO UPDATE SET hidden = 1, updated_at = CURRENT_TIMESTAMP
            ');
        }
        $stmt->bindValue(':id', $albumId, SQLITE3_TEXT);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to toggle hidden gallery album: " . $e->getMessage());
    }
}

function incrementGalleryAlbumViews(string $albumId): int {
    $db = getDB();
    if (!$db) return 0;

    try {
        $stmt = $db->prepare('
            INSERT INTO gallery_album_stats (album_id, views, updated_at)
            VALUES (:id, 1, CURRENT_TIMESTAMP)
            ON CONFLICT(album_id) DO UPDATE SET views = views + 1, updated_at = CURRENT_TIMESTAMP
        ');
        $stmt->bindValue(':id', $albumId, SQLITE3_TEXT);
        $stmt->execute();

        $stmt = $db->prepare('SELECT views FROM gallery_album_stats WHERE album_id = :id');
        $stmt->bindValue(':id', $albumId, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        return $row ? (int) $row['views'] : 0;
    } catch (Exception $e) {
        error_log("Failed to increment gallery album views: " . $e->getMessage());
        return 0;
    }
}

function clearGalleryStats(): bool {
    $db = getDB();
    if (!$db) return false;

    try {
        $db->exec('DELETE FROM gallery_album_stats');
        return true;
    } catch (Exception $e) {
        error_log("Failed to clear gallery stats: " . $e->getMessage());
        return false;
    }
}

function isNotificationDismissed(string $sessionId): bool {
    $db = getDB();
    if (!$db || !$sessionId) return false;
    try {
        $stmt = $db->prepare('SELECT dismissed FROM gallery_download_notification WHERE session_id = :sid ORDER BY id DESC LIMIT 1');
        $stmt->bindValue(':sid', $sessionId, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        return $row && (int)$row['dismissed'] === 1;
    } catch (Exception $e) {
        error_log("Failed to check notification dismissal: " . $e->getMessage());
        return false;
    }
}

function dismissNotification(string $sessionId): void {
    $db = getDB();
    if (!$db || !$sessionId) return;
    try {
        $stmt = $db->prepare('INSERT INTO gallery_download_notification (session_id, dismissed) VALUES (:sid, 1)');
        $stmt->bindValue(':sid', $sessionId, SQLITE3_TEXT);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to dismiss notification: " . $e->getMessage());
    }
}
