<?php
/**
 * Legacy entry point kept for bookmarks and direct links.
 *
 * The backup UI and JSON API live in project/backup/. The Admin Panel embeds
 * the same panel markup inline, without an iframe.
 */
require_once __DIR__ . '/project/backup/index.php';
