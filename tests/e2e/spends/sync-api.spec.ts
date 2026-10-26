import { expect, test } from '@playwright/test';
import { createHash } from 'crypto';
import { login } from '../helpers/shell';

function sig(date: string, store: string, amount: number, raw = '') {
  const payload = [
    date,
    store.trim().toLowerCase(),
    Number(amount).toFixed(2),
    raw.trim().toLowerCase(),
  ].join('|');
  return createHash('sha256').update(payload).digest('hex');
}

test.describe('spends — mobile sync API', () => {
  test('profile, pull, push create then skip by signature, goals round-trip', async ({ page }) => {
    await login(page);
    await page.goto('/apps/spends/?lang=en&theme=light', { waitUntil: 'domcontentloaded' });

    const tokenRow = await page.evaluate(async () => {
      const csrfRes = await fetch('/csrf', { credentials: 'same-origin', cache: 'no-store' });
      const csrfJson = await csrfRes.json().catch(() => null);
      const csrf = csrfJson?.csrf || '';
      const fd = new FormData();
      fd.append('action', 'generate_mcp_token');
      fd.append('generate_mcp_token', '1');
      fd.append('ajax', '1');
      fd.append('token_app', 'spends');
      fd.append('mcp_token_name', 'e2e sync');
      if (csrf) fd.append('_csrf', csrf);
      const r = await fetch('/main', { method: 'POST', body: fd, credentials: 'same-origin' });
      return r.json().catch(() => null);
    });
    expect(tokenRow?.success, JSON.stringify(tokenRow)).toBeTruthy();
    const token = String(tokenRow?.token || '');
    expect(token.length).toBeGreaterThan(20);

    const api = async (path: string, init?: RequestInit) =>
      page.evaluate(
        async ({ path: p, init: i, tok }) => {
          const r = await fetch(p, {
            ...i,
            headers: {
              ...(i?.headers || {}),
              Authorization: `Bearer ${tok}`,
              'X-Device-Id': 'e2e-playwright',
              Accept: 'application/json',
            },
          });
          return { status: r.status, json: await r.json().catch(() => null) };
        },
        { path, init, tok: token },
      );

    const profile = await api('/api/spends/profile');
    expect(profile.status).toBe(200);
    expect(profile.json?.status).toBe('success');
    expect(profile.json?.profile?.app).toBe('spends');
    expect(String(profile.json?.profile?.email || '')).toContain('@');

    const pull = await api('/api/spends/sync');
    expect(pull.status).toBe(200);
    expect(pull.json?.status).toBe('success');
    expect(Array.isArray(pull.json?.expenses)).toBeTruthy();
    expect(Array.isArray(pull.json?.known_signatures)).toBeTruthy();
    expect(Array.isArray(pull.json?.goals)).toBeTruthy();

    const date = new Date().toISOString().slice(0, 10);
    const store = `E2E Sync ${Date.now()}`;
    const amount = 7.77;
    const signature = sig(date, store, amount);
    const push1 = await api('/api/spends/sync', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        expenses: [{ date, store, amount, signature, source: 'mobile', mode: 'skip_if_known' }],
      }),
    });
    expect(push1.json?.status).toBe('success');
    expect(Number(push1.json?.created)).toBeGreaterThanOrEqual(1);

    const push2 = await api('/api/spends/sync', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        expenses: [{ date, store, amount, signature, source: 'mobile', mode: 'skip_if_known' }],
      }),
    });
    expect(push2.json?.status).toBe('success');
    expect(Number(push2.json?.skipped)).toBeGreaterThanOrEqual(1);

    const goalName = `Sync Goal ${Date.now()}`;
    const goalPush = await api('/api/spends/sync', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        goals: [
          {
            name: goalName,
            goal_type: 'savings',
            target_amount: 50,
            current_amount: 5,
            currency: 'USD',
          },
        ],
      }),
    });
    expect(goalPush.json?.status).toBe('success');

    const pull2 = await api('/api/spends/sync');
    expect((pull2.json?.goals as Array<{ name: string }>).some((g) => g.name === goalName)).toBeTruthy();
  });
});
