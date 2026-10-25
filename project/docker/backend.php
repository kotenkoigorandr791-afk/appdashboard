<?php
/**
 * Docker Manager — request handlers (view + action) + data loading.
 * Includes the HTML template for the main page.
 */
declare(strict_types=1);

require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/views.php';

$view = $_GET['view'] ?? '';
$action = $_GET['action'] ?? '';
function validContainerIdParam($id) {
    return is_string($id) && preg_match('/^[a-fA-F0-9]{12,64}$/', $id);
}
if ($view === 'logs' && isset($_GET['id'])) {
    $lid = $_GET['id'];
    if (!validContainerIdParam($lid)) {
        viewPageShell(t('docker_error'), '<h1>' . htmlspecialchars(t('docker_invalid_id'), ENT_QUOTES, 'UTF-8') . '</h1>');
        exit;
    }
    $lr = callDockerAPI(dockerApi('containers/' . rawurlencode($lid) . '/logs?stdout=true&stderr=true&tail=500'), 'GET', null, 90);
    $rawLog = is_string($lr['raw'] ?? null) ? $lr['raw'] : '';
    $text = ($lr['code'] === 200) ? dockerDecodeLogStream($rawLog) : ('HTTP ' . $lr['code']);
    $copyId = 'log_' . bin2hex(random_bytes(4));
    $copyTxt = json_encode(t('docker_copy'), JSON_UNESCAPED_UNICODE);
    $copiedTxt = json_encode(t('docker_copied'), JSON_UNESCAPED_UNICODE);
    $innerHtml = '<div class="view-panel"><div class="log-toolbar"><h1>' . htmlspecialchars(t('container_logs'), ENT_QUOTES, 'UTF-8') . '</h1><button class="log-copy-btn" onclick="copyLogText(\'' . $copyId . '\',this)">' . ico('copy') . ' ' . htmlspecialchars(t('docker_copy'), ENT_QUOTES, 'UTF-8') . '</button></div>' . renderHighlightedLogs($text) . '</div><script id="' . $copyId . '-script">function copyLogText(id,btn){var e=document.querySelector(\'#\'+id+\' ~ .log-code, #\'+id+\'-script ~ .log-code\');if(!e){var all=document.querySelectorAll(\'.log-code\');if(all.length)e=all[all.length-1];}if(!e)return;var t=document.createElement(\'textarea\');t.value=e.textContent;document.body.appendChild(t);t.select();document.execCommand(\'copy\');document.body.removeChild(t);btn.innerHTML=\'\u2713 ' . $copiedTxt . '\';setTimeout(function(){btn.innerHTML=\'\u2398 ' . $copyTxt . '\';},2000);btn.classList.toggle(\'copied\',btn.innerHTML.indexOf(' . $copiedTxt . ')>-1);setTimeout(function(){btn.classList.remove(\'copied\');},3000);}<\/script>';
    viewPageShell(t('container_logs'), $innerHtml);
    exit;
}
if ($view === 'inspect' && isset($_GET['id'])) {
    $iid = $_GET['id'];
    if (!validContainerIdParam($iid)) {
        viewPageShell(t('docker_error'), '<h1>' . htmlspecialchars(t('docker_invalid_id'), ENT_QUOTES, 'UTF-8') . '</h1>');
        exit;
    }
    $ir = dockerContainerInspect($iid);
    $json = ($ir['code'] === 200 && is_array($ir['data'])) ? json_encode($ir['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ('Error: HTTP ' . $ir['code']);
    viewPageShell(t('docker_inspect'), '<div class="view-panel"><h1>' . htmlspecialchars(t('docker_inspect'), ENT_QUOTES, 'UTF-8') . '</h1><pre class="json-pre">' . htmlspecialchars($json, ENT_QUOTES, 'UTF-8') . '</pre></div>');
    exit;
}
if ($view === 'image_inspect' && isset($_GET['id'])) {
    $imageId = trim((string)$_GET['id']);
    if ($imageId === '') {
        viewPageShell(t('docker_error'), '<h1>' . htmlspecialchars(t('docker_invalid_image'), ENT_QUOTES, 'UTF-8') . '</h1>');
        exit;
    }
    $ir = callDockerAPI(dockerApi('images/' . rawurlencode($imageId) . '/json'));
    $json = json_encode($ir['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    viewPageShell(t('docker_image_inspect'), '<div class="view-panel"><h1>' . htmlspecialchars(t('docker_image_inspect'), ENT_QUOTES, 'UTF-8') . '</h1><pre class="json-pre">' . htmlspecialchars($json, ENT_QUOTES, 'UTF-8') . '</pre></div>');
    exit;
}
if ($view === 'volume_inspect' && isset($_GET['id'])) {
    $volumeName = trim((string)$_GET['id']);
    if ($volumeName === '') {
        viewPageShell(t('docker_error'), '<h1>' . htmlspecialchars(t('docker_invalid_volume'), ENT_QUOTES, 'UTF-8') . '</h1>');
        exit;
    }
    $vr = callDockerAPI(dockerApi('volumes/' . rawurlencode($volumeName)));
    $json = json_encode($vr['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    viewPageShell(t('docker_volume_inspect'), '<div class="view-panel"><h1>' . htmlspecialchars(t('docker_volume_inspect'), ENT_QUOTES, 'UTF-8') . '</h1><pre class="json-pre">' . htmlspecialchars($json, ENT_QUOTES, 'UTF-8') . '</pre></div>');
    exit;
}
if ($view === 'stats' && isset($_GET['id'])) {
    $sid = $_GET['id'];
    if (!validContainerIdParam($sid)) {
        viewPageShell(t('docker_error'), '<h1>' . htmlspecialchars(t('docker_invalid_id'), ENT_QUOTES, 'UTF-8') . '</h1>');
        exit;
    }
    $sr = callDockerAPI(dockerApi('containers/' . rawurlencode($sid) . '/stats?stream=false'), 'GET', null, 30);
    $html = '<div class="view-panel"><h1>' . htmlspecialchars(t('stats_snapshot'), ENT_QUOTES, 'UTF-8') . '</h1>';
    if ($sr['code'] === 200 && is_array($sr['data'])) {
        $d = $sr['data'];
        $mem = $d['memory_stats'] ?? [];
        $mu = $mem['usage'] ?? 0;
        $ml = $mem['limit'] ?? 0;
        $mp = ($ml > 0) ? round(100 * $mu / $ml, 1) : 0;
        $cpuDelta = (float)(($d['cpu_stats']['cpu_usage']['total_usage'] ?? 0) - ($d['precpu_stats']['cpu_usage']['total_usage'] ?? 0));
        $systemDelta = (float)(($d['cpu_stats']['system_cpu_usage'] ?? 0) - ($d['precpu_stats']['system_cpu_usage'] ?? 0));
        $onlineCpus = dockerSafeInt($d['cpu_stats']['online_cpus'] ?? 0);
        $cpuPercent = ($cpuDelta > 0 && $systemDelta > 0 && $onlineCpus > 0) ? round(($cpuDelta / $systemDelta) * $onlineCpus * 100, 1) : 0;
        $pidsCurrent = dockerSafeInt($d['pids_stats']['current'] ?? 0);
        $pidsLimit = dockerSafeInt($d['pids_stats']['limit'] ?? 0);
        $pidsPercent = $pidsLimit > 0 ? round(100 * $pidsCurrent / $pidsLimit, 1) : 0;
        $rx = 0;
        $tx = 0;
        if (!empty($d['networks']) && is_array($d['networks'])) {
            foreach ($d['networks'] as $net) {
                if (!is_array($net)) {
                    continue;
                }
                $rx += dockerSafeInt($net['rx_bytes'] ?? 0);
                $tx += dockerSafeInt($net['tx_bytes'] ?? 0);
            }
        }
        $blockRead = 0;
        $blockWrite = 0;
        $ioRows = $d['blkio_stats']['io_service_bytes_recursive'] ?? [];
        if (is_array($ioRows)) {
            foreach ($ioRows as $io) {
                if (!is_array($io)) {
                    continue;
                }
                $op = strtolower((string)($io['op'] ?? ''));
                if ($op === 'read') {
                    $blockRead += dockerSafeInt($io['value'] ?? 0);
                } elseif ($op === 'write') {
                    $blockWrite += dockerSafeInt($io['value'] ?? 0);
                }
            }
        }
        $html .= '<div class="stats-grid-view">';
        $html .= statBarHtml(t('docker_cpu'), $cpuPercent . '% / ' . $onlineCpus . ' ' . t('docker_cores'), min($cpuPercent, 100), $cpuPercent >= 85 ? 'danger' : ($cpuPercent >= 65 ? 'warn' : 'ok'));
        $html .= statBarHtml(t('docker_memory'), formatBytesValue($mu) . ' / ' . ($ml ? formatBytesValue($ml) : '—') . ' (' . $mp . '%)', $mp, $mp >= 85 ? 'danger' : ($mp >= 65 ? 'warn' : 'ok'));
        $html .= statBarHtml(t('docker_pids'), $pidsCurrent . ($pidsLimit ? ' / ' . $pidsLimit : ''), $pidsPercent, $pidsPercent >= 85 ? 'danger' : ($pidsPercent >= 65 ? 'warn' : 'ok'));
        $html .= statBarHtml(t('docker_network'), 'RX ' . formatBytesValue($rx) . ' / TX ' . formatBytesValue($tx), 100, '');
        $html .= statBarHtml(t('docker_block_io_read'), formatBytesValue($blockRead), 100, '');
        $html .= statBarHtml(t('docker_block_io_write'), formatBytesValue($blockWrite), 100, '');
        $html .= '</div><div class="detail-list">';
        $html .= '<div><strong>' . htmlspecialchars(t('docker_instance_id'), ENT_QUOTES, 'UTF-8') . ':</strong> ' . htmlspecialchars((string)($d['id'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
        $html .= '<div><strong>' . htmlspecialchars(t('docker_name'), ENT_QUOTES, 'UTF-8') . ':</strong> ' . htmlspecialchars((string)($d['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
        $html .= '<div><strong>' . htmlspecialchars(t('docker_read'), ENT_QUOTES, 'UTF-8') . ':</strong> ' . htmlspecialchars((string)($d['read'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
        $html .= '</div>';
    } else {
        $html .= '<p>' . htmlspecialchars(t('docker_error'), ENT_QUOTES, 'UTF-8') . ' HTTP ' . (int)($sr['code'] ?? 0) . '</p>';
    }
    $html .= '</div>';
    viewPageShell(t('docker_resources'), $html);
    exit;
}
if ($view === 'stats_data' && isset($_GET['id'])) {
    // An earlier PHP warning (e.g. "Too many open files") may already have
    // flushed output; discard it so the JSON response stays parseable and no
    // second "headers already sent" warning is triggered.
    if (headers_sent()) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    $sid = $_GET['id'];
    if (!validContainerIdParam($sid)) {
        echo json_encode(['ok' => false, 'error' => 'invalid_id'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $sr = callDockerAPI(dockerApi('containers/' . rawurlencode($sid) . '/stats?stream=false'), 'GET', null, 20);
    if (($sr['code'] ?? 0) !== 200 || !is_array($sr['data'])) {
        echo json_encode(['ok' => false, 'error' => 'http_' . (int)($sr['code'] ?? 0)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $d = $sr['data'];
    $mem = $d['memory_stats'] ?? [];
    $mu = dockerSafeInt($mem['usage'] ?? 0);
    $ml = dockerSafeInt($mem['limit'] ?? 0);
    $cpuDelta = (float)(($d['cpu_stats']['cpu_usage']['total_usage'] ?? 0) - ($d['precpu_stats']['cpu_usage']['total_usage'] ?? 0));
    $systemDelta = (float)(($d['cpu_stats']['system_cpu_usage'] ?? 0) - ($d['precpu_stats']['system_cpu_usage'] ?? 0));
    $onlineCpus = dockerSafeInt($d['cpu_stats']['online_cpus'] ?? 0);
    $cpuPercent = ($cpuDelta > 0 && $systemDelta > 0 && $onlineCpus > 0) ? (($cpuDelta / $systemDelta) * $onlineCpus * 100) : 0.0;
    $rx = 0;
    $tx = 0;
    if (!empty($d['networks']) && is_array($d['networks'])) {
        foreach ($d['networks'] as $net) {
            if (!is_array($net)) continue;
            $rx += dockerSafeInt($net['rx_bytes'] ?? 0);
            $tx += dockerSafeInt($net['tx_bytes'] ?? 0);
        }
    }
    $blockRead = 0;
    $blockWrite = 0;
    $ioRows = $d['blkio_stats']['io_service_bytes_recursive'] ?? [];
    if (is_array($ioRows)) {
        foreach ($ioRows as $io) {
            if (!is_array($io)) continue;
            $op = strtolower((string)($io['op'] ?? ''));
            if ($op === 'read') $blockRead += dockerSafeInt($io['value'] ?? 0);
            if ($op === 'write') $blockWrite += dockerSafeInt($io['value'] ?? 0);
        }
    }
    echo json_encode(['ok' => true, 'ts' => time(), 'stats' => ['cpu_total' => round($cpuPercent, 2), 'mem_used' => $mu, 'mem_limit' => $ml, 'pids' => dockerSafeInt($d['pids_stats']['current'] ?? 0), 'net_rx' => $rx, 'net_tx' => $tx, 'blk_read' => $blockRead, 'blk_write' => $blockWrite]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_stack') {
    $stackId = (string)($_POST['id'] ?? '');
    if (!validStackIdParam($stackId)) {
        dockerRedirect(t('docker_invalid_stack'));
    }
    $stack = dockerStackById($stackId);
    if ($stack === null) {
        dockerRedirect(t('docker_stack_not_found'));
    }
    $result = dockerUpdateStackFile($stack, (string)($_POST['stack_file_content'] ?? ''), isset($_POST['prune']), isset($_POST['pull_image']));
    header('Location: ' . dockerUrl(['view' => 'stack_editor', 'id' => $stackId, 'action' => null, 'message' => dockerActionMessage($result, [200, 204], 'docker_stack_updated')]));
    exit;
}
if ($view === 'compose_project' && isset($_GET['project'])) {
    $projectName = trim((string)$_GET['project']);
    if ($projectName === '') { viewPageShell(t('docker_error'), '<h1>' . htmlspecialchars(t('docker_invalid_project'), ENT_QUOTES, 'UTF-8') . '</h1>'); exit; }
    [$content, $error] = detectComposeFile($projectName);
    $innerHtml = '<div class="view-panel"><div class="log-toolbar"><h1>' . htmlspecialchars(t('docker_compose_detect') . ' · ' . $projectName, ENT_QUOTES, 'UTF-8') . '</h1></div>';
    if ($error !== null) {
        $innerHtml .= '<p style="color:var(--danger);margin-bottom:12px;padding:10px 12px;background:rgba(255,50,50,0.08);border-radius:8px;">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
        $innerHtml .= '<p style="color:var(--muted);font-size:13px;">' . htmlspecialchars(t('docker_compose_create_hint'), ENT_QUOTES, 'UTF-8') . '</p>';
    } else {
        $innerHtml .= '<textarea readonly spellcheck="false" style="width:100%;min-height:62vh;resize:vertical;background:rgba(0,0,0,.16);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:12px;font:13px/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;">' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '</textarea>';
        $innerHtml .= '<p style="color:var(--muted);margin-top:8px;font-size:12px;">' . htmlspecialchars(t('docker_compose_readonly'), ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $innerHtml .= '</div>';
    viewPageShell(t('docker_compose_detect'), $innerHtml);
    exit;
}

if ($view === 'stack_editor' && isset($_GET['id'])) {
    $stackId = (string)$_GET['id'];
    if (!validStackIdParam($stackId)) {
        viewPageShell(t('docker_error'), '<h1>' . htmlspecialchars(t('docker_invalid_stack'), ENT_QUOTES, 'UTF-8') . '</h1>');
        exit;
    }
    $stack = dockerStackById($stackId);
    if ($stack === null) {
        viewPageShell(t('docker_error'), '<h1>' . htmlspecialchars(t('docker_stack_not_found'), ENT_QUOTES, 'UTF-8') . '</h1>');
        exit;
    }
    [$content, $fileError] = dockerStackFileContent($stackId);
    $stackName = (string)($stack['Name'] ?? ('#' . $stackId));
    $innerHtml = '<div class="view-panel"><form method="post" action="' . htmlspecialchars(dockerUrl(['view' => 'stack_editor', 'id' => $stackId, 'action' => null]), ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="action" value="save_stack"><input type="hidden" name="id" value="' . htmlspecialchars($stackId, ENT_QUOTES, 'UTF-8') . '"><div class="log-toolbar"><h1>' . htmlspecialchars(t('docker_stack_editor') . ' · ' . $stackName, ENT_QUOTES, 'UTF-8') . '</h1><button class="log-copy-btn" type="submit">' . ico('check') . ' ' . htmlspecialchars(t('docker_update_stack'), ENT_QUOTES, 'UTF-8') . '</button></div>';
    if (trim((string)($_GET['message'] ?? '')) !== '') {
        $innerHtml .= '<p style="background:var(--accent-muted);border:1px solid var(--border);border-radius:8px;margin-bottom:12px;padding:10px 12px;">' . htmlspecialchars((string)$_GET['message'], ENT_QUOTES, 'UTF-8') . '</p>';
    }
    if ($fileError !== null) {
        $innerHtml .= '<p style="color:var(--danger);margin-bottom:12px;">' . htmlspecialchars($fileError, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $innerHtml .= '<textarea name="stack_file_content" spellcheck="false" style="width:100%;min-height:62vh;resize:vertical;background:rgba(0,0,0,.16);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:12px;font:13px/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;">' . htmlspecialchars((string)$content, ENT_QUOTES, 'UTF-8') . '</textarea><div class="detail-list"><label><input type="checkbox" name="prune" value="1">' . htmlspecialchars(t('docker_prune_services'), ENT_QUOTES, 'UTF-8') . '</label><label><input type="checkbox" name="pull_image" value="1">' . htmlspecialchars(t('docker_pull_latest_image'), ENT_QUOTES, 'UTF-8') . '</label><div><strong>' . htmlspecialchars(t('docker_endpoint'), ENT_QUOTES, 'UTF-8') . ':</strong> ' . (int)($stack['EndpointId'] ?? getDockerEndpointId()) . '</div><div><strong>ID:</strong> ' . htmlspecialchars($stackId, ENT_QUOTES, 'UTF-8') . '</div></div></form></div>';
    viewPageShell(t('docker_stack_editor'), $innerHtml);
    exit;
}

// --- ACTION handlers ---
if ($action === 'stop_container' && isset($_GET['id'])) {
    $result = callDockerAPI(dockerApi('containers/' . rawurlencode($_GET['id']) . '/stop'), 'POST');
    dockerRedirect(dockerActionMessage($result, [204, 304], 'docker_container_stopped'));
}

if ($action === 'start_container' && isset($_GET['id'])) {
    $result = callDockerAPI(dockerApi('containers/' . rawurlencode($_GET['id']) . '/start'), 'POST');
    dockerRedirect(dockerActionMessage($result, [204, 304], 'docker_container_started'));
}

if ($action === 'restart_container' && isset($_GET['id'])) {
    $result = callDockerAPI(dockerApi('containers/' . rawurlencode($_GET['id']) . '/restart'), 'POST');
    dockerRedirect(dockerActionMessage($result, [204], 'docker_container_restarted'));
}

if ($action === 'remove_container' && isset($_GET['id'])) {
    $result = callDockerAPI(dockerApi('containers/' . rawurlencode($_GET['id']) . '?force=true'), 'DELETE');
    dockerRedirect(dockerActionMessage($result, [204], 'docker_container_removed'));
}
if ($action === 'pull_image' && isset($_GET['id'])) {
    $insp = dockerContainerInspect($_GET['id']);
    $img = ($insp['code'] === 200 && is_array($insp['data'])) ? ($insp['data']['Config']['Image'] ?? '') : '';
    if ($img === '') {
        $message = t('docker_container_image_missing');
    } else {
        $pr = dockerPullImage($img);
        $raw = is_string($pr['raw'] ?? null) ? $pr['raw'] : '';
        if ($pr['code'] !== 200) {
            $message = tf('docker_image_pull_http_error', ['code' => $pr['code']]);
        } elseif ($raw !== '' && (strpos($raw, '"error"') !== false || strpos($raw, '"errorDetail"') !== false)) {
            $message = t('docker_image_pull_docker_error');
        } else {
            $message = tf('docker_image_pulled', ['image' => $img]);
        }
    }
    dockerRedirect($message);
}
if ($action === 'pull_named_image' && isset($_GET['image'])) {
    $img = trim((string)$_GET['image']);
    if ($img === '') {
        $message = t('docker_invalid_image');
    } else {
        $pr = dockerPullImage($img);
        $raw = is_string($pr['raw'] ?? null) ? $pr['raw'] : '';
        if ($pr['code'] !== 200) {
            $message = tf('docker_image_pull_http_error', ['code' => $pr['code']]);
        } elseif ($raw !== '' && (strpos($raw, '"error"') !== false || strpos($raw, '"errorDetail"') !== false)) {
            $message = t('docker_image_pull_docker_error');
        } else {
            $message = tf('docker_image_pulled', ['image' => $img]);
        }
    }
    dockerRedirect($message);
}
if ($action === 'remove_image' && isset($_GET['id'])) {
    $imageId = trim((string)$_GET['id']);
    if ($imageId === '') {
        dockerRedirect(t('docker_invalid_image'));
    }
    $result = callDockerAPI(dockerApi('images/' . rawurlencode($imageId) . '?force=true&noprune=false'), 'DELETE', null, 120);
    dockerRedirect(dockerActionMessage($result, [200, 202, 204], 'docker_image_removed'));
}
if ($action === 'remove_volume' && isset($_GET['id'])) {
    $volumeName = trim((string)$_GET['id']);
    if ($volumeName === '') {
        dockerRedirect(t('docker_invalid_volume'));
    }
    $result = callDockerAPI(dockerApi('volumes/' . rawurlencode($volumeName) . '?force=true'), 'DELETE', null, 120);
    dockerRedirect(dockerActionMessage($result, [204], 'docker_volume_removed'));
}
if ($action === 'recreate_container' && isset($_GET['id'])) {
    $msg = dockerRecreateContainer($_GET['id'], null);
    dockerRedirect($msg);
}
if ($action === 'pause_container' && isset($_GET['id'])) {
    $result = callDockerAPI(dockerApi('containers/' . rawurlencode($_GET['id']) . '/pause'), 'POST', null, 60);
    dockerRedirect(dockerActionMessage($result, [204], 'docker_container_paused'));
}
if ($action === 'unpause_container' && isset($_GET['id'])) {
    $result = callDockerAPI(dockerApi('containers/' . rawurlencode($_GET['id']) . '/unpause'), 'POST', null, 60);
    dockerRedirect(dockerActionMessage($result, [204], 'docker_container_unpaused'));
}
if ($action === 'kill_container' && isset($_GET['id'])) {
    $result = callDockerAPI(dockerApi('containers/' . rawurlencode($_GET['id']) . '/kill'), 'POST', null, 60);
    dockerRedirect(dockerActionMessage($result, [204], 'docker_container_killed'));
}


// --- Main page data load (after actions/views) ---
$message = $_GET['message'] ?? '';
$epId = getDockerEndpointId();
$containers = callDockerAPI(dockerApi('containers/json?all=true'));
$stacks = ['code' => 200, 'data' => []];
$stats = callDockerAPI(dockerApi('info'));
$images = callDockerAPI(dockerApi('images/json'));
$systemInfo = ['code' => 200, 'data' => ['Name' => 'Local Docker socket', 'URL' => docker_socket_path(), 'Status' => 1]];
$dockerStatus = callDockerAPI(dockerApi('version'));
$networksList = callDockerAPI(dockerApi('networks'));
$volumesList = callDockerAPI(dockerApi('volumes'));
$dockerVersionInfo = callDockerAPI(dockerApi('version'));
$systemDfData = callDockerAPI(dockerApi('system/df'));
$apiError = null;
$cd = $containers['data'];
if ($containers['code'] !== 200) {
    $apiError = is_array($cd) && isset($cd['message']) ? $cd['message'] : ('HTTP ' . $containers['code']);
} elseif (!is_array($cd)) {
    $apiError = 'Invalid API response';
} elseif (isset($cd['message']) && !array_key_exists(0, $cd)) {
    $apiError = $cd['message'];
}
$stacksMap = [];
$containersInStack = [];
$usedVolumesMap = [];
if (($stacks['code'] ?? 0) === 200 && is_array($stacks['data'])) {
    foreach ($stacks['data'] as $stack) {
        if (!is_array($stack)) continue;
        $stackEndpointId = (int)($stack['EndpointId'] ?? $stack['EndpointID'] ?? 0);
        if ($stackEndpointId > 0 && $stackEndpointId !== (int)$epId) continue;
        $stackName = trim((string)($stack['Name'] ?? ''));
        if ($stackName === '') continue;
        $stacksMap[$stackName] = [
            'name' => $stackName,
            'containers' => 0,
            'id' => isset($stack['Id']) ? (int)$stack['Id'] : null,
            'type' => (int)($stack['Type'] ?? 0),
            'endpoint_id' => $stackEndpointId,
            'status' => (string)($stack['Status'] ?? '')
        ];
    }
}
if (is_array($containers['data'])) {
    foreach ($containers['data'] as $c) {
        if (!is_array($c)) continue;
        $labels = is_array($c['Labels'] ?? null) ? $c['Labels'] : [];
        $stackName = trim((string)($labels['com.docker.compose.project'] ?? $labels['com.docker.stack.namespace'] ?? ''));
        if ($stackName !== '') {
            $cname = str_replace('/', '', (string)($c['Names'][0] ?? ''));
            if (!isset($stacksMap[$stackName])) $stacksMap[$stackName] = ['name' => $stackName, 'containers' => 0, 'id' => null, 'type' => 0, 'endpoint_id' => (int)$epId, 'status' => ''];
            $stacksMap[$stackName]['containers']++;
            if (!isset($containersInStack[$stackName])) $containersInStack[$stackName] = [];
            $containersInStack[$stackName][] = ['id' => (string)($c['Id'] ?? ''), 'name' => $cname, 'image' => (string)($c['Image'] ?? ''), 'state' => (string)($c['State'] ?? '')];
        }
        if (!empty($c['Mounts']) && is_array($c['Mounts'])) {
            foreach ($c['Mounts'] as $mount) {
                $vn = (string)($mount['Name'] ?? '');
                if ($vn !== '') $usedVolumesMap[$vn] = true;
            }
        }
    }
}
if ($stacksMap !== []) {
    uasort($stacksMap, static function ($a, $b) {
        return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });
}
$messageIsError = $message !== '' && (
    strpos($message, 'Error') === 0 ||
    strpos($message, t('docker_error')) === 0 ||
    strpos($message, t('docker_api_error')) === 0
);
$startupModals = [];
if ($apiError) {
    $startupModals[] = [
        'type' => 'error',
        'title' => t('docker_api_error'),
        'message' => 'API: ' . $apiError . ' (endpoint #' . (int)$epId . ')',
    ];
}
if ($message !== '') {
    $startupModals[] = [
        'type' => $messageIsError ? 'error' : 'success',
        'title' => $messageIsError ? t('docker_error') : t('docker_action_done'),
        'message' => $message,
    ];
}

// Render main page
ob_start();
require __DIR__ . '/../views/modals/docker_manager.php';
dockerOutput((string)ob_get_clean(), t('docker_manager_title'), false);
