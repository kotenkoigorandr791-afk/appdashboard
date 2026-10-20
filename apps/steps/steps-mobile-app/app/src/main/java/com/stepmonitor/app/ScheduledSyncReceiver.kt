package com.stepmonitor.app

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.util.Log
import androidx.work.ExistingWorkPolicy
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.WorkManager
import com.stepmonitor.app.updates.AppUpdateManager

/**
 * BroadcastReceiver that handles scheduled sync alarms and device boot.
 *
 * For ACTION_SCHEDULED_SYNC we immediately delegate to [SyncForegroundService]
 * rather than doing heavy I/O inside goAsync(). Android 14+ kills goAsync()
 * coroutines after ~10 seconds, which is not enough time for Health Connect
 * reads + HTTP webhook delivery. The foreground service has no such limit.
 *
 * Alarm rescheduling for the next day is handled inside [SyncForegroundService]
 * once the sync completes, so we don't need to do it here.
 */
class ScheduledSyncReceiver : BroadcastReceiver() {

    companion object {
        private const val TAG = "ScheduledSyncReceiver"
    }

    override fun onReceive(context: Context, intent: Intent) {
        Log.d(TAG, "Received intent: ${intent.action}")

        when (intent.action) {
            ScheduledSyncManager.ACTION_SCHEDULED_SYNC -> {
                val scheduleId = intent.getStringExtra(ScheduledSyncManager.EXTRA_SCHEDULE_ID)
                Log.d(TAG, "Delegating scheduled sync to WorkManager (scheduleId=$scheduleId)")
                rescheduleNext(context, scheduleId)
                WorkManager.getInstance(context).enqueueUniqueWork("steps-sync-once", ExistingWorkPolicy.KEEP, OneTimeWorkRequestBuilder<SyncWorker>().build())
            }

            Intent.ACTION_BOOT_COMPLETED,
            Intent.ACTION_MY_PACKAGE_REPLACED -> {
                Log.d(TAG, "Rescheduling after boot/update")
                val prefsManager = PreferencesManager(context)
                when (prefsManager.getSyncMode()) {
                    SyncMode.SCHEDULED -> {
                        ScheduledSyncManager(context).scheduleAllAlarms()
                    }
                    SyncMode.INTERVAL -> {
                        // WorkManager survives reboots on its own, but re-enqueue
                        // to be safe and pick up any interval changes.
                        (context.applicationContext as? HCWebhookApplication)?.scheduleSyncWork()
                        // Rebuild the interval alarm chain lost on reboot.
                        ScheduledSyncManager(context).scheduleNextIntervalSync()
                    }
                }
                if (intent.action == Intent.ACTION_MY_PACKAGE_REPLACED) {
                    prefsManager.setWhatsNewPending(true)
                }
                AppUpdateManager.start(context)
            }
        }
    }

    private fun rescheduleNext(context: Context, scheduleId: String?) {
        val prefsManager = PreferencesManager(context)
        val scheduler = ScheduledSyncManager(context)
        when (prefsManager.getSyncMode()) {
            SyncMode.INTERVAL -> scheduler.scheduleNextIntervalSync()
            SyncMode.SCHEDULED -> {
                if (scheduleId == null) return
                val schedule = prefsManager.getScheduledSyncs().find { it.id == scheduleId }
                if (schedule != null && schedule.enabled) {
                    scheduler.scheduleAlarm(schedule)
                }
            }
        }
    }
}
