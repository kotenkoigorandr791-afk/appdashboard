package orbit.nex.steps.screens

import orbit.nex.steps.ui.theme.Spacing
import orbit.nex.steps.ui.theme.semantic
import android.graphics.Canvas
import android.graphics.Color as AndroidColor
import android.location.LocationManager
import android.graphics.Paint
import android.graphics.Path
import android.graphics.Point
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
import androidx.compose.material.icons.filled.Layers
import androidx.compose.material.icons.filled.LocalFireDepartment
import androidx.compose.material.icons.filled.Map
import androidx.compose.material.icons.filled.MyLocation
import androidx.compose.material.icons.filled.Satellite
import androidx.compose.material.icons.filled.Speed
import androidx.compose.material.icons.filled.TravelExplore
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
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
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
import kotlinx.coroutines.delay
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
    private val data = mutableMapOf<String, GeoMapData>()

    fun get(configUrl: String?, day: LocalDate): GeoMapData? = data["${configUrl.orEmpty()}:${day}"]

    fun put(configUrl: String?, day: LocalDate, value: GeoMapData) {
        if (value.error == null) data["${configUrl.orEmpty()}:${day}"] = value
    }
}

/** GeoJSON layers the map can draw, matching the server's layer keys. */
private enum class GeoLayer(val key: String, val titleRes: Int) {
    TRIPS("trips", R.string.map_layer_trips),
    STOPS("stops", R.string.map_layer_stops),
    PLACES("places", R.string.map_layer_places),
    VISITS("visits", R.string.map_layer_visits),
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
fun TrackingMapScreen(modifier: Modifier = Modifier) {
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
            expirationOverrideDuration = 90L * 24 * 3600 * 1000
            expirationExtendedDuration = 90L * 24 * 3600 * 1000
            tileDownloadThreads = 4
        }
    }

    var trackingState by remember { mutableStateOf(manager.state()) }
    var permissionDenied by remember { mutableStateOf(LocationPermissions.state(context) == LocationPermissionState.DENIED) }
    var pendingUploads by remember { mutableStateOf(LocationTrackingManager.pendingCount(context)) }
    var lastUploadAt by remember { mutableStateOf(prefs.getLocationUploadOkAt()) }
    var lastError by remember { mutableStateOf(prefs.getLocationUploadError()) }

    // A stored day from the future has nothing to show, so it opens on today.
    var selectedDay by remember {
        mutableStateOf(
            (prefs.getMapDay()?.let { runCatching { LocalDate.parse(it) }.getOrNull() } ?: LocalDate.now())
                .coerceAtMost(LocalDate.now()),
        )
    }
    // Server data and the local outbox stay separate so that a sync landing in
    // the background never duplicates the measurements still waiting for it.
    var remoteData by remember { mutableStateOf(GeoMapData()) }
    var localEvents by remember { mutableStateOf<List<StoredLocationEvent>>(emptyList()) }
    val data = remember(remoteData, localEvents) { remoteData.withLocalEvents(localEvents) }
    var loading by remember { mutableStateOf(false) }
    var mapStyle by remember { mutableStateOf(MapStyle.fromKey(prefs.getMapBasemap())) }
    var layers by remember { mutableStateOf(prefs.getMapLayers()) }
    var showLayers by remember { mutableStateOf(false) }
    var myLocation by remember { mutableStateOf<GeoPoint?>(null) }
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
    LaunchedEffect(Unit) {
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

    LaunchedEffect(selectedDay, config) {
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

    val permissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) {
        if (LocationPermissions.hasForeground(context)) manager.start()
        trackingState = manager.state()
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
    val roadsOverlay = remember {
        TilesOverlay(MapTileProviderBasic(context).apply { setTileSource(EsriTransportation) }, context)
    }
    DisposableEffect(Unit) {
        mapView.onResume()
        onDispose {
            val center = mapView.mapCenter
            prefs.setMapCamera(center.latitude, center.longitude, mapView.zoomLevelDouble)
            prefs.setMapDay(selectedDay.toString())
            mapView.onPause()
            mapView.onDetach()
        }
    }

    LaunchedEffect(resolvedStyle) {
        mapView.setTileSource(
            when (resolvedStyle) {
                MapStyle.SATELLITE -> EsriSatellite
                MapStyle.NORMAL -> EsriStreet
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

    // Overlays are rebuilt only when the day data or the enabled layers change.
    LaunchedEffect(data, layers, resolvedStyle, myLocation) {
        rebuildOverlays(mapView, data, layers, myLocation)
        mapView.overlays.remove(roadsOverlay)
        if (resolvedStyle == MapStyle.SATELLITE) mapView.overlays.add(0, roadsOverlay)
        val box = data.bbox
        if (!restoringCamera && !data.isEmpty && box != null && fittedDay != selectedDay) {
            fittedDay = selectedDay
            fitBoundingBox(mapView, box)
        }
    }

    @android.annotation.SuppressLint("MissingPermission")
    fun locate() {
        val client = com.google.android.gms.location.LocationServices.getFusedLocationProviderClient(context)
        fun show(location: android.location.Location?) {
            if (location == null) return
            val point = GeoPoint(location.latitude, location.longitude)
            myLocation = point
            mapView.controller.setZoom(16.0)
            mapView.controller.animateTo(point)
            restoringCamera = false
        }
        client.getCurrentLocation(com.google.android.gms.location.Priority.PRIORITY_HIGH_ACCURACY, null)
            .addOnSuccessListener { location ->
                if (location != null) show(location) else client.lastLocation.addOnSuccessListener { show(it) }
            }
            .addOnFailureListener { client.lastLocation.addOnSuccessListener { show(it) } }
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
                        trackingState = manager.state()
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
        // half of a small map.
        Column(
            Modifier.align(Alignment.CenterEnd).padding(end = edge),
            verticalArrangement = Arrangement.spacedBy((10.dp * uiScale).coerceAtLeast(6.dp)),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            MapControlButton(
                icon = Icons.Filled.TravelExplore,
                contentDescription = stringResource(R.string.map_panels_open),
                size = controlSize,
                iconSize = controlIconSize,
                onClick = { showPanels = true },
            )
            MapControlButton(
                icon = Icons.Filled.Layers,
                contentDescription = stringResource(R.string.map_layers),
                size = controlSize,
                iconSize = controlIconSize,
                onClick = { showLayers = true },
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

        // The legend and the sync state stack together along the bottom edge.
        // The status box spans the free width so it does not leave a large empty
        // strip on the right side of the map.
        Column(
            Modifier
                .align(Alignment.BottomStart)
                .fillMaxWidth()
                .padding(start = edge, end = edge, bottom = edge),
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
        LocationPanelsSheet(onDismiss = { showPanels = false })
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
        color = MaterialTheme.colorScheme.surface.copy(alpha = 0.72f),
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

internal class StyledInfoWindow(mapView: MapView) : org.osmdroid.views.overlay.infowindow.InfoWindow(R.layout.map_info_window, mapView) {
    override fun onOpen(item: Any?) {
        val marker = item as? Marker
        mView.findViewById<android.widget.TextView>(R.id.info_title).text = marker?.title.orEmpty()
        mView.findViewById<android.widget.TextView>(R.id.info_snippet).apply {
            text = marker?.snippet.orEmpty()
            visibility = if (marker?.snippet.isNullOrBlank()) android.view.View.GONE else android.view.View.VISIBLE
        }
        mView.setOnClickListener { close() }
    }

    override fun onClose() {}
}

internal fun styleMarker(marker: Marker, mapView: MapView) {
    if (marker.title.isNullOrBlank()) marker.setInfoWindow(null) else marker.setInfoWindow(StyledInfoWindow(mapView))
}

internal fun configureOsm(context: android.content.Context) {
    Configuration.getInstance().apply {
        userAgentValue = "${context.packageName}/${BuildConfig.VERSION_NAME} (NexSteps)"
        osmdroidBasePath = java.io.File(context.filesDir, "osmdroid")
        osmdroidTileCache = java.io.File(context.filesDir, "osmdroid/tiles")
        tileFileSystemCacheMaxBytes = 1024L * 1024 * 1024
        tileFileSystemCacheTrimBytes = 800L * 1024 * 1024
        expirationOverrideDuration = 90L * 24 * 3600 * 1000
        expirationExtendedDuration = 90L * 24 * 3600 * 1000
        tileDownloadThreads = 4
    }
}

internal fun applyBasemap(mapView: MapView, style: MapStyle, darkTheme: Boolean) {
    mapView.setTileSource(if (style == MapStyle.SATELLITE) EsriSatellite else EsriStreet)
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

internal fun rebuildOverlays(mapView: MapView, data: GeoMapData, layers: Set<String>, myLocation: GeoPoint? = null) {
    mapView.overlays.removeAll { it !is KeepOverlay }
    val resources = mapView.resources
    val density = resources.displayMetrics.density
    val tripColor = AndroidColor.rgb(46, 125, 255)
    val stopColor = AndroidColor.rgb(240, 150, 40)
    val placeColor = AndroidColor.rgb(120, 80, 220)
    val visitColor = AndroidColor.rgb(40, 170, 110)
    val pointColor = AndroidColor.argb(150, 90, 90, 100)
    val pendingPointColor = AndroidColor.argb(220, 232, 132, 20)
    val speedOn = GeoLayer.SPEED.key in layers && data.speed.isNotEmpty()

    fun addPolyline(path: List<GeoLatLon>, color: Int, widthDp: Float, dashed: Boolean = false) {
        val line = Polyline(mapView)
        path.forEach { line.addPoint(GeoPoint(it.lat, it.lon)) }
        line.outlinePaint.color = color
        line.outlinePaint.strokeWidth = widthDp * density
        line.outlinePaint.strokeCap = android.graphics.Paint.Cap.ROUND
        line.outlinePaint.strokeJoin = android.graphics.Paint.Join.ROUND
        if (dashed) line.outlinePaint.pathEffect = android.graphics.DashPathEffect(floatArrayOf(14f * density, 10f * density), 0f)
        mapView.overlays.add(line)
    }

    // A speed-coloured track replaces the plain trip line: drawing both would
    // simply hide the colour scale under the solid stroke.
    if (speedOn) {
        if (GeoLayer.TRIPS.key in layers) data.trips.filter { !it.pending && it.kind == "trip" }.forEach { trip -> addPolyline(trip.path, AndroidColor.argb(170, 148, 163, 184), 3.5f) }
        data.speed.forEach { segment ->
            val casing = AndroidColor.argb(90, 0, 0, 0)
            addPolyline(segment.path, casing, 7.5f)
            addPolyline(segment.path, SPEED_BUCKET_COLORS[segment.bucket.coerceIn(0, 5)], 5.5f)
        }
    } else if (GeoLayer.TRIPS.key in layers) {
        data.trips.filter { !it.pending && it.kind == "trip" }.forEach { trip -> addPolyline(trip.path, tripColor, 5f) }
    }
    data.trips.filter { it.kind == "smooth" }.forEach { trip -> addPolyline(trip.path, AndroidColor.rgb(52, 211, 153), 4f) }
    data.trips.filter { it.kind == "matched" }.forEach { trip -> addPolyline(trip.path, AndroidColor.rgb(167, 139, 250), 4f, dashed = true) }
    data.trips.filter { it.pending }.forEach { trip -> addPolyline(trip.path, pendingPointColor, 4f, dashed = true) }

    // Only repeatedly travelled streets are returned by the server, and the
    // overlay goes on top of the track so the hotspots stay readable.
    if (GeoLayer.HEATMAP.key in layers && data.heat.isNotEmpty()) {
        mapView.overlays.add(HeatmapOverlay(data.heat))
    }

    fun addPoints(layerKey: String, color: Int, border: Int, sizeDp: Int, title: (GeoPointFeature) -> String?) {
        if (layerKey !in layers) return
        data.points.filter { it.layer == layerKey }.forEach { feature ->
            val marker = Marker(mapView)
            marker.position = GeoPoint(feature.lat, feature.lon)
            marker.setAnchor(Marker.ANCHOR_CENTER, Marker.ANCHOR_CENTER)
            marker.icon = dot(color, border, (sizeDp * density).toInt())
            title(feature)?.let { marker.title = it }
            styleMarker(marker, mapView)
            mapView.overlays.add(marker)
        }
    }

    addPoints("stop", stopColor, AndroidColor.WHITE, 14) { feature ->
        if (feature.count > 1) feature.count.toString() else null
    }
    addPoints("place", placeColor, AndroidColor.WHITE, 16) { it.label }
    addPoints("visit", visitColor, AndroidColor.WHITE, 12) { it.label }
    // Raw points and aggregated clusters are decoration: dense days would be
    // unreadable as markers, so only clustered positions are drawn.
    if (GeoLayer.POINTS.key in layers) {
        data.points.filter { it.layer == "point_cluster" || it.layer == "point" }.forEach { feature ->
            val marker = Marker(mapView)
            marker.position = GeoPoint(feature.lat, feature.lon)
            marker.setAnchor(Marker.ANCHOR_CENTER, Marker.ANCHOR_CENTER)
            marker.icon = dot(pointColor, AndroidColor.TRANSPARENT, (6 + feature.count.coerceAtMost(6)) * density.toInt())
            styleMarker(marker, mapView)
            mapView.overlays.add(marker)
        }
    }
    // Measurements that have not reached the server yet are always drawn, even
    // with the points layer off, so nothing recorded on the device is invisible.
    data.points.filter { it.pending }.forEach { feature ->
        val marker = Marker(mapView)
        marker.position = GeoPoint(feature.lat, feature.lon)
        marker.setAnchor(Marker.ANCHOR_CENTER, Marker.ANCHOR_CENTER)
        marker.icon = dot(pendingPointColor, AndroidColor.WHITE, (9 * density).toInt())
        marker.setInfoWindow(null)
        marker.setOnMarkerClickListener { _, _ -> true }
        mapView.overlays.add(marker)
    }
    myLocation?.let { point ->
        val marker = Marker(mapView)
        marker.position = point
        marker.setAnchor(Marker.ANCHOR_CENTER, Marker.ANCHOR_CENTER)
        marker.icon = dot(AndroidColor.rgb(33, 150, 243), AndroidColor.WHITE, (16 * density).toInt())
        marker.setInfoWindow(null)
        marker.setInfoWindow(null)
        marker.setOnMarkerClickListener { _, _ -> true }
        mapView.overlays.add(marker)
    }
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

private fun GeoMapData.withLocalEvents(events: List<StoredLocationEvent>): GeoMapData {
    // Only measurements the server has not confirmed yet: accepted rows stay in
    // the outbox for a few days and would otherwise duplicate server points.
    val local = events.filter { it.isPending }.sortedBy { it.deviceSequence }.mapNotNull { event ->
        runCatching { JSONObject(event.payload) }.getOrNull()?.let { payload ->
            val lat = payload.optDouble("latitude", Double.NaN)
            val lon = payload.optDouble("longitude", Double.NaN)
            if (lat.isNaN() || lon.isNaN() || lat !in -90.0..90.0 || lon !in -180.0..180.0) null else GeoPointFeature("local_point", lat, lon, null, 1, 0.5f, pending = true)
        }
    }
    if (local.isEmpty()) return this
    val localPath = local.map { GeoLatLon(it.lat, it.lon) }
    val anchor = (trips.filter { !it.pending }.lastOrNull()?.path?.lastOrNull() ?: speed.lastOrNull()?.path?.lastOrNull())
    val connector = GeoTrip("local", "", listOfNotNull(anchor) + localPath, 0.0, pending = true)
    val coordinates = points + local
    val localBox = GeoBoundingBox(coordinates.maxOf { it.lat }, coordinates.minOf { it.lat }, coordinates.maxOf { it.lon }, coordinates.minOf { it.lon })
    val merged = bbox?.let { GeoBoundingBox(maxOf(it.north, localBox.north), minOf(it.south, localBox.south), maxOf(it.east, localBox.east), minOf(it.west, localBox.west)) } ?: localBox
    return copy(points = coordinates, trips = if (connector.path.size >= 2) trips + connector else trips, bbox = merged)
}

private fun fitBoundingBox(mapView: MapView, bbox: GeoBoundingBox) {
    if (bbox.north <= bbox.south || bbox.east <= bbox.west) return
    runCatching {
        val box = org.osmdroid.util.BoundingBox(bbox.north, bbox.east, bbox.south, bbox.west)
        mapView.zoomToBoundingBox(box, true, (48 * mapView.resources.displayMetrics.density).toInt())
    }
}
