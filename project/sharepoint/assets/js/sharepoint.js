/* SharePoint — page interactions */
(function () {
  'use strict';

  const $ = (selector, root) => (root || document).querySelector(selector);
  const $$ = (selector, root) => Array.prototype.slice.call((root || document).querySelectorAll(selector));

  function spT(key, fallback) {
    const dict = window.SP_I18N || {};
    return dict[key] || fallback || key;
  }

  function toast(message) {
    const node = document.getElementById('sp-snackbar');
    if (!node) return;
    node.textContent = message;
    node.classList.add('sp-snackbar--open');
    clearTimeout(node.__timer);
    node.__timer = setTimeout(function () { node.classList.remove('sp-snackbar--open'); }, 2800);
  }

  function copyText(text) {
    if (!text) return;
    const done = function () { toast(spT('copied', 'Copied')); };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done).catch(fallback);
    } else {
      fallback();
    }
    function fallback() {
      const area = document.createElement('textarea');
      area.value = text;
      area.style.position = 'fixed';
      area.style.opacity = '0';
      document.body.appendChild(area);
      area.select();
      try { document.execCommand('copy'); done(); } catch (e) { toast(spT('copy_failed', 'Could not copy the link')); }
      area.remove();
    }
  }

  function downloadBlob(filename, type, content) {
    const blob = new Blob([content], { type: type });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1500);
  }

  function csrfToken() {
    const meta = document.querySelector('meta[name="sp-csrf"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function postAction(action, payload) {
    const token = document.body.dataset.spToken || '';
    return fetch('/s/' + encodeURIComponent(token) + '/actions', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-SharePoint-CSRF': csrfToken() },
      body: JSON.stringify(Object.assign({ action: action }, payload || {}))
    }).then(function (response) { return response.json(); });
  }

  /* Theme */
  function applyStoredTheme() {
    try {
      const stored = localStorage.getItem('sp-theme');
      if (stored === 'light' || stored === 'dark') document.documentElement.dataset.theme = stored;
    } catch (e) {}
  }
  function cycleTheme() {
    const current = document.documentElement.dataset.theme ||
      (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = next;
    try { localStorage.setItem('sp-theme', next); } catch (e) {}
    toast(next === 'dark' ? spT('theme_dark', 'Dark') : spT('theme_light', 'Light'));
  }

  /* File image viewer — pan/zoom with a viewport minimap and no native scrollbars. */
  function initImageViewer() {
    const stage = $('[data-sp-image-stage]');
    const image = $('[data-sp-image]');
    if (!stage || !image || stage.dataset.spImageViewer === '1') return;
    stage.dataset.spImageViewer = '1';

    const MIN_SCALE = 0.2;
    const MAX_SCALE = 8;
    const scaleLabel = $('[data-sp-image-scale]', stage);
    const minimap = $('[data-sp-image-minimap]', stage);
    const minimapImg = $('[data-sp-image-minimap-img]', stage);
    const minimapView = $('[data-sp-image-minimap-view]', stage);
    const pointers = new Map();
    let scale = 1;
    let rotation = 0;
    let panX = 0;
    let panY = 0;
    let drag = null;
    let pinch = null;

    function clamp(value, min, max) {
      return Math.max(min, Math.min(max, value));
    }

    function rotatedSize() {
      const width = image.clientWidth || image.naturalWidth || 0;
      const height = image.clientHeight || image.naturalHeight || 0;
      const angle = rotation * Math.PI / 180;
      return {
        width: (width * Math.abs(Math.cos(angle)) + height * Math.abs(Math.sin(angle))) * scale,
        height: (width * Math.abs(Math.sin(angle)) + height * Math.abs(Math.cos(angle))) * scale
      };
    }

    function clampPan() {
      const rect = stage.getBoundingClientRect();
      const size = rotatedSize();
      const maxX = Math.max(0, (size.width - rect.width) / 2);
      const maxY = Math.max(0, (size.height - rect.height) / 2);
      panX = clamp(panX, -maxX, maxX);
      panY = clamp(panY, -maxY, maxY);
    }

    function updateMinimap() {
      if (!minimap || !minimapImg) return;
      const rect = stage.getBoundingClientRect();
      const baseWidth = image.clientWidth || 0;
      const baseHeight = image.clientHeight || 0;
      const width = baseWidth * scale;
      const height = baseHeight * scale;
      if (!width || !height) { minimap.hidden = true; return; }

      const show = (width > rect.width + 1 || height > rect.height + 1) && scale > 1.001;
      minimap.hidden = !show;
      if (!show) return;

      const naturalWidth = image.naturalWidth || baseWidth;
      const naturalHeight = image.naturalHeight || baseHeight;
      const cssWidth = parseFloat(window.getComputedStyle(minimap).width) || 132;
      const maxWidth = cssWidth;
      const maxHeight = Math.max(64, Math.round(cssWidth * 0.72));
      let mapWidth = maxWidth;
      let mapHeight = mapWidth * naturalHeight / naturalWidth;
      if (mapHeight > maxHeight) {
        mapHeight = maxHeight;
        mapWidth = mapHeight * naturalWidth / naturalHeight;
      }
      minimap.style.width = Math.round(mapWidth) + 'px';
      minimap.style.height = Math.round(mapHeight) + 'px';
      if (minimapImg.getAttribute('src') !== (image.currentSrc || image.src)) {
        minimapImg.src = image.currentSrc || image.src;
      }

      const viewWidth = Math.min(1, rect.width / width);
      const viewHeight = Math.min(1, rect.height / height);
      const left = clamp(0.5 - panX / width - viewWidth / 2, 0, 1 - viewWidth);
      const top = clamp(0.5 - panY / height - viewHeight / 2, 0, 1 - viewHeight);
      const pad = 4;
      minimapView.style.left = (pad + left * mapWidth) + 'px';
      minimapView.style.top = (pad + top * mapHeight) + 'px';
      minimapView.style.width = Math.max(6, viewWidth * mapWidth) + 'px';
      minimapView.style.height = Math.max(6, viewHeight * mapHeight) + 'px';
    }

    function apply() {
      image.style.transform = 'translate3d(' + panX + 'px,' + panY + 'px,0) scale(' + scale + ') rotate(' + rotation + 'deg)';
      if (scaleLabel) scaleLabel.textContent = Math.round(scale * 100) + '%';
      updateMinimap();
    }

    function setScale(next, anchorX, anchorY) {
      const target = clamp(next, MIN_SCALE, MAX_SCALE);
      if (Math.abs(target - scale) < 0.0001) return;
      const ax = anchorX || 0;
      const ay = anchorY || 0;
      const ratio = target / scale;
      panX = ax - (ax - panX) * ratio;
      panY = ay - (ay - panY) * ratio;
      scale = target;
      clampPan();
      apply();
    }

    function reset() {
      scale = 1;
      rotation = 0;
      panX = 0;
      panY = 0;
      apply();
    }

    $$('[data-sp-image-zoom]', stage).forEach(function (button) {
      button.addEventListener('click', function () {
        setScale(scale * (button.dataset.spImageZoom === 'in' ? 1.25 : 0.8), 0, 0);
      });
    });
    $$('[data-sp-image-rotate]', stage).forEach(function (button) {
      button.addEventListener('click', function () {
        rotation = (rotation + Number(button.dataset.spImageRotate || 0) + 360) % 360;
        clampPan();
        apply();
      });
    });
    const resetButton = $('[data-sp-image-reset]', stage);
    if (resetButton) resetButton.addEventListener('click', reset);

    stage.addEventListener('wheel', function (event) {
      event.preventDefault();
      const rect = stage.getBoundingClientRect();
      let delta = event.deltaY;
      if (event.deltaMode === 1) delta *= 16;
      else if (event.deltaMode === 2) delta *= rect.height;
      const factor = Math.exp(-delta * 0.0018);
      setScale(scale * factor, event.clientX - rect.left - rect.width / 2, event.clientY - rect.top - rect.height / 2);
    }, { passive: false });

    stage.addEventListener('pointerdown', function (event) {
      if (event.target.closest('.sp-image-tools') || event.target.closest('.sp-image-minimap')) return;
      if (event.pointerType === 'mouse' && event.button !== 0) return;
      pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
      try { stage.setPointerCapture(event.pointerId); } catch (e) {}
      if (pointers.size === 1) {
        const size = rotatedSize();
        const rect = stage.getBoundingClientRect();
        if (size.width > rect.width || size.height > rect.height) {
          drag = { x: event.clientX, y: event.clientY, panX: panX, panY: panY };
          stage.classList.add('is-dragging');
        }
      } else if (pointers.size === 2) {
        const points = Array.from(pointers.values());
        const distance = Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y);
        const rect = stage.getBoundingClientRect();
        const centerX = (points[0].x + points[1].x) / 2;
        const centerY = (points[0].y + points[1].y) / 2;
        pinch = { distance: Math.max(1, distance), scale: scale, panX: panX, panY: panY,
          anchorX: centerX - rect.left - rect.width / 2, anchorY: centerY - rect.top - rect.height / 2 };
        drag = null;
      }
      event.preventDefault();
    });

    stage.addEventListener('pointermove', function (event) {
      if (!pointers.has(event.pointerId)) return;
      pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
      if (pointers.size === 2 && pinch) {
        const points = Array.from(pointers.values());
        const distance = Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y);
        const target = clamp(pinch.scale * distance / pinch.distance, MIN_SCALE, MAX_SCALE);
        const ratio = target / pinch.scale;
        scale = target;
        panX = pinch.anchorX - (pinch.anchorX - pinch.panX) * ratio;
        panY = pinch.anchorY - (pinch.anchorY - pinch.panY) * ratio;
        clampPan();
        apply();
      } else if (drag) {
        panX = drag.panX + event.clientX - drag.x;
        panY = drag.panY + event.clientY - drag.y;
        clampPan();
        apply();
      }
    });

    function endPointer(event) {
      pointers.delete(event.pointerId);
      if (pointers.size < 2) pinch = null;
      if (pointers.size === 0) {
        drag = null;
        stage.classList.remove('is-dragging');
      }
    }
    stage.addEventListener('pointerup', endPointer);
    stage.addEventListener('pointercancel', endPointer);
    stage.addEventListener('lostpointercapture', endPointer);

    if (image.complete) apply();
    image.addEventListener('load', function () { updateMinimap(); apply(); });
    window.addEventListener('resize', function () { clampPan(); apply(); });
  }

  /* Custom video player: native controls hide the seek bar and make keyboard/
     touch seeking inconsistent, so the public viewer uses its own timeline. */
  function initVideoPlayers() {
    $$('[data-sp-video-player]').forEach(function (player) {
      if (player.dataset.spVideoReady === '1') return;
      const video = $('[data-sp-video]', player);
      if (!video) return;
      player.dataset.spVideoReady = '1';
      const seek = $('[data-sp-video-seek]', player);
      const current = $('[data-sp-video-current]', player);
      const durationLabel = $('[data-sp-video-duration]', player);
      const muteButton = $('[data-sp-video-mute]', player);
      const volume = $('[data-sp-video-volume]', player);
      const fullscreen = $('[data-sp-video-fullscreen]', player);
      const labels = player.dataset;

      const formatTime = function (seconds) {
        if (!Number.isFinite(seconds) || seconds < 0) return '0:00';
        const total = Math.floor(seconds);
        const minutes = Math.floor(total / 60);
        const rest = total % 60;
        const hours = Math.floor(minutes / 60);
        if (hours > 0) return hours + ':' + String(minutes % 60).padStart(2, '0') + ':' + String(rest).padStart(2, '0');
        return minutes + ':' + String(rest).padStart(2, '0');
      };

      const updateToggle = function () {
        const playing = !video.paused && !video.ended;
        player.classList.toggle('is-playing', playing);
        $$('[data-sp-video-toggle]', player).forEach(function (button) {
          const icon = $('.material-symbols-outlined', button);
          if (icon) icon.textContent = playing ? 'pause' : 'play_arrow';
          button.setAttribute('aria-label', playing ? labels.labelPause : labels.labelPlay);
        });
      };
      const toggle = function () {
        if (video.paused || video.ended) {
          const promise = video.play();
          if (promise && promise.catch) promise.catch(function () {});
        } else {
          video.pause();
        }
      };
      const updateTime = function () {
        const duration = Number.isFinite(video.duration) ? video.duration : 0;
        if (seek) {
          seek.max = String(duration || 0);
          if (!video.seeking) seek.value = String(video.currentTime || 0);
        }
        if (current) current.textContent = formatTime(video.currentTime);
        if (durationLabel) durationLabel.textContent = formatTime(duration);
      };
      const updateVolume = function () {
        if (volume) volume.value = String(video.muted ? 0 : video.volume);
        if (muteButton) {
          const icon = $('.material-symbols-outlined', muteButton);
          const muted = video.muted || video.volume === 0;
          if (icon) icon.textContent = muted ? 'volume_off' : 'volume_up';
          muteButton.setAttribute('aria-label', muted ? labels.labelUnmute : labels.labelMute);
        }
      };

      $$('[data-sp-video-toggle]', player).forEach(function (button) { button.addEventListener('click', toggle); });
      video.addEventListener('click', toggle);
      video.addEventListener('keydown', function (event) {
        if (event.key === ' ' || event.key === 'Enter') { event.preventDefault(); toggle(); }
      });
      video.addEventListener('play', updateToggle);
      video.addEventListener('pause', updateToggle);
      video.addEventListener('ended', updateToggle);
      video.addEventListener('loadedmetadata', updateTime);
      video.addEventListener('durationchange', updateTime);
      video.addEventListener('timeupdate', updateTime);
      video.addEventListener('volumechange', updateVolume);
      if (seek) {
        seek.addEventListener('input', function () {
          const target = Number(seek.value);
          if (Number.isFinite(target) && Number.isFinite(video.duration)) {
            try { video.currentTime = Math.max(0, Math.min(video.duration, target)); } catch (e) {}
          }
        });
        seek.addEventListener('change', updateTime);
      }
      if (muteButton) muteButton.addEventListener('click', function () { video.muted = !video.muted; });
      if (volume) volume.addEventListener('input', function () {
        video.volume = Math.max(0, Math.min(1, Number(volume.value) || 0));
        video.muted = video.volume === 0;
      });
      if (fullscreen) fullscreen.addEventListener('click', function () {
        if (document.fullscreenElement) {
          if (document.exitFullscreen) document.exitFullscreen();
        } else if (player.requestFullscreen) {
          player.requestFullscreen().catch(function () {});
        }
      });
      document.addEventListener('fullscreenchange', function () {
        const icon = fullscreen ? $('.material-symbols-outlined', fullscreen) : null;
        const active = document.fullscreenElement === player;
        if (icon) icon.textContent = active ? 'fullscreen_exit' : 'fullscreen';
        if (fullscreen) fullscreen.setAttribute('aria-label', active ? labels.labelExitFullscreen : labels.labelFullscreen);
      });
      updateToggle();
      updateVolume();
      updateTime();
    });
  }

  /* Recipe */
  function initRecipe() {
    const dataNode = $('[data-sp-export-recipe], #sp-recipe-data');
    const dataEl = document.getElementById('sp-recipe-data');
    if (!dataEl) return;
    let data = {};
    try { data = JSON.parse(dataEl.textContent || '{}'); } catch (e) {}
    const shareId = document.body.dataset.spShareId || '0';
    const key = 'sp:recipe:' + shareId + ':checks';
    let state = {};
    try { state = JSON.parse(localStorage.getItem(key) || '{}') || {}; } catch (e) { state = {}; }

    const boxes = $$('[data-sp-persist]');
    boxes.forEach(function (box) {
      if (state[box.dataset.spPersist]) box.checked = true;
      box.addEventListener('change', function () {
        state[box.dataset.spPersist] = box.checked;
        try { localStorage.setItem(key, JSON.stringify(state)); } catch (e) {}
        updateProgress();
      });
    });

    function updateProgress() {
      const steps = $$('[data-sp-persist^="step-"]');
      const done = steps.filter(function (box) { return box.checked; }).length;
      const bar = $('[data-sp-progress-bar]');
      const text = $('[data-sp-progress-text]');
      if (bar) bar.style.width = (steps.length ? Math.round(done / steps.length * 100) : 0) + '%';
      if (text) text.textContent = done + ' / ' + steps.length;
      const progress = $('.sp-progress');
      if (progress) progress.setAttribute('aria-valuenow', String(done));
    }
    updateProgress();

    $$('[data-sp-timer]').forEach(function (timer) {
      const total = Math.max(1, Number(timer.dataset.seconds || 0));
      let remaining = total;
      let interval = null;
      const value = $('[data-sp-timer-value]', timer);
      const start = $('[data-sp-timer-start]', timer);
      const reset = $('[data-sp-timer-reset]', timer);
      const paint = function () {
        if (!value) return;
        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;
        value.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        timer.classList.toggle('sp-timer--running', interval !== null);
        const icon = start ? start.querySelector('.material-symbols-outlined') : null;
        if (icon) icon.textContent = interval === null ? 'play_arrow' : 'pause';
      };
      const stop = function () { if (interval) { clearInterval(interval); interval = null; } paint(); };
      if (start) start.addEventListener('click', function () {
        if (interval) { stop(); return; }
        if (remaining <= 0) remaining = total;
        interval = setInterval(function () {
          remaining -= 1;
          if (remaining <= 0) { remaining = 0; paint(); stop(); toast(spT('timer_finished', 'Timer finished')); return; }
          paint();
        }, 1000);
        paint();
      });
      if (reset) reset.addEventListener('click', function () { stop(); remaining = total; paint(); });
      paint();
    });

    $$('[data-sp-export-recipe]').forEach(function (button) {
      button.addEventListener('click', function () {
        downloadBlob('recipe-' + shareId + '.json', 'application/json', JSON.stringify(data, null, 2));
      });
    });
  }

  /* Expense chart */
  function initExpenseChart() {
    const dataEl = document.getElementById('sp-expense-data');
    const canvas = document.getElementById('sp-expense-chart');
    if (!dataEl || !canvas) return;
    let data = {};
    try { data = JSON.parse(dataEl.textContent || '{}'); } catch (e) { return; }
    const monthly = Array.isArray(data.monthly) ? data.monthly : [];
    const categories = Array.isArray(data.categories) ? data.categories : [];
    let kind = 'bar';
    let period = 12;

    const css = function (name) {
      return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || '#6750a4';
    };
    const palette = function () {
      return [css('--md-sys-color-primary'), css('--md-sys-color-tertiary'), css('--md-sys-color-secondary'),
        css('--md-sys-color-primary-container'), css('--md-sys-color-tertiary-container'), css('--md-sys-color-secondary-container')];
    };

    function draw() {
      const ctx = canvas.getContext('2d');
      const width = canvas.width;
      const height = canvas.height;
      ctx.clearRect(0, 0, width, height);
      const font = css('--md-sys-color-on-surface-variant');
      ctx.font = '13px Roboto, system-ui, sans-serif';
      ctx.fillStyle = font;

      if (kind === 'pie') {
        const items = categories.slice(0, 6);
        const rest = categories.slice(6).reduce(function (sum, item) { return sum + Number(item.total || 0); }, 0);
        if (rest > 0) items.push({ category: 'other', total: rest });
        const total = items.reduce(function (sum, item) { return sum + Number(item.total || 0); }, 0);
        const cx = width / 2;
        const cy = height / 2;
        const radius = Math.min(width, height) * 0.34;
        let angle = -Math.PI / 2;
        const colors = palette();
        items.forEach(function (item, index) {
          const slice = total ? Number(item.total || 0) / total * Math.PI * 2 : 0;
          ctx.beginPath();
          ctx.moveTo(cx, cy);
          ctx.arc(cx, cy, radius, angle, angle + slice);
          ctx.closePath();
          ctx.fillStyle = colors[index % colors.length];
          ctx.fill();
          angle += slice;
        });
        ctx.beginPath();
        ctx.arc(cx, cy, radius * 0.56, 0, Math.PI * 2);
        ctx.fillStyle = css('--md-sys-color-surface');
        ctx.fill();
        ctx.fillStyle = css('--md-sys-color-on-surface');
        ctx.textAlign = 'center';
        ctx.font = '700 22px Roboto, system-ui';
        ctx.fillText((data.currency || '') + ' ' + Number(total).toLocaleString(), cx, cy + 8);
        legend(items.map(function (item, index) {
          return { label: item.category, color: colors[index % colors.length] };
        }));
        return;
      }

      const rows = monthly.slice(period ? -period : 0);
      if (!rows.length) return;
      const max = Math.max.apply(null, rows.map(function (row) { return Number(row.total || 0); })) || 1;
      const left = 62;
      const right = 20;
      const top = 24;
      const bottom = 52;
      const plotW = width - left - right;
      const plotH = height - top - bottom;
      ctx.strokeStyle = css('--md-sys-color-outline-variant');
      ctx.fillStyle = font;
      ctx.textAlign = 'right';
      for (let i = 0; i <= 4; i++) {
        const y = top + plotH - plotH * i / 4;
        ctx.beginPath(); ctx.moveTo(left, y); ctx.lineTo(left + plotW, y); ctx.stroke();
        ctx.fillText(Math.round(max * i / 4).toLocaleString(), left - 8, y + 4);
      }
      const color = css('--md-sys-color-primary');
      if (kind === 'bar') {
        const slot = plotW / rows.length;
        const barW = Math.min(48, slot * 0.62);
        rows.forEach(function (row, index) {
          const value = Number(row.total || 0);
          const barH = plotH * value / max;
          const x = left + slot * index + (slot - barW) / 2;
          const y = top + plotH - barH;
          ctx.fillStyle = color;
          const radius = Math.min(8, barW / 2);
          ctx.beginPath();
          ctx.moveTo(x, y + radius);
          ctx.quadraticCurveTo(x, y, x + radius, y);
          ctx.lineTo(x + barW - radius, y);
          ctx.quadraticCurveTo(x + barW, y, x + barW, y + radius);
          ctx.lineTo(x + barW, top + plotH);
          ctx.lineTo(x, top + plotH);
          ctx.closePath();
          ctx.fill();
        });
      } else {
        ctx.strokeStyle = color;
        ctx.lineWidth = 3;
        ctx.beginPath();
        rows.forEach(function (row, index) {
          const x = left + plotW * (rows.length === 1 ? 0.5 : index / (rows.length - 1));
          const y = top + plotH - plotH * Number(row.total || 0) / max;
          if (index === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
        });
        ctx.stroke();
        rows.forEach(function (row, index) {
          const x = left + plotW * (rows.length === 1 ? 0.5 : index / (rows.length - 1));
          const y = top + plotH - plotH * Number(row.total || 0) / max;
          ctx.beginPath();
          ctx.arc(x, y, 4, 0, Math.PI * 2);
          ctx.fillStyle = color;
          ctx.fill();
        });
      }
      ctx.fillStyle = font;
      ctx.textAlign = 'center';
      const step = Math.max(1, Math.ceil(rows.length / 10));
      rows.forEach(function (row, index) {
        if (index % step !== 0 && index !== rows.length - 1) return;
        const x = kind === 'bar'
          ? left + plotW / rows.length * (index + 0.5)
          : left + plotW * (rows.length === 1 ? 0.5 : index / (rows.length - 1));
        ctx.fillText(String(row.month || '').slice(2), x, height - 22);
      });
      legend(rows.map(function (row) {
        return { label: row.month + ' · ' + Number(row.total).toLocaleString(), color: color };
      }).slice(-3));
    }

    function legend(items) {
      const node = $('[data-sp-chart-legend]');
      if (!node) return;
      node.innerHTML = items.map(function (item) {
        return '<span><i style="background:' + item.color + '"></i>' + String(item.label) + '</span>';
      }).join('');
    }

    function renderCategories() {
      const node = $('[data-sp-category-list]');
      if (!node) return;
      const max = Math.max.apply(null, categories.map(function (item) { return Number(item.total || 0); }).concat([1]));
      node.innerHTML = categories.map(function (item) {
        const pct = Math.round(Number(item.total || 0) / max * 100);
        return '<div class="sp-bar-list__row"><span class="md3-typescale-body-medium">' + String(item.category) +
          '</span><span class="sp-bar-list__track"><span class="sp-bar-list__fill" style="width:' + pct + '%"></span></span>' +
          '<span class="md3-typescale-body-small">' + (data.currency || '') + ' ' + Number(item.total || 0).toFixed(2) + '</span></div>';
      }).join('');
    }

    $$('[data-sp-chart-kind]').forEach(function (button) {
      button.addEventListener('click', function () {
        kind = button.dataset.spChartKind || 'bar';
        $$('[data-sp-chart-kind]').forEach(function (other) {
          other.classList.toggle('md3-chip--selected', other === button);
        });
        draw();
      });
    });
    const periodSelect = $('[data-sp-chart-period]');
    if (periodSelect) periodSelect.addEventListener('change', function () {
      period = Number(periodSelect.value || 12);
      draw();
    });
    $$('[data-sp-export-csv]').forEach(function (button) {
      button.addEventListener('click', function () {
        let csv = 'month,total\n' + monthly.map(function (row) { return row.month + ',' + row.total; }).join('\n');
        csv += '\n\ncategory,total\n' + categories.map(function (row) { return row.category + ',' + row.total; }).join('\n');
        downloadBlob('expenses.csv', 'text/csv', csv);
      });
    });
    renderCategories();
    draw();
  }

  /* Steps */
  function initSteps() {
    const dataEl = document.getElementById('sp-steps-data');
    if (!dataEl) return;
    let data = {};
    try { data = JSON.parse(dataEl.textContent || '{}'); } catch (e) { return; }
    const days = Array.isArray(data.days) ? data.days : [];
    const goal = Number(data.goal || 10000);
    const chart = $('[data-sp-steps-chart]');
    const table = $('[data-sp-steps-table]');
    let period = 30;

    function render() {
      const rows = days.slice(-period);
      if (!rows.length) {
        if (chart) chart.innerHTML = '<p class="sp-muted md3-typescale-body-medium">' + spT('no_items', 'Nothing here yet') + '</p>';
        if (table) table.innerHTML = '';
        return;
      }
      const max = Math.max.apply(null, rows.map(function (row) { return Number(row.steps || 0); }).concat([goal, 1]));
      if (chart) {
        chart.innerHTML = rows.map(function (row) {
          const height = Math.max(3, Math.round(Number(row.steps || 0) / max * 200));
          const color = Number(row.steps || 0) >= goal ? 'var(--md-sys-color-primary)' : 'var(--md-sys-color-tertiary)';
          return '<div class="sp-steps-chart__bar" style="height:' + height + 'px;background:' + color + '">' +
            '<span>' + Number(row.steps || 0).toLocaleString() + '</span></div>';
        }).join('');
      }
      if (table) {
        table.innerHTML = rows.slice().reverse().map(function (row) {
          const pct = Math.min(100, Math.round(Number(row.steps || 0) / Math.max(1, goal) * 100));
          return '<tr><td>' + String(row.date) + '</td><td>' + Number(row.steps || 0).toLocaleString() + '</td><td>' + pct + '%</td></tr>';
        }).join('');
      }
    }

    const select = $('[data-sp-steps-period]');
    if (select) select.addEventListener('change', function () {
      period = Number(select.value || 30);
      render();
    });
    $$('[data-sp-export-steps]').forEach(function (button) {
      button.addEventListener('click', function () {
        const csv = 'date,steps\n' + days.map(function (row) { return row.date + ',' + row.steps; }).join('\n');
        downloadBlob('steps.csv', 'text/csv', csv);
      });
    });
    render();
  }

  /* Folder navigation — Explorer-like in-place browsing. */
  let navSequence = 0;

  function syncTopBar(doc) {
    const pairs = [
      ['.md3-top-app-bar__title', '.md3-top-app-bar__title'],
      ['.sp-top-bar__subtitle', '.sp-top-bar__subtitle']
    ];
    pairs.forEach(function (pair) {
      const current = $(pair[0]);
      const next = $(pair[1], doc);
      if (current && next) current.textContent = next.textContent;
    });
  }

  function reinitContent() {
    initImageViewer();
    initPdfViewer();
    initVideoPlayers();
    initRecipe();
    initExpenseChart();
    initSteps();
    initFolderTreeDrawer();
  }

  async function navigateShare(url, pushHistory) {
    const main = document.getElementById('sp-main');
    if (!main) {
      window.location.href = url;
      return;
    }
    const sequence = ++navSequence;
    // Only dim for genuinely slow requests; fast folder swaps stay flicker-free.
    const dimTimer = setTimeout(function () {
      if (sequence === navSequence) main.classList.add('sp-is-navigating');
    }, 180);
    try {
      const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'X-SharePoint-Navigation': '1' }
      });
      if (!response.ok) throw new Error('HTTP ' + response.status);
      const html = await response.text();
      if (sequence !== navSequence) return;
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const nextMain = doc.getElementById('sp-main');
      if (!nextMain) throw new Error('Missing content');
      // Documents with inline scripts (office editor) must load normally.
      if (nextMain.querySelector('script')) {
        window.location.href = url;
        return;
      }
      const swap = function () {
        main.innerHTML = nextMain.innerHTML;
        main.className = nextMain.className;
        document.body.className = doc.body.className;
        if (doc.body.dataset.spToken) document.body.dataset.spToken = doc.body.dataset.spToken;
        if (doc.body.dataset.spShareId) document.body.dataset.spShareId = doc.body.dataset.spShareId;
        document.title = doc.title;
        syncTopBar(doc);
        if (pushHistory) history.pushState({ spNav: true }, '', url);
        reinitContent();
        window.scrollTo({ top: 0, behavior: 'auto' });
      };
      if (document.startViewTransition) {
        await document.startViewTransition(swap).finished.catch(function () {});
      } else {
        swap();
      }
    } catch (error) {
      if (sequence === navSequence) window.location.href = url;
    } finally {
      clearTimeout(dimTimer);
      if (sequence === navSequence) main.classList.remove('sp-is-navigating');
    }
  }

  function setFolderTreeOpen(open) {
    document.body.classList.toggle('is-tree-open', !!open);
    document.querySelectorAll('[data-sp-tree-toggle]').forEach(function (btn) {
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    const backdrop = document.querySelector('[data-sp-tree-backdrop]');
    if (backdrop) backdrop.hidden = !open;
  }

  function initFolderTreeDrawer() {
    const toggle = document.querySelector('[data-sp-tree-toggle]');
    if (!toggle || toggle.dataset.spTreeInit === '1') return;
    toggle.dataset.spTreeInit = '1';
    toggle.addEventListener('click', function () {
      setFolderTreeOpen(!document.body.classList.contains('is-tree-open'));
    });
    document.querySelectorAll('[data-sp-tree-close], [data-sp-tree-backdrop]').forEach(function (el) {
      el.addEventListener('click', function () { setFolderTreeOpen(false); });
    });
    document.querySelectorAll('.sp-tree__summary a').forEach(function (link) {
      link.addEventListener('click', function (event) {
        // Keep navigation, but stop <details> from toggling on link click.
        event.preventDefault();
        event.stopPropagation();
        setFolderTreeOpen(false);
        const url = new URL(link.href, window.location.href);
        navigateShare(url.href, true);
      });
    });
  }

  function initFolderNavigation() {
    const main = document.getElementById('sp-main');
    if (!main || main.dataset.spNavigation === '1') return;
    main.dataset.spNavigation = '1';
    main.addEventListener('click', function (event) {
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      const link = event.target.closest('a[href]');
      if (!link || link.target || link.hasAttribute('download')) return;
      if (link.closest('.sp-tree__summary')) return;
      const raw = link.getAttribute('href') || '';
      // Folder renderer navigates with query-only links (?subpath=...&file=...).
      if (!raw.startsWith('?')) return;
      const url = new URL(link.href, window.location.href);
      if (url.origin !== window.location.origin || url.pathname !== window.location.pathname) return;
      event.preventDefault();
      setFolderTreeOpen(false);
      navigateShare(url.href, true);
    });
    window.addEventListener('popstate', function () {
      if (!document.body.classList.contains('sp-folder-page') && !document.body.classList.contains('sp-file-page')) return;
      setFolderTreeOpen(false);
      navigateShare(window.location.href, false);
    });
    initFolderTreeDrawer();
  }

  function initLanguageDialog() {
    const dialog = document.getElementById('sp-language-dialog');
    document.querySelectorAll('[data-sp-language]').forEach(function (trigger) {
      if (trigger.dataset.spLanguageInit === '1') return;
      trigger.dataset.spLanguageInit = '1';
      trigger.addEventListener('click', function (event) {
        event.preventDefault();
        if (window.MD3Menu) window.MD3Menu.closeAll();
        if (dialog) {
          if (window.MD3Dialog) window.MD3Dialog.open(dialog);
          else dialog.classList.add('md3-dialog--open');
        }
      });
    });
    if (!dialog || dialog.dataset.spLangLinks === '1') return;
    dialog.dataset.spLangLinks = '1';
    dialog.querySelectorAll('[data-sp-lang]').forEach(function (option) {
      option.addEventListener('click', function (event) {
        event.preventDefault();
        const url = new URL(window.location.href);
        url.searchParams.set('lang', option.dataset.spLang || 'en');
        window.location.href = url.toString();
      });
    });
  }

  /* PDF viewer — custom toolbar driving the native PDF frame via URL fragments. */
  function initPdfViewer() {
    $$('[data-sp-pdf]').forEach(function (shell) {
      if (shell.dataset.spPdfReady === '1') return;
      shell.dataset.spPdfReady = '1';
      const frame = $('iframe.sp-pdf', shell);
      if (!frame) return;

      const base = (frame.getAttribute('src') || '').split('#')[0];
      const state = { page: 1, zoom: 100, fit: false, total: 0 };
      const steps = [50, 75, 90, 100, 110, 125, 150, 175, 200, 250, 300];
      const pageInput = $('[data-sp-pdf-page]', shell);
      const totalEl = $('[data-sp-pdf-total]', shell);
      const totalWrap = $('[data-sp-pdf-total-wrap]', shell);
      const zoomEl = $('[data-sp-pdf-zoom]', shell);
      const prevBtn = $('[data-sp-pdf-prev]', shell);
      const nextBtn = $('[data-sp-pdf-next]', shell);
      const zoomInBtn = $('[data-sp-pdf-zoom-in]', shell);
      const zoomOutBtn = $('[data-sp-pdf-zoom-out]', shell);
      const fitBtn = $('[data-sp-pdf-fit]', shell);
      const fsBtn = $('[data-sp-pdf-fullscreen]', shell);

      function apply() {
        const params = ['toolbar=0', 'navpanes=0'];
        if (state.page > 1) params.push('page=' + state.page);
        params.push(state.fit ? 'zoom=page-width' : 'zoom=' + state.zoom);
        const src = base + '#' + params.join('&');
        if (frame.getAttribute('src') !== src) frame.setAttribute('src', src);
        if (zoomEl) zoomEl.textContent = state.fit ? spT('fit_width', 'Fit') : state.zoom + '%';
        if (fitBtn) fitBtn.setAttribute('aria-pressed', state.fit ? 'true' : 'false');
        if (pageInput && String(state.page) !== pageInput.value) pageInput.value = String(state.page);
      }

      function setPage(value) {
        let page = parseInt(value, 10);
        if (!isFinite(page) || page < 1) page = 1;
        if (state.total && page > state.total) page = state.total;
        state.page = page;
        apply();
      }

      function zoomTo(direction) {
        state.fit = false;
        let index = 0;
        let best = Infinity;
        steps.forEach(function (step, i) {
          const distance = Math.abs(step - state.zoom);
          if (distance < best) { best = distance; index = i; }
        });
        index = Math.max(0, Math.min(steps.length - 1, index + direction));
        state.zoom = steps[index];
        apply();
      }

      if (prevBtn) prevBtn.addEventListener('click', function () { if (state.page > 1) { state.page--; apply(); } });
      if (nextBtn) nextBtn.addEventListener('click', function () {
        if (!state.total || state.page < state.total) { state.page++; apply(); }
      });
      if (zoomInBtn) zoomInBtn.addEventListener('click', function () { zoomTo(1); });
      if (zoomOutBtn) zoomOutBtn.addEventListener('click', function () { zoomTo(-1); });
      if (fitBtn) fitBtn.addEventListener('click', function () { state.fit = !state.fit; apply(); });
      if (pageInput) {
        pageInput.addEventListener('change', function () { setPage(pageInput.value); });
        pageInput.addEventListener('keydown', function (event) {
          if (event.key === 'Enter') { event.preventDefault(); setPage(pageInput.value); pageInput.blur(); }
        });
      }

      function updateFullscreenIcon() {
        if (!fsBtn) return;
        const active = document.fullscreenElement === shell;
        const icon = fsBtn.querySelector('.material-symbols-outlined');
        if (icon) icon.textContent = active ? 'fullscreen_exit' : 'fullscreen';
        const label = spT(active ? 'exit_fullscreen' : 'fullscreen', active ? 'Exit fullscreen' : 'Fullscreen');
        fsBtn.setAttribute('aria-label', label);
        fsBtn.setAttribute('title', label);
      }
      if (fsBtn) {
        fsBtn.addEventListener('click', function () {
          if (document.fullscreenElement === shell) {
            if (document.exitFullscreen) document.exitFullscreen();
          } else if (shell.requestFullscreen) {
            shell.requestFullscreen().catch(function () {});
          }
        });
        document.addEventListener('fullscreenchange', updateFullscreenIcon);
      }

      apply();

      // Best-effort page count: count page objects in the served PDF.
      fetch(base, { credentials: 'same-origin' }).then(function (response) {
        if (!response.ok) return null;
        const length = Number(response.headers.get('content-length') || 0);
        if (length > 15 * 1024 * 1024) return null;
        return response.arrayBuffer();
      }).then(function (buffer) {
        if (!buffer) return;
        const text = new TextDecoder('latin1').decode(new Uint8Array(buffer));
        const matches = text.match(/\/Type\s*\/Page(?![s])/g);
        if (!matches || !matches.length) return;
        state.total = matches.length;
        if (totalEl) totalEl.textContent = String(state.total);
        if (totalWrap) totalWrap.hidden = false;
        if (state.page > state.total) setPage(state.total);
      }).catch(function () {});
    });
  }

  /* Global bindings */
  function init() {
    applyStoredTheme();
    document.addEventListener('click', function (event) {
      const copy = event.target.closest('[data-sp-copy]');
      if (copy) {
        event.preventDefault();
        copyText(copy.dataset.spCopy);
        if (window.MD3Menu) window.MD3Menu.closeAll();
        return;
      }
      const copyTextButton = event.target.closest('[data-sp-copy-text]');
      if (copyTextButton) {
        event.preventDefault();
        const target = document.querySelector(copyTextButton.dataset.spCopyText);
        if (target) copyText(target.textContent);
        return;
      }
      const theme = event.target.closest('[data-sp-theme-cycle]');
      if (theme) {
        event.preventDefault();
        cycleTheme();
        if (window.MD3Menu) window.MD3Menu.closeAll();
        return;
      }
      const print = event.target.closest('[data-sp-print]');
      if (print) {
        event.preventDefault();
        window.print();
      }
    });
    initImageViewer();
    initPdfViewer();
    initVideoPlayers();
    initRecipe();
    initExpenseChart();
    initSteps();
    initFolderNavigation();
    initFolderTreeDrawer();
    initLanguageDialog();
  }

  window.SharepointActions = { post: postAction, toast: toast, copy: copyText };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
