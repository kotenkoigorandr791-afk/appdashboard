#!/bin/sh
set -eu
ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)"
UNIT=performance-monitor-collector.service
install -m 0644 "$ROOT/project/performance/deploy/$UNIT" "/etc/systemd/system/$UNIT"
systemctl daemon-reload
systemctl enable "$UNIT"
systemctl restart "$UNIT"
systemctl --quiet is-active "$UNIT"
