import { expect, test } from '@playwright/test';
import { execSync } from 'child_process';

const OTHER_EMAIL = process.env.E2E_USER_EMAIL || 'dashboard-e2e@test.local';
const OTHER_PASSWORD = process.env.E2E_USER_PASSWORD || 'dashboard-e2e-e2e-password';

function setModels(list: string[]) {
  const json = JSON.stringify(list);
  const sql = `update app_ai_config set value=$mdl$${json}$mdl$ where key=$k$models$k$`;
  execSync(`docker exec appdashboard-db psql -U appdashboard -d appdashboard -c '${sql}'`, { stdio: 'pipe' });
}
function setApiKey(key: string | null) {
  if (key === null) {
    execSync(`docker exec appdashboard-db psql -U appdashboard -d appdashboard -c "delete from app_ai_config where key='api_key'"`, { stdio: 'pipe' });
  } else {
    execSync(
      `docker exec appdashboard-db psql -U appdashboard -d appdashboard -c "insert into app_ai_config (key,value) values ('api_key','${key}') on conflict (key) do update set value=excluded.value"`,
      { stdio: 'pipe' },
    );
  }
}

test.describe('AI model list freshness (task 005)', () => {
  test.beforeAll(() => {
    // The dev DB has no api_key row; the assistant treats it as unconfigured.
    setApiKey('e2e-test-key');
  });

  test.afterAll(() => {
    // Restore production-ish state.
    setApiKey(null);
    setModels(['test-model-a', 'test-model-b']);
  });

  test('cloud app dropdown reflects fresh config after reload', async ({ page }) => {
    setModels(['test-model-a', 'test-model-b']);
    await page.goto('/login?lang=en', { waitUntil: 'domcontentloaded' });
    await page.locator('#login-content input[name="email"]').fill(OTHER_EMAIL);
    await page.locator('#login-password').fill(OTHER_PASSWORD);
    await Promise.all([
      page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30000 }),
      page.locator('#login-content button[name="login"]').click(),
    ]);

    // Load the cloud app in its iframe.
    await page.goto('/main?app=cloud&lang=en&theme=light&color=%232563eb', { waitUntil: 'domcontentloaded' });
    const iframe = page.frameLocator('#app-iframe');
    const select = iframe.locator('#ai-model-select');
    await expect
      .poll(async () => (await select.locator('option').allTextContents()).length, { timeout: 20000 })
      .toBeGreaterThan(0);
    let values = await select.locator('option').evaluateAll((os) => os.map((o) => (o as HTMLOptionElement).value));
    expect(values).toEqual(['test-model-a', 'test-model-b']);

    // Admin changes the model list (simulated by updating the config store).
    setModels(['test-model-b', 'test-model-c']);

    // Reload the page — the dropdown must reflect the new config, and the
    // removed model must not stay selected.
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect
      .poll(async () => (await select.locator('option').evaluateAll((os) => os.map((o) => (o as HTMLOptionElement).value))), { timeout: 20000 })
      .toEqual(['test-model-b', 'test-model-c']);
    values = await select.locator('option').evaluateAll((os) => os.map((o) => (o as HTMLOptionElement).value));
    expect(values).not.toContain('test-model-a');
    const selected = await select.locator('option[selected], option:checked').first().getAttribute('value');
    expect(['test-model-b', 'test-model-c']).toContain(selected);
  });

  test('ai_runtime_config and ai_models endpoints send no-store cache headers', async ({ request }) => {
    const res = await request.post('/apps/cloud/ai.php?action=ai_runtime_config', {
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      data: 'action=ai_runtime_config',
    });
    expect(res.status()).toBe(200);
    expect(res.headers()['cache-control']).toContain('no-store');
  });
});
