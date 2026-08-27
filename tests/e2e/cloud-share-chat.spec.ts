import { expect, test, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { autoAcceptCookies } from './helpers/shell';

const adminUser = process.env.MESSENGER_ADMIN_EMAIL || 'codex-admin@example.com';
const targetUser = process.env.CLOUD_SHARE_PEER_EMAIL || 'codex-cloud-peer@example.com';
const password = process.env.MESSENGER_USER_PASSWORD || 'CodexPass1';

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
upsertUser("${adminUser}", ["name"=>"Codex Admin","password"=>appPasswordHash("${password}"),"role"=>2,"verified"=>true,"language"=>"en","theme"=>"light","color"=>"#2563eb"]);
upsertUser("${targetUser}", ["name"=>"Codex Peer Two","password"=>appPasswordHash("${password}"),"role"=>1,"verified"=>true,"language"=>"en","theme"=>"light","color"=>"#2563eb"]);
$stmt = $db->prepare("DELETE FROM support_chat WHERE user_email = :target OR sender_email IN (:admin, :target2)");
$stmt->bindValue(":target", "${targetUser}", PDO::PARAM_STR);
$stmt->bindValue(":admin", "${adminUser}", PDO::PARAM_STR);
$stmt->bindValue(":target2", "${targetUser}", PDO::PARAM_STR);
$stmt->execute();
`;
  execFileSync('docker', ['exec', 'appdashboard', 'php', '-r', php], { stdio: 'pipe' });
}

test.describe('cloud share to chat', () => {
  test.beforeAll(seedUsers);

  test('admin sends internal cloud attachment that recipient imports into temp storage', async ({ page, browser }) => {
    test.setTimeout(180000);
    const consoleErrors: string[] = [];
    page.on('console', msg => {
      if (msg.type() === 'error') consoleErrors.push(msg.text());
    });
    page.on('pageerror', err => consoleErrors.push(err.message));
    await loginAs(page, adminUser);
    await page.goto('/main?app=cloud&lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
    await page.locator('#cookie-consent-overlay').evaluate(el => el.remove()).catch(() => {});
    await page.waitForSelector('#app-iframe', { timeout: 30000 });
    const frame = page.frameLocator('#app-iframe');
    await frame.locator('#cloud-encryption-notice-confirm').click({ timeout: 5000 }).catch(() => {});
    await frame.locator('#btn-share').waitFor({ state: 'visible', timeout: 30000 });
    const filePath = `codex-cloud-share-${Date.now()}.txt`;
    const fileContent = `internal attachment preview ${Date.now()}`;
    await frame.locator('body').evaluate(async (_body, data) => {
      const { path, content } = data as { path: string; content: string };
      const params = new URLSearchParams({ action: 'create_file', path, root: 'main' });
      await fetch((window as any).APP_CONFIG.apiBase, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params });
      const saveParams = new URLSearchParams({ action: 'save_file', path, content, root: 'main' });
      await fetch((window as any).APP_CONFIG.apiBase, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: saveParams });
      document.getElementById('viewer')!.dataset.path = path;
    }, { path: filePath, content: fileContent });
    await frame.locator('#btn-share').click();
    await expect(frame.getByText('Get link', { exact: true })).toBeVisible();
    await frame.getByText('Send to chat', { exact: true }).click();
    await frame.locator('#cloud-share-chat-email').fill(targetUser);
    await page.screenshot({ path: 'test-results/artifacts/cloud-share-chat-admin.png' });
    await frame.locator('#cloud-share-chat-send').click();
    await expect(frame.locator('#cloud-share-chat-modal')).toBeHidden({ timeout: 15000 });
    const recipientContext = await browser.newContext({ baseURL: process.env.TEST_BASE_URL || 'http://localhost' });
    const recipientPage = await recipientContext.newPage();
    await loginAs(recipientPage, targetUser);
    await recipientPage.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
    await recipientPage.locator('#cookie-consent-overlay').evaluate(el => el.remove()).catch(() => {});
    await recipientPage.locator('#support-chat-btn').click();
    await expect(recipientPage.locator('.chat-cloud-attachment').last()).toContainText(filePath, { timeout: 10000 });
    await expect(recipientPage.locator('#chat-messages')).toContainText(fileContent);
    await expect(recipientPage.locator('#chat-messages')).not.toContainText('sharedFile');
    await recipientPage.screenshot({ path: 'test-results/artifacts/cloud-share-chat-recipient-attachment.png' });
    await recipientPage.locator('.chat-cloud-attachment').last().click();
    await recipientPage.waitForURL(url => url.pathname.includes('/main') && url.searchParams.get('app') === 'cloud', { timeout: 30000 });
    await recipientPage.waitForSelector('#app-iframe', { timeout: 30000 });
    const recipientFrame = recipientPage.frameLocator('#app-iframe');
    await recipientFrame.locator('#cloud-encryption-notice-confirm').click({ timeout: 5000 }).catch(() => {});
    await expect(recipientFrame.getByText(filePath).first()).toBeVisible({ timeout: 30000 });
    await expect(recipientFrame.getByText(fileContent).first()).toBeVisible({ timeout: 30000 });
    await recipientPage.screenshot({ path: 'test-results/artifacts/cloud-share-chat-recipient-cloud.png' });
    await recipientContext.close();
    expect(consoleErrors).toEqual([]);
  });
});
