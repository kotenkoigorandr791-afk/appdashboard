import { expect, test, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { autoAcceptCookies, expectNoCenterOverlaps, expectNoViewportOverflow } from './helpers/shell';

const userA = process.env.MESSENGER_USER_EMAIL || 'codex-peer1@example.com';
const userB = process.env.MESSENGER_PEER_EMAIL || 'codex-peer2@example.com';
const adminUser = process.env.MESSENGER_ADMIN_EMAIL || 'codex-admin@example.com';
const adminPeer = process.env.MESSENGER_ADMIN_PEER_EMAIL || 'codex-admin-peer@example.com';
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

function seedMessengerUsers() {
  const php = `
define("APP_ROOT", getcwd());
require "db.php";
$db = getDB();
initDB($db);
$a = "${userA}";
$b = "${userB}";
$admin = "${adminUser}";
$adminPeer = "${adminPeer}";
upsertUser($a, ["name"=>"Codex Peer One","password"=>appPasswordHash("${password}"),"role"=>1,"verified"=>true,"language"=>"en","theme"=>"light","color"=>"#2563eb"]);
upsertUser($b, ["name"=>"Codex Peer Two","password"=>appPasswordHash("${password}"),"role"=>1,"verified"=>true,"language"=>"en","theme"=>"light","color"=>"#2563eb"]);
upsertUser($admin, ["name"=>"Codex Admin","password"=>appPasswordHash("${password}"),"role"=>2,"verified"=>true,"language"=>"en","theme"=>"light","color"=>"#2563eb"]);
upsertUser($adminPeer, ["name"=>"Codex Admin Peer","password"=>appPasswordHash("${password}"),"role"=>2,"verified"=>true,"language"=>"en","theme"=>"light","color"=>"#2563eb"]);
[$u,$r] = strcmp($a,$b) <= 0 ? [$a,$b] : [$b,$a];
$stmt = $db->prepare("DELETE FROM support_chat WHERE (chat_type = 'peer' AND user_email = :u AND recipient_email = :r) OR user_email IN (:a,:b,:c)");
$stmt->bindValue(":u", $u, PDO::PARAM_STR);
$stmt->bindValue(":r", $r, PDO::PARAM_STR);
$stmt->bindValue(":a", $a, PDO::PARAM_STR);
$stmt->bindValue(":b", $b, PDO::PARAM_STR);
$stmt->bindValue(":c", $adminPeer, PDO::PARAM_STR);
$stmt->execute();
$stmt = $db->prepare("DELETE FROM chat_blocks WHERE blocker_email IN (:a,:b) OR blocked_email IN (:a2,:b2)");
$stmt->bindValue(":a", $a, PDO::PARAM_STR);
$stmt->bindValue(":b", $b, PDO::PARAM_STR);
$stmt->bindValue(":a2", $a, PDO::PARAM_STR);
$stmt->bindValue(":b2", $b, PDO::PARAM_STR);
$stmt->execute();
$stmt = $db->prepare("DELETE FROM chat_hidden WHERE email IN (:a,:b) OR user_email IN (:a2,:b2) OR recipient_email IN (:a3,:b3)");
$stmt->bindValue(":a", $a, PDO::PARAM_STR);
$stmt->bindValue(":b", $b, PDO::PARAM_STR);
$stmt->bindValue(":a2", $a, PDO::PARAM_STR);
$stmt->bindValue(":b2", $b, PDO::PARAM_STR);
$stmt->bindValue(":a3", $a, PDO::PARAM_STR);
$stmt->bindValue(":b3", $b, PDO::PARAM_STR);
$stmt->execute();
$stmt = $db->prepare("INSERT INTO support_chat (user_email, chat_type, sender_email, message, created_at) VALUES (:u, 'support', :s, 'admin support seed', :ts)");
$stmt->bindValue(":u", $a, PDO::PARAM_STR);
$stmt->bindValue(":s", $a, PDO::PARAM_STR);
$stmt->bindValue(":ts", time(), PDO::PARAM_INT);
$stmt->execute();
	`;
  execFileSync('docker', ['exec', 'appdashboard', 'php', '-r', php], { stdio: 'pipe' });
}

test.describe('messenger chat', () => {
  test.describe.configure({ mode: 'serial' });

  test.beforeAll(() => {
    seedMessengerUsers();
  });

  test('starts peer chat by full email and supports send block unblock delete', async ({ page }) => {
    test.setTimeout(120000);
    const consoleErrors: string[] = [];
    page.on('console', msg => {
      if (msg.type() === 'error') consoleErrors.push(msg.text());
    });
    page.on('pageerror', err => consoleErrors.push(err.message));
    await loginAs(page, userA);
    await page.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
    await autoAcceptCookies(page);
    await page.locator('#support-chat-btn').click();
    await expect(page.locator('#support-chat-modal')).toBeVisible();
    await page.screenshot({ path: 'test-results/artifacts/messenger-list.png' });
    await expect(page.locator('#chat-list-container')).toContainText('Priority support');
    await expect(page.locator('#chat-list-container')).toContainText('People');
    await expect(page.locator('#chat-modal-title')).toContainText('Support');
    await page.locator('#new-chat-btn').click();
    await expect(page.locator('#chat-user-search-input')).toHaveAttribute('type', 'email');
    await page.locator('#chat-user-search-input').fill(userB.slice(0, 8));
    await expect(page.locator('#chat-user-search-results')).toContainText('No users found');
    await page.locator('#chat-user-search-input').fill(userB);
    await expect(page.locator('#chat-user-search-results')).toContainText(userB);
    await page.locator('#chat-user-search-results button').first().click();
    await expect(page.locator('#chat-modal-title')).toContainText('Codex Peer Two');
    await expect(page.locator('#chat-header-avatar')).toBeVisible();
    const headerMetrics = await page.locator('#delete-chat-btn').evaluate(deleteBtn => {
      const closeBtn = document.getElementById('close-chat-modal')!;
      const del = deleteBtn as HTMLElement;
      const close = closeBtn as HTMLElement;
      const delStyle = getComputedStyle(del);
      const closeStyle = getComputedStyle(close);
      const closeRect = close.getBoundingClientRect();
      return {
        deleteDisplay: delStyle.display,
        deleteFitsOneLine: del.scrollHeight <= del.clientHeight + 1,
        closeWidth: closeRect.width,
        closeHeight: closeRect.height,
        closeRadius: parseFloat(closeStyle.borderTopLeftRadius)
      };
    });
    expect(['flex', 'inline-flex']).toContain(headerMetrics.deleteDisplay);
    expect(headerMetrics.deleteFitsOneLine).toBe(true);
    expect(Math.abs(headerMetrics.closeWidth - headerMetrics.closeHeight)).toBeLessThanOrEqual(1);
    expect(headerMetrics.closeRadius).toBeGreaterThanOrEqual(headerMetrics.closeWidth / 2 - 1);
    await page.screenshot({ path: 'test-results/artifacts/messenger-peer.png' });
    await page.locator('#chat-input').fill('hello peer from playwright');
    await page.locator('#chat-send-btn').click();
    await expect(page.locator('#chat-messages')).toContainText('hello peer from playwright');
    await page.screenshot({ path: 'test-results/artifacts/messenger-sent.png' });
    await page.locator('#block-chat-btn').click();
    await expect(page.locator('#block-chat-btn')).toHaveText('Unblock');
    await expect(page.locator('#chat-input')).toBeDisabled();
    await page.locator('#block-chat-btn').click();
    await expect(page.locator('#block-chat-btn')).toHaveText('Block');
    await expect(page.locator('#chat-input')).toBeEnabled();
    await page.locator('#delete-chat-btn').click();
    await expect(page.locator('#support-chat-delete-modal')).toBeVisible();
    await page.locator('#support-chat-delete-confirm').click();
    await expect(page.locator('#chat-list-container')).not.toContainText(userB);
    await expectNoViewportOverflow(page, '#support-chat-modal, #admin-chat-list, #chat-messages-container, #chat-composer-row');
    await expectNoCenterOverlaps(page, '#new-chat-btn, #delete-chat-btn, #close-chat-modal');
    expect(consoleErrors).toEqual([]);
  });

	  test('uses list and chat phases on mobile chromium', async ({ page }) => {
    test.setTimeout(120000);
    await page.setViewportSize({ width: 390, height: 844 });
    await loginAs(page, userA);
    await page.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
    await autoAcceptCookies(page);
    await page.locator('#support-chat-btn').click();
    await expect(page.locator('#admin-chat-list')).toBeVisible();
    await expect(page.locator('#chat-messages-container')).not.toBeVisible();
    await page.locator('.chat-list-row').first().click();
    await expect(page.locator('#chat-messages-container')).toBeVisible();
    await expect(page.locator('#admin-chat-list')).not.toBeVisible();
    await page.locator('#chat-admin-mobile-back').click();
    await expect(page.locator('#admin-chat-list')).toBeVisible();
    await page.screenshot({ path: 'test-results/artifacts/messenger-mobile-list.png' });
	  });

  test('admin can choose send identity and delete keeps messenger open', async ({ page }) => {
    test.setTimeout(120000);
    const consoleErrors: string[] = [];
    page.on('console', msg => {
      if (msg.type() === 'error') consoleErrors.push(msg.text());
    });
    page.on('pageerror', err => consoleErrors.push(err.message));
    await loginAs(page, adminUser);
    await page.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
    await autoAcceptCookies(page);
    await page.locator('#support-chat-btn').click();
    await expect(page.locator('#support-chat-modal')).toBeVisible();
    await page.locator('#new-chat-btn').click();
    await expect(page.locator('#chat-type-modal')).toBeVisible();
    await page.locator('#chat-type-peer').click();
    await expect(page.locator('#chat-user-search-results button').first()).toBeVisible();
    await expect(page.locator('#chat-user-search-results img').first()).toBeVisible();
    await expect(page.locator('#chat-user-search-input')).toHaveAttribute('type', 'text');
    await page.locator('#chat-user-search-input').fill(adminPeer);
    await expect(page.locator('#chat-user-search-results')).toContainText(adminPeer);
    await page.locator('#chat-user-search-results button').first().click();
    await expect(page.locator('#admin-chat-send-as')).toBeHidden();
    await page.locator('#chat-input').fill('admin wrote as user');
    await page.route('**/main?api=support_chat', async route => {
      const req = route.request();
      if (req.method() === 'POST' && (req.postData() || '').includes('send_message')) {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ ok: false, error: 'permission_denied' })
        });
        return;
      }
      await route.continue();
    });
    await page.locator('#chat-send-btn').click();
    await expect(page.locator('#fm-alert-modal')).toBeVisible();
    await expect(page.locator('#fm-alert-msg')).toContainText('permission_denied');
    await page.locator('#fm-alert-ok').click();
    await page.unroute('**/main?api=support_chat');
    await page.locator('#chat-input').fill('admin wrote as user');
    await page.locator('#chat-send-btn').click();
    await expect(page.locator('#chat-messages')).toContainText('admin wrote as user');
    await page.locator('#new-chat-btn').click();
    await expect(page.locator('#chat-type-modal')).toBeVisible();
    await page.locator('#chat-type-support').click();
    await page.locator('#chat-user-search-input').fill(adminPeer);
    await expect(page.locator('#chat-user-search-results')).toContainText(adminPeer);
    await page.locator('#chat-user-search-results button').first().click();
    await expect(page.locator('#admin-chat-send-as')).toBeHidden();
    await page.locator('#chat-input').fill('admin wrote as support');
    await page.locator('#chat-send-btn').click();
    await expect(page.locator('#chat-messages')).toContainText('admin wrote as support');
    await page.locator('#delete-chat-btn').click();
    await expect(page.locator('#support-chat-delete-modal')).toBeVisible();
    await page.locator('#support-chat-delete-confirm').click();
    await expect(page.locator('#support-chat-modal')).toBeVisible();
    await expect(page.locator('#admin-chat-list')).toBeVisible();
    await page.screenshot({ path: 'test-results/artifacts/messenger-admin-delete.png' });
    await expectNoViewportOverflow(page, '#support-chat-modal, #admin-chat-list, #chat-messages-container, #chat-composer-row');
    await expectNoCenterOverlaps(page, '#new-chat-btn, #delete-chat-btn, #close-chat-modal');
    expect(consoleErrors).toEqual([]);
  });
	});
