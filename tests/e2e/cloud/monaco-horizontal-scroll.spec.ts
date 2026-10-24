import { test, expect, type Page } from '@playwright/test';
import { hasAuthUser, login, gotoShell } from '../helpers/shell';

const ROOT_KEY = 'temp';

type CloudFrame = ReturnType<Page['frameLocator']>;

// Sequential (one worker because of the shared login) with a budget that covers
// signing in and opening the editor; a failure must not skip the remaining tests.
test.describe.configure({ timeout: 120000 });

/**
 * Lines are wrapped (wordWrap: 'on'), so the text area must never scroll
 * horizontally — not by a pixel — while typing. Wrapped lines used to render a
 * few pixels wider than the width Monaco wrapped for (the editor font was
 * measured before the JetBrains Mono webfont arrived), which gave the editor a
 * phantom horizontal scroll range and made the text jump left and right
 * whenever a word did not fit.
 */
test.describe('Monaco editor horizontal stability', () => {
  async function openEditorWithFixture(page: Page): Promise<CloudFrame> {
    const fileName = `e2e-hscroll-${Date.now()}-${Math.floor(Math.random() * 1e6)}.txt`;
    await login(page);
    await gotoShell(page, '/main?app=cloud');
    await page.waitForSelector('#app-iframe', { timeout: 60000 });
    const frame = page.frameLocator('#app-iframe');
    await frame.locator('#tree .node').first().waitFor({ timeout: 60000 });

    await frame.locator('body').evaluate(async (_el, { name, root }) => {
      const api = (window as any).api;
      await api('create_file', { path: name, root });
      await api('save_file', { path: name, root, content: '' });
      await api('invalidate_file_tree', {}, 'POST');
    }, { name: fileName, root: ROOT_KEY });

    await frame.locator('body').evaluate((_el, root) => (window as any).switchCloudRoot(root), ROOT_KEY);
    const node = frame.locator(`#tree .node[data-path="${fileName}"]`).first();
    await node.waitFor({ timeout: 60000 });
    await node.click();
    await frame.locator('.monaco-editor textarea').first().waitFor({ state: 'attached', timeout: 60000 });
    await page.waitForTimeout(1500);
    return frame;
  }

  async function focusEditor(page: Page, frame: CloudFrame) {
    await frame.locator('body').evaluate(() => {
      const editor = (window as any).monaco.editor.getEditors()[0];
      editor.focus();
      editor.setPosition({ lineNumber: 1, column: 1 });
    });
    await page.waitForTimeout(200);
  }

  /** Samples on every animation frame so transient scroll offsets are not missed. */
  async function startScrollSampler(frame: CloudFrame) {
    await frame.locator('body').evaluate(() => {
      const w = window as any;
      const editor = w.monaco.editor.getEditors()[0];
      const stats = { maxScrollLeft: 0, maxDomScrollLeft: 0, maxLinesOffset: 0, frames: 0 };
      w.__hscroll = stats;
      const sample = () => {
        const scrollLeft = Math.abs(editor.getScrollLeft());
        const scrollable = editor.getDomNode()?.querySelector('.monaco-scrollable-element') as HTMLElement | null;
        const domScrollLeft = scrollable ? Math.abs(scrollable.scrollLeft) : 0;
        const lines = editor.getDomNode()?.querySelector('.lines-content') as HTMLElement | null;
        const linesOffset = lines ? Math.abs(parseFloat(getComputedStyle(lines).left) || 0) : 0;
        stats.maxScrollLeft = Math.max(stats.maxScrollLeft, scrollLeft);
        stats.maxDomScrollLeft = Math.max(stats.maxDomScrollLeft, domScrollLeft);
        stats.maxLinesOffset = Math.max(stats.maxLinesOffset, linesOffset);
        stats.frames++;
        w.__hscrollRaf = requestAnimationFrame(sample);
      };
      sample();
    });
  }

  async function readScrollStats(frame: CloudFrame) {
    return frame.locator('body').evaluate(() => {
      const w = window as any;
      cancelAnimationFrame(w.__hscrollRaf);
      const editor = w.monaco.editor.getEditors()[0];
      const layout = editor.getLayoutInfo();
      const fontInfo = editor.getOption(w.monaco.editor.EditorOption.fontInfo);
      return {
        ...w.__hscroll,
        scrollWidth: editor.getScrollWidth(),
        contentWidth: layout.contentWidth,
        characterWidth: fontInfo.typicalHalfwidthCharacterWidth,
        horizontalRange: editor.getScrollWidth() - layout.contentWidth,
      };
    });
  }

  async function editorValue(frame: CloudFrame) {
    return frame.locator('body').evaluate(() => {
      const models = (window as any).monaco?.editor.getModels() || [];
      return models[0]?.getValue() || '';
    });
  }

  test('typing wrapped words never shifts the text horizontally', async ({ page }) => {
    test.skip(!hasAuthUser(), 'Needs E2E_USER_EMAIL/PASSWORD');
    const frame = await openEditorWithFixture(page);
    await focusEditor(page, frame);
    await startScrollSampler(frame);

    // Words that only just fit on a wrapped line, so many wrap points sit at the
    // right edge of the content area, plus a URL-like token and a very long word
    // that has no break opportunity at all.
    const paragraph =
      'supercalifragilistic configuration dashboardeditor horizontalstability wrappingbehaviour ' +
      'paragraph testingmonaco scrolloffset alphabravo charlie deltacho echo foxtrot golf hotel ' +
      'india juliett kilo lima mike november oscar papa quebec romeo sierra tango uniform victor ' +
      'whiskey xray yankee zulu integrity monitor keyboard selection translation composition ' +
      'https://example.com/a/very/long/path/that/never/ends/anywhere/at/all/ok ' +
      'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz ' +
      'trailing words after the long token to keep wrapping at the cursor ';
    for (const chunk of paragraph.match(/.{1,10}/g) || []) {
      await page.keyboard.type(chunk);
      await page.waitForTimeout(25);
    }
    await page.waitForTimeout(500);

    const stats = await readScrollStats(frame);
    console.log('horizontal scroll diagnostics:', JSON.stringify(stats));

    // The app auto-capitalizes after a period, so compare the interesting parts.
    const value = await editorValue(frame);
    expect(value).toContain('abcdefghijklmnopqrstuvwxyz'.repeat(3));
    expect(value).toContain('trailing words after the long token to keep wrapping at the cursor');
    // Without the font wait this used to be several characters wide (the wrap used
    // stale metrics), which is what allowed the drift in the first place.
    expect(stats.horizontalRange, 'editor must not reserve more than one character of scrollable width')
      .toBeLessThanOrEqual(stats.characterWidth);
    expect(stats.maxScrollLeft, 'editor.getScrollLeft() must stay at 0 while typing').toBe(0);
    expect(stats.maxDomScrollLeft, 'scrollable element must not scroll horizontally').toBe(0);
    expect(stats.maxLinesOffset, 'rendered lines must not be offset horizontally').toBe(0);
  });

  test('the text area stays put while the window is resized', async ({ page }) => {
    test.skip(!hasAuthUser(), 'Needs E2E_USER_EMAIL/PASSWORD');
    const frame = await openEditorWithFixture(page);
    await focusEditor(page, frame);

    const paragraph = 'resize wrapping stability check '.repeat(120);
    await frame.locator('body').evaluate((_el, value) => {
      (window as any).monaco.editor.getModels()[0].setValue(value);
    }, paragraph);
    await page.waitForTimeout(400);
    await startScrollSampler(frame);

    for (const width of [1920, 1600, 1200, 900, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      await page.waitForTimeout(400);
    }

    const stats = await readScrollStats(frame);
    console.log('resize diagnostics:', JSON.stringify(stats));
    expect(stats.horizontalRange, 'resizing must not reserve more than one character of scrollable width')
      .toBeLessThanOrEqual(stats.characterWidth);
    expect(stats.maxScrollLeft, 'resizing must not introduce a horizontal offset').toBe(0);
    expect(stats.maxLinesOffset, 'rendered lines must not be offset horizontally').toBe(0);
  });
});
