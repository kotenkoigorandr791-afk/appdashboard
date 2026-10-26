#!/usr/bin/env node
import { spawn } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { env } from './lib/env.mjs'
const __dirname = path.dirname(fileURLToPath(import.meta.url))
const resultsDir = env.resultsDir
fs.mkdirSync(resultsDir, { recursive: true })
const stamp = new Date().toISOString().replace(/[:.]/g, '-')
const summaryPath = path.join(resultsDir, `summary-${stamp}.json`)
console.log(`load-all apps=${env.apps.join(',')} vus=${env.vus} durationMs=${env.durationMs} thinkMs=${env.thinkMs}`)
const children = env.apps.map((app) => new Promise((resolve) => {
  const logFile = path.join(resultsDir, `${app}-${stamp}.log`)
  const out = fs.createWriteStream(logFile)
  const child = spawn(process.execPath, [path.join(__dirname, 'run-app.mjs'), app], {
    env: { ...process.env, LOAD_RESULTS_DIR: resultsDir },
    stdio: ['ignore', 'pipe', 'pipe'],
  })
  child.stdout.pipe(out)
  child.stderr.pipe(out)
  child.stdout.on('data', (b) => process.stdout.write(`[${app}] ${b}`))
  child.stderr.on('data', (b) => process.stderr.write(`[${app}] ${b}`))
  child.on('close', (code) => resolve({ app, code: code ?? 1, logFile }))
}))
const settled = await Promise.all(children)
const files = fs.readdirSync(resultsDir).filter((f) => f.endsWith('.json') && !f.startsWith('summary-'))
const latestByApp = new Map()
for (const f of files.sort()) {
  try {
    const j = JSON.parse(fs.readFileSync(path.join(resultsDir, f), 'utf8'))
    if (j.app) latestByApp.set(j.app, j)
  } catch {}
}
const results = env.apps.map((app) => latestByApp.get(app) || { app, passed: false, error: 'no result' })
const summary = {
  at: new Date().toISOString(),
  config: { vus: env.vus, durationMs: env.durationMs, thinkMs: env.thinkMs, p95Ms: env.p95Ms, errorRateMax: env.errorRateMax, baseUrl: env.baseUrl },
  processes: settled,
  results,
  allPassed: results.every((r) => r.passed),
}
fs.writeFileSync(summaryPath, JSON.stringify(summary, null, 2))
console.log('--- summary ---')
for (const r of results) {
  if (r.latency) {
    console.log(`${r.passed ? 'PASS' : 'FAIL'} ${r.app} req=${r.requests} err=${((r.errorRate || 0) * 100).toFixed(2)}% rps=${(r.rps || 0).toFixed(1)} p95=${r.latency.p95}`)
  } else {
    console.log(`FAIL ${r.app} ${r.error || 'unknown'}`)
  }
}
console.log(`summary=${summaryPath} allPassed=${summary.allPassed}`)
process.exit(summary.allPassed ? 0 : 1)
