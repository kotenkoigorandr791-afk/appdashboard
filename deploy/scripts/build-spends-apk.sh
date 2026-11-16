#!/bin/sh
set -eu
ROOT="$(cd "${1:-/var/www/AppDashboard}" && pwd)"
APP_DIR="$ROOT/apps/spends/spends-native-android"
OUT_DIR="$ROOT/downloads"
APK="$OUT_DIR/spends-mobile.apk"
HASH_FILE="$OUT_DIR/spends-mobile-build.sha256"
META_FILE="$OUT_DIR/spends-mobile.json"
SIGN_ENV="${SPENDS_SIGN_ENV:-$HOME/.config/appdashboard/spends-signing.env}"
if [ -f "$SIGN_ENV" ]; then
  set -a
  . "$SIGN_ENV"
  set +a
fi
if [ -z "${KEYSTORE_PASSWORD:-}" ] || [ -z "${KEY_ALIAS:-}" ] || [ -z "${KEY_PASSWORD:-}" ] || [ ! -f "$APP_DIR/app/release.jks" ]; then
  echo "Spends release signing credentials or keystore are missing" >&2
  exit 1
fi
if [ -d /usr/lib/jvm/java-17-openjdk-amd64 ]; then
  JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64
  export JAVA_HOME
fi
if [ -z "${ANDROID_HOME:-}" ]; then
  for sdk in /opt/android-sdk /opt/android-sdk-linux /android-sdk /root/Android/Sdk /home/ihor/Android/Sdk; do
    if [ -d "$sdk/platforms" ]; then ANDROID_HOME="$sdk"; export ANDROID_HOME; break; fi
  done
fi
if [ -z "${ANDROID_HOME:-}" ]; then echo "Android SDK not found" >&2; exit 1; fi
mkdir -p "$OUT_DIR"
HASH="$(cd "$APP_DIR" && find . -type f ! -path './build/*' ! -path './.gradle/*' ! -name 'local.properties' ! -name 'release.jks' -print0 | sort -z | xargs -0 sha256sum | sha256sum | awk '{print $1}')"
if [ -f "$APK" ] && [ -f "$HASH_FILE" ] && [ "$(cat "$HASH_FILE")" = "$HASH" ]; then exit 0; fi
printf 'sdk.dir=%s\n' "$ANDROID_HOME" > "$APP_DIR/local.properties"
export KEYSTORE_PASSWORD KEY_ALIAS KEY_PASSWORD
sh "$APP_DIR/gradlew" --no-daemon -p "$APP_DIR" :app:assembleRelease
BUILT="$APP_DIR/app/build/outputs/apk/release/app-release.apk"
test -f "$BUILT"
cp "$BUILT" "$APK"
printf '%s' "$HASH" > "$HASH_FILE"
SIZE_BYTES="$(stat -c '%s' "$APK")"
APK_HASH="$(sha256sum "$APK" | awk '{print $1}')"
VERSION_NAME="$(tr -d '[:space:]' < "$ROOT/VERSION")"
VERSION_CODE="$(( $(printf '%s' "$VERSION_NAME" | cut -d. -f1) * 10000 + $(printf '%s' "$VERSION_NAME" | cut -d. -f2) * 100 + $(printf '%s' "$VERSION_NAME" | cut -d. -f3) ))"
test -n "$VERSION_NAME"
test -n "$VERSION_CODE"
printf '{"version":"%s","version_code":%s,"hash":"%s","size_bytes":%s,"built_at":%s}\n' "$VERSION_NAME" "$VERSION_CODE" "$APK_HASH" "$SIZE_BYTES" "$(date +%s)" > "$META_FILE"
