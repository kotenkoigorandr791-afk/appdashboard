</head>

<body class="<?= $theme ?> admin-embed">

<div class="admin-monitor-topbar">
    <div class="admin-monitor-title"><?= htmlspecialchars($t['title']) ?></div>
    <div class="admin-monitor-tabs">
        <button class="admin-monitor-tab active" type="button" data-section-target="graphs"><svg viewBox="0 0 24 24"><path d="M4 19V5"/><path d="M4 19h16"/><path d="m7 15 4-4 3 3 5-7"/></svg><?= htmlspecialchars($t['section_graphs']) ?></button>
        <button class="admin-monitor-tab" type="button" data-section-target="monitorCharts"><svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m7 14 4-4 3 3 5-6"/></svg><?= htmlspecialchars($monitorChartsTitle) ?></button>
        <button class="admin-monitor-tab" type="button" data-section-target="uptime"><svg viewBox="0 0 24 24"><path d="M12 6v6l4 2"/><circle cx="12" cy="12" r="9"/></svg><?= htmlspecialchars($t['uptime_monitoring'] ?? 'Uptime') ?></button>
        <button class="admin-monitor-tab" type="button" data-section-target="disk"><svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg><?= htmlspecialchars($t['disk']) ?></button>
        <button class="admin-monitor-tab" type="button" data-section-target="nginx"><svg viewBox="0 0 24 24"><path d="M12 3 4 7v10l8 4 8-4V7z"/><path d="M9 9v6"/><path d="m9 9 6 6"/><path d="M15 9v6"/></svg><?= htmlspecialchars($t['nginx_modules']) ?></button>
        <button class="admin-monitor-tab" type="button" data-section-target="php"><svg viewBox="0 0 24 24"><ellipse cx="12" cy="12" rx="10" ry="6"/><text x="12" y="14.4" text-anchor="middle" font-size="5.8" font-weight="800" fill="currentColor" stroke="none">PHP</text></svg><?= htmlspecialchars($t['php_modules']) ?></button>
    </div>
    <div class="admin-monitor-actions">
        <?php if ($showPhpInfoButton): ?>
        <button class="btn-secondary icon-button" type="button" onclick="openPhpInfo()"><svg viewBox="0 0 24 24"><path d="M12 8v8"/><path d="M8 12h8"/><circle cx="12" cy="12" r="10"/></svg><?= htmlspecialchars($t['phpinfo_button']) ?></button>
        <div id="autoZoomToggle" class="auto-zoom-toggle" onclick="toggleAutoZoom()" title="<?= htmlspecialchars($t['auto_zoom']) ?>">
            <svg class="zoom-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" x2="16.65" y1="21" y2="16.65"/><line x1="11" x2="11" y1="8" y2="14"/><line x1="8" x2="14" y1="11" y2="11"/></svg>
            <span class="toggle-label"><?= htmlspecialchars($t['auto_zoom']) ?></span>
            <div class="switch-track"><div class="switch-thumb"></div></div>
        </div>
        <?php endif; ?>
        <button class="btn-secondary icon-button" type="button" onclick="refreshData()"><svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg><?= htmlspecialchars($t['refresh']) ?></button>
    </div>
</div>
<div id="connectionStatus" class="connection-status" style="display: none;">
    <span id="statusText"></span>
    <span id="retryCount"></span>
</div>

<div class="container scrollable-content">

    <section id="monitorSection-graphs" class="monitor-section active" data-section-panel="graphs">
        <div class="section-cluster">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?= htmlspecialchars($t['cpu_usage']) ?></h3>
                    <canvas id="cpuChart" width="150" height="150" onclick="openGraphModal('cpu')"></canvas>
                    <div id="cpuVal" class="stat-val">0%</div>
                    <div id="cpuError" class="stat-error" style="display: none;"></div>
                    <?php if ($userRole >= 2): ?>
                    <button class="stat-cleanup-btn" type="button" onclick="cleanupMetricData('cpu')" title="<?= htmlspecialchars($t['cleanup_device_data'] ?? 'Delete device data') ?>"><svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                    <?php endif; ?>
                </div>
                <div class="stat-card">
                    <h3><?= htmlspecialchars($t['memory_usage']) ?></h3>
                    <canvas id="memChart" width="150" height="150" onclick="openGraphModal('memory')"></canvas>
                    <div id="memVal" class="stat-val">0%</div>
                    <div id="memError" class="stat-error" style="display: none;"></div>
                    <?php if ($userRole >= 2): ?>
                    <button class="stat-cleanup-btn" type="button" onclick="cleanupMetricData('memory')" title="<?= htmlspecialchars($t['cleanup_device_data'] ?? 'Delete device data') ?>"><svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                    <?php endif; ?>
                </div>
                <div class="stat-card">
                    <h3><?= htmlspecialchars($t['disk_usage']) ?></h3>
                    <canvas id="diskChart" width="150" height="150" onclick="openGraphModal('disk')"></canvas>
                    <div id="diskVal" class="stat-val">0%</div>
                    <div id="diskError" class="stat-error" style="display: none;"></div>
                    <?php if ($userRole >= 2): ?>
                    <button class="stat-cleanup-btn" type="button" onclick="cleanupMetricData('disk')" title="<?= htmlspecialchars($t['cleanup_device_data'] ?? 'Delete device data') ?>"><svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                    <?php endif; ?>
                </div>
                <div class="stat-card">
                    <h3><?= htmlspecialchars($t['network_usage']) ?></h3>
                    <canvas id="networkChart" width="150" height="150" onclick="openGraphModal('network')"></canvas>
                    <div id="networkVal" class="stat-val">0 KB/s</div>
                    <div id="networkError" class="stat-error" style="display: none;"></div>
                    <?php if ($userRole >= 2): ?>
                    <button class="stat-cleanup-btn" type="button" onclick="cleanupMetricData('network')" title="<?= htmlspecialchars($t['cleanup_device_data'] ?? 'Delete device data') ?>"><svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                    <?php endif; ?>
                </div>
                <div class="stat-card">
                    <h3><?= htmlspecialchars($t['swap_usage']) ?></h3>
                    <canvas id="swapChart" width="150" height="150" onclick="openGraphModal('swap')"></canvas>
                    <div id="swapVal" class="stat-val">0%</div>
                    <div id="swapError" class="stat-error" style="display: none;"></div>
                    <?php if ($userRole >= 2): ?>
                    <button class="stat-cleanup-btn" type="button" onclick="cleanupMetricData('swap')" title="<?= htmlspecialchars($t['cleanup_device_data'] ?? 'Delete device data') ?>"><svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                    <?php endif; ?>
                </div>
                <div class="stat-card uptime-card">
                    <h3><?= htmlspecialchars($t['uptime']) ?></h3>
                    <div id="uptimeVal" class="stat-val">-</div>
                    <div id="uptimeError" class="stat-error" style="display: none;"></div>
                </div>
            </div>

            <section>
                <h2><?= htmlspecialchars($t['network_interfaces']) ?></h2>
                <div id="networkGrid" class="network-grid">
                    <div class="loading-placeholder"><?= htmlspecialchars($t['loading']) ?></div>
                </div>
            </section>
        </div>
    </section>

    <section id="monitorSection-monitorCharts" class="monitor-section" data-section-panel="monitorCharts">
        <div class="section-cluster">
            <section>
                <div class="monitor-charts-toolbar">
                    <h2><?= htmlspecialchars($monitorChartsTitle) ?></h2>
                    <select id="monitorChartsPeriod" class="monitor-charts-period">
                        <option value="1h">1h</option>
                        <option value="6h">6h</option>
                        <option value="24h" selected>24h</option>
                        <option value="7d">7d</option>
                        <option value="30d">30d</option>
                    </select>
                </div>
                <div id="monitorChartsStatus" class="stats-status"><?= htmlspecialchars($t['loading']) ?></div>
                <div id="monitorSystemCharts" class="monitor-charts-grid"></div>
                <div id="monitorDiskCharts" class="monitor-charts-grid"></div>
                <div id="monitorHourlyCharts" class="monitor-charts-grid"></div>
            </section>
        </div>
    </section>

    <section id="monitorSection-uptime" class="monitor-section" data-section-panel="uptime">
        <div class="section-cluster">
            <section>
                <div class="uptime-toolbar">
                    <h2><?= htmlspecialchars($t['uptime_monitoring'] ?? 'Uptime Monitoring') ?></h2>
                    <div class="uptime-toolbar-actions">
                        <?php if ($userRole >= 2): ?>
                        <div class="uptime-add-dropdown" id="uptimeAddDropdown">
                            <button class="btn-primary icon-button" type="button" onclick="toggleUptimeAddDropdown(event)"><svg viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg><?= htmlspecialchars($t['add_uptime_check'] ?? 'Add check') ?></button>
                            <div class="uptime-add-dropdown-menu" id="uptimeAddDropdownMenu">
                                <button type="button" onclick="openUptimeCheckModal(); closeUptimeAddDropdown();"><svg viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg> <?= htmlspecialchars($t['add_manually'] ?? 'Add manually') ?></button>
                                <button type="button" onclick="importChecksFromJson(); closeUptimeAddDropdown();"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> <?= htmlspecialchars($t['import_from_json'] ?? 'Import from JSON') ?></button>
                            </div>
                        </div>
                        <?php endif; ?>
                        <button id="toggleAllDockerMetricsBtn" class="btn-secondary icon-button" type="button" onclick="toggleAllDockerMetrics()"><svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg><span>Expand all</span></button>
                        <button class="btn-secondary icon-button" type="button" onclick="runUptimeChecks()"><svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg><?= htmlspecialchars($t['run_checks'] ?? 'Run checks') ?></button>
                    </div>
                </div>
                <div id="uptimeSummary" class="uptime-summary">
                    <div class="loading-placeholder"><?= htmlspecialchars($t['loading']) ?></div>
                </div>
                <div id="uptimeChecksGrid" class="uptime-checks-grid">
                    <div class="loading-placeholder"><?= htmlspecialchars($t['loading']) ?></div>
                </div>
            </section>
        </div>
    </section>

    <section id="monitorSection-disk" class="monitor-section" data-section-panel="disk">
        <div class="section-cluster">
            <section>
                <h2><?= htmlspecialchars($t['connected_drives']) ?></h2>
                <div id="drivesGrid" class="drives-grid">
                    <div class="loading-placeholder"><?= htmlspecialchars($t['loading']) ?></div>
                </div>
            </section>

            <section>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h2 style="margin: 0;"><?= htmlspecialchars($t['smart_diagnostics']) ?></h2>
                    <?php if ($userRole >= 2): ?>
                    <button class="btn-secondary icon-button" type="button" onclick="openCleanupTimePeriodModal()"><svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg><?= htmlspecialchars($t['cleanup_time_period'] ?? 'Delete by time period') ?></button>
                    <?php endif; ?>
                </div>
                <div id="smartGrid" class="smart-grid">
                    <div class="loading-placeholder"><?= htmlspecialchars($t['loading']) ?></div>
                </div>
            </section>
        </div>
    </section>

    <section id="monitorSection-nginx" class="monitor-section" data-section-panel="nginx">
        <h2><?= htmlspecialchars($t['nginx_modules']) ?></h2>
        <div class="search-box">
            <svg class="search-icon" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>
            <input id="nginxSearch" type="text" placeholder="<?= htmlspecialchars($t['search']) ?>" onkeyup="filterTable('nginxSearch','nginxBody')" value="">
        </div>
        <table class="modules-table">
            <thead><tr><th><?= htmlspecialchars($t['module_name']) ?></th><th><?= htmlspecialchars($t['status']) ?></th></tr></thead>
            <tbody id="nginxBody">
                <tr><td colspan="2"><?= htmlspecialchars($t['loading']) ?></td></tr>
            </tbody>
        </table>
    </section>

    <section id="monitorSection-php" class="monitor-section" data-section-panel="php">
        <h2><?= htmlspecialchars($t['php_modules']) ?></h2>
        <div class="search-box">
            <svg class="search-icon" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>
            <input id="phpSearch" type="text" placeholder="<?= htmlspecialchars($t['search']) ?>" onkeyup="filterTable('phpSearch','phpBody')" value="">
        </div>
        <table class="modules-table">
            <thead><tr><th><?= htmlspecialchars($t['extension_name']) ?></th><th><?= htmlspecialchars($t['status']) ?></th></tr></thead>
            <tbody id="phpBody">
                <tr><td colspan="2"><?= htmlspecialchars($t['loading']) ?></td></tr>
            </tbody>
        </table>
    </section>
</div>
</div>

<?php if ($userRole >= 2): ?>
<div id="uptimeCheckModal" class="modal uptime-check-modal">
    <span class="modal-close" onclick="closeUptimeCheckModal()">&times;</span>
    <div class="modal-content uptime-modal-content">
        <div class="uptime-form-panel">
            <h2><?= htmlspecialchars($t['add_uptime_check'] ?? 'Add check') ?></h2>
            <form id="uptimeCheckForm" class="uptime-check-form" onsubmit="saveUptimeCheck(event)">
                <input type="hidden" name="id" value="">
                <label><span><?= htmlspecialchars($t['monitor_name'] ?? 'Name') ?></span><input name="name" type="text" required></label>
                <label><span><?= htmlspecialchars($t['monitor_type'] ?? 'Type') ?></span><select name="type"><option value="http">HTTP</option><option value="keyword">HTTP Keyword</option><option value="tcp">TCP Port</option><option value="ping">Ping</option><option value="dns">DNS</option></select></label>
                <label><span><?= htmlspecialchars($t['monitor_target'] ?? 'Target') ?></span><input name="target" type="text" placeholder="https://example.com or host:443" required></label>
                <label><span><?= htmlspecialchars($t['method'] ?? 'Method') ?></span><select name="method"><option>GET</option><option>HEAD</option><option>POST</option></select></label>
                <label><span><?= htmlspecialchars($t['expected_status'] ?? 'Expected status') ?></span><input name="expected_status" type="number" min="0" value="200"></label>
                <label><span><?= htmlspecialchars($t['keyword'] ?? 'Keyword') ?></span><input name="keyword" type="text"></label>
                <label><span><?= htmlspecialchars($t['interval_sec'] ?? 'Interval, sec') ?></span><input name="interval_sec" type="number" min="10" value="60"></label>
                <label><span><?= htmlspecialchars($t['timeout_sec'] ?? 'Timeout, sec') ?></span><input name="timeout_sec" type="number" min="1" value="10"></label>
                <label class="uptime-enabled"><input name="enabled" type="checkbox" checked><span><?= htmlspecialchars($t['enabled'] ?? 'Enabled') ?></span></label>
                <div class="uptime-form-actions">
                    <button class="btn-primary" type="submit"><?= htmlspecialchars($t['save'] ?? 'Save') ?></button>
                    <button class="btn-secondary" type="button" onclick="closeUptimeCheckModal()"><?= htmlspecialchars($t['cancel'] ?? 'Cancel') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="cleanupTimePeriodModal" class="modal uptime-check-modal">
    <span class="modal-close" onclick="closeCleanupTimePeriodModal()">&times;</span>
    <div class="modal-content uptime-modal-content">
        <div class="uptime-form-panel">
            <h2><?= htmlspecialchars($t['cleanup_time_period'] ?? 'Delete by time period') ?></h2>
            <form id="cleanupTimePeriodForm" class="uptime-check-form" onsubmit="submitCleanupTimePeriod(event)">
                <label><span><?= htmlspecialchars($t['from'] ?? 'From') ?></span><input id="cleanupStartTime" type="datetime-local" required></label>
                <label><span><?= htmlspecialchars($t['to'] ?? 'To') ?></span><input id="cleanupEndTime" type="datetime-local" required></label>
                <div class="uptime-form-actions">
                    <button class="btn-primary" type="submit"><?= htmlspecialchars($t['delete'] ?? 'Delete') ?></button>
                    <button class="btn-secondary" type="button" onclick="closeCleanupTimePeriodModal()"><?= htmlspecialchars($t['cancel'] ?? 'Cancel') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="phpinfoModal" class="modal">
    <span class="modal-close" onclick="closePhpInfo()">&times;</span>
    <div id="phpinfoContent" class="modal-content"></div>
</div>

<div id="graphModal" class="modal">
    <div class="modal-content graph-modal-content">
        <div class="graph-container">
            <span class="modal-close" onclick="closeGraphModal()">&times;</span>
            <div id="graphTitle" class="graph-title"></div>
            <?php if ($userRole >= 2): ?>
            <button id="graphCleanupBtn" class="graph-cleanup-btn" type="button"><svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
            <?php endif; ?>
            <div class="graph-canvas-container">
                <canvas id="graphChart"></canvas>
            </div>
        </div>
    </div>
</div>
