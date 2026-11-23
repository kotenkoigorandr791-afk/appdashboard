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
    val startedAt: String? = null,
    val endedAt: String? = null,
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
    val photoUrl: String? = null,
    val osmType: String? = null,
    val osmId: Long? = null,
    val photoAttribution: String? = null,
    val assetId: String? = null,
    val immichAssetId: String? = null,
    val immichOpenUrl: String? = null,
    val capturedAt: String? = null,
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
        val generation = GeoMapChanges.revision.value
        val zone = java.time.ZoneId.systemDefault()
        val from = day.atStartOfDay(zone).toInstant().toString()
        val to = day.plusDays(1).atStartOfDay(zone).toInstant().minusMillis(1).toString()
        val url = buildString {
            append(apiBase(config))
            append("/geolocation_map?from=").append(from)
            append("&to=").append(to)
            append("&zoom=").append(MAP_ZOOM)
            append("&layers=trips,improved,stops,places,visits,points,photos,heatmap,speed")
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
                val parsed = parse(JSONObject(body)).withoutSpoofOutliers()
                if (parsed.error == null && generation == GeoMapChanges.revision.value) runCatching { cacheFile(config, day)?.writeText(body) }
                parsed
            }
        }.getOrElse { error ->
            GeoMapData(error = error.message ?: "network")
        }.let { result ->
            if (result.error != null) cached(config, day)?.copy(error = result.error)?.withoutSpoofOutliers()
                ?: result
            else result
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
                        startedAt = properties.optString("started_at").ifBlank { null },
                        endedAt = properties.optString("ended_at").ifBlank { null },
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
                    val osmType = properties.optString("osm_type").ifBlank { null }
                    val osmId = properties.optLong("osm_id").takeIf { properties.has("osm_id") && !properties.isNull("osm_id") && it > 0L }
                    val photoUrl = properties.optString("photo_url").ifBlank { properties.optString("thumbnail").ifBlank { null } }
                    val assetId = properties.optString("asset_id").ifBlank { null }
                    points += GeoPointFeature(
                        layer = layer,
                        lat = lat,
                        lon = lon,
                        label = when (layer) {
                            "place" -> properties.optString("name").ifBlank { null }
                            "visit" -> properties.optString("place_name").ifBlank { properties.optString("category") }.ifBlank { properties.optString("detection_status") }.ifBlank { null }
                            "photo_cluster" -> properties.optString("original_filename").ifBlank {
                                if (properties.optInt("count", 1) > 1) properties.optInt("count", 1).toString() else null
                            }
                            else -> null
                        },
                        count = properties.optInt("count", 1),
                        intensity = properties.optDouble("intensity", 0.5).toFloat(),
                        photoUrl = photoUrl,
                        osmType = osmType,
                        osmId = osmId,
                        photoAttribution = properties.optString("photo_attribution").ifBlank { null },
                        assetId = assetId,
                        immichAssetId = properties.optString("immich_asset_id").ifBlank { null },
                        immichOpenUrl = properties.optString("immich_open_url").ifBlank { null },
                        capturedAt = properties.optString("captured_at").ifBlank { null },
                    )
                }
            }
        }
        return GeoMapData(trips = trips, points = points, heat = heat, speed = speed).withoutSpoofOutliers()
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
        /** Drop minority teleport clusters (<20%) so the map frames the real day. */
        private const val SPOOF_MINORITY_MAX = 0.20
        private const val CLUSTER_CELL_DEG = 2.0

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

        fun GeoMapData.withoutSpoofOutliers(): GeoMapData {
            if (error != null) return this
            val samples = collectSamples()
            val keep = dominantClusterMask(samples)
            if (keep == null) {
                return copy(bbox = boundingBoxOfSamples(samples) ?: bbox)
            }
            fun kept(lat: Double, lon: Double) = keep(cellKey(lat, lon))
            val nextPoints = points.filter { kept(it.lat, it.lon) }
            val nextTrips = trips.mapNotNull { trip ->
                val path = trip.path.filter { kept(it.lat, it.lon) }
                if (path.size < 2) null else trip.copy(path = path)
            }
            val nextHeat = heat.mapNotNull { line ->
                val path = line.path.filter { kept(it.lat, it.lon) }
                if (path.size < 2) null else line.copy(path = path)
            }
            val nextSpeed = speed.mapNotNull { seg ->
                val path = seg.path.filter { kept(it.lat, it.lon) }
                if (path.size < 2) null else seg.copy(path = path)
            }
            val filtered = copy(trips = nextTrips, points = nextPoints, heat = nextHeat, speed = nextSpeed)
            return filtered.copy(bbox = boundingBoxOfSamples(filtered.collectSamples()) ?: filtered.bbox)
        }

        private fun GeoMapData.collectSamples(): List<Pair<Double, Double>> {
            val samples = ArrayList<Pair<Double, Double>>(points.size + 64)
            points.forEach { samples += it.lat to it.lon }
            trips.forEach { trip -> trip.path.forEach { samples += it.lat to it.lon } }
            heat.forEach { line -> line.path.forEach { samples += it.lat to it.lon } }
            speed.forEach { seg -> seg.path.forEach { samples += it.lat to it.lon } }
            return samples
        }

        private fun cellKey(lat: Double, lon: Double): Long {
            val y = kotlin.math.floor(lat / CLUSTER_CELL_DEG).toLong()
            val x = kotlin.math.floor(lon / CLUSTER_CELL_DEG).toLong()
            return (y shl 32) xor (x and 0xffffffffL)
        }

        private fun dominantClusterMask(samples: List<Pair<Double, Double>>): ((Long) -> Boolean)? {
            if (samples.size < 5) return null
            val counts = LinkedHashMap<Long, Int>()
            samples.forEach { (lat, lon) ->
                val key = cellKey(lat, lon)
                counts[key] = (counts[key] ?: 0) + 1
            }
            if (counts.size < 2) return null
            val total = samples.size.toDouble()
            val dominant = counts.maxByOrNull { it.value } ?: return null
            if (dominant.value / total < (1.0 - SPOOF_MINORITY_MAX)) return null
            val dy = dominant.key shr 32
            val dx = dominant.key and 0xffffffffL
            val allowed = HashSet<Long>(9)
            for (oy in -1L..1L) for (ox in -1L..1L) {
                allowed += ((dy + oy) shl 32) xor ((dx + ox) and 0xffffffffL)
            }
            return { key -> key in allowed }
        }

        private fun boundingBoxOfSamples(samples: List<Pair<Double, Double>>): GeoBoundingBox? {
            var north = Double.NEGATIVE_INFINITY
            var south = Double.POSITIVE_INFINITY
            var east = Double.NEGATIVE_INFINITY
            var west = Double.POSITIVE_INFINITY
            var any = false
            samples.forEach { (lat, lon) ->
                if (lat !in -90.0..90.0 || lon !in -180.0..180.0 || lat.isNaN() || lon.isNaN()) return@forEach
                any = true
                if (lat > north) north = lat
                if (lat < south) south = lat
                if (lon > east) east = lon
                if (lon < west) west = lon
            }
            return if (!any) null else GeoBoundingBox(north = north, south = south, east = east, west = west)
        }
    }
}
