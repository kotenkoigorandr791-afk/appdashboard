package orbit.nex.steps.location

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.WebhookConfig
import org.json.JSONArray
import org.json.JSONObject
import java.time.OffsetDateTime
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.util.Locale

data class WeekdayMovement(val trips: Int, val distanceM: Double)
data class NamedPlace(val name: String, val visits: Int)
data class DeviceHealth(val name: String, val platform: String, val status: String)

data class ServerStatistics(
    val activeDays: Int = 0,
    val routeDistanceM: Double = 0.0,
    val activeSeconds: Long = 0,
    val trips: Int = 0,
    val averageTripM: Double = 0.0,
    val countries: List<String> = emptyList(),
    val cities: List<String> = emptyList(),
    val places: List<NamedPlace> = emptyList(),
    val byWeekday: List<WeekdayMovement> = emptyList(),
    val coverageRatio: Double = 0.0,
    val missingDays: Int = 0,
    val accuracyM: Double? = null,
    val latestPointAt: String? = null,
    val devices: List<DeviceHealth> = emptyList(),
    val sources: Map<String, Int> = emptyMap(),
    val processing: Boolean = false,
    val cached: Boolean = false,
    val computedAt: String? = null,
    val error: String? = null,
) {
    val hasData: Boolean get() = activeDays > 0 || routeDistanceM > 0.0
}

data class AchievementCard(
    val id: String,
    val titleKey: String,
    val descriptionKey: String,
    val icon: String,
    val unit: String,
    val target: Double,
    val earnedAt: String?,
)

data class EarnedAchievements(
    val enabled: Boolean = true,
    val cards: List<AchievementCard> = emptyList(),
    val earnedCount: Int = 0,
    val processing: Boolean = false,
    val error: String? = null,
)

/**
 * Read-only owner endpoints for the unified Statistics screen.
 *
 * The server owns all aggregation: this repository never infers transport,
 * recomputes trips or stores coordinates. Achievements are reduced to the
 * earned-only card list, so locked progress never enters the normal UI.
 */
class StatisticsRepository(
    private val prefs: PreferencesManager,
    private val client: OkHttpClient = OkHttpClient(),
) {
    suspend fun load(config: WebhookConfig): ServerStatistics = withContext(Dispatchers.IO) {
        runCatching {
            val root = get(config, "geolocation_statistics?tz=" + ZoneId.systemDefault().id)
            parseStatistics(root)
        }.getOrElse { ServerStatistics(error = it.message ?: "network") }
    }

    suspend fun achievements(config: WebhookConfig): EarnedAchievements = withContext(Dispatchers.IO) {
        runCatching {
            val root = get(config, "geolocation_achievements")
            parseAchievements(root)
        }.getOrElse { EarnedAchievements(error = it.message ?: "network") }
    }

    private fun get(config: WebhookConfig, path: String): JSONObject {
        val request = Request.Builder()
            .url(GeoMapRepository.apiBase(config).trimEnd('/') + "/" + path)
            .addHeader("Accept", "application/json")
            .addHeader("X-Device-Id", prefs.getDeviceId())
            .apply { config.headers.forEach { (key, value) -> addHeader(key, value) } }
            .build()
        client.newCall(request).execute().use { response ->
            if (response.code == 401 || response.code == 403) prefs.markAuthError(response.code)
            val body = response.body?.string().orEmpty()
            val root = runCatching { JSONObject(body) }.getOrElse { JSONObject() }
            if (!response.isSuccessful || !root.optBoolean("ok", false)) {
                error(root.optString("error").ifBlank { "HTTP ${response.code}" })
            }
            return root
        }
    }

    private fun parseStatistics(root: JSONObject): ServerStatistics {
        val metrics = root.optJSONObject("metrics") ?: JSONObject()
        val movement = root.optJSONObject("movement_patterns") ?: JSONObject()
        val coverage = root.optJSONObject("coverage") ?: JSONObject()
        val accuracy = root.optJSONObject("accuracy") ?: JSONObject()
        val health = root.optJSONObject("source_health") ?: JSONObject()
        val countries = mutableListOf<String>()
        metrics.optJSONArray("countries")?.let { rows ->
            for (index in 0 until rows.length()) {
                val row = rows.optJSONObject(index) ?: continue
                row.optString("name").ifBlank { row.optString("code") }.takeIf { it.isNotBlank() }?.let(countries::add)
            }
        }
        val cities = mutableListOf<String>()
        metrics.optJSONArray("cities")?.let { rows ->
            for (index in 0 until rows.length()) rows.optString(index).takeIf { it.isNotBlank() }?.let(cities::add)
        }
        val places = mutableListOf<NamedPlace>()
        root.optJSONArray("most_visited_places")?.let { rows ->
            for (index in 0 until rows.length()) {
                val row = rows.optJSONObject(index) ?: continue
                val name = row.optString("name").ifBlank { row.optString("label") }
                if (name.isBlank()) continue
                places += NamedPlace(name, row.optInt("visits", row.optInt("count", 0)))
            }
        }
        val weekdays = mutableListOf<WeekdayMovement>()
        movement.optJSONArray("by_weekday")?.let { rows ->
            for (index in 0 until rows.length()) {
                val row = rows.optJSONObject(index) ?: continue
                weekdays += WeekdayMovement(row.optInt("trips", 0), row.optDouble("distance_m", 0.0))
            }
        }
        val devices = mutableListOf<DeviceHealth>()
        health.optJSONArray("devices")?.let { rows ->
            for (index in 0 until rows.length()) {
                val row = rows.optJSONObject(index) ?: continue
                devices += DeviceHealth(
                    row.optString("name").ifBlank { row.optString("platform") },
                    row.optString("platform"),
                    row.optString("status"),
                )
            }
        }
        val sources = mutableMapOf<String, Int>()
        health.optJSONObject("sources")?.let { rows ->
            rows.keys().forEach { key -> sources[key] = rows.optInt(key, 0) }
        }
        return ServerStatistics(
            activeDays = metrics.optInt("active_days", 0),
            routeDistanceM = metrics.optDouble("route_distance_m", 0.0),
            activeSeconds = metrics.optLong("active_seconds", 0L),
            trips = movement.optInt("trips", 0),
            averageTripM = movement.optDouble("avg_distance_m", 0.0),
            countries = countries,
            cities = cities,
            places = places,
            byWeekday = weekdays,
            coverageRatio = coverage.optDouble("coverage_ratio", 0.0),
            missingDays = coverage.optInt("missing_days", 0),
            accuracyM = accuracy.optDouble("avg_m").takeUnless { it.isNaN() },
            latestPointAt = health.optString("latest_point_at").takeIf { it.isNotBlank() },
            devices = devices,
            sources = sources,
            processing = root.optBoolean("processing", false),
            cached = root.optBoolean("cached", false),
            computedAt = root.optString("computed_at").takeIf { it.isNotBlank() },
        )
    }

    private fun parseAchievements(root: JSONObject): EarnedAchievements {
        val cards = mutableListOf<AchievementCard>()
        val rows: JSONArray = root.optJSONArray("earned_achievements") ?: JSONArray()
        for (index in 0 until rows.length()) {
            val row = rows.optJSONObject(index) ?: continue
            cards += AchievementCard(
                id = row.optString("id"),
                titleKey = row.optString("title_key"),
                descriptionKey = row.optString("description_key"),
                icon = row.optString("icon"),
                unit = row.optString("unit"),
                target = row.optDouble("target", 0.0),
                earnedAt = row.optString("earned_at").takeIf { it.isNotBlank() },
            )
        }
        return EarnedAchievements(
            enabled = root.optBoolean("enabled", true),
            cards = cards,
            earnedCount = root.optInt("earned_count", cards.size),
            processing = root.optBoolean("processing", false),
        )
    }

    companion object {
        fun dateLabel(value: String?): String {
            if (value.isNullOrBlank()) return ""
            return runCatching {
                OffsetDateTime.parse(value)
                    .atZoneSameInstant(ZoneId.systemDefault())
                    .format(DateTimeFormatter.ofPattern("d MMM yyyy", Locale.getDefault()))
            }.getOrElse { value.take(10) }
        }
    }
}
