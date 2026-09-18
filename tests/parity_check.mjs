import { chromium } from '@playwright/test';
import fs from 'fs';

const appBase = process.env.APP_BASE_URL || 'http://localhost';
const deepseekPath = '/home/ihor/Downloads/deepseek_html_20260918_266a28.html';
const homeDemoPath = '/home/ihor/Downloads/главная.html';
const fileUrl = (p) => 'file://' + p;

const waitParticles = async (page) => {
  await page.waitForSelector('#particles', { timeout: 15000 });
  await page.waitForFunction(() => document.querySelectorAll('#particles .particle').length > 0, { timeout: 15000 });
};

const extractAnim = (el) => {
  if (!el) return null;
  const cs = window.getComputedStyle(el);
  const name = (cs.animationName || '').split(',')[0].trim();
  const duration = (cs.animationDuration || '').split(',')[0].trim();
  const delay = (cs.animationDelay || '').split(',')[0].trim();
  const iter = (cs.animationIterationCount || '').split(',')[0].trim();
  const playState = cs.animationPlayState || null;
  return { name: name || null, duration: duration || null, delay: delay || null, iter: iter || null, playState };
};

const extractAuth = async (page) => {
  return page.evaluate(() => {
    const el = (sel) => document.querySelector(sel);
    const getAnim = (node) => {
      if (!node) return null;
      const cs = window.getComputedStyle(node);
      const name = (cs.animationName || '').split(',')[0].trim();
      const duration = (cs.animationDuration || '').split(',')[0].trim();
      const delay = (cs.animationDelay || '').split(',')[0].trim();
      const iter = (cs.animationIterationCount || '').split(',')[0].trim();
      const playState = cs.animationPlayState || null;
      return { name: name || null, duration: duration || null, delay: delay || null, iter: iter || null, playState };
    };
    const rememberInput = el('.checkbox-label input:checked');
    const checkmark = rememberInput ? el('.checkbox-label input:checked + .checkmark') : null;

    return {
      topbar: (() => {
        const topbar = el('#auth-top-bar');
        if (!topbar) return null;
        const cs = window.getComputedStyle(topbar);
        return { backgroundColor: cs.backgroundColor, borderBottomColor: cs.borderBottomColor, backdropFilter: cs.backdropFilter };
      })(),
      rememberMe: rememberInput
        ? {
            checked: true,
            checkmarkBg: checkmark ? window.getComputedStyle(checkmark).backgroundColor : null,
            checkmarkBorder: checkmark ? window.getComputedStyle(checkmark).borderColor : null,
          }
        : { checked: false },
      bg: {
        gradient: (() => {
          const n = el('.bg-gradient');
          return n ? { anim: getAnim(n), backgroundImage: window.getComputedStyle(n).backgroundImage } : null;
        })(),
        grid: (() => {
          const n = el('.bg-grid');
          const cs = n ? window.getComputedStyle(n) : null;
          return n ? { anim: getAnim(n), backgroundSize: cs.backgroundSize, maskImage: cs.maskImage } : null;
        })(),
        rays: (() => {
          const n = el('.bg-rays');
          const cs = n ? window.getComputedStyle(n) : null;
          return n ? { anim: getAnim(n), opacity: cs.opacity } : null;
        })(),
        orb1: (() => {
          const n = el('.glow-orb.orb-1');
          return n ? { anim: getAnim(n) } : null;
        })(),
        orb2: (() => {
          const n = el('.glow-orb.orb-2');
          return n ? { anim: getAnim(n) } : null;
        })(),
        orb3: (() => {
          const n = el('.glow-orb.orb-3');
          return n ? { anim: getAnim(n) } : null;
        })(),
        scanline: (() => {
          const n = el('.scanline');
          const cs = n ? window.getComputedStyle(n) : null;
          return n ? { anim: getAnim(n), opacity: cs.opacity, filter: cs.filter } : null;
        })(),
      },
      particles: (() => {
        const container = el('#particles');
        const count = container ? container.querySelectorAll('.particle').length : 0;
        const p = el('#particles .particle');
        return { containerExists: !!container, count, firstParticleAnim: p ? getAnim(p) : null };
      })(),
    };
  });
};

const extractHome = async (page) => {
  return page.evaluate(() => {
    const el = (sel) => document.querySelector(sel);
    const getAnim = (node) => {
      if (!node) return null;
      const cs = window.getComputedStyle(node);
      const name = (cs.animationName || '').split(',')[0].trim();
      const duration = (cs.animationDuration || '').split(',')[0].trim();
      const delay = (cs.animationDelay || '').split(',')[0].trim();
      const iter = (cs.animationIterationCount || '').split(',')[0].trim();
      const playState = cs.animationPlayState || null;
      return { name: name || null, duration: duration || null, delay: delay || null, iter: iter || null, playState };
    };
    return {
      bg: {
        gradient: (() => {
          const n = el('.bg-gradient');
          return n ? { anim: getAnim(n) } : null;
        })(),
        grid: (() => {
          const n = el('.bg-grid');
          return n ? { anim: getAnim(n) } : null;
        })(),
        rays: (() => {
          const n = el('.bg-rays');
          return n ? { anim: getAnim(n), opacity: window.getComputedStyle(n).opacity } : null;
        })(),
        scanline: (() => {
          const n = el('.scanline');
          return n ? { anim: getAnim(n), opacity: window.getComputedStyle(n).opacity } : null;
        })(),
        orb1: (() => {
          const n = el('.glow-orb.orb-1');
          return n ? { anim: getAnim(n) } : null;
        })(),
      },
      particlesCount: document.querySelectorAll('#particles .particle').length,
    };
  });
};

const run = async () => {
  const browser = await chromium.launch({ args: ['--disable-setuid-sandbox'] });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'en-US' });
  const page = await context.newPage();

  const routes = [
    { key: 'login', app: `${appBase}/login?lang=en&theme=dark&color=%232563eb`, demo: fileUrl(deepseekPath) },
    { key: 'register', app: `${appBase}/register?lang=en&theme=dark&color=%232563eb`, demo: fileUrl(deepseekPath) },
  ];

  const out = {};

  for (const r of routes) {
    await page.goto(r.app, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);
    await waitParticles(page);
    out[r.key] = await extractAuth(page);

    await page.goto(r.demo, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);
    await waitParticles(page);
    out[r.key + '_demo'] = await extractAuth(page);
  }

  // home app vs home demo
  await page.goto(`${appBase}/?lang=en&theme=dark&color=%232563eb`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(800);
  await waitParticles(page);
  out['home_app'] = await extractHome(page);

  await page.goto(fileUrl(homeDemoPath), { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(800);
  await waitParticles(page);
  out['home_demo'] = await extractHome(page);

  const outPath = '/tmp/parity_check_results.json';
  fs.writeFileSync(outPath, JSON.stringify(out, null, 2));
  console.log('Wrote', outPath);
};

run().catch((e) => {
  console.error(e);
  process.exit(1);
});
