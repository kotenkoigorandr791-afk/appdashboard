#!/bin/sh
set -e
# Ensure WebSSH deps, SSH key, default profile, then supervisord can autostart ssh-ws.
APP_ROOT="${1:-/var/www/AppDashboard}"
DATA_DIR="${APPDATA_DATA_DIR:-/opt/AppDashboard/data}"
SSH_DIR="$DATA_DIR/.ssh"
mkdir -p "$DATA_DIR/ssh_ws_tokens" "$SSH_DIR"
chown -R www-data:www-data "$DATA_DIR/ssh_ws_tokens" "$SSH_DIR" 2>/dev/null || true
find "$DATA_DIR/ssh_ws_tokens" -type d -exec chmod 0700 {} \; 2>/dev/null || true
find "$SSH_DIR" -type d -exec chmod 0700 {} \; 2>/dev/null || true

if [ -f "$APP_ROOT/webssh/composer.json" ]; then
  if [ ! -f "$APP_ROOT/webssh/vendor/autoload.php" ] \
    || [ ! -d "$APP_ROOT/webssh/vendor/cboden/ratchet" ] \
    || [ ! -d "$APP_ROOT/webssh/vendor/phpseclib/phpseclib" ]; then
    mkdir -p "$APP_ROOT/webssh/vendor"
    chown -R www-data:www-data "$APP_ROOT/webssh/vendor" 2>/dev/null || true
    su -s /bin/bash www-data -c "cd '$APP_ROOT/webssh' && composer install --no-interaction --no-progress --no-dev --prefer-dist --optimize-autoloader" \
      || composer install --no-interaction --no-progress --no-dev --prefer-dist --optimize-autoloader -d "$APP_ROOT/webssh"
  fi
fi

if [ ! -f "$APP_ROOT/webssh/server.php" ]; then
  echo "webssh/server.php missing" >&2
  exit 1
fi
if [ ! -f "$APP_ROOT/webssh/vendor/autoload.php" ]; then
  echo "webssh vendor/autoload.php missing" >&2
  exit 1
fi

# Auto-create container SSH key used by Admin SSH Terminal.
KEY_PATH=""
for name in id_ed25519 id_rsa id_ecdsa; do
  if [ -f "$SSH_DIR/$name" ]; then
    KEY_PATH="$SSH_DIR/$name"
    break
  fi
done
if [ -z "$KEY_PATH" ]; then
  KEY_PATH="$SSH_DIR/id_ed25519"
  if command -v ssh-keygen >/dev/null 2>&1; then
    ssh-keygen -t ed25519 -N "" -f "$KEY_PATH" -C "appdashboard-ssh-terminal" >/dev/null 2>&1 \
      || ssh-keygen -t rsa -b 4096 -N "" -f "$KEY_PATH" -C "appdashboard-ssh-terminal" >/dev/null 2>&1 || true
  fi
fi
if [ -n "$KEY_PATH" ] && [ -f "$KEY_PATH" ]; then
  chown www-data:www-data "$KEY_PATH" "$KEY_PATH.pub" 2>/dev/null || true
  chmod 0600 "$KEY_PATH" 2>/dev/null || true
  chmod 0644 "$KEY_PATH.pub" 2>/dev/null || true
  echo "ssh key ready: $KEY_PATH"
  if [ -f "$KEY_PATH.pub" ]; then
    echo "authorize this pubkey on the SSH target:"
    cat "$KEY_PATH.pub"
  fi
fi

# Auto-create dedicated host key for connecting to host.docker.internal
HOST_KEY_PATH="$SSH_DIR/id_ed25519_host"
if [ ! -f "$HOST_KEY_PATH" ]; then
  if command -v ssh-keygen >/dev/null 2>&1; then
    ssh-keygen -t ed25519 -N "" -f "$HOST_KEY_PATH" -C "appdashboard-ssh-host" >/dev/null 2>&1 || true
  fi
fi
if [ -f "$HOST_KEY_PATH" ]; then
  chown www-data:www-data "$HOST_KEY_PATH" "$HOST_KEY_PATH.pub" 2>/dev/null || true
  chmod 0600 "$HOST_KEY_PATH" 2>/dev/null || true
  chmod 0644 "$HOST_KEY_PATH.pub" 2>/dev/null || true
fi

# Ensure known_hosts has host.docker.internal for passwordless connections
KNOWN_HOSTS="$SSH_DIR/known_hosts"
if [ ! -f "$KNOWN_HOSTS" ]; then
  touch "$KNOWN_HOSTS"
fi
if ! grep -q "host.docker.internal" "$KNOWN_HOSTS" 2>/dev/null; then
  ssh-keyscan -t ed25519,rsa,ecdsa host.docker.internal >> "$KNOWN_HOSTS" 2>/dev/null || true
fi
chown www-data:www-data "$KNOWN_HOSTS" 2>/dev/null || true
chmod 0644 "$KNOWN_HOSTS" 2>/dev/null || true

echo "SSH WebSocket setup complete."