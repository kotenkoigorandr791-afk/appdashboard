package com.stepmonitor.app

import android.content.Context
import android.content.SharedPreferences
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.Json

enum class SyncMode {
    INTERVAL,    // Continuous sync at specified interval
    SCHEDULED    // Sync at specific times (morning & evening)
}

class PreferencesManager(context: Context) {

    private val prefs: SharedPreferences = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    fun getSharedPreferences(): SharedPreferences = prefs

    companion object {
        private const val PREFS_NAME = "hc_webhook_prefs"
        private const val KEY_LAST_SYNC_TS_PREFIX = "last_sync_ts_"
        private const val KEY_LAST_STEPS_SYNC_TS = "last_steps_sync_ts"
        private const val KEY_LAST_SLEEP_SYNC_TS = "last_sleep_sync_ts"
        private const val KEY_SYNC_INTERVAL_MINUTES = "sync_interval_minutes"
        private const val KEY_WEBHOOK_URLS = "webhook_urls"
        private const val KEY_WEBHOOK_CONFIGS = "webhook_configs"
        private const val KEY_ENABLED_DATA_TYPES = "enabled_data_types"
        private const val KEY_WEBHOOK_LOGS = "webhook_logs"
        private const val KEY_LAST_SYNC_TIME = "last_sync_time"
        private const val KEY_LAST_SYNC_ATTEMPT_MS = "last_sync_attempt_ms"
        private const val KEY_LAST_SYNC_SUMMARY = "last_sync_summary"
        private const val DEFAULT_SYNC_INTERVAL_MINUTES = 60
        private const val MAX_LOGS = 100
        private const val KEY_SCHEDULED_SYNC_ENABLED = "scheduled_sync_enabled"
        private const val KEY_MORNING_SYNC_HOUR = "morning_sync_hour"
        private const val KEY_MORNING_SYNC_MINUTE = "morning_sync_minute"
        private const val KEY_EVENING_SYNC_HOUR = "evening_sync_hour"
        private const val KEY_EVENING_SYNC_MINUTE = "evening_sync_minute"
        private const val KEY_SYNC_MODE = "sync_mode"
        private const val KEY_SCHEDULED_SYNCS = "scheduled_syncs"
        private const val KEY_KNOWN_GRANTED_PERMISSIONS = "known_granted_permissions"
        private const val KEY_HAS_SEEN_ONBOARDING = "has_seen_onboarding"
        private const val KEY_LAST_SEEN_VERSION_CODE = "last_seen_version_code"
        private const val KEY_LOCAL_TCP_ENABLED = "local_tcp_enabled"
        private const val KEY_LOCAL_TCP_PORT = "local_tcp_port"
        private const val DEFAULT_LOCAL_TCP_PORT = 8787
        private const val KEY_LOCAL_HTTP_AUTH_ENABLED = "local_http_auth_enabled"
        private const val KEY_LOCAL_HTTP_TOKEN = "local_http_token"
        private const val KEY_NOTIFICATION_CONFIGS = "notification_configs"
        private const val KEY_HR_DOWNSAMPLE_MINUTES = "hr_downsample_minutes"
        private const val KEY_STEPS_RESOLUTION_MINUTES = "steps_resolution_minutes"
        private const val KEY_DATA_TYPE_RESOLUTIONS = "data_type_resolutions"
        private const val KEY_BATTERY_BANNER_DISMISSED = "battery_banner_dismissed"
        private const val KEY_MAX_LOGS = "max_logs"
        private const val KEY_LOCAL_FEEDBACK = "local_feedback"
        private const val KEY_FIRST_OPEN_TIME = "first_open_time"
        private const val KEY_WEEKLY_FEEDBACK_PROMPT_DISMISSED = "weekly_feedback_prompt_dismissed"
        private const val KEY_WIFI_ONLY = "wifi_only"
        private const val KEY_THEME_MODE = "theme_mode"
        private const val KEY_AMOLED_ENABLED = "amoled_enabled"
        private const val KEY_ACCENT_COLOR = "accent_color"
        private const val KEY_APP_LANGUAGE = "app_language"
        private const val KEY_PORTRAIT_ONLY = "portrait_only"
        private const val KEY_STATS_CACHE_JSON = "stats_cache_json"
        private const val KEY_STATS_CACHE_TIME = "stats_cache_time"
        private const val KEY_PROFILE_CACHE_JSON = "profile_cache_json"
        private const val KEY_PROFILE_CACHE_TIME = "profile_cache_time"
        private const val KEY_ANDROID_STEPS_CACHE_PREFIX = "android_steps_cache_"
        private const val KEY_PENDING_CONFLICT_JSON = "pending_conflict_json"
        private const val KEY_CONFLICT_REMEMBER = "conflict_remember"
        private const val KEY_CONFLICT_DEFAULT_ACTION = "conflict_default_action"
        private const val WEEKLY_FEEDBACK_PROMPT_MS = 7L * 24 * 60 * 60 * 1000
        val MAX_LOG_OPTIONS = listOf(50, 100, 500)
        private const val DEFAULT_MAX_LOGS = 100
        private const val MAX_LOCAL_FEEDBACK = 50
    }


    fun getLastStepsSyncTimestamp(): Long? {
        val timestamp = prefs.getLong(KEY_LAST_STEPS_SYNC_TS, -1)
        return if (timestamp == -1L) null else timestamp
    }

    fun setLastStepsSyncTimestamp(timestamp: Long) {
        prefs.edit().putLong(KEY_LAST_STEPS_SYNC_TS, timestamp).apply()
    }

    fun getLastSleepSyncTimestamp(): Long? {
        val timestamp = prefs.getLong(KEY_LAST_SLEEP_SYNC_TS, -1)
        return if (timestamp == -1L) null else timestamp
    }

    fun setLastSleepSyncTimestamp(timestamp: Long) {
        prefs.edit().putLong(KEY_LAST_SLEEP_SYNC_TS, timestamp).apply()
    }

    fun getSyncIntervalMinutes(): Int {
        return prefs.getInt(KEY_SYNC_INTERVAL_MINUTES, DEFAULT_SYNC_INTERVAL_MINUTES)
    }

    fun setSyncIntervalMinutes(minutes: Int) {
        prefs.edit().putInt(KEY_SYNC_INTERVAL_MINUTES, minutes).apply()
    }

    fun getWebhookUrls(): List<String> {
        val urlsString = prefs.getString(KEY_WEBHOOK_URLS, "") ?: ""
        return if (urlsString.isEmpty()) emptyList() else urlsString.split(",")
    }

    fun setWebhookUrls(urls: List<String>) {
        val urlsString = urls.joinToString(",")
        prefs.edit().putString(KEY_WEBHOOK_URLS, urlsString).apply()
    }

    fun getWebhookConfigs(): List<WebhookConfig> {
        val configsJson = prefs.getString(KEY_WEBHOOK_CONFIGS, null)
        
        // If we have new format configs, use them
        if (configsJson != null) {
            return try {
                Json.decodeFromString<List<WebhookConfig>>(configsJson)
            } catch (e: Exception) {
                // If JSON parsing fails, fall back to old format
                getWebhookUrls().map { WebhookConfig.fromUrl(it) }
            }
        }
        
        // Fall back to old format for backward compatibility
        return getWebhookUrls().map { WebhookConfig.fromUrl(it) }
    }

    fun setWebhookConfigs(configs: List<WebhookConfig>) {
        val configsJson = Json.encodeToString(configs)
        prefs.edit().putString(KEY_WEBHOOK_CONFIGS, configsJson).apply()
        
        // Also update old format for backward compatibility
        val urls = configs.map { it.url }
        setWebhookUrls(urls)
    }


    fun getEnabledDataTypes(): Set<HealthDataType> {
        val typesString = prefs.getString(KEY_ENABLED_DATA_TYPES, "") ?: ""
        return if (typesString.isEmpty()) {
            emptySet()
        } else {
            typesString.split(",").mapNotNull {
                try { HealthDataType.valueOf(it) } catch (e: Exception) { null }
            }.toSet()
        }
    }

    fun setEnabledDataTypes(types: Set<HealthDataType>) {
        val typesString = types.joinToString(",") { it.name }
        prefs.edit().putString(KEY_ENABLED_DATA_TYPES, typesString).apply()
    }

    fun getKnownGrantedPermissions(): Set<String> {
        return prefs.getStringSet(KEY_KNOWN_GRANTED_PERMISSIONS, emptySet()) ?: emptySet()
    }

    fun setKnownGrantedPermissions(permissions: Set<String>) {
        prefs.edit().putStringSet(KEY_KNOWN_GRANTED_PERMISSIONS, permissions).apply()
    }

    fun getLastSyncTimestamp(type: HealthDataType): Long? {
        val timestamp = prefs.getLong(KEY_LAST_SYNC_TS_PREFIX + type.name, -1)
        return if (timestamp == -1L) null else timestamp
    }

    fun setLastSyncTimestamp(type: HealthDataType, timestamp: Long) {
        prefs.edit().putLong(KEY_LAST_SYNC_TS_PREFIX + type.name, timestamp).apply()
    }

    fun getWebhookLogs(): List<WebhookLog> {
        val logsJson = prefs.getString(KEY_WEBHOOK_LOGS, null) ?: return emptyList()
        return try {
            Json.decodeFromString<List<WebhookLog>>(logsJson)
        } catch (e: Exception) {
            emptyList()
        }
    }

    fun getMaxLogs(): Int = prefs.getInt(KEY_MAX_LOGS, DEFAULT_MAX_LOGS)

    fun setMaxLogs(max: Int) {
        prefs.edit().putInt(KEY_MAX_LOGS, max).apply()
        val current = getWebhookLogs()
        if (current.size > max) {
            prefs.edit().putString(KEY_WEBHOOK_LOGS, Json.encodeToString(current.take(max))).apply()
        }
    }

    fun addWebhookLog(log: WebhookLog) {
        val currentLogs = getWebhookLogs().toMutableList()
        currentLogs.add(0, log)
        val trimmedLogs = currentLogs.take(getMaxLogs())
        val logsJson = Json.encodeToString(trimmedLogs)
        prefs.edit().putString(KEY_WEBHOOK_LOGS, logsJson).apply()
    }

    fun removeWebhookLog(id: String) {
        val updated = getWebhookLogs().filter { it.id != id }
        val logsJson = Json.encodeToString(updated)
        prefs.edit().putString(KEY_WEBHOOK_LOGS, logsJson).apply()
    }

    fun removeWebhookLogs(ids: Set<String>) {
        val updated = getWebhookLogs().filter { it.id !in ids }
        prefs.edit().putString(KEY_WEBHOOK_LOGS, Json.encodeToString(updated)).apply()
    }

    fun clearWebhookLogs() {
        prefs.edit().remove(KEY_WEBHOOK_LOGS).apply()
    }

    fun isScheduledSyncEnabled(): Boolean {
        return prefs.getBoolean(KEY_SCHEDULED_SYNC_ENABLED, true)
    }

    fun setScheduledSyncEnabled(enabled: Boolean) {
        prefs.edit().putBoolean(KEY_SCHEDULED_SYNC_ENABLED, enabled).apply()
    }

    fun getMorningSyncHour(): Int = prefs.getInt(KEY_MORNING_SYNC_HOUR, 8)
    fun getMorningSyncMinute(): Int = prefs.getInt(KEY_MORNING_SYNC_MINUTE, 0)

    fun setMorningSyncTime(hour: Int, minute: Int) {
        prefs.edit()
            .putInt(KEY_MORNING_SYNC_HOUR, hour)
            .putInt(KEY_MORNING_SYNC_MINUTE, minute)
            .apply()
    }

    fun getEveningSyncHour(): Int = prefs.getInt(KEY_EVENING_SYNC_HOUR, 21)
    fun getEveningSyncMinute(): Int = prefs.getInt(KEY_EVENING_SYNC_MINUTE, 0)

    fun setEveningSyncTime(hour: Int, minute: Int) {
        prefs.edit()
            .putInt(KEY_EVENING_SYNC_HOUR, hour)
            .putInt(KEY_EVENING_SYNC_MINUTE, minute)
            .apply()
    }

    fun getSyncMode(): SyncMode {
        val modeString = prefs.getString(KEY_SYNC_MODE, SyncMode.INTERVAL.name) ?: SyncMode.INTERVAL.name
        return try {
            SyncMode.valueOf(modeString)
        } catch (e: Exception) {
            SyncMode.INTERVAL // Default to interval mode for backward compatibility
        }
    }

    fun setSyncMode(mode: SyncMode) {
        prefs.edit().putString(KEY_SYNC_MODE, mode.name).apply()
    }

    fun getScheduledSyncs(): List<ScheduledSync> {
        val syncsJson = prefs.getString(KEY_SCHEDULED_SYNCS, null)
        
        if (syncsJson != null) {
            return try {
                Json.decodeFromString<List<ScheduledSync>>(syncsJson)
            } catch (e: Exception) {
                // If JSON parsing fails, persist defaults so schedule IDs stay stable
                val defaults = getDefaultScheduledSyncs()
                setScheduledSyncs(defaults)
                defaults
            }
        }
        
        // For backward compatibility, migrate from old morning/evening settings
        val morningHour = getMorningSyncHour()
        val morningMinute = getMorningSyncMinute()
        val eveningHour = getEveningSyncHour()
        val eveningMinute = getEveningSyncMinute()
        
        // Check if these are default values (8:00 and 21:00)
        val isDefaultValues = morningHour == 8 && morningMinute == 0 && eveningHour == 21 && eveningMinute == 0
        
        val syncs = if (isDefaultValues) {
            getDefaultScheduledSyncs()
        } else {
            // Migrate user's custom times
            listOf(
                ScheduledSync.create(morningHour, morningMinute, "Morning"),
                ScheduledSync.create(eveningHour, eveningMinute, "Evening")
            )
        }
        // Persist so AlarmManager PendingIntent request codes stay stable across restarts
        setScheduledSyncs(syncs)
        return syncs
    }

    fun setScheduledSyncs(syncs: List<ScheduledSync>) {
        val syncsJson = Json.encodeToString(syncs)
        prefs.edit().putString(KEY_SCHEDULED_SYNCS, syncsJson).apply()
    }
    
    private fun getDefaultScheduledSyncs(): List<ScheduledSync> {
        return listOf(
            ScheduledSync.create(8, 0, "Morning"),
            ScheduledSync.create(21, 0, "Evening")
        )
    }

    fun getLastSyncTime(): Long? {
        val timestamp = prefs.getLong(KEY_LAST_SYNC_TIME, -1)
        return if (timestamp == -1L) null else timestamp
    }

    /**
     * The last moment a sync actually completed, persisted across restarts.
     *
     * Prefers the stored [getLastSyncTime]; when that was never written (e.g.
     * older installs that synced before the key existed), falls back to the
     * most recent successful webhook delivery so the next-sync countdown stays
     * anchored to real history instead of resetting on every screen switch.
     */
    fun getEffectiveLastSyncTime(): Long? {
        getLastSyncTime()?.let { return it }
        return getWebhookLogs().asSequence()
            .filter { it.success }
            .maxOfOrNull { it.timestamp }
    }

    fun getLastSyncAttemptTime(): Long? {
        val timestamp = prefs.getLong(KEY_LAST_SYNC_ATTEMPT_MS, -1)
        return if (timestamp == -1L) null else timestamp
    }

    fun setLastSyncAttemptTime(timestamp: Long) {
        prefs.edit().putLong(KEY_LAST_SYNC_ATTEMPT_MS, timestamp).apply()
    }

    /**
     * Anchor for the next-sync countdown and the INTERVAL alarm chain.
     *
     * Uses the most recent sync activity (any attempt, success or server
     * rejection). This keeps the 12h countdown advancing after every real sync
     * even when the server answers 409 for a duplicate batch, instead of
     * staying stuck on an old success timestamp and re-firing every few minutes.
     */
    fun getEffectiveLastSyncAnchor(): Long? {
        getLastSyncAttemptTime()?.let { return it }
        getLastSyncTime()?.let { return it }
        return getEffectiveLastSyncTime()
    }

    fun setLastSyncTime(timestamp: Long) {
        prefs.edit().putLong(KEY_LAST_SYNC_TIME, timestamp).apply()
    }

    fun getLastSyncSummary(): String? {
        return prefs.getString(KEY_LAST_SYNC_SUMMARY, null)
    }

    fun setLastSyncSummary(summary: String) {
        prefs.edit().putString(KEY_LAST_SYNC_SUMMARY, summary).apply()
    }

    fun hasSeenOnboarding(): Boolean = prefs.getBoolean(KEY_HAS_SEEN_ONBOARDING, false)

    fun setHasSeenOnboarding() {
        prefs.edit().putBoolean(KEY_HAS_SEEN_ONBOARDING, true).apply()
    }

    fun getLastSeenVersionCode(): Int =
        prefs.getInt(KEY_LAST_SEEN_VERSION_CODE, 0)

    fun setLastSeenVersionCode(versionCode: Int) {
        prefs.edit().putInt(KEY_LAST_SEEN_VERSION_CODE, versionCode).apply()
    }

    fun isLocalTcpEnabled(): Boolean {
        return prefs.getBoolean(KEY_LOCAL_TCP_ENABLED, false)
    }

    fun setLocalTcpEnabled(enabled: Boolean) {
        prefs.edit().putBoolean(KEY_LOCAL_TCP_ENABLED, enabled).apply()
    }

    fun getLocalTcpPort(): Int {
        val configuredPort = prefs.getInt(KEY_LOCAL_TCP_PORT, DEFAULT_LOCAL_TCP_PORT)
        return if (configuredPort in 1024..65535) configuredPort else DEFAULT_LOCAL_TCP_PORT
    }

    fun setLocalTcpPort(port: Int) {
        val safePort = port.coerceIn(1024, 65535)
        prefs.edit().putInt(KEY_LOCAL_TCP_PORT, safePort).apply()
    }

    fun isLocalHttpAuthEnabled(): Boolean = prefs.getBoolean(KEY_LOCAL_HTTP_AUTH_ENABLED, false)

    fun setLocalHttpAuthEnabled(enabled: Boolean) {
        prefs.edit().putBoolean(KEY_LOCAL_HTTP_AUTH_ENABLED, enabled).apply()
    }

    fun getLocalHttpToken(): String {
        val existing = prefs.getString(KEY_LOCAL_HTTP_TOKEN, null)
        if (!existing.isNullOrBlank()) return existing
        val generated = java.util.UUID.randomUUID().toString()
        prefs.edit().putString(KEY_LOCAL_HTTP_TOKEN, generated).apply()
        return generated
    }

    fun regenerateLocalHttpToken(): String {
        val token = java.util.UUID.randomUUID().toString()
        prefs.edit().putString(KEY_LOCAL_HTTP_TOKEN, token).apply()
        return token
    }

    fun getNotificationConfigs(): List<NotificationConfig> {
        val configsJson = prefs.getString(KEY_NOTIFICATION_CONFIGS, null)
        if (configsJson != null) {
            return try {
                Json.decodeFromString<List<NotificationConfig>>(configsJson)
            } catch (e: Exception) {
                emptyList()
            }
        }
        return emptyList()
    }

    fun setNotificationConfigs(configs: List<NotificationConfig>) {
        val configsJson = Json.encodeToString(configs)
        prefs.edit().putString(KEY_NOTIFICATION_CONFIGS, configsJson).apply()
    }

    /**
     * Heart rate downsampling interval in minutes. 0 means full resolution
     * (every raw sample is sent). A positive value groups samples into buckets
     * of that size and sends one avg/min/max summary per bucket, shrinking the
     * payload. Defaults to 0 to preserve the existing full-resolution behavior.
     */
    fun getHeartRateDownsampleMinutes(): Int =
        getDataTypeResolution(HealthDataType.HEART_RATE)

    fun setHeartRateDownsampleMinutes(minutes: Int) {
        setDataTypeResolution(HealthDataType.HEART_RATE, minutes.coerceAtLeast(0))
    }

    /**
     * Steps resolution in minutes: negative = daily cumulative total (default,
     * the original behavior), 0 = full (every raw interval), positive = group raw
     * intervals into buckets of that size and sum the counts. Controls how
     * granular the step data sent to webhooks is.
     */
    fun getStepsResolutionMinutes(): Int =
        getDataTypeResolution(HealthDataType.STEPS)

    fun setStepsResolutionMinutes(minutes: Int) {
        setDataTypeResolution(HealthDataType.STEPS, minutes)
    }

    fun getDataTypeResolution(type: HealthDataType): Int {
        val stored = readResolutionMap()[type.name]
        return stored ?: type.defaultResolutionMinutes
    }

    fun setDataTypeResolution(type: HealthDataType, minutes: Int) {
        val map = readResolutionMap().toMutableMap()
        map[type.name] = minutes
        writeResolutionMap(map)
        syncLegacyResolutionKeys(type, minutes)
    }

    fun getDataTypeResolutions(): Map<HealthDataType, Int> =
        HealthDataType.entries
            .filter { it.supportsResolution }
            .associateWith { getDataTypeResolution(it) }

    private fun readResolutionMap(): Map<String, Int> {
        val json = prefs.getString(KEY_DATA_TYPE_RESOLUTIONS, null)
        if (json != null) {
            return try {
                Json.decodeFromString<Map<String, Int>>(json)
            } catch (_: Exception) {
                emptyMap()
            }
        }
        return buildMap {
            if (prefs.contains(KEY_HR_DOWNSAMPLE_MINUTES)) {
                put(HealthDataType.HEART_RATE.name, prefs.getInt(KEY_HR_DOWNSAMPLE_MINUTES, RESOLUTION_FULL))
            }
            if (prefs.contains(KEY_STEPS_RESOLUTION_MINUTES)) {
                put(HealthDataType.STEPS.name, prefs.getInt(KEY_STEPS_RESOLUTION_MINUTES, RESOLUTION_DAILY))
            }
        }
    }

    private fun writeResolutionMap(map: Map<String, Int>) {
        prefs.edit()
            .putString(KEY_DATA_TYPE_RESOLUTIONS, Json.encodeToString(map))
            .apply()
    }

    private fun syncLegacyResolutionKeys(type: HealthDataType, minutes: Int) {
        when (type) {
            HealthDataType.HEART_RATE ->
                prefs.edit().putInt(KEY_HR_DOWNSAMPLE_MINUTES, minutes.coerceAtLeast(0)).apply()
            HealthDataType.STEPS ->
                prefs.edit().putInt(KEY_STEPS_RESOLUTION_MINUTES, minutes).apply()
            else -> Unit
        }
    }

    private fun applyResolutionMap(map: Map<String, Int>) {
        writeResolutionMap(map)
        map[HealthDataType.HEART_RATE.name]?.let { syncLegacyResolutionKeys(HealthDataType.HEART_RATE, it) }
        map[HealthDataType.STEPS.name]?.let { syncLegacyResolutionKeys(HealthDataType.STEPS, it) }
    }

    fun isBatteryBannerDismissed(): Boolean =
        prefs.getBoolean(KEY_BATTERY_BANNER_DISMISSED, false)

    fun setBatteryBannerDismissed(dismissed: Boolean) {
        prefs.edit().putBoolean(KEY_BATTERY_BANNER_DISMISSED, dismissed).apply()
    }

    // -------------------------------------------------------------------------
    // Export / Import
    // -------------------------------------------------------------------------

    /**
     * Builds a [SettingsExport] snapshot of all current user-configurable preferences.
     */
    fun exportSettings(): SettingsExport {
        return SettingsExport(
            exportedAt = System.currentTimeMillis(),
            webhookConfigs = getWebhookConfigs(),
            enabledDataTypes = getEnabledDataTypes().map { it.name },
            syncMode = getSyncMode().name,
            syncIntervalMinutes = getSyncIntervalMinutes(),
            scheduledSyncs = getScheduledSyncs(),
            localTcpEnabled = isLocalTcpEnabled(),
            localTcpPort = getLocalTcpPort(),
            notificationConfigs = getNotificationConfigs(),
            heartRateDownsampleMinutes = getHeartRateDownsampleMinutes(),
            stepsResolutionMinutes = getStepsResolutionMinutes(),
            dataTypeResolutions = getDataTypeResolutions().mapKeys { it.key.name },
        )
    }

    /**
     * Restores all user-configurable preferences from a [SettingsExport].
     */
    fun importSettings(export: SettingsExport) {
        setWebhookConfigs(export.webhookConfigs)

        val dataTypes = export.enabledDataTypes.mapNotNull { name ->
            try { HealthDataType.valueOf(name) } catch (e: Exception) { null }
        }.toSet()
        setEnabledDataTypes(dataTypes)

        val mode = try { SyncMode.valueOf(export.syncMode) } catch (e: Exception) { SyncMode.INTERVAL }
        setSyncMode(mode)

        setSyncIntervalMinutes(export.syncIntervalMinutes)
        setScheduledSyncs(export.scheduledSyncs)
        setLocalTcpEnabled(export.localTcpEnabled)
        setLocalTcpPort(export.localTcpPort)
        setNotificationConfigs(export.notificationConfigs)
        if (export.dataTypeResolutions.isNotEmpty()) {
            applyResolutionMap(export.dataTypeResolutions)
        } else {
            setHeartRateDownsampleMinutes(export.heartRateDownsampleMinutes)
            setStepsResolutionMinutes(export.stepsResolutionMinutes)
        }
    }

    fun getLocalFeedback(): List<LocalFeedbackEntry> {
        val json = prefs.getString(KEY_LOCAL_FEEDBACK, null) ?: return emptyList()
        return try {
            Json.decodeFromString<List<LocalFeedbackEntry>>(json)
        } catch (_: Exception) {
            emptyList()
        }
    }

    fun addLocalFeedback(entry: LocalFeedbackEntry) {
        val updated = (listOf(entry) + getLocalFeedback()).take(MAX_LOCAL_FEEDBACK)
        prefs.edit().putString(KEY_LOCAL_FEEDBACK, Json.encodeToString(updated)).apply()
    }

    fun ensureFirstOpenTime() {
        if (!prefs.contains(KEY_FIRST_OPEN_TIME)) {
            prefs.edit().putLong(KEY_FIRST_OPEN_TIME, System.currentTimeMillis()).apply()
        }
    }

    fun shouldShowWeeklyFeedbackPrompt(): Boolean {
        ensureFirstOpenTime()
        if (prefs.getBoolean(KEY_WEEKLY_FEEDBACK_PROMPT_DISMISSED, false)) return false
        val firstOpen = prefs.getLong(KEY_FIRST_OPEN_TIME, System.currentTimeMillis())
        return System.currentTimeMillis() - firstOpen >= WEEKLY_FEEDBACK_PROMPT_MS
    }

    fun setWeeklyFeedbackPromptDismissed(dismissed: Boolean) {
        prefs.edit().putBoolean(KEY_WEEKLY_FEEDBACK_PROMPT_DISMISSED, dismissed).apply()
    }
    fun isWifiOnly(): Boolean = prefs.getBoolean(KEY_WIFI_ONLY, false)
    fun setWifiOnly(value: Boolean) {
        prefs.edit().putBoolean(KEY_WIFI_ONLY, value).apply()
    }
    fun getThemeMode(): String = prefs.getString(KEY_THEME_MODE, "auto") ?: "auto"
    fun setThemeMode(value: String) {
        prefs.edit().putString(KEY_THEME_MODE, value).apply()
    }
    fun isAmoledEnabled(): Boolean = prefs.getBoolean(KEY_AMOLED_ENABLED, false)
    fun setAmoledEnabled(value: Boolean) {
        prefs.edit().putBoolean(KEY_AMOLED_ENABLED, value).apply()
    }
    fun getAccentColor(): String = prefs.getString(KEY_ACCENT_COLOR, "blue") ?: "blue"
    fun setAccentColor(value: String) {
        prefs.edit().putString(KEY_ACCENT_COLOR, value).apply()
    }
    fun getAppLanguage(): String = prefs.getString(KEY_APP_LANGUAGE, "en") ?: "en"
    fun setAppLanguage(value: String) {
        prefs.edit().putString(KEY_APP_LANGUAGE, value).apply()
    }
    fun isPortraitOnly(): Boolean = prefs.getBoolean(KEY_PORTRAIT_ONLY, false)
    fun setPortraitOnly(value: Boolean) {
        prefs.edit().putBoolean(KEY_PORTRAIT_ONLY, value).apply()
    }
    fun getStatsCache(maxAgeMs: Long): String? {
        val savedAt = prefs.getLong(KEY_STATS_CACHE_TIME, 0)
        if (System.currentTimeMillis() - savedAt > maxAgeMs) return null
        return prefs.getString(KEY_STATS_CACHE_JSON, null)
    }
    fun setStatsCache(json: String) {
        prefs.edit().putString(KEY_STATS_CACHE_JSON, json).putLong(KEY_STATS_CACHE_TIME, System.currentTimeMillis()).apply()
    }
    fun getProfileCache(maxAgeMs: Long): String? {
        val savedAt = prefs.getLong(KEY_PROFILE_CACHE_TIME, 0)
        if (System.currentTimeMillis() - savedAt > maxAgeMs) return null
        return prefs.getString(KEY_PROFILE_CACHE_JSON, null)
    }
    fun setProfileCache(json: String) {
        prefs.edit().putString(KEY_PROFILE_CACHE_JSON, json).putLong(KEY_PROFILE_CACHE_TIME, System.currentTimeMillis()).apply()
    }
    fun getAndroidStepsCache(date: String, maxAgeMs: Long): Long? {
        val raw = prefs.getString(KEY_ANDROID_STEPS_CACHE_PREFIX + date, null) ?: return null
        val parts = raw.split(":", limit = 2)
        if (parts.size != 2) return null
        val savedAt = parts[0].toLongOrNull() ?: return null
        if (System.currentTimeMillis() - savedAt > maxAgeMs) return null
        return parts[1].toLongOrNull()
    }
    fun setAndroidStepsCache(date: String, steps: Long) {
        prefs.edit().putString(KEY_ANDROID_STEPS_CACHE_PREFIX + date, "${System.currentTimeMillis()}:$steps").apply()
    }
    fun getPendingConflictJson(): String? = prefs.getString(KEY_PENDING_CONFLICT_JSON, null)
    fun setPendingConflictJson(json: String) {
        prefs.edit().putString(KEY_PENDING_CONFLICT_JSON, json).apply()
    }
    fun clearPendingConflictJson() {
        prefs.edit().remove(KEY_PENDING_CONFLICT_JSON).apply()
    }

    fun isConflictRememberEnabled(): Boolean = prefs.getBoolean(KEY_CONFLICT_REMEMBER, false)
    fun setConflictRememberEnabled(enabled: Boolean) {
        prefs.edit().putBoolean(KEY_CONFLICT_REMEMBER, enabled).apply()
        if (!enabled) prefs.edit().remove(KEY_CONFLICT_DEFAULT_ACTION).apply()
    }
    fun getConflictDefaultAction(): String? = prefs.getString(KEY_CONFLICT_DEFAULT_ACTION, null)
    fun setConflictDefaultAction(action: String) {
        prefs.edit().putString(KEY_CONFLICT_DEFAULT_ACTION, action).apply()
    }

    fun clearServerDataCache() {
        val editor = prefs.edit()

        editor.remove(KEY_PROFILE_CACHE_JSON)
        editor.remove(KEY_PROFILE_CACHE_TIME)

        editor.remove(KEY_STATS_CACHE_JSON)
        editor.remove(KEY_STATS_CACHE_TIME)

        editor.remove(KEY_PENDING_CONFLICT_JSON)

        editor.remove(KEY_LAST_SYNC_TIME)
        editor.remove(KEY_LAST_SYNC_ATTEMPT_MS)
        editor.remove(KEY_LAST_SYNC_SUMMARY)

        // last_sync_ts_<HealthDataType>
        prefs.all.keys
            .filter { it.startsWith(KEY_LAST_SYNC_TS_PREFIX) }
            .forEach { editor.remove(it) }

        editor.remove(KEY_LAST_STEPS_SYNC_TS)
        editor.remove(KEY_LAST_SLEEP_SYNC_TS)

        editor.apply()
    }

    /** Debug only: backdate first open and clear dismiss so the weekly Home card shows. */
    fun debugForceWeeklyFeedbackPrompt() {
        prefs.edit()
            .putLong(KEY_FIRST_OPEN_TIME, System.currentTimeMillis() - WEEKLY_FEEDBACK_PROMPT_MS - 1_000L)
            .putBoolean(KEY_WEEKLY_FEEDBACK_PROMPT_DISMISSED, false)
            .apply()
    }
}
