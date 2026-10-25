import { test, expect } from '@playwright/test';
import { resetRootToFactoryPassword, restoreRootSuitePassword } from '../helpers/rootPassword';

const ROOT_EMAIL = 'root@root';
const ROOT_PASSWORD = 'root';
const BASE = 'http://localhost';

test.beforeAll(() => {
  resetRootToFactoryPassword();
});

test.afterAll(() => {
  restoreRootSuitePassword();
});

test('3 sequential password changes all work', async ({ page }) => {
  let currentPassword = ROOT_PASSWORD;

  for (let i = 1; i <= 3; i++) {
    const newPassword = `TestPass${i}234`;

    await page.goto(`${BASE}/login`);
    await page.waitForLoadState('networkidle');

    await page.locator('#login-content input[name="email"]').fill(ROOT_EMAIL);
    await page.locator('#login-password').fill(currentPassword);
    await page.locator('#login-content button[name="login"]').click();

    // Wait for either redirect to /main or /register or error
    await page.waitForURL(url => {
      const path = url.pathname;
      return path === '/main' || path.includes('/register') || path === '/login';
    }, { timeout: 30000 });

    if (page.url().includes('/register')) {
      const setPasswordContent = page.locator('#set-password-content');
      await expect(setPasswordContent).toBeVisible({ timeout: 15000 });

      await page.locator('#register-password').fill(newPassword);
      await page.locator('#register-confirm-password').fill(newPassword);
      await page.locator('button[name="register_complete"]').click();

      await page.waitForURL(url => url.pathname === '/main', { timeout: 30000 });
    }

    const hasLoginError = await page.locator('.auth-error').count() > 0;
    expect(hasLoginError).toBe(false);
    expect(page.url()).toContain('/main');

    // Logout
    await page.evaluate(() => {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = '/main';
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'logout';
      input.value = '1';
      form.appendChild(input);
      document.body.appendChild(form);
      form.submit();
    });
    await page.waitForURL(url => url.pathname !== '/main', { timeout: 30000 });

    currentPassword = newPassword;
    await page.screenshot({ path: `tmp/pwd-cycle-${i}-complete.png` });
  }

  // Final check — login with last password
  await page.goto(`${BASE}/login`);
  await page.waitForLoadState('networkidle');
  await page.locator('#login-content input[name="email"]').fill(ROOT_EMAIL);
  await page.locator('#login-password').fill(currentPassword);
  await page.locator('#login-content button[name="login"]').click();

  await page.waitForURL(url => url.pathname === '/main' || url.pathname === '/login', { timeout: 30000 });

  const hasError = await page.locator('.auth-error').count() > 0;
  expect(hasError).toBe(false);
  expect(page.url()).toContain('/main');
  await page.screenshot({ path: 'tmp/pwd-final-login-success.png' });
});
