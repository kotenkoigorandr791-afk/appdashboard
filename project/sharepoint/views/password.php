<?php
require_once __DIR__ . '/header.php';

function sp_render_password_page(Share $share, string $error = ''): string
{
    $errorHtml = $error !== ''
        ? '<div class="md3-card md3-card--outlined sp-alert sp-alert--error"><span class="material-symbols-outlined">error</span><span>' . sp_h($error) . '</span></div>'
        : '';
    $body = '<section class="md3-card md3-card--elevated sp-password-card">
  <div class="md3-card__content">
    <span class="material-symbols-outlined sp-password-card__icon">lock</span>
    <h1 class="md3-typescale-headline-small">' . sp_h(sp_t('password_required')) . '</h1>
    <p class="md3-typescale-body-large sp-muted">' . sp_h(sp_t('password_hint')) . '</p>
    ' . $errorHtml . '
    <form method="post" action="' . sp_h(SHAREPOINT_PUBLIC_PREFIX . '/' . rawurlencode($share->token) . '/unlock') . '" class="sp-password-form">
      <input type="hidden" name="_csrf" value="' . sp_h(sp_csrf_token()) . '">
      <div class="md3-text-field md3-text-field--outlined">
        <input class="md3-text-field__input" type="password" id="sp-password" name="password" placeholder=" " autocomplete="current-password" autofocus required>
        <label class="md3-text-field__label" for="sp-password">' . sp_h(sp_t('password')) . '</label>
      </div>
      <button class="md3-btn md3-btn--filled" type="submit"><span class="material-symbols-outlined">lock_open</span>' . sp_h(sp_t('unlock')) . '</button>
    </form>
  </div>
</section>';
    return sp_public_shell(sp_t('password_required'), $body, $share, 200);
}
