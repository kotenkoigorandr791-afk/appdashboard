import fs from 'node:fs';
import { execSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { createRequire } from 'node:module';
const { Agent, setGlobalDispatcher } = createRequire(process.env.UNDICI_DIR + '/')('undici');
const env = process.env;
const base = (env.BASE || 'http://127.0.0.1').replace(/\/$/, '');
const creds = JSON.parse(fs.readFileSync(env.CREDS, 'utf8'));
const stages = (env.STAGES || '10,25,50,100,250,500,1000,2000').split(',').map(Number);
const hold = Number(env.HOLD || 60) * 1000;
const warm = Number(env.WARM || 10) * 1000;
const maxP95 = Number(env.MAX_P95 || 1000);
const maxErr = Number(env.MAX_ERR || 0.01);
const out = env.OUT;
setGlobalDispatcher(new Agent({ connections: 0, pipelining: 1, keepAliveTimeout: 30000, connect: { timeout: 5000 } }));
const sleep = ms => new Promise(r => setTimeout(r, ms));
const sh = c => { try { return execSync(c, { encoding: 'utf8', timeout: 15000, stdio: ['ignore', 'pipe', 'ignore'] }).trim(); } catch { return ''; } };
const pct = (a, p) => a.length ? a[Math.min(a.length - 1, Math.ceil(p / 100 * a.length) - 1)] : 0;
const mix = [['points', 50], ['trips', 10], ['places', 5], ['statistics', 10], ['upload', 25]];
const pick = () => { let r = Math.random() * 100; for (const [n, w] of mix) { if ((r -= w) < 0) return n; } return 'points'; };
async function req(kind, c) {
  const h = { 'X-API-Key': c.key, Accept: 'application/json' };
  const i = Math.floor(Math.random() * 700);
  const bbox = [30.45 + i * 0.0009, 50.40 + i * 0.0006, 30.55 + i * 0.0009, 50.50 + i * 0.0006].join(',');
  const from = new Date(Date.now() - 30 * 864e5).toISOString(), to = new Date().toISOString();
  const u = `${base}/apps/steps/api/v1.php?path=`;
  if (kind === 'upload') {
    const pts = Array.from({ length: 20 }, () => ({ device_id: c.device, client_event_id: randomUUID(), source_type: 'mobile', source_at: new Date().toISOString(), latitude: 50.4 + Math.random() * 0.1, longitude: 30.45 + Math.random() * 0.1, accuracy_m: 10 }));
    return fetch(u + 'points', { method: 'POST', headers: { ...h, 'Content-Type': 'application/json' }, body: JSON.stringify({ points: pts }) });
  }
  const q = kind === 'points' ? `points&limit=50&from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}&bbox=${encodeURIComponent(bbox)}` : kind === 'statistics' ? `statistics&from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}` : kind;
  return fetch(u + q, { headers: h });
}
let stats;
const resetStats = () => (stats = { lat: [], core: [], err: {}, n: 0, bytes: 0, byKind: {} });
resetStats();
async function vu(id, stop) {
  const c = creds[id % creds.length];
  await sleep(Math.random() * 1000);
  while (!stop.v) {
    const kind = pick(), t = performance.now();
    let code = 0;
    try { const r = await req(kind, c); code = r.status; await r.arrayBuffer(); } catch (e) { code = 'net'; }
    const d = performance.now() - t;
    stats.n++; stats.lat.push(d); if (kind !== 'statistics') stats.core.push(d);
    const b = (stats.byKind[kind] ||= { n: 0, sum: 0, err: 0 });
    b.n++; b.sum += d;
    if (!(code >= 200 && code < 300)) { stats.err[code] = (stats.err[code] || 0) + 1; b.err++; }
    await sleep(200 + Math.random() * 800);
  }
}
const snap = () => ({
  docker: sh(`docker stats --no-stream --format '{{.Name}}|{{.CPUPerc}}|{{.MemUsage}}' appdashboard appdashboard-db appdashboard-pgbouncer appdashboard-redis`),
  pg: sh(`docker exec appdashboard-db psql -U appdashboard -d appdashboard -Atc "select state,count(*) from pg_stat_activity where datname='appdashboard' group by 1"`),
  pools: sh(`docker exec -e PGPASSWORD=\${DB_PASSWORD:-appdashboard_dev_password} appdashboard-pgbouncer psql -h 127.0.0.1 -p 5432 -U appdashboard pgbouncer -Atc "show pools" 2>&1 | grep appdashboard`),
  hostLoad: sh('cat /proc/loadavg'),
});
const results = [];
let broke = null;
for (const n of stages) {
  const stop = { v: false };
  const vus = []; resetStats();
  const start = Date.now();
  for (let i = 0; i < n; i++) vus.push(vu(i, stop));
  await sleep(warm); resetStats();
  const t0 = Date.now();
  await sleep(hold / 2);
  const s1 = snap();
  await sleep(hold / 2);
  const s = stats, el = (Date.now() - t0) / 1000;
  const mid = snap();
  stop.v = true; await Promise.allSettled(vus);
  s.lat.sort((a, b) => a - b); s.core.sort((a, b) => a - b);
  const errN = Object.values(s.err).reduce((a, b) => a + b, 0);
  const r = { vus: n, requests: s.n, rps: +(s.n / el).toFixed(1), p50: +pct(s.lat, 50).toFixed(1), p95: +pct(s.lat, 95).toFixed(1), p99: +pct(s.lat, 99).toFixed(1), coreP95: +pct(s.core, 95).toFixed(1), max: +pct(s.lat, 100).toFixed(1), errRate: +(errN / Math.max(1, s.n)).toFixed(4), errors: s.err, byKind: Object.fromEntries(Object.entries(s.byKind).map(([k, v]) => [k, { n: v.n, avg: +(v.sum / v.n).toFixed(1), err: v.err }])), snapshot: s1, snapshotEnd: mid };
  results.push(r);
  console.log(JSON.stringify({ vus: r.vus, rps: r.rps, p50: r.p50, p95: r.p95, coreP95: r.coreP95, p99: r.p99, errRate: r.errRate, errors: r.errors }));
  fs.writeFileSync(out, JSON.stringify({ base, stages, results, breakpoint: broke }, null, 2));
  if (r.coreP95 > maxP95 || r.errRate > maxErr) { broke = { vus: n, reason: r.coreP95 > maxP95 ? 'p95' : 'errors' }; break; }
  await sleep(5000);
}
const ok = results.filter(r => r.coreP95 <= maxP95 && r.errRate <= maxErr);
const final = { base, stages, results, breakpoint: broke, maxSustainedVus: ok.length ? ok[ok.length - 1].vus : 0, maxRps: Math.max(...ok.map(r => r.rps), 0) };
fs.writeFileSync(out, JSON.stringify(final, null, 2));
console.log('RESULT', JSON.stringify({ breakpoint: broke, maxSustainedVus: final.maxSustainedVus, maxRps: final.maxRps }));
process.exit(0);
