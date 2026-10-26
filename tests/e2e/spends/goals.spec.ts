import { expect, test } from '@playwright/test';
import { login } from '../helpers/shell';

test.describe('spends — goals', () => {
  test('create, contribute, archive, restore and delete a goal', async ({ page }) => {
    await login(page);
    await page.goto('/apps/spends/?lang=en&theme=light', { waitUntil: 'domcontentloaded' });

    const name = `E2E Goal ${Date.now()}`;
    const call = async (body: Record<string, string | number>) =>
      page.evaluate(async (payload) => {
        const r = await fetch('/apps/spends/', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams(payload as Record<string, string>),
        });
        return { status: r.status, json: await r.json().catch(() => null) };
      }, body);

    const created = await call({
      save_goal: '1',
      name,
      goal_type: 'savings',
      target_amount: 100,
      current_amount: 10,
      currency: 'USD',
      target_date: '2030-12-31',
      description: 'e2e',
    });
    expect(created.json?.status, JSON.stringify(created.json)).toBe('success');
    const id = String(created.json?.goal?.id || '');
    expect(id).not.toBe('');
    expect(Number(created.json?.goal?.progress_percent)).toBeCloseTo(10, 1);

    try {
      const contributed = await call({ contribute_goal: id, id, amount: 15 });
      expect(contributed.json?.status).toBe('success');
      expect(Number(contributed.json?.goal?.current_amount)).toBeCloseTo(25, 2);
      expect(Number(contributed.json?.goal?.progress_percent)).toBeCloseTo(25, 1);

      const listed = await page.evaluate(async () =>
        (await fetch('/apps/spends/?get_goals=1')).json(),
      );
      expect((listed.goals as Array<{ id: string }>).some((g) => g.id === id)).toBeTruthy();

      const archived = await call({ archive_goal: id, is_archived: 1 });
      expect(archived.json?.status).toBe('success');
      expect(archived.json?.goal?.is_archived).toBeTruthy();

      const activeOnly = await page.evaluate(async () =>
        (await fetch('/apps/spends/?get_goals=1')).json(),
      );
      expect((activeOnly.goals as Array<{ id: string }>).some((g) => g.id === id)).toBeFalsy();

      const withArchived = await page.evaluate(async () =>
        (await fetch('/apps/spends/?get_goals=1&include_archived=1')).json(),
      );
      expect((withArchived.goals as Array<{ id: string }>).some((g) => g.id === id)).toBeTruthy();

      const restored = await call({ archive_goal: id, is_archived: 0 });
      expect(restored.json?.goal?.is_archived).toBeFalsy();
    } finally {
      const deleted = await call({ delete_goal: id });
      expect(deleted.json?.status).toBe('success');
      const after = await page.evaluate(async () =>
        (await fetch('/apps/spends/?get_goals=1&include_archived=1')).json(),
      );
      expect((after.goals as Array<{ id: string }>).some((g) => g.id === id)).toBeFalsy();
    }
  });

  test('goals settings section renders editor UI', async ({ page }) => {
    await login(page);
    await page.goto('/apps/spends/?lang=en&theme=light', { waitUntil: 'domcontentloaded' });
    await page.locator('.header-btn[title*="Settings"], .header-btn[onclick*="settings-modal"]').first().click();
    await expect(page.locator('#settings-modal')).toBeVisible();
    await page.locator('[data-settings-section="goals"]').click();
    await expect(page.locator('#settings-section-goals')).toBeVisible();
    await page.locator('#goals-add-btn').click();
    await expect(page.locator('#goal-editor')).toBeVisible();
    await expect(page.locator('#goal-name')).toBeVisible();
  });
});
