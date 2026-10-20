<?php
if (!function_exists('sp_resolve_shared_path')) {
    function sp_resolve_shared_path(string $path): string
    {
        if ($path !== '' && file_exists($path)) {
            return $path;
        }
        $normalized = str_replace('\\', '/', $path);
        $marker = '/cloud/';
        $pos = strrpos($normalized, $marker);
        if ($pos !== false) {
            $relative = substr($normalized, $pos + strlen($marker));
            $candidate = dirname(__DIR__, 3) . '/apps/cloud/storage/' . ltrim($relative, '/');
            if (file_exists($candidate)) {
                return $candidate;
            }
        }
        return $path;
    }
}

if (!function_exists('sp_email_to_cloud_username')) {
    function sp_email_to_cloud_username(string $email): string
    {
        $username = str_replace(['@', '.'], '_', $email);
        $username = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $username);
        return $username ?: 'guest';
    }
}

if (!function_exists('sp_is_documents_file')) {
    function sp_is_documents_file(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);
        return strpos($normalized, '/.documents/') !== false || str_ends_with($normalized, '/.documents');
    }
}

if (!function_exists('sp_documents_secret')) {
    function sp_documents_secret(string $user): string
    {
        $salt = hash('sha256', dirname(__DIR__, 3) . '/apps/cloud/api.php' . '::cloud-docs-v1');
        return hash('sha256', $salt . '|' . $user, true);
    }
}

if (!function_exists('sp_decrypt_documents_content')) {
    function sp_decrypt_documents_content(string $raw, string $user): string
    {
        if (!str_starts_with($raw, 'CLOUDDOC1')) {
            return $raw;
        }
        $ivLen = openssl_cipher_iv_length('aes-256-cbc');
        $body = substr($raw, 9);
        $iv = substr($body, 0, $ivLen);
        $cipher = substr($body, $ivLen);
        $plain = openssl_decrypt($cipher, 'aes-256-cbc', sp_documents_secret($user), OPENSSL_RAW_DATA, $iv);
        return $plain === false ? '' : $plain;
    }
}

if (!function_exists('sp_file_bytes')) {
    function sp_file_bytes(string $path, string $ownerEmail = ''): string|false
    {
        $filePath = sp_resolve_shared_path($path);
        if (!is_file($filePath)) {
            return false;
        }
        $raw = (string)file_get_contents($filePath);
        if (sp_is_documents_file($filePath)) {
            return sp_decrypt_documents_content($raw, sp_email_to_cloud_username($ownerEmail));
        }
        return $raw;
    }
}

if (!function_exists('sp_stream_bytes')) {
    /**
     * Emit an in-memory payload with byte-range support. Video/audio seeking in
     * browsers needs 206 responses; without them the native timeline snaps back
     * to the start even though the whole file was downloaded.
     */
    function sp_stream_bytes(string $bytes, string $mime, string $name, bool $download = false): void
    {
        $size = strlen($bytes);
        $start = 0;
        $end = $size > 0 ? $size - 1 : 0;
        $partial = false;
        $range = trim((string)($_SERVER['HTTP_RANGE'] ?? ''));
        if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/i', $range, $match)) {
            if ($match[1] === '' && $match[2] !== '') {
                $suffix = (int)$match[2];
                $start = max(0, $size - $suffix);
                $end = $size - 1;
            } elseif ($match[1] !== '') {
                $start = (int)$match[1];
                if ($match[2] !== '') {
                    $end = min((int)$match[2], $size - 1);
                }
            }
            if ($size === 0 || $start > $end || $start >= $size) {
                header('Content-Range: bytes */' . $size);
                http_response_code(416);
                return;
            }
            $partial = true;
        }

        $length = $partial ? ($end - $start + 1) : $size;
        header('Content-Type: ' . $mime);
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . $length);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=300');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . str_replace('"', '', $name) . '"');
        if ($download) {
            header('Content-Description: File Transfer');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
        }
        if ($partial) {
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        }
        while (ob_get_level()) {
            ob_end_clean();
        }
        echo $partial ? substr($bytes, $start, $length) : $bytes;
    }
}

if (!function_exists('sp_cloud_icon_ext')) {
    /**
     * Cloud ships a per-extension SVG set; reuse it on public share pages so
     * folders and files look exactly like the Cloud tree/grid.
     */
    function sp_cloud_icon_ext(string $name): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === '') {
            return 'txt';
        }
        $icon = dirname(__DIR__, 3) . '/apps/cloud/icons/' . $ext . '.svg';
        return is_file($icon) ? $ext : 'txt';
    }
}

if (!function_exists('sp_file_icon_html')) {
    function sp_file_icon_html(string $name, string $class = ''): string
    {
        $ext = sp_cloud_icon_ext($name);
        $src = '/apps/cloud/icons/' . rawurlencode($ext) . '.svg';
        $fallback = '/apps/cloud/icons/txt.svg';
        return '<span class="sp-file-icon ' . sp_h($class) . '" aria-hidden="true">'
            . '<img src="' . sp_h($src) . '" alt="" loading="lazy" draggable="false"'
            . ' onerror="this.onerror=null;this.src=\'' . sp_h($fallback) . '\';">'
            . '</span>';
    }
}

if (!function_exists('sp_folder_icon_html')) {
    function sp_folder_icon_html(string $class = ''): string
    {
        return '<span class="sp-folder-icon ' . sp_h($class) . '" aria-hidden="true">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="#eb9a05" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>'
            . '</svg></span>';
    }
}

if (!function_exists('sp_file_mime')) {
    function sp_file_mime(string $filePath, string $fileName = ''): string
    {
        $ext = strtolower(pathinfo($fileName !== '' ? $fileName : $filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'bmp' => 'image/bmp',
            'tif' => 'image/tiff', 'tiff' => 'image/tiff', 'avif' => 'image/avif', 'heic' => 'image/heic',
            'mp4' => 'video/mp4', 'webm' => 'video/webm', 'avi' => 'video/x-msvideo', 'mov' => 'video/quicktime',
            'mkv' => 'video/x-matroska', 'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg',
            'flac' => 'audio/flac', 'pdf' => 'application/pdf', 'zip' => 'application/zip',
            'rar' => 'application/vnd.rar', '7z' => 'application/x-7z-compressed', 'gz' => 'application/gzip',
            'tar' => 'application/x-tar', 'txt' => 'text/plain; charset=utf-8', 'html' => 'text/html; charset=utf-8',
            'htm' => 'text/html; charset=utf-8', 'css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8', 'xml' => 'application/xml; charset=utf-8',
            'csv' => 'text/csv; charset=utf-8', 'md' => 'text/markdown; charset=utf-8',
            'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odt' => 'application/vnd.oasis.opendocument.text', 'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'odp' => 'application/vnd.oasis.opendocument.presentation',
        ];
        if (isset($mimeTypes[$ext])) {
            return $mimeTypes[$ext];
        }
        return (is_file($filePath) && !sp_is_documents_file($filePath)) ? (mime_content_type($filePath) ?: 'application/octet-stream') : 'application/octet-stream';
    }
}

if (!function_exists('sp_is_binary')) {
    function sp_is_binary(?string $content): bool
    {
        if ($content === null || $content === '') {
            return false;
        }
        if (strlen($content) >= 2 && substr($content, 0, 2) === 'PK') {
            return true;
        }
        $sample = substr($content, 0, 1000);
        $nulls = substr_count($sample, "\0");
        $control = 0;
        $len = strlen($sample);
        for ($i = 0; $i < $len; $i++) {
            $ord = ord($sample[$i]);
            if ($ord < 32 && !in_array($ord, [9, 10, 13], true)) {
                $control++;
            }
        }
        return $nulls > 0 || ($len > 0 && $control > $len * 0.3);
    }
}

if (!function_exists('sp_detect_file_type')) {
    function sp_detect_file_type(string $path, ?string $content = null): string
    {
        $filePath = sp_resolve_shared_path($path);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = is_file($filePath) ? (mime_content_type($filePath) ?: '') : '';
        $archiveExt = ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'tgz', 'tbz2', 'txz', 'cab', 'iso'];
        if (in_array($ext, $archiveExt, true)) return 'archive';
        $imageExt = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'ico', 'tiff', 'heic', 'avif'];
        if (in_array($ext, $imageExt, true) || str_starts_with($mime, 'image/')) return 'image';
        $videoExt = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', 'm4v', '3gp', 'mpg', 'mpeg', 'ogv'];
        if (in_array($ext, $videoExt, true) || str_starts_with($mime, 'video/')) return 'video';
        $audioExt = ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'wma', 'opus', 'weba'];
        if (in_array($ext, $audioExt, true) || str_starts_with($mime, 'audio/')) return 'audio';
        $modelExt = ['stl', '3mf', 'obj', 'gltf', 'glb', 'ply', 'off', '3ds'];
        if (in_array($ext, $modelExt, true)) return '3dmodel';
        if ($ext === 'pdf' || str_contains($mime, 'pdf')) return 'pdf';
        $officeExt = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf'];
        if (in_array($ext, $officeExt, true)) return 'office';
        $textExt = ['txt', 'html', 'htm', 'css', 'js', 'php', 'json', 'xml', 'csv', 'md', 'py', 'java', 'c', 'cpp', 'h', 'sql', 'log', 'ini', 'conf', 'yml', 'yaml', 'm', 'sh', 'bat', 'ps1', 'vue', 'ts', 'jsx', 'tsx', 'tex', 'latex'];
        if (in_array($ext, $textExt, true)) return 'text';
        if ($content === '') return 'binary';
        return ($content !== null && sp_is_binary($content)) ? 'binary' : 'text';
    }
}

if (!function_exists('sp_safe_join')) {
    function sp_safe_join(string $base, string $relative): ?string
    {
        $baseReal = realpath($base);
        if ($baseReal === false) {
            return null;
        }
        $relative = str_replace(['\\', "\0"], ['/', ''], $relative);
        $parts = [];
        foreach (explode('/', $relative) as $part) {
            if ($part === '' || $part === '.') continue;
            if ($part === '..') return null;
            $parts[] = $part;
        }
        $candidate = $baseReal . ($parts ? DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts) : '');
        $parent = realpath(dirname($candidate));
        $target = is_file($candidate) || is_dir($candidate) ? realpath($candidate) : ($parent !== false ? $parent . DIRECTORY_SEPARATOR . basename($candidate) : false);
        if ($target === false || !str_starts_with($target, $baseReal)) {
            return null;
        }
        return $candidate;
    }
}

if (!function_exists('sp_relative_path')) {
    function sp_relative_path(string $base, string $path): string
    {
        $baseReal = rtrim((string)realpath($base), DIRECTORY_SEPARATOR);
        $pathReal = realpath($path);
        if ($baseReal === '' || $pathReal === false) {
            return '';
        }
        if (str_starts_with($pathReal, $baseReal)) {
            return ltrim(substr($pathReal, strlen($baseReal)), DIRECTORY_SEPARATOR);
        }
        return '';
    }
}
