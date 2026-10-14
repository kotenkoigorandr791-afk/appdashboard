import { expect, test, type FrameLocator, type Page } from '@playwright/test';
import { autoAcceptCookies } from './helpers/shell';

const password = process.env.E2E_USER_PASSWORD || '12345T';
const email = process.env.E2E_USER_EMAIL || 'root@root';

async function loginAs(page: Page) {
  await page.goto('/login?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
  await page.locator('#login-content input[name="email"]').fill(email);
  await page.locator('#login-password').fill(password);
  await Promise.all([
    page.waitForURL(url => !url.pathname.includes('/login'), { timeout: 30000 }),
    page.locator('#login-content button[name="login"]').click()
  ]);
  await autoAcceptCookies(page);
}

async function openCloud(page: Page): Promise<FrameLocator> {
  await page.goto('/main?app=cloud&lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
  await page.locator('#cookie-consent-overlay').evaluate(el => el.remove()).catch(() => {});
  await page.waitForSelector('#app-iframe', { timeout: 30000 });
  const frame = page.frameLocator('#app-iframe');
  await frame.locator('#cloud-encryption-notice-confirm').click({ timeout: 5000 }).catch(() => {});
  await frame.locator('#btn-share').waitFor({ state: 'attached', timeout: 30000 });
  return frame;
}

async function cloudApi<T = any>(page: Page, action: string, params: Record<string, string>): Promise<T> {
  return page.evaluate(async ({ action, params }) => {
    const body = new URLSearchParams({ action, ...params });
    const res = await fetch('/apps/cloud/api.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body
    });
    return res.json();
  }, { action, params });
}

const ROOT_PREFIX: Record<string, string> = {
  temp: '.temp_files',
  archive: '.archive',
  documents: '.documents',
  photos: '.photos',
  pdf: '.pdf',
  trash: '.trash'
};

test.describe('cloud share path resolution from any folder', () => {
  test('share_file resolves every root and folder depth', async ({ page }) => {
    test.setTimeout(240000);
    await loginAs(page);
    await openCloud(page);

    const stamp = Date.now();
    const relPaths: Record<string, string> = {
      main: `share-probe-${stamp}.txt`,
      mainNested: `share-probe-folder-${stamp}/inside.txt`,
      temp: `share-probe-${stamp}.txt`,
      tempNested: `share-probe-folder-${stamp}/inside.txt`,
      archive: `share-probe-${stamp}.txt`,
      documents: `share-probe-${stamp}.txt`,
      photos: `share-probe-${stamp}.txt`,
      pdf: `share-probe-${stamp}.txt`,
      trash: `share-probe-${stamp}.txt`
    };

    const roots: Array<[string, string]> = [
      ['main', relPaths.main],
      ['main', relPaths.mainNested],
      ['temp', relPaths.temp],
      ['temp', relPaths.tempNested],
      ['archive', relPaths.archive],
      ['documents', relPaths.documents],
      ['photos', relPaths.photos],
      ['pdf', relPaths.pdf],
      ['trash', relPaths.trash]
    ];

    for (const [root, path] of roots) {
      const made = await cloudApi(page, 'create_file', { path, root });
      expect(made.error, `create_file ${root}/${path}`).toBeUndefined();
      const saved = await cloudApi(page, 'save_file', { path, content: `probe ${path}`, root });
      expect(saved.error, `save_file ${root}/${path}`).toBeUndefined();
    }

    const cases = roots.map(([root, path]) => ({
      label: `${root} :: ${path.includes('/') ? 'subfolder' : 'top level'}`,
      root,
      clientPath: root === 'main' ? path : `${ROOT_PREFIX[root]}/${path}`
    }));

    const failures: string[] = [];
    const keys: Record<string, string> = {};

    for (const testCase of cases) {
      const response = await cloudApi(page, 'share_file', {
        path: testCase.clientPath,
        root: testCase.root,
        expires_option: 'never'
      });
      if (response.error || !response.key) {
        failures.push(`${testCase.label} -> ${response.error || 'no key'} (path=${testCase.clientPath})`);
      } else {
        keys[testCase.label] = response.key;
      }
    }

    expect(failures, 'share_file must resolve these paths:\n' + failures.join('\n')).toEqual([]);
    expect(Object.keys(keys).length).toBe(cases.length);

    for (const [label, key] of Object.entries(keys)) {
      const meta = await cloudApi(page, 'internal_share_metadata', { path: cases.find(c => c.label === label)!.clientPath, root: cases.find(c => c.label === label)!.root });
      expect(meta.error, `internal_share_metadata ${label}`).toBeUndefined();
      expect(meta.name, `internal_share_metadata ${label}`).toBeTruthy();
    }
  });

  test('a real share link opens the file it was generated for', async ({ page, browser }) => {
    test.setTimeout(240000);
    await loginAs(page);
    await openCloud(page);

    const stamp = Date.now();
    const path = `share-link-folder-${stamp}/inside.txt`;
    expect((await cloudApi(page, 'create_file', { path, root: 'main' })).error).toBeUndefined();
    expect((await cloudApi(page, 'save_file', { path, content: `unique-content-${stamp}`, root: 'main' })).error).toBeUndefined();

    const share = await cloudApi(page, 'share_file', { path, root: 'main', expires_option: 'never' });
    expect(share.error).toBeUndefined();
    expect(share.key).toBeTruthy();

    const guestContext = await browser.newContext({ baseURL: 'http://localhost' });
    const guestPage = await guestContext.newPage();
    const response = await guestPage.goto(`/?sharedFile=${share.key}&lang=en&theme=light&color=%232563eb`, { waitUntil: 'domcontentloaded' });
    expect(response?.status(), 'shared link must respond 200').toBeLessThan(400);
    await guestPage.waitForTimeout(2500);
    const body = await guestPage.locator('body').innerText();
    expect(body, 'shared link must render the shared file').toContain(`unique-content-${stamp}`);
    await guestContext.close();
  });

  test('group root cannot be escaped with a hidden root prefix', async ({ page }) => {
    test.setTimeout(180000);
    await loginAs(page);
    await openCloud(page);

    const stamp = Date.now();
    const path = `group-escape-${stamp}.txt`;
    expect((await cloudApi(page, 'create_file', { path, root: 'temp' })).error).toBeUndefined();

    const realGroupId = '11111111-2222-3333-4444-555555555555';
    const response = await cloudApi(page, 'share_file', {
      path: `.temp_files/${path}`,
      root: `group:${realGroupId}`,
      expires_option: 'never'
    });
    expect(response.key, 'a group root must not resolve a personal .temp_files path').toBeFalsy();
    expect(response.error).toBeTruthy();
  });
});