import { expect, test, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { autoAcceptCookies } from './helpers/shell';

const adminUser = process.env.STORAGE_REQ_ADMIN_EMAIL || 'pw-storage-admin@example.com';
const normalUser = process.env.STORAGE_REQ_USER_EMAIL || 'pw-storage-user@example.com';
const password = process.env.STORAGE_REQ_PASSWORD || 'CodexPass1';

async function loginAs(page: Page, email: string) {
  await page.goto('/login?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
  await page.locator('#login-content input[name="email"]').fill(email);
  await page.locator('#login-password').fill(password);
  await Promise.all([
    page.waitForURL(url => !url.pathname.includes('/login'), { timeout: 30000 }),
    page.locator('#login-content button[name="login"]').click()
  ]);
  await autoAcceptCookies(page);
}

function seedUsers() {
  const php = `
define("APP_ROOT", getcwd());
require "db.php";
$db = getDB();
initDB($db);
ensureSupportChatSchema($db);
upsertUser("${adminUser}", ["name"=>"Storage Admin","password"=>appPasswordHash("${password}"),"role"=>2,"verified"=>true,"language"=>"en","theme"=>"light","color"=>"#2563eb"]);
upsertUser("${normalUser}", ["name"=>"Storage User","password"=>appPasswordHash("${password}"),"role"=>1,"verified"=>true,"language"=>"en","theme"=>"light","color"=>"#2563eb"]);
// fresh quota + no leftover requests/notifications
$st = $db->prepare("DELETE FROM cloud_user_settings WHERE email IN (:a, :u)");
$st->bindValue(":a", "${adminUser}"); $st->bindValue(":u", "${normalUser}"); $st->execute();
$db->exec("CREATE TABLE IF NOT EXISTS cloud_storage_requests (id BIGSERIAL PRIMARY KEY, email TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'pending', note TEXT NOT NULL DEFAULT '', current_quota_mb INTEGER NOT NULL DEFAULT 0, used_bytes BIGINT NOT NULL DEFAULT 0, granted_quota_mb INTEGER NOT NULL DEFAULT 0, reject_reason TEXT NOT NULL DEFAULT '', notification_id BIGINT NOT NULL DEFAULT 0, created_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint), resolved_at BIGINT, resolved_by TEXT NOT NULL DEFAULT '')");
$rq = $db->prepare("DELETE FROM cloud_storage_requests WHERE email = :u");
$rq->bindValue(":u", "${normalUser}"); $rq->execute();
$nt = $db->prepare("DELETE FROM app_notifications WHERE user_email = :u OR (event_type = 'cloud_storage_request' AND context_json LIKE :p)");
$nt->bindValue(":u", "${normalUser}"); $nt->bindValue(":p", "%${normalUser}%"); $nt->execute();
`;
  execFileSync('docker', ['exec', 'appdashboard', 'php', '-r', php], { stdio: 'pipe' });
}

async function openCloudApp(page: Page) {
  await page.goto('/main?app=cloud&lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
  await page.locator('#cookie-consent-overlay').evaluate(el => el.remove()).catch(() => {});
  await page.waitForSelector('#app-iframe', { timeout: 30000 });
  const frame = page.frameLocator('#app-iframe');
  await frame.locator('#cloud-encryption-notice-confirm').click({ timeout: 5000 }).catch(() => {});
  await frame.locator('#btn-settings').waitFor({ state: 'visible', timeout: 30000 });
  return frame;
}

async function cloudApi<T = any>(page: Page, action: string, params: Record<string, string> = {}, method: 'GET' | 'POST' = 'GET'): Promise<T> {
  return page.evaluate(async ({ action, params, method }) => {
    if (method === 'GET') {
      const url = new URL('/apps/cloud/api.php', location.origin);
      url.searchParams.set('action', action);
      Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
      const res = await fetch(url.toString(), { credentials: 'same-origin' });
      return res.json();
    }
    const body = new URLSearchParams({ action, ...params });
    const res = await fetch('/apps/cloud/api.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
    return res.json();
  }, { action, params, method });
}

type LocatorScope = { locator: (selector: string) => any };

async function expectInsideViewport(frame: LocatorScope, selector: string) {
  const box = await frame.locator(selector).evaluate(el => {
    const r = el.getBoundingClientRect();
    return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width, height: r.height, vw: window.innerWidth, vh: window.innerHeight };
  });
  expect(box.width).toBeGreaterThan(0);
  expect(box.height).toBeGreaterThan(0);
  expect(box.left).toBeGreaterThanOrEqual(-1);
  expect(box.right).toBeLessThanOrEqual(box.vw + 1);
}

async function expectNoHorizontalOverflow(frame: LocatorScope, selector: string) {
  const overflow = await frame.locator(selector).evaluate(el => el.scrollWidth - el.clientWidth);
  expect(overflow).toBeLessThanOrEqual(2);
}

async function expectTableFillsModal(frame: LocatorScope, modalSelector: string) {
  const widths = await frame.locator(modalSelector).evaluate(el => {
    const body = el.querySelector('.modal-body');
    if (!body) return { bodyContent: 0, table: 0 };
    const cs = getComputedStyle(body);
    const bodyContent = body.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
    const scrollBody = body.scrollWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
    const table = body.querySelector('#storage-admin-table');
    return { bodyContent, scrollBody, table: table ? table.getBoundingClientRect().width : 0 };
  });
  expect(widths.table).toBeGreaterThan(widths.bodyContent * 0.85);
}

async function notificationItems(page: Page): Promise<any[]> {
  return page.evaluate(async () => {
    const res = await fetch('/main?api=notifications&action=list&limit=40');
    const data = await res.json();
    return data.items || [];
  });
}

test.describe('cloud storage requests', () => {
  // Both flows share the same seeded admin/user and wipe each other's pending
  // requests, so they must not run concurrently (default workers > 1).
  test.describe.configure({ mode: 'serial' });
  test.beforeAll(seedUsers);

  test('user requests more storage; admin is notified and approves from chat', async ({ page, browser }) => {
    test.setTimeout(240000);
    const consoleErrors: string[] = [];
    page.on('console', msg => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
    page.on('pageerror', err => consoleErrors.push(err.message));

    // --- User requests more storage from the cloud settings ---
    await loginAs(page, normalUser);
    const frame = await openCloudApp(page);
    await frame.locator('#btn-settings').click();
    await expect(frame.locator('#settings-quota-row')).toBeHidden();
    await expect(frame.locator('#settings-storage-request')).toBeVisible();
    await expect(frame.locator('#storage-request-summary')).toContainText('GB');
    await frame.locator('#btn-request-storage').click();
    await expect(frame.locator('#storage-request-pending')).toBeVisible({ timeout: 15000 });
    await page.screenshot({ path: 'tests/test-results/cloud-storage-request-user.png' });

    // The admin broadcast notification must not leak to the regular user, and
    // admin endpoints must be forbidden for them.
    const userItemsBefore = await notificationItems(page);
    expect(userItemsBefore.some((n: any) => n.event_type === 'cloud_storage_request')).toBeFalsy();
    const forbidden = await cloudApi(page, 'admin_storage_overview');
    expect(forbidden.error).toBe('forbidden');

    // --- Admin gets the shell notification ---
    const adminContext = await browser.newContext({ baseURL: process.env.TEST_BASE_URL || 'http://localhost' });
    const adminPage = await adminContext.newPage();
    const adminErrors: string[] = [];
    adminPage.on('console', msg => { if (msg.type() === 'error') adminErrors.push(msg.text()); });
    adminPage.on('pageerror', err => adminErrors.push(err.message));
    await loginAs(adminPage, adminUser);
    await adminPage.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
    await adminPage.locator('#cookie-consent-overlay').evaluate(el => el.remove()).catch(() => {});
    await adminPage.locator('#notifications-btn').first().click();
    await expect(adminPage.locator('#notifications-list')).toContainText('Storage increase request', { timeout: 15000 });
    await expect(adminPage.locator('#notifications-list')).toContainText(normalUser);
    await adminPage.screenshot({ path: 'tests/test-results/cloud-storage-request-admin-notification.png' });

    // --- Admin opens the chat with the requester and approves ---
    await adminPage.locator('#support-chat-btn').first().click();
    await expect(adminPage.locator('#support-chat-modal')).toBeVisible();
    await adminPage.locator('#new-chat-btn').click();
    await adminPage.locator('#chat-type-support').click();
    await adminPage.locator('#chat-user-search-input').fill(normalUser);
    await expect(adminPage.locator('#chat-user-search-results button').first()).toBeVisible({ timeout: 15000 });
    await adminPage.locator('#chat-user-search-results button').first().click();
    await expect(adminPage.locator('#chat-storage-request')).toBeVisible({ timeout: 20000 });
    await expect(adminPage.locator('#chat-storage-request-info')).toContainText('1.0 GB');
    await expectInsideViewport(adminPage.locator('#support-chat-modal'), '#chat-storage-request');
    await expectNoHorizontalOverflow(adminPage.locator('#support-chat-modal'), '#chat-storage-request');
    await adminPage.locator('#chat-storage-quota-input').fill('2048');
    await adminPage.screenshot({ path: 'tests/test-results/cloud-storage-request-chat.png' });
    const approveResponse = adminPage.waitForResponse(r => r.url().includes('/apps/cloud/api.php') && r.request().method() === 'POST');
    await adminPage.locator('#chat-storage-approve').click();
    expect((await approveResponse).ok()).toBeTruthy();
    await expect(adminPage.locator('#chat-storage-request')).toBeHidden({ timeout: 15000 });

    // --- Broadcast notification is gone; quota applied ---
    const adminItems = await notificationItems(adminPage);
    expect(adminItems.some((n: any) => n.event_type === 'cloud_storage_request')).toBeFalsy();
    const overview = await cloudApi(adminPage, 'admin_get_storage_request', { email: normalUser });
    expect(overview.ok).toBeTruthy();
    expect(overview.pending).toBeFalsy();
    expect(overview.quota_mb).toBe(2048);

    // --- Requester sees the approval notification ---
    const userItems = await notificationItems(page);
    expect(userItems.some((n: any) => n.event_type === 'cloud_storage_request_resolved' && /approved/i.test(n.title))).toBeTruthy();

    await adminContext.close();
    // The forbidden admin API probe above intentionally returns 403, which the
    // browser logs as a failed resource — ignore that expected entry.
    expect(consoleErrors.filter(e => !/403/.test(e))).toEqual([]);
    expect(adminErrors).toEqual([]);
  });

  test('admin mini panel sets per-user quota, rejects with reason and validates the quota field', async ({ page, browser }) => {
    test.setTimeout(240000);
    seedUsers();

    // user asks for space
    await loginAs(page, normalUser);
    await openCloudApp(page);
    await cloudApi(page, 'request_storage', {}, 'POST');

    const adminContext = await browser.newContext({ baseURL: process.env.TEST_BASE_URL || 'http://localhost' });
    const adminPage = await adminContext.newPage();
    const adminErrors: string[] = [];
    adminPage.on('console', msg => { if (msg.type() === 'error') adminErrors.push(msg.text()); });
    adminPage.on('pageerror', err => adminErrors.push(err.message));
    await loginAs(adminPage, adminUser);
    const frame = await openCloudApp(adminPage);
    await frame.locator('#btn-settings').click();
    await expect(frame.locator('#settings-quota-row')).toBeVisible();
    await expect(frame.locator('#settings-storage-request')).toBeHidden();
    await frame.locator('#btn-manage-storage').click();
    await expect(frame.locator('#modal-storage-admin')).toBeVisible({ timeout: 15000 });
    await expect(frame.locator('#storage-admin-requests')).toContainText(normalUser, { timeout: 15000 });
    await expectInsideViewport(frame, '#modal-storage-admin .modal-content');
    await expectNoHorizontalOverflow(frame, '#modal-storage-admin .modal-body');
    await expectTableFillsModal(frame, '#modal-storage-admin .modal-content');

    // search narrows the (potentially huge) user list to the target user
    const searchResponse = adminPage.waitForResponse(r => r.url().includes('action=admin_storage_overview') && r.url().includes('q=' + encodeURIComponent(normalUser)));
    await frame.locator('#storage-admin-search').fill(normalUser);
    await searchResponse;
    const userRow = frame.locator('#storage-admin-table-body tr', { hasText: normalUser });
    await expect(userRow).toBeVisible({ timeout: 15000 });

    // set an individual quota for the user (icon-only save button)
    await userRow.locator('.storage-admin-quota-input').fill('3072');
    await expect(userRow.locator('.storage-admin-save-btn')).toHaveAttribute('aria-label', /quota/i);
    const saveResponse = adminPage.waitForResponse(r => r.request().method() === 'POST' && (r.request().postData() || '').includes('action=admin_set_user_quota'));
    const saveReRender = adminPage.waitForResponse(r => r.url().includes('action=admin_storage_overview'));
    await userRow.locator('.storage-admin-save-btn').click();
    const saveResp = await saveResponse;
    const saveReqBody = saveResp.request().postData() || '';
    expect(saveReqBody).toContain('quota_mb=3072');
    expect(saveReqBody).toContain(encodeURIComponent(normalUser));
    expect((await saveResp.json()).ok).toBeTruthy();
    const afterSave = await cloudApi(adminPage, 'admin_get_storage_request', { email: normalUser });
    expect(afterSave.quota_mb).toBe(3072);
    // wait for the panel to finish re-rendering after the save
    await saveReRender;
    await expect(frame.locator('#storage-admin-requests')).toContainText(normalUser, { timeout: 15000 });

    // validation: approving without a valid quota must not resolve the request
    await frame.locator('[data-req-quota]').fill('0');
    const badApprove = adminPage.waitForResponse(r => r.request().method() === 'POST' && (r.request().postData() || '').includes('action=admin_resolve_storage_request'), { timeout: 3000 }).catch(() => null);
    await frame.locator('[data-req-approve]').first().click();
    const badResp = await badApprove;
    // The client validates before sending; either it never sent, or the server rejected it.
    if (badResp) expect(badResp.ok()).toBeFalsy();
    const stillPending = await cloudApi(adminPage, 'admin_get_storage_request', { email: normalUser });
    expect(stillPending.pending).toBeTruthy();
    await expect(frame.locator('#storage-admin-requests')).toContainText(normalUser);

    // reject with a reason
    await frame.locator('[data-req-reason]').first().fill('Not enough capacity right now');
    const rejectResponse = adminPage.waitForResponse(r => r.request().method() === 'POST' && (r.request().postData() || '').includes('action=admin_resolve_storage_request'));
    await frame.locator('[data-req-reject]').first().click();
    const rejectJson = await (await rejectResponse).json();
    expect(rejectJson.ok).toBeTruthy();
    await expect(frame.locator('#storage-admin-requests')).not.toContainText(normalUser, { timeout: 15000 });

    // analytics window: donut chart of top-10 users + the rest
    await frame.locator('#btn-storage-analytics').click();
    await expect(frame.locator('#modal-storage-analytics')).toBeVisible({ timeout: 15000 });
    await expect(frame.locator('#storage-analytics-canvas')).toBeVisible();
    await expect(frame.locator('#storage-analytics-summary .storage-stat')).toHaveCount(4);
    await expectInsideViewport(frame, '#modal-storage-analytics .modal-content');
    const legendCount = await frame.locator('#storage-analytics-legend .storage-legend-item').count();
    const emptyCount = await frame.locator('#storage-analytics-legend .storage-analytics-empty').count();
    expect(legendCount + emptyCount).toBeGreaterThanOrEqual(1);
    if (legendCount > 0) {
      // the donut must actually be painted, not left blank
      const painted = await frame.locator('#storage-analytics-canvas').evaluate(canvas => {
        const ctx = (canvas as HTMLCanvasElement).getContext('2d');
        if (!ctx) return 0;
        const data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        let filled = 0;
        for (let i = 3; i < data.length; i += 4) { if (data[i] !== 0) { filled++; if (filled > 100) break; } }
        return filled;
      });
      expect(painted).toBeGreaterThan(0);
    }
    await adminPage.screenshot({ path: 'tests/test-results/cloud-storage-admin-analytics.png' });
    await frame.locator('#btn-storage-analytics-done').click();
    await expect(frame.locator('#modal-storage-analytics')).toBeHidden();

    await adminPage.screenshot({ path: 'tests/test-results/cloud-storage-admin-panel.png' });

    // requester gets a rejection notification
    const userItems = await notificationItems(page);
    expect(userItems.some((n: any) => n.event_type === 'cloud_storage_request_resolved' && /rejected/i.test(n.title))).toBeTruthy();

    await adminContext.close();
    expect(adminErrors).toEqual([]);
  });
});
