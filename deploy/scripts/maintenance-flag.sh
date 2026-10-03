#!/bin/sh
set -eu
TARGET="${MAINTENANCE_ROOT:-/var/www/appdashboard-maintenance}"
FLAG="$TARGET/ACTIVE"
case "${1:-}" in
  on)
    install -d -m 0755 "$TARGET"
    : > "$FLAG"
    ;;
  off)
    rm -f "$FLAG"
    ;;
  *)
    echo "usage: maintenance-flag.sh on|off" >&2
    exit 2
    ;;
esac
