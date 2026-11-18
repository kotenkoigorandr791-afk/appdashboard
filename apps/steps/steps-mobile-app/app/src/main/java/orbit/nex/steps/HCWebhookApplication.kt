package orbit.nex.steps

import android.app.Application
import androidx.work.BackoffPolicy
import org.osmdroid.config.Configuration
import androidx.work.Constraints
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import java.util.concurrent.TimeUnit

class HCWebhookApplication : Application() {

    private lateinit var preferencesManager: PreferencesManager

    override fun onCreate() {
        super.onCreate()
        preferencesManager = PreferencesManager(this)
        val osmdroid = Configuration.getInstance()
        osmdroid.load(this, getSharedPreferences("osmdroid", MODE_PRIVATE))
        osmdroid.userAgentValue = "$packageName/${BuildConfig.VERSION_NAME} (https://nex.orbit)"
        // Connectivity is observed app-wide so the offline banner and the bar
        // indicator react the moment the network drops.
        NetworkState.start(this)

        // Location work is only resumed when the user previously enabled it.
        if (preferencesManager.isLocationTrackingEnabled() || orbit.nex.steps.location.LocationTrackingManager.pendingCount(this) > 0) {
            orbit.nex.steps.location.LocationUploadWorker.ensurePeriodic(this)
            orbit.nex.steps.location.LocationUploadWorker.enqueue(this)
        }

        // Schedule syncs based on the selected sync mode
        when (preferencesManager.getSyncMode()) {
            SyncMode.INTERVAL -> {
                scheduleSyncWork()
                // Cancel scheduled alarms if they were previously set
                ScheduledSyncManager(this).cancelAllAlarms()
                // (Re)start the Doze-friendly interval alarm chain
                ScheduledSyncManager(this).scheduleNextIntervalSync()
            }
            SyncMode.SCHEDULED -> {
                // Cancel WorkManager periodic sync if it was previously set
                cancelSyncWork()
                // Guaranteed daily alarms (morning + evening)
                ScheduledSyncManager(this).cancelIntervalSync()
                ScheduledSyncManager(this).scheduleAllAlarms()
            }
        }

    }

    fun scheduleSyncWork() {
        // Only schedule if sync mode is INTERVAL
        if (preferencesManager.getSyncMode() != SyncMode.INTERVAL) {
            return
        }

        val syncIntervalMinutes = preferencesManager.getSyncIntervalMinutes()

        // No network constraint: let SyncWorker fail gracefully and retry via
        // backoff when there is no connection, rather than deferring the job
        // indefinitely (which is what NetworkType.CONNECTED causes on Samsung
        // devices with aggressive battery management).
        val builder = PeriodicWorkRequestBuilder<SyncWorker>(
            repeatInterval = syncIntervalMinutes.toLong(),
            repeatIntervalTimeUnit = TimeUnit.MINUTES
        )
        .setBackoffCriteria(
            BackoffPolicy.EXPONENTIAL,
            5,
            TimeUnit.MINUTES
        )
        if (preferencesManager.isWifiOnly()) {
            builder.setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.UNMETERED).build())
        }
        val syncWorkRequest = builder.build()

        WorkManager.getInstance(this).enqueueUniquePeriodicWork(
            SYNC_WORK_NAME,
            ExistingPeriodicWorkPolicy.UPDATE, // Update existing work with new configuration
            syncWorkRequest
        )
    }

    fun cancelSyncWork() {
        WorkManager.getInstance(this).cancelUniqueWork(SYNC_WORK_NAME)
    }

    companion object {
        private const val SYNC_WORK_NAME = "health_data_sync"
    }
}
