# WebSSH Application Documentation

## Overview

The `webssh/` directory contains a WebSocket-based SSH terminal application that allows users to connect to remote servers directly from the browser.

## Directory Structure

```
webssh/
├── composer.json           # PHP dependencies for SSH functionality
├── vendor/                 # PHP dependencies (phpseclib, ratchet)
└── ... (WebSocket server files)
```

## Dependencies (`composer.json`)

```json
{
    "require": {
        "phpseclib/phpseclib": "^3.0",
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

The WebSSH application is integrated via `ssh_terminal_handler.php` in the root directory.

### Connection Flow

1. User enters SSH credentials in the UI
2. Credentials are encrypted and stored in SQLite `config` table
3. WebSocket connection is established to the server
4. Terminal I/O is relayed between browser and SSH server

## Credential Storage

### Encryption

SSH credentials are encrypted using AES-256-GCM:

```php
// Key derivation
function apSshCryptoKey(): string {
    $key = getenv('SSH_CREDENTIALS_CRYPT_KEY') ?: 'default-key-change-me';
    return hash('sha256', $key, true); // 32 bytes for AES-256
}

// Encryption
function apSshEncryptCredentials(array $creds): string {
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt(
        json_encode($creds),
        'aes-256-gcm',
        apSshCryptoKey(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );
    return json_encode([
        'v' => 1,
        'iv' => base64_encode($iv),
        'ct' => base64_encode($ct),
        'tag' => base64_encode($tag),
    ]);
}
```

### Storage Location

Credentials are stored in the main database:
```sql
-- Table: config
-- Key: 'ssh_terminal_credentials_json'
-- Value: Encrypted JSON with host, user, port, key path, etc.
```

### Legacy Support

Older versions stored credentials in a file:
```
/ssh_terminal_credentials.enc.json
```

The system automatically migrates legacy file-based credentials to SQLite on first access.

## SSH Connection Handler Functions

### Response Handling

```php
function apSshJsonResponse(array $payload): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}
```

### Input Validation

```php
// Host validation (alphanumeric, dots, underscores, hyphens)
function apSshValidateHost(string $host): bool {
    return (bool)preg_match('/^[a-zA-Z0-9._-]+$/', $host);
}

// Username validation (standard Unix username rules)
function apSshValidateUser(string $user): bool {
    return (bool)preg_match('/^[a-z_][a-z0-9_-]*[$]?$/', $user);
}
```

### Character Encoding

```php
// Ensures UTF-8 output from terminal
function apSshEnsureUtf8(string $text): string {
    if (mb_detect_encoding($text) !== 'UTF-8') {
        // Try common encodings: CP866, Windows-1251, ISO-8859-1
        $converted = mb_convert_encoding($text, 'UTF-8', 'UTF-8,CP866,Windows-1251');
        return $converted ?: $text;
    }
    return $text;
}
```

## WebSocket Terminal Protocol

### Client → Server Messages

```json
{
    "action": "connect",
    "host": "server.example.com",
    "port": 22,
    "username": "user",
    "password": "encrypted_password",
    "privateKey": "optional_key_path"
}
```

```json
{
    "action": "input",
    "data": "ls -la\n"
}
```

```json
{
    "action": "resize",
    "cols": 80,
    "rows": 24
}
```

### Server → Client Messages

```json
{
    "status": "connected",
    "message": "SSH connection established"
}
```

```json
{
    "output": "total 48\ndrwxr-xr-x 2 user user 4096 ..."
}
```

```json
{
    "status": "error",
    "message": "Connection failed: Authentication failed"
}
```

## SSH Authentication Methods

### 1. Password Authentication

```php
use phpseclib3\Net\SSH;

$ssh = new SSH($host, $port);
$auth = $ssh->login($username, $password);
```

### 2. SSH Key Authentication

```php
use phpseclib3\Crypt\PublicKeyLoader;

$privateKey = PublicKeyLoader::load($keyContent, $passphrase);
$ssh = new SSH($host, $port);
$auth = $ssh->login($username, $privateKey);
```

### 3. Key File Path

Users can specify a path to their private key file on the server.

## Terminal Features

### Supported Terminal Types

- xterm
- xterm-256color
- vt100

### Terminal Resize

The terminal supports dynamic resizing:
```javascript
term.onResize(({ cols, rows }) => {
    socket.send(JSON.stringify({
        action: 'resize',
        cols: cols,
        rows: rows
    }));
});
```

### Character Encoding

The handler ensures proper UTF-8 encoding for terminal output, with fallback conversions for:
- CP866 (DOS Russian)
- Windows-1251 (Windows Cyrillic)
- ISO-8859-1 (Latin-1)

## Security Considerations

### Environment Variables

Set these environment variables for production:

```bash
# SSH credential encryption key
export SSH_CREDENTIALS_CRYPT_KEY="your-secret-key-here"

# WebSocket server binding
export WEBSOCKET_HOST="127.0.0.1"
export WEBSOCKET_PORT="8080"
```

### Access Control

- SSH terminal requires authenticated session
- Credentials encrypted at rest
- Host and username validation prevents injection
- Session-based access control

### Best Practices

1. **Change default encryption key**: Set `SSH_CREDENTIALS_CRYPT_KEY` environment variable
2. **Use SSH keys**: Prefer key-based authentication over passwords
3. **Limit access**: Restrict SSH terminal to admin/manager roles
4. **Audit logs**: Log SSH connections for security auditing
5. **Timeout sessions**: Implement idle timeout for SSH sessions

## Frontend Integration

### HTML Structure

```html
<div id="terminal-container"></div>
<div class="ssh-controls">
    <input id="ssh-host" placeholder="Host">
    <input id="ssh-user" placeholder="Username">
    <input id="ssh-port" value="22" type="number">
    <button id="ssh-connect">Connect</button>
</div>
```

### JavaScript (xterm.js)

```javascript
import { Terminal } from 'xterm';
import { FitAddon } from 'xterm-addon-fit';

const term = new Terminal();
const fitAddon = new FitAddon();
term.loadAddon(fitAddon);
term.open(document.getElementById('terminal-container'));

// Connect to WebSocket
const socket = new WebSocket('wss://server/webssh/');

// Send input to server
term.onData(data => {
    socket.send(JSON.stringify({ action: 'input', data }));
});

// Display output from server
socket.onmessage = event => {
    const msg = JSON.parse(event.data);
    if (msg.output) {
        term.write(msg.output);
    }
};
```

## Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| Connection timeout | Check firewall, SSH port, host accessibility |
| Authentication failed | Verify credentials, check SSH key permissions |
| Garbled characters | Ensure UTF-8 locale on remote server |
| WebSocket disconnect | Check WebSocket server status, proxy configuration |

### Debug Mode

Enable debug logging:
```php
error_reporting(E_ALL);
ini_set('display_errors', '1');
```

Check logs:
```bash
tail -f /var/log/apache2/error.log
# Or for WebSocket server
journalctl -u webssh -f
```
