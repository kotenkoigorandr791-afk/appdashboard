package com.stepmonitor.app.screens

import android.app.Activity
import android.content.Intent
import android.content.pm.ActivityInfo
import androidx.activity.compose.rememberLauncherForActivityResult
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
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.AccountCircle
import androidx.compose.material.icons.filled.Cake
import androidx.compose.material.icons.filled.CloudDone
import androidx.compose.material.icons.filled.Download
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.Email
import androidx.compose.material.icons.filled.ExpandLess
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material.icons.filled.Flag
import androidx.compose.material.icons.filled.Height
import androidx.compose.material.icons.filled.ManageAccounts
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.PersonOff
import androidx.compose.material.icons.filled.Save
import androidx.compose.material.icons.filled.Straighten
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExposedDropdownMenuBox
import androidx.compose.material3.ExposedDropdownMenuDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.RadioButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SegmentedButton
import androidx.compose.material3.SegmentedButtonDefaults
import androidx.compose.material3.SingleChoiceSegmentedButtonRow
import androidx.compose.material3.Surface
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TimePicker
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.material3.rememberTimePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
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
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.core.content.FileProvider
import androidx.core.os.LocaleListCompat
import androidx.health.connect.client.permission.HealthPermission
import coil.compose.AsyncImage
import com.stepmonitor.app.HCWebhookApplication
import com.stepmonitor.app.HealthConnectManager
import com.stepmonitor.app.HealthDataType
import com.stepmonitor.app.PreferencesManager
import com.stepmonitor.app.R
import com.stepmonitor.app.ScheduledSync
import com.stepmonitor.app.ScheduledSyncManager
import com.stepmonitor.app.SyncMode
import com.stepmonitor.app.WebhookConfig
import com.stepmonitor.app.components.APP_LANGUAGES
import com.stepmonitor.app.components.absoluteProfileUrl
import com.stepmonitor.app.components.downloadAvatarFile
import com.stepmonitor.app.components.localAvatarFile
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.io.File
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.encodeToJsonElement
import kotlinx.serialization.json.put

private val JSON_MEDIA_TYPE = "application/json; charset=utf-8".toMediaType()

private const val KEY_GOAL = "goal_steps"
private const val KEY_HEIGHT = "height"
private const val KEY_WEIGHT = "weight"
private const val KEY_STRIDE = "stride_length"
private const val KEY_BIRTH = "birth_date"
private val PERSONAL_KEYS = listOf(KEY_GOAL, KEY_HEIGHT, KEY_WEIGHT, KEY_STRIDE, KEY_BIRTH)

/** POSTs a JSON body to an app endpoint and returns (httpCode, body). */
private suspend fun postJson(
    config: WebhookConfig?,
    prefs: PreferencesManager,
    path: String,
    body: JSONObject,
): Pair<Int, String> = withContext(Dispatchers.IO) {
    if (config == null) return@withContext -1 to ""
    runCatching {
        val url = config.url.trimEnd('/') + path
        val request = stepsRequest(config, prefs, url)
            .post(body.toString().toRequestBody(JSON_MEDIA_TYPE))
            .build()
        OkHttpClient().newCall(request).execute().use { response ->
            if (response.code == 401 || response.code == 403) prefs.markAuthError(response.code)
            response.code to (response.body?.string().orEmpty())
        }
    }.getOrElse { -1 to (it.message ?: "network") }
}

/** Reads the server-side personal settings; returns an empty map when unavailable. */
private suspend fun loadPersonalSettings(
    config: WebhookConfig?,
    prefs: PreferencesManager,
): Pair<Map<String, String>, Boolean> = withContext(Dispatchers.IO) {
    if (config == null) return@withContext emptyMap<String, String>() to false
    runCatching {
        val url = config.url.trimEnd('/') + "/settings?settings=1"
        val request = stepsRequest(config, prefs, url).get().build()
        OkHttpClient().newCall(request).execute().use { response ->
            if (response.code == 401 || response.code == 403) prefs.markAuthError(response.code)
            if (!response.isSuccessful) return@use emptyMap<String, String>() to false
            val settings = JSONObject(response.body?.string().orEmpty()).optJSONObject("settings") ?: JSONObject()
            val out = mutableMapOf<String, String>()
            PERSONAL_KEYS.forEach { key -> if (settings.has(key)) out[key] = settings.optString(key) }
            out to true
        }
    }.getOrElse { emptyMap<String, String>() to false }
}

private fun <K, V> Map<K, V>.toJSONObject(): JSONObject {
    val json = JSONObject()
    forEach { (k, v) -> json.put(k.toString(), v) }
    return json
}

/**
 * Settings screen hosted by [com.stepmonitor.app.SettingsActivity].
 * Holds the user's personal data plus every app setting, so the profile tab
 * stays a light overview and this one screen is the single settings surface.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SettingsScreen(onBack: () -> Unit) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val scope = rememberCoroutineScope()

    var configs by remember { mutableStateOf(prefs.getWebhookConfigs()) }
    val connected = configs.any { it.isEnabled }

    // ── Personal data ────────────────────────────────────────────────────────
    var profile by remember { mutableStateOf(profileFromJson(prefs.getProfileCache(Long.MAX_VALUE)) ?: ServerProfile()) }
    var avatarModel by remember {
        mutableStateOf(localAvatarFile(context).takeIf { it.exists() }?.absolutePath ?: profile.photo.takeIf { it.isNotBlank() })
    }
    var personal by remember { mutableStateOf(emptyMap<String, String>()) }
    var nameField by remember { mutableStateOf(profile.name) }
    var goalField by remember { mutableStateOf("") }
    var heightField by remember { mutableStateOf("") }
    var weightField by remember { mutableStateOf("") }
    var strideField by remember { mutableStateOf("") }
    var birthField by remember { mutableStateOf("") }
    var personalLoadFailed by remember { mutableStateOf(false) }
    var saving by remember { mutableStateOf(false) }
    var saveOk by remember { mutableStateOf<Boolean?>(null) }
    var saveDetail by remember { mutableStateOf("") }

    // ── App settings ─────────────────────────────────────────────────────────
    var enabled by remember { mutableStateOf(prefs.getEnabledDataTypes().ifEmpty { HealthDataType.values().toSet() }) }
    var grantedPermissions by remember { mutableStateOf(prefs.getKnownGrantedPermissions()) }
    var permissionsSheetOpen by remember { mutableStateOf(false) }
    var wifiOnly by remember { mutableStateOf(prefs.isWifiOnly()) }
    var autoUpdate by remember { mutableStateOf(prefs.isAutoUpdateEnabled()) }
    var syncMode by remember { mutableStateOf(prefs.getSyncMode()) }
    var syncInterval by remember { mutableStateOf(prefs.getSyncIntervalMinutes()) }
    var scheduledSyncs by remember { mutableStateOf(prefs.getScheduledSyncs()) }
    var intervalExpanded by remember { mutableStateOf(false) }
    var scheduleToEdit by remember { mutableStateOf<ScheduledSync?>(null) }
    var conflictRemember by remember { mutableStateOf(prefs.isConflictRememberEnabled()) }
    var conflictDefaultAction by remember { mutableStateOf(prefs.getConflictDefaultAction() ?: "skip") }
    var conflictActionExpanded by remember { mutableStateOf(false) }
    var metricsExpanded by remember { mutableStateOf(false) }
    var themeMode by remember { mutableStateOf(prefs.getThemeMode()) }
    var amoledEnabled by remember { mutableStateOf(prefs.isAmoledEnabled()) }
    var accentColor by remember { mutableStateOf(prefs.getAccentColor()) }
    var accentExpanded by remember { mutableStateOf(false) }
    var language by remember { mutableStateOf(prefs.getAppLanguage()) }
    var portraitOnly by remember { mutableStateOf(prefs.isPortraitOnly()) }
    var showRestartDialog by remember { mutableStateOf(false) }
    var showExportSheet by remember { mutableStateOf(false) }
    var exportSelectedTypes by remember { mutableStateOf(enabled) }
    var exportRange by remember { mutableIntStateOf(0) }
    var exportFormat by remember { mutableIntStateOf(0) }

    val permissionLauncher = rememberLauncherForActivityResult(
        androidx.health.connect.client.PermissionController.createRequestPermissionResultContract()
    ) { granted ->
        grantedPermissions = granted
        prefs.setKnownGrantedPermissions(granted)
    }

    LaunchedEffect(Unit) {
        val actualGranted = HealthConnectManager(context).getGrantedPermissions()
        grantedPermissions = actualGranted
        prefs.setKnownGrantedPermissions(actualGranted)
    }

    LaunchedEffect(connected) {
        if (!connected) {
            personalLoadFailed = false
            return@LaunchedEffect
        }
        val config = configs.firstOrNull { it.isEnabled } ?: return@LaunchedEffect
        val fetched = fetchProfile(config, prefs)
        if (fetched.error == null) {
            if (fetched.photo.isNotBlank()) downloadAvatarFile(context, fetched.photo)
            profile = fetched
            avatarModel = localAvatarFile(context).takeIf { it.exists() }?.absolutePath
                ?: fetched.photo.takeIf { it.isNotBlank() }
            nameField = fetched.name
        }
        val (values, ok) = loadPersonalSettings(config, prefs)
        personal = values
        personalLoadFailed = !ok
        goalField = values[KEY_GOAL] ?: ""
        heightField = values[KEY_HEIGHT] ?: ""
        weightField = values[KEY_WEIGHT] ?: ""
        strideField = values[KEY_STRIDE] ?: ""
        birthField = values[KEY_BIRTH] ?: ""
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

    fun validatePersonal(): String? {
        val name = nameField.trim()
        if (name.isEmpty() || name.length > 80) return context.getString(R.string.personal_name)
        fun intIn(raw: String, min: Int, max: Int): Boolean =
            raw.isNotBlank() && raw.matches(Regex("^\\d+$")) && raw.toIntOrNull() in min..max
        if (goalField.isNotBlank() && !intIn(goalField, 1000, 200000)) return context.getString(R.string.personal_goal)
        if (heightField.isNotBlank() && !intIn(heightField, 50, 260)) return context.getString(R.string.personal_height)
        if (weightField.isNotBlank() && !intIn(weightField, 20, 400)) return context.getString(R.string.personal_weight)
        if (strideField.isNotBlank() && !intIn(strideField, 20, 250)) return context.getString(R.string.personal_stride)
        if (birthField.isNotBlank()) {
            if (!Regex("^\\d{4}-\\d{2}-\\d{2}$").matches(birthField)) return context.getString(R.string.personal_birth)
            val valid = runCatching {
                val parsed = java.time.LocalDate.parse(birthField)
                !parsed.isAfter(java.time.LocalDate.now()) && !parsed.isBefore(java.time.LocalDate.parse("1900-01-01"))
            }.getOrDefault(false)
            if (!valid) return context.getString(R.string.personal_birth)
        }
        return null
    }

    fun savePersonal() {
        val invalid = validatePersonal()
        if (invalid != null) {
            saveOk = false
            saveDetail = invalid
            return
        }
        val config = configs.firstOrNull { it.isEnabled } ?: return
        scope.launch {
            saving = true
            saveOk = null
            // Only send the keys that actually changed: the server skips no-op
            // writes, which keeps the database load proportional to real edits.
            val payload = mutableMapOf<String, String>()
            fun putIfChanged(key: String, value: String) {
                val normalized = value.trim()
                if (normalized != (personal[key] ?: "")) payload[key] = normalized
            }
            putIfChanged(KEY_GOAL, goalField)
            putIfChanged(KEY_HEIGHT, heightField)
            putIfChanged(KEY_WEIGHT, weightField)
            putIfChanged(KEY_STRIDE, strideField)
            putIfChanged(KEY_BIRTH, birthField)
            val nameChanged = nameField.trim() != profile.name && nameField.trim().isNotBlank()

            var failed = false
            var detail = ""
            if (payload.isNotEmpty()) {
                val (code, body) = postJson(config, prefs, "/settings", payload.toJSONObject())
                if (code in 200..299) {
                    val updated = JSONObject(body).optJSONObject("settings")
                    if (updated != null) {
                        val refreshed = mutableMapOf<String, String>()
                        PERSONAL_KEYS.forEach { key -> refreshed[key] = updated.optString(key) }
                        personal = refreshed
                        goalField = refreshed[KEY_GOAL] ?: goalField
                        heightField = refreshed[KEY_HEIGHT] ?: heightField
                        weightField = refreshed[KEY_WEIGHT] ?: weightField
                        strideField = refreshed[KEY_STRIDE] ?: strideField
                        birthField = refreshed[KEY_BIRTH] ?: birthField
                    }
                } else {
                    failed = true
                    detail = runCatching { JSONObject(body).optString("message") }.getOrDefault("")
                    if (detail.isBlank()) detail = "HTTP $code"
                }
            }
            if (nameChanged && !failed) {
                val (code, body) = postJson(config, prefs, "/profile", JSONObject().put("name", nameField.trim()))
                if (code in 200..299) {
                    val serverProfile = JSONObject(body).optJSONObject("profile")
                    if (serverProfile != null) {
                        profile = ServerProfile(
                            name = serverProfile.optString("name"),
                            email = serverProfile.optString("email"),
                            photo = absoluteProfileUrl(config.url, serverProfile.optString("photo")),
                        )
                        prefs.setProfileCache(
                            JSONObject()
                                .put("name", profile.name)
                                .put("email", profile.email)
                                .put("photo", profile.photo)
                                .toString()
                        )
                        nameField = profile.name
                    }
                } else {
                    failed = true
                    detail = runCatching { JSONObject(body).optString("message") }.getOrDefault("")
                    if (detail.isBlank()) detail = "HTTP $code"
                }
            }
            saveOk = !failed
            saveDetail = detail
            saving = false
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(stringResource(R.string.settings_title)) },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(
                            imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                            contentDescription = stringResource(R.string.settings_back_cd),
                        )
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.surfaceVariant,
                ),
            )
        },
    ) { padding ->
        com.stepmonitor.app.components.AppBackground {
            LazyColumn(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding)
                    .padding(20.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                // ── Personal data ────────────────────────────────────────────
                item {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Icon(
                                    Icons.Filled.ManageAccounts,
                                    contentDescription = null,
                                    tint = MaterialTheme.colorScheme.primary,
                                )
                                Spacer(Modifier.size(8.dp))
                                Text(stringResource(R.string.personal_title), style = MaterialTheme.typography.titleMedium)
                            }
                            if (personalLoadFailed) {
                                Text(
                                    stringResource(R.string.personal_load_error),
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.error,
                                )
                            }
                            if (!connected) {
                                Text(
                                    stringResource(R.string.personal_requires_connection),
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                            Row(
                                Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(12.dp),
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                if (connected && avatarModel != null) {
                                    AsyncImage(
                                        model = avatarModel,
                                        contentDescription = null,
                                        contentScale = ContentScale.Crop,
                                        modifier = Modifier.size(56.dp).clip(CircleShape),
                                    )
                                } else {
                                    Icon(
                                        if (connected) Icons.Filled.AccountCircle else Icons.Filled.PersonOff,
                                        contentDescription = null,
                                        modifier = Modifier.size(56.dp),
                                        tint = if (connected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant,
                                    )
                                }
                                Column(Modifier.weight(1f)) {
                                    Text(
                                        profile.name.ifBlank { stringResource(R.string.app_name) },
                                        style = MaterialTheme.typography.titleMedium,
                                    )
                                    Text(
                                        profile.email.ifBlank { profile.error.orEmpty() },
                                        style = MaterialTheme.typography.bodySmall,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    )
                                }
                            }
                            OutlinedTextField(
                                value = nameField,
                                onValueChange = { nameField = it },
                                label = { Text(stringResource(R.string.personal_name)) },
                                leadingIcon = { Icon(Icons.Filled.Person, contentDescription = null) },
                                singleLine = true,
                                enabled = connected,
                                modifier = Modifier.fillMaxWidth(),
                            )
                            OutlinedTextField(
                                value = profile.email,
                                onValueChange = {},
                                label = { Text(stringResource(R.string.personal_email)) },
                                leadingIcon = { Icon(Icons.Filled.Email, contentDescription = null) },
                                readOnly = true,
                                singleLine = true,
                                modifier = Modifier.fillMaxWidth(),
                            )
                            HorizontalDivider()
                            Text(stringResource(R.string.personal_body_title), style = MaterialTheme.typography.titleSmall)
                            OutlinedTextField(
                                value = goalField,
                                onValueChange = { goalField = it.filter { c -> c.isDigit() } },
                                label = { Text(stringResource(R.string.personal_goal)) },
                                leadingIcon = { Icon(Icons.Filled.Flag, contentDescription = null) },
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                singleLine = true,
                                enabled = connected,
                                modifier = Modifier.fillMaxWidth(),
                            )
                            OutlinedTextField(
                                value = heightField,
                                onValueChange = { heightField = it.filter { c -> c.isDigit() } },
                                label = { Text(stringResource(R.string.personal_height)) },
                                leadingIcon = { Icon(Icons.Filled.Height, contentDescription = null) },
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                singleLine = true,
                                enabled = connected,
                                modifier = Modifier.fillMaxWidth(),
                            )
                            OutlinedTextField(
                                value = weightField,
                                onValueChange = { weightField = it.filter { c -> c.isDigit() } },
                                label = { Text(stringResource(R.string.personal_weight)) },
                                leadingIcon = { Icon(Icons.Filled.Straighten, contentDescription = null) },
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                singleLine = true,
                                enabled = connected,
                                modifier = Modifier.fillMaxWidth(),
                            )
                            OutlinedTextField(
                                value = strideField,
                                onValueChange = { strideField = it.filter { c -> c.isDigit() } },
                                label = { Text(stringResource(R.string.personal_stride)) },
                                leadingIcon = { Icon(Icons.Filled.Straighten, contentDescription = null) },
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                singleLine = true,
                                enabled = connected,
                                modifier = Modifier.fillMaxWidth(),
                            )
                            OutlinedTextField(
                                value = birthField,
                                onValueChange = { birthField = it.filter { c -> c.isDigit() || c == '-' } },
                                label = { Text(stringResource(R.string.personal_birth)) },
                                placeholder = { Text(stringResource(R.string.personal_birth_hint)) },
                                leadingIcon = { Icon(Icons.Filled.Cake, contentDescription = null) },
                                singleLine = true,
                                enabled = connected,
                                modifier = Modifier.fillMaxWidth(),
                            )
                            Button(
                                onClick = { savePersonal() },
                                enabled = connected && !saving,
                                modifier = Modifier.fillMaxWidth(),
                            ) {
                                if (saving) {
                                    CircularProgressIndicator(
                                        modifier = Modifier.size(18.dp),
                                        strokeWidth = 2.dp,
                                        color = MaterialTheme.colorScheme.onPrimary,
                                    )
                                    Spacer(Modifier.size(8.dp))
                                } else {
                                    Icon(Icons.Filled.Save, contentDescription = null)
                                    Spacer(Modifier.size(8.dp))
                                }
                                Text(stringResource(R.string.personal_save))
                            }
                            when (saveOk) {
                                true -> Text(
                                    stringResource(R.string.personal_saved),
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.primary,
                                )
                                false -> Text(
                                    stringResource(R.string.personal_error, saveDetail),
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.error,
                                )
                                null -> {}
                            }
                        }
                    }
                }

                // ── Network ──────────────────────────────────────────────────
                item {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                            Text(stringResource(R.string.settings_network), style = MaterialTheme.typography.titleMedium)
                            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                Text(stringResource(R.string.settings_wifi_only), modifier = Modifier.weight(1f))
                                Switch(
                                    checked = wifiOnly,
                                    onCheckedChange = {
                                        wifiOnly = it
                                        prefs.setWifiOnly(it)
                                        (context.applicationContext as? HCWebhookApplication)?.scheduleSyncWork()
                                    },
                                )
                            }
                        }
                    }
                }

                // ── Updates ──────────────────────────────────────────────────
                item {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
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
                            if (connected) {
                                Text(
                                    stringResource(
                                        R.string.settings_update_source,
                                        configs.firstOrNull { it.isEnabled }?.url.orEmpty(),
                                    ),
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                        }
                    }
                }

                // ── Permissions ──────────────────────────────────────────────
                item {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                            Text(stringResource(R.string.perms_title), style = MaterialTheme.typography.titleMedium)
                            val grantedCount = HealthDataType.entries.count { type ->
                                HealthPermission.getReadPermission(type.recordClass) in grantedPermissions
                            }
                            Text(
                                stringResource(R.string.perms_granted_summary, grantedCount, HealthDataType.entries.size),
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                            Button(
                                onClick = { permissionLauncher.launch(HealthConnectManager.getPermissionsForTypes(enabled)) },
                                modifier = Modifier.fillMaxWidth(),
                            ) {
                                Text(stringResource(R.string.action_grant_permissions))
                            }
                            OutlinedButton(onClick = { permissionsSheetOpen = true }, modifier = Modifier.fillMaxWidth()) {
                                Text(stringResource(R.string.config_action_view_perms))
                            }
                        }
                    }
                }

                // ── Sync interval ────────────────────────────────────────────
                item {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                            Text(stringResource(R.string.settings_sync_interval), style = MaterialTheme.typography.titleMedium)
                            val currentLabel = if (syncMode == SyncMode.SCHEDULED) {
                                stringResource(R.string.config_sync_mode_scheduled)
                            } else {
                                intervalOptions.firstOrNull { it.first == syncInterval }?.let { stringResource(it.second) }
                                    ?: context.getString(R.string.settings_sync_hours, syncInterval / 60)
                            }
                            ExposedDropdownMenuBox(
                                expanded = intervalExpanded,
                                onExpandedChange = { intervalExpanded = !intervalExpanded },
                                modifier = Modifier.fillMaxWidth(),
                            ) {
                                OutlinedTextField(
                                    value = currentLabel,
                                    onValueChange = {},
                                    readOnly = true,
                                    label = { Text(stringResource(R.string.settings_sync_mode)) },
                                    trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = intervalExpanded) },
                                    colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
                                    modifier = Modifier.menuAnchor().fillMaxWidth(),
                                )
                                ExposedDropdownMenu(expanded = intervalExpanded, onDismissRequest = { intervalExpanded = false }) {
                                    intervalOptions.forEach { (minutes, labelRes) ->
                                        DropdownMenuItem(
                                            text = { Text(stringResource(labelRes)) },
                                            onClick = {
                                                intervalExpanded = false
                                                applySyncSchedule(SyncMode.INTERVAL, minutes)
                                            },
                                        )
                                    }
                                    DropdownMenuItem(
                                        text = { Text(stringResource(R.string.config_sync_mode_scheduled)) },
                                        onClick = {
                                            intervalExpanded = false
                                            applySyncSchedule(SyncMode.SCHEDULED)
                                        },
                                    )
                                }
                            }
                        }
                    }
                }

                if (syncMode == SyncMode.SCHEDULED) {
                    item {
                        Card(
                            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                            modifier = Modifier.fillMaxWidth(),
                        ) {
                            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                Text(stringResource(R.string.settings_sync_time), style = MaterialTheme.typography.titleMedium)
                                scheduledSyncs.forEach { schedule ->
                                    Row(
                                        Modifier.fillMaxWidth().padding(vertical = 4.dp),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically,
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

                // ── Conflicts ────────────────────────────────────────────────
                item {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                            Text(stringResource(R.string.settings_conflict_title), style = MaterialTheme.typography.titleMedium)
                            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                Column(Modifier.weight(1f)) {
                                    Text(stringResource(R.string.settings_conflict_remember))
                                    Text(
                                        stringResource(R.string.settings_conflict_remember_desc),
                                        style = MaterialTheme.typography.bodySmall,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    )
                                }
                                Switch(
                                    checked = conflictRemember,
                                    onCheckedChange = { checked ->
                                        conflictRemember = checked
                                        prefs.setConflictRememberEnabled(checked)
                                    },
                                )
                            }
                            if (conflictRemember) {
                                val actionLabels = listOf(
                                    "skip" to stringResource(R.string.conflict_skip),
                                    "replace" to stringResource(R.string.conflict_replace),
                                    "add" to stringResource(R.string.conflict_add),
                                )
                                ExposedDropdownMenuBox(
                                    expanded = conflictActionExpanded,
                                    onExpandedChange = { conflictActionExpanded = !conflictActionExpanded },
                                    modifier = Modifier.fillMaxWidth(),
                                ) {
                                    OutlinedTextField(
                                        value = actionLabels.firstOrNull { it.first == conflictDefaultAction }?.second
                                            ?: conflictDefaultAction,
                                        onValueChange = {},
                                        readOnly = true,
                                        label = { Text(stringResource(R.string.settings_conflict_default_action)) },
                                        trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = conflictActionExpanded) },
                                        colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
                                        modifier = Modifier.menuAnchor().fillMaxWidth(),
                                    )
                                    ExposedDropdownMenu(expanded = conflictActionExpanded, onDismissRequest = { conflictActionExpanded = false }) {
                                        actionLabels.forEach { (value, label) ->
                                            DropdownMenuItem(
                                                text = { Text(label) },
                                                onClick = {
                                                    conflictDefaultAction = value
                                                    prefs.setConflictDefaultAction(value)
                                                    conflictActionExpanded = false
                                                },
                                            )
                                        }
                                    }
                                }
                                TextButton(
                                    onClick = {
                                        conflictRemember = false
                                        prefs.setConflictRememberEnabled(false)
                                    },
                                    modifier = Modifier.fillMaxWidth(),
                                ) {
                                    Text(stringResource(R.string.settings_conflict_forget))
                                }
                            }
                        }
                    }
                }

                // ── Personalization ──────────────────────────────────────────
                item {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                            Text(stringResource(R.string.settings_personalization), style = MaterialTheme.typography.titleMedium)

                            Text(stringResource(R.string.about_theme), style = MaterialTheme.typography.titleSmall)
                            SingleChoiceSegmentedButtonRow(modifier = Modifier.fillMaxWidth()) {
                                listOf(
                                    "auto" to R.string.theme_auto,
                                    "light" to R.string.theme_light,
                                    "dark" to R.string.theme_dark,
                                ).forEachIndexed { index, (value, label) ->
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
                                Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                    Text(stringResource(R.string.theme_amoled), modifier = Modifier.weight(1f))
                                    Switch(
                                        checked = amoledEnabled,
                                        onCheckedChange = {
                                            amoledEnabled = it
                                            prefs.setAmoledEnabled(it)
                                            (context as? Activity)?.recreate()
                                        },
                                    )
                                }
                            }

                            Text(stringResource(R.string.about_accent_color), style = MaterialTheme.typography.titleSmall)
                            Box {
                                OutlinedButton(
                                    onClick = { accentExpanded = true },
                                    modifier = Modifier.fillMaxWidth(),
                                    shape = RoundedCornerShape(8.dp),
                                ) {
                                    Surface(
                                        color = accentColorValue(accentColor),
                                        shape = CircleShape,
                                        modifier = Modifier.size(18.dp),
                                        content = {},
                                    )
                                    Spacer(Modifier.size(10.dp))
                                    Text(stringResource(accentNameRes(accentColor)))
                                    Spacer(Modifier.weight(1f))
                                    Icon(
                                        if (accentExpanded) Icons.Filled.ExpandLess else Icons.Filled.ExpandMore,
                                        contentDescription = null,
                                    )
                                }
                                DropdownMenu(expanded = accentExpanded, onDismissRequest = { accentExpanded = false }) {
                                    listOf("blue", "teal", "orange", "pink", "purple", "gray").forEach { value ->
                                        DropdownMenuItem(
                                            text = {
                                                Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                                    Surface(
                                                        color = accentColorValue(value),
                                                        shape = CircleShape,
                                                        modifier = Modifier.size(18.dp),
                                                        content = {},
                                                    )
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
                                            modifier = Modifier.padding(horizontal = 16.dp, vertical = 12.dp),
                                        )
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
                                                language = tag
                                                prefs.setAppLanguage(tag)
                                                if (tag.isEmpty()) {
                                                    AppCompatDelegate.setApplicationLocales(LocaleListCompat.getEmptyLocaleList())
                                                } else {
                                                    AppCompatDelegate.setApplicationLocales(LocaleListCompat.forLanguageTags(tag))
                                                }
                                                showRestartDialog = true
                                            }
                                            .padding(vertical = 8.dp),
                                        verticalAlignment = Alignment.CenterVertically,
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
                                Switch(
                                    checked = portraitOnly,
                                    onCheckedChange = {
                                        portraitOnly = it
                                        prefs.setPortraitOnly(it)
                                        (context as? Activity)?.requestedOrientation =
                                            if (it) ActivityInfo.SCREEN_ORIENTATION_PORTRAIT else ActivityInfo.SCREEN_ORIENTATION_UNSPECIFIED
                                    },
                                )
                            }
                        }
                    }
                }

                // ── Data types ───────────────────────────────────────────────
                item {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            Row(
                                Modifier.fillMaxWidth().clickable { metricsExpanded = !metricsExpanded },
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                Text(
                                    stringResource(R.string.settings_metrics),
                                    style = MaterialTheme.typography.titleMedium,
                                    modifier = Modifier.weight(1f),
                                )
                                Text(
                                    "${enabled.size}/${HealthDataType.values().size}",
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                                IconButton(onClick = { metricsExpanded = !metricsExpanded }) {
                                    Icon(
                                        if (metricsExpanded) Icons.Filled.ExpandLess else Icons.Filled.ExpandMore,
                                        contentDescription = null,
                                    )
                                }
                            }
                            if (metricsExpanded) {
                                HealthDataType.values().forEach { type ->
                                    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                        Checkbox(
                                            checked = type in enabled,
                                            onCheckedChange = { checked ->
                                                enabled = if (checked) enabled + type else enabled - type
                                                prefs.setEnabledDataTypes(enabled)
                                            },
                                        )
                                        Text(stringResource(type.nameResId), modifier = Modifier.weight(1f))
                                    }
                                }
                            }
                        }
                    }
                }

                // ── Export ───────────────────────────────────────────────────
                item {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                        modifier = Modifier.fillMaxWidth(),
                    ) {
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
        }
    }

    if (permissionsSheetOpen) {
        PermissionsBottomSheet(
            grantedPermissionsSet = grantedPermissions,
            onDismiss = { permissionsSheetOpen = false },
        )
    }

    if (showExportSheet) {
        val sheetState = rememberModalBottomSheetState()
        ModalBottomSheet(onDismissRequest = { showExportSheet = false }, sheetState = sheetState) {
            Column(
                modifier = Modifier
                    .padding(horizontal = 24.dp, vertical = 16.dp)
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(16.dp),
            ) {
                Text(stringResource(R.string.health_export_title), style = MaterialTheme.typography.titleLarge)

                Text(stringResource(R.string.export_data_types), style = MaterialTheme.typography.titleSmall)
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                ) {
                    Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        HealthDataType.values().forEach { type ->
                            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                Checkbox(
                                    checked = type in exportSelectedTypes,
                                    onCheckedChange = {
                                        exportSelectedTypes = if (it) exportSelectedTypes + type else exportSelectedTypes - type
                                    },
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
                        R.string.export_range_all,
                    )
                    labels.forEachIndexed { idx, labelRes ->
                        SegmentedButton(
                            selected = exportRange == idx,
                            onClick = { exportRange = idx },
                            shape = SegmentedButtonDefaults.itemShape(idx, labels.size),
                            modifier = Modifier.weight(1f),
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
                            modifier = Modifier.weight(1f),
                        ) { Text(stringResource(labelRes)) }
                    }
                }

                Button(
                    onClick = {
                        showExportSheet = false
                        performExport(context, prefs, exportSelectedTypes, exportRange, exportFormat)
                    },
                    modifier = Modifier.fillMaxWidth(),
                    enabled = exportSelectedTypes.isNotEmpty(),
                ) {
                    Icon(Icons.Filled.Download, contentDescription = null)
                    Text(stringResource(R.string.export_confirm), modifier = Modifier.padding(start = 8.dp))
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
                    (context as? Activity)?.recreate()
                }) { Text(stringResource(R.string.about_restart)) }
            },
            dismissButton = {
                TextButton(onClick = { showRestartDialog = false }) { Text(stringResource(R.string.about_later)) }
            },
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
                    val updated = scheduledSyncs.map {
                        if (it.id == target.id) it.copy(hour = pickerState.hour, minute = pickerState.minute) else it
                    }
                    prefs.setScheduledSyncs(updated)
                    scheduledSyncs = updated
                    scheduleToEdit = null
                    ScheduledSyncManager(context).scheduleAllAlarms()
                }) { Text(stringResource(R.string.action_ok)) }
            },
            dismissButton = { TextButton(onClick = { scheduleToEdit = null }) { Text(stringResource(R.string.action_cancel)) } },
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

private fun performExport(
    context: android.content.Context,
    prefs: PreferencesManager,
    selectedTypes: Set<HealthDataType>,
    rangeIndex: Int,
    formatIndex: Int,
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
                    "\"${log.errorMessage.orEmpty().replace("\"", "\"\"")}\"",
                ).joinToString(","),
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
