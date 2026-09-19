# Telegram Bot Integration Documentation

**Path:** `bot/`  
**Type:** Bot Integration (Python + PHP)  
**Description:** Telegram bot integration for AppDashboard with user management and service monitoring.

---

## Overview

The Telegram bot provides a command-line interface to AppDashboard through Telegram. It supports user management, service monitoring, and system administration via Telegram messages.

## File Structure

```
bot/
├── bot.py                       # Main bot logic (Python 3.11+)
├── bot_service.php              # PHP service wrapper
├── tg_mini_auth.php             # Telegram Mini App authentication
├── pg_compat.py                 # PostgreSQL compatibility layer
├── requirements.txt             # Python dependencies
└── appdashboard-bot.service     # systemd service file
├── .venv/                       # Python virtual environment (Pillow, psycopg2, bcrypt, requests)
```

## Configuration

The bot uses PostgreSQL credentials from `/opt/AppDashboard/data/db.env` and connects to AppDashboard via its internal API.

### Environment Variables

- `BOT_LOG_FILE` - Path to log file (default: `/var/log/bot/bot.log`)
- `TELEGRAM_BOT_TOKEN` - Telegram bot token
- `TELEGRAM_CHAT_ID` - Target chat ID for notifications
- `DB_PATH` - PostgreSQL database path (default: `/opt/AppDashboard/data/db.env`)

## Python Dependencies

| Package | Purpose |
|---------|---------|
| **Pillow (PIL)** | Image generation for uptime charts and backup reports |
| **psycopg2** | PostgreSQL database access |
| **bcrypt** | Password hashing |
| **requests** | HTTP client for API calls |

### Fonts

Images are rendered using Pillow with DejaVuSans fonts.

## Features

### Uptime Monitoring
- Charts showing uptime check results for critical services
- Service status tracking (online/offline)
- Historical uptime statistics
- Backup report generation with Pillow charts

### User Management
- List registered users
- Create/delete users via bot commands
- Role management (admin/manager/guest)
- Session management

### System Administration
- Service status checks
- Restart services via bot commands
- System information retrieval
- Log access and error reporting

### Telegram Mini App Integration
- `tg_mini_auth.php` handles Telegram Mini App authentication
- OAuth flow for Telegram users
- Session-based authentication

## Data Storage

Bot data stored in PostgreSQL `bot` schema:

```sql
Schema: bot
├── tg_bot_sessions    # Telegram session mapping
├── tg_users           # Telegram user profiles
└── bot_commands       # Command history
```

## Security Features

- Token-based authentication for bot commands
- Rate limiting on bot API calls
- Input validation for all commands
- Password hashing with bcrypt
- Session management with expiry

## Deployment

### Systemd Service

`appdashboard-bot.service` manages the bot process:

```ini
[Unit]
Description=AppDashboard Telegram Bot
After=network.target

[Service]
Type=simple
User=appdashboard
WorkingDirectory=/path/to/bot
ExecStart=/path/to/bot/.venv/bin/python bot.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

### Virtual Environment

The bot uses a Python virtual environment at `bot/.venv/` with all dependencies installed.

## API Integration

The bot communicates with AppDashboard through:

1. **Internal PHP API** - Via `bot_service.php` for database operations
2. **PostgreSQL Direct** - Via `psycopg2` and `pg_compat.py`
3. **Telegram Bot API** - Via `requests` library

## Troubleshooting

### Common Issues

1. **Bot not responding**: Check systemd service status and token validity
2. **Database errors**: Verify PostgreSQL credentials in `db.env`
3. **Permission errors**: Ensure bot user has write access to log directory

### Debug Mode

Set `BOT_LOG_FILE` to a writable path for detailed logging.

## Deployment Notes

- Python 3.11+ required
- Virtual environment recommended
- PostgreSQL credentials required
- Telegram bot token required
