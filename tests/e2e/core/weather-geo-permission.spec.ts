import { test, expect, type Page } from '@playwright/test';

const ADMIN_EMAIL = process.env.E2E_USER_EMAIL || 'dashboard-e2e@test.local';
const ADMIN_PASSWORD = process.env.E2E_USER_PASSWORD || 'dashboard-e2e-e2e-password';

const FORECAST_FIXTURE = {
  current: { temperature_2m: 22, weather_code: 2, wind_speed_10m: 15 },
  daily: {
    time: ['2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09', '2026-10-10', '2026-10-11', '2026-10-12'],
    weathercode: [0, 1, 2, 45, 51, 61, 95],
    temperature_2m_max: [22, 24, 23, 18, 15, 14, 16],
    temperature_2m_min: [15, 16, 15, 12, 10, 9, 11],
    precipitation_sum: [0, 0, 0, 0, 5, 20, 50],
    wind_speed_10m_max: [10, 12, 11, 8, 6, 20, 35],
  },
};

const NO_COORDS_SETTINGS = {
  use_current_location: false,
  selected_country: null,
  selected_country_code: null,
  selected_region: null,
  selected_city: null,
  lat: null,
  lon: null,
};

async function login(page: Page) {
  await page.goto('/login?lang=en', { waitUntil: 'domcontentloaded' });
  await page.locator('#login-content input[name="email"]').fill(ADMIN_EMAIL);
  await page.locator('#login-password').fill(ADMIN_PASSWORD);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30000 }),
    page.locator('#login-content button[name="login"]').click(),
  ]);
}

async function mockWeatherBackend(page: Page, opts: { settings?: object } = {}) {
  await page.route('**/project/weather_settings.php', async (route) => {
    if (route.request().method() === 'POST') {
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true }) });
    } else {
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(opts.settings ?? NO_COORDS_SETTINGS) });
    }
  });
  await page.route('**/api.open-meteo.com/**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(FORECAST_FIXTURE) }));
  await page.route('**/ipwho.is/**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: false }) }));
  await page.route('**/api.bigdatacloud.net/**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ city: 'Testville' }) }));
}

test.describe('weather geolocation permission handling', () => {
  test('prompt state initiates a permission request instead of showing "denied"', async ({ page }) => {
    // Fresh context (no cached coords), permissions.query reports 'prompt'.
    await page.addInitScript(() => {
      try { localStorage.removeItem('ap_weather_data'); } catch (e) {}
      const q = navigator.permissions && navigator.permissions.query.bind(navigator.permissions);
      if (q) {
        navigator.permissions.query = ((p: any) =>
          Promise.resolve({ state: 'prompt', onchange: null, addEventListener() {}, removeEventListener() {} } as any)) as any;
      }
      (window as any).__geoCalls = 0;
      const orig = navigator.geolocation.getCurrentPosition.bind(navigator.geolocation);
      navigator.geolocation.getCurrentPosition = ((ok: any, err: any, opts: any) => {
        (window as any).__geoCalls++;
        orig(ok, err, opts);
      }) as any;
    });
    await mockWeatherBackend(page);
    await login(page);
    await page.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });

    // A real getCurrentPosition request must be kicked off (browser prompt),
    // instead of the widget showing "denied" without ever asking.
    await page.waitForTimeout(1500);
    const geoCalls = await page.evaluate(() => (window as any).__geoCalls);
    expect(geoCalls).toBeGreaterThan(0);
    // If headless auto-denied the prompt afterwards, the widget may show the
    // perm error — but only after a real PERMISSION_DENIED, never earlier.
    const permText = await page.locator('.weather-widget-instance .weather-perm').first().textContent().catch(() => '');
    if (permText && permText.trim() !== '') {
      expect(permText).toContain('denied');
    }
  });

  test('granted state loads weather and the modal shows the forecast', async ({ page }) => {
    await page.addInitScript(() => {
      try { localStorage.removeItem('ap_weather_data'); } catch (e) {}
    });
    await page.context().grantPermissions(['geolocation']);
    await page.context().setGeolocation({ latitude: 50.45, longitude: 30.52 });
    await mockWeatherBackend(page);
    await login(page);
    await page.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });

    await expect(page.locator('.weather-widget-instance .weather-temp').first()).toContainText('22', { timeout: 15000 });
    // JS-dispatched click: on mobile the top-bar title overlays the widget.
    await page.locator('.weather-widget-instance').first().evaluate((el: HTMLElement) => el.click());
    const modal = page.locator('#weatherModalOverlay');
    await expect(modal).toBeVisible();
    await expect(page.locator('.weather-geo-prompt')).toHaveCount(0);
    await expect(page.locator('#weatherModalContent')).not.toContainText('Please allow');
  });

  test('denied state shows the correct denied message and does not re-request', async ({ page }) => {
    await page.addInitScript(() => {
      try { localStorage.removeItem('ap_weather_data'); } catch (e) {}
      if (navigator.permissions) {
        navigator.permissions.query = ((p: any) =>
          Promise.resolve({ state: 'denied', onchange: null, addEventListener() {}, removeEventListener() {} } as any)) as any;
      }
      (window as any).__geoCalls = 0;
      navigator.geolocation.getCurrentPosition = (() => {
        (window as any).__geoCalls++;
      }) as any;
    });
    await mockWeatherBackend(page);
    await login(page);
    await page.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });

    await expect(page.locator('.weather-widget-instance .weather-perm').first()).toBeVisible({ timeout: 10000 });
    await expect(page.locator('.weather-widget-instance .weather-perm').first()).toContainText('denied');
    const geoCalls = await page.evaluate(() => (window as any).__geoCalls);
    expect(geoCalls).toBe(0);
  });

  test('modal geo prompt does not overlap header buttons (desktop and mobile)', async ({ page, viewport }) => {
    await page.addInitScript(() => {
      try { localStorage.removeItem('ap_weather_data'); } catch (e) {}
      if (navigator.permissions) {
        navigator.permissions.query = ((p: any) =>
          Promise.resolve({ state: 'denied', onchange: null, addEventListener() {}, removeEventListener() {} } as any)) as any;
      }
      navigator.geolocation.getCurrentPosition = (() => {}) as any;
    });
    await mockWeatherBackend(page);
    await login(page);
    await page.goto('/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });

    await page.locator('.weather-widget-instance').first().evaluate((el: HTMLElement) => el.click());
    const modal = page.locator('#weatherModalOverlay');
    await expect(modal).toBeVisible();
    const prompt = page.locator('.weather-geo-prompt');
    await expect(prompt).toBeVisible();

    // Force-show the wind chart button so all three absolute buttons are measured.
    await page.locator('#weatherWindChartBtn').evaluate((el: HTMLElement) => { el.style.display = ''; });

    // The prompt's visible content (text + button) must not intersect the
    // absolutely positioned header buttons. (The container box itself includes
    // the reserved padding, so we measure its children.)
    const isMobile = (viewport?.width || 0) < 480;
    const modalScope = page.locator('#weatherModalOverlay');
    const contentBoxes = await prompt.evaluate((el) =>
      Array.from(el.children).map((c) => {
        const r = (c as HTMLElement).getBoundingClientRect();
        return { x: r.x, y: r.y, width: r.width, height: r.height };
      }));
    expect(contentBoxes.length).toBeGreaterThan(0);
    for (const sel of ['.weather-modal-close', '.weather-settings-btn', '.weather-wind-chart-btn']) {
      const btnBox = await modalScope.locator(sel).boundingBox();
      expect(btnBox, sel).not.toBeNull();
      for (const cb of contentBoxes) {
        const overlapX = Math.min(cb.x + cb.width, btnBox!.x + btnBox!.width) - Math.max(cb.x, btnBox!.x);
        const overlapY = Math.min(cb.y + cb.height, btnBox!.y + btnBox!.height) - Math.max(cb.y, btnBox!.y);
        expect(overlapX <= 0 || overlapY <= 0, `${sel} overlaps .weather-geo-prompt content (viewport ${viewport?.width}px, mobile=${isMobile})`).toBe(true);
      }
    }

    // The prompt content must start below the button row (buttons bottom at 60px).
    const topPad = await prompt.evaluate((el) => parseFloat(getComputedStyle(el).paddingTop));
    expect(topPad).toBeGreaterThanOrEqual(60);
  });
});
