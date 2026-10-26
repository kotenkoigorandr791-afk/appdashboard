import { useRouter } from "expo-router"
import { useTranslation } from "react-i18next"
import { ScrollView } from "react-native"
import { StyleSheet } from "react-native-unistyles"

import { ServerPairingPanel } from "~/components/settings/server-pairing-panel"
import { settingsStyles } from "~/components/settings/settings-list"
import { IconSvg } from "~/components/icons"
import { Text } from "~/components/ui/text"
import { View } from "~/components/ui/view"
import { useOnboardingStore } from "~/stores/onboarding.store"

export default function OnboardingStartScreen() {
  const { t } = useTranslation()
  const router = useRouter()
  const setCompleted = useOnboardingStore((s) => s.setCompleted)

  return (
    <ScrollView
      style={settingsStyles.screen}
      contentContainerStyle={styles.content}
      contentInsetAdjustmentBehavior="automatic"
      showsVerticalScrollIndicator={false}
    >
      <View style={styles.banner}>
        <View style={styles.iconBox}>
          <IconSvg name="cloud" size={48} />
        </View>
        <Text style={styles.bannerTitle}>{t("onboarding.pair.title")}</Text>
        <Text style={styles.bannerHint}>
          {t("screens.settings.serverSync.requireServer")}
        </Text>
      </View>
      <ServerPairingPanel
        showWallet={false}
        allowDisconnect={false}
        onPaired={() => {
          setCompleted()
          router.replace("/")
        }}
      />
    </ScrollView>
  )
}

const styles = StyleSheet.create((theme) => ({
  content: { paddingTop: 12, paddingBottom: 48 },
  banner: {
    paddingHorizontal: 20,
    paddingBottom: 16,
    gap: 8,
  },
  iconBox: {
    width: 72,
    height: 72,
    borderRadius: theme.radius * 2,
    backgroundColor: theme.colors.secondary,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 4,
  },
  bannerTitle: {
    fontSize: theme.typography.headlineMedium.fontSize,
    fontWeight: "700",
    color: theme.colors.onSurface,
  },
  bannerHint: {
    fontSize: theme.typography.bodyLarge.fontSize,
    color: theme.colors.semantic.semi,
    lineHeight: 22,
  },
}))
