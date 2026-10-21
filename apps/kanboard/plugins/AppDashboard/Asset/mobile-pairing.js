(function () {
  function ready(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  ready(function () {
    var modal = document.getElementById('kb-mobile-app-modal');
    if (!modal) return;

    var qrFrame = document.getElementById('kb-mobile-app-qr-code');
    var placeholder = modal.querySelector('.kb-ma-qr-placeholder');
    var genBtn = document.getElementById('kb-mobile-app-qr-generate');
    var revokeBtn = document.getElementById('kb-mobile-token-revoke');
    var hintBtn = document.getElementById('kb-mobile-manual-hint');
    var closeBtn = document.getElementById('kb-mobile-app-close');
    var openBtn = document.getElementById('kb-open-mobile-app');

    var lanEndpoint = window.__kbMobileLanEndpoint || '';
    var currentHost = window.location.hostname;
    var endpoint =
      lanEndpoint && (currentHost === 'localhost' || currentHost === '127.0.0.1' || currentHost === '::1')
        ? lanEndpoint
        : window.location.origin + '/api/kanboard';

    var pairing = { token: '', link: '' };
    var hintModal = document.getElementById('kb-manual-pair-modal');
    var hintClose = document.getElementById('kb-manual-pair-close');
    var hintEndpoint = document.getElementById('kb-manual-pair-endpoint');
    var hintLink = document.getElementById('kb-manual-pair-link');
    var hintToken = document.getElementById('kb-manual-pair-token');
    var regenBtn = document.getElementById('kb-manual-pair-regenerate');
    var regenLabel = regenBtn ? regenBtn.innerHTML : '';
    var revokeLabel = revokeBtn ? revokeBtn.innerHTML : '';
    var noTokenMsg = 'Generate a QR code first.';

    function openModal(el) {
      if (!el) return;
      el.style.display = 'flex';
      el.setAttribute('aria-hidden', 'false');
      document.body.classList.add('modal-open');
    }
    function closeModal(el) {
      if (!el) return;
      el.style.display = 'none';
      el.setAttribute('aria-hidden', 'true');
      var otherOpen =
        (modal && modal.style.display === 'flex') || (hintModal && hintModal.style.display === 'flex');
      if (!otherOpen) document.body.classList.remove('modal-open');
    }

    function refreshHintFields() {
      if (hintEndpoint) hintEndpoint.textContent = endpoint;
      if (hintLink) hintLink.textContent = pairing.link || '—';
      if (hintToken) hintToken.textContent = pairing.token || noTokenMsg;
    }

    function openHint() {
      refreshHintFields();
      openModal(hintModal);
    }

    openBtn && openBtn.addEventListener('click', function () { openModal(modal); });
    closeBtn && closeBtn.addEventListener('click', function () { closeModal(modal); });
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(modal); });
    hintBtn && hintBtn.addEventListener('click', openHint);
    hintClose && hintClose.addEventListener('click', function () { closeModal(hintModal); });
    hintModal && hintModal.addEventListener('click', function (e) {
      if (e.target === hintModal) closeModal(hintModal);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      if (hintModal && hintModal.style.display === 'flex') closeModal(hintModal);
      else if (modal.style.display === 'flex') closeModal(modal);
    });

    async function fetchCsrf() {
      try {
        var r = await fetch('/csrf', { method: 'GET', credentials: 'same-origin', cache: 'no-store' });
        var d = await r.json().catch(function () { return null; });
        return d && d.csrf ? String(d.csrf) : '';
      } catch (e) {
        return '';
      }
    }

    async function ensureQrCodeStyling() {
      if (typeof QRCodeStyling !== 'undefined') return QRCodeStyling;
      var url = 'https://cdn.jsdelivr.net/npm/qr-code-styling@1.5.0/lib/qr-code-styling.js';
      var res = await fetch(url, { cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      var code = await res.text();
      var prevDefine = typeof define !== 'undefined' ? define : undefined;
      try {
        if (typeof window !== 'undefined') window.define = undefined;
        if (typeof self !== 'undefined') self.define = undefined;
        (0, eval)(code);
      } finally {
        if (typeof window !== 'undefined' && typeof prevDefine !== 'undefined') window.define = prevDefine;
        if (typeof self !== 'undefined' && typeof prevDefine !== 'undefined') self.define = prevDefine;
      }
      if (typeof window !== 'undefined' && typeof window.QRCodeStyling !== 'undefined') return window.QRCodeStyling;
      if (typeof self !== 'undefined' && typeof self.QRCodeStyling !== 'undefined') return self.QRCodeStyling;
      return QRCodeStyling;
    }

    async function renderPairingQr(hostEl, data) {
      try {
        var QRCodeStylingCtor = await ensureQrCodeStyling();
        if (typeof QRCodeStylingCtor === 'undefined') {
          hostEl.innerHTML = '<p style="color:#121212;padding:12px;">QR code unavailable</p>';
          return;
        }
        hostEl.innerHTML = '';
        var qr = new QRCodeStylingCtor({
          width: 220,
          height: 220,
          type: 'svg',
          data: data,
          margin: 0,
          qrOptions: { errorCorrectionLevel: 'H' },
          dotsOptions: {
            type: 'rounded',
            gradient: {
              type: 'linear',
              rotation: Math.PI / 4,
              colorStops: [
                { offset: 0, color: '#6366f1' },
                { offset: 0.5, color: '#818cf8' },
                { offset: 1, color: '#22d3ee' },
              ],
            },
          },
          cornersSquareOptions: { type: 'extra-rounded', color: '#6366f1' },
          cornersDotOptions: { type: 'dot', color: '#818cf8' },
          backgroundOptions: { color: 'rgba(255,255,255,0)' },
        });
        qr.append(hostEl);
      } catch (error) {
        console.error('Pairing QR error:', error);
        hostEl.innerHTML = '<p style="color:#121212;padding:12px;">QR code unavailable</p>';
      }
    }

    async function generateQr() {
      var errEl = document.getElementById('kb-mobile-app-qr-error');
      function showErr(msg) {
        if (errEl) {
          errEl.textContent = msg;
          errEl.style.display = 'block';
        }
      }
      function hideErr() {
        if (errEl) {
          errEl.style.display = 'none';
          errEl.textContent = '';
        }
      }
      async function sendRequest(csrf) {
        var fd = new FormData();
        fd.append('action', 'generate_mcp_token');
        fd.append('generate_mcp_token', '1');
        fd.append('ajax', '1');
        fd.append('mcp_token_name', 'Planning Mobile');
        fd.append('token_app', 'kanboard');
        var opts = { method: 'POST', body: fd, credentials: 'same-origin' };
        if (csrf) {
          fd.append('_csrf', csrf);
          opts.headers = { 'X-CSRF-TOKEN': csrf };
        }
        return fetch('/main', opts);
      }
      try {
        hideErr();
        var csrf = await fetchCsrf();
        var res = await sendRequest(csrf);
        var data = await res.json().catch(function () { return null; });
        if (data && data.error === 'csrf_forbidden') {
          csrf = await fetchCsrf();
          res = await sendRequest(csrf);
          data = await res.json().catch(function () { return null; });
        }
        if (!data || !data.success || !data.token) throw new Error((data && data.error) || 'Token generation failed');
        pairing.token = String(data.token);
        pairing.link =
          'appdashboard-planning://auth?endpoint=' +
          encodeURIComponent(endpoint) +
          '&token=' +
          encodeURIComponent(data.token);
        if (placeholder) placeholder.style.display = 'none';
        if (qrFrame) {
          qrFrame.style.display = 'flex';
          qrFrame.innerHTML = '';
          await renderPairingQr(qrFrame, pairing.link);
        }
        if (genBtn) genBtn.style.display = 'none';
        if (revokeBtn) revokeBtn.style.display = '';
        refreshHintFields();
      } catch (e) {
        console.error('Pairing QR error:', e);
        showErr(String(e && e.message ? e.message : e));
      }
    }

    async function revokeToken() {
      var csrf = await fetchCsrf();
      var fd = new FormData();
      fd.append('action', 'revoke_mcp_token');
      fd.append('revoke_mcp_token', '1');
      fd.append('ajax', '1');
      fd.append('token_app', 'kanboard');
      if (csrf) fd.append('_csrf', csrf);
      var opts = { method: 'POST', body: fd, credentials: 'same-origin' };
      if (csrf) opts.headers = { 'X-CSRF-TOKEN': csrf };
      var res = await fetch('/main', opts);
      var data = await res.json().catch(function () { return null; });
      if (!data || !data.success) throw new Error((data && data.error) || 'Token revoke failed');
      pairing = { token: '', link: '' };
      if (qrFrame) {
        qrFrame.innerHTML = '';
        qrFrame.style.display = 'none';
      }
      if (placeholder) placeholder.style.display = '';
      if (genBtn) genBtn.style.display = '';
      if (revokeBtn) revokeBtn.style.display = 'none';
      refreshHintFields();
    }

    genBtn && genBtn.addEventListener('click', async function () {
      genBtn.disabled = true;
      try {
        await generateQr();
      } finally {
        genBtn.disabled = false;
      }
    });
    revokeBtn && revokeBtn.addEventListener('click', async function () {
      if (revokeBtn.disabled) return;
      revokeBtn.disabled = true;
      try {
        await revokeToken();
      } catch (e) {
        console.error('Pairing token revoke error:', e);
      } finally {
        revokeBtn.disabled = false;
        revokeBtn.innerHTML = revokeLabel;
      }
    });
    regenBtn && regenBtn.addEventListener('click', async function () {
      if (regenBtn.disabled) return;
      regenBtn.disabled = true;
      try {
        await generateQr();
      } finally {
        regenBtn.disabled = false;
        regenBtn.innerHTML = regenLabel;
      }
    });

    document.querySelectorAll('#kb-manual-pair-modal .kb-ma-copy-btn').forEach(function (btn) {
      btn.addEventListener('click', async function () {
        var target = document.getElementById(btn.getAttribute('data-copy-target'));
        var text = target ? target.textContent.trim() : '';
        if (!text || text === '—' || text === noTokenMsg) {
          btn.classList.add('is-empty');
          setTimeout(function () { btn.classList.remove('is-empty'); }, 700);
          return;
        }
        try {
          await navigator.clipboard.writeText(text);
          btn.classList.remove('is-copied');
          void btn.offsetWidth;
          btn.classList.add('is-copied');
          setTimeout(function () { btn.classList.remove('is-copied'); }, 1600);
        } catch (e) {
          var ta = document.createElement('textarea');
          ta.value = text;
          ta.style.position = 'fixed';
          ta.style.opacity = '0';
          document.body.appendChild(ta);
          ta.select();
          try {
            document.execCommand('copy');
            btn.classList.add('is-copied');
            setTimeout(function () { btn.classList.remove('is-copied'); }, 1600);
          } catch (e2) {}
          ta.remove();
        }
      });
    });
  });
})();
