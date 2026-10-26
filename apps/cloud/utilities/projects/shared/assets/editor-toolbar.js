(function () {
    function isHidden(el) {
        return getComputedStyle(el).display === 'none';
    }
    function initToolbar(tb) {
        if (tb.__tbInit) return;
        tb.__tbInit = true;
        const items = Array.from(tb.children).filter(c => !c.classList.contains('tb-pin'));
        const pinned = Array.from(tb.children).find(c => c.classList.contains('tb-pin'));
        const more = document.createElement('button');
        more.type = 'button';
        more.className = 'toolbar-more';
        more.innerHTML = '&hellip;';
        const menu = document.createElement('div');
        menu.className = 'toolbar-more-menu';
        document.body.appendChild(menu);
        tb.insertBefore(more, pinned || null);
        function closeMenu() {
            menu.classList.remove('show');
            more.classList.remove('active');
        }
        function fit() {
            closeMenu();
            items.forEach(i => tb.insertBefore(i, more));
            more.style.display = 'none';
            if (tb.scrollWidth <= tb.clientWidth) return;
            more.style.display = 'flex';
            for (let i = items.length - 1; i >= 0 && tb.scrollWidth > tb.clientWidth; i--) {
                if (isHidden(items[i])) continue;
                menu.insertBefore(items[i], menu.firstChild);
            }
            if (!menu.children.length) more.style.display = 'none';
        }
        more.addEventListener('click', e => {
            e.stopPropagation();
            const open = !menu.classList.contains('show');
            closeMenu();
            if (!open) return;
            const r = more.getBoundingClientRect();
            menu.style.top = r.bottom + 4 + 'px';
            menu.style.right = Math.max(8, window.innerWidth - r.right) + 'px';
            menu.classList.add('show');
            more.classList.add('active');
        });
        menu.addEventListener('click', e => { if (e.target.closest('button')) closeMenu(); });
        document.addEventListener('click', e => { if (!menu.contains(e.target) && e.target !== more) closeMenu(); });
        const schedule = () => requestAnimationFrame(fit);
        new ResizeObserver(schedule).observe(tb);
        window.addEventListener('resize', schedule);
        window.addEventListener('load', schedule);
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(schedule);
        setTimeout(schedule, 400);
        fit();
    }
    document.querySelectorAll('.editor-toolbar, .tb-overflow').forEach(initToolbar);
})();
