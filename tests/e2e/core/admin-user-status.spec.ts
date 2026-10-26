import { expect, test } from '@playwright/test';
import { execSync } from 'child_process';

// A dedicated non-root admin: E2E_USER_EMAIL is the built-in root account and
// this spec is specifically about what a *non-root* admin may do.
const ADMIN_EMAIL = process.env.E2E_ADMIN_EMAIL || 'dashboard-e2e@test.local';
const ADMIN_PASSWORD = process.env.E2E_ADMIN_PASSWORD || 'dashboard-e2e-e2e-password';
// A second, non-root user used as the status-change target.
const TARGET_EMAIL = 'codex-cloud-peer@example.com';

function sql(q: string) {
  execSync(`docker exec appdashboard-db psql -U appdashboard -d appdashboard -c "${q.replace(/"/g, '\\"')}"`, { stdio: 'pipe' });
}
function getVerified(email: string): boolean {
  const out = execSync(
    `docker exec appdashboard-db psql -U appdashboard -d appdashboard -t -c "select verified from users where email='${email}'"`,
  ).toString().trim();
  return out === '1' || out.toLowerCase() === 't';
}
function setVerified(email: string, v: boolean) {
  sql(`update users set verified=${v ? 1 : 0} where email='${email}'`);
}
function getRole(email: string): string {
  return execSync(
    `docker exec appdashboard-db psql -U appdashboard -d appdashboard -t -c "select role from users where email='${email}'"`,
  ).toString().trim();
}

test.describe('admin changes other user status (task 008)', () => {
  const originalTargetVerified = getVerified(TARGET_EMAIL);
  const originalSelfVerified = getVerified(ADMIN_EMAIL);
  const originalTargetRole = getRole(TARGET_EMAIL);
  const originalAdminRole = Number(getRole(ADMIN_EMAIL));

  test.afterAll(() => {
    setVerified(TARGET_EMAIL, originalTargetVerified);
    setVerified(ADMIN_EMAIL, originalSelfVerified);
    sql(`update users set role=${originalTargetRole} where email='${TARGET_EMAIL}'`);
  });

  async function loginAsAdmin(page: import('@playwright/test').Page) {
    await page.goto('/login?lang=en', { waitUntil: 'domcontentloaded' });
    await page.locator('#login-content input[name="email"]').fill(ADMIN_EMAIL);
    await page.locator('#login-password').fill(ADMIN_PASSWORD);
    await Promise.all([
      page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30000 }),
      page.locator('#login-content button[name="login"]').click(),
    ]);
    // Pass the separate admin-panel gate.
    const gate = await page.evaluate(async (password) => {
      const fd = new FormData();
      fd.append('admin_panel_login', '1');
      fd.append('ajax', '1');
      fd.append('password', password);
      const r = await fetch('/main', { method: 'POST', body: fd });
      return r.json();
    }, ADMIN_PASSWORD);
    expect(gate.success).toBeTruthy();
  }

  async function saveUserRoles(page: import('@playwright/test').Page, prefix: string, fields: Record<string, string>) {
    return page.evaluate(async ({ p, f }) => {
      const fd = new FormData();
      fd.append('save_user_roles', '1');
      fd.append('ajax', '1');
      for (const [k, v] of Object.entries(f)) fd.append(`${p}[${k}]`, v);
      const r = await fetch('/main', { method: 'POST', body: fd });
      return { status: r.status, body: await r.json() };
    }, { p: prefix, f: fields });
  }

  test('non-root admin can toggle another user status, but not own or root', async ({ page }) => {
    await loginAsAdmin(page);

    // 1. Toggle another user: allowed.
    const res = await saveUserRoles(page, 'user_verified', { [TARGET_EMAIL]: '0' });
    expect(res.body.success).toBeTruthy();
    expect(getVerified(TARGET_EMAIL)).toBe(false);
    await saveUserRoles(page, 'user_verified', { [TARGET_EMAIL]: '1' });
    expect(getVerified(TARGET_EMAIL)).toBe(true);

    // 2. Own status: server must refuse (no change).
    await saveUserRoles(page, 'user_verified', { [ADMIN_EMAIL]: '0' });
    expect(getVerified(ADMIN_EMAIL)).toBe(true);

    // 3. root@root status: server must refuse.
    await saveUserRoles(page, 'user_verified', { 'root@root': '0' });
    expect(getVerified('root@root')).toBe(true);

    // 4. Non-root admins may change another user's role (see the admin feature
    //    that lets them manage roles), but never their own.
    const roleBefore = getRole(TARGET_EMAIL);
    const newRole = roleBefore === '1' ? '0' : '1';
    await saveUserRoles(page, 'user_roles', { [TARGET_EMAIL]: newRole });
    expect(getRole(TARGET_EMAIL)).toBe(newRole);
    await saveUserRoles(page, 'user_roles', { [ADMIN_EMAIL]: '0' });
    expect(getRole(ADMIN_EMAIL)).toBe(String(originalAdminRole));
  });

  test('direct POST without admin panel gate is rejected by the gate', async ({ request }) => {
    // No session at all: request must not succeed.
    const res = await request.post('/main', {
      data: `save_user_roles=1&ajax=1&user_verified[${TARGET_EMAIL}]=0`,
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    });
    // Should not modify anything (403 CSRF / 401 / redirect to login).
    expect(getVerified(TARGET_EMAIL)).toBe(originalTargetVerified);
  });
});
