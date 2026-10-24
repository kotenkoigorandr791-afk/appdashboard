import http from 'node:http'
import https from 'node:https'
import { URL } from 'node:url'
export function createAgent(baseUrl, maxSockets) {
  const u = new URL(baseUrl)
  const Agent = u.protocol === 'https:' ? https.Agent : http.Agent
  return new Agent({ keepAlive: true, maxSockets: maxSockets > 0 ? maxSockets : Infinity, maxFreeSockets: 256, scheduling: 'lifo' })
}
export function request(agent, baseUrl, { method = 'GET', path, headers = {}, body, timeoutMs = 30000, cookie } = {}) {
  const u = new URL(path, baseUrl)
  const lib = u.protocol === 'https:' ? https : http
  const payload = body == null ? null : Buffer.isBuffer(body) ? body : Buffer.from(String(body))
  const hdrs = { ...headers, Connection: 'keep-alive' }
  if (cookie) hdrs.Cookie = cookie
  if (payload) {
    hdrs['Content-Length'] = String(payload.length)
    if (!hdrs['Content-Type']) hdrs['Content-Type'] = 'application/x-www-form-urlencoded'
  }
  const t0 = Date.now()
  return new Promise((resolve) => {
    const req = lib.request({
      protocol: u.protocol,
      hostname: u.hostname,
      port: u.port || (u.protocol === 'https:' ? 443 : 80),
      path: u.pathname + u.search,
      method,
      headers: hdrs,
      agent,
      timeout: timeoutMs,
    }, (res) => {
      const chunks = []
      res.on('data', (c) => chunks.push(c))
      res.on('end', () => {
        const buf = Buffer.concat(chunks)
        resolve({ status: res.statusCode || 0, ms: Date.now() - t0, bytes: buf.length, headers: res.headers, body: buf, error: null })
      })
    })
    req.on('timeout', () => { req.destroy(new Error('timeout')); resolve({ status: 0, ms: Date.now() - t0, bytes: 0, headers: {}, body: Buffer.alloc(0), error: 'timeout' }) })
    req.on('error', (e) => resolve({ status: 0, ms: Date.now() - t0, bytes: 0, headers: {}, body: Buffer.alloc(0), error: e.message || 'error' }))
    if (payload) req.write(payload)
    req.end()
  })
}
export function pickSetCookie(headers) {
  const raw = headers['set-cookie']
  if (!raw) return ''
  const list = Array.isArray(raw) ? raw : [raw]
  const map = new Map()
  for (const line of list) {
    const part = String(line).split(';')[0]
    const i = part.indexOf('=')
    if (i > 0) map.set(part.slice(0, i), part.slice(i + 1))
  }
  return [...map.entries()].map(([k, v]) => `${k}=${v}`).join('; ')
}
export function mergeCookie(prev, headers) {
  const next = pickSetCookie(headers)
  if (!next) return prev || ''
  const map = new Map()
  for (const part of String(prev || '').split(';').map(s => s.trim()).filter(Boolean)) {
    const i = part.indexOf('=')
    if (i > 0) map.set(part.slice(0, i), part.slice(i + 1))
  }
  for (const part of next.split(';').map(s => s.trim()).filter(Boolean)) {
    const i = part.indexOf('=')
    if (i > 0) map.set(part.slice(0, i), part.slice(i + 1))
  }
  return [...map.entries()].map(([k, v]) => `${k}=${v}`).join('; ')
}
