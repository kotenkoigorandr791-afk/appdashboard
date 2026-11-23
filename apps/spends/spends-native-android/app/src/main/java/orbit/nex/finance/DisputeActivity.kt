package orbit.nex.finance
import orbit.nex.finance.ui.AppIcons

import android.app.Activity
import android.content.Context
import android.content.Intent
import android.os.Bundle
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.rememberDatePickerState
import orbit.nex.finance.ui.AppDatePickerDialog
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import orbit.nex.finance.data.Appearance
import orbit.nex.finance.data.Dispute
import orbit.nex.finance.data.OutboxItem
import orbit.nex.finance.data.OutboxSync
import orbit.nex.finance.data.Session
import orbit.nex.finance.data.Snapshot
import orbit.nex.finance.data.walletCaptureId
import orbit.nex.finance.ui.AppBackground
import orbit.nex.finance.ui.DetailScreen
import orbit.nex.finance.ui.SpendsTheme
import java.time.LocalDate
import java.time.ZoneOffset
import java.time.Instant

/**
 * Shared resolver for charges that could not be booked automatically —
 * Google Wallet notifications and OCR receipts both land here.
 */
class DisputeActivity : AppCompatActivity() {
    override fun attachBaseContext(newBase: Context) {
        super.attachBaseContext(AppLocale.wrap(newBase))
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        val app = application as SpendsApplication
        setContent {
            val appearance by app.store.appearance.collectAsState(Appearance())
            val snapshot by app.store.snapshot.collectAsState(Snapshot())
            val session by app.store.session.collectAsState(Session())
            val extras by app.store.extras.collectAsState(orbit.nex.finance.data.LocalExtras())
            SpendsTheme(appearance) {
                AppBackground {
                    DisputeScreen(
                        app = app,
                        session = session,
                        snapshot = snapshot,
                        disputes = extras.disputes,
                        onBack = { finish() },
                    )
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DisputeScreen(
    app: SpendsApplication,
    session: Session,
    snapshot: Snapshot,
    disputes: List<Dispute>,
    onBack: () -> Unit,
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val chosen = remember { mutableStateMapOf<String, String>() }
    val amounts = remember { mutableStateMapOf<String, String>() }
    var editingId by remember { mutableStateOf<String?>(null) }
    var message by remember { mutableStateOf("") }
    var busy by remember { mutableStateOf(false) }
    var filterDate by remember { mutableStateOf<String?>(null) }
    var pickFilterDate by remember { mutableStateOf(false) }
    val picker = rememberLauncherForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
        if (result.resultCode == Activity.RESULT_OK) {
            val name = result.data?.getStringExtra(PickStoreActivity.EXTRA_STORE).orEmpty()
            val id = editingId
            if (id != null && name.isNotBlank()) chosen[id] = name
        }
        editingId = null
    }

    // Convenience: the nearest known store name for the misread merchant.
    fun guess(raw: String): String =
        snapshot.stores.firstOrNull { it.name.equals(raw, ignoreCase = true) }?.name
            ?: snapshot.stores.firstOrNull {
                val a = it.name.lowercase().take(4)
                a.isNotBlank() && raw.lowercase().contains(a)
            }?.name.orEmpty()

    DetailScreen(stringResource(R.string.disputes_title), onBack, insetTop = true) {
        Column(
            Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Text(stringResource(R.string.disputes_desc), color = MaterialTheme.colorScheme.onSurfaceVariant)
            Card(Modifier.fillMaxWidth().clickable { pickFilterDate = true }, RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surfaceVariant)) {
                Row(Modifier.fillMaxWidth().padding(14.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.SpaceBetween) {
                    Text(filterDate ?: stringResource(R.string.disputes_all_dates), color = MaterialTheme.colorScheme.onSurface)
                    Icon(AppIcons.CalendarMonth, stringResource(R.string.date_label))
                }
            }
            val visibleDisputes = disputes.filter { filterDate == null || it.date == filterDate }
            if (visibleDisputes.isEmpty()) {
                Card(Modifier.fillMaxWidth(), RoundedCornerShape(20.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
                    Column(Modifier.fillMaxWidth().padding(20.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Text(stringResource(R.string.disputes_empty_title), fontWeight = FontWeight.SemiBold)
                        Text(stringResource(R.string.disputes_empty_body), color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
            }
            visibleDisputes.forEach { dispute ->
                val storeName = chosen[dispute.id] ?: guess(dispute.rawName)
                val amountText = amounts[dispute.id] ?: dispute.amount.takeIf { it > 0 }?.toString().orEmpty()
                Card(Modifier.fillMaxWidth(), RoundedCornerShape(22.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surface)) {
                    Column(Modifier.fillMaxWidth().padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Box(
                                Modifier.size(36.dp).background(
                                    Color(0xFFFFE082).copy(alpha = 0.45f),
                                    RoundedCornerShape(12.dp),
                                ),
                                contentAlignment = Alignment.Center,
                            ) { Icon(AppIcons.WarningAmber, null, tint = Color(0xFF8D6E00)) }
                            Spacer(Modifier.width(10.dp))
                            Column(Modifier.weight(1f)) {
                                Text(
                                    stringResource(
                                        if (dispute.source == "ocr") R.string.disputes_source_ocr else R.string.disputes_source_wallet,
                                    ),
                                    style = MaterialTheme.typography.labelLarge,
                                    color = MaterialTheme.colorScheme.primary,
                                )
                                Text(
                                    stringResource(
                                        if (dispute.reason == "unreadable") R.string.disputes_reason_unreadable else R.string.disputes_reason_store,
                                    ),
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                            if (dispute.amount > 0) {
                                Text(
                                    "${dispute.amount} ${dispute.currency}",
                                    fontWeight = FontWeight.Bold,
                                    color = MaterialTheme.colorScheme.onSurface,
                                )
                            }
                        }
                        Text(
                            dispute.rawName.ifBlank { dispute.raw },
                            style = MaterialTheme.typography.titleMedium,
                            color = MaterialTheme.colorScheme.onSurface,
                            maxLines = 2,
                            overflow = TextOverflow.Ellipsis,
                        )
                        if (dispute.raw.isNotBlank() && dispute.raw != dispute.rawName) {
                            Text(
                                dispute.raw,
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.65f),
                                maxLines = 3,
                                overflow = TextOverflow.Ellipsis,
                            )
                        }
                        Card(
                            Modifier.fillMaxWidth().clickable {
                                editingId = dispute.id
                                picker.launch(Intent(context, PickStoreActivity::class.java))
                            },
                            RoundedCornerShape(16.dp),
                            colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surfaceVariant),
                        ) {
                            Row(
                                Modifier.fillMaxWidth().padding(14.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                Text(
                                    storeName.ifBlank { stringResource(R.string.disputes_pick_store) },
                                    fontWeight = FontWeight.SemiBold,
                                    color = MaterialTheme.colorScheme.onSurface,
                                )
                                Icon(AppIcons.Storefront, null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                        }
                        OutlinedTextField(
                            amountText,
                            { input ->
                                val clean = input.replace(',', '.').filter { it.isDigit() || it == '.' }
                                val firstDot = clean.indexOf('.')
                                amounts[dispute.id] = if (firstDot < 0) clean
                                else clean.substring(0, firstDot + 1) + clean.substring(firstDot + 1).replace(".", "")
                            },
                            Modifier.fillMaxWidth(),
                            label = { Text(stringResource(R.string.amount_label)) },
                            shape = RoundedCornerShape(14.dp),
                            singleLine = true,
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                        )
                        val valid = storeName.isNotBlank() && (amountText.replace(',', '.').toDoubleOrNull() ?: 0.0) > 0.0
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            Button(
                                {
                                    busy = true
                                    scope.launch {
                        val amount = amountText.replace(',', '.').toDoubleOrNull() ?: 0.0
                                        val captureDate = dispute.date.ifBlank { LocalDate.now().toString() }
                                        val captureSource = if (dispute.source == "ocr") "ocr" else "notification"
                                        OutboxSync.enqueue(
                                            app.store,
                                            OutboxItem(
                                                id = if (captureSource == "notification") walletCaptureId(captureDate, storeName, amount, captureSource) else java.util.UUID.randomUUID().toString(),
                                                kind = "expense",
                                                date = captureDate,
                                                store = storeName,
                                                amount = amount,
                                                source = captureSource,
                                                raw = if (dispute.source == "ocr") dispute.rawName else "",
                                            ),
                                        )
                                        val ok = runCatching { OutboxSync.flush(app.store, app.api) }.getOrDefault(false)
                                        removeDispute(app, dispute.id)
                                        runCatching { app.store.saveSnapshot(app.api.pull(session)) }
                                        message = context.getString(if (ok) R.string.disputes_saved else R.string.wallet_saved)
                                        busy = false
                                    }
                                },
                                Modifier.weight(1f),
                                shape = RoundedCornerShape(14.dp),
                                enabled = valid && !busy,
                            ) { Text(stringResource(R.string.save)) }
                            Button(
                                {
                                    scope.launch {
                                        removeDispute(app, dispute.id)
                                        message = context.getString(R.string.disputes_discarded)
                                    }
                                },
                                Modifier.weight(1f),
                                shape = RoundedCornerShape(14.dp),
                                colors = ButtonDefaults.buttonColors(
                                    MaterialTheme.colorScheme.surfaceVariant,
                                    MaterialTheme.colorScheme.onSurface,
                                ),
                            ) { Text(stringResource(R.string.disputes_discard)) }
                        }
                    }
                }
            }
            if (message.isNotBlank()) {
                Card(Modifier.fillMaxWidth(), RoundedCornerShape(14.dp), colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surfaceVariant)) {
                    Text(message, Modifier.padding(14.dp), color = MaterialTheme.colorScheme.onSurface)
                }
            }
            if (disputes.isEmpty()) {
                TextButton(onBack, Modifier.fillMaxWidth()) { Text(stringResource(R.string.disputes_back)) }
            }
        }
    }
    if (pickFilterDate) {
        val picker = rememberDatePickerState(initialSelectedDateMillis = runCatching {
            LocalDate.parse(filterDate ?: LocalDate.now().toString()).atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli()
        }.getOrDefault(System.currentTimeMillis()))
        AppDatePickerDialog(
            initialDateMillis = picker.selectedDateMillis ?: System.currentTimeMillis(),
            onDismiss = { pickFilterDate = false },
            onConfirm = { ms -> filterDate = Instant.ofEpochMilli(ms).atZone(ZoneOffset.UTC).toLocalDate().toString() },
            dismissLabel = stringResource(R.string.disputes_all_dates),
        )
    }
}

/** Removes one dispute from local storage after it is resolved or discarded. */
private fun removeDispute(app: SpendsApplication, id: String) {
    runCatching {
        app.saveExtrasSync { extras -> extras.copy(disputes = extras.disputes.filterNot { it.id == id }) }
    }
}

private fun SpendsApplication.saveExtrasSync(transform: (orbit.nex.finance.data.LocalExtras) -> orbit.nex.finance.data.LocalExtras) {
    kotlinx.coroutines.runBlocking {
        store.saveExtras(transform(store.extrasNow()))
    }
}
