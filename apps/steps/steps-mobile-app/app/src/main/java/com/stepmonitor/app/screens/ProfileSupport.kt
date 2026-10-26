package com.stepmonitor.app.screens

import com.stepmonitor.app.PreferencesManager
import com.stepmonitor.app.WebhookConfig
import com.stepmonitor.app.components.absoluteProfileUrl
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONObject

/** Profile data as returned by the server `GET /profile` endpoint. */
data class ServerProfile(
    val name: String = "",
    val email: String = "",
    val photo: String = "",
    val error: String? = null,
)

/** How long a cached profile stays valid before it must be re-fetched. */
const val PROFILE_CACHE_MAX_AGE_MS = 24L * 60 * 60 * 1000

/** Minimum age of the cached profile before a screen tries to refresh it. */
const val PROFILE_REFRESH_MAX_AGE_MS = 10L * 60 * 1000

/** Builds an authenticated OkHttp request for an app endpoint. */
internal fun stepsRequest(
    config: WebhookConfig,
    prefs: PreferencesManager,
    url: String,
): Request.Builder {
    val builder = Request.Builder().url(url)
    config.headers.forEach { (key, value) -> builder.addHeader(key, value) }
    builder.addHeader("X-Device-Id", prefs.getDeviceId())
    return builder
}

internal fun profileFromJson(json: String?): ServerProfile? = runCatching {
    if (json.isNullOrBlank()) null
    else JSONObject(json).let {
        ServerProfile(it.optString("name"), it.optString("email"), it.optString("photo"))
    }
}.getOrNull()

internal suspend fun fetchProfile(
    config: WebhookConfig?,
    prefs: PreferencesManager,
): ServerProfile = withContext(Dispatchers.IO) {
    if (config == null) return@withContext ServerProfile()
    runCatching {
        val profileUrl = config.url.trimEnd('/') + "/profile"
        OkHttpClient().newCall(stepsRequest(config, prefs, profileUrl).build()).execute().use { response ->
            if (response.code == 401 || response.code == 403) prefs.markAuthError(response.code)
            if (!response.isSuccessful) error("HTTP ${response.code}")
            val profile = JSONObject(response.body?.string().orEmpty()).optJSONObject("profile") ?: JSONObject()
            val result = ServerProfile(
                name = profile.optString("name"),
                email = profile.optString("email"),
                photo = absoluteProfileUrl(config.url, profile.optString("photo")),
            )
            prefs.setProfileCache(
                JSONObject()
                    .put("name", result.name)
                    .put("email", result.email)
                    .put("photo", result.photo)
                    .toString()
            )
            // A successful profile fetch is proof that the pairing is alive, even
            // when there is no new health data to upload.
            prefs.markSyncSuccess()
            result
        }
    }.getOrElse { ServerProfile(error = it.message) }
}
