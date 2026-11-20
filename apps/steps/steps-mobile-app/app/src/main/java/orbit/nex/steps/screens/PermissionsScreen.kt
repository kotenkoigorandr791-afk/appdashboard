package orbit.nex.steps.screens

import android.content.Intent
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.OpenInNew
import androidx.compose.material.icons.filled.BarChart
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Error
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.nativeCanvas
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.health.connect.client.permission.HealthPermission
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.time.format.FormatStyle
import java.util.Locale
import kotlin.math.roundToInt
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import orbit.nex.steps.HealthConnectManager
import orbit.nex.steps.HealthDataType
import orbit.nex.steps.R
import orbit.nex.steps.SyncManager
import orbit.nex.steps.dashboard.DashboardFormatter
import orbit.nex.steps.ui.theme.Spacing

private enum class PermFilter { ALL, GRANTED, WITH_DATA }

private val WarningYellow = Color(0xFFF5C542)
private val DangerRed = Color(0xFFEF5350)

@Composable
fun HealthPermissionsScreen(
    grantedPermissionsSet: Set<String>,
    onRequestPermissions: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val manager = remember { HealthConnectManager(context) }
    var query by remember { mutableStateOf("") }
    var filter by remember { mutableStateOf(PermFilter.ALL) }
    var probes by remember { mutableStateOf<Map<HealthDataType, HealthConnectManager.MetricProbe>>(emptyMap()) }
    var probing by remember { mutableStateOf(true) }
    var selected by remember { mutableStateOf<HealthDataType?>(null) }
    val total = HealthDataType.entries.size
    val grantedCount = HealthDataType.entries.count {
        HealthPermission.getReadPermission(it.recordClass) in grantedPermissionsSet
    }
    val pendingCount = total - grantedCount
    val withDataCount = probes.count { it.value.status == HealthConnectManager.MetricStatus.READY }
    val emptyCount = probes.count { it.value.status == HealthConnectManager.MetricStatus.EMPTY }

    LaunchedEffect(grantedPermissionsSet) {
        probing = true
        val next = linkedMapOf<HealthDataType, HealthConnectManager.MetricProbe>()
        HealthDataType.entries.forEach { type ->
            val granted = HealthPermission.getReadPermission(type.recordClass) in grantedPermissionsSet
            next[type] = withContext(Dispatchers.IO) { manager.probeMetric(type, granted) }
        }
        probes = next
        probing = false
    }

    val filtered = HealthDataType.entries.filter { type ->
        val probe = probes[type]
        val granted = HealthPermission.getReadPermission(type.recordClass) in grantedPermissionsSet
        val matchesFilter = when (filter) {
            PermFilter.ALL -> true
            PermFilter.GRANTED -> granted
            PermFilter.WITH_DATA -> probe?.status == HealthConnectManager.MetricStatus.READY
        }
        val q = query.trim()
        val matchesQuery = q.isEmpty() ||
            context.getString(type.nameResId).contains(q, ignoreCase = true) ||
            context.getString(type.rationaleResId).contains(q, ignoreCase = true)
        matchesFilter && matchesQuery
    }

    Box(modifier.fillMaxWidth()) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = Spacing.lg, vertical = Spacing.md),
            verticalArrangement = Arrangement.spacedBy(Spacing.md),
        ) {
            Text(
                stringResource(R.string.perms_tap_hint),
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier
                    .background(MaterialTheme.colorScheme.surfaceVariant, RoundedCornerShape(20.dp))
                    .padding(horizontal = 12.dp, vertical = 6.dp),
            )
            Card(
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                shape = RoundedCornerShape(20.dp),
                modifier = Modifier.fillMaxWidth(),
            ) {
                Column(Modifier.padding(Spacing.lg), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Column(Modifier.weight(1f)) {
                            Text(
                                stringResource(R.string.perms_granted_summary, grantedCount, total),
                                style = MaterialTheme.typography.titleLarge,
                                fontWeight = FontWeight.Bold,
                            )
                            Text(
                                when {
                                    pendingCount > 0 -> stringResource(R.string.perms_hc_pending, pendingCount)
                                    emptyCount > 0 && !probing -> stringResource(R.string.perms_hc_empty_hint, emptyCount)
                                    grantedCount > 0 -> stringResource(R.string.perms_hc_active)
                                    else -> stringResource(R.string.perms_hc_inactive)
                                },
                                style = MaterialTheme.typography.bodyMedium,
                                color = when {
                                    pendingCount > 0 || emptyCount > 0 -> WarningYellow
                                    grantedCount > 0 -> MaterialTheme.colorScheme.primary
                                    else -> MaterialTheme.colorScheme.onSurfaceVariant
                                },
                            )
                        }
                        when {
                            pendingCount > 0 || (emptyCount > 0 && !probing) ->
                                Icon(Icons.Filled.Warning, null, tint = WarningYellow, modifier = Modifier.size(36.dp))
                            grantedCount == total && emptyCount == 0 && !probing ->
                                Icon(Icons.Filled.CheckCircle, null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(36.dp))
                        }
                    }
                    Text(
                        stringResource(R.string.perms_rationale_desc),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    if (pendingCount > 0) {
                        Button(onClick = onRequestPermissions, modifier = Modifier.fillMaxWidth()) {
                            Icon(Icons.Filled.Warning, null, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(8.dp))
                            Text(stringResource(R.string.perms_grant_missing, pendingCount))
                        }
                    }
                    OutlinedButton(onClick = { openHealthConnect(context) }, modifier = Modifier.fillMaxWidth()) {
                        Icon(Icons.AutoMirrored.Filled.OpenInNew, null, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(8.dp))
                        Text(stringResource(R.string.config_manage_hc))
                    }
                }
            }
            OutlinedTextField(
                value = query,
                onValueChange = { query = it },
                singleLine = true,
                leadingIcon = { Icon(Icons.Filled.Search, null) },
                placeholder = { Text(stringResource(R.string.perms_search_placeholder, total)) },
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
            )
            Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                FilterChip(selected = filter == PermFilter.ALL, onClick = { filter = PermFilter.ALL }, label = { Text(stringResource(R.string.perms_filter_all, total)) })
                FilterChip(selected = filter == PermFilter.GRANTED, onClick = { filter = PermFilter.GRANTED }, label = { Text(stringResource(R.string.perms_filter_granted, grantedCount)) })
                FilterChip(selected = filter == PermFilter.WITH_DATA, onClick = { filter = PermFilter.WITH_DATA }, label = { Text(stringResource(R.string.perms_filter_with_data, withDataCount)) })
            }
            if (probing && probes.isEmpty()) {
                Box(Modifier.fillMaxWidth().height(120.dp), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator()
                }
            }
            filtered.forEach { type ->
                val granted = HealthPermission.getReadPermission(type.recordClass) in grantedPermissionsSet
                val probe = probes[type] ?: HealthConnectManager.MetricProbe(
                    status = if (granted) HealthConnectManager.MetricStatus.EMPTY else HealthConnectManager.MetricStatus.DENIED,
                    todayLabel = DashboardFormatter.NO_DATA,
                    hasChart = false,
                )
                MetricPermissionCard(
                    type = type,
                    probe = probe,
                    onClick = {
                        if (probe.status == HealthConnectManager.MetricStatus.DENIED) onRequestPermissions()
                        else selected = type
                    },
                )
            }
            Spacer(Modifier.height(24.dp))
        }
        selected?.let { type ->
            val granted = HealthPermission.getReadPermission(type.recordClass) in grantedPermissionsSet
            MetricDetailSheet(
                type = type,
                probe = probes[type] ?: HealthConnectManager.MetricProbe(
                    status = if (granted) HealthConnectManager.MetricStatus.EMPTY else HealthConnectManager.MetricStatus.DENIED,
                    todayLabel = DashboardFormatter.NO_DATA,
                    hasChart = false,
                ),
                onDismiss = { selected = null },
            )
        }
    }
}

@Composable
private fun MetricPermissionCard(
    type: HealthDataType,
    probe: HealthConnectManager.MetricProbe,
    onClick: () -> Unit,
) {
    val statusColor = when (probe.status) {
        HealthConnectManager.MetricStatus.READY -> MaterialTheme.colorScheme.primary
        HealthConnectManager.MetricStatus.EMPTY -> WarningYellow
        HealthConnectManager.MetricStatus.DENIED, HealthConnectManager.MetricStatus.ERROR -> DangerRed
    }
    Card(
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
        shape = RoundedCornerShape(16.dp),
        modifier = Modifier.fillMaxWidth().clickable(onClick = onClick),
    ) {
        Row(
            Modifier.padding(14.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Box(
                Modifier.size(42.dp).background(statusColor.copy(alpha = 0.15f), CircleShape),
                contentAlignment = Alignment.Center,
            ) {
                Icon(iconForDataType(type), null, tint = statusColor, modifier = Modifier.size(22.dp))
            }
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(stringResource(type.nameResId), style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    if (probe.status == HealthConnectManager.MetricStatus.READY && probe.todayLabel != DashboardFormatter.NO_DATA) {
                        Text("  ·  ${probe.todayLabel}", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant, maxLines = 1)
                    }
                }
                Text(stringResource(type.rationaleResId), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant, maxLines = 2, overflow = TextOverflow.Ellipsis)
            }
            if (probe.hasChart) {
                Icon(Icons.Filled.BarChart, null, tint = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.size(20.dp))
            }
            Icon(
                imageVector = when (probe.status) {
                    HealthConnectManager.MetricStatus.READY -> Icons.Filled.CheckCircle
                    HealthConnectManager.MetricStatus.EMPTY -> Icons.Filled.Warning
                    HealthConnectManager.MetricStatus.DENIED, HealthConnectManager.MetricStatus.ERROR -> Icons.Filled.Error
                },
                contentDescription = stringResource(when (probe.status) {
                    HealthConnectManager.MetricStatus.READY -> R.string.perms_status_granted
                    HealthConnectManager.MetricStatus.EMPTY -> R.string.perms_status_empty
                    HealthConnectManager.MetricStatus.DENIED -> R.string.perms_status_denied
                    HealthConnectManager.MetricStatus.ERROR -> R.string.perms_status_error
                }),
                tint = statusColor,
                modifier = Modifier.size(20.dp),
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun MetricDetailSheet(type: HealthDataType, probe: HealthConnectManager.MetricProbe, onDismiss: () -> Unit) {
    val context = LocalContext.current
    val manager = remember { HealthConnectManager(context) }
    val scope = rememberCoroutineScope()
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    var series by remember { mutableStateOf<HealthConnectManager.MetricSeries?>(null) }
    val canChart = probe.status == HealthConnectManager.MetricStatus.READY
    var loading by remember { mutableStateOf(canChart) }
    var syncing by remember { mutableStateOf(false) }
    var syncMessage by remember { mutableStateOf<String?>(null) }
    val badgeColor = when (probe.status) {
        HealthConnectManager.MetricStatus.READY -> MaterialTheme.colorScheme.primary
        HealthConnectManager.MetricStatus.EMPTY -> WarningYellow
        HealthConnectManager.MetricStatus.DENIED, HealthConnectManager.MetricStatus.ERROR -> DangerRed
    }

    LaunchedEffect(type, canChart) {
        if (!canChart) {
            series = null
            loading = false
            return@LaunchedEffect
        }
        loading = true
        series = withContext(Dispatchers.IO) { manager.readMetricSeries(type, 30).getOrNull() }
        loading = false
    }

    ModalBottomSheet(onDismissRequest = onDismiss, sheetState = sheetState) {
        Column(
            Modifier.fillMaxWidth().padding(horizontal = Spacing.lg).padding(bottom = Spacing.xxl),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    Modifier.size(44.dp).background(badgeColor.copy(alpha = 0.15f), CircleShape),
                    contentAlignment = Alignment.Center,
                ) { Icon(iconForDataType(type), null, tint = badgeColor) }
                Spacer(Modifier.width(12.dp))
                Column(Modifier.weight(1f)) {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(stringResource(type.nameResId), style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                        Surface(color = badgeColor.copy(alpha = 0.18f), shape = RoundedCornerShape(20.dp)) {
                            Text(
                                stringResource(when (probe.status) {
                                    HealthConnectManager.MetricStatus.READY -> R.string.perms_status_granted
                                    HealthConnectManager.MetricStatus.EMPTY -> R.string.perms_status_empty
                                    HealthConnectManager.MetricStatus.DENIED -> R.string.perms_status_denied
                                    HealthConnectManager.MetricStatus.ERROR -> R.string.perms_status_error
                                }),
                                modifier = Modifier.padding(horizontal = 10.dp, vertical = 3.dp),
                                style = MaterialTheme.typography.labelSmall,
                                color = badgeColor,
                            )
                        }
                    }
                    Text(stringResource(R.string.perms_chart_subtitle), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                IconButton(onClick = onDismiss) { Icon(Icons.Filled.Close, stringResource(R.string.action_close)) }
            }

            if (!canChart) {
                Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), shape = RoundedCornerShape(16.dp), modifier = Modifier.fillMaxWidth()) {
                    Text(
                        stringResource(when (probe.status) {
                            HealthConnectManager.MetricStatus.DENIED -> R.string.perms_detail_denied
                            HealthConnectManager.MetricStatus.EMPTY -> R.string.perms_detail_empty
                            else -> R.string.perms_detail_error
                        }),
                        modifier = Modifier.padding(16.dp),
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            } else if (loading) {
                Box(Modifier.fillMaxWidth().height(220.dp), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
            } else {
                val data = series
                Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), shape = RoundedCornerShape(16.dp)) {
                    Row(Modifier.fillMaxWidth().padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                        Column(Modifier.weight(1f)) {
                            Text(LocalDate.now().format(DateTimeFormatter.ofLocalizedDate(FormatStyle.MEDIUM)), style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            Text(data?.todayLabel ?: DashboardFormatter.NO_DATA, style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
                        }
                        Text(stringResource(R.string.perms_daily_totals), style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
                MetricMonthChart(points = data?.points.orEmpty(), average = data?.average ?: 0.0, unit = data?.unitLabel.orEmpty())
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    StatTile(stringResource(R.string.perms_stat_avg), formatStat(type, data?.average ?: 0.0), Modifier.weight(1f))
                    StatTile(stringResource(R.string.perms_stat_peak), formatStat(type, data?.peak ?: 0.0), Modifier.weight(1f))
                    StatTile(stringResource(R.string.perms_stat_records), (data?.records ?: 0).toString(), Modifier.weight(1f))
                }
            }

            syncMessage?.let { Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant) }
            Button(
                enabled = !syncing && probe.status != HealthConnectManager.MetricStatus.DENIED,
                onClick = {
                    syncing = true
                    syncMessage = null
                    scope.launch {
                        val result = withContext(Dispatchers.IO) { SyncManager(context).performSync(timeRangeDays = 30, syncType = "manual") }
                        syncMessage = result.fold(
                            onSuccess = { context.getString(R.string.perms_sync_done) },
                            onFailure = { it.message ?: context.getString(R.string.perms_sync_failed) },
                        )
                        syncing = false
                    }
                },
                modifier = Modifier.fillMaxWidth(),
            ) {
                if (syncing) CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp)
                else Icon(Icons.Filled.Refresh, null, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(8.dp))
                Text(stringResource(R.string.perms_sync_metric))
            }
        }
    }
}

@Composable
private fun StatTile(label: String, value: String, modifier: Modifier = Modifier) {
    Card(modifier = modifier, colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), shape = RoundedCornerShape(14.dp)) {
        Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(label, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Text(value, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
        }
    }
}

@Composable
private fun MetricMonthChart(
    points: List<HealthConnectManager.DailyMetricPoint>,
    average: Double,
    unit: String,
) {
    val density = LocalDensity.current
    val barColor = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.35f)
    val todayColor = MaterialTheme.colorScheme.onSurface
    val gridColor = MaterialTheme.colorScheme.outlineVariant
    val labelColor = MaterialTheme.colorScheme.onSurfaceVariant
    val today = LocalDate.now()
    val start = today.minusDays(29)
    val days = (0..29).map { start.plusDays(it.toLong()) }
    val byDate = points.associateBy { it.date }
    val max = (points.maxOfOrNull { it.value } ?: average).coerceAtLeast(1.0)
    val labelSize = with(density) { 10.sp.toPx() }
    Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), shape = RoundedCornerShape(16.dp)) {
        Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(stringResource(R.string.perms_chart_title), style = MaterialTheme.typography.labelLarge)
            Canvas(Modifier.fillMaxWidth().height(180.dp)) {
                val left = 8.dp.toPx()
                val right = 8.dp.toPx()
                val top = 12.dp.toPx()
                val bottom = 28.dp.toPx()
                val w = (size.width - left - right).coerceAtLeast(1f)
                val h = (size.height - top - bottom).coerceAtLeast(1f)
                val baseY = top + h
                val barW = w / days.size
                if (average > 0) {
                    val ay = baseY - (average / max).toFloat() * h
                    drawLine(
                        color = gridColor,
                        start = Offset(left, ay),
                        end = Offset(size.width - right, ay),
                        strokeWidth = 2f,
                        pathEffect = PathEffect.dashPathEffect(floatArrayOf(10f, 8f)),
                    )
                }
                days.forEachIndexed { index, day ->
                    val value = byDate[day]?.value ?: 0.0
                    if (value <= 0) return@forEachIndexed
                    val bh = ((value / max) * h).toFloat().coerceAtLeast(2f)
                    val x = left + index * barW + barW * 0.15f
                    drawRoundRect(
                        color = if (day == today) todayColor else barColor,
                        topLeft = Offset(x, baseY - bh),
                        size = Size((barW * 0.7f).coerceAtLeast(1f), bh),
                        cornerRadius = CornerRadius(4.dp.toPx()),
                    )
                }
                val paint = android.graphics.Paint(android.graphics.Paint.ANTI_ALIAS_FLAG).apply {
                    textSize = labelSize
                    color = android.graphics.Color.argb(
                        (labelColor.alpha * 255).roundToInt(),
                        (labelColor.red * 255).roundToInt(),
                        (labelColor.green * 255).roundToInt(),
                        (labelColor.blue * 255).roundToInt(),
                    )
                    textAlign = android.graphics.Paint.Align.CENTER
                }
                listOf(0, 14, 29).forEach { idx ->
                    val day = days[idx]
                    val x = left + idx * barW + barW / 2f
                    drawContext.canvas.nativeCanvas.drawText(
                        day.format(DateTimeFormatter.ofPattern("MMM d", Locale.getDefault())),
                        x,
                        size.height - 6.dp.toPx(),
                        paint,
                    )
                }
            }
            if (unit.isNotBlank()) Text(unit, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}

private fun formatStat(type: HealthDataType, value: Double): String {
    if (value <= 0) return DashboardFormatter.NO_DATA
    return when (type) {
        HealthDataType.SLEEP, HealthDataType.EXERCISE -> "%.1f hrs".format(value / 60.0)
        HealthDataType.DISTANCE -> DashboardFormatter.formatDistanceKm(value)
        HealthDataType.STEPS -> DashboardFormatter.formatSteps(value.toLong())
        HealthDataType.ACTIVE_CALORIES, HealthDataType.TOTAL_CALORIES -> DashboardFormatter.formatCalories(value)
        HealthDataType.HEART_RATE, HealthDataType.RESTING_HEART_RATE -> DashboardFormatter.formatAverageBpm(value)
        HealthDataType.HEART_RATE_VARIABILITY -> DashboardFormatter.formatHeartRateVariabilityMs(value)
        else -> "%.1f".format(value)
    }
}

private fun openHealthConnect(context: android.content.Context) {
    try {
        val intent = Intent("androidx.health.connect.action.MANAGE_HEALTH_PERMISSIONS").apply {
            putExtra(Intent.EXTRA_PACKAGE_NAME, context.packageName)
            setPackage("com.google.android.apps.healthdata")
        }
        if (intent.resolveActivity(context.packageManager) != null) context.startActivity(intent)
        else context.startActivity(Intent("android.health.connect.action.HEALTH_HOME_SETTINGS"))
    } catch (_: Exception) {
        runCatching { context.startActivity(Intent("android.health.connect.action.HEALTH_HOME_SETTINGS")) }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PermissionsBottomSheet(grantedPermissionsSet: Set<String>, onDismiss: () -> Unit) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    ModalBottomSheet(onDismissRequest = onDismiss, sheetState = sheetState) {
        Column(Modifier.fillMaxWidth()) {
            HealthPermissionsScreen(
                grantedPermissionsSet = grantedPermissionsSet,
                onRequestPermissions = onDismiss,
                modifier = Modifier.fillMaxWidth().height(640.dp),
            )
            TextButton(onClick = onDismiss, modifier = Modifier.padding(Spacing.lg)) {
                Text(stringResource(R.string.action_close))
            }
        }
    }
}
