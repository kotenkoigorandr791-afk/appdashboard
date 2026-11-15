/*
 * Sanitized Chrome/CDP benchmark for task 36.
 *
 * Requires a Chrome started with --remote-debugging-port=9222 and an
 * authenticated PHPSESSID supplied through the environment. The script never
 * prints the cookie, full resource URLs or response bodies.
 *
 *   CHROME_DEBUG=http://127.0.0.1:9222 \
 *   STEPS_BENCH_URL=http://127.0.0.1/apps/steps/ \
 *   STEPS_BENCH_COOKIE=... node apps/steps/tests/performance/benchmark_page_map.mjs
 *
 * Run it three times and use the median. The report in
 * docs/geolocation_page_performance.md contains the measured values.
 */
import { setTimeout as sleep } from 'node:timers/promises';

const DEBUG = process.env.CHROME_DEBUG || 'http://127.0.0.1:9222';
const BASE = process.env.STEPS_BENCH_URL || 'http://127.0.0.1/apps/steps/';
const COOKIE = process.env.STEPS_BENCH_COOKIE || '';
if (!COOKIE) {
  console.error('STEPS_BENCH_COOKIE is required');
  process.exit(2);
}

class CDP {
  constructor(url) {
    this.ws = new WebSocket(url);
    this.id = 0;
    this.pending = new Map();
    this.listeners = new Map();
    this.ready = new Promise((resolve, reject) => {
      this.ws.onopen = resolve;
      this.ws.onerror = reject;
    });
    this.ws.onmessage = (event) => {
      const msg = JSON.parse(event.data);
      if (msg.id && this.pending.has(msg.id)) {
        const { resolve, reject } = this.pending.get(msg.id);
        this.pending.delete(msg.id);
        if (msg.error) reject(new Error(JSON.stringify(msg.error)));
        else resolve(msg.result);
        return;
      }
      (this.listeners.get(msg.method) || []).forEach((fn) => fn(msg.params));
    };
  }
  async send(method, params = {}) {
    await this.ready;
    const id = ++this.id;
    return new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
      this.ws.send(JSON.stringify({ id, method, params }));
    });
  }
  on(method, fn) {
    const list = this.listeners.get(method) || [];
    list.push(fn);
    this.listeners.set(method, list);
  }
  waitEvent(method, timeout = 30000) {
    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => reject(new Error('timeout ' + method)), timeout);
      const fn = (params) => {
        clearTimeout(timer);
        this.listeners.set(method, (this.listeners.get(method) || []).filter((x) => x !== fn));
        resolve(params);
      };
      this.on(method, fn);
    });
  }
}

const tab = await (await fetch(DEBUG + '/json/new?about:blank', { method: 'PUT' })).json();
const cdp = new CDP(tab.webSocketDebuggerUrl);
await cdp.ready;
await Promise.all([
  cdp.send('Page.enable'),
  cdp.send('Network.enable'),
  cdp.send('Runtime.enable'),
  cdp.send('Performance.enable'),
  cdp.send('Log.enable'),
]);
await cdp.send('Network.setCookie', {
  name: 'PHPSESSID',
  value: COOKIE,
  domain: new URL(BASE).hostname,
  path: '/',
  httpOnly: true,
  sameSite: 'Lax',
});
const errors = [];
const netErrors = [];
cdp.on('Runtime.exceptionThrown', (p) => errors.push(p.exceptionDetails?.text || 'exception'));
cdp.on('Log.entryAdded', (p) => { if (p.entry?.level === 'error') errors.push(p.entry.text); });
cdp.on('Network.responseReceived', (p) => {
  if (p.response?.status >= 400) netErrors.push(p.response.status + ' ' + new URL(p.response.url).pathname);
});
await cdp.send('Page.addScriptToEvaluateOnNewDocument', {
  source: `(() => {
    window.__perf36 = { lcp: 0, cls: 0, longtasks: [] };
    try {
      new PerformanceObserver((list) => {
        for (const e of list.getEntries()) window.__perf36.lcp = Math.max(window.__perf36.lcp, e.startTime);
      }).observe({ type: 'largest-contentful-paint', buffered: true });
      new PerformanceObserver((list) => {
        for (const e of list.getEntries()) {
          if (!e.hadRecentInput) window.__perf36.cls += e.value;
        }
      }).observe({ type: 'layout-shift', buffered: true });
      new PerformanceObserver((list) => {
        for (const e of list.getEntries()) window.__perf36.longtasks.push({ t: e.startTime, d: e.duration });
      }).observe({ type: 'longtask', buffered: true });
    } catch (e) {}
  })();`,
});

async function evalJs(expression) {
  const { result } = await cdp.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
  if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
  return result.value;
}

async function collect(label) {
  const { result } = await cdp.send('Runtime.evaluate', {
    expression: `(() => {
      const nav = performance.getEntriesByType('navigation')[0] || {};
      const paint = Object.fromEntries(performance.getEntriesByType('paint').map(e => [e.name, e.startTime]));
      const resources = performance.getEntriesByType('resource');
      const api = resources.filter(e => /geolocation_map/.test(e.name));
      const fcp = paint['first-contentful-paint'] || 0;
      const longTasks = (window.__perf36 && window.__perf36.longtasks) || [];
      const sum = (arr, key) => arr.reduce((a, e) => a + (e[key] || 0), 0);
      return {
        ttfb: nav.responseStart || 0, fcp,
        lcp: (window.__perf36 && window.__perf36.lcp) || 0,
        dcl: nav.domContentLoadedEventEnd || 0, load: nav.loadEventEnd || 0,
        tbt: longTasks.filter(e => e.t >= fcp).reduce((a, e) => a + Math.max(0, e.d - 50), 0),
        requests: resources.length, transfer: sum(resources, 'transferSize'),
        js: sum(resources.filter(e => /\\.js(\\?|$)/.test(e.name)), 'transferSize'),
        css: sum(resources.filter(e => /\\.css(\\?|$)/.test(e.name)), 'transferSize'),
        mapApiCalls: api.length, mapApiMs: api.map(e => Math.round(e.duration)),
        mapApiBytes: sum(api, 'transferSize'),
        leafletRequests: resources.filter(e => /leaflet/.test(e.name)).length,
        chartRequests: resources.filter(e => /chart/.test(e.name)).length,
        mapDom: !!document.querySelector('.leaflet-container'),
        mapHidden: !!document.getElementById('map-view')?.hidden
      };
    })()`,
    returnByValue: true,
  });
  return { label, ...result.value };
}

async function load(cacheDisabled) {
  await cdp.send('Network.setCacheDisabled', { cacheDisabled });
  if (cacheDisabled) await cdp.send('Network.clearBrowserCache');
  const loaded = cdp.waitEvent('Page.loadEventFired', 45000);
  await cdp.send('Page.navigate', { url: BASE });
  await loaded.catch(() => {});
  await sleep(3000);
  return collect(cacheDisabled ? 'cold' : 'warm');
}

async function openMap() {
  const t0 = Date.now();
  await evalJs(`document.querySelector('[data-mode="map"]').click()`);
  for (let i = 0; i < 150; i++) {
    const state = await evalJs(`(() => {
      const summary = document.getElementById('ma-map-summary');
      const canvas = document.getElementById('ma-map-canvas');
      return {
        shown: !document.getElementById('map-view').hidden,
        leaflet: !!document.querySelector('.leaflet-container'),
        tiles: document.querySelectorAll('.leaflet-tile-loaded').length,
        summary: summary ? summary.textContent : '',
        w: canvas ? canvas.clientWidth : 0, h: canvas ? canvas.clientHeight : 0
      };
    })()`);
    if (state.shown && state.leaflet && state.tiles > 0 && state.summary && !/loading|…/.test(state.summary)) {
      return { ...state, ms: Date.now() - t0 };
    }
    await sleep(100);
  }
  return { ms: Date.now() - t0, timeout: true };
}

async function drag(rect, dx, steps, delay) {
  const x = rect.x + rect.w / 2, y = rect.y + rect.h / 2;
  await cdp.send('Input.dispatchMouseEvent', { type: 'mousePressed', x, y, button: 'left', clickCount: 1 });
  for (let i = 1; i <= steps; i++) {
    await cdp.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: x + dx * i / steps, y, button: 'left', buttons: 1 });
    await sleep(delay);
  }
  await cdp.send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: x + dx, y, button: 'left', clickCount: 1 });
  await sleep(2200);
}

await cdp.send('Page.navigate', { url: BASE });
await sleep(1500);
await evalJs(`localStorage.clear(); sessionStorage.clear(); true`);
const cold = await load(true);
const map = await openMap();
const afterMap = await collect('after-map');
const rect = await evalJs(`(() => { const r = document.getElementById('ma-map-canvas').getBoundingClientRect(); return {x:r.x,y:r.y,w:r.width,h:r.height}; })()`);
await drag(rect, 280, 24, 45);
const slowPan = await collect('slow-pan');
await drag(rect, -620, 8, 10);
const rapidPan = await collect('rapid-pan');
const stats = await evalJs(`window.stepsMapPerf || null`);
const environment = await evalJs(`({ userAgent: navigator.userAgent, viewport: {w:innerWidth,h:innerHeight,dpr:devicePixelRatio} })`);
const warm = await load(false);

console.log(JSON.stringify({
  scenarios: { cold, warm, map, afterMap, slowPan, rapidPan },
  prefetch: stats,
  errors,
  netErrors,
  environment
}, null, 2));
cdp.ws.close();
process.exit(0);
