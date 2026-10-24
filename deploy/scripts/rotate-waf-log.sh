#!/bin/sh
LOG=/var/log/modsecurity/audit.log
MAX=${WAF_AUDIT_MAX_BYTES:-104857600}
KEEP=${WAF_AUDIT_KEEP:-3}
while true; do
  if [ -f "$LOG" ] && [ "$(stat -c %s "$LOG")" -gt "$MAX" ]; then
    i=$KEEP
    while [ "$i" -gt 1 ]; do
      [ -f "$LOG.$((i-1)).gz" ] && mv -f "$LOG.$((i-1)).gz" "$LOG.$i.gz"
      i=$((i-1))
    done
    cp "$LOG" "$LOG.1" && : > "$LOG" && gzip -f "$LOG.1"
  fi
  sleep 300
done
