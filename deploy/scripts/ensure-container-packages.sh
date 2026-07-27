#!/bin/sh
set -e

# Ensure system packages
need=""
command -v 7z >/dev/null 2>&1 || need="p7zip-full"
command -v unzip >/dev/null 2>&1 || need="${need:+$need }unzip"
if [ -n "$need" ]; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get install -y -qq --no-install-recommends $need
    rm -rf /var/lib/apt/lists/*
fi

# Ensure Python packages for image processing & background removal
# Note: import names differ from pip package names for some packages
PYTHON_MISSING=""
python3 -c "import importlib.util; raise SystemExit(0 if importlib.util.find_spec('PIL') else 1)" 2>/dev/null || PYTHON_MISSING="${PYTHON_MISSING:+$PYTHON_MISSING }pillow"
python3 -c "import importlib.util; raise SystemExit(0 if importlib.util.find_spec('numpy') else 1)" 2>/dev/null || PYTHON_MISSING="${PYTHON_MISSING:+$PYTHON_MISSING }numpy"
python3 -c "import importlib.util; raise SystemExit(0 if importlib.util.find_spec('scipy') else 1)" 2>/dev/null || PYTHON_MISSING="${PYTHON_MISSING:+$PYTHON_MISSING }scipy"
python3 -c "import importlib.util; raise SystemExit(0 if importlib.util.find_spec('rembg') else 1)" 2>/dev/null || PYTHON_MISSING="${PYTHON_MISSING:+$PYTHON_MISSING }rembg"
python3 -c "import importlib.util; raise SystemExit(0 if importlib.util.find_spec('onnxruntime') else 1)" 2>/dev/null || PYTHON_MISSING="${PYTHON_MISSING:+$PYTHON_MISSING }onnxruntime"
if [ -n "$PYTHON_MISSING" ]; then
    python3 -m pip install --break-system-packages --no-cache-dir $PYTHON_MISSING
fi
