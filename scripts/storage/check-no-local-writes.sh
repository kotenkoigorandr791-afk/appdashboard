#!/bin/sh
set -eu
cd "$(dirname "$0")/../.."
PATTERN='(file_put_contents|fopen\([^)]*["'"'"']w|move_uploaded_file|@?mkdir|@?rename|@?copy|@?unlink)\s*\([^)]*(__DIR__|getcwd\(\)|APP_ROOT\s*\.|'"'"'\/tmp'"'"'|"\/tmp"|'"'"'\/host'"'"'|"\/host"|'"'"'\/host-home'"'"'|"\/host-home")'
MATCHES=$(grep -rnE "$PATTERN" --include='*.php' . \
  | grep -v '/tests/' \
  | grep -v '/vendor/' \
  | grep -v 'static-fallback' \
  || true)
if [ -n "$MATCHES" ]; then
  echo "FAIL: forbidden local-write target(s) found:"
  echo "$MATCHES"
  exit 1
fi
echo "OK: no forbidden local-write targets found"
