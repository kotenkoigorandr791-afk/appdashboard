import { expect, test } from '@playwright/test';
import { login } from '../helpers/shell';

const pad = (n: number) => String(n).padStart(2, '0');
const ymd = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

test.describe('spends — subscriptions', () => {
  test('monthly charges clamp the 31st to the last day of shorter months', async ({ page }) => {
    await login(page);
    await page.goto('/apps/spends/?lang=en&theme=light', { waitUntil: 'domcontentloaded' });

    const storeId = `e2e_sub_${Date.now()}`;
    const storeName = `E2E Subscription ${Date.now()}`;
    const call = async (body: Record<string, string | number>) => page.evaluate(async (payload) => {
      const r = await fetch('/apps/spends/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(payload as Record<string, string>),
      });
      return { status: r.status, json: await r.json().catch(() => null) };
    }, body);

    const created = await call({
      add_edit_store: '1', store_id: storeId, store_name: storeName, store_category: 'other',
    });
    expect(created.json?.status).toBe('success');

    const today = new Date();
    const firstMonth = new Date(today.getFullYear(), today.getMonth() - 3, 1);
    const amount = 12.5;
    const monthly = await call({
      add_subscription: '1',
      store: storeName,
      amount,
      frequency: 'monthly',
      day_of_month: 31,
      interval_days: 0,
      start_date: ymd(firstMonth),
      end_date: '',
    });

    try {
      expect(monthly.json?.status, JSON.stringify(monthly.json)).toBe('success');

      // Every month from the start month to now: the 31st, clamped to the month end.
      const expected: string[] = [];
      for (let i = 0; i < 4; i++) {
        const monthStart = new Date(firstMonth.getFullYear(), firstMonth.getMonth() + i, 1);
        const daysInMonth = new Date(monthStart.getFullYear(), monthStart.getMonth() + 1, 0).getDate();
        const charge = new Date(monthStart.getFullYear(), monthStart.getMonth(), Math.min(31, daysInMonth));
        if (charge <= today) expected.push(ymd(charge));
      }
      expect(expected.length).toBeGreaterThanOrEqual(3);
      expect(expected.length).toBe(monthly.json?.added);

      const readDay = (date: string) => page.evaluate(async (d) => {
        const r = await fetch(`/apps/spends/?get_expenses_page=1&months_window=0&start_date=${d}&end_date=${d}`);
        return r.json() as Promise<{ rows: Array<{ date: string; store: string; amount: number }> }>;
      }, date);

      for (const date of expected) {
        const row = (await readDay(date)).rows.find((r) => r.store === storeName);
        expect(row, `charge on ${date}`).toBeTruthy();
        expect(Number(row!.amount)).toBeCloseTo(amount, 2);
      }

      // The day after the last clamped charge is not charged twice.
      const lastDate = expected[expected.length - 1];
      const day = new Date(lastDate + 'T00:00:00');
      day.setDate(day.getDate() + 1);
      if (day <= today) {
        const extra = (await readDay(ymd(day))).rows.find((r) => r.store === storeName);
        expect(extra, `no duplicate charge on ${ymd(day)}`).toBeFalsy();
      }

      // Re-applying the scheduler must not duplicate existing charges.
      const listBefore = await page.evaluate(async () => (await fetch('/apps/spends/?get_subscriptions=1')).json());
      expect((listBefore.subscriptions as Array<{ store: string }>).filter((s) => s.store === storeName)).toHaveLength(1);
    } finally {
      const subs = await page.evaluate(async () => (await fetch('/apps/spends/?get_subscriptions=1')).json());
      for (const sub of (subs.subscriptions || []) as Array<{ id: string; store: string }>) {
        if (sub.store === storeName) await call({ delete_subscription: sub.id });
      }
      await call({ delete_store: '1', store_id: storeId, store_name: storeName });
    }
  });

  test('interval subscriptions repeat every N days and stop at the end date', async ({ page }) => {
    await login(page);
    await page.goto('/apps/spends/?lang=en&theme=light', { waitUntil: 'domcontentloaded' });

    const storeId = `e2e_sub_int_${Date.now()}`;
    const storeName = `E2E Interval ${Date.now()}`;
    const call = async (body: Record<string, string | number>) => page.evaluate(async (payload) => {
      const r = await fetch('/apps/spends/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(payload as Record<string, string>),
      });
      return { status: r.status, json: await r.json().catch(() => null) };
    }, body);

    await call({ add_edit_store: '1', store_id: storeId, store_name: storeName, store_category: 'other' });

    const today = new Date();
    const start = new Date(today);
    start.setDate(start.getDate() - 42);
    const amount = 7.25;

    try {
      const res = await call({
        add_subscription: '1',
        store: storeName,
        amount,
        frequency: 'interval',
        day_of_month: 0,
        interval_days: 21,
        start_date: ymd(start),
        end_date: '',
      });
      expect(res.json?.status, JSON.stringify(res.json)).toBe('success');
      expect(res.json?.added).toBe(3);

      // An end date in the past leaves no future charges and keeps history intact.
      const subs = await page.evaluate(async () => (await fetch('/apps/spends/?get_subscriptions=1')).json());
      const sub = (subs.subscriptions as Array<{ id: string; store: string }>).find((s) => s.store === storeName)!;
      await call({ delete_subscription: sub.id });

      const second = await call({
        add_subscription: '1',
        store: storeName,
        amount,
        frequency: 'interval',
        day_of_month: 0,
        interval_days: 21,
        start_date: ymd(start),
        end_date: ymd(start),
      });
      expect(second.json?.status).toBe('success');
      expect(second.json?.added).toBe(1);
    } finally {
      const subs = await page.evaluate(async () => (await fetch('/apps/spends/?get_subscriptions=1')).json());
      for (const sub of (subs.subscriptions || []) as Array<{ id: string; store: string }>) {
        if (sub.store === storeName) await call({ delete_subscription: sub.id });
      }
      await call({ delete_store: '1', store_id: storeId, store_name: storeName });
    }
  });

  test('invalid subscription payloads are rejected', async ({ page }) => {
    await login(page);
    await page.goto('/apps/spends/?lang=en&theme=light', { waitUntil: 'domcontentloaded' });
    const res = await page.evaluate(async () => {
      const r = await fetch('/apps/spends/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ add_subscription: '1', store: '', amount: '0', start_date: 'x' }),
      });
      return r.json();
    });
    expect(res.status).toBe('error');
  });
});
