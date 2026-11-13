<?php
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
try {
    steps_geolocation_migrate(app_main_db());
    fwrite(STDOUT, "geolocation migration complete\n");
} catch (Throwable $e) {
    fwrite(STDERR, "geolocation migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
