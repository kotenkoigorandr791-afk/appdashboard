window.initSyncLogModal = function initSyncLogModal(i18n) {
  const openBtn = document.getElementById('btn-open-sync-log');
  const modal = document.getElementById('sync-log-modal');
  const closeBtn = document.getElementById('btn-close-sync-log');
  const body = document.getElementById('sync-log-body');
  if (!openBtn || !modal || !closeBtn || !body) return;

  const renderRows = (rows) => {
    body.innerHTML = '';
    rows.forEach((row) => {
      const tr = document.createElement('tr');
      const level = String(row.level || 'INFO').toUpperCase();
      const levelClass = level === 'ERROR'
        ? 'sync-log-level-error'
        : (level === 'WARNING' ? 'sync-log-level-warning' : 'sync-log-level-info');
      tr.innerHTML = `<td>${new Date((row.ts || 0) * 1000).toLocaleString()}</td><td class="${levelClass}">${level}</td><td>${t(i18n, `${row.root || 'main'}Root`)}</td><td>${row.action || ''}</td><td>${row.path || ''}</td><td>${row.message || ''}</td>`;
      body.appendChild(tr);
    });
  };

  openBtn.addEventListener('click', async () => {
    try {
      const res = await api('get_sync_log', { limit: 300 }, 'GET');
      renderRows(res.items || []);
      modal.classList.remove('hidden');
    } catch (err) {
      flashStatus(t(i18n, 'errorPrefix') + err.message);
    }
  });

  closeBtn.addEventListener('click', () => modal.classList.add('hidden'));
  modal.addEventListener('click', (event) => {
    if (event.target === modal) {
      modal.classList.add('hidden');
    }
  });
};

window.initSyncTokenModal = function initSyncTokenModal(i18n) {
  const modal = document.getElementById('sync-token-modal');
  const openBtn = document.getElementById('btn-open-token-modal');
  const closeBtn = document.getElementById('btn-close-token-modal');
  const btnGenerate = document.getElementById('btn-generate-token');
  const btnRegenerate = document.getElementById('btn-regenerate-token');
  const btnRevoke = document.getElementById('btn-revoke-token');
  const btnRevokeAll = document.getElementById('btn-revoke-all-tokens');
  const btnCopy = document.getElementById('btn-copy-token');
  const tokenDisplay = document.getElementById('sync-token-display');
  const tokenValue = document.getElementById('sync-token-value');
  const tokenInfo = document.getElementById('token-info');
  const tokensList = document.getElementById('sync-tokens-list');
  const tokensListContainer = document.getElementById('tokens-list-container');
  const selectedTokenHint = document.getElementById('selected-token-hint');

  if (!modal || !openBtn) return;

  let currentToken = null;
  let currentTokenHash = null;
  let tokens = [];
  let selectedTokenHash = null;

  function tr(key, fallback) {
    if (i18n && typeof t === 'function') return t(i18n, key);
    return fallback;
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatDateTime(unixTime) {
    if (!unixTime) return tr('neverUsed', 'Never');
    return new Date(unixTime * 1000).toLocaleString();
  }

  function formatDate(unixTime) {
    if (!unixTime) return '—';
    return new Date(unixTime * 1000).toLocaleDateString();
  }

  function getSelectedToken() {
    return tokens.find((token) => token.token_hash === selectedTokenHash) || null;
  }

  function syncSelection() {
    if (tokens.length === 0) {
      selectedTokenHash = null;
      return;
    }
    const hasSelectedToken = tokens.some((token) => token.token_hash === selectedTokenHash);
    if (!hasSelectedToken) {
      const preferredToken = currentTokenHash
        ? tokens.find((token) => token.token_hash === currentTokenHash)
        : null;
      selectedTokenHash = preferredToken ? preferredToken.token_hash : tokens[0].token_hash;
    }
  }

  function renderTokenDisplay() {
    if (currentToken) {
      tokenValue.textContent = currentToken;
      tokenDisplay.style.display = 'block';
    } else {
      tokenValue.textContent = '';
      tokenInfo.textContent = '';
      tokenDisplay.style.display = 'none';
    }
  }

  function renderTokensList() {
    const hasTokens = tokens.length > 0;
    tokensList.style.display = hasTokens ? 'block' : 'none';
    btnRevokeAll.style.display = hasTokens ? 'inline-flex' : 'none';
    btnRegenerate.style.display = hasTokens ? 'inline-flex' : 'none';
    if (btnRevoke) btnRevoke.style.display = hasTokens ? 'inline-flex' : 'none';

    if (!hasTokens) {
      tokensListContainer.innerHTML = '';
      if (selectedTokenHint) {
        selectedTokenHint.textContent = tr('noActiveTokens', 'No active tokens yet.');
      }
      return;
    }

    syncSelection();
    const selectedToken = getSelectedToken();

    tokensListContainer.innerHTML = tokens.map((token) => {
      const isSelected = token.token_hash === selectedTokenHash;
      const deviceName = escapeHtml(token.device_name || tr('unknownDevice', 'Unknown Device'));
      const platform = escapeHtml(token.platform || tr('unknownPlatform', 'Unknown Platform'));
      const createdAt = escapeHtml(formatDateTime(token.created_at));
      const lastUsed = escapeHtml(formatDateTime(token.last_used_at));
      const expiresAt = escapeHtml(formatDate(token.expires_at));

      return `<button type="button"
        class="token-item${isSelected ? ' selected' : ''}"
        data-token-hash="${escapeHtml(token.token_hash)}"
        style="width:100%;text-align:left;padding:12px 14px;border:0;border-bottom:1px solid var(--border);background:${isSelected ? 'rgba(var(--accent-rgb), 0.16)' : 'transparent'};color:var(--fg);cursor:pointer;transition:background .15s ease, box-shadow .15s ease;color:var(--fg);box-shadow:${isSelected ? 'inset 3px 0 0 var(--accent), inset 0 0 0 1px rgba(var(--accent-rgb), 0.22)' : 'none'};">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:6px;">
            <strong style="font-size:13px;color:var(--fg);">${deviceName}</strong>
            <span style="font-size:11px;color:${isSelected ? 'var(--accent)' : 'var(--fg-secondary)'};">${platform}</span>
          </div>
          <div style="font-size:11px;color:var(--fg-secondary);line-height:1.5;">
            <div>${tr('lastUsed', 'Last used')}: ${lastUsed}</div>
            <div>${tr('expires', 'Expires')}: ${expiresAt}</div>
            <div>${tr('created', 'Created')}: ${createdAt}</div>
          </div>
        </button>`;
    }).join('');

    if (selectedTokenHint) {
      selectedTokenHint.textContent = selectedToken
        ? `${selectedToken.device_name || tr('unknownDevice', 'Unknown Device')} • ${selectedToken.platform || tr('unknownPlatform', 'Unknown Platform')}`
        : tr('selectTokenToManage', 'Select a device to manage its token.');
    }
  }

  function updateActionState() {
    const hasSelection = !!getSelectedToken();
    if (btnRevoke) btnRevoke.disabled = !hasSelection;
  }

  function refreshModalContent() {
    if (currentTokenHash && !tokens.some((token) => token.token_hash === currentTokenHash)) {
      currentToken = null;
      currentTokenHash = null;
    }
    renderTokenDisplay();
    renderTokensList();
    updateActionState();
  }

  async function loadTokens() {
    try {
      const response = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=client_token_list'
      });
      const data = await response.json();
      tokens = data.ok && data.tokens && data.tokens.length > 0 ? data.tokens : [];
      refreshModalContent();
    } catch (err) {
      console.error('Failed to load tokens:', err);
      tokens = [];
      refreshModalContent();
      flashStatus(tr('failedToLoadTokens', 'Failed to load tokens'));
    }
  }

  async function generateToken() {
    const defaultGenerateLabel = `<svg width="18" height="18" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path></svg><span>${tr('generateToken', 'Generate New Token')}</span>`;
    try {
      btnGenerate.disabled = true;
      btnGenerate.innerHTML = `<span>${tr('generating', 'Generating...')}</span>`;
      const response = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=client_token_generate&device_id=web&device_name=Web+Browser&platform=Web'
      });
      const data = await response.json();
      btnGenerate.disabled = false;
      btnGenerate.innerHTML = defaultGenerateLabel;

      if (data.ok && data.token) {
        currentToken = data.token;
        currentTokenHash = data.token_hash || null;
        tokenValue.textContent = data.token;
        const expiresDate = new Date(data.expires_at * 1000).toLocaleDateString();
        tokenInfo.textContent = `${tr('expires', 'Expires')}: ${expiresDate} (${data.expires_in_days} ${tr('days', 'days')})`;
        flashStatus(`✓ ${tr('tokenGenerated', 'Token generated!')} ${tr('copyNow', 'Copy it now.')}`);
        await loadTokens();
        renderTokenDisplay();
      } else {
        flashStatus(`✗ ${tr('failedToGenerate', 'Failed to generate')}: ${data.error || tr('unknownError', 'Unknown error')}`);
      }
    } catch (err) {
      console.error('Failed to generate token:', err);
      btnGenerate.disabled = false;
      btnGenerate.innerHTML = defaultGenerateLabel;
      flashStatus(`✗ ${tr('failedToGenerate', 'Failed to generate token')}`);
    }
  }

  async function revokeSelectedToken() {
    const selectedToken = getSelectedToken();
    if (!selectedToken) {
      flashStatus(tr('selectTokenToManage', 'Select a device to manage its token.'));
      return;
    }

    try {
      if (btnRevoke) btnRevoke.disabled = true;
      const response = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=client_token_revoke&token_hash=${encodeURIComponent(selectedToken.token_hash)}`
      });
      const data = await response.json();
      if (btnRevoke) btnRevoke.disabled = false;

      if (data.ok) {
        if (selectedToken.token_hash === currentTokenHash) {
          currentToken = null;
          currentTokenHash = null;
        }
        selectedTokenHash = null;
        await loadTokens();
        flashStatus(`✓ ${tr('tokenRevoked', 'Token revoked')}`);
      } else {
        flashStatus(`✗ ${tr('failedToRevoke', 'Failed to revoke')}: ${data.error || tr('unknownError', 'Unknown error')}`);
      }
    } catch (err) {
      console.error('Failed to revoke token:', err);
      if (btnRevoke) btnRevoke.disabled = false;
      flashStatus(`✗ ${tr('failedToRevoke', 'Failed to revoke tokens')}`);
    }
  }

  async function revokeAllTokens() {
    const confirmModal = await showConfirmModal(
      tr('revokeAllTokensTitle', 'Revoke all sync tokens'),
      tr('confirmRevokeAll', 'Revoke ALL sync tokens? This will disconnect all desktop clients.')
    );
    if (!confirmModal.confirmed) return false;

    try {
      btnRevokeAll.disabled = true;
      const response = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=client_token_revoke_all'
      });
      const data = await response.json();

      if (data.ok) {
        btnRevokeAll.disabled = false;
        currentToken = null;
        currentTokenHash = null;
        tokens = [];
        selectedTokenHash = null;
        tokenDisplay.style.display = 'none';
        refreshModalContent();
        flashStatus(`✓ ${tr('allTokensRevoked', 'All tokens revoked')}`);
        return true;
      }

      btnRevokeAll.disabled = false;
      flashStatus(`✗ ${tr('failedToRevoke', 'Failed to revoke')}: ${data.error || tr('unknownError', 'Unknown error')}`);
      return false;
    } catch (err) {
      console.error('Failed to revoke tokens:', err);
      btnRevokeAll.disabled = false;
      flashStatus(`✗ ${tr('failedToRevoke', 'Failed to revoke tokens')}`);
      return false;
    }
  }

  async function copyToken() {
    if (!currentToken) {
      flashStatus(tr('noTokenToCopy', 'No token to copy'));
      return false;
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      try {
        await navigator.clipboard.writeText(currentToken);
        flashStatus(`✓ ${tr('tokenCopied', 'Token copied to clipboard')}`);
        return true;
      } catch (_) {
        return fallbackCopyToken();
      }
    }
    return fallbackCopyToken();
  }

  function fallbackCopyToken() {
    const textarea = document.createElement('textarea');
    textarea.value = currentToken;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
      document.execCommand('copy');
      flashStatus(`✓ ${tr('tokenCopied', 'Token copied to clipboard')}`);
      return true;
    } catch (err) {
      flashStatus(tr('failedToCopy', 'Failed to copy token'));
      return false;
    } finally {
      document.body.removeChild(textarea);
    }
  }

  openBtn.addEventListener('click', () => {
    modal.classList.remove('hidden');
    loadTokens();
  });
  closeBtn.addEventListener('click', () => modal.classList.add('hidden'));
  modal.addEventListener('click', (event) => {
    if (event.target === modal) modal.classList.add('hidden');
  });
  btnGenerate.addEventListener('click', generateToken);
  btnRegenerate.addEventListener('click', async () => {
    const revoked = await revokeAllTokens();
    if (revoked) await generateToken();
  });
  if (btnRevoke) btnRevoke.addEventListener('click', revokeSelectedToken);
  btnRevokeAll.addEventListener('click', revokeAllTokens);
  btnCopy.addEventListener('click', async () => {
    const ok = await copyToken();
    if (ok) showSuccessCheck(btnCopy);
  });
  tokensListContainer.addEventListener('click', (event) => {
    const item = event.target.closest('[data-token-hash]');
    if (!item) return;
    selectedTokenHash = item.dataset.tokenHash || null;
    renderTokensList();
    updateActionState();
  });
};
