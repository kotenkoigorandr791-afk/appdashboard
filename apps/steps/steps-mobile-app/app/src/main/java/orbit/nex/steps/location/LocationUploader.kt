package orbit.nex.steps.location

import android.content.Context
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
import java.io.IOException

/** Result of one upload attempt; [error] is null when the batch was accepted. */
data class UploadReport(
    val accepted: Int = 0,
    val duplicate: Int = 0,
    val rejected: Int = 0,
    val pending: Int = 0,
    val error: String? = null,
    val retryable: Boolean = false,
)

/**
 * Uploads the local outbox to the Steps ingestion endpoint from tasks 04/05.
 *
 * The endpoint is derived from the paired server URL, exactly like the other
 * app calls, so no extra host or transport setting is introduced. Accepted,
 * duplicate and rejected items are removed from the outbox; network failures
 * leave them queued for the next attempt.
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
    ): UploadReport = withContext(Dispatchers.IO) {
        val store = LocationEventStore(appContext)
        store.purgeUploaded()
        val events = store.pending(limit)
        if (events.isEmpty()) return@withContext UploadReport(pending = 0)

        val body = buildJsonObject {
            put("points", buildJsonArray { events.forEach { add(Json.parseToJsonElement(it.payload)) } })
        }
        val url = config.url.trimEnd('/') + "/api/steps/geolocation?action=batch"
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
                    val retryable = response.code == 408 || response.code == 429 || response.code >= 500
                    store.markAttempt(events.map { it.id })
                    val error = "HTTP ${response.code}"
                    prefs.setLocationUploadFailure(error)
                    return@use UploadReport(
                        pending = store.pendingCount(),
                        error = error,
                        retryable = retryable,
                    )
                }

                val results = runCatching {
                    Json.parseToJsonElement(response.body?.string().orEmpty())
                        .jsonObject["results"]?.jsonArray
                }.getOrNull()
                val uploadedIds = mutableListOf<Long>()
                var accepted = 0
                var duplicate = 0
                var rejected = 0
                events.forEachIndexed { index, event ->
                    val status = results?.getOrNull(index)
                        ?.jsonObject?.get("status")?.jsonPrimitive?.content
                        ?: "rejected"
                    when (status) {
                        "accepted" -> {
                            accepted++
                            uploadedIds += event.id
                        }
                        "duplicate" -> {
                            duplicate++
                            uploadedIds += event.id
                        }
                        else -> {
                            // A permanently invalid point must not block the queue.
                            rejected++
                            uploadedIds += event.id
                        }
                    }
                }
                store.markUploaded(uploadedIds)
                val pending = store.pendingCount()
                prefs.setLocationUploadSuccess()
                UploadReport(
                    accepted = accepted,
                    duplicate = duplicate,
                    rejected = rejected,
                    pending = pending,
                )
            }
        } catch (e: IOException) {
            store.markAttempt(events.map { it.id })
            prefs.setLocationUploadFailure(e.message ?: "network")
            UploadReport(
                pending = store.pendingCount(),
                error = e.message ?: "network",
                retryable = true,
            )
        }
    }

    companion object {
        private val JSON_MEDIA_TYPE = "application/json; charset=utf-8".toMediaType()
    }
}
