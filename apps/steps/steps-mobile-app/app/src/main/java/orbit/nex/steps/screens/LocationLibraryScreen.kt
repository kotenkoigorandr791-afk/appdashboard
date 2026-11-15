package orbit.nex.steps.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.R
import orbit.nex.steps.location.LibraryAction
import orbit.nex.steps.location.LibraryEntry
import orbit.nex.steps.location.LibrarySnapshot
import orbit.nex.steps.location.LibraryTab
import orbit.nex.steps.location.LocationLibraryRepository
import kotlinx.coroutines.delay

private data class PendingLibraryAction(val entry: LibraryEntry, val action: LibraryAction)

/**
 * All location entities that previously existed only on the web.
 *
 * The screen is intentionally a thin, permission-aware view over the existing
 * mobile token endpoints: it does not compute trips, visits or achievements
 * locally and it never shows raw coordinates.
 */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun LocationLibraryScreen(modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val repository = remember { LocationLibraryRepository(prefs) }
    val scope = rememberCoroutineScope()
    val config = remember { prefs.getWebhookConfigs().firstOrNull { it.isEnabled } }
    var tabName by rememberSaveable { mutableStateOf(LibraryTab.PLACES.name) }
    val tab = LibraryTab.entries.firstOrNull { it.name == tabName } ?: LibraryTab.PLACES
    var snapshot by remember { mutableStateOf(LibrarySnapshot()) }
    var loading by remember { mutableStateOf(false) }
    var refreshKey by remember { mutableIntStateOf(0) }
    var pending by remember { mutableStateOf<PendingLibraryAction?>(null) }
    var actionBusy by remember { mutableStateOf(false) }
    var actionError by remember { mutableStateOf<String?>(null) }
    var notice by remember { mutableStateOf<String?>(null) }
    var searchQuery by rememberSaveable { mutableStateOf("") }
    val updatedLabel = stringResource(R.string.library_updated)

    LaunchedEffect(tab, refreshKey, config?.url, searchQuery) {
        val activeConfig = config ?: return@LaunchedEffect
        if (tab == LibraryTab.SEARCH) delay(350)
        loading = true
        actionError = null
        snapshot = repository.load(activeConfig, tab, if (tab == LibraryTab.SEARCH) searchQuery else "")
        loading = false
    }

    Column(modifier.fillMaxSize()) {
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(Modifier.weight(1f)) {
                Text(stringResource(R.string.library_title), style = MaterialTheme.typography.titleLarge)
                Text(
                    stringResource(R.string.library_subtitle),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            IconButton(onClick = { refreshKey++ }, enabled = !loading) {
                Icon(Icons.Filled.Refresh, contentDescription = stringResource(R.string.library_refresh))
            }
        }

        FlowRow(
            Modifier.fillMaxWidth().padding(horizontal = 16.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            verticalArrangement = Arrangement.spacedBy(6.dp),
        ) {
            LibraryTab.entries.forEach { candidate ->
                FilterChip(
                    selected = tab == candidate,
                    onClick = {
                        tabName = candidate.name
                        notice = null
                    },
                    label = { Text(stringResource(candidate.titleRes())) },
                )
            }
        }

        if (tab == LibraryTab.SEARCH) {
            OutlinedTextField(
                value = searchQuery,
                onValueChange = { searchQuery = it },
                singleLine = true,
                label = { Text(stringResource(R.string.library_search_label)) },
                modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
            )
        }

        Spacer(Modifier.height(8.dp))

        when {
            config == null -> LibraryMessage(stringResource(R.string.library_no_connection))
            loading && snapshot.entries.isEmpty() -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator()
            }
            snapshot.error != null -> LibraryMessage(stringResource(R.string.library_error, snapshot.error.orEmpty()), isError = true)
            snapshot.entries.isEmpty() -> LibraryMessage(
                if (!snapshot.enabled) stringResource(R.string.library_disabled) else stringResource(R.string.library_empty)
            )
            else -> LazyColumn(
                Modifier.fillMaxSize().padding(horizontal = 12.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                if (snapshot.summary.isNotBlank()) {
                    item { Text(snapshot.summary, style = MaterialTheme.typography.labelLarge) }
                }
                if (notice != null) {
                    item {
                        Text(
                            notice.orEmpty(),
                            color = MaterialTheme.colorScheme.primary,
                            style = MaterialTheme.typography.bodySmall,
                        )
                    }
                }
                if (actionError != null) {
                    item {
                        Text(
                            actionError.orEmpty(),
                            color = MaterialTheme.colorScheme.error,
                            style = MaterialTheme.typography.bodySmall,
                        )
                    }
                }
                items(snapshot.entries, key = { it.id }) { entry ->
                    LibraryCard(
                        entry = entry,
                        enabled = !actionBusy,
                        onAction = { action -> pending = PendingLibraryAction(entry, action) },
                    )
                }
                item { Spacer(Modifier.height(10.dp)) }
            }
        }
    }

    pending?.let { request ->
        AlertDialog(
            onDismissRequest = { if (!actionBusy) pending = null },
            title = { Text(stringResource(R.string.library_confirm_title)) },
            text = { Text(confirmText(request.action)) },
            confirmButton = {
                TextButton(
                    enabled = !actionBusy,
                    onClick = {
                        val activeConfig = config ?: return@TextButton
                        if (request.action == LibraryAction.APPLY_VIEW) {
                            searchQuery = request.entry.query
                            tabName = LibraryTab.SEARCH.name
                            pending = null
                            return@TextButton
                        }
                        actionBusy = true
                        actionError = null
                        scope.launch {
                            repository.apply(activeConfig, request.entry, request.action)
                                .onSuccess {
                                    notice = updatedLabel
                                    refreshKey++
                                }
                                .onFailure { actionError = it.message ?: context.getString(R.string.library_error_generic) }
                            actionBusy = false
                            pending = null
                        }
                    },
                ) { Text(stringResource(R.string.library_confirm)) }
            },
            dismissButton = {
                TextButton(enabled = !actionBusy, onClick = { pending = null }) {
                    Text(stringResource(R.string.library_cancel))
                }
            },
        )
    }
}

@Composable
private fun LibraryMessage(message: String, isError: Boolean = false) {
    Box(Modifier.fillMaxSize().padding(24.dp), contentAlignment = Alignment.Center) {
        Text(
            message,
            color = if (isError) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant,
            style = MaterialTheme.typography.bodyMedium,
        )
    }
}

@Composable
private fun LibraryCard(entry: LibraryEntry, enabled: Boolean, onAction: (LibraryAction) -> Unit) {
    Card(
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
        shape = RoundedCornerShape(16.dp),
        modifier = Modifier.fillMaxWidth(),
    ) {
        Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(5.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    entry.title,
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.SemiBold,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f),
                )
                if (entry.status.isNotBlank()) {
                    Box(
                        Modifier
                            .background(
                                if (entry.active == true) MaterialTheme.colorScheme.primary.copy(alpha = 0.16f)
                                else MaterialTheme.colorScheme.surface.copy(alpha = 0.7f),
                                RoundedCornerShape(20.dp),
                            )
                            .padding(horizontal = 8.dp, vertical = 3.dp),
                    ) {
                        Text(
                            statusLabel(entry.status),
                            style = MaterialTheme.typography.labelSmall,
                            color = if (entry.active == true) MaterialTheme.colorScheme.primary
                            else MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
            }
            if (entry.subtitle.isNotBlank()) {
                Text(entry.subtitle, style = MaterialTheme.typography.bodySmall)
            }
            if (entry.meta.isNotBlank()) {
                Text(
                    entry.meta,
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            if (entry.actions.isNotEmpty()) {
                Row(
                    Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                ) {
                    entry.actions.forEach { action ->
                        TextButton(onClick = { onAction(action) }, enabled = enabled) {
                            Text(stringResource(action.labelRes()))
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun statusLabel(status: String): String = when (status.lowercase()) {
    "active", "ready" -> stringResource(R.string.library_status_active)
    "paused" -> stringResource(R.string.library_status_paused)
    "revoked" -> stringResource(R.string.library_status_revoked)
    "deleted", "archived" -> stringResource(R.string.library_status_archived)
    "confirmed" -> stringResource(R.string.library_status_active)
    "rejected" -> stringResource(R.string.library_status_archived)
    else -> status.replaceFirstChar { it.uppercase() }
}

private fun LibraryTab.titleRes(): Int = when (this) {
    LibraryTab.PLACES -> R.string.library_tab_places
    LibraryTab.TRIPS -> R.string.library_tab_trips
    LibraryTab.VISITS -> R.string.library_tab_visits
    LibraryTab.ACHIEVEMENTS -> R.string.library_tab_achievements
    LibraryTab.GEOFENCES -> R.string.library_tab_geofences
    LibraryTab.DEVICES -> R.string.library_tab_devices
    LibraryTab.QUALITY -> R.string.library_tab_quality
    LibraryTab.SEARCH -> R.string.library_tab_search
    LibraryTab.SAVED -> R.string.library_tab_saved
    LibraryTab.ANALYTICS -> R.string.library_tab_analytics
}

private fun LibraryAction.labelRes(): Int = when (this) {
    LibraryAction.DELETE -> R.string.library_delete
    LibraryAction.ARCHIVE -> R.string.library_archive
    LibraryAction.CONFIRM -> R.string.library_confirm
    LibraryAction.REJECT -> R.string.library_reject
    LibraryAction.PAUSE -> R.string.library_pause
    LibraryAction.RESUME -> R.string.library_resume
    LibraryAction.REVOKE -> R.string.library_revoke
    LibraryAction.DISABLE -> R.string.library_disable
    LibraryAction.EXCLUDE -> R.string.library_exclude
    LibraryAction.UNDO -> R.string.library_undo
    LibraryAction.PIN_VIEW -> R.string.library_pin_view
    LibraryAction.DELETE_VIEW -> R.string.library_delete_view
    LibraryAction.APPLY_VIEW -> R.string.library_apply_view
}

@Composable
private fun confirmText(action: LibraryAction): String = when (action) {
    LibraryAction.DELETE -> stringResource(R.string.library_confirm_delete_place)
    LibraryAction.ARCHIVE -> stringResource(R.string.library_confirm_archive_trip)
    LibraryAction.CONFIRM, LibraryAction.REJECT -> stringResource(R.string.library_confirm_visit)
    LibraryAction.PAUSE, LibraryAction.RESUME -> stringResource(R.string.library_confirm_device)
    LibraryAction.REVOKE -> stringResource(R.string.library_confirm_revoke)
    LibraryAction.DISABLE -> stringResource(R.string.library_confirm_disable_geofence)
    LibraryAction.EXCLUDE -> stringResource(R.string.library_confirm_exclude)
    LibraryAction.UNDO -> stringResource(R.string.library_confirm_undo)
    LibraryAction.PIN_VIEW -> stringResource(R.string.library_confirm_pin_view)
    LibraryAction.DELETE_VIEW -> stringResource(R.string.library_confirm_delete_view)
    LibraryAction.APPLY_VIEW -> stringResource(R.string.library_confirm_apply_view)
}
