import { expect, type FrameLocator, type Page } from '@playwright/test';
import { hasAuthUser, login, gotoShell } from './shell';

export const ROOT_KEY = 'temp';

export type CloudFrame = FrameLocator;

export function uniqueName(prefix: string, ext = ''): string {
  const stamp = `${Date.now()}-${Math.floor(Math.random() * 1e6)}`;
  return ext ? `${prefix}-${stamp}.${ext}` : `${prefix}-${stamp}`;
}

export async function prepareCloudFrame(page: Page): Promise<CloudFrame> {
  await page.waitForSelector('#app-iframe', { timeout: 60000 });
  const frame = page.frameLocator('#app-iframe');
  await frame.locator('#cloud-encryption-notice-confirm').click({ timeout: 5000 }).catch(() => {});
  await frame.locator('#tree .node').first().waitFor({ timeout: 60000 });
  await frame.locator('body').evaluate((_el, root) => (window as any).switchCloudRoot(root), ROOT_KEY);
  await frame.locator('#tree .node').first().waitFor({ timeout: 60000 });
  return frame;
}

export async function openCloud(page: Page): Promise<CloudFrame> {
  if (!hasAuthUser()) throw new Error('Needs E2E_USER_EMAIL/PASSWORD');
  await login(page);
  await gotoShell(page, '/main?app=cloud');
  return prepareCloudFrame(page);
}

export async function reloadCloud(page: Page): Promise<CloudFrame> {
  await gotoShell(page, '/main?app=cloud');
  return prepareCloudFrame(page);
}

export async function cloudApi(
  frame: CloudFrame,
  action: string,
  params: Record<string, unknown> = {},
  method: 'GET' | 'POST' = 'POST'
) {
  return frame.locator('body').evaluate(
    async (_el, args) => {
      const api = (window as any).api;
      return api(args.action, args.params, args.method);
    },
    { action, params, method }
  );
}

export async function ensureFile(frame: CloudFrame, path: string, content = '') {
  try {
    await cloudApi(frame, 'create_file', { path, root: ROOT_KEY });
  } catch {
    /* exists */
  }
  if (content !== '') {
    await cloudApi(frame, 'save_file', { path, root: ROOT_KEY, content });
  }
  await cloudApi(frame, 'invalidate_file_tree', {}, 'POST');
}

export async function ensureDir(frame: CloudFrame, path: string) {
  try {
    await cloudApi(frame, 'mkdir', { path, root: ROOT_KEY });
  } catch {
    /* exists */
  }
  await cloudApi(frame, 'invalidate_file_tree', {}, 'POST');
}

export async function deletePaths(frame: CloudFrame, paths: string[]) {
  for (const path of paths) {
    try {
      await cloudApi(frame, 'delete', { path, root: ROOT_KEY });
    } catch {
      /* gone */
    }
  }
  try {
    await cloudApi(frame, 'invalidate_file_tree', {}, 'POST');
  } catch {
    /* ignore */
  }
}

export async function listHas(frame: CloudFrame, dir: string, name: string): Promise<boolean> {
  const list = await cloudApi(frame, 'list', { path: dir, root: ROOT_KEY }, 'GET');
  return (list?.items || []).some((i: { name?: string }) => i.name === name);
}

export async function openTreeFile(frame: CloudFrame, path: string) {
  await frame.locator('body').evaluate(
    (_el, args) => {
      const w = window as any;
      if (typeof w.openFileFromTree === 'function') {
        w.openFileFromTree(args.path, args.root);
        return;
      }
      w.openFileInTab?.(args.path, true, args.root);
    },
    { path, root: ROOT_KEY }
  );
}

export async function waitForTreeNode(frame: CloudFrame, path: string) {
  const node = frame.locator(`#tree .node[data-path="${path}"]`).first();
  await node.waitFor({ timeout: 60000 });
  return node;
}

export async function editorFrame(frame: CloudFrame) {
  const iframe = frame.locator('#viewer iframe[data-tab-id]').first();
  await iframe.waitFor({ state: 'attached', timeout: 60000 });
  return frame.frameLocator('#viewer iframe[data-tab-id]');
}

export async function waitMonacoIn(frame: CloudFrame | ReturnType<CloudFrame['frameLocator']>) {
  await frame.locator('.monaco-editor textarea').first().waitFor({ state: 'attached', timeout: 60000 });
}

export async function monacoValue(frame: CloudFrame | ReturnType<CloudFrame['frameLocator']>) {
  return frame.locator('body').evaluate(() => {
    const models = (window as any).monaco?.editor?.getModels?.() || [];
    return (models[0]?.getValue?.() || '').replace(/\r\n/g, '\n');
  });
}

export async function setMonacoValue(
  frame: CloudFrame | ReturnType<CloudFrame['frameLocator']>,
  value: string
) {
  await frame.locator('body').evaluate((_el, text) => {
    const editor = (window as any).monaco.editor.getEditors()[0];
    editor.getModel().setValue(text);
    editor.focus();
  }, value);
}

export function skipAuth(test: { skip: (cond: boolean, msg: string) => void }) {
  test.skip(!hasAuthUser(), 'Needs E2E_USER_EMAIL/PASSWORD');
}

export async function expectPdfMagic(blobBase64: string) {
  const head = Buffer.from(blobBase64, 'base64').subarray(0, 5).toString('utf8');
  expect(head).toBe('%PDF-');
}

export async function uploadPng(frame: CloudFrame, path: string, color = '#ff0000') {
  return frame.locator('body').evaluate(
    async (_el, args) => {
      const canvas = document.createElement('canvas');
      canvas.width = 48;
      canvas.height = 48;
      const ctx = canvas.getContext('2d');
      if (!ctx) throw new Error('no canvas');
      ctx.fillStyle = args.color;
      ctx.fillRect(0, 0, 48, 48);
      const blob: Blob = await new Promise((resolve, reject) => {
        canvas.toBlob(b => (b ? resolve(b) : reject(new Error('toBlob failed'))), 'image/png');
      });
      const apiBase = (window as any).APP_CONFIG?.apiBase || 'api.php';
      const url = new URL(apiBase, window.location.href);
      const fd = new FormData();
      fd.append('action', 'upload');
      fd.append('path', args.path);
      fd.append('root', args.root);
      fd.append('file', blob, args.path.split('/').pop() || 'image.png');
      const res = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
      const text = await res.text();
      let data: any = {};
      try { data = text ? JSON.parse(text) : {}; } catch { /* ignore */ }
      if (!res.ok || data.error) throw new Error(data.error || `upload ${res.status}`);
      const api = (window as any).api;
      await api('invalidate_file_tree', {}, 'POST');
      return data;
    },
    { path, root: ROOT_KEY, color }
  );
}
