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
# Ensure the data exists on the volume and remove the path from the code tree.
# The repository never carries symlinks: applications resolve data through
# storage_helper.php, so the legacy link is simply detached.
ensure_data() {
  link="$1"
  target="$2"
  kind="${3:-dir}"
  ensure_dir "$(dirname "$target")"
  if [ "$kind" = "dir" ]; then
    ensure_dir "$target"
    if [ -L "$link" ]; then
      rm -f "$link"
    elif [ -d "$link" ]; then
      migrate_dir_to_target "$link" "$target"
    fi
    return 0
  fi
  if [ -L "$link" ]; then
    rm -f "$link"
  elif [ -f "$link" ]; then
    migrate_file_to_target "$link" "$target"
  fi
  if [ ! -e "$target" ]; then
    printf '%s\n' '{"current_user":"","table_settings":{}}' > "$target"
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
ensure_data "$APP_ROOT/data" "$DATA_ROOT" dir
ensure_data "$APP_ROOT/users" "$DATA_ROOT/users" dir
ensure_data "$APP_ROOT/backups" "$DATA_ROOT/backups" dir
ensure_data "$APP_ROOT/upload_sessions" "$DATA_ROOT/upload_sessions" dir
ensure_data "$APP_ROOT/utils/ocr_jobs" "$DATA_ROOT/ocr_jobs" dir
ensure_data "$APP_ROOT/utils/results" "$DATA_ROOT/results" dir
ensure_data "$APP_ROOT/global_settings.json" "$DATA_ROOT/global_settings.json"
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
# Legacy links must stay removed so the code tree contains no symlinks.
for link in "$APP_ROOT/users" "$APP_ROOT/backups" "$APP_ROOT/upload_sessions" "$APP_ROOT/data" "$APP_ROOT/utils/ocr_jobs" "$APP_ROOT/utils/results" "$APP_ROOT/global_settings.json"; do
  if [ -L "$link" ]; then
    rm -f "$link"
  fi
done
