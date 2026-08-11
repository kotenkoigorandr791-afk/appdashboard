import { expect, test } from '@playwright/test';
import { autoAcceptCookies, expectNoCenterOverlaps, expectNoViewportOverflow, expectVisibleBox, login, skipWithoutAuth } from '../helpers/shell';

test.describe('authenticated application shell', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    await skipWithoutAuth(testInfo);
    await login(page);
    await autoAcceptCookies(page);
    await page.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
  });

  test('renders main shell, app grid and navigation drawer', async ({ page }) => {
    await expectVisibleBox(page.locator('#content'));
    await expectVisibleBox(page.locator('#app-grid'));
    await expect(page.locator('#app-grid .app-tile-link').first()).toBeVisible();
    await page.locator('#menu-button').click();
    await expect(page.locator('#sidebar')).toHaveClass(/open/);
    await expect(page.locator('#app-list .app-tile-link').first()).toBeVisible();
    await expect(page.locator('#settings-button')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.locator('#sidebar')).not.toHaveClass(/open/);
  });

  test('filters sidebar apps and sanitizes search input', async ({ page }) => {
    await page.locator('#menu-button').click();
    await page.locator('#search-bar').fill('cloud!@#');
    await expect(page.locator('#search-bar')).toHaveValue('cloud');
    const visibleItems = await page.locator('#app-list li').evaluateAll(items => items.filter(item => getComputedStyle(item).display !== 'none').length);
    expect(visibleItems).toBeGreaterThan(0);
    await page.locator('#search-bar').fill('zzzzzzzzzz');
    const noneVisible = await page.locator('#app-list li').evaluateAll(items => items.filter(item => getComputedStyle(item).display !== 'none').length);
    expect(noneVisible).toBe(0);
  });

  test('opens settings, select menus, wallpaper picker, shortcuts and QR modal', async ({ page }) => {
    await page.locator('#menu-button').click();
    await page.locator('#settings-button').click();
    await expect(page.locator('#settings-modal')).toBeVisible();
    await page.locator('#lang-trigger').click();
    await expect(page.locator('#lang-dropdown')).toBeVisible();
    await page.locator('#theme-trigger').click();
    await expect(page.locator('#theme-dropdown')).toBeVisible();
    await page.locator('#wallpaper-open-btn').click();
    await expect(page.locator('#wallpaper-modal')).toBeVisible();
    await expect(page.locator('#wallpaper-picker .wallpaper-option').first()).toBeVisible();
    await page.locator('#close-wallpaper-modal').click();
    await page.locator('#shortcuts-help-button').click();
    await expect(page.locator('#shortcuts-modal')).toBeVisible();
    await page.locator('#close-shortcuts').click();
    await page.locator('#qr-button').click();
    await expect(page.locator('#qr-modal')).toBeVisible();
    await expect(page.locator('#current-domain-url')).not.toBeEmpty();
    await page.locator('#close-qr').click();
    await page.locator('#close-settings').click();
    await expect(page.locator('#settings-modal')).not.toBeVisible();
  });

  test('opens profile, notifications, support chat and weather dialogs', async ({ page }) => {
    await page.locator('#menu-button').click();
    await page.locator('#user-profile').click();
    await expect(page.locator('#profile-modal')).toBeVisible();
    await expect(page.locator('#profile-content')).toBeVisible();
    await page.locator('#close-profile').click();
    await page.locator('#notifications-btn').first().click();
    await expect(page.locator('#notifications-panel')).toHaveClass(/open/);
    await page.locator('#notifications-btn').first().click();
    await expect(page.locator('#notifications-panel')).not.toHaveClass(/open/);
    await page.locator('#support-chat-btn').first().click();
    await expect(page.locator('#support-chat-modal')).toBeVisible();
    await page.locator('#support-chat-close').click();
    await page.locator('#weatherWidget').click();
    await expect(page.locator('#weatherModalOverlay')).toBeVisible();
    await page.locator('#weatherSettingsBtn').click();
    await expect(page.locator('#weatherSettingsModalOverlay')).toBeVisible();
    await page.locator('#weatherSettingsModalOverlay .location-switch button').nth(1).click();
    await expect(page.locator('#weatherCitySelection')).toBeVisible();
    await page.locator('#weatherSettingsModalOverlay .weather-modal-close').click();
    await page.locator('#weatherModalOverlay .weather-modal-close').click();
  });

  test('supports keyboard shortcuts and layout constraints', async ({ page }) => {
    await page.keyboard.press('/');
    await expect(page.locator('#sidebar')).toHaveClass(/open/);
    await expect(page.locator('#search-bar')).toBeFocused();
    await page.keyboard.press('I');
    await expect(page.locator('#shortcuts-modal')).toBeVisible();
    await page.locator('#close-shortcuts').click();
    await page.keyboard.press('ArrowRight');
    await expect(page.locator('#app-grid .app-tile-key-selected')).toHaveCount(1);
    await expectNoViewportOverflow(page, '#top-bar, #menu-button, #sidebar, #app-grid .app-tile-link, #notifications-btn, #support-chat-btn');
    await expectNoCenterOverlaps(page, '#menu-button, #notifications-btn, #support-chat-btn');
  });
});
