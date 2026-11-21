# AppDashboard Documentation

**Self-hosted personal dashboard** for file storage, media, home automation, health tracking, finance, recipes, and external app integration — with a unified API and MCP server for AI assistants.

---

## Quick Links

| Section | Description |
|---------|-------------|
| [Getting Started](getting-started.md) | Install, configure, first login |
| [Core Concepts](core-concepts.md) | Authentication, app access, sharing, languages |
| [Services Reference](services/) | All apps: functions, API endpoints, web routes |
| [API Reference](api-reference.md) | Unified API specs, errors, pagination, WebSocket |
| [User Guides](user-guides/) | Task-oriented how-tos |
| [Admin Guides](admin-guides/) | Operations, security, backup, performance |
| [Troubleshooting](troubleshooting.md) | Common issues & solutions |

---

## Services Overview

| Service | Purpose | Web UI | API Base | Auth |
|---------|---------|--------|----------|------|
| [Cloud](services/cloud.md) | Files, WebDAV, OnlyOffice, transcription, translation | `/apps/cloud/` | `/public-api/v1/cloud` | Bearer + app access |
| [Films](services/films.md) | Movie library, streaming, metadata | `/apps/films/` | `/public-api/v1/films` | Bearer + app access |
| [Smart Home](services/smart-home.md) | IoT devices, MQTT, scenes, WebSocket real-time | `/apps/smart_home/` | `/public-api/v1/smart_home` | Bearer + app access |
| [Steps](services/steps.md) | Step counter, Health Connect sync, backups | `/apps/steps/` | `/public-api/v1/steps` | Bearer + app access |
| [Spends](services/spends.md) | Expense tracking, categories, reports | `/apps/spends/` | `/public-api/v1/spends` | Bearer + app access |
| [Recipes](services/recipes.md) | Recipe management, meal planning, shopping lists | `/apps/recipes/` | `/public-api/v1/recipes` | Bearer + app access |
| [Integrations](services/integrations.md) | SSO/launch for Dawarich, Gitea, Sentry, Immich, Kanboard, Lopaka | `/apps/{name}/` | `/public-api/v1/integrations` | Bearer + app access |
| [Admin](services/admin.md) | Users, Docker, shell, backup, performance, database | `/shell/*`, `/database` | `/public-api/v1/admin`... | Admin only |
| [Auth & OAuth](services/auth.md) | Login, registration, OAuth providers, desktop auth, tokens | `/login`, `/oauth/*` | `/public-api/v1/auth`, `/public-api/v1/oauth` | Session / Bearer / Device ID |
| [MCP Server](services/mcp.md) | Model Context Protocol for AI assistants (Claude, Cursor) | `/mcp` | `/mcp` (JSON-RPC) | OAuth client credentials |
| [Public Sharing](services/sharing.md) | Share links, WebDAV public access, Sharepoint viewer | `/s/{token}`, `/sharedFile` | — | Optional password |

---

## Architecture at a Glance

```
┌─────────────────────────────────────────────────────────────┐
│                      AppDashboard                           │
├──────────────┬──────────────┬──────────────┬────────────────┤
│   Web UI     │   Public API │   WebDAV     │   MCP Server   │
│  (PHP/JS)    │  (REST/WS)   │  (RFC 4918)  │  (JSON-RPC)    │
└──────┬───────┴──────┬───────┴──────┬───────┴───────┬────────┘
       │              │              │               │
       ▼              ▼              ▼               ▼
┌─────────────────────────────────────────────────────────────┐
│                    Shared Core (PHP)                        │
│  Auth • Routing • i18n • Config • Database • Logging        │
└─────────────────────────────────────────────────────────────┘
```

---

## Technology Stack

- **Runtime**: PHP 8.3 + Nginx (Docker)
- **Database**: PostgreSQL 16
- **Cache/Queue**: Redis
- **Frontend**: Vanilla JS, Tailwind CSS
- **Real-time**: WebSocket (Ratchet)
- **File sync**: WebDAV (SabreDAV), OnlyOffice Document Server
- **AI Integration**: MCP (Model Context Protocol) server
- **Container**: Docker Compose (app + db + redis + onlyoffice)

---

## Default Credentials (Fresh Install)

| Role | Email | Password |
|------|-------|----------|
| Admin | `root@root` | `12345T` |

> **Change immediately after first login.**

---

## Support

- **Issues**: GitHub Issues
- **API Problems**: Check [API Reference](api-reference.md) → Error Format
- **Logs**: `docker compose logs -f appdashboard`
- **Health Check**: `GET /health` (returns 200 OK)