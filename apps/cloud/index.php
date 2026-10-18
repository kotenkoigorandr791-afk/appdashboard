<?php
// index.php
// Entry UI: reads theme/lang/color from URL and serves the app shell.
declare(strict_types=1);
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}

function get_param(string $key, string $default=''): string {
  return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

$lang = get_param('lang', 'en'); // en, ru, uk, be, pl, cs
$theme = get_param('theme', 'light'); // light, dark
$color = get_param('color', '#4a90e2'); // hex like %2385b503
if (!preg_match('/^#?[0-9a-fA-F]{6}$/', $color)) $color = '#4a90e2';
if ($color[0] !== '#') $color = '#'.$color;

$safeLang = in_array($lang, ['en','ru','uk','be','pl','cs'], true) ? $lang : 'en';
$safeTheme = in_array($theme, ['light','dark'], true) ? $theme : 'light';

$nameData = is_file(__DIR__ . '/name.json') ? json_decode((string)file_get_contents(__DIR__ . '/name.json'), true) : [];
if (!is_array($nameData)) {
    $nameData = [];
}
$parseMenuBtn = static function (array $data, string $key, array $fallback): array {
    $btn = isset($data[$key]) && is_array($data[$key]) ? $data[$key] : $fallback;
    $parsed = $fallback;
    foreach (['left', 'top', 'width', 'height'] as $k) {
        if (isset($btn[$k]) && is_numeric($btn[$k])) {
            $parsed[$k] = max(0, (int)$btn[$k]);
        }
    }
    return $parsed;
};
$defaultMenuBtn = ['left' => 7, 'top' => 12, 'width' => 36, 'height' => 36];
$menuBtn = $parseMenuBtn($nameData, 'menu_button', $defaultMenuBtn);
$menuBtnMobile = $parseMenuBtn($nameData, 'menu_button_mobile', $menuBtn);
$menuBtnReserve = $menuBtn['left'] + $menuBtn['width'] + 7;
$menuBtnMobileReserve = $menuBtnMobile['left'] + $menuBtnMobile['width'] + 6;
$menuBtnClearTop = max(0, ($menuBtn['top'] + $menuBtn['height']) - 30);
$menuBtnToolbarTop = $menuBtnClearTop + 10;
$menuBtnMobileClearTop = max(0, ($menuBtnMobile['top'] + $menuBtnMobile['height']) - 30);
$menuBtnMobileToolbarTop = $menuBtnMobileClearTop + 8;

$cloudScriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$cloudRequestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
$cloudScriptDir = str_replace('\\', '/', dirname($cloudScriptName));
if (str_contains($cloudRequestPath, '/apps/cloud')) {
    $cloudScriptDir = '/apps/cloud';
} elseif ($cloudScriptDir === '/' || $cloudScriptDir === '.' || $cloudScriptDir === '') {
    $cloudScriptDir = '/apps/cloud';
}
$cloudAppBase = rtrim($cloudScriptDir, '/');
$cloudApiBase = $cloudAppBase . '/api.php';
$cloudAiBase = $cloudAppBase . '/ai.php';

// Auth gate: this page is served directly by nginx, so it must enforce the
// dashboard session itself instead of relying on project/routes.php.
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth_gate.php';
cloud_session_start();
cloud_require_session_auth();

?><!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($safeLang, ENT_QUOTES); ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, interactive-widget=resizes-content" />
  <title data-i18n="appTitle">File Space</title>
  <?php require_once dirname(__DIR__, 2) . '/lib/sentry_bootstrap.php'; app_sentry_browser_script('cloud'); ?>
  <link rel="preload" as="image" href="assets/background_<?php echo $safeTheme; ?>.jpg" fetchpriority="high" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap" />
  <link rel="stylesheet" href="assets/styles.css?v=49" />
  <script>
    try {
      if (localStorage.getItem('hideLangSwitcher') === '1') {
        document.documentElement.classList.add('hide-lang-switcher');
      }
    } catch (e) {}
  </script>
  <style>
:root{--menu-button-reserve:<?php echo (int)$menuBtnReserve; ?>px;--menu-button-mobile-reserve:<?php echo (int)$menuBtnMobileReserve; ?>px;--menu-button-clear-top:<?php echo (int)$menuBtnClearTop; ?>px;--menu-button-toolbar-top:<?php echo (int)$menuBtnToolbarTop; ?>px;--menu-button-mobile-toolbar-top:<?php echo (int)$menuBtnMobileToolbarTop; ?>px;}
.hide-lang-switcher .lang-switcher{display:none!important;}
.settings-menu,.dropdown-menu,.context-menu,.tab-preview,.search-panel{background:color-mix(in srgb,var(--panel) 24%,transparent)!important;backdrop-filter:blur(10px)!important;-webkit-backdrop-filter:blur(10px)!important;}
.modal-content{background:color-mix(in srgb,var(--panel) 88%,var(--bg));backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);}
.root-toolbar{width:50px;display:flex;flex-direction:column;align-items:center;gap:10px;padding:12px 7px;border-right:1px solid color-mix(in srgb,var(--border) 70%,transparent);background:var(--glass-panel);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);transition:opacity 0.3s ease,transform 0.3s ease}
.root-toolbar.hidden-by-sidebar{opacity:0;transform:translateX(-100%);pointer-events:none;position:absolute;height:100%;z-index:-1}
.trash-mode .root-toolbar.hidden-by-sidebar{opacity:1;transform:none;pointer-events:auto;position:static;height:auto;z-index:auto}
.root-toolbar-btn{width:36px;height:36px;border:1px solid color-mix(in srgb,var(--ui-border) 80%,transparent);background:var(--glass-bg);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);color:var(--fg);border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background-color .18s ease,border-color .18s ease,box-shadow .18s ease,color .18s ease,transform .18s ease}
.root-toolbar-btn:hover{background:color-mix(in srgb,var(--accent) 10%,var(--bg));border-color:color-mix(in srgb,var(--accent) 45%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--accent) 12%,transparent)}
.root-toolbar-btn:active{transform:translateY(1px)}
.root-toolbar-btn.active{border-color:var(--accent);color:var(--accent);box-shadow:0 0 0 1px var(--accent) inset}
	.root-toolbar-top-separator{width:100%;height:50px;display:flex;align-items:flex-end}
	#btn-toggle-sidebar{position:fixed;left:<?php echo (int)($menuBtn['left']+$menuBtn['width']); ?>px;top:<?php echo (int)$menuBtn['top']; ?>px;width:<?php echo (int)$menuBtn['width']; ?>px;height:<?php echo (int)$menuBtn['height']; ?>px;border-radius:0 10px 10px 0;background:var(--glass-panel);border:1px solid color-mix(in srgb,var(--border) 70%,transparent);border-left:none;box-shadow:none;backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);z-index:1200}
	@media (max-width:768px){#btn-toggle-sidebar{left:<?php echo (int)($menuBtnMobile['left']+$menuBtnMobile['width']); ?>px;top:<?php echo (int)$menuBtnMobile['top']; ?>px;width:<?php echo (int)$menuBtnMobile['width']; ?>px;height:<?php echo (int)$menuBtnMobile['height']; ?>px}}
	.root-toolbar-spacer{margin-top:auto}
.root-toolbar-footer{display:flex;flex-direction:column;align-items:center;gap:10px;width:100%}
.root-toolbar-separator{width:100%;padding:2px 0 0;margin-top:2px}
.root-toolbar-top-separator::before,.root-toolbar-separator::before{content:"";display:block;height:1px;margin:0 7px;background:color-mix(in srgb,var(--border) 72%,transparent);width:calc(100% - 14px)}
.sync-log-modal-content{max-width:980px;width:92vw}
.sync-log-table-wrap{max-height:60vh;overflow:auto;border:1px solid var(--border);border-radius:10px}
.sync-log-table{width:100%;border-collapse:collapse;font-size:13px}
.sync-log-table th,.sync-log-table td{padding:10px 12px;border-bottom:1px solid var(--border);text-align:left}
.sync-log-level-info{color:#38bdf8;font-weight:600}
.sync-log-level-warning{color:#f59e0b;font-weight:600}
.sync-log-level-error{color:#ef4444;font-weight:700}
.root-toolbar-btn.hidden{display:none}
#group-switcher-bar{display:flex;align-items:center;gap:8px;padding:8px 12px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);background:color-mix(in srgb,var(--panel) 40%,transparent)}
#group-switcher-bar.hidden{display:none}
.group-switcher-label{font-size:12px;color:var(--muted)}
.group-switcher-select{flex:1;min-width:0;padding:6px 8px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--fg)}
.groups-members-table,.groups-history-table,.groups-overview-table{width:100%;border-collapse:collapse;font-size:13px;display:table;table-layout:fixed}
#modal-groups-manage .modal-body table{display:table;width:100%;table-layout:fixed}
#modal-groups-manage #groups-manage-body{width:100%}
.groups-overview-wrap,.groups-history-table-wrap{width:100%;box-sizing:border-box}
.groups-members-table th,.groups-members-table td,.groups-history-table th,.groups-history-table td,.groups-overview-table th,.groups-overview-table td{padding:10px 12px;border-bottom:1px solid var(--border);text-align:left;vertical-align:middle}
.groups-overview-wrap{border:1px solid var(--border);border-radius:10px;overflow:auto;max-height:220px;background:color-mix(in srgb,var(--panel) 50%,var(--bg))}
.groups-overview-table thead th{position:sticky;top:0;z-index:1;background:color-mix(in srgb,var(--panel) 92%,var(--bg));font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.03em}
.groups-overview-row{cursor:pointer;transition:background .15s ease}
.groups-overview-row:hover{background:color-mix(in srgb,var(--accent) 6%,transparent)}
.groups-overview-row--active{background:color-mix(in srgb,var(--accent) 12%,transparent)}
.groups-overview-row--active .groups-col-name strong{color:var(--accent)}
.groups-col-name{min-width:140px}
.groups-col-admin{color:var(--muted);font-size:12px;word-break:break-all}
.groups-col-actions{text-align:right;white-space:nowrap}
.groups-role-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;background:color-mix(in srgb,var(--border) 40%,transparent)}
.groups-role-badge--admin{background:color-mix(in srgb,var(--accent) 18%,transparent);color:var(--accent)}
.groups-list-empty{margin:0;padding:16px;text-align:center;color:var(--muted);font-size:13px}
.groups-detail-panel{margin-top:16px;padding-top:16px;border-top:1px solid var(--border)}
.groups-detail-title{margin:0 0 12px;font-size:15px;font-weight:600}
.groups-members-section{margin-top:4px}
.groups-members-section h4,.groups-history-panel h4{margin:0 0 10px;font-size:13px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.03em}
.groups-perm-line{margin:0 0 12px;font-size:13px;color:var(--muted)}
.groups-add-member{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;align-items:flex-start}
.groups-add-email-wrap{position:relative;flex:1;min-width:180px}
.groups-add-email-wrap input{width:100%;box-sizing:border-box}
.groups-add-suggest{position:absolute;left:0;right:0;top:calc(100% + 4px);z-index:20;background:var(--panel);border:1px solid var(--border);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.18);overflow:hidden}
.groups-add-suggest.hidden{display:none}
.groups-add-suggest-item{display:flex;align-items:center;gap:10px;width:100%;padding:10px 12px;border:none;background:transparent;color:var(--fg);cursor:pointer;text-align:left}
.groups-add-suggest-item:hover{background:color-mix(in srgb,var(--accent) 10%,transparent)}
.groups-add-suggest-avatar{width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0}
.groups-add-suggest-email{font-size:13px;word-break:break-all}
.groups-col-actions{display:flex;align-items:center;gap:6px;justify-content:flex-end}
.groups-leave-btn{width:32px;height:32px;padding:0;flex-shrink:0;color:var(--muted);display:inline-flex;align-items:center;justify-content:center}
.groups-leave-btn:hover{color:var(--danger)}
.groups-leave-btn svg{display:block}
.groups-leave-action{display:inline-flex;align-items:center;justify-content:center;gap:6px}
.groups-leave-action svg,.groups-leave-action-icon svg{display:block;flex-shrink:0}
.groups-leave-action-icon{display:inline-flex;align-items:center;justify-content:center}
.groups-member-detail .groups-manage-actions{margin-top:12px}
.groups-history-table-wrap{max-height:40vh;overflow:auto;border:1px solid var(--border);border-radius:8px}
.groups-history-path{display:block;padding:4px 6px;border-radius:6px;font-size:12px;white-space:pre-wrap;word-break:break-all;background:color-mix(in srgb,var(--panel) 80%,var(--bg))}
.groups-history-action{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace}
.groups-history-action--open{background:color-mix(in srgb,#38bdf8 18%,transparent);color:#38bdf8}
.groups-history-action--edit,.groups-history-action--rename{background:color-mix(in srgb,#a78bfa 18%,transparent);color:#a78bfa}
.groups-history-action--move{background:color-mix(in srgb,#f59e0b 18%,transparent);color:#f59e0b}
.groups-history-action--create{background:color-mix(in srgb,#22c55e 18%,transparent);color:#22c55e}
.groups-history-action--delete{background:color-mix(in srgb,#ef4444 18%,transparent);color:#ef4444}
.groups-btn-icon{display:inline-flex;align-items:center;justify-content:center}
.groups-btn-icon svg{display:block}
.groups-manage-actions .btn{display:inline-flex;align-items:center;gap:6px}
.groups-delete-target-display{margin-top:8px;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:color-mix(in srgb,var(--panel) 70%,var(--bg));font-size:13px;word-break:break-all}
.groups-delete-move-picker{display:flex;flex-direction:column;gap:8px;margin-top:12px}
.groups-manage-actions{display:flex;gap:8px;margin-top:16px;flex-wrap:wrap}
.groups-delete-move-fields{display:flex;flex-direction:column;gap:10px;margin-top:4px}
  </style>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.css">
  <script src="https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.polyfilled.min.js"></script>
  <!-- Tesseract must load before Monaco's AMD loader (vs/loader.js), otherwise the UMD build
       calls define() and never sets window.Tesseract — OCR then times out waiting for the API. -->
  <script src="https://cdn.jsdelivr.net/npm/tesseract.js@6/dist/tesseract.min.js"></script>
<!-- After CodeMirror connections, add Monaco Editor -->
<link rel="stylesheet" data-name="vs/editor/editor.main" href="https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs/editor/editor.main.min.css">
<script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs/loader.js"></script>
<script>
  try {
    if (window.define && window.define.amd) {
      delete window.define.amd;
    }
  } catch (e) {}
</script>

  <link rel="stylesheet" 
        href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css" 
        integrity="sha512-rO+olRTkcf304DQBxSWxln8JXCzTHlKnIdnMUwYvQa9/Jd4cQaNkItIUj6Z4nvW1dqK0SKXLbn9h4KwZTNtAyw==" 
        crossorigin="anonymous" 
        referrerpolicy="no-referrer" />
  <script defer 
          src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js" 
          integrity="sha512-D9gUyxqja7hBtkWpPWGt9wfbfaMGVt9gnyCvYa+jojwwPHLCzUm5i8rpk7vD7wNee9bA35eYIjobYPaQuKS1MQ==" 
          crossorigin="anonymous" 
          referrerpolicy="no-referrer"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.22/dist/katex.min.css">
  <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.22/dist/katex.min.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.22/dist/contrib/auto-render.min.js"></script>

  


  <script>
    window.APP_CONFIG = {
      lang: "<?php echo htmlspecialchars($safeLang, ENT_QUOTES); ?>",
      theme: "<?php echo htmlspecialchars($safeTheme, ENT_QUOTES); ?>",
      color: "<?php echo htmlspecialchars($color, ENT_QUOTES); ?>",
      appBase: "<?php echo htmlspecialchars($cloudAppBase, ENT_QUOTES, 'UTF-8'); ?>",
      apiBase: "<?php echo htmlspecialchars($cloudApiBase, ENT_QUOTES, 'UTF-8'); ?>",
      aiApiBase: "<?php echo htmlspecialchars($cloudAiBase, ENT_QUOTES, 'UTF-8'); ?>",
      collabWsUrl: "<?php
        $wsScheme = 'ws';
        if (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        ) {
            $wsScheme = 'wss';
        }
        if (isset($_SERVER['HTTP_CF_VISITOR'])) {
            $cfVisitor = json_decode((string)$_SERVER['HTTP_CF_VISITOR'], true);
            if (is_array($cfVisitor) && (($cfVisitor['scheme'] ?? '') === 'https')) {
                $wsScheme = 'wss';
            }
        }
        $wsHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        echo htmlspecialchars($wsScheme . '://' . $wsHost . '/cloud-collab-ws', ENT_QUOTES, 'UTF-8');
      ?>"
    };
  </script>



<script defer src="assets/completions.js"></script>
<script defer src="assets/filename-guard.js?v=1"></script>
<script defer src="assets/app.js?v=45"></script>
<script defer src="assets/app/editors-and-tree.js?v=12"></script>
<script defer src="assets/app/media-player.js?v=1"></script>
<script defer src="assets/app/viewer-layout.js?v=1"></script>
<script defer src="assets/app/file-viewers.js?v=3"></script>
<script defer src="assets/app/file-actions.js?v=10"></script>
<script defer src="assets/app/downloads-and-account.js?v=1"></script>
<script defer src="assets/app/cloud-groups.js?v=8"></script>
<script defer src="assets/audio-player.js?v=2"></script>
<script defer src="assets/app/cloud-group-chat.js?v=9"></script>
<script defer src="assets/ui/button-feedback.js?v=1"></script>
<script defer src="assets/ui/topbar.js?v=3"></script>
<script defer src="assets/settings/sync-modals.js?v=9"></script>
<script defer src="assets/storage-usage-modal.js?v=1"></script>
<script defer src="assets/upload.js?v=4"></script>
<script defer src="assets/advanced-search.js?v=1"></script>
<script defer src="assets/properties-modal.js?v=1"></script>
<script defer src="assets/ai-agent-skeleton.js?v=15"></script>
<script defer src="assets/ai-assistant.js?v=40"></script>
<script defer src="assets/monaco-shared.js?v=3"></script>
<script defer src="assets/monaco-editor.js?v=21"></script>


</head>
<body data-theme="<?php echo htmlspecialchars($safeTheme, ENT_QUOTES); ?>">

  <header class="topbar">
  <div class="brand">
    <button id="btn-toggle-sidebar" class="icon-btn" title="" data-i18n-title="toggleSidebar" aria-label="toggleSidebar">
      <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
    </button>
  </div>

  <div id="topbar-search-host" class="topbar-search-host">
    <div class="search-container">
      <div class="search-input-wrapper">
        <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" id="search-input" placeholder="" class="search-input" data-i18n-placeholder="search" aria-label="search" />
        <button id="btn-advanced-search" class="btn small" title="" data-i18n-title="advancedSearch">
          <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            <line x1="8" y1="8" x2="14" y2="14"></line>
            <line x1="14" y1="8" x2="8" y2="14"></line>
          </svg>
        </button>
        <button id="btn-clear-search" class="search-clear hidden">
          <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>
    </div>
  </div>

  <div id="topbar-actions" class="actions">
    <div class="lang-switcher">
      <div class="lang-option active" data-lang="en" data-i18n="english"></div>
      <div class="lang-option" data-lang="ru" data-i18n="russian"></div>
      <div class="lang-option" data-lang="uk" data-i18n="ukrainian"></div>
    </div>
     <button id="btn-group-chat" class="icon-btn hidden" title="" data-i18n-title="groupChatTitle">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.1 2.182a10 10 0 0 1 3.8 0"/><path d="M13.9 21.818a10 10 0 0 1-3.8 0"/><path d="M17.609 3.72a10 10 0 0 1 2.69 2.7"/><path d="M2.182 13.9a10 10 0 0 1 0-3.8"/><path d="M20.28 17.61a10 10 0 0 1-2.7 2.69"/><path d="M21.818 10.1a10 10 0 0 1 0 3.8"/><path d="M3.721 6.391a10 10 0 0 1 2.7-2.69"/><path d="m6.163 21.117-2.906.85a1 1 0 0 1-1.236-1.169l.965-2.98"/></svg>
      <span class="group-chat-unread-badge hidden" aria-hidden="true"></span>
    </button>
    <div class="settings">
      <button id="btn-settings" class="icon-btn settings-avatar-btn" title="" data-i18n-title="settings">
        <img id="user-settings-avatar" class="user-settings-avatar" src="/default_avatar.png" alt="">
      </button>
      <div id="settings-menu" class="settings-menu hidden" role="dialog" aria-labelledby="settings-menu-title">
        <div class="settings-menu-header">
          <h3 id="settings-menu-title" data-i18n="settings">Settings</h3>
          <button id="btn-close-settings-menu" class="icon-btn settings-menu-close" type="button" aria-label="Close settings">
            <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </div>
        <div class="settings-menu-body">

        <section class="settings-group">
          <h4 class="settings-group-title" data-i18n="sectionOverview">Overview</h4>
          <div class="settings-actions">
            <button id="btn-storage-usage" class="settings-action-btn">
              <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="10" width="4" height="10"></rect>
                <rect x="10" y="6" width="4" height="14"></rect>
                <rect x="17" y="3" width="4" height="17"></rect>
              </svg>
              <span data-i18n="storageUsageOpenButton">Storage usage by folders</span>
            </button>
            <button id="btn-open-sync-log" class="settings-action-btn">
              <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" aria-hidden="true"><path d="M8 6h13"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M3 6h.01"></path><path d="M3 12h.01"></path><path d="M3 18h.01"></path></svg>
              <span data-i18n="syncLogOpenButton">Sync log</span>
            </button>
            <button id="btn-groups-manage" class="settings-action-btn">
              <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              <span data-i18n="groupsManage">Manage groups</span>
            </button>
            <button id="btn-about-filespace" class="settings-action-btn">
              <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
              <span data-i18n="info">About File Space</span>
            </button>
          </div>
        </section>

        <section class="settings-group">
          <h4 class="settings-group-title" data-i18n="currentUser">Current User</h4>
          <div class="settings-row">
            <div class="settings-row-text">
              <span class="settings-row-label" data-i18n="role">Role</span>
            </div>
            <div class="user-role-display">
              <svg id="user-role-icon" class="user-role-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17.925 20.056a6 6 0 0 0-11.851.001"/>
                <circle cx="12" cy="11" r="4"/>
                <circle cx="12" cy="12" r="10"/>
              </svg>
              <span id="user-role-label" class="user-role-label" data-i18n="roleGuest">Guest</span>
            </div>
          </div>
          <div class="users-table-wrapper">
            <table id="users-table" class="users-table">
              <thead>
                <tr>
                  <th data-i18n="user">User</th>
                  <th data-i18n="used">Used</th>
                  <th data-i18n="quota">Quota</th>
                  <th data-i18n="sessionLimit">Session Limit</th>
                </tr>
              </thead>
              <tbody id="users-table-body"></tbody>
            </table>
          </div>
        </section>

        <section class="settings-group">
          <h4 class="settings-group-title" data-i18n="sessionLimit">Session Limit</h4>
          <label class="settings-row" for="session-limit-mb">
            <span class="settings-row-text">
              <span class="settings-row-label" data-i18n="setSessionLimit">Set session limit</span>
            </span>
            <span class="settings-number-field">
              <input type="number" id="session-limit-mb" min="0" step="1" />
              <span>MB</span>
            </span>
          </label>
        </section>

        <section class="settings-group hidden" id="settings-storage-request">
          <h4 class="settings-group-title" data-i18n="storageRequestSection">Storage</h4>
          <p class="settings-row-text" id="storage-request-summary" style="margin:0;line-height:1.5;"></p>
          <p class="settings-row-text hidden" id="storage-request-pending" style="margin:0;line-height:1.5;color:var(--accent);"></p>
          <div class="settings-actions">
            <button id="btn-request-storage" class="settings-action-btn" type="button">
              <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>
              <span data-i18n="storageRequestButton">Request more storage</span>
            </button>
          </div>
        </section>

        <section class="settings-group">
          <h4 class="settings-group-title" data-i18n="sectionCustomization">Customization</h4>
          <label class="settings-row" for="hide-lang-switcher-toggle">
            <span class="settings-row-text">
              <span class="settings-row-label" data-i18n="hideLanguageSwitcherOnMainPage">Hide language switcher on main page</span>
            </span>
            <span class="settings-toggle">
              <input type="checkbox" id="hide-lang-switcher-toggle" />
              <span class="settings-toggle-slider"></span>
            </span>
          </label>
        </section>

        <section class="settings-group" id="settings-admin-actions">
          <h4 class="settings-group-title" data-i18n="sectionSecurity">Security & Administration</h4>
          <div class="settings-actions">
            <button id="btn-open-token-modal" class="settings-action-btn">
              <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" aria-hidden="true">
                <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path>
              </svg>
              <span data-i18n="manageSyncTokens">Manage Sync Tokens</span>
            </button>
            <button id="btn-manage-storage" class="settings-action-btn hidden" type="button">
              <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M3 5v14a9 3 0 0 0 18 0V5"></path><path d="M3 12a9 3 0 0 0 18 0"></path></svg>
              <span data-i18n="manageStorageButton">Manage user storage</span>
            </button>
          </div>
        </section>
        </div>
        <div class="settings-menu-footer"></div>
      </div>
    </div>
  </div>
</header>

  <main class="layout">
    <aside id="root-toolbar" class="root-toolbar">
      <div class="root-toolbar-top-separator" aria-hidden="true"></div>
       <button class="root-toolbar-btn active" data-root="main" data-i18n-title="folders" title="" style="align-self: flex-start;"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></button>
        <button class="root-toolbar-btn" data-root="archive" data-i18n-title="archiveRoot" title="" style="align-self: flex-start;"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h2"/><path d="M20 8v11a2 2 0 0 1-2 2h-2"/><path d="m9 15 3-3 3 3"/><path d="M12 12v9"/></svg></button>
        <button class="root-toolbar-btn" data-root="temp" data-i18n-title="tempFilesRoot" title="" style="align-self: flex-start;"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 5H3"/><path d="M7 12H3"/><path d="M7 19H3"/><path d="M12 18a5 5 0 0 0 9-3 4.5 4.5 0 0 0-4.5-4.5c-1.33 0-2.54.54-3.41 1.41L11 14"/><path d="M11 10v4h4"/></svg></button>
        <button class="root-toolbar-btn" data-root="documents" data-i18n-title="documentsRoot" title="" style="align-self: flex-start;"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v8l3-3 3 3V2"/><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a1 1 0 0 1 0-5H20"/></svg></button>
        <button class="root-toolbar-btn" data-root="photos" data-i18n-title="photosRoot" title="" style="align-self: flex-start;"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg></button>
        <button class="root-toolbar-btn" data-root="pdf" data-i18n-title="pdfRoot" title="" style="align-self: flex-start;"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7v14"/><path d="M16 12h2"/><path d="M16 8h2"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/><path d="M6 12h2"/><path d="M6 8h2"/></svg></button>
       <button id="btn-root-groups" class="root-toolbar-btn hidden" data-i18n-title="groupsRoot" title="" style="align-self: flex-start;"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></button>
      <div class="root-toolbar-separator" aria-hidden="true"></div>
       <button class="root-toolbar-btn" data-root="trash" data-i18n-title="trashRoot" title=""><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg></button>
      <div class="root-toolbar-spacer" aria-hidden="true"></div>
      <div id="desktop-root-toolbar-footer" class="root-toolbar-footer">
        <div class="root-toolbar-separator" aria-hidden="true"></div>
      </div>
    </aside>
    <aside class="sidebar">
    <div id="desktop-tree-search-host" class="desktop-tree-search-host"></div>
    <div class="section-title">
      <button id="btn-refresh-tree" class="icon-btn" title="Refresh folder list">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-refresh-ccw-dot-icon lucide-refresh-ccw-dot"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/><circle cx="12" cy="12" r="1"/></svg>
      </button>
      <div class="section-title-center">
        <span id="section-title-label" data-i18n="folders">Folders</span>
        <select id="group-section-switcher" class="group-section-switcher hidden" aria-label="Switch group"></select>
      </div>
      <button id="btn-sort-filter" class="icon-btn sort-filter-btn" title="Sort options" style="width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center;">
        <svg 
          width="22" 
          height="22" 
          viewBox="0 0 24 24" 
          stroke="currentColor" 
          stroke-width="1.5" 
          fill="none" 
          stroke-linecap="round" 
          stroke-linejoin="round"
          style="transform: rotate(0deg) translateY(1px);"
        >
          <path d="M4 4h16v2L12 12v6l-4 2v-8L4 6V4z"/>
          <line x1="8" y1="16" x2="12" y2="14"/>
        </svg>
      </button>
    </div>
    <div id="group-switcher-bar" class="hidden" aria-label="Group switcher"></div>
    <div id="tree-browse-bar" class="tree-browse-bar hidden">
      <button type="button" id="btn-tree-back" class="tree-back-btn" data-i18n-title="treeBack" title="Go back">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        <span data-i18n="treeBack">Go back</span>
      </button>
      <div class="tree-browse-folder">
        <span class="tree-browse-folder-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg></span>
        <span id="tree-browse-label" class="tree-browse-label"></span>
      </div>
    </div>
    <div id="tree" class="tree"></div>
    <div id="sort-filter-menu" class="dropdown-menu hidden">
      <div class="dropdown-item sort-option" data-sort="name-asc">
        <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
        <span data-i18n="sortByNameAsc">A → Z</span>
      </div>
      <div class="dropdown-item sort-option" data-sort="name-desc">
        <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
        <span data-i18n="sortByNameDesc">Z → A</span>
      </div>
      <div class="dropdown-divider"></div>
      <div class="dropdown-item sort-option" data-sort="date-asc">
        <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
        <span data-i18n="sortByDateAsc">Oldest first</span>
      </div>
      <div class="dropdown-item sort-option" data-sort="date-desc">
        <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
        <span data-i18n="sortByDateDesc">Newest first</span>
      </div>
      <div class="dropdown-divider"></div>
      <div class="dropdown-item sort-option" data-sort="size-asc">
        <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
        <span data-i18n="sortBySizeAsc">Smallest first</span>
      </div>
      <div class="dropdown-item sort-option" data-sort="size-desc">
        <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
        <span data-i18n="sortBySizeDesc">Largest first</span>
      </div>
    </div>
  </aside>



    <section class="content">
      <div id="file-view" class="file-view">

      <div id="tabs-container" class="tabs-container">
          <button class="tab-strip-btn" id="tab-strip-toggle" type="button" title="Tabs" aria-label="Tabs">
              <svg viewBox="0 0 24 24"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"></path></svg>
          </button>
          <button class="tab-scroll-btn" id="tab-scroll-left" title="Scroll left" style="display: none;">
              <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
          </button>
          <div id="tabs-list" class="tabs-list"></div>
          <button class="tab-scroll-btn" id="tab-scroll-right" title="Scroll right" style="display: none;">
              <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"></polyline></svg>
          </button>
          <div class="controls-right">
              <button id="btn-close-all-tabs" class="icon-btn tab-close-all" title="" data-i18n-title="closeAllTabs">
                  <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                      <line x1="18" y1="6" x2="6" y2="18"></line>
                      <line x1="6" y1="6" x2="18" y2="18"></line>
                  </svg>
              </button>
          </div>
          <div class="tab-search-dropdown" id="tab-search-dropdown">
              <div class="tab-search-section">
                  <div class="tab-search-title" data-i18n="openTabs">Open Tabs</div>
                  <div id="tab-search-open-list"></div>
              </div>
              <div class="tab-search-section">
                  <div class="tab-search-title" data-i18n="recentFiles">Recent files</div>
                  <div id="tab-search-recent-list"></div>
              </div>
          </div>
      </div>


        <div class="file-toolbar">
          <span id="file-name" class="filename"></span>
          <div class="spacer"></div>
          <button id="btn-trash-restore-all" class="btn secondary small hidden" data-i18n="restoreAll"></button>
          <button id="btn-trash-delete-selected" class="btn danger small hidden" data-i18n="deleteSelected"></button>
          <button id="btn-trash-delete-all" class="btn danger small hidden" data-i18n="deleteAll"></button>
          <button id="btn-trash-restore-selected" class="btn primary small hidden" data-i18n="restoreSelected"></button>
          <button id="btn-ai" class="icon-btn" title="AI Assistant">
            <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
              <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
          </button>
          
          <div class="code-actions">
            <button id="btn-code-actions" class="icon-btn" title="Code Actions">
              <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="1"></circle>
                <circle cx="12" cy="5" r="1"></circle>
                <circle cx="12" cy="19" r="1"></circle>
              </svg>
            </button>
            <div id="code-actions-menu" class="dropdown-menu hidden">
                <div class="dropdown-item" onclick="compactCurrentCode()" style="display:flex;align-items:center;gap:8px;">
                  <svg width="15" height="15" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                    <polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/>
                    <line x1="10" y1="14" x2="3" y2="21"/><line x1="21" y1="3" x2="14" y2="10"/>
                  </svg>
                  <span data-i18n="compressCode"></span>
                </div>
                <div class="dropdown-item" onclick="formatCurrentCode()" style="display:flex;align-items:center;gap:8px;">
                  <svg width="15" height="15" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                    <line x1="21" y1="10" x2="7" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/>
                    <line x1="21" y1="14" x2="3" y2="14"/><line x1="21" y1="18" x2="7" y2="18"/>
                  </svg>
                  <span data-i18n="formatCode"></span>
                </div>
                <div class="dropdown-item" onclick="removeCommentsFromCode()" style="display:flex;align-items:center;gap:8px;">
                  <svg width="15" height="15" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                    <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                  </svg>
                  <span data-i18n="removeComments"></span>
                </div>
                <div class="dropdown-item" id="code-action-compile" onclick="compileAndRun()" style="display:flex;align-items:center;gap:8px;">
                  <svg width="15" height="15" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                    <polygon points="5 3 19 12 5 21 5 3"/>
                  </svg>
                  <span data-i18n="compileRun"></span>
                </div>
            </div>
          </div>
            <button id="btn-save" class="icon-btn hidden" title="Save">
              <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
              </svg>
            </button>
            <button id="btn-monaco-undo" class="icon-btn hidden" title="Undo">
              <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 14 4 9l5-5"></path>
                <path d="M4 9h9a7 7 0 1 1 0 14h-1"></path>
              </svg>
            </button>
            <button id="btn-monaco-redo" class="icon-btn hidden" title="Redo">
              <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path d="m15 14 5-5-5-5"></path>
                <path d="M20 9h-9a7 7 0 1 0 0 14h1"></path>
              </svg>
            </button>
            <button id="btn-download" class="icon-btn" title="Download">
              <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
              </svg>
            </button>
            <button id="btn-copy" class="icon-btn" title="Copy">
              <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
              </svg>
            </button>
            <button id="btn-share" class="icon-btn" title="Share">
              <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="18" cy="5" r="3"></circle>
                <circle cx="6" cy="12" r="3"></circle>
                <circle cx="18" cy="19" r="3"></circle>
                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
              </svg>
            </button>

            <button id="btn-fullscreen" class="icon-btn" title="Fullscreen">
              <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>
              </svg>
            </button>
        </div>
        <div id="viewer" class="viewer"></div>
      </div>
    </section>
    <aside id="group-chat-panel" class="group-chat-panel group-chat-panel--docked hidden" aria-label="Group chat">
      <div id="group-chat-resize" class="group-chat-resize" title="Resize"></div>
      <div id="group-chat-resize-h" class="group-chat-resize-h hidden" title="Resize height"></div>
      <header id="group-chat-drag-handle" class="group-chat-header">
        <select id="group-chat-group-select" class="group-chat-group-select" aria-label="Group"></select>
        <span id="group-chat-title" class="group-chat-title" data-i18n="groupChatTitle">Group chat</span>
        <div class="group-chat-header-actions">
          <button type="button" id="group-chat-delete" class="icon-btn group-chat-icon-btn group-chat-delete-btn hidden" title="Delete chat" aria-label="Delete chat">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
          </button>
          <button type="button" id="group-chat-detach" class="icon-btn group-chat-icon-btn" title="Detach" aria-label="Detach">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
          </button>
          <button type="button" id="group-chat-close" class="icon-btn group-chat-icon-btn" aria-label="Close">
            <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
          </button>
        </div>
      </header>
      <div id="group-chat-messages" class="group-chat-messages"></div>
      <div class="group-chat-compose">
        <div id="group-chat-mentions" class="group-chat-mentions hidden"></div>
        <div id="group-chat-mention-suggestions" class="group-chat-mention-suggestions hidden"></div>
        <form class="group-chat-compose-form" autocomplete="off" onsubmit="return false">
          <div class="group-chat-compose-row">
            <input type="file" id="group-chat-file" class="hidden" accept="image/*,video/*,audio/*">
            <button type="button" id="group-chat-upload" class="icon-btn group-chat-compose-btn" title="Upload" aria-label="Upload">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            </button>
            <button type="button" id="group-chat-voice" class="icon-btn group-chat-compose-btn" title="Voice" aria-label="Voice">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
            </button>
            <textarea id="group-chat-input" class="group-chat-input" rows="1" name="group-chat-compose-body" data-i18n-placeholder="groupChatPlaceholder" placeholder="Type a message…" autocomplete="off" autocapitalize="sentences" spellcheck="true" data-lpignore="true" data-1p-ignore data-form-type="other" role="textbox"></textarea>
            <button type="button" id="group-chat-send" class="group-chat-send-btn" data-i18n-title="groupChatSend" title="Send" aria-label="Send">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.714 3.048a.498.498 0 0 0-.683.627l2.843 7.627a2 2 0 0 1 0 1.396l-2.842 7.627a.498.498 0 0 0 .682.627l18-8.5a.5.5 0 0 0 0-.904z"/><path d="M6 12h16"/></svg>
            </button>
          </div>
        </form>
      </div>
    </aside>
  </main>

  <footer class="statusbar">
    <div id="status-left">
      <span id="status-quota"></span>
      <div id="status-editor-slot" class="hidden"></div>
    </div>
    <div id="status-right">
      <span id="status-right-content"></span>
    </div>
    <div id="status-flash" aria-live="polite"></div>
  </footer>

  <div id="group-chat-context-menu" class="group-chat-context-menu" style="display:none">
    <button type="button" id="group-chat-context-edit" data-i18n="groupChatEdit">Edit</button>
    <button type="button" id="group-chat-context-delete" data-i18n="groupChatDelete">Delete</button>
  </div>
  <div id="group-chat-lightbox" class="group-chat-lightbox hidden" role="dialog" aria-modal="true">
    <button type="button" id="group-chat-lightbox-close" class="group-chat-lightbox-close" aria-label="Close">×</button>
    <div id="group-chat-lightbox-inner" class="group-chat-lightbox-inner"></div>
  </div>

  <div id="info-modal" class="modal hidden">
    <div class="modal-content info-modal-tiled">
      <div class="modal-header">
        <h3 data-i18n="aboutTitle">About File Space</h3>
        <button id="btn-close-info" class="icon-btn">
          <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>
      <div class="modal-body info-modal-body">
        <div class="info-tiles-grid">

          <!-- Tile: Overview -->
          <div class="info-tile" data-tile="overview">
            <div class="info-tile-header" role="button" tabindex="0">
              <svg class="info-tile-icon" width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
              <span class="info-tile-title" data-i18n="sectionOverview">Overview</span>
              <svg class="info-tile-chevron" width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="info-tile-content">
              <p data-i18n="overviewText">File Space is a multi-user cloud file management and collaboration platform with secure storage, advanced editors, AI assistant, code compilation, file conversion, desktop sync, and support for dozens of file formats — all in a modern web interface with customizable themes.</p>
            </div>
          </div>

          <!-- Tile: File Management -->
          <div class="info-tile" data-tile="filemgmt">
            <div class="info-tile-header" role="button" tabindex="0">
              <svg class="info-tile-icon" width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
              <span class="info-tile-title" data-i18n="sectionFileManagement">File Management</span>
              <svg class="info-tile-chevron" width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="info-tile-content">
              <ul>
                <li data-i18n="featMultiUser">Multi-user file management with per-user storage quotas and session limits</li>
                <li data-i18n="featTreeNav">Hierarchical folder tree navigation with expand/collapse</li>
                <li data-i18n="featCreateFolders">Create, rename, move, and delete folders</li>
                <li data-i18n="featCreateFiles">Create new text files directly from the interface</li>
                <li data-i18n="featDragDrop">Drag &amp; drop file upload with progress tracking and resume support</li>
                <li data-i18n="featBulkUpload">Bulk file and folder upload (files and directory structures)</li>
                <li data-i18n="featDownload">Single and batch file download</li>
                <li data-i18n="featMultiSelect">Multi-select with batch download as ZIP archive</li>
                <li data-i18n="featContextMenu">Context menu with file operations (open, edit, rename, delete, move, copy, share, properties)</li>
                <li data-i18n="featFileMove">Move files and folders between directories with confirmation dialog</li>
                <li data-i18n="featFileCopy">Copy file content to clipboard</li>
                <li data-i18n="featFileSharing">File sharing via shareable links with QR code generation</li>
                <li data-i18n="featRootFolders">Six root workspaces: Folders, Archive, Temporary Files, Documents (encrypted), Photos, PDF</li>
                <li data-i18n="featEncryptedDocs">AES-encrypted Documents folder — files encrypted server-side, accessible only by owner</li>
              </ul>
            </div>
          </div>

          <!-- Tile: Editors -->
          <div class="info-tile" data-tile="editors">
            <div class="info-tile-header" role="button" tabindex="0">
              <svg class="info-tile-icon" width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
              <span class="info-tile-title" data-i18n="sectionEditors">Editors</span>
              <svg class="info-tile-chevron" width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="info-tile-content">
              <ul>
                <li data-i18n="featMonacoEditor">Monaco Editor (VS Code engine) with syntax highlighting, IntelliSense, code folding, minimap, multi-cursor, find/replace with regex</li>
                <li data-i18n="featCodeMirror">CodeMirror editor with 25+ language modes (JS, TS, Python, PHP, HTML, CSS, SQL, YAML, TOML, Shell, Ruby, Go, Swift, Kotlin, Lua, Perl, R, Vue, Octave, LaTeX)</li>
                <li data-i18n="featAutoSave">Auto-save with configurable interval</li>
                <li data-i18n="featTabbedEditing">Tabbed editing — up to 10 tabs on desktop, 4 on mobile</li>
                <li data-i18n="featLatexEditor">LaTeX editor with live PDF preview, template library, symbol palette, document structure insertions</li>
                <li data-i18n="featLatexCompile">LaTeX compilation with Ctrl+Enter</li>
                <li data-i18n="featMarkdownEditor">Markdown editor with live rendered preview</li>
                <li data-i18n="featPhotoEditor">Photo editor: crop, rotate, draw (brush, shapes, text), filters, brightness/contrast/saturation, undo</li>
              </ul>
            </div>
          </div>

          <!-- Tile: Viewers -->
          <div class="info-tile" data-tile="viewers">
            <div class="info-tile-header" role="button" tabindex="0">
              <svg class="info-tile-icon" width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <span class="info-tile-title" data-i18n="sectionViewers">Viewers</span>
              <svg class="info-tile-chevron" width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="info-tile-content">
              <ul>
                <li data-i18n="featPdfViewer">PDF viewer with page navigation, zoom, annotation/drawing tools</li>
                <li data-i18n="featOfficeViewers">Microsoft Word (.doc, .docx), Excel (.xls, .xlsx), PowerPoint (.ppt, .pptx) rendered as HTML</li>
                <li data-i18n="featOnlyOffice">ONLYOFFICE integration for collaborative document editing</li>
                <li data-i18n="featImageViewer">Image viewer with EXIF metadata (camera model, exposure, GPS)</li>
                <li data-i18n="featVideoPlayer">Video player (MP4, WebM, OGG, MOV, AVI, MKV) with Plyr controls</li>
                <li data-i18n="featAudioPlayer">Audio player (MP3, WAV, OGG, M4A, FLAC) with playback controls</li>
                <li data-i18n="feat3dViewer">3D model viewer (OBJ, STL, FBX, 3MF, GLTF, GLB) powered by Three.js — rotate, zoom, wireframe/solid</li>
                <li data-i18n="featZipViewer">ZIP/RAR archive browser with file listing, content preview, individual file extraction</li>
                <li data-i18n="featTranscriptionEditor">Audio transcription editor — upload audio, get AI-generated text transcript</li>
              </ul>
            </div>
          </div>

          <!-- Tile: Code Tools -->
          <div class="info-tile" data-tile="codetools">
            <div class="info-tile-header" role="button" tabindex="0">
              <svg class="info-tile-icon" width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>
              <span class="info-tile-title" data-i18n="sectionCodeTools">Code Tools</span>
              <svg class="info-tile-chevron" width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="info-tile-content">
              <ul>
                <li data-i18n="featCompileRun">Online code compilation and execution for 20+ languages (C, C++, Java, Python, PHP, Ruby, Go, Swift, Kotlin, JS, TS, Rust, Scala, Perl, Haskell, Lua, R, Octave)</li>
                <li data-i18n="featCompileOutput">Compilation output terminal with stdout/stderr, execution time, memory usage</li>
                <li data-i18n="featProgramInput">Program input support for interactive applications</li>
                <li data-i18n="featCodeFormat">Code formatting for JavaScript, Python, PHP, JSON, HTML, CSS</li>
                <li data-i18n="featCodeCompress">Code compression/minification</li>
                <li data-i18n="featRemoveComments">Comment removal from code</li>
                <li data-i18n="featCodeActions">Code Actions menu: Format, Compress, Remove Comments, Fix Problems, Calculate Data</li>
              </ul>
            </div>
          </div>

          <!-- Tile: AI Assistant -->
          <div class="info-tile" data-tile="ai">
            <div class="info-tile-header" role="button" tabindex="0">
              <svg class="info-tile-icon" width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              <span class="info-tile-title" data-i18n="sectionAI">AI Assistant</span>
              <svg class="info-tile-chevron" width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="info-tile-content">
              <ul>
                <li data-i18n="featAIChat">AI chat assistant via LLM (Llama, Qwen, or custom model through LM Studio / OpenAI-compatible API)</li>
                <li data-i18n="featAIContext">File-aware AI — sends current file content as context</li>
                <li data-i18n="featAIRoles">AI roles: General Assistant, Programmer, Search Assistant</li>
                <li data-i18n="featAIActions">Quick actions: Explain Code, Optimize Code, Generate Tests, Code Review, Fix Problems, Calculate Data</li>
                <li data-i18n="featAIReplace">Apply AI-generated code to file with "Replace File Content" confirmation</li>
                <li data-i18n="featAIHistory">Persistent chat history in SQLite database with pagination</li>
                <li data-i18n="featAIClearHistory">Clear chat history function</li>
                <li data-i18n="featAIConfig">Configurable AI endpoint, API key, model selection</li>
                <li data-i18n="featAITokenStreaming">Token-by-token streaming with typing animation</li>
              </ul>
            </div>
          </div>

          <!-- Tile: Search -->
          <div class="info-tile" data-tile="search">
            <div class="info-tile-header" role="button" tabindex="0">
              <svg class="info-tile-icon" width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <span class="info-tile-title" data-i18n="sectionSearch">Search</span>
              <svg class="info-tile-chevron" width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="info-tile-content">
              <ul>
                <li data-i18n="featQuickSearch">Quick search in top bar with instant tree filtering</li>
                <li data-i18n="featAdvancedSearch">Advanced Search: name, extension, type (documents/images/audio/video/archives/code/3D), size range, modification date</li>
                <li data-i18n="featInFileSearch">In-file text search with match highlighting and prev/next navigation</li>
                <li data-i18n="featSearchOptions">Search options: match case, whole word, regular expressions</li>
              </ul>
            </div>
          </div>

          <!-- Tile: Conversion -->
          <div class="info-tile" data-tile="conversion">
            <div class="info-tile-header" role="button" tabindex="0">
              <svg class="info-tile-icon" width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/><line x1="4" y1="4" x2="9" y2="9"/></svg>
              <span class="info-tile-title" data-i18n="sectionConversion">File Conversion</span>
              <svg class="info-tile-chevron" width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="info-tile-content">
              <ul>
                <li data-i18n="featDocConversion">Documents: DOCX ↔ HTML, XLSX ↔ HTML, PPTX → HTML (PhpOffice)</li>
                <li data-i18n="featImageConversion">Images: PNG, JPG, WEBP, BMP, GIF (via GD/Imagick)</li>
                <li data-i18n="featConvertDialog">Target format selection, output folder choice, optional original deletion</li>
                <li data-i18n="featConvertCopy">Convert and create copy (keeps original)</li>
                <li data-i18n="featConvertReplace">Convert and replace original</li>
              </ul>
            </div>
          </div>

          <!-- Tile: Sync -->
          <div class="info-tile" data-tile="sync">
            <div class="info-tile-header" role="button" tabindex="0">
              <svg class="info-tile-icon" width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
              <span class="info-tile-title" data-i18n="sectionSync">Synchronization</span>
              <svg class="info-tile-chevron" width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="info-tile-content">
              <ul>
                <li data-i18n="featSyncDesktop">Desktop sync client — synchronize local folders with File Space</li>
                <li data-i18n="featSyncTokens">Token-based auth for sync clients (180-day expiry, manageable via Settings)</li>
                <li data-i18n="featSyncLog">Sync log viewer — tracks upload/download/delete with timestamps and status levels (INFO, WARNING, ERROR)</li>
                <li data-i18n="featSyncConflict">Conflict resolution — server wins if file modified after last sync</li>
                <li data-i18n="featSyncSelective">Per-root selective sync</li>
              </ul>
            </div>
          </div>

          <!-- Tile: Customization -->
          <div class="info-tile" data-tile="customization">
            <div class="info-tile-header" role="button" tabindex="0">
              <svg class="info-tile-icon" width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82 2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82-.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.26.604.852.997 1.51 1H21a2 2 0 0 1 0 4h-.09c-.658.003-1.25.396-1.51 1z"/></svg>
              <span class="info-tile-title" data-i18n="sectionCustomization">Customization</span>
              <svg class="info-tile-chevron" width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="info-tile-content">
              <ul>
                <li data-i18n="featThemes">Dark and Light themes (?theme=dark/light or Settings)</li>
                <li data-i18n="featAccentColor">Custom accent color (?color=#hex or Settings color picker)</li>
                <li data-i18n="featI18n">Multi-language interface: English, Russian, Ukrainian, Belarusian</li>
                <li data-i18n="featLangSwitcher">Language switcher in top bar</li>
                <li data-i18n="featConstellationBG">Animated constellation background</li>
                <li data-i18n="featEditorSettings">Per-user editor settings: font size, tab size, word wrap, line numbers, auto-completion</li>
                <li data-i18n="featHideLangSwitcher">Option to hide language switcher (per-user)</li>
              </ul>
            </div>
          </div>

          <!-- Tile: Security -->
          <div class="info-tile" data-tile="security">
            <div class="info-tile-header" role="button" tabindex="0">
              <svg class="info-tile-icon" width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <span class="info-tile-title" data-i18n="sectionSecurity">Security</span>
              <svg class="info-tile-chevron" width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="info-tile-content">
              <ul>
                <li data-i18n="featAuth">Session-based authentication with 12-hour timeout</li>
                <li data-i18n="featQuotas">Per-user storage quotas (MB) and upload session limits (MB)</li>
                <li data-i18n="featUserRole">User role display (Guest, User, Manager, Admin)</li>
                <li data-i18n="featStorageStats">Storage usage statistics — breakdown by top-level folder</li>
                <li data-i18n="featClientAuth">Client auth tokens for API access (device tracking, 365-day expiry)</li>
                <li data-i18n="featEncryptedStorage">Server-side AES encryption for Documents root</li>
                <li data-i18n="featFileProps">File properties: name, path, size, type, dates, attributes, dimensions, EXIF</li>
                <li data-i18n="featFolderProps">Folder properties: total size (recursive), file/subfolder count, content listing</li>
              </ul>
            </div>
          </div>

          <!-- Tile: Formats -->
          <div class="info-tile" data-tile="formats">
            <div class="info-tile-header" role="button" tabindex="0">
              <svg class="info-tile-icon" width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              <span class="info-tile-title" data-i18n="supportedFormatsTitle">Supported Formats</span>
              <svg class="info-tile-chevron" width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="info-tile-content">
              <div class="format-category-grid">
                <section class="format-card">
                  <h4 class="format-card-title" data-i18n="fmtCode">Code and text</h4>
                  <div class="format-card-tags">
                    <span class="format-tag">.txt</span><span class="format-tag">.md</span><span class="format-tag">.markdown</span>
                    <span class="format-tag">.js</span><span class="format-tag">.jsx</span><span class="format-tag">.ts</span><span class="format-tag">.tsx</span>
                    <span class="format-tag">.html</span><span class="format-tag">.css</span><span class="format-tag">.php</span><span class="format-tag">.py</span>
                    <span class="format-tag">.java</span><span class="format-tag">.c</span><span class="format-tag">.cpp</span><span class="format-tag">.h</span><span class="format-tag">.hpp</span>
                    <span class="format-tag">.cs</span><span class="format-tag">.go</span><span class="format-tag">.rs</span><span class="format-tag">.rb</span>
                    <span class="format-tag">.swift</span><span class="format-tag">.kt</span><span class="format-tag">.sql</span><span class="format-tag">.json</span>
                    <span class="format-tag">.xml</span><span class="format-tag">.yaml</span><span class="format-tag">.yml</span><span class="format-tag">.sh</span>
                    <span class="format-tag">.tex</span><span class="format-tag">.m</span><span class="format-tag">.pl</span><span class="format-tag">.lua</span><span class="format-tag">.r</span>
                  </div>
                </section>
                <section class="format-card">
                  <h4 class="format-card-title" data-i18n="fmtDocuments">Documents</h4>
                  <div class="format-card-tags">
                    <span class="format-tag">.pdf</span><span class="format-tag">.doc</span><span class="format-tag">.docx</span><span class="format-tag">.odt</span>
                    <span class="format-tag">.rtf</span><span class="format-tag">.txt</span><span class="format-tag">.xls</span><span class="format-tag">.xlsx</span>
                    <span class="format-tag">.ods</span><span class="format-tag">.csv</span><span class="format-tag">.ppt</span><span class="format-tag">.pptx</span><span class="format-tag">.odp</span>
                  </div>
                </section>
                <section class="format-card">
                  <h4 class="format-card-title" data-i18n="fmtImages">Images</h4>
                  <div class="format-card-tags">
                    <span class="format-tag">.jpg</span><span class="format-tag">.jpeg</span><span class="format-tag">.png</span><span class="format-tag">.webp</span>
                    <span class="format-tag">.bmp</span><span class="format-tag">.gif</span><span class="format-tag">.svg</span><span class="format-tag">.ico</span>
                    <span class="format-tag">.tif</span><span class="format-tag">.tiff</span>
                  </div>
                </section>
                <section class="format-card">
                  <h4 class="format-card-title" data-i18n="fmtVideo">Video</h4>
                  <div class="format-card-tags">
                    <span class="format-tag">.mp4</span><span class="format-tag">.webm</span><span class="format-tag">.ogg</span>
                    <span class="format-tag">.mov</span><span class="format-tag">.avi</span><span class="format-tag">.mkv</span>
                  </div>
                </section>
                <section class="format-card">
                  <h4 class="format-card-title" data-i18n="fmtAudio">Audio</h4>
                  <div class="format-card-tags">
                    <span class="format-tag">.mp3</span><span class="format-tag">.wav</span><span class="format-tag">.ogg</span>
                    <span class="format-tag">.m4a</span><span class="format-tag">.flac</span><span class="format-tag">.aac</span>
                  </div>
                </section>
                <section class="format-card">
                  <h4 class="format-card-title" data-i18n="fmt3d">3D Models</h4>
                  <div class="format-card-tags">
                    <span class="format-tag">.obj</span><span class="format-tag">.stl</span>
                  </div>
                </section>
                <section class="format-card">
                  <h4 class="format-card-title" data-i18n="fmtArchives">Archives</h4>
                  <div class="format-card-tags">
                    <span class="format-tag">.zip</span><span class="format-tag">.rar</span>
                  </div>
                </section>
              </div>
            </div>
          </div>

          <!-- Tile: Hotkeys -->
          <div class="info-tile" data-tile="hotkeys">
            <div class="info-tile-header" role="button" tabindex="0">
              <svg class="info-tile-icon" width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M6 8h.01M10 8h.01M14 8h.01M18 8h.01M6 12h.01M10 12h.01M14 12h.01M18 12h.01M8 16h8"/></svg>
              <span class="info-tile-title" data-i18n="hotkeysTitle">Keyboard Shortcuts</span>
              <svg class="info-tile-chevron" width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="info-tile-content">
              <ul class="hotkeys-list">
                <li><kbd>Ctrl+S</kbd> <span data-i18n="hotkey1">Save file</span></li>
                <li><kbd>Ctrl+C</kbd> <span data-i18n="hotkey2">Copy content</span></li>
                <li><kbd>Ctrl+Shift+A</kbd> <span data-i18n="hotkey3">Open AI Assistant</span></li>
                <li><kbd>Esc</kbd> <span data-i18n="hotkey4">Close modal / viewer</span></li>
                <li><kbd>Delete</kbd> <span data-i18n="hotkey5">Delete selected file/folder</span></li>
                <li><kbd>Ctrl+/</kbd> <span data-i18n="hotkey6">Toggle comment</span></li>
                <li><kbd>Ctrl+D</kbd> <span data-i18n="hotkey7">Delete line</span></li>
                <li><kbd>Ctrl+L</kbd> <span data-i18n="hotkey8">Select line</span></li>
                <li><kbd>Ctrl+F</kbd> <span data-i18n="hotkey11">Search in file</span></li>
                <li><kbd>Ctrl+Enter</kbd> <span data-i18n="hotkey12">Compile LaTeX</span></li>
                <li><kbd>Ctrl+Z</kbd> / <kbd>Ctrl+Shift+Z</kbd> <span data-i18n="hotkey13">Undo / Redo</span></li>
              </ul>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>






  <!-- Modal for file sharing -->
<div id="share-modal" class="modal hidden">
  <div class="modal-content" style="max-width: 560px; overflow:hidden;">
    <div class="modal-header" style="padding-right:52px; margin:0 -6px; border-bottom:1px solid var(--border);">
      <h3 data-i18n="shareFile">Share File</h3>
      <button id="btn-close-share" class="icon-btn" style="position:absolute; top:10px; right:10px; z-index:2;">
        <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
      </button>
    </div>
    <div class="modal-body" style="text-align: center; padding: 20px; position:relative;">
      <div id="share-link-container" style="margin: 15px 0; word-break: break-all; background: var(--panel); padding: 12px; border-radius: 6px; font-family: monospace;">
        <a id="share-link" href="#" target="_blank"></a>
      </div>
      <div id="qrcode-container" style="margin: 20px auto; display: inline-block;"></div>
    </div>
    <div id="share-modal-actions" class="modal-footer" style="display:flex; align-items:center; justify-content:center; gap:10px; flex-wrap:nowrap;">
        <button id="btn-share-settings" class="btn secondary" type="button" style="display:inline-flex; align-items:center; gap:8px; height:38px;">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings2-icon lucide-settings-2"><path d="M14 17H5"/><path d="M19 7h-9"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/></svg>
          <span data-i18n="shareLinkSettings">Link settings</span>
        </button>
        <button id="btn-copy-share-link" class="btn secondary" style="display:inline-flex; align-items:center; gap:8px; height:38px;">
        <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
            <path d="M8 7H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2v-3"></path>
            <path d="M16 3h5v5"></path>
            <path d="M21 3l-7.5 7.5"></path>
        </svg>
        <span data-i18n="shareCopyButton">Copy link</span>
        </button>
      </div>
  </div>
</div>
<div id="share-settings-modal" class="modal hidden">
  <div class="modal-content" style="max-width: 520px;">
    <div class="modal-header">
      <h3>Share settings</h3>
      <button id="btn-close-share-settings" class="icon-btn">
        <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
      </button>
    </div>
    <div class="modal-body" style="padding:18px;">
      <div style="display:grid; gap:10px;">
        <div style="margin: 0 0 10px; text-align: left;">
          <label for="share-expires-select" data-i18n="shareExpiryLabel" style="display:block; margin-bottom: 6px; font-size: 13px; color: var(--muted);">Link expiration</label>
          <select id="share-expires-select" style="width:100%;">
            <option value="never" data-i18n="shareExpiryNever">Never</option>
            <option value="1h" data-i18n="shareExpiry1h">1 hour</option>
            <option value="1d" data-i18n="shareExpiry1d">1 day</option>
            <option value="1w" data-i18n="shareExpiry1w">1 week</option>
            <option value="1m" data-i18n="shareExpiry1m">1 month</option>
            <option value="3m" data-i18n="shareExpiry3m">3 months</option>
            <option value="6m" data-i18n="shareExpiry6m">Half a year</option>
            <option value="1y" data-i18n="shareExpiry1y">1 year</option>
          </select>
          <div id="share-expires-at" style="margin-top: 8px; font-size: 12px; color: var(--muted);"></div>
        </div>
        <div style="font-size:13px; color:var(--muted);" data-i18n="shareAccessMode">Access mode</div>
        <label style="display:flex; align-items:center; gap:8px; font-size:13px;"><input type="radio" name="share-access-mode" id="share-access-none" value="none" checked><span data-i18n="share-access-none"></span></label>
        <label style="display:flex; align-items:center; gap:8px; font-size:13px;"><input type="radio" name="share-access-mode" id="share-access-view-only" value="view_only"><span data-i18n="share-access-view-only"></span></label>
        <label style="display:flex; align-items:center; gap:8px; font-size:13px;"><input type="radio" name="share-access-mode" id="share-access-disable-download" value="disable_download"><span data-i18n="share-access-disable-download"></span></label>
        <label style="display:flex; align-items:center; gap:8px; font-size:13px;"><input type="radio" name="share-access-mode" id="share-access-disable-copy" value="disable_copy"><span data-i18n="share-access-disable-copy"></span></label>
        <label style="display:block; font-size:13px;">
          <span style="display:block; margin:4px 0 6px;" data-i18n="password"></span>
          <div style="position:relative;">
             <input id="share-password" type="password" placeholder="Optional password" data-i18n-placeholder="password_optional" autocomplete="new-password" style="width:100%;padding-right:44px;">
            <button id="btn-toggle-share-password" type="button" class="icon-btn" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);width:30px;height:30px;">
              <svg id="share-password-eye-open" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
              <svg id="share-password-eye-closed" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="m3 3 18 18"/><path d="M10.58 10.58a2 2 0 1 0 2.83 2.83"/><path d="M16.68 16.67A10.94 10.94 0 0 1 12 17.75c-5 0-9.27-3.11-11-7.5a11.8 11.8 0 0 1 5.17-5.94"/><path d="M9.88 5.09A10.94 10.94 0 0 1 12 4.75c5 0 9.27 3.11 11 7.5a11.8 11.8 0 0 1-1.39 2.5"/></svg>
            </button>
          </div>
        </label>
        <label style="display:block; font-size:13px;">
          <span style="display:block; margin:4px 0 6px;" data-i18n="share-open-limit"></span>
          <input id="share-open-limit" type="number" min="0" step="1" value="0" style="width:100%;">
        </label>
      </div>
    </div>
  </div>
</div>






<div id="storage-usage-modal" class="modal hidden">
  <div class="modal-content storage-usage-modal-content">
    <div class="modal-header">
      <h3 data-i18n="storageUsageTitle">Storage usage by folders</h3>
      <button id="btn-close-storage-usage" class="icon-btn" data-i18n-title="close">
        <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
    <div class="modal-body storage-usage-modal-body">
      <div class="storage-usage-toolbar">
        <div class="storage-usage-meta">
          <span id="storage-usage-source"></span>
          <span id="storage-usage-updated"></span>
        </div>
        <button id="btn-refresh-storage-usage" class="btn primary small" data-i18n="storageUsageRefresh">Refresh by scanning disk</button>
      </div>
      <div id="storage-usage-loading" class="storage-usage-loading hidden" data-i18n="storageUsageLoading">Loading storage usage...</div>
      <div id="storage-usage-empty" class="storage-usage-empty hidden" data-i18n="storageUsageNoData">No data to display.</div>
      <div id="storage-usage-treemap" class="storage-usage-treemap"></div>
      <div id="storage-usage-hover" class="storage-usage-hover">
        <div class="storage-usage-hover-title" data-i18n="storageUsageHoverHint">Hover over a block to see folder details</div>
      </div>
    </div>
  </div>
</div>
<div id="sync-log-modal" class="modal hidden">
  <div class="modal-content sync-log-modal-content">
    <div class="modal-header">
      <h3 data-i18n="syncLogTitle">Sync log</h3>
      <button id="btn-close-sync-log" class="icon-btn" data-i18n-title="close">
        <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
      </button>
    </div>
    <div class="modal-body">
      <div class="sync-log-table-wrap">
        <table class="sync-log-table">
          <thead><tr><th data-i18n="syncLogTime">Time</th><th data-i18n="syncLogLevel">Level</th><th data-i18n="syncLogRoot">Root</th><th data-i18n="syncLogAction">Action</th><th data-i18n="syncLogPath">Path</th><th data-i18n="syncLogMessage">Message</th></tr></thead>
          <tbody id="sync-log-body"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>


  <!-- Modal for viewing compact/format results -->
<div id="code-modal" class="modal hidden">
  <div class="modal-content code-modal-content">
    <div class="modal-header">
      <h3 id="code-modal-title" data-i18n="codeProcessing">Code Processing</h3>
      <button id="btn-close-code-modal" class="icon-btn">
        <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
    <div class="modal-body code-modal-body">
      <div id="code-modal-editor" class="code-modal-editor"></div>
    </div>
    <div class="modal-footer">
      <button id="btn-apply-code-changes" class="btn primary" data-i18n="replaceFileContent">Replace File Content</button>
    </div>
  </div>
</div>

<!-- Add after the existing code-modal in index.php -->
<div id="compile-modal" class="modal hidden">
  <div class="modal-content compile-modal-content" style="max-width: 800px; width: 90%; height: auto; max-height: 90vh;">
    <div class="modal-header">
      <h3 data-i18n="compileOutput">Compile & Run Output</h3>
      <div style="display: flex; align-items: center; gap: 15px;">
        <div id="compile-timer" class="compile-timer" style="
          font-family: 'Courier New', monospace;
          font-size: 16px;
          font-weight: 600;
          color: var(--accent);
          background: var(--bg);
          padding: 4px 10px;
          border-radius: 20px;
          border: 1px solid var(--border);
          display: flex;
          align-items: center;
          gap: 5px;
        ">
          <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
            <circle cx="12" cy="12" r="10"/>
            <polyline points="12 6 12 12 16 14"/>
          </svg>
          <span id="timer-display">00:00.0</span>
        </div>
        <button id="btn-minimize-compile-modal" class="icon-btn" data-i18n-title="minimize" title="Minimize">
          <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14"></path>
          </svg>
        </button>
        <button id="btn-close-compile-modal" class="icon-btn" data-i18n-title="close">
          <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>
    </div>
    <div class="modal-body compile-modal-body" style="display: flex; flex-direction: column; gap: 10px; min-height: 400px;">
      <div id="compile-terminal" class="compile-terminal" style="
        flex: 1; 
        min-height: 300px;
        overflow-x: hidden;
        overflow-y: auto;
        word-wrap: break-word;
        white-space: pre-wrap;
        word-break: break-word;
      "></div>
      <div class="compile-input" style="margin-top: 10px;">
        <div style="display: flex; gap: 10px; align-items: center;">
          <textarea 
            id="compile-input-text" 
            data-i18n-placeholder="enterProgramInput"
            placeholder="Enter program input (optional)" 
            style="
              flex: 1; 
              padding: 8px 12px; 
              background: var(--bg); 
              color: var(--fg); 
              border: 1px solid var(--border); 
              border-radius: 4px; 
              font-family: inherit; 
              resize: vertical; 
              min-height: 40px; 
              max-height: 120px;
            "
          ></textarea>
          <button id="btn-compile-send" class="btn primary" data-i18n="compileAndRun">Compile & Run</button>
        </div>
      </div>
    </div>
  </div>
</div>



<!-- Modal for delete confirmation -->
<div id="delete-modal" class="modal hidden">
  <div class="modal-content" style="max-width: 500px;">
    <div class="modal-header">
      <h3 id="delete-modal-title" data-i18n="deleteConfirmation">Delete confirmation</h3>
      <button class="icon-btn close-btn" id="btn-close-delete-modal">&times;</button>
    </div>
    <div class="modal-body">
      <div style="display: flex; align-items: center; margin-bottom: 15px;">
        <div style="margin-right: 12px; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: rgba(255, 100, 100, 0.1); border-radius: 50%;">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #ff6b6b;">
            <path d="M10 11v6"/>
            <path d="M14 11v6"/>
            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>
            <path d="M3 6h18"/>
            <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
          </svg>
        </div>
        <div>
          <p id="delete-confirmation-text"></p>
          <label id="delete-permanent-option" class="animated-check-label" style="margin-top:12px;">
            <input type="checkbox" id="delete-permanently-checkbox" class="animated-checkbox" />
            <span class="animated-checkbox-box">
              <svg width="24" height="18" viewBox="0 0 24 18" fill="none">
                <path d="M2 9L8.5 15.5L22 2" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </span>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
            <span data-i18n="deletePermanently">Delete permanently</span>
          </label>
          <p id="delete-warning-text" style="color: var(--muted); font-size: 14px; margin-top: 8px;" data-i18n="permanentDeleteWarning">
            This action cannot be undone.
          </p>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button id="btn-cancel-delete" class="btn secondary" data-i18n="btnCancel">Cancel</button>
      <button id="btn-confirm-delete" class="btn danger" data-i18n="btnDelete">Delete</button>
    </div>
  </div>
</div>

<!-- Modal for creating a file -->
<div id="new-file-modal" class="modal hidden">
  <div class="modal-content" style="max-width: 500px;">
    <div class="modal-header">
      <h3>
        <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
        </svg>
        <span data-i18n="createNewFile">Create New File</span>
      </h3>
      <button class="icon-btn close-btn">&times;</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label data-i18n="fileName">File Name</label>
        <div class="filename-input-container" style="display: flex; gap: 10px;">
          <input type="text" id="new-file-name" class="filename-input" placeholder="Enter file name..." style="flex: 1;">
          <select id="file-extension-select" style="width: 120px;">
            <option value="">.txt</option>
            <!-- Office Documents -->
            <option value=".docx" data-color="#2b579a">.docx</option>
            <option value=".doc" data-color="#2b579a">.doc</option>
            
            <!-- Spreadsheets -->
            <option value=".xlsx" data-color="#217346">.xlsx</option>
            <option value=".xls" data-color="#217346">.xls</option>
            <option value=".csv" data-color="#217346">.csv</option>
            
            <!-- Presentations -->
            <option value=".pptx" data-color="#d24726">.pptx</option>
            <option value=".ppt" data-color="#d24726">.ppt</option>
            
            <!-- Existing formats... -->
            <option value=".js">.js</option>
            <option value=".html">.html</option>
            <option value=".css">.css</option>
            <option value=".php">.php</option>
            <option value=".py">.py</option>
            <option value=".json">.json</option>
            <option value=".md">.md</option>
            <option value=".xml">.xml</option>
            <option value=".sql">.sql</option>
            <option value=".java">.java</option>
            <option value=".cpp">.cpp</option>
            <option value=".c">.c</option>
            <option value=".m">.m</option>
            <option value=".md">.md</option>
            <option value=".yaml">.yaml</option>
            <option value=".yml">.yml</option>
            <option value=".ini">.ini</option>
            <option value=".sh">.sh</option>
            <option value=".bat">.bat</option>
            <option value=".ps1">.ps1</option>
            <option value=".vue">.vue</option>
            <option value=".ts">.ts</option>
            <option value=".jsx">.jsx</option>
            <option value=".tsx">.tsx</option>
            <option value=".tex">.tex</option>
            <option value="custom">Custom</option>
          </select>
        </div>
        <input type="text" id="custom-extension-input" class="hidden" placeholder="Enter custom extension..." style="margin-top: 8px;">
      </div>
      <div class="supported-extensions" style="margin-top: 15px; padding: 10px; background: var(--panel); border-radius: 4px;">
        <strong data-i18n="supportedFormats">Supported Formats</strong>
        <div style="margin-top: 8px; font-size: 12px; line-height: 1.4;">
          <span class="format-tag">.txt</span>
          <span class="format-tag">.js</span>
          <span class="format-tag">.html</span>
          <span class="format-tag">.css</span>
          <span class="format-tag">.php</span>
          <span class="format-tag">.py</span>
          <span class="format-tag">.json</span>
          <span class="format-tag">.md</span>
          <span class="format-tag">.m</span>
          <span class="format-tag">.java</span>
          <span class="format-tag">.cpp</span>
          <span class="format-tag">and others...</span>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button id="btn-cancel-new-file" class="btn secondary" data-i18n="cancel">Cancel</button>
      <button id="btn-create-file" class="btn primary" data-i18n="create">Create</button>
    </div>
  </div>
</div>

<!-- Modal for renaming -->
<div id="rename-modal" class="modal hidden">
  <div class="modal-content" style="max-width: 500px;">
    <div class="modal-header">
      <h3>
        <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;">
          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
          <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
        </svg>
        <span data-i18n="renameFile">Rename File</span>
      </h3>
      <button class="icon-btn close-btn">&times;</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label data-i18n="newName">New Name</label>
        <div class="filename-input-container" style="display: flex; gap: 10px;">
          <input type="text" id="rename-file-name" class="filename-input" style="flex: 1;">
          <select id="rename-extension-select" style="width: 120px;">
            <option value="keep">Keep current</option>
            <option value=".txt" selected>.txt</option>
            <option value=".js">.js</option>
            <option value=".html">.html</option>
            <option value=".css">.css</option>
            <option value=".php">.php</option>
            <option value=".py">.py</option>
            <option value=".json">.json</option>
            <option value=".md">.md</option>
            <option value=".xml">.xml</option>
            <option value=".sql">.sql</option>
            <option value=".java">.java</option>
            <option value=".cpp">.cpp</option>
            <option value=".c">.c</option>
            <option value=".m">.m</option>
            <option value=".md">.md</option>
            <option value=".yaml">.yaml</option>
            <option value=".yml">.yml</option>
            <option value=".ini">.ini</option>
            <option value=".sh">.sh</option>
            <option value=".bat">.bat</option>
            <option value=".ps1">.ps1</option>
            <option value=".vue">.vue</option>
            <option value=".ts">.ts</option>
            <option value=".jsx">.jsx</option>
            <option value=".tsx">.tsx</option>
            <option value=".tex">.tex</option>
            <option value="custom">Custom</option>
          </select>
        </div>
        <input type="text" id="rename-custom-extension-input" class="hidden" placeholder="Enter custom extension..." style="margin-top: 8px;">
      </div>
      <div class="file-info" style="margin-top: 10px; padding: 8px; background: var(--panel); border-radius: 4px; font-size: 12px;">
        <span data-i18n="currentFile">Current file:</span> <span id="current-file-name"></span>
      </div>
    </div>
    <div class="modal-footer">
      <button id="btn-cancel-rename" class="btn secondary" data-i18n="cancel">Cancel</button>
      <button id="btn-confirm-rename" class="btn primary" data-i18n="rename">Rename</button>
    </div>
  </div>
</div>






<!-- Modal for creating a folder -->
<div id="new-folder-modal" class="modal hidden">
  <div class="modal-content" style="max-width: 500px;">
    <div class="modal-header">
      <h3>
        <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;">
          <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
        </svg>
        <span data-i18n="createNewFolder">Create New Folder</span>
      </h3>
      <button class="icon-btn close-btn">&times;</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label data-i18n="folderName">Folder Name</label>
        <input type="text" id="new-folder-name" placeholder="Enter folder name..." style="width: 100%;">
      </div>
      <div class="folder-tips" style="margin-top: 15px; padding: 10px; background: var(--panel); border-radius: 4px; font-size: 12px; color: var(--muted);">
        <strong>Tips:</strong>
        <ul style="margin: 8px 0 0 0; padding-left: 20px;">
          <li>Use descriptive names for better organization</li>
          <li>Avoid special characters: \ / : * ? " &lt; &gt; |</li>
          <li>Folder names are case-sensitive</li>
        </ul>
      </div>
    </div>
    <div class="modal-footer">
      <button id="btn-cancel-new-folder" class="btn secondary" data-i18n="cancel">Cancel</button>
      <button id="btn-create-folder" class="btn primary" data-i18n="create">Create</button>
    </div>
  </div>
</div>





<div id="ai-modal" class="modal hidden">
    <div class="modal-content ai-modal-content">
        <div class="modal-header">
            <h3 data-i18n="aiAssistant">AI Assistant</h3>
            <div class="modal-actions">
                <button id="btn-close-ai" class="icon-btn" data-i18n-title="close">
                    <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="ai-controls">
            <div class="select-wrapper">
                <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="2" width="20" height="20" rx="2" ry="2" fill="none" />
                    <path d="M8 2v20M16 2v20M2 8h20M2 16h20" fill="none" />
                </svg>
                <!-- Model list is loaded from the runtime config (ai_runtime_config);
                     a hardcoded fallback here used to mask stale admin config. -->
                <select id="ai-model-select"></select>
            </div>
            <div class="select-wrapper">
                <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="8" r="4" fill="none" />
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" fill="none" />
                </svg>
                <select id="ai-role-select">
                    <option value="general">General Assistant</option>
                    <option value="programmer">Programmer</option>
                    <option value="analyst">Analyst</option>
                </select>
            </div>
        </div>
        
        <div class="ai-modal-body">
            <div id="ai-agent-activity" class="ai-agent-activity" aria-live="polite"></div>
            <div id="ai-chat" class="ai-chat"></div>
            
            <div class="ai-input">
                <textarea id="ai-input-text" data-i18n-placeholder="askAI"></textarea>
                <button id="btn-ai-send" class="btn primary" data-i18n-title="sendMessage">
                    <svg width="18" height="18" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>


    <!-- Modal for chat history deletion confirmation -->
    <div id="clear-history-modal" class="modal hidden">
      <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
          <h3 data-i18n="clearHistoryTitle">Clear Chat History</h3>
          <button id="btn-close-clear-history" class="icon-btn" data-i18n-title="close">
            <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <div style="display: flex; align-items: center; margin-bottom: 15px;">
            <svg width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px;">
              <polyline points="3 6 5 6 21 6"></polyline>
              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
              <line x1="10" y1="11" x2="10" y2="17"></line>
              <line x1="14" y1="11" x2="14" y2="17"></line>
            </svg>
            <p data-i18n="clearHistoryConfirm">Are you sure you want to clear the chat history? This action cannot be undone.</p>
          </div>
        </div>
        <div class="modal-footer">
          <button id="btn-cancel-clear-history" class="btn secondary" data-i18n="cancel">Cancel</button>
          <button id="btn-confirm-clear-history" class="btn danger" data-i18n="clear">Clear</button>
        </div>
      </div>
    </div>






<!-- Advanced search modal -->
<div id="advanced-search-modal" class="modal hidden">
  <div class="modal-content" style="max-width: 700px; max-height: 80vh;">
    <div class="modal-header">
      <h3>
        <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          <path d="M14 8L8 14"></path>
          <path d="M8 8L14 14"></path>
        </svg>
        <span data-i18n="advancedSearch">Advanced Search</span>
      </h3>
      <button class="icon-btn close-btn">&times;</button>
    </div>
    
    <div class="modal-body" style="overflow-y: auto;">
      <!-- Progress bar -->
      <div id="search-progress-container" class="search-progress-container hidden">
        <div class="progress-info">
          <span data-i18n="searching">Searching...</span>
          <span id="search-progress-percent">0%</span>
        </div>
        <div class="progress-bar">
          <div id="search-progress-fill" class="progress-fill" style="width: 0%;"></div>
        </div>
        <div class="progress-details">
          <span id="search-processed-items">0</span> / <span id="search-total-items">0</span>
          <span data-i18n="itemsProcessed">items processed</span>
        </div>
      </div>

      <!-- Search form -->
      <div class="search-form">
        <!-- Folder selection with visual tree -->
        <div class="form-group">
          <label data-i18n="searchInFolder">Search in folder:</label>
          <div class="folder-selector-with-tree">
            <div class="selected-folder-display" id="selected-folder-display" style="
              padding: 8px 12px;
              background: var(--bg);
              border: 1px solid var(--border);
              border-radius: 6px;
              margin-bottom: 10px;
              font-size: 13px;
              color: var(--fg);
              display: flex;
              align-items: center;
              gap: 8px;
            ">
              <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
              </svg>
              <span>/ (root)</span>
            </div>
            
            <div id="search-folder-tree-container" class="folder-tree" style="
              border: 1px solid var(--border);
              border-radius: 6px;
              padding: 10px;
              max-height: 250px;
              overflow-y: auto;
              background: var(--panel);
            "></div>
          </div>
        </div>

        <div class="form-group">
          <label data-i18n="searchByName">By name:</label>
          <div class="search-mode-segmented" id="search-text-mode">
            <button type="button" class="active" data-mode="name" data-i18n="searchByName">By name:</button>
            <button type="button" data-mode="extension" data-i18n="searchByExtension">By extension:</button>
          </div>
          <div class="search-mode-panel" data-search-panel="name">
            <input type="text" id="search-name" data-i18n-placeholder="searchNamePlaceholder" placeholder="*partial* or exact name" class="search-input">
          </div>
          <div class="search-mode-panel hidden" data-search-panel="extension">
            <div class="search-extension-autocomplete">
              <input type="text" id="search-extension" data-i18n-placeholder="searchExtensionPlaceholder" placeholder=".txt, .php, .js" class="search-input" autocomplete="off">
              <div id="search-extension-suggestions" class="search-extension-suggestions hidden" role="listbox" aria-label="File extension suggestions"></div>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label data-i18n="searchByType">File type:</label>
          <input type="hidden" id="search-type" value="all">
          <div class="search-type-segmented" id="search-type-mode">
            <button type="button" class="active" data-type="all" data-i18n="allTypes">All types</button>
            <button type="button" data-type="file" data-i18n="files">Files</button>
            <button type="button" data-type="folder" data-i18n="folders">Folders</button>
          </div>
        </div>

        <div class="form-group">
          <label data-i18n="searchBySize">By size:</label>
          <div class="size-filter">
            <select id="search-size-operator" class="size-operator">
              <option value="any" data-i18n="sizeAny">Any</option>
              <option value="greater" data-i18n="sizeGreater">Greater than</option>
              <option value="less" data-i18n="sizeLess">Less than</option>
              <option value="between" data-i18n="sizeBetween">Between</option>
              <option value="exact" data-i18n="sizeExact">Exactly</option>
            </select>
            <input type="number" id="search-size-value" class="size-value" data-i18n-placeholder="sizeValuePlaceholder" placeholder="0" min="0">
            <select id="search-size-unit" class="size-unit">
              <option value="B">B</option>
              <option value="KB">KB</option>
              <option value="MB" selected>MB</option>
              <option value="GB">GB</option>
            </select>
          </div>
          <div id="search-size-between" class="size-between hidden">
            <input type="number" id="search-size-min" class="size-min" data-i18n-placeholder="sizeMinPlaceholder" placeholder="Min" min="0">
            <span>—</span>
            <input type="number" id="search-size-max" class="size-max" data-i18n-placeholder="sizeMaxPlaceholder" placeholder="Max" min="0">
            <select id="search-size-between-unit" class="size-unit">
              <option value="B">B</option>
              <option value="KB">KB</option>
              <option value="MB" selected>MB</option>
              <option value="GB">GB</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label data-i18n="searchByDate">By modification date:</label>
          <div class="date-filter">
            <select id="search-date-operator" class="date-operator">
              <option value="any" data-i18n="dateAny">Any</option>
              <option value="after" data-i18n="dateAfter">After</option>
              <option value="before" data-i18n="dateBefore">Before</option>
              <option value="between" data-i18n="dateBetween">Between</option>
            </select>
            <input type="date" id="search-date-value" class="date-value">
          </div>
          <div id="search-date-between" class="date-between hidden">
            <input type="date" id="search-date-start" class="date-start">
            <span>—</span>
            <input type="date" id="search-date-end" class="date-end">
          </div>
        </div>
      </div>
    </div>
    
    <div class="modal-footer">
      <button id="btn-cancel-advanced-search" class="btn secondary" data-i18n="cancel">Cancel</button>
      <button id="btn-reset-advanced-search" class="btn secondary" data-i18n="reset">Reset</button>
      <button id="btn-start-advanced-search" class="btn primary" data-i18n="search">Search...</button>
    </div>
  </div>
</div>







<!-- Floating download button -->
<button id="floating-download-btn" class="floating-download-btn hidden">
    <svg width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
        <polyline points="7 10 12 15 17 10"></polyline>
        <line x1="12" y1="15" x2="12" y2="3"></line>
    </svg>
    <svg class="circular-progress" viewBox="0 0 64 64">
        <circle cx="32" cy="32" r="29.1" stroke-linecap="round"/>
    </svg>
</button>

<!-- Download progress panel -->
<div id="download-progress-panel" class="download-progress-panel hidden">
    <div class="download-panel-header">
        <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="7 10 12 15 17 10"></polyline>
            <line x1="12" y1="15" x2="12" y2="3"></line>
        </svg>
        <span data-i18n="downloadProgress">Download Progress</span>
    </div>
    <div class="download-panel-body" id="download-items-container">
        <!-- Download items will be added here dynamically -->
    </div>
</div>

<!-- Floating upload button -->
<button id="floating-upload-btn" class="floating-download-btn hidden">
    <svg width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
        <path d="M12 13v8"></path>
        <path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"></path>
        <path d="m8 17 4-4 4 4"></path>
    </svg>
    <svg class="circular-progress" viewBox="0 0 64 64">
        <circle cx="32" cy="32" r="29.1" stroke-linecap="round"/>
    </svg>
</button>

<!-- Floating compile button -->
<button id="floating-compile-btn" class="floating-download-btn hidden" title="Compile output">
    <svg width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
        <polyline points="4 17 10 11 14 15 20 9"></polyline>
        <polyline points="16 9 20 9 20 13"></polyline>
    </svg>
    <svg class="circular-progress" viewBox="0 0 64 64">
        <circle cx="32" cy="32" r="29.1" stroke-linecap="round"/>
    </svg>
    <span class="floating-compile-time"></span>
</button>

<button id="floating-archive-btn" class="floating-download-btn hidden" title="Archive operations">
    <svg width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
        <rect width="20" height="5" x="2" y="3" rx="1"></rect>
        <path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"></path>
        <path d="M10 12h4"></path>
    </svg>
    <svg class="circular-progress" viewBox="0 0 64 64">
        <circle cx="32" cy="32" r="29.1" stroke-linecap="round"/>
    </svg>
</button>

<div id="archive-progress-panel" class="download-progress-panel hidden">
    <div class="download-panel-header">
        <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
            <rect width="20" height="5" x="2" y="3" rx="1"></rect>
            <path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"></path>
            <path d="M10 12h4"></path>
        </svg>
        <span>Archive Progress</span>
    </div>
    <div class="download-panel-body" id="archive-items-container"></div>
</div>

<!-- Upload progress panel -->
<div id="upload-progress-panel" class="download-progress-panel hidden">
    <div class="download-panel-header">
        <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
            <path d="M12 13v8"></path>
            <path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"></path>
            <path d="m8 17 4-4 4 4"></path>
        </svg>
        <span data-i18n="uploadProgress">Upload Progress</span>
        <button class="icon-btn minimize-btn" id="upload-progress-minimize-btn" style="margin-left: auto; background: transparent; border: none; color: white;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"></path>
            </svg>
        </button>
    </div>
    <div class="download-panel-body" id="upload-items-container"></div>
</div>


    
<!-- Action panel for selected items -->
<div id="selection-action-panel" class="hidden">
    <button id="btn-move-selected" class="btn secondary small">
        <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
            <path d="M5 19h14M5 5h14M5 12h10m3 0l3-3m-3 3l3 3"></path>
        </svg>
        <span data-i18n="moveSelected"></span>
    </button>
    
    <!-- New download button -->
    <button id="btn-download-selected" class="btn primary small">
        <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="7 10 12 15 17 10"></polyline>
            <line x1="12" y1="15" x2="12" y2="3"></line>
        </svg>
        <span data-i18n="downloadSelected"></span>
    </button>
    
    <button id="btn-delete-selected" class="btn danger small">
        <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
            <polyline points="3 6 5 6 21 6"></polyline>
            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
        </svg>
        <span data-i18n="deleteSelected"></span>
    </button>
    
    <button id="btn-clear-selection" class="btn secondary small" data-i18n-title="clearSelectionTooltip" aria-label="Clear selection">
        <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
    </button>
</div>
  





<script>
(function() {
    function hasOpenTabs() {
        if (window.tabsState && Array.isArray(window.tabsState.tabs)) {
            return window.tabsState.tabs.length > 0;
        }
        const tabsList = document.getElementById('tabs-list');
        return tabsList && tabsList.children.length > 0;
    }
    function checkTabsAndUpdateBodyClass() {
        document.body.classList.toggle('tabs-open', hasOpenTabs());
    }
    setInterval(checkTabsAndUpdateBodyClass, 500);
    if (window.tabsState) {
        let originalTabs = window.tabsState.tabs;
        Object.defineProperty(window.tabsState, 'tabs', {
            get: () => originalTabs,
            set: (newTabs) => {
                originalTabs = newTabs;
                checkTabsAndUpdateBodyClass();
                return newTabs;
            }
        });
    }
    const otherBg = document.body.getAttribute('data-theme') === 'dark' ? 'assets/background_light.jpg' : 'assets/background_dark.jpg';
    const preload = new Image();
    preload.src = otherBg;
})();
</script>

<!-- Sync Token Modal -->
<div id="sync-token-modal" class="modal hidden">
  <div class="modal-content" style="max-width:650px;">
    <div class="modal-header">
      <h3 data-i18n="syncTokenTitle">WebDAV Tokens</h3>
      <button id="btn-close-token-modal" class="icon-btn">
        <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
    <div class="modal-body" style="padding:20px;">
      <p style="color:var(--fg-secondary);font-size:13px;margin-bottom:16px;" data-i18n="syncTokenDescription">
        Generate a WebDAV token. Use your account email as the login and this token as the password.
      </p>
      <div style="margin-bottom:12px;">
        <label for="sync-token-name" style="display:block;font-size:12px;color:var(--fg-secondary);margin-bottom:6px;" data-i18n="syncTokenNameLabel">Connection name</label>
        <input id="sync-token-name" type="text" autocomplete="off" placeholder="My computer" data-i18n-placeholder="syncTokenNamePlaceholder" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:6px;background:var(--panel);color:var(--fg);">
      </div>
      
      <!-- Generate button -->
      <div style="margin-bottom:20px;">
        <button id="btn-generate-token" class="btn primary" style="width:100%;padding:12px;display:inline-flex;align-items:center;justify-content:center;gap:8px;">
          <svg width="18" height="18" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path>
          </svg>
          <span data-i18n="generateToken">Generate New Token</span>
        </button>
        <div style="font-size:11px;color:var(--fg-secondary);margin-top:8px;">
          Create a token, copy it once, then manage active WebDAV connections below.
        </div>
      </div>
      
      <!-- Generated token display -->
      <div id="sync-token-display" style="display:none;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:16px;margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
          <code id="sync-username-value" style="flex:1;background:var(--panel);padding:10px;border-radius:6px;font-size:13px;word-break:break-all;font-family:monospace;"></code>
          <button id="btn-copy-username" class="btn secondary small" title="Copy username">
            <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
            </svg>
          </button>
        </div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
          <code id="sync-token-value" style="flex:1;background:var(--panel);padding:10px;border-radius:6px;font-size:13px;word-break:break-all;font-family:monospace;"></code>
          <button id="btn-copy-token" class="btn secondary small" title="Copy">
            <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
            </svg>
          </button>
        </div>
        <div id="webdav-script-hint" style="font-size:11px;color:var(--fg-secondary);line-height:1.45;margin-bottom:8px;" data-i18n="webdavScriptHint">Download the script, run it, and enter your sudo password if the system asks to install davfs2 or mount the folder.</div>
        <div id="webdav-windows-address-wrap" style="display:none;margin-bottom:8px;">
          <div style="font-size:11px;color:var(--fg-secondary);margin-bottom:6px;" data-i18n="webdavWindowsAddressLabel">Windows network drive folder:</div>
          <code id="webdav-windows-address" style="display:block;background:var(--panel);padding:10px;border-radius:6px;font-size:12px;word-break:break-all;font-family:monospace;"></code>
        </div>
        <div id="webdav-linux-command-wrap" style="display:none;margin-bottom:8px;">
          <div style="font-size:11px;color:var(--fg-secondary);margin-bottom:6px;" data-i18n="webdavLinuxCommandLabel">Linux command after downloading:</div>
          <div style="display:flex;align-items:center;gap:8px;">
            <code id="webdav-linux-command" style="flex:1;background:var(--panel);padding:10px;border-radius:6px;font-size:12px;word-break:break-all;font-family:monospace;"></code>
            <button id="btn-copy-webdav-linux-command" class="btn secondary small" title="Copy command">
              <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
              </svg>
            </button>
          </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
          <button id="btn-download-webdav-script" class="btn primary small" style="display:inline-flex;align-items:center;gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="7 10 12 15 17 10"></polyline>
              <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            <span data-i18n="downloadConnectScript">Download connect script</span>
          </button>
          <button id="btn-download-linux-webdav-script" class="btn secondary small" style="display:inline-flex;align-items:center;gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="7 10 12 15 17 10"></polyline>
              <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            <span data-i18n="downloadLinuxScript">Linux/macOS SH</span>
          </button>
          <button id="btn-download-windows-webdav-script" class="btn secondary small" style="display:inline-flex;align-items:center;gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="7 10 12 15 17 10"></polyline>
              <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            <span data-i18n="downloadWindowsScript">Windows PS1</span>
          </button>
        </div>
        <div id="token-info" style="font-size:11px;color:var(--fg-secondary);"></div>
      </div>
      
      <!-- Active devices list -->
      <div id="sync-tokens-list" style="display:none;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px;flex-wrap:wrap;">
          <h4 style="font-size:14px;font-weight:600;margin:0;" data-i18n="activeDevices">Active WebDAV Tokens</h4>
          <div id="selected-token-hint" style="font-size:11px;color:var(--fg-secondary);">Select a device to manage its token.</div>
        </div>
        <div id="tokens-list-container" style="max-height:240px;overflow-y:auto;border:1px solid var(--border);border-radius:0;background:var(--bg);"></div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
          <button id="btn-regenerate-token" class="btn secondary small" style="display:inline-flex;align-items:center;gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
              <polyline points="23 4 23 10 17 10"></polyline>
              <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
            </svg>
            <span data-i18n="regenerateToken">Regenerate All</span>
          </button>
          <button id="btn-revoke-token" class="btn secondary small" style="display:inline-flex;align-items:center;gap:6px;color:#ef4444;">
            <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
            <span>Revoke Selected</span>
          </button>
          <button id="btn-revoke-all-tokens" class="btn secondary small" style="display:inline-flex;align-items:center;gap:6px;color:#ef4444;">
            <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
            <span data-i18n="revokeAllTokens">Revoke All</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="modal-groups-manage" class="modal hidden">
  <div class="modal-content" style="max-width:860px;width:92vw">
    <div class="modal-header">
      <h3 data-i18n="groupsManage">Manage groups</h3>
      <button type="button" id="btn-groups-manage-close" class="icon-btn" aria-label="Close">&times;</button>
    </div>
    <div class="modal-body">
      <div class="groups-create-row" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
        <input type="text" id="group-create-name" data-i18n-placeholder="groupCreatePlaceholder" placeholder="New group name" style="flex:1;min-width:160px" />
        <button type="button" id="btn-group-create" class="btn primary" data-i18n="groupCreate">Create group</button>
      </div>
      <div id="groups-manage-body"></div>
    </div>
  </div>
</div>

<div id="modal-storage-admin" class="modal hidden">
  <div class="modal-content storage-admin-content">
    <div class="modal-header">
      <h3 data-i18n="manageStorageTitle">Manage user storage</h3>
      <div class="storage-admin-head-actions">
        <button type="button" id="btn-storage-analytics" class="settings-action-btn storage-admin-analytics-btn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>
          <span data-i18n="storageAnalyticsButton">Analytics</span>
        </button>
        <button type="button" id="btn-storage-admin-close" class="icon-btn" aria-label="Close">&times;</button>
      </div>
    </div>
    <div class="modal-body storage-admin-body">
      <p class="settings-row-text storage-admin-hint" id="storage-admin-hint" data-i18n="manageStorageHint">Set an individual quota for each user. Pending requests can be approved or rejected here.</p>
      <h4 class="settings-group-title" data-i18n="storagePendingRequests">Pending requests</h4>
      <div id="storage-admin-requests" class="storage-admin-requests"></div>
      <div class="storage-admin-users-head">
        <h4 class="settings-group-title" data-i18n="storageAllUsers">All users</h4>
        <span id="storage-admin-count" class="user-quota-used"></span>
      </div>
      <div class="storage-admin-toolbar">
        <input type="search" id="storage-admin-search" class="storage-admin-search" placeholder="Search by email or name" data-i18n-placeholder="storageSearchPlaceholder" autocomplete="off">
      </div>
      <div class="storage-admin-table-wrap">
        <table class="storage-admin-table" id="storage-admin-table">
          <thead>
            <tr>
              <th class="storage-col-user" data-i18n="user">User</th>
              <th class="storage-col-role" data-i18n="role">Role</th>
              <th class="storage-col-used" data-i18n="used">Used</th>
              <th class="storage-col-quota" data-i18n="quota">Quota</th>
              <th class="storage-col-actions" data-i18n="actions">Actions</th>
            </tr>
          </thead>
          <tbody id="storage-admin-table-body"></tbody>
        </table>
      </div>
      <div id="storage-admin-pager" class="storage-admin-pager"></div>
    </div>
    <div class="modal-footer">
      <button type="button" id="btn-storage-admin-refresh" class="btn secondary" data-i18n="refresh">Refresh</button>
      <button type="button" id="btn-storage-admin-done" class="btn primary" data-i18n="close">Close</button>
    </div>
  </div>
</div>

<div id="modal-storage-analytics" class="modal hidden">
  <div class="modal-content storage-analytics-content">
    <div class="modal-header">
      <h3 data-i18n="storageAnalyticsTitle">Storage analytics</h3>
      <button type="button" id="btn-storage-analytics-close" class="icon-btn" aria-label="Close">&times;</button>
    </div>
    <div class="modal-body storage-analytics-body">
      <div class="storage-analytics-summary" id="storage-analytics-summary"></div>
      <div class="storage-analytics-chart-row">
        <canvas id="storage-analytics-canvas" width="300" height="300"></canvas>
        <div id="storage-analytics-legend" class="storage-analytics-legend"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" id="btn-storage-analytics-done" class="btn primary" data-i18n="close">Close</button>
    </div>
  </div>
</div>

<div id="modal-groups-delete" class="modal hidden">
  <div class="modal-content" style="max-width:520px;width:92vw">
    <div class="modal-header">
      <h3 data-i18n="groupDelete">Delete group</h3>
      <button type="button" id="btn-groups-delete-close" class="icon-btn" aria-label="Close">&times;</button>
    </div>
    <div class="modal-body">
      <p id="group-delete-message" data-i18n="groupDeleteConfirm">Delete the group and all its files permanently?</p>
      <label class="animated-check-label groups-delete-move-toggle" style="margin:16px 0 12px;">
        <input type="checkbox" id="group-delete-move-files" class="animated-checkbox" />
        <span class="animated-checkbox-box">
          <svg width="24" height="18" viewBox="0 0 24 18" fill="none"><path d="M2 9L8.5 15.5L22 2" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        <span data-i18n="groupDeleteMoveLabel">Move files to my folder before deleting</span>
      </label>
      <div id="group-delete-move-fields" class="hidden groups-delete-move-picker">
        <button type="button" id="btn-group-delete-pick-folder" class="btn secondary small" data-i18n="selectTargetFolder">Select target folder</button>
        <div id="group-delete-target-display" class="groups-delete-target-display">/</div>
      </div>
    </div>
    <div class="modal-footer" style="display:flex;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid var(--border)">
      <button type="button" id="btn-groups-delete-cancel" class="btn secondary" data-i18n="cancel">Cancel</button>
      <button type="button" id="btn-groups-delete-confirm" class="btn danger" data-i18n="groupDelete">Delete group</button>
    </div>
  </div>
</div>

</body>
</html>
