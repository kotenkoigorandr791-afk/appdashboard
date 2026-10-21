import { expect, test } from '@playwright/test';
import { autoAcceptCookies, login } from './helpers/shell';

test('mobile app grid remains vertically scrollable', async ({ page }) => {
  test.skip((page.viewportSize()?.width || 0) > 768, 'mobile layout only');
  await login(page);
  await page.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
  await autoAcceptCookies(page);
  const grid = page.locator('#app-grid');
  await expect(grid).toBeVisible();
  const metrics = await grid.evaluate(el => ({
    clientHeight: el.clientHeight,
    scrollHeight: el.scrollHeight,
    overflowY: getComputedStyle(el).overflowY,
    touchAction: getComputedStyle(el).touchAction,
  }));
  expect(metrics.overflowY).toBe('auto');
  expect(metrics.touchAction).toBe('pan-y');
  expect(metrics.scrollHeight).toBeGreaterThan(metrics.clientHeight);
  await grid.evaluate(el => { el.scrollTop = el.scrollHeight; });
  await expect.poll(() => grid.evaluate(el => el.scrollTop)).toBeGreaterThan(0);
});
