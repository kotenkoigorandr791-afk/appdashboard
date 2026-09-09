package com.stepmonitor.app.screens
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.ArrowForward
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
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
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.stepmonitor.app.HealthConnectManager
import com.stepmonitor.app.HealthDataType
import com.stepmonitor.app.PreferencesManager
import com.stepmonitor.app.R
import com.stepmonitor.app.WebhookConfig
import kotlinx.coroutines.Dispatchers
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
    val config = prefs.getWebhookConfigs().firstOrNull { it.isEnabled }
    val selectedDate = LocalDate.now().plusDays(dayOffset.toLong())
    val chartMonth = YearMonth.now().plusMonths(monthOffset.toLong())
    suspend fun load() {
        state = fetchYearStats(config, prefs.getEnabledDataTypes().ifEmpty { HealthDataType.values().toSet() })
    }
    suspend fun loadAndroidSteps() {
        androidStepsError = null
        readAndroidSteps(context, selectedDate)
            .onSuccess { androidSteps = it }
            .onFailure {
                androidSteps = 0
                androidStepsError = it.message
            }
    }
    LaunchedEffect(config?.url) { load() }
    LaunchedEffect(selectedDate) { loadAndroidSteps() }
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
        Button(onClick = { scope.launch { load(); loadAndroidSteps() } }, modifier = Modifier.fillMaxWidth()) {
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
    val days = remember(steps, month) {
        (1..month.lengthOfMonth()).map { day -> month.atDay(day) to (steps[month.atDay(day)] ?: 0L) }
    }
    Canvas(Modifier.fillMaxWidth().height(170.dp)) {
        val max = (days.maxOfOrNull { it.second } ?: 1L).coerceAtLeast(1L).toFloat()
        val barWidth = size.width / 30f
        days.take(30).forEachIndexed { index, item ->
            val h = (item.second / max) * size.height
            drawRect(color = if (item.second > 0) color else Color.LightGray.copy(alpha = 0.25f), topLeft = Offset(index * barWidth, size.height - h), size = Size((barWidth - 2f).coerceAtLeast(1f), h.coerceAtLeast(2f)))
        }
    }
}
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
    }
}
private suspend fun fetchYearStats(config: WebhookConfig?, enabled: Set<HealthDataType>): StatsState = withContext(Dispatchers.IO) {
    if (config == null) return@withContext StatsState(error = "Not connected")
    runCatching {
        val to = LocalDate.now()
        val from = to.minusDays(365)
        val url = "${config.url}${if (config.url.contains("?")) "&" else "?"}from=$from&to=$to&metrics=1"
        val builder = Request.Builder().url(url)
        config.headers.forEach { (key, value) -> builder.addHeader(key, value) }
        OkHttpClient().newCall(builder.build()).execute().use { response ->
            if (!response.isSuccessful) error("HTTP ${response.code}")
            parseServerMetrics(JSONObject(response.body?.string().orEmpty()).optJSONArray("metrics") ?: JSONArray(), enabled)
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
