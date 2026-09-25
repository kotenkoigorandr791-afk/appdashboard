# appdashboard-mcp

Prebuilt MCP server for AppDashboard (stdio). Clients install with **one command** — no local build.

One server key: **`appdashboard-mcp`**. Tools for the service shell and all apps (Spends, Cloud, Smart Home, Recipes, Steps, Immich, Gitea, Kanboard) live in the same package.

## Install (from AppDashboard UI)

Settings → MCP → copy command for your agent, e.g.:

```bash
npx -y https://YOUR_HOST/mcp/appdashboard-mcp.tgz setup --agent cursor --url https://YOUR_HOST
```

Requires Node.js 18+. Setup asks for an AppDashboard token, or pass `--token TOKEN` / `APPDASHBOARD_TOKEN`.
Restart the agent after setup.

## Agent configs

**Claude Desktop** (`~/.config/claude/claude_desktop_config.json` or macOS `~/Library/Application Support/Claude/claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "appdashboard-mcp": {
      "command": "npx",
      "args": ["-y", "https://YOUR_HOST/mcp/download"],
      "env": { "APPDASHBOARD_URL": "https://YOUR_HOST", "APPDASHBOARD_TOKEN": "YOUR_TOKEN" }
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
      "env": { "APPDASHBOARD_URL": "https://YOUR_HOST", "APPDASHBOARD_TOKEN": "YOUR_TOKEN" }
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
      "env": { "APPDASHBOARD_URL": "https://YOUR_HOST", "APPDASHBOARD_TOKEN": "YOUR_TOKEN" }
    }
  }
}
```

**OpenCode** (`opencode.json`):

```json
{
  "mcp": {
    "appdashboard-mcp": {
      "enabled": true,
      "type": "local",
      "command": ["npx", "-y", "https://YOUR_HOST/mcp/download"],
      "environment": { "APPDASHBOARD_URL": "https://YOUR_HOST", "APPDASHBOARD_TOKEN": "YOUR_TOKEN" }
    }
  }
}
```

Local develop entry uses `node` + `dist/index.js` instead of `npx`.

## Grouped tool discovery (default)

Instead of exposing one huge flat tool list (100+ tools), the server advertises only 4 meta-tools:

| Tool | Purpose |
|------|---------|
| `tools_catalog` | List tool groups with counts and descriptions |
| `tools_load` | Get full schemas for the groups you need (e.g. `{"groups":["kanboard"]}`) |
| `tools_search` | Find tools by keyword across all groups |
| `mcp_check_update` | Check for a newer MCP package and auto-update |

The agent is instructed to call these tools **only when the task explicitly requires AppDashboard** (its apps or server management); regular local coding tasks must not trigger MCP calls.

Environment variables:

| Variable | Default | Meaning |
|----------|---------|---------|
| `MCP_GROUPED_TOOLS` | `1` | `0` disables grouped mode (legacy flat list) |
| `MCP_GROUPS` | *(unset)* | Comma-separated allow-list of group ids (e.g. `kanboard,gitea`); `all` = no restriction |
| `APPDASHBOARD_MCP_AUTOUPDATE` | `1` | `0` disables the background auto-updater |
| `IMMICH_URL` / `IMMICH_API_KEY` | *(unset)* | Override Immich credentials (bypass dashboard integration; useful for tests) |
| `GITEA_URL` / `GITEA_TOKEN` | *(unset)* | Override Gitea credentials |
| `KANBOARD_URL` / `KANBOARD_USERNAME` / `KANBOARD_API_TOKEN` | *(unset)* | Override Kanboard credentials |

## Auto-update

On start (and every 6 hours) the server compares the installed package fingerprint
(`~/.config/appdashboard-mcp/package.json`, written by `setup`) with `GET APPDASHBOARD_URL/mcp/info`.
If a new version is published, the server downloads the tgz, verifies its sha256,
extracts it into `~/.cache/appdashboard-mcp/`, links `node_modules` and re-execs itself from the
updated copy. The user's agent session stays functional; no manual reinstall needed.

`mcp_check_update` triggers the same flow on demand.

## Viewing photos (Immich)

`immich_assets_thumbnail` returns an MCP image content block, so the agent can actually
see the photo (preview/thumbnail/original). `immich_assets_download` returns the original
file as base64 for saving to disk. Typical flow: `tools_load ["immich"]` → `immich_assets_list`
(or `immich_search_smart`) → `immich_assets_thumbnail {assetId}`.

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
| `kanboard_*` | Kanboard project management |
| `news_*` | News portal (`news_delete` requires Manager+) |

All `steps_*`, `immich_*`, `gitea_*`, and `kanboard_*` tools are available to any authenticated user (no Admin role required).

## Automatic Immich / Gitea / Kanboard credentials

After token setup, Immich, Gitea, and Kanboard tools do **not** ask for API keys or tokens. On first use the MCP client calls:

- `GET /public-api/integrations/immich` → `{ url, api_key }`
- `GET /public-api/integrations/gitea` → `{ url, access_token }`
- `GET /public-api/integrations/kanboard` → `{ url, username, api_token }`

The dashboard creates (or reuses) a per-user key named **MCP** and returns it. Credentials are cached in the MCP process memory and never printed by the tools themselves.
