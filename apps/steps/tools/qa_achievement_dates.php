<?php
/**
 * Recompute one owner's achievements and print the earned dates so the timeline
 * can be inspected against real history.
 *
 *   php apps/steps/tools/qa_achievement_dates.php root@root [--recompute]
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_achievements.php';

$owner = strtolower(trim((string)($argv[1] ?? '')));
if ($owner === '') {
    fwrite(STDERR, "usage: php qa_achievement_dates.php <owner-email> [--recompute]\n");
    exit(2);
}
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

if (in_array('--recompute', $argv, true)) {
    $result = StepsGeolocationAchievements::recalculate($pdo, $owner, 'manual');
    echo json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";
}

$progress = StepsGeolocationAchievements::progress($pdo, $owner);
$earned = [];
foreach ($progress['achievements'] as $row) {
    if ($row['earned']) $earned[] = ['id' => $row['id'], 'target' => $row['target'], 'earned_at' => $row['earned_at']];
}
usort($earned, static fn(array $a, array $b): int => strcmp((string)$a['earned_at'], (string)$b['earned_at']));
echo 'rules_version=' . ($progress['rules_version'] ?? '?') . ' earned=' . count($earned) . ' undated=' . count(array_filter($earned, static fn(array $r): bool => $r['earned_at'] === null)) . "\n";
foreach ($earned as $row) {
    echo str_pad((string)($row['earned_at'] ?? 'null'), 22) . $row['id'] . "\n";
}
