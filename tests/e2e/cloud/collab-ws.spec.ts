import { test, expect, type Browser, type BrowserContext, type Frame, type Page } from '@playwright/test';

// Two independent users (each in its own browser context) editing the same
// cloud file. Covers the reported failure: "Monaco collab auth failed:
// unauthorized" and iframe editors (markdown/html/latex) not syncing, both
// inside the cloud shell and when opened standalone.
//
// The accounts are created by the e2e bootstrap described in
// tests/README (or manually):
//   collab-a@test.local / collab-b@test.local, password CollabTest123!

const BASE = process.env.TEST_BASE_URL || 'http://localhost';
const PASSWORD = process.env.COLLAB_USER_PASSWORD || 'CollabTest123!';
const USER_A = { email: process.env.COLLAB_USER_A || 'collab-a@test.local', password: PASSWORD };
const USER_B = { email: process.env.COLLAB_USER_B || 'collab-b@test.local', password: PASSWORD };
const CLOUD_URL = '/apps/cloud/?lang=en&theme=light&color=%23007bff';

type Group = { id: string; root: string; name: string };

async function loginAs(page: Page, user: { email: string; password: string }) {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  const form = page.locator('#login-content form[action="/login"]');
  await form.locator('input[name="email"]').fill(user.email);
  await form.locator('input[name="password"]').fill(user.password);
  await form.locator('button[name="login"]').click();
  await page.waitForURL(
    (url) => !url.pathname.startsWith('/login') && !url.pathname.startsWith('/register'),
    { timeout: 60000 }
  );
  const authError = await page.locator('.auth-error').first().textContent().catch(() => null);
  if (authError) throw new Error(`login failed for ${user.email}: ${authError.trim()}`);
}

async function openCloud(page: Page) {
  await page.goto(`${BASE}${CLOUD_URL}`, { waitUntil: 'domcontentloaded' });
  await page.locator('#cloud-encryption-notice-confirm').click({ timeout: 3000 }).catch(() => {});
  await page.waitForFunction(
    () => typeof (window as any).api === 'function' && typeof (window as any).openFileInTab === 'function',
    null,
    { timeout: 60000 }
  );
}

async function createGroup(page: Page, name: string): Promise<Group> {
  const created = await page.evaluate(async (groupName) => (window as any).api('groups_create', { name: groupName }), name);
  return created.group as Group;
}

async function addMember(page: Page, group: Group, email: string) {
  await page.evaluate(
    async ({ gid, member }: { gid: string; member: string }) =>
      (window as any).api('groups_add_member', { group_id: gid, member_email: member, permission: 'read_edit' }),
    { gid: group.id, member: email }
  );
}

async function createGroupFile(page: Page, group: Group, path: string) {
  await page.evaluate(
    async ({ path: filePath, root }: { path: string; root: string }) => {
      try {
        await (window as any).api('create_file', { path: filePath, root });
      } catch {
        /* already exists */
      }
      await (window as any).api('save_file', { path: filePath, root, content: '' });
      await (window as any).api('invalidate_file_tree', {}, 'POST');
    },
    { path, root: group.root }
  );
}

async function openInTab(page: Page, path: string, root: string) {
  await page.evaluate(
    ({ path: filePath, root: rootKey }: { path: string; root: string }) =>
      (window as any).openFileInTab(filePath, true, rootKey),
    { path, root }
  );
}

// Markdown/HTML files get their own editor tab (iframe), they are not opened by
// openFileInTab, which shows them in the plain Monaco editor.
async function openCustomEditor(
  page: Page,
  opener: 'openMarkdownEditor' | 'openHtmlEditor',
  path: string,
  root: string
) {
  await page.evaluate(
    ({ opener: fn, path: filePath, root: rootKey }: { opener: string; path: string; root: string }) => {
      const fnRef = (window as any)[fn];
      if (typeof fnRef !== 'function') throw new Error(`${fn} is not available`);
      fnRef(filePath, rootKey);
    },
    { opener, path, root }
  );
}

async function waitForMainMonacoCollab(page: Page) {
  // monacoCollabSocketState / monacoCollabSessions are top-level `const` in a
  // classic script, so they are lexical globals rather than window properties.
  await page.waitForFunction(
    () => {
      const state = (typeof monacoCollabSocketState !== 'undefined') ? (monacoCollabSocketState as any) : null;
      const sessions = (typeof monacoCollabSessions !== 'undefined') ? monacoCollabSessions : null;
      return !!state && state.authed === true && !!sessions && sessions.size > 0;
    },
    null,
    { timeout: 40000 }
  );
  await page.locator('#viewer .monaco-editor').first().waitFor({ state: 'visible', timeout: 40000 });
}

async function mainMonacoValue(page: Page): Promise<string> {
  return page.evaluate(() => {
    const w = window as any;
    const editors = (typeof editorsMap !== 'undefined') ? editorsMap : null;
    const tabs = (typeof tabsState !== 'undefined') ? tabsState : null;
    const editor = editors?.get(tabs?.activeTabId);
    return (editor?.getValue?.() || '').replace(/\r\n/g, '\n');
  });
}

async function typeInMainMonaco(page: Page, text: string) {
  await page.bringToFront();
  await page.evaluate(() => {
    const editors = (typeof editorsMap !== 'undefined') ? editorsMap : null;
    const tabs = (typeof tabsState !== 'undefined') ? tabsState : null;
    const editor = editors?.get(tabs?.activeTabId);
    if (!editor) throw new Error('active monaco editor not found');
    editor.focus();
    editor.setPosition({ lineNumber: 1, column: 1 });
  });
  await page.keyboard.type(text, { delay: 30 });
}

async function findEditorFrame(page: Page, hrefPart: string, timeout = 60000): Promise<Frame> {
  const deadline = Date.now() + timeout;
  while (Date.now() < deadline) {
    const frame = page.frames().find((candidate) => candidate.url().includes(hrefPart));
    if (frame) {
      // The editor pages declare `let editor` at top level, i.e. a lexical
      // global of that frame, not a window property.
      await frame.waitForFunction(
        () => typeof editor !== 'undefined' && !!editor && typeof editor.getModel === 'function',
        null,
        { timeout: 60000 }
      );
      return frame;
    }
    await page.waitForTimeout(250);
  }
  const frameUrls = page.frames().map((candidate) => candidate.url());
  throw new Error(
    `editor frame containing "${hrefPart}" was not created (page=${page.url()}; frames=${JSON.stringify(frameUrls)})`
  );
}

async function iframeValue(frame: Frame): Promise<string> {
  return frame.evaluate(() => {
    const ed = (typeof editor !== 'undefined') ? editor : null;
    return String(ed?.getValue?.() || '').replace(/\r\n/g, '\n');
  });
}

async function typeInIframe(page: Page, frame: Frame, text: string) {
  await page.bringToFront();
  await frame.evaluate(() => {
    const ed = (typeof editor !== 'undefined') ? editor : null;
    if (!ed) throw new Error('iframe monaco editor not found');
    ed.focus();
    ed.setPosition({ lineNumber: 1, column: 1 });
  });
  await page.keyboard.type(text, { delay: 30 });
}

async function waitForIframeCollab(frame: Frame) {
  await frame.waitForFunction(
    () => !!(window as any).cloudIframeCollab && !!(window as any).cloudIframeCollab.getSession(),
    null,
    { timeout: 40000 }
  );
  // The ticket exchange must have completed for the room to be joined.
  await frame.waitForFunction(
    () => {
      const session = (window as any).cloudIframeCollab?.getSession?.();
      return !!session && session.applying === false;
    },
    null,
    { timeout: 1000 }
  ).catch(() => {});
}

function collectCollabAuthErrors(page: Page): string[] {
  const errors: string[] = [];
  page.on('console', (msg) => {
    if (msg.type() !== 'error') return;
    const text = msg.text();
    if (/collab.*auth|auth.*collab|Collaboration ticket/i.test(text)) errors.push(text);
  });
  page.on('pageerror', (error) => {
    if (/collab/i.test(error.message)) errors.push(error.message);
  });
  return errors;
}

async function newUserContext(browser: Browser, user: { email: string; password: string }): Promise<{ context: BrowserContext; page: Page; authErrors: string[] }> {
  const context = await browser.newContext({ baseURL: BASE, viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  const authErrors = collectCollabAuthErrors(page);
  await loginAs(page, user);
  return { context, page, authErrors };
}

test.describe.configure({ mode: 'serial' });

test.describe('cloud monaco realtime collaboration (2 users, group)', () => {
  let group: Group;

  test.beforeEach(async ({}, testInfo) => {
    testInfo.setTimeout(180000);
  });

  test('text file syncs between two users in a shared group', async ({ browser }) => {
    const a = await newUserContext(browser, USER_A);
    const b = await newUserContext(browser, USER_B);
    try {
      await openCloud(a.page);
      await openCloud(b.page);
      group = await createGroup(a.page, `collab-ws-${Date.now()}`);
      await addMember(a.page, group, USER_B.email);
      await createGroupFile(a.page, group, 'collab-sync.txt');

      await openInTab(a.page, 'collab-sync.txt', group.root);
      await openInTab(b.page, 'collab-sync.txt', group.root);
      await waitForMainMonacoCollab(a.page);
      await waitForMainMonacoCollab(b.page);

      // The editor auto-capitalizes the first character of a line, so markers
      // are compared case-insensitively.
      const fromA = `from-A-${Date.now()}`;
      await typeInMainMonaco(a.page, fromA);
      await expect
        .poll(async () => (await mainMonacoValue(b.page)).toLowerCase(), {
          timeout: 30000,
          message: 'user B did not receive user A text'
        })
        .toContain(fromA.toLowerCase());

      const fromB = `-from-B-${Date.now()}`;
      await typeInMainMonaco(b.page, fromB);
      await expect
        .poll(async () => (await mainMonacoValue(a.page)).toLowerCase(), {
          timeout: 30000,
          message: 'user A did not receive user B text'
        })
        .toContain(fromB.toLowerCase());

      // Autosave must persist the text without pressing Save.
      await expect
        .poll(
          async () => {
            const opened = await a.page.evaluate(
              async ({ path, root }: { path: string; root: string }) =>
                (window as any).api('open', { path, root }, 'GET'),
              { path: 'collab-sync.txt', root: group.root }
            );
            return String(opened?.content || '').toLowerCase();
          },
          { timeout: 45000, message: 'autosave did not persist the group file' }
        )
        .toContain(fromB.toLowerCase());

      expect(a.authErrors, 'user A collab auth errors').toEqual([]);
      expect(b.authErrors, 'user B collab auth errors').toEqual([]);
    } finally {
      await a.context.close();
      await b.context.close();
    }
  });

  test('a user who lost the connection catches up without reloading', async ({ browser }) => {
    const a = await newUserContext(browser, USER_A);
    const b = await newUserContext(browser, USER_B);
    try {
      await openCloud(a.page);
      await openCloud(b.page);
      const offlineGroup = group || (await createGroup(a.page, `collab-offline-${Date.now()}`));
      await addMember(a.page, offlineGroup, USER_B.email).catch(() => {});
      await createGroupFile(a.page, offlineGroup, 'collab-offline.txt');

      await openInTab(a.page, 'collab-offline.txt', offlineGroup.root);
      await openInTab(b.page, 'collab-offline.txt', offlineGroup.root);
      await waitForMainMonacoCollab(a.page);
      await waitForMainMonacoCollab(b.page);

      // Simulate a dropped connection for B only.
      await b.context.setOffline(true);
      await b.page.waitForTimeout(1500);

      const marker = `offline-${Date.now()}`;
      await typeInMainMonaco(a.page, marker);

      // B comes back: the client must reconnect and pull the current room state
      // by itself, without the file being reopened.
      await b.context.setOffline(false);
      await expect
        .poll(async () => (await mainMonacoValue(b.page)).toLowerCase(), {
          timeout: 60000,
          message: 'B did not catch up after reconnecting'
        })
        .toContain(marker.toLowerCase());

      expect(b.authErrors, 'user B collab auth errors').toEqual([]);
    } finally {
      await a.context.close();
      await b.context.close();
    }
  });

  test('markdown iframe editor syncs between two users in a group', async ({ browser }) => {
    const a = await newUserContext(browser, USER_A);
    const b = await newUserContext(browser, USER_B);
    try {
      await openCloud(a.page);
      await openCloud(b.page);
      const mdGroup = group || (await createGroup(a.page, `collab-md-${Date.now()}`));
      await addMember(a.page, mdGroup, USER_B.email).catch(() => {});
      await createGroupFile(a.page, mdGroup, 'collab-sync.md');

      await openCustomEditor(a.page, 'openMarkdownEditor', 'collab-sync.md', mdGroup.root);
      await openCustomEditor(b.page, 'openMarkdownEditor', 'collab-sync.md', mdGroup.root);
      const frameA = await findEditorFrame(a.page, 'markdown-editor');
      const frameB = await findEditorFrame(b.page, 'markdown-editor');
      await waitForIframeCollab(frameA);
      await waitForIframeCollab(frameB);

      const fromA = `md-from-A-${Date.now()}`;
      await typeInIframe(a.page, frameA, fromA);
      await expect
        .poll(async () => (await iframeValue(frameB)).toLowerCase(), {
          timeout: 30000,
          message: 'markdown: B did not receive A'
        })
        .toContain(fromA.toLowerCase());

      const fromB = `-md-from-B-${Date.now()}`;
      await typeInIframe(b.page, frameB, fromB);
      await expect
        .poll(async () => (await iframeValue(frameA)).toLowerCase(), {
          timeout: 30000,
          message: 'markdown: A did not receive B'
        })
        .toContain(fromB.toLowerCase());

      // Both iframes have the shared collab client enabled for the same room.
      expect(await frameA.evaluate(() => !!(window as any).cloudIframeCollab?.getSession())).toBe(true);
      expect(await frameB.evaluate(() => !!(window as any).cloudIframeCollab?.getSession())).toBe(true);

      expect(a.authErrors, 'user A collab auth errors').toEqual([]);
      expect(b.authErrors, 'user B collab auth errors').toEqual([]);
    } finally {
      await a.context.close();
      await b.context.close();
    }
  });

  test('html and latex iframe editors sync when one is opened standalone', async ({ browser }) => {
    const a = await newUserContext(browser, USER_A);
    const b = await newUserContext(browser, USER_B);
    try {
      await openCloud(a.page);
      const editorGroup = group || (await createGroup(a.page, `collab-ed-${Date.now()}`));
      await addMember(a.page, editorGroup, USER_B.email).catch(() => {});

      // HTML: shell in A, standalone tab in B (no cloud shell parent API).
      await createGroupFile(a.page, editorGroup, 'collab-sync.html');
      await openCustomEditor(a.page, 'openHtmlEditor', 'collab-sync.html', editorGroup.root);
      const htmlFrameA = await findEditorFrame(a.page, 'html-editor');
      await waitForIframeCollab(htmlFrameA);

      const htmlStandalone = await b.context.newPage();
      const standaloneErrors = collectCollabAuthErrors(htmlStandalone);
      await htmlStandalone.goto(
        `${BASE}/apps/cloud/utilities/projects/html-editor/public/index.php?path=${encodeURIComponent(
          'collab-sync.html'
        )}&user=collab&lang=en&theme=light&color=%23007bff&root=${encodeURIComponent(editorGroup.root)}`,
        { waitUntil: 'domcontentloaded' }
      );
      await htmlStandalone.waitForURL(/html-editor/, { timeout: 60000 });
      const htmlFrameB = await findEditorFrame(htmlStandalone, 'html-editor', 90000);
      await waitForIframeCollab(htmlFrameB);

      const htmlFromA = `html-from-A-${Date.now()}`;
      await typeInIframe(a.page, htmlFrameA, htmlFromA);
      await expect
        .poll(async () => (await iframeValue(htmlFrameB)).toLowerCase(), {
          timeout: 30000,
          message: 'html standalone: B did not receive A'
        })
        .toContain(htmlFromA.toLowerCase());
      expect(standaloneErrors, 'standalone html collab auth errors').toEqual([]);

      // LaTeX: both sides in the shell.
      await createGroupFile(a.page, editorGroup, 'collab-sync.tex');
      await openInTab(a.page, 'collab-sync.tex', editorGroup.root);
      const texFrameA = await findEditorFrame(a.page, 'latex-editor');
      await waitForIframeCollab(texFrameA);

      await openCloud(b.page);
      await openInTab(b.page, 'collab-sync.tex', editorGroup.root);
      const texFrameB = await findEditorFrame(b.page, 'latex-editor');
      await waitForIframeCollab(texFrameB);

      const texFromA = `tex-from-A-${Date.now()}`;
      await typeInIframe(a.page, texFrameA, texFromA);
      await expect
        .poll(async () => (await iframeValue(texFrameB)).toLowerCase(), {
          timeout: 30000,
          message: 'latex: B did not receive A'
        })
        .toContain(texFromA.toLowerCase());

      expect(a.authErrors, 'user A collab auth errors').toEqual([]);
      expect(b.authErrors, 'user B collab auth errors').toEqual([]);
    } finally {
      await a.context.close();
      await b.context.close();
    }
  });

  test.afterAll(async ({ browser }) => {
    if (!group) return;
    const context = await browser.newContext({ baseURL: BASE });
    const page = await context.newPage();
    try {
      await loginAs(page, USER_A);
      await openCloud(page);
      await page.evaluate(async (gid) => (window as any).api('groups_delete', { group_id: gid }), group.id);
    } catch {
      /* best-effort cleanup */
    } finally {
      await context.close();
    }
  });
});
