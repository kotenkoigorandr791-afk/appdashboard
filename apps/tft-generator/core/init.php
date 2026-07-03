<?php
$url_theme = isset($_GET['theme']) ? $_GET['theme'] : null;
$url_lang = isset($_GET['lang']) ? $_GET['lang'] : null;
// Read config and canvas from per-user DB
$config = [];
$canvasData = [];
$gearsConfig = [];
$udb = getUserDB();
if ($udb) {
    try {
        $res = $udb->query("SELECT value FROM config WHERE key='main'");
        $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
        if ($row) $config = json_decode($row['value'], true) ?? [];
        $res2 = $udb->query("SELECT value FROM canvas WHERE key='main'");
        $row2 = $res2 ? $res2->fetchArray(SQLITE3_ASSOC) : null;
        if ($row2) $canvasData = json_decode($row2['value'], true) ?? [];
        $res3 = $udb->query("SELECT value FROM config WHERE key='gears_settings'");
        $row3 = $res3 ? $res3->fetchArray(SQLITE3_ASSOC) : null;
        if ($row3) $gearsConfig = json_decode($row3['value'], true) ?? [];
    } catch (Exception $e) {
        error_log("User DB read failed: " . $e->getMessage());
    }
}
