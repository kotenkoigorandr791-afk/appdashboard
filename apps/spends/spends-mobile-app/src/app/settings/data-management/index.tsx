import { useCallback, useEffect, useState } from "react"
import { useTranslation } from "react-i18next"
import { Alert, ScrollView } from "react-native"
import { StyleSheet } from "react-native-unistyles"

import {
  SettingsRow,
  SettingsSection,
} from "~/components/settings/settings-list"
import { Button } from "~/components/ui/button"
import { Text } from "~/components/ui/text"
import {
  deleteServerBackup,
  listServerBackups,
  restoreServerBackup,
  saveServerBackup,
  type ServerBackup,
} from "~/services/spends-api"
import { performSpendsSync } from "~/services/spends-sync"
import { Toast } from "~/utils/toast"

const backupName = () =>
  `phone_${new Date().toISOString().replace(/[-:T]/g, "").slice(0, 14)}`

export default function DataManagementScreen() {
  const { t } = useTranslation()
  const [items, setItems] = useState<ServerBackup[]>([])
  const [busy, setBusy] = useState(false)

  const refresh = useCallback(async () => {
    try {
      setItems(await listServerBackups())
    } catch {
      Toast.error({ title: t("screens.settings.dataManagement.importError") })
    }
  }, [t])

  useEffect(() => {
    refresh()
  }, [refresh])

  const run = async (
    job: () => Promise<{ status: string; message?: string }>,
    after?: () => Promise<unknown>,
  ) => {
    setBusy(true)
    try {
      const res = await job()
      if (res.status !== "success") throw new Error(res.message)
      await after?.()
      await refresh()
      Toast.success({ title: res.message || t("common.toast.success") })
    } catch (e) {
      Toast.error({ title: e instanceof Error ? e.message : t("common.toast.error") })
    } finally {
      setBusy(false)
    }
  }

  const confirm = (message: string, onOk: () => void) =>
    Alert.alert(t("common.toast.warning", { defaultValue: "Confirm" }), message, [
      { text: t("common.actions.cancel"), style: "cancel" },
      { text: t("common.actions.confirm"), onPress: onOk },
    ])

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <SettingsSection
        title={t("screens.settings.dataManagement.serverBackups", {
          defaultValue: "Server backups",
        })}
        description={t("screens.settings.dataManagement.serverBackupsDesc", {
          defaultValue: "Same backups as on the web. Shared with the server.",
        })}
      >
        <Button
          disabled={busy}
          onPress={() => run(() => saveServerBackup(backupName()))}
        >
          <Text variant="default">
            {t("screens.settings.dataManagement.createBackup", {
              defaultValue: "Create backup",
            })}
          </Text>
        </Button>
        {items.map((b) => (
          <SettingsRow
            key={b.name}
            label={b.name.replace(/\.csv$/, "")}
            description={new Date(b.mtime * 1000).toLocaleString()}
            onPress={() =>
              confirm(
                t("screens.settings.dataManagement.restoreConfirm", {
                  defaultValue: "Restore this backup on the server?",
                }),
                () =>
                  run(() => restoreServerBackup(b.name), performSpendsSync),
              )
            }
            trailing={
              <Button
                variant="ghost"
                disabled={busy}
                onPress={() =>
                  confirm(
                    t("common.actions.delete", { defaultValue: "Delete" }),
                    () => run(() => deleteServerBackup(b.name)),
                  )
                }
              >
                <Text variant="default">
                  {t("common.actions.delete", { defaultValue: "Delete" })}
                </Text>
              </Button>
            }
          />
        ))}
      </SettingsSection>
    </ScrollView>
  )
}

const styles = StyleSheet.create((theme) => ({
  container: { flex: 1, backgroundColor: theme.colors.surface },
  content: { padding: 20, gap: 12 },
}))
