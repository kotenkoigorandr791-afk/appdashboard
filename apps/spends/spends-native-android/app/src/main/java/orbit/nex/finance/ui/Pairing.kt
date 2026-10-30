package orbit.nex.finance.ui

import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CloudDone
import androidx.compose.material.icons.filled.CloudOff
import androidx.compose.material.icons.filled.LinkOff
import androidx.compose.material.icons.filled.PersonOff
import androidx.compose.material.icons.filled.QrCodeScanner
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
            }
            Button({
                testing = true
                ok = null
                model.testConnection { success -> testing = false; ok = success }
            }, Modifier.fillMaxWidth(), enabled = !testing, colors = ButtonDefaults.buttonColors(containerColor = when (ok) {
                true -> Color(0xFF66BB6A)
                false -> Color(0xFF9B1C31)
                null -> MaterialTheme.colorScheme.primary
            })) {
                if (testing) {
                    CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary)
                    Spacer(Modifier.size(8.dp))
                    Text(stringResource(R.string.pair_testing))
                } else {
                    Icon(Icons.Filled.CloudDone, null)
                    Text(stringResource(R.string.pair_test), Modifier.padding(start = 8.dp))
                }
            }
            OutlinedButton(model::logout, Modifier.fillMaxWidth()) {
                Icon(Icons.Filled.LinkOff, null)
                Text(stringResource(R.string.disconnect), Modifier.padding(start = 8.dp))
            }
        }
    }
}
