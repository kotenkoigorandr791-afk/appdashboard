package com.appdashboard.planning.data.prefs

import android.content.Context
import java.util.UUID

enum class ViewMode { LIST, BOARD, TABLE }

enum class ThemeMode { LIGHT, DARK, AUTO }

class SessionStore(context: Context) {
    private val prefs = context.getSharedPreferences("planning_session", Context.MODE_PRIVATE)

    fun isPaired(): Boolean = !endpoint().isNullOrBlank() && !token().isNullOrBlank()

    fun endpoint(): String? = prefs.getString(KEY_ENDPOINT, null)
    fun token(): String? = prefs.getString(KEY_TOKEN, null)
    fun userName(): String? = prefs.getString(KEY_USER, null)
    fun userEmail(): String? = prefs.getString(KEY_EMAIL, null)
    fun userRole(): String? = prefs.getString(KEY_ROLE, null)
    fun selectedProjectId(): Int = prefs.getInt(KEY_PROJECT, 0)
    fun viewMode(): ViewMode = runCatching {
        ViewMode.valueOf(prefs.getString(KEY_VIEW, ViewMode.LIST.name) ?: ViewMode.LIST.name)
    }.getOrDefault(ViewMode.LIST)
    fun themeMode(): ThemeMode = runCatching {
        ThemeMode.valueOf(prefs.getString(KEY_THEME, ThemeMode.AUTO.name) ?: ThemeMode.AUTO.name)
    }.getOrDefault(ThemeMode.AUTO)
    fun translucentUi(): Boolean = prefs.getBoolean(KEY_GLASS, true)

    fun deviceId(): String {
        val existing = prefs.getString(KEY_DEVICE, null)
        if (!existing.isNullOrBlank()) return existing
        val id = UUID.randomUUID().toString().replace("-", "")
        prefs.edit().putString(KEY_DEVICE, id).apply()
        return id
    }

    fun pair(endpoint: String, token: String) {
        prefs.edit()
            .putString(KEY_ENDPOINT, endpoint.trim().trimEnd('/'))
            .putString(KEY_TOKEN, token.trim())
            .apply()
    }

    fun setUser(name: String?, email: String?, role: String?) {
        prefs.edit()
            .putString(KEY_USER, name)
            .putString(KEY_EMAIL, email)
            .putString(KEY_ROLE, role)
            .apply()
    }

    fun setSelectedProjectId(id: Int) {
        prefs.edit().putInt(KEY_PROJECT, id).apply()
    }

    fun setViewMode(mode: ViewMode) {
        prefs.edit().putString(KEY_VIEW, mode.name).apply()
    }

    fun setThemeMode(mode: ThemeMode) {
        prefs.edit().putString(KEY_THEME, mode.name).apply()
    }

    fun setTranslucentUi(enabled: Boolean) {
        prefs.edit().putBoolean(KEY_GLASS, enabled).apply()
    }

    fun clear() {
        prefs.edit()
            .remove(KEY_ENDPOINT)
            .remove(KEY_TOKEN)
            .remove(KEY_USER)
            .remove(KEY_EMAIL)
            .remove(KEY_ROLE)
            .remove(KEY_PROJECT)
            .apply()
    }

    companion object {
        private const val KEY_ENDPOINT = "endpoint"
        private const val KEY_TOKEN = "token"
        private const val KEY_DEVICE = "device_id"
        private const val KEY_USER = "user_name"
        private const val KEY_EMAIL = "user_email"
        private const val KEY_ROLE = "user_role"
        private const val KEY_PROJECT = "selected_project"
        private const val KEY_VIEW = "view_mode"
        private const val KEY_THEME = "theme_mode"
        private const val KEY_GLASS = "translucent_ui"
    }
}
