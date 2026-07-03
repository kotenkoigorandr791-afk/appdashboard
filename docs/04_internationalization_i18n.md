# Internationalization (i18n) Documentation

## Overview

The ServerKherson platform supports 4 languages through JSON-based translation files located in the `i18n/` directory.

## Supported Languages

| Code | Language | File |
|------|----------|------|
| `en` | English | `i18n/en.json` |
| `uk` | Ukrainian | `i18n/uk.json` |
| `ru` | Russian | `i18n/ru.json` |
| `be` | Belarusian | `i18n/be.json` |

## Language Selection

Languages are selected via URL parameter:
```
?lang=en    // English
?lang=uk    // Ukrainian
?lang=ru    // Russian
?lang=be    // Belarusian
```

Default language: **English** (`en`)

## Usage in PHP

```php
// Load translations
$lang = isset($_GET['lang']) && isset($translations[$_GET['lang']]) 
    ? $_GET['lang'] 
    : 'en';
$t = $translations[$lang];

// Use translation
echo $t['welcome_message'];
```

## Usage in JavaScript

```javascript
// Cloud app example
const translations = {
    en: { /* ... */ },
    uk: { /* ... */ },
    ru: { /* ... */ },
    be: { /* ... */ }
};

// Get current language from URL or config
const lang = new URLSearchParams(window.location.search).get('lang') || 'en';

// Use translation
document.getElementById('title').textContent = translations[lang]['appTitle'];
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

#### User Interface
- `home`, `settings`, `save`, `cancel`, `delete`
- `search`, `refresh`, `back`, `close`
- `name`, `size`, `date`, `status`

#### File Management
- `fm_title`, `fm_new_folder`, `fm_new_file`
- `fm_rename`, `fm_delete`, `fm_upload`, `fm_download`
- `fm_copy_clipboard`, `fm_trash_title`

#### Admin Panel
- `admin_panel_title`, `user_management`, `users_list`
- `email_settings`, `google_client_id`, `smtp_host`
- `backup_dashboard`, `ssh_terminal`

#### Themes & Appearance
- `theme`, `theme_light`, `theme_dark`, `theme_auto`
- `color`, `change_theme`, `change_color`

#### User Roles
- `guest`, `manager`, `admin`
- `user_role`, `change_role`

#### Messages & Notifications
- `password_changed`, `photo_updated`, `user_deleted`
- `registration_success`, `password_reset_success`

#### One-Time PIN (OTP)
- `otp_page_title`, `otp_submit`, `otp_generate`
- `otp_your_pin`, `otp_expires_in`

#### Backup & SSH
- `backup_create`, `backup_restore`, `backup_download`
- `ssh_host`, `ssh_user`, `ssh_port`, `ssh_profile`

## Per-App Translations

Some apps have their own translation files:

### Cloud App (`apps/cloud/assets/i18n/`)
- App-specific file management terms
- Editor-related translations
- AI assistant strings

### Films App (`apps/films/translations.php`)
- Movie-related terms
- Comment system strings

### Performance App (`apps/performance/translations.php`)
- Server metrics labels
- Monitoring dashboard terms

### Other Apps
Each app may have `translations.json` or `translations.php` with app-specific strings.

## Best Practices

1. **Always provide fallback**: If a translation key is missing, fall back to English
2. **Use consistent keys**: Same concept should use the same key across all files
3. **Escape output**: Always escape translated strings when outputting to HTML
4. **Keep keys descriptive**: Use `user_profile_settings` instead of `ups`
5. **Update all languages**: When adding new keys, update all 4 language files

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
    "delete": "Delete",
    "search": "Search",
    "theme": "Theme",
    "theme_light": "Light",
    "theme_dark": "Dark",
    "language": "Language",
    "admin_panel_title": "Admin Panel",
    "user_management": "User Management",
    "backup_dashboard": "Backup Dashboard",
    "ssh_terminal": "SSH Terminal"
}
```

## Adding a New Language

1. Create new JSON file: `i18n/{code}.json`
2. Copy structure from `en.json`
3. Translate all values
4. Update language validation in PHP files:
   ```php
   $safeLang = in_array($lang, ['en','ru','uk','be','new'], true) ? $lang : 'en';
   ```
5. Update language selector in UI components
