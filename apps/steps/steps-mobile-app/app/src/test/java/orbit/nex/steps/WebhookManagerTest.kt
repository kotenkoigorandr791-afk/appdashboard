package com.stepmonitor.app

import kotlinx.coroutines.runBlocking
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * Guards the behaviour that used to produce 401/429/529 request storms:
 * every sync request must carry the device id, auth failures must not be retried,
 * rate limits must not be retried inline and overload responses must back off.
 */
class WebhookManagerTest {

    private lateinit var server: MockWebServer

    @Before
    fun setUp() {
        server = MockWebServer()
        server.start()
    }

    @After
    fun tearDown() {
        server.shutdown()
    }

    private fun manager(url: String) = WebhookManager(
        webhookConfigs = listOf(
            WebhookConfig(url = url, name = "test", headers = mapOf("Authorization" to "Bearer token"))
        ),
        deviceIdOverride = "0123456789abcdef0123456789abcdef",
        userAgentOverride = "1.9.35"
    )

    private fun payload() = """{"timestamp":"2026-10-20T10:00:00Z","app_version":"1.9.35"}"""

    @Test
    fun `sends device id and auth headers`() = runBlocking {
        server.enqueue(MockResponse().setResponseCode(200).setBody("{\"status\":\"success\"}"))

        val result = manager(server.url("/api/steps").toString()).postData(payload())

        assertTrue(result.isSuccess)
        val request = server.takeRequest()
        assertEquals("0123456789abcdef0123456789abcdef", request.getHeader("X-Device-Id"))
        assertEquals("Bearer token", request.getHeader("Authorization"))
        assertEquals("application/json", request.getHeader("Accept"))
        assertTrue(request.getHeader("User-Agent").orEmpty().startsWith("StepMonitor/"))
    }

    @Test
    fun `401 is not retried`() = runBlocking {
        server.enqueue(MockResponse().setResponseCode(401).setBody("{\"error\":\"invalid_token\"}"))

        val result = manager(server.url("/api/steps").toString()).postData(payload())

        assertTrue(result.isFailure)
        assertTrue(result.exceptionOrNull() is AuthException)
        assertEquals(1, server.requestCount)
    }

    @Test
    fun `429 is not retried inline`() = runBlocking {
        server.enqueue(MockResponse().setResponseCode(429).setBody("{\"error\":\"rate_limited\"}"))

        val result = manager(server.url("/api/steps").toString()).postData(payload())

        assertTrue(result.isFailure)
        assertEquals(1, server.requestCount)
    }

    @Test(timeout = 45_000)
    fun `529 is retried with backoff then reported`() = runBlocking {
        repeat(WebhookManagerRetryLimit.MAX_ATTEMPTS) {
            server.enqueue(MockResponse().setResponseCode(529).setBody("error code: 529"))
        }

        val result = manager(server.url("/api/steps").toString()).postData(payload())

        assertTrue(result.isFailure)
        assertNotNull(result.exceptionOrNull())
        assertEquals(WebhookManagerRetryLimit.MAX_ATTEMPTS, server.requestCount)
    }

    @Test
    fun `409 conflict is surfaced without retry`() = runBlocking {
        server.enqueue(MockResponse().setResponseCode(409).setBody("{\"status\":\"conflict\"}"))

        val result = manager(server.url("/api/steps").toString()).postData(payload())

        assertTrue(result.isFailure)
        assertTrue(result.exceptionOrNull() is SyncConflictException)
        assertEquals(1, server.requestCount)
    }
}

/** Kept in one place so the test and the implementation cannot drift apart. */
internal object WebhookManagerRetryLimit {
    const val MAX_ATTEMPTS = 3
}
