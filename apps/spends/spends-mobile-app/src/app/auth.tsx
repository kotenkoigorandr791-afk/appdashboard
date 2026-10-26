import * as Linking from "expo-linking"
import { useLocalSearchParams, useRouter } from "expo-router"
import { useEffect, useRef, useState } from "react"
import { useTranslation } from "react-i18next"
import { ActivityIndicator } from "react-native"
import { StyleSheet } from "react-native-unistyles"

import { Text } from "~/components/ui/text"
import { View } from "~/components/ui/view"
import { parsePairingLink } from "~/services/spends-api"
import { pairFromLink, pairManual, performSpendsSync } from "~/services/spends-sync"
import { useOnboardingStore } from "~/stores/onboarding.store"
import { logger } from "~/utils/logger"

function first(v: string | string[] | undefined): string {
  if (Array.isArray(v)) return v[0] || ""
  return v || ""
}

export default function AuthDeepLinkScreen() {
  const { t } = useTranslation()
  const router = useRouter()
  const params = useLocalSearchParams<{
    endpoint?: string | string[]
    token?: string | string[]
  }>()
  const setCompleted = useOnboardingStore((s) => s.setCompleted)
  const [error, setError] = useState<string | null>(null)
  const ran = useRef(false)

  useEffect(() => {
    if (ran.current) return
    ran.current = true
    ;(async () => {
      try {
        let endpoint = first(params.endpoint)
        let token = first(params.token)
        if (!endpoint || !token) {
          const initial = await Linking.getInitialURL()
          logger.info("spends auth route", {
            endpoint,
            hasToken: Boolean(token),
            initial,
          })
          if (initial) {
            const parsed = parsePairingLink(initial)
            if (parsed) {
              endpoint = parsed.endpoint
              token = parsed.token
            } else {
              await pairFromLink(initial)
              await performSpendsSync()
              setCompleted()
              router.replace("/")
              return
            }
          }
        }
        if (!endpoint || !token) throw new Error("missing_params")
        await pairManual(endpoint, token)
        await performSpendsSync()
        setCompleted()
        router.replace("/")
      } catch (e) {
        const msg = e instanceof Error ? e.message : String(e)
        logger.error("spends auth route failed", { error: msg })
        setError(msg)
        setTimeout(() => {
          router.replace("/onboarding/start")
        }, 2000)
      }
    })().catch(() => {})
  }, [params.endpoint, params.token, router, setCompleted])

  return (
    <View style={styles.wrap}>
      <ActivityIndicator size="large" />
      <Text style={styles.title}>
        {error
          ? t("screens.settings.serverSync.pairFailed")
          : t("screens.settings.serverSync.statusSyncing")}
      </Text>
      {error ? <Text style={styles.err}>{error}</Text> : null}
    </View>
  )
}

const styles = StyleSheet.create((theme) => ({
  wrap: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    gap: 12,
    padding: 24,
    backgroundColor: theme.colors.surface,
  },
  title: {
    fontSize: theme.typography.titleLarge.fontSize,
    fontWeight: "600",
    color: theme.colors.onSurface,
    textAlign: "center",
  },
  err: {
    fontSize: 13,
    color: theme.colors.error,
    textAlign: "center",
  },
}))
