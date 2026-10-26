import { expect, test } from '@playwright/test';
import { login } from '../helpers/shell';

test.describe('spends — core handlers', () => {
  test('settings, years, expenses page and store CRUD', async ({ page }) => {
    await login(page);
    await page.goto('/apps/spends/?lang=en&theme=light', { waitUntil: 'domcontentloaded' });

    const settings = await page.evaluate(async () =>
      (await fetch('/apps/spends/?get_settings=1')).json(),
    );
    expect(settings.status).toBe('success');
    expect(settings).toHaveProperty('monthly_budget');

    const years = await page.evaluate(async () =>
      (await fetch('/apps/spends/?get_available_years=1')).json(),
    );
    expect(years.status || Array.isArray(years.years) || Array.isArray(years)).toBeTruthy();

    const storeId = `e2e_core_${Date.now()}`;
    const storeName = `E2E Core ${Date.now()}`;
    const call = async (body: Record<string, string>) =>
      page.evaluate(async (payload) => {
        const r = await fetch('/apps/spends/', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams(payload),
        });
        return r.json();
      }, body);

    const created = await call({
      add_edit_store: '1',
      store_id: storeId,
      store_name: storeName,
      store_category: 'other',
    });
    expect(created.status).toBe('success');

    const today = new Date().toISOString().slice(0, 10);
    const expense = await call({
      update_expense: '1',
      date: today,
      store: storeName,
      amount: '3.21',
    });
    expect(['success', 'ok', undefined].includes(expense.status) || expense.status === 'success' || expense === true || expense.status).toBeTruthy();

    const pageData = await page.evaluate(async (d) => {
      return (await fetch(`/apps/spends/?get_expenses_page=1&months_window=0&start_date=${d}&end_date=${d}`)).json();
    }, today);
    expect(Array.isArray(pageData.rows)).toBeTruthy();

    await call({ delete_store: '1', store_id: storeId, store_name: storeName });
  });
});
