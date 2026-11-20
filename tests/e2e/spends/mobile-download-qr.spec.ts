import { expect, test } from '@playwright/test';
import { hasAuthUser, login } from '../helpers/shell';

const APK_PATH = '/downloads/spends-mobile.apk';

test.describe('spends — mobile app download link', () => {
  test.skip(!hasAuthUser(), 'E2E credentials required');

  test('offers the release APK download link', async ({ page }) => {
    await login(page);
    await page.goto('/apps/spends/?lang=en&theme=light', { waitUntil: 'domcontentloaded' });

    await page.evaluate(() => {
      if (typeof (window as any).openMobileAppModal === 'function') {
        (window as any).openMobileAppModal();
      }
    });

    await expect(page.locator('#mobile-app-modal')).toBeVisible();

    const downloadHref = await page.locator('.ma-download[download="spends-mobile.apk"]').getAttribute('href');
    expect(downloadHref).toBe(APK_PATH);

    const response = await page.evaluate(async (path) => {
      const res = await fetch(path, { method: 'GET', credentials: 'same-origin' });
      return { status: res.status, contentType: res.headers.get('content-type') || '' };
    }, APK_PATH);
    expect(response.status).toBe(200);
    expect(response.contentType).toContain('application/octet-stream');
  });
});
