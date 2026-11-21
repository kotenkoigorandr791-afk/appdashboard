    <script src="/project/sharepoint/assets/js/qr-code.js?v=5"></script>
    <script src="/project/sharepoint/share_i18n.php?lang=<?php echo htmlspecialchars($language ?? 'en', ENT_QUOTES); ?>"></script>
    <?php
    // The share endpoint verifies X-SharePoint-CSRF against the session token, so
    // the page has to publish it. Without this every create/list call answered
    // 403 csrf_invalid (the share dialog silently failed in the steps app).
    if (empty($_SESSION['sharepoint_csrf'])) $_SESSION['sharepoint_csrf'] = bin2hex(random_bytes(24));
    ?>
    <script>window.SHAREPOINT_CSRF=<?= json_encode($_SESSION['sharepoint_csrf']) ?>;</script>
    <script src="/project/sharepoint/assets/js/share-modal.js?v=13"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var shareButton = document.getElementById('sharepoint-share-button');
            var chooser = document.getElementById('share-chooser');
            if (shareButton && chooser) {
                shareButton.addEventListener('click', function (event) {
                    event.stopPropagation();
                    chooser.hidden = !chooser.hidden;
                });
                document.addEventListener('click', function (event) {
                    if (!chooser.contains(event.target) && event.target !== shareButton) chooser.hidden = true;
                });
                document.getElementById('share-choose-steps')?.addEventListener('click', function () {
                    chooser.hidden = true;
                    if (!window.openShareModal) return;
                    window.openShareModal('steps', 'app', 'step_routine', {
                        title: <?php echo json_encode($translations['steps_counter_title'] ?? 'My steps', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>,
                        defaultPermissions: ['view', 'interact'],
                        apiUrl: '/apps/steps/api/share.php'
                    });
                });
                document.getElementById('share-choose-location')?.addEventListener('click', function () {
                    chooser.hidden = true;
                    openModal('share-location-modal');
                    window.dispatchEvent(new CustomEvent('steps-share-location-open'));
                });
            }
        });
        let stepsData = <?php echo json_encode($stepsData, JSON_HEX_TAG); ?>,
            healthData = <?php echo json_encode($healthMetrics ?? [], JSON_HEX_TAG); ?>,
            userSettings = <?php echo json_encode($userSettings, JSON_HEX_TAG); ?>,
            currentUser = '<?php echo htmlspecialchars($currentUser); ?>',
            translations = <?php echo json_encode($translations, JSON_HEX_TAG); ?>;

        const localeMap = { 'en': 'en-EN', 'ru': 'ru-RU', 'uk': 'uk-UA', 'be': 'be-BY' },
              currentLocale = localeMap['<?php echo $language; ?>'] || 'en-EN';

        const chartTypeStorageKey = () => 'stepsChartType_' + String(currentUser || 'u').replace(/[^a-z0-9_\-]/gi, '_');
        let currentView = 'week', currentDataType = 'steps', currentDate = new Date(), chart = null;
        let yearlyDistributionChart = null, monthlyIntensityChart = null;
        let currentChartType = 'bar';
        let stepsDataVersion = '';
        let stepsDataRefreshTimer = null;
        let stepsDataRefreshBusy = false;
        try { const _ct = localStorage.getItem(chartTypeStorageKey()); if (_ct === 'bar' || _ct === 'line') currentChartType = _ct; } catch (e) {}
        let selectedCells = new Set();
        let editingCell = null;
        let currentRow = 0;
        let currentCol = 0;
        let isEditing = false;
        (() => {
            const selector = '.app-steps button, .excel-btn, .save-button, .alert-btn, .modal-close, .data-type-btn, .view-btn, .chart-type-btn, .settings-tab, .add-tab, .page-btn';
            const pressedSelector = selector.split(',').map(part => part.trim() + '.is-pressed').join(',');
            const setPressed = (target, pressed) => {
                const button = target && target.closest ? target.closest(selector) : null;
                if (!button || button.disabled) return;
                button.classList.toggle('is-pressed', pressed);
            };
            const clearPressed = () => document.querySelectorAll(pressedSelector).forEach(button => button.classList.remove('is-pressed'));
            document.addEventListener('pointerdown', event => {
                if (event.pointerType === 'mouse' && event.button !== 0) return;
                setPressed(event.target, true);
            }, true);
            document.addEventListener('pointerup', clearPressed, true);
            document.addEventListener('pointercancel', clearPressed, true);
            document.addEventListener('dragstart', clearPressed, true);
            window.addEventListener('blur', clearPressed);
        })();


        // Helper functions
        const formatNumber = num => num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        const formatLocalYMD = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        const localDateFromYMD = s => { const p = String(s).split('-'); if (p.length !== 3) return new Date(NaN); const y = +p[0], m = +p[1], da = +p[2]; if (!y || !m || !da) return new Date(NaN); return new Date(y, m - 1, da); };
        const parseStepsRaw = s => String(s).replace(/\s/g, '');
        const setStepsInputValue = n => { const el = document.getElementById('steps-input'); if (el) { const v = Number(n) || 0; el.value = v > 0 ? formatNumber(v) : ''; } };

        const formatDate = (date, mode = 'day', lang = currentLocale) =>
            mode === 'month' ? date.toLocaleDateString(lang, { month: 'long' }) :
            mode === 'day' ? date.toLocaleDateString(lang, { day: '2-digit', month: 'short' }) :
            date.toLocaleDateString(lang, { year: 'numeric' });
        const mondayWeekOffset = d => (d.getDay() + 6) % 7;
        const getChartLabel = (view, date, lang = currentLocale) => {
    if (view === 'year') {
        return date.toLocaleDateString(lang, { year: 'numeric' });
    } else if (view === 'month') {
        return date.toLocaleDateString(lang, { month: 'long', year: 'numeric' });
    } else if (view === 'week') {
        const weekStart = new Date(date);
        weekStart.setDate(date.getDate() - mondayWeekOffset(date));
        const weekEnd = new Date(weekStart);
        weekEnd.setDate(weekStart.getDate() + 6);
        return `${formatDate(weekStart, 'day', lang)} - ${formatDate(weekEnd, 'day', lang)}`;
    }
    return '';
};

        const getAvailableYears = () =>
            Array.from(new Set(stepsData.map(entry => localDateFromYMD(entry.date).getFullYear())))
                .sort((a, b) => a - b);

        const getAvailableMonths = (lang = currentLocale) =>
            Array.from(new Set(stepsData.map(entry => { const x = localDateFromYMD(entry.date); return x.getFullYear() + '-' + (x.getMonth() + 1); })))
                .sort()
                .map(month => ({
                    value: month,
                    label: new Date(month + '-01').toLocaleDateString(lang, { month: 'long', year: 'numeric' })
                }));

        const getAvailableMonthsForYear = (year, lang = currentLocale) =>
            Array.from(new Set(stepsData
                .filter(entry => localDateFromYMD(entry.date).getFullYear() === parseInt(year))
                .map(entry => localDateFromYMD(entry.date).getMonth() + 1)))
                .sort((a, b) => a - b)
                .map(m => ({
                    value: `${year}-${m}`,
                    label: new Date(`${year}-${m}-01`).toLocaleDateString(lang, { month: 'long' })
                }));

        const hasDataForYear = (year) =>
            stepsData.some(entry => localDateFromYMD(entry.date).getFullYear() === year);

        const hasDataForMonth = (year, month) =>
            stepsData.some(entry => {
                const d = localDateFromYMD(entry.date);
                return d.getFullYear() === year && d.getMonth() === month;
            });

        const hasDataForWeek = (date) => {
            const start = new Date(date);
            start.setDate(date.getDate() - mondayWeekOffset(date));
            const end = new Date(start);
            end.setDate(start.getDate() + 6);
            return stepsData.some(entry => {
                const d = localDateFromYMD(entry.date);
                return d >= start && d <= end;
            });
        };

        const getMinDate = () => {
            let min = null;
            for (const entry of stepsData) {
                const d = localDateFromYMD(entry.date);
                if (!isNaN(d.getTime()) && (!min || d < min)) min = d;
            }
            return min;
        };

        const getMaxDate = () => {
            let max = null;
            for (const entry of stepsData) {
                const d = localDateFromYMD(entry.date);
                if (!isNaN(d.getTime()) && (!max || d > max)) max = d;
            }
            return max;
        };

        const canNavigateForward = () => {
            const max = getMaxDate();
            if (!max) return false;
            if (currentView === 'year') return currentDate.getFullYear() < max.getFullYear();
            if (currentView === 'month') {
                const ym = currentDate.getFullYear() * 12 + currentDate.getMonth();
                const ymMax = max.getFullYear() * 12 + max.getMonth();
                return ym < ymMax;
            }
            if (currentView === 'week') {
                const weekStart = new Date(currentDate);
                weekStart.setDate(currentDate.getDate() - mondayWeekOffset(currentDate));
                return weekStart < max;
            }
            return true;
        };

        const canNavigateBackward = () => {
            const min = getMinDate();
            if (!min) return false;
            if (currentView === 'year') return currentDate.getFullYear() > min.getFullYear();
            if (currentView === 'month') {
                const ym = currentDate.getFullYear() * 12 + currentDate.getMonth();
                const ymMin = min.getFullYear() * 12 + min.getMonth();
                return ym > ymMin;
            }
            if (currentView === 'week') {
                const weekStart = new Date(currentDate);
                weekStart.setDate(currentDate.getDate() - mondayWeekOffset(currentDate));
                return weekStart > min;
            }
            return true;
        };

        const healthMap = {};
        (healthData || []).forEach(e => { if (e && e.date) healthMap[e.date] = e.metrics || {}; });
        const isSeriesType = dt => dt === 'heart_rate' || dt === 'weight';
        const unitLabelFor = dt => dt === 'steps' ? (translations.steps || 'Steps') :
            dt === 'distance' ? (translations.distance_km || 'km') :
            dt === 'calories' ? (translations.calories || 'kcal') :
            dt === 'heart_rate' ? (translations.heart_rate || 'Heart rate (bpm)') :
            dt === 'sleep' ? (translations.sleep || 'Sleep (hours)') :
            dt === 'weight' ? (translations.weight || 'Weight (kg)') : dt;
        const hcDayValue = (dateStr, dataType) => {
            const m = healthMap[dateStr];
            if (!m) return null;
            if (dataType === 'steps') return typeof m.steps?.total === 'number' ? m.steps.total : null;
            if (dataType === 'distance' && m.distance) return typeof m.distance.total_meters === 'number' ? m.distance.total_meters / 1000 : null;
            if (dataType === 'calories') {
                const c = m.total_calories || m.active_calories;
                return c && typeof c.total === 'number' ? c.total : null;
            }
            if (dataType === 'heart_rate' && m.heart_rate) return typeof m.heart_rate.avg === 'number' ? m.heart_rate.avg : (m.heart_rate.last ?? null);
            if (dataType === 'sleep' && m.sleep) return typeof m.sleep.total_seconds === 'number' ? m.sleep.total_seconds / 3600 : null;
            if (dataType === 'weight' && m.weight) return typeof m.weight.last === 'number' ? m.weight.last : (typeof m.weight.avg === 'number' ? m.weight.avg : null);
            return null;
        };
        const stepsEntryFor = dateStr => stepsData.find(entry => entry.date === dateStr);
        const dayValue = (dateStr, dataType) => {
            if (dataType === 'steps') { const e = stepsEntryFor(dateStr); return e ? e.steps : 0; }
            const real = hcDayValue(dateStr, dataType);
            if (real !== null) return real;
            const steps = (stepsEntryFor(dateStr) || {}).steps || 0;
            if (dataType === 'distance') return (steps * userSettings.stride_length) / 100000;
            if (dataType === 'calories') return steps * 0.04 * (userSettings.weight / 70);
            return null;
        };
        const avgOf = vals => { const v = vals.filter(x => x !== null && Number.isFinite(x)); return v.length ? v.reduce((a, b) => a + b, 0) / v.length : 0; };

        const hasRealMetricForDate = (dateStr, dataType) => {
            const m = healthMap[dateStr] || {};
            if (dataType === 'distance') return !!(m.distance && Number.isFinite(m.distance.total_meters) && m.distance.total_meters > 0);
            if (dataType === 'calories') {
                const c = m.total_calories || m.active_calories;
                return !!(c && Number.isFinite(c.total) && c.total > 0);
            }
            if (dataType === 'heart_rate') {
                const hr = m.heart_rate;
                const v = hr ? (Number.isFinite(hr.avg) ? hr.avg : (Number.isFinite(hr.last) ? hr.last : null)) : null;
                return v !== null && v > 0;
            }
            if (dataType === 'sleep') return !!(m.sleep && Number.isFinite(m.sleep.total_seconds) && m.sleep.total_seconds > 0);
            if (dataType === 'weight') {
                const w = m.weight;
                const v = w ? (Number.isFinite(w.last) ? w.last : (Number.isFinite(w.avg) ? w.avg : null)) : null;
                return v !== null && v > 0;
            }
            return true;
        };

        // Hide optional metric buttons when there is no corresponding data.
        // Steps / Distance / Calories stay visible (distance & calories can be derived).
        const hideEmptyDataTypeButtons = () => {
            const candidates = ['heart_rate', 'sleep', 'weight'];
            const hasAny = (dt) => stepsData.some(entry => hasRealMetricForDate(entry.date, dt));

            const stepsBtn = document.querySelector('.data-type-btn[data-type="steps"]');
            const ensureActive = () => {
                document.querySelectorAll('.data-type-btn').forEach(b => b.classList.remove('active'));
                if (currentDataType === 'steps' && stepsBtn) stepsBtn.classList.add('active');
            };

            ['steps', 'distance', 'calories'].forEach(dt => {
                const btn = document.querySelector(`.data-type-btn[data-type="${dt}"]`);
                if (btn) btn.style.display = '';
            });

            candidates.forEach(dt => {
                const btn = document.querySelector(`.data-type-btn[data-type="${dt}"]`);
                if (!btn) return;
                if (!hasAny(dt)) {
                    btn.style.display = 'none';
                    if (currentDataType === dt) {
                        currentDataType = 'steps';
                        ensureActive();
                        renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                    }
                }
            });
        };

        // Function to show toast notification
        function showToast(message, type = 'info') {
            const container = document.getElementById('notifications');
            if (!container) return;

            const kind = ['success', 'error', 'info'].includes(type) ? type : 'info';
            const iconClass = kind === 'success' ? 'fa-check-circle' : kind === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
            const titleText = kind === 'success' ? (translations.toast_success || 'Success') : kind === 'error' ? (translations.toast_error || 'Error') : (translations.toast_info || 'Info');

            const toast = document.createElement('div');
            toast.className = 'notification toast-notification ' + kind;

            const header = document.createElement('div');
            header.className = 'notification-header';

            const title = document.createElement('div');
            title.className = 'notification-title';
            const icon = document.createElement('i');
            icon.className = 'fas ' + iconClass;
            title.appendChild(icon);
            title.appendChild(document.createTextNode(' ' + titleText));

            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'close-notification';
            closeBtn.setAttribute('aria-label', titleText);
            closeBtn.textContent = '×';

            header.appendChild(title);
            header.appendChild(closeBtn);

            const content = document.createElement('div');
            content.className = 'notification-content';
            content.textContent = message;

            toast.appendChild(header);
            toast.appendChild(content);
            container.appendChild(toast);

            const dismiss = () => {
                if (!toast.parentNode) return;
                toast.style.animation = 'toastOut 0.3s ease-in';
                toast.addEventListener('animationend', () => toast.remove(), { once: true });
            };
            closeBtn.addEventListener('click', dismiss);
            setTimeout(dismiss, 4000);
        }

        // Function to show alert
        const ALERT_ICON_WARNING = '<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>';
        const ALERT_ICON_INFO = '<i class="fa-solid fa-circle-info" aria-hidden="true"></i>';

        function showAlert(message, isConfirm = false, options = {}) {
            return new Promise((resolve) => {
                const modal = document.getElementById('alert-modal');
                const messageEl = document.getElementById('alert-message');
                const titleEl = document.getElementById('alert-title');
                const subtitleEl = document.getElementById('alert-subtitle');
                const headerEl = document.getElementById('alert-header');
                const boxEl = document.getElementById('alert-box');
                const iconEl = document.getElementById('alert-icon');
                const okBtn = document.getElementById('alert-ok');
                const extraBtn = document.getElementById('alert-extra');
                const cancelBtn = document.getElementById('alert-cancel');
                const okText = options.okText || 'OK';
                const cancelText = options.cancelText || (translations.cancel || 'Cancel');
                const extraText = options.extraText || '';
                const variant = options.variant || (isConfirm ? 'warning' : 'info');
                messageEl.textContent = message;
                okBtn.textContent = okText;
                cancelBtn.textContent = cancelText;
                extraBtn.textContent = extraText;
                cancelBtn.style.display = isConfirm ? 'block' : 'none';
                extraBtn.style.display = isConfirm && extraText ? 'block' : 'none';

                titleEl.textContent = options.title || '';
                subtitleEl.textContent = options.subtitle || '';
                headerEl.classList.toggle('is-empty', !options.title && !options.subtitle);
                boxEl.className = 'alert-box' + (variant ? ' ' + variant : '');
                iconEl.innerHTML = options.icon || ((variant === 'danger' || variant === 'warning') ? ALERT_ICON_WARNING : ALERT_ICON_INFO);
                okBtn.className = 'alert-btn ok' + (variant !== 'info' ? ' ' + variant : '');

                const openModal = () => {
                    document.body.classList.add('modal-open');
                    modal.style.display = 'flex';
                    setTimeout(() => okBtn.focus(), 0);
                };

                const closeModal = (result) => {
                    modal.removeEventListener('keydown', handleKeyDown, true);
                    document.body.classList.remove('modal-open');
                    modal.style.display = 'none';
                    resolve(result);
                };
                const handleKeyDown = (e) => {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        e.stopPropagation();
                        closeModal(false);
                        return;
                    }
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        e.stopPropagation();
                        if (isConfirm) closeModal(true);
                    }
                };

                okBtn.onclick = () => closeModal(true);
                cancelBtn.onclick = () => closeModal(false);
                extraBtn.onclick = () => closeModal('extra');

                modal.querySelector('.alert-close').onclick = () => closeModal(false);
                modal.addEventListener('keydown', handleKeyDown, true);

                openModal();
            });
        }

        // Handle modal windows
        function syncChartTypeButtons() {
            document.querySelectorAll('.chart-type-btn').forEach(b => b.classList.toggle('active', b.getAttribute('data-type') === currentChartType));
            renderChartPreviews();
        }
        async function renderChartPreviews() {
            try { await window.ensureStepsCharts(); } catch (e) { return; }
            if (!window.Chart) return;
            const canvasBar = document.getElementById('settings-chart-preview-bar');
            const canvasLine = document.getElementById('settings-chart-preview-line');
            if (!canvasBar || !canvasLine) return;

            if (chartPreviewBarInstance) {
                chartPreviewBarInstance.destroy();
                chartPreviewBarInstance = null;
            }
            if (chartPreviewLineInstance) {
                chartPreviewLineInstance.destroy();
                chartPreviewLineInstance = null;
            }

            const isDarkTheme = '<?php echo $theme === 'dark' ? 'true' : 'false'; ?>' === 'true';
            const chartGridColor = isDarkTheme ? 'rgba(160, 160, 160, 0.45)' : 'rgba(0, 0, 0, 0.12)';
            const sampleData = [3200, 5800, 4100, 7200, 6500, 8900, 5300];
            const sampleLabels = ['<?php echo htmlspecialchars($translations['day_mon'] ?? 'Mon'); ?>', '<?php echo htmlspecialchars($translations['day_tue'] ?? 'Tue'); ?>', '<?php echo htmlspecialchars($translations['day_wed'] ?? 'Wed'); ?>', '<?php echo htmlspecialchars($translations['day_thu'] ?? 'Thu'); ?>', '<?php echo htmlspecialchars($translations['day_fri'] ?? 'Fri'); ?>', '<?php echo htmlspecialchars($translations['day_sat'] ?? 'Sat'); ?>', '<?php echo htmlspecialchars($translations['day_sun'] ?? 'Sun'); ?>'];

            const createPreviewGradient = (canvas) => {
                const ctx = canvas.getContext('2d');
                const gradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
                const r = 0, g = 123, b = 255;
                const alpha = 0.5;
                const halfWhite = `rgba(${Math.round((255 + r) / 2)},${Math.round((255 + g) / 2)},${Math.round((255 + b) / 2)},${alpha})`;
                const full = `rgba(${r},${g},${b},${alpha})`;
                gradient.addColorStop(0, halfWhite);
                gradient.addColorStop(1, full);
                return gradient;
            };

            const commonOptions = {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: 8 },
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: true }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: chartGridColor },
                        border: { color: chartGridColor },
                        ticks: {
                            color: isDarkTheme ? '#fff' : '#000',
                            callback: (v) => formatNumber(v)
                        }
                    },
                    x: {
                        grid: { color: chartGridColor },
                        border: { color: chartGridColor },
                        ticks: { color: isDarkTheme ? '#fff' : '#000' }
                    }
                }
            };

            chartPreviewBarInstance = new Chart(canvasBar.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: sampleLabels,
                    datasets: [{
                        label: '<?php echo htmlspecialchars($translations['steps']); ?>',
                        data: sampleData,
                        backgroundColor: createPreviewGradient(canvasBar),
                        borderColor: 'rgba(0,123,255,1)',
                        borderWidth: 1
                    }]
                },
                options: commonOptions
            });

            chartPreviewLineInstance = new Chart(canvasLine.getContext('2d'), {
                type: 'line',
                data: {
                    labels: sampleLabels,
                    datasets: [{
                        label: '<?php echo htmlspecialchars($translations['steps']); ?>',
                        data: sampleData,
                        backgroundColor: 'transparent',
                        borderColor: 'rgba(0,123,255,1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: commonOptions
            });
        }
        let chartPreviewBarInstance = null;
        let chartPreviewLineInstance = null;
        function resetSettingsModalTabs() {
            document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.settings-tab-panel').forEach(p => p.classList.remove('active'));
            const t0 = document.querySelector('.settings-tab[data-settings-tab="profile"]'), p0 = document.getElementById('settings-panel-profile');
            if (t0) t0.classList.add('active');
            if (p0) p0.classList.add('active');
        }
        function resetAddModalTabs() {
            document.querySelectorAll('.add-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.add-tab-panel').forEach(p => p.classList.remove('active'));
            const t0 = document.querySelector('.add-tab[data-add-tab="manual"]'), p0 = document.getElementById('add-panel-manual');
            if (t0) t0.classList.add('active');
            if (p0) p0.classList.add('active');
        }
        function switchAddTab(id) {
            document.querySelectorAll('.add-tab').forEach(t => t.classList.toggle('active', t.getAttribute('data-add-tab') === id));
            document.querySelectorAll('.add-tab-panel').forEach(p => p.classList.toggle('active', p.id === 'add-panel-' + id));
            if (id === 'video' && typeof zlResumeFromAccountState === 'function') zlResumeFromAccountState();
            if (id === 'takeout' && typeof ftResumeFromAccountState === 'function') ftResumeFromAccountState();
        }
        function openModal(modalId, opts) {
            opts = opts || {};
            const modal = document.getElementById(modalId);
            if (modal) {
                if (modal.firstElementChild) modal.firstElementChild.classList.add('modal-content');
                document.body.classList.add('modal-open');
                modal.style.removeProperty('pointer-events');
                modal.style.removeProperty('visibility');
                modal.style.setProperty('display', 'flex', 'important');
                document.querySelectorAll('.topbar-icon-btn.is-active').forEach(btn => btn.classList.remove('is-active'));
                if (modalId === 'settings-modal') {
                    resetSettingsModalTabs();
                    syncChartTypeButtons();
                }
                if (modalId === 'add-steps-modal') {
                    if (opts.addTab) switchAddTab(opts.addTab);
                    else resetAddModalTabs();
                    updateAdjacentDates(document.getElementById('selected-date-input').value, currentLocale);
                }
                if (modalId === 'video-confirm-modal' && !document.hidden) {
                    const vn = document.getElementById('video-recognition-notification');
                    if (vn) vn.style.display = 'none';
                }
                if (modalId === 'stats-modal') {
                    setTimeout(openStatisticsExperience, 0);
                }
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeModal(modalId);
                    }
                });
            }
        }

        function refreshBodyModalLock() {
            const ids = ['info-modal','stats-modal','settings-modal','add-steps-modal','day-steps-modal','user-modal','add-user-modal','video-confirm-modal','video-confirm-gaps-modal','alert-modal','video-crop-modal','guide-expand-modal','mobile-app-modal','global-image-viewer'];
            const any = ids.some(id => {
                const m = document.getElementById(id);
                if (!m) return false;
                const cs = window.getComputedStyle(m);
                const isVisible = cs.display !== 'none' && cs.visibility !== 'hidden' && cs.opacity !== '0';
                return isVisible;
            });
            if (any) document.body.classList.add('modal-open');
            else document.body.classList.remove('modal-open');
        }
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.setProperty('display', 'none', 'important');
                modal.style.removeProperty('pointer-events');
                modal.style.removeProperty('visibility');
                document.querySelectorAll('.topbar-icon-btn.is-active').forEach(btn => btn.classList.remove('is-active'));
                try {
                    const url = new URL(window.location.href);
                    if (modalId === 'settings-modal' && url.searchParams.has('settings')) {
                        url.searchParams.delete('settings');
                        history.replaceState(null, '', url);
                    }
                } catch (e) {}
                if (modalId === 'add-steps-modal') {
                    if (typeof zlStopPoll === 'function') zlStopPoll();
                    if (typeof ftStopPoll === 'function') ftStopPoll();
                }
                if (modalId === 'video-confirm-modal') {
                    try {
                        if (!pendingVideoRows || pendingVideoRows.length === 0) {
                            runVideoCleanup();
                        }
                    } catch (eCu) {}
                    if (typeof destroyVideoConfirmChart === 'function') destroyVideoConfirmChart();
                    if (typeof closeVideoGapsModal === 'function') closeVideoGapsModal();
                }
                if (modalId === 'guide-expand-modal') {
                    const gf = document.getElementById('guide-expand-frame');
                    if (gf) gf.src = 'about:blank';
                }
                if (modalId === 'stats-modal') {
                    destroyStatisticsCharts();
                    const statsButton = document.getElementById('stats-button');
                    if (statsButton) statsButton.classList.remove('active');
                }
                refreshBodyModalLock();
            }
        }

        // Functions for chart operations
        function prepareChartData(view, selectedDate, dataType, lang = currentLocale) {
    let labels = [], data = [], dates = [];
    const series = isSeriesType(dataType);

    if (view === 'year') {
        const year = selectedDate.getFullYear();
        const monthVals = {};
        for (let month = 0; month < 12; month++) monthVals[month] = [];

        stepsData.forEach(entry => {
            const date = localDateFromYMD(entry.date);
            if (date.getFullYear() !== year) return;
            const v = dayValue(entry.date, dataType);
            if (v !== null) monthVals[date.getMonth()].push(v);
        });

        for (let month = 0; month < 12; month++) {
            labels.push(new Date(year, month).toLocaleDateString(lang, { month: 'short' }));
            dates.push(`${year}-${String(month + 1).padStart(2, '0')}-01`);
            data.push(series ? avgOf(monthVals[month]) : monthVals[month].reduce((a, b) => a + b, 0));
        }

    } else if (view === 'month') {
        stepsData.forEach(entry => {
            const date = localDateFromYMD(entry.date);
            if (date.getMonth() !== selectedDate.getMonth() ||
                date.getFullYear() !== selectedDate.getFullYear()) return;
            const v = dayValue(entry.date, dataType);
            labels.push(formatDate(date, 'day', lang));
            data.push(v === null ? 0 : v);
            dates.push(entry.date);
        });

    } else if (view === 'week') {
        const weekStart = new Date(selectedDate);
        weekStart.setDate(selectedDate.getDate() - mondayWeekOffset(selectedDate));
        for (let i = 0; i < 7; i++) {
            const currentDay = new Date(weekStart);
            currentDay.setDate(weekStart.getDate() + i);
            const dateStr = formatLocalYMD(currentDay);
            const v = dayValue(dateStr, dataType);
            labels.push(formatDate(currentDay, 'day', lang));
            data.push(v === null ? 0 : v);
            dates.push(dateStr);
        }
    }

    return { labels, data, dates };
}

        function createBarGradient(canvas, alpha = 1) {
            const ctx = canvas.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, canvas.height || 300);
            gradient.addColorStop(0, `rgba(76, 154, 255, ${alpha})`);
            gradient.addColorStop(1, `rgba(37, 99, 235, ${alpha})`);
            return gradient;
        }

        async function renderChart(view, selectedDate, dataType, chartType, lang = currentLocale) {
            try { await window.ensureStepsCharts(); } catch (e) { return; }
            if (!window.Chart) return;
            if (chart) { chart.destroy(); chart = null; }

            const chartData = prepareChartData(view, selectedDate, dataType, lang);
            if (!chartData.data.length) return;

            const chartLabelText = getChartLabel(view, selectedDate, lang);
            document.getElementById('chart-label').textContent = chartLabelText;
            const rangeLabel = document.getElementById('chart-range-label');
            if (rangeLabel) rangeLabel.textContent = chartLabelText;
            document.getElementById('chart-nav-left').style.display = 'inline-flex';
            document.getElementById('chart-nav-right').style.display = 'inline-flex';
            document.getElementById('chart-nav-left').disabled = !canNavigateBackward();
            document.getElementById('chart-nav-right').disabled = !canNavigateForward();

            const unitLabel = unitLabelFor(dataType);
            const isDarkTheme = true;
            const chartGridColor = 'rgba(255, 255, 255, 0.07)';
            const axisColor = '#71757D';
            const seriesType = isSeriesType(dataType);
            const hideChartTitles = true;
            const fmtAxis = v => seriesType ? String(Math.round(v * 10) / 10) : formatNumber(Math.round(v));
            const fmtTooltip = v => seriesType ? String(Math.round(v * 10) / 10) : formatNumber(Math.round(v));

            if (!Chart.registry.plugins.get('annotation')) {
                Chart.register(ChartAnnotation);
            }
            const goalSteps = userSettings.goal_steps;
            const dataMax = chartData.data.length ? Math.max.apply(null, chartData.data) : 0;
            const yMaxWhenBelowGoal = view !== 'year' && dataType === 'steps' && dataMax < goalSteps
                ? Math.round(goalSteps * 1.2)
                : undefined;
            const barBg = chartType === 'bar' ? createBarGradient(document.getElementById('steps-chart')) : 'transparent';
            chart = new Chart(document.getElementById('steps-chart').getContext('2d'), {
                type: chartType,
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: unitLabel,
                        data: chartData.data,
                        backgroundColor: barBg,
                        borderColor: '#4C9AFF',
                        borderWidth: chartType === 'bar' ? 0 : 2,
                        borderRadius: chartType === 'bar' ? 8 : 0,
                        borderSkipped: false,
                        fill: chartType === 'line',
                        tension: chartType === 'line' ? 0.4 : 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 8, right: 8, bottom: 4, left: 4 } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ...(yMaxWhenBelowGoal != null ? { max: yMaxWhenBelowGoal } : {}),
                            grid: { color: chartGridColor },
                            border: { color: '#34363B' },
                            ticks: {
                                display: true,
                                color: axisColor,
                                font: { family: 'JetBrains Mono', size: 11 },
                                callback: (v) => fmtAxis(v)
                            },
                            title: {
                                display: !hideChartTitles,
                                text: unitLabel,
                                color: axisColor
                            }
                        },
                        x: {
                            grid: { display: false },
                            border: { color: '#34363B' },
                            ticks: { display: true, color: axisColor, font: { family: 'JetBrains Mono', size: 11 } },
                            title: {
                                display: !hideChartTitles,
                                text: translations.time || 'Time',
                                color: axisColor
                            }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1C1D1F',
                            titleColor: '#fff',
                            bodyColor: '#E3E5E8',
                            borderColor: '#34363B',
                            borderWidth: 1,
                            callbacks: {
                                label: context => `${unitLabel}: ${fmtTooltip(context.raw)}`
                            }
                        },
                        zoom: {
                            pan: { enabled: true, mode: 'x' },
                            zoom: {
                                wheel: { enabled: true },
                                pinch: { enabled: true },
                                mode: 'x'
                            }
                        },
                        ...(view !== 'year' && dataType === 'steps' ? {
                            annotation: {
                                annotations: {
                                    goalLine: {
                                        type: 'line',
                                        yMin: userSettings.goal_steps,
                                        yMax: userSettings.goal_steps,
                                        borderColor: 'rgba(52, 211, 153, 0.55)',
                                        borderWidth: 2,
                                        borderDash: [6, 6],
                                        label: {
                                            display: true,
                                            content: (translations.goal_label || 'Goal: ') + formatNumber(userSettings.goal_steps),
                                            position: 'end',
                                            yAdjust: -18,
                                            backgroundColor: 'rgba(6, 78, 59, 0.9)',
                                            color: '#34d399',
                                            borderColor: 'rgba(52, 211, 153, 0.35)',
                                            borderWidth: 1,
                                            font: { size: 10, family: 'JetBrains Mono' }
                                        }
                                    }
                                }
                            }
                        } : {})
                    }
                }
            });
        }

        let statsNotifRevealTimer = null, goalNotifRevealTimer = null, statsNotifRevealDone = false, goalNotifRevealDone = false;
        function panelNotifUserKey(kind) {
            return 'stepsPanelN_' + kind + '_' + String(currentUser || 'u').replace(/[^a-z0-9_\-]/gi, '_');
        }
        function getPanelNotifSnoozeUntil(kind) {
            try { return parseInt(localStorage.getItem(panelNotifUserKey(kind)) || '0', 10); } catch (e) { return 0; }
        }
        function snoozePanelNotif12h(kind) {
            try { localStorage.setItem(panelNotifUserKey(kind), String(Date.now() + 12 * 60 * 60 * 1000)); } catch (e) {}
        }
        function cancelStatsNotifTimer() {
            if (statsNotifRevealTimer) { clearTimeout(statsNotifRevealTimer); statsNotifRevealTimer = null; }
        }
        function cancelGoalNotifTimer() {
            if (goalNotifRevealTimer) { clearTimeout(goalNotifRevealTimer); goalNotifRevealTimer = null; }
        }
        function ensureStatsNotifScheduled() {
            const el = document.getElementById('stats-notification');
            if (!el) return;
            if (Date.now() < getPanelNotifSnoozeUntil('stats')) return;
            if (statsNotifRevealDone || statsNotifRevealTimer !== null) return;
            statsNotifRevealTimer = setTimeout(function() {
                statsNotifRevealTimer = null;
                statsNotifRevealDone = true;
                if (Date.now() >= getPanelNotifSnoozeUntil('stats')) el.style.display = 'block';
            }, Math.floor(Math.random() * 60000));
        }
        function ensureGoalNotifScheduled() {
            const el = document.getElementById('goal-notification');
            if (!el) return;
            if (Date.now() < getPanelNotifSnoozeUntil('goal')) return;
            if (goalNotifRevealDone || goalNotifRevealTimer !== null) return;
            goalNotifRevealTimer = setTimeout(function() {
                goalNotifRevealTimer = null;
                goalNotifRevealDone = true;
                if (Date.now() >= getPanelNotifSnoozeUntil('goal')) el.style.display = 'block';
            }, Math.floor(Math.random() * 60000));
        }
        // Informational pop-ups were removed on purpose: notifications are now
        // reserved for real errors and things the user must act on. The stats and
        // goal summaries live in the Statistics experience instead.
        function updateStatsNotification() {}
        function updateGoalNotification() {}

        function destroyStatisticsCharts() {
            if (yearlyDistributionChart) { yearlyDistributionChart.destroy(); yearlyDistributionChart = null; }
            if (monthlyIntensityChart) { monthlyIntensityChart.destroy(); monthlyIntensityChart = null; }
        }
        async function renderStatisticsCharts(lang = currentLocale) {
            try { await window.ensureStepsCharts(); } catch (e) { return; }
            if (!window.Chart) return;
            destroyStatisticsCharts();
            const empty = document.getElementById('stats-empty');
            const grid = document.querySelector('#stats-modal .stats-grid');
            if (!stepsData.length) {
                if (empty) empty.style.display = 'block';
                if (grid) grid.style.display = 'none';
                return;
            }
            if (empty) empty.style.display = 'none';
            if (grid) grid.style.display = 'grid';
            const isDarkTheme = '<?php echo $theme === 'dark' ? 'true' : 'false'; ?>' === 'true';
            const chartGridColor = isDarkTheme ? 'rgba(160, 160, 160, 0.45)' : 'rgba(0, 0, 0, 0.12)';
            const chartTextColor = isDarkTheme ? '#fff' : '#000';
            const palette = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6610f2', '#20c997', '#fd7e14', '#6f42c1', '#17a2b8', '#e83e8c', '#6c757d', '#198754'];
            const yearlyTotals = new Map();
            stepsData.forEach(entry => {
                const d = localDateFromYMD(entry.date);
                if (!isNaN(d.getTime())) yearlyTotals.set(d.getFullYear(), (yearlyTotals.get(d.getFullYear()) || 0) + (Number(entry.steps) || 0));
            });
            const yearlyLabels = Array.from(yearlyTotals.keys()).sort((a, b) => a - b).filter(y => (yearlyTotals.get(y) || 0) > 0);
            const yearlyData = yearlyLabels.map(year => yearlyTotals.get(year));
            if (!yearlyLabels.length) {
                if (empty) empty.style.display = 'block';
                if (grid) grid.style.display = 'none';
                return;
            }
            const year = new Date().getFullYear();
            const goal = Math.max(1, Number(userSettings.goal_steps) || 8000);
            let yearSteps = 0;
            let yearDays = 0;
            let goalDays = 0;
            const activeMonths = new Set();
            const monthly = Array.from({ length: 12 }, () => 0);
            stepsData.forEach(entry => {
                const d = localDateFromYMD(entry.date);
                if (isNaN(d.getTime()) || d.getFullYear() !== year) return;
                const steps = Number(entry.steps) || 0;
                monthly[d.getMonth()] += steps;
                if (steps <= 0) return;
                yearSteps += steps;
                yearDays += 1;
                activeMonths.add(d.getMonth());
                if (steps >= goal) goalDays += 1;
            });
            const completionPct = yearDays ? Math.min(100, Math.round((goalDays / yearDays) * 100)) : 0;
            const yearsCanvas = document.getElementById('stats-years-chart');
            if (yearsCanvas) {
                yearlyDistributionChart = new Chart(yearsCanvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: [
                            translations.stats_goal_completion || 'Goal completion',
                            translations.stats_remaining || 'Remaining'
                        ],
                        datasets: [{
                            data: [Math.max(completionPct, 0), Math.max(0, 100 - completionPct)],
                            backgroundColor: ['#3B82F6', isDarkTheme ? '#2E3034' : '#E5E7EB'],
                            borderWidth: 0,
                            hoverOffset: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '72%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: context => `${context.label}: ${context.raw}%`
                                }
                            }
                        }
                    }
                });
            }
            const pctNode = document.getElementById('stats-yearly-donut-pct');
            const stepsNode = document.getElementById('stats-yearly-donut-steps');
            if (pctNode) pctNode.textContent = completionPct + '%';
            if (stepsNode) stepsNode.textContent = formatNumber(yearSteps);
            const metricsHost = document.getElementById('stats-yearly-metrics');
            if (metricsHost) {
                metricsHost.textContent = '';
                const monthNames = Array.from({ length: 12 }, (_, i) => new Date(year, i, 1).toLocaleDateString(lang, { month: 'short' }));
                const monthIndexes = Array.from(activeMonths).sort((a, b) => a - b);
                const monthsLabel = monthIndexes.length
                    ? (monthIndexes.length === 1
                        ? monthNames[monthIndexes[0]]
                        : monthNames[monthIndexes[0]] + ' – ' + monthNames[monthIndexes[monthIndexes.length - 1]])
                    : '—';
                const rows = [
                    [translations.stats_active_months || 'Active Months', monthsLabel],
                    [translations.stats_goal_days || 'Goal Completion Days', formatNumber(goalDays)],
                    [translations.stats_daily_average || 'Daily Average', formatNumber(yearDays ? Math.round(yearSteps / yearDays) : 0) + ' ' + (translations.steps_per_day || 'steps/day')]
                ];
                rows.forEach(([label, value]) => {
                    const row = document.createElement('div');
                    row.className = 'stats-yearly-metric-row';
                    const l = document.createElement('span');
                    l.textContent = label;
                    const v = document.createElement('strong');
                    v.textContent = value;
                    row.appendChild(l);
                    row.appendChild(v);
                    metricsHost.appendChild(row);
                });
            }
            const monthlyTitle = document.getElementById('stats-monthly-title');
            if (monthlyTitle) monthlyTitle.textContent = (translations.statistics_monthly_intensity || 'Monthly Step Intensity') + ' ' + year;
            monthlyIntensityChart = new Chart(document.getElementById('stats-months-chart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: Array.from({ length: 12 }, (_, i) => new Date(year, i, 1).toLocaleDateString(lang, { month: 'short' })),
                    datasets: [{
                        label: translations.statistics_bar_chart_label || 'Monthly Step Intensity',
                        data: monthly,
                        backgroundColor: createBarGradient(document.getElementById('stats-months-chart'), 0.7),
                        borderColor: 'rgba(0,123,255,1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: chartGridColor },
                            border: { color: chartGridColor },
                            ticks: { color: chartTextColor, callback: v => formatNumber(v) },
                            title: { display: true, text: translations.steps || 'Steps', color: chartTextColor }
                        },
                        x: {
                            grid: { color: chartGridColor },
                            border: { color: chartGridColor },
                            ticks: { color: chartTextColor }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: context => `${translations.steps || 'Steps'}: ${formatNumber(Math.round(context.raw))}` } }
                    }
                }
            });
        }

        const statsExperience = { summary: null, achievements: null, active: 'overview', summaryLoading: false, achievementsLoading: false, error: '' };

        function statsText(id, value) {
            const node = document.getElementById(id);
            if (node) node.textContent = value == null || value === '' ? '—' : String(value);
        }

        function statsDistance(meters) {
            const value = Number(meters) || 0;
            if (value <= 0) return '0 km';
            return value < 1000 ? Math.round(value) + ' m' : (value / 1000).toFixed(value >= 100000 ? 0 : 1) + ' km';
        }

        function statsDuration(seconds) {
            const value = Math.max(0, Math.round(Number(seconds) || 0));
            const hours = Math.floor(value / 3600);
            const minutes = Math.round((value % 3600) / 60);
            return hours > 0 ? hours + 'h ' + minutes + 'm' : minutes + 'm';
        }

        function statsDate(value) {
            if (!value) return '';
            const date = new Date(value);
            return isNaN(date.getTime()) ? String(value) : date.toLocaleDateString(currentLocale, { day: 'numeric', month: 'short', year: 'numeric' });
        }

        function statsBadge(text, kind) {
            const node = document.createElement('span');
            if (kind) node.className = kind;
            node.textContent = text;
            return node;
        }

        function renderStatsFreshness() {
            const host = document.getElementById('stats-freshness');
            if (!host) return;
            host.textContent = '';
            const summary = statsExperience.summary;
            if (statsExperience.error) host.appendChild(statsBadge(statsExperience.error, 'is-waiting'));
            if (summary) {
                if (summary.processing) host.appendChild(statsBadge(translations.stats_processing || 'Updating in the background…', 'is-waiting'));
                else host.appendChild(statsBadge(translations.stats_up_to_date || 'Up to date', 'is-live'));
                if (summary.computed_at) host.appendChild(statsBadge(statsDate(summary.computed_at)));
                if (summary.cached) host.appendChild(statsBadge(translations.stats_cached || 'Instant cached view'));
            } else if (statsExperience.summaryLoading) {
                host.appendChild(statsBadge(translations.stats_loading || 'Loading your activity…', 'is-waiting'));
            }
            const achievements = statsExperience.achievements;
            if (achievements && achievements.enabled === false) host.appendChild(statsBadge(translations.achievement_disabled_notice || 'Achievements are disabled'));
        }

        function renderStatsOverview() {
            const year = new Date().getFullYear();
            let steps = 0;
            let days = 0;
            stepsData.forEach(entry => {
                const date = localDateFromYMD(entry.date);
                if (!isNaN(date.getTime()) && date.getFullYear() === year) {
                    steps += Number(entry.steps) || 0;
                    if ((Number(entry.steps) || 0) > 0) days++;
                }
            });
            statsText('stats-overview-steps', formatNumber(steps));
            statsText('stats-overview-average', days ? (translations.stats_daily_average || 'Daily average') + ': ' + formatNumber(Math.round(steps / days)) : '');
            const summary = statsExperience.summary;
            const metrics = summary && summary.metrics ? summary.metrics : {};
            statsText('stats-overview-days', metrics.active_days != null ? formatNumber(metrics.active_days) : (days || 0));
            statsText('stats-overview-distance', metrics.route_distance_m != null ? statsDistance(metrics.route_distance_m) : '—');
            const places = summary && summary.most_visited_places ? summary.most_visited_places : [];
            statsText('stats-overview-places', places.length ? formatNumber(places.length) : (metrics.cities ? metrics.cities.length : '—'));
            const achievements = statsExperience.achievements;
            if (achievements && achievements.enabled !== false) {
                const earned = Number(achievements.earned_count != null ? achievements.earned_count : (achievements.earned_achievements || []).length) || 0;
                statsText('stats-overview-earned', formatNumber(earned));
            } else if (achievements && achievements.enabled === false) {
                statsText('stats-overview-earned', '—');
            }

            const reach = document.getElementById('stats-overview-reach');
            const reachCount = document.getElementById('stats-overview-reach-count');
            if (reach) {
                reach.textContent = '';
                const countries = metrics.countries || [];
                const cities = metrics.cities || [];
                if (reachCount) {
                    if (countries.length) {
                        reachCount.hidden = false;
                        reachCount.textContent = countries.length + ' ' + (countries.length === 1
                            ? (translations.stats_country_one || translations.stats_country || 'country')
                            : (translations.stats_countries || 'countries'));
                    } else {
                        reachCount.hidden = true;
                        reachCount.textContent = '';
                    }
                }
                countries.slice(0, 8).forEach(country => {
                    const chip = statsBadge((country.name || country.code || '') + ' · ' + (translations.stats_country || 'country'));
                    chip.className = 'stats-reach-chip';
                    reach.appendChild(chip);
                });
                if (!countries.length) {
                    cities.slice(0, 6).forEach(city => {
                        const chip = statsBadge(String(city));
                        chip.className = 'stats-reach-chip';
                        reach.appendChild(chip);
                    });
                }
                if (!countries.length && !cities.length) {
                    reach.appendChild(statsBadge(translations.stats_reach_empty || 'Movement will appear here as your history grows.'));
                }
            }
        }

        function renderStatsMovement() {
            const summary = statsExperience.summary;
            if (!summary) return;
            const metrics = summary.metrics || {};
            const movement = summary.movement_patterns || {};
            const places = summary.most_visited_places || [];
            statsText('stats-movement-distance', statsDistance(metrics.route_distance_m));
            statsText('stats-movement-trips', formatNumber(movement.trips || 0));
            statsText('stats-movement-trip-avg', movement.avg_distance_m ? ' · ' + statsDistance(movement.avg_distance_m) : '');
            statsText('stats-movement-time', statsDuration(metrics.active_seconds));
            const top = places[0];
            if (top && (top.name || top.label)) {
                statsText('stats-movement-place', top.name || top.label);
                statsText('stats-movement-place-meta', (translations.stats_visits || 'visits') + ': ' + formatNumber(top.visits || top.count || 0));
            } else {
                statsText('stats-movement-place', translations.stats_no_confirmed_place || 'No confirmed place');
                statsText('stats-movement-place-meta', translations.stats_confirm_places_hint || 'Confirm places on map');
            }
            const host = document.getElementById('stats-week-rhythm');
            if (!host) return;
            host.textContent = '';
            const weekdays = (translations.days_of_week && translations.days_of_week.length === 7) ? translations.days_of_week : ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
            const rows = movement.by_weekday || [];
            const max = rows.reduce((value, row) => Math.max(value, Number(row.trips) || 0), 0);
            for (let index = 0; index < 7; index++) {
                const row = rows[index] || {};
                const trips = Number(row.trips) || 0;
                const day = document.createElement('div');
                day.className = 'stats-rhythm-day' + (max && trips === max ? ' is-peak' : '');
                const value = document.createElement('strong');
                value.textContent = formatNumber(trips);
                const bar = document.createElement('div');
                bar.className = 'stats-rhythm-bar';
                bar.style.height = (max ? Math.max(8, Math.round(trips / max * 160)) : 8) + 'px';
                bar.title = weekdays[index] + ': ' + trips;
                const label = document.createElement('small');
                label.textContent = weekdays[index];
                day.appendChild(value);
                day.appendChild(bar);
                day.appendChild(label);
                host.appendChild(day);
            }
        }

        function renderStatsQuality() {
            const summary = statsExperience.summary;
            if (!summary) return;
            const coverage = summary.coverage || {};
            const accuracy = summary.accuracy || {};
            const health = summary.source_health || {};
            const percent = Math.round((Number(coverage.coverage_ratio) || 0) * 100);
            statsText('stats-quality-coverage', percent + '%');
            statsText('stats-quality-accuracy', accuracy.avg_m != null ? '±' + Math.round(accuracy.avg_m) + ' m' : '—');
            statsText('stats-quality-latest', health.latest_point_at ? statsDate(health.latest_point_at) : '—');
            statsText('stats-quality-devices', formatNumber((health.devices || []).length));
            const host = document.getElementById('stats-quality-sources');
            if (!host) return;
            host.textContent = '';
            const sources = health.sources || {};
            const devices = health.devices || [];
            const sourceNames = Object.keys(sources);
            if (!sourceNames.length && !devices.length) {
                const empty = document.createElement('p');
                empty.className = 'stats-source-empty';
                empty.textContent = translations.stats_no_sources || 'No paired sources yet.';
                host.appendChild(empty);
                return;
            }
            // Tables read far better than loose chips: one row per source/device,
            // tabular numbers in the count column, explicit column headers.
            const cell = (item, className) => {
                const td = document.createElement('td');
                if (className) td.className = className;
                if (item && item.nodeType === 1) td.appendChild(item);
                else td.textContent = item;
                return td;
            };
            const buildGridTable = (colsClass, headers, rows) => {
                const wrap = document.createElement('div');
                wrap.className = 'stats-source-grid ' + colsClass;
                const head = document.createElement('div');
                head.className = 'stats-source-row is-head';
                headers.forEach((label, index) => {
                    const cellNode = document.createElement('div');
                    cellNode.className = 'stats-source-cell' + (index === 0 ? ' is-name' : ' is-num');
                    cellNode.textContent = label;
                    head.appendChild(cellNode);
                });
                wrap.appendChild(head);
                rows.forEach(cells => {
                    const row = document.createElement('div');
                    row.className = 'stats-source-row';
                    cells.forEach((item, index) => {
                        const cellNode = document.createElement('div');
                        cellNode.className = 'stats-source-cell' + (index === 0 ? ' is-name' : ' is-num');
                        if (item && item.nodeType === 1) cellNode.appendChild(item);
                        else cellNode.textContent = item == null ? '—' : String(item);
                        row.appendChild(cellNode);
                    });
                    wrap.appendChild(row);
                });
                return wrap;
            };
            if (sourceNames.length) {
                host.appendChild(buildGridTable(
                    'cols-sources',
                    [translations.stats_source_name || 'Source', translations.stats_source_points || 'Points'],
                    sourceNames.map(source => [source, formatNumber(sources[source])])
                ));
            }
            if (devices.length) {
                const statusLabels = {
                    active: translations.device_active || 'Active',
                    paused: translations.device_paused || 'Paused',
                    revoked: translations.device_revoked || 'Revoked'
                };
                host.appendChild(buildGridTable(
                    'cols-devices',
                    [
                        translations.stats_connected_device || translations.device_name || 'Connected device',
                        translations.stats_device_points || 'Points',
                        translations.stats_device_last_seen || 'Last seen'
                    ],
                    devices.map(device => {
                        const status = String(device.status || '').toLowerCase();
                        const name = device.name || device.platform || 'Device';
                        const label = statusLabels[status] || device.status || '—';
                        const nameCell = document.createElement('div');
                        nameCell.className = 'stats-device-inline';
                        const title = document.createElement('span');
                        title.className = 'stats-device-name';
                        title.textContent = name;
                        nameCell.appendChild(title);
                        const formatRaw = String(device.format || device.platform || '').toLowerCase();
                        const formatMatch = formatRaw.match(/\b(gpx|geojson|json|csv|tcx)\b/i);
                        if (formatMatch) {
                            const formatPill = document.createElement('span');
                            formatPill.className = 'stats-device-format';
                            formatPill.textContent = formatMatch[1].toUpperCase();
                            nameCell.appendChild(formatPill);
                        }
                        const statusChip = document.createElement('span');
                        statusChip.className = 'stats-source-status' +
                            (status === 'active' ? ' is-active' : '') +
                            (status === 'paused' ? ' is-paused' : '') +
                            (status === 'revoked' ? ' is-revoked' : '');
                        statusChip.textContent = label;
                        nameCell.appendChild(statusChip);
                        return [
                            nameCell,
                            formatNumber(device.points),
                            device.last_seen_at ? statsDate(device.last_seen_at) : '—'
                        ];
                    })
                ));
            }
        }

        function achievementVisual(item) {
            const id = String(item.id || '');
            const target = Number(item.target) || 0;
            if (id.startsWith('coverage_days_')) {
                return { tone: 'coverage', icon: target >= 365 ? 'fa-calendar-check' : target >= 30 ? 'fa-calendar-week' : 'fa-calendar-day' };
            }
            if (id.startsWith('coverage_cells_')) {
                return { tone: 'coverage', icon: target >= 200 ? 'fa-map-location-dot' : target >= 50 ? 'fa-map' : 'fa-table-cells-large' };
            }
            if (id.startsWith('distance_')) {
                return { tone: 'distance', icon: target >= 1000000 ? 'fa-compass' : target >= 500000 ? 'fa-route' : 'fa-person-walking' };
            }
            if (id.startsWith('places_')) {
                return { tone: 'places', icon: target >= 25 ? 'fa-map-location-dot' : target >= 10 ? 'fa-map-pin' : 'fa-location-dot' };
            }
            if (id.startsWith('cities_')) return { tone: 'cities', icon: target >= 25 ? 'fa-city' : 'fa-building' };
            if (id.startsWith('regions_')) return { tone: 'regions', icon: target >= 5 ? 'fa-map' : 'fa-map-location-dot' };
            if (id.startsWith('countries_')) return { tone: 'countries', icon: target >= 10 ? 'fa-earth-americas' : 'fa-flag' };
            // Steps milestones share the same cards as the location ones.
            if (id.startsWith('steps_total_')) return { tone: 'steps', icon: 'fa-shoe-prints' };
            if (id.startsWith('steps_day_')) return { tone: 'steps', icon: 'fa-star' };
            if (id.startsWith('steps_goal_')) return { tone: 'steps', icon: 'fa-bullseye' };
            if (id.startsWith('steps_streak_')) return { tone: 'steps', icon: 'fa-fire-flame-curved' };
            if (id.startsWith('steps_walk_')) return { tone: 'steps', icon: target >= 1000000 ? 'fa-person-hiking' : 'fa-person-walking' };
            if (id.startsWith('steps_trail_')) return { tone: 'trail', icon: 'fa-map-location-dot' };
            return { tone: 'default', icon: 'fa-medal' };
        }

        function achievementTier(item) {
            const target = Number(item.target) || 0;
            if (item.unit === 'm') return (target / 1000) + ' km';
            if (item.unit === 'days') return target + ' ' + (translations.achievement_days || 'days');
            if (item.unit === 'steps') return formatNumber(target) + ' ' + (translations.steps || 'steps');
            return formatNumber(target);
        }

        function achievementYear(item) {
            const raw = String(item.earned_at || item.unlocked_at || item.created_at || '');
            const m = raw.match(/(20\d{2}|19\d{2})/);
            return m ? m[1] : 'undated';
        }

        function filterStatsAchievementsByYear(year) {
            statsExperience.achYear = year || 'ALL';
            renderStatsAchievements();
        }

        function renderStatsAchievements() {
            const data = statsExperience.achievements;
            const host = document.getElementById('stats-achievement-grid');
            const empty = document.getElementById('stats-achievement-empty');
            const yearHost = document.getElementById('stats-ach-year-filters');
            if (!host) return;
            host.textContent = '';
            const earned = data && data.enabled !== false ? (data.earned_achievements || []) : [];
            if (data) statsText('stats-achievement-count', formatNumber(data.earned_count || earned.length));
            if (empty) empty.hidden = earned.length > 0;
            if (!data && statsExperience.achievementsLoading) {
                if (empty) { empty.hidden = false; empty.textContent = translations.stats_loading || 'Loading…'; }
                return;
            }
            const byYear = {};
            earned.forEach(item => {
                const y = achievementYear(item);
                (byYear[y] || (byYear[y] = [])).push(item);
            });
            const years = Object.keys(byYear).sort((a, b) => {
                if (a === 'undated') return 1;
                if (b === 'undated') return -1;
                return String(b).localeCompare(String(a));
            });
            const activeYear = statsExperience.achYear || 'ALL';
            if (yearHost) {
                yearHost.textContent = '';
                const makeChip = (key, label) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'ach-year-chip' + (activeYear === key ? ' is-active' : '');
                    btn.setAttribute('data-year', key);
                    btn.textContent = label;
                    btn.addEventListener('click', () => filterStatsAchievementsByYear(key));
                    yearHost.appendChild(btn);
                };
                makeChip('ALL', (translations.stats_all_years || 'All Years') + ' (' + earned.length + ')');
                years.forEach(y => {
                    const label = (y === 'undated' ? (translations.stats_undated || 'Undated') : y) + ' (' + byYear[y].length + ')';
                    makeChip(y, label);
                });
            }
            const visibleYears = activeYear === 'ALL' ? years : years.filter(y => y === activeYear);
            let index = 0;
            visibleYears.forEach(year => {
                const group = document.createElement('div');
                group.className = 'stats-ach-year-group';
                group.setAttribute('data-year', year);
                const head = document.createElement('div');
                head.className = 'stats-ach-year-head';
                const badge = document.createElement('span');
                badge.className = 'stats-ach-year-badge';
                badge.textContent = year === 'undated' ? (translations.stats_undated || 'Undated') : year;
                const count = document.createElement('span');
                count.className = 'stats-ach-year-count';
                const n = byYear[year].length;
                count.textContent = n + ' ' + (translations.stats_milestones_count || translations.stats_milestones || 'milestones');
                const rule = document.createElement('div');
                rule.className = 'stats-ach-year-rule';
                rule.setAttribute('aria-hidden', 'true');
                head.appendChild(badge);
                head.appendChild(count);
                head.appendChild(rule);
                group.appendChild(head);
                const grid = document.createElement('div');
                grid.className = 'stats-achievement-grid';
                byYear[year].forEach(item => {
                    const card = document.createElement('article');
                    const visual = achievementVisual(item);
                    card.className = 'stats-achievement stats-achievement--' + visual.tone;
                    card.style.animationDelay = Math.min(index * 35, 260) + 'ms';
                    index += 1;
                    const icon = document.createElement('div');
                    icon.className = 'stats-achievement-icon';
                    icon.innerHTML = '<i class="fa-solid ' + visual.icon + '" aria-hidden="true"></i>';
                    const title = document.createElement('h4');
                    title.textContent = translations[item.title_key] || trFallbackKey(item.title_key);
                    const description = document.createElement('p');
                    description.textContent = translations[item.description_key] || trFallbackKey(item.description_key);
                    const footer = document.createElement('footer');
                    footer.className = 'stats-achievement-foot';
                    const tier = document.createElement('span');
                    tier.className = 'stats-achievement-tier';
                    tier.textContent = achievementTier(item);
                    const badge = document.createElement('span');
                    badge.className = 'stats-achievement-badge';
                    badge.textContent = (translations.achievement_earned || 'Earned') + (item.earned_at ? ' · ' + statsDate(item.earned_at) : '');
                    footer.appendChild(tier);
                    footer.appendChild(badge);
                    card.appendChild(icon);
                    card.appendChild(title);
                    card.appendChild(description);
                    card.appendChild(footer);
                    grid.appendChild(card);
                });
                group.appendChild(grid);
                host.appendChild(group);
            });
            if (!earned.length && data && empty) empty.textContent = translations.stats_no_achievements || 'Your first milestone is still ahead.';
        }

        function trFallbackKey(key) {
            return String(key || '').replace(/^achievement_(desc_|family_|collection_)?/, '').replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase());
        }

        function activateStatsTab(name, focus) {
            const valid = ['overview', 'activity', 'movement', 'achievements', 'quality'];
            if (!valid.includes(name)) name = 'overview';
            statsExperience.active = name;
            document.querySelectorAll('#stats-modal .stats-tab').forEach(button => {
                const on = button.getAttribute('data-stats-tab') === name;
                button.classList.toggle('is-active', on);
                button.setAttribute('aria-selected', on ? 'true' : 'false');
                button.tabIndex = on ? 0 : -1;
                if (on && focus) button.focus({ preventScroll: true });
            });
            document.querySelectorAll('#stats-modal .stats-panel').forEach(panel => {
                const on = panel.getAttribute('data-stats-panel') === name;
                panel.hidden = !on;
                panel.classList.toggle('is-active', on);
            });
            if (name === 'activity') setTimeout(() => renderStatisticsCharts(), 20);
            if (name === 'overview') renderStatsOverview();
            if (name === 'movement') renderStatsMovement();
            if (name === 'achievements') renderStatsAchievements();
            if (name === 'quality') renderStatsQuality();
            if (name === 'movement' || name === 'quality') loadStatsSummary(false);
            if (name === 'achievements') loadStatsAchievements(false);
        }

        function statsGateway(endpoint) {
            return '/apps/steps/api/session_gateway.php?endpoint=' + endpoint;
        }

        async function loadStatsSummary(force) {
            if (statsExperience.summaryLoading) return;
            if (statsExperience.summary && !force) return;
            statsExperience.summaryLoading = true;
            renderStatsFreshness();
            try {
                const response = await fetch(statsGateway('geolocation_statistics'), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const data = await response.json();
                if (!data.ok) throw new Error(data.error || 'statistics_failed');
                statsExperience.summary = data;
                statsExperience.error = '';
            } catch (error) {
                statsExperience.error = translations.stats_unavailable || 'Activity data is temporarily unavailable.';
            } finally {
                statsExperience.summaryLoading = false;
                renderStatsFreshness();
                renderStatsOverview();
                renderStatsMovement();
                renderStatsQuality();
            }
        }

        async function loadStatsAchievements(force) {
            if (statsExperience.achievementsLoading) return;
            if (statsExperience.achievements && !force) return;
            statsExperience.achievementsLoading = true;
            renderStatsAchievements();
            try {
                const response = await fetch(statsGateway('geolocation_achievements'), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const data = await response.json();
                if (!data.ok) throw new Error(data.error || 'achievements_failed');
                statsExperience.achievements = data;
            } catch (error) {
                statsExperience.achievements = { enabled: true, earned_achievements: [], earned_count: 0 };
                statsExperience.error = translations.stats_achievements_unavailable || 'Achievements are temporarily unavailable.';
            } finally {
                statsExperience.achievementsLoading = false;
                renderStatsFreshness();
                renderStatsOverview();
                renderStatsAchievements();
            }
        }

        function openStatisticsExperience() {
            statsExperience.summary = null;
            statsExperience.achievements = null;
            statsExperience.error = '';
            statsExperience.summaryLoading = false;
            statsExperience.achievementsLoading = false;
            activateStatsTab('overview');
            loadStatsSummary(true);
            loadStatsAchievements(true);
        }

        document.addEventListener('click', event => {
            const button = event.target && event.target.closest ? event.target.closest('#stats-modal .stats-tab') : null;
            if (!button) return;
            activateStatsTab(button.getAttribute('data-stats-tab'), true);
        });
        document.addEventListener('keydown', event => {
            if (!event.target || !event.target.closest || !event.target.closest('#stats-modal .stats-tabbar')) return;
            if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return;
            const tabs = Array.from(document.querySelectorAll('#stats-modal .stats-tab'));
            const current = tabs.indexOf(document.activeElement);
            if (current < 0) return;
            event.preventDefault();
            const next = tabs[(current + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
            activateStatsTab(next.getAttribute('data-stats-tab'), true);
        });

        // Functions for calendar
        function updateCalendar(lang = currentLocale) {
            const yearSelector = document.getElementById('year-selector');
            const monthSelector = document.getElementById('month-selector');
            const years = getAvailableYears();
            yearSelector.innerHTML = years
                .map(y => `<option value="${y}" ${y === currentDate.getFullYear() ? 'selected' : ''}>${y}</option>`)
                .join('');
            populateMonthSelectorForYear(currentDate.getFullYear(), lang, currentDate.getMonth() + 1);
            renderCalendar(new Date(currentDate.getFullYear(), currentDate.getMonth(), 1), lang);
            refreshCustomSelects();
        }

        function populateMonthSelectorForYear(year, lang = currentLocale, selectMonth = null) {
            const monthSelector = document.getElementById('month-selector');
            const months = getAvailableMonthsForYear(year, lang);
            if (!months.length) {
                monthSelector.innerHTML = '<option value="-">—</option>';
                monthSelector.disabled = true;
                refreshCustomSelects();
                return;
            }
            monthSelector.disabled = false;
            monthSelector.innerHTML = months
                .map(m => `<option value="${m.value}" ${selectMonth && m.value === `${year}-${selectMonth}` ? 'selected' : ''}>${m.label}</option>`)
                .join('');
            refreshCustomSelects();
        }

        function syncCalendarSelectors() {
            const yearSelector = document.getElementById('year-selector');
            const currentYear = currentDate.getFullYear();
            if (yearSelector.value !== String(currentYear)) {
                yearSelector.value = String(currentYear);
                populateMonthSelectorForYear(currentYear, currentLocale, currentDate.getMonth() + 1);
            } else {
                document.getElementById('month-selector').value = `${currentYear}-${currentDate.getMonth() + 1}`;
            }
            refreshCustomSelects();
        }

        function renderCalendar(date, lang = currentLocale) {
            const year = date.getFullYear(),
                  month = date.getMonth(),
                  firstDay = new Date(year, month, 1),
                  lastDay = new Date(year, month + 1, 0),
                  daysInMonth = lastDay.getDate(),
                  weekStartOffset = mondayWeekOffset(firstDay),
                  numRows = Math.ceil((weekStartOffset + daysInMonth) / 7),
                  totalCells = numRows * 7;
            let calendarHTML = `<table class="calendar-table"><tr>${(translations.days_of_week || ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']).map(day => `<th>${day}</th>`).join('')}</tr>`;
            for (let idx = 0; idx < totalCells; idx++) {
                if (idx % 7 === 0) calendarHTML += '<tr>';
                const dayDate = new Date(year, month, 1 - weekStartOffset + idx),
                      inMonth = dayDate.getMonth() === month && dayDate.getFullYear() === year,
                      domDay = dayDate.getDate(),
                      dateStr = formatLocalYMD(dayDate),
                      steps = (stepsData.find(entry => entry.date === dateStr) || { steps: 0 }).steps,
                      tipRaw = (translations.steps || 'Steps') + ': ' + formatNumber(steps),
                      tipAttr = tipRaw.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/'/g, '&#39;'),
                      sel = dayDate.toDateString() === new Date().toDateString() ? 'selected' : '',
                      oth = inMonth ? '' : ' calendar-day-other-month';
                calendarHTML += `<td class="${sel}${oth}" data-date="${dateStr}" title="${tipAttr}" tabindex="-1">
    <span class="day-number">${domDay}</span>
    ${steps >= userSettings.goal_steps ? '<img src="goal.webp" alt="Goal" class="goal-icon">' : ''}
</td>`;
                if (idx % 7 === 6) calendarHTML += '</tr>';
            }
            calendarHTML += '</table>';

            document.getElementById('calendar').innerHTML = calendarHTML;

            const monthSteps = stepsData.filter(entry =>
                localDateFromYMD(entry.date).getFullYear() === year &&
                localDateFromYMD(entry.date).getMonth() === month
            );

            const totalSteps = monthSteps.reduce((sum, entry) => sum + entry.steps, 0);
            const goalDays = monthSteps.filter(entry => entry.steps >= userSettings.goal_steps).length;

            const goalSummary = document.getElementById('goal-summary');
            if (goalSummary) {
                goalSummary.innerHTML = `
                <div class="goal-summary-item"><span class="goal-summary-label">${translations.total_steps}</span><span class="goal-summary-value">${formatNumber(totalSteps)}</span></div>
                <div class="goal-summary-item"><span class="goal-summary-label">${translations.goal_days}</span><span class="goal-summary-value">${goalDays}</span></div>`;
            }
            updateKpiCards(date, totalSteps, goalDays);
        }

        function updateKpiCards(selectedDate, monthTotalSteps, goalDaysCount) {
            const goal = Math.max(1, Number(userSettings.goal_steps) || 8000);
            const dateStr = formatLocalYMD(selectedDate instanceof Date ? selectedDate : currentDate);
            const day = stepsData.find(entry => entry.date === dateStr) || { steps: 0 };
            const steps = Number(day.steps) || 0;
            const pct = Math.round((steps / goal) * 100);
            const distanceKm = dayValue(dateStr, 'distance');
            const calories = dayValue(dateStr, 'calories');
            const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };
            setText('kpi-selected-label', (selectedDate instanceof Date ? selectedDate : currentDate).toLocaleDateString(currentLocale, { month: 'short', day: 'numeric', year: 'numeric' }));
            setText('kpi-goal-badge', pct + '%');
            const badge = document.getElementById('kpi-goal-badge');
            if (badge) badge.classList.toggle('is-complete', pct >= 100);
            setText('kpi-steps-val', formatNumber(steps));
            setText('kpi-steps-goal', '/ ' + formatNumber(goal));
            const bar = document.getElementById('kpi-steps-bar');
            if (bar) {
                bar.style.width = Math.min(100, pct) + '%';
                bar.classList.toggle('is-complete', pct >= 100);
            }
            setText('kpi-distance-val', (Number(distanceKm) || 0).toFixed(2));
            setText('kpi-calories-val', formatNumber(Math.round(Number(calories) || 0)));
            setText('kpi-streak-val', formatNumber(goalDaysCount != null ? goalDaysCount : 0));
            const monthTotal = document.getElementById('kpi-month-total');
            if (monthTotal) {
                const label = (translations.goal_days || translations.streak_avg_hint || 'Goal days') + ' · ' + (translations.total_steps || 'Month total') + ': ';
                monthTotal.textContent = '';
                monthTotal.appendChild(document.createTextNode(label));
                const strong = document.createElement('span');
                strong.className = 'kpi-sub-strong';
                strong.textContent = formatNumber(monthTotalSteps != null ? monthTotalSteps : 0);
                monthTotal.appendChild(strong);
            }
        }

        // Main mode switch: steps / map
        function initModeSwitch() {
            const appMain = document.querySelector('.app-main'),
                  mapView = document.getElementById('map-view'),
                  buttons = Array.from(document.querySelectorAll('.mode-switch-btn'));
            if (!buttons.length || !appMain || !mapView) return;
            const applyMode = mode => {
                buttons.forEach(btn => {
                    const on = btn.getAttribute('data-mode') === mode;
                    btn.classList.toggle('active', on);
                    btn.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                const showMap = mode === 'map';
                appMain.hidden = showMap;
                mapView.hidden = !showMap;
                appMain.style.setProperty('display', showMap ? 'none' : 'flex', 'important');
                mapView.style.setProperty('display', showMap ? 'grid' : 'none', 'important');
                document.body.classList.toggle('md3-map-mode', showMap);
                if (!showMap) document.body.classList.remove('map-sidebar-open');
                if (showMap) {
                    window.dispatchEvent(new Event('steps-map-shown'));
                    try {
                        window.dispatchEvent(new Event('resize'));
                    } catch (e) {}
                } else if (chart && typeof chart.resize === 'function') {
                    chart.resize();
                }
            };
            buttons.forEach(btn => btn.addEventListener('click', () => applyMode(btn.getAttribute('data-mode'))));
        }

        // Custom dropdowns for the calendar selectors
        function initCustomSelect(nativeId, title) {
            const native = document.getElementById(nativeId);
            if (!native) return;
            const root = native.closest('.custom-select');
            if (!root) return;
            const trigger = root.querySelector('.custom-select-trigger'),
                  valueEl = root.querySelector('.custom-select-value'),
                  menu = root.querySelector('.custom-select-menu');
            if (!trigger || !valueEl || !menu) return;
            const escapeHtml = text => String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            const render = () => {
                const options = Array.from(native.options).filter(opt => opt.value !== '' || native.dataset.allowEmpty);
                const selected = options.find(opt => opt.selected) || options[0] || null;
                valueEl.textContent = selected ? selected.textContent.trim() : '—';
                trigger.disabled = native.disabled || !options.length;
                menu.innerHTML = `<div class="custom-select-header">${escapeHtml(title)}</div><div class="custom-select-grid">` +
                    options.map(opt => `<button type="button" class="custom-select-option${opt.selected ? ' active' : ''}" data-value="${escapeHtml(opt.value)}" role="option" aria-selected="${opt.selected ? 'true' : 'false'}">${escapeHtml(opt.textContent.trim())}</button>`).join('') +
                    '</div>';
            };
            const placeMenu = () => {
                const r = trigger.getBoundingClientRect();
                const width = Math.max(r.width, 180);
                const maxH = Math.min(280, window.innerHeight - 16);
                menu.classList.add('is-portal-open');
                menu.style.display = 'block';
                menu.style.position = 'fixed';
                menu.style.left = Math.min(Math.max(8, r.left), window.innerWidth - width - 8) + 'px';
                menu.style.width = width + 'px';
                menu.style.right = 'auto';
                menu.style.zIndex = '200000';
                menu.style.maxHeight = maxH + 'px';
                menu.style.overflow = 'auto';
                let top = r.bottom + 8;
                const h = Math.min(menu.scrollHeight || 200, maxH);
                if (top + h > window.innerHeight - 8) top = Math.max(8, r.top - h - 8);
                menu.style.top = top + 'px';
            };
            const close = () => {
                root.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
                menu.classList.remove('is-portal-open');
                menu.style.display = '';
                if (menu.parentElement !== root) root.appendChild(menu);
                menu.style.position = '';
                menu.style.left = '';
                menu.style.top = '';
                menu.style.width = '';
                menu.style.right = '';
                menu.style.zIndex = '';
                menu.style.maxHeight = '';
            };
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                if (trigger.disabled) return;
                if (root.classList.contains('is-open')) { close(); return; }
                document.querySelectorAll('.custom-select.is-open').forEach(other => {
                    if (typeof other._stepsClose === 'function') other._stepsClose();
                    else {
                        other.classList.remove('is-open');
                        const otherTrigger = other.querySelector('.custom-select-trigger');
                        if (otherTrigger) otherTrigger.setAttribute('aria-expanded', 'false');
                    }
                });
                document.querySelectorAll('.custom-select-menu.is-portal-open').forEach(m => {
                    m.classList.remove('is-portal-open');
                    m.style.display = '';
                    m.style.position = '';
                    m.style.left = '';
                    m.style.top = '';
                    m.style.width = '';
                    m.style.right = '';
                    m.style.zIndex = '';
                    m.style.maxHeight = '';
                });
                render();
                document.body.appendChild(menu);
                root.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
                placeMenu();
            });
            menu.addEventListener('click', e => {
                const option = e.target.closest('.custom-select-option');
                if (!option) return;
                const value = option.getAttribute('data-value');
                close();
                if (native.value !== value) {
                    native.value = value;
                    native.dispatchEvent(new Event('change', { bubbles: true }));
                }
                render();
            });
            document.addEventListener('click', e => {
                if (!root.classList.contains('is-open')) return;
                if (!root.contains(e.target) && !menu.contains(e.target)) close();
            });
            document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
            window.addEventListener('resize', () => { if (root.classList.contains('is-open')) placeMenu(); });
            window.addEventListener('scroll', () => { if (root.classList.contains('is-open')) placeMenu(); }, true);
            root._stepsClose = close;
            native.addEventListener('change', render);
            new MutationObserver(render).observe(native, { childList: true, subtree: true });
            root._stepsRender = render;
            render();
        }
        function refreshCustomSelects() {
            document.querySelectorAll('.custom-select').forEach(root => {
                if (typeof root._stepsRender === 'function') root._stepsRender();
            });
        }

        // Minimalist day steps editor
        function openDayStepsEditor(date) {
            const input = document.getElementById('day-steps-input');
            if (!input) return;
            const entry = stepsData.find(item => item.date === date),
                  steps = entry ? (Number(entry.steps) || 0) : 0;
            input.dataset.date = date;
            input.value = steps > 0 ? formatNumber(steps) : '';
            const dateEl = document.getElementById('day-steps-date');
            if (dateEl) dateEl.textContent = localDateFromYMD(date).toLocaleDateString(currentLocale, { day: 'numeric', month: 'long', year: 'numeric' });
            openModal('day-steps-modal');
            setTimeout(() => { input.focus(); input.select(); }, 60);
        }
        async function saveDaySteps() {
            const input = document.getElementById('day-steps-input');
            if (!input) return;
            const date = input.dataset.date || '',
                  steps = parseStepsRaw(input.value) || '0';
            if (!date || !/^\d*$/.test(steps)) {
                showToast(translations.invalid_date_steps || 'Invalid date or steps', 'error');
                return;
            }
            const formData = new FormData();
            formData.append('date', date);
            formData.append('steps', steps);
            formData.append('add_steps', '1');
            try {
                const response = await fetch('', { method: 'POST', body: formData, credentials: 'same-origin' });
                const data = await response.json();
                if (data.status !== 'success') {
                    showToast(data.message || translations.failed_save_settings || 'Error', 'error');
                    return;
                }
                const value = parseInt(steps, 10) || 0;
                stepsData = stepsData.filter(entry => entry.date !== date);
                stepsData.push({ date, steps: value });
                stepsData.sort((a, b) => new Date(a.date) - new Date(b.date));
                closeModal('day-steps-modal');
                renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                updateCalendar(currentLocale);
                updateStatsNotification();
                updateGoalNotification();
            } catch (err) {
                showToast(`${translations.failed_save_settings || 'Error'}: ${err.message}`, 'error');
            }
        }

        // Functions for add steps modal
        function applyManualDate(date, lang = currentLocale) {
            document.getElementById('selected-date-input').value = date;
            setStepsInputValue((stepsData.find(entry => entry.date === date) || { steps: 0 }).steps);
            manualSaveSignature = getManualSaveSignature();
            updateAdjacentDates(date, lang);
        }
        let manualSaveSignature = '';
        function getManualSaveSignature() {
            return document.getElementById('selected-date-input').value + '|' + parseStepsRaw(document.getElementById('steps-input').value);
        }
        function handleDateWheelScroll(e) {
            e.preventDefault();
            const dateInput = document.getElementById('selected-date-input'),
                  cur = localDateFromYMD(dateInput.value);
            if (isNaN(cur.getTime())) return;
            const direction = e.deltaY > 0 ? 'next' : 'prev';
            cur.setDate(cur.getDate() + (direction === 'next' ? 1 : -1));
            const newDateStr = formatLocalYMD(cur);
            applyManualDate(newDateStr, currentLocale);
        }
        function updateAdjacentDates(date, lang = currentLocale) {
            const dateObj = localDateFromYMD(date),
                  prevDates = [],
                  nextDates = [];
            if (isNaN(dateObj.getTime())) return;
            const addPanel = document.getElementById('add-panel-manual');
            const panelHeight = addPanel ? addPanel.clientHeight : 510;
            const currentDateEl = addPanel ? addPanel.querySelector('.current-date') : null;
            const currentDateHeight = currentDateEl ? currentDateEl.getBoundingClientRect().height : 60;
            // Make adjacent dates fill most of the modal height (without relying on scroll)
            const probe = document.querySelector('#prev-dates .adjacent-date, #next-dates .adjacent-date');
            const rowHeight = probe ? probe.getBoundingClientRect().height + 10 : 42;
            const usable = Math.max(0, panelHeight - currentDateHeight - 20);
            const count = Math.max(3, Math.min(18, Math.floor(usable / (2 * rowHeight))));

            for (let i = 1; i <= count; i++) {
                const prevDate = new Date(dateObj);
                prevDate.setDate(dateObj.getDate() - i);

                const nextDate = new Date(dateObj);
                nextDate.setDate(dateObj.getDate() + i);
                const prevStr = formatLocalYMD(prevDate), nextStr = formatLocalYMD(nextDate);
                prevDates.push({
                    date: prevStr,
                    steps: (stepsData.find(entry => entry.date === prevStr) || { steps: 0 }).steps
                });

                nextDates.push({
                    date: nextStr,
                    steps: (stepsData.find(entry => entry.date === nextStr) || { steps: 0 }).steps
                });
            }

            document.getElementById('prev-dates').innerHTML = prevDates.reverse()
                .map(item => `<div class="adjacent-date" data-date="${item.date}">
                    <span>${localDateFromYMD(item.date).toLocaleDateString(lang)}</span>
                    <span>${formatNumber(item.steps)}</span>
                </div>`)
                .join('');

            document.getElementById('next-dates').innerHTML = nextDates
                .map(item => `<div class="adjacent-date" data-date="${item.date}">
                    <span>${localDateFromYMD(item.date).toLocaleDateString(lang)}</span>
                    <span>${formatNumber(item.steps)}</span>
                </div>`)
                .join('');

            document.querySelectorAll('.adjacent-date').forEach(el => {
                el.addEventListener('click', () => {
                    applyManualDate(el.getAttribute('data-date'), lang);
                });
            });
        }

        async function saveSteps(options = {}) {
            const advance = options.advance !== false;
            const silent = options.silent === true;
            const date = document.getElementById('selected-date-input').value;
            const steps = parseStepsRaw(document.getElementById('steps-input').value);
            if (!date || !/^\d*$/.test(steps)) {
                if (!silent) showToast(translations.invalid_date_steps || 'Invalid date or steps', 'error');
                return;
            }
            document.getElementById('selected-date').value = date;
            document.getElementById('selected-steps').value = steps;

            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: new FormData(document.getElementById('add-steps-form'))
                });
                const data = await response.json();

                if (data.status === 'success') {
                    stepsData = stepsData.filter(entry => entry.date !== date);
                    stepsData.push({ date, steps: parseInt(steps) });
                    stepsData.sort((a, b) => new Date(a.date) - new Date(b.date));
                    manualSaveSignature = date + '|' + steps;

                    renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                    updateCalendar(currentLocale);
                    updateStatsNotification();
                    updateGoalNotification();
                    if (!advance) {
                        updateAdjacentDates(date, currentLocale);
                        return;
                    }
                    setStepsInputValue(0);
                    const nextDateObj = localDateFromYMD(date);
                    nextDateObj.setDate(nextDateObj.getDate() + 1);
                    const newDateStr = formatLocalYMD(nextDateObj);
                    applyManualDate(newDateStr, currentLocale, 'next');
                    document.getElementById('steps-input').focus();
                } else {
                    if (!silent) showToast(`${translations.invalid_request || 'Error'}: ${data.message}`, 'error');
                }
            } catch (err) {
                if (!silent) showToast(`${translations.invalid_request || 'Error'}: ${err.message}`, 'error');
            }
        }
        async function autoSaveManualSteps() {
            const steps = parseStepsRaw(document.getElementById('steps-input').value);
            const signature = getManualSaveSignature();
            if (steps === '' || signature === manualSaveSignature) return;
            await saveSteps({ advance: false, silent: true });
        }

        // Functions for Excel modal
        // ── Table with pagination and SQL filter ─────────────────────────────
        let tablePage = 1;
        let tableTotal = 0;
        let tablePageSize = 50;
        let tableFilterOp = '';
        let tableFilterVal = '';
        let tableFilterValTo = '';
        let tableDateFrom = '';
        let tableDateTo = '';

        async function renderExcelTable(page = 1, filterOp = tableFilterOp, filterVal = tableFilterVal, filterValTo = tableFilterValTo, dateFrom = tableDateFrom, dateTo = tableDateTo) {
            tablePage      = page;
            tableFilterOp  = filterOp;
            tableFilterVal = filterVal;
            tableFilterValTo = filterValTo;
            tableDateFrom = dateFrom;
            tableDateTo = dateTo;

            const container = document.getElementById('excel-table-container');
            const pagBar = document.getElementById('excel-table-pagination');
            if (!container) return;
            container.innerHTML = `<div style="text-align:center;padding:20px;">${translations.loading || 'Loading...'}</div>`;
            if (pagBar) pagBar.innerHTML = '';

            try {
                const fd = new FormData();
                fd.append('get_table_page', '1');
                fd.append('page', page);
                if (filterOp && filterVal !== '') {
                    fd.append('filter_op',  filterOp);
                    fd.append('filter_val', filterVal);
                    if (filterOp === 'range' && filterValTo !== '') fd.append('filter_val_to', filterValTo);
                }
                if (dateFrom) fd.append('date_from', dateFrom);
                if (dateTo) fd.append('date_to', dateTo);
                const res  = await fetch('', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.status !== 'success') {
                    container.innerHTML = `<p style="color:red">${data.message}</p>`;
                    if (pagBar) pagBar.innerHTML = '';
                    return;
                }

                tableTotal    = data.total;
                tablePageSize = data.page_size;
                const rows    = data.rows;
                const totalPages = Math.ceil(tableTotal / tablePageSize);

                let html = `<table class="excel-table" id="excel-table">
                    <thead><tr>
                        <th>${translations.date || 'Date'}</th>
                        <th>${translations.steps || 'Steps'}</th>
                    </tr></thead><tbody>`;

                rows.forEach((entry, rowIndex) => {
                    const d  = localDateFromYMD(entry.date);
                    const dd = String(d.getDate()).padStart(2,'0');
                    const mm = String(d.getMonth()+1).padStart(2,'0');
                    const yy = String(d.getFullYear()).slice(-2);
                    const fmtDate = `${dd}-${mm}-${yy}`;
                    html += `<tr data-row="${rowIndex}">
                        <td data-row="${rowIndex}" data-col="0" contenteditable="false">${fmtDate}</td>
                        <td data-row="${rowIndex}" data-col="1" contenteditable="false">${formatNumber(entry.steps)}</td>
                    </tr>`;
                });

                html += `</tbody></table>`;
                const pagHtml = `<div class="table-pagination">
                    <button class="page-btn" onclick="renderExcelTable(1)" ${page<=1?'disabled':''}>«</button>
                    <button class="page-btn" onclick="renderExcelTable(${page-1})" ${page<=1?'disabled':''}>‹</button>
                    <span class="page-info">${translations.page||'Page'} ${page} / ${totalPages} &nbsp; (${tableTotal} ${translations.records||'records'})</span>
                    <button class="page-btn" onclick="renderExcelTable(${page+1})" ${page>=totalPages?'disabled':''}>›</button>
                    <button class="page-btn" onclick="renderExcelTable(${totalPages})" ${page>=totalPages?'disabled':''}>»</button>
                </div>`;
                container.innerHTML = html;
                if (pagBar) pagBar.innerHTML = pagHtml;

                const table = document.getElementById('excel-table');
                if (table) { setupExcelTableEvents(table); setupTableNavigation(table); }
            } catch(err) {
                container.innerHTML = `<p style="color:red">${err.message}</p>`;
                if (pagBar) pagBar.innerHTML = '';
            }
        }

        function setupExcelTableEvents(table) {
            let isSelecting = false;
            let startCell = null;

            table.addEventListener('mousedown', (e) => {
                const cell = e.target.closest('td');
                if (!cell || cell.closest('thead')) return;

                const row = parseInt(cell.getAttribute('data-row'));
                const col = parseInt(cell.getAttribute('data-col'));
                currentRow = row;
                currentCol = col;

                if (e.shiftKey && startCell) {
                    // Range selection
                    selectCellRange(startCell, cell);
                } else {
                    // Single selection
                    clearSelection();
                    toggleCellSelection(cell);
                    startCell = cell;
                }

                isSelecting = true;
                e.preventDefault();
            });

            table.addEventListener('mouseover', (e) => {
                if (!isSelecting) return;

                const cell = e.target.closest('td');
                if (cell && startCell) {
                    selectCellRange(startCell, cell);
                }
            });

            // Auto-save on focus out
            table.addEventListener('focusout', (e) => {
                if (editingCell) {
                    finishEditing(true);
                }
            });

            // Save on Tab press
            table.addEventListener('keydown', (e) => {
                if (e.key === 'Tab' && editingCell) {
                    e.preventDefault();
                    finishEditing(true);

                    // Move to next cell
                    const nextCell = getCell(currentRow, currentCol + 1);
                    if (nextCell) {
                        startEditing(nextCell);
                    }
                }
            });

            document.addEventListener('mouseup', () => {
                isSelecting = false;
            });

            // Double click to edit
            table.addEventListener('dblclick', (e) => {
                const cell = e.target.closest('td');
                if (!cell || cell.closest('thead')) return;

                if (editingCell) {
                    finishEditing();
                }

                startEditing(cell);
            });
        }

        // Function for table navigation with arrow keys
        function setupTableNavigation(table) {
            function getCell(row, col) {
                return table.querySelector(`td[data-row="${row}"][data-col="${col}"]`);
            }

            function moveSelection(newRow, newCol) {
                const rows = table.querySelectorAll('tbody tr');
                const maxRow = rows.length - 1;
                const maxCol = 1; // 0 - date, 1 - steps

                // Constrain range
                newRow = Math.max(0, Math.min(newRow, maxRow));
                newCol = Math.max(0, Math.min(newCol, maxCol));

                // Clear selection from all cells
                clearSelection();

                // Select new cell
                const newCell = getCell(newRow, newCol);
                if (newCell) {
                    toggleCellSelection(newCell);
                    currentRow = newRow;
                    currentCol = newCol;

                    // Scroll to cell if not visible
                    newCell.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }

            // Handle hotkeys
            document.addEventListener('keydown', (e) => {
                // If in edit mode, don't handle arrows
                if (editingCell || e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA') {
                    return;
                }

                const modal = document.getElementById('excel-modal');
                if (!modal || modal.style.display !== 'flex') {
                    return;
                }

                switch (e.key) {
                    case 'ArrowUp':
                        e.preventDefault();
                        moveSelection(currentRow - 1, currentCol);
                        break;
                    case 'ArrowDown':
                        e.preventDefault();
                        moveSelection(currentRow + 1, currentCol);
                        break;
                    case 'ArrowLeft':
                        e.preventDefault();
                        moveSelection(currentRow, currentCol - 1);
                        break;
                    case 'ArrowRight':
                        e.preventDefault();
                        moveSelection(currentRow, currentCol + 1);
                        break;
                    case 'Enter':
                        if (!editingCell) {
                            const currentCell = getCell(currentRow, currentCol);
                            if (currentCell) {
                                if (editingCell) {
                                    finishEditing();
                                }
                                startEditing(currentCell);
                            }
                        } else {
                            finishEditing(true);
                        }
                        break;
                    case 'Escape':
                        if (editingCell) {
                            finishEditing(false);
                        }
                        break;
                    case 'Delete':
                        if (selectedCells.size > 0) {
                            deleteSelectedCells();
                        }
                        break;
                    case 'a':
                        if (e.ctrlKey) {
                            e.preventDefault();
                            selectAllCells();
                        }
                        break;
                    case 's':
                        if (e.ctrlKey) {
                            e.preventDefault();
                            updateDataFromTable();
                        }
                        break;
                }
            });
        }

        function selectAllCells() {
            const table = document.getElementById('excel-table');
            if (!table) return;

            clearSelection();

            const cells = table.querySelectorAll('td');
            cells.forEach(cell => {
                cell.classList.add('selected');
                selectedCells.add(cell);
            });
        }

        function toggleCellSelection(cell) {
            if (!cell) return;

            if (selectedCells.has(cell)) {
                cell.classList.remove('selected');
                selectedCells.delete(cell);
            } else {
                cell.classList.add('selected');
                selectedCells.add(cell);
            }
        }

        function selectCellRange(startCell, endCell) {
            if (!startCell || !endCell) return;

            clearSelection();

            const startRow = parseInt(startCell.getAttribute('data-row'));
            const startCol = parseInt(startCell.getAttribute('data-col'));
            const endRow = parseInt(endCell.getAttribute('data-row'));
            const endCol = parseInt(endCell.getAttribute('data-col'));

            const minRow = Math.min(startRow, endRow);
            const maxRow = Math.max(startRow, endRow);
            const minCol = Math.min(startCol, endCol);
            const maxCol = Math.max(startCol, endCol);

            const table = document.getElementById('excel-table');
            if (!table) return;

            for (let row = minRow; row <= maxRow; row++) {
                for (let col = minCol; col <= maxCol; col++) {
                    const cell = table.querySelector(`td[data-row="${row}"][data-col="${col}"]`);
                    if (cell) {
                        cell.classList.add('selected');
                        selectedCells.add(cell);
                    }
                }
            }
        }

        function clearSelection() {
            selectedCells.forEach(cell => {
                cell.classList.remove('selected');
            });
            selectedCells.clear();
        }

        function startEditing(cell) {
            if (!cell) return;

            // Check if cell is a date (column 0)
            const col = parseInt(cell.getAttribute('data-col'));
            if (col === 0) {
                // This is a date cell - editing not allowed
                showToast(translations.date_not_editable || 'Date cannot be edited', 'info');
                return;
            }

            // Finish previous editing if any
            if (editingCell) {
                finishEditing(false);
            }

            editingCell = cell;
            isEditing = true;
            cell.classList.add('editing');
            cell.setAttribute('contenteditable', 'true');

            const displayVal = cell.textContent.trim();
            if (col === 1) {
                const r = parseInt(parseStepsRaw(displayVal), 10) || 0;
                cell.setAttribute('data-original', String(r));
                cell.textContent = String(r);
            } else {
                cell.setAttribute('data-original', displayVal);
            }
            // Focus and select text
            cell.focus();

            // Select text
            const range = document.createRange();
            range.selectNodeContents(cell);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        }

        async function updateDataFromTable() {
            // Save only the editing cell
            if (!editingCell) return false;

            const row = parseInt(editingCell.getAttribute('data-row'));
            const col = parseInt(editingCell.getAttribute('data-col'));

            // Get entire row
            const table = document.getElementById('excel-table');
            if (!table) return false;

            const rowElement = table.querySelector(`tr[data-row="${row}"]`);
            if (!rowElement) return false;

            const cells = rowElement.querySelectorAll('td');
            if (cells.length < 2) return false;

            const dateCell = cells[0];
            const stepsCell = cells[1];

            const dateStr = dateCell.textContent.trim();
            const newSteps = parseInt(parseStepsRaw(stepsCell.textContent), 10) || 0;
            const originalSteps = parseInt(parseStepsRaw(editingCell.getAttribute('data-original') || '0'), 10) || 0;

            // Check if value changed
            if (newSteps === originalSteps) {
                return true;
            }
            return await persistTableRow(dateStr, newSteps, row);
        }
        async function persistTableRow(dateStr, newSteps, row) {
            if (!/^\d{2}-\d{2}-\d{2}$/.test(dateStr)) {
                showToast(translations.invalid_date_format || 'Invalid date format. Use dd-mm-yy format', 'error');
                return false;
            }
            if (isNaN(newSteps) || newSteps < 0) {
                await showAlert(translations.invalid_steps || 'Invalid steps value');
                return false;
            }
            try {
                const formData = new FormData();
                formData.append('save_table_data', '1');
                formData.append('date', dateStr);
                formData.append('steps', newSteps);
                formData.append('row_index', row);
                const response = await fetch('', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.status !== 'success') {
                    showToast(`${translations.save_error || 'Error saving data'}: ${data.message}`, 'error');
                    return false;
                }
                updateLocalData(dateStr, newSteps, row);
                return true;
            } catch (err) {
                showToast(`${translations.save_error || 'Error saving data'}: ${err.message}`, 'error');
                return false;
            }
        }

        function updateLocalData(date, steps, rowIndex) {
            // Convert date from dd-mm-yy to yyyy-mm-dd for lookup in stepsData
            const dateParts = date.split('-');
            const day = dateParts[0];
            const month = dateParts[1];
            const year = dateParts[2];

            // Determine full year
            const fullYear = parseInt(year) < 70 ? `20${year}` : `19${year}`;
            const searchDate = `${fullYear}-${month}-${day}`;
            let found = false;
            for (let i = 0; i < stepsData.length; i++) {
                if (stepsData[i].date === searchDate) {
                    stepsData[i].steps = steps;
                    found = true;
                    break;
                }
            }

            // If not found, add new entry
            if (!found) {
                stepsData.push({ date: searchDate, steps: steps });
                stepsData.sort((a, b) => new Date(a.date) - new Date(b.date));
            }

            // Update chart and calendar
            renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
            updateCalendar(currentLocale);
            updateStatsNotification();
            updateGoalNotification();

            setTimeout(() => {
                const table = document.getElementById('excel-table');
                if (table) {
                    const cell = table.querySelector(`td[data-row="${rowIndex}"][data-col="1"]`);
                    if (cell) {
                        cell.textContent = formatNumber(steps);
                    }
                }
            }, 0);
        }

        function updateLocalDataSilent(date, steps, rowIndex) {
            const dateParts = date.split('-');
            const day = dateParts[0];
            const month = dateParts[1];
            const year = dateParts[2];
            const fullYear = parseInt(year) < 70 ? `20${year}` : `19${year}`;
            const searchDate = `${fullYear}-${month}-${day}`;
            let found = false;
            for (let i = 0; i < stepsData.length; i++) {
                if (stepsData[i].date === searchDate) {
                    stepsData[i].steps = steps;
                    found = true;
                    break;
                }
            }
            if (!found) {
                stepsData.push({ date: searchDate, steps: steps });
                stepsData.sort((a, b) => new Date(a.date) - new Date(b.date));
            }
        }

        function finishEditing(save = true) {
    if (!editingCell) return;

    const originalValue = editingCell.getAttribute('data-original');
    const newValue = editingCell.textContent.trim();

    editingCell.classList.remove('editing');
    editingCell.setAttribute('contenteditable', 'false');
    const cellToUpdate = editingCell;
    editingCell = null;
    isEditing = false;

    const colx = parseInt(cellToUpdate.getAttribute('data-col'), 10);
    if (!save) {
        if (colx === 1) {
            cellToUpdate.textContent = formatNumber(parseInt(originalValue || '0', 10) || 0);
        } else {
            cellToUpdate.textContent = originalValue;
        }
        cellToUpdate.removeAttribute('data-original');
    } else if (originalValue !== newValue) {
        const finalNewValue = newValue;
        cellToUpdate.textContent = finalNewValue;
        cellToUpdate.removeAttribute('data-original');
        (async () => {
            editingCell = cellToUpdate;
            editingCell.setAttribute('data-original', originalValue);
            const success = await updateDataFromTable();
            if (!success) {
                if (colx === 1) {
                    cellToUpdate.textContent = formatNumber(parseInt(originalValue || '0', 10) || 0);
                } else {
                    cellToUpdate.textContent = originalValue;
                }
            }
            cellToUpdate.removeAttribute('data-original');
            editingCell = null;
        })();
    } else {
        if (colx === 1) {
            cellToUpdate.textContent = formatNumber(parseInt(newValue, 10) || 0);
        }
        cellToUpdate.removeAttribute('data-original');
    }
}

        async function deleteSelectedCells() {
            if (selectedCells.size === 0) return;

            const confirm = await showAlert(
                translations.confirm_delete_cells_message || `Delete ${selectedCells.size} selected cell(s)?`,
                true,
                {
                    title: translations.confirm_delete_cells || 'Delete selected cells?',
                    subtitle: translations.alert_cannot_undo || 'This action cannot be undone.',
                    variant: 'danger',
                    okText: translations.delete || 'Delete'
                }
            );

            if (!confirm) return;
            const targets = Array.from(selectedCells).filter(cell => parseInt(cell.getAttribute('data-col'), 10) === 1);
            if (targets.length === 0) return;

            const items = [];
            for (const cell of targets) {
                const row = parseInt(cell.getAttribute('data-row'), 10);
                if (Number.isNaN(row)) continue;
                const rowEl = cell.closest('tr');
                if (!rowEl) continue;
                const dateCell = rowEl.querySelector('td[data-col="0"]');
                if (!dateCell) continue;
                const dateStr = dateCell.textContent.trim();
                items.push({ date: dateStr, steps: 0, row: row, cell: cell });
            }
            if (items.length === 0) return;

            try {
                const formData = new FormData();
                formData.append('save_table_data_batch', '1');
                formData.append('items', JSON.stringify(items.map(i => ({ date: i.date, steps: i.steps }))));
                const response = await fetch('', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.status !== 'success') {
                    showToast(`${translations.save_error || 'Error saving data'}: ${data.message}`, 'error');
                    return;
                }
                for (const item of items) {
                    updateLocalDataSilent(item.date, 0, item.row);
                    item.cell.textContent = '0';
                }
                renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                updateCalendar(currentLocale);
                updateStatsNotification();
                updateGoalNotification();
            } catch (err) {
                showToast(`${translations.save_error || 'Error saving data'}: ${err.message}`, 'error');
                return;
            }
            clearSelection();
            showToast(translations.cells_cleared || 'Cells cleared successfully', 'success');
        }

        // Function to create new backup
        async function createBackup() {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'create_backup=1'
                });

                const data = await response.json();

                if (data.status === 'success') {
                    showToast(data.message || translations.backup_created || 'Backup created successfully', 'success');
                    if (document.getElementById('settings-panel-backups')) {
                        loadBackups();
                    }
                } else {
                    showToast(`Error: ${data.message}`, 'error');
                }
            } catch (err) {
                showToast(`Error creating backup: ${err.message}`, 'error');
            }
        }

        // Search — SQL filter via server, first page
        async function searchInTable() {
            const val   = document.getElementById('search-steps').value.trim();
            const op    = document.getElementById('search-operator').value;
            const valTo = document.getElementById('search-steps-to').value.trim();
            const dateFrom = document.getElementById('search-date-from').value;
            const dateTo = document.getElementById('search-date-to').value;

            if (!val && op !== 'range' && !dateFrom && !dateTo) {
                await renderExcelTable(1, '', '', '', '', '');
                return;
            }
            await renderExcelTable(1, op, val, valTo, dateFrom, dateTo);
        }

        async function loadBackups() {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'get_backups_list=1'
                });

                const data = await response.json();

                if (data.status === 'success') {
                    renderBackupsList(data.backups);
                } else {
                    showToast(`Error: ${data.message}`, 'error');
                }
            } catch (err) {
                showToast(`Error: ${err.message}`, 'error');
            }
        }

        function renderBackupsList(backups) {
            const container = document.getElementById('backups-list');
            if (!container) return;

            if (!backups || backups.length === 0) {
                container.innerHTML = `<div style="text-align: center; padding: 20px; color: #666;">${translations.no_backups_found || 'No backups found'}</div>`;
                return;
            }

            let html = '';

            const lblName = translations.backup_name || 'Backup';
            const lblDate = translations.date || 'Date';
            const lblSize = translations.size || 'Size';
            const lblActions = translations.actions || 'Actions';

            html = `
                <div class="backup-table-wrap">
                    <table class="backup-table">
                        <thead>
                            <tr>
                                <th>${lblName}</th>
                                <th>${lblDate}</th>
                                <th>${lblSize}</th>
                                <th>${lblActions}</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${backups.map(backup => {
                                const fileSize = (backup.size / 1024).toFixed(2);
                                return `
                                    <tr>
                                        <td class="backup-name" data-label="${lblName}">${backup.name}</td>
                                        <td data-label="${lblDate}">${backup.date}</td>
                                        <td data-label="${lblSize}">${fileSize} KB</td>
                                        <td class="actions-cell" data-label="${lblActions}">
                                            <div class="backup-actions">
                                                <button class="backup-btn restore" data-file="${backup.name}">
                                                    <i class="fas fa-history"></i> ${translations.restore || 'Restore'}
                                                </button>
                                                <button class="backup-btn delete" data-file="${backup.name}">
                                                    <i class="fas fa-trash"></i> ${translations.delete || 'Delete'}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            `;

            container.innerHTML = html;

            // Add event handlers for buttons
            container.querySelectorAll('.backup-btn.restore').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const fileName = btn.getAttribute('data-file');
                    await restoreBackup(fileName);
                });
            });

            container.querySelectorAll('.backup-btn.delete').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const fileName = btn.getAttribute('data-file');
                    await deleteBackup(fileName);
                });
            });
        }

        async function restoreBackup(fileName) {
            const confirm = await showAlert(
                (translations.restore_backup_detail || 'Restore data from "{file}"?').replace(/\{file\}/g, fileName),
                true,
                {
                    title: translations.confirm_restore_backup || 'Restore backup',
                    subtitle: translations.restore_replaces_data || 'Current data will be replaced with data from the backup.',
                    variant: 'warning',
                    okText: translations.restore || 'Restore'
                }
            );

            if (!confirm) return;

            try {
                const formData = new FormData();
                formData.append('restore_backup', '1');
                formData.append('backup_file', fileName);

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.status === 'success') {
                    showToast(translations.backup_restored || 'Backup restored successfully', 'success');

                    // Reload page to refresh data
                    location.reload();
                } else {
                    showToast(`Error: ${data.message}`, 'error');
                }
            } catch (err) {
                showToast(`Error: ${err.message}`, 'error');
            }
        }

        async function deleteBackup(fileName) {
            const confirm = await showAlert(
                (translations.delete_backup_detail || 'Backup "{file}" will be permanently deleted.').replace(/\{file\}/g, fileName),
                true,
                {
                    title: translations.confirm_delete_backup || 'Delete backup',
                    subtitle: translations.alert_cannot_undo || 'This action cannot be undone.',
                    variant: 'danger',
                    okText: translations.delete || 'Delete'
                }
            );

            if (!confirm) return;

            try {
                const formData = new FormData();
                formData.append('delete_backup', '1');
                formData.append('backup_file', fileName);

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.status === 'success') {
                    showToast(translations.backup_deleted || 'Backup deleted successfully', 'success');
                    await loadBackups(); // Update the list
                } else {
                    showToast(`Error: ${data.message}`, 'error');
                }
            } catch (err) {
                showToast(`Error: ${err.message}`, 'error');
            }
        }

        async function downloadBackup() {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'download_backup=1'
                });

                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `Steps_backup_${formatLocalYMD(new Date())}.csv`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    await showAlert(translations.backup_downloaded || 'Backup downloaded successfully');
                } else {
                    const data = await response.json();
                    showToast(`Error: ${data.message}`, 'error');
                }
            } catch (err) {
                showToast(`Error: ${err.message}`, 'error');
            }
        }

        async function importExternalBackup() {
            const fileInput = document.getElementById('external-backup-file');
            const mode = document.querySelector('input[name="external-import-mode"]:checked')?.value || 'merge';
            if (!fileInput?.files?.length) {
                await showAlert(translations.no_file_selected || 'No file selected');
                return;
            }
            if (mode === 'replace') {
                const confirmed = await showAlert(
                    translations.import_replace_notice || 'All current data will be replaced with data from the imported file.',
                    true,
                    {
                        title: (translations.import_mode_replace || 'Replace all data') + '?',
                        variant: 'warning',
                        okText: translations.import_external_btn || 'Import'
                    }
                );
                if (!confirmed) return;
            }
            const btn = document.getElementById('import-external-backup-btn');
            const origHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            try {
                const formData = new FormData();
                formData.append('import_external_backup', '1');
                formData.append('backup_file', fileInput.files[0]);
                formData.append('import_mode', mode);
                const response = await fetch('', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.status === 'success') {
                    await showAlert(data.message || (translations.external_backup_imported || 'Import complete'));
                    fileInput.value = '';
                    location.reload();
                } else {
                    showToast(`Error: ${data.message}`, 'error');
                }
            } catch (err) {
                showToast(`Error: ${err.message}`, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = origHTML;
            }
        }

        let fitPollTimer = null, fitLastJobId = null;
        let zlPollTimer = null, zlLastJobId = null;
        let pendingVideoCleanup = { jobId: '', outputBasename: '' };
        let pendingZeepJobId = null;
        let pendingVideoRows = [];
        let pendingVideoOcrPreview = [];
        let globalImageViewerOpen = false;
        let workerWidgetTimer = null;
        const workerProgressState = { zeep: null, takeout: null };
        const ZL_API = <?php echo is_file(__DIR__ . '/../utils/zeep_life/zeep_api.php')
            ? json_encode('utils/zeep_life/zeep_api.php', JSON_HEX_TAG)
            : 'null'; ?>;
        const FTO_API = <?php echo is_file(__DIR__ . '/../utils/google_fit_takeout/takeout_api.php')
            ? json_encode('utils/google_fit_takeout/takeout_api.php', JSON_HEX_TAG)
            : 'null'; ?>;
        function closeGlobalImageViewer() {
            const wrap = document.getElementById('global-image-viewer');
            const img = document.getElementById('global-image-viewer-img');
            if (!wrap || !img) return;
            wrap.classList.remove('open');
            wrap.setAttribute('aria-hidden', 'true');
            img.src = '';
            img.alt = '';
            globalImageViewerOpen = false;
        }
        function openGlobalImageViewer(src, alt) {
            const wrap = document.getElementById('global-image-viewer');
            const img = document.getElementById('global-image-viewer-img');
            if (!wrap || !img || !src) return;
            img.src = src;
            img.alt = alt || '';
            wrap.classList.add('open');
            wrap.setAttribute('aria-hidden', 'false');
            globalImageViewerOpen = true;
        }
        function workerNormalizePct(v) {
            const n = Number(v);
            if (!isFinite(n)) return 0;
            return Math.max(0, Math.min(100, Math.round(n)));
        }
        function workerSetState(kind, state) {
            if (!Object.prototype.hasOwnProperty.call(workerProgressState, kind)) return;
            workerProgressState[kind] = state || null;
            workerRenderProgressWidget();
        }
        function workerRenderProgressWidget() {
            const box = document.getElementById('worker-progress-widget');
            if (!box) return;
            const items = [];
            if (workerProgressState.zeep && workerProgressState.zeep.status === 'running') {
                items.push({ icon: 'fas fa-file-archive', pct: workerNormalizePct(workerProgressState.zeep.progress) });
            }
            if (workerProgressState.takeout && workerProgressState.takeout.status === 'running') {
                items.push({ icon: 'fas fa-file-zipper', pct: workerNormalizePct(workerProgressState.takeout.progress) });
            }
            if (!items.length) {
                box.style.display = 'none';
                box.innerHTML = '';
                return;
            }
            box.innerHTML = items.map(function(it) {
                return '<div class="worker-progress-item"><i class="' + it.icon + '"></i><span class="worker-progress-text">' + String(it.pct) + '%</span></div>';
            }).join('');
            box.style.display = 'flex';
        }
        async function workerRefreshStates() {
            const requestActiveJob = function(api) {
                if (!api) return Promise.resolve(null);
                return fetch(api + '?action=active_job', { credentials: 'same-origin' })
                    .then(function(r) { return r.ok ? r.json() : null; })
                    .catch(function() { return null; });
            };
            const reqZeep = requestActiveJob(ZL_API);
            const reqTakeout = requestActiveJob(FTO_API);
            const res = await Promise.all([reqZeep, reqTakeout]);
            const zd = res[0];
            const td = res[1];
            workerSetState('zeep', zd && zd.ok && zd.active && zd.active.state ? zd.active.state : null);
            workerSetState('takeout', td && td.ok && td.active && td.active.state ? td.active.state : null);
            return !!(
                (zd && zd.ok && zd.active && zd.active.state && zd.active.state.status === 'running') ||
                (td && td.ok && td.active && td.active.state && td.active.state.status === 'running')
            );
        }
        function workerStartRefreshLoop() {
            if (workerWidgetTimer) clearTimeout(workerWidgetTimer);
            workerWidgetTimer = null;
            const refresh = function() {
                workerRefreshStates().then(function(hasActiveJob) {
                    workerWidgetTimer = hasActiveJob ? setTimeout(refresh, 2500) : null;
                });
            };
            refresh();
        }
        let zlWizardStep = 1;
        let zlWizardLocked = false;
        let zlCachedFile = null;
        let zlCachedPassword = '';
        let ftWizardStep = 1;
        let ftWizardLocked = false;
        function zlNormPassword(v) {
            return String(v || '').replace(/[\u200B-\u200D\uFEFF\u2060]/g, '').trim();
        }
        function zlCacheInputs() {
            const inp = document.getElementById('zl-zip');
            const pwdEl = document.getElementById('zl-password');
            zlCachedFile = (inp && inp.files && inp.files[0]) ? inp.files[0] : null;
            zlCachedPassword = pwdEl ? zlNormPassword(pwdEl.value) : '';
        }
        function zlHasFile() {
            if (zlCachedFile) return true;
            return !!(document.getElementById('zl-zip')?.files?.[0]);
        }
        function zlHasReadyInput() {
            const p = document.getElementById('zl-password');
            const pwd = zlCachedPassword || (p ? zlNormPassword(p.value) : '');
            return zlHasFile() && !!pwd;
        }
        function zlUpdateWizardUi() {
            const hasFile = zlHasFile();
            const ready = zlHasReadyInput();
            let maxStep = 1;
            if (ready) maxStep = zlWizardStep >= 2 ? 3 : 2;
            if (!hasFile) zlWizardStep = 1;
            if (zlWizardStep > maxStep) zlWizardStep = maxStep;
            setWizardUi('zl-steps', zlWizardStep, maxStep, zlWizardLocked);
            setWizardSections('add-panel-video', zlWizardStep, zlWizardLocked);
            const st = document.getElementById('zl-start');
            if (st) st.disabled = !(ready && zlWizardStep === 3 && !zlWizardLocked);
            const n1 = document.getElementById('zl-next-step1');
            if (n1) n1.disabled = !(ready && zlWizardStep === 1 && !zlWizardLocked);
            const n2 = document.getElementById('zl-next');
            if (n2) n2.disabled = !(ready && zlWizardStep === 2 && !zlWizardLocked);
        }
        function setWizardUi(rootId, currentStep, maxReached, locked) {
            const root = document.getElementById(rootId);
            if (!root) return;
            const dots = root.querySelectorAll('.va-step-dot');
            const lines = root.querySelectorAll('.va-step-line');
            dots.forEach((dot, idx) => {
                const step = idx + 1;
                const done = step < currentStep;
                dot.classList.toggle('is-active', step === currentStep);
                dot.classList.toggle('is-done', done);
                dot.textContent = done ? '✓' : (idx + 1);
                dot.disabled = !!locked || step > maxReached;
            });
            lines.forEach((line, idx) => { line.classList.toggle('is-done', idx + 1 < currentStep); });
            const fill = root.querySelector('.va-progress-fill');
            if (fill) fill.style.width = ((currentStep - 1) / Math.max(1, dots.length - 1)) * 100 + '%';
        }
        function setWizardSections(panelId, currentStep, locked) {
            const panel = document.getElementById(panelId);
            if (!panel) return;
            const prev = panel._wizardPrevStep != null ? panel._wizardPrevStep : currentStep;
            panel._wizardPrevStep = currentStep;
            const dir = currentStep > prev ? 'next' : (currentStep < prev ? 'prev' : 'up');
            panel.querySelectorAll('[data-step-section]').forEach((section) => {
                const step = parseInt(section.getAttribute('data-step-section') || '0', 10);
                const active = step === currentStep;
                if (active) {
                    section.style.display = '';
                    section.classList.remove('va-section-in-up', 'va-section-in-next', 'va-section-in-prev');
                    void section.offsetWidth;
                    section.classList.add('va-section-in-' + dir);
                } else {
                    section.style.display = 'none';
                }
                section.classList.toggle('is-locked', !!locked);
                section.querySelectorAll('input,select,button,textarea').forEach((el) => {
                    if (el.dataset && el.dataset.wizardKeepEnabled === '1') return;
                    el.disabled = !!locked || !active;
                });
            });
        }
        function ftUpdateWizardUi() {
            const hasFile = !!(document.getElementById('ft-zip') && document.getElementById('ft-zip').files && document.getElementById('ft-zip').files[0]);
            let maxStep = 1;
            if (hasFile) maxStep = ftWizardStep >= 2 ? 3 : 2;
            if (!hasFile) ftWizardStep = 1;
            if (ftWizardStep > maxStep) ftWizardStep = maxStep;
            setWizardUi('ft-steps', ftWizardStep, maxStep, ftWizardLocked);
            setWizardSections('add-panel-takeout', ftWizardStep, ftWizardLocked);
            const n1 = document.getElementById('ft-next-step1');
            if (n1) n1.disabled = !(hasFile && ftWizardStep === 1 && !ftWizardLocked);
            const st = document.getElementById('ft-start');
            if (st) st.disabled = !(hasFile && ftWizardStep === 3 && !ftWizardLocked);
        }
        function zlStopPoll() { if (zlPollTimer) { clearInterval(zlPollTimer); zlPollTimer = null; } }
        function zlSetBar(pct) { const f = document.getElementById('zl-progress-fill'); if (f) f.style.width = Math.min(100, Math.max(0, pct)) + '%'; }
        function zlTranslateStateMessage(st) {
            if (!st) return '—';
            const raw = st.message;
            if (raw == null || raw === '') {
                if (st.status === 'running') return translations.zeep_queued || translations.takeout_queued || '…';
                return st.status || '—';
            }
            const s = String(raw);
            if (s === 'unzip') return translations.takeout_unzip || s;
            if (s === 'parse') return translations.takeout_parse || s;
            if (s === 'queued') return translations.zeep_queued || translations.takeout_queued || s;
            if (s === 'conflict_prompt') return translations.takeout_conflict_prompt || s;
            if (s === 'apply') return translations.takeout_apply || s;
            if (s === 'bad password') return translations.zeep_err_password || s;
            if (s === 'preview') return translations.zeep_preview_parsing || s;
            const x = s.match(/^file (\d+)\/(\d+)$/i);
            if (x) return (translations.takeout_file_n || s).replace(/\{a\}/g, x[1]).replace(/\{b\}/g, x[2]);
            if (s === 'no step data in archive') return translations.takeout_err_none || s;
            if (s === 'stale_job') return translations.video_err_stale_job || s;
            return s;
        }
        function zlZeepApiError(err) {
            const e = String(err || '').trim();
            if (e === 'state') return translations.zeep_err_state || translations.takeout_err_state || 'Job is not ready for this action';
            if (e === 'rows') return translations.zeep_err_rows || translations.video_no_data || 'No step data';
            if (e === 'not found') return translations.zeep_err_not_found || 'Job not found';
            return e;
        }
        async function zlResolveConflict(mode, jid) {
            const fd = new FormData();
            fd.append('action', 'resolve_conflict');
            fd.append('job', jid);
            fd.append('mode', mode);
            let d = null;
            try {
                d = await fetch(ZL_API, { method: 'POST', body: fd, credentials: 'same-origin' }).then(r => r.json());
            } catch (e) {
                d = null;
            }
            if (!d || !d.ok) {
                await showAlert((translations.zeep_err_worker || translations.takeout_err_worker || 'Error') + (d && d.error ? ' ' + zlZeepApiError(d.error) : ''));
                return;
            }
            if (d.state && d.state.status) {
                zlOnState(d.state);
                return;
            }
            zlStartPoll(jid);
        }
        function zlOnState(st) {
            if (!st) return;
            workerSetState('zeep', st);
            const m = document.getElementById('zl-msg');
            if (m) m.textContent = zlTranslateStateMessage(st);
            zlSetBar(st.progress != null && !isNaN(st.progress) ? st.progress : 0);
            const meta = document.getElementById('zl-meta');
            if (meta) {
                const n = st.total_days != null && st.total_days > 0 ? st.total_days : 0;
                meta.textContent = n ? (translations.takeout_meta_days || '').replace(/\{n\}/g, String(n)) : '';
            }
            if (st.status === 'done') {
                zlStopPoll();
                workerSetState('zeep', null);
                zlWizardLocked = false;
                zlUpdateWizardUi();
                zlLastJobId = null;
                location.reload();
            } else if (st.status === 'awaiting_confirm') {
                zlStopPoll();
                zlWizardLocked = false;
                zlUpdateWizardUi();
                zlShowPreviewConfirm(st.job_id || zlLastJobId);
            } else if (st.status === 'awaiting_conflict') {
                zlStopPoll();
                const n = parseInt(st.conflict_days, 10) || 0;
                const q = (translations.takeout_conflict_question || 'Conflicts found for {n} days. Overwrite existing values?').replace(/\{n\}/g, String(n));
                showAlert(q, true, {
                    title: translations.conflict_title || 'Conflicts detected',
                    variant: 'warning',
                    okText: translations.takeout_overwrite || 'Overwrite',
                    cancelText: translations.cancel || 'Cancel',
                    extraText: translations.takeout_add_new_only || 'Add only new values'
                }).then(async (choice) => {
                    if (choice === true) {
                        await zlResolveConflict('overwrite', st.job_id || zlLastJobId);
                        return;
                    }
                    if (choice === 'extra') {
                        await zlResolveConflict('new_only', st.job_id || zlLastJobId);
                        return;
                    }
                    await zlStopJob();
                });
            } else if (st.status === 'error') {
                zlStopPoll();
                workerSetState('zeep', null);
                zlWizardLocked = false;
                zlUpdateWizardUi();
                zlLastJobId = null;
                let det = String(st.message || '');
                if (det === 'stale_job') det = translations.video_err_stale_job || det;
                else if (det === 'no step data in archive') det = translations.takeout_err_none || det;
                else if (det === 'bad password') det = translations.zeep_err_password || det;
                showAlert((translations.video_job_err || 'Error') + (det ? ': ' + det : ''));
            } else if (st.status === 'cancelled') {
                zlStopPoll();
                workerSetState('zeep', null);
                zlWizardLocked = false;
                zlUpdateWizardUi();
                zlLastJobId = null;
                const p = document.getElementById('zl-job-panel');
                if (p) p.style.display = 'none';
            }
        }
        function zlStartPoll(jid) {
            if (!ZL_API) return;
            zlLastJobId = jid;
            zlStopPoll();
            zlWizardLocked = true;
            zlUpdateWizardUi();
            const p = document.getElementById('zl-job-panel');
            if (p) p.style.display = 'block';
            zlOnState({ status: 'running', message: 'queued', progress: 0, job_id: jid });
            const one = () => {
                fetch(ZL_API + '?action=job_status&job=' + encodeURIComponent(jid), { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(d => {
                        if (d && d.ok && d.state) zlOnState(d.state);
                    })
                    .catch(function(e) {});
            };
            one();
            zlPollTimer = setInterval(one, 400);
        }
        async function zlResumeFromAccountState() {
            if (!ZL_API) return false;
            let d = null;
            try {
                d = await fetch(ZL_API + '?action=active_job', { credentials: 'same-origin' }).then(r => r.json());
            } catch (e) {
                d = null;
            }
            const p = document.getElementById('zl-job-panel');
            if (d && d.ok && d.active && d.active.job_id) {
                zlLastJobId = d.active.job_id;
                if (p) p.style.display = 'block';
                zlWizardLocked = true;
                zlUpdateWizardUi();
                if (d.active.state) {
                    if (d.active.state.status === 'awaiting_confirm') {
                        zlWizardLocked = false;
                        zlUpdateWizardUi();
                        zlShowPreviewConfirm(d.active.job_id);
                        return true;
                    }
                    zlOnState(d.active.state);
                }
                zlStartPoll(d.active.job_id);
                return true;
            }
            if (p) p.style.display = 'none';
            zlStopPoll();
            zlWizardLocked = false;
            zlUpdateWizardUi();
            zlLastJobId = null;
            return false;
        }
        async function zlStopJob() {
            if (!zlLastJobId) return;
            const ok = await showAlert(translations.worker_stop_confirm || '', true, {
                title: translations.worker_stop_title || 'Stop processing?',
                variant: 'warning'
            });
            if (!ok) return;
            const fd = new FormData();
            fd.append('action', 'stop_job');
            fd.append('job', zlLastJobId);
            try {
                await fetch(ZL_API, { method: 'POST', body: fd, credentials: 'same-origin' });
            } catch (e) {}
            zlStopPoll();
            workerSetState('zeep', null);
            zlLastJobId = null;
            zlWizardLocked = false;
            zlUpdateWizardUi();
            const p = document.getElementById('zl-job-panel');
            if (p) p.style.display = 'none';
            const pb = document.getElementById('zl-progress-fill');
            if (pb) pb.style.width = '0%';
        }
        async function zlCancelZeepJob(jid) {
            if (!jid) return;
            const fd = new FormData();
            fd.append('action', 'cancel_zeep');
            fd.append('job', jid);
            try {
                await fetch(ZL_API, { method: 'POST', body: fd, credentials: 'same-origin' });
            } catch (e) {}
            zlStopPoll();
            workerSetState('zeep', null);
            zlLastJobId = null;
            zlWizardLocked = false;
            zlUpdateWizardUi();
            const p = document.getElementById('zl-job-panel');
            if (p) p.style.display = 'none';
        }
        async function zlShowPreviewConfirm(jid) {
            if (!jid) return;
            pendingZeepJobId = jid;
            pendingVideoCleanup = { jobId: '', outputBasename: '' };
            pendingVideoOcrPreview = [];
            let rows = [];
            let previewOk = false;
            let previewError = '';
            try {
                const d = await fetch(ZL_API + '?action=preview&job=' + encodeURIComponent(jid), { credentials: 'same-origin' }).then((r) => r.json());
                previewOk = !!(d && d.ok);
                previewError = d && d.error ? String(d.error) : '';
                if (previewOk && Array.isArray(d.rows)) rows = d.rows;
            } catch (e) {
                previewError = 'network';
            }
            pendingVideoRows = [];
            rows.forEach((r) => {
                const iso = String(r.date || '').trim();
                const s = parseInt(String(r.steps), 10);
                if (!/^\d{4}-\d{2}-\d{2}$/.test(iso) || isNaN(s) || s < 0) return;
                pendingVideoRows.push({ date: videoIsoToDmy(iso), isoDate: iso, steps: s, t: '' });
            });
            if (!previewOk || !pendingVideoRows.length) {
                pendingZeepJobId = null;
                await showAlert(previewError ? zlZeepApiError(previewError) : (translations.zeep_preview_empty || translations.video_no_data || 'No data'));
                return;
            }
            const titleEl = document.getElementById('video-confirm-title');
            if (titleEl) titleEl.innerHTML = '<i class="fas fa-clipboard-list"></i> ' + videoEscapeHtml(translations.zeep_confirm_title || translations.video_confirm_title || 'Preview');
            const subEl = document.getElementById('video-confirm-sub');
            const emptyHint = document.getElementById('video-confirm-empty-hint');
            const phoneGuide = document.getElementById('video-confirm-phone-guide-segment');
            if (phoneGuide) phoneGuide.style.display = 'none';
            if (subEl) subEl.textContent = translations.zeep_confirm_sub || translations.video_confirm_sub || '';
            if (emptyHint) {
                emptyHint.style.display = !pendingVideoRows.length ? '' : 'none';
                emptyHint.textContent = translations.zeep_preview_empty || translations.video_none_recognized || '';
            }
            renderVideoConfirmOcrBlock();
            const imb = document.getElementById('video-confirm-import');
            if (imb) {
                imb.textContent = translations.zeep_confirm_import || translations.video_add_to_app || 'Import';
                imb.disabled = !pendingVideoRows.length;
                imb.style.opacity = pendingVideoRows.length ? '1' : '0.55';
            }
            renderVideoConfirmTableBody();
            closeModal('add-steps-modal');
            openModal('video-confirm-modal');
            requestAnimationFrame(function() { renderVideoConfirmChart(pendingVideoRows); });
        }
        async function zlConfirmImportFromPreview() {
            syncVideoConfirmFromInputs();
            if (!pendingZeepJobId) return false;
            if (!pendingVideoRows.length) {
                await showAlert(translations.zeep_preview_empty || translations.video_no_data || 'No data');
                return false;
            }
            const isoRows = pendingVideoRows
                .map((r) => ({ date: videoDmyToSortKey(r.date) || String(r.isoDate || ''), steps: parseInt(String(r.steps), 10) || 0 }))
                .filter((r) => /^\d{4}-\d{2}-\d{2}$/.test(r.date) && r.steps >= 0);
            if (!isoRows.length) {
                await showAlert(translations.zeep_err_rows || translations.video_no_data || 'No data');
                return false;
            }
            const jid = pendingZeepJobId;
            pendingZeepJobId = null;
            const fd = new FormData();
            fd.append('action', 'confirm_zeep');
            fd.append('job', jid);
            fd.append('rows', JSON.stringify(isoRows));
            fd.append('conflict_mode', 'ask');
            let d = null;
            try {
                d = await fetch(ZL_API, { method: 'POST', body: fd, credentials: 'same-origin' }).then((r) => r.json());
            } catch (e) {
                d = null;
            }
            if (!d || !d.ok) {
                pendingZeepJobId = jid;
                await showAlert((translations.zeep_err_worker || translations.takeout_err_worker || '') + (d && d.error ? ' ' + zlZeepApiError(d.error) : ''));
                return false;
            }
            closeModal('video-confirm-modal');
            if (d.state && d.state.status) {
                zlOnState(d.state);
                return true;
            }
            zlStartPoll(d.job_id || jid);
            return true;
        }
        function zlOpenZeepModal() {
            openModal('add-steps-modal', { addTab: 'video' });
        }
        async function streamUploadFileInChunks(apiUrl, file, kind, onProgress) {
            if (!apiUrl) throw new Error('API endpoint is unavailable');
            const chunkSize = 512 * 1024;
            const totalChunks = Math.max(1, Math.ceil((file.size || 0) / chunkSize));
            const uploadId = (typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID().replace(/-/g, '') : (Date.now().toString(16) + Math.random().toString(16).slice(2)));
            for (let i = 0; i < totalChunks; i++) {
                const start = i * chunkSize;
                const end = Math.min(file.size, start + chunkSize);
                const part = file.slice(start, end);
                const fd = new FormData();
                fd.append('action', 'upload_chunk');
                fd.append('file_kind', kind);
                fd.append('upload_id', uploadId);
                fd.append('chunk_index', String(i));
                fd.append('chunk_total', String(totalChunks));
                fd.append('file_size', String(file.size || 0));
                fd.append('file_name', file.name || '');
                fd.append('chunk', part, (file.name || 'chunk') + '.part');
                let d = null;
                try {
                    d = await fetch(apiUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then((r) => r.json());
                } catch (e) {
                    d = null;
                }
                if (!d || !d.ok) throw new Error((d && d.error) ? d.error : 'upload_chunk_failed');
                if (typeof onProgress === 'function') onProgress(Math.min(1, (i + 1) / totalChunks), i + 1, totalChunks);
            }
            return uploadId;
        }
        function ftStopPoll() { if (fitPollTimer) { clearInterval(fitPollTimer); fitPollTimer = null; } }
        function ftSetBar(pct) { const f = document.getElementById('ft-progress-fill'); if (f) f.style.width = Math.min(100, Math.max(0, pct)) + '%'; }
        function ftTranslateStateMessage(st) {
            if (!st) return '—';
            const raw = st.message;
            if (raw == null || raw === '') {
                if (st.status === 'running') return translations.takeout_queued || '…';
                return st.status || '—';
            }
            const s = String(raw);
            if (s === 'unzip') return translations.takeout_unzip || s;
            if (s === 'parse') return translations.takeout_parse || s;
            if (s === 'queued') return translations.takeout_queued || s;
            if (s === 'conflict_prompt') return translations.takeout_conflict_prompt || s;
            if (s === 'apply') return translations.takeout_apply || s;
            const x = s.match(/^file (\d+)\/(\d+)$/i);
            if (x) return (translations.takeout_file_n || s).replace(/\{a\}/g, x[1]).replace(/\{b\}/g, x[2]);
            if (s === 'no step data in archive') return translations.takeout_err_none || s;
            if (s === 'stale_job') return translations.video_err_stale_job || s;
            return s;
        }
        async function ftResolveConflict(mode, jid) {
            const fd = new FormData();
            fd.append('action', 'resolve_conflict');
            fd.append('job', jid);
            fd.append('mode', mode);
            let d = null;
            try {
                d = await fetch(FTO_API, { method: 'POST', body: fd, credentials: 'same-origin' }).then(r => r.json());
            } catch (e) {
                d = null;
            }
            if (!d || !d.ok) {
                await showAlert((translations.takeout_err_worker || 'Error') + (d && d.error ? ' ' + d.error : ''));
                return;
            }
            if (d.state && d.state.status) {
                ftOnState(d.state);
                return;
            }
            ftStartPoll(jid);
        }
        function ftOnState(st) {
            if (!st) return;
            workerSetState('takeout', st);
            const m = document.getElementById('ft-msg');
            if (m) m.textContent = ftTranslateStateMessage(st);
            ftSetBar(st.progress != null && !isNaN(st.progress) ? st.progress : 0);
            const meta = document.getElementById('ft-meta');
            if (meta) {
                const n = st.total_days != null && st.total_days > 0 ? st.total_days : 0;
                meta.textContent = n ? (translations.takeout_meta_days || '').replace(/\{n\}/g, String(n)) : '';
            }
            if (st.status === 'done') {
                ftStopPoll();
                workerSetState('takeout', null);
                ftWizardLocked = false;
                ftUpdateWizardUi();
                fitLastJobId = null;
                location.reload();
            } else if (st.status === 'awaiting_conflict') {
                ftStopPoll();
                const n = parseInt(st.conflict_days, 10) || 0;
                const q = (translations.takeout_conflict_question || 'Conflicts found for {n} days. Overwrite existing values?').replace(/\{n\}/g, String(n));
                showAlert(q, true, {
                    title: translations.conflict_title || 'Conflicts detected',
                    variant: 'warning',
                    okText: translations.takeout_overwrite || 'Overwrite',
                    cancelText: translations.cancel || 'Cancel',
                    extraText: translations.takeout_add_new_only || 'Add only new values'
                }).then(async (choice) => {
                    if (choice === true) {
                        await ftResolveConflict('overwrite', st.job_id || fitLastJobId);
                        return;
                    }
                    if (choice === 'extra') {
                        await ftResolveConflict('new_only', st.job_id || fitLastJobId);
                        return;
                    }
                    await ftStopJob();
                });
            } else if (st.status === 'error') {
                ftStopPoll();
                workerSetState('takeout', null);
                ftWizardLocked = false;
                ftUpdateWizardUi();
                fitLastJobId = null;
                let det = String(st.message || '');
                if (det === 'stale_job') det = translations.video_err_stale_job || det;
                else if (det === 'no step data in archive') det = translations.takeout_err_none || det;
                showAlert((translations.video_job_err || 'Error') + (det ? ': ' + det : ''));
            } else if (st.status === 'cancelled') {
                ftStopPoll();
                workerSetState('takeout', null);
                ftWizardLocked = false;
                ftUpdateWizardUi();
                fitLastJobId = null;
                const p = document.getElementById('ft-job-panel');
                if (p) p.style.display = 'none';
            }
        }
        function ftStartPoll(jid) {
            if (!FTO_API) return;
            fitLastJobId = jid;
            ftStopPoll();
            ftWizardLocked = true;
            ftUpdateWizardUi();
            const p = document.getElementById('ft-job-panel');
            if (p) p.style.display = 'block';
            const one = () => {
                fetch(FTO_API + '?action=job_status&job=' + encodeURIComponent(jid), { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(d => {
                        if (d && d.ok) ftOnState(d.state);
                    })
                    .catch(function(e) {});
            };
            one();
            fitPollTimer = setInterval(one, 400);
        }
        async function ftResumeFromAccountState() {
            if (!FTO_API) return false;
            let d = null;
            try {
                d = await fetch(FTO_API + '?action=active_job', { credentials: 'same-origin' }).then(r => r.json());
            } catch (e) {
                d = null;
            }
            const p = document.getElementById('ft-job-panel');
            if (d && d.ok && d.active && d.active.job_id) {
                fitLastJobId = d.active.job_id;
                if (p) p.style.display = 'block';
                ftWizardLocked = true;
                ftUpdateWizardUi();
                if (d.active.state) ftOnState(d.active.state);
                ftStartPoll(d.active.job_id);
                return true;
            }
            if (p) p.style.display = 'none';
            ftStopPoll();
            ftWizardLocked = false;
            ftUpdateWizardUi();
            fitLastJobId = null;
            return false;
        }
        function videoEscapeHtml(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
        }
        function videoCanonToDmy(canon) {
            const s0 = String(canon || '').trim().replace(/\u00A0/g, ' ').replace(/[\uFF0C\u201A\u060C]/g, ',');
            const x = /^([A-Za-z]{3,9})\s+(\d{1,2}),\s*(\d{4})/.exec(s0);
            if (!x) return '';
            const order = {'jan':0,'feb':1,'mar':2,'apr':3,'may':4,'jun':5,'jul':6,'aug':7,'sep':8,'oct':9,'nov':10,'dec':11};
            let k = x[1].toLowerCase().slice(0, 3);
            if (order[k] === undefined && x[1].toLowerCase().indexOf('sept') === 0) k = 'sep';
            const mi = order[k];
            if (mi == null) return '';
            const day = parseInt(x[2], 10);
            const y = parseInt(x[3], 10);
            if (day < 1 || day > 31 || !y) return '';
            return String(day).padStart(2, '0') + '-' + String(mi + 1).padStart(2, '0') + '-' + String(y % 100).padStart(2, '0');
        }
        function videoIsoLikeToDmy(s) {
            const x = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(s || '').replace(/\s/g, ''));
            if (!x) return '';
            const y = parseInt(x[1], 10), mo = parseInt(x[2], 10), dd = parseInt(x[3], 10);
            if (mo < 1 || mo > 12 || dd < 1 || dd > 31) return '';
            return String(dd).padStart(2, '0') + '-' + String(mo).padStart(2, '0') + '-' + String(y % 100).padStart(2, '0');
        }
        function videoResultRowResolvedDmy(r) {
            if (!r) return '';
            const d0 = String(r.dmy || '').trim();
            if (/^\d{2}-\d{2}-\d{2}$/.test(d0)) return d0;
            const c = videoCanonToDmy(r.date || '');
            if (c) return c;
            return videoIsoLikeToDmy(r.date || '');
        }
        function videoDmyToSortKey(d) {
            const raw = String(d || '').trim();
            if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw;
            const p = raw.split('-');
            if (p.length !== 3) return '';
            const yy = parseInt(p[2], 10);
            if (isNaN(yy)) return '';
            const cent = yy < 70 ? '20' : '19';
            return cent + p[2].padStart(2, '0') + '-' + p[1].padStart(2, '0') + '-' + p[0].padStart(2, '0');
        }
        function videoIsoToDmy(iso) {
            const p = String(iso).split('-');
            if (p.length !== 3) return '';
            return p[2] + '-' + p[1] + '-' + p[0].slice(2);
        }
        function sortPendingVideoRows() {
            pendingVideoRows.sort((a, b) => videoDmyToSortKey(a.date).localeCompare(videoDmyToSortKey(b.date)));
        }
        function renderVideoConfirmTableBody() {
            const wrap = document.getElementById('video-confirm-table-wrap');
            if (!wrap) return;
            sortPendingVideoRows();
            if (!pendingVideoRows.length) {
                wrap.innerHTML = '<p style="text-align:center;">' + (translations.video_no_data || 'No data') + '</p>';
                updateVideoConfirmGapsButton();
                return;
            }
            const dh = translations['date'] || 'Date';
            const thh = translations.video_col_time || 'Time';
            const sh = translations['steps'] || 'Steps';
            let h = '<table class="excel-table" id="video-confirm-table"><tr><th>' + videoEscapeHtml(dh) + '</th><th>' + videoEscapeHtml(thh) + '</th><th>' + videoEscapeHtml(sh) + '</th></tr>';
            pendingVideoRows.forEach((r) => {
                h += '<tr><td><input class="vcr-inp vcr-date" type="text" inputmode="numeric" value="' + videoEscapeHtml(r.date) + '" maxlength="8" style="min-width:7em;max-width:9em;box-sizing:border-box;padding:4px 6px;border:1px solid var(--border-color);border-radius:4px;background:var(--modal-bg);color:var(--text-color);"></td><td>' + videoEscapeHtml(r.t || '—') + '</td><td><input class="vcr-inp vcr-steps" type="text" inputmode="numeric" value="' + videoEscapeHtml(formatNumber(parseInt(r.steps, 10) || 0)) + '" style="min-width:5em;max-width:8em;box-sizing:border-box;padding:4px 6px;border:1px solid var(--border-color);border-radius:4px;background:var(--modal-bg);color:var(--text-color);"></td></tr>';
            });
            h += '</table>';
            wrap.innerHTML = h;
            updateVideoConfirmGapsButton();
        }
        function syncVideoConfirmFromInputs() {
            const wrap = document.getElementById('video-confirm-table-wrap');
            if (!wrap) return;
            const table = wrap.querySelector('table.excel-table');
            if (!table) return;
            const trs = table.querySelectorAll('tr');
            const byDate = new Map();
            for (let i = 1; i < trs.length; i++) {
                const tds = trs[i].querySelectorAll('td');
                if (tds.length < 3) continue;
                const di = tds[0].querySelector('.vcr-date');
                const si = tds[2].querySelector('.vcr-steps');
                if (!di || !si) continue;
                const d = (di.value || '').trim();
                const s = parseInt(parseStepsRaw(si.value), 10);
                if (!/^\d{2}-\d{2}-\d{2}$/.test(d) || isNaN(s) || s < 0) continue;
                const tc = (tds[1].textContent || '').trim();
                const t = tc === '—' ? '' : tc;
                byDate.set(d, { date: d, steps: s, t: t });
            }
            pendingVideoRows = Array.from(byDate.values());
            sortPendingVideoRows();
        }
        function refreshVideoConfirmView() {
            renderVideoConfirmTableBody();
            requestAnimationFrame(function() { renderVideoConfirmChart(pendingVideoRows); });
        }
        function updateVideoConfirmGapsButton() {
            const b = document.getElementById('video-confirm-gaps-btn');
            if (!b) return;
            b.style.display = pendingVideoRows.length ? 'inline-flex' : 'none';
        }
        function computeVideoGaps() {
            if (pendingVideoRows.length < 1) return [];
            sortPendingVideoRows();
            const first = pendingVideoRows[0];
            const last = pendingVideoRows[pendingVideoRows.length - 1];
            const iso0 = videoDmyToSortKey(first.date);
            const iso1 = videoDmyToSortKey(last.date);
            if (!iso0 || !iso1) return [];
            const have = new Set();
            pendingVideoRows.forEach((r) => {
                const k = videoDmyToSortKey(r.date);
                if (k) have.add(k);
            });
            const out = [];
            const cur = new Date(iso0 + 'T12:00:00');
            const end = new Date(iso1 + 'T12:00:00');
            while (cur <= end) {
                const iso = cur.toISOString().slice(0, 10);
                if (!have.has(iso)) {
                    out.push({ iso: iso, dmy: videoIsoToDmy(iso) });
                }
                cur.setDate(cur.getDate() + 1);
            }
            return out;
        }
        function openVideoGapsModal() {
            const gaps = computeVideoGaps();
            const body = document.getElementById('video-confirm-gaps-body');
            const lab = translations['date'] || 'Date';
            const sLab = translations['steps'] || 'Steps';
            if (!gaps.length) {
                body.innerHTML = '<p style="text-align:center;opacity:0.9;">' + videoEscapeHtml(translations.video_gaps_empty || 'No gaps') + '</p>';
            } else {
                let h = '<table class="excel-table"><tr><th>' + videoEscapeHtml(lab) + '</th><th>' + videoEscapeHtml(sLab) + '</th></tr>';
                gaps.forEach((g) => {
                    h += '<tr><td>' + videoEscapeHtml(g.dmy) + '</td><td><input type="text" class="vcr-gap-step" data-dmy="' + videoEscapeHtml(g.dmy) + '" inputmode="numeric" placeholder="" style="min-width:5em;max-width:8em;box-sizing:border-box;padding:4px 6px;border:1px solid var(--border-color);border-radius:4px;background:var(--modal-bg);color:var(--text-color);"></td></tr>';
                });
                h += '</table>';
                body.innerHTML = h;
            }
            const gm = document.getElementById('video-confirm-gaps-modal');
            if (gm) gm.style.display = 'flex';
        }
        function closeVideoGapsModal() {
            const gm = document.getElementById('video-confirm-gaps-modal');
            if (gm) gm.style.display = 'none';
        }
        let videoConfirmChart = null;
        function destroyVideoConfirmChart() {
            if (videoConfirmChart) {
                videoConfirmChart.destroy();
                videoConfirmChart = null;
            }
        }
        function renderVideoConfirmChart(rows) {
            destroyVideoConfirmChart();
            const cwrap = document.getElementById('video-confirm-chart-wrap');
            const canvas = document.getElementById('video-confirm-chart');
            if (!cwrap || !canvas || !rows || !rows.length) {
                if (cwrap) cwrap.style.display = 'none';
                return;
            }
            cwrap.style.display = 'block';
            const sorted = [...rows].sort((a, b) => videoDmyToSortKey(a.date).localeCompare(videoDmyToSortKey(b.date)));
            const labels = sorted.map(r => r.date);
            const data = sorted.map(r => r.steps);
            const isDarkTheme = '<?php echo $theme === 'dark' ? 'true' : 'false'; ?>' === 'true';
            const chartGridColor = isDarkTheme ? 'rgba(160, 160, 160, 0.45)' : 'rgba(0, 0, 0, 0.12)';
            const dlab = translations['date'] || 'Date';
            const slab = translations['steps'] || 'Steps';
            const barBg = createBarGradient(canvas);
            videoConfirmChart = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: { labels, datasets: [{ label: slab, data, backgroundColor: barBg, borderColor: 'rgba(0,123,255,1)', borderWidth: 1 }] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: 10 },
                    scales: {
                        y: { beginAtZero: true, grid: { color: chartGridColor }, border: { color: chartGridColor }, ticks: { color: isDarkTheme ? '#fff' : '#000' }, title: { display: true, text: slab, color: isDarkTheme ? '#fff' : '#000' } },
                        x: { grid: { color: chartGridColor }, border: { color: chartGridColor }, ticks: { color: isDarkTheme ? '#fff' : '#000' }, title: { display: true, text: dlab, color: isDarkTheme ? '#fff' : '#000' } }
                    },
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => `${slab}: ${formatNumber(Math.round(c.raw))}` } } }
                }
            });
        }
        async function runVideoCleanup() {
            const fd = new FormData();
            fd.append('cleanup_video_temp', '1');
            fd.append('job_id', pendingVideoCleanup.jobId || '');
            fd.append('output_basename', pendingVideoCleanup.outputBasename || '');
            try {
                await fetch('', { method: 'POST', body: fd });
            } catch (e) {}
            pendingVideoCleanup = { jobId: '', outputBasename: '' };
            pendingVideoRows = [];
            pendingVideoOcrPreview = [];
        }
        async function ftStopJob() {
            if (!fitLastJobId) return;
            const ok = await showAlert(translations.worker_stop_confirm || '', true, {
                title: translations.worker_stop_title || 'Stop processing?',
                variant: 'warning'
            });
            if (!ok) return;
            const fd = new FormData();
            fd.append('action', 'stop_job');
            fd.append('job', fitLastJobId);
            try {
                await fetch(FTO_API, { method: 'POST', body: fd, credentials: 'same-origin' });
            } catch (e) {}
            ftStopPoll();
            workerSetState('takeout', null);
            fitLastJobId = null;
            ftWizardLocked = false;
            ftUpdateWizardUi();
            const p = document.getElementById('ft-job-panel');
            if (p) p.style.display = 'none';
            const pb = document.getElementById('ft-progress-fill');
            if (pb) pb.style.width = '0%';
        }

        function renderVideoConfirmOcrBlock() {
            const obr = document.getElementById('video-confirm-ocr');
            const owrap = document.getElementById('video-confirm-ocr-wrap');
            const obody = document.getElementById('video-confirm-ocr-body');
            const obtn = document.getElementById('video-confirm-ocr-log-btn');
            if (!obr || !owrap) return;
            const list = pendingVideoOcrPreview || [];
            if (!list.length) {
                obr.textContent = '';
                owrap.style.display = 'none';
                if (obody) obody.style.display = 'none';
                if (obtn) { obtn.style.display = 'none'; obtn.setAttribute('aria-expanded', 'false'); }
                return;
            }
            owrap.style.display = '';
            if (obody) obody.style.display = 'none';
            if (obtn) {
                obtn.style.display = '';
                obtn.textContent = translations.video_ocr_open_log || 'Open log';
                obtn.setAttribute('aria-expanded', 'false');
            }
            obr.innerHTML = list.map(function(it) {
                const tm = it.t_display != null && String(it.t_display) !== '' ? String(it.t_display) : String(Math.round(Number(it.t_sec) * 1000) / 1000);
                const st = (it.steps !== null && it.steps !== undefined && String(it.steps) !== '') ? String(it.steps) : '—';
                const dt = (it.date !== null && it.date !== undefined && String(it.date) !== '') ? String(it.date) : '—';
                const raw = String(it.raw !== null && it.raw !== undefined ? it.raw : '');
                return '<div style="margin-bottom:12px;border-bottom:1px solid var(--border-color);padding-bottom:8px;"><div style="opacity:0.85;font-size:11px;margin-bottom:3px;"><b>' + videoEscapeHtml(tm) + '</b> · ' + videoEscapeHtml(dt) + ' · ' + videoEscapeHtml(translations.steps || 'Steps') + ': ' + videoEscapeHtml(st) + '</div><div>' + videoEscapeHtml(raw).replace(/\n/g, '<br>') + '</div></div>';
            }).join('');
        }
        function videoNotifUserKey() {
            return 'stepsVNotifZ_' + String(currentUser || 'u').replace(/[^a-z0-9_\-]/gi, '_');
        }
        function snoozeVideoNotifications12h() {
            try { localStorage.setItem(videoNotifUserKey(), String(Date.now() + 12 * 60 * 60 * 1000)); } catch (e) {}
            const n = document.getElementById('video-recognition-notification');
            if (n) n.style.display = 'none';
        }
        function computeStepsDataVersion() {
            let sum = 0, maxDate = '';
            stepsData.forEach(function(entry) {
                if (entry.date > maxDate) maxDate = entry.date;
                sum += parseInt(entry.steps, 10) || 0;
            });
            return String(stepsData.length) + '|' + maxDate + '|' + String(sum);
        }
        async function refreshStepsDataIfChanged() {
            if (stepsDataRefreshBusy || document.hidden || editingCell) return;
            stepsDataRefreshBusy = true;
            try {
                const fd = new FormData();
                fd.append('get_steps_updates', '1');
                fd.append('version', stepsDataVersion || computeStepsDataVersion());
                const data = await fetch('', { method: 'POST', body: fd, credentials: 'same-origin' }).then(r => r.json());
                if (data && data.status === 'success') {
                    stepsDataVersion = data.version || stepsDataVersion;
                    if (data.changed && Array.isArray(data.steps)) {
                        stepsData = data.steps;
                        renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                        updateCalendar(currentLocale);
                        updateStatsNotification();
                        updateGoalNotification();
                        hideEmptyDataTypeButtons();
                        if (document.getElementById('excel-table')) renderExcelTable(tablePage);
                    }
                }
            } catch (e) {
            } finally {
                stepsDataRefreshBusy = false;
            }
        }
        function startStepsDataRefreshLoop() {
            if (stepsDataRefreshTimer) clearInterval(stepsDataRefreshTimer);
            stepsDataVersion = computeStepsDataVersion();
            stepsDataRefreshTimer = setInterval(refreshStepsDataIfChanged, 60000);
        }

        // App initialization
        function initApp() {
            const paintChart = () => renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
            if ('requestIdleCallback' in window) requestIdleCallback(paintChart, {timeout: 1500});
            else setTimeout(paintChart, 300);
            updateCalendar(currentLocale);
            initCustomSelect('year-selector', translations.year || 'Year');
            initCustomSelect('month-selector', translations.month || 'Month');
            initModeSwitch();
            updateStatsNotification();
            updateGoalNotification();
            hideEmptyDataTypeButtons();
            workerStartRefreshLoop();
            startStepsDataRefreshLoop();
            document.addEventListener('visibilitychange', function() { if (!document.hidden) { workerRefreshStates(); refreshStepsDataIfChanged(); } });
            let chartLabelsHideState = window.matchMedia('(max-width: 768px)').matches;
            let adjacentResizeTimer = null;
            window.addEventListener('resize', function() {
                const state = window.matchMedia('(max-width: 768px)').matches;
                if (state !== chartLabelsHideState) {
                    chartLabelsHideState = state;
                    renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                }
                if (document.getElementById('add-steps-modal')?.style.display !== 'none') {
                    clearTimeout(adjacentResizeTimer);
                    adjacentResizeTimer = setTimeout(() => {
                        updateAdjacentDates(document.getElementById('selected-date-input').value, currentLocale);
                    }, 120);
                }
            });

            // Handlers for info button
            document.getElementById('info-button').addEventListener('click', () => {
                openModal('info-modal');
            });

            // Handlers for view buttons
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    currentView = btn.getAttribute('data-view');
                    renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                    syncCalendarSelectors();
                });
            });
            const statsButton = document.getElementById('stats-button');
            if (statsButton) {
                statsButton.addEventListener('click', () => {
                    statsButton.classList.add('active');
                    openModal('stats-modal');
                });
            }

            // Handlers for data type
            document.querySelectorAll('.data-type-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.data-type-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    currentDataType = btn.getAttribute('data-type');
                    renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                });
            });

            // Handlers for chart type
            document.querySelectorAll('.chart-type-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.chart-type-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    currentChartType = btn.getAttribute('data-type');
                    try { localStorage.setItem(chartTypeStorageKey(), currentChartType); } catch (e) {}
                    renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                });
            });
            const locationStatsApi = '/apps/steps/api/session_gateway.php?endpoint=geolocation_statistics';
            let locationStatsBusy = false;
            function locationStatText(key, fallback) {
                return (window.translations && translations[key]) || fallback;
            }
            function locationStatNumber(value, digits) {
                const number = Number(value || 0);
                return number.toLocaleString(currentLocale || 'en-US', { maximumFractionDigits: digits || 0 });
            }
            function locationStatDuration(seconds) {
                const h = Math.floor(Number(seconds || 0) / 3600), m = Math.round((Number(seconds || 0) % 3600) / 60);
                return h > 0 ? h + ' h ' + m + ' min' : m + ' min';
            }
            function locationStatEscape(text) {
                return String(text == null ? '' : text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }
            function getCountryFlag(nameOrCode) {
                const raw = String(nameOrCode || '').trim();
                if (!raw) return '🌐';
                const nameMap = {
                    'ukraine': 'UA', 'україна': 'UA', 'poland': 'PL', 'polska': 'PL', 'portugal': 'PT', 'peru': 'PE',
                    'united states': 'US', 'united states of america': 'US', 'usa': 'US', 'germany': 'DE', 'deutschland': 'DE',
                    'czechia': 'CZ', 'czech republic': 'CZ', 'česko': 'CZ', 'slovakia': 'SK', 'austria': 'AT',
                    'france': 'FR', 'spain': 'ES', 'italy': 'IT', 'united kingdom': 'GB', 'great britain': 'GB', 'uk': 'GB',
                    'netherlands': 'NL', 'belgium': 'BE', 'switzerland': 'CH', 'sweden': 'SE', 'norway': 'NO',
                    'denmark': 'DK', 'finland': 'FI', 'ireland': 'IE', 'hungary': 'HU', 'romania': 'RO',
                    'bulgaria': 'BG', 'greece': 'GR', 'turkey': 'TR', 'türkiye': 'TR', 'japan': 'JP', 'china': 'CN',
                    'canada': 'CA', 'brazil': 'BR', 'argentina': 'AR', 'mexico': 'MX', 'australia': 'AU',
                    'new zealand': 'NZ', 'israel': 'IL', 'india': 'IN', 'lithuania': 'LT', 'latvia': 'LV',
                    'estonia': 'EE', 'belarus': 'BY', 'moldova': 'MD', 'georgia': 'GE', 'armenia': 'AM',
                    'croatia': 'HR', 'slovenia': 'SI', 'serbia': 'RS', 'bosnia and herzegovina': 'BA', 'north macedonia': 'MK'
                };
                let code = raw.toUpperCase();
                if (!/^[A-Z]{2}$/.test(code)) code = nameMap[raw.toLowerCase()] || '';
                if (!/^[A-Z]{2}$/.test(code)) return '🌐';
                return String.fromCodePoint(...[...code].map(ch => 127397 + ch.charCodeAt(0)));
            }
            function ensureLocationTimezoneOption(select, value) {
                if (!select || !value) return;
                const exists = Array.from(select.options).some(opt => opt.value === value);
                if (!exists) {
                    const opt = document.createElement('option');
                    opt.value = value;
                    opt.textContent = value;
                    select.appendChild(opt);
                }
                select.value = value;
            }
            function initLocationTimezoneSelect() {
                const tzInput = document.getElementById('location-stats-tz');
                if (!tzInput || tzInput.dataset.initialized === '1') return tzInput;
                tzInput.dataset.initialized = '1';
                try {
                    const browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                    if (browserTz) ensureLocationTimezoneOption(tzInput, browserTz);
                } catch (e) {}
                return tzInput;
            }
            function renderLocationStatistics(data) {
                const status = document.getElementById('location-stats-status');
                const period = document.getElementById('location-stats-period');
                const cards = document.getElementById('location-stats-cards');
                const warnings = document.getElementById('location-stats-warnings');
                const details = document.getElementById('location-stats-details');
                const boundary = document.getElementById('location-stats-boundary');
                const assumptions = document.getElementById('location-stats-assumptions');
                if (!status || !cards || !details) return;
                const m = data.metrics || {};
                const coverage = data.coverage || {};
                const periodText = (data.range ? data.range.from + ' → ' + data.range.to + ' · ' : '') + (data.timezone || 'UTC') + (data.cached ? ' · ' + locationStatText('location_stats_cached', 'cached') : '');
                status.textContent = '';
                if (period) {
                    period.textContent = periodText;
                    period.hidden = !periodText;
                }
                const card = (label, value) => '<div class="location-card loc-kpi-tile"><span>' + locationStatEscape(label) + '</span><strong class="font-mono">' + locationStatEscape(value) + '</strong></div>';
                cards.innerHTML = [
                    card(locationStatText('location_stats_points', 'Points'), locationStatNumber(m.point_count)),
                    card(locationStatText('location_stats_distance', 'Distance'), locationStatNumber((m.route_distance_m || 0) / 1000, 1) + ' km'),
                    card(locationStatText('location_stats_active_days', 'Active days'), locationStatNumber(m.active_days)),
                    card(locationStatText('location_stats_visits', 'Visits'), locationStatNumber(m.visits)),
                    card(locationStatText('location_stats_countries', 'Countries'), locationStatNumber((m.countries || []).length)),
                    card(locationStatText('location_stats_cities', 'Cities'), locationStatNumber((m.cities || []).length)),
                    card(locationStatText('location_stats_dwell', 'Dwell time'), locationStatDuration(m.dwell_seconds)),
                    card(locationStatText('location_stats_places', 'Places'), locationStatNumber(m.places))
                ].join('');
                const residency = data.residency || {};
                const warningList = residency.warnings || [];
                warnings.innerHTML = warningList.length ? warningList.map(w => '<div class="location-warning"><i class="fas fa-triangle-exclamation"></i> ' + locationStatEscape(w.country_name || w.country_code) + ': ' + w.days + ' / ' + w.window_days + ' ' + locationStatText('location_stats_days', 'days') + ' (' + locationStatEscape(w.window_from) + ' → ' + locationStatEscape(w.window_to) + ')</div>').join('') : '';
                const topPlaces = data.most_visited_places || [];
                const topHtml = topPlaces.length
                    ? '<ul class="loc-bento-list">' + topPlaces.map(p => '<li><span class="loc-bento-list-name">' + locationStatEscape(p.name) + '</span><span class="font-mono">' + locationStatEscape(p.visits + ' × · ' + locationStatDuration(p.dwell_seconds)) + '</span></li>').join('') + '</ul>'
                    : '<div class="loc-empty-state"><span class="material-symbols-rounded" aria-hidden="true">location_off</span><strong>' + locationStatEscape(locationStatText('location_stats_none', 'No data yet')) + '</strong><span>' + locationStatEscape(locationStatText('location_stats_places_empty_hint', 'Confirm visits on the map to populate')) + '</span></div>';
                const countryRows = m.countries || [];
                const countriesHtml = countryRows.length
                    ? '<div class="loc-country-chips">' + countryRows.map(c => {
                        const label = c.name || c.code || '';
                        return '<span class="loc-country-chip"><span class="loc-country-flag" aria-hidden="true">' + getCountryFlag(c.code || c.name) + '</span><span>' + locationStatEscape(label) + '</span></span>';
                    }).join('') + '</div>'
                    : '<p class="loc-bento-muted">' + locationStatEscape(locationStatText('location_stats_boundary_missing', 'No boundary dataset imported yet.')) + '</p>';
                const accuracy = data.accuracy || {};
                const sourceRows = Object.entries((data.source_health || {}).sources || {}).map(([source, count]) => '<li><span>' + locationStatEscape(source) + '</span><span class="font-mono">' + locationStatNumber(count) + '</span></li>').join('');
                const monthRows = (data.monthly || []).slice(-6);
                const peakMonth = Math.max(1, ...monthRows.map(row => Number((row.metrics && row.metrics.route_distance_m) || 0)));
                const monthsHtml = monthRows.length
                    ? '<div class="loc-month-list">' + monthRows.map(row => {
                        const dist = Number((row.metrics && row.metrics.route_distance_m) || 0);
                        const pct = Math.max(4, Math.round((dist / peakMonth) * 100));
                        return '<div class="loc-month-row"><div class="loc-month-meta"><span class="font-mono">' + locationStatEscape(row.period) + '</span><span class="font-mono">' + locationStatNumber(dist / 1000, 1) + ' km</span><span class="loc-month-days font-mono">' + locationStatNumber(row.metrics.active_days) + ' d</span></div><div class="loc-month-track"><div class="loc-month-fill" style="width:' + pct + '%"></div></div></div>';
                    }).join('') + '</div>'
                    : '<p class="loc-bento-muted">' + locationStatEscape(locationStatText('location_stats_none', 'No data yet.')) + '</p>';
                const gaps = (coverage.gaps || []).slice(0, 12);
                const gapsHtml = gaps.length
                    ? '<div class="loc-gap-chips">' + gaps.map(g => '<span class="loc-gap-chip font-mono">' + locationStatEscape(g) + '</span>').join('') + '</div>'
                    : '<span class="loc-bento-muted">—</span>';
                const kv = (label, value, tone) => '<li><span>' + locationStatEscape(label) + '</span><span class="font-mono' + (tone ? ' ' + tone : '') + '">' + locationStatEscape(value) + '</span></li>';
                details.innerHTML =
                    '<div class="loc-bento-row loc-bento-2">'
                    + '<div class="location-detail-block loc-bento-card"><h4>' + locationStatEscape(locationStatText('location_stats_recent_months', 'Recent months')) + '</h4>' + monthsHtml + '</div>'
                    + '<div class="location-detail-block loc-bento-card"><h4>' + locationStatEscape(locationStatText('location_stats_coverage', 'Coverage & gaps')) + '</h4>'
                    + '<ul class="loc-bento-list">'
                    + kv(locationStatText('location_stats_days_with_data', 'Days with data'), locationStatNumber(coverage.days_with_data) + ' / ' + locationStatNumber(coverage.days_total))
                    + kv(locationStatText('location_stats_missing_days', 'Missing days'), locationStatNumber(coverage.missing_days), 'is-amber')
                    + '</ul>'
                    + '<div class="loc-gap-section"><span class="loc-gap-label">' + locationStatEscape(locationStatText('location_stats_gaps', 'Data gaps (latest)')) + '</span>' + gapsHtml + '</div>'
                    + '</div></div>'
                    + '<div class="loc-bento-row loc-bento-3">'
                    + '<div class="location-detail-block loc-bento-card"><h4>' + locationStatEscape(locationStatText('location_stats_accuracy', 'Accuracy & sources')) + '</h4><ul class="loc-bento-list">'
                    + kv(locationStatText('location_stats_accuracy_avg', 'Average accuracy'), (accuracy.avg_m === null || accuracy.avg_m === undefined ? '—' : locationStatNumber(accuracy.avg_m, 1) + ' m'))
                    + kv(locationStatText('location_stats_accuracy_missing', 'Missing accuracy'), locationStatNumber(accuracy.missing))
                    + '</ul><div class="loc-bento-divider"></div><ul class="loc-bento-list">' + (sourceRows || '<li><span>' + locationStatEscape(locationStatText('location_stats_sources', 'Sources')) + '</span><span>—</span></li>') + '</ul></div>'
                    + '<div class="location-detail-block loc-bento-card"><h4>' + locationStatEscape(locationStatText('location_stats_movement', 'Movement patterns')) + '</h4><ul class="loc-bento-list">'
                    + kv(locationStatText('location_stats_trips', 'Trips'), locationStatNumber((data.movement_patterns || {}).trips))
                    + kv(locationStatText('location_stats_avg_distance', 'Average trip distance'), locationStatNumber(((data.movement_patterns || {}).avg_distance_m || 0) / 1000, 1) + ' km')
                    + kv(locationStatText('location_stats_avg_duration', 'Average trip duration'), locationStatDuration((data.movement_patterns || {}).avg_duration_s))
                    + '</ul></div>'
                    + '<div class="location-detail-block loc-bento-card"><h4>' + locationStatEscape(locationStatText('location_stats_comparison', 'Previous period')) + '</h4><ul class="loc-bento-list">'
                    + kv(locationStatText('location_stats_distance', 'Distance'), locationStatNumber(((data.comparison || {}).metrics?.route_distance_m || 0) / 1000, 1) + ' km')
                    + kv(locationStatText('location_stats_active_days', 'Active days'), locationStatNumber((data.comparison || {}).metrics?.active_days))
                    + kv(locationStatText('location_stats_visits', 'Visits'), locationStatNumber((data.comparison || {}).metrics?.visits))
                    + '</ul></div></div>'
                    + '<div class="loc-bento-row loc-bento-countries">'
                    + '<div class="location-detail-block loc-bento-card loc-bento-span-7"><div class="loc-bento-card-head"><h4>' + locationStatEscape(locationStatText('location_stats_countries', 'Countries')) + '</h4><span class="loc-total-badge font-mono">' + locationStatNumber(countryRows.length) + ' ' + locationStatEscape(locationStatText('location_stats_visited', 'visited')) + '</span></div>' + countriesHtml + '</div>'
                    + '<div class="location-detail-block loc-bento-card loc-bento-span-5"><h4>' + locationStatEscape(locationStatText('location_stats_top_places', 'Most visited places')) + '</h4>' + topHtml + '</div>'
                    + '</div>';
                const countryDataset = (data.boundary || {}).country_dataset;
                if (boundary) boundary.textContent = countryDataset ? locationStatText('location_stats_boundary_version', 'Boundary dataset') + ': ' + countryDataset.version + ' (' + countryDataset.feature_count + ')' : ((data.boundary || {}).assumption || '');
                if (assumptions) assumptions.innerHTML = (data.assumptions || []).map(text => '<li>' + locationStatEscape(text) + '</li>').join('');
            }
            async function loadLocationStatistics() {
                const panel = document.getElementById('settings-panel-location');
                if (!panel) return;
                const tzInput = initLocationTimezoneSelect();
                const status = document.getElementById('location-stats-status');
                const tz = (tzInput && tzInput.value.trim()) || 'UTC';
                if (status) status.textContent = locationStatText('map_loading', 'Loading…');
                try {
                    const res = await fetch(locationStatsApi + '&tz=' + encodeURIComponent(tz), { credentials: 'same-origin' });
                    const data = await res.json();
                    if (!res.ok || !data.ok) throw new Error((data && data.error) || 'statistics_failed');
                    renderLocationStatistics(data);
                } catch (e) {
                    if (status) status.textContent = (e && e.message) || locationStatText('map_error', 'Could not load data.');
                }
            }
            document.getElementById('location-stats-refresh')?.addEventListener('click', async () => {
                if (locationStatsBusy) return;
                locationStatsBusy = true;
                const tzInput = initLocationTimezoneSelect();
                const status = document.getElementById('location-stats-status');
                const tz = (tzInput && tzInput.value.trim()) || 'UTC';
                if (status) status.textContent = locationStatText('map_loading', 'Loading…');
                try {
                    const res = await fetch(locationStatsApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'recompute', tz }) });
                    const data = await res.json();
                    if (!res.ok || !data.ok) throw new Error((data && data.error) || 'statistics_failed');
                    await loadLocationStatistics();
                } catch (e) {
                    if (status) status.textContent = (e && e.message) || locationStatText('map_error', 'Could not load data.');
                } finally { locationStatsBusy = false; }
            });
            document.getElementById('location-stats-tz')?.addEventListener('change', loadLocationStatistics);
            const locationImportsApi = '/apps/steps/api/session_gateway.php?endpoint=geolocation_imports';
            let locationImportsPoll = null;
            let locationImportsMaxBytes = 0;
            let locationImportsReady = new Set();
            function importText(key, fallback) { return (window.translations && translations[key]) || fallback; }
            function importStateClass(state) {
                if (state === 'ready') return 'is-ready';
                if (state === 'failed') return 'is-failed';
                if (state === 'deleted') return 'is-deleted';
                if (state === 'processing' || state === 'pending') return 'is-processing';
                return '';
            }
            function importStateLabel(state) {
                const labels = {
                    pending: importText('location_import_state_pending', 'queued'),
                    processing: importText('location_import_state_processing', 'importing'),
                    ready: importText('location_import_state_ready', 'ready'),
                    failed: importText('location_import_state_failed', 'failed'),
                    deleted: importText('location_import_state_deleted', 'deleted')
                };
                return labels[state] || state;
            }
            function renderImports(data) {
                const host = document.getElementById('location-import-history');
                if (!host) return;
                const imports = data.imports || [];
                locationImportsMaxBytes = Number(data.max_bytes || 0);
                host.innerHTML = '';
                if (!imports.length) {
                    const empty = document.createElement('p');
                    empty.className = 'location-hint';
                    empty.textContent = importText('location_import_none', 'No imports yet.');
                    host.appendChild(empty);
                    return;
                }
                imports.forEach(item => {
                    const card = document.createElement('div');
                    card.className = 'import-item';
                    const head = document.createElement('div');
                    head.className = 'import-item-head';
                    const name = document.createElement('strong');
                    const firstFile = (item.files || [])[0];
                    name.textContent = firstFile ? firstFile.filename : importText('location_import_file', 'Import');
                    const badge = document.createElement('span');
                    badge.className = 'import-state ' + importStateClass(item.state);
                    badge.textContent = importStateLabel(item.state);
                    head.appendChild(name); head.appendChild(badge);
                    const meta = document.createElement('div');
                    meta.className = 'import-item-meta';
                    const progress = item.progress || {};
                    const parts = [item.format || '', (progress.points || 0) + ' ' + importText('location_import_points', 'points')];
                    if (item.files && item.files.length) {
                        parts.push(item.files.map(f => f.state + ' · ' + (f.accepted_count || 0) + '+' + (f.duplicate_count || 0) + 'dup').join(', '));
                    }
                    if (progress.finished_at) parts.push(progress.finished_at);
                    meta.textContent = parts.filter(Boolean).join(' · ');
                    card.appendChild(head); card.appendChild(meta);
                    const errors = (item.errors && Object.keys(item.errors).length) ? JSON.stringify(item.errors) : '';
                    if (errors) { const error = document.createElement('div'); error.className = 'import-error'; error.textContent = errors; card.appendChild(error); }
                    const actions = document.createElement('div');
                    actions.className = 'import-item-actions';
                    if (item.state !== 'deleted' && item.state !== 'processing') {
                        const del = document.createElement('button');
                        del.type = 'button'; del.className = 'excel-btn'; del.dataset.importDelete = item.id;
                        del.innerHTML = '<i class="fas fa-trash-can"></i> ' + importText('location_import_remove', 'Remove imported data');
                        actions.appendChild(del);
                    }
                    const sourceAvailable = (item.files || []).some(f => f.source_available);
                    if (sourceAvailable && item.state !== 'deleted') {
                        const clean = document.createElement('button');
                        clean.type = 'button'; clean.className = 'excel-btn'; clean.dataset.importCleanup = item.id;
                        clean.innerHTML = '<i class="fas fa-file-circle-xmark"></i> ' + importText('location_import_delete_file', 'Delete source files');
                        actions.appendChild(clean);
                    }
                    card.appendChild(actions);
                    host.appendChild(card);
                });
            }
            async function loadImports() {
                const host = document.getElementById('location-import-history');
                if (!host) return;
                if (locationImportsPoll) { clearTimeout(locationImportsPoll); locationImportsPoll = null; }
                try {
                    const res = await fetch(locationImportsApi, { credentials: 'same-origin' });
                    const data = await res.json();
                    if (!res.ok || !data.ok) throw new Error((data && data.error) || 'import_list_failed');
                    renderImports(data);
                    const imports = data.imports || [];
                    const active = imports.some(item => item.state === 'pending' || item.state === 'processing');
                    // A worker finishes in the background, so the map has to learn
                    // about the new rows without waiting for a manual refresh.
                    const ready = new Set(imports.filter(item => item.state === 'ready').map(item => item.id));
                    let finished = false;
                    ready.forEach(id => { if (!locationImportsReady.has(id)) finished = true; });
                    locationImportsReady = ready;
                    if (finished && typeof window.stepsMapInvalidate === 'function') window.stepsMapInvalidate();
                    if (active) locationImportsPoll = setTimeout(loadImports, 4000);
                } catch (e) {
                    host.innerHTML = '<p class="import-error">' + ((e && e.message) || importText('map_error', 'Could not load data.')) + '</p>';
                }
            }
            async function importAction(action, id) {
                const status = document.getElementById('location-import-status');
                if (status) status.textContent = importText('map_loading', 'Loading…');
                try {
                    const res = await fetch(locationImportsApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action, id }) });
                    const data = await res.json();
                    if (!res.ok || !data.ok) throw new Error((data && data.error) || 'import_action_failed');
                    if (status) status.textContent = action === 'delete' ? (data.deleted_points + ' ' + importText('location_import_removed_points', 'points removed')) : importText('location_import_file_deleted', 'Source files deleted.');
                    if (action === 'delete' && typeof window.stepsMapInvalidate === 'function') window.stepsMapInvalidate();
                    await loadImports();
                } catch (e) {
                    if (status) status.textContent = (e && e.message) || importText('map_error', 'Could not load data.');
                }
            }
            document.getElementById('location-import-upload')?.addEventListener('click', async () => {
                const input = document.getElementById('location-import-file');
                const status = document.getElementById('location-import-status');
                if (!input || !input.files || !input.files[0]) {
                    if (status) status.textContent = importText('location_import_choose_file', 'Choose a file first.');
                    return;
                }
                const file = input.files[0];
                if (locationImportsMaxBytes > 0 && file.size > locationImportsMaxBytes) {
                    if (status) status.textContent = importText('location_import_too_large', 'This file is too large.');
                    return;
                }
                const body = new FormData();
                body.append('file', file);
                if (document.getElementById('location-import-delete-source')?.checked) body.append('delete_source', '1');
                if (status) status.textContent = importText('location_import_uploading', 'Uploading…');
                try {
                    const res = await fetch(locationImportsApi, { method: 'POST', credentials: 'same-origin', body });
                    const data = await res.json();
                    if (!res.ok || !data.ok) throw new Error((data && data.error) || 'upload_failed');
                    if (status) status.textContent = importText('location_import_queued', 'Queued for import.');
                    input.value = '';
                    await loadImports();
                } catch (e) {
                    if (status) status.textContent = (e && e.message) || importText('map_error', 'Could not load data.');
                }
            });
            document.getElementById('location-import-history')?.addEventListener('click', async (event) => {
                const del = event.target.closest('[data-import-delete]');
                if (del) {
                    const id = del.getAttribute('data-import-delete');
                    const message = importText('location_import_confirm_remove', 'Remove this import? Only points that no other source owns are deleted.');
                    let confirmed = false;
                    if (typeof window.showAlert === 'function') { try { confirmed = await window.showAlert(message, true); } catch (e) { confirmed = false; } }
                    else confirmed = window.confirm(message);
                    if (confirmed) await importAction('delete', id);
                    return;
                }
                const clean = event.target.closest('[data-import-cleanup]');
                if (clean) await importAction('cleanup_file', clean.getAttribute('data-import-cleanup'));
            });
            const placesApi = '/apps/steps/api/session_gateway.php?endpoint=places';
            const placePrefsApi = '/apps/steps/api/session_gateway.php?endpoint=geolocation_place_prefs';
            const geofencesApi = '/apps/steps/api/session_gateway.php?endpoint=geofences';
            const qualityApi = '/apps/steps/api/session_gateway.php?endpoint=geolocation_quality';
            function toolsText(key, fallback) { return (window.translations && translations[key]) || fallback; }
            async function toolsCall(url, body) {
                const res = await fetch(url, body === null ? { credentials: 'same-origin' } : { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
                const data = await res.json();
                if (!res.ok || !data.ok) throw new Error((data && data.error) || 'location_tools_failed');
                return data;
            }
            function renderToolItems(hostId, items, label, onDelete) {
                const host = document.getElementById(hostId);
                if (!host) return;
                host.innerHTML = '';
                if (!items.length) { host.textContent = toolsText('location_tools_empty', 'Nothing here yet.'); return; }
                items.forEach(item => {
                    const row = document.createElement('div');
                    row.className = 'location-tool-item';
                    const text = document.createElement('span');
                    text.textContent = label(item);
                    row.appendChild(text);
                    const button = document.createElement('button');
                    button.type = 'button'; button.className = 'excel-btn';
                    button.textContent = toolsText('location_tools_remove', 'Remove');
                    button.addEventListener('click', () => onDelete(item));
                    row.appendChild(button);
                    host.appendChild(row);
                });
            }
            function renderPlaceChips(tags) {
                const host = document.getElementById('place-chips');
                if (!host) return;
                const list = (Array.isArray(tags) && tags.length ? tags : ['home', 'work', 'gym', 'school', 'family', 'favorite']).map(t => typeof t === 'string' ? t : (t.tag || t.key || t.name || '')).filter(Boolean);
                host.innerHTML = '';
                list.forEach(tag => {
                    const b = document.createElement('button');
                    b.type = 'button'; b.className = 'place-chip'; b.textContent = toolsText('place_tag_' + tag, tag);
                    b.addEventListener('click', () => {
                        const tagEl = document.getElementById('place-tag'), nameEl = document.getElementById('place-name');
                        if (tagEl) tagEl.value = tag;
                        if (nameEl && !nameEl.value) nameEl.value = b.textContent;
                        host.querySelectorAll('.place-chip').forEach(x => x.classList.toggle('is-active', x === b));
                    });
                    host.appendChild(b);
                });
            }
            window.addEventListener('steps-places-changed', () => loadLocationTools());
            document.getElementById('place-pick')?.addEventListener('click', () => {
                if (!window.stepsPickOnMap) return;
                const lat = document.getElementById('place-lat'), lon = document.getElementById('place-lon'), rad = document.getElementById('place-radius');
                window.stepsPickOnMap({ lat: Number(lat.value) || null, lon: Number(lon.value) || null, radius: Number(rad.value) || 120, onPick: (la, lo) => { lat.value = la.toFixed(6); lon.value = lo.toFixed(6); } });
            });
            async function loadLocationTools() {
                const status = document.getElementById('location-tools-status');
                try {
                    const [quality, places, zones, placePrefs] = await Promise.all([toolsCall(qualityApi, null), toolsCall(placesApi, null), toolsCall(geofencesApi, null), toolsCall(placePrefsApi, null).catch(() => ({pending:0,last_run:null}))]);
                    const detectStatus = document.getElementById('location-places-detect-status');
                    if (detectStatus) {
                        const pending = Number(placePrefs.pending || 0);
                        const run = placePrefs.last_run;
                        detectStatus.textContent = pending
                            ? toolsText('location_places_detect_pending', '{n} visit(s) waiting for detection').replace('{n}', String(pending))
                            : (run ? toolsText('location_places_detect_idle', 'Up to date. Last run: {state}').replace('{state}', String(run.state || '')) : toolsText('location_places_detect_idle_empty', 'No pending visits.'));
                    }
                    const c = quality.counts || {};
                    const counts = document.getElementById('location-quality-counts');
                    if (counts) counts.textContent = ['accepted', 'flagged', 'suspect', 'rejected', 'excluded_by_user', 'private_redacted', 'not_enough_evidence', 'pending'].map(k => k + ': ' + (c[k] || 0)).join(' · ');
                    const settings = quality.settings || {};
                    const geometry = document.getElementById('location-geometry-mode');
                    const matchMode = document.getElementById('location-match-mode');
                    if (geometry && settings.geometry_mode) geometry.value = settings.geometry_mode;
                    if (matchMode && settings.mode) matchMode.value = settings.mode;
                    const matchSummary = quality.matching || {};
                    const summaryEl = document.getElementById('location-geometry-summary');
                    if (summaryEl) summaryEl.textContent = toolsText('location_match_summary', 'Matched segments') + ': ' + (matchSummary.matched || 0) + ' · ' + toolsText('location_match_abstained', 'raw fallback') + ': ' + (matchSummary.abstained || 0) + ' · ' + toolsText('location_match_high', 'high confidence') + ': ' + (matchSummary.high || 0);
                    renderQualityIssues(quality.issues || []);
                    renderCorrections(quality.corrections || []);
                    renderPlaceChips(places.builtin_tags || []);
                    renderToolItems('location-places-list', places.places || [], p => p.name + ' · ' + p.tag + ' · ' + Number(p.radius_m).toFixed(0) + ' m', async p => {
                        if (!window.confirm(toolsText('location_place_confirm', 'Delete this place?'))) return;
                        try { await toolsCall(placesApi, { action: 'delete', id: p.id }); await loadLocationTools(); } catch (e) { if (status) status.textContent = e.message; }
                    });
                    renderToolItems('location-geofences-list', (zones.geofences || []).filter(z => z.is_active), z => z.name + ' · ' + Number(z.radius_m).toFixed(0) + ' m', async z => {
                        if (!window.confirm(toolsText('location_geofence_confirm', 'Disable this geofence?'))) return;
                        try { await toolsCall(geofencesApi, { action: 'delete', id: z.id }); await loadLocationTools(); } catch (e) { if (status) status.textContent = e.message; }
                    });
                } catch (e) { if (status) status.textContent = (e && e.message) || 'location_tools_failed'; }
            }
            function renderQualityIssues(items) {
                const host = document.getElementById('location-quality-issues');
                if (!host) return;
                host.innerHTML = '';
                if (!items.length) { host.textContent = toolsText('location_quality_none', 'No quality issues in this range.'); return; }
                items.slice(0, 30).forEach(item => {
                    const row = document.createElement('div');
                    row.className = 'location-tool-item';
                    const text = document.createElement('span');
                    const when = item.source_at ? new Date(item.source_at).toLocaleString() : '';
                    const reasons = Array.isArray(item.reasons) && item.reasons.length ? ' · ' + item.reasons.join(', ') : '';
                    text.textContent = when + ' · ' + item.quality_state + reasons + (item.accuracy_m !== null && item.accuracy_m !== undefined ? ' · ' + Number(item.accuracy_m).toFixed(0) + ' m' : '');
                    row.appendChild(text);
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'excel-btn';
                    button.textContent = toolsText('location_quality_exclude', 'Exclude');
                    button.addEventListener('click', async () => {
                        const status = document.getElementById('location-tools-status');
                        try {
                            await toolsCall(qualityApi, { action: 'correct', scope_type: 'points', point_ids: [item.id], effects: ['exclude_display', 'exclude_analytics'], reason: 'quality_issue', note: '' });
                            if (status) status.textContent = toolsText('location_correction_saved', 'Correction saved.');
                            await loadLocationTools();
                        } catch (e) { if (status) status.textContent = e.message; }
                    });
                    row.appendChild(button);
                    host.appendChild(row);
                });
            }
            function renderCorrections(items) {
                const host = document.getElementById('location-corrections-list');
                if (!host) return;
                host.innerHTML = '';
                const active = items.filter(item => item.state === 'active');
                if (!active.length) { host.textContent = toolsText('location_corrections_none', 'No active corrections.'); return; }
                active.forEach(item => {
                    const row = document.createElement('div');
                    row.className = 'location-tool-item';
                    const text = document.createElement('span');
                    text.textContent = item.scope_type + ' · ' + (item.point_count || 0) + ' ' + toolsText('location_points_short', 'points') + ' · ' + (item.effects_json || []).join(', ');
                    row.appendChild(text);
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'excel-btn';
                    button.textContent = toolsText('location_correction_undo', 'Undo');
                    button.addEventListener('click', async () => {
                        const status = document.getElementById('location-tools-status');
                        try {
                            await toolsCall(qualityApi, { action: 'undo', id: item.id });
                            if (status) status.textContent = toolsText('location_correction_undone', 'Correction undone.');
                            await loadLocationTools();
                        } catch (e) { if (status) status.textContent = e.message; }
                    });
                    row.appendChild(button);
                    host.appendChild(row);
                });
            }
            document.getElementById('location-places-detect')?.addEventListener('click', async () => {
                const status = document.getElementById('location-tools-status');
                const detectStatus = document.getElementById('location-places-detect-status');
                try {
                    const data = await toolsCall(placePrefsApi, { action: 'reprocess' });
                    const queued = data.queued || {};
                    const msg = toolsText('location_places_detect_queued', 'Place detection queued for {n} visit(s).').replace('{n}', String(queued.visits ?? queued.pending ?? 0));
                    if (status) status.textContent = msg;
                    if (detectStatus) detectStatus.textContent = msg;
                    await loadLocationTools();
                } catch (e) { if (status) status.textContent = e.message; }
            });
            document.getElementById('location-quality-reprocess')?.addEventListener('click', async () => {
                const status = document.getElementById('location-tools-status');
                try { await toolsCall(qualityApi, { action: 'reprocess' }); if (status) status.textContent = toolsText('location_quality_queued', 'Quality recheck queued.'); }
                catch (e) { if (status) status.textContent = e.message; }
            });
            document.getElementById('location-match-save')?.addEventListener('click', async () => {
                const status = document.getElementById('location-tools-status');
                try {
                    const data = await toolsCall(qualityApi, { action: 'settings', mode: document.getElementById('location-match-mode')?.value || 'conservative', geometry_mode: document.getElementById('location-geometry-mode')?.value || 'raw' });
                    if (status) status.textContent = toolsText('location_match_saved', 'Display settings saved.');
                    await loadLocationTools();
                    return data;
                } catch (e) { if (status) status.textContent = e.message; }
            });
            document.getElementById('location-match-run')?.addEventListener('click', async () => {
                const status = document.getElementById('location-tools-status');
                try { await toolsCall(qualityApi, { action: 'match' }); if (status) status.textContent = toolsText('location_match_queued', 'Geometry recomputation queued.'); }
                catch (e) { if (status) status.textContent = e.message; }
            });
            document.getElementById('location-correction-apply')?.addEventListener('click', async () => {
                const status = document.getElementById('location-tools-status');
                const from = document.getElementById('location-correction-from')?.value || '';
                const to = document.getElementById('location-correction-to')?.value || '';
                const effects = Array.from(document.querySelectorAll('#location-correction-effects input:checked')).map(el => el.value);
                if (!from || !to || !effects.length) { if (status) status.textContent = toolsText('location_correction_invalid', 'Choose a date range and at least one effect.'); return; }
                try {
                    await toolsCall(qualityApi, { action: 'correct', scope_type: 'range', from: from + 'T00:00:00Z', to: to + 'T23:59:59Z', effects, reason: document.getElementById('location-correction-reason')?.value || 'user_correction', note: document.getElementById('location-correction-note')?.value || '' });
                    if (status) status.textContent = toolsText('location_correction_saved', 'Correction saved.');
                    await loadLocationTools();
                } catch (e) { if (status) status.textContent = e.message; }
            });
            document.getElementById('place-create')?.addEventListener('click', async () => {
                const status = document.getElementById('location-tools-status');
                try {
                    await toolsCall(placesApi, { action: 'create', name: document.getElementById('place-name')?.value || '', tag: document.getElementById('place-tag')?.value || 'favorite', latitude: Number(document.getElementById('place-lat')?.value), longitude: Number(document.getElementById('place-lon')?.value), radius_m: Number(document.getElementById('place-radius')?.value || 120) });
                    if (status) status.textContent = toolsText('location_place_saved', 'Place saved.'); await loadLocationTools();
                } catch (e) { if (status) status.textContent = e.message; }
            });
            document.getElementById('geofence-create')?.addEventListener('click', async () => {
                const status = document.getElementById('location-tools-status');
                try {
                    await toolsCall(geofencesApi, { action: 'create', name: document.getElementById('geofence-name')?.value || '', latitude: Number(document.getElementById('geofence-lat')?.value), longitude: Number(document.getElementById('geofence-lon')?.value), radius_m: Number(document.getElementById('geofence-radius')?.value || 200), notify: true });
                    if (status) status.textContent = toolsText('location_geofence_saved', 'Geofence saved.'); await loadLocationTools();
                } catch (e) { if (status) status.textContent = e.message; }
            });
            const searchApi = '/apps/steps/api/session_gateway.php?endpoint=geolocation_search';
            const savedViewsApi = '/apps/steps/api/session_gateway.php?endpoint=geolocation_saved_views';
            const analyticsApi = '/apps/steps/api/session_gateway.php?endpoint=geolocation_analytics';
            let locationSearchRange = null;
            let locationSearchTimer = null;
            function locationSearchText(key, fallback) { return (window.translations && translations[key]) || fallback; }
            function locationSearchResultRow(item) {
                const row = document.createElement('div');
                row.className = 'location-tool-item';
                const text = document.createElement('span');
                const when = item.started_at ? new Date(item.started_at).toLocaleString() : '';
                text.textContent = item.type + ' · ' + item.title + (item.summary ? ' · ' + item.summary : '') + (when ? ' · ' + when : '');
                row.appendChild(text);
                return row;
            }
            async function runLocationSearch() {
                const status = document.getElementById('location-search-range');
                const resultsHost = document.getElementById('location-search-results');
                const suggestionsHost = document.getElementById('location-search-suggestions');
                const q = document.getElementById('location-search-q')?.value || '';
                const type = document.getElementById('location-search-type')?.value || '';
                const params = new URLSearchParams({ q, limit: '30' });
                if (type) params.set('types', type);
                if (locationSearchRange) { params.set('from', locationSearchRange.from); params.set('to', locationSearchRange.to); }
                try {
                    const data = await toolsCall(searchApi + '&' + params.toString(), null);
                    if (status) status.textContent = (data.range && data.range.label ? data.range.label : '') + ' · ' + (data.range && data.range.timezone ? data.range.timezone : '');
                    if (suggestionsHost) suggestionsHost.textContent = (data.suggestions || []).join(' · ');
                    if (resultsHost) {
                        resultsHost.innerHTML = '';
                        if (!(data.results || []).length) resultsHost.textContent = locationSearchText('location_search_empty', 'No authorized matches.');
                        else (data.results || []).forEach(item => resultsHost.appendChild(locationSearchResultRow(item)));
                    }
                } catch (e) {
                    if (status) status.textContent = e.message;
                }
            }
            async function loadSavedViews() {
                const host = document.getElementById('location-saved-views');
                if (!host) return;
                try {
                    const data = await toolsCall(savedViewsApi, null);
                    host.innerHTML = '';
                    if (!(data.views || []).length) { host.textContent = locationSearchText('location_views_empty', 'No saved views yet.'); return; }
                    (data.views || []).forEach(view => {
                        const row = document.createElement('div');
                        row.className = 'location-tool-item';
                        const text = document.createElement('span');
                        text.textContent = (view.is_pinned ? '★ ' : '') + view.name + ' · ' + view.context + ' · ' + view.date_period;
                        row.appendChild(text);
                        const apply = document.createElement('button');
                        apply.type = 'button'; apply.className = 'excel-btn'; apply.textContent = locationSearchText('location_view_apply', 'Apply');
                        apply.addEventListener('click', async () => {
                            try {
                                const applied = await toolsCall(savedViewsApi + '&id=' + encodeURIComponent(view.id) + '&apply=1&tz=' + encodeURIComponent(Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'), null);
                                locationSearchRange = { from: applied.range.from, to: applied.range.to };
                                const q = document.getElementById('location-search-q');
                                if (q) q.value = (applied.view.query && applied.view.query.q) || '';
                                await runLocationSearch();
                            } catch (e) { const s = document.getElementById('location-tools-status'); if (s) s.textContent = e.message; }
                        });
                        row.appendChild(apply);
                        const pin = document.createElement('button');
                        pin.type = 'button'; pin.className = 'excel-btn'; pin.textContent = view.is_pinned ? locationSearchText('location_view_unpin', 'Unpin') : locationSearchText('location_view_pin', 'Pin');
                        pin.addEventListener('click', async () => { try { await toolsCall(savedViewsApi, { action: 'pin', id: view.id }); await loadSavedViews(); } catch (e) { const s = document.getElementById('location-tools-status'); if (s) s.textContent = e.message; } });
                        row.appendChild(pin);
                        const del = document.createElement('button');
                        del.type = 'button'; del.className = 'excel-btn'; del.textContent = locationSearchText('location_view_delete', 'Delete');
                        del.addEventListener('click', async () => {
                            if (!window.confirm(locationSearchText('location_view_confirm_delete', 'Delete this saved view?'))) return;
                            try { await toolsCall(savedViewsApi, { action: 'delete', id: view.id }); await loadSavedViews(); } catch (e) { const s = document.getElementById('location-tools-status'); if (s) s.textContent = e.message; }
                        });
                        row.appendChild(del);
                        host.appendChild(row);
                    });
                } catch (e) { host.textContent = e.message; }
            }
            async function loadAnalytics(refresh = false) {
                const status = document.getElementById('location-analytics-status');
                const cards = document.getElementById('location-analytics-cards');
                const coverage = document.getElementById('location-analytics-coverage');
                const places = document.getElementById('location-analytics-places');
                const compare = document.getElementById('location-analytics-compare')?.checked ? '&compare=1' : '';
                try {
                    const data = await toolsCall(analyticsApi + '?from=' + encodeURIComponent(locationSearchRange?.from || '') + '&to=' + encodeURIComponent(locationSearchRange?.to || '') + '&tz=' + encodeURIComponent(Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC') + compare + (refresh ? '&refresh=1' : ''), null);
                    if (status) status.textContent = (data.range?.label || '') + ' · ' + (data.cached ? 'cached' : 'fresh') + ' · ' + (data.formula_version || '');
                    const metrics = data.metrics || {};
                    if (cards) {
                        cards.innerHTML = '';
                        [['distance_m', 'Distance', 'm'], ['trip_count', 'Trips', ''], ['active_days', 'Active days', ''], ['unique_places', 'Places', ''], ['new_places', 'New places', ''], ['geofence_events', 'Geofence events', '']].forEach(([key, label, unit]) => {
                            const card = document.createElement('div'); card.className = 'location-card';
                            card.textContent = label + ': ' + (metrics[key] ?? 0) + (unit ? ' ' + unit : '');
                            cards.appendChild(card);
                        });
                    }
                    if (coverage) {
                        const c = data.coverage || {}; const q = data.quality || {};
                        coverage.textContent = locationSearchText('location_analytics_coverage', 'Coverage') + ': ' + (c.quality_percent ?? 0) + '% · ' + (c.confidence || 'low') + ' · ' + (locationSearchText('location_analytics_gap', 'largest gap') + ': ' + Math.round((c.largest_gap_s || 0) / 60) + ' min') + ' · ' + (q.accepted || 0) + ' accepted / ' + (q.rejected || 0) + ' rejected';
                    }
                    if (places) {
                        places.innerHTML = '';
                        (data.places || []).slice(0, 5).forEach(p => { const row = document.createElement('div'); row.className = 'location-tool-item'; row.textContent = p.name + ' · ' + p.visits + ' visits'; places.appendChild(row); });
                    }
                    if (Array.isArray(data.warnings) && data.warnings.length && coverage) coverage.textContent += ' · ' + data.warnings.join(' · ');
                } catch (e) { if (status) status.textContent = e.message; }
            }
            document.getElementById('location-search-run')?.addEventListener('click', runLocationSearch);
            document.getElementById('location-search-q')?.addEventListener('input', () => { clearTimeout(locationSearchTimer); locationSearchTimer = setTimeout(runLocationSearch, 350); });
            document.getElementById('location-view-save')?.addEventListener('click', async () => {
                const name = document.getElementById('location-view-name')?.value || '';
                if (!name.trim()) return;
                try {
                    await toolsCall(savedViewsApi, { action: 'create', name, context: document.getElementById('location-view-context')?.value || 'dashboard', date_period: document.getElementById('location-view-period')?.value || 'last_30_days', timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC', query: { q: document.getElementById('location-search-q')?.value || '', types: document.getElementById('location-search-type')?.value ? [document.getElementById('location-search-type').value] : [] } });
                    await loadSavedViews();
                } catch (e) { const s = document.getElementById('location-tools-status'); if (s) s.textContent = e.message; }
            });
            document.getElementById('location-analytics-run')?.addEventListener('click', () => loadAnalytics(true));
            const immichApi = '/apps/steps/api/session_gateway.php?endpoint=immich';
            let immichGalleryPoll = null;
            function immichText(key, fallback) { return (window.translations && translations[key]) || fallback; }
            function immichThumb(assetId) { return '/apps/steps/api/session_gateway.php?endpoint=immich_thumbnail&id=' + encodeURIComponent(assetId); }
            function renderImmich(data) {
                const host = document.getElementById('immich-gallery');
                const status = document.getElementById('immich-status');
                if (!host) return;
                host.innerHTML = '';
                const connection = data.connection;
                if (!connection || !connection.connected) {
                    if (status) status.textContent = immichText('immich_not_connected', 'Immich is not connected.');
                    return;
                }
                if (status) status.textContent = immichText('immich_connected', 'Connected. Last sync:') + ' ' + String(connection.last_sync_at || '—').slice(0, 19).replace('T', ' ');
                const gallery = data.gallery || {};
                const assets = gallery.assets || [];
                if (!assets.length) {
                    const empty = document.createElement('p');
                    empty.className = 'location-hint';
                    empty.textContent = immichText('immich_none', 'No matched photos yet. Run a metadata sync.');
                    host.appendChild(empty);
                    return;
                }
                const summary = document.createElement('p');
                summary.className = 'location-hint';
                const placeText = Object.keys(gallery.places || {}).slice(0, 4).map(name => name + ' (' + gallery.places[name] + ')').join(', ');
                summary.textContent = (placeText ? immichText('immich_places', 'Places') + ': ' + placeText : '') + (Object.keys(gallery.trips || {}).length ? ' · ' + immichText('immich_trips', 'Trips') + ': ' + Object.keys(gallery.trips).slice(0, 4).join(', ') : '');
                host.appendChild(summary);
                const grid = document.createElement('div');
                grid.className = 'immich-gallery';
                assets.forEach(asset => {
                    const card = document.createElement('div');
                    card.className = 'immich-card';
                    const img = document.createElement('img');
                    img.loading = 'lazy'; img.alt = asset.original_filename || ''; img.src = immichThumb(asset.id);
                    card.appendChild(img);
                    const caption = document.createElement('span');
                    caption.textContent = [String(asset.captured_at || '').slice(0, 16).replace('T', ' '), asset.place_name, asset.trip_title].filter(Boolean).join(' · ');
                    card.appendChild(caption);
                    const candidate = (asset.match_json || {}).point_id;
                    if (asset.state === 'unmatched' && candidate) {
                        const write = document.createElement('button');
                        write.type = 'button'; write.className = 'excel-btn'; write.dataset.immichWriteGps = asset.id; write.dataset.pointId = candidate;
                        write.textContent = immichText('immich_write_gps', 'Write GPS');
                        card.appendChild(write);
                    }
                    grid.appendChild(card);
                });
                host.appendChild(grid);
            }
            async function loadImmich() {
                const host = document.getElementById('immich-gallery');
                if (!host) return;
                if (immichGalleryPoll) { clearTimeout(immichGalleryPoll); immichGalleryPoll = null; }
                try {
                    const res = await fetch(immichApi, { credentials: 'same-origin' });
                    const data = await res.json();
                    if (!res.ok || !data.ok) throw new Error((data && data.error) || 'immich_failed');
                    renderImmich(data);
                    if (data.connection && data.connection.connected && (data.connection.last_sync_at === null)) immichGalleryPoll = setTimeout(loadImmich, 5000);
                } catch (e) {
                    host.innerHTML = '<p class="import-error">' + ((e && e.message) || 'immich_failed') + '</p>';
                }
            }
            async function immichAction(action, extra) {
                const status = document.getElementById('immich-status');
                if (status) status.textContent = immichText('map_loading', 'Loading…');
                const res = await fetch(immichApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.assign({ action }, extra || {})) });
                const data = await res.json();
                if (!res.ok || !data.ok) throw new Error((data && data.error) || 'immich_failed');
                await loadImmich();
                return data;
            }
            document.getElementById('immich-connect')?.addEventListener('click', async () => {
                const status = document.getElementById('immich-status');
                try {
                    await immichAction('connect', { server_url: document.getElementById('immich-server')?.value || '', api_key: document.getElementById('immich-api-key')?.value || '' });
                    const key = document.getElementById('immich-api-key'); if (key) key.value = '';
                    if (status) status.textContent = immichText('immich_saved', 'Immich connection saved.');
                } catch (e) { if (status) status.textContent = (e && e.message) || 'immich_failed'; }
            });
            document.getElementById('immich-test')?.addEventListener('click', async () => {
                const status = document.getElementById('immich-status');
                try { const data = await immichAction('test', { server_url: document.getElementById('immich-server')?.value || '', api_key: document.getElementById('immich-api-key')?.value || '' }); if (status) status.textContent = immichText('immich_test_ok', 'Connection works:') + ' ' + (data.server || 'Immich'); }
                catch (e) { if (status) status.textContent = (e && e.message) || 'immich_failed'; }
            });
            document.getElementById('immich-sync')?.addEventListener('click', async () => {
                const status = document.getElementById('immich-status');
                try { await immichAction('sync', {}); if (status) status.textContent = immichText('immich_sync_queued', 'Metadata sync queued. Photos will appear shortly.'); immichGalleryPoll = setTimeout(loadImmich, 5000); }
                catch (e) { if (status) status.textContent = (e && e.message) || 'immich_failed'; }
            });
            document.getElementById('immich-disconnect')?.addEventListener('click', async () => {
                const status = document.getElementById('immich-status');
                if (!window.confirm(immichText('immich_confirm_disconnect', 'Disconnect Immich and delete local photo metadata? Immich itself is not changed.'))) return;
                try { await immichAction('disconnect', {}); if (status) status.textContent = immichText('immich_disconnected', 'Immich disconnected.'); }
                catch (e) { if (status) status.textContent = (e && e.message) || 'immich_failed'; }
            });
            document.getElementById('immich-gallery')?.addEventListener('click', async (event) => {
                const button = event.target.closest('[data-immich-write-gps]');
                if (!button) return;
                if (!window.confirm(immichText('immich_confirm_write', 'Write this location to Immich? This changes the photo metadata in Immich.'))) return;
                const status = document.getElementById('immich-status');
                try { await immichAction('write_gps', { asset_id: button.dataset.immichWriteGps, point_id: button.dataset.pointId, confirm: true }); if (status) status.textContent = immichText('immich_gps_written', 'GPS metadata written to Immich.'); }
                catch (e) { if (status) status.textContent = (e && e.message) || 'immich_failed'; }
            });
            const familyApi = '/apps/steps/api/session_gateway.php?endpoint=family';
            function familyText(key, fallback) { return (window.translations && translations[key]) || fallback; }
            async function familyRequest(body) {
                const res = await fetch(familyApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
                const data = await res.json();
                if (!res.ok || !data.ok) throw new Error((data && data.error) || 'family_update_failed');
                await loadFamily();
                return data;
            }
            function familyButton(label, action, attrs, className) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = className || 'excel-btn';
                button.dataset.familyAction = action;
                Object.entries(attrs || {}).forEach(([key, value]) => button.dataset[key] = value);
                button.textContent = label;
                return button;
            }
            function renderFamily(data) {
                const groupsHost = document.getElementById('family-groups');
                const pendingHost = document.getElementById('family-pending');
                if (!groupsHost || !pendingHost) return;
                groupsHost.innerHTML = '';
                pendingHost.innerHTML = '';
                const pending = data.pending_invitations || [];
                if (pending.length) {
                    const heading = document.createElement('p');
                    heading.className = 'location-hint';
                    heading.textContent = familyText('family_pending_invites', 'Invitations waiting for you') + ':';
                    pendingHost.appendChild(heading);
                    pending.forEach(invite => {
                        const card = document.createElement('div');
                        card.className = 'import-item';
                        const line = document.createElement('div');
                        line.textContent = (invite.group_name || '') + ' · ' + invite.inviter_email + ' · ' + String(invite.expires_at || '').slice(0, 10);
                        card.appendChild(line);
                        const hint = document.createElement('div');
                        hint.className = 'import-item-meta';
                        hint.textContent = familyText('family_pending_hint', 'Use the invitation token from the person who invited you.');
                        card.appendChild(hint);
                        pendingHost.appendChild(card);
                    });
                }
                const groups = data.groups || [];
                if (!groups.length) {
                    const empty = document.createElement('p');
                    empty.className = 'location-hint';
                    empty.textContent = familyText('family_none', 'No family groups yet.');
                    groupsHost.appendChild(empty);
                    return;
                }
                groups.forEach(group => {
                    const card = document.createElement('div');
                    card.className = 'family-group';
                    const head = document.createElement('div');
                    head.className = 'import-item-head';
                    const title = document.createElement('strong');
                    title.textContent = group.name + ' · ' + group.member_count + '/' + group.max_members + ' · ' + group.role;
                    head.appendChild(title);
                    const actions = document.createElement('div');
                    actions.className = 'import-item-actions';
                    if (group.role === 'owner' || group.role === 'admin') {
                        actions.appendChild(familyButton(familyText('family_rename', 'Rename'), 'rename', { groupId: group.id, groupName: group.name }));
                    }
                    if (group.role !== 'owner') {
                        actions.appendChild(familyButton(familyText('family_leave', 'Leave group'), 'leave', { groupId: group.id }));
                    }
                    head.appendChild(actions);
                    card.appendChild(head);
                    (group.members || []).forEach(member => {
                        const row = document.createElement('div');
                        row.className = 'family-member';
                        const label = document.createElement('span');
                        label.textContent = member.user_email + (member.user_email === group.owner_email ? ' · ' + familyText('family_owner', 'owner') : ' · ' + member.role);
                        row.appendChild(label);
                        const rowActions = document.createElement('div');
                        rowActions.className = 'import-item-actions';
                        if (group.role === 'owner' && member.user_email !== group.owner_email) {
                            const select = document.createElement('select');
                            ['admin', 'member'].forEach(role => { const option = document.createElement('option'); option.value = role; option.textContent = role; option.selected = member.role === role; select.appendChild(option); });
                            select.dataset.familyRoleSelect = member.user_email;
                            select.dataset.groupId = group.id;
                            rowActions.appendChild(select);
                            rowActions.appendChild(familyButton(familyText('family_apply_role', 'Apply role'), 'role', { groupId: group.id, email: member.user_email }));
                            rowActions.appendChild(familyButton(familyText('family_transfer', 'Make owner'), 'transfer', { groupId: group.id, email: member.user_email }));
                        }
                        if (group.role !== 'member' && member.user_email !== group.owner_email && (group.role === 'owner' || member.role !== 'admin')) {
                            rowActions.appendChild(familyButton(familyText('family_remove', 'Remove'), 'remove', { groupId: group.id, email: member.user_email }));
                        }
                        row.appendChild(rowActions);
                        card.appendChild(row);
                    });
                    if (group.role === 'owner' || group.role === 'admin') {
                        const form = document.createElement('div');
                        form.className = 'family-invite-form';
                        const email = document.createElement('input');
                        email.type = 'email'; email.placeholder = familyText('family_email', 'person@example.com'); email.dataset.familyInviteEmail = '1';
                        email.dataset.groupId = group.id;
                        const role = document.createElement('select');
                        ['member', 'admin'].forEach(value => { const option = document.createElement('option'); option.value = value; option.textContent = value; if (value === 'admin' && group.role !== 'owner') option.disabled = true; role.appendChild(option); });
                        role.dataset.familyInviteRole = '1'; role.dataset.groupId = group.id;
                        const invite = familyButton(familyText('family_invite', 'Invite'), 'invite', { groupId: group.id });
                        form.appendChild(email); form.appendChild(role); form.appendChild(invite);
                        card.appendChild(form);
                        const invitations = group.invitations || [];
                        invitations.forEach(invitation => {
                            const inviteRow = document.createElement('div');
                            inviteRow.className = 'family-member';
                            const text = document.createElement('span');
                            text.textContent = invitation.invitee_email + ' · ' + invitation.role + ' · ' + String(invitation.expires_at || '').slice(0, 10);
                            inviteRow.appendChild(text);
                            inviteRow.appendChild(familyButton(familyText('family_cancel_invite', 'Cancel invite'), 'cancel_invitation', { invitationId: invitation.id }));
                            card.appendChild(inviteRow);
                        });
                    }
                    groupsHost.appendChild(card);
                });
            }
            async function loadFamily() {
                const host = document.getElementById('family-groups');
                if (!host) return;
                try {
                    const res = await fetch(familyApi, { credentials: 'same-origin' });
                    const data = await res.json();
                    if (!res.ok || !data.ok) throw new Error((data && data.error) || 'family_list_failed');
                    renderFamily(data);
                } catch (e) {
                    host.innerHTML = '<p class="import-error">' + ((e && e.message) || 'family_list_failed') + '</p>';
                }
            }
            document.getElementById('family-create')?.addEventListener('click', async () => {
                const input = document.getElementById('family-new-name');
                const status = document.getElementById('family-status');
                try { await familyRequest({ action: 'create', name: input ? input.value : '' }); if (input) input.value = ''; if (status) status.textContent = familyText('family_created', 'Group created.'); }
                catch (e) { if (status) status.textContent = (e && e.message) || 'family_create_failed'; }
            });
            document.getElementById('family-accept')?.addEventListener('click', async () => {
                const input = document.getElementById('family-token');
                const status = document.getElementById('family-status');
                try { await familyRequest({ action: 'accept', token: input ? input.value : '' }); if (input) input.value = ''; if (status) status.textContent = familyText('family_joined', 'You joined the group.'); }
                catch (e) { if (status) status.textContent = (e && e.message) || 'family_join_failed'; }
            });
            document.getElementById('family-groups')?.addEventListener('click', async (event) => {
                const button = event.target.closest('[data-family-action]');
                if (!button) return;
                const action = button.dataset.familyAction;
                const groupId = button.dataset.groupId || '';
                const status = document.getElementById('family-status');
                try {
                    if (action === 'invite') {
                        const group = button.closest('.family-group');
                        const email = group?.querySelector('[data-family-invite-email]')?.value || '';
                        const role = group?.querySelector('[data-family-invite-role]')?.value || 'member';
                        const data = await familyRequest({ action: 'invite', group_id: groupId, email, role });
                        if (status) status.textContent = familyText('family_invite_created', 'Invitation token (share privately):') + ' ' + (data.token || '');
                    } else if (action === 'remove') {
                        await familyRequest({ action: 'remove_member', group_id: groupId, email: button.dataset.email || '' });
                        if (status) status.textContent = familyText('family_removed', 'Member removed.');
                    } else if (action === 'leave') {
                        await familyRequest({ action: 'leave', group_id: groupId });
                        if (status) status.textContent = familyText('family_left', 'You left the group.');
                    } else if (action === 'cancel_invitation') {
                        await familyRequest({ action: 'cancel_invitation', invitation_id: button.dataset.invitationId || '' });
                        if (status) status.textContent = familyText('family_invite_cancelled', 'Invitation cancelled.');
                    } else if (action === 'role') {
                        const select = button.closest('.family-member')?.querySelector('[data-family-role-select]');
                        await familyRequest({ action: 'role', group_id: groupId, email: button.dataset.email || '', role: select ? select.value : 'member' });
                        if (status) status.textContent = familyText('family_role_changed', 'Role updated.');
                    } else if (action === 'transfer') {
                        const message = familyText('family_confirm_transfer', 'Make this member the group owner?');
                        if (!window.confirm(message)) return;
                        await familyRequest({ action: 'transfer', group_id: groupId, email: button.dataset.email || '' });
                        if (status) status.textContent = familyText('family_transferred', 'Ownership transferred.');
                    } else if (action === 'rename') {
                        const name = window.prompt(familyText('family_group_name', 'Group name'), button.dataset.groupName || '');
                        if (name === null) return;
                        await familyRequest({ action: 'rename', group_id: groupId, name });
                        if (status) status.textContent = familyText('family_renamed', 'Group renamed.');
                    }
                } catch (e) { if (status) status.textContent = (e && e.message) || 'family_update_failed'; }
            });
            const notificationsApi = '/apps/steps/api/session_gateway.php?endpoint=notifications';
            const permissionsApi = '/apps/steps/api/session_gateway.php?endpoint=family_permissions';
            function shareText(key, fallback) { return (window.translations && translations[key]) || fallback; }
            async function shareRequest(api, body) {
                const res = await fetch(api, body === null ? { credentials: 'same-origin' } : { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
                const data = await res.json();
                if (!res.ok || !data.ok) throw new Error((data && data.error) || 'request_failed');
                return data;
            }
            function shareItem(text, actions) {
                const row = document.createElement('div');
                row.className = 'location-tool-item';
                const label = document.createElement('span');
                label.textContent = text;
                row.appendChild(label);
                const host = document.createElement('div');
                host.className = 'import-item-actions';
                (actions || []).forEach(action => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'excel-btn';
                    button.textContent = action.label;
                    button.addEventListener('click', action.onClick);
                    host.appendChild(button);
                });
                row.appendChild(host);
                return row;
            }
            function shareStatus(error) {
                const el = document.getElementById('share-status');
                if (el) el.textContent = error ? ((error && error.message) || String(error)) : '';
            }
            function renderSharing(data) {
                const host = document.getElementById('share-grants');
                if (!host) return;
                host.innerHTML = '';
                const active = (data.grants || []).filter(grant => grant.revoked_at === null);
                if (!active.length) {
                    const empty = document.createElement('p');
                    empty.className = 'location-hint';
                    empty.textContent = shareText('location_share_none', 'You are not sharing your location with anyone.');
                    host.appendChild(empty);
                    return;
                }
                active.forEach(grant => {
                    const until = grant.ends_at ? String(grant.ends_at).slice(0, 10) : shareText('location_share_no_expiry', 'no expiry');
                    const parts = [grant.viewer_email, grant.access_level, until];
                    if (Number(grant.precision_m) > 0) parts.push(Number(grant.precision_m) + ' m');
                    if (grant.family_group_name) parts.push(grant.family_group_name);
                    if (grant.reason) parts.push(grant.reason);
                    if (grant.paused_at) parts.push(shareText('location_share_paused', 'paused'));
                    host.appendChild(shareItem(parts.join(' · '), [
                        {
                            label: grant.paused_at ? shareText('location_share_resume', 'Resume') : shareText('location_share_pause', 'Pause'),
                            onClick: async () => {
                                try { await shareRequest(permissionsApi, { action: grant.paused_at ? 'resume' : 'pause', id: grant.id }); await loadSharing(); }
                                catch (e) { shareStatus(e); }
                            }
                        },
                        {
                            label: shareText('location_share_revoke', 'Revoke'),
                            onClick: async () => {
                                if (!window.confirm(shareText('location_share_confirm', 'Revoke this access? It stops immediately.'))) return;
                                try { await shareRequest(permissionsApi, { action: 'revoke', id: grant.id }); await loadSharing(); }
                                catch (e) { shareStatus(e); }
                            }
                        }
                    ]));
                });
            }
            window.addEventListener('steps-share-location-open', () => loadSharing());
            async function loadSharing() {
                const host = document.getElementById('share-grants');
                if (!host) return;
                try { renderSharing(await shareRequest(permissionsApi, null)); }
                catch (e) { host.innerHTML = '<p class="import-error">' + ((e && e.message) || 'family_permissions_failed') + '</p>'; }
            }
            document.getElementById('share-create')?.addEventListener('click', async () => {
                const status = document.getElementById('share-status');
                const iso = id => { const value = document.getElementById(id)?.value || ''; return value ? new Date(value).toISOString() : ''; };
                try {
                    const until = document.getElementById('share-until')?.value || '';
                    await shareRequest(permissionsApi, {
                        action: 'grant',
                        viewer_email: document.getElementById('share-viewer')?.value || '',
                        access_level: document.getElementById('share-level')?.value || 'recent',
                        ends_at: until ? new Date(until + 'T23:59:59').toISOString() : '',
                        precision_m: Number(document.getElementById('share-precision')?.value || 0),
                        reason: document.getElementById('share-reason')?.value || '',
                        range_from: iso('share-from'),
                        range_to: iso('share-to'),
                        consent: !!document.getElementById('share-consent')?.checked
                    });
                    if (status) status.textContent = shareText('location_share_created', 'Location sharing saved.');
                    await loadSharing();
                } catch (e) { if (status) status.textContent = (e && e.message) || 'family_permissions_failed'; }
            });
            function notifStatus(error) {
                const el = document.getElementById('notifications-status');
                if (el) el.textContent = error ? ((error && error.message) || String(error)) : '';
            }
            async function loadNotifications() {
                const eventsHost = document.getElementById('notif-events');
                const hooksHost = document.getElementById('notifications-webhooks');
                if (!eventsHost || !hooksHost) return;
                try {
                    const data = await shareRequest(notificationsApi, null);
                    const prefs = data.preferences || {};
                    const events = prefs.events || {};
                    eventsHost.innerHTML = '';
                    Object.keys(data.events || {}).forEach(key => {
                        const label = document.createElement('label');
                        label.className = 'notif-event';
                        const box = document.createElement('input');
                        box.type = 'checkbox';
                        box.dataset.notifEvent = key;
                        box.checked = events[key] !== false;
                        label.appendChild(box);
                        label.appendChild(document.createTextNode(' ' + (data.events[key] || key)));
                        eventsHost.appendChild(label);
                    });
                    const inApp = document.getElementById('notif-in-app'); if (inApp) inApp.checked = prefs.in_app !== false;
                    const webhook = document.getElementById('notif-webhook'); if (webhook) webhook.checked = prefs.webhook !== false;
                    const quiet = prefs.quiet_hours || {};
                    const quietBox = document.getElementById('notif-quiet'); if (quietBox) quietBox.checked = !!quiet.enabled;
                    const quietStart = document.getElementById('notif-quiet-start'); if (quietStart) quietStart.value = quiet.start || '22:00';
                    const quietEnd = document.getElementById('notif-quiet-end'); if (quietEnd) quietEnd.value = quiet.end || '07:00';
                    hooksHost.innerHTML = '';
                    const hooks = data.webhooks || [];
                    if (!hooks.length) {
                        const empty = document.createElement('p');
                        empty.className = 'location-hint';
                        empty.textContent = shareText('notifications_none', 'No webhooks yet.');
                        hooksHost.appendChild(empty);
                    }
                    hooks.forEach(hook => {
                        const eventsLabel = (hook.event_types || []).length ? hook.event_types.join(', ') : shareText('notifications_all_events', 'all events');
                        hooksHost.appendChild(shareItem((hook.description || hook.url_host || 'webhook') + ' · ' + eventsLabel + (hook.is_active ? '' : ' · off'), [
                            {
                                label: shareText('notifications_delete', 'Delete'),
                                onClick: async () => {
                                    if (!window.confirm(shareText('notifications_confirm_delete', 'Delete this webhook?'))) return;
                                    try { await shareRequest(notificationsApi, { action: 'delete_webhook', id: hook.id }); await loadNotifications(); }
                                    catch (e) { notifStatus(e); }
                                }
                            }
                        ]));
                    });
                    const deliveriesHost = document.getElementById('notification-deliveries');
                    if (deliveriesHost) {
                        deliveriesHost.innerHTML = '';
                        (data.deliveries || []).slice(0, 8).forEach(delivery => {
                            const text = delivery.event_type + ' · ' + delivery.state + ' · ' + (delivery.response_code || '-') + ' · ' + String(delivery.created_at || '').slice(0, 16) + (delivery.last_error ? ' · ' + delivery.last_error : '');
                            deliveriesHost.appendChild(shareItem(text, []));
                        });
                    }
                } catch (e) { notifStatus(e); }
            }
            document.getElementById('notifications-save')?.addEventListener('click', async () => {
                const events = {};
                document.querySelectorAll('[data-notif-event]').forEach(box => events[box.dataset.notifEvent] = box.checked);
                try {
                    await shareRequest(notificationsApi, {
                        action: 'save_preferences',
                        in_app: !!document.getElementById('notif-in-app')?.checked,
                        webhook: !!document.getElementById('notif-webhook')?.checked,
                        events,
                        quiet_hours: {
                            enabled: !!document.getElementById('notif-quiet')?.checked,
                            start: document.getElementById('notif-quiet-start')?.value || '22:00',
                            end: document.getElementById('notif-quiet-end')?.value || '07:00'
                        }
                    });
                    const status = document.getElementById('notifications-status');
                    if (status) status.textContent = shareText('notifications_saved', 'Preferences saved.');
                    await loadNotifications();
                } catch (e) { notifStatus(e); }
            });
            document.getElementById('notifications-webhook-create')?.addEventListener('click', async () => {
                try {
                    const types = (document.getElementById('notifications-webhook-events')?.value || '').split(',').map(part => part.trim()).filter(Boolean);
                    const data = await shareRequest(notificationsApi, {
                        action: 'create_webhook',
                        url: document.getElementById('notifications-webhook-url')?.value || '',
                        secret: document.getElementById('notifications-webhook-secret')?.value || '',
                        description: document.getElementById('notifications-webhook-description')?.value || '',
                        event_types: types
                    });
                    const status = document.getElementById('notifications-status');
                    if (status) status.textContent = shareText('notifications_secret_once', 'Signing secret (store it now):') + ' ' + (data.secret || '');
                    const secret = document.getElementById('notifications-webhook-secret'); if (secret) secret.value = '';
                    await loadNotifications();
                } catch (e) { notifStatus(e); }
            });
            document.getElementById('notifications-webhook-test')?.addEventListener('click', async () => {
                try {
                    await shareRequest(notificationsApi, { action: 'test_webhook' });
                    const status = document.getElementById('notifications-status');
                    if (status) status.textContent = shareText('notifications_test_queued', 'Test event queued.');
                } catch (e) { notifStatus(e); }
            });
            const locationExportsApi = '/apps/steps/api/session_gateway.php?endpoint=geolocation_exports';
            let locationExportsPoll = null;
            let locationExportOptionsReady = false;
            function exportText(key, fallback) { return (window.translations && translations[key]) || fallback; }
            function exportFormatLabel(format) {
                return ({ json: 'JSON', geojson: 'GeoJSON', gpx: 'GPX', backup: exportText('location_export_backup', 'Full account backup') })[format] || format;
            }
            function exportScopeLabel(scope) {
                const type = (scope && scope.type) || 'all';
                if (type === 'date') return exportText('location_export_scope_date', 'Date range') + ': ' + (scope.from || '') + ' – ' + (scope.to || '');
                if (type === 'device') return exportText('location_export_scope_device', 'Device');
                if (type === 'trip') return exportText('location_export_scope_trip', 'Trip');
                if (type === 'place') return exportText('location_export_scope_place', 'Place');
                return exportText('location_export_scope_all', 'All history');
            }
            function exportSize(bytes) {
                const value = Number(bytes || 0);
                if (value < 1024) return value + ' B';
                if (value < 1024 * 1024) return (value / 1024).toFixed(1) + ' KB';
                return (value / (1024 * 1024)).toFixed(1) + ' MB';
            }
            function updateExportScope() {
                const type = document.getElementById('location-export-scope')?.value || 'all';
                document.querySelectorAll('[data-export-scope]').forEach(field => {
                    field.hidden = field.getAttribute('data-export-scope') !== type;
                });
                const format = document.getElementById('location-export-format')?.value || 'geojson';
                const derived = document.getElementById('location-export-derived-wrap');
                if (derived) derived.style.display = format === 'json' ? '' : 'none';
                const scope = document.getElementById('location-export-scope');
                if (scope) scope.disabled = format === 'backup';
            }
            function fillExportOptions(data) {
                if (locationExportOptionsReady) return;
                const options = data.options || {};
                const fill = (id, rows, label) => {
                    const select = document.getElementById(id);
                    if (!select) return;
                    select.innerHTML = '';
                    if (!rows || !rows.length) {
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = exportText('location_export_none_option', 'None available');
                        select.appendChild(option);
                        return;
                    }
                    rows.forEach(row => {
                        const option = document.createElement('option');
                        option.value = row.id;
                        option.textContent = label(row);
                        select.appendChild(option);
                    });
                };
                fill('location-export-device', options.devices, row => (row.name || row.id) + (row.platform ? ' · ' + row.platform : ''));
                fill('location-export-trip', options.trips, row => (row.title || exportText('location_export_trip', 'Trip')) + (row.started_at ? ' · ' + String(row.started_at).slice(0, 10) : ''));
                fill('location-export-place', options.places, row => row.name || row.id);
                locationExportOptionsReady = true;
            }
            function renderExports(data) {
                const host = document.getElementById('location-export-history');
                if (!host) return;
                fillExportOptions(data);
                const exports = data.exports || [];
                host.innerHTML = '';
                if (!exports.length) {
                    const empty = document.createElement('p');
                    empty.className = 'location-hint';
                    empty.textContent = exportText('location_export_none', 'No exports yet.');
                    host.appendChild(empty);
                    return;
                }
                exports.forEach(item => {
                    const card = document.createElement('div');
                    card.className = 'import-item';
                    const head = document.createElement('div');
                    head.className = 'import-item-head';
                    const title = document.createElement('strong');
                    title.textContent = exportFormatLabel(item.format) + ' · ' + exportScopeLabel(item.scope || {});
                    const badge = document.createElement('span');
                    badge.className = 'import-state ' + importStateClass(item.state);
                    badge.textContent = importStateLabel(item.state);
                    head.appendChild(title); head.appendChild(badge);
                    const meta = document.createElement('div');
                    meta.className = 'import-item-meta';
                    const artifacts = item.artifacts || [];
                    const available = artifacts.filter(a => a.available);
                    const bits = [String(item.created_at || '').slice(0, 19).replace('T', ' ')];
                    if (available.length) {
                        bits.push(available.map(a => exportSize(a.size_bytes)).join(', '));
                        bits.push(exportText('location_export_expires', 'Expires') + ' ' + String(available[0].expires_at || '').slice(0, 10));
                    }
                    if (item.state === 'failed' && item.errors && item.errors.error) bits.push(String(item.errors.error));
                    meta.textContent = bits.filter(Boolean).join(' · ');
                    card.appendChild(head); card.appendChild(meta);
                    if (available.length) {
                        const links = document.createElement('div');
                        links.className = 'export-artifact-links';
                        available.forEach(artifact => {
                            const link = document.createElement('a');
                            link.href = '/apps/steps/api/session_gateway.php?endpoint=geolocation_export_download&id=' + encodeURIComponent(artifact.id);
                            link.innerHTML = '<i class="fas fa-download"></i> ' + exportText('location_export_download', 'Download') + ' (' + exportSize(artifact.size_bytes) + ')';
                            links.appendChild(link);
                        });
                        card.appendChild(links);
                    }
                    if (item.state !== 'deleted') {
                        const actions = document.createElement('div');
                        actions.className = 'import-item-actions';
                        const del = document.createElement('button');
                        del.type = 'button'; del.className = 'excel-btn'; del.dataset.exportDelete = item.id;
                        del.innerHTML = '<i class="fas fa-trash-can"></i> ' + exportText('location_export_remove', 'Delete export');
                        actions.appendChild(del);
                        card.appendChild(actions);
                    }
                    host.appendChild(card);
                });
            }
            async function loadExports() {
                const host = document.getElementById('location-export-history');
                if (!host) return;
                if (locationExportsPoll) { clearTimeout(locationExportsPoll); locationExportsPoll = null; }
                try {
                    const res = await fetch(locationExportsApi, { credentials: 'same-origin' });
                    const data = await res.json();
                    if (!res.ok || !data.ok) throw new Error((data && data.error) || 'export_list_failed');
                    renderExports(data);
                    const active = (data.exports || []).some(item => item.state === 'pending' || item.state === 'processing');
                    if (active) locationExportsPoll = setTimeout(loadExports, 4000);
                } catch (e) {
                    host.innerHTML = '<p class="import-error">' + ((e && e.message) || importText('map_error', 'Could not load data.')) + '</p>';
                }
            }
            async function createExport() {
                const status = document.getElementById('location-export-status');
                const format = document.getElementById('location-export-format')?.value || 'geojson';
                const type = format === 'backup' ? 'all' : (document.getElementById('location-export-scope')?.value || 'all');
                const scope = { type, include_derived: !!document.getElementById('location-export-derived')?.checked };
                if (type === 'date') {
                    scope.from = document.getElementById('location-export-from')?.value || '';
                    scope.to = document.getElementById('location-export-to')?.value || '';
                    if (!scope.from || !scope.to) { if (status) status.textContent = exportText('location_export_choose_range', 'Choose both dates.'); return; }
                } else if (type === 'device') {
                    scope.device_id = document.getElementById('location-export-device')?.value || '';
                } else if (type === 'trip') {
                    scope.trip_id = document.getElementById('location-export-trip')?.value || '';
                } else if (type === 'place') {
                    scope.place_id = document.getElementById('location-export-place')?.value || '';
                }
                if (status) status.textContent = exportText('map_loading', 'Loading…');
                try {
                    const res = await fetch(locationExportsApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'create', format, scope }) });
                    const data = await res.json();
                    if (!res.ok || !data.ok) throw new Error((data && data.error) || 'export_failed');
                    if (status) status.textContent = exportText('location_export_queued', 'Export queued. It will be ready shortly.');
                    await loadExports();
                } catch (e) {
                    if (status) status.textContent = (e && e.message) || importText('map_error', 'Could not load data.');
                }
            }
            async function deleteExport(id) {
                const message = exportText('location_export_confirm_remove', 'Delete this export and its file?');
                let confirmed = false;
                if (typeof window.showAlert === 'function') { try { confirmed = await window.showAlert(message, true); } catch (e) { confirmed = false; } }
                else confirmed = window.confirm(message);
                if (!confirmed) return;
                const status = document.getElementById('location-export-status');
                try {
                    const res = await fetch(locationExportsApi, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'delete', id }) });
                    const data = await res.json();
                    if (!res.ok || !data.ok) throw new Error((data && data.error) || 'export_delete_failed');
                    if (status) status.textContent = exportText('location_export_deleted', 'Export deleted.');
                    await loadExports();
                } catch (e) {
                    if (status) status.textContent = (e && e.message) || importText('map_error', 'Could not load data.');
                }
            }
            document.getElementById('location-export-format')?.addEventListener('change', updateExportScope);
            document.getElementById('location-export-scope')?.addEventListener('change', updateExportScope);
            document.getElementById('location-export-create')?.addEventListener('click', createExport);
            document.getElementById('location-export-history')?.addEventListener('click', (event) => {
                const del = event.target.closest('[data-export-delete]');
                if (del) deleteExport(del.getAttribute('data-export-delete'));
            });
            updateExportScope();
            document.querySelectorAll('.settings-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    const id = tab.getAttribute('data-settings-tab');
                    document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.settings-tab-panel').forEach(p => p.classList.remove('active'));
                    tab.classList.add('active');
                    tab.setAttribute('aria-selected', 'true');
                    document.querySelectorAll('.settings-tab').forEach(t => { if (t !== tab) t.setAttribute('aria-selected', 'false'); });
                    const panel = document.getElementById('settings-panel-' + id);
                    if (panel) panel.classList.add('active');
                    if (id === 'chart') syncChartTypeButtons();
                    if (id === 'backups') loadBackups();
                    if (id === 'location') { loadLocationStatistics(); loadImports(); loadExports(); loadImmich(); loadLocationTools(); loadSavedViews(); loadAnalytics(); loadNotifications(); }
                    if (id === 'family') { loadFamily(); }
                    if (id === 'points') window.dispatchEvent(new CustomEvent('steps-points-tab-open'));
                    if (id === 'render') window.dispatchEvent(new CustomEvent('steps-render-tab-open'));
                    steps_syncSettingsUrl();
                });
            });
            document.querySelectorAll('#settings-loc-subtabs .locdata-pill').forEach(pill => {
                pill.addEventListener('click', () => {
                    const sec = pill.getAttribute('data-locsec') || 'stats';
                    document.querySelectorAll('#settings-loc-subtabs .locdata-pill').forEach(p => p.classList.toggle('is-active', p === pill));
                    document.querySelectorAll('#settings-panel-location [data-locgroup]').forEach(group => {
                        group.classList.toggle('is-hidden', group.getAttribute('data-locgroup') !== sec);
                    });
                });
            });
            document.getElementById('settings-md3-close')?.addEventListener('click', () => closeModal('settings-modal'));
            (function lockSettingsModalScroll() {
                const modal = document.getElementById('settings-modal');
                if (!modal) return;
                const reset = () => {
                    modal.scrollTop = 0;
                    const content = modal.querySelector('.settings-content');
                    if (content) content.scrollTop = 0;
                };
                modal.addEventListener('focusin', reset);
                modal.addEventListener('scroll', reset, { passive: true });
                modal.querySelector('.settings-content')?.addEventListener('scroll', reset, { passive: true });
            })();
            document.getElementById('settings-md3-save')?.addEventListener('click', async () => {
                const settingsForm = document.getElementById('settings-form');
                if (settingsForm) {
                    try {
                        const formData = new FormData(settingsForm);
                        const response = await fetch('', { method: 'POST', body: formData });
                        const data = await response.json();
                        if (data.status !== 'success') {
                            showToast(`${translations.failed_save_settings || 'Error saving settings'}: ${data.message}`, 'error');
                            return;
                        }
                        showToast(translations.settings_saved || 'Settings saved', 'success');
                    } catch (err) {
                        showToast(`${translations.failed_save_settings || 'Error saving settings'}: ${err.message}`, 'error');
                        return;
                    }
                }
                closeModal('settings-modal');
            });
            function steps_syncSettingsUrl() {
                try {
                    const modal = document.getElementById('settings-modal');
                    const url = new URL(window.location.href);
                    const open = modal && modal.style.display !== 'none';
                    const active = document.querySelector('.settings-tab.active');
                    if (open) url.searchParams.set('settings', active ? active.getAttribute('data-settings-tab') : 'profile');
                    else url.searchParams.delete('settings');
                    history.replaceState(null, '', url);
                } catch (e) {}
            }
            (function settingsDeepLink() {
                const modal = document.getElementById('settings-modal');
                if (!modal) return;
                new MutationObserver(steps_syncSettingsUrl).observe(modal, { attributes: true, attributeFilter: ['style'] });
                const wanted = (new URLSearchParams(window.location.search).get('settings') || '').replace(/[^a-z_]/g, '');
                if (wanted) {
                    openModal('settings-modal');
                    const tab = document.querySelector('.settings-tab[data-settings-tab="' + wanted + '"]');
                    if (tab) tab.click();
                }
                const upgrade = (select) => {
                    if (select.closest('.custom-select') || select.multiple || select.dataset.nativeSelect) return;
                    if (!select.id) select.id = 'sel-' + Math.random().toString(36).slice(2, 9);
                    const root = document.createElement('div');
                    root.className = 'custom-select';
                    root.innerHTML = '<button type="button" class="custom-select-trigger" aria-haspopup="listbox" aria-expanded="false"><span class="custom-select-value"></span><i class="fas fa-chevron-down custom-select-caret" aria-hidden="true"></i></button><div class="custom-select-menu" role="listbox"></div>';
                    select.parentNode.insertBefore(root, select);
                    root.insertBefore(select, root.firstChild);
                    select.classList.add('custom-select-native');
                    select.tabIndex = -1;
                    select.setAttribute('aria-hidden', 'true');
                    const label = select.getAttribute('aria-label') || (select.closest('label') && select.closest('label').querySelector('span') ? select.closest('label').querySelector('span').textContent : '');
                    initCustomSelect(select.id, label);
                };
                const shareModal = document.getElementById('share-location-modal');
                const upgradeAll = () => { modal.querySelectorAll('select').forEach(upgrade); if (shareModal) shareModal.querySelectorAll('select').forEach(upgrade); };
                upgradeAll();
                new MutationObserver(upgradeAll).observe(modal, { childList: true, subtree: true });
                if (shareModal) new MutationObserver(upgradeAll).observe(shareModal, { childList: true, subtree: true });
            })();

            const todayBtn = document.getElementById('chart-today-btn');
            if (todayBtn) {
                todayBtn.addEventListener('click', () => {
                    currentDate = new Date();
                    syncCalendarSelectors();
                    renderCalendar(currentDate, currentLocale);
                    renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                });
            }
            const scrubber = document.getElementById('history-scrubber');
            if (scrubber) {
                scrubber.addEventListener('input', () => {
                    const years = [...new Set(stepsData.map(e => localDateFromYMD(e.date).getFullYear()))].sort((a, b) => a - b);
                    if (!years.length) return;
                    const idx = Math.round((Number(scrubber.value) / 100) * (years.length - 1));
                    const year = years[Math.max(0, Math.min(years.length - 1, idx))];
                    currentDate = new Date(year, currentDate.getMonth(), Math.min(currentDate.getDate(), 28));
                    syncCalendarSelectors();
                    renderCalendar(currentDate, currentLocale);
                    renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                });
            }

            // Chart navigation
            document.getElementById('chart-nav-left').addEventListener('click', () => {
                if (!canNavigateBackward()) return;
                const prevYear = currentDate.getFullYear();
                const prevMonth = currentDate.getMonth();
                if (currentView === 'month') {
                    currentDate.setMonth(currentDate.getMonth() - 1);
                } else if (currentView === 'week') {
                    currentDate.setDate(currentDate.getDate() - 7);
                } else if (currentView === 'year') {
                    currentDate.setFullYear(currentDate.getFullYear() - 1);
                }
                renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                if (currentView === 'year' || currentView === 'month') {
                    syncCalendarSelectors();
                } else if (currentView === 'week' && (currentDate.getFullYear() !== prevYear || currentDate.getMonth() !== prevMonth)) {
                    syncCalendarSelectors();
                }
            });

            document.getElementById('chart-nav-right').addEventListener('click', () => {
                if (!canNavigateForward()) return;
                const prevYear = currentDate.getFullYear();
                const prevMonth = currentDate.getMonth();
                if (currentView === 'month') {
                    currentDate.setMonth(currentDate.getMonth() + 1);
                } else if (currentView === 'week') {
                    currentDate.setDate(currentDate.getDate() + 7);
                } else if (currentView === 'year') {
                    currentDate.setFullYear(currentDate.getFullYear() + 1);
                }
                renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                if (currentView === 'year' || currentView === 'month') {
                    syncCalendarSelectors();
                } else if (currentView === 'week' && (currentDate.getFullYear() !== prevYear || currentDate.getMonth() !== prevMonth)) {
                    syncCalendarSelectors();
                }
            });

            // Year selection
            document.getElementById('year-selector').addEventListener('change', () => {
                const year = parseInt(document.getElementById('year-selector').value);
                currentDate.setFullYear(year);
                populateMonthSelectorForYear(year, currentLocale);
                document.getElementById('month-selector').value = '-';
                renderCalendar(new Date(year, 0, 1), currentLocale);
                refreshCustomSelects();
            });

            // Month selection
            document.getElementById('month-selector').addEventListener('change', () => {
                const val = document.getElementById('month-selector').value;
                if (val === '-') return;
                const [year, month] = val.split('-');
                currentDate = new Date(year, month - 1);
                renderCalendar(currentDate, currentLocale);
                renderChart('month', currentDate, currentDataType, currentChartType, currentLocale);
            });

            // Settings button
            document.getElementById('settings-button').addEventListener('click', () => {
                openModal('settings-modal');
            });

            // Add steps button
            document.getElementById('add-steps-button').addEventListener('click', () => {
                const today = formatLocalYMD(new Date());
                applyManualDate(today, currentLocale);
                openModal('add-steps-modal');
                setTimeout(() => document.getElementById('steps-input').focus(), 100);
            });

            // Create backup button
            document.getElementById('create-backup')?.addEventListener('click', createBackup);

            document.getElementById('video-recognition-notif-open')?.addEventListener('click', function() { zlOpenZeepModal(); });
            document.getElementById('global-image-viewer-close')?.addEventListener('click', function() { closeGlobalImageViewer(); });
            document.getElementById('global-image-viewer')?.addEventListener('click', function(e) {
                if (e.target && (e.target.id === 'global-image-viewer' || e.target.id === 'global-image-viewer-img')) closeGlobalImageViewer();
            });
            window.addEventListener('message', function(event) {
                if (!event || !event.data || typeof event.data !== 'object') return;
                if (event.data.type === 'takeout-guide-image-open') openGlobalImageViewer(event.data.src || '', event.data.alt || '');
                else if (event.data.type === 'takeout-guide-image-close') closeGlobalImageViewer();
                else if (event.data.type === 'takeout-guide-close') closeModal('guide-expand-modal');
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && globalImageViewerOpen) closeGlobalImageViewer();
            });
            document.querySelectorAll('.add-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    const id = tab.getAttribute('data-add-tab');
                    if (id) switchAddTab(id);
                });
            });
            document.getElementById('ft-zip')?.addEventListener('change', function() {
                ftUpdateWizardUi();
            });
            document.getElementById('ft-next-step1')?.addEventListener('click', function() {
                if (!(document.getElementById('ft-zip')?.files?.[0]) || ftWizardLocked) return;
                ftWizardStep = 2;
                ftUpdateWizardUi();
            });
            document.getElementById('ft-lang')?.addEventListener('change', function() {
                if (document.getElementById('ft-zip')?.files?.[0] && ftWizardStep >= 2) { ftWizardStep = 3; ftUpdateWizardUi(); }
            });
            document.getElementById('ft-year')?.addEventListener('input', function() {
                if (document.getElementById('ft-zip')?.files?.[0] && ftWizardStep >= 2) { ftWizardStep = 3; ftUpdateWizardUi(); }
            });
            document.getElementById('ft-start')?.addEventListener('click', async function() {
                if (ftWizardStep < 3) return;
                const inp = document.getElementById('ft-zip');
                const f = inp && inp.files && inp.files[0];
                if (!f) { await showAlert(translations.takeout_err_zip || 'Zip'); return; }
                const msg = document.getElementById('ft-msg');
                if (msg) msg.textContent = translations.video_file_uploading || 'Uploading...';
                let takeoutToken = '';
                try {
                    takeoutToken = await streamUploadFileInChunks(FTO_API, f, 'takeout', function(progress) {
                        ftSetBar((progress || 0) * 100);
                        if (msg) msg.textContent = (translations.video_file_uploading || 'Uploading...') + ' ' + String(Math.round((progress || 0) * 100)) + '%';
                    });
                } catch (e) {
                    if (msg) msg.textContent = '—';
                    ftSetBar(0);
                    await showAlert((translations.takeout_err_worker || '') + ' ' + ((e && e.message) ? e.message : 'upload'));
                    return;
                }
                const fd = new FormData();
                fd.append('action', 'upload_takeout');
                fd.append('takeout_token', takeoutToken);
                let d;
                try {
                    d = await fetch(FTO_API, { method: 'POST', body: fd, credentials: 'same-origin' }).then(r => r.json());
                } catch (e) { d = null; }
                if (!d || !d.ok) { await showAlert((translations.takeout_err_worker || '') + (d && d.error ? ' ' + d.error : '')); return; }
                ftStartPoll(d.job_id);
            });
            document.getElementById('ft-stop')?.addEventListener('click', async function() { await ftStopJob(); });
            document.getElementById('zl-zip')?.addEventListener('change', function() {
                zlCacheInputs();
                zlUpdateWizardUi();
            });
            document.getElementById('zl-password')?.addEventListener('input', function() {
                zlCachedPassword = zlNormPassword(this.value);
                zlUpdateWizardUi();
            });
            document.getElementById('zl-password-toggle')?.addEventListener('click', function() {
                const inp = document.getElementById('zl-password');
                const icon = this.querySelector('i');
                if (!inp) return;
                const show = inp.type === 'password';
                inp.type = show ? 'text' : 'password';
                inp.classList.toggle('is-visible', show);
                this.setAttribute('aria-pressed', show ? 'true' : 'false');
                this.setAttribute('aria-label', show ? (translations.zeep_password_hide || 'Hide password') : (translations.zeep_password_show || 'Show password'));
                if (icon) icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
            });
            document.getElementById('zl-next-step1')?.addEventListener('click', function() {
                if (!zlHasReadyInput() || zlWizardLocked) return;
                zlCacheInputs();
                zlWizardStep = 2;
                zlUpdateWizardUi();
            });
            document.getElementById('zl-next')?.addEventListener('click', function() {
                if (!zlHasReadyInput() || zlWizardLocked) return;
                zlCacheInputs();
                zlWizardStep = 3;
                zlUpdateWizardUi();
            });
            document.getElementById('zl-start')?.addEventListener('click', async function() {
                if (!ZL_API) {
                    await showAlert(translations.zeep_err_worker || 'Zeep Life API is unavailable');
                    return;
                }
                if (zlWizardStep < 3) return;
                zlCacheInputs();
                const inp = document.getElementById('zl-zip');
                const f = zlCachedFile || (inp && inp.files && inp.files[0]);
                const pwd = zlCachedPassword || zlNormPassword(document.getElementById('zl-password')?.value);
                if (!f) { await showAlert(translations.zeep_err_zip || translations.takeout_err_zip || 'Zip'); return; }
                if (!pwd) { await showAlert(translations.zeep_err_password || 'Password'); return; }
                const jp = document.getElementById('zl-job-panel');
                if (jp) jp.style.display = 'block';
                const msg = document.getElementById('zl-msg');
                if (msg) msg.textContent = translations.video_file_uploading || 'Uploading...';
                let zeepToken = '';
                try {
                    zeepToken = await streamUploadFileInChunks(ZL_API, f, 'zeep', function(progress) {
                        zlSetBar((progress || 0) * 100);
                        if (msg) msg.textContent = (translations.video_file_uploading || 'Uploading...') + ' ' + String(Math.round((progress || 0) * 100)) + '%';
                    });
                } catch (e) {
                    if (msg) msg.textContent = '—';
                    zlSetBar(0);
                    await showAlert((translations.zeep_err_worker || translations.takeout_err_worker || '') + ' ' + ((e && e.message) ? e.message : 'upload'));
                    return;
                }
                const fd = new FormData();
                fd.append('action', 'upload_zeep');
                fd.append('zeep_token', zeepToken);
                fd.append('archive_password', pwd);
                let d;
                try {
                    d = await fetch(ZL_API, { method: 'POST', body: fd, credentials: 'same-origin' }).then(r => r.json());
                } catch (e) { d = null; }
                if (!d || !d.ok) {
                    let err = d && d.error ? String(d.error) : '';
                    if (err === 'password') err = translations.zeep_err_password || err;
                    else if (err === 'not_zip') err = translations.zeep_err_zip || err;
                    await showAlert((translations.zeep_err_worker || translations.takeout_err_worker || '') + (err ? ' ' + err : ''));
                    return;
                }
                zlStartPoll(d.job_id);
            });
            document.getElementById('zl-stop')?.addEventListener('click', async function() { await zlStopJob(); });
            document.querySelectorAll('#zl-steps .va-step-dot').forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (zlWizardLocked) return;
                    const s = parseInt(btn.getAttribute('data-step-target') || '1', 10);
                    if (isNaN(s)) return;
                    zlWizardStep = s;
                    zlUpdateWizardUi();
                });
            });
            document.querySelectorAll('#ft-steps .va-step-dot').forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (ftWizardLocked) return;
                    const s = parseInt(btn.getAttribute('data-step-target') || '1', 10);
                    if (isNaN(s)) return;
                    ftWizardStep = s;
                    ftUpdateWizardUi();
                });
            });
            zlUpdateWizardUi();
            ftUpdateWizardUi();
            document.getElementById('video-confirm-ocr-log-btn')?.addEventListener('click', function() {
                const body = document.getElementById('video-confirm-ocr-body');
                const btn = document.getElementById('video-confirm-ocr-log-btn');
                if (!body || !btn || btn.style.display === 'none') return;
                const show = btn.getAttribute('aria-expanded') !== 'true';
                body.style.display = show ? 'block' : 'none';
                btn.setAttribute('aria-expanded', show ? 'true' : 'false');
                btn.textContent = show ? (translations.video_ocr_close_log || 'Hide log') : (translations.video_ocr_open_log || 'Open log');
            });
            document.getElementById('video-confirm-import').addEventListener('click', async () => {
                if (pendingZeepJobId) {
                    await zlConfirmImportFromPreview();
                    return;
                }
                syncVideoConfirmFromInputs();
                if (!pendingVideoRows.length) {
                    closeModal('video-confirm-modal');
                    return;
                }
                const fd = new FormData();
                fd.append('import_video_batch', '1');
                fd.append('rows', JSON.stringify(pendingVideoRows));
                let data = null;
                try {
                    const response = await fetch('', { method: 'POST', body: fd, credentials: 'same-origin' });
                    data = await response.json();
                    if (data && data.status === 'success' && data.new_steps) {
                        Object.entries(data.new_steps).forEach(([date, steps]) => {
                            stepsData = stepsData.filter(entry => entry.date !== date);
                            stepsData.push({ date, steps: parseInt(steps, 10) });
                        });
                        stepsData.sort((a, b) => new Date(a.date) - new Date(b.date));
                        renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                        if (currentView === 'month') updateCalendar(currentLocale);
                        updateStatsNotification();
                        updateGoalNotification();
                    }
                } catch (e) {}
                if (!data || data.status !== 'success') {
                    await showAlert((translations.video_job_err || 'Error') + (data && data.message ? ': ' + data.message : ''));
                    return;
                }
                if (!data.new_steps || !Object.keys(data.new_steps).length) {
                    await showAlert(translations.video_no_data || 'No data');
                    return;
                }
                if (data && data.status === 'success' && data.new_steps && Object.keys(data.new_steps).length) {
                    await runVideoCleanup();
                }
                closeModal('video-confirm-modal');
            });
            document.getElementById('video-confirm-skip').addEventListener('click', async () => {
                if (pendingZeepJobId) {
                    const jid = pendingZeepJobId;
                    pendingZeepJobId = null;
                    await zlCancelZeepJob(jid);
                    closeModal('video-confirm-modal');
                    return;
                }
                await runVideoCleanup();
                closeModal('video-confirm-modal');
            });
            document.getElementById('video-confirm-close-x').addEventListener('click', async () => {
                if (pendingZeepJobId) {
                    const jid = pendingZeepJobId;
                    pendingZeepJobId = null;
                    await zlCancelZeepJob(jid);
                }
                closeModal('video-confirm-modal');
            });
            const vctw = document.getElementById('video-confirm-table-wrap');
            if (vctw) {
                vctw.addEventListener('change', (e) => { if (e.target && e.target.classList && e.target.classList.contains('vcr-inp')) { syncVideoConfirmFromInputs(); refreshVideoConfirmView(); } });
                vctw.addEventListener('focusin', (e) => { if (e.target && e.target.classList && e.target.classList.contains('vcr-steps')) e.target.value = parseStepsRaw(e.target.value); });
                vctw.addEventListener('blur', (e) => { if (e.target && e.target.classList && e.target.classList.contains('vcr-inp')) { if (e.target.classList.contains('vcr-steps')) { const t = e.target.value.replace(/\D/g, ''); e.target.value = t ? formatNumber(parseInt(t, 10) || 0) : ''; } syncVideoConfirmFromInputs(); refreshVideoConfirmView(); } }, true);
            }
            const vgBody = document.getElementById('video-confirm-gaps-body');
            if (vgBody) {
                vgBody.addEventListener('focusin', (e) => { if (e.target && e.target.classList && e.target.classList.contains('vcr-gap-step')) e.target.value = parseStepsRaw(e.target.value); });
                vgBody.addEventListener('focusout', (e) => { if (e.target && e.target.classList && e.target.classList.contains('vcr-gap-step')) { const t = e.target.value.replace(/\D/g, ''); e.target.value = t ? formatNumber(parseInt(t, 10) || 0) : ''; } }, true);
            }
            document.getElementById('video-confirm-gaps-btn')?.addEventListener('click', () => { openVideoGapsModal(); });
            document.getElementById('video-confirm-gaps-apply')?.addEventListener('click', () => {
                const byDmy = new Map();
                pendingVideoRows.forEach((r) => { byDmy.set(r.date, { date: r.date, steps: r.steps, t: r.t || '' }); });
                document.querySelectorAll('.vcr-gap-step').forEach((inp) => {
                    const dmy = inp.getAttribute('data-dmy');
                    const v = parseInt(parseStepsRaw(inp.value), 10);
                    if (!dmy || isNaN(v) || v < 0) return;
                    const prev = byDmy.get(dmy);
                    byDmy.set(dmy, { date: dmy, steps: v, t: prev ? prev.t : '' });
                });
                pendingVideoRows = Array.from(byDmy.values());
                sortPendingVideoRows();
                closeVideoGapsModal();
                refreshVideoConfirmView();
            });
            document.getElementById('video-confirm-gaps-close-x')?.addEventListener('click', () => { closeVideoGapsModal(); });
            const vgm = document.getElementById('video-confirm-gaps-modal');
            if (vgm) vgm.addEventListener('click', (e) => { if (e.target === vgm) closeVideoGapsModal(); });
            const takeoutGuideSegment = document.getElementById('takeout-guide-segment');
            const takeoutGuideToggle = document.getElementById('takeout-guide-toggle');
            if (takeoutGuideSegment && takeoutGuideToggle) {
                takeoutGuideToggle.addEventListener('click', () => {
                    const collapsed = takeoutGuideSegment.classList.toggle('is-collapsed');
                    takeoutGuideToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                });
            }
            const zeepGuideSegment = document.getElementById('zeep-guide-segment');
            const zeepGuideToggle = document.getElementById('zeep-guide-toggle');
            function openGuideExpand(which) {
                const frame = document.getElementById('guide-expand-frame');
                const title = document.getElementById('guide-expand-title-text');
                const lang = <?php echo json_encode($language, JSON_HEX_TAG); ?>;
                const theme = <?php echo json_encode($theme, JSON_HEX_TAG); ?>;
                const color = <?php echo json_encode($color, JSON_HEX_TAG); ?>;
                const guides = {
                    zeep: { path: 'instruction/zeep_life_guide.html', title: translations.zeep_guide_title || 'How to export Zeep Life data' },
                    takeout: { path: 'instruction/takeout_guide.html', title: translations.takeout_guide_title || 'How to create a Google Takeout archive' },
                    play_protect: { path: 'instruction/play_protect_guide.html', title: translations.mobile_app_play_protect_title || 'Disable Google Play Protect' }
                };
                const guide = guides[which] || guides.takeout;
                const guidePath = `${guide.path}?lang=${encodeURIComponent(lang)}&theme=${encodeURIComponent(theme)}&color=${encodeURIComponent(color)}`;
                if (frame) {
                    frame.src = guidePath;
                }
                if (title) {
                    title.textContent = guide.title;
                }
                openModal('guide-expand-modal');
            }
            window.openStepsGuideExpand = openGuideExpand;
            const zeepGuideOpenBtn = document.getElementById('zeep-guide-open-btn');
            if (zeepGuideOpenBtn) zeepGuideOpenBtn.addEventListener('click', () => openGuideExpand('zeep'));
            const takeoutGuideOpenBtn = document.getElementById('takeout-guide-open-btn');
            if (takeoutGuideOpenBtn) takeoutGuideOpenBtn.addEventListener('click', () => openGuideExpand('takeout'));
            const guideExpandBackBtn = document.getElementById('guide-expand-back-btn');
            if (guideExpandBackBtn) guideExpandBackBtn.addEventListener('click', () => closeModal('guide-expand-modal'));
            [['video-confirm-phone-guide-segment','video-confirm-phone-guide-toggle']].forEach(function(pair) {
                const seg = document.getElementById(pair[0]);
                const btn = document.getElementById(pair[1]);
                if (seg && btn) {
                    btn.addEventListener('click', function() {
                        const collapsed = seg.classList.toggle('is-collapsed');
                        btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    });
                }
            });
            // Refresh backups list button
            document.getElementById('refresh-backups')?.addEventListener('click', async () => {
                await loadBackups();
            });

            // Download backup button
            document.getElementById('download-backup')?.addEventListener('click', downloadBackup);
            document.getElementById('toggle-import-backup')?.addEventListener('click', () => {
                const importSection = document.querySelector('.settings-backup-import');
                if (!importSection) return;
                const isHidden = window.getComputedStyle(importSection).display === 'none';
                importSection.style.display = isHidden ? 'block' : 'none';
                if (isHidden) {
                    importSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
            document.getElementById('import-external-backup-btn')?.addEventListener('click', importExternalBackup);

            // Search button
            document.getElementById('search-btn')?.addEventListener('click', searchInTable);

            // Clear search
            document.getElementById('clear-search')?.addEventListener('click', () => {
                document.getElementById('search-steps').value = '';
                document.getElementById('search-steps-to').value = '';
                document.getElementById('search-date-from').value = '';
                document.getElementById('search-date-to').value = '';
                renderExcelTable(1, '', '', '', '', '');
            });

            // Search operator change
            document.getElementById('search-operator')?.addEventListener('change', function() {
                const rangeInput = document.getElementById('search-steps-to');
                if (this.value === 'range') {
                    rangeInput.style.display = 'inline-block';
                } else {
                    rangeInput.style.display = 'none';
                    rangeInput.value = '';
                }
            });

            // Enter for search
            document.getElementById('search-steps')?.addEventListener('keypress', e => {
                if (e.key === 'Enter') searchInTable();
            });

            document.getElementById('search-steps-to')?.addEventListener('keypress', e => {
                if (e.key === 'Enter') searchInTable();
            });
            document.getElementById('search-date-from')?.addEventListener('change', searchInTable);
            document.getElementById('search-date-to')?.addEventListener('change', searchInTable);

            // Close modals
            document.querySelectorAll('.modal-close, #close-settings, #settings-md3-close').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const modal = btn.closest('[id$="-modal"]');
                    if (modal) closeModal(modal.id);
                });
            });

            document.querySelectorAll('.close-notification').forEach(btn => {
                btn.addEventListener('click', () => {
                    const notification = btn.closest('.notification');
                    if (!notification) return;
                    if (notification.id === 'video-recognition-notification') {
                        snoozeVideoNotifications12h();
                        return;
                    }
                    if (notification.id === 'stats-notification') {
                        cancelStatsNotifTimer();
                        statsNotifRevealDone = false;
                        snoozePanelNotif12h('stats');
                    } else if (notification.id === 'goal-notification') {
                        cancelGoalNotifTimer();
                        goalNotifRevealDone = false;
                        snoozePanelNotif12h('goal');
                    }
                    notification.style.display = 'none';
                });
            });

            // Auto-save settings
            const settingsForm = document.getElementById('settings-form');
            let debounceTimer;
            settingsForm.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(async () => {
                    const formData = new FormData(settingsForm);
                    try {
                        const response = await fetch('', { method: 'POST', body: formData });
                        const data = await response.json();
                        if (data.status !== 'success') {
                            showToast(`${translations.failed_save_settings || 'Error saving settings'}: ${data.message}`, 'error');
                        }
                    } catch (err) {
                        showToast(`${translations.failed_save_settings || 'Error saving settings'}: ${err.message}`, 'error');
                    }
                }, 2000);
            });
            // Disable default submit
            settingsForm.addEventListener('submit', (e) => e.preventDefault());

            // Save steps
            const stepsInpEl = document.getElementById('steps-input');
            stepsInpEl.addEventListener('focus', function() { this.value = parseStepsRaw(this.value); });
            stepsInpEl.addEventListener('blur', function() { const t = this.value.replace(/\D/g, ''); this.value = t ? formatNumber(parseInt(t, 10) || 0) : ''; autoSaveManualSteps(); });
            stepsInpEl.addEventListener('keypress', e => { if (e.key === 'Enter') saveSteps(); });
            document.getElementById('selected-date-input').addEventListener('change', function() { applyManualDate(this.value, currentLocale); });

            // Date scroll
            document.getElementById('selected-date-input').addEventListener('wheel', handleDateWheelScroll);

            // Calendar click
            document.getElementById('calendar').addEventListener('click', e => {
                const cell = e.target.closest('td[data-date]');
                if (cell) openDayStepsEditor(cell.getAttribute('data-date'));
            });

            // Minimalist day steps editor
            document.getElementById('day-steps-save')?.addEventListener('click', saveDaySteps);
            document.getElementById('day-steps-cancel')?.addEventListener('click', () => closeModal('day-steps-modal'));
            document.querySelector('#day-steps-modal .modal-close')?.addEventListener('click', () => closeModal('day-steps-modal'));
            document.getElementById('day-steps-input')?.addEventListener('keydown', e => {
                if (e.key === 'Enter') { e.preventDefault(); saveDaySteps(); }
            });

            // Handlers for Excel modal
            document.getElementById('delete-selected')?.addEventListener('click', deleteSelectedCells);
        }

        // App startup
        initApp();

    (function () {
    const valueDesc = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
    const loc = () => (typeof currentLocale !== 'undefined' && currentLocale) ? currentLocale : 'en-US';
    const p2 = n => String(n).padStart(2, '0');
    const ymd = (y, m, d) => y + '-' + p2(m + 1) + '-' + p2(d);
    const parse = v => { const m = /^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?/.exec(v || ''); return m ? { y: +m[1], m: +m[2] - 1, d: +m[3], h: +(m[4] || 0), i: +(m[5] || 0) } : null; };
    const tx = (k, f) => (window.translations && translations[k]) || f;
    function upgrade(input) {
        if (input.dataset.dpDone || input.classList.contains('dp-native') || input.closest('.dp-field')) return;
        input.dataset.dpDone = '1';
        const dt = input.type === 'datetime-local';
        const field = document.createElement('div'); field.className = 'dp-field'; field.dataset.dpNew = '1';
        input.parentNode.insertBefore(field, input);
        field.appendChild(input);
        input.classList.add('dp-native'); input.tabIndex = -1; input.setAttribute('aria-hidden', 'true');
        const trigger = document.createElement('button'); trigger.type = 'button'; trigger.className = 'dp-trigger'; trigger.setAttribute('aria-haspopup', 'dialog'); trigger.setAttribute('aria-expanded', 'false');
        trigger.innerHTML = '<span class="dp-value"></span><i class="fas fa-calendar-days dp-icon" aria-hidden="true"></i>';
        const pop = document.createElement('div'); pop.className = 'dp-popover'; pop.setAttribute('role', 'dialog'); pop.hidden = true;
        field.append(trigger, pop);
        const valueEl = trigger.firstChild;
        let cur = parse(input.value), view = new Date();
        const sync = () => {
            cur = parse(input.value);
            if (!cur) { valueEl.textContent = '—'; return; }
            const d = new Date(cur.y, cur.m, cur.d, cur.h, cur.i);
            valueEl.textContent = dt ? d.toLocaleString(loc(), { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : d.toLocaleDateString(loc(), { day: 'numeric', month: 'short', year: 'numeric' });
        };
        Object.defineProperty(input, 'value', { configurable: true, get() { return valueDesc.get.call(this); }, set(v) { valueDesc.set.call(this, v); sync(); } });
        const commit = (y, m, d, h, i) => {
            const v = ymd(y, m, d) + (dt ? 'T' + p2(h) + ':' + p2(i) : '');
            valueDesc.set.call(input, v); sync();
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        };
        const render = () => {
            view = new Date(view.getFullYear(), view.getMonth(), 1);
            const wk = (window.translations && Array.isArray(translations.days_of_week) && translations.days_of_week.length === 7) ? translations.days_of_week : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            const y = view.getFullYear(), m = view.getMonth(), off = (new Date(y, m, 1).getDay() + 6) % 7, days = new Date(y, m + 1, 0).getDate();
            const now = new Date(), today = ymd(now.getFullYear(), now.getMonth(), now.getDate()), sel = cur ? ymd(cur.y, cur.m, cur.d) : '';
            let h = '<div class="dp-head"><button type="button" class="dp-nav" data-nav="-1"><i class="fas fa-chevron-left"></i></button><span class="dp-title">' + view.toLocaleDateString(loc(), { month: 'long', year: 'numeric' }) + '</span><button type="button" class="dp-nav" data-nav="1"><i class="fas fa-chevron-right"></i></button></div>';
            h += '<div class="dp-weekdays">' + wk.map(d => '<span>' + d.slice(0, 2) + '</span>').join('') + '</div><div class="dp-grid">';
            for (let k = 0; k < off; k++) h += '<span class="dp-empty"></span>';
            for (let d = 1; d <= days; d++) { const s = ymd(y, m, d); h += '<button type="button" class="dp-day' + (s === sel ? ' is-selected' : '') + (s === today ? ' is-today' : '') + '" data-day="' + d + '">' + d + '</button>'; }
            h += '</div>';
            if (dt) h += '<div class="dp-time"><input type="number" min="0" max="23" class="dp-h" value="' + p2(cur ? cur.h : 0) + '"><span>:</span><input type="number" min="0" max="59" class="dp-i" value="' + p2(cur ? cur.i : 0) + '"></div>';
            h += '<div class="dp-foot"><button type="button" class="dp-today" data-today>' + tx('map_today', 'Today') + '</button></div>';
            pop.innerHTML = h;
        };
        const close = () => {
            pop.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            if (pop.parentElement !== field) field.appendChild(pop);
            pop.style.left = ''; pop.style.top = ''; pop.style.width = ''; pop.style.zIndex = '';
        };
        const open = () => {
            document.querySelectorAll('.dp-popover').forEach(p => {
                p.hidden = true;
                const host = p.closest('.dp-field');
                if (host && p.parentElement !== host) host.appendChild(p);
            });
            view = cur ? new Date(cur.y, cur.m, 1) : new Date();
            render();
            document.body.appendChild(pop);
            pop.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            const r = trigger.getBoundingClientRect(), w = Math.min(300, window.innerWidth - 16), hh = pop.offsetHeight || 340;
            pop.style.position = 'fixed';
            pop.style.width = w + 'px';
            pop.style.left = Math.min(Math.max(8, r.left), window.innerWidth - w - 8) + 'px';
            let top = r.bottom + 8; if (top + hh > window.innerHeight - 8) top = Math.max(8, r.top - hh - 8);
            pop.style.top = top + 'px';
            pop.style.zIndex = '200000';
        };
        trigger.addEventListener('click', e => { e.stopPropagation(); pop.hidden ? open() : close(); });
        pop.addEventListener('click', e => {
            const nav = e.target.closest('[data-nav]');
            if (nav) { view.setMonth(view.getMonth() + Number(nav.dataset.nav)); render(); return; }
            const day = e.target.closest('[data-day]');
            const base = cur || { h: 0, i: 0 };
            if (day) { commit(view.getFullYear(), view.getMonth(), Number(day.dataset.day), base.h, base.i); if (dt) render(); else close(); return; }
            if (e.target.closest('[data-today]')) { const n = new Date(); commit(n.getFullYear(), n.getMonth(), n.getDate(), dt ? n.getHours() : 0, dt ? n.getMinutes() : 0); if (dt) render(); else close(); }
        });
        pop.addEventListener('change', e => {
            if (!e.target.matches('.dp-h,.dp-i')) return;
            const b = cur || (() => { const n = new Date(); return { y: n.getFullYear(), m: n.getMonth(), d: n.getDate() }; })();
            const hh = Math.min(23, Math.max(0, Number(pop.querySelector('.dp-h').value) || 0)), ii = Math.min(59, Math.max(0, Number(pop.querySelector('.dp-i').value) || 0));
            commit(b.y, b.m, b.d, hh, ii);
        });
        document.addEventListener('click', e => { if (!pop.hidden && !pop.contains(e.target) && !field.contains(e.target)) close(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && !pop.hidden) close(); });
        input.addEventListener('change', sync);
        sync();
    }
    const scan = root => (root.querySelectorAll ? root : document).querySelectorAll('input[type="date"],input[type="datetime-local"]').forEach(upgrade);
    const start = () => { scan(document); new MutationObserver(ms => ms.forEach(m => m.addedNodes.forEach(n => { if (n.nodeType === 1) { if (n.matches && n.matches('input[type="date"],input[type="datetime-local"]')) upgrade(n); else scan(n); } }))).observe(document.body, { childList: true, subtree: true }); };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();

    // Give every standard modal the same header: title on the left, close on the right.
    // Keeping this in one place prevents new modals from drifting from the shared design.
    function setupModalTopbars() {
        const ids = [
            'stats-modal', 'info-modal', 'mobile-app-modal', 'manual-pair-modal',
            'share-location-modal', 'settings-modal', 'day-steps-modal', 'add-steps-modal',
            'video-crop-modal', 'alert-modal', 'video-confirm-modal', 'video-confirm-gaps-modal'
        ];
        const closeLabel = <?php echo json_encode($translations['close'] ?? 'Close', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        ids.forEach(function (id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            const content = modal.firstElementChild;
            if (!content || content.querySelector(':scope > .modal-topbar')) return;
            if (content.querySelector(':scope > .md3-modal-head') || document.querySelector('.app-steps.md3')) return;

            const title = content.querySelector('h1, h2, h3, .day-steps-title, .alert-title');
            let close = null;
            for (const child of Array.from(content.children)) {
                if (child.classList && child.classList.contains('modal-close')) {
                    close = child;
                    break;
                }
            }

            const topbar = document.createElement('div');
            topbar.className = 'modal-topbar';

            const titleShell = title ? title.closest('.info-header') : null;
            if (title) {
                title.classList.add('modal-topbar-title');
                topbar.appendChild(title);
                if (titleShell && content.contains(titleShell)) titleShell.remove();
            } else {
                const fallback = document.createElement('div');
                fallback.className = 'modal-topbar-title';
                fallback.textContent = content.getAttribute('data-modal-title') || '';
                topbar.appendChild(fallback);
            }

            if (!close) {
                close = document.createElement('button');
                close.type = 'button';
                close.className = 'modal-close';
                close.setAttribute('aria-label', closeLabel);
                close.addEventListener('click', function () {
                    if (typeof closeModal === 'function') closeModal(id);
                    else modal.style.display = 'none';
                });
            }
            close.type = 'button';
            close.classList.add('app-close-button');
            close.setAttribute('aria-label', close.getAttribute('aria-label') || closeLabel);
            close.innerHTML = '<span class="material-symbols-rounded" aria-hidden="true">close</span>';
            topbar.appendChild(close);

            content.insertBefore(topbar, content.firstChild);
            const body = document.createElement('div');
            body.className = 'modal-body';
            while (topbar.nextSibling) body.appendChild(topbar.nextSibling);
            content.appendChild(body);
            content.classList.add('has-modal-topbar');
        });
    }
    setupModalTopbars();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupModalTopbars);
    }

    function enhanceM3Checkboxes(root) {
        const scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
            if (input.classList.contains('m3-checkbox-input') || input.classList.contains('dp-native')) return;
            if (input.closest('.map-switch') || input.closest('.m3-checkbox-wrapper')) return;
            input.classList.add('m3-checkbox-input');
            const box = document.createElement('span');
            box.className = 'm3-checkbox-box';
            box.innerHTML = '<svg class="m3-checkbox-svg" viewBox="0 0 16 16"><polyline points="3.5 8.5 6.5 11.5 12.5 4.5"></polyline></svg>';
            input.insertAdjacentElement('afterend', box);
            const label = input.closest('label');
            if (label) label.classList.add('m3-checkbox-wrapper');
            else {
                const wrap = document.createElement('label');
                wrap.className = 'm3-checkbox-wrapper';
                input.parentNode.insertBefore(wrap, input);
                wrap.appendChild(input);
                wrap.appendChild(box);
            }
        });
    }
    const startM3Checkboxes = function () {
        enhanceM3Checkboxes(document);
        new MutationObserver(function (ms) {
            ms.forEach(function (m) {
                m.addedNodes.forEach(function (n) {
                    if (n.nodeType !== 1) return;
                    if (n.matches && n.matches('input[type="checkbox"]')) enhanceM3Checkboxes(n.parentNode || document);
                    else enhanceM3Checkboxes(n);
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', startM3Checkboxes);
    else startM3Checkboxes();
})();</script>

</body>
</html>
