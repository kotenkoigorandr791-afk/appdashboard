# AppDashboard MCP benchmarks

These scripts drive the **real** MCP server over Streamable HTTP and STDIO. They
never print the bearer token or device id.

## Prerequisites

1. Build the server: `npm run build` (or `npm test`).
2. A local AppDashboard instance reachable at `http://localhost`.
3. A temporary test token and device id in two gitignored files:
   - `../.bench-token` (relative to `appdashboard-mcp/`)
   - `../.bench-device`
4. For write/destructive tools, do not use production data. The benchmark suite
   only calls read-only/discovery tools; the smoke test uses a mock backend.

## Run

```bash
cd appdashboard-mcp

# Auth endpoint only
node benchmarks/bench.mjs --label baseline --scenarios auth

# Grouped and legacy flat discovery/calls + STDIO
node benchmarks/bench.mjs --label baseline --scenarios protocol,protocol_flat,stdio

# Concurrency + rate-limit behavior (isolated MCP limit for concurrency)
node benchmarks/bench.mjs --label baseline-load --scenarios concurrency,rate-limit,rate-limit-auth

# Sustained load and memory/event-loop samples
MCP_AUTH_CACHE_TTL_MS=600000 \
  node benchmarks/bench.mjs --label after-soak --scenarios sustained \
  --duration-ms 30000 --sustained-clients 10

# Inventory all registered tools
node benchmarks/inventory.mjs
```

Results are written to `benchmarks/results/<label>.json`. Server perf samples are
written to `benchmarks/results/<label>-<scenario>-perf.jsonl` and are gitignored
along with server logs.

## What each scenario does

- `auth` — direct `GET /public-api/auth/me` latency.
- `protocol` — one warm session, repeated `tools/list`, `tools_catalog`,
  `tools_search`, `tools_load`, `tools_call`, unknown/invalid calls.
- `protocol_flat` — same with `MCP_GROUPED_TOOLS=0`.
- `concurrency` — creates N sessions, then measures 3 warm `tools/list` rounds per
  session; uses an isolated `MCP_RATE_LIMIT_MAX`.
- `rate-limit` / `rate-limit-auth` — default 120/min limiter behavior for
  unauthenticated and authenticated bursts.
- `sustained` — N sessions continuously call `tools/list` for the configured
  duration; records attempts, successes, errors, latency, RSS/heap/ELU.

## Notes

- Production rate limits are preserved by default. Only the isolated concurrency
  and sustained scenarios override `MCP_RATE_LIMIT_MAX`; rate-limit scenarios use
  the defaults.
- Do not commit `.bench-token` or `.bench-device`.
- The MCP process is spawned by the harness and killed when the scenario ends.
- `MCP_PERF_LOG` is set by the harness; `perf-preload.cjs` is loaded with
  `node --require` and does not require changes to the server.
