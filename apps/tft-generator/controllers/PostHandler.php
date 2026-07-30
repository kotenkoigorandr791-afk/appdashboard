<?php
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        echo json_encode(['status' => 'error', 'message' => 'Empty body']);
        exit;
    }
    $data = json_decode($rawInput, true);
    if ($data === null) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
        exit;
    }
    $udb = getUserDB();
    if ($udb) {
        try {
            $udb->exec('BEGIN');
            if (isset($data['type']) && $data['type'] === 'canvas') {
                // Always save canvas data, even if shapes array is empty
                $canvasPayload = $data['data'] ?? ['shapes' => [], 'bgColor' => '#000000'];
                // Ensure shapes is always an array (never null)
                if (!isset($canvasPayload['shapes']) || !is_array($canvasPayload['shapes'])) {
                    $canvasPayload['shapes'] = [];
                }
                $val = json_encode($canvasPayload);
                $stmt = $udb->prepare('INSERT INTO canvas (key, value) VALUES (:k, :v) ON CONFLICT (key) DO UPDATE SET value=excluded.value');
                $stmt->bindValue(':k', 'main', PDO::PARAM_STR);
                $stmt->bindValue(':v', $val, PDO::PARAM_STR);
                $stmt->execute();
            } elseif (isset($data['type']) && $data['type'] === 'gears_settings') {
                // Save gears settings with separate key
                $val = json_encode($data['data'] ?? $data);
                $stmt = $udb->prepare('INSERT INTO config (key, value) VALUES (:k, :v) ON CONFLICT (key) DO UPDATE SET value=excluded.value');
                $stmt->bindValue(':k', 'gears_settings', PDO::PARAM_STR);
                $stmt->bindValue(':v', $val, PDO::PARAM_STR);
                $stmt->execute();
            } else {
                $val = json_encode($data);
                $stmt = $udb->prepare('INSERT INTO config (key, value) VALUES (:k, :v) ON CONFLICT (key) DO UPDATE SET value=excluded.value');
                $stmt->bindValue(':k', 'main', PDO::PARAM_STR);
                $stmt->bindValue(':v', $val, PDO::PARAM_STR);
                $stmt->execute();
            }
            $udb->exec('COMMIT');
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            try { $udb->exec('ROLLBACK'); } catch (Exception $re) {}
            error_log("DB write error: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'DB unavailable']);
    }
    exit;
}
