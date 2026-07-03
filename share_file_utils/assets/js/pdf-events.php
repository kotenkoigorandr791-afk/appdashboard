
<script>
<?php if ($fileType === 'pdf'): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Назначаем обработчики после инициализации
    setTimeout(() => {
        document.getElementById('pdf-prev')?.addEventListener('click', pdfPrevPage);
        document.getElementById('pdf-next')?.addEventListener('click', pdfNextPage);
        document.getElementById('pdf-zoom-in')?.addEventListener('click', () => updatePDFScale(pdfScale + 0.25));
        document.getElementById('pdf-zoom-out')?.addEventListener('click', () => updatePDFScale(pdfScale - 0.25));
        document.getElementById('pdf-zoom-select')?.addEventListener('change', (e) => updatePDFScale(parseFloat(e.target.value)));
        document.getElementById('pdf-fit')?.addEventListener('click', () => {
            const container = document.getElementById('pdf-viewer-container');
            const containerWidth = container.clientWidth - 40;
            updatePDFScale(containerWidth / 612);
        });
        document.getElementById('pdf-rotate-left')?.addEventListener('click', () => {
            pdfRotation = (pdfRotation - 90) % 360;
            renderAllPDFPages();
        });
        document.getElementById('pdf-rotate-right')?.addEventListener('click', () => {
            pdfRotation = (pdfRotation + 90) % 360;
            renderAllPDFPages();
        });
        
        document.getElementById('pdf-page-input')?.addEventListener('change', function() {
            const num = parseInt(this.value);
            if (num >= 1 && num <= pdfTotalPages && pdfPages[num]) {
                pdfPages[num].scrollIntoView({behavior: 'smooth', block: 'start'});
            } else {
                this.value = document.getElementById('pdf-page-input').value;
            }
        });
        
        const container = document.getElementById('pdf-viewer-container');
        let scrollTimeout;
        container?.addEventListener('scroll', () => {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                if (pdfPages.length) {
                    const containerTop = container.scrollTop;
                    const containerHeight = container.clientHeight;
                    for (let i = 1; i <= pdfTotalPages; i++) {
                        if (pdfPages[i]) {
                            const pageTop = pdfPages[i].offsetTop;
                            const pageBottom = pageTop + pdfPages[i].offsetHeight;
                            if (pageTop <= containerTop + containerHeight/2 && pageBottom >= containerTop + containerHeight/2) {
                                document.getElementById('pdf-page-input').value = i;
                                break;
                            }
                        }
                    }
                }
            }, 50);
        });
        
        // Ctrl + Wheel для зума
        container?.addEventListener('wheel', (e) => {
            if (e.ctrlKey) {
                e.preventDefault();
                const delta = e.deltaY > 0 ? -0.1 : 0.1;
                const newScale = Math.max(0.25, Math.min(3, pdfScale + delta));
                if (Math.abs(newScale - pdfScale) > 0.01) {
                    updatePDFScale(newScale);
                }
            }
        }, {passive: false});
        
        // Глобальные клавиши
        document.addEventListener('keydown', e => {
            if (e.target.tagName === 'INPUT') return;
            if (e.key === 'ArrowLeft') pdfPrevPage();
            if (e.key === 'ArrowRight') pdfNextPage();
            if (e.key === '+' || e.key === '=') updatePDFScale(pdfScale + 0.25);
            if (e.key === '-') updatePDFScale(pdfScale - 0.25);
            if (e.key === '0') {
                const container = document.getElementById('pdf-viewer-container');
                const containerWidth = container.clientWidth - 40;
                updatePDFScale(containerWidth / 612);
            }
        });
    }, 500);
});
<?php endif; ?>
</script>
