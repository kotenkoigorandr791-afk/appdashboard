package orbit.nex.steps.location

import android.net.Uri
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.coroutines.sync.withLock
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.OkHttpClient
import okhttp3.Request
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.WebhookConfig
import org.json.JSONArray
import org.json.JSONObject
import java.time.LocalDate

/** A single coordinate from a GeoJSON feature, in the order the map needs. */
data class GeoLatLon(val lat: Double, val lon: Double)

/** One recorded trip (a GeoJSON LineString) for the selected day. */
data class GeoTrip(
    val id: String,
    val title: String,
    val path: List<GeoLatLon>,
    val distanceM: Double,
    val pending: Boolean = false,
    val kind: String = "trip",
)

/**
 * One weighted line of the server heatmap layer. The server aggregates repeated
 * passes over the same street into a single line with a relative [intensity]
 * in 0..1 and an absolute [weight] (number of passes).
 */
data class GeoHeatLine(
    val path: List<GeoLatLon>,
    val weight: Int,
    val intensity: Float,
)

/**
 * One continuous run of the travelled track with an average [kmh] and the
 * server speed [bucket] (0..5, slowest to fastest).
 */
data class GeoSpeedSegment(
    val path: List<GeoLatLon>,
    val bucket: Int,
    val kmh: Double,
)

/**
 * A point feature: a stop, place, visit, raw location point or heat cell.
 * [layer] is the server layer key, [count] the aggregated number of records.
 */
data class GeoPointFeature(
    val layer: String,
    val lat: Double,
    val lon: Double,
    val label: String?,
    val count: Int,
    val intensity: Float,
    val pending: Boolean = false,
)

/** Data extent of the response, used to frame the map after loading a day. */
data class GeoBoundingBox(val north: Double, val south: Double, val east: Double, val west: Double)

object GeoMapChanges {
    private val counter = kotlinx.coroutines.flow.MutableStateFlow(0L)
    val revision: kotlinx.coroutines.flow.StateFlow<Long> = counter
    fun invalidate(context: android.content.Context) {
        context.cacheDir.listFiles()?.filter { it.name.startsWith("geomap_") && it.name.endsWith(".json") }?.forEach { it.delete() }
        counter.value += 1
    }
}

data class GeoMapData(
    val trips: List<GeoTrip> = emptyList(),
    val points: List<GeoPointFeature> = emptyList(),
    /** Weighted lines of the `heatmap` layer. */
    val heat: List<GeoHeatLine> = emptyList(),
    /** Track split into speed buckets by the `speed` layer. */
    val speed: List<GeoSpeedSegment> = emptyList(),
    val bbox: GeoBoundingBox? = null,
    val error: String? = null,
) {
    val isEmpty: Boolean get() = trips.isEmpty() && points.isEmpty() && heat.isEmpty() && speed.isEmpty()
}

/**
 * Reads the geolocation map endpoint for one day.
 *
 * All layers are fetched in a single request and the layer switches in the UI
 * only decide what is drawn, so toggling a layer never waits for the network.
 */
class GeoMapRepository(
    private val prefs: PreferencesManager,
    private val client: OkHttpClient = OkHttpClient(),
    private val cacheDir: java.io.File? = null,
) {

    private fun cacheFile(config: WebhookConfig, day: LocalDate): java.io.File? =
        cacheDir?.let { java.io.File(it, "geomap_${(config.url + prefs.getDeviceId()).hashCode()}_$day.json") }

    suspend fun reconcileDeleted(config: WebhookConfig, context: android.content.Context, force: Boolean = false): Boolean = withContext(Dispatchers.IO) {
        LocationMutationLock.mutex.withLock {
            runCatching {
                val base = apiBase(config).trimEnd('/')
                fun builder(url: String): Request.Builder = Request.Builder().url(url)
                    .addHeader("Accept", "application/json")
                    .addHeader("X-Device-Id", prefs.getDeviceId())
                    .apply { config.headers.forEach { (key, value) -> addHeader(key, value) } }
                val revision = client.newCall(builder("$base/geolocation_points?revision=1").build()).execute().use { response ->
                    if (!response.isSuccessful) error("revision request failed")
                    val json = JSONObject(response.body?.string().orEmpty())
                    if (!json.optBoolean("ok") || !json.has("revision")) error("revision unavailable")
                    json.getString("revision")
                }
                val key = "geo_deletion_revision_${config.url.hashCode()}"
                val settings = prefs.getSharedPreferences()
                val changed = settings.getString(key, null) != revision
                if (!changed && !force) return@runCatching
                val store = LocationEventStore(context)
                val local = store.syncedEvents().map { it.eventId }
                val missing = mutableListOf<String>()
                local.chunked(500).forEach { batch ->
                    val payload = JSONObject().put("action", "reconcile").put("local_device_id", prefs.getLocationDeviceId()).put("event_ids", JSONArray(batch))
                    val request = builder("$base/geolocation_points").post(payload.toString().toRequestBody("application/json; charset=utf-8".toMediaType())).build()
                    val existing = client.newCall(request).execute().use { response ->
                        if (!response.isSuccessful) error("reconciliation failed")
                        val json = JSONObject(response.body?.string().orEmpty())
                        if (!json.optBoolean("ok") || !json.has("event_ids")) error("reconciliation unavailable")
                        val ids = json.getJSONArray("event_ids")
                        (0 until ids.length()).map { ids.getString(it) }.toSet()
                    }
                    missing += batch.filterNot { it in existing }
                }
                store.removeEvents(missing)
                settings.edit().putString(key, revision).apply()
                if (changed || missing.isNotEmpty()) GeoMapChanges.invalidate(context)
            }.isSuccess
        }
    }

    fun cached(config: WebhookConfig, day: LocalDate): GeoMapData? = runCatching {
        cacheFile(config, day)?.takeIf { it.exists() }?.let { parse(JSONObject(it.readText())) }
    }.getOrNull()

    suspend fun loadDay(config: WebhookConfig, day: LocalDate): GeoMapData = withContext(Dispatchers.IO) {
        // A bare date would be parsed by the server as UTC midnight, which drops
        // the local first hours of the day. Send the local day as explicit
        // instants: [local 00:00, local next 00:00).
        val generation = GeoMapChanges.revision.value
        val zone = java.time.ZoneId.systemDefault()
        val from = day.atStartOfDay(zone).toInstant().toString()
        val to = day.plusDays(1).atStartOfDay(zone).toInstant().minusMillis(1).toString()
        val url = buildString {
            append(apiBase(config))
            append("/geolocation_map?from=").append(from)
            append("&to=").append(to)
            append("&zoom=").append(MAP_ZOOM)
            append("&layers=trips,improved,stops,places,visits,points,heatmap,speed")
        }
        val request = Request.Builder()
            .url(url)
            .apply {
                config.headers.forEach { (key, value) -> addHeader(key, value) }
                addHeader("X-Device-Id", prefs.getDeviceId())
                addHeader("Accept", "application/json")
            }
            .build()
        runCatching {
            client.newCall(request).execute().use { response ->
                if (response.code == 401 || response.code == 403) prefs.markAuthError(response.code)
                val body = response.body?.string().orEmpty()
                if (!response.isSuccessful) {
                    if (response.code == 404) return@use GeoMapData()
                    if (response.code == 400 && JSONObject(body).optString("error") == "invalid_bbox") {
                        return@use GeoMapData()
                    }
                    return@use GeoMapData(error = "HTTP ${response.code}")
                }
                val parsed = parse(JSONObject(body))
                if (parsed.error == null && generation == GeoMapChanges.revision.value) runCatching { cacheFile(config, day)?.writeText(body) }
                parsed
            }
        }.getOrElse { error ->
            GeoMapData(error = error.message ?: "network")
        }.let { result ->
            if (result.error != null) cached(config, day)?.copy(error = result.error) ?: result else result
        }
    }

    private fun parse(root: JSONObject): GeoMapData {
        if (!root.optBoolean("ok", false)) {
            return GeoMapData(error = root.optString("error").ifBlank { "error" })
        }
        val trips = mutableListOf<GeoTrip>()
        val points = mutableListOf<GeoPointFeature>()
        val heat = mutableListOf<GeoHeatLine>()
        val speed = mutableListOf<GeoSpeedSegment>()
        val features = root.optJSONObject("geojson")?.optJSONArray("features") ?: JSONArray()
        for (i in 0 until features.length()) {
            val feature = features.optJSONObject(i) ?: continue
            val properties = feature.optJSONObject("properties") ?: JSONObject()
            val geometry = feature.optJSONObject("geometry") ?: continue
            val layer = properties.optString("layer")
            // The heatmap and speed layers are LineStrings too, so the layer key
            // decides what a line becomes. Treating every line as a trip is what
            // used to leave the heatmap overlay permanently empty.
            fun addLine(path: List<GeoLatLon>) {
                if (path.size < 2) return
                when (layer) {
                    "heatmap" -> heat += GeoHeatLine(
                        path = path,
                        weight = properties.optInt("weight", 1),
                        intensity = properties.optDouble("intensity", 0.5).toFloat().coerceIn(0f, 1f),
                    )
                    "speed" -> speed += GeoSpeedSegment(
                        path = path,
                        bucket = properties.optInt("bucket", 0).coerceIn(0, 5),
                        kmh = properties.optDouble("kmh", 0.0),
                    )
                    else -> trips += GeoTrip(
                        kind = if (layer == "matched" || layer == "smooth") layer else "trip",
                        id = properties.optString("id"),
                        title = properties.optString("title"),
                        path = path,
                        distanceM = properties.optDouble("distance_m", 0.0),
                    )
                }
            }
            when (geometry.optString("type")) {
                "LineString" -> addLine(readLine(geometry.optJSONArray("coordinates")))
                "MultiLineString" -> {
                    val lines = geometry.optJSONArray("coordinates") ?: JSONArray()
                    for (line in 0 until lines.length()) addLine(readLine(lines.optJSONArray(line)))
                }
                "Point" -> {
                    val coordinates = geometry.optJSONArray("coordinates") ?: continue
                    if (coordinates.length() < 2) continue
                    val lon = coordinates.optDouble(0)
                    val lat = coordinates.optDouble(1)
                    if (!isCoordinate(lat, lon)) continue
                    points += GeoPointFeature(
                        layer = layer,
                        lat = lat,
                        lon = lon,
                        label = when (layer) {
                            "place" -> properties.optString("name").ifBlank { null }
                            "visit" -> properties.optString("place_name").ifBlank { null }
                            else -> null
                        },
                        count = properties.optInt("count", 1),
                        intensity = properties.optDouble("intensity", 0.5).toFloat(),
                    )
                }
            }
        }
        val bbox = root.optJSONObject("bbox")?.let { box ->
            val south = box.optDouble("minLat", Double.NaN)
            val north = box.optDouble("maxLat", Double.NaN)
            val west = box.optDouble("minLon", Double.NaN)
            val east = box.optDouble("maxLon", Double.NaN)
            if (south.isNaN() || north.isNaN() || west.isNaN() || east.isNaN()) null
            else GeoBoundingBox(north = north, south = south, east = east, west = west)
        } ?: boundingBoxOf(points.asSequence().map { it.lat to it.lon } +
            trips.asSequence().flatMap { trip -> trip.path.asSequence().map { it.lat to it.lon } } +
            heat.asSequence().flatMap { line -> line.path.asSequence().map { it.lat to it.lon } } +
            speed.asSequence().flatMap { segment -> segment.path.asSequence().map { it.lat to it.lon } })
        return GeoMapData(trips = trips, points = points, heat = heat, speed = speed, bbox = bbox)
    }

    private fun readLine(coordinates: JSONArray?): List<GeoLatLon> {
        if (coordinates == null) return emptyList()
        val path = ArrayList<GeoLatLon>(coordinates.length())
        for (i in 0 until coordinates.length()) {
            val pair = coordinates.optJSONArray(i) ?: continue
            if (pair.length() < 2) continue
            val lon = pair.optDouble(0)
            val lat = pair.optDouble(1)
            if (isCoordinate(lat, lon)) path += GeoLatLon(lat, lon)
        }
        return path
    }

    private fun boundingBoxOf(coordinates: Sequence<Pair<Double, Double>>): GeoBoundingBox? {
        var north = Double.NEGATIVE_INFINITY
        var south = Double.POSITIVE_INFINITY
        var east = Double.NEGATIVE_INFINITY
        var west = Double.POSITIVE_INFINITY
        var seen = false
        coordinates.forEach { (lat, lon) ->
            if (!isCoordinate(lat, lon)) return@forEach
            seen = true
            north = maxOf(north, lat)
            south = minOf(south, lat)
            east = maxOf(east, lon)
            west = minOf(west, lon)
        }
        return if (seen) GeoBoundingBox(north = north, south = south, east = east, west = west) else null
    }

    private fun isCoordinate(lat: Double, lon: Double): Boolean =
        lat in -90.0..90.0 && lon in -180.0..180.0 && !lat.isNaN() && !lon.isNaN()

    companion object {
        /** Server aggregation is off from this zoom up, so a day is always raw. */
        const val MAP_ZOOM = 15

        /** Pairing URLs already include `/api/steps`; only scheme and host are reused. */
        fun apiBase(config: WebhookConfig): String {
            val uri = Uri.parse(config.url)
            val base = if (uri.scheme != null && uri.authority != null) {
                "${uri.scheme}://${uri.authority}"
            } else {
                config.url.trimEnd('/')
            }
            return "$base/api/steps"
        }
    }
}
