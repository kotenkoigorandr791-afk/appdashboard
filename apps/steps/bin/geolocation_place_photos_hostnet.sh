#!/bin/bash
# Place-photos need outbound HTTPS (Wikimedia). The app container often has no
# egress; this wrapper runs each batch in a host-network sibling. volumes-from
# keeps APPDATA on the same /storage/applications tree the web PHP-FPM uses.
set -euo pipefail

IMAGE="${APPDASHBOARD_PLACE_PHOTOS_IMAGE:-${APPDASHBOARD_IMAGE:-ihorkotenko/appdashboard:latest}}"
LIMIT="${APPDASHBOARD_PLACE_PHOTOS_LIMIT:-15}"
SELF_NAME="${APPDASHBOARD_CONTAINER_NAME:-appdashboard}"
CODE_DIR="${APPDASHBOARD_CODE_DIR:-/home/ihor/apps-dashboard}"
DB_PASSWORD="${DB_PASSWORD:-appdashboard_dev_password}"
APPDATA_BASE_DIR="${APPDATA_BASE_DIR:-/storage/applications}"

resolve_pg() {
  local ip
  ip="$(getent hosts appdashboard-pgbouncer 2>/dev/null | awk '{print $1; exit}')"
  if [[ -z "${ip}" ]]; then
    ip="$(docker inspect -f '{{range.NetworkSettings.Networks}}{{.IPAddress}}{{end}}' appdashboard-pgbouncer 2>/dev/null || true)"
  fi
  printf '%s' "${ip}"
}

echo "place-photos-hostnet: self=${SELF_NAME} code=${CODE_DIR} appdata=${APPDATA_BASE_DIR}" >&2

while true; do
  PG_IP="$(resolve_pg)"
  if [[ -z "${PG_IP}" ]]; then
    echo "place-photos-hostnet: pgbouncer IP unavailable, retrying" >&2
    sleep 10
    continue
  fi
  set +e
  # volumes-from: live /storage/applications (FPM APPDATA). Code bind: host tree.
  docker run --rm --network host --volumes-from "${SELF_NAME}" \
    -v "${CODE_DIR}:/var/www/AppDashboard" \
    --entrypoint php \
    -e DB_HOST="${PG_IP}" \
    -e DB_PORT=5432 \
    -e DB_NAME=appdashboard \
    -e DB_USER=appdashboard \
    -e DB_PASSWORD="${DB_PASSWORD}" \
    -e APP_DB_CONFIG=/opt/AppDashboard/data/db.env \
    -e APPDATA_BASE_DIR="${APPDATA_BASE_DIR}" \
    -e APPDATA_DATA_DIR=/opt/AppDashboard/data \
    -e TMPDIR=/tmp \
    "${IMAGE}" \
    /var/www/AppDashboard/apps/steps/bin/geolocation_place_photos_run.php --limit="${LIMIT}"
  rc=$?
  set -e
  if [[ "${rc}" -ne 0 ]]; then
    echo "place-photos-hostnet: batch exit ${rc}" >&2
    sleep 8
  else
    sleep 3
  fi
done
