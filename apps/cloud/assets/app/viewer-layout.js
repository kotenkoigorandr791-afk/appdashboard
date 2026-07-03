(function() {
    const MOBILE_MQ = '(max-width: 768px)';
    const COMPACT_WIDTH = 420;
    function syncViewerLayout() {
        const content = document.querySelector('.content');
        const viewer = document.getElementById('viewer');
        if (!content || !viewer) return;
        const width = content.getBoundingClientRect().width;
        const compact = window.matchMedia(MOBILE_MQ).matches && width <= COMPACT_WIDTH;
        content.classList.toggle('viewer-area-compact', compact);
        viewer.classList.toggle('viewer-compact', compact);
    }
    function initViewerLayoutSync() {
        syncViewerLayout();
        window.addEventListener('resize', syncViewerLayout);
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            new MutationObserver(syncViewerLayout).observe(sidebar, { attributes: true, attributeFilter: ['class'] });
        }
        const layout = document.querySelector('.layout');
        if (layout) {
            new MutationObserver(syncViewerLayout).observe(layout, { attributes: true, attributeFilter: ['class'] });
        }
    }
    window.syncViewerLayout = syncViewerLayout;
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initViewerLayoutSync);
    } else {
        initViewerLayoutSync();
    }
})();
