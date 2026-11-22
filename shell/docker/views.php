<?php
/**
 * Docker Manager — view helpers & page shell.
 */
declare(strict_types=1);

function ico($n) {
    $ico = [
        'brand' => '<svg class="ico" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
        'cube' => '<svg class="ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 16-7 4V10l7-4v10z"/><path d="m3.27 6.96 7 4.01"/><path d="M12 22V12"/><path d="m3.27 6.96-1.02 5.64a4 4 0 0 0 1.57 3.98L12 22l8.18-5.42a4 4 0 0 0 1.57-3.98L21 6.96 12 2 3.27 6.96z"/></svg>',
        'layers' => '<svg class="ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.05 4.32a2 2 0 0 1-1.9 0L2 17.65"/><path d="m22 12.65-9.05 4.32a2 2 0 0 1-1.9 0L2 12.65"/></svg>',
        'info' => '<svg class="ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>',
        'refresh' => '<svg class="ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/></svg>',
        'play' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="6 3 20 12 6 21 6 3"/></svg>',
        'stop' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="5" width="14" height="14" rx="1"/></svg>',
        'restart' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M21 21v-5h-5"/></svg>',
        'trash' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>',
        'pause' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>',
        'download' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
        'duplicate' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>',
        'kill' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>',
        'dot' => '<svg class="ico" width="8" height="8" viewBox="0 0 24 24" fill="currentColor" stroke="none"><circle cx="12" cy="12" r="5"/></svg>',
        'check' => '<svg class="ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
        'x' => '<svg class="ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
        'chevron' => '<svg class="ico chevron-ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>',
        'network' => '<svg class="ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>',
        'volume' => '<svg class="ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"/></svg>',
        'external' => '<svg class="ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>',
        'menu' => '<svg class="ico" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>',
        'file' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
        'chart' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
        'search' => '<svg class="ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
        'copy' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/></svg>',
    ];
    return $ico[$n] ?? '';
}

function viewPageShell($title, $innerHtml) {
    global $embed;
    $bar = ($embed || dockerIsFragment()) ? '' : '<div class="bar"><a href="' . htmlspecialchars(dockerUrl(['view' => null, 'id' => null]), ENT_QUOTES, 'UTF-8') . '">&larr; ' . htmlspecialchars(t('back'), ENT_QUOTES, 'UTF-8') . '</a></div>';
    dockerOutput('<div class="docker-view">' . $bar . $innerHtml . '</div>', (string)$title, true);
}

function renderHighlightedLogs($text) {
    $lines = preg_split('/\R/u', (string)$text);
    if ($lines === false) {
        $lines = [(string)$text];
    }
    $html = '<div class="log-code">';
    foreach ($lines as $line) {
        $raw = (string)$line;
        $cls = 'log-line';
        if (preg_match('/\b(error|fatal|panic|critical|exception|failed|failure)\b/i', $raw)) {
            $cls .= ' log-error';
        } elseif (preg_match('/\b(warn|warning|deprecated)\b/i', $raw)) {
            $cls .= ' log-warn';
        } elseif (preg_match('/\b(debug|trace|verbose)\b/i', $raw)) {
            $cls .= ' log-debug';
        } elseif (preg_match('/\b(info|notice|success|started|listening|ready)\b/i', $raw)) {
            $cls .= ' log-info';
        }
        if (preg_match('/^\s*[\{\[]/', $raw)) {
            $cls .= ' log-json';
        }
        $escaped = htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
        $escaped = preg_replace('/^(\s*(?:\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}(?:[.,]\d+)?Z?|\[[^\]]+\]))/u', '<span class="log-time">$1</span>', $escaped);
        $escaped = preg_replace('/\b(ERROR|ERR|FATAL|PANIC|CRITICAL|EXCEPTION|FAILED|FAILURE)\b/i', '<span class="log-level log-level-error">$1</span>', $escaped);
        $escaped = preg_replace('/\b(WARN|WARNING|DEPRECATED)\b/i', '<span class="log-level log-level-warn">$1</span>', $escaped);
        $escaped = preg_replace('/\b(INFO|NOTICE|SUCCESS|STARTED|LISTENING|READY)\b/i', '<span class="log-level log-level-info">$1</span>', $escaped);
        $escaped = preg_replace('/\b(DEBUG|TRACE|VERBOSE)\b/i', '<span class="log-level log-level-debug">$1</span>', $escaped);
        $escaped = preg_replace('/(&quot;[^&]*?&quot;)(\s*:)/u', '<span class="log-json-key">$1</span>$2', $escaped);
        $html .= '<div class="' . $cls . '">' . ($escaped === '' ? '&nbsp;' : $escaped) . '</div>';
    }
    return $html . '</div>';
}
