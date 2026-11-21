package orbit.nex.steps.screens

import orbit.nex.steps.ui.theme.Spacing
import orbit.nex.steps.ui.theme.semantic
import android.graphics.Canvas
import android.location.Location
import android.graphics.Color as AndroidColor
import android.location.LocationManager
import android.graphics.Paint
import android.graphics.Path
import android.graphics.Point
import android.graphics.RectF
import android.graphics.drawable.GradientDrawable
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowLeft
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.filled.CloudDone
import androidx.compose.material.icons.filled.CloudOff
import androidx.compose.material.icons.filled.Explore
import androidx.compose.material.icons.filled.Layers
import androidx.compose.material.icons.filled.LocalFireDepartment
import androidx.compose.material.icons.filled.Map
import androidx.compose.material.icons.filled.MyLocation
import androidx.compose.material.icons.filled.Satellite
import androidx.compose.material.icons.filled.Speed
import androidx.compose.material.icons.filled.TravelExplore
import android.content.Intent
import android.net.Uri
import android.view.MotionEvent
import android.widget.Button
import android.widget.ImageView
import android.widget.ProgressBar
import android.widget.TextView
import androidx.compose.material3.Checkbox
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.SegmentedButton
import androidx.compose.material3.SegmentedButtonDefaults
import androidx.compose.material3.SingleChoiceSegmentedButtonRow
import androidx.compose.material3.Surface
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.graphics.luminance
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.osmdroid.config.Configuration
import org.osmdroid.tileprovider.tilesource.OnlineTileSourceBase
import org.osmdroid.tileprovider.MapTileProviderBasic
import org.osmdroid.util.GeoPoint
import org.osmdroid.util.MapTileIndex
import org.osmdroid.views.MapView
import org.osmdroid.views.overlay.Marker
import org.osmdroid.views.overlay.Overlay
import org.osmdroid.views.overlay.Polyline
import org.osmdroid.views.overlay.TilesOverlay
import orbit.nex.steps.BuildConfig
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.R
import orbit.nex.steps.location.GeoBoundingBox
import orbit.nex.steps.location.GeoHeatLine
import orbit.nex.steps.location.GeoLatLon
import orbit.nex.steps.location.GeoMapData
import orbit.nex.steps.location.GeoMapRepository
import orbit.nex.steps.location.GeoTrip
import orbit.nex.steps.location.GeoPointFeature
import orbit.nex.steps.location.GeoSpeedSegment
import orbit.nex.steps.location.StoredLocationEvent
import orbit.nex.steps.location.LocationPermissionState
import orbit.nex.steps.location.LocationPermissions
import orbit.nex.steps.location.LocationEventStore
import orbit.nex.steps.location.LocationTrackingManager
import orbit.nex.steps.location.TrackingState
import orbit.nex.steps.location.LiveLocationProvider
import orbit.nex.steps.location.LiveLocationState
import orbit.nex.steps.location.PlacePhotoCache
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.util.Locale
import org.json.JSONObject

internal enum class MapStyle(val key: String, val titleRes: Int) {
    NORMAL("normal", R.string.map_style_standard),
    SATELLITE("satellite", R.string.map_style_satellite),
    ;

    companion object {
        fun fromKey(key: String): MapStyle = if (key == SATELLITE.key) SATELLITE else NORMAL
    }
}

private object TrackingMapCache {
    private val data = mutableMapOf<String, Pair<Long, GeoMapData>>()
    fun clear() = data.clear()

    fun get(configUrl: String?, day: LocalDate): GeoMapData? = data["${configUrl.orEmpty()}:${day}"]
        ?.takeIf { android.os.SystemClock.elapsedRealtime() - it.first < 20_000L }?.second

    fun put(configUrl: String?, day: LocalDate, value: GeoMapData) {
        if (value.error == null) data["${configUrl.orEmpty()}:${day}"] = android.os.SystemClock.elapsedRealtime() to value
    }
}

/** GeoJSON layers the map can draw, matching the server's layer keys. */
private enum class GeoLayer(val key: String, val titleRes: Int) {
    TRIPS("trips", R.string.map_layer_trips),
    STOPS("stops", R.string.map_layer_stops),
    PLACES("places", R.string.map_layer_places),
    PHOTOS("photos", R.string.map_layer_photos),
    POINTS("points", R.string.map_layer_points),
    HEATMAP("heatmap", R.string.map_layer_heatmap),
    SPEED("speed", R.string.map_layer_speed),
}

/**
 * Street basemap without an API key.
 *
 * The OSM Mapnik tiles and the retired CARTO basemaps answered the app with
 * blank 1x1 images (public rate limiting / withdrawn free endpoints), which is
 * why the map looked like an empty background. Esri's World Street Map has
 * z/y/x order like World Imagery and can be colour-inverted for dark mode.
 */
internal object EsriStreet : OnlineTileSourceBase(
    "EsriWorldStreetMap", 0, 19, 256, ".png",
    arrayOf("https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/"),
    "Esri, HERE, Garmin, OpenStreetMap contributors",
) {
    override fun getTileURLString(pMapTileIndex: Long): String =
        baseUrl + MapTileIndex.getZoom(pMapTileIndex) + "/" +
            MapTileIndex.getY(pMapTileIndex) + "/" + MapTileIndex.getX(pMapTileIndex)
}

internal object EsriLight : OnlineTileSourceBase(
    "NexStepsOsmStreet", 0, 19, 256, ".png",
    arrayOf("https://tile.openstreetmap.org/"),
    "© OpenStreetMap contributors",
) {
    override fun getTileURLString(pMapTileIndex: Long): String =
        baseUrl + MapTileIndex.getZoom(pMapTileIndex) + "/" +
            MapTileIndex.getX(pMapTileIndex) + "/" + MapTileIndex.getY(pMapTileIndex) + ".png"
}

/** Esri imagery uses z/y/x order instead of the usual z/x/y. */
internal object EsriSatellite : OnlineTileSourceBase(
    "EsriWorldImagery", 0, 19, 256, ".jpg",
    arrayOf("https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/"),
    "Esri, Maxar, Earthstar Geographics",
) {
    override fun getTileURLString(pMapTileIndex: Long): String =
        baseUrl + MapTileIndex.getZoom(pMapTileIndex) + "/" +
            MapTileIndex.getY(pMapTileIndex) + "/" + MapTileIndex.getX(pMapTileIndex)
}

private object EsriLightLabels : OnlineTileSourceBase(
    "EsriLightGrayLabels", 0, 19, 256, ".png",
    arrayOf("https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Reference/MapServer/tile/"),
    "Esri, HERE, Garmin, © OpenStreetMap contributors",
) {
    override fun getTileURLString(pMapTileIndex: Long): String =
        baseUrl + MapTileIndex.getZoom(pMapTileIndex) + "/" +
            MapTileIndex.getY(pMapTileIndex) + "/" + MapTileIndex.getX(pMapTileIndex)
}

private object EsriTransportation : OnlineTileSourceBase(
    "EsriTransportation", 0, 19, 256, ".png",
    arrayOf("https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Transportation/MapServer/tile/"),
    "Esri, OpenStreetMap contributors",
) {
    override fun getTileURLString(pMapTileIndex: Long): String =
        baseUrl + MapTileIndex.getZoom(pMapTileIndex) + "/" +
            MapTileIndex.getY(pMapTileIndex) + "/" + MapTileIndex.getX(pMapTileIndex)
}

/**
 * Heatmap drawn from the server's weighted route lines.
 *
 * The `heatmap` layer is a set of LineStrings (one per repeatedly travelled
 * street) rather than isolated cells, so each line gets a wide warm halo and a
 * narrow bright core whose width and alpha follow `intensity`.
 */
private class HeatmapOverlay(
    private val lines: List<GeoHeatLine>,
) : Overlay() {

    private val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        style = Paint.Style.STROKE
        strokeCap = Paint.Cap.ROUND
        strokeJoin = Paint.Join.ROUND
    }
    private val screenPoint = Point()

    override fun draw(canvas: Canvas, mapView: MapView, shadow: Boolean) {
        if (shadow || lines.isEmpty()) return
        val projection = mapView.projection
        val density = mapView.resources.displayMetrics.density
        lines.forEach { line ->
            val path = Path()
            var started = false
            line.path.forEach { coordinate ->
                projection.toPixels(GeoPoint(coordinate.lat, coordinate.lon), screenPoint)
                if (started) {
                    path.lineTo(screenPoint.x.toFloat(), screenPoint.y.toFloat())
                } else {
                    path.moveTo(screenPoint.x.toFloat(), screenPoint.y.toFloat())
                    started = true
                }
            }
            if (!started) return@forEach
            val intensity = line.intensity.coerceIn(0f, 1f)
            paint.color = AndroidColor.argb((55 + 85 * intensity).toInt(), 255, 120, 40)
            paint.strokeWidth = (5f + 11f * intensity) * density
            canvas.drawPath(path, paint)
            paint.color = AndroidColor.argb((120 + 90 * intensity).toInt(), 255, 212, 74)
            paint.strokeWidth = (1.4f + 2.2f * intensity) * density
            canvas.drawPath(path, paint)
        }
    }
}

/**
 * "Where am I" puck, drawn like the one in consumer map apps: an accuracy halo,
 * a white-ringed blue dot, and a translucent cone plus arrow that show which way
 * the phone is facing. It is repainted on every location or compass update and
 * survives the overlay rebuild that follows a day reload.
 */
private class UserLocationOverlay(
    private val state: LiveLocationState,
) : Overlay(), KeepOverlay {

    private val halo = Paint(Paint.ANTI_ALIAS_FLAG)
    private val fill = Paint(Paint.ANTI_ALIAS_FLAG)
    private val cone = Paint(Paint.ANTI_ALIAS_FLAG)
    private val point = Point()
    private val conePath = Path()
    private val arrowPath = Path()

    override fun draw(canvas: Canvas, mapView: MapView, shadow: Boolean) {
        if (shadow) return
        val fix = state.fix ?: return
        val projection = mapView.projection
        projection.toPixels(GeoPoint(fix.latitude, fix.longitude), point)
        val x = point.x.toFloat()
        val y = point.y.toFloat()
        val density = mapView.resources.displayMetrics.density

        fix.accuracyM?.takeIf { it > 0f }?.let { accuracy ->
            val radius = projection.metersToPixels(accuracy, fix.latitude, fix.longitude)
                .coerceIn(7f * density, 900f * density)
            halo.style = Paint.Style.FILL
            halo.color = AndroidColor.argb(26, 33, 150, 243)
            canvas.drawCircle(x, y, radius, halo)
            halo.style = Paint.Style.STROKE
            halo.strokeWidth = 1.4f * density
            halo.color = AndroidColor.argb(70, 33, 150, 243)
            canvas.drawCircle(x, y, radius, halo)
        }

        val heading = state.headingDeg
        if (heading != null) {
            val radius = 54f * density
            // Wedge pointing up, rotated to the current heading.
            conePath.reset()
            conePath.moveTo(0f, 0f)
            conePath.lineTo(-radius * 0.438f, -radius * 0.899f)
            conePath.arcTo(RectF(-radius, -radius, radius, radius), -90f - 26f, 52f)
            conePath.close()
            cone.color = AndroidColor.argb(42, 33, 150, 243)
            canvas.save()
            canvas.translate(x, y)
            canvas.rotate(heading)
            canvas.drawPath(conePath, cone)
            canvas.restore()
        }

        fill.style = Paint.Style.FILL
        fill.color = AndroidColor.WHITE
        canvas.drawCircle(x, y, 11f * density, fill)
        fill.color = AndroidColor.rgb(33, 150, 243)
        canvas.drawCircle(x, y, 8f * density, fill)

        // Direction arrow sits on top of the dot, like a map app puck.
        if (heading != null) {
            arrowPath.reset()
            arrowPath.moveTo(0f, -7f * density)
            arrowPath.lineTo(-4.6f * density, 4.2f * density)
            arrowPath.lineTo(0f, 2.2f * density)
            arrowPath.lineTo(4.6f * density, 4.2f * density)
            arrowPath.close()
            canvas.save()
            canvas.translate(x, y)
            canvas.rotate(heading)
            fill.color = AndroidColor.WHITE
            canvas.drawPath(arrowPath, fill)
            canvas.restore()
        }
    }
}

/** Slowest to fastest buckets returned by the server `speed` layer. */
private val SPEED_BUCKET_COLORS = intArrayOf(
    AndroidColor.rgb(56, 189, 248),
    AndroidColor.rgb(34, 197, 94),
    AndroidColor.rgb(234, 179, 8),
    AndroidColor.rgb(249, 115, 22),
    AndroidColor.rgb(239, 68, 68),
    AndroidColor.rgb(168, 85, 247),
)

private fun dot(color: Int, border: Int, sizeDp: Int): GradientDrawable =
    GradientDrawable().apply {
        shape = GradientDrawable.OVAL
        setColor(color)
        setStroke(2, border)
        setSize(sizeDp, sizeDp)
    }

/**
 * Tracking tab: the movement map for one day.
 *
 * The day is chosen with the arrows in the floating header, the layers button
 * switches basemap style and overlays (including the heatmap), and the floating
 * switch starts or stops recording. Detailed tracking settings live in the
 * general settings screen.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TrackingMapScreen(modifier: Modifier = Modifier, isVisible: Boolean = true) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val manager = remember { LocationTrackingManager(context) }
    val repository = remember { GeoMapRepository(prefs, cacheDir = context.cacheDir) }
    // osmdroid defaults to a generic user agent that the public tile servers
    // answer with blank 1x1 images, which is why the map looked empty. Identify
    // the app explicitly before the first tile request.
    remember {
        Configuration.getInstance().apply {
            userAgentValue = "${context.packageName}/${BuildConfig.VERSION_NAME} (NexSteps)"
            osmdroidBasePath = java.io.File(context.filesDir, "osmdroid")
            osmdroidTileCache = java.io.File(context.filesDir, "osmdroid/tiles")
            tileFileSystemCacheMaxBytes = 1024L * 1024 * 1024
            tileFileSystemCacheTrimBytes = 800L * 1024 * 1024
            tileDownloadThreads = 4
        }
    }

    var trackingState by remember { mutableStateOf(manager.state()) }
    var trackingNotice by remember { mutableStateOf<String?>(null) }
    var trackingNoticeError by remember { mutableStateOf(false) }
    fun reportTracking() {
        trackingState = manager.state()
        trackingNoticeError = trackingState == TrackingState.PERMISSION_MISSING || trackingState == TrackingState.NOT_CONNECTED
        trackingNotice = context.getString(when (trackingState) {
            TrackingState.RUNNING -> R.string.tracking_status_running
            TrackingState.PAUSED -> R.string.tracking_status_paused
            TrackingState.PERMISSION_MISSING -> R.string.tracking_status_permission
            TrackingState.NOT_CONNECTED -> R.string.tracking_status_no_connection
            TrackingState.OFF -> R.string.tracking_status_off
        })
    }
    LaunchedEffect(trackingNotice) { if (trackingNotice != null) { delay(3500); trackingNotice = null } }
    var permissionDenied by remember { mutableStateOf(LocationPermissions.state(context) == LocationPermissionState.DENIED) }
    var pendingUploads by remember { mutableStateOf(LocationTrackingManager.pendingCount(context)) }
    var lastUploadAt by remember { mutableStateOf(prefs.getLocationUploadOkAt()) }
    var lastError by remember { mutableStateOf(prefs.getLocationUploadError()) }

    // A stored day from the future has nothing to show, so it opens on today.
    var selectedDay by remember { mutableStateOf(LocalDate.now()) }
    // Server data and the local outbox stay separate so that a sync landing in
    // the background never duplicates the measurements still waiting for it.
    var remoteData by remember { mutableStateOf(GeoMapData()) }
    var localEvents by remember { mutableStateOf<List<StoredLocationEvent>>(emptyList()) }
    val mapRevision by orbit.nex.steps.location.GeoMapChanges.revision.collectAsState()
    var seenRevision by remember { mutableStateOf(mapRevision) }
    val data = remember(remoteData, localEvents) { remoteData.withLocalEvents(localEvents) }
    var loading by remember { mutableStateOf(false) }
    var mapStyle by remember { mutableStateOf(MapStyle.fromKey(prefs.getMapBasemap())) }
    var layers by remember { mutableStateOf(prefs.getMapLayers()) }
    var showLayers by remember { mutableStateOf(false) }
    // Live puck state: position, accuracy and heading. It is intentionally
    // separate from the recorded day data, so it works with tracking off too.
    val liveState = remember { LiveLocationState() }
    val userOverlay = remember { UserLocationOverlay(liveState) }
    val followState = remember { mutableStateOf(prefs.getMapCamera() == null) }
    val latestFollow = rememberUpdatedState(followState.value)
    val latestDay = rememberUpdatedState(selectedDay)
    var hintVisible by remember { mutableStateOf(true) }
    LaunchedEffect(selectedDay) { hintVisible = true; delay(3_000); hintVisible = false }
    var showPanels by remember { mutableStateOf(false) }
    var fittedDay by remember { mutableStateOf<LocalDate?>(null) }
    var restoringCamera by remember { mutableStateOf(prefs.getMapCamera() != null) }

    val darkTheme = MaterialTheme.colorScheme.background.luminance() < 0.5f
    val resolvedStyle = mapStyle
    val mapBackground = when (resolvedStyle) {
        MapStyle.SATELLITE -> Color(0xFF083744)
        MapStyle.NORMAL -> if (darkTheme) Color(0xFF12181C) else Color(0xFFE8EDF2)
    }

    // Server state is polled so the switch and sync box track the service.
    LaunchedEffect(isVisible) {
        if (!isVisible) return@LaunchedEffect
        while (true) {
            manager.reconcile()
            trackingState = manager.state()
            permissionDenied = LocationPermissions.state(context) == LocationPermissionState.DENIED
            pendingUploads = LocationTrackingManager.pendingCount(context)
            lastUploadAt = prefs.getLocationUploadOkAt()
            lastError = prefs.getLocationUploadError()
            // Unsynced measurements appear on the map as soon as they are
            // recorded, and disappear once the server confirms them.
            localEvents = runCatching { LocationEventStore(context).eventsForDay(selectedDay) }.getOrDefault(localEvents)
            delay(2_000)
        }
    }

    val config = prefs.getWebhookConfigs().firstOrNull { it.isEnabled }

    LaunchedEffect(config?.url, isVisible) {
        val active = config ?: return@LaunchedEffect
        if (!isVisible) return@LaunchedEffect
        var lastFullReconcile = 0L
        while (true) {
            val now = android.os.SystemClock.elapsedRealtime()
            val force = lastFullReconcile == 0L || now - lastFullReconcile >= 60_000L
            if (repository.reconcileDeleted(active, context, force) && force) lastFullReconcile = now
            delay(LIVE_MAP_REFRESH_MS)
        }
    }

    LaunchedEffect(selectedDay, config, mapRevision, isVisible) {
        if (mapRevision != seenRevision) {
            TrackingMapCache.clear()
            remoteData = GeoMapData()
            localEvents = emptyList()
            seenRevision = mapRevision
        }
        if (!isVisible) return@LaunchedEffect
        localEvents = runCatching { LocationEventStore(context).eventsForDay(selectedDay) }.getOrDefault(localEvents)
        val cached = TrackingMapCache.get(config?.url, selectedDay)
        if (cached == null) remoteData = GeoMapData()
        if (cached != null) {
            remoteData = cached
            loading = false
            return@LaunchedEffect
        }
        config?.let { repository.cached(it, selectedDay) }?.let { remoteData = it }
        loading = config != null
        val remote = config?.let { repository.loadDay(it, selectedDay) } ?: GeoMapData()
        TrackingMapCache.put(config?.url, selectedDay, remote)
        remoteData = remote
        loading = false
    }

    // While today is open, re-read the server so points recorded elsewhere show
    // up without switching days. The endpoint caches for 20s, so 10s polling
    // stays cheap and still feels live.
    LaunchedEffect(selectedDay, config?.url, mapRevision, isVisible) {
        if (!isVisible) return@LaunchedEffect
        val activeConfig = config ?: return@LaunchedEffect
        while (true) {
            delay(LIVE_MAP_REFRESH_MS)
            if (selectedDay != LocalDate.now()) continue
            val remote = repository.loadDay(activeConfig, selectedDay)
            if (remote.error != null) continue
            TrackingMapCache.put(activeConfig.url, selectedDay, remote)
            remoteData = remote
        }
    }

    val permissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) {
        if (LocationPermissions.hasForeground(context)) manager.start()
        reportTracking()
    }

    val mapView = remember {
        val camera = prefs.getMapCamera()
        // With no saved camera the map used to open on an arbitrary point in the
        // Sahara at street zoom, so the user saw a featureless beige frame. Start
        // from the device's last position when it is known, otherwise show the
        // whole world so the map visibly works.
        val lastKnown = runCatching {
            if (LocationPermissions.hasForeground(context)) {
                val manager = context.getSystemService(LocationManager::class.java)
                listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER)
                    .firstNotNullOfOrNull { provider -> runCatching { manager?.getLastKnownLocation(provider) }.getOrNull() }
            } else null
        }.getOrNull()
        MapView(context).apply {
            setMultiTouchControls(true)
            overlays.add(MapRotationOverlay(this))
            if (!darkTheme) setTilesScaledToDpi(false)
            zoomController.setVisibility(org.osmdroid.views.CustomZoomButtonsController.Visibility.NEVER)
            setHorizontalMapRepetitionEnabled(false)
            setVerticalMapRepetitionEnabled(false)
            when {
                camera != null -> {
                    controller.setZoom(camera.third)
                    controller.setCenter(GeoPoint(camera.first, camera.second))
                }
                lastKnown != null -> {
                    controller.setZoom(15.0)
                    controller.setCenter(GeoPoint(lastKnown.latitude, lastKnown.longitude))
                }
                else -> {
                    controller.setZoom(3.0)
                    controller.setCenter(GeoPoint(20.0, 0.0))
                }
            }
        }
    }
    val labelsOverlay = remember {
        TilesOverlay(MapTileProviderBasic(context).apply { setTileSource(EsriLightLabels) }, context)
    }
    val roadsOverlay = remember {
        TilesOverlay(MapTileProviderBasic(context).apply { setTileSource(EsriTransportation) }, context)
    }
    DisposableEffect(Unit) {
        onDispose {
            val center = mapView.mapCenter
            prefs.setMapCamera(center.latitude, center.longitude, mapView.zoomLevelDouble)
            prefs.setMapDay(selectedDay.toString())
            mapView.onDetach()
        }
    }

    DisposableEffect(isVisible) {
        if (isVisible) mapView.onResume()
        onDispose { if (isVisible) mapView.onPause() }
    }

    LaunchedEffect(resolvedStyle, darkTheme) {
        mapView.setTileSource(
            when (resolvedStyle) {
                MapStyle.SATELLITE -> EsriSatellite
                MapStyle.NORMAL -> if (darkTheme) EsriStreet else EsriLight
            }
        )
        // Same street tiles in both themes; dark mode inverts them the way the
        // OSM basemap used to be inverted.
        mapView.overlayManager.tilesOverlay.setColorFilter(
            if (resolvedStyle == MapStyle.NORMAL && darkTheme) TilesOverlay.INVERT_COLORS else null
        )
        mapView.setBackgroundColor(
            when (resolvedStyle) {
                MapStyle.SATELLITE -> AndroidColor.rgb(8, 55, 68)
                MapStyle.NORMAL -> if (darkTheme) AndroidColor.rgb(18, 24, 28) else AndroidColor.rgb(232, 237, 242)
            }
        )
        mapView.invalidate()
    }

    // Foreground live position. The recording service runs on its own schedule,
    // so the visible map keeps its own high-accuracy stream for the puck.
    val liveProvider = remember {
        LiveLocationProvider(
            context = context,
            state = liveState,
            onFix = { fix ->
                mapView.invalidate()
                if (latestFollow.value && latestDay.value == LocalDate.now()) {
                    mapView.controller.animateTo(GeoPoint(fix.latitude, fix.longitude))
                }
            },
            onDirectionChanged = { mapView.invalidate() },
        )
    }
    DisposableEffect(Unit) {
        mapView.overlays.add(userOverlay)
        // Touching the map means the user wants to browse, so stop following.
        mapView.setOnTouchListener { _, event ->
            if (event.actionMasked == android.view.MotionEvent.ACTION_DOWN) followState.value = false
            false
        }
        onDispose { mapView.overlays.remove(userOverlay) }
    }
    DisposableEffect(isVisible) {
        if (isVisible) liveProvider.start()
        onDispose { liveProvider.stop() }
    }

    // Paint on every data/layer change. Photo network fetch uses a stable key so
    // the ~10s map poll does not cancel in-flight Immich/place downloads.
    fun paintOverlays(fit: Boolean) {
        rebuildOverlays(mapView, data, layers, darkTheme, prefs, config)
        mapView.overlays.remove(roadsOverlay)
        mapView.overlays.remove(labelsOverlay)
        if (resolvedStyle == MapStyle.SATELLITE) mapView.overlays.add(0, roadsOverlay)
        val box = data.bbox
        if (fit && !restoringCamera && !followState.value && !data.isEmpty && box != null && fittedDay != selectedDay) {
            fittedDay = selectedDay
            fitBoundingBox(mapView, box)
        }
    }
    LaunchedEffect(data, layers, resolvedStyle, darkTheme, config?.url) {
        paintOverlays(fit = true)
    }
    val placePhotoKeys = remember(data, layers) {
        if (GeoLayer.PLACES.key !in layers) emptyList()
        else data.points.asSequence()
            .filter { it.layer == "visit" || it.layer == "place" }
            .mapNotNull { f ->
                when {
                    !f.osmType.isNullOrBlank() && f.osmId != null -> "${f.osmType}:${f.osmId}"
                    !f.photoUrl.isNullOrBlank() -> "url:${f.photoUrl}"
                    else -> null
                }
            }
            .sorted()
            .toList()
    }
    val immichPhotoKeys = remember(data, layers) {
        if (GeoLayer.PHOTOS.key !in layers) emptyList()
        else data.points.asSequence()
            .filter { it.layer == "photo_cluster" && !it.assetId.isNullOrBlank() }
            .map { it.assetId!! }
            .sorted()
            .toList()
    }
    LaunchedEffect(placePhotoKeys, immichPhotoKeys, config?.url) {
        val active = config ?: return@LaunchedEffect
        if (placePhotoKeys.isEmpty() && immichPhotoKeys.isEmpty()) return@LaunchedEffect
        val gate = kotlinx.coroutines.sync.Semaphore(6)
        var loaded = 0
        kotlinx.coroutines.coroutineScope {
            if (GeoLayer.PLACES.key in layers) {
                data.points.filter {
                    (it.layer == "visit" || it.layer == "place") &&
                        ((!it.osmType.isNullOrBlank() && it.osmId != null) || !it.photoUrl.isNullOrBlank())
                }.forEach { feature ->
                    launch(Dispatchers.IO) {
                        gate.acquire()
                        try {
                            val bitmap = PlacePhotoCache.ensureFeature(
                                context, prefs, active, feature.osmType, feature.osmId, feature.photoUrl,
                            )
                            if (bitmap != null) {
                                withContext(Dispatchers.Main) {
                                    loaded++
                                    if (loaded == 1 || loaded % 3 == 0) paintOverlays(fit = false)
                                }
                            }
                        } finally {
                            gate.release()
                        }
                    }
                }
            }
            if (GeoLayer.PHOTOS.key in layers) {
                data.points.filter { it.layer == "photo_cluster" && !it.assetId.isNullOrBlank() }.forEach { feature ->
                    launch(Dispatchers.IO) {
                        gate.acquire()
                        try {
                            val bitmap = PlacePhotoCache.ensureImmich(context, prefs, active, feature.assetId!!)
                            if (bitmap != null) {
                                withContext(Dispatchers.Main) {
                                    loaded++
                                    if (loaded == 1 || loaded % 3 == 0) paintOverlays(fit = false)
                                }
                            }
                        } finally {
                            gate.release()
                        }
                    }
                }
            }
        }
        paintOverlays(fit = false)
    }

    fun locate() {
        followState.value = true
        restoringCamera = false
        fittedDay = selectedDay
        val fix = liveState.fix
        if (fix != null) {
            mapView.controller.setZoom(maxOf(mapView.zoomLevelDouble, 16.0))
            mapView.controller.animateTo(GeoPoint(fix.latitude, fix.longitude))
        } else {
            liveProvider.refresh()
        }
    }

    val locateLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) {
        if (LocationPermissions.hasForeground(context)) locate()
    }

    fun fitToData() {
        val bbox = data.bbox
        if (!data.isEmpty && bbox != null) {
            fitBoundingBox(mapView, bbox)
            return
        }
        val manager = context.getSystemService(LocationManager::class.java)
        val location = listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER)
            .firstNotNullOfOrNull { provider -> runCatching { manager?.getLastKnownLocation(provider) }.getOrNull() }
        if (location != null) {
            mapView.controller.setZoom(13.5)
            mapView.controller.animateTo(GeoPoint(location.latitude, location.longitude))
        }
    }

    BoxWithConstraints(modifier.fillMaxSize().background(mapBackground)) {
        // Floating UI is sized from the map viewport rather than from fixed dp:
        // on a 320dp wide phone the default 48dp controls and pills covered a
        // third of the map. Narrow screens get compact pills, smaller controls
        // and tighter edge insets.
        val compact = maxWidth < 360.dp
        val edge = if (compact) 8.dp else 12.dp
        val uiScale = ((maxWidth - edge * 2) / 388.dp).coerceIn(0.7f, 1f)
        val controlSize = (44.dp * uiScale).coerceAtLeast(30.dp)
        val controlIconSize = (22.dp * uiScale).coerceAtLeast(15.dp)

        AndroidView(factory = { mapView }, modifier = Modifier.fillMaxSize())
        Text(if (resolvedStyle == MapStyle.NORMAL && !darkTheme) "© OpenStreetMap contributors" else "© Esri, HERE, Garmin, © OpenStreetMap contributors", modifier = Modifier.align(Alignment.BottomStart).padding(start = 4.dp, bottom = 2.dp).background(MaterialTheme.colorScheme.surface.copy(alpha = 0.8f)).padding(horizontal = 3.dp), style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurface)

        // Floating header: what is recording and which day is shown. Both pills
        // always share one row and split the available width, so they fit even
        // on the narrowest screen instead of stacking into two lines. Their
        // labels ellipsize rather than push the row out of the viewport.
        Box(
            Modifier
                .align(Alignment.TopCenter)
                .fillMaxWidth()
                .padding(start = edge, end = edge, top = edge),
        ) {
            val switchPill: @Composable (Modifier) -> Unit = { pillModifier ->
                TrackingSwitchPill(
                    tracking = trackingState == TrackingState.RUNNING || trackingState == TrackingState.PAUSED,
                    paused = trackingState == TrackingState.PAUSED,
                    compact = compact,
                    modifier = pillModifier,
                    onCheckedChange = { enabled ->
                        if (enabled) {
                            if (!LocationPermissions.hasForeground(context) || permissionDenied) {
                                permissionLauncher.launch(LocationPermissions.foregroundPermissions())
                            } else {
                                manager.start()
                            }
                        } else {
                            manager.stop()
                        }
                        if (!enabled || LocationPermissions.hasForeground(context)) reportTracking()
                    },
                )
            }
            val dayPill: @Composable (Modifier) -> Unit = { pillModifier ->
                DayPill(
                    selectedDay = selectedDay,
                    compact = compact,
                    modifier = pillModifier,
                    onPick = { selectedDay = it; fittedDay = null; restoringCamera = false },
                    onPrevious = {
                        selectedDay = selectedDay.minusDays(1)
                        fittedDay = null
                        restoringCamera = false
                    },
                    onNext = {
                        if (selectedDay.isBefore(LocalDate.now())) {
                            selectedDay = selectedDay.plusDays(1)
                            fittedDay = null
                            restoringCamera = false
                        }
                    },
                )
            }
            Row(
                Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(Spacing.sm),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                switchPill(Modifier.weight(1f))
                dayPill(Modifier.weight(1f))
            }
        }

        // Map controls, scaled with the viewport so five buttons do not take up
        // half of a small map. North-up sits above the side-panel entry.
        Column(
            Modifier.align(Alignment.CenterEnd).padding(end = edge),
            verticalArrangement = Arrangement.spacedBy((10.dp * uiScale).coerceAtLeast(6.dp)),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            MapControlButton(
                icon = Icons.Filled.Explore,
                contentDescription = stringResource(R.string.map_north_up),
                size = controlSize,
                iconSize = controlIconSize,
                onClick = {
                    mapView.mapOrientation = 0f
                    mapView.invalidate()
                },
            )
            MapControlButton(
                icon = Icons.Filled.TravelExplore,
                contentDescription = stringResource(R.string.map_panels_open),
                size = controlSize,
                iconSize = controlIconSize,
                onClick = { showPanels = true },
            )
            MapControlButton(
                icon = Icons.Filled.MyLocation,
                contentDescription = stringResource(R.string.map_locate),
                size = controlSize,
                iconSize = controlIconSize,
                onClick = {
                    if (LocationPermissions.hasForeground(context)) locate()
                    else locateLauncher.launch(LocationPermissions.foregroundPermissions())
                },
            )
            MapControlButton(
                icon = Icons.Filled.Layers,
                contentDescription = stringResource(R.string.map_layers),
                size = controlSize,
                iconSize = controlIconSize,
                onClick = { showLayers = true },
            )
        }

        if (data.isEmpty && !loading && data.error == null && hintVisible) {
            Surface(
                shape = RoundedCornerShape(16.dp),
                color = MaterialTheme.colorScheme.surface.copy(alpha = 0.82f),
                modifier = Modifier.align(Alignment.Center),
            ) {
                Text(
                    stringResource(R.string.map_no_data),
                    style = MaterialTheme.typography.bodyMedium,
                    modifier = Modifier.padding(horizontal = 18.dp, vertical = Spacing.md),
                )
            }
        }

        trackingNotice?.let { orbit.nex.steps.components.SavedToast(it, Modifier.align(Alignment.BottomCenter).padding(bottom = 90.dp), trackingNoticeError) { trackingNotice = null } }

        // The legend and the sync state stack together along the bottom edge.
        // The status box spans the free width so it does not leave a large empty
        // strip on the right side of the map.
        Column(
            Modifier
                .align(Alignment.BottomStart)
                .fillMaxWidth()
                .padding(start = edge, end = edge, bottom = edge + 22.dp),
            verticalArrangement = Arrangement.spacedBy(Spacing.sm),
        ) {
            if (GeoLayer.SPEED.key in layers) {
                SpeedLegend()
            }
            SyncStatusBox(
                loading = loading,
                error = data.error ?: lastError,
                lastUploadAt = lastUploadAt,
                pending = pendingUploads,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }

    if (showPanels) {
        LocationPanelsSheet(onDismiss = { showPanels = false }, onTripSelected = { day, path ->
            showPanels = false
            followState.value = false
            restoringCamera = false
            selectedDay = day
            fittedDay = day
            val box = orbit.nex.steps.location.GeoBoundingBox(path.maxOf { it.lat }, path.minOf { it.lat }, path.maxOf { it.lon }, path.minOf { it.lon })
            fitBoundingBox(mapView, box)
        })
    }

    if (showLayers) {
        ModalBottomSheet(onDismissRequest = { showLayers = false }) {
            Column(Modifier.verticalScroll(rememberScrollState()).navigationBarsPadding().padding(horizontal = 18.dp).padding(bottom = Spacing.xl), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                Text(stringResource(R.string.map_style_title), style = MaterialTheme.typography.titleMedium)
                SingleChoiceSegmentedButtonRow(Modifier.fillMaxWidth()) {
                    MapStyle.entries.forEachIndexed { index, style ->
                        SegmentedButton(
                            selected = mapStyle == style,
                            onClick = {
                                mapStyle = style
                                prefs.setMapBasemap(style.key)
                            },
                            shape = SegmentedButtonDefaults.itemShape(index, MapStyle.entries.size),
                        ) {
                            Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm), verticalAlignment = Alignment.CenterVertically) {
                                Icon(
                                    imageVector = when (style) {
                                        MapStyle.NORMAL -> Icons.Filled.Map
                                        MapStyle.SATELLITE -> Icons.Filled.Satellite
                                    },
                                    contentDescription = null,
                                    modifier = Modifier.size(20.dp),
                                )
                                Text(stringResource(style.titleRes), maxLines = 1)
                            }
                        }
                    }
                }
                Text(stringResource(R.string.map_layers_title), style = MaterialTheme.typography.titleMedium)
                GeoLayer.entries.forEach { layer ->
                    Row(
                        Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Text(stringResource(layer.titleRes), modifier = Modifier.weight(1f))
                        Switch(
                            checked = layer.key in layers,
                            onCheckedChange = { checked ->
                                layers = if (checked) layers + layer.key else layers - layer.key
                                prefs.setMapLayers(layers)
                            },
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun TrackingSwitchPill(
    tracking: Boolean,
    paused: Boolean,
    compact: Boolean,
    onCheckedChange: (Boolean) -> Unit,
    modifier: Modifier = Modifier,
) {
    Surface(
        shape = RoundedCornerShape(percent = 50),
        color = MaterialTheme.colorScheme.surface.copy(alpha = 0.82f),
        shadowElevation = 6.dp,
        modifier = modifier,
    ) {
        Row(
            Modifier
                .fillMaxWidth()
                .padding(
                    start = if (compact) 10.dp else 14.dp,
                    end = if (compact) 4.dp else 6.dp,
                    top = Spacing.xxs,
                    bottom = Spacing.xxs,
                ),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(if (compact) 6.dp else 8.dp),
        ) {
            Box(
                Modifier
                    .size(if (compact) 7.dp else 9.dp)
                    .clip(CircleShape)
                    .background(
                        when {
                            tracking && !paused -> MaterialTheme.colorScheme.primary
                            tracking -> MaterialTheme.semantic.warning
                            else -> MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.5f)
                        }
                    )
            )
            Text(
                stringResource(R.string.map_tracking),
                style = if (compact) MaterialTheme.typography.labelMedium else MaterialTheme.typography.labelLarge,
                fontWeight = FontWeight.Medium,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
                modifier = Modifier.weight(1f),
            )
            // The M3 switch is a fixed 52x32dp widget. On a compact pill it is
            // scaled down inside a smaller box, otherwise it would leave no room
            // for the label next to the day selector.
            Box(
                Modifier.size(
                    width = if (compact) 40.dp else 52.dp,
                    height = if (compact) 26.dp else 32.dp,
                ),
                contentAlignment = Alignment.Center,
            ) {
                Switch(
                    checked = tracking,
                    onCheckedChange = onCheckedChange,
                    modifier = if (compact) Modifier.scale(0.78f) else Modifier,
                )
            }
        }
    }
}

@Composable
internal fun DayPill(
    selectedDay: LocalDate,
    compact: Boolean,
    onPrevious: () -> Unit,
    onNext: () -> Unit,
    modifier: Modifier = Modifier,
    onPick: ((LocalDate) -> Unit)? = null,
) {
    var picking by remember { mutableStateOf(false) }
    if (picking && onPick != null) orbit.nex.steps.components.AppDatePickerDialog(selectedDay, null, LocalDate.now(), { picking = false }) { onPick(it); picking = false }
    val today = LocalDate.now()
    val formatter = remember { DateTimeFormatter.ofPattern("d MMM, EEE", Locale.getDefault()) }
    val label = when (selectedDay) {
        today -> stringResource(R.string.map_today)
        today.minusDays(1) -> stringResource(R.string.map_yesterday)
        else -> selectedDay.format(formatter)
    }
    Surface(
        shape = RoundedCornerShape(percent = 50),
        color = MaterialTheme.colorScheme.surface.copy(alpha = 0.82f),
        shadowElevation = 4.dp,
        modifier = modifier,
    ) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            DayArrowButton(
                icon = Icons.AutoMirrored.Filled.KeyboardArrowLeft,
                contentDescription = stringResource(R.string.map_previous_day),
                compact = compact,
                onClick = onPrevious,
            )
            Text(
                label,
                style = if (compact) MaterialTheme.typography.labelMedium else MaterialTheme.typography.labelLarge,
                modifier = Modifier.weight(1f).padding(horizontal = Spacing.xxs).clickable(enabled = onPick != null) { picking = true },
                textAlign = TextAlign.Center,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            DayArrowButton(
                icon = Icons.AutoMirrored.Filled.KeyboardArrowRight,
                contentDescription = stringResource(R.string.map_next_day),
                compact = compact,
                enabled = selectedDay.isBefore(today),
                onClick = onNext,
            )
        }
    }
}

@Composable
private fun DayArrowButton(
    icon: ImageVector,
    contentDescription: String,
    compact: Boolean,
    onClick: () -> Unit,
    enabled: Boolean = true,
) {
    // Plain clickable box instead of IconButton: IconButton keeps a 48dp
    // minimum touch target, which is too wide for the compact day pill.
    Box(
        Modifier
            .size(if (compact) 30.dp else 40.dp)
            .clip(CircleShape)
            .clickable(enabled = enabled, onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        Icon(
            icon,
            contentDescription = contentDescription,
            modifier = Modifier.size(if (compact) 18.dp else 24.dp),
            tint = if (enabled) {
                MaterialTheme.colorScheme.onSurface
            } else {
                MaterialTheme.colorScheme.onSurface.copy(alpha = 0.38f)
            },
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun MapControlButton(
    icon: ImageVector,
    contentDescription: String,
    size: Dp,
    iconSize: Dp,
    active: Boolean = false,
    onClick: () -> Unit,
) {
    Surface(
        onClick = onClick,
        shape = RoundedCornerShape(if (size < 40.dp) 10.dp else 14.dp),
        color = if (active) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surface.copy(alpha = 0.82f),
        shadowElevation = 4.dp,
        modifier = Modifier.size(size),
    ) {
        Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            Icon(
                icon,
                contentDescription = contentDescription,
                tint = if (active) MaterialTheme.colorScheme.onPrimaryContainer else MaterialTheme.colorScheme.onSurface,
                modifier = Modifier.size(iconSize),
            )
        }
    }
}

@Composable
private fun SyncStatusBox(
    loading: Boolean,
    error: String?,
    lastUploadAt: Long?,
    pending: Int,
    modifier: Modifier = Modifier,
) {
    val failed = error != null
    Surface(
        shape = RoundedCornerShape(16.dp),
        color = MaterialTheme.colorScheme.surface,
        shadowElevation = 4.dp,
        modifier = modifier,
    ) {
        Row(
            Modifier.padding(horizontal = 14.dp, vertical = Spacing.sm),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(Spacing.sm),
        ) {
            if (loading) {
                CircularProgressIndicator(Modifier.size(16.dp), strokeWidth = 2.dp)
            } else {
                Icon(
                    if (failed) Icons.Filled.CloudOff else Icons.Filled.CloudDone,
                    contentDescription = null,
                    tint = if (failed) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.primary,
                    modifier = Modifier.size(18.dp),
                )
            }
            Column {
                Text(
                    when {
                        failed -> stringResource(R.string.map_sync_error, error.orEmpty())
                        lastUploadAt != null -> stringResource(
                            R.string.map_sync_ok,
                            orbit.nex.steps.location.formatLocationUploadTime(lastUploadAt),
                        )
                        else -> stringResource(R.string.map_sync_never)
                    },
                    style = MaterialTheme.typography.bodySmall,
                    fontWeight = FontWeight.Medium,
                    maxLines = 1,
                )
                Text(
                    stringResource(R.string.map_sync_pending, pending),
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 1,
                )
            }
        }
    }
}

/** Replaces every overlay, so a reload never stacks duplicate lines or dots. */
internal interface KeepOverlay

internal class MapRotationOverlay(mapView: MapView) : org.osmdroid.views.overlay.gestures.RotationGestureOverlay(mapView), KeepOverlay

internal enum class MapHitKind { PHOTO, VISIT, POINT }

internal data class MapHitPayload(
    val kind: MapHitKind,
    val feature: GeoPointFeature? = null,
)

internal class StyledInfoWindow(mapView: MapView, private val dark: Boolean = false) : org.osmdroid.views.overlay.infowindow.InfoWindow(R.layout.map_info_window, mapView) {
    override fun onOpen(item: Any?) {
        val marker = item as? Marker
        val bg = android.graphics.drawable.GradientDrawable().apply {
            setColor(if (dark) AndroidColor.rgb(38, 39, 43) else AndroidColor.WHITE)
            cornerRadius = 16f * mView.resources.displayMetrics.density
            setStroke((mView.resources.displayMetrics.density).toInt().coerceAtLeast(1), if (dark) AndroidColor.DKGRAY else AndroidColor.LTGRAY)
        }
        mView.background = bg
        mView.findViewById<TextView>(R.id.info_title).setTextColor(if (dark) AndroidColor.WHITE else AndroidColor.BLACK)
        mView.findViewById<TextView>(R.id.info_snippet).setTextColor(if (dark) AndroidColor.LTGRAY else AndroidColor.DKGRAY)
        mView.findViewById<TextView>(R.id.info_title).text = marker?.title.orEmpty()
        mView.findViewById<TextView>(R.id.info_snippet).apply {
            text = marker?.snippet.orEmpty()
            visibility = if (marker?.snippet.isNullOrBlank()) android.view.View.GONE else android.view.View.VISIBLE
        }
        mView.setOnClickListener { close() }
    }

    override fun onClose() {}
}

internal class PhotoInfoWindow(
    mapView: MapView,
    private val dark: Boolean = false,
    private val prefs: PreferencesManager? = null,
    private val config: orbit.nex.steps.WebhookConfig? = null,
) : org.osmdroid.views.overlay.infowindow.InfoWindow(R.layout.map_photo_info_window, mapView) {
    private var loadJob: kotlinx.coroutines.Job? = null
    private var boundMarker: Marker? = null

    override fun onOpen(item: Any?) {
        val marker = item as? Marker ?: return
        boundMarker = marker
        val feature = (marker.relatedObject as? MapHitPayload)?.feature
        val density = mView.resources.displayMetrics.density
        val bg = android.graphics.drawable.GradientDrawable().apply {
            setColor(if (dark) AndroidColor.rgb(18, 20, 26) else AndroidColor.WHITE)
            cornerRadius = 18f * density
            setStroke(density.toInt().coerceAtLeast(1), if (dark) AndroidColor.rgb(55, 58, 68) else AndroidColor.LTGRAY)
        }
        mView.background = bg
        val frame = mView.findViewById<android.widget.FrameLayout>(R.id.photo_frame)
        val image = mView.findViewById<ImageView>(R.id.photo_image)
        val loading = mView.findViewById<ProgressBar>(R.id.photo_loading)
        val immich = mView.findViewById<Button>(R.id.photo_immich)
        fun sizeBubble(w: Int, h: Int) {
            image.layoutParams = android.widget.FrameLayout.LayoutParams(w, h)
            frame.layoutParams = android.widget.LinearLayout.LayoutParams(w, h)
            immich.layoutParams = (immich.layoutParams as android.widget.LinearLayout.LayoutParams).apply {
                width = w
                height = (40 * density).toInt()
            }
            mView.layoutParams = mView.layoutParams?.apply {
                width = android.view.ViewGroup.LayoutParams.WRAP_CONTENT
                height = android.view.ViewGroup.LayoutParams.WRAP_CONTENT
            }
            mView.measure(
                android.view.View.MeasureSpec.makeMeasureSpec(0, android.view.View.MeasureSpec.UNSPECIFIED),
                android.view.View.MeasureSpec.makeMeasureSpec(0, android.view.View.MeasureSpec.UNSPECIFIED),
            )
            mView.layout(0, 0, mView.measuredWidth, mView.measuredHeight)
        }
        fun fitBitmap(bitmap: android.graphics.Bitmap) {
            val maxW = (280 * density).toInt()
            val maxH = (360 * density).toInt()
            val scale = minOf(1f, maxW.toFloat() / bitmap.width, maxH.toFloat() / bitmap.height)
            val w = (bitmap.width * scale).toInt().coerceAtLeast(1)
            val h = (bitmap.height * scale).toInt().coerceAtLeast(1)
            image.setImageBitmap(bitmap)
            image.visibility = android.view.View.VISIBLE
            loading.visibility = android.view.View.GONE
            sizeBubble(w, h)
            // Re-anchor after exact measure so the bubble opens on the first tap.
            mMapView?.post {
                if (isOpen) {
                    boundMarker?.showInfoWindow()
                    mMapView?.invalidate()
                }
            }
        }
        val cached = feature?.assetId?.let { PlacePhotoCache.memoryOrDisk(mView.context, PlacePhotoCache.immichKey(it)) }
        if (cached != null) {
            fitBitmap(cached)
        } else {
            image.setImageDrawable(null)
            image.visibility = android.view.View.INVISIBLE
            loading.visibility = android.view.View.VISIBLE
            val side = (160 * density).toInt()
            sizeBubble(side, side)
            val assetId = feature?.assetId
            val activePrefs = prefs
            val activeConfig = config
            if (!assetId.isNullOrBlank() && activePrefs != null && activeConfig != null) {
                loadJob?.cancel()
                loadJob = kotlinx.coroutines.CoroutineScope(kotlinx.coroutines.Dispatchers.Main.immediate).launch {
                    val bitmap = withContext(kotlinx.coroutines.Dispatchers.IO) {
                        PlacePhotoCache.ensureImmich(mView.context, activePrefs, activeConfig, assetId)
                    }
                    if (bitmap != null && isOpen) fitBitmap(bitmap)
                    else loading.visibility = android.view.View.GONE
                }
            } else {
                loading.visibility = android.view.View.GONE
            }
        }
        immich.setOnClickListener {
            openImmichPhoto(mView.context, feature?.immichAssetId, feature?.immichOpenUrl)
        }
    }

    override fun onClose() {
        loadJob?.cancel()
        loadJob = null
        boundMarker = null
    }
}

internal fun openImmichPhoto(context: android.content.Context, immichAssetId: String?, openUrl: String?) {
    val id = immichAssetId?.trim().orEmpty()
    val urls = buildList {
        openUrl?.trim()?.takeIf { it.isNotEmpty() }?.let(::add)
        if (id.isNotEmpty()) add("https://my.immich.app/photos/$id")
    }
    if (urls.isEmpty()) return
    val packages = listOf("app.alextran.immich", "app.alextran.immich.offline", "com.immichapp")
    for (pkg in packages) {
        for (url in urls) {
            val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url)).setPackage(pkg).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            if (intent.resolveActivity(context.packageManager) != null) {
                runCatching { context.startActivity(intent) }
                return
            }
        }
    }
    runCatching {
        context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(urls.first())).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
    }
}

internal fun styleMarker(marker: Marker, mapView: MapView, dark: Boolean, coordsFallback: Boolean = true) {
    if (coordsFallback && marker.title.isNullOrBlank()) {
        marker.title = mapView.context.getString(R.string.map_coordinates)
    }
    if (coordsFallback) {
        marker.snippet = mapView.context.getString(R.string.map_coordinates_values, marker.position.latitude, marker.position.longitude)
    }
    marker.setInfoWindow(StyledInfoWindow(mapView, dark))
}

/** Photos beat visits beat raw points. Handles the tap before rotation/markers steal it. */
internal class MapPriorityTapOverlay : Overlay(), KeepOverlay {
    var photos: List<Marker> = emptyList()
    var visits: List<Marker> = emptyList()
    var points: List<Marker> = emptyList()
    private var downX = 0f
    private var downY = 0f
    private var tracking = false
    private var openedOnUp = false

    private fun hitMarker(x: Float, y: Float, mapView: MapView): Marker? {
        val density = mapView.resources.displayMetrics.density
        fun nearest(markers: List<Marker>, maxPx: Float): Marker? {
            var best: Marker? = null
            var bestDist = maxPx * maxPx
            val tap = android.graphics.Point()
            mapView.projection.unrotateAndScalePoint(x.toInt(), y.toInt(), tap)
            val pixel = android.graphics.Point()
            markers.forEach { marker ->
                mapView.projection.toPixels(marker.position, pixel)
                val dx = (pixel.x - tap.x).toFloat()
                val dy = (pixel.y - tap.y).toFloat()
                val d = dx * dx + dy * dy
                if (d <= bestDist) {
                    bestDist = d
                    best = marker
                }
            }
            return best
        }
        return nearest(photos, 72f * density)
            ?: nearest(visits, 48f * density)
            ?: nearest(points, 28f * density)
    }

    private fun openHit(x: Float, y: Float, mapView: MapView): Boolean {
        val hit = hitMarker(x, y, mapView)
        org.osmdroid.views.overlay.infowindow.InfoWindow.closeAllInfoWindowsOn(mapView)
        if (hit == null) {
            mapView.invalidate()
            return false
        }
        hit.showInfoWindow()
        mapView.post {
            if (hit.isInfoWindowShown) {
                hit.showInfoWindow()
                mapView.invalidate()
            }
        }
        return true
    }

    override fun onTouchEvent(event: MotionEvent, mapView: MapView): Boolean {
        when (event.actionMasked) {
            MotionEvent.ACTION_DOWN -> {
                downX = event.x
                downY = event.y
                tracking = hitMarker(event.x, event.y, mapView) != null
                openedOnUp = false
            }
            MotionEvent.ACTION_MOVE -> {
                if (tracking) {
                    val slop = 24f * mapView.resources.displayMetrics.density
                    if (kotlin.math.abs(event.x - downX) > slop || kotlin.math.abs(event.y - downY) > slop) {
                        tracking = false
                    }
                }
            }
            MotionEvent.ACTION_UP -> {
                if (tracking) {
                    openedOnUp = openHit(event.x, event.y, mapView)
                    tracking = false
                    if (openedOnUp) return true
                }
            }
            MotionEvent.ACTION_CANCEL -> tracking = false
        }
        return false
    }

    override fun onSingleTapUp(e: MotionEvent, mapView: MapView): Boolean {
        if (openedOnUp) {
            openedOnUp = false
            return true
        }
        return openHit(e.x, e.y, mapView)
    }

    override fun onSingleTapConfirmed(e: MotionEvent, mapView: MapView): Boolean = true
}

internal fun configureOsm(context: android.content.Context) {
    Configuration.getInstance().apply {
        userAgentValue = "${context.packageName}/${BuildConfig.VERSION_NAME} (NexSteps)"
        osmdroidBasePath = java.io.File(context.filesDir, "osmdroid")
        osmdroidTileCache = java.io.File(context.filesDir, "osmdroid/tiles")
        tileFileSystemCacheMaxBytes = 1024L * 1024 * 1024
        tileFileSystemCacheTrimBytes = 800L * 1024 * 1024
        tileDownloadThreads = 4
    }
}

private class BasemapLabels(context: android.content.Context) : TilesOverlay(
    MapTileProviderBasic(context).apply { setTileSource(EsriLightLabels) }, context
), KeepOverlay

private class BasemapRoads(context: android.content.Context) : TilesOverlay(
    MapTileProviderBasic(context).apply { setTileSource(EsriTransportation) }, context
), KeepOverlay

internal fun applyBasemap(mapView: MapView, style: MapStyle, darkTheme: Boolean) {
    mapView.overlays.removeAll { it is BasemapRoads || it is BasemapLabels }
    mapView.setTileSource(if (style == MapStyle.SATELLITE) EsriSatellite else if (darkTheme) EsriStreet else EsriLight)
    mapView.overlayManager.tilesOverlay.setColorFilter(
        if (style == MapStyle.NORMAL && darkTheme) TilesOverlay.INVERT_COLORS else null
    )
    mapView.setBackgroundColor(
        when (style) {
            MapStyle.SATELLITE -> AndroidColor.rgb(8, 55, 68)
            MapStyle.NORMAL -> if (darkTheme) AndroidColor.rgb(18, 24, 28) else AndroidColor.rgb(232, 237, 242)
        }
    )
    mapView.invalidate()
}

internal fun rebuildOverlays(
    mapView: MapView,
    data: GeoMapData,
    layers: Set<String>,
    dark: Boolean = false,
    prefs: PreferencesManager? = null,
    config: orbit.nex.steps.WebhookConfig? = null,
) {
    mapView.overlays.removeAll { it !is KeepOverlay }
    val resources = mapView.resources
    val density = resources.displayMetrics.density
    val tripColor = AndroidColor.rgb(46, 125, 255)
    val stopColor = AndroidColor.rgb(240, 150, 40)
    val placeColor = AndroidColor.rgb(120, 80, 220)
    val visitColor = AndroidColor.rgb(40, 170, 110)
    // Visits vs Immich photos must read as different rings at a glance.
    val visitBorder = AndroidColor.rgb(14, 165, 233)
    val photoBorder = AndroidColor.rgb(245, 158, 11)
    val pointColor = AndroidColor.rgb(46, 125, 255)
    val pendingPointColor = AndroidColor.rgb(168, 112, 0)
    val speedOn = GeoLayer.SPEED.key in layers && data.speed.isNotEmpty()

    fun addPolyline(path: List<GeoLatLon>, color: Int, widthDp: Float, dashed: Boolean = false) {
        val line = Polyline(mapView)
        path.forEach { line.addPoint(GeoPoint(it.lat, it.lon)) }
        line.outlinePaint.color = color
        line.outlinePaint.strokeWidth = widthDp * density
        line.outlinePaint.strokeCap = android.graphics.Paint.Cap.ROUND
        line.outlinePaint.strokeJoin = android.graphics.Paint.Join.ROUND
        if (dashed) line.outlinePaint.pathEffect = android.graphics.DashPathEffect(floatArrayOf(14f * density, 10f * density), 0f)
        // Keep lines drawable; do not consume taps so photos/visits win via MapPriorityTapOverlay.
        line.setOnClickListener { _, _, _ -> false }
        mapView.overlays.add(line)
    }

    if (GeoLayer.TRIPS.key in layers) {
        data.trips.filter { !it.pending && it.kind == "trip" }.forEach { trip -> addPolyline(trip.path, tripColor, 5f) }
    }
    data.trips.filter { it.kind == "smooth" }.forEach { trip -> addPolyline(trip.path, AndroidColor.rgb(52, 211, 153), 4f) }
    data.trips.filter { it.kind == "matched" }.forEach { trip -> addPolyline(trip.path, AndroidColor.rgb(167, 139, 250), 4f, dashed = true) }
    data.trips.filter { it.pending }.forEach { trip -> addPolyline(trip.path, pendingPointColor, 5f) }
    if (speedOn) {
        data.speed.forEach { segment -> addPolyline(segment.path, SPEED_BUCKET_COLORS[segment.bucket.coerceIn(0, 5)], 3f) }
    }

    // Only repeatedly travelled streets are returned by the server, and the
    // overlay goes on top of the track so the hotspots stay readable.
    if (GeoLayer.HEATMAP.key in layers && data.heat.isNotEmpty()) {
        mapView.overlays.add(HeatmapOverlay(data.heat))
    }

    val hitPhotos = mutableListOf<Marker>()
    val hitVisits = mutableListOf<Marker>()
    val hitPoints = mutableListOf<Marker>()

    fun disableDefaultClick(marker: Marker) {
        marker.setOnMarkerClickListener { _, _ -> true }
    }

    fun addPoints(
        featureLayer: String,
        toggleKey: String,
        color: Int,
        border: Int,
        sizeDp: Int,
        kind: MapHitKind,
        title: (GeoPointFeature) -> String?,
    ) {
        if (toggleKey !in layers) return
        data.points.filter { it.layer == featureLayer }.forEach { feature ->
            val marker = Marker(mapView)
            marker.position = GeoPoint(feature.lat, feature.lon)
            marker.setAnchor(Marker.ANCHOR_CENTER, Marker.ANCHOR_CENTER)
            val photo = if (featureLayer == "visit" || featureLayer == "place") {
                when {
                    feature.osmType != null && feature.osmId != null ->
                        PlacePhotoCache.memoryOrDisk(mapView.context, PlacePhotoCache.key(feature.osmType, feature.osmId))
                    !feature.photoUrl.isNullOrBlank() ->
                        PlacePhotoCache.memoryOrDisk(mapView.context, "url:${feature.photoUrl.hashCode()}")
                    else -> null
                }
            } else null
            marker.icon = if (photo != null) {
                val size = (sizeDp * density * 2.2f).toInt().coerceIn(36, 72)
                android.graphics.drawable.BitmapDrawable(
                    resources,
                    PlacePhotoCache.circularIcon(photo, size, border, 2.5f * density),
                )
            } else {
                dot(color, border, (sizeDp * density).toInt())
            }
            title(feature)?.let { marker.title = it }
            styleMarker(marker, mapView, dark, coordsFallback = featureLayer == "stop")
            if (!feature.photoAttribution.isNullOrBlank()) {
                marker.snippet = listOfNotNull(marker.snippet?.takeIf { it.isNotBlank() }, feature.photoAttribution).joinToString("\n")
            }
            marker.relatedObject = MapHitPayload(kind, feature)
            disableDefaultClick(marker)
            mapView.overlays.add(marker)
            when (kind) {
                MapHitKind.VISIT -> hitVisits += marker
                MapHitKind.POINT -> hitPoints += marker
                MapHitKind.PHOTO -> hitPhotos += marker
            }
        }
    }

    // Draw low-priority first; PriorityTapOverlay decides the tap winner.
    if (GeoLayer.POINTS.key in layers) {
        data.points.filter { it.layer == "point_cluster" || it.layer == "point" || it.layer == "local_synced" }.forEach { feature ->
            val marker = Marker(mapView)
            marker.position = GeoPoint(feature.lat, feature.lon)
            marker.setAnchor(Marker.ANCHOR_CENTER, Marker.ANCHOR_CENTER)
            marker.icon = dot(pointColor, AndroidColor.TRANSPARENT, (6 + feature.count.coerceAtMost(6)) * density.toInt())
            styleMarker(marker, mapView, dark)
            marker.relatedObject = MapHitPayload(MapHitKind.POINT, feature)
            disableDefaultClick(marker)
            mapView.overlays.add(marker)
            hitPoints += marker
        }
        data.points.filter { it.pending }.forEach { feature ->
            val marker = Marker(mapView)
            marker.position = GeoPoint(feature.lat, feature.lon)
            marker.setAnchor(Marker.ANCHOR_CENTER, Marker.ANCHOR_CENTER)
            marker.icon = dot(pendingPointColor, AndroidColor.WHITE, (9 * density).toInt())
            styleMarker(marker, mapView, dark)
            marker.relatedObject = MapHitPayload(MapHitKind.POINT, feature)
            disableDefaultClick(marker)
            mapView.overlays.add(marker)
            hitPoints += marker
        }
    }
    addPoints("stop", GeoLayer.STOPS.key, stopColor, AndroidColor.WHITE, 14, MapHitKind.POINT) { feature ->
        if (feature.count > 1) feature.count.toString() else null
    }
    addPoints("place", GeoLayer.PLACES.key, placeColor, visitBorder, 16, MapHitKind.VISIT) { it.label }
    addPoints("visit", GeoLayer.PLACES.key, visitColor, visitBorder, 14, MapHitKind.VISIT) { feature ->
        feature.label ?: mapView.context.getString(R.string.visit_unidentified)
    }
    if (GeoLayer.PHOTOS.key in layers) {
        data.points.filter { it.layer == "photo_cluster" }.forEach { feature ->
            val marker = Marker(mapView)
            marker.position = GeoPoint(feature.lat, feature.lon)
            marker.setAnchor(Marker.ANCHOR_CENTER, Marker.ANCHOR_CENTER)
            val cached = feature.assetId?.let { PlacePhotoCache.memoryOrDisk(mapView.context, PlacePhotoCache.immichKey(it)) }
            val size = (36 * density).toInt().coerceIn(48, 80)
            marker.icon = if (cached != null) {
                android.graphics.drawable.BitmapDrawable(
                    resources,
                    PlacePhotoCache.circularIcon(cached, size, photoBorder, 2.5f * density),
                )
            } else {
                dot(photoBorder, AndroidColor.WHITE, (18 * density).toInt())
            }
            marker.title = feature.label ?: mapView.context.getString(R.string.map_layer_photos)
            marker.setInfoWindow(PhotoInfoWindow(mapView, dark, prefs, config))
            marker.relatedObject = MapHitPayload(MapHitKind.PHOTO, feature)
            disableDefaultClick(marker)
            mapView.overlays.add(marker)
            hitPhotos += marker
        }
    }
    val priority = mapView.overlays.filterIsInstance<MapPriorityTapOverlay>().firstOrNull()
        ?: MapPriorityTapOverlay().also { mapView.overlays.add(it) }
    priority.photos = hitPhotos
    priority.visits = hitVisits
    priority.points = hitPoints
    // Tap overlay must stay above markers/routes AND the live puck so the first
    // tap opens the photo without requiring a map pan first.
    mapView.overlays.remove(priority)
    mapView.overlays.add(priority)
    mapView.invalidate()
}

@Composable
private fun SpeedLegend(modifier: Modifier = Modifier) {
    Surface(
        shape = RoundedCornerShape(14.dp),
        color = MaterialTheme.colorScheme.surface.copy(alpha = 0.86f),
        shadowElevation = 4.dp,
        modifier = modifier,
    ) {
        Column(Modifier.padding(horizontal = 10.dp, vertical = Spacing.sm)) {
            Text(
                stringResource(R.string.map_speed_legend_title),
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.size(4.dp))
            SPEED_LEGEND_LABELS.forEachIndexed { index, label ->
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    Box(
                        Modifier
                            .size(10.dp)
                            .clip(RoundedCornerShape(3.dp))
                            .background(Color(SPEED_BUCKET_COLORS[index])),
                    )
                    Text(label, style = MaterialTheme.typography.labelSmall)
                }
            }
        }
    }
}

private val SPEED_LEGEND_LABELS = listOf("0–3", "3–6", "6–12", "12–25", "25–50", "50+")

/** Server refresh cadence for the open (today) map. */
private const val LIVE_MAP_REFRESH_MS = 10_000L

internal fun GeoMapData.withLocalEvents(events: List<StoredLocationEvent>): GeoMapData {
    data class LocalFix(val point: GeoPointFeature, val timestamp: java.time.Instant?, val speed: Double?)
    val local = events.filter { it.state != LocationEventStore.STATE_ACCEPTED && it.state != LocationEventStore.STATE_DUPLICATE }.sortedBy { it.deviceSequence }.mapNotNull { event ->
        val payload = runCatching { JSONObject(event.payload) }.getOrNull() ?: return@mapNotNull null
        val lat = payload.optDouble("latitude", Double.NaN)
        val lon = payload.optDouble("longitude", Double.NaN)
        if (!lat.isFinite() || !lon.isFinite() || lat !in -90.0..90.0 || lon !in -180.0..180.0) return@mapNotNull null
        val pending = event.state != LocationEventStore.STATE_ACCEPTED && event.state != LocationEventStore.STATE_DUPLICATE
        LocalFix(GeoPointFeature(if (pending) "local_point" else "local_synced", lat, lon, null, 1, 0.5f, pending),
            runCatching { java.time.Instant.parse(payload.optString("source_at")) }.getOrNull(),
            payload.optDouble("speed_mps", Double.NaN).takeIf { it.isFinite() && it >= 0 })
    }
    if (local.isEmpty()) return this
    val localTrips = mutableListOf<GeoTrip>()
    val localSpeed = mutableListOf<GeoSpeedSegment>()
    var runPending: Boolean? = null
    val runPath = mutableListOf<GeoLatLon>()
    fun flushRun() {
        if (runPath.size >= 2) localTrips += GeoTrip("local", "", runPath.toList(), 0.0, runPending == true)
        runPath.clear()
        runPending = null
    }
    val heatPaths = mutableListOf<Pair<Pair<Int, Int>, List<GeoLatLon>>>()
    local.zipWithNext().forEach { (previous, current) ->
        val a = GeoLatLon(previous.point.lat, previous.point.lon)
        val b = GeoLatLon(current.point.lat, current.point.lon)
        val seconds = if (previous.timestamp != null && current.timestamp != null) java.time.Duration.between(previous.timestamp, current.timestamp).seconds else 0L
        if (seconds < 0 || seconds > 300) { flushRun(); return@forEach }
        if (a == b) return@forEach
        val path = listOf(a, b)
        if (runPending != current.point.pending || (runPath.isNotEmpty() && runPath.last() != a)) {
            flushRun()
            runPending = current.point.pending
            runPath += a
        }
        runPath += b
        if (current.point.pending || previous.point.pending || speed.isEmpty() || heat.isEmpty()) {
            val distance = FloatArray(1)
            Location.distanceBetween(a.lat, a.lon, b.lat, b.lon, distance)
            val kmh = (current.speed ?: if (seconds > 0) distance[0].toDouble() / seconds else 0.0) * 3.6
            val bucket = when { kmh < 3 -> 0; kmh < 6 -> 1; kmh < 12 -> 2; kmh < 25 -> 3; kmh < 50 -> 4; else -> 5 }
            if (current.point.pending || previous.point.pending || speed.isEmpty()) localSpeed += GeoSpeedSegment(path, bucket, kmh)
            if (current.point.pending || previous.point.pending || heat.isEmpty()) {
                val cell = kotlin.math.floor((a.lat + b.lat) / 0.0004).toInt() to kotlin.math.floor((a.lon + b.lon) / 0.0004).toInt()
                heatPaths += cell to path
            }
        }
    }
    flushRun()
    val density = heatPaths.groupingBy { it.first }.eachCount()
    val maxDensity = density.values.maxOrNull()?.coerceAtLeast(1) ?: 1
    val localHeat = heatPaths.map { (cell, path) ->
        val weight = density[cell] ?: 1
        GeoHeatLine(path, weight, 0.35f + 0.65f * (weight - 1).toFloat() / (maxDensity - 1).coerceAtLeast(1))
    }
    val coordinates = points + local.map { it.point }
    val localBox = GeoBoundingBox(coordinates.maxOf { it.lat }, coordinates.minOf { it.lat }, coordinates.maxOf { it.lon }, coordinates.minOf { it.lon })
    val merged = bbox?.let { GeoBoundingBox(maxOf(it.north, localBox.north), minOf(it.south, localBox.south), maxOf(it.east, localBox.east), minOf(it.west, localBox.west)) } ?: localBox
    return copy(points = coordinates, trips = trips + localTrips, speed = speed + localSpeed, heat = heat + localHeat, bbox = merged)
}

private fun fitBoundingBox(mapView: MapView, bbox: GeoBoundingBox) {
    if (bbox.north == bbox.south && bbox.east == bbox.west) {
        mapView.controller.setZoom(17.0)
        mapView.controller.animateTo(GeoPoint(bbox.north, bbox.east))
        return
    }
    runCatching {
        val box = org.osmdroid.util.BoundingBox(bbox.north + 0.00001, bbox.east + 0.00001, bbox.south - 0.00001, bbox.west - 0.00001)
        mapView.zoomToBoundingBox(box, true, (48 * mapView.resources.displayMetrics.density).toInt())
    }
}
