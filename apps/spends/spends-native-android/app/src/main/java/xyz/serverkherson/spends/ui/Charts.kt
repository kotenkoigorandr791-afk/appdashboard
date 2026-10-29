package xyz.serverkherson.spends.ui

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.ChevronLeft
import androidx.compose.material.icons.outlined.ChevronRight
import androidx.compose.material.icons.outlined.ShowChart
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.unit.dp
import xyz.serverkherson.spends.AppState
import java.time.LocalDate
import kotlin.math.max

private val StorePalette = listOf(
    Color(0xFFFF6384), Color(0xFF36A2EB), Color(0xFFFFCE56), Color(0xFF4BC0C0),
    Color(0xFF9966FF), Color(0xFFFF9F40), Color(0xFF067F00), Color(0xFFC9CBCF),
)

@Composable
fun YearExpenseChart(state: AppState) {
    val years = state.snapshot.years.ifEmpty { listOf(LocalDate.now().year) }.sorted()
    var year by remember(years) { mutableIntStateOf(years.last()) }
    var mode by remember { mutableStateOf("total") }
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
    val series = buildList {
        if (mode == "total") {
            add(ChartSeries("Expenses", SpendsColors.Expense, shownMonths.map { (monthlyStores[it]?.values?.sum() ?: 0.0) * conv }))
            if (state.snapshot.flags.showIncomes) add(ChartSeries("Incomes", SpendsColors.Income, shownMonths.map { (monthlyIncome[it] ?: 0.0) * conv }))
            if (state.snapshot.flags.showTrend) {
                val exp = shownMonths.map { (monthlyStores[it]?.values?.sum() ?: 0.0) * conv }
                val inc = shownMonths.map { (monthlyIncome[it] ?: 0.0) * conv }
                add(ChartSeries("Difference", Color(0xFFF59E0B), exp.indices.map { inc[it] - exp[it] }))
            }
        } else {
            visible.take(8).forEachIndexed { i, store ->
                add(ChartSeries(store.name, StorePalette[i % StorePalette.size], shownMonths.map { (monthlyStores[it]?.get(store.name) ?: 0.0) * conv }))
            }
        }
    }
    Column(Modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Outlined.ShowChart, null, tint = MaterialTheme.colorScheme.primary)
            Spacer(Modifier.padding(6.dp))
            Text("Year chart", style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
            IconButton({ if (years.any { it == year - 1 }) year-- }, enabled = years.any { it == year - 1 }) { Icon(Icons.Outlined.ChevronLeft, "Prev") }
            Text("$year", style = MaterialTheme.typography.titleMedium)
            IconButton({ if (years.any { it == year + 1 }) year++ }, enabled = years.any { it == year + 1 }) { Icon(Icons.Outlined.ChevronRight, "Next") }
        }
        Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            listOf("USD", "EUR", "PLN", "UAH", "BYN").forEach { code ->
                FilterChip(selected = currency == code, onClick = { currency = code }, label = { Text(code) })
            }
        }
        FilterChip(selected = mode == "stores", onClick = { mode = if (mode == "total") "stores" else "total" }, label = { Text(if (mode == "total") "Per store" else "Totals") })
        LineChart(series, shownMonths.map { it.toInt().toString() }, currency)
        series.take(6).forEach { Text("● ${it.label}", color = it.color, style = MaterialTheme.typography.bodySmall) }
    }
}

private data class ChartSeries(val label: String, val color: Color, val values: List<Double>)

@Composable
private fun LineChart(series: List<ChartSeries>, labels: List<String>, currency: String) {
    val grid = MaterialTheme.colorScheme.outline.copy(alpha = 0.35f)
    val maxY = max(1.0, series.flatMap { it.values }.maxOrNull() ?: 1.0)
    val minY = minOf(0.0, series.flatMap { it.values }.minOrNull() ?: 0.0)
    val span = (maxY - minY).coerceAtLeast(1.0)
    Box(Modifier.fillMaxWidth().height(220.dp)) {
        Canvas(Modifier.matchParentSize()) {
            val padL = 12f; val padR = 12f; val padT = 16f; val padB = 28f
            val w = size.width - padL - padR
            val h = size.height - padT - padB
            for (i in 0..4) {
                val y = padT + h * i / 4f
                drawLine(grid, Offset(padL, y), Offset(padL + w, y), 1f)
            }
            series.forEach { s ->
                if (s.values.isEmpty()) return@forEach
                val path = Path()
                s.values.forEachIndexed { i, v ->
                    val x = padL + if (s.values.size == 1) w / 2f else w * i / (s.values.size - 1)
                    val y = padT + h * (1f - ((v - minY) / span).toFloat())
                    if (i == 0) path.moveTo(x, y) else path.lineTo(x, y)
                    drawCircle(s.color, 7f, Offset(x, y))
                }
                if (s.values.size > 1) {
                    drawPath(path, s.color.copy(alpha = 0.18f))
                    drawPath(path, s.color, style = Stroke(width = 5f, pathEffect = PathEffect.cornerPathEffect(18f)))
                }
            }
        }
        Text(currency, style = MaterialTheme.typography.labelLarge, modifier = Modifier.align(Alignment.TopEnd).padding(8.dp), color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f))
        Row(Modifier.align(Alignment.BottomCenter).fillMaxWidth().padding(horizontal = 8.dp), horizontalArrangement = Arrangement.SpaceBetween) {
            labels.forEach { Text(it, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.45f)) }
        }
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
            Text(money(total, state.snapshot.currency), fontWeight = androidx.compose.ui.text.font.FontWeight.SemiBold)
        }
        if (totals.isEmpty()) Text("No category spend yet.")
    }
}

@Composable
fun CalendarHeatmap(state: AppState) {
    val byDay = state.snapshot.expenses.groupBy { it.date }.mapValues { it.value.sumOf { e -> e.amount } }
    val month = java.time.YearMonth.now()
    val prefix = month.toString()
    val max = byDay.filterKeys { it.startsWith(prefix) }.values.maxOrNull() ?: 1.0
    val first = month.atDay(1)
    val shift = first.dayOfWeek.value % 7
    val days = month.lengthOfMonth()
    Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally) {
        Text(month.month.name.lowercase().replaceFirstChar { it.titlecase() } + " ${month.year}", style = MaterialTheme.typography.titleMedium)
        Spacer(Modifier.height(8.dp))
        Canvas(Modifier.fillMaxWidth(0.78f).height(176.dp)) {
            val cols = 7
            val rows = 6
            val gap = 6f
            val cell = minOf((size.width - gap * (cols - 1)) / cols, (size.height - gap * (rows - 1)) / rows)
            val gridW = cols * cell + (cols - 1) * gap
            val gridH = rows * cell + (rows - 1) * gap
            val ox = (size.width - gridW) / 2f
            val oy = (size.height - gridH) / 2f
            for (day in 1..days) {
                val idx = shift + day - 1
                val col = idx % 7
                val row = idx / 7
                val key = "%s-%02d".format(prefix, day)
                val v = ((byDay[key] ?: 0.0) / max).toFloat()
                val color = if (v <= 0f) Color(0x22808090) else Color(0xFFE24B63).copy(alpha = 0.22f + 0.78f * v)
                drawRoundRect(color, Offset(ox + col * (cell + gap), oy + row * (cell + gap)), androidx.compose.ui.geometry.Size(cell, cell), androidx.compose.ui.geometry.CornerRadius(8f, 8f))
            }
        }
    }
}

private fun conversion(state: AppState, target: String): Double {
    val base = state.snapshot.currency
    if (target == base) return 1.0
    val rates = state.rates.rates
    val toTarget = rates[target] ?: return 1.0
    return toTarget
}
