package xyz.serverkherson.spends

import android.content.Intent
import android.content.pm.ActivityInfo
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.ui.Alignment
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Scaffold
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import androidx.compose.ui.platform.LocalContext
import androidx.lifecycle.viewmodel.compose.viewModel
import xyz.serverkherson.spends.data.Appearance
import xyz.serverkherson.spends.data.Expense
import xyz.serverkherson.spends.data.Store
import xyz.serverkherson.spends.ui.AppBackground
import xyz.serverkherson.spends.ui.AppearanceScreen
import xyz.serverkherson.spends.ui.CalendarScreen
import xyz.serverkherson.spends.ui.CashFlowScreen
import xyz.serverkherson.spends.ui.CategoriesScreen
import xyz.serverkherson.spends.ui.DataScreen
import xyz.serverkherson.spends.ui.Dest
import xyz.serverkherson.spends.ui.DetailScreen
import xyz.serverkherson.spends.ui.ErrorDialog
import xyz.serverkherson.spends.ui.ExpenseDetailScreen
import xyz.serverkherson.spends.ui.GoalsScreen
import xyz.serverkherson.spends.ui.HomeScreen
import xyz.serverkherson.spends.ui.InsightsScreen
import xyz.serverkherson.spends.ui.NetWorthScreen
import xyz.serverkherson.spends.ui.PairingScreen
import xyz.serverkherson.spends.ui.PendingScreen
import xyz.serverkherson.spends.ui.PrefsScreen
import xyz.serverkherson.spends.ui.SettingsHub
import xyz.serverkherson.spends.ui.SpeedDial
import xyz.serverkherson.spends.ui.SpendsNavBar
import xyz.serverkherson.spends.ui.SpendsTheme
import xyz.serverkherson.spends.ui.StatsHub
import xyz.serverkherson.spends.ui.StoreDetailScreen
import xyz.serverkherson.spends.ui.StoresScreen
import xyz.serverkherson.spends.ui.SyncScreen
import xyz.serverkherson.spends.ui.TagsScreen
import xyz.serverkherson.spends.ui.TransactionSheet
import xyz.serverkherson.spends.ui.TrashScreen
import xyz.serverkherson.spends.ui.WrappedScreen

class MainActivity : ComponentActivity() {
    private var pairingLink: String? = null
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        pairingLink = intent?.dataString
        val app = application as SpendsApplication
        setContent {
            val appearance by app.store.appearance.collectAsState(Appearance())
            requestedOrientation = if (appearance.portraitOnly) ActivityInfo.SCREEN_ORIENTATION_PORTRAIT else ActivityInfo.SCREEN_ORIENTATION_UNSPECIFIED
            SpendsTheme(appearance) { AppBackground { SpendsApp(app, pairingLink) } }
        }
    }
    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        pairingLink = intent.dataString
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable private fun SpendsApp(app: SpendsApplication, pairingLink: String?) {
    val context = LocalContext.current
    val model: SpendsViewModel = viewModel { SpendsViewModel(app.store, app.api) }
    val state by model.state.collectAsState()
    val appearance by app.store.appearance.collectAsState(Appearance())
    var tab by remember { mutableIntStateOf(0) }
    var dest by remember { mutableStateOf<Dest?>(null) }
    var fabOpen by remember { mutableStateOf(false) }
    var sheet by remember { mutableStateOf<String?>(null) }
    var selectedExpense by remember { mutableStateOf<Expense?>(null) }
    var selectedStore by remember { mutableStateOf<Store?>(null) }

    if (state.session.token.isBlank()) {
        PairingScreen(pairingLink.orEmpty(), model)
        ErrorDialog(state.error, model::dismissError)
        return
    }

    Scaffold(
        containerColor = Color.Transparent,
        bottomBar = {
            if (dest == null) SpendsNavBar(tab, { tab = it; fabOpen = false }) { fabOpen = !fabOpen }
        },
    ) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            if (fabOpen && dest == null) {
                Column(Modifier.align(Alignment.BottomCenter).padding(bottom = 12.dp)) {
                    SpeedDial { kind ->
                        fabOpen = false
                        if (kind == "ocr") context.startActivity(Intent(context, OcrActivity::class.java))
                        else sheet = kind
                    }
                }
            }
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
                Dest.Prefs -> DetailScreen("Preferences", { dest = null }) { PrefsScreen(state, model) }
                Dest.Appearance -> DetailScreen("Appearance", { dest = null }) { AppearanceScreen(appearance, app.store) }
                Dest.Data -> DetailScreen("Data", { dest = null }) { DataScreen(state, model) }
                Dest.Sync -> DetailScreen("Server sync", { dest = null }) { SyncScreen(state, model) { context.startActivity(Intent(context, LogsActivity::class.java)) } }
                Dest.Stores -> DetailScreen("Stores", { dest = null }) { StoresScreen(state, model) { selectedStore = it; dest = Dest.Store } }
                Dest.Store -> selectedStore?.let { store -> DetailScreen(store.name, { dest = Dest.Stores; selectedStore = null }) { StoreDetailScreen(state, store) { selectedExpense = it; dest = Dest.Expense } } }
                Dest.Expense -> selectedExpense?.let { expense ->
                    DetailScreen(expense.store, { dest = if (selectedStore != null) Dest.Store else null; if (dest == null) selectedExpense = null }) {
                        ExpenseDetailScreen(state, expense) { model.deleteExpense(expense); dest = null; selectedExpense = null }
                    }
                }
                else -> when (tab) {
                    0 -> HomeScreen(state, model) { selectedExpense = it; dest = Dest.Expense }
                    1 -> StatsHub(state) { dest = it }
                    else -> SettingsHub(state, { dest = it }, model::logout)
                }
            }
        }
    }
    sheet?.let { kind ->
        TransactionSheet(kind, { sheet = null },
            { d, s, a -> model.addExpense(d, s, a, "manual") { sheet = null } },
            { d, a -> model.addIncome(d, a) { sheet = null } },
        )
    }
    ErrorDialog(state.error, model::dismissError)
}
