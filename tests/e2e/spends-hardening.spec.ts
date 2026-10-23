import { expect, test } from '@playwright/test';
import { hasAuthUser, login } from './helpers/shell';

const blockedPaths = [
  '/apps/spends/utils/parser_bridge.py',
  '/apps/spends/global_settings.json',
  '/apps/spends/backups/root_root/a.csv?download=1',
  '/apps/spends/users/root_root/a.csv?download=1',
];

test('spends — unauthenticated requests are rejected', async ({ request }) => {
  for (const p of blockedPaths) {
    const res = await request.get(p, { maxRedirects: 0 });
    expect([403, 404], p).toContain(res.status());
  }
  const api = await request.get('/apps/spends/index.php?get_settings=1', { headers: { Accept: 'application/json' }, maxRedirects: 0 });
  expect(api.status()).toBe(401);
  const ocr = await request.get('/apps/spends/utils/expense_ocr_app.php?api=stores', { headers: { Accept: 'application/json' }, maxRedirects: 0 });
  expect(ocr.status()).toBe(401);
});

test('spends — csrf, sparse storage and fx allowlist', async ({ page }) => {
  test.skip(!hasAuthUser(), 'Needs E2E_USER_EMAIL/PASSWORD');
  await login(page);
  await page.goto('/apps/spends/?lang=en&theme=light', { waitUntil: 'domcontentloaded' });
  const token = await page.evaluate(() => (window as any).spendsCsrfToken as string);
  expect(token).toMatch(/^[0-9a-f]{64}$/);
  const post = (body: Record<string, string>, withToken: boolean) =>
    page.evaluate(async ({ body, withToken, token }) => {
      const headers: Record<string, string> = withToken ? { 'X-CSRF-Token': token } : {};
      const r = await fetch('/apps/spends/', {
        method: 'POST',
        headers: { ...headers, 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(body),
        credentials: 'same-origin',
      });
      return { status: r.status, json: await r.json().catch(() => null) };
    }, { body, withToken, token });
  const bare = await page.request.post('/apps/spends/', { form: { update_income: '1', date: '2020-01-01', amount: '1' }, maxRedirects: 0 });
  expect(bare.status()).toBe(403);
  const date = '2020-01-15';
  const ok = await post({ update_income: '1', date, amount: '12.5' }, true);
  expect(ok.json?.status).toBe('success');
  const read = (d: string) => page.evaluate(async (d) => {
    const r = await fetch(`/apps/spends/?get_expenses_page=1&months_window=0&start_date=${d}&end_date=${d}`);
    return r.json();
  }, d);
  expect((await read(date)).incomes.length).toBe(1);
  const zero = await post({ update_income: '1', date, amount: '0' }, true);
  expect(zero.json?.status).toBe('success');
  expect((await read(date)).incomes.length).toBe(0);
  const fx = await page.evaluate(async () => (await fetch('/apps/spends/?get_exchange_rates=1&base=../x')).json());
  expect(fx.status).toBe('error');
});
