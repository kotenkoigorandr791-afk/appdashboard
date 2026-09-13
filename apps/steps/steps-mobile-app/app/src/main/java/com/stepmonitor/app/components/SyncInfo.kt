package com.stepmonitor.app.components

import com.stepmonitor.app.PreferencesManager
import com.stepmonitor.app.SyncMode
import java.util.Calendar

data class NextSyncInfo(
    val nextSyncMs: Long,
    val periodMs: Long,
    val label: String
)

/**
 * Computes the next sync instant for the current sync mode, shared by the
 * Home screen card and the top-bar countdown so both always agree.
 */
fun computeNextSyncInfo(prefs: PreferencesManager, now: Long): NextSyncInfo? {
    return when (prefs.getSyncMode()) {
        SyncMode.SCHEDULED -> {
            val syncs = prefs.getScheduledSyncs().filter { it.enabled }
            if (syncs.isEmpty()) return null
            var earliest = Long.MAX_VALUE
            var earliestLabel = ""
            for (s in syncs) {
                val cal = Calendar.getInstance().apply {
                    set(Calendar.HOUR_OF_DAY, s.hour)
                    set(Calendar.MINUTE, s.minute)
                    set(Calendar.SECOND, 0)
                    set(Calendar.MILLISECOND, 0)
                    if (timeInMillis <= now) add(Calendar.DAY_OF_YEAR, 1)
                }
                if (cal.timeInMillis < earliest) {
                    earliest = cal.timeInMillis
                    earliestLabel = s.getDisplayTime()
                }
            }
            NextSyncInfo(earliest, 24L * 60 * 60 * 1000, earliestLabel)
        }
        SyncMode.INTERVAL -> {
            val intervalMs = prefs.getSyncIntervalMinutes() * 60_000L
            val lastSync = prefs.getEffectiveLastSyncAnchor() ?: now
            NextSyncInfo(lastSync + intervalMs, intervalMs, "")
        }
    }
}

fun formatSyncCountdown(remainingMs: Long): String {
    val hours = remainingMs / 3600_000L
    val minutes = (remainingMs % 3600_000L) / 60_000L
    val seconds = (remainingMs % 60_000L) / 1000L
    return "%02d:%02d:%02d".format(hours, minutes, seconds)
}