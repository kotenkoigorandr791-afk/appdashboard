import { expect, test } from '@playwright/test';
import { gotoShell } from '../helpers/shell';

test.describe('one-time PIN shell', () => {
  test('renders keypad and validates six digits', async ({ page, isMobile }) => {
    await gotoShell(page, '/one-time-pin');
    await expect(page.locator('.otp-card')).toBeVisible();
    await expect(page.locator('#otp-boxes .otp-digit')).toHaveCount(6);
    await page.locator('#otp-submit-btn').click();
    await expect(page.locator('#otp-err')).not.toBeEmpty();
    const viewport = page.viewportSize();
    if (isMobile && (viewport?.width || 0) < 768) {
      await expect(page.locator('#otp-numpad')).toBeVisible();
      for (const digit of ['1', '2', '3', '4', '5', '6']) {
        await page.locator(`.otp-key[data-key="${digit}"]`).click();
      }
      await expect(page.locator('#otp-d5')).toHaveText('6');
    }
  });
});
