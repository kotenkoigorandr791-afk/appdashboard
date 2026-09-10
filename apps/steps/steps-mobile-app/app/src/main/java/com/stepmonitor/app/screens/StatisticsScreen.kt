package com.stepmonitor.app.screens
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.ArrowForward
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.nativeCanvas
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.stepmonitor.app.HealthConnectManager
import com.stepmonitor.app.HealthDataType
import com.stepmonitor.app.PreferencesManager
import com.stepmonitor.app.R
import com.stepmonitor.app.WebhookConfig
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONArray
import org.json.JSONObject
import java.time.LocalDate
import java.time.YearMonth
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.text.NumberFormat
import java.util.Locale
import kotlin.math.roundToInt
data class StatMetric(val type: HealthDataType, val value: String)
data class StatsState(val byDay: Map<LocalDate, List<StatMetric>> = emptyMap(), val steps: Map<LocalDate, Long> = emptyMap(), val error: String? = null)
@Composable
fun StatisticsScreen(modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val scope = rememberCoroutineScope()
    var dayOffset by remember { mutableIntStateOf(0) }
    var monthOffset by remember { mutableIntStateOf(0) }
    var state by remember { mutableStateOf(StatsState()) }
    var androidSteps by remember { mutableStateOf<Long?>(null) }
    var androidStepsError by remember { mutableStateOf<String?>(null) }
    var currentConfigUrl by remember { mutableStateOf(prefs.getWebhookConfigs().firstOrNull { it.isEnabled }?.url) }
    val config = prefs.getWebhookConfigs().firstOrNull { it.isEnabled }
    val selectedDate = LocalDate.now().plusDays(dayOffset.toLong())
    val chartMonth = YearMonth.now().plusMonths(monthOffset.toLong())
    suspend fun load(force: Boolean = false) {
        state = fetchYearStats(config, prefs.getEnabledDataTypes().ifEmpty { HealthDataType.values().toSet() }, prefs, force)
    }
    suspend fun loadAndroidSteps(force: Boolean = false) {
        androidStepsError = null
        if (!force) {
            prefs.getAndroidStepsCache(selectedDate.toString(), ANDROID_CACHE_MAX_AGE_MS)?.let {
                androidSteps = it
                return
            }
        }
        readAndroidSteps(context, selectedDate)
            .onSuccess { androidSteps = it }
            .onFailure {
                androidSteps = 0
                androidStepsError = it.message
            }
    }
    LaunchedEffect(Unit) {
        while (true) {
            val newUrl = prefs.getWebhookConfigs().firstOrNull { it.isEnabled }?.url
            if (newUrl != currentConfigUrl) {
                currentConfigUrl = newUrl
                state = StatsState()
                if (newUrl != null) load()
            }
            delay(2_000)
        }
    }
    LaunchedEffect(currentConfigUrl) {
        if (currentConfigUrl != null) load()
        else state = StatsState()
    }
    LaunchedEffect(selectedDate) {
        while (true) {
            loadAndroidSteps()
            delay(ANDROID_CACHE_MAX_AGE_MS)
        }
    }
    Column(modifier.fillMaxSize().padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
                    IconButton(onClick = { dayOffset-- }) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = null) }
                    Spacer(Modifier.width(8.dp))
                    Text(selectedDate.toString(), style = MaterialTheme.typography.titleLarge, modifier = Modifier.weight(1f))
                    IconButton(onClick = { if (dayOffset < 0) dayOffset++ }) { Icon(Icons.AutoMirrored.Filled.ArrowForward, contentDescription = null) }
                }
                Text("${stringResource(R.string.stats_steps)}: ${androidSteps ?: 0}", style = MaterialTheme.typography.headlineSmall)
                androidStepsError?.let { Text(it, color = MaterialTheme.colorScheme.error) }
            }
        }
        Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
                    IconButton(onClick = { if (monthOffset > -11) monthOffset-- }) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = null) }
                    Text(chartMonth.format(DateTimeFormatter.ofPattern("yyyy-MM")), style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
                    IconButton(onClick = { if (monthOffset < 0) monthOffset++ }) { Icon(Icons.AutoMirrored.Filled.ArrowForward, contentDescription = null) }
                }
                StepsChart(state.steps, chartMonth)
            }
        }
        Button(onClick = { scope.launch { load(true); loadAndroidSteps(true) } }, modifier = Modifier.fillMaxWidth()) {
            Text(stringResource(R.string.dashboard_refresh))
        }
        state.error?.let { Text(it, color = MaterialTheme.colorScheme.error) }
        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            items(state.byDay[selectedDate].orEmpty(), key = { it.type.name }) { metric ->
                Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                    Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                        Text(stringResource(metric.type.nameResId), style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
                        Text(metric.value, style = MaterialTheme.typography.headlineSmall)
                    }
                }
            }
        }
    }
}
@Composable
private fun StepsChart(steps: Map<LocalDate, Long>, month: YearMonth) {
    val color = MaterialTheme.colorScheme.primary
    val axisColor = MaterialTheme.colorScheme.onSurfaceVariant
    val gridColor = MaterialTheme.colorScheme.outlineVariant
    val days = remember(steps, month) {
        (1..month.lengthOfMonth()).map { day -> month.atDay(day) to (steps[month.atDay(day)] ?: 0L) }
    }
    val density = LocalDensity.current
    val labelTextSize = with(density) { 10.sp.toPx() }
    val leftPad = with(density) { 38.dp.toPx() }
    val rightPad = with(density) { 6.dp.toPx() }
    val bottomPad = with(density) { 20.dp.toPx() }
    val topPad = with(density) { 8.dp.toPx() }
    var tooltip by remember { mutableStateOf<BarTooltip?>(null) }
    var tapX by remember { mutableFloatStateOf(0f) }
    var canvasWidth by remember { mutableFloatStateOf(0f) }
    fun showTooltip(offset: Offset) {
        val chartWidth = canvasWidth - leftPad - rightPad
        if (chartWidth <= 0f) return
        val barWidth = chartWidth / days.size.toFloat()
        val index = ((offset.x - leftPad) / barWidth).toInt().coerceIn(0, days.lastIndex)
        val (date, count) = days[index]
        if (count > 0L) {
            tapX = offset.x
            tooltip = BarTooltip(date, count)
        }
    }
    LaunchedEffect(tooltip) {
        if (tooltip != null) {
            delay(TOOLTIP_TIMEOUT_MS)
            tooltip = null
        }
    }
    Box(
        Modifier
            .fillMaxWidth()
            .onSizeChanged { canvasWidth = it.width.toFloat() }
    ) {
        Canvas(
            Modifier
                .fillMaxWidth()
                .height(170.dp)
                .pointerInput(days) {
                    detectTapGestures(
                        onTap = { showTooltip(it) },
                        onLongPress = { showTooltip(it) }
                    )
                }
        ) {
            val max = (days.maxOfOrNull { it.second } ?: 1L).coerceAtLeast(1L).toFloat()
            val chartWidth = (size.width - leftPad - rightPad).coerceAtLeast(1f)
            val chartHeight = (size.height - topPad - bottomPad).coerceAtLeast(1f)
            val baseY = topPad + chartHeight
            val barWidth = chartWidth / days.size.toFloat()
            val paint = android.graphics.Paint(android.graphics.Paint.ANTI_ALIAS_FLAG).apply {
                textSize = labelTextSize
                setColor(axisColor.toArgb())
            }
            listOf(0f, 0.5f, 1f).forEach { frac ->
                val y = baseY - chartHeight * frac
                drawLine(gridColor, Offset(leftPad, y), Offset(size.width - rightPad, y), strokeWidth = 1f)
                paint.textAlign = android.graphics.Paint.Align.RIGHT
                drawContext.canvas.nativeCanvas.drawText(
                    (max * frac).roundToInt().toString(),
                    leftPad - 6f,
                    y + labelTextSize / 3f,
                    paint
                )
            }
            days.forEachIndexed { index, (_, count) ->
                val h = if (max > 0f) (count / max) * chartHeight else 0f
                val x = leftPad + index * barWidth
                drawRect(
                    color = if (count > 0) color else Color.LightGray.copy(alpha = 0.25f),
                    topLeft = Offset(x, baseY - h),
                    size = Size((barWidth - 2f).coerceAtLeast(1f), h.coerceAtLeast(2f))
                )
            }
            paint.textAlign = android.graphics.Paint.Align.CENTER
            val xLabels = (sequenceOf(1) + (5..month.lengthOfMonth() step 5)).distinct().toList()
            xLabels.forEach { day ->
                val x = leftPad + (day - 1) * barWidth + barWidth / 2f
                drawContext.canvas.nativeCanvas.drawText(
                    day.toString(),
                    x,
                    size.height - bottomPad + labelTextSize + 4f,
                    paint
                )
            }
        }
        tooltip?.let { tip ->
            val halfWidth = with(density) { 66.dp.toPx() }
            val clampedX = (tapX - halfWidth).coerceIn(0f, (canvasWidth - halfWidth * 2f).coerceAtLeast(0f))
            Surface(
                modifier = Modifier
                    .align(Alignment.TopStart)
                    .offset { IntOffset(clampedX.roundToInt(), 0) },
                shape = RoundedCornerShape(6.dp),
                color = MaterialTheme.colorScheme.inverseSurface,
                shadowElevation = 4.dp
            ) {
                Text(
                    text = "${formatTooltipDate(tip.date)}: ${NumberFormat.getIntegerInstance(Locale.getDefault()).format(tip.count)} ${stringResource(R.string.stats_tooltip_steps)}",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.inverseOnSurface,
                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                )
            }
        }
    }
}
private data class BarTooltip(val date: LocalDate, val count: Long)
private fun formatTooltipDate(date: LocalDate): String =
    DateTimeFormatter.ofPattern("d MMMM", Locale.getDefault()).format(date)
private const val TOOLTIP_TIMEOUT_MS = 2_000L
private suspend fun readAndroidSteps(context: android.content.Context, date: LocalDate): Result<Long> = withContext(Dispatchers.IO) {
    runCatching {
        val zone = ZoneId.systemDefault()
        val start = date.atStartOfDay(zone).toInstant()
        val end = date.plusDays(1).atStartOfDay(zone).toInstant()
        HealthConnectManager(context)
            .readHealthData(setOf(HealthDataType.STEPS), emptyMap(), start = start, end = end)
            .getOrThrow()
            .steps
            .sumOf { it.count }
            .also { PreferencesManager(context).setAndroidStepsCache(date.toString(), it) }
    }
}
private suspend fun fetchYearStats(config: WebhookConfig?, enabled: Set<HealthDataType>, prefs: PreferencesManager, force: Boolean = false): StatsState = withContext(Dispatchers.IO) {
    if (config == null) return@withContext StatsState(error = "Not connected")
    if (!force) {
        prefs.getStatsCache(STATS_CACHE_MAX_AGE_MS)?.let {
            return@withContext runCatching { parseServerMetrics(JSONObject(it).optJSONArray("metrics") ?: JSONArray(), enabled) }.getOrNull() ?: StatsState()
        }
    }
    runCatching {
        val to = LocalDate.now()
        val from = to.minusDays(365)
        val url = "${config.url}${if (config.url.contains("?")) "&" else "?"}from=$from&to=$to&metrics=1"
        val builder = Request.Builder().url(url)
        config.headers.forEach { (key, value) -> builder.addHeader(key, value) }
        OkHttpClient().newCall(builder.build()).execute().use { response ->
            if (!response.isSuccessful) error("HTTP ${response.code}")
            val body = response.body?.string().orEmpty()
            prefs.setStatsCache(body)
            parseServerMetrics(JSONObject(body).optJSONArray("metrics") ?: JSONArray(), enabled)
        }
    }.getOrElse { StatsState(error = it.message) }
}
private fun parseServerMetrics(items: JSONArray, enabled: Set<HealthDataType>): StatsState {
    val byDay = mutableMapOf<LocalDate, List<StatMetric>>()
    val steps = mutableMapOf<LocalDate, Long>()
    for (index in 0 until items.length()) {
        val row = items.optJSONObject(index) ?: continue
        val date = runCatching { LocalDate.parse(row.optString("date")) }.getOrNull() ?: continue
        val day = row.optJSONObject("metrics") ?: continue
        val rows = mutableListOf<StatMetric>()
        fun metric(name: String): JSONObject? = day.optJSONObject(name)
        fun total(name: String): Double {
            val obj = metric(name) ?: return 0.0
            val total = obj.optDouble("total", Double.NaN)
            if (!total.isNaN()) return total
            val meters = obj.optDouble("total_meters", Double.NaN)
            return if (meters.isNaN()) 0.0 else meters
        }
        val stepTotal = total("steps").toLong()
        if (HealthDataType.STEPS in enabled && stepTotal > 0) {
            steps[date] = stepTotal
            rows += StatMetric(HealthDataType.STEPS, stepTotal.toString())
        }
        val candidates = listOf(
            HealthDataType.DISTANCE to ("distance" to "%.0f m".format(total("distance"))),
            HealthDataType.ACTIVE_CALORIES to ("active_calories" to "%.0f kcal".format(total("active_calories"))),
            HealthDataType.TOTAL_CALORIES to ("total_calories" to "%.0f kcal".format(total("total_calories"))),
            HealthDataType.SLEEP to ("sleep" to "%.1f h".format(total("sleep") / 3600.0)),
            HealthDataType.HEART_RATE to ("heart_rate" to "%.0f bpm".format(metric("heart_rate")?.optDouble("avg") ?: 0.0)),
            HealthDataType.WEIGHT to ("weight" to "%.1f kg".format(metric("weight")?.optDouble("last") ?: 0.0)),
            HealthDataType.BLOOD_PRESSURE to ("blood_pressure" to "%.0f mmHg".format(metric("blood_pressure")?.optDouble("last") ?: 0.0)),
            HealthDataType.OXYGEN_SATURATION to ("oxygen_spo2" to "%.1f %%".format(metric("oxygen_spo2")?.optDouble("avg") ?: 0.0))
        )
        candidates.forEach { (type, pair) ->
            val obj = metric(pair.first)
            if (type in enabled && obj != null && obj.optInt("samples", 0) > 0) rows += StatMetric(type, pair.second)
        }
        byDay[date] = rows
    }
    return StatsState(byDay = byDay, steps = steps)
}
private const val STATS_CACHE_MAX_AGE_MS = 60L * 60 * 1000
private const val ANDROID_CACHE_MAX_AGE_MS = 30L * 1000
