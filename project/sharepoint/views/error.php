<?php
require_once __DIR__ . '/header.php';

function sp_render_status_page(string $reason, ?Share $share = null, int $status = 200): string
{
    $reason = strtolower($reason);
    $config = [
        'expired' => ['icon' => 'schedule', 'title' => sp_t('expired')],
        'revoked' => ['icon' => 'block', 'title' => sp_t('revoked')],
        'not_found' => ['icon' => 'link_off', 'title' => sp_t('not_found')],
        'max_views_exceeded' => ['icon' => 'visibility_off', 'title' => sp_t('views_exceeded')],
        'resource_unavailable' => ['icon' => 'cloud_off', 'title' => sp_t('resource_unavailable')],
        'too_many_attempts' => ['icon' => 'timer', 'title' => sp_t('too_many_attempts')],
    ];
    $view = $config[$reason] ?? ['icon' => 'error', 'title' => sp_t('not_found')];
    $body = '<section class="sp-empty md3-card md3-card--outlined" role="alert">
  <span class="material-symbols-outlined sp-empty__icon">' . sp_h($view['icon']) . '</span>
  <h1 class="md3-typescale-headline-small">' . sp_h($view['title']) . '</h1>
  <p class="md3-typescale-body-large sp-muted">' . sp_h($view['title']) . '</p>
  <a class="md3-btn md3-btn--filled" href="/"><span class="material-symbols-outlined">home</span>' . sp_h(sp_t('app_dashboard')) . '</a>
</section>';
    return sp_public_shell($view['title'], $body, $share, $status);
}
