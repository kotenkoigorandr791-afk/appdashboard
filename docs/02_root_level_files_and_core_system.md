# Root Level Files Documentation

## Core Application Files

### index.php (78 bytes - lightweight entry point)
**Purpose**: Minimal root entry point that handles sharedFile parameter and webdav proxy. Delegates main application logic to `project/index.php`.

**Key Responsibilities**:
- Handle sharedFile parameter for lightweight public shared-file links
- Proxy webdav requests to apps/cloud/webdav.php
- Load bootstrap chain for sharedFile flow (bootstrap.php → core.php → oauth.php → oauth_login.php → routes.php)

**Main Functions**:
```php
// Entry point delegation
if (isset($_GET['sharedFile'])) {
    // Load shared file bootstrap chain
    require __DIR__ . '/bootstrap.php';
    require __DIR__ . '/core.php';
    require __DIR__ . '/oauth.php';
    require __DIR__ . '/oauth_login.php';
    require __DIR__ . '/routes.php';
    exit;
}

// Main application routing
$__path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
// ... aliases and early exits for /webdav*, /project/performance*
```

### project/index.php (~8,100 lines)
**Purpose**: Main entry point for the entire application. Handles routing, authentication, and UI rendering.

**Key Responsibilities**:
- Session management and authentication
- User session validation with 12-hour timeout
- Google OAuth integration
- Client auth token generation/validation for API access
- One-Time PIN (OTP) generation and validation
- Trusted device remember-me flow
- Telegram Mini App authentication
- Brute-force protection via IP lockouts
- Routing to different apps based on `?app=` parameter
- Public app access control (test, demo, landing, info, public, shared_file)
- Login/registration flow
- Password reset functionality
- Email verification

**Main Functions**:
```php
// Session management
app_session_start()
loginUserSession(string $email, array $users): bool
ensureClientAuthTokensSchema(SQLite3 $db): void
createClientAuthToken(SQLite3 $db, string $email, ...): array
parseTrustedDeviceHintCookie(string $cookie): array|null
setTrustedDeviceHintCookie(array $data): void
clearTrustedDeviceHintCookie(): void

// Authentication
// - Google OAuth callback handling
// - Email/password login
// - OTP login
// - Password reset

// Routing
// - App selection via $_GET['app']
// - Public vs authenticated routes
// - sharedFile lightweight flow
// - /webdav* early exit
// - /project/performance* early exit
// - /mcp* MCP package download
```

**Security Features**:
- Session timeout enforcement (12 hours)
- IP-based brute-force protection
- OTP with attempt limits (3 attempts)
- Client auth tokens with 180-day expiry
- Device tracking for sessions
- Trusted device persistent login via encrypted cookies
- CSRF protection tokens
- Secure cookie flags (HttpOnly, Secure, SameSite=Lax)
- Password hashing with Argon2id

### db.php (~205,000 lines)
**Purpose**: PostgreSQL database abstraction layer. Replaces all JSON file operations for user data, sessions, and configuration.

**Key Responsibilities**:
- PostgreSQL database connection singleton
- Schema initialization and migrations
- User CRUD operations
- Session management (active_sessions table)
- Visit statistics tracking
- Shared files metadata
- Password reset codes
- Email verification tokens
- Application configuration storage
- OTP management
- IP lockout tracking
- Client auth tokens
- Trusted device hints
- OAuth state management
- WebSocket connection metadata

**Database Tables Created**:
```sql
users                    -- User accounts (email, password_hash, role, created_at)
pending_users            -- Awaiting email verification
active_sessions          -- Current sessions (token, user_id, expires_at, ip, user_agent)
visit_stats              -- Access statistics
shared_files             -- Publicly shared files metadata
config                   -- Key-value application configuration
client_auth_tokens       -- Long-lived API tokens (180-day expiry)
one_time_pins            -- OTP codes for login/password reset
ip_lockouts              -- Brute-force protection
user_app_restrictions    -- Per-user app access permissions
verification_tokens      -- Email verification
reset_codes              -- Password reset codes
oauth_states             -- OAuth CSRF protection
webhook_deliveries       -- Outgoing webhook tracking
```

**Key Functions**:
```php
// Connection
getDB(): PDO
appDatabase(): PDO (alias)

// User management
getUserByEmail(string $email): array|null
createUser(array $data): int
updateUser(int $id, array $data): bool
deleteUser(int $id): bool
verifyUserPassword(string $email, string $password): bool

// Session management
createUserSession(int $userId, string $ip, string $userAgent): string
validateSession(string $token): array|null
destroySession(string $token): bool
cleanExpiredSessions(): int

// Auth tokens
createClientAuthToken(string $email, array $scopes): array
validateClientAuthToken(string $token): array|null
revokeClientAuthToken(string $token): bool

// OTP
generateOneTimePin(string $email, string $purpose): string
validateOneTimePin(string $email, string $pin, string $purpose): bool
```

**Configuration Source**:
- PostgreSQL credentials: `/opt/AppDashboard/data/db.env` (mounted volume)
- Connection uses PDO with PostgreSQL driver
- No SQLite3 or WAL mode - pure PostgreSQL

---
### db_admin.php (~233,000 lines)
**Purpose**: Database admin interface with SQL console and management tools.

**Key Responsibilities**:
- Raw SQL query execution interface
- Database schema visualization
- Table data browsing/editing
- Backup/restore management
- User and session administration
- Performance monitoring

### backup_dashboard.php (~321,000 lines)
**Purpose**: Backup management interface with AES-256-GCM encryption.

**Key Responsibilities**:
- Create encrypted backups of PostgreSQL database
- Schedule automated backups
- Restore from backup files
- Manage backup retention policies
- Upload/download backup storage
- Encryption key management

### file_viewer.php
**Purpose**: File viewing/downloading handler for various file types.

**Key Responsibilities**:
- Serve files with appropriate Content-Type headers
- Handle range requests for large files
- Generate thumbnails for images/documents
- Stream media content
- Enforce access permissions
- Log file access events

### ssh_terminal_handler.php
**Purpose**: SSH terminal WebSocket handler.

**Key Responsibilities**:
- Upgrade HTTP connection to WebSocket
- Proxy terminal I/O between browser and SSH server
- Manage SSH connection lifecycle
- Handle authentication and encryption
- Resize terminal windows
- Log SSH session activity

### doc_viewer.php
**Purpose**: Document viewing handler for office formats, PDFs, etc.

**Key Responsibilities**:
- Convert documents to viewable formats
- Stream document pages
- Handle zoom/navigation
- Support common office formats
- Render PDFs in browser

### composer.json / composer.lock
**Purpose**: PHP dependency management via Composer.

**Dependencies**:
- christians-riesen/otp: ^1.4 (OTP generation/validation)
- firebase/php-jwt: ^7.0 (JSON Web Tokens)
- google/apiclient: ^2.19 (Google OAuth integration)
- guzzlehttp/guzzle: ^7.15 (HTTP client)
- guzzlehttp/psr7: ^2.13 (PSR-7 HTTP message interfaces)
- php-mqtt/client: ^2.3 (MQTT client for IoT)
- phpmailer/phpmailer: ^7.0 (Email sending)
- phpseclib/phpseclib: ^3.0.54 (SSH, crypto, SFTP)
- pimple/pimple: ^3.5 (Dependency injection container)
- sentry/sentry: ^4.31 (Error monitoring)
- symfony/*: Various Symfony components

### .env
**Purpose**: Runtime configuration environment variables.

**Variables**:
- BASE_DOMAIN: Public domain (e.g., example.com)
- APP_PUBLIC_SCHEME: http or https
- DB_PASSWORD: PostgreSQL password
- APP_ENV: local/production
- APP_DEBUG: Enable debug mode
- Various service subdomain overrides (AI_PUBLIC_URL, GITEA_PUBLIC_URL, etc.)

### .htaccess
**Purpose**: Kept for legacy/reference; actual URL rewriting handled by nginx.

**Note**: In Docker deployment, nginx serves as the web server with config rendered from `nginx/appdashboard.conf.template`. The .htaccess file is preserved but not active.

### data/
**Purpose**: Persistent data volume mounted at `/opt/AppDashboard/data/`.

**Contents**:
- db.env: PostgreSQL credentials file
- (Other app-specific data directories may be mounted here)

### nginx/
**Purpose**: Nginx configuration rendered from templates.

**Files**:
- appdashboard.conf: Rendered main site config
- appdashboard.conf.template: Template with variable substitution
- Additional configs for subdomains/services

### lib/
**Purpose**: Shared PHP libraries and helpers.

**Key Files**:
- bootstrap.php: Early initialization (constants, paths, autoloader)
- core.php: Core functions (config, logging, security helpers)
- i18n_helpers.php: Internationalization functions
- app_domains.php: Subdomain URL generation
- app_session.php: Session management helpers
- redis.php: Redis connection and helpers
- db_auth_security.php: DB-based auth security (brute force, lockouts)
- pg_*: PostgreSQL compatibility helpers
- pgsql_compat.php: PostgreSQL SQL function emulation
- app_db.php: Database wrapper functions
- portainer_client.php: Portainer container management
- sentry_bootstrap.php: Error reporting initialization

### apps/
**Purpose**: Contains 13 sub-applications (see 03_applications_directory_summary.md).

### public-api/
**Purpose**: Public REST API endpoints under `/public-api/` prefix.

**Key Files**:
- index.php: API router
- routes/: Individual API route handlers
- auth/: Authentication middleware
- Integrations with various apps/services

### bot/
**Purpose**: Telegram bot integration.

**Key Files**:
- bot.py: Main bot logic (Python)
- bot_service.php: PHP service wrapper
- tg_mini_auth.php: Telegram Mini App authentication
- Requirements: python-telegram-bot, requests, etc.

### webssh/
**Purpose**: WebSocket SSH terminal implementation.

**Key Files**:
- server.php: WebSocket server entrypoint
- SshWsHandler.php: Core SSH/WebSocket logic
- Dependencies: phpseclib, ratchet (via Composer)

### Shared Files in Root
- default_avatar.png: Default user avatar
- icon.png: Main application icon
- google.png: Google OAuth button
- privacy.html: Privacy policy
- terms.html: Terms of service
- google7cb515c0a41e4548.html: Google Search Console verification
- share.png: Social sharing icon
- privacy-terms.png: Combined privacy/terms image
- php_errors.log: PHP error log (when enabled)
