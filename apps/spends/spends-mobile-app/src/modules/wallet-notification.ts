import { NativeEventEmitter, NativeModules, Platform } from "react-native"

type WalletEvent = {
  store: string
  amount: number
  raw?: string
  packageName?: string
  key?: string
}

type WalletNativeModule = {
  openListenerSettings: () => Promise<void>
  isListenerEnabled: () => Promise<boolean>
}

const Native: WalletNativeModule | undefined =
  Platform.OS === "android"
    ? (NativeModules.SpendsWalletNotification as WalletNativeModule | undefined)
    : undefined

function getEmitter(): NativeEventEmitter | null {
  if (Platform.OS !== "android" || !NativeModules.SpendsWalletNotification) {
    return null
  }
  const mod = NativeModules.SpendsWalletNotification as {
    addListener?: (eventName: string) => void
    removeListeners?: (count: number) => void
  }
  if (typeof mod.addListener !== "function") {
    mod.addListener = () => {}
  }
  if (typeof mod.removeListeners !== "function") {
    mod.removeListeners = () => {}
  }
  return new NativeEventEmitter(NativeModules.SpendsWalletNotification)
}

export const WalletNotificationModule = {
  async openListenerSettings(): Promise<void> {
    if (Native?.openListenerSettings) {
      await Native.openListenerSettings()
      return
    }
    throw new Error("wallet_module_unavailable")
  },
  async isListenerEnabled(): Promise<boolean> {
    if (Native?.isListenerEnabled) return Native.isListenerEnabled()
    return false
  },
  subscribe(handler: (event: WalletEvent) => void): () => void {
    const emitter = getEmitter()
    if (!emitter) return () => {}
    const sub = emitter.addListener("spends.wallet.notification", handler)
    return () => sub.remove()
  },
}
