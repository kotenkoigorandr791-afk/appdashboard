# StepMonitor — мобильное приложение Android

## Сборка

### Требования

- JDK 17 (not JDK 25 — несовместим с текущим Gradle/Kotlin)
- Android SDK (API 34+)
- Переменная `JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64`

### Команды сборки

```bash
cd apps/steps/steps-mobile-app

# Debug (FOS — для установки через adb)
JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64 ./gradlew :app:assembleFossDebug

# Debug (Play Store flavor)
JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64 ./gradlew :app:assemblePlaystoreDebug

# Только компиляция Kotlin (проверка синтаксиса)
JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64 ./gradlew :app:compilePlaystoreDebugKotlin
```

APK будет в `app/build/outputs/apk/foss/debug/app-foss-debug.apk`.

### Установка на устройство

```bash
adb connect <IP>:<PORT>
adb install -r app/build/outputs/apk/foss/debug/app-foss-debug.apk
```

> **Важно**: flavor `playstore` не запускается при установке через adb — нужен только `foss`.

## Версия приложения

Версия задаётся в `app/build.gradle.kts`:

```kotlin
val appVersionMajor = 1
val appVersionMinor = 9
val appVersionPatch = 22
val appVersionCode = (appVersionMajor * 10000) + (appVersionMinor * 100) + appVersionPatch
```

**При каждом изменении кода необходимо поднимать `appVersionPatch` на 1** (или `appVersionMinor` для крупных изменений). Это критично:

- `versionCode` используется Android для определения, является ли установка обновлением
- Без поднятия версии `adb install -r` не обновит приложение корректно на некоторых устройствах
- Для Play Store — `versionCode` должен быть строго больше предыдущего

## Устройство разработки

Текущее тестовое устройство: Pixel 8, Android 17, Tailscale IP.

```bash
adb connect 100.120.132.28:34545
```

### What's new

For every release and new build, add customer-facing update notes to the `What's new` window so users clearly understand what changed after updating. Release notes are bundled with the app in `app/src/main/res/raw/release_notes.md` as `## vX.Y.Z - YYYY-MM-DD` sections followed by `* ` bullets, with translations in matching `res/raw-<locale>/release_notes.md` folders (de, es, fr, it, ja, ko, pl, pt, ru, ta, uk, zh, be, cs). Each new section must include current, user-visible information and concise icons/emoji at the start of bullets when they improve scanning in the app window. The window prefers the bundled section for the installed version (in the device locale) and falls back to the GitHub releases API only when no local section matches. Before installing on a device, verify that the `What's new` window opens after the app update and contains the current change list.

### Production updates

Before committing or merging StepMonitor changes to `main`, build the production download with `bash deploy/scripts/build-stepmonitor-apk.sh /home/ihor/apps-dashboard`, verify `downloads/stepmonitor.json` reports the new `version` and `version_code`, compare the new APK signature with the installed app, then test `adb install -r downloads/stepmonitor.apk` on the existing device without uninstalling. The production APK must keep the same signing certificate as installed user builds so updates install cleanly instead of failing with `INSTALL_FAILED_UPDATE_INCOMPATIBLE`.
