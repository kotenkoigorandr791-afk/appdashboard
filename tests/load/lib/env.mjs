const num = (k, d) => {
  const v = process.env[k]
  if (v === undefined || v === '') return d
  const n = Number(v)
  return Number.isFinite(n) ? n : d
}
export const env = {
  baseUrl: (process.env.TEST_BASE_URL || process.env.LOAD_BASE_URL || 'http://localhost').replace(/\/$/, ''),
  email: process.env.E2E_USER_EMAIL || process.env.LOAD_USER_EMAIL || 'root@root',
  password: process.env.E2E_USER_PASSWORD || process.env.LOAD_USER_PASSWORD || '12345T',
  vus: num('LOAD_VUS', 15000),
  durationMs: num('LOAD_DURATION_MS', 60000),
  thinkMs: num('LOAD_THINK_MS', 1000),
  rampMs: num('LOAD_RAMP_MS', 15000),
  timeoutMs: num('LOAD_TIMEOUT_MS', 30000),
  maxSockets: num('LOAD_MAX_SOCKETS', 0),
  resultsDir: process.env.LOAD_RESULTS_DIR || new URL('../results', import.meta.url).pathname,
  apps: (process.env.LOAD_APPS || 'project,cloud,films,lopaka,recipes,smart_home,spends,steps').split(',').map(s => s.trim()).filter(Boolean),
  p95Ms: num('LOAD_P95_MS', 2000),
  errorRateMax: num('LOAD_ERROR_RATE_MAX', 0.05),
  okStatuses: new Set((process.env.LOAD_OK_STATUSES || '200,204,301,302,304').split(',').map(s => Number(s.trim())).filter(Number.isFinite)),
}
