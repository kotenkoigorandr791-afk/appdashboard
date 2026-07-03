<body>

<!-- Import Modal -->
<div id="import-modal" class="modal">
    <div class="modal-content ocr-shell">
        <button class="modal-close ocr-shell-close" onclick="closeImportModal()" type="button">×</button>
        <?php $ocrLanguage=$language;$ocrTheme=$theme;$ocrPrimaryColor=$primaryColor;$ocrBaseCurrency=$userSettings['currency']??'USD';$ocrApiBase='utils/expense_ocr_app.php';$ocrRootId='embedded-ocr-wizard';include __DIR__.'/../utils/expense_ocr_wizard.php'; ?>
        <div id="ocr-log" style="display:none;"></div>
    </div>
</div>
<div id="unresolved-modal" class="modal">
    <div class="modal-content" style="max-width:760px;width:92%;">
        <button class="modal-close" onclick="closeModal('unresolved-modal')">×</button>
        <h3 style="margin-top:0;"><?= htmlspecialchars($translations['ocr_unresolved_title'] ?? 'Unresolved stores') ?></h3>
        <div id="unresolved-list" style="max-height:50vh;overflow:auto;"></div>
        <button onclick="submitUnresolvedMappings()" style="margin-top:12px;padding:10px;background:var(--primary-color);color:#fff;border:none;border-radius:6px;cursor:pointer;"><?= htmlspecialchars($translations['save_mappings'] ?? 'Save mappings') ?></button>
    </div>
</div>

<!-- Confirm Delete Modal -->
<div id="confirm-delete-modal" class="modal confirm-modal">
    <div class="modal-content">
        <h3><?= htmlspecialchars($translations['confirm_delete_store'] ?? 'Confirm Delete') ?></h3>
        <p id="confirm-delete-message">Are you sure?</p>
        <div class="button-group">
            <button class="confirm-btn" onclick="executeDelete()"><?= htmlspecialchars($translations['delete']) ?></button>
            <button class="cancel-btn" onclick="closeModal('confirm-delete-modal')"><?= htmlspecialchars($translations['close'] ?? 'Cancel') ?></button>
        </div>
    </div>
</div>
<div id="confirm-restore-backup-modal" class="modal confirm-modal" style="z-index:2001;">
    <div class="modal-content" style="max-width:420px;border-radius:12px;position:relative;padding-top:8px;">
        <button class="modal-close" onclick="pendingRestoreFile=null;closeModal('confirm-restore-backup-modal')" style="position:absolute;right:10px;top:10px;">×</button>
        <div class="restore-confirm-icon" aria-hidden="true">
            <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.95;">
                <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/>
                <path d="M21 3v5h-5"/>
                <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/>
                <path d="M8 16H3v5"/>
            </svg>
        </div>
        <h3 id="restore-backup-title" style="text-align:center;margin:4px 0 12px;font-size:1.2rem;"><?= htmlspecialchars($translations['restore_backup_title'] ?? 'Restore backup?') ?></h3>
        <p id="restore-backup-text" class="restore-confirm-text" style="font-size:15px;opacity:0.92;"><?= nl2br(htmlspecialchars($translations['confirm_restore_backup'] ?? 'Are you sure you want to restore this backup? Current data will be replaced.', ENT_QUOTES, 'UTF-8')) ?></p>
        <div class="button-group" style="justify-content:center;flex-wrap:wrap;margin-top:20px;">
            <button class="action-primary-btn" type="button" onclick="executeRestoreBackup()"><?= htmlspecialchars($translations['restore']) ?></button>
            <button class="cancel-btn" type="button" onclick="pendingRestoreFile=null;closeModal('confirm-restore-backup-modal')"><?= htmlspecialchars($translations['close'] ?? 'Cancel') ?></button>
        </div>
    </div>
</div>

<!-- Exchange Rates Modal -->
<div id="exchange-rates-modal" class="modal">
    <div class="modal-content" style="width:90%;max-width:1000px;">
        <button class="modal-close" onclick="closeModal('exchange-rates-modal')">×</button>
        <h3 style="margin-top:0;color:var(--text-color);"><?= htmlspecialchars($translations['exchange_rates']) ?></h3>
        <div class="form-row">
            <label><?= htmlspecialchars($translations['base_currency']) ?>:</label>
            <select id="base-currency-select" class="currency-select" onchange="loadExchangeRates()">
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
                <option value="UAH">UAH</option>
                <option value="PLN">PLN</option>
                <option value="BYN">BYN</option>
            </select>
        </div>
        <div id="rates-table-container">
            <table class="rates-table" id="rates-table">
                <thead>
                    <tr><th><?= htmlspecialchars($translations['currency']) ?></th><th><?= htmlspecialchars($translations['rate']) ?></th><th><?= htmlspecialchars($translations['vs_usd']) ?></th></tr>
                </thead>
                <tbody id="rates-tbody">
                    <tr><td colspan="3" style="text-align:center;"><?= htmlspecialchars($translations['no_data']) ?></td></tr>
                </tbody>
            </table>
        </div>
        <div class="rate-chart-container">
            <h4><?= htmlspecialchars($translations['rate_chart']) ?></h4>
            <div class="form-row">
                <label><?= htmlspecialchars($translations['select_currency']) ?>:</label>
                <select id="chart-currency-select" class="currency-select" onchange="updateRateChart()">
                    <option value="EUR">EUR</option>
                    <option value="UAH">UAH</option>
                    <option value="PLN">PLN</option>
                    <option value="BYN">BYN</option>
                </select>
            </div>
            <canvas id="rate-chart" style="max-height:300px; width:100%;"></canvas>
        </div>
    </div>
</div>

<div class="app-expenses">
    <div class="header">
        <h2><?= htmlspecialchars($translations['expense_tracker_title']) ?></h2>
        <div class="header-buttons">
            <div class="add-menu-wrap">
            <button id="import-btn" class="header-btn add-btn" type="button" title="<?= htmlspecialchars($translations['add']) ?>">
                <svg width="28" height="28" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="4">
                    <line x1="24" y1="12" x2="24" y2="36"/>
                    <line x1="12" y1="24" x2="36" y2="24"/>
                </svg>
            </button>
            <div id="import-menu" class="import-dropdown" role="menu">
                <button type="button" role="menuitem" id="import-opt-wallet"><?= htmlspecialchars($translations['import_menu_wallet'] ?? 'Google Wallet') ?></button>
                <button type="button" role="menuitem" id="import-opt-csv"><?= htmlspecialchars($translations['import_menu_csv'] ?? 'CSV') ?></button>
            </div>
            </div>
            <button class="header-btn" onclick="showOCRAnalyticsModal()" title="<?= htmlspecialchars($translations['analytics_title'] ?? 'Analytics') ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16v5"/><path d="M16 14.639V21"/><path d="M20 10.656V21"/><path d="m22 3-8.646 8.646a.5.5 0 0 1-.708 0L9.354 8.354a.5.5 0 0 0-.707 0L2 15"/><path d="M4 18.463V21"/><path d="M8 14.656V21"/></svg>
            </button>
            <button class="header-btn" onclick="showModal('settings-modal')" title="<?= htmlspecialchars($translations['settings_title'].' · '.$translations['table_settings']) ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19.14,12.94a1.43,1.43,0,0,0,0-1.88l2.11-1.65a.48.48,0,0,0,.12-.61l-2-3.46a.49.49,0,0,0-.6-.21l-2.49,1a5.14,5.14,0,0,0-1.62-.94l-.38-2.65A.48.48,0,0,0,13.81,2H10.19a.48.48,0,0,0-.48.41L9.33,5.06a5.14,5.14,0,0,0-1.62.94l-2.49-1a.49.49,0,0,0-.6.21l-2,3.46a.48.48,0,0,0,.12.61L4.86,11.06a1.43,1.43,0,0,0,0,1.88L2.75,14.59a.48.48,0,0,0-.12.61l2,3.46a.49.49,0,0,0,.6.21l2.49-1a5.14,5.14,0,0,0,1.62.94l.38,2.65a.48.48,0,0,0,.48.41h3.62a.48.48,0,0,0,.48-.41l.38-2.65a5.14,5.14,0,0,0,1.62-.94l2.49,1a.49.49,0,0,0,.6-.21l2-3.46a.48.48,0,0,0-.12-.61ZM12,15.5A3.5,3.5,0,1,1,15.5,12,3.5,3.5,0,0,1,12,15.5Z"/></svg>
            </button>
            <button class="header-btn" onclick="showModal('backup-modal')" title="Backup">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 12v7H5v-7H3v7c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-7h-2zm-6 .67l2.59-2.58L17 11.5l-5 5-5-5 1.41-1.41L11 12.67V3h2z"/></svg>
            </button>
            <button class="header-btn" onclick="showShareModal()" title="<?= htmlspecialchars($translations['share_chart']) ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/></svg>
            </button>
            <button class="header-btn" onclick="showModal('exchange-rates-modal'); loadExchangeRates();" title="<?= htmlspecialchars($translations['exchange_rates']) ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>
            </button>
            <button class="header-btn profile-avatar-btn" type="button" title="<?= htmlspecialchars($translations['user_management']) ?>">
                <img src="<?= $profilePhoto ?>" class="user-icon" alt="User Profile">
            </button>
        </div>
    </div>

    <div class="chart-header">
        <div class="chart-year-framed" id="chart-year"></div>
    </div>
    <div class="chart-container">
        <canvas id="expenses-chart"></canvas>
    </div>
    <div class="chart-nav-bottom">
        <button id="chart-year-prev-btn" class="chart-nav-btn" onclick="changeChartYear(-1)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
        </button>
        <button id="chart-year-next-btn" class="chart-nav-btn" onclick="changeChartYear(1)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
        </button>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;justify-content:center;gap:10px;margin-top:10px;">
            <button class="currency-btn" data-currency="USD" onclick="switchChartCurrency('USD')">USD</button>
            <button class="currency-btn" data-currency="EUR" onclick="switchChartCurrency('EUR')">EUR</button>
            <button class="currency-btn" data-currency="PLN" onclick="switchChartCurrency('PLN')">PLN</button>
            <button class="currency-btn" data-currency="UAH" onclick="switchChartCurrency('UAH')">UAH</button>
            <button class="currency-btn" data-currency="BYN" onclick="switchChartCurrency('BYN')">BYN</button>
        </div>
        <button class="chart-toggle-btn" onclick="toggleChartMode()"><?= htmlspecialchars($translations['total_expenses']) ?></button>
        <div class="chart-year" id="chart-year2"></div>
    </div>

    <div class="divider"></div>

    <div class="controls-container">
        <button class="add-date-btn" onclick="showModal('add-date-modal')"><?= htmlspecialchars($translations['add_date']) ?></button>
        <div class="search-container">
            <span class="search-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
            </span>
            <input type="text" class="search-box" id="search-input"
                placeholder="<?= htmlspecialchars($translations['search_placeholder']) ?>"
                oninput="filterTable()">
        </div>
    </div>

    <div id="preview-tooltip" style="display:none;position:absolute;background:var(--modal-bg);border:1px solid var(--primary-color);border-radius:4px;padding:5px 10px;font-size:14px;box-shadow:0 2px 10px rgba(0,0,0,.2);z-index:1000;pointer-events:none;">
        <span id="preview-tooltip-result"></span>
    </div>

    <div class="data-table-container">
        <table class="data-table" id="data-table">
            <thead>
                <tr>
                    <th><?= htmlspecialchars($translations['date']) ?></th>
                    <?php foreach ($visibleStores as $store):
                        $fn = preg_replace('/[^a-zA-Z0-9]/', '_', $store['name']);
                        $safe = getSafeFolderName($currentUser);
                        $storePhoto = file_exists(APPS_DATA_DIR . "$safe/store_$fn.jpg") ? "users/$safe/store_$fn.jpg" : null;
                    ?>
                        <th>
                            <div class="store-header">
                                <?php if ($storePhoto): ?><img src="<?= $storePhoto ?>" class="store-icon" alt="<?= htmlspecialchars($store['name']) ?>"><?php endif; ?>
                                <?= htmlspecialchars($store['name']) ?>
                            </div>
                        </th>
                    <?php endforeach; ?>
                    <?php if (!empty($userSettings['show_incomes'])): ?>
                        <th class="income-separator income-header"><div class="store-header"><svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path fill="#22c55e" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2Zm1 15.93V19h-2v-1.09c-1.96-.33-3.5-1.72-3.58-3.41h2.03c.08.85.95 1.52 2.05 1.52 1.16 0 2.08-.69 2.08-1.55 0-.9-.78-1.42-2.38-1.82-1.77-.44-3.5-1.08-3.5-3.12 0-1.63 1.3-2.86 3.3-3.17V5h2v1.08c1.82.3 3.05 1.44 3.2 3h-2.03c-.12-.72-.84-1.25-1.87-1.25-1.05 0-1.85.54-1.85 1.31 0 .78.69 1.2 2.27 1.61 1.8.46 3.62 1.16 3.62 3.35 0 1.78-1.38 3.18-3.34 3.53Z"/></svg><?= htmlspecialchars($translations['incomes'] ?? 'Incomes') ?></div></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($firstPageRows as $date => $rowData):
                    $dateObj = new DateTime($date);
                    $day = $dateObj->format('d');
                    $monthIndex = (int)$dateObj->format('m') - 1;
                    $month = ($monthNames[$language] ?? $monthNames['en'])[$monthIndex];
                    $year = $dateObj->format('Y');
                    $isToday = ($date === date('Y-m-d'));
                ?>
                    <tr data-date="<?= htmlspecialchars($date) ?>" class="<?= $isToday ? 'current-date-row' : '' ?>">
                        <td><?= htmlspecialchars("$day $month $year") ?></td>
                        <?php foreach ($visibleStores as $store):
                            $amount = $rowData[$store['name']] ?? 0;
                        ?>
                            <td>
                                <input type="text"
                                    data-date="<?= htmlspecialchars($date) ?>"
                                    data-store="<?= htmlspecialchars($store['name']) ?>"
                                    value="<?= $amount != 0 ? number_format($amount, 2, '.', '') : '' ?>"
                                    oninput="formatNumberInput(this)"
                                    onchange="scheduleSave(this)">
                            </td>
                        <?php endforeach; ?>
                        <?php if (!empty($userSettings['show_incomes'])):
                            $income = $allIncomes[$date] ?? 0;
                        ?>
                            <td class="income-separator">
                                <input type="text"
                                    data-date="<?= htmlspecialchars($date) ?>"
                                    data-income="1"
                                    value="<?= $income != 0 ? number_format($income, 2, '.', '') : '' ?>"
                                    oninput="formatNumberInput(this)"
                                    onchange="scheduleSave(this)">
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div id="pagination"></div>
</div>
<div id="ocr-analytics-modal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('ocr-analytics-modal')">×</button>
        <h3 style="margin-top:0;"><?= htmlspecialchars($translations['analytics_title'] ?? 'Analytics') ?></h3>
        <div class="chart-nav-bottom" style="margin:0 0 10px;">
            <button id="analytics-year-prev-btn" class="chart-nav-btn" onclick="changeAnalyticsYear(-1)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
            </button>
            <div class="chart-year-framed" id="analytics-year-label"></div>
            <button id="analytics-year-next-btn" class="chart-nav-btn" onclick="changeAnalyticsYear(1)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
            </button>
        </div>
        <div class="form-row">
            <label><?= htmlspecialchars($translations['store_name'] ?? 'Store') ?></label>
            <select id="ocr-analytics-store" class="currency-select" onchange="loadOCRAnalytics()"><option value="__all__"><?= htmlspecialchars($translations['all_stores'] ?? 'All stores') ?></option></select>
        </div>
        <div class="rate-chart-container"><canvas id="ocr-analytics-chart"></canvas></div>
    </div>
</div>

<div id="bank-csv-import-modal" class="modal" style="display:none;">
    <div class="modal-content">
        <button type="button" class="modal-close" onclick="closeBankCsvModal()">×</button>
        <h3 id="bank-csv-title" style="margin:0 0 8px;text-align:center;color:var(--text-color);"><?= htmlspecialchars($translations['bank_csv_title'] ?? 'Bank import') ?></h3>
        <p id="bank-csv-meta" style="margin:0 0 10px;font-size:13px;opacity:.9;text-align:center;color:var(--text-color);"></p>
        <p style="font-size:12px;opacity:.85;margin:0 0 8px;color:var(--text-color);"><?= htmlspecialchars($translations['bank_csv_hint'] ?? '') ?></p>
        <div id="bank-csv-conflict-box" style="display:none;margin-bottom:12px;padding:10px;border-radius:8px;background:var(--group-block-bg);border:1px solid var(--border-color);color:var(--text-color);">
            <div id="bank-csv-conflict-text" style="margin-bottom:8px;font-weight:600;"></div>
            <label style="display:block;margin:4px 0;"><input type="radio" name="bank-csv-conflict" value="skip" checked> <?= htmlspecialchars($translations['bank_csv_skip_conflicts'] ?? '') ?></label>
            <label style="display:block;margin:4px 0;"><input type="radio" name="bank-csv-conflict" value="replace"> <?= htmlspecialchars($translations['bank_csv_replace_conflicts'] ?? '') ?></label>
        </div>
        <div style="font-weight:600;margin:8px 0;color:var(--text-color);"><?= htmlspecialchars($translations['bank_csv_groups'] ?? '') ?></div>
        <div id="bank-csv-scroll"><div id="bank-csv-groups"></div></div>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:12px;">
            <button type="button" class="action-primary-btn" id="bank-csv-apply-btn" onclick="submitBankCsvImport()"><?= htmlspecialchars($translations['bank_csv_apply'] ?? 'Import') ?></button>
            <button type="button" class="cancel-btn" onclick="closeBankCsvModal()"><?= htmlspecialchars($translations['bank_csv_close'] ?? 'Close') ?></button>
        </div>
    </div>
</div>
<input type="file" id="bank-csv-input" accept=".csv,text/csv" style="display:none;">
<div class="notification-container" id="notification-container"></div>
<div id="amount-currency-popover" class="amount-currency-popover" style="display:none" role="listbox" aria-hidden="true"></div>

<!-- Settings Modal -->
<div id="settings-modal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('settings-modal')">×</button>
        <h3><?= htmlspecialchars($translations['settings_title']) ?></h3>
        <div class="settings-menu-bar">
            <button type="button" class="settings-menu-btn active" data-settings-section="stores" onclick="switchSettingsSection('stores')"><?= htmlspecialchars($translations['stores']) ?></button>
            <button type="button" class="settings-menu-btn" data-settings-section="table" onclick="switchSettingsSection('table')"><?= htmlspecialchars($translations['table_settings']) ?></button>
            <button type="button" class="settings-menu-btn" data-settings-section="incomes" onclick="switchSettingsSection('incomes')"><?= htmlspecialchars($translations['incomes'] ?? 'Incomes') ?></button>
            <button type="button" class="settings-menu-btn" data-settings-section="delete" onclick="switchSettingsSection('delete')"><?= htmlspecialchars($translations['data_deletion'] ?? 'Data deletion') ?></button>
        </div>
        <div class="settings-section active" id="settings-section-stores">
            <div class="form-row">
                <label><?= htmlspecialchars($translations['monthly_budget']) ?>:</label>
                <input type="number" id="monthly-budget" value="<?= $userSettings['monthly_budget'] ?>" step="0.01">
            </div>
            <div class="form-row">
                <label><?= htmlspecialchars($translations['currency']) ?>:</label>
                <select id="currency">
                    <?php foreach (['USD','EUR','PLN','UAH','BYN'] as $cur): ?>
                        <option value="<?= $cur ?>" <?= $userSettings['currency']===$cur?'selected':'' ?>><?= $cur ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="store-list" id="store-list">
                <?php foreach ($userSettings['stores'] as $store):
                    $fn = preg_replace('/[^a-zA-Z0-9]/', '_', $store['name']);
                    $safe = getSafeFolderName($currentUser);
                    $storePhoto = file_exists(APPS_DATA_DIR . "$safe/store_$fn.jpg") ? "users/$safe/store_$fn.jpg" : null;
                ?>
                    <div class="store-item" data-store-id="<?= $store['id'] ?>" data-store-name="<?= htmlspecialchars($store['name']) ?>">
                        <?php if ($storePhoto): ?><img src="<?= $storePhoto ?>" class="store-icon" alt="<?= htmlspecialchars($store['name']) ?>"><?php endif; ?>
                        <span style="flex:1;"><?= htmlspecialchars($store['name']) ?></span>
                        <button class="edit-store-btn" onclick="showAddEditStoreModal('<?= $store['id'] ?>','<?= htmlspecialchars($store['name'],ENT_QUOTES) ?>',<?= $store['visible']?'true':'false' ?>)">
                            <?= htmlspecialchars($translations['edit_store']) ?>
                        </button>
                        <button class="delete-store-btn" onclick="confirmDeleteStore('<?= $store['id'] ?>','<?= htmlspecialchars($store['name'],ENT_QUOTES) ?>')">
                            <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                            <?= htmlspecialchars($translations['delete']) ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="add-store-btn" onclick="showAddEditStoreModal()">+ <?= htmlspecialchars($translations['add_store']) ?></button>
        </div>
        <div class="settings-section" id="settings-section-table">
            <div class="form-row">
                <label><?= htmlspecialchars($translations['sort_by']) ?>:</label>
                <select id="sort-by">
                    <option value="date_asc" <?= $tableSettings['sort_by']==='date_asc'?'selected':'' ?>><?= htmlspecialchars($translations['date_asc']) ?></option>
                    <option value="date_desc" <?= $tableSettings['sort_by']==='date_desc'?'selected':'' ?>><?= htmlspecialchars($translations['date_desc']) ?></option>
                </select>
            </div>
            <div class="form-row">
                <label><?= htmlspecialchars($translations['rows_per_page']) ?>:</label>
                <input type="number" id="rows-per-page" min="1" value="<?= $tableSettings['rows_per_page'] ?>">
            </div>
            <div class="form-row">
                <label><?= htmlspecialchars($translations['start_date']) ?>:</label>
                <input type="date" id="start-date" value="<?= $tableSettings['start_date'] ?>">
            </div>
            <table class="store-visibility-table">
                <thead><th><?= htmlspecialchars($translations['store_name']) ?></th><th><?= htmlspecialchars($translations['visible']) ?></th></thead>
                <tbody>
                    <?php foreach ($userSettings['stores'] as $store): ?>
                        <tr>
                            <td><?= htmlspecialchars($store['name']) ?></td>
                            <td>
                                <button class="visibility-toggle" data-store-id="<?= $store['id'] ?>" data-visible="<?= $store['visible']?'true':'false' ?>" onclick="toggleStoreVisibility(this)">
                                    <?php if ($store['visible']): ?>
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                    <?php else: ?>
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27z"/></svg>
                                    <?php endif; ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="settings-section" id="settings-section-incomes">
            <div class="form-row">
                <label class="income-switch-row">
                    <span class="income-switch">
                        <input type="checkbox" id="show-incomes-toggle" <?= !empty($userSettings['show_incomes'])?'checked':'' ?>>
                        <span class="income-slider"></span>
                    </span>
                    <span><?= htmlspecialchars($translations['show_incomes'] ?? 'Show incomes in table and chart') ?></span>
                </label>
            </div>
        </div>
        <div class="settings-section" id="settings-section-delete">
            <p class="bulk-delete-intro"><?= htmlspecialchars($translations['bulk_delete_intro'] ?? '') ?></p>
            <div class="form-row">
                <label><?= htmlspecialchars($translations['bulk_delete_mode'] ?? 'Mode') ?>:</label>
                <select id="bulk-delete-mode">
                    <option value="range"><?= htmlspecialchars($translations['bulk_delete_mode_range'] ?? 'By period') ?></option>
                    <option value="all"><?= htmlspecialchars($translations['bulk_delete_mode_all'] ?? 'All data') ?></option>
                </select>
            </div>
            <div id="bulk-delete-range-fields">
                <div class="form-row">
                    <label><?= htmlspecialchars($translations['bulk_delete_date_from'] ?? 'From') ?>:</label>
                    <input type="date" id="bulk-delete-from">
                    <label><?= htmlspecialchars($translations['bulk_delete_date_to'] ?? 'To') ?>:</label>
                    <input type="date" id="bulk-delete-to">
                </div>
                <div class="form-row">
                    <label><?= htmlspecialchars($translations['store_name'] ?? 'Store') ?>:</label>
                    <select id="bulk-delete-store">
                        <option value="__all__"><?= htmlspecialchars($translations['bulk_delete_store_all'] ?? 'All stores') ?></option>
                        <?php foreach ($userSettings['stores'] as $st): ?>
                            <option value="<?= htmlspecialchars((string)$st['id']) ?>"><?= htmlspecialchars($st['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label><?= htmlspecialchars($translations['bulk_delete_scope'] ?? 'What to delete') ?>:</label>
                    <select id="bulk-delete-scope">
                        <option value="expenses"><?= htmlspecialchars($translations['bulk_delete_expenses_only'] ?? 'Expenses only') ?></option>
                        <option value="both"><?= htmlspecialchars($translations['bulk_delete_expenses_and_incomes'] ?? 'Expenses and incomes') ?></option>
                    </select>
                </div>
                <p class="bulk-delete-hint"><?= htmlspecialchars($translations['bulk_delete_income_hint'] ?? '') ?></p>
            </div>
            <div id="bulk-delete-all-fields" style="display:none;">
                <p class="bulk-delete-danger"><?= htmlspecialchars($translations['bulk_delete_all_warning'] ?? '') ?></p>
                <div class="form-row">
                    <label><?= htmlspecialchars($translations['bulk_delete_type_delete'] ?? 'Type DELETE') ?>:</label>
                    <input type="text" id="bulk-delete-confirm-all" autocomplete="off" placeholder="DELETE">
                </div>
            </div>
            <button type="button" class="confirm-btn" id="bulk-delete-run-btn" onclick="submitBulkDelete()"><?= htmlspecialchars($translations['bulk_delete_execute'] ?? 'Delete') ?></button>
        </div>
    </div>
</div>

<!-- Add/Edit Store Modal -->
<div id="add-edit-store-modal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeAddEditStoreModal()">×</button>
        <h3 id="add-edit-store-title"><?= htmlspecialchars($translations['add_store']) ?></h3>
        <div class="form-row">
            <label><?= htmlspecialchars($translations['store_name']) ?>:</label>
            <input type="text" id="store-name-input" placeholder="<?= htmlspecialchars($translations['store_name']) ?>">
        </div>
        <div class="form-row">
            <label><?= htmlspecialchars($translations['store_photo']) ?>:</label>
            <input type="file" id="store-photo-input" accept="image/*" onchange="previewStorePhoto(this)">
        </div>
        <img id="store-photo-preview" src="" alt="Preview" style="display:none;max-width:200px;max-height:200px;margin:10px 0;">
        <button id="crop-store-btn" onclick="cropStorePhoto()" style="display:none;margin:10px 0;padding:10px;background:var(--primary-color);color:white;border:none;cursor:pointer;border-radius:4px;"><?= htmlspecialchars($translations['crop_photo']) ?></button>
        <button onclick="saveStore()" style="margin-top:10px;padding:10px;background:var(--primary-color);color:white;border:none;cursor:pointer;border-radius:4px;"><?= htmlspecialchars($translations['save']) ?></button>
    </div>
</div>

<!-- Crop Modal -->
<div id="crop-modal" class="modal" style="z-index:2000;">
    <div class="modal-content">
        <button class="modal-close" onclick="closeCropModal()">×</button>
        <h3><?= htmlspecialchars($translations['crop_photo']) ?></h3>
        <div class="cropper-container"><img id="crop-image"></div>
        <button onclick="cropAndSave()" style="margin-top:10px;padding:10px;background:var(--primary-color);color:white;border:none;cursor:pointer;border-radius:4px;"><?= htmlspecialchars($translations['crop_and_save']) ?></button>
    </div>
</div>

<!-- Share Chart Modal -->
<div id="share-modal" class="modal">
    <div class="modal-content share-modal-content" style="max-width:600px;">
        <button class="modal-close" onclick="closeModal('share-modal')">×</button>
        <h3><?= htmlspecialchars($translations['share_chart']) ?></h3>
        <div class="share-chart-container">
            <canvas id="share-chart" width="500" height="300"></canvas>
        </div>
        <div class="share-actions">
            <button class="action-btn" onclick="downloadChartImage()" style="padding:10px 20px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" style="margin-right:8px;"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                <?= htmlspecialchars($translations['download_chart']) ?>
            </button>
            <button class="action-btn" onclick="copyChartToClipboard()" style="padding:10px 20px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" style="margin-right:8px;"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
                <?= htmlspecialchars($translations['copy_chart']) ?>
            </button>
        </div>
    </div>
</div>

<!-- Add Date Modal -->
<div id="add-date-modal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('add-date-modal')">×</button>
        <h3><?= htmlspecialchars($translations['add_date']) ?></h3>
        <div class="form-row">
            <label><?= htmlspecialchars($translations['select_date_label']) ?></label>
            <div class="date-picker-container">
                <div class="date-scroll">
                    <div class="number-input">
                        <button onclick="changeDateValue('year',-1)">-</button>
                        <input type="number" id="date-year" class="date-spinner" value="<?= date('Y') ?>" placeholder="<?= htmlspecialchars($translations['year_label']) ?>">
                        <button onclick="changeDateValue('year',1)">+</button>
                    </div>
                    <div class="number-input">
                        <button onclick="changeDateValue('month',-1)">-</button>
                        <input type="number" id="date-month" class="date-spinner" value="<?= date('m') ?>" placeholder="<?= htmlspecialchars($translations['month_label']) ?>">
                        <button onclick="changeDateValue('month',1)">+</button>
                    </div>
                    <div class="number-input">
                        <button onclick="changeDateValue('day',-1)">-</button>
                        <input type="number" id="date-day" class="date-spinner" value="<?= date('d') ?>" placeholder="<?= htmlspecialchars($translations['day_label']) ?>">
                        <button onclick="changeDateValue('day',1)">+</button>
                    </div>
                </div>
                <div class="scroll-hint"><?= htmlspecialchars($translations['date_format_hint']) ?></div>
            </div>
        </div>
        <button onclick="addNewDate()" style="margin-top:10px;padding:10px;background:var(--primary-color);color:white;border:none;cursor:pointer;border-radius:4px;"><?= htmlspecialchars($translations['add_date']) ?></button>
    </div>
</div>

<!-- Backup Modal -->
<div id="backup-modal" class="modal">
    <div class="modal-content" style="max-width:800px;">
        <button class="modal-close" onclick="closeModal('backup-modal')">×</button>
        <h3><?= htmlspecialchars($translations['backup_management']) ?></h3>
        <div class="form-row">
            <label><?= htmlspecialchars($translations['backup_name_label']) ?></label>
            <input type="text" id="backup-name" placeholder="<?= htmlspecialchars($translations['backup_name']) ?>">
            <button id="save-backup-btn" onclick="saveBackup()" style="margin:5px 0;padding:8px;background:var(--primary-color);color:white;border:none;cursor:pointer;border-radius:4px;"><?= htmlspecialchars($translations['save_backup']) ?></button>
        </div>
        <h4><?= htmlspecialchars($translations['upload_backup_file_title']) ?></h4>
        <div class="form-row">
            <input type="file" id="upload-backup-file" accept=".csv" style="margin:5px 0;">
            <button onclick="uploadBackup()" style="padding:8px;background:var(--primary-color);color:white;border:none;cursor:pointer;border-radius:4px;"><?= htmlspecialchars($translations['upload_backup_file']) ?></button>
        </div>
        <h4><?= htmlspecialchars($translations['existing_backups_for']) ?> <?= htmlspecialchars($displayName) ?></h4>
        <div id="backup-list" style="max-height:300px;overflow-y:auto;">
            <?php
            $userBackupDir = getUserBackupDir($currentUser);
            $backups = glob("$userBackupDir/*.csv") ?: [];
            foreach ($backups as $backup):
                $filename = basename($backup);
                $fileSize = round(filesize($backup)/1024, 2) . ' KB';
                $fileDate = date('Y-m-d H:i:s', filemtime($backup));
            ?>
                <div class="backup-item">
                    <div style="flex:1;margin-right:20px;">
                        <div style="margin-bottom:5px;"><strong><?= htmlspecialchars($filename) ?></strong></div>
                        <div style="font-size:12px;color:#666;"><?= $fileSize ?> • <?= $fileDate ?></div>
                    </div>
                    <div>
                        <button onclick="downloadBackup('<?= $filename ?>')" title="<?= htmlspecialchars($translations['download']) ?>" class="download-btn">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                        </button>
                        <button onclick="viewBackup('<?= $filename ?>')" title="<?= htmlspecialchars($translations['view']) ?>" class="view-btn">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                        </button>
                        <button onclick="restoreBackup('<?= $filename ?>')" title="<?= htmlspecialchars($translations['restore']) ?>" class="restore-btn">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M13 3c-4.97 0-9 4.03-9 9H1l4 4 4-4H6c0-3.86 3.14-7 7-7s7 3.14 7 7-3.14 7-7 7c-1.9 0-3.62-.76-4.88-1.99L6.7 18.42C8.32 20.01 10.55 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 12v-4h-2v4H8l4 4 4-4h-2z"/></svg>
                        </button>
                        <button onclick="deleteBackup('<?= $filename ?>')" title="<?= htmlspecialchars($translations['delete']) ?>" class="delete-btn">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- View Backup Modal -->
<div id="view-backup-modal" class="modal">
    <div class="modal-content" style="max-width:95%;max-height:95vh;width:auto;">
        <button class="modal-close" onclick="closeModal('view-backup-modal')">×</button>
        <h3 style="margin-top:0;color:var(--text-color);">Backup Preview</h3>
        <div id="backup-preview" style="overflow:auto;max-height:80vh;background:var(--bg-color);padding:10px;border-radius:5px;"></div>
    </div>
</div>

