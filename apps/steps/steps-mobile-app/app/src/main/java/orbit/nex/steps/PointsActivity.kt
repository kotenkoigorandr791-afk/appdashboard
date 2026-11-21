package orbit.nex.steps

import orbit.nex.steps.ui.theme.Spacing
import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.ArrowForward
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.ExpandLess
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material.icons.filled.Map
import androidx.compose.material.icons.filled.Restore
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import orbit.nex.steps.components.AppTooltipIconButton
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.res.stringResource
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.Job
import kotlinx.coroutines.launch
import orbit.nex.steps.components.AppEmptyState
import orbit.nex.steps.components.AppSectionCard
import orbit.nex.steps.components.AppStatusPill
import orbit.nex.steps.location.ManagedPoint
import orbit.nex.steps.location.PointManagementRepository
import orbit.nex.steps.location.QualityCorrection
import orbit.nex.steps.ui.theme.HCWebhookTheme
import java.time.OffsetDateTime
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.util.Locale

class PointsActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent { HCWebhookTheme { PointsScreen { finish() } } }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PointsScreen(onBack: () -> Unit) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val repository = remember { PointManagementRepository(prefs, context) }
    val scope = rememberCoroutineScope()
    val config = remember { prefs.getWebhookConfigs().firstOrNull { it.isEnabled } }
    var from by remember { mutableStateOf("") }
    var to by remember { mutableStateOf("") }
    var stateFilter by remember { mutableStateOf("") }
    var page by remember { mutableIntStateOf(0) }
    var total by remember { mutableIntStateOf(0) }
    var points by remember { mutableStateOf(emptyList<ManagedPoint>()) }
    var selected by remember { mutableStateOf(emptySet<String>()) }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var pendingDelete by remember { mutableStateOf(false) }
    var corrections by remember { mutableStateOf(emptyList<QualityCorrection>()) }
    var undoPending by remember { mutableStateOf<QualityCorrection?>(null) }
    var showHistory by remember { mutableStateOf(false) }
    var loadJob by remember { mutableStateOf<Job?>(null) }
    var notice by remember { mutableStateOf<String?>(null) }
    val pageCount = ((total + 99) / 100).coerceAtLeast(1)

    suspend fun load() {
        val active = config ?: run {
            error = context.getString(R.string.points_no_connection)
            points = emptyList()
            return
        }
        loading = true
        error = null
        val result = repository.loadPage(active, page, from.ifBlank { null }, to.ifBlank { null }, stateFilter.ifBlank { null }, null)
        points = result.points
        total = result.total
        error = result.error
        selected = emptySet()
        loading = false
    }

    suspend fun loadHistory() {
        val active = config ?: return
        corrections = repository.corrections(active)
    }

    LaunchedEffect(from, to, stateFilter, page, config?.url) {
        loadJob?.cancel()
        loadJob = scope.launch { load() }
    }
    LaunchedEffect(config?.url) { loadHistory() }
    // Returning from the map-selection screen may have deleted points there, so
    // the list must not keep showing rows that no longer exist on the server.
    val lifecycleOwner = LocalLifecycleOwner.current
    DisposableEffect(lifecycleOwner) {
        val observer = LifecycleEventObserver { _, event ->
            if (event == Lifecycle.Event.ON_RESUME) scope.launch { load() }
        }
        lifecycleOwner.lifecycle.addObserver(observer)
        onDispose { lifecycleOwner.lifecycle.removeObserver(observer) }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(stringResource(R.string.points_management_title)) },
                navigationIcon = {
                    AppTooltipIconButton(
                        onClick = onBack,
                        imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                        contentDescription = stringResource(R.string.settings_back_cd),
                    )
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.surfaceVariant,
                    scrolledContainerColor = MaterialTheme.colorScheme.surfaceVariant,
                ),
            )
        },
        containerColor = MaterialTheme.colorScheme.background,
    ) { padding ->
        orbit.nex.steps.components.AppBackground {
            LazyColumn(
                Modifier.fillMaxSize().padding(padding).padding(horizontal = Spacing.md),
                verticalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                item {
                    AppSectionCard(title = stringResource(R.string.points_filter_title), icon = Icons.Filled.Search) {
                        Row(
                            Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
                            horizontalArrangement = Arrangement.spacedBy(6.dp),
                        ) {
                            listOf("" to R.string.points_filter_all, "pending" to R.string.points_state_pending,
                                "accepted" to R.string.points_state_accepted, "flagged" to R.string.points_state_flagged,
                                "rejected" to R.string.points_state_rejected).forEach { (value, label) ->
                                FilterChip(
                                    selected = stateFilter == value,
                                    onClick = { stateFilter = value; page = 0 },
                                    label = { Text(stringResource(label)) },
                                )
                            }
                        }
                    }
                }
                item {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                        OutlinedButton(
                            onClick = {
                                context.startActivity(Intent(context, PointSelectionActivity::class.java))
                            },
                            modifier = Modifier.weight(1f).height(64.dp),
                        ) {
                            Icon(Icons.Filled.Map, contentDescription = null, modifier = Modifier.size(18.dp))
                            Text(stringResource(R.string.points_select_on_map), modifier = Modifier.padding(start = 6.dp), textAlign = androidx.compose.ui.text.style.TextAlign.Center)
                        }
                        if (selected.isNotEmpty()) {
                            Button(onClick = { pendingDelete = true }, modifier = Modifier.weight(1f).height(64.dp)) {
                                Icon(Icons.Filled.Delete, contentDescription = null, modifier = Modifier.size(18.dp))
                                Text(stringResource(R.string.points_delete_count, selected.size), modifier = Modifier.padding(start = 6.dp))
                            }
                        }
                    }
                }
                val undoable = corrections.firstOrNull { it.state == "active" }
                if (undoable != null) {
                    item {
                        OutlinedButton(onClick = { undoPending = undoable }, modifier = Modifier.fillMaxWidth()) {
                            Icon(Icons.Filled.Restore, contentDescription = null, modifier = Modifier.size(18.dp))
                            Text(
                                stringResource(R.string.points_undo_last, undoable.pointCount),
                                modifier = Modifier.padding(start = Spacing.sm),
                            )
                        }
                    }
                }
                item {
                    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                        Text(
                            stringResource(R.string.points_page_status, page + 1, pageCount, total),
                            style = MaterialTheme.typography.labelMedium,
                            modifier = Modifier.weight(1f),
                        )
                        AppTooltipIconButton(
                            enabled = page > 0 && !loading,
                            onClick = { page-- },
                            imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                            contentDescription = stringResource(R.string.points_previous_page),
                        )
                        AppTooltipIconButton(
                            enabled = page + 1 < pageCount && !loading,
                            onClick = { page++ },
                            imageVector = Icons.AutoMirrored.Filled.ArrowForward,
                            contentDescription = stringResource(R.string.points_next_page),
                        )
                    }
                }
                if (loading) item { LinearProgressIndicator(Modifier.fillMaxWidth()) }
                notice?.let { text -> item { AppStatusPill(text, highlighted = true) } }
                error?.let { text -> item { AppEmptyState(text, isError = true) } }
                if (!loading && error == null && points.isEmpty()) {
                    item { AppEmptyState(stringResource(R.string.points_empty)) }
                }
                items(points, key = { it.id }) { point ->
                    PointRow(
                        point = point,
                        checked = point.id in selected,
                        onChecked = { checked ->
                            selected = if (checked) selected + point.id else selected - point.id
                        },
                        onMap = {
                            context.startActivity(
                                Intent(context, PointSelectionActivity::class.java)
                                    .putExtra("focus_lat", point.lat)
                                    .putExtra("focus_lon", point.lon)
                            )
                        },
                    )
                }
                item {
                    OutlinedButton(
                        onClick = { showHistory = !showHistory },
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Text(stringResource(R.string.points_correction_history), modifier = Modifier.weight(1f))
                        Icon(
                            if (showHistory) Icons.Filled.ExpandLess else Icons.Filled.ExpandMore,
                            contentDescription = null,
                        )
                    }
                }
                if (showHistory) {
                    if (corrections.isEmpty()) {
                        item { AppEmptyState(stringResource(R.string.points_no_corrections)) }
                    } else {
                        items(corrections.take(50), key = { it.id }) { correction ->
                            CorrectionRow(correction) { undoPending = correction }
                        }
                        if (corrections.size >= 50) {
                            item {
                                Text(
                                    stringResource(R.string.points_correction_cap),
                                    style = MaterialTheme.typography.labelSmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    if (pendingDelete) {
        AlertDialog(
            onDismissRequest = { pendingDelete = false },
            title = { Text(stringResource(R.string.points_delete_confirm_title)) },
            text = { Text(stringResource(R.string.points_delete_confirm_text, selected.size)) },
            confirmButton = {
                TextButton(onClick = {
                    pendingDelete = false
                    val ids = selected
                    scope.launch {
                        val active = config ?: return@launch
                        repository.deleteIds(active, ids).onSuccess { deleted ->
                            notice = context.getString(R.string.points_deleted, deleted)
                            selected = emptySet()
                            page = 0
                            load()
                        }.onFailure { failure ->
                            error = failure.message ?: failure.javaClass.simpleName
                        }
                    }
                }) { Text(stringResource(R.string.points_delete_forever)) }
            },
            dismissButton = { TextButton(onClick = { pendingDelete = false }) { Text(stringResource(R.string.library_cancel)) } },
        )
    }

    undoPending?.let { correction ->
        AlertDialog(
            onDismissRequest = { undoPending = null },
            title = { Text(stringResource(R.string.points_undo_confirm_title)) },
            text = { Text(stringResource(R.string.points_undo_confirm_text, correction.pointCount)) },
            confirmButton = {
                TextButton(onClick = {
                    val active = config
                    undoPending = null
                    if (active != null) {
                        scope.launch {
                            repository.undoCorrection(active, correction.id)
                                .onSuccess {
                                    notice = context.getString(R.string.points_undo_done)
                                    loadHistory()
                                    load()
                                }
                                .onFailure { error = it.message }
                        }
                    }
                }) { Text(stringResource(R.string.library_undo)) }
            },
            dismissButton = { TextButton(onClick = { undoPending = null }) { Text(stringResource(R.string.library_cancel)) } },
        )
    }
}

@Composable
private fun PointRow(point: ManagedPoint, checked: Boolean, onChecked: (Boolean) -> Unit, onMap: () -> Unit) {
    Surface(
        shape = MaterialTheme.shapes.medium,
        color = MaterialTheme.colorScheme.surfaceVariant,
        modifier = Modifier.fillMaxWidth().clickable { onChecked(!checked) },
    ) {
        Row(Modifier.padding(10.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
            Checkbox(checked, onCheckedChange = onChecked)
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
                Text(
                    formatPointTime(point.sourceAt),
                    style = MaterialTheme.typography.bodyMedium,
                    fontWeight = FontWeight.Medium,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically) {
                    AppStatusPill(point.qualityState)
                    point.accuracyM?.let { AppStatusPill("±${it.toInt()} m") }
                    point.source.takeIf { it.isNotBlank() }?.let { AppStatusPill(it) }
                }
            }
            AppTooltipIconButton(
                onClick = onMap,
                imageVector = Icons.Filled.Map,
                contentDescription = stringResource(R.string.points_open_map),
            )
        }
    }
}

@Composable
private fun CorrectionRow(correction: QualityCorrection, onUndo: () -> Unit) {
    val active = correction.state == "active"
    Surface(
        shape = MaterialTheme.shapes.medium,
        color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = if (active) 1f else 0.55f),
        modifier = Modifier.fillMaxWidth(),
    ) {
        Column(Modifier.padding(Spacing.md), verticalArrangement = Arrangement.spacedBy(5.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    stringResource(R.string.points_correction_points, correction.pointCount),
                    fontWeight = FontWeight.Medium,
                    modifier = Modifier.weight(1f),
                )
                AppStatusPill(correction.state, highlighted = active)
            }
            Text(
                correction.effects.joinToString(" · ").ifBlank { correction.scopeType },
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Text(
                formatPointTime(correction.revokedAt ?: correction.createdAt),
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            if (active) {
                TextButton(onClick = onUndo) { Text(stringResource(R.string.library_undo)) }
            }
        }
    }
}

private fun formatPointTime(value: String): String {
    if (value.isBlank()) return "—"
    return runCatching {
        OffsetDateTime.parse(value).atZoneSameInstant(ZoneId.systemDefault())
            .format(DateTimeFormatter.ofPattern("d MMM yyyy, HH:mm", Locale.getDefault()))
    }.getOrElse { value.take(16).replace('T', ' ') }
}
