package orbit.nex.steps

import androidx.test.ext.junit.runners.AndroidJUnit4
import androidx.test.platform.app.InstrumentationRegistry
import kotlinx.coroutines.runBlocking
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import orbit.nex.steps.location.LocationAccuracy
import orbit.nex.steps.location.LocationEvent
import orbit.nex.steps.location.LocationEventStore
import orbit.nex.steps.location.LocationUploader
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith
import java.util.UUID

@RunWith(AndroidJUnit4::class)
class LocationTrackingInstrumentedTest {

    private val context = InstrumentationRegistry.getInstrumentation().targetContext
    private lateinit var server: MockWebServer
    private lateinit var prefs: PreferencesManager

    @Before
    fun setUp() {
        server = MockWebServer().apply { start() }
        prefs = PreferencesManager(context)
        prefs.setLocationTrackingEnabled(true)
        prefs.setLocationTrackingPaused(false)
        prefs.setWebhookConfigs(
            listOf(WebhookConfig(server.url("/api/steps").toString().trimEnd('/'), "test")),
        )
        LocationEventStore(context).purgeUploaded(0)
    }

    @After
    fun tearDown() {
        server.shutdown()
        prefs.setLocationTrackingEnabled(false)
    }

    private fun event(latitude: Double = 50.0): LocationEvent = LocationEvent(
        clientEventId = UUID.randomUUID().toString(),
        latitude = latitude,
        longitude = 30.0,
        sourceAt = "2026-11-13T10:00:00Z",
        sourceOffsetMinutes = 120,
        accuracyM = 5.0,
        altitudeM = null,
        speedMps = null,
        bearingDegrees = null,
        deviceSequence = prefs.nextLocationSequence(),
        recordedAtMs = System.currentTimeMillis(),
    )

    @Test
    fun outboxPersistsBeforeUploadAndDeduplicates() {
        val store = LocationEventStore(context)
        val first = event()
        assertTrue(store.insert(prefs.getLocationDeviceId(), first))
        assertFalse("same client event id must not be stored twice", store.insert(prefs.getLocationDeviceId(), first))

        // A brand-new store instance (fresh process) still sees the queued row.
        val reopened = LocationEventStore(context)
        assertTrue(reopened.pending().any { it.eventId == first.clientEventId })
    }

    @Test
    fun uploaderPostsDeviceIdAndEventIdThenClearsQueue() = runBlocking {
        val store = LocationEventStore(context)
        val queued = event()
        store.insert(prefs.getLocationDeviceId(), queued)

        server.enqueue(
            MockResponse()
                .setResponseCode(200)
                .setBody("""{"ok":true,"results":[{"status":"accepted","id":"x"}]}"""),
        )

        val report = LocationUploader(context).uploadPending(
            WebhookConfig(server.url("/api/steps").toString().trimEnd('/'), "test"),
            prefs,
        )

        assertNull(report.error)
        assertEquals(1, report.accepted)
        assertEquals(0, store.pendingCount())

        val request = server.takeRequest()
        assertEquals("/api/steps/geolocation?action=batch", request.path)
        assertEquals(prefs.getDeviceId(), request.getHeader("X-Device-Id"))
        val body = request.body.readUtf8()
        assertTrue(body.contains(queued.clientEventId))
        // The ingestion API rejects non-UUID device ids, so the payload uses the
        // dedicated location installation id rather than the compact pairing id.
        assertTrue(body.contains("\"device_id\":\"${prefs.getLocationDeviceId()}\""))
        assertTrue(prefs.getLocationDeviceId().matches(Regex("^[0-9a-fA-F-]{36}$")))
        assertTrue(body.contains("\"source_type\":\"mobile\""))
        assertTrue(body.contains("\"device_sequence\""))
    }

    @Test
    fun failedUploadKeepsMeasurementsForRetry() = runBlocking {
        val store = LocationEventStore(context)
        store.insert(prefs.getLocationDeviceId(), event())
        server.enqueue(MockResponse().setResponseCode(503))

        val report = LocationUploader(context).uploadPending(
            WebhookConfig(server.url("/api/steps").toString().trimEnd('/'), "test"),
            prefs,
        )

        assertTrue("transport failures must stay retryable", report.retryable)
        assertEquals(1, store.pendingCount())
        assertEquals("HTTP 503", prefs.getLocationUploadError())
    }

    @Test
    fun rejectedPointIsDroppedInsteadOfBlockingQueue() = runBlocking {
        val store = LocationEventStore(context)
        store.insert(prefs.getLocationDeviceId(), event())
        server.enqueue(
            MockResponse()
                .setResponseCode(200)
                .setBody("""{"ok":true,"results":[{"status":"rejected","error":"invalid_point"}]}"""),
        )

        val report = LocationUploader(context).uploadPending(
            WebhookConfig(server.url("/api/steps").toString().trimEnd('/'), "test"),
            prefs,
        )

        assertEquals(1, report.rejected)
        assertEquals(0, store.pendingCount())
    }

    @Test
    fun accuracyAndIntervalSettingsRoundTrip() {
        prefs.setLocationAccuracy(LocationAccuracy.HIGH)
        prefs.setLocationIntervalMinutes(5)
        assertEquals(LocationAccuracy.HIGH, prefs.getLocationAccuracy())
        assertEquals(5, prefs.getLocationIntervalMinutes())

        prefs.setLocationIntervalMinutes(0)
        assertEquals(1, prefs.getLocationIntervalMinutes())
    }
}
