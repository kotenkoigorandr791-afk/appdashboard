package orbit.nex.steps.location

import android.content.Context
import androidx.work.BackoffPolicy
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import orbit.nex.steps.PreferencesManager
import java.util.concurrent.TimeUnit

/**
 * Drains the location outbox whenever the phone has connectivity again.
 *
 * WorkManager persists this job across process death and reboot, which is what
 * keeps measurements captured in a dead zone from being lost. Network
 * availability is a WorkManager constraint rather than a receiver so the
 * scheduler, not this app, decides when to wake up.
 */
class LocationUploadWorker(
    appContext: Context,
    workerParams: WorkerParameters,
) : CoroutineWorker(appContext, workerParams) {

    override suspend fun doWork(): Result = withContext(Dispatchers.IO) {
        val prefs = PreferencesManager(applicationContext)
        // Tracking may have been switched off while points were still queued; they
        // must reach the server anyway, so only an auth failure blocks the drain.
        if (prefs.hasAuthError()) return@withContext Result.success()

        val config = prefs.getWebhookConfigs().firstOrNull { it.isEnabled }
            ?: return@withContext Result.success()

        try {
            val uploader = LocationUploader(applicationContext)
            var result: Result? = null
            while (result == null) {
                val report = uploader.uploadPending(config, prefs)
                when {
                    report.error != null && report.retryable -> result = Result.retry()
                    report.error != null -> result = Result.failure()
                    report.pending == 0 -> {
                        if (!prefs.isLocationTrackingEnabled()) cancelPeriodic(applicationContext)
                        result = Result.success()
                    }
                    report.accepted + report.duplicate + report.rejected == 0 -> result = Result.retry()
                }
            }
            result
        } catch (e: CancellationException) {
            throw e
        } catch (e: Exception) {
            Result.retry()
        }
    }

    companion object {
        private const val UNIQUE_WORK = "location_upload"
        private const val PERIODIC_WORK = "location_upload_recovery"
        private const val PERIOD_MINUTES = 15L

        /** Immediate attempt; coalesced while one is already queued or running. */
        fun enqueue(context: Context) {
            val request = OneTimeWorkRequestBuilder<LocationUploadWorker>()
                .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build())
                .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 1, TimeUnit.MINUTES)
                .build()
            WorkManager.getInstance(context.applicationContext)
                .enqueueUniqueWork(UNIQUE_WORK, ExistingWorkPolicy.KEEP, request)
        }

        /** Safety net for measurements taken while nothing could reach the server. */
        fun ensurePeriodic(context: Context) {
            val request = PeriodicWorkRequestBuilder<LocationUploadWorker>(PERIOD_MINUTES, TimeUnit.MINUTES)
                .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build())
                .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 5, TimeUnit.MINUTES)
                .build()
            WorkManager.getInstance(context.applicationContext)
                .enqueueUniquePeriodicWork(PERIODIC_WORK, ExistingPeriodicWorkPolicy.UPDATE, request)
        }

        fun cancelAll(context: Context) {
            WorkManager.getInstance(context.applicationContext).cancelUniqueWork(UNIQUE_WORK)
            cancelPeriodic(context)
        }

        fun cancelPeriodic(context: Context) {
            WorkManager.getInstance(context.applicationContext).cancelUniqueWork(PERIODIC_WORK)
        }
    }
}
