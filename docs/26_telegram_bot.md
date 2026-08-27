# Bot Service

**Location**: `/bot/`

## Overview

The bot service provides a Telegram bot interface for managing AppDashboard, monitoring server health, and managing users. It's implemented in Python 3.11+ (with Pillow for image generation).

## Features

- **Server monitoring**: Real-time CPU, memory, swap, disk, network metrics
- **Backup monitoring**: Duplicati backup job status with PNG charts
- **Docker monitoring**: Container status via docker-cli or Portainer API
- **Uptime monitoring**: Charts showing uptime check results for critical services
- **User management**: Add, edit, delete users, change roles, manage verification
- **Database management**: DB statistics, table listing, VACUUM
- **Error tracking**: Latest errors and top errors (10 most frequent)
- **Admin menus**: Full administrator menu with keyboard navigation
- **Configuration**: Token-based Telegram API, PG/SQLite database compatibility

## Files

```
bot/
├── bot.py              # Main Telegram bot service (82KB, 31139 lines)
├── bot_service.php     # PHP bridge helper for docker config, backup credentials
├── pg_compat.py        # PostgreSQL compatibility layer (empty placeholder)
├── requirements.txt    # Python dependencies (empty - uses system packages)
├── appdashboard-bot.service  # systemd service unit
└── tg_mini_auth.php    # Telegram Mini App auth handler
```

## Dependencies

- **Python**: 3.11+
- **Pillow** (PIL): For generating uptime charts and backup reports as PNG
- **Requests**: For HTTP API calls to Telegram
- **bcrypt**: Password hashing
- **psycopg2**: PostgreSQL connectivity layer

System packages:
- **DejaVuSans**: Font for PNG chart rendering
- **DejaVuSans-Bold**: Bold font
- **configparser** (stdlib): Service unit parsing

## Database Schema

### tg_bot_sessions

| Column | Type | Purpose |
|--------|------|---------|
| chat_id | TEXT | Telegram chat ID (primary key) |
| email | TEXT | Linked AppDashboard user email |
| authorized_at | INTEGER | Session creation timestamp |
| telegram_user_id | TEXT | Telegram user ID (linked account) |

### tg_bot_link_tokens

| Column | Type | Purpose |
|--------|------|---------|
| token_hash | TEXT | SHA256 hash of link token (primary key) |
| email | TEXT | User email (must be admin+verified) |
| created_by | TEXT | Creator's telegram chat ID (optional) |
| created_at | INTEGER | Token creation timestamp |
| expires_at | INTEGER | Token expiration timestamp |
| used_at | INTEGER | Link redemption timestamp (NULL if unused) |
| used_chat_id | TEXT | Chat ID that redeemed the token (optional) |

## Menus

### Administrator Menu Structure

1. **Monitoring**
   - Full statistics (`/stats`)
   - Quick status (`/status`)
   - Uptime charts (`/uptime_charts`) - sends PNG chart
   - 💾 Backup status (`/backup_status`)
   - 🐳 Docker status (`/docker_status`)
   - Back to main menu (`/menu_back_main`)

2. **Errors**
   - Latest errors (`/errors_recent`)
   - Top errors (`/errors_top`)
   - Back to main menu

3. **Users**
   - Users list (`/users_list`)
   - Find user (`/user_find`)
   - Add user (`/user_add`)
   - Back to main menu

4. **User Actions** (during user management)
   - Change role (`/user_change_role`)
   - Toggle verification (`/user_toggle_verify`)
   - Reset password (`/user_reset_password`)
   - Delete user (`/user_delete`)
   - Back to users menu

5. **Database** (root only)
   - DB statistics (`/db_stats`)
   - Tables list (`/db_tables`)
   - VACUUM (`/db_vacuum`)
   - Back to main menu

6. **Role Selection** (during user change role)
   - Role: Guest → `/set_role_0`
   - Role: Manager → `/set_role_1`
   - Role: Admin → `/set_role_2`

7. **Verification Selection** (during user toggle verify)
   - Verified: Yes → `/set_verified_1`
   - Verified: No → `/set_verified_0`

## Telegram Commands

### Navigation & Status

| Command | Description | Supports Aliases |
|---------|-------------|------------------|
| Monitoring | Full statistics | `monitoring` |
| Errors | Latest errors | `errors` |
| Users | Users management | `users` |
| Database | DB operations | `database` |
| Back to menu | Return to main | `back to menu` |
| Help | Show this message | `help` |
| Logout | Terminate session | `logout` |
| Status | Quick system status | `status` |
| Full statistics | Detailed metrics | `full statistics` |
| Quick status | Basic metrics | `quick status` |
| Uptime charts | PNG uptime charts | `uptime charts`, ` uptime графики`, `графики uptime` |
| 💾 Backup | Backup monitoring | `💾 backup` |
| 🐳 Docker | Docker monitoring | `🐳 docker` |
| Latest errors | Recent errors | `latest errors` |
| Top errors | Most frequent errors | `top errors` |
| Users list | List all users | `users list` |
| Find user | Search user by pattern | `find user` |
| Add user | Create new user | `add user` |
| Change role | Edit user role | `role: guest`, `role: manager`, `role: admin` |
| Toggle verify | Verify/unverify user | `verified: yes`, `verified: no` |
| Reset password | Reset user password | `reset password` |
| Delete user | Remove user (with confirmation) | `delete user` |
| Confirm delete | Confirm deletion | `confirm delete` |
| Cancel | Cancel edit | `cancel` |
| DB statistics | Database statistics | `db statistics` |
| Tables list | List database tables | `tables list` |
| Vacuum | Vacuum database file | `vacuum` |
| Role: Guest | Set role | `role: guest` |
| Role: Manager | Set role | `role: manager` |
| Role: Admin | Set role | `role: admin` |
| Verified: Yes | User verification | `verified: yes` |
| Verified: No | User verification | `verified: no` |
| Confirm delete user | Confirmation | `confirm delete user` |

## Translations Supported

- English (English)
- Ukrainian (Українська)
- Russian (Русский)

## Image Reports

The bot generates PNG images for:

1. **Uptime charts**: Shows uptime check history for multiple services (up to 6)
2. **Backup monitor report**: Shows backup job statuses and schedules

Images are rendered using Pillow with DejaVuSans fonts.

## Configuration

### Environment Variables

- **BOT_LOG_FILE**: Path to log file (default: `/var/log/bot/bot.log`)
- **DB_PATH**: Path to database (default: `/opt/AppDashboard/data/db.env`)

### Service Unit

The bot runs as a systemd service `appdashboard-bot.service`:

```ini
[Unit]
Description=AppDashboard Telegram bot

[Service]
Type=simple
User=YOUR_USER
WorkingDirectory=/opt/AppDashboard/bot
ExecStart=/opt/AppDashboard/bot/bot.py
Restart=on-failure
StandardOutput=append:/var/log/bot/bot.log
StandardError=append:/var/log/bot/bot.log
```

## Authentication Tokens

Tokens are stored in the dashboard's `config` table:

- **Key**: `tg_bot_token`
- **Value**: Telegram Bot API Bearer token
- **Cache**: 60-second in-memory cache (`TOKEN_CACHE_TTL`)

## Link Tokens

Clients can create shareable link tokens to authenticate without direct chat:

- **Format**: 8-12 hex characters (auto-formatted to `XXXX-XXXX`)
- **Hash**: SHA256 of uppercase-formatted token
- **Expiration**: Configurable
- **Requirements**: Admin role + verified user
- **Usage**: Redeem in any bot chat to link account

## Usage

### For Administrators

1. Bot automatically authenticates if session exists in `tg_bot_sessions`
2. Menu navigation via inline buttons
3. Click to navigate, respond with aliases for keyboard input (e.g., "Full statistics", "Quick status")

### For Users

1. Admin must generate link token (`/user_create_link_token` via PHP admin)
2. User shares token with bot
3. Bot interprets token and authenticates session
4. Limited to Status/Logout menu

## System Paths

- **Logs**: `/var/log/bot/bot.log`
- **Service**: `systemctl status appdashboard-bot.service`
- **Logs**: `journalctl -u appdashboard-bot.service -f`

## API Call Details

### Server Statistics

- CPU load (percentage)
- Memory (used/total, percentage)
- Swap (used/total, percentage)
- Disk usage (mount points)
- Network (interface, rx/tx in bytes/sec)
- Uptime (formatted)

### Backup Monitor

Queries Duplicati API:
- `/api/v1/serverstate`
- `/api/v1/backups`

### Docker Monitor

Queries either:
- `docker ps -a --format '{{.Names}}|{{.Status}}|{{.Image}}'` (CLI)
- Portainer API `/api/status`, `/api/endpoints`, `/api/endpoints/{id}/docker/containers/json`

Prioritizes CLI if available, falls back to Portainer.

### Uptime Charts

Queries PostgreSQL tables:
- `monitor_uptime_checks` (enabled checks)
- `monitor_uptime_results` (histogram per check)

Generates PNG chart showing:
- Check name + availability percentage
- Connection target
- Latest status
- Latency bars
- Time axis

## Related Documentation

- [01_project_overview_and_architecture.md](01_project_overview_and_architecture.md) - Project overview
- [22_public_api_reference.md](22_public_api_reference.md) - Public API for MCP authentication
- [Share file utils](28_share_file_utils.md) - File viewer module