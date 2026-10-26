import { test, expect, type Page } from '@playwright/test';
import { execSync } from 'child_process';
import {
  openCloud,
  reloadCloud,
  ensureFile,
  deletePaths,
  uniqueName,
  skipAuth,
  type CloudFrame,
} from '../helpers/cloud';

test.describe.configure({ timeout: 180000 });

function setAiKey(key: string | null) {
  if (key === null) {
    execSync(
      `docker exec appdashboard-db psql -U appdashboard -d appdashboard -c "delete from app_ai_config where key='api_key'"`,
      { stdio: 'pipe' }
    );
    return;
  }
  execSync(
    `docker exec appdashboard-db psql -U appdashboard -d appdashboard -c "insert into app_ai_config (key,value) values ('api_key','${key}') on conflict (key) do update set value=excluded.value"`,
    { stdio: 'pipe' }
  );
}

function setAiModels(list: string[]) {
  const json = JSON.stringify(list);
  const sql = `update app_ai_config set value=$mdl$${json}$mdl$ where key=$k$models$k$`;
  execSync(`docker exec appdashboard-db psql -U appdashboard -d appdashboard -c '${sql}'`, {
    stdio: 'pipe',
  });
}

async function openAiModal(frame: CloudFrame) {
  await frame.locator('#btn-ai').waitFor({ state: 'visible', timeout: 15000 });
  await frame.locator('body').evaluate(() => {
    const w = window as any;
    if (typeof w.toggleAIModal === 'function') w.toggleAIModal();
    else if (w.aiAssistant?.toggleModal) w.aiAssistant.toggleModal();
    else document.getElementById('btn-ai')?.click();
  });
  await expect(frame.locator('#ai-modal:not(.hidden)')).toBeVisible({ timeout: 15000 });
}

async function mockAiCompletions(page: Page, reply = 'E2E AI reply OK') {
  await page.route('**/apps/cloud/ai.php**', async route => {
    const url = route.request().url();
    if (!url.includes('action=ai_completions')) {
      await route.continue();
      return;
    }
    const chunk = JSON.stringify({
      id: 'e2e-chatcmpl',
      object: 'chat.completion.chunk',
      choices: [{ index: 0, delta: { role: 'assistant', content: reply }, finish_reason: null }],
    });
    const done = JSON.stringify({
      id: 'e2e-chatcmpl',
      object: 'chat.completion.chunk',
      choices: [{ index: 0, delta: {}, finish_reason: 'stop' }],
    });
    await route.fulfill({
      status: 200,
      headers: { 'Content-Type': 'text/event-stream; charset=utf-8', 'Cache-Control': 'no-cache' },
      body: `data: ${chunk}\n\ndata: ${done}\n\ndata: [DONE]\n\n`,
    });
  });
}

test.describe('AI assistant', () => {
  test.use({ viewport: { width: 1500, height: 950 } });

  let frame: CloudFrame;
  const created: string[] = [];

  test.beforeAll(() => {
    setAiModels(['e2e-model-a', 'e2e-model-b']);
    setAiKey('e2e-test-key');
  });

  test.afterAll(() => {
    setAiKey(null);
    setAiModels(['test-model-a', 'test-model-b']);
  });

  test.beforeEach(async ({ page }) => {
    skipAuth(test);
    test.skip((page.viewportSize()?.width || 0) < 769, 'desktop AI modal');
    frame = await openCloud(page);
    created.length = 0;
  });

  test.afterEach(async () => {
    if (!frame) return;
    await deletePaths(frame, [...created].reverse()).catch(() => {});
  });

  test('opens modal with model list and settings role', async () => {
    await openAiModal(frame);
    await expect(frame.locator('#ai-model-select')).toBeVisible();
    await expect(frame.locator('#ai-input-text')).toBeVisible();
    await expect(frame.locator('#btn-ai-send')).toBeVisible();
    await expect
      .poll(async () => frame.locator('#ai-model-select option').count(), { timeout: 20000 })
      .toBeGreaterThan(0);
    const values = await frame.locator('#ai-model-select option').evaluateAll(os =>
      os.map(o => (o as HTMLOptionElement).value)
    );
    expect(values).toEqual(expect.arrayContaining(['e2e-model-a', 'e2e-model-b']));
    await frame.locator('body').evaluate(() => {
      const modal = document.getElementById('ai-controls-modal');
      if (!modal) throw new Error('ai-controls-modal missing');
      modal.classList.remove('hidden');
    });
    await expect(frame.locator('#ai-controls-modal')).not.toHaveClass(/hidden/, { timeout: 10000 });
    await frame.locator('#ai-role-select').selectOption('programmer');
    await expect(frame.locator('#ai-role-select')).toHaveValue('programmer');
    await frame.locator('body').evaluate(() => {
      document.getElementById('ai-controls-modal')?.classList.add('hidden');
    });
    await frame.locator('#btn-close-ai').click();
    await expect(frame.locator('#ai-modal')).toHaveClass(/hidden/, { timeout: 10000 });
  });

  test('send without API key surfaces configuration error', async ({ page }) => {
    setAiKey(null);
    frame = await reloadCloud(page);
    await openAiModal(frame);
    await frame.locator('body').evaluate(() => {
      localStorage.setItem('ai_chat_mode', 'chat');
      const mode = document.getElementById('ai-mode-select') as HTMLInputElement | null;
      if (mode) {
        mode.checked = false;
        mode.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    await frame.locator('#ai-input-text').fill('ping without key');
    await frame.locator('#btn-ai-send').click();
    await expect
      .poll(async () => {
        const status = await frame.locator('#status-flash, #status-right-content').allTextContents();
        const chat = await frame.locator('#ai-chat').innerText().catch(() => '');
        return `${status.join(' ')}\n${chat}`;
      }, { timeout: 20000 })
      .toMatch(/not configured|configuration|API key|не настроен|конфігур|Failed to load AI/i);
    setAiKey('e2e-test-key');
  });

  test('mocked completion renders user and assistant messages', async ({ page }) => {
    setAiKey('e2e-test-key');
    frame = await reloadCloud(page);
    await mockAiCompletions(page, 'E2E AI reply OK');
    await openAiModal(frame);
    await frame.locator('body').evaluate(() => {
      localStorage.setItem('ai_chat_mode', 'chat');
      const mode = document.getElementById('ai-mode-select') as HTMLInputElement | null;
      if (mode) {
        mode.checked = false;
        mode.dispatchEvent(new Event('change', { bubbles: true }));
      }
      document.getElementById('btn-ai-new-chat')?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    });
    await frame.locator('#ai-input-text').fill('Say hello for e2e');
    await frame.locator('#btn-ai-send').click();
    await expect(frame.locator('#ai-chat .message.user-message').last()).toContainText('Say hello for e2e', {
      timeout: 20000,
    });
    await expect
      .poll(async () => frame.locator('#ai-chat').innerText(), { timeout: 45000 })
      .toContain('E2E AI reply OK');
  });

  test('chat session create and clear APIs work', async () => {
    await openAiModal(frame);
    const createdChat = await frame.locator('body').evaluate(async () => {
      const url = new URL((window as any).APP_CONFIG?.aiApiBase || 'ai.php', window.location.href);
      const make = async (action: string, extra: Record<string, string> = {}) => {
        const res = await fetch(url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ action, ...extra }),
        });
        return res.json();
      };
      const created = await make('ai_chat_create');
      const cleared = created?.chat_id
        ? await make('ai_chat_clear', { chat_id: String(created.chat_id) })
        : null;
      return { created, cleared };
    });
    expect(createdChat.created?.success ?? createdChat.created?.chat_id).toBeTruthy();
    if (createdChat.cleared) {
      expect(createdChat.cleared.success ?? createdChat.cleared.ok ?? true).toBeTruthy();
    }
  });

  test('attaching tree file adds mention chip', async () => {
    const path = uniqueName('e2e-ai-mention', 'txt');
    created.push(path);
    await ensureFile(frame, path, 'mention me\n');
    await openAiModal(frame);
    await frame.locator('body').evaluate((_el, p) => {
      const w = window as any;
      if (w.aiAssistant?.attachMentionFromTree) w.aiAssistant.attachMentionFromTree(p, 'temp');
      else throw new Error('attachMentionFromTree missing');
    }, path);
    await expect(frame.locator('.mentioned-file-chip').first()).toBeVisible({ timeout: 10000 });
  });
});
