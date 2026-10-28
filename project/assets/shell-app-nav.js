(function () {
  'use strict';
  if (window.__shellAppNavInit) return;
  window.__shellAppNavInit = 1;

  var catalog = window.__SHELL_APPS || {};
  var railTopApps = window.__SHELL_RAIL_TOP_APPS || ['immich', 'gitea', 'dawarich', 'sentry'];
  var edgeApps = window.__SHELL_EDGE_APPS || ['immich', 'gitea', 'dawarich', 'sentry', 'x', 'X'];
  var lang = window.__SHELL_LANG || 'en';
  var theme = window.__SHELL_THEME || 'light';
  var color = window.__SHELL_COLOR || '';
  var userData = window.__SHELL_USER_DATA || '';
  var currentFolder = window.__SHELL_CURRENT_APP || null;
  var loadGen = 0;
  var i18n = window.__SHELL_I18N || {};

  function byFolder(folder) {
    var keys = Object.keys(catalog);
    for (var i = 0; i < keys.length; i++) {
      if (catalog[keys[i]].folder === folder) return catalog[keys[i]];
    }
    return null;
  }

  function iframeSrc(folder) {
    var path = 'apps/' + encodeURIComponent(folder) + '/' + (folder === 'lopaka' ? '' : 'index.php');
    var activeTheme = document.body.classList.contains('dark-theme') || document.body.classList.contains('theme-dark') ? 'dark' : (document.body.classList.contains('light-theme') || document.body.classList.contains('theme-light') ? 'light' : theme);
    var q = 'lang=' + encodeURIComponent(lang) + '&theme=' + encodeURIComponent(activeTheme) + '&color=' + encodeURIComponent(color);
    if (userData) q += '&user_data=' + encodeURIComponent(userData);
    var routeHash = folder === 'smart_home' && /^#smart_home\/(home|devices|groups|statistics|errors|map|settings)$/.test(window.location.hash)
      ? window.location.hash : '';
    return path + '?' + q + routeHash;
  }

  function ensureMenuHost() {
    var host = document.getElementById('shell-app-menu-host');
    if (host) return host;
    host = document.createElement('div');
    host.id = 'shell-app-menu-host';
    document.body.insertBefore(host, document.body.firstChild);
    return host;
  }

  function clearMenuInlineLayout(btn) {
    if (!btn || !btn.style) return;
    ['position', 'top', 'left', 'right', 'bottom', 'width', 'height', 'margin', 'transform', 'display', 'visibility', 'opacity', 'z-index'].forEach(function (prop) {
      btn.style.removeProperty(prop);
    });
  }

  function setMenuVars(app) {
    var root = document.documentElement;
    var desk = (app && app.menu_button) || { left: 20, top: 13, width: 40, height: 40 };
    var mob = (app && app.menu_button_mobile) || desk;
    root.style.setProperty('--shell-menu-button-left', (desk.left || 20) + 'px');
    root.style.setProperty('--shell-menu-button-top', (desk.top || 13) + 'px');
    root.style.setProperty('--shell-menu-button-width', (desk.width || 40) + 'px');
    root.style.setProperty('--shell-menu-button-height', (desk.height || 40) + 'px');
    root.style.setProperty('--shell-menu-button-mobile-left', (mob.left || desk.left || 20) + 'px');
    root.style.setProperty('--shell-menu-button-mobile-top', (mob.top || desk.top || 13) + 'px');
    root.style.setProperty('--shell-menu-button-mobile-width', (mob.width || desk.width || 40) + 'px');
    root.style.setProperty('--shell-menu-button-mobile-height', (mob.height || desk.height || 40) + 'px');
    var btn = document.getElementById('menu-button');
    if (!btn) return;
    clearMenuInlineLayout(btn);
    btn.style.position = app && app.menu_button_scroll ? 'absolute' : '';
    ['color', 'background', 'border', 'box-shadow', 'border-radius', 'opacity'].forEach(function (prop) {
      btn.style.removeProperty(prop);
    });
    var home = document.getElementById('home-root-button');
    if (home) clearMenuInlineLayout(home);
    var styleMap = (app && app.menu_button_style) || {};
    Object.keys(styleMap).forEach(function (prop) {
      try {
        btn.style.setProperty(prop, styleMap[prop]);
        if (home) home.style.setProperty(prop, styleMap[prop]);
      } catch (_) {}
    });
  }

  function mountAppChrome() {
    var host = ensureMenuHost();
    var menu = document.getElementById('menu-button');
    if (menu && menu.parentElement !== host) host.appendChild(menu);
    var home = document.getElementById('home-root-button');
    if (home && home.parentElement !== host) host.appendChild(home);
  }

  function restoreHomeChrome() {
    var topBar = document.getElementById('top-bar');
    var menu = document.getElementById('menu-button');
    var home = document.getElementById('home-root-button');
    if (!topBar) return;
    if (menu) {
      clearMenuInlineLayout(menu);
      if (!topBar.contains(menu)) {
        var before = home && topBar.contains(home) ? home : (document.getElementById('top-bar-title') || topBar.firstChild);
        topBar.insertBefore(menu, before);
      }
    }
    if (home) {
      clearMenuInlineLayout(home);
      if (!topBar.contains(home)) {
        var afterMenu = document.getElementById('menu-button');
        if (afterMenu && topBar.contains(afterMenu) && afterMenu.nextSibling) {
          topBar.insertBefore(home, afterMenu.nextSibling);
        } else if (afterMenu && topBar.contains(afterMenu)) {
          topBar.appendChild(home);
        } else {
          var title = document.getElementById('top-bar-title');
          topBar.insertBefore(home, title || topBar.firstChild);
        }
      }
    }
  }

  function ensureAppShell() {
    var content = document.getElementById('content');
    if (!content) return null;
    var frame = document.getElementById('shell-app-frame');
    if (frame) return frame;
    frame = document.createElement('div');
    frame.id = 'shell-app-frame';
    frame.className = 'shell-app-frame';
    frame.hidden = true;
    frame.innerHTML =
      '<div id="app-icon-rail" class="hidden">' +
        '<div class="app-rail-scroll">' +
          '<a href="/main" class="app-rail-link app-rail-home shell-home-link" title="Home"><svg viewBox="0 0 24 24" fill="currentColor" width="60%" height="60%"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>' +
          '<span class="app-rail-service-name"></span>' +
        '</div>' +
      '</div>' +
      '<iframe id="app-iframe" name="devtools-app" title="Application" class="mobile-vv-fill" allow="clipboard-write *; clipboard-read *" src="about:blank"></iframe>';
    content.appendChild(frame);
    return frame;
  }

  function ensureHomeShell() {
    var content = document.getElementById('content');
    if (!content) return null;
    var home = document.getElementById('shell-home');
    if (home) return home;
    var grid = document.getElementById('app-grid');
    if (!grid) return null;
    home = document.createElement('div');
    home.id = 'shell-home';
    home.className = 'shell-home';
    grid.parentNode.insertBefore(home, grid);
    home.appendChild(grid);
    Array.prototype.slice.call(content.querySelectorAll('#notifications-btn.notifications-floating, #support-chat-btn.support-chat-floating')).forEach(function (el) {
      home.insertBefore(el, home.firstChild);
    });
    return home;
  }

  function contentAppClasses(folder, app) {
    var classes = ['mobile-vv-shell', 'app-edge-' + String(folder).toLowerCase()];
    if (app && app.app_rail) {
      classes.push('has-app-rail');
      if (railTopApps.indexOf(folder) !== -1) classes.push('rail-top-on-mobile');
      if (edgeApps.indexOf(folder) !== -1) classes.push('edge-app-shell');
    }
    return classes.join(' ');
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function hideAppLoading() {
    var ol = document.getElementById('shellAppLoading');
    if (ol) ol.remove();
    document.body.classList.remove('shell-app-loading');
    var iframe = document.getElementById('app-iframe');
    if (iframe) {
      iframe.classList.remove('shell-iframe-loading');
      iframe.style.removeProperty('opacity');
      iframe.style.removeProperty('visibility');
      iframe.style.removeProperty('pointer-events');
    }
  }

  function showAppLoading(app) {
    hideAppLoading();
    var title = i18n.shell_application || 'ServerKherson';
    var sub = i18n.opening_app || 'Opening…';
    if (app && (app.name || app.folder)) {
      sub = (i18n.opening_app_named || 'Opening {name}…').replace('{name}', app.name || app.folder);
    }
    var ol = document.createElement('div');
    ol.id = 'shellAppLoading';
    ol.className = 'shell-app-loading-overlay';
    ol.setAttribute('role', 'status');
    ol.setAttribute('aria-live', 'polite');
    ol.innerHTML =
      '<div class="shell-app-loading-box">' +
        '<img class="shell-app-loading-logo" src="/home/icon.png" alt="">' +
        '<h2 class="shell-app-loading-title">' + esc(title) + '</h2>' +
        '<p class="shell-app-loading-sub">' + esc(sub) + '</p>' +
        '<div class="shell-app-loading-bar" aria-hidden="true"></div>' +
      '</div>';
    document.body.appendChild(ol);
    document.body.classList.add('shell-app-loading');
    var iframe = document.getElementById('app-iframe');
    if (iframe) {
      iframe.classList.add('shell-iframe-loading');
      iframe.style.opacity = '0';
      iframe.style.visibility = 'hidden';
      iframe.style.pointerEvents = 'none';
    }
  }

  function waitIframeLoad(iframe, token) {
    if (!iframe) {
      if (token === loadGen) hideAppLoading();
      return;
    }
    var done = false;
    function finish() {
      if (done || token !== loadGen) return;
      done = true;
      hideAppLoading();
      try {
        var t = document.body.classList.contains('dark-theme') || document.body.classList.contains('theme-dark') ? 'dark' : 'light';
        if (iframe && iframe.contentWindow) iframe.contentWindow.postMessage({ type: 'appdashboard-theme', theme: t }, '*');
      } catch (_) {}
    }
    iframe.addEventListener('load', finish, { once: true });
    try {
      var src = iframe.getAttribute('src') || '';
      var doc = iframe.contentDocument;
      if (src && src !== 'about:blank' && doc && doc.readyState === 'complete') finish();
    } catch (_) {}
    setTimeout(finish, 45000);
  }

  function showApp(folder, push) {
    var app = byFolder(folder);
    if (!app) {
      window.location.href = '/main?app=' + encodeURIComponent(folder);
      return;
    }
    var content = document.getElementById('content');
    var home = ensureHomeShell();
    var frame = ensureAppShell();
    if (!content || !frame) {
      window.location.href = app.href;
      return;
    }
    var iframe = document.getElementById('app-iframe');
    var rail = document.getElementById('app-icon-rail');
    var nameEl = frame.querySelector('.app-rail-service-name');
    if (nameEl) nameEl.textContent = app.name || folder;
    if (rail) {
      rail.classList.toggle('hidden', !app.app_rail);
      rail.style.display = app.app_rail ? '' : 'none';
    }
    content.className = contentAppClasses(folder, app);
    if (typeof window.updateEdgeAppShellTheme === 'function') {
      var edgeTheme = document.body.classList.contains('dark-theme') || document.body.classList.contains('theme-dark') ? 'dark' : 'light';
      window.updateEdgeAppShellTheme(edgeTheme);
    }
    if (home) home.hidden = true;
    frame.hidden = false;
    document.body.classList.add('shell-app-open');
    document.body.classList.remove('shell-home', 'app-modal-open');
    document.body.classList.toggle('shell-local-app', !(app && app.app_rail));
    var topBar = document.getElementById('top-bar');
    if (topBar) {
      topBar.hidden = true;
      topBar.style.removeProperty('display');
    }
    mountAppChrome();
    setMenuVars(app);
    if (iframe) {
      var next = iframeSrc(folder);
      var needLoad = iframe.getAttribute('src') !== next;
      iframe.title = 'Application: ' + folder;
      if (needLoad) {
        var token = ++loadGen;
        showAppLoading(app);
        iframe.setAttribute('src', next);
        waitIframeLoad(iframe, token);
      }
    }
    currentFolder = folder;
    var url = new URL(app.href, window.location.origin);
    if (push !== false) {
      history.pushState({ shellApp: folder }, app.name || folder, url.pathname + url.search);
    }
    try {
      fetch('/main?app=' + encodeURIComponent(folder) + '&_visit=1', { method: 'GET', credentials: 'same-origin', keepalive: true }).catch(function () {});
    } catch (_) {}
  }

  function showHome(push) {
    loadGen++;
    hideAppLoading();
    var content = document.getElementById('content');
    var home = ensureHomeShell() || document.getElementById('shell-home') || document.getElementById('app-grid');
    var frame = document.getElementById('shell-app-frame');
    if (content) content.className = '';
    if (typeof window.updateEdgeAppShellTheme === 'function') window.updateEdgeAppShellTheme(document.body.classList.contains('dark-theme') || document.body.classList.contains('theme-dark') ? 'dark' : 'light');
    if (frame) {
      frame.hidden = true;
      var iframe = document.getElementById('app-iframe');
      if (iframe) iframe.setAttribute('src', 'about:blank');
    }
    if (home) home.hidden = false;
    document.body.classList.remove('shell-app-open', 'app-modal-open', 'shell-local-app');
    document.body.classList.add('shell-home');
    var topBar = document.getElementById('top-bar');
    if (topBar) {
      topBar.hidden = false;
      topBar.removeAttribute('hidden');
      topBar.style.removeProperty('display');
      topBar.style.removeProperty('visibility');
      topBar.style.removeProperty('opacity');
    }
    restoreHomeChrome();
    setMenuVars(null);
    try { window.__SHELL_ON_HOME = true; } catch (_) {}
    currentFolder = null;
    if (push !== false) {
      var url = new URL('/main', window.location.origin);
      url.searchParams.set('lang', lang);
      url.searchParams.set('theme', theme);
      if (color) url.searchParams.set('color', color);
      history.pushState({ shellApp: null }, 'Shell', url.pathname + url.search);
    }
  }

  window.addEventListener('message', function (event) {
    if (event.origin !== window.location.origin) return;
    var data = event.data || {};
    if (data.type !== 'appdashboard:app-route' || data.app !== 'smart_home') return;
    if (currentFolder !== 'smart_home') return;
    var page = String(data.page || 'home');
    if (!/^(home|devices|groups|statistics|errors|map|settings)$/.test(page)) return;
    var hash = '#smart_home/' + page;
    if (window.location.hash === hash) return;
    history.replaceState({ shellApp: currentFolder, smartHomePage: page }, document.title, window.location.pathname + window.location.search + hash);
  });

  function isShellHomeLink(el) {
    return !!(el && el.closest && el.closest('a.shell-home-link, a.app-rail-home, a#home-root-button'));
  }

  function onClick(e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var tile = e.target.closest && e.target.closest('a.app-tile-link');
    if (tile) {
      var href = tile.getAttribute('href') || '';
      if (!href || tile.target === '_blank') return;
      var url = new URL(href, window.location.origin);
      var folder = url.searchParams.get('app');
      if (!folder) return;
      e.preventDefault();
      showApp(folder, true);
      return;
    }
    var homeRoot = e.target.closest && e.target.closest('#home-root-button');
    if (homeRoot) {
      e.preventDefault();
      window.location.href = '/';
      return;
    }
    if (isShellHomeLink(e.target)) {
      if (currentFolder || document.body.classList.contains('shell-app-open')) {
        e.preventDefault();
        showHome(true);
        return;
      }
      var here = new URL(window.location.href);
      if (here.pathname === '/main' && !here.searchParams.get('app')) {
        e.preventDefault();
      }
    }
  }

  function onPopState(e) {
    hideAppLoading();
    var folder = (e.state && e.state.shellApp) || new URL(window.location.href).searchParams.get('app');
    if (folder) showApp(folder, false);
    else showHome(false);
  }

  document.addEventListener('click', onClick, true);
  window.addEventListener('popstate', onPopState);
  if (currentFolder) {
    ensureHomeShell();
    ensureAppShell();
    mountAppChrome();
    setMenuVars(byFolder(currentFolder));
    var initialFrame = document.getElementById('app-iframe');
    var initialApp = byFolder(currentFolder);
    var initialFrameSrc = iframeSrc(currentFolder);
    var token = ++loadGen;
    showAppLoading(initialApp || { name: currentFolder, folder: currentFolder });
    if (initialFrame) {
      if (initialFrame.getAttribute('src') !== initialFrameSrc) initialFrame.setAttribute('src', initialFrameSrc);
      waitIframeLoad(initialFrame, token);
    } else {
      hideAppLoading();
    }
    document.body.classList.add('shell-app-open');
    document.body.classList.remove('shell-home');
    document.body.classList.toggle('shell-local-app', !(initialApp && initialApp.app_rail));
    history.replaceState({ shellApp: currentFolder }, document.title, window.location.href);
  } else if (document.getElementById('app-grid')) {
    document.body.classList.add('shell-home');
    history.replaceState({ shellApp: null }, document.title, window.location.href);
  }

  window.ShellAppNav = { open: showApp, home: showHome };
})();
