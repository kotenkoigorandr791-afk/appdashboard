import { expect, test } from '@playwright/test';
import { autoAcceptCookies } from '../helpers/shell';

const ROOT_EMAIL = 'root@root';
const OTHER_EMAIL = 'dashboard-e2e@test.local';

test.describe('remember-me hidden for root@root on login', () => {
  test('checkbox smoothly hides for root@root and restores for other emails', async ({ page }) => {
    await page.goto('/login?lang=en', { waitUntil: 'domcontentloaded' });
    const email = page.locator('#login-content input[name="email"]');
    const label = page.locator('#login-content label.checkbox-label');

    // If the default-admin notice prefills root@root, the label starts hidden.
    if ((await email.inputValue()) === ROOT_EMAIL) {
      await expect(label).toHaveClass(/remember-hidden/);
    }

    await email.fill(OTHER_EMAIL);
    await expect(label).not.toHaveClass(/remember-hidden/);
    await expect(label).toBeVisible();
    await expect(label).toHaveCSS('opacity', '0.86');

    // Smooth collapse: transition is defined on the element.
    const transition = await label.evaluate((el) => getComputedStyle(el).transitionProperty);
    expect(transition).toContain('max-height');
    expect(transition).toContain('opacity');

    const heightBefore = await page.locator('#login-content').boundingBox();

    await email.fill(ROOT_EMAIL);
    await expect(label).toHaveClass(/remember-hidden/);
    await expect(label).toHaveCSS('opacity', '0');
    await expect(label).toHaveCSS('max-height', '0px');

    // The form collapses smoothly with the label (its height shrinks from
    // ~33px to 0 over the transition; no jump, just the label's own space).
    const heightAfter = await page.locator('#login-content').boundingBox();
    const delta = Math.abs((heightBefore?.height || 0) - (heightAfter?.height || 0));
    expect(delta).toBeGreaterThan(0);
    expect(delta).toBeLessThan(40);

    // Clearing the field restores the checkbox smoothly.
    await email.fill('');
    await expect(label).not.toHaveClass(/remember-hidden/);
    await expect(label).toHaveCSS('opacity', '0.86');

    // The hidden state must not disable the checkbox value for others.
    await email.fill(OTHER_EMAIL);
    await expect(page.locator('#login-content input[name="remember_me"]')).toHaveValue('1');
    await email.fill(ROOT_EMAIL);
    await expect(page.locator('#login-content input[name="remember_me"]')).toHaveValue('');
  });

  test('root password login with remember_me still works but sets no trusted-device cookie', async ({ page }) => {
    await page.goto('/login?lang=en', { waitUntil: 'domcontentloaded' });
    await autoAcceptCookies(page);
    const email = page.locator('#login-content input[name="email"]');
    await email.fill(ROOT_EMAIL);
    await page.locator('#login-password').fill('root');
    // Force the checkbox checked even though UI hides it (server must guard).
    await page.evaluate(() => {
      const cb = document.querySelector('#login-content input[name="remember_me"]') as HTMLInputElement;
      if (cb) { cb.value = '1'; }
    });
    await Promise.all([
      page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30000 }),
      page.locator('#login-content button[name="login"]').click(),
    ]);
    await expect(page).not.toHaveURL(/\/login/);
    const cookies = await page.context().cookies();
    const trusted = cookies.filter((c) => /trusted/i.test(c.name));
    expect(trusted).toHaveLength(0);
  });
});
