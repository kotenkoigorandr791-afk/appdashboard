package com.stepmonitor.app

import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.content.pm.ServiceInfo
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.work.CoroutineWorker
import androidx.work.ForegroundInfo
import androidx.work.WorkerParameters
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.IOException

class SyncWorker(
    appContext: Context,
    workerParams: WorkerParameters
) : CoroutineWorker(appContext, workerParams) {

    private val syncManager = SyncManager(appContext)

    override suspend fun doWork(): Result = withContext(Dispatchers.IO) {
        try {
            val syncResult = syncManager.performSync(syncType = "auto")
            if (syncResult.isSuccess) {
                Result.success()
            } else {
                mapFailure(syncResult.exceptionOrNull())
            }
        } catch (e: CancellationException) {
            throw e
        } catch (e: IOException) {
            mapFailure(e)
        } catch (e: Exception) {
            Result.failure()
        } finally {
            ScheduledSyncManager(applicationContext).scheduleNextIntervalSync()
        }
    }

    private fun mapFailure(error: Throwable?): Result {
        return when (error) {
            is CancellationException -> throw error
            is IOException -> if (WebhookManager.isRetryableException(error)) Result.retry() else Result.failure()
            else -> Result.failure()
        }
    }

    private fun createForegroundInfo(): ForegroundInfo {
        ensureNotificationChannel()
        val notification = NotificationCompat.Builder(applicationContext, CHANNEL_ID)
            .setContentTitle(applicationContext.getString(R.string.sync_service_notification_title))
            .setContentText(applicationContext.getString(R.string.sync_service_notification_text))
            .setSmallIcon(R.mipmap.ic_launcher)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .setCategory(NotificationCompat.CATEGORY_SERVICE)
            .setOngoing(true)
            .build()
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            ForegroundInfo(NOTIFICATION_ID, notification, ServiceInfo.FOREGROUND_SERVICE_TYPE_DATA_SYNC)
        } else {
            ForegroundInfo(NOTIFICATION_ID, notification)
        }
    }

    private fun ensureNotificationChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val manager = applicationContext.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        if (manager.getNotificationChannel(CHANNEL_ID) != null) return
        val channel = NotificationChannel(
            CHANNEL_ID,
            applicationContext.getString(R.string.sync_service_channel_name),
            NotificationManager.IMPORTANCE_LOW
        )
        channel.description = applicationContext.getString(R.string.sync_service_channel_desc)
        manager.createNotificationChannel(channel)
    }

    companion object {
        private const val CHANNEL_ID = SyncForegroundService.CHANNEL_ID
        private const val NOTIFICATION_ID = 2002
    }
}
