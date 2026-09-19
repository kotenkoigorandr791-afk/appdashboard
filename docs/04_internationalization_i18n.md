# Internationalization (i18n) Documentation

## Overview

The ServerKherson platform supports **6 languages** through JSON-based translation files located in the `i18n/` directory.

## Supported Languages

| Code | Language | File |
|------|----------|------|
| `en` | English | `i18n/en.json` |
| `uk` | Ukrainian | `i18n/uk.json` |
| `ru` | Russian | `i18n/ru.json` |
| `be` | Belarusian | `i18n/be.json` |
| `pl` | Polish | `i18n/pl.json` |
| `cs` | Czech | `i18n/cs.json` |

### Per-app i18n

Some apps also maintain per-app translation files:
- `apps/viewer/i18n/` — contains per-language JSON files (e.g., `uk.json`)
- `apps/lopaka/` and others use inline translation helpers

## Language Selection

Languages are selected via URL parameter:
```
?lang=en    // English
?lang=uk    // Ukrainian
?lang=ru    // Russian
?lang=be    // Belarusian
?lang=pl    // Polish
?lang=cs    // Czech
```

Default language: **English** (`en`)

Language is stored in the session/Redis and persists across requests.

## Usage in PHP

```php
// Load translations (loaded into $t or $L global by bootstrap.php / i18n_helpers.php)
$lang = isset($_GET['lang']) && isset($translations[$_GET['lang']])
    ? $_GET['lang']
    : 'en';
$t = $translations[$lang];

// Use translation
echo $t['welcome_message'];

// Helper function
_L('welcome_message'); // translates and escapes for HTML output
```

## Usage in JavaScript

```javascript
// Translation keys are embedded by PHP into window.__I18N__
const lang = window.APP_LANG || 'en';
const t = window.__I18N__?.[lang] || window.__I18N__?.['en'] || {};

// Use translation
document.getElementById('title').textContent = t.appTitle;
```

## Translation Keys Structure

The translation files use a flat key-value structure:

```json
{
    "key": "Translated Value",
    "another_key": "Another Translated Value"
}
```

### Key Categories

#### Authentication & Login
- `login`, `logout`, `register`, `email`, `password`
- `login_with_google`, `forgot_password`, `reset_password`
- `verification_code`, `verify_email`
- `trusted_device`, `add_trusted_device`, `remember_me`

#### User Interface
- `home`, `settings`, `save`, `cancel`, `delete`
- `search`, `refresh`, `back`, `close`
- `name`, `size`, `date`, `status`

#### Apps Menu
- `cloud_app`, `films_app`, `dawarich_app`, `recipes_app`
- `spends_app`, `steps_app`, `viewer_app`, `kanboard_app`
- `smart_home_app`, `media_viewer_app`, `lopaka_app`
- `gitea_app`, `immich_app`

## Best Practices

1. **Always provide fallback**: If a translation key is missing, fall back to English
2. **Use consistent keys**: Same concept should use the same key across all files
3. **Escape output**: Always escape translated strings when outputting to HTML
4. **Keep keys descriptive**: Use `user_profile_settings` instead of `ups`
5. **Update all languages**: When adding new keys, update all **6** language files

## Example Translation File Structure

```json
{
    "appTitle": "File Space",
    "welcome_message": "Welcome to the Shell. Select an app from the menu.",
    "login": "Login",
    "logout": "Logout",
    "email": "Email",
    "password": "Password",
    "save": "Save",
    "cancel": "Cancel",
    "delete": "Delete"
}
```
