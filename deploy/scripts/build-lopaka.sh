#!/bin/sh
# Build the Lopaka app (apps/lopaka/src) and deploy the dist into apps/lopaka.
# Usage: sh deploy/scripts/build-lopaka.sh [project_root]
#
# The build is fully local: sources are vendored in apps/lopaka/src and no
# network fetch from GitHub is performed.
set -eu

ROOT="${1:-$(pwd)}"
# make absolute so later `cd` calls don't break these paths
case "$ROOT" in /*) ;; *) ROOT="$(cd "$ROOT" && pwd)" ;; esac
SRC="$ROOT/apps/lopaka/src"
OUT="$ROOT/apps/lopaka"

[ -f "$SRC/package.json" ] || { echo "lopaka: $SRC/package.json not found" >&2; exit 1; }

# Resolve pnpm (install a local copy if missing)
if command -v pnpm >/dev/null 2>&1; then
  PNPM=pnpm
elif [ -x "$HOME/.local/share/pnpm/pnpm" ]; then
  PNPM="$HOME/.local/share/pnpm/pnpm"
else
  echo "lopaka: installing pnpm locally..."
  mkdir -p "$HOME/.local/share/pnpm"
  # corepack ships with node; prefer it
  if command -v corepack >/dev/null 2>&1; then
    corepack enable --install-directory "$HOME/.local/share/pnpm" >/dev/null 2>&1 || true
  fi
  if ! [ -x "$HOME/.local/share/pnpm/pnpm" ]; then
    npm install -g pnpm@10.30.3 >/dev/null 2>&1 || npm install --prefix "$HOME/.local/share/pnpm" pnpm@10.30.3
    [ -x "$HOME/.local/share/pnpm/pnpm" ] || PNPM_FALLBACK=1
  fi
  PNPM="$HOME/.local/share/pnpm/pnpm"
fi
export HUSKY=0
cd "$SRC"

if [ -f pnpm-lock.yaml ] && ! "$PNPM" install --frozen-lockfile --prod=false; then
  echo "lopaka: frozen install failed, retrying without lockfile strictness" >&2
  "$PNPM" install --no-frozen-lockfile --prod=false
fi

NODE_OPTIONS="--max-old-space-size=8192" "$PNPM" run build

[ -f dist/index.html ] || { echo "lopaka: build produced no dist/index.html" >&2; exit 1; }

# Deploy dist -> apps/lopaka, preserving dashboard metadata files and i18n overlay
TMP="$OUT/.deploy-tmp"
rm -rf "$TMP"
mkdir -p "$TMP"
cp -r dist/. "$TMP/"

# preserve i18n overlay if the build does not include it
if [ -f "$OUT/assets/i18n.js" ] && [ ! -f "$TMP/assets/i18n.js" ]; then
  cp "$OUT/assets/i18n.js" "$TMP/assets/i18n.js"
fi

rm -rf "$OUT/assets" "$OUT/index.html" "$OUT/font-preview.html"
mv "$TMP/assets" "$OUT/assets"
mv "$TMP/index.html" "$OUT/index.html"
[ -f "$TMP/font-preview.html" ] && mv "$TMP/font-preview.html" "$OUT/font-preview.html"
rm -rf "$TMP"

# ensure i18n overlay is wired into index.html
if [ -f "$OUT/assets/i18n.js" ] && ! grep -q 'i18n.js' "$OUT/index.html"; then
  sed -i 's|\(<script src="[^"]*lopaka-integration.js"></script>\)|\1\n      <script src="/apps/lopaka/assets/i18n.js?v=3"></script>|' "$OUT/index.html"
fi

echo "lopaka: build and deploy OK ($(find "$OUT/assets" -type f | wc -l) asset files)"

# record source hash so CI change-detection knows this build is fresh
if [ -f "$ROOT/deploy/scripts/lopaka-needs-build.sh" ]; then
  CUR="$(find "$SRC" -name node_modules -prune -o -name dist -prune -o -name .pnpm-store -prune -o -type f -print0 | sort -z | xargs -0 sha256sum 2>/dev/null | sha256sum | cut -d' ' -f1)"
  if [ -n "$CUR" ]; then
    printf '%s' "$CUR" > "$ROOT/.lopaka-build-marker"
    echo "lopaka: recorded build marker"
  fi
fi
