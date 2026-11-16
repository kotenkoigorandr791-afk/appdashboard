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
    val source: String = "server",
    val download_url: String = "",
    val release_notes: String = "",
)

/** Release notes of the newest published build, shown on the updates screen. */
data class ReleaseNotes(
    val version_name: String = "",
    val body: String = "",
    val html_url: String = "",
    val published_at: String = "",
)

enum class UpdateOutcome { NOT_CONNECTED, UP_TO_DATE, UPDATE_AVAILABLE, ERROR }

data class UpdateCheck(
    val outcome: UpdateOutcome,
    val info: UpdateInfo? = null,
    val releaseNotes: ReleaseNotes? = null,
)

/**
 * Checks the paired server for a newer build of this app, downloads it and hands the
 * APK to the system installer. Mirrors the Steps companion app so both clients update
 * from the same dashboard release files.
 */
class AppUpdateChecker(private val context: Context) {
    private val deviceId by lazy { orbit.nex.finance.data.SessionStore(context).deviceId() }
    private val json = Json { ignoreUnknownKeys = true }
    private val client = OkHttpClient.Builder()
        .connectTimeout(15, TimeUnit.SECONDS)
        .readTimeout(60, TimeUnit.SECONDS)
        .build()

    suspend fun checkForUpdate(endpoint: String, token: String): UpdateCheck = withContext(Dispatchers.IO) {
        val serverResult = checkContainer(endpoint, token)
        when {
            serverResult != null -> UpdateCheck(UpdateOutcome.UPDATE_AVAILABLE, serverResult)
            token.isNotBlank() -> UpdateCheck(UpdateOutcome.UP_TO_DATE)
            token.isBlank() -> UpdateCheck(UpdateOutcome.NOT_CONNECTED)
            else -> UpdateCheck(UpdateOutcome.ERROR)
        }
    }

    private fun checkContainer(endpoint: String, token: String): UpdateInfo? {
        if (token.isBlank()) return null
        val base = endpoint.trim().trimEnd('/').substringBefore("/api/")
        if (base.isBlank()) return null
        return runCatching {
            val request = Request.Builder()
                .url("$base/api/spends/version")
                .header("Authorization", "Bearer $token")
                .header("X-Device-Id", deviceId)
                .header("Accept", "application/json")
                .get()
                .build()
            client.newCall(request).execute().use { response ->
                if (!response.isSuccessful) return@use null
                val info = json.decodeFromString<UpdateInfo>(response.body?.string().orEmpty())
                    .copy(source = "server")
                    .takeIf { it.available && it.version_code > BuildConfig.VERSION_CODE }
                info?.copy(download_url = "$base${info.apk_path.ifBlank { "/api/spends/apk" }}")
            }
        }.getOrNull()
    }

    suspend fun downloadApk(token: String, info: UpdateInfo, onProgress: ((Int) -> Unit)? = null): File? =
        withContext(Dispatchers.IO) {
            if (token.isBlank()) return@withContext null
            val url = info.download_url
            if (url.isBlank()) return@withContext null
            val dir = File(context.cacheDir, "updates").apply { mkdirs() }
            val safeVersion = info.version_name.replace(Regex("[^A-Za-z0-9._-]"), "_")
            val dest = File(dir, "spends-${info.source}-$safeVersion.apk")
            if (dest.exists() && verifyHash(dest, info.hash)) return@withContext dest
            dest.delete()
            try {
                val requestBuilder = Request.Builder()
                    .url(url)
                    .header("Accept", "application/vnd.android.package-archive")
                    .get()
                requestBuilder.header("Authorization", "Bearer $token")
                    .header("X-Device-Id", deviceId)
                val request = requestBuilder.build()
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
                    if (verifyHash(dest, info.hash) && isCompatibleApk(dest)) dest else {
                        dest.delete()
                        null
                    }
                }
            } catch (_: Exception) {
                dest.delete()
                null
            }
        }

    suspend fun downloadApk(
        endpoint: String,
        token: String,
        info: UpdateInfo,
        onProgress: ((Int) -> Unit)? = null,
    ): File? = downloadApk(token, info, onProgress)

    private fun isCompatibleApk(file: File): Boolean {
        val archive = context.packageManager.getPackageArchiveInfo(file.absolutePath, 0) ?: return false
        val archiveVersionCode = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            archive.longVersionCode
        } else {
            @Suppress("DEPRECATION")
            archive.versionCode.toLong()
        }
        return archive.packageName == BuildConfig.APPLICATION_ID && archiveVersionCode > BuildConfig.VERSION_CODE
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
