# appdashboard-mcp

Prebuilt MCP server for AppDashboard (stdio). Clients install with **one command** — no local TypeScript build.

## Install (from AppDashboard UI)

Settings → MCP → copy command for your agent, e.g.:

```bash
npx -y https://YOUR_HOST/mcp/appdashboard-mcp.tgz setup --agent cursor --url https://YOUR_HOST
```

Requires Node.js 18+. Restart the agent after setup. On first use, open the browser auth URL.

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
