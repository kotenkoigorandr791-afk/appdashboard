import { expect, test, type Page } from '@playwright/test';
import { execSync } from 'node:child_process';
import { login } from '../helpers/shell';

/**
 * End-to-end coverage for the Steps Counter web app (apps/steps):
 * dashboard rendering, manual step entry, body settings, statistics/info/mobile
 * modals and the CSV/backup endpoints. Values are read back from Postgres so a
 * test fails when the UI reports success but nothing was persisted.
 */

const USER_EMAIL = 'root@root';
const STEPS_PATH = '/apps/steps/index.php?lang=en&theme=light&color=%23007bff';
// A fixed, long-past day so the tests never collide with real activity data.
const TEST_DATE = '2020-02-29';
const TEST_DATE_DMY = '29-02-20';
const ALT_DATE = '2020-03-01';
const IMPORT_DATE = '2020-03-02';

function sql(query: string): string {
  return execSync(
    `docker exec appdashboard-db psql -U appdashboard -d appdashboard -t -A -c "${query.replace(/"/g, '\\"')}"`,
    { stdio: 'pipe' },
  ).toString().trim();
}

function getDay(date: string): string {
  return sql(`select steps from steps_days where user_email='${USER_EMAIL}' and date='${date}'`);
}

function setDay(date: string, steps: number) {
  sql(
    `insert into steps_days (user_email, date, steps) values ('${USER_EMAIL}','${date}',${steps}) ` +
      `on conflict (user_email, date) do update set steps=excluded.steps`,
  );
}

function deleteDay(date: string) {
  sql(`delete from steps_days where user_email='${USER_EMAIL}' and date='${date}'`);
}

function getSetting(key: string): string {
  return sql(`select value from steps_settings where user_email='${USER_EMAIL}' and key='${key}'`);
}

function setSetting(key: string, value: string) {
  sql(
    `insert into steps_settings (user_email, key, value) values ('${USER_EMAIL}','${key}','${value}') ` +
      `on conflict (user_email, key) do update set value=excluded.value`,
  );
}

/** POST a form to the steps controller the way the page itself does. */
async function postSteps(page: Page, fields: Record<string, string>) {
  return page.evaluate(async ({ path, data }) => {
    const fd = new FormData();
    for (const [k, v] of Object.entries(data)) fd.append(k, v);
    const res = await fetch(path, { method: 'POST', body: fd });
    const text = await res.text();
    let json: unknown = null;
    try { json = JSON.parse(text); } catch { /* CSV or HTML */ }
    return { status: res.status, contentType: res.headers.get('content-type') || '', text, json };
  }, { path: STEPS_PATH, data: fields });
}

async function openSteps(page: Page) {
  await login(page);
  const res = await page.goto(STEPS_PATH, { waitUntil: 'domcontentloaded' });
  expect(res?.status(), 'steps page must render').toBe(200);
  await expect(page.locator('#calendar')).toBeVisible({ timeout: 20000 });
  await page.waitForFunction(() => document.querySelectorAll('#calendar [data-date]').length > 0, null, { timeout: 20000 });
}

test.describe('steps web app', () => {
  // Restored in afterAll so the shared root account keeps its history.
  const originalTestDay = getDay(TEST_DATE);
  const originalAltDay = getDay(ALT_DATE);
  const originalImportDay = getDay(IMPORT_DATE);
  const originalWeight = getSetting('weight');
  const originalGoal = getSetting('goal_steps');

  test.afterAll(() => {
    const restore = (date: string, original: string) => {
      if (original === '') deleteDay(date);
      else setDay(date, Number(original));
    };
    restore(TEST_DATE, originalTestDay);
    restore(ALT_DATE, originalAltDay);
    restore(IMPORT_DATE, originalImportDay);
    if (originalWeight !== '') setSetting('weight', originalWeight);
    if (originalGoal !== '') setSetting('goal_steps', originalGoal);
  });

  test('dashboard renders calendar, chart, totals and controls', async ({ page }) => {
    await openSteps(page);

    await expect(page).toHaveTitle(/Steps Counter/i);
    await expect(page.locator('#steps-chart')).toBeVisible();
    await expect(page.locator('#year-selector')).toBeVisible();
    await expect(page.locator('#month-selector')).toBeVisible();
    await expect(page.locator('#goal-summary')).not.toBeEmpty();

    for (const id of ['sharepoint-share-button', 'add-steps-button', 'stepmonitor-apk-button', 'stats-button', 'settings-button', 'info-button']) {
      await expect(page.locator(`#${id}`), `${id} must be visible`).toBeVisible();
    }

    const cells = await page.locator('#calendar [data-date]').count();
    expect(cells).toBeGreaterThanOrEqual(28);

    // Year / month / week views all re-render the chart without errors.
    for (const view of ['Year', 'Month', 'Week']) {
      await page.getByRole('button', { name: view, exact: true }).first().click();
      await expect(page.locator('#steps-chart')).toBeVisible();
    }
    const chartLabel = await page.locator('#chart-label').innerText();
    expect(chartLabel.length).toBeGreaterThan(0);
  });

  test('manual step entry persists and refreshes totals', async ({ page }) => {
    await openSteps(page);
    // Work in the month that owns the test day so the totals react to the save.
    await page.selectOption('#year-selector', '2020');
    await page.selectOption('#month-selector', '2020-2');
    await expect(page.locator('#goal-summary')).toContainText('0');

    await page.locator('#add-steps-button').click();
    await expect(page.locator('#add-steps-modal')).toBeVisible();

    await page.locator('#selected-date-input').fill(TEST_DATE);
    await page.locator('#selected-date-input').dispatchEvent('change');
    await page.locator('#steps-input').fill('4321');

    const [response] = await Promise.all([
      page.waitForResponse((r) => r.request().method() === 'POST' && r.url().includes('/apps/steps/'), { timeout: 20000 }),
      page.locator('#steps-input').press('Enter'),
    ]);
    expect(response.status()).toBe(200);
    expect(getDay(TEST_DATE)).toBe('4321');

    // Enter advances the modal to the next day; the saved value stays in the DB.
    await expect(page.locator('#selected-date-input')).toHaveValue('2020-03-01');
    await page.locator('#add-steps-modal .modal-close').click();
    await expect(page.locator('#add-steps-modal')).toBeHidden();

    // Calendar cell and month totals both pick the new value up.
    await expect(page.locator('#calendar [data-date="2020-02-29"]')).toHaveAttribute('title', /Steps: 4[\s,.]?321/);
    await expect(page.locator('#goal-summary')).toContainText(/4[\s,.]?321/);
  });

  test('body settings autosave into the database', async ({ page }) => {
    await openSteps(page);
    await page.locator('#settings-button').click();
    await expect(page.locator('#settings-modal')).toBeVisible();

    const weight = page.locator('#settings-form input[name="weight"]');
    await expect(weight).toHaveValue(/\d+/);
    const [response] = await Promise.all([
      page.waitForResponse((r) => r.request().method() === 'POST' && r.url().includes('/apps/steps/'), { timeout: 20000 }),
      weight.fill('78'),
    ]);
    const body = await response.json();
    expect(body.status).toBe('success');
    expect(getSetting('weight')).toBe('78');

    // Goal is stored in steps (thousands in the UI), so 9.5 -> 9500.
    const goal = page.locator('#settings-form input[name="goal_steps"]');
    const [goalResponse] = await Promise.all([
      page.waitForResponse((r) => r.request().method() === 'POST' && r.url().includes('/apps/steps/'), { timeout: 20000 }),
      goal.fill('9.5'),
    ]);
    expect((await goalResponse.json()).status).toBe('success');
    expect(getSetting('goal_steps')).toBe('9500');

    await page.locator('#settings-modal .modal-close').first().click();
    await expect(page.locator('#settings-modal')).toBeHidden();
  });

  test('settings rejects an invalid goal value', async ({ page }) => {
    await openSteps(page);
    const res = await postSteps(page, { settings: '1', goal_steps: '-5', height: '175', weight: '70', stride_length: '70', birth_date: '1994-01-01' });
    expect(res.status).toBe(200);
    expect((res.json as { status: string }).status).toBe('error');
  });

  test('statistics modal opens with charts', async ({ page }) => {
    await openSteps(page);
    await page.locator('#stats-button').click();
    await expect(page.locator('#stats-modal')).toBeVisible();
    await expect(page.locator('#stats-monthly-title')).toBeVisible();
    await expect(page.locator('#stats-months-chart')).toBeAttached();
    await expect(page.locator('#stats-years-chart')).toBeAttached();
    await page.locator('#stats-modal .modal-close').first().click();
    await expect(page.locator('#stats-modal')).toBeHidden();
  });

  test('info modal opens and closes', async ({ page }) => {
    await openSteps(page);
    await page.locator('#info-button').click();
    await expect(page.locator('#info-modal')).toBeVisible();
    await page.locator('#info-modal .modal-close').first().click();
    await expect(page.locator('#info-modal')).toBeHidden();
  });

  test('mobile app modal exposes the pairing section', async ({ page }) => {
    await openSteps(page);
    await page.locator('#stepmonitor-apk-button').click();
    await expect(page.locator('#mobile-app-modal')).toBeVisible();
    await expect(page.locator('#mobile-app-qr-code svg')).toBeVisible();
    // Either a pairing code can be generated or an existing token revoked.
    const canGenerate = await page.locator('#mobile-app-qr-generate').isVisible();
    const canRevoke = await page.locator('#mobile-app-token-revoke').isVisible();
    expect(canGenerate || canRevoke, 'pairing modal must offer generate or revoke').toBe(true);
    await page.locator('#mobile-app-modal .modal-close').first().click();
    await expect(page.locator('#mobile-app-modal')).toBeHidden();
  });

  test('backup lifecycle: create, list, download, restore, delete', async ({ page }) => {
    await openSteps(page);

    const created = await postSteps(page, { create_backup: '1' });
    const createdBody = created.json as { status: string; backup_file?: string; message?: string };
    expect(createdBody.status, createdBody.message).toBe('success');
    const backupFile = createdBody.backup_file || '';
    expect(backupFile).toMatch(/^Steps_backup_.*\.csv$/);

    const list = await postSteps(page, { get_backups_list: '1' });
    const backups = (list.json as { backups: { name: string }[] }).backups;
    expect(backups.some((b) => b.name === backupFile)).toBe(true);

    const download = await postSteps(page, { download_backup: '1' });
    expect(download.contentType).toContain('text/csv');
    expect(download.text).toMatch(/^\d{2}-\d{2}-\d{2},\d+$/m);

    const restored = await postSteps(page, { restore_backup: '1', backup_file: backupFile });
    expect((restored.json as { status: string }).status).toBe('success');

    const deleted = await postSteps(page, { delete_backup: '1', backup_file: backupFile });
    expect((deleted.json as { status: string }).status).toBe('success');

    const listAfter = await postSteps(page, { get_backups_list: '1' });
    const backupsAfter = (listAfter.json as { backups: { name: string }[] }).backups;
    expect(backupsAfter.some((b) => b.name === backupFile)).toBe(false);
  });

  test('backup endpoints reject unsafe file names', async ({ page }) => {
    await openSteps(page);
    // Note: names containing "../" never reach PHP - nginx rejects them with 403,
    // so the app-level allowlist is checked with names that do arrive.
    for (const name of ['Steps_backup_evil.sh', 'Before_restore_evil.csv', '']) {
      const res = await postSteps(page, { delete_backup: '1', backup_file: name });
      expect((res.json as { status: string }).status, `delete_backup must reject "${name}"`).toBe('error');
      const restore = await postSteps(page, { restore_backup: '1', backup_file: name });
      expect((restore.json as { status: string }).status, `restore_backup must reject "${name}"`).toBe('error');
    }
  });

  test('external CSV import merges rows', async ({ page }) => {
    await openSteps(page);
    deleteDay(IMPORT_DATE);

    const csv = `01-03-20,1234\n02-03-20,5678\n`;
    const res = await page.request.post(STEPS_PATH, {
      multipart: {
        import_external_backup: '1',
        import_mode: 'merge',
        backup_file: { name: 'external.csv', mimeType: 'text/csv', buffer: Buffer.from(csv) },
      },
    });
    expect(res.status()).toBe(200);
    const body = (await res.json()) as { status: string; count?: number };
    expect(body.status).toBe('success');
    expect(body.count).toBe(2);
    expect(getDay(ALT_DATE)).toBe('1234');
    expect(getDay(IMPORT_DATE)).toBe('5678');
  });

  test('table endpoints page rows and validate dates', async ({ page }) => {
    await openSteps(page);

    const pageRes = await postSteps(page, { get_table_page: '1', page: '1' });
    const pageBody = pageRes.json as { status: string; rows: { date: string; steps: number }[]; total: number };
    expect(pageBody.status).toBe('success');
    expect(Array.isArray(pageBody.rows)).toBe(true);
    expect(pageBody.total).toBeGreaterThan(0);
    for (const row of pageBody.rows) expect(row.date).toMatch(/^\d{4}-\d{2}-\d{2}$/);

    const filtered = await postSteps(page, { get_table_page: '1', page: '1', filter_op: '>', filter_val: '1000000' });
    const filteredBody = filtered.json as { rows: unknown[]; total: number };
    expect(filteredBody.rows.length).toBe(0);
    expect(filteredBody.total).toBe(0);

    const badDate = await postSteps(page, { save_table_data: '1', date: '2020/03/01', steps: '10' });
    expect((badDate.json as { status: string }).status).toBe('error');

    const saved = await postSteps(page, { save_table_data: '1', date: TEST_DATE_DMY, steps: '777' });
    expect((saved.json as { status: string }).status).toBe('success');
    expect(getDay(TEST_DATE)).toBe('777');
  });

  test('steps updates endpoint reports a version fingerprint', async ({ page }) => {
    await openSteps(page);
    // Write through the app so its cache generation is bumped before reading.
    const saved = await postSteps(page, { add_steps: '1', date: ALT_DATE, steps: '2222' });
    expect((saved.json as { status: string }).status).toBe('success');

    const changed = await postSteps(page, { get_steps_updates: '1', version: 'stale-version' });
    const body = changed.json as { status: string; changed: boolean; version: string; steps?: { date: string; steps: number }[] };
    expect(body.status).toBe('success');
    expect(body.changed).toBe(true);
    expect(body.version.length).toBeGreaterThan(0);
    expect(body.steps?.some((s) => s.date === ALT_DATE && s.steps === 2222)).toBe(true);

    const unchanged = await postSteps(page, { get_steps_updates: '1', version: body.version });
    expect((unchanged.json as { changed: boolean }).changed).toBe(false);
  });
});
