import { test, expect, type FrameLocator, type Page } from '@playwright/test';
import { hasAuthUser, login, gotoShell } from './helpers/shell';

const ROOT_KEY = 'temp';
const SRC_DIR = 'e2e-fixture-src';
const DST_DIR = 'e2e-fixture-dst';
const NESTED_DIR = `${DST_DIR}/nested`;
const FILE_NAME = 'e2e-fixture-target.txt';

type CloudFrame = ReturnType<Page['frameLocator']>;

test.describe.configure({ mode: 'serial' });

test.describe('Cloud file move', () => {
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

  /** Creates the fixture folders and one file inside SRC_DIR, all through the app API. */
  async function seedFixture(frame: CloudFrame) {
    await frame.locator('body').evaluate(async (_el, { src, dirs, name }) => {
      const api = (window as any).api;
      for (const dir of dirs) {
        try {
          await api('mkdir', { path: dir, root: 'temp' });
        } catch {
          // already exists
        }
      }
      try {
        await api('create_file', { path: `${src}/${name}`, root: 'temp' });
      } catch {
        // already exists
      }
      // The app keeps a per-user file-tree cache; drop it so the tree renders the fixture.
      await api('invalidate_file_tree', {}, 'POST');
    }, { src: SRC_DIR, dirs: [SRC_DIR, DST_DIR, NESTED_DIR], name: FILE_NAME });
  }

  /** Puts a copy of the fixture file inside an arbitrary fixture folder. */
  async function seedFile(frame: CloudFrame, dir: string) {
    await frame.locator('body').evaluate(
      async (_el, { dir, name }) => {
        const api = (window as any).api;
        try {
          await api('mkdir', { path: dir, root: 'temp' });
        } catch {
          // already exists
        }
        try {
          await api('create_file', { path: `${dir}/${name}`, root: 'temp' });
        } catch {
          // already exists
        }
        await api('invalidate_file_tree', {}, 'POST');
      },
      { dir, name: FILE_NAME }
    );
  }

  /** Reports where the fixture file currently lives, per folder. */
  async function fileLocations(frame: CloudFrame, dirs: string[], name: string) {
    return frame.locator('body').evaluate(
      async (_el, { dirs, name }) => {
        const api = (window as any).api;
        const result: Record<string, boolean> = {};
        for (const dir of dirs) {
          const list = await api('list', { path: dir, root: 'temp' }, 'GET');
          result[dir] = (list.items || []).some((i: any) => i.name === name);
        }
        return result;
      },
      { dirs, name }
    );
  }

  async function switchToTempRoot(frame: CloudFrame) {
    await frame.locator('body').evaluate((_el, root) => (window as any).switchCloudRoot(root), ROOT_KEY);
    await frame.locator('#tree .node').first().waitFor({ timeout: 30000 });
  }

  async function expandTo(frame: CloudFrame, path: string) {
    await frame.locator('body').evaluate((_el, p) => (window as any).expandFolderToPath?.(p), path);
    await frame.locator(`#tree .node[data-path="${path}"]`).first().waitFor({ timeout: 30000 });
  }

  /** Realistic mouse drag inside the cloud iframe: press, move in steps, release. */
  async function dragOnto(page: Page, from: { x: number; y: number }, to: { x: number; y: number }) {
    await page.mouse.move(from.x, from.y);
    await page.mouse.down();
    for (let i = 1; i <= 12; i++) {
      await page.mouse.move(from.x + ((to.x - from.x) * i) / 12, from.y + ((to.y - from.y) * i) / 12);
      await page.waitForTimeout(30);
    }
    await page.mouse.up();
  }

  test.afterEach(async ({ page }) => {
    await page
      .frameLocator('#app-iframe')
      .locator('body')
      .evaluate(
        async (_el, { dirs, name }) => {
          const api = (window as any).api;
          if (typeof api !== 'function') return;
          for (const path of [...dirs, name, ...dirs.map(d => `${d}/${name}`)]) {
            try {
              await api('delete', { path, root: 'temp' });
            } catch {
              // already gone
            }
          }
          await api('invalidate_file_tree', {}, 'POST');
        },
        { dirs: [SRC_DIR, NESTED_DIR, DST_DIR], name: FILE_NAME }
      )
      .catch(() => {});
  });

  test('move dialog opens on the source root and expands nested folders on label click', async ({ page }) => {
    skipWithoutAuth();
    const frame = await openCloud(page);
    await seedFixture(frame);
    await switchToTempRoot(frame);
    await expandTo(frame, SRC_DIR);

    const fileNode = frame.locator(`#tree .node[data-type="file"][data-path="${SRC_DIR}/${FILE_NAME}"]`);
    await fileNode.waitFor({ timeout: 30000 });

    await fileNode.click({ button: 'right' });
    await frame.locator('.context-menu-item').filter({ hasText: /^Actions$/ }).first().hover();
    await frame.locator('.context-menu-submenu .context-menu-item').filter({ hasText: /^Move$/ }).first().click();
    await frame.locator('#move-folder-tree').waitFor({ timeout: 15000 });

    // The target root defaults to the root the source file lives in, not a hardcoded "main".
    await expect(frame.locator('.move-root-btn.active')).toHaveAttribute('data-root', ROOT_KEY);
    expect(await frame.locator('body').evaluate(() => (window as any).selectedMoveRoot)).toBe(ROOT_KEY);
    await expect(frame.locator(`#move-folder-tree .move-tree-node[data-path="${DST_DIR}"]`)).toBeVisible();

    // Clicking a folder name selects it and lazily loads its children in the same step.
    await frame.locator(`#move-folder-tree .move-tree-node[data-path="${DST_DIR}"] .move-tree-label`).click();
    await frame.locator(`#move-folder-tree .move-tree-node[data-path="${NESTED_DIR}"]`).waitFor({ timeout: 15000 });
    expect(await frame.locator('body').evaluate(() => (window as any).selectedMoveFolder)).toBe(DST_DIR);

    await frame.locator(`#move-folder-tree .move-tree-node[data-path="${NESTED_DIR}"] .move-tree-label`).click();
    expect(await frame.locator('body').evaluate(() => (window as any).selectedMoveFolder)).toBe(NESTED_DIR);
    await expect(frame.locator('#current-folder-path')).toHaveText(NESTED_DIR);

    await frame.locator('#btn-confirm-move').click();
    await expect(frame.locator('#move-folder-tree')).toHaveCount(0, { timeout: 15000 });

    const locations = await fileLocations(frame, [SRC_DIR, NESTED_DIR], FILE_NAME);
    expect(locations[NESTED_DIR]).toBe(true);
    expect(locations[SRC_DIR]).toBe(false);
  });

  for (const grab of ['label', 'icon'] as const) {
    test(`drag by ${grab} into a folder moves the file itself`, async ({ page }) => {
      skipWithoutAuth();
      const frame = await openCloud(page);
      await seedFixture(frame);
      await switchToTempRoot(frame);
      await expandTo(frame, SRC_DIR);

      const fileNode = frame.locator(`#tree .node[data-type="file"][data-path="${SRC_DIR}/${FILE_NAME}"]`);
      const dirNode = frame.locator(`#tree .node[data-type="dir"][data-path="${DST_DIR}"]`);
      await fileNode.waitFor({ timeout: 30000 });
      await dirNode.waitFor({ timeout: 30000 });

      const handle = grab === 'icon' ? fileNode.locator('.toggle img') : fileNode.locator('.label');
      if (grab === 'icon') {
        // Grabbing the icon must not start a native image drag, which would carry the SVG URL.
        await expect(handle).toHaveAttribute('draggable', 'false');
      }

      const fileBox = await handle.boundingBox();
      const dirBox = await dirNode.boundingBox();
      expect(fileBox).toBeTruthy();
      expect(dirBox).toBeTruthy();

      await dragOnto(
        page,
        { x: fileBox!.x + fileBox!.width / 2, y: fileBox!.y + fileBox!.height / 2 },
        { x: dirBox!.x + 40, y: dirBox!.y + dirBox!.height / 2 }
      );

      const locations = await fileLocations(frame, [SRC_DIR, DST_DIR], FILE_NAME);
      expect(locations[DST_DIR]).toBe(true);
      expect(locations[SRC_DIR]).toBe(false);
    });
  }

  test('drag into an ancestor folder moves the file up instead of dropping it at the tree root', async ({ page }) => {
    skipWithoutAuth();
    const frame = await openCloud(page);
    await seedFixture(frame);
    await seedFile(frame, NESTED_DIR);
    await switchToTempRoot(frame);
    await expandTo(frame, NESTED_DIR);

    const fileNode = frame.locator(`#tree .node[data-type="file"][data-path="${NESTED_DIR}/${FILE_NAME}"]`);
    const ancestorNode = frame.locator(`#tree .node[data-type="dir"][data-path="${DST_DIR}"]`);
    await fileNode.waitFor({ timeout: 30000 });
    await ancestorNode.waitFor({ timeout: 30000 });

    const fileBox = await fileNode.locator('.label').boundingBox();
    const dirBox = await ancestorNode.boundingBox();
    expect(fileBox).toBeTruthy();
    expect(dirBox).toBeTruthy();

    await dragOnto(
      page,
      { x: fileBox!.x + fileBox!.width / 2, y: fileBox!.y + fileBox!.height / 2 },
      { x: dirBox!.x + 40, y: dirBox!.y + dirBox!.height / 2 }
    );

    const locations = await fileLocations(frame, [DST_DIR, NESTED_DIR, ''], FILE_NAME);
    expect(locations[DST_DIR]).toBe(true);
    expect(locations[NESTED_DIR]).toBe(false);
    expect(locations['']).toBe(false);
  });
});
