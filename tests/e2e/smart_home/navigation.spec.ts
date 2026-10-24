import { expect, test } from '@playwright/test';
import { login } from '../helpers/shell';

test.describe('smart home shell navigation', () => {
  test('persists the selected section in the shell URL and after reload', async ({ page }) => {
    await login(page);
    await page.goto('/main?app=smart_home&lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
    const frame = page.frameLocator('#app-iframe');
    await expect(frame.locator('#menuToggle')).toBeVisible({ timeout: 30000 });
    await frame.locator('#menuToggle').click();
    await frame.locator('.drawer-item[data-page="devices"]').click();
    await expect(page).toHaveURL(/#smart_home\/devices$/);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.frameLocator('#app-iframe').locator('#page-devices')).toBeVisible({ timeout: 30000 });
  });

  test('keeps the shell menu clear of the mobile smart home toolbar', async ({ page }) => {
    test.skip((page.viewportSize()?.width || 0) > 640, 'mobile layout only');
    await login(page);
    await page.goto('/main?app=smart_home&lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
    const frame = page.frameLocator('#app-iframe');
    await expect(frame.locator('.app-bar')).toBeVisible({ timeout: 30000 });
    const paddingLeft = await frame.locator('.app-bar').evaluate(el => parseFloat(getComputedStyle(el).paddingLeft));
    expect(paddingLeft).toBeGreaterThanOrEqual(60);
    await expect(page.locator('#menu-button')).toBeVisible();
  });
});
