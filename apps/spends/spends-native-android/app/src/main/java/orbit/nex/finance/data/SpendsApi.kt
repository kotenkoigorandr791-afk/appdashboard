package orbit.nex.finance.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.booleanOrNull
import kotlinx.serialization.json.contentOrNull
import kotlinx.serialization.json.doubleOrNull
import kotlinx.serialization.json.intOrNull
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import kotlinx.serialization.json.longOrNull
import okhttp3.FormBody
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.IOException
import java.net.ConnectException
import java.net.SocketTimeoutException
import java.net.UnknownHostException
import java.util.concurrent.TimeUnit

/** Raised for DNS/connect/timeout failures so the UI never shows raw socket text. */
class OfflineException : IOException("offline")

private fun isNetworkFailure(error: Throwable?): Boolean {
    var current = error
    while (current != null) {
        if (current is UnknownHostException || current is ConnectException || current is SocketTimeoutException) return true
        if (current is IOException && current !is OfflineException) {
            val message = current.message.orEmpty().lowercase()
            if (message.contains("resolve host", true) 
                || message.contains("failed to connect", true) 
                || message.contains("timeout", true)
                || message.contains("unexpected end of stream", true)
                || message.contains("connection reset", true)
                || message.contains("broken pipe", true)
                || message.contains("eof", true)) return true
        }
        current = current.cause
    }
    return false
}

class SpendsApi(private val deviceId: String, private val onLog: (SyncLog) -> Unit = {}) {
    private val client = OkHttpClient.Builder().connectTimeout(15, TimeUnit.SECONDS).readTimeout(45, TimeUnit.SECONDS).build()
    private val json = Json { ignoreUnknownKeys = true }

    fun endpoint(raw: String): String {
        var value = raw.trim().trimEnd('/')
        if (!value.startsWith("https://") && !value.startsWith("http://")) value = "https://$value"
        return when {
            value.endsWith("/api/spends") || value.endsWith("/public-api/spends") -> value
            value.endsWith("/api") || value.endsWith("/public-api") -> "$value/spends"
            else -> "$value/api/spends"
        }
    }

    suspend fun profile(endpoint: String, token: String): Session = withContext(Dispatchers.IO) {
        val body = request("GET", "${endpoint(endpoint)}/profile", token)
        val profile = json.parseToJsonElement(body).jsonObject["profile"]?.jsonObject ?: error("Invalid profile")
        val ep = endpoint(endpoint)
        Session(ep, token, profile.text("email"), profile.text("name"), orbit.nex.finance.ui.rebaseUrl(profile.text("image_url"), ep))
    }

    suspend fun pull(session: Session, previous: Snapshot = Snapshot()): Snapshot = withContext(Dispatchers.IO) {
        val revision = previous.syncRevision.takeIf { it.isNotBlank() }?.let { "?revision=$it" }.orEmpty()
        val root = json.parseToJsonElement(request("GET", "${session.endpoint}/sync$revision", session.token)).jsonObject
        if (root.text("status") == "not_modified") return@withContext previous.copy(syncedAt = System.currentTimeMillis())
        if (root.text("status") != "success") error("Sync failed")
        val settings = root["settings"]?.jsonObject
        val expenses = root["expenses"]?.jsonArray.orEmpty().map {
            val o = it.jsonObject
            Expense(o.text("date"), o.text("store"), o.number("amount"), o.text("signature"), o.text("entry_source", "manual"), o.text("ocr_raw_store"))
        }
        val incomes = root["incomes"]?.jsonArray.orEmpty().map {
            val o = it.jsonObject
            Income(o.text("date"), o.number("amount"), o.text("signature"))
        }
        val stores = root["stores"]?.jsonArray.orEmpty().map {
            val o = it.jsonObject
            Store(o.text("id"), o.text("name"), o.text("category", "other"), o["visible"]?.jsonPrimitive?.booleanOrNull != false, orbit.nex.finance.ui.rebaseUrl(o.text("photo_url"), session.endpoint))
        }
        val cats = root["store_categories"]?.jsonArray.orEmpty().map { it.jsonPrimitive.content }
        // PHP encodes an empty map as [] — accept both shapes.
        val expenseTags = (root["expense_tags"] as? JsonObject)?.mapValues { (_, value) ->
            (value as? JsonArray)?.mapNotNull { it.jsonPrimitive.contentOrNull } ?: emptyList()
        } ?: emptyMap()
        val years = root["years"]?.jsonArray.orEmpty().mapNotNull { it.jsonPrimitive.intOrNull }
        val subscriptions = root["subscriptions"]?.jsonArray.orEmpty().map {
            val o = it.jsonObject
            Subscription(o.text("id"), o.text("store"), o.number("amount"), o.text("frequency", "monthly"), o["day_of_month"]?.jsonPrimitive?.intOrNull ?: 1, o["interval_days"]?.jsonPrimitive?.intOrNull ?: 0, o.text("start_date"), o.text("end_date"), o.text("last_run"))
        }
        val goals = root["goals"]?.jsonArray.orEmpty().map { it.jsonObject.toGoal() }
        val tags = root["tags"]?.jsonArray.orEmpty().map {
            val o = it.jsonObject
            Tag(o.text("id"), o.text("name"), o.text("color"), o["usage_count"]?.jsonPrimitive?.intOrNull ?: 0)
        }
        Snapshot(
            settings?.text("currency", "USD") ?: "USD",
            settings?.number("monthly_budget") ?: 0.0,
            expenses, incomes, stores, cats, years, subscriptions, goals, tags, expenseTags,
            SettingsFlags(settings?.number("monthly_budget") ?: 0.0, settings?.text("currency", "USD") ?: "USD", settings?.flag("show_incomes") ?: true, settings?.flag("show_trend") ?: false, settings?.flag("trim_empty_months") ?: false),
            System.currentTimeMillis(),
            root.text("revision"),
        )
    }

    /** Replaces the tag ids attached to one payment (date + store). */
    suspend fun setExpenseTags(session: Session, date: String, store: String, tagIds: List<String>) = withContext(Dispatchers.IO) {
        val tagsJson = tagIds.joinToString(",") { "\"${it.escape()}\"" }
        val body = """{"expense_tags":[{"date":"${date.escape()}","store":"${store.escape()}","tags":[$tagsJson]}]}"""
        pushJson(session, body)
    }

    suspend fun pushJson(session: Session, body: String) = withContext(Dispatchers.IO) {
        val result = json.parseToJsonElement(request("POST", "${session.endpoint}/sync", session.token, body)).jsonObject
        if (result.text("status") != "success") error(result.text("message", "Sync rejected"))
        // The server reports per-row problems without failing the request. Keep
        // the write queued so it is retried instead of silently disappearing.
        val rowErrors = result["errors"]?.jsonArray
        if (!rowErrors.isNullOrEmpty()) error(result.text("message", "Sync rejected"))
    }

    suspend fun form(session: Session, fields: Map<String, String>, query: String = ""): JsonObject = withContext(Dispatchers.IO) {
        val url = session.endpoint + if (query.isBlank()) "" else "?$query"
        val form = FormBody.Builder().also { b -> fields.forEach { (k, v) -> b.add(k, v) } }.build()
        val req = Request.Builder().url(url).header("Authorization", "Bearer ${session.token}").header("X-Device-Id", deviceId).header("Accept", "application/json").post(form).build()
        execute(req, "POST", url, fields.toString())
    }

    suspend fun get(session: Session, query: String): JsonObject = withContext(Dispatchers.IO) {
        json.parseToJsonElement(request("GET", "${session.endpoint}?$query", session.token)).jsonObject
    }

    suspend fun rates(session: Session, base: String): Rates = withContext(Dispatchers.IO) {
        val o = json.parseToJsonElement(request("GET", "${session.endpoint}?get_exchange_rates=1&base=$base", session.token)).jsonObject
        if (o.text("status") != "success") error(o.text("message", "Rates unavailable"))
        val history = runCatching { rateHistory(session, base) }.getOrDefault(emptyMap())
        Rates(
            base = o.text("base", base),
            rates = o["rates"]?.jsonObject?.mapValues { it.value.jsonPrimitive.doubleOrNull ?: 0.0 } ?: emptyMap(),
            updatedAt = o["timestamp"]?.jsonPrimitive?.longOrNull ?: System.currentTimeMillis() / 1000,
            history = history,
        )
    }

    /** Daily series per currency for the sparkline charts; empty when the server has no history. */
    private suspend fun rateHistory(session: Session, base: String, days: Int = 14): Map<String, List<Double>> =
        withContext(Dispatchers.IO) {
            val o = json.parseToJsonElement(request("GET", "${session.endpoint}?get_exchange_history=1&base=$base&days=$days", session.token)).jsonObject
            if (o.text("status") != "success") return@withContext emptyMap()
            val history = o["history"]?.jsonObject ?: return@withContext emptyMap()
            history.mapValues { (_, series) ->
                (series as? JsonObject)?.values?.mapNotNull { it.jsonPrimitive?.doubleOrNull } ?: emptyList()
            }.filterValues { it.size > 1 }
        }

    /** Creates or updates a store, uploading the photo as multipart exactly like the web UI. */
    suspend fun saveStore(
        session: Session,
        id: String,
        name: String,
        category: String,
        photo: ByteArray?,
    ): JsonObject = withContext(Dispatchers.IO) {
        val builder = MultipartBody.Builder().setType(MultipartBody.FORM)
            .addFormDataPart("add_edit_store", "1")
            .addFormDataPart("store_id", id)
            .addFormDataPart("store_name", name)
            .addFormDataPart("store_category", category)
        if (photo != null && photo.isNotEmpty()) {
            builder.addFormDataPart("store_photo", "store_photo.png", photo.toRequestBody("image/png".toMediaType()))
        }
        val req = Request.Builder()
            .url(session.endpoint)
            .header("Authorization", "Bearer ${session.token}")
            .header("X-Device-Id", deviceId)
            .header("Accept", "application/json")
            .post(builder.build())
            .build()
        execute(req, "POST", session.endpoint, "store $name")
    }

    suspend fun backups(session: Session): List<BackupFile> {
        val o = form(session, mapOf("backup_action" to "list"))
        return o["backups"]?.jsonArray.orEmpty().map {
            val b = it.jsonObject
            BackupFile(b.text("name"), b["size"]?.jsonPrimitive?.longOrNull ?: 0, b["mtime"]?.jsonPrimitive?.longOrNull ?: 0)
        }
    }

    private fun request(method: String, url: String, token: String, body: String? = null): String {
        val builder = Request.Builder().url(url).header("Authorization", "Bearer $token").header("X-Device-Id", deviceId).header("Accept", "application/json")
        if (body == null) builder.get() else builder.method(method, body.toRequestBody("application/json; charset=utf-8".toMediaType()))
        val obj = execute(builder.build(), method, url, body)
        return obj.toString()
    }

    private fun execute(req: Request, method: String, url: String, payload: String?): JsonObject {
        return try {
            var lastError: Exception? = null
            repeat(if (method == "GET") 3 else 1) { attempt ->
                try {
                    client.newCall(req).execute().use { response ->
                        // Any HTTP answer proves the server itself is reachable.
                        NetworkState.markReachable()
                        val text = response.body?.string().orEmpty()
                        if (!response.isSuccessful && response.code >= 500 && attempt < 2) {
                            Thread.sleep(200L * (attempt + 1))
                            return@use
                        }
                        onLog(SyncLog(url = url, method = method, success = response.isSuccessful, statusCode = response.code, errorMessage = if (response.isSuccessful) null else text.take(800), payload = payload?.take(1200)))
                        if (!response.isSuccessful) error("Server error (HTTP ${response.code})")
                        // Some endpoints prepend notices/whitespace to their JSON;
                        // parse the object itself instead of failing the whole call.
                        val trimmed = text.trim().removePrefix("\uFEFF").trim()
                        val start = trimmed.indexOf('{')
                        val end = trimmed.lastIndexOf('}')
                        val candidate = if (start >= 0 && end > start) trimmed.substring(start, end + 1) else trimmed
                        return runCatching { json.parseToJsonElement(candidate).jsonObject }
                            .getOrElse { JsonObject(emptyMap()) }
                    }
                } catch (e: Exception) {
                    lastError = e
                    if (attempt < 2 && method == "GET") Thread.sleep(200L * (attempt + 1))
                }
            }
            throw (lastError ?: IllegalStateException("Server error"))
        } catch (e: Exception) {
            val network = isNetworkFailure(e)
            if (network) {
                NetworkState.markUnreachable()
                onLog(SyncLog(url = url, method = method, success = false, errorMessage = "offline", payload = payload?.take(1200)))
                throw OfflineException()
            }
            if (e.message?.startsWith("Server error") != true) onLog(SyncLog(url = url, method = method, success = false, errorMessage = e.message, payload = payload?.take(1200)))
            throw e
        }
    }

    private fun JsonObject.text(name: String, fallback: String = "") = this[name]?.jsonPrimitive?.content ?: fallback
    private fun JsonObject.number(name: String) = this[name]?.jsonPrimitive?.doubleOrNull ?: 0.0
    private fun JsonObject.flag(name: String): Boolean? = this[name]?.jsonPrimitive?.booleanOrNull ?: this[name]?.jsonPrimitive?.content?.let { it == "1" || it.equals("true", true) }
    private fun JsonObject.toGoal() = Goal(text("id"), text("name"), text("description"), number("target_amount"), number("current_amount"), text("currency", "USD"), text("target_date"), text("goal_type", "savings"), this["is_archived"]?.jsonPrimitive?.booleanOrNull == true, number("progress_percent"))
    private fun String.escape() = replace("\\", "\\\\").replace("\"", "\\\"")
    fun expenseJson(e: Expense, mode: String = "force", signature: String? = null) = """{"date":"${e.date.escape()}","store":"${e.store.escape()}","amount":${e.amount},"source":"${e.entrySource.escape()}","mode":"$mode","raw":"${e.ocrRawStore.escape()}"${signature?.let { ",\"signature\":\"${it.escape()}\"" } ?: ""}}"""
    fun incomeJson(i: Income, mode: String = "force", signature: String? = null) = """{"date":"${i.date.escape()}","amount":${i.amount},"mode":"$mode"${signature?.let { ",\"signature\":\"${it.escape()}\"" } ?: ""}}"""

    // The router exposes the OCR worker under /ocr/{action}; a deep .php path
    // below /api is not routed and used to answer 404.
    private fun ocrUrl(session: Session, api: String) = "${session.endpoint}/ocr/$api"

    suspend fun ocrStart(session: Session, files: List<Pair<String, ByteArray>>): JsonObject = withContext(Dispatchers.IO) {
        val body = MultipartBody.Builder().setType(MultipartBody.FORM)
        files.forEach { (name, bytes) ->
            body.addFormDataPart("photos[]", name, bytes.toRequestBody("image/jpeg".toMediaType()))
        }
        val req = Request.Builder().url(ocrUrl(session, "start")).header("Authorization", "Bearer ${session.token}").header("X-Device-Id", deviceId).header("Accept", "application/json").post(body.build()).build()
        execute(req, "POST", ocrUrl(session, "start"), "${files.size} files")
    }

    suspend fun ocrStatus(session: Session, jobId: String): JsonObject = withContext(Dispatchers.IO) {
        json.parseToJsonElement(request("GET", ocrUrl(session, "status") + "?job_id=$jobId", session.token)).jsonObject
    }

    suspend fun ocrResolve(session: Session, jobId: String, map: Map<String, String>): JsonObject = withContext(Dispatchers.IO) {
        val payload = """{"job_id":"${jobId.escape()}","map":{${map.entries.joinToString(",") { "\"${it.key.escape()}\":\"${it.value.escape()}\"" }}},"skip":[]}"""
        json.parseToJsonElement(request("POST", ocrUrl(session, "resolve"), session.token, payload)).jsonObject
    }

    suspend fun ocrFinalize(session: Session, jobId: String): JsonObject = withContext(Dispatchers.IO) {
        json.parseToJsonElement(request("POST", ocrUrl(session, "finalize"), session.token, """{"job_id":"${jobId.escape()}"}""")).jsonObject
    }
}
