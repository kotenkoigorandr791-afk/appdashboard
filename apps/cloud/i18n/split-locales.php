<?php
declare(strict_types=1);
$baseDir = __DIR__;
$locales = ['en', 'ru', 'uk', 'be', 'pl', 'cs'];
$rules = [
    'ai' => '/^(ai[A-Z]|think$|regenerateResponse|historyCleared|clearHistoryError|fixProblemsPrompt|calculateDataPrompt|noFileOpen|noUserMessageFound|errorPrefix|retrying$)/',
    'upload' => '/^(upload|insufficient|checkingSpace|calculatingTotal|quotaCheck|filesSelected|totalUpload|availableSpace|freeUp|uploadAnyway|pause$|resume$|downloadError)/',
    'search' => '/^(search|advancedSearch|executingSearch|noResults|itemsProcessed|allTypes|documents$|images$|audio$|video$|archives$|code$|models3d|size|date)/',
    'sync' => '/^(sync|manageSync|webdav|download(Linux|Windows|Connect)Script|downloadConnectScript|selectToken|token[A-Z]|noActiveTokens|noCommandToCopy|noUsernameToCopy)/i',
    'settings' => '/^(settings|section|quota|session|apiKey|apiEndpoint|hideLanguage|theme|color|role|english|russian|ukrainian)/i',
];
function chunk_for_key(string $key, array $rules): string
{
    foreach ($rules as $chunk => $pattern) {
        if (preg_match($pattern, $key)) {
            return $chunk;
        }
    }
    return 'core';
}
foreach ($locales as $lang) {
    $src = $baseDir . '/' . $lang . '.json';
    if (!is_file($src)) {
        fwrite(STDERR, "Skip missing $src\n");
        continue;
    }
    $data = json_decode((string)file_get_contents($src), true);
    if (!is_array($data)) {
        fwrite(STDERR, "Invalid JSON: $src\n");
        continue;
    }
    $chunks = [];
    foreach ($data as $key => $value) {
        $c = chunk_for_key((string)$key, $rules);
        $chunks[$c][$key] = $value;
    }
    $outDir = $baseDir . '/' . $lang;
    if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
        throw new RuntimeException("Cannot create $outDir");
    }
    foreach ($chunks as $name => $map) {
        $path = $outDir . '/' . $name . '.json';
        file_put_contents($path, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    }
    echo "$lang: " . count($data) . " keys -> " . count($chunks) . " chunks\n";
}
