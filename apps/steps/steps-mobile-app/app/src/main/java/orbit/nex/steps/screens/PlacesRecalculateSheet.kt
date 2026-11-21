package orbit.nex.steps.screens

import android.graphics.Color as AndroidColor
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.material3.rememberModalBottomSheetState
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
import orbit.nex.steps.location.GeoLatLon
import orbit.nex.steps.location.GeoMapRepository
import orbit.nex.steps.ui.theme.Spacing
import org.json.JSONObject
import org.osmdroid.util.GeoPoint
import org.osmdroid.views.MapView
import org.osmdroid.views.overlay.Marker
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId
import java.time.ZoneOffset

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PlacesRecalculateSheet(onDismiss: () -> Unit) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    ModalBottomSheet(onDismissRequest = onDismiss, sheetState = sheetState) {
        PlacesRecalculateContent(
            modifier = Modifier
                .fillMaxWidth()
                .fillMaxHeight(0.92f)
                .navigationBarsPadding(),
        )
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
    var status by remember { mutableStateOf<String?>(null) }
    var places by remember { mutableStateOf<List<Pair<String, GeoLatLon>>>(emptyList()) }
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

    LaunchedEffect(config?.url) {
        val active = config ?: return@LaunchedEffect
        applyBasemap(mapView, MapStyle.NORMAL, darkTheme)
        val data = GeoMapRepository(prefs, cacheDir = context.cacheDir).loadDay(active, LocalDate.now())
        places = data.points.filter { it.layer == "place" || it.layer == "visit" }
            .map { (it.label ?: "Place") to GeoLatLon(it.lat, it.lon) }
            .distinctBy { "${it.second.lat},${it.second.lon}" }
        mapView.overlays.removeAll { it is Marker }
        val density = mapView.resources.displayMetrics.density
        places.forEach { (title, pos) ->
            val marker = Marker(mapView)
            marker.position = GeoPoint(pos.lat, pos.lon)
            marker.title = title
            marker.setAnchor(Marker.ANCHOR_CENTER, Marker.ANCHOR_CENTER)
            marker.icon = android.graphics.drawable.GradientDrawable().apply {
                shape = android.graphics.drawable.GradientDrawable.OVAL
                setSize((18 * density).toInt(), (18 * density).toInt())
                setColor(AndroidColor.rgb(40, 170, 110))
                setStroke((2 * density).toInt(), AndroidColor.WHITE)
            }
            mapView.overlays.add(marker)
        }
        places.firstOrNull()?.let {
            mapView.controller.setCenter(GeoPoint(it.second.lat, it.second.lon))
            mapView.controller.setZoom(13.0)
        }
        mapView.invalidate()
    }

    Column(modifier.padding(horizontal = Spacing.lg), verticalArrangement = Arrangement.spacedBy(Spacing.md)) {
        Text(stringResource(R.string.places_recalculate_title), style = MaterialTheme.typography.titleLarge)
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
