package xyz.serverkherson.spends

import android.content.Context
import android.net.Uri
import android.os.Bundle
import androidx.appcompat.app.AppCompatActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExposedDropdownMenuBox
import androidx.compose.material3.ExposedDropdownMenuDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.contentOrNull
import kotlinx.serialization.json.doubleOrNull
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import xyz.serverkherson.spends.data.Appearance
import xyz.serverkherson.spends.data.Session
import xyz.serverkherson.spends.data.Snapshot
import xyz.serverkherson.spends.ui.AppBackground
import xyz.serverkherson.spends.ui.SpendsTheme

class OcrActivity : AppCompatActivity() {
    override fun attachBaseContext(newBase: android.content.Context) {
        super.attachBaseContext(AppLocale.wrap(newBase))
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        val app = application as SpendsApplication
        setContent {
            val appearance by app.store.appearance.collectAsState(Appearance())
            SpendsTheme(appearance) { AppBackground { OcrScreen(app) { finish() } } }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun OcrScreen(app: SpendsApplication, onBack: () -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val session by app.store.session.collectAsState(Session())
    val snapshot by app.store.snapshot.collectAsState(Snapshot())
    var files by remember { mutableStateOf<List<Uri>>(emptyList()) }
    var jobId by remember { mutableStateOf("") }
    var status by remember { mutableStateOf<JsonObject?>(null) }
    var message by remember { mutableStateOf("") }
    var busy by remember { mutableStateOf(false) }
    var map by remember { mutableStateOf<Map<String, String>>(emptyMap()) }
    val picker = rememberLauncherForActivityResult(ActivityResultContracts.GetMultipleContents()) { files = it }
    val job = status?.get("job")?.jsonObject
    val state = job?.get("state")?.jsonPrimitive?.contentOrNull.orEmpty()
    val percent = job?.get("progress")?.jsonObject?.get("percent")?.jsonPrimitive?.doubleOrNull ?: 0.0
    val unresolved = job?.get("unresolved")?.jsonArray.orEmpty()
    val records = job?.get("records")?.jsonArray.orEmpty()
    LaunchedEffect(jobId, session.token) {
        var running = jobId.isNotBlank()
        while (running) {
            runCatching {
                val root = app.api.ocrStatus(session, jobId)
                status = root
                val st = root["job"]?.jsonObject?.get("state")?.jsonPrimitive?.contentOrNull.orEmpty()
                if (st in listOf("done", "error", "canceled")) running = false
            }.onFailure { message = it.message.orEmpty(); running = false }
            if (running) delay(1500)
        }
    }
    Scaffold(
        containerColor = Color.Transparent,
        topBar = { TopAppBar(title = { Text("Receipt OCR") }, navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back") } }) },
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding).padding(20.dp).verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Button({ picker.launch("image/*") }, Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp)) { Text(if (files.isEmpty()) "Choose screenshots" else "${files.size} files selected") }
            Button({
                if (files.isEmpty() || session.token.isBlank()) { message = "Choose files first"; return@Button }
                busy = true; message = ""
                scope.launch {
                    runCatching {
                        val payloads = files.mapIndexed { i, uri ->
                            val bytes = context.contentResolver.openInputStream(uri)?.use { it.readBytes() } ?: ByteArray(0)
                            "shot-$i.jpg" to bytes
                        }.filter { it.second.isNotEmpty() }
                        val root = app.api.ocrStart(session, payloads)
                        jobId = root["job_id"]?.jsonPrimitive?.contentOrNull.orEmpty()
                        if (jobId.isBlank()) error(root["message"]?.jsonPrimitive?.contentOrNull ?: "OCR did not start")
                        status = root
                    }.onFailure { message = it.message.orEmpty() }
                    busy = false
                }
            }, Modifier.fillMaxWidth(), enabled = !busy && files.isNotEmpty(), shape = RoundedCornerShape(16.dp)) { Text(if (busy) "Uploading…" else "Send to server") }
            if (jobId.isNotBlank()) {
                Text("Job $jobId · ${state.ifBlank { "queued" }}")
                LinearProgressIndicator({ (percent / 100.0).toFloat().coerceIn(0f, 1f) }, Modifier.fillMaxWidth())
            }
            if (message.isNotBlank()) Text(message, color = MaterialTheme.colorScheme.error)
            records.forEach { row ->
                val o = row.jsonObject
                Card(Modifier.fillMaxWidth(), RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
                    Column(Modifier.padding(14.dp)) {
                        Text(o["store"]?.jsonPrimitive?.contentOrNull ?: "store", style = MaterialTheme.typography.titleMedium)
                        Text("${o["date"]?.jsonPrimitive?.contentOrNull.orEmpty()} · ${o["amount"]?.jsonPrimitive?.contentOrNull.orEmpty()}")
                    }
                }
            }
            unresolved.forEach { row ->
                val o = row.jsonObject
                val raw = o["merchant_raw"]?.jsonPrimitive?.contentOrNull.orEmpty()
                if (raw.isBlank()) return@forEach
                var open by remember(raw) { mutableStateOf(false) }
                val selected = map[raw].orEmpty()
                Card(Modifier.fillMaxWidth(), RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surfaceVariant)) {
                    Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(raw, style = MaterialTheme.typography.titleSmall)
                        Text("${o["date"]?.jsonPrimitive?.contentOrNull.orEmpty()} · ${o["amount"]?.jsonPrimitive?.contentOrNull.orEmpty()} ${o["currency"]?.jsonPrimitive?.contentOrNull.orEmpty()}")
                        ExposedDropdownMenuBox(open, { open = it }) {
                            OutlinedTextField(selected.ifBlank { "Map to store" }, {}, Modifier.fillMaxWidth().menuAnchor(), readOnly = true, trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(open) })
                            ExposedDropdownMenu(open, { open = false }) {
                                storesByLastUse(snapshot).forEach { store ->
                                    DropdownMenuItem({ Text(store.name) }, { map = map + (raw to store.name); open = false })
                                }
                            }
                        }
                    }
                }
            }
            if (unresolved.isNotEmpty()) {
                Button({
                    busy = true
                    scope.launch {
                        runCatching {
                            app.api.ocrResolve(session, jobId, map)
                            status = app.api.ocrStatus(session, jobId)
                            val left = status?.get("job")?.jsonObject?.get("unresolved")?.jsonArray.orEmpty()
                            if (left.isEmpty()) app.api.ocrFinalize(session, jobId)
                        }.onFailure { message = it.message.orEmpty() }
                        busy = false
                    }
                }, Modifier.fillMaxWidth(), enabled = map.isNotEmpty() && !busy, shape = RoundedCornerShape(16.dp)) { Text("Apply mapping") }
            }
            if (state == "done" && unresolved.isEmpty()) Text("OCR finished. Pull to refresh on Home to see new charges.")
        }
    }
}
