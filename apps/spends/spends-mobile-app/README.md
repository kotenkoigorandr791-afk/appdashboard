# Spends mobile app

Companion React Native (Expo) client for AppDashboard Spends.

## Features

- Offline-first local ledger (SQLite)
- QR / deep-link pairing (`spends://auth?endpoint=&token=`)
- Bidirectional sync with `/api/spends` (`/sync` pull & push, signature dedup)
- Wi‑Fi-only or mobile-data sync toggle
- Google Wallet notification capture (Android NotificationListener)
- GlitchTip monitoring (`@sentry/react-native`)

## Develop

```bash
pnpm install
pnpm prebuild
pnpm android
```

Point the emulator at the host Docker stack via `http://10.0.2.2/api/spends`
(or the LAN IP shown in the Spends web pairing modal).

Native modules require a **dev build** — Expo Go is not supported.
