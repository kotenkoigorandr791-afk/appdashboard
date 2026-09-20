# ServerKherson - Project Overview

## Project Description
**ServerKherson** is a comprehensive PHP-based multi-application web platform that provides secure cloud storage, server management, and various integrated applications. It functions as a unified portal with authentication, file management, and multiple specialized apps.

## Architecture
### Technology Stack
- **Backend**: PHP 8+ with PostgreSQL database
- **Frontend**: HTML5, CSS3, JavaScript (vanilla + Three.js for 3D rendering)
- **Authentication**: Google OAuth, email/password, one-time PIN (OTP), trusted devices, Telegram Mini App
- **Package Manager**: Composer (PHP dependencies)
- **Web Server**: nginx (Docker Compose)

### Core Features
- Single entry point routing (`project/index.php`); lightweight shared-file flow in root `index.php`
- Session-based authentication with 12-hour timeout; session cookies secured via HTTPS, HttpOnly, SameSite=Lax
- Multi-tenant architecture with per-user data isolation
- REST APIs for client applications; `/public-api/` prefix; `/api/steps` aliased to `/public-api/steps`
- Internationalization (i18n) support: English, Ukrainian, Russian, Belarusian, Polish, Czech (6 languages)
- Role-based access control (guest/manager/admin)
- Client auth tokens with 180-day expiry; device tracking
- Trusted device remember-me flow with cookie-based persistence

## Directory Structure
```
/mnt/server/
├── index.php                          # Root entry; sharedFile handling + webdav proxy
├── project/index.php                  # Main entry point (routing, auth, UI shell)
├── db.php                             # PostgreSQL database abstraction layer
├── db_admin.php                       # Database admin interface
├── backup_dashboard.php               # Backup management interface
├── file_viewer.php                    # File viewing/downloading handler
├── ssh_terminal_handler.php           # SSH terminal WebSocket handler
├── composer.json                      # Root PHP dependencies
├── data/db.env                        # PostgreSQL credentials (appdashboard)
├── .htaccess                          # Kept for reference; nginx used in production
├── .env                               # Configuration (BASE_DOMAIN, DB_PASSWORD, etc.)
├── nginx/                             # Nginx config rendered from template
│   ├── appdashboard.conf
│   └── appdashboard.conf.template
├── data/                              # Persistent data volume
│   └── db.env
├── apps/                              # Sub-applications directory (13 apps)
│   ├── cloud/                         # Cloud Storage Manager (main app)
│   ├── dawarich/                      # Maps / SSO bridge (renamed from map/)
│   ├── films/                         # Movie Manager
│   ├── gitea/                         # Git Service
│   ├── immich/                        # Photo/Video Backup
│   ├── kanboard/                      # Kanban Boards
│   ├── lopaka/                        # TFT Display Generator
│   ├── media_viewer/                  # Media Gallery
│   ├── recipes/                       # Recipe Manager
│   ├── smart_home/                    # Smart Home Controller
│   ├── spends/                        # Expense Tracker
│   ├── steps/                         # Steps Counter
│   └── viewer/                        # Network Device Viewer
├── public-api/                        # Public REST API (endpoints under /public-api/)
├── bot/                               # Telegram bot integration
├── webssh/                            # WebSocket SSH terminal
│   ├── server.php                     # SSH WebSocket handler
│   └── SshWsHandler.php               # Core SSH logic
├── backup_dashboard.php
├── doc_viewer.php
├── composer.lock
└── php_errors.log