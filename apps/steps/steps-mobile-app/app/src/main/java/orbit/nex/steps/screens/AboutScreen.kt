package orbit.nex.steps.screens

import orbit.nex.steps.ui.theme.Spacing
import orbit.nex.steps.ui.theme.semantic
import android.content.Intent
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.Image
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccountCircle
import androidx.compose.material.icons.filled.CloudDone
import androidx.compose.material.icons.filled.CloudOff
import androidx.compose.material.icons.filled.LinkOff
import androidx.compose.material.icons.filled.PersonOff
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.filled.Update
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.key
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.ListAlt
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.FilledTonalButton
import androidx.compose.material3.IconButton
import androidx.compose.material3.Surface
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import orbit.nex.steps.ui.theme.ComponentHeight
import orbit.nex.steps.ui.theme.IconSize
import orbit.nex.steps.ui.theme.Layout
import orbit.nex.steps.HCWebhookApplication
import orbit.nex.steps.BuildConfig
import orbit.nex.steps.HealthConnectManager
import orbit.nex.steps.HealthDataType
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.R
import orbit.nex.steps.SettingsActivity
import orbit.nex.steps.WebhookConfig
import orbit.nex.steps.components.absoluteProfileUrl
import orbit.nex.steps.components.downloadAvatarFile
import orbit.nex.steps.components.localAvatarFile
import orbit.nex.steps.updates.AppUpdateChecker
import orbit.nex.steps.updates.CheckOutcome
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONObject

/**
 * Profile tab: shows who is connected and links into [SettingsActivity], which
 * is the single screen holding the user's personal data and every app setting.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AboutScreen(modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val scope = rememberCoroutineScope()
    var configs by remember { mutableStateOf(prefs.getWebhookConfigs()) }
    var status by remember {
        mutableStateOf(if (prefs.isUserConnected()) context.getString(R.string.auth_connected) else context.getString(R.string.auth_waiting))
    }
    var testingConn by remember { mutableStateOf(false) }
    var connResult by remember { mutableStateOf<Boolean?>(null) }
    var connectionLatency by remember { mutableStateOf<Long?>(null) }
    var confirmDisconnect by remember { mutableStateOf(false) }
    val updateChecker = remember { AppUpdateChecker(context) }
    var autoUpdate by remember { mutableStateOf(prefs.isAutoUpdateEnabled()) }
    var updateBusy by remember { mutableStateOf(false) }
    var updateStatus by remember { mutableStateOf<String?>(null) }
    var downloadedApk by remember { mutableStateOf(updateChecker.latestDownloaded()) }
    var profile by remember { mutableStateOf(profileFromJson(prefs.getProfileCache(Long.MAX_VALUE)) ?: ServerProfile()) }
    var profilePhotoModel by remember {
        mutableStateOf(localAvatarFile(context).takeIf { it.exists() }?.absolutePath ?: profile.photo.takeIf { it.isNotBlank() })
    }

    LaunchedEffect(configs) {
        val config = configs.firstOrNull { it.isEnabled } ?: return@LaunchedEffect
        // Only refetch if cache is older than PROFILE_REFRESH_MAX_AGE_MS (throttle)
        if (prefs.getProfileCache(PROFILE_REFRESH_MAX_AGE_MS) != null) return@LaunchedEffect
        val fetched = fetchProfile(config, prefs)
        if (fetched.error == null) {
            // Download photo to local file for genuine offline caching
            if (fetched.photo.isNotBlank()) {
                withContext(Dispatchers.IO) { downloadAvatarFile(context, fetched.photo) }
            }
            profile = fetched
            profilePhotoModel = localAvatarFile(context).takeIf { it.exists() }?.absolutePath
                ?: fetched.photo.takeIf { it.isNotBlank() }
        }
    }

    var open by rememberSaveable { mutableStateOf<String?>(null) }
    val entries = listOf(
        Triple("account", Icons.Filled.AccountCircle, R.string.auth_title),
        Triple("profile", Icons.Filled.ManageAccounts, R.string.personal_title),
        Triple("sync", Icons.Filled.Sync, R.string.settings_group_sync),
        Triple("data", Icons.Filled.Storage, R.string.settings_group_data),
        Triple("perms", Icons.Filled.Security, R.string.perms_title),
        Triple("location", Icons.Filled.LocationOn, R.string.settings_group_location),
        Triple("points", Icons.Filled.ScatterPlot, R.string.points_title),
        Triple("look", Icons.Filled.Palette, R.string.settings_personalization),
        Triple("updates", Icons.Filled.Update, R.string.settings_update_title),
        Triple("logs", Icons.AutoMirrored.Filled.ListAlt, R.string.settings_logs_title),
    )
    LazyColumn(modifier.fillMaxSize().padding(horizontal = Layout.screenPadding), verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
        item { Spacer(Modifier.height(Spacing.sm)) }
        items(entries.size) { index ->
            val (key, icon, title) = entries[index]
            FilledTonalButton(
                onClick = { open = key },
                modifier = Modifier.fillMaxWidth().heightIn(min = ComponentHeight.listRow),
                shape = MaterialTheme.shapes.medium,
                contentPadding = PaddingValues(horizontal = Spacing.lg),
            ) {
                Icon(icon, contentDescription = null, modifier = Modifier.size(IconSize.md))
                Spacer(Modifier.width(Spacing.lg))
                Text(stringResource(title), style = MaterialTheme.typography.titleSmall, modifier = Modifier.weight(1f), textAlign = TextAlign.Start)
            }
        }
        item { Spacer(Modifier.height(Spacing.sm)) }
    }
    if (confirmDisconnect) {
        androidx.compose.material3.AlertDialog(
            onDismissRequest = { confirmDisconnect = false },
            icon = { Icon(Icons.Filled.LinkOff, contentDescription = null, tint = MaterialTheme.colorScheme.error) },
            title = { Text(stringResource(R.string.account_disconnect_title)) },
            text = { Text(stringResource(R.string.account_disconnect_desc)) },
            confirmButton = { androidx.compose.material3.TextButton(onClick = {
                configs = emptyList()
                prefs.setWebhookConfigs(emptyList())
                prefs.clearConnectionEstablished()
                prefs.clearServerDataCache()
                localAvatarFile(context).delete()
                status = context.getString(R.string.auth_waiting)
                profile = ServerProfile()
                profilePhotoModel = null
                confirmDisconnect = false
                open = null
            }) { Text(stringResource(R.string.auth_disconnect), color = MaterialTheme.colorScheme.error) } },
            dismissButton = { androidx.compose.material3.TextButton(onClick = { confirmDisconnect = false }) { Text(stringResource(R.string.action_cancel)) } },
        )
    }
    val current = open
    val windowed = mapOf(
        "profile" to Intent(context, SettingsActivity::class.java).putExtra("section", SettingsSection.PROFILE.name),
        "data" to Intent(context, SettingsActivity::class.java).putExtra("section", SettingsSection.DATA.name),
        "location" to Intent(context, orbit.nex.steps.LocationSettingsActivity::class.java),
        "look" to Intent(context, orbit.nex.steps.PersonalizationActivity::class.java),
        "logs" to Intent(context, orbit.nex.steps.LogsActivity::class.java),
        "points" to Intent(context, orbit.nex.steps.PointsActivity::class.java),
    )
    if (current != null && windowed.containsKey(current)) {
        LaunchedEffect(current) { context.startActivity(windowed.getValue(current)); open = null }
    } else if (current != null) {
        val dismiss = { open = null }
        Dialog(onDismissRequest = dismiss, properties = DialogProperties(usePlatformDefaultWidth = false)) {
            Surface(
                shape = MaterialTheme.shapes.large,
                color = MaterialTheme.colorScheme.background,
                modifier = Modifier.fillMaxWidth(if (current == "account") 1f else 0.96f).fillMaxHeight(if (current == "account") 1f else 0.92f),
            ) {
                when (current) {
                    "account" -> AccountDetails(
                        profile = profile,
                        photo = profilePhotoModel,
                        connected = configs.any { it.isEnabled },
                        connectedAt = prefs.getConnectionEstablishedAt(),
                        syncs = prefs.getWebhookLogs().size,
                        pending = orbit.nex.steps.location.LocationTrackingManager.pendingCount(context),
                        testing = testingConn,
                        result = connResult,
                        latency = connectionLatency,
                        onBack = dismiss,
                        onTest = { scope.launch { testingConn = true; connResult = null; val start = android.os.SystemClock.elapsedRealtime(); connResult = testConnection(configs.firstOrNull { it.isEnabled }, prefs); connectionLatency = android.os.SystemClock.elapsedRealtime() - start; testingConn = false } },
                        onDisconnect = { confirmDisconnect = true },
                        prefs = prefs,
                    )
                    "sync" -> SettingsScreen(onBack = dismiss, section = SettingsSection.SYNC)
                    "perms" -> SettingsScreen(onBack = dismiss, section = SettingsSection.PERMISSIONS)
                    else -> Column {
                        Row(Modifier.fillMaxWidth().padding(Spacing.sm), verticalAlignment = Alignment.CenterVertically) {
                            IconButton(onClick = dismiss) {
                                Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = stringResource(R.string.settings_back_cd))
                            }
                            Text(stringResource(if (current == "account") R.string.account_title else R.string.settings_update_title), style = MaterialTheme.typography.titleLarge, maxLines = 1)
                        }
                        LazyColumn(Modifier.fillMaxSize().padding(horizontal = Layout.screenPadding), verticalArrangement = Arrangement.spacedBy(Spacing.md), contentPadding = PaddingValues(top = Spacing.sm, bottom = Spacing.lg)) {
                            if (current == "account") {
        item {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(Spacing.lg), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(Spacing.md)) {
                        if (profilePhotoModel != null) {
                            AsyncImage(model = profilePhotoModel, contentDescription = null, contentScale = ContentScale.Crop, modifier = Modifier.size(52.dp).clip(CircleShape))
                        } else {
                            Icon(Icons.Filled.AccountCircle, contentDescription = null, modifier = Modifier.size(52.dp), tint = MaterialTheme.colorScheme.primary)
                        }
                        Column(Modifier.weight(1f)) {
                            Text(profile.name.ifBlank { profile.email.ifBlank { stringResource(R.string.account_title) } }, style = MaterialTheme.typography.titleMedium)
                            if (profile.email.isNotBlank() && profile.name.isNotBlank()) Text(profile.email, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            Text(status, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }
                    if (configs.any { it.isEnabled }) {
                        val connectedAt = prefs.getConnectionEstablishedAt()
                        Surface(shape = MaterialTheme.shapes.medium, color = MaterialTheme.colorScheme.surface, modifier = Modifier.fillMaxWidth()) {
                            Row(Modifier.padding(Spacing.md), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(Spacing.md)) {
                                Icon(Icons.Filled.Event, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                                Column {
                                    Text(stringResource(R.string.connection_date), style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                    Text(if (connectedAt > 0) java.text.DateFormat.getDateInstance(java.text.DateFormat.MEDIUM).format(java.util.Date(connectedAt)) else stringResource(R.string.connection_date_unknown), style = MaterialTheme.typography.bodyMedium)
                                }
                            }
                        }
                    }
                    if (configs.any { it.isEnabled }) {
                        Button(
                            onClick = {
                                scope.launch {
                                    testingConn = true
                                    connResult = null
                                    connResult = testConnection(configs.firstOrNull { it.isEnabled }, prefs)
                                    testingConn = false
                                }
                            },
                            enabled = !testingConn,
                            colors = ButtonDefaults.buttonColors(
                                containerColor = when (connResult) {
                                    true -> MaterialTheme.semantic.success
                                    false -> MaterialTheme.colorScheme.error
                                    null -> MaterialTheme.colorScheme.primary
                                }
                            ),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            if (testingConn) {
                                CircularProgressIndicator(modifier = Modifier.size(18.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary)
                                Spacer(Modifier.size(8.dp))
                                Text(stringResource(R.string.settings_test_connection_progress))
                            } else {
                                Icon(Icons.Filled.CloudDone, contentDescription = null)
                                Text(stringResource(R.string.settings_test_connection), modifier = Modifier.padding(start = Spacing.sm))
                            }
                        }
                        OutlinedButton(onClick = {
                            configs = emptyList()
                            prefs.setWebhookConfigs(emptyList())
                            prefs.clearConnectionEstablished()
                            prefs.clearServerDataCache()
                            localAvatarFile(context).delete()
                            status = context.getString(R.string.auth_waiting)
                            profile = ServerProfile()
                            profilePhotoModel = null
                        }, modifier = Modifier.fillMaxWidth()) {
                            Icon(Icons.Filled.LinkOff, contentDescription = null)
                            Text(stringResource(R.string.auth_disconnect), modifier = Modifier.padding(start = Spacing.sm))
                        }

                    }
                }
            }
        }
                            } else {
        item {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(Spacing.lg), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(stringResource(R.string.settings_update_title), style = MaterialTheme.typography.titleMedium)
                    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                        Column(Modifier.weight(1f)) {
                            Text(stringResource(R.string.settings_update_automatic))
                            Text(
                                stringResource(R.string.settings_update_automatic_desc),
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                        Switch(
                            checked = autoUpdate,
                            onCheckedChange = {
                                autoUpdate = it
                                prefs.setAutoUpdateEnabled(it)
                            },
                        )
                    }
                    Text(
                        context.getString(R.string.app_version_short, BuildConfig.VERSION_NAME),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Button(
                        onClick = {
                            scope.launch {
                                updateBusy = true
                                updateStatus = context.getString(R.string.update_checking)
                                val check = updateChecker.checkForUpdate()
                                when (check.outcome) {
                                    CheckOutcome.NOT_CONNECTED -> updateStatus = context.getString(R.string.update_not_connected)
                                    CheckOutcome.UP_TO_DATE -> updateStatus = context.getString(R.string.update_up_to_date)
                                    CheckOutcome.ERROR -> updateStatus = context.getString(R.string.update_check_failed)
                                    CheckOutcome.UPDATE_AVAILABLE -> {
                                        val info = check.info
                                        if (info == null) {
                                            updateStatus = context.getString(R.string.update_check_failed)
                                        } else {
                                            updateStatus = context.getString(R.string.update_downloading)
                                            val file = updateChecker.downloadApk(info)
                                            if (file != null) {
                                                downloadedApk = file
                                                updateStatus = context.getString(R.string.update_downloaded_text, info.version_name)
                                            } else {
                                                updateStatus = context.getString(R.string.update_download_failed)
                                            }
                                        }
                                    }
                                }
                                updateBusy = false
                            }
                        },
                        enabled = prefs.getWebhookConfigs().any { it.isEnabled } && !updateBusy,
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        if (updateBusy) {
                            CircularProgressIndicator(modifier = Modifier.size(18.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary)
                            Spacer(Modifier.size(8.dp))
                        } else {
                            Icon(Icons.Filled.Update, contentDescription = null)
                            Spacer(Modifier.size(8.dp))
                        }
                        Text(stringResource(R.string.update_check_button))
                    }
                    downloadedApk?.let { file ->
                        OutlinedButton(onClick = { updateChecker.install(file) }, modifier = Modifier.fillMaxWidth()) {
                            Text(stringResource(R.string.update_install))
                        }
                    }
                    updateStatus?.let { status ->
                        Text(status, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
            }
        }
                            }
                        }
                    }
                }
            }
        }
    }
}

private suspend fun testConnection(config: WebhookConfig?, prefs: PreferencesManager): Boolean = withContext(Dispatchers.IO) {
    if (config == null) return@withContext false
    runCatching {
        val builder = Request.Builder().url(config.url.trimEnd('/') + "/profile")
        config.headers.forEach { (key, value) -> builder.addHeader(key, value) }
        builder.addHeader("X-Device-Id", prefs.getDeviceId())
        OkHttpClient().newCall(builder.build()).execute().use { response ->
            if (response.code == 401 || response.code == 403) prefs.markAuthError(response.code)
            if (!response.isSuccessful) error("HTTP ${response.code}")
            true
        }
    }.getOrDefault(false)
}

@Composable
private fun AccountDetails(profile: ServerProfile, photo: String?, connected: Boolean, connectedAt: Long, syncs: Int, pending: Int, testing: Boolean, result: Boolean?, latency: Long?, onBack: () -> Unit, onTest: () -> Unit, onDisconnect: () -> Unit, prefs: PreferencesManager) {
    val context = LocalContext.current
    var points by remember { mutableStateOf<Int?>(null) }
    LaunchedEffect(connected) {
        val config = prefs.getWebhookConfigs().firstOrNull { it.isEnabled } ?: return@LaunchedEffect
        val page = orbit.nex.steps.location.PointManagementRepository(prefs, context).loadPage(config, 0, null, null, null, null)
        if (page.error == null) points = page.total
    }
    Column(Modifier.fillMaxSize().padding(horizontal = 16.dp, vertical = 8.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = stringResource(R.string.settings_back_cd)) }
            Text(stringResource(R.string.account_title), style = MaterialTheme.typography.titleLarge)
        }
        Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), shape = RoundedCornerShape(22.dp), modifier = Modifier.fillMaxWidth()) {
            Column(Modifier.fillMaxWidth().padding(18.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(8.dp)) {
                if (photo != null) AsyncImage(model = photo, contentDescription = null, contentScale = ContentScale.Crop, modifier = Modifier.size(72.dp).clip(CircleShape))
                else Surface(shape = CircleShape, color = MaterialTheme.colorScheme.surface, modifier = Modifier.size(72.dp)) { androidx.compose.foundation.layout.Box(contentAlignment = Alignment.Center) { Text(profile.name.split(" ").filter { it.isNotBlank() }.take(2).joinToString("") { it.take(1).uppercase() }.ifBlank { "?" }, style = MaterialTheme.typography.titleLarge) } }
                Text(profile.name.ifBlank { profile.email.ifBlank { stringResource(R.string.account_title) } }, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                if (profile.email.isNotBlank()) Text(profile.email, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Surface(shape = RoundedCornerShape(30.dp), color = MaterialTheme.colorScheme.surface, modifier = Modifier.fillMaxWidth()) {
                    Text(if (connected) stringResource(R.string.account_connected_since, if (connectedAt > 0) java.text.DateFormat.getDateInstance(java.text.DateFormat.MEDIUM).format(java.util.Date(connectedAt)) else "—") else stringResource(R.string.account_status_disconnected), modifier = Modifier.padding(10.dp), textAlign = TextAlign.Center, style = MaterialTheme.typography.labelMedium)
                }
            }
        }
        Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), shape = RoundedCornerShape(22.dp), modifier = Modifier.fillMaxWidth()) {
            Row(Modifier.fillMaxWidth().padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text(stringResource(R.string.account_server_connection), style = MaterialTheme.typography.titleSmall)
                    Text(when (result) { true -> stringResource(R.string.account_reachable, latency ?: 0L); false -> stringResource(R.string.account_unreachable); null -> if (connected) stringResource(R.string.auth_connected) else stringResource(R.string.account_status_disconnected) }, style = MaterialTheme.typography.bodySmall, color = when (result) { true -> MaterialTheme.semantic.success; false -> MaterialTheme.colorScheme.error; null -> MaterialTheme.colorScheme.onSurfaceVariant })
                }
                FilledTonalButton(onClick = onTest, enabled = connected && !testing) { if (testing) CircularProgressIndicator(Modifier.size(16.dp), strokeWidth = 2.dp) else Text(stringResource(R.string.account_test)) }
            }
        }
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            listOf(Triple(Icons.Filled.Sync, syncs.toString(), R.string.account_syncs), Triple(Icons.Filled.LocationOn, points?.let { java.text.NumberFormat.getIntegerInstance().format(it) } ?: "—", R.string.points_title), Triple(Icons.Filled.Schedule, pending.toString(), R.string.account_pending)).forEach { (icon, value, label) ->
                Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), shape = RoundedCornerShape(18.dp), modifier = Modifier.weight(1f)) {
                    Column(Modifier.fillMaxWidth().padding(vertical = 12.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(4.dp)) { Icon(icon, null, Modifier.size(18.dp), tint = MaterialTheme.colorScheme.onSurfaceVariant); Text(value, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold); Text(stringResource(label), style = MaterialTheme.typography.labelSmall, maxLines = 1) }
                }
            }
        }
        Spacer(Modifier.weight(1f))
        if (connected) OutlinedButton(onClick = onDisconnect, modifier = Modifier.fillMaxWidth(), colors = ButtonDefaults.outlinedButtonColors(contentColor = MaterialTheme.colorScheme.error), shape = RoundedCornerShape(14.dp)) { Icon(Icons.Filled.LinkOff, null, Modifier.size(18.dp)); Spacer(Modifier.width(8.dp)); Text(stringResource(R.string.account_disconnect)) }
    }
}
