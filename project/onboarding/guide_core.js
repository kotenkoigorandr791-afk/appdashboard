(function () {
    'use strict';

    var DATA = window.__guideData || { ui: {}, steps: [] };
    var FORCE = window.__guideForce === true;

    if (!DATA.steps || !DATA.steps.length) return;

    var ui = DATA.ui || {};
    var steps = DATA.steps.map(function (s) {
        return {
            target: s[0],
            advance: s[1] || 'next',
            enter: s[2] || [],
            leave: s[3] || [],
            title: s[4],
            text: s[5],
            highlight: s[6] !== false
        };
    });

    var ACTIONS = {
        weather_on: function () {
            var m = byId('weatherModalOverlay');
            if (m && m.style.display !== 'flex') {
                var w = byId('weatherWidget');
                if (w) w.click();
                else if (typeof window.showWeatherModal === 'function') window.showWeatherModal();
            }
        },
        weather_off: function () {
            var m = byId('weatherModalOverlay');
            if (m) { m.classList.remove('active'); m.style.display = 'none'; }
        },
        notif_on: function () {
            var p = byId('notifications-panel');
            if (p) p.classList.add('open');
        },
        notif_off: function () {
            var p = byId('notifications-panel');
            if (p) p.classList.remove('open');
        },
        chat_on: function () {
            var m = byId('support-chat-modal');
            if (m && m.style.display !== 'flex') m.style.display = 'flex';
        },
        chat_off: function () {
            var m = byId('support-chat-modal');
            if (m) m.style.display = 'none';
        },
        sidebar_on: function () {
            var s = byId('sidebar');
            if (s) s.classList.add('open');
        },
        sidebar_off: function () {
            var s = byId('sidebar');
            if (s) s.classList.remove('open');
        },
        settings_on: function () {
            var m = byId('settings-modal');
            if (m && m.style.display !== 'flex') m.style.display = 'flex';
        },
        settings_off: function () {
            var m = byId('settings-modal');
            if (m) m.style.display = 'none';
        },
        shortcuts_on: function () {
            var m = byId('shortcuts-modal');
            if (m && m.style.display !== 'flex') {
                var b = byId('shortcuts-help-button');
                if (b) b.click();
                else m.style.display = 'flex';
            }
        },
        shortcuts_off: function () {
            var m = byId('shortcuts-modal');
            if (m) m.style.display = 'none';
        },
        sessions_on: function () {
            var m = byId('sessions-modal');
            if (m && m.style.display !== 'flex') {
                var b = byId('sessions-button');
                if (b) b.click();
                else m.style.display = 'flex';
            }
        },
        sessions_off: function () {
            var m = byId('sessions-modal');
            if (m) m.style.display = 'none';
        },
        shared_files_on: function () {
            var m = byId('shared-files-modal');
            if (m && m.style.display !== 'flex') {
                var b = byId('shared-files-button');
                if (b) b.click();
                else m.style.display = 'flex';
            }
        },
        shared_files_off: function () {
            var m = byId('shared-files-modal');
            if (m) m.style.display = 'none';
        },
        qr_on: function () {
            var m = byId('qr-modal');
            if (m && m.style.display !== 'flex') {
                var b = byId('qr-button');
                if (b) b.click();
                else m.style.display = 'flex';
            }
        },
        qr_off: function () {
            var m = byId('qr-modal');
            if (m) m.style.display = 'none';
        },
        profile_on: function () {
            var m = byId('profile-modal');
            if (m && m.style.display !== 'flex') m.style.display = 'flex';
        },
        profile_off: function () {
            var m = byId('profile-modal');
            if (m) m.style.display = 'none';
        },
        otp_gen_on: function () {
            var m = byId('otp-gen-modal');
            if (m && m.style.display !== 'flex') {
                if (typeof window.openOtpGeneratorModal === 'function') window.openOtpGeneratorModal();
                else m.style.display = 'flex';
            }
        },
        otp_gen_off: function () {
            var m = byId('otp-gen-modal');
            if (m) m.style.display = 'none';
        },
        search_on: function () {
            var c = document.querySelector('.search-container');
            if (!c || !c.parentElement) return;
            var bar = c.parentElement;
            var actions = bar.querySelector('.bottom-bar-actions');
            var gap = parseFloat(window.getComputedStyle(bar).gap) || 0;
            var barRect = bar.getBoundingClientRect();
            var cRect = c.getBoundingClientRect();
            var avail = barRect.right - cRect.left;
            if (actions) {
                var aRect = actions.getBoundingClientRect();
                avail = aRect.left - cRect.left - gap;
            }
            avail = Math.max(36, Math.min(avail, barRect.width - 8));
            c.style.width = avail + 'px';
            c.classList.add('open');
        },
        search_off: function () {
            var c = document.querySelector('.search-container');
            if (c) {
                c.classList.remove('open');
                c.style.width = '';
            }
        },
        trusted_on: function () {
            var c = byId('add-trusted-device-container');
            if (c && !byId('add-trusted-device-btn')) c.style.minHeight = '48px';
        },
        trusted_off: function () {
            var c = byId('add-trusted-device-container');
            if (c) c.style.minHeight = '';
        }
    };

    var ALL_OFF = ['weather_off', 'notif_off', 'chat_off', 'sidebar_off', 'settings_off', 'shortcuts_off', 'sessions_off', 'shared_files_off', 'qr_off', 'profile_off', 'otp_gen_off', 'search_off', 'trusted_off'];

    var idx = 0;
    var running = false;
    var collapsed = false;
    var rafPending = false;
    var clickEl = null;
    var clickTimer = null;
    var dragState = null;
    var renderSeq = 0;

    var elLayer, elDims, elHole, elTip, elTitle, elText, elPage, elPrev, elNext, elMin, elFab;

    var MIN_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7 20 5-5 5 5"/><path d="m7 4 5 5 5-5"/></svg>';
    var FAB_ICON = '<svg class="guide-fab-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>';

    function byId(id) { return document.getElementById(id); }

    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function visibleFirst(sel) {
        var nodes = document.querySelectorAll(sel);
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].offsetWidth || nodes[i].offsetHeight || nodes[i].getClientRects().length) return nodes[i];
        }
        return nodes[0] || null;
    }

    function isScrollable(el) {
        if (!el || el === document.body || el === document.documentElement) return false;
        try {
            var cs = window.getComputedStyle(el);
            if (!/(auto|scroll|overlay)/.test(cs.overflow + cs.overflowX + cs.overflowY)) return false;
        } catch (e) {}
        return el.scrollHeight > el.clientHeight + 1 || el.scrollWidth > el.clientWidth + 1;
    }

    function scrollParent(el) {
        for (var p = el && el.parentElement; p; p = p.parentElement) {
            if (isScrollable(p)) return p;
        }
        return document.scrollingElement || document.documentElement;
    }

    function clippedRect(el) {
        var r = el.getBoundingClientRect();
        var out = { left: r.left, top: r.top, right: r.right, bottom: r.bottom, width: r.width, height: r.height };
        var vw = document.documentElement.clientWidth;
        var vh = document.documentElement.clientHeight;
        out.left = Math.max(0, out.left);
        out.top = Math.max(0, out.top);
        out.right = Math.min(vw, out.right);
        out.bottom = Math.min(vh, out.bottom);
        for (var p = el.parentElement; p; p = p.parentElement) {
            if (!isScrollable(p)) continue;
            var pr = p.getBoundingClientRect();
            out.left = Math.max(out.left, pr.left);
            out.top = Math.max(out.top, pr.top);
            out.right = Math.min(out.right, pr.right);
            out.bottom = Math.min(out.bottom, pr.bottom);
        }
        out.width = Math.max(0, out.right - out.left);
        out.height = Math.max(0, out.bottom - out.top);
        if (el.id === 'app-list') {
            out.left = Math.max(out.left, r.left);
            out.right = Math.min(out.right, r.left + el.clientWidth);
            out.top = Math.max(out.top, r.top);
            out.bottom = Math.min(out.bottom, r.top + el.clientHeight);
            out.width = Math.max(0, out.right - out.left);
            out.height = Math.max(0, out.bottom - out.top);
        }
        return out;
    }

    function scrollTargetIntoView(el) {
        var parent = scrollParent(el);
        try {
            el.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'auto' });
        } catch (e) {
            try { el.scrollIntoView(); } catch (err) {}
        }
        if (parent && parent !== document.scrollingElement && parent !== document.documentElement && parent !== document.body) {
            var r = el.getBoundingClientRect();
            var pr = parent.getBoundingClientRect();
            if (r.top < pr.top || r.bottom > pr.bottom) {
                if (r.height <= pr.height) parent.scrollTop += r.top - pr.top - (pr.height - r.height) / 2;
                else parent.scrollTop = 0;
            }
            if (r.left < pr.left || r.right > pr.right) {
                if (r.width <= pr.width) parent.scrollLeft += r.left - pr.left - (pr.width - r.width) / 2;
                else parent.scrollLeft = 0;
            }
        }
    }

    function runActions(actions) {
        for (var i = 0; i < actions.length; i++) {
            if (typeof ACTIONS[actions[i]] === 'function') {
                try { ACTIONS[actions[i]](); } catch (e) {}
            }
        }
    }

    function build() {
        elLayer = document.createElement('div');
        elLayer.id = 'guide-layer';
        elDims = document.createElement('div');
        elDims.id = 'guide-dims';
        elHole = document.createElement('div');
        elHole.id = 'guide-hole';
        elTip = document.createElement('div');
        elTip.id = 'guide-tip';
        elTip.innerHTML =
            '<span class="guide-tip-arrow" aria-hidden="true"></span>' +
            '<div class="guide-tip-head">' +
                '<div class="guide-tip-title"></div>' +
                '<button type="button" class="guide-minimize" title="' + esc(ui.minimize || 'Minimize') + '">' + MIN_ICON + '</button>' +
            '</div>' +
            '<div class="guide-tip-text"></div>' +
            '<div class="guide-tip-foot">' +
                '<span class="guide-page"></span>' +
                '<span class="guide-actions">' +
                    '<button type="button" class="guide-btn" data-act="prev"></button>' +
                    '<button type="button" class="guide-btn guide-btn-primary" data-act="next"></button>' +
                '</span>' +
            '</div>';
        elTitle = elTip.querySelector('.guide-tip-title');
        elText = elTip.querySelector('.guide-tip-text');
        elPage = elTip.querySelector('.guide-page');
        elPrev = elTip.querySelector('[data-act="prev"]');
        elNext = elTip.querySelector('[data-act="next"]');
        elMin = elTip.querySelector('.guide-minimize');
        elLayer.appendChild(elDims);
        elLayer.appendChild(elHole);
        elLayer.appendChild(elTip);
        document.body.appendChild(elLayer);
        elPrev.textContent = ui.prev || 'Back';
        elPrev.addEventListener('click', function () { go(idx - 1); });
        elNext.addEventListener('click', function () {
            if (idx >= steps.length - 1) end(true);
            else go(idx + 1);
        });
        elMin.addEventListener('click', function(e) { e.stopPropagation(); collapse(); });
        elHole.addEventListener('click', function (e) {
            if (!running || collapsed || !steps[idx] || steps[idx].advance !== 'click') return;
            var target = visibleFirst(steps[idx].target);
            if (!target) return;
            e.preventDefault();
            e.stopPropagation();
            target.click();
            onTargetClick();
        });
        elTip.addEventListener('click', function (e) { e.stopPropagation(); });
        elTip.addEventListener('pointerdown', onTipPointerDown);
        elTip.addEventListener('pointermove', onTipPointerMove);
        elTip.addEventListener('pointerup', onTipPointerUp);
        elTip.addEventListener('pointercancel', onTipPointerUp);
    }

    function onTipPointerDown(e) {
        var t = e.target;
        if (t) {
            var interactive = t.closest ? (t.closest('.guide-minimize') || t.closest('.guide-btn')) : (t.classList && (t.classList.contains('guide-minimize') || t.classList.contains('guide-btn')));
            if (interactive) return;
        }
        if (e.button !== undefined && e.button !== 0) return;
        dragState = {
            startX: e.clientX,
            startY: e.clientY,
            origLeft: parseInt(elTip.style.left, 10) || 0,
            origTop: parseInt(elTip.style.top, 10) || 0
        };
        try { if (elTip.setPointerCapture) elTip.setPointerCapture(e.pointerId); } catch (err) {}
        if (e.preventDefault) e.preventDefault();
    }

    function onTipPointerMove(e) {
        if (!dragState) return;
        var vw = document.documentElement.clientWidth;
        var vh = document.documentElement.clientHeight;
        var tw = elTip.offsetWidth;
        var th = elTip.offsetHeight;
        var left = dragState.origLeft + (e.clientX - dragState.startX);
        var top = dragState.origTop + (e.clientY - dragState.startY);
        left = Math.max(8, Math.min(left, vw - tw - 8));
        top = Math.max(8, Math.min(top, vh - th - 8));
        elTip.style.left = left + 'px';
        elTip.style.top = top + 'px';
        elTip.style.visibility = 'visible';
        if (e.preventDefault) e.preventDefault();
    }

    function onTipPointerUp() {
        dragState = null;
    }

    function positionTip(r) {
        var vw = document.documentElement.clientWidth;
        var vh = document.documentElement.clientHeight;
        if (dragState) return;
        if (!r) r = { top: 0, left: 0, right: vw, bottom: vh, width: 0, height: 0 };
        elTip.style.visibility = 'hidden';
        elTip.style.top = '-9999px';
        var tw = elTip.offsetWidth;
        var th = elTip.offsetHeight;
        var top, left, place = 'fallback';
        if (r.bottom + 12 + th <= vh - 8) {
            place = 'bottom';
            top = r.bottom + 12;
            left = r.left + (r.width || 0) / 2 - tw / 2;
        } else if (r.top - th - 12 >= 8) {
            place = 'top';
            top = r.top - th - 12;
            left = r.left + (r.width || 0) / 2 - tw / 2;
        } else if (vw - r.right - 12 >= tw + 8) {
            place = 'right';
            top = r.top + (r.height || 0) / 2 - th / 2;
            left = r.right + 12;
        } else if (r.left - tw - 12 >= 8) {
            place = 'left';
            top = r.top + (r.height || 0) / 2 - th / 2;
            left = r.left - tw - 12;
        } else {
            place = 'fallback';
            top = vh - th - 12;
            left = (vw - tw) / 2;
        }
        top = Math.max(8, Math.min(top, vh - th - 8));
        left = Math.max(8, Math.min(left, vw - tw - 8));
        elTip.style.top = top + 'px';
        elTip.style.left = left + 'px';
        setTipArrow(place, r, left, top, tw, th);
        elTip.style.visibility = 'visible';
    }

    function setTipArrow(place, r, tipLeft, tipTop, tipW, tipH) {
        var arrow = elTip.querySelector('.guide-tip-arrow');
        if (!arrow) return;
        elTip.className = String(elTip.className || '').replace(/\bguide-pos-\S+/g, '').trim();
        if (place === 'fallback') {
            elTip.classList.add('guide-pos-fallback');
            return;
        }
        elTip.classList.add('guide-pos-' + place);
        arrow.style.left = '';
        arrow.style.top = '';
        arrow.style.right = '';
        arrow.style.bottom = '';
        var size = 12;
        var gap = 20;
        var off;
        if (place === 'bottom' || place === 'top') {
            off = r.left + (r.width || 0) / 2 - tipLeft - size / 2;
            off = Math.max(size / 2 + gap, Math.min(off, tipW - size / 2 - gap));
            arrow.style.left = off + 'px';
        } else {
            off = r.top + (r.height || 0) / 2 - tipTop - size / 2;
            off = Math.max(size / 2 + gap, Math.min(off, tipH - size / 2 - gap));
            arrow.style.top = off + 'px';
        }
    }

    function positionTipBottom() {
        var vw = document.documentElement.clientWidth;
        var vh = document.documentElement.clientHeight;
        if (dragState) return;
        elTip.style.visibility = 'hidden';
        elTip.style.top = '-9999px';
        var tw = elTip.offsetWidth;
        var th = elTip.offsetHeight;
        var top = vh - th - 20;
        if (top < 12) top = 12;
        var left = (vw - tw) / 2;
        left = Math.max(12, Math.min(left, vw - tw - 12));
        elTip.style.top = top + 'px';
        elTip.style.left = left + 'px';
        setTipArrow('fallback', null, 0, 0, 0, 0);
        elTip.style.visibility = 'visible';
    }

    function rectsEqual(a, b) {
        return a && b && Math.abs(a.left - b.left) < 0.5 && Math.abs(a.top - b.top) < 0.5 && Math.abs(a.right - b.right) < 0.5 && Math.abs(a.bottom - b.bottom) < 0.5 && Math.abs(a.width - b.width) < 0.5 && Math.abs(a.height - b.height) < 0.5;
    }

    function targetRadius(el, r) {
        var cssRadius = '';
        try {
            var cs = window.getComputedStyle(el);
            if (cs) cssRadius = String(cs.borderRadius || '').trim();
        } catch (e) {}
        var px = 12;
        if (cssRadius && cssRadius !== '0px' && cssRadius !== '0%') {
            var first = cssRadius.split(/[ /]/)[0];
            if (first.indexOf('%') > -1) px = Math.min(r.width, r.height) * parseFloat(first) / 100;
            else px = parseFloat(first) || px;
        } else {
            var ratio = r.height > 0 ? r.width / r.height : 1;
            px = (ratio >= 0.85 && ratio <= 1.18) ? Math.min(r.width, r.height) / 2 : 12;
            cssRadius = (ratio >= 0.85 && ratio <= 1.18) ? '50%' : '12px';
        }
        px = Math.max(0, Math.min(px, Math.min(r.width, r.height) / 2));
        return { css: (px + 6) + 'px', px: px + 6 };
    }

    function drawMask(r, radius) {
        var vw = document.documentElement.clientWidth;
        var vh = document.documentElement.clientHeight;
        var x = Math.max(-8, r.left - 6);
        var y = Math.max(-8, r.top - 6);
        var w = Math.max(0, r.width + 12);
        var h = Math.max(0, r.height + 12);
        elDims.innerHTML =
            '<svg class="guide-dim-svg" width="' + vw + '" height="' + vh + '" viewBox="0 0 ' + vw + ' ' + vh + '" preserveAspectRatio="none" aria-hidden="true">' +
                '<defs><mask id="guide-mask"><rect x="0" y="0" width="' + vw + '" height="' + vh + '" fill="white"/><rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '" rx="' + radius.px + '" ry="' + radius.px + '" fill="black"/></mask></defs>' +
                '<rect x="0" y="0" width="' + vw + '" height="' + vh + '" fill="rgba(6,10,16,0.55)" mask="url(#guide-mask)"/>' +
            '</svg>';
    }

    function showHoleResult(el, r) {
        if (collapsed || !running || el !== visibleFirst(steps[idx].target)) return;
        var vw = document.documentElement.clientWidth;
        var vh = document.documentElement.clientHeight;
        r = clippedRect(el);
        if (!(r.right > 0 && r.left < vw && r.bottom > 0 && r.top < vh)) {
            elHole.style.display = 'none';
            elDims.innerHTML = '';
            positionTipBottom();
            return;
        }
        var radius = targetRadius(el, r);
        elHole.style.display = 'block';
        elHole.style.pointerEvents = steps[idx].advance === 'click' ? 'auto' : 'none';
        elHole.style.borderRadius = radius.css;
        elHole.style.left = (r.left - 6) + 'px';
        elHole.style.top = (r.top - 6) + 'px';
        elHole.style.width = (r.width + 12) + 'px';
        elHole.style.height = (r.height + 12) + 'px';
        drawMask(r, radius);
        positionTip(r);
    }

    function paintCurrent() {
        if (collapsed || !running) return;
        var s = steps[idx];
        var el = visibleFirst(s.target);
        if (!el || s.highlight === false) return;
        showHoleResult(el, clippedRect(el));
    }

    function measureStable(el, s, seq, prev, stable, ticks) {
        stable = stable || 0;
        ticks = ticks || 0;
        if (ticks > 24 || seq !== renderSeq) return;
        requestAnimationFrame(function () {
            if (collapsed || !running || steps[idx] !== s) return;
            var r = clippedRect(el);
            if (rectsEqual(prev, r)) stable++;
            else stable = 0;
            if (stable >= 2 || ticks >= 24) {
                showHoleResult(el, r);
                return;
            }
            measureStable(el, s, seq, r, stable, ticks + 1);
        });
    }

    function render() {
        if (collapsed) return;
        var s = steps[idx];
        elTitle.textContent = s.title || '';
        elText.textContent = s.text || '';
        elPage.textContent = (idx + 1) + ' ' + (ui.of || 'of') + ' ' + steps.length;
        elPrev.disabled = idx === 0;
        elNext.textContent = idx === steps.length - 1 ? (ui.finish || 'Finish') : (ui.next || 'Next');
        elLayer.style.display = 'block';
        var el = visibleFirst(s.target);
        if (!el || s.highlight === false) {
            renderSeq++;
            elHole.style.display = 'none';
            elDims.innerHTML = '';
            positionTipBottom();
            return;
        }
        var vw = document.documentElement.clientWidth;
        var vh = document.documentElement.clientHeight;
        scrollTargetIntoView(el);
        var r = clippedRect(el);
        var onscreen = r.right > 0 && r.left < vw && r.bottom > 0 && r.top < vh;
        if (!onscreen || r.width < 8 || r.height < 8) {
            scrollTargetIntoView(el);
            r = clippedRect(el);
        }
        renderSeq++;
        elHole.style.display = 'none';
        elDims.innerHTML = '';
        elTip.style.visibility = 'hidden';
        measureStable(el, s, renderSeq, r, 0, 0);
    }

    function onViewport() {
        if (rafPending || !running || collapsed) return;
        rafPending = true;
        requestAnimationFrame(function () {
            rafPending = false;
            if (running && !collapsed) paintCurrent();
        });
    }

    function onTargetClick() {
        if (steps[idx].advance !== 'click') return;
        if (clickTimer) return;
        clickTimer = setTimeout(function () {
            clickTimer = null;
            go(idx + 1);
        }, 60);
    }

    function attachClick() {
        detachClick();
        var s = steps[idx];
        if (s.advance !== 'click') return;
        var el = visibleFirst(s.target);
        if (!el) return;
        clickEl = el;
        el.addEventListener('click', onTargetClick, true);
    }

    function detachClick() {
        if (clickEl) {
            clickEl.removeEventListener('click', onTargetClick, true);
            clickEl = null;
        }
    }

    function scheduleReRender() {
        setTimeout(function () {
            if (running && !collapsed) onViewport();
        }, 450);
    }

    function buildFab() {
        if (elFab) return;
        elFab = document.createElement('div');
        elFab.id = 'guide-fab';
        elFab.setAttribute('role', 'button');
        elFab.tabIndex = 0;
        elFab.innerHTML = FAB_ICON +
            '<span class="guide-fab-label">' + esc(ui.resume || 'Continue tour') + '</span>' +
            '<button type="button" class="guide-fab-close" title="' + esc(ui.close || 'Close') + '">&times;</button>';
        elFab.addEventListener('click', function (e) {
            if (e && e.target && e.target.classList && e.target.classList.contains('guide-fab-close')) {
                end();
                return;
            }
            resume();
        });
        elFab.addEventListener('click', function (e) { e.stopPropagation(); });
        elFab.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); resume(); }
        });
        document.body.appendChild(elFab);
    }

    function removeFab() {
        if (elFab) {
            if (elFab.parentNode) elFab.parentNode.removeChild(elFab);
            elFab = null;
        }
    }

    function collapse() {
        if (collapsed || !running) return;
        collapsed = true;
        detachClick();
        if (elLayer) {
            elLayer.classList.add('guide-collapsing');
            setTimeout(function () {
                if (elLayer && collapsed) elLayer.style.display = 'none';
            }, 200);
        }
        buildFab();
    }

    function resume() {
        if (!collapsed) return;
        collapsed = false;
        removeFab();
        if (elLayer) {
            elLayer.classList.remove('guide-collapsing');
            elLayer.classList.add('guide-resuming');
            elLayer.style.display = 'block';
            setTimeout(function () {
                if (elLayer) elLayer.classList.remove('guide-resuming');
            }, 320);
        }
        runActions(steps[idx].enter);
        attachClick();
        render();
    }

    function go(n) {
        if (n < 0 || n >= steps.length) return;
        detachClick();
        runActions(steps[idx].leave);
        idx = n;
        var hadEnter = steps[idx].enter.length > 0;
        runActions(steps[idx].enter);
        attachClick();
        render();
        if (hadEnter) scheduleReRender();
    }

    function onKey(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
        }
    }

    function end(finish) {
        if (!running) return;
        running = false;
        collapsed = false;
        detachClick();
        removeFab();
        window.removeEventListener('resize', onViewport);
        window.removeEventListener('scroll', onViewport, true);
        document.removeEventListener('keydown', onKey, true);
        runActions(ALL_OFF);
        if (finish) launchConfetti();
        setTimeout(function () {
            if (elLayer && elLayer.parentNode) elLayer.parentNode.removeChild(elLayer);
            elLayer = elDims = elHole = elTip = elTitle = elText = elPage = elPrev = elNext = elMin = null;
        }, 800);
        if (!FORCE) {
            try { localStorage.setItem('ap_onboarding_done', '1'); } catch (e) {}
            try {
                fetch('/main?api=onboarding', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'done' })
                });
            } catch (e) {}
        }
    }

    function launchConfetti() {
        var rect = elTip ? elTip.getBoundingClientRect() : null;
        var x = rect ? rect.left + rect.width / 2 : innerWidth / 2;
        var y = rect ? rect.top + rect.height / 2 : innerHeight * 0.62;
        var canvas = document.createElement('canvas');
        canvas.id = 'guide-confetti';
        canvas.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:2147483003;';
        document.body.appendChild(canvas);
        var ctx = canvas.getContext('2d');
        var particles = [];
        var animationId = null;
        var dpr = window.devicePixelRatio || 1;

        function resize() {
            canvas.width = innerWidth * dpr;
            canvas.height = innerHeight * dpr;
            canvas.style.width = innerWidth + 'px';
            canvas.style.height = innerHeight + 'px';
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        }
        resize();
        window.addEventListener('resize', resize);

        for (var i = 0; i < 180; i++) {
            var angle = Math.random() * Math.PI * 2;
            var speed = 6 + Math.random() * 10;
            particles.push({
                x: x, y: y,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed - 5,
                size: 5 + Math.random() * 7,
                rotation: Math.random() * Math.PI,
                rotationSpeed: (Math.random() - 0.5) * 0.25,
                gravity: 0.18 + Math.random() * 0.08,
                life: 1,
                color: 'hsl(' + (Math.random() * 360) + ', 90%, 60%)'
            });
        }

        function animate() {
            ctx.clearRect(0, 0, innerWidth, innerHeight);
            particles = particles.filter(function (p) { return p.life > 0; });
            for (var j = 0; j < particles.length; j++) {
                var p = particles[j];
                p.x += p.vx;
                p.y += p.vy;
                p.vy += p.gravity;
                p.vx *= 0.99;
                p.rotation += p.rotationSpeed;
                p.life -= 0.008;
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rotation);
                ctx.globalAlpha = Math.max(0, p.life);
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.65);
                ctx.restore();
            }
            if (particles.length) {
                animationId = requestAnimationFrame(animate);
            } else {
                animationId = null;
                window.removeEventListener('resize', resize);
                if (canvas.parentNode) canvas.parentNode.removeChild(canvas);
            }
        }
        animate();
    }

    function start() {
        if (running) return;
        try {
            if (localStorage.getItem('ap_onboarding_done') === '1' && !FORCE) return;
        } catch (e) {}
        running = true;
        collapsed = false;
        removeFab();
        build();
        runActions(steps[idx].enter);
        attachClick();
        render();
        window.addEventListener('resize', onViewport);
        window.addEventListener('scroll', onViewport, true);
        document.addEventListener('keydown', onKey, true);
    }

    function boot() {
        setTimeout(start, 500);
    }

    window.startOnboardingTour = function () {
        if (running) return;
        try { localStorage.removeItem('ap_onboarding_done'); } catch (e) {}
        runActions(ALL_OFF);
        idx = 0;
        start();
    };

    if (window.__guideAuto === true) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
        else boot();
    }
})();
