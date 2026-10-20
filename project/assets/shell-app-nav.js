(function () {
  'use strict';
  if (window.__shellAppNavInit) return;
  window.__shellAppNavInit = 1;

  var catalog = window.__SHELL_APPS || {};
  var railTopApps = window.__SHELL_RAIL_TOP_APPS || ['immich', 'gitea', 'kanboard', 'dawarich', 'sentry', 'lopaka'];
  var edgeApps = window.__SHELL_EDGE_APPS || ['immich', 'gitea', 'kanboard', 'dawarich', 'sentry', 'x', 'X', 'lopaka'];
  var lang = window.__SHELL_LANG || 'en';
  var theme = window.__SHELL_THEME || 'light';
  var color = window.__SHELL_COLOR || '';
  var userData = window.__SHELL_USER_DATA || '';
  var currentFolder = window.__SHELL_CURRENT_APP || null;

  function byFolder(folder) {
    var keys = Object.keys(catalog);
    for (var i = 0; i < keys.length; i++) {
      if (catalog[keys[i]].folder === folder) return catalog[keys[i]];
    }
    return null;
  }

  function iframeSrc(folder) {
    var path = 'apps/' + encodeURIComponent(folder) + '/' + (folder === 'lopaka' ? '' : 'index.php');
    var q = 'lang=' + encodeURIComponent(lang) + '&theme=' + encodeURIComponent(theme) + '&color=' + encodeURIComponent(color);
    if (userData) q += '&user_data=' + encodeURIComponent(userData);
    return path + '?' + q;
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
    if (btn) {
      btn.style.position = app && app.menu_button_scroll ? 'absolute' : '';
      var styleMap = (app && app.menu_button_style) || {};
      btn.style.cssText = btn.style.cssText.replace(/;?\s*(color|background|border|box-shadow|border-radius|z-index|opacity)\s*:[^;]*/gi, '');
      Object.keys(styleMap).forEach(function (prop) {
        try { btn.style.setProperty(prop, styleMap[prop]); } catch (_) {}
      });
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
          '<a href="/main" class="app-rail-link app-rail-home" title="Home"><svg viewBox="0 0 24 24" fill="currentColor" width="60%" height="60%"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>' +
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
    while (grid.parentElement === content && content.firstChild !== grid) {
      /* keep floating buttons outside if any */
      break;
    }
    grid.parentNode.insertBefore(home, grid);
    home.appendChild(grid);
    Array.prototype.slice.call(content.querySelectorAll('#notifications-btn.notifications-floating, #support-chat-btn.support-chat-floating')).forEach(function (el) {
      home.insertBefore(el, home.firstChild);
    });
    return home;
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
    content.className = 'mobile-vv-shell app-edge-' + String(folder).toLowerCase();
    if (app.app_rail) content.classList.add('has-app-rail');
    if (app.app_rail && railTopApps.indexOf(folder) !== -1) content.classList.add('rail-top-on-mobile');
    if (app.app_rail && edgeApps.indexOf(folder) !== -1) content.classList.add('edge-app-shell');
    if (home) home.hidden = true;
    frame.hidden = false;
    document.body.classList.add('shell-app-open');
    document.body.classList.remove('shell-home');
    var topBar = document.getElementById('top-bar');
    if (topBar) topBar.hidden = true;
    var homeMenu = document.getElementById('menu-button');
    var appMenuHost = document.getElementById('shell-app-menu-host');
    if (!appMenuHost) {
      appMenuHost = document.createElement('div');
      appMenuHost.id = 'shell-app-menu-host';
      document.body.insertBefore(appMenuHost, document.body.firstChild);
    }
    if (homeMenu && homeMenu.parentElement !== appMenuHost) {
      appMenuHost.appendChild(homeMenu);
    }
    setMenuVars(app);
    if (iframe) {
      var next = iframeSrc(folder);
      if (iframe.getAttribute('src') !== next) iframe.setAttribute('src', next);
      iframe.title = 'Application: ' + folder;
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
    var content = document.getElementById('content');
    var home = ensureHomeShell() || document.getElementById('shell-home') || document.getElementById('app-grid');
    var frame = document.getElementById('shell-app-frame');
    if (content) content.className = '';
    if (frame) {
      frame.hidden = true;
      var iframe = document.getElementById('app-iframe');
      if (iframe) iframe.setAttribute('src', 'about:blank');
    }
    if (home) home.hidden = false;
    document.body.classList.remove('shell-app-open');
    document.body.classList.add('shell-home');
    var topBar = document.getElementById('top-bar');
    if (topBar) topBar.hidden = false;
    var menu = document.getElementById('menu-button');
    if (menu && topBar && !topBar.contains(menu)) {
      var homeRoot = document.getElementById('home-root-button');
      if (homeRoot) topBar.insertBefore(menu, homeRoot);
      else topBar.insertBefore(menu, topBar.firstChild);
    }
    setMenuVars(null);
    currentFolder = null;
    if (push !== false) {
      var url = new URL('/main', window.location.origin);
      url.searchParams.set('lang', lang);
      url.searchParams.set('theme', theme);
      if (color) url.searchParams.set('color', color);
      history.pushState({ shellApp: null }, 'Shell', url.pathname + url.search);
    }
  }

  function onClick(e) {
    var tile = e.target.closest && e.target.closest('a.app-tile-link');
    if (tile) {
      var href = tile.getAttribute('href') || '';
      if (!href || tile.target === '_blank' || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      var url = new URL(href, window.location.origin);
      var folder = url.searchParams.get('app');
      if (!folder) return;
      e.preventDefault();
      showApp(folder, true);
      return;
    }
    var homeLink = e.target.closest && e.target.closest('a.app-rail-home, a#home-root-button');
    if (homeLink && currentFolder) {
      e.preventDefault();
      showHome(true);
    }
  }

  function onPopState(e) {
    var folder = (e.state && e.state.shellApp) || new URL(window.location.href).searchParams.get('app');
    if (folder) showApp(folder, false);
    else showHome(false);
  }

  document.addEventListener('click', onClick, true);
  window.addEventListener('popstate', onPopState);
  if (currentFolder) {
    ensureHomeShell();
    ensureAppShell();
    history.replaceState({ shellApp: currentFolder }, document.title, window.location.href);
  } else if (document.getElementById('app-grid')) {
    document.body.classList.add('shell-home');
    history.replaceState({ shellApp: null }, document.title, window.location.href);
  }

  window.ShellAppNav = { open: showApp, home: showHome };
})();
