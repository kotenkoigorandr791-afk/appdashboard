#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
if getent group docker >/dev/null 2>&1; then
  usermod -aG docker www-data || true
fi
units=(
  "$ROOT/webssh/ap-ssh-ws.service"
  "$ROOT/apps/cloud/cloud-sync-ws.service"
  "$ROOT/apps/cloud/cloud-monaco-collab-ws.service"
  "$ROOT/apps/smart_home/smart-home-ws.service"
  "$ROOT/apps/performance/deploy/performance-monitor-collector.service"
  "$ROOT/bot/appdashboard-bot.service"
)
for unit in "${units[@]}"; do
  name="$(basename "$unit")"
  install -m 0644 "$unit" "/etc/systemd/system/$name"
  echo "installed $name"
done
systemctl daemon-reload
for unit in "${units[@]}"; do
  name="$(basename "$unit")"
  systemctl enable "$name"
  systemctl restart "$name"
  systemctl --no-pager --full status "$name" | sed -n '1,5p'
done
systemctl restart php8.3-fpm 2>/dev/null || systemctl restart php-fpm 2>/dev/null || true
