import { expect, test } from '@playwright/test';
import { autoAcceptCookies, gotoShell, hasAuthUser, login } from './helpers/shell';

test.describe('kanboard settings and phone pairing', () => {
  test.skip(!hasAuthUser(), 'E2E credentials required');
  test.describe.configure({ timeout: 120000 });

  test('opens settings, generates a pairing code and revokes it', async ({ page }) => {
    await autoAcceptCookies(page);
    await login(page);
    await gotoShell(page, '/main?app=kanboard');
    const frame = page.frameLocator('#app-iframe');
    await expect(frame.locator('#kb-app-sidebar')).toBeVisible({ timeout: 60000 });

    await frame.locator('#kb-open-settings').click();
    await expect(frame.locator('#kb-mobile-app-modal')).toBeVisible({ timeout: 15000 });
    await expect(frame.locator('#kb-settings-panel-profile')).toBeVisible();
    await expect(frame.locator('.kb-settings-avatar')).toHaveText(/^[A-Za-z\u0400-\u04FF]$/);

    await frame.locator('[data-kb-settings-tab="mobile"]').click();
    await expect(frame.locator('#kb-settings-panel-mobile')).toBeVisible();
    await frame.locator('#kb-mobile-app-qr-generate').click();
    await expect(frame.locator('#kb-mobile-app-qr-code svg')).toBeVisible({ timeout: 30000 });
    await frame.locator('#kb-mobile-manual-hint').click();
    await expect(frame.locator('#kb-manual-pair-modal')).toBeVisible();
    await expect(frame.locator('#kb-manual-pair-token')).not.toHaveText(/Generate a QR code first/);
    await frame.locator('#kb-manual-pair-close').click();

    await frame.locator('[data-kb-settings-tab="connection"]').click();
    await expect(frame.locator('#kb-settings-panel-connection')).toBeVisible();
    await expect(frame.locator('#kb-connection-endpoint')).not.toBeEmpty();
    await expect(frame.locator('#kb-connection-endpoint')).toHaveText(/\/api\/kanboard$/);
    await expect(frame.locator('#kb-connection-account')).not.toBeEmpty();

    // The pairing endpoint must reach the public API, not the login redirect.
    const endpointProbe = await page.request.get('/api/kanboard');
    expect(endpointProbe.status()).toBe(401);
    expect(endpointProbe.headers()['content-type']).toContain('application/json');

    await frame.locator('#kb-connection-revoke').click();
    await expect(frame.locator('.kb-connection-status strong')).toHaveText(/No phones connected/);
  });
});
