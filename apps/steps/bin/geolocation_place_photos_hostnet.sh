#!/bin/bash
# Place-photos need outbound HTTPS (Wikimedia). The app container often has no
# egress; this wrapper runs each batch in a host-network sibling. volumes-from
# keeps APPDATA on the same tree the web PHP-FPM uses.
set -euo pipefail

IMAGE="${APPDASHBOARD_PLACE_PHOTOS_IMAGE:-${APPDASHBOARD_IMAGE:-ihorkotenko/appdashboard:latest}}"
LIMIT="${APPDASHBOARD_PLACE_PHOTOS_LIMIT:-15}"
SELF_NAME="${APPDASHBOARD_CONTAINER_NAME:-appdashboard}"
# Optional host bind for live code. Never default to a workstation path: Docker
# creates an empty host directory for missing binds and hides the image files.
CODE_DIR="${APPDASHBOARD_CODE_DIR:-}"
DB_PASSWORD="${DB_PASSWORD:-appdashboard_dev_password}"
APPDATA_BASE_DIR="${APPDATA_BASE_DIR:-}"

resolve_pg() {
  local ip
  ip="$(getent hosts appdashboard-pgbouncer 2>/dev/null | awk '{print $1; exit}')"
  if [[ -z "${ip}" ]]; then
    ip="$(docker inspect -f '{{range.NetworkSettings.Networks}}{{.IPAddress}}{{end}}' appdashboard-pgbouncer 2>/dev/null || true)"
  fi
  printf '%s' "${ip}"
}

# Prefer the APPDATA PHP-FPM actually serves. docker-entrypoint may re-export
# APPDATA_BASE_DIR to /storage/applications after seeding mergerfs, while
# Config.Env still says /opt/ApplicationData — writing there leaves the API
# unable to see place photos.
parent_env() {
  local key="$1"
  docker inspect -f '{{range .Config.Env}}{{println .}}{{end}}' "${SELF_NAME}" 2>/dev/null | sed -n "s/^${key}=//p" | head -n1
}
fpm_appdata() {
  local pid
  pid="$(docker exec "${SELF_NAME}" sh -c 'pgrep -o -u www-data php-fpm 2>/dev/null || pgrep -o php-fpm' 2>/dev/null | head -n1 || true)"
  if [[ -z "${pid}" ]]; then return 0; fi
  docker exec "${SELF_NAME}" sh -c "tr '\\0' '\\n' < /proc/${pid}/environ 2>/dev/null" \
    | sed -n 's/^APPDATA_BASE_DIR=//p' | head -n1
}
resolved_appdata="$(fpm_appdata || true)"
if [[ -n "${resolved_appdata}" ]]; then
  APPDATA_BASE_DIR="${resolved_appdata}"
elif [[ -z "${APPDATA_BASE_DIR}" ]]; then
  APPDATA_BASE_DIR="$(parent_env APPDATA_BASE_DIR)"
fi
APPDATA_BASE_DIR="${APPDATA_BASE_DIR:-/opt/ApplicationData}"
if [[ -z "${DB_PASSWORD}" || "${DB_PASSWORD}" == "appdashboard_dev_password" ]]; then
  parent_db="$(parent_env DB_PASSWORD)"
  if [[ -n "${parent_db}" ]]; then DB_PASSWORD="${parent_db}"; fi
fi
if [[ -z "${IMAGE}" || "${IMAGE}" == "ihorkotenko/appdashboard:latest" ]]; then
  parent_image="$(docker inspect -f '{{.Config.Image}}' "${SELF_NAME}" 2>/dev/null || true)"
  if [[ -n "${parent_image}" ]]; then IMAGE="${parent_image}"; fi
fi

echo "place-photos-hostnet: self=${SELF_NAME} code=${CODE_DIR:-<image>} appdata=${APPDATA_BASE_DIR}" >&2

while true; do
  PG_IP="$(resolve_pg)"
  if [[ -z "${PG_IP}" ]]; then
    echo "place-photos-hostnet: pgbouncer IP unavailable, retrying" >&2
    sleep 10
    continue
  fi
  set +e
  # volumes-from: live APPDATA mounts from the app container. Use the image's
  # /var/www/AppDashboard unless an explicit host code tree is provided.
  docker_args=(
    run --rm --network host --volumes-from "${SELF_NAME}"
    --entrypoint php
    -e DB_HOST="${PG_IP}"
    -e DB_PORT=5432
    -e DB_NAME=appdashboard
    -e DB_USER=appdashboard
    -e DB_PASSWORD="${DB_PASSWORD}"
    -e APP_DB_CONFIG=/opt/AppDashboard/data/db.env
    -e APPDATA_BASE_DIR="${APPDATA_BASE_DIR}"
    -e APPDATA_DATA_DIR=/opt/AppDashboard/data
    -e TMPDIR=/tmp
  )
  if [[ -n "${CODE_DIR}" ]]; then
    docker_args+=(-v "${CODE_DIR}:/var/www/AppDashboard")
  fi
  docker "${docker_args[@]}" \
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
