<?php
require_once __DIR__ . '/../../../lib/app_db.php';
// The monitor endpoints may be included after the dashboard bootstrap has
// already loaded the root db.php (which declares getDB()). Guard the
// declaration so both entry points can coexist in one request.
if (!function_exists('getDB')) {
    function getDB(): ?AppDatabase {
        try {
            return app_main_db();
        } catch (Throwable $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            return null;
        }
    }
}
