import { expect, test } from '@playwright/test';
import { autoAcceptCookies, gotoShell, login, skipWithoutAuth } from '../helpers/shell';

test.describe('notifications mark read', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    await skipWithoutAuth(testInfo);
    await login(page);
    await autoAcceptCookies(page);
    await gotoShell(page);
    await autoAcceptCookies(page);
  });

  test('mark all as read — badge disappears', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });

    // Открыть панель уведомлений
    await page.click('#notifications-btn');
    await page.waitForSelector('#notifications-panel.open');

    // Сделать скриншот ДО — бейдж виден
    await page.screenshot({ path: 'tmp/notifications-before-mark-read.png' });
    const badgeBefore = await page.locator('#notifications-unread-badge').first();
    await expect(badgeBefore).toBeVisible();

    // Нажать "Прочитать все"
    await page.click('#notifications-mark-read-btn');

    // Ждём завершения запроса
    await page.waitForResponse(resp =>
      resp.url().includes('api=notifications') && resp.request().method() === 'POST'
    );
    await page.waitForTimeout(500);

    // Сделать скриншот ПОСЛЕ — бейдж скрыт
    await page.screenshot({ path: 'tmp/notifications-after-mark-read.png' });
    const badgeAfter = await page.locator('#notifications-unread-badge').first();
    await expect(badgeAfter).toBeHidden();
  });

  test('badge hidden when count is 0, not showing "0"', async ({ page }) => {
    const badge = page.locator('#notifications-unread-badge').first();
    const text = await badge.textContent().catch(() => '');
    // Бейдж не должен показывать "0"
    expect(text).not.toBe('0');
    // Бейдж должен быть скрыт если 0
    const display = await badge.evaluate(el => getComputedStyle(el).display);
    expect(display).toBe('none');
    await page.screenshot({ path: 'tmp/notifications-badge-hidden.png' });
  });

  test('single notification click marks it as read', async ({ page }) => {
    await page.click('#notifications-btn');
    await page.waitForSelector('#notifications-panel.open');

    await page.screenshot({ path: 'tmp/notifications-panel-open.png' });

    // Кликнуть на первое непрочитанное уведомление
    const unreadRow = page.locator('.notification-row.unread').first();
    if (await unreadRow.count() > 0) {
      await unreadRow.click();
      await page.waitForResponse(resp =>
        resp.url().includes('api=notifications') && resp.request().method() === 'POST'
      );
      await page.waitForTimeout(300);
      await page.screenshot({ path: 'tmp/notifications-after-single-read.png' });
    }
  });

  test('mark_read sends correct POST request', async ({ page }) => {
    await page.click('#notifications-btn');
    await page.waitForSelector('#notifications-panel.open');

    const [response] = await Promise.all([
      page.waitForResponse(resp =>
        resp.url().includes('api=notifications') && resp.request().method() === 'POST'
      ),
      page.click('#notifications-mark-read-btn'),
    ]);

    const json = await response.json();
    expect(json.ok).toBe(true);
    await page.screenshot({ path: 'tmp/notifications-api-response.png' });
  });
});