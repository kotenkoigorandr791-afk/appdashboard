<body class="md3-steps <?php echo $theme === 'dark' ? 'dark' : 'light'; ?>" data-theme="<?php echo htmlspecialchars($theme === 'dark' ? 'dark' : 'light'); ?>">
    <?php
$bgFull = $theme === 'dark' ? 'background_dark.jpg' : 'background_light.jpg';
$bgBase = $theme === 'dark' ? 'background_dark' : 'background_light';
$bgSrcset = $bgBase . '-960.jpg 960w, ' . $bgBase . '-1920.jpg 1920w, ' . $bgFull . ' 5001w';
$stepmonitorRoot = dirname(__DIR__, 3);
$stepmonitorApk = $stepmonitorRoot . '/downloads/stepmonitor.apk';
$stepmonitorMetaFile = $stepmonitorRoot . '/downloads/stepmonitor.json';
$stepmonitorMeta = is_file($stepmonitorMetaFile) ? (json_decode((string)file_get_contents($stepmonitorMetaFile), true) ?: []) : [];
$stepmonitorVersionName = (string)($stepmonitorMeta['version'] ?? '');
$stepmonitorBuiltAt = (int)($stepmonitorMeta['built_at'] ?? 0);
$stepmonitorAvailable = is_file($stepmonitorApk);
// The endpoint must be reachable from the phone, not from inside the container:
// SERVER_ADDR/SERVER_PORT are the Docker bridge IP and nginx's internal port.
require_once dirname(__DIR__, 3) . '/lib/mobile_pair_endpoint.php';
$stepmonitorLanEndpoint = mobile_pair_endpoint('steps');
$stepmonitorApkDownloadName = 'NexSteps' . ($stepmonitorVersionName !== '' ? '-' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $stepmonitorVersionName) : '') . '.apk';
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
    'steps' => ['label' => $translations['steps'] ?? 'Steps', 'icon' => '<span class="data-type-icon" style="--metric-color:#3D8BFF" aria-hidden="true"><i class="fas fa-shoe-prints"></i></span>'],
    'distance' => ['label' => $translations['distance'] ?? 'Distance', 'icon' => '<span class="data-type-icon" style="--metric-color:#22B573" aria-hidden="true"><i class="fas fa-location-dot"></i></span>'],
    'calories' => ['label' => $translations['calories'] ?? 'Calories', 'icon' => '<span class="data-type-icon" style="--metric-color:#FF6B4A" aria-hidden="true"><i class="fas fa-fire-flame-curved"></i></span>'],
    'heart_rate' => ['label' => $translations['heart_rate'] ?? 'Heart rate', 'icon' => '<span class="data-type-icon" style="--metric-color:#F44778" aria-hidden="true"><i class="fas fa-heart-pulse"></i></span>'],
    'sleep' => ['label' => $translations['sleep'] ?? 'Sleep', 'icon' => '<span class="data-type-icon" style="--metric-color:#4C5FD5" aria-hidden="true"><i class="fas fa-moon"></i></span>'],
    'weight' => ['label' => $translations['weight'] ?? 'Weight', 'icon' => '<span class="data-type-icon" style="--metric-color:#a78bfa" aria-hidden="true"><i class="fas fa-weight-scale"></i></span>'],
];
?>
    <div id="bg-layer" aria-hidden="true"></div>
    <script>(function(){var s=<?php echo json_encode($bgFull, JSON_HEX_TAG); ?>,ss=<?php echo json_encode($bgSrcset, JSON_HEX_TAG); ?>,el=document.getElementById('bg-layer'),im=new Image(),apply=function(){el.style.backgroundImage='url('+(im.currentSrc||s)+')';el.classList.add('ready')};im.decoding='async';im.sizes='100vw';im.srcset=ss;im.onload=apply;im.src=s;if(im.complete)apply()})();</script>
    <div class="app-steps md3">
        <div class="app-topbar">
            <div class="app-topbar-inner">
                <div class="app-brand">
                    <div class="app-brand-mark" aria-hidden="true"><span class="material-symbols-rounded filled">directions_walk</span></div>
                    <div class="app-brand-text">
                        <h1><?php echo htmlspecialchars($translations['steps_counter_title']); ?></h1>
                        <p class="app-brand-sub"><?php echo htmlspecialchars($translations['activity_mobility'] ?? 'Activity & Mobility'); ?></p>
                    </div>
                </div>
                <div class="mode-switch" role="tablist" aria-label="<?php echo htmlspecialchars($translations['steps'] ?? 'Steps'); ?> / <?php echo htmlspecialchars($translations['map_title'] ?? 'Map'); ?>">
                    <button type="button" class="mode-switch-btn active" data-mode="steps" role="tab" aria-selected="true">
                        <span class="material-symbols-rounded icon-sm filled" aria-hidden="true">directions_walk</span>
                        <span class="mode-label"><?php echo htmlspecialchars($translations['steps'] ?? 'Steps'); ?></span>
                    </button>
                    <button type="button" class="mode-switch-btn" data-mode="map" role="tab" aria-selected="false">
                        <span class="material-symbols-rounded icon-sm" aria-hidden="true">map</span>
                        <span class="mode-label"><?php echo htmlspecialchars($translations['map_title'] ?? 'Location map'); ?></span>
                    </button>
                </div>
                <div class="app-topbar-actions">
                    <button id="sharepoint-share-button" class="topbar-icon-btn" type="button" title="<?php echo htmlspecialchars($translations['share'] ?? 'Share'); ?>" aria-label="<?php echo htmlspecialchars($translations['share'] ?? 'Share'); ?>"><span class="material-symbols-rounded" aria-hidden="true">share</span></button>
                    <div id="share-chooser" class="share-chooser" hidden>
                        <button type="button" id="share-choose-steps"><i class="fas fa-shoe-prints"></i> <?php echo htmlspecialchars($translations['share_steps'] ?? 'Share steps'); ?></button>
                        <button type="button" id="share-choose-location"><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($translations['location_share_title'] ?? 'Location sharing'); ?></button>
                    </div>
                    <button id="add-steps-button" class="topbar-icon-btn" type="button" title="<?php echo htmlspecialchars($translations['add_action_tooltip'] ?? $translations['add_steps_tooltip']); ?>" aria-label="<?php echo htmlspecialchars($translations['add_action_tooltip'] ?? $translations['add_steps_tooltip']); ?>">
                        <span class="material-symbols-rounded" aria-hidden="true">add</span>
                    </button>
                    <button id="stepmonitor-apk-button" class="topbar-icon-btn" type="button" title="<?php echo htmlspecialchars($translations['mobile_app_download'] ?? 'Download mobile app'); ?>" aria-label="<?php echo htmlspecialchars($translations['mobile_app_download'] ?? 'Download mobile app'); ?>"><span class="material-symbols-rounded" aria-hidden="true">smartphone</span></button>
                    <?php if (!empty($stepsData)): ?>
                    <button id="stats-button" class="topbar-icon-btn" type="button" title="<?php echo htmlspecialchars($translations['statistics_navigation'] ?? 'Activity & Movement'); ?>" aria-label="<?php echo htmlspecialchars($translations['statistics_navigation'] ?? 'Activity & Movement'); ?>">
                        <span class="material-symbols-rounded" aria-hidden="true">pie_chart</span>
                    </button>
                    <?php endif; ?>
                    <button id="settings-button" class="topbar-icon-btn" type="button" title="<?php echo htmlspecialchars($translations['settings_tooltip']); ?>" aria-label="<?php echo htmlspecialchars($translations['settings_tooltip']); ?>">
                        <span class="material-symbols-rounded" aria-hidden="true">settings</span>
                    </button>
                    <button id="info-button" class="topbar-icon-btn" type="button" title="<?php echo htmlspecialchars($translations['app_info'] ?? 'App Information'); ?>" aria-label="<?php echo htmlspecialchars($translations['app_info'] ?? 'App Information'); ?>">
                        <span class="material-symbols-rounded" aria-hidden="true">info</span>
                    </button>
                    <div id="user-button" class="topbar-icon-btn" title="<?php echo htmlspecialchars($userName); ?>" role="button" tabindex="0" aria-label="<?php echo htmlspecialchars($userName); ?>">
                        <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="">
                    </div>
                </div>
            </div>
        </div>

        <div class="app-main">
        <div class="kpi-row" id="kpi-row" aria-live="polite">
            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label" id="kpi-selected-label"><?php echo htmlspecialchars($translations['selected_day'] ?? 'Selected day'); ?></span>
                    <span class="kpi-badge" id="kpi-goal-badge">0%</span>
                </div>
                <div class="kpi-metric-row">
                    <span class="kpi-value" id="kpi-steps-val">0</span>
                    <span class="kpi-unit" id="kpi-steps-goal">/ 0</span>
                </div>
                <div class="kpi-bar" aria-hidden="true"><span id="kpi-steps-bar"></span></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label"><?php echo htmlspecialchars($translations['distance_km'] ?? 'Distance'); ?></span>
                    <span class="kpi-icon-badge kpi-icon-badge--blue" aria-hidden="true"><span class="material-symbols-rounded">straighten</span></span>
                </div>
                <div class="kpi-metric-row">
                    <span class="kpi-value" id="kpi-distance-val">0.00</span>
                    <span class="kpi-unit">km</span>
                </div>
                <span class="kpi-sub"><?php echo htmlspecialchars($translations['stride_length'] ?? 'Stride length'); ?>: <span id="kpi-stride-val" class="kpi-sub-strong"><?php echo (int)($userSettings['stride_length'] ?? 72); ?> cm</span></span>
            </div>
            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label"><?php echo htmlspecialchars($translations['calories'] ?? 'Calories'); ?></span>
                    <span class="kpi-icon-badge kpi-icon-badge--amber" aria-hidden="true"><span class="material-symbols-rounded">local_fire_department</span></span>
                </div>
                <div class="kpi-metric-row">
                    <span class="kpi-value" id="kpi-calories-val">0</span>
                    <span class="kpi-unit">kcal</span>
                </div>
                <span class="kpi-sub"><?php echo htmlspecialchars($translations['active_time'] ?? 'Active time'); ?>: <span id="kpi-time-val" class="kpi-sub-strong">—</span></span>
            </div>
            <div class="kpi-card">
                <div class="kpi-card-top">
                    <span class="kpi-label"><?php echo htmlspecialchars($translations['goal_days'] ?? 'Daily Goal Streak'); ?></span>
                    <span class="kpi-icon-badge kpi-icon-badge--emerald" aria-hidden="true"><span class="material-symbols-rounded">trending_up</span></span>
                </div>
                <div class="kpi-metric-row">
                    <span class="kpi-value" id="kpi-streak-val">0</span>
                </div>
                <span class="kpi-sub" id="kpi-month-total"><?php echo htmlspecialchars($translations['total_steps'] ?? 'Month total'); ?>: <span class="kpi-sub-strong">0</span></span>
            </div>
        </div>

        <section class="step-activity" aria-label="<?php echo htmlspecialchars($translations['step_activity'] ?? 'Step activity'); ?>">
            <div class="step-activity-head">
                <div class="step-activity-title-row">
                    <div class="step-activity-icon" aria-hidden="true"><span class="material-symbols-rounded">bar_chart</span></div>
                    <div class="step-activity-copy">
                        <h2><?php echo htmlspecialchars($translations['step_activity'] ?? 'Step Activity'); ?></h2>
                        <p><?php echo htmlspecialchars($translations['step_activity_hint'] ?? 'Click any bar to inspect daily metrics or scroll across history'); ?></p>
                    </div>
                </div>
                <div class="step-activity-toolbar">
                    <div class="data-type-selector seg-group">
                        <?php foreach (['steps', 'distance', 'calories'] as $metricType):
                            $metricButton = $metricButtons[$metricType];
                        ?>
                        <button class="data-type-btn<?php echo $metricType === 'steps' ? ' active' : ''; ?>" data-type="<?php echo htmlspecialchars($metricType); ?>" title="<?php echo htmlspecialchars($metricButton['label']); ?>" aria-label="<?php echo htmlspecialchars($metricButton['label']); ?>">
                            <span class="mode-label"><?php echo htmlspecialchars($metricButton['label']); ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="view-selector seg-group">
                        <button class="view-btn active" data-view="week"><?php echo htmlspecialchars($translations['week']); ?></button>
                        <button class="view-btn" data-view="month"><?php echo htmlspecialchars($translations['month']); ?></button>
                        <button class="view-btn" data-view="year"><?php echo htmlspecialchars($translations['year']); ?></button>
                    </div>
                    <div class="period-nav seg-group">
                        <button type="button" class="chart-nav chart-nav-left" id="chart-nav-left" aria-label="<?php echo htmlspecialchars($translations['chart_prev'] ?? 'Previous'); ?>">
                            <span class="material-symbols-rounded icon-sm" aria-hidden="true">chevron_left</span>
                        </button>
                        <span class="chart-range-label" id="chart-range-label">—</span>
                        <button type="button" class="chart-nav chart-nav-right" id="chart-nav-right" aria-label="<?php echo htmlspecialchars($translations['chart_next'] ?? 'Next'); ?>">
                            <span class="material-symbols-rounded icon-sm" aria-hidden="true">chevron_right</span>
                        </button>
                    </div>
                    <button type="button" class="btn-today" id="chart-today-btn"><span class="material-symbols-rounded icon-sm" aria-hidden="true">today</span> <?php echo htmlspecialchars($translations['today'] ?? 'Today'); ?></button>
                </div>
            </div>
            <div class="chart-container">
                <div class="chart-main">
                    <div class="chart-label" id="chart-label"></div>
                    <div class="chart-wrapper">
                        <canvas id="steps-chart"></canvas>
                    </div>
                </div>
            </div>
            <div class="history-scrubber">
                <label for="history-scrubber"><span class="material-symbols-rounded icon-sm" style="color:#4C9AFF" aria-hidden="true">swap_horiz</span><?php echo htmlspecialchars($translations['scroll_history'] ?? 'Scroll History'); ?></label>
                <input type="range" id="history-scrubber" class="md-slider" min="0" max="100" value="100" aria-label="<?php echo htmlspecialchars($translations['scroll_history'] ?? 'Scroll history'); ?>">
                <div class="chart-legend" aria-hidden="true">
                    <span><i class="lg-goal"></i><?php echo htmlspecialchars($translations['goal_reached'] ?? 'Goal reached'); ?></span>
                    <span><i class="lg-below"></i><?php echo htmlspecialchars($translations['below_goal'] ?? 'Below goal'); ?></span>
                </div>
            </div>
        </section>

        <div class="calendar-container">
            <div class="calendar-header">
                <div class="calendar-selector-box">
                    <div class="custom-select" data-custom-select="year-selector">
                        <select id="year-selector" class="custom-select-native" tabindex="-1" aria-hidden="true"></select>
                        <button type="button" class="custom-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                            <span class="custom-select-value">—</span>
                            <i class="fas fa-chevron-down custom-select-caret" aria-hidden="true"></i>
                        </button>
                        <div class="custom-select-menu" role="listbox"></div>
                    </div>
                    <div class="custom-select" data-custom-select="month-selector">
                        <select id="month-selector" class="custom-select-native" tabindex="-1" aria-hidden="true" disabled><option value="-">—</option></select>
                        <button type="button" class="custom-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                            <span class="custom-select-value">—</span>
                            <i class="fas fa-chevron-down custom-select-caret" aria-hidden="true"></i>
                        </button>
                        <div class="custom-select-menu" role="listbox"></div>
                    </div>
                </div>
            </div>
            <div id="calendar"></div>
            <div class="goal-summary" id="goal-summary" hidden></div>
        </div>

        </div>
        <div class="map-view" id="map-view" hidden>
                <!-- Map: viewport-bounded layers, provider settings, non-map summary -->
                <section id="ma-map" class="map-workspace" aria-label="<?php echo htmlspecialchars($translations['map_title'] ?? 'Location map'); ?>">
                    <div class="map-stage">
                        <div id="ma-map-canvas" role="application" aria-label="<?php echo htmlspecialchars($translations['map_title'] ?? 'Location map'); ?>"></div>
                        <div class="map-hud">
                            <p id="ma-map-summary" role="status" aria-live="polite"></p>
                            <p id="ma-map-live" role="status" aria-live="polite" hidden><span class="map-live-pulse" aria-hidden="true"></span><span id="ma-map-live-text"></span></p>
                            <p id="ma-map-error" role="alert" style="display:none;"></p>
                        </div>
                        <div class="map-vignette" aria-hidden="true"></div>
                        <button type="button" id="ma-sidebar-show" class="map-sidebar-show" title="<?php echo htmlspecialchars($translations['map_show_panel'] ?? 'Show panel'); ?>" aria-label="<?php echo htmlspecialchars($translations['map_show_panel'] ?? 'Show panel'); ?>" hidden>
                            <i class="fas fa-sliders" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="map-sidebar-backdrop" aria-hidden="true"></div>
                    <aside class="map-sidebar" data-tab="layers" aria-label="<?php echo htmlspecialchars($translations['map_controls'] ?? 'Map controls'); ?>">
                        <div class="map-sidebar-head">
                            <div class="map-sidebar-title">
                                <span class="map-sidebar-kicker"><?php echo htmlspecialchars($translations['map_controls'] ?? 'Map controls'); ?></span>
                            </div>
                            <button type="button" id="ma-sidebar-hide" class="map-day-btn" title="<?php echo htmlspecialchars($translations['map_hide_panel'] ?? 'Hide panel'); ?>" aria-label="<?php echo htmlspecialchars($translations['map_hide_panel'] ?? 'Hide panel'); ?>">
                                <i class="fas fa-angles-right" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="map-tabs" role="tablist" aria-label="<?php echo htmlspecialchars($translations['map_controls'] ?? 'Map controls'); ?>">
                            <button type="button" class="map-tab-btn is-active" data-map-tab="layers" role="tab" aria-selected="true"><?php echo htmlspecialchars($translations['map_layers'] ?? 'Layers'); ?></button>
                            <button type="button" class="map-tab-btn" data-map-tab="places" role="tab" aria-selected="false"><?php echo htmlspecialchars($translations['places'] ?? 'Places'); ?></button>
                            <button type="button" class="map-tab-btn" data-map-tab="trips" role="tab" aria-selected="false"><?php echo htmlspecialchars($translations['map_trips_title'] ?? 'Trips'); ?></button>
                        </div>
                        <div class="map-tab-panel" data-tab-panel="layers">
                        <div class="map-panel">
                            <span class="map-panel-title"><?php echo htmlspecialchars($translations['points_area_title'] ?? 'Area tools'); ?></span>
                            <div class="map-area-tools">
                                <button type="button" id="ma-area-toggle" class="map-day-btn" title="<?php echo htmlspecialchars($translations['points_area'] ?? 'Select an area on the map'); ?>"><i class="fas fa-vector-square" aria-hidden="true"></i></button>
                                <span id="ma-area-info" hidden></span>
                                <button type="button" id="ma-area-smooth" class="trip-btn" hidden title="<?php echo htmlspecialchars($translations['points_smooth'] ?? 'Smooth the selected area into routes'); ?>"><i class="fas fa-wand-magic-sparkles"></i></button>
                                <label id="ma-area-tolerance-wrap" class="map-area-tolerance" hidden title="<?php echo htmlspecialchars($translations['points_tolerance'] ?? 'Smoothing tolerance'); ?>">
                                    <select id="ma-area-tolerance" aria-label="<?php echo htmlspecialchars($translations['points_tolerance'] ?? 'Smoothing tolerance'); ?>">
                                        <option value="10">10 m</option>
                                        <option value="25" selected>25 m</option>
                                        <option value="50">50 m</option>
                                        <option value="100">100 m</option>
                                        <option value="250">250 m</option>
                                    </select>
                                </label>
                                <button type="button" id="ma-area-delete" class="trip-btn trip-btn-primary" hidden title="<?php echo htmlspecialchars($translations['points_delete'] ?? 'Delete'); ?>"><i class="fas fa-trash"></i></button>
                                <button type="button" id="ma-area-cancel" class="trip-btn" hidden><i class="fas fa-xmark"></i></button>
                            </div>
                        </div>
                        <div class="map-panel">
                            <div class="map-panel-head">
                                <span class="map-panel-title"><?php echo htmlspecialchars($translations['map_date_range'] ?? 'Date range'); ?></span>
                                <div class="map-day-nav">
                                    <button type="button" id="ma-map-day-prev" class="map-day-btn" aria-label="<?php echo htmlspecialchars($translations['map_day_prev'] ?? 'Previous day'); ?>" title="<?php echo htmlspecialchars($translations['map_day_prev'] ?? 'Previous day'); ?>">
                                        <i class="fas fa-chevron-left" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" id="ma-map-day-next" class="map-day-btn" aria-label="<?php echo htmlspecialchars($translations['map_day_next'] ?? 'Next day'); ?>" title="<?php echo htmlspecialchars($translations['map_day_next'] ?? 'Next day'); ?>">
                                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="map-date-grid">
                                <div class="map-field">
                                    <span><?php echo htmlspecialchars($translations['map_from'] ?? 'From'); ?></span>
                                    <div class="dp-field">
                                        <input type="date" id="ma-map-from" class="dp-native" tabindex="-1" aria-hidden="true" max="<?php echo date('Y-m-d'); ?>">
                                        <button type="button" class="dp-trigger" aria-haspopup="dialog" aria-expanded="false">
                                            <span class="dp-value">—</span>
                                            <i class="fas fa-calendar-days dp-icon" aria-hidden="true"></i>
                                        </button>
                                        <div class="dp-popover" role="dialog" hidden></div>
                                    </div>
                                </div>
                                <div class="map-field">
                                    <span><?php echo htmlspecialchars($translations['map_to'] ?? 'To'); ?></span>
                                    <div class="dp-field">
                                        <input type="date" id="ma-map-to" class="dp-native" tabindex="-1" aria-hidden="true" max="<?php echo date('Y-m-d'); ?>">
                                        <button type="button" class="dp-trigger" aria-haspopup="dialog" aria-expanded="false">
                                            <span class="dp-value">—</span>
                                            <i class="fas fa-calendar-days dp-icon" aria-hidden="true"></i>
                                        </button>
                                        <div class="dp-popover" role="dialog" hidden></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="map-panel">
                            <span class="map-panel-title"><?php echo htmlspecialchars($translations['map_layers'] ?? 'Layers'); ?></span>
                            <div class="map-layers">
                                <?php
                                $mapLayers = [
                                    'trips' => $translations['map_layer_trips'] ?? 'Trips',
                                    'improved' => $translations['map_layer_improved'] ?? 'Improved (derived)',
                                    'stops' => $translations['map_layer_stops'] ?? 'Stops',
                                    'places' => $translations['map_layer_places'] ?? 'Places',
                                    'points' => $translations['map_layer_points'] ?? 'Points',
                                    'photos' => $translations['map_layer_photos'] ?? 'Photos',
                                    'speed' => $translations['map_layer_speed'] ?? 'Speed',
                                    'heatmap' => $translations['map_layer_heatmap'] ?? 'Heatmap',
                                    'explored' => $translations['map_layer_explored'] ?? 'Explored area',
                                ];
                                foreach ($mapLayers as $layerKey => $layerLabel): $checked = in_array($layerKey, ['trips', 'stops', 'places', 'improved'], true); ?>
                                <label class="map-switch">
                                    <input type="checkbox" data-layer="<?php echo htmlspecialchars($layerKey); ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                    <span class="map-switch-track" aria-hidden="true"><span class="map-switch-thumb"></span></span>
                                    <span class="map-switch-label"><?php echo htmlspecialchars($layerLabel); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <div id="ma-speed-legend" class="speed-legend" hidden>
                                <span style="background:#38bdf8"></span><em>&lt;3</em><span style="background:#22c55e"></span><em>3–6</em><span style="background:#eab308"></span><em>6–12</em><span style="background:#f97316"></span><em>12–25</em><span style="background:#ef4444"></span><em>25–50</em><span style="background:#a855f7"></span><em>50+ km/h</em>
                            </div>
                        </div>
                        </div>
                        <div class="map-tab-panel" data-tab-panel="places">
                        <div class="map-panel">
                            <div class="map-panel-head">
                                <span class="map-panel-title"><?php echo htmlspecialchars($translations['visits_title'] ?? 'Visited places'); ?></span>
                            </div>
                            <div id="ma-visits-list" class="trip-list"></div>
                        </div>
                        </div>
                        <div class="map-tab-panel" data-tab-panel="trips">
                        <div class="map-panel">
                            <div class="map-panel-head">
                                <span class="map-panel-title"><?php echo htmlspecialchars($translations['map_trips_title'] ?? 'Trips'); ?></span>
                                <div class="map-day-nav">
                                    <button type="button" id="ma-trip-new" class="map-day-btn" title="<?php echo htmlspecialchars($translations['map_trip_new'] ?? 'Create trip from this range'); ?>" aria-label="<?php echo htmlspecialchars($translations['map_trip_new'] ?? 'Create trip from this range'); ?>">
                                        <i class="fas fa-plus" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" id="ma-trip-merge" class="map-day-btn" title="<?php echo htmlspecialchars($translations['map_trip_merge'] ?? 'Merge selected trips'); ?>" aria-label="<?php echo htmlspecialchars($translations['map_trip_merge'] ?? 'Merge selected trips'); ?>" disabled>
                                        <i class="fas fa-code-merge" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                            <div id="ma-trips-list" class="trip-list">
                                <span class="trip-empty"><?php echo htmlspecialchars($translations['map_trip_loading'] ?? 'Loading trips…'); ?></span>
                            </div>
                            <div id="ma-trip-editor" class="trip-editor" hidden>
                                <div class="trip-editor-head">
                                    <strong><?php echo htmlspecialchars($translations['map_trip_edit'] ?? 'Edit trip'); ?></strong>
                                    <button type="button" id="ma-trip-close" class="trip-close" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>"><span class="material-symbols-rounded" aria-hidden="true">close</span></button>
                                </div>
                                <label class="trip-field"><span><?php echo htmlspecialchars($translations['map_trip_name'] ?? 'Title'); ?></span><input type="text" id="ma-trip-title" maxlength="120"></label>
                                <div class="trip-dates">
                                    <label class="trip-field"><span><?php echo htmlspecialchars($translations['map_trip_start'] ?? 'Start'); ?></span><input type="datetime-local" id="ma-trip-start"></label>
                                    <label class="trip-field"><span><?php echo htmlspecialchars($translations['map_trip_end'] ?? 'End'); ?></span><input type="datetime-local" id="ma-trip-end"></label>
                                </div>
                                <label class="trip-field"><span><?php echo htmlspecialchars($translations['map_trip_notes'] ?? 'Notes'); ?></span><textarea id="ma-trip-notes" rows="2" maxlength="4000"></textarea></label>
                                <label class="trip-visible"><input type="checkbox" id="ma-trip-visible"> <span><?php echo htmlspecialchars($translations['map_trip_visible'] ?? 'Show route line on map'); ?></span></label>
                                <div class="trip-editor-actions">
                                    <button type="button" id="ma-trip-recalc" class="trip-btn"><i class="fas fa-rotate" aria-hidden="true"></i> <?php echo htmlspecialchars($translations['map_trip_recalculate'] ?? 'Recalculate'); ?></button>
                                    <button type="button" id="ma-trip-save" class="trip-btn trip-btn-primary"><?php echo htmlspecialchars($translations['map_trip_save'] ?? 'Save'); ?></button>
                                </div>
                            </div>
                        </div>
                        </div>
                        <div class="map-sidebar-foot">
                        <button type="button" id="ma-map-refresh" class="map-apply-btn map-refresh-btn">
                            <span class="material-symbols-rounded icon-sm" aria-hidden="true">refresh</span> <?php echo htmlspecialchars($translations['map_refresh'] ?? 'Refresh'); ?>
                        </button>
                        </div>
                    </aside>
                </section>
                <script>
                (function(){
                    function boot(){
                    const section=document.getElementById('ma-map');
                    if(!section) return;
                    const sidebarEl=section.querySelector('.map-sidebar');
                    section.querySelectorAll('[data-map-tab]').forEach(btn=>{
                        btn.addEventListener('click',()=>{
                            const tab=btn.getAttribute('data-map-tab');
                            if(!tab||!sidebarEl) return;
                            sidebarEl.setAttribute('data-tab', tab);
                            section.querySelectorAll('[data-map-tab]').forEach(b=>{
                                const on=b===btn;
                                b.classList.toggle('is-active', on);
                                b.setAttribute('aria-selected', on?'true':'false');
                            });
                        });
                    });
                    const mapHost=document.getElementById('map-view');
                    const canvas=document.getElementById('ma-map-canvas');
                    const summary=document.getElementById('ma-map-summary');
                    const errorEl=document.getElementById('ma-map-error');
                    const providerSel=document.getElementById('ma-map-provider');
                    const fromInput=document.getElementById('ma-map-from');
                    const toInput=document.getElementById('ma-map-to');
                    const refresh=document.getElementById('ma-map-refresh');
                    const resetBtn=document.getElementById('ma-map-reset');
                    const tripsList=document.getElementById('ma-trips-list');
                    const tripEditor=document.getElementById('ma-trip-editor');
                    const tripMergeBtn=document.getElementById('ma-trip-merge');
                    let tripsCache=[], editingTripId=null, tripsLoadSeq=0, focusTripId=null;
                    const selTrips=new Set();
                    const t={
                        loading: <?php echo json_encode($translations['map_loading'] ?? 'Loading map…'); ?>,
                        empty: <?php echo json_encode($translations['map_empty'] ?? 'No location data in this range.'); ?>,
                        unavailable: <?php echo json_encode($translations['map_unavailable'] ?? 'Map tiles are unavailable; showing counts only.'); ?>,
                        error: <?php echo json_encode($translations['map_error'] ?? 'Could not load map data.'); ?>,
                        summary: <?php echo json_encode($translations['map_summary'] ?? 'Trips: {trips} · Stops: {stops} · Places: {places} · Points: {points}'); ?>
                    };
                    const iso = (d) => d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
                    const todayIso = () => iso(new Date());
                    // A day that has not started yet has no data, so never show it.
                    const capToToday = () => {
                        const today = todayIso();
                        if (toInput.value && toInput.value > today) toInput.value = today;
                        if (fromInput.value && fromInput.value > today) fromInput.value = today;
                        if (fromInput.value && toInput.value && fromInput.value > toInput.value) fromInput.value = toInput.value;
                    };
                    toInput.max = todayIso();
                    fromInput.max = todayIso();
                    if (!toInput.value) toInput.value = todayIso();
                    if (!fromInput.value) fromInput.value = iso(new Date(Date.now() - 30*864e5));
                    capToToday();
                    const fallbackProviders={
                        esri_dark:{name:'Esri Dark Gray Canvas',url:'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}',attribution:'Tiles © Esri',max_zoom:16},
                        esri_imagery:{name:'Esri Satellite',url:'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',attribution:'Tiles © Esri',max_zoom:19},
                        esri_street:{name:'Esri Streets',url:'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}',attribution:'Tiles © Esri',max_zoom:19},
                        osm:{name:'OpenStreetMap',url:'https://tile.openstreetmap.org/{z}/{x}/{y}.png',attribution:'© OpenStreetMap contributors',max_zoom:19}
                    };
                    // CARTO now watermarks keyless tiles, so the web UI only offers keyless providers.
                    const keylessProviders=['esri_dark','esri_imagery','esri_street','osm','opentopomap'];
                    let map=null, layerGroup=null, providers=Object.assign({},fallbackProviders), provider='esri_dark', busy=false, lastFeatures=[], didFit=false;
                    let mapStarted=false, mapBootPromise=null, mapConfig={prefetch:true, overlays:true};
                    let leafletPromise=null, viewportSeq=0, viewportAbort=null, lastMove=null, prefetchTimer=null;
                    const PREFETCH_KEY='stepsMapPrefetchMode';
                    const prefetchModes=['off','wifi','balanced','aggressive'];
                    let prefetchMode=(function(){ try{ const v=localStorage.getItem(PREFETCH_KEY); return prefetchModes.includes(v)?v:'balanced'; }catch(e){ return 'balanced'; } })();
                    const chunkCache=new Map();
                    const prefetchQueue=[];
                    let prefetchActive=0, prefetchAbort=null, prefetchRequests=0, prefetchBytes=0, prefetchWindowStart=Date.now();
                    window.stepsMapPerf={visible:0,prefetch:0,prefetchHits:0,cancelled:0,stale:0,bytes:0,prefetchBytes:0,errors:0};
                    const APPEARANCE_KEY='stepsMapAppearance';
                    const sliderDefs=[
                        {id:'ma-map-point-size', key:'pointSize', def:5, out:v=>v+' px'},
                        {id:'ma-map-opacity', key:'opacity', def:85, out:v=>v+'%'},
                        {id:'ma-map-route-width', key:'routeWidth', def:4, out:v=>v+' px'},
                        {id:'ma-map-heat-intensity', key:'heat', def:60, out:v=>v+'%'}
                    ];
                    const defaultAppearance=()=>{ const d={}; sliderDefs.forEach(s=>d[s.key]=s.def); return d; };
                    let appearance=defaultAppearance();
                    try{ appearance=Object.assign(defaultAppearance(), JSON.parse(localStorage.getItem(APPEARANCE_KEY)||'{}')); }catch(e){}
                    const saveAppearance=()=>{ try{ localStorage.setItem(APPEARANCE_KEY, JSON.stringify(appearance)); }catch(e){} };
                    const sliders=sliderDefs.map(def=>{
                        const input=document.getElementById(def.id);
                        const out=document.querySelector(`output[data-for="${def.key}"]`);
                        if(!input) return null;
                        input.value=appearance[def.key];
                        if(out) out.textContent=def.out(appearance[def.key]);
                        input.addEventListener('input',()=>{
                            appearance[def.key]=Number(input.value)||def.def;
                            if(out) out.textContent=def.out(appearance[def.key]);
                            saveAppearance();
                            scheduleRender();
                        });
                        return {def,input,out};
                    }).filter(Boolean);
                    function resetAppearance(){
                        appearance=defaultAppearance();
                        sliders.forEach(s=>{
                            s.input.value=appearance[s.def.key];
                            if(s.out) s.out.textContent=s.def.out(appearance[s.def.key]);
                        });
                        saveAppearance();
                        scheduleRender();
                    }
                    let renderTimer=null;
                    function scheduleRender(){
                        if(renderTimer) return;
                        renderTimer=setTimeout(()=>{ renderTimer=null; render(lastFeatures); },80);
                    }
                    // Places subsumes Visits: one toggle loads both map layers.
                    const selectedLayers = () => {
                        const layers=Array.from(section.querySelectorAll('input[data-layer]:checked')).map(i=>i.getAttribute('data-layer')).filter(Boolean);
                        if(layers.includes('places') && !layers.includes('visits')) layers.push('visits');
                        return layers;
                    };
                    const showError = (m) => { errorEl.textContent = m || ''; errorEl.style.display = m ? '' : 'none'; };
                    function ensureLeaflet(){
                        if (window.L) return Promise.resolve(window.L);
                        if (leafletPromise) return leafletPromise;
                        leafletPromise=new Promise((resolve, reject) => {
                            if (!document.getElementById('ma-leaflet-css')) {
                                const css=document.createElement('link');
                                css.id='ma-leaflet-css'; css.rel='stylesheet';
                                css.href='https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css';
                                document.head.appendChild(css);
                            }
                            const script=document.createElement('script');
                            script.src='https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js';
                            script.onload=()=>resolve(window.L);
                            script.onerror=()=>{ leafletPromise=null; reject(new Error('leaflet_unavailable')); };
                            document.head.appendChild(script);
                        });
                        return leafletPromise;
                    }
                    function renderProviders(){
                        const keys=Object.keys(providers).filter(key=>keylessProviders.includes(key));
                        const list=keys.length?keys:Object.keys(providers);
                        if(!list.includes(provider)) provider=list.includes('esri_dark')?'esri_dark':(list[0]||'osm');
                        providerSel.innerHTML='';
                        list.forEach(key=>{
                            const opt=document.createElement('option');
                            opt.value=key; opt.textContent=providers[key].name;
                            if (key===provider) opt.selected=true;
                            providerSel.appendChild(opt);
                        });
                    }
                    function selectProvider(key){
                        const p=providers[key]||providers.osm;
                        if(!map||!p) return;
                        if(map._tileLayer){ map.removeLayer(map._tileLayer); map._tileLayer=null; }
                        // Browser loads upstream tiles directly (avoids proxy 502 noise when
                        // the container has no egress). CDN + keepBuffer keep day flips warm.
                        const tileUrl=String(p.url||'');
                        if(!tileUrl) return;
                        map._tileLayer=window.L.tileLayer(tileUrl,{
                            attribution:p.attribution,
                            maxZoom:p.max_zoom||19,
                            keepBuffer:8,
                            updateWhenIdle:true,
                            updateWhenZooming:false,
                            crossOrigin:true,
                            className:'map-tiles',
                        }).addTo(map);
                        canvas.dataset.provider=key;
                    }
                    function makeMap(L){
                        map=L.map(canvas,{zoomControl:false,attributionControl:false,preferCanvas:true,worldCopyJump:true}).setView([50.45,30.52],12);
                        ['mapBackdrop','routesGlow','routes'].forEach((name,i)=>{
                            const pane=map.createPane(name);
                            pane.style.zIndex=String(395+i*5);
                            pane.style.pointerEvents='none';
                        });
                        // Live position sits above every recorded layer.
                        const livePane=map.createPane('live');
                        livePane.style.zIndex='455';
                        layerGroup=L.layerGroup().addTo(map);
                        L.control.zoom({position:'bottomright'}).addTo(map);
                        // Locate control sits above Leaflet's zoom +/- stack.
                        const LocateControl=L.Control.extend({
                            options:{position:'bottomright'},
                            onAdd:function(){
                                const wrap=L.DomUtil.create('div','leaflet-bar map-locate-control');
                                const btn=L.DomUtil.create('a','map-locate-btn',wrap);
                                btn.href='#';
                                btn.title=tr('map_goto_live','Go to my location');
                                btn.setAttribute('aria-label',tr('map_goto_live','Go to my location'));
                                btn.innerHTML='<i class="fas fa-location-crosshairs" aria-hidden="true"></i>';
                                L.DomEvent.disableClickPropagation(wrap);
                                L.DomEvent.on(btn,'click',function(ev){
                                    L.DomEvent.preventDefault(ev);
                                    goToLiveLocation();
                                });
                                return wrap;
                            }
                        });
                        new LocateControl().addTo(map);
                        L.control.scale({position:'bottomleft',imperial:false,maxWidth:110}).addTo(map);
                        try{ L.control.attribution({position:'bottomleft',prefix:false}).addTo(map); }catch(e){}
                        selectProvider(provider);
                        map.on('contextmenu',e=>openMapMenu(e.latlng,e.originalEvent,null));
                        map.on('movestart',()=>{
                            // A new gesture makes all queued prediction stale immediately.
                            cancelPrefetch();
                            const c=map.getCenter();
                            lastMove={lat:c.lat,lon:c.lng,t:performance.now()};
                        });
                        map.on('zoomend',()=>{
                            // Re-cluster photos from the last payload immediately so icons
                            // split/merge in place without waiting for the viewport fetch.
                            if(lastFeatures&&lastFeatures.length) render(lastFeatures);
                        });
                        map.on('moveend',()=>{
                            if(!map) return;
                            const c=map.getCenter(), now=performance.now();
                            const dt=lastMove?Math.max(1,now-lastMove.t):0;
                            const dx=lastMove?(c.lng-lastMove.lon):0, dy=lastMove?(c.lat-lastMove.lat):0;
                            lastMove={lat:c.lat,lon:c.lng,t:now};
                            if(map.getZoom()>=2) fetchViewport({dir:{x:dx,y:dy,dt}});
                        });
                        window.addEventListener('steps-map-shown',()=>{
                            if(!map) return;
                            didFit=false;
                            requestAnimationFrame(()=>map.invalidateSize({pan:false}));
                            setTimeout(()=>{ map.invalidateSize({pan:false}); fetchViewport({dir:null}); loadTrips(); },80);
                        });
                        window.addEventListener('steps-map-focus',(event)=>{
                            const bbox=event.detail&&event.detail.bbox;
                            if(!bbox) return;
                            map.fitBounds([[bbox.minLat,bbox.minLon],[bbox.maxLat,bbox.maxLon]],{padding:[30,30],maxZoom:16});
                        });
                        if(window.ResizeObserver){
                            new ResizeObserver(()=>{ if(map && mapHost && !mapHost.hidden) map.invalidateSize({pan:false}); }).observe(canvas);
                        }
                    }
                    const VISIT_COLORS={confirmed:'#22c55e',provisional:'#f59e0b',manual:'#3b82f6',unidentified:'#94a3b8',pending:'#94a3b8'};
                    function popupHtml(title, lines){
                        const div=document.createElement('div');
                        const strong=document.createElement('strong'); strong.textContent=title; div.appendChild(strong);
                        (lines||[]).forEach(line=>{ const p=document.createElement('div'); p.textContent=line; p.style.fontSize='11px'; p.style.opacity='.82'; div.appendChild(p); });
                        return div;
                    }
                    const photoThumbQueue=[];
                    let photoThumbActive=0;
                    const PHOTO_THUMB_MAX=8;
                    const photoThumbReady=new Map();
                    const photoThumbInflight=new Map();
                    function enqueuePhotoThumb(loader){
                        return new Promise((resolve,reject)=>{
                            photoThumbQueue.push({loader,resolve,reject});
                            pumpPhotoThumbs();
                        });
                    }
                    function pumpPhotoThumbs(){
                        while(photoThumbActive<PHOTO_THUMB_MAX && photoThumbQueue.length){
                            const job=photoThumbQueue.shift();
                            photoThumbActive++;
                            Promise.resolve().then(job.loader).then(job.resolve,job.reject).finally(()=>{ photoThumbActive--; pumpPhotoThumbs(); });
                        }
                    }
                    function loadPhotoThumb(url){
                        const key=String(url||'');
                        if(!key) return Promise.reject(new Error('thumb_missing'));
                        if(photoThumbReady.has(key)) return Promise.resolve({src:photoThumbReady.get(key)});
                        if(photoThumbInflight.has(key)) return photoThumbInflight.get(key);
                        // fetch+blob survives marker rebuilds better than Image()+IntersectionObserver:
                        // map poll/clearLayers used to drop in-flight <img> loads mid-flight.
                        const pending=enqueuePhotoThumb(()=>fetch(key,{credentials:'same-origin',cache:'force-cache'})
                            .then(res=>{
                                if(!res.ok) throw new Error('thumb_http_'+res.status);
                                const ctype=String(res.headers.get('content-type')||'');
                                if(ctype && !ctype.startsWith('image/')) throw new Error('thumb_not_image');
                                return res.blob();
                            })
                            .then(blob=>{
                                if(!blob||!String(blob.type||'').startsWith('image/')) throw new Error('thumb_bad_blob');
                                const src=URL.createObjectURL(blob);
                                photoThumbReady.set(key,src);
                                return {src};
                            })
                        ).finally(()=>{ photoThumbInflight.delete(key); });
                        photoThumbInflight.set(key,pending);
                        return pending;
                    }
                    function openImmichFromSteps(immichAssetId){
                        const id=String(immichAssetId||'').trim();
                        if(!id) return;
                        try{
                            if(window.top && window.top.ShellAppNav && typeof window.top.ShellAppNav.open==='function'){
                                window.top.ShellAppNav.open('immich', true, {asset:id});
                                return;
                            }
                        }catch(_){}
                        const url='/apps/immich/index.php?asset='+encodeURIComponent(id);
                        try{ if(window.top && window.top!==window){ window.top.location.href='/main?app=immich&asset='+encodeURIComponent(id); return; } }catch(_){}
                        window.location.href=url;
                    }
                    function ensurePhotoLightbox(){
                        let box=document.getElementById('photo-md3-lightbox');
                        if(box) return box;
                        box=document.createElement('div');
                        box.id='photo-md3-lightbox';
                        box.className='photo-md3-lightbox';
                        box.hidden=true;
                        box.innerHTML='<div class="photo-md3-lightbox-card"><div class="photo-md3-lightbox-top"><button type="button" class="photo-md3-btn" data-photo-lb-info>Info</button><button type="button" class="photo-md3-btn primary" data-photo-lb-immich>Immich</button><button type="button" class="photo-md3-btn" data-photo-lb-close aria-label="Close">✕</button></div><div class="photo-md3-lightbox-media"><img alt=""></div><div class="photo-md3-lightbox-meta" hidden></div></div>';
                        document.body.appendChild(box);
                        box.addEventListener('click',ev=>{ if(ev.target===box) box.hidden=true; });
                        box.querySelector('[data-photo-lb-close]').addEventListener('click',()=>{ box.hidden=true; });
                        return box;
                    }
                    function openPhotoLightbox(p){
                        const box=ensurePhotoLightbox();
                        const img=box.querySelector('.photo-md3-lightbox-media img');
                        const meta=box.querySelector('.photo-md3-lightbox-meta');
                        const immichBtn=box.querySelector('[data-photo-lb-immich]');
                        const infoBtn=box.querySelector('[data-photo-lb-info]');
                        img.removeAttribute('src');
                        meta.hidden=true;
                        meta.textContent='';
                        const lines=[];
                        if(p.original_filename) lines.push(p.original_filename);
                        if(p.captured_at) lines.push(String(p.captured_at).slice(0,19).replace('T',' '));
                        if(p.count>1) lines.push(String(p.count)+' photos here');
                        if(p.immich_asset_id) lines.push(p.immich_asset_id);
                        meta.innerHTML=lines.map(l=>'<div>'+String(l).replace(/[<>&]/g,s=>({"<":"&lt;",">":"&gt;","&":"&amp;"}[s]))+'</div>').join('');
                        infoBtn.onclick=()=>{ meta.hidden=!meta.hidden; };
                        immichBtn.onclick=()=>openImmichFromSteps(p.immich_asset_id);
                        box.hidden=false;
                        loadPhotoThumb(p.preview||p.thumbnail).then(loaded=>{ img.src=loaded.src; }).catch(()=>{ if(p.thumbnail) img.src=p.thumbnail; });
                    }
                    function photoPopupCard(p){
                        const root=document.createElement('div');
                        root.className='photo-md3-pop';
                        const media=document.createElement('div'); media.className='photo-md3-media';
                        const sk=document.createElement('div'); sk.className='photo-md3-skeleton'; media.appendChild(sk);
                        const img=document.createElement('img'); img.alt=''; img.decoding='async'; media.appendChild(img);
                        root.appendChild(media);
                        const actions=document.createElement('div'); actions.className='photo-md3-actions';
                        const expand=document.createElement('button'); expand.type='button'; expand.className='photo-md3-btn'; expand.innerHTML='<i class="fas fa-expand"></i>'; expand.title='Expand';
                        const info=document.createElement('button'); info.type='button'; info.className='photo-md3-btn'; info.innerHTML='<i class="fas fa-circle-info"></i>'; info.title='Info';
                        const open=document.createElement('button'); open.type='button'; open.className='photo-md3-btn primary'; open.textContent='Immich';
                        actions.appendChild(expand); actions.appendChild(info); actions.appendChild(open);
                        root.appendChild(actions);
                        const infoBox=document.createElement('div'); infoBox.className='photo-md3-info';
                        const bits=[];
                        if(p.original_filename) bits.push('<div>'+String(p.original_filename).replace(/[<>&]/g,s=>({"<":"&lt;",">":"&gt;","&":"&amp;"}[s]))+'</div>');
                        if(p.captured_at) bits.push('<div>'+String(p.captured_at).slice(0,19).replace('T',' ')+'</div>');
                        if(p.count>1) bits.push('<div>'+String(p.count)+' photos</div>');
                        infoBox.innerHTML=bits.join('')||'<div>—</div>';
                        root.appendChild(infoBox);
                        expand.addEventListener('click',ev=>{ ev.preventDefault(); ev.stopPropagation(); openPhotoLightbox(p); });
                        info.addEventListener('click',ev=>{ ev.preventDefault(); ev.stopPropagation(); infoBox.classList.toggle('is-open'); });
                        open.addEventListener('click',ev=>{ ev.preventDefault(); ev.stopPropagation(); openImmichFromSteps(p.immich_asset_id); });
                        root._photoLoad=()=>{
                            if(root._photoLoaded || !p.thumbnail) return;
                            root._photoLoaded=true;
                            loadPhotoThumb(p.thumbnail).then(loaded=>{
                                img.src=loaded.src;
                                img.classList.add('is-ready');
                                sk.remove();
                            }).catch(()=>{ sk.remove(); });
                        };
                        return root;
                    }
                    function addPoint(feature, style, title, lines){
                        const L=window.L;
                        const [lon,lat]=feature.geometry.coordinates;
                        const marker=L.circleMarker([lat,lon],Object.assign({className:'map-dot',stroke:true,color:'#ffffff',weight:1.5},style)).addTo(layerGroup);
                        marker.bindPopup(popupHtml(title, lines));
                        return marker;
                    }
                    function addCluster(feature, count){
                        const L=window.L;
                        const [lon,lat]=feature.geometry.coordinates;
                        const size=Math.min(48, 26+Math.sqrt(count)*4);
                        const marker=L.marker([lat,lon],{
                            keyboard:false,
                            icon:L.divIcon({className:'map-cluster', html:`<span>${count}</span>`, iconSize:[size,size], iconAnchor:[size/2,size/2]})
                        }).addTo(layerGroup);
                        marker.bindPopup(popupHtml(String(count), []));
                        return marker;
                    }
                    // Pixel-distance clustering keeps each photo at its GPS point; groups
                    // only form when icons would overlap on screen, so zoom no longer
                    // jumps markers via server-side SnapToGrid centroids.
                    function clusterPhotoFeatures(photoFeatures){
                        if(!map||!window.L||!photoFeatures.length) return [];
                        const L=window.L;
                        const zoom=map.getZoom();
                        const pxRadius=zoom>=17?22:zoom>=15?28:zoom>=13?34:40;
                        const items=photoFeatures.map(f=>{
                            const [lon,lat]=f.geometry.coordinates;
                            const ll=L.latLng(lat,lon);
                            const pt=map.latLngToLayerPoint(ll);
                            return {f, ll, pt, id:String((f.properties&&f.properties.asset_id)||'')};
                        }).sort((a,b)=>a.id<b.id?-1:a.id>b.id?1:0);
                        const used=new Set();
                        const out=[];
                        for(let i=0;i<items.length;i++){
                            if(used.has(i)) continue;
                            const group=[items[i]];
                            used.add(i);
                            for(let j=i+1;j<items.length;j++){
                                if(used.has(j)) continue;
                                const dx=items[i].pt.x-items[j].pt.x, dy=items[i].pt.y-items[j].pt.y;
                                if((dx*dx+dy*dy)<=pxRadius*pxRadius){ group.push(items[j]); used.add(j); }
                            }
                            // Anchor stays on the lexicographically first asset so the
                            // visible marker does not drift when membership changes.
                            group.sort((a,b)=>a.id<b.id?-1:a.id>b.id?1:0);
                            const head=group[0];
                            const props=Object.assign({}, head.f.properties||{}, {count:group.length});
                            out.push({
                                type:'Feature',
                                geometry:{type:'Point', coordinates:[head.ll.lng, head.ll.lat]},
                                properties:props
                            });
                        }
                        return out;
                    }
                    function addPhotoMarker(feature, baseRadius){
                        const L=window.L;
                        const p=feature.properties||{};
                        const [plon,plat]=feature.geometry.coordinates;
                        const count=Math.max(1, parseInt(p.count,10)||1);
                        const size=Math.max(32, baseRadius*4+14);
                        const countHtml=count>1?'<em class="map-photo-count">'+count+'</em>':'';
                        const photoCard=photoPopupCard(p);
                        const cachedThumb=p.thumbnail?photoThumbReady.get(String(p.thumbnail)):null;
                        const ringClass=cachedThumb?'map-photo-marker-ring is-ready':'map-photo-marker-ring is-loading';
                        const ringStyle='width:'+size+'px;height:'+size+'px';
                        const photoMarker=L.marker([plat,plon],{keyboard:false,icon:L.divIcon({className:'map-photo-marker',html:'<span class="'+ringClass+'" style="'+ringStyle+'"><span class="map-photo-fallback"><i class="fas fa-camera"></i></span><img alt="" decoding="async">'+countHtml+'</span>',iconSize:[size,size],iconAnchor:[size/2,size/2]})}).addTo(layerGroup);
                        photoMarker.bindPopup(photoCard,{maxWidth:280,className:'photo-md3-leaflet',closeButton:false,autoPanPadding:[24,24]});
                        photoMarker.on('popupopen',()=>{ if(typeof photoCard._photoLoad==='function') photoCard._photoLoad(); });
                        const ring=photoMarker.getElement()&&photoMarker.getElement().querySelector('.map-photo-marker-ring');
                        const ringImg=ring&&ring.querySelector('img');
                        if(ring && ringImg && p.thumbnail){
                            const applyThumb=(src)=>{
                                if(!src||!ringImg.isConnected) return;
                                ringImg.src=src;
                                ringImg.classList.add('is-ready');
                                ring.classList.add('is-ready');
                                ring.classList.remove('is-loading');
                            };
                            if(cachedThumb){
                                applyThumb(cachedThumb);
                            } else {
                                // Always start immediately — IO missed Leaflet marker
                                // panes and left amber camera placeholders forever.
                                loadPhotoThumb(p.thumbnail).then(loaded=>{
                                    applyThumb(loaded.src);
                                }).catch(()=>{ if(ring.isConnected) ring.classList.remove('is-loading'); });
                            }
                        }
                        return photoMarker;
                    }
                    function render(features){
                        if(!map||!window.L) return;
                        lastFeatures=Array.isArray(features)?features:[];
                        const L=window.L;
                        const pointOpacity=Math.max(0.15, appearance.opacity/100);
                        const baseRadius=Math.max(2, appearance.pointSize);
                        const routeWidth=Math.max(1, appearance.routeWidth);
                        const heatStrength=Math.max(0.1, appearance.heat/100);
                        layerGroup.clearLayers();
                        const speedOn=lastFeatures.some(f=>f.properties&&f.properties.layer==='speed');
                        const visitCenters=lastFeatures.filter(f=>f.properties&&f.properties.layer==='visit'&&f.geometry).map(f=>L.latLng(f.geometry.coordinates[1],f.geometry.coordinates[0]));
                        const photoFeatures=[];
                        lastFeatures.forEach(feature=>{
                            const p=feature.properties||{};
                            if(!feature.geometry||!feature.geometry.coordinates) return;
                            if(p.layer==='photo_cluster'){ photoFeatures.push(feature); return; }
                            if(p.layer==='trip'){
                                const isFocus=!!p.id&&focusTripId===p.id, isSel=!!p.id&&selTrips.has(p.id);
                                if(speedOn&&!isFocus&&!isSel) return;
                                const popup=popupHtml(p.title||p.boundary_reason||'Trip',['distance: '+Math.round(p.distance_m||0)+' m']);
                                const hl=isFocus?'#f43f5e':(isSel?'#facc15':null);
                                if(hl) L.geoJSON(feature,{pane:'routesGlow',style:{color:hl,weight:routeWidth*6,opacity:0.38,lineCap:'round',lineJoin:'round'},interactive:false}).addTo(layerGroup);
                                else L.geoJSON(feature,{pane:'routesGlow',style:{color:'#38bdf8',weight:routeWidth*3.2,opacity:0.16,lineCap:'round',lineJoin:'round'},interactive:false}).addTo(layerGroup);
                                const tripShape=L.geoJSON(feature,{pane:'routes',style:{color:hl||'#60a5fa',weight:hl?routeWidth+2:routeWidth,opacity:0.95,lineCap:'round',lineJoin:'round'}}).bindPopup(popup).addTo(layerGroup);
                                // Clicking the drawn route selects the trip, glides the camera to it
                                // and keeps its popup so the map and the trips panel stay in sync.
                                tripShape.on('click',()=>{
                                    if(p.id==null) return;
                                    const center=tripShape.getBounds().getCenter();
                                    focusTripId=p.id;
                                    const list=document.getElementById('ma-trips-list')||tripsList;
                                    if(list) list.querySelectorAll('.trip-item').forEach(el=>el.classList.toggle('is-focused',el.dataset.tripId===String(p.id)));
                                    render(lastFeatures);
                                    flyToTrip(p.id);
                                    if(map&&center) map.openPopup(popup,center,{maxWidth:300});
                                });
                            } else if(p.layer==='smooth'){
                                L.geoJSON(feature,{pane:'routesGlow',style:{color:'#34d399',weight:routeWidth*3.2,opacity:0.18,lineCap:'round',lineJoin:'round'},interactive:false}).addTo(layerGroup);
                                L.geoJSON(feature,{pane:'routes',style:{color:'#34d399',weight:routeWidth,opacity:0.95,lineCap:'round',lineJoin:'round'}})
                                    .bindPopup(popupHtml(tr('map_smooth_title','Smoothed route'),[(p.point_count||0)+' '+tr('points_unit','points'),p.started_at||'']))
                                    .addTo(layerGroup);
                            } else if(p.layer==='matched'){
                                L.geoJSON(feature,{pane:'routesGlow',style:{color:'#a78bfa',weight:routeWidth*3.2,opacity:0.16,lineCap:'round',lineJoin:'round'},interactive:false}).addTo(layerGroup);
                                L.geoJSON(feature,{pane:'routes',style:{color:'#a78bfa',weight:routeWidth,opacity:0.85,dashArray:'6 5',lineCap:'round',lineJoin:'round'}}).bindPopup(popupHtml(tr('map_matched_title','Improved display')+' · '+Math.round((p.confidence||0)*100)+'%',[p.explanation||''])).addTo(layerGroup);
                            } else if(p.layer==='speed'){
                                const sc=['#38bdf8','#22c55e','#eab308','#f97316','#ef4444','#a855f7'][p.bucket]||'#60a5fa';
                                L.geoJSON(feature,{pane:'routesGlow',style:{color:sc,weight:routeWidth*3.2,opacity:0.16,lineCap:'round',lineJoin:'round'},interactive:false}).addTo(layerGroup);
                                L.geoJSON(feature,{pane:'routes',style:{color:sc,weight:routeWidth,opacity:0.95,lineCap:'round',lineJoin:'round'}}).bindPopup(popupHtml(Math.round(p.kmh||0)+' km/h',[p.started_at||''])).addTo(layerGroup);
                            } else if(p.layer==='place'){
                                if(p.radius_m) L.circle([feature.geometry.coordinates[1],feature.geometry.coordinates[0]],{pane:'mapBackdrop',radius:p.radius_m,color:p.color||'#22c55e',weight:1,opacity:0.6,fillColor:p.color||'#22c55e',fillOpacity:0.10}).addTo(layerGroup);
                                addPoint(feature,{radius:baseRadius+2,color:p.color||'#22c55e',fillColor:p.color||'#22c55e',fillOpacity:pointOpacity},p.name||'place',[p.tag]);
                            } else if(p.layer==='stop'){
                                const sc=L.latLng(feature.geometry.coordinates[1],feature.geometry.coordinates[0]);
                                if(visitCenters.some(c=>c.distanceTo(sc)<80)) return;
                                addPoint(feature,{radius:baseRadius-1,color:'#f59e0b',fillColor:'#f59e0b',fillOpacity:pointOpacity},p.name||'Stop',[p.started_at||'']);
                            } else if(p.layer==='point_cluster'){
                                addCluster(feature, p.count||1);
                            } else if(p.layer==='visit'){
                                const vc=VISIT_COLORS[p.detection_status]||VISIT_COLORS.unidentified;
                                const [vlon,vlat]=feature.geometry.coordinates;
                                let vm;
                                if(p.photo_url){
                                    const size=Math.max(28, baseRadius*4+16);
                                    const photoSrc=String(p.photo_url).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
                                    // Visit rings stay sky-blue; Immich photo rings stay amber (#f59e0b).
                                    vm=L.marker([vlat,vlon],{keyboard:false,icon:L.divIcon({className:'map-place-photo',html:'<span class="map-place-photo-ring" style="border-color:#0ea5e9"><img src="'+photoSrc+'" alt="" loading="lazy" decoding="async"></span>',iconSize:[size,size],iconAnchor:[size/2,size/2]})}).addTo(layerGroup);
                                } else {
                                    vm=addPoint(feature,{radius:baseRadius+(p.detection_status==='confirmed'||p.detection_status==='manual'?2.5:1.5),color:'#0ea5e9',fillColor:vc,fillOpacity:Math.max(pointOpacity,0.85),dashArray:p.detection_status==='provisional'?'3 2':null},'',[]);
                                }
                                vm.unbindPopup(); vm.bindPopup(()=>visitPopup(p),{maxWidth:420,className:'visit-md3-leaflet',closeButton:false});
                                vm.on('contextmenu',ev=>{ window.L.DomEvent.stop(ev); openMapMenu(ev.latlng,ev.originalEvent,p); });
                            } else if(p.layer==='point'){
                                const flagged=p.quality_state==='flagged';
                                addPoint(feature,{radius:Math.max(2,baseRadius-2),color:flagged?'#ef4444':'#0ea5e9',fillColor:flagged?'#ef4444':'#0ea5e9',fillOpacity:pointOpacity},p.source_at||'',[p.accuracy_m?'±'+p.accuracy_m+' m':'']);
                            } else if(p.layer==='heatmap'){
                                const intensity=Math.max(0,Math.min(1,(p.intensity||0)*heatStrength));
                                if(/LineString/.test(feature.geometry.type)){
                                    const color=intensity>0.72?'#ef4444':(intensity>0.42?'#f97316':(intensity>0.18?'#facc15':'#38bdf8'));
                                    L.geoJSON(feature,{pane:'routesGlow',style:{color:color,weight:Math.max(4,routeWidth*4),opacity:0.10+0.16*intensity,lineCap:'round',lineJoin:'round'},interactive:false}).addTo(layerGroup);
                                    L.geoJSON(feature,{pane:'routes',style:{color:color,weight:Math.max(1.5,routeWidth*0.85),opacity:0.52+0.44*intensity,lineCap:'round',lineJoin:'round'}}).bindPopup(popupHtml((p.weight||1)+'×',[tr('map_heat_repeats','Repeated route')])).addTo(layerGroup);
                                } else {
                                    const pointIntensity=(p.intensity||0)*heatStrength;
                                    addPoint(feature,{pane:'mapBackdrop',radius:Math.max(8,baseRadius*2.4),stroke:false,fillColor: p.quality_state==='flagged'?'#ef4444':'#f97316',fillOpacity:Math.min(0.85,0.10+0.7*pointIntensity)},String(p.weight||0),[]);
                                }
                            } else if(p.layer==='explored'){
                                addPoint(feature,{pane:'mapBackdrop',radius:Math.max(6,baseRadius*1.8),stroke:false,fillColor:'#10b981',fillOpacity:Math.min(0.6,0.12+0.35*heatStrength)},String(p.visits||0),[]);
                            }
                        });
                        clusterPhotoFeatures(photoFeatures).forEach(f=>addPhotoMarker(f, baseRadius));
                    }
                    function bboxQuery(){
                        if(!map) return '';
                        const b=map.getBounds();
                        const span=b.getEast()-b.getWest();
                        if(!isFinite(span)||span<=0||span>85||(b.getNorth()-b.getSouth())>85) return '';
                        return [b.getWest(),b.getSouth(),b.getEast(),b.getNorth()].map(v=>v.toFixed(5)).join(',');
                    }
                    function dateLocale(){ return (typeof currentLocale!=='undefined' && currentLocale) ? currentLocale : 'en-US'; }
                    function parseYMD(value){ const m=/^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value||'')); return m?new Date(Number(m[1]),Number(m[2])-1,Number(m[3])):null; }
                    function toYMD(date){ return date.getFullYear()+'-'+String(date.getMonth()+1).padStart(2,'0')+'-'+String(date.getDate()).padStart(2,'0'); }
                    function initDatePickers(){
                        const weekdays=(window.translations&&Array.isArray(translations.days_of_week)&&translations.days_of_week.length===7)?translations.days_of_week:['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
                        const todayLabel=(window.translations&&translations.map_today)||'Today';
                        document.querySelectorAll('.dp-field:not([data-dp-new])').forEach(field=>{
                            const native=field.querySelector('.dp-native'),
                                  trigger=field.querySelector('.dp-trigger'),
                                  valueEl=field.querySelector('.dp-value'),
                                  pop=field.querySelector('.dp-popover');
                            if(!native||!trigger||!pop) return;
                            let view=parseYMD(native.value)||new Date();
                            const syncValue=()=>{
                                const d=parseYMD(native.value);
                                valueEl.textContent=d?d.toLocaleDateString(dateLocale(),{day:'numeric',month:'short',year:'numeric'}):'—';
                            };
                            const render=()=>{
                                view=new Date(view.getFullYear(),view.getMonth(),1);
                                const year=view.getFullYear(), month=view.getMonth(),
                                      first=new Date(year,month,1), offset=(first.getDay()+6)%7,
                                      days=new Date(year,month+1,0).getDate(),
                                      selected=native.value, today=toYMD(new Date());
                                let html=`<div class="dp-head"><button type="button" class="dp-nav" data-dp-nav="-1" aria-label="${t.prevMonth||'Previous month'}"><i class="fas fa-chevron-left"></i></button><span class="dp-title">${first.toLocaleDateString(dateLocale(),{month:'long',year:'numeric'})}</span><button type="button" class="dp-nav" data-dp-nav="1" aria-label="${t.nextMonth||'Next month'}"><i class="fas fa-chevron-right"></i></button></div>`;
                                html+='<div class="dp-weekdays">'+weekdays.map(d=>`<span>${d.slice(0,2)}</span>`).join('')+'</div><div class="dp-grid">';
                                for(let i=0;i<offset;i++) html+='<span class="dp-empty"></span>';
                                for(let day=1;day<=days;day++){
                                    const dateStr=toYMD(new Date(year,month,day));
                                    html+=`<button type="button" class="dp-day${dateStr===selected?' is-selected':''}${dateStr===today?' is-today':''}" data-dp-date="${dateStr}">${day}</button>`;
                                }
                                html+='</div><div class="dp-foot"><button type="button" class="dp-today" data-dp-today>'+todayLabel+'</button></div>';
                                pop.innerHTML=html;
                            };
                            const close=()=>{
                                pop.hidden=true;
                                trigger.setAttribute('aria-expanded','false');
                                if(pop.parentElement!==field) field.appendChild(pop);
                                pop.style.left=''; pop.style.top=''; pop.style.width=''; pop.style.zIndex='';
                            };
                            const open=()=>{
                                document.querySelectorAll('.dp-popover').forEach(p=>{
                                    p.hidden=true;
                                    const host=p.closest('.dp-field');
                                    if(host&&p.parentElement!==host) host.appendChild(p);
                                });
                                view=parseYMD(native.value)||new Date();
                                render();
                                document.body.appendChild(pop);
                                pop.hidden=false;
                                trigger.setAttribute('aria-expanded','true');
                                const rect=trigger.getBoundingClientRect(),
                                      width=Math.min(300, window.innerWidth-16),
                                      height=pop.offsetHeight||330;
                                let left=Math.min(Math.max(8,rect.left), window.innerWidth-width-8);
                                let top=rect.bottom+8;
                                if(top+height>window.innerHeight-8) top=Math.max(8,rect.top-height-8);
                                pop.style.position='fixed';
                                pop.style.width=width+'px';
                                pop.style.left=left+'px';
                                pop.style.top=top+'px';
                                pop.style.zIndex='200000';
                            };
                            trigger.addEventListener('click',event=>{
                                event.stopPropagation();
                                if(pop.hidden) open(); else close();
                            });
                            pop.addEventListener('click',event=>{
                                // Keep popover clicks away from the document click handler:
                                // re-rendering on month change detaches the target, which
                                // made the outside-click check close the calendar.
                                event.stopPropagation();
                                const nav=event.target.closest('[data-dp-nav]');
                                if(nav){ view.setMonth(view.getMonth()+Number(nav.getAttribute('data-dp-nav'))); render(); return; }
                                const day=event.target.closest('[data-dp-date]');
                                if(day){
                                    native.value=day.getAttribute('data-dp-date');
                                    syncValue();
                                    close();
                                    native.dispatchEvent(new Event('change',{bubbles:true}));
                                    return;
                                }
                                if(event.target.closest('[data-dp-today]')){
                                    native.value=toYMD(new Date());
                                    syncValue();
                                    close();
                                    native.dispatchEvent(new Event('change',{bubbles:true}));
                                }
                            });
                            document.addEventListener('click',event=>{
                                if(pop.hidden) return;
                                if(!pop.contains(event.target)&&!field.contains(event.target)) close();
                            });
                            document.addEventListener('keydown',event=>{ if(event.key==='Escape'&&!pop.hidden) close(); });
                            native.addEventListener('change',syncValue);
                            syncValue();
                        });
                    }
                    function shiftRange(days){
                        const from=parseYMD(fromInput.value)||new Date(),
                              to=parseYMD(toInput.value)||new Date();
                        from.setDate(from.getDate()+days);
                        to.setDate(to.getDate()+days);
                        fromInput.value=toYMD(from);
                        toInput.value=toYMD(to);
                        capToToday();
                        fromInput.dispatchEvent(new Event('change',{bubbles:true}));
                        toInput.dispatchEvent(new Event('change',{bubbles:true}));
                    }
                    const tr=(key,fallback)=>(window.translations&&translations[key])||fallback;
                    const tripApi='/apps/steps/api/session_gateway.php?endpoint=geolocation_trips';
                    function tripErrorText(error){
                        const messages={
                            invalid_range:tr('map_trip_err_range','Choose a valid date range.'),
                            trip_needs_points:tr('map_trip_err_points','No accepted points in this selection.'),
                            trip_not_found:tr('map_trip_err_missing','Trip not found.'),
                            split_failed:tr('map_trip_err_split','Could not split this trip.'),
                            split_outside_trip:tr('map_trip_err_split','Could not split this trip.'),
                            merge_failed:tr('map_trip_err_merge','Could not merge these trips.'),
                        };
                        return messages[error]||error||tr('map_trip_err','Something went wrong.');
                    }
                    async function tripCommand(payload){
                        const res=await fetch(tripApi,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
                        const data=await res.json();
                        if(!res.ok||!data.ok) throw new Error((data&&data.error)||'trip_command_failed');
                        return data;
                    }
                    function reportTripError(error){
                        const message=tripErrorText(error&&error.message?error.message:error);
                        if(typeof window.showAlert==='function') window.showAlert(message);
                        else showError(message);
                    }
                    async function confirmTripAction(message){
                        if(typeof window.showAlert==='function'){
                            try{ return await window.showAlert(message,true); }catch(e){ return false; }
                        }
                        return window.confirm(message);
                    }
                    function tripMeta(trip){
                        const format=value=>{ const d=new Date(value); return isNaN(d)?'':d.toLocaleString(dateLocale(),{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}); };
                        const km=(Number(trip.distance_m||0)/1000).toFixed(1);
                        return format(trip.started_at)+' → '+format(trip.ended_at)+' · '+km+' km'+(trip.state==='archived'?' · '+tr('map_trip_archived','archived'):'');
                    }
                    function updateMergeButton(){
                        const selected=tripsList.querySelectorAll('.trip-pick:checked').length;
                        if(tripMergeBtn) tripMergeBtn.disabled=selected<2;
                    }
                    // Trip features carry the id as string in some payloads, so compare loosely.
                    function findTripFeature(tripId){
                        const id=String(tripId);
                        return (lastFeatures||[]).find(f=>f.properties&&f.properties.layer==='trip'&&String(f.properties.id)===id)||null;
                    }
                    function flyToTrip(tripId){
                        if(!map||!window.L) return false;
                        const feat=findTripFeature(tripId);
                        if(!feat) return false;
                        try{
                            const bounds=window.L.geoJSON(feat).getBounds();
                            if(!bounds||!bounds.isValid()) return false;
                            const opts={padding:[60,60],maxZoom:16,duration:0.9,easeLinearity:0.22};
                            if(typeof map.flyToBounds==='function') map.flyToBounds(bounds,opts);
                            else map.fitBounds(bounds,opts);
                            return true;
                        }catch(e){ return false; }
                    }
                    function renderTrips(){
                        tripsList.innerHTML='';
                        if(!tripsCache.length){
                            const empty=document.createElement('span');
                            empty.className='trip-empty';
                            empty.textContent=tr('map_trip_none','No trips in this range.');
                            tripsList.appendChild(empty);
                            updateMergeButton();
                            return;
                        }
                        tripsCache.forEach(trip=>{
                            const item=document.createElement('div');
                            item.className='trip-item'+(editingTripId===trip.id?' is-editing':'')+(focusTripId===trip.id?' is-focused':'');
                            item.dataset.tripId=trip.id;
                            const pick=document.createElement('input');
                            pick.type='checkbox'; pick.className='trip-pick'; pick.value=trip.id;
                            pick.setAttribute('aria-label',tr('map_trip_select','Select trip'));
                            pick.checked=selTrips.has(trip.id);
                            pick.addEventListener('change',()=>{ if(pick.checked) selTrips.add(trip.id); else selTrips.delete(trip.id); updateMergeButton(); render(lastFeatures); });
                            const open=document.createElement('button');
                            open.type='button'; open.className='trip-open';
                            const title=document.createElement('span'); title.className='trip-title';
                            title.textContent=trip.title||tr('map_trip_untitled','Untitled trip');
                            const meta=document.createElement('span'); meta.className='trip-meta'; meta.textContent=tripMeta(trip);
                            open.append(title,meta);
                            open.addEventListener('click',()=>{
                                focusTripId=focusTripId===trip.id?null:trip.id;
                                tripsList.querySelectorAll('.trip-item').forEach(el=>el.classList.toggle('is-focused',el.dataset.tripId===focusTripId));
                                render(lastFeatures);
                                if(focusTripId) flyToTrip(trip.id);
                                openTripEditor(trip);
                            });
                            const actions=document.createElement('div'); actions.className='trip-actions';
                            const tripAction=(icon,label,handler)=>{
                                const button=document.createElement('button');
                                button.type='button'; button.className='trip-action'; button.title=label;
                                button.setAttribute('aria-label',label);
                                button.innerHTML='<i class="fas '+icon+'" aria-hidden="true"></i>';
                                button.addEventListener('click',handler);
                                return button;
                            };
                            actions.append(
                                tripAction('fa-scissors',tr('map_trip_split','Split'),()=>splitTrip(trip)),
                                tripAction('fa-box-archive',tr('map_trip_archive','Archive'),()=>archiveTrip(trip,false)),
                                tripAction('fa-trash-can',tr('map_trip_delete','Delete'),()=>archiveTrip(trip,true))
                            );
                            item.append(pick,open,actions);
                            tripsList.appendChild(item);
                        });
                        updateMergeButton();
                    }
                    let tracksSyncKey='';
                    async function syncTracks(force){
                        const key=fromInput.value+'|'+toInput.value;
                        if(!force&&key===tracksSyncKey) return;
                        tracksSyncKey=key;
                        await fetch(tripApi,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'from_points',from:fromInput.value+'T00:00:00Z',to:toInput.value+'T23:59:59Z'})}).catch(()=>{});
                    }
                    async function loadTrips(){
                        const seq=++tripsLoadSeq;
                        window.dispatchEvent(new Event('steps-visits-refresh'));
                        try{
                            await syncTracks(false);
                            const params=new URLSearchParams({
                                from:fromInput.value+'T00:00:00Z',
                                to:toInput.value+'T23:59:59Z',
                                limit:'50',
                            });
                            const res=await fetch(tripApi+'&'+params.toString(),{credentials:'same-origin'});
                            const data=await res.json();
                            if(!res.ok||!data.ok) throw new Error((data&&data.error)||'trip_list_failed');
                            if(seq!==tripsLoadSeq) return;
                            tripsCache=Array.isArray(data.trips)?data.trips:[];
                            renderTrips();
                        }catch(e){
                            if(seq!==tripsLoadSeq) return;
                            tripsList.innerHTML='';
                            const empty=document.createElement('span');
                            empty.className='trip-empty';
                            empty.textContent=tr('map_trip_err_load','Could not load trips.');
                            tripsList.appendChild(empty);
                        }
                    }
                    function isoToInput(value){
                        const d=new Date(value);
                        if(isNaN(d)) return '';
                        const pad=n=>String(n).padStart(2,'0');
                        return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':'+pad(d.getMinutes());
                    }
                    function inputToIso(value){
                        const d=new Date(value);
                        return isNaN(d)?null:d.toISOString();
                    }
                    function openTripEditor(trip){
                        if(!trip) return;
                        editingTripId=trip.id;
                        tripEditor.dataset.id=trip.id;
                        document.getElementById('ma-trip-title').value=trip.title||'';
                        document.getElementById('ma-trip-notes').value=trip.notes||'';
                        document.getElementById('ma-trip-start').value=isoToInput(trip.started_at);
                        document.getElementById('ma-trip-end').value=isoToInput(trip.ended_at);
                        document.getElementById('ma-trip-visible').checked=trip.geometry_visible!==false;
                        tripEditor.hidden=false;
                        renderTrips();
                        if(window.innerWidth<=820) tripEditor.scrollIntoView({block:'nearest',behavior:'smooth'});
                    }
                    function closeTripEditor(){
                        editingTripId=null;
                        tripEditor.hidden=true;
                        renderTrips();
                    }
                    async function saveTrip(){
                        if(!editingTripId) return;
                        const startedAt=inputToIso(document.getElementById('ma-trip-start').value);
                        const endedAt=inputToIso(document.getElementById('ma-trip-end').value);
                        if(!startedAt||!endedAt||new Date(startedAt)>=new Date(endedAt)){
                            reportTripError({message:'invalid_range'});
                            return;
                        }
                        try{
                            await tripCommand({
                                action:'update',
                                id:editingTripId,
                                title:document.getElementById('ma-trip-title').value,
                                notes:document.getElementById('ma-trip-notes').value,
                                geometry_visible:document.getElementById('ma-trip-visible').checked,
                                started_at:startedAt,
                                ended_at:endedAt,
                            });
                            closeTripEditor();
                            await loadTrips();
                            await fetchViewport();
                        }catch(e){ reportTripError(e); }
                    }
                    async function createTripFromRange(){
                        try{
                            await syncTracks(true);
                            await loadTrips();
                            await fetchViewport();
                        }catch(e){ reportTripError(e); }
                    }
                    async function mergeSelectedTrips(){
                        const ids=Array.from(tripsList.querySelectorAll('.trip-pick:checked')).map(cb=>cb.value);
                        if(ids.length<2) return;
                        if(!await confirmTripAction(tr('map_trip_confirm_merge','Merge the selected trips into one?'))) return;
                        try{
                            await tripCommand({action:'merge',ids});
                            closeTripEditor();
                            await loadTrips();
                            await fetchViewport();
                        }catch(e){ reportTripError(e); }
                    }
                    async function splitTrip(trip){
                        if(!await confirmTripAction(tr('map_trip_confirm_split','Split this trip at its midpoint?'))) return;
                        const at=new Date((new Date(trip.started_at).getTime()+new Date(trip.ended_at).getTime())/2).toISOString();
                        try{
                            await tripCommand({action:'split',id:trip.id,at});
                            closeTripEditor();
                            await loadTrips();
                            await fetchViewport();
                        }catch(e){ reportTripError(e); }
                    }
                    async function archiveTrip(trip, remove){
                        const message=remove
                            ? tr('map_trip_confirm_delete','Delete this trip? The location history is kept.')
                            : tr('map_trip_confirm_archive','Archive this trip?');
                        if(!await confirmTripAction(message)) return;
                        try{
                            await tripCommand({action:remove?'delete':'archive',id:trip.id});
                            if(editingTripId===trip.id) closeTripEditor();
                            await loadTrips();
                            await fetchViewport();
                        }catch(e){ reportTripError(e); }
                    }
                    async function recalculateTrip(){
                        if(!editingTripId) return;
                        try{
                            await tripCommand({action:'recalculate',id:editingTripId});
                            await loadTrips();
                            const updated=tripsCache.find(trip=>trip.id===editingTripId);
                            if(updated) openTripEditor(updated);
                            await fetchViewport();
                        }catch(e){ reportTripError(e); }
                    }
                    function initTripManager(){
                        document.getElementById('ma-trip-new')?.addEventListener('click',createTripFromRange);
                        document.getElementById('ma-trip-merge')?.addEventListener('click',mergeSelectedTrips);
                        document.getElementById('ma-trip-close')?.addEventListener('click',closeTripEditor);
                        document.getElementById('ma-trip-save')?.addEventListener('click',saveTrip);
                        document.getElementById('ma-trip-recalc')?.addEventListener('click',recalculateTrip);
                        updateMergeButton();
                    }
                    function chunkKey(params){
                        return ['v1',fromInput.value,toInput.value,params.get('zoom')||'',params.get('layers')||'',
                            params.get('family_email')||'',params.get('bbox')||''].join('|');
                    }
                    function cacheChunk(key,data,bytes){
                        if(chunkCache.size>=24){
                            const oldest=chunkCache.keys().next().value;
                            if(oldest) chunkCache.delete(oldest);
                        }
                        chunkCache.set(key,{data,at:Date.now(),bytes:bytes||0});
                    }
                    // Counts the features that are actually drawn. The server sends
                    // per-layer counts too, but a layer that was not queried (family
                    // scope, disabled filter) reports nothing, which used to leave the
                    // map summary stuck at zero next to visible data.
                    function countLayers(features){
                        const c={trips:0,stops:0,places:0,visits:0,points:0};
                        let stopPoints=0, clusters=0, pointTotal=0;
                        (Array.isArray(features)?features:[]).forEach(f=>{
                            const p=(f&&f.properties)||{}, layer=p.layer||'';
                            if(layer==='trip') c.trips+=1;
                            else if(layer==='stop') stopPoints+=1;
                            else if(layer==='place') c.places+=1;
                            else if(layer==='visit') c.visits+=1;
                            else if(layer==='point_cluster'){ clusters+=1; pointTotal+=Number(p.count)||1; }
                            else if(layer==='point') pointTotal+=1;
                        });
                        c.stops=stopPoints+clusters;
                        c.points=pointTotal||clusters;
                        return c;
                    }
                    function applyViewportData(data){
                        providers=data.providers||providers;
                        if(!provider) provider=(data.settings&&data.settings.provider)||(providers.esri_dark?'esri_dark':'osm');
                        if(!providerSel.options.length) renderProviders();
                        render(data.geojson.features);
                        const server=Object.assign({},(data.meta&&data.meta.counts)||{});
                        // Points inside a smoothed zone are not drawn, so they must not
                        // inflate the summary either.
                        const hiddenPoints=Number(server.smoothed_points_hidden)||0;
                        if(hiddenPoints>0&&Number(server.points)>0) server.points=Math.max(0,Number(server.points)-hiddenPoints);
                        const derived=countLayers(data.geojson.features);
                        const count=key=>{
                            const fromServer=Number(server[key]);
                            return fromServer>0?fromServer:(derived[key]||0);
                        };
                        const serverStops=Number(server.stops)||0, serverClusters=Number(server.point_clusters)||0;
                        const stops=(serverStops+serverClusters)>0?serverStops+serverClusters:derived.stops;
                        const placesCount=(count('places')||0)+(count('visits')||0);
                        summary.textContent=t.summary.replace('{trips}',count('trips')).replace('{stops}',stops).replace('{places}',count('places')).replace('{visits}',count('visits')).replace('{points}',count('points'));
                        if(!data.geojson.features.length) summary.textContent=t.empty;
                    }
                    function clearPrefetchQueue(abortRequests){
                        while(prefetchQueue.length) prefetchQueue.shift();
                        if(prefetchTimer){ clearTimeout(prefetchTimer); prefetchTimer=null; }
                        if(abortRequests) cancelPrefetch();
                    }
                    function cancelPrefetch(){
                        clearPrefetchQueue(false);
                        if(prefetchAbort){
                            try{ prefetchAbort.abort(); }catch(e){}
                            prefetchAbort=null;
                            prefetchActive=0;
                            window.stepsMapPerf.cancelled+=1;
                        }
                    }
                    function prefetchAllowed(){
                        if(!mapConfig.prefetch||!mapStarted||!map||busy||document.hidden||navigator.onLine===false) return false;
                        if(prefetchMode==='off') return false;
                        const c=navigator.connection;
                        if(c&&c.saveData) return false;
                        if(c&&/^(slow-2g|2g|3g)$/.test(String(c.effectiveType||''))) return false;
                        if(prefetchMode==='wifi'&&(!c||c.type!=='wifi')) return false;
                        const now=Date.now();
                        if(now-prefetchWindowStart>60000){ prefetchWindowStart=now; prefetchRequests=0; prefetchBytes=0; }
                        const maxReq=prefetchMode==='aggressive'?12:5;
                        const maxBytes=prefetchMode==='aggressive'?2097152:786432;
                        return prefetchRequests<maxReq&&prefetchBytes<maxBytes;
                    }
                    function shiftedBounds(dx,dy){
                        if(!map) return null;
                        const b=map.getBounds(), west=b.getWest(), east=b.getEast(), south=b.getSouth(), north=b.getNorth();
                        const w=east-west, h=north-south;
                        const nx=Math.max(-179.9,Math.min(179.9,west+dx*w)), ny=Math.max(-89.9,Math.min(89.9,south+dy*h));
                        return [nx,ny,Math.max(-179.9,Math.min(179.9,nx+w)),Math.max(-89.9,Math.min(89.9,ny+h))].map(v=>v.toFixed(5)).join(',');
                    }
                    function enqueuePrefetch(dir,ctx){
                        if(!prefetchAllowed()||prefetchQueue.length>=2) return;
                        if(!dir||Math.abs(dir.x)+Math.abs(dir.y)<0.00001) return;
                        const ax=Math.abs(dir.x), ay=Math.abs(dir.y);
                        const sx=ax>0.00001?Math.sign(dir.x):0, sy=ay>0.00001?Math.sign(dir.y):0;
                        // Slow pans get one immediate neighbouring viewport. Fast pans
                        // get only the leading edge, never a fan-out over crossed chunks.
                        const look=0.6;
                        const targets=[shiftedBounds(sx*look,sy*look)];
                        if(prefetchMode==='aggressive'){
                            targets.push(shiftedBounds(sx*look*2,sy*look*2));
                            if(ax>0.00001&&ay>0.00001) targets.push(shiftedBounds(sx*look,sy*look*2));
                        }
                        targets.filter(Boolean).forEach(bbox=>{
                            const p=ctx.params(); p.set('bbox',bbox);
                            const key=chunkKey(p);
                            if(chunkCache.has(key)) return;
                            if(!prefetchQueue.some(q=>q.key===key)) prefetchQueue.push({key,params:p});
                        });
                        pumpPrefetch();
                    }
                    function pumpPrefetch(){
                        if(prefetchActive||!prefetchQueue.length||!prefetchAllowed()) return;
                        const item=prefetchQueue.shift();
                        const controller=new AbortController();
                        prefetchAbort=controller;
                        prefetchActive=1; prefetchRequests+=1; window.stepsMapPerf.prefetch+=1;
                        const timeout=setTimeout(()=>controller.abort(),10000);
                        const started=performance.now();
                        const prefetchParams=new URLSearchParams(item.params); prefetchParams.set('prefetch','1');
                        fetch('/apps/steps/api/session_gateway.php?endpoint=geolocation_map&'+prefetchParams.toString(),{
                            credentials:'same-origin',signal:controller.signal,priority:'low',cache:'no-store',headers:{'X-Steps-Prefetch':'1'}
                        }).then(res=>res.ok?res.json():Promise.reject(new Error('prefetch_http'))).then(data=>{
                            if(data&&data.ok){
                                const bytes=JSON.stringify(data).length;
                                prefetchBytes+=bytes; window.stepsMapPerf.prefetchBytes+=bytes;
                                cacheChunk(item.key,data,bytes);
                                const entry=chunkCache.get(item.key);
                                if(entry&&data.meta&&data.meta.chunk&&data.meta.chunk.ttl) entry.ttl=data.meta.chunk.ttl*1000;
                            }
                        }).catch((e)=>{
                            if(!(e&&e.name==='AbortError')) window.stepsMapPerf.errors+=1;
                        }).finally(()=>{
                            clearTimeout(timeout);
                            if(prefetchAbort===controller){
                                prefetchAbort=null; prefetchActive=0;
                                if(performance.now()-started<12000) pumpPrefetch();
                            }
                        });
                    }
                    function schedulePrefetch(dir,ctx){
                        clearTimeout(prefetchTimer);
                        prefetchTimer=setTimeout(()=>{ prefetchTimer=null; enqueuePrefetch(dir,ctx); },dir?450:900);
                    }
                    async function fetchViewport(opts){
                        if(!mapStarted||!map||!mapHost||mapHost.hidden) return;
                        cancelPrefetch();
                        const seq=++viewportSeq;
                        if(viewportAbort) viewportAbort.abort();
                        viewportAbort=new AbortController();
                        busy=true;
                        summary.textContent=t.loading; showError('');
                        const makeParams=()=>{
                            const params=new URLSearchParams();
                            const bbox=bboxQuery();
                            if(bbox) params.set('bbox',bbox);
                            params.set('from',fromInput.value+'T00:00:00Z');
                            params.set('to',toInput.value+'T23:59:59Z');
                            params.set('zoom',String(map?Math.round(map.getZoom()):12));
                            params.set('layers',selectedLayers().join(','));
                            const family=document.getElementById('ma-map-family');
                            if(family&&family.value) params.set('family_email',family.value);
                            return params;
                        };
                        const params=makeParams();
                        const key=chunkKey(params), cached=chunkCache.get(key);
                        try{
                            if(cached&&Date.now()-cached.at<(cached.ttl||20000)){
                                window.stepsMapPerf.prefetchHits+=1;
                                applyViewportData(cached.data);
                                schedulePrefetch(opts&&opts.dir,{params:makeParams});
                                return;
                            }
                            // no-store keeps the browser from answering with a body-less 304:
                            // the chunk is already cached in JS, and a revalidation would
                            // silently keep drawing features that the server just changed
                            // (for example raw points replaced by a smoothed zone).
                            const res=await fetch('/apps/steps/api/session_gateway.php?endpoint=geolocation_map&'+params.toString(),{credentials:'same-origin',signal:viewportAbort.signal,cache:'no-store',headers:{'X-Steps-Prefetch':'0'}});
                            if(res.status===304){ return; }
                            const data=await res.json();
                            if(seq!==viewportSeq) return;
                            if(data&&(data.error==='invalid_bbox'||data.error==='invalid_range')){
                                render([]);
                                summary.textContent=t.empty;
                                return;
                            }
                            if(!res.ok||!data.ok) throw new Error((data&&data.error)||t.error);
                            const bytes=JSON.stringify(data).length;
                            window.stepsMapPerf.visible+=1; window.stepsMapPerf.bytes+=bytes;
                            cacheChunk(key,data,bytes);
                            applyViewportData(data);
                            didFit=true;
                            try{ localStorage.setItem('stepsMapView:'+String(currentUser||'u'),JSON.stringify(map.getCenter())+'|'+map.getZoom()); }catch(e){}
                            schedulePrefetch(opts&&opts.dir,{params:makeParams});
                        }catch(e){
                            if(e&&e.name==='AbortError'){ window.stepsMapPerf.cancelled+=1; return; }
                            if(seq!==viewportSeq) return;
                            window.stepsMapPerf.errors+=1;
                            showError((e&&e.message)||t.error); summary.textContent='';
                        }finally{
                            if(seq===viewportSeq) busy=false;
                        }
                    }
                    async function loadMapExtent(){
                        const params=new URLSearchParams({extent:'1',from:fromInput.value+'T00:00:00Z',to:toInput.value+'T23:59:59Z'});
                        const family=document.getElementById('ma-map-family');
                        if(family&&family.value) params.set('family_email',family.value);
                        try{
                            const data=await fetch('/apps/steps/api/session_gateway.php?endpoint=geolocation_map&'+params.toString(),{credentials:'same-origin'}).then(r=>r.json());
                            if(data&&data.ok&&data.bbox&&map) map.fitBounds([[data.bbox.minLat,data.bbox.minLon],[data.bbox.maxLat,data.bbox.maxLon]],{padding:[28,28],animate:false,maxZoom:15});
                        }catch(e){}
                    }
                    async function startMapWorkspace(){
                        if(mapStarted) return mapBootPromise;
                        mapStarted=true;
                        pollPointRevision();
                        mapBootPromise=(async()=>{
                            loadFamilyTargets();
                            try{
                                const [, L]=await Promise.all([loadSettings(), ensureLeaflet()]);
                                makeMap(L);
                                const saved=localStorage.getItem('stepsMapView:'+String(currentUser||'u'));
                                if(saved){ const m=/^\{"lat":([-0-9.]+),"lng":([-0-9.]+)\}\|([0-9.]+)$/.exec(saved); if(m) map.setView([Number(m[1]),Number(m[2])],Number(m[3]),{animate:false}); }
                                else await loadMapExtent();
                            }catch(e){
                                mapStarted=false;
                                mapBootPromise=null;
                                canvas.style.display='none';
                                showError(t.unavailable);
                                return;
                            }
                            await fetchViewport({dir:null});
                            loadTrips();
                            pollLive();
                        })();
                        return mapBootPromise;
                    }
                    async function loadSettings(){
                        try{
                            const res=await fetch('/apps/steps/api/session_gateway.php?endpoint=geolocation_map&settings=1',{credentials:'same-origin'});
                            const data=await res.json();
                            if(data.ok){
                                providers=data.providers||{};
                                provider=(data.settings&&data.settings.provider)||(providers.esri_dark?'esri_dark':'osm');
                                if(!keylessProviders.includes(provider)) provider=providers.esri_dark?'esri_dark':'osm';
                                if(data.config){
                                    mapConfig.prefetch=data.config.prefetch_enabled!==false;
                                    mapConfig.overlays=data.config.overlays_enabled!==false;
                                    if(!mapConfig.prefetch) prefetchMode='off';
                                }
                                const pf=document.getElementById('ma-map-prefetch');
                                if(pf){ pf.value=prefetchMode; pf.disabled=!mapConfig.prefetch; }
                                renderProviders();
                            }
                        }catch(e){ /* defaults stay usable */ }
                    }
                    function saveSettings(){
                        fetch('/apps/steps/api/session_gateway.php?endpoint=geolocation_map&settings=1',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({provider:provider,layers:selectedLayers()})}).catch(()=>{});
                    }
                    refresh.addEventListener('click',fetchViewport);
                    if(resetBtn) resetBtn.addEventListener('click',resetAppearance);
                    const pointsApi='/apps/steps/api/session_gateway.php?endpoint=geolocation_points';
                    // ---- Live position ----------------------------------------
                    // The map is a viewport of recorded history; the live marker is
                    // the newest measurement, refreshed independently of the day
                    // range so the user can see where they are right now.
                    let liveLayer=null, liveMarker=null, liveSeq=0;
                    function clearLiveMarker(){
                        if(liveLayer) liveLayer.clearLayers();
                        liveMarker=null;
                        const badge=document.getElementById('ma-map-live');
                        if(badge) badge.hidden=true;
                    }
                    function renderLiveMarker(point){
                        if(!map||!window.L) return;
                        const L=window.L;
                        const lat=Number(point.latitude), lon=Number(point.longitude);
                        if(!isFinite(lat)||!isFinite(lon)) return;
                        if(!liveLayer) liveLayer=L.layerGroup().addTo(map);
                        liveLayer.clearLayers();
                        const at=new Date(point.source_at);
                        const age=Date.now()-at.getTime();
                        const fresh=age<=120000;
                        const acc=Number(point.accuracy_m);
                        if(acc>0) L.circle([lat,lon],{pane:'mapBackdrop',radius:Math.min(1500,acc),color:'#38bdf8',weight:1,opacity:0.55,fillColor:'#38bdf8',fillOpacity:0.12,interactive:false}).addTo(liveLayer);
                        const bearing=Number(point.bearing_degrees);
                        // FA location-arrow points NE (~45°); offset so 0° = north.
                        const arrow=isFinite(bearing)
                            ?'<i class="fas fa-location-arrow map-live-heading" style="transform:rotate('+(bearing-45)+'deg)"></i>'
                            :'<span class="map-live-core"></span>';
                        liveMarker=L.marker([lat,lon],{keyboard:false,pane:'live',zIndexOffset:1000,icon:L.divIcon({
                            className:'map-live-marker'+(fresh?' is-live':'')+(isFinite(bearing)?' has-heading':''),
                            html:'<span class="map-live-ring"></span><span class="map-live-dot">'+arrow+'</span>',
                            iconSize:[36,36],iconAnchor:[18,18]
                        })}).addTo(liveLayer);
                        liveMarker.bindPopup(popupHtml(
                            fresh?tr('map_live_now','Live location'):tr('map_live_last','Last known location'),
                            [at.toLocaleString(dateLocale(),{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}),(acc>0?'±'+Math.round(acc)+' m':'')]
                        ));
                        const badge=document.getElementById('ma-map-live'), text=document.getElementById('ma-map-live-text');
                        if(badge&&text){
                            badge.hidden=false;
                            badge.classList.toggle('is-stale',!fresh);
                            text.textContent=(fresh?tr('map_live_now','Live location'):tr('map_live_last','Last known location'))+' · '+at.toLocaleTimeString(dateLocale(),{hour:'2-digit',minute:'2-digit'});
                        }
                    }
                    function goToLiveLocation(){
                        if(!map) return;
                        if(liveMarker){
                            const ll=liveMarker.getLatLng();
                            map.flyTo(ll, Math.max(map.getZoom(), 16), {duration:0.55});
                            try{ liveMarker.openPopup(); }catch(_){}
                            return;
                        }
                        pollLive().then(()=>{
                            if(liveMarker){
                                const ll=liveMarker.getLatLng();
                                map.flyTo(ll, Math.max(map.getZoom(), 16), {duration:0.55});
                            }
                        });
                    }
                    let liveWs=null, liveWsTimer=null;
                    function liveWsUrl(ticket){
                        const proto=location.protocol==='https:'?'wss:':'ws:';
                        return proto+'//'+location.host+'/steps-live-ws?ticket='+encodeURIComponent(ticket);
                    }
                    async function ensureLiveWs(){
                        if(liveWs&&(liveWs.readyState===WebSocket.OPEN||liveWs.readyState===WebSocket.CONNECTING)) return;
                        try{
                            const res=await fetch('/apps/steps/api/session_gateway.php?endpoint=geolocation_live',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'ticket',role:'viewer'})});
                            const data=await res.json();
                            if(!res.ok||!data.ok||!data.ticket) return;
                            const ws=new WebSocket(liveWsUrl(data.ticket));
                            liveWs=ws;
                            ws.onmessage=(ev)=>{
                                try{
                                    const msg=JSON.parse(ev.data||'{}');
                                    if(msg.type==='presence'&&Number.isFinite(msg.latitude)&&Number.isFinite(msg.longitude)){
                                        renderLiveMarker({
                                            latitude:msg.latitude,
                                            longitude:msg.longitude,
                                            accuracy_m:msg.accuracy_m,
                                            bearing_degrees:msg.bearing_degrees,
                                            source_at:msg.source_at||new Date().toISOString()
                                        });
                                    }
                                }catch(_){}
                            };
                            ws.onclose=()=>{ if(liveWs===ws) liveWs=null; };
                        }catch(_){}
                    }
                    async function pollLive(){
                        // Live marker is independent of the selected day range: the
                        // phone can be sharing position while the map shows history.
                        if(!mapStarted||!map||!mapHost||mapHost.hidden||document.hidden) return;
                        ensureLiveWs();
                        const seq=++liveSeq;
                        try{
                            const res=await fetch('/apps/steps/api/session_gateway.php?endpoint=geolocation_live',{credentials:'same-origin',cache:'no-store'});
                            const data=await res.json();
                            if(seq!==liveSeq) return;
                            if(res.ok&&data.ok&&data.presence){
                                renderLiveMarker(data.presence);
                                return;
                            }
                        }catch(_){}
                        const params=new URLSearchParams({
                            limit:'1',
                            from:new Date(Date.now()-20*60000).toISOString(),
                            to:new Date(Date.now()+60000).toISOString()
                        });
                        try{
                            const res=await fetch(pointsApi+'&'+params.toString(),{credentials:'same-origin',cache:'no-store'});
                            const data=await res.json();
                            if(seq!==liveSeq) return;
                            if(!res.ok||!data.ok) return;
                            const point=(data.points||[])[0];
                            if(!point){ clearLiveMarker(); return; }
                            renderLiveMarker(point);
                        }catch(e){ /* next poll retries */ }
                    }
                    async function pointsRequest(body){
                        const res=await fetch(pointsApi,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
                        const data=await res.json();
                        if(!res.ok||!data.ok) throw new Error((data&&data.error)||'points_failed');
                        return data;
                    }
                    async function confirmDelete(message){
                        if(typeof window.showAlert==='function'){ try{ return !!(await window.showAlert(message,true)); }catch(e){ return false; } }
                        return window.confirm(message);
                    }
                    const areaBtn=document.getElementById('ma-area-toggle'), areaInfo=document.getElementById('ma-area-info'),
                          areaDel=document.getElementById('ma-area-delete'), areaCancel=document.getElementById('ma-area-cancel'),
                          areaSmooth=document.getElementById('ma-area-smooth'), areaToleranceWrap=document.getElementById('ma-area-tolerance-wrap'),
                          areaTolerance=document.getElementById('ma-area-tolerance');
                    let areaMode=false, areaRect=null, areaStart=null, areaBox=null, areaBound=false;
                    function areaRange(){ return {from:fromInput.value+'T00:00:00Z',to:toInput.value+'T23:59:59Z'}; }
                    function areaClear(){
                        if(areaRect&&map) map.removeLayer(areaRect);
                        areaRect=null; areaStart=null; areaBox=null;
                        [areaInfo,areaDel,areaCancel,areaSmooth,areaToleranceWrap].forEach(el=>{ if(el) el.hidden=true; });
                    }
                    function setAreaMode(on){
                        areaMode=on;
                        areaBtn?.classList.toggle('active',on);
                        if(!on) areaClear();
                        if(!map) return;
                        if(on) map.dragging.disable(); else map.dragging.enable();
                        map.getContainer().style.cursor=on?'crosshair':'';
                        map.getContainer().style.touchAction=on?'none':'';
                        if(on&&!areaBound){
                            areaBound=true;
                            const el=map.getContainer();
                            el.addEventListener('pointerdown',e=>{
                                if(!areaMode||e.button>0) return;
                                areaClear();
                                areaStart=map.mouseEventToLatLng(e);
                                areaRect=window.L.rectangle([areaStart,areaStart],{color:'#ef4444',weight:2,fillOpacity:0.12,interactive:false}).addTo(map);
                                el.setPointerCapture(e.pointerId);
                            });
                            el.addEventListener('pointermove',e=>{
                                if(!areaMode||!areaStart||!areaRect) return;
                                areaRect.setBounds(window.L.latLngBounds(areaStart,map.mouseEventToLatLng(e)));
                            });
                            el.addEventListener('pointerup',async e=>{
                                if(!areaMode||!areaStart||!areaRect) return;
                                const end=map.mouseEventToLatLng(e);
                                const b=window.L.latLngBounds(areaStart,end);
                                areaStart=null;
                                if(Math.abs(map.latLngToContainerPoint(b.getNorthEast()).x-map.latLngToContainerPoint(b.getSouthWest()).x)<8){ areaClear(); return; }
                                areaApply(b);
                            });
                        }
                    }
                    async function areaApply(b){
                        areaBox=[b.getWest(),b.getSouth(),b.getEast(),b.getNorth()];
                        if(!areaRect) areaRect=window.L.rectangle(b,{color:'#ef4444',weight:2,fillOpacity:0.12,interactive:false}).addTo(map);
                        areaRect.setBounds(b);
                        try{
                            const data=await pointsRequest(Object.assign({action:'count',bbox:areaBox},areaRange()));
                            areaInfo.textContent=data.count+' '+tr('points_unit','points');
                            areaInfo.hidden=false; areaDel.hidden=false; areaCancel.hidden=false;
                            areaSmooth.hidden=data.count<2; areaToleranceWrap.hidden=data.count<2;
                            areaDel.innerHTML='<i class="fas fa-trash"></i> '+tr('points_delete','Delete');
                            areaDel.disabled=data.count===0;
                        }catch(err){ showError((err&&err.message)||t.error); }
                    }
                    areaBtn?.addEventListener('click',()=>setAreaMode(!areaMode));

                    const visitsApi='/apps/steps/api/session_gateway.php?endpoint=geolocation_stops';
                    const placesApi='/apps/steps/api/session_gateway.php?endpoint=places';
                    const fmtDur=sec=>{ sec=Math.max(0,Math.round(sec||0)); const h=Math.floor(sec/3600), m=Math.floor(sec%3600/60); return h?h+' h '+m+' min':m+' min'; };
                    const fmtAt=iso=>iso?new Date(iso).toLocaleString([],{dateStyle:'short',timeStyle:'short'}):'';
                    const statusLabel=st=>tr('visit_status_'+st,st||'unidentified');
                    async function visitsRequest(body){
                        const res=await fetch(visitsApi,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
                        const data=await res.json().catch(()=>({}));
                        if(!res.ok||!data.ok) throw new Error((data&&data.error)||'visit_failed');
                        return data;
                    }
                    function statusTone(st){
                        if(st==='confirmed') return 'confirmed';
                        if(st==='provisional') return 'provisional';
                        if(st==='manual') return 'manual';
                        return 'muted';
                    }
                    function categoryHuman(cat){
                        if(!cat) return '';
                        const known={
                            'building=college':'College / Educational Building',
                            'building=school':'School / Educational Building',
                            'building=university':'University',
                            'amenity=university':'University',
                            'amenity=school':'School',
                            'amenity=college':'College',
                            'leisure=pitch':'Sports pitch',
                            'leisure=track':'Sports track',
                            'leisure=park':'Park',
                            'landuse=grass':'Grass / open land',
                            'public_transport=stop_position':'Transit stop',
                            'railway=station':'Railway station',
                            'amenity=restaurant':'Restaurant',
                            'shop=supermarket':'Supermarket'
                        };
                        if(known[cat]) return known[cat];
                        return String(cat).replace(/=/g,' · ').replace(/_/g,' ');
                    }
                    function statusBadge(status){
                        const c=document.createElement('span');
                        c.className='visit-md3-status is-'+statusTone(status);
                        c.textContent=statusLabel(status);
                        return c;
                    }
                    function placeAvatar(photoUrl, status){
                        const av=document.createElement('div');
                        av.className='visit-md3-avatar is-'+statusTone(status);
                        if(photoUrl){
                            const img=document.createElement('img'); img.src=photoUrl; img.alt=''; img.loading='lazy'; av.appendChild(img);
                        } else {
                            av.innerHTML='<span class="material-symbols-rounded" aria-hidden="true">location_on</span>';
                        }
                        return av;
                    }
                    function iconEl(name, cls){
                        const s=document.createElement('span'); s.className='material-symbols-rounded'+(cls?' '+cls:''); s.setAttribute('aria-hidden','true'); s.textContent=name; return s;
                    }
                    function visitPopup(p){
                        const div=document.createElement('div'); div.className='visit-md3-pop';
                        const top=document.createElement('div'); top.className='visit-md3-pop-top';
                        const ident=document.createElement('div'); ident.className='visit-md3-pop-ident';
                        ident.appendChild(placeAvatar(p.photo_url, p.detection_status));
                        const copy=document.createElement('div'); copy.className='min-w-0';
                        const title=document.createElement('h3'); title.className='visit-md3-title'; title.textContent=p.place_name||tr('visit_unidentified','Unidentified place'); copy.appendChild(title);
                        const badges=document.createElement('div'); badges.className='visit-md3-badges';
                        badges.appendChild(statusBadge(p.detection_status));
                        if(p.category){
                            const human=categoryHuman(p.category);
                            if(human && human!==p.category){ const h=document.createElement('span'); h.className='visit-md3-cat-human'; h.textContent=human; badges.appendChild(h); }
                            const raw=document.createElement('span'); raw.className='visit-md3-cat'; raw.textContent=p.category; badges.appendChild(raw);
                        }
                        copy.appendChild(badges); ident.appendChild(copy); top.appendChild(ident);
                        const closeBtn=document.createElement('button'); closeBtn.type='button'; closeBtn.className='modal-close'; closeBtn.setAttribute('aria-label',tr('close','Close'));
                        closeBtn.appendChild(iconEl('close'));
                        closeBtn.addEventListener('click',()=>map.closePopup());
                        top.appendChild(closeBtn); div.appendChild(top);
                        const tele=document.createElement('div'); tele.className='visit-md3-telemetry';
                        const timeRow=document.createElement('div'); timeRow.className='visit-md3-row';
                        const timeLeft=document.createElement('div'); timeLeft.className='visit-md3-row-start';
                        timeLeft.appendChild(iconEl('schedule'));
                        const timeTxt=document.createElement('span'); timeTxt.className='visit-md3-time';
                        timeTxt.textContent=[fmtAt(p.arrived_at),fmtAt(p.departed_at)].filter(Boolean).join(' → ')||'—';
                        timeLeft.appendChild(timeTxt); timeRow.appendChild(timeLeft);
                        const dur=document.createElement('span'); dur.className='visit-md3-pill'; dur.textContent=fmtDur(p.dwell_s); timeRow.appendChild(dur);
                        tele.appendChild(timeRow);
                        if(p.address){
                            const addr=document.createElement('div'); addr.className='visit-md3-row-start';
                            addr.appendChild(iconEl('location_on'));
                            const a=document.createElement('span'); a.textContent=p.address; addr.appendChild(a); tele.appendChild(addr);
                        }
                        if(p.confidence!=null){
                            const pct=Math.max(0,Math.min(100,Math.round(Number(p.confidence)*100)));
                            const conf=document.createElement('div'); conf.className='visit-md3-conf';
                            const confTop=document.createElement('div'); confTop.className='visit-md3-conf-top';
                            const confLabel=document.createElement('span'); confLabel.className='visit-md3-conf-label';
                            confLabel.appendChild(iconEl('verified')); confLabel.appendChild(document.createTextNode(tr('visit_confidence','Detection confidence')));
                            const confVal=document.createElement('span'); confVal.className='visit-md3-conf-val'; confVal.textContent=pct+'%';
                            confTop.appendChild(confLabel); confTop.appendChild(confVal); conf.appendChild(confTop);
                            const track=document.createElement('div'); track.className='visit-md3-conf-track';
                            const fill=document.createElement('div'); fill.className='visit-md3-conf-fill'; fill.style.width=pct+'%'; track.appendChild(fill);
                            conf.appendChild(track); tele.appendChild(conf);
                        }
                        div.appendChild(tele);
                        if(p.photo_attribution){
                            const attr=document.createElement('div'); attr.className='visit-md3-attr';
                            attr.appendChild(iconEl('photo_camera'));
                            const t=document.createElement('span'); t.textContent=p.photo_attribution; attr.appendChild(t); div.appendChild(attr);
                        }
                        const ids=Array.isArray(p.visit_ids)?p.visit_ids:[];
                        if(ids.length){
                            const b=document.createElement('button'); b.type='button'; b.className='visit-md3-edit-btn';
                            b.appendChild(iconEl('edit_location_alt')); b.appendChild(document.createTextNode(tr('visit_edit','Edit place')));
                            b.addEventListener('click',()=>{ map.closePopup(); openVisitEditor(ids[ids.length-1]); });
                            div.appendChild(b);
                        }
                        return div;
                    }
                    function m3Select(options, value, onChange, mono){
                        const wrap=document.createElement('div'); wrap.className='m3-select';
                        const trigger=document.createElement('button'); trigger.type='button'; trigger.className='m3-select-trigger';
                        const valueEl=document.createElement('span'); valueEl.className='m3-select-value'+(mono?' font-mono':'');
                        const chev=iconEl('expand_more','m3-select-chevron');
                        trigger.appendChild(valueEl); trigger.appendChild(chev); wrap.appendChild(trigger);
                        const menu=document.createElement('div'); menu.className='m3-select-menu'; wrap.appendChild(menu);
                        let current=value;
                        const render=()=>{
                            const opt=options.find(o=>o.value===current)||options[0]||{value:'',label:''};
                            current=opt.value; valueEl.textContent=opt.label;
                            menu.textContent='';
                            options.forEach(o=>{
                                const row=document.createElement('div'); row.className='m3-select-option'+(o.value===current?' selected':'');
                                const lab=document.createElement('span'); if(mono) lab.className='font-mono'; lab.style.fontSize=mono?'12.5px':''; lab.textContent=o.label; row.appendChild(lab);
                                const ck=iconEl('check', o.value===current?'':'check-off'); row.appendChild(ck);
                                row.addEventListener('click',e=>{ e.stopPropagation(); current=o.value; wrap.classList.remove('open'); render(); if(onChange) onChange(current); });
                                menu.appendChild(row);
                            });
                        };
                        trigger.addEventListener('click',e=>{
                            e.stopPropagation();
                            const willOpen=!wrap.classList.contains('open');
                            document.querySelectorAll('.m3-select.open').forEach(el=>{ if(el!==wrap) el.classList.remove('open'); });
                            wrap.classList.toggle('open', willOpen);
                        });
                        render();
                        return {
                            el: wrap,
                            get: ()=>current,
                            set: v=>{ current=v; render(); }
                        };
                    }
                    document.addEventListener('click',()=>{ document.querySelectorAll('.m3-select.open').forEach(el=>el.classList.remove('open')); });
                    let menuEl=null;
                    function closeMapMenu(){ if(menuEl){ menuEl.remove(); menuEl=null; } }
                    function openMapMenu(latlng,ev,visit){
                        closeMapMenu();
                        const host=map.getContainer();
                        menuEl=document.createElement('div'); menuEl.className='map-ctx-menu'; menuEl.setAttribute('role','menu');
                        const add=(icon,label,fn)=>{
                            const b=document.createElement('button'); b.type='button'; b.setAttribute('role','menuitem');
                            const i=document.createElement('i'); i.className='fas fa-'+icon; b.appendChild(i); b.appendChild(document.createTextNode(' '+label));
                            b.addEventListener('click',()=>{ closeMapMenu(); fn(); }); menuEl.appendChild(b);
                        };
                        const coords=latlng.lat.toFixed(6)+', '+latlng.lng.toFixed(6);
                        const head=document.createElement('div'); head.className='map-ctx-head'; head.textContent=coords; menuEl.appendChild(head);
                        if(visit&&Array.isArray(visit.visit_ids)&&visit.visit_ids.length){
                            add('pen',tr('visit_edit','Edit place'),()=>openVisitEditor(visit.visit_ids[visit.visit_ids.length-1]));
                        }
                        add('map-pin',tr('ctx_add_place','Add place here…'),()=>openPlaceCreator(latlng));
                        add('vector-square',tr('ctx_select_area','Select points nearby (100 m)'),()=>{
                            areaClear(); setAreaMode(true);
                            areaApply(window.L.latLng(latlng).toBounds(200));
                        });
                        add('draw-polygon',tr('ctx_draw_area','Draw selection area'),()=>{ areaClear(); setAreaMode(true); });
                        add('copy',tr('ctx_copy','Copy coordinates'),()=>{ try{ navigator.clipboard.writeText(coords); }catch(e){} });
                        host.appendChild(menuEl);
                        const r=host.getBoundingClientRect();
                        let x=ev.clientX-r.left, y=ev.clientY-r.top;
                        x=Math.max(4,Math.min(x,r.width-menuEl.offsetWidth-4)); y=Math.max(4,Math.min(y,r.height-menuEl.offsetHeight-4));
                        menuEl.style.left=x+'px'; menuEl.style.top=y+'px';
                        menuEl.querySelector('button')?.focus();
                    }
                    function modal(titleText, opts){
                        opts=opts||{};
                        const ov=document.createElement('div'); ov.className='visit-modal visit-md3-overlay';
                        const box=document.createElement('div'); box.className='visit-modal-box visit-md3-box'+(opts.narrow?' is-narrow':''); box.setAttribute('role','dialog'); box.setAttribute('aria-modal','true');
                        const head=document.createElement('div'); head.className='visit-md3-head';
                        const main=document.createElement('div'); main.className='visit-md3-head-main';
                        const iconWrap=document.createElement('div'); iconWrap.className='visit-md3-head-icon'; iconWrap.appendChild(iconEl(opts.icon||'edit_location_alt')); main.appendChild(iconWrap);
                        const titles=document.createElement('div');
                        const h=document.createElement('h2'); h.textContent=titleText; titles.appendChild(h);
                        if(opts.subtitle){ const p=document.createElement('p'); p.textContent=opts.subtitle; titles.appendChild(p); }
                        main.appendChild(titles); head.appendChild(main);
                        const x=document.createElement('button'); x.type='button'; x.className='modal-close'; x.setAttribute('aria-label',tr('close','Close')); x.appendChild(iconEl('close')); head.appendChild(x);
                        box.appendChild(head); ov.appendChild(box);
                        const close=()=>{ ov.remove(); document.removeEventListener('keydown',onKey); };
                        const onKey=e=>{ if(e.key==='Escape') close(); };
                        x.addEventListener('click',close); ov.addEventListener('mousedown',e=>{ if(e.target===ov) close(); }); document.addEventListener('keydown',onKey);
                        document.body.appendChild(ov);
                        return {ov,box,head,close};
                    }
                    function tagOptions(){
                        return [
                            {value:'',label:tr('visit_tag_none','No tag')},
                            {value:'home',label:tr('tag_home','Home')},
                            {value:'work',label:tr('tag_work','Work')},
                            {value:'transit',label:tr('tag_transit','Transit')},
                            {value:'school',label:tr('tag_study','Study')},
                            {value:'favorite',label:tr('tag_favorite','Favorite')},
                            {value:'gym',label:tr('tag_gym','Gym')},
                            {value:'parents',label:tr('tag_parents','Parents')}
                        ];
                    }
                    function tagQuickValues(){ return ['','home','work','transit','school']; }
                    async function openVisitEditor(id){
                        const m=modal(tr('visit_edit','Edit place'),{subtitle:tr('visit_edit_subtitle','Verify venue identity, category tag, and detection rules'),icon:'edit_location_alt'});
                        const scroll=document.createElement('div'); scroll.className='visit-md3-scroll';
                        const loading=document.createElement('div'); loading.className='visit-md3-banner'; loading.textContent=tr('map_trip_loading','Loading…'); scroll.appendChild(loading);
                        m.box.appendChild(scroll);
                        let v;
                        try{
                            const res=await fetch(visitsApi+'&id='+encodeURIComponent(id),{credentials:'same-origin'});
                            const data=await res.json(); v=(data.visits||[])[0];
                            if(!res.ok||!v) throw new Error('not_found');
                        }catch(e){ loading.textContent=(e&&e.message)||'error'; return; }
                        scroll.textContent='';
                        const confPct=v.confidence!=null?Math.max(0,Math.min(100,Math.round(Number(v.confidence)*100))):null;
                        const banner=document.createElement('div'); banner.className='visit-md3-banner';
                        banner.appendChild(placeAvatar(v.photo_url, v.detection_status));
                        const bcopy=document.createElement('div'); bcopy.className='visit-md3-banner-copy';
                        const brow=document.createElement('div'); brow.className='visit-md3-banner-title-row';
                        const btitle=document.createElement('span'); btitle.className='visit-md3-title'; btitle.style.fontSize='16px'; btitle.textContent=v.place_name||tr('visit_unidentified','Unidentified place'); brow.appendChild(btitle);
                        brow.appendChild(statusBadge(v.detection_status)); bcopy.appendChild(brow);
                        const bmeta=document.createElement('div'); bmeta.className='visit-md3-banner-meta';
                        const metaBits=[v.category,[fmtAt(v.arrived_at),fmtAt(v.departed_at)].filter(Boolean).join(' → '),fmtDur(v.duration_s!=null?v.duration_s:v.dwell_s),v.address].filter(Boolean);
                        bmeta.appendChild(document.createTextNode(metaBits.join(' · ')));
                        if(confPct!=null){ bmeta.appendChild(document.createTextNode(' · ')); const sc=document.createElement('span'); sc.className='is-conf'; sc.textContent=confPct+'%'; bmeta.appendChild(sc); }
                        bcopy.appendChild(bmeta);
                        if(v.photo_attribution){ const ba=document.createElement('div'); ba.className='visit-md3-banner-attr'; ba.textContent=v.photo_attribution; bcopy.appendChild(ba); }
                        banner.appendChild(bcopy); scroll.appendChild(banner);
                        const grid=document.createElement('div'); grid.className='visit-md3-grid';
                        const left=document.createElement('div'); left.className='visit-md3-col';
                        const right=document.createElement('div'); right.className='visit-md3-col';
                        const cands=v.candidates||[];
                        let selectedCandidateId=null;
                        const nameIn=document.createElement('input'); nameIn.type='text'; nameIn.maxLength=80; nameIn.className='visit-md3-input'; nameIn.value=v.display_name||v.place_name||'';
                        const catOpts=[];
                        const pushCat=val=>{ if(!val) return; if(!catOpts.some(o=>o.value===val)) catOpts.push({value:val,label:val}); };
                        pushCat(v.category); cands.forEach(c=>pushCat(c.category));
                        if(!catOpts.length) pushCat('building=college');
                        const catSelect=m3Select(catOpts, v.category||catOpts[0].value, null, true);
                        const tagOpts=tagOptions();
                        let tagValue=v.place_tag||'';
                        const chips=document.createElement('div'); chips.className='visit-md3-chips';
                        const syncChips=()=>{ chips.querySelectorAll('.visit-md3-chip').forEach(btn=>btn.classList.toggle('is-active', btn.getAttribute('data-chip')===tagValue)); };
                        const tagSelectCtl=m3Select(tagOpts, tagValue, val=>{ tagValue=val; syncChips(); }, false);
                        const leftHead=document.createElement('div'); leftHead.className='visit-md3-sec-label';
                        const leftLab=document.createElement('span'); leftLab.appendChild(iconEl('near_me')); leftLab.appendChild(document.createTextNode('1. '+tr('visit_candidates','Possible places'))); leftHead.appendChild(leftLab);
                        const leftCount=document.createElement('span'); leftCount.className='visit-md3-sec-count'; leftCount.textContent=cands.length+' '+(tr('visit_candidates_count','candidates')); leftHead.appendChild(leftCount);
                        left.appendChild(leftHead);
                        const list=document.createElement('div'); list.className='visit-md3-cands';
                        if(!cands.length){
                            const empty=document.createElement('div'); empty.className='visit-md3-banner-attr'; empty.textContent=tr('visit_no_candidates','No nearby candidates yet'); list.appendChild(empty);
                        } else {
                            cands.forEach(c=>{
                                const b=document.createElement('button'); b.type='button'; b.className='visit-md3-cand'+(c.selected?' is-selected':'');
                                if(c.selected) selectedCandidateId=c.id;
                                const main=document.createElement('div'); main.className='visit-md3-cand-main';
                                const t1=document.createElement('strong'); t1.textContent=c.name||tr('visit_unnamed','Unnamed object'); main.appendChild(t1);
                                const t2=document.createElement('span'); t2.textContent=[c.category,c.distance_m!=null?Math.round(c.distance_m)+' m':''].filter(Boolean).join(' · '); main.appendChild(t2);
                                b.appendChild(main);
                                const score=document.createElement('span'); score.className='visit-md3-cand-score'; score.textContent=Math.round((c.score||0)*100)+'%'; b.appendChild(score);
                                b.addEventListener('click',()=>{
                                    list.querySelectorAll('.visit-md3-cand').forEach(el=>el.classList.remove('is-selected'));
                                    b.classList.add('is-selected');
                                    selectedCandidateId=c.id;
                                    if(c.name) nameIn.value=c.name;
                                    if(c.category){ pushCat(c.category); catSelect.set(c.category); }
                                });
                                list.appendChild(b);
                            });
                        }
                        left.appendChild(list);
                        const rightHead=document.createElement('div'); rightHead.className='visit-md3-sec-label';
                        const rightLab=document.createElement('span'); rightLab.appendChild(iconEl('tune')); rightLab.appendChild(document.createTextNode('2. '+tr('visit_details_rules','Place details & rules'))); rightHead.appendChild(rightLab);
                        right.appendChild(rightHead);
                        const details=document.createElement('div'); details.className='visit-md3-details';
                        const nameField=document.createElement('div'); nameField.className='visit-md3-field';
                        const nameLab=document.createElement('label'); nameLab.textContent=tr('map_trip_name','Name'); nameField.appendChild(nameLab); nameField.appendChild(nameIn); details.appendChild(nameField);
                        const catField=document.createElement('div'); catField.className='visit-md3-field';
                        const catLab=document.createElement('label'); catLab.textContent=tr('visit_category','Category'); catField.appendChild(catLab); catField.appendChild(catSelect.el); details.appendChild(catField);
                        const tagField=document.createElement('div'); tagField.className='visit-md3-field';
                        const tagLab=document.createElement('label'); tagLab.textContent=tr('visit_tag','Tag'); tagField.appendChild(tagLab); tagField.appendChild(tagSelectCtl.el);
                        tagQuickValues().forEach(val=>{
                            const opt=tagOpts.find(o=>o.value===val)||{value:val,label:val||tr('visit_tag_none','No tag')};
                            const chipBtn=document.createElement('button'); chipBtn.type='button'; chipBtn.className='visit-md3-chip'; chipBtn.setAttribute('data-chip',val); chipBtn.textContent=opt.label;
                            chipBtn.addEventListener('click',()=>{ tagValue=val; tagSelectCtl.set(val); syncChips(); });
                            chips.appendChild(chipBtn);
                        });
                        tagField.appendChild(chips); details.appendChild(tagField); syncChips();
                        right.appendChild(details);
                        grid.appendChild(left); grid.appendChild(right); scroll.appendChild(grid);
                        const msg=document.createElement('div'); msg.className='visit-md3-msg'; scroll.appendChild(msg);
                        const foot=document.createElement('div'); foot.className='visit-md3-foot';
                        const footLeft=document.createElement('div'); footLeft.className='visit-md3-foot-left';
                        const done=async fn=>{ try{ msg.textContent=''; await fn(); m.close(); chunkCache.clear(); fetchViewport(); loadVisits(); }catch(e){ msg.textContent=(e&&e.message)||'error'; } };
                        const secBtn=(label,icon,fn,danger)=>{
                            const b=document.createElement('button'); b.type='button'; b.className='visit-md3-sec-btn'+(danger?' is-danger':'');
                            b.appendChild(iconEl(icon)); b.appendChild(document.createTextNode(label)); b.addEventListener('click',fn); footLeft.appendChild(b); return b;
                        };
                        secBtn(tr('visit_retry','Detect again'),'refresh',()=>done(()=>visitsRequest({action:'retry',id:v.id})));
                        if(v.osm_id) secBtn(tr('visit_refresh_photo','Refresh photo'),'image_search',()=>done(()=>visitsRequest({action:'refresh_photo',id:v.id,osm_type:v.osm_type,osm_id:v.osm_id})));
                        if(v.osm_id) secBtn(tr('visit_block','Never suggest this place'),'block',()=>done(()=>visitsRequest({action:'block',id:v.id})),true);
                        foot.appendChild(footLeft);
                        const save=document.createElement('button'); save.type='button'; save.className='visit-md3-save-btn';
                        save.appendChild(iconEl('check')); save.appendChild(document.createTextNode(tr('visit_save','Save')));
                        save.addEventListener('click',()=>done(async()=>{
                            if(selectedCandidateId!=null) await visitsRequest({action:'select_candidate',id:v.id,candidate_id:selectedCandidateId});
                            await visitsRequest({action:'assign',id:v.id,name:nameIn.value,category:catSelect.get(),tag:tagSelectCtl.get()});
                        }));
                        foot.appendChild(save); m.box.appendChild(foot);
                        nameIn.focus();
                    }
                    function openPlaceCreator(latlng){
                        const m=modal(tr('ctx_add_place','Add place here…'),{narrow:true,subtitle:tr('ctx_add_place_hint','Create a named place at this map location'),icon:'add_location_alt'});
                        const scroll=document.createElement('div'); scroll.className='visit-md3-scroll';
                        const details=document.createElement('div'); details.className='visit-md3-details'; details.style.margin='20px 24px';
                        const nameIn=document.createElement('input'); nameIn.type='text'; nameIn.maxLength=80; nameIn.className='visit-md3-input';
                        const nameField=document.createElement('div'); nameField.className='visit-md3-field';
                        const nameLab=document.createElement('label'); nameLab.textContent=tr('map_trip_name','Name'); nameField.appendChild(nameLab); nameField.appendChild(nameIn); details.appendChild(nameField);
                        let tagValue='';
                        const tagOpts=tagOptions();
                        const chips=document.createElement('div'); chips.className='visit-md3-chips';
                        const syncChips=()=>{ chips.querySelectorAll('.visit-md3-chip').forEach(btn=>btn.classList.toggle('is-active', btn.getAttribute('data-chip')===tagValue)); };
                        const tagSelectCtl=m3Select(tagOpts, tagValue, val=>{ tagValue=val; syncChips(); }, false);
                        const tagField=document.createElement('div'); tagField.className='visit-md3-field';
                        const tagLab=document.createElement('label'); tagLab.textContent=tr('visit_tag','Tag'); tagField.appendChild(tagLab); tagField.appendChild(tagSelectCtl.el);
                        tagQuickValues().forEach(val=>{
                            const opt=tagOpts.find(o=>o.value===val)||{value:val,label:val||tr('visit_tag_none','No tag')};
                            const chipBtn=document.createElement('button'); chipBtn.type='button'; chipBtn.className='visit-md3-chip'; chipBtn.setAttribute('data-chip',val); chipBtn.textContent=opt.label;
                            chipBtn.addEventListener('click',()=>{ tagValue=val; tagSelectCtl.set(val); syncChips(); });
                            chips.appendChild(chipBtn);
                        });
                        tagField.appendChild(chips); details.appendChild(tagField); syncChips();
                        const radIn=document.createElement('input'); radIn.type='number'; radIn.min='20'; radIn.max='1000'; radIn.value='60'; radIn.className='visit-md3-input';
                        const radField=document.createElement('div'); radField.className='visit-md3-field';
                        const radLab=document.createElement('label'); radLab.textContent=tr('location_radius','Radius (m)'); radField.appendChild(radLab); radField.appendChild(radIn); details.appendChild(radField);
                        scroll.appendChild(details);
                        const msg=document.createElement('div'); msg.className='visit-md3-msg'; scroll.appendChild(msg);
                        m.box.appendChild(scroll);
                        const foot=document.createElement('div'); foot.className='visit-md3-foot';
                        const save=document.createElement('button'); save.type='button'; save.className='visit-md3-save-btn';
                        save.appendChild(iconEl('check')); save.appendChild(document.createTextNode(tr('visit_save','Save')));
                        save.addEventListener('click',async()=>{
                            const name=nameIn.value.trim()||(tagSelectCtl.get()?(tagOpts.find(o=>o.value===tagSelectCtl.get())||{}).label:'');
                            if(!name){ msg.textContent=tr('visit_name_required','Enter a name'); return; }
                            try{
                                const res=await fetch(placesApi,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'create',name:name,tag:tagSelectCtl.get()||'custom',latitude:latlng.lat,longitude:latlng.lng,radius_m:Number(radIn.value)||60})});
                                const data=await res.json().catch(()=>({}));
                                if(!res.ok||data.ok===false) throw new Error((data&&data.error)||'place_failed');
                                m.close(); window.dispatchEvent(new Event('steps-places-changed')); chunkCache.clear(); fetchViewport();
                            }catch(e){ msg.textContent=(e&&e.message)||'error'; }
                        });
                        foot.appendChild(document.createElement('div')); foot.appendChild(save); m.box.appendChild(foot);
                        nameIn.focus();
                    }
                    let visitsSeq=0;
                    async function loadVisits(){
                        const host=document.getElementById('ma-visits-list'); if(!host) return;
                        const seq=++visitsSeq;
                        try{
                            const res=await fetch(visitsApi+'&limit=200',{credentials:'same-origin'});
                            const data=await res.json(); if(seq!==visitsSeq) return;
                            if(!res.ok||!data.ok) throw new Error('visits_failed');
                            const from=new Date(fromInput.value+'T00:00:00Z').getTime(), to=new Date(toInput.value+'T23:59:59Z').getTime();
                            const rows=(data.visits||[]).filter(v=>{ const t=Date.parse(v.arrived_at); return t>=from&&t<=to; });
                            host.textContent='';
                            if(!rows.length){ const e=document.createElement('span'); e.className='trip-empty'; e.textContent=tr('visit_none','No visits in this range'); host.appendChild(e); return; }
                            rows.forEach(v=>{
                                const b=document.createElement('button'); b.type='button'; b.className='visit-row';
                                if(v.photo_url){
                                    const av=document.createElement('span'); av.className='visit-row-photo';
                                    const img=document.createElement('img'); img.src=v.photo_url; img.alt=''; img.loading='lazy'; av.appendChild(img); b.appendChild(av);
                                } else {
                                    const dot=document.createElement('i'); dot.className='visit-dot'; dot.style.background=VISIT_COLORS[v.detection_status]||VISIT_COLORS.unidentified; b.appendChild(dot);
                                }
                                const txt=document.createElement('span'); txt.className='visit-row-text';
                                const a=document.createElement('strong'); a.textContent=v.place_name||tr('visit_unidentified','Unidentified place'); txt.appendChild(a);
                                const sub=document.createElement('small'); sub.textContent=[fmtAt(v.arrived_at),fmtDur(v.duration_s),v.category].filter(Boolean).join(' · '); txt.appendChild(sub);
                                b.appendChild(txt);
                                b.addEventListener('click',()=>{ if(v.latitude!=null&&map) map.setView([v.latitude,v.longitude],Math.max(map.getZoom(),17)); openVisitEditor(v.id); });
                                host.appendChild(b);
                            });
                        }catch(e){ host.textContent=''; }
                    }
                    window.addEventListener('steps-visits-refresh',loadVisits);
                    loadVisits();
                    document.addEventListener('mousedown',e=>{ if(menuEl&&!menuEl.contains(e.target)) closeMapMenu(); });
                    document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeMapMenu(); });
                    areaCancel?.addEventListener('click',areaClear);
                    // Smoothing keeps one simplified line per pass through the box, so a
                    // chaotic 250 m cloud becomes one or two readable routes.
                    areaSmooth?.addEventListener('click',async()=>{
                        if(!areaBox) return;
                        const tolerance=Number(areaTolerance&&areaTolerance.value)||25;
                        areaSmooth.disabled=true;
                        try{
                            const data=await pointsRequest(Object.assign({action:'smooth',bbox:areaBox,tolerance_m:tolerance},areaRange()));
                            showError('');
                            areaInfo.textContent=(tr('points_smoothed','{routes} routes').replace('{routes}',String(data.paths||0)))+' · '+(data.points||0)+' → '+(data.paths||0);
                            areaDel.hidden=true; areaSmooth.hidden=true; areaToleranceWrap.hidden=true;
                            // The chunk fetched a moment ago still holds the raw cloud.
                            chunkCache.clear();
                            await fetchViewport(); loadTrips();
                        }catch(err){ showError((err&&err.message)||t.error); }
                        finally{ areaSmooth.disabled=false; }
                    });
                    areaDel?.addEventListener('click',async()=>{
                        if(!areaBox) return;
                        if(!await confirmDelete(tr('points_confirm','Delete these points permanently?')+' ('+areaInfo.textContent+')')) return;
                        try{
                            await pointsRequest(Object.assign({action:'delete',bbox:areaBox},areaRange()));
                            areaClear(); chunkCache.clear(); await fetchViewport(); loadTrips();
                        }catch(err){ showError((err&&err.message)||t.error); }
                    });
                    const ptEl=id=>document.getElementById(id);
                    let ptOffset=0; let PT_LIMIT=50; let ptRows=[], ptTotal=0;
                    const ptIso=id=>{ const v=ptEl(id)?.value; return v?new Date(v).toISOString():''; };
                    const ptFilter=()=>{
                        const f={from:ptIso('pt-from'),to:ptIso('pt-to'),state:ptEl('pt-state')?.value||'',source:ptEl('pt-source')?.value||''};
                        const latV=ptEl('pt-lat')?.value, lonV=ptEl('pt-lon')?.value, hasLat=latV!==''&&latV!=null&&isFinite(Number(latV)), hasLon=lonV!==''&&lonV!=null&&isFinite(Number(lonV));
                        if(hasLat||hasLon){
                            const r=Math.max(0.1,Number(ptEl('pt-radius')?.value)||5), lat=hasLat?Number(latV):0, lon=hasLon?Number(lonV):0;
                            const dLat=r/111.32, dLon=r/(111.32*Math.max(0.05,Math.cos((hasLat?lat:0)*Math.PI/180)));
                            const minLat=hasLat?Math.max(-90,lat-dLat):-90, maxLat=hasLat?Math.min(90,lat+dLat):90;
                            const minLon=hasLon?Math.max(-180,lon-dLon):-180, maxLon=hasLon?Math.min(180,lon+dLon):180;
                            f.bbox=[minLon,minLat,maxLon,maxLat].join(',');
                        }
                        return f;
                    };
                    function ptSelected(){ return Array.from(document.querySelectorAll('#pt-list input[data-pt-id]:checked')).map(i=>i.getAttribute('data-pt-id')); }
                    function ptSync(){ const n=ptSelected().length; const b=ptEl('pt-delete'); if(b) b.disabled=n===0; }
                    async function ptLoad(){
                        const host=ptEl('pt-list'); if(!host) return;
                        const q=new URLSearchParams(Object.assign({limit:PT_LIMIT,offset:ptOffset},ptFilter()));
                        try{
                            const res=await fetch(pointsApi+'&'+q.toString(),{credentials:'same-origin'});
                            const data=await res.json();
                            if(!res.ok||!data.ok) throw new Error((data&&data.error)||'points_failed');
                            ptRows=data.points||[]; host.innerHTML='';
                            ptEl('pt-total').textContent='('+data.total+')';
                            ptEl('pt-all').checked=false;
                            ptRows.forEach(r=>{
                                const row=document.createElement('label'); row.className='points-row m3-checkbox-wrapper';
                                const cb=document.createElement('input'); cb.type='checkbox'; cb.className='m3-checkbox-input'; cb.setAttribute('data-pt-id',r.id); cb.addEventListener('change',ptSync);
                                const box=document.createElement('span'); box.className='m3-checkbox-box'; box.innerHTML='<svg class="m3-checkbox-svg" viewBox="0 0 16 16"><polyline points="3.5 8.5 6.5 11.5 12.5 4.5"></polyline></svg>';
                                const text=document.createElement('span'); text.textContent=String(r.source_at).slice(0,19)+' · '+Number(r.latitude).toFixed(5)+', '+Number(r.longitude).toFixed(5);
                                const tag=document.createElement('em'); tag.className='points-meta';
                                const state=String(r.quality_state||'');
                                const acc=r.accuracy_m!=null?Number(r.accuracy_m):null;
                                const lowAcc=acc!=null&&acc>=50;
                                if(state==='flagged'||state==='suspect'||state==='rejected'||lowAcc) tag.classList.add('is-flagged');
                                else if(state==='accepted'||(acc!=null&&acc<=10)) tag.classList.add('is-accepted');
                                tag.textContent=state+' · '+r.source_type+(acc!=null?' · ±'+Math.round(acc)+' m':'');
                                row.appendChild(cb); row.appendChild(box); row.appendChild(text); row.appendChild(tag); host.appendChild(row);
                            });
                            if(!ptRows.length){ const e=document.createElement('p'); e.className='location-hint'; e.textContent=t.empty; host.appendChild(e); }
                            ptTotal=Number(data.total)||0; ptPager();
                            ptSync();
                        }catch(e){ ptEl('pt-status').textContent=(e&&e.message)||'points_failed'; }
                    }
                    ptEl('pt-search')?.addEventListener('click',()=>{ ptOffset=0; ptLoad(); });
                    window.stepsPickOnMap=async function(opts){
                        opts=opts||{};
                        let Lf; try{ Lf=await ensureLeaflet(); }catch(e){ return; }
                        const ov=document.createElement('div'); ov.className='picker-overlay';
                        ov.innerHTML='<div class="picker-box"><div class="picker-head"><strong></strong><button type="button" class="picker-x" aria-label="Close"><span class="material-symbols-rounded" aria-hidden="true">close</span></button></div><div class="picker-map"></div><div class="picker-foot"><span class="picker-coords">—</span><button type="button" class="excel-btn picker-ok" disabled>OK</button><button type="button" class="excel-btn picker-cancel"></button></div></div>';
                        ov.querySelector('strong').textContent=opts.title||tr('pick_title','Click the map to choose a location');
                        ov.querySelector('.picker-ok').textContent=tr('pick_ok','Select');
                        ov.querySelector('.picker-cancel').textContent=tr('cancel','Cancel');
                        document.body.appendChild(ov);
                        const has=opts.lat!=null&&opts.lon!=null;
                        const m=Lf.map(ov.querySelector('.picker-map'),{worldCopyJump:true}).setView(has?[opts.lat,opts.lon]:[50.45,30.52],has?11:3);
                        Lf.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap contributors'}).addTo(m);
                        setTimeout(()=>m.invalidateSize(),50);
                        let marker=null, circle=null, chosen=null;
                        const ok=ov.querySelector('.picker-ok'), coords=ov.querySelector('.picker-coords');
                        const choose=ll=>{
                            chosen=ll; ok.disabled=false; coords.textContent=ll.lat.toFixed(6)+', '+ll.lng.toFixed(6);
                            if(marker) marker.setLatLng(ll); else marker=Lf.marker(ll).addTo(m);
                            if(opts.radius){ if(circle) circle.setLatLng(ll); else circle=Lf.circle(ll,{radius:opts.radius,color:'#60a5fa',weight:2,fillOpacity:0.15}).addTo(m); }
                        };
                        if(has) choose(Lf.latLng(opts.lat,opts.lon));
                        m.on('click',e=>choose(e.latlng));
                        const close=()=>{ m.remove(); ov.remove(); };
                        ov.querySelector('.picker-x').addEventListener('click',close);
                        ov.querySelector('.picker-cancel').addEventListener('click',close);
                        ok.addEventListener('click',()=>{ if(chosen&&opts.onPick) opts.onPick(chosen.lat,chosen.lng); close(); });
                        if(opts.showPlaces!==false){
                            try{
                                const res=await fetch('/apps/steps/api/session_gateway.php?endpoint=places',{credentials:'same-origin'});
                                const data=await res.json();
                                (data.places||[]).forEach(pl=>{
                                    const c=Lf.circle([pl.latitude,pl.longitude],{radius:pl.radius_m,color:pl.color||'#22c55e',weight:1,fillOpacity:0.18}).addTo(m);
                                    c.bindTooltip(pl.name+' · '+pl.tag,{permanent:true,direction:'top',className:'picker-tip'});
                                    const box=document.createElement('div'); box.textContent=pl.name+' · '+pl.tag+' · '+Math.round(pl.radius_m)+' m';
                                    const del=document.createElement('button'); del.type='button'; del.className='excel-btn'; del.style.marginTop='6px'; del.textContent=tr('location_tools_remove','Remove');
                                    del.addEventListener('click',async()=>{
                                        if(!window.confirm(tr('location_place_confirm','Delete this place?'))) return;
                                        await fetch('/apps/steps/api/session_gateway.php?endpoint=places',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete',id:pl.id})}).catch(()=>{});
                                        c.remove(); window.dispatchEvent(new Event('steps-places-changed'));
                                    });
                                    box.appendChild(document.createElement('br')); box.appendChild(del);
                                    c.bindPopup(box);
                                });
                            }catch(e){}
                        }
                    };                    const ptPageCount=()=>Math.max(1,Math.ceil(ptTotal/PT_LIMIT));
                    const ptGo=p=>{ p=Math.min(ptPageCount(),Math.max(1,p)); ptOffset=(p-1)*PT_LIMIT; ptLoad(); };
                    function ptPager(){
                        const cur=Math.floor(ptOffset/PT_LIMIT)+1, n=ptPageCount(), host=ptEl('pt-pages'); if(!host) return;
                        host.innerHTML='';
                        const set=new Set([1,n,cur-2,cur-1,cur,cur+1,cur+2]); let prev=0;
                        Array.from(set).filter(p=>p>=1&&p<=n).sort((a,b)=>a-b).forEach(p=>{
                            if(p-prev>1){ const gap=document.createElement('span'); gap.textContent='…'; gap.className='pager-gap'; host.appendChild(gap); }
                            const b=document.createElement('button'); b.type='button'; b.className='pager-page'+(p===cur?' is-current':''); b.textContent=p; b.addEventListener('click',()=>ptGo(p)); host.appendChild(b); prev=p;
                        });
                        ptEl('pt-first').disabled=ptEl('pt-prev').disabled=cur<=1; ptEl('pt-next').disabled=ptEl('pt-last').disabled=cur>=n;
                    }
                    ptEl('pt-first')?.addEventListener('click',()=>ptGo(1));
                    ptEl('pt-prev')?.addEventListener('click',()=>ptGo(Math.floor(ptOffset/PT_LIMIT)));
                    ptEl('pt-next')?.addEventListener('click',()=>ptGo(Math.floor(ptOffset/PT_LIMIT)+2));
                    ptEl('pt-last')?.addEventListener('click',()=>ptGo(ptPageCount()));
                    ptEl('pt-size')?.addEventListener('change',e=>{ PT_LIMIT=Number(e.target.value)||50; ptOffset=0; ptLoad(); });
                    ptEl('pt-pick')?.addEventListener('click',()=>window.stepsPickOnMap&&window.stepsPickOnMap({lat:Number(ptEl('pt-lat').value)||null,lon:Number(ptEl('pt-lon').value)||null,showPlaces:false,onPick:(la,lo)=>{ ptEl('pt-lat').value=la.toFixed(6); ptEl('pt-lon').value=lo.toFixed(6); ptOffset=0; ptLoad(); }}));
                    ptEl('pt-all')?.addEventListener('change',e=>{ document.querySelectorAll('#pt-list input[data-pt-id]').forEach(i=>{ i.checked=e.target.checked; }); ptSync(); });
                    async function ptDelete(body,count){
                        if(!await confirmDelete(tr('points_confirm','Delete these points permanently?')+' ('+count+')')) return;
                        try{
                            const data=await pointsRequest(Object.assign({action:'delete'},body));
                            ptEl('pt-status').textContent=data.deleted+' '+tr('points_deleted','deleted');
                            ptOffset=0; await ptLoad(); fetchViewport(); loadTrips();
                        }catch(e){ ptEl('pt-status').textContent=(e&&e.message)||'delete_failed'; }
                    }
                    ptEl('pt-delete')?.addEventListener('click',()=>{ const ids=ptSelected(); if(ids.length) ptDelete({ids},ids.length); });
                    ptEl('pt-delete-range')?.addEventListener('click',async()=>{
                        const f=ptFilter();
                        if(!f.from||!f.to){ ptEl('pt-status').textContent=tr('points_need_range','Choose From and To first.'); return; }
                        try{ const c=await pointsRequest(Object.assign({action:'count'},f)); ptDelete(f,c.count); }
                        catch(e){ ptEl('pt-status').textContent=(e&&e.message)||'points_failed'; }
                    });
                    window.addEventListener('steps-points-tab-open',()=>{
                        const local=(d,end)=>d+(end?'T23:59':'T00:00');
                        if(ptEl('pt-from')&&!ptEl('pt-from').value) ptEl('pt-from').value=local(fromInput.value,false);
                        if(ptEl('pt-to')&&!ptEl('pt-to').value) ptEl('pt-to').value=local(toInput.value,true);
                        ptOffset=0; ptLoad();
                    });                    const SIDEBAR_KEY='stepsMapSidebarHidden';
                    const hideBtn=document.getElementById('ma-sidebar-hide'), showBtn=document.getElementById('ma-sidebar-show');
                    const sidebarModal=window.matchMedia('(max-width: 820px)');
                    let sidebarHidden=false;
                    function syncSidebarModal(){
                        // On phones the panel behaves like a modal sheet, so the page
                        // behind it must not scroll and the backdrop click closes it.
                        document.body.classList.toggle('map-sidebar-open', !sidebarHidden && sidebarModal.matches && !mapHost.hidden);
                    }
                    function setSidebar(hidden){
                        sidebarHidden=!!hidden;
                        section.classList.toggle('is-collapsed',hidden);
                        if(showBtn) showBtn.hidden=!hidden;
                        syncSidebarModal();
                        try{ localStorage.setItem(SIDEBAR_KEY,hidden?'1':'0'); }catch(e){}
                        setTimeout(()=>{ if(map) map.invalidateSize({pan:false}); },260);
                    }
                    hideBtn?.addEventListener('click',()=>setSidebar(true));
                    showBtn?.addEventListener('click',()=>setSidebar(false));
                    section.querySelector('.map-sidebar-backdrop')?.addEventListener('click',()=>setSidebar(true));
                    if(sidebarModal.addEventListener) sidebarModal.addEventListener('change',syncSidebarModal);
                    window.addEventListener('steps-map-shown',syncSidebarModal);
                    document.addEventListener('keydown',e=>{ if(e.key==='Escape'&&!section.classList.contains('is-collapsed')&&sidebarModal.matches) setSidebar(true); });
                    try{ if(localStorage.getItem(SIDEBAR_KEY)==='1') setSidebar(true); }catch(e){}
                    syncSidebarModal();
                    const onRangeChanged=()=>{
                        didFit=false;
                        clearPrefetchQueue(true);
                        chunkCache.clear();
                        if(mapStarted){ fetchViewport(); loadTrips(); }
                    };
                    // Import completion and point deletion happen outside this
                    // closure. Drop the cached chunks so the next draw reflects the
                    // new rows instead of a viewport captured before them.
                    window.stepsMapInvalidate=()=>{
                        didFit=false;
                        chunkCache.clear();
                        clearPrefetchQueue(true);
                        if(mapStarted){ fetchViewport({dir:null}); loadTrips(); }
                    };
                    document.getElementById('ma-map-family')?.addEventListener('change',onRangeChanged);
                    async function loadFamilyTargets(){
                        const select=document.getElementById('ma-map-family');
                        if(!select) return;
                        try{
                            const res=await fetch('/apps/steps/api/session_gateway.php?endpoint=family_permissions',{credentials:'same-origin'});
                            const data=await res.json();
                            (data.incoming||[]).forEach(row=>{
                                const opt=document.createElement('option');
                                opt.value=row.owner_email;
                                opt.textContent=(row.family_group_name?row.family_group_name+' · ':'')+row.owner_email;
                                select.appendChild(opt);
                            });
                        }catch(e){}
                    }
                    const renderApi='/apps/steps/api/session_gateway.php?endpoint=geolocation_renders';
                    const rd=id=>document.getElementById(id);
                    let renderJob=null, renderPoll=null;
                    function showRenderStep(step){
                        const n=Math.min(3,Math.max(1,Number(step)||1));
                        document.querySelectorAll('#settings-panel-render [data-render-step-panel]').forEach(panel=>{
                            panel.classList.toggle('is-active', Number(panel.getAttribute('data-render-step-panel'))===n);
                        });
                        document.querySelectorAll('#settings-panel-render .render-step').forEach(btn=>{
                            const active=Number(btn.getAttribute('data-render-step'))===n;
                            btn.classList.toggle('is-active', active);
                            if(active) btn.setAttribute('aria-current','step'); else btn.removeAttribute('aria-current');
                        });
                    }
                    // The render panel markup lives further down the page, so the
                    // stepper is wired once the document is parsed.
                    function initRenderStepper(){
                        document.querySelectorAll('#settings-panel-render .render-step, #settings-panel-render [data-render-goto]').forEach(btn=>{
                            if(btn.dataset.renderBound==='1') return;
                            btn.dataset.renderBound='1';
                            btn.addEventListener('click',()=>showRenderStep(btn.getAttribute('data-render-step')||btn.getAttribute('data-render-goto')));
                        });
                        showRenderStep(1);
                    }
                    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',initRenderStepper);
                    else initRenderStepper();
                    function renderErrorText(code){
                        const m={no_geometry:tr('render_err_geometry','No route in this period.'),invalid_range:tr('map_trip_err_range','Choose a valid date range.'),encoder_unavailable:tr('render_err_encoder','Video encoder is unavailable.'),render_busy:tr('render_err_busy','Another render is running.'),permission_changed:tr('render_err_permission','Access changed; start again.'),render_timeout:tr('render_err_timeout','Rendering took too long.')};
                        return m[code]||code||'render_failed';
                    }
                    function syncRenderKind(){
                        const video=rd('rd-kind')?.value==='video';
                        document.querySelectorAll('#settings-panel-render [data-video-only]').forEach(el=>{ el.hidden=!video; });
                    }
                    function renderUi(job){
                        const running=!!job&&(job.state==='pending'||job.state==='processing');
                        if(rd('rd-bar')) rd('rd-bar').style.width=(job?Number(job.progress)||0:0)+'%';
                        if(rd('rd-start')) rd('rd-start').disabled=running;
                        if(rd('rd-cancel')) rd('rd-cancel').disabled=!running;
                        if(rd('rd-status')) rd('rd-status').textContent=job?(job.state+(job.error_code?': '+renderErrorText(job.error_code):'')+(running?' '+(job.progress||0)+'%':'')):'';
                    }
                    function renderCard(job){
                        const icons={video:'fa-film',poster:'fa-image'};
                        const card=document.createElement('div'); card.className='render-card is-'+job.state;
                        const head=document.createElement('div'); head.className='render-card-head';
                        const ico=document.createElement('i'); ico.className='fas '+(icons[job.kind]||'fa-file'); head.appendChild(ico);
                        const name=document.createElement('strong'); name.textContent=(job.title||String(job.range_from||'').slice(0,10)||job.kind)+' · '+String(job.file_format||job.kind).toUpperCase(); head.appendChild(name);
                        const badge=document.createElement('span'); badge.className='render-badge'; badge.textContent=job.state; head.appendChild(badge);
                        card.appendChild(head);
                        const meta=document.createElement('div'); meta.className='render-card-meta';
                        meta.textContent=String(job.created_at||'').slice(0,16)+(job.size_bytes?' · '+(Number(job.size_bytes)/1024).toFixed(0)+' KB':'')+(job.orientation?' · '+job.orientation:'')+(job.error_code?' · '+renderErrorText(job.error_code):'');
                        card.appendChild(meta);
                        if(job.state==='pending'||job.state==='processing'){
                            const bar=document.createElement('div'); bar.className='render-progress'; const fill=document.createElement('span'); fill.style.width=(Number(job.progress)||0)+'%'; bar.appendChild(fill); card.appendChild(bar);
                        }
                        const actions=document.createElement('div'); actions.className='render-card-actions';
                        if(job.state==='ready'){
                            const prev=document.createElement('button'); prev.type='button'; prev.className='excel-btn'; prev.innerHTML='<i class="fas fa-eye"></i> '+tr('render_preview','Preview');
                            prev.addEventListener('click',()=>showRenderPreview(job));
                            const dl=document.createElement('a'); dl.className='excel-btn'; dl.innerHTML='<i class="fas fa-download"></i> '+tr('render_download','Download'); dl.href=renderApi+'&download='+encodeURIComponent(job.id);
                            actions.appendChild(prev); actions.appendChild(dl);
                        }
                        if(job.state==='pending'||job.state==='processing'){
                            const cancel=document.createElement('button'); cancel.type='button'; cancel.className='excel-btn'; cancel.innerHTML='<i class="fas fa-stop"></i> '+tr('render_cancel','Cancel');
                            cancel.addEventListener('click',async()=>{ await fetch(renderApi,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'cancel',id:job.id})}).catch(()=>{}); loadRenderJobs(); });
                            actions.appendChild(cancel);
                        }
                        if(job.state!=='pending'&&job.state!=='processing'){
                            const del=document.createElement('button'); del.type='button'; del.className='excel-btn render-delete';
                            del.innerHTML='<i class="fas fa-trash"></i> '+tr('render_delete','Delete');
                            del.addEventListener('click',async()=>{
                                if(!window.confirm(tr('render_delete_confirm','Delete this render permanently?'))) return;
                                try{
                                    const r=await fetch(renderApi,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete',id:job.id})});
                                    const data=await r.json().catch(()=>null);
                                    if(!r.ok||!data||!data.ok) throw new Error((data&&data.error)||'render_delete_failed');
                                    if(rd('rd-preview')){ rd('rd-preview').innerHTML=''; rd('rd-preview').hidden=true; }
                                    loadRenderJobs();
                                }catch(e){ if(rd('rd-status')) rd('rd-status').textContent=String(e&&e.message?e.message:e); }
                            });
                            actions.appendChild(del);
                        }
                        card.appendChild(actions);
                        return card;
                    }
                    function buildRenderPlayer(src){
                        const box=document.createElement('div'); box.className='rplayer';
                        const v=document.createElement('video'); v.className='rplayer-video'; v.preload='auto'; v.loop=true; v.muted=true; v.autoplay=true; v.playsInline=true; v.src=src;
                        const big=document.createElement('button'); big.type='button'; big.className='rplayer-big'; big.innerHTML='<i class="fas fa-play"></i>';
                        const bar=document.createElement('div'); bar.className='rplayer-bar';
                        const mk=(cls,icon)=>{ const b=document.createElement('button'); b.type='button'; b.className='rplayer-btn '+cls; b.innerHTML='<i class="fas '+icon+'"></i>'; return b; };
                        const play=mk('rplayer-play','fa-pause'), mute=mk('rplayer-mute','fa-volume-xmark'), full=mk('rplayer-full','fa-expand');
                        const track=document.createElement('div'); track.className='rplayer-track';
                        const buf=document.createElement('span'); buf.className='rplayer-buf'; const pos=document.createElement('span'); pos.className='rplayer-pos'; const knob=document.createElement('i'); knob.className='rplayer-knob';
                        track.appendChild(buf); track.appendChild(pos); track.appendChild(knob);
                        const time=document.createElement('span'); time.className='rplayer-time'; time.textContent='0:00 / 0:00';
                        const speed=document.createElement('button'); speed.type='button'; speed.className='rplayer-btn rplayer-speed'; speed.textContent='1×';
                        bar.append(play,track,time,speed,mute,full); box.append(v,big,bar);
                        const fmt=s=>{ s=Math.max(0,Math.floor(s||0)); return Math.floor(s/60)+':'+String(s%60).padStart(2,'0'); };
                        const sync=()=>{
                            const d=v.duration||0; pos.style.width=d?(v.currentTime/d*100)+'%':'0'; knob.style.left=d?(v.currentTime/d*100)+'%':'0';
                            let end=0; for(let i=0;i<v.buffered.length;i++){ if(v.buffered.start(i)<=v.currentTime+0.5) end=Math.max(end,v.buffered.end(i)); }
                            buf.style.width=d?(end/d*100)+'%':'0'; time.textContent=fmt(v.currentTime)+' / '+fmt(d);
                        };
                        const state=()=>{ const p=!v.paused; play.innerHTML='<i class="fas '+(p?'fa-pause':'fa-play')+'"></i>'; big.hidden=p; box.classList.toggle('is-playing',p); };
                        const toggle=()=>{ if(v.paused) v.play().catch(()=>{}); else v.pause(); };
                        play.addEventListener('click',toggle); big.addEventListener('click',toggle); v.addEventListener('click',toggle);
                        v.addEventListener('timeupdate',sync); v.addEventListener('progress',sync); v.addEventListener('loadedmetadata',sync); v.addEventListener('play',state); v.addEventListener('pause',state);
                        v.addEventListener('waiting',()=>box.classList.add('is-loading')); v.addEventListener('canplay',()=>box.classList.remove('is-loading')); v.addEventListener('playing',()=>box.classList.remove('is-loading'));
                        const seek=e=>{ const r=track.getBoundingClientRect(); if(v.duration) v.currentTime=Math.min(1,Math.max(0,(e.clientX-r.left)/r.width))*v.duration; };
                        let drag=false;
                        track.addEventListener('pointerdown',e=>{ drag=true; track.setPointerCapture(e.pointerId); seek(e); });
                        track.addEventListener('pointermove',e=>{ if(drag) seek(e); });
                        track.addEventListener('pointerup',()=>{ drag=false; });
                        mute.addEventListener('click',()=>{ v.muted=!v.muted; mute.innerHTML='<i class="fas '+(v.muted?'fa-volume-xmark':'fa-volume-high')+'"></i>'; });
                        const rates=[1,1.5,2,0.5]; let ri=0;
                        speed.addEventListener('click',()=>{ ri=(ri+1)%rates.length; v.playbackRate=rates[ri]; speed.textContent=rates[ri]+'×'; });
                        full.addEventListener('click',()=>{ if(document.fullscreenElement) document.exitFullscreen(); else (box.requestFullscreen||box.webkitRequestFullscreen||(()=>{})).call(box); });
                        box.tabIndex=0;
                        box.addEventListener('keydown',e=>{ if(e.key===' '){ e.preventDefault(); toggle(); } else if(e.key==='ArrowRight') v.currentTime+=2; else if(e.key==='ArrowLeft') v.currentTime-=2; });
                        box.classList.add('is-loading'); state();
                        return box;
                    }
                    function showRenderPreview(job){
                        const host=rd('rd-preview'); if(!host) return;
                        const src=renderApi+'&download='+encodeURIComponent(job.id)+'&inline=1';
                        host.innerHTML=''; host.hidden=false;
                        let el;
                        if(job.file_format==='mp4'){ host.appendChild(buildRenderPlayer(src)); host.scrollIntoView({block:'nearest',behavior:'smooth'}); return; }
                        else if(job.file_format==='pdf'){ el=document.createElement('iframe'); }
                        else { el=document.createElement('img'); el.alt=''; }
                        el.src=src; el.className='render-preview-media';
                        host.appendChild(el);
                        host.scrollIntoView({block:'nearest',behavior:'smooth'});
                    }
                    async function loadRenderJobs(){
                        const host=rd('rd-jobs'); if(!host) return;
                        try{
                            const res=await fetch(renderApi,{credentials:'same-origin'});
                            const data=await res.json();
                            host.innerHTML='';
                            (data.jobs||[]).forEach(job=>host.appendChild(renderCard(job)));
                            const active=(data.jobs||[]).find(j=>j.state==='pending'||j.state==='processing');
                            if(active&&!renderJob){ renderJob=active.id; pollRender(); }
                        }catch(e){}
                    }                    async function pollRender(){
                        if(!renderJob) return;
                        try{
                            const res=await fetch(renderApi+'&id='+encodeURIComponent(renderJob),{credentials:'same-origin'});
                            const data=await res.json();
                            const job=data.job||{};
                            renderUi(job); loadRenderJobs();
                            if(job.state==='pending'||job.state==='processing'){ renderPoll=setTimeout(pollRender,1500); return; }
                            renderJob=null; loadRenderJobs();
                        }catch(e){ renderPoll=setTimeout(pollRender,3000); }
                    }
                    async function startRender(){
                        const kindSel=rd('rd-kind')?.value||'video';
                        const body={
                            action:'create', kind:kindSel==='video'?'video':'poster', file:kindSel==='pdf'?'pdf':'png',
                            format:rd('rd-format').value, theme:rd('rd-theme').value, edge:Number(rd('rd-edge').value),
                            duration:Number(rd('rd-duration').value), fps:Number(rd('rd-fps').value),
                            title:rd('rd-title').value,
                            from:rd('rd-from').value+'T00:00:00Z', to:rd('rd-to').value+'T23:59:59Z',
                            family_email:rd('ma-map-family')?.value||'',
                            provider:rd('rd-provider').value, camera:rd('rd-camera').value, route_color:rd('rd-route-color').value,
                            tint:rd('rd-tint').value, tint_opacity:Number(rd('rd-tint-opacity').value)
                        };
                        try{
                            const res=await fetch(renderApi,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
                            const data=await res.json();
                            if(!res.ok||!data.ok) throw new Error((data&&data.error)||'render_failed');
                            renderJob=data.job.id; renderUi(data.job); pollRender();
                        }catch(e){ if(rd('rd-status')) rd('rd-status').textContent=renderErrorText(e&&e.message); }
                    }
                    rd('rd-kind')?.addEventListener('change',syncRenderKind);
                    rd('rd-start')?.addEventListener('click',startRender);
                    rd('rd-cancel')?.addEventListener('click',async()=>{
                        if(!renderJob) return;
                        await fetch(renderApi,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'cancel',id:renderJob})}).catch(()=>{});
                    });
                    window.addEventListener('steps-render-tab-open',()=>{
                        if(rd('rd-from')&&!rd('rd-from').value){ rd('rd-from').value=fromInput.value; rd('rd-from').dispatchEvent(new Event('change',{bubbles:true})); }
                        if(rd('rd-to')&&!rd('rd-to').value){ rd('rd-to').value=toInput.value; rd('rd-to').dispatchEvent(new Event('change',{bubbles:true})); }
                        syncRenderKind(); loadRenderJobs();
                    });
                    syncRenderKind();
                    toInput.addEventListener('change', capToToday);
                    fromInput.addEventListener('change', capToToday);
                    fromInput.addEventListener('change',()=>{ if(toInput.value&&fromInput.value>toInput.value){ toInput.value=fromInput.value; toInput.dispatchEvent(new Event('input',{bubbles:true})); const v=toInput.closest('.dp-field')?.querySelector('.dp-value'); const d=parseYMD(toInput.value); if(v&&d) v.textContent=d.toLocaleDateString(dateLocale(),{day:'numeric',month:'short',year:'numeric'}); } onRangeChanged(); });
                    toInput.addEventListener('change',()=>{ if(fromInput.value&&toInput.value<fromInput.value){ fromInput.value=toInput.value; const v=fromInput.closest('.dp-field')?.querySelector('.dp-value'); const d=parseYMD(fromInput.value); if(v&&d) v.textContent=d.toLocaleDateString(dateLocale(),{day:'numeric',month:'short',year:'numeric'}); } onRangeChanged(); });
                    document.getElementById('ma-map-day-prev')?.addEventListener('click',()=>shiftRange(-1));
                    document.getElementById('ma-map-day-next')?.addEventListener('click',()=>shiftRange(1));
                    initDatePickers();
                    initTripManager();
                    const prefetchSel=document.getElementById('ma-map-prefetch');
                    if(prefetchSel){
                        prefetchSel.value=prefetchMode;
                        prefetchSel.addEventListener('change',()=>{
                            prefetchMode=prefetchModes.includes(prefetchSel.value)?prefetchSel.value:'balanced';
                            try{ localStorage.setItem(PREFETCH_KEY,prefetchMode); }catch(e){}
                            cancelPrefetch(); chunkCache.clear();
                        });
                    }
                    providerSel.addEventListener('change',()=>{ provider=providerSel.value; selectProvider(provider); saveSettings(); });
                    const syncSpeedLegend=()=>{ const l=document.getElementById('ma-speed-legend'); if(l) l.hidden=!selectedLayers().includes('speed'); };
                    section.querySelectorAll('input[data-layer]').forEach(input=>input.addEventListener('change',()=>{ syncSpeedLegend(); saveSettings(); fetchViewport(); }));
                    setTimeout(syncSpeedLegend,300);
                    let pointRevision = null;
                    let revisionPending = false;
                    async function pollPointRevision(){
                        if(!mapStarted||!mapHost||mapHost.hidden||document.hidden||revisionPending) return;
                        revisionPending=true;
                        try{
                            const response=await fetch('/apps/steps/api/session_gateway.php?endpoint=geolocation_points&revision=1',{credentials:'same-origin',cache:'no-store'});
                            if(!response.ok) return;
                            const data=await response.json();
                            if(!data.ok) return;
                            const revision=String(data.revision||'');
                            if(pointRevision!==null&&pointRevision!==revision){
                                cancelPrefetch(); chunkCache.clear();
                                await fetchViewport(); loadTrips();
                            }
                            pointRevision=revision;
                        }catch(e){}finally{ revisionPending=false; }
                    }
                    setInterval(pollPointRevision,3000);
                    setInterval(()=>{ if(!mapHost||mapHost.hidden) return; fetchViewport(); pollLive(); },30000);
                    // The live marker is cheap (one row) and updates every 10 s, so a
                    // moving phone visibly walks across the map.
                    setInterval(()=>{ pollLive(); },2500);
                    document.addEventListener('visibilitychange',()=>{ if(!document.hidden) pollLive(); });
                    window.addEventListener('steps-map-shown',()=>{ startMapWorkspace(); });
                    document.addEventListener('visibilitychange',()=>{ if(document.hidden) cancelPrefetch(); });
                    window.addEventListener('offline',()=>cancelPrefetch());
                    // Network hints are advisory only: no transport classification is stored.
                    if(navigator.connection&&navigator.connection.addEventListener){
                        navigator.connection.addEventListener('change',()=>{ if(!prefetchAllowed()) cancelPrefetch(); });
                    }
                    if(mapStarted) startMapWorkspace();
                    }
                    if(document.readyState==='loading'){
                        document.addEventListener('DOMContentLoaded',boot,{once:true});
                    } else {
                        boot();
                    }
                })();
                </script>
        </div>
        </div>

        <div id="notifications" aria-live="polite" aria-atomic="true">
            <div id="video-recognition-notification" class="notification video-related-notif" style="display:none;">
                <div class="notification-header">
                    <div class="notification-title" id="video-recognition-notif-title">
                        <i class="fas fa-file-archive"></i> <?php echo htmlspecialchars($translations['zeep_notif_title'] ?? 'Zeep Life import'); ?>
                    </div>
                    <button type="button" class="close-notification" id="video-recognition-notif-close" title="<?php echo htmlspecialchars($translations['video_notif_snooze_hint'] ?? 'Hide for 12 hours'); ?>" aria-label="<?php echo htmlspecialchars($translations['video_notif_snooze_hint'] ?? 'Close'); ?>"><i class="fas fa-xmark" aria-hidden="true"></i></button>
                </div>
                <div class="notification-content" id="video-recognition-notif-body"><?php echo htmlspecialchars($translations['zeep_notif_body'] ?? ''); ?></div>
                <div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end;">
                    <button type="button" class="excel-btn" id="video-recognition-notif-open" style="background: var(--primary-color); color: #fff; padding: 8px 14px; font-size: 13px;"><?php echo htmlspecialchars($translations['zeep_notif_open'] ?? 'Open'); ?></button>
                </div>
            </div>
        </div>

        <div id="stats-modal" style="display:none;">
            <div class="stats-content stats-experience">
                <header class="md3-modal-head stats-hero" style="flex-direction:column;align-items:stretch;gap:16px;">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;">
                        <div>
                            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;">
                                <h2 style="font-size:18px;font-weight:700;"><?php echo htmlspecialchars($translations['stats_experience_title'] ?? 'Activity & Movement'); ?></h2>
                                <span class="stats-eyebrow" style="display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:999px;background:rgba(76,154,255,0.15);border:1px solid rgba(76,154,255,0.3);font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#4C9AFF;"><i class="fas fa-lock" aria-hidden="true"></i> <?php echo htmlspecialchars($translations['stats_private_eyebrow'] ?? 'Private to your account'); ?></span>
                            </div>
                            <p style="margin:4px 0 0;font-size:13px;color:#9FA3AA;"><?php echo htmlspecialchars($translations['stats_experience_subtitle'] ?? 'Your steps, journeys and milestones, brought together in one calm view.'); ?></p>
                            <div id="stats-freshness" class="stats-freshness" role="status" aria-live="polite"></div>
                        </div>
                        <button class="modal-close" type="button" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>"><span class="material-symbols-rounded" aria-hidden="true">close</span></button>
                    </div>
                <div class="stats-tabbar" role="tablist" aria-label="<?php echo htmlspecialchars($translations['stats_sections'] ?? 'Statistics sections'); ?>">
                    <button type="button" class="stats-tab stats-tab--overview is-active" role="tab" aria-selected="true" data-stats-tab="overview"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i><span><?php echo htmlspecialchars($translations['stats_tab_overview'] ?? 'Overview'); ?></span></button>
                    <button type="button" class="stats-tab stats-tab--activity" role="tab" aria-selected="false" data-stats-tab="activity"><i class="fa-solid fa-shoe-prints" aria-hidden="true"></i><span><?php echo htmlspecialchars($translations['stats_tab_activity'] ?? 'Steps & Health'); ?></span></button>
                    <button type="button" class="stats-tab stats-tab--movement" role="tab" aria-selected="false" data-stats-tab="movement"><i class="fa-solid fa-route" aria-hidden="true"></i><span><?php echo htmlspecialchars($translations['stats_tab_movement'] ?? 'Movement'); ?></span></button>
                    <button type="button" class="stats-tab stats-tab--achievements" role="tab" aria-selected="false" data-stats-tab="achievements"><i class="fa-solid fa-trophy" aria-hidden="true"></i><span><?php echo htmlspecialchars($translations['stats_tab_achievements'] ?? 'Achievements'); ?></span></button>
                    <button type="button" class="stats-tab stats-tab--quality" role="tab" aria-selected="false" data-stats-tab="quality"><i class="fa-solid fa-shield-heart" aria-hidden="true"></i><span><?php echo htmlspecialchars($translations['stats_tab_quality'] ?? 'Data Quality'); ?></span></button>
                </div>
                </header>
                <div class="md3-modal-body stats-panels">
                    <section class="stats-panel is-active" role="tabpanel" data-stats-panel="overview">
                        <div class="stats-metric-grid">
                            <article class="stats-metric"><span><?php echo htmlspecialchars($translations['stats_active_days'] ?? 'Active days'); ?></span><strong id="stats-overview-days">—</strong><small><?php echo htmlspecialchars($translations['stats_active_days_hint'] ?? 'days with recorded movement'); ?></small></article>
                            <article class="stats-metric"><span><?php echo htmlspecialchars($translations['stats_distance'] ?? 'Distance'); ?></span><strong id="stats-overview-distance">—</strong><small><?php echo htmlspecialchars($translations['stats_distance_hint'] ?? 'across your confirmed tracks'); ?></small></article>
                            <article class="stats-metric"><span><?php echo htmlspecialchars($translations['stats_places'] ?? 'Places'); ?></span><strong id="stats-overview-places">—</strong><small><?php echo htmlspecialchars($translations['stats_places_hint'] ?? 'places you spent time in'); ?></small></article>
                            <article class="stats-metric"><span><?php echo htmlspecialchars($translations['stats_earned'] ?? 'Earned'); ?></span><strong id="stats-overview-earned">—</strong><small><?php echo htmlspecialchars($translations['stats_earned_hint'] ?? 'milestones, visible only to you'); ?></small></article>
                        </div>
                        <div class="stats-overview-split">
                            <article class="stats-card stats-spotlight">
                                <span class="stats-card-kicker"><?php echo htmlspecialchars($translations['stats_this_year'] ?? 'This year'); ?></span>
                                <h3><?php echo htmlspecialchars($translations['stats_steps_this_year'] ?? 'Steps in motion'); ?></h3>
                                <strong id="stats-overview-steps">—</strong>
                                <p id="stats-overview-average"></p>
                            </article>
                            <article class="stats-card stats-reach-card">
                                <div class="stats-reach-head">
                                    <h3><i class="fas fa-globe" aria-hidden="true"></i> <?php echo htmlspecialchars($translations['stats_reach'] ?? 'Your reach'); ?></h3>
                                    <span id="stats-overview-reach-count" class="stats-reach-count" hidden></span>
                                </div>
                                <div id="stats-overview-reach" class="stats-reach"></div>
                            </article>
                        </div>
                    </section>
                    <section class="stats-panel" role="tabpanel" data-stats-panel="activity" hidden>
                        <div class="stats-grid">
                            <section class="stats-card" id="stats-yearly-share-card">
                                <h3 class="stats-card-title stats-card-title--steps"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i><?php echo htmlspecialchars($translations['statistics_yearly_distribution'] ?? 'Yearly Step Share'); ?></h3>
                                <div class="stats-yearly-share">
                                    <div class="stats-yearly-donut-wrap">
                                        <canvas id="stats-years-chart"></canvas>
                                        <div class="stats-yearly-donut-center" id="stats-yearly-donut-center" aria-hidden="true">
                                            <strong id="stats-yearly-donut-pct">—</strong>
                                            <span id="stats-yearly-donut-steps">—</span>
                                        </div>
                                    </div>
                                    <div class="stats-yearly-metrics" id="stats-yearly-metrics"></div>
                                </div>
                            </section>
                            <section class="stats-card">
                                <h3 id="stats-monthly-title" class="stats-card-title stats-card-title--calendar"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i><?php echo htmlspecialchars($translations['statistics_monthly_intensity'] ?? 'Monthly Step Intensity'); ?></h3>
                                <div class="stats-chart-wrap"><canvas id="stats-months-chart"></canvas></div>
                            </section>
                        </div>
                        <p id="stats-empty" style="display:none;"><?php echo htmlspecialchars($translations['statistics_no_data'] ?? 'No step data'); ?></p>
                    </section>
                    <section class="stats-panel" role="tabpanel" data-stats-panel="movement" hidden>
                        <div class="stats-metric-grid">
                            <article class="stats-metric"><span><?php echo htmlspecialchars($translations['stats_route_distance'] ?? 'Route distance'); ?></span><strong id="stats-movement-distance">—</strong><small><?php echo htmlspecialchars($translations['stats_confirmed_only'] ?? 'confirmed history only'); ?></small></article>
                            <article class="stats-metric"><span><?php echo htmlspecialchars($translations['stats_trips'] ?? 'Trips'); ?></span><strong id="stats-movement-trips">—</strong><small><?php echo htmlspecialchars($translations['stats_avg_trip'] ?? 'average journey'); ?><span id="stats-movement-trip-avg"></span></small></article>
                            <article class="stats-metric"><span><?php echo htmlspecialchars($translations['stats_active_time'] ?? 'Active time'); ?></span><strong id="stats-movement-time">—</strong><small><?php echo htmlspecialchars($translations['stats_active_time_hint'] ?? 'recorded movement time'); ?></small></article>
                            <article class="stats-metric"><span><?php echo htmlspecialchars($translations['stats_top_place'] ?? 'Most visited'); ?></span><strong id="stats-movement-place">—</strong><small id="stats-movement-place-meta"></small></article>
                        </div>
                        <article class="stats-card">
                            <h3><i class="fas fa-calendar-week" aria-hidden="true"></i> <?php echo htmlspecialchars($translations['stats_week_rhythm'] ?? 'Weekly rhythm'); ?></h3>
                            <div id="stats-week-rhythm" class="stats-week-rhythm"></div>
                        </article>
                    </section>
                    <section class="stats-panel" role="tabpanel" data-stats-panel="achievements" hidden>
                        <div class="stats-achievement-head">
                            <div><span class="stats-card-kicker"><?php echo htmlspecialchars($translations['stats_earned_only'] ?? 'Earned only'); ?></span><h3><?php echo htmlspecialchars($translations['stats_milestones'] ?? 'Milestones you unlocked'); ?></h3></div>
                            <strong id="stats-achievement-count" class="stats-achievement-count">—</strong>
                        </div>
                        <div id="stats-ach-year-filters" aria-label="Filter by year"></div>
                        <div id="stats-achievement-grid" class="stats-ach-year-stack"></div>
                        <p id="stats-achievement-empty" class="stats-empty-state" hidden><?php echo htmlspecialchars($translations['stats_no_achievements'] ?? 'Your first milestone is still ahead. Keep moving — unlocked achievements will appear here.'); ?></p>
                    </section>
                    <section class="stats-panel" role="tabpanel" data-stats-panel="quality" hidden>
                        <div class="stats-metric-grid">
                            <article class="stats-metric"><span><?php echo htmlspecialchars($translations['stats_coverage'] ?? 'Coverage'); ?></span><strong id="stats-quality-coverage">—</strong><small><?php echo htmlspecialchars($translations['stats_coverage_hint'] ?? 'days with data in range'); ?></small></article>
                            <article class="stats-metric"><span><?php echo htmlspecialchars($translations['stats_accuracy'] ?? 'Accuracy'); ?></span><strong id="stats-quality-accuracy">—</strong><small><?php echo htmlspecialchars($translations['stats_accuracy_hint'] ?? 'average reported radius'); ?></small></article>
                            <article class="stats-metric"><span><?php echo htmlspecialchars($translations['stats_latest_point'] ?? 'Latest point'); ?></span><strong id="stats-quality-latest">—</strong><small><?php echo htmlspecialchars($translations['stats_latest_point_hint'] ?? 'last accepted sample'); ?></small></article>
                            <article class="stats-metric"><span><?php echo htmlspecialchars($translations['stats_devices'] ?? 'Devices'); ?></span><strong id="stats-quality-devices">—</strong><small><?php echo htmlspecialchars($translations['stats_devices_hint'] ?? 'paired sources'); ?></small></article>
                        </div>
                        <article class="stats-card">
                            <h3><span class="material-symbols-rounded" aria-hidden="true">hub</span> <?php echo htmlspecialchars($translations['stats_sources'] ?? 'Sources'); ?></h3>
                            <div id="stats-quality-sources" class="stats-sources"></div>
                            <p class="stats-note"><?php echo htmlspecialchars($translations['stats_quality_note'] ?? 'Quality is computed from your own samples. Nothing here is shared, sold or used to infer transport.'); ?></p>
                        </article>
                    </section>
                </div>
            </div>
        </div>
        <!-- Info Modal -->
        <div id="info-modal" style="display:none;">
            <div class="info-content">
                <div class="md3-modal-head">
                    <div class="md3-modal-head-main">
                        <div class="md3-modal-icon"><i class="fas fa-circle-info" aria-hidden="true"></i></div>
                        <div><h2><?php echo htmlspecialchars($translations['app_info'] ?? 'Application Information'); ?></h2></div>
                    </div>
                    <button class="modal-close" type="button" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>"><span class="material-symbols-rounded" aria-hidden="true">close</span></button>
                </div>
                <div class="md3-modal-body info-scroll">
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-card-icon">
                            <i class="fas fa-flag" style="font-size:24px" aria-hidden="true"></i>
                        </div>
<h3><?php echo htmlspecialchars($translations['info_card_tracking_title'] ?? 'Activity Tracking'); ?></h3>
                        <p><?php echo htmlspecialchars($translations['info_card_tracking_text'] ?? 'Log your daily steps, distance walked, and calories burned with detailed statistics.'); ?></p>
                    </div>
                    <div class="info-card">
                        <div class="info-card-icon">
                            <i class="fas fa-chart-column" style="font-size:24px" aria-hidden="true"></i>
                        </div>
                        <h3><?php echo htmlspecialchars($translations['info_card_charts_title'] ?? 'Data Visualization'); ?></h3>
                        <p><?php echo htmlspecialchars($translations['info_card_charts_text'] ?? 'Visualize trends with interactive bar and line charts across different time periods.'); ?></p>
                    </div>
                    <div class="info-card">
                        <div class="info-card-icon">
                            <i class="fas fa-calendar-days" style="font-size:24px" aria-hidden="true"></i>
                        </div>
                        <h3><?php echo htmlspecialchars($translations['info_card_calendar_title'] ?? 'Goal Calendar'); ?></h3>
                        <p><?php echo htmlspecialchars($translations['info_card_calendar_text'] ?? 'Track your progress with calendar view and visually see goal achievement indicators.'); ?></p>
                    </div>
                    <div class="info-card">
                        <div class="info-card-icon">
                            <i class="fas fa-house" style="font-size:24px" aria-hidden="true"></i>
                        </div>
                        <h3><?php echo htmlspecialchars($translations['info_card_views_title'] ?? 'Flexible Layouts'); ?></h3>
                        <p><?php echo htmlspecialchars($translations['info_card_views_text'] ?? 'Switch between Year, Month, and Week views to analyze your data from different perspectives.'); ?></p>
                    </div>
                    <div class="info-card">
                        <div class="info-card-icon">
                            <i class="fas fa-user-group" style="font-size:24px" aria-hidden="true"></i>
                        </div>
                        <h3><?php echo htmlspecialchars($translations['info_card_metrics_title'] ?? 'Metric Options'); ?></h3>
                        <p><?php echo htmlspecialchars($translations['info_card_metrics_text'] ?? 'Select between Steps, Distance, and Calories to focus on the metrics that matter for your goals.'); ?></p>
                    </div>
                    <div class="info-card">
                        <div class="info-card-icon">
                            <i class="fas fa-download" style="font-size:24px" aria-hidden="true"></i>
                        </div>
                        <h3><?php echo htmlspecialchars($translations['info_card_backup_title'] ?? 'Backup & Restore'); ?></h3>
                        <p><?php echo htmlspecialchars($translations['info_card_backup_text'] ?? 'Keep your data safe with automatic backups and the ability to restore to any previous point.'); ?></p>
                    </div>
                <div class="info-card">
                        <div class="info-card-icon">
                            <i class="fas fa-mobile-screen-button" style="font-size:24px" aria-hidden="true"></i>
                        </div>
                        <h3><?php echo htmlspecialchars($translations['mobile_app_modal_title'] ?? 'Mobile App'); ?></h3>
                        <p><?php echo htmlspecialchars($translations['info_card_mobile_text'] ?? 'Install the companion Android app to sync steps and health data from your phone to this dashboard.'); ?></p>
                    </div>
                </div>
                </div>
            </div>
        </div>

        <!-- Mobile App Modal -->
        <style>
            /* Mobile pairing modal — shared layout with the Spends companion modal */
            #mobile-app-modal { position:fixed; inset:0; align-items:center; justify-content:center; padding:16px; z-index:10040; background:rgba(10,14,18,.48); -webkit-backdrop-filter:blur(14px); backdrop-filter:blur(14px); box-sizing:border-box; }
            #mobile-app-modal .mobile-app-content { position:relative; max-width:800px; width:min(800px,calc(100vw - 32px)); max-height:calc(100dvh - 32px); overflow:auto; padding:26px 26px 22px; border:1px solid var(--border-color); border-radius:18px; background:color-mix(in srgb,var(--modal-bg) 92%,var(--surface-solid)); box-shadow:var(--shadow-soft); box-sizing:border-box; }
            #mobile-app-modal .modal-close { position:static; }
            #mobile-app-modal .ma-header { margin:0 0 20px; padding-right:44px; }
            #mobile-app-modal .ma-header h1 { font-size:22px; margin:0 0 6px; font-weight:700; letter-spacing:-.3px; color:var(--text-color); }
            #mobile-app-modal .ma-header p { margin:0; font-size:14px; line-height:1.5; color:var(--muted-text); }
            #mobile-app-modal .ma-panel { margin:0 0 18px; padding:14px 16px; border:1px solid var(--border-color); border-radius:var(--radius-md); background:var(--group-block-bg); }
            #mobile-app-modal .ma-panel-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin:0 0 6px; }
            #mobile-app-modal .ma-panel-head h3 { margin:0; font-size:15px; color:var(--text-color); }
            #mobile-app-modal .ma-empty { margin:4px 0 0; font-size:12px; opacity:.7; }
            #mobile-app-modal .ma-device-list { list-style:none; margin:0; padding:0; }
            #mobile-app-modal .ma-device { padding:10px 0; border-top:1px solid color-mix(in srgb,var(--border-color) 70%,transparent); }
            #mobile-app-modal .ma-device-top { display:flex; align-items:center; justify-content:space-between; gap:8px; }
            #mobile-app-modal .ma-device-name { font-size:13px; font-weight:700; color:var(--text-color); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
            #mobile-app-modal .ma-device-state { flex:0 0 auto; font-size:11px; opacity:.75; }
            #mobile-app-modal .ma-device-meta { font-size:11px; opacity:.7; margin:2px 0 8px; }
            #mobile-app-modal .ma-pager { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:10px; padding-top:10px; border-top:1px solid color-mix(in srgb,var(--border-color) 70%,transparent); }
            #mobile-app-modal .ma-pager .ma-btn { min-width:88px; }
            #mobile-app-modal .ma-pager-info { font-size:11px; opacity:.75; font-variant-numeric:tabular-nums; }
            #mobile-app-modal .ma-idle-hint { margin:8px 0 0; font-size:11px; line-height:1.4; opacity:.6; }
            @media (max-width:420px) {
                #mobile-app-modal .ma-pager .ma-btn { min-width:0; padding:6px 10px; }
                #mobile-app-modal .ma-pager-info { font-size:10.5px; }
            }
            #mobile-app-modal .ma-btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:6px 12px; border:1px solid var(--border-color); border-radius:var(--radius-sm); background:var(--surface-solid); color:var(--primary-text); font-family:inherit; font-size:12px; font-weight:700; cursor:pointer; }
            #mobile-app-modal .ma-btn:hover { background:color-mix(in srgb,var(--primary-color) 12%,var(--surface-solid)); }
            #mobile-app-modal .ma-btn:disabled { opacity:.6; cursor:wait; }
            #mobile-app-modal .ma-btn-danger { border-color:color-mix(in srgb,#ef4444 45%,var(--border-color)); background:color-mix(in srgb,#ef4444 12%,var(--surface-solid)); color:var(--danger-text); }
            #mobile-app-modal .ma-grid { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1.15fr); gap:20px; align-items:stretch; }
            #mobile-app-modal .ma-action { display:flex; flex-direction:column; align-items:center; gap:10px; min-width:0; text-align:center; padding:20px; border:1px solid var(--border-color); border-radius:var(--radius-md); background:var(--group-block-bg); }
            #mobile-app-modal .ma-action-icon { display:flex; align-items:center; justify-content:center; width:64px; height:64px; border-radius:50%; background:color-mix(in srgb,var(--primary-color) 14%,transparent); color:var(--primary-text); }
            #mobile-app-modal .ma-action h3 { margin:0; font-size:16px; color:var(--text-color); }
            #mobile-app-modal .ma-action > p { margin:0; font-size:13px; line-height:1.45; color:var(--muted-text); }
            #mobile-app-modal .ma-primary { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:10px 16px; border:0; border-radius:var(--radius-sm); background:var(--primary-fill); color:var(--primary-ink); font-family:inherit; font-size:13px; font-weight:700; cursor:pointer; }
            #mobile-app-modal .ma-primary:disabled { opacity:.6; cursor:wait; }
            #mobile-app-modal .ma-pair-fields { display:grid; gap:10px; width:100%; margin:4px 0 0; text-align:left; }
            #mobile-app-modal .ma-pair-row { display:flex; gap:8px; align-items:stretch; }
            #mobile-app-modal .ma-pair-field { flex:1; min-width:0; }
            #mobile-app-modal .ma-pair-field label { display:block; margin:0 0 4px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--muted-text); }
            #mobile-app-modal .ma-pair-field pre { margin:0; padding:9px 11px; min-height:40px; box-sizing:border-box; border:1px solid var(--border-color); border-radius:10px; background:var(--surface-solid); color:var(--text-color); font:12px/1.45 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; word-break:break-all; white-space:pre-wrap; }
            #mobile-app-modal .ma-error { margin:6px 0 0; font-size:12px; color:var(--danger-text); }
            #mobile-app-modal .ma-download-row { display:flex; flex-wrap:wrap; gap:8px; justify-content:center; }
            #mobile-app-modal .ma-download, #mobile-app-modal .ma-play-protect { display:inline-flex; align-items:center; gap:8px; margin:6px 0 0; padding:10px 14px; border:1px solid color-mix(in srgb,var(--primary-color) 45%,var(--border-color)); border-radius:var(--radius-sm); background:color-mix(in srgb,var(--primary-color) 12%,var(--surface-solid)); color:var(--primary-text); font-size:13px; font-weight:700; text-decoration:none; cursor:pointer; }
            #mobile-app-modal .ma-download:hover, #mobile-app-modal .ma-play-protect:hover { background:color-mix(in srgb,var(--primary-color) 22%,var(--surface-solid)); }
            #mobile-app-modal .ma-meta { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--muted-text); }
            #mobile-app-modal .ma-features { display:flex; flex-direction:column; justify-content:space-between; gap:10px; min-width:0; min-height:100%; }
            #mobile-app-modal .ma-feature { display:flex; flex:1 1 0; align-items:center; gap:14px; min-height:76px; padding:14px; border:1px solid var(--border-color); border-radius:var(--radius-md); box-sizing:border-box; }
            #mobile-app-modal .ma-feature:hover { background:color-mix(in srgb,var(--surface-solid) 55%,transparent); border-color:color-mix(in srgb,var(--primary-color) 26%,var(--border-color)); }
            #mobile-app-modal .ma-feature-icon { display:flex; align-items:center; justify-content:center; flex:0 0 38px; width:38px; height:38px; border-radius:var(--radius-sm); background:color-mix(in srgb,var(--primary-color) 14%,transparent); color:var(--primary-text); }
            #mobile-app-modal .ma-feature-text { min-width:0; text-align:left; }
            #mobile-app-modal .ma-feature-text h4 { margin:0 0 3px; font-size:13.5px; font-weight:700; color:var(--text-color); }
            #mobile-app-modal .ma-feature-text p { margin:0; font-size:12px; line-height:1.4; color:var(--muted-text); }
            #mobile-app-modal .ma-copy-btn { flex:0 0 auto; align-self:flex-end; width:40px; height:40px; border:1px solid var(--border-color); border-radius:10px; background:var(--surface-solid); color:var(--primary-text); display:inline-flex; align-items:center; justify-content:center; cursor:pointer; }
            #mobile-app-modal .ma-copy-btn:hover { background:color-mix(in srgb,var(--primary-color) 12%,var(--surface-solid)); }
            #mobile-app-modal .ma-copy-btn.is-copied { background:#166534; border-color:#166534; color:#fff; }
            @media (max-width:700px) {
                #mobile-app-modal .ma-grid { grid-template-columns:minmax(0,1fr); }
                #mobile-app-modal .ma-features { min-height:0; }
                #mobile-app-modal .ma-feature { flex:0 0 auto; }
                #mobile-app-modal .ma-pair-row { flex-direction:column; }
                #mobile-app-modal .ma-copy-btn { width:100%; height:36px; }
            }
        </style>
        <div id="mobile-app-modal" style="display:none;">
            <div class="mobile-app-content">
                <div class="md3-modal-head">
                    <div class="md3-modal-head-main">
                        <div class="md3-modal-icon"><i class="fas fa-mobile-screen-button" aria-hidden="true"></i></div>
                        <div>
                            <h2><?php echo htmlspecialchars($translations['mobile_app_modal_title'] ?? 'Mobile App'); ?></h2>
                            <p><?php echo htmlspecialchars($translations['info_card_mobile_text'] ?? 'Install the companion Android app to sync steps and health data from your phone to this dashboard.'); ?></p>
                        </div>
                    </div>
                    <button class="modal-close" type="button" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>"><span class="material-symbols-rounded" aria-hidden="true">close</span></button>
                </div>
                <div class="md3-modal-body">
                <section class="ma-panel" id="ma-devices">
                    <div class="ma-panel-head">
                        <h3><?php echo htmlspecialchars($translations['mobile_app_devices_title'] ?? 'Connected devices'); ?></h3>
                        <button type="button" id="mobile-app-devices-refresh" class="ma-btn ma-btn-sm"><?php echo htmlspecialchars($translations['mobile_app_devices_refresh'] ?? 'Refresh'); ?></button>
                    </div>
                    <p class="ma-empty" id="mobile-app-devices-empty"><?php echo htmlspecialchars($translations['mobile_app_devices_empty'] ?? 'No devices connected yet.'); ?></p>
                    <ul class="ma-device-list" id="mobile-app-devices-list"></ul>
                    <div class="ma-pager" id="mobile-app-devices-pager" hidden>
                        <button type="button" class="ma-btn ma-btn-sm" id="mobile-app-devices-prev"><?php echo htmlspecialchars($translations['mobile_app_devices_prev'] ?? 'Previous'); ?></button>
                        <span class="ma-pager-info" id="mobile-app-devices-page-info"></span>
                        <button type="button" class="ma-btn ma-btn-sm" id="mobile-app-devices-next"><?php echo htmlspecialchars($translations['mobile_app_devices_next'] ?? 'Next'); ?></button>
                    </div>
                    <p class="ma-idle-hint" id="mobile-app-devices-idle"></p>
                    <p class="ma-error" id="mobile-app-devices-error" hidden></p>
                </section>

                <div class="ma-grid">
                    <div class="ma-action">
                        <div class="ma-action-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                        </div>
                        <h3><?php echo htmlspecialchars($translations['mobile_app_connect_title'] ?? 'Connect a new device'); ?></h3>
                        <p><?php echo htmlspecialchars($translations['mobile_app_connect_desc'] ?? 'Create a connection link and open it on your phone. The app connects automatically.'); ?></p>
                        <button type="button" id="mobile-app-connect" class="ma-primary"><?php echo htmlspecialchars($translations['mobile_app_connect_button'] ?? 'Create a pairing token'); ?></button>

                        <div class="ma-pair-fields" id="mobile-app-pair-fields" hidden>
                            <div class="ma-pair-row">
                                <div class="ma-pair-field">
                                    <label><?php echo htmlspecialchars($translations['manual_pair_link_label'] ?? 'Connection link'); ?></label>
                                    <pre id="mobile-app-pair-link"></pre>
                                </div>
                                <button type="button" class="ma-copy-btn" data-copy-target="mobile-app-pair-link" aria-label="<?php echo htmlspecialchars($translations['copy'] ?? 'Copy'); ?>">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                </button>
                            </div>
                        </div>

                        <p class="ma-error" id="mobile-app-pair-error" hidden></p>

                        <?php if ($stepmonitorAvailable): ?>
                        <div class="ma-download-row">
                            <a class="ma-download" href="/downloads/stepmonitor.apk" download="<?php echo htmlspecialchars($stepmonitorApkDownloadName); ?>">
                                <i class="fas fa-download" style="font-size:18px" aria-hidden="true"></i>
                                <?php echo htmlspecialchars($translations['mobile_app_download'] ?? 'Download mobile app'); ?>
                            </a>
                            <button type="button" id="mobile-app-play-protect-guide" class="ma-play-protect">
                                <i class="fas fa-shield-halved" style="font-size:18px" aria-hidden="true"></i>
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
                                <i class="fas fa-heart-pulse" style="font-size:20px" aria-hidden="true"></i>
                            </div>
                            <div class="ma-feature-text">
                                <h4><?php echo htmlspecialchars($translations['mobile_app_feature_sync_title'] ?? 'Health Connect Sync'); ?></h4>
                                <p><?php echo htmlspecialchars($translations['mobile_app_feature_sync_desc'] ?? 'Automatically sync your steps with Health Connect on Android.'); ?></p>
                            </div>
                        </div>

                        <div class="ma-feature">
                            <div class="ma-feature-icon">
                                <i class="fas fa-power-off" style="font-size:20px" aria-hidden="true"></i>
                            </div>
                            <div class="ma-feature-text">
                                <h4><?php echo htmlspecialchars($translations['mobile_app_feature_offline_title'] ?? 'Offline Tracking'); ?></h4>
                                <p><?php echo htmlspecialchars($translations['mobile_app_feature_offline_desc'] ?? 'Track steps even without an internet connection.'); ?></p>
                            </div>
                        </div>

                        <div class="ma-feature">
                            <div class="ma-feature-icon">
                                <i class="fas fa-shield-halved" style="font-size:20px" aria-hidden="true"></i>
                            </div>
                            <div class="ma-feature-text">
                                <h4><?php echo htmlspecialchars($translations['mobile_app_feature_secure_title'] ?? 'Secure Token Auth'); ?></h4>
                                <p><?php echo htmlspecialchars($translations['mobile_app_feature_secure_desc'] ?? 'Use a generated token for secure, password-free authentication.'); ?></p>
                            </div>
                        </div>

                        <div class="ma-feature">
                            <div class="ma-feature-icon">
                                <i class="fas fa-user-group" style="font-size:20px" aria-hidden="true"></i>
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
        </div>

        <!-- Settings Modal -->
        <div id="settings-modal" style="display:none;">
            <div class="settings-content">
                <div class="md3-modal-head">
                    <div class="md3-modal-head-main">
                        <div class="md3-modal-icon"><i class="fas fa-gear" aria-hidden="true"></i></div>
                        <div>
                            <h2><?php echo htmlspecialchars($translations['settings_title']); ?></h2>
                            <p><?php echo htmlspecialchars($translations['settings_subtitle'] ?? 'Configure profile biometrics, map styling, exports, and backups'); ?></p>
                        </div>
                    </div>
                    <button id="close-settings" class="modal-close" type="button" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>"><span class="material-symbols-rounded" aria-hidden="true">close</span></button>
                </div>
                <div class="settings-layout">
                <div class="settings-tab-bar" role="tablist">
                    <?php
                    $settingsTabs = [
                        ['profile', 'fa-user-edit', $translations['settings_tab_profile'] ?? $translations['settings_title'] ?? 'Settings', true],
                        ['chart', 'fa-chart-area', $translations['settings_tab_chart'] ?? 'Chart', false],
                        ['map', 'fa-map-location-dot', $translations['map_settings_title'] ?? 'Map', false],
                        ['render', 'fa-film', $translations['map_render_title'] ?? 'Poster and video', false],
                        ['points', 'fa-location-crosshairs', $translations['points_title'] ?? 'Location points', false],
                        ['location', 'fa-earth-europe', $translations['location_data_title'] ?? 'Location data', false],
                        ['family', 'fa-people-roof', $translations['family_title'] ?? 'Family', false],
                        ['achievements', 'fa-trophy', $translations['achievement_title'] ?? 'Achievements', false],
                        ['backups', 'fa-database', $translations['manage_backups'] ?? 'Manage Backups', false],
                    ];
                    foreach ($settingsTabs as [$tabId, $tabIcon, $tabLabel, $tabActive]):
                    ?>
                    <button type="button" class="settings-tab<?php echo $tabActive ? ' active' : ''; ?>" data-settings-tab="<?php echo htmlspecialchars($tabId); ?>" role="tab" aria-selected="<?php echo $tabActive ? 'true' : 'false'; ?>" title="<?php echo htmlspecialchars($tabLabel); ?>">
                        <i class="fas <?php echo htmlspecialchars($tabIcon); ?>" aria-hidden="true"></i>
                        <span class="settings-tab-label truncate"><?php echo htmlspecialchars($tabLabel); ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <div class="settings-main">
                <div class="settings-main-scroll">
                <div id="settings-panel-profile" class="settings-tab-panel active" role="tabpanel">
                    <div class="settings-pane-title">
                        <h3><?php echo htmlspecialchars($translations['settings_profile_biometrics'] ?? 'Profile & Biometrics'); ?></h3>
                        <p><?php echo htmlspecialchars($translations['settings_profile_hint'] ?? 'Used to calculate stride distance, daily targets, and calorie estimates.'); ?></p>
                    </div>
                    <form id="settings-form" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="settings" value="1">
                        <div class="settings-tile">
                            <div class="tile-title"><?php echo htmlspecialchars($translations['occupation_title'] ?? 'Occupation'); ?></div>
                            <hr>
                            <div class="tile-content">
                                <span class="tile-icon"><i class="fas fa-briefcase"></i></span>
                                <select name="occupation" aria-label="<?php echo htmlspecialchars($translations['occupation_title'] ?? 'Occupation'); ?>">
                                    <?php foreach (steps_occupation_values() as $occupation): ?>
                                        <option value="<?php echo htmlspecialchars($occupation); ?>" <?php echo ($userSettings['occupation'] ?? 'prefer_not_to_say') === $occupation ? 'selected' : ''; ?>><?php echo htmlspecialchars($translations['occupation_' . $occupation] ?? $occupation); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="settings-tile">
                            <div class="tile-title"><?php echo htmlspecialchars($translations['goal_steps']); ?></div>
                            <hr>
                            <div class="tile-content">
                                <span class="tile-icon"><i class="fas fa-walking"></i></span>
                                <input type="text" name="goal_steps" value="<?php echo $userSettings['goal_steps'] / 1000; ?>" pattern="[0-9.,]*" inputmode="decimal">
                                <span class="tile-unit"><?php echo htmlspecialchars($translations['thousands'] ?? 'k'); ?></span>
                            </div>
                        </div>
                        <div class="settings-tile">
                            <div class="tile-title"><?php echo htmlspecialchars($translations['height']); ?></div>
                            <hr>
                            <div class="tile-content">
                                <span class="tile-icon"><i class="fas fa-ruler-vertical"></i></span>
                                <input type="text" name="height" value="<?php echo $userSettings['height']; ?>" pattern="\d*" inputmode="numeric">
                                <span class="tile-unit">cm</span>
                            </div>
                        </div>
                        <div class="settings-tile">
                            <div class="tile-title"><?php echo htmlspecialchars($translations['weight']); ?></div>
                            <hr>
                            <div class="tile-content">
                                <span class="tile-icon"><i class="fas fa-weight"></i></span>
                                <input type="text" name="weight" value="<?php echo $userSettings['weight']; ?>" pattern="\d*" inputmode="numeric">
                                <span class="tile-unit">kg</span>
                            </div>
                        </div>
                        <div class="settings-tile">
                            <div class="tile-title"><?php echo htmlspecialchars($translations['stride_length']); ?></div>
                            <hr>
                            <div class="tile-content">
                                <span class="tile-icon"><i class="fas fa-ruler"></i></span>
                                <input type="text" name="stride_length" value="<?php echo $userSettings['stride_length']; ?>" pattern="\d*" inputmode="numeric">
                                <span class="tile-unit">cm</span>
                            </div>
                        </div>
                        <div class="settings-tile">
                            <div class="tile-title"><?php echo htmlspecialchars($translations['age']); ?> (<?php echo $userSettings['age']; ?>)</div>
                            <hr>
                            <div class="tile-content">
                                <span class="tile-icon"><i class="fas fa-birthday-cake"></i></span>
                                <input type="date" name="birth_date" value="<?php echo $userSettings['birth_date']; ?>">
                                <span class="tile-unit"><?php echo htmlspecialchars($translations['year'] ?? 'y'); ?></span>
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
                <div id="settings-panel-map" class="settings-tab-panel" role="tabpanel">
                    <div class="settings-map-grid">
                        <section class="settings-map-section">
                            <h3><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($translations['map_settings_markers'] ?? 'Markers'); ?></h3>
                            <p class="settings-map-hint"><?php echo htmlspecialchars($translations['map_settings_hint'] ?? 'Tune how location points and clusters are drawn on the map.'); ?></p>
                            <label class="map-slider">
                                <span class="map-slider-head"><span><?php echo htmlspecialchars($translations['map_point_size'] ?? 'Point size'); ?></span><output data-for="pointSize">5 px</output></span>
                                <input type="range" id="ma-map-point-size" min="2" max="14" step="1" value="5">
                            </label>
                            <label class="map-slider">
                                <span class="map-slider-head"><span><?php echo htmlspecialchars($translations['map_opacity'] ?? 'Opacity'); ?></span><output data-for="opacity">85%</output></span>
                                <input type="range" id="ma-map-opacity" min="20" max="100" step="5" value="85">
                            </label>
                        </section>
                        <section class="settings-map-section">
                            <h3><i class="fas fa-route"></i> <?php echo htmlspecialchars($translations['map_settings_routes'] ?? 'Routes & heat'); ?></h3>
                            <p class="settings-map-hint"><?php echo htmlspecialchars($translations['map_settings_hint_routes'] ?? 'Control route thickness and heat-map intensity.'); ?></p>
                            <label class="map-slider">
                                <span class="map-slider-head"><span><?php echo htmlspecialchars($translations['map_route_width'] ?? 'Route width'); ?></span><output data-for="routeWidth">4 px</output></span>
                                <input type="range" id="ma-map-route-width" min="1" max="8" step="1" value="4">
                            </label>
                            <label class="map-slider">
                                <span class="map-slider-head"><span><?php echo htmlspecialchars($translations['map_heat_intensity'] ?? 'Heat intensity'); ?></span><output data-for="heat">60%</output></span>
                                <input type="range" id="ma-map-heat-intensity" min="10" max="100" step="5" value="60">
                            </label>
                            <div class="settings-map-actions">
                                <button type="button" id="ma-map-reset" class="settings-map-reset">
                                    <i class="fas fa-arrow-rotate-left" aria-hidden="true"></i> <?php echo htmlspecialchars($translations['map_reset'] ?? 'Reset'); ?>
                                </button>
                            </div>
                        </section>
                        <section class="settings-map-section">
                            <h3><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($translations['map_source_title'] ?? 'Source and tiles'); ?></h3>
                            <label class="settings-field">
                                <span><?php echo htmlspecialchars($translations['family_title'] ?? 'Family'); ?></span>
                                <select id="ma-map-family" data-allow-empty="1">
                                    <option value=""><?php echo htmlspecialchars($translations['map_family_self'] ?? 'My routes'); ?></option>
                                </select>
                            </label>
                            <label class="settings-field">
                                <span><?php echo htmlspecialchars($translations['map_provider'] ?? 'Tiles'); ?></span>
                                <select id="ma-map-provider" aria-label="<?php echo htmlspecialchars($translations['map_provider'] ?? 'Tiles'); ?>"></select>
                            </label>
                            <label class="settings-field">
                                <span><?php echo htmlspecialchars($translations['map_prefetch'] ?? 'Nearby map prefetch'); ?></span>
                                <select id="ma-map-prefetch" aria-label="<?php echo htmlspecialchars($translations['map_prefetch'] ?? 'Nearby map prefetch'); ?>">
                                    <option value="off"><?php echo htmlspecialchars($translations['map_prefetch_off'] ?? 'Off'); ?></option>
                                    <option value="wifi"><?php echo htmlspecialchars($translations['map_prefetch_wifi'] ?? 'Wi-Fi only'); ?></option>
                                    <option value="balanced" selected><?php echo htmlspecialchars($translations['map_prefetch_balanced'] ?? 'Balanced'); ?></option>
                                    <option value="aggressive"><?php echo htmlspecialchars($translations['map_prefetch_aggressive'] ?? 'Aggressive'); ?></option>
                                </select>
                            </label>
                        </section>
                    </div>
                </div>
                <div id="settings-panel-render" class="settings-tab-panel" role="tabpanel">
                    <div class="location-panel render-flow">
                        <nav class="render-stepper" aria-label="<?php echo htmlspecialchars($translations['render_flow_label'] ?? 'Render steps'); ?>">
                            <button type="button" class="render-step is-active" data-render-step="1" aria-current="step">
                                <span class="render-step-index">1</span>
                                <span class="render-step-label"><?php echo htmlspecialchars($translations['render_step_source'] ?? 'Route and period'); ?></span>
                            </button>
                            <button type="button" class="render-step" data-render-step="2">
                                <span class="render-step-index">2</span>
                                <span class="render-step-label"><?php echo htmlspecialchars($translations['render_step_look'] ?? 'Output'); ?></span>
                            </button>
                            <button type="button" class="render-step" data-render-step="3">
                                <span class="render-step-index">3</span>
                                <span class="render-step-label"><?php echo htmlspecialchars($translations['render_step_run'] ?? 'Render'); ?></span>
                            </button>
                        </nav>
                        <section class="location-section render-step-panel is-active" data-render-step-panel="1">
                            <div class="location-section-head"><h3><span class="flow-step">1</span> <?php echo htmlspecialchars($translations['render_step_source'] ?? 'Route and period'); ?></h3></div>
                            <div class="settings-grid-2">
                                <div class="settings-field"><span><?php echo htmlspecialchars($translations['map_from'] ?? 'From'); ?></span><div class="dp-field"><input type="date" id="rd-from" class="dp-native" tabindex="-1" aria-hidden="true"><button type="button" class="dp-trigger" aria-haspopup="dialog" aria-expanded="false"><span class="dp-value"></span><i class="fas fa-calendar-days dp-icon" aria-hidden="true"></i></button><div class="dp-popover" role="dialog" hidden></div></div></div>
                                <div class="settings-field"><span><?php echo htmlspecialchars($translations['map_to'] ?? 'To'); ?></span><div class="dp-field"><input type="date" id="rd-to" class="dp-native" tabindex="-1" aria-hidden="true"><button type="button" class="dp-trigger" aria-haspopup="dialog" aria-expanded="false"><span class="dp-value"></span><i class="fas fa-calendar-days dp-icon" aria-hidden="true"></i></button><div class="dp-popover" role="dialog" hidden></div></div></div>
                            </div>
                            <label class="settings-field"><span><?php echo htmlspecialchars($translations['render_title'] ?? 'Title'); ?></span><input type="text" id="rd-title" maxlength="80"></label>
                            <div class="render-step-nav">
                                <button type="button" class="excel-btn render-next" data-render-goto="2"><?php echo htmlspecialchars($translations['render_next'] ?? 'Next'); ?> <i class="fas fa-arrow-right"></i></button>
                            </div>
                        </section>
                        <section class="location-section render-step-panel" data-render-step-panel="2">
                            <div class="location-section-head"><h3><span class="flow-step">2</span> <?php echo htmlspecialchars($translations['render_step_look'] ?? 'Output'); ?></h3></div>
                            <div class="settings-grid-2">
                                <label class="settings-field"><span><?php echo htmlspecialchars($translations['render_kind'] ?? 'Type'); ?></span>
                                    <select id="rd-kind"><option value="video">MP4</option><option value="png">PNG</option><option value="pdf">PDF</option></select></label>
                                <label class="settings-field"><span><?php echo htmlspecialchars($translations['render_format'] ?? 'Orientation'); ?></span>
                                    <select id="rd-format"><option value="landscape"><?php echo htmlspecialchars($translations['map_render_landscape'] ?? 'Landscape'); ?></option><option value="portrait"><?php echo htmlspecialchars($translations['map_render_portrait'] ?? 'Portrait'); ?></option><option value="square"><?php echo htmlspecialchars($translations['map_render_square'] ?? 'Square'); ?></option></select></label>
                                <label class="settings-field"><span><?php echo htmlspecialchars($translations['render_theme'] ?? 'Theme'); ?></span>
                                    <select id="rd-theme"><option value="dark">Dark</option><option value="light">Light</option><option value="paper">Paper</option></select></label>
                                <label class="settings-field"><span><?php echo htmlspecialchars($translations['render_provider'] ?? 'Base map'); ?></span>
                                    <select id="rd-provider"><option value="esri_dark">Esri Dark</option><option value="esri_imagery">Esri Satellite</option><option value="esri_street">Esri Streets</option><option value="osm">OpenStreetMap</option><option value="opentopomap">OpenTopoMap</option><option value="none"><?php echo htmlspecialchars($translations['render_no_map'] ?? 'No map'); ?></option></select></label>
                                <label class="settings-field" data-video-only><span><?php echo htmlspecialchars($translations['render_camera'] ?? 'Camera'); ?></span>
                                    <select id="rd-camera"><option value="follow"><?php echo htmlspecialchars($translations['render_camera_follow'] ?? 'Follow route'); ?></option><option value="overview"><?php echo htmlspecialchars($translations['render_camera_overview'] ?? 'Overview'); ?></option></select></label>
                                <label class="settings-field"><span><?php echo htmlspecialchars($translations['render_route_color'] ?? 'Route color'); ?></span><input type="color" id="rd-route-color" value="#60a5fa"></label>
                                <label class="settings-field"><span><?php echo htmlspecialchars($translations['render_tint'] ?? 'Map tint'); ?></span><input type="color" id="rd-tint" value="#000000"></label>
                                <label class="settings-field"><span><?php echo htmlspecialchars($translations['render_tint_strength'] ?? 'Tint strength'); ?></span><input type="range" id="rd-tint-opacity" min="0" max="80" value="0"></label>
                                <label class="settings-field"><span><?php echo htmlspecialchars($translations['render_resolution'] ?? 'Resolution'); ?></span>
                                    <select id="rd-edge"><option value="480">480p</option><option value="640" selected>640p</option><option value="800">800p</option><option value="960">960p</option></select></label>
                                <label class="settings-field" data-video-only><span><?php echo htmlspecialchars($translations['render_duration'] ?? 'Duration, s'); ?></span>
                                    <select id="rd-duration"><option value="8">8</option><option value="12" selected>12</option><option value="20">20</option><option value="30">30</option><option value="45">45</option></select></label>
                                <label class="settings-field" data-video-only><span><?php echo htmlspecialchars($translations['render_fps'] ?? 'Frame rate'); ?></span>
                                    <select id="rd-fps"><option value="6">6</option><option value="8" selected>8</option><option value="12">12</option></select></label>
                            </div>
                            <div class="render-step-nav">
                                <button type="button" class="excel-btn render-back" data-render-goto="1"><i class="fas fa-arrow-left"></i> <?php echo htmlspecialchars($translations['render_back'] ?? 'Back'); ?></button>
                                <button type="button" class="excel-btn render-next" data-render-goto="3"><?php echo htmlspecialchars($translations['render_next'] ?? 'Next'); ?> <i class="fas fa-arrow-right"></i></button>
                            </div>
                        </section>
                        <section class="location-section render-step-panel" data-render-step-panel="3">
                            <div class="location-section-head"><h3><span class="flow-step">3</span> <?php echo htmlspecialchars($translations['render_step_run'] ?? 'Render'); ?></h3></div>
                            <div class="settings-actions-row">
                                <button type="button" id="rd-start" class="excel-btn"><i class="fas fa-play"></i> <?php echo htmlspecialchars($translations['render_start'] ?? 'Start'); ?></button>
                                <button type="button" id="rd-cancel" class="excel-btn" disabled><i class="fas fa-stop"></i> <?php echo htmlspecialchars($translations['render_cancel'] ?? 'Cancel'); ?></button>
                            </div>
                            <div class="render-progress" aria-hidden="true"><span id="rd-bar"></span></div>
                            <p id="rd-status" class="location-status" role="status" aria-live="polite"></p>
                            <p class="location-hint"><?php echo htmlspecialchars($translations['render_retention_hint'] ?? 'Rendered files are kept for 1 hour. Use Delete to remove one right away.'); ?></p>
                            <div id="rd-preview" class="render-preview" hidden></div>
                            <div id="rd-jobs" class="render-cards"></div>
                        </section>
                    </div>
                </div>
                <div id="settings-panel-points" class="settings-tab-panel" role="tabpanel">
                    <div class="location-panel">
                        <section class="location-section">
                            <div class="location-section-head"><h3><i class="fas fa-filter"></i> <?php echo htmlspecialchars($translations['points_filters'] ?? 'Filters'); ?></h3></div>
                            <div class="settings-grid-2">
                                <label class="settings-field"><span><?php echo htmlspecialchars($translations['map_from'] ?? 'From'); ?></span><input type="datetime-local" id="pt-from"></label>
                                <label class="settings-field"><span><?php echo htmlspecialchars($translations['map_to'] ?? 'To'); ?></span><input type="datetime-local" id="pt-to"></label>
                                <label class="settings-field"><span><?php echo htmlspecialchars($translations['points_state'] ?? 'Quality'); ?></span>
                                    <select id="pt-state"><option value="" selected><?php echo htmlspecialchars($translations['points_any'] ?? 'Any'); ?></option><option value="accepted">accepted</option><option value="flagged">flagged</option><option value="rejected">rejected</option><option value="pending">pending</option></select></label>
                                <label class="settings-field"><span><?php echo htmlspecialchars($translations['points_source'] ?? 'Source'); ?></span>
                                    <select id="pt-source"><option value="" selected><?php echo htmlspecialchars($translations['points_any'] ?? 'Any'); ?></option><option value="mobile">mobile</option><option value="import">import</option><option value="api">api</option></select></label>
                                <label class="settings-field"><span><?php echo htmlspecialchars($translations['points_lat'] ?? 'Latitude'); ?></span><input type="number" id="pt-lat" step="0.000001" min="-90" max="90" placeholder="53.9"></label>
                                <label class="settings-field"><span><?php echo htmlspecialchars($translations['points_lon'] ?? 'Longitude'); ?></span><input type="number" id="pt-lon" step="0.000001" min="-180" max="180" placeholder="27.56"></label>
                                <label class="settings-field"><span><?php echo htmlspecialchars($translations['points_radius'] ?? 'Radius, km'); ?></span><input type="number" id="pt-radius" step="0.1" min="0.1" max="20000" value="5"></label>
                                <div class="settings-field"><span>&nbsp;</span><button type="button" id="pt-pick" class="excel-btn"><i class="fas fa-location-crosshairs"></i> <?php echo htmlspecialchars($translations['points_pick_map'] ?? 'Pick on map'); ?></button></div>
                            </div>
                            <div class="settings-actions-row">
                                <button type="button" id="pt-search" class="excel-btn"><i class="fas fa-magnifying-glass"></i> <?php echo htmlspecialchars($translations['points_search'] ?? 'Show'); ?></button>
                                <button type="button" id="pt-delete-range" class="excel-btn"><i class="fas fa-trash"></i> <?php echo htmlspecialchars($translations['points_delete_range'] ?? 'Delete all in range'); ?></button>
                            </div>
                        </section>
                        <section class="location-section">
                            <div class="location-section-head"><h3><i class="fas fa-list-check"></i> <?php echo htmlspecialchars($translations['points_list'] ?? 'Points'); ?> <small id="pt-total"></small></h3></div>
                            <div class="settings-actions-row">
                                <label class="location-consent m3-checkbox-wrapper"><input type="checkbox" id="pt-all" class="m3-checkbox-input"><span class="m3-checkbox-box"><svg class="m3-checkbox-svg" viewBox="0 0 16 16"><polyline points="3.5 8.5 6.5 11.5 12.5 4.5"></polyline></svg></span> <?php echo htmlspecialchars($translations['points_select_page'] ?? 'Select page'); ?></label>
                                <button type="button" id="pt-delete" class="excel-btn" disabled><i class="fas fa-trash"></i> <?php echo htmlspecialchars($translations['points_delete_selected'] ?? 'Delete selected'); ?></button>
                            </div>
                            <div class="pager" id="pt-pager">
                                <button type="button" id="pt-first" class="excel-btn" aria-label="First"><i class="fas fa-angles-left"></i></button>
                                <button type="button" id="pt-prev" class="excel-btn" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>
                                <span class="pager-pages" id="pt-pages"></span>
                                <button type="button" id="pt-next" class="excel-btn" aria-label="Next"><i class="fas fa-chevron-right"></i></button>
                                <button type="button" id="pt-last" class="excel-btn" aria-label="Last"><i class="fas fa-angles-right"></i></button>
                                <select id="pt-size" aria-label="Page size"><option value="25">25</option><option value="50" selected>50</option><option value="100">100</option><option value="200">200</option></select>
                            </div>
                            <p id="pt-status" class="location-status" role="status" aria-live="polite"></p>
                            <div id="pt-list" class="points-list"></div>
                        </section>
                    </div>
                </div>
                <div id="settings-panel-location" class="settings-tab-panel" role="tabpanel">
                    <div id="settings-loc-subtabs" role="tablist">
                        <button type="button" class="locdata-pill is-active" data-locsec="stats"><?php echo htmlspecialchars($translations['locdata_stats'] ?? 'Statistics & Import'); ?></button>
                        <button type="button" class="locdata-pill" data-locsec="export"><?php echo htmlspecialchars($translations['locdata_export'] ?? 'Export & Views'); ?></button>
                        <button type="button" class="locdata-pill" data-locsec="corrections"><?php echo htmlspecialchars($translations['locdata_corrections'] ?? 'Corrections & Places'); ?></button>
                        <button type="button" class="locdata-pill" data-locsec="privacy"><?php echo htmlspecialchars($translations['locdata_privacy'] ?? 'Notifications & Privacy'); ?></button>
                    </div>
                    <div class="location-panel">
                        <section class="location-section loc-stats-section" data-locgroup="stats">
                            <div class="location-section-head loc-stats-head">
                                <div class="loc-stats-head-left">
                                    <h3><span class="material-symbols-rounded loc-stats-icon" aria-hidden="true">bar_chart</span> <?php echo htmlspecialchars($translations['location_stats_title'] ?? 'Movement statistics'); ?></h3>
                                    <span id="location-stats-period" class="loc-period-badge font-mono" hidden></span>
                                </div>
                                <div class="location-actions loc-stats-actions">
                                    <label class="location-tz"><span><?php echo htmlspecialchars($translations['timeline_timezone'] ?? 'Timezone'); ?></span>
                                        <select id="location-stats-tz" aria-label="<?php echo htmlspecialchars($translations['timeline_timezone'] ?? 'Timezone'); ?>">
                                            <option value="UTC">UTC</option>
                                            <option value="Europe/Warsaw">Europe/Warsaw</option>
                                            <option value="Europe/Kyiv">Europe/Kyiv</option>
                                            <option value="Europe/Berlin">Europe/Berlin</option>
                                            <option value="Europe/Prague">Europe/Prague</option>
                                            <option value="Europe/London">Europe/London</option>
                                            <option value="America/New_York">America/New_York</option>
                                            <option value="America/Los_Angeles">America/Los_Angeles</option>
                                            <option value="Asia/Tokyo">Asia/Tokyo</option>
                                        </select>
                                    </label>
                                    <button type="button" id="location-stats-refresh" class="excel-btn loc-recompute-btn"><span class="material-symbols-rounded icon-sm" aria-hidden="true">refresh</span> <?php echo htmlspecialchars($translations['location_stats_refresh'] ?? 'Recompute'); ?></button>
                                </div>
                            </div>
                            <p id="location-stats-status" class="location-status" role="status" aria-live="polite"></p>
                            <div id="location-stats-cards" class="location-cards loc-kpi-grid"></div>
                            <div id="location-stats-warnings" class="location-warnings"></div>
                            <div id="location-stats-details" class="location-details loc-bento"></div>
                            <details class="loc-boundary-notes">
                                <summary><?php echo htmlspecialchars($translations['location_stats_boundary_notes'] ?? 'Boundary dataset & methodology notes'); ?></summary>
                                <div id="location-stats-boundary" class="location-hint"></div>
                                <ul id="location-stats-assumptions" class="location-assumptions"></ul>
                            </details>
                        </section>
                        <section class="location-section" data-locgroup="stats">
                            <div class="location-section-head">
                                <h3><i class="fas fa-file-import"></i> <?php echo htmlspecialchars($translations['location_import_title'] ?? 'Import location history'); ?></h3>
                            </div>
                            <p class="location-hint"><?php echo htmlspecialchars($translations['location_import_hint'] ?? 'Google Timeline/Takeout, OwnTracks, GPX/TCX (including Strava), GeoJSON, EXIF GPS photos, Immich metadata and CSV. Files are parsed by the background worker and can be removed without affecting other sources.'); ?></p>
                            <div class="location-import-form">
                                <input type="file" id="location-import-file" accept=".gpx,.tcx,.json,.geojson,.ndjson,.jsonl,.csv,.jpg,.jpeg">
                                <label class="location-import-option"><input type="checkbox" id="location-import-delete-source"> <span><?php echo htmlspecialchars($translations['location_import_delete_source'] ?? 'Delete the source file after a successful import'); ?></span></label>
                                <button type="button" id="location-import-upload" class="excel-btn"><i class="fas fa-upload"></i> <?php echo htmlspecialchars($translations['location_import_upload'] ?? 'Upload & import'); ?></button>
                            </div>
                            <p id="location-import-status" class="location-status" role="status" aria-live="polite"></p>
                            <div id="location-import-history" class="location-import-history"></div>
                        </section>
                        <section class="location-section is-hidden" data-locgroup="export">
                            <div class="location-section-head">
                                <h3><i class="fas fa-file-export"></i> <?php echo htmlspecialchars($translations['location_export_title'] ?? 'Export and backup'); ?></h3>
                            </div>
                            <p class="location-hint"><?php echo htmlspecialchars($translations['location_export_hint'] ?? 'Exports run in the background and stay downloadable until they expire. GeoJSON and GPX can be imported back; a full backup includes account metadata but not Immich binaries or credentials.'); ?></p>
                            <div class="location-import-form location-export-form">
                                <label class="location-export-field"><span><?php echo htmlspecialchars($translations['location_export_format'] ?? 'Format'); ?></span>
                                    <select id="location-export-format">
                                        <option value="geojson">GeoJSON</option>
                                        <option value="gpx">GPX</option>
                                        <option value="json">JSON</option>
                                        <option value="backup"><?php echo htmlspecialchars($translations['location_export_backup'] ?? 'Full account backup'); ?></option>
                                    </select>
                                </label>
                                <label class="location-export-field"><span><?php echo htmlspecialchars($translations['location_export_scope'] ?? 'Scope'); ?></span>
                                    <select id="location-export-scope">
                                        <option value="all"><?php echo htmlspecialchars($translations['location_export_scope_all'] ?? 'All history'); ?></option>
                                        <option value="date"><?php echo htmlspecialchars($translations['location_export_scope_date'] ?? 'Date range'); ?></option>
                                        <option value="device"><?php echo htmlspecialchars($translations['location_export_scope_device'] ?? 'Device'); ?></option>
                                        <option value="trip"><?php echo htmlspecialchars($translations['location_export_scope_trip'] ?? 'Trip'); ?></option>
                                        <option value="place"><?php echo htmlspecialchars($translations['location_export_scope_place'] ?? 'Place'); ?></option>
                                    </select>
                                </label>
                                <label class="location-export-field" data-export-scope="date" hidden><span><?php echo htmlspecialchars($translations['location_export_from'] ?? 'From'); ?></span><input type="date" id="location-export-from"></label>
                                <label class="location-export-field" data-export-scope="date" hidden><span><?php echo htmlspecialchars($translations['location_export_to'] ?? 'To'); ?></span><input type="date" id="location-export-to"></label>
                                <label class="location-export-field" data-export-scope="device" hidden><span><?php echo htmlspecialchars($translations['location_export_device'] ?? 'Device'); ?></span><select id="location-export-device"></select></label>
                                <label class="location-export-field" data-export-scope="trip" hidden><span><?php echo htmlspecialchars($translations['location_export_trip'] ?? 'Trip'); ?></span><select id="location-export-trip"></select></label>
                                <label class="location-export-field" data-export-scope="place" hidden><span><?php echo htmlspecialchars($translations['location_export_place'] ?? 'Place'); ?></span><select id="location-export-place"></select></label>
                                <label class="location-import-option" id="location-export-derived-wrap"><input type="checkbox" id="location-export-derived"> <span><?php echo htmlspecialchars($translations['location_export_derived'] ?? 'Include derived trips, places and visits (JSON)'); ?></span></label>
                                <button type="button" id="location-export-create" class="excel-btn"><i class="fas fa-file-export"></i> <?php echo htmlspecialchars($translations['location_export_create'] ?? 'Create export'); ?></button>
                            </div>
                            <p id="location-export-status" class="location-status" role="status" aria-live="polite"></p>
                            <div id="location-export-history" class="location-import-history"></div>
                        </section>
                        <section class="location-section is-hidden" id="location-explore-section" data-locgroup="export">
                            <div class="location-section-head">
                                <h3><i class="fas fa-magnifying-glass"></i> <?php echo htmlspecialchars($translations['location_search_title'] ?? 'Search and saved views'); ?></h3>
                            </div>
                            <div class="location-import-form">
                                <input type="search" id="location-search-q" maxlength="120" placeholder="<?php echo htmlspecialchars($translations['location_search_placeholder'] ?? 'Search trips, places, visits, jobs, dates…'); ?>" autocomplete="off">
                                <select id="location-search-type" aria-label="Search type">
                                    <option value=""><?php echo htmlspecialchars($translations['location_search_all'] ?? 'All authorized types'); ?></option>
                                    <option value="trip">Trips</option><option value="place">Places</option><option value="visit">Visits</option>
                                    <option value="geofence">Geofences</option><option value="device">Devices</option><option value="import">Imports</option>
                                    <option value="export">Exports</option><option value="achievement">Achievements</option><option value="quality">Quality</option><option value="job">Jobs</option>
                                </select>
                                <button type="button" id="location-search-run" class="excel-btn"><i class="fas fa-magnifying-glass"></i> <?php echo htmlspecialchars($translations['location_search_run'] ?? 'Search'); ?></button>
                            </div>
                            <p id="location-search-range" class="location-hint" role="status" aria-live="polite"></p>
                            <div id="location-search-suggestions" class="location-hint"></div>
                            <div id="location-search-results" class="location-import-history"></div>
                            <div class="location-tool-card">
                                <h4><?php echo htmlspecialchars($translations['location_saved_views'] ?? 'Saved views'); ?></h4>
                                <div class="location-tool-row">
                                    <input type="text" id="location-view-name" maxlength="80" placeholder="<?php echo htmlspecialchars($translations['location_view_name'] ?? 'View name'); ?>">
                                    <select id="location-view-context">
                                        <?php foreach (['dashboard' => 'nav_dashboard', 'map' => 'nav_map', 'timeline' => 'nav_timeline', 'trips' => 'nav_trips', 'places' => 'nav_places', 'quality' => 'nav_quality', 'jobs' => 'nav_jobs', 'analytics' => 'nav_analytics'] as $contextValue => $contextKey): ?>
                                            <option value="<?php echo $contextValue; ?>"><?php echo htmlspecialchars($translations[$contextKey] ?? ucfirst($contextValue)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select id="location-view-period">
                                        <?php foreach (['today' => 'period_today', 'last_7_days' => 'period_last_7_days', 'last_30_days' => 'period_last_30_days', 'last_90_days' => 'period_last_90_days', 'this_month' => 'period_this_month', 'last_month' => 'period_last_month'] as $periodValue => $periodKey): ?>
                                            <option value="<?php echo $periodValue; ?>"<?php echo $periodValue === 'last_30_days' ? ' selected' : ''; ?>><?php echo htmlspecialchars($translations[$periodKey] ?? $periodValue); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" id="location-view-save" class="excel-btn"><i class="fas fa-bookmark"></i> <?php echo htmlspecialchars($translations['location_view_save'] ?? 'Save view'); ?></button>
                                </div>
                                <div id="location-saved-views" class="location-import-history"></div>
                            </div>
                        </section>
                        <section class="location-section is-hidden" id="location-analytics-section" data-locgroup="export">
                            <div class="location-section-head">
                                <h3><i class="fas fa-chart-line"></i> <?php echo htmlspecialchars($translations['location_analytics_title'] ?? 'Private mobility analytics'); ?></h3>
                                <div class="location-actions">
                                    <label class="location-consent"><input type="checkbox" id="location-analytics-compare" checked> <?php echo htmlspecialchars($translations['location_analytics_compare'] ?? 'Compare previous period'); ?></label>
                                    <button type="button" id="location-analytics-run" class="excel-btn"><i class="fas fa-rotate"></i> <?php echo htmlspecialchars($translations['location_analytics_refresh'] ?? 'Refresh'); ?></button>
                                </div>
                            </div>
                            <p id="location-analytics-status" class="location-hint" role="status" aria-live="polite"></p>
                            <div id="location-analytics-cards" class="location-cards"></div>
                            <div id="location-analytics-coverage" class="location-warnings"></div>
                            <div id="location-analytics-places" class="location-details"></div>
                            <p class="location-hint"><?php echo htmlspecialchars($translations['location_analytics_hint'] ?? 'Metrics are bounded aggregates. Coverage and confidence are shown; no transport mode is inferred.'); ?></p>
                        </section>
                        <section class="location-section is-hidden" id="location-tools-section" data-locgroup="corrections">
                            <div class="location-section-head">
                                <h3><i class="fas fa-sliders"></i> <?php echo htmlspecialchars($translations['location_tools_title'] ?? 'Location management'); ?></h3>
                                <button type="button" id="location-quality-reprocess" class="excel-btn"><i class="fas fa-shield-halved"></i> <?php echo htmlspecialchars($translations['location_quality_reprocess'] ?? 'Recheck quality'); ?></button>
                            </div>
                            <p id="location-quality-counts" class="location-hint"></p>
                            <div class="location-quality-workspace">
                                <div class="location-tool-card">
                                    <h4><?php echo htmlspecialchars($translations['location_geometry_title'] ?? 'Displayed geometry'); ?></h4>
                                    <label class="location-export-field"><span><?php echo htmlspecialchars($translations['location_geometry_mode'] ?? 'Geometry'); ?></span>
                                        <select id="location-geometry-mode">
                                            <option value="raw"><?php echo htmlspecialchars($translations['location_geometry_raw'] ?? 'Raw GPS'); ?></option>
                                            <option value="improved"><?php echo htmlspecialchars($translations['location_geometry_improved'] ?? 'Improved'); ?></option>
                                            <option value="compare"><?php echo htmlspecialchars($translations['location_geometry_compare'] ?? 'Compare raw and improved'); ?></option>
                                        </select>
                                    </label>
                                    <label class="location-export-field"><span><?php echo htmlspecialchars($translations['location_match_mode'] ?? 'Matching'); ?></span>
                                        <select id="location-match-mode">
                                            <option value="disabled"><?php echo htmlspecialchars($translations['location_match_disabled'] ?? 'Disabled'); ?></option>
                                            <option value="conservative"><?php echo htmlspecialchars($translations['location_match_conservative'] ?? 'Conservative automatic'); ?></option>
                                            <option value="review_required"><?php echo htmlspecialchars($translations['location_match_review'] ?? 'Review required'); ?></option>
                                        </select>
                                    </label>
                                    <div class="location-actions">
                                        <button type="button" id="location-match-save" class="excel-btn"><i class="fas fa-floppy-disk"></i> <?php echo htmlspecialchars($translations['location_match_save'] ?? 'Save'); ?></button>
                                        <button type="button" id="location-match-run" class="excel-btn"><i class="fas fa-wand-magic-sparkles"></i> <?php echo htmlspecialchars($translations['location_match_run'] ?? 'Recompute geometry'); ?></button>
                                    </div>
                                    <p id="location-geometry-summary" class="location-hint"></p>
                                </div>
                                <div class="location-tool-card">
                                    <h4><?php echo htmlspecialchars($translations['location_quality_issues'] ?? 'Quality issues'); ?></h4>
                                    <p class="location-hint"><?php echo htmlspecialchars($translations['location_quality_issues_hint'] ?? 'Raw points stay available. Exclusions affect derived views only and can be undone.'); ?></p>
                                    <div id="location-quality-issues" class="location-import-history"></div>
                                </div>
                                <div class="location-tool-card">
                                    <h4><?php echo htmlspecialchars($translations['location_correction_title'] ?? 'Apply correction'); ?></h4>
                                    <div class="location-tool-row">
                                        <label class="location-export-field"><span><?php echo htmlspecialchars($translations['location_correction_from'] ?? 'From'); ?></span><input type="date" id="location-correction-from"></label>
                                        <label class="location-export-field"><span><?php echo htmlspecialchars($translations['location_correction_to'] ?? 'To'); ?></span><input type="date" id="location-correction-to"></label>
                                    </div>
                                    <div class="location-tool-row" id="location-correction-effects">
                                        <label><input type="checkbox" value="exclude_display"> <?php echo htmlspecialchars($translations['location_effect_display'] ?? 'Hide from display'); ?></label>
                                        <label><input type="checkbox" value="exclude_analytics"> <?php echo htmlspecialchars($translations['location_effect_analytics'] ?? 'Exclude from analytics'); ?></label>
                                        <label><input type="checkbox" value="exclude_export"> <?php echo htmlspecialchars($translations['location_effect_export'] ?? 'Exclude from export'); ?></label>
                                        <label><input type="checkbox" value="exclude_family"> <?php echo htmlspecialchars($translations['location_effect_family'] ?? 'Hide from family'); ?></label>
                                        <label><input type="checkbox" value="do_not_match"> <?php echo htmlspecialchars($translations['location_effect_no_match'] ?? 'Do not match'); ?></label>
                                    </div>
                                    <div class="location-tool-row">
                                        <input type="text" id="location-correction-reason" maxlength="200" placeholder="<?php echo htmlspecialchars($translations['location_correction_reason'] ?? 'Reason'); ?>">
                                        <input type="text" id="location-correction-note" maxlength="500" placeholder="<?php echo htmlspecialchars($translations['location_correction_note'] ?? 'Private note (optional)'); ?>">
                                    </div>
                                    <button type="button" id="location-correction-apply" class="excel-btn"><i class="fas fa-check"></i> <?php echo htmlspecialchars($translations['location_correction_apply'] ?? 'Apply correction'); ?></button>
                                    <div id="location-corrections-list" class="location-import-history"></div>
                                </div>
                            </div>
                            <div class="location-tools-grid">
                                <div class="location-tool-card">
                                    <h4><?php echo htmlspecialchars($translations['location_places_detect_title'] ?? 'Automatic place detection'); ?></h4>
                                    <p class="location-hint"><?php echo htmlspecialchars($translations['location_places_detect_hint'] ?? 'Visits are saved to the database immediately. The background worker looks them up in OpenStreetMap and writes the name back.'); ?></p>
                                    <p id="location-places-detect-status" class="location-hint"></p>
                                    <button type="button" id="location-places-detect" class="excel-btn"><i class="fas fa-map-location-dot"></i> <?php echo htmlspecialchars($translations['location_places_detect_run'] ?? 'Detect places now'); ?></button>
                                    <button type="button" id="location-places-manager-open" class="excel-btn"><i class="fas fa-map"></i> <?php echo htmlspecialchars($translations['location_places_manager_open'] ?? 'Places map & recalculate'); ?></button>
                                </div>
                                <div id="location-places-manager" class="places-manager-modal" hidden>
                                    <div class="places-manager-card">
                                        <div class="places-manager-top">
                                            <h3><?php echo htmlspecialchars($translations['location_places_manager_title'] ?? 'Places'); ?></h3>
                                            <button type="button" id="location-places-manager-close" class="excel-btn" aria-label="Close">✕</button>
                                        </div>
                                        <div id="location-places-manager-map" class="places-manager-map"></div>
                                        <div class="places-manager-bottom">
                                            <label class="location-export-field"><span><?php echo htmlspecialchars($translations['location_places_recalc_from'] ?? 'From'); ?></span><input type="date" id="location-places-recalc-from"></label>
                                            <label class="location-export-field"><span><?php echo htmlspecialchars($translations['location_places_recalc_to'] ?? 'To'); ?></span><input type="date" id="location-places-recalc-to"></label>
                                            <button type="button" id="location-places-recalc-run" class="excel-btn"><i class="fas fa-rotate"></i> <?php echo htmlspecialchars($translations['location_places_recalc_run'] ?? 'Recalculate visits'); ?></button>
                                            <p id="location-places-recalc-status" class="location-hint"></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="location-tool-card">
                                    <h4><?php echo htmlspecialchars($translations['location_places_title'] ?? 'Places'); ?></h4>
                                    <div class="place-chips" id="place-chips"></div>
                                    <div class="location-tool-row">
                                        <input type="text" id="place-name" maxlength="80" placeholder="<?php echo htmlspecialchars($translations['location_place_name'] ?? 'Name'); ?>">
                                        <input type="text" id="place-tag" maxlength="32" placeholder="home">
                                    </div>
                                    <div class="location-tool-row">
                                        <input type="number" id="place-lat" step="0.000001" placeholder="lat">
                                        <input type="number" id="place-lon" step="0.000001" placeholder="lon">
                                        <input type="number" id="place-radius" min="25" max="5000" step="5" value="120" placeholder="m">
                                    </div>
                                    <button type="button" id="place-pick" class="excel-btn"><i class="fas fa-location-crosshairs"></i> <?php echo htmlspecialchars($translations['location_place_pick'] ?? 'Set on map'); ?></button>
                                    <button type="button" id="place-create" class="excel-btn"><i class="fas fa-plus"></i> <?php echo htmlspecialchars($translations['location_place_add'] ?? 'Add place'); ?></button>
                                    <div id="location-places-list" class="location-import-history"></div>
                                </div>
                                <div class="location-tool-card">
                                    <h4><?php echo htmlspecialchars($translations['location_geofences_title'] ?? 'Geofences'); ?></h4>
                                    <div class="location-tool-row">
                                        <input type="text" id="geofence-name" maxlength="80" placeholder="<?php echo htmlspecialchars($translations['location_geofence_name'] ?? 'Name'); ?>">
                                    </div>
                                    <div class="location-tool-row">
                                        <input type="number" id="geofence-lat" step="0.000001" placeholder="lat">
                                        <input type="number" id="geofence-lon" step="0.000001" placeholder="lon">
                                        <input type="number" id="geofence-radius" min="25" max="5000" step="5" value="200" placeholder="m">
                                    </div>
                                    <button type="button" id="geofence-create" class="excel-btn"><i class="fas fa-plus"></i> <?php echo htmlspecialchars($translations['location_geofence_add'] ?? 'Add geofence'); ?></button>
                                    <div id="location-geofences-list" class="location-import-history"></div>
                                </div>
                            </div>
                            <p id="location-tools-status" class="location-status" role="status" aria-live="polite"></p>
                        </section>
                        <section class="location-section is-hidden" id="notifications-section" data-locgroup="privacy">
                            <div class="location-section-head">
                                <h3><i class="fas fa-bell"></i> <?php echo htmlspecialchars($translations['notifications_title'] ?? 'Notifications and webhooks'); ?></h3>
                                <button type="button" id="notifications-webhook-test" class="excel-btn"><i class="fas fa-paper-plane"></i> <?php echo htmlspecialchars($translations['notifications_test'] ?? 'Send test event'); ?></button>
                            </div>
                            <p class="location-hint"><?php echo htmlspecialchars($translations['notifications_hint'] ?? 'Get in-app and optional signed webhook notifications for location events.'); ?></p>
                            <div class="location-tools-grid">
                                <div class="location-tool-card">
                                    <h4><?php echo htmlspecialchars($translations['notifications_events'] ?? 'Events'); ?></h4>
                                    <label class="notif-event"><input type="checkbox" id="notif-in-app"> <?php echo htmlspecialchars($translations['notifications_in_app'] ?? 'In-app notifications'); ?></label>
                                    <label class="notif-event"><input type="checkbox" id="notif-webhook"> <?php echo htmlspecialchars($translations['notifications_webhook'] ?? 'Send webhooks'); ?></label>
                                    <div id="notif-events" class="notif-events"></div>
                                    <label class="notif-event"><input type="checkbox" id="notif-quiet"> <?php echo htmlspecialchars($translations['notifications_quiet'] ?? 'Quiet hours'); ?></label>
                                    <div class="location-tool-row">
                                        <label class="notif-time"><?php echo htmlspecialchars($translations['notifications_quiet_start'] ?? 'From'); ?> <input type="time" id="notif-quiet-start" value="22:00"></label>
                                        <label class="notif-time"><?php echo htmlspecialchars($translations['notifications_quiet_end'] ?? 'To'); ?> <input type="time" id="notif-quiet-end" value="07:00"></label>
                                    </div>
                                    <button type="button" id="notifications-save" class="excel-btn"><i class="fas fa-check"></i> <?php echo htmlspecialchars($translations['notifications_save'] ?? 'Save preferences'); ?></button>
                                </div>
                                <div class="location-tool-card">
                                    <h4><?php echo htmlspecialchars($translations['notifications_webhooks'] ?? 'Webhooks'); ?></h4>
                                    <div class="location-tool-row">
                                        <input type="url" id="notifications-webhook-url" placeholder="https://example.com/steps">
                                        <input type="text" id="notifications-webhook-description" maxlength="120" placeholder="<?php echo htmlspecialchars($translations['notifications_webhook_name'] ?? 'Name'); ?>">
                                    </div>
                                    <div class="location-tool-row">
                                        <input type="text" id="notifications-webhook-events" placeholder="geofence_enter,import_complete">
                                        <input type="password" id="notifications-webhook-secret" placeholder="<?php echo htmlspecialchars($translations['notifications_secret'] ?? 'Signing secret (empty = generate)'); ?>" autocomplete="new-password">
                                    </div>
                                    <button type="button" id="notifications-webhook-create" class="excel-btn"><i class="fas fa-plus"></i> <?php echo htmlspecialchars($translations['notifications_add'] ?? 'Add webhook'); ?></button>
                                    <div id="notifications-webhooks" class="location-import-history"></div>
                                    <h4><?php echo htmlspecialchars($translations['notifications_deliveries'] ?? 'Recent deliveries'); ?></h4>
                                    <div id="notification-deliveries" class="location-import-history"></div>
                                </div>
                            </div>
                            <p id="notifications-status" class="location-status" role="status" aria-live="polite"></p>
                        </section>
                        <section class="location-section is-hidden" id="geo-privacy-section" data-locgroup="privacy">
                            <div class="location-section-head">
                                <h3><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($translations['geo_privacy_title'] ?? 'Privacy and data deletion'); ?></h3>
                            </div>
                            <p class="location-hint"><?php echo htmlspecialchars($translations['geo_privacy_hint'] ?? 'Control coordinate precision, automatic retention and deletion. Exports are private; no public location links exist. Account purge removes every Steps location row and artifact for this account.'); ?></p>
                            <div class="location-import-form">
                                <label class="location-export-field"><span><?php echo htmlspecialchars($translations['geo_privacy_precision'] ?? 'Summary precision'); ?></span>
                                    <select id="geo-privacy-precision">
                                        <option value="0">0 - country</option>
                                        <option value="1">1 - region</option>
                                        <option value="2" selected>2 - city</option>
                                        <option value="3">3 - neighbourhood</option>
                                        <option value="4">4 - street</option>
                                    </select>
                                </label>
                                <label class="location-export-field"><span><?php echo htmlspecialchars($translations['geo_privacy_retention'] ?? 'Keep history, days'); ?></span><input type="number" id="geo-privacy-retention" min="0" max="3650" value="0" title="0 keeps history until you delete it"></label>
                                <label class="location-export-field"><span><?php echo htmlspecialchars($translations['geo_privacy_audit'] ?? 'Keep audit, days'); ?></span><input type="number" id="geo-privacy-audit-days" min="30" max="3650" value="365"></label>
                                <button type="button" id="geo-privacy-save" class="excel-btn"><i class="fas fa-check"></i> <?php echo htmlspecialchars($translations['geo_privacy_save'] ?? 'Save policy'); ?></button>
                            </div>
                            <p id="geo-privacy-counts" class="location-hint"></p>
                            <div class="location-import-form">
                                <label class="location-export-field"><span><?php echo htmlspecialchars($translations['geo_privacy_from'] ?? 'Delete from'); ?></span><input type="datetime-local" id="geo-privacy-from"></label>
                                <label class="location-export-field"><span><?php echo htmlspecialchars($translations['geo_privacy_to'] ?? 'Delete to'); ?></span><input type="datetime-local" id="geo-privacy-to"></label>
                                <button type="button" id="geo-privacy-delete" class="excel-btn"><i class="fas fa-trash"></i> <?php echo htmlspecialchars($translations['geo_privacy_delete'] ?? 'Delete range'); ?></button>
                            </div>
                            <div class="location-import-form">
                                <input type="text" id="geo-privacy-confirm" maxlength="5" autocomplete="off" placeholder="<?php echo htmlspecialchars($translations['geo_privacy_confirm'] ?? 'Type PURGE to confirm'); ?>">
                                <button type="button" id="geo-privacy-purge" class="excel-btn"><i class="fas fa-triangle-exclamation"></i> <?php echo htmlspecialchars($translations['geo_privacy_purge'] ?? 'Purge account location data'); ?></button>
                            </div>
                            <p id="geo-privacy-status" class="location-status" role="status" aria-live="polite"></p>
                            <div id="geo-privacy-audit" class="location-import-history"></div>
                        </section>
                        <script>
                        (function () {
                            const api = '/apps/steps/api/geolocation_privacy.php';
                            const statusEl = document.getElementById('geo-privacy-status');
                            if (!statusEl) return;
                            const setStatus = (message, error) => { statusEl.textContent = message || ''; statusEl.style.color = error ? '#c0392b' : ''; };
                            const num = (id) => document.getElementById(id).value;
                            const render = (data) => {
                                const policy = data.policy || {};
                                document.getElementById('geo-privacy-precision').value = String(policy.summary_precision_decimals ?? 2);
                                document.getElementById('geo-privacy-retention').value = String(policy.retention_days ?? 0);
                                document.getElementById('geo-privacy-audit-days').value = String(policy.audit_retention_days ?? 365);
                                const c = data.counts || {};
                                document.getElementById('geo-privacy-counts').textContent = `Points: ${c.points || 0} · trips: ${c.trips || 0} · places: ${c.places || 0} · exports: ${c.exports || 0}`;
                                const audit = document.getElementById('geo-privacy-audit');
                                audit.innerHTML = '';
                                for (const row of (data.audit || [])) {
                                    const item = document.createElement('div');
                                    item.className = 'location-hint';
                                    item.textContent = `${row.event_type} · ${new Date(row.created_at).toLocaleString()}`;
                                    audit.appendChild(item);
                                }
                            };
                            const load = async () => {
                                try {
                                    const res = await fetch(api, { credentials: 'same-origin' });
                                    const data = await res.json();
                                    if (!res.ok || !data.ok) throw new Error(data.error || 'load_failed');
                                    render(data);
                                } catch (e) { setStatus(e.message, true); }
                            };
                            const send = async (body) => {
                                const res = await fetch(api, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
                                const data = await res.json();
                                if (!res.ok || !data.ok) throw new Error(data.error || 'request_failed');
                                return data;
                            };
                            document.getElementById('geo-privacy-save').addEventListener('click', async () => {
                                try {
                                    await send({ action: 'policy', summary_precision_decimals: Number(num('geo-privacy-precision')), retention_days: Number(num('geo-privacy-retention')), audit_retention_days: Number(num('geo-privacy-audit-days')) });
                                    setStatus('<?php echo htmlspecialchars($translations['geo_privacy_saved'] ?? 'Privacy policy saved.'); ?>');
                                    await load();
                                } catch (e) { setStatus(e.message, true); }
                            });
                            document.getElementById('geo-privacy-delete').addEventListener('click', async () => {
                                if (!confirm('<?php echo htmlspecialchars($translations['geo_privacy_delete_confirm'] ?? 'Delete location points and derived data in this range? This cannot be undone.'); ?>')) return;
                                try {
                                    const data = await send({ action: 'delete_range', from: num('geo-privacy-from'), to: num('geo-privacy-to') });
                                    setStatus('Deleted: ' + JSON.stringify(data.deleted));
                                    await load();
                                } catch (e) { setStatus(e.message, true); }
                            });
                            document.getElementById('geo-privacy-purge').addEventListener('click', async () => {
                                if (!confirm('<?php echo htmlspecialchars($translations['geo_privacy_purge_confirm'] ?? 'Purge every Steps location row, export and credential for this account? This cannot be undone.'); ?>')) return;
                                try {
                                    await send({ action: 'purge', confirmation: num('geo-privacy-confirm') });
                                    setStatus('<?php echo htmlspecialchars($translations['geo_privacy_purged'] ?? 'Account location data purged.'); ?>');
                                    await load();
                                } catch (e) { setStatus(e.message, true); }
                            });
                            load();
                        })();
                        </script>
                        <section class="location-section is-hidden" id="immich-section" data-locgroup="privacy">
                            <div class="location-section-head">
                                <h3><i class="fas fa-images"></i> <?php echo htmlspecialchars($translations['immich_title'] ?? 'Immich photos'); ?></h3>
                            </div>
                            <p class="location-hint"><?php echo htmlspecialchars($translations['immich_hint'] ?? 'Connect a self-hosted Immich server to show photo clusters and galleries. Steps stores only asset references and never copies your photos. Family members cannot see them.'); ?></p>
                            <div class="location-import-form">
                                <input type="url" id="immich-server" placeholder="https://immich.example.com" autocomplete="off">
                                <input type="password" id="immich-api-key" placeholder="<?php echo htmlspecialchars($translations['immich_api_key'] ?? 'API key'); ?>" autocomplete="new-password">
                                <button type="button" id="immich-connect" class="excel-btn"><i class="fas fa-link"></i> <?php echo htmlspecialchars($translations['immich_connect'] ?? 'Connect'); ?></button>
                                <button type="button" id="immich-test" class="excel-btn"><i class="fas fa-plug-circle-check"></i> <?php echo htmlspecialchars($translations['immich_test'] ?? 'Test'); ?></button>
                                <button type="button" id="immich-sync" class="excel-btn"><i class="fas fa-rotate"></i> <?php echo htmlspecialchars($translations['immich_sync'] ?? 'Sync metadata'); ?></button>
                                <button type="button" id="immich-disconnect" class="excel-btn"><?php echo htmlspecialchars($translations['immich_disconnect'] ?? 'Disconnect'); ?></button>
                            </div>
                            <p id="immich-status" class="location-status" role="status" aria-live="polite"></p>
                            <div id="immich-gallery" class="location-import-history"></div>
                        </section>
                    </div>
                </div>
                <div id="settings-panel-family" class="settings-tab-panel" role="tabpanel">
                    <div class="location-panel">
                        <section class="location-section">
                            <div class="location-section-head">
                                <h3><i class="fas fa-people-roof"></i> <?php echo htmlspecialchars($translations['family_title'] ?? 'Family groups'); ?></h3>
                            </div>
                            <p class="location-hint"><?php echo htmlspecialchars($translations['family_hint'] ?? 'Family groups are private. Membership never shares your location; location access is a separate grant. Up to 15 people per group.'); ?></p>
                            <div class="location-import-form">
                                <input type="text" id="family-new-name" maxlength="80" placeholder="<?php echo htmlspecialchars($translations['family_group_name'] ?? 'Group name'); ?>">
                                <button type="button" id="family-create" class="excel-btn"><i class="fas fa-plus"></i> <?php echo htmlspecialchars($translations['family_create'] ?? 'Create group'); ?></button>
                            </div>
                            <div class="location-import-form">
                                <input type="text" id="family-token" maxlength="200" autocomplete="off" placeholder="<?php echo htmlspecialchars($translations['family_invite_token'] ?? 'Invitation token'); ?>">
                                <button type="button" id="family-accept" class="excel-btn"><i class="fas fa-right-to-bracket"></i> <?php echo htmlspecialchars($translations['family_accept'] ?? 'Join with token'); ?></button>
                            </div>
                            <p id="family-status" class="location-status" role="status" aria-live="polite"></p>
                            <div id="family-pending" class="location-import-history"></div>
                            <div id="family-groups" class="location-import-history"></div>
                        </section>
                    </div>
                </div>
                <div id="settings-panel-achievements" class="settings-tab-panel" role="tabpanel">
                    <div class="location-panel">
                        <section class="location-section">
                            <div class="location-section-head">
                                <h3><i class="fas fa-trophy"></i> <?php echo htmlspecialchars($translations['achievement_title'] ?? 'Achievements'); ?></h3>
                                <div class="location-actions">
                                    <button type="button" id="achievements-recompute" class="excel-btn"><i class="fas fa-rotate"></i> <?php echo htmlspecialchars($translations['achievement_recompute'] ?? 'Recompute'); ?></button>
                                </div>
                            </div>
                            <p class="location-hint"><?php echo htmlspecialchars($translations['achievement_hint'] ?? 'Private milestones based only on your confirmed history. There is no leaderboard and nothing is shared publicly.'); ?></p>
                            <label class="location-import-option m3-checkbox-wrapper"><input type="checkbox" id="achievements-enabled" class="m3-checkbox-input" checked> <span class="m3-checkbox-box"><svg class="m3-checkbox-svg" viewBox="0 0 16 16"><polyline points="3.5 8.5 6.5 11.5 12.5 4.5"></polyline></svg></span><span><?php echo htmlspecialchars($translations['achievement_enable'] ?? 'Enable achievements'); ?></span></label>
                            <p id="achievements-status" class="location-status" role="status" aria-live="polite"></p>
                            <div id="achievements-bridge" class="achievement-bridge" hidden></div>
                            <div id="achievements-list" class="location-import-history"></div>
                            <p id="achievements-disabled" class="location-hint" hidden><?php echo htmlspecialchars($translations['achievement_disabled_notice'] ?? 'Achievements are disabled. Existing results stay stored but are hidden, and no new evaluations run until you enable them again.'); ?></p>
                        </section>
                    </div>
                </div>
                <script>
                (function () {
                    var gateway = '/apps/steps/api/session_gateway.php?endpoint=';
                    function byId(id) { return document.getElementById(id); }
                    function tr(key, fallback) {
                        try { if (typeof translations !== 'undefined' && translations && translations[key]) return translations[key]; } catch (e) {}
                        return fallback;
                    }
                    function status(id, message, bad) {
                        var node = byId(id);
                        if (!node) return;
                        node.textContent = message || '';
                        node.classList.toggle('is-error', !!bad);
                    }
                    function getJson(endpoint) {
                        return fetch(gateway + endpoint, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); });
                    }
                    function postJson(endpoint, payload) {
                        return fetch(gateway + endpoint, {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify(payload)
                        }).then(function (r) { return r.json(); });
                    }

                    function achievementRow(item) {
                        var row = document.createElement('div');
                        row.className = 'location-tool-row';
                        var label = document.createElement('span');
                        var family = tr(item.title_key, item.family);
                        label.textContent = family + ' · ' + (item.unit === 'm' ? Math.round(item.target / 1000) + ' km' : item.target + (item.unit === 'days' ? ' ' + tr('achievement_days', 'days') : ''));
                        var value = document.createElement('span');
                        var shown = item.unit === 'm' ? (Math.round(item.progress / 100) / 10) + ' km' : Math.round(item.progress);
                        value.textContent = shown + ' (' + item.percent + '%)' + (item.earned ? ' ✓ ' + tr('achievement_earned', 'Earned') : '');
                        row.appendChild(label);
                        row.appendChild(value);
                        return row;
                    }

                    function fmtInt(value) {
                        return String(Math.round(Number(value) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                    }
                    // The steps half and the track half share one achievement set, so
                    // the panel shows the bridge state instead of a second ladder.
                    function renderBridge(data) {
                        var host = byId('achievements-bridge');
                        if (!host) return;
                        var h = data && data.highlights;
                        if (!data || !data.enabled || !h) { host.hidden = true; host.innerHTML = ''; return; }
                        var items = [
                            [tr('achievement_bridge_steps_total', 'Steps counted'), fmtInt(h.steps_total)],
                            [tr('achievement_bridge_goal_streak', 'Goal streak'), fmtInt(h.steps_streak) + ' ' + tr('achievement_days', 'days')],
                            [tr('achievement_bridge_trail_days', 'Goal days with track'), fmtInt(h.steps_trail_days)],
                            [tr('achievement_bridge_route', 'Tracked route'), (Math.round(Number(h.route_distance_m || 0) / 100) / 10) + ' km']
                        ];
                        host.innerHTML = '';
                        items.forEach(function (pair) {
                            var cell = document.createElement('div');
                            cell.className = 'achievement-bridge-item';
                            var label = document.createElement('span');
                            label.textContent = pair[0];
                            var value = document.createElement('strong');
                            value.textContent = pair[1];
                            cell.appendChild(label);
                            cell.appendChild(value);
                            host.appendChild(cell);
                        });
                        host.hidden = false;
                    }

                    function renderAchievements(data, showList) {
                        var enabled = byId('achievements-enabled');
                        var hint = byId('achievements-disabled');
                        var list = byId('achievements-list');
                        if (!enabled || !list || !hint) return;
                        enabled.checked = !!data.enabled;
                        hint.hidden = !!data.enabled;
                        list.innerHTML = '';
                        renderBridge(data);
                        // Settings only owns the preference and a bounded recompute.
                        // Earned cards and progress live in the Statistics experience,
                        // so this panel never renders a locked/progress checklist.
                        if (!data.enabled || !showList) return;
                        if (data.processing) status('achievements-status', tr('achievement_recomputing', 'Recomputing in the background…'));
                    }

                    function loadAchievements(force) {
                        status('achievements-status', tr('achievement_loading', 'Loading…'));
                        getJson('geolocation_achievements').then(function (data) {
                            if (!data.ok) { status('achievements-status', data.error || 'failed', true); return; }
                            renderAchievements(data, true);
                            status('achievements-status', data.enabled ? (data.earned_count + '/' + data.total_count) : tr('achievement_disabled_notice', 'Disabled'));
                        }).catch(function () { status('achievements-status', 'failed', true); });
                    }

                    document.addEventListener('change', function (event) {
                        if (!event.target || event.target.id !== 'achievements-enabled') return;
                        postJson('geolocation_achievements', { action: 'preference', enabled: !!event.target.checked }).then(function (data) {
                            if (!data.ok) { status('achievements-status', data.error || 'failed', true); return; }
                            renderAchievements(data, true);
                            status('achievements-status', data.enabled ? (data.earned_count + '/' + data.total_count) : tr('achievement_disabled_notice', 'Disabled'));
                        });
                    });
                    document.addEventListener('click', function (event) {
                        var target = event.target && event.target.closest ? event.target : null;
                        if (!target) return;
                        if (target.closest('#achievements-recompute')) {
                            status('achievements-status', tr('achievement_loading', 'Loading…'));
                            postJson('geolocation_achievements', { action: 'recompute' }).then(function (data) {
                                if (!data.ok) { status('achievements-status', data.error || 'failed', true); return; }
                                renderAchievements(data, true);
                                status('achievements-status', data.earned_count + '/' + data.total_count);
                            });
                        }
                        var tab = target.closest('.settings-tab[data-settings-tab]');
                        if (tab) {
                            var id = tab.getAttribute('data-settings-tab');
                            if (id === 'achievements') loadAchievements();
                            if (id === 'api') loadApiKeys();
                        }
                    });

                    var scopesRendered = false;
                    function renderApiKeys(data) {
                        var list = byId('api-key-list');
                        if (!list) return;
                        list.innerHTML = '';
                        if (!data.keys || !data.keys.length) {
                            var empty = document.createElement('p');
                            empty.textContent = tr('api_keys_no_keys', 'No API keys yet.');
                            list.appendChild(empty);
                            return;
                        }
                        data.keys.forEach(function (key) {
                            var row = document.createElement('div');
                            row.className = 'location-tool-row';
                            var label = document.createElement('span');
                            label.textContent = key.name + ' · ' + key.prefix + '… · ' + (key.active ? tr('api_keys_status', 'Active') : tr('api_keys_revoked', 'Revoked')) + ' · ' + tr('api_keys_last_used', 'Last used') + ': ' + (key.last_used_at || tr('api_keys_never', 'Never'));
                            row.appendChild(label);
                            if (key.active) {
                                var rotate = document.createElement('button');
                                rotate.type = 'button';
                                rotate.className = 'excel-btn';
                                rotate.textContent = tr('api_keys_rotate', 'Rotate');
                                rotate.addEventListener('click', function () {
                                    postJson('api_keys', { action: 'rotate', id: key.id }).then(function (result) {
                                        if (!result.ok) { status('api-key-status', result.error || 'failed', true); return; }
                                        showSecret(result.key.secret);
                                        renderApiKeys(result);
                                    });
                                });
                                var revoke = document.createElement('button');
                                revoke.type = 'button';
                                revoke.className = 'excel-btn';
                                revoke.textContent = tr('api_keys_revoke', 'Revoke');
                                revoke.addEventListener('click', function () {
                                    postJson('api_keys', { action: 'revoke', id: key.id }).then(function (result) {
                                        if (!result.ok) { status('api-key-status', result.error || 'failed', true); return; }
                                        renderApiKeys(result);
                                    });
                                });
                                row.appendChild(rotate);
                                row.appendChild(revoke);
                            }
                            list.appendChild(row);
                        });
                    }
                    function showSecret(secret) {
                        var box = byId('api-key-new');
                        if (!box) return;
                        box.hidden = false;
                        box.textContent = tr('api_keys_created_once', 'Copy it now — it will not be shown again:') + ' ' + secret;
                    }
                    function loadApiKeys() {
                        getJson('api_keys').then(function (data) {
                            if (!data.ok) { status('api-key-status', data.error || 'failed', true); return; }
                            var container = byId('api-key-scopes');
                            if (container && !scopesRendered) {
                                (data.scopes || []).forEach(function (scope) {
                                    var label = document.createElement('label');
                                    label.className = 'notif-event';
                                    var box = document.createElement('input');
                                    box.type = 'checkbox';
                                    box.value = scope;
                                    box.checked = (data.default_scopes || []).indexOf(scope) !== -1;
                                    box.setAttribute('data-api-scope', scope);
                                    label.appendChild(box);
                                    label.appendChild(document.createTextNode(' ' + scope));
                                    container.appendChild(label);
                                });
                                scopesRendered = true;
                            }
                            renderApiKeys(data);
                            status('api-key-status', '');
                        }).catch(function () { status('api-key-status', 'failed', true); });
                    }
                    document.addEventListener('click', function (event) {
                        var button = event.target && event.target.closest ? event.target.closest('#api-key-create') : null;
                        if (!button) return;
                        var nameInput = byId('api-key-name');
                        var scopes = [];
                        document.querySelectorAll('[data-api-scope]').forEach(function (box) { if (box.checked) scopes.push(box.value); });
                        postJson('api_keys', { action: 'create', name: nameInput ? nameInput.value : '', scopes: scopes }).then(function (data) {
                            if (!data.ok) { status('api-key-status', (data.details && data.details.reason) || data.error || 'failed', true); return; }
                            showSecret(data.key.secret);
                            renderApiKeys(data);
                            status('api-key-status', tr('api_keys_created', 'Key created'));
                            if (nameInput) nameInput.value = '';
                        });
                    });
                    document.addEventListener('DOMContentLoaded', function () {
                        var panel = byId('settings-panel-achievements');
                        if (panel && panel.classList.contains('active')) loadAchievements();
                    });
                })();
                </script>
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
                </div>
                <div class="md3-modal-foot">
                    <button type="button" class="excel-btn" id="settings-md3-close"><?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?></button>
                    <button type="button" class="excel-btn" id="settings-md3-save" style="background:var(--primary-color);color:#fff;"><?php echo htmlspecialchars($translations['save'] ?? 'Save Changes'); ?></button>
                </div>
            </div>
        </div>
        
        <div id="day-steps-modal" style="display:none;">
            <div class="day-steps-content">
                <div class="md3-modal-head" style="padding:16px 24px;border-bottom:1px solid rgba(255,255,255,0.07);">
                    <div class="day-steps-title"><?php echo htmlspecialchars($translations['steps'] ?? 'Steps'); ?></div>
                    <button class="modal-close" type="button" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>"><span class="material-symbols-rounded" aria-hidden="true">close</span></button>
                </div>
                <div class="day-steps-date" id="day-steps-date"></div>
                <input type="text" id="day-steps-input" inputmode="numeric" placeholder="0" autocomplete="off" autocorrect="off" spellcheck="false" aria-label="<?php echo htmlspecialchars($translations['steps'] ?? 'Steps'); ?>">
                <div class="day-steps-actions">
                    <button type="button" class="day-steps-cancel" id="day-steps-cancel"><?php echo htmlspecialchars($translations['cancel'] ?? 'Cancel'); ?></button>
                    <button type="button" class="day-steps-save" id="day-steps-save"><?php echo htmlspecialchars($translations['save'] ?? 'Save'); ?></button>
                </div>
            </div>
        </div>

        <div id="add-steps-modal" style="display:none;">
            <div class="add-steps-content add-steps-merged">
                <div class="md3-modal-head">
                    <div class="md3-modal-head-main">
                        <div class="md3-modal-icon"><i class="fas fa-plus" aria-hidden="true"></i></div>
                        <div>
                            <h2><?php echo htmlspecialchars($translations['add_action_tooltip'] ?? $translations['add_steps']); ?></h2>
                            <p><?php echo htmlspecialchars($translations['add_modal_subtitle'] ?? 'Enter daily steps manually or import ZIP exports'); ?></p>
                        </div>
                    </div>
                    <button class="modal-close" type="button" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>"><span class="material-symbols-rounded" aria-hidden="true">close</span></button>
                </div>
                <h2 class="add-steps-main-title" hidden><i class="fas fa-plus"></i> <?php echo htmlspecialchars($translations['add_action_tooltip'] ?? $translations['add_steps']); ?></h2>
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
                    <div class="zip-import-layout video-analysis-content">
                        <div class="zip-import-head va-head">
                            <h2><i class="fas fa-file-archive"></i> <?php echo htmlspecialchars($translations['zeep_analysis'] ?? 'Zeep Life'); ?></h2>
                            <p class="va-sub"><?php echo htmlspecialchars($translations['zeep_hint'] ?? ''); ?></p>
                        </div>
                        <div class="va-steps zip-import-steps" id="zl-steps">
                            <div class="va-progress"><div class="va-progress-fill"></div></div>
                            <div class="va-steps-row">
                                <button type="button" class="va-step-dot is-active" data-step-target="1">1</button>
                                <span class="va-step-line"></span>
                                <button type="button" class="va-step-dot" data-step-target="2" disabled>2</button>
                                <span class="va-step-line"></span>
                                <button type="button" class="va-step-dot" data-step-target="3" disabled>3</button>
                            </div>
                        </div>
                        <div class="zip-import-stack va-grid">
                            <section class="va-segment zip-import-card" data-step-section="1">
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
                                <div class="video-btn-row zip-import-actions">
                                    <button type="button" class="excel-btn" id="zl-next-step1" style="background:var(--primary-color);color:#fff;" disabled><?php echo htmlspecialchars($translations['zeep_continue'] ?? 'Continue'); ?></button>
                                </div>
                            </section>
                            <section class="va-segment zip-import-card" data-step-section="2" style="display:none;">
                                <div class="va-segment-h"><i class="fas fa-circle-check"></i> <?php echo htmlspecialchars($translations['zeep_ready_section'] ?? 'Ready'); ?></div>
                                <p class="va-sub zeep-ready-hint"><?php echo htmlspecialchars($translations['zeep_ready_hint'] ?? ''); ?></p>
                                <div class="video-btn-row zip-import-actions">
                                    <button type="button" class="excel-btn" id="zl-next" style="background:var(--primary-color);color:#fff;"><?php echo htmlspecialchars($translations['zeep_continue'] ?? 'Continue'); ?></button>
                                </div>
                            </section>
                            <section class="va-segment zip-import-card" data-step-section="3" style="display:none;">
                                <div class="va-segment-h"><i class="fas fa-bolt"></i> <?php echo htmlspecialchars($translations['video_section_actions'] ?? 'Actions'); ?></div>
                                <div class="video-btn-row zip-import-actions">
                                    <button type="button" class="excel-btn" id="zl-start" style="background:var(--primary-color);color:#fff;" disabled><?php echo htmlspecialchars($translations['zeep_start'] ?? 'Import'); ?></button>
                                </div>
                            </section>
                            <section class="va-segment zip-import-card" id="zl-job-panel" style="display:none;" aria-live="polite">
                                <div class="va-segment-h"><i class="fas fa-chart-line"></i> <?php echo htmlspecialchars($translations['video_section_progress'] ?? 'Status'); ?></div>
                                <p id="zl-msg">—</p>
                                <div id="zl-progress-bar"><div id="zl-progress-fill"></div></div>
                                <p id="zl-meta"></p>
                                <div class="video-btn-row zip-import-actions"><button type="button" class="excel-btn worker-stop-btn" id="zl-stop"><?php echo htmlspecialchars($translations['stop'] ?? 'Stop'); ?></button></div>
                            </section>
                            <section class="zip-import-guide takeout-guide-segment" id="zeep-guide-segment" data-step-section="1">
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
                <div id="add-panel-takeout" class="add-tab-panel" role="tabpanel">
                    <div class="zip-import-layout video-analysis-content">
                        <div class="zip-import-head va-head">
                            <h2><i class="fas fa-file-archive"></i> <?php echo htmlspecialchars($translations['add_tab_fit'] ?? 'Takeout'); ?></h2>
                            <p class="va-sub takeout-hint"><?php echo htmlspecialchars($translations['takeout_hint'] ?? ''); ?></p>
                        </div>
                        <div class="va-steps zip-import-steps" id="ft-steps">
                            <div class="va-progress"><div class="va-progress-fill"></div></div>
                            <div class="va-steps-row">
                                <button type="button" class="va-step-dot is-active" data-step-target="1">1</button>
                                <span class="va-step-line"></span>
                                <button type="button" class="va-step-dot" data-step-target="2" disabled>2</button>
                                <span class="va-step-line"></span>
                                <button type="button" class="va-step-dot" data-step-target="3" disabled>3</button>
                            </div>
                        </div>
                        <div class="zip-import-stack va-grid">
                            <section class="va-segment zip-import-card" data-step-section="1">
                                <div class="va-segment-h"><i class="fas fa-file-zipper"></i> <?php echo htmlspecialchars($translations['takeout_zip_section'] ?? 'ZIP archive'); ?></div>
                                <div class="va-field">
                                    <label for="ft-zip"><?php echo htmlspecialchars($translations['takeout_zip_file_label'] ?? 'Archive file'); ?> (.zip)</label>
                                    <input type="file" id="ft-zip" accept=".zip,application/zip" data-wizard-keep-enabled="1">
                                </div>
                                <div class="video-btn-row zip-import-actions">
                                    <button type="button" class="excel-btn" id="ft-next-step1" style="background:var(--primary-color);color:#fff;" disabled><?php echo htmlspecialchars($translations['takeout_continue'] ?? $translations['zeep_continue'] ?? 'Continue'); ?></button>
                                </div>
                            </section>
                            <section class="va-segment zip-import-card" data-step-section="2" style="display:none;">
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
                            <section class="va-segment zip-import-card" data-step-section="3" style="display:none;">
                                <div class="va-segment-h"><i class="fas fa-bolt"></i> <?php echo htmlspecialchars($translations['video_section_actions'] ?? 'Actions'); ?></div>
                                <div class="video-btn-row zip-import-actions">
                                    <button type="button" class="excel-btn" id="ft-start" style="background:var(--primary-color);color:#fff;" disabled><?php echo htmlspecialchars($translations['takeout_start'] ?? 'Import'); ?></button>
                                </div>
                            </section>
                            <section class="va-segment zip-import-card" id="ft-job-panel" style="display:none;" aria-live="polite">
                                <div class="va-segment-h"><i class="fas fa-chart-line"></i> <?php echo htmlspecialchars($translations['video_section_progress'] ?? 'Status'); ?></div>
                                <p id="ft-msg">—</p>
                                <div id="ft-progress-bar"><div id="ft-progress-fill"></div></div>
                                <p id="ft-meta"></p>
                                <div class="video-btn-row zip-import-actions"><button type="button" class="excel-btn worker-stop-btn" id="ft-stop"><?php echo htmlspecialchars($translations['stop'] ?? 'Stop'); ?></button></div>
                            </section>
                            <section class="zip-import-guide takeout-guide-segment" id="takeout-guide-segment" data-step-section="1">
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
        <div id="guide-expand-modal" style="display:none;">
            <div class="guide-expand-content">
                <div class="guide-expand-topbar">
                    <button type="button" id="guide-expand-back-btn" class="guide-expand-back-btn">
                        <i class="fas fa-arrow-left"></i>
                        <span><?php echo htmlspecialchars($translations['back'] ?? 'Back'); ?></span>
                    </button>
                    <h2 class="guide-expand-title"><i class="fas fa-book-open"></i> <span id="guide-expand-title-text"></span></h2>
                    <button type="button" class="modal-close" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>"><span class="material-symbols-rounded" aria-hidden="true">close</span></button>
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
        
        <!-- Location sharing Modal -->
        <div id="share-location-modal" style="display:none;">
            <div class="share-location-content">
                <div class="md3-modal-head">
                    <div class="md3-modal-head-main">
                        <div class="md3-modal-icon"><i class="fas fa-location-dot" aria-hidden="true"></i></div>
                        <div><h2><?php echo htmlspecialchars($translations['location_share_title'] ?? 'Location sharing'); ?></h2></div>
                    </div>
                    <button class="modal-close" type="button" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>"><span class="material-symbols-rounded" aria-hidden="true">close</span></button>
                </div>
                <div class="share-location-body">
                    <p class="location-hint"><?php echo htmlspecialchars($translations['location_share_hint'] ?? 'Share your location only with consent. You can pause or revoke access at any time.'); ?></p>
                    <div class="share-location-grid">
                        <label class="share-field"><span><?php echo htmlspecialchars($translations['location_share_viewer'] ?? 'Person email'); ?></span><input id="share-viewer" type="email" autocomplete="off" placeholder="user@example.com"></label>
                        <label class="share-field"><span><?php echo htmlspecialchars($translations['location_share_level'] ?? 'Access level'); ?></span>
                            <select id="share-level">
                                <option value="recent"><?php echo htmlspecialchars($translations['location_share_level_recent'] ?? 'Recent history'); ?></option>
                                <option value="current"><?php echo htmlspecialchars($translations['location_share_level_current'] ?? 'Current location'); ?></option>
                                <option value="range"><?php echo htmlspecialchars($translations['location_share_level_range'] ?? 'Date range'); ?></option>
                                <option value="places"><?php echo htmlspecialchars($translations['location_share_level_places'] ?? 'Places only'); ?></option>
                            </select>
                        </label>
                        <label class="share-field"><span><?php echo htmlspecialchars($translations['location_share_precision'] ?? 'Precision, metres (0 = exact)'); ?></span><input id="share-precision" type="number" min="0" step="50" value="0" inputmode="numeric"></label>
                        <label class="share-field"><span><?php echo htmlspecialchars($translations['location_share_until'] ?? 'Until'); ?></span><input id="share-until" type="date"></label>
                        <label class="share-field"><span><?php echo htmlspecialchars($translations['location_share_from'] ?? 'From'); ?></span><input id="share-from" type="date"></label>
                        <label class="share-field"><span><?php echo htmlspecialchars($translations['location_share_to'] ?? 'To'); ?></span><input id="share-to" type="date"></label>
                    </div>
                    <label class="share-field"><span><?php echo htmlspecialchars($translations['location_share_reason'] ?? 'Reason (shown to you)'); ?></span><input id="share-reason" type="text" maxlength="200"></label>
                    <label class="share-consent"><input type="checkbox" id="share-consent"><span><?php echo htmlspecialchars($translations['location_share_consent'] ?? 'I agree to share my location with this person'); ?></span></label>
                    <div class="share-location-actions">
                        <button type="button" id="share-create" class="excel-btn"><?php echo htmlspecialchars($translations['location_share_add'] ?? 'Share location'); ?></button>
                        <span id="share-status" role="status" aria-live="polite"></span>
                    </div>
                    <div id="share-grants" class="share-grants"></div>
                </div>
            </div>
        </div>
        <!-- Toast container -->
        <!-- Alert Modal -->
        <div id="alert-modal" style="display:none;">
            <div class="alert-content">
                <button class="modal-close alert-close" type="button" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>"><span class="material-symbols-rounded" aria-hidden="true">close</span></button>
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
                <button class="modal-close" type="button" id="video-confirm-close-x" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>"><span class="material-symbols-rounded" aria-hidden="true">close</span></button>
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
                <button class="modal-close" type="button" id="video-confirm-gaps-close-x" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>"><span class="material-symbols-rounded" aria-hidden="true">close</span></button>
                <h2 style="margin-top:0;"><i class="fas fa-calendar-day"></i> <span id="video-confirm-gaps-heading"><?php echo htmlspecialchars($translations['video_gaps_title'] ?? 'Missing days'); ?></span></h2>
                <p id="video-confirm-gaps-sub" style="text-align:left;font-size:14px;opacity:0.9;"><?php echo htmlspecialchars($translations['video_gaps_sub'] ?? 'Days between the first and last recognized date with no data. Enter steps, then apply.'); ?></p>
                <div id="video-confirm-gaps-body" class="excel-table-container" style="max-height:50vh;overflow:auto;"></div>
                <div style="margin-top:16px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                    <button type="button" class="excel-btn" id="video-confirm-gaps-apply" style="background:var(--primary-color);color:#fff;"><?php echo htmlspecialchars($translations['video_gaps_apply'] ?? 'Add to list'); ?></button>
                </div>
            </div>
        </div>
        <div id="global-image-viewer" aria-hidden="true">
            <button type="button" id="global-image-viewer-close" class="modal-close" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>"><span class="material-symbols-rounded" aria-hidden="true">close</span></button>
            <img id="global-image-viewer-img" src="" alt="">
        </div>

        <div id="stepmonitor-conflict-modal" style="display:none;position:fixed;inset:0;z-index:10070;background:rgba(10,14,18,0.48);-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px);align-items:center;justify-content:center;padding:16px;">
            <div class="smc-card">
                <div class="smc-head">
                    <h3 class="smc-title"><?php echo htmlspecialchars($translations['sync_conflict_title'] ?? 'Sync conflict'); ?></h3>
                </div>
                <div class="smc-body">
                    <div class="smc-box">
                        <div class="smc-icon" aria-hidden="true"><i class="fas fa-triangle-exclamation" style="font-size:24px" aria-hidden="true"></i></div>
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
            // The web dashboard is authenticated by a PHP session, while
            // /api/steps (public-api) only accepts a Bearer token, so polling
            // conflicts from this page always answered 401 missing_token.
            // Conflicts are surfaced and resolved in the mobile app, which has
            // a token; keep the modal markup for that flow and do not poll here.
        })();
        </script>

        <script>
        (function () {
            const modal = document.getElementById('mobile-app-modal');
            if (!modal) return;
            const connectBtn = document.getElementById('mobile-app-connect');
            const fields = document.getElementById('mobile-app-pair-fields');
            const linkEl = document.getElementById('mobile-app-pair-link');
            const pairError = document.getElementById('mobile-app-pair-error');
            const devicesList = document.getElementById('mobile-app-devices-list');
            const devicesEmpty = document.getElementById('mobile-app-devices-empty');
            const devicesError = document.getElementById('mobile-app-devices-error');
            const devicesRefresh = document.getElementById('mobile-app-devices-refresh');
            const devicesPager = document.getElementById('mobile-app-devices-pager');
            const devicesPageInfo = document.getElementById('mobile-app-devices-page-info');
            const devicesPrev = document.getElementById('mobile-app-devices-prev');
            const devicesNext = document.getElementById('mobile-app-devices-next');
            const devicesIdleHint = document.getElementById('mobile-app-devices-idle');
            const devicesPerPage = 5;
            let devicesPage = 1;
            // Server-computed: the browser origin, or the configured LAN host when
            // the dashboard is opened on loopback. Never the container's address.
            const endpoint = <?php echo json_encode($stepmonitorLanEndpoint, JSON_HEX_TAG); ?> || (window.location.origin + '/api/steps');
            const t = {
                copy: <?php echo json_encode($translations['copy'] ?? 'Copy', JSON_HEX_TAG); ?>,
                copied: <?php echo json_encode($translations['copied'] ?? 'Copied', JSON_HEX_TAG); ?>,
                tokenError: <?php echo json_encode($translations['mobile_app_token_error'] ?? 'Token generation failed', JSON_HEX_TAG); ?>,
                devicesError: <?php echo json_encode($translations['mobile_app_devices_error'] ?? 'Could not load devices.', JSON_HEX_TAG); ?>,
                connected: <?php echo json_encode($translations['mobile_app_devices_state_active'] ?? 'Connected', JSON_HEX_TAG); ?>,
                waiting: <?php echo json_encode($translations['mobile_app_devices_state_pending'] ?? 'Waiting for the phone', JSON_HEX_TAG); ?>,
                created: <?php echo json_encode($translations['mobile_app_devices_created'] ?? 'Added {t}', JSON_HEX_TAG); ?>,
                lastUsed: <?php echo json_encode($translations['mobile_app_devices_last_used'] ?? 'Last used {t}', JSON_HEX_TAG); ?>,
                disconnect: <?php echo json_encode($translations['mobile_app_devices_disconnect'] ?? 'Disconnect', JSON_HEX_TAG); ?>,
                expires: <?php echo json_encode($translations['mobile_app_devices_expires'] ?? 'Expires {t}', JSON_HEX_TAG); ?>,
                page: <?php echo json_encode($translations['mobile_app_devices_page'] ?? 'Page {page} of {pages}', JSON_HEX_TAG); ?>,
                idleDays: <?php echo (int)round(max(3600, (int)(getenv('CLIENT_AUTH_TOKEN_IDLE_TTL') ?: (30 * 24 * 3600))) / 86400); ?>,
                idleHint: <?php echo json_encode($translations['mobile_app_devices_idle_hint'] ?? 'Devices unused for {days} days are disconnected automatically.', JSON_HEX_TAG); ?>
            };

            function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
            function fmt(unix) { return unix ? new Date(unix * 1000).toLocaleString() : ''; }
            function showPairError(message) { pairError.textContent = message || ''; pairError.hidden = !message; }
            function showDevicesError(message) { devicesError.textContent = message || ''; devicesError.hidden = !message; }

            async function copyText(text, btn) {
                if (!text) return;
                try { await navigator.clipboard.writeText(text); }
                catch (error) {
                    const area = document.createElement('textarea');
                    area.value = text; area.style.position = 'fixed'; area.style.opacity = '0';
                    document.body.appendChild(area); area.select();
                    try { document.execCommand('copy'); } catch (e) {}
                    area.remove();
                }
                btn.classList.add('is-copied');
                setTimeout(function () { btn.classList.remove('is-copied'); }, 1600);
            }

            async function csrf() {
                try {
                    const res = await fetch('/csrf', { method: 'GET', credentials: 'same-origin', cache: 'no-store' });
                    const data = await res.json().catch(function () { return null; });
                    return data && data.csrf ? String(data.csrf) : '';
                } catch (error) { return ''; }
            }
            async function request(action, extra) {
                const token = await csrf();
                const form = new FormData();
                form.append('action', action);
                form.append(action, '1');
                form.append('ajax', '1');
                form.append('token_app', 'steps');
                if (token) form.append('_csrf', token);
                Object.keys(extra || {}).forEach(function (key) { form.append(key, String(extra[key])); });
                const headers = token ? { 'X-CSRF-TOKEN': token } : undefined;
                const res = await fetch('/main', { method: 'POST', body: form, credentials: 'same-origin', headers: headers });
                return res.json().catch(function () { return null; });
            }

            function renderDevices(devices, meta) {
                meta = meta || {};
                devicesList.innerHTML = '';
                devicesEmpty.style.display = devices.length ? 'none' : 'block';
                devices.forEach(function (device) {
                    const li = document.createElement('li');
                    li.className = 'ma-device';
                    const top = document.createElement('div');
                    top.className = 'ma-device-top';
                    const name = document.createElement('strong');
                    name.className = 'ma-device-name';
                    name.textContent = device.name || 'Mobile app';
                    const state = document.createElement('span');
                    state.className = 'ma-device-state';
                    state.textContent = device.claimed ? t.connected : t.waiting;
                    top.appendChild(name); top.appendChild(state);

                    const meta = document.createElement('div');
                    meta.className = 'ma-device-meta';
                    const parts = [];
                    if (device.created_at) parts.push(t.created.replace('{t}', fmt(device.created_at)));
                    if (device.claimed && device.last_used_at) parts.push(t.lastUsed.replace('{t}', fmt(device.last_used_at)));
                    if (device.expires_at) parts.push(t.expires.replace('{t}', fmt(device.expires_at)));
                    meta.textContent = parts.join(' · ');

                    const row = document.createElement('div');
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'ma-btn ma-btn-danger';
                    btn.textContent = t.disconnect;
                    btn.addEventListener('click', async function () {
                        btn.disabled = true;
                        showDevicesError('');
                        try {
                            const data = await request('revoke_mcp_token', { token_hash: device.token_hash });
                            if (!data || !data.success) throw new Error((data && (data.error || data.message)) || t.devicesError);
                            await fetchDevices();
                            // The last row of a page may have just been removed.
                            if (!devicesList.children.length && devicesPage > 1) await fetchDevices(devicesPage - 1);
                        } catch (error) {
                            showDevicesError(String(error && error.message ? error.message : error));
                        } finally { btn.disabled = false; }
                    });
                    row.appendChild(btn);
                    li.appendChild(top); li.appendChild(meta); li.appendChild(row);
                    devicesList.appendChild(li);
                });
                const pages = Math.max(1, Number(meta.pages) || 1);
                devicesPage = Math.min(Math.max(1, Number(meta.page) || devicesPage), pages);
                const showPager = (Number(meta.total) || 0) > devicesPerPage;
                devicesPager.hidden = !showPager;
                if (showPager) {
                    devicesPageInfo.textContent = t.page.replace('{page}', String(devicesPage)).replace('{pages}', String(pages));
                    devicesPrev.disabled = devicesPage <= 1;
                    devicesNext.disabled = devicesPage >= pages;
                }
            }
            async function fetchDevices(page) {
                if (typeof page === 'number') devicesPage = Math.max(1, page);
                showDevicesError('');
                try {
                    const data = await request('list_mobile_tokens', { page: devicesPage, per_page: devicesPerPage });
                    if (!data || !data.success) throw new Error((data && (data.error || data.message)) || t.devicesError);
                    renderDevices(Array.isArray(data.devices) ? data.devices : [], data);
                } catch (error) {
                    showDevicesError(String(error && error.message ? error.message : error));
                }
            }

            connectBtn.addEventListener('click', async function () {
                connectBtn.disabled = true;
                showPairError('');
                try {
                    const data = await request('generate_mcp_token', { mcp_token_name: 'Health Connect' });
                    if (!data || !data.success || !data.token) throw new Error((data && (data.error || data.message)) || t.tokenError);
                    linkEl.textContent = 'stepmonitor://auth?endpoint=' + encodeURIComponent(endpoint) + '&token=' + encodeURIComponent(data.token);
                    fields.hidden = false;
                    await fetchDevices();
                } catch (error) {
                    showPairError(String(error && error.message ? error.message : error));
                } finally { connectBtn.disabled = false; }
            });

            modal.querySelectorAll('.ma-copy-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const target = document.getElementById(btn.getAttribute('data-copy-target'));
                    copyText(target ? target.textContent.trim() : '', btn);
                });
            });
            devicesRefresh.addEventListener('click', function () { fetchDevices(1); });
            devicesPrev.addEventListener('click', function () { if (devicesPage > 1) fetchDevices(devicesPage - 1); });
            devicesNext.addEventListener('click', function () { fetchDevices(devicesPage + 1); });
            if (devicesIdleHint) devicesIdleHint.textContent = t.idleHint.replace('{days}', String(t.idleDays));

            window.openMobileAppModal = function () {
                if (typeof openModal === 'function') openModal('mobile-app-modal');
                else { modal.style.display = 'flex'; modal.firstElementChild.classList.add('modal-content'); document.body.classList.add('modal-open'); }
                fetchDevices();
            };
            window.closeMobileAppModal = function () {
                if (typeof closeModal === 'function') closeModal('mobile-app-modal');
                else { modal.style.display = 'none'; document.body.classList.remove('modal-open'); }
            };
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal.style.display === 'flex') window.closeMobileAppModal();
            });

            // The header button opens the modal through the shared openModal()
            // helper, so refresh the device list whenever the modal becomes visible.
            new MutationObserver(function () {
                if (modal.style.display !== 'none') fetchDevices();
            }).observe(modal, { attributes: true, attributeFilter: ['style'] });

            const playProtectBtn = document.getElementById('mobile-app-play-protect-guide');
            playProtectBtn && playProtectBtn.addEventListener('click', function () {
                if (typeof openModal === 'function' && document.getElementById('play-protect-modal')) openModal('play-protect-modal');
            });
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
    
