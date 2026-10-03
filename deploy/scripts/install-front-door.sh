#!/bin/sh
set -eu
ROOT="${1:-/opt/docker/appdashboard/code}"
export APP_UPSTREAM="${APP_UPSTREAM:-127.0.0.1:8080}"
START_FRONT_DOOR="${START_FRONT_DOOR:-1}"
AVAILABLE="/etc/nginx/sites-available/appdashboard-front-door.conf"
ENABLED="/etc/nginx/sites-enabled/appdashboard-front-door.conf"
DISABLED="/etc/nginx/conf.d/public-app-proxies.conf"
TMP="$(mktemp)"
python3 "$ROOT/deploy/scripts/render-nginx-conf.sh" "$ROOT/deploy/nginx/front-door.conf.template" "$TMP"
if [ -f "$DISABLED" ]; then
  mv "$DISABLED" "$DISABLED.disabled-$(date +%Y%m%d%H%M%S)"
fi
install -d -m 0755 /opt/docker/appdashboard/nginx /etc/nginx/sites-available /etc/nginx/sites-enabled
if [ ! -f /opt/docker/appdashboard/nginx/cloudflare-realip.conf ]; then
  cat > /opt/docker/appdashboard/nginx/cloudflare-realip.conf <<'EOF'
real_ip_header CF-Connecting-IP;
EOF
fi
install -m 0644 "$TMP" "$AVAILABLE"
ln -sfn "$AVAILABLE" "$ENABLED"
rm -f "$TMP"
nginx -t
if systemctl is-active --quiet nginx; then
  systemctl reload nginx
elif [ "$START_FRONT_DOOR" = "1" ]; then
  systemctl enable --now nginx
fi
