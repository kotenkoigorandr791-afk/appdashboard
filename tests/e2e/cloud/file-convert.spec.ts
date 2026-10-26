import { test, expect } from '@playwright/test';
import {
  ROOT_KEY,
  openCloud,
  cloudApi,
  ensureFile,
  deletePaths,
  listHas,
  uniqueName,
  skipAuth,
  uploadPng,
  expectPdfMagic,
  type CloudFrame,
} from '../helpers/cloud';

test.describe.configure({ mode: 'serial', timeout: 240000 });

test.describe('Cloud file conversion', () => {
  test.use({ viewport: { width: 1500, height: 950 } });

  let frame: CloudFrame;
  const created: string[] = [];

  test.beforeEach(async ({ page }) => {
    skipAuth(test);
    test.skip((page.viewportSize()?.width || 0) < 769, 'desktop cloud');
    frame = await openCloud(page);
    created.length = 0;
  });

  test.afterEach(async () => {
    if (!frame) return;
    await deletePaths(frame, [...created].reverse()).catch(() => {});
  });

  test('png converts to jpg via convert_file', async () => {
    const src = uniqueName('e2e-cv-img', 'png');
    const dst = src.replace(/\.png$/i, '.jpg');
    created.push(dst, src);
    await uploadPng(frame, src);
    const res = await cloudApi(frame, 'convert_file', {
      source_path: src,
      target_format: 'jpg',
      root: ROOT_KEY,
    });
    expect(res?.ok, res?.error || JSON.stringify(res)).toBe(true);
    expect(res.output_path).toBe(dst);
    expect(await listHas(frame, '', dst)).toBe(true);
    expect(await listHas(frame, '', src)).toBe(true);
  });

  test('png converts to gif and can delete original', async () => {
    const src = uniqueName('e2e-cv-gif', 'png');
    const dst = src.replace(/\.png$/i, '.gif');
    created.push(dst, src);
    await uploadPng(frame, src, '#00aa44');
    const res = await cloudApi(frame, 'convert_file', {
      source_path: src,
      target_format: 'gif',
      delete_original: true,
      root: ROOT_KEY,
    });
    expect(res?.ok, res?.error || JSON.stringify(res)).toBe(true);
    expect(res.original_deleted).toBe(true);
    expect(await listHas(frame, '', dst)).toBe(true);
    expect(await listHas(frame, '', src)).toBe(false);
  });

  test('webp conversion fails cleanly when GD lacks webp', async () => {
    const src = uniqueName('e2e-cv-nowebp', 'png');
    created.push(src);
    await uploadPng(frame, src, '#112233');
    let err = '';
    try {
      await cloudApi(frame, 'convert_file', {
        source_path: src,
        target_format: 'webp',
        root: ROOT_KEY,
      });
    } catch (e: any) {
      err = String(e?.message || e);
    }
    expect(err).toMatch(/WebP|webp|not supported|Failed|Conversion/i);
  });

  test('markdown converts to html via pandoc', async () => {
    const src = uniqueName('e2e-cv-md', 'md');
    const dst = src.replace(/\.md$/i, '.html');
    created.push(dst, src);
    await ensureFile(frame, src, '# Convert Me\n\nParagraph body.\n');
    const res = await cloudApi(frame, 'convert_file', {
      source_path: src,
      target_format: 'html',
      root: ROOT_KEY,
    });
    expect(res?.ok, res?.error || JSON.stringify(res)).toBe(true);
    expect(await listHas(frame, '', dst)).toBe(true);
    const opened = await cloudApi(frame, 'open', { path: dst, root: ROOT_KEY }, 'GET');
    expect(String(opened?.content || '')).toMatch(/Convert Me|h1|p>/i);
  });

  test('txt converts to pdf via LibreOffice', async () => {
    const src = uniqueName('e2e-cv-txt', 'txt');
    const dst = src.replace(/\.txt$/i, '.pdf');
    created.push(dst, src);
    await ensureFile(frame, src, 'LibreOffice conversion smoke text.\n');
    const res = await cloudApi(frame, 'convert_file', {
      source_path: src,
      target_format: 'pdf',
      root: ROOT_KEY,
    });
    expect(res?.ok, res?.error || JSON.stringify(res)).toBe(true);
    expect(await listHas(frame, '', dst)).toBe(true);
    const raw = await frame.locator('body').evaluate(async (_el, args) => {
      const resp = await fetch(
        `/apps/cloud/api.php?action=download&path=${encodeURIComponent(args.pdf)}&root=${args.root}`,
        { credentials: 'same-origin' }
      );
      const buf = await resp.arrayBuffer();
      const u8 = new Uint8Array(buf).slice(0, 5);
      let s = '';
      u8.forEach(b => { s += String.fromCharCode(b); });
      return btoa(s);
    }, { pdf: dst, root: ROOT_KEY });
    await expectPdfMagic(raw);
  });

  test('unsupported conversion returns error', async () => {
    const src = uniqueName('e2e-cv-bad', 'txt');
    created.push(src);
    await ensureFile(frame, src, 'nope\n');
    let err = '';
    try {
      await cloudApi(frame, 'convert_file', {
        source_path: src,
        target_format: 'mp3',
        root: ROOT_KEY,
      });
    } catch (e: any) {
      err = String(e?.message || e);
    }
    expect(err).toMatch(/not supported|invalid|Conversion|failed|mp3/i);
  });

  test('convert dialog converts png to jpg in UI', async () => {
    const src = uniqueName('e2e-cv-ui', 'png');
    const dst = src.replace(/\.png$/i, '.jpg');
    created.push(dst, src);
    await uploadPng(frame, src, '#3366cc');
    await frame.locator('body').evaluate((_el, root) => (window as any).switchCloudRoot(root), ROOT_KEY);
    await frame.locator('body').evaluate((_el, p) => (window as any).expandFolderToPath?.(p), src);
    const node = frame.locator(`#tree .node[data-path="${src}"]`).first();
    await expect.poll(async () => node.count(), { timeout: 45000 }).toBeGreaterThan(0);
    await node.click({ button: 'right' });
    await frame.locator('.context-menu-item').filter({ hasText: /^Actions$/ }).first().hover();
    await frame.locator('.context-menu-submenu .context-menu-item').filter({ hasText: /^Convert$/i }).first().click();
    await frame.locator('#target-format-select').waitFor({ timeout: 15000 });
    await frame.locator('#target-format-select').selectOption('jpg');
    await frame.locator('#btn-confirm-convert').click();
    await expect.poll(async () => listHas(frame, '', dst), { timeout: 90000 }).toBe(true);
  });
});
