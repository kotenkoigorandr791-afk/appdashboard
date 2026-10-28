import { expect, test, type Locator, type Page } from '@playwright/test';
import { hasAuthUser, login } from '../helpers/shell';

const APK_PATH = '/downloads/spends-mobile.apk';
const JSQR_URL = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js';

/**
 * Decode the QR code rendered into `svg` so the test verifies the encoded
 * payload instead of only the presence of an SVG element.
 */
async function decodeQrData(page: Page, svg: Locator): Promise<string | null> {
  await page.addScriptTag({ url: JSQR_URL });
  const markup = await svg.evaluate((element) => new XMLSerializer().serializeToString(element));
  const dataUrl = `data:image/svg+xml;base64,${Buffer.from(markup, 'utf8').toString('base64')}`;

  return await page.evaluate(async (url) => {
    const decode = (window as any).jsQR;
    if (typeof decode !== 'function') throw new Error('jsQR was not loaded');
    const image = new Image();
    image.src = url;
    await new Promise<void>((resolve, reject) => {
      image.onload = () => resolve();
      image.onerror = () => reject(new Error('QR image could not be loaded'));
    });
    const canvas = document.createElement('canvas');
    canvas.width = 480;
    canvas.height = 480;
    const context = canvas.getContext('2d');
    if (!context) throw new Error('2d context unavailable');
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(image, 0, 0, canvas.width, canvas.height);
    const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
    const result = decode(imageData.data, imageData.width, imageData.height);
    return result && result.data ? String(result.data) : null;
  }, dataUrl);
}

test.describe('spends — mobile app download QR code', () => {
  test.skip(!hasAuthUser(), 'E2E credentials required');

  test('renders a QR code that points at the release APK', async ({ page }) => {
    await login(page);
    await page.goto('/apps/spends/?lang=en&theme=light', { waitUntil: 'domcontentloaded' });

    await page.evaluate(() => {
      if (typeof (window as any).openMobileAppModal === 'function') {
        (window as any).openMobileAppModal();
      }
    });

    const container = page.locator('#mobile-app-download-qr-code');
    await expect(container).toBeVisible();

    const qr = container.locator('svg, canvas').first();
    await expect(qr).toBeVisible({ timeout: 30000 });

    const decoded = await decodeQrData(page, container.locator('svg').first());
    expect(decoded).toBeTruthy();
    expect(new URL(decoded as string).pathname).toBe(APK_PATH);

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
