# ServerKherson Documentation Index

## Documentation Files

### Core Documentation

| File | Description |
|------|-------------|
| **[00_documentation_index.md](00_documentation_index.md)** | Documentation index |
| **[01_project_overview_and_architecture.md](01_project_overview_and_architecture.md)** | Overview, architecture, stack, security |
| **[02_root_level_files_and_core_system.md](02_root_level_files_and_core_system.md)** | Root PHP, `project/`, `lib/`, config |
| **[03_applications_directory_summary.md](03_applications_directory_summary.md)** | Summary of all 13 apps and new components |
| **[04_internationalization_i18n.md](04_internationalization_i18n.md)** | i18n (6 languages: en, ru, uk, be, pl, cs) |
| **[05_webssh_terminal.md](05_webssh_terminal.md)** | WebSSH WebSocket terminal |
| **[06_detailed_applications_documentation.md](06_detailed_applications_documentation.md)** | Detailed overview of each app |
| **[07_project_structure.md](07_project_structure.md)** | Project file tree |

### Application-Specific Documentation

| File | Description |
|------|-------------|
| **[08_cloud_app_detailed.md](08_cloud_app_detailed.md)** | Cloud Storage |
| **[09_films_app_detailed.md](09_films_app_detailed.md)** | Films / Movie Manager |
| **[10_dawarich_app_detailed.md](10_dawarich_app_detailed.md)** | Dawarich (Maps) SSO bridge |
| **[11_media_viewer_app_detailed.md](11_media_viewer_app_detailed.md)** | Media Viewer / Gallery |
| **[12_performance_app_detailed.md](12_performance_app_detailed.md)** | Server Monitor (`project/performance/`) |
| **[13_recipes_app_detailed.md](13_recipes_app_detailed.md)** | Recipe Manager |
| **[14_spends_app_detailed.md](14_spends_app_detailed.md)** | Expense Tracker |
| **[15_steps_app_detailed.md](15_steps_app_detailed.md)** | Steps Counter |
| **[16_lopaka_app_detailed.md](16_lopaka_app_detailed.md)** | Lopaka (embedded display editor) |
| **[17_viewer_app_detailed.md](17_viewer_app_detailed.md)** | Network Viewer |
| **[18_gitea_sso_bridge.md](18_gitea_sso_bridge.md)** | Gitea SSO bridge |
| **[19_immich_sso_bridge.md](19_immich_sso_bridge.md)** | Immich SSO bridge |
| **[20_plane_sso_bridge.md](20_plane_sso_bridge.md)** | Plane SSO bridge |
| **[21_smart_home_app_detailed.md](21_smart_home_app_detailed.md)** | Smart Home (WS + MQTT) |

### System & Services Documentation

| File | Description |
|------|-------------|
| **[22_public_api_reference.md](22_public_api_reference.md)** | Public API routes and specifications |
├── docs/
├── index.php                  # Entry → project/index.php
├── db.php
├── project/                   # Core framework, OAuth, UI shell, performance/
├── lib/                       # DB layer, public API auth
├── appdashboard-mcp/           # MCP server for AI agents (Cursor, Claude, VS Code, OpenCode)
├── bots/                      # Bot scripts and integrations
├── public-api/                # REST API endpoints
├── share_file_utils/          # Shared file viewer module
├── webssh/                    # WebSSH terminal
├── apps/                      # 13 sub-applications
│   ├── cloud/
│   ├── dawarich/
│   ├── films/
│   ├── gitea/
│   ├── immich/
│   ├── lopaka/
│   ├── media_viewer/
│   ├── plane/
│   ├── recipes/
│   ├── smart_home/
│   ├── spends/
│   ├── steps/
│   └── viewer/
├── i18n/                      # en, ru, uk, be, pl, cs
├── home/
├── deploy/
└── uploads/
```

### Technology Stack

| Component | Technology |
|-----------|------------|
| Backend | PHP 8.3 |
| Backend-FS | Node.js 18+ (MCP server) |
| Database | PostgreSQL (+ SQLite-compat layer) |
| Frontend | HTML5, CSS3, JavaScript |
| Web server | nginx (Docker) |
| 3D | Three.js |
| Code editor | Monaco |
| SSH | phpseclib + Ratchet |
| Smart Home | Ratchet WS + Mosquitto MQTT |
| Email | PHPMailer |
| OAuth | Google, GitHub, bridge providers |
| AI Agents | Cursor, Claude Desktop, VS Code, OpenCode, Agora |
| MCP | stdio/HTTP transport for AI agents |

### Key Features

- Authentication: email/password, Google OAuth, OTP, Telegram Mini App
- Roles: guest / manager / admin
- Cloud storage with editors, AI, 3D, sharing
- SSO bridges: Gitea, Immich, Plane, Dawarich
- Smart Home: WebSocket + MQTT dual transport
- Server monitoring in `project/performance/`
- Public API (`public-api/`) with Bearer + device id
- MCP server for AI agents (Cursor, Claude, VS Code, OpenCode)
- Telegram bot service for server management
- Share file viewer module
- i18n: 6 languages (en, ru, uk, be, pl, cs)

### Applications Summary

| App | Purpose |
|-----|---------|
| **cloud** | Cloud storage, editors, 3D, AI, transcription |
| **films** | Movie catalog and comments |
| **dawarich** | Maps / location — OAuth bridge to Dawarich |
| **media_viewer** | Photo/video gallery (Immich-related) |
| **recipes** | Recipes and products |
| **spends** | Expense tracking |
| **steps** | Steps counter with OCR |
| **lopaka** | Embedded display graphics editor |
| **viewer** | Network device dashboard |
| **gitea** | SSO bridge to Gitea |
| **immich** | SSO bridge to Immich |
| **plane** | SSO bridge to Plane |
| **smart_home** | Smart home (WS + MQTT) |

Admin server monitor lives in `project/performance/` (not under `apps/`).

### Security

- Session validation via `active_sessions`
- IP lockouts / OTP limits
- AES-256-GCM for SSH and backup secrets
- Role-based app access
- Public API: Bearer token + `X-Device-Id`

### Development

```bash
composer install
cd apps/cloud && composer install
cd webssh && composer install
```

### AI Agents & MCP

**appdashboard-mcp** provides one MCP server that exposes tools for:
- Service shell (auth, users, apps, Docker, files, config, monitor, backup, commands, support)
- Spends app
- Cloud app
- Smart Home app
- Recipes app
- Steps counter
- Immich photo library
- Gitea git forge
- Plane project management

Install with one command via UI (Settings → MCP):

```bash
npx -y https://YOUR_HOST/mcp/appdashboard-mcp.tgz setup --agent cursor --url https://YOUR_HOST
```

Supported agents: Cursor, Claude Desktop, VS Code, OpenCode

### API

- Public API: `/public-api/` — see [22_public_api_reference.md](22_public_api_reference.md)
- Cloud: `apps/cloud/api.php`
- Performance: `project/performance/` + public-api `/performance/*`
- MCP HTTP server: `project/appdashboard-mcp/index.js` on port 8808

### Troubleshooting

| Issue | Check |
|-------|-------|
| Session | `active_sessions`, cookie settings |
| Upload | `upload_max_filesize` |
| WebSocket | nginx proxy for WSS, ports 8766 (smart home) |
| MQTT | Mosquitto + `mqtt_auth_sync.php` |
| MCP HTTP | Check `supervisord.conf` for `appdashboard-mcp-http` |

Prefer nginx/Docker logs under the deployment host, not Apache.