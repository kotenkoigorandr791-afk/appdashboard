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
  const btnCopyUsername = document.getElementById('btn-copy-username');
  const btnDownloadScript = document.getElementById('btn-download-webdav-script');
  const btnDownloadLinuxScript = document.getElementById('btn-download-linux-webdav-script');
  const btnDownloadWindowsScript = document.getElementById('btn-download-windows-webdav-script');
  const btnCopyLinuxCommand = document.getElementById('btn-copy-webdav-linux-command');
  const tokenDisplay = document.getElementById('sync-token-display');
  const tokenValue = document.getElementById('sync-token-value');
  const usernameValue = document.getElementById('sync-username-value');
  const tokenNameInput = document.getElementById('sync-token-name');
  const scriptHint = document.getElementById('webdav-script-hint');
  const windowsAddressWrap = document.getElementById('webdav-windows-address-wrap');
  const windowsAddressValue = document.getElementById('webdav-windows-address');
  const linuxCommandWrap = document.getElementById('webdav-linux-command-wrap');
  const linuxCommandValue = document.getElementById('webdav-linux-command');
  const tokenInfo = document.getElementById('token-info');
  const tokensList = document.getElementById('sync-tokens-list');
  const tokensListContainer = document.getElementById('tokens-list-container');
  const selectedTokenHint = document.getElementById('selected-token-hint');

  if (!modal || !openBtn) return;

  let currentToken = null;
  let currentUsername = null;
  let currentWebdavUrl = null;
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
      if (usernameValue) usernameValue.textContent = currentUsername || '';
      if (linuxCommandValue) linuxCommandValue.textContent = linuxConnectCommand();
      if (windowsAddressValue) windowsAddressValue.textContent = windowsExplorerAddress();
      if (btnDownloadScript) btnDownloadScript.disabled = false;
      updatePlatformUi();
      tokenDisplay.style.display = 'block';
    } else {
      tokenValue.textContent = '';
      if (usernameValue) usernameValue.textContent = '';
      if (linuxCommandValue) linuxCommandValue.textContent = '';
      if (windowsAddressValue) windowsAddressValue.textContent = '';
      if (linuxCommandWrap) linuxCommandWrap.style.display = 'none';
      if (windowsAddressWrap) windowsAddressWrap.style.display = 'none';
      if (btnDownloadLinuxScript) btnDownloadLinuxScript.style.display = 'none';
      if (btnDownloadWindowsScript) btnDownloadWindowsScript.style.display = 'none';
      if (btnDownloadScript) btnDownloadScript.disabled = true;
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
      currentUsername = null;
      currentWebdavUrl = null;
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
    const tokenName = (tokenNameInput?.value || '').trim();
    if (!tokenName) {
      flashStatus(`✗ ${tr('syncTokenNameRequired', 'Enter a connection name')}`);
      tokenNameInput?.focus();
      return;
    }
    try {
      btnGenerate.disabled = true;
      btnGenerate.innerHTML = `<span>${tr('generating', 'Generating...')}</span>`;
      const body = new URLSearchParams({
        action: 'client_token_generate',
        device_id: `webdav-${Date.now()}`,
        device_name: tokenName,
        platform: 'WebDAV'
      });
      const response = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
      });
      const data = await response.json();
      btnGenerate.disabled = false;
      btnGenerate.innerHTML = defaultGenerateLabel;

      if (data.ok && data.token) {
        currentToken = data.token;
        currentUsername = data.username || '';
        currentWebdavUrl = data.webdav_url || window.location.origin.replace(/^https?:\/\//, (s) => s === 'https://' ? 'webdavs://' : 'webdav://') + '/webdav/';
        currentTokenHash = data.token_hash || null;
        tokenValue.textContent = data.token;
        if (usernameValue) usernameValue.textContent = currentUsername;
        const expiresDate = new Date(data.expires_at * 1000).toLocaleDateString();
        tokenInfo.textContent = `${tr('expires', 'Expires')}: ${expiresDate} (${data.expires_in_days} ${tr('days', 'days')}) | WebDAV: ${currentWebdavUrl}`;
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
          currentUsername = null;
          currentWebdavUrl = null;
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
      tr('revokeAllTokensTitle', 'Revoke all WebDAV tokens'),
      tr('confirmRevokeAll', 'Revoke ALL WebDAV tokens? This will disconnect all WebDAV connections.')
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
        currentUsername = null;
        currentWebdavUrl = null;
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
    return copyValue(currentToken, tr('noTokenToCopy', 'No token to copy'));
  }

  async function copyUsername() {
    return copyValue(currentUsername, tr('noUsernameToCopy', 'No username to copy'));
  }

  async function copyValue(value, emptyMessage) {
    if (!value) {
      flashStatus(emptyMessage);
      return false;
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      try {
        await navigator.clipboard.writeText(value);
        flashStatus(`✓ ${tr('tokenCopied', 'Token copied to clipboard')}`);
        return true;
      } catch (_) {
        return fallbackCopyValue(value);
      }
    }
    return fallbackCopyValue(value);
  }

  function fallbackCopyValue(value) {
    const textarea = document.createElement('textarea');
    textarea.value = value;
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

  function shellQuote(value) {
    return `'${String(value).replace(/'/g, `'\\''`)}'`;
  }

  function psQuote(value) {
    return `'${String(value).replace(/'/g, "''")}'`;
  }

  function downloadTextFile(filename, content, type = 'text/plain') {
    const blob = new Blob([content], { type });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  function downloadWindowsWebdavScript() {
    downloadTextFile('appdashboard-webdav-connect.ps1', windowsWebdavScript(), 'text/plain');
  }

  function detectedDesktopOS() {
    const platform = `${navigator.userAgentData?.platform || ''} ${navigator.platform || ''} ${navigator.userAgent || ''}`.toLowerCase();
    if (platform.includes('win')) return 'windows';
    return 'unix';
  }

  function updatePlatformUi() {
    const os = detectedDesktopOS();
    const isWindows = os === 'windows';
    if (scriptHint) {
      scriptHint.textContent = isWindows
        ? tr('webdavWindowsHint', 'Windows: in File Explorer, open This PC, choose Map network drive, then use this folder address.')
        : tr('webdavScriptHint', 'Download the script, run it, and enter your sudo password if the system asks to install davfs2 or mount the folder.');
    }
    if (linuxCommandWrap) linuxCommandWrap.style.display = !isWindows && currentToken ? 'block' : 'none';
    if (windowsAddressWrap) windowsAddressWrap.style.display = isWindows && currentToken ? 'block' : 'none';
    if (btnDownloadLinuxScript) btnDownloadLinuxScript.style.display = 'none';
    if (btnDownloadWindowsScript) btnDownloadWindowsScript.style.display = 'none';
    if (btnDownloadScript) {
      btnDownloadScript.querySelector('span').textContent = isWindows
        ? tr('downloadConnectScriptWindows', 'Download Windows connect script')
        : tr('downloadConnectScriptLinux', 'Download Linux connect script');
    }
  }

  function webdavConfig() {
    return {
      url: currentWebdavUrl || window.location.origin.replace(/^https?:\/\//, (s) => s === 'https://' ? 'webdavs://' : 'webdav://') + '/webdav/',
      username: currentUsername || '',
      token: currentToken || '',
      iconUrl: new URL('project/views/icon.png', window.location.origin + '/').href,
      iconIcoUrl: new URL('project/views/icon.ico', window.location.origin + '/').href
    };
  }

  function linuxConnectCommand() {
    return 'cd ~/Downloads && chmod +x appdashboard-webdav-connect.sh && ./appdashboard-webdav-connect.sh';
  }

  function windowsExplorerAddress() {
    const cfg = webdavConfig();
    const url = cfg.url.replace(/^webdavs:\/\//, 'https://').replace(/^webdav:\/\//, 'http://');
    const parsed = new URL(url);
    const path = parsed.pathname.replace(/\/$/, '').replace(/\//g, '\\');
    return parsed.protocol === 'https:'
      ? `\\\\${parsed.host}@SSL\\DavWWWRoot${path}`
      : `\\\\${parsed.host}\\DavWWWRoot${path}`;
  }

  function linuxWebdavScript() {
      const cfg = webdavConfig();
      const davUrl = cfg.url.replace(/^https:\/\//, 'webdavs://').replace(/^http:\/\//, 'webdav://');
      const httpUrl = cfg.url.replace(/^webdavs:\/\//, 'https://').replace(/^webdav:\/\//, 'http://');
      return `#!/usr/bin/env sh
set -eu
WEBDAV_URL=${shellQuote(httpUrl)}
WEBDAV_BROWSER_URL=${shellQuote(davUrl)}
WEBDAV_USER=${shellQuote(cfg.username)}
WEBDAV_TOKEN=${shellQuote(cfg.token)}
ICON_URL=${shellQuote(cfg.iconUrl)}
TARGET_USER="\${SUDO_USER:-\$(id -un)}"
TARGET_HOME="\$(getent passwd "$TARGET_USER" | cut -d: -f6)"
TARGET_UID="\$(id -u "$TARGET_USER")"
TARGET_GID="\$(id -g "$TARGET_USER")"
[ -n "$TARGET_HOME" ] || TARGET_HOME="$HOME"
if [ "$(id -u)" -eq 0 ]; then SUDO_CMD=""; else SUDO_CMD="sudo"; fi
run_as_user() {
  if [ "$(id -u)" -eq "$TARGET_UID" ]; then "$@"; else sudo -u "$TARGET_USER" "$@"; fi
}
MOUNT_DIR="$TARGET_HOME/AppDashboard Cloud"
ICON_DIR="$TARGET_HOME/.local/share/icons"
ICON_FILE="$ICON_DIR/appdashboard-cloud.png"
DESKTOP_DIR="$TARGET_HOME/.local/share/applications"
DESKTOP_FILE="$DESKTOP_DIR/appdashboard-cloud-webdav.desktop"
USER_DESKTOP_DIR="$TARGET_HOME/Desktop"
USER_DESKTOP_FILE="$USER_DESKTOP_DIR/AppDashboard Cloud.desktop"
RCLONE_CONFIG_DIR="$TARGET_HOME/.config/rclone"
RCLONE_CONFIG_FILE="$RCLONE_CONFIG_DIR/appdashboard-cloud.conf"
RCLONE_REMOTE="appdashboard-cloud"
RCLONE_LOG_FILE="$TARGET_HOME/.cache/appdashboard-rclone.log"
LOG_FILE="\$(mktemp "\${TMPDIR:-/tmp}/appdashboard-webdav-mount.XXXXXX")"
mount_is_healthy() {
  findmnt -rn -M "$MOUNT_DIR" >/dev/null 2>&1 && run_as_user ls "$MOUNT_DIR" >/dev/null 2>&1
}
write_desktop_link() {
  [ -d "$USER_DESKTOP_DIR" ] || return 0
  cat > "$USER_DESKTOP_FILE" <<EOF
[Desktop Entry]
Type=Link
Name=AppDashboard Cloud
URL=file://$MOUNT_DIR
Icon=$ICON_FILE
EOF
  chmod +x "$USER_DESKTOP_FILE" 2>/dev/null || true
  chown "$TARGET_UID:$TARGET_GID" "$USER_DESKTOP_FILE" 2>/dev/null || true
}
install_package() {
  pkg="$1"
  if command -v apt-get >/dev/null 2>&1; then ($SUDO_CMD apt-get update && $SUDO_CMD apt-get install -y "$pkg") || true
  elif command -v dnf >/dev/null 2>&1; then $SUDO_CMD dnf install -y "$pkg" || true
  elif command -v yum >/dev/null 2>&1; then $SUDO_CMD yum install -y "$pkg" || true
  elif command -v zypper >/dev/null 2>&1; then $SUDO_CMD zypper --non-interactive install "$pkg" || true
  elif command -v pacman >/dev/null 2>&1; then $SUDO_CMD pacman -Sy --noconfirm "$pkg" || true
  fi
}
unmount_mount_dir() {
  n=0
  while findmnt -rn -M "$MOUNT_DIR" >/dev/null 2>&1; do
    fusermount3 -u "$MOUNT_DIR" 2>/dev/null || fusermount -u "$MOUNT_DIR" 2>/dev/null || $SUDO_CMD umount -l "$MOUNT_DIR" 2>/dev/null || true
    n=$((n + 1))
    [ "$n" -ge 8 ] && break
    sleep 1
  done
}
choose_action() {
  case "\${1:-}" in
    --mount|mount) echo "mount"; return 0 ;;
    --unmount|unmount|--umount|umount) echo "unmount"; return 0 ;;
  esac
  if command -v kdialog >/dev/null 2>&1 && [ -n "\${DISPLAY:-}" ]; then
    run_as_user kdialog --title "AppDashboard Cloud" --menu "Choose an action for the WebDAV folder:" mount "Mount folder" unmount "Unmount folder" 2>/dev/null || echo "cancel"
    return 0
  fi
  if command -v zenity >/dev/null 2>&1 && [ -n "\${DISPLAY:-}" ]; then
    run_as_user zenity --list --title="AppDashboard Cloud" --text="Choose an action for the WebDAV folder:" --column="Action" --column="Description" mount "Mount folder" unmount "Unmount folder" 2>/dev/null || echo "cancel"
    return 0
  fi
  printf '%s\\n' "AppDashboard Cloud"
  printf '%s\\n' "1) Mount folder"
  printf '%s\\n' "2) Unmount folder"
  printf '%s' "Choose an action [1/2]: "
  read answer
  case "$answer" in
    2) echo "unmount" ;;
    *) echo "mount" ;;
  esac
}
ACTION="$(choose_action "\${1:-}")"
if [ "$ACTION" = "cancel" ]; then
  echo "Action cancelled."
  exit 0
fi
if [ "$ACTION" = "unmount" ]; then
  unmount_mount_dir
  echo "Folder unmounted: $MOUNT_DIR"
  exit 0
fi
if mount_is_healthy; then
  write_desktop_link
  echo "Folder is already mounted: $MOUNT_DIR"
  exit 0
fi
if ! run_as_user ls -ld "$MOUNT_DIR" >/dev/null 2>&1; then
  unmount_mount_dir
fi
mkdir -p "$MOUNT_DIR" "$ICON_DIR" "$DESKTOP_DIR" "$RCLONE_CONFIG_DIR" "$TARGET_HOME/.cache"
if ! findmnt -rn -M "$MOUNT_DIR" >/dev/null 2>&1; then
  rm -f "$MOUNT_DIR/.directory"
fi
if command -v curl >/dev/null 2>&1; then curl -fsSL "$ICON_URL" -o "$ICON_FILE" || true; fi
if command -v wget >/dev/null 2>&1 && [ ! -s "$ICON_FILE" ]; then wget -qO "$ICON_FILE" "$ICON_URL" || true; fi
if [ -s "$ICON_FILE" ]; then chown "$TARGET_UID:$TARGET_GID" "$ICON_FILE" 2>/dev/null || true; fi
cat > "$DESKTOP_FILE" <<EOF
[Desktop Entry]
Type=Link
Name=AppDashboard Cloud
URL=$WEBDAV_BROWSER_URL
Icon=$ICON_FILE
EOF
chmod +x "$DESKTOP_FILE" 2>/dev/null || true
chown -R "$TARGET_UID:$TARGET_GID" "$TARGET_HOME/.local" "$MOUNT_DIR" 2>/dev/null || true
if mountpoint -q "$MOUNT_DIR" && ! mount_is_healthy; then
  $SUDO_CMD umount -l "$MOUNT_DIR" 2>/dev/null || true
fi
write_desktop_link
if command -v gio >/dev/null 2>&1; then
  run_as_user gio set -t string "$MOUNT_DIR" metadata::custom-icon "file://$ICON_FILE" 2>/dev/null || true
fi
if command -v curl >/dev/null 2>&1; then
  HTTP_CODE="$(curl -k -sS -o /dev/null -w "%{http_code}" -u "$WEBDAV_USER:$WEBDAV_TOKEN" -X PROPFIND -H "Depth: 0" "$WEBDAV_URL" 2>/dev/null || true)"
  case "$HTTP_CODE" in
    200|207|301|302) ;;
    401|403)
      echo "WebDAV authentication failed. Generate a new token and download a fresh script."
      exit 1
      ;;
    500|502|503|504)
      echo "WebDAV server returned HTTP $HTTP_CODE. Try again after the server update is applied."
      exit 1
      ;;
  esac
fi
if ! command -v mount.davfs >/dev/null 2>&1; then
  install_package davfs2
fi
if command -v mount.davfs >/dev/null 2>&1; then
  mkdir -p "$TARGET_HOME/.davfs2"
  touch "$TARGET_HOME/.davfs2/secrets"
  chmod 600 "$TARGET_HOME/.davfs2/secrets"
  chown -R "$TARGET_UID:$TARGET_GID" "$TARGET_HOME/.davfs2" 2>/dev/null || true
  grep -vF "$WEBDAV_URL" "$TARGET_HOME/.davfs2/secrets" > "$TARGET_HOME/.davfs2/secrets.tmp" || true
  printf '%s %s %s\\n' "$WEBDAV_URL" "$WEBDAV_USER" "$WEBDAV_TOKEN" >> "$TARGET_HOME/.davfs2/secrets.tmp"
  mv "$TARGET_HOME/.davfs2/secrets.tmp" "$TARGET_HOME/.davfs2/secrets"
  chown "$TARGET_UID:$TARGET_GID" "$TARGET_HOME/.davfs2/secrets" 2>/dev/null || true
  if [ -z "$SUDO_CMD" ] || command -v sudo >/dev/null 2>&1; then
    ROOT_SECRETS_TMP="$(mktemp "\${TMPDIR:-/tmp}/appdashboard-davfs2-secrets.XXXXXX")"
    $SUDO_CMD sh -c "touch /etc/davfs2/secrets && chmod 600 /etc/davfs2/secrets"
    $SUDO_CMD grep -vF "$WEBDAV_URL" /etc/davfs2/secrets > "$ROOT_SECRETS_TMP" || true
    printf '%s %s %s\\n' "$WEBDAV_URL" "$WEBDAV_USER" "$WEBDAV_TOKEN" >> "$ROOT_SECRETS_TMP"
    $SUDO_CMD mv "$ROOT_SECRETS_TMP" /etc/davfs2/secrets
    $SUDO_CMD chown root:root /etc/davfs2/secrets
    $SUDO_CMD chmod 600 /etc/davfs2/secrets
  fi
  if mount_is_healthy; then
    echo "Already mounted: $MOUNT_DIR"
    exit 0
  elif $SUDO_CMD mount -t davfs "$WEBDAV_URL" "$MOUNT_DIR" -o "uid=$TARGET_UID,gid=$TARGET_GID,rw,file_mode=664,dir_mode=775" 2>"$LOG_FILE"; then
    if command -v gio >/dev/null 2>&1; then
      run_as_user gio set -t string "$MOUNT_DIR" metadata::custom-icon "file://$ICON_FILE" 2>/dev/null || true
    fi
    echo "Mounted: $MOUNT_DIR"
    exit 0
  else
    cat "$LOG_FILE" 2>/dev/null || true
  fi
fi
if ! command -v rclone >/dev/null 2>&1; then
  install_package rclone
fi
if command -v rclone >/dev/null 2>&1; then
  OBFUSCATED_TOKEN="$(run_as_user rclone obscure "$WEBDAV_TOKEN")"
  cat > "$RCLONE_CONFIG_FILE" <<EOF
[$RCLONE_REMOTE]
type = webdav
url = $WEBDAV_URL
vendor = other
user = $WEBDAV_USER
pass = $OBFUSCATED_TOKEN
EOF
  chown -R "$TARGET_UID:$TARGET_GID" "$RCLONE_CONFIG_DIR" "$TARGET_HOME/.cache" 2>/dev/null || true
  chmod 600 "$RCLONE_CONFIG_FILE" 2>/dev/null || true
  if mount_is_healthy; then
    echo "Already mounted: $MOUNT_DIR"
    exit 0
  fi
  unmount_mount_dir
  run_as_user setsid nohup rclone mount "$RCLONE_REMOTE:" "$MOUNT_DIR" --config "$RCLONE_CONFIG_FILE" --vfs-cache-mode full --dir-cache-time 1s --log-file "$RCLONE_LOG_FILE" --log-level INFO >/dev/null 2>&1 &
  n=0
  while [ "$n" -lt 45 ]; do
    if mount_is_healthy; then
      if command -v gio >/dev/null 2>&1; then
        run_as_user gio set -t string "$MOUNT_DIR" metadata::custom-icon "file://$ICON_FILE" 2>/dev/null || true
      fi
      echo "Mounted: $MOUNT_DIR"
      exit 0
    fi
    n=$((n + 1))
    sleep 1
  done
  cat "$RCLONE_LOG_FILE" 2>/dev/null || true
  unmount_mount_dir
fi
echo "Opening WebDAV location. For a permanent mounted folder install davfs2, then rerun this script."
if command -v dolphin >/dev/null 2>&1; then run_as_user nohup dolphin "$WEBDAV_BROWSER_URL" >/dev/null 2>&1 &
elif command -v xdg-open >/dev/null 2>&1; then run_as_user xdg-open "$DESKTOP_FILE" >/dev/null 2>&1 || run_as_user xdg-open "$WEBDAV_BROWSER_URL" >/dev/null 2>&1 || true
elif command -v open >/dev/null 2>&1; then run_as_user open "$WEBDAV_BROWSER_URL"
else echo "$WEBDAV_BROWSER_URL"; fi
`;
  }

  function windowsWebdavScript() {
    const cfg = webdavConfig();
    return `$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$WebDavUrl = ${psQuote(cfg.url)}
$Username = ${psQuote(cfg.username)}
$Token = ${psQuote(cfg.token)}
$IconIcoUrl = ${psQuote(cfg.iconIcoUrl)}
$Name = 'AppDashboard Cloud'
$PreferredLetter = 'Z'
[Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12
function Get-HttpUrl([string]$Url) {
  $normalized = $Url -replace '^webdavs://', 'https://' -replace '^webdav://', 'http://'
  return $normalized.TrimEnd('/') + '/'
}
function Get-DavUnc([string]$Url) {
  $parsed = [Uri](Get-HttpUrl $Url)
  $hostPart = $parsed.Host
  if ($parsed.Scheme -eq 'https') { $hostPart = $hostPart + '@SSL' }
  if (-not $parsed.IsDefaultPort) { $hostPart = $hostPart + '@' + $parsed.Port }
  return '\\\\' + $hostPart + '\\DavWWWRoot' + $parsed.AbsolutePath.TrimEnd('/').Replace('/', '\\')
}
function Invoke-Net([string[]]$NetArgs) {
  $previous = $ErrorActionPreference
  $ErrorActionPreference = 'Continue'
  $lines = & net.exe @NetArgs 2>&1 | ForEach-Object {
    if ($_ -is [Management.Automation.ErrorRecord]) { $_.Exception.Message.Trim() } else { $_.ToString().Trim() }
  } | Where-Object { $_ -ne '' }
  $code = $LASTEXITCODE
  $ErrorActionPreference = $previous
  return [pscustomobject]@{ ExitCode = $code; Output = ($lines -join ' ') }
}
function Invoke-Elevated([string]$Command, [string]$FailureMessage) {
  try {
    Start-Process -FilePath 'powershell.exe' -Verb RunAs -Wait -WindowStyle Hidden -ArgumentList '-NoProfile', '-Command', $Command
  } catch {
    throw $FailureMessage
  }
}
function Enable-WebClient {
  $service = Get-Service -Name WebClient -ErrorAction SilentlyContinue
  if (-not $service) { throw 'The WebClient (WebDAV) service is not available on this system.' }
  if ($service.Status -ne 'Running') {
    try {
      Start-Service -Name WebClient -ErrorAction Stop
    } catch {
      Invoke-Elevated 'Set-Service -Name WebClient -StartupType Automatic; Start-Service -Name WebClient' 'The WebClient service must be running. Start it in services.msc and rerun this script.'
    }
  }
  for ($i = 0; $i -lt 20; $i++) {
    if ((Get-Service -Name WebClient).Status -eq 'Running') { return }
    Start-Sleep -Milliseconds 500
  }
  throw 'The WebClient service did not start.'
}
function Enable-BasicAuthOverHttp {
  $key = 'HKLM:\\SYSTEM\\CurrentControlSet\\Services\\WebClient\\Parameters'
  $current = (Get-ItemProperty -Path $key -Name BasicAuthLevel -ErrorAction SilentlyContinue).BasicAuthLevel
  if ($current -eq 2) { return }
  Invoke-Elevated ('New-Item -Path ''' + $key + ''' -Force | Out-Null; Set-ItemProperty -Path ''' + $key + ''' -Name BasicAuthLevel -Value 2 -Type DWord; Restart-Service WebClient') 'Plain HTTP WebDAV needs administrator rights. Approve the elevation prompt or use an HTTPS address.'
}
function Test-WebDavAccess([string]$Url) {
  try {
    $request = [Net.WebRequest]::Create($Url)
    $request.Method = 'PROPFIND'
    $request.Timeout = 30000
    $request.AllowAutoRedirect = $false
    $request.Headers['Depth'] = '0'
    $request.Headers['Authorization'] = 'Basic ' + [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($Username + ':' + $Token))
    $request.ContentLength = 0
    $request.GetRequestStream().Close()
    $response = $request.GetResponse()
    $code = [int]$response.StatusCode
    $response.Close()
    return $code
  } catch [Net.WebException] {
    if ($_.Exception.Response) {
      $failed = $_.Exception.Response
      $code = [int]$failed.StatusCode
      $failed.Close()
      return $code
    }
    throw ('Cannot reach ' + $Url + ': ' + $_.Exception.Message)
  }
}
function Get-DriveLetter([string]$Preferred) {
  $used = @(Get-PSDrive -PSProvider FileSystem -ErrorAction SilentlyContinue | ForEach-Object { $_.Name.ToUpper() })
  foreach ($candidate in @($Preferred) + @('Y', 'X', 'W', 'V', 'U', 'T', 'S', 'R', 'Q', 'P', 'N', 'M')) {
    if ($used -notcontains $candidate.ToUpper()) { return $candidate.ToUpper() }
  }
  throw 'No free drive letter is available.'
}
function Disconnect-Drive([string]$Drive) {
  $deleteArgs = @('use', $Drive, '/delete', '/y')
  Invoke-Net $deleteArgs | Out-Null
}
function Connect-WebDavDrive([string]$Drive, [string]$Unc) {
  $userArgument = '/user:' + $Username
  $connectArgs = @('use', $Drive, $Unc, $Token, $userArgument, '/persistent:yes')
  for ($attempt = 1; $attempt -le 3; $attempt++) {
    $result = Invoke-Net $connectArgs
    Start-Sleep -Milliseconds 500
    if ($result.ExitCode -eq 0 -and (Test-Path ($Drive + '\\'))) { return }
    Disconnect-Drive $Drive
    if ($attempt -eq 3) { throw ('Windows could not map ' + $Unc + ' (net use exit code ' + $result.ExitCode + '). ' + $result.Output) }
    Start-Sleep -Seconds 3
  }
}
function New-DesktopShortcut([string]$Drive) {
  $iconDir = Join-Path $env:APPDATA 'AppDashboard'
  New-Item -ItemType Directory -Force -Path $iconDir | Out-Null
  $iconIco = Join-Path $iconDir 'cloud.ico'
  try { Invoke-WebRequest -Uri $IconIcoUrl -OutFile $iconIco -UseBasicParsing -TimeoutSec 20 } catch {}
  $shell = New-Object -ComObject WScript.Shell
  $link = $shell.CreateShortcut((Join-Path ([Environment]::GetFolderPath('Desktop')) ($Name + '.lnk')))
  $link.TargetPath = $Drive + '\\'
  $link.WorkingDirectory = $Drive + '\\'
  if (Test-Path $iconIco) { $link.IconLocation = $iconIco + ',0' }
  $link.Save()
}
try {
  $httpUrl = Get-HttpUrl $WebDavUrl
  $unc = Get-DavUnc $WebDavUrl
  Enable-WebClient
  if (([Uri]$httpUrl).Scheme -eq 'http') { Enable-BasicAuthOverHttp }
  $status = Test-WebDavAccess $httpUrl
  if ($status -eq 401 -or $status -eq 403) { throw 'The cloud rejected the token. Generate a new token and download the script again.' }
  if ($status -ne 200 -and $status -ne 207) { throw ('The cloud returned HTTP ' + $status + ' for ' + $httpUrl + '.') }
  Disconnect-Drive ($PreferredLetter + ':')
  $drive = (Get-DriveLetter $PreferredLetter) + ':'
  Connect-WebDavDrive $drive $unc
  New-DesktopShortcut $drive
  Write-Host ('Connected ' + $Name + ' as ' + $drive)
} catch {
  Write-Host ('Connection failed: ' + $_.Exception.Message) -ForegroundColor Red
  exit 1
}
`;
  }

  function downloadDetectedScript() {
    if (detectedDesktopOS() === 'windows') {
      downloadWindowsWebdavScript();
      return;
    }
    downloadTextFile('appdashboard-webdav-connect.sh', linuxWebdavScript(), 'text/x-shellscript');
  }

  openBtn.addEventListener('click', () => {
    modal.classList.remove('hidden');
    updatePlatformUi();
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
  if (btnCopyUsername) {
    btnCopyUsername.addEventListener('click', async () => {
      const ok = await copyUsername();
      if (ok) showSuccessCheck(btnCopyUsername);
    });
  }
  if (btnCopyLinuxCommand) {
    btnCopyLinuxCommand.addEventListener('click', async () => {
      const ok = await copyValue(linuxConnectCommand(), tr('noCommandToCopy', 'No command to copy'));
      if (ok) showSuccessCheck(btnCopyLinuxCommand);
    });
  }
  if (btnDownloadLinuxScript) {
    btnDownloadLinuxScript.addEventListener('click', () => {
      if (!currentToken || !currentUsername) {
        flashStatus(tr('noTokenToCopy', 'No token to copy'));
        return;
      }
      downloadTextFile('appdashboard-webdav-connect.sh', linuxWebdavScript(), 'text/x-shellscript');
    });
  }
  if (btnDownloadScript) {
    btnDownloadScript.addEventListener('click', () => {
      if (!currentToken || !currentUsername) {
        flashStatus(tr('noTokenToCopy', 'No token to copy'));
        return;
      }
      downloadDetectedScript();
    });
  }
  if (btnDownloadWindowsScript) {
    btnDownloadWindowsScript.addEventListener('click', () => {
      if (!currentToken || !currentUsername) {
        flashStatus(tr('noTokenToCopy', 'No token to copy'));
        return;
      }
      downloadWindowsWebdavScript();
    });
  }
  tokensListContainer.addEventListener('click', (event) => {
    const item = event.target.closest('[data-token-hash]');
    if (!item) return;
    selectedTokenHash = item.dataset.tokenHash || null;
    renderTokensList();
    updateActionState();
  });
};
