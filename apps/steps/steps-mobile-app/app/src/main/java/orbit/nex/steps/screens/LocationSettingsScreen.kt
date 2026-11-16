package orbit.nex.steps.screens

import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.LocationOff
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.NotificationsOff
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SegmentedButton
import androidx.compose.material3.SegmentedButtonDefaults
import androidx.compose.material3.SingleChoiceSegmentedButtonRow
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.R
import orbit.nex.steps.location.MatchingState
import orbit.nex.steps.location.PointManagementRepository
import orbit.nex.steps.location.LocationAccuracy
import orbit.nex.steps.location.LocationEventStore
import orbit.nex.steps.location.LocationPermissions
import orbit.nex.steps.location.LocationPermissionState
import orbit.nex.steps.location.LocationTrackingManager
import orbit.nex.steps.location.TrackingState

/**
 * Location tracking settings, opened from the settings tab and from the map.
 *
 * Holds the recording controls, permission recovery, sampling settings and the
 * privacy explanation, so the Tracking tab itself is only the map. The screen
 * never starts tracking on its own: every state change comes from a button tap
 * and is mirrored in the ongoing notification.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun LocationSettingsScreen(onBack: () -> Unit) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val manager = remember { LocationTrackingManager(context) }
    val pointRepository = remember { PointManagementRepository(prefs) }
    val scope = androidx.compose.runtime.rememberCoroutineScope()
    val matchingConfig = remember { prefs.getWebhookConfigs().firstOrNull { it.isEnabled } }

    var matching by remember { mutableStateOf(MatchingState()) }
    var matchingBusy by remember { mutableStateOf(false) }
    var matchingMessage by remember { mutableStateOf<String?>(null) }
    var state by remember { mutableStateOf(manager.state()) }
    var permissionState by remember { mutableStateOf(LocationPermissions.state(context)) }
    var notificationsEnabled by remember { mutableStateOf(LocationPermissions.areNotificationsEnabled(context)) }
    var pending by remember { mutableIntStateOf(LocationTrackingManager.pendingCount(context)) }
    var counts by remember { mutableStateOf<Map<String, Int>>(emptyMap()) }
    var lastUploadAt by remember { mutableStateOf<Long?>(prefs.getLocationUploadOkAt()) }
    var lastError by remember { mutableStateOf<String?>(prefs.getLocationUploadError()) }
    var permissionLost by remember { mutableStateOf(prefs.isLocationPermissionLost()) }
    var accuracy by remember { mutableStateOf(prefs.getLocationAccuracy()) }
    var actionMessage by remember { mutableStateOf<String?>(null) }

    fun refresh() {
        manager.reconcile()
        state = manager.state()
        permissionState = LocationPermissions.state(context)
        notificationsEnabled = LocationPermissions.areNotificationsEnabled(context)
        pending = LocationTrackingManager.pendingCount(context)
        counts = LocationEventStore(context).counts()
        lastUploadAt = prefs.getLocationUploadOkAt()
        lastError = prefs.getLocationUploadError()
        permissionLost = prefs.isLocationPermissionLost()
        accuracy = prefs.getLocationAccuracy()
    }

    LaunchedEffect(matchingConfig?.url) {
        val active = matchingConfig ?: return@LaunchedEffect
        matching = pointRepository.matching(active)
    }

    // Poll so the screen reflects service, permission and upload changes live.
    LaunchedEffect(Unit) {
        while (true) {
            refresh()
            delay(2_000)
        }
    }

    val permissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) {
        refresh()
    }

    fun startTracking() {
        actionMessage = null
        if (!manager.start()) {
            actionMessage = context.getString(R.string.tracking_status_permission)
        }
        refresh()
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(stringResource(R.string.tracking_settings_title)) },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(
                            Icons.AutoMirrored.Filled.ArrowBack,
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
            LazyColumn(
                Modifier.fillMaxSize().padding(padding).padding(horizontal = 10.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
        item { Spacer(Modifier.size(6.dp)) }

        // ── Status ────────────────────────────────────────────────────────────
        item {
            Card(
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                modifier = Modifier.fillMaxWidth(),
            ) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            if (state == TrackingState.RUNNING) Icons.Filled.LocationOn else Icons.Filled.LocationOff,
                            contentDescription = null,
                            tint = if (state == TrackingState.RUNNING) {
                                MaterialTheme.colorScheme.primary
                            } else {
                                MaterialTheme.colorScheme.onSurfaceVariant
                            },
                        )
                        Spacer(Modifier.size(8.dp))
                        Text(
                            stringResource(R.string.tracking_title),
                            style = MaterialTheme.typography.titleMedium,
                        )
                    }
                    Text(
                        stringResource(statusRes(state)),
                        style = MaterialTheme.typography.bodyLarge,
                        fontWeight = FontWeight.Medium,
                    )
                    Text(
                        stringResource(R.string.tracking_desc),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    if (state == TrackingState.NOT_CONNECTED) {
                        Text(
                            stringResource(R.string.tracking_requires_connection),
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.error,
                        )
                    }
                    HorizontalDivider()
                    Text(
                        stringResource(R.string.tracking_pending, pending),
                        style = MaterialTheme.typography.bodyMedium,
                    )
                    Text(
                        stringResource(
                            R.string.tracking_diagnostics,
                            counts[LocationEventStore.STATE_PENDING] ?: 0,
                            counts[LocationEventStore.STATE_REJECTED] ?: 0,
                            counts[LocationEventStore.STATE_FAILED] ?: 0,
                        ),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    when {
                        asTimeText(lastUploadAt) != null -> Text(
                            stringResource(R.string.tracking_last_upload_ok, asTimeText(lastUploadAt).orEmpty()),
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        else -> Text(
                            stringResource(R.string.tracking_last_upload_never),
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                    lastError?.let { error ->
                        Text(
                            stringResource(R.string.tracking_last_upload_failed, error),
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.error,
                        )
                    }
                    actionMessage?.let { message ->
                        Text(
                            message,
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.error,
                        )
                    }

                    // ── Explicit controls ────────────────────────────────────
                    when (state) {
                        TrackingState.RUNNING -> Row(
                            Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                        ) {
                            OutlinedButton(
                                onClick = { manager.pause(); refresh() },
                                modifier = Modifier.weight(1f),
                            ) { Text(stringResource(R.string.tracking_pause)) }
                            OutlinedButton(
                                onClick = {
                                    manager.stop()
                                    actionMessage = null
                                    refresh()
                                },
                                modifier = Modifier.weight(1f),
                            ) { Text(stringResource(R.string.tracking_stop)) }
                        }
                        TrackingState.PAUSED -> Row(
                            Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                        ) {
                            Button(
                                onClick = { manager.resume(); refresh() },
                                modifier = Modifier.weight(1f),
                            ) { Text(stringResource(R.string.tracking_resume)) }
                            OutlinedButton(
                                onClick = {
                                    manager.stop()
                                    actionMessage = null
                                    refresh()
                                },
                                modifier = Modifier.weight(1f),
                            ) { Text(stringResource(R.string.tracking_stop)) }
                        }
                        else -> Button(
                            onClick = {
                                if (permissionState == LocationPermissionState.DENIED) {
                                    permissionLauncher.launch(LocationPermissions.foregroundPermissions())
                                } else {
                                    startTracking()
                                }
                            },
                            enabled = state != TrackingState.NOT_CONNECTED,
                            modifier = Modifier.fillMaxWidth(),
                        ) {
                            Text(
                                stringResource(
                                    if (permissionState == LocationPermissionState.DENIED) {
                                        R.string.tracking_perm_grant
                                    } else {
                                        R.string.tracking_start
                                    }
                                )
                            )
                        }
                    }
                    if (permissionLost && state != TrackingState.RUNNING) {
                        Text(
                            stringResource(R.string.tracking_autostart_hint),
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
            }
        }

        // ── Permissions ───────────────────────────────────────────────────────
        item {
            Card(
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                modifier = Modifier.fillMaxWidth(),
            ) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(
                        stringResource(R.string.perms_title),
                        style = MaterialTheme.typography.titleMedium,
                    )
                    PermissionRow(
                        granted = permissionState != LocationPermissionState.DENIED,
                        title = stringResource(R.string.tracking_perm_foreground_title),
                        description = stringResource(R.string.tracking_perm_foreground_desc),
                    )
                    PermissionRow(
                        granted = permissionState == LocationPermissionState.GRANTED,
                        title = stringResource(R.string.tracking_perm_background_title),
                        description = stringResource(R.string.tracking_perm_background_desc),
                    )
                    if (permissionState == LocationPermissionState.DENIED) {
                        Button(
                            onClick = { permissionLauncher.launch(LocationPermissions.foregroundPermissions()) },
                            modifier = Modifier.fillMaxWidth(),
                        ) { Text(stringResource(R.string.tracking_perm_grant)) }
                    }
                    if (permissionState == LocationPermissionState.FOREGROUND_ONLY) {
                        OutlinedButton(
                            onClick = { LocationPermissions.openBackgroundLocationSettings(context) },
                            modifier = Modifier.fillMaxWidth(),
                        ) { Text(stringResource(R.string.tracking_perm_settings)) }
                    }
                    if (!notificationsEnabled) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(
                                Icons.Filled.NotificationsOff,
                                contentDescription = null,
                                tint = MaterialTheme.colorScheme.error,
                                modifier = Modifier.size(18.dp),
                            )
                            Spacer(Modifier.size(8.dp))
                            Text(
                                stringResource(R.string.tracking_perm_notifications),
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.error,
                            )
                        }
                        OutlinedButton(
                            onClick = { LocationPermissions.openAppSettings(context) },
                            modifier = Modifier.fillMaxWidth(),
                        ) { Text(stringResource(R.string.tracking_perm_settings)) }
                    }
                }
            }
        }

        // ── Sampling settings ─────────────────────────────────────────────────
        item {
            Card(
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                modifier = Modifier.fillMaxWidth(),
            ) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(
                        stringResource(R.string.tracking_accuracy_title),
                        style = MaterialTheme.typography.titleMedium,
                    )
                    SingleChoiceSegmentedButtonRow(Modifier.fillMaxWidth()) {
                        LocationAccuracy.entries.forEachIndexed { index, option ->
                            SegmentedButton(
                                selected = accuracy == option,
                                onClick = {
                                    accuracy = option
                                    prefs.setLocationAccuracy(option)
                                },
                                shape = SegmentedButtonDefaults.itemShape(index, LocationAccuracy.entries.size),
                            ) {
                                Text(stringResource(accuracyRes(option)), maxLines = 1)
                            }
                        }
                    }
                }
            }
        }

        // ── Snap to road / map matching ───────────────────────────────────────
        item {
            Card(
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                modifier = Modifier.fillMaxWidth(),
            ) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(stringResource(R.string.matching_title), style = MaterialTheme.typography.titleMedium)
                    Text(
                        stringResource(R.string.matching_hint),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    val modes = listOf("disabled" to R.string.matching_mode_disabled, "conservative" to R.string.matching_mode_conservative, "review_required" to R.string.matching_mode_review)
                    modes.forEach { (value, label) ->
                        Row(
                            Modifier.fillMaxWidth().clickable {
                                val active = matchingConfig ?: return@clickable
                                matchingBusy = true
                                scope.launch {
                                    pointRepository.setMatchingMode(active, value)
                                        .onSuccess { matching = pointRepository.matching(active); matchingMessage = context.getString(R.string.matching_saved) }
                                        .onFailure { matchingMessage = it.message }
                                    matchingBusy = false
                                }
                            }.padding(vertical = 7.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(10.dp),
                        ) {
                            androidx.compose.material3.RadioButton(selected = matching.mode == value, onClick = null)
                            Column(Modifier.weight(1f)) {
                                Text(stringResource(label), style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.Medium)
                                Text(
                                    stringResource(matchingDescriptionRes(value)),
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                        }
                    }
                    Text(
                        stringResource(R.string.matching_summary, matching.matched, matching.abstained, matching.stale),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Button(
                        enabled = !matchingBusy && matchingConfig != null,
                        onClick = {
                            val active = matchingConfig ?: return@Button
                            matchingBusy = true
                            matchingMessage = context.getString(R.string.matching_queued)
                            scope.launch {
                                pointRepository.runMatching(active)
                                    .onFailure { matchingMessage = it.message }
                                delay(4_000)
                                matching = pointRepository.matching(active)
                                matchingBusy = false
                            }
                        },
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        if (matchingBusy) CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp)
                        else Text(stringResource(R.string.matching_run))
                    }
                    matchingMessage?.let { Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.primary) }
                }
            }
        }

        // ── Privacy copy ──────────────────────────────────────────────────────
        item {
            Card(
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text(
                    stringResource(R.string.tracking_privacy),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(16.dp),
                )
            }
        }
        item { Spacer(Modifier.size(12.dp)) }
            }
        }
    }
}

@Composable
private fun PermissionRow(granted: Boolean, title: String, description: String) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        Icon(
            if (granted) Icons.Filled.Check else Icons.Filled.Close,
            contentDescription = null,
            tint = if (granted) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.size(20.dp),
        )
        Column {
            Text(title, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.Medium)
            Text(
                description,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

private fun statusRes(state: TrackingState): Int = when (state) {
    TrackingState.RUNNING -> R.string.tracking_status_running
    TrackingState.PAUSED -> R.string.tracking_status_paused
    TrackingState.PERMISSION_MISSING -> R.string.tracking_status_permission
    TrackingState.NOT_CONNECTED -> R.string.tracking_status_no_connection
    TrackingState.OFF -> R.string.tracking_status_off
}

private fun matchingDescriptionRes(mode: String): Int = when (mode) {
    "disabled" -> R.string.matching_desc_disabled
    "review_required" -> R.string.matching_desc_review
    else -> R.string.matching_desc_conservative
}

private fun accuracyRes(accuracy: LocationAccuracy): Int = when (accuracy) {
    LocationAccuracy.HIGH -> R.string.tracking_accuracy_high
    LocationAccuracy.BALANCED -> R.string.tracking_accuracy_balanced
    LocationAccuracy.LOW -> R.string.tracking_accuracy_low
}

private fun asTimeText(epochMs: Long?): String? =
    epochMs?.let { orbit.nex.steps.location.formatLocationUploadTime(it) }

