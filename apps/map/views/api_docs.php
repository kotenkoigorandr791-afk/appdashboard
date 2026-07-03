<?php
function renderApiDocs() {
    global $lang, $theme, $color, $lang_array, $view_mode, $display_mode;
    $t = $lang_array;
    $d = [
        'title' => $t['api_docs_title'] ?? 'API Documentation',
        'intro' => $t['api_docs_intro'] ?? '',
        'auth_title' => $t['api_docs_auth_title'] ?? 'Authentication',
        'auth_desc' => $t['api_docs_auth_desc'] ?? '',
        'gen_token_title' => $t['api_docs_gen_token_title'] ?? 'Generate Token',
        'gen_token_desc' => $t['api_docs_gen_token_desc'] ?? '',
        'get_token_title' => $t['api_docs_get_token_title'] ?? 'Get Token',
        'get_token_desc' => $t['api_docs_get_token_desc'] ?? '',
        'send_loc_title' => $t['api_docs_send_loc_title'] ?? 'Send Location',
        'send_loc_desc' => $t['api_docs_send_loc_desc'] ?? '',
        'get_loc_title' => $t['api_docs_get_loc_title'] ?? 'Get Location',
        'get_loc_desc' => $t['api_docs_get_loc_desc'] ?? '',
        'del_token_title' => $t['api_docs_del_token_title'] ?? 'Delete Token',
        'del_token_desc' => $t['api_docs_del_token_desc'] ?? '',
        'params' => $t['api_docs_params'] ?? 'Parameters',
        'response' => $t['api_docs_response'] ?? 'Response',
        'example' => $t['api_docs_example'] ?? 'Example',
        'required' => $t['api_docs_required'] ?? 'required',
        'optional' => $t['api_docs_optional'] ?? 'optional',
        'back' => $t['back'] ?? 'Back',
    ];
    $backUrl = "?lang={$lang}&theme={$theme}&color=" . urlencode($color) . "&view_mode={$view_mode}&display_mode={$display_mode}";
    ob_start(); ?>
    <!DOCTYPE html>
    <html lang="<?= $lang ?>" data-theme="<?= $theme ?>">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($d['title']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --primary-color: <?= $color ?>; --bg-color: <?= $theme === 'dark' ? '#1a1a1a' : '#f4f6f8' ?>; --text-color: <?= $theme === 'dark' ? '#e8e8e8' : '#1a1a1a' ?>; --card-bg: <?= $theme === 'dark' ? '#242424' : '#ffffff' ?>; --border-color: <?= $theme === 'dark' ? '#333' : '#e0e4ea' ?>; --muted: <?= $theme === 'dark' ? '#888' : '#666' ?>; --code-bg: <?= $theme === 'dark' ? '#111' : '#f0f4f8' ?>; --scrollbar-bg: <?= $theme === 'dark' ? '#111' : '#e8ecf0' ?>; --scrollbar-thumb: <?= $theme === 'dark' ? '#444' : '#b0b8c4' ?>; --method-get: #2da44e; --method-post: #0969da; --method-delete: #cf222e; }
        ::-webkit-scrollbar { width: 10px; height: 10px; }
        ::-webkit-scrollbar-track { background: var(--scrollbar-bg); border-radius: 6px; }
        ::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 6px; border: 2px solid var(--scrollbar-bg); }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary-color); }
        * { scrollbar-width: thin; scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-bg); }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg-color); color: var(--text-color); min-height: 100vh; line-height: 1.6; }
        .page { max-width: 820px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }
        .modal-close { position: fixed; top: 1.25rem; right: 1.25rem; width: 46px; height: 46px; min-width: 46px; padding: 0; border-radius: 999px; border: 2px solid rgba(220,38,38,0.72); background: rgba(220,38,38,0.08); color: #dc2626; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: transform 0.14s ease, background 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease; box-shadow: inset 0 1px 0 rgba(255,255,255,0.1); z-index: 100; text-decoration: none; }
        .modal-close:hover { background: rgba(220,38,38,0.14); border-color: rgba(220,38,38,0.92); box-shadow: 0 10px 24px rgba(220,38,38,0.16); }
        .modal-close:active { transform: scale(0.9) rotate(8deg); background: rgba(220,38,38,0.2); }
        .modal-close svg { width: 18px; height: 18px; stroke: currentColor; fill: none; }
        h1 { font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; padding-right: 3.5rem; }
        .intro { color: var(--muted); margin-bottom: 2.5rem; font-size: 1rem; }
        .section { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; margin-bottom: 1.5rem; overflow: hidden; }
        .section-header { padding: 1.1rem 1.5rem; display: flex; align-items: center; gap: 1rem; border-bottom: 1px solid var(--border-color); flex-wrap: wrap; }
        .method { font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.55rem; border-radius: 6px; letter-spacing: 0.04em; color: #fff; flex-shrink: 0; }
        .method.get { background: var(--method-get); }
        .method.post { background: var(--method-post); }
        .method.delete { background: var(--method-delete); }
        .endpoint { font-family: 'SFMono-Regular', Consolas, monospace; font-size: 0.92rem; font-weight: 600; }
        .section-title { font-weight: 700; font-size: 1.05rem; margin-left: auto; }
        .section-body { padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 1.1rem; }
        .desc { color: var(--muted); font-size: 0.95rem; }
        .params-label { font-size: 0.78rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); margin-bottom: 0.5rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        th { text-align: left; padding: 0.4rem 0.75rem; font-weight: 600; color: var(--muted); background: var(--code-bg); }
        td { padding: 0.5rem 0.75rem; border-top: 1px solid var(--border-color); vertical-align: top; }
        .badge { font-size: 0.72rem; padding: 0.15rem 0.45rem; border-radius: 4px; font-weight: 600; }
        .badge.req { background: <?= $theme === 'dark' ? 'rgba(13,110,253,0.2)' : '#dbeafe' ?>; color: <?= $theme === 'dark' ? '#93c5fd' : '#1d4ed8' ?>; }
        .badge.opt { background: <?= $theme === 'dark' ? 'rgba(0,0,0,0.3)' : '#f0f4f8' ?>; color: var(--muted); }
        code { font-family: 'SFMono-Regular', Consolas, monospace; background: var(--code-bg); padding: 0.1rem 0.35rem; border-radius: 4px; font-size: 0.88em; }
        pre { background: var(--code-bg); border: 1px solid var(--border-color); border-radius: 10px; padding: 1rem 1.25rem; overflow-x: auto; font-family: 'SFMono-Regular', Consolas, monospace; font-size: 0.83rem; line-height: 1.6; }
        .auth-box { background: <?= $theme === 'dark' ? 'rgba(255,200,0,0.08)' : '#fffbeb' ?>; border: 1px solid <?= $theme === 'dark' ? 'rgba(255,200,0,0.2)' : '#fde68a' ?>; border-radius: 12px; padding: 1rem 1.25rem; font-size: 0.93rem; margin-bottom: 2rem; }
        .auth-box strong { display: block; margin-bottom: 0.3rem; }
        .copy-md-btn { display: inline-flex; align-items: center; gap: 0.6rem; padding: 0.65rem 1.2rem; border-radius: 999px; border: 1.5px solid var(--primary-color); background: transparent; color: var(--primary-color); font-size: 0.88rem; font-weight: 600; cursor: pointer; transition: background 0.18s, color 0.18s, transform 0.12s, box-shadow 0.18s; margin-bottom: 2rem; }
        .copy-md-btn:hover { background: var(--primary-color); color: #fff; box-shadow: 0 6px 18px color-mix(in srgb, var(--primary-color) 35%, transparent); }
        .copy-md-btn:active { transform: scale(0.93); }
        .copy-md-btn.copied { background: #2da44e; border-color: #2da44e; color: #fff; }
        .copy-md-btn svg { width: 15px; height: 15px; flex-shrink: 0; transition: transform 0.22s cubic-bezier(.34,1.56,.64,1); }
        .copy-md-btn.copied svg { transform: scale(1.3) rotate(-10deg); }
        @keyframes pop { 0%{transform:scale(1)} 40%{transform:scale(1.18)} 70%{transform:scale(0.94)} 100%{transform:scale(1)} }
        .copy-md-btn.pop { animation: pop 0.38s ease; }
        @media (max-width: 600px) { h1 { font-size: 1.5rem; } .section-title { margin-left: 0; width: 100%; } .modal-close { top: 0.75rem; right: 0.75rem; } }
    </style>
    </head>
    <body>
    <a href="<?= htmlspecialchars($backUrl) ?>" class="modal-close" aria-label="Close">
        <svg viewBox="0 0 24 24" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
    </a>
    <div class="page">
        <h1><?= htmlspecialchars($d['title']) ?></h1>
        <button class="copy-md-btn" id="copy-md-btn" aria-label="Copy as Markdown">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
            <span id="copy-md-label">Copy as Markdown</span>
        </button>
        <p class="intro"><?= htmlspecialchars($d['intro']) ?></p>
        <div class="auth-box">
            <strong><?= htmlspecialchars($d['auth_title']) ?></strong>
            <?= $d['auth_desc'] ?>
        </div>
        <div class="section">
            <div class="section-header">
                <span class="method post">POST</span>
                <span class="endpoint">/map/api/token</span>
                <span class="section-title"><?= htmlspecialchars($d['gen_token_title']) ?></span>
            </div>
            <div class="section-body">
                <p class="desc"><?= htmlspecialchars($d['gen_token_desc']) ?></p>
                <div>
                    <div class="params-label"><?= htmlspecialchars($d['response']) ?></div>
                    <pre>{"success": true, "token": "abc123...", "expires_at": "2026-05-28T..."}</pre>
                </div>
                <div>
                    <div class="params-label"><?= htmlspecialchars($d['example']) ?></div>
                    <pre>curl -X POST /map/api/token \
     -b "PHPSESSID=your_session_id"</pre>
                </div>
            </div>
        </div>
        <div class="section">
            <div class="section-header">
                <span class="method get">GET</span>
                <span class="endpoint">/map/api/tokens</span>
                <span class="section-title"><?= htmlspecialchars($d['get_token_title']) ?></span>
            </div>
            <div class="section-body">
                <p class="desc"><?= htmlspecialchars($d['get_token_desc']) ?></p>
                <div>
                    <div class="params-label"><?= htmlspecialchars($d['response']) ?></div>
                    <pre>{"success": true, "tokens": [{"token": "abc123...", "expires_at": "...", "is_active": 1}]}</pre>
                </div>
            </div>
        </div>
        <div class="section">
            <div class="section-header">
                <span class="method post">POST</span>
                <span class="endpoint">/map/api/location</span>
                <span class="section-title"><?= htmlspecialchars($d['send_loc_title']) ?></span>
            </div>
            <div class="section-body">
                <p class="desc"><?= htmlspecialchars($d['send_loc_desc']) ?></p>
                <div>
                    <div class="params-label"><?= htmlspecialchars($d['params']) ?></div>
                    <table>
                        <tr><th><?= htmlspecialchars($t['name'] ?? 'Name') ?></th><th>Type</th><th></th><th><?= htmlspecialchars($t['actions'] ?? 'Description') ?></th></tr>
                        <tr><td><code>token</code></td><td>string</td><td><span class="badge req"><?= htmlspecialchars($d['required']) ?></span></td><td>Bearer token</td></tr>
                        <tr><td><code>latitude</code></td><td>float</td><td><span class="badge req"><?= htmlspecialchars($d['required']) ?></span></td><td>-90 … 90</td></tr>
                        <tr><td><code>longitude</code></td><td>float</td><td><span class="badge req"><?= htmlspecialchars($d['required']) ?></span></td><td>-180 … 180</td></tr>
                        <tr><td><code>accuracy</code></td><td>float</td><td><span class="badge opt"><?= htmlspecialchars($d['optional']) ?></span></td><td><?= htmlspecialchars($t['accuracy'] ?? 'Accuracy') ?> (m)</td></tr>
                        <tr><td><code>message</code></td><td>string</td><td><span class="badge opt"><?= htmlspecialchars($d['optional']) ?></span></td><td><?= htmlspecialchars($t['message'] ?? 'Message') ?></td></tr>
                        <tr><td><code>photo</code></td><td>file</td><td><span class="badge opt"><?= htmlspecialchars($d['optional']) ?></span></td><td>multipart/form-data</td></tr>
                    </table>
                </div>
                <div>
                    <div class="params-label"><?= htmlspecialchars($d['example']) ?></div>
                    <pre>curl -X POST "/map/api/location?token=abc123" \
     -d "latitude=50.4501&longitude=30.5234&accuracy=10&message=Hello"</pre>
                </div>
                <div>
                    <div class="params-label"><?= htmlspecialchars($d['response']) ?></div>
                    <pre>{"success": true, "id": 42}</pre>
                </div>
            </div>
        </div>
        <div class="section">
            <div class="section-header">
                <span class="method get">GET</span>
                <span class="endpoint">/map/api/location</span>
                <span class="section-title"><?= htmlspecialchars($d['get_loc_title']) ?></span>
            </div>
            <div class="section-body">
                <p class="desc"><?= htmlspecialchars($d['get_loc_desc']) ?></p>
                <div>
                    <div class="params-label"><?= htmlspecialchars($d['params']) ?></div>
                    <table>
                        <tr><th><?= htmlspecialchars($t['name'] ?? 'Name') ?></th><th>Type</th><th></th><th></th></tr>
                        <tr><td><code>token</code></td><td>string</td><td><span class="badge req"><?= htmlspecialchars($d['required']) ?></span></td><td>Bearer token</td></tr>
                        <tr><td><code>limit</code></td><td>int</td><td><span class="badge opt"><?= htmlspecialchars($d['optional']) ?></span></td><td>1 – 100 (default 10)</td></tr>
                    </table>
                </div>
                <div>
                    <div class="params-label"><?= htmlspecialchars($d['example']) ?></div>
                    <pre>curl "/map/api/location?token=abc123&limit=1"</pre>
                </div>
                <div>
                    <div class="params-label"><?= htmlspecialchars($d['response']) ?></div>
                    <pre>{"success": true, "locations": [{"latitude": 50.45, "longitude": 30.52, "accuracy": 10, "message": "Hello", "timestamp": "..."}]}</pre>
                </div>
            </div>
        </div>
        <div class="section">
            <div class="section-header">
                <span class="method delete">DELETE</span>
                <span class="endpoint">/map/api/token/{token}</span>
                <span class="section-title"><?= htmlspecialchars($d['del_token_title']) ?></span>
            </div>
            <div class="section-body">
                <p class="desc"><?= htmlspecialchars($d['del_token_desc']) ?></p>
                <div>
                    <div class="params-label"><?= htmlspecialchars($d['example']) ?></div>
                    <pre>curl -X DELETE /map/api/token/abc123 \
     -b "PHPSESSID=your_session_id"</pre>
                </div>
                <div>
                    <div class="params-label"><?= htmlspecialchars($d['response']) ?></div>
                    <pre>{"success": true}</pre>
                </div>
            </div>
        </div>
    </div>
    <script>
    const MD = `# Routes Manager — Location Sharing API

Base URL: \`/map/api\`

This API allows you to programmatically send and retrieve GPS coordinates via a bearer token.
All endpoints return JSON. Authentication uses a per-user token (valid 30 days) that you generate
from the app's Settings panel. One active token per user is allowed at a time.

---

## Authentication

Include your token as a query parameter or an HTTP header:

\`\`\`
?token=YOUR_TOKEN
Authorization: Bearer YOUR_TOKEN
\`\`\`

---

## Endpoints

### 1. Generate Token

\`\`\`
POST /map/api/token
\`\`\`

Generates (or replaces) your location-sharing token. Requires a valid browser session cookie
(\`PHPSESSID\`). Returns the new token and its expiry timestamp.

**Request**
No body required. Session cookie must be present.

**Response**
\`\`\`json
{
  "success": true,
  "token": "a3f8c2d1e4b5...",
  "expires_at": "2026-05-28T18:00:00"
}
\`\`\`

**Example**
\`\`\`bash
curl -X POST https://yourhost/map/api/token \\
     -b "PHPSESSID=your_session_id"
\`\`\`

---

### 2. Get Current Token

\`\`\`
GET /map/api/tokens
\`\`\`

Returns your current active token. Requires session cookie.

**Response**
\`\`\`json
{
  "success": true,
  "tokens": [
    {
      "token": "a3f8c2d1e4b5...",
      "created_at": "2026-04-28T18:00:00",
      "expires_at": "2026-05-28T18:00:00",
      "is_active": 1
    }
  ]
}
\`\`\`

**Example**
\`\`\`bash
curl https://yourhost/map/api/tokens \\
     -b "PHPSESSID=your_session_id"
\`\`\`

---

### 3. Send Location

\`\`\`
POST /map/api/location
\`\`\`

Submits a GPS coordinate record. Token authentication required.

**Parameters**

| Name       | Type   | Required | Description                        |
|------------|--------|----------|------------------------------------|
| token      | string | ✓        | Your bearer token                  |
| latitude   | float  | ✓        | Latitude in degrees (−90 to 90)    |
| longitude  | float  | ✓        | Longitude in degrees (−180 to 180) |
| accuracy   | float  |          | GPS accuracy in metres             |
| message    | string |          | Optional text note                 |
| photo      | file   |          | Optional image (multipart/form-data)|

**Response**
\`\`\`json
{ "success": true, "id": 42 }
\`\`\`

**Examples**

Basic (form-encoded):
\`\`\`bash
curl -X POST "https://yourhost/map/api/location?token=a3f8c2d1e4b5" \\
     -d "latitude=50.4501&longitude=30.5234&accuracy=10&message=I+am+here"
\`\`\`

With photo (multipart):
\`\`\`bash
curl -X POST "https://yourhost/map/api/location?token=a3f8c2d1e4b5" \\
     -F "latitude=50.4501" \\
     -F "longitude=30.5234" \\
     -F "accuracy=5" \\
     -F "message=Checkpoint" \\
     -F "photo=@/path/to/photo.jpg"
\`\`\`

JavaScript (browser / Node):
\`\`\`js
const TOKEN = 'a3f8c2d1e4b5...';
navigator.geolocation.getCurrentPosition(async pos => {
  const body = new URLSearchParams({
    latitude:  pos.coords.latitude,
    longitude: pos.coords.longitude,
    accuracy:  pos.coords.accuracy,
    message:   'Auto update'
  });
  const res = await fetch(\`/map/api/location?token=\${TOKEN}\`, {
    method: 'POST',
    body
  });
  const data = await res.json();
  console.log(data); // { success: true, id: 43 }
});
\`\`\`

Python:
\`\`\`python
import requests

TOKEN = 'a3f8c2d1e4b5...'
requests.post(
    f'https://yourhost/map/api/location?token={TOKEN}',
    data={
        'latitude': 50.4501,
        'longitude': 30.5234,
        'accuracy': 10,
        'message': 'Python client'
    }
)
\`\`\`

---

### 4. Get Location

\`\`\`
GET /map/api/location?token=TOKEN[&limit=N]
\`\`\`

Returns the most recent location records for the given token.

**Parameters**

| Name  | Type | Required | Description                        |
|-------|------|----------|------------------------------------|
| token | string | ✓      | Your bearer token                  |
| limit | int  |          | Number of records to return (1–100, default 10) |

**Response**
\`\`\`json
{
  "success": true,
  "locations": [
    {
      "latitude": 50.4501,
      "longitude": 30.5234,
      "accuracy": 10,
      "message": "I am here",
      "photo_path": null,
      "timestamp": "2026-04-28T18:05:00"
    }
  ]
}
\`\`\`

**Example**
\`\`\`bash
curl "https://yourhost/map/api/location?token=a3f8c2d1e4b5&limit=1"
\`\`\`

---

### 5. Revoke Token

\`\`\`
DELETE /map/api/token/{token}
\`\`\`

Permanently deactivates the specified token. Requires session cookie.

**Response**
\`\`\`json
{ "success": true }
\`\`\`

**Example**
\`\`\`bash
curl -X DELETE https://yourhost/map/api/token/a3f8c2d1e4b5 \\
     -b "PHPSESSID=your_session_id"
\`\`\`

---

## Integration Notes

- Replace \`yourhost\` with the actual domain where the app is hosted.
- Replace \`a3f8c2d1e4b5...\` with the token you generate in Settings.
- Tokens expire after **30 days**. Generate a new one from the app or automate renewal via \`POST /map/api/token\`.
- To track a device continuously, call \`POST /map/api/location\` on a timer (e.g. every 30 s with \`setInterval\`).
- The \`photo\` field must be sent as \`multipart/form-data\`; all other fields accept \`application/x-www-form-urlencoded\`.
`;

    function copyText(text) {
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            return navigator.clipboard.writeText(text);
        }

        return new Promise((resolve, reject) => {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.left = '-999999px';
            textarea.style.top = '-999999px';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();

            try {
                if (document.execCommand('copy')) {
                    resolve();
                } else {
                    reject(new Error('Copy command failed'));
                }
            } catch (error) {
                reject(error);
            } finally {
                document.body.removeChild(textarea);
            }
        });
    }

    document.getElementById('copy-md-btn').addEventListener('click', function () {
        copyText(MD).then(() => {
            const btn = this;
            const label = document.getElementById('copy-md-label');
            btn.classList.add('copied', 'pop');
            label.textContent = 'Copied!';
            setTimeout(() => {
                btn.classList.remove('copied');
                label.textContent = 'Copy as Markdown';
            }, 2200);
            setTimeout(() => btn.classList.remove('pop'), 400);
        }).catch((error) => {
            console.error('Error copying Markdown:', error);
        });
    });
    </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>
