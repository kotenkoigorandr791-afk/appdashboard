import { test, expect, type Page } from '@playwright/test';
import { hasAuthUser, login, gotoShell } from '../helpers/shell';

const ROOT_KEY = 'temp';
const FILE_NAME = `e2e-binary-${Date.now()}.bin`;

type CloudFrame = ReturnType<Page['frameLocator']>;

test.describe.configure({ mode: 'serial' });

test.describe('Cloud binary file + recent files', () => {
  test.use({ viewport: { width: 1500, height: 950 } });

  const skipWithoutAuth = () => test.skip(!hasAuthUser(), 'Needs E2E_USER_EMAIL/PASSWORD');

  async function openCloud(page: Page): Promise<CloudFrame> {
    await login(page);
    await gotoShell(page, '/main?app=cloud');
    await page.waitForSelector('#app-iframe', { timeout: 30000 });
    const frame = page.frameLocator('#app-iframe');
    await frame.locator('#tree .node').first().waitFor({ timeout: 30000 });
    return frame;
  }

  function binaryContent(): string {
    // Zip magic bytes + junk: server should classify as binary.
    return 'PK\x03\x04' + String.fromCharCode(0, 1, 2, 3, 0xff, 0x00);
  }

  test('opening binary file does not show recent files in the viewer', async ({ page }) => {
    skipWithoutAuth();
    const frame = await openCloud(page);

    // Seed a binary file. save_file only writes existing files, so create the
    // entry first, then fill it with the binary payload.
    await frame.locator('body').evaluate(async (_el, { name, content }: any) => {
      const api = (window as any).api;
      try {
        await api('create_file', { path: name, root: 'temp' }, 'POST');
      } catch (e) {
        // An already existing file is fine; anything else must fail loudly
        // instead of surfacing as an application console error.
        if (!String((e as Error)?.message || e).includes('exists')) {
          throw e;
        }
      }
      await api('save_file', { path: name, content, root: 'temp' }, 'POST');
      await api('invalidate_file_tree', {}, 'POST');
    }, { name: FILE_NAME, content: binaryContent() });

    await expect(frame.locator(`#tree .node[data-path="${FILE_NAME}"]`).first()).toBeVisible({ timeout: 20000 });
    await frame.locator(`#tree .node[data-path="${FILE_NAME}"]`).first().click();

    // Binary placeholder with icon + label shows up
    await expect(frame.locator('#viewer').getByText(/Binary File|Бинарный файл/i)).toBeVisible({ timeout: 15000 });

    // Should NOT fall back to recent files state
    await page.waitForTimeout(1500);
    const emptyTitle = await page.frameLocator('#app-iframe').locator('.viewer-empty-title').count();
    const recentList = await page.frameLocator('#app-iframe').locator('.viewer-recent-list').count();
    expect(emptyTitle).toBe(0);
    expect(recentList).toBe(0);
  });
});
