#!/bin/sh
set -eu
ROOT="${1:-/var/www/AppDashboard}"
APP_DIR="$ROOT/apps/steps/steps-mobile-app"
OUT_DIR="$ROOT/downloads"
APK="$OUT_DIR/stepmonitor.apk"
HASH_FILE="$OUT_DIR/stepmonitor-build.sha256"
META_FILE="$OUT_DIR/stepmonitor.json"
mkdir -p "$OUT_DIR"
HASH="$(cd "$APP_DIR" && find . -type f ! -path './app/build/*' ! -path './build/*' ! -path './.gradle/*' ! -name 'local.properties' -print0 | sort -z | xargs -0 sha256sum | sha256sum | awk '{print $1}')"
if [ -f "$APK" ] && [ -f "$HASH_FILE" ] && [ "$(cat "$HASH_FILE")" = "$HASH" ]; then
  echo "StepMonitor APK is up to date"
  exit 0
fi
if [ -d /usr/lib/jvm/java-17-openjdk-amd64 ]; then
  export JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64
fi
if [ -z "${ANDROID_HOME:-}" ]; then
  for sdk in /opt/android-sdk /opt/android-sdk-linux /android-sdk /root/Android/Sdk /home/ihor/Android/Sdk; do
    if [ -d "$sdk/platforms" ]; then
      export ANDROID_HOME="$sdk"
      break
    fi
  done
fi
cd "$APP_DIR"
./gradlew --no-daemon assembleFossDebug
BUILT="$(find app/build/outputs/apk/foss/debug -name '*.apk' | head -n 1)"
test -n "$BUILT"
cp "$BUILT" "$APK"
printf '%s' "$HASH" > "$HASH_FILE"
printf '{"version":"%s","hash":"%s","built_at":%s}\n' "$(./gradlew -q :app:printVersionName 2>/dev/null || echo 1.0.0)" "$HASH" "$(date +%s)" > "$META_FILE"
