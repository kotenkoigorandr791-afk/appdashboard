<?php
declare(strict_types=1);

require_once __DIR__ . '/i18n_loader.php';

$messages = sharepoint_load_share_i18n();

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo 'window.SHAREPOINT_SHARE_I18N = ' . json_encode(
    $messages,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) . ';';