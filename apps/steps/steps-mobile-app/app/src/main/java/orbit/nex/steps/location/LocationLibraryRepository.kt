package orbit.nex.steps.location

import android.net.Uri
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
import java.time.Duration
import java.time.OffsetDateTime
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.util.Locale

enum class LibraryTab(val endpoint: String) {
    PLACES("places"),
    TRIPS("geolocation_trips?limit=100"),
    VISITS("geolocation_stops?limit=100"),
    GEOFENCES("geofences"),
    DEVICES("devices"),
    QUALITY("geolocation_quality"),
    SEARCH("geolocation_search"),
    SAVED("geolocation_saved_views"),
    ANALYTICS("geolocation_analytics"),
}

enum class LibraryAction {
    DELETE,
    ARCHIVE,
    RENAME,
    CONFIRM,
    REJECT,
    CORRECT,
    PAUSE,
    RESUME,
    REVOKE,
    DISABLE,
    EXCLUDE,
    UNDO,
    PIN_VIEW,
    DELETE_VIEW,
    APPLY_VIEW,
}

data class LibraryEntry(
    val id: String,
    val title: String,
    val subtitle: String = "",
    val meta: String = "",
    val status: String = "",
    val active: Boolean? = null,
    val actions: List<LibraryAction> = emptyList(),
    val query: String = "",
    val isTrip: Boolean = false,
)

data class LibrarySnapshot(
    val entries: List<LibraryEntry> = emptyList(),
    val summary: String = "",
    val enabled: Boolean = true,
    val error: String? = null,
    val offline: Boolean = false,
)

data class VisitCandidate(val id: String, val name: String, val subtitle: String)

data class VisitDetail(
    val id: String,
    val name: String,
    val category: String,
    val tag: String,
    val candidates: List<VisitCandidate>,
    val canBlock: Boolean,
)

/**
 * Reads and mutates the geolocation entities that used to be web-only.
 *
 * The repository deliberately uses the existing `/api/steps` endpoints with
 * the paired device headers. It never caches coordinates or secrets on disk and
 * turns server errors into short, redacted messages.
 */
internal object VisitCache {
    fun write(file: java.io.File?, snapshot: LibrarySnapshot) {
        if (file == null) return
        runCatching {
            val rows = JSONArray()
            snapshot.entries.forEach {
                rows.put(JSONObject().put("id", it.id).put("title", it.title).put("subtitle", it.subtitle).put("meta", it.meta).put("status", it.status))
            }
            file.writeText(rows.toString())
        }
    }

    fun read(file: java.io.File?): LibrarySnapshot? {
        val source = file?.takeIf { it.isFile } ?: return null
        return runCatching {
            val rows = JSONArray(source.readText())
            val entries = (0 until rows.length()).mapNotNull { i ->
                val row = rows.optJSONObject(i) ?: return@mapNotNull null
                LibraryEntry(
                    id = row.optString("id"),
                    title = row.optString("title"),
                    subtitle = row.optString("subtitle"),
                    meta = row.optString("meta"),
                    status = row.optString("status"),
                    active = row.optString("status") in setOf("confirmed", "manual"),
                )
            }
            LibrarySnapshot(entries = entries, offline = true)
        }.getOrNull()
    }
}

class LocationLibraryRepository(
    private val prefs: PreferencesManager,
    private val client: OkHttpClient = OkHttpClient(),
    private val cacheFile: java.io.File? = null,
) {
    suspend fun load(config: WebhookConfig, tab: LibraryTab, query: String = ""): LibrarySnapshot = withContext(Dispatchers.IO) {
        runCatching {
            val endpoint = if (tab == LibraryTab.SEARCH) {
                "geolocation_search?q=" + Uri.encode(query) + "&limit=30"
            } else tab.endpoint
            val root = get(config, endpoint)
            when (tab) {
                LibraryTab.PLACES -> places(root)
                LibraryTab.TRIPS -> trips(root)
                LibraryTab.VISITS -> visits(root).also { VisitCache.write(cacheFile, it) }
                LibraryTab.GEOFENCES -> geofences(root)
                LibraryTab.DEVICES -> devices(root)
                LibraryTab.QUALITY -> quality(root)
                LibraryTab.SEARCH -> search(root)
                LibraryTab.SAVED -> savedViews(root)
                LibraryTab.ANALYTICS -> analytics(root)
            }
        }.getOrElse { failure ->
            (if (tab == LibraryTab.VISITS) VisitCache.read(cacheFile) else null)
                ?: LibrarySnapshot(error = failure.message ?: "network")
        }
    }

    suspend fun visitDetail(config: WebhookConfig, id: String): Result<VisitDetail> = withContext(Dispatchers.IO) {
        runCatching {
            val visit = get(config, "geolocation_stops?id=" + Uri.encode(id)).getJSONArray("visits").getJSONObject(0)
            val rows = visit.optJSONArray("candidates") ?: JSONArray()
            val candidates = (0 until rows.length()).mapNotNull { i ->
                val c = rows.optJSONObject(i) ?: return@mapNotNull null
                VisitCandidate(
                    id = c.optString("id"),
                    name = c.optString("name"),
                    subtitle = listOf(
                        c.optString("category"),
                        if (c.has("distance_m")) "${c.optDouble("distance_m").toInt()} m" else "",
                        "${(c.optDouble("score") * 100).toInt()}%",
                    ).filter { it.isNotBlank() }.joinToString(" · "),
                )
            }
            VisitDetail(
                id = id,
                name = visit.optString("display_name").ifBlank { visit.optString("place_name") },
                category = visit.optString("category"),
                tag = visit.optString("place_tag"),
                candidates = candidates,
                canBlock = !visit.isNull("osm_id"),
            )
        }
    }

    suspend fun selectCandidate(config: WebhookConfig, id: String, candidateId: String): Result<Unit> = withContext(Dispatchers.IO) {
        runCatching { post(config, "geolocation_stops", JSONObject().put("action", "select_candidate").put("id", id).put("candidate_id", candidateId)) }
    }

    suspend fun assignVisit(config: WebhookConfig, id: String, name: String, category: String, tag: String): Result<Unit> = withContext(Dispatchers.IO) {
        runCatching {
            post(config, "geolocation_stops", JSONObject().put("action", "assign").put("id", id).put("name", name).put("category", category).put("tag", tag))
        }
    }

    suspend fun visitAction(config: WebhookConfig, id: String, action: String): Result<Unit> = withContext(Dispatchers.IO) {
        runCatching { post(config, "geolocation_stops", JSONObject().put("action", action).put("id", id)) }
    }

    suspend fun apply(config: WebhookConfig, entry: LibraryEntry, action: LibraryAction): Result<Unit> =
        withContext(Dispatchers.IO) {
            runCatching {
                val (endpoint, body) = when (action) {
                    LibraryAction.DELETE -> "places" to JSONObject()
                        .put("action", "delete")
                        .put("id", entry.id)
                    LibraryAction.RENAME -> error("Use renameTrip")
                    LibraryAction.ARCHIVE -> "geolocation_trips" to JSONObject()
                        .put("action", "archive")
                        .put("id", entry.id)
                    LibraryAction.CONFIRM -> "geolocation_stops" to JSONObject()
                        .put("action", "confirm")
                        .put("id", entry.id)
                    LibraryAction.REJECT -> "geolocation_stops" to JSONObject()
                        .put("action", "reject")
                        .put("id", entry.id)
                    LibraryAction.CORRECT -> error("Use the visit editor")
                    LibraryAction.PAUSE -> "devices" to JSONObject()
                        .put("action", "pause")
                        .put("device_id", entry.id)
                    LibraryAction.RESUME -> "devices" to JSONObject()
                        .put("action", "resume")
                        .put("device_id", entry.id)
                    LibraryAction.REVOKE -> "devices" to JSONObject()
                        .put("action", "revoke")
                        .put("device_id", entry.id)
                    LibraryAction.DISABLE -> "geofences" to JSONObject()
                        .put("action", "delete")
                        .put("id", entry.id)
                    LibraryAction.EXCLUDE -> "geolocation_quality" to JSONObject()
                        .put("action", "correct")
                        .put("scope_type", "points")
                        .put("point_ids", JSONArray().put(entry.id))
                        .put("effects", JSONArray().put("exclude_display").put("exclude_analytics"))
                        .put("reason", "quality_issue")
                        .put("note", "")
                    LibraryAction.UNDO -> "geolocation_quality" to JSONObject()
                        .put("action", "undo")
                        .put("id", entry.id)
                    LibraryAction.PIN_VIEW -> "geolocation_saved_views" to JSONObject()
                        .put("action", "pin")
                        .put("id", entry.id)
                    LibraryAction.DELETE_VIEW -> "geolocation_saved_views" to JSONObject()
                        .put("action", "delete")
                        .put("id", entry.id)
                    LibraryAction.APPLY_VIEW -> "geolocation_saved_views" to JSONObject().put("action", "apply").put("id", entry.id)
                }
                post(config, endpoint, body)
                Unit
            }
        }

    suspend fun renameTrip(config: WebhookConfig, id: String, title: String): Result<Unit> = withContext(Dispatchers.IO) {
        runCatching { post(config, "geolocation_trips", JSONObject().put("action", "rename").put("id", id).put("title", title)); Unit }
    }

    suspend fun tripGeometry(config: WebhookConfig, id: String): Result<Pair<java.time.LocalDate, List<GeoLatLon>>> = withContext(Dispatchers.IO) {
        runCatching {
            val trip = get(config, "geolocation_trips?id=" + Uri.encode(id)).getJSONObject("trip")
            val date = OffsetDateTime.parse(trip.getString("started_at")).atZoneSameInstant(ZoneId.systemDefault()).toLocalDate()
            val coordinates = trip.optJSONObject("geojson")?.optJSONArray("coordinates") ?: JSONArray()
            val path = (0 until coordinates.length()).mapNotNull { i ->
                val point = coordinates.optJSONArray(i) ?: return@mapNotNull null
                val lon = point.optDouble(0, Double.NaN)
                val lat = point.optDouble(1, Double.NaN)
                if (lat in -90.0..90.0 && lon in -180.0..180.0) GeoLatLon(lat, lon) else null
            }
            require(path.isNotEmpty()) { "no_geometry" }
            date to path
        }
    }

    private fun get(config: WebhookConfig, path: String): JSONObject {
        val builder = Request.Builder()
            .url(apiUrl(config, path))
            .addHeader("Accept", "application/json")
            .addHeader("X-Device-Id", prefs.getDeviceId())
        config.headers.forEach { (key, value) -> builder.addHeader(key, value) }
        client.newCall(builder.build()).execute().use { response ->
            if (response.code == 401 || response.code == 403) prefs.markAuthError(response.code)
            val body = response.body?.string().orEmpty()
            val json = runCatching { JSONObject(body) }.getOrElse { JSONObject() }
            if (!response.isSuccessful || !json.optBoolean("ok", false)) {
                error(json.optString("error").ifBlank { "HTTP ${response.code}" })
            }
            return json
        }
    }

    private fun post(config: WebhookConfig, path: String, body: JSONObject) {
        val builder = Request.Builder()
            .url(apiUrl(config, path))
            .addHeader("Accept", "application/json")
            .addHeader("X-Device-Id", prefs.getDeviceId())
            .post(body.toString().toRequestBody("application/json; charset=utf-8".toMediaType()))
        config.headers.forEach { (key, value) -> builder.addHeader(key, value) }
        client.newCall(builder.build()).execute().use { response ->
            if (response.code == 401 || response.code == 403) prefs.markAuthError(response.code)
            val responseBody = response.body?.string().orEmpty()
            val json = runCatching { JSONObject(responseBody) }.getOrElse { JSONObject() }
            if (!response.isSuccessful || !json.optBoolean("ok", false)) {
                error(json.optString("error").ifBlank { "HTTP ${response.code}" })
            }
        }
    }

    private fun apiUrl(config: WebhookConfig, path: String): String =
        GeoMapRepository.apiBase(config).trimEnd('/') + "/" + path.trimStart('/')

    private fun places(root: JSONObject): LibrarySnapshot {
        val entries = mutableListOf<LibraryEntry>()
        val rows = root.optJSONArray("places") ?: JSONArray()
        for (i in 0 until rows.length()) {
            val row = rows.optJSONObject(i) ?: continue
            val active = row.optBoolean("is_active", true)
            entries += LibraryEntry(
                id = row.optString("id"),
                title = row.optString("name").ifBlank { "—" },
                subtitle = listOf(row.optString("tag"), radius(row.optDouble("radius_m", 0.0)))
                    .filter { it.isNotBlank() }
                    .joinToString(" · "),
                meta = formatTimestamp(row.optString("updated_at")),
                active = active,
                actions = if (active) listOf(LibraryAction.DELETE) else emptyList(),
            )
        }
        return LibrarySnapshot(entries = entries)
    }

    private fun trips(root: JSONObject): LibrarySnapshot {
        val entries = mutableListOf<LibraryEntry>()
        val rows = root.optJSONArray("trips") ?: JSONArray()
        for (i in 0 until rows.length()) {
            val row = rows.optJSONObject(i) ?: continue
            val started = row.optString("started_at")
            entries += LibraryEntry(
                id = row.optString("id"),
                title = row.optString("title").ifBlank { formatTimestamp(started) },
                subtitle = listOf(
                    formatTimestamp(started),
                    distance(row.optDouble("distance_m", 0.0)),
                    duration(row.optLong("duration_s", 0L)),
                ).filter { it.isNotBlank() }.joinToString(" · "),
                status = row.optString("state"),
                actions = listOf(LibraryAction.ARCHIVE, LibraryAction.RENAME),
                isTrip = true,
            )
        }
        return LibrarySnapshot(entries = entries)
    }

    private fun visits(root: JSONObject): LibrarySnapshot {
        val entries = mutableListOf<LibraryEntry>()
        val rows = root.optJSONArray("visits") ?: JSONArray()
        for (i in 0 until rows.length()) {
            val row = rows.optJSONObject(i) ?: continue
            val arrived = row.optString("arrived_at")
            val departed = row.optString("departed_at")
            val status = row.optString("detection_status").ifBlank { row.optString("state") }
            val confidence = if (row.isNull("confidence")) "" else "${(row.optDouble("confidence") * 100).toInt()}%"
            entries += LibraryEntry(
                id = row.optString("id"),
                title = row.optString("place_name"),
                subtitle = listOf(formatTimestamp(arrived), formatTimestamp(departed))
                    .filter { it.isNotBlank() }.joinToString(" — ") + row.optLong("duration_s").takeIf { it > 0 }?.let { " · " + duration(it) }.orEmpty(),
                meta = listOf(row.optString("category"), row.optString("place_tag"), confidence, row.optString("address"))
                    .filter { it.isNotBlank() }.joinToString(" · "),
                status = status,
                active = status == "confirmed" || status == "manual",
                actions = listOf(LibraryAction.CORRECT, LibraryAction.CONFIRM, LibraryAction.REJECT),
            )
        }
        return LibrarySnapshot(entries = entries)
    }

    private fun geofences(root: JSONObject): LibrarySnapshot {
        val entries = mutableListOf<LibraryEntry>()
        val rows = root.optJSONArray("geofences") ?: JSONArray()
        for (i in 0 until rows.length()) {
            val row = rows.optJSONObject(i) ?: continue
            val active = row.optBoolean("is_active", true)
            entries += LibraryEntry(
                id = row.optString("id"),
                title = row.optString("name").ifBlank { "—" },
                subtitle = radius(row.optDouble("radius_m", 0.0)),
                meta = formatTimestamp(row.optString("updated_at")),
                active = active,
                actions = if (active) listOf(LibraryAction.DISABLE) else emptyList(),
            )
        }
        return LibrarySnapshot(entries = entries)
    }

    private fun quality(root: JSONObject): LibrarySnapshot {
        val entries = mutableListOf<LibraryEntry>()
        val issues = root.optJSONArray("issues") ?: JSONArray()
        for (i in 0 until issues.length()) {
            val row = issues.optJSONObject(i) ?: continue
            val reasons = row.optJSONArray("reasons")?.let { list ->
                (0 until list.length()).joinToString(", ") { list.optString(it) }
            }.orEmpty()
            entries += LibraryEntry(
                id = row.optString("id"),
                title = formatTimestamp(row.optString("source_at")),
                subtitle = listOf(row.optString("quality_state"), reasons)
                    .filter { it.isNotBlank() }.joinToString(" · "),
                meta = row.optDouble("accuracy_m", 0.0).takeIf { it > 0.0 }?.let { "${it.toInt()} m" }.orEmpty(),
                status = row.optString("quality_severity").ifBlank { row.optString("quality_state") },
                active = false,
                actions = listOf(LibraryAction.EXCLUDE),
            )
        }
        val corrections = root.optJSONArray("corrections") ?: JSONArray()
        for (i in 0 until corrections.length()) {
            val row = corrections.optJSONObject(i) ?: continue
            if (row.optString("state") != "active") continue
            entries += LibraryEntry(
                id = row.optString("id"),
                title = row.optString("scope_type"),
                subtitle = "${row.optInt("point_count", 0)} points",
                meta = formatTimestamp(row.optString("created_at")),
                status = row.optString("state"),
                active = true,
                actions = listOf(LibraryAction.UNDO),
            )
        }
        val counts = root.optJSONObject("counts")
        val summary = listOf("accepted", "flagged", "excluded_by_user")
            .joinToString(" · ") { "$it: ${counts?.optInt(it, 0) ?: 0}" }
        return LibrarySnapshot(entries = entries, summary = summary)
    }

    private fun search(root: JSONObject): LibrarySnapshot {
        val entries = mutableListOf<LibraryEntry>()
        val rows = root.optJSONArray("results") ?: JSONArray()
        for (i in 0 until rows.length()) {
            val row = rows.optJSONObject(i) ?: continue
            entries += LibraryEntry(
                id = row.optString("id"),
                title = row.optString("title").ifBlank { row.optString("type") },
                subtitle = listOf(row.optString("type"), row.optString("summary"))
                    .filter { it.isNotBlank() }.joinToString(" · "),
                meta = formatTimestamp(row.optString("started_at")),
                status = row.optString("status"),
                active = true,
            )
        }
        val range = root.optJSONObject("range")
        return LibrarySnapshot(entries = entries, summary = range?.optString("label").orEmpty())
    }

    private fun savedViews(root: JSONObject): LibrarySnapshot {
        val entries = mutableListOf<LibraryEntry>()
        val rows = root.optJSONArray("views") ?: JSONArray()
        for (i in 0 until rows.length()) {
            val row = rows.optJSONObject(i) ?: continue
            val queryObject = row.optJSONObject("query")
            entries += LibraryEntry(
                id = row.optString("id"),
                title = row.optString("name").ifBlank { "—" },
                subtitle = listOf(row.optString("context"), row.optString("date_period"))
                    .filter { it.isNotBlank() }.joinToString(" · "),
                meta = formatTimestamp(row.optString("updated_at")),
                status = when {
                    row.optBoolean("is_default") -> "default"
                    row.optBoolean("is_pinned") -> "pinned"
                    else -> "private"
                },
                active = true,
                actions = listOf(LibraryAction.APPLY_VIEW, LibraryAction.PIN_VIEW, LibraryAction.DELETE_VIEW),
                query = queryObject?.optString("q").orEmpty(),
            )
        }
        return LibrarySnapshot(entries = entries)
    }

    private fun analytics(root: JSONObject): LibrarySnapshot {
        val metrics = root.optJSONObject("metrics") ?: JSONObject()
        val coverage = root.optJSONObject("coverage") ?: JSONObject()
        val entries = mutableListOf<LibraryEntry>()
        listOf(
            "distance_m" to "Distance",
            "trip_count" to "Trips",
            "active_days" to "Active days",
            "unique_places" to "Places",
            "new_places" to "New places",
            "geofence_events" to "Geofence events",
        ).forEach { (key, label) ->
            entries += LibraryEntry(id = key, title = label, subtitle = metrics.optString(key, "0"), active = true)
        }
        return LibrarySnapshot(
            entries = entries,
            summary = "Coverage ${coverage.optDouble("quality_percent", 0.0)}% · ${coverage.optString("confidence", "low")}",
        )
    }

    private fun devices(root: JSONObject): LibrarySnapshot {
        val entries = mutableListOf<LibraryEntry>()
        val rows = root.optJSONArray("devices") ?: JSONArray()
        for (i in 0 until rows.length()) {
            val row = rows.optJSONObject(i) ?: continue
            val status = row.optString("status")
            val actions = when (status) {
                "active" -> listOf(LibraryAction.PAUSE, LibraryAction.REVOKE)
                "paused" -> listOf(LibraryAction.RESUME, LibraryAction.REVOKE)
                "revoked" -> listOf(LibraryAction.RESUME)
                else -> emptyList()
            }
            entries += LibraryEntry(
                id = row.optString("id"),
                title = row.optString("name").ifBlank { "—" },
                subtitle = listOf(row.optString("platform"), row.optString("app_version"))
                    .filter { it.isNotBlank() }.joinToString(" · "),
                meta = listOf(
                    row.optString("last_seen_at").takeIf { it.isNotBlank() }?.let { formatTimestamp(it) },
                    row.optInt("point_count", 0).takeIf { it > 0 }?.let { "$it" },
                ).filterNotNull().joinToString(" · "),
                status = status,
                active = status == "active",
                actions = actions,
            )
        }
        return LibrarySnapshot(entries = entries)
    }

    private fun radius(meters: Double): String =
        if (meters <= 0.0) "" else if (meters < 1000) "${meters.toInt()} m" else String.format(Locale.getDefault(), "%.1f km", meters / 1000.0)

    private fun distance(meters: Double): String =
        if (meters <= 0.0) "" else if (meters < 1000) "${meters.toInt()} m" else String.format(Locale.getDefault(), "%.1f km", meters / 1000.0)

    private fun duration(seconds: Long): String {
        if (seconds <= 0L) return ""
        val value = Duration.ofSeconds(seconds)
        val hours = value.toHours()
        val minutes = value.toMinutesPart()
        return if (hours > 0) "${hours}h ${minutes}m" else "${minutes}m"
    }

    private fun formatTimestamp(value: String): String {
        if (value.isBlank()) return ""
        return runCatching {
            OffsetDateTime.parse(value)
                .atZoneSameInstant(ZoneId.systemDefault())
                .format(DateTimeFormatter.ofPattern("d MMM, HH:mm", Locale.getDefault()))
        }.getOrElse { value.take(16).replace('T', ' ') }
    }

}
