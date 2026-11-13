package orbit.nex.steps.location

import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put

/**
 * One location measurement, persisted locally before any network attempt.
 *
 * [clientEventId] is generated on the device and is the deduplication key the
 * Steps ingestion endpoint uses, so a retried upload can never store the same
 * measurement twice. [deviceSequence] is a monotonically increasing counter for
 * this installation and lets the server order points that share a timestamp.
 */
data class LocationEvent(
    val clientEventId: String,
    val latitude: Double,
    val longitude: Double,
    val sourceAt: String,
    val sourceOffsetMinutes: Int,
    val accuracyM: Double?,
    val altitudeM: Double?,
    val speedMps: Float?,
    val bearingDegrees: Float?,
    val deviceSequence: Long,
    val recordedAtMs: Long,
) {
    /** Body item accepted by `POST /api/steps/geolocation?action=batch`. */
    fun toServerJson(deviceId: String): String = buildJsonObject {
        put("device_id", deviceId)
        put("client_event_id", clientEventId)
        put("latitude", JsonPrimitive(latitude))
        put("longitude", JsonPrimitive(longitude))
        put("source_type", "mobile")
        put("source_at", sourceAt)
        put("source_offset_minutes", sourceOffsetMinutes)
        accuracyM?.let { put("accuracy_m", JsonPrimitive(it)) }
        altitudeM?.let { put("altitude_m", JsonPrimitive(it)) }
        speedMps?.let { put("speed_mps", JsonPrimitive(it)) }
        bearingDegrees?.let { put("bearing_degrees", JsonPrimitive(it)) }
        put("device_sequence", deviceSequence)
    }.toString()
}
