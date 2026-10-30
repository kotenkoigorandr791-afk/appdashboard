# Spends Android

The native Kotlin application is in `apps/spends/spends-native-android`.

```bash
export JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64
export ANDROID_HOME=/home/ihor/Android/Sdk
apps/kanboard/kanboard-mobile-app/gradlew --no-daemon -p apps/spends/spends-native-android :app:assembleDebug
```

The debug APK is `app/build/outputs/apk/debug/app-debug.apk`. Pair with a `spends://auth?endpoint=...&token=...` link; all API calls use its bearer token against `/api/spends`.
