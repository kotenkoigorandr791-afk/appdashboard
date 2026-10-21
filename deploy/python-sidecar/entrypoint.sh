#!/bin/sh
set -eu

MODELS_ROOT="${MODELS_ROOT:-/opt/ApplicationData/models}"
export HF_HOME="${HF_HOME:-$MODELS_ROOT/huggingface}"
export U2NET_HOME="${U2NET_HOME:-$MODELS_ROOT/rembg}"
export XDG_CACHE_HOME="${XDG_CACHE_HOME:-$MODELS_ROOT/cache}"
export XDG_DATA_HOME="${XDG_DATA_HOME:-$MODELS_ROOT}"
export HOME="${HOME:-$MODELS_ROOT/home}"

mkdir -p "$HF_HOME" "$U2NET_HOME" "$XDG_CACHE_HOME" "$HOME"

prefetch_models() {
  if [ "${PREFETCH_MODELS:-1}" != "1" ]; then
    return 0
  fi
  echo "sidecar: prefetching rembg into $MODELS_ROOT"
  python3 - <<'PY' || true
from rembg import new_session
new_session("u2net")
print("sidecar: models ready")
PY
}

# Prefetch in background so /health comes up quickly; first request can also download.
prefetch_models &

exec uvicorn server:app --host 0.0.0.0 --port 8100
