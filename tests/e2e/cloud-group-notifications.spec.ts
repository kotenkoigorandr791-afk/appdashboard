import { expect, test } from '@playwright/test';
import { autoAcceptCookies, login, skipWithoutAuth } from './helpers/shell';

test.describe('cloud group notifications', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    await skipWithoutAuth(testInfo);
    await login(page);
    await autoAcceptCookies(page);
    await page.goto('/main?app=cloud&lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
    await autoAcceptCookies(page);
  });

  test('marks group file-change notifications read when a group is opened', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', msg => {
      if (msg.type() === 'error') errors.push(msg.text());
    });
    await expect(page.locator('#root-toolbar')).toBeVisible();
    const group = await page.evaluate(async () => {
      const name = 'pw-notify-' + Date.now();
      const created = await (window as any).api('groups_create', { name });
      await (window as any).cloudGroups.loadGroups();
      return created.group;
    });
    const markRead = page.waitForResponse(response => response.url().includes('groups_mark_notifications_read') && response.request().method() === 'POST');
    await page.evaluate(async groupId => {
      await (window as any).cloudGroups.switchToGroup(groupId);
    }, group.id);
    const response = await markRead;
    expect(response.ok()).toBeTruthy();
    await expect(page.locator('#btn-root-groups')).toHaveClass(/active/);
    await page.screenshot({ path: 'tests/test-results/cloud-group-notifications.png', fullPage: true });
    await page.evaluate(async groupId => {
      await (window as any).api('groups_delete', { group_id: groupId });
    }, group.id);
    expect(errors).toEqual([]);
  });
});
