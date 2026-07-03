<?php
require_once __DIR__ . '/../../../lib/app_db.php';
function getDB(): ?AppDatabase {
    try {
        return app_main_db();
    } catch (Throwable $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        return null;
    }
}
