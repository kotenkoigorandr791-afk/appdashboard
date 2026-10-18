# Project Structure - Complete File Tree

**ServerKherson** is a comprehensive PHP-based multi-application web platform. This document provides a complete structural overview of the entire project.

---

## Root Directory Structure

```
/mnt/server/
├── index.php                          # Root entry: sharedFile + webdav proxy → project/index.php
├── project/index.php                  # Main entry point (routing, auth, UI shell)
├── db.php                             # PostgreSQL database abstraction layer
├── db_admin.php                       # Database admin interface with SQL console
├── backup_dashboard.php               # Backup management with AES-256-GCM encryption
├── file_viewer.php                    # File viewing/downloading handler
├── ssh_terminal_handler.php           # SSH terminal WebSocket handler
├── doc_viewer.php                     # Document viewer handler
├── composer.json                      # Root PHP dependencies
├── composer.lock                      # Composer lock file
├── data/                              # Symlink to /opt/AppDashboard/data/
│   └── db.env                         # PostgreSQL credentials (appdashboard)
├── default_avatar.png                 # Default user avatar image
├── google.png                         # Google OAuth icon
├── google7cb515c0a41e4548.html        # Google Search Console verification
├── icon.png                           # Main application icon
├── home.html                          # Static home page
├── privacy.html                       # Privacy policy page
├── privacy-terms.png                  # Privacy terms image
├── .htaccess                          # Kept for reference; nginx used in production
├── client_apps_architecture.md        # Client applications architecture documentation
├── php_errors.log                     # PHP error log file
│
├── docs/                              # Documentation directory
│   ├── 00_documentation_index.md
│   ├── 01_project_overview_and_architecture.md
│   ├── 02_root_level_files_and_core_system.md
│   ├── 03_applications_directory_summary.md
│   ├── 04_internationalization_i18n.md
│   ├── 05_webssh_terminal.md
│   ├── 06_detailed_applications_documentation.md
│   ├── 07_project_structure.md
│   ├── 08_cloud_app_detailed.md
│   ├── 09_films_app_detailed.md
│   ├── 10_dawarich_app_detailed.md
│   ├── 11_media_viewer_app_detailed.md
│   ├── 12_performance_app_detailed.md
│   ├── 13_recipes_app_detailed.md
│   ├── 14_spends_app_detailed.md
│   ├── 15_steps_app_detailed.md
│   ├── 16_lopaka_app_detailed.md
│   ├── 17_viewer_app_detailed.md
│   ├── 25_appdashboard_mcp.md
│   ├── 26_telegram_bot.md
│   ├── 27_public_api.md
│   ├── 28_share_file_utils.md
│   └── 29_waf.md
│
├── nginx/                             # Nginx configuration
│   ├── appdashboard.conf              # Rendered main site config
│   └── appdashboard.conf.template     # Template with variable substitution
│
├── deploy/                            # Deployment scripts and configs
│   ├── scripts/
│   │   ├── build-appdashboard-mcp.sh
│   │   └── ...
│   └── ...
│
├── lib/                               # Shared PHP libraries
│   ├── bootstrap.php                  # Early initialization
│   ├── core.php                       # Core functions
│   ├── i18n_helpers.php               # Internationalization
│   ├── app_domains.php                # Subdomain URL generation
│   ├── app_session.php                # Session management
│   ├── redis.php                      # Redis connection
│   ├── db_auth_security.php           # DB-based auth security
│   ├── pg_*.php / pg_*.py             # PostgreSQL compatibility
│   ├── pgsql_compat.php               # PostgreSQL SQL emulation
│   ├── app_db.php                     # Database wrapper
│   ├── portainer_client.php           # Portainer management
│   ├── sentry_bootstrap.php           # Sentry error reporting
│   ├── public_api.php                 # Public API helpers
│   └── public_api_auth.php            # Public API auth
│
├── project/                           # Main application core
│   ├── index.php                      # Main entry point
│   ├── bootstrap.php                  # Bootstrap
│   ├── core.php                       # Core functions
│   ├── i18n_helpers.php
│   ├── oauth.php                      # OAuth providers
│   ├── oauth_login.php                # OAuth login handlers
│   ├── routes.php                     # Route dispatcher
│   ├── post_handlers.php              # POST handlers
│   ├── runtime.php                    # Runtime config
│   ├── wallpapers.php                 # Wallpaper management
│   ├── weather_settings.php           # Weather settings
│   ├── main.php                       # Main UI shell (700K lines)
│   ├── mcp_download.php               # MCP package download
│   ├── mobile_chrome.php              # Mobile Chrome helpers
│   ├── browser-icons.php              # Browser icons
│   ├── onboarding/                    # User onboarding
│   ├── views/                         # View templates
│   ├── assets/                        # Static assets
│   ├── bin/                           # CLI tools
│   └── performance/                   # Performance monitor (moved from apps/)
│       ├── bin/monitor_collector.php
│       ├── controllers/
│       ├── core/
│       ├── deploy/
│       ├── i18n/
│       ├── views/
│       ├── server_monitor.php
│       ├── monitor_stats.php
│       ├── index.php
│       ├── style.css
│       ├── translations.php
│       ├── description.json
│       ├── icon.svg
│       └── name.json
│
├── apps/                              # Sub-applications (13 apps)
│   ├── cloud/                         # Cloud Storage Manager (flagship)
│   │   ├── index.php, api.php, ai.php
│   │   ├── api/actions/
│   │   ├── assets/
│   │   ├── i18n/
│   │   ├── includes/
│   │   ├── js/
│   │   ├── storage/
│   │   ├── temp/
│   │   ├── webdav.php
│   │   └── ...
│   ├── dawarich/                      # Maps / SSO bridge
│   ├── films/                         # Movie Manager
│   │   ├── controllers/
│   │   ├── core/
│   │   ├── comments/
│   │   └── ...
│   ├── gitea/                         # Git Service (SSO bridge)
│   ├── immich/                        # Photo/Video Backup (SSO bridge)
│   ├── kanboard/                      # Kanban Boards
│   │   ├── app/
│   │   ├── plugins/AppDashboard/
│   │   ├── assets/
│   │   └── ...
│   ├── lopaka/                        # TFT Display Generator
│   ├── media_viewer/                  # Media Gallery
│   ├── recipes/                       # Recipe Manager
│   ├── smart_home/                    # Smart Home Controller
│   │   ├── core/
│   │   ├── views/
│   │   ├── SmartHomeWsHandler.php
│   │   ├── mqtt_*.php
│   │   ├── ws_*.php
│   │   └── smart-home-ws.service
│   ├── spends/                        # Expense Tracker
│   ├── steps/                         # Steps Counter
│   └── viewer/                        # Network Device Viewer
│
├── public-api/                        # Public REST API
│   ├── index.php                      # API router
│   └── routes/
│       ├── integrations.php
│       ├── oauth/
│       └── steps/
│
├── bot/                               # Telegram bot integration
│   ├── bot.py                         # Main bot logic (Python)
│   ├── bot_service.php                # PHP service wrapper
│   ├── tg_mini_auth.php               # Telegram Mini App auth
│   ├── pg_compat.py
│   ├── requirements.txt
│   └── appdashboard-bot.service
│
├── webssh/                            # WebSocket SSH terminal
│   ├── server.php                     # WebSocket server entrypoint
│   ├── SshWsHandler.php               # Core SSH logic
│   ├── composer.json
│   ├── composer.lock
│   └── vendor/
│
├── i18n/                              # Global translation files (6 languages)
│   ├── en.json
│   ├── uk.json
│   ├── ru.json
│   ├── be.json
│   ├── pl.json
│   └── cs.json
│
├── assets/                            # Global static assets
│   ├── background_*.jpg
│   └── ...
│
├── appdashboard-mcp/                  # MCP server package
│   ├── package.json
│   ├── tsconfig.json
│   ├── src/
│   └── scripts/
│
├── tests/                             # Playwright E2E tests
│   ├── package.json
│   ├── config/playwright.config.ts
│   └── specs/
│
├── .env                               # Runtime configuration
├── .env.example                       # Example configuration
├── Dockerfile                         # Docker build
├── docker-compose.yml                 # Docker Compose
├── docker-entrypoint.sh               # Container entrypoint
├── supervisord.conf                   # Supervisor config
├── README.md
├── README.docker.md
└── AGENTS.md
```
