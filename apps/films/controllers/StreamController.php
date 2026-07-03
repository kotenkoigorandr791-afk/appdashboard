<?php
if (isset($_GET['stream']) && !empty($_GET['stream'])) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    ini_set('display_errors', '0');
    $filename = basename($_GET['stream']);
    $filePath = UPLOAD_DIR . $filename;
    error_log("Streaming request for: $filePath");
    error_log("UPLOAD_DIR constant: " . UPLOAD_DIR);
    error_log("Real path: " . @realpath($filePath));
    
    // Check if file exists or is accessible
    if (!@file_exists($filePath)) {
        error_log("File not found via file_exists: $filePath");
        // Try to open directly anyway (for symlinks)
        $testFp = @fopen($filePath, 'rb');
        if ($testFp) {
            error_log("File opened successfully via fopen despite file_exists failure");
            fclose($testFp);
        } else {
            error_log("File cannot be opened: $filePath");
            http_response_code(404);
            exit(films_t('file_not_found_or_not_accessible'));
        }
    } else {
        $fileSize = @filesize($filePath);
        error_log("File found, size: " . ($fileSize ?: 'unknown') . " bytes");
    }

    streamFile($filePath);
}
