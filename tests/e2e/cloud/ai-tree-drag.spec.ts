import { test, expect } from '@playwright/test';
import { login, gotoShell } from '../helpers/shell';

test.describe('AI tree drag attach', () => {
  test.use({ viewport: { width: 1400, height: 900 } });

  test('drag file from tree to AI assistant attaches @mention chip', async ({ page }) => {
    test.skip(!process.env.E2E_USER_EMAIL || !process.env.E2E_USER_PASSWORD, 'Needs E2E_USER_EMAIL/PASSWORD');

    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));

    await login(page);
    await gotoShell(page, '/main?app=cloud&theme=dark&color=%2359b300');
    await page.waitForSelector('#app-iframe', { timeout: 30000 });

    const frame = page.frameLocator('#app-iframe');
    await frame.locator('#tree .node[data-type="file"]').first().waitFor({ timeout: 30000 });
    await frame.locator('#btn-ai').waitFor({ state: 'visible', timeout: 15000 });
    await frame.locator('body').evaluate(() => {
      if (typeof window.toggleAIModal === 'function') window.toggleAIModal();
      else document.getElementById('btn-ai')?.click();
    });
    await expect(frame.locator('#ai-modal.ai-open, #ai-modal:not(.hidden)')).toBeVisible({ timeout: 10000 });

    const fileNode = frame.locator('#tree .node[data-type="file"]').first();
    await fileNode.scrollIntoViewIfNeeded();
    await expect(fileNode).toBeVisible();

    const aiInput = frame.locator('#ai-input-text');
    await aiInput.scrollIntoViewIfNeeded();

    const fileBox = await fileNode.boundingBox();
    const inputBox = await aiInput.boundingBox();
    const iframeBox = await page.locator('#app-iframe').boundingBox();
    expect(fileBox).toBeTruthy();
    expect(inputBox).toBeTruthy();
    expect(iframeBox).toBeTruthy();

    const startX = iframeBox!.x + fileBox!.x + fileBox!.width / 2;
    const startY = iframeBox!.y + fileBox!.y + fileBox!.height / 2;
    const endX = iframeBox!.x + inputBox!.x + inputBox!.width / 2;
    const endY = iframeBox!.y + inputBox!.y + inputBox!.height / 2;

    await page.mouse.move(startX, startY);
    await page.mouse.down();
    await page.waitForTimeout(120);
    await page.mouse.move(endX, endY, { steps: 30 });
    await page.waitForTimeout(120);
    await page.mouse.up();
    await page.waitForTimeout(600);

    await expect(frame.locator('.mentioned-file-chip')).toHaveCount(1, { timeout: 8000 });
    expect(errors.filter((e) => /Unexpected end of input/i.test(e))).toHaveLength(0);
  });
});
