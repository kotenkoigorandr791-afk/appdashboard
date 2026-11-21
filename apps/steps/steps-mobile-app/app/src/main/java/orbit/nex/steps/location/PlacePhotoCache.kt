package orbit.nex.steps.location

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Canvas
import android.graphics.Paint
import android.graphics.Path
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.WebhookConfig
import java.io.File
import java.util.concurrent.ConcurrentHashMap
import java.util.concurrent.TimeUnit

object PlacePhotoCache {
    private val memory = ConcurrentHashMap<String, Bitmap>()
    private val client = OkHttpClient.Builder()
        .connectTimeout(20, TimeUnit.SECONDS)
        .readTimeout(45, TimeUnit.SECONDS)
        .followRedirects(true)
        .followSslRedirects(true)
        .build()

    fun key(osmType: String, osmId: Long): String = "$osmType:$osmId"

    fun memoryGet(key: String): Bitmap? = memory[key]

    fun diskFile(context: Context, key: String): File =
        File(context.cacheDir, "place_photos/${key.replace(':', '_').replace('/', '_')}.bin")

    fun resolveUrl(config: WebhookConfig, osmType: String, osmId: Long): String =
        GeoMapRepository.apiBase(config).trimEnd('/') + "/geolocation_place_photo?osm_type=$osmType&osm_id=$osmId"

    /** Prefer the authenticated mobile API; rewrite session_gateway photo URLs. */
    fun resolveFeatureUrl(config: WebhookConfig, osmType: String?, osmId: Long?, photoUrl: String?): String? {
        if (!osmType.isNullOrBlank() && osmId != null && osmId > 0L) {
            return resolveUrl(config, osmType, osmId)
        }
        val raw = photoUrl?.trim().orEmpty()
        if (raw.isEmpty()) return null
        if (raw.startsWith("http://", true) || raw.startsWith("https://", true)) return raw
        val origin = android.net.Uri.parse(GeoMapRepository.apiBase(config)).let { uri ->
            if (uri.scheme != null && uri.authority != null) "${uri.scheme}://${uri.authority}" else return null
        }
        return if (raw.startsWith("/")) origin + raw else "$origin/$raw"
    }

    suspend fun ensure(
        context: Context,
        prefs: PreferencesManager,
        config: WebhookConfig,
        osmType: String,
        osmId: Long,
    ): Bitmap? = withContext(Dispatchers.IO) {
        fetch(context, prefs, config, key(osmType, osmId), resolveUrl(config, osmType, osmId))
    }

    suspend fun ensureFeature(
        context: Context,
        prefs: PreferencesManager,
        config: WebhookConfig,
        osmType: String?,
        osmId: Long?,
        photoUrl: String?,
    ): Bitmap? = withContext(Dispatchers.IO) {
        val cacheKey = when {
            !osmType.isNullOrBlank() && osmId != null && osmId > 0L -> key(osmType, osmId)
            !photoUrl.isNullOrBlank() -> "url:${photoUrl.hashCode()}"
            else -> return@withContext null
        }
        val url = resolveFeatureUrl(config, osmType, osmId, photoUrl) ?: return@withContext null
        fetch(context, prefs, config, cacheKey, url)
    }

    fun immichKey(assetId: String): String = "immich:$assetId"

    fun resolveImmichUrl(config: WebhookConfig, assetId: String): String =
        GeoMapRepository.apiBase(config).trimEnd('/') + "/immich_thumbnail?id=" +
            java.net.URLEncoder.encode(assetId, Charsets.UTF_8.name()) + "&size=thumbnail"

    suspend fun ensureImmich(
        context: Context,
        prefs: PreferencesManager,
        config: WebhookConfig,
        assetId: String,
    ): Bitmap? = withContext(Dispatchers.IO) {
        val id = assetId.trim()
        if (id.isEmpty()) return@withContext null
        fetch(context, prefs, config, immichKey(id), resolveImmichUrl(config, id))
    }

    private fun isImageMagic(bytes: ByteArray): Boolean {
        if (bytes.size < 3) return false
        if (bytes[0] == 0xFF.toByte() && bytes[1] == 0xD8.toByte()) return true
        if (bytes[0] == 0x89.toByte() && bytes[1] == 0x50.toByte() && bytes[2] == 0x4E.toByte()) return true
        if (bytes.size >= 12 &&
            bytes[0] == 0x52.toByte() && bytes[1] == 0x49.toByte() && bytes[2] == 0x46.toByte() &&
            bytes[8] == 0x57.toByte() && bytes[9] == 0x45.toByte() && bytes[10] == 0x42.toByte()
        ) return true
        if (bytes[0] == 0x47.toByte() && bytes[1] == 0x49.toByte() && bytes[2] == 0x46.toByte()) return true
        return false
    }

    private fun fetch(
        context: Context,
        prefs: PreferencesManager,
        config: WebhookConfig,
        k: String,
        url: String,
    ): Bitmap? {
        memory[k]?.let { return it }
        val file = diskFile(context, k)
        if (file.exists() && file.length() > 32) {
            val diskBytes = runCatching { file.readBytes() }.getOrNull()
            if (diskBytes != null && isImageMagic(diskBytes)) {
                BitmapFactory.decodeByteArray(diskBytes, 0, diskBytes.size)?.also { memory[k] = it }?.let { return it }
            }
            runCatching { file.delete() }
        }
        val request = Request.Builder().url(url)
            .apply {
                // Auth only — never inherit Accept: application/json from pairing headers.
                config.headers.forEach { (h, v) ->
                    if (!h.equals("Accept", ignoreCase = true) && !h.equals("Content-Type", ignoreCase = true)) {
                        header(h, v)
                    }
                }
                header("X-Device-Id", prefs.getDeviceId())
                header("Accept", "image/jpeg,image/webp,image/png,image/*,*/*;q=0.1")
            }
            .get()
            .build()
        return runCatching {
            client.newCall(request).execute().use { response ->
                val bytes = response.body?.bytes() ?: return@use null
                if (!response.isSuccessful) return@use null
                // Trust magic bytes over Content-Type: some proxies rewrite the header.
                if (!isImageMagic(bytes)) return@use null
                val opts = BitmapFactory.Options().apply { inPreferredConfig = Bitmap.Config.ARGB_8888 }
                val bitmap = BitmapFactory.decodeByteArray(bytes, 0, bytes.size, opts) ?: return@use null
                file.parentFile?.mkdirs()
                file.writeBytes(bytes)
                memory[k] = bitmap
                bitmap
            }
        }.getOrNull()
    }

    fun circularIcon(bitmap: Bitmap, sizePx: Int, borderColor: Int, borderPx: Float): Bitmap {
        val out = Bitmap.createBitmap(sizePx, sizePx, Bitmap.Config.ARGB_8888)
        val canvas = Canvas(out)
        val paint = Paint(Paint.ANTI_ALIAS_FLAG)
        val path = Path().apply { addCircle(sizePx / 2f, sizePx / 2f, sizePx / 2f - borderPx, Path.Direction.CW) }
        canvas.save()
        canvas.clipPath(path)
        val scaled = Bitmap.createScaledBitmap(bitmap, sizePx, sizePx, true)
        canvas.drawBitmap(scaled, 0f, 0f, paint)
        canvas.restore()
        paint.style = Paint.Style.STROKE
        paint.strokeWidth = borderPx
        paint.color = borderColor
        canvas.drawCircle(sizePx / 2f, sizePx / 2f, sizePx / 2f - borderPx / 2f, paint)
        return out
    }
}
