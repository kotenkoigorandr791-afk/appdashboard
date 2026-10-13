import { test, expect, type Page } from '@playwright/test';
import { hasAuthUser, login, gotoShell } from './helpers/shell';

const ROOT_KEY = 'temp';
const FILE_NAME = `e2e-backspace-${Date.now()}.txt`;

type CloudFrame = ReturnType<Page['frameLocator']>;

test.describe.configure({ mode: 'serial' });

test.describe('Monaco editor mobile backspace', () => {
  test.use({ viewport: { width: 375, height: 667 }, isMobile: true, hasTouch: true });

  const skipWithoutAuth = () => test.skip(!hasAuthUser(), 'Needs E2E_USER_EMAIL/PASSWORD');

  /** Logs in, seeds an empty .txt file in the temp root and opens it in Monaco. */
  async function openEditorWithFixture(page: Page): Promise<CloudFrame> {
    await login(page);
    // Navigate to cloud app with longer timeout
    await page.goto('/main?app=cloud&lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForSelector('#app-iframe', { timeout: 60000 });
    const frame = page.frameLocator('#app-iframe');
    await frame.locator('#tree .node').first().waitFor({ timeout: 60000 });

    await frame.locator('body').evaluate(async (_el, { name, root }) => {
      const api = (window as any).api;
      await api('create_file', { path: name, root });
      await api('invalidate_file_tree', {}, 'POST');
    }, { name: FILE_NAME, root: ROOT_KEY });

    await frame.locator('body').evaluate((_el, root) => (window as any).switchCloudRoot(root), ROOT_KEY);
    const node = frame.locator(`#tree .node[data-path="${FILE_NAME}"]`).first();
    await node.waitFor({ timeout: 60000 });
    await node.click();
    await frame.locator('.monaco-editor textarea').first().waitFor({ timeout: 60000 });
    return frame;
  }

  function editorValue(frame: CloudFrame) {
    return frame.locator('body').evaluate((_el) => {
      const models = (window as any).monaco?.editor.getModels() || [];
      return models[0]?.getValue() || '';
    });
  }

  function setEditorValue(frame: CloudFrame, value: string) {
    return frame.locator('body').evaluate((_el, val) => {
      const models = (window as any).monaco?.editor.getModels() || [];
      if (models[0]) models[0].setValue(val);
    }, value);
  }

  test('backspace deletes characters on mobile', async ({ page }) => {
    skipWithoutAuth();
    const frame = await openEditorWithFixture(page);

    // Set initial content with dots, numbers, and punctuation
    await setEditorValue(frame, 'test.123!@#');
    await page.waitForTimeout(500);

    // Focus the editor textarea
    const textarea = frame.locator('.monaco-editor textarea').first();
    await textarea.click();
    await page.waitForTimeout(200);

    // Press backspace multiple times
    for (let i = 0; i < 10; i++) {
      await page.keyboard.press('Backspace');
      await page.waitForTimeout(50);
    }

    // Verify content was deleted
    const value = await editorValue(frame);
    expect(value.length).toBeLessThan('test.123!@#'.length);
  });

  test('backspace deletes punctuation and numbers on mobile', async ({ page }) => {
    skipWithoutAuth();
    const frame = await openEditorWithFixture(page);

    // Set content with various punctuation, numbers and special characters
    const testContent = 'hello.world! 123, 456. test? (brackets) "quotes" [square] {curly}';
    await setEditorValue(frame, testContent);
    await page.waitForTimeout(500);

    const textarea = frame.locator('.monaco-editor textarea').first();
    await textarea.click();
    await page.waitForTimeout(200);

    // Delete all characters using backspace
    for (let i = 0; i < testContent.length + 5; i++) {
      await page.keyboard.press('Backspace');
      await page.waitForTimeout(30);
    }

    const value = await editorValue(frame);
    expect(value).toBe('');
  });

  test('backspace works after typing on mobile', async ({ page }) => {
    skipWithoutAuth();
    const frame = await openEditorWithFixture(page);

    const textarea = frame.locator('.monaco-editor textarea').first();
    await textarea.click();
    await page.waitForTimeout(200);

    // Type some text with punctuation
    await page.keyboard.type('test.123!');
    await page.waitForTimeout(300);

    // Verify content was typed
    let value = await editorValue(frame);
    expect(value).toBe('test.123!');

    // Press backspace to delete
    await page.keyboard.press('Backspace');
    await page.waitForTimeout(100);

    value = await editorValue(frame);
    expect(value).toBe('test.123');

    // Continue deleting
    await page.keyboard.press('Backspace');
    await page.waitForTimeout(100);

    value = await editorValue(frame);
    expect(value).toBe('test.12');
  });
});