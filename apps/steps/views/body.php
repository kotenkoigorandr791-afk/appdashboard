<body>
    <?php $bgFull = $theme === 'dark' ? 'background_dark.jpg' : 'background_light.jpg'; ?>
    <div id="bg-layer" aria-hidden="true"></div>
    <script>(function(){var s=<?php echo json_encode($bgFull); ?>,el=document.getElementById('bg-layer'),im=new Image(),apply=function(){el.style.backgroundImage='url('+s+')';el.classList.add('ready')};im.decoding='async';im.onload=apply;im.src=s;if(im.complete)apply()})();</script>
    <div class="app-steps">
        <div class="app-topbar">
            <h1><?php echo htmlspecialchars($translations['steps_counter_title']); ?></h1>
            <div class="app-topbar-actions">
                <button id="add-steps-button" title="<?php echo htmlspecialchars($translations['add_action_tooltip'] ?? $translations['add_steps_tooltip']); ?>">
                    <i class="fas fa-plus"></i>
                </button>
                <button id="excel-button" title="<?php echo htmlspecialchars($translations['excel_view'] ?? 'Excel View'); ?>">
                    <i class="fas fa-table"></i>
                </button>
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
    <button class="data-type-btn active" data-type="steps">
        <img src="steps.webp" alt="<?php echo htmlspecialchars($translations['steps']); ?>">
    </button>
    <button class="data-type-btn" data-type="distance">
        <img src="distance.webp" alt="<?php echo htmlspecialchars($translations['distance_km']); ?>">
    </button>
    <button class="data-type-btn" data-type="calories">
        <img src="kkal.webp" alt="<?php echo htmlspecialchars($translations['calories']); ?>">
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
        
        <!-- Info Modal -->
        <div id="info-modal" style="display:none;">
            <div class="info-content">
                <button class="modal-close">×</button>
                <h2><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($translations['app_info'] ?? 'Application Information'); ?></h2>
                
                <div class="info-section">
                    <h3><i class="fas fa-walking"></i> <?php echo htmlspecialchars($translations['main_features'] ?? 'Main Features'); ?></h3>
                    <ul>
                        <li><?php echo htmlspecialchars($translations['feature_steps_tracking'] ?? 'Track your daily steps with detailed statistics'); ?></li>
                        <li><?php echo htmlspecialchars($translations['feature_visualization'] ?? 'Visualize data with interactive charts (bar and line charts)'); ?></li>
                        <li><?php echo htmlspecialchars($translations['feature_calendar_view'] ?? 'Calendar view with goal achievement indicators'); ?></li>
                        <li><?php echo htmlspecialchars($translations['feature_multiple_views'] ?? 'Multiple view modes: Year, Month, and Week'); ?></li>
                        <li><?php echo htmlspecialchars($translations['feature_data_types'] ?? 'Different data types: Steps, Distance, and Calories'); ?></li>
                        <li><?php echo htmlspecialchars($translations['feature_excel_editor'] ?? 'Excel-like table editor for managing your data'); ?></li>
                        <li><?php echo htmlspecialchars($translations['feature_backup_system'] ?? 'Automatic backup system with restore functionality'); ?></li>
                    </ul>
                </div>
                
                <div class="info-section">
                    <h3><i class="fas fa-keyboard"></i> <?php echo htmlspecialchars($translations['hotkeys'] ?? 'Hotkeys'); ?></h3>
                    <ul>
                        <li><?php echo htmlspecialchars($translations['hotkey_enter'] ?? 'Press Enter to save edited cell'); ?></li>
                        <li><?php echo htmlspecialchars($translations['hotkey_escape'] ?? 'Press Escape to cancel editing'); ?></li>
                        <li><?php echo htmlspecialchars($translations['hotkey_delete'] ?? 'Press Delete to clear selected cells'); ?></li>
                        <li><?php echo htmlspecialchars($translations['hotkey_ctrl_a'] ?? 'Ctrl+A to select all cells in Excel view'); ?></li>
                        <li><?php echo htmlspecialchars($translations['hotkey_ctrl_s'] ?? 'Ctrl+S to save changes in Excel view'); ?></li>
                        <li><?php echo htmlspecialchars($translations['hotkey_shift_select'] ?? 'Hold Shift to select multiple cells'); ?></li>
                    </ul>
                </div>
                
                <div class="info-section">
                    <h3><i class="fas fa-table"></i> <?php echo htmlspecialchars($translations['excel_features'] ?? 'Excel View Features'); ?></h3>
                    <ul>
                        <li><?php echo htmlspecialchars($translations['excel_edit'] ?? 'Double-click any cell to edit its value'); ?></li>
                        <li><?php echo htmlspecialchars($translations['excel_select'] ?? 'Click and drag to select multiple cells'); ?></li>
                        <li><?php echo htmlspecialchars($translations['excel_manage'] ?? 'Manage backups directly from the Excel view'); ?></li>
                        <li><?php echo htmlspecialchars($translations['excel_restore'] ?? 'Restore data from any backup point'); ?></li>
                    </ul>
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
                        <div class="save-button-container">
                            <button type="submit" class="save-button">
                                <i class="fas fa-save"></i> <?php echo htmlspecialchars($translations['save']); ?>
                            </button>
                        </div>
                    </form>
                </div>
                <div id="settings-panel-chart" class="settings-tab-panel" role="tabpanel">
                    <p class="settings-chart-type-intro"><?php echo htmlspecialchars($translations['settings_chart_type_hint'] ?? 'Choose how the main chart is drawn.'); ?></p>
                    <div class="settings-chart-type-group">
                        <button type="button" class="chart-type-btn active" data-type="bar">
                            <i class="fas fa-chart-bar"></i>
                            <span><?php echo htmlspecialchars($translations['bar_chart'] ?? 'Bar Chart'); ?></span>
                        </button>
                        <button type="button" class="chart-type-btn" data-type="line">
                            <i class="fas fa-chart-line"></i>
                            <span><?php echo htmlspecialchars($translations['linear_chart'] ?? 'Linear Chart'); ?></span>
                        </button>
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
                    <div class="save-button-container">
                        <button id="add-steps-submit" class="save-button">
                            <i class="fas fa-save"></i> <?php echo htmlspecialchars($translations['add_steps']); ?>
                        </button>
                    </div>
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
                                    <button type="button" class="va-step-dot is-active" data-step-target="1">1</button>
                                    <span class="va-step-line"></span>
                                    <button type="button" class="va-step-dot" data-step-target="2" disabled>2</button>
                                    <span class="va-step-line"></span>
                                    <button type="button" class="va-step-dot" data-step-target="3" disabled>3</button>
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
                                        <button type="button" class="takeout-guide-toggle" id="zeep-guide-toggle" aria-expanded="false" title="<?php echo htmlspecialchars($translations['show'] ?? 'Show'); ?>"><i class="fas fa-chevron-down"></i></button>
                                    </div>
                                    <div class="takeout-guide-body">
                                        <iframe
                                            id="zeep-guide-frame"
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
                                    <button type="button" class="va-step-dot is-active" data-step-target="1">1</button>
                                    <span class="va-step-line"></span>
                                    <button type="button" class="va-step-dot" data-step-target="2" disabled>2</button>
                                    <span class="va-step-line"></span>
                                    <button type="button" class="va-step-dot" data-step-target="3" disabled>3</button>
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
                                        <button type="button" class="takeout-guide-toggle" id="takeout-guide-toggle" aria-expanded="false" title="<?php echo htmlspecialchars($translations['show'] ?? 'Show'); ?>"><i class="fas fa-chevron-down"></i></button>
                                    </div>
                                    <div class="takeout-guide-body">
                                        <iframe
                                            id="takeout-guide-frame"
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
        
        <!-- Excel Modal -->
        <div id="excel-modal" style="display:none;">
            <div class="excel-content">
                <button class="modal-close">×</button>
                <div class="excel-modal-head">
                    <button type="button" id="excel-hotkeys-open" class="excel-head-side-left" title="<?php echo htmlspecialchars($translations['hotkeys'] ?? 'Hotkeys'); ?>" aria-label="<?php echo htmlspecialchars($translations['hotkeys'] ?? 'Hotkeys'); ?>"><i class="fas fa-info-circle"></i></button>
                    <h2><i class="fas fa-table"></i> <?php echo htmlspecialchars($translations['excel_view'] ?? 'Excel View'); ?></h2>
                    <span class="excel-head-spacer" aria-hidden="true"></span>
                </div>
                <div class="excel-search-container">
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                        <div style="display: flex; gap: 5px; align-items: center; flex-wrap: wrap;">
                            <input type="text" id="search-steps" placeholder="<?php echo htmlspecialchars($translations['search_steps'] ?? 'Search steps...'); ?>" style="padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--modal-bg); color: var(--text-color); width: 150px;">
                            <select id="search-operator" style="padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--modal-bg); color: var(--text-color);">
                                <option value="="><?php echo htmlspecialchars($translations['equals'] ?? '='); ?></option>
                                <option value=">">></option>
                                <option value="<"><</option>
                                <option value=">=">>=</option>
                                <option value="<="><=</option>
                                <option value="range"><?php echo htmlspecialchars($translations['range'] ?? 'Range'); ?></option>
                            </select>
                            <input type="text" id="search-steps-to" placeholder="<?php echo htmlspecialchars($translations['to'] ?? 'to'); ?>" style="padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--modal-bg); color: var(--text-color); width: 150px; display: none;">
                            <input type="date" id="search-date-from" title="<?php echo htmlspecialchars($translations['from'] ?? 'From'); ?>" style="padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--modal-bg); color: var(--text-color);">
                            <input type="date" id="search-date-to" title="<?php echo htmlspecialchars($translations['to'] ?? 'To'); ?>" style="padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--modal-bg); color: var(--text-color);">
                            <button id="search-btn" class="excel-btn" style="padding: 8px 15px;">
                                <i class="fas fa-search"></i> <?php echo htmlspecialchars($translations['search'] ?? 'Search'); ?>
                            </button>
                            <button id="clear-search" class="excel-btn" style="padding: 8px 15px; background: #6c757d;">
                                <i class="fas fa-times"></i> <?php echo htmlspecialchars($translations['clear'] ?? 'Clear'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="excel-table-stack">
                    <div class="excel-table-container" id="excel-table-container">
                    </div>
                    <div class="excel-table-pagination-bar" id="excel-table-pagination"></div>
                </div>
                <div class="excel-actions">
                    <button class="excel-btn delete" id="delete-selected">
                        <i class="fas fa-trash"></i> <?php echo htmlspecialchars($translations['delete_selected'] ?? 'Delete Selected'); ?>
                    </button>
                    <button class="excel-btn create-backup" id="create-backup" style="background: #17a2b8;">
                        <i class="fas fa-plus-circle"></i> <?php echo htmlspecialchars($translations['create_backup'] ?? 'Create Backup'); ?>
                    </button>
                    <button class="excel-btn restore" id="restore-backup">
                        <i class="fas fa-history"></i> <?php echo htmlspecialchars($translations['restore_backup'] ?? 'Restore from Backup'); ?>
                    </button>
                    <button class="excel-btn manage-backups" id="manage-backups">
                        <i class="fas fa-database"></i> <?php echo htmlspecialchars($translations['manage_backups'] ?? 'Manage Backups'); ?>
                    </button>
                    <button class="excel-btn download" id="download-backup">
                        <i class="fas fa-download"></i> <?php echo htmlspecialchars($translations['download_backup'] ?? 'Download Backup'); ?>
                    </button>
                </div>
            </div>
        </div>
        <div id="excel-hotkeys-modal" style="display:none;">
            <div class="excel-hotkeys-inner">
                <button class="modal-close">×</button>
                <h2><i class="fas fa-keyboard"></i> <?php echo htmlspecialchars($translations['hotkeys'] ?? 'Hotkeys'); ?></h2>
                <div class="excel-hotkeys-info">
                    <strong><?php echo htmlspecialchars($translations['navigation'] ?? 'Navigation:'); ?></strong>
                    <span class="hotkey">↑↓←→</span> - <?php echo htmlspecialchars($translations['move_cells'] ?? 'Move between cells'); ?>
                    <span class="hotkey">Enter</span> - <?php echo htmlspecialchars($translations['save_edit'] ?? 'Save edit'); ?>
                    <span class="hotkey">Esc</span> - <?php echo htmlspecialchars($translations['cancel_edit'] ?? 'Cancel edit'); ?>
                    <span class="hotkey">Ctrl+A</span> - <?php echo htmlspecialchars($translations['select_all'] ?? 'Select all'); ?>
                    <span class="hotkey">Ctrl+S</span> - <?php echo htmlspecialchars($translations['save_changes'] ?? 'Save changes'); ?>
                    <span class="hotkey">Delete</span> - <?php echo htmlspecialchars($translations['clear_selected'] ?? 'Clear selected'); ?>
                </div>
            </div>
        </div>
        
        <!-- Backups Modal -->
        <div id="backups-modal" style="display:none;">
            <div class="backups-content">
                <button class="modal-close">×</button>
                <h2><i class="fas fa-database"></i> <?php echo htmlspecialchars($translations['manage_backups'] ?? 'Manage Backups'); ?></h2>
                
                <div class="backups-list" id="backups-list">
                    <!-- Backups will be loaded here -->
                </div>
                
                <div style="margin-top: 20px; text-align: center;">
                    <button id="refresh-backups" class="excel-btn">
                        <i class="fas fa-sync"></i> <?php echo htmlspecialchars($translations['refresh_backups'] ?? 'Refresh List'); ?>
                    </button>
                </div>

                <hr style="margin: 20px 0; border-color: var(--border-color);">
                <h3 style="margin: 0 0 12px; font-size: 15px;"><i class="fas fa-file-import"></i> <?php echo htmlspecialchars($translations['import_external_backup'] ?? 'Import from External Server'); ?></h3>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <div>
                        <label for="external-backup-file" style="display: block; margin-bottom: 4px; font-size: 13px; opacity: 0.8;"><?php echo htmlspecialchars($translations['external_backup_file'] ?? 'CSV backup file'); ?></label>
                        <input type="file" id="external-backup-file" accept=".csv,text/csv"
                            style="width: 100%; box-sizing: border-box; padding: 6px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--modal-bg); color: var(--text-color); font-size: 14px;">
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <label style="font-size: 13px; opacity: 0.8;"><?php echo htmlspecialchars($translations['import_mode'] ?? 'Import mode'); ?>:</label>
                        <label style="display: flex; align-items: center; gap: 5px; font-size: 13px; cursor: pointer;">
                            <input type="radio" name="external-import-mode" value="merge" checked> <?php echo htmlspecialchars($translations['import_mode_merge'] ?? 'Merge (keep existing)'); ?>
                        </label>
                        <label style="display: flex; align-items: center; gap: 5px; font-size: 13px; cursor: pointer;">
                            <input type="radio" name="external-import-mode" value="replace"> <?php echo htmlspecialchars($translations['import_mode_replace'] ?? 'Replace all data'); ?>
                        </label>
                    </div>
                    <div>
                        <button id="import-external-backup-btn" class="excel-btn" style="background: var(--primary-color); color: #fff;">
                            <i class="fas fa-file-import"></i> <?php echo htmlspecialchars($translations['import_external_btn'] ?? 'Import'); ?>
                        </button>
                    </div>
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
    </div>
    
