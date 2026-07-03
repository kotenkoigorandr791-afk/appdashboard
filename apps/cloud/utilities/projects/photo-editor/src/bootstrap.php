<?php

declare(strict_types=1);

cloud_projects_bootstrap();
require_once is_file(__DIR__ . '/controller.php') ? __DIR__ . '/controller.php' : __DIR__ . '/legacy.php';
