package orbit.nex.steps.screens

import orbit.nex.steps.ui.theme.Spacing
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Place
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.FilterChip
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.asImageBitmap
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
import orbit.nex.steps.location.PlacePhotoCache
import orbit.nex.steps.location.VisitDetail
import kotlinx.coroutines.delay
import androidx.compose.foundation.Image

private data class PendingLibraryAction(val entry: LibraryEntry, val action: LibraryAction)

/**
 * Map-first location panels. This used to be a fifth bottom-nav destination;
 * it is now a sheet opened from the map, so Places, Trips, Visits, Geofences,
 * Devices, Quality, Search and Saved views stay close to the geography they
 * describe. The sheet is a thin view over existing owner endpoints and never
 * shows raw coordinates.
 */
@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
fun LocationPanelsSheet(onDismiss: () -> Unit, onTripSelected: (java.time.LocalDate, List<orbit.nex.steps.location.GeoLatLon>) -> Unit, modifier: Modifier = Modifier) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    ModalBottomSheet(onDismissRequest = onDismiss, sheetState = sheetState) {
        LocationPanelsContent(
            onDismiss = onDismiss,
            onTripSelected = onTripSelected,
            modifier = modifier.fillMaxWidth().fillMaxHeight(0.92f),
        )
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun LocationPanelsContent(onDismiss: () -> Unit, onTripSelected: (java.time.LocalDate, List<orbit.nex.steps.location.GeoLatLon>) -> Unit, modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val repository = remember { LocationLibraryRepository(prefs, cacheFile = java.io.File(context.filesDir, "visits_cache.json")) }
    val scope = rememberCoroutineScope()
    val config = remember { prefs.getWebhookConfigs().firstOrNull { it.isEnabled } }
    var tabName by rememberSaveable { mutableStateOf(LibraryTab.PLACES.name) }
    val tab = LibraryTab.entries.firstOrNull { it.name == tabName } ?: LibraryTab.PLACES
    var snapshot by remember { mutableStateOf(LibrarySnapshot()) }
    var loading by remember { mutableStateOf(false) }
    var refreshKey by remember { mutableIntStateOf(0) }
    var pending by remember { mutableStateOf<PendingLibraryAction?>(null) }
    var renameEntry by remember { mutableStateOf<LibraryEntry?>(null) }
    var renameText by remember { mutableStateOf("") }
    var visitEdit by remember { mutableStateOf<LibraryEntry?>(null) }
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
            Modifier.fillMaxWidth().padding(horizontal = Spacing.lg, vertical = Spacing.md),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(Modifier.weight(1f)) {
                Text(stringResource(R.string.map_panels_title), style = MaterialTheme.typography.titleLarge)
                Text(
                    stringResource(R.string.map_panels_subtitle),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            IconButton(onClick = { refreshKey++ }, enabled = !loading) {
                Icon(Icons.Filled.Refresh, contentDescription = stringResource(R.string.library_refresh))
            }
            IconButton(onClick = onDismiss) {
                Icon(Icons.Filled.Close, contentDescription = stringResource(R.string.action_close))
            }
        }

        FlowRow(
            Modifier.fillMaxWidth().padding(horizontal = Spacing.lg),
            horizontalArrangement = Arrangement.spacedBy(Spacing.sm),
            verticalArrangement = Arrangement.spacedBy(6.dp),
        ) {
            LibraryTab.entries.filter { it != LibraryTab.VISITS }.forEach { candidate ->
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
                modifier = Modifier.fillMaxWidth().padding(horizontal = Spacing.lg, vertical = Spacing.sm),
            )
        }

        Spacer(Modifier.height(8.dp))

        when {
            config == null -> LibraryMessage(stringResource(R.string.library_no_connection))
            loading && snapshot.entries.isEmpty() -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator()
            }
            snapshot.error != null -> LibraryMessage(stringResource(R.string.library_error, snapshot.error.orEmpty()), isError = true)
            snapshot.entries.isEmpty() -> LibraryMessage(stringResource(R.string.library_empty))
            else -> LazyColumn(
                Modifier.fillMaxSize().padding(horizontal = Spacing.md),
                verticalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                if (snapshot.offline) {
                    item { Text(stringResource(R.string.visit_offline), style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.error) }
                }
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
                        enabled = !actionBusy && !snapshot.offline,
                        onAction = { action ->
                            if (action == LibraryAction.RENAME) { renameEntry = entry; renameText = entry.title }
                            else if (action == LibraryAction.CORRECT) visitEdit = entry
                            else pending = PendingLibraryAction(entry, action)
                        },
                        onOpen = {
                            val activeConfig = config
                            if (entry.isTrip && activeConfig != null) {
                                actionBusy = true
                                scope.launch {
                                    repository.tripGeometry(activeConfig, entry.id)
                                        .onSuccess { (day, path) -> onTripSelected(day, path) }
                                        .onFailure { actionError = if (it.message == "no_geometry") context.getString(R.string.library_trip_no_geometry) else it.message }
                                    actionBusy = false
                                }
                            }
                        },
                    )
                }
                item { Spacer(Modifier.height(10.dp)) }
            }
        }
    }

    visitEdit?.let { entry ->
        val active = config
        if (active != null) VisitEditorDialog(
            repository = repository,
            config = active,
            entry = entry,
            onDone = { changed -> visitEdit = null; if (changed) { notice = updatedLabel; refreshKey++ } },
        )
    }

    renameEntry?.let { entry ->
        AlertDialog(
            onDismissRequest = { if (!actionBusy) renameEntry = null },
            title = { Text(stringResource(R.string.library_rename)) },
            text = { OutlinedTextField(value = renameText, onValueChange = { renameText = it }, singleLine = true, label = { Text(stringResource(R.string.library_trip_name)) }) },
            confirmButton = {
                TextButton(enabled = !actionBusy && renameText.isNotBlank(), onClick = {
                    val active = config ?: return@TextButton
                    actionBusy = true
                    scope.launch {
                        repository.renameTrip(active, entry.id, renameText.trim()).onSuccess { notice = updatedLabel; refreshKey++; renameEntry = null }
                            .onFailure { actionError = it.message ?: context.getString(R.string.library_error_generic); renameEntry = null }
                        actionBusy = false
                    }
                }) { Text(stringResource(R.string.library_rename)) }
            },
            dismissButton = { TextButton(enabled = !actionBusy, onClick = { renameEntry = null }) { Text(stringResource(R.string.library_cancel)) } },
        )
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
    Box(Modifier.fillMaxSize().padding(Spacing.xl), contentAlignment = Alignment.Center) {
        Text(
            message,
            color = if (isError) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant,
            style = MaterialTheme.typography.bodyMedium,
        )
    }
}

@Composable
private fun LibraryCard(entry: LibraryEntry, enabled: Boolean, onAction: (LibraryAction) -> Unit, onOpen: () -> Unit) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val config = prefs.getWebhookConfigs().firstOrNull { it.isEnabled }
    var photoBitmap by remember(entry.id, entry.osmId) { mutableStateOf<android.graphics.Bitmap?>(null) }
    LaunchedEffect(entry.osmType, entry.osmId, config?.url) {
        val type = entry.osmType
        val id = entry.osmId
        val active = config
        if (type != null && id != null && active != null) {
            photoBitmap = PlacePhotoCache.ensure(context, prefs, active, type, id)
        }
    }
    Card(
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
        shape = RoundedCornerShape(16.dp),
        modifier = Modifier.fillMaxWidth().then(if (entry.isTrip) Modifier.clickable(enabled = enabled, onClick = onOpen) else Modifier),
    ) {
        Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(5.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                val bmp = photoBitmap
                if (bmp != null) {
                    Image(
                        bitmap = bmp.asImageBitmap(),
                        contentDescription = null,
                        modifier = Modifier.size(36.dp).clip(CircleShape),
                    )
                } else if (entry.osmId != null) {
                    Box(
                        Modifier.size(36.dp).clip(CircleShape).background(MaterialTheme.colorScheme.surface),
                        contentAlignment = Alignment.Center,
                    ) { Icon(Icons.Filled.Place, null, modifier = Modifier.size(18.dp)) }
                }
                Text(
                    entry.title.ifBlank { stringResource(R.string.visit_unidentified) },
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
                            .padding(horizontal = Spacing.sm, vertical = 3.dp),
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
    "confirmed" -> stringResource(R.string.visit_status_confirmed)
    "provisional" -> stringResource(R.string.visit_status_provisional)
    "manual" -> stringResource(R.string.visit_status_manual)
    "unidentified" -> stringResource(R.string.visit_status_unidentified)
    "pending" -> stringResource(R.string.visit_status_pending)
    "rejected" -> stringResource(R.string.library_status_archived)
    else -> status.replaceFirstChar { it.uppercase() }
}

private fun LibraryTab.titleRes(): Int = when (this) {
    LibraryTab.PLACES -> R.string.library_tab_places
    LibraryTab.TRIPS -> R.string.library_tab_trips
    LibraryTab.VISITS -> R.string.library_tab_visits
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
    LibraryAction.RENAME -> R.string.library_rename
    LibraryAction.CONFIRM -> R.string.library_confirm
    LibraryAction.REJECT -> R.string.library_reject
    LibraryAction.CORRECT -> R.string.visit_edit
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
    LibraryAction.RENAME -> stringResource(R.string.library_rename)
    LibraryAction.CONFIRM, LibraryAction.REJECT, LibraryAction.CORRECT -> stringResource(R.string.library_confirm_visit)
    LibraryAction.PAUSE, LibraryAction.RESUME -> stringResource(R.string.library_confirm_device)
    LibraryAction.REVOKE -> stringResource(R.string.library_confirm_revoke)
    LibraryAction.DISABLE -> stringResource(R.string.library_confirm_disable_geofence)
    LibraryAction.EXCLUDE -> stringResource(R.string.library_confirm_exclude)
    LibraryAction.UNDO -> stringResource(R.string.library_confirm_undo)
    LibraryAction.PIN_VIEW -> stringResource(R.string.library_confirm_pin_view)
    LibraryAction.DELETE_VIEW -> stringResource(R.string.library_confirm_delete_view)
    LibraryAction.APPLY_VIEW -> stringResource(R.string.library_confirm_apply_view)
}

private val VISIT_TAGS = listOf("" to R.string.visit_tag_none, "home" to R.string.visit_tag_home, "work" to R.string.visit_tag_work, "school" to R.string.visit_tag_school, "gym" to R.string.visit_tag_gym, "favorite" to R.string.visit_tag_favorite)

@Composable
private fun VisitEditorDialog(
    repository: LocationLibraryRepository,
    config: orbit.nex.steps.WebhookConfig,
    entry: LibraryEntry,
    onDone: (Boolean) -> Unit,
) {
    val scope = rememberCoroutineScope()
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    var detail by remember { mutableStateOf<VisitDetail?>(null) }
    var name by remember { mutableStateOf("") }
    var category by remember { mutableStateOf("") }
    var tag by remember { mutableStateOf("") }
    var busy by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }
    var photoBitmap by remember { mutableStateOf<android.graphics.Bitmap?>(null) }
    LaunchedEffect(entry.id) {
        repository.visitDetail(config, entry.id)
            .onSuccess { detail = it; name = it.name; category = it.category; tag = it.tag }
            .onFailure { error = it.message }
        busy = false
    }
    LaunchedEffect(detail?.osmType, detail?.osmId) {
        val type = detail?.osmType
        val id = detail?.osmId
        if (type != null && id != null) photoBitmap = PlacePhotoCache.ensure(context, prefs, config, type, id)
    }
    fun run(block: suspend () -> Result<Unit>) {
        busy = true
        error = null
        scope.launch {
            block().onSuccess { onDone(true) }.onFailure { error = it.message; busy = false }
        }
    }
    AlertDialog(
        onDismissRequest = { if (!busy) onDone(false) },
        title = { Text(stringResource(R.string.visit_edit)) },
        text = {
            Column(Modifier.verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                if (busy && detail == null) CircularProgressIndicator()
                detail?.let { d ->
                    photoBitmap?.let { bmp ->
                        Image(bitmap = bmp.asImageBitmap(), contentDescription = null, modifier = Modifier.size(72.dp).clip(CircleShape).align(Alignment.CenterHorizontally))
                    }
                    d.photoAttribution?.takeIf { it.isNotBlank() }?.let {
                        Text(it, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                    if (d.candidates.isNotEmpty()) {
                        Text(stringResource(R.string.visit_candidates), style = MaterialTheme.typography.labelLarge)
                        d.candidates.forEach { c ->
                            TextButton(enabled = !busy, onClick = { run { repository.selectCandidate(config, d.id, c.id) } }, modifier = Modifier.fillMaxWidth()) {
                                Column(Modifier.fillMaxWidth()) {
                                    Text(c.name.ifBlank { stringResource(R.string.visit_unnamed) }, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
                                    Text(c.subtitle, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                }
                            }
                        }
                    }
                    OutlinedTextField(value = name, onValueChange = { name = it.take(80) }, singleLine = true, label = { Text(stringResource(R.string.visit_name)) }, modifier = Modifier.fillMaxWidth())
                    OutlinedTextField(value = category, onValueChange = { category = it.take(60) }, singleLine = true, label = { Text(stringResource(R.string.visit_category)) }, modifier = Modifier.fillMaxWidth())
                    Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        VISIT_TAGS.forEach { (value, label) ->
                            FilterChip(selected = tag == value, onClick = { tag = value }, label = { Text(stringResource(label)) })
                        }
                    }
                    Row(Modifier.horizontalScroll(rememberScrollState())) {
                        TextButton(enabled = !busy, onClick = { run { repository.visitAction(config, d.id, "retry") } }) { Text(stringResource(R.string.visit_retry)) }
                        if (d.canBlock) TextButton(enabled = !busy, onClick = { run { repository.visitAction(config, d.id, "block") } }) { Text(stringResource(R.string.visit_block)) }
                        if (d.osmId != null) TextButton(enabled = !busy, onClick = { run { repository.refreshVisitPhoto(config, d.id) } }) { Text(stringResource(R.string.visit_refresh_photo)) }
                    }
                }
                error?.let { Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall) }
            }
        },
        confirmButton = {
            TextButton(enabled = !busy && detail != null && (name.isNotBlank() || tag.isNotBlank()), onClick = { run { repository.assignVisit(config, entry.id, name.trim(), category.trim(), tag) } }) {
                Text(stringResource(R.string.visit_save))
            }
        },
        dismissButton = { TextButton(enabled = !busy, onClick = { onDone(false) }) { Text(stringResource(R.string.library_cancel)) } },
    )
}
