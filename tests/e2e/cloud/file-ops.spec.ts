import { test, expect, type Page } from '@playwright/test';
import {
  ROOT_KEY,
  openCloud,
  cloudApi,
  ensureFile,
  ensureDir,
  deletePaths,
  listHas,
  uniqueName,
  skipAuth,
  type CloudFrame,
} from '../helpers/cloud';

test.describe.configure({ mode: 'serial', timeout: 120000 });

test.describe('Cloud file operations', () => {
  test.use({ viewport: { width: 1500, height: 950 } });

  let frame: CloudFrame;
  const created: string[] = [];

  test.beforeEach(async ({ page }) => {
    skipAuth(test);
    test.skip((page.viewportSize()?.width || 0) < 769, 'desktop cloud tree');
    frame = await openCloud(page);
    created.length = 0;
  });

  test.afterEach(async () => {
    if (!frame) return;
    await deletePaths(frame, [...created].reverse()).catch(() => {});
  });

  function track(...paths: string[]) {
    created.push(...paths);
  }

  test('mkdir create_file list and save_file', async () => {
    const dir = uniqueName('e2e-ops-dir');
    const file = `${dir}/${uniqueName('e2e-ops', 'txt')}`;
    track(file, dir);
    await ensureDir(frame, dir);
    await ensureFile(frame, file, 'hello ops\n');
    expect(await listHas(frame, dir, file.split('/').pop()!)).toBe(true);
    const opened = await cloudApi(frame, 'open', { path: file, root: ROOT_KEY }, 'GET');
    expect(String(opened?.content ?? opened?.data ?? '')).toContain('hello ops');
    await cloudApi(frame, 'save_file', { path: file, root: ROOT_KEY, content: 'updated\n' });
    const again = await cloudApi(frame, 'open', { path: file, root: ROOT_KEY }, 'GET');
    expect(String(again?.content ?? again?.data ?? '')).toContain('updated');
  });

  test('rename moves basename in place', async () => {
    const src = uniqueName('e2e-rename-src', 'txt');
    const dstName = uniqueName('e2e-rename-dst', 'txt');
    track(src, dstName);
    await ensureFile(frame, src, 'rename-me\n');
    await cloudApi(frame, 'rename', { old_path: src, new_name: dstName, root: ROOT_KEY });
    await cloudApi(frame, 'invalidate_file_tree', {}, 'POST');
    expect(await listHas(frame, '', src)).toBe(false);
    expect(await listHas(frame, '', dstName)).toBe(true);
  });

  test('copy keeps source and creates target', async () => {
    const dir = uniqueName('e2e-copy-dir');
    const src = uniqueName('e2e-copy', 'txt');
    track(`${dir}/${src}`, src, dir);
    await ensureDir(frame, dir);
    await ensureFile(frame, src, 'copy-body\n');
    await cloudApi(frame, 'copy', {
      source_path: src,
      target_dir: dir,
      target_root: ROOT_KEY,
      root: ROOT_KEY,
    });
    expect(await listHas(frame, '', src)).toBe(true);
    expect(await listHas(frame, dir, src)).toBe(true);
  });

  test('move relocates file into folder', async () => {
    const dir = uniqueName('e2e-mv-dir');
    const src = uniqueName('e2e-mv', 'txt');
    track(`${dir}/${src}`, src, dir);
    await ensureDir(frame, dir);
    await ensureFile(frame, src, 'move-body\n');
    await cloudApi(frame, 'move', {
      source_path: src,
      target_dir: dir,
      target_root: ROOT_KEY,
      root: ROOT_KEY,
    });
    expect(await listHas(frame, '', src)).toBe(false);
    expect(await listHas(frame, dir, src)).toBe(true);
  });

  test('delete removes path', async () => {
    const file = uniqueName('e2e-del', 'txt');
    track(file);
    await ensureFile(frame, file, 'bye\n');
    await cloudApi(frame, 'delete', { path: file, root: ROOT_KEY });
    await cloudApi(frame, 'invalidate_file_tree', {}, 'POST');
    expect(await listHas(frame, '', file)).toBe(false);
    created.length = 0;
  });

  test('move_to_trash then restore_from_trash', async () => {
    const file = uniqueName('e2e-trash', 'txt');
    track(file);
    await ensureFile(frame, file, 'trash-me\n');
    const trashRes = await cloudApi(frame, 'move_to_trash', { path: file, root: ROOT_KEY });
    expect(trashRes?.ok).toBeTruthy();
    const trashId = String(trashRes?.item?.id || '');
    expect(trashId).not.toBe('');
    expect(await listHas(frame, '', file)).toBe(false);
    const restored = await cloudApi(frame, 'restore_from_trash', { path: trashId });
    expect(restored?.ok).toBeTruthy();
    await cloudApi(frame, 'invalidate_file_tree', {}, 'POST');
    expect(await listHas(frame, '', file)).toBe(true);
  });

  test('context menu Move confirms into nested folder', async () => {
    const srcDir = uniqueName('e2e-ui-src');
    const dstDir = uniqueName('e2e-ui-dst');
    const nested = `${dstDir}/nested`;
    const name = uniqueName('e2e-ui', 'txt');
    const srcPath = `${srcDir}/${name}`;
    track(`${nested}/${name}`, `${dstDir}/${name}`, srcPath, nested, dstDir, srcDir);
    await ensureDir(frame, srcDir);
    await ensureDir(frame, dstDir);
    await ensureDir(frame, nested);
    await ensureFile(frame, srcPath, 'ui-move\n');
    await frame.locator('body').evaluate((_el, root) => (window as any).switchCloudRoot(root), ROOT_KEY);
    await frame.locator('#tree .node').first().waitFor({ timeout: 30000 });
    await frame.locator('body').evaluate(async (_el, p) => {
      const w = window as any;
      if (typeof w.expandFolderToPath === 'function') await w.expandFolderToPath(p);
      else if (typeof w.expandPath === 'function') await w.expandPath(p);
    }, srcPath);
    const fileNode = frame.locator(`#tree .node[data-path="${srcPath}"]`).first();
    await expect
      .poll(async () => fileNode.count(), { timeout: 45000 })
      .toBeGreaterThan(0);
    await fileNode.click({ button: 'right' });
    await frame.locator('.context-menu-item').filter({ hasText: /^Actions$/ }).first().hover();
    await frame.locator('.context-menu-submenu .context-menu-item').filter({ hasText: /^Move$/ }).first().click();
    await frame.locator('#move-folder-tree').waitFor({ timeout: 15000 });
    await frame.locator(`#move-folder-tree .move-tree-node[data-path="${dstDir}"] .move-tree-label`).click();
    await frame.locator(`#move-folder-tree .move-tree-node[data-path="${nested}"]`).waitFor({ timeout: 15000 });
    await frame.locator(`#move-folder-tree .move-tree-node[data-path="${nested}"] .move-tree-label`).click();
    await frame.locator('#btn-confirm-move').click();
    await expect(frame.locator('#move-folder-tree')).toHaveCount(0, { timeout: 15000 });
    expect(await listHas(frame, nested, name)).toBe(true);
    expect(await listHas(frame, srcDir, name)).toBe(false);
  });
});
