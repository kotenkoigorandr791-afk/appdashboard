package orbit.nex.steps.location

import android.content.Context
import android.net.Uri
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.add
import kotlinx.serialization.json.buildJsonArray
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import kotlinx.serialization.json.put
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.WebhookConfig
import orbit.nex.steps.WebhookLog
import java.io.IOException
import java.util.UUID

/** Result of one upload attempt; [error] is null when every row was settled. */
data class UploadReport(
    val accepted: Int = 0,
    val duplicate: Int = 0,
    val rejected: Int = 0,
    val failed: Int = 0,
    val pending: Int = 0,
    val error: String? = null,
    val retryable: Boolean = false,
)

/**
 * Uploads the leased outbox to the Steps ingestion endpoint from tasks 04/05.
 *
 * Rows are leased before the request and settled one by one from the per-item
 * results, so a crash or a lost connection leaves them leased only until the
 * lease expires. Result statuses are reconciled by client event id; an
 * unexpected shape releases the lease and retries instead of dropping data.
 * Accepted, duplicate and rejected rows are terminal, while a transport error
 * or an unknown status returns to pending with bounded backoff.
 */
class LocationUploader(
    context: Context,
    private val client: OkHttpClient = OkHttpClient(),
) {
    private val appContext = context.applicationContext

    suspend fun uploadPending(
        config: WebhookConfig,
        prefs: PreferencesManager,
        limit: Int = LocationEventStore.DEFAULT_BATCH,
        /** A combined sync run writes one log row for both points and health data. */
        writeLog: Boolean = true,
    ): UploadReport = withContext(Dispatchers.IO) {
        val store = LocationEventStore(appContext)
        store.trim()
        val events = store.claim(limit)
        if (events.isEmpty()) return@withContext UploadReport(pending = store.pendingCount())

        val body = buildJsonObject {
            put("points", buildJsonArray { events.forEach { add(Json.parseToJsonElement(it.payload)) } })
        }
        val url = batchUrl(config)
        val startedAt = System.currentTimeMillis()
        val request = Request.Builder()
            .url(url)
            .post(body.toString().toRequestBody(JSON_MEDIA_TYPE))
            .apply {
                config.headers.forEach { (key, value) -> addHeader(key, value) }
                addHeader("X-Device-Id", prefs.getDeviceId())
                addHeader("Accept", "application/json")
            }
            .build()

        try {
            client.newCall(request).execute().use { response ->
                if (!response.isSuccessful) {
                    if (response.code == 401 || response.code == 403) prefs.markAuthError(response.code)
                    val retryable = response.code == 408 || response.code == 409 || response.code == 429 || response.code >= 500
                    val error = "HTTP ${response.code}"
                    store.retryLater(events.map { it.id }, error)
                    prefs.setLocationUploadFailure(error)
                    if (writeLog) log(prefs, url, response.code, false, "Location batch failed: $error (${events.size} points)", events.size, startedAt)
                    return@use UploadReport(
                        failed = if (retryable) 0 else events.size,
                        pending = store.pendingCount(),
                        error = error,
                        retryable = retryable,
                    )
                }

                val results = runCatching {
                    Json.parseToJsonElement(response.body?.string().orEmpty())
                        .jsonObject["results"]?.jsonArray
                }.getOrNull()
                if (results == null || results.size != events.size) {
                    // Server contract changed or response truncated: keep the data.
                    store.release(events.map { it.id })
                    store.retryLater(events.map { it.id }, "unexpected_response")
                    prefs.setLocationUploadFailure("unexpected_response")
                    if (writeLog) log(prefs, url, response.code, false, "Location batch response incomplete, keeping ${events.size} points", events.size, startedAt)
                    return@use UploadReport(
                        pending = store.pendingCount(),
                        error = "unexpected_response",
                        retryable = true,
                    )
                }

                var accepted = 0
                var duplicate = 0
                var rejected = 0
                var unknown = 0
                events.forEachIndexed { index, event ->
                    val status = results[index].jsonObject["status"]?.jsonPrimitive?.content
                    when (status) {
                        "accepted" -> {
                            accepted++
                            store.settle(event.eventId, LocationEventStore.STATE_ACCEPTED)
                        }
                        "duplicate" -> {
                            duplicate++
                            store.settle(event.eventId, LocationEventStore.STATE_DUPLICATE)
                        }
                        "rejected" -> {
                            rejected++
                            store.settle(event.eventId, LocationEventStore.STATE_REJECTED, "rejected")
                        }
                        else -> {
                            // Unknown or missing status: retry rather than discard.
                            unknown++
                            store.retryLater(listOf(event.id), "unknown_status")
                        }
                    }
                }
                val pending = store.pendingCount()
                if (unknown == 0) prefs.setLocationUploadSuccess()
                if (writeLog) log(
                    prefs,
                    url,
                    200,
                    unknown == 0,
                    "Location batch: $accepted accepted, $duplicate duplicate, $rejected rejected, $unknown retried",
                    events.size,
                    startedAt,
                )
                UploadReport(
                    accepted = accepted,
                    duplicate = duplicate,
                    rejected = rejected,
                    pending = pending,
                    error = if (unknown == 0) null else "unknown_status",
                    retryable = unknown > 0,
                )
            }
        } catch (e: IOException) {
            store.retryLater(events.map { it.id }, e.message ?: "network")
            prefs.setLocationUploadFailure(e.message ?: "network")
            if (writeLog) log(prefs, url, null, false, "Location batch failed: ${e.message ?: "network"} (${events.size} points)", events.size, startedAt)
            UploadReport(
                pending = store.pendingCount(),
                error = e.message ?: "network",
                retryable = true,
            )
        }
    }

    /**
     * Writes one row per batch to the log screen, so a user can tell whether the
     * recorded coordinates really reached the server.
     */
    private fun log(
        prefs: PreferencesManager,
        url: String,
        statusCode: Int?,
        success: Boolean,
        message: String,
        recordCount: Int,
        startedAt: Long,
    ) {
        prefs.addWebhookLog(
            WebhookLog(
                id = UUID.randomUUID().toString(),
                timestamp = System.currentTimeMillis(),
                url = url,
                statusCode = statusCode,
                success = success,
                errorMessage = message,
                dataType = "location",
                recordCount = recordCount,
                responseTimeMs = System.currentTimeMillis() - startedAt,
                syncType = "location",
                deliveryFormat = WebhookLog.FORMAT_JSON,
            )
        )
    }

    /**
     * Builds the endpoint from the stored config the same way the rest of the
     * app does: only scheme and host are reused, so a pairing URL that already
     * contains `/api/steps` is not duplicated.
     */
    private fun batchUrl(config: WebhookConfig): String {
        val uri = Uri.parse(config.url)
        val base = if (uri.scheme != null && uri.authority != null) {
            "${uri.scheme}://${uri.authority}"
        } else {
            config.url.trimEnd('/')
        }
        return "$base/api/steps/geolocation?action=batch"
    }

    companion object {
        private val JSON_MEDIA_TYPE = "application/json; charset=utf-8".toMediaType()
    }
}
