#!/bin/sh
set -e
if [ "${RUN_CONTAINER_PACKAGE_ENSURE:-0}" != "1" ]; then
APPDATA_BASE="${APPDATA_BASE_DIR:-/opt/ApplicationData}"
mkdir -p "$APPDATA_BASE/cloud_runtime/tts_output/.cache"
chmod 0777 "$APPDATA_BASE/cloud_runtime/tts_output" "$APPDATA_BASE/cloud_runtime/tts_output/.cache" 2>/dev/null || true
mkdir -p "$APPDATA_BASE/cloud_runtime/transcription_uploads/.cache"
chmod 0777 "$APPDATA_BASE/cloud_runtime/transcription_uploads" "$APPDATA_BASE/cloud_runtime/transcription_uploads/.cache" 2>/dev/null || true
mkdir -p /opt/ApplicationData/cloud_libreoffice_home/.cache /opt/ApplicationData/cloud_libreoffice_home/.config /opt/ApplicationData/cloud_libreoffice_home/tmp "$APPDATA_BASE/cloud_runtime/temp_convert"
chown -R www-data:www-data /opt/ApplicationData/cloud_libreoffice_home "$APPDATA_BASE/cloud_runtime/temp_convert" 2>/dev/null || true
chmod -R 0775 /opt/ApplicationData/cloud_libreoffice_home "$APPDATA_BASE/cloud_runtime/temp_convert" 2>/dev/null || true
exit 0
fi

# Ensure system packages
need=""
command -v 7z >/dev/null 2>&1 || need="p7zip-full"
command -v unzip >/dev/null 2>&1 || need="${need:+$need }unzip"
command -v ffmpeg >/dev/null 2>&1 || need="${need:+$need }ffmpeg"
command -v soffice >/dev/null 2>&1 || need="${need:+$need }libreoffice"
command -v pandoc >/dev/null 2>&1 || need="${need:+$need }pandoc"
command -v bwrap >/dev/null 2>&1 || need="${need:+$need }bubblewrap"
command -v gcc >/dev/null 2>&1 || need="${need:+$need }gcc"
command -v g++ >/dev/null 2>&1 || need="${need:+$need }g++"
if [ -n "$need" ]; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get install -y -qq --no-install-recommends $need
    rm -rf /var/lib/apt/lists/*
fi
if command -v bwrap >/dev/null 2>&1; then
    chmod u+s "$(command -v bwrap)" 2>/dev/null || true
fi

APPDATA_BASE="${APPDATA_BASE_DIR:-/opt/ApplicationData}"
mkdir -p "$APPDATA_BASE/cloud_runtime/tts_output/.cache"
chmod 0777 "$APPDATA_BASE/cloud_runtime/tts_output" "$APPDATA_BASE/cloud_runtime/tts_output/.cache"
mkdir -p "$APPDATA_BASE/cloud_runtime/transcription_uploads/.cache"
chmod 0777 "$APPDATA_BASE/cloud_runtime/transcription_uploads" "$APPDATA_BASE/cloud_runtime/transcription_uploads/.cache"
mkdir -p /opt/ApplicationData/cloud_libreoffice_home/.cache /opt/ApplicationData/cloud_libreoffice_home/.config /opt/ApplicationData/cloud_libreoffice_home/tmp "$APPDATA_BASE/cloud_runtime/temp_convert"
chown -R www-data:www-data /opt/ApplicationData/cloud_libreoffice_home "$APPDATA_BASE/cloud_runtime/temp_convert" 2>/dev/null || true
chmod -R 0775 /opt/ApplicationData/cloud_libreoffice_home "$APPDATA_BASE/cloud_runtime/temp_convert" 2>/dev/null || true
