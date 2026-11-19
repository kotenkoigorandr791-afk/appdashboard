<?php
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
$pdo = app_main_db()->getPdo();
$owner = $argv[1] ?? 'root@root';
$st = $pdo->prepare("SELECT source_at, ST_Y(position) lat, ST_X(position) lon, quality_state FROM geo_location_points WHERE user_email = :o AND quality_state NOT IN ('rejected','excluded_by_user','private_redacted') ORDER BY source_at ASC, id ASC");
$st->execute([':o' => $owner]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
$hav = static function (float $a, float $b, float $c, float $d): float {
    $r = 6371000.0;
    $dLat = deg2rad($c - $a);
    $dLon = deg2rad($d - $b);
    $q = sin($dLat / 2) ** 2 + cos(deg2rad($a)) * cos(deg2rad($c)) * sin($dLon / 2) ** 2;
    return 2 * $r * asin(min(1.0, sqrt($q)));
};
$caps = [450, 150, 90, 55, 40];
$out = [];
$prev = null;
foreach ($rows as $row) {
    $at = strtotime((string)$row['source_at']);
    $day = gmdate('Y-m-d', $at);
    if ($prev !== null) {
        $gap = $at - $prev['at'];
        $seg = $hav((float)$prev['lat'], (float)$prev['lon'], (float)$row['lat'], (float)$row['lon']);
        $prevDay = gmdate('Y-m-d', $prev['at']);
        if ($prevDay === $day && $gap > 0 && $gap <= 900) {
            foreach ($caps as $cap) {
                if ($seg <= 5000 || ($seg / $gap) <= $cap) {
                    $out[$cap][$day] = ($out[$cap][$day] ?? 0.0) + $seg;
                }
            }
        }
    }
    if ((string)$row['quality_state'] !== 'rejected') $prev = ['at' => $at, 'lat' => (float)$row['lat'], 'lon' => (float)$row['lon']];
}
$days = array_keys($out[450] ?? []);
sort($days);
echo 'day            ' . implode('        ', array_map(static fn($c) => str_pad((string)$c, 8), $caps)) . PHP_EOL;
$totals = array_fill_keys($caps, 0.0);
foreach ($days as $day) {
    echo $day . '  ';
    foreach ($caps as $cap) {
        $km = ($out[$cap][$day] ?? 0) / 1000;
        $totals[$cap] += $km;
        echo str_pad(number_format($km, 1, '.', ''), 8, ' ', STR_PAD_LEFT) . '  ';
    }
    echo PHP_EOL;
}
echo 'TOTAL          ';
foreach ($caps as $cap) echo str_pad(number_format($totals[$cap], 1, '.', ''), 8, ' ', STR_PAD_LEFT) . '  ';
echo PHP_EOL;
