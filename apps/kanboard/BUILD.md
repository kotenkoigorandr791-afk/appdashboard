# Building the Kanboard mobile app

The Android companion lives in `apps/kanboard/kanboard-mobile-app` (the same
layout used by `apps/steps/steps-mobile-app`). It is built outside the
`appdashboard` container.

## Local build

1. Install Android SDK (platform 35) and JDK 17.
2. `cd apps/kanboard/kanboard-mobile-app`
3. `JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64 ./gradlew :app:assembleDebug`
4. Install with `adb install -r app/build/outputs/apk/debug/app-debug.apk`.

## Web pairing

The web settings window (`Settings → Mobile app`) generates a scoped token and
shows a QR code. To offer the APK for download, copy it to
`downloads/kanboard-mobile.apk` and update `downloads/kanboard-mobile.json`
(`version`, `built_at`). The download link is hidden while the file is absent.
