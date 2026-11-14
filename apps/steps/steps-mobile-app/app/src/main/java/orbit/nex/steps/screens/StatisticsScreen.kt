package orbit.nex.steps.screens
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.aspectRatio
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
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
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
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.ExperimentalMaterial3Api
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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.lerp
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
import orbit.nex.steps.HealthConnectManager
import orbit.nex.steps.HealthDataType
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.R
import orbit.nex.steps.WebhookConfig
import orbit.nex.steps.ui.theme.AppCornerRadius
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
@OptIn(ExperimentalMaterial3Api::class)
fun StatisticsScreen(modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val scope = rememberCoroutineScope()
    var monthOffset by remember { mutableIntStateOf(0) }
    var goalSteps by remember { mutableIntStateOf(0) }
    var state by remember { mutableStateOf(StatsState()) }
    var localMonthSteps by remember { mutableStateOf<Map<LocalDate, Long>>(emptyMap()) }
    var androidSteps by remember { mutableStateOf<Long?>(null) }
    var androidStepsError by remember { mutableStateOf<String?>(null) }
    var selectedStatsDate by remember { mutableStateOf<LocalDate?>(null) }
    var currentConfigUrl by remember { mutableStateOf(prefs.getWebhookConfigs().firstOrNull { it.isEnabled }?.url) }
    val config = prefs.getWebhookConfigs().firstOrNull { it.isEnabled }
    val connected = config != null
    val selectedDate = LocalDate.now()
    val chartMonth = YearMonth.now().plusMonths(monthOffset.toLong())
    suspend fun load(force: Boolean = false) {
        state = config?.let { fetchYearStats(it, prefs.getEnabledDataTypes().ifEmpty { HealthDataType.values().toSet() }, prefs, force, context) } ?: StatsState()
    }
    suspend fun loadLocalMonth() {
        if (connected) {
            localMonthSteps = emptyMap()
            return
        }
        val cached = cachedMonthSteps(chartMonth, prefs)
        val fresh = readAndroidMonthSteps(context, chartMonth)
        localMonthSteps = cached + fresh
    }
    suspend fun loadAndroidSteps(force: Boolean = false) {
        if (connected) {
            androidSteps = null
            androidStepsError = null
            return
        }
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
                load()
            }
            delay(2_000)
        }
    }
    LaunchedEffect(currentConfigUrl) {
        load()
    }
    // The goal lives in the server-side settings, same value the web chart draws.
    LaunchedEffect(currentConfigUrl) {
        goalSteps = config?.let { fetchStepGoal(it, prefs) } ?: 0
    }
    LaunchedEffect(chartMonth, currentConfigUrl) {
        loadLocalMonth()
    }
    LaunchedEffect(selectedDate, currentConfigUrl) {
        while (true) {
            loadAndroidSteps()
            delay(ANDROID_CACHE_MAX_AGE_MS)
        }
    }
    val monthSteps = if (connected) state.steps else localMonthSteps
    val daySteps = if (connected) state.steps[selectedDate] ?: 0L else androidSteps ?: 0L
    val dayStepsError = if (connected) null else androidStepsError
    Column(
        modifier.fillMaxSize().padding(horizontal = 10.dp).verticalScroll(rememberScrollState()),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        // Gap under the top bar that scrolls away with the content.
        Spacer(Modifier.height(12.dp))
        // Today's summary: gradient card with just the label and the count.
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(AppCornerRadius))
                .background(
                    Brush.linearGradient(
                        listOf(
                            MaterialTheme.colorScheme.primary.copy(alpha = 0.34f),
                            MaterialTheme.colorScheme.surfaceVariant,
                            MaterialTheme.colorScheme.tertiary.copy(alpha = 0.30f),
                        )
                    )
                )
        ) {
            Column(Modifier.padding(horizontal = 18.dp, vertical = 16.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                // Label and value on one line, e.g. "Today  8 000 steps".
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    Text(
                        stringResource(R.string.stats_today),
                        style = MaterialTheme.typography.titleMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Text(
                        NumberFormat.getIntegerInstance(Locale.getDefault()).format(daySteps),
                        style = MaterialTheme.typography.headlineMedium,
                    )
                    Text(
                        stringResource(R.string.stats_steps),
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                dayStepsError?.let { Text(it, color = MaterialTheme.colorScheme.error) }
            }
        }
        Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
                    IconButton(onClick = { if (monthOffset > -11) monthOffset-- }) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = null) }
                    Text(chartMonth.format(DateTimeFormatter.ofPattern("yyyy-MM")), style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
                    IconButton(onClick = { if (monthOffset < 0) monthOffset++ }) { Icon(Icons.AutoMirrored.Filled.ArrowForward, contentDescription = null) }
                }
                StepsChart(monthSteps, chartMonth, goalSteps) { selectedStatsDate = it }
            }
        }
        Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text(stringResource(R.string.stats_heatmap_title), style = MaterialTheme.typography.titleMedium)
                StepsHeatmap(monthSteps, chartMonth, goalSteps) { selectedStatsDate = it }
            }
        }
        if (!connected && localMonthSteps.isNotEmpty()) {
            Text(stringResource(R.string.stats_local_data), color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall)
        }
        Button(onClick = { scope.launch { load(true); loadAndroidSteps(true); loadLocalMonth() } }, modifier = Modifier.fillMaxWidth()) {
            Text(stringResource(R.string.dashboard_refresh))
        }
        state.error?.let { Text(it, color = MaterialTheme.colorScheme.error) }
        if (connected) state.byDay[selectedDate].orEmpty().forEach { metric ->
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                    Text(stringResource(metric.type.nameResId), style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
                    Text(metric.value, style = MaterialTheme.typography.headlineSmall)
                }
            }
        }
    }
    selectedStatsDate?.let { date ->
        ModalBottomSheet(onDismissRequest = { selectedStatsDate = null }) {
            Column(Modifier.padding(24.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text(formatTooltipDate(date), style = MaterialTheme.typography.titleLarge)
                Text("${NumberFormat.getIntegerInstance(Locale.getDefault()).format(monthSteps[date] ?: 0L)} ${stringResource(R.string.stats_steps)}", style = MaterialTheme.typography.headlineSmall)
                state.byDay[date].orEmpty().forEach { metric ->
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text(stringResource(metric.type.nameResId))
                        Text(metric.value)
                    }
                }
            }
        }
    }
}
@Composable
private fun StepsChart(steps: Map<LocalDate, Long>, month: YearMonth, goal: Int = 0, onDayClick: (LocalDate) -> Unit) {
    val barTop = MaterialTheme.colorScheme.primary
    val barBottom = MaterialTheme.colorScheme.tertiary
    val axisColor = MaterialTheme.colorScheme.onSurfaceVariant
    val gridColor = MaterialTheme.colorScheme.outlineVariant
    val goalColor = Color(0xFFE53935)
    val goalLabelBg = MaterialTheme.colorScheme.surface
    val goalLabel = stringResource(R.string.stats_goal, NumberFormat.getIntegerInstance(Locale.getDefault()).format(goal))
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
            onDayClick(date)
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
            // Keep the goal visible with some headroom, like the web chart does.
            val dataMax = days.maxOfOrNull { it.second } ?: 0L
            val axisMax = if (goal > 0) maxOf(dataMax, (goal * 1.2).toLong()) else dataMax
            val max = axisMax.coerceAtLeast(1L).toFloat()
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
            val barRadius = CornerRadius((barWidth * 0.28f).coerceIn(1.5f, 5.dp.toPx()))
            days.forEachIndexed { index, (_, count) ->
                val gap = (barWidth * 0.10f).coerceAtLeast(2f)
                val h = if (max > 0f) (count / max) * chartHeight else 0f
                val x = leftPad + index * barWidth + gap / 2f
                val barWidthPx = (barWidth - gap).coerceAtLeast(1f)
                if (count > 0L) {
                    // Colourful like the Spends charts: a vertical gradient that
                    // shifts from the accent to the secondary accent over the bar.
                    drawRoundRect(
                        brush = Brush.verticalGradient(
                            listOf(barTop, barBottom.copy(alpha = 0.75f)),
                            startY = baseY - h,
                            endY = baseY,
                        ),
                        topLeft = Offset(x, baseY - h),
                        size = Size(barWidthPx, h.coerceAtLeast(2f)),
                        cornerRadius = barRadius,
                    )
                } else {
                    drawRoundRect(
                        color = gridColor.copy(alpha = 0.45f),
                        topLeft = Offset(x, baseY - 2f),
                        size = Size(barWidthPx, 2f),
                        cornerRadius = barRadius,
                    )
                }
            }
            if (goal > 0) {
                val goalY = baseY - chartHeight * (goal.toFloat() / max)
                drawLine(
                    color = goalColor,
                    start = Offset(leftPad, goalY),
                    end = Offset(size.width - rightPad, goalY),
                    strokeWidth = 2f,
                    pathEffect = PathEffect.dashPathEffect(floatArrayOf(12f, 8f))
                )
                // Label sits on a rounded plate so it stays legible over bars.
                paint.textAlign = android.graphics.Paint.Align.RIGHT
                paint.color = goalColor.toArgb()
                val textWidth = paint.measureText(goalLabel)
                val padH = 4.dp.toPx()
                val padV = 2.dp.toPx()
                val baseline = (goalY - 6f).coerceAtLeast(labelTextSize + padV)
                val right = size.width - rightPad
                drawRoundRect(
                    color = goalLabelBg,
                    topLeft = Offset(right - textWidth - padH * 2f, baseline - labelTextSize - padV),
                    size = Size(textWidth + padH * 2f, labelTextSize + padV * 2f),
                    cornerRadius = CornerRadius(4.dp.toPx(), 4.dp.toPx())
                )
                drawContext.canvas.nativeCanvas.drawText(
                    goalLabel,
                    right - padH,
                    baseline,
                    paint
                )
                paint.color = axisColor.toArgb()
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
@Composable
private fun StepsHeatmap(steps: Map<LocalDate, Long>, month: YearMonth, goal: Int, onDayClick: (LocalDate) -> Unit) {
    // Gold marks the days the daily step goal was reached.
    val goalGold = Color(0xFFD4A017)
    val days = remember(steps, month) { (1..month.lengthOfMonth()).map { month.atDay(it) } }
    val max = remember(days, steps) { (days.maxOfOrNull { steps[it] ?: 0L } ?: 0L).coerceAtLeast(1L) }
    val base = MaterialTheme.colorScheme.primary
    val warm = MaterialTheme.colorScheme.tertiary
    val empty = MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.25f)
    var picked by remember { mutableStateOf<LocalDate?>(null) }
    // Weeks start on Monday; leading blanks keep the weekday columns aligned.
    val leading = month.atDay(1).dayOfWeek.value - 1
    val cells: List<LocalDate?> = List(leading) { null } + days
    val rows = cells.chunked(7)
    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
        rows.forEach { week ->
            Row(horizontalArrangement = Arrangement.spacedBy(4.dp), modifier = Modifier.fillMaxWidth()) {
                week.forEach { date ->
                    if (date == null) {
                        Spacer(Modifier.weight(1f).aspectRatio(1f))
                    } else {
                        val count = steps[date] ?: 0L
                        val intensity = (count.toFloat() / max.toFloat()).coerceIn(0f, 1f)
                        val color = if (count <= 0L) empty else lerp(base, warm, intensity).copy(alpha = 0.35f + 0.65f * intensity)
                        val goalReached = goal > 0 && count >= goal
                        Box(
                            modifier = Modifier
                                .weight(1f)
                                .aspectRatio(1f)
                                .clip(RoundedCornerShape(4.dp))
                                .background(color)
                                .then(
                                    if (goalReached) {
                                        Modifier.border(1.5.dp, goalGold, RoundedCornerShape(4.dp))
                                    } else Modifier
                                )
                                .clickable { picked = if (picked == date) null else date; onDayClick(date) },
                            contentAlignment = Alignment.Center,
                        ) {
                            // Day of month so the grid reads as a calendar.
                            Text(
                                date.dayOfMonth.toString(),
                                style = MaterialTheme.typography.labelSmall,
                                color = when {
                                    goalReached -> goalGold
                                    intensity > 0.55f -> MaterialTheme.colorScheme.onPrimary
                                    else -> MaterialTheme.colorScheme.onSurfaceVariant
                                },
                            )
                        }
                    }
                }
                // Pad the final short week so its cells keep the weekday width.
                repeat(7 - week.size) {
                    Spacer(Modifier.weight(1f).aspectRatio(1f))
                }
            }
        }
        val numberFormat = NumberFormat.getIntegerInstance(Locale.getDefault())
        val caption = picked?.let { date ->
            "${formatTooltipDate(date)}: ${numberFormat.format(steps[date] ?: 0L)} ${stringResource(R.string.stats_tooltip_steps)}"
        } ?: "${stringResource(R.string.stats_month_total)}: ${numberFormat.format(days.sumOf { steps[it] ?: 0L })}"
        Text(
            caption,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
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
private fun cachedMonthSteps(month: YearMonth, prefs: PreferencesManager): Map<LocalDate, Long> {
    val result = mutableMapOf<LocalDate, Long>()
    for (day in 1..month.lengthOfMonth()) {
        val date = month.atDay(day)
        prefs.getAndroidStepsCache(date.toString(), Long.MAX_VALUE)?.let { result[date] = it }
    }
    return result
}
private suspend fun readAndroidMonthSteps(context: android.content.Context, month: YearMonth): Map<LocalDate, Long> = withContext(Dispatchers.IO) {
    val prefs = PreferencesManager(context)
    runCatching {
        val manager = HealthConnectManager(context)
        if (HealthConnectManager.grantedDataTypes(manager.getGrantedPermissions()).none { it == HealthDataType.STEPS }) {
            return@runCatching cachedMonthSteps(month, prefs)
        }
        val zone = ZoneId.systemDefault()
        manager.readHealthData(
            enabledTypes = setOf(HealthDataType.STEPS),
            lastSyncTimestamps = emptyMap(),
            start = month.atDay(1).atStartOfDay(zone).toInstant(),
            end = month.plusMonths(1).atDay(1).atStartOfDay(zone).toInstant()
        )
            .getOrThrow()
            .steps
            .groupBy { it.startTime.atZone(zone).toLocalDate() }
            .mapValues { (_, records) -> records.sumOf { it.count } }
            .also { map -> map.forEach { (date, count) -> prefs.setAndroidStepsCache(date.toString(), count) } }
    }.getOrElse { cachedMonthSteps(month, prefs) }
}
private suspend fun fetchYearStats(config: WebhookConfig, enabled: Set<HealthDataType>, prefs: PreferencesManager, force: Boolean = false, context: android.content.Context): StatsState = withContext(Dispatchers.IO) {
    if (!force) {
        prefs.getStatsCache(STATS_CACHE_MAX_AGE_MS)?.let {
            return@withContext parseStatsCache(it, enabled, context) ?: StatsState()
        }
    }
    runCatching {
        val to = LocalDate.now()
        val from = to.minusDays(365)
        val baseUrl = config.url.trimEnd('/')
        // metrics=1 gives per-day metric details (limited to days with metric files),
        // the plain endpoint returns the full steps history from the server DB.
        val metricsUrl = "$baseUrl${if (baseUrl.contains("?")) "&" else "?"}from=$from&to=$to&metrics=1"
        val stepsUrl = "$baseUrl${if (baseUrl.contains("?")) "&" else "?"}from=$from&to=$to"
        val client = OkHttpClient()
        fun httpGet(url: String): String {
            val builder = Request.Builder().url(url)
            config.headers.forEach { (key, value) -> builder.addHeader(key, value) }
            // The API binds a pairing token to one device and answers 400 without it.
            if (config.headers.keys.none { it.equals("X-Device-Id", ignoreCase = true) }) {
                builder.addHeader("X-Device-Id", prefs.getDeviceId())
            }
            return client.newCall(builder.build()).execute().use { response ->
                if (!response.isSuccessful) error("HTTP ${response.code}")
                response.body?.string().orEmpty()
            }
        }
        val metricsBody = httpGet(metricsUrl)
        val stepsBody = httpGet(stepsUrl)
        val combined = JSONObject()
            .put("metrics", JSONObject(metricsBody).optJSONArray("metrics"))
            .put("steps", JSONObject(stepsBody).optJSONArray("steps"))
        prefs.setStatsCache(combined.toString())
        parseServerMetrics(combined.optJSONArray("metrics") ?: JSONArray(), enabled, combined.optJSONArray("steps") ?: JSONArray(), context)
    }.getOrElse { failure ->
        parseStatsCache(prefs.getStatsCache(Long.MAX_VALUE), enabled, context)?.copy(error = failure.message)
            ?: StatsState(error = failure.message)
    }
}
private fun parseStatsCache(json: String?, enabled: Set<HealthDataType>, context: android.content.Context): StatsState? {
    val cached = json ?: return null
    return runCatching {
        parseServerMetrics(
            JSONObject(cached).optJSONArray("metrics") ?: JSONArray(),
            enabled,
            JSONObject(cached).optJSONArray("steps") ?: JSONArray(),
            context
        )
    }.getOrNull()
}
private fun parseServerMetrics(items: JSONArray, enabled: Set<HealthDataType>, serverSteps: JSONArray = JSONArray(), context: android.content.Context): StatsState {
    val byDay = mutableMapOf<LocalDate, List<StatMetric>>()
    val steps = mutableMapOf<LocalDate, Long>()
    for (index in 0 until serverSteps.length()) {
        val row = serverSteps.optJSONObject(index) ?: continue
        val date = runCatching { LocalDate.parse(row.optString("date")) }.getOrNull() ?: continue
        val s = row.optLong("steps", 0L)
        if (HealthDataType.STEPS in enabled && s > 0) steps[date] = s
    }
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
        if (HealthDataType.STEPS in enabled && stepTotal > 0 && date !in steps) {
            steps[date] = stepTotal
        }
        // Units come from resources so every locale can localise them.
        fun unit(resId: Int, value: String): String = context.getString(resId, value)
        val candidates = listOf(
            HealthDataType.DISTANCE to ("distance" to unit(R.string.unit_meters, "%.0f".format(total("distance")))),
            HealthDataType.ACTIVE_CALORIES to ("active_calories" to unit(R.string.unit_kcal, "%.0f".format(total("active_calories")))),
            HealthDataType.TOTAL_CALORIES to ("total_calories" to unit(R.string.unit_kcal, "%.0f".format(total("total_calories")))),
            HealthDataType.SLEEP to ("sleep" to unit(R.string.unit_hours, "%.1f".format(total("sleep") / 3600.0))),
            HealthDataType.HEART_RATE to ("heart_rate" to unit(R.string.unit_bpm, "%.0f".format(metric("heart_rate")?.optDouble("avg") ?: 0.0))),
            HealthDataType.WEIGHT to ("weight" to unit(R.string.unit_kg, "%.1f".format(metric("weight")?.optDouble("last") ?: 0.0))),
            HealthDataType.BLOOD_PRESSURE to ("blood_pressure" to unit(R.string.unit_mmhg, "%.0f".format(metric("blood_pressure")?.optDouble("last") ?: 0.0))),
            HealthDataType.OXYGEN_SATURATION to ("oxygen_spo2" to unit(R.string.unit_percent, "%.1f".format(metric("oxygen_spo2")?.optDouble("avg") ?: 0.0)))
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

/** Daily step goal from the server settings, or 0 when it is unavailable. */
private suspend fun fetchStepGoal(config: WebhookConfig, prefs: PreferencesManager): Int = withContext(Dispatchers.IO) {
    runCatching {
        val url = config.url.trimEnd('/') + "/settings?settings=1"
        OkHttpClient().newCall(stepsRequest(config, prefs, url).build()).execute().use { response ->
            if (!response.isSuccessful) return@use 0
            val settings = JSONObject(response.body?.string().orEmpty()).optJSONObject("settings") ?: JSONObject()
            settings.optString("goal_steps").toIntOrNull() ?: 0
        }
    }.getOrDefault(0)
}
