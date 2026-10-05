    <script>
        let stepsData = <?php echo json_encode($stepsData, JSON_HEX_TAG); ?>,
            healthData = <?php echo json_encode($healthMetrics ?? [], JSON_HEX_TAG); ?>,
            userSettings = <?php echo json_encode($userSettings, JSON_HEX_TAG); ?>,
            currentUser = '<?php echo htmlspecialchars($currentUser); ?>',
            translations = <?php echo json_encode($translations, JSON_HEX_TAG); ?>;

        const localeMap = { 'en': 'en-EN', 'ru': 'ru-RU', 'uk': 'uk-UA', 'be': 'be-BY' },
              currentLocale = localeMap['<?php echo $language; ?>'] || 'en-EN';

        const chartTypeStorageKey = () => 'stepsChartType_' + String(currentUser || 'u').replace(/[^a-z0-9_\-]/gi, '_');
        let currentView = 'year', currentDataType = 'steps', currentDate = new Date(), chart = null;
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

        Chart.register(ChartZoom);

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

        // Hide buttons when there is no corresponding metric on server.
        // This prevents showing empty charts (e.g. no sleep data => no sleep button).
        const hideEmptyDataTypeButtons = () => {
            const candidates = ['distance', 'calories', 'heart_rate', 'sleep', 'weight'];
            const hasAny = (dt) => stepsData.some(entry => hasRealMetricForDate(entry.date, dt));

            const stepsBtn = document.querySelector('.data-type-btn[data-type="steps"]');
            const ensureActive = () => {
                document.querySelectorAll('.data-type-btn').forEach(b => b.classList.remove('active'));
                if (currentDataType === 'steps' && stepsBtn) stepsBtn.classList.add('active');
            };

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
        const ALERT_ICON_WARNING = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';
        const ALERT_ICON_INFO = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';

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
        function renderChartPreviews() {
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
                modal.style.display = 'flex';
                if (modalId === 'settings-modal') {
                    resetSettingsModalTabs();
                    syncChartTypeButtons();
                }
                if (modalId === 'add-steps-modal') {
                    if (opts.addTab) switchAddTab(opts.addTab);
                    else resetAddModalTabs();
                }
                if (modalId === 'video-confirm-modal' && !document.hidden) {
                    const vn = document.getElementById('video-recognition-notification');
                    if (vn) vn.style.display = 'none';
                }
                if (modalId === 'stats-modal') {
                    setTimeout(renderStatisticsModal, 0);
                }
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeModal(modalId);
                    }
                });
            }
        }

        function refreshBodyModalLock() {
            const ids = ['info-modal','stats-modal','settings-modal','add-steps-modal','user-modal','add-user-modal','video-confirm-modal','video-confirm-gaps-modal','alert-modal','video-crop-modal','guide-expand-modal','mobile-app-modal','global-image-viewer'];
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
                modal.style.display = 'none';
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

        function createBarGradient(canvas, alpha = 0.5) {
            const ctx = canvas.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
            const r = 0, g = 123, b = 255;
            const halfWhite = `rgba(${Math.round((255 + r) / 2)},${Math.round((255 + g) / 2)},${Math.round((255 + b) / 2)},${alpha})`;
            const full = `rgba(${r},${g},${b},${alpha})`;
            gradient.addColorStop(0, halfWhite);
            gradient.addColorStop(1, full);
            return gradient;
        }

        function renderChart(view, selectedDate, dataType, chartType, lang = currentLocale) {
            if (chart) { chart.destroy(); chart = null; }

            const chartData = prepareChartData(view, selectedDate, dataType, lang);
            if (!chartData.data.length) return;

            document.getElementById('chart-label').textContent = getChartLabel(view, selectedDate, lang);
            document.getElementById('chart-nav-left').style.display = 'block';
            document.getElementById('chart-nav-right').style.display = 'block';
            document.getElementById('chart-nav-left').disabled = !canNavigateBackward();
            document.getElementById('chart-nav-right').disabled = !canNavigateForward();

            const unitLabel = unitLabelFor(dataType);
            const isDarkTheme = '<?php echo $theme === 'dark' ? 'true' : 'false'; ?>' === 'true';
            const chartGridColor = isDarkTheme ? 'rgba(160, 160, 160, 0.45)' : 'rgba(0, 0, 0, 0.12)';
            const seriesType = isSeriesType(dataType);
            const hideChartTitles = window.matchMedia('(max-width: 768px)').matches;
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
                        borderColor: 'rgba(0,123,255,1)',
                        borderWidth: chartType === 'bar' ? 1 : 2,
                        fill: chartType === 'line',
                        tension: chartType === 'line' ? 0.4 : 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: 10 },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ...(yMaxWhenBelowGoal != null ? { max: yMaxWhenBelowGoal } : {}),
                            grid: { color: chartGridColor },
                            border: { color: chartGridColor },
                            ticks: {
                                display: true,
                                color: isDarkTheme ? '#fff' : '#000',
                                callback: (v) => fmtAxis(v)
                            },
                            title: {
                                display: !hideChartTitles,
                                text: unitLabel,
                                color: isDarkTheme ? '#fff' : '#000'
                            }
                        },
                        x: {
                            grid: { color: chartGridColor },
                            border: { color: chartGridColor },
                            ticks: { display: true, color: isDarkTheme ? '#fff' : '#000' },
                            title: {
                                display: !hideChartTitles,
                                text: translations.time || 'Time',
                                color: isDarkTheme ? '#fff' : '#000'
                            }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
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
                                        borderColor: 'rgba(194, 0, 42, 0.76)',
                                        borderWidth: 2,
                                        borderDash: [6, 6],
                                        label: {
                                            display: true,
                                            content: (translations.goal_label || 'Goal: ') + formatNumber(userSettings.goal_steps),
                                            position: 'end',
                                            yAdjust: -20,
                                            backgroundColor: 'rgba(167, 0, 36, 0.55)',
                                            font: {
                                                size: 12
                                            }
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
        function updateStatsNotification() {
            const steps = stepsData.map(entry => entry.steps),
                  maxSteps = Math.max(...steps),
                  maxDistance = (maxSteps * userSettings.stride_length) / 100000;
            const monthData = stepsData.filter(entry =>
                localDateFromYMD(entry.date).getMonth() === currentDate.getMonth() &&
                localDateFromYMD(entry.date).getFullYear() === currentDate.getFullYear()
            );
            const avgSteps = monthData.length ? monthData.reduce((sum, entry) => sum + entry.steps, 0) / monthData.length : 0;
            const el = document.getElementById('stats-notification');
            document.getElementById('stats-content').innerHTML = `
                <div class="notification-item">
                    <span>${translations.max_steps}</span>
                    <span class="notification-value">${formatNumber(maxSteps)}</span>
                </div>
                <div class="notification-item">
                    <span>${translations.max_distance}</span>
                    <span class="notification-value">${maxDistance.toFixed(2)} ${translations.distance_km}</span>
                </div>
                <div class="notification-item">
                    <span>${translations.avg_steps}</span>
                    <span class="notification-value">${formatNumber(Math.round(avgSteps))}</span>
                </div>
                <div class="notification-item">
                    <span>${translations.avg_distance}</span>
                    <span class="notification-value">${(avgSteps * userSettings.stride_length / 100000).toFixed(2)} ${translations.distance_km}</span>
                </div>`;
            if (!el) return;
            if (Date.now() < getPanelNotifSnoozeUntil('stats')) {
                cancelStatsNotifTimer();
                statsNotifRevealDone = false;
                el.style.display = 'none';
                return;
            }
            ensureStatsNotifScheduled();
            if (statsNotifRevealDone) el.style.display = 'block';
        }
        function updateGoalNotification() {
            const monthStart = new Date();
            monthStart.setDate(1);
            const currentMonth = monthStart.getMonth();
            const currentYear = monthStart.getFullYear();
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
            const goalPerDay = userSettings.goal_steps;
            const el = document.getElementById('goal-notification');
            document.getElementById('goal-content').innerHTML = `
                <div class="notification-item">
                    <span>${translations.goal_daily_hint || 'пройти нужно каждый день по'}</span>
                    <span class="notification-value">${formatNumber(goalPerDay)}</span>
                </div>
                <div class="notification-item">
                    <span>${translations.goal_days_hint || 'ходить нужно'}</span>
                    <span class="notification-value">${daysInMonth} ${translations.days || 'дней'}</span>
                </div>`;
            if (!el) return;
            if (Date.now() < getPanelNotifSnoozeUntil('goal')) {
                cancelGoalNotifTimer();
                goalNotifRevealDone = false;
                el.style.display = 'none';
                return;
            }
            ensureGoalNotifScheduled();
            if (goalNotifRevealDone) el.style.display = 'block';
        }

        function destroyStatisticsCharts() {
            if (yearlyDistributionChart) { yearlyDistributionChart.destroy(); yearlyDistributionChart = null; }
            if (monthlyIntensityChart) { monthlyIntensityChart.destroy(); monthlyIntensityChart = null; }
        }
        function renderStatisticsModal(lang = currentLocale) {
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
            const yearlySection = document.querySelector('#stats-modal .stats-card');
            const hasMultipleYears = yearlyLabels.length >= 2;
            if (yearlySection) yearlySection.style.display = hasMultipleYears ? '' : 'none';
            const totalAllYears = yearlyData.reduce((sum, value) => sum + value, 0);
            if (hasMultipleYears) {
            yearlyDistributionChart = new Chart(document.getElementById('stats-years-chart').getContext('2d'), {
                type: 'pie',
                data: {
                    labels: yearlyLabels,
                    datasets: [{
                        label: translations.statistics_pie_chart_label || 'Yearly Step Share',
                        data: yearlyData,
                        backgroundColor: yearlyLabels.map((_, i) => palette[i % palette.length]),
                        borderColor: isDarkTheme ? 'rgba(255,255,255,0.22)' : 'rgba(0,0,0,0.12)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: chartTextColor } },
                        tooltip: {
                            callbacks: {
                                label: context => {
                                    const value = Number(context.raw) || 0;
                                    const pct = totalAllYears ? (value / totalAllYears * 100).toFixed(1) : '0.0';
                                    return `${context.label}: ${formatNumber(value)} ${translations.steps || 'steps'} (${pct}%)`;
                                }
                            }
                        }
                    }
                }
            });
            }
            const year = new Date().getFullYear();
            const monthly = Array.from({ length: 12 }, () => 0);
            stepsData.forEach(entry => {
                const d = localDateFromYMD(entry.date);
                if (!isNaN(d.getTime()) && d.getFullYear() === year) monthly[d.getMonth()] += Number(entry.steps) || 0;
            });
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
        }

        function populateMonthSelectorForYear(year, lang = currentLocale, selectMonth = null) {
            const monthSelector = document.getElementById('month-selector');
            const months = getAvailableMonthsForYear(year, lang);
            if (!months.length) {
                monthSelector.innerHTML = '<option value="-">—</option>';
                monthSelector.disabled = true;
                return;
            }
            monthSelector.disabled = false;
            monthSelector.innerHTML = months
                .map(m => `<option value="${m.value}" ${selectMonth && m.value === `${year}-${selectMonth}` ? 'selected' : ''}>${m.label}</option>`)
                .join('');
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

            document.getElementById('goal-summary').innerHTML = `
                <div class="goal-summary-item"><span class="goal-summary-label">${translations.total_steps}</span><span class="goal-summary-value">${formatNumber(totalSteps)}</span></div>
                <div class="goal-summary-item"><span class="goal-summary-label">${translations.goal_days}</span><span class="goal-summary-value">${goalDays}</span></div>`;
        }

        // Functions for add steps modal
        function applyManualDate(date, lang = currentLocale, direction = 'none') {
            document.getElementById('selected-date-input').value = date;
            setStepsInputValue((stepsData.find(entry => entry.date === date) || { steps: 0 }).steps);
            manualSaveSignature = getManualSaveSignature();
            updateAdjacentDates(date, lang, direction);
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
            applyManualDate(newDateStr, currentLocale, direction);
        }
        function updateAdjacentDates(date, lang = currentLocale, direction = 'none') {
            const dateObj = localDateFromYMD(date),
                  prevDates = [],
                  nextDates = [];
            if (isNaN(dateObj.getTime())) return;
            const addPanel = document.getElementById('add-panel-manual');
            const panelHeight = addPanel ? addPanel.clientHeight : 510;
            const currentDateEl = addPanel ? addPanel.querySelector('.current-date') : null;
            const currentDateHeight = currentDateEl ? currentDateEl.getBoundingClientRect().height : 60;
            // Make adjacent dates fill most of the modal height (without relying on scroll)
            const usable = Math.max(0, panelHeight - currentDateHeight - 20);
            const count = Math.max(7, Math.min(18, Math.floor(usable / 36)));

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
                    const newDate = el.getAttribute('data-date');
                    const curDate = document.getElementById('selected-date-input').value;
                    const direction = newDate > curDate ? 'next' : 'prev';
                    applyManualDate(newDate, lang, direction);
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
            const maxStep = hasFile ? 3 : 1;
            if (!hasFile) ftWizardStep = 1;
            else if (ftWizardStep < 2) ftWizardStep = 2;
            if (ftWizardStep > maxStep) ftWizardStep = maxStep;
            setWizardUi('ft-steps', ftWizardStep, maxStep, ftWizardLocked);
            setWizardSections('add-panel-takeout', ftWizardStep, ftWizardLocked);
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
        async function showVideoResultsConfirm(payload) {
            pendingZeepJobId = null;
            pendingVideoCleanup = { jobId: payload.jobId || '', outputBasename: payload.outputBasename || '' };
            const raw = payload.results && payload.results.length ? payload.results : [];
            pendingVideoOcrPreview = Array.isArray(payload.ocrPreview) ? payload.ocrPreview : (Array.isArray(payload.ocr_preview) ? payload.ocr_preview : []);
            pendingVideoRows = [];
            raw.forEach((r) => {
                const d = videoResultRowResolvedDmy(r);
                if (!d) return;
                const s = parseInt(String(r.steps), 10);
                if (isNaN(s) || s < 0) return;
                pendingVideoRows.push({ date: d, steps: s, t: (r.t_display != null && r.t_display !== '') ? String(r.t_display) : '' });
            });
            const subEl = document.getElementById('video-confirm-sub');
            const emptyHint = document.getElementById('video-confirm-empty-hint');
            const otitle = document.getElementById('video-confirm-ocr-title');
            const titleEl = document.getElementById('video-confirm-title');
            const phoneGuide = document.getElementById('video-confirm-phone-guide-segment');
            if (phoneGuide) phoneGuide.style.display = '';
            if (titleEl) titleEl.innerHTML = '<i class="fas fa-clipboard-list"></i> ' + videoEscapeHtml(translations.video_confirm_title || 'Recognized steps');
            if (subEl) subEl.textContent = translations.video_confirm_sub || '';
            if (emptyHint) {
                emptyHint.style.display = !pendingVideoRows.length ? '' : 'none';
                emptyHint.textContent = translations.video_none_recognized || translations.video_no_data || '';
            }
            if (otitle) otitle.textContent = translations.video_ocr_preview_heading || 'OCR samples';
            renderVideoConfirmOcrBlock();
            const imb = document.getElementById('video-confirm-import');
            if (imb) {
                imb.textContent = translations.video_add_to_app || 'Add to app';
                imb.disabled = !pendingVideoRows.length;
                imb.style.opacity = pendingVideoRows.length ? '1' : '0.55';
            }
            renderVideoConfirmTableBody();
            closeModal('add-steps-modal');
            openModal('video-confirm-modal');
            requestAnimationFrame(function() { renderVideoConfirmChart(pendingVideoRows); });
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
            renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
            updateCalendar(currentLocale);
            updateStatsNotification();
            updateGoalNotification();
            hideEmptyDataTypeButtons();
            workerStartRefreshLoop();
            startStepsDataRefreshLoop();
            document.addEventListener('visibilitychange', function() { if (!document.hidden) { workerRefreshStates(); refreshStepsDataIfChanged(); } });
            let chartLabelsHideState = window.matchMedia('(max-width: 768px)').matches;
            window.addEventListener('resize', function() {
                const state = window.matchMedia('(max-width: 768px)').matches;
                if (state !== chartLabelsHideState) {
                    chartLabelsHideState = state;
                    renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
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
            document.querySelectorAll('.settings-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    const id = tab.getAttribute('data-settings-tab');
                    document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.settings-tab-panel').forEach(p => p.classList.remove('active'));
                    tab.classList.add('active');
                    const panel = document.getElementById('settings-panel-' + id);
                    if (panel) panel.classList.add('active');
                    if (id === 'chart') syncChartTypeButtons();
                    if (id === 'backups') loadBackups();
                });
            });

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
                const f = this.files && this.files[0];
                if (f && ftWizardStep < 2) ftWizardStep = 2;
                ftUpdateWizardUi();
            });
            document.getElementById('ft-lang')?.addEventListener('change', function() {
                if (document.getElementById('ft-zip')?.files?.[0]) { ftWizardStep = 3; ftUpdateWizardUi(); }
            });
            document.getElementById('ft-year')?.addEventListener('input', function() {
                if (document.getElementById('ft-zip')?.files?.[0]) { ftWizardStep = 3; ftUpdateWizardUi(); }
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
                const guidePath = which === 'zeep'
                    ? `instruction/zeep_life_guide.html?lang=${encodeURIComponent(lang)}&theme=${encodeURIComponent(theme)}&color=${encodeURIComponent(color)}`
                    : `instruction/takeout_guide.html?lang=${encodeURIComponent(lang)}&theme=${encodeURIComponent(theme)}&color=${encodeURIComponent(color)}`;
                if (frame) {
                    frame.src = guidePath;
                }
                if (title) {
                    title.textContent = which === 'zeep'
                        ? (translations.zeep_guide_title || 'How to export Zeep Life data')
                        : (translations.takeout_guide_title || 'How to create a Google Takeout archive');
                }
                openModal('guide-expand-modal');
            }
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
            document.querySelectorAll('.modal-close').forEach(btn => {
                btn.addEventListener('click', () => {
                    const modal = btn.closest('[id$="-modal"]');
                    if (modal) {
                        closeModal(modal.id);
                    }
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

            // Prevent scrolling in adjacent dates
            document.querySelectorAll('.adjacent-dates').forEach(container => {
                container.addEventListener('wheel', e => e.preventDefault());
            });

            // Calendar click
            document.getElementById('calendar').addEventListener('click', e => {
                const cell = e.target.closest('td[data-date]');
                if (cell) {
                    const date = cell.getAttribute('data-date');
                    applyManualDate(date, currentLocale);
                    openModal('add-steps-modal');
                }
            });

            // Handlers for Excel modal
            document.getElementById('delete-selected')?.addEventListener('click', deleteSelectedCells);
        }

        // App startup
        initApp();

    </script>

</body>
</html>
