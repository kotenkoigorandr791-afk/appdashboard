package orbit.nex.finance.updates

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.content.FileProvider
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import okhttp3.OkHttpClient
import okhttp3.Request
import orbit.nex.finance.BuildConfig
import orbit.nex.finance.MainActivity
import orbit.nex.finance.R
import java.io.File
import java.security.MessageDigest
import java.util.concurrent.TimeUnit

@Serializable
data class UpdateInfo(
    val available: Boolean = false,
    val version_name: String = "",
    val version_code: Int = 0,
    val hash: String = "",
    val size_bytes: Long = 0,
    val built_at: Long = 0,
    val apk_path: String = "/api/spends/apk",
)

enum class UpdateOutcome { NOT_CONNECTED, UP_TO_DATE, UPDATE_AVAILABLE, ERROR }

data class UpdateCheck(val outcome: UpdateOutcome, val info: UpdateInfo? = null)

/**
 * Checks the paired server for a newer build of this app, downloads it and hands the
 * APK to the system installer. Mirrors the Steps companion app so both clients update
 * from the same dashboard release files.
 */
class AppUpdateChecker(private val context: Context) {
    private val json = Json { ignoreUnknownKeys = true }
    private val client = OkHttpClient.Builder()
        .connectTimeout(15, TimeUnit.SECONDS)
        .readTimeout(60, TimeUnit.SECONDS)
        .build()

    private fun baseUrl(endpoint: String): String? {
        val uri = Uri.parse(endpoint)
        val scheme = uri.scheme ?: return null
        val authority = uri.authority ?: return null
        return "$scheme://$authority"
    }

    suspend fun checkForUpdate(endpoint: String, token: String): UpdateCheck = withContext(Dispatchers.IO) {
        val base = baseUrl(endpoint) ?: return@withContext UpdateCheck(UpdateOutcome.NOT_CONNECTED)
        if (token.isBlank()) return@withContext UpdateCheck(UpdateOutcome.NOT_CONNECTED)
        try {
            val request = Request.Builder()
                .url("$base/api/spends/version")
                .header("Authorization", "Bearer $token")
                .header("Accept", "application/json")
                .get()
                .build()
            client.newCall(request).execute().use { response ->
                if (!response.isSuccessful) return@withContext UpdateCheck(UpdateOutcome.ERROR)
                val info = json.decodeFromString<UpdateInfo>(response.body?.string().orEmpty())
                if (!info.available || info.version_code <= BuildConfig.VERSION_CODE) {
                    return@withContext UpdateCheck(UpdateOutcome.UP_TO_DATE, info)
                }
                UpdateCheck(UpdateOutcome.UPDATE_AVAILABLE, info)
            }
        } catch (_: Exception) {
            UpdateCheck(UpdateOutcome.ERROR)
        }
    }

    suspend fun downloadApk(endpoint: String, token: String, info: UpdateInfo, onProgress: ((Int) -> Unit)? = null): File? =
        withContext(Dispatchers.IO) {
            val base = baseUrl(endpoint) ?: return@withContext null
            val path = info.apk_path.ifBlank { "/api/spends/apk" }
            val url = if (path.startsWith("http")) path else base + path
            val dir = File(context.cacheDir, "updates").apply { mkdirs() }
            val dest = File(dir, "spends-${info.version_code}.apk")
            if (dest.exists() && verifyHash(dest, info.hash)) return@withContext dest
            dest.delete()
            try {
                val request = Request.Builder()
                    .url(url)
                    .header("Authorization", "Bearer $token")
                    .header("Accept", "application/vnd.android.package-archive")
                    .get()
                    .build()
                client.newCall(request).execute().use { response ->
                    if (!response.isSuccessful) return@withContext null
                    val body = response.body ?: return@withContext null
                    val total = if (info.size_bytes > 0) info.size_bytes else body.contentLength()
                    body.byteStream().use { input ->
                        dest.outputStream().use { out ->
                            val buffer = ByteArray(64 * 1024)
                            var written = 0L
                            while (true) {
                                val read = input.read(buffer)
                                if (read < 0) break
                                out.write(buffer, 0, read)
                                written += read
                                if (total > 0) onProgress?.invoke(((written * 100) / total).toInt().coerceIn(0, 100))
                            }
                        }
                    }
                    if (verifyHash(dest, info.hash)) dest else {
                        dest.delete()
                        null
                    }
                }
            } catch (_: Exception) {
                dest.delete()
                null
            }
        }

    fun install(apkFile: File) {
        val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", apkFile)
        val intent = Intent(Intent.ACTION_INSTALL_PACKAGE)
            .setDataAndType(uri, "application/vnd.android.package-archive")
            .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        runCatching { context.startActivity(intent) }
    }

    private fun verifyHash(file: File, expected: String): Boolean {
        if (expected.isBlank()) return true
        val digest = MessageDigest.getInstance("SHA-256")
        file.inputStream().use { input ->
            val buffer = ByteArray(8192)
            while (true) {
                val read = input.read(buffer)
                if (read < 0) break
                digest.update(buffer, 0, read)
            }
        }
        val hex = digest.digest().joinToString("") { "%02x".format(it) }
        return hex.equals(expected, ignoreCase = true)
    }

    companion object {
        /** Posts a "ready to install" notification for a downloaded APK. */
        fun postInstallNotification(context: Context, apkFile: File, versionName: String) {
            val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager ?: return
            val channelId = "app_updates"
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && manager.getNotificationChannel(channelId) == null) {
                manager.createNotificationChannel(
                    NotificationChannel(channelId, context.getString(R.string.update_ready_title), NotificationManager.IMPORTANCE_DEFAULT).apply {
                        description = context.getString(R.string.update_ready_text, versionName)
                    }
                )
            }
            val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", apkFile)
            val intent = Intent(context, UpdateInstallReceiver::class.java)
                .setData(uri)
                .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
            val pending = PendingIntent.getBroadcast(
                context,
                0,
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
            )
            val notification = NotificationCompat.Builder(context, channelId)
                .setSmallIcon(android.R.drawable.stat_sys_download_done)
                .setContentTitle(context.getString(R.string.update_ready_title))
                .setContentText(context.getString(R.string.update_ready_text, versionName))
                .setContentIntent(pending)
                .setAutoCancel(true)
                .build()
            runCatching { manager.notify(3001, notification) }
        }

        fun openApp(context: Context) {
            val intent = Intent(context, MainActivity::class.java).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            runCatching { context.startActivity(intent) }
        }
    }
}

/** Receives the notification tap and launches the package installer. */
class UpdateInstallReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val uri: Uri? = intent.data
        if (uri == null) {
            AppUpdateChecker.openApp(context)
            return
        }
        val install = Intent(Intent.ACTION_INSTALL_PACKAGE)
            .setDataAndType(uri, "application/vnd.android.package-archive")
            .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        runCatching { context.startActivity(install) }
    }
}
