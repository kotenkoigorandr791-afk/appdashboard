<?php

declare(strict_types=1);

$host = $argv[1] ?? '0.0.0.0';
$port = isset($argv[2]) ? (int)$argv[2] : 8765;

$server = stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "WebSocket server start failed: {$errstr} ({$errno})\n");
    exit(1);
}

stream_set_blocking($server, false);

$clients = [];
$handshakes = [];
$clientBuffers = [];
$rooms = [];
$meta = [];
$roomSnapshots = [];
$authed = [];

function ws_validate_ticket(string $ticket): ?string
{
    $ticket = preg_replace('/[^a-f0-9]/', '', strtolower($ticket));
    if ($ticket === '' || strlen($ticket) < 16) return null;
    $file = sys_get_temp_dir() . '/cloud_monaco_tickets/' . $ticket . '.json';
    if (!is_file($file)) return null;
    $raw = @file_get_contents($file);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data) || empty($data['email'])) return null;
    if ((int)($data['exp'] ?? 0) < time()) {
        @unlink($file);
        return null;
    }
    return strtolower(trim((string)$data['email']));
}

function ws_room_update_snapshot(string $roomKey, array $message, array &$roomSnapshots): bool
{
    if (!isset($message['content']) || !is_string($message['content'])) return false;
    $updatedAt = (int)($message['updatedAt'] ?? time());
    $current = $roomSnapshots[$roomKey] ?? null;
    if ($current !== null && $current['content'] === $message['content']) {
        return false;
    }
    $prevRevision = $current !== null ? (int)($current['revision'] ?? 0) : 0;
    $roomSnapshots[$roomKey] = [
        'content' => $message['content'],
        'clientId' => (string)($message['clientId'] ?? ''),
        'revision' => $prevRevision + 1,
        'updatedAt' => $updatedAt
    ];
    return true;
}

function ws_encode(string $payload): string
{
    $length = strlen($payload);
    $header = chr(0x81);
    if ($length < 126) {
        return $header . chr($length) . $payload;
    }
    if ($length <= 65535) {
        return $header . chr(126) . pack('n', $length) . $payload;
    }
    return $header . chr(127) . pack('J', $length) . $payload;
}

function ws_try_decode(string $data): ?array
{
    if ($data === '' || strlen($data) < 2) return null;
    $bytes = unpack('Cfirst/Csecond', substr($data, 0, 2));
    $opcode = $bytes['first'] & 0x0f;
    if ($opcode === 0x8) return ['payload' => '__CLOSE__', 'consumed' => 2];
    $masked = ($bytes['second'] & 0x80) === 0x80;
    $length = $bytes['second'] & 0x7f;
    $offset = 2;
    if ($length === 126) {
        if (strlen($data) < 4) return null;
        $length = unpack('n', substr($data, $offset, 2))[1];
        $offset += 2;
    } elseif ($length === 127) {
        if (strlen($data) < 10) return null;
        $parts = unpack('N2', substr($data, $offset, 8));
        $length = ($parts[1] << 32) | $parts[2];
        $offset += 8;
    }
    if ($masked) {
        if (strlen($data) < $offset + 4) return null;
        $mask = substr($data, $offset, 4);
        $offset += 4;
    } else {
        $mask = '';
    }
    $frameLen = $offset + $length;
    if (strlen($data) < $frameLen) return null;
    $payload = substr($data, $offset, $length);
    if ($masked && $length > 0) {
        $decoded = '';
        for ($i = 0; $i < $length; $i++) {
            $decoded .= $payload[$i] ^ $mask[$i % 4];
        }
        $payload = $decoded;
    }
    return ['payload' => $payload, 'consumed' => $frameLen];
}

function ws_handshake($client, string $buffer): bool
{
    if (!preg_match("/Sec-WebSocket-Key: (.*)\r\n/i", $buffer, $matches)) return false;
    $key = trim($matches[1]);
    $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    $response = implode("\r\n", [
        'HTTP/1.1 101 Switching Protocols',
        'Upgrade: websocket',
        'Connection: Upgrade',
        'Sec-WebSocket-Accept: ' . $accept,
        ''
    ]) . "\r\n";
    fwrite($client, $response);
    return true;
}

function ws_remove_client($clientId, array &$clients, array &$handshakes, array &$clientBuffers, array &$rooms, array &$meta, array &$authed = null): void
{
    foreach (array_keys($rooms) as $roomKey) {
        unset($rooms[$roomKey][$clientId]);
        if (empty($rooms[$roomKey])) unset($rooms[$roomKey]);
    }
    if (isset($clients[$clientId]) && is_resource($clients[$clientId])) {
        fclose($clients[$clientId]);
    }
    unset($clients[$clientId], $handshakes[$clientId], $clientBuffers[$clientId], $meta[$clientId]);
    if (is_array($authed)) unset($authed[$clientId]);
}

function ws_broadcast(string $roomKey, string $payload, string $excludeClientId, array $clients, array $rooms): void
{
    if (empty($rooms[$roomKey])) return;
    $frame = ws_encode($payload);
    foreach (array_keys($rooms[$roomKey]) as $clientId) {
        if ($clientId === $excludeClientId || empty($clients[$clientId])) continue;
        @fwrite($clients[$clientId], $frame);
    }
}

function ws_send_to_client(string $clientId, string $payload, array $clients): void
{
    if (empty($clients[$clientId])) return;
    @fwrite($clients[$clientId], ws_encode($payload));
}

function ws_room_presence(string $roomKey, array $meta, array $rooms): array
{
    $users = [];
    if (empty($rooms[$roomKey])) return $users;
    foreach (array_keys($rooms[$roomKey]) as $cid) {
        if (empty($meta[$cid]) || ($meta[$cid]['roomKey'] ?? '') !== $roomKey) continue;
        $users[] = [
            'clientId' => (string)($meta[$cid]['clientId'] ?? $cid),
            'name' => (string)($meta[$cid]['name'] ?? 'user'),
            'color' => (string)($meta[$cid]['color'] ?? '#3b82f6'),
        ];
    }
    return $users;
}

function ws_broadcast_presence(string $roomKey, array $clients, array $rooms, array $meta): void
{
    $payload = json_encode([
        'type' => 'presence',
        'roomKey' => $roomKey,
        'users' => ws_room_presence($roomKey, $meta, $rooms),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ws_broadcast($roomKey, $payload, '', $clients, $rooms);
}

function ws_dispatch_message(string $clientId, array $message, array &$clients, array &$rooms, array &$meta, array &$roomSnapshots, array &$authed): void
{
    if (($message['type'] ?? '') === 'auth') {
        $email = ws_validate_ticket((string)($message['ticket'] ?? ''));
        if ($email === null) {
            ws_send_to_client($clientId, json_encode(['type' => 'auth_error', 'error' => 'unauthorized'], JSON_UNESCAPED_UNICODE), $clients);
            return;
        }
        $authed[$clientId] = $email;
        ws_send_to_client($clientId, json_encode(['type' => 'auth_ok', 'email' => $email], JSON_UNESCAPED_UNICODE), $clients);
        return;
    }
    if (empty($authed[$clientId])) {
        ws_send_to_client($clientId, json_encode(['type' => 'auth_error', 'error' => 'auth required'], JSON_UNESCAPED_UNICODE), $clients);
        return;
    }
    if ($message['type'] === 'join' && !empty($message['roomKey'])) {
        $roomKey = (string)$message['roomKey'];
        $rooms[$roomKey][$clientId] = true;
        $logicalClientId = (string)($message['clientId'] ?? $clientId);
        $meta[$clientId] = [
            'roomKey' => $roomKey,
            'clientId' => $logicalClientId,
            'name' => (string)($message['name'] ?? 'user'),
            'color' => (string)($message['color'] ?? '#3b82f6')
        ];
        if (isset($message['content']) && is_string($message['content'])) {
            $incoming = $message['content'];
            $hasRoomSnapshot = !empty($roomSnapshots[$roomKey]);
            $incomingAt = (int)($message['updatedAt'] ?? time());
            $snapAt = $hasRoomSnapshot ? (int)($roomSnapshots[$roomKey]['updatedAt'] ?? 0) : 0;
            if (!$hasRoomSnapshot || $incomingAt >= $snapAt) {
                $message['revision'] = (int)($message['revision'] ?? 0);
                $message['updatedAt'] = $incomingAt;
                ws_room_update_snapshot($roomKey, $message, $roomSnapshots);
            }
        }
        if (!empty($roomSnapshots[$roomKey])) {
            $snap = $roomSnapshots[$roomKey];
            ws_send_to_client($clientId, json_encode([
                'type' => 'sync',
                'roomKey' => $roomKey,
                'content' => $snap['content'],
                'clientId' => $snap['clientId'],
                'revision' => $snap['revision'] ?? 0,
                'updatedAt' => $snap['updatedAt'] ?? 0
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $clients);
        }
        ws_broadcast_presence($roomKey, $clients, $rooms, $meta);
        return;
    }

    if ($message['type'] === 'content_snapshot' && !empty($message['roomKey'])) {
        $roomKey = (string)$message['roomKey'];
        $message['updatedAt'] = (int)($message['updatedAt'] ?? time());
        if (ws_room_update_snapshot($roomKey, $message, $roomSnapshots)) {
            $snap = $roomSnapshots[$roomKey];
            $outgoing = json_encode([
                'type' => 'content_snapshot',
                'roomKey' => $roomKey,
                'content' => $snap['content'],
                'clientId' => $snap['clientId'],
                'revision' => $snap['revision'] ?? 0,
                'updatedAt' => $snap['updatedAt'] ?? 0
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            ws_broadcast($roomKey, $outgoing, $clientId, $clients, $rooms);
        }
        return;
    }

    if ($message['type'] === 'sync_request' && !empty($message['roomKey'])) {
        $roomKey = (string)$message['roomKey'];
        if (!empty($roomSnapshots[$roomKey])) {
            $snap = $roomSnapshots[$roomKey];
            ws_send_to_client($clientId, json_encode([
                'type' => 'sync',
                'roomKey' => $roomKey,
                'content' => $snap['content'],
                'clientId' => $snap['clientId'],
                'revision' => $snap['revision'] ?? 0,
                'updatedAt' => $snap['updatedAt'] ?? 0
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $clients);
        }
        return;
    }

    if ($message['type'] === 'leave' && !empty($message['roomKey'])) {
        $roomKey = (string)$message['roomKey'];
        ws_broadcast($roomKey, json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $clientId, $clients, $rooms);
        unset($rooms[$roomKey][$clientId]);
        if (empty($rooms[$roomKey])) unset($rooms[$roomKey]);
        if (($meta[$clientId]['roomKey'] ?? null) === $roomKey) unset($meta[$clientId]['roomKey']);
        ws_broadcast_presence($roomKey, $clients, $rooms, $meta);
        return;
    }

    if (!empty($message['roomKey'])) {
        ws_broadcast((string)$message['roomKey'], json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $clientId, $clients, $rooms);
    }
}

function ws_process_buffer($socket, string $clientId, array &$clients, array &$handshakes, array &$clientBuffers, array &$rooms, array &$meta, array &$roomSnapshots, array &$authed): void
{
    while (true) {
        if (empty($handshakes[$clientId])) {
            if (strpos($clientBuffers[$clientId], "\r\n\r\n") === false) return;
            if (ws_handshake($socket, $clientBuffers[$clientId])) {
                $handshakes[$clientId] = true;
                $clientBuffers[$clientId] = '';
            } else {
                ws_remove_client($clientId, $clients, $handshakes, $clientBuffers, $rooms, $meta, $authed);
            }
            return;
        }

        $frame = ws_try_decode($clientBuffers[$clientId]);
        if ($frame === null) return;
        $clientBuffers[$clientId] = substr($clientBuffers[$clientId], $frame['consumed']);
        if ($frame['payload'] === '__CLOSE__') {
            ws_remove_client($clientId, $clients, $handshakes, $clientBuffers, $rooms, $meta, $authed);
            return;
        }

        $message = json_decode($frame['payload'], true);
        if (!is_array($message) || empty($message['type'])) continue;
        ws_dispatch_message($clientId, $message, $clients, $rooms, $meta, $roomSnapshots, $authed);
    }
}

fwrite(STDOUT, "Monaco collab WebSocket server listening on {$host}:{$port}\n");

while (true) {
    $read = array_merge([$server], array_values($clients));
    $write = null;
    $except = null;
    if (@stream_select($read, $write, $except, 1) === false) {
        continue;
    }

    foreach ($read as $socket) {
        if ($socket === $server) {
            $client = @stream_socket_accept($server, 0);
            if ($client) {
                stream_set_blocking($client, false);
                $clientId = (string)(int)$client;
                $clients[$clientId] = $client;
                $handshakes[$clientId] = false;
                $clientBuffers[$clientId] = '';
            }
            continue;
        }

        $clientId = (string)(int)$socket;
        $chunk = @fread($socket, 65535);
        if ($chunk === '' || $chunk === false) {
            ws_remove_client($clientId, $clients, $handshakes, $clientBuffers, $rooms, $meta, $authed);
            continue;
        }

        $clientBuffers[$clientId] = ($clientBuffers[$clientId] ?? '') . $chunk;
        ws_process_buffer($socket, $clientId, $clients, $handshakes, $clientBuffers, $rooms, $meta, $roomSnapshots, $authed);
    }
}
