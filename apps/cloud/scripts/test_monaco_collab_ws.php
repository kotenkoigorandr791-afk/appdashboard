<?php
declare(strict_types=1);

$host = $argv[1] ?? '127.0.0.1';
$port = (int)($argv[2] ?? 8767);
$roomKey = 'test::room-' . getmypid() . '-' . time() . '.txt';
// Mirror the ticket location used by the web API and the server: the app
// storage directory first, then the legacy temp path.
$ticketDir = null;
foreach ([dirname(__DIR__) . '/storage/.monaco_collab_tickets', sys_get_temp_dir() . '/cloud_monaco_tickets'] as $dir) {
    if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) continue;
    if (!is_writable($dir)) continue;
    $ticketDir = $dir;
    break;
}
if ($ticketDir === null) {
    fwrite(STDERR, "cannot create ticket directory\n");
    exit(1);
}

function create_test_ticket(string $ticketDir, string $email): string {
    $ticket = bin2hex(random_bytes(24));
    file_put_contents($ticketDir . '/' . $ticket . '.json', json_encode(['email' => $email, 'exp' => time() + 60]));
    return $ticket;
}
function ws_connect(string $host, int $port): mixed {
    $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 5);
    if (!$socket) throw new RuntimeException("connect failed: {$errstr} ({$errno})");
    stream_set_timeout($socket, 2);
    $key = base64_encode(random_bytes(16));
    $headers = implode("\r\n", [
        'GET / HTTP/1.1', "Host: {$host}:{$port}", 'Upgrade: websocket', 'Connection: Upgrade',
        'Sec-WebSocket-Version: 13', "Sec-WebSocket-Key: {$key}", '', ''
    ]) . "\r\n";
    fwrite($socket, $headers);
    $buf = '';
    while (!str_contains($buf, "\r\n\r\n")) $buf .= (string)fread($socket, 4096);
    if (!str_contains($buf, '101')) throw new RuntimeException("handshake failed: {$buf}");
    return $socket;
}
function ws_send_frame($socket, array $message): void {
    $payload = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $len = strlen($payload); $mask = random_bytes(4);
    $frame = chr(0x81) . ($len < 126 ? chr(0x80 | $len) : chr(0xFE) . pack('n', $len)) . $mask;
    for ($i = 0; $i < $len; $i++) $frame .= $payload[$i] ^ $mask[$i % 4];
    fwrite($socket, $frame);
}
function ws_read_frame($socket): ?array {
    $head = fread($socket, 2);
    if (!$head || strlen($head) < 2) return null;
    $len = unpack('Cfirst/Csecond', $head)['second'] & 0x7f;
    if ($len === 126) $len = unpack('n', fread($socket, 2))[1];
    if ($len === 127) return null;
    $payload = $len ? fread($socket, $len) : '';
    return is_string($payload) ? json_decode($payload, true) : null;
}
function ws_wait_for($socket, string $type): ?array {
    for ($i = 0; $i < 5; $i++) {
        $message = ws_read_frame($socket);
        if (($message['type'] ?? '') === $type) return $message;
    }
    return null;
}

$ticketA = create_test_ticket($ticketDir, 'a@example.test');
$ticketB = create_test_ticket($ticketDir, 'b@example.test');
try {
    $a = ws_connect($host, $port);
    ws_send_frame($a, ['type' => 'auth', 'ticket' => $ticketA]);
    if (!ws_wait_for($a, 'auth_ok')) throw new RuntimeException('client A authentication failed');
    ws_send_frame($a, ['type' => 'join', 'roomKey' => $roomKey, 'clientId' => 'test-client-a', 'name' => 'Tester A', 'content' => "hello\n", 'updatedAt' => (int)(microtime(true) * 1000)]);
    ws_wait_for($a, 'sync');
    ws_send_frame($a, ['type' => 'content_snapshot', 'roomKey' => $roomKey, 'clientId' => 'test-client-a', 'content' => "hello world\n", 'updatedAt' => (int)(microtime(true) * 1000)]);

    $b = ws_connect($host, $port);
    ws_send_frame($b, ['type' => 'auth', 'ticket' => $ticketB]);
    if (!ws_wait_for($b, 'auth_ok')) throw new RuntimeException('client B authentication failed');
    ws_send_frame($b, ['type' => 'join', 'roomKey' => $roomKey, 'clientId' => 'test-client-b', 'name' => 'Tester B', 'content' => '', 'updatedAt' => (int)(microtime(true) * 1000)]);
    $syncB = ws_wait_for($b, 'sync');
    if (($syncB['content'] ?? '') !== "hello world\n") throw new RuntimeException('second join overwrote the room snapshot');
    usleep(100000);
    ws_send_frame($b, ['type' => 'content_snapshot', 'roomKey' => $roomKey, 'clientId' => 'test-client-b', 'content' => "from B\n", 'updatedAt' => (int)(microtime(true) * 1000)]);
    usleep(100000);
    $updateA = ws_wait_for($a, 'content_snapshot');
    if (($updateA['content'] ?? '') !== "from B\n") {
        // A sync request is the recovery path used by clients after a dropped
        // frame, and must return B's current room snapshot.
        ws_send_frame($a, ['type' => 'sync_request', 'roomKey' => $roomKey, 'clientId' => 'test-client-a']);
        $updateA = ws_wait_for($a, 'sync');
    }
    if (($updateA['content'] ?? '') !== "from B\n") throw new RuntimeException('client A did not receive client B update');
    fwrite(STDOUT, "OK authenticated collaboration synchronization test passed\n");
    fclose($a); fclose($b);
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . "\n");
    exit(1);
} finally {
    @unlink($ticketDir . '/' . $ticketA . '.json');
    @unlink($ticketDir . '/' . $ticketB . '.json');
}
