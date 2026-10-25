import { expect, test, type Page } from '@playwright/test';
import { resetRootToFactoryPassword, restoreRootSuitePassword } from '../helpers/rootPassword';

// Deliberately a separate non-root admin: E2E_USER_EMAIL points at the
// built-in root account, which is exactly what the later cases forbid.
const ADMIN_EMAIL = process.env.E2E_ADMIN_EMAIL || 'dashboard-e2e@test.local';
const ADMIN_PASSWORD = process.env.E2E_ADMIN_PASSWORD || 'dashboard-e2e-e2e-password';
const ROOT_EMAIL = 'root@root';
const ROOT_PASSWORD = 'root';

test.beforeAll(() => {
  resetRootToFactoryPassword();
});

test.afterAll(() => {
  restoreRootSuitePassword();
});

async function loginViaUi(page: Page, email: string, password: string) {
  await page.goto('/login?lang=en', { waitUntil: 'domcontentloaded' });
  await page.locator('#login-content input[name="email"]').fill(email);
  await page.locator('#login-password').fill(password);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30000 }),
    page.locator('#login-content button[name="login"]').click(),
  ]);
}

async function generateOtpPin(page: Page): Promise<string> {
  const res = await page.evaluate(async () => {
    const fd = new FormData();
    fd.append('generate_otp', '1');
    fd.append('ajax', '1');
    fd.append('ttl_seconds', '300');
    const r = await fetch(window.location.pathname, { method: 'POST', body: fd });
    return r.json();
  });
  return String(res.pin || '');
}

async function clearSession(page: Page) {
  await page.context().clearCookies();
}

test.describe('one-time PIN login permissions', () => {
  test('non-root admin can generate a PIN and sign in via /one-time-pin', async ({ page }) => {
    await loginViaUi(page, ADMIN_EMAIL, ADMIN_PASSWORD);
    const pin = await generateOtpPin(page);
    expect(pin).toMatch(/^\d{6}$/);

    // Drop the session so the PIN login is exercised from a clean state.
    await clearSession(page);
    await page.goto('/one-time-pin?lang=en', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.otp-card')).toBeVisible();
    // The digits are tracked in JS on keydown, so locator.fill() would not
    // register; pressSequentially sends real key events. (The on-screen
    // keypad is hidden on desktop viewports.)
    await page.locator('#otp-real').pressSequentially(pin, { delay: 60 });
    await expect(page.locator('#otp-d5')).toHaveText(pin.slice(5));
    await Promise.all([
      page.waitForURL((url) => url.pathname === '/main', { timeout: 30000 }),
      page.locator('#otp-submit-btn').click(),
    ]);
    await expect(page).toHaveURL(/\/main/);
  });

  test('root@root cannot generate a one-time PIN', async ({ page }) => {
    await loginViaUi(page, ROOT_EMAIL, ROOT_PASSWORD);
    const res = await page.evaluate(async () => {
      const fd = new FormData();
      fd.append('generate_otp', '1');
      fd.append('ajax', '1');
      fd.append('ttl_seconds', '300');
      const r = await fetch(window.location.pathname, { method: 'POST', body: fd });
      return { status: r.status, body: await r.json() };
    });
    expect(res.body.success).toBeFalsy();
  });

  test('direct verify_otp POST with a PIN issued for root@root is rejected server-side', async ({ page }) => {
    // Seed a PIN bound to root@root straight into the DB (generation is blocked for root).
    const seededPin = String(100000 + Math.floor(Math.random() * 899999));
    await execSql(`DELETE FROM ip_lockouts WHERE lock_type='otp'; DELETE FROM one_time_pins WHERE email='root@root'; INSERT INTO one_time_pins (pin, email, expires) VALUES ('${seededPin}', 'root@root', extract(epoch from now())::int + 600);`);

    await page.goto('/one-time-pin?lang=en', { waitUntil: 'domcontentloaded' });
    const csrf = await page.evaluate(() => String((window as any).__AP_CSRF__ || ''));
    expect(csrf).not.toBe('');

    const res = await page.evaluate(async ({ pin, token }) => {
      const fd = new FormData();
      fd.append('verify_otp', '1');
      fd.append('otp_pin', pin);
      fd.append('_csrf', token);
      const r = await fetch('/one-time-pin', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      const text = await r.text();
      return { status: r.status, url: r.url, hasErr: /otp-err">[^<]/.test(text), err: (text.match(/otp-err">([^<]*)/) || [])[1] || '' };
    }, { pin: seededPin, token: csrf });
    // OTP form is re-rendered with an error (no login redirect happened) and
    // the response is the PIN page, never a session redirect to /main.
    expect(res.status).toBe(200);
    expect(res.err).not.toBe('');

    // And no admin session was established: /main must bounce to the login page.
    await page.goto('/main', { waitUntil: 'domcontentloaded' });
    expect(page.url()).toContain('/login');
  });

  test('root@root password login still works', async ({ page }) => {
    await loginViaUi(page, ROOT_EMAIL, ROOT_PASSWORD);
    await expect(page).not.toHaveURL(/\/login/);
  });
});

async function execSql(sql: string) {
  const { execSync } = await import('child_process');
  const dbUser = process.env.E2E_DB_USER || 'appdashboard';
  const dbName = process.env.E2E_DB_NAME || 'appdashboard';
  const container = process.env.E2E_DB_CONTAINER || 'appdashboard-db';
  execSync(`docker exec ${container} psql -U ${dbUser} -d ${dbName} -c "${sql.replace(/"/g, '\\"')}"`, {
    stdio: 'pipe',
  });
}
