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
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.Add
import androidx.compose.material.icons.outlined.CameraAlt
import androidx.compose.material.icons.outlined.ChevronRight
import androidx.compose.material.icons.outlined.Insights
import androidx.compose.material.icons.outlined.Payments
import androidx.compose.material.icons.outlined.QrCodeScanner
import androidx.compose.material.icons.outlined.Settings
import androidx.compose.material.icons.outlined.SwapHoriz
import androidx.compose.material.icons.outlined.TrendingDown
import androidx.compose.material.icons.outlined.TrendingUp
import androidx.compose.material.icons.outlined.Wallet
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

enum class Dest { Home, Stats, Stores, Settings, Insights, CashFlow, Categories, Calendar, Wrapped, NetWorth, Goals, Tags, Trash, Pending, Bills, Recurring, Prefs, Data, Sync, Transaction, Ocr }

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PairingScreen(initialLink: String, model: SpendsViewModel, onScan: () -> Unit) {
    var link by remember(initialLink) { mutableStateOf(initialLink) }
    var token by remember { mutableStateOf("") }
    LaunchedEffect(initialLink) {
        if (initialLink.contains("token=")) model.pair(initialLink, "") {}
    }
    Box(Modifier.fillMaxSize().background(canvasBrush()).navigationBarsPadding()) {
        Column(Modifier.fillMaxSize().padding(28.dp), verticalArrangement = Arrangement.Center) {
            Box(Modifier.size(64.dp).clip(RoundedCornerShape(20.dp)).background(MaterialTheme.colorScheme.primary), contentAlignment = Alignment.Center) {
                Icon(Icons.Outlined.Payments, null, tint = MaterialTheme.colorScheme.onPrimary, modifier = Modifier.size(32.dp))
            }
            Spacer(Modifier.height(24.dp))
            Text("Spends", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
            Text("Your money, quietly in order", style = MaterialTheme.typography.headlineMedium)
            Spacer(Modifier.height(8.dp))
            Text("Scan the pairing QR from AppDashboard, or paste the link.", color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f))
            Spacer(Modifier.height(24.dp))
            OutlinedTextField(link, { link = it }, Modifier.fillMaxWidth(), label = { Text("Pairing link") }, shape = RoundedCornerShape(20.dp), minLines = 2)
            Spacer(Modifier.height(10.dp))
            OutlinedTextField(token, { token = it }, Modifier.fillMaxWidth(), label = { Text("Token") }, shape = RoundedCornerShape(20.dp), singleLine = true)
            Spacer(Modifier.height(16.dp))
            Button({ model.pair(link, token) {} }, Modifier.fillMaxWidth().height(54.dp), shape = RoundedCornerShape(18.dp)) { Text("Connect securely") }
            Spacer(Modifier.height(8.dp))
            OutlinedButton(onScan, Modifier.fillMaxWidth().height(50.dp), shape = RoundedCornerShape(18.dp)) { Icon(Icons.Outlined.QrCodeScanner, null); Spacer(Modifier.width(8.dp)); Text("Scan QR") }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(state: AppState, model: SpendsViewModel, onOpen: (Expense) -> Unit) {
    val groups = remember(state.snapshot.expenses) { state.snapshot.expenses.sortedByDescending { it.date }.groupBy { it.date } }
    PullToRefreshBox(state.loading, model::refresh, Modifier.fillMaxSize()) {
        LazyColumn(Modifier.fillMaxSize().padding(horizontal = 20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            item { Spacer(Modifier.height(12.dp)); Greeting(state); Spacer(Modifier.height(4.dp)); HeroCard(state) }
            if (groups.isEmpty()) item { EmptyCard("A quiet ledger", "Add expense, income, transfer, or a receipt.") }
            groups.forEach { (date, rows) ->
                item { Text(prettyDate(date), style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.55f), modifier = Modifier.padding(top = 8.dp, start = 4.dp)) }
                items(rows, key = { "${it.date}-${it.store}-${it.amount}-${it.signature}" }) { row ->
                    ExpenseRow(row, state.snapshot.currency) { onOpen(row) }
                }
            }
            item { Spacer(Modifier.height(110.dp)) }
        }
    }
}

@Composable fun Greeting(state: AppState) {
    val name = state.session.name.ifBlank { state.session.email.substringBefore('@') }.ifBlank { "there" }
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
        Column {
            Text("Good to see you", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.55f))
            Text(name, style = MaterialTheme.typography.headlineMedium)
        }
        AnimatedVisibility(state.loading) { CircularProgressIndicator(Modifier.size(22.dp), strokeWidth = 2.dp) }
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

@Composable fun ExpenseRow(expense: Expense, currency: String, onClick: () -> Unit) {
    val initial = expense.store.trim().firstOrNull()?.uppercaseChar()?.toString() ?: "•"
    Card(Modifier.clickable(onClick = onClick), RoundedCornerShape(22.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface), elevation = CardDefaults.cardElevation(0.dp)) {
        Row(Modifier.fillMaxWidth().padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.size(44.dp).clip(RoundedCornerShape(14.dp)).background(MaterialTheme.colorScheme.primaryContainer), contentAlignment = Alignment.Center) {
                Text(initial, fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onPrimaryContainer)
            }
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
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text("Statistics", style = MaterialTheme.typography.headlineMedium)
        NavRow("Cash flow", "Month by month", Dest.CashFlow, go)
        NavRow("Categories", "By store category", Dest.Categories, go)
        NavRow("Calendar", "Heatmap of spend", Dest.Calendar, go)
        NavRow("Insights", "Quiet observations", Dest.Insights, go)
        NavRow("Net position", "In versus out", Dest.NetWorth, go)
        NavRow("Year in review", "Wrapped", Dest.Wrapped, go)
        Spacer(Modifier.height(12.dp))
        Text("Rates vs ${state.rates.base.ifBlank { state.snapshot.currency }}", style = MaterialTheme.typography.titleMedium)
        state.rates.rates.entries.take(8).forEach { (code, value) -> Text("$code  ${"%.4f".format(value)}", color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)) }
    }
}

@Composable fun NavRow(title: String, subtitle: String, dest: Dest, go: (Dest) -> Unit) {
    Card(Modifier.fillMaxWidth().clickable { go(dest) }, RoundedCornerShape(20.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface), elevation = CardDefaults.cardElevation(0.dp)) {
        Row(Modifier.padding(18.dp), verticalAlignment = Alignment.CenterVertically) {
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
    val catOf = state.snapshot.stores.associate { it.name to it.category }
    val totals = state.snapshot.expenses.groupBy { catOf[it.store] ?: "other" }.mapValues { it.value.sumOf { e -> e.amount } }.toList().sortedByDescending { it.second }
    val max = totals.maxOfOrNull { it.second } ?: 1.0
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text("Categories", style = MaterialTheme.typography.headlineMedium)
        totals.forEach { (cat, total) ->
            Text(cat.replaceFirstChar { it.titlecase(Locale.getDefault()) }, fontWeight = FontWeight.Medium)
            LinearProgressIndicator({ (total / max).toFloat() }, Modifier.fillMaxWidth().height(8.dp).clip(RoundedCornerShape(99.dp)))
            Text(money(total, state.snapshot.currency))
        }
        if (totals.isEmpty()) EmptyCard("No categories", "Stores will group spend once they exist.")
    }
}

@Composable fun CalendarScreen(state: AppState) {
    val byDay = state.snapshot.expenses.groupBy { it.date }.mapValues { it.value.sumOf { e -> e.amount } }
    val month = YearMonth.now()
    Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text("Calendar", style = MaterialTheme.typography.headlineMedium)
        Text(month.toString())
        (1..month.lengthOfMonth()).forEach { day ->
            val key = "%s-%02d".format(month, day)
            val v = byDay[key] ?: 0.0
            if (v > 0) Text("$key  ${money(v, state.snapshot.currency)}")
        }
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

@Composable fun StoresScreen(state: AppState, model: SpendsViewModel) {
    var name by remember { mutableStateOf("") }
    var cat by remember { mutableStateOf(state.snapshot.storeCategories.firstOrNull() ?: "other") }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text("Stores", style = MaterialTheme.typography.headlineMedium)
        OutlinedTextField(name, { name = it }, Modifier.fillMaxWidth(), label = { Text("New store") }, shape = RoundedCornerShape(16.dp))
        OutlinedTextField(cat, { cat = it }, Modifier.fillMaxWidth(), label = { Text("Category") }, shape = RoundedCornerShape(16.dp))
        Button({ model.saveStore(name, cat); name = "" }, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp)) { Text("Add store") }
        state.snapshot.stores.forEach { store ->
            Card(shape = RoundedCornerShape(18.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
                Row(Modifier.fillMaxWidth().padding(16.dp), horizontalArrangement = Arrangement.SpaceBetween) {
                    Column { Text(store.name, fontWeight = FontWeight.SemiBold); Text(store.category, style = MaterialTheme.typography.bodySmall) }
                    Text(money(state.snapshot.expenses.filter { it.store == store.name }.sumOf { it.amount }, state.snapshot.currency))
                }
            }
        }
    }
}

@Composable fun SettingsHub(state: AppState, go: (Dest) -> Unit, logout: () -> Unit) {
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text("Settings", style = MaterialTheme.typography.headlineMedium)
        Text(state.session.email.ifBlank { state.session.name }, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.65f))
        NavRow("Insights", "Read the ledger", Dest.Insights, go)
        NavRow("Stores", "Merchants as accounts", Dest.Stores, go)
        Text("Planning", style = MaterialTheme.typography.labelLarge)
        NavRow("Goals", "Savings and spending targets", Dest.Goals, go)
        NavRow("Pending", "Wallet captures", Dest.Pending, go)
        NavRow("Bill splitter", "Settle a shared check", Dest.Bills, go)
        NavRow("Recurring", "Subscriptions", Dest.Recurring, go)
        Text("Organization", style = MaterialTheme.typography.labelLarge)
        NavRow("Categories", "Store categories", Dest.Categories, go)
        NavRow("Tags", "Labels on spend", Dest.Tags, go)
        NavRow("Trash", "Recently deleted", Dest.Trash, go)
        Text("System", style = MaterialTheme.typography.labelLarge)
        NavRow("Server sync", "Pairing and endpoint", Dest.Sync, go)
        NavRow("Preferences", "Budget, currency, flags", Dest.Prefs, go)
        NavRow("Data management", "Backups", Dest.Data, go)
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

@Composable fun BillsScreen() {
    var people by remember { mutableStateOf("Alex, Sam") }
    var total by remember { mutableStateOf("") }
    val names = people.split(',').map { it.trim() }.filter { it.isNotEmpty() }
    val amount = total.replace(',', '.').toDoubleOrNull()
    val share = if (amount != null && names.isNotEmpty()) amount / names.size else null
    Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text("Bill splitter", style = MaterialTheme.typography.headlineMedium)
        OutlinedTextField(people, { people = it }, Modifier.fillMaxWidth(), label = { Text("Names") }, shape = RoundedCornerShape(16.dp))
        OutlinedTextField(total, { total = it }, Modifier.fillMaxWidth(), label = { Text("Total") }, shape = RoundedCornerShape(16.dp))
        if (share != null) names.forEach { Text("$it owes ${"%.2f".format(share)}") }
    }
}

@Composable fun RecurringScreen(state: AppState, model: SpendsViewModel) {
    var store by remember { mutableStateOf("") }; var amount by remember { mutableStateOf("") }; var day by remember { mutableStateOf("1") }
    Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text("Recurring", style = MaterialTheme.typography.headlineMedium)
        OutlinedTextField(store, { store = it }, Modifier.fillMaxWidth(), label = { Text("Store") }, shape = RoundedCornerShape(16.dp))
        OutlinedTextField(amount, { amount = it }, Modifier.fillMaxWidth(), label = { Text("Amount") }, shape = RoundedCornerShape(16.dp))
        OutlinedTextField(day, { day = it }, Modifier.fillMaxWidth(), label = { Text("Day of month") }, shape = RoundedCornerShape(16.dp))
        Button({ model.addSubscription(store, amount, day) }, shape = RoundedCornerShape(16.dp)) { Text("Add") }
        state.snapshot.subscriptions.forEach { s ->
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text("${s.store} ${s.amount} · ${s.frequency}")
                TextButton({ model.deleteSubscription(s.id) }) { Text("Delete") }
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

@Composable fun SyncScreen(state: AppState) {
    Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text("Server sync", style = MaterialTheme.typography.headlineMedium)
        Text(state.session.endpoint)
        Text(state.session.email)
        Text("Last sync ${if (state.snapshot.syncedAt == 0L) "—" else java.text.DateFormat.getDateTimeInstance().format(java.util.Date(state.snapshot.syncedAt))}")
        Text("Pull writes the local snapshot; pushes use signatures so wallet, OCR, and manual entries are not double-counted.")
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable fun TransactionSheet(kind: String, stores: List<Store>, onDismiss: () -> Unit, saveExpense: (String, String, String) -> Unit, saveIncome: (String, String) -> Unit, saveTransfer: (String, String, String, String) -> Unit) {
    var date by remember { mutableStateOf(LocalDate.now().toString()) }
    var merchant by remember { mutableStateOf(stores.firstOrNull()?.name.orEmpty()) }
    var to by remember { mutableStateOf(stores.getOrNull(1)?.name.orEmpty()) }
    var amount by remember { mutableStateOf("") }
    ModalBottomSheet(onDismissRequest = onDismiss, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp)) {
        Column(Modifier.padding(horizontal = 24.dp).padding(bottom = 36.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Text(when (kind) { "income" -> "New income"; "transfer" -> "Transfer"; "ocr" -> "From receipt"; else -> "New expense" }, style = MaterialTheme.typography.headlineMedium)
            OutlinedTextField(date, { date = it }, Modifier.fillMaxWidth(), label = { Text("Date") }, shape = RoundedCornerShape(16.dp), singleLine = true)
            if (kind != "income") OutlinedTextField(merchant, { merchant = it }, Modifier.fillMaxWidth(), label = { Text(if (kind == "transfer") "From store" else "Merchant") }, shape = RoundedCornerShape(16.dp), singleLine = true)
            if (kind == "transfer") OutlinedTextField(to, { to = it }, Modifier.fillMaxWidth(), label = { Text("To store") }, shape = RoundedCornerShape(16.dp), singleLine = true)
            OutlinedTextField(amount, { amount = it }, Modifier.fillMaxWidth(), label = { Text("Amount") }, shape = RoundedCornerShape(16.dp), singleLine = true)
            Button({
                when (kind) {
                    "income" -> saveIncome(date, amount)
                    "transfer" -> saveTransfer(date, merchant, to, amount)
                    else -> saveExpense(date, merchant, amount)
                }
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
        content()
    }
}

@Composable fun FabMenu(expanded: Boolean, onToggle: () -> Unit, onPick: (String) -> Unit) {
    Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.BottomEnd) {
        if (expanded) {
            Column(horizontalAlignment = Alignment.End, verticalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.padding(end = 8.dp, bottom = 72.dp)) {
                MiniFab("Income", Icons.Outlined.TrendingDown, SpendsColors.Income) { onPick("income") }
                MiniFab("Expense", Icons.Outlined.TrendingUp, SpendsColors.Expense) { onPick("expense") }
                MiniFab("Transfer", Icons.Outlined.SwapHoriz, MaterialTheme.colorScheme.secondary) { onPick("transfer") }
                MiniFab("OCR", Icons.Outlined.CameraAlt, MaterialTheme.colorScheme.primary) { onPick("ocr") }
            }
        }
        FloatingActionButton(onToggle, shape = CircleShape, containerColor = MaterialTheme.colorScheme.primary) { Icon(Icons.Outlined.Add, "Add") }
    }
}

@Composable private fun MiniFab(label: String, icon: ImageVector, color: Color, onClick: () -> Unit) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(label, style = MaterialTheme.typography.labelLarge)
        FloatingActionButton(onClick, Modifier.size(48.dp), shape = CircleShape, containerColor = color, contentColor = Color.White) { Icon(icon, label) }
    }
}

@Composable fun SpendsNavBar(tab: Int, onTab: (Int) -> Unit) {
    NavigationBar(containerColor = MaterialTheme.colorScheme.surface.copy(alpha = 0.94f)) {
        NavigationBarItem(tab == 0, { onTab(0) }, { Icon(Icons.Outlined.Wallet, null) }, label = { Text("Home") })
        NavigationBarItem(tab == 1, { onTab(1) }, { Icon(Icons.Outlined.Insights, null) }, label = { Text("Stats") })
        NavigationBarItem(tab == 2, { onTab(2) }, { Icon(Icons.Outlined.Payments, null) }, label = { Text("Stores") })
        NavigationBarItem(tab == 3, { onTab(3) }, { Icon(Icons.Outlined.Settings, null) }, label = { Text("Settings") })
    }
}

@Composable fun ErrorDialog(message: String?, dismiss: () -> Unit) {
    if (message != null) AlertDialog(dismiss, title = { Text("Something went wrong") }, text = { Text(message) }, confirmButton = { TextButton(dismiss) { Text("OK") } })
}

private fun prettyDate(raw: String): String = try {
    LocalDate.parse(raw).format(DateTimeFormatter.ofPattern("EEEE, d MMM", Locale.getDefault()))
} catch (_: DateTimeParseException) { raw }
