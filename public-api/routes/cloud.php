<?php
$parts = public_api_parse_path();
if (!isset($_GET['action']) && isset($parts[1])) {
    $_GET['action'] = $parts[1];
}
require APP_ROOT . '/apps/cloud/api.php';
