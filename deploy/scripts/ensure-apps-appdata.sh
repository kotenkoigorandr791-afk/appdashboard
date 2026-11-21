#!/bin/sh
# Move every sub-application's runtime data onto the APPDATA volume.
#
# The repository intentionally contains no symlinks: code resolves data through
# storage_helper.php (appdata_app_base_dir / UPLOADS_BASE_DIR) and, in the
# container, the APPDATA volume is mounted directly. This script therefore
# *detaches* legacy links/directories from the code tree instead of recreating
# them: content is copied into the volume once and the path in the repository is
# removed.
set -eu
ROOT="${1:-/var/www/AppDashboard}"
APPDATA_BASE="${APPDATA_BASE_DIR:-/opt/ApplicationData}"
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
  if [ -d "$src" ] && [ ! -L "$src" ]; then
    # Fallback when files are owned by another user (e.g. www-data).
    find "$src" -mindepth 1 -exec rm -rf {} + 2>/dev/null || true
    rmdir "$src" 2>/dev/null || true
  fi
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
    rm -f "$src" 2>/dev/null || true
  fi
}
# Ensure the data exists on the volume and drop any path from the code tree.
ensure_data() {
  path="$1"
  target="$2"
  kind="${3:-dir}"
  parent="$(dirname "$path")"
  if [ ! -d "$parent" ]; then
    return 0
  fi
  if [ "$kind" = "dir" ]; then
    ensure_dir "$target"
    if [ -L "$path" ]; then
      rm -f "$path"
    elif [ -d "$path" ]; then
      migrate_dir_to_target "$path" "$target"
    fi
    return 0
  fi
  ensure_dir "$(dirname "$target")"
  if [ -L "$path" ]; then
    rm -f "$path"
  elif [ -f "$path" ]; then
    migrate_file_to_target "$path" "$target"
  fi
  if [ ! -e "$target" ]; then
    if [ "$kind" = "json" ]; then
      printf '%s\n' '{}' > "$target"
    else
      : > "$target"
    fi
  fi
}
strip_junk() {
  app="$1"
  rm -f "$app/Thumbs.db" "$app/php_errors.log" 2>/dev/null || true
  rm -rf "$app/__pycache__" "$app/"*/__pycache__ 2>/dev/null || true
}

# cloud
ensure_dir "$APPDATA_BASE/cloud"
ensure_dir "$APPDATA_BASE/cloud_runtime/sync_bus"
ensure_dir "$APPDATA_BASE/cloud_runtime/monaco_collab_tickets"
if [ -d "$ROOT/apps/cloud/storage/.sync_bus" ]; then
  cp -a "$ROOT/apps/cloud/storage/.sync_bus"/. "$APPDATA_BASE/cloud_runtime/sync_bus"/ 2>/dev/null || true
fi
if [ -d "$ROOT/apps/cloud/storage/.monaco_collab_tickets" ]; then
  cp -a "$ROOT/apps/cloud/storage/.monaco_collab_tickets"/. "$APPDATA_BASE/cloud_runtime/monaco_collab_tickets"/ 2>/dev/null || true
fi
ensure_data "$ROOT/apps/cloud/storage" "$APPDATA_BASE/cloud" dir
for name in temp temp_convert transcription_uploads tts_output upload_sessions; do
  ensure_data "$ROOT/apps/cloud/$name" "$APPDATA_BASE/cloud_runtime/$name" dir
done

# films
ensure_dir "$APPDATA_BASE/films"
ensure_data "$ROOT/apps/films/storage" "$APPDATA_BASE/films" dir
ensure_data "$ROOT/apps/films/movies.json" "$APPDATA_BASE/films/movies.json" json
ensure_data "$ROOT/apps/films/config.json" "$APPDATA_BASE/films/config.json" json
ensure_data "$ROOT/apps/films/comments" "$APPDATA_BASE/films/comments" dir
ensure_data "$ROOT/apps/films/uploads" "$APPDATA_BASE/films/uploads" dir

# recipes
ensure_dir "$APPDATA_BASE/recipes"
ensure_data "$ROOT/apps/recipes/products" "$APPDATA_BASE/recipes/products" dir
ensure_data "$ROOT/apps/recipes/recipes" "$APPDATA_BASE/recipes/recipes" dir

# steps
ensure_dir "$APPDATA_BASE/steps"
ensure_dir "$APPDATA_BASE/steps/place-photos"
ensure_dir "$APPDATA_BASE/steps/place-photos/node"
ensure_dir "$APPDATA_BASE/steps/place-photos/way"
ensure_dir "$APPDATA_BASE/steps/place-photos/relation"
ensure_dir "$APPDATA_BASE/steps/immich-thumbs"
ensure_data "$ROOT/apps/steps/users" "$APPDATA_BASE/steps" dir
ensure_data "$ROOT/apps/steps/upload_sessions" "$APPDATA_BASE/steps/upload_sessions" dir

# smart_home
ensure_dir "$APPDATA_BASE/smart_home"
ensure_data "$ROOT/apps/smart_home/data" "$APPDATA_BASE/smart_home/data" dir

# kanboard (task attachments and thumbnails). Keep the tracked .gitkeep in the
# code tree but move every runtime file to the volume.
KB_SRC="$ROOT/apps/kanboard/data"
KB_DST="$APPDATA_BASE/kanboard/data"
ensure_dir "$KB_DST"
ensure_dir "$KB_DST/files"
ensure_dir "$KB_DST/cache"
if [ -d "$KB_SRC" ] && [ ! -L "$KB_SRC" ]; then
  for _kb_item in files cache; do
    if [ -d "$KB_SRC/$_kb_item" ]; then
      cp -a "$KB_SRC/$_kb_item"/. "$KB_DST/$_kb_item"/ 2>/dev/null || true
      rm -rf "$KB_SRC/$_kb_item" 2>/dev/null || true
    fi
  done
fi
chown -R www-data:www-data "$KB_DST" 2>/dev/null || true
chmod -R u+rwX,g+rwX "$KB_DST" 2>/dev/null || true

# spends
if [ -f "$ROOT/deploy/scripts/ensure-spends-appdata.sh" ]; then
  sh "$ROOT/deploy/scripts/ensure-spends-appdata.sh" "$ROOT/apps/spends" || true
fi

for app in cloud films recipes spends steps smart_home kanboard; do
  if [ -d "$ROOT/apps/$app" ]; then
    strip_junk "$ROOT/apps/$app"
  fi
done

chown -R www-data:www-data "$APPDATA_BASE" 2>/dev/null || true
chmod -R u+rwX,g+rwX "$APPDATA_BASE" 2>/dev/null || true
