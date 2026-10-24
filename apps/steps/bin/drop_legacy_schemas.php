<?php
if (PHP_SAPI !== 'cli') exit(1);
define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/lib/app_db.php';
require_once APP_ROOT . '/apps/steps/core/store.php';
$apply = in_array('--apply', $argv, true);
$db = app_main_db();
steps_store_ensure($db);
$schemas = [];
$res = $db->query("SELECT nspname FROM pg_namespace WHERE nspname LIKE 'steps\\_%'");
while ($r = $res->fetch(PDO::FETCH_NUM)) $schemas[$r[0]] = true;
$drop = [];
$res = $db->query('SELECT user_email FROM steps_users');
while ($r = $res->fetch(PDO::FETCH_NUM)) {
    $id = app_user_schema_id('steps', $r[0]);
    if (isset($schemas[$id])) $drop[] = $id;
}
foreach (array_keys($schemas) as $s) {
    if (str_starts_with($s, 'steps_folder_user_')) $drop[] = $s;
}
$drop = array_unique($drop);
echo ($apply ? 'dropping ' : 'would drop ') . count($drop) . " schemas\n";
if (!$apply) exit(0);
foreach ($drop as $s) {
    $db->exec('DROP SCHEMA IF EXISTS ' . app_user_schema_sql($s) . ' CASCADE');
}
