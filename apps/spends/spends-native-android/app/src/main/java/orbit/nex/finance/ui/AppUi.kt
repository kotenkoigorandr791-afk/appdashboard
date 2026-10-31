package orbit.nex.finance.ui

import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
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
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.Add
import androidx.compose.material.icons.outlined.Autorenew
import androidx.compose.material.icons.filled.CloudDone
import androidx.compose.material.icons.outlined.Backup
import androidx.compose.material.icons.outlined.CalendarMonth
import androidx.compose.material.icons.outlined.CameraAlt
import androidx.compose.material.icons.outlined.Check
import androidx.compose.material.icons.outlined.Category
import androidx.compose.material.icons.outlined.ChevronRight
import androidx.compose.material.icons.outlined.Cloud
import androidx.compose.material.icons.outlined.Delete
import androidx.compose.material.icons.outlined.Edit
import androidx.compose.material.icons.outlined.Flag
import androidx.compose.material.icons.outlined.HelpOutline
import androidx.compose.material.icons.outlined.AccountBalanceWallet
import androidx.compose.material.icons.outlined.ReceiptLong
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.Sort
import androidx.compose.material.icons.outlined.History
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material.icons.outlined.Insights
import androidx.compose.material.icons.outlined.Label
import androidx.compose.material.icons.outlined.Notifications
import androidx.compose.material.icons.outlined.Palette
import androidx.compose.material.icons.outlined.PieChart
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material.icons.outlined.Settings
import androidx.compose.material.icons.outlined.Smartphone
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
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.RectangleShape
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import android.net.Uri
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import java.time.Instant
import java.time.ZoneOffset
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.time.format.DateTimeParseException
import java.util.Locale
import kotlinx.coroutines.launch
import orbit.nex.finance.AppState
import orbit.nex.finance.BuildConfig
import orbit.nex.finance.NotificationAccess
import orbit.nex.finance.R
import orbit.nex.finance.SpendsViewModel
import orbit.nex.finance.data.BackupEntry
import orbit.nex.finance.data.Expense
import orbit.nex.finance.data.Goal
import orbit.nex.finance.data.PendingCapture
import orbit.nex.finance.data.Store
import orbit.nex.finance.data.Tag
import orbit.nex.finance.updates.AppUpdateChecker
import orbit.nex.finance.updates.UpdateInfo
import orbit.nex.finance.updates.UpdateOutcome

enum class Dest {
    Home, Stats, Stores, Settings, Insights, CashFlow, Categories, Calendar, Wrapped, NetWorth,
    Goals, Tags, Trash, Pending, Prefs, Data, Sync, Appearance, Transaction, Ocr, Expense, Store, About,
    Subscriptions, Updates,
}

/** Screens opened under [DetailScreen] already carry the title in the top bar. */
internal val LocalHeadingHidden = androidx.compose.runtime.staticCompositionLocalOf { false }

@Composable
fun PageHeading(res: Int) {
    if (LocalHeadingHidden.current) return
    Text(stringResource(res), style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onBackground)
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
    // Capital is cumulative; the in/out tiles and the budget are for the current month only.
    val allSpent = state.snapshot.expenses.sumOf { it.amount }
    val allEarned = state.snapshot.incomes.sumOf { it.amount }
    val net = allEarned - allSpent
    val month = LocalDate.now().toString().take(7)
    val spent = state.snapshot.expenses.filter { it.date.startsWith(month) }.sumOf { it.amount }
    val earned = state.snapshot.incomes.filter { it.date.startsWith(month) }.sumOf { it.amount }
    val budget = state.snapshot.budget
    val ink = MaterialTheme.colorScheme.onPrimary
    Box(Modifier.fillMaxWidth().background(heroBrush(), RoundedCornerShape(28.dp)).padding(22.dp)) {
        Column {
            Text(stringResource(R.string.net_position), color = ink.copy(alpha = 0.75f), style = MaterialTheme.typography.labelLarge)
            Text(money(net, state.snapshot.currency), color = ink, style = MaterialTheme.typography.headlineLarge)
            Spacer(Modifier.height(16.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Column(
                    Modifier.weight(1f).clip(RoundedCornerShape(16.dp))
                        .background(HeroExpense).padding(12.dp),
                ) {
                    Text(stringResource(R.string.spent), color = Color.White.copy(alpha = 0.85f), style = MaterialTheme.typography.labelLarge)
                    Text(money(spent, state.snapshot.currency), color = Color.White, fontWeight = FontWeight.SemiBold)
                }
                Column(
                    Modifier.weight(1f).clip(RoundedCornerShape(16.dp))
                        .background(HeroIncome).padding(12.dp),
                ) {
                    Text(stringResource(R.string.cash_in), color = Color.White.copy(alpha = 0.88f), style = MaterialTheme.typography.labelLarge)
                    Text(money(earned, state.snapshot.currency), color = Color.White, fontWeight = FontWeight.SemiBold)
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
                // Green → amber → red: the filled part shows how much of the budget is gone.
                Box(
                    Modifier.fillMaxWidth().height(8.dp).clip(RoundedCornerShape(99.dp))
                        .background(Color.White.copy(alpha = 0.28f)),
                ) {
                    Box(
                        Modifier.fillMaxWidth(ratio).height(8.dp).clip(RoundedCornerShape(99.dp))
                            .background(
                                when {
                                    ratio >= 1f -> BudgetRed
                                    ratio >= .8f -> BudgetAmber
                                    else -> BudgetGreen
                                },
                            ),
                    )
                }
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
                SourceBadge(expense.entrySource)
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
    var ratesOpen by remember { mutableStateOf(false) }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        Text(stringResource(R.string.statistics), style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onBackground)
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
        ActionCard(Icons.Outlined.ShowChart, stringResource(R.string.rates_title), stringResource(R.string.rates_open)) { ratesOpen = true }
        Spacer(Modifier.height(24.dp))
    }
    if (heatmap) HeatmapModal(state) { heatmap = false }
    if (ratesOpen) RatesDialog(state) { ratesOpen = false }
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
        PageHeading(R.string.cash_flow_title)
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

private val HeroExpense = Color(0xFFC21919)
private val HeroIncome = Color(0xFF6BCC41)
private val BudgetGreen = Color(0xFF6BCC41)
private val BudgetAmber = Color(0xFFF2C230)
private val BudgetRed = Color(0xFFC21919)

/** Compact, consistently sized date picker used by all app date-selection flows. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AppDatePickerDialog(
    initialDateMillis: Long,
    onDismiss: () -> Unit,
    onConfirm: (Long) -> Unit,
    dismissLabel: String,
) {
    val picker = rememberDatePickerState(initialSelectedDateMillis = initialDateMillis)
    Dialog(
        onDismissRequest = onDismiss,
        properties = DialogProperties(usePlatformDefaultWidth = false),
    ) {
        Surface(
            modifier = Modifier.fillMaxWidth().padding(horizontal = 24.dp).widthIn(max = 480.dp),
            shape = RoundedCornerShape(28.dp),
            color = MaterialTheme.colorScheme.surface,
            tonalElevation = 6.dp,
        ) {
            Column(horizontalAlignment = Alignment.End, modifier = Modifier.heightIn(max = 520.dp)) {
                DatePicker(
                    picker,
                    modifier = Modifier.fillMaxWidth()
                        .padding(horizontal = 12.dp),
                    title = null,
                    headline = null,
                )
                Row(
                    modifier = Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 8.dp),
                    horizontalArrangement = Arrangement.End,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    TextButton(onClick = onDismiss) { Text(dismissLabel) }
                    TextButton(onClick = { picker.selectedDateMillis?.let(onConfirm); onDismiss() }) {
                        Text(stringResource(R.string.ok))
                    }
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
                name = category,
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
        PageHeading(R.string.categories)
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
                            Text(categoryLabel(row.name), modifier = Modifier.weight(1f), style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurface)
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
                    Text(categoryLabel(row.name).take(1), fontWeight = FontWeight.Bold, color = color)
                }
                Spacer(Modifier.width(12.dp))
                Column(Modifier.weight(1f)) {
                    Text(categoryLabel(row.name), style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.onSurface)
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
        PageHeading(R.string.calendar)
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
        PageHeading(R.string.insights)
        InsightCard(
            title = stringResource(R.string.spent),
            value = money(spent, state.snapshot.currency),
            caption = stringResource(R.string.insights_charges, state.snapshot.expenses.size),
            accent = SpendsColors.expense(),
        )
        if (top != null) {
            InsightCard(
                title = stringResource(R.string.insights_top, top.key, money(top.value, state.snapshot.currency)),
                value = top.key,
                caption = money(top.value, state.snapshot.currency),
            )
        }
        if (state.snapshot.budget > 0) {
            InsightCard(
                title = stringResource(R.string.monthly_budget),
                value = money(state.snapshot.budget - spent, state.snapshot.currency),
                caption = stringResource(R.string.insights_budget, money(state.snapshot.budget, state.snapshot.currency)),
                accent = if (state.snapshot.budget - spent >= 0) SpendsColors.income() else SpendsColors.expense(),
            )
        }
        InsightCard(
            title = stringResource(R.string.pending),
            value = stringResource(R.string.insights_waiting, state.extras.pending.size),
            caption = stringResource(R.string.insights_in_trash, state.extras.trash.size),
        )
    }
}

@Composable
private fun InsightCard(title: String, value: String, caption: String, accent: Color? = null) {
    Card(
        Modifier.fillMaxWidth(),
        RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(0.dp),
    ) {
        Column(
            Modifier.fillMaxWidth().border(1.dp, MaterialTheme.colorScheme.outlineVariant, RoundedCornerShape(20.dp)).padding(18.dp),
            verticalArrangement = Arrangement.spacedBy(6.dp),
        ) {
            Text(title, style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
            Text(value, style = MaterialTheme.typography.titleLarge, color = accent ?: MaterialTheme.colorScheme.onSurface)
            Text(caption, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}

@Composable
fun NetWorthScreen(state: AppState) {
    val incomes = state.snapshot.incomes.sumOf { it.amount }
    val expenses = state.snapshot.expenses.sumOf { it.amount }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        PageHeading(R.string.net_position)
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
        PageHeading(R.string.year_in_review)
        Box(Modifier.fillMaxWidth().background(heroBrush(), RoundedCornerShape(24.dp)).padding(22.dp)) {
            Column {
                Text("$year", color = MaterialTheme.colorScheme.onPrimary.copy(alpha = 0.75f), style = MaterialTheme.typography.labelLarge)
                Text(money(yearSpend, state.snapshot.currency), color = MaterialTheme.colorScheme.onPrimary, style = MaterialTheme.typography.headlineLarge)
            }
        }
        val yearExpenses = state.snapshot.expenses.filter { it.date.startsWith("$year") }
        val yearIncomes = state.snapshot.incomes.filter { it.date.startsWith("$year") }.sumOf { it.amount }
        // A real two-column table instead of one long line joined with separators.
        TableCard(
            title = stringResource(R.string.year_in_review_sub),
            rows = listOf(
                stringResource(R.string.expenses) to "−${money(yearSpend, state.snapshot.currency)}",
                stringResource(R.string.incomes) to "+${money(yearIncomes, state.snapshot.currency)}",
                stringResource(R.string.difference) to money(yearIncomes - yearSpend, state.snapshot.currency),
                stringResource(R.string.charges) to yearExpenses.size.toString(),
                stringResource(R.string.stores_title) to state.snapshot.stores.size.toString(),
                stringResource(R.string.goals_title) to state.snapshot.goals.size.toString(),
                stringResource(R.string.tags_title) to state.snapshot.tags.size.toString(),
            ),
        )
    }
}

/** Aligned label/value table used for yearly and summary numbers. */
@Composable
fun TableCard(title: String, rows: List<Pair<String, String>>) {
    Card(
        Modifier.fillMaxWidth(),
        RoundedCornerShape(22.dp),
        colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(0.dp),
    ) {
        Column(
            Modifier.fillMaxWidth().border(1.dp, MaterialTheme.colorScheme.outlineVariant, RoundedCornerShape(22.dp)).padding(18.dp),
            verticalArrangement = Arrangement.spacedBy(2.dp),
        ) {
            Text(title, style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.onSurface)
            Spacer(Modifier.height(8.dp))
            rows.forEachIndexed { index, (label, value) ->
                Row(
                    Modifier.fillMaxWidth().padding(vertical = 9.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text(
                        label,
                        modifier = Modifier.weight(1f),
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Text(
                        value,
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.SemiBold,
                        color = MaterialTheme.colorScheme.onSurface,
                        textAlign = TextAlign.End,
                    )
                }
                if (index != rows.lastIndex) {
                    Box(Modifier.fillMaxWidth().height(1.dp).background(MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.6f)))
                }
            }
        }
    }
}

private enum class StoreSort { DEFAULT, NAME_ASC, NAME_DESC, SPEND_DESC, SPEND_ASC }

@Composable
private fun storeSortLabel(sort: StoreSort): String = stringResource(
    when (sort) {
        StoreSort.DEFAULT -> R.string.sort_default
        StoreSort.NAME_ASC -> R.string.sort_name_asc
        StoreSort.NAME_DESC -> R.string.sort_name_desc
        StoreSort.SPEND_DESC -> R.string.sort_spend_desc
        StoreSort.SPEND_ASC -> R.string.sort_spend_asc
    },
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun StoresScreen(state: AppState, model: SpendsViewModel, onStore: (Store) -> Unit) {
    var add by remember { mutableStateOf(false) }
    var query by remember { mutableStateOf("") }
    var sort by remember { mutableStateOf(StoreSort.DEFAULT) }
    var sortOpen by remember { mutableStateOf(false) }
    val totals = remember(state.snapshot.expenses) {
        state.snapshot.expenses.groupBy { it.store }.mapValues { entry -> entry.value.sumOf { it.amount } }
    }
    val stores = remember(state.snapshot.stores, query, sort, totals) {
        val filtered = state.snapshot.stores.filter { query.isBlank() || it.name.contains(query.trim(), ignoreCase = true) }
        when (sort) {
            StoreSort.DEFAULT -> filtered
            StoreSort.NAME_ASC -> filtered.sortedBy { it.name.lowercase() }
            StoreSort.NAME_DESC -> filtered.sortedByDescending { it.name.lowercase() }
            StoreSort.SPEND_DESC -> filtered.sortedByDescending { totals[it.name] ?: 0.0 }
            StoreSort.SPEND_ASC -> filtered.sortedBy { totals[it.name] ?: 0.0 }
        }
    }
    Column(Modifier.fillMaxSize()) {
        Row(
            Modifier.fillMaxWidth().padding(start = 16.dp, end = 8.dp, top = 12.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            OutlinedTextField(
                query,
                { query = it },
                Modifier.weight(1f),
                placeholder = { Text(stringResource(R.string.search_hint)) },
                leadingIcon = { Icon(Icons.Outlined.Search, null) },
                shape = RoundedCornerShape(16.dp),
                singleLine = true,
            )
            IconButton({ sortOpen = true }) {
                Icon(Icons.Outlined.Sort, stringResource(R.string.sort), tint = MaterialTheme.colorScheme.primary)
            }
        }
        Box(Modifier.fillMaxSize()) {
            LazyVerticalGrid(
                GridCells.Fixed(2),
                Modifier.fillMaxSize(),
                contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
                horizontalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                if (stores.isEmpty()) {
                    item(span = { androidx.compose.foundation.lazy.grid.GridItemSpan(2) }) {
                        EmptyCard(stringResource(R.string.search_empty), stringResource(R.string.ledger_empty_body))
                    }
                }
                items(stores, key = { it.id.ifBlank { it.name } }) { store ->
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
            FloatingActionButton(
                { add = true },
                Modifier.align(Alignment.BottomEnd).padding(20.dp),
                shape = RoundedCornerShape(18.dp),
                containerColor = MaterialTheme.colorScheme.primary,
                contentColor = MaterialTheme.colorScheme.onPrimary,
            ) { Icon(Icons.Outlined.Add, stringResource(R.string.add_store)) }
        }
    }
    if (add) {
        AddStoreSheet(state, { add = false }) { name, category, photo ->
            model.saveStore(name, category, photo)
            add = false
        }
    }
    if (sortOpen) {
        AlertDialog(
            onDismissRequest = { sortOpen = false },
            title = { Text(stringResource(R.string.sort)) },
            text = {
                Column(Modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    StoreSort.values().forEach { option ->
                        Row(
                            Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp))
                                .clickable { sort = option; sortOpen = false }
                                .padding(vertical = 12.dp, horizontal = 8.dp),
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Text(storeSortLabel(option), Modifier.weight(1f), color = MaterialTheme.colorScheme.onSurface)
                            if (option == sort) Icon(Icons.Outlined.Check, null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(18.dp))
                        }
                    }
                }
            },
            confirmButton = { TextButton({ sortOpen = false }) { Text(stringResource(R.string.heatmap_close)) } },
        )
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
    var cropUri by remember { mutableStateOf<Uri?>(null) }
    var photoBitmap by remember { mutableStateOf<android.graphics.Bitmap?>(null) }
    var photoBytes by remember { mutableStateOf<ByteArray?>(null) }
    val pickPhoto = androidx.activity.compose.rememberLauncherForActivityResult(androidx.activity.result.contract.ActivityResultContracts.GetContent()) { uri ->
        if (uri != null) cropUri = uri
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
                            if (photoBitmap != null) {
                                Image(
                                    bitmap = photoBitmap!!.asImageBitmap(),
                                    contentDescription = null,
                                    modifier = Modifier.matchParentSize(),
                                    contentScale = ContentScale.Crop,
                                )
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
                        Text(stringResource(if (photoBitmap == null) R.string.store_photo_choose else R.string.store_photo_change))
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
    cropUri?.let { uri ->
        PhotoCropDialog(
            uri = uri,
            onCancel = { cropUri = null },
            onCropped = { bytes ->
                photoBytes = bytes
                photoBitmap = bytes?.let { android.graphics.BitmapFactory.decodeByteArray(it, 0, it.size) }
                cropUri = null
            },
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
fun ExpenseDetailScreen(state: AppState, expense: Expense, model: SpendsViewModel, onTrash: () -> Unit) {
    val store = state.snapshot.stores.firstOrNull { it.name == expense.store }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Card(Modifier.fillMaxWidth(), RoundedCornerShape(24.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
            Column(
                Modifier.fillMaxWidth().padding(20.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                RemotePhoto(store?.photoUrl.orEmpty(), expense.store.take(1).uppercase(), round = false, size = 88.dp)
                if (!LocalHeadingHidden.current) {
                    Text(expense.store, style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onSurface)
                }
                Text(
                    store?.category?.takeIf { it.isNotBlank() }?.let { categoryLabel(it) } ?: stringResource(R.string.store_fallback),
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
        StatCard(stringResource(R.string.amount), "−${money(expense.amount, state.snapshot.currency)}", SpendsColors.expense())
        Card(Modifier.fillMaxWidth(), RoundedCornerShape(20.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
            Column(Modifier.fillMaxWidth().padding(18.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text(weekdayLabel(expense.date), style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                Text(longDateLabel(expense.date), style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.onSurface)
            }
        }
        SourceBadgeCard(expense.entrySource)
        ExpenseTagsSection(state, expense, model)
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
                if (!LocalHeadingHidden.current) {
                    Text(store.name, style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onSurface)
                }
                Text(categoryLabel(store.category), color = MaterialTheme.colorScheme.onSurfaceVariant)
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

/** Name only; the full account details open in their own window. */
@Composable
private fun ProfileCard(state: AppState) {
    var open by remember { mutableStateOf(false) }
    Card(
        Modifier.fillMaxWidth().clickable { open = true },
        RoundedCornerShape(22.dp),
        colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(0.dp),
    ) {
        Row(Modifier.fillMaxWidth().padding(16.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
            RemotePhoto(state.session.imageUrl, state.session.name.take(1).uppercase(), round = true, size = 56.dp, person = true)
            Text(
                state.session.name.ifBlank { stringResource(R.string.paired_account) },
                fontWeight = FontWeight.SemiBold,
                style = MaterialTheme.typography.titleMedium,
                color = MaterialTheme.colorScheme.onSurface,
                modifier = Modifier.weight(1f),
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Icon(Icons.Outlined.ChevronRight, null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
    if (open) {
        val parts = state.session.name.trim().split(Regex("\\s+"), limit = 2)
        val firstName = parts.firstOrNull().orEmpty()
        val lastName = parts.getOrNull(1).orEmpty()
        Dialog(onDismissRequest = { open = false }) {
            Surface(shape = RoundedCornerShape(28.dp), color = MaterialTheme.colorScheme.surface, tonalElevation = 2.dp) {
                Column(Modifier.fillMaxWidth().padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        RemotePhoto(state.session.imageUrl, state.session.name.take(1).uppercase(), round = true, size = 52.dp, person = true)
                        Column(Modifier.weight(1f)) {
                            Text(stringResource(R.string.profile_details), style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.onSurface)
                            Text(state.session.name.ifBlank { stringResource(R.string.paired_account) }, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }
                    InfoBox(stringResource(R.string.profile_first_name), firstName.ifBlank { "—" })
                    InfoBox(stringResource(R.string.profile_last_name), lastName.ifBlank { "—" })
                    InfoBox(stringResource(R.string.profile_email), state.session.email.ifBlank { "—" })
                    InfoBox(stringResource(R.string.profile_server), state.session.endpoint.ifBlank { "—" })
                    Button({ open = false }, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp)) {
                        Text(stringResource(R.string.heatmap_close))
                    }
                }
            }
        }
    }
}

@Composable
fun SettingsHub(state: AppState, go: (Dest) -> Unit, logout: () -> Unit) {
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Card(Modifier.fillMaxWidth(), RoundedCornerShape(22.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.primaryContainer)) {
            Row(Modifier.padding(18.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                Image(
                    painterResource(R.drawable.nex_finance_icon),
                    contentDescription = stringResource(R.string.app_name),
                    modifier = Modifier.size(52.dp).clip(RoundedCornerShape(14.dp)),
                    contentScale = ContentScale.Crop,
                )
                Column {
                    Text(stringResource(R.string.app_name), style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onPrimaryContainer)
                }
            }
        }
        Card(
            modifier = Modifier.fillMaxWidth().clickable { go(Dest.Updates) },
            shape = RoundedCornerShape(20.dp),
            colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface),
        ) {
            Row(
                Modifier.fillMaxWidth().padding(16.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(14.dp),
            ) {
                Icon(Icons.Outlined.Autorenew, null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(26.dp))
                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
                    Text(stringResource(R.string.update_settings_title), style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.onSurface)
                    Text(stringResource(R.string.update_settings_subtitle), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                Icon(Icons.Outlined.ChevronRight, null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
        Text(stringResource(R.string.settings), style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.onBackground)
        ProfileCard(state)
        SectionLabel(Icons.Outlined.Insights, stringResource(R.string.overview))
        NavRow(stringResource(R.string.notifications_title), stringResource(R.string.notifications_sub), Icons.Outlined.Notifications, Dest.Pending, go)
        NavRow(stringResource(R.string.insights), stringResource(R.string.insights_sub), Icons.Outlined.Insights, Dest.Insights, go)
        NavRow(stringResource(R.string.stores_title), stringResource(R.string.categories_sub), Icons.Outlined.Storefront, Dest.Stores, go)
        SectionLabel(Icons.Outlined.Flag, stringResource(R.string.planning))
        NavRow(stringResource(R.string.goals), stringResource(R.string.goals_sub), Icons.Outlined.Flag, Dest.Goals, go)
        NavRow(stringResource(R.string.subscriptions_title), stringResource(R.string.subscriptions_sub), Icons.Outlined.Autorenew, Dest.Subscriptions, go)
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
    Column(
        Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        PageHeading(R.string.about_title)
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
                InfoBox(stringResource(R.string.about_app), stringResource(R.string.app_name))
                InfoBox(stringResource(R.string.about_version_label), "${BuildConfig.VERSION_NAME} (${BuildConfig.VERSION_CODE})")
                InfoBox(stringResource(R.string.about_updated), updateDate(context))
                InfoBox(stringResource(R.string.about_endpoint), state.session.endpoint.ifBlank { "—" })
                InfoBox(stringResource(R.string.about_account), state.session.email.ifBlank { "—" })
            }
        }
    }
}

@Composable
fun UpdatesScreen(state: AppState) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val checker = remember { AppUpdateChecker(context.applicationContext) }
    var checking by remember { mutableStateOf(false) }
    var downloading by remember { mutableStateOf(false) }
    var info by remember { mutableStateOf<UpdateInfo?>(null) }
    var status by remember { mutableStateOf<String?>(null) }
    var progress by remember { mutableStateOf(0) }

    fun checkUpdates() {
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

    LaunchedEffect(state.session.token) { checkUpdates() }

    Column(
        Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        PageHeading(R.string.update_settings_title)
        Card(Modifier.fillMaxWidth(), RoundedCornerShape(20.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
            Column(Modifier.fillMaxWidth().padding(18.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text(stringResource(R.string.about_version_label), style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                Text("${BuildConfig.VERSION_NAME} (${BuildConfig.VERSION_CODE})", style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.onSurface)
                Text(
                    status.orEmpty(),
                    color = if (info != null) Color(0xFF16834A) else MaterialTheme.colorScheme.onSurfaceVariant,
                    style = MaterialTheme.typography.bodyMedium,
                )
                info?.release_notes?.takeIf { it.isNotBlank() }?.let {
                    Text(it.take(500), color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall, maxLines = 6, overflow = TextOverflow.Ellipsis)
                }
                Button(
                    onClick = {
                        val available = info
                        if (available == null) {
                            checkUpdates()
                        } else {
                            scope.launch {
                                downloading = true
                                status = context.getString(R.string.update_downloading, progress)
                                val file = checker.downloadApk(state.session.token, available) { progress = it }
                                if (file != null) {
                                    checker.install(file)
                                } else {
                                    status = context.getString(R.string.update_failed)
                                }
                                downloading = false
                            }
                        }
                    },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(16.dp),
                    enabled = !checking && !downloading,
                ) {
                    if (checking || downloading) {
                        CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary)
                        Spacer(Modifier.width(8.dp))
                    }
                    Text(
                        when {
                            checking -> stringResource(R.string.checking_updates)
                            downloading -> stringResource(R.string.update_downloading, progress)
                            info != null -> stringResource(R.string.update_download)
                            else -> stringResource(R.string.check_updates)
                        },
                    )
                }
                if (info != null) {
                    TextButton(onClick = { info = null; checkUpdates() }, modifier = Modifier.fillMaxWidth()) {
                        Text(stringResource(R.string.check_updates))
                    }
                }
            }
        }
    }
}

@Composable
private fun InfoBox(label: String, value: String) {
    Card(
        Modifier.fillMaxWidth(),
        RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surfaceVariant),
        elevation = CardDefaults.cardElevation(0.dp),
    ) {
        Column(Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 10.dp), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(label, style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
            Text(value, color = MaterialTheme.colorScheme.onSurface, maxLines = 2, overflow = TextOverflow.Ellipsis)
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

private enum class GoalKind { EARN, LIMIT, STORE_LIMIT, STORE_NO_SPEND }

/** Goals are built from pickers; only the amount needs typing. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun GoalsScreen(state: AppState, model: SpendsViewModel) {
    val context = LocalContext.current
    var kind by remember { mutableStateOf(GoalKind.EARN) }
    var amount by remember { mutableStateOf("") }
    var storeName by remember { mutableStateOf("") }
    var targetDate by remember {
        mutableStateOf(LocalDate.now().withDayOfMonth(LocalDate.now().lengthOfMonth()).toString())
    }
    var pickDate by remember { mutableStateOf(false) }
    val pickStore = androidx.activity.compose.rememberLauncherForActivityResult(
        androidx.activity.result.contract.ActivityResultContracts.StartActivityForResult(),
    ) { result ->
        if (result.resultCode == android.app.Activity.RESULT_OK) {
            storeName = result.data?.getStringExtra(orbit.nex.finance.PickStoreActivity.EXTRA_STORE).orEmpty()
        }
    }
    val currency = state.snapshot.currency
    val isStore = kind == GoalKind.STORE_LIMIT || kind == GoalKind.STORE_NO_SPEND
    val numeric = amount.replace(',', '.').toDoubleOrNull()
    val canSave = numeric != null && numeric > 0 && (!isStore || storeName.isNotBlank())
    val amt = numeric ?: 0.0
    val autoName = when (kind) {
        GoalKind.EARN -> stringResource(R.string.goal_name_earn, money(amt, currency))
        GoalKind.LIMIT -> stringResource(R.string.goal_name_limit, money(amt, currency))
        GoalKind.STORE_LIMIT -> stringResource(R.string.goal_name_store_limit, storeName.ifBlank { "…" })
        GoalKind.STORE_NO_SPEND -> stringResource(R.string.goal_name_store_no_spend, storeName.ifBlank { "…" })
    }

    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        PageHeading(R.string.goals_title)
        Text(stringResource(R.string.goal_type), style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
        Row(Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            GoalKind.values().forEach { k ->
                FilterChip(kind == k, { kind = k }, { Text(goalKindLabel(k), maxLines = 1) })
            }
        }
        OutlinedTextField(
            amount,
            { amount = sanitizeDecimal(it) },
            Modifier.fillMaxWidth(),
            label = { Text(stringResource(R.string.target)) },
            shape = RoundedCornerShape(16.dp),
            singleLine = true,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
        )
        if (isStore) {
            Card(
                Modifier.fillMaxWidth().clickable {
                    pickStore.launch(android.content.Intent(context, orbit.nex.finance.PickStoreActivity::class.java))
                },
                RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surfaceVariant),
            ) {
                Row(Modifier.fillMaxWidth().padding(16.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                    Text(storeName.ifBlank { stringResource(R.string.choose_store) }, fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onSurface)
                    Icon(Icons.Outlined.Storefront, null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }
        Card(
            Modifier.fillMaxWidth().clickable { pickDate = true },
            RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surfaceVariant),
        ) {
            Row(Modifier.fillMaxWidth().padding(16.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Column {
                    Text(stringResource(R.string.goal_period), style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text(longDateLabel(targetDate), style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.onSurface)
                }
                Icon(Icons.Outlined.CalendarMonth, null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
        Button(
            {
                model.saveGoal(
                    Goal(
                        name = autoName,
                        description = if (isStore) "store:$storeName" else "",
                        targetAmount = amt,
                        currentAmount = 0.0,
                        currency = currency,
                        targetDate = targetDate,
                        goalType = if (kind == GoalKind.EARN) "savings" else "expense",
                    ),
                )
                amount = ""
                storeName = ""
            },
            Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            enabled = canSave,
        ) { Text(stringResource(R.string.add_goal)) }
        state.snapshot.goals.forEach { g ->
            val cleanName = cleanGoalName(g.name)
            Card(shape = RoundedCornerShape(18.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
                Column(Modifier.fillMaxWidth().padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(
                        cleanName.ifBlank { stringResource(R.string.goal_fallback) },
                        fontWeight = FontWeight.SemiBold,
                        color = MaterialTheme.colorScheme.onSurface,
                    )
                    Spacer(Modifier.height(2.dp))
                    LinearProgressIndicator(
                        { (g.progressPercent / 100.0).toFloat().coerceIn(0f, 1f) },
                        Modifier.fillMaxWidth().height(8.dp).clip(RoundedCornerShape(99.dp)),
                    )
                    Text(
                        "${money(g.currentAmount, g.currency)} / ${money(g.targetAmount, g.currency)}",
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Row {
                        TextButton({ model.contributeGoal(g.id, "10") }) { Text("+10") }
                        TextButton({ model.deleteGoal(g.id) }) { Text(stringResource(R.string.delete)) }
                    }
                }
            }
        }
    }
    if (pickDate) {
        val pickerState = rememberDatePickerState(
            initialSelectedDateMillis = runCatching {
                LocalDate.parse(targetDate).atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli()
            }.getOrDefault(System.currentTimeMillis()),
        )
        AppDatePickerDialog(initialDateMillis = pickerState.selectedDateMillis ?: System.currentTimeMillis(), onDismiss = { pickDate = false },
            onConfirm = { ms -> targetDate = Instant.ofEpochMilli(ms).atZone(ZoneOffset.UTC).toLocalDate().toString() },
            dismissLabel = stringResource(R.string.heatmap_close))
    }
}

@Composable
private fun goalKindLabel(kind: GoalKind): String = stringResource(
    when (kind) {
        GoalKind.EARN -> R.string.goal_kind_earn
        GoalKind.LIMIT -> R.string.goal_kind_limit
        GoalKind.STORE_LIMIT -> R.string.goal_kind_store_limit
        GoalKind.STORE_NO_SPEND -> R.string.goal_kind_store_no_spend
    },
)

/** Allows digits with at most one decimal separator. */
private fun sanitizeDecimal(input: String): String {
    val filtered = input.replace(',', '.').filter { it.isDigit() || it == '.' }
    val firstDot = filtered.indexOf('.')
    return if (firstDot < 0) filtered
    else filtered.substring(0, firstDot + 1) + filtered.substring(firstDot + 1).replace(".", "")
}

/**
 * Tags belong to a concrete payment, not to the whole ledger: a tag row opens a
 * payment picker, and a payment can be picked first as well.
 */
@Composable
fun TagsScreen(state: AppState, model: SpendsViewModel) {
    var name by remember { mutableStateOf("") }
    var tagBeingAssigned by remember { mutableStateOf<Tag?>(null) }
    var showPaymentPicker by remember { mutableStateOf(false) }
    var pendingTagExpense by remember { mutableStateOf<Expense?>(null) }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        PageHeading(R.string.tags_title)
        Text(stringResource(R.string.tags_hint), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
            OutlinedTextField(name, { name = it }, Modifier.weight(1f), label = { Text(stringResource(R.string.tag)) }, shape = RoundedCornerShape(16.dp), singleLine = true)
            Button({ model.saveTag(name, "#4F46E5"); name = "" }, Modifier.height(56.dp), shape = RoundedCornerShape(16.dp)) { Text(stringResource(R.string.add)) }
        }
        OutlinedButton({ showPaymentPicker = true }, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp)) {
            Text(stringResource(R.string.tags_pick_payment))
        }
        if (state.snapshot.tags.isEmpty()) EmptyCard(stringResource(R.string.tags_empty_title), stringResource(R.string.tags_empty_body))
        state.snapshot.tags.forEach { t ->
            Card(
                Modifier.fillMaxWidth(),
                RoundedCornerShape(18.dp),
                colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface),
            ) {
                Row(
                    Modifier.fillMaxWidth().border(1.dp, MaterialTheme.colorScheme.outlineVariant, RoundedCornerShape(18.dp)).padding(horizontal = 14.dp, vertical = 6.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(10.dp),
                ) {
                    Box(Modifier.size(10.dp).clip(CircleShape).background(tagColor(t.color)))
                    Column(Modifier.weight(1f).padding(vertical = 10.dp)) {
                        Text(t.name, fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onSurface)
                        Text(
                            stringResource(R.string.tags_usage, t.usageCount),
                            style = MaterialTheme.typography.bodySmall,
                            maxLines = 1,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                    // Icon actions keep the row on one line even on narrow screens.
                    IconButton({ tagBeingAssigned = t }) {
                        Icon(Icons.Outlined.Label, stringResource(R.string.tags_assign), tint = MaterialTheme.colorScheme.primary)
                    }
                    IconButton({ model.deleteTag(t.id) }) {
                        Icon(Icons.Outlined.Delete, stringResource(R.string.delete), tint = MaterialTheme.colorScheme.error)
                    }
                }
            }
        }
    }
    tagBeingAssigned?.let { tag ->
        PaymentPickerDialog(
            state = state,
            title = stringResource(R.string.tags_pick_payment),
            onDismiss = { tagBeingAssigned = null },
        ) { expense ->
            model.toggleExpenseTag(expense, tag.id)
            tagBeingAssigned = null
        }
    }
    if (showPaymentPicker) {
        PaymentPickerDialog(
            state = state,
            title = stringResource(R.string.tags_pick_payment),
            onDismiss = { showPaymentPicker = false },
        ) { picked ->
            showPaymentPicker = false
            pendingTagExpense = picked
        }
    }
    pendingTagExpense?.let { expense ->
        PaymentTagDialog(state, expense, model) { pendingTagExpense = null }
    }
}

private fun tagColor(hex: String): Color = runCatching {
    Color(android.graphics.Color.parseColor(hex))
}.getOrDefault(Color(0xFF4F46E5))

/** Full ledger with store and date filters, used to pick the payment a tag belongs to. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PaymentPickerDialog(state: AppState, title: String, onDismiss: () -> Unit, onPick: (Expense) -> Unit) {
    var store by remember { mutableStateOf("") }
    var dateQuery by remember { mutableStateOf("") }
    var filtersOpen by remember { mutableStateOf(true) }
    val stores = remember(state.snapshot.expenses) { state.snapshot.expenses.map { it.store }.distinct().sorted() }
    val rows = remember(state.snapshot.expenses, store, dateQuery) {
        state.snapshot.expenses
            .filter { store.isBlank() || it.store == store }
            .filter { dateQuery.isBlank() || it.date.contains(dateQuery.trim()) }
            .sortedByDescending { it.date }
    }
    Dialog(onDismissRequest = onDismiss) {
        Surface(shape = RoundedCornerShape(28.dp), color = MaterialTheme.colorScheme.surface, tonalElevation = 2.dp) {
            Column(Modifier.fillMaxWidth().padding(18.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(title, style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.onSurface, modifier = Modifier.weight(1f))
                    IconButton({ filtersOpen = !filtersOpen }) { Icon(Icons.Outlined.Tune, stringResource(R.string.tags_filters)) }
                }
                if (filtersOpen) {
                    OutlinedTextField(
                        dateQuery,
                        { dateQuery = it },
                        Modifier.fillMaxWidth(),
                        label = { Text(stringResource(R.string.tags_date_filter)) },
                        placeholder = { Text("2026-10") },
                        singleLine = true,
                        shape = RoundedCornerShape(14.dp),
                    )
                    Row(Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        FilterChip(store.isBlank(), { store = "" }, { Text(stringResource(R.string.tags_all_stores)) })
                        stores.forEach { name ->
                            FilterChip(store == name, { store = if (store == name) "" else name }, { Text(name, maxLines = 1) })
                        }
                    }
                }
                if (rows.isEmpty()) {
                    Text(stringResource(R.string.tags_no_payments), color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                LazyColumn(Modifier.fillMaxWidth().heightIn(max = 380.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    items(rows, key = { "${it.date}-${it.store}-${it.amount}-${it.signature}" }) { expense ->
                        val tags = state.snapshot.expenseTags["${expense.date}\t${expense.store}"].orEmpty()
                        Column {
                            ExpenseRow(expense, state.snapshot.currency, state.snapshot.stores.firstOrNull { it.name == expense.store }?.photoUrl.orEmpty()) { onPick(expense) }
                            if (tags.isNotEmpty()) {
                                Row(Modifier.padding(start = 12.dp, top = 2.dp), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                    tags.forEach { id ->
                                        val tag = state.snapshot.tags.firstOrNull { it.id == id }
                                        if (tag != null) {
                                            Text(tag.name, style = MaterialTheme.typography.labelSmall, color = tagColor(tag.color))
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                Button(onDismiss, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp)) { Text(stringResource(R.string.heatmap_close)) }
            }
        }
    }
}

/** Tag chips for one payment; tapping a tag attaches or detaches it. */
@Composable
fun ExpenseTagsSection(state: AppState, expense: Expense, model: SpendsViewModel) {
    val attached = state.snapshot.expenseTags["${expense.date}\t${expense.store}"].orEmpty()
    var open by remember { mutableStateOf(false) }
    Card(Modifier.fillMaxWidth(), RoundedCornerShape(20.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
        Column(Modifier.fillMaxWidth().padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Outlined.Label, null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(8.dp))
                Text(stringResource(R.string.tags_title), style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary, modifier = Modifier.weight(1f))
                TextButton({ open = true }) { Text(stringResource(R.string.tags_assign)) }
            }
            if (attached.isEmpty()) {
                Text(stringResource(R.string.tags_none), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            } else {
                Row(Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    attached.forEach { id ->
                        val tag = state.snapshot.tags.firstOrNull { it.id == id } ?: return@forEach
                        Box(Modifier.clip(RoundedCornerShape(99.dp)).background(tagColor(tag.color).copy(alpha = 0.16f)).padding(horizontal = 10.dp, vertical = 5.dp)) {
                            Text(tag.name, style = MaterialTheme.typography.labelSmall, color = tagColor(tag.color))
                        }
                    }
                }
            }
        }
    }
    if (open) PaymentTagDialog(state, expense, model) { open = false }
}

/** Tag checklist for one payment; each tap attaches or detaches that tag. */
@Composable
fun PaymentTagDialog(state: AppState, expense: Expense, model: SpendsViewModel, onDismiss: () -> Unit) {
    val attached = state.snapshot.expenseTags["${expense.date}\t${expense.store}"].orEmpty()
    var newTag by remember { mutableStateOf("") }
    Dialog(onDismissRequest = onDismiss) {
        Surface(shape = RoundedCornerShape(28.dp), color = MaterialTheme.colorScheme.surface, tonalElevation = 2.dp) {
            Column(Modifier.fillMaxWidth().padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text(stringResource(R.string.tags_choose), style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.onSurface)
                Text(
                    "${expense.store} · ${longDateLabel(expense.date)} · ${money(expense.amount, state.snapshot.currency)}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                // Create a tag right here; it is attached to this payment, not the store.
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                    OutlinedTextField(
                        newTag,
                        { newTag = it },
                        Modifier.weight(1f),
                        label = { Text(stringResource(R.string.tag)) },
                        singleLine = true,
                        shape = RoundedCornerShape(14.dp),
                    )
                    Button(
                        {
                            val name = newTag.trim()
                            if (name.isNotEmpty()) {
                                model.createTagAndAttach(expense, name)
                                newTag = ""
                            }
                        },
                        shape = RoundedCornerShape(14.dp),
                        enabled = newTag.isNotBlank(),
                    ) { Text(stringResource(R.string.add)) }
                }
                if (state.snapshot.tags.isEmpty()) {
                    Text(stringResource(R.string.tags_empty_body), color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                state.snapshot.tags.forEach { tag ->
                    val checked = tag.id in attached
                    Row(
                        Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).clickable { model.toggleExpenseTag(expense, tag.id) }.padding(vertical = 8.dp, horizontal = 6.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(10.dp),
                    ) {
                        Box(Modifier.size(10.dp).clip(CircleShape).background(tagColor(tag.color)))
                        Text(tag.name, modifier = Modifier.weight(1f), color = MaterialTheme.colorScheme.onSurface)
                        if (checked) Icon(Icons.Outlined.Check, null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(18.dp))
                    }
                }
                Button(onDismiss, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp)) { Text(stringResource(R.string.ok)) }
            }
        }
    }
}

/**
 * Recurring charges. A subscription is its own entry source (server-side
 * `entry_source = 'subscription'`), so the charges it creates are recognisable.
 */
@Composable
fun SubscriptionsScreen(state: AppState, model: SpendsViewModel) {
    val context = LocalContext.current
    var store by remember { mutableStateOf("") }
    var amount by remember { mutableStateOf("") }
    var day by remember { mutableStateOf("1") }
    var adding by remember { mutableStateOf(false) }
    val pickStore = androidx.activity.compose.rememberLauncherForActivityResult(androidx.activity.result.contract.ActivityResultContracts.StartActivityForResult()) { result ->
        if (result.resultCode == android.app.Activity.RESULT_OK) {
            store = result.data?.getStringExtra(orbit.nex.finance.PickStoreActivity.EXTRA_STORE).orEmpty()
        }
    }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        PageHeading(R.string.subscriptions_title)
        Text(stringResource(R.string.subscriptions_desc), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Button({ adding = !adding }, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp)) {
            Icon(Icons.Outlined.Add, null, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(8.dp))
            Text(stringResource(R.string.subscriptions_new))
        }
        if (adding) {
            Card(Modifier.fillMaxWidth(), RoundedCornerShape(20.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
                Column(Modifier.fillMaxWidth().padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Card(
                        Modifier.fillMaxWidth().clickable { pickStore.launch(android.content.Intent(context, orbit.nex.finance.PickStoreActivity::class.java)) },
                        RoundedCornerShape(14.dp),
                        colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surfaceVariant),
                    ) {
                        Row(Modifier.fillMaxWidth().padding(14.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                            Text(store.ifBlank { stringResource(R.string.choose_store) }, fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onSurface)
                            Icon(Icons.Outlined.Storefront, null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }
                    OutlinedTextField(amount, { amount = it }, Modifier.fillMaxWidth(), label = { Text(stringResource(R.string.amount_label)) }, shape = RoundedCornerShape(14.dp), singleLine = true)
                    OutlinedTextField(day, { day = it.filter { c -> c.isDigit() }.take(2) }, Modifier.fillMaxWidth(), label = { Text(stringResource(R.string.subscriptions_day)) }, shape = RoundedCornerShape(14.dp), singleLine = true)
                    Button(
                        {
                            model.addSubscription(store, amount.replace(',', '.'), day.ifBlank { "1" })
                            store = ""; amount = ""; day = "1"; adding = false
                        },
                        Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(14.dp),
                        enabled = store.isNotBlank() && amount.toDoubleOrNull() != null,
                    ) { Text(stringResource(R.string.save)) }
                }
            }
        }
        if (state.snapshot.subscriptions.isEmpty()) {
            EmptyCard(stringResource(R.string.subscriptions_empty_title), stringResource(R.string.subscriptions_empty_body))
        }
        state.snapshot.subscriptions.forEach { sub ->
            Card(Modifier.fillMaxWidth(), RoundedCornerShape(20.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
                Column(
                    Modifier.fillMaxWidth().border(1.dp, MaterialTheme.colorScheme.outlineVariant, RoundedCornerShape(20.dp)).padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(6.dp),
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        RemotePhoto(
                            state.snapshot.stores.firstOrNull { it.name == sub.store }?.photoUrl.orEmpty(),
                            sub.store.take(1).uppercase(),
                            round = false,
                            size = 40.dp,
                        )
                        Spacer(Modifier.width(12.dp))
                        Column(Modifier.weight(1f)) {
                            Text(sub.store, fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onSurface)
                            Text(
                                stringResource(R.string.subscriptions_next, sub.dayOfMonth),
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                        Text("−${money(sub.amount, state.snapshot.currency)}", fontWeight = FontWeight.SemiBold, color = SpendsColors.expense())
                    }
                    TextButton({ model.deleteSubscription(sub.id) }) {
                        Text(stringResource(R.string.subscriptions_cancel), color = MaterialTheme.colorScheme.error)
                    }
                }
            }
        }
    }
}

@Composable
fun TrashScreen(state: AppState, model: SpendsViewModel) {
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        PageHeading(R.string.trash_title)
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
            val name = result.data?.getStringExtra(orbit.nex.finance.PickStoreActivity.EXTRA_STORE).orEmpty()
            editing?.let { model.updatePendingStore(it, name) }
        }
        editing = null
    }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        PageHeading(R.string.pending_title)
        Text(stringResource(R.string.pending_desc), color = MaterialTheme.colorScheme.onSurfaceVariant)
        NotificationAccessCard(state, model)
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
                            picker.launch(android.content.Intent(context, orbit.nex.finance.PickStoreActivity::class.java))
                        }) { Text(stringResource(R.string.pending_choose_store)) }
                        TextButton({ model.dismissPending(p) }) { Text(stringResource(R.string.skip)) }
                    }
                }
            }
        }
    }
}

@Composable
private fun NotificationAccessCard(state: AppState, model: SpendsViewModel) {
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
            Button(
                { model.scanWalletNotifications() },
                Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(14.dp),
                enabled = enabled && !state.scanBusy,
            ) { Text(if (state.scanBusy) stringResource(R.string.notifications_scanning) else stringResource(R.string.notifications_scan_current)) }
            state.scan?.let { result ->
                val text = when {
                    result.error == "notification_access" -> stringResource(R.string.notifications_scan_not_connected)
                    result.error == "not_paired" -> stringResource(R.string.notifications_scan_not_paired)
                    else -> stringResource(R.string.notifications_scan_result, result.scanned, result.written, result.conflicts)
                }
                Text(text, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
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

private val CurrencyOptions = listOf("USD", "EUR", "GBP", "PLN", "UAH", "BYN", "CZK", "CHF", "SEK", "NOK", "CAD", "AUD")

@Composable
fun PrefsScreen(state: AppState, model: SpendsViewModel) {
    var budget by remember(state.snapshot.budget) { mutableStateOf(state.snapshot.budget.toString()) }
    var currency by remember(state.snapshot.currency) { mutableStateOf(state.snapshot.currency) }
    var incomes by remember(state.snapshot.flags.showIncomes) { mutableStateOf(state.snapshot.flags.showIncomes) }
    var trend by remember(state.snapshot.flags.showTrend) { mutableStateOf(state.snapshot.flags.showTrend) }
    var trim by remember(state.snapshot.flags.trimEmptyMonths) { mutableStateOf(state.snapshot.flags.trimEmptyMonths) }
    var pickCurrency by remember { mutableStateOf(false) }
    val budgetValid = budget.replace(',', '.').toDoubleOrNull()?.let { it > 0.0 } == true
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        PageHeading(R.string.preferences_title)
        OutlinedTextField(
            budget,
            { budget = sanitizeDecimal(it) },
            Modifier.fillMaxWidth(),
            label = { Text(stringResource(R.string.monthly_budget)) },
            shape = RoundedCornerShape(16.dp),
            singleLine = true,
            isError = budget.isNotBlank() && !budgetValid,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
        )
        Card(
            Modifier.fillMaxWidth().clickable { pickCurrency = true },
            RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surfaceVariant),
        ) {
            Row(Modifier.fillMaxWidth().padding(16.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Column {
                    Text(stringResource(R.string.currency), style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text(currency.ifBlank { "—" }, style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.onSurface)
                }
                Icon(Icons.Outlined.ChevronRight, null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
        SwitchRow(stringResource(R.string.show_incomes), incomes) { incomes = it }
        SwitchRow(stringResource(R.string.show_trend), trend) { trend = it }
        SwitchRow(stringResource(R.string.trim_empty_months), trim) { trim = it }
        Button(
            { model.saveSettings(budget, currency, incomes, trend, trim) },
            Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            enabled = budgetValid,
        ) { Text(stringResource(R.string.save)) }
        Text(stringResource(R.string.prefs_note), color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall)
    }
    if (pickCurrency) {
        Dialog(onDismissRequest = { pickCurrency = false }) {
            Surface(shape = RoundedCornerShape(28.dp), color = MaterialTheme.colorScheme.surface, tonalElevation = 2.dp) {
                Column(Modifier.fillMaxWidth().padding(20.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    Text(stringResource(R.string.choose_currency), style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.onSurface)
                    LazyColumn(Modifier.fillMaxWidth().heightIn(max = 380.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        items(CurrencyOptions.distinct(), key = { it }) { code ->
                            val active = code == currency
                            Row(
                                Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp))
                                    .background(if (active) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surfaceVariant)
                                    .clickable { currency = code; pickCurrency = false }
                                    .padding(horizontal = 16.dp, vertical = 14.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.SpaceBetween,
                            ) {
                                Text(code, fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onSurface)
                                if (active) Icon(Icons.Outlined.Check, null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(18.dp))
                            }
                        }
                    }
                    Button({ pickCurrency = false }, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp)) { Text(stringResource(R.string.heatmap_close)) }
                }
            }
        }
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
    var selected by remember { mutableStateOf<BackupEntry?>(null) }
    val entries = remember(state.backups, state.localBackups) { state.allBackups }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        PageHeading(R.string.data_title)
        OutlinedTextField(name, { name = it }, Modifier.fillMaxWidth(), label = { Text(stringResource(R.string.backup_name)) }, shape = RoundedCornerShape(16.dp))
        Button({ model.saveBackup(name) }, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp)) {
            Icon(Icons.Outlined.Backup, null)
            Spacer(Modifier.width(8.dp))
            Text(stringResource(R.string.save_backup))
        }
        if (entries.isEmpty()) EmptyCard(stringResource(R.string.data_title), stringResource(R.string.notifications_empty))
        entries.forEach { entry ->
            BackupCard(entry) { selected = entry }
        }
    }
    selected?.let { entry ->
        BackupDetailsDialog(
            entry = entry,
            onRestore = { model.restoreBackup(entry.name); selected = null },
            onDelete = { model.deleteBackup(entry.name); selected = null },
            onDismiss = { selected = null },
        )
    }
}

@Composable
private fun BackupCard(entry: BackupEntry, onClick: () -> Unit) {
    Card(
        Modifier.fillMaxWidth().clickable(onClick = onClick),
        RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(0.dp),
    ) {
        Box(Modifier.fillMaxWidth().border(1.dp, MaterialTheme.colorScheme.outlineVariant, RoundedCornerShape(20.dp))) {
            Row(Modifier.fillMaxWidth().padding(16.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                Box(Modifier.size(40.dp).clip(RoundedCornerShape(12.dp)).background(MaterialTheme.colorScheme.primaryContainer), contentAlignment = Alignment.Center) {
                    Icon(Icons.Outlined.Backup, null, tint = MaterialTheme.colorScheme.onPrimaryContainer)
                }
                Column(Modifier.weight(1f)) {
                    Text(entry.name, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis, color = MaterialTheme.colorScheme.onSurface)
                    Text(
                        listOfNotNull(backupDate(entry.mtime).takeIf { it.isNotBlank() }, humanSize(entry.size)).joinToString(" · "),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
            // Storage badge in the top-right corner: local, server or both.
            Icon(
                imageVector = when {
                    entry.local && entry.server -> Icons.Filled.CloudDone
                    entry.server -> Icons.Outlined.Cloud
                    else -> Icons.Outlined.Smartphone
                },
                contentDescription = stringResource(
                    when {
                        entry.local && entry.server -> R.string.backup_both
                        entry.server -> R.string.backup_server
                        else -> R.string.backup_local
                    },
                ),
                tint = when {
                    entry.local && entry.server -> SpendsColors.income()
                    entry.server -> MaterialTheme.colorScheme.primary
                    else -> MaterialTheme.colorScheme.onSurfaceVariant
                },
                modifier = Modifier.align(Alignment.TopEnd).padding(10.dp).size(18.dp),
            )
        }
    }
}

@Composable
private fun BackupDetailsDialog(entry: BackupEntry, onRestore: () -> Unit, onDelete: () -> Unit, onDismiss: () -> Unit) {
    Dialog(onDismissRequest = onDismiss) {
        Surface(shape = RoundedCornerShape(28.dp), color = MaterialTheme.colorScheme.surface, tonalElevation = 2.dp) {
            Column(Modifier.fillMaxWidth().padding(20.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    Box(Modifier.size(44.dp).clip(RoundedCornerShape(14.dp)).background(MaterialTheme.colorScheme.primaryContainer), contentAlignment = Alignment.Center) {
                        Icon(Icons.Outlined.Backup, null, tint = MaterialTheme.colorScheme.onPrimaryContainer)
                    }
                    Column(Modifier.weight(1f)) {
                        Text(stringResource(R.string.backup_details), style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.onSurface)
                        Text(entry.name, color = MaterialTheme.colorScheme.onSurfaceVariant, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    }
                }
                InfoRow(stringResource(R.string.backup_date), backupDate(entry.mtime))
                InfoRow(stringResource(R.string.backup_size), humanSize(entry.size))
                InfoRow(
                    stringResource(R.string.backup_stored_in),
                    stringResource(
                        when {
                            entry.local && entry.server -> R.string.backup_both
                            entry.server -> R.string.backup_server
                            else -> R.string.backup_local
                        },
                    ),
                )
                Button(onRestore, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp)) { Text(stringResource(R.string.restore)) }
                TextButton(onDelete, Modifier.fillMaxWidth()) {
                    Text(stringResource(R.string.delete), color = MaterialTheme.colorScheme.error)
                }
                TextButton(onDismiss, Modifier.fillMaxWidth()) { Text(stringResource(R.string.heatmap_close)) }
            }
        }
    }
}

/** Last install/update date of the app package, shown in About. */
private fun updateDate(context: android.content.Context): String = runCatching {
    val info = context.packageManager.getPackageInfo(context.packageName, 0)
    java.text.DateFormat.getDateInstance(java.text.DateFormat.MEDIUM).format(java.util.Date(info.lastUpdateTime))
}.getOrDefault("—")

private fun humanSize(bytes: Long): String = when {
    bytes >= 1_048_576 -> "%.1f MB".format(bytes / 1_048_576.0)
    bytes >= 1024 -> "%.1f KB".format(bytes / 1024.0)
    bytes > 0 -> "$bytes B"
    else -> "—"
}

private fun backupDate(mtime: Long): String =
    if (mtime <= 0L) "—" else java.text.DateFormat.getDateTimeInstance(java.text.DateFormat.SHORT, java.text.DateFormat.SHORT).format(java.util.Date(mtime))

@Composable
fun SyncScreen(state: AppState, model: SpendsViewModel, onLogs: () -> Unit) {
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        PageHeading(R.string.sync_title)
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
        if (result.resultCode == android.app.Activity.RESULT_OK) merchant = result.data?.getStringExtra(orbit.nex.finance.PickStoreActivity.EXTRA_STORE).orEmpty()
    }
    ModalBottomSheet(onDismissRequest = onDismiss, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp)) {
        Column(Modifier.padding(horizontal = 24.dp).padding(bottom = 36.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Text(stringResource(if (kind == "income") R.string.new_income else R.string.new_expense), style = MaterialTheme.typography.headlineMedium)
            DateSelectorField(date, { date = it })
            if (kind != "income") {
                Card(
                    Modifier.fillMaxWidth().clickable { pickStore.launch(android.content.Intent(context, orbit.nex.finance.PickStoreActivity::class.java)) },
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
fun DetailScreen(title: String, onBack: () -> Unit, onEdit: (() -> Unit)? = null, content: @Composable () -> Unit) {
    Column(Modifier.fillMaxSize().background(canvasBrush()).statusBarsPadding()) {
        Row(Modifier.fillMaxWidth().padding(8.dp), verticalAlignment = Alignment.CenterVertically) {
            Button(
                onClick = onBack,
                colors = ButtonDefaults.filledTonalButtonColors(),
                shape = RoundedCornerShape(16.dp),
                modifier = Modifier.padding(end = 8.dp),
            ) {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    Icon(Icons.AutoMirrored.Outlined.ArrowBack, null, modifier = Modifier.size(20.dp))
                    Text(stringResource(R.string.back), style = MaterialTheme.typography.labelLarge)
                }
            }
            Text(title, style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.onBackground, modifier = Modifier.weight(1f))
            if (onEdit != null) {
                IconButton(onEdit) { Icon(Icons.Outlined.Edit, stringResource(R.string.edit), tint = MaterialTheme.colorScheme.primary) }
            }
        }
        // Content headings would repeat the toolbar title, so screens hide theirs here.
        androidx.compose.runtime.CompositionLocalProvider(LocalHeadingHidden provides true) {
            Box(Modifier.weight(1f).fillMaxWidth()) { content() }
        }
    }
}

/** Read-only date field with an explicit calendar affordance and a separate Material dialog. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DateSelectorField(value: String, onValueChange: (String) -> Unit) {
    var open by remember { mutableStateOf(false) }
    OutlinedTextField(
        value = value,
        onValueChange = {},
        modifier = Modifier.fillMaxWidth().clickable { open = true },
        label = { Text(stringResource(R.string.date_label)) },
        shape = RoundedCornerShape(16.dp),
        singleLine = true,
        readOnly = true,
        trailingIcon = {
            IconButton({ open = true }) { Icon(Icons.Outlined.CalendarMonth, stringResource(R.string.date_label)) }
        },
    )
    if (open) {
        val picker = rememberDatePickerState(initialSelectedDateMillis = runCatching {
            LocalDate.parse(value).atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli()
        }.getOrDefault(System.currentTimeMillis()))
        AppDatePickerDialog(
            initialDateMillis = picker.selectedDateMillis ?: System.currentTimeMillis(),
            onDismiss = { open = false },
            onConfirm = { millis -> onValueChange(Instant.ofEpochMilli(millis).atZone(ZoneOffset.UTC).toLocalDate().toString()) },
            dismissLabel = stringResource(R.string.heatmap_close),
        )
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
    val shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp)
    Box(Modifier.fillMaxWidth().shadow(16.dp, shape, clip = false)) {
        // Background is painted across the navigation-bar inset too, so the bar
        // reaches the bottom edge instead of leaving a gap above the system keys.
        Box(
            Modifier.fillMaxWidth()
                .clip(shape)
                .background(MaterialTheme.colorScheme.surface)
                .navigationBarsPadding()
                .height(80.dp),
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

/** Floating confirmation pill shown after any save action. */
@Composable
fun SavedToast(modifier: Modifier = Modifier) {
    Surface(modifier, shape = RoundedCornerShape(99.dp), color = MaterialTheme.colorScheme.primary, shadowElevation = 8.dp) {
        Row(
            Modifier.padding(horizontal = 18.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            Icon(Icons.Outlined.CheckCircle, null, tint = MaterialTheme.colorScheme.onPrimary, modifier = Modifier.size(18.dp))
            Text(stringResource(R.string.saved_changes), color = MaterialTheme.colorScheme.onPrimary, fontWeight = FontWeight.SemiBold)
        }
    }
}

/** Edit every field of an existing payment (date, store, amount). */
@Composable
fun EditExpenseDialog(state: AppState, expense: Expense, model: SpendsViewModel, onDismiss: () -> Unit) {
    val context = LocalContext.current
    var date by remember(expense) { mutableStateOf(expense.date) }
    var store by remember(expense) { mutableStateOf(expense.store) }
    var amount by remember(expense) { mutableStateOf(expense.amount.toString()) }
    val pickStore = androidx.activity.compose.rememberLauncherForActivityResult(
        androidx.activity.result.contract.ActivityResultContracts.StartActivityForResult(),
    ) { result ->
        if (result.resultCode == android.app.Activity.RESULT_OK) {
            store = result.data?.getStringExtra(orbit.nex.finance.PickStoreActivity.EXTRA_STORE).orEmpty()
        }
    }
    val valid = store.isNotBlank() && (amount.replace(',', '.').toDoubleOrNull() ?: 0.0) > 0.0
    Dialog(onDismissRequest = onDismiss) {
        Surface(shape = RoundedCornerShape(28.dp), color = MaterialTheme.colorScheme.surface, tonalElevation = 2.dp) {
            Column(
                Modifier.fillMaxWidth().padding(20.dp).verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                Text(stringResource(R.string.edit_expense), style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.onSurface)
                DateSelectorField(date, { date = it })
                Card(
                    Modifier.fillMaxWidth().clickable {
                        pickStore.launch(android.content.Intent(context, orbit.nex.finance.PickStoreActivity::class.java))
                    },
                    RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surfaceVariant),
                ) {
                    Row(Modifier.fillMaxWidth().padding(16.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                        Text(store.ifBlank { stringResource(R.string.choose_store) }, fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onSurface)
                        Icon(Icons.Outlined.Storefront, null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
                OutlinedTextField(
                    amount,
                    { amount = sanitizeDecimal(it) },
                    Modifier.fillMaxWidth(),
                    label = { Text(stringResource(R.string.amount_label)) },
                    shape = RoundedCornerShape(14.dp),
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                )
                Button(
                    { model.updateExpense(expense, date, store, amount) { onDismiss() } },
                    Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(16.dp),
                    enabled = valid,
                ) { Text(stringResource(R.string.save)) }
                TextButton(onDismiss, Modifier.fillMaxWidth()) { Text(stringResource(R.string.heatmap_close)) }
            }
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
        Icon(icon, label, tint = color, modifier = Modifier.size(22.dp))
        Text(
            label,
            style = MaterialTheme.typography.labelSmall,
            color = color,
            fontSize = 10.sp,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            textAlign = TextAlign.Center,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}

@Composable
fun ErrorDialog(message: String?, dismiss: () -> Unit) {
    if (message != null) {
        Dialog(
            onDismissRequest = dismiss,
            properties = DialogProperties(usePlatformDefaultWidth = false),
        ) {
            Surface(
                modifier = Modifier.fillMaxWidth().padding(horizontal = 24.dp).widthIn(max = 480.dp),
                shape = RoundedCornerShape(28.dp),
                color = MaterialTheme.colorScheme.surface,
                tonalElevation = 6.dp,
            ) {
                Column(Modifier.fillMaxWidth().padding(24.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
                    Text(stringResource(R.string.something_went_wrong), style = MaterialTheme.typography.headlineSmall, color = MaterialTheme.colorScheme.onSurface)
                    Text(message, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    TextButton(onClick = dismiss, modifier = Modifier.align(Alignment.End)) { Text(stringResource(R.string.ok)) }
                }
            }
        }
    }
}

private fun prettyDate(raw: String): String = try {
    LocalDate.parse(raw).format(DateTimeFormatter.ofPattern("EEEE, d MMM", Locale.getDefault()))
} catch (_: DateTimeParseException) {
    raw
}

/** Full weekday name, capitalised: "Понедельник". */
private fun weekdayLabel(raw: String): String = try {
    LocalDate.parse(raw)
        .format(DateTimeFormatter.ofPattern("EEEE", Locale.getDefault()))
        .replaceFirstChar { it.titlecase(Locale.getDefault()) }
} catch (_: DateTimeParseException) {
    raw
}

/** "26-Октября-2026" — day, capitalised month name, year. */
private fun longDateLabel(raw: String): String = try {
    val date = LocalDate.parse(raw)
    val day = date.format(DateTimeFormatter.ofPattern("d", Locale.getDefault()))
    val month = date.format(DateTimeFormatter.ofPattern("MMMM", Locale.getDefault()))
        .replaceFirstChar { it.titlecase(Locale.getDefault()) }
    "$day-$month-${date.year}"
} catch (_: DateTimeParseException) {
    raw
}

/** Small icon + label for an expense source, used inside rows. */
@Composable
private fun SourceBadge(raw: String) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(5.dp)) {
        Icon(sourceIcon(raw), null, tint = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.size(14.dp))
        Text(sourceLabel(raw), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
    }
}

/** Card with a distinct icon for the source on the expense detail screen. */
@Composable
private fun SourceBadgeCard(raw: String) {
    Card(Modifier.fillMaxWidth(), RoundedCornerShape(20.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
        Row(
            Modifier.fillMaxWidth().padding(18.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Box(Modifier.size(40.dp).clip(RoundedCornerShape(12.dp)).background(MaterialTheme.colorScheme.primaryContainer), contentAlignment = Alignment.Center) {
                Icon(sourceIcon(raw), null, tint = MaterialTheme.colorScheme.onPrimaryContainer)
            }
            Column {
                Text(stringResource(R.string.source), style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                Text(sourceLabel(raw), style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.onSurface)
            }
        }
    }
}

private fun sourceIcon(raw: String): ImageVector = when (raw.trim().lowercase(Locale.ROOT)) {
    "ocr" -> Icons.Outlined.ReceiptLong
    "notification" -> Icons.Outlined.Notifications
    "wallet" -> Icons.Outlined.Notifications
    "subscription" -> Icons.Outlined.Autorenew
    "other" -> Icons.Outlined.HelpOutline
    else -> Icons.Outlined.Edit
}

/** Locally translated label for an expense source (manual, ocr, wallet…). */
@Composable
private fun sourceLabel(raw: String): String = when (raw.trim().lowercase(Locale.ROOT)) {
    "", "manual" -> stringResource(R.string.source_manual)
    "ocr" -> stringResource(R.string.source_ocr)
    "notification", "wallet" -> stringResource(R.string.source_notification)
    "subscription" -> stringResource(R.string.source_subscription)
    "other" -> stringResource(R.string.source_other)
    else -> raw.replaceFirstChar { it.titlecase(Locale.getDefault()) }
}

/** Hides generated ids in goal names ("Sync Goal 1793049032925"). */
private fun cleanGoalName(name: String): String {
    val withoutIds = name.replace(Regex("\\d{4,}"), " ").replace(Regex("\\s+"), " ").trim(' ', '-', '_', '·')
    return if (withoutIds.equals("sync goal", ignoreCase = true) || withoutIds.equals("goal", ignoreCase = true)) "" else withoutIds
}
