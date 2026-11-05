(function () {
  'use strict';
  var io = null;
  function load(el) {
    if (el.getAttribute('data-kb-loading') === '1') return;
    el.setAttribute('data-kb-loading', '1');
    var url = el.getAttribute('data-kb-lazy-url');
    var prev = el.getAttribute('data-prev-ts');
    if (prev) url += (url.indexOf('?') === -1 ? '?' : '&') + 'prev_ts=' + encodeURIComponent(prev);
    fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.ok ? r.text() : Promise.reject(); })
      .then(function (html) {
        var tpl = document.createElement('template');
        tpl.innerHTML = html;
        el.parentNode.replaceChild(tpl.content, el);
      })
      .catch(function () { el.removeAttribute('data-kb-loading'); });
  }
  function scan() {
    var list = document.querySelectorAll('.kb-lazy-sentinel[data-kb-lazy-url]:not([data-kb-watched])');
    for (var i = 0; i < list.length; i++) {
      list[i].setAttribute('data-kb-watched', '1');
      if (io) io.observe(list[i]);
      else load(list[i]);
    }
  }
  if ('IntersectionObserver' in window) {
    io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { io.unobserve(e.target); load(e.target); }
      });
    }, { rootMargin: '400px 0px' });
  }
  var t = null;
  new MutationObserver(function () {
    if (t) return;
    t = setTimeout(function () { t = null; scan(); }, 50);
  }).observe(document.documentElement, { childList: true, subtree: true });
  scan();
})();
(function () {
  'use strict';
  function fetchMore(marker) {
    if (marker.getAttribute('data-kb-loading') === '1') return;
    marker.setAttribute('data-kb-loading', '1');
    var url = marker.getAttribute('data-kb-url');
    if (!url) return;
    var offset = parseInt(marker.getAttribute('data-kb-offset'), 10) || 0;
    var total = parseInt(marker.getAttribute('data-kb-total'), 10) || 0;
    // the url carries the initial offset, keep it in sync with the current position
    url = url.replace(/([?&]offset=)\d+/, '$1' + offset);
    fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.ok ? r.text() : Promise.reject(); })
      .then(function (html) {
        var tpl = document.createElement('template');
        tpl.innerHTML = html;
        // count task cards only, text nodes between them would break the offset
        var added = tpl.content.querySelectorAll('.task-board').length;
        while (tpl.content.firstChild) {
          marker.parentNode.insertBefore(tpl.content.firstChild, marker);
        }
        marker.removeAttribute('data-kb-loading');
        if (added === 0) { marker.remove(); return; }
        offset += added;
        marker.setAttribute('data-kb-offset', String(offset));
        if (total > 0 && offset >= total) { marker.remove(); return; }
        if (marker._moreIO) {
          marker._moreIO.disconnect();
          setTimeout(function () {
            if (marker.isConnected) marker._moreIO.observe(marker);
          }, 120);
        }
      })
      .catch(function () { marker.removeAttribute('data-kb-loading'); });
  }
  function scanMore() {
    var list = document.querySelectorAll('[data-kb-board-more]:not([data-kb-more-bound])');
    for (var i = 0; i < list.length; i++) {
      var m = list[i];
      m.setAttribute('data-kb-more-bound', '1');
      if ('IntersectionObserver' in window) {
        m._moreIO = new IntersectionObserver(function (entries) {
          entries.forEach(function (e) {
            if (e.isIntersecting) fetchMore(e.target);
          });
        }, { rootMargin: '300px 0px' });
        m._moreIO.observe(m);
      } else {
        fetchMore(m);
      }
    }
  }
  var moreTimer = null;
  new MutationObserver(function () {
    if (moreTimer) return;
    moreTimer = setTimeout(function () { moreTimer = null; scanMore(); }, 80);
  }).observe(document.documentElement, { childList: true, subtree: true });
  scanMore();
})();
(function () {
  'use strict';
  function reveal(marker) {
    var step = parseInt(marker.getAttribute('data-kb-chunk-more'), 10) || 25;
    var parent = marker.parentNode;
    var hidden = parent.querySelectorAll(':scope > [data-kb-chunk-hidden]');
    for (var i = 0; i < hidden.length && i < step; i++) {
      hidden[i].hidden = false;
      hidden[i].removeAttribute('data-kb-chunk-hidden');
    }
    if (parent.querySelectorAll(':scope > [data-kb-chunk-hidden]').length === 0) {
      if (marker._io) marker._io.disconnect();
      marker.remove();
    } else if (marker._io) {
      marker._io.unobserve(marker);
      setTimeout(function () { marker._io && marker._io.observe(marker); }, 80);
    }
  }
  function scan() {
    var list = document.querySelectorAll('[data-kb-chunk-more]:not([data-kb-chunk-bound])');
    for (var i = 0; i < list.length; i++) {
      var m = list[i];
      m.setAttribute('data-kb-chunk-bound', '1');
      m.style.minHeight = '1px';
      if ('IntersectionObserver' in window) {
        m._io = new IntersectionObserver(function (entries) {
          entries.forEach(function (e) { if (e.isIntersecting) reveal(e.target); });
        }, { rootMargin: '300px 0px' });
        m._io.observe(m);
      } else {
        while (m.parentNode) { reveal(m); if (!m.parentNode) break; }
      }
    }
  }
  var t = null;
  new MutationObserver(function () {
    if (t) return;
    t = setTimeout(function () { t = null; scan(); }, 50);
  }).observe(document.documentElement, { childList: true, subtree: true });
  scan();
})();
