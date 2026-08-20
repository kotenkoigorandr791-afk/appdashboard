<?php
public_api_auth_user(true);
public_api_require_role(2);
$parts = public_api_parse_path();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$key = isset($parts[1]) ? rawurldecode((string)$parts[1]) : '';
if ($key === '' && $method === 'GET') {
    $db = getDB();
    $keys = [];
    if ($db) {
        $res = $db->query('SELECT key FROM config ORDER BY key ASC');
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $keys[] = (string)$row['key'];
        }
    }
    public_api_json(['ok' => true, 'keys' => $keys]);
}
if ($key === '') public_api_json(['ok' => false, 'error' => 'missing_key'], 400);
if ($method === 'GET') {
    public_api_json(['ok' => true, 'key' => $key, 'value' => getConfig($key, '')]);
}
if ($method === 'PUT') {
    $body = public_api_body();
    if (!array_key_exists('value', $body)) public_api_json(['ok' => false, 'error' => 'missing_value'], 400);
    setConfig($key, (string)$body['value']);
    public_api_json(['ok' => true, 'key' => $key, 'value' => getConfig($key, '')]);
}
public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
