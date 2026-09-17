package com.stepmonitor.app.screens
import android.app.Activity
import android.content.Intent
import android.content.pm.ActivityInfo
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.appcompat.app.AppCompatActivity
import androidx.appcompat.app.AppCompatDelegate
import androidx.compose.foundation.clickable
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.ui.layout.ContentScale
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CloudDone
import androidx.compose.material.icons.filled.Download
import androidx.compose.material.icons.filled.ExpandLess
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material.icons.filled.LinkOff
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Checkbox
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.RadioButton
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExposedDropdownMenuBox
import androidx.compose.material3.ExposedDropdownMenuDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.SegmentedButton
import androidx.compose.material3.SegmentedButtonDefaults
import androidx.compose.material3.SingleChoiceSegmentedButtonRow
import androidx.compose.material3.Surface
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TimePicker
import androidx.compose.material3.rememberTimePickerState
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.key
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.IntRect
import androidx.compose.ui.unit.IntSize
import androidx.compose.ui.unit.LayoutDirection
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Popup
import androidx.core.content.FileProvider
import androidx.health.connect.client.permission.HealthPermission
import androidx.core.os.LocaleListCompat
import coil.compose.AsyncImage
import com.stepmonitor.app.HCWebhookApplication
import com.stepmonitor.app.HealthConnectManager
import com.stepmonitor.app.HealthDataType
import com.stepmonitor.app.MainActivity
import com.stepmonitor.app.PreferencesManager
import com.stepmonitor.app.R
import com.stepmonitor.app.ScheduledSync
import com.stepmonitor.app.ScheduledSyncManager
import com.stepmonitor.app.SyncMode
import com.stepmonitor.app.WebhookConfig
import com.stepmonitor.app.components.absoluteProfileUrl
import com.stepmonitor.app.components.downloadAvatarFile
import com.stepmonitor.app.components.localAvatarFile
import com.stepmonitor.app.components.APP_LANGUAGES
import kotlin.math.roundToInt
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.encodeToJsonElement
import kotlinx.serialization.json.put
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONObject
import com.journeyapps.barcodescanner.ScanContract
import com.journeyapps.barcodescanner.ScanOptions
import java.io.File
data class ServerProfile(val name: String = "", val email: String = "", val photo: String = "", val error: String? = null)
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AboutScreen(modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val scope = rememberCoroutineScope()
    var configs by remember { mutableStateOf(prefs.getWebhookConfigs()) }
    var enabled by remember { mutableStateOf(prefs.getEnabledDataTypes().ifEmpty { HealthDataType.values().toSet() }) }
    var link by remember { mutableStateOf("") }
    var status by remember { mutableStateOf(if (configs.any { it.isEnabled }) context.getString(R.string.auth_connected) else context.getString(R.string.auth_waiting)) }
    var testingConn by remember { mutableStateOf(false) }
    var connResult by remember { mutableStateOf<Boolean?>(null) }
    var profile by remember { mutableStateOf(profileFromJson(prefs.getProfileCache(Long.MAX_VALUE)) ?: ServerProfile()) }
    var profilePhotoModel by remember { mutableStateOf(localAvatarFile(context).takeIf { it.exists() }?.absolutePath ?: profile.photo.takeIf { it.isNotBlank() }) }
    var metricsExpanded by remember { mutableStateOf(false) }
    var wifiOnly by remember { mutableStateOf(prefs.isWifiOnly()) }
    var themeMode by remember { mutableStateOf(prefs.getThemeMode()) }
    var amoledEnabled by remember { mutableStateOf(prefs.isAmoledEnabled()) }
    var accentColor by remember { mutableStateOf(prefs.getAccentColor()) }
    var accentExpanded by remember { mutableStateOf(false) }
    var grantedPermissions by remember { mutableStateOf(prefs.getKnownGrantedPermissions()) }
    var permissionsSheetOpen by remember { mutableStateOf(false) }
    var showExportSheet by remember { mutableStateOf(false) }
    var exportSelectedTypes by remember { mutableStateOf(enabled) }
    var exportRange by remember { mutableIntStateOf(0) }
    var exportFormat by remember { mutableIntStateOf(0) }
    var syncMode by remember { mutableStateOf(prefs.getSyncMode()) }
    var syncInterval by remember { mutableStateOf(prefs.getSyncIntervalMinutes()) }
    var scheduledSyncs by remember { mutableStateOf(prefs.getScheduledSyncs()) }
    var intervalExpanded by remember { mutableStateOf(false) }
    var scheduleToEdit by remember { mutableStateOf<ScheduledSync?>(null) }
    var conflictRemember by remember { mutableStateOf(prefs.isConflictRememberEnabled()) }
    var conflictDefaultAction by remember { mutableStateOf(prefs.getConflictDefaultAction() ?: "skip") }
    var conflictActionExpanded by remember { mutableStateOf(false) }
    var personalizationOpen by remember { mutableStateOf(false) }
    var language by remember { mutableStateOf(prefs.getAppLanguage()) }
    var portraitOnly by remember { mutableStateOf(prefs.isPortraitOnly()) }
    var showRestartDialog by remember { mutableStateOf(false) }
    fun connect(value: String) {
        runCatching {
            val uri = Uri.parse(value.trim())
            val endpoint = uri.getQueryParameter("endpoint").orEmpty()
            val token = uri.getQueryParameter("token").orEmpty()
            require(endpoint.startsWith("http") && token.isNotBlank())
            configs = listOf(WebhookConfig(endpoint, "StepMonitor", mapOf("Authorization" to "Bearer $token")))
            prefs.setWebhookConfigs(configs)
            prefs.setEnabledDataTypes(enabled)
            status = context.getString(R.string.auth_connected)
            link = ""
        }.onFailure {
            status = context.getString(R.string.auth_invalid_link)
        }
    }
    val scanner = rememberLauncherForActivityResult(ScanContract()) { result ->
        result.contents?.takeIf { it.isNotBlank() }?.let { connect(it) }
    }
    val permissionLauncher = rememberLauncherForActivityResult(androidx.health.connect.client.PermissionController.createRequestPermissionResultContract()) { granted ->
        grantedPermissions = granted
        prefs.setKnownGrantedPermissions(granted)
    }
    LaunchedEffect(Unit) {
        val actualGranted = HealthConnectManager(context).getGrantedPermissions()
        grantedPermissions = actualGranted
        prefs.setKnownGrantedPermissions(actualGranted)
    }
    fun applySyncSchedule(mode: SyncMode, interval: Int = syncInterval) {
        prefs.setSyncMode(mode)
        syncMode = mode
        val app = context.applicationContext as? HCWebhookApplication
        if (mode == SyncMode.INTERVAL) {
            prefs.setSyncIntervalMinutes(interval)
            syncInterval = interval
            app?.scheduleSyncWork()
            ScheduledSyncManager(context).cancelAllAlarms()
            ScheduledSyncManager(context).scheduleNextIntervalSync()
        } else {
            app?.cancelSyncWork()
            ScheduledSyncManager(context).cancelIntervalSync()
            ScheduledSyncManager(context).scheduleAllAlarms()
        }
    }
    LaunchedEffect(configs) {
        val config = configs.firstOrNull { it.isEnabled } ?: return@LaunchedEffect
        // Only refetch if cache is older than PROFILE_REFRESH_MAX_AGE_MS (throttle)
        if (prefs.getProfileCache(PROFILE_REFRESH_MAX_AGE_MS) != null) return@LaunchedEffect
        val fetched = fetchProfile(config, prefs)
        if (fetched.error == null) {
            // Download photo to local file for genuine offline caching
            if (fetched.photo.isNotBlank()) {
                downloadAvatarFile(context, fetched.photo)
            }
            profile = fetched
            profilePhotoModel = localAvatarFile(context).takeIf { it.exists() }?.absolutePath
                ?: fetched.photo.takeIf { it.isNotBlank() }
        }
    }
    LazyColumn(modifier.fillMaxSize().padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Row(Modifier.padding(16.dp), horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                    key(profilePhotoModel) {
                        AsyncImage(
                            model = profilePhotoModel,
                            contentDescription = null,
                            contentScale = ContentScale.Crop,
                            modifier = Modifier.size(56.dp).clip(CircleShape)
                        )
                    }
                    Column(Modifier.weight(1f)) {
                        Text(profile.name.ifBlank { stringResource(R.string.app_name) }, style = MaterialTheme.typography.titleMedium)
                        Text(profile.email.ifBlank { profile.error ?: status }, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
            }
        }
        item {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(stringResource(R.string.auth_title), style = MaterialTheme.typography.titleMedium)
                    Text(status, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    if (configs.any { it.isEnabled }) {
                        Button(
                            onClick = {
                                scope.launch {
                                    testingConn = true
                                    connResult = null
                                    connResult = testConnection(configs.firstOrNull { it.isEnabled })
                                    testingConn = false
                                }
                            },
                            enabled = !testingConn,
                            colors = ButtonDefaults.buttonColors(
                                containerColor = when (connResult) {
                                    true -> Color(0xFF66BB6A)
                                    false -> Color(0xFF9B1C31)
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
                                Text(stringResource(R.string.settings_test_connection), modifier = Modifier.padding(start = 8.dp))
                            }
                        }
                        OutlinedButton(onClick = {
                            configs = emptyList()
                            prefs.setWebhookConfigs(emptyList())
                            prefs.clearServerDataCache()
                            localAvatarFile(context).delete()
                            status = context.getString(R.string.auth_waiting)
                            profile = ServerProfile()
                            profilePhotoModel = null
                        }, modifier = Modifier.fillMaxWidth()) {
                            Icon(Icons.Filled.LinkOff, contentDescription = null)
                            Text(stringResource(R.string.auth_disconnect), modifier = Modifier.padding(start = 8.dp))
                        }
                    } else {
                        Column(
                            modifier = Modifier.fillMaxWidth().padding(vertical = 8.dp),
                            horizontalAlignment = Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            Surface(
                                modifier = Modifier.size(56.dp),
                                shape = CircleShape,
                                color = MaterialTheme.colorScheme.errorContainer
                            ) {
                                Icon(
                                    Icons.Filled.LinkOff,
                                    contentDescription = null,
                                    modifier = Modifier.padding(12.dp),
                                    tint = MaterialTheme.colorScheme.onErrorContainer
                                )
                            }
                            Button(onClick = {
                                scanner.launch(ScanOptions().apply {
                                    setDesiredBarcodeFormats(ScanOptions.QR_CODE)
                                    setPrompt(context.getString(R.string.auth_scan_qr))
                                    setBeepEnabled(false)
                                    setOrientationLocked(true)
                                })
                            }, modifier = Modifier.fillMaxWidth()) {
                                Icon(Icons.Filled.QrCodeScanner, contentDescription = null)
                                Text(stringResource(R.string.auth_scan_qr), modifier = Modifier.padding(start = 8.dp))
                            }
                            OutlinedTextField(
                                value = link,
                                onValueChange = { link = it },
                                modifier = Modifier.fillMaxWidth(),
                                label = { Text(stringResource(R.string.auth_link_label)) }
                            )
                            Button(onClick = { connect(link) }, modifier = Modifier.fillMaxWidth()) {
                                Text(stringResource(R.string.auth_connect))
                            }
                        }
                    }
                }
            }
        }
        item {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(stringResource(R.string.settings_network), style = MaterialTheme.typography.titleMedium)
                    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                        Text(stringResource(R.string.settings_wifi_only), modifier = Modifier.weight(1f))
                        Switch(checked = wifiOnly, onCheckedChange = {
                            wifiOnly = it
                            prefs.setWifiOnly(it)
                            (context.applicationContext as? HCWebhookApplication)?.scheduleSyncWork()
                        })
                    }
                }
            }
        }
        item {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(stringResource(R.string.perms_title), style = MaterialTheme.typography.titleMedium)
                    val grantedCount = HealthDataType.entries.count { type ->
                        HealthPermission.getReadPermission(type.recordClass) in grantedPermissions
                    }
                    Text(
                        stringResource(R.string.perms_granted_summary, grantedCount, HealthDataType.entries.size),
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    Button(onClick = { permissionLauncher.launch(HealthConnectManager.ALL_PERMISSIONS) }, modifier = Modifier.fillMaxWidth()) {
                        Text(stringResource(R.string.action_grant_permissions))
                    }
                    OutlinedButton(onClick = { permissionsSheetOpen = true }, modifier = Modifier.fillMaxWidth()) {
                        Text(stringResource(R.string.config_action_view_perms))
                    }
                }
            }
        }
        item {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(stringResource(R.string.settings_sync_interval), style = MaterialTheme.typography.titleMedium)
                    val currentSyncMode = syncMode
                    val currentInterval = syncInterval
                    val currentLabel = if (currentSyncMode == SyncMode.SCHEDULED) {
                        stringResource(R.string.config_sync_mode_scheduled)
                    } else {
                        intervalOptions.firstOrNull { it.first == currentInterval }?.let { stringResource(it.second) }
                            ?: context.getString(R.string.settings_sync_hours, currentInterval / 60)
                    }
                    ExposedDropdownMenuBox(
                        expanded = intervalExpanded,
                        onExpandedChange = { intervalExpanded = !intervalExpanded },
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        OutlinedTextField(
                            value = currentLabel,
                            onValueChange = {},
                            readOnly = true,
                            label = { Text(stringResource(R.string.settings_sync_mode)) },
                            trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = intervalExpanded) },
                            colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
                            modifier = Modifier.menuAnchor().fillMaxWidth()
                        )
                        ExposedDropdownMenu(expanded = intervalExpanded, onDismissRequest = { intervalExpanded = false }) {
                            intervalOptions.forEach { (minutes, labelRes) ->
                                DropdownMenuItem(
                                    text = { Text(stringResource(labelRes)) },
                                    onClick = {
                                        intervalExpanded = false
                                        applySyncSchedule(SyncMode.INTERVAL, minutes)
                                    }
                                )
                            }
                            DropdownMenuItem(
                                text = { Text(stringResource(R.string.config_sync_mode_scheduled)) },
                                onClick = {
                                    intervalExpanded = false
                                    applySyncSchedule(SyncMode.SCHEDULED)
                                }
                            )
                        }
                    }
                }
            }
        }
        if (syncMode == SyncMode.SCHEDULED) {
            item {
                Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                    Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Text(stringResource(R.string.settings_sync_time), style = MaterialTheme.typography.titleMedium)
                        scheduledSyncs.forEach { schedule ->
                            Row(
                                Modifier.fillMaxWidth().padding(vertical = 4.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text(schedule.getDisplayLabel(), modifier = Modifier.weight(1f))
                                Text(schedule.getDisplayTime(), color = MaterialTheme.colorScheme.onSurfaceVariant)
                                TextButton(onClick = { scheduleToEdit = schedule }) {
                                    Text(stringResource(R.string.action_change))
                                }
                            }
                        }
                    }
                }
            }
        }
        item {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(stringResource(R.string.settings_conflict_title), style = MaterialTheme.typography.titleMedium)
                    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                        androidx.compose.foundation.layout.Column(Modifier.weight(1f)) {
                            Text(stringResource(R.string.settings_conflict_remember))
                            Text(stringResource(R.string.settings_conflict_remember_desc), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                        Switch(checked = conflictRemember, onCheckedChange = { checked ->
                            conflictRemember = checked
                            prefs.setConflictRememberEnabled(checked)
                        })
                    }
                    if (conflictRemember) {
                        val actionLabels = listOf(
                            "skip" to stringResource(R.string.conflict_skip),
                            "replace" to stringResource(R.string.conflict_replace),
                            "add" to stringResource(R.string.conflict_add)
                        )
                        ExposedDropdownMenuBox(
                            expanded = conflictActionExpanded,
                            onExpandedChange = { conflictActionExpanded = !conflictActionExpanded },
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            OutlinedTextField(
                                value = actionLabels.firstOrNull { it.first == conflictDefaultAction }?.second ?: conflictDefaultAction,
                                onValueChange = {},
                                readOnly = true,
                                label = { Text(stringResource(R.string.settings_conflict_default_action)) },
                                trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = conflictActionExpanded) },
                                colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
                                modifier = Modifier.menuAnchor().fillMaxWidth()
                            )
                            ExposedDropdownMenu(expanded = conflictActionExpanded, onDismissRequest = { conflictActionExpanded = false }) {
                                actionLabels.forEach { (value, label) ->
                                    DropdownMenuItem(
                                        text = { Text(label) },
                                        onClick = {
                                            conflictDefaultAction = value
                                            prefs.setConflictDefaultAction(value)
                                            conflictActionExpanded = false
                                        }
                                    )
                                }
                            }
                        }
                        TextButton(onClick = {
                            conflictRemember = false
                            prefs.setConflictRememberEnabled(false)
                        }, modifier = Modifier.fillMaxWidth()) {
                            Text(stringResource(R.string.settings_conflict_forget))
                        }
                    }
                }
            }
        }
item {
        Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text(stringResource(R.string.settings_personalization), style = MaterialTheme.typography.titleMedium)
                Button(onClick = { personalizationOpen = true }, modifier = Modifier.fillMaxWidth()) {
                    Icon(Icons.Filled.Settings, contentDescription = null)
                    Text(stringResource(R.string.about_configure), modifier = Modifier.padding(start = 8.dp))
                }
            }
        }
    }
        item {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Row(
                        Modifier.fillMaxWidth().clickable { metricsExpanded = !metricsExpanded },
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(stringResource(R.string.settings_metrics), style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
                        Text("${enabled.size}/${HealthDataType.values().size}", color = MaterialTheme.colorScheme.onSurfaceVariant)
                        IconButton(onClick = { metricsExpanded = !metricsExpanded }) {
                            Icon(if (metricsExpanded) Icons.Filled.ExpandLess else Icons.Filled.ExpandMore, contentDescription = null)
                        }
                    }
                    if (metricsExpanded) {
                        HealthDataType.values().forEach { type ->
                            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                Checkbox(checked = type in enabled, onCheckedChange = { checked ->
                                    enabled = if (checked) enabled + type else enabled - type
                                    prefs.setEnabledDataTypes(enabled)
                                })
                                Text(stringResource(type.nameResId), modifier = Modifier.weight(1f))
                            }
                        }
                    }
                }
            }
        }
        item {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(stringResource(R.string.health_export_title), style = MaterialTheme.typography.titleMedium)
                    Button(onClick = { showExportSheet = true }, modifier = Modifier.fillMaxWidth()) {
                        Icon(Icons.Filled.Download, contentDescription = null)
                        Text(stringResource(R.string.health_export_title), modifier = Modifier.padding(start = 8.dp))
                    }
                }
            }
        }
    }
    if (permissionsSheetOpen) {
        PermissionsBottomSheet(
            grantedPermissionsSet = grantedPermissions,
            onDismiss = { permissionsSheetOpen = false }
        )
    }
    if (showExportSheet) {
        val sheetState = rememberModalBottomSheetState()
        ModalBottomSheet(
            onDismissRequest = { showExportSheet = false },
            sheetState = sheetState
        ) {
            Column(
                modifier = Modifier
                    .padding(horizontal = 24.dp, vertical = 16.dp)
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                Text(stringResource(R.string.health_export_title), style = MaterialTheme.typography.titleLarge)

                Text(stringResource(R.string.export_data_types), style = MaterialTheme.typography.titleSmall)
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
                ) {
                    Column(
                        Modifier.padding(16.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        HealthDataType.values().forEach { type ->
                            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                Checkbox(
                                    checked = type in exportSelectedTypes,
                                    onCheckedChange = {
                                        exportSelectedTypes = if (it) exportSelectedTypes + type else exportSelectedTypes - type
                                    }
                                )
                                Text(stringResource(type.nameResId), modifier = Modifier.weight(1f))
                            }
                        }
                    }
                }

                Text(stringResource(R.string.export_time_range), style = MaterialTheme.typography.titleSmall)
                SingleChoiceSegmentedButtonRow(modifier = Modifier.fillMaxWidth()) {
                    val labels = listOf(
                        R.string.export_range_24h,
                        R.string.export_range_7d,
                        R.string.export_range_30d,
                        R.string.export_range_all
                    )
                    labels.forEachIndexed { idx, labelRes ->
                        SegmentedButton(
                            selected = exportRange == idx,
                            onClick = { exportRange = idx },
                            shape = SegmentedButtonDefaults.itemShape(idx, labels.size),
                            modifier = Modifier.weight(1f)
                        ) { Text(stringResource(labelRes), style = MaterialTheme.typography.labelSmall) }
                    }
                }

                Text(stringResource(R.string.export_format), style = MaterialTheme.typography.titleSmall)
                SingleChoiceSegmentedButtonRow(modifier = Modifier.fillMaxWidth()) {
                    listOf(R.string.export_format_json, R.string.export_format_csv).forEachIndexed { idx, labelRes ->
                        SegmentedButton(
                            selected = exportFormat == idx,
                            onClick = { exportFormat = idx },
                            shape = SegmentedButtonDefaults.itemShape(idx, 2),
                            modifier = Modifier.weight(1f)
                        ) { Text(stringResource(labelRes)) }
                    }
                }

                Button(
                    onClick = {
                        showExportSheet = false
                        performExport(context, prefs, exportSelectedTypes, exportRange, exportFormat)
                    },
                    modifier = Modifier.fillMaxWidth(),
                    enabled = exportSelectedTypes.isNotEmpty()
                ) {
                    Icon(Icons.Filled.Download, contentDescription = null)
                    Text(stringResource(R.string.export_confirm), modifier = Modifier.padding(start = 8.dp))
                }
                Spacer(Modifier.heightIn(min = 16.dp))
            }
        }
    }
    if (personalizationOpen) {
        val sheetState = rememberModalBottomSheetState()
        ModalBottomSheet(
            onDismissRequest = { personalizationOpen = false },
            sheetState = sheetState
        ) {
            Column(
                modifier = Modifier
                    .padding(horizontal = 24.dp, vertical = 16.dp)
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                Text(stringResource(R.string.about_personalization), style = MaterialTheme.typography.titleLarge)
                HorizontalDivider()

                Text(stringResource(R.string.about_theme), style = MaterialTheme.typography.titleSmall)
                SingleChoiceSegmentedButtonRow(modifier = Modifier.fillMaxWidth()) {
                    listOf("auto" to R.string.theme_auto, "light" to R.string.theme_light, "dark" to R.string.theme_dark).forEachIndexed { index, (value, label) ->
                        SegmentedButton(
                            selected = themeMode == value,
                            onClick = {
                                themeMode = value
                                prefs.setThemeMode(value)
                                (context as? Activity)?.recreate()
                            },
                            shape = SegmentedButtonDefaults.itemShape(index, 3),
                        ) {
                            Text(stringResource(label))
                        }
                    }
                }

                if (themeMode == "dark" || (themeMode == "auto" && isSystemInDarkTheme())) {
                    Row(
                        Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(stringResource(R.string.theme_amoled), modifier = Modifier.weight(1f))
                        Switch(checked = amoledEnabled, onCheckedChange = {
                            amoledEnabled = it
                            prefs.setAmoledEnabled(it)
                            (context as? Activity)?.recreate()
                        })
                    }
                }

                Text(stringResource(R.string.about_accent_color), style = MaterialTheme.typography.titleSmall)
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Box {
                        OutlinedButton(
                            onClick = { accentExpanded = true },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(8.dp)
                        ) {
                            Surface(color = accentColorValue(accentColor), shape = CircleShape, modifier = Modifier.size(18.dp), content = {})
                            Spacer(Modifier.size(10.dp))
                            Text(stringResource(accentNameRes(accentColor)))
                            Spacer(Modifier.weight(1f))
                            Icon(if (accentExpanded) Icons.Filled.ExpandLess else Icons.Filled.ExpandMore, contentDescription = null)
                        }
                        DropdownMenu(
                            expanded = accentExpanded,
                            onDismissRequest = { accentExpanded = false }
                        ) {
                            listOf("blue", "teal", "orange", "pink", "purple", "gray").forEach { value ->
                                DropdownMenuItem(
                                    text = {
                                        Row(
                                            Modifier.fillMaxWidth(),
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Surface(color = accentColorValue(value), shape = CircleShape, modifier = Modifier.size(18.dp), content = {})
                                            Spacer(Modifier.size(10.dp))
                                            Text(stringResource(accentNameRes(value)))
                                        }
                                    },
                                    onClick = {
                                        accentExpanded = false
                                        accentColor = value
                                        prefs.setAccentColor(value)
                                        (context as? Activity)?.recreate()
                                    },
                                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 12.dp)
                                )
                            }
                        }
                    }
                }

                Text(stringResource(R.string.about_language), style = MaterialTheme.typography.titleSmall)
                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    APP_LANGUAGES.forEach { (tag, name) ->
                        Row(
                            Modifier
                                .fillMaxWidth()
                                .clickable {
                                    val newLang = tag
                                    language = newLang
                                    prefs.setAppLanguage(newLang)
                                    if (newLang.isEmpty()) {
                                        AppCompatDelegate.setApplicationLocales(LocaleListCompat.getEmptyLocaleList())
                                    } else {
                                        AppCompatDelegate.setApplicationLocales(LocaleListCompat.forLanguageTags(newLang))
                                    }
                                    showRestartDialog = true
                                }
                                .padding(vertical = 8.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            RadioButton(selected = language == tag, onClick = null)
                            Spacer(Modifier.size(12.dp))
                            Text(if (tag.isEmpty()) stringResource(R.string.system_default) else name)
                        }
                    }
                }

                Text(stringResource(R.string.about_screen_orientation), style = MaterialTheme.typography.titleSmall)
                Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                    Text(stringResource(R.string.about_portrait_only), modifier = Modifier.weight(1f))
                    Switch(checked = portraitOnly, onCheckedChange = {
                        portraitOnly = it
                        prefs.setPortraitOnly(it)
                        (context as? Activity)?.requestedOrientation = if (it) ActivityInfo.SCREEN_ORIENTATION_PORTRAIT else ActivityInfo.SCREEN_ORIENTATION_UNSPECIFIED
                    })
                }

                Spacer(Modifier.heightIn(min = 16.dp))
            }
        }
    }
    if (showRestartDialog) {
        AlertDialog(
            onDismissRequest = { showRestartDialog = false },
            title = { Text(stringResource(R.string.about_language_changed_title)) },
            text = { Text(stringResource(R.string.about_language_changed_text)) },
            confirmButton = {
                TextButton(onClick = {
                    showRestartDialog = false
                    val restartIntent = Intent(context, MainActivity::class.java).apply {
                        addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK)
                    }
                    context.startActivity(restartIntent)
                    Runtime.getRuntime().exit(0)
                }) { Text(stringResource(R.string.about_restart)) }
            },
            dismissButton = {
                TextButton(onClick = { showRestartDialog = false }) { Text(stringResource(R.string.about_later)) }
            }
        )
    }
    scheduleToEdit?.let { target ->
        val pickerState = rememberTimePickerState(initialHour = target.hour, initialMinute = target.minute, is24Hour = true)
        AlertDialog(
            onDismissRequest = { scheduleToEdit = null },
            title = { Text(target.getDisplayLabel()) },
            text = { TimePicker(state = pickerState) },
            confirmButton = {
                TextButton(onClick = {
                    val updated = scheduledSyncs.map { if (it.id == target.id) it.copy(hour = pickerState.hour, minute = pickerState.minute) else it }
                    prefs.setScheduledSyncs(updated)
                    scheduledSyncs = updated
                    scheduleToEdit = null
                    ScheduledSyncManager(context).scheduleAllAlarms()
                }) { Text(stringResource(R.string.action_ok)) }
            },
            dismissButton = { TextButton(onClick = { scheduleToEdit = null }) { Text(stringResource(R.string.action_cancel)) } }
        )
    }
}
private val intervalOptions = listOf(
    60 to R.string.sync_interval_1h,
    180 to R.string.sync_interval_3h,
    360 to R.string.sync_interval_6h,
    720 to R.string.sync_interval_12h,
    1440 to R.string.sync_interval_24h,
    4320 to R.string.sync_interval_72h,
)
private suspend fun fetchProfile(config: WebhookConfig?, prefs: PreferencesManager): ServerProfile = withContext(Dispatchers.IO) {
    if (config == null) return@withContext ServerProfile()
    runCatching {
        val profileUrl = config.url.trimEnd('/') + "/profile"
        val builder = Request.Builder().url(profileUrl)
        config.headers.forEach { (key, value) -> builder.addHeader(key, value) }
        OkHttpClient().newCall(builder.build()).execute().use { response ->
            if (!response.isSuccessful) error("HTTP ${response.code}")
            val profile = JSONObject(response.body?.string().orEmpty()).optJSONObject("profile") ?: JSONObject()
            val result = ServerProfile(
                name = profile.optString("name"),
                email = profile.optString("email"),
                photo = absoluteProfileUrl(config.url, profile.optString("photo"))
            )
            prefs.setProfileCache(JSONObject().put("name", result.name).put("email", result.email).put("photo", result.photo).toString())
            result
        }
    }.getOrElse { ServerProfile(error = it.message) }
}
private suspend fun testConnection(config: WebhookConfig?): Boolean = withContext(Dispatchers.IO) {
    if (config == null) return@withContext false
    runCatching {
        val builder = Request.Builder().url(config.url.trimEnd('/') + "/profile")
        config.headers.forEach { (key, value) -> builder.addHeader(key, value) }
        OkHttpClient().newCall(builder.build()).execute().use { response ->
            if (!response.isSuccessful) error("HTTP ${response.code}")
            true
        }
    }.getOrDefault(false)
}
private fun profileFromJson(json: String?): ServerProfile? = runCatching {
    if (json.isNullOrBlank()) null else JSONObject(json).let { ServerProfile(it.optString("name"), it.optString("email"), it.optString("photo")) }
}.getOrNull()
private fun accentNameRes(value: String): Int = when (value) {
    "teal" -> R.string.accent_teal
    "orange" -> R.string.accent_orange
    "pink" -> R.string.accent_pink
    "purple" -> R.string.accent_purple
    "gray" -> R.string.accent_gray
    else -> R.string.accent_blue
}
private fun accentColorValue(value: String): Color = when (value) {
    "teal" -> Color(0xFF00897B)
    "orange" -> Color(0xFFE8710A)
    "pink" -> Color(0xFFD81B60)
    "purple" -> Color(0xFF7E57C2)
    "gray" -> Color(0xFF475569)
    else -> Color(0xFF4285F4)
}
private const val PROFILE_CACHE_MAX_AGE_MS = 24L * 60 * 60 * 1000
private const val PROFILE_REFRESH_MAX_AGE_MS = 10L * 60 * 1000
private fun performExport(
    context: android.content.Context,
    prefs: PreferencesManager,
    selectedTypes: Set<HealthDataType>,
    rangeIndex: Int,
    formatIndex: Int
) {
    val cutoffMs = when (rangeIndex) {
        0 -> System.currentTimeMillis() - 24L * 60 * 60 * 1000
        1 -> System.currentTimeMillis() - 7L * 24 * 60 * 60 * 1000
        2 -> System.currentTimeMillis() - 30L * 24 * 60 * 60 * 1000
        else -> 0L
    }
    val logs = prefs.getWebhookLogs().filter { it.timestamp >= cutoffMs }
    val exportDir = File(context.cacheDir, "exports").apply { mkdirs() }
    if (formatIndex == 0) {
        val file = File(exportDir, "stepmonitor-export.json")
        val json = Json { prettyPrint = true }
        val payload = buildJsonObject {
            put("exportedAt", java.time.Instant.now().toString())
            put("enabledDataTypes", JsonArray(selectedTypes.map { json.encodeToJsonElement(it.name) }))
            put("logs", json.encodeToJsonElement(logs))
        }
        file.writeText(json.encodeToString(kotlinx.serialization.json.JsonObject.serializer(), payload))
        val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
        context.startActivity(Intent.createChooser(Intent(Intent.ACTION_SEND).apply {
            type = "application/json"
            putExtra(Intent.EXTRA_STREAM, uri)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }, context.getString(R.string.export_confirm)))
    } else {
        val file = File(exportDir, "stepmonitor-export.csv")
        val sb = StringBuilder()
        sb.appendLine("timestamp,dataType,url,statusCode,success,syncType,errorMessage")
        logs.forEach { log ->
            sb.appendLine(
                listOf(
                    log.timestamp,
                    log.dataType.orEmpty(),
                    log.url,
                    log.statusCode ?: "",
                    log.success,
                    log.syncType.orEmpty(),
                    "\"${log.errorMessage.orEmpty().replace("\"", "\"\"")}\""
                ).joinToString(",")
            )
        }
        file.writeText(sb.toString())
        val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
        context.startActivity(Intent.createChooser(Intent(Intent.ACTION_SEND).apply {
            type = "text/csv"
            putExtra(Intent.EXTRA_STREAM, uri)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }, context.getString(R.string.export_confirm)))
    }
}
