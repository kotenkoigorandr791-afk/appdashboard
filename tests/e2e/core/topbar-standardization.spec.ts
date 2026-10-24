import { expect, test } from '@playwright/test';
import { autoAcceptCookies, login, skipWithoutAuth } from '../helpers/shell';

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

async function openShellPage(page: any, testInfo: any) {
  await skipWithoutAuth(testInfo);
  await login(page);
  await autoAcceptCookies(page);
  await page.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
  await autoAcceptCookies(page);
}

async function openModal(page: any, modal: (typeof SHELL_MODALS)[number] | typeof ADMIN_MODAL) {
  if ('forceOpen' in modal && modal.forceOpen) {
    const modalId = modal.topbarSelector.split(' ')[0].replace('#', '');
    await page.evaluate((id) => {
      const m = document.getElementById(id);
      if (m) (m as HTMLElement).style.display = 'flex';
    }, modalId);
    return;
  }

  const triggerId = (modal as any).trigger.replace('#', '');
  await page.evaluate((id) => document.getElementById(id)?.click(), triggerId);
}

// suite mode handled by config / runner; do not call test.describe.configure() here

test('topbar standardization — smoke + sizing', async ({ page }, testInfo) => {
  for (const modal of SHELL_MODALS) {
    await openShellPage(page, testInfo);

    await page.setViewportSize({ width: 1920, height: 1080 });
    await openModal(page, modal);
    await page.waitForSelector(modal.topbarSelector, { state: 'visible', timeout: 10000 });
    const barDesktop = page.locator(modal.topbarSelector).first();
    await expect(barDesktop).toBeVisible();
    const boxDesktop = await barDesktop.boundingBox();
    expect(boxDesktop?.height).toBe(STD_TOPBAR_HEIGHT);
    await page.screenshot({ path: `/tmp/topbar-${modal.name}-1920.png`, fullPage: false });

    await openShellPage(page, testInfo);
    await page.setViewportSize({ width: 375, height: 812 });
    await openModal(page, modal);
    await page.waitForSelector(modal.topbarSelector, { state: 'visible', timeout: 10000 });
    const barMobile = page.locator(modal.topbarSelector).first();
    await expect(barMobile).toBeVisible();
    const boxMobile = await barMobile.boundingBox();
    expect(boxMobile?.height).toBe(STD_TOPBAR_HEIGHT);
    await page.screenshot({ path: `/tmp/topbar-${modal.name}-375.png`, fullPage: false });
  }

  await openShellPage(page, testInfo);
  await page.setViewportSize({ width: 1920, height: 1080 });
  await openModal(page, ADMIN_MODAL);
  await page.waitForSelector(ADMIN_MODAL.topbarSelector, { state: 'visible', timeout: 10000 });
  const adminDesktop = page.locator(ADMIN_MODAL.topbarSelector).first();
  await expect(adminDesktop).toBeVisible();
  const adminBoxDesktop = await adminDesktop.boundingBox();
  expect(adminBoxDesktop?.height).toBe(STD_TOPBAR_HEIGHT);
  await page.screenshot({ path: `/tmp/topbar-${ADMIN_MODAL.name}-1920.png`, fullPage: false });

  await openShellPage(page, testInfo);
  await page.setViewportSize({ width: 375, height: 812 });
  await openModal(page, ADMIN_MODAL);
  await page.waitForSelector(ADMIN_MODAL.topbarSelector, { state: 'visible', timeout: 10000 });
  const adminMobile = page.locator(ADMIN_MODAL.topbarSelector).first();
  await expect(adminMobile).toBeVisible();
  const adminBoxMobile = await adminMobile.boundingBox();
  expect(adminBoxMobile?.height).toBe(STD_TOPBAR_HEIGHT);
  await page.screenshot({ path: `/tmp/topbar-${ADMIN_MODAL.name}-375.png`, fullPage: false });
});
