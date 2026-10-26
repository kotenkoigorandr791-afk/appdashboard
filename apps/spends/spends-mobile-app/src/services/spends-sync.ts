import * as Crypto from "expo-crypto"
import { and, eq, or } from "drizzle-orm"
import { AppState, type AppStateStatus, Platform } from "react-native"

import { drizzleDb } from "~/database/drizzle/db"
import { accounts, categories, transactions } from "~/database/drizzle/schema"
import { generateId } from "~/database/utils/generate-id"
import { createTransaction } from "~/database/services/ledger-service"
import type { TransactionFormValues } from "~/schemas/transactions.schema"
import {
  fetchSpendsProfile,
  normalizePairingEndpoint,
  parsePairingLink,
  pullSpendsSync,
  pushSpendsSync,
  type SpendsSyncPushPayload,
} from "~/services/spends-api"
import { useProfileStore } from "~/stores/profile.store"
import { useServerSyncStore } from "~/stores/server-sync.store"
import { logger } from "~/utils/logger"
import { parseMajorUnits, toMajorUnits } from "~/utils/money"

let syncing = false
let appStateSub: { remove: () => void } | null = null
let netTimer: ReturnType<typeof setInterval> | null = null

export async function spendsSignature(
  date: string,
  store: string,
  amount: number,
  raw = "",
): Promise<string> {
  const payload = [
    date,
    store.trim().toLowerCase(),
    Number(amount).toFixed(2),
    raw.trim().toLowerCase(),
  ].join("|")
  return Crypto.digestStringAsync(Crypto.CryptoDigestAlgorithm.SHA256, payload)
}

async function networkAllowsSync(): Promise<boolean> {
  const { wifiOnly, allowMobileData } = useServerSyncStore.getState()
  if (!wifiOnly && allowMobileData) return true
  try {
    const NetInfo = await import("@react-native-community/netinfo")
    const state = await NetInfo.default.fetch()
    if (!state.isConnected) return false
    if (wifiOnly) return state.type === "wifi" || state.type === "ethernet"
    return true
  } catch {
    return true
  }
}

function ymd(d: Date | string | number): string {
  const dt = d instanceof Date ? d : new Date(d)
  const y = dt.getFullYear()
  const m = String(dt.getMonth() + 1).padStart(2, "0")
  const day = String(dt.getDate()).padStart(2, "0")
  return `${y}-${m}-${day}`
}

async function ensureLocalStoreCategory(name: string): Promise<string | null> {
  const existing = drizzleDb
    .select()
    .from(categories)
    .where(eq(categories.name, name))
    .all()
  if (existing[0]) return existing[0].id
  const id = generateId()
  const now = new Date().toISOString()
  drizzleDb
    .insert(categories)
    .values({
      id,
      name,
      type: "expense",
      icon: "basket",
      colorSchemeName: null,
      createdAt: now,
      updatedAt: now,
    })
    .run()
  return id
}

async function ensurePrimaryAccount(currency: string): Promise<string> {
  const rows = drizzleDb
    .select()
    .from(accounts)
    .where(eq(accounts.isPrimary, 1))
    .all()
  if (rows[0]) return rows[0].id
  const any = drizzleDb.select().from(accounts).all()
  if (any[0]) return any[0].id
  const id = generateId()
  const now = new Date().toISOString()
  drizzleDb
    .insert(accounts)
    .values({
      id,
      name: "Spends",
      type: "checking",
      balance: 0,
      currencyCode: currency || "USD",
      icon: "wallet",
      colorSchemeName: null,
      isPrimary: 1,
      excludeFromBalance: 0,
      isArchived: 0,
      sortOrder: 0,
      createdAt: now,
      updatedAt: now,
    })
    .run()
  return id
}

export async function pairFromLink(link: string): Promise<{ email: string }> {
  const parsed = parsePairingLink(link)
  if (!parsed) throw new Error("invalid_pairing_link")
  return pairManual(parsed.endpoint, parsed.token)
}

export async function pairManual(
  endpointRaw: string,
  token: string,
): Promise<{ email: string }> {
  const endpoint = normalizePairingEndpoint(endpointRaw)
  if (!endpoint || !token.trim()) throw new Error("invalid_pairing")
  useServerSyncStore.getState().ensureDeviceId()
  const profile = await fetchSpendsProfile(endpoint, token.trim())
  useServerSyncStore
    .getState()
    .setPairing(endpoint, token.trim(), profile.email || "")
  if (profile.email) {
    const localName = profile.email.split("@")[0] || profile.email
    useProfileStore.getState().setName(localName)
  }
  return { email: profile.email || "" }
}

export async function performSpendsSync(): Promise<{
  created: number
  skipped: number
  pulled: number
}> {
  if (syncing) return { created: 0, skipped: 0, pulled: 0 }
  const store = useServerSyncStore.getState()
  if (!store.isPaired()) return { created: 0, skipped: 0, pulled: 0 }
  if (!(await networkAllowsSync())) {
    store.setStatus("error", "wifi_required")
    throw new Error("wifi_required")
  }
  syncing = true
  store.setStatus("syncing")
  try {
    const pull = await pullSpendsSync()
    store.setKnownSignatures(pull.known_signatures || [])
    if (pull.profile?.email) {
      store.setPairing(store.endpoint, store.token, pull.profile.email)
      const localName = pull.profile.email.split("@")[0] || pull.profile.email
      if (!useProfileStore.getState().name) {
        useProfileStore.getState().setName(localName)
      }
    }

    const currency = pull.settings?.currency || "USD"
    const accountId = await ensurePrimaryAccount(currency)
    const known = new Set(store.knownSignatures)
    let pulled = 0

    for (const exp of pull.expenses || []) {
      const sig =
        exp.signature ||
        (await spendsSignature(exp.date, exp.store, exp.amount, exp.ocr_raw_store || ""))
      if (known.has(sig)) continue
      const catId = await ensureLocalStoreCategory(exp.store)
      const minor = parseMajorUnits(String(exp.amount), currency)
      const txData: TransactionFormValues = {
        accountId,
        categoryId: catId,
        amount: minor,
        type: "expense",
        transactionDate: new Date(`${exp.date}T12:00:00`),
        title: exp.store,
        description: exp.ocr_raw_store || undefined,
        isPending: false,
        tags: [],
        extra: { spendsSignature: sig, spendsSource: exp.entry_source || "server" },
      }
      await createTransaction(txData)
      known.add(sig)
      pulled++
    }

    for (const inc of pull.incomes || []) {
      const sig =
        inc.signature ||
        (await spendsSignature(inc.date, "__income__", inc.amount))
      if (known.has(sig)) continue
      const minor = parseMajorUnits(String(inc.amount), currency)
      const txData: TransactionFormValues = {
        accountId,
        categoryId: null,
        amount: minor,
        type: "income",
        transactionDate: new Date(`${inc.date}T12:00:00`),
        title: "Income",
        isPending: false,
        tags: [],
        extra: { spendsSignature: sig, spendsSource: "server" },
      }
      await createTransaction(txData)
      known.add(sig)
      pulled++
    }

    const localTx = drizzleDb
      .select()
      .from(transactions)
      .where(
        and(
          eq(transactions.isDeleted, 0),
          or(eq(transactions.type, "expense"), eq(transactions.type, "income")),
        ),
      )
      .all()

    const payload: SpendsSyncPushPayload = {
      stores: (pull.stores || []).map((s) => ({
        name: s.name,
        category: s.category || "other",
      })),
      expenses: [],
      incomes: [],
      settings: pull.settings,
    }

    const accMap = new Map(
      drizzleDb
        .select()
        .from(accounts)
        .all()
        .map((a) => [a.id, a]),
    )

    for (const tx of localTx) {
      let extra: Record<string, string> = {}
      try {
        extra = tx.extra ? (JSON.parse(tx.extra) as Record<string, string>) : {}
      } catch {
        extra = {}
      }
      if (extra.spendsSource === "server") continue
      const date = ymd(tx.transactionDate)
      const acc = accMap.get(tx.accountId)
      const cur = acc?.currencyCode || currency
      const amount = Math.abs(toMajorUnits(tx.amount, cur))
      const storeName = (tx.title || "Other").trim() || "Other"
      const raw = tx.description || ""
      const sig =
        extra.spendsSignature ||
        (await spendsSignature(
          date,
          tx.type === "income" ? "__income__" : storeName,
          amount,
          raw,
        ))
      if (known.has(sig)) continue
      if (tx.type === "income") {
        payload.incomes!.push({
          date,
          amount,
          signature: sig,
          client_id: tx.id,
          mode: "skip_if_known",
        })
      } else {
        payload.stores!.push({ name: storeName, category: "other" })
        payload.expenses!.push({
          date,
          store: storeName,
          amount,
          raw,
          signature: sig,
          source: "mobile",
          client_id: tx.id,
          mode: "skip_if_known",
        })
      }
    }

    const push = await pushSpendsSync(payload)
    const newSigs = [
      ...(payload.expenses || []).map((e) => e.signature || ""),
      ...(payload.incomes || []).map((e) => e.signature || ""),
    ].filter(Boolean)
    store.addKnownSignatures([...known, ...newSigs])
    store.setLastSyncAt(Date.now())
    store.setStatus("ok")
    return {
      created: push.created + pulled,
      skipped: push.skipped,
      pulled,
    }
  } catch (e) {
    const msg = e instanceof Error ? e.message : String(e)
    logger.error("spends sync failed", { error: msg })
    useServerSyncStore.getState().setStatus("error", msg)
    throw e
  } finally {
    syncing = false
  }
}

export async function ingestWalletEvent(event: {
  store: string
  amount: number
  raw?: string
  date?: string
}): Promise<boolean> {
  const state = useServerSyncStore.getState()
  if (!state.isPaired()) return false
  const date = event.date || ymd(new Date())
  const store = event.store.trim()
  const amount = Number(event.amount)
  if (!store || !(amount > 0)) return false
  const raw = event.raw || ""
  const sig = await spendsSignature(date, store, amount, raw || "wallet")
  if (state.knownSignatures.includes(sig)) return false
  if (!(await networkAllowsSync())) return false
  try {
    await pushSpendsSync({
      wallet_events: [
        {
          date,
          store,
          amount,
          raw,
          signature: sig,
          client_id: sig.slice(0, 16),
        },
      ],
    })
  } catch (e) {
    logger.error("wallet push failed", {
      error: e instanceof Error ? e.message : String(e),
    })
    return false
  }
  const currency = "USD"
  const accountId = await ensurePrimaryAccount(currency)
  const catId = await ensureLocalStoreCategory(store)
  const txData: TransactionFormValues = {
    accountId,
    categoryId: catId,
    amount: parseMajorUnits(String(amount), currency),
    type: "expense",
    transactionDate: new Date(`${date}T12:00:00`),
    title: store,
    description: raw || "Google Wallet",
    isPending: true,
    requiresManualConfirmation: true,
    tags: [],
    extra: {
      spendsSignature: sig,
      spendsSource: "wallet",
    },
  }
  await createTransaction(txData)
  state.addKnownSignatures([sig])
  return true
}

export function startSpendsSyncScheduler(): () => void {
  if (Platform.OS === "web") return () => {}
  const onAppState = (next: AppStateStatus) => {
    if (next === "active") {
      performSpendsSync().catch(() => {})
    }
  }
  appStateSub?.remove()
  appStateSub = AppState.addEventListener("change", onAppState)
  if (netTimer) clearInterval(netTimer)
  netTimer = setInterval(() => {
    performSpendsSync().catch(() => {})
  }, 5 * 60 * 1000)
  performSpendsSync().catch(() => {})
  return () => {
    appStateSub?.remove()
    appStateSub = null
    if (netTimer) clearInterval(netTimer)
    netTimer = null
  }
}
