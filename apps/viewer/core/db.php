<?php
require_once __DIR__ . '/../../../lib/app_db.php';
function ensureViewerSchema(AppDatabase $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS viewer_meta (
        id INTEGER PRIMARY KEY CHECK (id = 1),
        theme TEXT NOT NULL DEFAULT \'light\',
        language TEXT NOT NULL DEFAULT \'en\',
        tile_size INTEGER NOT NULL DEFAULT 50,
        sidebar_width INTEGER NOT NULL DEFAULT 300,
        header_color TEXT NOT NULL DEFAULT \'#34a853\',
        tiles_per_row INTEGER NOT NULL DEFAULT 20,
        token TEXT NOT NULL DEFAULT \'\'
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS viewer_sites (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sort_order INTEGER NOT NULL DEFAULT 0,
        name TEXT NOT NULL,
        url TEXT NOT NULL,
        priority INTEGER NOT NULL DEFAULT 0,
        hidden INTEGER NOT NULL DEFAULT 0,
        use_token INTEGER NOT NULL DEFAULT 0
    )');
    $db->exec('INSERT OR IGNORE INTO viewer_meta (id) VALUES (1)');
}
function getDB(): ?AppDatabase {
    static $db = null;
    if ($db === null) {
        try {
            $db = app_main_db();
            ensureViewerSchema($db);
        } catch (Throwable $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            return null;
        }
    }
    return $db;
}
