# AppDashboard MCP Documentation

**Path:** `appdashboard-mcp/`  
**Type:** MCP Server (stdio and HTTP transports)  
**Description:** Model Context Protocol (MCP) server integration for AppDashboard.

---

## Overview

The AppDashboard MCP server provides AI tool integration for the ServerKherson platform. It enables AI assistants to interact with AppDashboard services through standardized MCP protocols, supporting both standard input/output and HTTP transports.

## File Structure

```
appdashboard-mcp/
├── package.json           # Node.js package configuration
├── tsconfig.json          # TypeScript configuration
├── src/                   # Source code
│   ├── index.ts          # Main entry point
│   ├── api.ts            # API helpers
│   ├── auth.ts           # Authentication helpers
│   ├── integrations.ts   # Service integrations
│   ├── oauth.ts          # OAuth helpers
│   ├── roles.ts          # Role helpers
│   ├── setup.ts          # Setup script
│   └── tools/            # MCP tools
│       ├── apps.ts
│       ├── auth.ts
│       ├── backup.ts
│       ├── cloud.ts
│       ├── commands.ts
│       ├── config.ts
│       ├── docker.ts
│       ├── files.ts
│       ├── gitea.ts
│       ├── immich.ts
│       ├── index.ts
│       ├── kanboard.ts
│       ├── monitor.ts
│       ├── news.ts
│       ├── recipes.ts
│       ├── shell.ts
│       ├── smarthome.ts
│       ├── spends.ts
│       ├── steps.ts
│       ├── support.ts
│       ├── types.ts
│       └── users.ts
├── scripts/               # Utility scripts
│   ├── smoke-tools.mjs
│   ├── test-cloud-tools.mjs
│   └── test-recipes-tools.mjs
├── deploy/                # Deployment scripts (symlink to root)
│   └── scripts/
│       └── build-appdashboard-mcp.sh
└── README.md             # Usage documentation
```

## Configuration

The MCP server can be configured via:

### Environment Variables
- `APPDASHBOARD_URL` - Base URL of the AppDashboard instance (e.g., `https://example.com`)
- `APPDASHBOARD_TOKEN` - Authentication token for AppDashboard API
- `PORT` - HTTP server port (default: 3000 for HTTP transport)
- `NODE_ENV` - Node environment (development/production)

### Package Configuration (`package.json`)
```json
{
  "name": "appdashboard-mcp",
  "version": "2.0.0",
  "description": "MCP server for AppDashboard with secure sandboxed script execution",
  "type": "module",
  "bin": {
    "appdashboard-mcp": "dist/index.js"
  },
  "files": [
    "dist",
    "README.md"
  ],
  "scripts": {
    "build": "tsc",
    "prepack": "npm run build",
    "start": "node dist/index.js",
    "setup": "node dist/index.js setup"
  },
  "dependencies": {
    "@modelcontextprotocol/sdk": "^1.6.0",
    "express": "^4.21.5",
    "zod": "^3.24.1"
  },
  "devDependencies": {
    "@types/express": "^4.17.21",
    "@types/node": "^22.10.1",
    "typescript": "^5.7.2"
  },
  "engines": {
    "node": ">=18"
  },
  "main": "dist/index.js"
}
```

## MCP Tools

The server provides tools organized by service category:

### Cloud Operations
- `cloud_upload` - Upload file to cloud storage
- `cloud_list` - List files in a directory
- `cloud_download` - Download file
- `cloud_mkdir` - Create folder
- `cloud_delete` - Delete file/folder
- `cloud_share` - Create share link

### User Management
- `get_current_user` - Get the currently authenticated user
- `user_list` - List users (admin)
- `user_get` - Get user profile
- `user_create` - Create new user (admin)
- `user_update` - Update user (admin)
- `user_delete` - Delete user (admin)

### App Management
- `list_apps` - List applications available to the current user
- `get_app_access_levels` - Get min_role access levels for all apps (admin)
- `set_app_access_level` - Set min_role for an application (admin)
- `execute_app_action` - Execute app-specific actions
- `get_app_status` - Get app status

### File Operations (Shared Files)
- `files_upload` - Upload file to shared files
- `files_download` - Download shared file
- `files_list` - List shared files
- `files_delete` - Delete shared file

### Backup & System
- `backup_create` - Create backup
- `backup_list` - List backups
- `backup_restore` - Restore from backup
- `system_info` - Get system information
- `system_reboot` - Reboot system (admin)
- `system_shutdown` - Shutdown system (admin)

### Database Operations
- `db_query` - Execute SQL query (admin)
- `db_tables` - List database tables

### Service-Specific Tools
- `gitea_*` - Gitea operations
- `immich_*` - Immich operations
- `kanboard_*` - Kanboard operations
- `recipes_*` - Recipe operations
- `spends_*` - Expense operations
- `steps_*` - Step counter operations
- `smarthome_*` - Smart home operations
- `monitor_*` - Performance monitoring
- `shell_*` - Shell command execution (admin)

### Utility Tools
- `echo` - Echo back input (testing)
- `sleep` - Sleep for specified time (testing)

## Integration

### Setup

1. Build the MCP package:
   ```bash
   # From repository root
   RUN_MCP_PACKAGE_BUILD=1 docker compose up -d --build
   # OR manually:
   cd appdashboard-mcp
   npm ci
   npm run build
   ```

2. The built package is available at:
   ```
   http://<APPDASHBOARD_URL>/mcp/appdashboard-mcp.tgz
   ```

3. Install in MCP client (e.g., Claude Desktop):
   ```bash
   npx -y https://<APPDASHBOARD_URL>/mcp/appdashboard-mcp.tgz setup
   ```

### Authentication

The MCP server supports:

- **Bearer Token**: `APPDASHBOARD_TOKEN` environment variable
- **Session Token**: Via AppDashboard session (when accessed through web interface)
- **No Auth**: For local development (when `NODE_ENV=development` and accessing via stdio)

### Protocol

The MCP server follows the Model Context Protocol specification:

1. **Initialization**: Client connects to server (stdio or HTTP)
2. **Capability Exchange**: Server provides list of available tools and resources
3. **Tool Execution**: Client requests tool execution with parameters
4. **Response**: Server returns tool results or errors

## Security

### Access Control

- All MCP requests are authenticated via token (except local stdio dev)
- Role-based access control (RBAC) for tools based on user role
- Rate limiting implemented for HTTP transport
- Request logging for security auditing

### Data Protection

- Sensitive data is never logged
- Tokens are hashed in memory
- TLS required for HTTP transport in production
- Input validation and sanitization for all parameters

## Deployment

### Docker Compose (Recommended)

The MCP server is built and run as part of the AppDashboard stack when `RUN_MCP_PACKAGE_BUILD=1` is set.

### Standalone Docker

```dockerfile
FROM node:20-alpine
WORKDIR /app
COPY appdashboard-mcp/ ./
RUN npm ci --only=production
EXPOSE 3000
CMD ["node", "dist/index.js"]
```

### Environment Variables

| Variable | Description | Required | Default |
|----------|-------------|----------|---------|
| `APPDASHBOARD_URL` | Base URL of AppDashboard | Yes | - |
| `APPDASHBOARD_TOKEN` | Authentication API token | Yes (except stdio dev) | - |
| `PORT` | HTTP server port | No | 3000 |
| `NODE_ENV` | Node environment | No | production |

## Troubleshooting

### Common Issues

1. **Connection Refused**: Verify `APPDASHBOARD_URL` and network connectivity
2. **Unauthorized**: Check `APPDASHBOARD_TOKEN` validity and permissions
3. **Tool Not Found**: Ensure tool name matches exactly (use `list_tools` to see available)
4. **Build Failures**: Ensure Node.js >=18 and run `npm ci` before `npm run build`

### Debug Mode

Set `NODE_ENV=development` for verbose logging and stdio access without token.

## Development

### Running Tests

```bash
cd appdashboard-mcp
npm test
```

### Adding New Tools

1. Create new tool file in `src/tools/` (e.g., `newtool.ts`)
2. Define tool schema and handler
3. Export tool in `src/tools/index.ts`
4. Run `npm run build`

## License

MIT License