import { test, expect } from '@playwright/test';

const ROOT_EMAIL = 'root@root';
const ROOT_PASSWORD = 'root';
const NEW_PASSWORD = 'Admin12345678';
const BASE = 'http://localhost';

test('password change and re-login full cycle', async ({ page }) => {
  // Step 1: Login with default root password
  await page.goto(`${BASE}/login`);
  await page.waitForLoadState('networkidle');

  await page.locator('#login-content input[name="email"]').fill(ROOT_EMAIL);
  await page.locator('#login-password').fill(ROOT_PASSWORD);
  await page.locator('#login-content button[name="login"]').click();

  await page.waitForURL(/\/register/, { timeout: 30000 });
  await page.screenshot({ path: 'tmp/pwd-02-set-password-page.png' });

  // Step 2: Set new password
  const setPasswordContent = page.locator('#set-password-content');
  await expect(setPasswordContent).toBeVisible({ timeout: 15000 });

  await page.locator('#register-password').fill(NEW_PASSWORD);
  await page.locator('#register-confirm-password').fill(NEW_PASSWORD);
  await page.screenshot({ path: 'tmp/pwd-03-new-password-filled.png' });

  const submitBtn = page.locator('button[name="register_complete"]');
  await expect(submitBtn).toBeEnabled({ timeout: 5000 });
  await submitBtn.click();

  await page.waitForURL(url => url.pathname === '/main', { timeout: 30000 });
  await page.screenshot({ path: 'tmp/pwd-04-after-password-change.png' });

  // Step 3: Logout
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
  await page.screenshot({ path: 'tmp/pwd-06-after-logout.png' });

  // Step 4: Re-login with NEW password — THE BUG TEST
  await page.goto(`${BASE}/login`);
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'tmp/pwd-07-login-page-after-logout.png' });

  const warning = page.locator('.auth-warning');
  await expect(warning).not.toBeVisible();

  await page.locator('#login-content input[name="email"]').fill(ROOT_EMAIL);
  await page.locator('#login-password').fill(NEW_PASSWORD);
  await page.locator('#login-content button[name="login"]').click();

  await page.waitForTimeout(3000);
  await page.screenshot({ path: 'tmp/pwd-08-relogin-attempt.png' });

  const url = page.url();
  const hasError = await page.locator('.auth-error').count() > 0;

  if (hasError) {
    const errorText = await page.locator('.auth-error').textContent();
    console.error('LOGIN FAILED:', errorText);
    await page.screenshot({ path: 'tmp/pwd-09-LOGIN-FAILED.png' });
  }

  expect(hasError).toBe(false);
  expect(url).toContain('/main');
  await page.screenshot({ path: 'tmp/pwd-09-relogin-success.png' });

  // Step 5: Old password should be rejected
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

  await page.goto(`${BASE}/login`);
  await page.waitForLoadState('networkidle');
  await page.locator('#login-content input[name="email"]').fill(ROOT_EMAIL);
  await page.locator('#login-password').fill(ROOT_PASSWORD);
  await page.locator('#login-content button[name="login"]').click();
  await page.waitForTimeout(2000);
  await page.screenshot({ path: 'tmp/pwd-10-old-password-rejected.png' });

  const hasErrorOld = await page.locator('.auth-error').count() > 0;
  expect(hasErrorOld).toBe(true);
});
