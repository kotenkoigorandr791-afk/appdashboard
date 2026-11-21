(function (root) {
  'use strict';
  var CDN = 'https://cdn.jsdelivr.net/npm/qr-code-styling@1.5.0/lib/qr-code-styling.js';
  var loading = null;
  function restoreDefine(prev) {
    try {
      if (typeof prev === 'undefined') {
        try { delete root.define; } catch (_) { root.define = undefined; }
      } else {
        root.define = prev;
      }
    } catch (_) {}
  }
  function ensureQrCodeStyling() {
    if (root.QRCodeStyling) return Promise.resolve(root.QRCodeStyling);
    if (loading) return loading;
    loading = new Promise(function (resolve, reject) {
      var prevDefine = root.define;
      try { root.define = undefined; } catch (_) {}
      var script = document.createElement('script');
      script.src = CDN;
      script.async = true;
      script.onload = function () {
        restoreDefine(prevDefine);
        if (root.QRCodeStyling) resolve(root.QRCodeStyling);
        else reject(new Error('QRCodeStyling missing after load'));
      };
      script.onerror = function () {
        restoreDefine(prevDefine);
        loading = null;
        reject(new Error('QR library failed to load'));
      };
      (document.head || document.documentElement).appendChild(script);
    });
    return loading;
  }
  function qrStyleOptions(data, size) {
    size = size || 256;
    return {
      width: size,
      height: size,
      type: 'svg',
      data: data,
      margin: Math.max(4, Math.round(size / 32)),
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
  function renderQrInto(container, data, size) {
    if (!container || !data) return Promise.reject(new Error('missing container/data'));
    container.innerHTML = '';
    var host = document.createElement('div');
    host.style.width = '100%';
    host.style.display = 'grid';
    host.style.placeItems = 'center';
    container.appendChild(host);
    return ensureQrCodeStyling().then(function (Ctor) {
      new Ctor(qrStyleOptions(data, size)).append(host);
    }).catch(function (err) {
      var img = document.createElement('img');
      img.alt = 'QR';
      img.width = size || 256;
      img.height = size || 256;
      img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=' + (size || 256) + 'x' + (size || 256) + '&data=' + encodeURIComponent(data);
      host.appendChild(img);
      throw err;
    });
  }
  root.apEnsureQrCodeStyling = ensureQrCodeStyling;
  root.apRenderQrInto = renderQrInto;
  root.apQrStyleOptions = qrStyleOptions;
})(typeof window !== 'undefined' ? window : this);
