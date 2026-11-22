package orbit.nex.steps

import orbit.nex.steps.ui.theme.Spacing
import android.graphics.Canvas
import android.graphics.Color as AndroidColor
import android.graphics.Paint
import android.graphics.drawable.GradientDrawable
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.AltRoute
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.SelectAll
import androidx.compose.material.icons.filled.VisibilityOff
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import orbit.nex.steps.components.AppTooltipIconButton
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import kotlinx.coroutines.Dispatchers
import org.osmdroid.tileprovider.tilesource.TileSourceFactory
import org.osmdroid.util.GeoPoint
import org.osmdroid.views.MapView
import org.osmdroid.views.overlay.Marker
import org.osmdroid.views.overlay.Overlay
import orbit.nex.steps.screens.KeepOverlay
import orbit.nex.steps.screens.MapStyle
import orbit.nex.steps.screens.StyledInfoWindow
import orbit.nex.steps.screens.MapRotationOverlay
import orbit.nex.steps.screens.withLocalEvents
import orbit.nex.steps.screens.applyBasemap
import orbit.nex.steps.screens.configureOsm
import orbit.nex.steps.screens.rebuildOverlays
import orbit.nex.steps.location.GeoMapData
import orbit.nex.steps.location.GeoMapRepository
import androidx.compose.ui.graphics.luminance
import androidx.compose.material3.ButtonDefaults
import androidx.compose.foundation.shape.RoundedCornerShape
import orbit.nex.steps.components.AppStatusPill
import orbit.nex.steps.location.GeoBoundingBox
import orbit.nex.steps.location.ManagedPoint
import orbit.nex.steps.location.PointManagementRepository
import orbit.nex.steps.ui.theme.HCWebhookTheme

class PointSelectionActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        val focusLat = intent.getDoubleExtra("focus_lat", Double.NaN)
        val focusLon = intent.getDoubleExtra("focus_lon", Double.NaN)
        setContent {
            HCWebhookTheme {
                PointSelectionScreen(
                    focus = if (focusLat.isNaN() || focusLon.isNaN()) null else GeoPoint(focusLat, focusLon),
                    onBack = { finish() },
                )
            }
        }
    }
}

private data class SelectionBox(
    val west: Double,
    val south: Double,
    val east: Double,
    val north: Double,
    val corners: List<GeoPoint> = emptyList(),
    val rotated: Boolean = false,
) {
    val query: String get() = "$west,$south,$east,$north"
    fun contains(lat: Double, lon: Double): Boolean {
        if (corners.size != 4) return lon in west..east && lat in south..north
        var inside = false
        var previous = corners.last()
        for (point in corners) {
            val ax = previous.longitude; val ay = previous.latitude
            val bx = point.longitude; val by = point.latitude
            val cross = (lon - ax) * (by - ay) - (lat - ay) * (bx - ax)
            if (kotlin.math.abs(cross) < 1e-12 && lon >= minOf(ax, bx) - 1e-10 && lon <= maxOf(ax, bx) + 1e-10 && lat >= minOf(ay, by) - 1e-10 && lat <= maxOf(ay, by) + 1e-10) return true
            if ((ay > lat) != (by > lat) && lon < (bx - ax) * (lat - ay) / (by - ay) + ax) inside = !inside
            previous = point
        }
        return inside
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PointSelectionScreen(focus: GeoPoint?, onBack: () -> Unit) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val repository = remember { PointManagementRepository(prefs, context) }
    val scope = rememberCoroutineScope()
    val config = remember { prefs.getWebhookConfigs().firstOrNull { it.isEnabled } }
    remember { configureOsm(context) }
    val darkTheme = MaterialTheme.colorScheme.background.luminance() < 0.5f
    val geoRepository = remember { GeoMapRepository(prefs, cacheDir = context.cacheDir) }
    val primary = MaterialTheme.colorScheme.primary
    val errorColor = MaterialTheme.colorScheme.error
    val drawMode = true
    var selection by remember { mutableStateOf<SelectionBox?>(null) }
    var count by remember { mutableStateOf(0) }
    var selectedIds by remember { mutableStateOf<Set<String>>(emptySet()) }
    var totalCount by remember { mutableStateOf(0) }
    var deletedCount by remember { mutableStateOf(0) }
    var loading by remember { mutableStateOf(false) }
    var selectionRevision by remember { mutableStateOf(0) }
    var notice by remember { mutableStateOf<String?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    LaunchedEffect(notice) { if (notice != null) { kotlinx.coroutines.delay(3500); notice = null } }
    var showDelete by remember { mutableStateOf(false) }
    var mapView by remember { mutableStateOf<MapView?>(null) }
    var loadedBox by remember { mutableStateOf<SelectionBox?>(null) }
    var day by remember { mutableStateOf<java.time.LocalDate?>(java.time.LocalDate.now()) }
    val zone = remember { java.time.ZoneId.systemDefault() }
    val dayFrom = day?.atStartOfDay(zone)?.toInstant()?.toString()
    val dayTo = day?.plusDays(1)?.atStartOfDay(zone)?.toInstant()?.minusMillis(1)?.toString()

    fun drawSelection(map: MapView) {
        val box = selection ?: return
        val polygon = org.osmdroid.views.overlay.Polygon(map).apply {
            points = box.corners.ifEmpty { listOf(GeoPoint(box.north, box.west), GeoPoint(box.north, box.east), GeoPoint(box.south, box.east), GeoPoint(box.south, box.west)) }
            outlinePaint.color = primary.toArgb()
            outlinePaint.strokeWidth = 5f
            fillPaint.color = primary.copy(alpha = 0.18f).toArgb()
        }
        map.overlays.add(polygon)
        map.invalidate()
    }

    fun refreshSelection(zoom: Boolean = false) {
        val map = mapView ?: return
        val box = selection
        if (box == null && day == null) {
            count = 0
            loadedBox = null
            rebuildOverlays(map, GeoMapData(), prefs.getMapLayers())
            return
        }
        loading = true
        error = null
        val revision = ++selectionRevision
        selectedIds = emptySet()
        count = 0
        val dayNow = day
        val dayFrom = dayNow?.atStartOfDay(zone)?.toInstant()?.toString()
        val dayTo = dayNow?.plusDays(1)?.atStartOfDay(zone)?.toInstant()?.minusMillis(1)?.toString()
        scope.launch {
            val active = config
            if (active != null) {
                val all = mutableListOf<orbit.nex.steps.location.ManagedPoint>()
                var pageIndex = 0
                var total = 0
                while (box != null || all.size < 1000) {
                    if (pageIndex > 0 && pageIndex % 100 == 0) kotlinx.coroutines.delay(60_000)
                    val page = repository.loadPage(active, pageIndex, dayFrom, dayTo, null, box?.query, pageSize = 200)
                    if (revision != selectionRevision) return@launch
                    total = page.total
                    all += page.points
                    if (page.error != null) { error = page.error; break }
                    pageIndex++
                    if (all.size >= page.total || page.points.isEmpty()) break
                }
                if (box == null) {
                    totalCount = total
                    selectedIds = emptySet()
                    count = 0
                } else {
                    val selected = if (error == null) all.filter { box.contains(it.lat, it.lon) } else emptyList()
                    selectedIds = selected.map { it.id }.toSet()
                    count = selectedIds.size
                    val totalPage = repository.loadPage(active, 0, dayFrom, dayTo, null, null)
                    if (revision != selectionRevision) return@launch
                    if (totalPage.error == null) totalCount = totalPage.total
                }
                val dayData = if (dayNow != null) geoRepository.loadDay(active, dayNow).withLocalEvents(withContext(Dispatchers.IO) { orbit.nex.steps.location.LocationEventStore(context).eventsForDay(dayNow) }) else GeoMapData()
                if (revision != selectionRevision) return@launch
                rebuildOverlays(map, dayData, prefs.getMapLayers(), darkTheme)
                if (box != null) drawSelection(map)
                loadedBox = if (all.isEmpty()) null else SelectionBox(all.minOf { it.lon } - 0.00001, all.minOf { it.lat } - 0.00001, all.maxOf { it.lon } + 0.00001, all.maxOf { it.lat } + 0.00001)
                map.overlays.add(SelectionPointsOverlay(all.map { GeoPoint(it.lat, it.lon) }, primary.toArgb()))
                if (zoom && (all.isNotEmpty() || dayData.bbox != null)) {
                    val bounds = dayData.bbox
                    val north = bounds?.north ?: all.maxOf { it.lat }; val south = bounds?.south ?: all.minOf { it.lat }
                    val east = bounds?.east ?: all.maxOf { it.lon }; val west = bounds?.west ?: all.minOf { it.lon }
                    if (north > south || east > west) {
                        runCatching { map.zoomToBoundingBox(org.osmdroid.util.BoundingBox(north + 0.00001, east + 0.00001, south - 0.00001, west - 0.00001), true, 96) }
                    } else {
                        map.controller.setZoom(17.0)
                        map.controller.animateTo(GeoPoint(north, east))
                    }
                }
                map.invalidate()
            }
            loading = false
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(stringResource(R.string.points_select_title)) },
                navigationIcon = {
                    AppTooltipIconButton(
                        onClick = onBack,
                        imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                        contentDescription = stringResource(R.string.settings_back_cd),
                    )
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.surfaceVariant,
                    scrolledContainerColor = MaterialTheme.colorScheme.surfaceVariant,
                ),
            )
        },
        containerColor = MaterialTheme.colorScheme.background,
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            Box(Modifier.weight(1f).fillMaxWidth()) {
                AndroidView(
                    factory = { ctx ->
                        MapView(ctx).apply {
                            setMultiTouchControls(true)
                            overlays.add(MapRotationOverlay(this))
                            if (!darkTheme) setTilesScaledToDpi(false)
                            applyBasemap(this, MapStyle.fromKey(prefs.getMapBasemap()), darkTheme)
                            zoomController.setVisibility(org.osmdroid.views.CustomZoomButtonsController.Visibility.NEVER)
                            controller.setZoom(if (focus != null) 16.0 else 5.0)
                            controller.setCenter(focus ?: GeoPoint(20.0, 0.0))
                            overlays.add(RectangleDrawOverlay(
                                paintColor = primary.toArgb(),
                                shouldDraw = { drawMode },
                                currentBox = { selection },
                                onBox = { box ->
                                    selection = box
                                    overlays.removeAll { it is org.osmdroid.views.overlay.Polygon }
                                    invalidate()
                                    drawSelection(this)
                                    refreshSelection()
                                },
                            ))
                            mapView = this
                            post { refreshSelection(focus == null) }
                        }
                    },
                    modifier = Modifier.fillMaxSize(),
                )
                Text(if (!darkTheme && MapStyle.fromKey(prefs.getMapBasemap()) == MapStyle.NORMAL) "© OpenStreetMap contributors" else "© Esri, HERE, Garmin, © OpenStreetMap contributors", modifier = Modifier.align(Alignment.BottomStart).padding(4.dp), style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurface)
                if (loading) CircularProgressIndicator(Modifier.align(Alignment.Center))
                notice?.let { orbit.nex.steps.components.SavedToast(it, Modifier.align(Alignment.BottomCenter).padding(bottom = 70.dp), onDismiss = { notice = null }) }
                Row(Modifier.align(Alignment.BottomCenter).padding(Spacing.md), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    orbit.nex.steps.screens.DayPill(
                        selectedDay = day ?: java.time.LocalDate.now(),
                        compact = false,
                        modifier = Modifier.width(220.dp),
                        onPrevious = { selection = null; day = (day ?: java.time.LocalDate.now()).minusDays(1); refreshSelection(true) },
                        onNext = { val d = day ?: java.time.LocalDate.now(); if (d.isBefore(java.time.LocalDate.now())) { selection = null; day = d.plusDays(1); refreshSelection(true) } },
                        onPick = { selection = null; day = it; refreshSelection(true) },
                    )
                }
                Surface(
                    Modifier.align(Alignment.TopCenter).padding(Spacing.md),
                    shape = MaterialTheme.shapes.medium,
                    color = MaterialTheme.colorScheme.surface.copy(alpha = 0.9f),
                ) {
                    Text(
                        if (drawMode) stringResource(R.string.points_draw_hint) else stringResource(R.string.points_draw_enable),
                        modifier = Modifier.padding(horizontal = Spacing.md, vertical = Spacing.sm),
                        style = MaterialTheme.typography.labelMedium,
                    )
                }
            }
            Surface(color = MaterialTheme.colorScheme.surface, modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.fillMaxWidth().navigationBarsPadding().padding(Spacing.md), verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(Spacing.xs), verticalAlignment = Alignment.CenterVertically) {
                        listOf(
                            stringResource(R.string.points_total_short, totalCount) to MaterialTheme.colorScheme.surfaceVariant,
                            stringResource(R.string.points_selected_short, count) to MaterialTheme.colorScheme.primaryContainer,
                            stringResource(R.string.points_removed_short, deletedCount) to MaterialTheme.colorScheme.tertiaryContainer,
                        ).forEach { (label, color) ->
                            Surface(modifier = Modifier.weight(1f), shape = MaterialTheme.shapes.small, color = color) {
                                Text(label, modifier = Modifier.padding(horizontal = 4.dp, vertical = 8.dp), textAlign = androidx.compose.ui.text.style.TextAlign.Center, style = MaterialTheme.typography.labelSmall, maxLines = 2)
                            }
                        }
                    }
                    Row(Modifier.fillMaxWidth().height(48.dp), horizontalArrangement = Arrangement.spacedBy(Spacing.sm), verticalAlignment = Alignment.CenterVertically) {
                        Button(
                            enabled = selection != null && count > 0 && !loading && config != null,
                            modifier = Modifier.weight(1f),
                            onClick = { showDelete = true },
                        ) {
                            Icon(Icons.Filled.Delete, contentDescription = null, modifier = Modifier.size(20.dp))
                            Text(stringResource(R.string.points_delete_forever), modifier = Modifier.padding(start = Spacing.sm), maxLines = 1)
                        }
                        AppTooltipIconButton(
                            enabled = selection != null && selection?.rotated == false && !loading && config != null,
                            onClick = {
                                val active = config ?: return@AppTooltipIconButton
                                val box = selection ?: return@AppTooltipIconButton
                                scope.launch {
                                    loading = true
                                    repository.smooth(active, box.query, day?.atStartOfDay(zone)?.toInstant()?.toString(), day?.plusDays(1)?.atStartOfDay(zone)?.toInstant()?.toString())
                                        .onSuccess { orbit.nex.steps.location.GeoMapChanges.invalidate(context); notice = context.getString(R.string.points_smooth_done); refreshSelection() }
                                        .onFailure { error = it.message }
                                    loading = false
                                }
                            },
                            imageVector = Icons.Filled.Edit,
                            contentDescription = stringResource(R.string.points_smooth),
                        )
                        AppTooltipIconButton(
                            enabled = !loading && config != null,
                            onClick = {
                                val active = config ?: return@AppTooltipIconButton
                                scope.launch {
                                    loading = true
                                    repository.runMatching(active).onSuccess { notice = context.getString(R.string.matching_queued) }.onFailure { error = it.message }
                                    loading = false
                                }
                            },
                            imageVector = Icons.Filled.AltRoute,
                            contentDescription = stringResource(R.string.points_match),
                        )
                        if (selection != null) {
                            AppTooltipIconButton(
                                onClick = { selection = null; count = 0; refreshSelection() },
                                imageVector = Icons.Filled.Close,
                                contentDescription = stringResource(R.string.action_close),
                            )
                        }
                    }
                    (error ?: if (config == null) stringResource(R.string.points_no_connection) else null)?.let {
                        Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall)
                    }
                }
            }
        }
    }

    if (showDelete) {
        AlertDialog(
            onDismissRequest = { showDelete = false },
            shape = RoundedCornerShape(24.dp),
            icon = { Icon(Icons.Filled.Delete, contentDescription = null, tint = MaterialTheme.colorScheme.error) },
            title = { Text(stringResource(R.string.points_delete_confirm_title)) },
            text = { Text(stringResource(R.string.points_delete_confirm_text, count)) },
            confirmButton = {
                Button(
                    onClick = {
                        showDelete = false
                        val active = config
                        val box = selection
                        val dayValue = day
                        // Without a drawn box the day is the selection; refusing to
                        // send anything made the confirmation a silent no-op.
                        if (active != null && (box != null || dayValue != null)) scope.launch {
                            loading = true
                            // The server deletes by bbox (or by day range) in one request;
                            // paging every id first used to take minutes and looked stuck.
                            var deleted = 0
                            var failure: Throwable? = null
                            for (batch in selectedIds.chunked(3000)) {
                                val result = repository.deleteIds(active, batch.toSet())
                                if (result.isFailure) { failure = result.exceptionOrNull(); break }
                                deleted += result.getOrDefault(0)
                            }
                            deletedCount += deleted
                            if (failure != null) error = failure.message ?: failure.javaClass.simpleName
                            else { notice = context.getString(R.string.points_deleted, deleted); selection = null; count = 0; selectedIds = emptySet() }
                            refreshSelection()
                            loading = false
                        }
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.error, contentColor = MaterialTheme.colorScheme.onError),
                    shape = RoundedCornerShape(16.dp),
                ) { Text(stringResource(R.string.points_delete_forever)) }
            },
            dismissButton = { TextButton(onClick = { showDelete = false }) { Text(stringResource(R.string.library_cancel)) } },
        )
    }

    DisposableEffect(Unit) {
        onDispose { mapView?.onDetach() }
    }
}

/** Drag-to-select rectangle; points are projected in screen space for a stable box. */
private class RectangleDrawOverlay(
    private val paintColor: Int,
    private val shouldDraw: () -> Boolean,
    private val currentBox: () -> SelectionBox?,
    private val onBox: (SelectionBox) -> Unit,
) : Overlay(), KeepOverlay {
    private val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        color = paintColor
        style = Paint.Style.FILL
        alpha = 56
    }
    private val stroke = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        color = paintColor
        style = Paint.Style.STROKE
        strokeWidth = 5f
    }
    private var startX = 0f
    private var startY = 0f
    private var endX = 0f
    private var endY = 0f
    private var drawing = false

    override fun draw(canvas: Canvas, mapView: MapView, shadow: Boolean) {
        if (shadow) return
        if (!drawing) {
            currentBox()?.let { box ->
                val px = android.graphics.Point()
                box.corners.ifEmpty { listOf(GeoPoint(box.north, box.west), GeoPoint(box.north, box.east), GeoPoint(box.south, box.east), GeoPoint(box.south, box.west)) }.forEach { point ->
                    mapView.projection.toPixels(point, px)
                    canvas.drawCircle(px.x.toFloat(), px.y.toFloat(), 7f * mapView.resources.displayMetrics.density, stroke)
                }
            }
            return
        }
        val left = minOf(startX, endX)
        val top = minOf(startY, endY)
        canvas.save()
        canvas.concat(mapView.projection.invertedScaleRotateCanvasMatrix)
        canvas.drawRect(left, top, maxOf(startX, endX), maxOf(startY, endY), paint)
        canvas.drawRect(left, top, maxOf(startX, endX), maxOf(startY, endY), stroke)
        canvas.restore()
    }

    private var downX = 0f
    private var downY = 0f
    private var pressing = false
    private var holdTask: Runnable? = null

    private fun cancelHold(mapView: MapView) {
        holdTask?.let { mapView.removeCallbacks(it) }
        holdTask = null
        pressing = false
    }

    override fun onTouchEvent(event: android.view.MotionEvent, mapView: MapView): Boolean {
        if (!shouldDraw()) return false
        val screen = floatArrayOf(event.x, event.y)
        mapView.projection.scaleRotateCanvasMatrix.mapPoints(screen)
        val x = screen[0]; val y = screen[1]
        when (event.actionMasked) {
            android.view.MotionEvent.ACTION_DOWN -> {
                cancelHold(mapView)
                val existing = currentBox()
                if (existing != null) {
                    val corners = existing.corners.ifEmpty { listOf(GeoPoint(existing.north, existing.west), GeoPoint(existing.north, existing.east), GeoPoint(existing.south, existing.east), GeoPoint(existing.south, existing.west)) }
                    val positions = corners.map { point -> android.graphics.Point().also { mapView.projection.toPixels(point, it); mapView.projection.rotateAndScalePoint(it.x, it.y, it) } }
                    val hit = positions.indexOfFirst { point -> kotlin.math.hypot(x - point.x, y - point.y) <= 28f * mapView.resources.displayMetrics.density }
                    if (hit >= 0) {
                        val opposite = positions[(hit + 2) % 4]
                        startX = opposite.x.toFloat(); startY = opposite.y.toFloat(); endX = x; endY = y
                        drawing = true
                        mapView.parent.requestDisallowInterceptTouchEvent(true)
                        return true
                    }
                }
                downX = x; downY = y; pressing = true
                val task = Runnable {
                    if (pressing) {
                        pressing = false
                        startX = downX; startY = downY; endX = downX; endY = downY; drawing = true
                        mapView.parent.requestDisallowInterceptTouchEvent(true)
                        mapView.performHapticFeedback(android.view.HapticFeedbackConstants.LONG_PRESS)
                        mapView.invalidate()
                    }
                }
                holdTask = task
                mapView.postDelayed(task, 350L)
                return false
            }
            android.view.MotionEvent.ACTION_POINTER_DOWN -> {
                cancelHold(mapView)
                if (drawing) {
                    drawing = false
                    mapView.parent.requestDisallowInterceptTouchEvent(false)
                    mapView.invalidate()
                }
                return false
            }
            android.view.MotionEvent.ACTION_MOVE -> {
                if (drawing) {
                    endX = x; endY = y
                    mapView.invalidate()
                    return true
                }
                if (pressing && (kotlin.math.abs(x - downX) > 16f || kotlin.math.abs(y - downY) > 16f)) cancelHold(mapView)
                return false
            }
            android.view.MotionEvent.ACTION_CANCEL -> {
                cancelHold(mapView)
                drawing = false
                mapView.parent.requestDisallowInterceptTouchEvent(false)
                mapView.invalidate()
                return false
            }
            android.view.MotionEvent.ACTION_UP -> {
                cancelHold(mapView)
                if (!drawing) return false
                endX = x; endY = y
                drawing = false
                mapView.parent.requestDisallowInterceptTouchEvent(false)
                if (kotlin.math.abs(endX - startX) > 24f && kotlin.math.abs(endY - startY) > 24f) {
                    val left = minOf(startX, endX); val right = maxOf(startX, endX)
                    val top = minOf(startY, endY); val bottom = maxOf(startY, endY)
                    val corners = listOf(left to top, right to top, right to bottom, left to bottom).map { (px, py) ->
                        val unrotated = mapView.projection.unrotateAndScalePoint(px.toInt(), py.toInt(), null)
                        val point = mapView.projection.fromPixels(unrotated.x, unrotated.y)
                        GeoPoint(point.latitude, point.longitude)
                    }
                    onBox(SelectionBox(
                        west = corners.minOf { it.longitude }, south = corners.minOf { it.latitude },
                        east = corners.maxOf { it.longitude }, north = corners.maxOf { it.latitude },
                        corners = corners, rotated = kotlin.math.abs(mapView.mapOrientation % 90f) > 1f && kotlin.math.abs(mapView.mapOrientation % 90f) < 89f,
                    ))
                }
                mapView.invalidate()
                return true
            }
        }
        return false
    }
}

private class SelectionPointsOverlay(private val points: List<GeoPoint>, color: Int) : Overlay() {
    private val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply { this.color = color; style = Paint.Style.FILL }
    override fun draw(canvas: Canvas, mapView: MapView, shadow: Boolean) {
        if (shadow) return
        val density = mapView.resources.displayMetrics.density
        val gridSize = (9f * density).coerceAtLeast(1f)
        val occupied = HashSet<Pair<Int, Int>>()
        val pixel = android.graphics.Point()
        points.forEach { point ->
            mapView.projection.toPixels(point, pixel)
            if (pixel.x < 0 || pixel.y < 0 || pixel.x > mapView.width || pixel.y > mapView.height) return@forEach
            val cell = (pixel.x / gridSize).toInt() to (pixel.y / gridSize).toInt()
            if (occupied.add(cell)) canvas.drawCircle(pixel.x.toFloat(), pixel.y.toFloat(), 2f * density, paint)
        }
    }
}
