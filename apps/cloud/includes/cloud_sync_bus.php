<?php
declare(strict_types=1);
require_once __DIR__ . '/cloud_sync_protocol.php';
function cloud_sync_bus_dir(): string {
    $dir = dirname(__DIR__) . '/storage/.sync_bus';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}
function cloud_sync_bus_channel(string $email): string {
    return cloud_sync_bus_dir() . '/' . hash('sha256', strtolower(trim($email))) . '.events';
}
function cloud_sync_bus_publish(string $email, string $rootKey, string $kind, string $path, array $extra = []): void {
    if ($email === '') return;
    $event = array_merge([
        'op' => 'event',
        'ts' => time(),
        'root' => $rootKey,
        'kind' => $kind,
        'path' => $path,
    ], $extra);
    $line = cloud_sync_encode($event) . "\n";
    @file_put_contents(cloud_sync_bus_channel($email), $line, FILE_APPEND | LOCK_EX);
    $notifyHost = getenv('CLOUD_SYNC_NOTIFY_HOST') ?: '127.0.0.1';
    $notifyPort = (int)(getenv('CLOUD_SYNC_NOTIFY_PORT') ?: 8771);
    $sock = @stream_socket_client("udp://{$notifyHost}:{$notifyPort}", $errno, $errstr, 0.2);
    if ($sock) {
        @fwrite($sock, substr(hash('sha256', strtolower(trim($email))), 0, 16));
        fclose($sock);
    }
}
function cloud_sync_bus_read_since(string $email, int $offset): array {
    $file = cloud_sync_bus_channel($email);
    if (!is_file($file)) return ['events' => [], 'offset' => 0];
    $size = (int)filesize($file);
    if ($offset < 0 || $offset > $size) $offset = 0;
    $fh = @fopen($file, 'rb');
    if (!$fh) return ['events' => [], 'offset' => $offset];
    fseek($fh, $offset);
    $chunk = stream_get_contents($fh);
    fclose($fh);
    $events = [];
    foreach (explode("\n", (string)$chunk) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $ev = cloud_sync_decode($line);
        if (is_array($ev) && ($ev['op'] ?? '') === 'event') $events[] = $ev;
    }
    return ['events' => $events, 'offset' => $size];
}
