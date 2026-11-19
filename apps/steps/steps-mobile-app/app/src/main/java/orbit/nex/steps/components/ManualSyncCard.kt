package orbit.nex.steps.components

import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.health.connect.client.PermissionController
import androidx.compose.foundation.clickable
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.Saver
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.health.connect.client.HealthConnectClient
import orbit.nex.steps.HealthConnectManager
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.SyncManager
import orbit.nex.steps.SyncResult
import orbit.nex.steps.WebhookConfig
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId
import java.time.ZoneOffset
import androidx.compose.ui.res.stringResource
import orbit.nex.steps.R

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ManualSyncCard(historyRequest: Int = 0, onSyncCompleted: () -> Unit = {}) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val preferencesManager = remember { PreferencesManager(context) }
    
    var isSyncing by remember { mutableStateOf(false) }
    var syncMessage by remember { mutableStateOf<String?>(null) }
    var showConfirmSheet by remember { mutableStateOf(false) }
    
    val webhookConfigs = preferencesManager.getWebhookConfigs()
    val enabledWebhooks = remember(webhookConfigs) { webhookConfigs.filter { it.isEnabled } }
    val localTcpEnabled = preferencesManager.isLocalTcpEnabled()
    var selectedWebhookUrl by remember { mutableStateOf<String?>(null) }

    val timeRangeOptions = listOf(
        context.getString(R.string.manual_sync_default_range) to null,
        context.getString(R.string.manual_sync_past_1_day) to 1,
        context.getString(R.string.manual_sync_past_7_days) to 7,
        context.getString(R.string.manual_sync_past_30_days) to 30,
        context.getString(R.string.manual_sync_custom) to -1
    )
    val localDateSaver = Saver<LocalDate?, String>(
        save = { it?.toString() ?: "" },
        restore = { if (it.isEmpty()) null else LocalDate.parse(it) }
    )
    var showHistorySheet by remember { mutableStateOf(false) }
    var selectedOptionIndex by rememberSaveable { mutableStateOf(0) }
    LaunchedEffect(historyRequest) { if (historyRequest > 0) { selectedOptionIndex = 3; showHistorySheet = true } }
    var startDate by rememberSaveable(stateSaver = localDateSaver) { mutableStateOf(null) }
    var endDate by rememberSaveable(stateSaver = localDateSaver) { mutableStateOf(null) }
    var showStartDatePicker by remember { mutableStateOf(false) }
    var showEndDatePicker by remember { mutableStateOf(false) }
    val historyPermissionLauncher = rememberLauncherForActivityResult(PermissionController.createRequestPermissionResultContract()) { granted ->
        if ("android.permission.health.READ_HEALTH_DATA_HISTORY" in granted) {
            showHistorySheet = false
            showConfirmSheet = true
        } else {
            syncMessage = context.getString(R.string.manual_sync_history_permission_required)
        }
    }

    // ── Confirmation Bottom Sheet ──────────────────────────────────────────────
    if (showConfirmSheet) {
        ModalBottomSheet(
            onDismissRequest = { showConfirmSheet = false }
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(start = 24.dp, end = 24.dp, bottom = 32.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Text(stringResource(R.string.manual_sync_confirm_title), style = MaterialTheme.typography.titleLarge)
                Text(
                    stringResource(R.string.manual_sync_confirm_desc),
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                if (enabledWebhooks.size >= 2) {
                    Column(
                        modifier = Modifier.fillMaxWidth(),
                        verticalArrangement = Arrangement.spacedBy(2.dp)
                    ) {
                        Text(
                            stringResource(R.string.manual_sync_send_to),
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clickable { selectedWebhookUrl = null },
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            RadioButton(
                                selected = selectedWebhookUrl == null,
                                onClick = { selectedWebhookUrl = null }
                            )
                            Text(stringResource(R.string.manual_sync_all_webhooks))
                        }
                        enabledWebhooks.forEach { config ->
                            val host = remember(config.url) {
                                try { java.net.URI(config.url).host?.takeIf { it.isNotEmpty() } ?: config.url }
                                catch (_: Exception) { config.url }
                            }
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clickable { selectedWebhookUrl = config.url },
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                RadioButton(
                                    selected = selectedWebhookUrl == config.url,
                                    onClick = { selectedWebhookUrl = config.url }
                                )
                                Text(host, maxLines = 1, overflow = TextOverflow.Ellipsis)
                            }
                        }
                    }
                }
                Spacer(modifier = Modifier.height(4.dp))
                Button(
                    onClick = {
                        showConfirmSheet = false
                        if (isSyncing) return@Button

                        scope.launch {
                            isSyncing = true
                            syncMessage = null

                            try {
                                val availability = HealthConnectClient.getSdkStatus(context)
                                if (availability != HealthConnectClient.SDK_AVAILABLE) {
                                    syncMessage = when (availability) {
                                        HealthConnectClient.SDK_UNAVAILABLE -> context.getString(R.string.err_hc_not_installed)
                                        HealthConnectClient.SDK_UNAVAILABLE_PROVIDER_UPDATE_REQUIRED -> context.getString(R.string.err_hc_needs_update)
                                        else -> context.getString(R.string.err_hc_not_available)
                                    }
                                    isSyncing = false
                                    return@launch
                                }

                                val healthConnectManager = HealthConnectManager(context)
                                val enabledTypes = preferencesManager.getEnabledDataTypes()
                                val requiredPermissions = HealthConnectManager.getPermissionsForTypes(
                                    enabledTypes,
                                    includeBackgroundPermission = false,
                                    // The manual-sync gate should only require read access to the
                                    // enabled data types. READ_HEALTH_DATA_HISTORY is only needed for
                                    // ranges older than 30 days, and READ_STEPS_CADENCE is optional
                                    // (its aggregate read is already try/catch-guarded). Requiring
                                    // either here left users stuck on "Permissions required for sync"
                                    // with no in-app way to grant them.
                                    includeHistoryPermission = false,
                                    includeStepsCadence = false
                                )
                                if (requiredPermissions.isNotEmpty() && !healthConnectManager.hasPermissions(requiredPermissions)) {
                                    syncMessage = context.getString(R.string.err_permissions_required)
                                    isSyncing = false
                                    return@launch
                                }

                                val syncManager = SyncManager(context)
                                val timeRangeSelection = timeRangeOptions[selectedOptionIndex].second

                                val targetWebhooks: List<WebhookConfig>? = selectedWebhookUrl?.let { url ->
                                    enabledWebhooks.filter { it.url == url }
                                }

                                val result = if (timeRangeSelection == -1) {
                                    val zone = ZoneId.systemDefault()
                                    val startInstant = startDate?.atStartOfDay(zone)?.toInstant()
                                    val endInstant = endDate?.plusDays(1)?.atStartOfDay(zone)?.toInstant()
                                    if (startInstant == null || endInstant == null) {
                                        syncMessage = context.getString(R.string.err_select_both_dates)
                                        isSyncing = false
                                        return@launch
                                    }
                                    if (!startInstant.isBefore(endInstant)) {
                                        syncMessage = context.getString(R.string.err_end_date_before_start)
                                        isSyncing = false
                                        return@launch
                                    }

                                    syncMessage = context.getString(R.string.manual_sync_progress, startDate.toString(), endDate.toString())
                                    syncManager.performSync(start = startInstant, end = endInstant, syncType = "manual", targetWebhooks = targetWebhooks, autoResolveConflict = true)
                                } else {
                                    syncManager.performSync(timeRangeSelection, syncType = "manual", targetWebhooks = targetWebhooks, autoResolveConflict = timeRangeSelection != null)
                                }

                                when {
                                    result.isSuccess -> {
                                        val syncResult = result.getOrThrow()
                                        syncMessage = when (syncResult) {
                                            is SyncResult.NoData -> context.getString(R.string.manual_sync_no_data)
                                            is SyncResult.NoMatchingData -> context.getString(R.string.manual_sync_no_matching_data)
                                            is SyncResult.AlreadyRunning -> context.getString(R.string.manual_sync_in_progress)
                                            is SyncResult.Success -> {
                                                val parts = syncResult.syncCounts.map { (type, count) ->
                                                    "$count ${context.getString(type.nameResId).lowercase()}"
                                                }
                                                if (parts.isEmpty()) context.getString(R.string.manual_sync_success_empty)
                                                else context.getString(R.string.manual_sync_success_items, parts.joinToString(", "))
                                            }
                                        }
                                        onSyncCompleted()
                                    }
                                    result.isFailure -> {
                                        syncMessage = context.getString(R.string.manual_sync_failed, result.exceptionOrNull()?.message ?: "Unknown error")
                                    }
                                }
                            } catch (e: CancellationException) {
                                throw e
                            } catch (e: Exception) {
                                syncMessage = context.getString(R.string.manual_sync_failed, e.message)
                            } finally {
                                isSyncing = false
                            }
                        }
                    },
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Text(stringResource(R.string.manual_sync_btn))
                }
                OutlinedButton(
                    onClick = { showConfirmSheet = false },
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Text(stringResource(R.string.action_cancel))
                }
            }
        }
    }

    Card {
        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Button(
                onClick = { selectedOptionIndex = 0; showConfirmSheet = true },
                enabled = !isSyncing && (webhookConfigs.isNotEmpty() || localTcpEnabled),
                modifier = Modifier.fillMaxWidth()
            ) {
                if (isSyncing) {
                    CircularProgressIndicator(Modifier.size(20.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary)
                    Spacer(Modifier.width(8.dp))
                }
                Text(stringResource(if (isSyncing) R.string.manual_sync_syncing else R.string.manual_sync_btn))
            }
            OutlinedButton(onClick = { selectedOptionIndex = 3; showHistorySheet = true }, enabled = !isSyncing, modifier = Modifier.fillMaxWidth()) {
                Text(stringResource(R.string.manual_sync_history_button))
            }
            syncMessage?.let { Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant) }
        }
    }
    if (showHistorySheet) {
        ModalBottomSheet(onDismissRequest = { showHistorySheet = false }) {
            Column(Modifier.fillMaxWidth().padding(start = 24.dp, end = 24.dp, bottom = 32.dp).heightIn(max = 600.dp).verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(stringResource(R.string.manual_sync_history_button), style = MaterialTheme.typography.titleLarge)
                timeRangeOptions.forEachIndexed { index, option ->
                    if (index > 0) {
                        Row(Modifier.fillMaxWidth().clickable { selectedOptionIndex = index }, verticalAlignment = Alignment.CenterVertically) {
                            RadioButton(selected = selectedOptionIndex == index, onClick = { selectedOptionIndex = index })
                            Text(option.first)
                        }
                    }
                }
            if (selectedOptionIndex == timeRangeOptions.indexOfFirst { it.second == -1 }) {
                val today = LocalDate.now()
                val todayMillis = remember(today) {
                    today.atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli()
                }

                if (showStartDatePicker) {
                    val startPickerState = rememberDatePickerState(
                        initialSelectedDateMillis = startDate
                            ?.atStartOfDay(ZoneOffset.UTC)
                            ?.toInstant()
                            ?.toEpochMilli()
                            ?: todayMillis,
                        selectableDates = object : SelectableDates {
                            override fun isSelectableDate(utcTimeMillis: Long): Boolean {
                                return utcTimeMillis <= todayMillis
                            }

                            override fun isSelectableYear(year: Int): Boolean {
                                return year <= today.year
                            }
                        }
                    )
                    DatePickerDialog(
                        onDismissRequest = { showStartDatePicker = false },
                        confirmButton = {
                            TextButton(onClick = {
                                val selectedMillis = startPickerState.selectedDateMillis
                                startDate = selectedMillis?.let {
                                    Instant.ofEpochMilli(it)
                                        .atZone(ZoneOffset.UTC)
                                        .toLocalDate()
                                }
                                showStartDatePicker = false
                            }) {
                                Text(stringResource(R.string.action_ok))
                            }
                        },
                        dismissButton = {
                            TextButton(onClick = { showStartDatePicker = false }) {
                                Text(stringResource(R.string.action_cancel))
                            }
                        }
                    ) {
                        DatePicker(state = startPickerState)
                    }
                }

                if (showEndDatePicker) {
                    val currentEndDate = endDate
                    val minEndDateMillis = startDate
                        ?.atStartOfDay(ZoneOffset.UTC)
                        ?.toInstant()
                        ?.toEpochMilli()
                    val minEndYear = startDate?.year
                    val endPickerState = rememberDatePickerState(
                        initialSelectedDateMillis = when {
                            currentEndDate != null && minEndDateMillis != null -> {
                                val endDateMillis = currentEndDate
                                    .atStartOfDay(ZoneOffset.UTC)
                                    .toInstant()
                                    .toEpochMilli()
                                maxOf(endDateMillis, minEndDateMillis)
                            }
                            currentEndDate != null -> currentEndDate
                                .atStartOfDay(ZoneOffset.UTC)
                                .toInstant()
                                .toEpochMilli()
                            minEndDateMillis != null -> minEndDateMillis
                            else -> todayMillis
                        },
                        selectableDates = object : SelectableDates {
                            override fun isSelectableDate(utcTimeMillis: Long): Boolean {
                                return utcTimeMillis <= todayMillis &&
                                    (minEndDateMillis == null || utcTimeMillis >= minEndDateMillis)
                            }

                            override fun isSelectableYear(year: Int): Boolean {
                                return year <= today.year && (minEndYear == null || year >= minEndYear)
                            }
                        }
                    )
                    DatePickerDialog(
                        onDismissRequest = { showEndDatePicker = false },
                        confirmButton = {
                            TextButton(onClick = {
                                val selectedMillis = endPickerState.selectedDateMillis
                                endDate = selectedMillis?.let {
                                    Instant.ofEpochMilli(it)
                                        .atZone(ZoneOffset.UTC)
                                        .toLocalDate()
                                }
                                showEndDatePicker = false
                            }) {
                                Text(stringResource(R.string.action_ok))
                            }
                        },
                        dismissButton = {
                            TextButton(onClick = { showEndDatePicker = false }) {
                                Text(stringResource(R.string.action_cancel))
                            }
                        }
                    ) {
                        DatePicker(state = endPickerState)
                    }
                }

                Column(modifier = Modifier.fillMaxWidth()) {
                    OutlinedButton(
                        onClick = { showStartDatePicker = true },
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text(startDate?.let { context.getString(R.string.manual_sync_start_label, it.toString()) } ?: stringResource(R.string.manual_sync_start_date))
                    }

                    Spacer(modifier = Modifier.height(8.dp))

                    OutlinedButton(
                        onClick = { showEndDatePicker = true },
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text(endDate?.let { context.getString(R.string.manual_sync_end_label, it.toString()) } ?: stringResource(R.string.manual_sync_end_date))
                    }

                    Spacer(modifier = Modifier.height(8.dp))

                    val startDateParsed = startDate
                    val endDateParsed = endDate

                    if (startDateParsed != null && endDateParsed != null) {
                        when {
                            endDateParsed < startDateParsed -> {
                                Text(
                                    stringResource(R.string.err_end_date_before_start),
                                    color = MaterialTheme.colorScheme.error,
                                    style = MaterialTheme.typography.bodySmall
                                )
                            }
                            endDateParsed > today -> {
                                Text(
                                    stringResource(R.string.err_end_date_future),
                                    color = MaterialTheme.colorScheme.error,
                                    style = MaterialTheme.typography.bodySmall
                                )
                            }
                        }
                    }
                }
            }

            val isCustomRange = selectedOptionIndex == timeRangeOptions.indexOfFirst { it.second == -1 }
            val selectedStart = startDate
            val selectedEnd = endDate
            val today = LocalDate.now()
            val customRangeValid = !isCustomRange || (
                selectedStart != null &&
                    selectedEnd != null &&
                    !selectedEnd.isBefore(selectedStart) &&
                    !selectedEnd.isAfter(today)
                )

            Button(onClick = {
                val needsHistory = isCustomRange && selectedStart != null && selectedStart.isBefore(today.minusDays(30))
                if (needsHistory) {
                    scope.launch {
                        val permissions = HealthConnectManager.getPermissionsForTypes(preferencesManager.getEnabledDataTypes(), includeBackgroundPermission = false, includeHistoryPermission = true, includeStepsCadence = false)
                        if (HealthConnectManager(context).hasPermissions(permissions)) {
                            showHistorySheet = false
                            showConfirmSheet = true
                        } else {
                            historyPermissionLauncher.launch(permissions)
                        }
                    }
                } else {
                    showHistorySheet = false
                    showConfirmSheet = true
                }
            }, enabled = !isSyncing && (webhookConfigs.isNotEmpty() || localTcpEnabled) && customRangeValid, modifier = Modifier.fillMaxWidth()) {
                Text(stringResource(R.string.manual_sync_btn))
            }
            syncMessage?.let { Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall) }
            }
        }
    }
}
