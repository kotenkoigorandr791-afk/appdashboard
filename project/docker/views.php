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
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    global $theme, $accent, $embed;
    $bg = $theme === 'light' ? '#f3f6fb' : '#0f1419';
    $text = $theme === 'light' ? '#0f172a' : '#f4f7fb';
    $surface = $theme === 'light' ? '#ffffff' : 'rgba(18,24,32,0.8)';
    $surfaceHover = $theme === 'light' ? '#eef3fb' : 'rgba(255,255,255,0.10)';
    $border = $theme === 'light' ? '#d7deeb' : 'rgba(255,255,255,0.14)';
    $muted = $theme === 'light' ? '#64748b' : 'rgba(255,255,255,0.42)';
    $scrollTrack = $theme === 'light' ? '#e6ebf3' : 'rgba(255,255,255,0.04)';
    $scrollThumb = $theme === 'light' ? '#aeb9c9' : 'rgba(255,255,255,0.22)';
    $scrollThumbHover = $theme === 'light' ? '#8794a8' : 'rgba(255,255,255,0.35)';
    $accentEsc = htmlspecialchars($accent, ENT_QUOTES, 'UTF-8');
    $bar = $embed ? '' : '<div class="bar"><a href="' . htmlspecialchars(dockerUrl(['view' => null, 'id' => null]), ENT_QUOTES, 'UTF-8') . '">&larr; ' . htmlspecialchars(t('back'), ENT_QUOTES, 'UTF-8') . '</a></div>';
    echo '<!DOCTYPE html><html lang="' . htmlspecialchars($GLOBALS['lang'], ENT_QUOTES, 'UTF-8') . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title><style>:root{--bg:' . $bg . ';--surface:' . $surface . ';--surface-hover:' . $surfaceHover . ';--border:' . $border . ';--text:' . $text . ';--muted:' . $muted . ';--accent:' . $accentEsc . ';--danger:#f87171;--ok:#4ade80;--warn:#fbbf24;--scroll-track:' . $scrollTrack . ';--scroll-thumb:' . $scrollThumb . ';--scroll-thumb-hover:' . $scrollThumbHover . '}*{box-sizing:border-box;scrollbar-width:thin;scrollbar-color:var(--scroll-thumb) var(--scroll-track)}::-webkit-scrollbar{width:9px;height:9px}::-webkit-scrollbar-track{background:var(--scroll-track);border-radius:8px}::-webkit-scrollbar-thumb{background:var(--scroll-thumb);border:2px solid var(--scroll-track);border-radius:8px}::-webkit-scrollbar-thumb:hover{background:var(--scroll-thumb-hover)}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;background:var(--bg);color:var(--text);margin:0;padding:' . ($embed ? '16px' : '20px') . ';line-height:1.5}a{color:var(--accent)}h1{font-size:22px;margin:0 0 18px}.bar{margin-bottom:16px}.view-panel{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:18px}
.log-toolbar{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:14px}
.log-toolbar h1{margin:0;font-size:18px}
.log-copy-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;border:1px solid var(--border);background:var(--surface-hover);color:var(--text);cursor:pointer;font-size:13px;font-weight:500;transition:all .2s ease;flex-shrink:0;margin-left:auto}
.log-copy-btn:hover{background:var(--accent);color:#fff;border-color:var(--accent);transform:scale(1.04)}.log-copy-btn:active{transform:scale(0.96)}
.log-copy-btn .ico{transition:transform .25s ease}.log-copy-btn.copied{background:var(--ok);color:#000;border-color:var(--ok)}.log-copy-btn.copied .ico{transform:rotateY(360deg)}
.log-code,.json-pre{white-space:pre-wrap;word-break:break-word;background:rgba(0,0,0,.16);border:1px solid var(--border);padding:12px;border-radius:8px;font:13px/1.55 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:var(--text);max-height:70vh;overflow:auto}.log-line{padding:1px 6px;border-left:3px solid transparent}.log-error{background:rgba(248,113,113,.10);border-left-color:var(--danger)}.log-warn{background:rgba(251,191,36,.10);border-left-color:var(--warn)}.log-info{border-left-color:var(--accent)}.log-debug{color:var(--muted)}.log-json{color:#c4b5fd}.log-time{color:#7dd3fc}.log-level{font-weight:700}.log-level-error{color:var(--danger)}.log-level-warn{color:var(--warn)}.log-level-info{color:var(--ok)}.log-level-debug{color:#93c5fd}.log-json-key{color:#f0abfc}.stats-grid-view{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}.metric{background:var(--surface-hover);border:1px solid var(--border);border-radius:8px;padding:14px}.metric-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:10px;font-size:13px;color:var(--muted)}.metric-head strong{color:var(--text);font-size:13px;text-align:right}.metric-bar{height:10px;border-radius:999px;background:rgba(0,0,0,.22);overflow:hidden;border:1px solid var(--border)}.metric-bar span{display:block;height:100%;background:var(--accent);border-radius:999px}.metric.warn .metric-bar span{background:var(--warn)}.metric.danger .metric-bar span{background:var(--danger)}.metric.ok .metric-bar span{background:var(--ok)}.detail-list{display:grid;gap:8px;margin-top:14px;color:var(--muted);font-size:13px}.detail-list strong{color:var(--text)}details{margin-top:16px}summary{cursor:pointer;color:var(--accent);margin-bottom:10px}</style></head><body>' . $bar . $innerHtml . '</body></html>';
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
