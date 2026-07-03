<?php
declare(strict_types=1);
$host = $argv[1] ?? '127.0.0.1';
$port = (int)($argv[2] ?? 8767);
$roomKey = 'test::room-' . getmypid() . '-' . time() . '.txt';
$key = base64_encode(random_bytes(16));
$headers = implode("\r\n", [
    "GET / HTTP/1.1",
    "Host: {$host}:{$port}",
    'Upgrade: websocket',
    'Connection: Upgrade',
    'Sec-WebSocket-Version: 13',
    "Sec-WebSocket-Key: {$key}",
    '',
    ''
]) . "\r\n";
$socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 5);
if (!$socket) {
    fwrite(STDERR, "connect failed: {$errstr} ({$errno})\n");
    exit(1);
}
stream_set_timeout($socket, 2);
fwrite($socket, $headers);
$buf = '';
while (!str_contains($buf, "\r\n\r\n")) {
    $chunk = fread($socket, 4096);
    if ($chunk === false || $chunk === '') break;
    $buf .= $chunk;
}
if (!str_contains($buf, '101')) {
    fwrite(STDERR, "handshake failed: {$buf}\n");
    exit(1);
}
function ws_send_frame($socket, string $payload): void {
    $len = strlen($payload);
    $header = chr(0x81);
    $mask = random_bytes(4);
    if ($len < 126) {
        $frame = $header . chr(0x80 | $len);
    } elseif ($len <= 65535) {
        $frame = $header . chr(0xFE) . pack('n', $len);
    } else {
        $frame = $header . chr(0xFF) . pack('J', $len);
    }
    $frame .= $mask;
    for ($i = 0; $i < $len; $i++) {
        $frame .= $payload[$i] ^ $mask[$i % 4];
    }
    fwrite($socket, $frame);
}
function ws_read_frame($socket): ?string {
    $head = fread($socket, 2);
    if (!$head || strlen($head) < 2) return null;
    $b = unpack('Cfirst/Csecond', $head);
    $len = $b['second'] & 0x7F;
    $offset = 2;
    if ($len === 126) {
        $len = unpack('n', fread($socket, 2))[1];
        $offset = 0;
    } elseif ($len === 127) {
        fread($socket, 8);
        $len = 0;
    }
    $masked = ($b['second'] & 0x80) === 0x80;
    if ($masked) fread($socket, 4);
    $payload = $len > 0 ? fread($socket, $len) : '';
    return $payload === false ? null : $payload;
}
$clientId = 'test-client-a';
ws_send_frame($socket, json_encode([
    'type' => 'join',
    'roomKey' => $roomKey,
    'clientId' => $clientId,
    'name' => 'TesterA',
    'content' => "hello\n",
    'revision' => 1,
    'updatedAt' => time()
], JSON_UNESCAPED_UNICODE));
$sync = json_decode((string)ws_read_frame($socket), true);
usleep(100000);
ws_send_frame($socket, json_encode([
    'type' => 'content_snapshot',
    'roomKey' => $roomKey,
    'clientId' => $clientId,
    'content' => "hello world\n",
    'revision' => 99,
    'updatedAt' => time()
], JSON_UNESCAPED_UNICODE));
usleep(100000);
$socketB = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 5);
if (!$socketB) {
    fwrite(STDERR, "client B connect failed\n");
    exit(1);
}
stream_set_timeout($socketB, 2);
fwrite($socketB, $headers);
$bufB = '';
while (!str_contains($bufB, "\r\n\r\n")) {
    $chunk = fread($socketB, 4096);
    if ($chunk === false || $chunk === '') break;
    $bufB .= $chunk;
}
ws_send_frame($socketB, json_encode([
    'type' => 'join',
    'roomKey' => $roomKey,
    'clientId' => 'test-client-b',
    'name' => 'TesterB',
    'content' => '',
    'revision' => 1,
    'updatedAt' => time()
], JSON_UNESCAPED_UNICODE));
$syncB = json_decode((string)ws_read_frame($socketB), true);
$ok = is_array($syncB) && ($syncB['type'] ?? '') === 'sync'
    && str_contains((string)($syncB['content'] ?? ''), 'hello world');
fwrite(STDOUT, $ok ? "OK collab ws bidirectional snapshot test passed\n" : "FAIL " . json_encode($syncB, JSON_UNESCAPED_UNICODE) . "\n");
fclose($socket);
fclose($socketB);
exit($ok ? 0 : 1);
