package orbit.nex.steps

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
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Delete
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
import androidx.compose.runtime.Composable
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
import org.osmdroid.tileprovider.tilesource.TileSourceFactory
import org.osmdroid.util.GeoPoint
import org.osmdroid.views.MapView
import org.osmdroid.views.overlay.Marker
import org.osmdroid.views.overlay.Overlay
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
) {
    val query: String get() = "$west,$south,$east,$north"
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PointSelectionScreen(focus: GeoPoint?, onBack: () -> Unit) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val repository = remember { PointManagementRepository(prefs) }
    val scope = rememberCoroutineScope()
    val config = remember { prefs.getWebhookConfigs().firstOrNull { it.isEnabled } }
    val primary = MaterialTheme.colorScheme.primary
    val errorColor = MaterialTheme.colorScheme.error
    var drawMode by remember { mutableStateOf(true) }
    var selection by remember { mutableStateOf<SelectionBox?>(null) }
    var count by remember { mutableStateOf(0) }
    var loading by remember { mutableStateOf(false) }
    var notice by remember { mutableStateOf<String?>(null) }
    var undoId by remember { mutableStateOf<String?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var showExclude by remember { mutableStateOf(false) }
    var showDelete by remember { mutableStateOf(false) }
    var deleteWord by remember { mutableStateOf("") }
    var mapView by remember { mutableStateOf<MapView?>(null) }

    suspend fun idsInSelection(limit: Int = 1000): List<String> {
        val active = config ?: return emptyList()
        val box = selection ?: return emptyList()
        val ids = mutableListOf<String>()
        var offset = 0
        while (ids.size < limit) {
            val page = repository.loadPage(active, offset / 100, null, null, null, box.query)
            if (page.error != null) { error = page.error; break }
            ids += page.points.map { it.id }
            offset += 100
            if (offset >= page.total || page.points.isEmpty()) break
        }
        return ids.distinct().take(limit)
    }

    fun drawSelection(map: MapView) {
        val box = selection ?: return
        val polygon = org.osmdroid.views.overlay.Polygon(map).apply {
            points = listOf(
                GeoPoint(box.north, box.west), GeoPoint(box.north, box.east),
                GeoPoint(box.south, box.east), GeoPoint(box.south, box.west),
            )
            outlinePaint.color = primary.toArgb()
            outlinePaint.strokeWidth = 5f
            fillPaint.color = primary.copy(alpha = 0.18f).toArgb()
        }
        map.overlays.add(polygon)
        map.invalidate()
    }

    fun refreshSelection() {
        val map = mapView ?: return
        val box = selection ?: return
        loading = true
        error = null
        scope.launch {
            val active = config
            if (active != null) {
                val page = repository.loadPage(active, 0, null, null, null, box.query)
                count = page.total
                error = page.error
                map.overlays.removeAll { it is Marker }
                page.points.forEach { point ->
                    map.overlays.add(Marker(map).apply {
                        position = GeoPoint(point.lat, point.lon)
                        icon = GradientDrawable().apply {
                            shape = GradientDrawable.OVAL
                            setColor(primary.toArgb())
                            setStroke(2, AndroidColor.WHITE)
                            setSize(14, 14)
                        }
                        setAnchor(Marker.ANCHOR_CENTER, Marker.ANCHOR_CENTER)
                    })
                }
                map.invalidate()
            }
            loading = false
        }
    }

    Scaffold(topBar = {
        TopAppBar(
            title = { Text(stringResource(R.string.points_select_title)) },
            navigationIcon = {
                IconButton(onClick = onBack) {
                    Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = stringResource(R.string.settings_back_cd))
                }
            },
        )
    }) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            Box(Modifier.weight(1f).fillMaxWidth()) {
                AndroidView(
                    factory = { ctx ->
                        MapView(ctx).apply {
                            setTileSource(TileSourceFactory.MAPNIK)
                            setMultiTouchControls(true)
                            controller.setZoom(if (focus != null) 16.0 else 5.0)
                            controller.setCenter(focus ?: GeoPoint(20.0, 0.0))
                            overlays.add(RectangleDrawOverlay(
                                paintColor = primary.toArgb(),
                                shouldDraw = { drawMode },
                                onBox = { box ->
                                    selection = box
                                    overlays.removeAll { it is org.osmdroid.views.overlay.Polygon }
                                    invalidate()
                                    drawSelection(this)
                                    refreshSelection()
                                },
                            ))
                            mapView = this
                        }
                    },
                    modifier = Modifier.fillMaxSize(),
                )
                if (loading) CircularProgressIndicator(Modifier.align(Alignment.Center))
                Surface(
                    Modifier.align(Alignment.TopCenter).padding(12.dp),
                    shape = MaterialTheme.shapes.medium,
                    color = MaterialTheme.colorScheme.surface.copy(alpha = 0.9f),
                ) {
                    Text(
                        if (drawMode) stringResource(R.string.points_draw_hint) else stringResource(R.string.points_draw_enable),
                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
                        style = MaterialTheme.typography.labelMedium,
                    )
                }
            }
            Surface(tonalElevation = 3.dp) {
                Column(Modifier.fillMaxWidth().padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                        AppStatusPill(stringResource(R.string.points_selected_count, count), highlighted = count > 0)
                        Spacer(Modifier.weight(1f))
                        TextButton(onClick = { drawMode = !drawMode }) {
                            Text(if (drawMode) stringResource(R.string.points_draw_disable) else stringResource(R.string.points_draw_area))
                        }
                    }
                    error?.let { Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall) }
                    notice?.let {
                        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                            Text(it, style = MaterialTheme.typography.bodySmall, modifier = Modifier.weight(1f))
                            undoId?.let { id ->
                                TextButton(onClick = {
                                    val active = config ?: return@TextButton
                                    scope.launch {
                                        repository.undoCorrection(active, id)
                                            .onSuccess { notice = context.getString(R.string.points_undo_done); undoId = null; refreshSelection() }
                                            .onFailure { error = it.message }
                                    }
                                }) { Text(stringResource(R.string.library_undo)) }
                            }
                        }
                    }
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedButton(
                            enabled = count > 0 && !loading && config != null,
                            onClick = { showExclude = true },
                            modifier = Modifier.weight(1f),
                        ) {
                            Icon(Icons.Filled.VisibilityOff, contentDescription = null, modifier = Modifier.size(18.dp))
                            Text(stringResource(R.string.points_exclude), modifier = Modifier.padding(start = 6.dp))
                        }
                        Button(
                            enabled = count > 0 && !loading && config != null,
                            onClick = { deleteWord = ""; showDelete = true },
                            modifier = Modifier.weight(1f),
                        ) {
                            Icon(Icons.Filled.Delete, contentDescription = null, modifier = Modifier.size(18.dp))
                            Text(stringResource(R.string.points_delete_forever), modifier = Modifier.padding(start = 6.dp))
                        }
                    }
                    if (config == null) Text(stringResource(R.string.points_no_connection), color = MaterialTheme.colorScheme.error)
                }
            }
        }
    }

    if (showExclude) {
        AlertDialog(
            onDismissRequest = { showExclude = false },
            title = { Text(stringResource(R.string.points_exclude_confirm_title)) },
            text = { Text(stringResource(R.string.points_exclude_confirm_text, count)) },
            confirmButton = {
                TextButton(onClick = {
                    showExclude = false
                    val active = config
                    if (active != null) scope.launch {
                        loading = true
                        val ids = idsInSelection().toSet()
                        loading = false
                        if (ids.isEmpty()) return@launch
                        repository.exclude(active, ids)
                            .onSuccess {
                                undoId = it
                                notice = context.getString(R.string.points_excluded, ids.size)
                                refreshSelection()
                            }
                            .onFailure { error = it.message }
                    }
                }) { Text(stringResource(R.string.points_exclude)) }
            },
            dismissButton = { TextButton(onClick = { showExclude = false }) { Text(stringResource(R.string.library_cancel)) } },
        )
    }

    if (showDelete) {
        AlertDialog(
            onDismissRequest = { showDelete = false },
            title = { Text(stringResource(R.string.points_delete_irreversible)) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text(stringResource(R.string.points_delete_confirm_text, count))
                    Text(stringResource(R.string.points_type_delete))
                    OutlinedTextField(deleteWord, { deleteWord = it.uppercase() }, singleLine = true)
                }
            },
            confirmButton = {
                TextButton(enabled = deleteWord == "DELETE", onClick = {
                    showDelete = false
                    val active = config
                    val box = selection
                    if (active != null && box != null) scope.launch {
                        loading = true
                        repository.deleteBbox(active, box.query)
                            .onSuccess {
                                notice = context.getString(R.string.points_deleted, count)
                                undoId = null
                                selection = null
                                count = 0
                                mapView?.overlays?.removeAll { it is Marker || it is org.osmdroid.views.overlay.Polygon }
                                mapView?.invalidate()
                            }
                            .onFailure { error = it.message }
                        loading = false
                    }
                }) { Text(stringResource(R.string.points_delete_forever)) }
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
    private val onBox: (SelectionBox) -> Unit,
) : Overlay() {
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
        if (shadow || !drawing) return
        val left = minOf(startX, endX)
        val top = minOf(startY, endY)
        canvas.drawRect(left, top, maxOf(startX, endX), maxOf(startY, endY), paint)
        canvas.drawRect(left, top, maxOf(startX, endX), maxOf(startY, endY), stroke)
    }

    override fun onTouchEvent(event: android.view.MotionEvent, mapView: MapView): Boolean {
        if (!shouldDraw()) return false
        when (event.actionMasked) {
            android.view.MotionEvent.ACTION_DOWN -> {
                startX = event.x; startY = event.y; endX = event.x; endY = event.y; drawing = true
                mapView.parent.requestDisallowInterceptTouchEvent(true)
                mapView.invalidate()
                return true
            }
            android.view.MotionEvent.ACTION_MOVE -> {
                if (!drawing) return false
                endX = event.x; endY = event.y
                mapView.invalidate()
                return true
            }
            android.view.MotionEvent.ACTION_UP, android.view.MotionEvent.ACTION_CANCEL -> {
                if (!drawing) return false
                endX = event.x; endY = event.y
                drawing = false
                mapView.parent.requestDisallowInterceptTouchEvent(false)
                if (kotlin.math.abs(endX - startX) > 24f && kotlin.math.abs(endY - startY) > 24f) {
                    val a = mapView.projection.fromPixels(startX.toInt(), startY.toInt())
                    val b = mapView.projection.fromPixels(endX.toInt(), endY.toInt())
                    onBox(SelectionBox(
                        west = minOf(a.longitude, b.longitude),
                        south = minOf(a.latitude, b.latitude),
                        east = maxOf(a.longitude, b.longitude),
                        north = maxOf(a.latitude, b.latitude),
                    ))
                }
                mapView.invalidate()
                return true
            }
        }
        return false
    }
}
