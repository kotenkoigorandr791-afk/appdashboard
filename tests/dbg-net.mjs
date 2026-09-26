import { chromium } from '@playwright/test';
const b = await chromium.launch();
const p = await b.newPage();
p.on('console', m => { if (m.type()==='error') console.log('console:', m.text().slice(0,200)); });
await p.goto('http://localhost/login?lang=en');
await p.fill('#login-content input[name="email"]','root@root');
await p.fill('#login-password','12345T');
await Promise.all([p.waitForURL(u=>!String(u).includes('/login')),p.click('#login-content button[name="login"]')]);
await p.goto('http://localhost/apps/spends/?lang=ru');
await p.waitForTimeout(2000);
// 1. search users
const s = await p.evaluate(async () => {
  const res = await fetch('?chat_search_users=1&q=ui-review-admin@example.com');
  return { status: res.status, body: (await res.text()).slice(0,200) };
});
console.log('search:', JSON.stringify(s));
// 2. share to cloud
const sh = await p.evaluate(async () => {
  const res = await fetch('?share_chart_to_cloud=1', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ image: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' })
  });
  return { status: res.status, body: (await res.text()).slice(0,300) };
});
console.log('share:', JSON.stringify(sh));
// 3. chat send via FormData
const cs = await p.evaluate(async () => {
  const fd = new FormData();
  fd.append('chat_send_message','1');
  fd.append('peer_email','ui-review-admin@example.com');
  fd.append('message','test');
  const res = await fetch('?', { method:'POST', body: fd });
  return { status: res.status, body: (await res.text()).slice(0,200) };
});
console.log('chat:', JSON.stringify(cs));
await b.close();
