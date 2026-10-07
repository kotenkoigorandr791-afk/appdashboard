import { expect, test } from '@playwright/test';
import { login } from './helpers/shell';

test.describe('localized app names (task 006)', () => {
  const expectations: Record<string, { tile: string; landing: string }> = {
    ru: { tile: 'Гит-репозиторий', landing: 'Гит-репозиторий' },
    uk: { tile: 'Гіт-репозиторій', landing: 'Гіт-репозиторій' },
    cs: { tile: 'Git repozitář', landing: 'Git repozitář' },
    pl: { tile: 'Repozytorium Gita', landing: 'Repozytorium Gita' },
  };

  for (const [lang, exp] of Object.entries(expectations)) {
    test(`shell renders localized names for lang=${lang}`, async ({ page }) => {
      await login(page);
      await page.goto(`/main?lang=${lang}&theme=light&color=%232563eb`, { waitUntil: 'domcontentloaded' });
      const names = await page.locator('#app-grid .app-name').allTextContents();
      expect(names).toContain(exp.tile);
      // No non-English locale should keep the bare English placeholder.
      if (lang !== 'en') {
        expect(names.filter((n) => n === 'Kanboard')).toHaveLength(0);
      }
      // Names must not be visually truncated in the tile.
      const longName = page.locator('#app-grid .app-name', { hasText: exp.tile.split(' ')[0] }).first();
      const box = await longName.boundingBox();
      expect(box).not.toBeNull();
      expect(box!.width).toBeGreaterThan(20);
    });

    test(`landing renders localized app names for lang=${lang}`, async ({ page }) => {
      await page.goto(`/?lang=${lang}`, { waitUntil: 'domcontentloaded' });
      await expect(page.locator('body')).toContainText(exp.landing);
    });
  }

  test('shell sidebar shows localized names too (ru)', async ({ page }) => {
    await login(page);
    await page.goto('/main?lang=ru&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
    const sidebarNames = await page.locator('.sidebar-app-name').allTextContents();
    expect(sidebarNames).toContain('Гит-репозиторий');
    expect(sidebarNames).toContain('Фотографии');
  });
});
