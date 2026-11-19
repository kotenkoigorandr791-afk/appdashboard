package orbit.nex.steps.location

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.WebhookConfig
import org.json.JSONArray
import org.json.JSONObject

data class ManagedPoint(
    val id: String,
    val sourceAt: String,
    val qualityState: String,
    val accuracyM: Double?,
    val source: String,
    val lat: Double,
    val lon: Double,
)

data class PointPage(
    val points: List<ManagedPoint> = emptyList(),
    val total: Int = 0,
    val error: String? = null,
)

data class QualityCorrection(
    val id: String,
    val scopeType: String,
    val pointCount: Int,
    val effects: List<String>,
    val createdAt: String,
    val state: String,
    val revokedAt: String?,
)

data class MatchingState(
    val mode: String = "conservative",
    val matched: Int = 0,
    val abstained: Int = 0,
    val stale: Int = 0,
    val error: String? = null,
)

/**
 * Owner-only point-management calls. The Android app uses its paired token
 * endpoint (`/api/steps` endpoints) with the existing device headers, never the legacy
 * `/geolocation?action=points` browser route. Coordinates stay in memory for
 * map rendering and are never written to preferences, logs or notifications.
 */
class PointManagementRepository(
    private val prefs: PreferencesManager,
    private val client: OkHttpClient = OkHttpClient(),
) {
    suspend fun loadPage(
        config: WebhookConfig,
        page: Int,
        from: String?,
        to: String?,
        state: String?,
        bbox: String?,
    ): PointPage = withContext(Dispatchers.IO) {
        runCatching {
            val query = buildList {
                add("limit=100")
                add("offset=${page * 100}")
                if (!from.isNullOrBlank()) add("from=$from")
                if (!to.isNullOrBlank()) add("to=$to")
                if (!state.isNullOrBlank()) add("state=$state")
                if (!bbox.isNullOrBlank()) add("bbox=$bbox")
            }.joinToString("&")
            val root = get(config, "geolocation_points?$query")
            val rows = root.optJSONArray("points") ?: JSONArray()
            val points = (0 until rows.length()).mapNotNull { index ->
                val row = rows.optJSONObject(index) ?: return@mapNotNull null
                val lat = row.optDouble("latitude", Double.NaN)
                val lon = row.optDouble("longitude", Double.NaN)
                if (lat.isNaN() || lon.isNaN()) return@mapNotNull null
                ManagedPoint(
                    id = row.optString("id"),
                    sourceAt = row.optString("source_at"),
                    qualityState = row.optString("quality_state", "accepted"),
                    accuracyM = row.optDouble("accuracy_m").takeUnless { it.isNaN() || it <= 0.0 },
                    source = row.optString("source"),
                    lat = lat,
                    lon = lon,
                )
            }
            PointPage(points, root.optInt("total", points.size))
        }.getOrElse { PointPage(error = it.message ?: "network") }
    }

    suspend fun deleteIds(config: WebhookConfig, ids: Set<String>): Result<Unit> =
        post(config, "geolocation_points", JSONObject().put("action", "delete").put("ids", JSONArray(ids.toList())))

    suspend fun deleteBbox(config: WebhookConfig, bbox: String): Result<Unit> =
        post(config, "geolocation_points", JSONObject().put("action", "delete").put("bbox", bbox))

    suspend fun exclude(config: WebhookConfig, ids: Set<String>): Result<String?> = withContext(Dispatchers.IO) {
        runCatching {
            val body = JSONObject()
                .put("action", "correct")
                .put("scope_type", "points")
                .put("point_ids", JSONArray(ids.toList()))
                .put("effects", JSONArray().put("exclude_display").put("exclude_analytics"))
                .put("reason", "quality_issue")
                .put("note", "")
            val root = postJson(config, "geolocation_quality", body)
            val id = root.optString("correction_id")
                .ifBlank { root.optJSONObject("correction")?.optString("id").orEmpty() }
            id.takeIf { it.isNotBlank() }
        }
    }

    suspend fun undoCorrection(config: WebhookConfig, id: String): Result<Unit> =
        post(config, "geolocation_quality", JSONObject().put("action", "undo").put("id", id))

    suspend fun corrections(config: WebhookConfig): List<QualityCorrection> = withContext(Dispatchers.IO) {
        runCatching {
            val root = get(config, "geolocation_quality")
            val rows = root.optJSONArray("corrections") ?: JSONArray()
            (0 until rows.length()).mapNotNull { index ->
                val row = rows.optJSONObject(index) ?: return@mapNotNull null
                val effects = mutableListOf<String>()
                row.optJSONArray("effects")?.let { list -> for (i in 0 until list.length()) effects += list.optString(i) }
                QualityCorrection(
                    id = row.optString("id"),
                    scopeType = row.optString("scope_type"),
                    pointCount = row.optInt("point_count", 0),
                    effects = effects,
                    createdAt = row.optString("created_at"),
                    state = row.optString("state", "active"),
                    revokedAt = row.optString("revoked_at").takeIf { it.isNotBlank() },
                )
            }
        }.getOrDefault(emptyList())
    }

    suspend fun matching(config: WebhookConfig): MatchingState = withContext(Dispatchers.IO) {
        runCatching {
            val root = get(config, "geolocation_matching")
            val summary = root.optJSONObject("summary") ?: JSONObject()
            MatchingState(
                mode = root.optString("mode", "conservative"),
                matched = summary.optInt("matched", 0),
                abstained = summary.optInt("abstained", 0),
                stale = summary.optInt("stale", 0),
            )
        }.getOrElse { MatchingState(error = it.message ?: "network") }
    }

    suspend fun setMatchingMode(config: WebhookConfig, mode: String): Result<Unit> =
        post(config, "geolocation_matching", JSONObject().put("action", "settings").put("mode", mode))

    suspend fun runMatching(config: WebhookConfig): Result<Unit> =
        post(config, "geolocation_matching", JSONObject().put("action", "run"))

    private fun get(config: WebhookConfig, path: String): JSONObject =
        request(config, path, null).let { response ->
            val body = response.first
            val code = response.second
            val root = parseJson(body)
            if (code !in 200..299 || !root.optBoolean("ok", false)) {
                error(root.optString("error").ifBlank { "HTTP $code" })
            }
            root
        }

    private fun parseJson(text: String): JSONObject {
        val start = text.indexOf('{')
        val end = text.lastIndexOf('}')
        val candidate = if (start >= 0 && end > start) text.substring(start, end + 1) else text
        return runCatching { JSONObject(candidate) }.getOrElse { JSONObject() }
    }

    private fun post(config: WebhookConfig, path: String, body: JSONObject): Result<Unit> =
        runCatching { postJson(config, path, body); Unit }

    private fun postJson(config: WebhookConfig, path: String, body: JSONObject): JSONObject {
        val (text, code) = request(config, path, body.toString())
        val root = parseJson(text)
        if (code !in 200..299 || !root.optBoolean("ok", false)) {
            error(root.optString("error").ifBlank { "HTTP $code" })
        }
        return root
    }

    private fun request(config: WebhookConfig, path: String, body: String?): Pair<String, Int> {
        val builder = Request.Builder()
            .url(GeoMapRepository.apiBase(config).trimEnd('/') + "/" + path)
            .addHeader("Accept", "application/json")
            .addHeader("X-Device-Id", prefs.getDeviceId())
            .apply { config.headers.forEach { (key, value) -> addHeader(key, value) } }
        if (body != null) builder.post(body.toRequestBody("application/json; charset=utf-8".toMediaType()))
        return client.newCall(builder.build()).execute().use { response ->
            if (response.code == 401 || response.code == 403) prefs.markAuthError(response.code)
            response.body?.string().orEmpty() to response.code
        }
    }

}
