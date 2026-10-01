import { chromium } from '@playwright/test';
const b = await chromium.launch();
const p = await b.newPage();
p.on('console', m => { if (m.type()==='error') console.log('console:', m.text().slice(0,300)); });
p.on('pageerror', e => console.log('pageerror:', String(e).slice(0,300)));

await p.goto('http://localhost/login?lang=en');
await p.fill('#login-content input[name="email"]','root@root');
await p.fill('#login-password','12345T');
await Promise.all([p.waitForURL(u=>!String(u).includes('/login')),p.click('#login-content button[name="login"]')]);

// open admin panel: click the sidebar admin button (openAdminPanel is inside IIFE)
await p.goto('http://localhost/main?lang=en');
await p.waitForTimeout(2000);
await p.evaluate(() => document.getElementById('open-admin-panel-btn')?.click());
await p.waitForTimeout(2500);

const dbg0 = await p.evaluate(() => ({
  overlayActive: document.getElementById('admin-panel-overlay')?.className,
  skeleton: document.getElementById('ap-loading-skeleton')?.style.display,
  gate: document.getElementById('ap-gate-login') ? getComputedStyle(document.getElementById('ap-gate-login')).display : 'absent',
  loadingMsg: document.getElementById('ap-loading-msg')?.textContent,
  hasApGate: typeof window.apGateShow === 'function',
  openBtnVisible: (()=>{const b=document.getElementById('open-admin-panel-btn'); if(!b) return 'absent'; const r=b.getBoundingClientRect(); return r.width+'x'+r.height;})(),
}));
console.log('state:', JSON.stringify(dbg0));
await p.screenshot({ path: 'tmp/eye-state.png', fullPage: false });

// admin gate may appear — unlock it
const gateVisible = await p.evaluate(() => {
  const g = document.getElementById('ap-gate-login');
  return g && getComputedStyle(g).display !== 'none' && g.offsetParent !== null;
});
console.log('gate visible:', gateVisible);
if (gateVisible) {
  await p.fill('#ap-gate-password', '12345T');
  await p.click('#ap-gate-submit');
  await p.waitForTimeout(3000);
}

// switch to integrations settings tab where the onlyoffice eye lives
await p.evaluate(() => {
  const tab = [...document.querySelectorAll('.ap-settings-tab')].find(t => t.dataset.settingsSection === 'onlyoffice');
  if (tab) tab.click();
});
await p.waitForTimeout(500);

const dbg = await p.evaluate(() => ({
  overlayActive: document.getElementById('admin-panel-overlay')?.className,
  eyes: document.querySelectorAll('.ap-pwd-eye').length,
  activeCards: [...document.querySelectorAll('.ap-settings-card.active')].map(c=>c.dataset.settingsCard),
}));
console.log('debug:', JSON.stringify(dbg));

const eye = p.locator('button.ap-pwd-eye[data-target="ap-onlyoffice-token"]');
await eye.waitFor({ state: 'visible', timeout: 10000 });

const before = await eye.evaluate(el => {
  const s = getComputedStyle(el, '::after');
  return { transform: s.transform, opacity: s.opacity };
});
console.log('before click ::after:', JSON.stringify(before));

await eye.click();
// sample mid-animation
await p.waitForTimeout(150);
const mid = await eye.evaluate(el => {
  const s = getComputedStyle(el, '::after');
  const cls = el.className;
  const input = document.getElementById(el.dataset.target);
  return { transform: s.transform, opacity: s.opacity, cls, inputType: input.type };
});
console.log('mid-animation ::after:', JSON.stringify(mid));

await p.waitForTimeout(600);
const after = await eye.evaluate(el => {
  const s = getComputedStyle(el, '::after');
  const cls = el.className;
  return { transform: s.transform, opacity: s.opacity, cls };
});
console.log('after animation ::after:', JSON.stringify(after));

await p.screenshot({ path: 'tmp/eye-anim.png' });
await b.close();
console.log('OK');
