<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}
define('CLOUD_SYNC_WS_BOOT', true);
require dirname(__DIR__) . '/api.php';
require_once dirname(__DIR__) . '/includes/cloud_sync_protocol.php';
require_once dirname(__DIR__) . '/includes/cloud_sync_engine.php';
$host = $argv[1] ?? (getenv('CLOUD_SYNC_WS_BIND') ?: '127.0.0.1');
$port = isset($argv[2]) ? (int)$argv[2] : (int)(getenv('CLOUD_SYNC_WS_PORT') ?: CLOUD_SYNC_DEFAULT_PORT);
$server = stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "Cloud sync WebSocket failed: {$errstr} ({$errno})\n");
    exit(1);
}
stream_set_blocking($server, false);
$clients = [];
$handshakes = [];
$sessions = [];
$clientBuffers = [];
function csws_encode(string $payload): string {
    $len = strlen($payload);
    if ($len < 126) return chr(0x81) . chr($len) . $payload;
    if ($len <= 65535) return chr(0x81) . chr(126) . pack('n', $len) . $payload;
    return chr(0x81) . chr(127) . pack('J', $len) . $payload;
}
function csws_try_decode(string $data): ?array {
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
        for ($i = 0; $i < $length; $i++) $decoded .= $payload[$i] ^ $mask[$i % 4];
        $payload = $decoded;
    }
    return ['payload' => $payload, 'consumed' => $frameLen];
}
function csws_handshake($client, string $buffer): bool {
    if (!preg_match("/Sec-WebSocket-Key: (.*)\r\n/i", $buffer, $matches)) return false;
    $accept = base64_encode(sha1(trim($matches[1]) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    fwrite($client, "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: {$accept}\r\n\r\n");
    return true;
}
function csws_send($client, array $msg): void {
    @fwrite($client, csws_encode(cloud_sync_encode($msg)));
}
function csws_remove(string $clientId, array &$clients, array &$handshakes, array &$sessions, array &$clientBuffers): void {
    if (isset($clients[$clientId]) && is_resource($clients[$clientId])) fclose($clients[$clientId]);
    unset($clients[$clientId], $handshakes[$clientId], $sessions[$clientId], $clientBuffers[$clientId]);
}
function csws_auth_token(string $token): ?string {
    if ($token === '') return null;
    $db = getDB();
    if (!$db) return null;
    try {
        ensure_client_auth_tokens_table();
        $stmt = $db->prepare("SELECT email FROM client_auth_tokens WHERE token_hash = :h AND app_id = 'cloud' AND revoked = 0 AND expires_at > :now LIMIT 1");
        $stmt->bindValue(':h', hash('sha256', $token), PDO::PARAM_STR);
        $stmt->bindValue(':now', time(), PDO::PARAM_INT);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['email'])) {
            $upd = $db->prepare('UPDATE client_auth_tokens SET last_used_at = :t WHERE token_hash = :h');
            $upd->bindValue(':t', time(), PDO::PARAM_INT);
            $upd->bindValue(':h', hash('sha256', $token), PDO::PARAM_STR);
            $upd->execute();
            return (string)$row['email'];
        }
    } catch (Exception $e) {
        cloud_safe_error_log('csws_auth_token: ' . $e->getMessage());
    }
    return null;
}
function csws_dispatch($client, string $clientId, array $msg, array &$sessions): void {
    $reqId = (string)($msg['req_id'] ?? '');
    $op = (string)($msg['op'] ?? '');
    if ($op === 'ping') {
        csws_send($client, ['op' => 'pong', 'req_id' => $reqId, 'ts' => time()]);
        return;
    }
    if ($op === 'auth') {
        $token = trim((string)($msg['token'] ?? ''));
        $email = csws_auth_token($token);
        if ($email === null) {
            csws_send($client, cloud_sync_error('auth_failed', 'Invalid or expired token', $reqId));
            return;
        }
        $user = email_to_cloud_username($email);
        $root = (string)($msg['root'] ?? 'main');
        $basePath = (string)($msg['base_path'] ?? '');
        $scope = cloud_sync_resolve_scope($user, $root, $basePath);
        if (!$scope['ok']) {
            csws_send($client, cloud_sync_error('scope_failed', (string)($scope['error'] ?? 'scope'), $reqId));
            return;
        }
        $GLOBALS['cloud_sync_email'] = $email;
        $sessions[$clientId] = [
            'email' => $email,
            'user' => $user,
            'device_id' => trim((string)($msg['device_id'] ?? '')),
            'root' => $scope['root'],
            'base_path' => $basePath,
            'scope' => $scope,
            'bus_offset' => (int)@filesize(cloud_sync_bus_channel($email)),
            'manifest_entries' => null,
            'manifest_generated_at' => 0,
        ];
        cloud_sync_touch_last($email);
        csws_send($client, [
            'op' => 'auth_ok',
            'req_id' => $reqId,
            'protocol' => CLOUD_SYNC_PROTOCOL_VERSION,
            'user' => $user,
            'email' => $email,
            'root' => $scope['root'],
            'base_path' => $basePath,
            'sync_mode' => cloud_sync_user_mode($email),
            'ws_path' => CLOUD_SYNC_WS_PATH,
        ]);
        return;
    }
    if (empty($sessions[$clientId])) {
        csws_send($client, cloud_sync_error('not_authenticated', 'Send auth first', $reqId));
        return;
    }
    $sess = $sessions[$clientId];
    $GLOBALS['cloud_sync_email'] = $sess['email'];
    $scope = $sess['scope'];
    switch ($op) {
        case 'manifest_request':
            $offset = max(0, (int)($msg['offset'] ?? 0));
            $limit = max(1, min((int)($msg['limit'] ?? CLOUD_SYNC_MANIFEST_PAGE), CLOUD_SYNC_MANIFEST_PAGE));
            if ($offset === 0 || empty($sess['manifest_entries'])) {
                $built = cloud_sync_manifest_build($sess['user'], $sess['root'], $sess['base_path'], $sess['email']);
                if (!$built['ok']) {
                    csws_send($client, cloud_sync_error('manifest_failed', (string)($built['error'] ?? 'manifest'), $reqId));
                    break;
                }
                $sess['manifest_entries'] = $built['entries'];
                $sess['manifest_generated_at'] = (int)$built['generated_at'];
                $sessions[$clientId] = $sess;
            }
            $page = cloud_sync_manifest_page(
                $sess['user'],
                $sess['root'],
                $sess['base_path'],
                $offset,
                $limit,
                $sess['email'],
                $sess['manifest_entries']
            );
            $page['req_id'] = $reqId;
            csws_send($client, $page);
            if (!empty($page['done'])) {
                unset($sess['manifest_entries'], $sess['manifest_generated_at']);
                $sessions[$clientId] = $sess;
                cloud_sync_touch_last($sess['email']);
            }
            break;
        case 'read':
            $res = cloud_sync_read_file($scope, (string)($msg['path'] ?? ''), (int)($msg['offset'] ?? 0), (int)($msg['length'] ?? CLOUD_SYNC_CHUNK_BYTES));
            $res['req_id'] = $reqId;
            csws_send($client, $res['ok'] ? $res : cloud_sync_error('read_failed', (string)($res['error'] ?? 'read'), $reqId));
            break;
        case 'write':
            $res = cloud_sync_write_file(
                $scope,
                $sess['email'],
                (string)($msg['path'] ?? ''),
                (int)($msg['offset'] ?? 0),
                (string)($msg['data_b64'] ?? ''),
                !empty($msg['truncate']),
                !array_key_exists('close', $msg) || !empty($msg['close'])
            );
            $res['req_id'] = $reqId;
            csws_send($client, $res['ok'] ? $res : cloud_sync_error('write_failed', (string)($res['error'] ?? 'write'), $reqId));
            break;
        case 'mkdir':
            $res = cloud_sync_mkdir($scope, $sess['email'], (string)($msg['path'] ?? ''));
            $res['req_id'] = $reqId;
            csws_send($client, $res['ok'] ? $res : cloud_sync_error('mkdir_failed', (string)($res['error'] ?? 'mkdir'), $reqId));
            break;
        case 'delete':
            $res = cloud_sync_delete_path($scope, $sess['email'], (string)($msg['path'] ?? ''), !empty($msg['is_dir']));
            $res['req_id'] = $reqId;
            csws_send($client, $res['ok'] ? $res : cloud_sync_error('delete_failed', (string)($res['error'] ?? 'delete'), $reqId));
            break;
        case 'rename':
            $res = cloud_sync_rename_path($scope, $sess['email'], (string)($msg['from'] ?? ''), (string)($msg['to'] ?? ''));
            $res['req_id'] = $reqId;
            csws_send($client, $res['ok'] ? $res : cloud_sync_error('rename_failed', (string)($res['error'] ?? 'rename'), $reqId));
            break;
        case 'events_poll':
            $off = array_key_exists('offset', $msg) ? (int)$msg['offset'] : (int)$sess['bus_offset'];
            $bus = cloud_sync_bus_read_since($sess['email'], $off);
            $sess['bus_offset'] = (int)$bus['offset'];
            $sessions[$clientId] = $sess;
            csws_send($client, ['op' => 'events', 'req_id' => $reqId, 'offset' => $bus['offset'], 'events' => $bus['events']]);
            break;
        default:
            csws_send($client, cloud_sync_error('unknown_op', 'Unknown op: ' . $op, $reqId));
    }
}
function csws_push_bus_events(array &$clients, array &$sessions): void {
    foreach ($sessions as $clientId => $sess) {
        if (empty($clients[$clientId])) continue;
        $bus = cloud_sync_bus_read_since($sess['email'], (int)$sess['bus_offset']);
        if (empty($bus['events'])) continue;
        $sess['bus_offset'] = (int)$bus['offset'];
        $sessions[$clientId] = $sess;
        foreach ($bus['events'] as $ev) {
            if (($ev['root'] ?? '') !== $sess['root']) continue;
            csws_send($clients[$clientId], $ev);
        }
    }
}
function csws_process_buffer($socket, string $clientId, array &$clients, array &$handshakes, array &$sessions, array &$clientBuffers): void {
    while (true) {
        if (empty($handshakes[$clientId])) {
            if (strpos($clientBuffers[$clientId], "\r\n\r\n") === false) return;
            if (csws_handshake($socket, $clientBuffers[$clientId])) {
                $handshakes[$clientId] = true;
                $clientBuffers[$clientId] = '';
            } else {
                csws_remove($clientId, $clients, $handshakes, $sessions, $clientBuffers);
            }
            return;
        }
        $frame = csws_try_decode($clientBuffers[$clientId]);
        if ($frame === null) return;
        $clientBuffers[$clientId] = substr($clientBuffers[$clientId], $frame['consumed']);
        if ($frame['payload'] === '__CLOSE__') {
            csws_remove($clientId, $clients, $handshakes, $sessions, $clientBuffers);
            return;
        }
        $msg = cloud_sync_decode($frame['payload']);
        if (!is_array($msg)) continue;
        csws_dispatch($socket, $clientId, $msg, $sessions);
    }
}
fwrite(STDOUT, "Cloud sync WebSocket on {$host}:{$port}\n");
while (true) {
    csws_push_bus_events($clients, $sessions);
    $read = array_merge([$server], array_values($clients));
    $write = null;
    $except = null;
    if (@stream_select($read, $write, $except, 0, 200000) === false) continue;
    foreach ($read as $socket) {
        if ($socket === $server) {
            $client = @stream_socket_accept($server, 0);
            if ($client) {
                stream_set_blocking($client, false);
                $id = (string)(int)$client;
                $clients[$id] = $client;
                $handshakes[$id] = false;
                $clientBuffers[$id] = '';
            }
            continue;
        }
        $clientId = (string)(int)$socket;
        $chunk = @fread($socket, 1048576);
        if ($chunk === '' || $chunk === false) {
            csws_remove($clientId, $clients, $handshakes, $sessions, $clientBuffers);
            continue;
        }
        $clientBuffers[$clientId] = ($clientBuffers[$clientId] ?? '') . $chunk;
        csws_process_buffer($socket, $clientId, $clients, $handshakes, $sessions, $clientBuffers);
    }
}
