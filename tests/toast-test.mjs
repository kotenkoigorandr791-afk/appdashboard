import { chromium } from 'playwright';

const BASE = process.env.BASE_URL || 'http://localhost/';

async function getScrollbarWidth(page) {
  return page.evaluate(() => window.innerWidth - document.documentElement.clientWidth);
}

async function triggerToasts(page) {
  await page.evaluate(() => {
    sessionStorage.clear();
    localStorage.clear();
  });
}

async function getToastBoxes(page) {
  return page.evaluate(() => {
    const selectors = ['#newsToast', '#engagementToast', '#exploreWidget'];
    const result = [];
    for (const sel of selectors) {
      const el = document.querySelector(sel);
      if (!el) continue;
      const cs = getComputedStyle(el);
      if (el.style.display === 'none' || cs.display === 'none') continue;
      const r = el.getBoundingClientRect();
      result.push({
        sel,
        left: Math.round(r.left),
        right: Math.round(r.right),
        width: Math.round(r.width),
        bottom: Math.round(r.bottom),
        top: Math.round(r.top),
        visible: r.width > 0 && r.height > 0,
      });
    }
    return result;
  });
}

function assert(cond, msg) {
  if (!cond) throw new Error('ASSERT FAILED: ' + msg);
}

async function testMobileViewport(browser, width, height) {
  const context = await browser.newContext({ viewport: { width, height } });
  const page = await context.newPage();
  const errors = [];
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('pageerror', e => errors.push(String(e)));

  await page.goto(BASE, { waitUntil: 'networkidle' });
  await triggerToasts(page);
  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForTimeout(6500);

  const boxes = await getToastBoxes(page);
  const innerWidth = await page.evaluate(() => window.innerWidth);
  const scrollbarWidth = await getScrollbarWidth(page);

  console.log(`[${width}x${height}] innerWidth=${innerWidth} scrollbarWidth=${scrollbarWidth}`);
  console.log('  toasts found:', boxes.map(b => b.sel).join(', ') || '(none)');

  for (const b of boxes) {
    console.log(`  ${b.sel}: left=${b.left} right=${b.right} width=${b.width} top=${b.top} bottom=${b.bottom} visible=${b.visible}`);
    assert(b.visible, `${b.sel} not visible`);
    assert(Math.abs(b.left) <= 1, `${b.sel} left=${b.left} should be ~0`);
    const expectedRight = innerWidth - scrollbarWidth;
    assert(Math.abs(b.right - expectedRight) <= 2, `${b.sel} right=${b.right} should be ~${expectedRight} [w=${width}, sb=${scrollbarWidth}]`);
  }

  const visibleBoxes = boxes.filter(b => b.visible);
  for (let i = 0; i < visibleBoxes.length - 1; i++) {
    const a = visibleBoxes[i];
    const b = visibleBoxes[i + 1];
    const overlap = a.top < b.bottom && a.bottom > b.top;
    assert(!overlap, `Toasts overlap vertically: ${a.sel}(${a.top}-${a.bottom}) and ${b.sel}(${b.top}-${b.bottom})`);
  }

  const clipping = await page.evaluate(() => {
    const issues = [];
    const pairs = [
      ['#newsToast', '.news-toast-inner', '.news-toast-close'],
      ['#engagementToast', '.engagement-toast-inner', '.engagement-toast-close'],
      ['#exploreWidget', '.explore-widget-inner', '.explore-widget-close'],
    ];
    for (const [hostSel, innerSel, closeSel] of pairs) {
      const host = document.querySelector(hostSel);
      if (!host) continue;
      if (getComputedStyle(host).display === 'none') continue;
      const inner = host.querySelector(innerSel);
      if (inner) {
        const r = inner.getBoundingClientRect();
        const hr = host.getBoundingClientRect();
        if (r.left < hr.left - 1 || r.right > hr.right + 1) {
          issues.push(`${innerSel} outside host: inner=[${r.left},${r.right}] host=[${hr.left},${hr.right}]`);
        }
      }
      const close = host.querySelector(closeSel);
      if (close) {
        const cr = close.getBoundingClientRect();
        if (cr.right > window.innerWidth || cr.left < 0) {
          issues.push(`${closeSel} ${cr.left}..${cr.right} exceeds viewport 0..${window.innerWidth}`);
        }
      }
    }
    return issues;
  });

  for (const c of clipping) console.log('  CLIP:', c);
  assert(clipping.length === 0, clipping.join('; '));

  assert(errors.length === 0, 'Console errors: ' + errors.join(' | '));
  console.log('  PASS: full-width, non-overlapping, no clipping, no console errors');

  await page.screenshot({ path: `mobile-${width}.png`, fullPage: false });
  await context.close();
}

async function testDesktopViewport(browser) {
  const context = await browser.newContext({ viewport: { width: 1200, height: 800 } });
  const page = await context.newPage();
  const errors = [];
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('pageerror', e => errors.push(String(e)));

  await page.goto(BASE, { waitUntil: 'networkidle' });
  await triggerToasts(page);
  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForTimeout(6500);

  const boxes = await getToastBoxes(page);
  const innerWidth = await page.evaluate(() => window.innerWidth);

  console.log(`[DESKTOP 1200x800] innerWidth=${innerWidth}`);
  console.log('  toasts found:', boxes.map(b => b.sel).join(', ') || '(none)');

  for (const b of boxes) {
    console.log(`  ${b.sel}: left=${b.left} right=${b.right} width=${b.width} bottom=${b.bottom} visible=${b.visible}`);
    assert(b.visible, `${b.sel} not visible`);
    assert(Math.abs(b.width - 400) <= 2, `${b.sel} width=${b.width} should be ~400 on desktop`);
    assert(b.left > innerWidth - 500, `${b.sel} should be bottom-right on desktop, left=${b.left}`);
    assert(b.right <= innerWidth, `${b.sel} right=${b.right} exceeds innerWidth`);
  }

  assert(errors.length === 0, 'Console errors: ' + errors.join(' | '));
  console.log('  PASS: desktop toasts ~400px at bottom-right, no console errors');
  await page.screenshot({ path: 'desktop-1200.png', fullPage: false });
  await context.close();
}

const browser = await chromium.launch();
try {
  await testMobileViewport(browser, 375, 812);
  await testMobileViewport(browser, 320, 568);
  await testMobileViewport(browser, 600, 800);
  await testDesktopViewport(browser);
  console.log('\nALL TESTS PASSED');
} finally {
  await browser.close();
}
