import { expect, test } from '@playwright/test';
import { hasAuthUser, login } from '../helpers/shell';

const PNG_1X1 = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');

test('spends — OCR wizard layout: roadmap chips, upload previews, scan progress', async ({ page }) => {
  test.skip(!hasAuthUser(), 'Needs E2E_USER_EMAIL/PASSWORD');
  await login(page);
  await page.goto('/apps/spends/?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1500);

  // Reset any leftover active OCR job so the wizard starts at step 1
  await page.evaluate(async () => {
    try {
      const res = await fetch('utils/expense_ocr_app.php?api=active_job');
      const data = await res.json();
      if (data && data.job_id) {
        await fetch('utils/expense_ocr_app.php?api=cancel', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ job_id: data.job_id }),
        });
      }
    } catch { /* ignore */ }
  });
  await page.reload({ waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1500);

  // Open import modal (Google Wallet / OCR wizard)
  await page.locator('#import-btn').click();
  await page.locator('#import-opt-wallet').click();

  const modal = page.locator('#import-modal');
  await expect(modal).toBeVisible();

  const photosInput = page.locator('#embedded-ocr-wizard-photos');
  await expect(photosInput).toBeAttached();

  const wizard = page.locator('#embedded-ocr-wizard');

  // Roadmap has 4 chips with labels, first is active
  const chips = wizard.locator('.ocrw-step');
  await expect(chips).toHaveCount(4);
  await expect(chips.first()).toHaveClass(/is-active/);
  await expect(wizard.locator('.ocrw-step-title')).toHaveCount(4);

  // Topbar title and status badge exist
  await expect(wizard.locator('.ocrw-topbar h1')).toBeVisible();
  await expect(wizard.locator('[data-ocr-role="hero-status"]')).toBeVisible();

  // Continue button is disabled without files
  const continueBtn = page.locator('button[data-ocr-role="upload-continue"]');
  await expect(continueBtn).toBeDisabled();

  await photosInput.setInputFiles({ name: 'placeholder.png', mimeType: 'image/png', buffer: PNG_1X1 });

  // Preview card appears with a delete button
  const preview = wizard.locator('.ocrw-preview-card').first();
  await expect(preview).toBeVisible();
  await expect(wizard.locator('.ocrw-preview-delete').first()).toBeVisible();
  await expect(continueBtn).toBeEnabled();

  // Start scan -> step 2 (scan panel) becomes active
  await continueBtn.click();
  const scanPanel = wizard.locator('[data-ocr-panel="2"]');
  await expect(scanPanel).toHaveClass(/is-active/, { timeout: 20000 });
  await expect(wizard.locator('[data-ocr-role="cancel-job"]')).toBeVisible();

  // Per-file progress list rendered for the uploaded photo
  const fileItems = wizard.locator('.ocrw-file-item');
  await expect(fileItems.first()).toBeVisible();

  // Wait for the job to finish (dummy image fails fast -> report step)
  const reportPanel = wizard.locator('[data-ocr-panel="4"]');
  await expect(reportPanel).toHaveClass(/is-active/, { timeout: 60000 });
  await expect(page.locator('button[data-ocr-role="finish-btn"]')).toBeVisible();
});
