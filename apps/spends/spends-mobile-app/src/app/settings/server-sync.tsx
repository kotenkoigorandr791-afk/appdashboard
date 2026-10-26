import * as Linking from "expo-linking"
import { useCallback, useEffect, useState } from "react"
import { useTranslation } from "react-i18next"
import { ScrollView, Switch } from "react-native"
import { StyleSheet } from "react-native-unistyles"

import {
  SettingsSection,
  settingsStyles,
} from "~/components/settings/settings-list"
import { Button } from "~/components/ui/button"
import { Input } from "~/components/ui/input"
import { Text } from "~/components/ui/text"
import { View } from "~/components/ui/view"
import {
  pairFromLink,
  pairManual,
  performSpendsSync,
} from "~/services/spends-sync"
import { useServerSyncStore } from "~/stores/server-sync.store"
import { Toast } from "~/utils/toast"

export default function ServerSyncScreen() {
  const { t } = useTranslation()
  const endpoint = useServerSyncStore((s) => s.endpoint)
  const email = useServerSyncStore((s) => s.email)
  const wifiOnly = useServerSyncStore((s) => s.wifiOnly)
  const allowMobileData = useServerSyncStore((s) => s.allowMobileData)
  const lastSyncAt = useServerSyncStore((s) => s.lastSyncAt)
  const status = useServerSyncStore((s) => s.status)
  const lastError = useServerSyncStore((s) => s.lastError)
  const setWifiOnly = useServerSyncStore((s) => s.setWifiOnly)
  const setAllowMobileData = useServerSyncStore((s) => s.setAllowMobileData)
  const clearPairing = useServerSyncStore((s) => s.clearPairing)
  const paired = Boolean(endpoint && useServerSyncStore.getState().token)

  const [endpointInput, setEndpointInput] = useState(endpoint)
  const [tokenInput, setTokenInput] = useState("")
  const [linkInput, setLinkInput] = useState("")
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    if (endpoint) setEndpointInput(endpoint)
  }, [endpoint])

  const handlePairLink = useCallback(
    async (url: string) => {
      setBusy(true)
      try {
        const { email: pairedEmail } = await pairFromLink(url)
        Toast.success({
          title: t("screens.settings.serverSync.pairSuccess"),
          description: pairedEmail || undefined,
        })
        await performSpendsSync()
      } catch {
        Toast.error({ title: t("screens.settings.serverSync.pairFailed") })
      } finally {
        setBusy(false)
      }
    },
    [t],
  )

  useEffect(() => {
    const sub = Linking.addEventListener("url", ({ url }) => {
      if (url?.startsWith("spends://")) handlePairLink(url).catch(() => {})
    })
    Linking.getInitialURL().then((url) => {
      if (url?.startsWith("spends://")) handlePairLink(url).catch(() => {})
    })
    return () => sub.remove()
  }, [handlePairLink])

  const onManualPair = async () => {
    setBusy(true)
    try {
      await pairManual(endpointInput, tokenInput)
      Toast.success({ title: t("screens.settings.serverSync.pairSuccess") })
      setTokenInput("")
      await performSpendsSync()
    } catch {
      Toast.error({ title: t("screens.settings.serverSync.pairFailed") })
    } finally {
      setBusy(false)
    }
  }

  const onPasteLink = async () => {
    if (!linkInput.trim()) return
    await handlePairLink(linkInput.trim())
    setLinkInput("")
  }

  const onSync = async () => {
    setBusy(true)
    try {
      const r = await performSpendsSync()
      Toast.success({
        title: t("screens.settings.serverSync.syncSuccess", {
          created: r.created,
          skipped: r.skipped,
        }),
      })
    } catch (e) {
      Toast.error({
        title: t("screens.settings.serverSync.statusError"),
        description: e instanceof Error ? e.message : undefined,
      })
    } finally {
      setBusy(false)
    }
  }

  const openWalletSettings = async () => {
    try {
      const { WalletNotificationModule } = await import(
        "~/modules/wallet-notification"
      )
      await WalletNotificationModule.openListenerSettings()
    } catch {
      await Linking.openSettings()
    }
  }

  const statusLabel =
    status === "syncing"
      ? t("screens.settings.serverSync.statusSyncing")
      : status === "error"
        ? `${t("screens.settings.serverSync.statusError")}${lastError ? `: ${lastError}` : ""}`
        : status === "ok"
          ? t("screens.settings.serverSync.statusOk")
          : t("screens.settings.serverSync.statusIdle")

  const lastSyncLabel = lastSyncAt
    ? new Date(lastSyncAt).toLocaleString()
    : t("screens.settings.serverSync.never")

  return (
    <ScrollView
      style={settingsStyles.screen}
      contentContainerStyle={settingsStyles.content}
      contentInsetAdjustmentBehavior="automatic"
      showsVerticalScrollIndicator={false}
    >
      <View style={styles.hero}>
        <Text style={styles.heroTitle}>
          {t("screens.settings.serverSync.title")}
        </Text>
        <Text style={styles.heroSubtitle}>
          {t("screens.settings.serverSync.subtitle")}
        </Text>
      </View>

      <SettingsSection title={t("screens.settings.serverSync.title")}>
        <View style={styles.block}>
          <Text style={styles.statusLine}>
            {paired
              ? t("screens.settings.serverSync.connected", {
                  email: email || "—",
                })
              : t("screens.settings.serverSync.notConnected")}
          </Text>
          {paired ? <Text style={styles.mono}>{endpoint}</Text> : null}
          <Text style={styles.meta}>
            {t("screens.settings.serverSync.lastSync", { time: lastSyncLabel })}
          </Text>
          <Text style={styles.meta}>{statusLabel}</Text>
          {paired ? (
            <View style={styles.actions}>
              <Button onPress={onSync} disabled={busy}>
                <Text>{t("screens.settings.serverSync.syncNow")}</Text>
              </Button>
              <Button variant="outline" onPress={clearPairing} disabled={busy}>
                <Text>{t("screens.settings.serverSync.disconnect")}</Text>
              </Button>
            </View>
          ) : null}
        </View>
      </SettingsSection>

      {!paired ? (
        <>
          <SettingsSection title={t("screens.settings.serverSync.pasteLink")}>
            <View style={styles.block}>
              <Input
                value={linkInput}
                onChangeText={setLinkInput}
                autoCapitalize="none"
                autoCorrect={false}
                placeholder="spends://auth?endpoint=…&token=…"
              />
              <Button onPress={onPasteLink} disabled={busy || !linkInput.trim()}>
                <Text>{t("screens.settings.serverSync.pair")}</Text>
              </Button>
            </View>
          </SettingsSection>

          <SettingsSection title={t("screens.settings.serverSync.manual")}>
            <View style={styles.block}>
              <Text style={styles.fieldLabel}>
                {t("screens.settings.serverSync.endpoint")}
              </Text>
              <Input
                value={endpointInput}
                onChangeText={setEndpointInput}
                autoCapitalize="none"
                autoCorrect={false}
                placeholder="http://10.0.2.2/api/spends"
              />
              <Text style={styles.fieldLabel}>
                {t("screens.settings.serverSync.token")}
              </Text>
              <Input
                value={tokenInput}
                onChangeText={setTokenInput}
                autoCapitalize="none"
                autoCorrect={false}
                secureTextEntry
                placeholder="••••••••"
              />
              <Button
                onPress={onManualPair}
                disabled={busy || !endpointInput.trim() || !tokenInput.trim()}
              >
                <Text>{t("screens.settings.serverSync.pair")}</Text>
              </Button>
            </View>
          </SettingsSection>
        </>
      ) : null}

      <SettingsSection title={t("screens.settings.groups.system")}>
        <View style={styles.row}>
          <View style={styles.rowText}>
            <Text style={styles.rowTitle}>
              {t("screens.settings.serverSync.wifiOnly")}
            </Text>
            <Text style={styles.meta}>
              {t("screens.settings.serverSync.wifiOnlyHint")}
            </Text>
          </View>
          <Switch value={wifiOnly} onValueChange={setWifiOnly} />
        </View>
        <View style={styles.row}>
          <View style={styles.rowText}>
            <Text style={styles.rowTitle}>
              {t("screens.settings.serverSync.allowMobile")}
            </Text>
            <Text style={styles.meta}>
              {t("screens.settings.serverSync.allowMobileHint")}
            </Text>
          </View>
          <Switch value={allowMobileData} onValueChange={setAllowMobileData} />
        </View>
      </SettingsSection>

      <SettingsSection title={t("screens.settings.serverSync.walletListener")}>
        <View style={styles.block}>
          <Text style={styles.meta}>
            {t("screens.settings.serverSync.walletListenerHint")}
          </Text>
          <Button variant="outline" onPress={openWalletSettings}>
            <Text>{t("screens.settings.serverSync.openListenerSettings")}</Text>
          </Button>
        </View>
      </SettingsSection>
    </ScrollView>
  )
}

const styles = StyleSheet.create((theme) => ({
  hero: { gap: 6, marginBottom: 8, paddingHorizontal: 4 },
  heroTitle: {
    fontSize: theme.typography.headlineMedium.fontSize,
    fontWeight: "700",
    color: theme.colors.onSurface,
  },
  heroSubtitle: {
    fontSize: theme.typography.bodyLarge.fontSize,
    color: theme.colors.semantic.semi,
    lineHeight: 22,
  },
  block: { gap: 10, paddingVertical: 8, paddingHorizontal: 4 },
  statusLine: {
    fontSize: theme.typography.titleLarge.fontSize,
    fontWeight: "600",
    color: theme.colors.onSurface,
  },
  fieldLabel: {
    fontSize: 12,
    fontWeight: "700",
    letterSpacing: 0.4,
    textTransform: "uppercase",
    color: theme.colors.semantic.semi,
  },
  mono: {
    fontSize: 12,
    fontFamily: "monospace",
    color: theme.colors.onSurface,
  },
  meta: { fontSize: 13, color: theme.colors.semantic.semi, lineHeight: 18 },
  actions: { gap: 8, marginTop: 4 },
  row: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 12,
    paddingVertical: 12,
    paddingHorizontal: 4,
  },
  rowText: { flex: 1, gap: 2 },
  rowTitle: {
    fontSize: 15,
    fontWeight: "600",
    color: theme.colors.onSurface,
  },
}))
