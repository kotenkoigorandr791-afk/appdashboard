import { expect, test } from '@playwright/test';
import path from 'node:path';

test('spends — OCR wizard crop layout: crop-reset under thumbs; skip/apply removed', async ({ page }) => {
  await page.goto('/apps/spends/?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });

  // Open import modal (Google Wallet / OCR wizard)
  await page.locator('#import-btn').click();
  await page.locator('#import-opt-wallet').click();

  const modal = page.locator('#import-modal');
  await expect(modal).toBeVisible();

  const photosInput = page.locator('#embedded-ocr-wizard-photos');
  await expect(photosInput).toBeVisible();

  // Use repo-local placeholder image (we only need a valid bitmap for layout)
  const filePath = path.resolve(__dirname, '../../google.png');
  await photosInput.setInputFiles(filePath);

  await page.locator('button[data-ocr-role="upload-continue"]').click();

  const firstThumb = page.locator('div[data-ocr-role="crop-thumbs"] .ocrw-thumb').first();
  await expect(firstThumb).toBeVisible();

  // Buttons removed
  await expect(page.locator('button[data-ocr-role="crop-skip"]')).toHaveCount(0);
  await expect(page.locator('button[data-ocr-role="crop-apply"]')).toHaveCount(0);

  const cropReset = page.locator('button[data-ocr-role="crop-reset"]');
  await expect(cropReset).toBeVisible();

  const thumbs = page.locator('div[data-ocr-role="crop-thumbs"]');
  await expect(thumbs).toBeVisible();

  const cropResetBox = await cropReset.boundingBox();
  const thumbsBox = await thumbs.boundingBox();

  expect(cropResetBox).not.toBeNull();
  expect(thumbsBox).not.toBeNull();

  // crop-reset should be below thumbs list, not inside it/above it
  expect(cropResetBox!.top).toBeGreaterThanOrEqual(thumbsBox!.bottom - 2);
});
