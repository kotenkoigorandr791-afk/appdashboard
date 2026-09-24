#!/bin/sh
# Detect whether the vendored Lopaka sources changed and the built bundle in
# apps/lopaka needs a rebuild. Exits 0 when a rebuild is needed, 1 otherwise.
# Usage: sh deploy/scripts/lopaka-needs-build.sh [project_root]
set -eu

ROOT="${1:-$(pwd)}"
SRC="$ROOT/apps/lopaka/src"
OUT="$ROOT/apps/lopaka"
MARKER="$ROOT/.lopaka-build-marker"

# no sources -> nothing to build
[ -d "$SRC" ] || exit 1
# no bundle yet -> build
[ -f "$OUT/index.html" ] || { echo "lopaka: no bundle found, rebuild needed" >&2; exit 0; }

# compare hash of sources with the recorded one
current_hash() {
    find "$SRC" \
        \( -name node_modules -o -name dist -o -name .pnpm-store \) -prune -o \
        -type f -print0 | sort -z | xargs -0 sha256sum 2>/dev/null | sha256sum | cut -d' ' -f1
}

CUR="$(current_hash)"
[ -n "$CUR" ] || exit 1

if [ -f "$MARKER" ] && [ "$(cat "$MARKER" 2>/dev/null)" = "$CUR" ]; then
    exit 1
fi

echo "lopaka: sources changed since last build, rebuild needed" >&2
exit 0
