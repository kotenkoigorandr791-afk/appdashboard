package orbit.nex.steps.screens

import android.graphics.Canvas
import android.graphics.Color as AndroidColor
import android.location.LocationManager
import android.graphics.Paint
import android.graphics.Point
import android.graphics.RadialGradient
import android.graphics.Shader
import android.graphics.drawable.GradientDrawable
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
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
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.luminance
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import kotlinx.coroutines.delay
import org.osmdroid.tileprovider.tilesource.OnlineTileSourceBase
import org.osmdroid.tileprovider.tilesource.TileSourceFactory
import org.osmdroid.tileprovider.tilesource.XYTileSource
import org.osmdroid.tileprovider.MapTileProviderBasic
import org.osmdroid.util.GeoPoint
import org.osmdroid.util.MapTileIndex
import org.osmdroid.views.MapView
import org.osmdroid.views.overlay.Marker
import org.osmdroid.views.overlay.Overlay
import org.osmdroid.views.overlay.Polyline
import org.osmdroid.views.overlay.TilesOverlay
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.R
import orbit.nex.steps.location.GeoBoundingBox
import orbit.nex.steps.location.GeoLatLon
import orbit.nex.steps.location.GeoMapData
import orbit.nex.steps.location.GeoMapRepository
import orbit.nex.steps.location.GeoPointFeature
import orbit.nex.steps.location.LocationPermissionState
import orbit.nex.steps.location.LocationPermissions
import orbit.nex.steps.location.LocationTrackingManager
import orbit.nex.steps.location.TrackingState
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.util.Locale

private enum class MapStyle(val key: String, val titleRes: Int) {
    NORMAL("normal", R.string.map_style_standard),
    SATELLITE("satellite", R.string.map_style_satellite),
    ;

    companion object {
        fun fromKey(key: String): MapStyle = if (key == SATELLITE.key) SATELLITE else NORMAL
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
}

private object CartoLight : XYTileSource(
    "CartoLight", 0, 20, 256, ".png",
    arrayOf(
        "https://a.basemaps.cartocdn.com/light_all/",
        "https://b.basemaps.cartocdn.com/light_all/",
        "https://c.basemaps.cartocdn.com/light_all/",
    ),
    "© OpenStreetMap contributors © CARTO",
)

private object CartoDark : XYTileSource(
    "CartoDark", 0, 20, 256, ".png",
    arrayOf(
        "https://a.basemaps.cartocdn.com/dark_all/",
        "https://b.basemaps.cartocdn.com/dark_all/",
        "https://c.basemaps.cartocdn.com/dark_all/",
    ),
    "© OpenStreetMap contributors © CARTO",
)

/** Esri World Imagery needs z/y/x order, so it cannot use [XYTileSource]. */
private object EsriSatellite : OnlineTileSourceBase(
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

/** Translucent density overlay; heat cells come from the server aggregation. */
private class HeatmapOverlay(
    private val cells: List<GeoPointFeature>,
    private val color: Int,
) : Overlay() {

    private val paint = Paint(Paint.ANTI_ALIAS_FLAG)
    private val screenPoint = Point()

    override fun draw(canvas: Canvas, mapView: MapView, shadow: Boolean) {
        if (shadow || cells.isEmpty()) return
        val projection = mapView.projection
        cells.forEach { cell ->
            projection.toPixels(GeoPoint(cell.lat, cell.lon), screenPoint)
            val radius = 18f + 42f * cell.intensity.coerceIn(0f, 1f)
            paint.shader = RadialGradient(
                screenPoint.x.toFloat(),
                screenPoint.y.toFloat(),
                radius,
                intArrayOf(color, AndroidColor.TRANSPARENT),
                floatArrayOf(0f, 1f),
                Shader.TileMode.CLAMP,
            )
            canvas.drawCircle(screenPoint.x.toFloat(), screenPoint.y.toFloat(), radius, paint)
        }
        paint.shader = null
    }
}

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
    val repository = remember { GeoMapRepository(prefs) }

    var trackingState by remember { mutableStateOf(manager.state()) }
    var permissionDenied by remember { mutableStateOf(LocationPermissions.state(context) == LocationPermissionState.DENIED) }
    var pendingUploads by remember { mutableStateOf(LocationTrackingManager.pendingCount(context)) }
    var lastUploadAt by remember { mutableStateOf(prefs.getLocationUploadOkAt()) }
    var lastError by remember { mutableStateOf(prefs.getLocationUploadError()) }

    var selectedDay by remember { mutableStateOf(LocalDate.now()) }
    var data by remember { mutableStateOf(GeoMapData()) }
    var loading by remember { mutableStateOf(false) }
    var mapStyle by remember { mutableStateOf(MapStyle.fromKey(prefs.getMapBasemap())) }
    var layers by remember { mutableStateOf(prefs.getMapLayers()) }
    var showLayers by remember { mutableStateOf(false) }
    var fittedDay by remember { mutableStateOf<LocalDate?>(null) }

    val darkTheme = MaterialTheme.colorScheme.background.luminance() < 0.5f
    val resolvedStyle = mapStyle
    val mapBackground = when (resolvedStyle) {
        MapStyle.SATELLITE -> Color(0xFF083744)
        MapStyle.NORMAL -> if (darkTheme) Color(0xFF5C2F1E) else Color(0xFFAED2E6)
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
            delay(2_000)
        }
    }

    val config = prefs.getWebhookConfigs().firstOrNull { it.isEnabled }

    LaunchedEffect(selectedDay, config) {
        val activeConfig = config ?: return@LaunchedEffect
        loading = true
        data = repository.loadDay(activeConfig, selectedDay)
        loading = false
    }

    val permissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) {
        if (LocationPermissions.hasForeground(context)) manager.start()
        trackingState = manager.state()
    }

    val mapView = remember {
        MapView(context).apply {
            setMultiTouchControls(true)
            setHorizontalMapRepetitionEnabled(false)
            setVerticalMapRepetitionEnabled(false)
            controller.setZoom(2.0)
            controller.setCenter(GeoPoint(20.0, 0.0))
        }
    }
    val roadsOverlay = remember {
        TilesOverlay(MapTileProviderBasic(context).apply { setTileSource(EsriTransportation) }, context)
    }
    DisposableEffect(Unit) {
        mapView.onResume()
        onDispose {
            mapView.onPause()
            mapView.onDetach()
        }
    }

    LaunchedEffect(resolvedStyle) {
        mapView.setTileSource(
            when (resolvedStyle) {
                MapStyle.SATELLITE -> EsriSatellite
                MapStyle.NORMAL -> TileSourceFactory.MAPNIK
            }
        )
        mapView.overlayManager.tilesOverlay.setColorFilter(
            if (resolvedStyle == MapStyle.NORMAL && darkTheme) TilesOverlay.INVERT_COLORS else null
        )
        mapView.setBackgroundColor(
            when (resolvedStyle) {
                MapStyle.SATELLITE -> AndroidColor.rgb(8, 55, 68)
                MapStyle.NORMAL -> if (darkTheme) AndroidColor.rgb(92, 47, 30) else AndroidColor.rgb(174, 210, 230)
            }
        )
        mapView.invalidate()
    }

    // Overlays are rebuilt only when the day data or the enabled layers change.
    LaunchedEffect(data, layers, resolvedStyle) {
        rebuildOverlays(mapView, data, layers)
        mapView.overlays.remove(roadsOverlay)
        if (resolvedStyle == MapStyle.SATELLITE) mapView.overlays.add(0, roadsOverlay)
        val box = data.bbox
        if (!data.isEmpty && box != null && fittedDay != selectedDay) {
            fittedDay = selectedDay
            fitBoundingBox(mapView, box)
        }
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

    Box(modifier.fillMaxSize().background(mapBackground)) {
        AndroidView(factory = { mapView }, modifier = Modifier.fillMaxSize())

        // Floating header: what is recording and which day is shown.
        Row(
            Modifier.align(Alignment.TopCenter).padding(top = 10.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            TrackingSwitchPill(
                tracking = trackingState == TrackingState.RUNNING || trackingState == TrackingState.PAUSED,
                paused = trackingState == TrackingState.PAUSED,
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
            DayPill(
                selectedDay = selectedDay,
                onPrevious = {
                    selectedDay = selectedDay.minusDays(1)
                    fittedDay = null
                },
                onNext = {
                    if (selectedDay.isBefore(LocalDate.now())) {
                        selectedDay = selectedDay.plusDays(1)
                        fittedDay = null
                    }
                },
            )
        }

        // Map controls.
        Column(
            Modifier.align(Alignment.CenterEnd).padding(end = 12.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            MapControlButton(
                icon = Icons.Filled.Layers,
                contentDescription = stringResource(R.string.map_layers),
                onClick = { showLayers = true },
            )
            MapControlButton(
                icon = Icons.Filled.LocalFireDepartment,
                contentDescription = stringResource(R.string.map_layer_heatmap),
                active = GeoLayer.HEATMAP.key in layers,
                onClick = {
                    layers = if (GeoLayer.HEATMAP.key in layers) layers - GeoLayer.HEATMAP.key else layers + GeoLayer.HEATMAP.key
                    prefs.setMapLayers(layers)
                },
            )
            MapControlButton(
                icon = Icons.Filled.MyLocation,
                contentDescription = stringResource(R.string.map_locate),
                onClick = { fitToData() },
            )
        }

        if (data.isEmpty && !loading) {
            Surface(
                shape = RoundedCornerShape(16.dp),
                color = MaterialTheme.colorScheme.surface.copy(alpha = 0.82f),
                modifier = Modifier.align(Alignment.Center),
            ) {
                Text(
                    stringResource(R.string.map_no_data),
                    style = MaterialTheme.typography.bodyMedium,
                    modifier = Modifier.padding(horizontal = 18.dp, vertical = 12.dp),
                )
            }
        }

        SyncStatusBox(
            loading = loading,
            error = data.error ?: lastError,
            lastUploadAt = lastUploadAt,
            pending = pendingUploads,
            modifier = Modifier.align(Alignment.BottomCenter).padding(bottom = 14.dp),
        )
    }

    if (showLayers) {
        ModalBottomSheet(onDismissRequest = { showLayers = false }) {
            Column(Modifier.padding(horizontal = 18.dp).padding(bottom = 24.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
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
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
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
                        Checkbox(
                            checked = layer.key in layers,
                            onCheckedChange = { checked ->
                                layers = if (checked) layers + layer.key else layers - layer.key
                                prefs.setMapLayers(layers)
                            },
                        )
                        Text(stringResource(layer.titleRes))
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
    onCheckedChange: (Boolean) -> Unit,
) {
    Surface(
        shape = RoundedCornerShape(percent = 50),
        color = MaterialTheme.colorScheme.surface.copy(alpha = 0.82f),
        shadowElevation = 6.dp,
    ) {
        Row(
            Modifier.padding(start = 14.dp, end = 6.dp, top = 2.dp, bottom = 2.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            Box(
                Modifier
                    .size(9.dp)
                    .clip(CircleShape)
                    .background(
                        when {
                            tracking && !paused -> Color(0xFF4C8DFF)
                            tracking -> Color(0xFFE0A100)
                            else -> MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.5f)
                        }
                    )
            )
            Text(
                stringResource(R.string.map_tracking),
                style = MaterialTheme.typography.labelLarge,
                fontWeight = FontWeight.Medium,
            )
            Switch(checked = tracking, onCheckedChange = onCheckedChange)
        }
    }
}

@Composable
private fun DayPill(
    selectedDay: LocalDate,
    onPrevious: () -> Unit,
    onNext: () -> Unit,
) {
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
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            IconButton(onClick = onPrevious) {
                Icon(
                    Icons.AutoMirrored.Filled.KeyboardArrowLeft,
                    contentDescription = stringResource(R.string.map_previous_day),
                )
            }
            Text(
                label,
                style = MaterialTheme.typography.labelLarge,
                modifier = Modifier.width(112.dp),
                textAlign = androidx.compose.ui.text.style.TextAlign.Center,
            )
            IconButton(onClick = onNext, enabled = selectedDay.isBefore(today)) {
                Icon(
                    Icons.AutoMirrored.Filled.KeyboardArrowRight,
                    contentDescription = stringResource(R.string.map_next_day),
                )
            }
        }
    }
}

@Composable
private fun MapControlButton(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    contentDescription: String,
    active: Boolean = false,
    onClick: () -> Unit,
) {
    Surface(
        shape = RoundedCornerShape(14.dp),
        color = if (active) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surface.copy(alpha = 0.82f),
        shadowElevation = 4.dp,
    ) {
        IconButton(onClick = onClick) {
            Icon(
                icon,
                contentDescription = contentDescription,
                tint = if (active) MaterialTheme.colorScheme.onPrimaryContainer else MaterialTheme.colorScheme.onSurface,
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
            Modifier.padding(horizontal = 14.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp),
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
private fun rebuildOverlays(mapView: MapView, data: GeoMapData, layers: Set<String>) {
    mapView.overlays.clear()
    val resources = mapView.resources
    val density = resources.displayMetrics.density
    val primary = AndroidColor.rgb(38, 108, 255)
    val tripColor = AndroidColor.rgb(46, 125, 255)
    val stopColor = AndroidColor.rgb(240, 150, 40)
    val placeColor = AndroidColor.rgb(120, 80, 220)
    val visitColor = AndroidColor.rgb(40, 170, 110)
    val pointColor = AndroidColor.argb(150, 90, 90, 100)

    if (GeoLayer.TRIPS.key in layers) {
        data.trips.forEach { trip ->
            val line = Polyline(mapView)
            trip.path.forEach { line.addPoint(GeoPoint(it.lat, it.lon)) }
            line.outlinePaint.color = tripColor
            line.outlinePaint.strokeWidth = 5f * density
            line.outlinePaint.strokeCap = android.graphics.Paint.Cap.ROUND
            mapView.overlays.add(line)
        }
    }

    fun addPoints(layerKey: String, color: Int, border: Int, sizeDp: Int, title: (GeoPointFeature) -> String?) {
        if (layerKey !in layers) return
        data.points.filter { it.layer == layerKey }.forEach { feature ->
            val marker = Marker(mapView)
            marker.position = GeoPoint(feature.lat, feature.lon)
            marker.setAnchor(Marker.ANCHOR_CENTER, Marker.ANCHOR_CENTER)
            marker.icon = dot(color, border, (sizeDp * density).toInt())
            title(feature)?.let { marker.title = it }
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
            mapView.overlays.add(marker)
        }
    }
    if (GeoLayer.HEATMAP.key in layers) {
        val cells = data.points.filter { it.layer == "heatmap" }
        if (cells.isNotEmpty()) {
            mapView.overlays.add(HeatmapOverlay(cells, AndroidColor.argb(130, 255, 82, 45)))
        }
    }
    mapView.invalidate()
}

private fun fitBoundingBox(mapView: MapView, bbox: GeoBoundingBox) {
    if (bbox.north <= bbox.south || bbox.east <= bbox.west) return
    runCatching {
        val box = org.osmdroid.util.BoundingBox(bbox.north, bbox.east, bbox.south, bbox.west)
        mapView.zoomToBoundingBox(box, true, (48 * mapView.resources.displayMetrics.density).toInt())
    }
}
