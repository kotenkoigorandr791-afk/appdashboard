import { expect, test, type Browser, type BrowserContext, type Page } from '@playwright/test';
import { autoAcceptCookies } from '../helpers/shell';

/**
 * Remember-me / trusted-device coverage for the shell.
 *
 * The shell stores the PHP session in Redis, the device id in `ap_device_id`,
 * a WebGL hash in `ap_gpu_fingerprint`, and the trust itself in per-email
 * `trusted_device_*` cookies backed by Postgres. These tests exercise the
 * whole chain: a normal remember-me login, browser restart, loss of the PHP
 * session cookie, dirty/foreign cookies, and fingerprint rendering.
 */

const USER_EMAIL = process.env.REMEMBER_ME_USER_EMAIL || 'pw-storage-user@example.com';
const USER_PASSWORD = process.env.REMEMBER_ME_USER_PASSWORD || 'CodexPass1';
const LOGIN_PATH = '/login?lang=en&theme=light&color=%232563eb';

async function openLogin(page: Page) {
  await page.goto(LOGIN_PATH, { waitUntil: 'domcontentloaded' });
  await autoAcceptCookies(page);
  await expect(page.locator('#login-content input[name="email"]')).toBeVisible();
}

async function loginWithRemember(page: Page) {
  await openLogin(page);
  await page.locator('#login-content input[name="email"]').fill(USER_EMAIL);
  await page.locator('#login-password').fill(USER_PASSWORD);
  const checkbox = page.locator('#login-content input[name="remember_me"]');
  if (!(await checkbox.isChecked())) {
    await page.locator('#login-content label.checkbox-label').click();
  }
  await expect(checkbox).toBeChecked();
  await Promise.all([
    page.waitForURL(url => !url.pathname.includes('/login'), { timeout: 30000, waitUntil: 'domcontentloaded' }),
    page.locator('#login-content button[name="login"]').click(),
  ]);
  await expect(page).not.toHaveURL(/\/login/);
}

async function expectLoggedIn(page: Page, path = '/main?lang=en') {
  const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
  expect(response?.status() ?? 0).toBeLessThan(400);
  await expect(page).not.toHaveURL(/\/login/);
  await expect(page.locator('#auth-modal')).toHaveCount(0);
}

function findCookie(cookies: Array<{ name: string }>, predicate: (name: string) => boolean) {
  return cookies.find(cookie => predicate(cookie.name));
}

async function contextWithCookies(browser: Browser, storageState: Awaited<ReturnType<BrowserContext['storageState']>>, baseURL: string) {
  return browser.newContext({ storageState, baseURL });
}

test.describe('remember-me and trusted devices', () => {
  test('remember-me survives a browser restart', async ({ page, context, browser }, testInfo) => {
    await loginWithRemember(page);

    const cookies = await context.cookies();
    const phpSession = findCookie(cookies, name => name === 'PHPSESSID');
    expect(phpSession, 'PHP session cookie must exist').toBeTruthy();
    expect(phpSession?.expires ?? 0, 'remember-me must persist the session cookie').toBeGreaterThan(Date.now() / 1000 + 30 * 24 * 3600);

    const trusted = cookies.filter(cookie => /^trusted_device_[a-f0-9]{16}$/.test(cookie.name));
    expect(trusted.length, 'a per-email trusted-device cookie must be set').toBeGreaterThan(0);
    expect(trusted[0].expires).toBeGreaterThan(Date.now() / 1000 + 30 * 24 * 3600);
    expect(findCookie(cookies, name => name === 'trusted_device_manifest')).toBeTruthy();
    expect(findCookie(cookies, name => name === 'ap_device_id')).toBeTruthy();

    const storageState = await context.storageState();
    const restarted = await contextWithCookies(browser, storageState, testInfo.project.use.baseURL!);
    const freshPage = await restarted.newPage();
    try {
      await expectLoggedIn(freshPage);
    } finally {
      await restarted.close();
    }
  });

  test('trusted device restores the account after the PHP session cookie is lost', async ({ page, context, browser }, testInfo) => {
    await loginWithRemember(page);

    const storageState = await context.storageState();
    storageState.cookies = storageState.cookies.filter(cookie => cookie.name !== 'PHPSESSID');
    const restarted = await contextWithCookies(browser, storageState, testInfo.project.use.baseURL!);
    const freshPage = await restarted.newPage();
    try {
      await expectLoggedIn(freshPage);
      const restoredCookies = await restarted.cookies();
      expect(findCookie(restoredCookies, name => name === 'PHPSESSID'), 'restore must create a new PHP session').toBeTruthy();
    } finally {
      await restarted.close();
    }
  });

  test('trusted device restores after the browser device id is regenerated', async ({ page, context, browser }, testInfo) => {
    await loginWithRemember(page);

    const storageState = await context.storageState();
    storageState.cookies = storageState.cookies.filter(cookie => cookie.name !== 'PHPSESSID');
    const deviceIdCookie = storageState.cookies.find(cookie => cookie.name === 'ap_device_id');
    expect(deviceIdCookie, 'ap_device_id cookie must exist').toBeTruthy();
    deviceIdCookie!.value = 'b'.repeat(32);

    const restarted = await contextWithCookies(browser, storageState, testInfo.project.use.baseURL!);
    const freshPage = await restarted.newPage();
    try {
      await expectLoggedIn(freshPage);
    } finally {
      await restarted.close();
    }
  });

  test('corrupted hint/manifest cookies still restore from the account cookie', async ({ page, context, browser }, testInfo) => {
    await loginWithRemember(page);

    const storageState = await context.storageState();
    storageState.cookies = storageState.cookies.filter(cookie => cookie.name !== 'PHPSESSID');
    for (const cookie of storageState.cookies) {
      if (cookie.name === 'trusted_device_manifest' || cookie.name === 'trusted_device_hint') {
        cookie.value = 'corrupted';
      }
    }

    const restarted = await contextWithCookies(browser, storageState, testInfo.project.use.baseURL!);
    const freshPage = await restarted.newPage();
    try {
      await expectLoggedIn(freshPage);
    } finally {
      await restarted.close();
    }
  });

  test('dirty and foreign cookies do not block a fresh remember-me login', async ({ page, context }) => {
    await openLogin(page);
    const baseURL = new URL(page.url()).origin;
    await context.addCookies([
      { name: 'PHPSESSID', value: 'f'.repeat(32), url: baseURL },
      { name: 'ap_device_id', value: 'definitely-dirty', url: baseURL },
      { name: 'ap_gpu_fingerprint', value: 'not-a-hash', url: baseURL },
      { name: 'trusted_device_manifest', value: 'corrupted-manifest', url: baseURL },
      { name: 'trusted_device_hint', value: 'corrupted-hint', url: baseURL },
      { name: 'trusted_device_' + '0'.repeat(16), value: 'foreign-token', url: baseURL },
    ]);

    // A stale PHP session + dirty trust cookies must render the login form,
    // not a 500 or a half-authenticated page.
    await page.goto('/main?lang=en', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#login-content input[name="email"]')).toBeVisible();

    await loginWithRemember(page);

    const cookies = await context.cookies();
    const deviceId = findCookie(cookies, name => name === 'ap_device_id')?.value || '';
    expect(deviceId).toMatch(/^[a-f0-9]{32,128}$/i);
    const gpu = findCookie(cookies, name => name === 'ap_gpu_fingerprint')?.value || '';
    expect(gpu === '' || /^[a-f0-9]{64}$/i.test(gpu), 'GPU fingerprint cookie must be empty or a SHA-256 hex').toBe(true);

    // The fresh trust must survive the loss of PHP's session cookie.
    const storageState = await context.storageState();
    const trustedOnly = {
      ...storageState,
      cookies: storageState.cookies.filter(cookie => cookie.name !== 'PHPSESSID'),
    };
    const restarted = await page.context().browser()!.newContext({ storageState: trustedOnly, baseURL });
    const freshPage = await restarted.newPage();
    try {
      await expectLoggedIn(freshPage);
    } finally {
      await restarted.close();
    }
  });

  test('renders a deterministic GPU fingerprint matching the WebGL hash', async ({ page }) => {
    await openLogin(page);
    await expect
      .poll(async () => page.evaluate(() => {
        const match = document.cookie.match(/(?:^|; )ap_gpu_fingerprint=([^;]*)/);
        return match ? decodeURIComponent(match[1]) : '';
      }), { timeout: 10000 })
      .toMatch(/^[a-f0-9]{64}$/i);

    const rendered = await page.evaluate(async () => {
      const canvas = document.createElement('canvas');
      const gl = canvas.getContext('webgl', { preserveDrawingBuffer: true, antialias: false })
        || canvas.getContext('experimental-webgl', { preserveDrawingBuffer: true, antialias: false });
      if (!gl || !crypto.subtle) return { available: false, hash: '' };
      canvas.width = 32;
      canvas.height = 32;
      gl.viewport(0, 0, 32, 32);
      gl.clearColor(0.13, 0.27, 0.41, 1);
      gl.clear(gl.COLOR_BUFFER_BIT);
      const pixels = new Uint8Array(32 * 32 * 4);
      gl.readPixels(0, 0, 32, 32, gl.RGBA, gl.UNSIGNED_BYTE, pixels);
      const digest = new Uint8Array(await crypto.subtle.digest('SHA-256', pixels));
      return { available: true, hash: Array.from(digest, byte => byte.toString(16).padStart(2, '0')).join('') };
    });
    test.skip(!rendered.available, 'WebGL is unavailable in this browser');

    const cookieFingerprint = await page.evaluate(() => {
      const match = document.cookie.match(/(?:^|; )ap_gpu_fingerprint=([^;]*)/);
      return match ? decodeURIComponent(match[1]) : '';
    });
    expect(cookieFingerprint).toBe(rendered.hash);

    // Fingerprint must not be regenerated on a reload of the same browser.
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(500);
    const afterReload = await page.evaluate(() => {
      const match = document.cookie.match(/(?:^|; )ap_gpu_fingerprint=([^;]*)/);
      return match ? decodeURIComponent(match[1]) : '';
    });
    expect(afterReload).toBe(rendered.hash);
  });

  test('trusted device still restores when WebGL is unavailable', async ({ page, context, browser }, testInfo) => {
    await loginWithRemember(page);

    const storageState = await context.storageState();
    storageState.cookies = storageState.cookies.filter(cookie => cookie.name !== 'PHPSESSID' && cookie.name !== 'ap_gpu_fingerprint');
    const restarted = await contextWithCookies(browser, storageState, testInfo.project.use.baseURL!);
    await restarted.addInitScript(() => {
      const original = HTMLCanvasElement.prototype.getContext;
      HTMLCanvasElement.prototype.getContext = function (kind: string, ...args: unknown[]) {
        if (kind === 'webgl' || kind === 'experimental-webgl') return null;
        return (original as Function).apply(this, [kind, ...args]);
      } as typeof HTMLCanvasElement.prototype.getContext;
    });
    const freshPage = await restarted.newPage();
    try {
      await expectLoggedIn(freshPage);
    } finally {
      await restarted.close();
    }
  });
});
