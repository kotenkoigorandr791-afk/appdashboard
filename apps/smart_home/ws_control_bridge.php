<?php
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

if ($argc < 5) {
    fwrite(STDERR, "Usage: php ws_control_bridge.php <user_email> <device_id> <control_id> <value_json>\n");
    exit(1);
}

$userEmail = (string)$argv[1];
$deviceId = (string)$argv[2];
$controlId = (string)$argv[3];
$value = json_decode((string)$argv[4], true);
if (json_last_error() !== JSON_ERROR_NONE) {
    fwrite(STDERR, "Invalid value JSON\n");
    exit(1);
}

function shb_token(int $len = 18): string {
    return substr(bin2hex(random_bytes($len)), 0, $len * 2);
}

function shb_store_dashboard_token(string $userEmail, string $deviceId): string {
    $helper = dirname(__DIR__, 2) . '/storage_helper.php';
    $base = null;
    if (is_file($helper)) {
        require_once $helper;
        try {
            $base = rtrim(appdata_app_base_dir('smart_home'), '/') . '/data';
        } catch (Throwable $e) {
            $base = null;
        }
    }
    if ($base === null) {
        $link = __DIR__ . '/data';
        $base = (is_link($link) && realpath($link)) ? realpath($link) : $link;
    }
    $file = rtrim($base, '/') . '/global/ws_tokens.json';
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $tokens = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
    $token = shb_token(18);
    $tokens[$token] = [
        'user_email' => $userEmail,
        'user_key' => preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', strtolower($userEmail)),
        'device_id' => $deviceId,
        'expires_at' => time() + 300,
        'created_at' => gmdate('c'),
    ];
    file_put_contents($file, json_encode($tokens, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $token;
}

function shb_frame(string $payload, int $opcode = 0x1): string {
    $finOpcode = 0x80 | ($opcode & 0x0F);
    $len = strlen($payload);
    $mask = random_bytes(4);
    if ($len < 126) {
        $header = pack('CC', $finOpcode, 0x80 | $len);
    } elseif ($len <= 0xFFFF) {
        $header = pack('CCn', $finOpcode, 0x80 | 126, $len);
    } else {
        fwrite(STDERR, "Payload too long\n");
        exit(1);
    }
    $masked = '';
    for ($i = 0; $i < $len; $i++) {
        $masked .= $payload[$i] ^ $mask[$i % 4];
    }
    return $header . $mask . $masked;
}

function shb_pull_frame(string &$buffer): ?array {
    if (strlen($buffer) < 2) {
        return null;
    }
    $first = ord($buffer[0]);
    $second = ord($buffer[1]);
    $opcode = $first & 0x0F;
    $masked = ($second & 0x80) !== 0;
    $length = $second & 0x7F;
    $offset = 2;

    if ($length === 126) {
        if (strlen($buffer) < $offset + 2) {
            return null;
        }
        $length = unpack('n', substr($buffer, $offset, 2))[1];
        $offset += 2;
    } elseif ($length === 127) {
        if (strlen($buffer) < $offset + 8) {
            return null;
        }
        $parts = unpack('Nhigh/Nlow', substr($buffer, $offset, 8));
        $length = ((int)$parts['high'] << 32) | (int)$parts['low'];
        $offset += 8;
    }

    $mask = '';
    if ($masked) {
        if (strlen($buffer) < $offset + 4) {
            return null;
        }
        $mask = substr($buffer, $offset, 4);
        $offset += 4;
    }

    if (strlen($buffer) < $offset + $length) {
        return null;
    }

    $payload = substr($buffer, $offset, $length);
    $buffer = (string)substr($buffer, $offset + $length);

    if ($masked) {
        $decoded = '';
        for ($i = 0; $i < $length; $i++) {
            $decoded .= $payload[$i] ^ $mask[$i % 4];
        }
        $payload = $decoded;
    }

    return ['opcode' => $opcode, 'payload' => $payload];
}

$host = (string)(getenv('SMART_HOME_WS_BIND') ?: '127.0.0.1');
$port = (int)(getenv('SMART_HOME_WS_PORT') ?: 8766);
$socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 2);
if (!$socket) {
    fwrite(STDERR, "Local WebSocket unavailable: {$errstr} ({$errno})\n");
    exit(1);
}

stream_set_timeout($socket, 2);
$key = base64_encode(random_bytes(16));
$request = "GET / HTTP/1.1\r\n"
    . "Host: {$host}:{$port}\r\n"
    . "Upgrade: websocket\r\n"
    . "Connection: Upgrade\r\n"
    . "Sec-WebSocket-Version: 13\r\n"
    . "Sec-WebSocket-Key: {$key}\r\n\r\n";
fwrite($socket, $request);

$response = '';
stream_set_blocking($socket, false);
$handshakeDeadline = microtime(true) + 2.0;
while (!str_contains($response, "\r\n\r\n") && microtime(true) < $handshakeDeadline) {
    $chunk = fread($socket, 1024);
    if ($chunk === false || $chunk === '') {
        usleep(10000);
        continue;
    }
    $response .= $chunk;
}
stream_set_blocking($socket, true);

if (!str_starts_with($response, 'HTTP/1.1 101')) {
    fwrite(STDERR, "Local WebSocket handshake failed\n");
    exit(1);
}

$expectedAccept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
$accept = null;
foreach (explode("\r\n", trim($response)) as $line) {
    if (stripos($line, 'Sec-WebSocket-Accept:') === 0) {
        $accept = trim(substr($line, strlen('Sec-WebSocket-Accept:')));
        break;
    }
}
if ($accept !== $expectedAccept) {
    fwrite(STDERR, "Invalid WebSocket accept header\n");
    exit(1);
}

$token = shb_store_dashboard_token($userEmail, $deviceId);
fwrite($socket, shb_frame(json_encode([
    'type' => 'auth',
    'role' => 'dashboard',
    'session_token' => $token,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));

stream_set_blocking($socket, false);
$buffer = '';
$deadline = microtime(true) + 3.0;
$connected = false;
$ackDevice = null;

while (microtime(true) < $deadline) {
    $chunk = fread($socket, 8192);
    if ($chunk !== false && $chunk !== '') {
        $buffer .= $chunk;
    }
    while (($frame = shb_pull_frame($buffer)) !== null) {
        if ($frame['opcode'] === 0x8) {
            break 2;
        }
        if ($frame['opcode'] === 0x9) {
            fwrite($socket, shb_frame($frame['payload'], 0xA));
            continue;
        }
        if ($frame['opcode'] !== 0x1) {
            continue;
        }
        $msg = json_decode($frame['payload'], true);
        if (!is_array($msg)) {
            continue;
        }
        if (($msg['type'] ?? '') === 'error') {
            fwrite(STDERR, (string)($msg['message'] ?? 'WebSocket error') . "\n");
            exit(1);
        }
        if (($msg['type'] ?? '') === 'connected' && !$connected) {
            $connected = true;
            fwrite($socket, shb_frame(json_encode([
                'type' => 'control',
                'control_id' => $controlId,
                'value' => $value,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
            continue;
        }
        if (($msg['type'] ?? '') === 'ack') {
            $ackDevice = $msg['device'] ?? null;
            break 2;
        }
    }
    usleep(20000);
}

@fwrite($socket, shb_frame('', 0x8));
@fclose($socket);

if (!$connected) {
    fwrite(STDERR, "Dashboard WebSocket auth timeout\n");
    exit(1);
}
if (!is_array($ackDevice)) {
    fwrite(STDERR, "Device did not acknowledge control command\n");
    exit(1);
}

echo json_encode($ackDevice, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
