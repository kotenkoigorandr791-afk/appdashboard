<?php
function resolveSharedFilePath(string $path): string
{
    if (file_exists($path)) {
        return $path;
    }
    $normalized = str_replace('\\', '/', $path);
    $marker = '/cloud/';
    $pos = strrpos($normalized, $marker);
    if ($pos !== false) {
        $relative = substr($normalized, $pos + strlen($marker));
        $candidate = dirname(__DIR__, 2) . '/apps/cloud/storage/' . ltrim($relative, '/');
        if (file_exists($candidate)) {
            return $candidate;
        }
    }
    return $path;
}
function email_to_cloud_username(string $email): string
{
    $username = str_replace(['@', '.'], '_', $email);
    $username = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $username);
    return $username ?: 'guest';
}
function isSharedDocumentsFile(string $path): bool
{
    $normalized = str_replace('\\', '/', $path);
    return strpos($normalized, '/.documents/') !== false || str_ends_with($normalized, '/.documents');
}
function getSharedDocumentsSecret(string $user): string
{
    $salt = hash('sha256', dirname(__DIR__, 2) . '/apps/cloud/api.php' . '::cloud-docs-v1');
    return hash('sha256', $salt . '|' . $user, true);
}
function decryptSharedDocumentsContent(string $raw, string $user): string
{
    if (!str_starts_with($raw, 'CLOUDDOC1')) {
        return $raw;
    }
    $ivLen = openssl_cipher_iv_length('aes-256-cbc');
    $body = substr($raw, 9);
    $iv = substr($body, 0, $ivLen);
    $cipher = substr($body, $ivLen);
    $plain = openssl_decrypt($cipher, 'aes-256-cbc', getSharedDocumentsSecret($user), OPENSSL_RAW_DATA, $iv);
    return $plain === false ? '' : $plain;
}
function getAbsoluteFilePath($url)
{
    if (file_exists($url)) {
        return $url;
    }
    $parsedUrl = parse_url($url);
    if (isset($parsedUrl['path'])) {
        $serverPath = $_SERVER['DOCUMENT_ROOT'] . $parsedUrl['path'];
        if (file_exists($serverPath)) {
            return $serverPath;
        }
    }
    return $url;
}
function getSharedFileBytes(string $url)
{
    $filePath = getAbsoluteFilePath($url);
    if (file_exists($filePath)) {
        $raw = (string) file_get_contents($filePath);
        if (isSharedDocumentsFile($filePath)) {
            $owner = $GLOBALS['sharedOwnerUser'] ?? '';
            return decryptSharedDocumentsContent($raw, $owner);
        }
        return $raw;
    }
    return false;
}
function getSharedFileMime(string $filePath, string $fileName): string
{
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
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
    return (file_exists($filePath) && !isSharedDocumentsFile($filePath)) ? mime_content_type($filePath) : 'application/octet-stream';
}
function sfu_build_query(array $extra = []): string
{
    $params = array_merge([
        'sharedFile' => $GLOBALS['fileId'] ?? '',
        'theme' => $GLOBALS['theme'] ?? 'light',
        'lang' => $GLOBALS['language'] ?? 'en',
        'color' => $GLOBALS['color'] ?? '4299e1',
    ], $extra);
    if (!empty($GLOBALS['isFolder']) && !empty($GLOBALS['currentSubPath'])) {
        $params['subpath'] = $GLOBALS['currentSubPath'];
    }
    if (!empty($GLOBALS['isFolder']) && !empty($GLOBALS['selectedFile']['name'])) {
        $params['file'] = $GLOBALS['selectedFile']['name'];
    }
    if (!empty($GLOBALS['isFullscreen'])) {
        $params['fullscreen'] = '1';
    }
    return '?' . http_build_query($params);
}
function getFileDisplayUrl($filePath, $fileName)
{
    if (isSharedDocumentsFile($filePath)) {
        return sfu_build_query(['display' => '1']);
    }
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
    $fileRealPath = realpath($filePath);
    if ($fileRealPath && $docRoot && strpos($fileRealPath, $docRoot) === 0) {
        return substr($fileRealPath, strlen($docRoot));
    }
    return sfu_build_query(['display' => '1']);
}
function getFileSize($url)
{
    $filePath = getAbsoluteFilePath($url);
    if (file_exists($filePath)) {
        if (isSharedDocumentsFile($filePath)) {
            $bytes = getSharedFileBytes($url);
            return $bytes === false ? 0 : strlen($bytes);
        }
        return filesize($filePath);
    }
    return 0;
}
function shouldLoadFileContent(string $fileType): bool
{
    return $fileType === 'text';
}
function fileStartsWith(string $filePath, string $signature): bool
{
    if (!is_file($filePath) || !is_readable($filePath)) {
        return false;
    }
    $handle = fopen($filePath, 'rb');
    if (!$handle) {
        return false;
    }
    $bytes = fread($handle, strlen($signature));
    fclose($handle);
    return $bytes === $signature;
}
function getFileContent($url, $maxSize = 10485760)
{
    $filePath = getAbsoluteFilePath($url);
    if (!file_exists($filePath)) {
        return false;
    }
    if (filesize($filePath) > $maxSize) {
        return 'FILE_TOO_LARGE';
    }
    $content = getSharedFileBytes($url);
    if ($content === false) {
        return false;
    }
    if (strlen($content) > $maxSize) {
        return 'FILE_TOO_LARGE';
    }
    return $content;
}
function isBinary($content)
{
    if (!$content) {
        return false;
    }
    if (strlen($content) >= 4 && substr($content, 0, 2) === 'PK') {
        return true;
    }
    if (strlen($content) >= 6 && substr($content, 0, 6) === "Rar!\x1a\x07") {
        return true;
    }
    if (strlen($content) >= 6 && substr($content, 0, 6) === "7z\xbc\xaf\x27\x1c") {
        return true;
    }
    $length = strlen($content);
    $nullCount = substr_count($content, "\0");
    $controlCount = 0;
    for ($i = 0; $i < min($length, 1000); $i++) {
        $ord = ord($content[$i]);
        if ($ord < 32 && $ord !== 9 && $ord !== 10 && $ord !== 13) {
            $controlCount++;
        }
    }
    return ($nullCount > 0) || ($controlCount > min($length, 1000) * 0.3);
}
function detectFileType($filename, $content = null)
{
    $filePath = getAbsoluteFilePath($filename);
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeType = file_exists($filePath) ? (@mime_content_type($filePath) ?: '') : '';
    $archiveExt = ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'tgz', 'tbz2', 'txz', 'cab', 'iso'];
    $archiveMimes = ['application/zip', 'application/x-zip-compressed', 'application/vnd.rar', 'application/x-rar-compressed',
        'application/x-7z-compressed', 'application/gzip', 'application/x-tar', 'application/x-gzip'];
    if (in_array($extension, $archiveExt, true) || in_array($mimeType, $archiveMimes, true)) {
        return 'archive';
    }
    $imageExt = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'ico', 'tiff', 'heic', 'avif'];
    $imageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp', 'image/svg+xml', 'image/x-icon', 'image/tiff', 'image/avif', 'image/heic'];
    if (in_array($extension, $imageExt, true) || in_array($mimeType, $imageMimes, true)) {
        return 'image';
    }
    $videoExt = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', 'm4v', '3gp', 'mpg', 'mpeg', 'ogv'];
    $videoMimes = ['video/mp4', 'video/avi', 'video/quicktime', 'video/x-ms-wmv', 'video/x-flv', 'video/webm', 'video/x-matroska', 'video/ogg', 'video/mpeg'];
    if (in_array($extension, $videoExt, true) || in_array($mimeType, $videoMimes, true)) {
        return 'video';
    }
    $audioExt = ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'wma', 'opus', 'weba'];
    $audioMimes = ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/flac', 'audio/aac', 'audio/mp4', 'audio/x-wav', 'audio/webm', 'audio/opus'];
    if (in_array($extension, $audioExt, true) || in_array($mimeType, $audioMimes, true)) {
        return 'audio';
    }
    $model3dExt = ['stl', '3mf', 'obj', 'gltf', 'glb', 'ply', 'off', '3ds'];
    if (in_array($extension, $model3dExt, true)) {
        return '3dmodel';
    }
    if (($extension === 'pdf' || stripos($mimeType, 'pdf') !== false) && fileStartsWith($filePath, '%PDF')) {
        return 'pdf';
    }
    $textExt = ['txt', 'html', 'htm', 'css', 'js', 'php', 'json', 'xml', 'csv', 'md', 'py', 'java', 'c', 'cpp', 'h', 'sql', 'log', 'ini', 'conf', 'yml', 'yaml', 'm', 'sh', 'bat', 'ps1', 'vue', 'ts', 'jsx', 'tsx', 'tex', 'latex'];
    if (in_array($extension, $textExt, true)) {
        return 'text';
    }
    $officeExt = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp'];
    if (in_array($extension, $officeExt, true)) {
        return 'office';
    }
    return ($content !== null && isBinary($content)) ? 'binary' : 'text';
}
function formatFileSize($bytes)
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 1) . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}
function getImageDisplayUrl($fileUrl, $fileId)
{
    $filePath = getAbsoluteFilePath($fileUrl);
    if (isSharedDocumentsFile($filePath)) {
        return sfu_build_query(['display' => '1']);
    }
    if (file_exists($filePath) && is_readable($filePath)) {
        $mimeType = mime_content_type($filePath);
        if (strpos($mimeType, 'image/') === 0) {
            $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
            $fileRealPath = realpath($filePath);
            if ($fileRealPath && $docRoot && strpos($fileRealPath, $docRoot) === 0) {
                return substr($fileRealPath, strlen($docRoot));
            }
            $content = file_get_contents($filePath);
            return 'data:' . $mimeType . ';base64,' . base64_encode($content);
        }
    }
    return sfu_build_query(['display' => '1']);
}
function getVideoDisplayUrl($fileUrl, $fileId)
{
    return sfu_build_query(['display' => '1']);
}
function getModelDisplayUrl($fileUrl, $fileId)
{
    $filePath = getAbsoluteFilePath($fileUrl);
    if (isSharedDocumentsFile($filePath)) {
        return sfu_build_query(['display' => '1']);
    }
    if (file_exists($filePath) && is_readable($filePath)) {
        $content = file_get_contents($filePath);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = ['stl' => 'application/sla', '3mf' => 'application/vnd.ms-package.3dmanufacturing-3dmodel+xml', 'obj' => 'model/obj', 'gltf' => 'model/gltf+json', 'glb' => 'model/gltf-binary'];
        $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';
        return 'data:' . $mimeType . ';base64,' . base64_encode($content);
    }
    return sfu_build_query(['display' => '1']);
}
function jwtEncode($payload, $secret)
{
    $header = ['typ' => 'JWT', 'alg' => 'HS256'];
    $h = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
    $p = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', "$h.$p", $secret, true);
    $s = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
    return "$h.$p.$s";
}
