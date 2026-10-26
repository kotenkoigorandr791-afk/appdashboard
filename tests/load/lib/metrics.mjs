export function createMetrics() {
  const lat = []
  let ok = 0, fail = 0, bytes = 0, start = 0, end = 0
  const byStatus = new Map()
  return {
    begin() { start = Date.now() },
    finish() { end = Date.now() },
    record(ms, status, size = 0, success) {
      lat.push(ms)
      bytes += size
      byStatus.set(status, (byStatus.get(status) || 0) + 1)
      if (success) ok++; else fail++
    },
    snapshot() {
      const sorted = lat.slice().sort((a, b) => a - b)
      const pct = (p) => sorted.length ? sorted[Math.min(sorted.length - 1, Math.floor((p / 100) * sorted.length))] : 0
      const elapsed = Math.max(1, (end || Date.now()) - (start || Date.now()))
      const total = ok + fail
      return {
        requests: total,
        ok,
        fail,
        errorRate: total ? fail / total : 0,
        rps: (total * 1000) / elapsed,
        bytes,
        durationMs: elapsed,
        latency: { p50: pct(50), p90: pct(90), p95: pct(95), p99: pct(99), max: sorted.at(-1) || 0, avg: sorted.length ? sorted.reduce((a, b) => a + b, 0) / sorted.length : 0 },
        status: Object.fromEntries(byStatus),
      }
    },
  }
}
export function passThresholds(snap, { p95Ms, errorRateMax }) {
  return snap.errorRate <= errorRateMax && snap.latency.p95 <= p95Ms
}
