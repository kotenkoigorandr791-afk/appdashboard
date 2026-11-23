/* SharePoint — QR code helper (CSP-safe, no eval). */
(function () {
  'use strict';
  function loadLoader() {
    if (typeof window.apEnsureQrCodeStyling === 'function') {
      return Promise.resolve(window.apEnsureQrCodeStyling);
    }
    return new Promise(function (resolve, reject) {
      var existing = document.querySelector('script[data-ap-qr-loader]');
      if (existing) {
        existing.addEventListener('load', function () { resolve(window.apEnsureQrCodeStyling); });
        existing.addEventListener('error', reject);
        return;
      }
      var s = document.createElement('script');
      s.src = '/shell/assets/qr-code-loader.js?v=1';
      s.async = true;
      s.dataset.apQrLoader = '1';
      s.onload = function () { resolve(window.apEnsureQrCodeStyling); };
      s.onerror = function () { reject(new Error('qr loader failed')); };
      document.head.appendChild(s);
    });
  }
  function options(value, size) {
    size = size || 256;
    return (window.apQrStyleOptions ? window.apQrStyleOptions(value, size) : {
      width: size, height: size, type: 'svg', data: value, margin: Math.max(2, Math.round(size / 40)),
      qrOptions: { errorCorrectionLevel: 'H' },
      dotsOptions: { type: 'rounded', gradient: { type: 'linear', rotation: Math.PI / 4, colorStops: [{ offset: 0, color: '#7c3aed' }, { offset: 0.5, color: '#2563eb' }, { offset: 1, color: '#06b6d4' }] } },
      cornersSquareOptions: { type: 'extra-rounded', color: '#2563eb' },
      cornersDotOptions: { type: 'dot', color: '#7c3aed' },
      backgroundOptions: { color: 'rgba(255,255,255,0)' }
    });
  }
  function render(container, value, size) {
    if (!container || !value) return;
    container.innerHTML = '';
    var card = document.createElement('div');
    card.className = 'sp-qr-card';
    container.appendChild(card);
    var px = size || (container.classList.contains('sp-share__qr--cloud') ? 200 : 256);
    loadLoader().then(function (ensure) {
      return ensure().then(function (Ctor) {
        new Ctor(options(value, px)).append(card);
      });
    }).catch(function () {
      var img = document.createElement('img');
      img.alt = (window.SP_I18N && window.SP_I18N.qr_code) || 'QR code';
      img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=256x256&data=' + encodeURIComponent(value);
      img.onerror = function () {
        card.textContent = value;
        card.className += ' sp-qr-card--fallback';
      };
      card.appendChild(img);
    });
  }
  function openQr() {
    var dialog = document.getElementById('sp-qr-dialog');
    var canvas = document.getElementById('sp-qr-canvas');
    var value = canvas ? (canvas.dataset.spQrValue || '') : '';
    if (!dialog || !canvas) return;
    if (window.MD3Dialog) window.MD3Dialog.open(dialog);
    else dialog.classList.add('md3-dialog--open');
    if (canvas.dataset.spQrRendered !== value) {
      render(canvas, value);
      canvas.dataset.spQrRendered = value;
    }
  }
  function init() {
    document.querySelectorAll('[data-sp-qr]').forEach(function (trigger) {
      if (trigger.dataset.spQrInit === '1') return;
      trigger.dataset.spQrInit = '1';
      trigger.addEventListener('click', function (event) {
        event.preventDefault();
        if (window.MD3Menu) window.MD3Menu.closeAll();
        openQr();
      });
    });
  }
  window.SharepointQR = { render: render, open: openQr };
  window.spRenderQr = render;
  window.spOpenQr = openQr;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
