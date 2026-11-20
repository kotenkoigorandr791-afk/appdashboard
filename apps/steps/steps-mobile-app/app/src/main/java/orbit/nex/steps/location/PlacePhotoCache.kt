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

object PlacePhotoCache {
    private val memory = ConcurrentHashMap<String, Bitmap>()
    private val client = OkHttpClient()

    fun key(osmType: String, osmId: Long): String = "$osmType:$osmId"

    fun memoryGet(key: String): Bitmap? = memory[key]

    fun diskFile(context: Context, key: String): File =
        File(context.cacheDir, "place_photos/${key.replace(':', '_')}.webp")

    fun resolveUrl(config: WebhookConfig, osmType: String, osmId: Long): String =
        GeoMapRepository.apiBase(config).trimEnd('/') + "/geolocation_place_photo?osm_type=$osmType&osm_id=$osmId"

    suspend fun ensure(
        context: Context,
        prefs: PreferencesManager,
        config: WebhookConfig,
        osmType: String,
        osmId: Long,
    ): Bitmap? = withContext(Dispatchers.IO) {
        val k = key(osmType, osmId)
        memory[k]?.let { return@withContext it }
        val file = diskFile(context, k)
        if (file.exists() && file.length() > 0) {
            BitmapFactory.decodeFile(file.absolutePath)?.also { memory[k] = it }?.let { return@withContext it }
        }
        val url = resolveUrl(config, osmType, osmId)
        val request = Request.Builder().url(url)
            .apply {
                config.headers.forEach { (h, v) -> addHeader(h, v) }
                addHeader("X-Device-Id", prefs.getDeviceId())
                addHeader("Accept", "image/webp,image/*")
            }
            .build()
        runCatching {
            client.newCall(request).execute().use { response ->
                if (!response.isSuccessful) return@use null
                val bytes = response.body?.bytes() ?: return@use null
                file.parentFile?.mkdirs()
                file.writeBytes(bytes)
                BitmapFactory.decodeByteArray(bytes, 0, bytes.size)?.also { memory[k] = it }
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
