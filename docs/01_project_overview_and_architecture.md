# ServerKherson - Project Overview

## Project Description

**ServerKherson** is a comprehensive PHP-based multi-application web platform that provides secure cloud storage, server management, and various integrated applications. It functions as a unified portal with authentication, file management, and multiple specialized apps.

## Architecture

### Technology Stack
- **Backend**: PHP 8+ with PostgreSQL database
- **Frontend**: HTML5, CSS3, JavaScript (vanilla + Three.js for 3D rendering)
- **Authentication**: Google OAuth, email/password, one-time PIN (OTP)
- **Package Manager**: Composer (PHP dependencies)
- **Web Server**: Apache (with mod_rewrite)

### Core Features
- Single entry point routing (`index.php`)
- Session-based authentication with 12-hour timeout
- Multi-tenant architecture with per-user data isolation
- REST APIs for client applications
- Internationalization (i18n) support: English, Ukrainian, Russian, Belarusian
- Role-based access control (guest/manager/admin)
- Client auth tokens for API access

## Directory Structure

```
/mnt/server/
├── index.php                    # Main entry point (routing, auth, UI)
├── db.php                       # Database layer (PostgreSQL connection & functions)
├── db_admin.php                 # Database admin interface
├── backup_dashboard.php         # Backup management interface
├── file_viewer.php              # File viewing/downloading handler
├── ssh_terminal_handler.php     # SSH terminal WebSocket handler
├── composer.json                # Root PHP dependencies
├── data/db.env                  # PostgreSQL credentials (appdashboard)
├── .htaccess                    # Apache URL rewriting & security rules
│
├── apps/                        # Sub-applications directory
│   ├── cloud/                   # Cloud Storage Manager (main app)
│   ├── films/                   # Movie Manager
│   ├── map/                     # Maps Application
│   ├── media_viewer/            # Media Gallery
│   ├── performance/             # Server Monitor
│   ├── recipes/                 # Recipe Manager
│   ├── spends/                  # Expense Tracker
│   ├── steps/                   # Activity/Steps Counter
│   ├── lopaka/           # TFT Display Code Generator
│   └── viewer/                  # Network Device Viewer
│
├── i18n/                        # Internationalization files
│   ├── en.json                  # English translations
│   ├── uk.json                  # Ukrainian translations
│   ├── ru.json                  # Russian translations
│   └── be.json                  # Belarusian translations
│
├── webssh/                      # SSH terminal WebSocket application
├── uploads/avatars/             # User avatar images
├── preview/                     # Screenshots for app descriptions
└── docs/                        # This documentation folder
```

## Security Features

- `.htaccess` blocks direct access to legacy SQLite archive files
- Session timeout (12 hours)
- OTP login support
- Brute-force protection (IP lockouts)
- Email verification for new users
- Role-based access (guest/manager/admin)
- Client auth tokens for API access
- Encrypted SSH credentials storage
- CORS headers for API endpoints

## Main Database Tables

The application uses a centralized PostgreSQL database (`appdashboard`, credentials in `/opt/AppDashboard/data/db.env`) with the following tables:

| Table | Purpose |
|-------|---------|
| `users` | User accounts with roles, preferences |
| `pending_users` | Users awaiting email verification |
| `active_sessions` | Current user sessions |
| `visit_stats` | Application visit statistics |
| `shared_files` | Shared file metadata |
| `reset_codes` | Password reset codes |
| `verification_tokens` | Email verification tokens |
| `config` | Application configuration (SMTP, OAuth, etc.) |
| `user_app_restrictions` | Per-user app access control |
| `cloud_user_settings` | Cloud storage quotas and sync settings |
| `one_time_pins` | OTP codes for login |
| `ip_lockouts` | Brute-force protection |
| `client_auth_tokens` | API access tokens |

## Authentication Flow

1. User accesses the application
2. `index.php` checks for active session
3. If no session, redirects to login/registration
4. Authentication via:
   - Email/password
   - Google OAuth
   - One-time PIN (OTP)
5. Session stored in `active_sessions` table
6. Client auth tokens available for API access (180-day expiry)

## Applications Overview

| App | Description |
|-----|-------------|
| **cloud** | Full-featured cloud storage with editors (code, markdown, LaTeX, photo), 3D model viewer, AI assistant, transcription |
| **films** | Movie collection manager with comments |
| **map** | Interactive maps with user uploads |
| **media_viewer** | Photo/video gallery with albums |
| **performance** | Server monitoring dashboard (CPU, memory, disk, network) |
| **recipes** | Recipe manager with product database |
| **spends** | Personal expense tracker with Excel import |
| **steps** | Activity/steps counter with OCR support |
| **lopaka** | TFT display code generator |
| **viewer** | Network device viewer |

## External Dependencies

### Root composer.json
- `phpmailer/phpmailer` - Email sending
- `google/apiclient` - Google OAuth/API integration

### apps/cloud composer.json
- `phpoffice/phpword` - Word document handling
- `phpoffice/phpspreadsheet` - Excel handling
- `phpoffice/phppresentation` - PowerPoint handling

### webssh composer.json
- `phpseclib/phpseclib` - SSH library
- `cboden/ratchet` - WebSocket server

### Frontend Libraries
- Three.js (3D rendering)
- Monaco Editor (code editing)
- CodeMirror (text editing)
- Various 3D loaders (3MF, STL, OBJ, FBX, DXF)
- highlight.js (syntax highlighting)
- Plyr (media player)
- QRCode.js (QR code generation)
