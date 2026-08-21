# appdashboard-mcp

Prebuilt MCP server for AppDashboard (stdio). Clients install with **one command** — no local build.

One server key: **`appdashboard-mcp`**. Tools for the service shell and all apps (Spends, Cloud, Smart Home, Recipes, Steps, Immich, Gitea) live in the same package.

## Install (from AppDashboard UI)

Settings → MCP → copy command for your agent, e.g.:

```bash
npx -y https://YOUR_HOST/mcp/appdashboard-mcp.tgz setup --agent cursor --url https://YOUR_HOST
```

Requires Node.js 18+. Restart the agent after setup. On first use, open the browser auth URL.

## Agent configs

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

Local develop entry uses `node` + `dist/index.js` instead of `npx`.

## Develop

```bash
npm ci && npm run build
npm start
```

## Pack (server deploy)

```bash
sh deploy/scripts/build-appdashboard-mcp.sh
```

Produces `downloads/appdashboard-mcp.tgz` served at `/mcp/appdashboard-mcp.tgz`.

## Tool groups

| Prefix | Service |
|--------|---------|
| *(shell)* | Auth, users, apps, Docker, files, config, monitor, backup, commands, support |
| `spends_*` / expense tools | Spends |
| `cloud_*` | Cloud |
| `smarthome_*` | Smart Home |
| `recipes_*` | Recipes |
| `steps_*` | Steps counter |
| `immich_*` | Immich photo library |
| `gitea_*` | Gitea git forge |

All `steps_*`, `immich_*`, and `gitea_*` tools are available to any authenticated user (no Admin role required).

## Automatic Immich / Gitea credentials

After `login`, Immich and Gitea tools do **not** ask for API keys or tokens. On first use the MCP client calls:

- `GET /public-api/integrations/immich` → `{ url, api_key }`
- `GET /public-api/integrations/gitea` → `{ url, access_token }`

The dashboard creates (or reuses) a per-user key named **MCP** and returns it. Credentials are cached in the MCP process memory and never printed by the tools themselves.
