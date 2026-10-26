import { request, mergeCookie } from './http.mjs'
function extractCsrf(html) {
  const m = String(html).match(/name="_csrf"\s+value="([^"]+)"/) || String(html).match(/name="csrf-token"\s+content="([^"]+)"/)
  return m ? m[1] : ''
}
export async function login(agent, env) {
  let cookie = ''
  const page = await request(agent, env.baseUrl, { path: '/login', timeoutMs: env.timeoutMs })
  cookie = mergeCookie(cookie, page.headers)
  const csrf = extractCsrf(page.body.toString('utf8'))
  const body = new URLSearchParams({ email: env.email, password: env.password, login: '1', remember_me: '1', _csrf: csrf }).toString()
  const post = await request(agent, env.baseUrl, { method: 'POST', path: '/login', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body, cookie, timeoutMs: env.timeoutMs })
  cookie = mergeCookie(cookie, post.headers)
  if (!/PHPSESSID=/.test(cookie)) throw new Error(`login failed: status=${post.status} cookie=${cookie.slice(0, 80)}`)
  return cookie
}
