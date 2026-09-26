import { chromium } from '@playwright/test';
const b = await chromium.launch();
const p = await b.newPage();
p.on('console', m => { if (m.type()==='error') console.log('console:', m.text().slice(0,150)); });
await p.goto('http://localhost/login?lang=en');
await p.fill('#login-content input[name="email"]','root@root');
await p.fill('#login-password','12345T');
await Promise.all([p.waitForURL(u=>!String(u).includes('/login')),p.click('#login-content button[name="login"]')]);
await p.goto('http://localhost/apps/spends/?lang=ru');
await p.waitForTimeout(2000);
// who am I
console.log('me:', await p.evaluate(() => document.querySelector('.user-icon')?.alt || 'unknown'));
// search
console.log('search:', await p.evaluate(async () => (await (await fetch('?chat_search_users=1&q=ui-review-admin@example.com')).text()).slice(0,120)));
// share with small valid PNG
console.log('share:', await p.evaluate(async () => {
  // 8x8 red PNG
  const c=document.createElement('canvas');c.width=8;c.height=8;
  const x=c.getContext('2d');x.fillStyle='red';x.fillRect(0,0,8,8);
  const blob=await new Promise(r=>c.toBlob(r,'image/png'));
  const fd=new FormData();
  fd.append('share_chart_to_cloud','1');
  fd.append('image',new File([blob],'t.png',{type:'image/png'}));
  const res=await fetch('?',{method:'POST',body:fd});
  return (await res.text()).slice(0,250);
}));
await b.close();
