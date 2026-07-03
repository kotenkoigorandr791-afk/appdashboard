<?php
declare(strict_types=1);
const CLOUD_SYNC_PROTOCOL_VERSION = 1;
const CLOUD_SYNC_WS_PATH = '/cloud-sync-ws';
const CLOUD_SYNC_DEFAULT_PORT = 8770;
const CLOUD_SYNC_CHUNK_BYTES = 262144;
const CLOUD_SYNC_MANIFEST_PAGE = 500;
const CLOUD_SYNC_ALLOWED_ROOTS = ['main', 'archive', 'temp', 'documents', 'photos', 'pdf', 'trash', 'clientsync'];
function cloud_sync_encode(array $msg): string {
    return json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}
function cloud_sync_decode(string $raw): ?array {
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}
function cloud_sync_error(string $code, string $message, ?string $reqId = null): array {
    $out = ['op' => 'error', 'code' => $code, 'message' => $message];
    if ($reqId !== null && $reqId !== '') $out['req_id'] = $reqId;
    return $out;
}
