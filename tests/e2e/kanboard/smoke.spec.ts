import { expect, test } from '@playwright/test';
import { autoAcceptCookies, gotoShell, hasAuthUser, login } from '../helpers/shell';

test.describe('kanboard embedded app', () => {
  test.skip(!hasAuthUser(), 'E2E credentials required');
  test.describe.configure({ timeout: 120000 });

  test('opens board chrome without admin/logout and supports project/task', async ({ page }) => {
    await autoAcceptCookies(page);
    await login(page);
    await gotoShell(page, '/main?app=kanboard');
    const frame = page.frameLocator('#app-iframe');
    await expect(frame.locator('#kb-app-sidebar')).toBeVisible({ timeout: 60000 });
    await expect(frame.locator('header .logo, h1 .logo')).toHaveCount(0);
    await expect(frame.getByRole('link', { name: /^Logout$/i })).toHaveCount(0);
    await expect(frame.getByRole('link', { name: /Users management/i })).toHaveCount(0);
    await expect(frame.getByRole('link', { name: /^Settings$/i })).toHaveCount(0);

    const boardLinks = frame.locator('#kb-app-sidebar a[href*="BoardViewController"]');
    if (!(await boardLinks.count())) {
      const createBtn = frame.locator('header .header-creation-menu > a').first();
      await createBtn.click({ timeout: 15000 });
      await expect(frame.locator('#modal-content #project-creation-form')).toBeVisible({ timeout: 15000 });
      await frame.locator('#modal-content input[name="name"]').fill(`E2E Board ${Date.now()}`);
      const privateCb = frame.locator('#modal-content input[name="is_private"]');
      if (await privateCb.count()) await privateCb.uncheck().catch(() => {});
      await frame.locator('#modal-content button[type="submit"]').click();
      await expect(boardLinks.first()).toBeVisible({ timeout: 45000 });
    }

    const href = await boardLinks.first().getAttribute('href');
    expect(href).toBeTruthy();
    await frame.locator('body').evaluate((_, h) => {
      location.assign(h as string);
    }, href);
    await expect(frame.locator('.kb-cycle-workspace, #board, .kb-cycle-views').first()).toBeVisible({
      timeout: 60000,
    });

    const taskTitle = `E2E Task ${Date.now()}`;
    const newTask = frame.locator('a.kb-cycle-new-task, a:has-text("New task"), a:has-text("Add a new task")').first();
    await expect(newTask).toBeVisible({ timeout: 20000 });
    await newTask.click();
    await expect(frame.locator('#modal-content .kb-plane-create, .kb-plane-create, #modal-content input[name="title"]').first()).toBeVisible({
      timeout: 15000,
    });
    await frame.locator('#modal-content input[name="title"], .kb-plane-create #form-title, form input[name="title"]').first().fill(taskTitle);
    await frame.locator('.kb-plane-save, #modal-content button[type="submit"]').first().click();
    await expect(frame.getByText(taskTitle).first()).toBeVisible({ timeout: 45000 });

    await page.goto('/main?app=kanboard&lang=ru&theme=dark&color=%232563eb', { waitUntil: 'domcontentloaded' });
    const darkFrame = page.frameLocator('#app-iframe');
    await expect(page.locator('#app-iframe')).toHaveAttribute('src', /theme=dark/, { timeout: 30000 });
    await expect
      .poll(
        async () => {
          const body = darkFrame.locator('body');
          if (!(await body.count())) return '';
          return body.evaluate((el) => el.className);
        },
        { timeout: 45000 },
      )
      .toMatch(/kb-dark/);
  });
});

