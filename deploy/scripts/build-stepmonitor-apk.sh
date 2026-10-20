#!/bin/sh
set -eu
ROOT="$(cd "${1:-/var/www/AppDashboard}" && pwd)"
APP_DIR="$ROOT/apps/steps/steps-mobile-app"
OUT_DIR="$ROOT/downloads"
APK="$OUT_DIR/stepmonitor.apk"
HASH_FILE="$OUT_DIR/stepmonitor-build.sha256"
META_FILE="$OUT_DIR/stepmonitor.json"
mkdir -p "$OUT_DIR"

# Release signing credentials. The keystore itself lives at
# apps/steps/steps-mobile-app/app/release.jks (gitignored); the passwords are
# read from this env file so they never end up in the repository.
SIGN_ENV="${STEPMONITOR_SIGN_ENV:-$HOME/.config/appdashboard/steps-signing.env}"
if [ -f "$SIGN_ENV" ]; then
  set -a
  # shellcheck disable=SC1090
  . "$SIGN_ENV"
  set +a
fi
if [ -z "${KEYSTORE_PASSWORD:-}" ] || [ -z "${KEY_ALIAS:-}" ] || [ -z "${KEY_PASSWORD:-}" ]; then
  echo "StepMonitor: release signing credentials are missing." >&2
  echo "Set KEYSTORE_PASSWORD/KEY_ALIAS/KEY_PASSWORD (see $SIGN_ENV) before publishing." >&2
  exit 1
fi

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
./gradlew --no-daemon assembleFossRelease
BUILT="$(find app/build/outputs/apk/foss/release -name '*.apk' | head -n 1)"
test -n "$BUILT"

# Refuse to publish anything that is not signed with the release key: the
# previous pipeline silently shipped an Android Debug signed APK.
APKSIGNER="$(ls -1d "$ANDROID_HOME"/build-tools/*/ 2>/dev/null | sort -V | tail -n 1)apksigner"
if [ ! -x "$APKSIGNER" ]; then
  echo "StepMonitor: apksigner not found under $ANDROID_HOME/build-tools" >&2
  exit 1
fi
CERT_DN="$("$APKSIGNER" verify --print-certs "$BUILT" | sed -n 's/^Signer #1 certificate DN: //p' | head -n 1)"
case "$CERT_DN" in
  *"Android Debug"*|"")
    echo "StepMonitor: refusing to publish a debug-signed APK ($CERT_DN)" >&2
    exit 1
    ;;
esac
echo "Signing certificate: $CERT_DN"

cp "$BUILT" "$APK"
printf '%s' "$HASH" > "$HASH_FILE"
VERSION_NAME="$(./gradlew -q :app:printVersionName 2>/dev/null || echo 1.0.0)"
VERSION_CODE="$(./gradlew -q :app:printVersionCode 2>/dev/null || echo 10000)"
SIZE_BYTES="$(stat -c '%s' "$APK" 2>/dev/null || stat -f '%z' "$APK")"
APK_HASH="$(sha256sum "$APK" | awk '{print $1}')"
printf '{"version":"%s","version_code":%s,"hash":"%s","size_bytes":%s,"built_at":%s}\n' "$VERSION_NAME" "$VERSION_CODE" "$APK_HASH" "$SIZE_BYTES" "$(date +%s)" > "$META_FILE"
