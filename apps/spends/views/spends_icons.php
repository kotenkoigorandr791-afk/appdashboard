<?php
function spends_icon(string $name, int $size = 18): string
{
    static $defs = [
        'close' => ['#94a3b8', '<path d="M6 6l12 12"/><path d="m18 6-12 12"/>'],
        'bell' => ['#f59e0b', '<path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5"/><path d="M9.5 17.5a2.5 2.5 0 0 0 5 0"/>'],
        'pencil' => ['#2563eb', '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>'],
        'check' => ['#10b981', '<path d="m5 12 4.5 4.5L19 7"/>'],
        'error' => ['#ef4444', '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>'],
        'chevron-right' => ['#64748b', '<path d="m9 6 6 6-6 6"/>'],
        'chevron-left' => ['#64748b', '<path d="m15 18-6-6 6-6"/>'],
        'trend-up' => ['#10b981', '<path d="m3 17 6-6 4 4 7-9"/><path d="M14 6h7v7"/>'],
        'trend-down' => ['#ef4444', '<path d="m3 7 6 6 4-4 7 9"/><path d="M14 18h7v-7"/>'],
        'search' => ['#64748b', '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>'],
        'download' => ['#2563eb', '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M4 21h16"/>'],
        'view' => ['#2563eb', '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>'],
        'restore' => ['#10b981', '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 3v6h6"/>'],
        'trash' => ['#ef4444', '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6"/><path d="M14 11v6"/>'],
    ];
    $d = $defs[$name] ?? $defs['close'];
    $stroke = $d[0];
    $path = $d[1];
    return '<span class="sp-ic sp-ic--' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true">'
        . '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="' . $stroke
        . '" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg></span>';
}