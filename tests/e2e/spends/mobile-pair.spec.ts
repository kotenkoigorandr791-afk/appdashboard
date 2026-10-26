import { expect, test } from '@playwright/test';
import { login } from '../helpers/shell';

test.describe('spends — mobile pairing modal', () => {
  test('QR modal exposes copy link and fills the white frame after generate', async ({ page }) => {
    await login(page);
    await page.goto('/apps/spends/?lang=en&theme=light', { waitUntil: 'domcontentloaded' });

    await page.evaluate(() => {
      if (typeof (window as any).openMobileAppModal === 'function') {
        (window as any).openMobileAppModal();
      }
    });
    await expect(page.locator('#mobile-app-modal')).toBeVisible();
    await expect(page.locator('.ma-qr-frame')).toBeVisible();

    const frameBox = await page.locator('.ma-qr-frame').boundingBox();
    expect(frameBox?.width).toBeGreaterThanOrEqual(210);
    expect(frameBox?.height).toBeGreaterThanOrEqual(210);

    const gen = page.locator('#mobile-app-qr-generate');
    const actions = page.locator('#mobile-app-qr-actions');
    if (await gen.isVisible()) {
      await gen.click();
    }
    await expect(actions).toHaveClass(/is-visible/, { timeout: 15000 });
    await expect(page.locator('#mobile-app-copy-link')).toBeVisible();
    await expect(page.locator('#mobile-app-qr-code svg, #mobile-app-qr-code canvas')).toBeVisible({ timeout: 15000 });

    const qrBox = await page.locator('#mobile-app-qr-code').boundingBox();
    expect(qrBox?.width).toBeGreaterThanOrEqual(200);
    expect(qrBox?.height).toBeGreaterThanOrEqual(200);

    await page.locator('#mobile-app-manual-hint').click();
    await expect(page.locator('#manual-pair-modal')).toBeVisible();
    await expect(page.locator('#manual-pair-link')).toBeVisible();
    const linkText = (await page.locator('#manual-pair-link').innerText()).trim();
    expect(linkText.startsWith('spends://auth?')).toBeTruthy();
    expect(linkText).toContain('endpoint=');
    expect(linkText).toContain('token=');

    await page.context().grantPermissions(['clipboard-read', 'clipboard-write']);
    await page.locator('#manual-pair-modal .ma-copy-btn[data-copy-target="manual-pair-link"]').click();
    const copied = await page.evaluate(async () => navigator.clipboard.readText());
    expect(copied).toContain('spends://auth?');
  });
});
