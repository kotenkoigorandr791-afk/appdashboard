import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"

vi.mock("~/i18n/config", () => ({ default: { t: (key: string) => key } }))
vi.mock("~/utils/toast", () => ({
  Toast: { success: vi.fn(), error: vi.fn(), warn: vi.fn() },
}))
vi.mock("~/services/spends-api", () => ({
  baseUrl: () => "http://srv/api/spends",
  authHeaders: () => ({ Authorization: "Bearer t" }),
}))

import { exchangeRatesService } from "~/services/exchange-rates"

const serverUsd = {
  status: "success",
  base: "USD",
  rates: { USD: 1, EUR: 0.89, SYP: 121.7, BTC: 0.000009 },
}

function stubFetch(ok: boolean, body: unknown = serverUsd) {
  const fetchMock = vi.fn(async () => ({
    ok,
    status: ok ? 200 : 500,
    statusText: "down",
    json: async () => body,
  }))
  vi.stubGlobal("fetch", fetchMock)
  return fetchMock
}

beforeEach(() => exchangeRatesService.clearCache())
afterEach(() => vi.unstubAllGlobals())

describe("exchange rates from the server", () => {
  it("reads the same rates the web uses, lowercased", async () => {
    const fetchMock = stubFetch(true)
    const rates = await exchangeRatesService.fetchRates("USD")
    expect(rates?.rates.eur).toBe(0.89)
    expect(rates?.rates.syp).toBe(121.7)
    expect(rates?.rates.btc).toBe(0.000009)
    const [url, init] = fetchMock.mock.calls[0] as unknown as [
      string,
      { headers: Record<string, string> },
    ]
    expect(url).toBe("http://srv/api/spends?get_exchange_rates=1&base=USD")
    expect(init.headers.Authorization).toBe("Bearer t")
  })

  it("returns null when the server is down", async () => {
    stubFetch(false)
    expect(await exchangeRatesService.fetchRates("USD")).toBeNull()
  })

  it("rejects an error payload", async () => {
    stubFetch(true, { status: "error" })
    expect(await exchangeRatesService.fetchRates("USD")).toBeNull()
  })

  it("caches per base currency", async () => {
    const fetchMock = stubFetch(true)
    await exchangeRatesService.fetchRates("USD")
    await exchangeRatesService.fetchRates("USD")
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })
})
