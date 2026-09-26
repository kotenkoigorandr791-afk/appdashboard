import { chromium } from '@playwright/test';
import fs from 'node:fs';
const OUT = '/tmp/spends-shots';
fs.mkdirSync(OUT, { recursive: true });
const b = await chromium.launch();

async function login(p) {
  await p.goto('http://localhost/login?lang=en&theme=light');
  await p.fill('#login-content input[name="email"]', 'root@root');
  await p.fill('#login-password', '12345T');
  await Promise.all([
    p.waitForURL(u => !String(u).includes('/login'), { timeout: 30000 }),
    p.click('#login-content button[name="login"]')
  ]);
}

const results = [];
for (const [name, vp, theme] of [
  ['desktop-dark', {width:1440,height:900}, 'dark'],
  ['mobile-dark', {width:390,height:844}, 'dark'],
  ['desktop-light', {width:1440,height:900}, 'light'],
]) {
  const p = await b.newPage({ viewport: vp });
  const errors = [];
  p.on('pageerror', e => errors.push(e.message));
  await login(p);
  await p.goto(`http://localhost/apps/spends/?lang=ru&theme=${theme}`, { waitUntil: 'domcontentloaded' });
  await p.waitForTimeout(2500);
  const r = { name, errors };

  // 1) share modal opens with 3 buttons
  await p.evaluate(() => showShareModal());
  await p.waitForTimeout(1200);
  r.shareButtons = await p.evaluate(() =>
    [...document.querySelectorAll('#share-modal .share-actions .share-act-btn')].map(b => b.textContent.trim())
  );
  await p.screenshot({ path: `${OUT}/${name}-s1-share.png` });

  // 2) create link -> cloud upload + QR modal
  await p.evaluate(() => shareChartToCloud());
  await p.waitForTimeout(6000);
  r.shareLink = await p.evaluate(() => {
    const m = document.getElementById('chart-share-link-modal');
    const link = document.getElementById('csl-link');
    const qr = document.getElementById('csl-qr svg, #csl-qr .csl-qr-wrap svg');
    return {
      visible: m && m.style.display === 'flex',
      link: link ? link.textContent.slice(0, 60) : null,
      qrPresent: !!document.querySelector('#csl-qr svg'),
    };
  });
  await p.screenshot({ path: `${OUT}/${name}-s2-sharelink.png` });
  await p.keyboard.press('Escape');
  await p.waitForTimeout(300);

  // 3) send to chat: open picker, search, pick, caption, send
  await p.evaluate(() => openChartChatSend());
  await p.waitForTimeout(600);
  await p.fill('#ccm-user-search', 'ui-review-admin@example.com');
  await p.waitForTimeout(1500);
  r.searchResults = await p.evaluate(() =>
    [...document.querySelectorAll('#ccm-user-results .ccm-user-row')].length
  );
  await p.screenshot({ path: `${OUT}/${name}-s3-chatpicker.png` });
  await p.click('#ccm-user-results .ccm-user-row');
  await p.waitForTimeout(400);
  await p.fill('#ccm-caption', 'Chart from spends (playwright)');
  await p.screenshot({ path: `${OUT}/${name}-s4-chatcompose.png` });
  await p.click('#ccm-send-btn');
  await p.waitForTimeout(3000);
  r.sendResult = await p.evaluate(() => ({
    modalClosed: document.getElementById('chart-chat-modal').style.display !== 'flex',
    notification: document.querySelector('.notification')?.textContent?.trim().slice(0, 60) || null,
  }));
  await p.screenshot({ path: `${OUT}/${name}-s5-chatsent.png` });

  // 4) xr modal padding check
  await p.evaluate(() => { showModal('exchange-rates-modal'); loadExchangeRates(); });
  await p.waitForTimeout(6000);
  r.xrPadding = await p.evaluate(() => {
    const mb = document.querySelector('#exchange-rates-modal .modal-body');
    const cs = getComputedStyle(mb);
    return { pad: cs.padding, rows: document.querySelectorAll('#xr-rows .xr-row').length };
  });
  await p.screenshot({ path: `${OUT}/${name}-s6-xr.png` });

  r.pageErrors = errors;
  results.push(r);
  await p.close();
}
console.log(JSON.stringify(results, null, 1));
await b.close();
