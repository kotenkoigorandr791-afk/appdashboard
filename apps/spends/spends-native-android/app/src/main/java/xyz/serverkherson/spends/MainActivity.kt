package xyz.serverkherson.spends

import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.lifecycle.viewmodel.compose.viewModel
import xyz.serverkherson.spends.data.Expense
import xyz.serverkherson.spends.ui.BillsScreen
import xyz.serverkherson.spends.ui.CalendarScreen
import xyz.serverkherson.spends.ui.CashFlowScreen
import xyz.serverkherson.spends.ui.CategoriesScreen
import xyz.serverkherson.spends.ui.DataScreen
import xyz.serverkherson.spends.ui.Dest
import xyz.serverkherson.spends.ui.DetailScreen
import xyz.serverkherson.spends.ui.ErrorDialog
import xyz.serverkherson.spends.ui.FabMenu
import xyz.serverkherson.spends.ui.GoalsScreen
import xyz.serverkherson.spends.ui.HomeScreen
import xyz.serverkherson.spends.ui.InsightsScreen
import xyz.serverkherson.spends.ui.NetWorthScreen
import xyz.serverkherson.spends.ui.PairingScreen
import xyz.serverkherson.spends.ui.PendingScreen
import xyz.serverkherson.spends.ui.PrefsScreen
import xyz.serverkherson.spends.ui.RecurringScreen
import xyz.serverkherson.spends.ui.SettingsHub
import xyz.serverkherson.spends.ui.SpendsNavBar
import xyz.serverkherson.spends.ui.SpendsTheme
import xyz.serverkherson.spends.ui.StatsHub
import xyz.serverkherson.spends.ui.StoresScreen
import xyz.serverkherson.spends.ui.SyncScreen
import xyz.serverkherson.spends.ui.TagsScreen
import xyz.serverkherson.spends.ui.TransactionSheet
import xyz.serverkherson.spends.ui.TrashScreen
import xyz.serverkherson.spends.ui.WrappedScreen
import xyz.serverkherson.spends.ui.canvasBrush

class MainActivity : ComponentActivity() {
    private var pairingLink: String? = null
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        pairingLink = intent?.dataString
        val app = application as SpendsApplication
        setContent { SpendsTheme { SpendsApp(app, pairingLink) } }
    }
    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        pairingLink = intent.dataString
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable private fun SpendsApp(app: SpendsApplication, pairingLink: String?) {
    val model: SpendsViewModel = viewModel { SpendsViewModel(app.store, app.api) }
    val state by model.state.collectAsState()
    var tab by remember { mutableIntStateOf(0) }
    var dest by remember { mutableStateOf<Dest?>(null) }
    var fabOpen by remember { mutableStateOf(false) }
    var sheet by remember { mutableStateOf<String?>(null) }
    var scan by remember { mutableStateOf(false) }
    var scanText by remember { mutableStateOf("") }
    var selected by remember { mutableStateOf<Expense?>(null) }

    if (state.session.token.isBlank()) {
        PairingScreen(pairingLink.orEmpty(), model) { scan = true }
        if (scan) AlertDialog(
            onDismissRequest = { scan = false },
            title = { Text("Pairing QR") },
            text = { OutlinedTextField(scanText, { scanText = it }, label = { Text("Paste spends://auth link") }) },
            confirmButton = { TextButton({ model.pair(scanText, "") { scan = false } }) { Text("Connect") } },
            dismissButton = { TextButton({ scan = false }) { Text("Cancel") } },
        )
        ErrorDialog(state.error, model::dismissError)
        return
    }

    Scaffold(
        containerColor = androidx.compose.ui.graphics.Color.Transparent,
        modifier = Modifier.background(canvasBrush()),
        bottomBar = { if (dest == null) SpendsNavBar(tab) { tab = it; fabOpen = false } },
        floatingActionButton = {
            if (dest == null && tab == 0) FabMenu(fabOpen, { fabOpen = !fabOpen }) { kind -> fabOpen = false; sheet = kind }
        },
    ) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            when (dest) {
                Dest.Insights -> DetailScreen("Insights", { dest = null }) { InsightsScreen(state) }
                Dest.CashFlow -> DetailScreen("Cash flow", { dest = null }) { CashFlowScreen(state) }
                Dest.Categories -> DetailScreen("Categories", { dest = null }) { CategoriesScreen(state) }
                Dest.Calendar -> DetailScreen("Calendar", { dest = null }) { CalendarScreen(state) }
                Dest.Wrapped -> DetailScreen("Wrapped", { dest = null }) { WrappedScreen(state) }
                Dest.NetWorth -> DetailScreen("Net position", { dest = null }) { NetWorthScreen(state) }
                Dest.Goals -> DetailScreen("Goals", { dest = null }) { GoalsScreen(state, model) }
                Dest.Tags -> DetailScreen("Tags", { dest = null }) { TagsScreen(state, model) }
                Dest.Trash -> DetailScreen("Trash", { dest = null }) { TrashScreen(state, model) }
                Dest.Pending -> DetailScreen("Pending", { dest = null }) { PendingScreen(state, model) }
                Dest.Bills -> DetailScreen("Bill splitter", { dest = null }) { BillsScreen() }
                Dest.Recurring -> DetailScreen("Recurring", { dest = null }) { RecurringScreen(state, model) }
                Dest.Prefs -> DetailScreen("Preferences", { dest = null }) { PrefsScreen(state, model) }
                Dest.Data -> DetailScreen("Data", { dest = null }) { DataScreen(state, model) }
                Dest.Sync -> DetailScreen("Server sync", { dest = null }) { SyncScreen(state) }
                Dest.Stores -> DetailScreen("Stores", { dest = null }) { StoresScreen(state, model) }
                else -> when (tab) {
                    0 -> HomeScreen(state, model) { selected = it }
                    1 -> StatsHub(state) { dest = it }
                    2 -> StoresScreen(state, model)
                    else -> SettingsHub(state, { dest = it }, model::logout)
                }
            }
        }
    }
    selected?.let { expense ->
        AlertDialog(
            onDismissRequest = { selected = null },
            title = { Text(expense.store) },
            text = { Text("${expense.date}\n${expense.entrySource}\n${expense.ocrRawStore}") },
            confirmButton = { TextButton({ model.deleteExpense(expense); selected = null }) { Text("Move to trash") } },
            dismissButton = { TextButton({ selected = null }) { Text("Close") } },
        )
    }
    sheet?.let { kind ->
        TransactionSheet(kind, state.snapshot.stores, { sheet = null },
            { d, s, a -> model.addExpense(d, s, a, if (kind == "ocr") "ocr" else "manual") { sheet = null } },
            { d, a -> model.addIncome(d, a) { sheet = null } },
            { d, f, t, a -> model.addTransfer(d, f, t, a) { sheet = null } },
        )
    }
    ErrorDialog(state.error, model::dismissError)
}
