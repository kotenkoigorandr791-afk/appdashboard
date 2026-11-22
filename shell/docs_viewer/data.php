<?php
function docViewerLoadDocs(): array {
    $docsDir = dirname(__DIR__, 2) . '/docs';
    $docsFiles = [];
    if (is_dir($docsDir)) {
        $files = scandir($docsDir);
        foreach ($files as $file) {
            if (preg_match('/^\d+_[a-z0-9_]+\.md$/i', $file)) {
                $filePath = $docsDir . '/' . $file;
                $content = file_get_contents($filePath);
                $title = $file;
                if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
                    $title = trim($matches[1]);
                }
                $description = '';
                if (preg_match('/^##\s+(.+)$/m', $content, $matches)) {
                    $description = trim($matches[1]);
                }
                $fileInfo = stat($filePath);
                $modified = $fileInfo['mtime'] ?? time();
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
    return $docsFiles;
}
