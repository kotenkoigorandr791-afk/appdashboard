import { test, expect, type Page } from '@playwright/test';
import { hasAuthUser, login, gotoShell } from '../helpers/shell';

const ROOT_KEY = 'temp';

type CloudFrame = ReturnType<Page['frameLocator']>;

// Sequential (one worker because of the shared login) with a budget that covers
// signing in and opening the editor; a failure must not skip the remaining tests.
test.describe.configure({ timeout: 120000 });

test.describe('Monaco editor toolbar and completions', () => {
  test.beforeEach(async ({ page }) => {
    test.skip(!hasAuthUser(), 'Needs E2E_USER_EMAIL/PASSWORD');
    test.skip((page.viewportSize()?.width || 0) < 769, 'desktop toolbar');
  });

  async function openFile(page: Page, extension: string, content: string) {
    const fileName = `e2e-toolbar-${Date.now()}-${Math.floor(Math.random() * 1e6)}.${extension}`;
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
    await page.waitForTimeout(1500);
    return frame;
  }

  function codeActionsHidden(frame: CloudFrame) {
    return frame.locator('body').evaluate(() => {
      const button = document.getElementById('btn-code-actions');
      if (!button) return null;
      return { hidden: button.classList.contains('hidden'), visible: button.offsetParent !== null };
    });
  }

  function editorValue(frame: CloudFrame) {
    return frame.locator('body').evaluate(
      () => (window as any).monaco.editor.getModels()[0].getValue().replace(/\r\n/g, '\n')
    );
  }

  test('the code actions button is hidden for text files', async ({ page }) => {
    const frame = await openFile(page, 'txt', 'plain text file\n');

    await expect.poll(async () => (await codeActionsHidden(frame))?.hidden).toBe(true);
    const txtState = await codeActionsHidden(frame);
    console.log('txt code-actions state:', JSON.stringify(txtState));
    expect(txtState).toEqual({ hidden: true, visible: false });

    // The save button of an editable file stays available.
    const saveVisible = await frame.locator('body').evaluate(
      () => !document.getElementById('btn-save')?.classList.contains('hidden')
    );
    expect(saveVisible).toBe(true);
  });

  test('the code actions button stays for code files', async ({ page }) => {
    const frame = await openFile(page, 'js', 'const value = 1;\n');

    await expect.poll(async () => (await codeActionsHidden(frame))?.hidden).toBe(false);
    const jsState = await codeActionsHidden(frame);
    console.log('js code-actions state:', JSON.stringify(jsState));
    expect(jsState).toEqual({ hidden: false, visible: true });
  });

  test('accepting a completion at the end of the line adds a space', async ({ page }) => {
    const frame = await openFile(page, 'js', '');

    await frame.locator('body').evaluate(() => {
      const editor = (window as any).monaco.editor.getEditors()[0];
      editor.focus();
      editor.setPosition({ lineNumber: 1, column: 1 });
      editor.trigger('e2e', 'editor.action.triggerSuggest', {});
    });
    await page.waitForTimeout(600);

    // Accept the highlighted entry — the list is built by Monaco's own providers,
    // so the test asserts the shape of the result instead of a fixed word.
    const row = frame.locator('.suggest-widget .monaco-list-row').first();
    await row.waitFor({ timeout: 15000 });
    await page.keyboard.press('Enter');
    await page.waitForTimeout(400);

    const value = await editorValue(frame);
    console.log('after completion:', JSON.stringify(value));
    expect(value, 'a word accepted at the end of the line is followed by a space').toMatch(/^[A-Za-z][A-Za-z0-9_]* $/);
  });
});
