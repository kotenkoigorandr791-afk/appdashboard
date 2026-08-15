#!/bin/sh
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
ensure_link() {
  link="$1"
  target="$2"
  kind="${3:-dir}"
  parent="$(dirname "$link")"
  if [ ! -d "$parent" ]; then
    return 0
  fi
  if [ "$kind" = "dir" ]; then
    ensure_dir "$target"
  else
    ensure_dir "$(dirname "$target")"
    if [ ! -L "$link" ] && [ -f "$link" ]; then
      migrate_file_to_target "$link" "$target"
    fi
    if [ ! -e "$target" ]; then
      if [ "$kind" = "json" ]; then
        printf '%s\n' '{}' > "$target"
      else
        : > "$target"
      fi
    fi
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
strip_junk() {
  app="$1"
  rm -f "$app/Thumbs.db" "$app/php_errors.log" 2>/dev/null || true
  rm -rf "$app/__pycache__" "$app/"*/__pycache__ 2>/dev/null || true
}

# cloud
ensure_dir "$APPDATA_BASE/cloud"
ensure_link "$ROOT/apps/cloud/storage" "$APPDATA_BASE/cloud" dir
for name in temp temp_convert transcription_uploads tts_output upload_sessions; do
  ensure_link "$ROOT/apps/cloud/$name" "$APPDATA_BASE/cloud_runtime/$name" dir
done

# films
ensure_dir "$APPDATA_BASE/films"
ensure_link "$ROOT/apps/films/storage" "$APPDATA_BASE/films" dir
ensure_link "$ROOT/apps/films/movies.json" "$APPDATA_BASE/films/movies.json" json
ensure_link "$ROOT/apps/films/config.json" "$APPDATA_BASE/films/config.json" json
ensure_link "$ROOT/apps/films/comments" "$APPDATA_BASE/films/comments" dir
ensure_link "$ROOT/apps/films/uploads" "$APPDATA_BASE/films/uploads" dir

# media_viewer
ensure_dir "$APPDATA_BASE/media_viewer"
ensure_link "$ROOT/apps/media_viewer/data" "$APPDATA_BASE/media_viewer/data" dir
ensure_link "$ROOT/apps/media_viewer/install" "$APPDATA_BASE/media_viewer/install" dir
ensure_link "$ROOT/apps/media_viewer/previews" "$APPDATA_BASE/media_viewer/previews" dir

# recipes
ensure_dir "$APPDATA_BASE/recipes"
ensure_link "$ROOT/apps/recipes/products" "$APPDATA_BASE/recipes/products" dir
ensure_link "$ROOT/apps/recipes/recipes" "$APPDATA_BASE/recipes/recipes" dir

# steps
ensure_dir "$APPDATA_BASE/steps"
ensure_link "$ROOT/apps/steps/users" "$APPDATA_BASE/steps" dir
ensure_link "$ROOT/apps/steps/upload_sessions" "$APPDATA_BASE/steps/upload_sessions" dir

# tft-generator
ensure_dir "$APPDATA_BASE/tft-generator"
ensure_link "$ROOT/apps/tft-generator/userdata" "$APPDATA_BASE/tft-generator/userdata" dir
# legacy path used by older code
ensure_link "$ROOT/apps/tft-generator/users" "$APPDATA_BASE/tft-generator/userdata" dir

# smart_home
ensure_dir "$APPDATA_BASE/smart_home"
ensure_link "$ROOT/apps/smart_home/data" "$APPDATA_BASE/smart_home/data" dir

# viewer
ensure_dir "$APPDATA_BASE/viewer"
ensure_link "$ROOT/apps/viewer/history.json" "$APPDATA_BASE/viewer/history.json" json

# spends
if [ -f "$ROOT/deploy/scripts/ensure-spends-appdata.sh" ]; then
  sh "$ROOT/deploy/scripts/ensure-spends-appdata.sh" "$ROOT/apps/spends" || true
fi

for app in cloud films media_viewer recipes spends steps tft-generator smart_home viewer; do
  if [ -d "$ROOT/apps/$app" ]; then
    strip_junk "$ROOT/apps/$app"
  fi
done

chown -R www-data:www-data "$APPDATA_BASE" 2>/dev/null || true
chmod -R u+rwX,g+rwX "$APPDATA_BASE" 2>/dev/null || true
