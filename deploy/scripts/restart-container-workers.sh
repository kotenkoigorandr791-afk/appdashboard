#!/bin/sh
set -e
CONTAINER="${APPDASHBOARD_CONTAINER:-appdashboard}"
SUPERVISORCTL="supervisorctl -c /etc/supervisor/supervisord.conf"
for _ in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do
  if docker exec "$CONTAINER" sh -c "$SUPERVISORCTL status smart-home-ws" >/dev/null 2>&1; then
    docker exec "$CONTAINER" sh -c "$SUPERVISORCTL restart php-fpm smart-home-ws ssh-ws"
    exit 0
  fi
  sleep 2
done
echo "supervisor not ready in container $CONTAINER" >&2
exit 1
