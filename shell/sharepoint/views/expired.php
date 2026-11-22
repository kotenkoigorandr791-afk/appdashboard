<?php
require_once __DIR__ . '/error.php';

function sp_render_expired_page(?Share $share = null): string
{
    return sp_render_status_page('expired', $share, 410);
}
