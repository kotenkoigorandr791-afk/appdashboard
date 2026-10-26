import { useRouter } from "expo-router"
import { useTranslation } from "react-i18next"
import { StyleSheet } from "react-native-unistyles"

import { IconSvg } from "~/components/icons"
import { Button } from "~/components/ui/button"
import { Text } from "~/components/ui/text"
import { View } from "~/components/ui/view"
import { useOnboardingStore } from "~/stores/onboarding.store"
import { useServerSyncStore } from "~/stores/server-sync.store"

export default function OnboardingStartScreen() {
  const { t } = useTranslation()
  const router = useRouter()
  const setCompleted = useOnboardingStore((s) => s.setCompleted)
  const paired = useServerSyncStore((s) => Boolean(s.endpoint && s.token))

  const finish = (toPair: boolean) => {
    setCompleted()
    if (toPair || !paired) {
      router.replace("/settings/server-sync")
      return
    }
    router.replace("/")
  }

  return (
    <View style={styles.container}>
      <View style={styles.content}>
        <View style={styles.iconBox}>
          <IconSvg name="cloud" size={64} />
        </View>
        <Text style={styles.title}>{t("onboarding.pair.title")}</Text>
        <Text style={styles.description}>{t("onboarding.pair.description")}</Text>
        <Button onPress={() => finish(true)} style={styles.btn}>
          <Text>{t("screens.settings.serverSync.pair")}</Text>
        </Button>
        <Button variant="outline" onPress={() => finish(false)} style={styles.btn}>
          <Text>{t("onboarding.pair.skip")}</Text>
        </Button>
      </View>
    </View>
  )
}

const styles = StyleSheet.create((theme) => ({
  container: { flex: 1, backgroundColor: theme.colors.surface },
  content: {
    flex: 1,
    paddingHorizontal: 24,
    paddingTop: 48,
    gap: 14,
  },
  iconBox: {
    width: 96,
    height: 96,
    borderRadius: theme.radius * 2,
    backgroundColor: theme.colors.secondary,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 8,
  },
  title: {
    fontSize: theme.typography.headlineMedium.fontSize,
    fontWeight: "700",
    color: theme.colors.onSurface,
  },
  description: {
    fontSize: theme.typography.bodyLarge.fontSize,
    color: theme.colors.semantic.semi,
    lineHeight: 22,
    marginBottom: 8,
  },
  btn: { marginTop: 4 },
}))
