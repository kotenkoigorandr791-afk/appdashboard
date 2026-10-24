import { test, expect } from '@playwright/test';

const ENDPOINT = '/apps/cloud/onlyoffice_editor.php';

test.describe('OnlyOffice callback security', () => {
  for (const query of ['callback=1&path=a.txt&user=x&root=main', 'action=download&path=a.txt&user=x&root=main']) {
    test(`rejects unsigned request: ${query}`, async ({ request }) => {
      const res = await request.post(`${ENDPOINT}?${query}`, {
        data: { status: 2, url: 'http://127.0.0.1:80/' },
        failOnStatusCode: false,
      });
      expect([401, 403, 404]).toContain(res.status());
    });
  }
});

test.describe('Cloud utility endpoints require auth', () => {
  for (const p of ['/apps/cloud/tts_api.php', '/apps/cloud/transcription_api.php']) {
    test(`anonymous traversal rejected: ${p}`, async ({ request }) => {
      const res = await request.post(`${p}?action=x`, { data: { path: '../../../etc/passwd' }, failOnStatusCode: false, maxRedirects: 0 });
      expect(res.status()).not.toBe(200);
    });
  }
});
