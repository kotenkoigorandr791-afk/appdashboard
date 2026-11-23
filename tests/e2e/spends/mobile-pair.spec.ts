import { expect, test } from '@playwright/test';
import { login } from '../helpers/shell';

test.describe('spends — mobile pairing modal', () => {
  test('pairing modal creates an API token and copies it', async ({ page }) => {
    await login(page);
    await page.goto('/apps/spends/?lang=en&theme=light', { waitUntil: 'domcontentloaded' });

    await page.evaluate(() => {
      if (typeof (window as any).openMobileAppModal === 'function') {
        (window as any).openMobileAppModal();
      }
    });
    await expect(page.locator('#mobile-app-modal')).toBeVisible();
    await page.locator('#mobile-app-connect').click();
    await expect(page.locator('#mobile-app-pair-fields')).toBeVisible({ timeout: 15000 });
    const tokenPre = page.locator('#mobile-app-pair-token');
    await expect(tokenPre).toHaveText(/[a-f0-9]{32,}/i, { timeout: 15000 });
    const tokenText = ((await tokenPre.textContent()) ?? '').trim();
    await expect(page.locator('#mobile-app-devices-list .ma-device').first()).toContainText(/Mobile|Spends/i, {
      timeout: 15000,
    });

    await page.context().grantPermissions(['clipboard-read', 'clipboard-write']);
    await page.locator('.ma-copy-btn[data-copy-target="mobile-app-pair-token"]').click();
    const copied = await page.evaluate(async () => navigator.clipboard.readText());
    expect(copied).toBe(tokenText);
  });

  test('feature rows share the pairing panel height evenly', async ({ page }) => {
    await login(page);
    await page.goto('/apps/spends/?lang=en&theme=light', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => (window as any).openMobileAppModal());
    await expect(page.locator('#mobile-app-modal')).toBeVisible();

    const layout = await page.locator('.ma-feature').evaluateAll((rows) => ({
      heights: rows.map((row) => row.getBoundingClientRect().height),
      action: document.querySelector('.ma-action')!.getBoundingClientRect().height,
      features: document.querySelector('.ma-features')!.getBoundingClientRect().height,
    }));
    expect(layout.heights.length).toBe(4);
    expect(Math.max(...layout.heights) - Math.min(...layout.heights)).toBeLessThanOrEqual(2);
    expect(Math.abs(layout.action - layout.features)).toBeLessThanOrEqual(2);
  });
});
