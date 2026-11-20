<?php
require_once __DIR__ . '/store.php';
// Settings
$defaultSettings = [
    'occupation'    => 'prefer_not_to_say',
    'goal_steps'    => 10000,
    'height'        => 170,
    'weight'        => 70,
    'stride_length' => 70,
    'birth_date'    => '1994-01-01',
];

function steps_occupation_values(): array { return ['student', 'school_student', 'office_worker', 'remote_worker', 'freelancer', 'entrepreneur', 'self_employed', 'industrial_worker', 'driver_courier', 'taxi_driver', 'retail_worker', 'teacher', 'healthcare_worker', 'military', 'public_servant', 'retired', 'unemployed', 'homemaker', 'athlete', 'frequent_traveler', 'working_student', 'other', 'prefer_not_to_say']; }

function loadUserSettings(AppDatabase $db, array $defaults): array {
    $settings = $defaults;
    try {
        $stmt = $db->prepare('SELECT key, value FROM steps_settings WHERE user_email = :u');
        steps_bind_user($stmt);
        $result = $stmt->execute();
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
    $settings['birth_date']     = $settings['birth_date'] ?? $defaults['birth_date'];
    try {
        $bd = new DateTime($settings['birth_date']);
        $settings['age'] = (new DateTime())->diff($bd)->y;
    } catch (Exception $e) {
        $settings['age'] = 30;
    }
    return $settings;
}

function saveUserSetting(AppDatabase $db, string $key, string $value): void {
    $stmt = $db->prepare('INSERT INTO steps_settings (user_email, key, value) VALUES (:u, :k, :v) ON CONFLICT (user_email, key) DO UPDATE SET value=excluded.value');
    steps_bind_user($stmt);
    $stmt->bindValue(':k', $key, PDO::PARAM_STR);
    $stmt->bindValue(':v', $value, PDO::PARAM_STR);
    $stmt->execute();
}
