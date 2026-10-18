# Сборка StepMonitor APK

APK больше не собирается внутри Docker-контейнера appdashboard.

## Локальная сборка

1. Установить Android SDK (platform 35, build-tools 35.0.0) и JDK 17+
2. `cd apps/steps/steps-mobile-app`
3. `./gradlew assembleFossRelease` (или `assembleRelease`)
4. Подписать APK при необходимости
5. Скопировать подписанный APK в `downloads/stepmonitor.apk`
6. Обновить `downloads/stepmonitor.json` (`version`, `version_code`, `built_at`)

Либо: `bash deploy/scripts/build-stepmonitor-apk.sh` на машине с Android SDK.

## CI

- Workflow не собирает APK внутри контейнера appdashboard
- Готовый артефакт должен уже лежать в `downloads/stepmonitor.apk`
- Контейнер только раздаёт файл через `/api/steps/apk` (503, если файла нет)
