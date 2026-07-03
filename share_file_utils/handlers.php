<?php
if ($isFolder && !$selectedFile) {
    $fileAbsolutePath = '';
    $fileDisplayUrl = '';
    $fileSize = 0;
    $fileContent = false;
    $fileType = 'folder';
    $imageDisplayUrl = '';
    $videoDisplayUrl = '';
    $modelDisplayUrl = '';
} else {
    $fileAbsolutePath = getAbsoluteFilePath($fileUrl);
    $fileDisplayUrl = getFileDisplayUrl($fileAbsolutePath, $fileName);
    $fileSize = getFileSize($fileUrl);
    $fileType = detectFileType($fileUrl);
    if (shouldLoadFileContent($fileType)) {
        $fileContent = getFileContent($fileUrl, 10485760);
        if ($fileContent !== false && $fileContent !== 'FILE_TOO_LARGE') {
            if (detectFileType($fileUrl, $fileContent) !== 'text') {
                $fileType = detectFileType($fileUrl, $fileContent);
                $fileContent = false;
            }
        }
    } else {
        $fileContent = false;
    }
    if ($fileContent === false && $fileType === 'text') {
        if ($fileSize > 10485760) {
            $fileContent = 'FILE_TOO_LARGE';
        } else {
            $fileType = 'binary';
        }
    }
    $imageDisplayUrl = ($fileType === 'image') ? getImageDisplayUrl($fileUrl, $fileId) : '';
    $videoDisplayUrl = ($fileType === 'video') ? getVideoDisplayUrl($fileUrl, $fileId) : '';
    $modelDisplayUrl = ($fileType === '3dmodel') ? getModelDisplayUrl($fileUrl, $fileId) : '';
}
if (!empty($shareNeedPassword)) {
    $fileType = 'protected';
}
if (isset($_GET['download']) || isset($_GET['display'])) {
    if (!empty($shareNeedPassword)) {
        http_response_code(403);
        echo 'Password required';
        exit;
    }
    if (isset($_GET['download']) && (int)($fileData['disable_download'] ?? 0) === 1) {
        http_response_code(403);
        echo 'Download disabled';
        exit;
    }
    if ($isFolder && !empty($selectedFile)) {
        $fileAbsolutePath = getAbsoluteFilePath($selectedFile['path']);
        $fileName = $selectedFile['name'];
    }
    $isDownload = isset($_GET['download']);
    $localBytes = ($fileAbsolutePath && file_exists($fileAbsolutePath)) ? getSharedFileBytes($fileAbsolutePath) : false;
    if ($localBytes !== false) {
        $contentType = $isDownload ? 'application/octet-stream' : getSharedFileMime($fileAbsolutePath, $fileName);
        header($isDownload ? 'Content-Description: File Transfer' : 'Content-Disposition: inline; filename="' . basename($fileName) . '"');
        header('Content-Type: ' . $contentType);
        if ($isDownload) {
            header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
        }
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . strlen($localBytes));
        while (ob_get_level()) {
            ob_end_clean();
        }
        echo $localBytes;
        exit;
    }
    http_response_code(404);
    echo 'File not found';
    exit;
}
$DOC_SERV_URL = 'https://document.serverkherson.xyz';
$docConfig = null;
$uiTheme = ($theme === 'light') ? 'theme-light' : 'theme-dark';
if ($fileType === 'office' && !isset($_GET['download']) && !isset($_GET['display']) && !isset($_GET['callback']) && !isset($_GET['action'])) {
    header('X-Frame-Options: SAMEORIGIN');
    $JWT_SECRET = trim(getOnlyofficeJwtSecret());
    $guestUser = 'guest_' . md5($fileId . $fileSize);
    $onlyofficeKey = md5($fileAbsolutePath . (file_exists($fileAbsolutePath) ? filemtime($fileAbsolutePath) : $fileSize) . $fileId);
    $scriptPath = '/' . ltrim($_SERVER['SCRIPT_NAME'], '/');
    $docUrl = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $scriptPath . sfu_build_query(['display' => '1']);
    $cbUrl = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $scriptPath . sfu_build_query(['callback' => '1']);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $docTypes = ['doc' => 'word', 'docx' => 'word', 'odt' => 'word', 'rtf' => 'word', 'txt' => 'word', 'xls' => 'cell', 'xlsx' => 'cell', 'ods' => 'cell', 'csv' => 'cell', 'ppt' => 'slide', 'pptx' => 'slide', 'odp' => 'slide'];
    $documentType = $docTypes[$ext] ?? 'word';
    $docConfig = [
        'documentType' => $documentType,
        'type' => 'desktop',
        'document' => ['fileType' => $ext, 'key' => $onlyofficeKey, 'title' => basename($fileName), 'url' => $docUrl],
        'editorConfig' => [
            'mode' => 'view',
            'callbackUrl' => $cbUrl,
            'lang' => $language,
            'user' => ['id' => 'guest_' . $guestUser, 'name' => 'Guest Viewer'],
            'customization' => [
                'goback' => ['url' => 'javascript:window.parent.postMessage({type: "closeOnlyOfficeEditor"}, "*")'],
                'toolbar' => false, 'header' => false, 'statusBar' => false, 'leftMenu' => false,
                'compactHeader' => true, 'compactToolbar' => true, 'hideRightMenu' => true,
                'chat' => false, 'comments' => false, 'plugins' => false, 'help' => false,
                'compact' => true, 'showReviewChanges' => false, 'macros' => false,
                'integrationMode' => 'embed', 'uiTheme' => $uiTheme,
            ],
        ],
    ];
    $docConfig['token'] = jwtEncode($docConfig, $JWT_SECRET);
}
if (isset($_GET['callback']) && $fileType === 'office') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 0, 'status' => 1]);
    exit;
}
if (isset($_GET['action']) && $_GET['action'] === 'download' && $fileType === 'office') {
    if (file_exists($fileAbsolutePath)) {
        $bytes = getSharedFileBytes($fileAbsolutePath);
        if ($bytes === false) {
            http_response_code(404);
            die('File not found');
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: private');
        header('Pragma: private');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        echo $bytes;
        exit;
    }
    http_response_code(404);
    die('File not found');
}
$downloadUrl = sfu_build_query(['download' => '1']);
$bgImage = SFU_ASSET_BASE . 'images/background_' . ($theme === 'dark' ? 'dark' : 'light') . '_opt.jpg';
