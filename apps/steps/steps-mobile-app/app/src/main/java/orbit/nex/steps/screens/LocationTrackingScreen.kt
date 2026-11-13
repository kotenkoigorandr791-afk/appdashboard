package orbit.nex.steps.screens

import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
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
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.LocationOff
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.NotificationsOff
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.SegmentedButton
import androidx.compose.material3.SegmentedButtonDefaults
import androidx.compose.material3.SingleChoiceSegmentedButtonRow
import androidx.compose.material3.Text
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
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.R
import orbit.nex.steps.location.LocationAccuracy
import orbit.nex.steps.location.LocationPermissions
import orbit.nex.steps.location.LocationPermissionState
import orbit.nex.steps.location.LocationTrackingManager
import orbit.nex.steps.location.TrackingState

private val INTERVAL_OPTIONS = listOf(1, 5, 10, 15, 30, 60)

/**
 * Location tracking tab: status, explicit start/pause/resume/stop, permission
 * recovery, sampling settings and the privacy explanation.
 *
 * The screen never starts tracking on its own. Every state change comes from a
 * button tap, and the same state is mirrored in the ongoing notification.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun LocationTrackingScreen(modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val manager = remember { LocationTrackingManager(context) }

    var state by remember { mutableStateOf(manager.state()) }
    var permissionState by remember { mutableStateOf(LocationPermissions.state(context)) }
    var notificationsEnabled by remember { mutableStateOf(LocationPermissions.areNotificationsEnabled(context)) }
    var pending by remember { mutableIntStateOf(LocationTrackingManager.pendingCount(context)) }
    var lastUploadAt by remember { mutableStateOf<Long?>(prefs.getLocationUploadOkAt()) }
    var lastError by remember { mutableStateOf<String?>(prefs.getLocationUploadError()) }
    var permissionLost by remember { mutableStateOf(prefs.isLocationPermissionLost()) }
    var accuracy by remember { mutableStateOf(prefs.getLocationAccuracy()) }
    var interval by remember { mutableIntStateOf(prefs.getLocationIntervalMinutes()) }
    var actionMessage by remember { mutableStateOf<String?>(null) }

    fun refresh() {
        manager.reconcile()
        state = manager.state()
        permissionState = LocationPermissions.state(context)
        notificationsEnabled = LocationPermissions.areNotificationsEnabled(context)
        pending = LocationTrackingManager.pendingCount(context)
        lastUploadAt = prefs.getLocationUploadOkAt()
        lastError = prefs.getLocationUploadError()
        permissionLost = prefs.isLocationPermissionLost()
        accuracy = prefs.getLocationAccuracy()
        interval = prefs.getLocationIntervalMinutes()
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

    LazyColumn(
        modifier.fillMaxSize().padding(horizontal = 10.dp),
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
                    HorizontalDivider()
                    Text(
                        stringResource(R.string.tracking_interval_title),
                        style = MaterialTheme.typography.titleMedium,
                    )
                    // A plain wrapped row of interval chips keeps every option visible.
                    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        INTERVAL_OPTIONS.chunked(3).forEach { row ->
                            Row(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth()) {
                                row.forEach { minutes ->
                                    if (minutes == interval) {
                                        Button(
                                            onClick = {},
                                            modifier = Modifier.weight(1f),
                                        ) { Text(stringResource(R.string.tracking_interval_value, minutes)) }
                                    } else {
                                        OutlinedButton(
                                            onClick = {
                                                interval = minutes
                                                prefs.setLocationIntervalMinutes(minutes)
                                            },
                                            modifier = Modifier.weight(1f),
                                        ) { Text(stringResource(R.string.tracking_interval_value, minutes)) }
                                    }
                                }
                            }
                        }
                    }
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

private fun accuracyRes(accuracy: LocationAccuracy): Int = when (accuracy) {
    LocationAccuracy.HIGH -> R.string.tracking_accuracy_high
    LocationAccuracy.BALANCED -> R.string.tracking_accuracy_balanced
    LocationAccuracy.LOW -> R.string.tracking_accuracy_low
}

private fun asTimeText(epochMs: Long?): String? =
    epochMs?.let { orbit.nex.steps.location.formatLocationUploadTime(it) }

