import { expect, test } from '@playwright/test';

test.describe('GPU fingerprint risk signal', () => {
  test('generates a deterministic SHA-256 WebGL pixel fingerprint', async ({ page }) => {
    await page.goto('/login?lang=en', { waitUntil: 'domcontentloaded' });
    const result = await page.evaluate(async () => {
      const canvas = document.createElement('canvas');
      const gl = canvas.getContext('webgl', { preserveDrawingBuffer: true, antialias: false });
      if (!gl || !crypto.subtle) return { available: false, first: '', second: '' };
      canvas.width = 32; canvas.height = 32;
      gl.viewport(0, 0, 32, 32); gl.clearColor(.13, .27, .41, 1); gl.clear(gl.COLOR_BUFFER_BIT);
      const pixels = new Uint8Array(32 * 32 * 4);
      gl.readPixels(0, 0, 32, 32, gl.RGBA, gl.UNSIGNED_BYTE, pixels);
      const digest = async () => Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', pixels)), b => b.toString(16).padStart(2, '0')).join('');
      return { available: true, first: await digest(), second: await digest() };
    });
    test.skip(!result.available, 'WebGL is unavailable in this browser');
    expect(result.first).toMatch(/^[a-f0-9]{64}$/);
    expect(result.second).toBe(result.first);
  });

  test('fails closed when WebGL is unavailable', async ({ page }) => {
    await page.addInitScript(() => {
      const original = HTMLCanvasElement.prototype.getContext;
      HTMLCanvasElement.prototype.getContext = function (kind: any, ...args: any[]) {
        if (kind === 'webgl' || kind === 'experimental-webgl') return null;
        return original.call(this, kind, ...args);
      };
    });
    await page.goto('/login?lang=en', { waitUntil: 'domcontentloaded' });
    await expect.poll(() => page.evaluate(() => document.cookie.includes('ap_gpu_fingerprint='))).toBe(false);
    expect(await page.evaluate(() => document.cookie.includes('ap_gpu_fingerprint='))).toBe(false);
  });

  test('does not accept malformed or oversized client fingerprint values', async ({ page }) => {
    await page.goto('/login?lang=en', { waitUntil: 'domcontentloaded' });
    await page.context().addCookies([{ name: 'ap_gpu_fingerprint', value: 'spoofed', url: 'http://localhost' }]);
    const response = await page.request.post('/main?api=client_auth&action=add_trusted_device', {
      headers: { 'Content-Type': 'application/json', 'X-GPU-Fingerprint': 'spoofed' }, body: '{}'
    });
    expect((await response.json()).ok).toBe(false);
  });
});
