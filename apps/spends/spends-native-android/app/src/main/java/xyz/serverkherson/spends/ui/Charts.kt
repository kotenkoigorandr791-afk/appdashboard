package xyz.serverkherson.spends.ui

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.items
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.ChevronLeft
import androidx.compose.material.icons.outlined.ChevronRight
import androidx.compose.material.icons.outlined.ShowChart
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import xyz.serverkherson.spends.AppState
import xyz.serverkherson.spends.R
import java.time.LocalDate
import java.time.YearMonth
import kotlin.math.abs
import kotlin.math.max

private val StorePalette = listOf(
    Color(0xFFFF6384), Color(0xFF36A2EB), Color(0xFFFFCE56), Color(0xFF4BC0C0),
    Color(0xFF9966FF), Color(0xFFFF9F40), Color(0xFF067F00), Color(0xFFC9CBCF),
)

private val ChartCurrencies = listOf("USD", "EUR", "PLN", "UAH", "BYN")

@OptIn(ExperimentalLayoutApi::class)
@Composable
fun YearExpenseChart(state: AppState) {
    val years = state.snapshot.years.ifEmpty { listOf(LocalDate.now().year) }.sorted()
    var year by remember(years) { mutableIntStateOf(years.last()) }
    var mode by remember { mutableStateOf("total") }
    var legendSeries by remember { mutableStateOf<List<ChartSeries>?>(null) }
    var currency by remember(state.snapshot.currency) { mutableStateOf(state.snapshot.currency) }
    val months = remember { (1..12).map { "%02d".format(it) } }
    val visible = state.snapshot.stores.filter { it.visible }
    val conv = conversion(state, currency)
    val monthlyStores = remember(state.snapshot.expenses, year) {
        months.associateWith { m ->
            visible.associate { s ->
                s.name to state.snapshot.expenses.filter { it.date.startsWith("$year-$m") && it.store == s.name }.sumOf { it.amount }
            }
        }
    }
    val monthlyIncome = remember(state.snapshot.incomes, year) {
        months.associateWith { m -> state.snapshot.incomes.filter { it.date.startsWith("$year-$m") }.sumOf { it.amount } }
    }
    val shownMonths = months.filter { m -> (monthlyStores[m]?.values?.sum() ?: 0.0) != 0.0 || (monthlyIncome[m] ?: 0.0) != 0.0 }.ifEmpty { months.takeLast(1) }
    val expensesLabel = stringResource(R.string.expenses)
    val incomesLabel = stringResource(R.string.incomes)
    val differenceLabel = stringResource(R.string.difference)
    val expenseColor = SpendsColors.expense()
    val incomeColor = SpendsColors.income()
    val categoryOf = remember(state.snapshot.stores) {
        state.snapshot.stores.associate { it.name to it.category.ifBlank { "other" } }
    }
    val categoryKeys = remember(state.snapshot.expenses, categoryOf) {
        state.snapshot.expenses.groupBy { categoryOf[it.store] ?: "other" }
            .mapValues { entry -> entry.value.sumOf { it.amount } }
            .toList().sortedByDescending { it.second }.map { it.first }
    }
    val categoryLabels = categoryKeys.associateWith { categoryLabel(it) }
    fun buildSeries(mode: String): List<ChartSeries> = buildList {
        when (mode) {
            "total" -> {
                add(ChartSeries(expensesLabel, expenseColor, shownMonths.map { (monthlyStores[it]?.values?.sum() ?: 0.0) * conv }))
                if (state.snapshot.flags.showIncomes) add(ChartSeries(incomesLabel, incomeColor, shownMonths.map { (monthlyIncome[it] ?: 0.0) * conv }))
                if (state.snapshot.flags.showTrend) {
                    val exp = shownMonths.map { (monthlyStores[it]?.values?.sum() ?: 0.0) * conv }
                    val inc = shownMonths.map { (monthlyIncome[it] ?: 0.0) * conv }
                    add(ChartSeries(differenceLabel, Color(0xFFF59E0B), exp.indices.map { inc[it] - exp[it] }))
                }
            }
            "categories" -> categoryKeys.take(8).forEachIndexed { i, category ->
                val color = StorePalette[i % StorePalette.size]
                add(
                    ChartSeries(
                        categoryLabels[category] ?: category,
                        color,
                        shownMonths.map { month ->
                            val prefix = "$year-$month"
                            state.snapshot.expenses
                                .filter { it.date.startsWith(prefix) && (categoryOf[it.store] ?: "other") == category }
                                .sumOf { it.amount } * conv
                        },
                    ),
                )
            }
            else -> visible.take(8).forEachIndexed { i, store ->
                add(ChartSeries(store.name, StorePalette[i % StorePalette.size], shownMonths.map { (monthlyStores[it]?.get(store.name) ?: 0.0) * conv }))
            }
        }
    }
    // Exactly one row of chips: the tracked currencies, with the account currency swapped in.
    val currencies = remember(state.snapshot.currency) {
        val base = ChartCurrencies.toMutableList()
        val account = state.snapshot.currency.uppercase()
        if (account.isNotBlank() && account !in base) base[base.lastIndex] = account
        base.distinct()
    }
    Column(Modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Outlined.ShowChart, null, tint = MaterialTheme.colorScheme.primary)
            Spacer(Modifier.width(8.dp))
            Text(stringResource(R.string.year_chart), style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
            IconButton({ if (years.any { it == year - 1 }) year-- }, enabled = years.any { it == year - 1 }) {
                Icon(Icons.Outlined.ChevronLeft, stringResource(R.string.prev_year))
            }
            Text("$year", style = MaterialTheme.typography.titleMedium)
            IconButton({ if (years.any { it == year + 1 }) year++ }, enabled = years.any { it == year + 1 }) {
                Icon(Icons.Outlined.ChevronRight, stringResource(R.string.next_year))
            }
        }
        // Compact chips share the width equally so they always stay on one line.
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            currencies.forEach { code ->
                val active = currency == code
                Box(
                    Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(12.dp))
                        .background(if (active) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.surfaceVariant)
                        .border(
                            1.dp,
                            if (active) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.outlineVariant,
                            RoundedCornerShape(12.dp),
                        )
                        .clickable { currency = code }
                        .padding(vertical = 9.dp),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        code,
                        style = MaterialTheme.typography.labelSmall,
                        color = if (active) MaterialTheme.colorScheme.onPrimary else MaterialTheme.colorScheme.onSurface,
                        maxLines = 1,
                    )
                }
            }
        }
        // Switching totals ↔ stores ↔ categories animates the whole plot area.
        androidx.compose.animation.AnimatedContent(
            targetState = mode,
            transitionSpec = {
                (androidx.compose.animation.fadeIn(androidx.compose.animation.core.tween(320)) +
                    androidx.compose.animation.scaleIn(androidx.compose.animation.core.tween(320), initialScale = 0.96f))
                    .togetherWith(androidx.compose.animation.fadeOut(androidx.compose.animation.core.tween(180)))
            },
            label = "year-chart-mode",
        ) { currentMode ->
            val series = buildSeries(currentMode)
            Column(Modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                AreaLineChart(series, shownMonths.map { it.toInt().toString() }, currency)
                when {
                    series.size > 5 -> OutlinedButton(
                        onClick = { legendSeries = series },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(14.dp),
                    ) {
                        Text("${stringResource(R.string.chart_legend)} · ${series.size}")
                    }
                    else -> FlowRow(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                        series.forEach { item ->
                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                Box(Modifier.size(9.dp).clip(CircleShape).background(item.color))
                                Text(item.label, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.8f), style = MaterialTheme.typography.bodySmall)
                            }
                        }
                    }
                }
            }
        }
        legendSeries?.let { series -> ChartLegendDialog(series) { legendSeries = null } }
        // Filled, so it never looks like the legend button above it.
        Button(
            onClick = { mode = when (mode) { "total" -> "stores"; "stores" -> "categories"; else -> "total" } },
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(14.dp),
        ) {
            Text(
                when (mode) {
                    "total" -> stringResource(R.string.per_store)
                    "stores" -> stringResource(R.string.chart_by_category)
                    else -> stringResource(R.string.totals)
                },
            )
        }
    }
}

/** Legend for charts with many series: searchable list instead of a wall of labels. */
@Composable
private fun ChartLegendDialog(series: List<ChartSeries>, onDismiss: () -> Unit) {
    var query by remember { mutableStateOf("") }
    val filtered = remember(series, query) {
        if (query.isBlank()) series else series.filter { it.label.contains(query, ignoreCase = true) }
    }
    androidx.compose.ui.window.Dialog(onDismissRequest = onDismiss) {
        androidx.compose.material3.Surface(shape = RoundedCornerShape(28.dp), color = MaterialTheme.colorScheme.surface, tonalElevation = 2.dp) {
            Column(Modifier.fillMaxWidth().padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text(stringResource(R.string.chart_legend_title), style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.onSurface)
                androidx.compose.material3.OutlinedTextField(
                    query,
                    { query = it },
                    Modifier.fillMaxWidth(),
                    placeholder = { Text(stringResource(R.string.chart_legend_search)) },
                    singleLine = true,
                    shape = RoundedCornerShape(14.dp),
                )
                androidx.compose.foundation.lazy.LazyColumn(
                    Modifier.fillMaxWidth().heightIn(max = 360.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    items(filtered) { item ->
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                            Box(Modifier.size(10.dp).clip(CircleShape).background(item.color))
                            Text(item.label, color = MaterialTheme.colorScheme.onSurface, modifier = Modifier.weight(1f))
                            Text(compactMoney(item.values.sum()), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }
                }
                androidx.compose.material3.Button(onDismiss, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp)) {
                    Text(stringResource(R.string.heatmap_close))
                }
            }
        }
    }
}

private data class ChartSeries(val label: String, val color: Color, val values: List<Double>)

private fun compactMoney(value: Double): String {
    val abs = abs(value)
    val sign = if (value < 0) "-" else ""
    return when {
        abs >= 1_000_000 -> "$sign${"%.1f".format(abs / 1_000_000)}M"
        abs >= 1_000 -> "$sign${"%.1f".format(abs / 1_000)}k"
        else -> "$sign${"%.0f".format(abs)}"
    }
}

/**
 * Line/area chart with a real vertical scale: four labelled grid ticks, gradient fills
 * under every line and a rounded stroke so the series stay readable in both themes.
 */
@Composable
private fun AreaLineChart(series: List<ChartSeries>, labels: List<String>, currency: String) {
    val grid = MaterialTheme.colorScheme.outlineVariant
    val axisText = MaterialTheme.colorScheme.onSurfaceVariant
    val surfaceColor = MaterialTheme.colorScheme.surface
    val allValues = series.flatMap { it.values }
    val rawMax = allValues.maxOrNull() ?: 0.0
    val rawMin = allValues.minOrNull() ?: 0.0
    val top = max(1.0, if (rawMax == rawMin) rawMax + 1 else rawMax + (rawMax - rawMin) * 0.12)
    val bottom = if (rawMin < 0) rawMin - (rawMax - rawMin) * 0.12 else 0.0
    val span = (top - bottom).coerceAtLeast(1.0)
    val ticks = (0..3).map { i -> top - span * i / 3.0 }
    Column(Modifier.fillMaxWidth()) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.Top) {
            Column(
                Modifier.width(46.dp).height(196.dp).padding(end = 4.dp),
                verticalArrangement = Arrangement.SpaceBetween,
                horizontalAlignment = Alignment.End,
            ) {
                ticks.forEach { tick ->
                    Text(compactMoney(tick), style = MaterialTheme.typography.labelSmall, color = axisText)
                }
            }
            Canvas(Modifier.weight(1f).height(196.dp)) {
                val h = size.height
                ticks.forEachIndexed { i, _ ->
                    val y = h * i / 3f
                    drawLine(grid, Offset(0f, y), Offset(size.width, y), 1.4f)
                }
                val stepX = if (labels.size > 1) size.width / (labels.size - 1) else size.width
                fun xAt(index: Int) = if (labels.size > 1) stepX * index else size.width / 2f
                fun yAt(value: Double) = (h * (1.0 - ((value - bottom) / span))).toFloat()
                series.forEach { item ->
                    if (item.values.isEmpty()) return@forEach
                    val line = Path()
                    val fill = Path()
                    val coords = item.values.mapIndexed { index, value -> Offset(xAt(index), yAt(value)) }
                    line.moveTo(coords.first().x, coords.first().y)
                    fill.moveTo(coords.first().x, h)
                    fill.lineTo(coords.first().x, coords.first().y)
                    for (i in 1 until coords.size) {
                        val prev = coords[i - 1]
                        val cur = coords[i]
                        val midX = (prev.x + cur.x) / 2f
                        line.cubicTo(midX, prev.y, midX, cur.y, cur.x, cur.y)
                        fill.cubicTo(midX, prev.y, midX, cur.y, cur.x, cur.y)
                    }
                    fill.lineTo(coords.last().x, h)
                    fill.close()
                    drawPath(fill, Brush.verticalGradient(listOf(item.color.copy(alpha = 0.34f), item.color.copy(alpha = 0.02f)), startY = 0f, endY = h))
                    drawPath(line, item.color, style = Stroke(width = 4.5f))
                    if (coords.size <= 14) coords.forEach { point ->
                        drawCircle(surfaceColor, 5.5f, point)
                        drawCircle(item.color, 3.4f, point)
                    }
                }
            }
        }
        Row(Modifier.fillMaxWidth().padding(start = 46.dp), verticalAlignment = Alignment.CenterVertically) {
            labels.forEachIndexed { index, label ->
                Text(
                    label,
                    style = MaterialTheme.typography.labelSmall,
                    color = axisText,
                    textAlign = androidx.compose.ui.text.style.TextAlign.Center,
                    modifier = Modifier.weight(1f),
                )
                if (index == labels.lastIndex) Spacer(Modifier.width(0.dp))
            }
        }
        Text(
            currency,
            style = MaterialTheme.typography.labelSmall,
            color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.55f),
            modifier = Modifier.padding(start = 46.dp, top = 4.dp),
        )
    }
}

@Composable
fun CategoryBars(state: AppState) {
    val catOf = state.snapshot.stores.associate { it.name to it.category }
    val totals = state.snapshot.expenses.groupBy { catOf[it.store] ?: "other" }.mapValues { it.value.sumOf { e -> e.amount } }.toList().sortedByDescending { it.second }
    val max = totals.maxOfOrNull { it.second } ?: 1.0
    Column(Modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        totals.take(8).forEachIndexed { i, (cat, total) ->
            Text(cat.replaceFirstChar { it.uppercase() }, style = MaterialTheme.typography.labelLarge)
            Box(Modifier.fillMaxWidth().height(10.dp).clip(RoundedCornerShape(99.dp)).background(MaterialTheme.colorScheme.surfaceVariant)) {
                Box(Modifier.fillMaxWidth((total / max).toFloat().coerceIn(0.04f, 1f)).height(10.dp).clip(RoundedCornerShape(99.dp)).background(StorePalette[i % StorePalette.size]))
            }
            Text(money(total, state.snapshot.currency), fontWeight = FontWeight.SemiBold)
        }
        if (totals.isEmpty()) Text(stringResource(R.string.no_category_spend), color = MaterialTheme.colorScheme.onSurfaceVariant)
    }
}

/** Responsive month grid; rendered inside a modal from the stats hub. */
@Composable
fun CalendarHeatmap(state: AppState, modifier: Modifier = Modifier) {
    val byDay = state.snapshot.expenses.groupBy { it.date }.mapValues { it.value.sumOf { e -> e.amount } }
    val month = YearMonth.now()
    val prefix = month.toString()
    val monthValues = byDay.filterKeys { it.startsWith(prefix) }
    val max = monthValues.values.maxOrNull() ?: 1.0
    val first = month.atDay(1)
    val shift = first.dayOfWeek.value % 7
    val days = month.lengthOfMonth()
    val cells = buildList<Int?> {
        repeat(shift) { add(null) }
        (1..days).forEach { add(it) }
        while (size % 7 != 0) add(null)
    }
    val heat = SpendsColors.expense()
    val empty = MaterialTheme.colorScheme.surfaceVariant
    val weekday = listOf("M", "T", "W", "T", "F", "S", "S")
    Column(modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(
            month.month.name.lowercase().replaceFirstChar { it.titlecase() } + " ${month.year}",
            style = MaterialTheme.typography.titleMedium,
        )
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(4.dp)) {
            weekday.forEach { day ->
                Text(day, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.weight(1f))
            }
        }
        cells.chunked(7).forEach { week ->
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                week.forEach { day ->
                    if (day == null) {
                        Box(Modifier.weight(1f).aspectRatio(1f))
                    } else {
                        val key = "%s-%02d".format(prefix, day)
                        val value = byDay[key] ?: 0.0
                        val ratio = (value / max).toFloat().coerceIn(0f, 1f)
                        val color = if (value <= 0.0) empty else heat.copy(alpha = 0.22f + 0.78f * ratio)
                        Box(
                            Modifier.weight(1f).aspectRatio(1f).clip(RoundedCornerShape(8.dp)).background(color),
                            contentAlignment = Alignment.Center,
                        ) {
                            Text(
                                day.toString(),
                                fontSize = 9.sp,
                                color = if (ratio > 0.55f) Color.White else MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }
                }
            }
        }
    }
}

/** Exchange rates live in their own modal window. */
@Composable
fun RatesDialog(state: AppState, onDismiss: () -> Unit) {
    androidx.compose.ui.window.Dialog(onDismissRequest = onDismiss) {
        androidx.compose.material3.Surface(
            shape = RoundedCornerShape(28.dp),
            color = MaterialTheme.colorScheme.surface,
            tonalElevation = 2.dp,
        ) {
            Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Outlined.ShowChart, null, tint = MaterialTheme.colorScheme.primary)
                    Spacer(Modifier.width(8.dp))
                    Text(stringResource(R.string.rates_title), style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.onSurface)
                }
                Column(
                    Modifier.heightIn(max = 430.dp).verticalScroll(rememberScrollState()),
                ) {
                    RatesSection(state)
                }
                androidx.compose.material3.Button(onDismiss, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp)) {
                    Text(stringResource(R.string.heatmap_close))
                }
            }
        }
    }
}

/** One card per currency: code, live rate, 14-day trend and a mini chart. */
@Composable
fun RatesSection(state: AppState) {
    val base = state.rates.base.ifBlank { state.snapshot.currency }
    // The server returns every quoted currency; show the ones this app actually tracks.
    val preferred = listOf("EUR", "USD", "PLN", "UAH", "BYN", "GBP", "CHF", "CZK", "SEK", "NOK", "TRY", "CAD")
    val rates = preferred.mapNotNull { code -> state.rates.rates[code]?.takeIf { code != base }?.let { code to it } }
    val updated = remember(state.rates.updatedAt) {
        if (state.rates.updatedAt <= 0) "" else java.text.DateFormat.getDateTimeInstance(java.text.DateFormat.SHORT, java.text.DateFormat.SHORT)
            .format(java.util.Date(state.rates.updatedAt * 1000))
    }
    Column(Modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(stringResource(R.string.rates_title), style = MaterialTheme.typography.titleMedium)
                Text(
                    stringResource(R.string.rates_base, base) + if (updated.isBlank()) "" else " · " + stringResource(R.string.rates_updated, updated),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f),
                )
            }
        }
        if (rates.isEmpty()) {
            Card(shape = RoundedCornerShape(18.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
                Text(
                    stringResource(R.string.rates_unavailable),
                    Modifier.fillMaxWidth().padding(16.dp),
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
        rates.chunked(2).forEach { pair ->
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                pair.forEach { (code, value) ->
                    RateCard(
                        code = code,
                        base = base,
                        value = value,
                        history = state.rates.history[code].orEmpty(),
                        modifier = Modifier.weight(1f),
                    )
                }
                if (pair.size == 1) Spacer(Modifier.weight(1f))
            }
        }
    }
}

@Composable
private fun RateCard(code: String, base: String, value: Double, history: List<Double>, modifier: Modifier = Modifier) {
    val delta = if (history.size > 1 && history.first() != 0.0) (history.last() - history.first()) / history.first() else 0.0
    val trendColor = if (delta >= 0) SpendsColors.income() else SpendsColors.expense()
    Card(
        modifier.fillMaxWidth(),
        RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(0.dp),
    ) {
        Column(
            Modifier.fillMaxWidth().border(1.dp, MaterialTheme.colorScheme.outlineVariant, RoundedCornerShape(20.dp)).padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(30.dp).clip(CircleShape).background(MaterialTheme.colorScheme.primaryContainer), contentAlignment = Alignment.Center) {
                    Text(code.take(1), fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onPrimaryContainer, fontSize = 13.sp)
                }
                Spacer(Modifier.width(8.dp))
                Column(Modifier.weight(1f)) {
                    Text(code, fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.titleMedium)
                    Text("1 $base", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.55f))
                }
            }
            Text(
                formatRate(value),
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface,
            )
            if (history.size > 1) {
                Sparkline(history, trendColor, Modifier.fillMaxWidth().height(34.dp))
                Text(
                    "${if (delta >= 0) "+" else ""}${"%.2f".format(delta * 100)}%",
                    style = MaterialTheme.typography.labelSmall,
                    color = trendColor,
                )
            } else {
                Box(Modifier.fillMaxWidth().height(34.dp), contentAlignment = Alignment.CenterStart) {
                    Text(stringResource(R.string.rates_no_history), style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f))
                }
            }
        }
    }
}

private fun formatRate(value: Double): String = when {
    value <= 0.0 -> "—"
    value >= 1000 -> "%,.0f".format(value)
    value >= 100 -> "%,.2f".format(value)
    value >= 1 -> "%,.3f".format(value)
    else -> "%,.4f".format(value)
}

@Composable
fun Sparkline(values: List<Double>, color: Color, modifier: Modifier = Modifier) {
    if (values.size < 2) return
    val min = values.min()
    val max = values.max()
    val span = (max - min).takeIf { it > 0.0 } ?: 1.0
    Canvas(modifier) {
        val step = size.width / (values.size - 1)
        val points = values.mapIndexed { index, value ->
            Offset(index * step, (size.height * (1.0 - ((value - min) / span))).toFloat())
        }
        val line = Path()
        val fill = Path()
        line.moveTo(points.first().x, points.first().y)
        fill.moveTo(points.first().x, size.height)
        fill.lineTo(points.first().x, points.first().y)
        for (i in 1 until points.size) {
            val prev = points[i - 1]
            val cur = points[i]
            val midX = (prev.x + cur.x) / 2f
            line.cubicTo(midX, prev.y, midX, cur.y, cur.x, cur.y)
            fill.cubicTo(midX, prev.y, midX, cur.y, cur.x, cur.y)
        }
        fill.lineTo(points.last().x, size.height)
        fill.close()
        drawPath(fill, Brush.verticalGradient(listOf(color.copy(alpha = 0.28f), color.copy(alpha = 0.01f))))
        drawPath(line, color, style = Stroke(width = 2.5f))
        drawCircle(color, 2.6f, points.last())
    }
}

private fun conversion(state: AppState, target: String): Double {
    val base = state.snapshot.currency
    if (target == base) return 1.0
    return state.rates.rates[target] ?: 1.0
}
