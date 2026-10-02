import { chromium } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const BASE_URL = process.env.APP_BASE_URL || 'http://localhost';
const OUT_DIR = process.env.PW_OUTPUT_DIR || process.env.PW_ARTIFACTS_DIR || '/tmp/perf-investigate';

const APPS = (process.env.APPS ? process.env.APPS.split(',').map(s => s.trim()).filter(Boolean) : [
  'cloud','dawarich','films','gitea','immich','kanboard','lopaka','recipes','smart_home','spends','steps'
]);

const E2E_EMAIL = process.env.E2E_USER_EMAIL || process.env.E2E_USER || process.env.E2E_EMAIL || 'root@root';
const E2E_PASSWORD = process.env.E2E_USER_PASSWORD || process.env.E2E_PASS || '12345T';

const SCREENSHOT_WAIT_MS = Number(process.env.SCREENSHOT_WAIT_MS || 8000);

function nowMs() { return Date.now(); }

async function acceptCookies(page) {
  const deviceId = '11111111111111111111111111111111';
  await page.evaluate((id) => {
    try { document.cookie = `ap_device_id=${id}; path=/; max-age=31536000; samesite=Lax`; } catch {}
  }, deviceId).catch(() => {});

  await page.evaluate(async (id) => {
    try {
      await fetch('/main?api=privacy&action=save_cookie_consent', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Device-Id': id },
        body: JSON.stringify({ status: 'accepted' })
      });
    } catch {}
  }, deviceId).catch(() => {});

  const overlay = page.locator('#cookie-consent-overlay');
  if (await overlay.first().isVisible().catch(() => false)) {
    await page.locator('#cookie-consent-accept').click({ timeout: 5000 }).catch(() => {});
    await overlay.evaluate(el => el.remove()).catch(() => {});
  }
}

async function login(page) {
  await page.goto(`${BASE_URL}/login?lang=en&theme=light&color=%232563eb`, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.locator('#login-content input[name="email"]').fill(E2E_EMAIL);
  await page.locator('#login-password').fill(E2E_PASSWORD);
  await Promise.all([
    page.waitForURL(u => !String(u.pathname).includes('/login'), { timeout: 30000 }),
    page.locator('#login-content button[name="login"]').click()
  ]);
  await acceptCookies(page);
}

async function evalIframeBestEffort(page) {
  return await page.evaluate(() => {
    const iframe = document.getElementById('app-iframe');
    if (!iframe) return { ok: false };
    let src = null;
    try { src = iframe.getAttribute('src'); } catch {}

    try {
      const doc = iframe.contentDocument;
      if (!doc) return { ok: false, src, same_origin: false };
      const bodyText = doc.body && doc.body.innerText ? String(doc.body.innerText) : '';
      return {
        ok: true,
        src,
        same_origin: true,
        readyState: doc.readyState,
        bodyTextLen: bodyText.length,
        imgCount: doc.images ? doc.images.length : 0,
        scriptCount: doc.scripts ? doc.scripts.length : 0,
        h1Count: doc.querySelectorAll ? doc.querySelectorAll('h1').length : null,
        iframeLocation: (doc.location && doc.location.href) ? String(doc.location.href) : null,
      };
    } catch {
      return { ok: true, src, same_origin: false };
    }
  });
}

async function getTopLongTasks(page) {
  try {
    return await page.evaluate(() => {
      const entries = (typeof performance !== 'undefined' && performance.getEntriesByType)
        ? performance.getEntriesByType('longtask') || []
        : [];
      const total = entries.reduce((sum, e) => sum + (e.duration || 0), 0);
      return { longTasksCount: entries.length, longTasksTotalMs: total };
    });
  } catch {
    return null;
  }
}

async function runOneApp(context, app) {
  const page = await context.newPage();
  const url = `${BASE_URL}/main?app=${encodeURIComponent(app)}&lang=en&theme=light&color=%232563eb`;

  const result = {
    app,
    url,
    status: 'ok',
    t_dom_ms: null,
    resources_duration_ms: null,
    console_errors: [],
    console_warnings: [],
    page_errors: [],
    network: {
      request_failed_count: 0,
      http_4xx_count: 0,
      http_5xx_count: 0,
      request_failed_samples: [],
      http_4xx_samples: [],
      http_5xx_samples: [],
    },
    iframe: {
      readyState: null,
      bodyTextLen: null,
      imgCount: null,
      scriptCount: null,
      same_origin: null,
      iframeLocation: null,
      src: null,
    },
    long_tasks_top: null,
    screenshot_path: null,
  };

  const push = (arr, v, max=25) => { if (arr.length < max) arr.push(v); };

  page.on('console', (msg) => {
    try {
      const type = msg.type ? msg.type() : 'other';
      const text = (() => { try { return msg.text(); } catch { return String(msg); } })();
      if (type === 'error') push(result.console_errors, text);
      else if (type === 'warning') push(result.console_warnings, text);
    } catch {}
  });

  page.on('pageerror', (err) => {
    try { push(result.page_errors, String(err?.message || err), 10); } catch {}
  });

  page.on('requestfailed', (req) => {
    result.network.request_failed_count++;
    try {
      if (result.network.request_failed_samples.length < 15) {
        result.network.request_failed_samples.push({
          url: req.url(),
          method: req.method(),
          errorText: req.failure()?.errorText || null,
        });
      }
    } catch {}
  });

  page.on('response', (resp) => {
    const status = resp.status();
    if (status >= 400 && status < 500) {
      result.network.http_4xx_count++;
      if (result.network.http_4xx_samples.length < 15) result.network.http_4xx_samples.push({ url: resp.url(), status });
    }
    if (status >= 500) {
      result.network.http_5xx_count++;
      if (result.network.http_5xx_samples.length < 15) result.network.http_5xx_samples.push({ url: resp.url(), status });
    }
  });

  const start = nowMs();
  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
    result.t_dom_ms = nowMs() - start;

    await page.waitForSelector('#app-iframe', { state: 'attached', timeout: 30000 });

    await page.waitForTimeout(SCREENSHOT_WAIT_MS);

    const perf = await page.evaluate(() => {
      try {
        const resources = performance.getEntriesByType('resource') || [];
        const durations = resources.map(r => r.duration).filter(n => typeof n === 'number' && Number.isFinite(n));
        const total = durations.length ? durations.reduce((a,b)=>a+b,0) : null;
        return { resources_duration_ms: total };
      } catch {
        return { resources_duration_ms: null };
      }
    });
    result.resources_duration_ms = perf.resources_duration_ms;

    result.iframe = await evalIframeBestEffort(page);

    result.long_tasks_top = await getTopLongTasks(page);

    fs.mkdirSync(OUT_DIR, { recursive: true });
    const screenshotPath = path.join(OUT_DIR, `investigate-${app}.png`);
    await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});
    result.screenshot_path = fs.existsSync(screenshotPath) ? screenshotPath : null;

    return result;
  } catch (e) {
    result.status = 'error';
    result.error = String(e?.message || e);
    result.t_dom_ms = result.t_dom_ms ?? (nowMs() - start);
    return result;
  } finally {
    await page.close().catch(() => {});
  }
}

async function main() {
  fs.mkdirSync(OUT_DIR, { recursive: true });

  const browser = await chromium.launch({ headless: true, args: ['--disable-setuid-sandbox'] });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'en-US' });

  const page = await context.newPage();
  try {
    await login(page);
  } finally {
    await page.close().catch(() => {});
  }

  const results = [];
  for (const app of APPS) {
    console.log(`Investigating ${app}...`);
    const r = await runOneApp(context, app);
    results.push(r);
    console.log(JSON.stringify({
      app: r.app,
      status: r.status,
      t_dom_ms: r.t_dom_ms,
      iframe_same_origin: r.iframe.same_origin,
      iframe_bodyTextLen: r.iframe.bodyTextLen,
      iframe_imgCount: r.iframe.imgCount,
      console_errors: r.console_errors.length,
      http_4xx: r.network.http_4xx_count,
    }));
  }

  await browser.close();
  const outPath = path.join(OUT_DIR, `investigate-${new Date().toISOString().replace(/[:.]/g,'-')}.json`);
  fs.writeFileSync(outPath, JSON.stringify({ baseUrl: BASE_URL, apps: APPS, waitMs: SCREENSHOT_WAIT_MS, results }, null, 2));
  console.log(`Wrote ${outPath}`);
}

main().catch(e => {
  console.error(e);
  process.exit(1);
});
