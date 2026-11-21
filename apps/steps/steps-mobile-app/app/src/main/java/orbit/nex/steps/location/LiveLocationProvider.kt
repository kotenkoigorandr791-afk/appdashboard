package orbit.nex.steps.location

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.hardware.Sensor
import android.hardware.SensorEvent
import android.hardware.SensorEventListener
import android.hardware.SensorManager
import android.location.Location
import android.os.Looper
import android.util.Log
import androidx.core.content.ContextCompat
import com.google.android.gms.location.FusedLocationProviderClient
import com.google.android.gms.location.LocationCallback
import com.google.android.gms.location.LocationRequest
import com.google.android.gms.location.LocationResult
import com.google.android.gms.location.LocationServices
import com.google.android.gms.location.Priority

/** Latest position shown on the map for the user, independent of recording. */
data class LiveLocation(
    val latitude: Double,
    val longitude: Double,
    val accuracyM: Float?,
    val speedMps: Float?,
    val bearingDeg: Float?,
    val atMs: Long,
)

/**
 * Mutable frame the map overlay reads while drawing.
 *
 * The overlay is painted from [fix]/[headingDeg] on every map invalidation, so a
 * new GPS fix does not need to rebuild every GeoJSON overlay or recompose the
 * whole screen.
 */
class LiveLocationState {
    @Volatile
    var fix: LiveLocation? = null

    @Volatile
    var headingDeg: Float? = null
}

/**
 * Foreground position and direction for the map's "my location" layer.
 *
 * The recording service samples on its own schedule and may be limited in the
 * background, so the map keeps a dedicated high-accuracy request while it is
 * visible. Direction comes from the movement bearing when the user is walking
 * or driving, and from the compass when they stand still, which is what makes
 * the arrow point where the phone is facing.
 */
class LiveLocationProvider(
    context: Context,
    private val state: LiveLocationState,
    private val onFix: (LiveLocation) -> Unit,
    /** Compass ticks repaint the puck without being treated as new positions. */
    private val onDirectionChanged: () -> Unit = {},
) {
    private val appContext = context.applicationContext
    private val fusedClient: FusedLocationProviderClient =
        LocationServices.getFusedLocationProviderClient(appContext)
    private val sensorManager = appContext.getSystemService(Context.SENSOR_SERVICE) as? SensorManager

    private var started = false
    private var compass: Sensor? = null
    private var smoothedAzimuth: Float? = null

    private val locationCallback = object : LocationCallback() {
        override fun onLocationResult(result: LocationResult) {
            result.lastLocation?.let { accept(it) }
        }
    }

    private val compassListener = object : SensorEventListener {
        override fun onSensorChanged(event: SensorEvent) {
            if (event.accuracy == SensorManager.SENSOR_STATUS_UNRELIABLE) return
            val azimuth = when (event.sensor.type) {
                Sensor.TYPE_ROTATION_VECTOR -> azimuthFromRotationVector(event.values)
                Sensor.TYPE_ORIENTATION -> event.values.getOrNull(0)
                else -> null
            } ?: return
            smoothedAzimuth = HeadingResolver.smooth(smoothedAzimuth, azimuth)
            val next = HeadingResolver.resolve(
                bearingDeg = state.fix?.bearingDeg,
                speedMps = state.fix?.speedMps,
                azimuthDeg = smoothedAzimuth,
            ) ?: return
            if (Math.abs((next - (state.headingDeg ?: Float.NaN))) < 0.5f) return
            state.headingDeg = next
            onDirectionChanged()
        }

        override fun onAccuracyChanged(sensor: Sensor?, accuracy: Int) = Unit
    }

    @android.annotation.SuppressLint("MissingPermission")
    fun start() {
        if (started) return
        if (!hasPermission()) return
        started = true
        val request = LocationRequest.Builder(Priority.PRIORITY_HIGH_ACCURACY, UPDATE_INTERVAL_MS)
            .setMinUpdateIntervalMillis(UPDATE_INTERVAL_MS / 2)
            .setMinUpdateDistanceMeters(0f)
            .setWaitForAccurateLocation(false)
            .build()
        runCatching {
            fusedClient.requestLocationUpdates(request, locationCallback, Looper.getMainLooper())
        }.onFailure { Log.w(TAG, "Live location request failed: ${it.message}") }
        // Show the last known position immediately instead of an empty map while
        // the first fix is being acquired.
        runCatching {
            fusedClient.lastLocation.addOnSuccessListener { location ->
                if (location != null && state.fix == null) accept(location)
            }
        }
        registerCompass()
    }

    /** One-off high-accuracy probe; used by the locate button. */
    @android.annotation.SuppressLint("MissingPermission")
    fun refresh() {
        if (!hasPermission()) return
        runCatching {
            fusedClient.getCurrentLocation(Priority.PRIORITY_HIGH_ACCURACY, null)
                .addOnSuccessListener { it?.let(::accept) }
                .addOnFailureListener { Log.w(TAG, "Current location failed: ${it.message}") }
        }
    }

    fun stop() {
        if (!started) return
        started = false
        runCatching { fusedClient.removeLocationUpdates(locationCallback) }
        unregisterCompass()
    }

    private fun hasPermission(): Boolean =
        ContextCompat.checkSelfPermission(appContext, Manifest.permission.ACCESS_FINE_LOCATION) ==
            PackageManager.PERMISSION_GRANTED ||
            ContextCompat.checkSelfPermission(appContext, Manifest.permission.ACCESS_COARSE_LOCATION) ==
            PackageManager.PERMISSION_GRANTED

    private fun accept(location: Location) {
        if (!location.hasAccuracy() && location.latitude == 0.0 && location.longitude == 0.0) return
        val fix = LiveLocation(
            latitude = location.latitude,
            longitude = location.longitude,
            accuracyM = if (location.hasAccuracy()) location.accuracy else null,
            speedMps = if (location.hasSpeed()) location.speed else null,
            bearingDeg = if (location.hasBearing()) location.bearing else null,
            atMs = location.time,
        )
        state.fix = fix
        state.headingDeg = HeadingResolver.resolve(
            bearingDeg = fix.bearingDeg,
            speedMps = fix.speedMps,
            azimuthDeg = smoothedAzimuth,
        ) ?: state.headingDeg
        onFix(fix)
    }

    private fun registerCompass() {
        val manager = sensorManager ?: return
        compass = manager.getDefaultSensor(Sensor.TYPE_ROTATION_VECTOR)
            ?: manager.getDefaultSensor(Sensor.TYPE_ORIENTATION)
        val sensor = compass ?: return
        runCatching {
            manager.registerListener(compassListener, sensor, SensorManager.SENSOR_DELAY_UI)
        }.onFailure { Log.w(TAG, "Compass unavailable: ${it.message}") }
    }

    private fun unregisterCompass() {
        sensorManager?.let { manager -> runCatching { manager.unregisterListener(compassListener) } }
        smoothedAzimuth = null
    }

    companion object {
        private const val TAG = "LiveLocationProvider"
        private const val UPDATE_INTERVAL_MS = 1_000L

        private fun azimuthFromRotationVector(values: FloatArray): Float? {
            if (values.size < 3) return null
            val rotation = FloatArray(9)
            val orientation = FloatArray(3)
            return runCatching {
                SensorManager.getRotationMatrixFromVector(rotation, values)
                SensorManager.getOrientation(rotation, orientation)
                Math.toDegrees(orientation[0].toDouble()).toFloat()
            }.getOrNull()
        }

    }
}
