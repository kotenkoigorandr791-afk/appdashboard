#!/bin/sh
set -eu
ROOT="${1:-/opt/docker/appdashboard/code}"
export APP_UPSTREAM="${APP_UPSTREAM:-127.0.0.1:8080}"
START_FRONT_DOOR="${START_FRONT_DOOR:-1}"
AVAILABLE="/etc/nginx/sites-available/appdashboard-front-door.conf"
ENABLED="/etc/nginx/sites-enabled/appdashboard-front-door.conf"
DISABLED="/etc/nginx/conf.d/public-app-proxies.conf"

# This nginx runs on the host, outside the compose network, so it cannot resolve
# docker service names (gitea, immich-server, dawarich, glitchtip-web).
# Point the front-door upstreams at host-published loopback ports.
# Dedicated FRONT_DOOR vars take precedence, and the *_INTERNAL_URL vars are reset
# unconditionally: `sudo -E` forwards them from .env, where a docker service name
# would break the host nginx.
export GITEA_FRONT_DOOR_UPSTREAM="${GITEA_FRONT_DOOR_UPSTREAM:-http://127.0.0.1:${GITEA_HTTP_PORT:-3001}}"
export IMMICH_FRONT_DOOR_UPSTREAM="${IMMICH_FRONT_DOOR_UPSTREAM:-http://127.0.0.1:${IMMICH_PORT:-2283}}"
export DAWARICH_FRONT_DOOR_UPSTREAM="${DAWARICH_FRONT_DOOR_UPSTREAM:-http://127.0.0.1:${DAWARICH_PORT:-3002}}"
export SENTRY_FRONT_DOOR_UPSTREAM="${SENTRY_FRONT_DOOR_UPSTREAM:-http://127.0.0.1:${SENTRY_PORT:-8000}}"
export OMNIROUTE_UPSTREAM="${OMNIROUTE_UPSTREAM:-http://127.0.0.1:${OMNIROUTE_PORT:-20128}}"
export GITEA_INTERNAL_URL="$GITEA_FRONT_DOOR_UPSTREAM"
export IMMICH_INTERNAL_URL="$IMMICH_FRONT_DOOR_UPSTREAM"
export DAWARICH_INTERNAL_URL="$DAWARICH_FRONT_DOOR_UPSTREAM"
export SENTRY_INTERNAL_URL="$SENTRY_FRONT_DOOR_UPSTREAM"

TMP="$(mktemp)"
python3 "$ROOT/deploy/scripts/render-nginx-conf.sh" "$ROOT/deploy/nginx/front-door.conf.template" "$TMP"
install -d -m 0755 /opt/docker/appdashboard/nginx /etc/nginx/sites-available /etc/nginx/sites-enabled /etc/nginx/sites-disabled
if [ -f "$DISABLED" ]; then
  mv "$DISABLED" "$DISABLED.disabled-$(date +%Y%m%d%H%M%S)"
fi
for old_site in /etc/nginx/sites-enabled/appdashboard.conf /etc/nginx/sites-enabled/default; do
  if [ -e "$old_site" ] || [ -L "$old_site" ]; then
    mv "$old_site" "/etc/nginx/sites-disabled/$(basename "$old_site").disabled-$(date +%Y%m%d%H%M%S)"
  fi
done
if [ ! -f /opt/docker/appdashboard/nginx/cloudflare-realip.conf ]; then
  cat > /opt/docker/appdashboard/nginx/cloudflare-realip.conf <<'EOF'
real_ip_header CF-Connecting-IP;
EOF
fi
BACKUP=""
if [ -f "$AVAILABLE" ]; then
  BACKUP="$(mktemp)"
  cp -a "$AVAILABLE" "$BACKUP"
fi
install -m 0644 "$TMP" "$AVAILABLE"
ln -sfn "$AVAILABLE" "$ENABLED"
rm -f "$TMP"

# Validate before touching the running service: a broken file in sites-enabled
# would make every later nginx reload/reboot fail.
if ! nginx -t; then
  echo "front-door config failed nginx -t; restoring previous site" >&2
  if [ -n "$BACKUP" ]; then
    install -m 0644 "$BACKUP" "$AVAILABLE"
    ln -sfn "$AVAILABLE" "$ENABLED"
    if nginx -t; then
      systemctl reload nginx 2>/dev/null || true
      echo "restored previous front-door config; nginx left untouched otherwise" >&2
    else
      echo "ERROR: previous front-door config also fails nginx -t" >&2
    fi
  else
    rm -f "$ENABLED"
    echo "no previous front-door config to restore; front door left disabled" >&2
  fi
  [ -n "$BACKUP" ] && rm -f "$BACKUP"
  # Staging for maintenance mode must not hard-fail the deploy.
  if [ "$START_FRONT_DOOR" != "1" ]; then
    exit 0
  fi
  exit 1
fi
[ -n "$BACKUP" ] && rm -f "$BACKUP"

if systemctl is-active --quiet nginx; then
  systemctl reload nginx
elif [ "$START_FRONT_DOOR" = "1" ]; then
  systemctl enable --now nginx
fi