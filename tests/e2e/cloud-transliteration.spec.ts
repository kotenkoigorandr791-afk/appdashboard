import { test, expect, type Page } from '@playwright/test';
import { hasAuthUser, login, gotoShell } from './helpers/shell';

const ROOT_KEY = 'temp';
const FILE_NAME = `e2e-translit-${Date.now()}.txt`;
const EDITOR_FILE_NAME = `e2e-translit-editor-${Date.now()}.md`;

type CloudFrame = ReturnType<Page['frameLocator']>;

test.describe.configure({ mode: 'serial' });

test.describe('Cloud editor transliteration', () => {
  test.use({ viewport: { width: 1500, height: 950 } });

  const skipWithoutAuth = () => test.skip(!hasAuthUser(), 'Needs E2E_USER_EMAIL/PASSWORD');

  /** Logs in, seeds an empty .txt file in the temp root and opens it in Monaco. */
  async function openEditorWithFixture(page: Page): Promise<CloudFrame> {
    await login(page);
    await gotoShell(page, '/main?app=cloud');
    await page.waitForSelector('#app-iframe', { timeout: 30000 });
    const frame = page.frameLocator('#app-iframe');
    await frame.locator('#tree .node').first().waitFor({ timeout: 30000 });

    await frame.locator('body').evaluate(async (_el, { name, root }) => {
      const api = (window as any).api;
      await api('create_file', { path: name, root });
      await api('invalidate_file_tree', {}, 'POST');
    }, { name: FILE_NAME, root: ROOT_KEY });

    await frame.locator('body').evaluate((_el, root) => (window as any).switchCloudRoot(root), ROOT_KEY);
    const node = frame.locator(`#tree .node[data-path="${FILE_NAME}"]`).first();
    await node.waitFor({ timeout: 30000 });
    await node.click();
    await frame.locator('.monaco-editor textarea').first().waitFor({ timeout: 30000 });
    return frame;
  }

  function editorValue(frame: CloudFrame) {
    return frame.locator('body').evaluate((_el, name) => {
      const models = (window as any).monaco?.editor.getModels() || [];
      const model = models.find((m: any) => m.uri.path.endsWith(name));
      return model ? model.getValue() : null;
    }, FILE_NAME);
  }

  test('RU transliteration keeps Б, ю, dots and commas in place', async ({ page }) => {
    skipWithoutAuth();
    const frame = await openEditorWithFixture(page);
    await frame.locator('.lang-option[data-lang="ru"]').click();

    const input = frame.locator('.monaco-editor textarea').first();
    await input.evaluate((el) => (el as HTMLElement).focus());
    await input.pressSequentially('ntcn? ,skf .kf/ <', { delay: 40 });

    await expect.poll(() => editorValue(frame), { timeout: 15000 }).toBe('Тест, была юла. Б');
  });

  test('punctuation stays literal after a real Cyrillic-layout key', async ({ page }) => {
    skipWithoutAuth();
    const frame = await openEditorWithFixture(page);
    await frame.locator('.lang-option[data-lang="ru"]').click();

    const input = frame.locator('.monaco-editor textarea').first();
    await input.evaluate((el) => (el as HTMLElement).focus());
    await input.evaluate((el) => {
      el.dispatchEvent(new KeyboardEvent('keydown', { key: 'я', bubbles: true, cancelable: true }));
    });
    await input.pressSequentially('.,', { delay: 40 });

    await expect.poll(() => editorValue(frame), { timeout: 15000 }).toBe('.,');
  });

  test('switching language in the shell updates an already-open project editor', async ({ page }) => {
    skipWithoutAuth();
    await login(page);
    await gotoShell(page, '/main?app=cloud');
    await page.waitForSelector('#app-iframe', { timeout: 30000 });
    const shell = page.frameLocator('#app-iframe');
    await shell.locator('#tree .node').first().waitFor({ timeout: 30000 });

    await shell.locator('body').evaluate(async (_el, { name, root }) => {
      const api = (window as any).api;
      await api('create_file', { path: name, root });
      await api('invalidate_file_tree', {}, 'POST');
      (window as any).switchCloudRoot(root);
    }, { name: EDITOR_FILE_NAME, root: ROOT_KEY });

    const node = shell.locator(`#tree .node[data-path="${EDITOR_FILE_NAME}"]`).first();
    await node.waitFor({ timeout: 30000 });
    await node.click();

    const editor = shell.frameLocator(
      `#viewer iframe[src*="markdown-editor"][src*="${EDITOR_FILE_NAME}"]`
    );
    const input = editor.locator('.monaco-editor textarea').first();
    await input.waitFor({ timeout: 30000 });

    const value = () =>
      editor.locator('body').evaluate(() => {
        const models = (window as any).monaco?.editor.getModels() || [];
        return models.length ? models[0].getValue() : null;
      });
    const type = async (text: string) => {
      await editor.locator('body').evaluate(() => {
        (window as any).monaco.editor.getModels()[0].setValue('');
      });
      await input.evaluate((el) => (el as HTMLElement).focus());
      await input.pressSequentially(text, { delay: 40 });
    };

    await type('ntcn');
    await expect.poll(value, { timeout: 15000 }).toBe('ntcn');

    await shell.locator('.lang-option[data-lang="ru"]').click();
    await type('ntcn');
    await expect.poll(value, { timeout: 15000 }).toBe('тест');

    await shell.locator('.lang-option[data-lang="en"]').click();
    await type('ntcn');
    await expect.poll(value, { timeout: 15000 }).toBe('ntcn');
  });

  test.afterEach(async ({ page }) => {
    await page
      .frameLocator('#app-iframe')
      .locator('body')
      .evaluate(async (_el, names) => {
        const api = (window as any).api;
        if (typeof api !== 'function') return;
        for (const name of names) {
          try {
            await api('delete', { path: name, root: 'temp' });
          } catch {
            // already gone
          }
        }
        await api('invalidate_file_tree', {}, 'POST').catch(() => {});
      }, [FILE_NAME, EDITOR_FILE_NAME])
      .catch(() => {});
  });
});
