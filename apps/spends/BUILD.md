# Spends mobile companion

The app is a **client of AppDashboard Spends** — pairing is required; offline-only use is not supported.

## Build (debug, emulator)

```bash
export ANDROID_HOME=/home/ihor/Android/Sdk
export JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64
export PATH="$JAVA_HOME/bin:$ANDROID_HOME/platform-tools:$PATH"

cd apps/spends/spends-mobile-app
pnpm install
pnpm exec expo prebuild --platform android --no-install
echo "sdk.dir=$ANDROID_HOME" > android/local.properties
cd android
./gradlew :app:assembleDebug -PreactNativeArchitectures=x86_64
adb install -r app/build/outputs/apk/debug/app-debug.apk
```

## Emulator → local Docker

Compose publishes the app on `127.0.0.1:80` only, so use `adb reverse` from the emulator:

```bash
adb reverse tcp:8081 tcp:8081   # Metro
adb reverse tcp:18080 tcp:80    # AppDashboard HTTP
```

Pair with (quote the URI so `&token=` is not eaten by the shell):

```bash
adb shell "am start -a android.intent.action.VIEW -d 'spends://auth?endpoint=http%3A%2F%2F127.0.0.1%3A18080%2Fapi%2Fspends&token=<token>'"
```

Or Settings → Server sync: endpoint `http://127.0.0.1:18080/api/spends` + token from the web pairing modal.

## Server API

- Alias: `/api/spends` → `/public-api/spends`
- `GET /api/spends/profile` — pairing probe
- `GET /api/spends/sync` — full pull + known signatures
- `POST /api/spends/sync` — push expenses/incomes/wallet_events (signature dedup)
