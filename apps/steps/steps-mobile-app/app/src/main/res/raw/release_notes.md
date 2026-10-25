## v1.10.0 - 2026-10-25

* 👤 New Settings screen with your personal data — name, email, daily step goal, height, weight, stride length and date of birth can now be viewed and edited from the app
* ⚙️ All app settings moved onto their own Settings screen, reachable with one tap from the profile tab
* 🐎 Faster, lighter server sync: profile and settings requests no longer scan the whole user table, so the dashboard stays responsive with many accounts syncing at once
* 📋 Logs screen cleaned up: the search field no longer squeezes the header, and an empty state explains when there is nothing to show yet

## v1.9.34 - 2026-09-29

* 🕒 Fixed health data landing on the wrong day for users east of UTC — distances, calories, sleep and pulse are now counted on the day they happened
* 👤 The Settings screen shows the default profile icon when no account is connected

## v1.9.33 - 2026-09-29

* 📊 Fixed wrong daily step counts — some days showed the value from the neighbouring day

## v1.9.32 - 2026-09-29

* 📊 Statistics now always comes from your server account when one is connected — the app no longer mixes in unsynced phone data
* 📱 Phone data is shown only when no account is connected

## v1.9.30 - 2026-09-25

* 📱 Statistics and Home screens now scroll properly on narrow screens — the update button is always reachable
* 🔄 Added a one-tap "Update now" button on Home
* 📊 Charts keep history from previous days stored on the device even when fresh health data is unavailable
* 🔐 Update signature is now consistent across all builds so updates install without conflicts

## v1.9.29 - 2026-09-23

* What's new notes are now shown in your language

## v1.9.28 - 2026-09-21

* Sync conflicts are now resolved automatically when you chose to remember a decision — no more repeated dialogs for the same conflict
* More reliable background sync that follows your schedule

## v1.9.25 - 2026-09-21

* The app now asks for notification and health permissions right after updating

## v1.9.24 - 2026-09-17

* Added in-app auto-update: the app can check for and install new versions from the server

## v1.9.23 - 2026-09-17

* New AMOLED dark theme for deeper blacks and lower battery use

## v1.9.22 - 2026-09-16

* Charts now work offline using health data saved on the device
* Fixed the sync timer anchor so the countdown stays accurate

## v1.9.20 - 2026-09-12

* Added a server history chart
* Added a sync timer and connection tests
* Added a resolution dialog when step data conflicts are detected
