#!/usr/bin/env sh
set -eu
cat >/etc/systemd/system/immich-theme-bridge-watch.service <<'EOF'
[Unit]
Description=Keep AppDashboard Immich theme bridge after container updates
After=docker.service
Requires=docker.service

[Service]
Type=simple
ExecStartPre=/bin/sh /opt/docker/appdashboard/code/deploy/scripts/patch-immich-theme-bridge.sh
ExecStart=/bin/sh -c 'docker events --filter container=immich_server --filter event=start --format "{{.Time}}" | while read -r _; do /bin/sh /opt/docker/appdashboard/code/deploy/scripts/patch-immich-theme-bridge.sh; done'
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable --now immich-theme-bridge-watch.service
