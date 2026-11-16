package orbit.nex.steps.screens

import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.content.Intent
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.ArrowDownward
import androidx.compose.material.icons.filled.ArrowUpward
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.ContentCopy
import androidx.compose.material.icons.filled.DeleteSweep
import androidx.compose.material.icons.filled.Error
import androidx.compose.material.icons.filled.ExpandLess
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material.icons.filled.FilterList
import androidx.compose.material.icons.filled.IosShare
import androidx.compose.material.icons.filled.MoreVert
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
<<<<<<< HEAD
import androidx.compose.runtime.CompositionLocalProvider
=======
>>>>>>> mobile-spends
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
<<<<<<< HEAD
import androidx.compose.material3.LocalContentColor
=======
>>>>>>> mobile-spends
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.delay
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.R
import orbit.nex.steps.WebhookLog
import orbit.nex.steps.components.SavedToast
import orbit.nex.steps.components.localizedLogLabel
import org.json.JSONArray
import org.json.JSONObject
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/**
 * Full synchronization log: title, expanding search, sort/filter toggles,
 * copy, share and clear. Hosted by [orbit.nex.steps.LogsActivity] and embedded
 * in the home screen entry point.
 */
@Composable
fun LogsScreen(modifier: Modifier = Modifier, onBack: (() -> Unit)? = null) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    var allLogs by remember { mutableStateOf(prefs.getWebhookLogs()) }
    var query by remember { mutableStateOf("") }
    var newestFirst by remember { mutableStateOf(true) }
    var statusFilter by remember { mutableIntStateOf(0) }
    var expandedId by remember { mutableStateOf<String?>(null) }
    var searchFocused by remember { mutableStateOf(false) }
    var menuOpen by remember { mutableStateOf(false) }
    var toast by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(toast) {
        if (toast != null) {
            delay(2_000)
            toast = null
        }
    }
<<<<<<< HEAD
    LaunchedEffect(Unit) {
        while (true) {
            allLogs = prefs.getWebhookLogs()
            delay(1_000)
        }
    }
=======
>>>>>>> mobile-spends

    val logs = remember(allLogs, query, newestFirst, statusFilter) {
        allLogs.asSequence()
            .filter { log ->
                (statusFilter == 0 || (statusFilter == 1 && log.success) || (statusFilter == 2 && !log.success)) &&
                    (query.isBlank() || listOf(log.url, log.errorMessage, log.dataType, log.syncType, log.payload)
                        .filterNotNull()
                        .any { it.contains(query, ignoreCase = true) })
            }
            .sortedBy { it.timestamp }
            .let { if (newestFirst) it.toList().asReversed() else it.toList() }
    }

    Box(modifier = modifier.fillMaxSize()) {
<<<<<<< HEAD
        CompositionLocalProvider(LocalContentColor provides MaterialTheme.colorScheme.onSurface) {
=======
>>>>>>> mobile-spends
        Column(
            modifier = Modifier.fillMaxSize().padding(horizontal = 10.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            // Title, search and actions share one row; the search field is compact
            // until it is focused and then grows into the free space.
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                // Title left, action buttons pushed to the right edge.
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                onBack?.let {
                    IconButton(onClick = it) {
                        Icon(
                            Icons.AutoMirrored.Filled.ArrowBack,
                            contentDescription = stringResource(R.string.logs_back_cd),
                        )
                    }
                }
                Text(
                    stringResource(R.string.home_logs_title),
                    style = MaterialTheme.typography.titleLarge,
                    maxLines = 1,
                    softWrap = false,
                    modifier = Modifier
                        .weight(1f)
                        .padding(start = 4.dp),
                )
                IconButton(onClick = {
                    newestFirst = !newestFirst
                    toast = context.getString(
                        if (newestFirst) R.string.logs_sort_changed_newest else R.string.logs_sort_changed_oldest,
                    )
                }) {
                    Icon(
                        if (newestFirst) Icons.Filled.ArrowDownward else Icons.Filled.ArrowUpward,
                        contentDescription = stringResource(
                            if (newestFirst) R.string.logs_sort_newest else R.string.logs_sort_oldest,
                        ),
                    )
                }
                IconButton(onClick = { statusFilter = (statusFilter + 1) % 3 }) {
                    val (icon, label) = when (statusFilter) {
                        1 -> Icons.Filled.CheckCircle to R.string.logs_filter_success
                        2 -> Icons.Filled.Error to R.string.logs_filter_error
                        else -> Icons.Filled.FilterList to R.string.logs_filter_all
                    }
                    Icon(icon, contentDescription = stringResource(label))
                }
                Box {
                    IconButton(onClick = { menuOpen = true }) {
                        Icon(Icons.Filled.MoreVert, contentDescription = null)
                    }
                    DropdownMenu(expanded = menuOpen, onDismissRequest = { menuOpen = false }) {
                        DropdownMenuItem(
                            text = { Text(stringResource(R.string.logs_action_export)) },
                            leadingIcon = { Icon(Icons.Filled.IosShare, contentDescription = null) },
                            onClick = {
                                menuOpen = false
                                shareLogs(context, logs)
                            },
                        )
                        DropdownMenuItem(
                            text = { Text(stringResource(R.string.logs_clear)) },
                            leadingIcon = { Icon(Icons.Filled.DeleteSweep, contentDescription = null) },
                            enabled = allLogs.isNotEmpty(),
                            onClick = {
                                menuOpen = false
                                prefs.clearWebhookLogs()
                                allLogs = emptyList()
                                toast = context.getString(R.string.logs_cleared_toast)
                            },
                        )
                    }
                }
            }
            // Search moved to its own row so the title and actions are never
            // squeezed by the field on narrow screens.
            OutlinedTextField(
                value = query,
                onValueChange = { query = it },
                modifier = Modifier
                    .fillMaxWidth()
                    .height(46.dp)
                    .onFocusChanged { searchFocused = it.isFocused },
                leadingIcon = {
                    Icon(Icons.Filled.Search, contentDescription = null, modifier = Modifier.size(18.dp))
                },
                placeholder = {
                    Text(
                        stringResource(R.string.logs_search_short),
                        maxLines = 1,
                        style = MaterialTheme.typography.bodySmall,
                    )
                },
                textStyle = MaterialTheme.typography.bodySmall,
                singleLine = true,
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = MaterialTheme.colorScheme.outline,
                    unfocusedBorderColor = MaterialTheme.colorScheme.outline,
                    focusedContainerColor = MaterialTheme.colorScheme.surface,
                    unfocusedContainerColor = MaterialTheme.colorScheme.surface,
                ),
                shape = MaterialTheme.shapes.medium,
            )
            if (logs.isEmpty()) {
                Box(
                    modifier = Modifier.fillMaxWidth().weight(1f),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        stringResource(if (allLogs.isEmpty()) R.string.logs_empty else R.string.logs_no_matches),
                        style = MaterialTheme.typography.bodyLarge,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        textAlign = TextAlign.Center,
                    )
                }
            } else {
                LazyColumn(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    items(logs, key = { it.id }) { log ->
                        val expanded = expandedId == log.id
                        val blockText = listOfNotNull(prettyJson(log.errorMessage), prettyJson(log.payload))
                            .filter { it.isNotBlank() }
                        Card(
                            colors = CardDefaults.cardColors(
                                containerColor = if (log.success) MaterialTheme.colorScheme.surfaceVariant
                                else MaterialTheme.colorScheme.errorContainer,
                            ),
                        ) {
                            Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                Row(Modifier.fillMaxWidth()) {
                                    Text(
                                        localizedLogLabel(log.dataType, log.syncType) ?: stringResource(R.string.home_logs_title),
                                        style = MaterialTheme.typography.titleSmall,
                                        modifier = Modifier.weight(1f),
                                    )
                                    IconButton(onClick = { expandedId = if (expanded) null else log.id }) {
                                        Icon(
                                            if (expanded) Icons.Filled.ExpandLess else Icons.Filled.ExpandMore,
                                            contentDescription = null,
                                        )
                                    }
                                }
                                Text(log.url, style = MaterialTheme.typography.bodyMedium)
                                val statusText = log.statusCode?.let { "${stringResource(R.string.logs_row_http_label)} $it" }
                                    ?: if (log.success) stringResource(R.string.logs_row_ok) else stringResource(R.string.logs_row_err)
                                Text(
                                    "${formatLogTime(log.timestamp)} · $statusText",
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                                if (expanded) {
                                    val copyText = blockText.ifEmpty { listOf(log.url) }
                                    Row(Modifier.fillMaxWidth()) {
                                        if (blockText.isNotEmpty()) {
                                            Surface(
                                                color = MaterialTheme.colorScheme.surfaceVariant,
                                                shape = MaterialTheme.shapes.small,
                                                modifier = Modifier.weight(1f),
                                            ) {
                                                Text(
                                                    text = blockText.joinToString("\n"),
                                                    modifier = Modifier.padding(8.dp),
                                                    fontFamily = FontFamily.Monospace,
                                                    fontSize = 12.sp,
                                                    color = MaterialTheme.colorScheme.onSurface,
                                                )
                                            }
                                        } else {
                                            Text(
                                                text = copyText.joinToString("\n"),
                                                modifier = Modifier.weight(1f),
                                                color = MaterialTheme.colorScheme.onSurface,
                                            )
                                        }
                                        IconButton(onClick = {
                                            copyLogMessage(context, copyText.joinToString("\n"))
                                            toast = context.getString(R.string.action_copy)
                                        }) {
                                            Icon(Icons.Filled.ContentCopy, contentDescription = stringResource(R.string.action_copy))
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
<<<<<<< HEAD
        }
=======
>>>>>>> mobile-spends
        toast?.let { message ->
            SavedToast(
                message,
                Modifier.align(Alignment.BottomCenter).padding(bottom = 24.dp),
            )
        }
    }
}

private fun prettyJson(text: String?): String {
    if (text.isNullOrBlank()) return ""
    val trimmed = text.trim()
    return when {
        trimmed.startsWith("{") -> runCatching { JSONObject(trimmed).toString(2) }.getOrElse { text }
        trimmed.startsWith("[") -> runCatching { JSONArray(trimmed).toString(2) }.getOrElse { text }
        else -> text
    }
}

private fun formatLogTime(timestamp: Long): String =
    SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()).format(Date(timestamp))

private fun copyLogMessage(context: Context, message: String) {
    (context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager).setPrimaryClip(
        ClipData.newPlainText(context.getString(R.string.logs_title), message),
    )
}

private fun shareLogs(context: Context, logs: List<WebhookLog>) {
    if (logs.isEmpty()) return
    val body = logs.joinToString("\n\n") { log ->
        buildString {
            append(formatLogTime(log.timestamp))
            append(" · ")
            append(if (log.success) "OK" else "ERR")
            log.statusCode?.let { append(" · HTTP ").append(it) }
            append("\n").append(log.url)
            log.dataType?.let { append("\n").append(it) }
            log.errorMessage?.takeIf { it.isNotBlank() }?.let { append("\n").append(it) }
        }
    }
    val intent = Intent(Intent.ACTION_SEND).apply {
        type = "text/plain"
        putExtra(Intent.EXTRA_SUBJECT, context.getString(R.string.logs_export_intent_title))
        putExtra(Intent.EXTRA_TEXT, body)
    }
    context.startActivity(Intent.createChooser(intent, context.getString(R.string.logs_export_chooser)))
}
