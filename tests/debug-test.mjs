import { chromium } from 'playwright';
const b = await chromium.launch();
const ctx = await b.newContext({ viewport: { width: 375, height: 812 } });
const p = await ctx.newPage();
p.on('console', m => { if (m.type()==='error') console.log('ERR', m.text()); });
await p.goto('http://localhost/', { waitUntil: 'networkidle' });
await p.evaluate(() => { sessionStorage.clear(); localStorage.clear(); });
await p.reload({ waitUntil: 'networkidle' });
await p.waitForTimeout(6500);
const info = await p.evaluate(() => {
  const out = {};
  out.gutter = getComputedStyle(document.documentElement).getPropertyValue('--toast-gutter');
  out.innerW = window.innerWidth;
  out.clientW = document.documentElement.clientWidth;
  for (const sel of ['#engagementToast', '#exploreWidget']) {
    const el = document.querySelector(sel);
    if (!el) { out[sel] = { missing: true }; continue; }
    const cs = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    out[sel] = {
      rectW: r.width, rectL: r.left, rectR: r.right,
      cssW: cs.width, cssL: cs.left, cssR: cs.right,
      cssMaxW: cs.maxWidth, boxSizing: cs.boxSizing, cssPr: cs.paddingRight,
    };
  }
  return out;
});
console.log(JSON.stringify(info, null, 2));
await b.close();
