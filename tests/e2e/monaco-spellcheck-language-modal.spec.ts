import { test, expect, type Page } from '@playwright/test';
import { hasAuthUser, login, gotoShell } from './helpers/shell';

const ROOT_KEY = 'temp';
const LANGUAGE_COUNT = 14; // en_US, ru_RU, uk_UA, be_BY, de_DE, fr_FR, es_ES, it_IT, pt_BR, nl_NL, pl_PL, cs_CZ, da_DK, sv_SE
const OPTION_COUNT = LANGUAGE_COUNT + 2; // + auto + off

type CloudFrame = ReturnType<Page['frameLocator']>;

// Sequential (one worker because of the shared login) with a budget that covers
// signing in and opening the editor; a failure must not skip the remaining tests.
test.describe.configure({ timeout: 120000 });

/**
 * The .txt spellcheck language picker is a centered modal: the two non-language
 * options carry an icon (globe / crossed-out circle) and every language carries
 * its country flag, which a native <select> cannot render.
 */
test.describe('Monaco spellcheck language picker', () => {
  async function openTxtEditor(page: Page): Promise<CloudFrame> {
    const fileName = `e2e-spellpick-${Date.now()}-${Math.floor(Math.random() * 1e6)}.txt`;
    await login(page);
    await gotoShell(page, '/main?app=cloud');
    await page.waitForSelector('#app-iframe', { timeout: 60000 });
    const frame = page.frameLocator('#app-iframe');
    await frame.locator('#tree .node').first().waitFor({ timeout: 60000 });

    await frame.locator('body').evaluate(async (_el, { name, root }) => {
      const api = (window as any).api;
      await api('create_file', { path: name, root });
      await api('save_file', { path: name, root, content: 'hello wrold\n' });
      await api('invalidate_file_tree', {}, 'POST');
    }, { name: fileName, root: ROOT_KEY });

    await frame.locator('body').evaluate((_el, root) => (window as any).switchCloudRoot(root), ROOT_KEY);
    const node = frame.locator(`#tree .node[data-path="${fileName}"]`).first();
    await node.waitFor({ timeout: 60000 });
    await node.click();
    await frame.locator('.monaco-editor textarea').first().waitFor({ state: 'attached', timeout: 60000 });
    await frame.locator('.monaco-status-spell-select').first().waitFor({ timeout: 60000 });
    return frame;
  }

  function trigger(frame: CloudFrame) {
    return frame.locator('.monaco-status-spell-select').first();
  }

  function modal(frame: CloudFrame) {
    return frame.locator('.monaco-spell-modal-overlay');
  }

  async function readStoredLanguage(frame: CloudFrame) {
    return frame.locator('body').evaluate(() => localStorage.getItem('monacoSpellcheckLanguage'));
  }

  test.beforeEach(async ({ page }) => {
    // The footer status bar (and with it the picker) is hidden by design on
    // mobile layouts: @media (max-width: 768px) { #status-editor-slot{display:none} }
    test.skip((page.viewportSize()?.width || 0) < 769, 'picker lives in the desktop footer status bar');
  });

  test('opens a centered modal with flags and icons', async ({ page }) => {
    test.skip(!hasAuthUser(), 'Needs E2E_USER_EMAIL/PASSWORD');
    const frame = await openTxtEditor(page);

    // The native select is gone; the trigger is a button carrying an icon + label.
    const triggerTag = await trigger(frame).evaluate((el) => el.tagName);
    expect(triggerTag).toBe('BUTTON');
    await expect(trigger(frame).locator('svg')).toHaveCount(1);
    await expect(trigger(frame).locator('.monaco-spell-icon-label')).not.toBeEmpty();

    await trigger(frame).click();
    await expect(modal(frame)).toBeVisible();

    // Centered on screen.
    const box = await frame.locator('.monaco-spell-modal').boundingBox();
    const viewport = page.viewportSize()!;
    expect(box).not.toBeNull();
    expect(Math.abs((box!.x + box!.width / 2) - viewport.width / 2)).toBeLessThanOrEqual(3);
    expect(Math.abs((box!.y + box!.height / 2) - viewport.height / 2)).toBeLessThanOrEqual(3);

    // Every option has an icon, and the language options are distinct flags.
    await expect(frame.locator('.monaco-spell-option')).toHaveCount(OPTION_COUNT);
    await expect(frame.locator('.monaco-spell-option-icon svg')).toHaveCount(OPTION_COUNT);

    const icons = await frame.locator('body').evaluate(() => {
      const options = Array.from(document.querySelectorAll('.monaco-spell-option')) as HTMLElement[];
      return options.map((option) => ({
        value: option.dataset.value,
        viewBox: option.querySelector('svg')?.getAttribute('viewBox') || '',
        markup: option.querySelector('svg')?.innerHTML || '',
      }));
    });

    for (const icon of icons) {
      expect(icon.markup.length, `${icon.value} icon markup`).toBeGreaterThan(0);
      if (icon.value === 'auto' || icon.value === 'off') {
        expect(icon.viewBox, `${icon.value} uses a square icon`).toBe('4 0 16 16');
      } else {
        expect(icon.viewBox, `${icon.value} uses a flag`).toBe('0 0 24 16');
      }
    }

    const byValue = new Map(icons.map((icon) => [icon.value, icon.markup]));
    // Spot-check that the flags really are the expected countries.
    expect(byValue.get('ru_RU')).toContain('#0039a6');
    expect(byValue.get('uk_UA')).toContain('#ffd700');
    expect(byValue.get('de_DE')).toContain('#ffce00');
    expect(byValue.get('fr_FR')).toContain('#ef4135');
    expect(byValue.get('pl_PL')).toContain('#dc143c');
    expect(byValue.get('sv_SE')).toContain('#006aa7');

    const flagMarkup = icons.filter((icon) => icon.value !== 'auto' && icon.value !== 'off');
    expect(new Set(flagMarkup.map((icon) => icon.markup)).size, 'each language needs its own flag').toBe(LANGUAGE_COUNT);

    // auto is the sparkles icon, off the crossed-out circle — neither is a flag.
    expect(byValue.get('auto')).toContain('<path');
    expect(byValue.get('auto')).not.toContain('<rect');
    expect(byValue.get('off')).not.toContain('<rect');

    // Rasterize the inline SVGs: the flags must really draw their band colours.
    const bands = await frame.locator('body').evaluate(async () => {
      const sample = (ctx: CanvasRenderingContext2D, x: number, y: number) => {
        const data = ctx.getImageData(x, y, 1, 1).data;
        return '#' + [data[0], data[1], data[2]].map((v) => v.toString(16).padStart(2, '0')).join('');
      };
      const result: Record<string, { top: string; middle: string; bottom: string; canton: string; stripe: string; lastRow: string }> = {};
      const options = Array.from(document.querySelectorAll('.monaco-spell-option')) as HTMLElement[];
      for (const option of options) {
        const svg = option.querySelector('svg');
        if (!svg) continue;
        const markup = new XMLSerializer().serializeToString(svg);
        const image = new Image();
        await new Promise<void>((resolve, reject) => {
          image.onload = () => resolve();
          image.onerror = () => reject(new Error('svg did not load'));
          image.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(markup);
        });
        const canvas = document.createElement('canvas');
        canvas.width = 24;
        canvas.height = 16;
        const ctx = canvas.getContext('2d')!;
        ctx.drawImage(image, 0, 0, 24, 16);
        const pixels = ctx.getImageData(0, 0, 24, 16).data;
        let painted = 0;
        for (let i = 3; i < pixels.length; i += 4) {
          if (pixels[i] > 0) painted++;
        }
        result[option.dataset.value || ''] = {
          top: sample(ctx, 18, 2),
          middle: sample(ctx, 18, 8),
          bottom: sample(ctx, 18, 14),
          canton: sample(ctx, 5, 3),
          stripe: sample(ctx, 18, 12),
          lastRow: sample(ctx, 18, 15),
          painted,
        };
      }
      return result;
    });

    expect(bands.ru_RU).toMatchObject({ top: '#ffffff', middle: '#0039a6', bottom: '#d52b1e', canton: '#ffffff', stripe: '#d52b1e', lastRow: '#d52b1e' });
    expect(bands.uk_UA).toMatchObject({ top: '#0057b7', middle: '#ffd700', bottom: '#ffd700', canton: '#0057b7', stripe: '#ffd700', lastRow: '#ffd700' });
    expect(bands.de_DE).toMatchObject({ top: '#111111', middle: '#dd0000', bottom: '#ffce00', canton: '#111111', stripe: '#ffce00', lastRow: '#ffce00' });
    expect(bands.pl_PL).toMatchObject({ top: '#ffffff', middle: '#dc143c', bottom: '#dc143c', canton: '#ffffff', stripe: '#dc143c', lastRow: '#dc143c' });
    expect(bands.nl_NL).toMatchObject({ top: '#ae1c28', middle: '#ffffff', bottom: '#21468b', canton: '#ae1c28', stripe: '#21468b', lastRow: '#21468b' });
    // US flag: a blue canton over red/white stripes (the stripes are thinner than
    // one rasterized row, so only the last row is a pure stripe colour).
    expect(bands.en_US.canton).toBe('#3c3b6e');
    expect(bands.en_US.lastRow).toBe('#b22234');
    expect(bands.en_US.stripe).not.toBe(bands.en_US.canton);

    // The two non-language icons really draw something.
    expect(bands.auto.painted).toBeGreaterThan(20);
    expect(bands.off.painted).toBeGreaterThan(20);

    // The list scrolls inside the capped panel, and the last language is reachable.
    const list = frame.locator('.monaco-spell-modal-list');
    const overflow = await list.evaluate((el) => ({ clientHeight: el.clientHeight, scrollHeight: el.scrollHeight }));
    expect(overflow.scrollHeight).toBeGreaterThan(overflow.clientHeight);
    const lastOption = frame.locator('.monaco-spell-option[data-value="sv_SE"]');
    await lastOption.scrollIntoViewIfNeeded();
    const panelBox = await frame.locator('.monaco-spell-modal').boundingBox();
    const lastBox = await lastOption.boundingBox();
    expect(lastBox!.y).toBeGreaterThanOrEqual(panelBox!.y);
    expect(lastBox!.y + lastBox!.height).toBeLessThanOrEqual(panelBox!.y + panelBox!.height + 1);
    await lastOption.click();
    await expect(trigger(frame).locator('.monaco-spell-icon-label')).toHaveText('Svenska');
  });

  test('the language list scrollbar follows the app theme', async ({ page }) => {
    test.skip(!hasAuthUser(), 'Needs E2E_USER_EMAIL/PASSWORD');
    const frame = await openTxtEditor(page);

    const readScrollbar = () => frame.locator('body').evaluate(() => {
      const list = document.querySelector('.monaco-spell-modal-list') as HTMLElement | null;
      if (!list) return null;
      const style = getComputedStyle(list);
      return {
        scrollbarColor: style.scrollbarColor,
        scrollbarWidth: style.scrollbarWidth,
        // Custom properties are inherited, so this is the value in effect here.
        thumb: style.getPropertyValue('--scrollbar-thumb').trim(),
        track: style.getPropertyValue('--scrollbar-track').trim(),
        theme: document.body.getAttribute('data-theme') || 'light',
      };
    });

    // Use the app's own theme switch: besides the data-theme attribute it also
    // injects the palette on :root, which would otherwise keep the saved theme.
    const setTheme = (theme: string) => frame.locator('body').evaluate((_el, value) => {
      (window as any).applyTheme(value, '#2563eb');
    }, theme);
    const readWithTheme = async (theme: string) => {
      await setTheme(theme);
      await page.waitForTimeout(100);
      await trigger(frame).click();
      const reading = await readScrollbar();
      await page.keyboard.press('Escape');
      await expect(modal(frame)).toHaveCount(0);
      return reading;
    };

    const light = await readWithTheme('light');
    const dark = await readWithTheme('dark');

    console.log('scrollbar diagnostics:', JSON.stringify({ light, dark }));
    expect(light?.theme).toBe('light');
    expect(dark?.theme).toBe('dark');
    // Light theme uses the light palette, dark theme the dark one — never the
    // browser default scrollbar.
    expect(light?.track).toBe('#f1f1f1');
    expect(light?.thumb).toBe('#c1c1c1');
    expect(light?.scrollbarColor).toContain('193, 193, 193');
    expect(dark?.track).toBe('#2a2a2a');
    expect(dark?.thumb).toBe('#555');
    expect(dark?.scrollbarColor).toContain('42, 42, 42');
    expect(dark?.scrollbarColor).toContain('85, 85, 85');
    expect(dark?.scrollbarWidth).toBe('thin');
    expect(dark?.scrollbarColor).not.toBe(light?.scrollbarColor);
  });

  test('selecting a language updates the trigger and persists the choice', async ({ page }) => {
    test.skip(!hasAuthUser(), 'Needs E2E_USER_EMAIL/PASSWORD');
    const frame = await openTxtEditor(page);

    await trigger(frame).click();
    await frame.locator('.monaco-spell-option[data-value="ru_RU"]').click();

    await expect(modal(frame)).toHaveCount(0);
    await expect(trigger(frame).locator('.monaco-spell-icon-label')).toHaveText('Русский');
    expect(await readStoredLanguage(frame)).toBe('ru_RU');
    const flagAfterSelect = await trigger(frame).locator('svg').getAttribute('viewBox');
    expect(flagAfterSelect).toBe('0 0 24 16');

    // Reopening marks the active language.
    await trigger(frame).click();
    await expect(frame.locator('.monaco-spell-option.is-active')).toHaveAttribute('data-value', 'ru_RU');
    await page.keyboard.press('Escape');
    await expect(modal(frame)).toHaveCount(0);

    // Backdrop click also closes it (top-left corner, away from the panel).
    await trigger(frame).click();
    await expect(modal(frame)).toBeVisible();
    await modal(frame).click({ position: { x: 6, y: 6 } });
    await expect(modal(frame)).toHaveCount(0);

    // Off switches the checker off and shows the crossed-out icon.
    await trigger(frame).click();
    await frame.locator('.monaco-spell-option[data-value="off"]').click();
    await expect(trigger(frame).locator('.monaco-spell-icon-label')).toHaveText('Off');
    expect(await readStoredLanguage(frame)).toBe('off');
    expect(await trigger(frame).locator('svg').getAttribute('viewBox')).toBe('4 0 16 16');
    await expect(frame.locator('.monaco-status-spell-status')).toHaveText(/off/i);
  });
});
