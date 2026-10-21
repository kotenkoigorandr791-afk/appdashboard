import { test, expect, type Page } from '@playwright/test';
import { hasAuthUser, login, gotoShell } from './helpers/shell';

const ROOT_KEY = 'temp';

type CloudFrame = ReturnType<Page['frameLocator']>;

// Sequential (one worker because of the shared login) with a budget that covers
// signing in and opening the editor; a failure must not skip the remaining tests.
test.describe.configure({ timeout: 120000 });

test.describe('Monaco Markdown numbered list', () => {
  test.beforeEach(async ({ page }) => {
    test.skip(!hasAuthUser(), 'Needs E2E_USER_EMAIL/PASSWORD');
    test.skip((page.viewportSize()?.width || 0) < 769, 'the editor under test is the desktop one');
  });

  async function openMarkdownEditor(page: Page): Promise<CloudFrame> {
    const fileName = `e2e-mdlist-${Date.now()}-${Math.floor(Math.random() * 1e6)}.md`;
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

    // .md files open in the markdown editor iframe inside the cloud app iframe.
    const editorFrame = frame.frameLocator('iframe[data-tab-id]').last();
    await editorFrame.locator('.monaco-editor textarea').first().waitFor({ state: 'attached', timeout: 60000 });
    await page.waitForTimeout(2000);
    await editorFrame.locator('body').evaluate(() => {
      const editor = (window as any).monaco.editor.getEditors()[0];
      editor.focus();
      editor.setPosition({ lineNumber: 1, column: 1 });
    });
    return editorFrame;
  }

  function editorState(frame: CloudFrame) {
    return frame.locator('body').evaluate(() => {
      const editor = (window as any).monaco.editor.getEditors()[0];
      return {
        value: String(editor.getModel().getValue()).replace(/\r\n/g, '\n'),
        lineNumber: editor.getPosition().lineNumber,
        column: editor.getPosition().column,
      };
    });
  }

  test('Enter continues the list and Backspace removes the auto number', async ({ page }) => {
    const frame = await openMarkdownEditor(page);

    await page.keyboard.type('1. Item one');
    await page.keyboard.press('Enter');
    await page.waitForTimeout(300);

    expect(await editorState(frame)).toEqual({
      value: '1. Item one\n2. ',
      lineNumber: 2,
      column: 4,
    });

    // The list keeps counting from the auto inserted marker.
    await page.keyboard.type('Item two');
    await page.keyboard.press('Enter');
    await page.waitForTimeout(300);
    expect(await editorState(frame)).toEqual({
      value: '1. Item one\n2. Item two\n3. ',
      lineNumber: 3,
      column: 4,
    });

    // One Backspace drops the whole auto inserted marker (like Word).
    await page.keyboard.press('Backspace');
    await page.waitForTimeout(300);
    expect(await editorState(frame)).toEqual({
      value: '1. Item one\n2. Item two\n',
      lineNumber: 3,
      column: 1,
    });
  });

  test('a numbered line inside a fenced code block stays literal', async ({ page }) => {
    const frame = await openMarkdownEditor(page);

    await frame.locator('body').evaluate(() => {
      const editor = (window as any).monaco.editor.getEditors()[0];
      editor.getModel().setValue('```\n1. code');
      editor.setPosition({ lineNumber: 2, column: 8 });
      editor.focus();
    });
    await page.keyboard.press('Enter');
    await page.waitForTimeout(300);

    // Code, not a list: Enter adds a plain newline instead of "2. ".
    expect(await editorState(frame)).toMatchObject({
      value: '```\n1. code\n',
      lineNumber: 3,
      column: 1,
    });
  });
});
