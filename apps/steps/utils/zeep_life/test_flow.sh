#!/bin/bash
set -euo pipefail
ROOT="/var/www/AppDashboard/apps/steps"
ZIP="$ROOT/3031480051_1782297159820.zip"
PY="$ROOT/utils/.venv/bin/python3"
WORKER="$ROOT/utils/zeep_life/zeep_import_worker.py"
JOB="/tmp/zeep_flow_test_$$"
SQLITE="/tmp/zeep_flow_test_$$.sqlite3"
JID="$(basename "$JOB")"
mkdir -p "$JOB"
cp "$ZIP" "$JOB/zeep.zip"
python3 - <<PY
import sqlite3
sqlite3.connect("$SQLITE").execute("CREATE TABLE IF NOT EXISTS steps (date TEXT PRIMARY KEY, steps INTEGER)").connection.commit()
PY
printf '%s' "{\"user_sqlite_path\":\"$SQLITE\",\"user_email\":\"test@test.com\",\"user_schema\":\"\",\"job_id\":\"$JID\",\"conflict_mode\":\"preview\",\"archive_password\":\"nYFtsTHn\"}" > "$JOB/job_config.json"
printf '%s' '{"status":"running","progress":0,"message":"queued","imported":0,"total_days":0,"job_id":"'"$JID"'"}' > "$JOB/state.json"
LOG="$JOB/worker.log"
nohup "$PY" "$WORKER" "$JOB" >>"$LOG" 2>&1 &
for i in $(seq 1 20); do
  sleep 0.3
  ST=$(cat "$JOB/state.json" 2>/dev/null || echo '{}')
  P=$(python3 -c "import json,sys; d=json.loads(sys.argv[1]); print(d.get('progress',0), d.get('status',''), d.get('message',''))" "$ST")
  echo "poll $i: $P"
  echo "$ST" | grep -q awaiting_confirm && break
  echo "$ST" | grep -q '"status": "error"' && break
done
echo "=== final state ==="
cat "$JOB/state.json"
echo "=== log tail ==="
tail -3 "$LOG" 2>/dev/null || true
rm -rf "$JOB" "$SQLITE"
