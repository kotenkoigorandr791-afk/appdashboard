#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
OUTDIR="${OUTDIR:-$(mktemp -d /tmp/steps-ramp.XXXXXX)}"
USERS="${USERS:-50}"
POINTS="${POINTS:-5000}"
export BASE="${BASE:-http://127.0.0.1}" CREDS="$OUTDIR/creds.json" OUT="$OUTDIR/report.json"
APP_C="${APP_CONTAINER:-appdashboard}"
export UNDICI_DIR="${UNDICI_DIR:-/tmp/steps-ramp-deps}"
[ -d "$UNDICI_DIR/node_modules/undici" ] || { mkdir -p "$UNDICI_DIR"; (cd "$UNDICI_DIR" && npm init -y >/dev/null && npm i undici >/dev/null 2>&1); }
NGX=/tmp/appdashboard-nginx.conf
if [ "${RATE_LIMIT_OFF:-1}" = 1 ]; then
  docker exec "$APP_C" sh -c "cp $NGX /tmp/appdashboard-nginx.conf.bak && sed -i 's/^\(\s*\)limit_req zone=appdashboard_api/\1#limit_req zone=appdashboard_api/' $NGX && nginx -s reload"
fi
trap 'docker exec "$APP_C" sh -c "[ -f /tmp/appdashboard-nginx.conf.bak ] && cp /tmp/appdashboard-nginx.conf.bak $NGX && rm /tmp/appdashboard-nginx.conf.bak && nginx -s reload" >/dev/null 2>&1 || true; docker exec "$APP_C" php /var/www/AppDashboard/apps/steps/tests/load/provision.php cleanup >/dev/null 2>&1 || true' EXIT
docker exec "$APP_C" php /var/www/AppDashboard/apps/steps/tests/load/provision.php provision "$USERS" "$POINTS" > "$CREDS"
docker exec appdashboard-db psql -U appdashboard -d appdashboard -qc "select pg_stat_statements_reset()" >/dev/null 2>&1 || true
node ramp.mjs
docker exec appdashboard-db psql -U appdashboard -d appdashboard -Atc "select round(total_exec_time::numeric),calls,round(mean_exec_time::numeric,2),left(regexp_replace(query,'\s+',' ','g'),140) from pg_stat_statements order by total_exec_time desc limit 15" > "$OUTDIR/top_sql.txt" 2>&1 || true
echo "REPORT $OUT"; echo "TOPSQL $OUTDIR/top_sql.txt"
