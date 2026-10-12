<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="<?php echo htmlspecialchars($theme, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('docker_manager_title'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/project/assets/docker/manager.css">
    <style>
        :root {
            --accent: <?php echo htmlspecialchars($accent, ENT_QUOTES, 'UTF-8'); ?>;
            --accent-muted: <?php
                if ($theme === 'light') {
                    echo 'rgba(37,99,235,0.1)';
                } else {
                    printf('rgba(%d,%d,%d,0.14)', hexdec(substr($accent, 1, 2)), hexdec(substr($accent, 3, 2)), hexdec(substr($accent, 5, 2)));
                }
            ?>;
        }
    </style>
</head>
<body>
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="brand-row"><?php echo ico('brand'); ?><h2><?php echo htmlspecialchars(t('docker_manager_title'), ENT_QUOTES, 'UTF-8'); ?></h2></div>
        </div>
        <div class="sidebar-nav">
            <div class="nav-item active" onclick="showSection('stacks', this); closeSidebar();">
                <span class="nav-icon"><?php echo ico('layers'); ?></span>
                <span><?php echo htmlspecialchars(t('docker_stacks'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="nav-item" onclick="showSection('containers', this); closeSidebar();">
                <span class="nav-icon"><?php echo ico('cube'); ?></span>
                <span><?php echo htmlspecialchars(t('containers'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="nav-item" onclick="showSection('networks', this); closeSidebar();">
                <span class="nav-icon"><?php echo ico('network'); ?></span>
                <span><?php echo htmlspecialchars(t('networks'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="nav-item" onclick="showSection('volumes', this); closeSidebar();">
                <span class="nav-icon"><?php echo ico('volume'); ?></span>
                <span><?php echo htmlspecialchars(t('volumes'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="nav-item" onclick="showSection('images', this); closeSidebar();">
                <span class="nav-icon"><?php echo ico('layers'); ?></span>
                <span><?php echo htmlspecialchars(t('images'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="nav-item" onclick="showSection('info', this); closeSidebar();">
                <span class="nav-icon"><?php echo ico('info'); ?></span>
                <span><?php echo htmlspecialchars(t('docker_system'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <div class="sidebar-footer">
            <a href="https://docs.docker.com/reference/api/engine/" target="_blank" rel="noopener noreferrer"><?php echo ico('info'); ?> <?php echo htmlspecialchars(t('docker_api_docs'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </div>
    
    <div class="main-content">
        <div class="filter-bar" id="containerFilterBar">
            <button type="button" class="btn-icon" onclick="toggleSidebar()" aria-label="Menu"><?php echo ico('menu'); ?></button>
            <div class="toolbar-search">
                <?php echo ico('search'); ?>
                <input type="search" id="containerFilter" placeholder="<?php echo htmlspecialchars(t('docker_search'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" aria-label="<?php echo htmlspecialchars(t('docker_search'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>
        <div class="top-bar">
            <h1 class="page-title" id="pageTitle"><?php echo htmlspecialchars(t('docker_stacks'), ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="connection-status<?php echo $apiError ? ' err' : ''; ?>">
                <div class="status-dot"></div>
                <span><?php echo htmlspecialchars($apiError ? t('docker_api_error') : rtrim(t('docker_connected')), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <div class="stat-title"><?php echo htmlspecialchars(t('docker_total_containers'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="stat-value" id="statTotalContainers"><?php echo is_array($containers['data']) ? count($containers['data']) : 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title"><?php echo htmlspecialchars(t('docker_running'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="stat-value" id="statRunning">
                    <?php 
                    $running = 0;
                    if (is_array($containers['data'])) {
                        foreach ($containers['data'] as $c) {
                            if ($c['State'] === 'running') $running++;
                        }
                    }
                    echo $running;
                    ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-title"><?php echo htmlspecialchars(t('docker_images_count'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="stat-value" id="statImages"><?php echo (is_array($images['data']) && ($images['code'] ?? 0) === 200) ? count($images['data']) : 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title"><?php echo htmlspecialchars(t('docker_docker_version'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="stat-value accent" id="statVersion">
                    <?php echo (is_array($stats['data']) && isset($stats['data']['ServerVersion'])) ? htmlspecialchars($stats['data']['ServerVersion']) : 'N/A'; ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-title"><?php echo htmlspecialchars(t('docker_stacks'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="stat-value" id="statStacks"><?php echo count($stacksMap); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title"><?php echo htmlspecialchars(t('docker_unused_volumes'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="stat-value" id="statUnusedVolumes">0</div>
            </div>
        </div>
        <div class="section" id="chartsSection" style="display:none;margin-bottom:16px;">
            <div class="section-header"><div class="section-title-wrap"><?php echo ico('chart'); ?><div class="section-title" id="monitorTitle"><?php echo htmlspecialchars(t('docker_live_monitoring'), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
            <div class="stats-grid-view">
                <div class="monitor-tile"><div class="metric-head"><span><?php echo htmlspecialchars(t('docker_cpu_total'), ENT_QUOTES, 'UTF-8'); ?></span><strong id="mCpu">0%</strong></div><div class="monitor-canvas-wrap"><canvas id="chartCpu"></canvas></div><div class="monitor-scale"><span>0</span><span id="sCpu"><?php echo htmlspecialchars(tf('max_label', ['value' => '0%']), ENT_QUOTES, 'UTF-8'); ?></span></div></div>
                <div class="monitor-tile"><div class="metric-head"><span><?php echo htmlspecialchars(t('docker_memory'), ENT_QUOTES, 'UTF-8'); ?></span><strong id="mMem">0 B</strong></div><div class="monitor-canvas-wrap"><canvas id="chartMem"></canvas></div><div class="monitor-scale"><span>0</span><span id="sMem"><?php echo htmlspecialchars(tf('max_label', ['value' => '0 B']), ENT_QUOTES, 'UTF-8'); ?></span></div></div>
                <div class="monitor-tile"><div class="metric-head"><span>Network RX/TX</span><strong id="mNet">0 B / 0 B</strong></div><div class="monitor-canvas-wrap"><canvas id="chartNet"></canvas></div><div class="monitor-scale"><span>0</span><span id="sNet"><?php echo htmlspecialchars(tf('max_label', ['value' => '0 B']), ENT_QUOTES, 'UTF-8'); ?></span></div></div>
                <div class="monitor-tile"><div class="metric-head"><span><?php echo htmlspecialchars(t('docker_block_io'), ENT_QUOTES, 'UTF-8'); ?></span><strong id="mIo">0 B / 0 B</strong></div><div class="monitor-canvas-wrap"><canvas id="chartIo"></canvas></div><div class="monitor-scale"><span>0</span><span id="sIo"><?php echo htmlspecialchars(tf('max_label', ['value' => '0 B']), ENT_QUOTES, 'UTF-8'); ?></span></div></div>
                <div class="monitor-tile"><div class="metric-head"><span>PIDs</span><strong id="mPids">0</strong></div><div class="monitor-canvas-wrap"><canvas id="chartPids"></canvas></div><div class="monitor-scale"><span>0</span><span id="sPids"><?php echo htmlspecialchars(tf('max_label', ['value' => '0']), ENT_QUOTES, 'UTF-8'); ?></span></div></div>
                <div class="monitor-tile"><div class="metric-head"><span><?php echo htmlspecialchars(t('docker_container'), ENT_QUOTES, 'UTF-8'); ?></span><strong id="mContainers">n/a</strong></div><div class="monitor-canvas-wrap"><canvas id="chartContainers"></canvas></div><div class="monitor-scale"><span>0</span><span id="sContainer"><?php echo htmlspecialchars(t('docker_cpu') . '/' . t('docker_container'), ENT_QUOTES, 'UTF-8'); ?></span></div></div>
            </div>
        </div>
        <div id="stacksSection" class="section">
            <div class="section-header"><div class="section-title-wrap"><?php echo ico('layers'); ?><div class="section-title"><?php echo htmlspecialchars(t('docker_stacks'), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
            <div class="table-container containers-table-wrap"><table><thead><tr><th><?php echo htmlspecialchars(t('docker_name'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(t('docker_containers_col'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(t('docker_type'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(t('docker_stack_file'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(t('docker_open'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead><tbody>
            <?php if (!empty($stacksMap)): foreach ($stacksMap as $stackName => $stackData): $sc=(int)($stackData['containers']??0); $stackId=$stackData['id'] ?? null; $stackType=(int)($stackData['type'] ?? 0); ?>
                <tr class="c-row" tabindex="0" data-search="<?php echo htmlspecialchars(strtolower((string)$stackName), ENT_QUOTES, 'UTF-8'); ?>"><td><span class="c-name-wrap"><?php echo ico('chevron'); ?><strong><?php echo htmlspecialchars((string)$stackName, ENT_QUOTES, 'UTF-8'); ?></strong></span></td><td><?php echo $sc; ?></td><td><?php echo $stackType === 2 ? t('docker_compose') : ($stackType === 1 ? t('docker_swarm') : ''); ?></td><td><?php if ($stackId !== null): ?><a class="stack-open-btn" href="<?php echo htmlspecialchars(dockerUrl(['view' => 'stack_editor', 'id' => (string)$stackId, 'action' => null, 'embed' => '1']), ENT_QUOTES, 'UTF-8'); ?>" onclick="openDockerModal(event, this.href, <?php echo htmlspecialchars(json_encode(t('docker_stack_editor') . ' · ' . (string)$stackName, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)"><?php echo ico('file'); ?> <?php echo htmlspecialchars(t('docker_edit_stack'), ENT_QUOTES, 'UTF-8'); ?></a><?php else: ?><a class="stack-open-btn" href="<?php echo htmlspecialchars(dockerUrl(['view' => 'compose_project', 'project' => (string)$stackName, 'id' => null, 'action' => null, 'embed' => '1']), ENT_QUOTES, 'UTF-8'); ?>" onclick="openDockerModal(event, this.href, <?php echo htmlspecialchars(json_encode(t('docker_compose_detect') . ' · ' . (string)$stackName, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)"><?php echo ico('file'); ?> <?php echo htmlspecialchars(t('docker_compose_detect_btn'), ENT_QUOTES, 'UTF-8'); ?></a><?php endif; ?></td><td><a class="stack-open-btn" href="#" onclick="event.preventDefault();showSection('containers',document.querySelector('.nav-item[onclick*=\'containers\']'));applyContainerFilter(<?php echo htmlspecialchars(json_encode((string)$stackName, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>);"><?php echo htmlspecialchars(t('docker_show_containers'), ENT_QUOTES, 'UTF-8'); ?></a></td></tr>
                <tr class="c-actions" style="display:none"><td colspan="5"><div class="c-panel"><div class="c-panel-hint"><?php echo htmlspecialchars(t('docker_containers_in_stack'), ENT_QUOTES, 'UTF-8'); ?></div><?php if (!empty($containersInStack[$stackName])): ?><div class="stack-container-grid"><?php foreach ($containersInStack[$stackName] as $stackContainer): $scState = (string)$stackContainer['state']; ?><div class="stack-container-card" onclick="event.stopPropagation();showSection('containers',document.querySelector('.nav-item[onclick*=\'containers\']'));applyContainerFilter(<?php echo htmlspecialchars(json_encode((string)$stackContainer['name'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>);" title="<?php echo htmlspecialchars(t('docker_click_to_navigate'), ENT_QUOTES, 'UTF-8'); ?>"><div class="stack-card-top"><span class="scc-name"><?php echo htmlspecialchars((string)$stackContainer['name'], ENT_QUOTES, 'UTF-8'); ?></span><span class="status-badge status-<?php echo $scState === 'running' ? 'running' : ($scState === 'exited' ? 'exited' : ($scState === 'paused' ? 'paused' : 'created')); ?>"><?php echo ico('dot'); ?> <?php echo htmlspecialchars($scState, ENT_QUOTES, 'UTF-8'); ?></span></div><div class="stack-card-image"><?php echo ico('cube'); ?> <span class="mono-clip"><?php echo htmlspecialchars((string)$stackContainer['image'], ENT_QUOTES, 'UTF-8'); ?></span></div></div><?php endforeach; ?></div><?php else: ?><div class="detail-list"><div><?php echo htmlspecialchars(t('no_containers'), ENT_QUOTES, 'UTF-8'); ?></div></div><?php endif; ?></div></td></tr>
            <?php endforeach; else: ?><tr><td colspan="5" style="text-align:center;padding:40px;"><?php echo htmlspecialchars(t('docker_no_stacks_found'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
        
        <div id="containersSection" class="section" style="display:none;">
            <div class="section-header">
                <div class="section-title-wrap">
                    <?php echo ico('cube'); ?>
                    <div class="section-title"><?php echo htmlspecialchars(t('docker_containers_list'), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <button type="button" class="btn btn-ghost" onclick="location.reload()"><?php echo ico('refresh'); ?> <?php echo htmlspecialchars(t('docker_refresh'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
            <div class="table-container containers-table-wrap">
                <table>
                    <colgroup>
                        <col class="c-name-col">
                        <col class="c-image-col">
                        <col class="c-status-col">
                        <col class="c-ports-col">
                        <col class="c-created-col">
                    </colgroup>
                    <thead>
                        <tr>
                            <th><?php echo htmlspecialchars(t('docker_name'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('docker_image'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('docker_status'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('docker_ports'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('docker_created'), ENT_QUOTES, 'UTF-8'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (is_array($containers['data']) && count($containers['data']) > 0): ?>
                            <?php foreach ($containers['data'] as $container): ?>
                            <?php
                            $st = $container['State'] ?? '';
                            $cid = rawurlencode($container['Id']);
                            $cname = str_replace('/', '', $container['Names'][0] ?? 'N/A');
                            $cimg = $container['Image'] ?? '';
                            $labels = is_array($container['Labels'] ?? null) ? $container['Labels'] : [];
                            $stackNameForContainer = trim((string)($labels['com.docker.compose.project'] ?? $labels['com.docker.stack.namespace'] ?? ''));
                            $searchBlob = strtolower($cname . ' ' . $cimg . ' ' . $stackNameForContainer);
                            $ports = [];
                            if (isset($container['Ports']) && is_array($container['Ports']) && count($container['Ports']) > 0) {
                                foreach ($container['Ports'] as $port) {
                                    $pp = $port['PrivatePort'] ?? '';
                                    $typ = $port['Type'] ?? 'tcp';
                                    if (isset($port['PublicPort'])) {
                                        $ports[] = $port['PublicPort'] . ':' . $pp . '/' . $typ;
                                    } elseif ($pp !== '') {
                                        $ports[] = $pp . '/' . $typ;
                                    }
                                }
                            }
                            $portsText = count($ports) > 0 ? implode(', ', $ports) : '-';
                            ?>
                            <tr class="c-row" tabindex="0" data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8'); ?>" data-stack="<?php echo htmlspecialchars($stackNameForContainer, ENT_QUOTES, 'UTF-8'); ?>" data-container-id="<?php echo htmlspecialchars((string)$container['Id'], ENT_QUOTES, 'UTF-8'); ?>" data-container-name="<?php echo htmlspecialchars($cname, ENT_QUOTES, 'UTF-8'); ?>">
                                <td class="c-name-cell" title="<?php echo htmlspecialchars($cname, ENT_QUOTES, 'UTF-8'); ?>"><div class="c-name-wrap"><?php echo ico('chevron'); ?><strong><?php echo htmlspecialchars($cname); ?></strong></div></td>
                                <td title="<?php echo htmlspecialchars($cimg ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?>"><span class="mono-clip image-cell"><?php echo htmlspecialchars($cimg ?: 'N/A'); ?></span></td>
                                <td>
                                    <span class="status-badge status-<?php echo $st === 'running' ? 'running' : ($st === 'exited' ? 'exited' : ($st === 'paused' ? 'paused' : 'created')); ?>">
                                        <?php echo ico('dot'); ?> <?php echo htmlspecialchars($st); ?>
                                    </span>
                                </td>
                                <td title="<?php echo htmlspecialchars($portsText, ENT_QUOTES, 'UTF-8'); ?>"><span class="mono-clip ports-cell"><?php echo htmlspecialchars($portsText); ?></span></td>
                                <td><?php $cr = $container['Created'] ?? 0; echo date('d.m.Y H:i', is_numeric($cr) ? (int)$cr : strtotime((string)$cr)); ?></td>
                            </tr>
                            <tr class="c-actions" style="display:none">
                                <td colspan="5">
                                    <div class="c-panel">
                                        <div class="c-panel-hint"><?php echo htmlspecialchars(t('hide_panel'), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="c-quick">
                                            <a href="<?php echo htmlspecialchars(dockerUrl(['view' => 'logs', 'id' => $container['Id'], 'action' => null, 'embed' => '1']), ENT_QUOTES, 'UTF-8'); ?>" onclick="openDockerModal(event, this.href, <?php echo htmlspecialchars(json_encode(t('docker_logs') . ' · ' . $cname, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)"><?php echo ico('file'); ?> <?php echo htmlspecialchars(t('docker_logs'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <a href="<?php echo htmlspecialchars(dockerUrl(['view' => 'inspect', 'id' => $container['Id'], 'action' => null, 'embed' => '1']), ENT_QUOTES, 'UTF-8'); ?>" onclick="openDockerModal(event, this.href, <?php echo htmlspecialchars(json_encode(t('docker_inspect') . ' · ' . $cname, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)"><?php echo ico('layers'); ?> <?php echo htmlspecialchars(t('docker_inspect'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <a href="<?php echo htmlspecialchars(dockerUrl(['view' => 'stats', 'id' => $container['Id'], 'action' => null, 'embed' => '1']), ENT_QUOTES, 'UTF-8'); ?>" onclick="openDockerModal(event, this.href, <?php echo htmlspecialchars(json_encode(t('docker_resources') . ' · ' . $cname, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)"><?php echo ico('chart'); ?> <?php echo htmlspecialchars(t('docker_resources'), ENT_QUOTES, 'UTF-8'); ?></a>
                                        </div>
                                        <div class="action-buttons">
                                            <?php if ($st !== 'running'): ?>
                                                <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'start_container', 'id' => $container['Id'], 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-start" title="<?php echo htmlspecialchars(t('docker_start'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('docker_start') . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('play'); ?> <?php echo htmlspecialchars(t('docker_start'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <?php else: ?>
                                                <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'stop_container', 'id' => $container['Id'], 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-stop" title="<?php echo htmlspecialchars(t('docker_stop'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('docker_stop') . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('stop'); ?> <?php echo htmlspecialchars(t('docker_stop'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <?php endif; ?>
                                            <?php if ($st === 'running'): ?>
                                                <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'pause_container', 'id' => $container['Id'], 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-pause" title="<?php echo htmlspecialchars(t('docker_pause'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('docker_pause') . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('pause'); ?> <?php echo htmlspecialchars(t('docker_pause'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <?php endif; ?>
                                            <?php if ($st === 'paused'): ?>
                                                <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'unpause_container', 'id' => $container['Id'], 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-unpause" title="<?php echo htmlspecialchars(t('docker_resume'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('docker_resume') . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('play'); ?> <?php echo htmlspecialchars(t('docker_resume'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <?php endif; ?>
                                            <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'restart_container', 'id' => $container['Id'], 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-restart" title="<?php echo htmlspecialchars(t('docker_restart'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('docker_restart') . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('restart'); ?> <?php echo htmlspecialchars(t('docker_restart'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'pull_image', 'id' => $container['Id'], 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-pull" title="<?php echo htmlspecialchars(t('docker_pull'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('docker_pull') . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('download'); ?> <?php echo htmlspecialchars(t('docker_pull'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'recreate_container', 'id' => $container['Id'], 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-recreate" title="<?php echo htmlspecialchars(t('docker_recreate'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('docker_recreate') . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('duplicate'); ?> <?php echo htmlspecialchars(t('docker_recreate'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <?php if (in_array($st, ['running', 'paused', 'restarting'], true)): ?>
                                                <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'kill_container', 'id' => $container['Id'], 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-kill" title="<?php echo htmlspecialchars(t('docker_kill'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('docker_kill') . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('kill'); ?> <?php echo htmlspecialchars(t('docker_kill'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <?php endif; ?>
                                            <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'remove_container', 'id' => $container['Id'], 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-remove" title="<?php echo htmlspecialchars(t('docker_remove'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('docker_remove') . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('trash'); ?> <?php echo htmlspecialchars(t('docker_remove'), ENT_QUOTES, 'UTF-8'); ?></a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px;">
                                    <?php echo htmlspecialchars(t('no_containers'), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div id="imagesSection" class="section" style="display: none;">
            <div class="section-header">
                <div class="section-title-wrap">
                    <?php echo ico('layers'); ?>
                    <div class="section-title"><?php echo htmlspecialchars(t('images'), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
            <div class="table-container containers-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo htmlspecialchars(t('docker_repository'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('docker_tag'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('docker_id'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('docker_size'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('docker_created'), ENT_QUOTES, 'UTF-8'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (is_array($images['data']) && count($images['data']) > 0): ?>
                            <?php foreach ($images['data'] as $image): ?>
                                <?php
                                $tag = (isset($image['RepoTags'][0]) && $image['RepoTags'][0] !== '') ? $image['RepoTags'][0] : '';
                                if ($tag === '<none>:<none>' || $tag === '') {
                                    $repo = '<none>';
                                    $itag = 'dangling';
                                } else {
                                    $parts = explode(':', $tag, 2);
                                    $repo = $parts[0];
                                    $itag = $parts[1] ?? 'latest';
                                }
                                $idShort = isset($image['Id']) ? (strpos($image['Id'], 'sha256:') === 0 ? substr($image['Id'], 7, 12) : substr($image['Id'], 0, 12)) : '';
                                $imageRef = dockerImageRefFromImage($image);
                                $imageLabel = $tag !== '' && $tag !== '<none>:<none>' ? $tag : $idShort;
                                $imageSearch = strtolower(trim($repo . ' ' . $itag . ' ' . $idShort . ' ' . $imageRef));
                                ?>
                                <tr class="c-row" tabindex="0" data-search="<?php echo htmlspecialchars($imageSearch, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td>
                                        <span class="c-name-wrap"><?php echo ico('chevron'); ?><strong><?php echo htmlspecialchars($repo); ?></strong></span>
                                    </td>
                                    <td><span class="tag-pill"><?php echo htmlspecialchars($itag); ?></span></td>
                                    <td style="font-family: monospace; font-size: 11px;"><?php echo htmlspecialchars($idShort); ?></td>
                                    <td><?php echo round(($image['Size'] ?? 0) / 1024 / 1024, 2); ?> MB</td>
                                    <td><?php echo date('d.m.Y', (int)($image['Created'] ?? 0)); ?></td>
                                </tr>
                                <tr class="c-actions" style="display:none">
                                    <td colspan="5">
                                        <div class="c-panel">
                                            <div class="c-panel-hint"><?php echo htmlspecialchars(t('docker_resource_panel_hint'), ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="c-quick">
                                                <a href="<?php echo htmlspecialchars(dockerUrl(['view' => 'image_inspect', 'id' => $imageRef, 'action' => null, 'embed' => '1']), ENT_QUOTES, 'UTF-8'); ?>" onclick="openDockerModal(event, this.href, <?php echo htmlspecialchars(json_encode(t('docker_image_inspect') . ' · ' . $imageLabel, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)"><?php echo ico('layers'); ?> <?php echo htmlspecialchars(t('docker_inspect'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            </div>
                                            <div class="action-buttons">
                                                <?php if ($tag !== '' && $tag !== '<none>:<none>'): ?>
                                                    <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'pull_named_image', 'image' => $tag, 'id' => null, 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-pull" title="<?php echo htmlspecialchars(t('docker_pull'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('docker_pull') . ' ' . $tag . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('download'); ?> <?php echo htmlspecialchars(t('docker_pull'), ENT_QUOTES, 'UTF-8'); ?></a>
                                                <?php endif; ?>
                                                <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'remove_image', 'id' => $imageRef, 'image' => null, 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-remove" title="<?php echo htmlspecialchars(t('docker_remove'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('docker_confirm_remove_image'), ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('trash'); ?> <?php echo htmlspecialchars(t('docker_remove'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px;">
                                    <?php echo htmlspecialchars(t('no_images'), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="networksSection" class="section" style="display: none;">
            <div class="section-header">
                <div class="section-title-wrap">
                    <?php echo ico('network'); ?>
                    <div class="section-title"><?php echo htmlspecialchars(t('networks'), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
            <div class="table-container containers-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo htmlspecialchars(t('docker_name'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('docker_driver'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('docker_scope'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('docker_id'), ENT_QUOTES, 'UTF-8'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $nets = ($networksList['code'] === 200 && is_array($networksList['data'])) ? $networksList['data'] : [];
                        if (is_array($nets) && count($nets) > 0):
                            foreach ($nets as $nw):
                                if (!is_array($nw)) {
                                    continue;
                                }
                                $nid = isset($nw['Id']) ? substr($nw['Id'], 0, 12) : '';
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($nw['Name'] ?? ''); ?></strong></td>
                                <td><?php echo htmlspecialchars($nw['Driver'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($nw['Scope'] ?? ''); ?></td>
                                <td style="font-family: monospace; font-size: 11px;"><?php echo htmlspecialchars($nid); ?></td>
                            </tr>
                        <?php
                            endforeach;
                        else:
                        ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 40px;"><?php echo htmlspecialchars(t('docker_no_data'), ENT_QUOTES, 'UTF-8'); ?> (<?php echo (int)($networksList['code'] ?? 0); ?>)</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="volumesSection" class="section" style="display: none;">
            <div class="section-header">
                <div class="section-title-wrap">
                    <?php echo ico('volume'); ?>
                    <div class="section-title"><?php echo htmlspecialchars(t('volumes'), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
            <div class="table-container containers-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo htmlspecialchars(t('docker_name'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('docker_driver'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('docker_mountpoint'), ENT_QUOTES, 'UTF-8'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $vols = ($volumesList['code'] === 200 && is_array($volumesList['data']) && isset($volumesList['data']['Volumes'])) ? $volumesList['data']['Volumes'] : [];
                        if (is_array($vols) && count($vols) > 0):
                            foreach ($vols as $vo):
                                if (!is_array($vo)) {
                                    continue;
                                }
                                $volumeName = (string)($vo['Name'] ?? '');
                                $volumeSearch = strtolower(trim($volumeName . ' ' . ($vo['Driver'] ?? '') . ' ' . ($vo['Mountpoint'] ?? '')));
                                $isVolumeUsed = isset($usedVolumesMap[$volumeName]);
                        ?>
                            <tr class="c-row" tabindex="0" data-search="<?php echo htmlspecialchars($volumeSearch, ENT_QUOTES, 'UTF-8'); ?>">
                                <td><span class="c-name-wrap"><?php echo ico('chevron'); ?><strong><?php echo htmlspecialchars($volumeName); ?></strong><?php if (!$isVolumeUsed): ?><span class="tag-pill" style="margin-left:8px;background:var(--danger-bg);color:var(--danger);"><?php echo htmlspecialchars(t('unused'), ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?></span></td>
                                <td><?php echo htmlspecialchars($vo['Driver'] ?? ''); ?></td>
                                <td style="font-family: monospace; font-size: 11px;"><?php echo htmlspecialchars($vo['Mountpoint'] ?? ''); ?></td>
                            </tr>
                            <tr class="c-actions" style="display:none">
                                <td colspan="3">
                                    <div class="c-panel">
                                        <div class="c-panel-hint"><?php echo htmlspecialchars(t('docker_resource_panel_hint'), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="c-quick">
                                            <a href="<?php echo htmlspecialchars(dockerUrl(['view' => 'volume_inspect', 'id' => $volumeName, 'action' => null, 'embed' => '1']), ENT_QUOTES, 'UTF-8'); ?>" onclick="openDockerModal(event, this.href, <?php echo htmlspecialchars(json_encode(t('docker_volume_inspect') . ' · ' . $volumeName, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)"><?php echo ico('volume'); ?> <?php echo htmlspecialchars(t('docker_inspect'), ENT_QUOTES, 'UTF-8'); ?></a>
                                        </div>
                                        <div class="action-buttons">
                                            <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'remove_volume', 'id' => $volumeName, 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-remove" title="<?php echo htmlspecialchars(t('docker_remove'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('docker_confirm_remove_volume'), ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('trash'); ?> <?php echo htmlspecialchars(t('docker_remove'), ENT_QUOTES, 'UTF-8'); ?></a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php
                            endforeach;
                        else:
                        ?>
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 40px;"><?php echo htmlspecialchars(t('docker_no_data'), ENT_QUOTES, 'UTF-8'); ?> (<?php echo (int)($volumesList['code'] ?? 0); ?>)</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="infoSection" class="section" style="display: none;">
            <div class="section-header">
                <div class="section-title-wrap">
                    <?php echo ico('info'); ?>
                    <div class="section-title"><?php echo htmlspecialchars(t('docker_system_info'), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
            <div style="padding: 4px 4px 8px;">
                <h3><?php echo htmlspecialchars(t('docker_docker'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <div class="info-grid">
                    <div><strong><?php echo htmlspecialchars(t('docker_docker_version'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo ($dockerStatus['code'] === 200 && is_array($dockerStatus['data'])) ? htmlspecialchars($dockerStatus['data']['Version'] ?? '—') : ('HTTP ' . (int)($dockerStatus['code'] ?? 0)); ?></div>
                    <div><strong><?php echo htmlspecialchars(t('docker_instance_id'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo ($dockerStatus['code'] === 200 && is_array($dockerStatus['data'])) ? htmlspecialchars(substr((string)($dockerStatus['data']['InstanceID'] ?? ''), 0, 36)) : '—'; ?></div>
                </div>
                <h3 style="margin-top: 22px;"><?php echo ico('layers'); ?> <?php echo htmlspecialchars(t('docker_docker_engine'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <div class="info-grid">
                    <div><strong><?php echo htmlspecialchars(t('docker_docker_version_info'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo is_array($stats['data'] ?? null) ? htmlspecialchars($stats['data']['ServerVersion'] ?? 'N/A') : 'N/A'; ?></div>
                    <div><strong><?php echo htmlspecialchars(t('docker_docker_api'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo ($dockerVersionInfo['code'] === 200 && is_array($dockerVersionInfo['data'])) ? htmlspecialchars($dockerVersionInfo['data']['ApiVersion'] ?? '—') : ('HTTP ' . (int)($dockerVersionInfo['code'] ?? 0)); ?></div>
                    <div><strong><?php echo htmlspecialchars(t('docker_os_arch'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo ($dockerVersionInfo['code'] === 200 && is_array($dockerVersionInfo['data'])) ? htmlspecialchars(($dockerVersionInfo['data']['Os'] ?? '') . ' / ' . ($dockerVersionInfo['data']['Arch'] ?? '')) : '—'; ?></div>
                    <div><strong><?php echo htmlspecialchars(t('docker_containers_total'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo is_array($stats['data'] ?? null) ? (int)($stats['data']['Containers'] ?? 0) : 0; ?></div>
                    <div><strong><?php echo htmlspecialchars(t('docker_running_total'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo is_array($stats['data'] ?? null) ? (int)($stats['data']['ContainersRunning'] ?? 0) : 0; ?></div>
                    <div><strong><?php echo htmlspecialchars(t('docker_stopped_total'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo is_array($stats['data'] ?? null) ? (int)($stats['data']['ContainersStopped'] ?? 0) : 0; ?></div>
                    <div><strong><?php echo htmlspecialchars(t('docker_images_total'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo is_array($stats['data'] ?? null) ? (int)($stats['data']['Images'] ?? 0) : 0; ?></div>
                    <div><strong><?php echo htmlspecialchars(t('docker_volumes_total'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo is_array($stats['data'] ?? null) ? (int)($stats['data']['Volumes'] ?? 0) : 0; ?></div>
                    <div><strong><?php echo htmlspecialchars(t('docker_cpu_cores'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo is_array($stats['data'] ?? null) ? (int)($stats['data']['NCPU'] ?? 0) : 0; ?></div>
                    <div><strong><?php echo htmlspecialchars(t('docker_ram_total'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo (is_array($stats['data'] ?? null) && isset($stats['data']['MemTotal'])) ? round($stats['data']['MemTotal'] / 1024 / 1024 / 1024, 2) : 0; ?> GB</div>
                </div>
                <h3 style="margin-top: 22px;"><?php echo htmlspecialchars(t('docker_disk_usage'), ENT_QUOTES, 'UTF-8'); ?> (system df)</h3>
                <?php if ($systemDfData['code'] === 200 && is_array($systemDfData['data'])): ?>
                <?php $sdf = $systemDfData['data']; ?>
                <div class="info-grid">
                    <div><strong><?php echo htmlspecialchars(t('layers_size'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo isset($sdf['LayersSize']) ? round($sdf['LayersSize'] / 1024 / 1024 / 1024, 2) : 0; ?> GB</div>
                    <div><strong><?php echo htmlspecialchars(t('images_in_report'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo isset($sdf['Images']) && is_array($sdf['Images']) ? count($sdf['Images']) : 0; ?></div>
                    <div><strong><?php echo htmlspecialchars(t('containers_in_report'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo isset($sdf['Containers']) && is_array($sdf['Containers']) ? count($sdf['Containers']) : 0; ?></div>
                    <div><strong><?php echo htmlspecialchars(t('volumes_in_report'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo isset($sdf['Volumes']) && is_array($sdf['Volumes']) ? count($sdf['Volumes']) : 0; ?></div>
                </div>
                <?php else: ?>
                <p style="color: var(--muted); font-size: 14px;"><?php echo htmlspecialchars(t('unavailable'), ENT_QUOTES, 'UTF-8'); ?> (HTTP <?php echo (int)($systemDfData['code'] ?? 0); ?>)</p>
                <?php endif; ?>
                <?php if (isset($systemInfo['data']['URL'])): ?>
                <h3 style="margin-top: 22px;"><?php echo htmlspecialchars(t('docker_endpoint'), ENT_QUOTES, 'UTF-8'); ?> #<?php echo (int)$epId; ?></h3>
                <div class="info-grid">
                    <div><strong><?php echo htmlspecialchars(t('endpoint_name'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars($systemInfo['data']['Name'] ?? ''); ?></div>
                    <div><strong><?php echo htmlspecialchars(t('docker_endpoint_url'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars($systemInfo['data']['URL'] ?? 'N/A'); ?></div>
                    <div><strong><?php echo htmlspecialchars(t('docker_endpoint_status'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo isset($systemInfo['data']['Status']) ? (int)$systemInfo['data']['Status'] : 'N/A'; ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="docker-modal" id="noticeModal" aria-hidden="true" onclick="closeNoticeModalOnBackdrop(event)">
        <div class="message-modal-card" role="dialog" aria-modal="true" aria-labelledby="noticeModalTitle">
            <div class="message-modal-body">
                <div class="message-modal-icon" id="noticeModalIcon"><?php echo ico('info'); ?></div>
                <div class="message-modal-content">
                    <h3 id="noticeModalTitle"></h3>
                    <p id="noticeModalText"></p>
                </div>
            </div>
            <div class="message-modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeNoticeModal()"><?php echo htmlspecialchars(t('docker_modal_ok'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
        </div>
    </div>

    <div class="docker-modal" id="confirmModal" aria-hidden="true" onclick="closeConfirmModalOnBackdrop(event)">
        <div class="message-modal-card" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
            <div class="message-modal-body">
                <div class="message-modal-icon warn"><?php echo ico('info'); ?></div>
                <div class="message-modal-content">
                    <h3 id="confirmModalTitle"><?php echo htmlspecialchars(t('docker_modal_confirm_title'), ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p id="confirmModalText"></p>
                </div>
            </div>
            <div class="message-modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeConfirmModal()"><?php echo htmlspecialchars(t('docker_modal_cancel'), ENT_QUOTES, 'UTF-8'); ?></button>
                <button type="button" class="btn btn-update" onclick="submitConfirmedAction()"><?php echo htmlspecialchars(t('docker_modal_confirm'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
        </div>
    </div>

    <div class="docker-modal" id="dockerModal" aria-hidden="true" onclick="closeDockerModalOnBackdrop(event)">
        <div class="docker-modal-card" role="dialog" aria-modal="true" aria-labelledby="dockerModalTitle">
            <div class="docker-modal-head">
                <div class="docker-modal-title" id="dockerModalTitle"></div>
                <button type="button" class="docker-modal-close" onclick="closeDockerModal()" aria-label="<?php echo htmlspecialchars(t('docker_close'), ENT_QUOTES, 'UTF-8'); ?>">&times;</button>
            </div>
            <iframe class="docker-modal-frame" id="dockerModalFrame" title="<?php echo htmlspecialchars(t('docker_details'), ENT_QUOTES, 'UTF-8'); ?>"></iframe>
        </div>
    </div>
    
    <script>
        const I18N = <?php echo json_encode($i18n[$lang] ?? $i18n['en'], JSON_UNESCAPED_UNICODE); ?>;
        const I18N_EN = <?php echo json_encode($i18n['en'], JSON_UNESCAPED_UNICODE); ?>;
        function t(key){return I18N[key] ?? I18N_EN[key] ?? key;}
        function tf(key, vars){let text=t(key);for(const n in (vars||{})){text=text.split('{'+n+'}').join(vars[n]);}return text;}
        const sectionTitles = <?php echo json_encode(['stacks' => t('stacks'), 'containers' => t('containers'), 'networks' => t('networks'), 'volumes' => t('volumes'), 'images' => t('images'), 'info' => t('system')], JSON_UNESCAPED_UNICODE); ?>;
        const startupModals = <?php echo json_encode($startupModals, JSON_UNESCAPED_UNICODE); ?>;
        const modalIcons = <?php echo json_encode(['success' => ico('check'), 'error' => ico('x'), 'info' => ico('info')], JSON_UNESCAPED_UNICODE); ?>;
        const modalLabels = <?php echo json_encode(['noticeTitle' => t('docker_modal_notice_title'), 'confirmTitle' => t('docker_modal_confirm_title')], JSON_UNESCAPED_UNICODE); ?>;
        let noticeQueue = [];
        let pendingActionHref = '';
        let liveBusy = false;
        let monitorContainerId = '';
        let monitorTimer = null;
        const chartHistory = {};
        function toBytesText(v){
            const n = Number(v||0);
            if(!Number.isFinite(n)||n<=0)return '0 B';
            const u=['B','KB','MB','GB','TB']; let x=n; let i=0;
            while(x>=1024&&i<u.length-1){x/=1024;i++;}
            return `${x.toFixed(i===0?0:2)} ${u[i]}`;
        }
        function pushMetric(name,val){
            if(!chartHistory[name])chartHistory[name]=[];
            chartHistory[name].push(Number(val)||0);
            if(chartHistory[name].length>36)chartHistory[name].shift();
        }
        function drawChart(canvasId, keys, scaleLabelId, unit){
            const c=document.getElementById(canvasId); if(!c)return 0;
            const dpr=window.devicePixelRatio||1;
            const cw=c.clientWidth||300; const ch=c.clientHeight||86;
            c.width=Math.floor(cw*dpr); c.height=Math.floor(ch*dpr);
            const ctx=c.getContext('2d'); ctx.setTransform(dpr,0,0,dpr,0,0);
            const w=cw; const h=ch; const p={l:38,r:8,t:8,b:12};
            const pw=w-p.l-p.r; const ph=h-p.t-p.b;
            ctx.clearRect(0,0,w,h);
            ctx.fillStyle='rgba(0,0,0,0.08)'; ctx.fillRect(p.l,p.t,pw,ph);
            ctx.strokeStyle='rgba(255,255,255,0.09)'; ctx.lineWidth=1;
            for(let i=0;i<=4;i++){const y=p.t+(ph*i/4);ctx.beginPath();ctx.moveTo(p.l,y);ctx.lineTo(p.l+pw,y);ctx.stroke();}
            let max=1;
            keys.forEach(key=>{const arr=chartHistory[key]||[];if(arr.length){max=Math.max(max,...arr);}});
            const colors=['#22d3ee','#f59e0b','#4ade80'];
            keys.forEach((key,idx)=>{
                const arr=chartHistory[key]||[]; if(arr.length<2)return;
                ctx.strokeStyle=colors[idx]||'#22d3ee'; ctx.lineWidth=2; ctx.beginPath();
                arr.forEach((v,i)=>{const x=p.l+(i*(pw/(arr.length-1))); const y=p.t+ph-((v/max)*ph); if(i===0)ctx.moveTo(x,y); else ctx.lineTo(x,y);});
                ctx.stroke();
            });
            const topLabel=unit==='bytes'?toBytesText(max):(unit==='percent'?`${max.toFixed(0)}%`:Math.round(max).toString());
            ctx.save();
            ctx.translate(11,p.t+ph); ctx.rotate(-Math.PI/2);
            ctx.fillStyle='rgba(148,163,184,0.85)'; ctx.font='10px sans-serif';
            ctx.textAlign='center'; ctx.textBaseline='middle';
            ctx.fillText(topLabel,0,ph); ctx.fillText('0',0,0);
            ctx.restore();
            const sl=document.getElementById(scaleLabelId);
            if(sl)sl.textContent=unit==='bytes'?tf('max_label',{value:toBytesText(max)}):(unit==='percent'?tf('max_label',{value:max.toFixed(1)+'%'}):tf('max_label',{value:String(Math.round(max))}));
            return max;
        }
        function updateLiveUi(stats){
            if(!stats)return;
            const set=(id,val)=>{const el=document.getElementById(id);if(el)el.textContent=val;};
            set('mCpu', `${Number(stats.cpu_total||0).toFixed(1)}%`);
            set('mMem', `${toBytesText(stats.mem_used)} / ${toBytesText(stats.mem_limit)}`);
            set('mNet', `${toBytesText(stats.net_rx)} / ${toBytesText(stats.net_tx)}`);
            set('mIo', `${toBytesText(stats.blk_read)} / ${toBytesText(stats.blk_write)}`);
            set('mPids', String(stats.pids || 0));
            set('mContainers', monitorContainerId ? monitorContainerId.slice(0,12) : t('na'));
            pushMetric('cpu',stats.cpu_total); pushMetric('mem',stats.mem_used); pushMetric('rx',stats.net_rx); pushMetric('tx',stats.net_tx); pushMetric('br',stats.blk_read); pushMetric('bw',stats.blk_write); pushMetric('pids',stats.pids);
            if(monitorContainerId)try{localStorage.setItem('docker_chart_'+monitorContainerId,JSON.stringify(chartHistory));}catch(e){}
            drawChart('chartCpu',['cpu'],'sCpu','percent');
            drawChart('chartMem',['mem'],'sMem','bytes');
            drawChart('chartNet',['rx','tx'],'sNet','bytes');
            drawChart('chartIo',['br','bw'],'sIo','bytes');
            drawChart('chartPids',['pids'],'sPids','count');
            drawChart('chartContainers',['cpu','pids'],'sContainer','count');
        }
        async function refreshLive(){
            if(document.hidden||liveBusy||!monitorContainerId)return;
            liveBusy=true;
            try{
                const u=new URL(window.location.href);
                u.searchParams.set('view','stats_data');
                u.searchParams.set('id',monitorContainerId);
                u.searchParams.set('_ts',Date.now().toString());
                const r=await fetch(u.toString(),{credentials:'same-origin'});
                const d=await r.json();
                if(d&&d.ok&&d.stats)updateLiveUi(d.stats);
            }catch(e){}
            liveBusy=false;
        }
        function startContainerMonitor(containerId, containerName){
            if(!containerId)return;
            monitorContainerId=containerId;
            Object.keys(chartHistory).forEach(k=>{chartHistory[k]=[];});
            const sec=document.getElementById('chartsSection');
            if(sec)sec.style.display='block';
            const mt=document.getElementById('monitorTitle');
            if(mt)mt.textContent=tf('live_monitoring_colon',{name:containerName||containerId.slice(0,12)});
            var saved=localStorage.getItem('docker_chart_'+containerId);
            if(saved){try{var p=JSON.parse(saved);if(p&&typeof p==='object'){Object.keys(p).forEach(function(k){if(Array.isArray(p[k]))chartHistory[k]=p[k];});}}catch(e){}}
            if(Object.keys(chartHistory).some(function(k){return chartHistory[k].length>0;})){
                drawChart('chartCpu',['cpu'],'sCpu','percent');
                drawChart('chartMem',['mem'],'sMem','bytes');
                drawChart('chartNet',['rx','tx'],'sNet','bytes');
                drawChart('chartIo',['br','bw'],'sIo','bytes');
                drawChart('chartPids',['pids'],'sPids','count');
                drawChart('chartContainers',['cpu','pids'],'sContainer','count');
            }
            refreshLive();
            if(monitorTimer)clearInterval(monitorTimer);
            monitorTimer=setInterval(refreshLive,5000);
        }
        function applyContainerFilter(stackName){
            const cf=document.getElementById('containerFilter');
            if(cf)cf.value=stackName||'';
            const q=(stackName||'').trim().toLowerCase();
            document.querySelectorAll('#containersSection .c-row').forEach(row=>{
                const s=(row.getAttribute('data-search')||'').toLowerCase();
                const ok=!q||s.indexOf(q)!==-1;
                row.style.display=ok?'':'none';
                const det=row.nextElementSibling;
                if(det&&det.classList.contains('c-actions')&&!ok){det.style.display='none';row.classList.remove('expanded');}
            });
        }
        function showSection(section, el) {
            ['stacks','containers','networks','volumes','images','info'].forEach(s => {
                const n = document.getElementById(s + 'Section');
                if (n) n.style.display = 'none';
            });
            const sec = document.getElementById(section + 'Section');
            if (sec) sec.style.display = 'block';
            document.getElementById('pageTitle').innerText = sectionTitles[section] || section;
            document.querySelectorAll('.nav-item').forEach(item => { item.classList.remove('active'); });
            if (el) el.classList.add('active');
            const fb = document.getElementById('containerFilterBar');
            if (fb) fb.style.display = (section === 'containers' || section === 'stacks') ? 'flex' : 'none';
            const charts = document.getElementById('chartsSection');
            if (charts && section !== 'containers') charts.style.display = 'none';
            if (charts && section === 'containers' && monitorContainerId) charts.style.display = 'block';
        }
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarBackdrop').classList.toggle('show');
        }
        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebarBackdrop').classList.remove('show');
        }
        function openDockerModal(e, href, title) {
            e.preventDefault();
            e.stopPropagation();
            const modal = document.getElementById('dockerModal');
            const frame = document.getElementById('dockerModalFrame');
            document.getElementById('dockerModalTitle').textContent = title || '';
            frame.src = href;
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
        }
        function closeDockerModal() {
            const modal = document.getElementById('dockerModal');
            const frame = document.getElementById('dockerModalFrame');
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
            frame.src = 'about:blank';
        }
        function closeDockerModalOnBackdrop(e) {
            if (e.target && e.target.id === 'dockerModal') closeDockerModal();
        }
        function setModalIcon(target, type) {
            target.classList.remove('success', 'error', 'warn');
            if (type === 'success' || type === 'error' || type === 'warn') target.classList.add(type);
            target.innerHTML = modalIcons[type] || modalIcons.info || '';
        }
        function openNoticeModal(notice) {
            const modal = document.getElementById('noticeModal');
            const icon = document.getElementById('noticeModalIcon');
            const type = notice.type || 'info';
            setModalIcon(icon, type);
            document.getElementById('noticeModalTitle').textContent = notice.title || modalLabels.noticeTitle || '';
            document.getElementById('noticeModalText').textContent = notice.message || '';
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
        }
        function closeNoticeModal() {
            const modal = document.getElementById('noticeModal');
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
            if (noticeQueue.length > 0) openNoticeModal(noticeQueue.shift());
        }
        function closeNoticeModalOnBackdrop(e) {
            if (e.target && e.target.id === 'noticeModal') closeNoticeModal();
        }
        function confirmDockerAction(e, link) {
            e.preventDefault();
            e.stopPropagation();
            pendingActionHref = link.href;
            document.getElementById('confirmModalTitle').textContent = modalLabels.confirmTitle || '';
            document.getElementById('confirmModalText').textContent = link.getAttribute('data-confirm') || '';
            const modal = document.getElementById('confirmModal');
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
        }
        function closeConfirmModal() {
            pendingActionHref = '';
            const modal = document.getElementById('confirmModal');
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
        }
        function closeConfirmModalOnBackdrop(e) {
            if (e.target && e.target.id === 'confirmModal') closeConfirmModal();
        }
        function submitConfirmedAction() {
            const href = pendingActionHref;
            closeConfirmModal();
            if (href) window.location.href = href;
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDockerModal();
                closeConfirmModal();
                closeNoticeModal();
            }
        });
        document.querySelectorAll('.containers-table-wrap').forEach(wrap => {
            const syncPanelOffset = () => {
                wrap.style.setProperty('--table-scroll-left', `${wrap.scrollLeft}px`);
            };
            wrap.addEventListener('scroll', syncPanelOffset, { passive: true });
            syncPanelOffset();
        });
        document.querySelectorAll('.c-row').forEach(row => {
            row.addEventListener('click', function(e) {
                if (e.target.closest('a')) return;
                const open = !this.classList.contains('expanded');
                document.querySelectorAll('.c-row').forEach(r => { r.classList.remove('expanded'); });
                document.querySelectorAll('.c-actions').forEach(a => { a.style.display = 'none'; });
                if (open) {
                    this.classList.add('expanded');
                    const next = this.nextElementSibling;
                    if (next && next.classList.contains('c-actions')) next.style.display = 'table-row';
                    if (this.closest('#containersSection')) {
                        startContainerMonitor(this.getAttribute('data-container-id') || '', this.getAttribute('data-container-name') || '');
                    }
                }
            });
            row.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); }
            });
        });
        const cf = document.getElementById('containerFilter');
        if (cf) cf.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('#containersSection .c-row, #imagesSection .c-row, #volumesSection .c-row, #stacksSection .c-row').forEach(row => {
                const s = row.getAttribute('data-search') || '';
                const ok = !q || s.indexOf(q) !== -1;
                row.style.display = ok ? '' : 'none';
                const det = row.nextElementSibling;
                if (det && det.classList.contains('c-actions')) {
                    if (!ok) { det.style.display = 'none'; row.classList.remove('expanded'); }
                }
            });
        });
        if (startupModals.length > 0) {
            noticeQueue = startupModals.slice(1);
            openNoticeModal(startupModals[0]);
        }
        showSection('stacks', document.querySelector('.sidebar .nav-item.active'));
        setTimeout(function(){
            document.querySelectorAll('#stacksSection .c-row').forEach(function(r){
                var cells=r.querySelectorAll('td');
                if(cells.length>=2&&cells[1].textContent.trim()==='1'){r.click();}
            });
        },100);
    </script>
    <script src="/project/assets/docker/manager.js"></script>
</body>
</html>