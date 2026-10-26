# Spends mobile companion

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

Emulator `10.0.2.2` maps to the Docker host. Pair with:

```
spends://auth?endpoint=http%3A%2F%2F10.0.2.2%2Fapi%2Fspends&token=<token>
```

Or use Settings → Server sync and paste the endpoint/token from the web pairing modal.

## Server API

- Alias: `/api/spends` → `/public-api/spends`
- `GET /api/spends/profile` — pairing probe
- `GET /api/spends/sync` — full pull + known signatures
- `POST /api/spends/sync` — push expenses/incomes/wallet_events (signature dedup)
