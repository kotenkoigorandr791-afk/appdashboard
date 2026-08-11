import { expect, type Locator, type Page, type TestInfo } from '@playwright/test';

export const authUser = {
  email: process.env.E2E_USER_EMAIL || '',
  password: process.env.E2E_USER_PASSWORD || ''
};

export function hasAuthUser() {
  return authUser.email !== '' && authUser.password !== '';
}

export async function gotoShell(page: Page, path = '/main') {
  const separator = path.includes('?') ? '&' : '?';
  await page.goto(`${path}${separator}lang=en&theme=light&color=%232563eb`, { waitUntil: 'domcontentloaded' });
}

export async function login(page: Page) {
  await gotoShell(page, '/login');
  await page.locator('#login-content input[name="email"]').fill(authUser.email);
  await page.locator('#login-password').fill(authUser.password);
  await Promise.all([
    page.waitForURL(url => !url.pathname.includes('/login'), { timeout: 30000 }),
    page.locator('#login-content button[name="login"]').click()
  ]);
}

export async function autoAcceptCookies(page: Page) {
  await page.addLocatorHandler(page.locator('#cookie-consent-overlay'), async () => {
    await page.locator('#cookie-consent-accept').click({ timeout: 5000 }).catch(() => {});
    await page.locator('#cookie-consent-overlay').waitFor({ state: 'detached', timeout: 5000 }).catch(() => {});
  });
}

export async function expectVisibleBox(locator: Locator) {
  await expect(locator).toBeVisible();
  const box = await locator.boundingBox();
  expect(box?.width || 0).toBeGreaterThan(0);
  expect(box?.height || 0).toBeGreaterThan(0);
}

export async function expectNoViewportOverflow(page: Page, selector: string) {
  const result = await page.locator(selector).evaluateAll(elements => {
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    return elements
      .filter(element => {
        const style = getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
      })
      .map(element => {
        const rect = element.getBoundingClientRect();
        return {
          tag: element.tagName.toLowerCase(),
          id: element.id,
          className: String(element.className || ''),
          text: (element.textContent || '').trim().slice(0, 80),
          left: rect.left,
          right: rect.right,
          top: rect.top,
          bottom: rect.bottom,
          width: rect.width,
          height: rect.height,
          viewportWidth,
          viewportHeight
        };
      })
      .filter(rect => rect.right < -1 || rect.bottom < -1 || rect.left > viewportWidth + 1 || rect.top > viewportHeight + 1 || rect.width < 1 || rect.height < 1);
  });
  expect(result).toEqual([]);
}

export async function expectNoCenterOverlaps(page: Page, selector: string) {
  const issues = await page.locator(selector).evaluateAll(elements => {
    return elements
      .filter(element => {
        const style = getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return style.display !== 'none' && style.visibility !== 'hidden' && style.pointerEvents !== 'none' && rect.width > 0 && rect.height > 0;
      })
      .map(element => {
        const rect = element.getBoundingClientRect();
        const x = rect.left + rect.width / 2;
        const y = rect.top + rect.height / 2;
        const top = document.elementFromPoint(x, y);
        const ok = top === element || element.contains(top) || (element.id ? !!top?.closest(`#${CSS.escape(element.id)}`) : false);
        return ok ? null : {
          id: element.id,
          className: String(element.className || ''),
          coveredBy: top ? `${top.tagName.toLowerCase()}#${top.id}.${String(top.className || '')}` : 'none'
        };
      })
      .filter(Boolean);
  });
  expect(issues).toEqual([]);
}

export async function skipWithoutAuth(testInfo: TestInfo) {
  testInfo.skip(!hasAuthUser(), 'Set E2E_USER_EMAIL and E2E_USER_PASSWORD to run authenticated shell tests.');
}
