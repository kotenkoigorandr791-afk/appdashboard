import { CameraView, useCameraPermissions } from "expo-camera"
import { useRouter } from "expo-router"
import { useRef } from "react"
import { useTranslation } from "react-i18next"
import { StyleSheet } from "react-native-unistyles"

import { Button } from "~/components/ui/button"
import { Text } from "~/components/ui/text"
import { View } from "~/components/ui/view"
import { pairFromLink, performSpendsSync } from "~/services/spends-sync"
import { Toast } from "~/utils/toast"

export default function ScanQrScreen() {
  const { t } = useTranslation()
  const router = useRouter()
  const [permission, requestPermission] = useCameraPermissions()
  const handled = useRef(false)

  const onScan = async ({ data }: { data: string }) => {
    if (handled.current) return
    handled.current = true
    try {
      await pairFromLink(data)
      await performSpendsSync()
      router.replace("/")
    } catch (e) {
      handled.current = false
      Toast.error({ title: e instanceof Error ? e.message : "pair_failed" })
    }
  }

  if (!permission?.granted) {
    return (
      <View style={styles.center}>
        <Text variant="default">
          {t("screens.settings.serverSync.scanPermission", {
            defaultValue: "Camera access is needed to scan the QR code",
          })}
        </Text>
        <Button onPress={requestPermission}>
          <Text variant="default">
            {t("screens.settings.serverSync.allowCamera", {
              defaultValue: "Allow camera",
            })}
          </Text>
        </Button>
      </View>
    )
  }

  return (
    <CameraView
      style={styles.camera}
      facing="back"
      barcodeScannerSettings={{ barcodeTypes: ["qr"] }}
      onBarcodeScanned={onScan}
    />
  )
}

const styles = StyleSheet.create((theme) => ({
  camera: { flex: 1 },
  center: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    gap: 16,
    padding: 24,
    backgroundColor: theme.colors.surface,
  },
}))
