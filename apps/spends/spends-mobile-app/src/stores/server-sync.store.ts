import * as Crypto from "expo-crypto"
import { createMMKV } from "react-native-mmkv"
import { create } from "zustand"
import { persist } from "zustand/middleware"

import { mmkvJSONStorage } from "~/utils/mmkv-storage"

const storage = createMMKV({ id: "spends-server-sync" })

export type SyncStatus = "idle" | "syncing" | "ok" | "error"

interface ServerSyncState {
  endpoint: string
  token: string
  deviceId: string
  email: string
  wifiOnly: boolean
  allowMobileData: boolean
  knownSignatures: string[]
  lastSyncAt: number | null
  lastError: string | null
  status: SyncStatus
  setPairing: (endpoint: string, token: string, email?: string) => void
  clearPairing: () => void
  setWifiOnly: (value: boolean) => void
  setAllowMobileData: (value: boolean) => void
  setStatus: (status: SyncStatus, error?: string | null) => void
  setLastSyncAt: (ts: number) => void
  addKnownSignatures: (sigs: string[]) => void
  setKnownSignatures: (sigs: string[]) => void
  ensureDeviceId: () => string
  isPaired: () => boolean
}

function randomDeviceId(): string {
  return Array.from(Crypto.getRandomBytes(32))
    .map((b) => b.toString(16).padStart(2, "0"))
    .join("")
}

export const useServerSyncStore = create<ServerSyncState>()(
  persist(
    (set, get) => ({
      endpoint: "",
      token: "",
      deviceId: "",
      email: "",
      wifiOnly: false,
      allowMobileData: true,
      knownSignatures: [],
      lastSyncAt: null,
      lastError: null,
      status: "idle",
      setPairing: (endpoint, token, email = "") =>
        set({
          endpoint: endpoint.replace(/\/+$/, ""),
          token,
          email,
          lastError: null,
          status: "idle",
        }),
      clearPairing: () =>
        set({
          endpoint: "",
          token: "",
          email: "",
          knownSignatures: [],
          lastSyncAt: null,
          lastError: null,
          status: "idle",
        }),
      setWifiOnly: (value) =>
        set({
          wifiOnly: value,
          allowMobileData: value ? false : get().allowMobileData,
        }),
      setAllowMobileData: (value) =>
        set({
          allowMobileData: value,
          wifiOnly: value ? false : get().wifiOnly,
        }),
      setStatus: (status, error = null) =>
        set({ status, lastError: error ?? null }),
      setLastSyncAt: (ts) => set({ lastSyncAt: ts, status: "ok" }),
      addKnownSignatures: (sigs) => {
        const cur = new Set(get().knownSignatures)
        for (const s of sigs) if (s) cur.add(s)
        set({ knownSignatures: [...cur] })
      },
      setKnownSignatures: (sigs) => set({ knownSignatures: [...new Set(sigs)] }),
      ensureDeviceId: () => {
        let id = get().deviceId
        if (!id) {
          id = randomDeviceId()
          set({ deviceId: id })
        }
        return id
      },
      isPaired: () => Boolean(get().endpoint && get().token),
    }),
    {
      name: "spends-server-sync",
      storage: mmkvJSONStorage(storage),
      partialize: (s) => ({
        endpoint: s.endpoint,
        token: s.token,
        deviceId: s.deviceId,
        email: s.email,
        wifiOnly: s.wifiOnly,
        allowMobileData: s.allowMobileData,
        knownSignatures: s.knownSignatures,
        lastSyncAt: s.lastSyncAt,
      }),
    },
  ),
)
