<?php

/**
 * Cloudflare Turnstile — adaptive captcha for outbound auth emails.
 *
 * Required from project/bootstrap.php, i.e. before core.php pulls in
 * vendor/autoload.php, so only curl_* may be used for the siteverify call.
 *
 * Adaptive model: the widget is not rendered on every request. A form is served
 * without captcha, turnstile_guard() evaluates the risk signals and only when one
 * of them fires the slot is marked data-turnstile-required="1" so the client
 * mounts Turnstile and repeats the submission with a token.
 */

function turnstile_site_key(): string {
    return trim((string)getConfig('turnstile_site_key'));
}

function turnstile_secret_key(): string {
    return trim((string)getConfig('turnstile_secret_key'));
}

function turnstile_enabled(): bool {
    return turnstile_site_key() !== '' && turnstile_secret_key() !== '';
}

function turnstile_lock_type(string $action): string {
    return in_array($action, ['reset', 'reset_email', 'send_reset_code'], true) ? 'reset_email' : 'register';
}

/**
 * Server-side verification against the Cloudflare siteverify endpoint.
 *
 * Fail-open on transport problems (timeout / no answer / 5xx) so an unavailable
 * Cloudflare never blocks registration, fail-closed when Cloudflare answers
 * success:false (invalid or replayed token).
 */
function turnstile_verify(string $token, string $remoteIp): bool {
    $secret = turnstile_secret_key();
    if ($secret === '' || $token === '') return false;

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    if ($ch === false) {
        error_log('[turnstile] curl_init failed — fail-open');
        return true;
    }
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => $remoteIp,
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $body   = curl_exec($ch);
    $errno  = curl_errno($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $errno !== 0 || $status === 0 || $status >= 500) {
        error_log('[turnstile] siteverify transport error (errno=' . $errno . ', status=' . $status . ') — fail-open');
        return true;
    }
    $data = json_decode((string)$body, true);
    if (!is_array($data)) {
        error_log('[turnstile] siteverify returned non-JSON body (status=' . $status . ') — fail-open');
        return true;
    }
    if (($data['success'] ?? false) !== true) {
        error_log('[turnstile] siteverify rejected token: ' . json_encode($data['error-codes'] ?? []));
        return false;
    }
    return true;
}

function turnstile_widget_required(string $action): bool {
    return !empty($_SESSION['_turnstile_widget_required'][$action]);
}

/**
 * Records the moment the form was rendered so turnstile_guard() can reject
 * submissions that arrive faster than a human could produce them.
 * Refreshed on every render so repeated attempts never lock a real user out.
 */
function turnstile_form_started(string $action): void {
    $_SESSION['_turnstile_started'][$action] = time();
}

/**
 * Adaptive decision table (priority order):
 *
 *   0 keys not configured      pass                      fail-open until keys exist
 *   1 honeypot filled          fail 'honeypot'           no widget — bot loops
 *   2 submitted in < 3 sec     fail 'too_fast'           show widget
 *   3 IP locked out            fail 'rate_limited'       no widget, retry_after
 *   4 email cooldown active    fail 'email_cooldown'     no widget, retry_after
 *   5 widget required, no tok. fail 'captcha_required'   show widget (fail-open when
 *                                                        the client reports the widget
 *                                                        could not be loaded)
 *   6 token invalid            fail 'captcha_failed'     show widget
 *   7 token valid              pass
 *   8 no signal fired          pass                      normal path, no captcha
 */
function turnstile_guard(string $action, string $email): array {
    $decide = static function (bool $pass, string $reason, int $retryAfter, bool $showWidget) use ($action): array {
        if ($showWidget) {
            $_SESSION['_turnstile_widget_required'][$action] = 1;
            $_SESSION['_turnstile_widget_reason'][$action] = $reason;
        } else {
            unset($_SESSION['_turnstile_widget_required'][$action]);
            unset($_SESSION['_turnstile_widget_reason'][$action]);
        }
        return [
            'pass'        => $pass,
            'reason'      => $reason,
            'retry_after' => $retryAfter,
            'show_widget' => $showWidget,
            'action'      => $action,
        ];
    };

    if (!turnstile_enabled()) {
        return $decide(true, '', 0, false);
    }
    if (trim((string)($_POST['ap_email_confirm'] ?? '')) !== '') {
        return $decide(false, 'honeypot', 0, false);
    }
    $started = (int)($_SESSION['_turnstile_started'][$action] ?? 0);
    if ($started > 0 && (time() - $started) < 3) {
        return $decide(false, 'too_fast', 0, true);
    }

    $ip      = getClientIp();
    $lockSec = getIpLockoutSeconds($ip, turnstile_lock_type($action));
    if ($lockSec > 0) {
        return $decide(false, 'rate_limited', $lockSec, false);
    }
    $cooldown = emailSendCooldownRemaining($email);
    if ($cooldown > 0) {
        return $decide(false, 'email_cooldown', $cooldown, false);
    }

    $token = trim((string)($_POST['cf-turnstile-response'] ?? ''));
    if ($token === '') {
        if (!turnstile_widget_required($action)) {
            return $decide(true, '', 0, false);
        }
        if ((string)($_POST['ap_turnstile_unavailable'] ?? '') === '1') {
            error_log('[turnstile] widget unavailable accepted for action=' . $action . ' — fail-open');
            return $decide(true, '', 0, false);
        }
        return $decide(false, 'captcha_required', 0, true);
    }
    if (!turnstile_verify($token, $ip)) {
        return $decide(false, 'captcha_failed', 0, true);
    }
    return $decide(true, '', 0, false);
}

/**
 * Honeypot + lazy widget slot for one form. The slot is always present but
 * empty (hidden by CSS) unless the guard asked for the captcha; only
 * data-turnstile-required="1" makes the client load turnstile.js.
 */
function turnstile_render(string $action, bool $forceRequired = false): string {
    if (!turnstile_enabled()) return '';
    $required = $forceRequired || turnstile_widget_required($action);
    $cfg      = $GLOBALS['config'] ?? null;
    $theme    = (is_array($cfg) && ($cfg['theme'] ?? '') === 'dark') ? 'dark' : 'light';
    $slot     = '<div class="ap-turnstile-slot"'
        . ' data-turnstile-form="' . my_htmlspecialchars($action) . '"'
        . ' data-sitekey="' . my_htmlspecialchars(turnstile_site_key()) . '"'
        . ' data-theme="' . $theme . '"'
        . ($required ? ' data-turnstile-required="1"' : '')
        . ($required ? ' data-turnstile-reason="' . my_htmlspecialchars($_SESSION['_turnstile_widget_reason'][$action] ?? 'signal') . '"' : '')
        . '></div>';
    $honeypot = '<div class="ap-turnstile-hp" aria-hidden="true">'
        . '<label>Email confirm<input type="text" name="ap_email_confirm" tabindex="-1" autocomplete="off" value=""></label>'
        . '</div>';
    return $slot . $honeypot;
}
