import { test, expect } from '@playwright/test';
import {
  ROOT_KEY,
  openCloud,
  cloudApi,
  ensureFile,
  deletePaths,
  openTreeFile,
  editorFrame,
  waitMonacoIn,
  listHas,
  uniqueName,
  skipAuth,
  expectPdfMagic,
  type CloudFrame,
} from '../helpers/cloud';

test.describe.configure({ mode: 'serial', timeout: 300000 });

const TEX = `\\documentclass{article}
\\begin{document}
E2E LaTeX compile works.
\\end{document}
`;

const MD = `# E2E Markdown PDF

Body for pandoc export.
`;

test.describe('PDF compile and export', () => {
  test.use({ viewport: { width: 1500, height: 950 } });

  let frame: CloudFrame;
  const created: string[] = [];

  test.beforeEach(async ({ page }) => {
    skipAuth(test);
    test.skip((page.viewportSize()?.width || 0) < 769, 'desktop editors');
    frame = await openCloud(page);
    created.length = 0;
  });

  test.afterEach(async () => {
    if (!frame) return;
    await deletePaths(frame, [...created].reverse()).catch(() => {});
  });

  test('latex compile returns PDF blob and renders preview', async () => {
    const path = uniqueName('e2e-pdf-tex', 'tex');
    created.push(path);
    await ensureFile(frame, path, TEX);
    await openTreeFile(frame, path);
    const ed = await editorFrame(frame);
    await waitMonacoIn(ed);
    await expect(ed.locator('#compile-btn')).toBeVisible({ timeout: 60000 });
    const result = await ed.locator('body').evaluate(async () => {
      const compile = (window as any).cloudIframeMonacoAiCompile;
      if (typeof compile !== 'function') return { ok: false, error: 'no compile fn' };
      const res = await compile(false);
      const canvas = document.querySelector('#pdf-canvas, canvas.pdf-page, #pdfViewer canvas, .pdfViewer canvas');
      const status = document.getElementById('status')?.className || document.querySelector('.status')?.className || '';
      return {
        ok: !!(res && res.success),
        error: res?.error || null,
        hasCanvas: !!canvas,
        status,
      };
    });
    expect(result.ok, result.error || 'compile failed').toBe(true);
    await expect
      .poll(async () => {
        return ed.locator('body').evaluate(() => {
          const c = document.querySelector(
            '#pdf-canvas, canvas.pdf-page, #pdfViewer canvas, .pdfViewer canvas, #pdf-pages canvas'
          );
          return !!(c && (c as HTMLCanvasElement).width > 0);
        });
      }, { timeout: 60000 })
      .toBe(true);
  });

  test('latex save_pdf writes sibling pdf file', async () => {
    const base = uniqueName('e2e-pdf-save');
    const path = `${base}.tex`;
    const pdfName = `${base}.pdf`;
    created.push(pdfName, path);
    await ensureFile(frame, path, TEX);
    await openTreeFile(frame, path);
    const ed = await editorFrame(frame);
    await waitMonacoIn(ed);
    const result = await ed.locator('body').evaluate(async () => {
      const compile = (window as any).cloudIframeMonacoAiCompile;
      return compile(true);
    });
    expect(result?.success, result?.error || 'save_pdf failed').toBe(true);
    await expect.poll(async () => listHas(frame, '', pdfName), { timeout: 60000 }).toBe(true);
  });

  test('markdown export_pdf creates pdf via API', async () => {
    const base = uniqueName('e2e-md-pdf');
    const path = `${base}.md`;
    const pdfName = `${base}.pdf`;
    created.push(pdfName, path);
    await ensureFile(frame, path, MD);
    const res = await cloudApi(frame, 'export_pdf', {
      path,
      root: ROOT_KEY,
      font: 'Liberation Serif',
      paper: 'a4',
    });
    expect(res?.success ?? res?.ok, res?.error || JSON.stringify(res)).toBeTruthy();
    await expect.poll(async () => listHas(frame, '', pdfName), { timeout: 90000 }).toBe(true);
    const raw = await frame.locator('body').evaluate(async (_el, args) => {
      const api = (window as any).api;
      const data = await api('raw', { path: args.pdf, root: args.root }, 'GET');
      if (typeof data === 'string') {
        const bytes = new TextEncoder().encode(data.slice(0, 8));
        let bin = '';
        bytes.forEach(b => { bin += String.fromCharCode(b); });
        return btoa(bin);
      }
      if (data?.content) {
        return btoa(String(data.content).slice(0, 8));
      }
      const resp = await fetch(`/apps/cloud/api.php?action=download&path=${encodeURIComponent(args.pdf)}&root=${args.root}`, {
        credentials: 'same-origin',
      });
      const buf = await resp.arrayBuffer();
      const u8 = new Uint8Array(buf).slice(0, 5);
      let s = '';
      u8.forEach(b => { s += String.fromCharCode(b); });
      return btoa(s);
    }, { pdf: pdfName, root: ROOT_KEY });
    await expectPdfMagic(raw);
  });

  test('markdown editor Export PDF UI completes', async () => {
    const base = uniqueName('e2e-md-ui-pdf');
    const path = `${base}.md`;
    const pdfName = `${base}.pdf`;
    created.push(pdfName, path);
    await ensureFile(frame, path, MD);
    await openTreeFile(frame, path);
    const ed = await editorFrame(frame);
    await waitMonacoIn(ed);
    await ed.locator('#exportPdfBtn').click();
    await expect(ed.locator('#pdfModal')).toBeVisible();
    await ed.locator('#generatePdfBtn').click();
    await expect
      .poll(async () => {
        const status = await ed.locator('#pdfStatus').textContent().catch(() => '');
        const listed = await listHas(frame, '', pdfName);
        return listed || /success|generated|успеш|успіш/i.test(status || '');
      }, { timeout: 120000 })
      .toBe(true);
  });
});
