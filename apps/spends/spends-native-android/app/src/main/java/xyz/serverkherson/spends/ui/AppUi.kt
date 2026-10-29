package xyz.serverkherson.spends.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.Add
import androidx.compose.material.icons.outlined.Backup
import androidx.compose.material.icons.outlined.CalendarMonth
import androidx.compose.material.icons.outlined.CameraAlt
import androidx.compose.material.icons.outlined.Category
import androidx.compose.material.icons.outlined.ChevronRight
import androidx.compose.material.icons.outlined.Cloud
import androidx.compose.material.icons.outlined.Delete
import androidx.compose.material.icons.outlined.Flag
import androidx.compose.material.icons.outlined.History
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material.icons.outlined.Insights
import androidx.compose.material.icons.outlined.Label
import androidx.compose.material.icons.outlined.Notifications
import androidx.compose.material.icons.outlined.Palette
import androidx.compose.material.icons.outlined.PieChart
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material.icons.outlined.Settings
import androidx.compose.material.icons.outlined.ShowChart
import androidx.compose.material.icons.outlined.Storefront
import androidx.compose.material.icons.outlined.TrendingDown
import androidx.compose.material.icons.outlined.TrendingUp
import androidx.compose.material.icons.outlined.Tune
import androidx.compose.material.icons.outlined.Wallet
import androidx.compose.material.icons.outlined.WorkspacePremium
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.RectangleShape
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import android.net.Uri
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import coil.compose.AsyncImage
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.time.format.DateTimeParseException
import java.util.Locale
import kotlinx.coroutines.launch
import xyz.serverkherson.spends.AppState
import xyz.serverkherson.spends.BuildConfig
import xyz.serverkherson.spends.NotificationAccess
import xyz.serverkherson.spends.R
import xyz.serverkherson.spends.SpendsViewModel
import xyz.serverkherson.spends.data.Expense
import xyz.serverkherson.spends.data.Goal
import xyz.serverkherson.spends.data.PendingCapture
import xyz.serverkherson.spends.data.Store
import xyz.serverkherson.spends.updates.AppUpdateChecker
import xyz.serverkherson.spends.updates.UpdateOutcome

enum class Dest {
    Home, Stats, Stores, Settings, Insights, CashFlow, Categories, Calendar, Wrapped, NetWorth,
    Goals, Tags, Trash, Pending, Prefs, Data, Sync, Appearance, Transaction, Ocr, Expense, Store, About,
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(state: AppState, model: SpendsViewModel, onOpen: (Expense) -> Unit) {
    val groups = remember(state.snapshot.expenses) { state.snapshot.expenses.sortedByDescending { it.date }.groupBy { it.date } }
    PullToRefreshBox(state.loading, model::refresh, Modifier.fillMaxSize()) {
        LazyColumn(Modifier.fillMaxSize().padding(horizontal = 20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            item { Spacer(Modifier.height(12.dp)); Greeting(); Spacer(Modifier.height(4.dp)); HeroCard(state) }
            item { SectionLabel(Icons.Outlined.History, stringResource(R.string.activity)) }
            if (groups.isEmpty()) item { EmptyCard(stringResource(R.string.ledger_empty_title), stringResource(R.string.ledger_empty_body)) }
            groups.forEach { (date, rows) ->
                item {
                    Text(
                        prettyDate(date),
                        style = MaterialTheme.typography.labelLarge,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(top = 8.dp, start = 4.dp),
                    )
                }
                items(rows, key = { "${it.date}-${it.store}-${it.amount}-${it.signature}" }) { row ->
                    ExpenseRow(row, state.snapshot.currency, state.snapshot.stores.firstOrNull { it.name == row.store }?.photoUrl.orEmpty()) { onOpen(row) }
                }
            }
            item { Spacer(Modifier.height(120.dp)) }
        }
    }
}

@Composable
fun Greeting() {
    Text(
        stringResource(R.string.app_tagline),
        style = MaterialTheme.typography.headlineMedium,
        color = MaterialTheme.colorScheme.onBackground,
        modifier = Modifier.padding(top = 8.dp),
    )
}

@Composable
fun HeroCard(state: AppState) {
    val spent = state.snapshot.expenses.sumOf { it.amount }
    val earned = state.snapshot.incomes.sumOf { it.amount }
    val net = earned - spent
    val budget = state.snapshot.budget
    val ink = MaterialTheme.colorScheme.onPrimary
    Box(Modifier.fillMaxWidth().background(heroBrush(), RoundedCornerShape(28.dp)).padding(22.dp)) {
        Column {
            Text(stringResource(R.string.this_period), color = ink.copy(alpha = 0.75f), style = MaterialTheme.typography.labelLarge)
            Text(money(net, state.snapshot.currency), color = ink, style = MaterialTheme.typography.headlineLarge)
            Spacer(Modifier.height(16.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Column(Modifier.weight(1f).clip(RoundedCornerShape(16.dp)).background(ink.copy(alpha = 0.12f)).padding(12.dp)) {
                    Text(stringResource(R.string.spent), color = ink.copy(alpha = 0.7f), style = MaterialTheme.typography.labelLarge)
                    Text(money(spent, state.snapshot.currency), color = ink, fontWeight = FontWeight.SemiBold)
                }
                Column(Modifier.weight(1f).clip(RoundedCornerShape(16.dp)).background(ink.copy(alpha = 0.12f)).padding(12.dp)) {
                    Text(stringResource(R.string.cash_in), color = ink.copy(alpha = 0.7f), style = MaterialTheme.typography.labelLarge)
                    Text(money(earned, state.snapshot.currency), color = ink, fontWeight = FontWeight.SemiBold)
                }
            }
            if (budget > 0) {
                val ratio = (spent / budget).toFloat().coerceIn(0f, 1f)
                Spacer(Modifier.height(14.dp))
                Text(
                    stringResource(R.string.of_budget, "${(ratio * 100).toInt()}%", money(budget, state.snapshot.currency)),
                    color = ink.copy(alpha = 0.85f),
                    style = MaterialTheme.typography.bodySmall,
                )
                Spacer(Modifier.height(8.dp))
                LinearProgressIndicator(
                    { ratio },
                    Modifier.fillMaxWidth().height(8.dp).clip(RoundedCornerShape(99.dp)),
                    ink,
                    ink.copy(alpha = 0.22f),
                )
            }
        }
    }
}

@Composable
fun ExpenseRow(expense: Expense, currency: String, photoUrl: String, onClick: () -> Unit) {
    val initial = expense.store.trim().firstOrNull()?.uppercaseChar()?.toString() ?: "•"
    Card(
        Modifier.clickable(onClick = onClick),
        RoundedCornerShape(22.dp),
        colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(0.dp),
    ) {
        Row(
            Modifier.fillMaxWidth().border(1.dp, MaterialTheme.colorScheme.outlineVariant, RoundedCornerShape(22.dp)).padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            RemotePhoto(photoUrl, initial, round = false, size = 44.dp)
            Spacer(Modifier.width(14.dp))
            Column(Modifier.weight(1f)) {
                Text(expense.store, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis, color = MaterialTheme.colorScheme.onSurface)
                Text(
                    expense.entrySource.ifBlank { "manual" },
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Text("−${money(expense.amount, currency)}", fontWeight = FontWeight.SemiBold, color = SpendsColors.expense())
        }
    }
}

@Composable
fun EmptyCard(title: String, body: String) = Card(shape = RoundedCornerShape(24.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
    Column(Modifier.fillMaxWidth().padding(28.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(4.dp)) {
        Text(title, style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.onSurface)
        Text(body, color = MaterialTheme.colorScheme.onSurfaceVariant, textAlign = TextAlign.Center)
    }
}

@Composable
fun StatsHub(state: AppState, go: (Dest) -> Unit) {
    var heatmap by remember { mutableStateOf(false) }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        Text(stringResource(R.string.statistics), style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onBackground)
        SectionLabel(Icons.Outlined.ShowChart, stringResource(R.string.section_ledger))
        YearExpenseChart(state)
        SectionLabel(Icons.Outlined.PieChart, stringResource(R.string.section_breakdown))
        CategoryBars(state)
        SectionLabel(Icons.Outlined.CalendarMonth, stringResource(R.string.section_calendar))
        ActionCard(Icons.Outlined.CalendarMonth, stringResource(R.string.calendar), stringResource(R.string.calendar_sub)) { heatmap = true }
        SectionLabel(Icons.Outlined.Insights, stringResource(R.string.section_more))
        NavRow(stringResource(R.string.cash_flow), stringResource(R.string.cash_flow_sub), Icons.Outlined.ShowChart, Dest.CashFlow, go)
        NavRow(stringResource(R.string.categories), stringResource(R.string.categories_sub), Icons.Outlined.Category, Dest.Categories, go)
        NavRow(stringResource(R.string.insights), stringResource(R.string.insights_sub), Icons.Outlined.Insights, Dest.Insights, go)
        NavRow(stringResource(R.string.net_position), stringResource(R.string.net_position_sub), Icons.Outlined.TrendingUp, Dest.NetWorth, go)
        NavRow(stringResource(R.string.year_in_review), stringResource(R.string.year_in_review_sub), Icons.Outlined.WorkspacePremium, Dest.Wrapped, go)
        SectionLabel(Icons.Outlined.ShowChart, stringResource(R.string.section_rates, state.rates.base.ifBlank { state.snapshot.currency }))
        RatesSection(state)
        Spacer(Modifier.height(24.dp))
    }
    if (heatmap) HeatmapModal(state) { heatmap = false }
}

@Composable
fun SectionLabel(icon: ImageVector, title: String) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier.padding(top = 6.dp),
    ) {
        Icon(icon, null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(18.dp))
        Text(title, style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
    }
}

@Composable
fun ActionCard(icon: ImageVector, title: String, subtitle: String, onClick: () -> Unit) {
    Card(
        Modifier.fillMaxWidth().clickable(onClick = onClick),
        RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(0.dp),
    ) {
        Row(
            Modifier.fillMaxWidth().border(1.dp, MaterialTheme.colorScheme.outlineVariant, RoundedCornerShape(20.dp)).padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Box(Modifier.size(40.dp).clip(RoundedCornerShape(12.dp)).background(MaterialTheme.colorScheme.primaryContainer), contentAlignment = Alignment.Center) {
                Icon(icon, null, tint = MaterialTheme.colorScheme.onPrimaryContainer)
            }
            Column(Modifier.weight(1f)) {
                Text(title, fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onSurface)
                Text(subtitle, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            Icon(Icons.Outlined.ChevronRight, null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}

@Composable
fun NavRow(title: String, subtitle: String, icon: ImageVector, dest: Dest, go: (Dest) -> Unit) {
    ActionCard(icon, title, subtitle) { go(dest) }
}

@Composable
fun HeatmapModal(state: AppState, onDismiss: () -> Unit) {
    Dialog(onDismissRequest = onDismiss) {
        Surface(shape = RoundedCornerShape(28.dp), color = MaterialTheme.colorScheme.surface, tonalElevation = 2.dp) {
            Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Outlined.CalendarMonth, null, tint = MaterialTheme.colorScheme.primary)
                    Spacer(Modifier.width(8.dp))
                    Text(stringResource(R.string.heatmap_title), style = MaterialTheme.typography.titleLarge, modifier = Modifier.weight(1f), color = MaterialTheme.colorScheme.onSurface)
                }
                CalendarHeatmap(state)
                Button(onDismiss, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp)) { Text(stringResource(R.string.heatmap_close)) }
            }
        }
    }
}

@Composable
fun CashFlowScreen(state: AppState) {
    val byMonth = state.snapshot.expenses.groupBy { it.date.take(7) }.mapValues { it.value.sumOf { e -> e.amount } }.toSortedMap()
    val max = byMonth.values.maxOrNull() ?: 1.0
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text(stringResource(R.string.cash_flow_title), style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onBackground)
        if (byMonth.isEmpty()) EmptyCard(stringResource(R.string.no_months_title), stringResource(R.string.no_months_body))
        byMonth.forEach { (month, total) ->
            Card(shape = RoundedCornerShape(18.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
                Column(Modifier.fillMaxWidth().padding(16.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    Text(month, style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    LinearProgressIndicator(
                        { (total / max).toFloat() },
                        Modifier.fillMaxWidth().height(10.dp).clip(RoundedCornerShape(99.dp)),
                        MaterialTheme.colorScheme.primary,
                        MaterialTheme.colorScheme.surfaceVariant,
                    )
                    Text(money(total, state.snapshot.currency), fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onSurface)
                }
            }
        }
    }
}

private val CategoryPalette = listOf(
    Color(0xFFFF6384), Color(0xFF36A2EB), Color(0xFFF5B301), Color(0xFF4BC0C0),
    Color(0xFF9966FF), Color(0xFFFF9F40), Color(0xFF3BA55C), Color(0xFF8B95A5),
)

private data class CategoryRow(val name: String, val amount: Double, val charges: Int, val stores: List<String>, val topStore: String?)

private fun categoryRows(state: AppState): List<CategoryRow> {
    val catOf = state.snapshot.stores.associate { it.name to it.category.ifBlank { "other" } }
    return state.snapshot.expenses
        .groupBy { catOf[it.store] ?: "other" }
        .map { (category, rows) ->
            val byStore = rows.groupBy { it.store }.mapValues { entry -> entry.value.sumOf { it.amount } }
            CategoryRow(
                name = category.replaceFirstChar { it.uppercase() },
                amount = rows.sumOf { it.amount },
                charges = rows.size,
                stores = byStore.keys.sorted(),
                topStore = byStore.maxByOrNull { it.value }?.key,
            )
        }
        .sortedByDescending { it.amount }
}

/** Category breakdown: one summary chart plus a card per category with its own numbers. */
@Composable
fun CategoriesScreen(state: AppState) {
    val rows = remember(state.snapshot.expenses, state.snapshot.stores) { categoryRows(state) }
    val total = rows.sumOf { it.amount }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text(stringResource(R.string.categories), style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onBackground)
        Box(Modifier.fillMaxWidth().background(heroBrush(), RoundedCornerShape(24.dp)).padding(20.dp)) {
            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(stringResource(R.string.categories_total), color = MaterialTheme.colorScheme.onPrimary.copy(alpha = 0.75f), style = MaterialTheme.typography.labelLarge)
                Text(money(total, state.snapshot.currency), color = MaterialTheme.colorScheme.onPrimary, style = MaterialTheme.typography.headlineMedium)
                Text(stringResource(R.string.categories_meta, rows.size, rows.sumOf { it.charges }), color = MaterialTheme.colorScheme.onPrimary.copy(alpha = 0.8f), style = MaterialTheme.typography.bodySmall)
            }
        }
        if (rows.isEmpty()) {
            EmptyCard(stringResource(R.string.no_category_spend), stringResource(R.string.ledger_empty_body))
            return@Column
        }
        SectionLabel(Icons.Outlined.PieChart, stringResource(R.string.categories_top))
        Card(Modifier.fillMaxWidth(), RoundedCornerShape(22.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface), elevation = CardDefaults.cardElevation(0.dp)) {
            Column(Modifier.fillMaxWidth().padding(18.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                rows.take(6).forEachIndexed { index, row ->
                    val share = if (total > 0) row.amount / total else 0.0
                    val color = CategoryPalette[index % CategoryPalette.size]
                    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Box(Modifier.size(9.dp).clip(CircleShape).background(color))
                            Spacer(Modifier.width(8.dp))
                            Text(row.name, modifier = Modifier.weight(1f), style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurface)
                            Text("${(share * 100).toInt()}%", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            Spacer(Modifier.width(8.dp))
                            Text(money(row.amount, state.snapshot.currency), style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onSurface)
                        }
                        Box(Modifier.fillMaxWidth().height(8.dp).clip(RoundedCornerShape(99.dp)).background(MaterialTheme.colorScheme.surfaceVariant)) {
                            Box(Modifier.fillMaxWidth(share.toFloat().coerceIn(0.03f, 1f)).height(8.dp).clip(RoundedCornerShape(99.dp)).background(color))
                        }
                    }
                }
            }
        }
        SectionLabel(Icons.Outlined.Category, stringResource(R.string.categories))
        rows.forEachIndexed { index, row ->
            CategoryCard(row, total, state.snapshot.currency, CategoryPalette[index % CategoryPalette.size])
        }
        Spacer(Modifier.height(24.dp))
    }
}

@Composable
private fun CategoryCard(row: CategoryRow, total: Double, currency: String, color: Color) {
    val share = if (total > 0) row.amount / total else 0.0
    Card(
        Modifier.fillMaxWidth(),
        RoundedCornerShape(22.dp),
        colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(0.dp),
    ) {
        Column(
            Modifier.fillMaxWidth().border(1.dp, MaterialTheme.colorScheme.outlineVariant, RoundedCornerShape(22.dp)).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(36.dp).clip(RoundedCornerShape(12.dp)).background(color.copy(alpha = 0.18f)), contentAlignment = Alignment.Center) {
                    Text(row.name.take(1), fontWeight = FontWeight.Bold, color = color)
                }
                Spacer(Modifier.width(12.dp))
                Column(Modifier.weight(1f)) {
                    Text(row.name, style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.onSurface)
                    Text(stringResource(R.string.categories_meta, row.stores.size, row.charges), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                Text(money(row.amount, currency), fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurface)
            }
            LinearProgressIndicator(
                { share.toFloat().coerceIn(0f, 1f) },
                Modifier.fillMaxWidth().height(8.dp).clip(RoundedCornerShape(99.dp)),
                color,
                MaterialTheme.colorScheme.surfaceVariant,
            )
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Text(stringResource(R.string.categories_share, "${(share * 100).toInt()}%"), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Text("·", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Text(stringResource(R.string.categories_avg, money(row.amount / row.charges.coerceAtLeast(1), currency)), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            row.topStore?.let { store ->
                Text(stringResource(R.string.categories_top_store, store), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.75f))
            }
        }
    }
}

@Composable
fun CalendarScreen(state: AppState) {
    Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text(stringResource(R.string.calendar), style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onBackground)
        CalendarHeatmap(state)
    }
}

/** Shared shell for the small stats pages so every field looks like the rest of the app. */
@Composable
private fun StatCard(label: String, value: String, accent: Color? = null) {
    Card(Modifier.fillMaxWidth(), RoundedCornerShape(20.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface), elevation = CardDefaults.cardElevation(0.dp)) {
        Column(Modifier.fillMaxWidth().padding(18.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Text(label, style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
            Text(value, style = MaterialTheme.typography.titleLarge, color = accent ?: MaterialTheme.colorScheme.onSurface)
        }
    }
}

@Composable
fun InsightsScreen(state: AppState) {
    val spent = state.snapshot.expenses.sumOf { it.amount }
    val top = state.snapshot.expenses.groupBy { it.store }.mapValues { it.value.sumOf { e -> e.amount } }.maxByOrNull { it.value }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text(stringResource(R.string.insights), style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onBackground)
        StatCard(stringResource(R.string.spent), stringResource(R.string.insights_spent, money(spent, state.snapshot.currency), state.snapshot.expenses.size))
        if (top != null) {
            StatCard(stringResource(R.string.insights), stringResource(R.string.insights_top, top.key, money(top.value, state.snapshot.currency)))
        }
        if (state.snapshot.budget > 0) {
            StatCard(stringResource(R.string.monthly_budget), stringResource(R.string.insights_budget, money(state.snapshot.budget - spent, state.snapshot.currency)))
        }
        StatCard(stringResource(R.string.pending), stringResource(R.string.insights_pending, state.extras.pending.size, state.extras.trash.size))
    }
}

@Composable
fun NetWorthScreen(state: AppState) {
    val incomes = state.snapshot.incomes.sumOf { it.amount }
    val expenses = state.snapshot.expenses.sumOf { it.amount }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text(stringResource(R.string.net_position), style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onBackground)
        StatCard(stringResource(R.string.incomes), stringResource(R.string.net_in, money(incomes, state.snapshot.currency)), SpendsColors.income())
        StatCard(stringResource(R.string.expenses), stringResource(R.string.net_out, money(expenses, state.snapshot.currency)), SpendsColors.expense())
        StatCard(stringResource(R.string.net_position), stringResource(R.string.net_total, money(incomes - expenses, state.snapshot.currency)))
    }
}

@Composable
fun WrappedScreen(state: AppState) {
    val year = LocalDate.now().year
    val yearSpend = state.snapshot.expenses.filter { it.date.startsWith("$year") }.sumOf { it.amount }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text(stringResource(R.string.year_in_review), style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onBackground)
        Box(Modifier.fillMaxWidth().background(heroBrush(), RoundedCornerShape(24.dp)).padding(22.dp)) {
            Column {
                Text("$year", color = MaterialTheme.colorScheme.onPrimary.copy(alpha = 0.75f), style = MaterialTheme.typography.labelLarge)
                Text(money(yearSpend, state.snapshot.currency), color = MaterialTheme.colorScheme.onPrimary, style = MaterialTheme.typography.headlineLarge)
            }
        }
        StatCard(
            stringResource(R.string.year_in_review_sub),
            stringResource(R.string.wrapped_stats, state.snapshot.stores.size, state.snapshot.goals.size, state.snapshot.tags.size),
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun StoresScreen(state: AppState, model: SpendsViewModel, onStore: (Store) -> Unit) {
    var add by remember { mutableStateOf(false) }
    Box(Modifier.fillMaxSize()) {
        LazyVerticalGrid(
            GridCells.Fixed(2),
            Modifier.fillMaxSize(),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            items(state.snapshot.stores, key = { it.id.ifBlank { it.name } }) { store ->
                Card(
                    Modifier.fillMaxWidth().clickable { onStore(store) },
                    RoundedCornerShape(22.dp),
                    colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface),
                ) {
                    Column(
                        Modifier.fillMaxWidth().padding(14.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(10.dp),
                    ) {
                        RemotePhoto(store.photoUrl, store.name.take(1).uppercase(), round = false, size = 92.dp)
                        Text(store.name, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis, color = MaterialTheme.colorScheme.onSurface)
                    }
                }
            }
        }
        // Same rounded square as the add-expense button for a consistent language.
        FloatingActionButton(
            { add = true },
            Modifier.align(Alignment.BottomEnd).padding(20.dp),
            shape = RoundedCornerShape(18.dp),
            containerColor = MaterialTheme.colorScheme.primary,
            contentColor = MaterialTheme.colorScheme.onPrimary,
        ) { Icon(Icons.Outlined.Add, stringResource(R.string.add_store)) }
    }
    if (add) {
        AddStoreSheet(state, { add = false }) { name, category, photo ->
            model.saveStore(name, category, photo)
            add = false
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun AddStoreSheet(state: AppState, onDismiss: () -> Unit, onSave: (String, String, ByteArray?) -> Unit) {
    val context = LocalContext.current
    var name by remember { mutableStateOf("") }
    var category by remember(state.snapshot.storeCategories) {
        mutableStateOf(state.snapshot.storeCategories.firstOrNull { it != "other" } ?: "other")
    }
    var pickCategory by remember { mutableStateOf(false) }
    var photoUri by remember { mutableStateOf<Uri?>(null) }
    var photoBytes by remember { mutableStateOf<ByteArray?>(null) }
    val pickPhoto = androidx.activity.compose.rememberLauncherForActivityResult(androidx.activity.result.contract.ActivityResultContracts.GetContent()) { uri ->
        if (uri != null) {
            photoUri = uri
            photoBytes = runCatching { context.contentResolver.openInputStream(uri)?.use { it.readBytes() } }.getOrNull()
        }
    }
    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp),
    ) {
        Column(
            Modifier.verticalScroll(rememberScrollState()).padding(horizontal = 24.dp).padding(bottom = 32.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Text(stringResource(R.string.new_store), style = MaterialTheme.typography.headlineMedium)
            OutlinedTextField(name, { name = it }, Modifier.fillMaxWidth(), label = { Text(stringResource(R.string.name)) }, shape = RoundedCornerShape(16.dp), singleLine = true)
            Card(
                Modifier.fillMaxWidth().clickable { pickCategory = true },
                RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surfaceVariant),
            ) {
                Row(Modifier.fillMaxWidth().padding(16.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    Icon(painterResource(StoreCategories.iconRes(category)), null, tint = MaterialTheme.colorScheme.primary)
                    Column(Modifier.weight(1f)) {
                        Text(stringResource(R.string.category), style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        Text(categoryLabel(category), style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.onSurface)
                    }
                    Icon(Icons.Outlined.ChevronRight, null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
            Card(
                Modifier.fillMaxWidth(),
                RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surfaceVariant),
            ) {
                Column(Modifier.fillMaxWidth().padding(12.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        Box(Modifier.size(52.dp).clip(RoundedCornerShape(14.dp)).background(MaterialTheme.colorScheme.surface), contentAlignment = Alignment.Center) {
                            if (photoUri != null) {
                                AsyncImage(model = photoUri, contentDescription = null, modifier = Modifier.matchParentSize(), contentScale = ContentScale.Crop)
                            } else {
                                Icon(Icons.Outlined.Storefront, null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                        }
                        Column(Modifier.weight(1f)) {
                            Text(stringResource(R.string.store_photo), style = MaterialTheme.typography.titleSmall, color = MaterialTheme.colorScheme.onSurface)
                            Text(stringResource(R.string.store_photo_hint), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }
                    OutlinedButton(
                        { pickPhoto.launch("image/*") },
                        Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(14.dp),
                    ) {
                        Text(stringResource(if (photoUri == null) R.string.store_photo_choose else R.string.store_photo_change))
                    }
                }
            }
            Button(
                { onSave(name.trim(), category, photoBytes) },
                Modifier.fillMaxWidth().height(54.dp),
                shape = RoundedCornerShape(18.dp),
                enabled = name.isNotBlank() && !state.loading,
            ) { Text(stringResource(R.string.save)) }
        }
    }
    if (pickCategory) {
        CategoryPickerDialog(
            selected = category,
            categories = (StoreCategories.keys + state.snapshot.storeCategories).distinct(),
            onPick = { category = it; pickCategory = false },
            onDismiss = { pickCategory = false },
        )
    }
}

/** Category tiles with the same icons and labels as the web interface. */
@Composable
fun CategoryPickerDialog(selected: String, categories: List<String>, onPick: (String) -> Unit, onDismiss: () -> Unit) {
    Dialog(onDismissRequest = onDismiss) {
        Surface(shape = RoundedCornerShape(28.dp), color = MaterialTheme.colorScheme.surface, tonalElevation = 2.dp) {
            Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                Text(stringResource(R.string.store_category_pick), style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.onSurface)
                LazyVerticalGrid(
                    GridCells.Fixed(3),
                    Modifier.fillMaxWidth().heightIn(max = 400.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    items(categories, key = { it }) { key ->
                        val active = key == selected
                        Column(
                            Modifier
                                .clip(RoundedCornerShape(16.dp))
                                .background(if (active) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surfaceVariant)
                                .border(1.dp, if (active) MaterialTheme.colorScheme.primary else Color.Transparent, RoundedCornerShape(16.dp))
                                .clickable { onPick(key) }
                                .padding(vertical = 10.dp, horizontal = 6.dp),
                            horizontalAlignment = Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.spacedBy(6.dp),
                        ) {
                            Icon(
                                painterResource(StoreCategories.iconRes(key)),
                                null,
                                tint = if (active) MaterialTheme.colorScheme.onPrimaryContainer else MaterialTheme.colorScheme.onSurfaceVariant,
                                modifier = Modifier.size(24.dp),
                            )
                            Text(
                                categoryLabel(key),
                                style = MaterialTheme.typography.labelSmall,
                                color = if (active) MaterialTheme.colorScheme.onPrimaryContainer else MaterialTheme.colorScheme.onSurface,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                            )
                        }
                    }
                }
                Button(onDismiss, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp)) { Text(stringResource(R.string.heatmap_close)) }
            }
        }
    }
}

@Composable
fun ExpenseDetailScreen(state: AppState, expense: Expense, onTrash: () -> Unit) {
    val store = state.snapshot.stores.firstOrNull { it.name == expense.store }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Card(Modifier.fillMaxWidth(), RoundedCornerShape(24.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
            Column(
                Modifier.fillMaxWidth().padding(20.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                RemotePhoto(store?.photoUrl.orEmpty(), expense.store.take(1).uppercase(), round = false, size = 88.dp)
                Text(expense.store, style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onSurface)
                Text(store?.category ?: stringResource(R.string.store_fallback), color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
        StatCard(stringResource(R.string.amount), "−${money(expense.amount, state.snapshot.currency)}", SpendsColors.expense())
        StatCard(stringResource(R.string.date), "${prettyDate(expense.date)} · ${expense.date}")
        StatCard(stringResource(R.string.source), expense.entrySource.ifBlank { "manual" })
        if (expense.ocrRawStore.isNotBlank()) {
            Card(Modifier.fillMaxWidth(), RoundedCornerShape(20.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
                Text(expense.ocrRawStore, Modifier.padding(16.dp), color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
        Button(
            onTrash,
            Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = ButtonDefaults.buttonColors(MaterialTheme.colorScheme.error, MaterialTheme.colorScheme.onError),
        ) { Text(stringResource(R.string.move_to_trash)) }
    }
}

@Composable
fun StoreDetailScreen(state: AppState, store: Store, onExpense: (Expense) -> Unit) {
    val rows = state.snapshot.expenses.filter { it.store == store.name }.sortedByDescending { it.date }
    val total = rows.sumOf { it.amount }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Card(Modifier.fillMaxWidth(), RoundedCornerShape(24.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
            Column(
                Modifier.fillMaxWidth().padding(20.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                RemotePhoto(store.photoUrl, store.name.take(1).uppercase(), round = false, size = 96.dp)
                Text(store.name, style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onSurface)
                Text(store.category, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
        StatCard(stringResource(R.string.total_spent), "${money(total, state.snapshot.currency)} · ${stringResource(R.string.charges_count, rows.size)}")
        rows.forEach { expense ->
            Card(
                Modifier.fillMaxWidth().clickable { onExpense(expense) },
                RoundedCornerShape(18.dp),
                colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface),
            ) {
                Row(Modifier.fillMaxWidth().padding(16.dp), horizontalArrangement = Arrangement.SpaceBetween) {
                    Column {
                        Text(prettyDate(expense.date), fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onSurface)
                        Text(expense.date, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                    Text("−${money(expense.amount, state.snapshot.currency)}", color = SpendsColors.expense(), fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

@Composable
fun SettingsHub(state: AppState, go: (Dest) -> Unit, logout: () -> Unit) {
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text(stringResource(R.string.settings), style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onBackground)
        Card(Modifier.fillMaxWidth(), RoundedCornerShape(22.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface), elevation = CardDefaults.cardElevation(0.dp)) {
            Row(Modifier.fillMaxWidth().padding(16.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                RemotePhoto(state.session.imageUrl, state.session.name.take(1).uppercase(), round = true, size = 56.dp, person = true)
                Column(Modifier.weight(1f)) {
                    Text(state.session.name.ifBlank { stringResource(R.string.paired_account) }, fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onSurface)
                    Text(state.session.email, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text(
                        state.session.endpoint,
                        style = MaterialTheme.typography.bodySmall,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f),
                    )
                }
            }
        }
        SectionLabel(Icons.Outlined.Insights, stringResource(R.string.overview))
        NavRow(stringResource(R.string.notifications_title), stringResource(R.string.notifications_sub), Icons.Outlined.Notifications, Dest.Pending, go)
        NavRow(stringResource(R.string.insights), stringResource(R.string.insights_sub), Icons.Outlined.Insights, Dest.Insights, go)
        NavRow(stringResource(R.string.stores_title), stringResource(R.string.categories_sub), Icons.Outlined.Storefront, Dest.Stores, go)
        SectionLabel(Icons.Outlined.Flag, stringResource(R.string.planning))
        NavRow(stringResource(R.string.goals), stringResource(R.string.goals_sub), Icons.Outlined.Flag, Dest.Goals, go)
        NavRow(stringResource(R.string.pending), stringResource(R.string.pending_sub), Icons.Outlined.History, Dest.Pending, go)
        SectionLabel(Icons.Outlined.Category, stringResource(R.string.organization))
        NavRow(stringResource(R.string.categories), stringResource(R.string.categories_sub), Icons.Outlined.Category, Dest.Categories, go)
        NavRow(stringResource(R.string.tags), stringResource(R.string.tags_sub), Icons.Outlined.Label, Dest.Tags, go)
        NavRow(stringResource(R.string.trash), stringResource(R.string.trash_sub), Icons.Outlined.Delete, Dest.Trash, go)
        SectionLabel(Icons.Outlined.Tune, stringResource(R.string.system))
        NavRow(stringResource(R.string.server_sync), stringResource(R.string.server_sync_sub), Icons.Outlined.Cloud, Dest.Sync, go)
        NavRow(stringResource(R.string.appearance), stringResource(R.string.appearance_sub), Icons.Outlined.Palette, Dest.Appearance, go)
        NavRow(stringResource(R.string.preferences), stringResource(R.string.preferences_sub), Icons.Outlined.Tune, Dest.Prefs, go)
        NavRow(stringResource(R.string.data_management), stringResource(R.string.data_management_sub), Icons.Outlined.Backup, Dest.Data, go)
        NavRow(stringResource(R.string.about), stringResource(R.string.about_sub), Icons.Outlined.Info, Dest.About, go)
        Spacer(Modifier.height(8.dp))
        Button(
            logout,
            Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = ButtonDefaults.buttonColors(MaterialTheme.colorScheme.error, MaterialTheme.colorScheme.onError),
        ) { Text(stringResource(R.string.disconnect)) }
        Text(
            stringResource(R.string.footer_made, BuildConfig.VERSION_NAME),
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}

@Composable
fun AboutScreen(state: AppState) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val checker = remember { AppUpdateChecker(context.applicationContext) }
    var checking by remember { mutableStateOf(false) }
    var info by remember { mutableStateOf<xyz.serverkherson.spends.updates.UpdateInfo?>(null) }
    var status by remember { mutableStateOf<String?>(null) }
    var progress by remember { mutableStateOf(0) }
    Column(
        Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text(stringResource(R.string.about_title), style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onBackground)
        Card(Modifier.fillMaxWidth(), RoundedCornerShape(22.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
            Column(Modifier.fillMaxWidth().padding(18.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                    Box(Modifier.size(52.dp).clip(RoundedCornerShape(16.dp)).background(MaterialTheme.colorScheme.primaryContainer), contentAlignment = Alignment.Center) {
                        Icon(Icons.Outlined.Wallet, null, tint = MaterialTheme.colorScheme.onPrimaryContainer)
                    }
                    Column(Modifier.weight(1f)) {
                        Text(stringResource(R.string.app_name), style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.onSurface)
                        Text(stringResource(R.string.app_tagline), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
                InfoRow(stringResource(R.string.about_app), stringResource(R.string.app_name))
                InfoRow(stringResource(R.string.about_version, BuildConfig.VERSION_NAME, BuildConfig.VERSION_CODE), BuildConfig.BUILD_TYPE)
                InfoRow(stringResource(R.string.about_package), context.packageName)
                InfoRow(stringResource(R.string.about_build), stringResource(R.string.about_build_value))
                InfoRow(stringResource(R.string.about_endpoint), state.session.endpoint.ifBlank { "—" })
                InfoRow(stringResource(R.string.about_account), state.session.email.ifBlank { "—" })
            }
        }
        Card(Modifier.fillMaxWidth(), RoundedCornerShape(22.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
            Column(Modifier.fillMaxWidth().padding(18.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text(stringResource(R.string.about_updates), style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.onSurface)
                val available = info
                Text(
                    status ?: if (available != null && available.version_code > BuildConfig.VERSION_CODE) {
                        stringResource(R.string.update_available, available.version_name)
                    } else {
                        ""
                    },
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    style = MaterialTheme.typography.bodySmall,
                )
                Button(
                    onClick = {
                        if (available != null && available.version_code > BuildConfig.VERSION_CODE) {
                            scope.launch {
                                status = context.getString(R.string.update_downloading, progress)
                                val file = checker.downloadApk(state.session.endpoint, state.session.token, available) { progress = it }
                                if (file != null) {
                                    AppUpdateChecker.postInstallNotification(context, file, available.version_name)
                                    checker.install(file)
                                    status = context.getString(R.string.update_downloading, 100)
                                } else {
                                    status = context.getString(R.string.update_failed)
                                }
                            }
                        } else {
                            scope.launch {
                                checking = true
                                status = context.getString(R.string.checking_updates)
                                val check = checker.checkForUpdate(state.session.endpoint, state.session.token)
                                info = check.info
                                status = when (check.outcome) {
                                    UpdateOutcome.UPDATE_AVAILABLE -> context.getString(R.string.update_available, check.info?.version_name.orEmpty())
                                    UpdateOutcome.UP_TO_DATE -> context.getString(R.string.update_up_to_date)
                                    UpdateOutcome.NOT_CONNECTED -> context.getString(R.string.update_not_connected)
                                    UpdateOutcome.ERROR -> context.getString(R.string.update_failed)
                                }
                                checking = false
                            }
                        }
                    },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(16.dp),
                    enabled = !checking,
                ) {
                    if (checking) {
                        CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary)
                        Spacer(Modifier.width(8.dp))
                    }
                    Text(
                        when {
                            checking -> stringResource(R.string.checking_updates)
                            info != null && info!!.version_code > BuildConfig.VERSION_CODE -> stringResource(R.string.update_download)
                            else -> stringResource(R.string.check_updates)
                        }
                    )
                }
            }
        }
    }
}

@Composable
private fun InfoRow(label: String, value: String) {
    Column(Modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(2.dp)) {
        Text(label, style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
        Text(value, color = MaterialTheme.colorScheme.onSurface, maxLines = 2, overflow = TextOverflow.Ellipsis)
    }
}

@Composable
fun GoalsScreen(state: AppState, model: SpendsViewModel) {
    var name by remember { mutableStateOf("") }
    var target by remember { mutableStateOf("") }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text(stringResource(R.string.goals_title), style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onBackground)
        OutlinedTextField(name, { name = it }, Modifier.fillMaxWidth(), label = { Text(stringResource(R.string.name)) }, shape = RoundedCornerShape(16.dp))
        OutlinedTextField(target, { target = it }, Modifier.fillMaxWidth(), label = { Text(stringResource(R.string.target)) }, shape = RoundedCornerShape(16.dp))
        Button(
            {
                model.saveGoal(Goal(name = name, targetAmount = target.toDoubleOrNull() ?: 0.0, currency = state.snapshot.currency))
                name = ""
                target = ""
            },
            Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
        ) { Text(stringResource(R.string.add_goal)) }
        state.snapshot.goals.forEach { g ->
            Card(shape = RoundedCornerShape(18.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
                Column(Modifier.padding(16.dp)) {
                    Text(g.name, fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onSurface)
                    LinearProgressIndicator(
                        { (g.progressPercent / 100.0).toFloat().coerceIn(0f, 1f) },
                        Modifier.fillMaxWidth().height(8.dp).clip(RoundedCornerShape(99.dp)),
                    )
                    Text("${money(g.currentAmount, g.currency)} / ${money(g.targetAmount, g.currency)}", color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Row {
                        TextButton({ model.contributeGoal(g.id, "10") }) { Text("+10") }
                        TextButton({ model.deleteGoal(g.id) }) { Text(stringResource(R.string.delete)) }
                    }
                }
            }
        }
    }
}

@Composable
fun TagsScreen(state: AppState, model: SpendsViewModel) {
    var name by remember { mutableStateOf("") }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text(stringResource(R.string.tags_title), style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onBackground)
        OutlinedTextField(name, { name = it }, Modifier.fillMaxWidth(), label = { Text(stringResource(R.string.tag)) }, shape = RoundedCornerShape(16.dp))
        Button({ model.saveTag(name, "#4F46E5"); name = "" }, shape = RoundedCornerShape(16.dp)) { Text(stringResource(R.string.add)) }
        state.snapshot.tags.forEach { t ->
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Text("${t.name} · ${t.usageCount}", color = MaterialTheme.colorScheme.onSurface)
                TextButton({ model.deleteTag(t.id) }) { Text(stringResource(R.string.delete)) }
            }
        }
    }
}

@Composable
fun TrashScreen(state: AppState, model: SpendsViewModel) {
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text(stringResource(R.string.trash_title), style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onBackground)
        if (state.extras.trash.isEmpty()) EmptyCard(stringResource(R.string.trash_empty_title), stringResource(R.string.trash_empty_body))
        state.extras.trash.forEach { e ->
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text("${e.store} ${money(e.amount, state.snapshot.currency)}", color = MaterialTheme.colorScheme.onSurface)
                TextButton({ model.restoreTrash(e) }) { Text(stringResource(R.string.restore)) }
            }
        }
        if (state.extras.trash.isNotEmpty()) OutlinedButton(model::emptyTrash) { Text(stringResource(R.string.empty_trash)) }
    }
}

@Composable
fun PendingScreen(state: AppState, model: SpendsViewModel) {
    val context = LocalContext.current
    var editing by remember { mutableStateOf<PendingCapture?>(null) }
    val picker = androidx.activity.compose.rememberLauncherForActivityResult(androidx.activity.result.contract.ActivityResultContracts.StartActivityForResult()) { result ->
        if (result.resultCode == android.app.Activity.RESULT_OK) {
            val name = result.data?.getStringExtra(xyz.serverkherson.spends.PickStoreActivity.EXTRA_STORE).orEmpty()
            editing?.let { model.updatePendingStore(it, name) }
        }
        editing = null
    }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text(stringResource(R.string.pending_title), style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onBackground)
        Text(stringResource(R.string.pending_desc), color = MaterialTheme.colorScheme.onSurfaceVariant)
        NotificationAccessCard(state)
        if (state.extras.pending.isEmpty()) EmptyCard(stringResource(R.string.pending_empty_title), stringResource(R.string.pending_empty_body))
        state.extras.pending.forEach { p ->
            Card(shape = RoundedCornerShape(20.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
                Column(Modifier.fillMaxWidth().padding(16.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(p.store, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f), color = MaterialTheme.colorScheme.onSurface)
                        Text(money(p.amount, state.snapshot.currency), fontWeight = FontWeight.Bold, color = SpendsColors.expense())
                    }
                    Text("${p.date} · ${stringResource(R.string.pending_auto)}", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    if (p.raw.isNotBlank()) {
                        Text(
                            p.raw,
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f),
                            maxLines = 3,
                            overflow = TextOverflow.Ellipsis,
                        )
                    }
                    Row {
                        TextButton({ model.confirmPending(p) }) { Text(stringResource(R.string.keep)) }
                        TextButton({
                            editing = p
                            picker.launch(android.content.Intent(context, xyz.serverkherson.spends.PickStoreActivity::class.java))
                        }) { Text(stringResource(R.string.pending_choose_store)) }
                        TextButton({ model.dismissPending(p) }) { Text(stringResource(R.string.skip)) }
                    }
                }
            }
        }
    }
}

@Composable
private fun NotificationAccessCard(state: AppState) {
    val context = LocalContext.current
    var enabled by remember { mutableStateOf(NotificationAccess.isEnabled(context)) }
    val launcher = androidx.activity.compose.rememberLauncherForActivityResult(androidx.activity.result.contract.ActivityResultContracts.StartActivityForResult()) {
        enabled = NotificationAccess.isEnabled(context)
    }
    Card(shape = RoundedCornerShape(20.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
        Column(Modifier.fillMaxWidth().padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Outlined.Notifications, null, tint = MaterialTheme.colorScheme.primary)
                Spacer(Modifier.width(10.dp))
                Column(Modifier.weight(1f)) {
                    Text(stringResource(R.string.notifications_title), fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onSurface)
                    Text(
                        stringResource(if (enabled) R.string.notifications_status_on else R.string.notifications_status_off),
                        style = MaterialTheme.typography.bodySmall,
                        color = if (enabled) SpendsColors.income() else SpendsColors.expense(),
                    )
                }
            }
            Text(
                stringResource(if (enabled) R.string.notifications_info else R.string.notifications_disabled_hint),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            if (!enabled) {
                Button(
                    { launcher.launch(android.content.Intent(android.provider.Settings.ACTION_NOTIFICATION_LISTENER_SETTINGS)) },
                    Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(14.dp),
                ) { Text(stringResource(R.string.notifications_enable)) }
            }
            Text(
                stringResource(R.string.notifications_pending),
                style = MaterialTheme.typography.labelLarge,
                color = MaterialTheme.colorScheme.primary,
            )
            if (state.extras.pending.isEmpty()) {
                Text(stringResource(R.string.notifications_empty), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}

@Composable
fun PrefsScreen(state: AppState, model: SpendsViewModel) {
    var budget by remember(state.snapshot.budget) { mutableStateOf(state.snapshot.budget.toString()) }
    var currency by remember(state.snapshot.currency) { mutableStateOf(state.snapshot.currency) }
    var incomes by remember(state.snapshot.flags.showIncomes) { mutableStateOf(state.snapshot.flags.showIncomes) }
    var trend by remember(state.snapshot.flags.showTrend) { mutableStateOf(state.snapshot.flags.showTrend) }
    var trim by remember(state.snapshot.flags.trimEmptyMonths) { mutableStateOf(state.snapshot.flags.trimEmptyMonths) }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text(stringResource(R.string.preferences_title), style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onBackground)
        OutlinedTextField(budget, { budget = it }, Modifier.fillMaxWidth(), label = { Text(stringResource(R.string.monthly_budget)) }, shape = RoundedCornerShape(16.dp))
        OutlinedTextField(currency, { currency = it.uppercase() }, Modifier.fillMaxWidth(), label = { Text(stringResource(R.string.currency)) }, shape = RoundedCornerShape(16.dp))
        SwitchRow(stringResource(R.string.show_incomes), incomes) { incomes = it }
        SwitchRow(stringResource(R.string.show_trend), trend) { trend = it }
        SwitchRow(stringResource(R.string.trim_empty_months), trim) { trim = it }
        Button({ model.saveSettings(budget, currency, incomes, trend, trim) }, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp)) {
            Text(stringResource(R.string.save))
        }
        Text(stringResource(R.string.prefs_note), color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall)
    }
}

@Composable
private fun SwitchRow(label: String, checked: Boolean, onChange: (Boolean) -> Unit) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
        Text(label, color = MaterialTheme.colorScheme.onSurface, modifier = Modifier.weight(1f))
        Switch(checked, onChange)
    }
}

@Composable
fun DataScreen(state: AppState, model: SpendsViewModel) {
    var name by remember { mutableStateOf("mobile") }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text(stringResource(R.string.data_title), style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onBackground)
        OutlinedTextField(name, { name = it }, Modifier.fillMaxWidth(), label = { Text(stringResource(R.string.backup_name)) }, shape = RoundedCornerShape(16.dp))
        Button({ model.saveBackup(name) }, shape = RoundedCornerShape(16.dp)) { Text(stringResource(R.string.save_backup)) }
        state.backups.forEach { b ->
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Text(b.name, modifier = Modifier.weight(1f), maxLines = 1, overflow = TextOverflow.Ellipsis, color = MaterialTheme.colorScheme.onSurface)
                TextButton({ model.restoreBackup(b.name) }) { Text(stringResource(R.string.restore)) }
                TextButton({ model.deleteBackup(b.name) }) { Text(stringResource(R.string.delete)) }
            }
        }
    }
}

@Composable
fun SyncScreen(state: AppState, model: SpendsViewModel, onLogs: () -> Unit) {
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text(stringResource(R.string.sync_title), style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onBackground)
        ConnectionCard(state, model)
        Text(state.session.endpoint, color = MaterialTheme.colorScheme.onSurfaceVariant)
        val last = if (state.snapshot.syncedAt == 0L) "—" else java.text.DateFormat.getDateTimeInstance().format(java.util.Date(state.snapshot.syncedAt))
        Text(stringResource(R.string.last_sync, last), color = MaterialTheme.colorScheme.onSurface)
        Button(onLogs, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp)) { Text(stringResource(R.string.logs)) }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TransactionSheet(kind: String, onDismiss: () -> Unit, saveExpense: (String, String, String) -> Unit, saveIncome: (String, String) -> Unit) {
    val context = LocalContext.current
    var date by remember { mutableStateOf(LocalDate.now().toString()) }
    var merchant by remember { mutableStateOf("") }
    var amount by remember { mutableStateOf("") }
    val pickStore = androidx.activity.compose.rememberLauncherForActivityResult(androidx.activity.result.contract.ActivityResultContracts.StartActivityForResult()) { result ->
        if (result.resultCode == android.app.Activity.RESULT_OK) merchant = result.data?.getStringExtra(xyz.serverkherson.spends.PickStoreActivity.EXTRA_STORE).orEmpty()
    }
    ModalBottomSheet(onDismissRequest = onDismiss, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp)) {
        Column(Modifier.padding(horizontal = 24.dp).padding(bottom = 36.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Text(stringResource(if (kind == "income") R.string.new_income else R.string.new_expense), style = MaterialTheme.typography.headlineMedium)
            OutlinedTextField(date, { date = it }, Modifier.fillMaxWidth(), label = { Text(stringResource(R.string.date_label)) }, shape = RoundedCornerShape(16.dp), singleLine = true)
            if (kind != "income") {
                Card(
                    Modifier.fillMaxWidth().clickable { pickStore.launch(android.content.Intent(context, xyz.serverkherson.spends.PickStoreActivity::class.java)) },
                    RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surfaceVariant),
                ) {
                    Row(Modifier.fillMaxWidth().padding(16.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                        Text(merchant.ifBlank { stringResource(R.string.choose_store) }, fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onSurface)
                        Icon(Icons.Outlined.Storefront, null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
            }
            OutlinedTextField(amount, { amount = it }, Modifier.fillMaxWidth(), label = { Text(stringResource(R.string.amount_label)) }, shape = RoundedCornerShape(16.dp), singleLine = true)
            Button(
                { if (kind == "income") saveIncome(date, amount) else saveExpense(date, merchant, amount) },
                Modifier.fillMaxWidth().height(54.dp),
                shape = RoundedCornerShape(18.dp),
            ) { Text(stringResource(R.string.save)) }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SearchSheet(state: AppState, onDismiss: () -> Unit, onOpen: (Expense) -> Unit) {
    var query by remember { mutableStateOf("") }
    val results = remember(query, state.snapshot.expenses) {
        val needle = query.trim()
        if (needle.isBlank()) emptyList()
        else state.snapshot.expenses.filter { expense ->
            expense.store.contains(needle, ignoreCase = true) ||
                expense.date.contains(needle) ||
                expense.amount.toString().contains(needle)
        }.sortedByDescending { it.date }.take(60)
    }
    ModalBottomSheet(onDismissRequest = onDismiss, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp)) {
        Column(Modifier.padding(horizontal = 20.dp).padding(bottom = 28.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Text(stringResource(R.string.search_title), style = MaterialTheme.typography.headlineMedium)
            OutlinedTextField(
                query,
                { query = it },
                Modifier.fillMaxWidth(),
                leadingIcon = { Icon(Icons.Outlined.Search, null) },
                placeholder = { Text(stringResource(R.string.search_hint)) },
                shape = RoundedCornerShape(16.dp),
                singleLine = true,
            )
            if (query.isNotBlank() && results.isEmpty()) {
                Text(stringResource(R.string.search_empty), color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            LazyColumn(Modifier.fillMaxWidth().height(360.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                items(results, key = { "${it.date}-${it.store}-${it.amount}-${it.signature}" }) { expense ->
                    ExpenseRow(expense, state.snapshot.currency, state.snapshot.stores.firstOrNull { it.name == expense.store }?.photoUrl.orEmpty()) {
                        onOpen(expense)
                    }
                }
            }
        }
    }
}

@Composable
fun DetailScreen(title: String, onBack: () -> Unit, content: @Composable () -> Unit) {
    Column(Modifier.fillMaxSize().background(canvasBrush())) {
        Row(Modifier.padding(8.dp), verticalAlignment = Alignment.CenterVertically) {
            IconButton(onBack) { Icon(Icons.AutoMirrored.Outlined.ArrowBack, stringResource(R.string.nav_home)) }
            Text(title, style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.onBackground)
        }
        Box(Modifier.weight(1f).fillMaxWidth()) { content() }
    }
}

@Composable
fun SpeedDial(onPick: (String) -> Unit) {
    Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(10.dp)) {
        MiniFab(stringResource(R.string.new_income), Icons.Outlined.TrendingUp, SpendsColors.income()) { onPick("income") }
        MiniFab(stringResource(R.string.new_expense), Icons.Outlined.TrendingDown, SpendsColors.expense()) { onPick("expense") }
        MiniFab("OCR", Icons.Outlined.CameraAlt, MaterialTheme.colorScheme.primary) { onPick("ocr") }
    }
}

@Composable
private fun MiniFab(label: String, icon: ImageVector, color: Color, onClick: () -> Unit) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(label, style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurface)
        FloatingActionButton(
            onClick,
            Modifier.size(48.dp),
            shape = CircleShape,
            containerColor = color,
            contentColor = Color.White,
        ) { Icon(icon, label) }
    }
}

@Composable
fun SpendsNavBar(tab: Int, onTab: (Int) -> Unit, onAdd: () -> Unit) {
    // A Box instead of Surface: Surface clips its children, which used to cut off the
    // protruding add button at the top edge of the bar.
    Box(Modifier.fillMaxWidth().shadow(16.dp, RectangleShape, clip = false)) {
        Box(
            Modifier.fillMaxWidth().navigationBarsPadding().height(80.dp)
                .background(MaterialTheme.colorScheme.surface),
        )
        Row(
            Modifier.fillMaxWidth().navigationBarsPadding().height(80.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            NavSlot(stringResource(R.string.nav_home), Icons.Outlined.Wallet, tab == 0, Modifier.weight(1f)) { onTab(0) }
            NavSlot(stringResource(R.string.nav_stats), Icons.Outlined.Insights, tab == 1, Modifier.weight(1f)) { onTab(1) }
            Box(Modifier.weight(1.05f), contentAlignment = Alignment.Center) {
                // Square, floating add button that breaks out of the bar.
                Box(
                    Modifier
                        .offset(y = (-24).dp)
                        .size(58.dp)
                        .clip(RoundedCornerShape(18.dp))
                        .background(MaterialTheme.colorScheme.primary)
                        .clickable(onClick = onAdd),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(Icons.Outlined.Add, stringResource(R.string.nav_add), tint = MaterialTheme.colorScheme.onPrimary)
                }
            }
            NavSlot(stringResource(R.string.nav_stores), Icons.Outlined.Storefront, tab == 3, Modifier.weight(1f)) { onTab(3) }
            NavSlot(stringResource(R.string.nav_settings), Icons.Outlined.Settings, tab == 2, Modifier.weight(1f)) { onTab(2) }
        }
    }
}

/** Bottom sheet shown from the add button: income, expense or receipt. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AddActionSheet(onDismiss: () -> Unit, onPick: (String) -> Unit) {
    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp),
    ) {
        Column(Modifier.fillMaxWidth().padding(horizontal = 20.dp).padding(bottom = 32.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Text(stringResource(R.string.add_action_title), style = MaterialTheme.typography.headlineMedium)
            AddActionRow(Icons.Outlined.TrendingDown, stringResource(R.string.new_expense), stringResource(R.string.amount_label), SpendsColors.expense()) { onPick("expense") }
            AddActionRow(Icons.Outlined.TrendingUp, stringResource(R.string.new_income), stringResource(R.string.incomes), SpendsColors.income()) { onPick("income") }
            AddActionRow(Icons.Outlined.CameraAlt, stringResource(R.string.add_receipt), "OCR", MaterialTheme.colorScheme.primary) { onPick("ocr") }
        }
    }
}

@Composable
private fun AddActionRow(icon: ImageVector, title: String, subtitle: String, color: Color, onClick: () -> Unit) {
    Card(
        Modifier.fillMaxWidth().clickable(onClick = onClick),
        RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surfaceVariant),
    ) {
        Row(Modifier.fillMaxWidth().padding(16.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
            Box(Modifier.size(44.dp).clip(RoundedCornerShape(14.dp)).background(color.copy(alpha = 0.18f)), contentAlignment = Alignment.Center) {
                Icon(icon, null, tint = color)
            }
            Column(Modifier.weight(1f)) {
                Text(title, style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.onSurface)
                Text(subtitle, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            Icon(Icons.Outlined.ChevronRight, null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}

@Composable
private fun NavSlot(label: String, icon: ImageVector, selected: Boolean, modifier: Modifier, onClick: () -> Unit) {
    val color = if (selected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurface.copy(alpha = 0.68f)
    Column(
        modifier.clickable(onClick = onClick).padding(vertical = 8.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(2.dp),
    ) {
        Icon(icon, label, tint = color)
        Text(label, style = MaterialTheme.typography.labelSmall, color = color, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
}

@Composable
fun ErrorDialog(message: String?, dismiss: () -> Unit) {
    if (message != null) {
        AlertDialog(
            dismiss,
            title = { Text(stringResource(R.string.something_went_wrong)) },
            text = { Text(message) },
            confirmButton = { TextButton(dismiss) { Text(stringResource(R.string.ok)) } },
        )
    }
}

private fun prettyDate(raw: String): String = try {
    LocalDate.parse(raw).format(DateTimeFormatter.ofPattern("EEEE, d MMM", Locale.getDefault()))
} catch (_: DateTimeParseException) {
    raw
}
