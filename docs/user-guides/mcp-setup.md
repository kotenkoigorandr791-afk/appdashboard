# Set Up MCP for AI Assistants

Connect Claude Desktop, Cursor, Windsurf, or Continue to your AppDashboard data.

---

## Option 1: Hosted MCP (Recommended)

Use your existing AppDashboard instance as MCP server.

### Prerequisites

- AppDashboard running with HTTPS
- Admin access
- AI assistant that supports MCP (Claude Desktop, Cursor, etc.)

### Step 1: Register MCP Client

Admin → Configuration → **OAuth Clients** → **Add Client**:

| Field | Value |
|-------|-------|
| Name | `Claude Desktop` (or your AI tool) |
| Client ID | `claude-desktop` (or auto) |
| Client Secret | (auto-generated, copy it) |
| Redirect URIs | `http://localhost:5173/callback` (Claude default) |
| Grant Types | `authorization_code`, `refresh_token` |
| Scopes | `mcp`, `cloud`, `films`, `smart_home`, `steps`, `spends`, `recipes` |
| Confidential | **No** (public client) |
| Require PKCE | **Yes** |

> **Save the Client Secret** — you'll need it for Claude config.

### Step 2: Configure Claude Desktop

**macOS**: `~/Library/Application Support/Claude/claude_desktop_config.json`
**Windows**: `%APPDATA%\Claude\claude_desktop_config.json`
**Linux**: `~/.config/Claude/claude_desktop_config.json`

```json
{
  "mcpServers": {
    "appdashboard": {
      "command": "npx",
      "args": [
        "-y",
        "@modelcontextprotocol/server-http",
        "https://your-domain.com/mcp"
      ],
      "env": {
        "OAUTH_CLIENT_ID": "claude-desktop",
        "OAUTH_CLIENT_SECRET": "your-client-secret-here",
        "OAUTH_REDIRECT_URI": "http://localhost:5173/callback"
      }
    }
  }
}
```

### Step 3: Authenticate

1. **Restart Claude Desktop** completely
2. Open Claude → Settings → **MCP** → You'll see "AppDashboard" with **Connect** button
3. Click **Connect**
4. Browser opens → Log in to AppDashboard → **Allow** access to requested scopes
5. Done! Tools appear in chat

### Step 4: Test

Ask Claude:
> "List my files in AppDashboard"
> "What movies do I have?"
> "Show my step count for this week"
> "Turn on the living room lights"

---

## Option 2: Self-Hosted MCP Package

Run MCP locally (air-gapped, no external dependency).

### Download & Run

```bash
# On your machine (not the server)
wget https://your-domain.com/downloads/appdashboard-mcp.tgz
tar -xzf appdashboard-mcp.tgz
cd appdashboard-mcp

# Configure
cp .env.example .env
# Edit .env:
# APPDASHBOARD_URL=https://your-domain.com
# OAUTH_CLIENT_ID=claude-desktop
# OAUTH_CLIENT_SECRET=your-secret

# Install & Build
npm ci
npm run build

# Start (runs on http://localhost:3000/mcp)
npm start
```

### Configure Claude for Local MCP

```json
{
  "mcpServers": {
    "appdashboard-local": {
      "command": "node",
      "args": ["/full/path/to/appdashboard-mcp/dist/index.js"],
      "env": {
        "APPDASHBOARD_URL": "https://your-domain.com",
        "OAUTH_CLIENT_ID": "claude-desktop",
        "OAUTH_CLIENT_SECRET": "your-secret",
        "OAUTH_REDIRECT_URI": "http://localhost:5173/callback"
      }
    }
  }
}
```

---

## Option 3: Cursor / Windsurf / Continue

### Cursor

`~/.cursor/mcp.json`:
```json
{
  "mcpServers": {
    "appdashboard": {
      "url": "https://your-domain.com/mcp",
      "headers": {
        "Authorization": "Bearer ${OAUTH_ACCESS_TOKEN}"
      }
    }
  }
}
```
Then set `OAUTH_ACCESS_TOKEN` in Cursor settings → Environment Variables.

### Windsurf

`~/.codeium/windsurf/mcp_config.json`:
```json
{
  "servers": {
    "appdashboard": {
      "transport": "http",
      "url": "https://your-domain.com/mcp"
    }
  }
}
```

### Continue (VS Code / JetBrains)

`~/.continue/config.json`:
```json
{
  "mcpServers": [
    {
      "name": "appdashboard",
      "transport": "http",
      "url": "https://your-domain.com/mcp"
    }
  ]
}
```

---

## Available Tools by Scope

| Scope | Tools |
|-------|-------|
| `mcp` | (base — required for all) |
| `cloud` | `list_files`, `read_file`, `write_file`, `search_files`, `create_share`, `get_file_metadata`, `delete_file`, `create_folder` |
| `films` | `list_movies`, `get_movie`, `search_movies` |
| `smart_home` | `list_devices`, `control_device`, `list_scenes`, `activate_scene`, `get_device_state` |
| `steps` | `get_steps`, `get_stats` |
| `spends` | `list_expenses`, `get_expenses`, `get_categories` |
| `recipes` | `list_recipes`, `get_recipe`, `search_recipes` |

**Tip**: Start with minimal scopes, add as needed.

---

## Permissions & Privacy

| Layer | What It Controls |
|-------|------------------|
| **OAuth Scopes** | Which tool categories AI can call |
| **App Access** | User must have app enabled in Admin → Users |
| **Resource Ownership** | AI only sees *your* data (not other users') |
| **Admin Override** | Admin tokens with `admin` scope see all |

**Example**: You grant `cloud` scope but user lacks `cloud` app access → AI gets `403` on file tools.

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| "Connection failed" | Check `APPDASHBOARD_URL` reachable; try `curl https://your-domain.com/mcp` |
| "Unauthorized" | Token expired → restart AI to re-auth; check scopes match tools |
| "Tool not found" | Typo in tool name; run `tools/list` via MCP inspector |
| Empty results | No data in that app; check app access; verify resource ownership |
| Rate limited | Reduce parallel calls; check Admin → Config → MCP → Rate Limit |
| Local package won't start | Check `.env` has all 3 vars; run `npm run build`; Node 18+ required |
| PKCE error | OAuth client must have **Require PKCE = Yes**; redirect URI exact match |
| "Invalid redirect_uri" | Must match exactly: `http://localhost:5173/callback` (Claude default) |

---

## MCP Inspector (Debug)

```bash
npx @modelcontextprotocol/inspector https://your-domain.com/mcp
```

Opens web UI to browse tools, call them manually, see raw requests/responses.

---

## Security Best Practices

- Use **dedicated OAuth client** per AI tool (revoke independently)
- Grant **minimum scopes** needed
- Monitor **Admin → Audit Logs** → filter `mcp` for AI activity
- Rotate client secrets quarterly
- Use **local MCP package** for sensitive/air-gapped environments