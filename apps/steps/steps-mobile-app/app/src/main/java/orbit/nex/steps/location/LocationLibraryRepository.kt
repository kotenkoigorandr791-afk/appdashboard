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
    ACHIEVEMENTS("geolocation_achievements"),
    GEOFENCES("geofences"),
    DEVICES("devices"),
    QUALITY("geolocation_quality"),
}

enum class LibraryAction {
    DELETE,
    ARCHIVE,
    CONFIRM,
    REJECT,
    PAUSE,
    RESUME,
    REVOKE,
    DISABLE,
    EXCLUDE,
    UNDO,
}

data class LibraryEntry(
    val id: String,
    val title: String,
    val subtitle: String = "",
    val meta: String = "",
    val status: String = "",
    val active: Boolean? = null,
    val actions: List<LibraryAction> = emptyList(),
)

data class LibrarySnapshot(
    val entries: List<LibraryEntry> = emptyList(),
    val summary: String = "",
    val enabled: Boolean = true,
    val error: String? = null,
)

/**
 * Reads and mutates the geolocation entities that used to be web-only.
 *
 * The repository deliberately uses the existing `/api/steps` endpoints with
 * the paired device headers. It never caches coordinates or secrets on disk and
 * turns server errors into short, redacted messages.
 */
class LocationLibraryRepository(
    private val prefs: PreferencesManager,
    private val client: OkHttpClient = OkHttpClient(),
) {
    suspend fun load(config: WebhookConfig, tab: LibraryTab): LibrarySnapshot = withContext(Dispatchers.IO) {
        runCatching {
            val root = get(config, tab.endpoint)
            when (tab) {
                LibraryTab.PLACES -> places(root)
                LibraryTab.TRIPS -> trips(root)
                LibraryTab.VISITS -> visits(root)
                LibraryTab.ACHIEVEMENTS -> achievements(root)
                LibraryTab.GEOFENCES -> geofences(root)
                LibraryTab.DEVICES -> devices(root)
                LibraryTab.QUALITY -> quality(root)
            }
        }.getOrElse { failure ->
            LibrarySnapshot(error = failure.message ?: "network")
        }
    }

    suspend fun apply(config: WebhookConfig, entry: LibraryEntry, action: LibraryAction): Result<Unit> =
        withContext(Dispatchers.IO) {
            runCatching {
                val (endpoint, body) = when (action) {
                    LibraryAction.DELETE -> "places" to JSONObject()
                        .put("action", "delete")
                        .put("id", entry.id)
                    LibraryAction.ARCHIVE -> "geolocation_trips" to JSONObject()
                        .put("action", "archive")
                        .put("id", entry.id)
                    LibraryAction.CONFIRM -> "geolocation_stops" to JSONObject()
                        .put("action", "confirm")
                        .put("id", entry.id)
                    LibraryAction.REJECT -> "geolocation_stops" to JSONObject()
                        .put("action", "reject")
                        .put("id", entry.id)
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
                }
                post(config, endpoint, body)
                Unit
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
                actions = listOf(LibraryAction.ARCHIVE),
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
            entries += LibraryEntry(
                id = row.optString("id"),
                title = row.optString("place_name").ifBlank { "—" },
                subtitle = listOf(formatTimestamp(arrived), formatTimestamp(departed))
                    .filter { it.isNotBlank() }.joinToString(" — "),
                meta = listOf(row.optString("place_tag"), row.optString("state"))
                    .filter { it.isNotBlank() }.joinToString(" · "),
                status = row.optString("state"),
                actions = listOf(LibraryAction.CONFIRM, LibraryAction.REJECT),
            )
        }
        return LibrarySnapshot(entries = entries)
    }

    private fun achievements(root: JSONObject): LibrarySnapshot {
        val entries = mutableListOf<LibraryEntry>()
        val rows = root.optJSONArray("achievements") ?: JSONArray()
        val enabled = root.optBoolean("enabled", true)
        for (i in 0 until rows.length()) {
            val row = rows.optJSONObject(i) ?: continue
            val progress = row.optDouble("progress", 0.0)
            val target = row.optDouble("target", 0.0)
            val earned = row.optBoolean("earned", false)
            entries += LibraryEntry(
                id = row.optString("id"),
                title = humanize(row.optString("title_key")),
                subtitle = buildString {
                    append(formatAmount(progress))
                    if (target > 0) append(" / ").append(formatAmount(target))
                    val unit = row.optString("unit")
                    if (unit.isNotBlank()) append(' ').append(unit)
                },
                meta = row.optString("family").replaceFirstChar { it.uppercase() },
                status = if (earned) "100%" else row.optString("percent") + "%",
                active = earned,
            )
        }
        val earned = root.optInt("earned_count", 0)
        val total = root.optInt("total_count", entries.size)
        return LibrarySnapshot(
            entries = entries,
            enabled = enabled,
            summary = if (enabled) "$earned / $total" else "",
        )
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

    private fun formatAmount(value: Double): String =
        if (value % 1.0 == 0.0) value.toLong().toString() else String.format(Locale.getDefault(), "%.1f", value)

    private fun humanize(key: String): String =
        key.removePrefix("achievement_")
            .split('_')
            .filter { it.isNotBlank() }
            .joinToString(" ") { part -> part.replaceFirstChar { it.uppercase() } }
}
