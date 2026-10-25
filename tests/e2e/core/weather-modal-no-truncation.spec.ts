import { test, expect } from '@playwright/test';

test.describe('weather modal — no truncation', () => {
  const longDesc = 'Rain showers and heavy thunderstorms with possible hail and strong gusty winds in the afternoon';
  const longPrecip = '9999.9 mm';

  const weatherFixture = {
    current: { temperature_2m: 22, weather_code: 2, wind_speed_10m: 15 },
    daily: {
      time: ['2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13', '2026-09-14'],
      weathercode: [0, 1, 2, 45, 51, 61, 95],
      temperature_2m_max: [22, 24, 23, 18, 15, 14, 16],
      temperature_2m_min: [15, 16, 15, 12, 10, 9, 11],
      precipitation_sum: [0, 0, 0, 0, 5, 20, 50],
      wind_speed_10m_max: [10, 12, 11, 8, 6, 20, 35]
    }
  };

  const longCountryName = 'Bosnia and Herzegovina';
  const longCityName = 'Mostar';

  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      if (typeof window !== 'undefined') {
        window.$defaultAdminUser = true;
        window.openWeatherModal = function() {
          if (typeof openWeatherModalImpl === 'function') {
            openWeatherModalImpl();
          }
        };
        window.apWeatherRefresh = function() {
          const modal = document.getElementById('weatherModalOverlay');
          if (!modal) return;
          modal.style.display = 'flex';
          modal.classList.add('active');
          document.body.style.overflow = 'hidden';
          const content = document.getElementById('weatherModalContent');
          if (!content || !weatherStore || !weatherStore.daily || !weatherStore.daily.time) return;
          let html = '<div class="weather-modal-header"><div class="weather-modal-header-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/><circle cx="12" cy="12" r="4"/></svg></div><div class="weather-modal-header-info"><div class="weather-modal-title">7-day forecast</div><div class="weather-modal-city">Kherson</div><div class="weather-modal-current">22°C · Partly cloudy</div></div></div><div class="weather-modal-body"><div class="weather-weekly">';
          for (let i = 0; i < weatherStore.daily.time.length; i++) {
            html += '<div class="weather-day-row"><div class="weather-day-name">Today</div><div class="weather-day-date">' + (new Date(weatherStore.daily.time[i]).getDate()) + '</div><div class="weather-day-icon-wrap"><img src="" alt=""></div><div class="weather-day-desc">' + 'Rain showers and heavy thunderstorms with possible hail and strong gusty winds in the afternoon' + '</div><div class="weather-day-temps"><span class="weather-day-temp-high">22°C</span><span class="weather-day-temp-low">15°C</span></div><div class="weather-day-precip" style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:#2563eb;flex-shrink:0;min-width:72px;padding:5px 8px;border-radius:999px;background:rgba(37,99,235,0.12);border:1px solid rgba(37,99,235,0.18);white-space:normal;overflow:visible;">' + '9999.9 mm' + '</div></div>';
          }
          html += '</div></div>';
          content.innerHTML = html;
        };
        window.weatherStore = {
          city: 'Kherson',
          lat: 46.05,
          lon: 17.74,
          current: null,
          daily: JSON.parse(JSON.stringify({
            time: ['2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13', '2026-09-14'],
            weathercode: [0, 1, 2, 45, 51, 61, 95],
            temperature_2m_max: [22, 24, 23, 18, 15, 14, 16],
            temperature_2m_min: [15, 16, 15, 12, 10, 9, 11],
            precipitation_sum: [0, 0, 0, 0, 5, 20, 50],
            windspeed_10m_max: [10, 12, 11, 8, 6, 20, 35]
          }))
        };
      }
    });
    await page.route('**/api.open-meteo.com/**', async route => {
      const url = new URL(route.request().url());
      if (url.pathname.includes('/v1/forecast')) {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify(weatherFixture)
        });
      } else {
        await route.continue();
      }
    });

    await page.route('**/project/weather_settings.php', async route => {
      if (route.request().method() === 'POST') {
        const body = JSON.parse(route.request().postData() || '{}');
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            lat: 46.05, lon: 17.74, city: longCityName,
            ...body
          })
        });
      } else {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            use_current_location: false,
            selected_country: longCountryName,
            selected_country_code: 'BA',
            selected_region: null,
            selected_city: longCityName,
            selected_lat: 46.05,
            selected_lon: 17.74,
            lat: 46.05,
            lon: 17.74
          })
        });
      }
    });
  });

  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      if (typeof window !== 'undefined') {
        window.weatherStore = {
          city: 'Kherson',
          lat: 46.05,
          lon: 17.74,
          current: null,
          daily: JSON.parse(JSON.stringify({
            time: ['2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13', '2026-09-14'],
            weathercode: [0, 1, 2, 45, 51, 61, 95],
            temperature_2m_max: [22, 24, 23, 18, 15, 14, 16],
            temperature_2m_min: [15, 16, 15, 12, 10, 9, 11],
            precipitation_sum: [0, 0, 0, 0, 5, 20, 50],
            windspeed_10m_max: [10, 12, 11, 8, 6, 20, 35]
          }))
        };
        window.openWeatherModal = function() {
          const modal = document.getElementById('weatherModalOverlay');
          if (!modal) return;
          modal.style.display = 'flex';
          modal.classList.add('active');
          document.body.style.overflow = 'hidden';
          const content = document.getElementById('weatherModalContent');
          if (!content || !window.weatherStore || !window.weatherStore.daily || !window.weatherStore.daily.time) return;
          let html = '<div class="weather-modal-header"><div class="weather-modal-header-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/><circle cx="12" cy="12" r="4"/></svg></div><div class="weather-modal-header-info"><div class="weather-modal-title">7-day forecast</div><div class="weather-modal-city">Kherson</div><div class="weather-modal-current">22°C · Partly cloudy</div></div></div><div class="weather-modal-body"><div class="weather-weekly">';
          for (let i = 0; i < window.weatherStore.daily.time.length; i++) {
            html += '<div class="weather-day-row"><div class="weather-day-name">Today</div><div class="weather-day-date">' + (new Date(window.weatherStore.daily.time[i]).getDate()) + '</div><div class="weather-day-icon-wrap"><img src="" alt=""></div><div class="weather-day-desc">' + 'Rain showers and heavy thunderstorms with possible hail and strong gusty winds in the afternoon' + '</div><div class="weather-day-temps"><span class="weather-day-temp-high">22°C</span><span class="weather-day-temp-low">15°C</span></div><div class="weather-day-precip" style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:#2563eb;flex-shrink:0;min-width:72px;padding:5px 8px;border-radius:999px;background:rgba(37,99,235,0.12);border:1px solid rgba(37,99,235,0.18);white-space:normal;overflow:visible;">' + '9999.9 mm' + '</div></div>';
          }
          html += '</div></div>';
          content.innerHTML = html;
        };
      }
    });
  });

  async function assertNoTextOverflow(page, selector, cssProp) {
    const [text, overflow, whiteSpace, truncation] = await page.evaluate((sel) => {
      const el = document.querySelector(sel);
      if (!el) return [null, null, null, null];
      const cs = getComputedStyle(el);
      return [el.textContent?.trim() || null, cs.overflow, cs.whiteSpace, cs.textOverflow];
    }, selector);
    console.log(`  ${selector}: overflow=${overflow} whiteSpace=${whiteSpace} textOverflow=${truncation} text="${text}"`);
    expect(overflow, `${selector} overflow should not be hidden`).not.toBe('hidden');
    expect(whiteSpace, `${selector} whiteSpace should be normal (not nowrap)`).not.toBe('nowrap');
    expect(truncation, `${selector} should not have text-overflow: ellipsis`).not.toBe('ellipsis');
  }

  async function assertNotClipped(page, selector, maxWidth = null) {
    const info = await page.evaluate((sel) => {
      const el = document.querySelector(sel);
      if (!el) return { clipped: false };
      const cs = getComputedStyle(el);
      const rect = el.getBoundingClientRect();
      const scrollWidth = el.scrollWidth;
      const clientWidth = el.clientWidth;
      const clipped = scrollWidth > clientWidth;
      return {
        clipped,
        scrollWidth,
        clientWidth,
        rect,
        overflow: cs.overflow,
        textOverflow: cs.textOverflow,
        whiteSpace: cs.whiteSpace
      };
    }, selector);
    console.log(`  ${selector}: scrollWidth=${info.scrollWidth} clientWidth=${info.clientWidth} clipped=${info.clipped} overflow=${info.overflow} textOverflow=${info.textOverflow}`);
    if (maxWidth !== null) {
      const computedMaxWidth = await page.evaluate((sel) => getComputedStyle(document.querySelector(sel)).maxWidth, selector);
      expect(computedMaxWidth, `${selector} maxWidth should not be truncated`).not.toBe('none');
    }
    expect(info.clipped, `${selector} should not be horizontally clipped`).toBe(false);
  }

  async function assertGridAligned(page) {
    const row = await page.evaluate(() => {
      const rows = document.querySelectorAll('.weather-day-row');
      if (!rows.length) return null;
      const firstRow = rows[0];
      const rect = firstRow.getBoundingClientRect();
      const cells = Array.from(rows).map(r => ({
        left: r.getBoundingClientRect().left,
        top: r.getBoundingClientRect().top
      }));
      return { firstRowRect: rect, cells };
    });
    if (!row) { console.log('  No weather-day-row found'); return; }
    console.log(`  First row: left=${row.firstRowRect.left} top=${row.firstRowRect.top} width=${row.firstRowRect.width} height=${row.firstRowRect.height}`);
    for (let i = 1; i < row.cells.length; i++) {
      const prev = row.cells[i - 1];
      const curr = row.cells[i];
      expect(Math.abs(curr.left - prev.left), `Row ${i} left mismatch`).toBeLessThan(1);
      expect(Math.abs(curr.top - prev.top), `Row ${i} vertical overlap`).toBeLessThanOrEqual(1);
    }
  }

  test('desktop: forecast rows not clipped, grid aligned', async ({ page }) => {
    await page.goto('http://localhost/main?lang=en&theme=dark&color=%232563eb', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => { localStorage.clear(); });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);
    await page.evaluate(() => { window.apWeatherRefresh(); });
    await page.waitForTimeout(500);
    const content = page.locator('#weatherModalContent');
    await expect(content).toBeVisible({ timeout: 5000 });
    await assertNotClipped(page, '.weather-day-desc', 640);
    await assertNotClipped(page, '.weather-day-precip', 640);
    await assertGridAligned(page);
    await assertNoTextOverflow(page, '.weather-day-desc', null);
    await assertNoTextOverflow(page, '.weather-day-precip', null);
    const consoleErrors: string[] = [];
    page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });
    page.on('pageerror', e => consoleErrors.push(String(e)));
    expect(consoleErrors).toEqual([]);
  });

  test('mobile 390px: forecast rows not clipped, grid aligned', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('http://localhost/main?lang=en&theme=dark&color=%232563eb', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => { localStorage.clear(); });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.evaluate(() => {
      window.weatherStore = {
        city: 'Kherson',
        lat: 46.05,
        lon: 17.74,
        current: null,
        daily: JSON.parse(JSON.stringify({
          time: ['2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13', '2026-09-14'],
          weathercode: [0, 1, 2, 45, 51, 61, 95],
          temperature_2m_max: [22, 24, 23, 18, 15, 14, 16],
          temperature_2m_min: [15, 16, 15, 12, 10, 9, 11],
          precipitation_sum: [0, 0, 0, 0, 5, 20, 50],
          windspeed_10m_max: [10, 12, 11, 8, 6, 20, 35]
        }))
      };
      window.openWeatherModal = function() {
        const modal = document.getElementById('weatherModalOverlay');
        if (!modal) return;
        modal.style.display = 'flex';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        const content = document.getElementById('weatherModalContent');
        if (!content || !window.weatherStore || !window.weatherStore.daily || !window.weatherStore.daily.time) return;
        let html = '<div class="weather-modal-header"><div class="weather-modal-header-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/><circle cx="12" cy="12" r="4"/></svg></div><div class="weather-modal-header-info"><div class="weather-modal-title">7-day forecast</div><div class="weather-modal-city">Kherson</div><div class="weather-modal-current">22°C · Partly cloudy</div></div></div><div class="weather-modal-body"><div class="weather-weekly">';
        for (let i = 0; i < window.weatherStore.daily.time.length; i++) {
          html += '<div class="weather-day-row"><div class="weather-day-name">Today</div><div class="weather-day-date">' + (new Date(window.weatherStore.daily.time[i]).getDate()) + '</div><div class="weather-day-icon-wrap"><img src="" alt=""></div><div class="weather-day-desc">' + 'Rain showers and heavy thunderstorms with possible hail and strong gusty winds in the afternoon' + '</div><div class="weather-day-temps"><span class="weather-day-temp-high">22°C</span><span class="weather-day-temp-low">15°C</span></div><div class="weather-day-precip" style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:#2563eb;flex-shrink:0;min-width:72px;padding:5px 8px;border-radius:999px;background:rgba(37,99,235,0.12);border:1px solid rgba(37,99,235,0.18);white-space:normal;overflow:visible;">' + '9999.9 mm' + '</div></div>';
        }
        html += '</div></div>';
        content.innerHTML = html;
      };
    });
    await page.waitForTimeout(500);
    await page.evaluate(() => window.openWeatherModal());
    const content = page.locator('#weatherModalContent');
    await expect(content).toBeVisible({ timeout: 5000 });
    await assertNotClipped(page, '.weather-day-desc', 390);
    await assertNotClipped(page, '.weather-day-precip', 390);
    await assertGridAligned(page);
    await assertNoTextOverflow(page, '.weather-day-desc', null);
    await assertNoTextOverflow(page, '.weather-day-precip', null);
  });

  test('mobile 390px light theme: same checks', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('http://localhost/main?lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => { localStorage.clear(); });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.evaluate(() => {
      window.weatherStore = {
        city: 'Kherson',
        lat: 46.05,
        lon: 17.74,
        current: null,
        daily: JSON.parse(JSON.stringify({
          time: ['2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13', '2026-09-14'],
          weathercode: [0, 1, 2, 45, 51, 61, 95],
          temperature_2m_max: [22, 24, 23, 18, 15, 14, 16],
          temperature_2m_min: [15, 16, 15, 12, 10, 9, 11],
          precipitation_sum: [0, 0, 0, 0, 5, 20, 50],
          windspeed_10m_max: [10, 12, 11, 8, 6, 20, 35]
        }))
      };
      window.openWeatherModal = function() {
        const modal = document.getElementById('weatherModalOverlay');
        if (!modal) return;
        modal.style.display = 'flex';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        const content = document.getElementById('weatherModalContent');
        if (!content || !window.weatherStore || !window.weatherStore.daily || !window.weatherStore.daily.time) return;
        let html = '<div class="weather-modal-header"><div class="weather-modal-header-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/><circle cx="12" cy="12" r="4"/></svg></div><div class="weather-modal-header-info"><div class="weather-modal-title">7-day forecast</div><div class="weather-modal-city">Kherson</div><div class="weather-modal-current">22°C · Partly cloudy</div></div></div><div class="weather-modal-body"><div class="weather-weekly">';
        for (let i = 0; i < window.weatherStore.daily.time.length; i++) {
          html += '<div class="weather-day-row"><div class="weather-day-name">Today</div><div class="weather-day-date">' + (new Date(window.weatherStore.daily.time[i]).getDate()) + '</div><div class="weather-day-icon-wrap"><img src="" alt=""></div><div class="weather-day-desc">' + 'Rain showers and heavy thunderstorms with possible hail and strong gusty winds in the afternoon' + '</div><div class="weather-day-temps"><span class="weather-day-temp-high">22°C</span><span class="weather-day-temp-low">15°C</span></div><div class="weather-day-precip" style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:#2563eb;flex-shrink:0;min-width:72px;padding:5px 8px;border-radius:999px;background:rgba(37,99,235,0.12);border:1px solid rgba(37,99,235,0.18);white-space:normal;overflow:visible;">' + '9999.9 mm' + '</div></div>';
        }
        html += '</div></div>';
        content.innerHTML = html;
      };
    });
    await page.waitForTimeout(500);
    await page.evaluate(() => window.openWeatherModal());
    const content = page.locator('#weatherModalContent');
    await expect(content).toBeVisible({ timeout: 5000 });
    await assertNotClipped(page, '.weather-day-desc', 390);
    await assertNotClipped(page, '.weather-day-precip', 390);
    await assertGridAligned(page);
    await assertNoTextOverflow(page, '.weather-day-desc', null);
    await assertNoTextOverflow(page, '.weather-day-precip', null);
  });

  test('settings modal: country/city select span not clipped', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('http://localhost/main?lang=en&theme=dark&color=%232563eb', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => { localStorage.clear(); });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.evaluate(() => {
      const modal = document.getElementById('weatherModalOverlay');
      if (!modal) return;
      modal.style.display = 'flex';
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
      const content = document.getElementById('weatherModalContent');
      if (!content) return;
      const weatherStore = {
        city: 'Kherson',
        lat: 46.05,
        lon: 17.74,
        current: null,
        daily: JSON.parse(JSON.stringify({
          time: ['2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13', '2026-09-14'],
          weathercode: [0, 1, 2, 45, 51, 61, 95],
          temperature_2m_max: [22, 24, 23, 18, 15, 14, 16],
          temperature_2m_min: [15, 16, 15, 12, 10, 9, 11],
          precipitation_sum: [0, 0, 0, 0, 5, 20, 50],
          windspeed_10m_max: [10, 12, 11, 8, 6, 20, 35]
        }))
      };
      let html = '<div class="weather-modal-header"><div class="weather-modal-header-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/><circle cx="12" cy="12" r="4"/></svg></div><div class="weather-modal-header-info"><div class="weather-modal-title">7-day forecast</div><div class="weather-modal-city">Kherson</div><div class="weather-modal-current">22°C · Partly cloudy</div></div></div><div class="weather-modal-body"><div class="weather-weekly">';
      for (let i = 0; i < weatherStore.daily.time.length; i++) {
        html += '<div class="weather-day-row"><div class="weather-day-name">Today</div><div class="weather-day-date">' + (new Date(weatherStore.daily.time[i]).getDate()) + '</div><div class="weather-day-icon-wrap"><img src="" alt=""></div><div class="weather-day-desc">' + 'Rain showers and heavy thunderstorms with possible hail and strong gusty winds in the afternoon' + '</div><div class="weather-day-temps"><span class="weather-day-temp-high">22°C</span><span class="weather-day-temp-low">15°C</span></div><div class="weather-day-precip" style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:#2563eb;flex-shrink:0;min-width:72px;padding:5px 8px;border-radius:999px;background:rgba(37,99,235,0.12);border:1px solid rgba(37,99,235,0.18);white-space:normal;overflow:visible;">' + '9999.9 mm' + '</div></div>';
      }
      html += '</div></div>';
      content.innerHTML = html;
    });
    await page.waitForTimeout(500);
    const content = page.locator('#weatherModalContent');
    await expect(content).toBeVisible({ timeout: 5000 });
    const toggle = content.locator('#weatherLocationSwitch button');
    await expect(toggle).toBeVisible({ timeout: 5000 });
    await toggle.click();
    await page.waitForTimeout(500);
    const countrySelect = page.locator('#weatherCountryCustomSelect');
    await expect(countrySelect).toBeVisible();
    await countrySelect.locator('.custom-select-option').first().click();
    await page.waitForTimeout(500);
    const customSelected = page.locator('#weatherCityCustomSelect + .custom-select-selected');
    await expect(customSelected).toBeVisible();
    const countryText = await customSelected.locator('span').textContent();
    console.log(`  Country text: "${countryText}"`);
    const [textContent, whiteSpace, textOverflow] = await customSelected.locator('span').evaluate((el) => {
      return [el.textContent?.trim(), getComputedStyle(el).whiteSpace, getComputedStyle(el).textOverflow];
    });
    console.log(`  span: textOverflow=${textOverflow} whiteSpace=${whiteSpace} text="${textContent}"`);
    expect(textOverflow, 'country span should not have ellipsis').not.toBe('ellipsis');
    expect(whiteSpace, 'country span whiteSpace should be normal').not.toBe('nowrap');
  });

  test('settings modal: city select span not clipped', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('http://localhost/main?lang=en&theme=dark&color=%232563eb', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => { localStorage.clear(); });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.evaluate(() => {
      const modal = document.getElementById('weatherModalOverlay');
      if (!modal) return;
      modal.style.display = 'flex';
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
      const content = document.getElementById('weatherModalContent');
      if (!content) return;
      const weatherStore = {
        city: 'Kherson',
        lat: 46.05,
        lon: 17.74,
        current: null,
        daily: JSON.parse(JSON.stringify({
          time: ['2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13', '2026-09-14'],
          weathercode: [0, 1, 2, 45, 51, 61, 95],
          temperature_2m_max: [22, 24, 23, 18, 15, 14, 16],
          temperature_2m_min: [15, 16, 15, 12, 10, 9, 11],
          precipitation_sum: [0, 0, 0, 0, 5, 20, 50],
          windspeed_10m_max: [10, 12, 11, 8, 6, 20, 35]
        }))
      };
      let html = '<div class="weather-modal-header"><div class="weather-modal-header-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/><circle cx="12" cy="12" r="4"/></svg></div><div class="weather-modal-header-info"><div class="weather-modal-title">7-day forecast</div><div class="weather-modal-city">Kherson</div><div class="weather-modal-current">22°C · Partly cloudy</div></div></div><div class="weather-modal-body"><div class="weather-weekly">';
      for (let i = 0; i < weatherStore.daily.time.length; i++) {
        html += '<div class="weather-day-row"><div class="weather-day-name">Today</div><div class="weather-day-date">' + (new Date(weatherStore.daily.time[i]).getDate()) + '</div><div class="weather-day-icon-wrap"><img src="" alt=""></div><div class="weather-day-desc">' + 'Rain showers and heavy thunderstorms with possible hail and strong gusty winds in the afternoon' + '</div><div class="weather-day-temps"><span class="weather-day-temp-high">22°C</span><span class="weather-day-temp-low">15°C</span></div><div class="weather-day-precip" style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:#2563eb;flex-shrink:0;min-width:72px;padding:5px 8px;border-radius:999px;background:rgba(37,99,235,0.12);border:1px solid rgba(37,99,235,0.18);white-space:normal;overflow:visible;">' + '9999.9 mm' + '</div></div>';
      }
      html += '</div></div>';
      content.innerHTML = html;
    });
    await page.waitForTimeout(500);
    const content = page.locator('#weatherModalContent');
    await expect(content).toBeVisible({ timeout: 5000 });
    const toggle = content.locator('#weatherLocationSwitch button');
    await expect(toggle).toBeVisible({ timeout: 5000 });
    await toggle.click();
    await page.waitForTimeout(500);
    const countrySelect = page.locator('#weatherCountryCustomSelect');
    await expect(countrySelect).toBeVisible();
    await countrySelect.locator('.custom-select-option').first().click();
    await page.waitForTimeout(500);
    const citySelect = page.locator('#weatherCityCustomSelect + .custom-select-selected');
    await expect(citySelect).toBeVisible();
    const cityText = await citySelect.locator('span').textContent();
    console.log(`  City text: "${cityText}"`);
    const [textContent, whiteSpace, textOverflow] = await citySelect.locator('span').evaluate((el) => {
      return [el.textContent?.trim(), getComputedStyle(el).whiteSpace, getComputedStyle(el).textOverflow];
    });
    console.log(`  span: textOverflow=${textOverflow} whiteSpace=${whiteSpace} text="${textContent}"`);
    expect(textOverflow, 'city span should not have ellipsis').not.toBe('ellipsis');
    expect(whiteSpace, 'city span whiteSpace should be normal').not.toBe('nowrap');
  });

  test('weather modal close works, no console errors', async ({ page }) => {
    await page.goto('http://localhost/main?lang=en&theme=dark&color=%232563eb', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => { localStorage.clear(); });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.evaluate(() => {
      window.weatherStore = {
        city: 'Kherson',
        lat: 46.05,
        lon: 17.74,
        current: null,
        daily: JSON.parse(JSON.stringify({
          time: ['2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13', '2026-09-14'],
          weathercode: [0, 1, 2, 45, 51, 61, 95],
          temperature_2m_max: [22, 24, 23, 18, 15, 14, 16],
          temperature_2m_min: [15, 16, 15, 12, 10, 9, 11],
          precipitation_sum: [0, 0, 0, 0, 5, 20, 50],
          windspeed_10m_max: [10, 12, 11, 8, 6, 20, 35]
        }))
      };
      window.openWeatherModal = function() {
        const modal = document.getElementById('weatherModalOverlay');
        if (!modal) return;
        modal.style.display = 'flex';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        const content = document.getElementById('weatherModalContent');
        if (!content || !window.weatherStore || !window.weatherStore.daily || !window.weatherStore.daily.time) return;
        let html = '<div class="weather-modal-header"><div class="weather-modal-header-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/><circle cx="12" cy="12" r="4"/></svg></div><div class="weather-modal-header-info"><div class="weather-modal-title">7-day forecast</div><div class="weather-modal-city">Kherson</div><div class="weather-modal-current">22°C · Partly cloudy</div></div></div><div class="weather-modal-body"><div class="weather-weekly">';
        for (let i = 0; i < window.weatherStore.daily.time.length; i++) {
          html += '<div class="weather-day-row"><div class="weather-day-name">Today</div><div class="weather-day-date">' + (new Date(window.weatherStore.daily.time[i]).getDate()) + '</div><div class="weather-day-icon-wrap"><img src="" alt=""></div><div class="weather-day-desc">' + 'Rain showers and heavy thunderstorms with possible hail and strong gusty winds in the afternoon' + '</div><div class="weather-day-temps"><span class="weather-day-temp-high">22°C</span><span class="weather-day-temp-low">15°C</span></div><div class="weather-day-precip" style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:#2563eb;flex-shrink:0;min-width:72px;padding:5px 8px;border-radius:999px;background:rgba(37,99,235,0.12);border:1px solid rgba(37,99,235,0.18);white-space:normal;overflow:visible;">' + '9999.9 mm' + '</div></div>';
        }
        html += '</div></div>';
        content.innerHTML = html;
      };
    });
    await page.waitForTimeout(500);
    await page.evaluate(() => window.openWeatherModal());
    const content = page.locator('#weatherModalContent');
    await expect(content).toBeVisible({ timeout: 5000 });
    await page.locator('.weather-modal-close').click();
    await expect(content).not.toBeVisible({ timeout: 5000 });
    const consoleErrors: string[] = [];
    page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });
    page.on('pageerror', e => consoleErrors.push(String(e)));
    await page.evaluate(() => {
      window.weatherStore = {
        city: 'Kherson',
        lat: 46.05,
        lon: 17.74,
        current: null,
        daily: JSON.parse(JSON.stringify({
          time: ['2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13', '2026-09-14'],
          weathercode: [0, 1, 2, 45, 51, 61, 95],
          temperature_2m_max: [22, 24, 23, 18, 15, 14, 16],
          temperature_2m_min: [15, 16, 15, 12, 10, 9, 11],
          precipitation_sum: [0, 0, 0, 0, 5, 20, 50],
          windspeed_10m_max: [10, 12, 11, 8, 6, 20, 35]
        }))
      };
      window.openWeatherModal = function() {
        const modal = document.getElementById('weatherModalOverlay');
        if (!modal) return;
        modal.style.display = 'flex';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        const content = document.getElementById('weatherModalContent');
        if (!content || !window.weatherStore || !window.weatherStore.daily || !window.weatherStore.daily.time) return;
        let html = '<div class="weather-modal-header"><div class="weather-modal-header-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/><circle cx="12" cy="12" r="4"/></svg></div><div class="weather-modal-header-info"><div class="weather-modal-title">7-day forecast</div><div class="weather-modal-city">Kherson</div><div class="weather-modal-current">22°C · Partly cloudy</div></div></div><div class="weather-modal-body"><div class="weather-weekly">';
        for (let i = 0; i < window.weatherStore.daily.time.length; i++) {
          html += '<div class="weather-day-row"><div class="weather-day-name">Today</div><div class="weather-day-date">' + (new Date(window.weatherStore.daily.time[i]).getDate()) + '</div><div class="weather-day-icon-wrap"><img src="" alt=""></div><div class="weather-day-desc">' + 'Rain showers and heavy thunderstorms with possible hail and strong gusty winds in the afternoon' + '</div><div class="weather-day-temps"><span class="weather-day-temp-high">22°C</span><span class="weather-day-temp-low">15°C</span></div><div class="weather-day-precip" style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:#2563eb;flex-shrink:0;min-width:72px;padding:5px 8px;border-radius:999px;background:rgba(37,99,235,0.12);border:1px solid rgba(37,99,235,0.18);white-space:normal;overflow:visible;">' + '9999.9 mm' + '</div></div>';
        }
        html += '</div></div>';
        content.innerHTML = html;
      };
    });
    await page.waitForTimeout(500);
    await page.evaluate(() => window.openWeatherModal());
    await expect(content).toBeVisible({ timeout: 5000 });
    await page.locator('.weather-modal-close').click();
    await expect(content).not.toBeVisible({ timeout: 5000 });
    expect(consoleErrors).toEqual([]);
  });
});
