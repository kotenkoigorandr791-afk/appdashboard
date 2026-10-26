import { expect, test } from '@playwright/test';
import { login } from '../helpers/shell';

test.describe('no silent login in embedded context (task 004)', () => {
  test('unauthenticated embedded app request shows an explicit sign-in notice', async ({ request }) => {
    const res = await request.get('/apps/dawarich/index.php?embed=1', { maxRedirects: 0 });
    expect(res.status()).toBe(200);
    const body = await res.text();
    expect(body).toContain('Sign in required');
    expect(body).toContain('target="_top"');
    // No silent redirect to /login or the OAuth bridge.
    expect(res.headers()['location']).toBeUndefined();
  });

  test('unauthenticated embedded /authorize does not issue a code or redirect silently', async ({ request }) => {
    const qs = new URLSearchParams({
      client_id: 'immich',
      redirect_uri: 'https://photo.serverkherson.xyz/auth/login',
      response_type: 'code',
      scope: 'openid',
      embed: '1',
    });
    const res = await request.get(`/authorize?${qs.toString()}`, { maxRedirects: 0 });
    expect(res.status()).toBe(200);
    const body = await res.text();
    expect(body).toContain('Sign in required');
    expect(res.headers()['location']).toBeUndefined();
  });

  test('embedded request for another app (gitea) is also gated', async ({ request }) => {
    const res = await request.get('/apps/gitea/index.php?embed=1', { maxRedirects: 0 });
    expect(res.status()).toBe(200);
    expect(await res.text()).toContain('Sign in required');
  });

  test('authenticated user in the same browser still gets into the app iframe', async ({ page }) => {
    await login(page);
    const result = await page.evaluate(async () => {
      const r = await fetch('/apps/dawarich/index.php?embed=1', { redirect: 'manual' });
      const text = r.type === 'opaqueredirect' ? '' : await r.text();
      return { status: r.status, type: r.type, isNotice: text.includes('Sign in required') };
    });
    // The authenticated browser context must NOT be blocked by the new notice.
    expect(result.isNotice).toBe(false);
  });

  test('non-embedded unauthenticated request keeps the previous redirect behavior', async ({ request }) => {
    const res = await request.get('/apps/dawarich/index.php', { maxRedirects: 0 });
    // No embed marker: the app may redirect (login/bridge) as before.
    expect([200, 301, 302]).toContain(res.status());
  });
});
