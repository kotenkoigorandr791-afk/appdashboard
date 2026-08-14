import { expect, test } from '@playwright/test';
import { autoAcceptCookies, expectNoCenterOverlaps, expectNoViewportOverflow, expectVisibleBox, login, skipWithoutAuth } from '../helpers/shell';

test.describe('authenticated application shell', () => {
  test.describe.configure({ mode: 'serial' });

  async function openSidebar(page) {
    await autoAcceptCookies(page);
    await page.evaluate(() => document.getElementById('menu-button')?.click());
    await expect(page.locator('#sidebar')).toHaveClass(/open/);
    await page.waitForFunction(() => {
      const sidebar = document.getElementById('sidebar');
      return !!sidebar && sidebar.getBoundingClientRect().left >= -1;
    });
  }

  async function closeSidebar(page) {
    await autoAcceptCookies(page);
    await page.evaluate(() => document.getElementById('menu-button')?.click());
    await expect(page.locator('#sidebar')).not.toHaveClass(/open/);
  }

  async function clickById(page, id: string) {
    await autoAcceptCookies(page);
    await page.evaluate((targetId) => document.getElementById(targetId)?.click(), id);
  }

  test.beforeEach(async ({ page }, testInfo) => {
    await skipWithoutAuth(testInfo);
    await login(page);
    await autoAcceptCookies(page);
    await page.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
    await autoAcceptCookies(page);
  });

  test('renders main shell, app grid and navigation drawer', async ({ page }) => {
    await expectVisibleBox(page.locator('#content'));
    await expectVisibleBox(page.locator('#app-grid'));
    await expect(page.locator('#app-grid .app-tile-link').first()).toBeVisible();
    await openSidebar(page);
    await expect(page.locator('#app-list .app-tile-link').first()).toBeVisible();
    await expect(page.locator('#settings-button')).toBeVisible();
    await closeSidebar(page);
  });

  test('filters sidebar apps and sanitizes search input', async ({ page }) => {
    await page.locator('#menu-button').dispatchEvent('click');
    await page.locator('.search-container').dispatchEvent('click');
    await expect(page.locator('.search-container')).toHaveClass(/open/);
    await page.locator('#search-bar').evaluate((input: HTMLInputElement) => {
      input.value = 'cloud!@#';
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });
    await expect(page.locator('#search-bar')).toHaveValue('cloud');
    const visibleItems = await page.locator('#app-list li').evaluateAll(items => items.filter(item => getComputedStyle(item).display !== 'none').length);
    expect(visibleItems).toBeGreaterThan(0);
    await page.locator('#search-bar').evaluate((input: HTMLInputElement) => {
      input.value = 'zzzzzzzzzz';
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });
    const noneVisible = await page.locator('#app-list li').evaluateAll(items => items.filter(item => getComputedStyle(item).display !== 'none').length);
    expect(noneVisible).toBe(0);
  });

  test('opens settings, select menus, wallpaper picker, shortcuts and QR modal', async ({ page }) => {
    await openSidebar(page);
    await clickById(page, 'settings-button');
    await expect(page.locator('#settings-modal')).toBeVisible();
    await page.locator('#lang-trigger').click();
    await expect(page.locator('#lang-dropdown')).toBeVisible();
    await page.locator('#theme-trigger').click();
    await expect(page.locator('#theme-dropdown')).toBeVisible();
    await page.locator('#wallpaper-open-btn').click();
    await expect(page.locator('#wallpaper-modal')).toBeVisible();
    await expect(page.locator('#wallpaper-picker .wallpaper-option').first()).toBeVisible();
    await page.locator('#close-wallpaper-modal').dispatchEvent('click');
    await clickById(page, 'shortcuts-help-button');
    await expect(page.locator('#shortcuts-modal')).toBeVisible();
    await clickById(page, 'close-shortcuts');
    await expect(page.locator('#shortcuts-modal')).not.toBeVisible();
    await clickById(page, 'settings-button');
    await expect(page.locator('#settings-modal')).toBeVisible();
    await clickById(page, 'qr-button');
    await expect(page.locator('#qr-modal')).toBeVisible();
    await expect(page.locator('#current-domain-url')).not.toBeEmpty();
    await page.locator('#close-qr').dispatchEvent('click');
    await clickById(page, 'close-settings');
    await expect(page.locator('#settings-modal')).not.toBeVisible();
  });

  test('opens profile, notifications, support chat and weather dialogs', async ({ page }) => {
    await openSidebar(page);
    await page.locator('#user-profile').dispatchEvent('click');
    await expect(page.locator('#profile-modal')).toBeVisible();
    await expect(page.locator('#profile-content')).toBeVisible();
    await page.locator('#close-profile').dispatchEvent('click');
    await page.locator('#notifications-btn').first().dispatchEvent('click');
    await expect(page.locator('#notifications-panel')).toHaveClass(/open/);
    await page.locator('#notifications-btn').first().dispatchEvent('click');
    await expect(page.locator('#notifications-panel')).not.toHaveClass(/open/);
    await page.locator('#support-chat-btn').first().dispatchEvent('click');
    await expect(page.locator('#support-chat-modal')).toBeVisible();
    await page.locator('#close-chat-modal').dispatchEvent('click');
    await page.locator('#weatherWidget').dispatchEvent('click');
    await expect(page.locator('#weatherModalOverlay')).toBeVisible();
    await page.locator('#weatherSettingsBtn').dispatchEvent('click');
    await expect(page.locator('#weatherSettingsModalOverlay')).toBeVisible();
    await page.locator('#weatherSettingsModalOverlay .location-switch button').nth(1).dispatchEvent('click');
    await expect(page.locator('#weatherCitySelection')).toBeVisible();
    await page.locator('#weatherSettingsModalOverlay .weather-modal-close').dispatchEvent('click');
    await page.locator('#weatherModalOverlay .weather-modal-close').dispatchEvent('click');
  });

  test('supports keyboard shortcuts and layout constraints', async ({ page }) => {
    await page.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
    await autoAcceptCookies(page);
    await page.waitForFunction(() => !document.getElementById('cookie-consent-overlay') || document.getElementById('cookie-consent-overlay').style.display === 'none', { timeout: 10000 });
    await page.keyboard.press('/');
    await expect(page.locator('#sidebar')).toHaveClass(/open/);
    await expect(page.locator('#search-bar')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(page.locator('#sidebar')).not.toHaveClass(/open/);
    await page.keyboard.press('I');
    await expect(page.locator('#shortcuts-modal')).toBeVisible();
    await clickById(page, 'close-shortcuts');
    await expect(page.locator('#shortcuts-modal')).not.toBeVisible();
    await page.keyboard.press('ArrowRight');
    await expect(page.locator('#app-grid .app-tile-key-selected')).toHaveCount(1);
    await expectNoViewportOverflow(page, '#top-bar, #menu-button, #sidebar, #notifications-btn, #support-chat-btn');
    await expectNoCenterOverlaps(page, '#menu-button, #notifications-btn, #support-chat-btn');
  });
});
