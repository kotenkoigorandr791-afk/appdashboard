<?php
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_render.php';
$pdo = app_main_db()->getPdo();
steps_geolocation_migrate(app_main_db());
StepsGeolocationRender::cleanup($pdo);
$deadline = time() + 150;
while (time() < $deadline) {
    $job = StepsGeolocationRender::claim($pdo);
    if (!$job) break;
    StepsGeolocationRender::runJob($pdo, $job);
}
