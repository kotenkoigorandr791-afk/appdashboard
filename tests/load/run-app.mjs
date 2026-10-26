#!/usr/bin/env node
import { env } from './lib/env.mjs'
import { runScenario, writeResult, printResult } from './lib/runner.mjs'
const name = process.argv[2] || process.env.LOAD_APP
if (!name) {
  console.error('Usage: node load/run-app.mjs <app>')
  process.exit(2)
}
const mod = await import(`./scenarios/${name}.mjs`)
const result = await runScenario(mod.default)
const file = writeResult(result, env.resultsDir)
printResult(result)
console.log(`result=${file}`)
process.exit(result.passed ? 0 : 1)
