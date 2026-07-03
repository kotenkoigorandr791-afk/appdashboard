# ServerKherson Documentation Index

Welcome to the ServerKherson project documentation. This folder contains comprehensive documentation for all components of the platform.

## Documentation Files

### Core Documentation

| File | Description |
|------|-------------|
| **[00_documentation_index.md](00_documentation_index.md)** | This file - documentation index with quick reference |
| **[01_project_overview_and_architecture.md](01_project_overview_and_architecture.md)** | High-level project overview, architecture, technology stack, and security features |
| **[02_root_level_files_and_core_system.md](02_root_level_files_and_core_system.md)** | Detailed documentation of root-level PHP files, configuration, and assets |
| **[03_applications_directory_summary.md](03_applications_directory_summary.md)** | Summary of all 10 sub-applications in the `apps/` directory |
| **[04_internationalization_i18n.md](04_internationalization_i18n.md)** | Internationalization system documentation (4 supported languages) |
| **[05_webssh_terminal.md](05_webssh_terminal.md)** | WebSSH WebSocket terminal application documentation |
| **[06_detailed_applications_documentation.md](06_detailed_applications_documentation.md)** | Comprehensive documentation overview for each of the 10 apps |
| **[07_project_structure.md](07_project_structure.md)** | **Complete project file tree with detailed structure** |

### Application-Specific Documentation

| File | Description | Lines |
|------|-------------|-------|
| **[08_cloud_app_detailed.md](08_cloud_app_detailed.md)** | Cloud Storage - Full documentation with API, database, features | ~15,000 |
| **[09_films_app_detailed.md](09_films_app_detailed.md)** | Films/Movie Manager - Complete documentation | ~4,000 |
| **[10_map_app_detailed.md](10_map_app_detailed.md)** | Maps Application - Full documentation | ~500 |
| **[11_media_viewer_app_detailed.md](11_media_viewer_app_detailed.md)** | Media Viewer/Gallery - Complete documentation | ~1,300 |
| **[12_performance_app_detailed.md](12_performance_app_detailed.md)** | Performance Monitor - Full documentation | ~2,400 |
| **[13_recipes_app_detailed.md](13_recipes_app_detailed.md)** | Recipe Manager - Complete documentation | ~2,800 |
| **[14_spends_app_detailed.md](14_spends_app_detailed.md)** | Expense Tracker - Full documentation | ~2,700 |
| **[15_steps_app_detailed.md](15_steps_app_detailed.md)** | Steps Counter - Complete documentation with OCR | ~3,400 |
| **[16_tft_generator_app_detailed.md](16_tft_generator_app_detailed.md)** | TFT Generator - Full documentation | ~1,700 |
| **[17_viewer_app_detailed.md](17_viewer_app_detailed.md)** | Network Viewer - Complete documentation | ~1,700 |

## Quick Reference

### Project Structure

```
/mnt/server/
├── docs/                      # This documentation folder
├── index.php                  # Main entry point
├── db.php                     # Database layer
├── apps/                      # 10 sub-applications
│   ├── cloud/                 # Cloud storage (main app)
│   ├── films/                 # Movie manager
│   ├── map/                   # Maps application
│   ├── media_viewer/          # Media gallery
│   ├── performance/           # Server monitor
│   ├── recipes/               # Recipe manager
│   ├── spends/                # Expense tracker
│   ├── steps/                 # Steps counter
│   ├── tft-generator/         # TFT code generator
│   └── viewer/                # Network device viewer
├── i18n/                      # Translation files (EN/UK/RU/BE)
├── webssh/                    # SSH terminal application
└── uploads/                   # User uploads
```

### Technology Stack

| Component | Technology |
|-----------|------------|
| Backend | PHP 8+ |
| Database | PostgreSQL |
| Frontend | HTML5, CSS3, JavaScript |
| 3D Rendering | Three.js |
| Code Editor | Monaco Editor, CodeMirror |
| SSH | phpseclib + Ratchet WebSocket |
| Email | PHPMailer |
| OAuth | Google API Client |

### Key Features

- **Authentication**: Email/password, Google OAuth, One-Time PIN (OTP)
- **Authorization**: Role-based access (guest/manager/admin)
- **File Management**: Cloud storage with editors, sharing, sync
- **3D Viewing**: Support for 3MF, STL, OBJ, FBX, DXF, DWG formats
- **AI Integration**: AI assistant with LM Studio proxy
- **Server Monitoring**: Real-time CPU, memory, disk, network metrics
- **SSH Terminal**: Browser-based SSH access with encrypted credentials
- **Internationalization**: 4 languages (English, Ukrainian, Russian, Belarusian)

### Database Schema

Main tables in `PostgreSQL appdashboard (/opt/AppDashboard/data/db.env)`:

| Table | Purpose |
|-------|---------|
| `users` | User accounts |
| `active_sessions` | Current sessions |
| `client_auth_tokens` | API access tokens |
| `one_time_pins` | OTP codes |
| `ip_lockouts` | Brute-force protection |
| `config` | Application configuration |
| `visit_stats` | App visit statistics |
| `shared_files` | Shared file metadata |

See [02_root_level_files_and_core_system.md](02_root_level_files_and_core_system.md) for complete schema.

## Applications Summary

| App | Lines of Code | Key Features |
|-----|---------------|--------------|
| **cloud** | ~15,000+ | File management, editors, 3D viewer, AI, transcription |
| **films** | ~3,700 | Movie catalog, comments |
| **spends** | ~2,600 | Expense tracking, Excel import |
| **performance** | ~1,600 | Server monitoring dashboard |
| **viewer** | ~500 | Network device viewer |
| **map** | ~400 | Maps with user uploads |
| **recipes** | ~400 | Recipe manager |
| **steps** | ~400 | Activity tracker with OCR |
| **tft-generator** | ~300 | TFT display code generator |
| **media_viewer** | ~300 | Photo/video gallery |

## Security

### Authentication Flow

1. User accesses application
2. Session validated against `active_sessions` table
3. 12-hour session timeout enforced
4. Client auth tokens available for API access (180 days)

### Protection Mechanisms

- IP-based brute-force protection
- OTP with attempt limits (3 attempts, then IP lockout)
- Encrypted SSH credentials (AES-256-GCM)
- SQLite file access blocked via `.htaccess`
- Role-based access control

### Environment Variables

Recommended environment variables for production:

```bash
# SSH credential encryption
export SSH_CREDENTIALS_CRYPT_KEY="your-secret-key"

# Backup encryption
export BACKUP_DASHBOARD_CRYPT_KEY="your-secret-key"

# LM Studio AI proxy
export LM_STUDIO_URL="http://localhost:1234"
```

## Development

### Installing Dependencies

```bash
# Root dependencies
composer install

# Cloud app dependencies
cd apps/cloud && composer install

# WebSSH dependencies
cd webssh && composer install
```

### Database Initialization

The database (`PostgreSQL appdashboard (/opt/AppDashboard/data/db.env)`) is automatically initialized on first access by `db.php`.

Default admin account:
- **Email**: `root@root`
- **Password**: `root`

### Adding a New Language

1. Create `i18n/{code}.json` with all translation keys
2. Update language validation in PHP files
3. Add language selector option in UI

See [04_internationalization_i18n.md](04_internationalization_i18n.md) for details.

### Adding a New App

1. Create `apps/{appname}/` directory
2. Add `index.php` with authentication check
3. Add `name.json` with app name translations
4. Register app in main routing (`index.php`)

## API Reference

### Client Auth Token API

```http
POST /api/client-auth/
Content-Type: application/json

{
    "email": "user@example.com",
    "device_id": "unique-device-id",
    "device_name": "My Phone",
    "platform": "android"
}

Response:
{
    "token": "abc123...",
    "expires_at": 1234567890
}
```

### Cloud Storage API

```http
GET  /apps/cloud/api.php?action=files
POST /apps/cloud/api.php?action=upload
DELETE /apps/cloud/api.php?action=delete
POST /apps/cloud/api.php?action=rename
```

See [03_applications_directory_summary.md](03_applications_directory_summary.md) for complete API documentation.

### Performance Monitor API

```http
GET /performance/cpu
GET /performance/mem
GET /performance/diskio
GET /performance/network
GET /performance/uptime
```

## Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| Database locked | Check WAL mode, increase busy timeout |
| Session expires quickly | Verify session cookie settings |
| Upload fails | Check `upload_max_filesize` in php.ini |
| WebSocket disconnect | Check proxy configuration for WSS |
| OTP not sending | Verify SMTP configuration |

### Logs

Check these logs for debugging:

```bash
# Apache error log
tail -f /var/log/apache2/error.log

# PHP error log
tail -f /var/log/php/error.log

# SQLite WAL files (if locking issues)
ls -la *.sqlite3-wal
```

## Contributing

When contributing to this project:

1. Follow existing code style
2. Add translations for all 4 languages
3. Update documentation for new features
4. Test with different user roles
5. Ensure mobile responsiveness

## License

[Add license information here]

## Contact

[Add contact information here]
