package orbit.nex.finance

import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.os.Bundle
import androidx.appcompat.app.AppCompatActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
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
import androidx.compose.material.icons.automirrored.filled.ArrowBack
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
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
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
import kotlinx.coroutines.launch
import orbit.nex.finance.data.Appearance
import orbit.nex.finance.R
import orbit.nex.finance.data.SyncLog
import orbit.nex.finance.ui.AppBackground
import orbit.nex.finance.ui.SpendsTheme
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

class LogsActivity : AppCompatActivity() {
    override fun attachBaseContext(newBase: android.content.Context) {
        super.attachBaseContext(AppLocale.wrap(newBase))
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        val app = application as SpendsApplication
        setContent {
            val appearance by app.store.appearance.collectAsState(Appearance())
            SpendsTheme(appearance) {
                AppBackground { LogsScreen(app) { finish() } }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun LogsScreen(app: SpendsApplication, onBack: () -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val allLogs by app.store.syncLogs.collectAsState(emptyList())
    var query by remember { mutableStateOf("") }
    var newestFirst by remember { mutableStateOf(true) }
    var statusFilter by remember { mutableIntStateOf(0) }
    var expandedId by remember { mutableStateOf<String?>(null) }
    val logs = remember(allLogs, query, newestFirst, statusFilter) {
        allLogs.asSequence()
            .filter { log ->
                (statusFilter == 0 || (statusFilter == 1 && log.success) || (statusFilter == 2 && !log.success)) &&
                    (query.isBlank() || listOfNotNull(log.url, log.errorMessage, log.payload, log.method).any { it.contains(query, ignoreCase = true) })
            }
            .sortedBy { it.timestamp }
            .let { if (newestFirst) it.toList().asReversed() else it.toList() }
    }
    Scaffold(
        containerColor = androidx.compose.ui.graphics.Color.Transparent,
        topBar = {
            TopAppBar(
                title = { Text(stringResource(R.string.logs), modifier = Modifier.fillMaxWidth().padding(end = 8.dp), textAlign = TextAlign.End) },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, stringResource(R.string.back)) } },
                actions = { if (allLogs.isNotEmpty()) TextButton({ scope.launch { app.store.clearLogs() } }) { Text(stringResource(R.string.logs_clear)) } },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding).padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Text(stringResource(R.string.sync_title), style = MaterialTheme.typography.titleLarge, modifier = Modifier.weight(1f))
                IconButton({ newestFirst = !newestFirst }) { Icon(if (newestFirst) Icons.Filled.ArrowDownward else Icons.Filled.ArrowUpward, null) }
                IconButton({ statusFilter = (statusFilter + 1) % 3 }) {
                    Icon(when (statusFilter) { 1 -> Icons.Filled.CheckCircle; 2 -> Icons.Filled.Error; else -> Icons.Filled.FilterList }, null)
                }
            }
            OutlinedTextField(query, { query = it }, Modifier.fillMaxWidth(), leadingIcon = { Icon(Icons.Filled.Search, null) }, placeholder = { Text(stringResource(R.string.search)) }, singleLine = true, shape = RoundedCornerShape(28.dp))
            if (logs.isEmpty()) {
                Box(Modifier.fillMaxWidth().weight(1f), contentAlignment = Alignment.Center) {
                    Text(stringResource(if (allLogs.isEmpty()) R.string.logs_empty else R.string.logs_no_matches), color = MaterialTheme.colorScheme.onSurfaceVariant, textAlign = TextAlign.Center)
                }
            } else {
                LazyColumn(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    items(logs, key = { it.id }) { log ->
                        val expanded = expandedId == log.id
                        Card(colors = CardDefaults.cardColors(containerColor = if (log.success) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.errorContainer)) {
                            Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                    Text("${log.method} · ${log.statusCode ?: if (log.success) "OK" else "ERR"}", style = MaterialTheme.typography.titleSmall, modifier = Modifier.weight(1f))
                                    IconButton({ expandedId = if (expanded) null else log.id }) { Icon(if (expanded) Icons.Filled.ExpandLess else Icons.Filled.ExpandMore, null) }
                                }
                                Text(log.url, style = MaterialTheme.typography.bodyMedium)
                                Text(SimpleDateFormat("MMM dd, yyyy HH:mm:ss", Locale.getDefault()).format(Date(log.timestamp)), color = MaterialTheme.colorScheme.onSurfaceVariant)
                                if (expanded) {
                                    val copy = listOfNotNull(log.errorMessage, log.payload).filter { it.isNotBlank() }.ifEmpty { listOf(log.url) }.joinToString("\n")
                                    Row(Modifier.fillMaxWidth()) {
                                        Surface(color = MaterialTheme.colorScheme.surfaceVariant, shape = RoundedCornerShape(8.dp), modifier = Modifier.weight(1f)) {
                                            Text(copy, Modifier.padding(8.dp), fontFamily = FontFamily.Monospace, fontSize = 12.sp)
                                        }
                                        IconButton({
                                            (context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager).setPrimaryClip(ClipData.newPlainText("Spends", copy))
                                        }) { Icon(Icons.Filled.ContentCopy, null) }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
