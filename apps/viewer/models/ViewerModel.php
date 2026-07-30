<?php
function viewerLoadConfigArray(AppDatabase $db) {
    $row = $db->querySingle('SELECT theme, language, tile_size, sidebar_width, header_color, tiles_per_row, token FROM viewer_meta WHERE id=1', true);
    if (!$row) {
        return [
            'theme' => 'light',
            'language' => 'en',
            'tileSize' => 50,
            'sidebarWidth' => 300,
            'headerColor' => '#34a853',
            'tilesPerRow' => 20,
            'sites' => [],
            'token' => '',
        ];
    }
    $sites = [];
    $res = $db->query('SELECT name, url, priority, hidden, use_token FROM viewer_sites ORDER BY sort_order ASC, id ASC');
    while ($s = $res->fetch(PDO::FETCH_ASSOC)) {
        $sites[] = [
            'name' => $s['name'],
            'url' => $s['url'],
            'priority' => (int)$s['priority'],
            'hidden' => (bool)$s['hidden'],
            'use_token' => (bool)$s['use_token'],
        ];
    }
    return [
        'theme' => $row['theme'],
        'language' => $row['language'],
        'tileSize' => (int)$row['tile_size'],
        'sidebarWidth' => (int)$row['sidebar_width'],
        'headerColor' => $row['header_color'],
        'tilesPerRow' => (int)$row['tiles_per_row'],
        'sites' => $sites,
        'token' => $row['token'],
    ];
}

function viewerEnsureToken(AppDatabase $db) {
    $t = $db->querySingle('SELECT token FROM viewer_meta WHERE id=1');
    if ($t === null || $t === '') {
        $token = bin2hex(random_bytes(16));
        $st = $db->prepare('UPDATE viewer_meta SET token=:tk WHERE id=1');
        $st->bindValue(':tk', $token, PDO::PARAM_STR);
        $st->execute();
        return $token;
    }
    return (string)$t;
}

function viewerSaveMetaFromArray(AppDatabase $db, array $c) {
    $st = $db->prepare('UPDATE viewer_meta SET theme=:th, language=:la, tile_size=:ts, sidebar_width=:sw, header_color=:hc, tiles_per_row=:tpr WHERE id=1');
    $st->bindValue(':th', isset($c['theme']) ? (string)$c['theme'] : 'light', PDO::PARAM_STR);
    $st->bindValue(':la', isset($c['language']) ? (string)$c['language'] : 'en', PDO::PARAM_STR);
    $st->bindValue(':ts', isset($c['tileSize']) ? (int)$c['tileSize'] : 50, PDO::PARAM_INT);
    $st->bindValue(':sw', isset($c['sidebarWidth']) ? (int)$c['sidebarWidth'] : 300, PDO::PARAM_INT);
    $st->bindValue(':hc', isset($c['headerColor']) ? (string)$c['headerColor'] : '#34a853', PDO::PARAM_STR);
    $st->bindValue(':tpr', isset($c['tilesPerRow']) ? (int)$c['tilesPerRow'] : 20, PDO::PARAM_INT);
    $st->execute();
}

function viewerReplaceSites(AppDatabase $db, array $sites) {
    $db->exec('BEGIN');
    $db->exec('DELETE FROM viewer_sites');
    $ins = $db->prepare('INSERT INTO viewer_sites (sort_order, name, url, priority, hidden, use_token) VALUES (:so,:n,:u,:p,:h,:ut)');
    foreach ($sites as $i => $site) {
        if (!is_array($site)) continue;
        $ins->reset();
        $ins->bindValue(':so', (int)$i, PDO::PARAM_INT);
        $ins->bindValue(':n', (string)($site['name'] ?? ''), PDO::PARAM_STR);
        $ins->bindValue(':u', (string)($site['url'] ?? ''), PDO::PARAM_STR);
        $ins->bindValue(':p', isset($site['priority']) ? (int)$site['priority'] : 0, PDO::PARAM_INT);
        $ins->bindValue(':h', !empty($site['hidden']) ? 1 : 0, PDO::PARAM_INT);
        $ut = isset($site['use_token']) ? $site['use_token'] : false;
        $ins->bindValue(':ut', ($ut === true || $ut === 1 || $ut === '1') ? 1 : 0, PDO::PARAM_INT);
        $ins->execute();
    }
    $db->exec('COMMIT');
}

function viewerUpdateToken(AppDatabase $db, $newToken) {
    if ($newToken === null || $newToken === '') {
        $newToken = bin2hex(random_bytes(16));
    } else {
        $newToken = (string)$newToken;
    }
    $st = $db->prepare('UPDATE viewer_meta SET token=:tk WHERE id=1');
    $st->bindValue(':tk', $newToken, PDO::PARAM_STR);
    $st->execute();
    return $newToken;
}
