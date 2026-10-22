# Tasks — Android companion app

Native Kotlin/Compose client for the Kanboard workspace. It pairs with the web UI
through a QR code or a manual connection link and keeps a local Room cache.

## Requirements

- JDK 17 (not 21/25 — Gradle/Kotlin in this project are pinned to 17)
- Android SDK with platform 35
- `JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64`
- `ANDROID_HOME=$HOME/Android/Sdk` (or a `local.properties` with `sdk.dir=...`)

## Build

```bash
cd apps/kanboard/kanboard-mobile-app
JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64 ANDROID_HOME=$HOME/Android/Sdk \
  ./gradlew :app:assembleDebug
```

The APK is written to `app/build/outputs/apk/debug/app-debug.apk`.

## Install and run

```bash
adb install -r app/build/outputs/apk/debug/app-debug.apk
adb shell am start -n com.appdashboard.planning/.MainActivity
```

## Pairing

1. Open Kanboard in the web dashboard and click **Settings → Mobile app**.
2. Tap **Generate** and scan the QR code with the app, or open **Manual pairing**
   and paste the connection link.
3. The app syncs the project list and the selected project's tasks immediately.

Pairing tokens are app-scoped (`app_id=kanboard`) and can be revoked from
**Settings → Connection** on the web.

## Features

- Create a task with description, column, priority, due date, color, assignee
  and an optional first comment.
- Edit or close tasks, delete them, switch list/board/table views.
- Cached projects and tasks stay readable offline; the cache is reconciled
  without being wiped when a refresh fails.
- Home-screen widget shows the selected project's open tasks and refreshes on
  demand (or automatically after every sync/create/update/delete).
