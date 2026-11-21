package orbit.nex.steps.screens

import orbit.nex.steps.ui.theme.Spacing
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
import androidx.compose.foundation.layout.height
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
import androidx.compose.material.icons.automirrored.filled.ArrowForward
import androidx.compose.material.icons.automirrored.filled.List
import androidx.compose.material.icons.filled.Update
import androidx.compose.material.icons.filled.AccountCircle
import androidx.compose.material.icons.filled.Cake
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.CloudDone
import androidx.compose.material.icons.filled.Download
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.Email
import androidx.compose.material.icons.filled.ExpandLess
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material.icons.filled.Flag
import androidx.compose.material.icons.filled.Height
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.ManageAccounts
import androidx.compose.material.icons.filled.Palette
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.PersonOff
import androidx.compose.material.icons.filled.Save
import androidx.compose.material.icons.filled.Straighten
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
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
import androidx.compose.material3.rememberDatePickerState
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
import orbit.nex.steps.HCWebhookApplication
import orbit.nex.steps.HealthConnectManager
import orbit.nex.steps.HealthDataType
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.R
import orbit.nex.steps.ScheduledSync
import orbit.nex.steps.ScheduledSyncManager
import orbit.nex.steps.SyncMode
import orbit.nex.steps.WebhookConfig
import orbit.nex.steps.components.APP_LANGUAGES
import orbit.nex.steps.components.absoluteProfileUrl
import orbit.nex.steps.components.downloadAvatarFile
import orbit.nex.steps.components.localAvatarFile
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
private const val KEY_OCCUPATION = "occupation"
private val OCCUPATIONS = listOf("student", "school_student", "office_worker", "remote_worker", "freelancer", "entrepreneur", "self_employed", "industrial_worker", "driver_courier", "taxi_driver", "retail_worker", "teacher", "healthcare_worker", "military", "public_servant", "retired", "unemployed", "homemaker", "athlete", "frequent_traveler", "working_student", "other", "prefer_not_to_say")
private val OCCUPATION_LABELS = listOf(R.string.occupation_student, R.string.occupation_school_student, R.string.occupation_office_worker, R.string.occupation_remote_worker, R.string.occupation_freelancer, R.string.occupation_entrepreneur, R.string.occupation_self_employed, R.string.occupation_industrial_worker, R.string.occupation_driver_courier, R.string.occupation_taxi_driver, R.string.occupation_retail_worker, R.string.occupation_teacher, R.string.occupation_healthcare_worker, R.string.occupation_military, R.string.occupation_public_servant, R.string.occupation_retired, R.string.occupation_unemployed, R.string.occupation_homemaker, R.string.occupation_athlete, R.string.occupation_frequent_traveler, R.string.occupation_working_student, R.string.occupation_other, R.string.occupation_prefer_not_to_say)
private val PERSONAL_KEYS = listOf(KEY_OCCUPATION, KEY_GOAL, KEY_HEIGHT, KEY_WEIGHT, KEY_STRIDE, KEY_BIRTH)

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
/** Personal settings cache written by every successful load/save. */
private fun parsePersonalCache(prefs: PreferencesManager): Map<String, String> {
    val raw = prefs.getPersonalSettingsCache() ?: return emptyMap()
    return runCatching {
        val json = JSONObject(raw)
        PERSONAL_KEYS.mapNotNull { key -> if (json.has(key)) key to json.optString(key) else null }.toMap()
    }.getOrDefault(emptyMap())
}

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
 * Settings screen hosted by [orbit.nex.steps.SettingsActivity].
 * Holds the user's personal data plus every app setting, so the profile tab
 * stays a light overview and this one screen is the single settings surface.
 */
enum class SettingsSection(val titleRes: Int) {
    PROFILE(R.string.personal_title),
    SYNC(R.string.settings_group_sync),
    PERMISSIONS(R.string.perms_title),
    DATA(R.string.settings_group_data),
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SettingsScreen(onBack: () -> Unit, section: SettingsSection = SettingsSection.PROFILE) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val scope = rememberCoroutineScope()

    var configs by remember { mutableStateOf(prefs.getWebhookConfigs()) }
    val connected = configs.any { it.isEnabled }

    // â”€â”€ Personal data â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    var profile by remember { mutableStateOf(profileFromJson(prefs.getProfileCache(Long.MAX_VALUE)) ?: ServerProfile()) }
    // Rendered from the local cache first, so opening settings never flashes
    // empty fields while the server request is in flight.
    var personal by remember { mutableStateOf(parsePersonalCache(prefs)) }
    var goalField by remember { mutableStateOf(personal[KEY_GOAL].orEmpty()) }
    var heightField by remember { mutableStateOf(personal[KEY_HEIGHT].orEmpty()) }
    var weightField by remember { mutableStateOf(personal[KEY_WEIGHT].orEmpty()) }
    var strideField by remember { mutableStateOf(personal[KEY_STRIDE].orEmpty()) }
    var occupationField by remember { mutableStateOf(personal[KEY_OCCUPATION] ?: "prefer_not_to_say") }
    var occupationExpanded by remember { mutableStateOf(false) }
    var birthField by remember { mutableStateOf(personal[KEY_BIRTH].orEmpty()) }
    var goalError by remember { mutableStateOf<String?>(null) }
    var heightError by remember { mutableStateOf<String?>(null) }
    var weightError by remember { mutableStateOf<String?>(null) }
    var strideError by remember { mutableStateOf<String?>(null) }
    var birthError by remember { mutableStateOf<String?>(null) }
    var showBirthPicker by remember { mutableStateOf(false) }
    var personalLoadFailed by remember { mutableStateOf(false) }
    var saving by remember { mutableStateOf(false) }
    var saveOk by remember { mutableStateOf<Boolean?>(null) }
    var saveDetail by remember { mutableStateOf("") }
    var showSavedToast by remember { mutableStateOf(false) }
    LaunchedEffect(showSavedToast) {
        if (showSavedToast) {
            kotlinx.coroutines.delay(2_000)
            showSavedToast = false
        }
    }

    // â”€â”€ App settings â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    var enabled by remember { mutableStateOf(prefs.getEnabledDataTypes().ifEmpty { HealthDataType.values().toSet() }) }
    var grantedPermissions by remember { mutableStateOf(prefs.getKnownGrantedPermissions()) }
    var wifiOnly by remember { mutableStateOf(prefs.isWifiOnly()) }
    var autoUpdate by remember { mutableStateOf(prefs.isAutoUpdateEnabled()) }
    var syncMode by remember { mutableStateOf(prefs.getSyncMode()) }
    var exactAlarmsAllowed by remember { mutableStateOf(ScheduledSyncManager(context).canScheduleExactAlarms()) }
    val exactAlarmLauncher = rememberLauncherForActivityResult(androidx.activity.result.contract.ActivityResultContracts.StartActivityForResult()) {
        exactAlarmsAllowed = ScheduledSyncManager(context).canScheduleExactAlarms()
        if (syncMode == SyncMode.INTERVAL) ScheduledSyncManager(context).scheduleNextIntervalSync() else ScheduledSyncManager(context).scheduleAllAlarms()
    }
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
    // Update controls live only here; the home tab no longer carries them.
    val updateChecker = remember { orbit.nex.steps.updates.AppUpdateChecker(context) }
    var updateBusy by remember { mutableStateOf(false) }
    var updateStatus by remember { mutableStateOf<String?>(null) }
    var downloadedApk by remember { mutableStateOf(updateChecker.latestDownloaded()) }
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
        }
        val (values, ok) = loadPersonalSettings(config, prefs)
        personalLoadFailed = !ok
        if (ok) {
            prefs.setPersonalSettingsCache(values.toJSONObject().toString())
            personal = values
            goalField = values[KEY_GOAL] ?: goalField
            heightField = values[KEY_HEIGHT] ?: heightField
            weightField = values[KEY_WEIGHT] ?: weightField
            strideField = values[KEY_STRIDE] ?: strideField
            birthField = values[KEY_BIRTH] ?: birthField
            occupationField = values[KEY_OCCUPATION] ?: occupationField
        }
    }

    fun applySyncSchedule(mode: SyncMode, interval: Int = syncInterval) {
        saveOk = true
        showSavedToast = true
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

    /** Validates every personal field and keeps a message next to each one. */
    fun validatePersonal(): Boolean {
        fun intIn(raw: String, min: Int, max: Int): Boolean =
            raw.isNotBlank() && raw.matches(Regex("^\\d+$")) && raw.toIntOrNull() in min..max
        goalError = if (goalField.isBlank() || intIn(goalField, 1000, 200000)) null
        else context.getString(R.string.personal_goal_invalid)
        heightError = if (heightField.isBlank() || intIn(heightField, 50, 260)) null
        else context.getString(R.string.personal_height_invalid)
        weightError = if (weightField.isBlank() || intIn(weightField, 20, 400)) null
        else context.getString(R.string.personal_weight_invalid)
        strideError = if (strideField.isBlank() || intIn(strideField, 20, 250)) null
        else context.getString(R.string.personal_stride_invalid)
        birthError = if (birthField.isBlank()) null else runCatching {
            val parsed = java.time.LocalDate.parse(birthField)
            if (parsed.isAfter(java.time.LocalDate.now()) || parsed.isBefore(java.time.LocalDate.parse("1900-01-01"))) {
                context.getString(R.string.personal_birth_invalid)
            } else null
        }.getOrElse { context.getString(R.string.personal_birth_invalid) }
        return listOf(goalError, heightError, weightError, strideError, birthError).all { it == null }
    }

    fun savePersonal() {
        if (!validatePersonal()) {
            saveOk = null
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
            putIfChanged(KEY_OCCUPATION, occupationField)
            putIfChanged(KEY_GOAL, goalField)
            putIfChanged(KEY_HEIGHT, heightField)
            putIfChanged(KEY_WEIGHT, weightField)
            putIfChanged(KEY_STRIDE, strideField)
            putIfChanged(KEY_BIRTH, birthField)

            var failed = false
            var detail = ""
            if (payload.isNotEmpty()) {
                val (code, body) = postJson(config, prefs, "/settings", payload.toJSONObject())
                if (code in 200..299) {
                    val updated = JSONObject(body).optJSONObject("settings")
                    if (payload.containsKey(KEY_OCCUPATION) && (updated == null || updated.optString(KEY_OCCUPATION) != payload[KEY_OCCUPATION])) {
                        failed = true
                        detail = context.getString(R.string.occupation_server_unsupported)
                    } else if (updated != null) {
                        val refreshed = mutableMapOf<String, String>()
                        PERSONAL_KEYS.forEach { key -> refreshed[key] = updated.optString(key) }
                        personal = refreshed
                        goalField = refreshed[KEY_GOAL] ?: goalField
                        heightField = refreshed[KEY_HEIGHT] ?: heightField
                        weightField = refreshed[KEY_WEIGHT] ?: weightField
                        strideField = refreshed[KEY_STRIDE] ?: strideField
                        birthField = refreshed[KEY_BIRTH] ?: birthField
                        occupationField = refreshed[KEY_OCCUPATION] ?: occupationField
                        prefs.setPersonalSettingsCache(refreshed.toJSONObject().toString())
                    }
                } else {
                    failed = true
                    detail = runCatching { JSONObject(body).optString("message") }.getOrDefault("")
                    if (detail.isBlank()) detail = "HTTP $code"
                }
            }
            saveOk = !failed
            saveDetail = detail
            showSavedToast = true
            saving = false
        }
    }

    if (showBirthPicker) {
        val today = java.time.LocalDate.now()
        val minDate = java.time.LocalDate.parse("1900-01-01")
        val initialDate = runCatching { java.time.LocalDate.parse(birthField) }
            .getOrNull()?.takeIf { !it.isAfter(today) && !it.isBefore(minDate) } ?: today.minusYears(30)
        val pickerState = rememberDatePickerState(
            initialSelectedDateMillis = initialDate.atStartOfDay(java.time.ZoneOffset.UTC).toInstant().toEpochMilli()
        )
        DatePickerDialog(
            onDismissRequest = { showBirthPicker = false },
            confirmButton = {
                TextButton(onClick = {
                    val millis = pickerState.selectedDateMillis
                    if (millis != null) {
                        val picked = java.time.Instant.ofEpochMilli(millis)
                            .atZone(java.time.ZoneOffset.UTC).toLocalDate()
                        if (!picked.isAfter(today) && !picked.isBefore(minDate)) {
                            birthField = picked.toString()
                            birthError = null
                        } else {
                            birthError = context.getString(R.string.personal_birth_invalid)
                        }
                    }
                    showBirthPicker = false
                }) { Text(stringResource(android.R.string.ok)) }
            },
            dismissButton = {
                TextButton(onClick = { showBirthPicker = false }) {
                    Text(stringResource(android.R.string.cancel))
                }
            },
        ) {
            DatePicker(state = pickerState)
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(stringResource(section.titleRes)) },
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
        orbit.nex.steps.components.AppBackground {
            Box(Modifier.fillMaxSize().padding(padding)) {
            LazyColumn(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(horizontal = 10.dp),
                verticalArrangement = Arrangement.spacedBy(Spacing.md),
                contentPadding = androidx.compose.foundation.layout.PaddingValues(top = Spacing.md, bottom = Spacing.md),
            ) {
                // â”€â”€ Personal data â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                if (section == SettingsSection.PROFILE) {
                item {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(top = 6.dp),
                    ) {
                        Column(Modifier.padding(Spacing.lg), verticalArrangement = Arrangement.spacedBy(10.dp)) {
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
                            OutlinedTextField(
                                value = profile.email,
                                onValueChange = {},
                                label = { Text(stringResource(R.string.personal_email)) },
                                leadingIcon = { Icon(Icons.Filled.Email, contentDescription = null) },
                                readOnly = true,
                                singleLine = true,
                                modifier = Modifier.fillMaxWidth(),
                            )
                            ExposedDropdownMenuBox(expanded = occupationExpanded, onExpandedChange = { occupationExpanded = !occupationExpanded }) {
                                OutlinedTextField(value = stringResource(OCCUPATION_LABELS[OCCUPATIONS.indexOf(occupationField).coerceAtLeast(0)]), onValueChange = {}, readOnly = true, enabled = connected, label = { Text(stringResource(R.string.occupation_title)) }, trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(occupationExpanded) }, modifier = Modifier.fillMaxWidth().menuAnchor())
                                ExposedDropdownMenu(expanded = occupationExpanded, onDismissRequest = { occupationExpanded = false }) {
                                    OCCUPATIONS.forEachIndexed { i, value -> DropdownMenuItem(text = { Text(stringResource(OCCUPATION_LABELS[i])) }, onClick = { occupationField = value; occupationExpanded = false }) }
                                }
                            }
                            HorizontalDivider()
                            Text(stringResource(R.string.personal_body_title), style = MaterialTheme.typography.titleSmall)
                            OutlinedTextField(
                                value = goalField,
                                onValueChange = { goalField = it.filter { c -> c.isDigit() }.take(6); goalError = null },
                                label = { Text(stringResource(R.string.personal_goal)) },
                                leadingIcon = { Icon(Icons.Filled.Flag, contentDescription = null) },
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                isError = goalError != null,
                                supportingText = { goalError?.let { message -> Text(message) } },
                                singleLine = true,
                                enabled = connected,
                                modifier = Modifier.fillMaxWidth(),
                            )
                            OutlinedTextField(
                                value = heightField,
                                onValueChange = { heightField = it.filter { c -> c.isDigit() }.take(3); heightError = null },
                                label = { Text(stringResource(R.string.personal_height)) },
                                leadingIcon = { Icon(Icons.Filled.Height, contentDescription = null) },
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                isError = heightError != null,
                                supportingText = { heightError?.let { message -> Text(message) } },
                                singleLine = true,
                                enabled = connected,
                                modifier = Modifier.fillMaxWidth(),
                            )
                            OutlinedTextField(
                                value = weightField,
                                onValueChange = { weightField = it.filter { c -> c.isDigit() }.take(3); weightError = null },
                                label = { Text(stringResource(R.string.personal_weight)) },
                                leadingIcon = { Icon(Icons.Filled.Straighten, contentDescription = null) },
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                isError = weightError != null,
                                supportingText = { weightError?.let { message -> Text(message) } },
                                singleLine = true,
                                enabled = connected,
                                modifier = Modifier.fillMaxWidth(),
                            )
                            OutlinedTextField(
                                value = strideField,
                                onValueChange = { strideField = it.filter { c -> c.isDigit() }.take(3); strideError = null },
                                label = { Text(stringResource(R.string.personal_stride)) },
                                leadingIcon = { Icon(Icons.Filled.Straighten, contentDescription = null) },
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                isError = strideError != null,
                                supportingText = { strideError?.let { message -> Text(message) } },
                                singleLine = true,
                                enabled = connected,
                                modifier = Modifier.fillMaxWidth(),
                            )
                            Box(Modifier.fillMaxWidth()) {
                                OutlinedTextField(
                                    value = birthField,
                                    onValueChange = {},
                                    label = { Text(stringResource(R.string.personal_birth)) },
                                    placeholder = { Text(stringResource(R.string.personal_birth_hint)) },
                                    leadingIcon = { Icon(Icons.Filled.Cake, contentDescription = null) },
                                    trailingIcon = { Icon(Icons.Filled.CalendarMonth, contentDescription = null) },
                                    isError = birthError != null,
                                    supportingText = { birthError?.let { message -> Text(message) } },
                                    singleLine = true,
                                    readOnly = true,
                                    modifier = Modifier.fillMaxWidth(),
                                )
                                // Transparent overlay: the read-only field itself ignores taps.
                                Box(
                                    Modifier
                                        .matchParentSize()
                                        .clickable(enabled = connected) { showBirthPicker = true }
                                )
                            }
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
                }

                // â”€â”€ Network â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                if (section == SettingsSection.SYNC) {
                item {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Column(Modifier.padding(Spacing.lg), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                            Text(stringResource(R.string.settings_network), style = MaterialTheme.typography.titleMedium)
                            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                Text(stringResource(R.string.settings_wifi_only), modifier = Modifier.weight(1f))
                                Switch(
                                    checked = wifiOnly,
                                    onCheckedChange = {
                                        wifiOnly = it
                                        prefs.setWifiOnly(it)
                                        saveOk = true
                                        showSavedToast = true
                                        (context.applicationContext as? HCWebhookApplication)?.scheduleSyncWork()
                                    },
                                )
                            }
                        }
                    }
                }
                }

                // â”€â”€ Permissions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                if (section == SettingsSection.PERMISSIONS) {
                item {
                    HealthPermissionsScreen(
                        grantedPermissionsSet = grantedPermissions,
                        onRequestPermissions = { permissionLauncher.launch(HealthConnectManager.getPermissionsForTypes(enabled)) },
                        modifier = Modifier.fillMaxWidth().height(780.dp),
                    )
                }
                }

                // â”€â”€ Sync interval â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                if (section == SettingsSection.SYNC) {
                item {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Column(Modifier.padding(Spacing.lg), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                            Text(stringResource(R.string.settings_sync_interval), style = MaterialTheme.typography.titleMedium)
                            if (!exactAlarmsAllowed && android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.S) {
                                OutlinedButton(onClick = {
                                    val intent = Intent(android.provider.Settings.ACTION_REQUEST_SCHEDULE_EXACT_ALARM, android.net.Uri.parse("package:${context.packageName}"))
                                    runCatching { exactAlarmLauncher.launch(intent) }
                                }, modifier = Modifier.fillMaxWidth()) { Text(stringResource(R.string.exact_alarm_warning), textAlign = androidx.compose.ui.text.style.TextAlign.Center) }
                            }
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
                }

                if (section == SettingsSection.SYNC && syncMode == SyncMode.SCHEDULED) {
                    item {
                        Card(
                            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                            modifier = Modifier.fillMaxWidth(),
                        ) {
                            Column(Modifier.padding(Spacing.lg), verticalArrangement = Arrangement.spacedBy(Spacing.xs)) {
                                Text(stringResource(R.string.settings_sync_time), style = MaterialTheme.typography.titleMedium)
                                scheduledSyncs.forEach { schedule ->
                                    Row(
                                        Modifier.fillMaxWidth().padding(vertical = Spacing.xs),
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

                // â”€â”€ Conflicts â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                if (section == SettingsSection.SYNC) {
                item {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Column(Modifier.padding(Spacing.lg), verticalArrangement = Arrangement.spacedBy(10.dp)) {
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
                                        saveOk = true
                                        showSavedToast = true
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
                                        saveOk = true
                                        showSavedToast = true
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
                                        saveOk = true
                                        showSavedToast = true
                                    },
                                    modifier = Modifier.fillMaxWidth(),
                                ) {
                                    Text(stringResource(R.string.settings_conflict_forget))
                                }
                            }
                        }
                    }
                }
                }

                // â”€â”€ Location tracking â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                // Recording controls and sampling live on their own screen, the
                // same one the map links to.

                // â”€â”€ Personalization â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                // Theme, accent, language and orientation live on their own
                // activity; this list only links to it.

                // â”€â”€ Data types â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                if (section == SettingsSection.DATA) {
                item {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Column(Modifier.padding(Spacing.lg), verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
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
                                        contentDescription = stringResource(if (metricsExpanded) R.string.a11y_collapse else R.string.a11y_expand),
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
                                        saveOk = true
                                        showSavedToast = true
                                            },
                                        )
                                        Text(stringResource(type.nameResId), modifier = Modifier.weight(1f))
                                    }
                                }
                            }
                        }
                    }
                }
                }

                // â”€â”€ Export â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                if (section == SettingsSection.DATA) {
                item {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Column(Modifier.padding(Spacing.lg), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                            Text(stringResource(R.string.health_export_title), style = MaterialTheme.typography.titleMedium)
                            Button(onClick = { showExportSheet = true }, modifier = Modifier.fillMaxWidth()) {
                                Icon(Icons.Filled.Download, contentDescription = null)
                                Text(stringResource(R.string.health_export_title), modifier = Modifier.padding(start = Spacing.sm))
                            }
                        }
                    }
                }
                item { LocationExportCard(prefs) }
                }
            }
            if (showSavedToast) {
                orbit.nex.steps.components.SavedToast(
                    stringResource(if (saveOk == false) R.string.save_failed_toast else R.string.saved_toast),
                    Modifier.align(Alignment.BottomCenter).padding(bottom = Spacing.xl),
                    isError = saveOk == false,
                    onDismiss = { showSavedToast = false },
                )
            }
            }
        }
    }

    if (showExportSheet) {
        val sheetState = rememberModalBottomSheetState()
        ModalBottomSheet(onDismissRequest = { showExportSheet = false }, sheetState = sheetState) {
            Column(
                modifier = Modifier
                    .padding(horizontal = Spacing.xl, vertical = Spacing.lg)
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(Spacing.lg),
            ) {
                Text(stringResource(R.string.health_export_title), style = MaterialTheme.typography.titleLarge)

                Text(stringResource(R.string.export_data_types), style = MaterialTheme.typography.titleSmall)
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                ) {
                    Column(Modifier.padding(Spacing.lg), verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
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
                    Text(stringResource(R.string.export_confirm), modifier = Modifier.padding(start = Spacing.sm))
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
                                        saveOk = true
                                        showSavedToast = true
                    scheduledSyncs = updated
                    scheduleToEdit = null
                    ScheduledSyncManager(context).scheduleAllAlarms()
                }) { Text(stringResource(R.string.action_ok)) }
                TextButton(onClick = { scheduleToEdit = null }) { Text(stringResource(R.string.action_cancel)) }
            },
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

internal fun accentNameRes(value: String): Int = when (value) {
    "dynamic" -> R.string.accent_dynamic
    "teal" -> R.string.accent_teal
    "orange" -> R.string.accent_orange
    "pink" -> R.string.accent_pink
    "purple" -> R.string.accent_purple
    "gray" -> R.string.accent_gray
    else -> R.string.accent_blue
}

internal fun accentColorValue(value: String): Color = when (value) {
    "teal" -> Color(0xFF00897B)
    "orange" -> Color(0xFFE8710A)
    "pink" -> Color(0xFFD81B60)
    "purple" -> Color(0xFF7E57C2)
    "gray" -> Color(0xFF475569)
    else -> Color(0xFF4285F4)
}

private fun performExport(context: android.content.Context, prefs: PreferencesManager, selectedTypes: Set<HealthDataType>, rangeIndex: Int, formatIndex: Int) {
    kotlinx.coroutines.CoroutineScope(kotlinx.coroutines.SupervisorJob() + kotlinx.coroutines.Dispatchers.Main).launch {
        runCatching {
            withContext(Dispatchers.IO) {
                val now = java.time.Instant.now()
                val days = when (rangeIndex) { 0 -> 1L; 1 -> 7L; 2 -> 30L; else -> 36500L }
                val data = HealthConnectManager(context).readHealthData(selectedTypes, emptyMap(), start = now.minusSeconds(days * 86400), end = now).getOrThrow()
                val json = orbit.nex.steps.SyncManager(context).buildJsonPayload(data)
                val file = File(context.cacheDir, "exports/health-export." + if (formatIndex == 0) "json" else "csv").apply { parentFile?.mkdirs() }
                if (formatIndex == 0) file.writeText(json) else {
                    val root = org.json.JSONObject(json)
                    file.bufferedWriter().use { out ->
                        out.write("type,record\n")
                        root.keys().forEach { key ->
                            val records = root.optJSONArray(key) ?: return@forEach
                            for (i in 0 until records.length()) out.write("\"" + key.replace("\"", "\"\"") + "\",\"" + records.get(i).toString().replace("\"", "\"\"") + "\"\n")
                        }
                    }
                }
                file
            }
        }.onSuccess { shareExport(context, it, if (formatIndex == 0) "application/json" else "text/csv") }
            .onFailure { android.widget.Toast.makeText(context, it.message ?: context.getString(R.string.save_failed_toast), android.widget.Toast.LENGTH_LONG).show() }
    }
}
private fun shareExport(context: android.content.Context, file: File, mime: String) {
    val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
    context.startActivity(Intent.createChooser(Intent(Intent.ACTION_SEND).apply {
        type = mime
        putExtra(Intent.EXTRA_STREAM, uri)
        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
    }, context.getString(R.string.export_confirm)))
}


@Composable
private fun LocationExportCard(prefs: PreferencesManager) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var contents by remember { mutableIntStateOf(2) }
    var range by remember { mutableIntStateOf(1) }
    var format by remember { mutableIntStateOf(0) }
    var from by remember { mutableStateOf(java.time.LocalDate.now().minusDays(6)) }
    var to by remember { mutableStateOf(java.time.LocalDate.now()) }
    var picker by remember { mutableIntStateOf(0) }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    if (picker != 0) orbit.nex.steps.components.AppDatePickerDialog(if (picker == 1) from else to, if (picker == 2) from else null, if (picker == 1) to else java.time.LocalDate.now(), { picker = 0 }) { date -> if (picker == 1) from = date else to = date; picker = 0 }
    Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(22.dp)) {
        Column(Modifier.padding(Spacing.lg), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Text(stringResource(R.string.location_export_title), style = MaterialTheme.typography.titleMedium)
            Text(stringResource(R.string.location_export_desc), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Text(stringResource(R.string.location_export_include), style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                listOf(R.string.location_export_routes, R.string.location_export_points, R.string.location_export_both).forEachIndexed { i, label ->
                    androidx.compose.material3.FilterChip(selected = contents == i, onClick = { contents = i }, label = { Text(stringResource(label), maxLines = 1) }, modifier = Modifier.weight(1f))
                }
            }
            Text(stringResource(R.string.export_time_range), style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            listOf(listOf(R.string.location_export_today, R.string.export_range_7d), listOf(R.string.export_range_30d, R.string.location_export_custom)).forEachIndexed { row, labels ->
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    labels.forEachIndexed { col, label -> val i = row * 2 + col; androidx.compose.material3.FilterChip(selected = range == i, onClick = { range = i }, label = { Text(stringResource(label), maxLines = 1) }, modifier = Modifier.weight(1f)) }
                }
            }
            if (range == 3) Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                OutlinedButton(onClick = { picker = 1 }, modifier = Modifier.weight(1f)) { Text(from.toString()) }
                OutlinedButton(onClick = { picker = 2 }, modifier = Modifier.weight(1f)) { Text(to.toString()) }
            }
            Text(stringResource(R.string.export_format), style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                listOf("GPX", "GeoJSON", "CSV").forEachIndexed { i, label -> androidx.compose.material3.FilterChip(selected = format == i, onClick = { format = i }, label = { Text(label) }, modifier = Modifier.weight(1f)) }
            }
            error?.let { Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall) }
            OutlinedButton(onClick = {
                val active = prefs.getWebhookConfigs().firstOrNull { it.isEnabled }
                if (active == null) { error = context.getString(R.string.points_no_connection); return@OutlinedButton }
                val today = java.time.LocalDate.now()
                val start = when (range) { 0 -> today; 1 -> today.minusDays(6); 2 -> today.minusDays(29); else -> from }
                val end = if (range == 3) to else today
                busy = true; error = null
                scope.launch {
                    runCatching {
                        val zone = java.time.ZoneId.systemDefault()
                        val fromTime = start.atStartOfDay(zone).toInstant().toString()
                        val endExclusive = end.plusDays(1).atStartOfDay(zone).toInstant()
                        val toTime = endExclusive.toString()
                        val repo = orbit.nex.steps.location.PointManagementRepository(prefs, context)
                        val points = mutableListOf<orbit.nex.steps.location.ManagedPoint>()
                        var page = 0
                        while (true) {
                            if (page > 0 && page % 100 == 0) kotlinx.coroutines.delay(60_000)
                            val result = repo.loadPage(active, page++, fromTime, toTime, null, null, pageSize = 200)
                            if (result.error != null) error(result.error)
                            points += result.points
                            if (points.size >= result.total || result.points.isEmpty()) break
                        }
                        withContext(Dispatchers.IO) { writeLocationExport(context, points.filter { runCatching { java.time.Instant.parse(it.sourceAt).isBefore(endExclusive) }.getOrDefault(false) }, contents, format) }
                    }.onSuccess { shareExport(context, it, when (format) { 0 -> "application/gpx+xml"; 1 -> "application/geo+json"; else -> "text/csv" }) }
                        .onFailure { error = it.message ?: context.getString(R.string.save_failed_toast) }
                    busy = false
                }
            }, enabled = !busy, modifier = Modifier.fillMaxWidth()) {
                if (busy) CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp) else Text(stringResource(R.string.location_export_button, listOf("GPX", "GeoJSON", "CSV")[format]))
            }
        }
    }
}
private fun writeLocationExport(context: android.content.Context, input: List<orbit.nex.steps.location.ManagedPoint>, contents: Int, format: Int): File {
    val points = input.distinctBy { it.id }.sortedBy { it.sourceAt }
    val segments = mutableListOf<MutableList<orbit.nex.steps.location.ManagedPoint>>()
    points.forEach { point ->
        val previous = segments.lastOrNull()?.lastOrNull()
        val gap = if (previous == null) Long.MAX_VALUE else runCatching { java.time.Duration.between(java.time.Instant.parse(previous.sourceAt), java.time.Instant.parse(point.sourceAt)).seconds }.getOrDefault(Long.MAX_VALUE)
        if (gap < 0 || gap > 300) segments.add(mutableListOf())
        segments.last().add(point)
    }
    val file = File(context.cacheDir, "exports/location-export." + listOf("gpx", "geojson", "csv")[format]).apply { parentFile?.mkdirs() }
    file.bufferedWriter().use { out ->
        when (format) {
            0 -> {
                out.write("<?xml version=\"1.0\" encoding=\"UTF-8\"?><gpx version=\"1.1\" creator=\"NexSteps\" xmlns=\"http://www.topografix.com/GPX/1/1\">")
                if (contents != 0) points.forEach { out.write("<wpt lat=\"${it.lat}\" lon=\"${it.lon}\"><time>${it.sourceAt.replace("&", "&amp;").replace("<", "&lt;")}</time></wpt>") }
                if (contents != 1) segments.filter { it.size >= 2 }.forEach { segment -> out.write("<trk><trkseg>"); segment.forEach { out.write("<trkpt lat=\"${it.lat}\" lon=\"${it.lon}\"><time>${it.sourceAt.replace("&", "&amp;").replace("<", "&lt;")}</time></trkpt>") }; out.write("</trkseg></trk>") }
                out.write("</gpx>")
            }
            1 -> {
                val features = org.json.JSONArray()
                if (contents != 0) points.forEach { features.put(org.json.JSONObject().put("type", "Feature").put("geometry", org.json.JSONObject().put("type", "Point").put("coordinates", org.json.JSONArray().put(it.lon).put(it.lat))).put("properties", org.json.JSONObject().put("source_at", it.sourceAt).put("accuracy_m", it.accuracyM))) }
                if (contents != 1) segments.filter { it.size >= 2 }.forEach { segment -> val coordinates = org.json.JSONArray(); segment.forEach { coordinates.put(org.json.JSONArray().put(it.lon).put(it.lat)) }; features.put(org.json.JSONObject().put("type", "Feature").put("geometry", org.json.JSONObject().put("type", "LineString").put("coordinates", coordinates)).put("properties", org.json.JSONObject())) }
                out.write(org.json.JSONObject().put("type", "FeatureCollection").put("features", features).toString())
            }
            else -> {
                out.write("type,segment,latitude,longitude,source_at,accuracy_m\n")
                segments.forEachIndexed { index, segment -> segment.forEach { p ->
                    if (contents != 0) out.write("point,$index,${p.lat},${p.lon},${p.sourceAt},${p.accuracyM ?: ""}\n")
                    if (contents != 1 && segment.size >= 2) out.write("route,$index,${p.lat},${p.lon},${p.sourceAt},${p.accuracyM ?: ""}\n")
                } }
            }
        }
    }
    return file
}
