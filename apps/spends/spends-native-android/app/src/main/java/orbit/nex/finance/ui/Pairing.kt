package orbit.nex.finance.ui

import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CloudDone
import androidx.compose.material.icons.filled.CloudOff
import androidx.compose.material.icons.filled.LinkOff
import androidx.compose.material.icons.filled.PersonOff
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.WifiOff
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.journeyapps.barcodescanner.ScanContract
import com.journeyapps.barcodescanner.ScanOptions
import orbit.nex.finance.AppState
import orbit.nex.finance.R
import orbit.nex.finance.SpendsViewModel

@Composable
fun PairingScreen(initialLink: String, model: SpendsViewModel) {
    val context = LocalContext.current
    var link by remember(initialLink) { mutableStateOf(initialLink) }
    val scanner = rememberLauncherForActivityResult(ScanContract()) { result ->
        result.contents?.takeIf { it.isNotBlank() }?.let { model.pair(it, "") {} }
    }
    LaunchedEffect(initialLink) { if (initialLink.contains("token=")) model.pair(initialLink, "") {} }
    AppBackground {
        Column(Modifier.fillMaxSize().padding(20.dp), verticalArrangement = Arrangement.Center) {
            Card(colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                    Surface(modifier = Modifier.size(56.dp), shape = CircleShape, color = MaterialTheme.colorScheme.errorContainer) {
                        Icon(Icons.Filled.CloudOff, null, Modifier.padding(12.dp), tint = Color(0xFFFFC107))
                    }
                    Text(stringResource(R.string.pair_title), style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.onSurface)
                    Text(stringResource(R.string.pair_desc), color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Button({
                        scanner.launch(ScanOptions().apply {
                            setDesiredBarcodeFormats(ScanOptions.QR_CODE)
                            setPrompt(context.getString(R.string.pair_scan_prompt))
                            setBeepEnabled(false)
                            setOrientationLocked(true)
                        })
                    }, Modifier.fillMaxWidth()) {
                        Icon(Icons.Filled.QrCodeScanner, null)
                        Text(stringResource(R.string.pair_scan), Modifier.padding(start = 8.dp))
                    }
                    OutlinedTextField(link, { link = it }, Modifier.fillMaxWidth(), label = { Text(stringResource(R.string.pair_link_label)) })
                    Button({ model.pair(link, "") {} }, Modifier.fillMaxWidth()) { Text(stringResource(R.string.pair_connect)) }
                }
            }
        }
    }
}

@Composable
fun ConnectionCard(state: AppState, model: SpendsViewModel) {
    var testing by remember { mutableStateOf(false) }
    var ok by remember { mutableStateOf<Boolean?>(null) }
    Card(colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                if (state.session.imageUrl.isNotBlank()) RemotePhoto(state.session.imageUrl, state.session.name.take(1), round = true, size = 56.dp, person = true)
                else Icon(Icons.Filled.PersonOff, null, Modifier.size(56.dp), tint = MaterialTheme.colorScheme.onSurfaceVariant)
                Column(Modifier.weight(1f)) {
                    Text(state.session.name.ifBlank { "Spends" }, style = MaterialTheme.typography.titleMedium)
                    Text(state.session.email.ifBlank { state.session.endpoint }, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                Column(horizontalAlignment = Alignment.End) {
                    if (testing) CircularProgressIndicator(Modifier.size(24.dp))
                    else {
                        Icon(
                            if (ok == false) Icons.Filled.LinkOff else Icons.Filled.CloudDone,
                            null,
                            tint = when (ok) {
                                true -> MaterialTheme.colorScheme.primary
                                false -> MaterialTheme.colorScheme.error
                                else -> MaterialTheme.colorScheme.onSurfaceVariant
                            },
                        )
                        Text(
                            when (ok) {
                                true -> stringResource(R.string.pair_test_ok)
                                false -> stringResource(R.string.pair_test_fail)
                                else -> stringResource(R.string.pair_test)
                            },
                            style = MaterialTheme.typography.labelMedium,
                        )
                    }
                }
            }
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedButton(
                    onClick = {
                        ok = null
                        testing = true
                        model.testConnection { reachable ->
                            ok = reachable
                            testing = false
                        }
                    },
                    modifier = Modifier.weight(1f),
                    enabled = !testing,
                ) {
                    Icon(Icons.Filled.Refresh, null, Modifier.padding(end = 8.dp))
                    Text(stringResource(R.string.pair_test))
                }
                Button(
                    onClick = { model.logout() },
                    modifier = Modifier.weight(1f),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = MaterialTheme.colorScheme.errorContainer,
                        contentColor = MaterialTheme.colorScheme.onErrorContainer,
                    ),
                ) {
                    Text(stringResource(R.string.pair_logout))
                }
            }
        }
    }
}

/**
 * Compact, non-blocking banner shown while the paired server is unreachable.
 * The rest of the app stays fully usable from local storage.
 */
@Composable
fun OfflineBanner(pending: Int, onRetry: () -> Unit) {
    Card(
        Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 6.dp),
        RoundedCornerShape(18.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.errorContainer),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            Icon(
                Icons.Filled.WifiOff,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.onErrorContainer,
                modifier = Modifier.size(22.dp),
            )
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(
                    stringResource(R.string.offline_banner_title),
                    style = MaterialTheme.typography.titleSmall,
                    color = MaterialTheme.colorScheme.onErrorContainer,
                )
                Text(
                    stringResource(R.string.offline_banner_text),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onErrorContainer.copy(alpha = 0.85f),
                )
                if (pending > 0) {
                    Text(
                        stringResource(R.string.offline_pending, pending),
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onErrorContainer,
                    )
                }
            }
            TextButton(onClick = onRetry) {
                Text(stringResource(R.string.offline_retry), color = MaterialTheme.colorScheme.onErrorContainer)
            }
        }
    }
}

/**
 * Full-screen connection failure state. It is used for the unpaired pairing
 * flow; a paired account keeps the normal UI and shows [OfflineBanner] instead.
 */
@Composable
fun OfflineState(
    message: String,
    actionLabel: String? = null,
    onAction: (() -> Unit)? = null,
    showRetry: Boolean = true,
    onRetry: (() -> Unit)? = null,
) {
    Box(Modifier.fillMaxSize().padding(24.dp), contentAlignment = Alignment.Center) {
        Card(
            modifier = Modifier.fillMaxWidth().widthIn(max = 480.dp),
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.surfaceContainerHighest,
                contentColor = MaterialTheme.colorScheme.onSurface,
            ),
            shape = RoundedCornerShape(28.dp),
            elevation = CardDefaults.cardElevation(defaultElevation = 8.dp),
        ) {
            Column(
                modifier = Modifier.padding(28.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(18.dp),
            ) {
                Surface(
                    modifier = Modifier.size(92.dp),
                    shape = CircleShape,
                    color = MaterialTheme.colorScheme.errorContainer,
                    tonalElevation = 4.dp,
                ) {
                    Icon(
                        Icons.Filled.WifiOff,
                        contentDescription = null,
                        modifier = Modifier.fillMaxSize().padding(22.dp),
                        tint = MaterialTheme.colorScheme.onErrorContainer,
                    )
                }
                Text(
                    text = stringResource(R.string.offline_title),
                    style = MaterialTheme.typography.headlineSmall.copy(fontWeight = FontWeight.Bold),
                    color = MaterialTheme.colorScheme.onSurface,
                    textAlign = TextAlign.Center,
                )
                Text(
                    text = message,
                    style = MaterialTheme.typography.bodyLarge,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    textAlign = TextAlign.Center,
                )
                Column(Modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    if (actionLabel != null && onAction != null) {
                        Button(onClick = onAction, modifier = Modifier.fillMaxWidth()) {
                            Text(actionLabel, style = MaterialTheme.typography.labelLarge)
                        }
                    }
                    if (showRetry && onRetry != null) {
                        OutlinedButton(onClick = onRetry, modifier = Modifier.fillMaxWidth()) {
                            Icon(Icons.Filled.Refresh, null, Modifier.padding(end = 8.dp))
                            Text(stringResource(R.string.offline_retry))
                        }
                    }
                    Text(
                        text = stringResource(R.string.offline_help),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.75f),
                        textAlign = TextAlign.Center,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            }
        }
    }
}
