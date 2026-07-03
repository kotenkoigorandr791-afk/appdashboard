</head>

<body class="<?= $theme ?>">

<canvas id="constellation-bg"></canvas>

<div id="sideMenuBackdrop" class="side-menu-backdrop" onclick="closeSideMenu()"></div>
<aside id="sideMenu" class="side-menu" aria-hidden="true">
    <div class="side-menu-header">
        <div class="side-menu-title">Menu</div>
        <button class="side-menu-close" type="button" onclick="closeSideMenu()" aria-label="Close menu">&times;</button>
    </div>
    <div class="user-info">
        <div class="user-avatar"><?= strtoupper(substr($currentUser, 0, 1)) ?></div>
        <div class="user-details">
            <span class="user-name"><?= htmlspecialchars($currentUser) ?></span>
            <span class="user-role"><?= htmlspecialchars($userRoleName) ?></span>
        </div>
    </div>
    <div class="side-menu-actions">
        <a class="side-menu-link" href="monitor_stats.php?lang=<?= urlencode($lang) ?>&theme=<?= urlencode($theme) ?>&color=<?= urlencode($color) ?>">Monitoring Charts</a>
        <?php if ($showPhpInfoButton): ?>
        <button class="btn-primary" type="button" onclick="openPhpInfo(); closeSideMenu();"><?= htmlspecialchars($t['phpinfo_button']) ?></button>
        <div id="autoZoomToggle" class="auto-zoom-toggle" onclick="toggleAutoZoom()" title="<?= htmlspecialchars($t['auto_zoom']) ?>">
            <svg class="zoom-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" x2="16.65" y1="21" y2="16.65"/>
                <line x1="11" x2="11" y1="8" y2="14"/>
                <line x1="8" x2="14" y1="11" y2="11"/>
            </svg>
            <span class="toggle-label"><?= htmlspecialchars($t['auto_zoom']) ?></span>
            <div class="switch-track">
                <div class="switch-thumb"></div>
            </div>
        </div>
        <?php endif; ?>
        <button class="btn-secondary" type="button" onclick="refreshData(); closeSideMenu();"><?= htmlspecialchars($t['refresh']) ?></button>
    </div>
</aside>

<div class="container">
    <header>
        <div class="header-wrapper">
            <div class="header-top-row">
                <div class="header-title">
                    <h1><?= htmlspecialchars($t['title']) ?></h1>
                </div>
                <div class="header-buttons">
                    <button class="btn-secondary mobile-refresh-btn" onclick="refreshData()" title="<?= htmlspecialchars($t['refresh']) ?>">↻</button>
                    <button class="side-menu-toggle" type="button" onclick="openSideMenu()" aria-label="Open menu" title="Menu">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-menu-icon lucide-square-menu">
                            <rect width="18" height="18" x="3" y="3" rx="2"></rect>
                            <path d="M7 8h10"></path>
                            <path d="M7 12h10"></path>
                            <path d="M7 16h10"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div id="connectionStatus" class="connection-status" style="display: none;">
                <span id="statusText"></span>
                <span id="retryCount"></span>
            </div>
        </div>
    </header>

    <nav class="section-strip" aria-label="Monitor Sections">
        <button class="section-strip-button active" type="button" data-section-target="graphs"><?= htmlspecialchars($t['section_graphs']) ?></button>
        <button class="section-strip-button" type="button" data-section-target="disk"><?= htmlspecialchars($t['disk']) ?></button>
        <button class="section-strip-button" type="button" data-section-target="nginx"><?= htmlspecialchars($t['nginx_modules']) ?></button>
        <button class="section-strip-button" type="button" data-section-target="php"><?= htmlspecialchars($t['php_modules']) ?></button>
    </nav>

    <section id="monitorSection-graphs" class="monitor-section active" data-section-panel="graphs">
        <div class="section-cluster">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?= htmlspecialchars($t['cpu_usage']) ?></h3>
                    <canvas id="cpuChart" width="150" height="150" onclick="openGraphModal('cpu')"></canvas>
                    <div id="cpuVal" class="stat-val">0%</div>
                    <div id="cpuError" class="stat-error" style="display: none;"></div>
                </div>
                <div class="stat-card">
                    <h3><?= htmlspecialchars($t['memory_usage']) ?></h3>
                    <canvas id="memChart" width="150" height="150" onclick="openGraphModal('memory')"></canvas>
                    <div id="memVal" class="stat-val">0%</div>
                    <div id="memError" class="stat-error" style="display: none;"></div>
                </div>
                <div class="stat-card">
                    <h3><?= htmlspecialchars($t['disk_usage']) ?></h3>
                    <canvas id="diskChart" width="150" height="150" onclick="openGraphModal('disk')"></canvas>
                    <div id="diskVal" class="stat-val">0%</div>
                    <div id="diskError" class="stat-error" style="display: none;"></div>
                </div>
                <div class="stat-card">
                    <h3><?= htmlspecialchars($t['network_usage']) ?></h3>
                    <canvas id="networkChart" width="150" height="150" onclick="openGraphModal('network')"></canvas>
                    <div id="networkVal" class="stat-val">0 KB/s</div>
                    <div id="networkError" class="stat-error" style="display: none;"></div>
                </div>
                <div class="stat-card">
                    <h3><?= htmlspecialchars($t['swap_usage']) ?></h3>
                    <canvas id="swapChart" width="150" height="150" onclick="openGraphModal('swap')"></canvas>
                    <div id="swapVal" class="stat-val">0%</div>
                    <div id="swapError" class="stat-error" style="display: none;"></div>
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

    <section id="monitorSection-disk" class="monitor-section" data-section-panel="disk">
        <div class="section-cluster">
            <section>
                <h2><?= htmlspecialchars($t['connected_drives']) ?></h2>
                <div id="drivesGrid" class="drives-grid">
                    <div class="loading-placeholder"><?= htmlspecialchars($t['loading']) ?></div>
                </div>
            </section>

            <section>
                <h2><?= htmlspecialchars($t['smart_diagnostics']) ?></h2>
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

<div id="phpinfoModal" class="modal">
    <span class="modal-close" onclick="closePhpInfo()">&times;</span>
    <div id="phpinfoContent" class="modal-content"></div>
</div>

<div id="graphModal" class="modal">
    <span class="modal-close" onclick="closeGraphModal()">&times;</span>
    <div class="modal-content graph-modal-content">
        <div class="graph-container">
            <div id="graphTitle" class="graph-title"></div>
            <div class="graph-canvas-container">
                <canvas id="graphChart"></canvas>
            </div>
        </div>
    </div>
</div>

