<?php
// Settings
$defaultSettings = [
    'goal_steps'    => 10000,
    'height'        => 170,
    'weight'        => 70,
    'stride_length' => 70,
    'birth_date'    => '1994-01-01',
];

function loadUserSettings(AppDatabase $db, array $defaults): array {
    $settings = $defaults;
    try {
        $result = $db->query('SELECT key, value FROM settings');
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key']] = $row['value'];
        }
    } catch (Exception $e) {
        error_log("Load settings failed: " . $e->getMessage());
    }
    $settings['goal_steps']    = (int)($settings['goal_steps']    ?? $defaults['goal_steps']);
    $settings['height']        = (int)($settings['height']        ?? $defaults['height']);
    $settings['weight']        = (int)($settings['weight']        ?? $defaults['weight']);
    $settings['stride_length'] = (int)($settings['stride_length'] ?? $defaults['stride_length']);
    $settings['birth_date']    = $settings['birth_date'] ?? $defaults['birth_date'];
    try {
        $bd = new DateTime($settings['birth_date']);
        $settings['age'] = (new DateTime())->diff($bd)->y;
    } catch (Exception $e) {
        $settings['age'] = 30;
    }
    return $settings;
}

function saveUserSetting(AppDatabase $db, string $key, string $value): void {
    $stmt = $db->prepare('INSERT INTO settings (key, value) VALUES (:k, :v) ON CONFLICT (key) DO UPDATE SET value=excluded.value');
    $stmt->bindValue(':k', $key, PDO::PARAM_STR);
    $stmt->bindValue(':v', $value, PDO::PARAM_STR);
    $stmt->execute();
}

