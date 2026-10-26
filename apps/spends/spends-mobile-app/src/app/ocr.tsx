import * as ImagePicker from "expo-image-picker"
import { useRouter } from "expo-router"
import { useEffect, useRef, useState } from "react"
import { useTranslation } from "react-i18next"
import { ScrollView } from "react-native"
import { StyleSheet } from "react-native-unistyles"

import { Button } from "~/components/ui/button"
import { Input } from "~/components/ui/input"
import { Text } from "~/components/ui/text"
import { View } from "~/components/ui/view"
import {
  finalizeOcrJob,
  getOcrJob,
  type OcrJob,
  resolveOcrJob,
  startOcrJob,
} from "~/services/spends-api"
import { performSpendsSync } from "~/services/spends-sync"
import { Toast } from "~/utils/toast"

const FINAL_STATES = ["done", "error", "canceled"]

export default function OcrScreen() {
  const { t } = useTranslation()
  const router = useRouter()
  const [jobId, setJobId] = useState("")
  const [job, setJob] = useState<OcrJob | null>(null)
  const [busy, setBusy] = useState(false)
  const [names, setNames] = useState<Record<string, string>>({})
  const timer = useRef<ReturnType<typeof setInterval> | null>(null)

  const stop = () => {
    if (timer.current) clearInterval(timer.current)
    timer.current = null
  }

  useEffect(() => stop, [])

  const poll = (id: string) => {
    stop()
    timer.current = setInterval(async () => {
      try {
        const next = await getOcrJob(id)
        setJob(next)
        if (FINAL_STATES.includes(next.state)) stop()
      } catch {
        stop()
      }
    }, 1500)
  }

  const pickAndUpload = async () => {
    const picked = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ["images"],
      allowsMultipleSelection: true,
      quality: 0.9,
    })
    if (picked.canceled) return
    setBusy(true)
    try {
      const res = await startOcrJob(
        picked.assets.map((a, i) => ({
          uri: a.uri,
          name: a.fileName || `shot_${i}.jpg`,
          type: a.mimeType || "image/jpeg",
        })),
      )
      if (res.status !== "ok" || !res.job_id) throw new Error(res.message)
      setJobId(res.job_id)
      setJob({ state: "queued" })
      poll(res.job_id)
    } catch (e) {
      Toast.error({ title: e instanceof Error ? e.message : "ocr_failed" })
    } finally {
      setBusy(false)
    }
  }

  const finish = async (skipAll: boolean) => {
    if (!job) return
    setBusy(true)
    try {
      const unresolved = job.unresolved || []
      const skip = unresolved
        .map((u) => u.merchant_raw)
        .filter((raw) => skipAll || !names[raw]?.trim())
      if (unresolved.length) await resolveOcrJob(jobId, names, skip)
      await finalizeOcrJob(jobId)
      await performSpendsSync()
      router.back()
    } catch (e) {
      Toast.error({ title: e instanceof Error ? e.message : "ocr_failed" })
    } finally {
      setBusy(false)
    }
  }

  const done = job && FINAL_STATES.includes(job.state)
  const label = (key: string, defaultValue: string) =>
    t(`screens.ocr.${key}`, { defaultValue })

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text variant="muted">
        {label("hint", "Pick screenshots. Recognition happens on the server.")}
      </Text>
      {!job && (
        <Button disabled={busy} onPress={pickAndUpload}>
          <Text variant="default">{label("pick", "Choose screenshots")}</Text>
        </Button>
      )}
      {job && !done && (
        <Text variant="default">
          {`${label("processing", "Recognizing")} ${job.progress?.percent ?? 0}%`}
        </Text>
      )}
      {job?.state === "error" && (
        <Text variant="default">{job.message || "error"}</Text>
      )}
      {job?.state === "done" && (
        <>
          <Text variant="default">
            {`${label("added", "Added")}: ${job.added_count ?? 0}, ${label("duplicates", "duplicates")}: ${job.duplicate_count ?? 0}`}
          </Text>
          {(job.unresolved || []).map((u) => (
            <View key={u.merchant_raw} style={styles.row}>
              <Text variant="default">{`${u.merchant_raw} · ${u.amount} ${u.currency || ""}`}</Text>
              <Input
                value={names[u.merchant_raw] || ""}
                onChangeText={(v) =>
                  setNames((s) => ({ ...s, [u.merchant_raw]: v }))
                }
                placeholder={label("storePlaceholder", "Store name")}
              />
            </View>
          ))}
        </>
      )}
      {done && (
        <Button disabled={busy} onPress={() => finish(false)}>
          <Text variant="default">{label("finish", "Save and sync")}</Text>
        </Button>
      )}
    </ScrollView>
  )
}

const styles = StyleSheet.create((theme) => ({
  container: { flex: 1, backgroundColor: theme.colors.surface },
  content: { padding: 20, gap: 14 },
  row: { gap: 6 },
}))
