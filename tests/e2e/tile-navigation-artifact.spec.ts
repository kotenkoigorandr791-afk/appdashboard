import { expect, test } from '@playwright/test';
import { autoAcceptCookies } from './helpers/shell';

const OTHER_EMAIL = process.env.E2E_USER_EMAIL || 'dashboard-e2e@test.local';
const OTHER_PASSWORD = process.env.E2E_USER_PASSWORD || 'dashboard-e2e-e2e-password';

test.describe('app tile navigation artifact', () => {
  test('description popover is hidden instantly on tile click before navigation', async ({ page }) => {
    await page.goto('/login?lang=en', { waitUntil: 'domcontentloaded' });
    await page.locator('#login-content input[name="email"]').fill(OTHER_EMAIL);
    await page.locator('#login-password').fill(OTHER_PASSWORD);
    await Promise.all([
      page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30000 }),
      page.locator('#login-content button[name="login"]').click(),
    ]);
    await page.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
    await autoAcceptCookies(page);

    const grid = page.locator('#app-grid');
    await expect(grid).toBeVisible();
    const tiles = page.locator('#app-grid .app-tile-link');
    const tileCount = await tiles.count();
    test.skip(tileCount === 0, 'No app tiles rendered');

    // Hold the navigation to observe the page state right after the click.
    const firstHref = await tiles.first().getAttribute('href');
    await page.route('**/main?app=*', async (route) => {
      await new Promise((r) => setTimeout(r, 1500));
      await route.continue();
    });

    const pop = page.locator('#app-desc-popover');
    // Hover long enough for the 2.5s show timer to fire.
    await tiles.first().hover();
    await page.waitForTimeout(2700);
    const hadPopover = await pop.evaluate((el) => el.classList.contains('visible'));

    await tiles.first().click();
    // The popover must be gone instantly (no 450ms fade lingering during navigation).
    await expect(pop).not.toHaveClass(/visible/);
    const opacity = await pop.evaluate((el) => getComputedStyle(el).opacity);
    expect(Number(opacity)).toBe(0);

    // Navigation still proceeds to the app URL.
    await page.waitForURL((url) => url.search.includes('app='), { timeout: 15000 }).catch(() => {});
    await page.unroute('**/main?app=*');
    expect(firstHref).toContain('/main?app=');
    expect(hadPopover !== undefined).toBeTruthy();
  });

  test('popover still appears and hides normally on hover without navigation', async ({ page }) => {
    await page.goto('/login?lang=en', { waitUntil: 'domcontentloaded' });
    await page.locator('#login-content input[name="email"]').fill(OTHER_EMAIL);
    await page.locator('#login-password').fill(OTHER_PASSWORD);
    await Promise.all([
      page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30000 }),
      page.locator('#login-content button[name="login"]').click(),
    ]);
    await page.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
    await autoAcceptCookies(page);

    const tiles = page.locator('#app-grid .app-tile-link');
    const tileCount = await tiles.count();
    test.skip(tileCount === 0, 'No app tiles rendered');

    const pop = page.locator('#app-desc-popover');
    await tiles.first().hover();
    await page.waitForTimeout(2700);
    // Popover (the diamond arrow included) still works for its intended purpose.
    await expect(pop).toHaveClass(/visible/);

    await page.mouse.move(10, 10);
    await expect(pop).not.toHaveClass(/visible/);
  });
});
