#!/bin/sh
set -e
if [ "${RUN_CONTAINER_PACKAGE_ENSURE:-0}" != "1" ]; then
mkdir -p /var/www/AppDashboard/apps/cloud/tts_output/.cache
chmod 0777 /var/www/AppDashboard/apps/cloud/tts_output /var/www/AppDashboard/apps/cloud/tts_output/.cache 2>/dev/null || true
mkdir -p /var/www/AppDashboard/apps/cloud/transcription_uploads/.cache
chmod 0777 /var/www/AppDashboard/apps/cloud/transcription_uploads /var/www/AppDashboard/apps/cloud/transcription_uploads/.cache 2>/dev/null || true
exit 0
fi

# Ensure system packages
need=""
command -v 7z >/dev/null 2>&1 || need="p7zip-full"
command -v unzip >/dev/null 2>&1 || need="${need:+$need }unzip"
command -v ffmpeg >/dev/null 2>&1 || need="${need:+$need }ffmpeg"
command -v bwrap >/dev/null 2>&1 || need="${need:+$need }bubblewrap"
command -v gcc >/dev/null 2>&1 || need="${need:+$need }gcc"
command -v g++ >/dev/null 2>&1 || need="${need:+$need }g++"
command -v javac >/dev/null 2>&1 || need="${need:+$need }default-jdk-headless"
if [ -n "$need" ]; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get install -y -qq --no-install-recommends $need
    rm -rf /var/lib/apt/lists/*
fi
if command -v bwrap >/dev/null 2>&1; then
    chmod u+s "$(command -v bwrap)" 2>/dev/null || true
fi

# Ensure Python packages for image processing & background removal
# Note: import names differ from pip package names for some packages
PYTHON_MISSING=""
python3 -c "import importlib.util; raise SystemExit(0 if importlib.util.find_spec('PIL') else 1)" 2>/dev/null || PYTHON_MISSING="${PYTHON_MISSING:+$PYTHON_MISSING }pillow"
python3 -c "import importlib.util; raise SystemExit(0 if importlib.util.find_spec('numpy') else 1)" 2>/dev/null || PYTHON_MISSING="${PYTHON_MISSING:+$PYTHON_MISSING }numpy"
python3 -c "import importlib.util; raise SystemExit(0 if importlib.util.find_spec('scipy') else 1)" 2>/dev/null || PYTHON_MISSING="${PYTHON_MISSING:+$PYTHON_MISSING }scipy"
python3 -c "import importlib.util; raise SystemExit(0 if importlib.util.find_spec('rembg') else 1)" 2>/dev/null || PYTHON_MISSING="${PYTHON_MISSING:+$PYTHON_MISSING }rembg"
python3 -c "import importlib.util; raise SystemExit(0 if importlib.util.find_spec('onnxruntime') else 1)" 2>/dev/null || PYTHON_MISSING="${PYTHON_MISSING:+$PYTHON_MISSING }onnxruntime"
python3 -c "import importlib.util; raise SystemExit(0 if importlib.util.find_spec('gtts') else 1)" 2>/dev/null || PYTHON_MISSING="${PYTHON_MISSING:+$PYTHON_MISSING }gtts"
python3 -c "import importlib.util; raise SystemExit(0 if importlib.util.find_spec('psycopg2') else 1)" 2>/dev/null || PYTHON_MISSING="${PYTHON_MISSING:+$PYTHON_MISSING }psycopg2-binary"
if [ -n "$PYTHON_MISSING" ]; then
    python3 -m pip install --break-system-packages --no-cache-dir $PYTHON_MISSING
fi

mkdir -p /var/www/AppDashboard/apps/cloud/tts_output/.cache
chmod 0777 /var/www/AppDashboard/apps/cloud/tts_output /var/www/AppDashboard/apps/cloud/tts_output/.cache
mkdir -p /var/www/AppDashboard/apps/cloud/transcription_uploads/.cache
chmod 0777 /var/www/AppDashboard/apps/cloud/transcription_uploads /var/www/AppDashboard/apps/cloud/transcription_uploads/.cache
