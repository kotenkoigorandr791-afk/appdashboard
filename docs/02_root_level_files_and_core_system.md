# Root Level Files Documentation

## Core Application Files

### index.php (8,139 lines)

**Purpose**: Main entry point for the entire application. Handles routing, authentication, and UI rendering.

**Key Responsibilities**:
- Session management and authentication
- User session validation with 12-hour timeout
- Google OAuth integration
- Client auth token generation/validation for API access
- Routing to different apps based on `?app=` parameter
- Public app access control (test, demo, landing, info, public, shared_file)
- Login/registration flow
- Password reset functionality
- Email verification
- One-Time PIN (OTP) generation and validation
- Brute-force protection via IP lockouts

**Main Functions**:
```php
// Session management
loginUserSession(string $email, array $users): bool
ensureClientAuthTokensSchema(SQLite3 $db): void
createClientAuthToken(SQLite3 $db, string $email, ...): array

// Authentication
// - Google OAuth callback handling
// - Email/password login
// - OTP login
// - Password reset

// Routing
// - App selection via $_GET['app']
// - Public vs authenticated routes
```

**Security Features**:
- Session timeout enforcement
- IP-based brute-force protection
- OTP with attempt limits (3 attempts)
- Client auth tokens with 180-day expiry
- Device tracking for sessions

---

### db.php (1,263 lines)

**Purpose**: PostgreSQL database abstraction layer. Replaces all JSON file operations for user data, sessions, and configuration.

**Key Responsibilities**:
- Database connection singleton with WAL mode
- Schema initialization and migrations
- User CRUD operations
- Session management
- Visit statistics tracking
- Shared files metadata
- Password reset codes
- Email verification tokens
- Application configuration storage
- OTP management
- IP lockout tracking
- Client auth tokens

**Database Tables Created**:
```sql
users                    -- User accounts
pending_users            -- Awaiting verification
active_sessions          -- Current sessions
visit_stats              -- App visit statistics
shared_files             -- Shared file metadata
reset_codes              -- Password reset codes
verification_tokens      -- Email verification
config                   -- App configuration
user_app_restrictions    -- Per-user app access
user_folder_paths        -- User folder mappings
app_meta                 -- App metadata
cloud_user_settings      -- Cloud quotas/sync
cloud_folder_state       -- Folder UI state
one_time_pins            -- OTP codes
ip_lockouts              -- Brute-force protection
client_auth_tokens       -- API access tokens
```

**Main Functions**:
```php
// Connection
getDB(): SQLite3
initDB(SQLite3 $db): void

// Users
loadUsers(): array
saveUsers(array $users): void
getUserByEmail(string $email): ?array
upsertUser(string $email, array $fields): void
deleteUser(string $email): void

// Sessions
loadActiveSessions(): array
saveActiveSessions(array $sessions): void
addUserToActiveSessions(string $email, string $sessionId, ...): void
getUserFromActiveSessions(string $sessionId): ?array
removeUserFromActiveSessions(string $email, string $sessionId): void
cleanupExpiredUserSessions(string $email): void

// Config
getConfig(string $key, string $default): mixed
setConfig(string $key, string $value): void

// OTP
generateOTP(string $email, int $ttlMinutes): string
verifyOTP(string $pin, string $email): bool

// Statistics
incrementVisitStat(string $appId, string $date): void
getVisitStats(string $appId, string $startDate, string $endDate): array
```

**Connection Settings**:
- WAL mode for concurrent reads
- 5-second busy timeout
- Foreign keys enabled
- 8MB page cache
- Normal synchronous mode

---

### db_admin.php

**Purpose**: Database administration interface for browsing and managing SQLite tables.

**Features**:
- Table browser with pagination
- Row editing (add/edit/delete)
- SQL console for raw queries
- Schema viewer
- Statistics dashboard
- VACUUM operation
- Export functionality
- Search within columns

---

### backup_dashboard.php (1,989 lines)

**Purpose**: Backup management interface with encryption support.

**Key Features**:
- Create encrypted backups
- Restore from backups
- Download backup files
- Delete old backups
- AES-256-GCM encryption
- Duplicati integration support
- Role-based access (admin only for standalone access)
- Embedded mode support (iframe)

**Encryption**:
- Uses `BACKUP_DASHBOARD_CRYPT_KEY` environment variable
- Falls back to stored key in config table
- IV + tag + ciphertext in JSON format
- Legacy decryption support

**Access Control**:
- Requires authentication
- Admin-only in standalone mode
- Embedded mode allows lower roles

---

### file_viewer.php

**Purpose**: Secure file viewing and downloading handler.

**Features**:
- View files with shared file tokens
- Download files
- Display mode (inline)
- Binary file detection
- Content-type detection
- Access control via shared file IDs

**URL Parameters**:
- `sharedFile` - Shared file ID
- `download` - Force download
- `display` - Inline display

---

### ssh_terminal_handler.php (454 lines)

**Purpose**: SSH terminal WebSocket handler for remote server access.

**Key Features**:
- Encrypted SSH credential storage
- AES-256-GCM encryption for passwords
- Support for SSH key authentication
- Password authentication via phpseclib
- WebSocket terminal integration
- Connection testing
- Profile management

**Security**:
- Credentials encrypted with `SSH_CREDENTIALS_CRYPT_KEY`
- Legacy file migration to SQLite config
- Host and username validation
- UTF-8 encoding enforcement for terminal output

**Functions**:
```php
apSshJsonResponse(array $payload): void
apSshReadPostString(string $key, string $default): string
apSshValidateHost(string $host): bool
apSshValidateUser(string $user): bool
apSshEnsureUtf8(string $text): string
apSshCredentialsFile(): string
apSshCredentialsConfigKey(): string
apSshCryptoKey(): string
```

---

## Configuration Files

### composer.json

**Purpose**: Root PHP dependency management.

**Dependencies**:
```json
{
    "require": {
        "phpmailer/phpmailer": "^7.0",
        "google/apiclient": "^2.18"
    }
}
```

- **phpmailer/phpmailer**: Email sending for registration, password reset, verification
- **google/apiclient**: Google OAuth 2.0 integration

---

### .htaccess

**Purpose**: Apache configuration for URL rewriting and security.

**Key Rules**:

1. **URL Cleanup**:
   - Removes `index.php` from URLs
   - Redirects to clean URLs

2. **File Access**:
   - Allows direct access to `file_viewer.php`
   - Allows `cloud/api.php`
   - Allows `server_monitor.php`

3. **Security Blocks**:
   - Blocks direct access to `.sqlite3` files
   - Blocks access to `data/`, `db/`, `database/` directories
   - Blocks hidden files (`.git*`, `.env`, etc.)
   - Blocks sensitive extensions (`.log`, `.sh`, `.bak`, `.sql`)

4. **App Routing**:
   - `/terms` → `terms.html`
   - `/privacy` → `privacy.html`
   - `/main`, `/login`, `/register` → `index.php`
   - `/ai/*` → Proxy to LM Studio (localhost:1234)

5. **Performance Monitor**:
   - `/performance/cpu`, `/performance/mem`, etc. → `server_monitor.php`

---

## Static Pages

### home.html

**Purpose**: Landing page for the application.

**Content**:
- Welcome message
- Feature highlights
- Call-to-action buttons

---

### privacy.html

**Purpose**: Privacy policy page.

**Content**:
- Data collection practices
- User rights
- Cookie usage
- Contact information

---

### terms.html

**Purpose**: Terms of service page.

**Content**:
- Usage terms
- User responsibilities
- Disclaimers
- Limitation of liability

---

### google7cb515c0a41e4548.html

**Purpose**: Google Search Console verification file.

**Content**: Google-provided meta tag for domain ownership verification.

---

## Assets

### default_avatar.png, icon.png, share.png, google.png, privacy-terms.png

**Purpose**: UI assets and icons.

- `default_avatar.png` - Default user profile picture
- `icon.png` - Main application icon
- `share.png` - Share functionality icon
- `google.png` - Google OAuth button icon
- `privacy-terms.png` - Privacy/Terms page icons

---

## Database

### PostgreSQL appdashboard (/opt/AppDashboard/data/db.env)

**Purpose**: Central PostgreSQL database for all application data.

**See**: `db.php` for schema details.

**Tables**: 15+ tables managing users, sessions, configuration, statistics, and more.

---

## Documentation

### client_apps_architecture.md

**Purpose**: Existing documentation for client applications architecture.

**Content**: Technical documentation about how client apps interact with the server.
