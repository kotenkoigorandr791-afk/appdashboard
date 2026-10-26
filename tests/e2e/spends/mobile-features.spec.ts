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
    await page.evaluate(() => (window as any).saveTagFromForm());
    await expect(page.locator('#tags-list .tag-chip', { hasText: name })).toBeVisible();
    page.once('dialog', (d) => d.accept());
    await page.locator('#tags-list .tag-row', { hasText: name }).locator('.tag-delete').click();
    await expect(page.locator('#tags-list .tag-chip', { hasText: name })).toHaveCount(0);
  });
});
