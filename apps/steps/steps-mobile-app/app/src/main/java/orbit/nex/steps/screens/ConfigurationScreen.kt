package orbit.nex.steps.screens
import orbit.nex.steps.ui.theme.Spacing
import orbit.nex.steps.ui.theme.semantic
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.material3.Switch
import orbit.nex.steps.location.LocationPermissions
import orbit.nex.steps.location.LocationTrackingManager
import orbit.nex.steps.location.TrackingState
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CloudDone
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.FilledTonalButton
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ProgressIndicatorDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import orbit.nex.steps.BuildConfig
import orbit.nex.steps.HealthDataType
import orbit.nex.steps.MockPayloadBuilder
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.R
import orbit.nex.steps.WebhookConfig
import orbit.nex.steps.WebhookManager
import orbit.nex.steps.components.ManualSyncCard
import orbit.nex.steps.components.computeNextSyncInfo
import orbit.nex.steps.components.formatSyncCountdown
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request

private object HomeMetricsCache {
    var day: java.time.LocalDate? = null
    var updatedAt = 0L
    var metrics = emptyList<orbit.nex.steps.dashboard.DashboardMetric>()
}
private enum class ConnectionTestResult { SUCCESS, FAILURE }
@Composable
fun ConfigurationScreen(modifier: Modifier = Modifier, historyRequest: Int = 0) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    var notice by remember { mutableStateOf<String?>(null) }
    var noticeError by remember { mutableStateOf(false) }
    var metrics by remember { mutableStateOf(if (HomeMetricsCache.day == java.time.LocalDate.now()) HomeMetricsCache.metrics else emptyList()) }
    LaunchedEffect(Unit) {
        while (true) {
            if (HomeMetricsCache.day == java.time.LocalDate.now() && System.currentTimeMillis() - HomeMetricsCache.updatedAt < 60_000) {
                metrics = HomeMetricsCache.metrics
                delay(60_000 - (System.currentTimeMillis() - HomeMetricsCache.updatedAt).coerceAtLeast(0L))
                continue
            }
            val loaded = withContext(Dispatchers.IO) {
                runCatching {
                    val health = orbit.nex.steps.HealthConnectManager(context)
                    health.readTodayDashboardStats(health.getGrantedPermissions()).getOrThrow().metrics
                }.getOrDefault(metrics)
            }
            metrics = loaded
            HomeMetricsCache.metrics = loaded
            HomeMetricsCache.day = java.time.LocalDate.now()
            HomeMetricsCache.updatedAt = System.currentTimeMillis()
            delay(60_000)
        }
    }
    LaunchedEffect(notice) { if (notice != null) { delay(3500); notice = null } }
    androidx.compose.foundation.layout.BoxWithConstraints(modifier.fillMaxSize()) {
        val minContentHeight = maxHeight
        Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).then(Modifier.heightIn(min = minContentHeight)).padding(horizontal = 16.dp, vertical = 12.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            TodayCard(metrics, runCatching { org.json.JSONObject(prefs.getPersonalSettingsCache().orEmpty()).optInt("goal_steps") }.getOrDefault(0))
            LocationTrackingCard(prefs) { message, error -> notice = message; noticeError = error }
            NextSyncCard(prefs, historyRequest, Modifier.weight(1f, fill = true))
            Spacer(Modifier.height(4.dp))
        }
        notice?.let { orbit.nex.steps.components.SavedToast(it, Modifier.align(Alignment.BottomCenter).padding(bottom = 8.dp), noticeError) { notice = null } }
    }
}
@Composable
private fun TodayCard(metrics: List<orbit.nex.steps.dashboard.DashboardMetric>, goal: Int) {
    val steps = metrics.firstOrNull { it.type == HealthDataType.STEPS }?.value ?: "—"
    val distance = metrics.firstOrNull { it.type == HealthDataType.DISTANCE }?.value?.let { "$it km" } ?: "—"
    val count = steps.toLongOrNull()
    Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth(), shape = androidx.compose.foundation.shape.RoundedCornerShape(22.dp)) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text(stringResource(R.string.stats_steps_today_suffix).replaceFirstChar { it.uppercase() }, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text(count?.let { java.text.NumberFormat.getIntegerInstance().format(it) } ?: steps, style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
                }
                androidx.compose.material3.Surface(shape = androidx.compose.foundation.shape.RoundedCornerShape(14.dp), color = MaterialTheme.colorScheme.surface.copy(alpha = .45f)) {
                    Column(Modifier.padding(horizontal = 15.dp, vertical = 10.dp)) {
                        Text(stringResource(R.string.stats_distance), style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        Text(distance, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                    }
                }
            }
            androidx.compose.material3.LinearProgressIndicator(progress = { if (goal > 0) ((count ?: 0L) / goal.toFloat()).coerceIn(0f, 1f) else 0f }, modifier = Modifier.fillMaxWidth().height(6.dp), color = MaterialTheme.colorScheme.onSurface, trackColor = MaterialTheme.colorScheme.surface)
        }
    }
}
@Composable
private fun LocationTrackingCard(prefs: PreferencesManager, onNotice: (String, Boolean) -> Unit) {
    val context = LocalContext.current
    val manager = remember { LocationTrackingManager(context) }
    var state by remember { mutableStateOf(manager.state()) }
    var lastSync by remember { mutableStateOf(prefs.getLocationUploadOkAt()) }
    var meters by remember { mutableStateOf(0.0) }
    fun report() {
        state = manager.state()
        val error = state == TrackingState.PERMISSION_MISSING || state == TrackingState.NOT_CONNECTED
        onNotice(context.getString(when (state) {
            TrackingState.RUNNING -> R.string.tracking_status_running
            TrackingState.PAUSED -> R.string.tracking_status_paused
            TrackingState.PERMISSION_MISSING -> R.string.tracking_status_permission
            TrackingState.NOT_CONNECTED -> R.string.tracking_status_no_connection
            TrackingState.OFF -> R.string.tracking_status_off
        }), error)
    }
    LaunchedEffect(Unit) {
        while (true) {
            manager.reconcile()
            state = manager.state()
            lastSync = prefs.getLocationUploadOkAt()
            meters = withContext(Dispatchers.IO) {
                val events = runCatching { orbit.nex.steps.location.LocationEventStore(context).eventsForDay(java.time.LocalDate.now()) }.getOrDefault(emptyList())
                var previous: Pair<Double, Double>? = null
                var total = 0.0
                events.forEach { event ->
                    val point = runCatching { org.json.JSONObject(event.payload) }.getOrNull()
                    val lat = point?.optDouble("latitude", Double.NaN) ?: Double.NaN
                    val lon = point?.optDouble("longitude", Double.NaN) ?: Double.NaN
                    if (!lat.isNaN() && !lon.isNaN()) {
                        previous?.let { val distance = FloatArray(1); android.location.Location.distanceBetween(it.first, it.second, lat, lon, distance); total += distance[0] }
                        previous = lat to lon
                    }
                }
                total
            }
            delay(5_000)
        }
    }
    val permissionLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) {
        if (LocationPermissions.hasForeground(context)) manager.start()
        report()
    }
    Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth(), shape = androidx.compose.foundation.shape.RoundedCornerShape(22.dp)) {
        Column(Modifier.padding(16.dp)) {
            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text(stringResource(R.string.tracking_title) + if (state == TrackingState.RUNNING) " •" else "", style = MaterialTheme.typography.titleMedium)
                    Text(stringResource(when (state) { TrackingState.RUNNING -> R.string.tracking_status_running; TrackingState.PAUSED -> R.string.tracking_status_paused; TrackingState.PERMISSION_MISSING -> R.string.tracking_status_permission; TrackingState.NOT_CONNECTED -> R.string.tracking_status_no_connection; TrackingState.OFF -> R.string.tracking_status_off }), style = MaterialTheme.typography.bodySmall, color = if (state == TrackingState.RUNNING) MaterialTheme.semantic.success else MaterialTheme.colorScheme.onSurfaceVariant)
                }
                Switch(checked = state == TrackingState.RUNNING || state == TrackingState.PAUSED, onCheckedChange = { enabled ->
                    if (enabled && !LocationPermissions.hasForeground(context)) permissionLauncher.launch(LocationPermissions.foregroundPermissions())
                    else { if (enabled) manager.start() else manager.stop(); report() }
                })
            }
            androidx.compose.material3.HorizontalDivider(Modifier.padding(vertical = 10.dp), color = MaterialTheme.colorScheme.outlineVariant.copy(alpha = .35f))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text(stringResource(R.string.home_last_sync, lastSync?.let { orbit.nex.steps.location.formatLocationUploadTime(it) } ?: "—"), style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Text(stringResource(R.string.home_tracked, String.format(java.util.Locale.getDefault(), "%.2f km", meters / 1000)), style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}
@Composable
private fun NextSyncCard(prefs: PreferencesManager, historyRequest: Int, modifier: Modifier = Modifier) {
    var now by remember { mutableLongStateOf(System.currentTimeMillis()) }
    LaunchedEffect(Unit) { while (true) { delay(1_000); now = System.currentTimeMillis() } }
    val info = computeNextSyncInfo(prefs, now)
    val remaining = info?.let { (it.nextSyncMs - now).coerceAtLeast(0L) } ?: 0L
    val fraction = info?.let { if (it.periodMs > 0) (remaining.toFloat() / it.periodMs).coerceIn(0f, 1f) else 0f } ?: 0f
    val animated by animateFloatAsState(fraction, tween(800, easing = LinearEasing), label = "syncProgress")
    Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = modifier.fillMaxWidth().heightIn(min = 245.dp), shape = androidx.compose.foundation.shape.RoundedCornerShape(22.dp)) {
        Column(Modifier.fillMaxSize().padding(vertical = 24.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.SpaceEvenly) {
            Box(Modifier.size(160.dp), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(progress = { animated }, modifier = Modifier.fillMaxSize(), strokeWidth = 7.dp, trackColor = MaterialTheme.colorScheme.surface, color = MaterialTheme.colorScheme.onSurface)
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(stringResource(R.string.next_sync_title).uppercase(), style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant, letterSpacing = 1.sp)
                    Text(if (info == null) "—" else formatSyncCountdown(remaining), style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
                }
            }
            ManualSyncCard(historyRequest = historyRequest)
        }
    }
}

private suspend fun testConnection(context: android.content.Context, config: WebhookConfig?): ConnectionTestResult = withContext(Dispatchers.IO) {
    if (config == null) return@withContext ConnectionTestResult.FAILURE
    runCatching {
        val prefs = PreferencesManager(context)
        val builder = Request.Builder().url(config.url.trimEnd('/') + "/profile")
        config.headers.forEach { (key, value) -> builder.addHeader(key, value) }
        builder.addHeader("Accept", "application/json")
        if (config.headers.keys.none { it.equals("X-Device-Id", ignoreCase = true) }) {
            builder.addHeader("X-Device-Id", prefs.getDeviceId())
        }
        OkHttpClient().newCall(builder.build()).execute().use { response ->
            if (response.code == 401 || response.code == 403) prefs.markAuthError(response.code)
            if (!response.isSuccessful) error("HTTP ${response.code}")
            prefs.markSyncSuccess()
            ConnectionTestResult.SUCCESS
        }
    }.getOrDefault(ConnectionTestResult.FAILURE)
}
