package orbit.nex.steps
import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.content.pm.ActivityInfo
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.activity.compose.setContent
import androidx.appcompat.app.AppCompatActivity
import androidx.appcompat.app.AppCompatDelegate
import androidx.core.content.ContextCompat
import androidx.core.os.LocaleListCompat
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.Image
import androidx.compose.material3.Icon
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Checkbox
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.Saver
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.graphics.BlendMode
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.CompositingStrategy
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.health.connect.client.PermissionController
import androidx.lifecycle.lifecycleScope
import orbit.nex.steps.screens.AboutScreen
import orbit.nex.steps.screens.ConfigurationScreen
import orbit.nex.steps.screens.ConnectionGateScreen
import orbit.nex.steps.screens.TrackingMapScreen
import orbit.nex.steps.screens.bindDevice
import orbit.nex.steps.screens.StatisticsScreen
import orbit.nex.steps.screens.WhatsNewSheet
import orbit.nex.steps.components.AppBackground
import orbit.nex.steps.components.AppTopBar
import orbit.nex.steps.ui.theme.HCWebhookTheme
import orbit.nex.steps.updates.AppUpdateManager
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.io.IOException
import java.util.UUID
class MainActivity : AppCompatActivity() {
    private lateinit var preferencesManager: PreferencesManager
    companion object {
        private const val KEY_APP_LANGUAGE = "app_language"
        private const val KEY_PORTRAIT_ONLY = "portrait_only"
    }
    override fun onCreate(savedInstanceState: Bundle?) {
        installSplashScreen()
        super.onCreate(savedInstanceState)
        preferencesManager = PreferencesManager(this)
        orbit.nex.steps.location.LocationTrackingManager.resumeIfEnabled(this)
        if (preferencesManager.getWebhookConfigs().any { it.isEnabled } && preferencesManager.getEnabledDataTypes().isEmpty()) {
            preferencesManager.setEnabledDataTypes(HealthDataType.values().toSet())
        }
        applyLanguage(preferencesManager.getAppLanguage())
        applyThemeMode(preferencesManager.getThemeMode())
        applyPortraitMode(preferencesManager.isPortraitOnly())
        handleAuthIntent(intent)
        setContent { HCWebhookTheme { MainScreen() } }
    }
    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        handleAuthIntent(intent)
    }
    private fun handleAuthIntent(intent: Intent?) {
        val uri = intent?.data ?: return
        if (uri.host != "auth") return
        if (uri.scheme !in setOf("nexsteps", "stepmonitor")) return
        val endpoint = uri.getQueryParameter("endpoint").orEmpty()
        val token = uri.getQueryParameter("token").orEmpty()
        if (!endpoint.startsWith("http") || token.isBlank()) return
        val config = WebhookConfig(endpoint, "NexSteps", mapOf("Authorization" to "Bearer $token"))
        // The link used to be stored unchecked, so a stale or already-claimed
        // token showed "Connected" and then every sync failed with 401. Verify
        // the pairing (which also binds the token to this device) before saving.
        lifecycleScope.launch {
            val bound = withContext(Dispatchers.IO) { bindDevice(config, preferencesManager) }
            if (bound) {
                preferencesManager.setWebhookConfigs(listOf(config))
                preferencesManager.setEnabledDataTypes(HealthDataType.values().toSet())
                preferencesManager.clearAuthError()
                Toast.makeText(this@MainActivity, getString(R.string.auth_connected), Toast.LENGTH_LONG).show()
            } else {
                Toast.makeText(this@MainActivity, getString(R.string.auth_invalid_link), Toast.LENGTH_LONG).show()
            }
        }
    }
    @Composable
    private fun MainScreen() {
        val navigationSaver = Saver<NavigationScreen, String>(
            save = { it.route },
            restore = { route -> bottomNavItems.firstOrNull { it.route == route } ?: NavigationScreen.Home }
        )
        var selected by rememberSaveable(stateSaver = navigationSaver) { mutableStateOf(NavigationScreen.Home) }
        // The app is usable only after a device token is paired: without it every
        // tab would show empty or stale data, so the gate replaces the whole shell.
        var connected by remember { mutableStateOf(preferencesManager.getWebhookConfigs().any { it.isEnabled }) }
        var pendingConflict by remember { mutableStateOf(preferencesManager.getPendingConflictJson()) }
        var showWhatsNew by remember { mutableStateOf(preferencesManager.consumeWhatsNewPending()) }
        LaunchedEffect(Unit) {
            while (true) {
                pendingConflict = preferencesManager.getPendingConflictJson()
                connected = preferencesManager.getWebhookConfigs().any { it.isEnabled }
                delay(2_000)
            }
        }
        LaunchedEffect(Unit) {
            AppUpdateManager.start(applicationContext)
        }
        if (showWhatsNew && connected) {
            WhatsNewSheet(
                visible = true,
                onDismiss = {
                    showWhatsNew = false
                    preferencesManager.setWhatsNewPending(false)
                },
            )
        }
        if (!connected) {
            AppBackground {
                ConnectionGateScreen(onConnected = { connected = true })
            }
            return
        }
        var notificationDone by remember {
            mutableStateOf(
                Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU ||
                    ContextCompat.checkSelfPermission(this@MainActivity, Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED
            )
        }
        var notificationRequestStarted by remember { mutableStateOf(false) }
        var healthRequestStarted by remember { mutableStateOf(false) }
        var healthPermissionRevision by remember { mutableStateOf(0) }
        var showHistoryOffer by remember { mutableStateOf(false) }
        var historyRequest by remember { mutableStateOf(0) }
        val setupPrefs = remember { getSharedPreferences("nexsteps_setup", MODE_PRIVATE) }
        var pendingHealthPermissions by remember { mutableStateOf<Set<String>>(emptySet()) }
        val notificationPermissionLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) {
            notificationDone = true
        }
        val healthPermissionLauncher = rememberLauncherForActivityResult(PermissionController.createRequestPermissionResultContract()) { granted ->
            preferencesManager.setKnownGrantedPermissions(granted)
            healthPermissionRevision++
        }
        LaunchedEffect(Unit) {
            if (!notificationDone && !notificationRequestStarted) {
                notificationRequestStarted = true
                notificationPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
            }
        }
        LaunchedEffect(notificationDone) {
            if (!notificationDone || healthRequestStarted) return@LaunchedEffect
            val enabledTypes = preferencesManager.getEnabledDataTypes().ifEmpty { HealthDataType.values().toSet() }
            val requiredPermissions = HealthConnectManager.getPermissionsForTypes(enabledTypes)
            val grantedPermissions = HealthConnectManager(this@MainActivity).getGrantedPermissions()
            val missingPermissions = requiredPermissions - grantedPermissions
            if (missingPermissions.isNotEmpty()) {
                healthRequestStarted = true
                pendingHealthPermissions = requiredPermissions
            }
        }
        if (pendingHealthPermissions.isNotEmpty()) {
            AlertDialog(
                onDismissRequest = { pendingHealthPermissions = emptySet() },
                icon = { Image(painterResource(R.drawable.nex_steps_icon), contentDescription = null, modifier = Modifier.size(48.dp)) },
                title = { Text(stringResource(R.string.nex_health_intro_title)) },
                text = { Text(stringResource(R.string.nex_health_intro_message)) },
                confirmButton = {
                    TextButton(onClick = {
                        val permissions = pendingHealthPermissions
                        pendingHealthPermissions = emptySet()
                        healthPermissionLauncher.launch(permissions)
                    }) { Text(stringResource(R.string.nex_auth_continue)) }
                },
                dismissButton = {
                    TextButton(onClick = { pendingHealthPermissions = emptySet() }) { Text(stringResource(R.string.nex_health_intro_later)) }
                },
            )
        }
        LaunchedEffect(connected, healthPermissionRevision) {
            if (!connected || setupPrefs.getBoolean("history_offer_checked", false)) return@LaunchedEffect
            val hc = HealthConnectManager(this@MainActivity)
            val granted = runCatching { hc.getGrantedPermissions() }.getOrDefault(emptySet())
            val types = HealthConnectManager.grantedDataTypes(granted).intersect(preferencesManager.getEnabledDataTypes())
            if (types.isEmpty()) return@LaunchedEffect
            val now = java.time.Instant.now()
            val installed = java.time.Instant.ofEpochMilli(packageManager.getPackageInfo(packageName, 0).firstInstallTime)
            val end = minOf(installed, now)
            val start = if ("android.permission.health.READ_HEALTH_DATA_HISTORY" in granted) java.time.Instant.EPOCH else now.minus(java.time.Duration.ofDays(29))
            if (!start.isBefore(end)) return@LaunchedEffect
            val result = hc.hasHistoricalRecords(types, start, end)
            if (result.isSuccess) {
                setupPrefs.edit().putBoolean("history_offer_checked", true).apply()
                showHistoryOffer = result.getOrThrow()
            }
        }
        if (showHistoryOffer) {
            AlertDialog(
                onDismissRequest = { showHistoryOffer = false },
                title = { Text(stringResource(R.string.manual_sync_history_offer_title)) },
                text = { Text(stringResource(R.string.manual_sync_history_offer_message)) },
                confirmButton = { TextButton(onClick = { showHistoryOffer = false; selected = NavigationScreen.Home; historyRequest++ }) { Text(stringResource(R.string.manual_sync_history_button)) } },
                dismissButton = { TextButton(onClick = { showHistoryOffer = false }) { Text(stringResource(R.string.nex_health_intro_later)) } },
            )
        }
        // Auto-resolve if user saved a preference
        LaunchedEffect(pendingConflict) {
            val conflict = pendingConflict ?: return@LaunchedEffect
            if (preferencesManager.isConflictRememberEnabled()) {
                val action = preferencesManager.getConflictDefaultAction()
                if (action != null) {
                    resolveConflict(action, conflict)
                    pendingConflict = null
                }
            }
        }
        // Back from any tab returns to Home first instead of leaving the app.
        // Nested screens (settings, logs) are separate activities and finish one
        // level at a time on their own.
        androidx.activity.compose.BackHandler(enabled = selected != NavigationScreen.Home) {
            selected = NavigationScreen.Home
        }
        Scaffold(
            topBar = {
                AppTopBar(showTimer = selected != NavigationScreen.Home)
            },
            bottomBar = {
            NavigationBar(
                modifier = Modifier.clip(RoundedCornerShape(topStart = 18.dp, topEnd = 18.dp))
            ) {
                bottomNavItems.forEach { screen ->
                    NavigationBarItem(
                        selected = selected == screen,
                        onClick = { selected = screen },
                        icon = { Icon(screen.icon, contentDescription = stringResource(screen.titleResId)) },
                        label = { NavLabel(screen.titleResId) }
                    )
                }
            }
        }) { padding ->
            AppBackground {
                when (selected) {
                    NavigationScreen.Home -> ConfigurationScreen(Modifier.padding(padding), historyRequest = historyRequest)
                    NavigationScreen.Tracking -> TrackingMapScreen(Modifier.padding(padding))
                    NavigationScreen.Statistics -> StatisticsScreen(Modifier.padding(padding))
                    NavigationScreen.About -> AboutScreen(Modifier.padding(padding))
                }
            }
        }
        val showConflict = pendingConflict != null &&
            !(preferencesManager.isConflictRememberEnabled() && preferencesManager.getConflictDefaultAction() != null)
        if (showConflict) {
            val conflictJson = pendingConflict!!
            var rememberChoice by remember { mutableStateOf(false) }
            AlertDialog(
                onDismissRequest = {},
                title = { Text(getString(R.string.conflict_title)) },
                text = {
                    androidx.compose.foundation.layout.Column {
                        Text(conflictSummary(conflictJson))
                        Spacer(Modifier.height(8.dp))
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Checkbox(
                                checked = rememberChoice,
                                onCheckedChange = { rememberChoice = it }
                            )
                            Text(getString(R.string.conflict_remember))
                        }
                    }
                },
                confirmButton = {
                    Row {
                        TextButton(onClick = {
                            if (rememberChoice) {
                                preferencesManager.setConflictRememberEnabled(true)
                                preferencesManager.setConflictDefaultAction("skip")
                            }
                            resolveConflict("skip", conflictJson)
                            pendingConflict = null
                        }) { Text(getString(R.string.conflict_skip)) }
                        TextButton(onClick = {
                            if (rememberChoice) {
                                preferencesManager.setConflictRememberEnabled(true)
                                preferencesManager.setConflictDefaultAction("replace")
                            }
                            resolveConflict("replace", conflictJson)
                            pendingConflict = null
                        }) { Text(getString(R.string.conflict_replace)) }
                        TextButton(onClick = {
                            if (rememberChoice) {
                                preferencesManager.setConflictRememberEnabled(true)
                                preferencesManager.setConflictDefaultAction("add")
                            }
                            resolveConflict("add", conflictJson)
                            pendingConflict = null
                        }) { Text(getString(R.string.conflict_add)) }
                    }
                },
                dismissButton = {},
                tonalElevation = 6.dp
            )
        }
    }
    private fun conflictSummary(json: String): String = runCatching {
        val obj = JSONObject(json).optJSONObject("conflict") ?: JSONObject(json)
        val days = obj.optJSONArray("days")
        getString(R.string.conflict_message, days?.length() ?: 0)
    }.getOrDefault(getString(R.string.conflict_message, 0))
    private fun resolveConflict(action: String, json: String) {
        preferencesManager.clearPendingConflictJson()
        kotlinx.coroutines.CoroutineScope(Dispatchers.IO).launch {
            val timestamp = System.currentTimeMillis()
            var loggedUrl = ""
            try {
                val obj = JSONObject(json).optJSONObject("conflict") ?: JSONObject(json)
                val config = preferencesManager.getWebhookConfigs().firstOrNull { it.isEnabled }
                val rawUrl = obj.optString("resolution_url").ifBlank {
                    val id = obj.optString("id")
                    "/api/steps/conflicts/$id"
                }
                val url = if (rawUrl.startsWith("http")) rawUrl else {
                    val base = Uri.parse(config?.url.orEmpty())
                    "${base.scheme}://${base.authority}/${rawUrl.trimStart('/')}"
                }
                loggedUrl = url
                val body = JSONObject().put("action", action).toString().toRequestBody("application/json; charset=utf-8".toMediaType())
                val builder = Request.Builder().url(url).post(body)
                config?.headers?.forEach { (key, value) -> builder.addHeader(key, value) }
                OkHttpClient().newCall(builder.build()).execute().use { response ->
                    if (response.isSuccessful) {
                        addConflictLog(url, timestamp, response.code, true, getString(R.string.conflict_resolve_success, action, response.code))
                    } else {
                        addConflictLog(url, timestamp, response.code, false, getString(R.string.conflict_resolve_error, "HTTP ${response.code}"))
                    }
                }
            } catch (e: IOException) {
                addConflictLog(loggedUrl, timestamp, null, false, getString(R.string.conflict_resolve_error, e.message ?: "IOException"))
            }
        }
    }
    private fun addConflictLog(url: String, timestamp: Long, statusCode: Int?, success: Boolean, errorMessage: String) {
        preferencesManager.addWebhookLog(
            WebhookLog(
                id = UUID.randomUUID().toString(),
                timestamp = timestamp,
                url = url,
                statusCode = statusCode,
                success = success,
                errorMessage = errorMessage,
                dataType = "steps",
                recordCount = null,
                syncType = "conflict",
                deliveryFormat = WebhookLog.FORMAT_JSON
            )
        )
    }

    /**
     * Bar label that fades out only when the word really does not fit the item
     * width. Previously every label was masked with a gradient, which made even
     * short labels look blurry although there was enough room.
     */
    @Composable
    private fun NavLabel(titleResId: Int) {
        var overflows by remember { mutableStateOf(false) }
        Text(
            stringResource(titleResId),
            maxLines = 1,
            softWrap = false,
            overflow = TextOverflow.Clip,
            onTextLayout = { layout -> overflows = layout.hasVisualOverflow },
            modifier = if (overflows) {
                Modifier
                    .graphicsLayer(compositingStrategy = CompositingStrategy.Offscreen)
                    .drawWithContent {
                        drawContent()
                        drawRect(
                            brush = Brush.horizontalGradient(
                                0.86f to Color.White,
                                1f to Color.Transparent
                            ),
                            blendMode = BlendMode.DstIn
                        )
                    }
            } else {
                Modifier
            }
        )
    }

    private fun applyLanguage(lang: String) {
        if (lang.isEmpty()) {
            AppCompatDelegate.setApplicationLocales(LocaleListCompat.getEmptyLocaleList())
        } else {
            AppCompatDelegate.setApplicationLocales(LocaleListCompat.forLanguageTags(lang))
        }
    }

    private fun applyThemeMode(mode: String) {
        val nightMode = when (mode) {
            "light" -> AppCompatDelegate.MODE_NIGHT_NO
            "dark" -> AppCompatDelegate.MODE_NIGHT_YES
            else -> AppCompatDelegate.MODE_NIGHT_FOLLOW_SYSTEM
        }
        AppCompatDelegate.setDefaultNightMode(nightMode)
    }

    private fun applyPortraitMode(portraitOnly: Boolean) {
        requestedOrientation = if (portraitOnly) ActivityInfo.SCREEN_ORIENTATION_PORTRAIT else ActivityInfo.SCREEN_ORIENTATION_UNSPECIFIED
    }
}
