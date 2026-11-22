<?php
/**
 * Live location websocket tunnel for Steps.
 *
 *   php apps/steps/bin/geolocation_live_ws.php 127.0.0.1 8768
 *
 * Devices authenticate with a short-lived ticket from geolocation_live and push
 * fixes; viewers in the same owner room receive broadcasts. Presence is also
 * written to PostgreSQL so the HTTP poll path stays warm.
 */
declare(strict_types=1);

$host = $argv[1] ?? '127.0.0.1';
$port = isset($argv[2]) ? (int)$argv[2] : 8768;

require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_ingestion.php';

$server = stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "steps live ws failed: {$errstr} ({$errno})\n");
    exit(1);
}
stream_set_blocking($server, false);

$clients = [];
$handshakes = [];
$buffers = [];
$meta = []; // id => [email, role, device_id]
$rooms = []; // email => [clientId => true]

function steps_live_ticket_dirs(): array
{
    if (!function_exists('appdata_base_dir')) require_once dirname(__DIR__, 3) . '/shell/storage_helper.php';
    return [rtrim(appdata_base_dir(), '/') . '/steps_runtime/live_ws_tickets'];
}

function steps_live_validate_ticket(string $ticket): ?array
{
    $ticket = preg_replace('/[^a-f0-9]/', '', strtolower($ticket)) ?? '';
    if ($ticket === '' || strlen($ticket) < 16) return null;
    foreach (steps_live_ticket_dirs() as $dir) {
        $file = $dir . '/' . $ticket . '.json';
        if (!is_file($file)) continue;
        $raw = @file_get_contents($file);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        @unlink($file);
        if (!is_array($data) || empty($data['email'])) return null;
        if ((int)($data['exp'] ?? 0) < time()) return null;
        return [
            'email' => StepsGeolocationDomain::normalizeOwner((string)$data['email']),
            'role' => in_array(($data['role'] ?? 'viewer'), ['device', 'viewer'], true) ? (string)$data['role'] : 'viewer',
            'device_id' => (string)($data['device_id'] ?? ''),
        ];
    }
    return null;
}

function steps_live_encode(string $payload): string
{
    $length = strlen($payload);
    $head = chr(0x81);
    if ($length < 126) $head .= chr($length);
    elseif ($length < 65536) $head .= chr(126) . pack('n', $length);
    else $head .= chr(127) . pack('J', $length);
    return $head . $payload;
}

function steps_live_decode(string $data): ?string
{
    if (strlen($data) < 2) return null;
    $b1 = ord($data[1]);
    $masked = ($b1 & 0x80) !== 0;
    $len = $b1 & 0x7f;
    $offset = 2;
    if ($len === 126) {
        if (strlen($data) < 4) return null;
        $len = unpack('n', substr($data, 2, 2))[1];
        $offset = 4;
    } elseif ($len === 127) {
        if (strlen($data) < 10) return null;
        $len = unpack('J', substr($data, 2, 8))[1];
        $offset = 10;
    }
    if ($masked) {
        if (strlen($data) < $offset + 4 + $len) return null;
        $mask = substr($data, $offset, 4);
        $offset += 4;
        $payload = substr($data, $offset, $len);
        $out = '';
        for ($i = 0; $i < $len; $i++) $out .= $payload[$i] ^ $mask[$i % 4];
        return $out;
    }
    if (strlen($data) < $offset + $len) return null;
    return substr($data, $offset, $len);
}

function steps_live_send($client, string $payload): void
{
    @fwrite($client, steps_live_encode($payload));
}

function steps_live_broadcast(array &$rooms, array &$clients, string $email, string $payload, $except = null): void
{
    foreach (array_keys($rooms[$email] ?? []) as $id) {
        if (!isset($clients[$id])) continue;
        if ($except !== null && $clients[$id] === $except) continue;
        steps_live_send($clients[$id], $payload);
    }
}

function steps_live_upsert(PDO $pdo, string $owner, ?string $deviceId, float $lat, float $lon, ?float $acc, ?float $bearing, string $at): void
{
    $pdo->prepare(
        "INSERT INTO geo_live_presence(user_email, device_id, position, accuracy_m, bearing_degrees, source_at, updated_at)
         VALUES(:o, :d, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), :a, :b, :at, now())
         ON CONFLICT (user_email) DO UPDATE SET
           device_id = EXCLUDED.device_id,
           position = EXCLUDED.position,
           accuracy_m = EXCLUDED.accuracy_m,
           bearing_degrees = EXCLUDED.bearing_degrees,
           source_at = EXCLUDED.source_at,
           updated_at = now()"
    )->execute([
        ':o' => $owner,
        ':d' => ($deviceId && StepsGeolocationDomain::isUuid($deviceId)) ? $deviceId : null,
        ':lon' => $lon,
        ':lat' => $lat,
        ':a' => $acc,
        ':b' => $bearing,
        ':at' => $at,
    ]);
}

$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();
$lastPurge = 0;

fwrite(STDOUT, "steps live ws listening on {$host}:{$port}\n");

while (true) {
    $read = array_values($clients);
    $read[] = $server;
    $write = null;
    $except = null;
    if (@stream_select($read, $write, $except, 1) === false) continue;

    if (in_array($server, $read, true)) {
        $conn = @stream_socket_accept($server, 0);
        if (is_resource($conn)) {
            stream_set_blocking($conn, false);
            $id = (int)$conn;
            $clients[$id] = $conn;
            $handshakes[$id] = false;
            $buffers[$id] = '';
        }
    }

    foreach ($read as $conn) {
        if ($conn === $server) continue;
        $id = (int)$conn;
        $chunk = @fread($conn, 8192);
        if ($chunk === '' || $chunk === false) {
            if (feof($conn)) {
                $email = $meta[$id]['email'] ?? null;
                if ($email) unset($rooms[$email][$id]);
                unset($clients[$id], $handshakes[$id], $buffers[$id], $meta[$id]);
                @fclose($conn);
            }
            continue;
        }
        $buffers[$id] = ($buffers[$id] ?? '') . $chunk;

        if (empty($handshakes[$id])) {
            if (!str_contains($buffers[$id], "\r\n\r\n")) continue;
            if (!preg_match('/Sec-WebSocket-Key:\s*(\S+)/i', $buffers[$id], $mKey)) {
                unset($clients[$id], $handshakes[$id], $buffers[$id]);
                @fclose($conn);
                continue;
            }
            $ticket = '';
            if (preg_match('/GET\s+[^\s]*[?&]ticket=([a-f0-9]+)/i', $buffers[$id], $mTicket)) {
                $ticket = $mTicket[1];
            }
            $auth = steps_live_validate_ticket($ticket);
            if ($auth === null) {
                @fwrite($conn, "HTTP/1.1 401 Unauthorized\r\nConnection: close\r\n\r\n");
                unset($clients[$id], $handshakes[$id], $buffers[$id]);
                @fclose($conn);
                continue;
            }
            $accept = base64_encode(sha1($mKey[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
            @fwrite($conn, "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: {$accept}\r\n\r\n");
            $handshakes[$id] = true;
            $buffers[$id] = '';
            $meta[$id] = $auth;
            $rooms[$auth['email']][$id] = true;
            steps_live_send($conn, json_encode(['type' => 'hello', 'role' => $auth['role']], JSON_UNESCAPED_SLASHES));
            continue;
        }

        $payload = steps_live_decode($buffers[$id]);
        $buffers[$id] = '';
        if ($payload === null || $payload === '') continue;
        $msg = json_decode($payload, true);
        if (!is_array($msg)) continue;
        $type = (string)($msg['type'] ?? '');
        $info = $meta[$id] ?? null;
        if ($info === null) continue;

        if ($type === 'ping') {
            steps_live_send($conn, json_encode(['type' => 'pong', 't' => time()], JSON_UNESCAPED_SLASHES));
            continue;
        }

        if ($type === 'fix' && ($info['role'] ?? '') === 'device') {
            $lat = $msg['latitude'] ?? null;
            $lon = $msg['longitude'] ?? null;
            if (!is_numeric($lat) || !is_numeric($lon) || !StepsGeolocationDomain::isCoordinate((float)$lat, (float)$lon)) continue;
            $at = (string)($msg['source_at'] ?? gmdate('c'));
            if (strtotime($at) === false) $at = gmdate('c');
            $acc = isset($msg['accuracy_m']) && is_numeric($msg['accuracy_m']) ? (float)$msg['accuracy_m'] : null;
            $bearing = isset($msg['bearing_degrees']) && is_numeric($msg['bearing_degrees']) ? (float)$msg['bearing_degrees'] : null;
            $deviceId = (string)($info['device_id'] ?: ($msg['device_id'] ?? ''));
            try {
                steps_live_upsert($pdo, $info['email'], $deviceId !== '' ? $deviceId : null, (float)$lat, (float)$lon, $acc, $bearing, $at);
                if ($deviceId !== '' && StepsGeolocationDomain::isUuid($deviceId)) {
                    $eventId = (string)($msg['client_event_id'] ?? StepsGeolocationIngestion::uuid());
                    StepsGeolocationIngestion::point($pdo, $info['email'], [
                        'device_id' => $deviceId,
                        'client_event_id' => $eventId,
                        'latitude' => (float)$lat,
                        'longitude' => (float)$lon,
                        'accuracy_m' => $acc,
                        'bearing_degrees' => $bearing,
                        'source_type' => 'mobile',
                        'source_at' => $at,
                        'device_name' => 'Live',
                        'platform' => 'android',
                    ]);
                }
            } catch (Throwable $e) {
                error_log('steps live ws upsert: ' . $e->getMessage());
            }
            $out = json_encode([
                'type' => 'presence',
                'latitude' => (float)$lat,
                'longitude' => (float)$lon,
                'accuracy_m' => $acc,
                'bearing_degrees' => $bearing,
                'source_at' => $at,
            ], JSON_UNESCAPED_SLASHES);
            steps_live_broadcast($rooms, $clients, $info['email'], $out);
        }
    }

    if (time() - $lastPurge > 300) {
        $lastPurge = time();
        foreach (steps_live_ticket_dirs() as $dir) {
            foreach (glob($dir . '/*.json') ?: [] as $file) {
                $raw = @file_get_contents($file);
                $data = is_string($raw) ? json_decode($raw, true) : null;
                if (!is_array($data) || (int)($data['exp'] ?? 0) < time()) @unlink($file);
            }
        }
    }
}
