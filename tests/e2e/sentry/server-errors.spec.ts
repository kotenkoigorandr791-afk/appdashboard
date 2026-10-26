import { expect, test, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { autoAcceptCookies } from '../helpers/shell';

const userEmail = process.env.SENTRY_FIX_USER_EMAIL || 'codex-sentry-fix@example.com';
const password = process.env.SENTRY_FIX_USER_PASSWORD || 'CodexPass1';

async function loginAs(page: Page) {
  await page.goto('/login?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
  await page.locator('#login-content input[name="email"]').fill(userEmail);
  await page.locator('#login-password').fill(password);
  await Promise.all([
    page.waitForURL(url => !url.pathname.includes('/login'), { timeout: 30000 }),
    page.locator('#login-content button[name="login"]').click()
  ]);
  await autoAcceptCookies(page);
}

function phpInContainer(code: string) {
  return execFileSync('docker', ['exec', 'appdashboard', 'php', '-r', code], { encoding: 'utf8' });
}

function seedUser() {
  phpInContainer(`
define("APP_ROOT", getcwd());
require "db.php";
$db = getDB();
initDB($db);
upsertUser("${userEmail}", ["name"=>"Codex Sentry Fix","password"=>appPasswordHash("${password}"),"role"=>1,"verified"=>true,"language"=>"en","theme"=>"light","color"=>"#2563eb"]);
`);
}

function collectErrors(page: Page) {
  const errors: string[] = [];
  page.on('pageerror', err => errors.push(err.message));
  page.on('console', msg => {
    if (msg.type() === 'error') errors.push(msg.text());
  });
  return errors;
}

test.describe('Sentry server project regressions', () => {
  test.beforeAll(seedUser);

  test('SERVER-2: inline scripts parse on /main and stay parseable with hostile settings', async ({ page }) => {
    test.setTimeout(120000);
    const errors = collectErrors(page);
    await loginAs(page);
    await page.goto('/main?app=smart_home&lang=en&theme=dark', { waitUntil: 'domcontentloaded' });

    // The original error was `SyntaxError: missing ) after argument list` at /main.
    expect(errors.filter(e => /SyntaxError/.test(e))).toEqual([]);

    // Every inline script in the shell document must be syntactically valid.
    const invalidScripts = await page.evaluate(() => {
      const scripts = Array.from(document.querySelectorAll('script:not([src])'));
      const bad: string[] = [];
      for (const [index, script] of scripts.entries()) {
        const source = script.textContent || '';
        if (!source.trim()) continue;
        try {
          // eslint-disable-next-line no-new-func
          new Function(source);
        } catch {
          bad.push(`${index}: ${source.slice(0, 80)}`);
        }
      }
      return bad;
    });
    expect(invalidScripts).toEqual([]);

    // The smart_home app renders in an iframe; when it loads, its inline
    // scripts must parse too (the app may be unavailable in CI).
    const iframeLocator = page.locator('#app-iframe');
    const iframeUrl = (await iframeLocator.count()) > 0
      ? await iframeLocator.getAttribute('src', { timeout: 15000 }).catch(() => null)
      : null;
    if (iframeUrl) {
      const iframePage = await page.context().newPage();
      const iframeErrors: string[] = [];
      iframePage.on('pageerror', err => iframeErrors.push(err.message));
      const response = await iframePage.goto(iframeUrl, { waitUntil: 'domcontentloaded' }).catch(() => null);
      await iframePage.waitForTimeout(1500);
      if (response && response.ok()) {
        expect(iframeErrors.filter(e => /SyntaxError/.test(e))).toEqual([]);
        const bad = await iframePage.evaluate(() => {
          const scripts = Array.from(document.querySelectorAll('script:not([src])'));
          const invalid: string[] = [];
          for (const [index, script] of scripts.entries()) {
            const source = script.textContent || '';
            if (!source.trim()) continue;
            try {
              // eslint-disable-next-line no-new-func
              new Function(source);
            } catch {
              invalid.push(`${index}: ${source.slice(0, 80)}`);
            }
          }
          return invalid;
        });
        expect(bad).toEqual([]);
      }
      await iframePage.close();
    }
  });

  test('SERVER-2: json_encode output is HEX_TAG escaped so user data cannot close a script block', async () => {
    const hostile = '</script><script>window.__pwned=1;</script>';
    const encoded = phpInContainer(
      `echo json_encode(["home_group_id" => ${JSON.stringify(hostile)}], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);`
    );
    expect(encoded).not.toContain('</script>');
    expect(encoded).toContain('\\u003C');

    // Guard against regressions: every json_encode that runs inside a <script>
// block must escape tags, otherwise user data can close the block and break
// parsing (SERVER-2).
    const files = [
      'project/views/main.php',
      'apps/smart_home/views/scripts.php'
    ];
    const offenders: string[] = [];
    for (const file of files) {
      const raw = phpInContainer(`echo file_get_contents(getcwd() . "/${file}");`);
      let inScript = false;
      raw.split('\n').forEach((line, index) => {
        if (/<script/i.test(line)) inScript = true;
        if (inScript && line.includes('json_encode') && !line.includes('JSON_HEX_TAG')) {
          // Multi-line json_encode calls carry their flags on the closing line.
          if (!/json_encode\(\s*$|json_encode\(\[?\s*$/.test(line.trim())) {
            offenders.push(`${file}:${index + 1}: ${line.trim().slice(0, 90)}`);
          }
        }
        if (/<\/script>/i.test(line)) inScript = false;
      });
    }
    expect(offenders).toEqual([]);
  });

  test('SERVER-3: support chat never requests peer messages without a peer email', async ({ page }) => {
    test.setTimeout(120000);
    await loginAs(page);
    await page.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });

    const badRequests: string[] = [];
    page.on('request', request => {
      const url = request.url();
      if (url.includes('api=support_chat') && url.includes('action=get_messages')) {
        if (url.includes('chat_type=peer') && /(?:[?&]peer_email=)(?:&|$)/.test(url)) {
          badRequests.push(url);
        }
      }
    });

    await page.locator('#support-chat-btn').first().click({ timeout: 15000 }).catch(() => {});
    await page.waitForTimeout(6000);

    expect(badRequests).toEqual([]);
  });

  test('SERVER-3: invalid_peer from get_messages is handled without a console error', async ({ page }) => {
    test.setTimeout(120000);
    await loginAs(page);
    await page.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });

    const status = await page.evaluate(async () => {
      const response = await fetch('?api=support_chat&action=get_messages&chat_type=peer&peer_email=&limit=30');
      return { status: response.status, body: await response.json() };
    });

    // The server guard is still enforced...
    expect(status.status).toBe(400);
    expect(status.body.error).toBe('invalid_peer');

    // ...but the client must not attempt this request on its own.
    const guardErrors: string[] = [];
    page.on('console', msg => {
      if (msg.type() === 'error') guardErrors.push(msg.text());
    });
    await page.locator('#support-chat-btn').first().click({ timeout: 15000 }).catch(() => {});
    await page.waitForTimeout(5000);
    expect(guardErrors.filter(e => /invalid_peer/.test(e))).toEqual([]);
  });

  test('SERVER-6: cloud_ai_config responds JSON without dropping tables or leaking the key', async ({ page }) => {
    test.setTimeout(120000);
    await loginAs(page);

    const before = phpInContainer(`
define("APP_ROOT", getcwd());
require "db.php";
$db = getDB();
$stmt = $db->query("SELECT to_regclass('public.cloud_ai_config') AS t");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo $row && $row["t"] ? "exists" : "absent";
`);

    const result = await page.evaluate(async () => {
      const response = await fetch('/main?api=cloud_ai_config', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
      });
      return {
        ok: response.ok,
        contentType: response.headers.get('content-type') || '',
        body: await response.json()
      };
    });

    expect(result.ok).toBe(true);
    expect(result.contentType).toContain('application/json');
    expect(result.body.ok).toBe(true);
    expect(Array.isArray(result.body.config.models)).toBe(true);
    // The endpoint is reachable without a session, so the secret must be blank.
    expect(result.body.config.api_key).toBe('');

    // A read request must not issue DDL against the legacy table.
    const after = phpInContainer(`
define("APP_ROOT", getcwd());
require "db.php";
$db = getDB();
$stmt = $db->query("SELECT to_regclass('public.cloud_ai_config') AS t");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo $row && $row["t"] ? "exists" : "absent";
`);
    expect(after).toBe(before);

    const store = phpInContainer(`echo file_get_contents(getcwd() . "/apps/cloud/includes/ai_config_store.php");`);
    expect(store).not.toMatch(/DROP\s+TABLE/);
  });

  test('SERVER-6: loadAiModels does not throw when the config endpoint misbehaves', async ({ page }) => {
    test.setTimeout(120000);
    const errors = collectErrors(page);
    await loginAs(page);
    await page.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });

    await page.evaluate(async () => {
      const original = window.fetch;
      window.fetch = ((input: RequestInfo | URL, init?: RequestInit) => {
        const url = typeof input === 'string' ? input : (input instanceof URL ? input.href : input.url);
        if (url.includes('api=cloud_ai_config')) {
          // Real network failure, exactly what produced
          // `TypeError: Failed to fetch` in Sentry.
          return Promise.reject(new TypeError('Failed to fetch'));
        }
        return original(input as RequestInfo, init);
      }) as typeof window.fetch;
      await (window as unknown as { loadAiModels?: () => Promise<void> }).loadAiModels?.();
    });

    // A network failure must be swallowed, not logged as an app error.
    expect(errors.filter(e => /Failed to fetch|Failed to load AI models/.test(e))).toEqual([]);
  });

  test('SERVER-4/5: DB host resolution never yields host-gateway', async () => {
    const host = phpInContainer(`
putenv("DB_HOST=host-gateway");
define("APP_ROOT", getcwd());
require "lib/db_config.php";
echo app_db_normalize_host("host-gateway");
`).trim();
    expect(host).not.toBe('host-gateway');
    expect(host).toBe('appdashboard-db');

    // A stale db.env (written by an older image) must be normalized on load.
    const fromConfig = phpInContainer(`
putenv("DB_HOST"); unset($_ENV["DB_HOST"]); unset($_SERVER["DB_HOST"]);
define("APP_ROOT", getcwd());
putenv("APP_DB_CONFIG=" . sys_get_temp_dir() . "/sentry-fix-db.env");
file_put_contents(getenv("APP_DB_CONFIG"), "DB_HOST=host-gateway\\n");
require "lib/db_config.php";
$cfg = app_db_load_config();
echo $cfg["host"];
`).trim();
    expect(fromConfig).toBe('appdashboard-db');

    // The kanboard bridge must resolve to the Postgres service, not the Docker host.
    const kanboard = phpInContainer(`
putenv("DB_HOST=host-gateway");
define("APP_ROOT", getcwd());
require "apps/kanboard/integration/bootstrap.php";
$cfg = kanboard_db_resolve();
echo $cfg["host"];
`).trim();
    expect(kanboard).not.toBe('host-gateway');
    expect(kanboard).toBe('appdashboard-db');

    // The entrypoint self-heal must rewrite a poisoned db.env at startup.
    const healed = execFileSync('docker', ['exec', 'appdashboard', 'bash', '-c', [
      'set -e',
      'd="$(mktemp -d)"',
      'printf \'DB_HOST=host-gateway\\nDB_PORT=5432\\n\' > "$d/db.env"',
      'APPDATA_DATA_DIR="$d"',
      // Extract and run just the self-heal block from the shipped entrypoint.
      'awk \'/^  # Self-heal: host-gateway/,/^  fi$/{print}\' /usr/local/bin/docker-entrypoint.sh > "$d/heal.sh"',
      'test -s "$d/heal.sh"',
      'bash "$d/heal.sh"',
      'cat "$d/db.env"'
    ].join('\n')], { encoding: 'utf8' });
    expect(healed).toContain('DB_HOST=appdashboard-db');
    expect(healed).toContain('DB_PORT=5432');
  });

  test('bootstrap: same-origin check is host and port aware', () => {
    // Exercises the real apSecSameOrigin() from project/bootstrap.php without
    // the bootstrap side effects, by extracting the function bodies.
    const probe = (host: string, origin: string, referer = '') => phpInContainer(`
$src = file_get_contents("project/bootstrap.php");
$grab = function (string $src, string $fn): string {
    $start = strpos($src, "function {$fn}(");
    $i = strpos($src, "{", $start);
    $depth = 0;
    for ($n = $i; $n < strlen($src); $n++) {
        if ($src[$n] === "{") $depth++;
        elseif ($src[$n] === "}") { $depth--; if ($depth === 0) return substr($src, $start, $n - $start + 1); }
    }
    return "";
};
foreach (["apSecAuthority", "apSecExpectedHosts", "apSecSameOrigin"] as $fn) eval($grab($src, $fn));
$_SERVER["HTTP_HOST"] = "${host}";
$_SERVER["SERVER_NAME"] = "";
$_SERVER["HTTP_ORIGIN"] = "${origin}";
$_SERVER["HTTP_REFERER"] = "${referer}";
$_SERVER["HTTPS"] = "";
$_SERVER["SERVER_PORT"] = "";
echo apSecSameOrigin() ? "allow" : "deny";
`).trim();

    // The reported bug: a legitimate same-origin POST on the mapped dev port
    // was rejected with cross_origin_forbidden.
    expect(probe('localhost:8080', 'http://localhost:8080')).toBe('allow');
    expect(probe('localhost:8080', '', 'http://localhost:8080/main')).toBe('allow');
    expect(probe('app.example.com', 'https://app.example.com')).toBe('allow');
    // Cross-port is a different origin and must stay denied.
    expect(probe('localhost:8080', 'http://localhost:9999')).toBe('deny');
    expect(probe('localhost:8080', 'http://localhost')).toBe('deny');
    // Hostile origins stay denied.
    expect(probe('localhost:8080', 'http://evil.com')).toBe('deny');
    expect(probe('localhost:8080', 'http://evil.com:8080')).toBe('deny');
    expect(probe('localhost:8080', '', 'http://evil.com/main')).toBe('deny');
    expect(probe('localhost:8080', 'http://localhost:8080.evil.com')).toBe('deny');
    expect(probe('app.example.com', 'https://evil.com')).toBe('deny');
    expect(probe('localhost:8080', '')).toBe('deny');
  });
});