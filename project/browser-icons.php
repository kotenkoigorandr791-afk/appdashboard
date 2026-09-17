<?php
function session_brand_icon_base(): string {
    return '/project/assets/session-icons';
}

function get_browser_icon_url(string $browser): string {
    $map = [
        'chrome' => 'chrome.svg',
        'firefox' => 'firefox.svg',
        'safari' => 'safari.svg',
        'edge' => 'edge.svg',
        'opera' => 'opera.svg',
        'brave' => 'brave.svg',
    ];
    $file = $map[$browser] ?? 'unknown.svg';
    return session_brand_icon_base() . '/browsers/' . $file;
}

function get_device_icon_url(string $device): string {
    $map = [
        'windows' => 'windows.svg',
        'linux' => 'linux.svg',
        'android' => 'android.svg',
        'macos' => 'macos.svg',
        'ios' => 'ios.svg',
    ];
    $file = $map[$device] ?? 'unknown.svg';
    return session_brand_icon_base() . '/devices/' . $file;
}

function get_browser_icon_svg(string $browser, int $size = 24): string {
    $url = htmlspecialchars(get_browser_icon_url($browser), ENT_QUOTES, 'UTF-8');
    $s = (int)$size;
    return '<img src="' . $url . '" width="' . $s . '" height="' . $s . '" alt="" class="session-brand-icon" loading="lazy" decoding="async">';
}

function get_device_icon_html(string $device, int $size = 24): string {
    $url = htmlspecialchars(get_device_icon_url($device), ENT_QUOTES, 'UTF-8');
    $s = (int)$size;
    return '<img src="' . $url . '" width="' . $s . '" height="' . $s . '" alt="" class="session-brand-icon" loading="lazy" decoding="async">';
}
