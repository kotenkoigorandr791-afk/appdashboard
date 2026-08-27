# appdashboard-mcp

**Version**: 1.0.0

## Overview

**appdashboard-mcp** is a Model Context Protocol (MCP) server that provides access to AppDashboard functionality from AI agent environments like Cursor, Claude Desktop, VS Code, OpenCode, and Agora. It exposes tools for the service shell and all apps (Spends, Cloud, Smart Home, Recipes, Steps, Immich, Gitea, Plane) through a single unified MCP package.

## Platform

AppDashboard hosts the MCP server prebuilt and served via HTTP (`/mcp/download`). Users install by copying one command from the UI (Settings → MCP) without needing to build locally.

## Features

- **Unified MCP server**: Single package exposes tools for service shell + all 13 apps
- **Auto-authentication**: Immich, Gitea, Plane API credentials managed automatically via dashboard integrations
- **Multi-agent support**: Compatible with Cursor, Claude Desktop, VS Code, OpenCode, Agora
- **HTTP transport**: Servers on port 8808 with WebSocket upgrade support
- **Per-user auth tokens**: JWT tokens stored in dashboard database, never exposed to tools
- **Shell integration**: Tools for service management, Docker, backup, config, monitoring

## Tool Groups

| Prefix | Service |
|--------|---------|
| *(shell)* | Auth, users, apps, Docker, files, config, monitor, backup, commands, support |
| `spends_*` / `expense_*` | Spends app |
| `cloud_*` | Cloud app |
| `smarthome_*` | Smart Home app |
| `recipes_*` | Recipes app |
| `steps_*` | Steps counter |
| `immich_*` | Immich photo library |
| `gitea_*` | Gitea git forge |
| `plane_*` | Plane project management |

All `steps_*`, `immich_*`, `gitea_*`, and `plane_*` tools are available to any authenticated user (no Admin role required).

## Installation

### Method 1: UI Setup (Recommended)

1. Log in to AppDashboard UI
2. Go to Settings → MCP
3. Copy command for your agent:

```bash
# Cursor
npx -y https://YOUR_HOST/mcp/appdashboard-mcp.tgz setup --agent cursor --url https://YOUR_HOST

# Claude Desktop
npx -y https://YOUR_HOST/mcp/appdashboard-mcp.tgz setup --agent claude --url https://YOUR_HOST

# VS Code
npx -y https://YOUR_HOST/mcp/appdashboard-mcp.tgz setup --agent opencode --url https://YOUR_HOST
```

4. Restart your agent
5. Chat with the dashboard (first messages will prompt for browser OAuth if not authenticated)

### Method 2: Configuration Files

**Claude Desktop** (`~/.config/claude/claude_desktop_config.json` or macOS `~/Library/Application Support/Claude/claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "appdashboard-mcp": {
      "command": "npx",
      "args": ["-y", "https://YOUR_HOST/mcp/download"],
      "env": { "APPDASHBOARD_URL": "https://YOUR_HOST" }
    }
  }
}
```

**Cursor** (`.cursor/mcp.json` or `~/.cursor/mcp.json`):

```json
{
  "mcpServers": {
    "appdashboard-mcp": {
      "command": "npx",
      "args": ["-y", "https://YOUR_HOST/mcp/download"],
      "env": { "APPDASHBOARD_URL": "https://YOUR_HOST" }
    }
  }
}
```

**VS Code** (`.vscode/mcp.json`):

```json
{
  "servers": {
    "appdashboard-mcp": {
      "command": "npx",
      "args": ["-y", "https://YOUR_HOST/mcp/download"],
      "env": { "APPDASHBOARD_URL": "https://YOUR_HOST" }
    }
  }
}
```

**OpenCode** (`opencode.json`):

```json
{
  "mcp": {
    "appdashboard-mcp": {
      "command": "npx",
      "args": ["-y", "https://YOUR_HOST/mcp/download"],
      "env": { "APPDASHBOARD_URL": "https://YOUR_HOST" }
    }
  }
}
```

## Integration API

### Automatic Immich / Gitea / Plane Credentials

After first `login`, Immich, Gitea, and Plane tools do **not** ask for API keys or tokens. On first use the MCP client calls:

- `GET /public-api/integrations/immich` → `{ url, api_key }`
- `GET /public-api/integrations/gitea` → `{ url, access_token }`
- `GET /public-api/integrations/plane` → `{ url, api_key, workspace_slug }`

The dashboard creates (or reuses) a per-user key named **MCP** and returns it. Credentials are cached in the MCP process memory and never printed by the tools themselves.

### HTTP Transport Endpoint

The MCP server provides an HTTP endpoint for WebSocket connections:

- **URL**: `http://YOUR_HOST:8808/mcp`
- **Port**: 8808 (configured in `supervisord.conf` as `appdashboard-mcp-http`)
- **Transport**: WebSocket (upgraded from HTTP)

MCP clients using HTTP transport connect to this endpoint.

## Development

```bash
cd appdashboard-mcp
npm ci
npm run build
npm start
```

Builds TypeScript to `dist/` and runs MCP server with stdio transport. HTTP mode with port 8808 for WebSocket connections.

## Deployment

Build and package:

```bash
cd appdashboard-mcp
sh ../deploy/scripts/build-appdashboard-mcp.sh
```

Produces `../downloads/appdashboard-mcp.tgz` served at `/mcp/appdashboard-mcp.tgz`.

## Requirements

- **Node.js**: 18 or higher
- **Runtime**: 64-bit Linux (required for auto-credentials)
- **Network**: Port 8808 available for HTTP/WSS transport

## Related Documentation

- [01_project_overview_and_architecture.md](01_project_overview_and_architecture.md) - Project overview
- [22_public_api_reference.md](22_public_api_reference.md) - Public API endpoints used by MCP (integrations, auth)
- [Share file utils](28_share_file_utils.md) - File viewer module