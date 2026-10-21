package com.stepmonitor.app

import android.content.Context
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.async
import kotlinx.coroutines.awaitAll
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.IOException
import java.net.ProtocolException
import java.net.SocketTimeoutException
import java.net.UnknownHostException
import java.util.UUID
import java.util.concurrent.TimeUnit
import javax.net.ssl.SSLException
import kotlin.math.pow
import kotlin.random.Random

internal class HttpResponseException(
    val statusCode: Int,
    message: String
) : IOException(message)

internal class AuthException(
    val statusCode: Int,
    message: String
) : IOException(message)

internal class SyncConflictException(
    val conflictJson: String,
    message: String
) : IOException(message)

class WebhookManager(
    private val webhookConfigs: List<WebhookConfig>,
    private val context: Context? = null,
    private val dataType: String? = null,
    private val recordCount: Int? = null,
    private val syncType: String? = null,
    private val payload: String? = null,
    /** Optional overrides used by tests; production derives both from [context]. */
    private val deviceIdOverride: String? = null,
    private val userAgentOverride: String? = null
) {

    private val client = OkHttpClient.Builder()
        .connectTimeout(TIMEOUT_SECONDS, TimeUnit.SECONDS)
        .readTimeout(TIMEOUT_SECONDS, TimeUnit.SECONDS)
        .writeTimeout(TIMEOUT_SECONDS, TimeUnit.SECONDS)
        .build()

    private val jsonMediaType = "application/json; charset=utf-8".toMediaType()

    suspend fun postData(jsonPayload: String): Result<Unit> = withContext(Dispatchers.IO) {
        val enabledConfigs = webhookConfigs.filter { it.isEnabled }
        if (enabledConfigs.isEmpty()) {
            return@withContext Result.failure(IllegalStateException("No enabled webhook URLs configured"))
        }

        var atLeastOneSuccess = false
        var retryableFailure: IOException? = null
        var lastFailure: Throwable? = null

        // Post to ALL enabled webhooks in parallel
        val results = coroutineScope {
            enabledConfigs.map { config -> async { postToUrl(config, jsonPayload) } }.awaitAll()
        }
        for (result in results) {
            if (result.isSuccess) {
                atLeastOneSuccess = true
            } else {
                val ex = result.exceptionOrNull()
                lastFailure = ex
                if (ex is IOException && isRetryableException(ex)) {
                    retryableFailure = ex
                }
            }
        }

        // Return success if at least one webhook succeeded
        if (atLeastOneSuccess) {
            return@withContext Result.success(Unit)
        }

        // Prefer a retryable exception so that SyncWorker can schedule a retry
        // even if the last webhook failed with a non-retryable error
        Result.failure(retryableFailure ?: lastFailure ?: IOException("All webhook posts failed"))
    }

    private suspend fun postToUrl(config: WebhookConfig, jsonPayload: String): Result<Unit> {
        val timestamp = System.currentTimeMillis()
        var statusCode: Int? = null
        var errorMessage: String? = null

        return try {
            val requestBody = jsonPayload.toRequestBody(jsonMediaType)
            val requestBuilder = Request.Builder()
                .url(config.url)
                .post(requestBody)

            val configHeaderKeys = config.headers.keys.map { it.lowercase() }.toSet()
            val deviceId = deviceIdOverride ?: context?.let { PreferencesManager(it).getDeviceId() }
            if ("x-device-id" !in configHeaderKeys && deviceId != null) {
                requestBuilder.addHeader("X-Device-Id", deviceId)
            }
            if ("user-agent" !in configHeaderKeys) {
                val versionName = userAgentOverride ?: try {
                    context?.packageManager?.getPackageInfo(context.packageName, 0)?.versionName ?: "unknown"
                } catch (_: Exception) { "unknown" }
                requestBuilder.addHeader("User-Agent", "StepMonitor/$versionName")
            }
            if ("accept" !in configHeaderKeys) {
                requestBuilder.addHeader("Accept", "application/json")
            }

            config.headers.forEach { (key, value) ->
                requestBuilder.addHeader(key, value)
            }

            val request = requestBuilder.build()

            var lastException: Exception? = null
            var attempt = 0
            while (attempt < MAX_RETRIES) {
                attempt++
                var shouldRetry = false
                try {
                    client.newCall(request).execute().use { response ->
                        statusCode = response.code
                        if (response.isSuccessful) {
                            logWebhookCall(config.url, timestamp, statusCode, true, null, System.currentTimeMillis() - timestamp)
                            return Result.success(Unit)
                        } else {
                            val body = response.body?.string().orEmpty()
                            if (response.code == 409 && body.isNotBlank()) {
                                val conflictException = SyncConflictException(body, "Sync conflict")
                                lastException = conflictException
                                errorMessage = conflictException.message
                                shouldRetry = false
                            } else if (response.code == 401 || response.code == 403) {
                                val authEx = AuthException(response.code, "HTTP ${response.code}: Authentication failed")
                                lastException = authEx
                                errorMessage = "HTTP ${response.code}: auth rejected (non-retryable)"
                                logWebhookCall(config.url, timestamp, statusCode, false, errorMessage, System.currentTimeMillis() - timestamp)
                                return Result.failure(authEx)
                            } else if (response.code == 429) {
                                // Rate limited: never retry inline. Retrying here is what turned
                                // a single rate-limit reply into a request storm (7-10 POSTs per
                                // second), which then escalated to Cloudflare 529. Let WorkManager
                                // apply its exponential backoff instead.
                                val retryAfter = response.header("Retry-After")
                                errorMessage = "HTTP 429: rate limited (Retry-After=${retryAfter ?: "none"}), backing off"
                                logWebhookCall(config.url, timestamp, statusCode, false, errorMessage, System.currentTimeMillis() - timestamp)
                                val ex = HttpResponseException(429, errorMessage ?: "HTTP 429")
                                lastException = ex
                                return Result.failure(ex)
                            } else if (response.code == 529) {
                                // Cloudflare "site is overloaded" (origin saturated). Treat as
                                // transient, but back off noticeably longer than other 5xx.
                                errorMessage = "HTTP 529: server overloaded (origin saturated), backing off"
                                logWebhookCall(config.url, timestamp, statusCode, false, errorMessage, System.currentTimeMillis() - timestamp)
                                lastException = HttpResponseException(529, errorMessage ?: "HTTP 529")
                                shouldRetry = true
                            } else {
                                val location = response.header("Location")
                                val detail = buildString {
                                    append(response.message)
                                    if (body.isNotBlank()) append(" ").append(body.take(300))
                                    if (response.code in 300..399 && !location.isNullOrBlank()) {
                                        append(" Location=").append(location)
                                    }
                                }
                                val httpException = HttpResponseException(
                                    response.code,
                                    "HTTP ${response.code}: $detail"
                                )
                                lastException = httpException
                                errorMessage = httpException.message
                                shouldRetry = isRetryableException(httpException)
                            }
                        }
                    }

                    if (!shouldRetry) {
                        break
                    }
                } catch (e: IOException) {
                    lastException = e
                    errorMessage = e.message
                    shouldRetry = isRetryableException(e)

                    if (!shouldRetry) {
                        break
                    }
                }

                if (attempt < MAX_RETRIES) {
                    val overloaded = (lastException as? HttpResponseException)?.statusCode == 529
                    val baseDelay = INITIAL_RETRY_DELAY_MS * (2.0.pow(attempt - 1).toLong()) * if (overloaded) 4 else 1
                    val jitterFactor = 0.7 + Random.nextDouble() * 0.6
                    val delayMs = (baseDelay * jitterFactor).toLong()
                    kotlinx.coroutines.delay(delayMs)
                }
            }

            logWebhookCall(config.url, timestamp, statusCode, false, errorMessage, System.currentTimeMillis() - timestamp)
            Result.failure(lastException ?: IOException("Max retries exceeded"))
        } catch (e: CancellationException) {
            throw e
        } catch (e: Exception) {
            android.util.Log.e("WebhookManager", "Post to ${config.url.redactSensitiveUrl()} failed", e)
            logWebhookCall(config.url, timestamp, null, false, e.message, System.currentTimeMillis() - timestamp)
            Result.failure(e)
        }
    }

    private fun String.redactSensitiveUrl(): String {
        var safe = this
        // Redact common auth query params
        safe = safe.replace(Regex("([?&](token|key|apikey|auth|api_key|secret)=)[^&]+", RegexOption.IGNORE_CASE), "$1********")
        // Redact Discord webhook tokens
        safe = safe.replace(Regex("(discord\\.com/api/webhooks/\\d+/)[^/?&]+"), "$1********")
        // Redact Telegram bot tokens (just in case they leak into url somehow)
        safe = safe.replace(Regex("(api\\.telegram\\.org/bot)[^/]+"), "$1********")
        return safe
    }

    private fun logWebhookCall(
        url: String,
        timestamp: Long,
        statusCode: Int?,
        success: Boolean,
        errorMessage: String?,
        responseTimeMs: Long
    ) {
        context?.let {
            val preferencesManager = PreferencesManager(it)
            val log = WebhookLog(
                id = UUID.randomUUID().toString(),
                timestamp = timestamp,
                url = url.redactSensitiveUrl(),
                statusCode = statusCode,
                success = success,
                errorMessage = errorMessage?.redactSensitiveUrl(),
                dataType = dataType,
                recordCount = recordCount,
                responseTimeMs = responseTimeMs,
                syncType = syncType,
                payload = payload?.take(MAX_PAYLOAD_CHARS),
                deliveryFormat = WebhookLog.FORMAT_JSON
            )
            preferencesManager.addWebhookLog(log)
        }
    }

    companion object {
        private const val TIMEOUT_SECONDS = 60L
        private const val MAX_RETRIES = 3
        private const val INITIAL_RETRY_DELAY_MS = 1000L
        private const val MAX_PAYLOAD_CHARS = 8000

        internal fun isRetryableException(exception: IOException): Boolean {
            return when (exception) {
                is AuthException -> false
                is HttpResponseException -> exception.statusCode >= 500
                is SocketTimeoutException -> true
                is UnknownHostException -> true
                is SSLException -> false
                is ProtocolException -> false
                else -> true
            }
        }
    }
}
