package xyz.serverkherson.spends.ui

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
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
import androidx.compose.material.icons.outlined.Insights
import androidx.compose.material.icons.outlined.Label
import androidx.compose.material.icons.outlined.Palette
import androidx.compose.material.icons.outlined.PieChart
import androidx.compose.material.icons.outlined.QrCodeScanner
import androidx.compose.material.icons.outlined.Settings
import androidx.compose.material.icons.outlined.ShowChart
import androidx.compose.material.icons.outlined.Storefront
import androidx.compose.material.icons.outlined.SwapHoriz
import androidx.compose.material.icons.outlined.Tune
import androidx.compose.material.icons.outlined.TrendingDown
import androidx.compose.material.icons.outlined.TrendingUp
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
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import java.time.LocalDate
import java.time.YearMonth
import java.time.format.DateTimeFormatter
import java.time.format.DateTimeParseException
import java.util.Locale
import xyz.serverkherson.spends.AppState
import xyz.serverkherson.spends.SpendsViewModel
import xyz.serverkherson.spends.data.Expense
import xyz.serverkherson.spends.data.Goal
import xyz.serverkherson.spends.data.PendingCapture
import xyz.serverkherson.spends.data.Store

enum class Dest { Home, Stats, Stores, Settings, Insights, CashFlow, Categories, Calendar, Wrapped, NetWorth, Goals, Tags, Trash, Pending, Prefs, Data, Sync, Appearance, Transaction, Ocr, Expense, Store }

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(state: AppState, model: SpendsViewModel, onOpen: (Expense) -> Unit) {
    val groups = remember(state.snapshot.expenses) { state.snapshot.expenses.sortedByDescending { it.date }.groupBy { it.date } }
    PullToRefreshBox(state.loading, model::refresh, Modifier.fillMaxSize()) {
        LazyColumn(Modifier.fillMaxSize().padding(horizontal = 20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            item { Spacer(Modifier.height(12.dp)); Greeting(); Spacer(Modifier.height(4.dp)); HeroCard(state) }
            item { SectionLabel(Icons.Outlined.History, "Activity") }
            if (groups.isEmpty()) item { EmptyCard("A quiet ledger", "Add an expense, income, or a receipt.") }
            groups.forEach { (date, rows) ->
                item { Text(prettyDate(date), style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.55f), modifier = Modifier.padding(top = 8.dp, start = 4.dp)) }
                items(rows, key = { "${it.date}-${it.store}-${it.amount}-${it.signature}" }) { row ->
                    ExpenseRow(row, state.snapshot.currency, state.snapshot.stores.firstOrNull { it.name == row.store }?.photoUrl.orEmpty()) { onOpen(row) }
                }
            }
            item { Spacer(Modifier.height(110.dp)) }
        }
    }
}

@Composable fun Greeting() {
    Column {
        Text("Spends", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
        Text("Ledger", style = MaterialTheme.typography.headlineMedium)
    }
}

@Composable fun HeroCard(state: AppState) {
    val spent = state.snapshot.expenses.sumOf { it.amount }
    val earned = state.snapshot.incomes.sumOf { it.amount }
    val net = earned - spent
    val budget = state.snapshot.budget
    Box(Modifier.fillMaxWidth().background(Brush.linearGradient(listOf(MaterialTheme.colorScheme.primary, MaterialTheme.colorScheme.secondary)), RoundedCornerShape(28.dp)).padding(22.dp)) {
        Column {
            Text("This period", color = Color.White.copy(alpha = 0.78f), style = MaterialTheme.typography.labelLarge)
            Text(money(net, state.snapshot.currency), color = Color.White, style = MaterialTheme.typography.headlineLarge)
            Spacer(Modifier.height(16.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Column(Modifier.clip(RoundedCornerShape(16.dp)).background(Color.White.copy(alpha = 0.16f)).padding(12.dp).weight(1f)) {
                    Text("Spent", color = Color.White.copy(alpha = 0.7f), style = MaterialTheme.typography.labelLarge)
                    Text(money(spent, state.snapshot.currency), color = Color.White, fontWeight = FontWeight.SemiBold)
                }
                Column(Modifier.clip(RoundedCornerShape(16.dp)).background(Color.White.copy(alpha = 0.16f)).padding(12.dp).weight(1f)) {
                    Text("In", color = Color.White.copy(alpha = 0.7f), style = MaterialTheme.typography.labelLarge)
                    Text(money(earned, state.snapshot.currency), color = Color.White, fontWeight = FontWeight.SemiBold)
                }
            }
            if (budget > 0) {
                val ratio = (spent / budget).toFloat().coerceIn(0f, 1f)
                Spacer(Modifier.height(14.dp))
                Text("${(ratio * 100).toInt()}% of ${money(budget, state.snapshot.currency)}", color = Color.White.copy(alpha = 0.85f), style = MaterialTheme.typography.bodySmall)
                Spacer(Modifier.height(8.dp))
                LinearProgressIndicator({ ratio }, Modifier.fillMaxWidth().height(8.dp).clip(RoundedCornerShape(99.dp)), Color.White, Color.White.copy(alpha = 0.22f))
            }
        }
    }
}

@Composable fun ExpenseRow(expense: Expense, currency: String, photoUrl: String, onClick: () -> Unit) {
    val initial = expense.store.trim().firstOrNull()?.uppercaseChar()?.toString() ?: "•"
    Card(Modifier.clickable(onClick = onClick), RoundedCornerShape(22.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface), elevation = CardDefaults.cardElevation(0.dp)) {
        Row(Modifier.fillMaxWidth().padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            RemotePhoto(photoUrl, initial, round = false, size = 44.dp)
            Spacer(Modifier.width(14.dp))
            Column(Modifier.weight(1f)) {
                Text(expense.store, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text(expense.entrySource.ifBlank { "manual" }, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.55f))
            }
            Text("−${money(expense.amount, currency)}", fontWeight = FontWeight.SemiBold, color = SpendsColors.Expense)
        }
    }
}

@Composable fun EmptyCard(title: String, body: String) = Card(shape = RoundedCornerShape(24.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
    Column(Modifier.fillMaxWidth().padding(28.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Text(title, style = MaterialTheme.typography.titleMedium)
        Text(body, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.65f))
    }
}

@Composable fun StatsHub(state: AppState, go: (Dest) -> Unit) {
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        Text("Statistics", style = MaterialTheme.typography.headlineMedium)
        SectionLabel(Icons.Outlined.ShowChart, "Ledger")
        YearExpenseChart(state)
        SectionLabel(Icons.Outlined.PieChart, "Breakdown")
        CategoryBars(state)
        SectionLabel(Icons.Outlined.CalendarMonth, "Calendar")
        CalendarHeatmap(state)
        SectionLabel(Icons.Outlined.Insights, "More")
        NavRow("Cash flow", "Month by month", Icons.Outlined.ShowChart, Dest.CashFlow, go)
        NavRow("Categories", "By store category", Icons.Outlined.Category, Dest.Categories, go)
        NavRow("Calendar", "Heatmap of spend", Icons.Outlined.CalendarMonth, Dest.Calendar, go)
        NavRow("Insights", "Quiet observations", Icons.Outlined.Insights, Dest.Insights, go)
        NavRow("Net position", "In versus out", Icons.Outlined.TrendingUp, Dest.NetWorth, go)
        NavRow("Year in review", "Wrapped", Icons.Outlined.WorkspacePremium, Dest.Wrapped, go)
        SectionLabel(Icons.Outlined.ShowChart, "Rates vs ${state.rates.base.ifBlank { state.snapshot.currency }}")
        Card(shape = RoundedCornerShape(20.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface), elevation = CardDefaults.cardElevation(0.dp)) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                state.rates.rates.entries.take(8).forEach { (code, value) -> Text("$code  ${"%.4f".format(value)}") }
            }
        }
        Spacer(Modifier.height(24.dp))
    }
}

@Composable fun SectionLabel(icon: ImageVector, title: String) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 6.dp)) {
        Icon(icon, null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(18.dp))
        Text(title, style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
    }
}

@Composable fun NavRow(title: String, subtitle: String, icon: ImageVector, dest: Dest, go: (Dest) -> Unit) {
    Card(Modifier.fillMaxWidth().clickable { go(dest) }, RoundedCornerShape(20.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface), elevation = CardDefaults.cardElevation(0.dp)) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            Box(Modifier.size(40.dp).clip(RoundedCornerShape(12.dp)).background(MaterialTheme.colorScheme.primaryContainer), contentAlignment = Alignment.Center) {
                Icon(icon, null, tint = MaterialTheme.colorScheme.onPrimaryContainer)
            }
            Column(Modifier.weight(1f)) { Text(title, fontWeight = FontWeight.SemiBold); Text(subtitle, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.55f)) }
            Icon(Icons.Outlined.ChevronRight, null)
        }
    }
}

@Composable fun CashFlowScreen(state: AppState) {
    val byMonth = state.snapshot.expenses.groupBy { it.date.take(7) }.mapValues { it.value.sumOf { e -> e.amount } }.toSortedMap()
    val max = byMonth.values.maxOrNull() ?: 1.0
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text("Cash flow", style = MaterialTheme.typography.headlineMedium)
        byMonth.forEach { (month, total) ->
            Text(month)
            LinearProgressIndicator({ (total / max).toFloat() }, Modifier.fillMaxWidth().height(10.dp).clip(RoundedCornerShape(99.dp)))
            Text(money(total, state.snapshot.currency), fontWeight = FontWeight.SemiBold)
        }
        if (byMonth.isEmpty()) EmptyCard("No months yet", "Add expenses to see cash flow.")
    }
}

@Composable fun CategoriesScreen(state: AppState) {
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text("Categories", style = MaterialTheme.typography.headlineMedium)
        CategoryBars(state)
    }
}

@Composable fun CalendarScreen(state: AppState) {
    Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text("Calendar", style = MaterialTheme.typography.headlineMedium)
        CalendarHeatmap(state)
    }
}

@Composable fun InsightsScreen(state: AppState) {
    val spent = state.snapshot.expenses.sumOf { it.amount }
    val top = state.snapshot.expenses.groupBy { it.store }.mapValues { it.value.sumOf { e -> e.amount } }.maxByOrNull { it.value }
    Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text("Insights", style = MaterialTheme.typography.headlineMedium)
        Text("You spent ${money(spent, state.snapshot.currency)} across ${state.snapshot.expenses.size} charges.")
        if (top != null) Text("${top.key} is the largest merchant at ${money(top.value, state.snapshot.currency)}.")
        if (state.snapshot.budget > 0) Text("Budget remaining: ${money(state.snapshot.budget - spent, state.snapshot.currency)}.")
        Text("${state.extras.pending.size} wallet captures waiting, ${state.extras.trash.size} in trash.")
    }
}

@Composable fun NetWorthScreen(state: AppState) {
    Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text("Net position", style = MaterialTheme.typography.headlineMedium)
        Text("In  ${money(state.snapshot.incomes.sumOf { it.amount }, state.snapshot.currency)}")
        Text("Out ${money(state.snapshot.expenses.sumOf { it.amount }, state.snapshot.currency)}")
        Text("Net ${money(state.snapshot.incomes.sumOf { it.amount } - state.snapshot.expenses.sumOf { it.amount }, state.snapshot.currency)}", fontWeight = FontWeight.Bold)
    }
}

@Composable fun WrappedScreen(state: AppState) {
    val year = LocalDate.now().year
    val yearSpend = state.snapshot.expenses.filter { it.date.startsWith("$year") }.sumOf { it.amount }
    Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text("Year in review", style = MaterialTheme.typography.headlineMedium)
        Text("$year · ${money(yearSpend, state.snapshot.currency)}")
        Text("${state.snapshot.stores.size} stores · ${state.snapshot.goals.size} goals · ${state.snapshot.tags.size} tags")
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable fun StoresScreen(state: AppState, model: SpendsViewModel, onStore: (Store) -> Unit) {
    var add by remember { mutableStateOf(false) }
    Box(Modifier.fillMaxSize()) {
        LazyVerticalGrid(GridCells.Fixed(2), Modifier.fillMaxSize(), contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            items(state.snapshot.stores, key = { it.id.ifBlank { it.name } }) { store ->
                Card(Modifier.fillMaxWidth().clickable { onStore(store) }, RoundedCornerShape(22.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
                    Column(Modifier.fillMaxWidth().padding(14.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        RemotePhoto(store.photoUrl, store.name.take(1).uppercase(), round = false, size = 92.dp)
                        Text(store.name, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    }
                }
            }
        }
        FloatingActionButton({ add = true }, Modifier.align(Alignment.BottomEnd).padding(20.dp), shape = CircleShape, containerColor = MaterialTheme.colorScheme.primary) { Icon(Icons.Outlined.Add, "Add store") }
    }
    if (add) AddStoreSheet(state, { add = false }) { name, cat -> model.saveStore(name, cat); add = false }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable private fun AddStoreSheet(state: AppState, onDismiss: () -> Unit, onSave: (String, String) -> Unit) {
    var name by remember { mutableStateOf("") }
    var cat by remember { mutableStateOf(state.snapshot.storeCategories.firstOrNull() ?: "other") }
    ModalBottomSheet(onDismissRequest = onDismiss, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp)) {
        Column(Modifier.padding(horizontal = 24.dp).padding(bottom = 36.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Text("New store", style = MaterialTheme.typography.headlineMedium)
            OutlinedTextField(name, { name = it }, Modifier.fillMaxWidth(), label = { Text("Name") }, shape = RoundedCornerShape(16.dp), singleLine = true)
            OutlinedTextField(cat, { cat = it }, Modifier.fillMaxWidth(), label = { Text("Category") }, shape = RoundedCornerShape(16.dp), singleLine = true)
            Button({ onSave(name, cat) }, Modifier.fillMaxWidth().height(54.dp), shape = RoundedCornerShape(18.dp), enabled = name.isNotBlank()) { Text("Save") }
        }
    }
}

@Composable fun ExpenseDetailScreen(state: AppState, expense: Expense, onTrash: () -> Unit) {
    val store = state.snapshot.stores.firstOrNull { it.name == expense.store }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Card(Modifier.fillMaxWidth(), RoundedCornerShape(24.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
            Column(Modifier.fillMaxWidth().padding(20.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(12.dp)) {
                RemotePhoto(store?.photoUrl.orEmpty(), expense.store.take(1).uppercase(), round = false, size = 88.dp)
                Text(expense.store, style = MaterialTheme.typography.headlineMedium)
                Text(store?.category ?: "store", color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.55f))
            }
        }
        Card(Modifier.fillMaxWidth(), RoundedCornerShape(22.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
            Column(Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text("Amount", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                Text("−${money(expense.amount, state.snapshot.currency)}", style = MaterialTheme.typography.headlineMedium, color = SpendsColors.Expense, fontWeight = FontWeight.Bold)
            }
        }
        Card(Modifier.fillMaxWidth(), RoundedCornerShape(22.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
            Column(Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text("Date", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                Text(prettyDate(expense.date), style = MaterialTheme.typography.titleMedium)
                Text(expense.date, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.55f))
            }
        }
        Card(Modifier.fillMaxWidth(), RoundedCornerShape(22.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
            Column(Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text("Source", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                Text(expense.entrySource.ifBlank { "manual" })
                if (expense.ocrRawStore.isNotBlank()) Text(expense.ocrRawStore, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f))
            }
        }
        Button(onTrash, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp), colors = ButtonDefaults.buttonColors(MaterialTheme.colorScheme.error)) { Text("Move to trash") }
    }
}

@Composable fun StoreDetailScreen(state: AppState, store: Store, onExpense: (Expense) -> Unit) {
    val rows = state.snapshot.expenses.filter { it.store == store.name }.sortedByDescending { it.date }
    val total = rows.sumOf { it.amount }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Card(Modifier.fillMaxWidth(), RoundedCornerShape(24.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
            Column(Modifier.fillMaxWidth().padding(20.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(12.dp)) {
                RemotePhoto(store.photoUrl, store.name.take(1).uppercase(), round = false, size = 96.dp)
                Text(store.name, style = MaterialTheme.typography.headlineMedium)
                Text(store.category, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.55f))
            }
        }
        Card(Modifier.fillMaxWidth(), RoundedCornerShape(22.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
            Column(Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text("Total spent", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                Text(money(total, state.snapshot.currency), style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
                Text("${rows.size} charges")
            }
        }
        rows.forEach { expense ->
            Card(Modifier.fillMaxWidth().clickable { onExpense(expense) }, RoundedCornerShape(18.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
                Row(Modifier.fillMaxWidth().padding(16.dp), horizontalArrangement = Arrangement.SpaceBetween) {
                    Column { Text(prettyDate(expense.date), fontWeight = FontWeight.SemiBold); Text(expense.date, style = MaterialTheme.typography.bodySmall) }
                    Text("−${money(expense.amount, state.snapshot.currency)}", color = SpendsColors.Expense, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

@Composable fun SettingsHub(state: AppState, go: (Dest) -> Unit, logout: () -> Unit) {
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text("Settings", style = MaterialTheme.typography.headlineMedium)
        Card(Modifier.fillMaxWidth(), RoundedCornerShape(22.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface), elevation = CardDefaults.cardElevation(0.dp)) {
            Row(Modifier.fillMaxWidth().padding(16.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                RemotePhoto(state.session.imageUrl, state.session.name.take(1).uppercase(), round = true, size = 56.dp, person = true)
                Column(Modifier.weight(1f)) {
                    Text(state.session.name.ifBlank { "Paired account" }, fontWeight = FontWeight.SemiBold)
                    Text(state.session.email, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.65f))
                    Text(state.session.endpoint, style = MaterialTheme.typography.bodySmall, maxLines = 1, overflow = TextOverflow.Ellipsis, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.45f))
                }
            }
        }
        SectionLabel(Icons.Outlined.Insights, "Overview")
        NavRow("Insights", "Read the ledger", Icons.Outlined.Insights, Dest.Insights, go)
        NavRow("Stores", "Merchants as accounts", Icons.Outlined.Storefront, Dest.Stores, go)
        SectionLabel(Icons.Outlined.Flag, "Planning")
        NavRow("Goals", "Savings and spending targets", Icons.Outlined.Flag, Dest.Goals, go)
        NavRow("Pending", "Wallet captures", Icons.Outlined.History, Dest.Pending, go)
        SectionLabel(Icons.Outlined.Category, "Organization")
        NavRow("Categories", "Store categories", Icons.Outlined.Category, Dest.Categories, go)
        NavRow("Tags", "Labels on spend", Icons.Outlined.Label, Dest.Tags, go)
        NavRow("Trash", "Recently deleted", Icons.Outlined.Delete, Dest.Trash, go)
        SectionLabel(Icons.Outlined.Tune, "System")
        NavRow("Server sync", "Pairing and endpoint", Icons.Outlined.Cloud, Dest.Sync, go)
        NavRow("Appearance", "Theme, accent, language", Icons.Outlined.Palette, Dest.Appearance, go)
        NavRow("Preferences", "Budget, currency, flags", Icons.Outlined.Tune, Dest.Prefs, go)
        NavRow("Data management", "Backups", Icons.Outlined.Backup, Dest.Data, go)
        Spacer(Modifier.height(8.dp))
        Button(logout, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp), colors = ButtonDefaults.buttonColors(MaterialTheme.colorScheme.error)) { Text("Disconnect") }
        Text("Spends 1.0.0 · made to help your money make cents.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f))
    }
}

@Composable fun GoalsScreen(state: AppState, model: SpendsViewModel) {
    var name by remember { mutableStateOf("") }; var target by remember { mutableStateOf("") }
    Column(Modifier.verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text("Goals", style = MaterialTheme.typography.headlineMedium)
        OutlinedTextField(name, { name = it }, Modifier.fillMaxWidth(), label = { Text("Name") }, shape = RoundedCornerShape(16.dp))
        OutlinedTextField(target, { target = it }, Modifier.fillMaxWidth(), label = { Text("Target") }, shape = RoundedCornerShape(16.dp))
        Button({ model.saveGoal(Goal(name = name, targetAmount = target.toDoubleOrNull() ?: 0.0, currency = state.snapshot.currency)); name = ""; target = "" }, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp)) { Text("Add goal") }
        state.snapshot.goals.forEach { g ->
            Card(shape = RoundedCornerShape(18.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
                Column(Modifier.padding(16.dp)) {
                    Text(g.name, fontWeight = FontWeight.SemiBold)
                    LinearProgressIndicator({ (g.progressPercent / 100.0).toFloat().coerceIn(0f, 1f) }, Modifier.fillMaxWidth().height(8.dp).clip(RoundedCornerShape(99.dp)))
                    Text("${money(g.currentAmount, g.currency)} / ${money(g.targetAmount, g.currency)}")
                    Row { TextButton({ model.contributeGoal(g.id, "10") }) { Text("+10") }; TextButton({ model.deleteGoal(g.id) }) { Text("Delete") } }
                }
            }
        }
    }
}

@Composable fun TagsScreen(state: AppState, model: SpendsViewModel) {
    var name by remember { mutableStateOf("") }
    Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text("Tags", style = MaterialTheme.typography.headlineMedium)
        OutlinedTextField(name, { name = it }, Modifier.fillMaxWidth(), label = { Text("Tag") }, shape = RoundedCornerShape(16.dp))
        Button({ model.saveTag(name, "#4F46E5"); name = "" }, shape = RoundedCornerShape(16.dp)) { Text("Add") }
        state.snapshot.tags.forEach { t ->
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Text("${t.name} · ${t.usageCount}")
                TextButton({ model.deleteTag(t.id) }) { Text("Delete") }
            }
        }
    }
}

@Composable fun TrashScreen(state: AppState, model: SpendsViewModel) {
    Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text("Trash", style = MaterialTheme.typography.headlineMedium)
        if (state.extras.trash.isEmpty()) EmptyCard("Empty", "Deleted expenses land here for restore.")
        state.extras.trash.forEach { e ->
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text("${e.store} ${money(e.amount, state.snapshot.currency)}")
                TextButton({ model.restoreTrash(e) }) { Text("Restore") }
            }
        }
        if (state.extras.trash.isNotEmpty()) OutlinedButton(model::emptyTrash) { Text("Empty trash") }
    }
}

@Composable fun PendingScreen(state: AppState, model: SpendsViewModel) {
    Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text("Pending from Wallet", style = MaterialTheme.typography.headlineMedium)
        Text("Enable notification access for Spends to capture Google Wallet charges.")
        if (state.extras.pending.isEmpty()) EmptyCard("None waiting", "Wallet notifications will appear here.")
        state.extras.pending.forEach { p ->
            Card(shape = RoundedCornerShape(18.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
                Column(Modifier.padding(16.dp)) {
                    Text(p.store, fontWeight = FontWeight.SemiBold)
                    Text("${p.date} · ${money(p.amount, state.snapshot.currency)}")
                    Row { TextButton({ model.confirmPending(p) }) { Text("Keep") }; TextButton({ model.dismissPending(p) }) { Text("Skip") } }
                }
            }
        }
    }
}

@Composable fun PrefsScreen(state: AppState, model: SpendsViewModel) {
    var budget by remember(state.snapshot.budget) { mutableStateOf(state.snapshot.budget.toString()) }
    var currency by remember(state.snapshot.currency) { mutableStateOf(state.snapshot.currency) }
    var incomes by remember(state.snapshot.flags.showIncomes) { mutableStateOf(state.snapshot.flags.showIncomes) }
    var trend by remember(state.snapshot.flags.showTrend) { mutableStateOf(state.snapshot.flags.showTrend) }
    var trim by remember(state.snapshot.flags.trimEmptyMonths) { mutableStateOf(state.snapshot.flags.trimEmptyMonths) }
    Column(Modifier.padding(20.dp).verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text("Preferences", style = MaterialTheme.typography.headlineMedium)
        OutlinedTextField(budget, { budget = it }, Modifier.fillMaxWidth(), label = { Text("Monthly budget") }, shape = RoundedCornerShape(16.dp))
        OutlinedTextField(currency, { currency = it.uppercase() }, Modifier.fillMaxWidth(), label = { Text("Currency") }, shape = RoundedCornerShape(16.dp))
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) { Text("Show incomes"); Switch(incomes, { incomes = it }) }
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) { Text("Show trend"); Switch(trend, { trend = it }) }
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) { Text("Trim empty months"); Switch(trim, { trim = it }) }
        Button({ model.saveSettings(budget, currency, incomes, trend, trim) }, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp)) { Text("Save") }
        Text("Language, theme, formatting, privacy, reminders, sound, toast, appearance, location, transfers, trash retention, and pending-capture follow the paired server plus Android system settings.")
    }
}

@Composable fun DataScreen(state: AppState, model: SpendsViewModel) {
    var name by remember { mutableStateOf("mobile") }
    Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text("Data management", style = MaterialTheme.typography.headlineMedium)
        OutlinedTextField(name, { name = it }, Modifier.fillMaxWidth(), label = { Text("Backup name") }, shape = RoundedCornerShape(16.dp))
        Button({ model.saveBackup(name) }, shape = RoundedCornerShape(16.dp)) { Text("Save backup") }
        state.backups.forEach { b ->
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text(b.name, modifier = Modifier.weight(1f), maxLines = 1, overflow = TextOverflow.Ellipsis)
                TextButton({ model.restoreBackup(b.name) }) { Text("Restore") }
                TextButton({ model.deleteBackup(b.name) }) { Text("Delete") }
            }
        }
    }
}

@Composable fun SyncScreen(state: AppState, model: SpendsViewModel, onLogs: () -> Unit) {
    Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text("Server sync", style = MaterialTheme.typography.headlineMedium)
        ConnectionCard(state, model)
        Text(state.session.endpoint, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.65f))
        Text("Last sync ${if (state.snapshot.syncedAt == 0L) "—" else java.text.DateFormat.getDateTimeInstance().format(java.util.Date(state.snapshot.syncedAt))}")
        Button(onLogs, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp)) { Text("Logs") }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable fun TransactionSheet(kind: String, onDismiss: () -> Unit, saveExpense: (String, String, String) -> Unit, saveIncome: (String, String) -> Unit) {
    val context = androidx.compose.ui.platform.LocalContext.current
    var date by remember { mutableStateOf(LocalDate.now().toString()) }
    var merchant by remember { mutableStateOf("") }
    var amount by remember { mutableStateOf("") }
    val pickStore = androidx.activity.compose.rememberLauncherForActivityResult(androidx.activity.result.contract.ActivityResultContracts.StartActivityForResult()) { result ->
        if (result.resultCode == android.app.Activity.RESULT_OK) merchant = result.data?.getStringExtra(xyz.serverkherson.spends.PickStoreActivity.EXTRA_STORE).orEmpty()
    }
    ModalBottomSheet(onDismissRequest = onDismiss, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp)) {
        Column(Modifier.padding(horizontal = 24.dp).padding(bottom = 36.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Text(if (kind == "income") "New income" else "New expense", style = MaterialTheme.typography.headlineMedium)
            OutlinedTextField(date, { date = it }, Modifier.fillMaxWidth(), label = { Text("Date") }, shape = RoundedCornerShape(16.dp), singleLine = true)
            if (kind != "income") {
                Card(Modifier.fillMaxWidth().clickable { pickStore.launch(android.content.Intent(context, xyz.serverkherson.spends.PickStoreActivity::class.java)) }, RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surfaceVariant)) {
                    Row(Modifier.fillMaxWidth().padding(16.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                        Text(merchant.ifBlank { "Choose store" }, fontWeight = FontWeight.SemiBold)
                        Icon(Icons.Outlined.Storefront, null)
                    }
                }
            }
            OutlinedTextField(amount, { amount = it }, Modifier.fillMaxWidth(), label = { Text("Amount") }, shape = RoundedCornerShape(16.dp), singleLine = true)
            Button({
                if (kind == "income") saveIncome(date, amount) else saveExpense(date, merchant, amount)
            }, Modifier.fillMaxWidth().height(54.dp), shape = RoundedCornerShape(18.dp)) { Text("Save") }
        }
    }
}

@Composable fun DetailScreen(title: String, onBack: () -> Unit, content: @Composable () -> Unit) {
    Column(Modifier.fillMaxSize().background(canvasBrush())) {
        Row(Modifier.padding(8.dp), verticalAlignment = Alignment.CenterVertically) {
            IconButton(onBack) { Icon(Icons.AutoMirrored.Outlined.ArrowBack, "Back") }
            Text(title, style = MaterialTheme.typography.titleLarge)
        }
        Box(Modifier.weight(1f).fillMaxWidth()) { content() }
    }
}

@Composable fun SpeedDial(onPick: (String) -> Unit) {
    Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(10.dp)) {
        MiniFab("Income", Icons.Outlined.TrendingUp, SpendsColors.Income) { onPick("income") }
        MiniFab("Expense", Icons.Outlined.TrendingDown, SpendsColors.Expense) { onPick("expense") }
        MiniFab("OCR", Icons.Outlined.CameraAlt, MaterialTheme.colorScheme.primary) { onPick("ocr") }
    }
}

@Composable private fun MiniFab(label: String, icon: ImageVector, color: Color, onClick: () -> Unit) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(label, style = MaterialTheme.typography.labelLarge)
        FloatingActionButton(onClick, Modifier.size(48.dp), shape = CircleShape, containerColor = color, contentColor = Color.White) { Icon(icon, label) }
    }
}

@Composable fun SpendsNavBar(tab: Int, onTab: (Int) -> Unit, onAdd: () -> Unit) {
    androidx.compose.material3.Surface(color = MaterialTheme.colorScheme.surface.copy(alpha = 0.96f)) {
        Row(Modifier.fillMaxWidth().navigationBarsPadding().height(72.dp), verticalAlignment = Alignment.CenterVertically) {
            NavSlot("Home", Icons.Outlined.Wallet, tab == 0, Modifier.weight(1f)) { onTab(0) }
            Box(Modifier.weight(1f), contentAlignment = Alignment.Center) {
                FloatingActionButton(onAdd, Modifier.size(56.dp).offset(y = (-10).dp), shape = CircleShape, containerColor = MaterialTheme.colorScheme.primary) {
                    Icon(Icons.Outlined.Add, "Add")
                }
            }
            NavSlot("Stats", Icons.Outlined.Insights, tab == 1, Modifier.weight(1f)) { onTab(1) }
            NavSlot("Settings", Icons.Outlined.Settings, tab == 2, Modifier.weight(1f)) { onTab(2) }
        }
    }
}

@Composable private fun NavSlot(label: String, icon: ImageVector, selected: Boolean, modifier: Modifier, onClick: () -> Unit) {
    val color = if (selected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurface.copy(alpha = 0.62f)
    Column(modifier.clickable(onClick = onClick).padding(vertical = 8.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(2.dp)) {
        Icon(icon, label, tint = color)
        Text(label, style = MaterialTheme.typography.labelSmall, color = color)
    }
}

@Composable fun ErrorDialog(message: String?, dismiss: () -> Unit) {
    if (message != null) AlertDialog(dismiss, title = { Text("Something went wrong") }, text = { Text(message) }, confirmButton = { TextButton(dismiss) { Text("OK") } })
}

private fun prettyDate(raw: String): String = try {
    LocalDate.parse(raw).format(DateTimeFormatter.ofPattern("EEEE, d MMM", Locale.getDefault()))
} catch (_: DateTimeParseException) { raw }
