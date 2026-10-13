import { test, expect, type Page } from '@playwright/test';
import { hasAuthUser, login, gotoShell } from './helpers/shell';

const ROOT_KEY = 'temp';

type CloudFrame = ReturnType<Page['frameLocator']>;

// Sequential (one worker because of the shared login) with a budget that covers
// signing in and opening the editor; a failure must not skip the remaining tests.
test.describe.configure({ timeout: 120000 });

test.describe('Monaco .txt editing', () => {
  test.beforeEach(async ({ page }) => {
    test.skip(!hasAuthUser(), 'Needs E2E_USER_EMAIL/PASSWORD');
    test.skip((page.viewportSize()?.width || 0) < 769, 'the editor under test is the desktop one');
  });

  async function openTxtEditor(page: Page, content: string): Promise<CloudFrame> {
    const fileName = `e2e-txtedit-${Date.now()}-${Math.floor(Math.random() * 1e6)}.txt`;
    await login(page);
    await gotoShell(page, '/main?app=cloud');
    await page.waitForSelector('#app-iframe', { timeout: 60000 });
    const frame = page.frameLocator('#app-iframe');
    await frame.locator('#tree .node').first().waitFor({ timeout: 60000 });

    await frame.locator('body').evaluate(async (_el, { name, root, text }) => {
      const api = (window as any).api;
      await api('create_file', { path: name, root });
      await api('save_file', { path: name, root, content: text });
      await api('invalidate_file_tree', {}, 'POST');
    }, { name: fileName, root: ROOT_KEY, text: content });

    await frame.locator('body').evaluate((_el, root) => (window as any).switchCloudRoot(root), ROOT_KEY);
    const node = frame.locator(`#tree .node[data-path="${fileName}"]`).first();
    await node.waitFor({ timeout: 60000 });
    await node.click();
    await frame.locator('.monaco-editor textarea').first().waitFor({ state: 'attached', timeout: 60000 });
    await page.waitForTimeout(1200);
    await frame.locator('body').evaluate(() => {
      const editor = (window as any).monaco.editor.getEditors()[0];
      editor.focus();
      editor.setPosition({ lineNumber: 1, column: 1 });
    });
    return frame;
  }

  /** The editor opens these files with CRLF line endings. */
  function editorValue(frame: CloudFrame) {
    return frame.locator('body').evaluate(
      () => (window as any).monaco.editor.getModels()[0].getValue().replace(/\r\n/g, '\n')
    );
  }

  function cursorPosition(frame: CloudFrame) {
    return frame.locator('body').evaluate(() => {
      const position = (window as any).monaco.editor.getEditors()[0].getPosition();
      return { lineNumber: position.lineNumber, column: position.column };
    });
  }

  function setValueAndCursor(frame: CloudFrame, value: string, lineNumber: number, column: number) {
    return frame.locator('body').evaluate((_el, payload) => {
      const editor = (window as any).monaco.editor.getEditors()[0];
      editor.getModel().setValue(payload.value);
      editor.setPosition({ lineNumber: payload.lineNumber, column: payload.column });
      editor.focus();
    }, { value, lineNumber, column });
  }

  test('Enter continues the list and Backspace removes the auto number', async ({ page }) => {
    const frame = await openTxtEditor(page, '');

    await page.keyboard.type('1. Item one');
    await page.keyboard.press('Enter');
    await page.waitForTimeout(200);

    // The next number was inserted automatically.
    expect(await editorValue(frame)).toBe('1. Item one\n2. ');
    expect(await cursorPosition(frame)).toEqual({ lineNumber: 2, column: 4 });

    // One Backspace removes the whole auto inserted marker (like Word) and leaves an
    // empty line — instead of peeling off the space, then the dot, then the digit.
    await page.keyboard.press('Backspace');
    await page.waitForTimeout(200);
    expect(await editorValue(frame)).toBe('1. Item one\n');
    expect(await cursorPosition(frame)).toEqual({ lineNumber: 2, column: 1 });

    // With text after the marker, Backspace drops only the number and keeps the text.
    await setValueAndCursor(frame, '1. Item one', 1, 12);
    await page.keyboard.press('Enter');
    await page.keyboard.type('Item two');
    await page.waitForTimeout(200);
    expect(await editorValue(frame)).toBe('1. Item one\n2. Item two');

    await setValueAndCursor(frame, '1. Item one\n2. Item two', 2, 4);
    await page.waitForTimeout(100);
    await page.keyboard.press('Backspace');
    await page.waitForTimeout(200);
    expect(await editorValue(frame)).toBe('1. Item one\nItem two');
    expect(await cursorPosition(frame)).toEqual({ lineNumber: 2, column: 1 });
  });

  test('the indentation and the delimiter are preserved', async ({ page }) => {
    const frame = await openTxtEditor(page, '');

    await setValueAndCursor(frame, '  3) alpha', 1, 11);
    await page.keyboard.press('Enter');
    await page.waitForTimeout(200);
    expect(await editorValue(frame)).toBe('  3) alpha\n  4) ');
    expect(await cursorPosition(frame)).toEqual({ lineNumber: 2, column: 6 });

    await page.keyboard.press('Backspace');
    await page.waitForTimeout(200);
    expect(await editorValue(frame)).toBe('  3) alpha\n  ');
    expect(await cursorPosition(frame)).toEqual({ lineNumber: 2, column: 3 });

    // The list can still be ended with Enter on the empty item.
    await setValueAndCursor(frame, '1. only item\n2. ', 2, 4);
    await page.keyboard.press('Enter');
    await page.waitForTimeout(200);
    expect(await editorValue(frame)).toBe('1. only item\n');
  });

  test('a hand typed number is not swallowed by Backspace', async ({ page }) => {
    const frame = await openTxtEditor(page, '');

    // No list continuation ran here: the "9. " marker came from the file, so
    // Backspace keeps its normal one-character behaviour.
    await setValueAndCursor(frame, 'hello\n9. manual', 2, 4);
    await page.waitForTimeout(150);
    await page.keyboard.press('Backspace');
    await page.waitForTimeout(200);

    expect(await editorValue(frame)).toBe('hello\n9.manual');
  });

  /** Waits until the spellchecker flags the first word of the fixture. */
  async function waitForSpellcheckMarker(frame: CloudFrame, lineNumber = 1, startColumn = 1) {
    await expect
      .poll(async () => frame.locator('body').evaluate(
        () => (window as any).monaco.editor.getModelMarkers({ owner: 'spellcheck' }).length
      ), { timeout: 30000, intervals: [500, 1000, 2000] })
      .toBeGreaterThan(0);
    const markers = await frame.locator('body').evaluate(() => {
      const list = (window as any).monaco.editor.getModelMarkers({ owner: 'spellcheck' });
      return list.map((marker: any) => [marker.startLineNumber, marker.startColumn, marker.endColumn]);
    });
    expect(markers).toContainEqual([lineNumber, startColumn, startColumn + 4]);
    return markers;
  }

  test('a suggestion at the end of the text gets a trailing space, punctuation sticks', async ({ page }) => {
    const frame = await openTxtEditor(page, 'helo');

    await frame.locator('body').evaluate(() => {
      const editor = (window as any).monaco.editor.getEditors()[0];
      editor.setPosition({ lineNumber: 1, column: 5 });
      editor.focus();
    });
    await waitForSpellcheckMarker(frame);

    await frame.locator('body').evaluate(() => {
      const editor = (window as any).monaco.editor.getEditors()[0];
      editor.setPosition({ lineNumber: 1, column: 3 });
      const action = editor.getAction('editor.action.quickFix');
      if (action) action.run();
    });
    const actionRow = frame.locator('.action-widget .monaco-list-row').first();
    await actionRow.waitFor({ timeout: 15000 });
    await page.keyboard.press('Enter');
    await page.waitForTimeout(400);

    const afterFix = await editorValue(frame);
    console.log('end-of-text fix:', JSON.stringify({ value: afterFix, position: await cursorPosition(frame) }));
    expect(afterFix, 'the word at the end of the text is followed by a space').toBe('hello ');
    expect(await cursorPosition(frame)).toEqual({ lineNumber: 1, column: 7 });

    // A punctuation mark glues itself to the word: the added space goes away.
    await page.keyboard.press('Period');
    await page.waitForTimeout(300);
    const afterPunctuation = await editorValue(frame);
    console.log('after punctuation:', JSON.stringify({ value: afterPunctuation, position: await cursorPosition(frame) }));
    expect(afterPunctuation).toBe('hello.');
    expect(await cursorPosition(frame)).toEqual({ lineNumber: 1, column: 7 });
  });

  test('the trailing space stays for a plain next word', async ({ page }) => {
    const frame = await openTxtEditor(page, 'helo');

    await frame.locator('body').evaluate(() => {
      const editor = (window as any).monaco.editor.getEditors()[0];
      editor.setPosition({ lineNumber: 1, column: 3 });
      editor.focus();
    });
    await waitForSpellcheckMarker(frame);
    await frame.locator('body').evaluate(() => {
      const editor = (window as any).monaco.editor.getEditors()[0];
      const action = editor.getAction('editor.action.quickFix');
      if (action) action.run();
    });
    await frame.locator('.action-widget .monaco-list-row').first().waitFor({ timeout: 15000 });
    await page.keyboard.press('Enter');
    await page.waitForTimeout(400);
    expect(await editorValue(frame)).toBe('hello ');

    await page.keyboard.type('world');
    await page.waitForTimeout(300);
    expect(await editorValue(frame)).toBe('hello world');
  });

  test('no trailing space when text follows on a later line', async ({ page }) => {
    const frame = await openTxtEditor(page, 'helo\nsecond line');

    await frame.locator('body').evaluate(() => {
      const editor = (window as any).monaco.editor.getEditors()[0];
      editor.setPosition({ lineNumber: 1, column: 3 });
      editor.focus();
    });
    await waitForSpellcheckMarker(frame);

    await frame.locator('body').evaluate(() => {
      const editor = (window as any).monaco.editor.getEditors()[0];
      const action = editor.getAction('editor.action.quickFix');
      if (action) action.run();
    });
    await frame.locator('.action-widget .monaco-list-row').first().waitFor({ timeout: 15000 });
    await page.keyboard.press('Enter');
    await page.waitForTimeout(400);

    // The word is corrected, but text follows on the next line, so the word is
    // in the middle of the text and gets no automatic space.
    expect(await editorValue(frame)).toBe('hello\nsecond line');
  });

  test('choosing a spelling suggestion keeps the trailing space', async ({ page }) => {
    const frame = await openTxtEditor(page, 'helo world');

    await frame.locator('body').evaluate(() => {
      const editor = (window as any).monaco.editor.getEditors()[0];
      editor.setPosition({ lineNumber: 1, column: 3 });
      editor.focus();
    });

    // Wait for the spellchecker to finish loading its dictionary and flag the word.
    await expect
      .poll(async () => frame.locator('body').evaluate(
        () => (window as any).monaco.editor.getModelMarkers({ owner: 'spellcheck' }).length
      ), { timeout: 30000, intervals: [500, 1000, 2000] })
      .toBeGreaterThan(0);

    const markers = await frame.locator('body').evaluate(() => {
      const list = (window as any).monaco.editor.getModelMarkers({ owner: 'spellcheck' });
      return list.map((marker: any) => [marker.startLineNumber, marker.startColumn, marker.endColumn]);
    });
    console.log('spellcheck markers:', JSON.stringify(markers));
    expect(markers[0]).toEqual([1, 1, 5]);

    // Real user flow: open the quick fix list and accept the first suggestion.
    await frame.locator('body').evaluate(() => {
      const editor = (window as any).monaco.editor.getEditors()[0];
      editor.setPosition({ lineNumber: 1, column: 3 });
      const action = editor.getAction('editor.action.quickFix');
      if (action) action.run();
    });
    const actionRow = frame.locator('.monaco-editor .action-widget .monaco-list-row, .action-widget .monaco-list-row').first();
    await actionRow.waitFor({ timeout: 15000 });
    const suggestion = (await actionRow.textContent())?.trim() || '';
    console.log('first suggestion:', suggestion);
    await page.keyboard.press('Enter');
    await page.waitForTimeout(400);

    const value = await editorValue(frame);
    const position = await cursorPosition(frame);
    console.log('after quick fix:', JSON.stringify({ value, position }));

    expect(value, 'the corrected word must keep the space that follows it').toBe('hello world');
    expect(position.lineNumber).toBe(1);

    // Typing right after the correction must not eat the space either.
    await page.keyboard.type('x');
    await page.waitForTimeout(300);
    const after = await editorValue(frame);
    console.log('after typing:', JSON.stringify(after));
    expect(after, 'the space must survive typing after the correction').toContain(' world');
    expect(after.replace('x', '')).toBe('hello world');
  });
});
