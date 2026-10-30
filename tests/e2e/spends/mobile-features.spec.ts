import { expect, test } from '@playwright/test';
import { login } from '../helpers/shell';

test.describe('spends — mobile features API', () => {
  test('rates, backups, tags, ocr and sync tags', async ({ page }) => {
    await login(page);
    await page.goto('/apps/spends/?lang=en&theme=light', { waitUntil: 'domcontentloaded' });
    const token = await page.evaluate(async () => {
      const csrf = (await (await fetch('/csrf', { credentials: 'same-origin', cache: 'no-store' })).json().catch(() => null))?.csrf || '';
      const fd = new FormData();
      fd.append('action', 'generate_mcp_token');
      fd.append('generate_mcp_token', '1');
      fd.append('ajax', '1');
      fd.append('token_app', 'spends');
      fd.append('mcp_token_name', 'e2e features');
      if (csrf) fd.append('_csrf', csrf);
      const r = await fetch('/main', { method: 'POST', body: fd, credentials: 'same-origin' });
      return String((await r.json().catch(() => null))?.token || '');
    });
    expect(token.length).toBeGreaterThan(20);
    const api = (path: string, init?: Record<string, unknown>) =>
      page.evaluate(
        async ({ p, i, tok }) => {
          const r = await fetch(p, { ...i, headers: { ...((i as any)?.headers || {}), Authorization: `Bearer ${tok}`, Accept: 'application/json' } });
          return { status: r.status, json: await r.json().catch(() => null) };
        },
        { p: path, i: init, tok: token },
      );
    const form = (o: Record<string, string>) => ({
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(o).toString(),
    });

    const rates = await api('/api/spends?get_exchange_rates=1&base=USD');
    expect(rates.json?.status).toBe('success');
    expect(rates.json?.rates?.EUR).toBeGreaterThan(0);

    const name = `e2e_${Date.now()}`;
    expect((await api('/api/spends', form({ backup_action: 'save', backup_name: name }))).json?.status).toBe('success');
    const list = await api('/api/spends', form({ backup_action: 'list' }));
    expect(list.json?.backups.map((b: { name: string }) => b.name)).toContain(`${name}.csv`);
    expect((await api('/api/spends', form({ backup_action: 'delete', backup_file: `${name}.csv` }))).json?.status).toBe('success');

    const tag = `tag${Date.now()}`;
    const saved = await api('/api/spends', form({ save_tag: '1', name: tag, color: '#ff0000' }));
    expect(saved.json?.status).toBe('success');
    const tags = await api('/api/spends?get_tags=1');
    expect(tags.json?.tags.map((t: { name: string }) => t.name)).toContain(tag);
    const pulled = await api('/api/spends/sync');
    expect(pulled.json?.tags.map((t: { name: string }) => t.name)).toContain(tag);
    const pushName = `pushed${Date.now()}`;
    await api('/api/spends/sync', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ tags: [{ name: pushName }] }) });
    expect((await api('/api/spends?get_tags=1')).json?.tags.map((t: { name: string }) => t.name)).toContain(pushName);
    for (const t of (await api('/api/spends?get_tags=1')).json.tags) {
      await api('/api/spends', form({ delete_tag: t.id }));
    }

    const ocr = await api('/api/spends/utils/expense_ocr_app?api=active_job');
    expect(ocr.json?.status).toBe('ok');
    const empty = await api('/api/spends/utils/expense_ocr_app?api=start', { method: 'POST' });
    expect(empty.json?.message).toBe('no_files');
  });

  test('web tags tab works', async ({ page }) => {
    await login(page);
    await page.goto('/apps/spends/?lang=en&theme=light', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => {
      document.getElementById('settings-modal')?.classList.add('show');
      (document.getElementById('settings-modal') as HTMLElement).style.display = 'block';
      (window as any).switchSettingsSection('tags');
    });
    const name = `webtag${Date.now()}`;
    await page.fill('#tag-name', name);
    await page.locator('#tag-store-picker').click();
    await page.locator('#tag-picker-list .tag-picker-option').first().click();
    await page.locator('#tag-payment-picker').click();
    await page.locator('#tag-picker-list .tag-picker-option').first().click();
    const selectedPayment = await page.locator('#tag-payment').inputValue();
    const selectedStore = await page.locator('#tag-store').inputValue();
    await page.evaluate(() => (window as any).saveTagFromForm());
    await expect(page.locator('#tags-list .tag-chip', { hasText: name })).toBeVisible();
    const assignment = await page.evaluate(async ({ date, store }) => {
      const response = await fetch(`?get_expense_tags=1&from=${date}&to=${date}`);
      const data = await response.json();
      return data.expense_tags?.[`${date}\t${store}`] || [];
    }, { date: selectedPayment, store: selectedStore });
    expect(assignment.length).toBeGreaterThan(0);
    await page.locator('#tags-list .tag-row', { hasText: name }).locator('.tag-delete').click();
    await expect(page.locator('#confirm-delete-modal')).toBeVisible();
    await page.locator('#confirm-delete-action').click();
    await expect(page.locator('#tags-list .tag-chip', { hasText: name })).toHaveCount(0);
  });

  test('list pagination is conditional, bottom-aligned, and tag form fits a phone viewport', async ({ page }) => {
    await login(page);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/apps/spends/?lang=en&theme=light', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => {
      document.getElementById('settings-modal')?.classList.add('show');
      (document.getElementById('settings-modal') as HTMLElement).style.display = 'flex';
      (window as any).switchSettingsSection('tags');
    });

    await expect(page.locator('.settings-pagination')).toHaveCount(0);
    await page.evaluate(() => {
      eval("tagsCache = Array.from({ length: 20 }, (_, i) => ({ id: `test-${i}`, name: `Tag ${i}`, color: '#4caf50', usage_count: 0 }))");
      (window as any).renderTagsList();
    });
    const tagPager = page.locator('#tags-pagination');
    await expect(tagPager).toBeVisible();
    const listBox = await page.locator('#tags-list').boundingBox();
    const pagerBox = await tagPager.boundingBox();
    expect(pagerBox!.y).toBeGreaterThan(listBox!.y + listBox!.height - 1);
    await tagPager.getByRole('button', { name: 'Next page' }).click();
    await expect(tagPager.locator('.settings-page-status')).toHaveText('2 of 3');

    const boxes = await page.locator('#tag-name, #tag-color, #tag-store-picker, #tag-payment-picker, #tags-add-btn').evaluateAll((elements) =>
      elements.map((element) => {
        const { x, y, width, height, right, bottom } = element.getBoundingClientRect();
        return { x, y, width, height, right, bottom };
      }),
    );
    const [name, color, store, payment, add] = boxes;
    expect(name.right).toBeLessThanOrEqual(color.x + 1);
    expect(color.y).toBeGreaterThanOrEqual(name.bottom - 1);
    expect(store.y).toBeGreaterThanOrEqual(color.bottom - 1);
    expect(payment.y).toBeGreaterThanOrEqual(store.bottom - 1);
    expect(add.y).toBeGreaterThanOrEqual(payment.bottom - 1);
    expect(add.right).toBeLessThanOrEqual(390);
  });

  test('store and transaction pickers support searching', async ({ page }) => {
    await login(page);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/apps/spends/?lang=en&theme=light', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => {
      document.getElementById('settings-modal')!.style.display = 'flex';
      (window as any).switchSettingsSection('tags');
    });

    await page.locator('#tag-store-picker').click();
    await page.locator('#tag-picker-search').fill('Test Store');
    await expect(page.locator('#tag-picker-list .tag-picker-option')).toHaveCount(1);
    await page.locator('#tag-picker-list .tag-picker-option').click();
    await expect(page.locator('#tag-store-label')).toHaveText('Test Store');

    await page.locator('#tag-payment-picker').click();
    await page.locator('#tag-picker-search').fill('2026-10-26');
    await expect(page.locator('#tag-picker-list .tag-picker-option')).toHaveCount(1);
    await expect(page.locator('#tag-picker-list .tag-picker-option')).toContainText('Test Store');
  });

  test('goal editor opens in a separate modal', async ({ page }) => {
    await login(page);
    await page.goto('/apps/spends/?lang=en&theme=light', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => {
      document.getElementById('settings-modal')!.style.display = 'flex';
      (window as any).switchSettingsSection('goals');
    });
    await page.locator('#goals-add-btn').click();
    await expect(page.locator('#goal-editor-modal')).toBeVisible();
    await expect(page.locator('#goal-editor-modal #goal-editor')).toBeVisible();
    await expect(page.locator('#goals-add-btn svg')).toBeVisible();
  });
});
