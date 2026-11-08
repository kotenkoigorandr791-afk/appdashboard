package orbit.nex.finance

import android.content.Context
import android.net.Uri
import android.os.Bundle
import androidx.appcompat.app.AppCompatActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.itemsIndexed
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material.icons.outlined.ErrorOutline
import androidx.compose.material.icons.outlined.ReceiptLong
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
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
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.contentOrNull
import kotlinx.serialization.json.doubleOrNull
import kotlinx.serialization.json.intOrNull
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import orbit.nex.finance.data.Appearance
import orbit.nex.finance.data.Session
import orbit.nex.finance.data.Snapshot
import orbit.nex.finance.ui.AppBackground
import orbit.nex.finance.ui.RemotePhoto
import orbit.nex.finance.ui.SpendsTheme
import orbit.nex.finance.ui.money
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.util.Locale

class OcrActivity : AppCompatActivity() {
    override fun attachBaseContext(newBase: Context) {
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

private fun prettyDate(raw: String): String = runCatching {
    LocalDate.parse(raw).format(DateTimeFormatter.ofPattern("d MMM", Locale.getDefault()))
}.getOrDefault(raw)

private fun stateLabel(state: String): String = when (state) {
    "queued" -> "Waiting for the server"
    "processing" -> "Reading your receipts"
    "done" -> "Finished"
    "error" -> "Failed"
    "canceled" -> "Canceled"
    else -> state.replaceFirstChar { it.titlecase(Locale.getDefault()) }
}

private fun JsonObject.text(name: String): String = this[name]?.jsonPrimitive?.contentOrNull.orEmpty()
private fun JsonObject.number(name: String): Double = this[name]?.jsonPrimitive?.doubleOrNull ?: 0.0
private fun JsonObject.integer(name: String): Int = this[name]?.jsonPrimitive?.intOrNull ?: 0

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
    val percent = job?.get("progress")?.jsonObject?.number("percent") ?: 0.0
    val total = job?.get("progress")?.jsonObject?.integer("total") ?: 0
    val processed = job?.get("progress")?.jsonObject?.integer("processed") ?: 0
    val added = job?.integer("added_count") ?: 0
    val duplicates = job?.integer("duplicate_count") ?: 0
    val unresolved = job?.get("unresolved")?.jsonArray.orEmpty()
    val records = job?.get("records")?.jsonArray.orEmpty()

    // The worker reports every charge twice (once per receipt row, once in the
    // merged per-store list). Keep the detailed rows and drop exact repeats so
    // the screen shows one card per purchase.
    val allRecords = records.map { it.jsonObject }
    val detailed = allRecords.filter { it.text("status").isNotBlank() }
    val shownRecords = (detailed.ifEmpty { allRecords }).distinctBy { row ->
        "${row.text("date")}|${row.text("store")}|${row.number("amount")}"
    }

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

    // The worker writes the recognised charges on the server; pull them straight
    // away so Home shows them without waiting for the next background refresh.
    var refreshedJob by remember { mutableStateOf("") }
    LaunchedEffect(state, jobId, session.token) {
        if (state == "done" && jobId.isNotBlank() && session.token.isNotBlank() && refreshedJob != jobId) {
            refreshedJob = jobId
            runCatching { app.store.saveSnapshot(app.api.pull(session, Snapshot())) }
        }
    }

    fun photoFor(store: String): String =
        snapshot.stores.firstOrNull { it.name.equals(store, ignoreCase = true) }?.photoUrl.orEmpty()

    Scaffold(
        containerColor = Color.Transparent,
        topBar = {
            TopAppBar(
                title = { Text("Receipt OCR") },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back") } },
            )
        },
    ) { padding ->
        Column(
            Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState())
                .navigationBarsPadding()
                .padding(horizontal = 20.dp, vertical = 16.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            // Intro card gives the screen a frame instead of a bare button stack.
            Card(Modifier.fillMaxWidth(), RoundedCornerShape(22.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
                Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    Box(
                        Modifier.size(44.dp).clip(RoundedCornerShape(14.dp)).background(MaterialTheme.colorScheme.primaryContainer),
                        contentAlignment = Alignment.Center,
                    ) { Icon(Icons.Outlined.ReceiptLong, null, tint = MaterialTheme.colorScheme.onPrimaryContainer) }
                    Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                        Text("Scan receipts", style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.onSurface)
                        Text(
                            "Pick screenshots from your wallet or a photo of a receipt. The server reads the store, date and amount.",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
            }

            Button({ picker.launch("image/*") }, Modifier.fillMaxWidth().height(50.dp), shape = RoundedCornerShape(16.dp)) {
                Text(if (files.isEmpty()) "Choose screenshots" else "${files.size} selected · choose more")
            }

            if (files.isNotEmpty()) {
                LazyVerticalGrid(
                    GridCells.Fixed(3),
                    Modifier.fillMaxWidth().height(if (files.size > 3) 176.dp else 88.dp),
                    verticalArrangement = Arrangement.spacedBy(6.dp),
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                ) {
                    itemsIndexed(files) { index, uri ->
                        Box(Modifier.fillMaxWidth().height(88.dp).clip(RoundedCornerShape(14.dp)).background(MaterialTheme.colorScheme.surfaceVariant)) {
                            AsyncImage(uri, "photo ${index + 1}", Modifier.matchParentSize(), contentScale = ContentScale.Crop)
                            Box(
                                Modifier.align(Alignment.TopEnd).padding(4.dp).size(20.dp)
                                    .clip(CircleShape).background(Color.Black.copy(alpha = 0.55f))
                                    .clickable { files = files.toMutableList().also { it.removeAt(index) } },
                                contentAlignment = Alignment.Center,
                            ) { Icon(Icons.Outlined.Close, "Remove", tint = Color.White, modifier = Modifier.size(14.dp)) }
                        }
                    }
                }
            }

            Button(
                {
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
                },
                Modifier.fillMaxWidth().height(50.dp),
                enabled = !busy && files.isNotEmpty(),
                shape = RoundedCornerShape(16.dp),
            ) {
                if (busy) {
                    CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary)
                    Text("Uploading…", Modifier.padding(start = 10.dp))
                } else {
                    Text("Send to server")
                }
            }

            // Status lives in its own bordered card; the internal job id is never shown.
            if (state.isNotBlank()) {
                Card(
                    Modifier.fillMaxWidth().border(1.dp, MaterialTheme.colorScheme.outlineVariant, RoundedCornerShape(20.dp)),
                    RoundedCornerShape(20.dp),
                    colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface),
                ) {
                    Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                            Box(
                                Modifier.size(40.dp).clip(RoundedCornerShape(14.dp)).background(MaterialTheme.colorScheme.primaryContainer),
                                contentAlignment = Alignment.Center,
                            ) {
                                when {
                                    state == "queued" || state == "processing" -> CircularProgressIndicator(Modifier.size(20.dp), strokeWidth = 2.dp)
                                    state == "done" -> Icon(Icons.Outlined.CheckCircle, null, tint = MaterialTheme.colorScheme.onPrimaryContainer, modifier = Modifier.size(22.dp))
                                    else -> Icon(Icons.Outlined.ErrorOutline, null, tint = MaterialTheme.colorScheme.error, modifier = Modifier.size(22.dp))
                                }
                            }
                            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                                Text(stateLabel(state), style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.onSurface)
                                Text(
                                    when {
                                        total > 0 && state != "done" -> "$processed of $total files"
                                        added > 0 || duplicates > 0 -> "$added added · $duplicates duplicates"
                                        else -> "No charges recognised yet"
                                    },
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                            Text(
                                "${percent.toInt().coerceIn(0, 100)}%",
                                style = MaterialTheme.typography.labelLarge,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                        LinearProgressIndicator({ (percent / 100.0).toFloat().coerceIn(0f, 1f) }, Modifier.fillMaxWidth())
                        if (state == "error") {
                            val reason = job?.text("message").orEmpty()
                            Text(
                                reason.ifBlank { "OCR failed on the server" },
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.error,
                            )
                        }
                    }
                }
            }

            if (message.isNotBlank()) {
                Card(Modifier.fillMaxWidth(), RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.errorContainer)) {
                    Text(message, Modifier.padding(14.dp), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onErrorContainer)
                }
            }

            if (shownRecords.isNotEmpty()) {
                Row(Modifier.fillMaxWidth().padding(top = 4.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("Recognised charges", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onBackground)
                    Text("${shownRecords.size}", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                shownRecords.forEach { row ->
                    val store = row.text("store").ifBlank { row.text("merchant_raw") }
                    val raw = row.text("merchant_raw")
                    Card(Modifier.fillMaxWidth(), RoundedCornerShape(20.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
                        Row(
                            Modifier.fillMaxWidth().padding(14.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(12.dp),
                        ) {
                            RemotePhoto(photoFor(store), store.take(1).uppercase(Locale.getDefault()), round = false, size = 46.dp)
                            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                                Text(
                                    store.ifBlank { "Unknown store" },
                                    style = MaterialTheme.typography.titleMedium,
                                    color = MaterialTheme.colorScheme.onSurface,
                                    maxLines = 1,
                                    overflow = TextOverflow.Ellipsis,
                                )
                                Text(
                                    prettyDate(row.text("date")),
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                                if (raw.isNotBlank() && !raw.equals(store, ignoreCase = true)) {
                                    Text(
                                        raw,
                                        style = MaterialTheme.typography.bodySmall,
                                        color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.55f),
                                        maxLines = 1,
                                        overflow = TextOverflow.Ellipsis,
                                    )
                                }
                            }
                            Text(
                                money(row.number("amount"), row.text("currency").ifBlank { snapshot.currency }),
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.SemiBold,
                                color = MaterialTheme.colorScheme.onSurface,
                            )
                        }
                    }
                }
            }

            if (unresolved.isNotEmpty()) {
                Text("Needs a store", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onBackground)
            }
            unresolved.forEach { row ->
                val o = row.jsonObject
                val raw = o.text("merchant_raw")
                if (raw.isBlank()) return@forEach
                var open by remember(raw) { mutableStateOf(false) }
                val selected = map[raw].orEmpty()
                Card(Modifier.fillMaxWidth(), RoundedCornerShape(20.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surfaceVariant)) {
                    Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.SpaceBetween) {
                            Column(Modifier.weight(1f)) {
                                Text(raw, style = MaterialTheme.typography.titleSmall, color = MaterialTheme.colorScheme.onSurface, maxLines = 1, overflow = TextOverflow.Ellipsis)
                                Text(
                                    "${prettyDate(o.text("date"))} · ${o.number("amount")} ${o.text("currency")}",
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                            RemotePhoto(photoFor(selected), selected.take(1).uppercase(Locale.getDefault()), round = false, size = 36.dp)
                        }
                        ExposedDropdownMenuBox(open, { open = it }) {
                            OutlinedTextField(
                                selected.ifBlank { "Map to store" },
                                {},
                                Modifier.fillMaxWidth().menuAnchor(),
                                readOnly = true,
                                trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(open) },
                                shape = RoundedCornerShape(14.dp),
                            )
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
                Button(
                    {
                        busy = true
                        scope.launch {
                            runCatching {
                                app.api.ocrResolve(session, jobId, map)
                                status = app.api.ocrStatus(session, jobId)
                                val left = status?.get("job")?.jsonObject?.get("unresolved")?.jsonArray.orEmpty()
                                if (left.isEmpty()) {
                                    app.api.ocrFinalize(session, jobId)
                                    runCatching { app.store.saveSnapshot(app.api.pull(session, Snapshot())) }
                                    message = "Saved. New charges are now in your ledger."
                                }
                            }.onFailure { message = it.message.orEmpty() }
                            busy = false
                        }
                    },
                    Modifier.fillMaxWidth().height(50.dp),
                    enabled = map.isNotEmpty() && !busy,
                    shape = RoundedCornerShape(16.dp),
                ) { Text("Apply mapping") }
            }

            if (state == "done" && unresolved.isEmpty() && shownRecords.isNotEmpty()) {
                Card(Modifier.fillMaxWidth(), RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surfaceVariant)) {
                    Text(
                        "OCR finished. The recognised charges were added to your ledger.",
                        Modifier.padding(14.dp),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
        }
    }
}
