import { expect, test } from '@playwright/test';
import { autoAcceptCookies, gotoShell, hasAuthUser, login } from '../helpers/shell';

test.describe('kanboard embedded app', () => {
  test.skip(!hasAuthUser(), 'E2E credentials required');

  test('opens board chrome without admin/logout and supports project/task', async ({ page }) => {
    await autoAcceptCookies(page);
    await login(page);
    await gotoShell(page, '/main?app=kanboard');
    const frame = page.frameLocator('#app-iframe');
    await expect(frame.locator('body')).toBeVisible({ timeout: 60000 });
    await expect(frame.locator('header .logo, h1 .logo')).toHaveCount(0);
    await expect(frame.getByRole('link', { name: /^Logout$/i })).toHaveCount(0);
    await expect(frame.getByRole('link', { name: /Users management/i })).toHaveCount(0);
    await expect(frame.getByRole('link', { name: /^Settings$/i })).toHaveCount(0);

    const projectName = `E2E Board ${Date.now()}`;
    await frame.getByRole('link', { name: /New project/i }).first().click();
    await expect(frame.locator('#modal-content #project-creation-form')).toBeVisible({ timeout: 15000 });
    await frame.locator('#modal-content input[name="name"]').fill(projectName);
    page.once('dialog', async (d) => d.dismiss().catch(() => {}));
    await frame.locator('#modal-content button[type="submit"]').click();
    await expect(
      frame.getByText(projectName).or(frame.getByText(/created successfully/i)).first(),
    ).toBeVisible({ timeout: 45000 });

    const addTask = frame.locator('#modal-content a, a').filter({ hasText: /Add a new task|New task/i }).first();
    const taskVisible = await addTask.isVisible().catch(() => false);
    if (!taskVisible) {
      const board = frame.getByRole('link', { name: /^Board$/i }).first();
      if (await board.isVisible().catch(() => false)) await board.click();
    }
    const taskBtn = frame.getByRole('link', { name: /Add a new task|New task/i }).first();
    if (await taskBtn.isVisible().catch(() => false)) {
      const taskTitle = `E2E Task ${Date.now()}`;
      await taskBtn.click();
      await expect(frame.locator('#modal-content input[name="title"], form input[name="title"]').first()).toBeVisible({
        timeout: 15000,
      });
      await frame.locator('#modal-content input[name="title"], form input[name="title"]').first().fill(taskTitle);
      await frame.locator('#modal-content button[type="submit"], form button[type="submit"]').first().click();
      await expect(frame.getByText(taskTitle).first()).toBeVisible({ timeout: 45000 });
    }

    await page.goto('/main?app=kanboard&lang=ru&theme=dark&color=%232563eb', { waitUntil: 'domcontentloaded' });
    const darkFrame = page.frameLocator('#app-iframe');
    await expect(darkFrame.locator('body')).toBeVisible({ timeout: 60000 });
    await expect(page.locator('#app-iframe')).toHaveAttribute('src', /theme=dark/);
    await expect
      .poll(async () => darkFrame.locator('body').evaluate((el) => el.className), { timeout: 15000 })
      .toMatch(/kb-dark/);
  });
});
