import { useServerSyncStore } from "~/stores/server-sync.store"

export type SpendsProfile = { email: string; app: string }

export type SpendsSyncPull = {
  status: string
  server_time: number
  profile: SpendsProfile
  settings: {
    monthly_budget: number
    currency: string
    show_incomes: boolean
    show_trend: boolean
    trim_empty_months: boolean
  }
  stores: Array<{
    id: string
    name: string
    visible?: boolean
    category?: string
  }>
  expenses: Array<{
    date: string
    store: string
    amount: number
    entry_source?: string
    ocr_raw_store?: string
    signature: string
  }>
  incomes: Array<{ date: string; amount: number; signature: string }>
  subscriptions: unknown[]
  known_signatures: string[]
}

export type SpendsSyncPushPayload = {
  stores?: Array<{ name: string; category?: string }>
  expenses?: Array<{
    date: string
    store: string
    amount: number
    raw?: string
    signature?: string
    source?: string
    client_id?: string
    mode?: string
    category?: string
  }>
  incomes?: Array<{
    date: string
    amount: number
    signature?: string
    client_id?: string
    mode?: string
  }>
  wallet_events?: Array<{
    date: string
    store: string
    amount: number
    raw?: string
    signature?: string
    client_id?: string
    category?: string
  }>
  settings?: Partial<SpendsSyncPull["settings"]>
}

export type SpendsSyncPushResult = {
  status: string
  created: number
  updated: number
  skipped: number
  errors: unknown[]
  server_time: number
}

function authHeaders(): HeadersInit {
  const { token, ensureDeviceId } = useServerSyncStore.getState()
  const deviceId = ensureDeviceId()
  return {
    Authorization: `Bearer ${token}`,
    "X-Device-Id": deviceId,
    Accept: "application/json",
  }
}

function baseUrl(): string {
  const endpoint = useServerSyncStore.getState().endpoint.replace(/\/+$/, "")
  if (!endpoint) throw new Error("not_paired")
  return endpoint
}

async function parseJson<T>(res: Response): Promise<T> {
  const text = await res.text()
  let data: unknown = null
  try {
    data = text ? JSON.parse(text) : null
  } catch {
    throw new Error(`invalid_json:${res.status}`)
  }
  if (!res.ok) {
    const err =
      data && typeof data === "object" && "error" in data
        ? String((data as { error: unknown }).error)
        : `http_${res.status}`
    throw new Error(err)
  }
  return data as T
}

export function normalizePairingEndpoint(raw: string): string {
  let url = raw.trim().replace(/\/+$/, "")
  if (!url) return ""
  if (!/^https?:\/\//i.test(url)) url = `http://${url}`
  if (/\/public-api\/spends$/i.test(url)) return url
  if (/\/api\/spends$/i.test(url)) return url
  if (/\/api$/i.test(url)) return `${url}/spends`
  if (/\/public-api$/i.test(url)) return `${url}/spends`
  return `${url.replace(/\/+$/, "")}/api/spends`
}

export function parsePairingLink(link: string): {
  endpoint: string
  token: string
} | null {
  const trimmed = link.trim()
  if (!trimmed) return null
  try {
    const url = new URL(trimmed)
    if (url.protocol === "spends:" && url.hostname === "auth") {
      const endpoint = url.searchParams.get("endpoint") || ""
      const token = url.searchParams.get("token") || ""
      if (endpoint && token) {
        return { endpoint: normalizePairingEndpoint(endpoint), token }
      }
    }
    const endpoint = url.searchParams.get("endpoint")
    const token = url.searchParams.get("token")
    if (endpoint && token) {
      return { endpoint: normalizePairingEndpoint(endpoint), token }
    }
  } catch {
    /* fall through */
  }
  return null
}

export async function fetchSpendsProfile(
  endpoint?: string,
  token?: string,
): Promise<SpendsProfile> {
  const ep = (endpoint || baseUrl()).replace(/\/+$/, "")
  const tok = token || useServerSyncStore.getState().token
  const deviceId = useServerSyncStore.getState().ensureDeviceId()
  const urls = [`${ep}/profile`, `${ep}?mobile_profile=1`]
  let lastErr: Error | null = null
  for (const url of urls) {
    try {
      const res = await fetch(url, {
        headers: {
          Authorization: `Bearer ${tok}`,
          "X-Device-Id": deviceId,
          Accept: "application/json",
        },
      })
      const data = await parseJson<{
        status?: string
        profile?: SpendsProfile
      }>(res)
      if (data.profile?.email !== undefined) return data.profile
    } catch (e) {
      lastErr = e instanceof Error ? e : new Error(String(e))
    }
  }
  throw lastErr || new Error("profile_failed")
}

export async function pullSpendsSync(): Promise<SpendsSyncPull> {
  const ep = baseUrl()
  const urls = [`${ep}/sync`, `${ep}?mobile_sync=1`]
  let lastErr: Error | null = null
  for (const url of urls) {
    try {
      const res = await fetch(url, { headers: authHeaders() })
      return await parseJson<SpendsSyncPull>(res)
    } catch (e) {
      lastErr = e instanceof Error ? e : new Error(String(e))
    }
  }
  throw lastErr || new Error("pull_failed")
}

export async function pushSpendsSync(
  payload: SpendsSyncPushPayload,
): Promise<SpendsSyncPushResult> {
  const ep = baseUrl()
  const body = JSON.stringify(payload)
  const headers = {
    ...authHeaders(),
    "Content-Type": "application/json",
  }
  const tryJson = async (url: string) => {
    const res = await fetch(url, { method: "POST", headers, body })
    return parseJson<SpendsSyncPushResult>(res)
  }
  try {
    return await tryJson(`${ep}/sync`)
  } catch {
    const form = new URLSearchParams()
    form.set("mobile_sync_push", body)
    const res = await fetch(ep, {
      method: "POST",
      headers: { ...authHeaders(), "Content-Type": "application/x-www-form-urlencoded" },
      body: form.toString(),
    })
    return parseJson<SpendsSyncPushResult>(res)
  }
}
