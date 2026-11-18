package orbit.nex.steps.location

import android.annotation.SuppressLint
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.pm.ServiceInfo
import android.location.Location
import android.os.Build
import android.os.IBinder
import android.os.Looper
import android.os.PowerManager
import android.util.Log
import android.widget.RemoteViews
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat
import com.google.android.gms.location.FusedLocationProviderClient
import com.google.android.gms.location.LocationCallback
import com.google.android.gms.location.LocationRequest
import com.google.android.gms.location.LocationResult
import com.google.android.gms.location.LocationServices
import com.google.android.gms.location.Priority
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import orbit.nex.steps.MainActivity
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.R
import org.json.JSONObject
import java.time.Instant
import java.time.LocalDate
import java.util.Locale
import java.util.TimeZone
import java.util.UUID
import kotlin.math.abs

/**
 * Records location while the app is in the background.
 *
 * The service owns the fused-location request and the outbox write: every
 * measurement is stored in [LocationEventStore] before [LocationUploadWorker]
 * can upload it. It only ever starts through a user action
 * ([LocationTrackingManager.start]) — there is no silent enable path.
 */
class LocationTrackingService : Service() {

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private lateinit var fusedClient: FusedLocationProviderClient
    private lateinit var prefs: PreferencesManager
    private var uploadLoop: Job? = null
    private var active = false
    private var lastUploadAtMs = 0L
    private var powerReceiver: BroadcastReceiver? = null

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onCreate() {
        super.onCreate()
        prefs = PreferencesManager(this)
        fusedClient = LocationServices.getFusedLocationProviderClient(this)
        if (!LocationPermissions.hasForeground(this)) {
            // Never keep a foreground service without the permission it advertises.
            prefs.setLocationPermissionLost(true)
            stopSelf()
            return
        }
        ensureChannel()
        if (!promoteToForeground()) {
            stopSelf()
            return
        }
        prefs.setLocationPermissionLost(false)
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        when (intent?.action) {
            ACTION_STOP -> {
                stopTracking()
                prefs.setLocationTrackingEnabled(false)
                prefs.setLocationTrackingPaused(false)
                stopSelf()
                return START_NOT_STICKY
            }
            ACTION_PAUSE -> {
                stopTracking()
                prefs.setLocationTrackingPaused(true)
                stopSelf()
                return START_NOT_STICKY
            }
            else -> {
                // A null intent comes from a system restart of the service. Only
                // continue when the user had tracking enabled, never re-enable a
                // session that was stopped.
                if (intent == null && (!prefs.isLocationTrackingEnabled() || prefs.isLocationTrackingPaused())) {
                    stopSelf()
                    return START_NOT_STICKY
                }
                prefs.setLocationTrackingEnabled(true)
                prefs.setLocationTrackingPaused(false)
                startTracking()
            }
        }
        return START_STICKY
    }

    @SuppressLint("MissingPermission")
    private fun startTracking() {
        if (active) return
        if (!LocationPermissions.hasForeground(this)) {
            prefs.setLocationPermissionLost(true)
            stopSelf()
            return
        }
        active = true
        applyRequest()
        registerPowerReceiver()
        startUploadLoop()
        LocationUploadWorker.ensurePeriodic(this)
        updateNotification()
    }

    private fun stopTracking() {
        isRecording = false
        if (active) {
            runCatching { fusedClient.removeLocationUpdates(locationCallback) }
            active = false
        }
        uploadLoop?.cancel()
        uploadLoop = null
        unregisterPowerReceiver()
    }

    /** Applies accuracy and sampling interval from settings; no transport inference. */
    @SuppressLint("MissingPermission")
    private fun applyRequest() {
        if (!LocationPermissions.hasForeground(this)) return
        val powerSaver = (getSystemService(Context.POWER_SERVICE) as? PowerManager)?.isPowerSaveMode == true
        // Sampling cadence is the location setting, not the health data sync
        // interval: sharing the two made recording follow the webhook schedule.
        val intervalMs = prefs.getLocationIntervalMinutes().coerceAtLeast(1) * 60_000L
        val priority = when {
            powerSaver -> Priority.PRIORITY_LOW_POWER
            else -> when (prefs.getLocationAccuracy()) {
                LocationAccuracy.HIGH -> Priority.PRIORITY_HIGH_ACCURACY
                LocationAccuracy.LOW -> Priority.PRIORITY_LOW_POWER
                else -> Priority.PRIORITY_BALANCED_POWER_ACCURACY
            }
        }
        // Battery saver doubles the interval instead of silently dropping updates.
        val effectiveInterval = if (powerSaver) intervalMs * 2 else intervalMs
        val request = LocationRequest.Builder(priority, effectiveInterval)
            .setMinUpdateIntervalMillis(effectiveInterval / 2)
            .setMinUpdateDistanceMeters(0f)
            .build()
        runCatching {
            fusedClient.removeLocationUpdates(locationCallback)
            fusedClient.requestLocationUpdates(request, locationCallback, Looper.getMainLooper())
                .addOnSuccessListener { if (active) isRecording = true }
                .addOnFailureListener { error ->
                    isRecording = false
                    Log.e(TAG, "Location request failed: ${error.message}")
                    prefs.setLocationUploadFailure(error.message ?: "location_unavailable")
                    updateNotification()
                }
        }.onFailure { error ->
            isRecording = false
            Log.e(TAG, "Location request rejected: ${error.message}")
        }
    }

    private val locationCallback = object : LocationCallback() {
        override fun onLocationResult(result: LocationResult) {
            result.locations.forEach { persist(it) }
            val pending = pendingCount()
            updateNotification()
            // Small batches keep the outbox short without uploading on every fix.
            if (pending >= UPLOAD_EVERY) requestUpload()
        }
    }

    /** Writes the measurement to disk before anything is sent anywhere. */
    private fun persist(location: Location): LocationEvent? {
        if (!location.hasAccuracy() && location.latitude == 0.0 && location.longitude == 0.0) return null
        if (abs(location.latitude) < 0.0001 && abs(location.longitude) < 0.0001) return null
        val event = LocationEvent(
            clientEventId = UUID.randomUUID().toString(),
            latitude = location.latitude,
            longitude = location.longitude,
            sourceAt = Instant.ofEpochMilli(location.time).toString(),
            sourceOffsetMinutes = TimeZone.getDefault().getOffset(location.time) / 60_000,
            accuracyM = if (location.hasAccuracy()) location.accuracy.toDouble() else null,
            altitudeM = if (location.hasAltitude()) location.altitude else null,
            speedMps = if (location.hasSpeed()) location.speed else null,
            bearingDegrees = if (location.hasBearing()) location.bearing else null,
            deviceSequence = prefs.nextLocationSequence(),
            recordedAtMs = System.currentTimeMillis(),
        )
        val stored = LocationEventStore(this).insert(prefs.getLocationDeviceId(), event)
        if (!stored) Log.w(TAG, "Duplicate location event ignored")
        return event.takeIf { stored }
    }

    private fun startUploadLoop() {
        uploadLoop?.cancel()
        uploadLoop = scope.launch {
            while (isActive) {
                delay(UPLOAD_INTERVAL_MS)
                if (System.currentTimeMillis() - lastUploadAtMs >= UPLOAD_INTERVAL_MS) requestUpload()
                // Uploads may have settled in the background; keep the shade in
                // sync with the real pending count.
                updateNotification()
            }
        }
    }

    /** Queue a durable upload; WorkManager owns connectivity and retries. */
    private fun requestUpload() {
        lastUploadAtMs = System.currentTimeMillis()
        LocationUploadWorker.enqueue(this)
    }

    private fun pendingCount(): Int = runCatching { LocationEventStore(this).pendingCount() }.getOrDefault(0)

    private fun ensureChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        if (manager.getNotificationChannel(CHANNEL_ID) != null) return
        val channel = NotificationChannel(
            CHANNEL_ID,
            getString(R.string.tracking_channel_name),
            NotificationManager.IMPORTANCE_LOW,
        ).apply {
            description = getString(R.string.tracking_channel_desc)
            setShowBadge(false)
        }
        manager.createNotificationChannel(channel)
    }

    private fun promoteToForeground(): Boolean {
        val notification = buildNotification(pendingCount(), todayRouteMeters())
        return runCatching {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                startForeground(NOTIFICATION_ID, notification, ServiceInfo.FOREGROUND_SERVICE_TYPE_LOCATION)
            } else {
                startForeground(NOTIFICATION_ID, notification)
            }
            true
        }.onFailure { error ->
            Log.e(TAG, "startForeground failed: ${error.message}", error)
        }.getOrDefault(false)
    }

    private fun updateNotification() {
        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager ?: return
        runCatching { manager.notify(NOTIFICATION_ID, buildNotification(pendingCount(), todayRouteMeters())) }
    }

    /**
     * Sums the distance between today's stored measurements so the ongoing
     * notification shows the route recorded so far, regardless of whether those
     * points have already reached the server.
     */
    private fun todayRouteMeters(): Double {
        val events = runCatching { LocationEventStore(this).eventsForDay(LocalDate.now()) }
            .getOrDefault(emptyList())
        if (events.size < 2) return 0.0
        var total = 0.0
        var previousLat: Double? = null
        var previousLon: Double? = null
        val result = FloatArray(1)
        events.forEach { event ->
            val payload = runCatching { JSONObject(event.payload) }.getOrNull() ?: return@forEach
            val lat = payload.optDouble("latitude", Double.NaN)
            val lon = payload.optDouble("longitude", Double.NaN)
            if (lat.isNaN() || lon.isNaN()) return@forEach
            val prevLat = previousLat
            val prevLon = previousLon
            if (prevLat != null && prevLon != null) {
                Location.distanceBetween(prevLat, prevLon, lat, lon, result)
                total += result[0]
            }
            previousLat = lat
            previousLon = lon
        }
        return total
    }

    private fun formatDistance(meters: Double): String =
        if (meters < 1_000) "${meters.toInt()} m"
        else String.format(Locale.getDefault(), "%.1f km", meters / 1_000)

    private fun buildNotification(pending: Int, routeMeters: Double): Notification {
        val open = PendingIntent.getActivity(
            this,
            0,
            Intent(this, MainActivity::class.java),
            pendingIntentFlags(),
        )
        val pause = PendingIntent.getService(
            this,
            1,
            Intent(this, LocationTrackingService::class.java).setAction(ACTION_PAUSE),
            pendingIntentFlags(),
        )
        val stop = PendingIntent.getService(
            this,
            2,
            Intent(this, LocationTrackingService::class.java).setAction(ACTION_STOP),
            pendingIntentFlags(),
        )
        val routeLine = getString(R.string.tracking_notification_route, formatDistance(routeMeters))
        val distanceText = formatDistance(routeMeters)
        val fullViews = RemoteViews(packageName, R.layout.notification_tracking).apply {
            setTextViewText(R.id.notif_route, distanceText)
            setTextViewText(R.id.notif_pending, pending.toString())
            setOnClickPendingIntent(R.id.notif_pause, pause)
            setOnClickPendingIntent(R.id.notif_stop, stop)
            // Tapping the rest of the card opens the app; the buttons win because
            // their click intents are attached to the child views.
            setOnClickPendingIntent(R.id.notif_root, open)
        }
        // The collapsed shade is much shorter, so it gets its own single-row
        // layout instead of a clipped version of the full card.
        val compactViews = RemoteViews(packageName, R.layout.notification_tracking_compact).apply {
            setTextViewText(
                R.id.notif_compact_text,
                getString(R.string.tracking_notification_compact, distanceText, pending),
            )
            setOnClickPendingIntent(R.id.notif_root, open)
        }
        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle(getString(R.string.tracking_notification_title))
            .setContentText(routeLine)
            .setSmallIcon(R.drawable.ic_notif_location)
            .setContentIntent(open)
            .setOngoing(true)
            .setShowWhen(false)
            .setOnlyAlertOnce(true)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .setCategory(NotificationCompat.CATEGORY_SERVICE)
            .setCustomContentView(compactViews)
            .setCustomBigContentView(fullViews)
            .build()
    }

    private fun pendingIntentFlags(): Int =
        PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE

    private fun registerPowerReceiver() {
        if (powerReceiver != null) return
        val receiver = object : BroadcastReceiver() {
            override fun onReceive(context: Context?, intent: Intent?) {
                // Battery saver changed: re-issue the request with the new cadence.
                if (active) applyRequest()
            }
        }
        val filter = IntentFilter(PowerManager.ACTION_POWER_SAVE_MODE_CHANGED)
        ContextCompat.registerReceiver(this, receiver, filter, ContextCompat.RECEIVER_NOT_EXPORTED)
        powerReceiver = receiver
    }

    private fun unregisterPowerReceiver() {
        powerReceiver?.let { runCatching { unregisterReceiver(it) } }
        powerReceiver = null
    }

    override fun onDestroy() {
        stopTracking()
        scope.coroutineContext[Job]?.cancel()
        // Keep the ongoing status notification only while the service really runs.
        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager
        manager?.cancel(NOTIFICATION_ID)
        super.onDestroy()
    }

    companion object {
        @Volatile
        var isRecording: Boolean = false
            private set
        private const val TAG = "LocationTrackingService"
        private const val NOTIFICATION_ID = 2003
        private const val UPLOAD_EVERY = 20
        private const val UPLOAD_INTERVAL_MS = 5L * 60 * 1000
        const val CHANNEL_ID = "location_tracking"
        const val ACTION_START = "orbit.nex.steps.location.START"
        const val ACTION_STOP = "orbit.nex.steps.location.STOP"
        const val ACTION_PAUSE = "orbit.nex.steps.location.PAUSE"

        fun start(context: Context) {
            val intent = Intent(context, LocationTrackingService::class.java).setAction(ACTION_START)
            runCatching { ContextCompat.startForegroundService(context, intent) }
                .onFailure { Log.e(TAG, "Failed to start location tracking: ${it.message}", it) }
        }

        fun stop(context: Context) {
            val intent = Intent(context, LocationTrackingService::class.java).setAction(ACTION_STOP)
            runCatching { context.startService(intent) }
                .onFailure { Log.e(TAG, "Failed to stop location tracking: ${it.message}", it) }
        }

        fun pause(context: Context) {
            val intent = Intent(context, LocationTrackingService::class.java).setAction(ACTION_PAUSE)
            runCatching { context.startService(intent) }
                .onFailure { Log.e(TAG, "Failed to pause location tracking: ${it.message}", it) }
        }
    }
}
