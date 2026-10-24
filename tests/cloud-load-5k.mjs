#!/usr/bin/env node
import http from 'node:http';
import https from 'node:https';
import { performance } from 'node:perf_hooks';

const BASE = process.env.TEST_BASE_URL || 'http://127.0.0.1';
const USERS = Number(process.env.LOAD_USERS || 5000);
const ACTIVE_RATIO = Number(process.env.ACTIVE_RATIO || 0.7);
const DURATION_SEC = Number(process.env.DURATION_SEC || 20);
const CONCURRENCY = Number(process.env.CONCURRENCY || 150);

const INTERVALS = {
  get_file_tree_ms: 1000,
  group_chat_poll_ms: 4000,
  group_unread_ms: 30000,
  groups_list_ms: 60000,
};

function modelQps(users) {
  const active = Math.floor(users * ACTIVE_RATIO);
  const tree = active / (INTERVALS.get_file_tree_ms / 1000);
  const chatOpen = Math.floor(active * 0.15);
  const chat = chatOpen / (INTERVALS.group_chat_poll_ms / 1000);
  const unread = active / (INTERVALS.group_unread_ms / 1000);
  const groups = active / (INTERVALS.groups_list_ms / 1000);
  const webdav = Math.floor(users * 0.1) * 0.2;
  const uploadsBurst = Math.floor(users * 0.01);
  const chunksPerSec = uploadsBurst * 2;
  return {
    activeTabs: active,
    qps: {
      get_file_tree: Math.round(tree),
      group_chat_messages: Math.round(chat),
      group_chat_unread: Math.round(unread),
      groups_list: Math.round(groups),
      webdav_token_auth: Math.round(webdav),
      upload_dir_size_walks: Math.round(chunksPerSec),
    },
    estimatedDbQps:
      Math.round(tree) +
      Math.round(chat) * 2 +
      Math.round(unread) * 3 +
      Math.round(groups) +
      Math.round(webdav) * 2,
  };
}

function request(path) {
  return new Promise((resolve) => {
    const url = new URL(path, BASE);
    const lib = url.protocol === 'https:' ? https : http;
    const t0 = performance.now();
    const req = lib.request(
      {
        hostname: url.hostname,
        port: url.port || (url.protocol === 'https:' ? 443 : 80),
        path: url.pathname + url.search,
        method: 'GET',
        timeout: 15000,
        headers: { Accept: 'application/json', Connection: 'keep-alive' },
      },
      (res) => {
        let buf = '';
        res.on('data', (c) => {
          buf += c;
          if (buf.length > 65536) buf = buf.slice(0, 65536);
        });
        res.on('end', () => {
          resolve({ status: res.statusCode, ms: performance.now() - t0, bytes: Buffer.byteLength(buf) });
        });
      }
    );
    req.on('error', (e) => resolve({ status: 0, ms: performance.now() - t0, err: e.message }));
    req.on('timeout', () => {
      req.destroy();
      resolve({ status: 0, ms: performance.now() - t0, err: 'timeout' });
    });
    req.end();
  });
}

const ENDPOINTS = [
  { name: 'get_state', path: '/apps/cloud/api.php?action=get_state', weight: 5 },
  { name: 'list', path: '/apps/cloud/api.php?action=list&path=', weight: 10 },
  { name: 'get_file_tree', path: '/apps/cloud/api.php?action=get_file_tree', weight: 40 },
  { name: 'debug_session', path: '/apps/cloud/api.php?action=debug_session', weight: 1 },
  { name: 'get_ai_config', path: '/apps/cloud/api.php?action=get_ai_config', weight: 2 },
  { name: 'office_download', path: '/apps/cloud/api.php?action=office_download&user=guest&path=x', weight: 2 },
];

function pickEndpoint() {
  const total = ENDPOINTS.reduce((s, e) => s + e.weight, 0);
  let r = Math.random() * total;
  for (const e of ENDPOINTS) {
    r -= e.weight;
    if (r <= 0) return e;
  }
  return ENDPOINTS[0];
}

async function runLiveLoad() {
  const stats = {};
  for (const e of ENDPOINTS) stats[e.name] = { n: 0, ok: 0, err: 0, sum: 0, max: 0, statuses: {} };
  const start = performance.now();
  const deadline = start + DURATION_SEC * 1000;
  async function worker() {
    while (performance.now() < deadline) {
      const ep = pickEndpoint();
      const r = await request(ep.path);
      const s = stats[ep.name];
      s.n++;
      s.sum += r.ms;
      if (r.ms > s.max) s.max = r.ms;
      const key = String(r.status || r.err || 'err');
      s.statuses[key] = (s.statuses[key] || 0) + 1;
      if (r.status >= 200 && r.status < 500 && r.status !== 0) s.ok++;
      else s.err++;
    }
  }
  await Promise.all(Array.from({ length: CONCURRENCY }, () => worker()));
  const elapsed = (performance.now() - start) / 1000;
  const summary = {};
  let total = 0;
  for (const [name, s] of Object.entries(stats)) {
    total += s.n;
    summary[name] = {
      requests: s.n,
      avg_ms: s.n ? Math.round(s.sum / s.n) : 0,
      max_ms: Math.round(s.max),
      rps: Math.round(s.n / elapsed),
      statuses: s.statuses,
    };
  }
  return { elapsed_sec: Math.round(elapsed * 10) / 10, concurrency: CONCURRENCY, total_requests: total, overall_rps: Math.round(total / elapsed), by_endpoint: summary };
}

const model = modelQps(USERS);
const live = await runLiveLoad();
console.log(JSON.stringify({
  scenario: { modeled_users: USERS, active_ratio: ACTIVE_RATIO, active_tabs: model.activeTabs, intervals_ms: INTERVALS },
  projected_steady_state: model,
  live_probe: live,
  projections_at_5k: {
    tree_poll_qps: model.qps.get_file_tree,
    estimated_db_qps_idle: model.estimatedDbQps,
    risk_flags: [
      model.qps.get_file_tree > 500 ? 'CRITICAL: get_file_tree poll >500 QPS' : null,
      model.estimatedDbQps > 1000 ? 'CRITICAL: estimated idle DB QPS >1000' : null,
      model.qps.upload_dir_size_walks > 50 ? 'HIGH: concurrent full-tree dir_size walks during uploads' : null,
    ].filter(Boolean),
  },
}, null, 2));
