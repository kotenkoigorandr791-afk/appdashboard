<body>
    <?php
$bgFull = $theme === 'dark' ? 'background_dark.jpg' : 'background_light.jpg';
$stepmonitorRoot = dirname(__DIR__, 3);
$stepmonitorApk = $stepmonitorRoot . '/downloads/stepmonitor.apk';
$stepmonitorMetaFile = $stepmonitorRoot . '/downloads/stepmonitor.json';
$stepmonitorMeta = is_file($stepmonitorMetaFile) ? (json_decode((string)file_get_contents($stepmonitorMetaFile), true) ?: []) : [];
$stepmonitorVersion = (string)($stepmonitorMeta['hash'] ?? (is_file($stepmonitorApk) ? filemtime($stepmonitorApk) : ''));
$stepmonitorAvailable = is_file($stepmonitorApk);
$stepmonitorShow = $stepmonitorAvailable && (empty($userSettings['mobile_apk_hidden']) || (string)($userSettings['mobile_apk_seen_version'] ?? '') !== $stepmonitorVersion);
$stepmonitorServerIp = (string)($_SERVER['SERVER_ADDR'] ?? '');
if ($stepmonitorServerIp === '' || str_starts_with($stepmonitorServerIp, '127.') || $stepmonitorServerIp === '::1') {
    $stepmonitorHostIps = function_exists('gethostbynamel') ? (gethostbynamel(gethostname()) ?: []) : [];
    foreach ($stepmonitorHostIps as $candidateIp) {
        $candidateIp = (string)$candidateIp;
        if (str_starts_with($candidateIp, '192.168.') || str_starts_with($candidateIp, '10.') || preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $candidateIp)) {
            $stepmonitorServerIp = $candidateIp;
            break;
        }
    }
    foreach ($stepmonitorHostIps as $candidateIp) {
        if ($stepmonitorServerIp === '' && !str_starts_with((string)$candidateIp, '127.')) {
            $stepmonitorServerIp = (string)$candidateIp;
            break;
        }
    }
}
$stepmonitorFallbackLanIp = '192.168.2.158';
if ($stepmonitorServerIp === '' || str_starts_with($stepmonitorServerIp, '127.') || str_starts_with($stepmonitorServerIp, '172.17.') || str_starts_with($stepmonitorServerIp, '172.18.')) {
    $stepmonitorServerIp = $stepmonitorFallbackLanIp;
}
$stepmonitorScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$stepmonitorPort = (string)($_SERVER['SERVER_PORT'] ?? '');
$stepmonitorPortPart = ($stepmonitorPort !== '' && !in_array($stepmonitorPort, ['80', '443'], true)) ? ':' . $stepmonitorPort : '';
$stepmonitorLanEndpoint = $stepmonitorServerIp !== '' ? $stepmonitorScheme . '://' . $stepmonitorServerIp . $stepmonitorPortPart . '/api/steps' : '';
$stepmonitorDevices = [];
if (!empty($_SESSION['user']['email']) && function_exists('app_main_db')) {
    try {
        $stepmonitorDb = app_main_db();
        if (function_exists('ensureClientAuthTokensSchema')) {
            ensureClientAuthTokensSchema($stepmonitorDb);
        }
        $stepmonitorStmt = $stepmonitorDb->prepare("SELECT device_name, platform, created_at, last_used_at FROM client_auth_tokens WHERE email = :email AND revoked = 0 AND expires_at > :now ORDER BY last_used_at DESC LIMIT 10");
        $stepmonitorStmt->bindValue(':email', (string)$_SESSION['user']['email'], PDO::PARAM_STR);
        $stepmonitorStmt->bindValue(':now', time(), PDO::PARAM_INT);
        $stepmonitorStmt->execute();
        $stepmonitorDevices = $stepmonitorStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $ignored) {
        $stepmonitorDevices = [];
    }
}
?>
    <div id="bg-layer" aria-hidden="true"></div>
    <script>(function(){var s=<?php echo json_encode($bgFull); ?>,el=document.getElementById('bg-layer'),im=new Image(),apply=function(){el.style.backgroundImage='url('+s+')';el.classList.add('ready')};im.decoding='async';im.onload=apply;im.src=s;if(im.complete)apply()})();</script>
    <div class="app-steps">
        <div class="app-topbar">
            <h1><?php echo htmlspecialchars($translations['steps_counter_title']); ?></h1>
            <div class="app-topbar-actions">
                <button id="add-steps-button" title="<?php echo htmlspecialchars($translations['add_action_tooltip'] ?? $translations['add_steps_tooltip']); ?>">
                    <i class="fas fa-plus"></i>
                </button>
                <?php if ($stepmonitorAvailable && $stepmonitorShow): ?><button id="stepmonitor-apk-button" type="button" title="<?php echo htmlspecialchars($translations['mobile_app_download'] ?? 'Download mobile app'); ?>" aria-label="<?php echo htmlspecialchars($translations['mobile_app_download'] ?? 'Download mobile app'); ?>"><i class="fas fa-mobile-screen-button"></i></button><?php endif; ?>
                <?php if (!empty($stepsData)): ?>
                <button id="stats-button" title="<?php echo htmlspecialchars($translations['statistics_navigation'] ?? 'Statistics'); ?>" aria-label="<?php echo htmlspecialchars($translations['statistics_navigation'] ?? 'Statistics'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chart-pie-icon lucide-chart-pie" aria-hidden="true" focusable="false">
                        <path d="M21 12c.552 0 1.005-.449.95-.998a10 10 0 0 0-8.953-8.951c-.55-.055-.998.398-.998.95v8a1 1 0 0 0 1 1z"/><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>
                    </svg>
                </button>
                <?php endif; ?>
                <button id="settings-button" title="<?php echo htmlspecialchars($translations['settings_tooltip']); ?>">
                    <i class="fas fa-cog"></i>
                </button>
                <button id="info-button" title="<?php echo htmlspecialchars($translations['app_info'] ?? 'App Information'); ?>">
                    <i class="fas fa-info-circle"></i>
                </button>
                <?php if (isset($_SESSION['user']) && !empty($_SESSION['user']['email'])): ?>
                <button id="hc-token-open-btn" type="button" class="steps-header-btn" title="<?php echo htmlspecialchars($translations['mcp_setup_title'] ?? 'MCP token'); ?>" aria-label="<?php echo htmlspecialchars($translations['mcp_setup_title'] ?? 'MCP token'); ?>">
                    <i class="fas fa-key"></i>
                </button>
                <?php endif; ?>
                <div id="user-button" title="<?php echo htmlspecialchars($userName); ?>">
                    <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="User">
                </div>
            </div>
        </div>
        <div class="app-main">
        <div class="view-selector">
            <button class="view-btn active" data-view="year"><?php echo htmlspecialchars($translations['year']); ?></button>
            <button class="view-btn" data-view="month"><?php echo htmlspecialchars($translations['month']); ?></button>
            <button class="view-btn" data-view="week"><?php echo htmlspecialchars($translations['week']); ?></button>
        </div>
        
        <div class="data-type-selector">
    <button class="data-type-btn active" data-type="steps">
        <img src="steps.webp" alt="<?php echo htmlspecialchars($translations['steps']); ?>">
    </button>
    <button class="data-type-btn" data-type="distance">
        <img src="distance.webp" alt="<?php echo htmlspecialchars($translations['distance_km']); ?>">
    </button>
    <button class="data-type-btn" data-type="calories">
        <img src="kkal.webp" alt="<?php echo htmlspecialchars($translations['calories']); ?>">
    </button>
    <button class="data-type-btn" data-type="heart_rate" title="<?php echo htmlspecialchars($translations['heart_rate'] ?? 'Heart rate'); ?>">
        <i class="fas fa-heartbeat"></i>
    </button>
    <button class="data-type-btn" data-type="sleep" title="<?php echo htmlspecialchars($translations['sleep'] ?? 'Sleep'); ?>">
        <i class="fas fa-moon"></i>
    </button>
    <button class="data-type-btn" data-type="weight" title="<?php echo htmlspecialchars($translations['weight'] ?? 'Weight'); ?>">
        <i class="fas fa-weight-hanging"></i>
    </button>
</div>
        
        <div class="chart-container">
            <button type="button" class="chart-nav chart-nav-left" id="chart-nav-left" style="display:none;" aria-label="<?php echo htmlspecialchars($translations['chart_prev'] ?? 'Previous'); ?>">
                <i class="fas fa-chevron-left"></i>
            </button>
            <div class="chart-main">
                <div class="chart-label" id="chart-label"></div>
                <div class="chart-wrapper">
                    <canvas id="steps-chart"></canvas>
                </div>
            </div>
            <button type="button" class="chart-nav chart-nav-right" id="chart-nav-right" style="display:none;" aria-label="<?php echo htmlspecialchars($translations['chart_next'] ?? 'Next'); ?>">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        
        <div class="calendar-container">
            <div class="calendar-header">
                <div class="calendar-selector-box">
                    <select id="year-selector"></select>
                    <select id="month-selector" disabled><option value="-">—</option></select>
                </div>
            </div>
            <div id="calendar"></div>
            <div class="goal-summary" id="goal-summary"></div>
        </div>
        
        <div id="notifications">
            <div id="stats-notification" class="notification" style="display:none;">
                <div class="notification-header">
                    <div class="notification-title">
                        <i class="fas fa-chart-line"></i> <?php echo htmlspecialchars($translations['max_steps']); ?>
                    </div>
                    <button class="close-notification">×</button>
                </div>
                <div class="notification-content" id="stats-content"></div>
            </div>
            <div id="goal-notification" class="notification" style="display:none;">
                <div class="notification-header">
                    <div class="notification-title">
                        <i class="fas fa-trophy"></i> <?php echo htmlspecialchars($translations['weekly_goal']); ?>
                    </div>
                    <button class="close-notification">×</button>
                </div>
                <div class="notification-content" id="goal-content"></div>
            </div>
            <div id="video-recognition-notification" class="notification video-related-notif" style="display:none;">
                <div class="notification-header">
                    <div class="notification-title" id="video-recognition-notif-title">
                        <i class="fas fa-file-archive"></i> <?php echo htmlspecialchars($translations['zeep_notif_title'] ?? 'Zeep Life import'); ?>
                    </div>
                    <button type="button" class="close-notification" id="video-recognition-notif-close" title="<?php echo htmlspecialchars($translations['video_notif_snooze_hint'] ?? 'Hide for 12 hours'); ?>" aria-label="<?php echo htmlspecialchars($translations['video_notif_snooze_hint'] ?? 'Close'); ?>">×</button>
                </div>
                <div class="notification-content" id="video-recognition-notif-body"><?php echo htmlspecialchars($translations['zeep_notif_body'] ?? ''); ?></div>
                <div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end;">
                    <button type="button" class="excel-btn" id="video-recognition-notif-open" style="background: var(--primary-color); color: #fff; padding: 8px 14px; font-size: 13px;"><?php echo htmlspecialchars($translations['zeep_notif_open'] ?? 'Open'); ?></button>
                </div>
            </div>
        </div>
        <div id="worker-progress-widget"></div>
        </div>
        <div id="stats-modal" style="display:none;">
            <div class="stats-content">
                <button class="modal-close">×</button>
                <h2><i class="fas fa-chart-pie"></i> <?php echo htmlspecialchars($translations['statistics_title'] ?? 'Statistics'); ?></h2>
                <div class="stats-grid">
                    <section class="stats-card">
                        <h3><?php echo htmlspecialchars($translations['statistics_yearly_distribution'] ?? 'Yearly Step Share'); ?></h3>
                        <div class="stats-chart-wrap">
                            <canvas id="stats-years-chart"></canvas>
                        </div>
                    </section>
                    <section class="stats-card">
                        <h3 id="stats-monthly-title"><?php echo htmlspecialchars($translations['statistics_monthly_intensity'] ?? 'Monthly Step Intensity'); ?></h3>
                        <div class="stats-chart-wrap">
                            <canvas id="stats-months-chart"></canvas>
                        </div>
                    </section>
                </div>
                <p id="stats-empty" style="display:none;"><?php echo htmlspecialchars($translations['statistics_no_data'] ?? 'No step data'); ?></p>
            </div>
        </div>
        <!-- Info Modal -->
        <div id="info-modal" style="display:none;">
            <div class="info-content">
                <button class="modal-close">×</button>
                <div class="info-header">
                    <div class="info-header-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                    </div>
                    <div>
                        <h2><?php echo htmlspecialchars($translations['app_info'] ?? 'Application Information'); ?></h2>
                    </div>
                </div>
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-card-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/>
                                <line x1="4" y1="22" x2="4" y2="15"/>
                            </svg>
                        </div>
<h3><?php echo htmlspecialchars($translations['info_card_tracking_title'] ?? 'Activity Tracking'); ?></h3>
                        <p><?php echo htmlspecialchars($translations['info_card_tracking_text'] ?? 'Log your daily steps, distance walked, and calories burned with detailed statistics.'); ?></p>
                    </div>
                    <div class="info-card">
                        <div class="info-card-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="20" x2="18" y2="10"></line>
                                <line x1="12" y1="20" x2="12" y2="4"></line>
                                <line x1="6" y1="20" x2="6" y2="14"></line>
                            </svg>
                        </div>
                        <h3><?php echo htmlspecialchars($translations['info_card_charts_title'] ?? 'Data Visualization'); ?></h3>
                        <p><?php echo htmlspecialchars($translations['info_card_charts_text'] ?? 'Visualize trends with interactive bar and line charts across different time periods.'); ?></p>
                    </div>
                    <div class="info-card">
                        <div class="info-card-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                        </div>
                        <h3><?php echo htmlspecialchars($translations['info_card_calendar_title'] ?? 'Goal Calendar'); ?></h3>
                        <p><?php echo htmlspecialchars($translations['info_card_calendar_text'] ?? 'Track your progress with calendar view and visually see goal achievement indicators.'); ?></p>
                    </div>
                    <div class="info-card">
                        <div class="info-card-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                <polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                        </div>
                        <h3><?php echo htmlspecialchars($translations['info_card_views_title'] ?? 'Flexible Layouts'); ?></h3>
                        <p><?php echo htmlspecialchars($translations['info_card_views_text'] ?? 'Switch between Year, Month, and Week views to analyze your data from different perspectives.'); ?></p>
                    </div>
                    <div class="info-card">
                        <div class="info-card-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <h3><?php echo htmlspecialchars($translations['info_card_metrics_title'] ?? 'Metric Options'); ?></h3>
                        <p><?php echo htmlspecialchars($translations['info_card_metrics_text'] ?? 'Select between Steps, Distance, and Calories to focus on the metrics that matter for your goals.'); ?></p>
                    </div>
                    <div class="info-card">
                        <div class="info-card-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                        </div>
                        <h3><?php echo htmlspecialchars($translations['info_card_backup_title'] ?? 'Backup & Restore'); ?></h3>
                        <p><?php echo htmlspecialchars($translations['info_card_backup_text'] ?? 'Keep your data safe with automatic backups and the ability to restore to any previous point.'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Settings Modal -->
        <div id="settings-modal" style="display:none;">
            <div class="settings-content">
                <button class="modal-close">×</button>
                <h2><i class="fas fa-cog"></i> <?php echo htmlspecialchars($translations['settings_title']); ?></h2>
                <div class="settings-tab-bar" role="tablist">
                    <button type="button" class="settings-tab active" data-settings-tab="profile" role="tab" aria-selected="true">
                        <i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($translations['settings_tab_profile'] ?? $translations['settings_title']); ?>
                    </button>
                    <button type="button" class="settings-tab" data-settings-tab="chart" role="tab" aria-selected="false">
                        <i class="fas fa-chart-area"></i> <?php echo htmlspecialchars($translations['settings_tab_chart'] ?? 'Chart'); ?>
                    </button>
                    <button type="button" class="settings-tab" data-settings-tab="backups" role="tab" aria-selected="false">
                        <i class="fas fa-database"></i> <?php echo htmlspecialchars($translations['manage_backups'] ?? 'Manage Backups'); ?>
                    </button>
                </div>
                <div id="settings-panel-profile" class="settings-tab-panel active" role="tabpanel">
                    <form id="settings-form" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="settings" value="1">
                        <div class="settings-tile">
                            <div class="tile-title"><?php echo htmlspecialchars($translations['goal_steps']); ?></div>
                            <hr>
                            <div class="tile-content">
                                <span class="tile-icon"><i class="fas fa-walking"></i></span>
                                <input type="text" name="goal_steps" value="<?php echo $userSettings['goal_steps'] / 1000; ?>" pattern="[0-9.,]*" inputmode="decimal">
                                <span><?php echo htmlspecialchars($translations['thousands'] ?? ''); ?></span>
                            </div>
                        </div>
                        <div class="settings-tile">
                            <div class="tile-title"><?php echo htmlspecialchars($translations['height']); ?></div>
                            <hr>
                            <div class="tile-content">
                                <span class="tile-icon"><i class="fas fa-ruler-vertical"></i></span>
                                <input type="text" name="height" value="<?php echo $userSettings['height']; ?>" pattern="\d*" inputmode="numeric">
                                <span>cm</span>
                            </div>
                        </div>
                        <div class="settings-tile">
                            <div class="tile-title"><?php echo htmlspecialchars($translations['weight']); ?></div>
                            <hr>
                            <div class="tile-content">
                                <span class="tile-icon"><i class="fas fa-weight"></i></span>
                                <input type="text" name="weight" value="<?php echo $userSettings['weight']; ?>" pattern="\d*" inputmode="numeric">
                                <span>kg</span>
                            </div>
                        </div>
                        <div class="settings-tile">
                            <div class="tile-title"><?php echo htmlspecialchars($translations['stride_length']); ?></div>
                            <hr>
                            <div class="tile-content">
                                <span class="tile-icon"><i class="fas fa-ruler"></i></span>
                                <input type="text" name="stride_length" value="<?php echo $userSettings['stride_length']; ?>" pattern="\d*" inputmode="numeric">
                                <span>cm</span>
                            </div>
                        </div>
                        <div class="settings-tile">
                            <div class="tile-title"><?php echo htmlspecialchars($translations['age']); ?> (<?php echo $userSettings['age']; ?>)</div>
                            <hr>
                            <div class="tile-content">
                                <span class="tile-icon"><i class="fas fa-birthday-cake"></i></span>
                                <input type="date" name="birth_date" value="<?php echo $userSettings['birth_date']; ?>">
                                <span></span>
                            </div>
                        </div>
                        <div class="settings-tile">
                            <div class="tile-title"><?php echo htmlspecialchars($translations['mobile_app_button'] ?? 'Mobile app button'); ?></div>
                            <hr>
                            <div class="tile-content">
                                <span class="tile-icon"><i class="fas fa-mobile-screen-button"></i></span>
                                <label style="display:flex;gap:8px;align-items:center;">
                                    <input type="hidden" name="mobile_apk_hidden" value="1">
                                    <input type="checkbox" name="mobile_apk_hidden" value="0" <?php echo empty($userSettings['mobile_apk_hidden']) ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($translations['mobile_app_button_show'] ?? 'Show download button'); ?></span>
                                </label>
                            </div>
                        </div>
                        <div class="save-button-container">
                            <button type="submit" class="save-button">
                                <i class="fas fa-save"></i> <?php echo htmlspecialchars($translations['save']); ?>
                            </button>
                        </div>
                    </form>
                </div>
                <div id="settings-panel-chart" class="settings-tab-panel" role="tabpanel">
                    <p class="settings-chart-type-intro"><?php echo htmlspecialchars($translations['settings_chart_type_hint'] ?? 'Choose how the main chart is drawn.'); ?></p>
                    <div class="settings-chart-preview-grid">
                        <div class="settings-chart-preview-item">
                            <button type="button" class="chart-type-btn active" data-type="bar">
                                <i class="fas fa-chart-bar"></i>
                                <span><?php echo htmlspecialchars($translations['bar_chart'] ?? 'Bar Chart'); ?></span>
                            </button>
                            <div class="settings-chart-preview-box">
                                <canvas id="settings-chart-preview-bar"></canvas>
                            </div>
                        </div>
                        <div class="settings-chart-preview-item">
                            <button type="button" class="chart-type-btn" data-type="line">
                                <i class="fas fa-chart-line"></i>
                                <span><?php echo htmlspecialchars($translations['linear_chart'] ?? 'Linear Chart'); ?></span>
                            </button>
                            <div class="settings-chart-preview-box">
                                <canvas id="settings-chart-preview-line"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="settings-panel-backups" class="settings-tab-panel" role="tabpanel">
                    <div class="excel-actions settings-backup-actions">
                        <button class="excel-btn create-backup" id="create-backup" type="button" style="background: #17a2b8;">
                            <i class="fas fa-plus-circle"></i> <?php echo htmlspecialchars($translations['create_backup'] ?? 'Create Backup'); ?>
                        </button>
                        <button id="refresh-backups" class="excel-btn" type="button">
                            <i class="fas fa-sync"></i> <?php echo htmlspecialchars($translations['refresh_backups'] ?? 'Refresh List'); ?>
                        </button>
                        <button class="excel-btn download" id="download-backup" type="button">
                            <i class="fas fa-download"></i> <?php echo htmlspecialchars($translations['download_backup'] ?? 'Download Backup'); ?>
                        </button>
                    </div>
                    <div class="backups-list" id="backups-list"></div>
                    <div class="settings-backup-import">
                        <h3><i class="fas fa-file-import"></i> <?php echo htmlspecialchars($translations['import_external_backup'] ?? 'Import CSV backup'); ?></h3>
                        <div class="settings-backup-import-grid">
                            <div>
                                <label for="external-backup-file"><?php echo htmlspecialchars($translations['external_backup_file'] ?? 'CSV backup file'); ?></label>
                                <input type="file" id="external-backup-file" accept=".csv,text/csv">
                            </div>
                            <div class="settings-backup-import-mode">
                                <span><?php echo htmlspecialchars($translations['import_mode'] ?? 'Import mode'); ?>:</span>
                                <label><input type="radio" name="external-import-mode" value="merge" checked> <?php echo htmlspecialchars($translations['import_mode_merge'] ?? 'Merge (keep existing)'); ?></label>
                                <label><input type="radio" name="external-import-mode" value="replace"> <?php echo htmlspecialchars($translations['import_mode_replace'] ?? 'Replace all data'); ?></label>
                            </div>
                            <button id="import-external-backup-btn" class="excel-btn" type="button" style="background: var(--primary-color); color: #fff;">
                                <i class="fas fa-file-import"></i> <?php echo htmlspecialchars($translations['import_external_btn'] ?? 'Import'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="add-steps-modal" style="display:none;">
            <div class="add-steps-content add-steps-merged">
                <button class="modal-close" type="button" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>">×</button>
                <h2 class="add-steps-main-title"><i class="fas fa-plus"></i> <?php echo htmlspecialchars($translations['add_action_tooltip'] ?? $translations['add_steps']); ?></h2>
                <div class="add-tab-bar" role="tablist">
                    <button type="button" class="add-tab active" data-add-tab="manual" role="tab" aria-selected="true">
                        <i class="fas fa-keyboard"></i> <?php echo htmlspecialchars($translations['add_tab_manual'] ?? 'Manual'); ?>
                    </button>
                    <button type="button" class="add-tab" data-add-tab="video" role="tab" aria-selected="false">
                        <i class="fas fa-file-archive"></i> <?php echo htmlspecialchars($translations['add_tab_video'] ?? 'Zeep Life'); ?>
                    </button>
                    <button type="button" class="add-tab" data-add-tab="takeout" role="tab" aria-selected="false">
                        <i class="fas fa-file-archive"></i> <?php echo htmlspecialchars($translations['add_tab_fit'] ?? 'Takeout'); ?>
                    </button>
                </div>
                <div id="add-panel-manual" class="add-tab-panel active" role="tabpanel">
                    <div class="adjacent-dates" id="prev-dates"></div>
                    <div class="current-date">
                        <input type="date" id="selected-date-input">
                        <input type="text" id="steps-input" inputmode="numeric" placeholder="<?php echo htmlspecialchars($translations['add_steps']); ?>" autocomplete="one-time-code" autocorrect="off" spellcheck="false" name="steps_count" data-lpignore="true" data-1p-ignore data-form-type="other" readonly onfocus="this.removeAttribute('readonly')">
                    </div>
                    <div class="adjacent-dates" id="next-dates"></div>
                    <form id="add-steps-form" method="post" style="display:none;">
                        <input type="hidden" name="date" id="selected-date">
                        <input type="hidden" name="steps" id="selected-steps">
                        <input type="hidden" name="add_steps" value="1">
                    </form>
                </div>
                <div id="add-panel-video" class="add-tab-panel" role="tabpanel">
                    <div class="video-analysis-content">
                        <div class="va-head">
                            <h2><i class="fas fa-file-archive"></i> <?php echo htmlspecialchars($translations['zeep_analysis'] ?? 'Zeep Life'); ?></h2>
                            <p class="va-sub"><?php echo htmlspecialchars($translations['zeep_hint'] ?? ''); ?></p>
                        </div>
                        <div class="video-inline-scroll">
                            <div class="va-grid">
                                <div class="va-steps" id="zl-steps">
                                    <div class="va-progress"><div class="va-progress-fill"></div></div>
                                    <div class="va-steps-row">
                                        <button type="button" class="va-step-dot is-active" data-step-target="1">1</button>
                                        <span class="va-step-line"></span>
                                        <button type="button" class="va-step-dot" data-step-target="2" disabled>2</button>
                                        <span class="va-step-line"></span>
                                        <button type="button" class="va-step-dot" data-step-target="3" disabled>3</button>
                                    </div>
                                </div>
                                <section class="va-segment" data-step-section="1">
                                    <div class="va-segment-h"><i class="fas fa-file-zipper"></i> <?php echo htmlspecialchars($translations['zeep_zip_section'] ?? 'ZIP archive'); ?></div>
                                    <div class="va-field">
                                        <label for="zl-zip"><?php echo htmlspecialchars($translations['zeep_zip_file_label'] ?? 'Archive file'); ?> (.zip)</label>
                                        <input type="file" id="zl-zip" accept=".zip,application/zip" data-wizard-keep-enabled="1">
                                    </div>
                                    <div class="va-field">
                                        <label for="zl-password"><?php echo htmlspecialchars($translations['zeep_password_label'] ?? 'Archive password'); ?></label>
                                        <div class="zl-password-wrap">
                                            <input type="password" id="zl-password" class="zl-secret-input" autocomplete="off" autocapitalize="off" spellcheck="false" inputmode="text" name="zeep_archive_password" data-wizard-keep-enabled="1" data-lpignore="true" data-1p-ignore data-form-type="other">
                                            <button type="button" class="zl-password-toggle" id="zl-password-toggle" aria-label="<?php echo htmlspecialchars($translations['zeep_password_show'] ?? 'Show password'); ?>" aria-pressed="false"><i class="fas fa-eye" aria-hidden="true"></i></button>
                                        </div>
                                    </div>
                                    <div class="video-btn-row">
                                        <button type="button" class="excel-btn" id="zl-next-step1" style="background:var(--primary-color);color:#fff;" disabled><?php echo htmlspecialchars($translations['zeep_continue'] ?? 'Continue'); ?></button>
                                    </div>
                                </section>
                                <section class="va-segment" data-step-section="2" style="display:none;">
                                    <div class="va-segment-h"><i class="fas fa-circle-check"></i> <?php echo htmlspecialchars($translations['zeep_ready_section'] ?? 'Ready'); ?></div>
                                    <p class="va-sub zeep-ready-hint"><?php echo htmlspecialchars($translations['zeep_ready_hint'] ?? ''); ?></p>
                                    <div class="video-btn-row">
                                        <button type="button" class="excel-btn" id="zl-next" style="background:var(--primary-color);color:#fff;"><?php echo htmlspecialchars($translations['zeep_continue'] ?? 'Continue'); ?></button>
                                    </div>
                                </section>
                                <section class="va-segment" data-step-section="3" style="display:none;">
                                    <div class="va-segment-h"><i class="fas fa-bolt"></i> <?php echo htmlspecialchars($translations['video_section_actions'] ?? 'Actions'); ?></div>
                                    <div class="video-btn-row">
                                        <button type="button" class="excel-btn" id="zl-start" style="background:var(--primary-color);color:#fff;" disabled><?php echo htmlspecialchars($translations['zeep_start'] ?? 'Import'); ?></button>
                                    </div>
                                </section>
                                <section class="va-segment" id="zl-job-panel" style="display:none;" aria-live="polite">
                                    <div class="va-segment-h"><i class="fas fa-chart-line"></i> <?php echo htmlspecialchars($translations['video_section_progress'] ?? 'Status'); ?></div>
                                    <p id="zl-msg">—</p>
                                    <div id="zl-progress-bar"><div id="zl-progress-fill"></div></div>
                                    <p id="zl-meta"></p>
                                    <div class="video-btn-row"><button type="button" class="excel-btn worker-stop-btn" id="zl-stop"><?php echo htmlspecialchars($translations['stop'] ?? 'Stop'); ?></button></div>
                                </section>
                                <section class="va-segment takeout-guide-segment is-collapsed" id="zeep-guide-segment" data-step-section="1">
                                    <div class="takeout-guide-head">
                                        <span><i class="fas fa-map-signs"></i> <?php echo htmlspecialchars($translations['zeep_guide_title'] ?? 'How to export Zeep Life data'); ?></span>
                                        <div class="takeout-guide-actions">
                                            <button type="button" class="takeout-guide-expand" id="zeep-guide-expand" aria-label="<?php echo htmlspecialchars($translations['expand'] ?? 'Expand'); ?>" title="<?php echo htmlspecialchars($translations['expand'] ?? 'Expand'); ?>"><i class="fas fa-expand"></i></button>
                                            <button type="button" class="takeout-guide-toggle" id="zeep-guide-toggle" aria-expanded="false" title="<?php echo htmlspecialchars($translations['show'] ?? 'Show'); ?>"><i class="fas fa-chevron-down"></i></button>
                                        </div>
                                    </div>
                                    <div class="takeout-guide-body">
                                        <iframe
                                            id="zeep-guide-frame"
                                            name="steps-zeep-guide"
                                            loading="lazy"
                                            title="<?php echo htmlspecialchars($translations['zeep_guide_iframe_title'] ?? 'Zeep Life instruction'); ?>"
                                            src="instruction/zeep_life_guide.html?lang=<?php echo urlencode($language); ?>&theme=<?php echo urlencode($theme); ?>&color=<?php echo urlencode($color); ?>"
                                        ></iframe>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="add-panel-takeout" class="add-tab-panel" role="tabpanel">
                    <div class="video-analysis-content">
                        <div class="va-head">
                            <h2><i class="fas fa-file-archive"></i> <?php echo htmlspecialchars($translations['add_tab_fit'] ?? 'Takeout'); ?></h2>
                            <p class="va-sub takeout-hint"><?php echo htmlspecialchars($translations['takeout_hint'] ?? ''); ?></p>
                        </div>
                        <div class="video-inline-scroll">
                            <div class="va-grid">
                                <div class="va-steps" id="ft-steps">
                                    <div class="va-progress"><div class="va-progress-fill"></div></div>
                                    <div class="va-steps-row">
                                        <button type="button" class="va-step-dot is-active" data-step-target="1">1</button>
                                        <span class="va-step-line"></span>
                                        <button type="button" class="va-step-dot" data-step-target="2" disabled>2</button>
                                        <span class="va-step-line"></span>
                                        <button type="button" class="va-step-dot" data-step-target="3" disabled>3</button>
                                    </div>
                                </div>
                                <section class="va-segment" data-step-section="1">
                                    <div class="va-segment-h"><i class="fas fa-file-zipper"></i> <?php echo htmlspecialchars($translations['takeout_zip_section'] ?? 'ZIP archive'); ?></div>
                                    <div class="va-field">
                                        <label for="ft-zip"><?php echo htmlspecialchars($translations['takeout_zip_file_label'] ?? 'Archive file'); ?> (.zip)</label>
                                        <input type="file" id="ft-zip" accept=".zip,application/zip">
                                    </div>
                                </section>
                                <section class="va-segment" data-step-section="2" style="display:none;">
                                    <div class="va-segment-h"><i class="fas fa-language"></i> <?php echo htmlspecialchars($translations['video_section_options'] ?? 'Recognition'); ?></div>
                                    <div class="va-field">
                                        <label for="ft-lang"><?php echo htmlspecialchars($translations['video_ocr_lang'] ?? 'OCR language'); ?></label>
                                        <select id="ft-lang">
                                            <option value="eng" <?php echo $stepsVideoOcrTess === 'eng' ? 'selected' : ''; ?>>English</option>
                                            <option value="rus" <?php echo $stepsVideoOcrTess === 'rus' ? 'selected' : ''; ?>>Русский</option>
                                            <option value="ukr" <?php echo $stepsVideoOcrTess === 'ukr' ? 'selected' : ''; ?>>Українська</option>
                                            <option value="bel" <?php echo $stepsVideoOcrTess === 'bel' ? 'selected' : ''; ?>>Беларуская</option>
                                        </select>
                                    </div>
                                    <div class="va-field">
                                        <label for="ft-year"><?php echo htmlspecialchars($translations['video_year_label'] ?? 'Year'); ?></label>
                                        <input type="text" id="ft-year" placeholder="-" value="-" autocomplete="off">
                                    </div>
                                </section>
                                <section class="va-segment" data-step-section="3" style="display:none;">
                                    <div class="va-segment-h"><i class="fas fa-bolt"></i> <?php echo htmlspecialchars($translations['video_section_actions'] ?? 'Actions'); ?></div>
                                    <div class="video-btn-row">
                                        <button type="button" class="excel-btn" id="ft-start" style="background:var(--primary-color);color:#fff;" disabled><?php echo htmlspecialchars($translations['takeout_start'] ?? 'Import'); ?></button>
                                    </div>
                                </section>
                                <section class="va-segment" id="ft-job-panel" style="display:none;" aria-live="polite">
                                    <div class="va-segment-h"><i class="fas fa-chart-line"></i> <?php echo htmlspecialchars($translations['video_section_progress'] ?? 'Status'); ?></div>
                                    <p id="ft-msg">—</p>
                                    <div id="ft-progress-bar"><div id="ft-progress-fill"></div></div>
                                    <p id="ft-meta"></p>
                                    <div class="video-btn-row"><button type="button" class="excel-btn worker-stop-btn" id="ft-stop"><?php echo htmlspecialchars($translations['stop'] ?? 'Stop'); ?></button></div>
                                </section>
                                <section class="va-segment takeout-guide-segment is-collapsed" id="takeout-guide-segment">
                                    <div class="takeout-guide-head">
                                        <span><i class="fas fa-map-signs"></i> <?php echo htmlspecialchars($translations['takeout_guide_title'] ?? 'How to create a Google Takeout archive'); ?></span>
                                        <div class="takeout-guide-actions">
                                            <button type="button" class="takeout-guide-expand" id="takeout-guide-expand" aria-label="<?php echo htmlspecialchars($translations['expand'] ?? 'Expand'); ?>" title="<?php echo htmlspecialchars($translations['expand'] ?? 'Expand'); ?>"><i class="fas fa-expand"></i></button>
                                            <button type="button" class="takeout-guide-toggle" id="takeout-guide-toggle" aria-expanded="false" title="<?php echo htmlspecialchars($translations['show'] ?? 'Show'); ?>"><i class="fas fa-chevron-down"></i></button>
                                        </div>
                                    </div>
                                    <div class="takeout-guide-body">
                                        <iframe
                                            id="takeout-guide-frame"
                                            name="steps-takeout-guide"
                                            loading="lazy"
                                            title="<?php echo htmlspecialchars($translations['takeout_guide_iframe_title'] ?? 'Takeout instruction'); ?>"
                                            src="instruction/takeout_guide.html?lang=<?php echo urlencode($language); ?>&theme=<?php echo urlencode($theme); ?>&color=<?php echo urlencode($color); ?>"
                                        ></iframe>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="guide-expand-modal" style="display:none;">
            <div class="guide-expand-content">
                <button type="button" class="modal-close" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>">×</button>
                <h2 class="guide-expand-title"><i class="fas fa-map-signs"></i> <span id="guide-expand-title-text"></span></h2>
                <div class="guide-expand-frame-wrap">
                    <iframe id="guide-expand-frame" name="steps-guide-expand" loading="lazy"></iframe>
                </div>
            </div>
        </div>
        <div id="video-crop-modal" style="display:none;">
            <div class="video-crop-inner" style="text-align:center;">
                <h3 style="margin-top:0;"><?php echo htmlspecialchars($translations['video_crop_btn'] ?? 'Crop frame'); ?></h3>
                <div style="max-height:70vh;overflow:hidden;"><img id="video-crop-img" src="" alt=""></div>
                <div class="video-crop-actions">
                    <button type="button" class="excel-btn" id="va-crop-ok" style="background:var(--primary-color);color:#fff;">OK</button>
                    <button type="button" class="excel-btn" id="va-crop-cancel" style="background:#6c757d;color:#fff;"><?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?></button>
                </div>
            </div>
        </div>
        
        <!-- Alert Modal -->
        <div id="alert-modal" style="display:none;">
            <div class="alert-content">
                <button class="modal-close">×</button>
                <div class="alert-message" id="alert-message"></div>
                <div class="alert-buttons">
                    <button class="alert-btn ok" id="alert-ok">OK</button>
                    <button class="alert-btn extra" id="alert-extra" style="display:none;"></button>
                    <button class="alert-btn cancel" id="alert-cancel" style="display:none;"><?php echo htmlspecialchars($translations['cancel'] ?? 'Cancel'); ?></button>
                </div>
            </div>
        </div>
        <div id="video-confirm-modal" style="display:none;">
            <div class="video-confirm-inner excel-content">
                <button class="modal-close" type="button" id="video-confirm-close-x">×</button>
                <div style="display:flex;align-items:center;justify-content:flex-start;gap:10px;flex-wrap:wrap;margin-bottom:4px;max-width:100%;padding-right:48px;box-sizing:border-box;">
                    <button type="button" id="video-confirm-gaps-btn" class="excel-btn" style="display:none;flex-shrink:0;padding:8px 12px;background:#ffc107;color:#212529;align-self:flex-start;" title="<?php echo htmlspecialchars($translations['video_gaps_title'] ?? 'Missing days in range'); ?>" aria-label="<?php echo htmlspecialchars($translations['video_gaps_title'] ?? 'Gaps'); ?>"><i class="fas fa-exclamation-triangle"></i></button>
                    <h2 id="video-confirm-title" style="margin:0;flex:1;min-width:0;"><i class="fas fa-clipboard-list"></i> <?php echo htmlspecialchars($translations['video_confirm_title'] ?? 'Recognized steps'); ?></h2>
                </div>
                <p id="video-confirm-sub" style="text-align:left;font-size:14px;opacity:0.9;margin-top:0;"></p>
                <p id="video-confirm-empty-hint" style="display:none;text-align:left;font-size:14px;opacity:0.9;"></p>
                <div id="video-confirm-ocr-wrap" style="display:none;margin-top:8px;width:100%;max-width:100%;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin:8px 0 6px;">
                        <div id="video-confirm-ocr-title" style="font-size:15px;font-weight:600;text-align:left;margin:0;flex:1;min-width:0;"></div>
                        <button type="button" id="video-confirm-ocr-log-btn" class="excel-btn" style="display:none;flex-shrink:0;padding:6px 12px;font-size:13px;background:var(--border-color);color:var(--text-color);" aria-expanded="false"><?php echo htmlspecialchars($translations['video_ocr_open_log'] ?? 'Open log'); ?></button>
                    </div>
                    <div id="video-confirm-ocr-body" style="display:none;">
                        <div id="video-confirm-ocr" class="excel-table-container" style="max-height:32vh;overflow:auto;font-family:system-ui,sans-serif;font-size:12px;line-height:1.35;white-space:pre-wrap;word-break:break-word;text-align:left;padding:10px;"></div>
                    </div>
                </div>
                <section class="takeout-guide-segment is-collapsed" id="video-confirm-phone-guide-segment">
                    <div class="takeout-guide-head">
                        <span><i class="fas fa-mobile-screen"></i> <?php echo htmlspecialchars($translations['video_phone_guide_title'] ?? 'Prepare steps chart on phone'); ?></span>
                        <button type="button" class="takeout-guide-toggle" id="video-confirm-phone-guide-toggle" aria-expanded="false" title="<?php echo htmlspecialchars($translations['show'] ?? 'Show'); ?>"><i class="fas fa-chevron-down"></i></button>
                    </div>
                    <div class="takeout-guide-body">
                        <iframe
                            id="video-confirm-phone-guide-frame"
                            name="steps-phone-guide"
                            title="<?php echo htmlspecialchars($translations['video_phone_guide_iframe_title'] ?? 'Phone steps instruction'); ?>"
                            src="instruction/video_phone_steps_guide.html?lang=<?php echo urlencode($language); ?>&theme=<?php echo urlencode($theme); ?>&color=<?php echo urlencode($color); ?>"
                        ></iframe>
                    </div>
                </section>
                <div id="video-confirm-chart-wrap" style="width:100%;height:280px;max-width:100%;margin:8px 0 12px;display:none;position:relative;">
                    <canvas id="video-confirm-chart"></canvas>
                </div>
                <div class="excel-table-container" id="video-confirm-table-wrap" style="max-height:45vh;overflow:auto;"></div>
                <div style="margin-top:16px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                    <button type="button" class="excel-btn" id="video-confirm-import" style="background:var(--primary-color);color:#fff;"><?php echo htmlspecialchars($translations['video_add_to_app'] ?? 'Add to app'); ?></button>
                    <button type="button" class="excel-btn" id="video-confirm-skip" style="background:#6c757d;color:#fff;"><?php echo htmlspecialchars($translations['video_discard'] ?? 'Do not add'); ?></button>
                </div>
            </div>
        </div>
        <div id="video-confirm-gaps-modal" style="display:none;">
            <div class="video-confirm-gaps-inner excel-content" style="max-width:520px;">
                <button class="modal-close" type="button" id="video-confirm-gaps-close-x">×</button>
                <h2 style="margin-top:0;"><i class="fas fa-calendar-day"></i> <span id="video-confirm-gaps-heading"><?php echo htmlspecialchars($translations['video_gaps_title'] ?? 'Missing days'); ?></span></h2>
                <p id="video-confirm-gaps-sub" style="text-align:left;font-size:14px;opacity:0.9;"><?php echo htmlspecialchars($translations['video_gaps_sub'] ?? 'Days between the first and last recognized date with no data. Enter steps, then apply.'); ?></p>
                <div id="video-confirm-gaps-body" class="excel-table-container" style="max-height:50vh;overflow:auto;"></div>
                <div style="margin-top:16px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                    <button type="button" class="excel-btn" id="video-confirm-gaps-apply" style="background:var(--primary-color);color:#fff;"><?php echo htmlspecialchars($translations['video_gaps_apply'] ?? 'Add to list'); ?></button>
                </div>
            </div>
        </div>
        <div id="global-image-viewer" aria-hidden="true">
            <button type="button" id="global-image-viewer-close" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>">×</button>
            <img id="global-image-viewer-img" src="" alt="">
        </div>

        <div id="hc-token-modal" style="display:none;position:fixed;inset:0;z-index:10060;background:rgba(6,10,16,.32);backdrop-filter:blur(20px) saturate(150%);-webkit-backdrop-filter:blur(20px) saturate(150%);align-items:center;justify-content:center;padding:16px;">
            <div style="width:min(640px,96vw);max-height:86vh;overflow:auto;background:var(--modal-bg);color:var(--text-color);border:1px solid var(--border-color);border-radius:16px;box-shadow:var(--glass-shadow,0 20px 50px rgba(0,0,0,.25));padding:18px;position:relative;">
                <button type="button" id="hc-token-close" aria-label="Close" style="position:absolute;top:10px;right:12px;background:none;border:none;color:var(--text-color);font-size:22px;cursor:pointer;line-height:1;">×</button>
                <h2 style="margin:0 0 8px;font-size:20px;">Health Connect authorization</h2>
                <p style="margin:0 0 14px;opacity:.78;font-size:13px;line-height:1.45;">
                    Use the token below as <code style="background:rgba(0,0,0,.14);padding:2px 6px;border-radius:8px;">Authorization: Bearer &lt;token&gt;</code>.
                </p>

                <div style="display:grid;grid-template-columns:1fr;gap:10px;">
                    <div style="display:flex;gap:8px;align-items:stretch;flex-wrap:wrap;">
                        <div style="flex:1;min-width:260px;">
                            <label style="display:block;font-size:12px;font-weight:700;opacity:.7;margin:0 0 6px;text-transform:uppercase;letter-spacing:.04em;">Endpoint</label>
                            <pre id="hc-token-endpoint" style="margin:0;padding:10px 12px;border-radius:10px;border:1px solid var(--border-color);background:rgba(0,0,0,.16);word-break:break-all;white-space:pre-wrap;"></pre>
                        </div>
                        <button type="button" id="hc-token-copy-endpoint" style="align-self:flex-end;border:none;border-radius:10px;padding:10px 14px;cursor:pointer;background:var(--primary-color,#2563eb);color:#fff;font-weight:600;font-size:13px;">Copy</button>
                    </div>

                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <div style="flex:1;min-width:240px;">
                            <label style="display:block;font-size:12px;font-weight:700;opacity:.7;margin:0 0 6px;text-transform:uppercase;letter-spacing:.04em;">Token name</label>
                            <input id="hc-token-name" type="text" maxlength="80" value="Health Connect" style="width:100%;padding:10px 12px;border-radius:10px;border:1px solid var(--border-color);background:var(--input-bg,var(--modal-bg));color:var(--text-color);" />
                        </div>
                        <button type="button" id="hc-token-generate" style="border:none;border-radius:10px;padding:12px 16px;cursor:pointer;background:var(--primary-color,#2563eb);color:#fff;font-weight:700;font-size:13px;">Generate</button>
                    </div>

                    <p id="hc-token-err" style="display:none;margin:0;color:#ef4444;font-size:13px;"></p>

                    <?php if (!empty($stepmonitorDevices)): ?>
                    <div style="padding:12px;border:1px solid var(--border-color);border-radius:12px;background:rgba(0,0,0,.08);">
                        <strong style="display:block;margin-bottom:8px;font-size:13px;"><?php echo htmlspecialchars($translations['mobile_app_devices'] ?? 'Connected devices'); ?></strong>
                        <div style="display:grid;gap:6px;font-size:13px;">
                            <?php foreach ($stepmonitorDevices as $stepmonitorDevice): ?>
                            <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
                                <span><?php echo htmlspecialchars((string)($stepmonitorDevice['device_name'] ?: ($stepmonitorDevice['platform'] ?: 'StepMonitor'))); ?></span>
                                <span style="opacity:.7;"><?php echo !empty($stepmonitorDevice['last_used_at']) ? htmlspecialchars(date('Y-m-d H:i', (int)$stepmonitorDevice['last_used_at'])) : ''; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div id="hc-token-output" style="display:none;padding:12px;border:1px solid color-mix(in srgb,#22c55e 45%,transparent);border-radius:12px;background:color-mix(in srgb,#22c55e 16%,transparent);">
                        <strong style="display:block;margin-bottom:8px;font-size:13px;">Token</strong>
                        <code id="hc-token-value" style="display:block;word-break:break-all;font:12px/1.4 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;"></code>
                        <img id="hc-token-qr" alt="StepMonitor QR" style="display:none;width:220px;height:220px;margin:12px auto;border-radius:12px;background:#fff;padding:8px;">
                        <a id="hc-token-link" href="#" style="display:none;word-break:break-all;color:var(--primary-color);font-size:13px;"></a>
                        <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;margin-top:10px;">
                            <button type="button" id="hc-token-copy" style="border:none;border-radius:10px;padding:10px 14px;cursor:pointer;background:#16a34a;color:#fff;font-weight:700;font-size:13px;">Copy</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="stepmonitor-conflict-modal" style="display:none;position:fixed;inset:0;z-index:10070;background:rgba(6,10,16,.36);backdrop-filter:blur(18px);align-items:center;justify-content:center;padding:16px;">
            <div style="width:min(560px,96vw);background:var(--modal-bg);color:var(--text-color);border:1px solid var(--border-color);border-radius:16px;box-shadow:0 18px 48px rgba(0,0,0,.28);padding:18px;">
                <h2 style="margin:0 0 8px;font-size:20px;"><?php echo htmlspecialchars($translations['sync_conflict_title'] ?? 'Sync conflict'); ?></h2>
                <p id="stepmonitor-conflict-message" style="margin:0 0 14px;opacity:.82;line-height:1.45;"></p>
                <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;">
                    <button type="button" class="excel-btn" data-conflict-action="skip" style="background:#6c757d;color:#fff;"><?php echo htmlspecialchars($translations['sync_conflict_skip'] ?? 'Skip phone data'); ?></button>
                    <button type="button" class="excel-btn" data-conflict-action="replace" style="background:var(--primary-color);color:#fff;"><?php echo htmlspecialchars($translations['sync_conflict_replace'] ?? 'Replace server data'); ?></button>
                    <button type="button" class="excel-btn" data-conflict-action="add" style="background:#16a34a;color:#fff;"><?php echo htmlspecialchars($translations['sync_conflict_add'] ?? 'Add values'); ?></button>
                </div>
            </div>
        </div>
        <script>
        (function(){
            const modal=document.getElementById('stepmonitor-conflict-modal');
            const msg=document.getElementById('stepmonitor-conflict-message');
            if(!modal||!msg) return;
            let current=null;
            function show(conflict){
                current=conflict;
                const days=Array.isArray(conflict.days)?conflict.days:[];
                msg.textContent=<?php echo json_encode($translations['sync_conflict_message'] ?? 'Different step values were found for {count} day(s). Choose which data to keep.'); ?>.replace('{count}', String(days.length));
                modal.style.display='flex';
            }
            async function poll(){
                try{
                    const res=await fetch('/api/steps/conflicts',{credentials:'same-origin'});
                    const data=await res.json();
                    const conflict=data&&Array.isArray(data.conflicts)?data.conflicts[0]:null;
                    if(conflict&&!current) show(conflict);
                }catch(e){}
            }
            modal.querySelectorAll('[data-conflict-action]').forEach(btn=>{
                btn.addEventListener('click',async()=>{
                    if(!current) return;
                    const action=btn.getAttribute('data-conflict-action');
                    btn.disabled=true;
                    try{
                        await fetch(current.resolution_url||('/api/steps/conflicts/'+encodeURIComponent(current.id)),{
                            method:'POST',
                            credentials:'same-origin',
                            headers:{'Content-Type':'application/json'},
                            body:JSON.stringify({action})
                        });
                    }catch(e){}
                    current=null;
                    modal.style.display='none';
                    btn.disabled=false;
                });
            });
            poll();
            setInterval(poll,60000);
        })();
        </script>

        <script>
        (function(){
            const btn=document.getElementById('hc-token-open-btn');
            const modal=document.getElementById('hc-token-modal');
            const closeBtn=document.getElementById('hc-token-close');
            if(!btn||!modal||!closeBtn) return;
            const endpointEl=document.getElementById('hc-token-endpoint');
            const endpointCopyBtn=document.getElementById('hc-token-copy-endpoint');
            const nameEl=document.getElementById('hc-token-name');
            const genBtn=document.getElementById('hc-token-generate');
            const errEl=document.getElementById('hc-token-err');
            const outEl=document.getElementById('hc-token-output');
            const tokenValEl=document.getElementById('hc-token-value');
            const tokenQrEl=document.getElementById('hc-token-qr');
            const tokenLinkEl=document.getElementById('hc-token-link');
            const tokenCopyBtn=document.getElementById('hc-token-copy');

            const lanEndpoint=<?php echo json_encode($stepmonitorLanEndpoint); ?>;
            const currentHost=window.location.hostname;
            const endpoint=(lanEndpoint && (currentHost==='localhost'||currentHost==='127.0.0.1'||currentHost==='::1')) ? lanEndpoint : window.location.origin + '/api/steps';
            if(endpointEl) endpointEl.textContent=endpoint;

            function showModal(){
                modal.style.display='flex';
            }
            function hideModal(){
                modal.style.display='none';
            }

            btn.addEventListener('click', showModal);
            closeBtn.addEventListener('click', hideModal);
            modal.addEventListener('click', (e)=>{ if(e.target===modal) hideModal(); });

            function setErr(msg){
                if(!errEl) return;
                if(!msg){ errEl.style.display='none'; errEl.textContent=''; return; }
                errEl.textContent=msg;
                errEl.style.display='block';
            }

            async function copyText(text){
                try{ await navigator.clipboard.writeText(text); return true; }catch(e){ return false; }
            }

            endpointCopyBtn?.addEventListener('click', async ()=>{
                await copyText(endpoint);
            });

            tokenCopyBtn?.addEventListener('click', async ()=>{
                const t=tokenValEl?.textContent?.trim()||'';
                if(t) await copyText(t);
            });

            genBtn?.addEventListener('click', async ()=>{
                setErr('');
                if(genBtn) genBtn.disabled=true;
                try{
                    const fd=new FormData();
                    fd.append('action','generate_mcp_token');
                    fd.append('generate_mcp_token','1');
                    fd.append('ajax','1');
                    const nm=nameEl && nameEl.value ? String(nameEl.value).trim() : 'Health Connect';
                    fd.append('mcp_token_name', nm || 'Health Connect');

                    const res=await fetch('/main', { method:'POST', body:fd, credentials:'same-origin' });
                    const data=await res.json().catch(()=>null);
                    if(!data||!data.success||!data.token) throw new Error(data.error||'Token generation failed');

                    const deepLink='stepmonitor://auth?endpoint='+encodeURIComponent(endpoint)+'&token='+encodeURIComponent(data.token);
                    if(tokenValEl) tokenValEl.textContent=data.token;
                    if(tokenQrEl){ tokenQrEl.src='https://api.qrserver.com/v1/create-qr-code/?size=220x220&data='+encodeURIComponent(deepLink); tokenQrEl.style.display='block'; }
                    if(tokenLinkEl){ tokenLinkEl.href=deepLink; tokenLinkEl.textContent=deepLink; tokenLinkEl.style.display='block'; }
                    if(outEl) outEl.style.display='block';
                    await copyText(deepLink);
                }catch(e){
                    setErr(String(e && e.message ? e.message : e));
                }finally{
                    if(genBtn) genBtn.disabled=false;
                }
            });
        })();
        </script>
        <script>
        (function(){
            const btn=document.getElementById('stepmonitor-apk-button');
            if(!btn) return;
            const version=<?php echo json_encode($stepmonitorVersion); ?>;
            btn.addEventListener('click', async ()=>{
                window.location.href='/downloads/stepmonitor.apk';
                try{
                    const fd=new FormData();
                    fd.append('mobile_apk_state','1');
                    fd.append('hidden','1');
                    fd.append('version',version);
                    await fetch(location.href,{method:'POST',body:fd,credentials:'same-origin'});
                }catch(e){}
                btn.remove();
                if(typeof showAlert==='function') showAlert(<?php echo json_encode($translations['mobile_app_download_notice'] ?? 'The mobile app download button was hidden. You can enable it again in settings.'); ?>);
            });
        })();
        </script>
    </div>
    
