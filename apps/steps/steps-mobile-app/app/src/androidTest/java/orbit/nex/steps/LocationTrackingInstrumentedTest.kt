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
    private lateinit var config: WebhookConfig

    @Before
    fun setUp() {
        server = MockWebServer().apply { start() }
        prefs = PreferencesManager(context)
        prefs.setLocationTrackingEnabled(true)
        prefs.setLocationTrackingPaused(false)
        config = WebhookConfig(server.url("/api/steps").toString().trimEnd('/'), "test")
        prefs.setWebhookConfigs(listOf(config))
        // Start every test from an empty outbox, including rows still waiting
        // out a backoff, which claim() would not return yet.
        LocationEventStore(context).clearAll()
    }

    @After
    fun tearDown() {
        runCatching { server.shutdown() }
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

    private fun clearQueue() {
        val store = LocationEventStore(context)
        store.claim(limit = 500, leaseMs = 0).forEach {
            store.settle(it.eventId, LocationEventStore.STATE_ACCEPTED)
        }
        store.trim()
    }

    @Test
    fun outboxPersistsBeforeUploadAndDeduplicates() {
        clearQueue()
        val store = LocationEventStore(context)
        val first = event()
        assertTrue(store.insert(prefs.getLocationDeviceId(), first))
        assertFalse("same client event id must not be stored twice", store.insert(prefs.getLocationDeviceId(), first))

        // A brand-new store instance (fresh process) still sees the queued row.
        val claimed = LocationEventStore(context).claim()
        assertTrue(claimed.any { it.eventId == first.clientEventId })
    }

    @Test
    fun expiredLeaseIsReclaimedAfterProcessDeath() {
        clearQueue()
        val store = LocationEventStore(context)
        val queued = event()
        store.insert(prefs.getLocationDeviceId(), queued)

        // First worker leases the row and "dies" before the response arrives.
        val leased = store.claim(limit = 10, leaseMs = 0)
        assertEquals(listOf(queued.clientEventId), leased.map { it.eventId })

        // A new process reclaims the expired lease instead of losing the point.
        val reclaimed = LocationEventStore(context).claim(limit = 10, leaseMs = 0)
        assertEquals(listOf(queued.clientEventId), reclaimed.map { it.eventId })
    }

    @Test
    fun uploaderPostsIdentityAndSettlesAcceptedRows() = runBlocking {
        clearQueue()
        val store = LocationEventStore(context)
        val queued = event()
        store.insert(prefs.getLocationDeviceId(), queued)

        server.enqueue(
            MockResponse()
                .setResponseCode(200)
                .setBody("""{"ok":true,"results":[{"status":"accepted","id":"x"}]}"""),
        )

        val report = LocationUploader(context).uploadPending(config, prefs)

        assertNull(report.error)
        assertEquals(1, report.accepted)
        assertEquals(0, store.pendingCount())
        assertEquals(1, store.counts()[LocationEventStore.STATE_ACCEPTED])

        val request = server.takeRequest()
        assertEquals("/api/steps/geolocation?action=batch", request.path)
        assertEquals(prefs.getDeviceId(), request.getHeader("X-Device-Id"))
        val body = request.body.readUtf8()
        assertTrue(body.contains(queued.clientEventId))
        assertTrue(body.contains("\"device_id\":\"${prefs.getLocationDeviceId()}\""))
        assertTrue(prefs.getLocationDeviceId().matches(Regex("^[0-9a-fA-F-]{36}$")))
        assertTrue(body.contains("\"source_type\":\"mobile\""))
        assertTrue(body.contains("\"device_sequence\""))
    }

    @Test
    fun serverErrorKeepsRowsAndDefersNextAttempt() = runBlocking {
        clearQueue()
        val store = LocationEventStore(context)
        store.insert(prefs.getLocationDeviceId(), event())
        server.enqueue(MockResponse().setResponseCode(503))

        val first = LocationUploader(context).uploadPending(config, prefs)
        assertTrue("transport failures must stay retryable", first.retryable)
        assertEquals(1, store.pendingCount())

        // Backoff must prevent an immediate second request (no request storm).
        val second = LocationUploader(context).uploadPending(config, prefs)
        assertNull(second.error)
        assertEquals(1, server.requestCount)
        assertEquals(1, store.pendingCount())
    }

    @Test
    fun offlineUploadKeepsRowsForReconnect() = runBlocking {
        clearQueue()
        val store = LocationEventStore(context)
        store.insert(prefs.getLocationDeviceId(), event())
        server.shutdown()

        val report = LocationUploader(context).uploadPending(config, prefs)

        assertTrue(report.retryable)
        assertEquals(1, store.pendingCount())
    }

    @Test
    fun rejectedPointIsTerminalInsteadOfBlockingQueue() = runBlocking {
        clearQueue()
        val store = LocationEventStore(context)
        store.insert(prefs.getLocationDeviceId(), event())
        server.enqueue(
            MockResponse()
                .setResponseCode(200)
                .setBody("""{"ok":true,"results":[{"status":"rejected","error":"invalid_point"}]}"""),
        )

        val report = LocationUploader(context).uploadPending(config, prefs)

        assertEquals(1, report.rejected)
        assertEquals(0, store.pendingCount())
        assertEquals(1, store.counts()[LocationEventStore.STATE_REJECTED])
    }

    @Test
    fun unknownStatusRetriesInsteadOfDropping() = runBlocking {
        clearQueue()
        val store = LocationEventStore(context)
        store.insert(prefs.getLocationDeviceId(), event())
        server.enqueue(
            MockResponse()
                .setResponseCode(200)
                .setBody("""{"ok":true,"results":[{"status":"processing"}]}"""),
        )

        val report = LocationUploader(context).uploadPending(config, prefs)

        assertTrue("a status this client does not know must be retried", report.retryable)
        assertEquals(1, store.pendingCount())
    }

    @Test
    fun malformedResponseKeepsDataAndRetries() = runBlocking {
        clearQueue()
        val store = LocationEventStore(context)
        store.insert(prefs.getLocationDeviceId(), event())
        server.enqueue(MockResponse().setResponseCode(200).setBody("""{"ok":true}"""))

        val report = LocationUploader(context).uploadPending(config, prefs)

        assertTrue(report.retryable)
        assertEquals(1, store.pendingCount())
    }

    @Test
    fun backoffIsBoundedExponential() {
        assertEquals(15_000L, LocationEventStore.backoffMs(1))
        assertEquals(30_000L, LocationEventStore.backoffMs(2))
        assertTrue(LocationEventStore.backoffMs(20) <= 30L * 60 * 1000)
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
