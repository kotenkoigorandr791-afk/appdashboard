<?php
session_start();
require_once __DIR__ . '/core/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$sessionId = session_id() ?: ($_COOKIE['PHPSESSID'] ?? '');
if (!$sessionId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No session']);
    exit;
}

dismissNotification($sessionId);
echo json_encode(['success' => true]);
