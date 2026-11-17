package orbit.nex.steps.updates

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.content.FileProvider
import orbit.nex.steps.BuildConfig
import orbit.nex.steps.FlavorUtils
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.R
import orbit.nex.steps.WebhookConfig
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import okhttp3.OkHttpClient
import okhttp3.Request
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
    val apk_path: String = "",
    val download_url: String = "",
    val source: String = "server",
)

enum class CheckOutcome { NOT_CONNECTED, UP_TO_DATE, UPDATE_AVAILABLE, ERROR }

data class UpdateCheck(val outcome: CheckOutcome, val info: UpdateInfo? = null)

class AppUpdateChecker(private val context: Context) {

    private val prefs = PreferencesManager(context)
    private val json = Json { ignoreUnknownKeys = true }
    private val client = OkHttpClient.Builder()
        .connectTimeout(15, TimeUnit.SECONDS)
        .readTimeout(60, TimeUnit.SECONDS)
        .build()

    fun connectedConfig(): WebhookConfig? = prefs.getWebhookConfigs()
        .firstOrNull { it.isEnabled && !it.headers["Authorization"].isNullOrBlank() }

    fun baseUrl(config: WebhookConfig): String? {
        val uri = Uri.parse(config.url)
        val scheme = uri.scheme ?: return null
        val authority = uri.authority ?: return null
        return "$scheme://$authority"
    }

    private fun apiUrl(path: String, config: WebhookConfig): String? {
        if (path.startsWith("http")) return path
        return baseUrl(config)?.let { it + path }
    }

    /**
     * Checks the connected server for a newer version. Never needs a hardcoded
     * domain: the base URL comes from the stored WebhookConfig. If the server
     * answered on a different host (redirect / domain change), the redirect is
     * followed and the new base is persisted back into the config.
     */
    suspend fun checkForUpdate(): UpdateCheck = withContext(Dispatchers.IO) {
        if (FlavorUtils.isPlayStore) return@withContext UpdateCheck(CheckOutcome.UP_TO_DATE)
        val candidates = mutableListOf<UpdateInfo>()
        var sawError = false

        connectedConfig()?.let { config ->
            if (!prefs.hasAuthError()) {
                try {
                    val url = apiUrl("/api/steps/version", config)
                    if (url != null) {
                        val request = Request.Builder().url(url)
                            .header("Authorization", config.headers["Authorization"].orEmpty())
                            .header("X-Device-Id", prefs.getDeviceId())
                            .header("Accept", "application/json").get().build()
                        client.newCall(request).execute().use { response ->
                            if (response.isSuccessful) {
                                val info = json.decodeFromString<UpdateInfo>(response.body?.string().orEmpty())
                                    .copy(source = "server")
                                if (info.available) candidates += info.copy(download_url = apiUrl(info.apk_path.ifBlank { "/api/steps/apk" }, config).orEmpty())
                                persistRedirectedBase(config, response.request.url.toString())
                            }
                        }
                    }
                } catch (_: Exception) {
                    sawError = true
                }
            }
        }

        val newest = candidates.maxByOrNull { it.version_code }
        if (newest == null) {
            return@withContext if (sawError) UpdateCheck(CheckOutcome.ERROR) else UpdateCheck(CheckOutcome.NOT_CONNECTED)
        }
        if (newest.version_code <= BuildConfig.VERSION_CODE) {
            return@withContext UpdateCheck(CheckOutcome.UP_TO_DATE, newest)
        }
        UpdateCheck(CheckOutcome.UPDATE_AVAILABLE, newest)
    }

    private fun persistRedirectedBase(config: WebhookConfig, finalUrl: String) {
        if (finalUrl.isEmpty()) return
        val currentBase = baseUrl(config) ?: return
        val finalBase = baseUrl(config.copy(url = finalUrl)) ?: return
        if (finalBase == currentBase) return
        val updated = prefs.getWebhookConfigs().map {
            if (it.url == config.url) it.copy(url = it.url.replace(currentBase, finalBase)) else it
        }
        prefs.setWebhookConfigs(updated)
    }

    /**
     * Downloads the APK into cacheDir/downloads and verifies its SHA-256 against
     * the authenticated response metadata. [onProgress] reports trailing writes.
     */
    suspend fun downloadApk(info: UpdateInfo, onProgress: ((Long) -> Unit)? = null): File? = withContext(Dispatchers.IO) {
        val config = connectedConfig()
        val url = when {
            info.download_url.isNotBlank() -> info.download_url
            else -> {
                val cfg = config ?: return@withContext null
                apiUrl(info.apk_path.ifBlank { "/api/steps/apk" }, cfg) ?: return@withContext null
            }
        }
        val destDir = File(context.cacheDir, "downloads").apply { mkdirs() }
        val dest = File(destDir, "stepmonitor-${info.version_code}.apk")
        if (dest.exists() && verifyHash(dest, info.hash)) return@withContext dest
        dest.delete()
        try {
            val builder = Request.Builder().url(url).header("Accept", "application/json")
            if (config != null) {
                builder.header("Authorization", config.headers["Authorization"].orEmpty())
                builder.header("X-Device-Id", prefs.getDeviceId())
            }
            val request = builder.get().build()
            client.newCall(request).execute().use { response ->
                if (!response.isSuccessful) return@withContext null
                val body = response.body ?: return@withContext null
                body.byteStream().use { input ->
                    dest.outputStream().use { out ->
                        val buffer = ByteArray(64 * 1024)
                        var written = 0L
                        while (true) {
                            val n = input.read(buffer)
                            if (n < 0) break
                            out.write(buffer, 0, n)
                            written += n
                            onProgress?.invoke(written)
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

    fun latestDownloaded(): File? {
        val dir = File(context.cacheDir, "downloads")
        return dir.listFiles { f -> f.name.startsWith("stepmonitor-") && f.name.endsWith(".apk") }
            ?.maxByOrNull { it.lastModified() }
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
                val n = input.read(buffer)
                if (n < 0) break
                digest.update(buffer, 0, n)
            }
        }
        val sb = StringBuilder()
        for (b in digest.digest()) {
            val v = b.toInt() and 0xff
            if (v < 16) sb.append('0')
            sb.append(Integer.toHexString(v))
        }
        return sb.toString().equals(expected, ignoreCase = true)
    }

    /**
     * Automatic flow: throttled background check + download, then a "ready to
     * install" notification. Runs on the shared update scope so it survives
     * screen navigation and process-lifetime changes to a reasonable degree.
     */
    suspend fun runAutoCheck() {
        if (FlavorUtils.isPlayStore) return
        if (!prefs.isAutoUpdateEnabled()) return
        val now = System.currentTimeMillis()
        if (now - prefs.getUpdateCheckedAt() < UPDATE_CHECK_THROTTLE_MS) return
        prefs.setUpdateCheckedAt(now)
        val check = checkForUpdate()
        if (check.outcome != CheckOutcome.UPDATE_AVAILABLE) return
        val info = check.info ?: return
        if (info.version_code == prefs.getIgnoreVersionCode()) return
        prefs.setLastKnownVersionCode(info.version_code)
        val file = downloadApk(info)
        if (file != null) postInstallNotification(context, file, info.version_name)
    }

    companion object {
        private const val UPDATE_CHECK_THROTTLE_MS = 24L * 60 * 60 * 1000
        const val ACTION_INSTALL_UPDATE = "orbit.nex.steps.INSTALL_UPDATE"
        private const val CHANNEL_ID = "app_updates"
        private const val NOTIFICATION_ID = 3001

        fun notifyChannelName(context: Context): String = context.getString(R.string.update_channel_name)
        fun notifyChannelDesc(context: Context): String = context.getString(R.string.update_channel_desc)

        fun postInstallNotification(context: Context, apkFile: File, versionName: String) {
            val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && manager.getNotificationChannel(CHANNEL_ID) == null) {
                manager.createNotificationChannel(
                    NotificationChannel(CHANNEL_ID, notifyChannelName(context), NotificationManager.IMPORTANCE_DEFAULT).apply {
                        description = notifyChannelDesc(context)
                    }
                )
            }
            val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", apkFile)
            val intent = Intent(ACTION_INSTALL_UPDATE)
                .setClass(context, UpdateInstallReceiver::class.java)
                .setData(uri)
                .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
            val pi = PendingIntent.getBroadcast(
                context,
                0,
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            val notification = NotificationCompat.Builder(context, CHANNEL_ID)
                .setSmallIcon(R.mipmap.ic_launcher)
                .setContentTitle(context.getString(R.string.update_downloaded_title))
                .setContentText(context.getString(R.string.update_downloaded_text, versionName))
                .setContentIntent(pi)
                .setAutoCancel(true)
                .build()
            manager.notify(NOTIFICATION_ID, notification)
        }
    }
}

object AppUpdateManager {
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    fun start(context: Context) {
        scope.launch { AppUpdateChecker(context.applicationContext).runAutoCheck() }
    }
}
