#!/bin/sh
set -eu
ROOT="${1:-/opt/docker/appdashboard/code}"
TARGET="${MAINTENANCE_ROOT:-/var/www/appdashboard-maintenance}"
install -d -m 0755 "$TARGET"
install -m 0644 "$ROOT/project/maintenance/index.html" "$TARGET/index.html"
install -m 0644 "$ROOT/project/maintenance/robots.txt" "$TARGET/robots.txt"
