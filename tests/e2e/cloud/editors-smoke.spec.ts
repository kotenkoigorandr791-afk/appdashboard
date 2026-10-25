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
  monacoValue,
  setMonacoValue,
  uniqueName,
  skipAuth,
  type CloudFrame,
} from '../helpers/cloud';

test.describe.configure({ mode: 'serial', timeout: 180000 });

const SAMPLE = {
  txt: 'plain editor smoke\n',
  js: 'const smoke = 1;\n',
  md: '# Smoke Markdown\n\nParagraph for preview.\n',
  html: '<!DOCTYPE html><html><body><h1>HTML smoke</h1></body></html>\n',
  tex: `\\documentclass{article}
\\begin{document}
Hello PDF smoke.
\\end{document}
`,
};

test.describe('Cloud editors smoke', () => {
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

  test('txt monaco save via shell toolbar', async () => {
    const path = uniqueName('e2e-ed-txt', 'txt');
    created.push(path);
    await ensureFile(frame, path, SAMPLE.txt);
    await openTreeFile(frame, path);
    await waitMonacoIn(frame);
    await setMonacoValue(frame, 'saved via e2e\n');
    await frame.locator('#btn-save').click();
    await expect.poll(async () => {
      const opened = await cloudApi(frame, 'open', { path, root: ROOT_KEY }, 'GET');
      return String(opened?.content ?? opened?.data ?? '');
    }).toContain('saved via e2e');
  });

  test('js monaco undo redo buttons exist and mutate model', async () => {
    const path = uniqueName('e2e-ed-js', 'js');
    created.push(path);
    await ensureFile(frame, path, SAMPLE.js);
    await openTreeFile(frame, path);
    await waitMonacoIn(frame);
    await frame.locator('body').evaluate(() => {
      const editor = (window as any).monaco.editor.getEditors()[0];
      editor.focus();
      editor.getModel().setValue('const a = 1;\n');
      editor.trigger('e2e', 'type', { text: 'const b = 2;\n' });
    });
    await expect(frame.locator('#btn-monaco-undo')).toBeVisible();
    await expect(frame.locator('#btn-monaco-redo')).toBeVisible();
    await frame.locator('body').evaluate(() => {
      const editor = (window as any).monaco.editor.getEditors()[0];
      editor.getModel().pushEditOperations([], [{ range: editor.getModel().getFullModelRange(), text: 'const a = 1;\n' }], () => null);
      editor.getModel().pushEditOperations([], [{ range: editor.getModel().getFullModelRange(), text: 'const a = 1;\nconst b = 2;\n' }], () => null);
    });
    const before = await monacoValue(frame);
    await frame.locator('#btn-monaco-undo').click();
    await pageWait(frame, 300);
    const afterUndo = await monacoValue(frame);
    expect(afterUndo.length).toBeLessThan(before.length);
    await frame.locator('#btn-monaco-redo').click();
    await pageWait(frame, 300);
    expect(await monacoValue(frame)).toBe(before);
  });

  test('markdown editor loads panes and preview', async () => {
    const path = uniqueName('e2e-ed-md', 'md');
    created.push(path);
    await ensureFile(frame, path, SAMPLE.md);
    await openTreeFile(frame, path);
    const ed = await editorFrame(frame);
    await waitMonacoIn(ed);
    await expect(ed.locator('#preview, .preview, #preview-pane, .markdown-body').first()).toBeVisible({
      timeout: 60000,
    });
    await expect(ed.locator('.rz-tools button').first()).toBeVisible({ timeout: 15000 });
    const mode = await ed.locator('body').evaluate(() => (window as any).cloudEditorPanes?.paneMode?.() || null);
    expect(['split', 'editor', 'preview', 'pdf', null]).toContain(mode);
    if (mode === 'split' || mode === null) {
      const expand = ed.locator('.rz-tools button').filter({ has: ed.locator('svg') }).first();
      if (await expand.isVisible().catch(() => false)) {
        await expand.click();
        await pageWait(frame, 300);
      }
    }
    expect(await monacoValue(ed)).toContain('Smoke Markdown');
  });

  test('html editor loads monaco and preview pane tools', async () => {
    const path = uniqueName('e2e-ed-html', 'html');
    created.push(path);
    await ensureFile(frame, path, SAMPLE.html);
    await openTreeFile(frame, path);
    const ed = await editorFrame(frame);
    await waitMonacoIn(ed);
    await expect(ed.locator('.rz-tools button').first()).toBeVisible({ timeout: 15000 });
    expect(await monacoValue(ed)).toContain('HTML smoke');
  });

  test('latex editor loads monaco compile button and panes', async () => {
    const path = uniqueName('e2e-ed-tex', 'tex');
    created.push(path);
    await ensureFile(frame, path, SAMPLE.tex);
    await openTreeFile(frame, path);
    const ed = await editorFrame(frame);
    await waitMonacoIn(ed);
    await expect(ed.locator('#compile-btn')).toBeVisible({ timeout: 60000 });
    await expect(ed.locator('.rz-tools button').first()).toBeVisible({ timeout: 15000 });
    expect(await monacoValue(ed)).toContain('Hello PDF smoke');
  });
});

async function pageWait(frame: CloudFrame, ms: number) {
  await frame.locator('body').evaluate((_el, wait) => new Promise(r => setTimeout(r, wait)), ms);
}
