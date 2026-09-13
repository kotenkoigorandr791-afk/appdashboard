package com.stepmonitor.app.components

import android.content.Context
import android.net.Uri
import com.stepmonitor.app.PreferencesManager
import org.json.JSONObject
import java.io.File

fun localAvatarFile(context: Context): File = File(context.filesDir, "avatar_cache.jpg")

/**
 * The profile photo URL taken from the cached server profile, matching how the
 * Settings screen displays it. Used as a fallback when the local avatar file
 * has not been downloaded yet.
 */
fun cachedProfilePhotoUrl(context: Context): String? {
    val json = PreferencesManager(context).getProfileCache(Long.MAX_VALUE) ?: return null
    return runCatching {
        JSONObject(json).optString("photo").takeIf { it.isNotBlank() }
    }.getOrNull()
}

fun absoluteProfileUrl(endpoint: String, photo: String): String {
    if (photo.isBlank() || photo.startsWith("http")) return photo
    val uri = Uri.parse(endpoint)
    return "${uri.scheme}://${uri.authority}/${photo.trimStart('/')}"
}

fun downloadAvatarFile(context: Context, url: String) {
    runCatching {
        val req = okhttp3.Request.Builder().url(url).build()
        okhttp3.OkHttpClient().newCall(req).execute().use { resp ->
            if (resp.isSuccessful) resp.body?.bytes()?.let { localAvatarFile(context).writeBytes(it) }
        }
    }
}