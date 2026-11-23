package orbit.nex.finance.data

import android.content.Context
import androidx.work.BackoffPolicy
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import kotlinx.coroutines.flow.first
import orbit.nex.finance.SpendsApplication
import java.util.concurrent.TimeUnit

/**
 * Background ledger sync.
 *
 * The activity used to poll the server every minute (every three minutes while
 * online) for as long as the process lived, including while the app was in the
 * background. WorkManager moves that to a single batched job with a network
 * constraint, so the OS can defer it in Doze and app-standby and the radio is
 * only woken once per interval instead of every 60 seconds.
 */
class SyncWorker(context: Context, params: WorkerParameters) : CoroutineWorker(context, params) {
    override suspend fun doWork(): Result {
        val app = applicationContext as? SpendsApplication ?: return Result.success()
        val store = app.store
        val session = store.session.first()
        if (session.token.isBlank() || session.endpoint.isBlank()) return Result.success()
        val flushed = runCatching { OutboxSync.flush(store, app.api) }.getOrDefault(false)
        if (!flushed) return Result.retry()
        return runCatching { OutboxSync.pull(store, app.api, store.snapshot.first()) }
            .fold(onSuccess = { Result.success() }, onFailure = { Result.retry() })
    }

    companion object {
        private const val UNIQUE_NAME = "spends-ledger-sync"

        fun schedule(context: Context) {
            val request = PeriodicWorkRequestBuilder<SyncWorker>(30, TimeUnit.MINUTES)
                .setConstraints(
                    Constraints.Builder()
                        .setRequiredNetworkType(NetworkType.CONNECTED)
                        .setRequiresBatteryNotLow(true)
                        .build(),
                )
                .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 30, TimeUnit.SECONDS)
                .build()
            WorkManager.getInstance(context)
                .enqueueUniquePeriodicWork(UNIQUE_NAME, ExistingPeriodicWorkPolicy.KEEP, request)
        }
    }
}
