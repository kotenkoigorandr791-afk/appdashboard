import fs from 'node:fs'
import path from 'node:path'
import { createAgent, request } from './http.mjs'
import { createMetrics, passThresholds } from './metrics.mjs'
import { login } from './auth.mjs'
import { env as defaultEnv } from './env.mjs'
const sleep = (ms) => new Promise((r) => setTimeout(r, ms))
export async function runScenario(scenario, overrides = {}) {
  const env = { ...defaultEnv, ...overrides }
  const agent = createAgent(env.baseUrl, env.maxSockets)
  const metrics = createMetrics()
  const cookie = await login(agent, env)
  const stopAt = Date.now() + env.durationMs
  let active = 0
  let started = 0
  let stopping = false
  metrics.begin()
  const workers = []
  const spawnOne = async () => {
    active++
    started++
    try {
      while (!stopping && Date.now() < stopAt) {
        let step
        try { step = await scenario.next({ env, cookie, vu: started }) } catch (e) { metrics.record(0, 0, 0, false); break }
        if (!step) break
        const res = await request(agent, env.baseUrl, { ...step, cookie, timeoutMs: env.timeoutMs })
        const ok = !res.error && env.okStatuses.has(res.status)
        metrics.record(res.ms, res.status || 0, res.bytes, ok)
        if (env.thinkMs > 0) await sleep(env.thinkMs)
      }
    } finally { active-- }
  }
  const rampStep = Math.max(1, Math.floor(env.vus / Math.max(1, Math.ceil(env.rampMs / 100))))
  const rampEvery = Math.max(1, Math.floor(env.rampMs / Math.max(1, Math.ceil(env.vus / rampStep))))
  while (started < env.vus && Date.now() < stopAt) {
    const batch = Math.min(rampStep, env.vus - started)
    for (let i = 0; i < batch; i++) workers.push(spawnOne())
    await sleep(rampEvery)
  }
  await sleep(Math.max(0, stopAt - Date.now()))
  stopping = true
  await Promise.all(workers)
  metrics.finish()
  agent.destroy()
  const snap = metrics.snapshot()
  const passed = passThresholds(snap, env)
  return { app: scenario.name, passed, env: { vus: env.vus, durationMs: env.durationMs, thinkMs: env.thinkMs, baseUrl: env.baseUrl }, ...snap }
}
export function writeResult(result, resultsDir) {
  fs.mkdirSync(resultsDir, { recursive: true })
  const file = path.join(resultsDir, `${result.app}-${Date.now()}.json`)
  fs.writeFileSync(file, JSON.stringify(result, null, 2))
  return file
}
export function printResult(result) {
  const { latency: l } = result
  const mark = result.passed ? 'PASS' : 'FAIL'
  console.log(`[${mark}] ${result.app} vus=${result.env.vus} req=${result.requests} ok=${result.ok} fail=${result.fail} err=${(result.errorRate * 100).toFixed(2)}% rps=${result.rps.toFixed(1)} p50=${l.p50.toFixed(0)} p95=${l.p95.toFixed(0)} p99=${l.p99.toFixed(0)} max=${l.max.toFixed(0)}`)
}
