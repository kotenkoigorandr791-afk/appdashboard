package com.stepmonitor.app.screens
import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowDownward
import androidx.compose.material.icons.filled.ArrowUpward
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.ContentCopy
import androidx.compose.material.icons.filled.Error
import androidx.compose.material.icons.filled.ExpandLess
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material.icons.filled.FilterList
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.SnackbarDuration
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.stepmonitor.app.PreferencesManager
import com.stepmonitor.app.R
import kotlinx.coroutines.launch
import org.json.JSONArray
import org.json.JSONObject
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
@Composable
fun LogsScreen(modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val snackbarHostState = remember { SnackbarHostState() }
    val allLogs = remember { PreferencesManager(context).getWebhookLogs() }
    var query by remember { mutableStateOf("") }
    var newestFirst by remember { mutableStateOf(true) }
    var statusFilter by remember { mutableIntStateOf(0) }
    var expandedId by remember { mutableStateOf<String?>(null) }
    val logs = remember(allLogs, query, newestFirst, statusFilter) {
        allLogs.asSequence()
            .filter { log ->
                (statusFilter == 0 || (statusFilter == 1 && log.success) || (statusFilter == 2 && !log.success)) &&
                    (query.isBlank() || listOf(log.url, log.errorMessage, log.dataType, log.syncType, log.payload).filterNotNull().any { it.contains(query, ignoreCase = true) })
            }
            .sortedBy { it.timestamp }
            .let { if (newestFirst) it.toList().asReversed() else it.toList() }
    }
    Box(modifier = modifier.fillMaxSize()) {
        Column(
            modifier = Modifier.fillMaxSize().padding(20.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Text(stringResource(R.string.nav_logs), style = MaterialTheme.typography.titleLarge)
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(4.dp)
            ) {
                OutlinedTextField(
                    value = query,
                    onValueChange = { query = it },
                    modifier = Modifier.weight(1f),
                    leadingIcon = { Icon(Icons.Filled.Search, contentDescription = null) },
                    label = { Text(stringResource(R.string.logs_search)) },
                    singleLine = true
                )
                IconButton(onClick = {
                    newestFirst = !newestFirst
                    val msg = if (newestFirst) context.getString(R.string.logs_sort_newest) else context.getString(R.string.logs_sort_oldest)
                    scope.launch {
                        snackbarHostState.currentSnackbarData?.dismiss()
                        snackbarHostState.showSnackbar(msg, duration = SnackbarDuration.Short)
                    }
                }) {
                    Icon(
                        if (newestFirst) Icons.Filled.ArrowDownward else Icons.Filled.ArrowUpward,
                        contentDescription = stringResource(if (newestFirst) R.string.logs_sort_newest else R.string.logs_sort_oldest)
                    )
                }
                IconButton(onClick = {
                    statusFilter = (statusFilter + 1) % 3
                    val msg = when (statusFilter) {
                        1 -> context.getString(R.string.logs_filter_success)
                        2 -> context.getString(R.string.logs_filter_error)
                        else -> context.getString(R.string.logs_filter_all)
                    }
                    scope.launch {
                        snackbarHostState.currentSnackbarData?.dismiss()
                        snackbarHostState.showSnackbar(msg, duration = SnackbarDuration.Short)
                    }
                }) {
                    val (icon, label) = when (statusFilter) {
                        1 -> Icons.Filled.CheckCircle to R.string.logs_filter_success
                        2 -> Icons.Filled.Error to R.string.logs_filter_error
                        else -> Icons.Filled.FilterList to R.string.logs_filter_all
                    }
                    Icon(icon, contentDescription = stringResource(label))
                }
            }
            LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            items(logs, key = { it.id }) { log ->
                val expanded = expandedId == log.id
                val blockText = listOfNotNull(prettyJson(log.errorMessage), prettyJson(log.payload))
                    .filter { it.isNotBlank() }
                Card(colors = CardDefaults.cardColors(containerColor = if (log.success) MaterialTheme.colorScheme.surfaceVariant else MaterialTheme.colorScheme.errorContainer)) {
                    Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Row(Modifier.fillMaxWidth()) {
                            Text(log.dataType ?: log.syncType ?: stringResource(R.string.nav_logs), style = MaterialTheme.typography.titleSmall, modifier = Modifier.weight(1f))
                            IconButton(onClick = { expandedId = if (expanded) null else log.id }) {
                                Icon(if (expanded) Icons.Filled.ExpandLess else Icons.Filled.ExpandMore, contentDescription = null)
                            }
                        }
                        Text(log.url, style = MaterialTheme.typography.bodyMedium)
                        Text("${formatLogTime(log.timestamp)} · ${log.statusCode?.let { "HTTP $it" } ?: if (log.success) "OK" else "ERR"}", color = MaterialTheme.colorScheme.onSurfaceVariant)
                        if (expanded) {
                            val copyText = blockText.ifEmpty { listOf(log.url) }
                            Row(Modifier.fillMaxWidth()) {
                                if (blockText.isNotEmpty()) {
                                    Surface(
                                        color = MaterialTheme.colorScheme.surfaceVariant,
                                        shape = RoundedCornerShape(8.dp),
                                        modifier = Modifier.weight(1f)
                                    ) {
                                        Text(
                                            text = blockText.joinToString("\n"),
                                            modifier = Modifier.padding(8.dp),
                                            fontFamily = FontFamily.Monospace,
                                            fontSize = 12.sp,
                                            color = if (log.success) MaterialTheme.colorScheme.onSurfaceVariant else MaterialTheme.colorScheme.error
                                        )
                                    }
                                } else {
                                    Text(
                                        text = copyText.joinToString("\n"),
                                        modifier = Modifier.weight(1f),
                                        color = if (log.success) MaterialTheme.colorScheme.onSurfaceVariant else MaterialTheme.colorScheme.error
                                    )
                                }
                                IconButton(onClick = { copyLogMessage(context, copyText.joinToString("\n")) }) {
                                    Icon(Icons.Filled.ContentCopy, contentDescription = stringResource(R.string.action_copy))
                                }
                            }
                        }
}
                    }
                }
            }
        }
        SnackbarHost(
            hostState = snackbarHostState,
            modifier = Modifier.align(Alignment.BottomCenter).padding(bottom = 8.dp),
            snackbar = { data ->
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.Center) {
                    Surface(
                        modifier = Modifier.fillMaxWidth(0.75f),
                        shape = RoundedCornerShape(50),
                        color = MaterialTheme.colorScheme.surfaceVariant,
                        contentColor = MaterialTheme.colorScheme.onSurfaceVariant,
                        shadowElevation = 6.dp
                    ) {
                        Text(
                            text = data.visuals.message,
                            modifier = Modifier.fillMaxWidth().padding(horizontal = 24.dp, vertical = 14.dp),
                            textAlign = TextAlign.Center,
                            style = MaterialTheme.typography.bodyMedium
                        )
                    }
                }
            }
        )
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
private fun formatLogTime(timestamp: Long): String = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()).format(Date(timestamp))
private fun copyLogMessage(context: Context, message: String) {
    (context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager).setPrimaryClip(ClipData.newPlainText("StepMonitor log", message))
}
