package orbit.nex.steps.location

import android.net.Uri
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
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

data class GeoMapData(
    val trips: List<GeoTrip> = emptyList(),
    val points: List<GeoPointFeature> = emptyList(),
    val bbox: GeoBoundingBox? = null,
    val error: String? = null,
) {
    val isEmpty: Boolean get() = trips.isEmpty() && points.isEmpty()
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
) {

    suspend fun loadDay(config: WebhookConfig, day: LocalDate): GeoMapData = withContext(Dispatchers.IO) {
        // The endpoint validates an exclusive upper bound, so a single day is
        // requested as [day, day + 1).
        val from = day.toString()
        val to = day.plusDays(1).toString()
        val url = buildString {
            append(apiBase(config))
            append("/geolocation_map?from=").append(from)
            append("&to=").append(to)
            append("&zoom=").append(MAP_ZOOM)
            append("&layers=trips,stops,places,visits,points,heatmap")
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
                    if (response.code == 400 && JSONObject(body).optString("error") == "invalid_bbox") {
                        return@use GeoMapData()
                    }
                    return@use GeoMapData(error = "HTTP ${response.code}")
                }
                parse(JSONObject(body))
            }
        }.getOrElse { error ->
            GeoMapData(error = error.message ?: "network")
        }
    }

    private fun parse(root: JSONObject): GeoMapData {
        if (!root.optBoolean("ok", false)) {
            return GeoMapData(error = root.optString("error").ifBlank { "error" })
        }
        val trips = mutableListOf<GeoTrip>()
        val points = mutableListOf<GeoPointFeature>()
        val features = root.optJSONObject("geojson")?.optJSONArray("features") ?: JSONArray()
        for (i in 0 until features.length()) {
            val feature = features.optJSONObject(i) ?: continue
            val properties = feature.optJSONObject("properties") ?: JSONObject()
            val geometry = feature.optJSONObject("geometry") ?: continue
            val layer = properties.optString("layer")
            when (geometry.optString("type")) {
                "LineString" -> {
                    val path = readLine(geometry.optJSONArray("coordinates"))
                    if (path.size >= 2) {
                        trips += GeoTrip(
                            id = properties.optString("id"),
                            title = properties.optString("title"),
                            path = path,
                            distanceM = properties.optDouble("distance_m", 0.0),
                        )
                    }
                }
                "MultiLineString" -> {
                    val lines = geometry.optJSONArray("coordinates") ?: JSONArray()
                    for (line in 0 until lines.length()) {
                        val path = readLine(lines.optJSONArray(line))
                        if (path.size >= 2) {
                            trips += GeoTrip(
                                id = properties.optString("id"),
                                title = properties.optString("title"),
                                path = path,
                                distanceM = properties.optDouble("distance_m", 0.0),
                            )
                        }
                    }
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
        }
        return GeoMapData(trips = trips, points = points, bbox = bbox)
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
