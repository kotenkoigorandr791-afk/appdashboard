package orbit.nex.finance

import android.content.Context
import android.content.Intent
import android.content.pm.ActivityInfo
import android.os.Bundle
import androidx.appcompat.app.AppCompatActivity
import androidx.activity.compose.BackHandler
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Add
import androidx.compose.material.icons.outlined.WarningAmber
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.LocalContentColor
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveableStateHolder
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.delay
import androidx.lifecycle.viewmodel.compose.viewModel
import orbit.nex.finance.data.Appearance
import orbit.nex.finance.data.Expense
import orbit.nex.finance.data.Store
import orbit.nex.finance.ui.AboutScreen
import orbit.nex.finance.ui.AddActionSheet
import orbit.nex.finance.ui.AppBackground
import orbit.nex.finance.ui.AppearanceScreen
import orbit.nex.finance.ui.CalendarScreen
import orbit.nex.finance.ui.CashFlowScreen
import orbit.nex.finance.ui.CategoriesScreen
import orbit.nex.finance.ui.DataScreen
import orbit.nex.finance.ui.Dest
import orbit.nex.finance.ui.DetailScreen
import orbit.nex.finance.ui.EditExpenseDialog
import orbit.nex.finance.ui.ErrorDialog
import orbit.nex.finance.ui.ExpenseDetailScreen
import orbit.nex.finance.ui.GoalsScreen
import orbit.nex.finance.ui.HomeScreen
import orbit.nex.finance.ui.InsightsScreen
import orbit.nex.finance.ui.NetWorthScreen
import orbit.nex.finance.ui.PairingScreen
import orbit.nex.finance.ui.PendingScreen
import orbit.nex.finance.ui.PrefsScreen
import orbit.nex.finance.ui.SearchSheet
import orbit.nex.finance.ui.SettingsHub
import orbit.nex.finance.ui.SpeedDial
import orbit.nex.finance.ui.SavedToast
import orbit.nex.finance.ui.SpendsNavBar
import orbit.nex.finance.ui.SpendsTheme
import orbit.nex.finance.ui.StatsHub
import orbit.nex.finance.ui.StoreDetailScreen
import orbit.nex.finance.ui.StoresScreen
import orbit.nex.finance.ui.SubscriptionsScreen
import orbit.nex.finance.ui.SyncScreen
import orbit.nex.finance.ui.TagsScreen
import orbit.nex.finance.ui.TransactionSheet
import orbit.nex.finance.ui.TrashScreen
import orbit.nex.finance.ui.WrappedScreen
import orbit.nex.finance.updates.AppUpdateChecker
import orbit.nex.finance.updates.UpdateOutcome

class MainActivity : AppCompatActivity() {
    private var pairingLink: String? = null
    private var openPending = false
    override fun attachBaseContext(newBase: android.content.Context) {
        super.attachBaseContext(AppLocale.wrap(newBase))
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        pairingLink = intent?.dataString
        openPending = intent?.getBooleanExtra(EXTRA_OPEN_PENDING, false) == true
        val app = application as SpendsApplication
        setContent {
            val appearance by app.store.appearance.collectAsState(Appearance())
            requestedOrientation = if (appearance.portraitOnly) ActivityInfo.SCREEN_ORIENTATION_PORTRAIT else ActivityInfo.SCREEN_ORIENTATION_UNSPECIFIED
            SpendsTheme(appearance) { AppBackground { SpendsApp(app, pairingLink, openPending) } }
        }
    }
    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        pairingLink = intent.dataString
        openPending = intent.getBooleanExtra(EXTRA_OPEN_PENDING, false)
    }

    companion object {
        const val EXTRA_OPEN_PENDING = "orbit.nex.finance.OPEN_PENDING"
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable private fun SpendsApp(app: SpendsApplication, pairingLink: String?, openPending: Boolean) {
    val context = LocalContext.current
    val model: SpendsViewModel = viewModel { SpendsViewModel(app.store, app.api) }
    val state by model.state.collectAsState()
    val appearance by app.store.appearance.collectAsState(Appearance())
    var tab by remember { mutableIntStateOf(0) }
    var dest by remember { mutableStateOf<Dest?>(null) }
    var addOpen by remember { mutableStateOf(false) }
    var sheet by remember { mutableStateOf<String?>(null) }
    var selectedExpense by remember { mutableStateOf<Expense?>(null) }
    var selectedStore by remember { mutableStateOf<Store?>(null) }
    var editingExpense by remember { mutableStateOf(false) }
    var showSaved by remember { mutableStateOf(false) }
    val disputeCount = state.extras.disputes.size

    // Back always steps one level up inside the app; only the home tab exits.
    BackHandler(enabled = dest != null || tab != 0) {
        when {
            dest == Dest.Expense && selectedStore != null -> { dest = Dest.Store; selectedExpense = null }
            dest == Dest.Store -> { dest = Dest.Stores; selectedStore = null }
            dest != null -> { dest = null; selectedExpense = null; selectedStore = null }
            else -> tab = 0
        }
    }

    LaunchedEffect(state.savedTick) {
        if (state.savedTick > 0L) {
            showSaved = true
            delay(2_000)
            showSaved = false
        }
    }

    // A captured wallet notification opens the review screen directly.
    LaunchedEffect(openPending) {
        if (openPending) dest = Dest.Pending
    }

    if (!state.sessionReady) {
        // Pairing must not flash for accounts that are already connected.
        androidx.compose.foundation.layout.Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            CircularProgressIndicator()
        }
        return
    }

    if (state.session.token.isBlank()) {
        PairingScreen(pairingLink.orEmpty(), model)
        ErrorDialog(state.error, model::dismissError)
        return
    }

    // Quiet background update check, throttled to once a day.
    LaunchedEffect(state.session.token) {
        val session = state.session
        if (session.token.isBlank() || session.endpoint.isBlank()) return@LaunchedEffect
        val prefs = context.getSharedPreferences("spends_updates", Context.MODE_PRIVATE)
        val now = System.currentTimeMillis()
        if (now - prefs.getLong("last_check", 0L) < 24L * 60 * 60 * 1000) return@LaunchedEffect
        prefs.edit().putLong("last_check", now).apply()
        runCatching {
            val checker = AppUpdateChecker(context.applicationContext)
            val check = checker.checkForUpdate(session.endpoint, session.token)
            val info = check.info
            if (check.outcome == UpdateOutcome.UPDATE_AVAILABLE && info != null) {
                val file = checker.downloadApk(session.endpoint, session.token, info)
                if (file != null) AppUpdateChecker.postInstallNotification(context, file, info.version_name)
            }
        }
    }

    // Pull fresh data (expenses, incomes and stores) on every app start and keep it
    // in sync with the server while the app is open.
    LaunchedEffect(Unit) { model.refresh() }
    LaunchedEffect(Unit) {
        while (true) {
            delay(45_000)
            model.refreshQuietly()
        }
    }

    val stateHolder = rememberSaveableStateHolder()
    val screenKey = when {
        dest == Dest.Store -> "store:${selectedStore?.id ?: selectedStore?.name}"
        dest == Dest.Expense -> "expense:${selectedExpense?.signature}-${selectedExpense?.date}-${selectedExpense?.store}"
        dest != null -> "dest:$dest"
        else -> "tab:$tab"
    }

    Scaffold(
        containerColor = Color.Transparent,
        contentColor = MaterialTheme.colorScheme.onBackground,
        bottomBar = {
            if (dest == null) SpendsNavBar(tab, { tab = it }, { addOpen = true })
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            if (dest == null && disputeCount > 0) {
                Surface(Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 10.dp)
                    .clickable { context.startActivity(Intent(context, DisputeActivity::class.java)) },
                    shape = RoundedCornerShape(22.dp), color = Color(0xFFFFD54F), shadowElevation = 10.dp) {
                    Row(Modifier.padding(horizontal = 18.dp, vertical = 11.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(androidx.compose.material.icons.Icons.Outlined.WarningAmber, null, tint = Color(0xFF6D5200), modifier = Modifier.size(20.dp))
                        Spacer(Modifier.size(8.dp))
                        Text(context.resources.getQuantityString(R.plurals.disputes_count, disputeCount, disputeCount), color = Color(0xFF5D4600))
                    }
                }
            }
            Box(Modifier.weight(1f).fillMaxWidth().padding(bottom = 8.dp)) {
            // Keeps each screen's scroll position when going back to it.
            stateHolder.SaveableStateProvider(screenKey) {
            when (dest) {
                Dest.Insights -> DetailScreen(stringResource(R.string.insights), { dest = null }) { InsightsScreen(state) }
                Dest.CashFlow -> DetailScreen(stringResource(R.string.cash_flow), { dest = null }) { CashFlowScreen(state) }
                Dest.Categories -> DetailScreen(stringResource(R.string.categories), { dest = null }) { CategoriesScreen(state) }
                Dest.Calendar -> DetailScreen(stringResource(R.string.calendar), { dest = null }) { CalendarScreen(state) }
                Dest.Wrapped -> DetailScreen(stringResource(R.string.year_in_review), { dest = null }) { WrappedScreen(state) }
                Dest.NetWorth -> DetailScreen(stringResource(R.string.net_position), { dest = null }) { NetWorthScreen(state) }
                Dest.Goals -> DetailScreen(stringResource(R.string.goals), { dest = null }) { GoalsScreen(state, model) }
                Dest.Subscriptions -> DetailScreen(stringResource(R.string.subscriptions_title), { dest = null }) { SubscriptionsScreen(state, model) }
                Dest.Tags -> DetailScreen(stringResource(R.string.tags), { dest = null }) { TagsScreen(state, model) }
                Dest.Trash -> DetailScreen(stringResource(R.string.trash), { dest = null }) { TrashScreen(state, model) }
                Dest.Pending -> DetailScreen(stringResource(R.string.pending), { dest = null }) { PendingScreen(state, model) }
                Dest.Prefs -> DetailScreen(stringResource(R.string.preferences), { dest = null }) { PrefsScreen(state, model) }
                Dest.Appearance -> DetailScreen(stringResource(R.string.appearance), { dest = null }) { AppearanceScreen(appearance, app.store) }
                Dest.Data -> DetailScreen(stringResource(R.string.data_management), { dest = null }) { DataScreen(state, model) }
                Dest.About -> DetailScreen(stringResource(R.string.about), { dest = null }) { AboutScreen(state) }
                Dest.Sync -> DetailScreen(stringResource(R.string.server_sync), { dest = null }) {
                    SyncScreen(state, model) { context.startActivity(Intent(context, LogsActivity::class.java)) }
                }
                Dest.Stores -> DetailScreen(stringResource(R.string.stores_title), { dest = null }) {
                    StoresScreen(state, model) { selectedStore = it; dest = Dest.Store }
                }
                Dest.Store -> selectedStore?.let { store ->
                    DetailScreen(store.name, { dest = Dest.Stores; selectedStore = null }) {
                        StoreDetailScreen(state, store) { selectedExpense = it; dest = Dest.Expense }
                    }
                }
                Dest.Expense -> selectedExpense?.let { expense ->
                    DetailScreen(
                        expense.store,
                        { dest = if (selectedStore != null) Dest.Store else null; if (dest == null) selectedExpense = null },
                        onEdit = { editingExpense = true },
                    ) {
                        ExpenseDetailScreen(state, expense, model) { model.deleteExpense(expense); dest = null; selectedExpense = null }
                    }
                }
                else -> when (tab) {
                    0 -> HomeScreen(state, model) { selectedExpense = it; dest = Dest.Expense }
                    1 -> StatsHub(state) { dest = it }
                    3 -> StoresScreen(state, model) { selectedStore = it; dest = Dest.Store }
                    else -> SettingsHub(state, {
                        if (it == Dest.Stores) tab = 3 else dest = it
                    }, model::logout)
                }
            }
            }
            if (showSaved) {
                SavedToast(Modifier.align(Alignment.BottomCenter).padding(bottom = if (dest == null) 100.dp else 28.dp))
            }
            }
        }
    }
    if (addOpen) {
        AddActionSheet({ addOpen = false }) { kind ->
            addOpen = false
            if (kind == "ocr") context.startActivity(Intent(context, OcrActivity::class.java)) else sheet = kind
        }
    }
    sheet?.let { kind ->
        TransactionSheet(kind, { sheet = null },
            { d, s, a -> model.addExpense(d, s, a, "manual") { sheet = null } },
            { d, a -> model.addIncome(d, a) { sheet = null } },
        )
    }
    ErrorDialog(state.error, model::dismissError)
    if (editingExpense) {
        selectedExpense?.let { expense ->
            EditExpenseDialog(state, expense, model) { editingExpense = false }
        }
    }
}
