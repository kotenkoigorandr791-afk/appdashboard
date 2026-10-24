import { expect, test, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { autoAcceptCookies } from '../helpers/shell';

const userEmail = process.env.SHELL_CONTROLS_USER_EMAIL || 'codex-shell-controls@example.com';
const password = process.env.SHELL_CONTROLS_USER_PASSWORD || 'CodexPass1';

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

function seedUser() {
  const php = `
define("APP_ROOT", getcwd());
require "db.php";
$db = getDB();
initDB($db);
upsertUser("${userEmail}", ["name"=>"Codex Shell Controls","password"=>appPasswordHash("${password}"),"role"=>1,"verified"=>true,"language"=>"en","theme"=>"light","color"=>"#2563eb"]);
`;
  execFileSync('docker', ['exec', 'appdashboard', 'php', '-r', php], { stdio: 'pipe' });
}

test.describe('app page shell controls', () => {
  test.beforeAll(seedUser);

  test('keeps desktop app menu usable and puts chat plus mcp controls in shell', async ({ page }) => {
    test.setTimeout(120000);
    const consoleErrors: string[] = [];
    page.on('console', msg => {
      if (msg.type() === 'error') consoleErrors.push(msg.text());
    });
    page.on('pageerror', err => consoleErrors.push(err.message));
    await loginAs(page, userEmail);
    await page.goto('/main?app=cloud&lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
    await autoAcceptCookies(page);
    await expect(page.locator('#app-iframe')).toBeVisible({ timeout: 30000 });
    const chatButton = page.locator('#bottom-bar #support-chat-btn.app-page-chat');
    const menuButton = page.locator('#menu-button');
    await expect(chatButton).toHaveAttribute('aria-label', 'Chat');
    await expect(chatButton).toHaveClass(/sidebar-button/);
    await expect(chatButton).not.toHaveClass(/support-chat-floating/);
    const collapsedMetrics = await page.evaluate(() => {
      const chat = document.querySelector('#support-chat-btn.app-page-chat')!.getBoundingClientRect();
      return { chatRight: chat.right };
    });
    expect(collapsedMetrics.chatRight).toBeLessThan(0);
    await page.screenshot({ path: 'test-results/artifacts/shell-app-page-collapsed-chat.png' });
    await menuButton.click();
    await expect(chatButton).toBeVisible();
    const openMetrics = await page.evaluate(() => {
      const chat = document.querySelector('#bottom-bar #support-chat-btn.app-page-chat')!.getBoundingClientRect();
      const search = document.querySelector('.search-container')!.getBoundingClientRect();
      const style = getComputedStyle(document.querySelector('#bottom-bar #support-chat-btn.app-page-chat')!);
      return {
        chatLeft: chat.left,
        searchRight: search.right,
        sameRow: Math.abs((chat.top + chat.height / 2) - (search.top + search.height / 2)) <= 2,
        background: style.backgroundColor,
        shadow: style.boxShadow
      };
    });
    expect(openMetrics.chatLeft).toBeGreaterThanOrEqual(openMetrics.searchRight);
    expect(openMetrics.sameRow).toBe(true);
    expect(openMetrics.background).toBe('rgba(0, 0, 0, 0)');
    expect(openMetrics.shadow).toBe('none');
    await page.locator('.search-container').click();
    await expect(chatButton).toBeHidden();
    await page.mouse.move(200, 200);
    await page.locator('#app-list-container').click({ position: { x: 120, y: 120 } });
    await expect(chatButton).toBeVisible();
    const appList = page.locator('#app-list-container');
    const scrollMetrics = await appList.evaluate(el => {
      const container = el as HTMLElement;
      container.scrollTop = 0;
      return {
        overflowY: getComputedStyle(container).overflowY,
        scrollTop: container.scrollTop,
        mask: getComputedStyle(container).maskImage || getComputedStyle(container).webkitMaskImage
      };
    });
    expect(scrollMetrics.overflowY).toBe('auto');
    expect(scrollMetrics.scrollTop).toBe(0);
    expect(scrollMetrics.mask).toBe('none');
    await page.screenshot({ path: 'test-results/artifacts/shell-app-page-sidebar.png' });
    await page.keyboard.press('Escape');
    await page.keyboard.press('M');
    await expect(page.locator('#mcp-setup-modal')).toBeVisible();
    await expect(page.locator('#mcp-setup-modal .mcp-setup-tab[data-mcp-tab="generate"]')).toHaveClass(/active/);
    await page.locator('#mcp-setup-modal .mcp-setup-tab[data-mcp-tab="tokens"]').click();
    await expect(page.locator('#mcp-setup-modal [data-mcp-pane="tokens"] .mcp-token-table')).toBeVisible();
    await expect(page.locator('#profile-modal .mcp-token-table')).toHaveCount(0);
    await page.screenshot({ path: 'test-results/artifacts/shell-app-page-mcp-tokens.png' });
    await page.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#bottom-bar #support-chat-btn.app-page-chat')).toHaveCount(0);
    await expect(page.locator('#support-chat-btn:not(.app-page-chat)')).toBeVisible();
    expect(consoleErrors).toEqual([]);
  });
});
