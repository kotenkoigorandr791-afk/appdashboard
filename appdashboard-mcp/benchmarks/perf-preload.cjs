// Benchmark-only instrumentation. Loaded with `node --require` so the MCP
// server itself stays free of diagnostics code. Writes one JSON object per
// second to MCP_PERF_LOG: RSS/heap, CPU deltas and event-loop health.
const fs = require("node:fs");
const { performance, monitorEventLoopDelay } = require("node:perf_hooks");

const out = process.env.MCP_PERF_LOG;
if (out) {
  const stream = fs.createWriteStream(out, { flags: "a" });
  const loop = monitorEventLoopDelay({ resolution: 10 });
  loop.enable();
  let prevCpu = process.cpuUsage();
  let prevElu = performance.eventLoopUtilization();
  let prevAt = performance.now();
  const timer = setInterval(() => {
    const now = performance.now();
    const cpu = process.cpuUsage(prevCpu);
    const elu = performance.eventLoopUtilization(prevElu);
    const mem = process.memoryUsage();
    const wallMs = Math.max(1, now - prevAt);
    stream.write(
      JSON.stringify({
        t: Date.now(),
        wall_ms: Math.round(wallMs),
        cpu_user_ms: Math.round(cpu.user / 1000),
        cpu_system_ms: Math.round(cpu.system / 1000),
        rss_mb: +(mem.rss / 1048576).toFixed(1),
        heap_used_mb: +(mem.heapUsed / 1048576).toFixed(1),
        external_mb: +(mem.external / 1048576).toFixed(1),
        elu: +elu.utilization.toFixed(4),
        loop_p50_ms: +(loop.percentile(50) / 1e6).toFixed(3),
        loop_p95_ms: +(loop.percentile(95) / 1e6).toFixed(3),
        loop_max_ms: +(loop.max / 1e6).toFixed(3),
      }) + "\n",
    );
    prevCpu = process.cpuUsage();
    prevElu = performance.eventLoopUtilization();
    prevAt = now;
    loop.reset();
  }, 1000);
  timer.unref();
  process.on("exit", () => stream.end());
}
