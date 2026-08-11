import { expect, test } from '@playwright/test';
import { expectNoCenterOverlaps, expectNoViewportOverflow, expectVisibleBox, gotoShell } from '../helpers/shell';

test.describe('auth shell', () => {
  test.beforeEach(async ({ page }) => {
    await gotoShell(page, '/login');
  });

  test('renders login, register and forgot-password states', async ({ page }) => {
    await expectVisibleBox(page.locator('#auth-modal'));
    await expectVisibleBox(page.locator('#auth-form'));
    await expect(page.locator('#login-content')).toHaveClass(/active/);
    await expect(page.locator('#login-content input[name="email"]')).toBeVisible();
    await expect(page.locator('#login-password')).toBeVisible();
    await expect(page.locator('#login-content button[name="login"]')).toBeVisible();

    await page.locator('.password-toggle[data-target="login-password"]').click();
    await expect(page.locator('#login-password')).toHaveAttribute('type', 'text');
    await page.locator('.password-toggle[data-target="login-password"]').click();
    await expect(page.locator('#login-password')).toHaveAttribute('type', 'password');

    await page.locator('.auth-tab[data-tab="register"]').click();
    await expect(page.locator('#register-content')).toHaveClass(/active/);
    await expect(page.locator('#register-content input[name="email"]')).toBeVisible();
    await expect(page.locator('#register-content input[name="name"]')).toBeVisible();
    await expect(page.locator('#register-content button[name="register_initial"]')).toBeVisible();

    await page.locator('#register-content .auth-link[data-tab="login"]').click();
    await expect(page.locator('#login-content')).toHaveClass(/active/);
    await page.locator('#show-forgot').click();
    await expect(page.locator('#forgot-content')).toHaveClass(/active/);
    await expect(page.locator('#forgot-content input[name="email"]')).toBeVisible();
    await expect(page.locator('#forgot-content button[name="send_reset_code"]')).toBeVisible();
  });

  test('opens policy documents from auth modal', async ({ page }) => {
    await page.locator('.policy-links a').first().click();
    await expect(page.locator('#policy-modal-overlay')).toHaveClass(/active/);
    await expect(page.locator('#policy-modal iframe')).toBeVisible();
    await page.locator('#policy-modal-close').dispatchEvent('click');
    await expect(page.locator('#policy-modal-overlay')).not.toHaveClass(/active/);
  });

  test('keeps auth shell controls inside viewport and clickable', async ({ page }) => {
    await expectNoViewportOverflow(page, '#auth-form, #auth-form input, #auth-form button:not(.back-button), #auth-form a, #auth-form .auth-link');
    await expectNoCenterOverlaps(page, '#auth-form input, #auth-form button:not(.back-button)');
  });
});
