<script>
    let activeOverlayId = null;
    let scrollbarWidth = 0;
    let scrollPos = 0;
    let isScrollLocked = false;
    let notificationDismissed = <?php echo isset($notificationDismissed) && $notificationDismissed ? 'true' : 'false'; ?>;
    const galleryAlbums = <?php echo json_encode($albums); ?>;
    let editCropImage = null;
    let editCropHasImage = false;

    function getScrollbarWidth() {
        return window.innerWidth - document.documentElement.clientWidth;
    }

    function lockBodyScroll() {
        if (isScrollLocked) return;
        isScrollLocked = true;
        scrollbarWidth = getScrollbarWidth();
        scrollPos = window.scrollY;
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.top = `-${scrollPos}px`;
        document.body.style.width = '100%';
        if (scrollbarWidth > 0) {
            document.body.style.paddingRight = scrollbarWidth + 'px';
        }
    }

    function unlockBodyScroll() {
        if (!isScrollLocked) return;
        isScrollLocked = false;
        document.documentElement.style.overflow = '';
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.width = '';
        document.body.style.paddingRight = '';
        if (scrollPos > 0) {
            window.scrollTo(0, scrollPos);
        }
        scrollbarWidth = 0;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const gridColumns = document.getElementById('gridColumns');
        const gridColumnsValue = document.getElementById('gridColumnsValue');
        if (gridColumns && gridColumnsValue) {
            gridColumns.addEventListener('input', function() {
                gridColumnsValue.textContent = this.value;
            });
        }
        updateUserButtonVisibility();
        window.addEventListener('resize', updateUserButtonVisibility);
        setupDragAndDrop();
        setupClipboardPaste();
        setupEditCropControls();
    });
    function updateUserButtonVisibility() {
        const desktopUserButtons = document.querySelectorAll('.desktop-user-button');
        const isMobile = window.innerWidth <= 768;
        desktopUserButtons.forEach(button => {
            button.style.display = isMobile ? 'none' : 'flex';
        });
    }
    function toggleMobileMenu() {
        closeAllOverlays(['mobileMenu']);
        const mobileMenu = document.getElementById('mobileMenu');
        mobileMenu.classList.toggle('active');
        activeOverlayId = mobileMenu.classList.contains('active') ? 'mobileMenu' : null;
        syncBodyScroll();
    }
    function openModal(modalId) {
        closeAllOverlays([modalId]);
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            activeOverlayId = modalId;
            lockBodyScroll();
            if (modalId === 'statsModal') setTimeout(renderStatsChart, 100);
        }
    }
    function showAppDialog({ title, message, confirmText = '<?php echo $translations['ok'] ?? 'OK'; ?>', cancelText = '', type = 'info' }) {
        return new Promise(resolve => {
            const dialog = document.getElementById('appDialog');
            const titleEl = document.getElementById('appDialogTitle');
            const messageEl = document.getElementById('appDialogMessage');
            const iconEl = document.getElementById('appDialogIcon');
            const confirmBtn = document.getElementById('appDialogConfirm');
            const cancelBtn = document.getElementById('appDialogCancel');
            if (!dialog || !titleEl || !messageEl || !confirmBtn || !cancelBtn || !iconEl) {
                resolve(true);
                return;
            }

            titleEl.textContent = title;
            messageEl.textContent = message;
            confirmBtn.textContent = confirmText;
            cancelBtn.textContent = cancelText;
            cancelBtn.style.display = cancelText ? '' : 'none';
            iconEl.className = 'dialog-icon';
            if (type === 'danger') iconEl.classList.add('dialog-icon--danger');
            if (type === 'success') iconEl.classList.add('dialog-icon--success');
            iconEl.innerHTML = type === 'danger'
                ? '<i class="fas fa-trash-alt"></i>'
                : (type === 'success' ? '<i class="fas fa-check"></i>' : '<i class="fas fa-info"></i>');

            const finish = value => {
                dialog.style.display = 'none';
                confirmBtn.onclick = null;
                cancelBtn.onclick = null;
                if (activeOverlayId === 'appDialog') activeOverlayId = null;
                syncBodyScroll();
                resolve(value);
            };
            confirmBtn.onclick = () => finish(true);
            cancelBtn.onclick = () => finish(false);
            dialog.style.display = 'flex';
            activeOverlayId = 'appDialog';
            lockBodyScroll();
        });
    }
    function showInfoDialog(message, type = 'info', title = '<?php echo $translations['message'] ?? 'Message'; ?>') {
        return showAppDialog({ title, message, type, confirmText: '<?php echo $translations['ok'] ?? 'OK'; ?>' });
    }
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
        if (activeOverlayId === modalId) activeOverlayId = null;
        syncBodyScroll();
    }
    function openInstallModal() {
        closeAllOverlays(['installModal']);
        backToInstallGrid();
        document.getElementById('installModal').style.display = 'block';
        activeOverlayId = 'installModal';
        lockBodyScroll();
    }
    function installDetailId(platform) {
        return 'install' + platform.split('_').map(s => s.charAt(0).toUpperCase() + s.slice(1)).join('') + 'Detail';
    }
    function showInstallInstructions(platform) {
        document.getElementById('installGrid').style.display = 'none';
        document.querySelectorAll('.install-detail').forEach(el => el.style.display = 'none');
        const detail = document.getElementById(installDetailId(platform));
        if (detail) {
            detail.style.display = 'block';
            detail.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
    function backToInstallGrid() {
        document.getElementById('installGrid').style.display = '';
        document.querySelectorAll('.install-detail').forEach(el => el.style.display = 'none');
    }
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            document.querySelectorAll('.album-card').forEach(album => {
                const title = album.querySelector('.album-title').textContent.toLowerCase();
                album.style.display = title.includes(searchTerm) ? 'block' : 'none';
            });
        });
    }
    function setupDragAndDrop() {
        const dropArea = document.getElementById('dropArea');
        if (!dropArea) return;
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });
        function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }
        ['dragenter', 'dragover'].forEach(eventName => dropArea.addEventListener(eventName, highlight, false));
        ['dragleave', 'drop'].forEach(eventName => dropArea.addEventListener(eventName, unhighlight, false));
        function highlight() {
            dropArea.style.borderColor = 'var(--primary-color)';
            dropArea.style.background = '<?php echo $color; ?>20';
        }
        function unhighlight() {
            dropArea.style.borderColor = '';
            dropArea.style.background = '';
        }
        dropArea.addEventListener('drop', handleDrop, false);
        function handleDrop(e) { handleFiles(e.dataTransfer.files); }
    }
    function handleFiles(files) {
        if (files.length > 0) {
            const file = files[0];
            if (file.type.startsWith('image/')) {
                setPreviewFromBlob(file);
            }
        }
    }
    function setPreviewFromBlob(blob) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const imagePreview = document.getElementById('imagePreview');
            const previewBase64 = document.getElementById('previewBase64');
            const fileInput = document.getElementById('fileInput');
            if (imagePreview) {
                imagePreview.src = e.target.result;
                imagePreview.style.display = 'block';
            }
            if (previewBase64) previewBase64.value = e.target.result;
            if (fileInput) fileInput.value = '';
        };
        reader.readAsDataURL(blob);
    }
    function setupClipboardPaste() {
        document.addEventListener('paste', function(e) {
            const addAlbumModal = document.getElementById('addAlbumModal');
            if (!addAlbumModal || addAlbumModal.style.display === 'none') return;
            const items = e.clipboardData?.items || [];
            for (const item of items) {
                if (item.type.startsWith('image/')) {
                    const file = item.getAsFile();
                    if (file) {
                        e.preventDefault();
                        setPreviewFromBlob(file);
                    }
                    return;
                }
            }
        });
    }
    async function pasteFromClipboard() {
        try {
            if (!navigator.clipboard || !navigator.clipboard.read) {
                throw new Error('Clipboard API is unavailable');
            }
            const clipboardItems = await navigator.clipboard.read();
            let pasted = false;
            for (const item of clipboardItems) {
                for (const type of item.types) {
                    if (type.startsWith('image/')) {
                        const blob = await item.getType(type);
                        setPreviewFromBlob(blob);
                        pasted = true;
                        break;
                    }
                }
                if (pasted) break;
            }
            if (!pasted) {
                throw new Error('Clipboard does not contain an image');
            }
        } catch (err) {
            showInfoDialog('<?php echo $translations["clipboard_error"] ?? "Error pasting from clipboard. Please try using file upload."; ?>');
        }
    }
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const imagePreview = document.getElementById('imagePreview');
                const previewBase64 = document.getElementById('previewBase64');
                if (imagePreview) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                }
                if (previewBase64) previewBase64.value = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    function saveSettings() {
        const gridColumns = document.getElementById('gridColumns').value;
        const fontSize = document.getElementById('fontSize').value;
        const albumsPerPage = document.getElementById('albumsPerPage').value;
        document.cookie = `grid_columns=${gridColumns}; path=/; max-age=31536000`;
        document.cookie = `font_size=${fontSize}; path=/; max-age=31536000`;
        document.cookie = `albums_per_page=${albumsPerPage}; path=/; max-age=31536000`;
        const albumsGrid = document.getElementById('albumsGrid');
        if (albumsGrid) albumsGrid.style.setProperty('--grid-columns', gridColumns);
        document.querySelectorAll('.album-title').forEach(title => { title.style.fontSize = `${fontSize}px`; });
        closeModal('settingsModal');
        setTimeout(() => location.reload(), 300);
    }
    async function deleteAlbum(albumId) {
        const confirmed = await showAppDialog({
            title: '<?php echo $translations["delete"] ?? "Delete"; ?>',
            message: '<?php echo $translations["confirm_delete"] ?? "Are you sure you want to delete this album?"; ?>',
            confirmText: '<?php echo $translations["delete"] ?? "Delete"; ?>',
            cancelText: '<?php echo $translations["cancel"] ?? "Cancel"; ?>',
            type: 'danger'
        });
        if (confirmed) {
            window.location.href = `?app=<?php echo $app; ?>&lang=<?php echo $lang; ?>&theme=<?php echo $theme; ?>&color=<?php echo urlencode($color); ?>&delete_album=${albumId}`;
        }
    }
    function toggleHideAlbum(albumId) {
        window.location.href = `?app=<?php echo $app; ?>&lang=<?php echo $lang; ?>&theme=<?php echo $theme; ?>&color=<?php echo urlencode($color); ?>&toggle_hide_album=${albumId}`;
    }
    function editAlbum(albumId) {
        const album = galleryAlbums.find(item => item.id === albumId);
        if (!album) {
            showInfoDialog('<?php echo $translations["album_not_found"] ?? "Album not found."; ?>');
            return;
        }

        document.getElementById('editAlbumId').value = album.id;
        document.getElementById('editAlbumName').value = album.name || '';
        document.getElementById('editAlbumUrl').value = album.url || '';
        document.getElementById('editMinRole').value = String(album.min_role || 0);
        document.getElementById('editAlbumCreated').value = formatDateTimeLocal(album.created);
        document.getElementById('editPreviewBase64').value = '';
        resetEditCropControls();
        if (album.preview) {
            loadEditCropImage(album.preview);
        } else {
            clearEditCropCanvas('<?php echo $translations["no_preview"] ?? "No preview selected"; ?>');
        }
        openModal('editAlbumModal');
    }
    function formatDateTimeLocal(value) {
        if (!value) return '';
        const normalized = String(value).replace(' ', 'T').slice(0, 16);
        return normalized.length === 16 ? normalized : '';
    }
    function setupEditCropControls() {
        ['editCropZoom', 'editCropX', 'editCropY'].forEach(id => {
            const input = document.getElementById(id);
            if (input) input.addEventListener('input', renderEditCrop);
        });
        clearEditCropCanvas('<?php echo $translations["no_preview"] ?? "No preview selected"; ?>');
    }
    function resetEditCropControls() {
        const zoom = document.getElementById('editCropZoom');
        const x = document.getElementById('editCropX');
        const y = document.getElementById('editCropY');
        if (zoom) zoom.value = '1';
        if (x) x.value = '0';
        if (y) y.value = '0';
    }
    function clearEditCropCanvas(message) {
        editCropImage = null;
        editCropHasImage = false;
        const canvas = document.getElementById('editCropCanvas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--surface-2').trim() || '#f5f7fb';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text-soft').trim() || '#66758a';
        ctx.font = '600 22px system-ui, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(message, canvas.width / 2, canvas.height / 2);
    }
    function loadEditCropImage(src) {
        const image = new Image();
        image.onload = () => {
            editCropImage = image;
            editCropHasImage = true;
            renderEditCrop();
        };
        image.onerror = () => clearEditCropCanvas('<?php echo $translations["preview_load_error"] ?? "Could not load preview"; ?>');
        image.src = src;
    }
    function loadEditPreviewFile(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        if (!file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = e => {
            resetEditCropControls();
            loadEditCropImage(e.target.result);
        };
        reader.readAsDataURL(file);
    }
    function renderEditCrop() {
        const canvas = document.getElementById('editCropCanvas');
        if (!canvas || !editCropImage || !editCropHasImage) return;
        const ctx = canvas.getContext('2d');
        const zoom = Number(document.getElementById('editCropZoom')?.value || 1);
        const offsetX = Number(document.getElementById('editCropX')?.value || 0) / 100;
        const offsetY = Number(document.getElementById('editCropY')?.value || 0) / 100;
        const baseScale = Math.max(canvas.width / editCropImage.naturalWidth, canvas.height / editCropImage.naturalHeight);
        const scale = baseScale * zoom;
        const width = editCropImage.naturalWidth * scale;
        const height = editCropImage.naturalHeight * scale;
        const maxX = Math.max(0, (width - canvas.width) / 2);
        const maxY = Math.max(0, (height - canvas.height) / 2);
        const drawX = (canvas.width - width) / 2 + offsetX * maxX;
        const drawY = (canvas.height - height) / 2 + offsetY * maxY;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(editCropImage, drawX, drawY, width, height);
    }
    function prepareEditPreviewForSubmit() {
        if (!editCropHasImage) return true;
        renderEditCrop();
        const canvas = document.getElementById('editCropCanvas');
        const previewBase64 = document.getElementById('editPreviewBase64');
        if (canvas && previewBase64) {
            previewBase64.value = canvas.toDataURL('image/jpeg', 0.9);
        }
        return true;
    }
    function exportData() {
        const data = {
            albums: <?php echo json_encode($albums); ?>,
            stats: <?php echo json_encode($stats); ?>,
            hiddenAlbums: <?php echo json_encode($hiddenAlbums); ?>,
            exportDate: new Date().toISOString()
        };
        const dataStr = JSON.stringify(data, null, 2);
        const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
        const exportFileDefaultName = 'albums_export_' + new Date().toISOString().split('T')[0] + '.json';
        const linkElement = document.createElement('a');
        linkElement.setAttribute('href', dataUri);
        linkElement.setAttribute('download', exportFileDefaultName);
        document.body.appendChild(linkElement);
        linkElement.click();
        document.body.removeChild(linkElement);
    }
    async function clearStats() {
        const confirmed = await showAppDialog({
            title: '<?php echo $translations["clear_stats"] ?? "Clear Statistics"; ?>',
            message: '<?php echo $translations["confirm_clear_stats"] ?? "Are you sure you want to clear all statistics?"; ?>',
            confirmText: '<?php echo $translations["clear_stats"] ?? "Clear Statistics"; ?>',
            cancelText: '<?php echo $translations["cancel"] ?? "Cancel"; ?>',
            type: 'danger'
        });
        if (confirmed) {
            fetch('clear_stats.php', { method: 'POST', headers: { 'Content-Type': 'application/json' } })
                .then(response => response.json())
                .then(async data => {
                    if (data.success) {
                        await showInfoDialog('<?php echo $translations["stats_cleared"] ?? "Statistics cleared successfully!"; ?>', 'success', '<?php echo $translations["clear_stats"] ?? "Clear Statistics"; ?>');
                        location.reload();
                    } else {
                        showInfoDialog('<?php echo $translations["clear_stats_error"] ?? "Error clearing statistics. Please try again."; ?>');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showInfoDialog('<?php echo $translations["clear_stats_error"] ?? "Error clearing statistics. Please try again."; ?>');
                });
        }
    }
    function viewAlbum(albumId, albumName, albumUrl) {
        fetch(`update_stats.php?album_id=${albumId}`)
            .then(response => response.json())
            .then(data => {
                if (data && data.success && Number.isFinite(Number(data.views))) {
                    updateAlbumViews(albumId, data.views);
                }
                showAlbumFrame(albumId, albumName, albumUrl);
            })
            .catch(error => {
                console.error('Error updating stats:', error);
                showAlbumFrame(albumId, albumName, albumUrl);
            });
    }
    function updateAlbumViews(albumId, views) {
        const card = Array.from(document.querySelectorAll('.album-card')).find(item => item.dataset.albumId === albumId);
        const counter = card?.querySelector('.album-views-count');
        if (counter) counter.textContent = views;
    }
    function showAlbumFrame(albumId, albumName, albumUrl) {
        closeAllOverlays(['frameOverlay']);
        const params = new URLSearchParams(window.location.search);
        params.set('view_album', albumId);
        history.replaceState(null, '', '?' + params.toString());
        const frameAlbumTitle = document.getElementById('frameAlbumTitle');
        const albumFrame = document.getElementById('albumFrame');
        const frameOverlay = document.getElementById('frameOverlay');
        if (frameAlbumTitle) frameAlbumTitle.textContent = albumName;
        if (albumFrame) albumFrame.src = albumUrl;
        if (frameOverlay) {
            frameOverlay.style.display = 'block';
            activeOverlayId = 'frameOverlay';
            lockBodyScroll();
        }
    }
    function closeFrame() {
        const frameOverlay = document.getElementById('frameOverlay');
        const albumFrame = document.getElementById('albumFrame');
        if (frameOverlay) frameOverlay.style.display = 'none';
        if (albumFrame) albumFrame.src = '';
        if (activeOverlayId === 'frameOverlay') activeOverlayId = null;
        syncBodyScroll();
    }
    function closeAllOverlays(exceptIds = []) {
        document.querySelectorAll('.modal').forEach(modal => {
            if (!exceptIds.includes(modal.id)) modal.style.display = 'none';
        });
        const installModal = document.getElementById('installModal');
        if (installModal && !exceptIds.includes('installModal')) installModal.style.display = 'none';
        const mobileMenu = document.getElementById('mobileMenu');
        if (mobileMenu && !exceptIds.includes('mobileMenu')) mobileMenu.classList.remove('active');
        const frameOverlay = document.getElementById('frameOverlay');
        const albumFrame = document.getElementById('albumFrame');
        if (frameOverlay && !exceptIds.includes('frameOverlay')) frameOverlay.style.display = 'none';
        if (albumFrame && !exceptIds.includes('frameOverlay')) albumFrame.src = '';
        if (exceptIds.length === 0 || !exceptIds.includes(activeOverlayId)) activeOverlayId = exceptIds[0] || null;
        syncBodyScroll();
    }
    function syncBodyScroll() {
        const hasOpenModal = Array.from(document.querySelectorAll('.modal')).some(modal => modal.style.display === 'flex');
        const installOpen = document.getElementById('installModal')?.style.display === 'block';
        const menuOpen = document.getElementById('mobileMenu')?.classList.contains('active');
        const frameOpen = document.getElementById('frameOverlay')?.style.display === 'block';
        if (hasOpenModal || installOpen || menuOpen || frameOpen) {
            lockBodyScroll();
        } else {
            unlockBodyScroll();
        }
    }
    function renderStatsChart() {
        const ctx = document.getElementById('statsChart');
        if (!ctx) return;
        const albums = <?php echo json_encode($albums); ?>;
        const stats = <?php echo json_encode($stats); ?>;
        const sortedAlbums = [...albums].sort((a, b) => (stats[b.id] || 0) - (stats[a.id] || 0));
        const topAlbums = sortedAlbums.slice(0, 10);
        const labels = topAlbums.map(a => a.name.length > 20 ? a.name.substring(0, 20) + '...' : a.name);
        const data = topAlbums.map(a => stats[a.id] || 0);
        if (window.statsChartInstance) window.statsChartInstance.destroy();
        window.statsChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '<?php echo $translations['views'] ?? "Views"; ?>',
                    data: data,
                    backgroundColor: '<?php echo $color; ?>',
                    borderColor: '<?php echo $color; ?>',
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '<?php echo $theme === 'dark' ? '#48484a' : '#eee'; ?>', drawBorder: false },
                        ticks: { color: '<?php echo $theme === 'dark' ? '#98989e' : '#666'; ?>', padding: 10 }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '<?php echo $theme === 'dark' ? '#98989e' : '#666'; ?>', maxRotation: 45, minRotation: 45, padding: 10 }
                    }
                },
                plugins: {
                    legend: { labels: { color: '<?php echo $theme === 'dark' ? '#f2f2f2' : '#333'; ?>', padding: 20, font: { size: 14 } } },
                    tooltip: {
                        backgroundColor: '<?php echo $theme === 'dark' ? '#3a3a3a' : '#fff'; ?>',
                        titleColor: '<?php echo $theme === 'dark' ? '#f2f2f2' : '#333'; ?>',
                        bodyColor: '<?php echo $theme === 'dark' ? '#f2f2f2' : '#333'; ?>',
                        borderColor: '<?php echo $color; ?>',
                        borderWidth: 1
                    }
                },
                layout: { padding: { top: 20, bottom: 20 } }
            }
        });
    }
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            closeModal(event.target.id);
        }
        if (event.target.classList.contains('install-modal')) {
            closeModal('installModal');
        }
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllOverlays();
        }
    });
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js').catch(err => {
                console.log('ServiceWorker registration failed: ', err);
            });
        });
    }
    window.addEventListener('orientationchange', function() {
        setTimeout(updateUserButtonVisibility, 300);
    });

    /* ── Download Notification Toast ── */
    let notificationTimer = null;
    let notificationShown = false;

    function scheduleNotification() {
        if (notificationDismissed || notificationShown) return;
        const minDelay = 30 * 1000;       // 30 seconds
        const maxDelay = 5 * 60 * 1000;    // 5 minutes
        const delay = Math.floor(Math.random() * (maxDelay - minDelay + 1)) + minDelay;
        notificationTimer = setTimeout(showNotification, delay);
    }

    function showNotification() {
        if (notificationDismissed || notificationShown) return;
        notificationShown = true;
        const toast = document.getElementById('downloadNotification');
        if (toast) {
            toast.classList.add('show');
        }
    }

    function dismissNotification() {
        notificationDismissed = true;
        notificationShown = true;
        if (notificationTimer) {
            clearTimeout(notificationTimer);
            notificationTimer = null;
        }
        const toast = document.getElementById('downloadNotification');
        if (toast) {
            toast.classList.remove('show');
        }
        fetch('dismiss_notification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        }).catch(function(err) {
            console.error('Failed to dismiss notification:', err);
        });
    }

    // Start the notification timer after page load
    if (document.readyState === 'complete') {
        scheduleNotification();
    } else {
        window.addEventListener('load', scheduleNotification);
    }
</script>
