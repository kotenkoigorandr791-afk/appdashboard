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
import android.content.res.Configuration
import android.location.Location
import android.location.LocationManager
import android.location.LocationListener
import android.hardware.Sensor
import android.hardware.SensorEvent
import android.hardware.SensorEventListener
import android.hardware.SensorManager
import android.hardware.TriggerEvent
import android.hardware.TriggerEventListener
import android.os.Build
import android.os.IBinder
import android.os.Handler
import android.os.Looper
import android.os.PowerManager
import android.os.SystemClock
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
    @Volatile private var active = false
    private val mainHandler = Handler(Looper.getMainLooper())
    private var requestGeneration = 0
    @Volatile private var powerMode = LocationAccuracy.BALANCED
    private var lastMovingMs = 0L
    private var lastModeChangeMs = 0L
    private var fastSamples = 0
    private var slowSamples = 0
    private val sensorManager by lazy { getSystemService(Context.SENSOR_SERVICE) as SensorManager }
    private val locationManager by lazy { getSystemService(Context.LOCATION_SERVICE) as LocationManager }
    private var sensorBaseline = 9.81f
    private val motionSensorListener = object : SensorEventListener {
        override fun onAccuracyChanged(sensor: Sensor?, accuracy: Int) = Unit
        override fun onSensorChanged(event: SensorEvent) {
            val v = event.values
            val magnitude = kotlin.math.sqrt(v[0] * v[0] + v[1] * v[1] + v[2] * v[2])
            sensorBaseline = sensorBaseline * 0.9f + magnitude * 0.1f
            if (kotlin.math.abs(magnitude - sensorBaseline) > 0.7f) {
                lastMovingMs = SystemClock.elapsedRealtime()
                if (prefs.getLocationAccuracy() == LocationAccuracy.AUTO && powerMode == LocationAccuracy.LOW) switchMode(LocationAccuracy.BALANCED)
            }
        }
    }
    private val significantMotionListener = object : TriggerEventListener() {
        override fun onTrigger(event: TriggerEvent?) {
            if (!active) return
            lastMovingMs = SystemClock.elapsedRealtime()
            if (prefs.getLocationAccuracy() == LocationAccuracy.AUTO && powerMode == LocationAccuracy.LOW) switchMode(LocationAccuracy.BALANCED)
            registerSignificantMotion()
        }
    }
    private val fallbackListener = LocationListener { location ->
        if (active) onMeasurement(location, "gnss")
    }
    private fun registerSignificantMotion() {
        sensorManager.getDefaultSensor(Sensor.TYPE_SIGNIFICANT_MOTION)?.let {
            runCatching { sensorManager.requestTriggerSensor(significantMotionListener, it) }
        }
    }
    private fun configureMotionSensors() {
        sensorManager.unregisterListener(motionSensorListener)
        sensorManager.getDefaultSensor(Sensor.TYPE_SIGNIFICANT_MOTION)?.let { runCatching { sensorManager.cancelTriggerSensor(significantMotionListener, it) } }
        if (prefs.getLocationAccuracy() == LocationAccuracy.AUTO) {
            sensorManager.getDefaultSensor(Sensor.TYPE_ACCELEROMETER)?.let { sensorManager.registerListener(motionSensorListener, it, SensorManager.SENSOR_DELAY_UI) }
            registerSignificantMotion()
        }
    }
    private fun switchMode(mode: LocationAccuracy) {
        if (powerMode == mode) return
        powerMode = mode
        lastModeChangeMs = SystemClock.elapsedRealtime()
        prefs.setLocationPowerMode(mode.storageValue)
        applyRequest()
    }
    private fun onMeasurement(location: Location, origin: String) {
        lastFixElapsedMs = SystemClock.elapsedRealtime()
        persist(location, origin)
        if (prefs.getLocationAccuracy() == LocationAccuracy.AUTO && location.hasAccuracy() && location.accuracy <= 50f) {
            val now = SystemClock.elapsedRealtime()
            val speed = if (location.hasSpeed()) location.speed else 0f
            if (speed > 0.8f) lastMovingMs = now
            fastSamples = if (speed >= prefs.getFastModeSpeed()) fastSamples + 1 else 0
            slowSamples = if (speed < 5f) slowSamples + 1 else 0
            if (now - lastModeChangeMs >= 60_000L) {
                when {
                    fastSamples >= 3 -> switchMode(LocationAccuracy.HIGH)
                    powerMode == LocationAccuracy.HIGH && slowSamples >= 6 -> switchMode(LocationAccuracy.BALANCED)
                    now - lastMovingMs >= prefs.getStationarySeconds() * 1_000L -> switchMode(LocationAccuracy.LOW)
                }
            }
        }
        updateNotification()
    }
    private var powerReceiver: BroadcastReceiver? = null
    private var screenReceiver: BroadcastReceiver? = null
    private var watchdogJob: Job? = null
    @Volatile private var lastFixElapsedMs = 0L
    @Volatile private var lastRecoveryElapsedMs = 0L
    private var lastUploadEnqueueMs = 0L
    private var lastPersistedLat = Double.NaN
    private var lastPersistedLon = Double.NaN
    private var lastPersistedAtMs = 0L

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
        // The card must appear immediately even before the first fix arrives, and
        // a restarted process must be seen as running by the UI.
        prefs.setLocationServiceHeartbeat(System.currentTimeMillis())
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
            ACTION_REFRESH -> {
                if (prefs.isLocationTrackingEnabled() && !prefs.isLocationTrackingPaused()) {
                    if (active) { configureMotionSensors(); applyRequest() } else startTracking()
                }
                // Re-inflates the card so a theme, or an in-app theme override,
                // is reflected without waiting for the next location fix.
                updateNotification()
                return START_STICKY
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
        powerMode = prefs.getLocationAccuracy().let { if (it == LocationAccuracy.AUTO) LocationAccuracy.BALANCED else it }
        prefs.setLocationPowerMode(powerMode.storageValue)
        lastMovingMs = SystemClock.elapsedRealtime()
        lastModeChangeMs = lastMovingMs
        configureMotionSensors()
        lastFixElapsedMs = SystemClock.elapsedRealtime()
        applyRequest()
        registerPowerReceiver()
        registerScreenReceiver()
        startWatchdog()
        updateNotification()
    }

    private fun stopTracking() {
        isRecording = false
        active = false
        sensorManager.unregisterListener(motionSensorListener)
        sensorManager.getDefaultSensor(Sensor.TYPE_SIGNIFICANT_MOTION)?.let { runCatching { sensorManager.cancelTriggerSensor(significantMotionListener, it) } }
        runCatching { locationManager.removeUpdates(fallbackListener) }
        requestGeneration++
        runCatching { fusedClient.removeLocationUpdates(locationCallback) }
        runCatching { fusedClient.removeLocationUpdates(passiveCallback) }
        watchdogJob?.cancel()
        watchdogJob = null
        unregisterPowerReceiver()
        unregisterScreenReceiver()
        prefs.setLocationServiceHeartbeat(0L)
    }

    /** Applies accuracy and sampling interval from settings; no transport inference. */
    @SuppressLint("MissingPermission")
    private fun applyRequest() {
        if (!LocationPermissions.hasForeground(this)) return
        val selected = prefs.getLocationAccuracy()
        if (selected != LocationAccuracy.AUTO) powerMode = selected
        prefs.setLocationPowerMode(powerMode.storageValue)
        val priority = if (powerMode == LocationAccuracy.LOW) Priority.PRIORITY_BALANCED_POWER_ACCURACY else Priority.PRIORITY_HIGH_ACCURACY
        val interval = when (powerMode) {
            LocationAccuracy.LOW -> prefs.getLocationIntervalSec().coerceIn(30, 60) * 1_000L
            LocationAccuracy.HIGH -> prefs.getLocationIntervalSec().coerceIn(1, 2) * 1_000L
            else -> prefs.getLocationIntervalSec().coerceIn(5, 10) * 1_000L
        }
        val requestedDistance = prefs.getLocationMinDistance()
        val distance = if (requestedDistance >= 0) requestedDistance.toFloat() else when (powerMode) {
            LocationAccuracy.LOW -> 20f
            LocationAccuracy.HIGH -> 0f
            else -> 0f
        }
        val request = LocationRequest.Builder(priority, interval)
            .setMinUpdateIntervalMillis(interval)
            .setMinUpdateDistanceMeters(distance)
            .setWaitForAccurateLocation(false)
            .build()
        mainHandler.post {
            if (!active) return@post
            val generation = ++requestGeneration
            runCatching { fusedClient.removeLocationUpdates(passiveCallback) }
            if (powerMode == LocationAccuracy.LOW) {
                runCatching {
                    val passive = LocationRequest.Builder(Priority.PRIORITY_PASSIVE, 30_000L).setMinUpdateIntervalMillis(1_000L).build()
                    fusedClient.requestLocationUpdates(passive, passiveCallback, Looper.getMainLooper())
                }
            }
            runCatching {
                fusedClient.removeLocationUpdates(locationCallback).addOnCompleteListener {
                    if (!active || generation != requestGeneration || !LocationPermissions.hasForeground(this)) return@addOnCompleteListener
                    runCatching {
                        fusedClient.requestLocationUpdates(request, locationCallback, Looper.getMainLooper())
                            .addOnSuccessListener {
                                if (active && generation == requestGeneration) {
                                    isRecording = true
                                    prefs.setLocationServiceHeartbeat(System.currentTimeMillis())
                                }
                            }
                            .addOnFailureListener { error ->
                                if (generation == requestGeneration) {
                                    isRecording = false
                                    Log.e(TAG, "Location request failed: ${error.message}")
                                    prefs.setLocationUploadFailure(error.message ?: "location_unavailable")
                                    startPlatformFallback()
                                    updateNotification()
                                }
                            }
                    }.onFailure { error ->
                        isRecording = false
                        Log.e(TAG, "Location request rejected: ${error.message}")
                    }
                }
            }.onFailure { error ->
                isRecording = false
                Log.e(TAG, "Location request reset failed: ${error.message}")
            }
        }
    }

    private val passiveCallback = object : LocationCallback() {
        override fun onLocationResult(result: LocationResult) {
            if (active && powerMode == LocationAccuracy.LOW) result.locations.forEach { onMeasurement(it, "passive") }
        }
    }

    private val locationCallback = object : LocationCallback() {
        override fun onLocationResult(result: LocationResult) {
            if (!active) return
            result.locations.forEach { location ->
                // Any delivery proves the request is alive, even when the point is
                // skipped as "standing still". The watchdog must not treat that as
                // a broken stream.
                onMeasurement(location, "fused")
            }
            updateNotification()
        }
    }

    /** Writes the measurement to disk before anything is sent anywhere. */
    @SuppressLint("MissingPermission")
    private fun startPlatformFallback() {
        if (!active || !LocationPermissions.hasForeground(this)) return
        runCatching {
            val provider = if (powerMode == LocationAccuracy.LOW && locationManager.isProviderEnabled(LocationManager.NETWORK_PROVIDER)) LocationManager.NETWORK_PROVIDER else if (locationManager.isProviderEnabled(LocationManager.GPS_PROVIDER)) LocationManager.GPS_PROVIDER else LocationManager.NETWORK_PROVIDER
            locationManager.requestLocationUpdates(provider, 10_000L, 0f, fallbackListener, Looper.getMainLooper())
            isRecording = true
        }.onFailure { Log.e(TAG, "Platform location request failed", it) }
    }

    private fun persist(location: Location, origin: String = "fused"): LocationEvent? {
        if (!location.hasAccuracy() && location.latitude == 0.0 && location.longitude == 0.0) return null
        if (location.latitude !in -90.0..90.0 || location.longitude !in -180.0..180.0) return null
        // Drop spoofed / impossible teleports while recording (e.g. Kyiv→USA in minutes).
        if (location.hasSpeed() && location.speed > MAX_SPEED_MPS) return null
        if (!lastPersistedLat.isNaN() && lastPersistedAtMs > 0L) {
            val results = FloatArray(1)
            Location.distanceBetween(lastPersistedLat, lastPersistedLon, location.latitude, location.longitude, results)
            val dtSec = (location.time - lastPersistedAtMs).coerceAtLeast(1L) / 1000.0
            if (results[0] / dtSec > MAX_SPEED_MPS) return null
        }
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
            provider = location.provider,
            trackingMode = powerMode.storageValue,
            origin = origin,
        )
        val stored = LocationEventStore(this).insert(prefs.getLocationDeviceId(), event)
        if (!stored) {
            Log.w(TAG, "Duplicate location event ignored")
            return null
        }
        lastFixElapsedMs = SystemClock.elapsedRealtime()
        lastPersistedLat = event.latitude
        lastPersistedLon = event.longitude
        lastPersistedAtMs = location.time
        prefs.setLocationLastFixAt(event.recordedAtMs)
        prefs.setLocationServiceHeartbeat(event.recordedAtMs)
        return event
    }

    /**
     * Recovers from the cases where the platform silently stops delivering fixes:
     * a request lost after a Play Services restart, restricted while-in-use access
     * after a background start, or a long phone call / Doze window. Re-issuing the
     * request and asking for one immediate fix costs nothing when updates flow.
     */
    private fun startWatchdog() {
        if (watchdogJob?.isActive == true) return
        watchdogJob = scope.launch {
            while (isActive) {
                delay(WATCHDOG_INTERVAL_MS)
                if (!active) continue
                val now = System.currentTimeMillis()
                prefs.setLocationServiceHeartbeat(now)
                val intervalMs = prefs.getLocationIntervalSec().coerceAtLeast(5) * 1000L
                // Two missed sampling windows is already a real gap: recover it
                // before the user notices missing points on the map.
                val allowedGap = maxOf(intervalMs * 2, STALE_FIX_FLOOR_MS)
                val elapsed = SystemClock.elapsedRealtime()
                if (!shouldRecoverLocation(elapsed, lastFixElapsedMs, lastRecoveryElapsedMs, allowedGap)) continue
                lastRecoveryElapsedMs = elapsed
                Log.w(TAG, "No location fix for ${allowedGap / 1000}s, re-requesting")
                applyRequest()
            }
        }
    }

    @SuppressLint("MissingPermission")
    private fun requestFallbackFix() {
        if (!LocationPermissions.hasForeground(this)) return
        runCatching {
            fusedClient.getCurrentLocation(Priority.PRIORITY_BALANCED_POWER_ACCURACY, null)
                .addOnSuccessListener { location ->
                    if (!active || location == null) return@addOnSuccessListener
                    lastFixElapsedMs = SystemClock.elapsedRealtime()
                    persist(location)
                    updateNotification()
                }
                .addOnFailureListener { error ->
                    Log.w(TAG, "Fallback location failed: ${error.message}")
                }
        }
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
        scope.launch {
            val manager = getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager ?: return@launch
            val notification = runCatching { buildNotification(pendingCount(), todayRouteMeters()) }.getOrNull() ?: return@launch
            if (active) manager.notify(NOTIFICATION_ID, notification)
        }
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

    /**
     * Day or night colours for the card. The app can force a theme that differs
     * from the system night mode, so RemoteViews never rely on resource
     * qualifiers alone: the palette is chosen here and applied explicitly.
     */
    private data class NotificationPalette(
        val card: Int,
        val chip: Int,
        val iconTile: Int,
        val stop: Int,
        val label: Int,
        val value: Int,
        val pending: Int,
    )

    private fun notificationPalette(): NotificationPalette {
        val dark = when (prefs.getThemeMode()) {
            "dark" -> true
            "light" -> false
            else -> (resources.configuration.uiMode and Configuration.UI_MODE_NIGHT_MASK) ==
                Configuration.UI_MODE_NIGHT_YES
        }
        return if (dark) {
            NotificationPalette(
                card = R.drawable.notif_card_bg_night,
                chip = R.drawable.notif_chip_bg_night,
                iconTile = R.drawable.notif_icon_bg_night,
                stop = R.drawable.notif_stop_bg_night,
                label = R.color.notif_night_label,
                value = R.color.notif_night_value,
                pending = R.color.notif_night_pending,
            )
        } else {
            NotificationPalette(
                card = R.drawable.notif_card_bg_day,
                chip = R.drawable.notif_chip_bg_day,
                iconTile = R.drawable.notif_icon_bg_day,
                stop = R.drawable.notif_stop_bg_day,
                label = R.color.notif_day_label,
                value = R.color.notif_day_value,
                pending = R.color.notif_day_pending,
            )
        }
    }

    private fun buildNotification(pending: Int, routeMeters: Double): Notification {
        val open = PendingIntent.getActivity(
            this,
            0,
            Intent(this, MainActivity::class.java),
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
        val palette = notificationPalette()
        val labelColor = ContextCompat.getColor(this, palette.label)
        val valueColor = ContextCompat.getColor(this, palette.value)
        val pendingColor = ContextCompat.getColor(this, palette.pending)
        val fullViews = RemoteViews(packageName, R.layout.notification_tracking).apply {
            setTextViewText(R.id.notif_route, distanceText)
            setTextViewText(R.id.notif_pending, pending.toString())
            setTextViewText(R.id.notif_sync, prefs.getLocationUploadOkAt()?.let { android.text.format.DateFormat.getTimeFormat(this@LocationTrackingService).format(java.util.Date(it)) } ?: getString(R.string.tracking_notification_never))
            setTextColor(R.id.notif_sync_label, labelColor)
            setTextColor(R.id.notif_sync, valueColor)
            setInt(R.id.notif_sync_chip, "setBackgroundResource", palette.chip)
            setTextColor(R.id.notif_route_label, labelColor)
            setTextColor(R.id.notif_pending_label, labelColor)
            setTextColor(R.id.notif_route, valueColor)
            setTextColor(R.id.notif_pending, pendingColor)
            setInt(R.id.notif_card, "setBackgroundResource", palette.card)
            setInt(R.id.notif_route_chip, "setBackgroundResource", palette.chip)
            setInt(R.id.notif_pending_chip, "setBackgroundResource", palette.chip)
            setInt(R.id.notif_stop, "setBackgroundResource", palette.stop)
            setOnClickPendingIntent(R.id.notif_stop, stop)
            // Tapping the rest of the card opens the app; the stop action wins
            // because its click intent is attached to the child view.
            setOnClickPendingIntent(R.id.notif_root, open)
        }
        // The collapsed shade is much shorter, so it gets its own single-row
        // layout instead of a clipped version of the full card.
        val compactViews = RemoteViews(packageName, R.layout.notification_tracking_compact).apply {
            setTextViewText(
                R.id.notif_compact_text,
                getString(R.string.tracking_notification_compact_values, distanceText, pending),
            )
            setTextColor(R.id.notif_compact_text, valueColor)
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

    /**
     * Screen-on is the cheapest reliable moment to recover: it follows the end of
     * a call, leaving Doze, or reopening the app. Re-issuing the request there is
     * what closes the gaps that used to appear after phone calls.
     */
    private fun registerScreenReceiver() {
        if (screenReceiver != null) return
        val receiver = object : BroadcastReceiver() {
            override fun onReceive(context: Context?, intent: Intent?) {
                if (!active) return
                val gap = maxOf(prefs.getLocationIntervalSec().coerceAtLeast(5) * 2_000L, STALE_FIX_FLOOR_MS)
                val elapsed = SystemClock.elapsedRealtime()
                if (shouldRecoverLocation(elapsed, lastFixElapsedMs, lastRecoveryElapsedMs, gap)) {
                    lastRecoveryElapsedMs = elapsed
                    applyRequest()
                    requestFallbackFix()
                }
            }
        }
        ContextCompat.registerReceiver(
            this,
            receiver,
            IntentFilter(Intent.ACTION_SCREEN_ON),
            ContextCompat.RECEIVER_NOT_EXPORTED,
        )
        screenReceiver = receiver
    }

    private fun unregisterScreenReceiver() {
        screenReceiver?.let { runCatching { unregisterReceiver(it) } }
        screenReceiver = null
    }

    override fun onConfigurationChanged(newConfig: Configuration) {
        super.onConfigurationChanged(newConfig)
        // System light/dark switch ("auto" theme) has to repaint the card.
        updateNotification()
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
        private const val WATCHDOG_INTERVAL_MS = 30_000L
        private const val STALE_FIX_FLOOR_MS = 2L * 60 * 1000
        private const val UPLOAD_THROTTLE_MS = 5_000L
        private const val MIN_MOVE_M = 10f
        private const val STATIONARY_REPEAT_MS = 5L * 60 * 1000
        /** 300 km/h — above this is treated as GPS spoof / teleport. */
        private const val MAX_SPEED_MPS = 300f / 3.6f
        const val CHANNEL_ID = "location_tracking"
        const val ACTION_START = "orbit.nex.steps.location.START"
        const val ACTION_STOP = "orbit.nex.steps.location.STOP"
        const val ACTION_PAUSE = "orbit.nex.steps.location.PAUSE"
        const val ACTION_REFRESH = "orbit.nex.steps.location.REFRESH"

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

        /** Rebuilds the ongoing notification, e.g. after the theme changed. */
        fun refreshNotification(context: Context) {
            val prefs = PreferencesManager(context)
            // Only refresh a live recording: starting the service for a paused or
            // stopped session would keep a foreground service alive for nothing.
            if (!prefs.isLocationTrackingEnabled() || prefs.isLocationTrackingPaused()) return
            val intent = Intent(context, LocationTrackingService::class.java).setAction(ACTION_REFRESH)
            runCatching { context.startService(intent) }
                .onFailure { Log.e(TAG, "Failed to refresh tracking notification: ${it.message}", it) }
        }
    }
}

internal fun shouldRecoverLocation(now: Long, lastFix: Long, lastRecovery: Long, allowedGap: Long): Boolean =
    now - lastFix >= allowedGap && now - lastRecovery >= allowedGap
