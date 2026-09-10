import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

test.describe('home — mobile toasts full-width', () => {
  test.beforeEach(async ({ page }) => {
    await page.route('**/main?api=news&action=list*', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          ok: true,
          items: [{
            id: 1,
            title: 'A very long news title that should definitely not be truncated even on mobile devices that are super narrow and have lots of text content',
            created_at: new Date().toISOString().split('T')[0],
            preview: 'News preview text',
            link: '#'
          }]
        })
      });
    });
  });

  async function showToastBoxes(browser, viewportWidth, viewportHeight, theme) {
    const context = await browser.newContext({ viewport: { width: viewportWidth, height: viewportHeight } });
    const localPage = await context.newPage();
    await localPage.goto('http://localhost/?lang=en&theme=' + theme, { waitUntil: 'domcontentloaded' });
    await localPage.evaluate(() => { localStorage.clear(); });
    await localPage.reload({ waitUntil: 'domcontentloaded' });
    await localPage.waitForTimeout(6500);
    const boxes = await localPage.evaluate(() => {
      const result = [];
      ['#newsToast', '#engagementToast', '#exploreWidget'].forEach(sel => {
        const el = document.querySelector(sel);
        if (!el) return;
        if (el.style.display === 'none') return;
        const r = el.getBoundingClientRect();
        result.push({ sel, left: Math.round(r.left), right: Math.round(r.right), width: Math.round(r.width), visible: r.width > 0 && r.height > 0 });
      });
      return result;
    });
    await context.close();
    return { boxes, viewportWidth, viewportHeight, theme };
  }

  async function checkFullWidth(boxes, innerWidth, scrollbarWidth, tolerance = 2) {
    for (const b of boxes) {
      expect(b.visible, `${b.sel} not visible`).toBe(true);
      expect(b.left, `${b.sel} left=${b.left} should be ~0`).toBeLessThanOrEqual(tolerance);
      const expectedRight = innerWidth - scrollbarWidth;
      expect(Math.abs(b.right - expectedRight), `${b.sel} right=${b.right} should be ~${expectedRight}`).toBeLessThanOrEqual(tolerance);
    }
  }

  async function checkOverlap(boxes) {
    const visibleBoxes = boxes.filter(b => b.visible);
    for (let i = 0; i < visibleBoxes.length - 1; i++) {
      const a = visibleBoxes[i];
      const b = visibleBoxes[i + 1];
      const overlap = a.top < b.bottom && a.bottom > b.top;
      expect(!overlap, `Toasts overlap: ${a.sel}(${a.top}-${a.bottom}) and ${b.sel}(${b.top}-${b.bottom})`).toBe(true);
    }
  }

  async function checkNoClipping(page, boxes) {
    const issues = await page.evaluate(() => {
      const pairs = [
        ['#newsToast', '.news-toast-inner', '.news-toast-close'],
        ['#engagementToast', '.engagement-toast-inner', '.engagement-toast-close'],
        ['#exploreWidget', '.explore-widget-inner', '.explore-widget-close']
      ];
      const results = [];
      for (const [hostSel, innerSel, closeSel] of pairs) {
        const host = document.querySelector(hostSel);
        if (!host || host.style.display === 'none') continue;
        const inner = host.querySelector(innerSel);
        if (inner) {
          const innerRect = inner.getBoundingClientRect();
          const hostRect = host.getBoundingClientRect();
          if (innerRect.left < hostRect.left - 1 || innerRect.right > hostRect.right + 1) {
            results.push(`${innerSel} outside host: inner=[${innerRect.left},${innerRect.right}] host=[${hostRect.left},${hostRect.right}]`);
          }
        }
        const close = host.querySelector(closeSel);
        if (close) {
          const closeRect = close.getBoundingClientRect();
          if (closeRect.right > window.innerWidth || closeRect.left < 0) {
            results.push(`${closeSel} ${closeRect.left}..${closeRect.right} exceeds viewport 0..${window.innerWidth}`);
          }
        }
      }
      return results;
    });
    for (const issue of issues) console.log('  CLIP:', issue);
    expect(issues.length, issues.join('; ')).toBe(0);
  }

  test('320px mobile, dark theme', async ({ browser }) => {
    const result = await showToastBoxes(browser, 320, 568, 'dark');
    const innerWidth = result.viewportWidth;
    const scrollbarWidth = 0;
    console.log(`[${result.viewportWidth}x${result.viewportHeight}] innerWidth=${innerWidth} scrollbarWidth=${scrollbarWidth}`);
    console.log('  toasts:', result.boxes.map(b => b.sel).join(', '));
    checkFullWidth(result.boxes, innerWidth, scrollbarWidth);
    checkOverlap(result.boxes);
    await page.evaluate(() => { result.boxes.forEach(b => expect(b.visible).toBe(true)); result.boxes.forEach(b => expect(b.left).toBeLessThanOrEqual(2)); });
    await page.evaluate(() => { result.boxes.forEach(b => { expect(Math.abs(b.right - (window.innerWidth - 0))).toBeLessThanOrEqual(2); }); });
    await checkNoClipping(page, result.boxes);
  });

  test('360px mobile, dark theme', async ({ browser }) => {
    const result = await showToastBoxes(browser, 360, 640, 'dark');
    const innerWidth = result.viewportWidth;
    const scrollbarWidth = 0;
    checkFullWidth(result.boxes, innerWidth, scrollbarWidth);
    checkOverlap(result.boxes);
    await checkNoClipping(page, result.boxes);
  });

  test('375px mobile, dark theme', async ({ browser }) => {
    const result = await showToastBoxes(browser, 375, 812, 'dark');
    checkFullWidth(result.boxes, result.viewportWidth, 0);
    checkOverlap(result.boxes);
    await checkNoClipping(page, result.boxes);
  });

  test('414px mobile, light theme', async ({ browser }) => {
    const result = await showToastBoxes(browser, 414, 896, 'light');
    checkFullWidth(result.boxes, result.viewportWidth, 0);
    checkOverlap(result.boxes);
    await checkNoClipping(page, result.boxes);
  });

  test('600px, light theme (borderline)', async ({ browser }) => {
    const result = await showToastBoxes(browser, 600, 800, 'light');
    checkFullWidth(result.boxes, result.viewportWidth, 0);
    checkOverlap(result.boxes);
    await checkNoClipping(page, result.boxes);
  });

  test('768px mobile, dark theme (borderline)', async ({ browser }) => {
    const result = await showToastBoxes(browser, 768, 1024, 'dark');
    checkFullWidth(result.boxes, result.viewportWidth, 0);
    checkOverlap(result.boxes);
    await checkNoClipping(page, result.boxes);
  });

  test('1200px desktop, no change (400px stack)', async ({ browser }) => {
    const result = await showToastBoxes(browser, 1200, 800, 'dark');
    const innerWidth = result.viewportWidth;
    console.log(`[1200x800] innerWidth=${innerWidth}`);
    for (const b of result.boxes) {
      console.log(`  ${b.sel}: left=${b.left} right=${b.right} width=${b.width} visible=${b.visible}`);
      expect(b.visible).toBe(true);
      expect(Math.abs(b.width - 400), `${b.sel} width=${b.width} should be ~400`).toBeLessThanOrEqual(2);
      expect(b.left > innerWidth - 500, `${b.sel} should be bottom-right`).toBe(true);
      expect(b.right <= innerWidth, `${b.sel} right=${b.right} exceeds innerWidth`).toBe(true);
    }
    checkOverlap(result.boxes);
  });

  test('1200→375 resize, verify full-width applied', async ({ browser, page }) => {
    await page.goto('http://localhost/?lang=en&theme=dark', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => { localStorage.clear(); });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(6500);
    const innerWidth = await page.evaluate(() => window.innerWidth);
    const scrollbarWidth = await page.evaluate(() => window.innerWidth - document.documentElement.clientWidth);
    const context = await browser.newContext({ viewport: { width: 375, height: 812 } });
    const resizePage = await context.newPage();
    await resizePage.goto('http://localhost/?lang=en&theme=dark', { waitUntil: 'domcontentloaded' });
    await resizePage.evaluate(() => { localStorage.clear(); });
    await resizePage.reload({ waitUntil: 'domcontentloaded' });
    await resizePage.waitForTimeout(6500);
    const boxes = await resizePage.evaluate(() => {
      const result = [];
      ['#newsToast', '#engagementToast', '#exploreWidget'].forEach(sel => {
        const el = document.querySelector(sel);
        if (!el || el.style.display === 'none') return;
        const r = el.getBoundingClientRect();
        result.push({ sel, left: Math.round(r.left), right: Math.round(r.right), width: Math.round(r.width) });
      });
      return result;
    });
    await context.close();
    console.log(`[after resize to 375x812] innerWidth=${innerWidth} scrollbarWidth=${scrollbarWidth}`);
    for (const b of boxes) {
      console.log(`  ${b.sel}: left=${b.left} right=${b.right} width=${b.width}`);
      expect(Math.abs(b.left), `${b.sel} left=${b.left} should be ~0`).toBeLessThanOrEqual(2);
      const expectedRight = innerWidth - scrollbarWidth;
      expect(Math.abs(b.right - expectedRight), `${b.sel} right=${b.right} should be ~${expectedRight}`).toBeLessThanOrEqual(2);
    }
  });

  test('force scrollbar, verify gutter applied', async ({ browser, page }) => {
    await page.goto('http://localhost/?lang=en&theme=dark', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => { localStorage.clear(); });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.evaluate(() => { document.body.style.height = '4000px'; });
    await page.waitForTimeout(6500);
    const innerWidth = await page.evaluate(() => window.innerWidth);
    const scrollbarWidth = await page.evaluate(() => window.innerWidth - document.documentElement.clientWidth);
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const scrollPage = await context.newPage();
    await scrollPage.goto('http://localhost/?lang=en&theme=dark', { waitUntil: 'domcontentloaded' });
    await scrollPage.evaluate(() => { localStorage.clear(); });
    await scrollPage.reload({ waitUntil: 'domcontentloaded' });
    await scrollPage.evaluate(() => { document.body.style.height = '4000px'; });
    await scrollPage.waitForTimeout(6500);
    const gutter = await scrollPage.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--toast-gutter').trim());
    console.log(`[force scrollbar] innerWidth=${innerWidth} scrollbarWidth=${scrollbarWidth} gutter=${gutter}px`);
    expect(parseInt(gutter), 'gutter should be positive').toBeGreaterThan(0);
    expect(parseInt(gutter), 'gutter should equal scrollbarWidth').toBe(scrollbarWidth);
    const boxes = await scrollPage.evaluate(() => {
      const result = [];
      ['#newsToast', '#engagementToast', '#exploreWidget'].forEach(sel => {
        const el = document.querySelector(sel);
        if (!el || el.style.display === 'none') return;
        const r = el.getBoundingClientRect();
        result.push({ sel, left: Math.round(r.left), right: Math.round(r.right), width: Math.round(r.width) });
      });
      return result;
    });
    await context.close();
    for (const b of boxes) {
      const expectedRight = innerWidth - scrollbarWidth;
      expect(Math.abs(b.right - expectedRight), `${b.sel} right=${b.right} should be ~${expectedRight}`).toBeLessThanOrEqual(2);
    }
  });
});
