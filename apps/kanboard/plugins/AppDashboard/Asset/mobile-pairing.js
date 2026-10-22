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
    var settingsBtn = document.getElementById('kb-open-settings');

    var lanEndpoint = modal.getAttribute('data-endpoint') || '';
    var accountEmail = modal.getAttribute('data-account') || '';
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
    function activateTab(name) {
      var tabs = document.querySelectorAll('.kb-settings-tab');
      var panels = document.querySelectorAll('.kb-settings-panel');
      if (!tabs.length) return;
      var known = false;
      tabs.forEach(function (tab) {
        var active = tab.getAttribute('data-kb-settings-tab') === name;
        if (active) known = true;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      if (!known) name = 'profile';
      panels.forEach(function (panel) {
        panel.classList.toggle('is-active', panel.id === 'kb-settings-panel-' + name);
      });
    }
    function openSettings(tab) {
      refreshConnectionFields();
      activateTab(tab || 'profile');
      openModal(modal);
    }
    function refreshConnectionFields() {
      var endpointEl = document.getElementById('kb-connection-endpoint');
      if (endpointEl) endpointEl.textContent = endpoint;
      var accountEl = document.getElementById('kb-connection-account');
      if (accountEl && accountEmail) accountEl.textContent = accountEmail;
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

    openBtn && openBtn.addEventListener('click', function () { openSettings('mobile'); });
    settingsBtn && settingsBtn.addEventListener('click', function () { openSettings('profile'); });
    document.querySelectorAll('.kb-settings-tab').forEach(function (tab) {
      tab.addEventListener('click', function () {
        activateTab(tab.getAttribute('data-kb-settings-tab') || 'profile');
      });
    });
    document.querySelectorAll('[data-kb-open-tab]').forEach(function (trigger) {
      trigger.addEventListener('click', function () {
        activateTab(trigger.getAttribute('data-kb-open-tab') || 'profile');
      });
    });
    var connectionManual = document.getElementById('kb-connection-manual');
    connectionManual && connectionManual.addEventListener('click', openHint);
    var connectionRevoke = document.getElementById('kb-connection-revoke');
    connectionRevoke && connectionRevoke.addEventListener('click', async function () {
      if (connectionRevoke.disabled) return;
      connectionRevoke.disabled = true;
      try {
        await revokeToken();
        markTokensRevoked();
      } catch (e) {
        console.error('Pairing token revoke error:', e);
      } finally {
        connectionRevoke.disabled = false;
      }
    });
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

    function renderPairingQr(hostEl, data) {
      try {
        // Bundled qrcode-generator (MIT). Rendering locally keeps the QR working
        // under the Kanboard CSP, which blocks remote scripts.
        if (typeof qrcode !== 'function') throw new Error('QR generator not loaded');
        var qr = qrcode(0, 'M');
        qr.addData(data);
        qr.make();
        hostEl.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 2, scalable: true });
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
          renderPairingQr(qrFrame, pairing.link);
        }
        if (genBtn) genBtn.style.display = 'none';
        if (revokeBtn) revokeBtn.style.display = '';
        refreshHintFields();
      } catch (e) {
        console.error('Pairing QR error:', e);
        showErr(String(e && e.message ? e.message : e));
      }
    }

    function markTokensRevoked() {
      var devices = document.querySelector('.kb-connection-devices');
      if (devices) {
        devices.innerHTML = '<li class="kb-connection-empty">No phones connected yet.</li>';
      }
      var status = document.querySelector('.kb-connection-status strong');
      if (status) status.textContent = 'No phones connected yet';
      var note = document.querySelector('.kb-connection-status small');
      if (note) note.textContent = 'Generate a pairing code to connect the Android app.';
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
