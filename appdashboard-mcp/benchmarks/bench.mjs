#!/usr/bin/env node
/**
 * AppDashboard MCP benchmark harness.
 *
 * Runs real MCP JSON-RPC sessions over Streamable HTTP and STDIO, records
 * p50/p90/p95/p99 latency, response sizes, startup time, rate-limit behavior,
 * and (optionally) server CPU/RAM/event-loop samples from perf-preload.cjs.
 *
 * Usage:
 *   node benchmarks/bench.mjs --label baseline
 *   node benchmarks/bench.mjs --label after --scenarios protocol,concurrency
 *
 * Token/device files are read locally and never printed.
 */
import { spawn } from "node:child_process";
import { mkdirSync, readFileSync, writeFileSync, existsSync, rmSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { performance } from "node:perf_hooks";
import { randomUUID } from "node:crypto";

const HERE = dirname(fileURLToPath(import.meta.url));
const PKG = resolve(HERE, "..");
const RESULTS = join(HERE, "results");
mkdirSync(RESULTS, { recursive: true });

const args = parseArgs(process.argv.slice(2));
const label = args.label || "run";
const port = Number(args.port || 8899);
const base = `http://127.0.0.1:${port}`;
const tokenFile = resolve(PKG, args["token-file"] || "../.bench-token");
const deviceFile = resolve(PKG, args["device-file"] || "../.bench-device");
const token = readFileSync(tokenFile, "utf8").trim();
const device = readFileSync(deviceFile, "utf8").trim();
const scenarios = new Set(
  String(args.scenarios || "auth,protocol,protocol_flat,concurrency,rate-limit,stdio").split(",").map((s) => s.trim()).filter(Boolean),
);
const protocolIterations = Number(args.iterations || 10);
const result = {
  label,
  generated_at: new Date().toISOString(),
  environment: {
    node: process.version,
    platform: process.platform,
    arch: process.arch,
    url: base,
    iterations: protocolIterations,
  },
  auth_me: null,
  startup_http_ms: null,
  protocol: {},
  protocol_flat: {},
  concurrency: [],
  sustained: null,
  rate_limit: null,
  rate_limit_auth: null,
  stdio: null,
  server_perf: {},
};

main().catch((err) => {
  console.error("[bench] fatal:", err);
  process.exit(1);
});

async function main() {
  if (scenarios.has("auth")) result.auth_me = await benchAuthMe();
  if (scenarios.has("protocol")) result.protocol = await withServer("protocol", {}, () => benchProtocol({ grouped: true }));
  if (scenarios.has("protocol_flat")) {
    result.protocol_flat = await withServer("protocol_flat", { MCP_GROUPED_TOOLS: "0" }, () => benchProtocol({ grouped: false }));
  }
  if (scenarios.has("concurrency")) {
    for (const clients of [1, 2, 5, 10, 25, 50]) {
      const row = await withServer(`concurrency_${clients}`, { MCP_RATE_LIMIT_MAX: "10000" }, () => benchConcurrency(clients));
      result.concurrency.push(row);
    }
  }
  if (scenarios.has("sustained")) {
    result.sustained = await withServer("sustained", { MCP_RATE_LIMIT_MAX: "100000" }, () => benchSustained());
  }
  if (scenarios.has("rate-limit")) result.rate_limit = await withServer("rate_limit", {}, () => benchRateLimit());
  if (scenarios.has("rate-limit-auth")) result.rate_limit_auth = await withServer("rate_limit_auth", {}, () => benchRateLimitAuth());
  if (scenarios.has("stdio")) result.stdio = await benchStdio();

  const out = join(RESULTS, `${label}.json`);
  writeFileSync(out, JSON.stringify(result, null, 2) + "\n");
  console.log(`[bench] wrote ${out}`);
  printSummary(result);
}

async function benchAuthMe() {
  const times = [];
  let status = 0;
  for (let i = 0; i < 30; i++) {
    const t = performance.now();
    const res = await fetch(`${args["app-url"] || "http://localhost"}/public-api/auth/me`, {
      headers: { Authorization: `Bearer ${token}`, "X-Device-Id": device },
    });
    status = res.status;
    await res.text();
    times.push(performance.now() - t);
  }
  return { ...stats(times), status };
}

async function withServer(name, extraEnv, fn) {
  const logPath = join(RESULTS, `${label}-${name}-server.log`);
  const perfPath = join(RESULTS, `${label}-${name}-perf.jsonl`);
  for (const p of [logPath, perfPath]) if (existsSync(p)) rmSync(p);
  const started = performance.now();
  const child = spawn(
    process.execPath,
    ["--require", join(HERE, "perf-preload.cjs"), join(PKG, "dist", "index.js"), "--http", "--port", String(port)],
    {
      cwd: PKG,
      env: {
        ...process.env,
        APPDASHBOARD_URL: args["app-url"] || "http://localhost",
        APPDASHBOARD_TOKEN: token,
        APPDASHBOARD_MCP_AUTOUPDATE: "0",
        MCP_CORS_ORIGINS: "",
        MCP_PERF_LOG: perfPath,
        ...extraEnv,
      },
      stdio: ["ignore", "ignore", "pipe"],
      windowsHide: true,
    },
  );
  let stderr = "";
  child.stderr.on("data", (d) => {
    stderr += d.toString();
    if (stderr.length > 100_000) stderr = stderr.slice(-50_000);
  });
  await waitFor(() => /HTTP transport listening/.test(stderr), 8000, () => {
    throw new Error(`server did not start:\n${stderr}`);
  });
  result.startup_http_ms = +(performance.now() - started).toFixed(2);
  // One readiness request consumes a rate-limit slot; it is unauthenticated.
  await fetch(`${base}/mcp`, { method: "GET" }).catch(() => undefined);
  try {
    return await fn();
  } finally {
    child.kill();
    await new Promise((r) => setTimeout(r, 250));
    if (existsSync(perfPath)) result.server_perf[name] = summarizePerf(perfPath);
  }
}

async function benchProtocol({ grouped }) {
  const session = await newSession();
  const out = {};
  const cases = [
    ["tools_list", () => rpc(session, "tools/list", {})],
    ["tools_catalog", () => rpc(session, "tools/call", { name: "tools_catalog", arguments: {} })],
    ["tools_search", () => rpc(session, "tools/call", { name: "tools_search", arguments: { query: "docker" } })],
    ["tools_load", () => rpc(session, "tools/call", { name: "tools_load", arguments: { groups: ["core"] } })],
    ["tools_call", () => rpc(session, "tools/call", { name: "get_current_user", arguments: {} })],
    ["unknown_tool", () => rpc(session, "tools/call", { name: "__bench_unknown__", arguments: {} })],
    ["invalid_tool_call", () => rpc(session, "tools/call", { name: "tools_call", arguments: {} })],
  ];
  for (const [name, fn] of cases) {
    try {
      await fn();
    } catch {
      /* warmup */
    }
    const times = [];
    const sizes = [];
    let errors = 0;
    let lastError = null;
    for (let i = 0; i < protocolIterations; i++) {
      const t = performance.now();
      try {
        const data = await fn();
        times.push(performance.now() - t);
        sizes.push(Buffer.byteLength(JSON.stringify(data)));
      } catch (err) {
        errors++;
        lastError = String(err?.message || err);
      }
    }
    out[name] = { ...stats(times), bytes_p50: median(sizes), errors, last_error: lastError, grouped };
  }
  await closeSession(session);
  return out;
}

async function benchConcurrency(clients) {
  const initStarted = performance.now();
  const sessions = await Promise.all(Array.from({ length: clients }, () => newSession()));
  const sessionInit = performance.now() - initStarted;
  const rounds = 3;
  const started = performance.now();
  const rows = await Promise.allSettled(
    sessions.flatMap((session) =>
      Array.from({ length: rounds }, async () => {
        const t = performance.now();
        await rpc(session, "tools/list", {});
        return performance.now() - t;
      }),
    ),
  );
  const latencies = [];
  let errors = 0;
  for (const r of rows) {
    if (r.status === "fulfilled" && typeof r.value === "number") latencies.push(r.value);
    else errors++;
  }
  const wall = performance.now() - started;
  return {
    clients,
    rounds,
    session_init_ms: +sessionInit.toFixed(2),
    wall_ms: +wall.toFixed(2),
    rps: +((clients * rounds) / (wall / 1000)).toFixed(2),
    errors,
    ...stats(latencies),
  };
}

async function benchSustained() {
  const clients = Math.max(1, Number(args["sustained-clients"] || 10));
  const durationMs = Math.max(1000, Number(args["duration-ms"] || 10000));
  const sessions = await Promise.all(Array.from({ length: clients }, () => newSession()));
  const started = performance.now();
  const deadline = started + durationMs;
  const times = [];
  const errorCounts = new Map();
  let requests = 0;
  let attempts = 0;
  let errors = 0;
  await Promise.all(
    sessions.map(async (session) => {
      while (performance.now() < deadline) {
        const t = performance.now();
        attempts++;
        try {
          await rpc(session, "tools/list", {});
          times.push(performance.now() - t);
          requests++;
        } catch (err) {
          errors++;
          const msg = String(err?.message || err).slice(0, 120);
          errorCounts.set(msg, (errorCounts.get(msg) || 0) + 1);
        }
        await new Promise((r) => setTimeout(r, 5));
      }
    }),
  );
  const wall = performance.now() - started;
  return {
    clients,
    duration_ms: durationMs,
    attempts,
    successes: requests,
    errors,
    error_samples: [...errorCounts.entries()].sort((a, b) => b[1] - a[1]).slice(0, 5),
    rps: +(attempts / (wall / 1000)).toFixed(2),
    success_rps: +(requests / (wall / 1000)).toFixed(2),
    ...stats(times),
  };
}

async function benchRateLimit() {
  return burst(130, async (i) => {
    const res = await fetch(`${base}/mcp`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json, text/event-stream" },
      body: JSON.stringify({ jsonrpc: "2.0", id: i + 1, method: "tools/list", params: {} }),
    });
    await res.text().catch(() => undefined);
    return res.status;
  });
}

async function benchRateLimitAuth() {
  const session = await newSession();
  return burst(130, async () => {
    try {
      await rpc(session, "tools/list", {});
      return 200;
    } catch (err) {
      const m = String(err?.message || err).match(/^http_(\d+)/);
      return m ? Number(m[1]) : 0;
    }
  });
}

async function burst(count, fn) {
  const statuses = [];
  const started = performance.now();
  for (let i = 0; i < count; i++) statuses.push(await fn(i));
  const first429 = statuses.indexOf(429);
  return {
    requests: statuses.length,
    allowed: statuses.filter((s) => s !== 429).length,
    rate_limited: statuses.filter((s) => s === 429).length,
    first_429_at: first429 === -1 ? null : first429 + 1,
    wall_ms: +(performance.now() - started).toFixed(2),
  };
}

async function benchStdio() {
  const child = spawn(process.execPath, [join(PKG, "dist", "index.js")], {
    cwd: PKG,
    env: {
      ...process.env,
      APPDASHBOARD_URL: args["app-url"] || "http://localhost",
      APPDASHBOARD_TOKEN: token,
      APPDASHBOARD_MCP_AUTOUPDATE: "0",
    },
    stdio: ["pipe", "pipe", "pipe"],
    windowsHide: true,
  });
  let buf = "";
  const pending = new Map();
  child.stdout.on("data", (d) => {
    buf += d.toString();
    let idx;
    while ((idx = buf.indexOf("\n")) >= 0) {
      const line = buf.slice(0, idx).trim();
      buf = buf.slice(idx + 1);
      if (!line) continue;
      try {
        const msg = JSON.parse(line);
        if (msg.id && pending.has(msg.id)) {
          pending.get(msg.id)(msg);
          pending.delete(msg.id);
        }
      } catch {
        /* ignore diagnostics accidentally on stdout */
      }
    }
  });
  const started = performance.now();
  const send = (msg) => child.stdin.write(JSON.stringify(msg) + "\n");
  const request = (id, method, params) =>
    new Promise((resolve, reject) => {
      const timer = setTimeout(() => reject(new Error(`stdio timeout after ${method}`)), 15000);
      pending.set(id, (msg) => {
        clearTimeout(timer);
        msg.error ? reject(new Error(msg.error.message || "jsonrpc_error")) : resolve(msg.result);
      });
      send({ jsonrpc: "2.0", id, method, params });
    });
  try {
    await request(1, "initialize", {
      protocolVersion: "2024-11-05",
      capabilities: {},
      clientInfo: { name: "appdashboard-bench", version: "1.0" },
    });
    const initMs = performance.now() - started;
    send({ jsonrpc: "2.0", method: "notifications/initialized" });
    const times = [];
    for (let i = 0; i < protocolIterations; i++) {
      const t = performance.now();
      await request(100 + i, "tools/list", {});
      times.push(performance.now() - t);
    }
    return { startup_init_ms: +initMs.toFixed(2), tools_list: stats(times) };
  } finally {
    child.kill();
  }
}

function newSession() {
  return (async () => {
    const session = { id: null, protocol: "2024-11-05" };
    await rpc(session, "initialize", {
      protocolVersion: "2024-11-05",
      capabilities: {},
      clientInfo: { name: "appdashboard-bench", version: "1.0" },
    });
    await notify(session, "notifications/initialized");
    return session;
  })();
}

async function notify(session, method) {
  const headers = rpcHeaders(session);
  const res = await fetch(`${base}/mcp`, {
    method: "POST",
    headers,
    body: JSON.stringify({ jsonrpc: "2.0", method }),
  });
  await res.text().catch(() => undefined);
  if (res.status >= 400) throw new Error(`notification_http_${res.status}`);
}

let seq = 0;
async function rpc(session, method, params) {
  const id = ++seq;
  const res = await fetch(`${base}/mcp`, {
    method: "POST",
    headers: rpcHeaders(session),
    body: JSON.stringify({ jsonrpc: "2.0", id, method, params }),
  });
  const sid = res.headers.get("mcp-session-id");
  if (sid) session.id = sid;
  const text = await res.text();
  if (!res.ok) throw new Error(`http_${res.status}: ${text.slice(0, 200)}`);
  const msg = parseRpcText(text);
  if (!msg) throw new Error(`empty_response: ${text.slice(0, 200)}`);
  if (msg.error) throw new Error(`jsonrpc_${msg.error.code}: ${msg.error.message}`);
  return msg.result;
}

function rpcHeaders(session) {
  const headers = {
    "Content-Type": "application/json",
    Accept: "application/json, text/event-stream",
    Authorization: `Bearer ${token}`,
    "X-Device-Id": device,
    "MCP-Protocol-Version": session.protocol,
  };
  if (session.id) headers["Mcp-Session-Id"] = session.id;
  return headers;
}

function parseRpcText(text) {
  const trimmed = text.trim();
  if (!trimmed) return null;
  if (trimmed.startsWith("{")) {
    try {
      return JSON.parse(trimmed);
    } catch {
      /* fall through to SSE */
    }
  }
  let last = null;
  for (const block of trimmed.split("\n\n")) {
    const data = block
      .split("\n")
      .filter((l) => l.startsWith("data:"))
      .map((l) => l.slice(5).trim())
      .join("\n");
    if (!data) continue;
    try {
      last = JSON.parse(data);
    } catch {
      /* ignore */
    }
  }
  return last;
}

async function closeSession(session) {
  if (!session.id) return;
  await fetch(`${base}/mcp`, {
    method: "DELETE",
    headers: rpcHeaders(session),
  }).catch(() => undefined);
}

function summarizePerf(path) {
  const rows = readFileSync(path, "utf8")
    .split("\n")
    .filter(Boolean)
    .map((l) => {
      try {
        return JSON.parse(l);
      } catch {
        return null;
      }
    })
    .filter(Boolean);
  if (!rows.length) return { samples: 0 };
  const maxRss = Math.max(...rows.map((r) => r.rss_mb || 0));
  const loop95 = rows.map((r) => r.loop_p95_ms || 0).sort((a, b) => a - b);
  const elu = rows.map((r) => r.elu || 0);
  return {
    samples: rows.length,
    rss_max_mb: +maxRss.toFixed(1),
    rss_start_mb: rows[0].rss_mb,
    rss_end_mb: rows[rows.length - 1].rss_mb,
    rss_delta_mb: +(rows[rows.length - 1].rss_mb - rows[0].rss_mb).toFixed(1),
    heap_end_mb: rows[rows.length - 1].heap_used_mb,
    elu_mean: +(elu.reduce((a, b) => a + b, 0) / elu.length).toFixed(4),
    event_loop_p95_ms_median: +loop95[Math.floor(loop95.length / 2)].toFixed(3),
    event_loop_max_ms: +Math.max(...rows.map((r) => r.loop_max_ms || 0)).toFixed(3),
    cpu_user_ms: rows.reduce((a, r) => a + (r.cpu_user_ms || 0), 0),
    cpu_system_ms: rows.reduce((a, r) => a + (r.cpu_system_ms || 0), 0),
  };
}

function stats(values) {
  if (!values.length) return { n: 0, min: null, p50: null, p90: null, p95: null, p99: null, max: null, avg: null };
  const s = [...values].sort((a, b) => a - b);
  const p = (q) => s[Math.min(s.length - 1, Math.floor(q * s.length))];
  return {
    n: s.length,
    min: +s[0].toFixed(2),
    p50: +p(0.5).toFixed(2),
    p90: +p(0.9).toFixed(2),
    p95: +p(0.95).toFixed(2),
    p99: +p(0.99).toFixed(2),
    max: +s[s.length - 1].toFixed(2),
    avg: +(s.reduce((a, b) => a + b, 0) / s.length).toFixed(2),
  };
}

function median(values) {
  if (!values.length) return null;
  const s = [...values].sort((a, b) => a - b);
  return s[Math.floor(s.length / 2)];
}

function parseArgs(argv) {
  const out = {};
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (!a.startsWith("--")) continue;
    const key = a.slice(2);
    const next = argv[i + 1];
    if (!next || next.startsWith("--")) out[key] = true;
    else {
      out[key] = next;
      i++;
    }
  }
  return out;
}

function waitFor(check, timeoutMs, onTimeout) {
  return new Promise((resolvePromise, reject) => {
    const started = Date.now();
    const timer = setInterval(() => {
      if (check()) {
        clearInterval(timer);
        resolvePromise();
      } else if (Date.now() - started > timeoutMs) {
        clearInterval(timer);
        reject(onTimeout ? onTimeout() : new Error("timeout"));
      }
    }, 25);
  });
}

function printSummary(r) {
  const lines = [];
  const add = (name, s) => {
    if (!s) return;
    lines.push(`${name.padEnd(22)} n=${String(s.n).padStart(3)} p50=${String(s.p50).padStart(8)}ms p95=${String(s.p95).padStart(8)}ms p99=${String(s.p99).padStart(8)}ms`);
  };
  add("auth/me", r.auth_me);
  for (const [k, v] of Object.entries(r.protocol)) add(`grouped ${k}`, v);
  for (const [k, v] of Object.entries(r.protocol_flat)) add(`flat ${k}`, v);
  for (const c of r.concurrency) lines.push(`concurrency ${String(c.clients).padStart(2)} clients  init=${c.session_init_ms}ms wall=${c.wall_ms}ms rps=${c.rps} errors=${c.errors} p95=${c.p95}ms`);
  if (r.sustained) lines.push(`sustained ${r.sustained.clients} clients ${r.sustained.duration_ms}ms: ${r.sustained.successes}/${r.sustained.attempts} ok, ${r.sustained.errors} errors, rps=${r.sustained.rps}, p95=${r.sustained.p95}ms`);
  for (const [name, v] of [
    ["rate-limit unauth", r.rate_limit],
    ["rate-limit auth", r.rate_limit_auth],
  ]) {
    if (v) lines.push(`${name}: ${v.allowed} allowed, ${v.rate_limited} limited, first 429 at #${v.first_429_at}`);
  }
  if (r.stdio) lines.push(`stdio: init=${r.stdio.startup_init_ms}ms tools/list p95=${r.stdio.tools_list.p95}ms`);
  if (r.startup_http_ms != null) lines.push(`HTTP startup: ${r.startup_http_ms}ms`);
  console.log(lines.join("\n"));
}
