#!/bin/sh
set -e
CONTAINER="${APPDASHBOARD_CONTAINER:-appdashboard}"
for _ in 1 2 3 4 5 6 7 8 9 10; do
  if docker exec "$CONTAINER" supervisorctl status smart-home-ws >/dev/null 2>&1; then
    docker exec "$CONTAINER" supervisorctl restart php-fpm smart-home-ws
    exit 0
  fi
  sleep 2
done
echo "supervisor not ready in container $CONTAINER" >&2
exit 1
