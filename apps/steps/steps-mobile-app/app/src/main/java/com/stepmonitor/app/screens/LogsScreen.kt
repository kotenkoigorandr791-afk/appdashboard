package com.stepmonitor.app.screens
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.AssistChip
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.stepmonitor.app.PreferencesManager
import com.stepmonitor.app.R
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
@Composable
fun LogsScreen(modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val allLogs = remember { PreferencesManager(context).getWebhookLogs() }
    var query by remember { mutableStateOf("") }
    var newestFirst by remember { mutableStateOf(true) }
    var statusFilter by remember { mutableIntStateOf(0) }
    val logs = remember(allLogs, query, newestFirst, statusFilter) {
        allLogs.asSequence()
            .filter { log ->
                (statusFilter == 0 || (statusFilter == 1 && log.success) || (statusFilter == 2 && !log.success)) &&
                    (query.isBlank() || listOf(log.url, log.errorMessage, log.dataType, log.syncType).filterNotNull().any { it.contains(query, ignoreCase = true) })
            }
            .sortedBy { it.timestamp }
            .let { if (newestFirst) it.toList().asReversed() else it.toList() }
    }
    Column(modifier.fillMaxSize().padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text(stringResource(R.string.nav_logs), style = MaterialTheme.typography.titleLarge)
        OutlinedTextField(
            value = query,
            onValueChange = { query = it },
            modifier = Modifier.fillMaxWidth(),
            leadingIcon = { Icon(Icons.Filled.Search, contentDescription = null) },
            label = { Text(stringResource(R.string.logs_search)) }
        )
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            AssistChip(onClick = { newestFirst = !newestFirst }, label = { Text(if (newestFirst) stringResource(R.string.logs_sort_newest) else stringResource(R.string.logs_sort_oldest)) })
            AssistChip(onClick = { statusFilter = (statusFilter + 1) % 3 }, label = { Text(listOf(stringResource(R.string.logs_filter_all), stringResource(R.string.logs_filter_success), stringResource(R.string.logs_filter_error))[statusFilter]) })
        }
        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            items(logs, key = { it.id }) { log ->
                Card(colors = CardDefaults.cardColors(containerColor = if (log.success) MaterialTheme.colorScheme.surfaceVariant else MaterialTheme.colorScheme.errorContainer)) {
                    Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Text(log.dataType ?: log.syncType ?: stringResource(R.string.nav_logs), style = MaterialTheme.typography.titleSmall)
                        Text(log.url, style = MaterialTheme.typography.bodyMedium)
                        Text("${formatLogTime(log.timestamp)} · ${log.statusCode?.let { "HTTP $it" } ?: if (log.success) "OK" else "ERR"}", color = MaterialTheme.colorScheme.onSurfaceVariant)
                        log.errorMessage?.takeIf { it.isNotBlank() }?.let { Text(it, color = MaterialTheme.colorScheme.error) }
                    }
                }
            }
        }
    }
}
private fun formatLogTime(timestamp: Long): String = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()).format(Date(timestamp))
