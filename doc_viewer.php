<?php
/**
 * Documentation Viewer
 * Browse and read project documentation files with markdown rendering
 */
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/smart_log.php';
smart_log_bootstrap('dashboard');
// Check if embedded request
function docViewerIsEmbeddedRequest(): bool {
    if (isset($_GET['embed']) && $_GET['embed'] === '1') { return true; }
    return isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'iframe';
}

// Deny access helper
function docViewerDeny(int $httpCode, string $htmlMsg, string $jsonMsg = ''): void {
    $j = $jsonMsg !== '' ? $jsonMsg : $htmlMsg;
    if (isset($_GET['docs']) || isset($_GET['file'])) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($httpCode);
        echo json_encode(['error' => $j], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    http_response_code($httpCode);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Access denied</title></head><body><p>'
        . htmlspecialchars($htmlMsg, ENT_QUOTES, 'UTF-8')
        . '</p><p><a href="/">Home</a></p></body></html>';
    exit;
}

// Check authentication - validate session freshness
$docViewerLoggedIn = isset($_SESSION['user']['email'], $_SESSION['auth_time']);
if ($docViewerLoggedIn) {
    // Check if session is still valid (8 hours timeout)
    $authTimeout = 8 * 3600;
    if (time() - $_SESSION['auth_time'] > $authTimeout) {
        $_SESSION = [];
        session_destroy();
        session_start();
        $docViewerLoggedIn = false;
    }
}

if (!$docViewerLoggedIn) {
    docViewerDeny(401, 'You must be logged in.', 'Unauthorized');
}

// Allow only embedded or admin access
$docViewerRole = $docViewerLoggedIn ? (int)($_SESSION['user']['role'] ?? 0) : -1;
if ($docViewerRole < 1 && !docViewerIsEmbeddedRequest()) {
    docViewerDeny(403, 'Access denied.', 'Forbidden');
}

// Get URL parameters for theme, language, and accent color
$docTheme = isset($_GET['theme']) && preg_match('/^(dark|light)$/', (string)$_GET['theme']) ? $_GET['theme'] : 'dark';
$docLang = isset($_GET['lang']) && preg_match('/^(en|ru|uk|be)$/', (string)$_GET['lang']) ? $_GET['lang'] : 'en';
$docColor = isset($_GET['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$_GET['color']) ? $_GET['color'] : '#007bff';

// Get language, theme, color from user config if not in URL
$lastActiveEmail = getLastActiveUser();
$defaultConfig = ['language' => $docLang, 'theme' => $docTheme, 'color' => $docColor];
$config = $defaultConfig;

if ($lastActiveEmail) {
    $users = loadUsers();
    if (isset($users[$lastActiveEmail])) {
        $userData = $users[$lastActiveEmail];
        $config = [
            'language' => $userData['language'] ?? $defaultConfig['language'],
            'theme' => $userData['theme'] ?? $defaultConfig['theme'],
            'color' => $userData['color'] ?? $defaultConfig['color']
        ];
    }
}

// URL params override user config
if (isset($_GET['theme'])) $config['theme'] = $docTheme;
if (isset($_GET['lang'])) $config['language'] = $docLang;
if (isset($_GET['color'])) $config['color'] = $docColor;

$language = $config['language'];
$theme = $config['theme'];
$color = $config['color'];

// Load translations
$translations = [];
$i18nPath = __DIR__ . '/i18n/' . preg_replace('/[^a-z0-9_-]/i', '', $language) . '.json';
if (is_readable($i18nPath)) {
    $translations = json_decode(file_get_contents($i18nPath), true) ?: [];
}

function t($key, $default = '') {
    global $translations;
    return $translations[$key] ?? $default;
}

// Handle file request via API
if (isset($_GET['file'])) {
    $filename = preg_replace('/[^a-z0-9_.-]/i', '', $_GET['file']);
    if ($filename === '') {
        docViewerDeny(400, 'Invalid filename', 'Invalid filename');
    }
    
    $filePath = __DIR__ . '/docs/' . $filename;
    if (!is_readable($filePath)) {
        docViewerDeny(404, 'File not found', 'File not found');
    }
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'content' => file_get_contents($filePath),
        'filename' => $filename
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get docs directory
$docsDir = __DIR__ . '/docs';
$docsFiles = [];

if (is_dir($docsDir)) {
    $files = scandir($docsDir);
    foreach ($files as $file) {
        if (preg_match('/^\d+_[a-z0-9_]+\.md$/i', $file)) {
            $filePath = $docsDir . '/' . $file;
            $content = file_get_contents($filePath);
            
            // Extract first line as title
            $title = $file;
            if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
                $title = trim($matches[1]);
            }
            
            // Extract description (second line or first paragraph)
            $description = '';
            if (preg_match('/^##\s+(.+)$/m', $content, $matches)) {
                $description = trim($matches[1]);
            }
            
            // Get file info
            $fileInfo = stat($filePath);
            $modified = $fileInfo['mtime'] ?? time();
            
            // Estimate reading time
            $wordCount = str_word_count($content);
            $readTime = ceil($wordCount / 200);
            
            $docsFiles[] = [
                'filename' => $file,
                'title' => $title,
                'description' => $description,
                'modified' => $modified,
                'readTime' => $readTime
            ];
        }
    }
    usort($docsFiles, function($a, $b) {
        return strcmp($a['filename'], $b['filename']);
    });
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($language); ?>" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('documentation', 'Documentation')); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: <?php echo htmlspecialchars($color); ?>;
            --accent-hover: <?php
                $c = $color;
                $r = max(0, min(255, hexdec(substr($c, 1, 2)) - 30));
                $g = max(0, min(255, hexdec(substr($c, 3, 2)) - 30));
                $b = max(0, min(255, hexdec(substr($c, 5, 2)) - 30));
                echo sprintf('#%02x%02x%02x', $r, $g, $b);
            ?>;
            --accent-dim: <?php echo $color; ?>2e;
        }

        [data-theme="dark"] {
            --bg-base:      #0f1419;
            --bg-raised:    rgba(18,24,32,0.8);
            --bg-overlay:   rgba(255,255,255,0.10);
            --bg-hover:     rgba(255,255,255,0.12);
            --border:       rgba(255,255,255,0.14);
            --border-lit:   rgba(255,255,255,0.22);
            --text-primary:   #f4f7fb;
            --text-secondary: rgba(255,255,255,0.65);
            --text-muted:     rgba(255,255,255,0.42);
            --code-bg: rgba(18,24,32,0.9);
            --table-border: rgba(255,255,255,0.14);
            --table-header-bg: rgba(255,255,255,0.08);
        }

        [data-theme="light"] {
            --bg-base: #f5f5f5;
            --bg-raised: #ffffff;
            --bg-overlay: #f0f0f0;
            --bg-hover: #e8e8e8;
            --border: #e0e0e0;
            --border-lit: #c0c0c0;
            --text-primary: #1a1a1a;
            --text-secondary: #555555;
            --text-muted: #888888;
            --code-bg: #f6f8fa;
            --table-border: #e0e0e0;
            --table-header-bg: #f6f8fa;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; scrollbar-width: thin; scrollbar-color: var(--border-lit) var(--bg-base); }

        html {
            scroll-behavior: smooth;
            color-scheme: <?php echo $theme === 'dark' ? 'dark' : 'light'; ?>;
            scrollbar-width: thin;
            scrollbar-color: var(--border-lit) var(--bg-base);
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg-base);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-base); }
        ::-webkit-scrollbar-thumb { background: var(--border-lit); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

        .doc-header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--bg-raised);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            box-shadow: 0 1px 0 rgba(0, 0, 0, 0.05);
        }

        .doc-header-left {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
            min-width: 0;
        }

        .doc-header-left > div {
            display: flex;
            align-items: baseline;
            gap: 12px;
            min-width: 0;
        }

        .doc-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            background: linear-gradient(135deg, var(--text-primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            white-space: nowrap;
        }

        .doc-subtitle {
            font-size: 13px;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
        }

        .doc-header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid var(--border);
            background: var(--bg-overlay);
            color: var(--text-primary);
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn:hover {
            background: var(--bg-hover);
            border-color: var(--border-lit);
            transform: translateY(-1px);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-hover));
            border-color: var(--accent);
            color: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--accent-hover), var(--accent));
            border-color: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .btn-icon {
            padding: 10px;
        }

        .search-box {
            position: relative;
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            transition: width 0.3s ease;
        }

        .search-box.expanded {
            width: 320px;
        }

        .search-toggle {
            position: absolute;
            top: 0;
            left: 0;
            width: 44px;
            height: 44px;
            border: 1px solid var(--border);
            border-radius: 50%;
            background: var(--bg-overlay);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
            z-index: 2;
        }

        .search-toggle:hover {
            background: var(--bg-hover);
            border-color: var(--border-lit);
        }

        .search-box.expanded .search-toggle {
            background: transparent;
            border-color: transparent;
            color: var(--text-muted);
            pointer-events: none;
        }

        .search-box input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 44px;
            padding: 0 14px 0 40px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--bg-base);
            color: var(--text-primary);
            font-size: 14px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease, border-color 0.2s, background 0.2s;
        }

        .search-box.expanded input {
            opacity: 1;
            pointer-events: auto;
            background: var(--bg-raised);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px var(--accent-dim);
        }

        .search-box input::placeholder {
            color: var(--text-muted);
        }

        .doc-container {
            max-width: 100%;
            margin: 0;
            padding: 24px 32px;
            width: 100%;
        }

        .empty-state {
            text-align: center;
            padding: 80px 24px;
            color: var(--text-muted);
        }

        .empty-state svg {
            opacity: 0.3;
            margin-bottom: 20px;
            color: var(--accent);
        }

        .empty-state p {
            font-size: 16px;
            color: var(--text-secondary);
        }

        .loading-state {
            text-align: center;
            padding: 80px 24px;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 3px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 20px;
        }
        .docs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 24px;
            width: 100%;
        }

        .doc-tile {
            background: var(--bg-raised);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            gap: 16px;
            position: relative;
            overflow: hidden;
        }

        .doc-tile::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), var(--accent-dim));
            opacity: 0;
            transition: opacity 0.25s;
        }

        .doc-tile:hover::before {
            opacity: 1;
        }

        .doc-tile:hover {
            border-color: var(--accent);
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
        }

        .doc-tile-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent-dim), transparent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
            transition: transform 0.25s;
        }

        .doc-tile:hover .doc-tile-icon {
            transform: scale(1.05) rotate(-3deg);
        }

        .doc-tile-title {
            font-size: 17px;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.4;
        }

        .doc-tile-desc {
            font-size: 14px;
            color: var(--text-secondary);
            flex: 1;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .doc-tile-meta {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 12px;
            color: var(--text-muted);
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }

        .doc-tile-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .doc-tile-badge {
            position: absolute;
            top: 16px;
            right: 16px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: var(--accent-dim);
            color: var(--accent);
        }

        /* Document View */
        .doc-view {
            display: none;
            width: 100%;
        }

        .doc-view.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .doc-view-header {
            padding: 24px 0;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
        }

        .doc-view-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .doc-view-meta {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 13px;
            color: var(--text-muted);
        }

        .doc-view-content {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 32px;
            width: 100%;
        }

        .doc-toc {
            position: sticky;
            top: 90px;
            align-self: start;
            height: fit-content;
            max-height: calc(100vh - 110px);
            overflow-y: auto;
            padding: 16px;
            background: var(--bg-overlay);
            border-radius: 10px;
            border: 1px solid var(--border);
        }

        .doc-toc-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border);
        }

        .doc-toc-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .doc-toc-item {
            font-size: 13px;
        }

        .doc-toc-link {
            display: block;
            padding: 6px 10px;
            border-radius: 6px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.15s;
            font-weight: 400;
        }

        .doc-toc-link:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
            transform: translateX(2px);
        }

        .doc-toc-link.active {
            background: var(--accent-dim);
            color: var(--accent);
            font-weight: 500;
        }

        .doc-toc-level-1 { padding-left: 0; }
        .doc-toc-level-2 { padding-left: 12px; }
        .doc-toc-level-3 { padding-left: 24px; }
        .doc-toc-level-4 { padding-left: 36px; }

        .doc-content-body {
            font-size: 16px;
            line-height: 1.8;
        }

        .doc-content-body h1,
        .doc-content-body h2,
        .doc-content-body h3,
        .doc-content-body h4,
        .doc-content-body h5,
        .doc-content-body h6 {
            margin-top: 40px;
            margin-bottom: 20px;
            font-weight: 700;
            line-height: 1.3;
            color: var(--text-primary);
            scroll-margin-top: 100px;
        }

        .doc-content-body h1 { 
            font-size: 32px; 
            border-bottom: 2px solid var(--border); 
            padding-bottom: 16px;
            margin-top: 0;
        }
        .doc-content-body h2 { font-size: 26px; }
        .doc-content-body h3 { font-size: 20px; }
        .doc-content-body h4 { font-size: 18px; }

        .doc-content-body p {
            margin-bottom: 20px;
        }

        .doc-content-body a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
            border-bottom: 1px solid transparent;
            transition: border-color 0.15s;
        }

        .doc-content-body a:hover {
            border-bottom-color: var(--accent);
        }

        .doc-content-body code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            background: var(--code-bg);
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid var(--border);
        }

        .doc-content-body pre {
            background: var(--code-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            overflow-x: auto;
            margin: 20px 0;
        }

        .doc-content-body pre code {
            background: none;
            padding: 0;
            border: none;
        }

        .doc-content-body ul,
        .doc-content-body ol {
            margin: 20px 0;
            padding-left: 28px;
        }

        .doc-content-body li {
            margin-bottom: 10px;
        }

        .doc-content-body blockquote {
            border-left: 4px solid var(--accent);
            padding-left: 20px;
            margin: 20px 0;
            color: var(--text-secondary);
            background: var(--bg-overlay);
            padding: 16px 20px;
            border-radius: 0 8px 8px 0;
        }

        .doc-content-body table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            border: 1px solid var(--table-border);
            border-radius: 8px;
            overflow: hidden;
        }

        .doc-content-body th,
        .doc-content-body td {
            border: 1px solid var(--table-border);
            padding: 12px 16px;
            text-align: left;
        }

        .doc-content-body th {
            background: var(--table-header-bg);
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .doc-content-body tr:last-child {
            border-bottom: none;
        }

        .doc-content-body hr {
            border: none;
            border-top: 1px solid var(--border);
            margin: 24px 0;
        }

        .doc-content-body img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 16px 0;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 900px) {
            .doc-header {
                padding: 12px 16px;
                gap: 12px;
            }

            .doc-header-left {
                gap: 10px;
            }

            .doc-header-left > div {
                gap: 8px;
            }

            .doc-subtitle {
                font-size: 12px;
            }

            .search-box.expanded {
                width: 240px;
            }

            .doc-container {
                padding: 16px 20px;
            }

            .docs-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .doc-tile {
                padding: 20px;
            }

            .doc-view-header {
                padding: 16px 0;
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .doc-view-title {
                font-size: 20px;
            }

            .doc-view-meta {
                flex-wrap: wrap;
                gap: 12px;
            }

            .doc-view-content {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .doc-toc {
                position: static;
                max-height: none;
                order: -1;
                margin-bottom: 16px;
                padding: 12px;
            }

            .doc-toc-title {
                font-size: 10px;
                margin-bottom: 10px;
            }

            .doc-toc-link {
                padding: 8px;
                font-size: 14px;
            }

            .doc-content-body {
                font-size: 15px;
            }

            .doc-content-body h1 {
                font-size: 26px;
            }

            .doc-content-body h2 {
                font-size: 22px;
            }

            .doc-content-body h3 {
                font-size: 18px;
            }

            .doc-content-body pre {
                padding: 12px;
                font-size: 13px;
            }

            .doc-content-body code {
                font-size: 13px;
                padding: 2px 6px;
            }

            .doc-content-body table {
                font-size: 14px;
            }

            .doc-content-body th,
            .doc-content-body td {
                padding: 10px;
            }

            .btn {
                padding: 8px 12px;
                font-size: 13px;
            }

            .btn-icon {
                padding: 8px;
            }
        }

        @media (max-width: 480px) {
            .doc-container {
                padding: 12px 16px;
            }

            .doc-title {
                font-size: 18px;
            }

            .doc-subtitle {
                display: none;
            }

            .search-box.expanded {
                width: 200px;
            }

            .doc-tile-title {
                font-size: 16px;
            }

            .doc-tile-desc {
                font-size: 13px;
            }

            .doc-view-title {
                font-size: 18px;
            }

            .doc-content-body h1 {
                font-size: 24px;
            }

            .doc-content-body h2 {
                font-size: 20px;
            }

            .doc-content-body blockquote {
                padding: 12px 16px;
                font-size: 14px;
            }
        }

        .hidden { display: none !important; }
    </style>
</head>
<body>
    <header class="doc-header">
        <div class="doc-header-left">
            <div>
                <div class="doc-title"><?php echo htmlspecialchars(t('documentation', 'Documentation')); ?></div>
                <div class="doc-subtitle"><?php echo htmlspecialchars(t('documentation_subtitle', 'Browse and read project documentation')); ?></div>
            </div>
        </div>
        <div class="doc-header-actions">
            <div class="search-box" id="searchBox">
                <button type="button" class="search-toggle" aria-label="Search" onclick="toggleSearch()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                </button>
                <input type="text" id="searchDocs" placeholder="<?php echo htmlspecialchars(t('search_docs', 'Search documentation...')); ?>" oninput="filterDocs()" onblur="collapseSearch()">
            </div>
            <button class="btn" onclick="showGridView()" id="backToGridBtn" style="display: none;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="m15 18-6-6 6-6"/>
                </svg>
                <span data-i18n="back_to_docs"><?php echo htmlspecialchars(t('back_to_docs', 'Back to List')); ?></span>
            </button>
        </div>
    </header>

    <main class="doc-container">
        <!-- Grid View -->
        <div id="docsGrid" class="docs-grid">
            <?php if (empty($docsFiles)): ?>
                <div class="empty-state" style="grid-column: 1/-1;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    <p data-i18n="no_docs_found"><?php echo htmlspecialchars(t('no_docs_found', 'No documentation files found')); ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($docsFiles as $index => $doc): ?>
                    <div class="doc-tile" onclick="openDoc('<?php echo htmlspecialchars($doc['filename']); ?>')" data-filename="<?php echo htmlspecialchars($doc['filename']); ?>">
                        <?php if ($index < 3): ?>
                            <span class="doc-tile-badge">Popular</span>
                        <?php endif; ?>
                        <div class="doc-tile-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10 9 9 9 8 9"/>
                            </svg>
                        </div>
                        <div class="doc-tile-title"><?php echo htmlspecialchars($doc['title']); ?></div>
                        <div class="doc-tile-desc"><?php echo htmlspecialchars($doc['description']); ?></div>
                        <div class="doc-tile-meta">
                            <div class="doc-tile-meta-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                <span><?php echo htmlspecialchars($doc['readTime']); ?> <?php echo htmlspecialchars(t('minutes', 'min')); ?> read</span>
                            </div>
                            <div class="doc-tile-meta-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                                <span>Updated <?php echo date('M d, Y', $doc['modified']); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Document View -->
        <div id="docView" class="doc-view">
            <div class="doc-view-header">
                <h1 class="doc-view-title" id="docViewTitle"></h1>
                <div class="doc-view-meta">
                    <span id="docViewReadTime"></span>
                    <span id="docViewModified"></span>
                </div>
            </div>
            <div class="doc-view-content">
                <nav class="doc-toc" id="docTOC">
                    <div class="doc-toc-title" data-i18n="table_of_contents"><?php echo htmlspecialchars(t('table_of_contents', 'Table of Contents')); ?></div>
                    <ul class="doc-toc-list" id="docTOCList"></ul>
                </nav>
                <article class="doc-content-body" id="docContent"></article>
            </div>
        </div>
    </main>

    <script src="/assets/simple-markdown.js"></script>
    <script>
        var docsData = <?php echo json_encode($docsFiles); ?>;
        var translations = <?php echo json_encode($translations); ?>;

        function t(key, defaultVal) {
            return translations[key] || defaultVal || key;
        }

        function showGridView() {
            document.getElementById('docsGrid').classList.remove('hidden');
            document.getElementById('docView').classList.remove('active');
            document.getElementById('backToGridBtn').style.display = 'none';
            window.history.pushState({}, '', window.location.pathname);
        }

        function openDoc(filename) {
            fetch('?file=' + encodeURIComponent(filename))
                .then(function(response) {
                    if (!response.ok) throw new Error('File not found');
                    return response.json();
                })
                .then(function(data) {
                    var markdown = data.content;
                    // Parse markdown
                    var html = SimpleMarkdown.parse(markdown);
                    document.getElementById('docContent').innerHTML = html;

                    // Update title
                    var firstLine = markdown.split('\n').find(function(line) {
                        return line.startsWith('# ');
                    });
                    var title = firstLine ? firstLine.replace(/^#\s+/, '') : filename;
                    document.getElementById('docViewTitle').textContent = title;

                    // Update meta
                    var doc = docsData.find(function(d) { return d.filename === filename; });
                    if (doc) {
                        document.getElementById('docViewReadTime').textContent = doc.readTime + ' ' + t('minutes', 'min');
                        document.getElementById('docViewModified').textContent = t('last_updated', 'Updated') + ': ' + new Date(doc.modified * 1000).toLocaleDateString();
                    }

                    // Generate TOC
                    var toc = SimpleMarkdown.extractTOC(markdown);
                    var tocList = document.getElementById('docTOCList');
                    tocList.innerHTML = '';
                    toc.forEach(function(item) {
                        var li = document.createElement('li');
                        li.className = 'doc-toc-item doc-toc-level-' + item.level;
                        var a = document.createElement('a');
                        a.href = '#' + item.id;
                        a.className = 'doc-toc-link';
                        a.textContent = item.text;
                        a.onclick = function(e) {
                            e.preventDefault();
                            document.getElementById(item.id).scrollIntoView({ behavior: 'smooth' });
                        };
                        li.appendChild(a);
                        tocList.appendChild(li);
                    });

                    // Show view
                    document.getElementById('docsGrid').classList.add('hidden');
                    document.getElementById('docView').classList.add('active');
                    document.getElementById('backToGridBtn').style.display = 'inline-flex';
                    
                    // Update URL hash
                    var newUrl = window.location.pathname + '?file=' + encodeURIComponent(filename);
                    window.history.pushState({file: filename}, '', newUrl);

                    // Setup link handling
                    setupDocLinks();
                })
                .catch(function(error) {
                    console.error('Error loading doc:', error);
                    alert((translations.error_loading_documentation || 'Error loading documentation:') + ' ' + error.message);
                });
        }

        function setupDocLinks() {
            // Handle all links in the document content
            var content = document.getElementById('docContent');
            var links = content.querySelectorAll('a');
            
            links.forEach(function(link) {
                var href = link.getAttribute('href');
                if (!href) return;
                
                // Check if it's a markdown link to another .md file
                if (href.endsWith('.md')) {
                    link.onclick = function(e) {
                        e.preventDefault();
                        var targetFile = href.replace(/^\.?\//, '');
                        var doc = docsData.find(function(d) { return d.filename === targetFile; });
                        if (doc) {
                            openDoc(targetFile);
                        } else {
                            // Try to find by partial match
                            var partialMatch = docsData.find(function(d) { return d.filename.includes(targetFile); });
                            if (partialMatch) {
                                openDoc(partialMatch.filename);
                            } else {
                                window.open(href, '_blank');
                            }
                        }
                    };
                }
                // Handle anchor links within the same document
                else if (href.startsWith('#')) {
                    link.onclick = function(e) {
                        e.preventDefault();
                        var targetId = href.substring(1);
                        var targetElement = document.getElementById(targetId);
                        if (targetElement) {
                            targetElement.scrollIntoView({ behavior: 'smooth' });
                        }
                    };
                }
            });
        }

        function toggleSearch() {
            document.getElementById('searchBox').classList.add('expanded');
            document.getElementById('searchDocs').focus();
        }

        function collapseSearch() {
            document.getElementById('searchBox').classList.remove('expanded');
        }

        function filterDocs() {
            var query = document.getElementById('searchDocs').value.toLowerCase();
            var tiles = document.querySelectorAll('.doc-tile');
            tiles.forEach(function(tile) {
                var filename = tile.getAttribute('data-filename');
                var title = tile.querySelector('.doc-tile-title').textContent.toLowerCase();
                var desc = tile.querySelector('.doc-tile-desc').textContent.toLowerCase();
                var match = title.includes(query) || desc.includes(query) || filename.toLowerCase().includes(query);
                tile.style.display = match ? 'flex' : 'none';
            });
        }

        // Handle file param on load
        window.addEventListener('DOMContentLoaded', function() {
            var urlParams = new URLSearchParams(window.location.search);
            var filename = urlParams.get('file');
            if (filename && docsData.some(function(d) { return d.filename === filename; })) {
                openDoc(filename);
            }
        });

        // Handle browser back/forward
        window.addEventListener('popstate', function(event) {
            if (event.state && event.state.file) {
                openDoc(event.state.file);
            } else {
                showGridView();
            }
        });
    </script>
</body>
</html>
