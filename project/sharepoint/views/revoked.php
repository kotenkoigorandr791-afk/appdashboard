<?php
require_once __DIR__ . '/error.php';

function sp_render_revoked_page(?Share $share = null): string
{
    return sp_render_status_page('revoked', $share, 403);
}
