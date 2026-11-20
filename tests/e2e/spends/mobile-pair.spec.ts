import { expect, test } from '@playwright/test';
import { login } from '../helpers/shell';

test.describe('spends — mobile pairing modal', () => {
  test('pairing modal creates a deep link and copies it', async ({ page }) => {
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
    await expect(page.locator('#mobile-app-pair-link')).toContainText('spends://auth?', { timeout: 15000 });
    const linkText = (await page.locator('#mobile-app-pair-link').innerText()).trim();
    expect(linkText.startsWith('spends://auth?')).toBeTruthy();
    expect(linkText).toContain('endpoint=');
    expect(linkText).toContain('token=');

    await page.context().grantPermissions(['clipboard-read', 'clipboard-write']);
    await page.locator('.ma-copy-btn[data-copy-target="mobile-app-pair-link"]').click();
    const copied = await page.evaluate(async () => navigator.clipboard.readText());
    expect(copied).toContain('spends://auth?');
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
