#!/bin/sh
set -eu
APP_ROOT="${1:-/var/www/AppDashboard/apps/spends}"
APPDATA_BASE="${APPDATA_BASE_DIR:-/opt/ApplicationData}"
DATA_ROOT="${APPDATA_BASE}/spends"
ensure_dir() {
  dir="$1"
  mkdir -p "$dir"
  chmod 2775 "$dir" 2>/dev/null || chmod 775 "$dir" 2>/dev/null || true
}
migrate_dir_to_target() {
  src="$1"
  target="$2"
  if [ ! -d "$src" ] || [ -L "$src" ]; then
    return 0
  fi
  if [ -n "$(ls -A "$src" 2>/dev/null || true)" ]; then
    cp -a "$src"/. "$target"/ 2>/dev/null || true
  fi
  rm -rf "$src" 2>/dev/null || true
}
migrate_file_to_target() {
  src="$1"
  target="$2"
  if [ -L "$src" ]; then
    return 0
  fi
  if [ -f "$src" ]; then
    if [ ! -f "$target" ]; then
      cp -a "$src" "$target" 2>/dev/null || true
    fi
    rm -f "$src"
  fi
}
ensure_link() {
  link="$1"
  target="$2"
  ensure_dir "$(dirname "$target")"
  if [ -d "$target" ] || [ "${3:-}" = "dir" ]; then
    ensure_dir "$target"
  fi
  if [ -L "$link" ]; then
    current="$(readlink "$link" 2>/dev/null || true)"
    if [ "$current" != "$target" ]; then
      rm -f "$link"
      ln -sfn "$target" "$link"
    fi
  elif [ -d "$link" ]; then
    migrate_dir_to_target "$link" "$target"
    ln -sfn "$target" "$link"
  elif [ -f "$link" ]; then
    migrate_file_to_target "$link" "$target"
    ln -sfn "$target" "$link"
  elif [ -e "$link" ]; then
    rm -rf "$link"
    ln -sfn "$target" "$link"
  else
    ln -sfn "$target" "$link"
  fi
}
ensure_dir "$DATA_ROOT"
ensure_dir "$DATA_ROOT/users"
ensure_dir "$DATA_ROOT/backups"
ensure_dir "$DATA_ROOT/upload_sessions"
ensure_dir "$DATA_ROOT/ocr_jobs"
ensure_dir "$DATA_ROOT/results"
if [ ! -f "$DATA_ROOT/global_settings.json" ]; then
  printf '%s\n' '{"current_user":"","table_settings":{}}' > "$DATA_ROOT/global_settings.json"
fi
migrate_file_to_target "$APP_ROOT/global_settings.json" "$DATA_ROOT/global_settings.json"
ensure_link "$APP_ROOT/data" "$DATA_ROOT" dir
ensure_link "$APP_ROOT/users" "$DATA_ROOT/users" dir
ensure_link "$APP_ROOT/backups" "$DATA_ROOT/backups" dir
ensure_link "$APP_ROOT/upload_sessions" "$DATA_ROOT/upload_sessions" dir
ensure_link "$APP_ROOT/utils/ocr_jobs" "$DATA_ROOT/ocr_jobs" dir
ensure_link "$APP_ROOT/utils/results" "$DATA_ROOT/results" dir
ensure_link "$APP_ROOT/global_settings.json" "$DATA_ROOT/global_settings.json"
# Remove leftover sample/runtime files from the code tree.
for leftover in "$APP_ROOT/Thumbs.db" "$APP_ROOT/"*.xlsx; do
  if [ -e "$leftover" ] && [ ! -L "$leftover" ]; then
    base="$(basename "$leftover")"
    if [ ! -e "$DATA_ROOT/$base" ]; then
      mv "$leftover" "$DATA_ROOT/$base" 2>/dev/null || rm -f "$leftover"
    else
      rm -f "$leftover"
    fi
  fi
done
rm -rf "$APP_ROOT/utils/__pycache__" 2>/dev/null || true
chown -R www-data:www-data "$DATA_ROOT" 2>/dev/null || true
chmod -R u+rwX,g+rwX "$DATA_ROOT" 2>/dev/null || true
for link in "$APP_ROOT/users" "$APP_ROOT/backups" "$APP_ROOT/upload_sessions" "$APP_ROOT/data" "$APP_ROOT/utils/ocr_jobs" "$APP_ROOT/utils/results" "$APP_ROOT/global_settings.json"; do
  if [ -L "$link" ]; then
    chown -h www-data:www-data "$link" 2>/dev/null || true
  fi
done
