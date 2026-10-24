#!/bin/sh
set -e
CONTAINER="${APPDASHBOARD_CONTAINER:-appdashboard}"
SUPERVISORCTL="supervisorctl -c /etc/supervisor/supervisord.conf"
# Entrypoint may run composer/pip/domain seed before supervisord starts (often >30s).
i=0
while [ "$i" -lt 120 ]; do
  i=$((i + 1))
  if ! docker inspect -f '{{.State.Running}}' "$CONTAINER" 2>/dev/null | grep -qx true; then
    sleep 2
    continue
  fi
  if docker exec "$CONTAINER" sh -c "$SUPERVISORCTL status smart-home-ws" >/dev/null 2>&1; then
    docker exec "$CONTAINER" sh -c "$SUPERVISORCTL restart php-fpm smart-home-ws smart-home-ws-2 smart-home-ws-3 smart-home-mqtt-worker smart-home-mqtt-worker-2 ssh-ws"
    exit 0
  fi
  sleep 2
done
echo "supervisor not ready in container $CONTAINER after ${i} attempts" >&2
docker inspect -f 'running={{.State.Running}} status={{.State.Status}} started={{.State.StartedAt}}' "$CONTAINER" 2>&1 || true
docker logs "$CONTAINER" --tail 50 2>&1 || true
exit 1
