package orbit.nex.steps.screens

import android.graphics.Color as AndroidColor
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import orbit.nex.steps.components.AppTooltipIconButton
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.R
import orbit.nex.steps.WebhookConfig
import orbit.nex.steps.location.GeoMapData
import orbit.nex.steps.location.GeoMapRepository
import orbit.nex.steps.location.GeoPointFeature
import orbit.nex.steps.ui.theme.Spacing
import org.json.JSONObject
import org.osmdroid.util.GeoPoint
import org.osmdroid.views.MapView
import org.osmdroid.views.overlay.Marker
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId
import java.time.ZoneOffset
import java.util.concurrent.TimeUnit

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PlacesRecalculateSheet(onDismiss: () -> Unit) {
    // Full-screen dialog (not a swipeable sheet) so map pans do not dismiss it.
    Dialog(
        onDismissRequest = onDismiss,
        properties = DialogProperties(
            usePlatformDefaultWidth = false,
            dismissOnBackPress = true,
            dismissOnClickOutside = false,
            decorFitsSystemWindows = false,
        ),
    ) {
        Scaffold(
            modifier = Modifier.fillMaxSize(),
            topBar = {
                TopAppBar(
                    title = { Text(stringResource(R.string.places_recalculate_title)) },
                    navigationIcon = {
                        AppTooltipIconButton(
                            onClick = onDismiss,
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
            containerColor = MaterialTheme.colorScheme.surface,
        ) { padding ->
            PlacesRecalculateContent(
                modifier = Modifier
                    .fillMaxWidth()
                    .fillMaxHeight()
                    .padding(padding),
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PlacesRecalculateContent(modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val scope = rememberCoroutineScope()
    val config = prefs.getWebhookConfigs().firstOrNull { it.isEnabled }
    val darkTheme = isSystemInDarkTheme()
    var fromDay by remember { mutableStateOf(LocalDate.now().minusDays(30)) }
    var toDay by remember { mutableStateOf(LocalDate.now()) }
    var pickingFrom by remember { mutableStateOf(false) }
    var pickingTo by remember { mutableStateOf(false) }
    var busy by remember { mutableStateOf(false) }
    var loadingMap by remember { mutableStateOf(false) }
    var status by remember { mutableStateOf<String?>(null) }
    val mapView = remember {
        configureOsm(context)
        MapView(context).apply {
            setMultiTouchControls(true)
            overlays.add(MapRotationOverlay(this))
            zoomController.setVisibility(org.osmdroid.views.CustomZoomButtonsController.Visibility.NEVER)
            controller.setZoom(12.0)
        }
    }
    DisposableEffect(Unit) {
        onDispose { mapView.onDetach() }
    }

    fun paintVisits(features: List<GeoPointFeature>) {
        mapView.overlays.removeAll { it is Marker || it is org.osmdroid.views.overlay.Polyline }
        val density = mapView.resources.displayMetrics.density
        val border = AndroidColor.rgb(14, 165, 233)
        val fill = AndroidColor.rgb(40, 170, 110)
        features.forEach { feature ->
            val marker = Marker(mapView)
            marker.position = GeoPoint(feature.lat, feature.lon)
            marker.title = feature.label ?: context.getString(R.string.visit_unidentified)
            marker.snippet = null
            marker.setAnchor(Marker.ANCHOR_CENTER, Marker.ANCHOR_CENTER)
            marker.icon = android.graphics.drawable.GradientDrawable().apply {
                shape = android.graphics.drawable.GradientDrawable.OVAL
                setSize((20 * density).toInt(), (20 * density).toInt())
                setColor(fill)
                setStroke((2.5f * density).toInt().coerceAtLeast(2), border)
            }
            styleMarker(marker, mapView, darkTheme, coordsFallback = false)
            mapView.overlays.add(marker)
        }
        if (features.isNotEmpty()) {
            val lats = features.map { it.lat }
            val lons = features.map { it.lon }
            val box = org.osmdroid.util.BoundingBox(
                lats.max(),
                lons.max(),
                lats.min(),
                lons.min(),
            )
            mapView.zoomToBoundingBox(box, true, (48 * density).toInt())
        }
        mapView.invalidate()
    }

    LaunchedEffect(config?.url, fromDay, toDay, darkTheme) {
        val active = config ?: return@LaunchedEffect
        loadingMap = true
        applyBasemap(
            mapView,
            MapStyle.NORMAL,
            darkTheme,
            MapTileScheme.fromKey(prefs.getMapTileScheme()),
        )
        val data = loadVisitsRange(prefs, active, fromDay, toDay)
        val visits = data.points.filter { it.layer == "visit" || it.layer == "place" }
        paintVisits(visits)
        loadingMap = false
    }

    Column(modifier.padding(horizontal = Spacing.lg), verticalArrangement = Arrangement.spacedBy(Spacing.md)) {
        Text(
            stringResource(R.string.places_recalculate_hint),
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        AndroidView(
            factory = { mapView },
            modifier = Modifier
                .fillMaxWidth()
                .weight(1f),
            update = { it.onResume() },
        )
        Surface(shape = RoundedCornerShape(18.dp), tonalElevation = 2.dp, modifier = Modifier.fillMaxWidth()) {
            Column(Modifier.padding(Spacing.md), verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                    OutlinedButton(onClick = { pickingFrom = true }, modifier = Modifier.weight(1f)) {
                        Text("${stringResource(R.string.places_recalculate_from)}: $fromDay")
                    }
                    OutlinedButton(onClick = { pickingTo = true }, modifier = Modifier.weight(1f)) {
                        Text("${stringResource(R.string.places_recalculate_to)}: $toDay")
                    }
                }
                if (loadingMap) {
                    Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                        CircularProgressIndicator(Modifier.size(16.dp), strokeWidth = 2.dp)
                        Text(stringResource(R.string.map_layer_places), style = MaterialTheme.typography.bodySmall)
                    }
                }
                Button(
                    enabled = !busy && config != null && !toDay.isBefore(fromDay),
                    onClick = {
                        val active = config ?: return@Button
                        busy = true
                        status = null
                        scope.launch {
                            val ok = queuePlacesRecalculate(prefs, active, fromDay, toDay)
                            status = context.getString(
                                if (ok) R.string.places_recalculate_queued else R.string.save_failed_toast,
                            )
                            busy = false
                        }
                    },
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    if (busy) CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp)
                    else Text(stringResource(R.string.places_recalculate_run))
                }
                status?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
            }
        }
    }
    if (pickingFrom || pickingTo) {
        val state = rememberDatePickerState(
            initialSelectedDateMillis = (if (pickingFrom) fromDay else toDay)
                .atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli(),
        )
        DatePickerDialog(
            onDismissRequest = { pickingFrom = false; pickingTo = false },
            confirmButton = {
                TextButton(onClick = {
                    val millis = state.selectedDateMillis ?: return@TextButton
                    val day = Instant.ofEpochMilli(millis).atZone(ZoneId.systemDefault()).toLocalDate()
                    if (pickingFrom) fromDay = day else toDay = day
                    pickingFrom = false
                    pickingTo = false
                }) { Text(stringResource(android.R.string.ok)) }
            },
            dismissButton = {
                TextButton(onClick = { pickingFrom = false; pickingTo = false }) {
                    Text(stringResource(android.R.string.cancel))
                }
            },
        ) { DatePicker(state = state) }
    }
}

private suspend fun loadVisitsRange(
    prefs: PreferencesManager,
    config: WebhookConfig,
    from: LocalDate,
    to: LocalDate,
): GeoMapData = withContext(Dispatchers.IO) {
    runCatching {
        val zone = ZoneId.systemDefault()
        val fromIso = from.atStartOfDay(zone).toInstant().toString()
        val toIso = to.plusDays(1).atStartOfDay(zone).toInstant().minusMillis(1).toString()
        val url = GeoMapRepository.apiBase(config).trimEnd('/') +
            "/geolocation_map?from=$fromIso&to=$toIso&zoom=${GeoMapRepository.MAP_ZOOM}&layers=visits,places"
        val request = Request.Builder().url(url)
            .apply {
                config.headers.forEach { (k, v) -> addHeader(k, v) }
                addHeader("X-Device-Id", prefs.getDeviceId())
                addHeader("Accept", "application/json")
            }
            .get()
            .build()
        val client = OkHttpClient.Builder()
            .connectTimeout(20, TimeUnit.SECONDS)
            .readTimeout(45, TimeUnit.SECONDS)
            .build()
        client.newCall(request).execute().use { response ->
            if (!response.isSuccessful) return@use GeoMapData(error = "HTTP ${response.code}")
            GeoMapRepository(prefs).let { repo ->
                // Reuse parse via a day load shape: repository parse is private, so decode here.
                parseVisitMap(JSONObject(response.body?.string().orEmpty()))
            }
        }
    }.getOrElse { GeoMapData(error = it.message) }
}

private fun parseVisitMap(root: JSONObject): GeoMapData {
    if (!root.optBoolean("ok", false)) {
        return GeoMapData(error = root.optString("error").ifBlank { "error" })
    }
    val points = mutableListOf<GeoPointFeature>()
    val features = root.optJSONObject("geojson")?.optJSONArray("features") ?: return GeoMapData()
    for (i in 0 until features.length()) {
        val feature = features.optJSONObject(i) ?: continue
        val properties = feature.optJSONObject("properties") ?: continue
        val geometry = feature.optJSONObject("geometry") ?: continue
        if (geometry.optString("type") != "Point") continue
        val layer = properties.optString("layer")
        if (layer != "visit" && layer != "place") continue
        val coordinates = geometry.optJSONArray("coordinates") ?: continue
        if (coordinates.length() < 2) continue
        val lon = coordinates.optDouble(0)
        val lat = coordinates.optDouble(1)
        points += GeoPointFeature(
            layer = layer,
            lat = lat,
            lon = lon,
            label = when (layer) {
                "place" -> properties.optString("name").ifBlank { null }
                else -> properties.optString("place_name").ifBlank { properties.optString("category") }.ifBlank { null }
            },
            count = 1,
            intensity = 1f,
            osmType = properties.optString("osm_type").ifBlank { null },
            osmId = properties.optLong("osm_id").takeIf { properties.has("osm_id") && !properties.isNull("osm_id") && it > 0L },
        )
    }
    return GeoMapData(points = points)
}

private suspend fun queuePlacesRecalculate(
    prefs: PreferencesManager,
    config: WebhookConfig,
    from: LocalDate,
    to: LocalDate,
): Boolean = withContext(Dispatchers.IO) {
    runCatching {
        val zone = ZoneId.systemDefault()
        val fromIso = from.atStartOfDay(zone).toInstant().toString()
        val toIso = to.plusDays(1).atStartOfDay(zone).toInstant().minusMillis(1).toString()
        val body = JSONObject()
            .put("action", "recalculate")
            .put("from", fromIso)
            .put("to", toIso)
            .toString()
            .toRequestBody("application/json; charset=utf-8".toMediaType())
        val request = Request.Builder()
            .url(GeoMapRepository.apiBase(config).trimEnd('/') + "/geolocation_place_prefs")
            .post(body)
            .apply {
                config.headers.forEach { (k, v) -> addHeader(k, v) }
                addHeader("X-Device-Id", prefs.getDeviceId())
                addHeader("Accept", "application/json")
            }
            .build()
        OkHttpClient().newCall(request).execute().use { response ->
            response.isSuccessful && JSONObject(response.body?.string().orEmpty()).optBoolean("ok")
        }
    }.getOrDefault(false)
}
