<body>
    <?php
$bgFull = $theme === 'dark' ? 'background_dark.jpg' : 'background_light.jpg';
$stepmonitorRoot = dirname(__DIR__, 3);
$stepmonitorApk = $stepmonitorRoot . '/downloads/stepmonitor.apk';
$stepmonitorMetaFile = $stepmonitorRoot . '/downloads/stepmonitor.json';
$stepmonitorMeta = is_file($stepmonitorMetaFile) ? (json_decode((string)file_get_contents($stepmonitorMetaFile), true) ?: []) : [];
$stepmonitorVersionName = (string)($stepmonitorMeta['version'] ?? '');
$stepmonitorBuiltAt = (int)($stepmonitorMeta['built_at'] ?? 0);
$stepmonitorAvailable = is_file($stepmonitorApk);
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
$stepmonitorPort = (string)($_SERVER['SERVER_PORT'] ?? '');
$stepmonitorForwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$stepmonitorScheme = ($stepmonitorForwardedProto === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $stepmonitorPort === '443') ? 'https' : 'http';
$stepmonitorPortPart = ($stepmonitorPort !== '' && !in_array($stepmonitorPort, ['80', '443'], true)) ? ':' . $stepmonitorPort : '';
$stepmonitorLanEndpoint = $stepmonitorServerIp !== '' ? $stepmonitorScheme . '://' . $stepmonitorServerIp . $stepmonitorPortPart . '/api/steps' : '';
$stepmonitorApkDownloadName = 'StepMonitor' . ($stepmonitorVersionName !== '' ? '-' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $stepmonitorVersionName) : '') . '.apk';
$stepmonitorExistingToken = '';
try {
    $tokenEmail = function_exists('get_current_user_email') ? (string)(get_current_user_email() ?? '') : (string)($_SESSION['user']['email'] ?? '');
    $tokenDb = function_exists('getDB') ? getDB() : null;
    if ($tokenEmail !== '' && $tokenDb) {
        $tokenStmt = $tokenDb->prepare("SELECT token_plain FROM client_auth_tokens WHERE email = :e AND app_id = 'steps' AND revoked = 0 AND expires_at > :t AND token_plain <> '' ORDER BY created_at DESC LIMIT 1");
        $tokenStmt->bindValue(':e', $tokenEmail, PDO::PARAM_STR);
        $tokenStmt->bindValue(':t', time(), PDO::PARAM_INT);
        $tokenRow = $tokenStmt->execute()->fetch(PDO::FETCH_ASSOC);
        if ($tokenRow && (string)($tokenRow['token_plain'] ?? '') !== '') {
            $stepmonitorExistingToken = (string)$tokenRow['token_plain'];
        }
    }
} catch (Throwable $e) {
    $stepmonitorExistingToken = '';
}
$metricHasValue = static function (array $metrics, string $type): bool {
    if ($type === 'distance') return isset($metrics['distance']['total_meters']) && is_numeric($metrics['distance']['total_meters']) && (float)$metrics['distance']['total_meters'] > 0;
    if ($type === 'calories') {
        $calories = $metrics['total_calories'] ?? $metrics['active_calories'] ?? null;
        return is_array($calories) && isset($calories['total']) && is_numeric($calories['total']) && (float)$calories['total'] > 0;
    }
    if ($type === 'heart_rate') {
        $heartRate = $metrics['heart_rate'] ?? null;
        if (!is_array($heartRate)) return false;
        $value = $heartRate['avg'] ?? $heartRate['last'] ?? null;
        return is_numeric($value) && (float)$value > 0;
    }
    if ($type === 'sleep') return isset($metrics['sleep']['total_seconds']) && is_numeric($metrics['sleep']['total_seconds']) && (float)$metrics['sleep']['total_seconds'] > 0;
    if ($type === 'weight') {
        $weight = $metrics['weight'] ?? null;
        if (!is_array($weight)) return false;
        $value = $weight['last'] ?? $weight['avg'] ?? null;
        return is_numeric($value) && (float)$value > 0;
    }
    return true;
};
$availableMetricTypes = ['steps' => true];
foreach (($healthMetrics ?? []) as $healthDay) {
    $metrics = is_array($healthDay['metrics'] ?? null) ? $healthDay['metrics'] : [];
    foreach (['distance', 'calories', 'heart_rate', 'sleep', 'weight'] as $metricType) {
        if (empty($availableMetricTypes[$metricType]) && $metricHasValue($metrics, $metricType)) $availableMetricTypes[$metricType] = true;
    }
}
$metricButtons = [
    'steps' => ['label' => $translations['steps'] ?? 'Steps', 'icon' => '<svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="30" fill="#3D8BFF"/><rect x="16" y="16" width="32" height="32" rx="8" fill="#FFFFFF"/><circle cx="32" cy="34" r="9" fill="none" stroke="#3D8BFF" stroke-width="2.6"/><path d="M32 34 L36 27" stroke="#3D8BFF" stroke-width="2.6" stroke-linecap="round"/><circle cx="32" cy="34" r="1.8" fill="#3D8BFF"/><path d="M26 20 h12 l-2 4 h-8 Z" fill="#3D8BFF"/></svg>'],
    'distance' => ['label' => $translations['distance_km'] ?? 'Distance', 'icon' => '<svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="30" fill="#22B573"/><path d="M17 28 A15 15 0 1 1 47 28 C47 39 32 51 32 51 C32 51 17 39 17 28 Z" fill="#FFFFFF"/><circle cx="32" cy="28" r="6" fill="#22B573"/></svg>'],
    'calories' => ['label' => $translations['calories'] ?? 'Calories', 'icon' => '<svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="30" fill="#FF6B4A"/><path d="M32 14 C38 22 44 28 44 36 C44 43.7 38.8 50 32 50 C25.2 50 20 43.7 20 36 C20 28 26 22 32 14 Z" fill="#FFD24C"/><path d="M32 26 C35 31 38 35 38 39 C38 43.4 35.3 46 32 46 C28.7 46 26 43.4 26 39 C26 35 29 31 32 26 Z" fill="#FF8A3D"/></svg>'],
    'heart_rate' => ['label' => $translations['heart_rate'] ?? 'Heart rate', 'icon' => '<svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="30" fill="#F44778"/><path d="M32 48.5 C20 40.5 13 33.5 13 26 C13 20 17.5 15.5 23 15.5 C27 15.5 30 18 32 21.5 C34 18 37 15.5 41 15.5 C46.5 15.5 51 20 51 26 C51 33.5 44 40.5 32 48.5 Z" fill="#FFFFFF"/><polyline points="18,31.5 24,31.5 27,24.5 31,38.5 34,31.5 46,31.5" fill="none" stroke="#F44778" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>'],
    'sleep' => ['label' => $translations['sleep'] ?? 'Sleep', 'icon' => '<svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="30" fill="#4C5FD5"/><path d="M40 18 C31 18 24 25 24 34 C24 43 31 50 40 50 C44.5 50 48.6 48.2 51.5 45.2 C47.9 46.6 43.9 46.6 40.1 44.9 C33.6 42 30 35.3 31.4 28.4 C32.4 23.6 35.8 19.9 40.2 18.3 C40.1 18.2 40.1 18.1 40 18 Z" fill="#FFE9A8"/><circle cx="19" cy="20" r="1.8" fill="#FFFFFF"/><circle cx="15" cy="30" r="1.2" fill="#FFFFFF"/><circle cx="46" cy="16" r="1.4" fill="#FFFFFF"/><path d="M15 42 h4 M17 40 v4" stroke="#FFFFFF" stroke-width="1.4" stroke-linecap="round"/></svg>'],
    'weight' => ['label' => $translations['weight'] ?? 'Weight', 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="5" fill="#a78bfa"/><path fill="#7c3aed" d="M8.2 9.5a4.4 4.4 0 0 1 7.6 0l-2.7 1.6a1.3 1.3 0 0 0-2.2 0L8.2 9.5Z"/><circle cx="12" cy="12" r="2.1" fill="#fff"/></svg>'],
];
?>
    <div id="bg-layer" aria-hidden="true"></div>
    <script>(function(){var s=<?php echo json_encode($bgFull, JSON_HEX_TAG); ?>,el=document.getElementById('bg-layer'),im=new Image(),apply=function(){el.style.backgroundImage='url('+s+')';el.classList.add('ready')};im.decoding='async';im.onload=apply;im.src=s;if(im.complete)apply()})();</script>
    <div class="app-steps">
        <div class="app-topbar">
            <h1><?php echo htmlspecialchars($translations['steps_counter_title']); ?></h1>
            <div class="app-topbar-actions">
                <button id="sharepoint-share-button" type="button" title="<?php echo htmlspecialchars($translations['share'] ?? 'Share'); ?>" aria-label="<?php echo htmlspecialchars($translations['share'] ?? 'Share'); ?>"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg></button>
                <button id="add-steps-button" title="<?php echo htmlspecialchars($translations['add_action_tooltip'] ?? $translations['add_steps_tooltip']); ?>">
                    <i class="fas fa-plus"></i>
                </button>
                <button id="stepmonitor-apk-button" type="button" title="<?php echo htmlspecialchars($translations['mobile_app_download'] ?? 'Download mobile app'); ?>" aria-label="<?php echo htmlspecialchars($translations['mobile_app_download'] ?? 'Download mobile app'); ?>"><i class="fas fa-mobile-screen-button"></i></button>
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
            <?php foreach ($metricButtons as $metricType => $metricButton): ?>
            <?php if (!empty($availableMetricTypes[$metricType])): ?>
            <button class="data-type-btn<?php echo $metricType === 'steps' ? ' active' : ''; ?>" data-type="<?php echo htmlspecialchars($metricType); ?>" title="<?php echo htmlspecialchars($metricButton['label']); ?>" aria-label="<?php echo htmlspecialchars($metricButton['label']); ?>">
                <?php echo $metricButton['icon']; ?>
            </button>
            <?php endif; ?>
            <?php endforeach; ?>
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
        
        </div>
        </div>

        <div id="notifications" aria-live="polite" aria-atomic="true">
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
                <div class="info-scroll">
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
                <div class="info-card">
                        <div class="info-card-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="7" y="2" width="10" height="20" rx="2" ry="2"></rect>
                                <line x1="2" y1="12" x2="5" y2="12"></line>
                                <line x1="5" y1="9" x2="5" y2="15"></line>
                                <line x1="15" y1="9" x2="15" y2="15"></line>
                            </svg>
                        </div>
                        <h3><?php echo htmlspecialchars($translations['mobile_app_modal_title'] ?? 'Mobile App'); ?></h3>
                        <p><?php echo htmlspecialchars($translations['info_card_mobile_text'] ?? 'Install the companion Android app to sync steps and health data from your phone to this dashboard.'); ?></p>
                    </div>
                </div>
                </div>
            </div>
        </div>
        
        <!-- Mobile App Modal -->
        <div id="mobile-app-modal" style="display:none;">
            <div class="mobile-app-content">
                <button class="modal-close" type="button" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>

                <div class="ma-header">
                    <h1><?php echo htmlspecialchars($translations['mobile_app_modal_title'] ?? 'Mobile App'); ?></h1>
                    <p><?php echo htmlspecialchars($translations['info_card_mobile_text'] ?? 'Install the companion Android app to sync steps and health data from your phone to this dashboard.'); ?></p>
                </div>

                <div class="ma-grid">
                    <div class="ma-action">
                        <button type="button" id="mobile-app-manual-hint" class="ma-qr-hint" title="<?php echo htmlspecialchars($translations['mobile_app_manual_hint_title'] ?? 'Manual pairing'); ?>" aria-label="<?php echo htmlspecialchars($translations['mobile_app_manual_hint_title'] ?? 'Manual pairing'); ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                        </button>
                        <div class="ma-qr-frame">
                            <div class="ma-qr-placeholder" aria-hidden="true">
                                <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="100" height="100" fill="white"/>
                                    <path d="M10 10H30V30H10V10ZM70 10H90V30H70V10ZM10 70H30V90H10V70ZM40 40H60V60H40V40ZM40 10H60V20H40V10ZM10 40H20V60H10V40ZM80 40H90V60H80V40ZM40 80H60V90H40V80ZM80 80H90V90H80V80Z" fill="#121212"/>
                                </svg>
                            </div>
                            <div class="ma-qr-code" id="mobile-app-qr-code"></div>
                            <button type="button" id="mobile-app-qr-generate" class="ma-qr-generate">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path><line x1="12" y1="2" x2="12" y2="12"></line></svg>
                                <?php echo htmlspecialchars($translations['mobile_app_qr_generate'] ?? 'Generate'); ?>
                            </button>
                        </div>
                        <button type="button" id="mobile-app-token-revoke" class="ma-qr-revoke" style="display:none;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path></svg>
                            <?php echo htmlspecialchars($translations['mobile_app_token_revoke'] ?? 'Revoke Token'); ?>
                        </button>
                        <h3><?php echo htmlspecialchars($translations['mobile_app_scan_title'] ?? 'Scan to Pair'); ?></h3>
                        <p><?php echo htmlspecialchars($translations['mobile_app_scan_desc'] ?? 'Open the app and scan this code to sync instantly.'); ?></p>
                        <p class="ma-qr-error" id="mobile-app-qr-error" style="display:none;color:#ef4444;font-size:12px;margin:-16px 0 16px;"></p>

                        <?php if ($stepmonitorAvailable): ?>
                        <div class="ma-download-row">
                            <a class="ma-download" href="/downloads/stepmonitor.apk" download="<?php echo htmlspecialchars($stepmonitorApkDownloadName); ?>">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                <?php echo htmlspecialchars($translations['mobile_app_download'] ?? 'Download mobile app'); ?>
                            </a>
                            <button type="button" id="mobile-app-play-protect-guide" class="ma-play-protect">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                <?php echo htmlspecialchars($translations['mobile_app_play_protect'] ?? 'Play Protect guide'); ?>
                            </button>
                        </div>
                        <?php endif; ?>

                        <?php if ($stepmonitorVersionName !== '' || $stepmonitorBuiltAt > 0): ?>
                        <div class="ma-meta">
                            <?php if ($stepmonitorVersionName !== ''): ?><span>v<?php echo htmlspecialchars($stepmonitorVersionName); ?></span><?php endif; ?>
                            <?php if ($stepmonitorVersionName !== '' && $stepmonitorBuiltAt > 0): ?><span>•</span><?php endif; ?>
                            <?php if ($stepmonitorBuiltAt > 0): ?><span><?php echo htmlspecialchars($translations['mobile_app_version_updated'] ?? 'Updated'); ?> <?php echo htmlspecialchars(date('d.m.Y', $stepmonitorBuiltAt)); ?></span><?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="ma-features">
                        <div class="ma-feature">
                            <div class="ma-feature-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
                            </div>
                            <div class="ma-feature-text">
                                <h4><?php echo htmlspecialchars($translations['mobile_app_feature_sync_title'] ?? 'Health Connect Sync'); ?></h4>
                                <p><?php echo htmlspecialchars($translations['mobile_app_feature_sync_desc'] ?? 'Automatically sync your steps with Health Connect on Android.'); ?></p>
                            </div>
                        </div>

                        <div class="ma-feature">
                            <div class="ma-feature-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                            </div>
                            <div class="ma-feature-text">
                                <h4><?php echo htmlspecialchars($translations['mobile_app_feature_qr_title'] ?? 'QR Pairing'); ?></h4>
                                <p><?php echo htmlspecialchars($translations['mobile_app_feature_qr_desc'] ?? 'Scan a QR code to pair the app with this dashboard in seconds.'); ?></p>
                            </div>
                        </div>

                        <div class="ma-feature">
                            <div class="ma-feature-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                            </div>
                            <div class="ma-feature-text">
                                <h4><?php echo htmlspecialchars($translations['mobile_app_feature_secure_title'] ?? 'Secure Token Auth'); ?></h4>
                                <p><?php echo htmlspecialchars($translations['mobile_app_feature_secure_desc'] ?? 'Use a generated token for secure, password-free authentication.'); ?></p>
                            </div>
                        </div>

                        <div class="ma-feature">
                            <div class="ma-feature-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path><line x1="12" y1="2" x2="12" y2="12"></line></svg>
                            </div>
                            <div class="ma-feature-text">
                                <h4><?php echo htmlspecialchars($translations['mobile_app_feature_offline_title'] ?? 'Offline Tracking'); ?></h4>
                                <p><?php echo htmlspecialchars($translations['mobile_app_feature_offline_desc'] ?? 'Track steps even without an internet connection.'); ?></p>
                            </div>
                        </div>

                        <div class="ma-feature">
                            <div class="ma-feature-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            </div>
                            <div class="ma-feature-text">
                                <h4><?php echo htmlspecialchars($translations['mobile_app_devices'] ?? 'Connected devices'); ?></h4>
                                <p><?php echo htmlspecialchars($translations['mobile_app_feature_devices_desc'] ?? 'Sync data from multiple wearables and fitness trackers.'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manual Pairing Modal -->
        <div id="manual-pair-modal" style="display:none;">
            <div class="manual-pair-content">
                <button type="button" id="manual-pair-close" class="modal-close" aria-label="<?php echo htmlspecialchars($translations['manual_pair_close'] ?? 'Close'); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
                <div class="ma-header">
                    <h1><?php echo htmlspecialchars($translations['manual_pair_title'] ?? 'Manual pairing'); ?></h1>
                    <p><?php echo htmlspecialchars($translations['manual_pair_intro'] ?? 'If QR scanning is not available, connect the app manually:'); ?></p>
                </div>
                <ol class="manual-pair-steps">
                    <li><?php echo htmlspecialchars($translations['manual_pair_step1'] ?? ''); ?></li>
                    <li><?php echo htmlspecialchars($translations['manual_pair_step2'] ?? ''); ?></li>
                    <li><?php echo htmlspecialchars($translations['manual_pair_step3'] ?? ''); ?></li>
                </ol>
                <div class="manual-pair-fields">
                    <div class="manual-pair-row">
                        <div class="manual-pair-field">
                            <label><?php echo htmlspecialchars($translations['manual_pair_endpoint_label'] ?? 'Server endpoint'); ?></label>
                            <pre id="manual-pair-endpoint"></pre>
                        </div>
                        <button type="button" class="ma-copy-btn" data-copy-target="manual-pair-endpoint" aria-label="<?php echo htmlspecialchars($translations['copy'] ?? 'Copy'); ?>">
                            <svg class="ma-copy-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                            <svg class="ma-copy-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </button>
                    </div>
                    <div class="manual-pair-row">
                        <div class="manual-pair-field">
                            <label><?php echo htmlspecialchars($translations['manual_pair_link_label'] ?? 'Connection link'); ?></label>
                            <pre id="manual-pair-link"></pre>
                        </div>
                        <button type="button" class="ma-copy-btn" data-copy-target="manual-pair-link" aria-label="<?php echo htmlspecialchars($translations['copy'] ?? 'Copy'); ?>">
                            <svg class="ma-copy-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                            <svg class="ma-copy-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </button>
                    </div>
                    <div class="manual-pair-row">
                        <div class="manual-pair-field">
                            <label><?php echo htmlspecialchars($translations['manual_pair_token_label'] ?? 'Token'); ?></label>
                            <pre id="manual-pair-token"><?php echo htmlspecialchars($translations['manual_pair_generate_first'] ?? 'Generate a QR code first.'); ?></pre>
                        </div>
                        <button type="button" class="ma-copy-btn" data-copy-target="manual-pair-token" aria-label="<?php echo htmlspecialchars($translations['copy'] ?? 'Copy'); ?>">
                            <svg class="ma-copy-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                            <svg class="ma-copy-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </button>
                    </div>
                </div>
                <div class="manual-pair-footer">
                    <button type="button" id="manual-pair-regenerate" class="manual-pair-regen-btn">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-refresh-ccw"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
                        <?php echo htmlspecialchars($translations['manual_pair_regenerate'] ?? 'Regenerate'); ?>
                    </button>
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
                        <button id="toggle-import-backup" class="excel-btn" type="button" style="background: var(--primary-color); color: #fff;">
                            <i class="fas fa-file-import"></i> <?php echo htmlspecialchars($translations['import_external_btn'] ?? 'Import'); ?>
                        </button>
                    </div>
                    <div class="backups-list" id="backups-list"></div>
                    <div class="settings-backup-import" style="display: none;">
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
                                <section class="va-segment takeout-guide-segment" id="zeep-guide-segment" data-step-section="1">
                                    <button type="button" class="takeout-guide-card-btn" id="zeep-guide-open-btn">
                                        <div class="takeout-guide-card-icon"><i class="fas fa-book-open"></i></div>
                                        <div class="takeout-guide-card-text">
                                            <strong><?php echo htmlspecialchars($translations['zeep_guide_title'] ?? 'How to export Zeep Life data'); ?></strong>
                                            <span><?php echo htmlspecialchars($translations['zeep_guide_btn_sub'] ?? 'Step-by-step instruction with screenshots'); ?></span>
                                        </div>
                                        <i class="fas fa-chevron-right takeout-guide-card-arrow"></i>
                                    </button>
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
                                <section class="va-segment takeout-guide-segment" id="takeout-guide-segment">
                                    <button type="button" class="takeout-guide-card-btn" id="takeout-guide-open-btn">
                                        <div class="takeout-guide-card-icon"><i class="fas fa-book-open"></i></div>
                                        <div class="takeout-guide-card-text">
                                            <strong><?php echo htmlspecialchars($translations['takeout_guide_title'] ?? 'How to create a Google Takeout archive'); ?></strong>
                                            <span><?php echo htmlspecialchars($translations['takeout_guide_btn_sub'] ?? 'Step-by-step instruction with screenshots'); ?></span>
                                        </div>
                                        <i class="fas fa-chevron-right takeout-guide-card-arrow"></i>
                                    </button>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="guide-expand-modal" style="display:none;">
            <div class="guide-expand-content">
                <div class="guide-expand-topbar">
                    <button type="button" id="guide-expand-back-btn" class="guide-expand-back-btn">
                        <i class="fas fa-arrow-left"></i>
                        <span><?php echo htmlspecialchars($translations['back'] ?? 'Back'); ?></span>
                    </button>
                    <h2 class="guide-expand-title"><i class="fas fa-book-open"></i> <span id="guide-expand-title-text"></span></h2>
                    <button type="button" class="modal-close" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>">×</button>
                </div>
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
        
        <!-- Toast container -->
        <!-- Alert Modal -->
        <div id="alert-modal" style="display:none;">
            <div class="alert-content">
                <button class="modal-close alert-close" type="button" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                <div class="alert-header" id="alert-header">
                    <h3 class="alert-title" id="alert-title"></h3>
                    <p class="alert-subtitle" id="alert-subtitle"></p>
                </div>
                <div class="alert-body">
                    <div class="alert-box" id="alert-box">
                        <div class="alert-icon" id="alert-icon" aria-hidden="true"></div>
                        <div class="alert-message" id="alert-message"></div>
                    </div>
                </div>
                <div class="alert-buttons">
                    <button class="alert-btn cancel" id="alert-cancel" style="display:none;"><?php echo htmlspecialchars($translations['cancel'] ?? 'Cancel'); ?></button>
                    <button class="alert-btn extra" id="alert-extra" style="display:none;"></button>
                    <button class="alert-btn ok" id="alert-ok">OK</button>
                </div>
            </div>
        </div>
        <div id="video-confirm-modal" style="display:none;">
            <div class="video-confirm-inner excel-content">
                <button class="modal-close" type="button" id="video-confirm-close-x" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
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
            <div class="video-confirm-gaps-inner excel-content">
                <button class="modal-close" type="button" id="video-confirm-gaps-close-x" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
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

        <div id="stepmonitor-conflict-modal" style="display:none;position:fixed;inset:0;z-index:10070;background:rgba(10,14,18,0.48);-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px);align-items:center;justify-content:center;padding:16px;">
            <div class="smc-card">
                <div class="smc-head">
                    <h3 class="smc-title"><?php echo htmlspecialchars($translations['sync_conflict_title'] ?? 'Sync conflict'); ?></h3>
                </div>
                <div class="smc-body">
                    <div class="smc-box">
                        <div class="smc-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg></div>
                        <p class="smc-msg" id="stepmonitor-conflict-message"></p>
                    </div>
                </div>
                <div class="smc-buttons">
                    <button type="button" class="smc-btn cancel" data-conflict-action="skip"><?php echo htmlspecialchars($translations['sync_conflict_skip'] ?? 'Skip phone data'); ?></button>
                    <button type="button" class="smc-btn extra" data-conflict-action="add"><?php echo htmlspecialchars($translations['sync_conflict_add'] ?? 'Add values'); ?></button>
                    <button type="button" class="smc-btn ok warning" data-conflict-action="replace"><?php echo htmlspecialchars($translations['sync_conflict_replace'] ?? 'Replace server data'); ?></button>
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
                msg.textContent=<?php echo json_encode($translations['sync_conflict_message'] ?? 'Different step values were found for {count} day(s). Choose which data to keep.', JSON_HEX_TAG); ?>.replace('{count}', String(days.length));
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
            const modal=document.getElementById('mobile-app-modal');
            const qrFrame=document.getElementById('mobile-app-qr-code');
            const placeholder=modal ? modal.querySelector('.ma-qr-placeholder') : null;
            const genBtn=document.getElementById('mobile-app-qr-generate');
            const revokeBtn=document.getElementById('mobile-app-token-revoke');
            const hintBtn=document.getElementById('mobile-app-manual-hint');

            const lanEndpoint=<?php echo json_encode($stepmonitorLanEndpoint, JSON_HEX_TAG); ?>;
            const currentHost=window.location.hostname;
            const endpoint=(lanEndpoint && (currentHost==='localhost'||currentHost==='127.0.0.1'||currentHost==='::1')) ? lanEndpoint : window.location.origin + '/api/steps';

            let pairing={token:'',link:''};
            const existingToken=<?php echo json_encode($stepmonitorExistingToken, JSON_HEX_TAG); ?>;

            const hintModal=document.getElementById('manual-pair-modal');
            const hintClose=document.getElementById('manual-pair-close');
            const hintEndpoint=document.getElementById('manual-pair-endpoint');
            const hintLink=document.getElementById('manual-pair-link');
            const hintToken=document.getElementById('manual-pair-token');
            const regenBtn=document.getElementById('manual-pair-regenerate');
            const regenLabel=regenBtn ? regenBtn.innerHTML : '';
            const revokeLabel=revokeBtn ? revokeBtn.innerHTML : '';
            const noTokenMsg=<?php echo json_encode($translations['manual_pair_generate_first'] ?? 'Generate a QR code first.', JSON_HEX_TAG); ?>;
            const playProtectBtn=document.getElementById('mobile-app-play-protect-guide');

            function setPairingToken(token){
                pairing.token=String(token||'');
                pairing.link=pairing.token ? ('stepmonitor://auth?endpoint='+encodeURIComponent(endpoint)+'&token='+encodeURIComponent(pairing.token)) : '';
            }
            function refreshHintFields(){
                if(hintEndpoint) hintEndpoint.textContent=endpoint;
                if(hintLink) hintLink.textContent=pairing.link||'—';
                if(hintToken) hintToken.textContent=pairing.token||noTokenMsg;
            }
            async function showPairingQr(){
                if(!pairing.token||!pairing.link) return;
                if(placeholder) placeholder.style.display='none';
                if(qrFrame){ qrFrame.style.display='flex'; qrFrame.innerHTML=''; await renderPairingQr(qrFrame, pairing.link); }
                if(genBtn) genBtn.style.display='none';
                if(revokeBtn) revokeBtn.style.display='';
                refreshHintFields();
            }
            function clearPairingUi(){
                pairing={token:'',link:''};
                if(qrFrame){ qrFrame.innerHTML=''; qrFrame.style.display='none'; }
                if(placeholder) placeholder.style.display='';
                if(genBtn) genBtn.style.display='';
                if(revokeBtn) revokeBtn.style.display='none';
                refreshHintFields();
            }
            function openHint(){
                if(!hintModal) return;
                refreshHintFields();
                document.body.classList.add('modal-open');
                hintModal.style.display='flex';
                if(hintModal.firstElementChild) hintModal.firstElementChild.classList.add('modal-content');
            }
            function closeHint(){
                if(!hintModal) return;
                hintModal.style.display='none';
                if(!modal || modal.style.display==='none') document.body.classList.remove('modal-open');
            }
            hintBtn?.addEventListener('click', openHint);
            hintClose?.addEventListener('click', closeHint);
            hintModal?.addEventListener('click', (e)=>{ if(e.target===hintModal) closeHint(); });
            document.addEventListener('keydown', (e)=>{ if(e.key==='Escape' && hintModal && hintModal.style.display==='flex') closeHint(); });

            playProtectBtn?.addEventListener('click', ()=>{
                if(typeof window.openStepsGuideExpand==='function') window.openStepsGuideExpand('play_protect');
            });

            document.querySelectorAll('#manual-pair-modal .ma-copy-btn').forEach(btn=>{
                btn.addEventListener('click', async ()=>{
                    const target=document.getElementById(btn.getAttribute('data-copy-target'));
                    const text=target ? target.textContent.trim() : '';
                    if(!text || text==='—' || text===noTokenMsg){
                        btn.classList.add('is-empty');
                        setTimeout(()=>btn.classList.remove('is-empty'),700);
                        return;
                    }
                    try{
                        await navigator.clipboard.writeText(text);
                        btn.classList.remove('is-copied');
                        void btn.offsetWidth;
                        btn.classList.add('is-copied');
                        setTimeout(()=>btn.classList.remove('is-copied'),1600);
                    }catch(e){
                        const ta=document.createElement('textarea');
                        ta.value=text;
                        ta.style.position='fixed';
                        ta.style.opacity='0';
                        document.body.appendChild(ta);
                        ta.select();
                        try{ document.execCommand('copy'); btn.classList.add('is-copied'); setTimeout(()=>btn.classList.remove('is-copied'),1600); }catch(e2){}
                        ta.remove();
                    }
                });
            });

            async function ensureQrCodeStyling(){
                if(typeof window.apEnsureQrCodeStyling==='function') return window.apEnsureQrCodeStyling();
                if(typeof QRCodeStyling !== 'undefined') return QRCodeStyling;
                await new Promise(function(resolve,reject){
                    var s=document.createElement('script');
                    s.src='/project/assets/qr-code-loader.js?v=1';
                    s.async=true;
                    s.onload=resolve;
                    s.onerror=reject;
                    document.head.appendChild(s);
                });
                if(typeof window.apEnsureQrCodeStyling==='function') return window.apEnsureQrCodeStyling();
                throw new Error('QR loader missing');
            }
            async function renderPairingQr(hostEl, data){
                try{
                    const QRCodeStylingCtor=await ensureQrCodeStyling();
                    if(typeof QRCodeStylingCtor==='undefined'){
                        hostEl.innerHTML='<p style="color:#121212;padding:12px;">QR code unavailable</p>';
                        return;
                    }
                    hostEl.innerHTML='';
                    const qr=new QRCodeStylingCtor({
                        width:220,
                        height:220,
                        type:'svg',
                        data:data,
                        margin:0,
                        qrOptions:{errorCorrectionLevel:'H'},
                        dotsOptions:{
                            type:'rounded',
                            gradient:{
                                type:'linear',
                                rotation:Math.PI/4,
                                colorStops:[
                                    { offset:0, color:'#7c3aed' },
                                    { offset:0.5, color:'#2563eb' },
                                    { offset:1, color:'#06b6d4' }
                                ]
                            }
                        },
                        cornersSquareOptions:{ type:'extra-rounded', color:'#2563eb' },
                        cornersDotOptions:{ type:'dot', color:'#7c3aed' },
                        backgroundOptions:{ color:'rgba(255,255,255,0)' }
                    });
                    qr.append(hostEl);
                }catch(error){
                    console.error('Pairing QR error:',error);
                    hostEl.innerHTML='<p style="color:#121212;padding:12px;">QR code unavailable</p>';
                }
            }

            async function fetchCsrf(){
                try{
                    const r=await fetch('/csrf',{method:'GET',credentials:'same-origin',cache:'no-store'});
                    const d=await r.json().catch(()=>null);
                    return d&&d.csrf?String(d.csrf):'';
                }catch(e){ return ''; }
            }
            async function generateQr(forceNew){
                const errEl=document.getElementById('mobile-app-qr-error');
                const showErr=(msg)=>{
                    if(errEl){ errEl.textContent=msg; errEl.style.display='block'; }
                };
                const hideErr=()=>{ if(errEl){ errEl.style.display='none'; errEl.textContent=''; } };
                async function sendRequest(csrf){
                    const fd=new FormData();
                    fd.append('action','generate_mcp_token');
                    fd.append('generate_mcp_token','1');
                    fd.append('ajax','1');
                    fd.append('mcp_token_name','Health Connect');
                    fd.append('token_app','steps');
                    if(forceNew) fd.append('force_new','1');
                    const opts={method:'POST',body:fd,credentials:'same-origin'};
                    if(csrf){
                        fd.append('_csrf',csrf);
                        opts.headers={'X-CSRF-TOKEN':csrf};
                    }
                    return fetch('/main',opts);
                }
                try{
                    hideErr();
                    let csrf=await fetchCsrf();
                    let res=await sendRequest(csrf);
                    let data=await res.json().catch(()=>null);
                    if(data && data.error==='csrf_forbidden'){
                        csrf=await fetchCsrf();
                        res=await sendRequest(csrf);
                        data=await res.json().catch(()=>null);
                    }
                    if(!data||!data.success||!data.token) throw new Error((data&&data.error)||'Token generation failed');
                    setPairingToken(data.token);
                    await showPairingQr();
                }catch(e){
                    console.error('Pairing QR error:',e);
                    showErr(String(e && e.message ? e.message : e));
                }
            }
            async function revokeToken(resetUi){
                const csrf=await fetchCsrf();
                const fd=new FormData();
                fd.append('action','revoke_mcp_token');
                fd.append('revoke_mcp_token','1');
                fd.append('ajax','1');
                fd.append('token_app','steps');
                if(csrf) fd.append('_csrf',csrf);
                const opts={method:'POST',body:fd,credentials:'same-origin'};
                if(csrf) opts.headers={'X-CSRF-TOKEN':csrf};
                const res=await fetch('/main',opts);
                const data=await res.json().catch(()=>null);
                if(!data||!data.success) throw new Error((data&&data.error)||'Token revoke failed');
                if(resetUi!==false) clearPairingUi();
                else pairing={token:'',link:''};
            }

            genBtn?.addEventListener('click', async ()=>{
                if(genBtn) genBtn.disabled=true;
                try{
                    await generateQr(false);
                }finally{
                    if(genBtn) genBtn.disabled=false;
                }
            });
            revokeBtn?.addEventListener('click', async ()=>{
                if(!revokeBtn || revokeBtn.disabled) return;
                revokeBtn.disabled=true;
                try{
                    await revokeToken(true);
                }catch(e){
                    console.error('Pairing token revoke error:',e);
                }finally{
                    revokeBtn.disabled=false;
                    revokeBtn.innerHTML=revokeLabel;
                }
            });
            regenBtn?.addEventListener('click', async ()=>{
                if(!regenBtn || regenBtn.disabled) return;
                regenBtn.disabled=true;
                regenBtn.classList.add('is-busy');
                try{
                    await generateQr(true);
                }finally{
                    regenBtn.disabled=false;
                    regenBtn.classList.remove('is-busy');
                    regenBtn.innerHTML=regenLabel;
                }
            });
            if(existingToken){
                setPairingToken(existingToken);
                showPairingQr().catch(e=>console.error('Existing pairing QR error:',e));
            }
        })();
        </script>
        <script>
        (function(){
            const headerBtn=document.getElementById('stepmonitor-apk-button');
            const modal=document.getElementById('mobile-app-modal');

            if(headerBtn){
                headerBtn.addEventListener('click', ()=>{
                    if(typeof openModal==='function') openModal('mobile-app-modal');
                    else if(modal){ modal.style.display='flex'; modal.firstElementChild.classList.add('modal-content'); document.body.classList.add('modal-open'); }
                });
            }
        })();
        </script>
    </div>
    
