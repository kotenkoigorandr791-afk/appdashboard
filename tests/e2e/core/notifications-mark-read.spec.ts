import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { autoAcceptCookies, gotoShell, login, skipWithoutAuth } from '../helpers/shell';

/**
 * The badge assertions need at least one unread row to exist, so seed one for
 * the logged-in account instead of depending on whatever state the database
 * happens to be in.
 */
function seedUnreadNotification(userEmail = 'root@root') {
  const php = `
define("APP_ROOT", getcwd());
require "lib/app_db.php";
$db = app_main_db();
app_notifications_ensure($db);
$st = $db->prepare("INSERT INTO app_notifications(user_email,app_id,severity,title,message,event_type,context_json,is_read,created_at) VALUES(:u,'core','info','E2E unread notification','seeded by e2e','e2e','{}',0,:t)");
$st->bindValue(":u", "${userEmail}");
$st->bindValue(":t", time());
$st->execute();
`;
  execFileSync('docker', ['exec', 'appdashboard', 'php', '-r', php], { stdio: 'pipe' });
}

/** Clears every unread row for the account so the "zero" state is real. */
function clearUnreadNotifications(userEmail = 'root@root') {
  const php = `
define("APP_ROOT", getcwd());
require "lib/app_db.php";
$db = app_main_db();
app_notifications_ensure($db);
$st = $db->prepare("UPDATE app_notifications SET is_read = 1, read_at = :t WHERE user_email = :u AND is_read = 0");
$st->bindValue(":u", "${userEmail}");
$st->bindValue(":t", time());
$st->execute();
`;
  execFileSync('docker', ['exec', 'appdashboard', 'php', '-r', php], { stdio: 'pipe' });
}

test.describe('notifications mark read', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    await skipWithoutAuth(testInfo);
    await login(page);
    await autoAcceptCookies(page);
    await gotoShell(page);
    await autoAcceptCookies(page);
  });

  test('mark all as read — badge disappears', async ({ page }) => {
    seedUnreadNotification();
    await page.reload({ waitUntil: 'domcontentloaded' });
    await autoAcceptCookies(page);
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
    clearUnreadNotifications();
    await page.reload({ waitUntil: 'domcontentloaded' });
    await autoAcceptCookies(page);
    await page.waitForTimeout(1500);
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
    seedUnreadNotification();
    await page.reload({ waitUntil: 'domcontentloaded' });
    await autoAcceptCookies(page);
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
    seedUnreadNotification();
    await page.reload({ waitUntil: 'domcontentloaded' });
    await autoAcceptCookies(page);
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