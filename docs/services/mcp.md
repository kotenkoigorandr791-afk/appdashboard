# MCP Server — Model Context Protocol

**Web UI**: `/mcp` **Endpoint**: `/mcp` (JSON-RPC 2.0) **Auth**: OAuth 2.0 Client Credentials + User Consent

---

## Overview

AppDashboard exposes an **MCP (Model Context Protocol) server** that allows AI assistants (Claude Desktop, Cursor, Windsurf, Continue, etc.) to access your data and execute actions through standardized tools.

**Protocol**: JSON-RPC 2.0 over HTTP POST
**Transport**: HTTP/HTTPS (streamable HTTP)
**Authentication**: OAuth 2.0 Bearer token (validated via introspection)

---

## Quick Start (Claude Desktop)

### 1. Register MCP Client in AppDashboard

Admin → Configuration → **OAuth Clients** → **Add Client**:

| Field | Value |
|-------|-------|
| Name | `Claude Desktop` |
| Client ID | `claude-desktop` (or auto) |
| Client Secret | (auto-generated) |
| Redirect URIs | `http://localhost:5173/callback` (Claude default) |
| Grant Types | `authorization_code`, `refresh_token` |
| Scopes | `mcp`, `cloud`, `films`, `smart_home`, `steps`, `spends`, `recipes` |
| Confidential | No (public client) |
| Require PKCE | Yes |

### 2. Configure Claude Desktop

Edit `~/Library/Application Support/Claude/claude_desktop_config.json` (macOS) or `%APPDATA%\Claude\claude_desktop_config.json` (Windows):

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
        "MCP_AUTH_TOKEN": "<will_be_filled_by_oauth>"
      }
    }
  }
}
```

### 3. Authenticate

1. Restart Claude Desktop
2. Click "Connect" on AppDashboard server
3. Browser opens → Log in to AppDashboard → **Allow** access
4. Done — tools appear in Claude

---

## Self-Hosted MCP Package (Alternative)

Download pre-built package for local/air-gapped use:

```bash
# Download
wget https://your-domain.com/downloads/appdashboard-mcp.tgz

# Extract & run
tar -xzf appdashboard-mcp.tgz
cd appdashboard-mcp
npm install
npm run build

# Configure
cp .env.example .env
# Edit .env: APPDASHBOARD_URL, OAUTH_CLIENT_ID, OAUTH_CLIENT_SECRET

# Run
npm start  # Starts on http://localhost:3000/mcp
```

Then point Claude to `http://localhost:3000/mcp`.

---

## MCP Endpoint

```
POST https://your-domain.com/mcp
Content-Type: application/json
Authorization: Bearer <oauth_access_token>
```

**Request** (JSON-RPC 2.0):
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "list_files",
    "arguments": { "parent_id": "root", "limit": 20 }
  }
}
```

**Response**:
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "content": [
      { "type": "text", "text": "Files: ..." }
    ]
  }
}
```

---

## Available Tools

### Cloud (Files)

| Tool | Description | Parameters |
|------|-------------|------------|
| `list_files` | List files/folders | `parent_id?`, `cursor?`, `limit?`, `search?`, `type?` (file/folder) |
| `read_file` | Read file content | `file_id`, `encoding?` (text/base64), `max_bytes?` |
| `write_file` | Create/update file | `parent_id`, `name`, `content` (base64), `mime_type?` |
| `search_files` | Full-text search | `query`, `type?`, `mime?`, `date_from?`, `date_to?` |
| `create_share` | Generate public share | `file_id`, `password?`, `expires_at?`, `max_downloads?` |
| `get_file_metadata` | Detailed file info | `file_id` |
| `delete_file` | Delete file/folder | `file_id`, `force?` |
| `create_folder` | Create folder | `parent_id`, `name` |

### Films

| Tool | Description | Parameters |
|------|-------------|------------|
| `list_movies` | List movies | `cursor?`, `limit?`, `search?`, `genre?`, `year_from?`, `year_to?`, `watched?` |
| `get_movie` | Full movie details | `movie_id` |
| `search_movies` | Search movies | `query`, `limit?` |

### Smart Home

| Tool | Description | Parameters |
|------|-------------|------------|
| `list_devices` | List devices | `room_id?`, `type?`, `capability?`, `online?` |
| `control_device` | Send command | `device_id`, `capability`, `command`, `params?` |
| `list_scenes` | List scenes | — |
| `activate_scene` | Activate scene | `scene_id` |
| `get_device_state` | Current state | `device_id` |

### Steps

| Tool | Description | Parameters |
|------|-------------|------------|
| `get_steps` | Step data | `date_from`, `date_to`, `aggregation?` (daily/weekly/monthly) |
| `get_stats` | Statistics overview | — |

### Spends

| Tool | Description | Parameters |
|------|-------------|------------|
| `list_expenses` | List expenses | `date_from?`, `date_to?`, `category_id?`, `account_id?`, `cursor?`, `limit?` |
| `get_expenses` | Expense details | `expense_id` |
| `get_categories` | Categories with budgets | — |

### Recipes

| Tool | Description | Parameters |
|------|-------------|------------|
| `list_recipes` | List recipes | `search?`, `tag?`, `cuisine?`, `max_time?`, `cursor?`, `limit?` |
| `get_recipe` | Full recipe | `recipe_id` |
| `search_recipes` | Search by ingredient | `ingredients[]`, `limit?` |

### Integrations

| Tool | Description | Parameters |
|------|-------------|------------|
| `list_integrations` | User's accessible apps | — |
| `launch_integration` | Get SSO launch URL | `app_key` |

---

## Tool Result Format

All tools return **structured content** for AI consumption:

```json
{
  "content": [
    { "type": "text", "text": "Human-readable summary" },
    { "type": "resource", "resource": { "uri": "appdashboard://cloud/file/123", "mimeType": "application/json", "text": "{...}" } }
  ],
  "isError": false
}
```

**Resource URIs** (for follow-up reads):
- `appdashboard://cloud/file/{id}`
- `appdashboard://films/movie/{id}`
- `appdashboard://smart_home/device/{id}`
- `appdashboard://steps/data/{date}`
- `appdashboard://spends/expense/{id}`
- `appdashboard://recipes/recipe/{id}`

---

## OAuth Flow for MCP Clients

### 1. Client Registration (One-time)

Admin creates OAuth client with `mcp` scope + desired data scopes.

### 2. Authorization (Per User)

```
GET https://your-domain.com/public-api/v1/oauth/authorize?
  response_type=code
  &client_id=MCP_CLIENT_ID
  &redirect_uri=MCP_REDIRECT_URI
  &scope=mcp cloud films smart_home
  &state=RANDOM
  &code_challenge=PKCE_CHALLENGE
  &code_challenge_method=S256
```

User logs in → consents → redirects back with `code`.

### 3. Token Exchange

```
POST https://your-domain.com/public-api/v1/oauth/token
  grant_type=authorization_code
  &code=AUTH_CODE
  &redirect_uri=MCP_REDIRECT_URI
  &client_id=MCP_CLIENT_ID
  &code_verifier=PKCE_VERIFIER
```

Returns `access_token` (1h) + `refresh_token` (30d).

### 4. MCP Calls

```
POST https://your-domain.com/mcp
Authorization: Bearer <access_token>
```

### 5. Token Refresh (Automatic)

MCP client should refresh before expiry:

```
POST https://your-domain.com/public-api/v1/oauth/token
  grant_type=refresh_token
  &refresh_token=REFRESH_TOKEN
  &client_id=MCP_CLIENT_ID
```

---

## Permissions & Access Control

| Layer | Enforcement |
|-------|-------------|
| **OAuth Scopes** | Token must have `cloud` scope to call `list_files` |
| **App Access** | User must have `cloud` app access enabled in Admin → Users |
| **Resource Ownership** | Users only see their own files/movies/recipes (unless shared) |
| **Admin Override** | Admin tokens with `admin` scope see all users' data |

**Example**: Token with `cloud` scope but user lacks `cloud` app access → `403 Forbidden` on all cloud tools.

---

## Configuration (Admin)

Admin → Configuration → **MCP**:

| Setting | Description |
|---------|-------------|
| Server enabled | On/Off |
| Max tools per call | Prevent prompt injection (default: 10) |
| Tool timeout | Seconds (default: 30) |
| Allowed origins | CORS for browser-based MCP clients |
| Rate limit | Requests/minute per token (default: 100) |
| Log tool calls | Audit all MCP tool invocations |

---

## Building the MCP Package

```bash
# In project root
./deploy/scripts/build-appdashboard-mcp.sh
# Or manually:
cd appdashboard-mcp
npm ci
npm run build
# Output: downloads/appdashboard-mcp.tgz
```

**Docker auto-build**: Set `RUN_MCP_PACKAGE_BUILD=1` in `.env` → runs on container start.

---

## Testing MCP Locally

```bash
# Requires: APPDASHBOARD_URL, valid user token
cd appdashboard-mcp
npm run test:cloud      # Tests cloud tools
npm run test:recipes    # Tests recipes tools
npm run test:all        # Full suite
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| "Unauthorized" on tool call | Check token has correct scopes; verify user has app access; token not expired |
| "Tool not found" | Tool name typo; check `/mcp` → `tools/list` for available tools |
| Claude "Connection failed" | Verify `APPDASHBOARD_URL` in config; check OAuth client redirect URI matches; try `npx @modelcontextprotocol/server-http` manually |
| Tools return empty | User has no data in that app; check app access; check resource ownership |
| Rate limited | Reduce call frequency; check Admin → Config → MCP → Rate Limit |
| Self-hosted package won't start | Check `.env` has `APPDASHBOARD_URL`, `OAUTH_CLIENT_ID`, `OAUTH_CLIENT_SECRET`; run `npm run build` |
| PKCE error | Ensure client configured with `Require PKCE=Yes`; generate challenge correctly |