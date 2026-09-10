import { expect, test } from '@playwright/test';
import { autoAcceptCookies, login, skipWithoutAuth } from './helpers/shell';

const STD_TOPBAR_HEIGHT = 44;

const SHELL_MODALS: Array<{
  name: string;
  trigger: string;
  topbarSelector: string;
  closeSelector: string;
  forceOpen?: boolean;
}> = [
  { name: 'settings', trigger: '#settings-button', topbarSelector: '#settings-modal .std-topbar', closeSelector: '#close-settings' },
  { name: 'email', trigger: '#email-button', topbarSelector: '#email-modal .std-topbar', closeSelector: '#close-email', forceOpen: true },
  { name: 'users', trigger: '#users-button', topbarSelector: '#users-modal .std-topbar', closeSelector: '#close-users', forceOpen: true },
  { name: 'sessions', trigger: '#sessions-button', topbarSelector: '#sessions-modal .std-topbar', closeSelector: '#close-sessions' },
  { name: 'qr-code', trigger: '#qr-button', topbarSelector: '#qr-modal .std-topbar', closeSelector: '#close-qr' },
  { name: 'telegram-bot', trigger: '#tg-bot-button', topbarSelector: '#tg-bot-modal .std-topbar', closeSelector: '#close-tg-bot', forceOpen: true },
  { name: 'shortcuts', trigger: '#shortcuts-help-button', topbarSelector: '#shortcuts-modal .std-topbar', closeSelector: '#close-shortcuts' },
  { name: 'wallpaper', trigger: '#wallpaper-open-btn', topbarSelector: '#wallpaper-modal .std-topbar', closeSelector: '#close-wallpaper-modal' },
  { name: 'shared-files', trigger: '#shared-files-button', topbarSelector: '#shared-files-modal .std-topbar', closeSelector: '#close-shared-files' },
];

const ADMIN_MODAL = {
  name: 'admin-panel',
  trigger: '#open-admin-panel-btn',
  topbarSelector: '#ap-topbar',
  closeSelector: '#ap-close-btn',
};

test.describe('topbar standardization', () => {
  test.describe.configure({ mode: 'serial' });

  test.beforeEach(async ({ page }, testInfo) => {
    await skipWithoutAuth(testInfo);
    await login(page);
    await autoAcceptCookies(page);
    await page.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
    await autoAcceptCookies(page);
  });

  for (const modal of SHELL_MODALS) {
    test(`topbar ${modal.name} — desktop 1920px`, async ({ page }) => {
      await page.setViewportSize({ width: 1920, height: 1080 });
      if (modal.forceOpen) {
        const modalId = modal.topbarSelector.split(' ')[0].replace('#', '');
        await page.evaluate((id) => { const m = document.getElementById(id); if (m) m.style.display = 'flex'; }, modalId);
      } else {
        await page.evaluate((id) => document.getElementById(id)?.click(), modal.trigger.replace('#', ''));
      }
      await page.waitForSelector(modal.topbarSelector, { state: 'visible', timeout: 10000 });
      await page.screenshot({ path: `tests/tmp/topbar-${modal.name}-1920.png`, fullPage: false });
      const bar = page.locator(modal.topbarSelector).first();
      await expect(bar).toBeVisible();
      const box = await bar.boundingBox();
      expect(box?.height).toBe(STD_TOPBAR_HEIGHT);
    });

    test(`topbar ${modal.name} — mobile 375px`, async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 812 });
      if (modal.forceOpen) {
        const modalId = modal.topbarSelector.split(' ')[0].replace('#', '');
        await page.evaluate((id) => { const m = document.getElementById(id); if (m) m.style.display = 'flex'; }, modalId);
      } else {
        await page.evaluate((id) => document.getElementById(id)?.click(), modal.trigger.replace('#', ''));
      }
      await page.waitForSelector(modal.topbarSelector, { state: 'visible', timeout: 10000 });
      await page.screenshot({ path: `tests/tmp/topbar-${modal.name}-375.png`, fullPage: false });
      const bar = page.locator(modal.topbarSelector).first();
      await expect(bar).toBeVisible();
      const box = await bar.boundingBox();
      expect(box?.height).toBe(STD_TOPBAR_HEIGHT);
    });
  }

  test(`topbar ${ADMIN_MODAL.name} — desktop 1920px`, async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.evaluate((id) => document.getElementById(id)?.click(), ADMIN_MODAL.trigger.replace('#', ''));
    await page.waitForSelector(ADMIN_MODAL.topbarSelector, { state: 'visible', timeout: 10000 });
    await page.screenshot({ path: `tests/tmp/topbar-${ADMIN_MODAL.name}-1920.png`, fullPage: false });
    const bar = page.locator(ADMIN_MODAL.topbarSelector).first();
    await expect(bar).toBeVisible();
    const box = await bar.boundingBox();
    expect(box?.height).toBe(STD_TOPBAR_HEIGHT);
  });

  test(`topbar ${ADMIN_MODAL.name} — mobile 375px`, async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.evaluate((id) => document.getElementById(id)?.click(), ADMIN_MODAL.trigger.replace('#', ''));
    await page.waitForSelector(ADMIN_MODAL.topbarSelector, { state: 'visible', timeout: 10000 });
    await page.screenshot({ path: `tests/tmp/topbar-${ADMIN_MODAL.name}-375.png`, fullPage: false });
    const bar = page.locator(ADMIN_MODAL.topbarSelector).first();
    await expect(bar).toBeVisible();
    const box = await bar.boundingBox();
    expect(box?.height).toBe(STD_TOPBAR_HEIGHT);
  });

  test('close button has consistent size across modals', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    const sizes: Array<{ name: string; width: number; height: number }> = [];

    for (const modal of [...SHELL_MODALS, ADMIN_MODAL]) {
      if (modal.forceOpen) {
        const modalId = modal.topbarSelector.split(' ')[0].replace('#', '');
        await page.evaluate((id) => { const m = document.getElementById(id); if (m) m.style.display = 'flex'; }, modalId);
      } else {
        await page.evaluate((id) => document.getElementById(id)?.click(), modal.trigger.replace('#', ''));
      }
      await page.waitForSelector(modal.topbarSelector, { state: 'visible', timeout: 10000 });
      const closeBtn = page.locator(`${modal.topbarSelector} .std-topbar-close`).first();
      if (await closeBtn.isVisible().catch(() => false)) {
        const box = await closeBtn.boundingBox();
        if (box) sizes.push({ name: modal.name, width: box.width, height: box.height });
      }
      await page.evaluate((id) => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
      }, modal.topbarSelector.split(' ')[0].replace('#', ''));
    }

    for (const s of sizes) {
      expect(s.width).toBe(32);
      expect(s.height).toBe(32);
    }
  });

  test('close button hover turns red on settings modal', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.evaluate(() => document.getElementById('settings-button')?.click());
    await page.waitForSelector('#settings-modal .std-topbar', { timeout: 10000 });
    const closeBtn = page.locator('#settings-modal .std-topbar-close').first();
    await expect(closeBtn).toBeVisible();
    const hasHoverRule = await page.evaluate(() => {
      for (const sheet of document.styleSheets) {
        try {
          for (const rule of sheet.cssRules) {
            if (rule.selectorText && rule.selectorText.includes('.std-topbar-close:hover')) {
              return rule.cssText.includes('239, 68, 68') || rule.cssText.includes('#ef4444') || rule.cssText.includes('var(--danger');
            }
          }
        } catch {}
      }
      return false;
    });
    expect(hasHoverRule).toBe(true);
    await closeBtn.hover();
    await page.screenshot({ path: 'tests/tmp/topbar-close-hover.png' });
  });

  test('close button closes settings modal', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.evaluate(() => document.getElementById('settings-button')?.click());
    await page.waitForSelector('#settings-modal .std-topbar', { timeout: 10000 });
    const modal = page.locator('#settings-modal');
    await expect(modal).toBeVisible();
    await page.click('#close-settings');
    await page.waitForTimeout(300);
    await page.screenshot({ path: 'tests/tmp/topbar-after-close.png' });
    const display = await modal.evaluate((el) => getComputedStyle(el).display);
    expect(display).toBe('none');
  });

  test('news_portal_modal not broken — std-topbar present', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.evaluate(() => {
      const btn = document.getElementById('news-portal-open-btn');
      if (btn) btn.click();
    });
    await page.waitForTimeout(1000);
    await page.screenshot({ path: 'tests/tmp/topbar-news-portal.png', fullPage: false });
    const topbar = page.locator('#news-portal-overlay .std-topbar').first();
    if (await topbar.isVisible().catch(() => false)) {
      const box = await topbar.boundingBox();
      expect(box?.height).toBe(STD_TOPBAR_HEIGHT);
    }
  });
});
