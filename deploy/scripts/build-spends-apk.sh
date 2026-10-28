#!/bin/sh
set -eu
ROOT="$(cd "${1:-/var/www/AppDashboard}" && pwd)"
APP_DIR="$ROOT/apps/spends/spends-mobile-app"
OUT_DIR="$ROOT/downloads"
APK="$OUT_DIR/spends-mobile.apk"
HASH_FILE="$OUT_DIR/spends-mobile-build.sha256"
META_FILE="$OUT_DIR/spends-mobile.json"

cleanup_build_artifacts() {
  rm -rf "$APP_DIR/android/app/build" "$APP_DIR/android/build" "$APP_DIR/android/.gradle"
}
trap cleanup_build_artifacts EXIT

mkdir -p "$OUT_DIR"

# Release signing credentials. The keystore itself lives at
# apps/spends/spends-mobile-app/app/release.jks (gitignored); the passwords are
# read from this env file so they never end up in the repository.
SIGN_ENV="${SPENDS_SIGN_ENV:-$HOME/.config/appdashboard/spends-signing.env}"
if [ -f "$SIGN_ENV" ]; then
  set -a
  # shellcheck disable=SC1090
  . "$SIGN_ENV"
  set +a
fi
if [ -z "${KEYSTORE_PASSWORD:-}" ] || [ -z "${KEY_ALIAS:-}" ] || [ -z "${KEY_PASSWORD:-}" ]; then
  echo "Spends mobile: release signing credentials are missing." >&2
  echo "Set KEYSTORE_PASSWORD/KEY_ALIAS/KEY_PASSWORD (see $SIGN_ENV) before publishing." >&2
  exit 1
fi
if [ ! -f "$APP_DIR/app/release.jks" ]; then
  echo "Spends mobile: release keystore is missing at $APP_DIR/app/release.jks" >&2
  exit 1
fi

HASH="$(cd "$APP_DIR" && find . -type f ! -path './node_modules/*' ! -path './android/*' ! -path './.expo/*' ! -path './dist/*' ! -path './build/*' ! -path './app/build/*' ! -name 'local.properties' ! -name 'release.jks' ! -name '.env.local' -print0 | sort -z | xargs -0 sha256sum | sha256sum | awk '{print $1}')"
if [ -f "$APK" ] && [ -f "$HASH_FILE" ] && [ "$(cat "$HASH_FILE")" = "$HASH" ]; then
  echo "Spends mobile APK is up to date"
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

# The native project is generated (android/ is gitignored), so it is rebuilt
# from app.json on every publish. The signing config is injected by the
# with-android-release-signing config plugin.
cd "$APP_DIR"
if command -v pnpm >/dev/null 2>&1; then
  pnpm install --frozen-lockfile
else
  npm install
fi
pnpm exec expo prebuild --platform android --no-install
if [ ! -f "$APP_DIR/android/local.properties" ]; then
  printf 'sdk.dir=%s\n' "$ANDROID_HOME" > "$APP_DIR/android/local.properties"
fi

cd "$APP_DIR/android"
./gradlew --no-daemon assembleRelease
BUILT="$(find app/build/outputs/apk/release -name '*.apk' | head -n 1)"
test -n "$BUILT"

# Refuse to publish anything that is not signed with the release key: the
# previous pipeline silently shipped an Android Debug signed APK.
APKSIGNER="$(ls -1d "$ANDROID_HOME"/build-tools/*/ 2>/dev/null | sort -V | tail -n 1)apksigner"
if [ ! -x "$APKSIGNER" ]; then
  echo "Spends mobile: apksigner not found under $ANDROID_HOME/build-tools" >&2
  exit 1
fi
CERT_DN="$("$APKSIGNER" verify --print-certs "$BUILT" | sed -n 's/^Signer #1 certificate DN: //p' | head -n 1)"
case "$CERT_DN" in
  *"Android Debug"*|"")
    echo "Spends mobile: refusing to publish a debug-signed APK ($CERT_DN)" >&2
    exit 1
    ;;
esac
echo "Signing certificate: $CERT_DN"

cp "$BUILT" "$APK"
printf '%s' "$HASH" > "$HASH_FILE"
VERSION_NAME="$(node -e "process.stdout.write(String(require('$APP_DIR/app.json').expo.version || '0.0.0'))")"
VERSION_CODE="$(node -e "process.stdout.write(String(require('$APP_DIR/app.json').expo.android?.versionCode || 1))")"
SIZE_BYTES="$(stat -c '%s' "$APK" 2>/dev/null || stat -f '%z' "$APK")"
APK_HASH="$(sha256sum "$APK" | awk '{print $1}')"
printf '{"version":"%s","version_code":%s,"hash":"%s","size_bytes":%s,"built_at":%s}\n' "$VERSION_NAME" "$VERSION_CODE" "$APK_HASH" "$SIZE_BYTES" "$(date +%s)" > "$META_FILE"
