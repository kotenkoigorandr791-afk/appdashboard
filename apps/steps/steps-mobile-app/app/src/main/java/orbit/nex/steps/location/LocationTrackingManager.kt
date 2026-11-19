package orbit.nex.steps.location

import android.content.Context
import android.util.Log
import orbit.nex.steps.PreferencesManager

/** Accuracy profile the user picks for background recording. */
enum class LocationAccuracy(val storageValue: String) {
    HIGH("high"),
    BALANCED("balanced"),
    LOW("low");

    companion object {
        fun fromStorage(value: String?): LocationAccuracy =
            entries.firstOrNull { it.storageValue == value } ?: BALANCED
    }
}

/** User-visible tracking state derived from settings and permissions. */
enum class TrackingState {
    OFF,
    RUNNING,
    PAUSED,
    PERMISSION_MISSING,
    NOT_CONNECTED,
}

/**
 * Single entry point for starting, pausing, resuming and reporting tracking.
 *
 * Every mutation is explicit: nothing starts the service unless the user asked
 * for it (or the app is recreating a previously user-enabled session, which the
 * UI shows and the user can stop at any time).
 */
class LocationTrackingManager(private val context: Context) {

    private val appContext = context.applicationContext
    private val prefs = PreferencesManager(appContext)

    fun state(): TrackingState {
        if (prefs.getWebhookConfigs().none { it.isEnabled }) return TrackingState.NOT_CONNECTED
        if (!prefs.isLocationTrackingEnabled()) return TrackingState.OFF
        if (prefs.isLocationTrackingPaused()) return TrackingState.PAUSED
        if (!LocationPermissions.hasForeground(appContext)) return TrackingState.PERMISSION_MISSING
        if (!LocationTrackingService.isRecording) return TrackingState.PAUSED
        return TrackingState.RUNNING
    }

    /** @return false when the foreground permission is missing; the UI explains recovery. */
    fun start(): Boolean {
        if (!LocationPermissions.hasForeground(appContext)) {
            prefs.setLocationPermissionLost(true)
            return false
        }
        prefs.setLocationTrackingEnabled(true)
        prefs.setLocationTrackingPaused(false)
        prefs.setLocationPermissionLost(false)
        LocationTrackingService.start(appContext)
        LocationUploadWorker.ensurePeriodic(appContext)
        return true
    }

    fun pause() {
        prefs.setLocationTrackingPaused(true)
        LocationTrackingService.pause(appContext)
    }

    fun resume(): Boolean {
        prefs.setLocationTrackingPaused(false)
        return start()
    }

    fun stop() {
        prefs.setLocationTrackingEnabled(false)
        prefs.setLocationTrackingPaused(false)
        LocationTrackingService.stop(appContext)
        if (pendingCount(appContext) > 0) {
            LocationUploadWorker.enqueue(appContext)
            LocationUploadWorker.ensurePeriodic(appContext)
        } else {
            LocationUploadWorker.cancelPeriodic(appContext)
        }
    }

    /**
     * Stops a running session whose permission was revoked and marks it so the
     * UI can explain the recovery instead of showing a stale "on" state.
     */
    fun reconcile(): Boolean {
        if (!prefs.isLocationTrackingEnabled() || prefs.isLocationTrackingPaused()) return true
        if (LocationPermissions.hasForeground(appContext)) return true
        prefs.setLocationPermissionLost(true)
        LocationTrackingService.stop(appContext)
        return false
    }

    companion object {
        private const val TAG = "LocationTrackingManager"

        /**
         * Restarts a user-enabled session after reboot, an app update, or after
         * the app was force-stopped and reopened. Android can refuse a
         * background foreground-service start, in which case the stored
         * "enabled" flag makes the in-app Resume action available.
         */
        fun resumeIfEnabled(context: Context) {
            val prefs = PreferencesManager(context.applicationContext)
            if (!prefs.isLocationTrackingEnabled() || prefs.isLocationTrackingPaused()) return
            if (!LocationPermissions.hasForeground(context.applicationContext)) {
                prefs.setLocationPermissionLost(true)
                return
            }
            runCatching {
                LocationTrackingService.start(context.applicationContext)
                LocationUploadWorker.ensurePeriodic(context.applicationContext)
            }.onFailure { Log.w(TAG, "Could not auto-resume tracking: ${it.message}") }
        }

        fun pendingCount(context: Context): Int =
            runCatching { LocationEventStore(context.applicationContext).pendingCount() }.getOrDefault(0)
    }
}
