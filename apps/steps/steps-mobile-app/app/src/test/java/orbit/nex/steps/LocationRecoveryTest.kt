package orbit.nex.steps

import orbit.nex.steps.location.shouldRecoverLocation
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import orbit.nex.steps.location.LocationEvent
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import org.junit.Assert.assertEquals
import org.junit.Test

class LocationRecoveryTest {
    @Test fun recoversOnlyWhenFixAndPreviousRecoveryAreStale() {
        assertFalse(shouldRecoverLocation(120_000, 60_000, 0, 120_000))
        assertFalse(shouldRecoverLocation(150_000, 0, 60_000, 120_000))
        assertTrue(shouldRecoverLocation(180_000, 0, 60_000, 120_000))
    }
    @Test fun rawMetadataAndTimestampSurviveSerialization() {
        val event = LocationEvent("id", 50.0, 20.0, "2026-01-01T00:00:00Z", 0, 3.0, 12.0, 4f, 90f, 1, 100, "gps", "high", "gnss")
        val json = Json.parseToJsonElement(event.toServerJson("device")).jsonObject
        assertEquals("2026-01-01T00:00:00Z", json.getValue("source_at").jsonPrimitive.content)
        assertEquals("gps", json.getValue("provider").jsonPrimitive.content)
        assertEquals("high", json.getValue("tracking_mode").jsonPrimitive.content)
        assertEquals("gnss", json.getValue("measurement_origin").jsonPrimitive.content)
    }
}

