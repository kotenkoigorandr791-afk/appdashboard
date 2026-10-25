import { expect, test, type Page } from '@playwright/test';
import { autoAcceptCookies, gotoShell } from '../helpers/shell';
import { resetRootToFactoryPassword, restoreRootSuitePassword } from '../helpers/rootPassword';

const ROOT_EMAIL = 'root@root';
const ROOT_PASSWORD = 'root';
const NEW_PASSWORD = 'Admin123456';

const NAV_TIMEOUT = 120000;

test.beforeAll(() => {
  // The whole flow only exists while the built-in admin still has the factory password.
  resetRootToFactoryPassword();
});

test.afterAll(() => {
  restoreRootSuitePassword();
});

async function submitLogin(page: Page) {
  const respPromise = page.waitForResponse(r => r.request().method() === 'POST' && r.url().includes('/login'), { timeout: NAV_TIMEOUT });
  await page.locator('#login-content button[name="login"]').click();
  await respPromise;
}

async function waitForMain(page: Page) {
  await page.waitForURL(url => url.pathname === '/main', { timeout: NAV_TIMEOUT, waitUntil: 'domcontentloaded' });
}

test.describe.serial('root password change flow', () => {
  test('notice shows when password is default root', async ({ page }) => {
    await gotoShell(page, '/login');
    const notice = page.locator('.auth-warning');
    await expect(notice).toBeVisible({ timeout: 15000 });
    await expect(notice).toContainText('First start');
    await expect(page.locator('#login-content input[name="email"]')).toHaveValue(ROOT_EMAIL);
    await expect(page.locator('#login-password')).toHaveValue(ROOT_PASSWORD);
  });

  test('login with root redirects to set_password, change to new password', async ({ page }) => {
    await gotoShell(page, '/login');
    await expect(page.locator('.auth-warning')).toBeVisible({ timeout: 15000 });
    await page.locator('#login-content input[name="email"]').fill(ROOT_EMAIL);
    await page.locator('#login-password').fill(ROOT_PASSWORD);
    await submitLogin(page);
    await page.waitForURL(/\/register/, { timeout: NAV_TIMEOUT, waitUntil: 'domcontentloaded' });

    await expect(page.locator('#set-password-content')).toBeVisible({ timeout: 15000 });
    await page.locator('#register-password').fill(NEW_PASSWORD);
    await page.locator('#register-confirm-password').fill(NEW_PASSWORD);
    await expect(page.locator('button[name="register_complete"]')).toBeEnabled();
    await page.locator('button[name="register_complete"]').click();
    await waitForMain(page);
  });

  test('notice no longer shows after password change', async ({ page }) => {
    await gotoShell(page, '/login');
    await expect(page.locator('.auth-warning')).not.toBeVisible();
    await expect(page.locator('#login-content input[name="email"]')).toHaveValue('');
    await expect(page.locator('#login-password')).toHaveValue('');
  });

  test('old password root is rejected, new password works', async ({ page }) => {
    await gotoShell(page, '/login');
    await expect(page.locator('.auth-warning')).not.toBeVisible();

    await page.locator('#login-content input[name="email"]').fill(ROOT_EMAIL);
    await page.locator('#login-password').fill(ROOT_PASSWORD);
    await submitLogin(page);
    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('.auth-error')).toContainText('Invalid email or password', { timeout: 8000 });

    await page.locator('#login-content input[name="email"]').fill(ROOT_EMAIL);
    await page.locator('#login-password').fill(NEW_PASSWORD);
    await submitLogin(page);
    await waitForMain(page);
  });

  test('change password via profile and verify new password works', async ({ page }) => {
    await gotoShell(page, '/login');
    await page.locator('#login-content input[name="email"]').fill(ROOT_EMAIL);
    await page.locator('#login-password').fill(NEW_PASSWORD);
    await submitLogin(page);
    await waitForMain(page);
    await autoAcceptCookies(page);

    await page.locator('#user-profile').evaluate(el => (el as HTMLElement).click());
    await expect(page.locator('#profile-modal')).toBeVisible({ timeout: 15000 });
    await page.locator('#show-password-form').click();

    const currentPwdInput = page.locator('#current-password');
    await expect(currentPwdInput).toBeVisible({ timeout: 15000 });
    await currentPwdInput.fill(NEW_PASSWORD);
    await page.locator('#verify-current-password').click();
    await expect(page.locator('#current-password-msg')).toContainText('verified', { timeout: 15000 });

    const newerPassword = 'NewAdmin789';
    await page.locator('#change-new-password').fill(newerPassword);
    await page.locator('#change-confirm-password').fill(newerPassword);
    const submitBtn = page.locator('#change-password-submit');
    await expect(submitBtn).toBeEnabled({ timeout: 15000 });
    await Promise.all([
      page.waitForResponse(r => r.request().method() === 'POST' && r.url().includes('/main'), { timeout: NAV_TIMEOUT }),
      submitBtn.click(),
    ]);

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
    await page.waitForURL(url => url.pathname !== '/main', { timeout: NAV_TIMEOUT, waitUntil: 'domcontentloaded' });

    await gotoShell(page, '/login');
    await expect(page.locator('.auth-warning')).not.toBeVisible();
    await page.locator('#login-content input[name="email"]').fill(ROOT_EMAIL);
    await page.locator('#login-password').fill(NEW_PASSWORD);
    await submitLogin(page);
    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('.auth-error')).toContainText('Invalid email or password', { timeout: 8000 });

    await page.locator('#login-content input[name="email"]').fill(ROOT_EMAIL);
    await page.locator('#login-password').fill(newerPassword);
    await submitLogin(page);
    await waitForMain(page);
  });
});