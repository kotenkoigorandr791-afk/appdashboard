<?php
$_GET['api'] = $_GET['api'] ?? ($parts[1] ?? 'state');
require_once APP_ROOT . '/apps/smart_home/core/app.php';
