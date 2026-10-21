/* SharePoint — QR code helper.
 * Uses the same qr-code-styling gradient palette as the Cloud app so every
 * public/share-dialog QR code has one consistent look. */
(function () {
  'use strict';

  let loading = null;

  function loadStyling() {
    if (window.QRCodeStyling) return Promise.resolve(window.QRCodeStyling);
    if (loading) return loading;
    loading = new Promise(function (resolve, reject) {
      const script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/qr-code-styling@1.5.0/lib/qr-code-styling.js';
      script.async = true;
      script.onload = function () { resolve(window.QRCodeStyling || null); };
      script.onerror = function () { reject(new Error('qr library failed')); };
      document.head.appendChild(script);
    });
    return loading;
  }

  function options(value) {
    return {
      width: 256,
      height: 256,
      type: 'svg',
      data: value,
      margin: 8,
      qrOptions: { errorCorrectionLevel: 'H' },
      dotsOptions: {
        type: 'rounded',
        gradient: {
          type: 'linear',
          rotation: Math.PI / 4,
          colorStops: [
            { offset: 0, color: '#7c3aed' },
            { offset: 0.5, color: '#2563eb' },
            { offset: 1, color: '#06b6d4' }
          ]
        }
      },
      cornersSquareOptions: { type: 'extra-rounded', color: '#2563eb' },
      cornersDotOptions: { type: 'dot', color: '#7c3aed' },
      backgroundOptions: { color: 'rgba(255,255,255,0)' }
    };
  }

  function render(container, value) {
    if (!container || !value) return;
    container.innerHTML = '';
    const card = document.createElement('div');
    card.className = 'sp-qr-card';
    container.appendChild(card);
    loadStyling().then(function (QRCodeStylingCtor) {
      if (!QRCodeStylingCtor) throw new Error('QRCodeStyling missing');
      new QRCodeStylingCtor(options(value)).append(card);
    }).catch(function () {
      const img = document.createElement('img');
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
    const dialog = document.getElementById('sp-qr-dialog');
    const canvas = document.getElementById('sp-qr-canvas');
    const value = canvas ? (canvas.dataset.spQrValue || '') : '';
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
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
