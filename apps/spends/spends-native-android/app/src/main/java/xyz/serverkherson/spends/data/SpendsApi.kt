package xyz.serverkherson.spends.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.booleanOrNull
import kotlinx.serialization.json.doubleOrNull
import kotlinx.serialization.json.intOrNull
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import kotlinx.serialization.json.longOrNull
import okhttp3.FormBody
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import java.util.concurrent.TimeUnit

class SpendsApi {
    private val client = OkHttpClient.Builder().connectTimeout(15, TimeUnit.SECONDS).readTimeout(45, TimeUnit.SECONDS).build()
    private val json = Json { ignoreUnknownKeys = true }

    fun endpoint(raw: String): String {
        var value = raw.trim().trimEnd('/')
        if (!value.startsWith("http://") && !value.startsWith("https://")) value = "http://$value"
        return when {
            value.endsWith("/api/spends") || value.endsWith("/public-api/spends") -> value
            value.endsWith("/api") || value.endsWith("/public-api") -> "$value/spends"
            else -> "$value/api/spends"
        }
    }

    suspend fun profile(endpoint: String, token: String): Session = withContext(Dispatchers.IO) {
        val body = request("GET", "${endpoint(endpoint)}/profile", token)
        val profile = json.parseToJsonElement(body).jsonObject["profile"]?.jsonObject ?: error("Invalid profile")
        Session(endpoint(endpoint), token, profile.text("email"), profile.text("name"), profile.text("image_url"))
    }

    suspend fun pull(session: Session): Snapshot = withContext(Dispatchers.IO) {
        val root = json.parseToJsonElement(request("GET", "${session.endpoint}/sync", session.token)).jsonObject
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
            Store(o.text("id"), o.text("name"), o.text("category", "other"), o["visible"]?.jsonPrimitive?.booleanOrNull != false, o.text("photo_url"))
        }
        val cats = root["store_categories"]?.jsonArray.orEmpty().map { it.jsonPrimitive.content }
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
            expenses, incomes, stores, cats, years, subscriptions, goals, tags,
            SettingsFlags(settings?.number("monthly_budget") ?: 0.0, settings?.text("currency", "USD") ?: "USD", settings?.flag("show_incomes") ?: true, settings?.flag("show_trend") ?: false, settings?.flag("trim_empty_months") ?: false),
            System.currentTimeMillis(),
        )
    }

    suspend fun pushJson(session: Session, body: String) = withContext(Dispatchers.IO) {
        val result = json.parseToJsonElement(request("POST", "${session.endpoint}/sync", session.token, body)).jsonObject
        if (result.text("status") != "success") error(result.text("message", "Sync rejected"))
    }

    suspend fun form(session: Session, fields: Map<String, String>, query: String = ""): JsonObject = withContext(Dispatchers.IO) {
        val url = session.endpoint + if (query.isBlank()) "" else "?$query"
        val form = FormBody.Builder().also { b -> fields.forEach { (k, v) -> b.add(k, v) } }.build()
        val req = Request.Builder().url(url).header("Authorization", "Bearer ${session.token}").header("Accept", "application/json").post(form).build()
        client.newCall(req).execute().use { response ->
            val text = response.body?.string().orEmpty()
            if (!response.isSuccessful) error("Server error (HTTP ${response.code})")
            json.parseToJsonElement(text).jsonObject
        }
    }

    suspend fun get(session: Session, query: String): JsonObject = withContext(Dispatchers.IO) {
        json.parseToJsonElement(request("GET", "${session.endpoint}?$query", session.token)).jsonObject
    }

    suspend fun rates(session: Session, base: String): Rates = withContext(Dispatchers.IO) {
        val o = json.parseToJsonElement(request("GET", "${session.endpoint}?get_exchange_rates=1&base=$base", session.token)).jsonObject
        if (o.text("status") != "success") error(o.text("message", "Rates unavailable"))
        Rates(o.text("base", base), o["rates"]?.jsonObject?.mapValues { it.value.jsonPrimitive.doubleOrNull ?: 0.0 } ?: emptyMap())
    }

    suspend fun backups(session: Session): List<BackupFile> {
        val o = form(session, mapOf("backup_action" to "list"))
        return o["backups"]?.jsonArray.orEmpty().map {
            val b = it.jsonObject
            BackupFile(b.text("name"), b["size"]?.jsonPrimitive?.longOrNull ?: 0, b["mtime"]?.jsonPrimitive?.longOrNull ?: 0)
        }
    }

    private fun request(method: String, url: String, token: String, body: String? = null): String {
        val builder = Request.Builder().url(url).header("Authorization", "Bearer $token").header("Accept", "application/json")
        if (body == null) builder.get() else builder.method(method, body.toRequestBody("application/json; charset=utf-8".toMediaType()))
        return client.newCall(builder.build()).execute().use { response ->
            val result = response.body?.string().orEmpty()
            if (!response.isSuccessful) error("Server error (HTTP ${response.code})")
            result
        }
    }

    private fun JsonObject.text(name: String, fallback: String = "") = this[name]?.jsonPrimitive?.content ?: fallback
    private fun JsonObject.number(name: String) = this[name]?.jsonPrimitive?.doubleOrNull ?: 0.0
    private fun JsonObject.flag(name: String): Boolean? = this[name]?.jsonPrimitive?.booleanOrNull ?: this[name]?.jsonPrimitive?.content?.let { it == "1" || it.equals("true", true) }
    private fun JsonObject.toGoal() = Goal(text("id"), text("name"), text("description"), number("target_amount"), number("current_amount"), text("currency", "USD"), text("target_date"), text("goal_type", "savings"), this["is_archived"]?.jsonPrimitive?.booleanOrNull == true, number("progress_percent"))
    private fun String.escape() = replace("\\", "\\\\").replace("\"", "\\\"")
    fun expenseJson(e: Expense, mode: String = "force") = """{"date":"${e.date.escape()}","store":"${e.store.escape()}","amount":${e.amount},"source":"${e.entrySource.escape()}","mode":"$mode","raw":"${e.ocrRawStore.escape()}"}"""
    fun incomeJson(i: Income, mode: String = "force") = """{"date":"${i.date.escape()}","amount":${i.amount},"mode":"$mode"}"""
}
