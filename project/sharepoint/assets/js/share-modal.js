/* SharePoint — app-adaptive share dialog.
 * The dialog uses the host app's design tokens (surface, accent, radius,
 * typography) instead of injecting the standalone SharePoint theme. */
(function () {
  'use strict';

  const APP_META = {
    cloud:      { icon: 'cloud', name: 'Cloud',      note: 'Create a public link for this cloud item.' },
    recipes:    { icon: 'recipes', name: 'Recipes',    note: 'Recipients can follow ingredients, tick steps and use timers.' },
    spends:     { icon: 'spends', name: 'Spends',     note: 'Recipients can explore the chart, change periods and export CSV.' },
    steps:      { icon: 'steps', name: 'Steps',      note: 'Recipients can view progress, daily chart and export the data.' },
    films:      { icon: 'films', name: 'Films',      note: 'A cinematic read-only page with poster, cast and trailer.' },
    smart_home: { icon: 'smart_home', name: 'Smart Home', note: 'Recipients see live device states; control requires edit access.' }
  };

  const ICONS = {
    cloud: '<path d="M17.5 19H7a5 5 0 1 1 .9-9.9A7 7 0 0 1 21 12a4 4 0 0 1-3.5 7Z"/>',
    recipes: '<path d="M4 3v7a2 2 0 0 0 2 2 2 2 0 0 0 2-2V3"/><path d="M6 12v9"/><path d="M18 3v18"/><path d="M18 3c-2.1 0-3 2-3 5s.9 4 3 4"/>',
    spends: '<path d="M3 20h18"/><path d="M6 20V11"/><path d="M11 20V5"/><path d="M16 20v-6"/><path d="M21 20V8"/>',
    steps: '<path d="M3 12h4l2-6 4 12 2-6h6"/>',
    films: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 4v16M17 4v16M3 9h4M3 15h4M17 9h4M17 15h4"/>',
    smart_home: '<path d="m3 11 9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
    link: '<path d="M10 13a5 5 0 0 0 7.1 0l2-2a5 5 0 0 0-7.1-7.1l-1.1 1.1"/><path d="M14 11a5 5 0 0 0-7.1 0l-2 2A5 5 0 0 0 12 20.1l1.1-1.1"/>',
    check: '<path d="m5 12 4.5 4.5L19 7"/>',
    copy: '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
    open: '<path d="M7 17 17 7"/><path d="M8 7h9v9"/>',
    view: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
    download: '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M4 21h16"/>',
    interact: '<path d="M9 11V6a2 2 0 1 1 4 0v5"/><path d="M13 11V8a2 2 0 1 1 4 0v6"/><path d="M17 11a2 2 0 0 1 4 0v4a7 7 0 0 1-7 7h-2a7 7 0 0 1-7-7v-3a2 2 0 1 1 4 0"/>',
    edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
    share: '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>'
  };

  function icon(name) {
    const path = ICONS[name] || ICONS.link;
    return '<span class="sp-share-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + path + '</svg></span>';
  }

  const ERROR_ALIASES = {
    'not authenticated': 'error_auth',
    'app mismatch': 'error_app_mismatch',
    'owner is required': 'error_owner_required',
    'unsupported resource type': 'error_resource_type',
    'unsupported app': 'error_app_unsupported',
    'unsupported app resource': 'error_app_unsupported',
    'resource id is required': 'error_resource_id',
    'resource is not available': 'error_resource_unavailable',
    'method_not_allowed': 'error_method_not_allowed',
    'unsupported_action': 'error_unsupported_action',
    'create_failed': 'error_create_failed',
    'invalid_request': 'error_invalid_request',
    'not_found': 'error_not_found',
    'forbidden': 'error_forbidden'
  };

  const PERMISSION_META = {
    view:     { icon: 'view', key: 'view' },
    download: { icon: 'download', key: 'download' },
    interact: { icon: 'interact', key: 'interact' },
    edit:     { icon: 'edit', key: 'edit' },
    share:    { icon: 'share', key: 'share' }
  };

  function t(key) {
    const dict = window.SHAREPOINT_SHARE_I18N || {};
    return dict[key] || key;
  }

  function translatedError(code) {
    const raw = String(code == null ? '' : code).trim();
    if (raw === '') return t('error');
    const alias = ERROR_ALIASES[raw.toLowerCase()];
    if (alias) return t(alias);
    const key = 'error_' + raw.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
    return t(key) !== key ? t(key) : t('error');
  }

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function parseDate(value) {
    if (!value) return null;
    // Postgres returns `2026-11-19 05:41:47+00`; `+00` is not valid ISO 8601.
    const normalized = String(value).trim()
      .replace(/\s+/, 'T')
      .replace(/([+-]\d{2})$/, '$1:00');
    const date = new Date(normalized);
    return Number.isNaN(date.getTime()) ? null : date;
  }

  function ensureStyles() {
    if (document.querySelector('link[data-sharepoint-share-modal]')) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = '/project/sharepoint/assets/css/share-modal.css?v=5';
    link.dataset.sharepointShareModal = '1';
    document.head.appendChild(link);
  }

  function defaultPermissions(appId, resourceType) {
    if (resourceType !== 'app') return ['view', 'download'];
    if (appId === 'films') return ['view'];
    if (appId === 'smart_home') return ['view', 'edit'];
    return ['view', 'interact'];
  }

  function permissionList(appId, resourceType) {
    if (resourceType !== 'app') return ['view', 'download', 'share'];
    if (appId === 'films') return ['view', 'share'];
    if (appId === 'smart_home') return ['view', 'edit', 'share'];
    return ['view', 'interact', 'edit', 'share'];
  }

  class SharepointShareModal {
    constructor(appId, resourceType, resourceId, options) {
      this.appId = APP_META[appId] ? appId : 'cloud';
      this.resourceType = resourceType || 'app';
      this.resourceId = resourceId || '';
      this.options = options || {};
      this.dialog = null;
      this.overlay = null;
      this.share = null;
      this.onKey = this.onKey.bind(this);
    }

    meta() { return APP_META[this.appId]; }

    note() {
      const key = 'note_' + this.appId;
      return t(key) !== key ? t(key) : this.meta().note;
    }

    expiryOptions() {
      return [['1m', t('exp_1m')], ['1h', t('exp_1h')], ['1d', t('exp_1d')], ['1w', t('exp_1w')], ['3m', t('exp_3m')], ['6m', t('exp_6m')], ['1y', t('exp_1y')], ['never', t('never')]]
        .map(([value, label]) => '<option value="' + value + '"' + (value === '1m' ? ' selected' : '') + '>' + esc(label) + '</option>')
        .join('');
    }

    open() {
      ensureStyles();
      this.overlay = this.buildOverlay();
      document.body.appendChild(this.overlay);
      document.body.classList.add('sp-share-open', 'sp-share-app-' + this.appId);
      requestAnimationFrame(() => {
        this.overlay.classList.add('is-open');
        const focus = this.overlay.querySelector('[autofocus], input, select, button');
        if (focus) setTimeout(() => focus.focus(), 60);
      });
      document.addEventListener('keydown', this.onKey);
      return this;
    }

    close() {
      if (!this.overlay) return;
      this.overlay.classList.remove('is-open');
      document.removeEventListener('keydown', this.onKey);
      const node = this.overlay;
      const appClass = 'sp-share-app-' + this.appId;
      this.overlay = null;
      setTimeout(() => {
        node.remove();
        if (!document.querySelector('.sp-share-overlay')) {
          document.body.classList.remove('sp-share-open', appClass);
        }
      }, 180);
    }

    onKey(event) {
      if (event.key === 'Escape') this.close();
    }

    buildOverlay() {
      const overlay = document.createElement('div');
      overlay.className = 'sp-share-overlay';
      overlay.setAttribute('role', 'presentation');
      overlay.addEventListener('click', (event) => {
        if (event.target === overlay) this.close();
      });
      overlay.innerHTML = this.buildModal();
      this.dialog = overlay.querySelector('.sp-share-modal');
      this.bind(overlay);
      return overlay;
    }

    buildModal() {
      const meta = this.meta();
      const defaults = this.options.defaultPermissions || defaultPermissions(this.appId, this.resourceType);
      const permissions = permissionList(this.appId, this.resourceType).map((permission) => {
        const selected = defaults.indexOf(permission) !== -1;
        return '<label class="sp-share__chip' + (selected ? ' is-selected' : '') + '">' +
          '<input type="checkbox" name="permissions" value="' + permission + '"' + (selected ? ' checked' : '') + '>' +
          icon(PERMISSION_META[permission].icon) + esc(t(PERMISSION_META[permission].key)) +
          '</label>';
      }).join('');
      const dataMode = this.resourceType === 'app'
        ? '<section class="sp-share__section"><span class="sp-share__label">' + esc(t('dataMode')) + '</span>' +
          '<div class="sp-share__segmented">' +
            '<label class="sp-share__segment is-selected"><input type="radio" name="data_mode" value="live" checked>' + esc(t('live')) + '</label>' +
            '<label class="sp-share__segment"><input type="radio" name="data_mode" value="snapshot">' + esc(t('snapshot')) + '</label>' +
          '</div></section>'
        : '';
      return '<div class="sp-share-modal sp-share--' + esc(this.appId) + '" role="dialog" aria-modal="true" aria-labelledby="sp-share-title">' +
        '<header class="sp-share__head">' +
          '<span class="sp-share__badge" aria-hidden="true">' + icon(meta.icon) + '</span>' +
          '<div class="sp-share__head-text">' +
            '<p class="sp-share__eyebrow">' + esc(meta.name) + ' · SharePoint</p>' +
            '<h2 class="sp-share__title" id="sp-share-title">' + esc(t('create')) + '</h2>' +
          '</div>' +
          '<button class="sp-share__close" type="button" data-close aria-label="' + esc(t('cancel')) + '">&times;</button>' +
        '</header>' +
        '<div class="sp-share__body">' +
          '<div data-error hidden></div>' +
          '<form data-form>' +
            '<section class="sp-share__section">' +
              '<label class="sp-share__label" for="sp-share-title-input">' + esc(t('title')) + '</label>' +
              '<input class="sp-share__input" id="sp-share-title-input" name="title" type="text" autofocus value="' + esc(this.options.title || '') + '" placeholder="' + esc(meta.name) + '">' +
            '</section>' +
            '<section class="sp-share__section">' +
              '<span class="sp-share__label">' + esc(t('permissions')) + '</span>' +
              '<div class="sp-share__chips" data-permissions>' + permissions + '</div>' +
              '<p class="sp-share__hint">' + esc(this.note()) + '</p>' +
            '</section>' +
            '<section class="sp-share__section">' +
              '<span class="sp-share__label">' + esc(t('access')) + '</span>' +
              '<div class="sp-share__segmented">' +
                '<label class="sp-share__segment is-selected"><input type="radio" name="access_mode" value="public" checked>' + esc(t('public')) + '</label>' +
                '<label class="sp-share__segment"><input type="radio" name="access_mode" value="restricted">' + esc(t('restricted')) + '</label>' +
              '</div>' +
            '</section>' +
            '<div class="sp-share__row">' +
              '<section class="sp-share__section">' +
                '<label class="sp-share__label" for="sp-share-expiry">' + esc(t('expiry')) + '</label>' +
                '<select class="sp-share__select" id="sp-share-expiry" name="expires_option">' +
                  this.expiryOptions() +
                '</select>' +
              '</section>' +
              '<section class="sp-share__section">' +
                '<label class="sp-share__label" for="sp-share-views">' + esc(t('maxViews')) + '</label>' +
                '<input class="sp-share__input" id="sp-share-views" name="max_views" type="number" min="0" step="1" value="0" inputmode="numeric">' +
                '<p class="sp-share__hint">' + esc(t('unlimited')) + '</p>' +
              '</section>' +
            '</div>' +
            '<section class="sp-share__section">' +
              '<label class="sp-share__label" for="sp-share-password">' + esc(t('password')) + ' <span style="font-weight:500;text-transform:none;letter-spacing:0">(' + esc(t('optional')) + ')</span></label>' +
              '<input class="sp-share__input" id="sp-share-password" name="password" type="password" autocomplete="new-password" placeholder="••••••••">' +
            '</section>' +
            dataMode +
          '</form>' +
        '</div>' +
        '<footer class="sp-share__foot">' +
          '<button class="sp-share__btn sp-share__btn--ghost" type="button" data-close>' + esc(t('cancel')) + '</button>' +
          '<button class="sp-share__btn sp-share__btn--primary" type="button" data-create>' + esc(t('createBtn')) + '</button>' +
        '</footer>' +
      '</div>';
    }

    bind(overlay) {
      overlay.querySelectorAll('[data-close]').forEach((button) => {
        button.addEventListener('click', () => this.close());
      });
      // Labels already toggle their hidden checkbox/radio. A second manual
      // toggle here made every click cancel itself, so chips could never be
      // deselected. Only mirror the native input state into the CSS class.
      overlay.querySelectorAll('.sp-share__chip input').forEach((input) => {
        input.addEventListener('change', () => {
          const chip = input.closest('.sp-share__chip');
          if (chip) chip.classList.toggle('is-selected', input.checked);
        });
      });
      overlay.querySelectorAll('.sp-share__segment input').forEach((input) => {
        input.addEventListener('change', () => {
          const group = input.closest('.sp-share__segmented');
          if (!group) return;
          group.querySelectorAll('.sp-share__segment').forEach((segment) => {
            segment.classList.toggle('is-selected', !!segment.querySelector('input:checked'));
          });
        });
      });
      overlay.querySelector('[data-create]').addEventListener('click', () => this.create());
      overlay.querySelector('form').addEventListener('submit', (event) => {
        event.preventDefault();
        this.create();
      });
    }

    payload() {
      const form = this.overlay.querySelector('form');
      const formData = new FormData(form);
      const permissions = formData.getAll('permissions').map(String);
      return {
        resource_type: this.resourceType,
        resource_app: this.appId,
        resource_id: this.resourceId,
        resource_data: this.options.resourceData || {},
        title: String(formData.get('title') || this.options.title || '').trim(),
        description: this.options.description || '',
        access_mode: formData.get('access_mode') || 'public',
        permissions: permissions.length ? permissions : ['view'],
        expires_option: formData.get('expires_option') || '1m',
        password: String(formData.get('password') || ''),
        max_views: Math.max(0, parseInt(formData.get('max_views') || '0', 10) || 0),
        data_mode: formData.get('data_mode') || 'live'
      };
    }

    async create() {
      const button = this.overlay.querySelector('[data-create]');
      const errorBox = this.overlay.querySelector('[data-error]');
      errorBox.hidden = true;
      button.disabled = true;
      const original = button.textContent;
      button.innerHTML = '<span class="sp-share__spinner" aria-hidden="true"></span>' + esc(t('createBtn'));
      try {
        const endpoint = this.options.apiUrl || ('/apps/' + this.appId + '/api/share.php');
        const response = await fetch(endpoint, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify(this.payload())
        });
        const text = await response.text();
        let result = null;
        try { result = JSON.parse(text); } catch (e) { result = null; }
        if (!result) {
          throw new Error(translatedError(response.status === 401 ? 'not authenticated' : 'create_failed'));
        }
        if (!response.ok || !result.success) throw new Error(translatedError(result.error));
        this.share = result.share;
        this.showSuccess();
      } catch (error) {
        errorBox.hidden = false;
        errorBox.className = 'sp-share__error';
        const message = error && error.message && error.message !== 'Failed to fetch'
          ? error.message
          : t('error_network');
        errorBox.textContent = message || t('error');
      } finally {
        button.disabled = false;
        button.textContent = original;
      }
    }

    showSuccess() {
      const share = this.share || {};
      const link = String(share.link || '');
      const expiresDate = parseDate(share.expires_at);
      const expires = expiresDate ? expiresDate.toLocaleString() : t('noExpiry');
      this.dialog.innerHTML =
        '<header class="sp-share__head">' +
          '<span class="sp-share__badge" aria-hidden="true">' + icon('check') + '</span>' +
          '<div class="sp-share__head-text"><p class="sp-share__eyebrow">' + esc(this.meta().name) + ' · SharePoint</p><h2 class="sp-share__title">' + esc(t('linkReady')) + '</h2></div>' +
          '<button class="sp-share__close" type="button" data-close aria-label="' + esc(t('done')) + '">&times;</button>' +
        '</header>' +
        '<div class="sp-share__body"><div class="sp-share__success">' +
          '<div class="sp-share__success-icon" aria-hidden="true">' + icon('link') + '</div>' +
          '<h3>' + esc(this.meta().name) + '</h3>' +
          '<p>' + esc(t('linkReadySub')) + '</p>' +
          '<div class="sp-share__link-box">' +
            '<input type="text" readonly value="' + esc(link) + '" aria-label="' + esc(t('link')) + '">' +
            '<button class="sp-share__mini" type="button" data-copy title="' + esc(t('copy')) + '">' + icon('copy') + '</button>' +
            '<a class="sp-share__mini" href="' + esc(link) + '" target="_blank" rel="noopener" title="' + esc(t('open')) + '">' + icon('open') + '</a>' +
          '</div>' +
          '<div class="sp-share__meta"><span>' + esc(t('expires')) + ': ' + esc(expires) + '</span><span>' + esc((share.permissions || []).join(' · ')) + '</span></div>' +
          '<div class="sp-share__qr" data-qr></div>' +
        '</div></div>' +
        '<footer class="sp-share__foot"><button class="sp-share__btn sp-share__btn--ghost" type="button" data-close>' + esc(t('done')) + '</button><button class="sp-share__btn sp-share__btn--primary" type="button" data-copy>' + esc(t('copy')) + '</button></footer>';
      this.dialog.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', () => this.close()));
      this.dialog.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', () => this.copy(link, button));
      });
      const qr = this.dialog.querySelector('[data-qr]');
      if (qr && window.SharepointQR) window.SharepointQR.render(qr, link);
    }

    copy(link, button) {
      const done = () => {
        if (button && button.tagName === 'BUTTON') {
          const prev = button.innerHTML;
          button.textContent = t('copied');
          setTimeout(() => { button.innerHTML = prev; }, 1400);
        }
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(link).then(done).catch(() => this.fallbackCopy(link, done));
      } else {
        this.fallbackCopy(link, done);
      }
    }

    fallbackCopy(link, done) {
      const input = document.createElement('textarea');
      input.value = link;
      input.style.position = 'fixed';
      input.style.opacity = '0';
      document.body.appendChild(input);
      input.select();
      try { document.execCommand('copy'); done(); } catch (e) {}
      input.remove();
    }
  }

  window.SharepointShareModal = SharepointShareModal;
  window.openShareModal = function (appId, resourceType, resourceId, options) {
    return new SharepointShareModal(appId, resourceType, resourceId, options || {}).open();
  };
})();
