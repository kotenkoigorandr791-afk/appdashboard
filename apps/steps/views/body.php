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
    'distance' => ['label' => $translations['distance_km'] ?? 'Distance', 'icon' => '<span class="data-type-icon" style="--metric-color:#22B573" aria-hidden="true"><i class="fas fa-location-dot"></i></span>'],
    'calories' => ['label' => $translations['calories'] ?? 'Calories', 'icon' => '<span class="data-type-icon" style="--metric-color:#FF6B4A" aria-hidden="true"><i class="fas fa-fire-flame-curved"></i></span>'],
    'heart_rate' => ['label' => $translations['heart_rate'] ?? 'Heart rate', 'icon' => '<span class="data-type-icon" style="--metric-color:#F44778" aria-hidden="true"><i class="fas fa-heart-pulse"></i></span>'],
    'sleep' => ['label' => $translations['sleep'] ?? 'Sleep', 'icon' => '<span class="data-type-icon" style="--metric-color:#4C5FD5" aria-hidden="true"><i class="fas fa-moon"></i></span>'],
    'weight' => ['label' => $translations['weight'] ?? 'Weight', 'icon' => '<span class="data-type-icon" style="--metric-color:#a78bfa" aria-hidden="true"><i class="fas fa-weight-scale"></i></span>'],
];
?>
    <div id="bg-layer" aria-hidden="true"></div>
    <script>(function(){var s=<?php echo json_encode($bgFull, JSON_HEX_TAG); ?>,el=document.getElementById('bg-layer'),im=new Image(),apply=function(){el.style.backgroundImage='url('+s+')';el.classList.add('ready')};im.decoding='async';im.onload=apply;im.src=s;if(im.complete)apply()})();</script>
    <div class="app-steps">
        <div class="app-topbar">
            <h1><?php echo htmlspecialchars($translations['steps_counter_title']); ?></h1>
            <div class="app-topbar-actions">
                <button id="sharepoint-share-button" type="button" title="<?php echo htmlspecialchars($translations['share'] ?? 'Share'); ?>" aria-label="<?php echo htmlspecialchars($translations['share'] ?? 'Share'); ?>"><i class="fas fa-share-nodes" style="font-size:22px" aria-hidden="true"></i></button>
                <div id="share-chooser" class="share-chooser" hidden>
                    <button type="button" id="share-choose-steps"><i class="fas fa-shoe-prints"></i> <?php echo htmlspecialchars($translations['share_steps'] ?? 'Share steps'); ?></button>
                    <button type="button" id="share-choose-location"><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($translations['location_share_title'] ?? 'Location sharing'); ?></button>
                </div>
                <button id="add-steps-button" title="<?php echo htmlspecialchars($translations['add_action_tooltip'] ?? $translations['add_steps_tooltip']); ?>">
                    <i class="fas fa-plus"></i>
                </button>
                <button id="stepmonitor-apk-button" type="button" title="<?php echo htmlspecialchars($translations['mobile_app_download'] ?? 'Download mobile app'); ?>" aria-label="<?php echo htmlspecialchars($translations['mobile_app_download'] ?? 'Download mobile app'); ?>"><i class="fas fa-mobile-screen-button"></i></button>
                <?php if (!empty($stepsData)): ?>
                <button id="stats-button" title="<?php echo htmlspecialchars($translations['statistics_navigation'] ?? 'Statistics'); ?>" aria-label="<?php echo htmlspecialchars($translations['statistics_navigation'] ?? 'Statistics'); ?>">
                    <i class="fas fa-chart-pie lucide-view-pie" style="font-size:24px" aria-hidden="true"></i>
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
        <div class="mode-switch" role="tablist" aria-label="<?php echo htmlspecialchars($translations['steps'] ?? 'Steps'); ?> / <?php echo htmlspecialchars($translations['map_title'] ?? 'Map'); ?>">
            <button type="button" class="mode-switch-btn active" data-mode="steps" role="tab" aria-selected="true">
                <i class="fas fa-shoe-prints" aria-hidden="true"></i> <?php echo htmlspecialchars($translations['steps'] ?? 'Steps'); ?>
            </button>
            <button type="button" class="mode-switch-btn" data-mode="map" role="tab" aria-selected="false">
                <i class="fas fa-map-location-dot" aria-hidden="true"></i> <?php echo htmlspecialchars($translations['map_title'] ?? 'Map'); ?>
            </button>
        </div>
        
        <div class="app-main">
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
        
        <div class="view-selector">
            <button class="view-btn active" data-view="year"><?php echo htmlspecialchars($translations['year']); ?></button>
            <button class="view-btn" data-view="month"><?php echo htmlspecialchars($translations['month']); ?></button>
            <button class="view-btn" data-view="week"><?php echo htmlspecialchars($translations['week']); ?></button>
        </div>

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
            <div class="goal-summary" id="goal-summary"></div>
        </div>
        
        </div>
        <div class="map-view" id="map-view" hidden>
                <!-- Map: viewport-bounded layers, provider settings, non-map summary -->
                <section id="ma-map" class="map-workspace" aria-label="<?php echo htmlspecialchars($translations['map_title'] ?? 'Location map'); ?>">
                    <div class="map-stage">
                        <div id="ma-map-canvas" role="application" aria-label="<?php echo htmlspecialchars($translations['map_title'] ?? 'Location map'); ?>"></div>
                        <div class="map-hud">
                            <p id="ma-map-summary" role="status" aria-live="polite"></p>
                            <p id="ma-map-error" role="alert" style="display:none;"></p>
                        </div>
                        <div class="map-vignette" aria-hidden="true"></div>
                        <button type="button" id="ma-sidebar-show" class="map-sidebar-show" title="<?php echo htmlspecialchars($translations['map_show_panel'] ?? 'Show panel'); ?>" aria-label="<?php echo htmlspecialchars($translations['map_show_panel'] ?? 'Show panel'); ?>" hidden>
                            <i class="fas fa-sliders" aria-hidden="true"></i>
                        </button>
                    </div>
                    <aside class="map-sidebar" aria-label="<?php echo htmlspecialchars($translations['map_controls'] ?? 'Map controls'); ?>">
                        <div class="map-sidebar-head">
                            <div class="map-sidebar-title">
                                <span class="map-sidebar-kicker"><?php echo htmlspecialchars($translations['map_controls'] ?? 'Map controls'); ?></span>
                            </div>
                            <button type="button" id="ma-sidebar-hide" class="map-day-btn" title="<?php echo htmlspecialchars($translations['map_hide_panel'] ?? 'Hide panel'); ?>" aria-label="<?php echo htmlspecialchars($translations['map_hide_panel'] ?? 'Hide panel'); ?>">
                                <i class="fas fa-angles-right" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="map-panel">
                            <span class="map-panel-title"><?php echo htmlspecialchars($translations['points_area_title'] ?? 'Delete points'); ?></span>
                            <div class="map-area-tools">
                                <button type="button" id="ma-area-toggle" class="map-day-btn" title="<?php echo htmlspecialchars($translations['points_area'] ?? 'Select area to delete points'); ?>"><i class="fas fa-vector-square" aria-hidden="true"></i></button>
                                <span id="ma-area-info" hidden></span>
                                <button type="button" id="ma-area-delete" class="trip-btn trip-btn-primary" hidden><i class="fas fa-trash"></i></button>
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
                                        <input type="date" id="ma-map-from" class="dp-native" tabindex="-1" aria-hidden="true">
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
                                        <input type="date" id="ma-map-to" class="dp-native" tabindex="-1" aria-hidden="true">
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
                                    'stops' => $translations['map_layer_stops'] ?? 'Stops',
                                    'places' => $translations['map_layer_places'] ?? 'Places',
                                    'visits' => $translations['map_layer_visits'] ?? 'Visits',
                                    'points' => $translations['map_layer_points'] ?? 'Points',
                                    'photos' => $translations['map_layer_photos'] ?? 'Photos',
                                    'speed' => $translations['map_layer_speed'] ?? 'Speed',
                                    'heatmap' => $translations['map_layer_heatmap'] ?? 'Heatmap',
                                    'explored' => $translations['map_layer_explored'] ?? 'Explored area',
                                ];
                                foreach ($mapLayers as $layerKey => $layerLabel): $checked = in_array($layerKey, ['trips', 'stops', 'places', 'visits'], true); ?>
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
                                    <button type="button" id="ma-trip-close" class="trip-close" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>"><i class="fas fa-xmark" aria-hidden="true"></i></button>
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
                        <button type="button" id="ma-map-refresh" class="map-apply-btn">
                            <i class="fas fa-rotate" aria-hidden="true"></i> <?php echo htmlspecialchars($translations['map_refresh'] ?? 'Refresh'); ?>
                        </button>
                    </aside>
                </section>
                <script>
                (function(){
                    function boot(){
                    const section=document.getElementById('ma-map');
                    if(!section) return;
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
                        summary: <?php echo json_encode($translations['map_summary'] ?? 'Trips: {trips} · Stops: {stops} · Places: {places} · Visits: {visits} · Points: {points}'); ?>
                    };
                    const iso = (d) => d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
                    if (!toInput.value) toInput.value = iso(new Date());
                    if (!fromInput.value) fromInput.value = iso(new Date(Date.now() - 864e5));
                    const fallbackProviders={
                        esri_dark:{name:'Esri Dark Gray Canvas',url:'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}',attribution:'Tiles © Esri',max_zoom:16},
                        esri_imagery:{name:'Esri Satellite',url:'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',attribution:'Tiles © Esri',max_zoom:19},
                        esri_street:{name:'Esri Streets',url:'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}',attribution:'Tiles © Esri',max_zoom:19},
                        osm:{name:'OpenStreetMap',url:'https://tile.openstreetmap.org/{z}/{x}/{y}.png',attribution:'© OpenStreetMap contributors',max_zoom:19}
                    };
                    // CARTO now watermarks keyless tiles, so the web UI only offers keyless providers.
                    const keylessProviders=['esri_dark','esri_imagery','esri_street','osm','opentopomap'];
                    let map=null, layerGroup=null, providers=Object.assign({},fallbackProviders), provider='esri_dark', busy=false, lastFeatures=[], didFit=false;
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
                    const selectedLayers = () => Array.from(section.querySelectorAll('input[data-layer]:checked')).map(i=>i.getAttribute('data-layer'));
                    const showError = (m) => { errorEl.textContent = m || ''; errorEl.style.display = m ? '' : 'none'; };
                    function ensureLeaflet(){
                        if (window.L) return Promise.resolve(window.L);
                        return new Promise((resolve, reject) => {
                            if (!document.getElementById('ma-leaflet-css')) {
                                const css=document.createElement('link');
                                css.id='ma-leaflet-css'; css.rel='stylesheet';
                                css.href='https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css';
                                document.head.appendChild(css);
                            }
                            const script=document.createElement('script');
                            script.src='https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js';
                            script.onload=()=>resolve(window.L);
                            script.onerror=()=>reject(new Error('leaflet_unavailable'));
                            document.head.appendChild(script);
                        });
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
                        if(map._tileLayer) map.removeLayer(map._tileLayer);
                        // Tiles go through the app's disk cache, so repeated pans and
                        // revisits do not re-download the same images from upstream.
                        const tileUrl='/apps/steps/api/map_tiles.php?provider='+encodeURIComponent(key)+'&z={z}&x={x}&y={y}';
                        map._tileLayer=window.L.tileLayer(tileUrl,{attribution:p.attribution,maxZoom:p.max_zoom||19,keepBuffer:4,updateWhenIdle:false,crossOrigin:true,className:'map-tiles'}).addTo(map);
                        canvas.dataset.provider=key;
                    }
                    function makeMap(L){
                        map=L.map(canvas,{zoomControl:false,attributionControl:false,preferCanvas:true,worldCopyJump:true}).setView([50.45,30.52],12);
                        ['mapBackdrop','routesGlow','routes'].forEach((name,i)=>{
                            const pane=map.createPane(name);
                            pane.style.zIndex=String(395+i*5);
                            pane.style.pointerEvents='none';
                        });
                        layerGroup=L.layerGroup().addTo(map);
                        L.control.zoom({position:'bottomright'}).addTo(map);
                        L.control.scale({position:'bottomleft',imperial:false,maxWidth:110}).addTo(map);
                        try{ L.control.attribution({position:'bottomleft',prefix:false}).addTo(map); }catch(e){}
                        selectProvider(provider);
                        map.on('moveend',()=>{ if(map.getZoom()>=2) fetchViewport(); });
                        window.addEventListener('steps-map-shown',()=>{
                            if(!map) return;
                            didFit=false;
                            requestAnimationFrame(()=>map.invalidateSize({pan:false}));
                            setTimeout(()=>{ map.invalidateSize({pan:false}); fetchViewport(); loadTrips(); },80);
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
                    function popupHtml(title, lines){
                        const div=document.createElement('div');
                        const strong=document.createElement('strong'); strong.textContent=title; div.appendChild(strong);
                        (lines||[]).forEach(line=>{ const p=document.createElement('div'); p.textContent=line; p.style.fontSize='11px'; p.style.opacity='.82'; div.appendChild(p); });
                        return div;
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
                        lastFeatures.forEach(feature=>{
                            const p=feature.properties||{};
                            if(!feature.geometry||!feature.geometry.coordinates) return;
                            if(p.layer==='trip'){
                                const isFocus=!!p.id&&focusTripId===p.id, isSel=!!p.id&&selTrips.has(p.id);
                                if(speedOn&&!isFocus&&!isSel) return;
                                const popup=popupHtml(p.title||p.boundary_reason||'Trip',['distance: '+Math.round(p.distance_m||0)+' m']);
                                const hl=isFocus?'#f43f5e':(isSel?'#facc15':null);
                                if(hl) L.geoJSON(feature,{pane:'routesGlow',style:{color:hl,weight:routeWidth*6,opacity:0.38,lineCap:'round',lineJoin:'round'},interactive:false}).addTo(layerGroup);
                                else L.geoJSON(feature,{pane:'routesGlow',style:{color:'#38bdf8',weight:routeWidth*3.2,opacity:0.16,lineCap:'round',lineJoin:'round'},interactive:false}).addTo(layerGroup);
                                L.geoJSON(feature,{pane:'routes',style:{color:hl||'#60a5fa',weight:hl?routeWidth+2:routeWidth,opacity:0.95,lineCap:'round',lineJoin:'round'}}).bindPopup(popup).addTo(layerGroup);
                            } else if(p.layer==='speed'){
                                const sc=['#38bdf8','#22c55e','#eab308','#f97316','#ef4444','#a855f7'][p.bucket]||'#60a5fa';
                                L.geoJSON(feature,{pane:'routesGlow',style:{color:sc,weight:routeWidth*3.2,opacity:0.16,lineCap:'round',lineJoin:'round'},interactive:false}).addTo(layerGroup);
                                L.geoJSON(feature,{pane:'routes',style:{color:sc,weight:routeWidth,opacity:0.95,lineCap:'round',lineJoin:'round'}}).bindPopup(popupHtml(Math.round(p.kmh||0)+' km/h',[p.started_at||''])).addTo(layerGroup);
                            } else if(p.layer==='place'){
                                if(p.radius_m) L.circle([feature.geometry.coordinates[1],feature.geometry.coordinates[0]],{pane:'mapBackdrop',radius:p.radius_m,color:p.color||'#22c55e',weight:1,opacity:0.6,fillColor:p.color||'#22c55e',fillOpacity:0.10}).addTo(layerGroup);
                                addPoint(feature,{radius:baseRadius+2,color:p.color||'#22c55e',fillColor:p.color||'#22c55e',fillOpacity:pointOpacity},p.name||'place',[p.tag]);
                            } else if(p.layer==='stop'){
                                addPoint(feature,{radius:baseRadius-1,color:'#f59e0b',fillColor:'#f59e0b',fillOpacity:pointOpacity},p.name||'Stop',[p.started_at||'']);
                            } else if(p.layer==='point_cluster'){
                                addCluster(feature, p.count||1);
                            } else if(p.layer==='photo_cluster'){
                                const [plon,plat]=feature.geometry.coordinates;
                                const photoPopup=popupHtml((p.count||1)+' photo(s)',[]);
                                if(p.thumbnail){ const img=document.createElement('img'); img.src=p.thumbnail; img.alt=''; img.loading='lazy'; img.style.cssText='max-width:180px;max-height:140px;border-radius:8px;margin-top:6px;display:block'; photoPopup.appendChild(img); }
                                const photoMarker=L.marker([plat,plon],{keyboard:false,icon:L.divIcon({className:'map-photo-marker',html:'<span><i class="fas fa-camera"></i></span>',iconSize:[28,28],iconAnchor:[14,14]})}).addTo(layerGroup);
                                photoMarker.bindPopup(photoPopup);
                            } else if(p.layer==='visit'){
                                addPoint(feature,{radius:baseRadius-0.5,color:'#a78bfa',fillColor:'#a78bfa',fillOpacity:pointOpacity},p.place_name||'visit',[p.arrived_at||'']);
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
                    }
                    function bboxQuery(){
                        if(!map) return '';
                        const b=map.getBounds();
                        const span=b.getEast()-b.getWest();
                        if(!isFinite(span)||span<=0||span>85||(b.getNorth()-b.getSouth())>85) return '';
                        return [b.getWest(),b.getSouth(),b.getEast(),b.getNorth()].map(v=>v.toFixed(5)).join(',');
                    }
                    let fetchPending=false;
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
                            const close=()=>{ pop.hidden=true; trigger.setAttribute('aria-expanded','false'); };
                            const open=()=>{
                                document.querySelectorAll('.dp-popover').forEach(p=>{p.hidden=true;});
                                view=parseYMD(native.value)||new Date();
                                render();
                                pop.hidden=false;
                                trigger.setAttribute('aria-expanded','true');
                                const rect=trigger.getBoundingClientRect(),
                                      width=Math.min(300, window.innerWidth-16),
                                      height=pop.offsetHeight||330;
                                let left=Math.min(Math.max(8,rect.left), window.innerWidth-width-8);
                                let top=rect.bottom+8;
                                if(top+height>window.innerHeight-8) top=Math.max(8,rect.top-height-8);
                                pop.style.width=width+'px';
                                pop.style.left=left+'px';
                                pop.style.top=top+'px';
                            };
                            trigger.addEventListener('click',event=>{
                                event.stopPropagation();
                                if(pop.hidden) open(); else close();
                            });
                            pop.addEventListener('click',event=>{
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
                                const feat=lastFeatures.find(f=>f.properties&&f.properties.layer==='trip'&&f.properties.id===trip.id);
                                if(focusTripId&&feat&&map&&window.L){ try{ map.fitBounds(window.L.geoJSON(feat).getBounds(),{padding:[60,60],maxZoom:16}); }catch(e){} }
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
                        loadTrips();
                    }
                    async function fetchViewport(){
                        if(busy){ fetchPending=true; return; }
                        busy=true;
                        summary.textContent=t.loading; showError('');
                        try{
                            const params=new URLSearchParams();
                            const bbox=didFit?bboxQuery():''; if(bbox) params.set('bbox',bbox);
                            params.set('from',fromInput.value+'T00:00:00Z');
                            params.set('to',toInput.value+'T23:59:59Z');
                            params.set('zoom',String(map?Math.round(map.getZoom()):12));
                            params.set('layers',selectedLayers().join(','));
                            const family=document.getElementById('ma-map-family');
                            if(family&&family.value) params.set('family_email',family.value);
                            const res=await fetch('/apps/steps/api/session_gateway.php?endpoint=geolocation_map&'+params.toString(),{credentials:'same-origin'});
                            const data=await res.json();
                            // An empty date range has no data extent, which the API reports as
                            // invalid_bbox; treat it as an empty map instead of an error.
                            if(data&&(data.error==='invalid_bbox'||data.error==='invalid_range')){
                                render([]);
                                summary.textContent=t.empty;
                                return;
                            }
                            if(!res.ok||!data.ok) throw new Error((data&&data.error)||t.error);
                            providers=data.providers||providers;
                            if(!provider) provider=(data.settings&&data.settings.provider)||(providers.esri_dark?'esri_dark':'osm');
                            if(!providerSel.options.length) renderProviders();
                            render(data.geojson.features);
                            const c=data.meta.counts||{};
                            summary.textContent=t.summary.replace('{trips}',c.trips||0).replace('{stops}',(c.stops||0)+(c.point_clusters||0)).replace('{places}',c.places||0).replace('{visits}',c.visits||0).replace('{points}',c.points||(c.point_clusters||0));
                            const mapReady=map && mapHost && !mapHost.hidden && canvas.clientWidth>60 && canvas.clientHeight>60;
                            if(mapReady && !didFit){
                                // data.bbox is the requested viewport on later calls, so fit to the
                                // rendered features instead of echoing the current view back.
                                const bounds=layerGroup.getBounds&&layerGroup.getBounds();
                                if(bounds&&bounds.isValid()){
                                    didFit=true;
                                    map.fitBounds(bounds,{padding:[40,40],animate:false,maxZoom:15});
                                } else if(data.bbox){
                                    didFit=true;
                                    map.fitBounds([[data.bbox.minLat,data.bbox.minLon],[data.bbox.maxLat,data.bbox.maxLon]],{padding:[28,28],animate:false,maxZoom:15});
                                }
                            }
                            if(data.geojson.features.length===0) summary.textContent=t.empty;
                        }catch(e){ showError((e&&e.message)||t.error); summary.textContent=''; }
                        finally{
                            busy=false;
                            if(fetchPending){ fetchPending=false; fetchViewport(); }
                        }
                    }
                    async function loadSettings(){
                        try{
                            const res=await fetch('/apps/steps/api/session_gateway.php?endpoint=geolocation_map&settings=1',{credentials:'same-origin'});
                            const data=await res.json();
                            if(data.ok){
                                providers=data.providers||{};
                                provider=(data.settings&&data.settings.provider)||(providers.esri_dark?'esri_dark':'osm');
                                if(!keylessProviders.includes(provider)) provider=providers.esri_dark?'esri_dark':'osm';
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
                          areaDel=document.getElementById('ma-area-delete'), areaCancel=document.getElementById('ma-area-cancel');
                    let areaMode=false, areaRect=null, areaStart=null, areaBox=null, areaBound=false;
                    function areaRange(){ return {from:fromInput.value+'T00:00:00Z',to:toInput.value+'T23:59:59Z'}; }
                    function areaClear(){
                        if(areaRect&&map) map.removeLayer(areaRect);
                        areaRect=null; areaStart=null; areaBox=null;
                        [areaInfo,areaDel,areaCancel].forEach(el=>{ if(el) el.hidden=true; });
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
                                areaBox=[b.getWest(),b.getSouth(),b.getEast(),b.getNorth()];
                                areaRect.setBounds(b);
                                try{
                                    const data=await pointsRequest(Object.assign({action:'count',bbox:areaBox},areaRange()));
                                    areaInfo.textContent=data.count+' '+tr('points_unit','points');
                                    areaInfo.hidden=false; areaDel.hidden=false; areaCancel.hidden=false;
                                    areaDel.innerHTML='<i class="fas fa-trash"></i> '+tr('points_delete','Delete');
                                    areaDel.disabled=data.count===0;
                                }catch(err){ showError((err&&err.message)||t.error); }
                            });
                        }
                    }
                    areaBtn?.addEventListener('click',()=>setAreaMode(!areaMode));
                    areaCancel?.addEventListener('click',areaClear);
                    areaDel?.addEventListener('click',async()=>{
                        if(!areaBox) return;
                        if(!await confirmDelete(tr('points_confirm','Delete these points permanently?')+' ('+areaInfo.textContent+')')) return;
                        try{
                            await pointsRequest(Object.assign({action:'delete',bbox:areaBox},areaRange()));
                            areaClear(); await fetchViewport(); loadTrips();
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
                                const row=document.createElement('label'); row.className='points-row';
                                const cb=document.createElement('input'); cb.type='checkbox'; cb.setAttribute('data-pt-id',r.id); cb.addEventListener('change',ptSync);
                                const text=document.createElement('span'); text.textContent=String(r.source_at).slice(0,19)+' · '+Number(r.latitude).toFixed(5)+', '+Number(r.longitude).toFixed(5);
                                const tag=document.createElement('em'); tag.textContent=r.quality_state+' · '+r.source_type+(r.accuracy_m?' · ±'+Math.round(r.accuracy_m)+' m':'');
                                row.appendChild(cb); row.appendChild(text); row.appendChild(tag); host.appendChild(row);
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
                        ov.innerHTML='<div class="picker-box"><div class="picker-head"><strong></strong><button type="button" class="picker-x" aria-label="Close"><i class="fas fa-xmark"></i></button></div><div class="picker-map"></div><div class="picker-foot"><span class="picker-coords">—</span><button type="button" class="excel-btn picker-ok" disabled>OK</button><button type="button" class="excel-btn picker-cancel"></button></div></div>';
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
                        const jump=document.createElement('input'); jump.type='number'; jump.min=1; jump.max=n; jump.placeholder='#'; jump.className='pager-jump';
                        jump.addEventListener('keydown',e=>{ if(e.key==='Enter'&&jump.value) ptGo(Number(jump.value)); });
                        host.appendChild(jump);
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
                    function setSidebar(hidden){
                        section.classList.toggle('is-collapsed',hidden);
                        if(showBtn) showBtn.hidden=!hidden;
                        try{ localStorage.setItem(SIDEBAR_KEY,hidden?'1':'0'); }catch(e){}
                        setTimeout(()=>{ if(map) map.invalidateSize({pan:false}); },260);
                    }
                    hideBtn?.addEventListener('click',()=>setSidebar(true));
                    showBtn?.addEventListener('click',()=>setSidebar(false));
                    try{ if(localStorage.getItem(SIDEBAR_KEY)==='1') setSidebar(true); }catch(e){}
                    const onRangeChanged=()=>{ didFit=false; fetchViewport(); loadTrips(); };
                    document.getElementById('ma-map-family')?.addEventListener('change',onRangeChanged);
                    (async function loadFamilyTargets(){
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
                    })();
                    const renderApi='/apps/steps/api/session_gateway.php?endpoint=geolocation_renders';
                    const rd=id=>document.getElementById(id);
                    let renderJob=null, renderPoll=null;
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
                    fromInput.addEventListener('change',()=>{ if(toInput.value&&fromInput.value>toInput.value){ toInput.value=fromInput.value; toInput.dispatchEvent(new Event('input',{bubbles:true})); const v=toInput.closest('.dp-field')?.querySelector('.dp-value'); const d=parseYMD(toInput.value); if(v&&d) v.textContent=d.toLocaleDateString(dateLocale(),{day:'numeric',month:'short',year:'numeric'}); } onRangeChanged(); });
                    toInput.addEventListener('change',()=>{ if(fromInput.value&&toInput.value<fromInput.value){ fromInput.value=toInput.value; const v=fromInput.closest('.dp-field')?.querySelector('.dp-value'); const d=parseYMD(fromInput.value); if(v&&d) v.textContent=d.toLocaleDateString(dateLocale(),{day:'numeric',month:'short',year:'numeric'}); } onRangeChanged(); });
                    document.getElementById('ma-map-day-prev')?.addEventListener('click',()=>shiftRange(-1));
                    document.getElementById('ma-map-day-next')?.addEventListener('click',()=>shiftRange(1));
                    initDatePickers();
                    initTripManager();
                    providerSel.addEventListener('change',()=>{ provider=providerSel.value; selectProvider(provider); saveSettings(); });
                    const syncSpeedLegend=()=>{ const l=document.getElementById('ma-speed-legend'); if(l) l.hidden=!selectedLayers().includes('speed'); };
                    section.querySelectorAll('input[data-layer]').forEach(input=>input.addEventListener('change',()=>{ syncSpeedLegend(); saveSettings(); fetchViewport(); }));
                    setTimeout(syncSpeedLegend,300);
                    setInterval(()=>{ if(!mapHost||mapHost.hidden) return; fetchViewport(); },30000);
                    (async function(){
                        await loadSettings();
                        try{
                            const L=await ensureLeaflet();
                            makeMap(L);
                        }
                        catch(e){
                            canvas.style.display='none';
                            showError(t.unavailable);
                        }
                        await fetchViewport();
                    })();
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
            <div id="stats-notification" class="notification" style="display:none;">
                <div class="notification-header">
                    <div class="notification-title">
                        <i class="fas fa-chart-line"></i> <?php echo htmlspecialchars($translations['max_steps']); ?>
                    </div>
                    <button class="close-notification"><i class="fas fa-xmark" aria-hidden="true"></i></button>
                </div>
                <div class="notification-content" id="stats-content"></div>
            </div>
            <div id="goal-notification" class="notification" style="display:none;">
                <div class="notification-header">
                    <div class="notification-title">
                        <i class="fas fa-trophy"></i> <?php echo htmlspecialchars($translations['weekly_goal']); ?>
                    </div>
                    <button class="close-notification"><i class="fas fa-xmark" aria-hidden="true"></i></button>
                </div>
                <div class="notification-content" id="goal-content"></div>
            </div>
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
            <div class="stats-content">
                <button class="modal-close"><i class="fas fa-xmark" aria-hidden="true"></i></button>
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
                <button class="modal-close"><i class="fas fa-xmark" aria-hidden="true"></i></button>
                <div class="info-scroll">
                <div class="info-header">
                    <div class="info-header-icon">
                        <i class="fas fa-file-lines" style="font-size:28px" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2><?php echo htmlspecialchars($translations['app_info'] ?? 'Application Information'); ?></h2>
                    </div>
                </div>
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
        <div id="mobile-app-modal" style="display:none;">
            <div class="mobile-app-content">
                <button class="modal-close" type="button" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>">
                    <i class="fas fa-xmark" style="font-size:14px" aria-hidden="true"></i>
                </button>

                <div class="ma-header">
                    <h1><?php echo htmlspecialchars($translations['mobile_app_modal_title'] ?? 'Mobile App'); ?></h1>
                    <p><?php echo htmlspecialchars($translations['info_card_mobile_text'] ?? 'Install the companion Android app to sync steps and health data from your phone to this dashboard.'); ?></p>
                </div>

                <!-- Timeline: day/month/year, explicit timezone, cursor pagination -->
                <section id="ma-timeline" aria-labelledby="ma-timeline-title" style="margin:0 0 18px;padding:14px 16px;border:1px solid rgba(127,127,127,.25);border-radius:14px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                        <h3 id="ma-timeline-title" style="margin:0;font-size:15px;"><?php echo htmlspecialchars($translations['timeline_title'] ?? 'History'); ?></h3>
                        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                            <select id="ma-tl-view" aria-label="<?php echo htmlspecialchars($translations['timeline_view'] ?? 'View'); ?>" style="font-size:12px;padding:3px 6px;border-radius:7px;border:1px solid rgba(127,127,127,.35);background:transparent;color:inherit;">
                                <option value="day"><?php echo htmlspecialchars($translations['timeline_day'] ?? 'Day'); ?></option>
                                <option value="month"><?php echo htmlspecialchars($translations['timeline_month'] ?? 'Month'); ?></option>
                                <option value="year"><?php echo htmlspecialchars($translations['timeline_year'] ?? 'Year'); ?></option>
                            </select>
                            <input type="date" id="ma-tl-date" style="font-size:12px;padding:3px 6px;border-radius:7px;border:1px solid rgba(127,127,127,.35);background:transparent;color:inherit;">
                            <input type="text" id="ma-tl-tz" aria-label="<?php echo htmlspecialchars($translations['timeline_timezone'] ?? 'Timezone'); ?>" placeholder="Europe/Kyiv" style="font-size:12px;padding:3px 6px;border-radius:7px;border:1px solid rgba(127,127,127,.35);background:transparent;color:inherit;width:130px;">
                            <input type="search" id="ma-tl-q" placeholder="<?php echo htmlspecialchars($translations['timeline_search'] ?? 'Search place or trip'); ?>" aria-label="<?php echo htmlspecialchars($translations['timeline_search'] ?? 'Search place or trip'); ?>" style="font-size:12px;padding:3px 6px;border-radius:7px;border:1px solid rgba(127,127,127,.35);background:transparent;color:inherit;width:160px;">
                        </div>
                    </div>
                    <p id="ma-tl-tz-note" style="font-size:11px;opacity:.65;margin:6px 0 0;"></p>
                    <p id="ma-tl-status" role="status" aria-live="polite" style="font-size:12px;opacity:.75;margin:6px 0;"></p>
                    <ul id="ma-tl-list" style="list-style:none;margin:0;padding:0;"></ul>
                    <button type="button" id="ma-tl-more" style="display:none;margin-top:8px;background:transparent;border:1px solid rgba(127,127,127,.35);border-radius:8px;padding:6px 10px;font-size:12px;cursor:pointer;color:inherit;"><?php echo htmlspecialchars($translations['timeline_more'] ?? 'Load more'); ?></button>
                    <p id="ma-tl-error" role="alert" style="display:none;color:#ef4444;font-size:12px;margin:6px 0 0;"></p>
                </section>
                <script>
                (function(){
                    const section=document.getElementById('ma-timeline');
                    if(!section) return;
                    const viewSel=document.getElementById('ma-tl-view');
                    const dateInput=document.getElementById('ma-tl-date');
                    const tzInput=document.getElementById('ma-tl-tz');
                    const queryInput=document.getElementById('ma-tl-q');
                    const list=document.getElementById('ma-tl-list');
                    const statusEl=document.getElementById('ma-tl-status');
                    const errorEl=document.getElementById('ma-tl-error');
                    const moreBtn=document.getElementById('ma-tl-more');
                    const t={
                        loading: <?php echo json_encode($translations['timeline_loading'] ?? 'Loading history…'); ?>,
                        empty: <?php echo json_encode($translations['timeline_empty'] ?? 'Nothing recorded for this period.'); ?>,
                        error: <?php echo json_encode($translations['timeline_error'] ?? 'Could not load history.'); ?>,
                        trip: <?php echo json_encode($translations['timeline_trip'] ?? 'Trip'); ?>,
                        stop: <?php echo json_encode($translations['timeline_stop'] ?? 'Stop'); ?>,
                        visit: <?php echo json_encode($translations['timeline_visit'] ?? 'Visit'); ?>,
                        gap: <?php echo json_encode($translations['timeline_gap'] ?? 'Gap'); ?>,
                        map: <?php echo json_encode($translations['timeline_show_on_map'] ?? 'Show on map'); ?>,
                        processing: <?php echo json_encode($translations['timeline_processing'] ?? 'Processing'); ?>,
                        tzNote: <?php echo json_encode($translations['timeline_timezone_note'] ?? 'Times are shown in {tz}.'); ?>
                    };
                    const kindLabel={trip:t.trip,stop:t.stop,visit:t.visit,gap:t.gap};
                    const kindColor={trip:'#2b8aef',stop:'#f59e0b',visit:'#8b5cf6',gap:'#94a3b8'};
                    let cursor=null, loading=false;
                    if(!dateInput.value) dateInput.value=new Date().toISOString().slice(0,10);
                    if(!tzInput.value){
                        try{ tzInput.value=Intl.DateTimeFormat().resolvedOptions().timeZone||'UTC'; }catch(e){ tzInput.value='UTC'; }
                    }
                    function fmtLocal(iso){
                        if(!iso) return '';
                        try{ return new Date(iso).toLocaleString(undefined,{dateStyle:'short',timeStyle:'short',timeZone:tzInput.value||'UTC'}); }catch(e){ return new Date(iso).toLocaleString(); }
                    }
                    function showError(message){ errorEl.textContent=message||''; errorEl.style.display=message?'':'none'; }
                    function renderEntry(entry){
                        const li=document.createElement('li');
                        li.style.cssText='display:flex;gap:10px;align-items:flex-start;padding:9px 0;border-top:1px solid rgba(127,127,127,.18);';
                        const dot=document.createElement('span');
                        dot.style.cssText='flex:0 0 8px;width:8px;height:8px;border-radius:50%;margin-top:5px;background:'+(kindColor[entry.kind]||'#94a3b8');
                        const body=document.createElement('div'); body.style.cssText='flex:1 1 auto;min-width:0;';
                        const head=document.createElement('div'); head.style.cssText='display:flex;justify-content:space-between;gap:8px;';
                        const title=document.createElement('strong'); title.style.cssText='font-size:13px;'; title.textContent=(kindLabel[entry.kind]||entry.kind)+' · '+(entry.title||'');
                        const when=document.createElement('span'); when.style.cssText='font-size:11px;opacity:.7;white-space:nowrap;'; when.textContent=fmtLocal(entry.local_started_at||entry.started_at);
                        head.appendChild(title); head.appendChild(when);
                        const meta=document.createElement('div'); meta.style.cssText='font-size:11px;opacity:.72;margin-top:2px;';
                        const parts=[];
                        if(entry.detail) parts.push(entry.detail);
                        if(entry.distance_m) parts.push(Math.round(entry.distance_m)+' m');
                        if(entry.duration_s) parts.push(Math.round(entry.duration_s/60)+' min');
                        if(entry.state&&entry.state!=='ready') parts.push(t.processing+': '+entry.state);
                        if(entry.processor_version) parts.push(entry.processor_version);
                        meta.textContent=parts.join(' · ');
                        body.appendChild(head); body.appendChild(meta);
                        if(entry.bbox){
                            const mapBtn=document.createElement('button');
                            mapBtn.type='button'; mapBtn.textContent=t.map;
                            mapBtn.style.cssText='margin-top:6px;background:transparent;border:1px solid rgba(127,127,127,.35);border-radius:7px;padding:3px 8px;font-size:11px;cursor:pointer;color:inherit;';
                            mapBtn.addEventListener('click',()=>window.dispatchEvent(new CustomEvent('steps-map-focus',{detail:{bbox:entry.bbox}})));
                            body.appendChild(mapBtn);
                        }
                        li.appendChild(dot); li.appendChild(body);
                        return li;
                    }
                    async function load(reset){
                        if(loading) return; loading=true;
                        if(reset){ cursor=null; list.innerHTML=''; moreBtn.style.display='none'; }
                        statusEl.textContent=t.loading; showError('');
                        try{
                            const params=new URLSearchParams({view:viewSel.value,at:dateInput.value,tz:tzInput.value||'UTC',limit:'50'});
                            if(queryInput.value.trim()) params.set('q',queryInput.value.trim());
                            if(cursor) params.set('cursor',cursor);
                            const res=await fetch('/apps/steps/api/session_gateway.php?endpoint=geolocation_timeline&'+params.toString(),{credentials:'same-origin'});
                            const data=await res.json();
                            if(!res.ok||!data.ok) throw new Error((data&&data.error)||t.error);
                            document.getElementById('ma-tl-tz-note').textContent=t.tzNote.replace('{tz}',data.timezone||'UTC');
                            (data.entries||[]).forEach(entry=>list.appendChild(renderEntry(entry)));
                            if(reset && data.photos && data.photos.length){
                                const photoItem=document.createElement('li');
                                photoItem.style.cssText='display:flex;gap:6px;overflow-x:auto;padding:9px 0;border-top:1px solid rgba(127,127,127,.18);';
                                data.photos.forEach(photo=>{
                                    const img=document.createElement('img');
                                    img.loading='lazy'; img.alt='';
                                    img.src='/apps/steps/api/session_gateway.php?endpoint=immich_thumbnail&id='+encodeURIComponent(photo.id);
                                    img.style.cssText='width:54px;height:54px;object-fit:cover;border-radius:8px;flex:0 0 auto';
                                    img.title=photo.captured_at||'';
                                    photoItem.appendChild(img);
                                });
                                list.appendChild(photoItem);
                            }
                            cursor=data.next_cursor||null;
                            moreBtn.style.display=cursor?'':'none';
                            statusEl.textContent=data.entries&&data.entries.length?(''+data.entries.length):(reset?t.empty:'');
                        }catch(e){ showError((e&&e.message)||t.error); statusEl.textContent=''; }
                        finally{ loading=false; }
                    }
                    viewSel.addEventListener('change',()=>load(true));
                    dateInput.addEventListener('change',()=>load(true));
                    tzInput.addEventListener('change',()=>load(true));
                    queryInput.addEventListener('input',()=>{ clearTimeout(queryInput._timer); queryInput._timer=setTimeout(()=>load(true),350); });
                    moreBtn.addEventListener('click',()=>load(false));
                    load(true);
                })();
                </script>

                <!-- Location sources: rename, pause, revoke, delete, re-enable -->
                <section id="ma-devices" style="margin:0 0 18px;padding:14px 16px;border:1px solid rgba(127,127,127,.25);border-radius:14px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:6px;">
                        <h3 style="margin:0;font-size:15px;"><?php echo htmlspecialchars($translations['devices_title'] ?? 'Location devices'); ?></h3>
                        <button type="button" id="ma-devices-refresh" style="background:transparent;border:1px solid rgba(127,127,127,.35);border-radius:8px;padding:5px 10px;font-size:12px;cursor:pointer;color:inherit;"><?php echo htmlspecialchars($translations['devices_refresh'] ?? 'Refresh'); ?></button>
                    </div>
                    <p id="ma-devices-empty" style="margin:4px 0 0;font-size:12px;opacity:.7;"><?php echo htmlspecialchars($translations['devices_empty'] ?? 'No devices have uploaded location yet.'); ?></p>
                    <ul id="ma-devices-list" style="list-style:none;margin:0;padding:0;"></ul>
                    <p id="ma-devices-error" style="display:none;color:#ef4444;font-size:12px;margin:8px 0 0;"></p>
                </section>
                <script>
                (function(){
                    const modal=document.getElementById('mobile-app-modal');
                    const list=document.getElementById('ma-devices-list');
                    const empty=document.getElementById('ma-devices-empty');
                    const errorEl=document.getElementById('ma-devices-error');
                    const refresh=document.getElementById('ma-devices-refresh');
                    if(!modal||!list) return;
                    const t={
                        refresh: <?php echo json_encode($translations['devices_refresh'] ?? 'Refresh'); ?>,
                        rename: <?php echo json_encode($translations['devices_rename'] ?? 'Rename'); ?>,
                        pause: <?php echo json_encode($translations['devices_pause'] ?? 'Pause'); ?>,
                        resume: <?php echo json_encode($translations['devices_resume'] ?? 'Resume'); ?>,
                        revoke: <?php echo json_encode($translations['devices_revoke'] ?? 'Revoke'); ?>,
                        delete: <?php echo json_encode($translations['devices_delete'] ?? 'Delete'); ?>,
                        enroll: <?php echo json_encode($translations['devices_enroll'] ?? 'Re-enable'); ?>,
                        active: <?php echo json_encode($translations['devices_status_active'] ?? 'Active'); ?>,
                        paused: <?php echo json_encode($translations['devices_status_paused'] ?? 'Paused'); ?>,
                        revoked: <?php echo json_encode($translations['devices_status_revoked'] ?? 'Revoked'); ?>,
                        deleted: <?php echo json_encode($translations['devices_status_deleted'] ?? 'Deleted'); ?>,
                        lastSeen: <?php echo json_encode($translations['devices_last_seen'] ?? 'Last seen {t}'); ?>,
                        points: <?php echo json_encode($translations['devices_points'] ?? '{n} points'); ?>,
                        confirmRevoke: <?php echo json_encode($translations['devices_confirm_revoke'] ?? 'Revoke this device? It stops uploading immediately.'); ?>,
                        confirmDelete: <?php echo json_encode($translations['devices_confirm_delete'] ?? 'Delete this device? Historical points stay attributed to it.'); ?>,
                        promptName: <?php echo json_encode($translations['devices_prompt_name'] ?? 'Device name'); ?>,
                        error: <?php echo json_encode($translations['devices_error'] ?? 'Could not update devices.'); ?>,
                        saved: <?php echo json_encode($translations['devices_saved'] ?? 'Device updated'); ?>
                    };
                    const sourceLabel={mobile:'Mobile',import:'Import',api:'API'};
                    let busy=false;
                    function esc(v){ return String(v==null?'':v).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
                    function fmt(iso){ if(!iso) return ''; const d=new Date(iso); return isNaN(d.getTime())?String(iso):d.toLocaleString(); }
                    function showError(message){ if(!errorEl) return; errorEl.textContent=message||''; errorEl.style.display=message?'':'none'; }
                    async function call(payload){
                        const res=await fetch('/apps/steps/api/session_gateway.php?endpoint=devices',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
                        const data=await res.json().catch(()=>({ok:false}));
                        if(!res.ok||!data.ok) throw new Error((data&&data.error)||t.error);
                        return data;
                    }
                    function button(label, action, device, extra){
                        const b=document.createElement('button');
                        b.type='button';
                        b.textContent=label;
                        b.style.cssText='background:transparent;border:1px solid rgba(127,127,127,.35);border-radius:7px;padding:4px 9px;font-size:11px;cursor:pointer;color:inherit;';
                        b.addEventListener('click', async ()=>{
                            if(busy) return;
                            let body=Object.assign({action:action,device_id:device.id}, extra||{});
                            if(action==='rename'){
                                const name=window.prompt(t.promptName, device.name||'');
                                if(name===null || !name.trim()) return;
                                body.name=name.trim();
                            }
                            if(action==='revoke' && !window.confirm(t.confirmRevoke)) return;
                            if(action==='delete' && !window.confirm(t.confirmDelete)) return;
                            busy=true; showError('');
                            try{ await call(body); await fetchDevices(); }
                            catch(e){ showError((e&&e.message)||t.error); }
                            finally{ busy=false; }
                        });
                        return b;
                    }
                    function renderList(devices){
                        list.innerHTML='';
                        if(empty) empty.style.display=devices.length?'none':'block';
                        devices.forEach(d=>{
                            const li=document.createElement('li');
                            li.style.cssText='padding:10px 0;border-top:1px solid rgba(127,127,127,.2);';
                            const head=document.createElement('div');
                            head.style.cssText='display:flex;align-items:center;justify-content:space-between;gap:8px;';
                            const name=document.createElement('strong');
                            name.textContent=d.name||d.id;
                            name.style.cssText='font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';
                            const status=document.createElement('span');
                            status.textContent=t[d.status]||d.status;
                            status.style.cssText='font-size:11px;opacity:.75;flex:0 0 auto;';
                            head.appendChild(name); head.appendChild(status);
                            const meta=document.createElement('div');
                            meta.style.cssText='font-size:11px;opacity:.7;margin:2px 0 8px;';
                            meta.textContent=[sourceLabel[d.source_type]||d.source_type, t.points.replace('{n}', String(d.point_count||0)), d.last_seen_at?t.lastSeen.replace('{t}', fmt(d.last_seen_at)):''].filter(Boolean).join(' · ');
                            const row=document.createElement('div');
                            row.style.cssText='display:flex;flex-wrap:wrap;gap:6px;';
                            row.appendChild(button(t.rename,'rename',d));
                            if(d.status==='active') row.appendChild(button(t.pause,'pause',d));
                            if(d.status==='paused') row.appendChild(button(t.resume,'resume',d));
                            if(d.status!=='revoked' && d.status!=='deleted') row.appendChild(button(t.revoke,'revoke',d));
                            if(d.status==='revoked') row.appendChild(button(t.enroll,'enroll',d));
                            if(d.status!=='deleted') row.appendChild(button(t.delete,'delete',d));
                            li.appendChild(head); li.appendChild(meta); li.appendChild(row);
                            list.appendChild(li);
                        });
                    }
                    async function fetchDevices(){
                        showError('');
                        try{
                            const res=await fetch('/apps/steps/api/session_gateway.php?endpoint=devices',{credentials:'same-origin'});
                            const data=await res.json();
                            if(!res.ok||!data.ok) throw new Error((data&&data.error)||t.error);
                            renderList(Array.isArray(data.devices)?data.devices:[]);
                        }catch(e){ showError((e&&e.message)||t.error); }
                    }
                    refresh?.addEventListener('click', fetchDevices);
                    new MutationObserver(()=>{ if(modal.style.display!=='none') fetchDevices(); })
                        .observe(modal,{attributes:true,attributeFilter:['style']});
                })();
                </script>

                <div class="ma-grid">
                    <div class="ma-action">
                        <button type="button" id="mobile-app-manual-hint" class="ma-qr-hint" title="<?php echo htmlspecialchars($translations['mobile_app_manual_hint_title'] ?? 'Manual pairing'); ?>" aria-label="<?php echo htmlspecialchars($translations['mobile_app_manual_hint_title'] ?? 'Manual pairing'); ?>">
                            <i class="fas fa-circle-question" style="font-size:14px" aria-hidden="true"></i>
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
                                <i class="fas fa-power-off" style="font-size:14px" aria-hidden="true"></i>
                                <?php echo htmlspecialchars($translations['mobile_app_qr_generate'] ?? 'Generate'); ?>
                            </button>
                        </div>
                        <div class="ma-qr-actions" id="mobile-app-qr-actions">
                            <button type="button" id="mobile-app-copy-link" class="ma-copy-link-btn">
                                <i class="fas fa-copy" style="font-size:14px" aria-hidden="true"></i>
                                <?php echo htmlspecialchars($translations['mobile_app_copy_link'] ?? 'Copy pairing link'); ?>
                            </button>
                            <button type="button" id="mobile-app-token-revoke" class="ma-qr-revoke">
                                <i class="fas fa-trash-can" style="font-size:14px" aria-hidden="true"></i>
                                <?php echo htmlspecialchars($translations['mobile_app_token_revoke'] ?? 'Revoke Token'); ?>
                            </button>
                        </div>
                        <h3><?php echo htmlspecialchars($translations['mobile_app_scan_title'] ?? 'Scan to Pair'); ?></h3>
                        <p><?php echo htmlspecialchars($translations['mobile_app_scan_desc'] ?? 'Open the app and scan this code to sync instantly.'); ?></p>
                        <p class="ma-qr-error" id="mobile-app-qr-error" style="display:none;color:#ef4444;font-size:12px;margin:-16px 0 16px;"></p>

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
                                <i class="fas fa-table-cells-large" style="font-size:20px" aria-hidden="true"></i>
                            </div>
                            <div class="ma-feature-text">
                                <h4><?php echo htmlspecialchars($translations['mobile_app_feature_qr_title'] ?? 'QR Pairing'); ?></h4>
                                <p><?php echo htmlspecialchars($translations['mobile_app_feature_qr_desc'] ?? 'Scan a QR code to pair the app with this dashboard in seconds.'); ?></p>
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
                                <i class="fas fa-power-off" style="font-size:20px" aria-hidden="true"></i>
                            </div>
                            <div class="ma-feature-text">
                                <h4><?php echo htmlspecialchars($translations['mobile_app_feature_offline_title'] ?? 'Offline Tracking'); ?></h4>
                                <p><?php echo htmlspecialchars($translations['mobile_app_feature_offline_desc'] ?? 'Track steps even without an internet connection.'); ?></p>
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

        <!-- Manual Pairing Modal -->
        <div id="manual-pair-modal" style="display:none;">
            <div class="manual-pair-content">
                <button type="button" id="manual-pair-close" class="modal-close" aria-label="<?php echo htmlspecialchars($translations['manual_pair_close'] ?? 'Close'); ?>">
                    <i class="fas fa-xmark" style="font-size:14px" aria-hidden="true"></i>
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
                            <i class="fas fa-copy ma-copy-icon" style="font-size:16px" aria-hidden="true"></i>
                            <i class="fas fa-check ma-copy-check" style="font-size:16px" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="manual-pair-row">
                        <div class="manual-pair-field">
                            <label><?php echo htmlspecialchars($translations['manual_pair_link_label'] ?? 'Connection link'); ?></label>
                            <pre id="manual-pair-link"></pre>
                        </div>
                        <button type="button" class="ma-copy-btn" data-copy-target="manual-pair-link" aria-label="<?php echo htmlspecialchars($translations['copy'] ?? 'Copy'); ?>">
                            <i class="fas fa-copy ma-copy-icon" style="font-size:16px" aria-hidden="true"></i>
                            <i class="fas fa-check ma-copy-check" style="font-size:16px" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="manual-pair-row">
                        <div class="manual-pair-field">
                            <label><?php echo htmlspecialchars($translations['manual_pair_token_label'] ?? 'Token'); ?></label>
                            <pre id="manual-pair-token"><?php echo htmlspecialchars($translations['manual_pair_generate_first'] ?? 'Generate a QR code first.'); ?></pre>
                        </div>
                        <button type="button" class="ma-copy-btn" data-copy-target="manual-pair-token" aria-label="<?php echo htmlspecialchars($translations['copy'] ?? 'Copy'); ?>">
                            <i class="fas fa-copy ma-copy-icon" style="font-size:16px" aria-hidden="true"></i>
                            <i class="fas fa-check ma-copy-check" style="font-size:16px" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div class="manual-pair-footer">
                    <button type="button" id="manual-pair-regenerate" class="manual-pair-regen-btn">
                        <i class="fas fa-rotate-left lucide-refresh-ccw" style="font-size:15px" aria-hidden="true"></i>
                        <?php echo htmlspecialchars($translations['manual_pair_regenerate'] ?? 'Regenerate'); ?>
                    </button>
                </div>
            </div>
        </div>


        <div id="share-location-modal" style="display:none;">
            <div class="share-location-content">
                <button class="modal-close" type="button"><i class="fas fa-xmark" aria-hidden="true"></i></button>
                <div class="location-panel">
                        <section class="location-section" id="family-sharing-section">
                            <div class="location-section-head">
                                <h3><i class="fas fa-shield-halved"></i> <?php echo htmlspecialchars($translations['location_share_title'] ?? 'Location sharing'); ?></h3>
                            </div>
                            <p class="location-hint"><?php echo htmlspecialchars($translations['location_share_hint'] ?? 'Share your location only with consent. You can pause or revoke access at any time.'); ?></p>
                            <div class="location-import-form">
                                <input type="email" id="share-viewer" maxlength="254" placeholder="<?php echo htmlspecialchars($translations['location_share_viewer'] ?? 'Person email'); ?>">
                                <select id="share-level">
                                    <option value="current"><?php echo htmlspecialchars($translations['share_level_current'] ?? 'Current location only'); ?></option>
                                    <option value="recent" selected><?php echo htmlspecialchars($translations['share_level_recent'] ?? 'Current + recent history'); ?></option>
                                    <option value="range"><?php echo htmlspecialchars($translations['share_level_range'] ?? 'Selected date range'); ?></option>
                                    <option value="places"><?php echo htmlspecialchars($translations['share_level_places'] ?? 'Places and visits only'); ?></option>
                                </select>
                                <input type="number" id="share-precision" min="0" max="5000" step="10" value="0" title="<?php echo htmlspecialchars($translations['location_share_precision'] ?? 'Precision, m (0 = exact)'); ?>">
                                <input type="date" id="share-until" title="<?php echo htmlspecialchars($translations['location_share_until'] ?? 'Until'); ?>">
                            </div>
                            <div class="location-import-form">
                                <input type="datetime-local" id="share-from">
                                <input type="datetime-local" id="share-to">
                                <input type="text" id="share-reason" maxlength="160" placeholder="<?php echo htmlspecialchars($translations['location_share_reason'] ?? 'Reason (shown to you)'); ?>">
                            </div>
                            <label class="location-consent"><input type="checkbox" id="share-consent"> <?php echo htmlspecialchars($translations['location_share_consent'] ?? 'I agree to share my location with this person'); ?></label>
                            <button type="button" id="share-create" class="excel-btn"><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($translations['location_share_add'] ?? 'Share location'); ?></button>
                            <p id="share-status" class="location-status" role="status" aria-live="polite"></p>
                            <div id="share-grants" class="location-import-history"></div>
                        </section>
                </div>
            </div>
        </div>
        <!-- Settings Modal -->
        <div id="settings-modal" style="display:none;">
            <div class="settings-content">
                <button class="modal-close"><i class="fas fa-xmark" aria-hidden="true"></i></button>
                <h2><i class="fas fa-cog"></i> <?php echo htmlspecialchars($translations['settings_title']); ?></h2>
                <div class="settings-tab-bar" role="tablist">
                    <button type="button" class="settings-tab active" data-settings-tab="profile" role="tab" aria-selected="true">
                        <i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($translations['settings_tab_profile'] ?? $translations['settings_title']); ?>
                    </button>
                    <button type="button" class="settings-tab" data-settings-tab="chart" role="tab" aria-selected="false">
                        <i class="fas fa-chart-area"></i> <?php echo htmlspecialchars($translations['settings_tab_chart'] ?? 'Chart'); ?>
                    </button>
                    <button type="button" class="settings-tab" data-settings-tab="map" role="tab" aria-selected="false">
                        <i class="fas fa-map-location-dot"></i> <?php echo htmlspecialchars($translations['map_settings_title'] ?? 'Map'); ?>
                    </button>
                    <button type="button" class="settings-tab" data-settings-tab="render" role="tab" aria-selected="false">
                        <i class="fas fa-film"></i> <span><?php echo htmlspecialchars($translations['map_render_title'] ?? 'Poster and video'); ?></span>
                    </button>
                    <button type="button" class="settings-tab" data-settings-tab="points" role="tab" aria-selected="false">
                        <i class="fas fa-location-crosshairs"></i> <span><?php echo htmlspecialchars($translations['points_title'] ?? 'Location points'); ?></span>
                    </button>
                    <button type="button" class="settings-tab" data-settings-tab="location" role="tab" aria-selected="false">
                        <i class="fas fa-earth-europe"></i> <?php echo htmlspecialchars($translations['location_data_title'] ?? 'Location data'); ?>
                    </button>
                    <button type="button" class="settings-tab" data-settings-tab="family" role="tab" aria-selected="false">
                        <i class="fas fa-people-roof"></i> <?php echo htmlspecialchars($translations['family_title'] ?? 'Family'); ?>
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
                        </section>
                    </div>
                </div>
                <div id="settings-panel-render" class="settings-tab-panel" role="tabpanel">
                    <div class="location-panel render-flow">
                        <section class="location-section">
                            <div class="location-section-head"><h3><span class="flow-step">1</span> <?php echo htmlspecialchars($translations['render_step_source'] ?? 'Route and period'); ?></h3></div>
                            <div class="settings-grid-2">
                                <div class="settings-field"><span><?php echo htmlspecialchars($translations['map_from'] ?? 'From'); ?></span><div class="dp-field"><input type="date" id="rd-from" class="dp-native" tabindex="-1" aria-hidden="true"><button type="button" class="dp-trigger" aria-haspopup="dialog" aria-expanded="false"><span class="dp-value"></span><i class="fas fa-calendar-days dp-icon" aria-hidden="true"></i></button><div class="dp-popover" role="dialog" hidden></div></div></div>
                                <div class="settings-field"><span><?php echo htmlspecialchars($translations['map_to'] ?? 'To'); ?></span><div class="dp-field"><input type="date" id="rd-to" class="dp-native" tabindex="-1" aria-hidden="true"><button type="button" class="dp-trigger" aria-haspopup="dialog" aria-expanded="false"><span class="dp-value"></span><i class="fas fa-calendar-days dp-icon" aria-hidden="true"></i></button><div class="dp-popover" role="dialog" hidden></div></div></div>
                            </div>
                            <label class="settings-field"><span><?php echo htmlspecialchars($translations['render_title'] ?? 'Title'); ?></span><input type="text" id="rd-title" maxlength="80"></label>
                        </section>
                        <section class="location-section">
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
                        </section>
                        <section class="location-section">
                            <div class="location-section-head"><h3><span class="flow-step">3</span> <?php echo htmlspecialchars($translations['render_step_run'] ?? 'Render'); ?></h3></div>
                            <div class="settings-actions-row">
                                <button type="button" id="rd-start" class="excel-btn"><i class="fas fa-play"></i> <?php echo htmlspecialchars($translations['render_start'] ?? 'Start'); ?></button>
                                <button type="button" id="rd-cancel" class="excel-btn" disabled><i class="fas fa-stop"></i> <?php echo htmlspecialchars($translations['render_cancel'] ?? 'Cancel'); ?></button>
                            </div>
                            <div class="render-progress" aria-hidden="true"><span id="rd-bar"></span></div>
                            <p id="rd-status" class="location-status" role="status" aria-live="polite"></p>
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
                                <label class="location-consent"><input type="checkbox" id="pt-all"> <?php echo htmlspecialchars($translations['points_select_page'] ?? 'Select page'); ?></label>
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
                    <div class="location-panel">
                        <section class="location-section">
                            <div class="location-section-head">
                                <h3><i class="fas fa-chart-simple"></i> <?php echo htmlspecialchars($translations['location_stats_title'] ?? 'Movement statistics'); ?></h3>
                                <div class="location-actions">
                                    <label class="location-tz"><span><?php echo htmlspecialchars($translations['timeline_timezone'] ?? 'Timezone'); ?></span><input type="text" id="location-stats-tz" value="UTC" placeholder="Europe/Kyiv"></label>
                                    <button type="button" id="location-stats-refresh" class="excel-btn"><i class="fas fa-rotate"></i> <?php echo htmlspecialchars($translations['location_stats_refresh'] ?? 'Recompute'); ?></button>
                                </div>
                            </div>
                            <p id="location-stats-status" class="location-status" role="status" aria-live="polite"></p>
                            <div id="location-stats-cards" class="location-cards"></div>
                            <div id="location-stats-warnings" class="location-warnings"></div>
                            <div id="location-stats-details" class="location-details"></div>
                            <div id="location-stats-boundary" class="location-hint"></div>
                            <ul id="location-stats-assumptions" class="location-assumptions"></ul>
                        </section>
                        <section class="location-section">
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
                        <section class="location-section">
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
                        <section class="location-section" id="location-tools-section">
                            <div class="location-section-head">
                                <h3><i class="fas fa-sliders"></i> <?php echo htmlspecialchars($translations['location_tools_title'] ?? 'Location management'); ?></h3>
                                <button type="button" id="location-quality-reprocess" class="excel-btn"><i class="fas fa-shield-halved"></i> <?php echo htmlspecialchars($translations['location_quality_reprocess'] ?? 'Recheck quality'); ?></button>
                            </div>
                            <p id="location-quality-counts" class="location-hint"></p>
                            <div class="location-tools-grid">
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
                        <section class="location-section" id="notifications-section">
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
                        <section class="location-section" id="immich-section">
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
        
        <div id="day-steps-modal" style="display:none;">
            <div class="day-steps-content">
                <button class="modal-close" type="button" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>"><i class="fas fa-xmark" aria-hidden="true"></i></button>
                <div class="day-steps-title"><?php echo htmlspecialchars($translations['steps'] ?? 'Steps'); ?></div>
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
                <button class="modal-close" type="button" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>"><i class="fas fa-xmark" aria-hidden="true"></i></button>
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
                    <button type="button" class="modal-close" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>"><i class="fas fa-xmark" aria-hidden="true"></i></button>
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
                    <i class="fas fa-xmark" style="font-size:18px" aria-hidden="true"></i>
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
                    <i class="fas fa-xmark" style="font-size:18px" aria-hidden="true"></i>
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
                    <i class="fas fa-xmark" style="font-size:18px" aria-hidden="true"></i>
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
            <button type="button" id="global-image-viewer-close" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>"><i class="fas fa-xmark" aria-hidden="true"></i></button>
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
        (function(){
            const modal=document.getElementById('mobile-app-modal');
            const qrFrame=document.getElementById('mobile-app-qr-code');
            const placeholder=modal ? modal.querySelector('.ma-qr-placeholder') : null;
            const genBtn=document.getElementById('mobile-app-qr-generate');
            const revokeBtn=document.getElementById('mobile-app-token-revoke');
            const actionsEl=document.getElementById('mobile-app-qr-actions');
            const copyLinkBtn=document.getElementById('mobile-app-copy-link');
            const hintBtn=document.getElementById('mobile-app-manual-hint');

            const lanEndpoint=<?php echo json_encode($stepmonitorLanEndpoint, JSON_HEX_TAG); ?>;
            const currentHost=window.location.hostname;
            const endpoint=(lanEndpoint && (currentHost==='localhost'||currentHost==='127.0.0.1'||currentHost==='::1')) ? lanEndpoint : window.location.origin + '/api/steps';

            let pairing={token:'',link:''};

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
                if(actionsEl) actionsEl.classList.add('is-visible');
                refreshHintFields();
            }
            function clearPairingUi(){
                pairing={token:'',link:''};
                if(qrFrame){ qrFrame.innerHTML=''; qrFrame.style.display='none'; }
                if(placeholder) placeholder.style.display='';
                if(genBtn) genBtn.style.display='';
                if(actionsEl) actionsEl.classList.remove('is-visible');
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

            copyLinkBtn?.addEventListener('click', async ()=>{
                if(!pairing.link){
                    copyLinkBtn.classList.add('is-empty');
                    setTimeout(()=>copyLinkBtn.classList.remove('is-empty'),700);
                    return;
                }
                try{
                    await navigator.clipboard.writeText(pairing.link);
                }catch(e){
                    const ta=document.createElement('textarea');
                    ta.value=pairing.link;
                    ta.style.position='fixed';
                    ta.style.opacity='0';
                    document.body.appendChild(ta);
                    ta.select();
                    try{ document.execCommand('copy'); }catch(e2){}
                    ta.remove();
                }
                copyLinkBtn.classList.add('is-copied');
                setTimeout(()=>copyLinkBtn.classList.remove('is-copied'),1600);
            });

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
    
