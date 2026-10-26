import { expect, test, type Page } from '@playwright/test';
import { login } from '../helpers/shell';

type ShareApiResult = { status: number; json: any };

async function shareApi(page: Page, payload: Record<string, unknown>): Promise<ShareApiResult> {
  const res = await page.request.post('/apps/spends/api/share.php', {
    data: payload,
    failOnStatusCode: false,
  });
  return { status: res.status(), json: await res.json().catch(() => null) };
}

async function activeShares(page: Page): Promise<any[]> {
  const listed = await shareApi(page, { action: 'list', resource_app: 'spends', resource_type: 'app', status: 'active' });
  return Array.isArray(listed.json?.shares) ? listed.json.shares : [];
}

async function revokeCreated(page: Page, title: string): Promise<void> {
  for (const share of await activeShares(page)) {
    if (String(share?.title ?? '').includes(title)) {
      await shareApi(page, { action: 'revoke', id: share.id });
    }
  }
}

/** The dialog renders an "existing link" state for a resource that already
 *  has one, which hides the create form. Start from a clean chart link so the
 *  create path (password required, finite expiry) is what gets exercised. */
async function revokeChartLinks(page: Page): Promise<void> {
  for (const share of await activeShares(page)) {
    if (String(share?.resource_id ?? '') === 'expense_chart') {
      await shareApi(page, { action: 'revoke', id: share.id });
    }
  }
}

async function openShareModal(page: Page) {
  await page.locator('.header-buttons .header-btn[onclick="showShareModal()"]').click();
  const modal = page.locator('.sp-share-modal');
  await expect(modal).toBeVisible();
  return modal;
}

test.describe('spends — share dialog', () => {
  test.describe.configure({ timeout: 90000 });

  test('rejects password-less links, drops edit, and forces a finite expiry', async ({ page }) => {
    await login(page);
    await page.goto('/apps/spends/?lang=en&theme=light', { waitUntil: 'domcontentloaded' });
    await revokeChartLinks(page);

    // 1. Server: a spending link can never be created without a password.
    const noPassword = await shareApi(page, {
      action: 'create',
      resource_app: 'spends',
      resource_type: 'app',
      resource_id: 'expense_chart',
      title: `E2E no password ${Date.now()}`,
      permissions: ['view'],
      expires_option: '1m',
      password: '',
    });
    expect(noPassword.status).toBe(400);
    expect(noPassword.json?.error).toBe('password_required');

    // 2. Server: "edit" is stripped and "never" collapses to a real date.
    const title = `E2E share ${Date.now()}`;
    const created = await shareApi(page, {
      action: 'create',
      resource_app: 'spends',
      resource_type: 'app',
      resource_id: 'expense_chart',
      title,
      permissions: ['view', 'interact', 'edit'],
      expires_option: 'never',
      password: 's3cret-pass',
    });
    expect(created.status, JSON.stringify(created.json)).toBe(200);
    expect(created.json?.success).toBe(true);
    expect(Array.isArray(created.json?.share?.permissions)).toBe(true);
    expect(created.json.share.permissions).not.toContain('edit');
    expect(created.json.share.expires_option).not.toBe('never');
    expect(created.json.share.expires_at).toBeTruthy();
    expect(created.json.share.password_protected).toBe(true);
    // The dialog shows an "existing link" state for a resource that already
    // has one, so drop this probe before exercising the create form.
    await shareApi(page, { action: 'revoke', id: created.json.share.id });

    try {
      // 3. Client: same rules are visible in the dialog itself.
      const modal = await openShareModal(page);
      await expect(modal.locator('.sp-share__chip input[value="edit"]')).toHaveCount(0);
      const expiryOptions = await modal.locator('#sp-share-expiry option').evaluateAll((opts) =>
        opts.map((o) => (o as HTMLOptionElement).value),
      );
      expect(expiryOptions).not.toContain('never');
      expect(expiryOptions.length).toBeGreaterThan(0);
      await expect(modal.locator('#sp-share-password')).toHaveAttribute('required', '');
      await expect(modal.locator('#sp-share-password')).toHaveAttribute('autocomplete', 'new-password');

      // 4. Client: an empty password blocks the create button with an inline error.
      await modal.locator('#sp-share-title-input').fill(`E2E UI share ${Date.now()}`);
      await modal.locator('[data-create]').click();
      const errorBox = modal.locator('[data-error]');
      await expect(errorBox).toBeVisible();
      await expect(errorBox).toContainText(/password/i);
      await expect(modal.locator('.sp-share__success')).toHaveCount(0);

      // 5. Client: with a password the link is created and shown.
      await modal.locator('#sp-share-password').fill('ui-secret-1');
      await modal.locator('[data-create]').click();
      await expect(modal.locator('.sp-share__success')).toBeVisible({ timeout: 20000 });
      const link = await modal.locator('.sp-share__link-box input').inputValue();
      expect(link).toContain('/s/');
      await expect(modal.locator('.sp-share__meta')).toContainText(/Expires/i);
      await expect(modal.locator('.sp-share__meta')).not.toContainText(/never/i);
    } finally {
      await revokeCreated(page, 'E2E share');
      await revokeCreated(page, 'E2E UI share');
    }
  });

  test('share dialog follows the app theme and stays readable in dark mode', async ({ page }) => {
    await login(page);
    await page.goto('/apps/spends/?lang=en&theme=dark', { waitUntil: 'domcontentloaded' });

    const modal = await openShareModal(page);

    // Native selects must follow the page colour scheme so the popup text is
    // not rendered dark-on-dark.
    const scheme = await modal.locator('#sp-share-expiry').evaluate((el) => getComputedStyle(el).colorScheme);
    expect(scheme).toContain('dark');

    // The dialog surface uses the app's own modal tokens, not a foreign theme.
    const tokens = await modal.evaluate((el) => {
      const cs = getComputedStyle(el);
      return {
        radius: cs.borderTopLeftRadius,
        border: cs.borderTopColor,
        tokenRadius: getComputedStyle(document.documentElement).getPropertyValue('--radius-lg').trim(),
      };
    });
    expect(tokens.tokenRadius).not.toBe('');
    expect(tokens.radius).toBe(tokens.tokenRadius);
    expect(tokens.border).not.toBe('');

    // Expiry text must clear WCAG AA against the composited select surface.
    const contrast = await modal.locator('#sp-share-expiry').evaluate((el) => {
      const parse = (c: string): [number, number, number, number] | null => {
        const m = c.match(/rgba?\(([^)]+)\)/);
        if (!m) return null;
        const p = m[1].split(',').map((v) => parseFloat(v));
        return [p[0], p[1], p[2], p.length > 3 ? p[3] : 1];
      };
      const lin = (v: number) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
      const lum = ([r, g, b]: number[]) => 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
      let bg = [255, 255, 255, 1] as [number, number, number, number];
      const layers: Array<[number, number, number, number]> = [];
      let node: Element | null = el;
      while (node) {
        const c = parse(getComputedStyle(node).backgroundColor);
        if (c && c[3] > 0) layers.push(c);
        node = node.parentElement;
      }
      for (let i = layers.length - 1; i >= 0; i--) {
        const [fr, fg2, fb, fa] = layers[i];
        const a = fa + bg[3] * (1 - fa);
        bg = [
          (fr * fa + bg[0] * bg[3] * (1 - fa)) / a,
          (fg2 * fa + bg[1] * bg[3] * (1 - fa)) / a,
          (fb * fa + bg[2] * bg[3] * (1 - fa)) / a,
          a,
        ];
      }
      const fgRaw = parse(getComputedStyle(el).color) || [0, 0, 0, 1];
      const fa = fgRaw[3];
      const fg = [
        fgRaw[0] * fa + bg[0] * (1 - fa),
        fgRaw[1] * fa + bg[1] * (1 - fa),
        fgRaw[2] * fa + bg[2] * (1 - fa),
      ];
      const l1 = lum(fg as [number, number, number]);
      const l2 = lum(bg as [number, number, number]);
      return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
    });
    expect(contrast).toBeGreaterThanOrEqual(4.5);

    await page.locator('.sp-share__close').click();
    await expect(modal).toBeHidden();
  });
});
