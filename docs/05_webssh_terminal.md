# WebSSH Application Documentation

## Overview

The `webssh/` directory contains a WebSocket-based SSH terminal application that allows users to connect to remote servers directly from the browser. Integrated via nginx WebSocket proxy (`/ssh-ws`) and `ssh_terminal_handler.php` at the root level.

## Directory Structure

```
webssh/
├── server.php              # WebSocket server entry point
├── SshWsHandler.php        # Core SSH session handler (phpseclib-based)
├── composer.json           # PHP dependencies
├── composer.lock
├── ap-ssh-ws.service       # systemd service
└── vendor/                 # PHP dependencies (phpseclib, ratchet)
```

## Dependencies (`composer.json`)

```json
{
    "require": {
        "phpseclib/phpseclib": "^3.0.54",
        "cboden/ratchet": "^0.4"
    }
}
```

### Libraries

| Library | Purpose |
|---------|---------|
| **phpseclib/phpseclib** | Pure PHP SSH implementation |
| **cboden/ratchet** | WebSocket server for real-time terminal |

## Integration with Main App

The WebSSH application is integrated via:
1. **Nginx** proxies `/ssh-ws` WebSocket connections to the appdashboard container
2. **`ssh_terminal_handler.php`** (root directory) handles the initial WebSocket upgrade
3. **`webssh/server.php`** runs the Ratchet-based WebSocket server

### Connection Flow

1. User enters SSH credentials in the UI
2. Credentials are encrypted using AES-256-GCM and stored in PostgreSQL `active_sessions`
3. WebSocket connection established via nginx proxy to appdashboard container
4. Terminal I/O relayed between browser and SSH server through `SshWsHandler.php`
5. Session persists for active user session; credentials available only in encrypted form

## Credential Storage

### Encryption

SSH credentials are encrypted using AES-256-GCM:

```php
function apSshCryptoKey(): string {
    $key = getenv('SSH_CREDENTIALS_CRYPT_KEY') ?: 'default-key-change-me';
    return hash('sha256', $key, true); // 32 bytes for AES-256
}
```

Storage: credentials stored encrypted in PostgreSQL `active_sessions` table or encrypted Redis, never in plain text.

## Systemd Service

`ap-ssh-ws.service` manages the WebSocket SSH server process on the host.

## Security

- All SSH traffic is proxied through nginx with TLS termination
- Credential encryption with AES-256-GCM
- Session validation via active session tokens
- No plain-text credential storage
