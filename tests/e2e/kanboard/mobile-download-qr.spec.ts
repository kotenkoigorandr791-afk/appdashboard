import { expect, test, type Locator, type Page } from '@playwright/test';
import { autoAcceptCookies, gotoShell, hasAuthUser, login } from '../helpers/shell';

const APK_PATH = '/downloads/kanboard-mobile.apk';
const JSQR_URL = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js';

/**
 * Decode the QR code rendered into `svg` so the test verifies the encoded
 * payload instead of only the presence of an SVG element.
 */
async function decodeQrData(page: Page, svg: Locator): Promise<string | null> {
  const markup = await svg.evaluate((element) => new XMLSerializer().serializeToString(element));
  const dataUrl = `data:image/svg+xml;base64,${Buffer.from(markup, 'utf8').toString('base64')}`;
  const decoderSource = await (await fetch(JSQR_URL)).text();

  // Decode in a scratch page: the dashboard shell defines a global `exports`,
  // which makes the jsQR UMD bundle register itself there instead of `window`.
  const scratch = await page.context().newPage();
  try {
    await scratch.setContent('<html><body></body></html>');
    await scratch.addScriptTag({ content: decoderSource });

    return await scratch.evaluate(async (url) => {
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
  } finally {
    await scratch.close();
  }
}

test.describe('kanboard — mobile app download QR code', () => {
  test.skip(!hasAuthUser(), 'E2E credentials required');
  test.describe.configure({ timeout: 120000 });

  test('renders a QR code that points at the release APK', async ({ page }) => {
    await autoAcceptCookies(page);
    await login(page);
    await gotoShell(page, '/main?app=kanboard');

    const frame = page.frameLocator('#app-iframe');
    await expect(frame.locator('#kb-app-sidebar')).toBeVisible({ timeout: 60000 });

    await frame.locator('#kb-open-settings').click();
    await expect(frame.locator('#kb-mobile-app-modal')).toBeVisible({ timeout: 15000 });
    await frame.locator('[data-kb-settings-tab="mobile"]').click();
    await expect(frame.locator('#kb-settings-panel-mobile')).toBeVisible();

    const container = frame.locator('#kb-ma-download-qr');
    await expect(container).toBeVisible({ timeout: 30000 });
    await expect(container.locator('svg, canvas').first()).toBeVisible({ timeout: 30000 });

    const dataUrl = await container.getAttribute('data-url');
    expect(dataUrl).toBeTruthy();
    expect(new URL(dataUrl as string).pathname).toBe(APK_PATH);

    const decoded = await decodeQrData(page, container.locator('svg').first());
    expect(decoded).toBeTruthy();
    expect(new URL(decoded as string).pathname).toBe(APK_PATH);
  });
});
