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
