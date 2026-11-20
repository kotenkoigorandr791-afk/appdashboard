package orbit.nex.steps.location

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.coroutines.sync.withLock
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
    private val context: android.content.Context,
    private val client: OkHttpClient = OkHttpClient(),
) {
    private fun localPoints(from: String?, to: String?, state: String?, bbox: String?): List<ManagedPoint> {
        if (!state.isNullOrBlank() && state != "pending") return emptyList()
        val bounds = bbox?.split(',')?.mapNotNull { it.trim().toDoubleOrNull() }?.takeIf { it.size == 4 }
        fun instant(value: String?, endOfDay: Boolean = false): java.time.Instant? = value?.let {
            runCatching { java.time.Instant.parse(it) }.getOrNull() ?: runCatching {
                val date = java.time.LocalDate.parse(it)
                (if (endOfDay) date.plusDays(1) else date).atStartOfDay(java.time.ZoneId.systemDefault()).toInstant().let { time -> if (endOfDay) time.minusMillis(1) else time }
            }.getOrNull()
        }
        val start = instant(from)
        val end = instant(to, true)
        return LocationEventStore(context).pendingEvents().mapNotNull { event ->
            val payload = runCatching { JSONObject(event.payload) }.getOrNull() ?: return@mapNotNull null
            val lat = payload.optDouble("latitude", Double.NaN)
            val lon = payload.optDouble("longitude", Double.NaN)
            val at = payload.optString("source_at")
            val time = instant(at) ?: return@mapNotNull null
            if (!lat.isFinite() || !lon.isFinite() || (start != null && time < start) || (end != null && time > end)) return@mapNotNull null
            if (bounds != null && (lon !in minOf(bounds[0], bounds[2])..maxOf(bounds[0], bounds[2]) || lat !in minOf(bounds[1], bounds[3])..maxOf(bounds[1], bounds[3]))) return@mapNotNull null
            ManagedPoint("local:${event.eventId}", at, "pending", payload.optDouble("accuracy_m").takeIf { it.isFinite() && it > 0 }, "mobile", lat, lon)
        }
    }

    suspend fun loadPage(config: WebhookConfig, page: Int, from: String?, to: String?, state: String?, bbox: String?): PointPage = withContext(Dispatchers.IO) {
        val local = localPoints(from, to, state, bbox)
        val offset = page * 100
        val localPage = local.drop(offset).take(100)
        runCatching {
            val query = buildList {
                add("limit=${(100 - localPage.size).coerceAtLeast(1)}")
                add("offset=${(offset - local.size).coerceAtLeast(0)}")
                if (!from.isNullOrBlank()) add("from=$from")
                if (!to.isNullOrBlank()) add("to=$to")
                if (!state.isNullOrBlank()) add("state=$state")
                if (!bbox.isNullOrBlank()) add("bbox=$bbox")
            }.joinToString("&")
            val root = get(config, "geolocation_points?$query")
            val rows = root.optJSONArray("points") ?: JSONArray()
            val points = (0 until if (localPage.size == 100) 0 else rows.length()).mapNotNull { index ->
                val row = rows.optJSONObject(index) ?: return@mapNotNull null
                val lat = row.optDouble("latitude", Double.NaN)
                val lon = row.optDouble("longitude", Double.NaN)
                if (!lat.isFinite() || !lon.isFinite()) return@mapNotNull null
                ManagedPoint(row.optString("id"), row.optString("source_at"), row.optString("quality_state", "accepted"), row.optDouble("accuracy_m").takeIf { it.isFinite() && it > 0 }, row.optString("source"), lat, lon)
            }
            PointPage(localPage + points, root.optInt("total", points.size) + local.size)
        }.getOrElse { PointPage(localPage, local.size, it.message ?: "network") }
    }

    suspend fun deleteIds(config: WebhookConfig, ids: Set<String>): Result<Int> = LocationMutationLock.mutex.withLock {
        val local = ids.filter { it.startsWith("local:") }.map { it.removePrefix("local:") }
        val remote = ids.filterNot { it.startsWith("local:") }
        val pending = withContext(Dispatchers.IO) { LocationEventStore(context).pendingEvents().associateBy { it.eventId } }
        val mightHaveUploaded = local.any { pending[it] == null || pending[it]?.state == LocationEventStore.STATE_UPLOADING }
        if (remote.isEmpty() && !mightHaveUploaded) withContext(Dispatchers.IO) {
            runCatching { LocationEventStore(context).removeEvents(local); GeoMapChanges.invalidate(context); local.size }
        } else postDeleted(config, JSONObject().put("action", "delete").put("ids", JSONArray(remote)), local)
    }

    /**
     * One request for the whole drawn area, instead of paging every id.
     *
     * [bbox] may be null when the user only picked a day: the server then deletes
     * every point of that day range. Returns the number of rows the server really
     * deleted, so the UI never reports a silent no-op as success.
     */
    suspend fun deleteBbox(
        config: WebhookConfig,
        bbox: String?,
        from: String? = null,
        to: String? = null,
    ): Result<Int> = LocationMutationLock.mutex.withLock { postDeleted(
        config,
        JSONObject()
            .put("action", "delete")
            .apply {
                if (!bbox.isNullOrBlank()) put("bbox", bbox)
                if (!from.isNullOrBlank()) put("from", from)
                if (!to.isNullOrBlank()) put("to", to)
            },
        withContext(Dispatchers.IO) { localPoints(from, to, null, bbox).map { it.id.removePrefix("local:") } },
    ) }

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

    private suspend fun get(config: WebhookConfig, path: String): JSONObject =
        withContext(Dispatchers.IO) {
            request(config, path, null).let { response ->
                val body = response.first
                val code = response.second
                val root = parseJson(body)
                if (code !in 200..299 || !root.optBoolean("ok", false)) {
                    error(root.optString("error").ifBlank { "HTTP $code" })
                }
                root
            }
        }

    private fun parseJson(text: String): JSONObject {
        val start = text.indexOf('{')
        val end = text.lastIndexOf('}')
        val candidate = if (start >= 0 && end > start) text.substring(start, end + 1) else text
        return runCatching { JSONObject(candidate) }.getOrElse { JSONObject() }
    }

    // Blocking OkHttp calls must never run on the caller's (main) thread: Android
    // throws NetworkOnMainThreadException with a null message, which used to make
    // "delete" a silent no-op. Every request is moved to Dispatchers.IO here, so
    // all callers are safe by construction.
    private suspend fun post(config: WebhookConfig, path: String, body: JSONObject): Result<Unit> =
        withContext(Dispatchers.IO) {
            runCatching { postJsonRequest(config, path, body); Unit }
                .recoverCatching { error(it.message ?: it.javaClass.simpleName) }
        }

    /** Delete response carries `deleted`; propagate it instead of assuming success. */
    private suspend fun postDeleted(config: WebhookConfig, body: JSONObject, localIds: List<String> = emptyList()): Result<Int> =
        withContext(Dispatchers.IO) {
            runCatching {
                body.put("local_device_id", prefs.getLocationDeviceId())
                if (localIds.isNotEmpty()) body.put("local_client_event_ids", JSONArray(localIds))
                val response = postJsonRequest(config, "geolocation_points", body)
                val ids = response.optJSONArray("local_event_ids") ?: JSONArray()
                LocationEventStore(context).removeEvents(((0 until ids.length()).map { ids.optString(it) }.filter { it.isNotBlank() } + localIds).distinct())
                GeoMapChanges.invalidate(context)
                response.optInt("deleted", 0) + localIds.count { id -> (0 until ids.length()).none { ids.optString(it) == id } }
            }
                .recoverCatching { error(it.message ?: it.javaClass.simpleName) }
        }

    private suspend fun postJson(config: WebhookConfig, path: String, body: JSONObject): JSONObject =
        withContext(Dispatchers.IO) { postJsonRequest(config, path, body) }

    private fun postJsonRequest(config: WebhookConfig, path: String, body: JSONObject): JSONObject {
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
