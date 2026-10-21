package com.appdashboard.planning.ui.screens

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.QrCodeScanner
import androidx.compose.material.icons.outlined.TaskAlt
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.appdashboard.planning.data.repo.PlanningRepository
import com.journeyapps.barcodescanner.ScanContract
import com.journeyapps.barcodescanner.ScanOptions
import kotlinx.coroutines.launch

@Composable
fun PairingScreen(
    repository: PlanningRepository,
    initialLink: String?,
    onPaired: () -> Unit,
) {
    var link by remember(initialLink) { mutableStateOf(initialLink.orEmpty()) }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()
    var autoTried by remember { mutableStateOf(false) }

    LaunchedEffect(initialLink) {
        val raw = initialLink?.trim().orEmpty()
        if (raw.isNotBlank() && !autoTried) {
            autoTried = true
            link = raw
            connect(repository, raw, { busy = it }, { error = it }, onPaired)
        }
    }

    val scanLauncher = rememberLauncherForActivityResult(ScanContract()) { result ->
        val raw = result.contents ?: return@rememberLauncherForActivityResult
        link = raw
        scope.launch { connect(repository, raw, { busy = it }, { error = it }, onPaired) }
    }

    Scaffold { padding ->
        Column(
            Modifier
                .fillMaxSize()
                .padding(padding)
                .padding(24.dp),
            verticalArrangement = Arrangement.Center,
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Icon(
                Icons.Outlined.TaskAlt,
                contentDescription = null,
                modifier = Modifier.size(56.dp),
                tint = MaterialTheme.colorScheme.primary,
            )
            Spacer(Modifier.height(12.dp))
            Text("Tasks", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(8.dp))
            Text(
                "Scan the QR code in Planning on the web, or paste the connection link.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = TextAlign.Center,
            )
            Spacer(Modifier.height(24.dp))
            OutlinedTextField(
                value = link,
                onValueChange = { link = it },
                modifier = Modifier.fillMaxWidth(),
                label = { Text("Connection link") },
                minLines = 3,
            )
            if (error != null) {
                Spacer(Modifier.height(12.dp))
                Text(error!!, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall)
            }
            Spacer(Modifier.height(20.dp))
            if (busy) {
                CircularProgressIndicator()
            } else {
                Button(
                    onClick = {
                        scope.launch { connect(repository, link, { busy = it }, { error = it }, onPaired) }
                    },
                    modifier = Modifier.fillMaxWidth(),
                ) { Text("Connect") }
                Spacer(Modifier.height(10.dp))
                OutlinedButton(
                    onClick = {
                        val options = ScanOptions()
                            .setDesiredBarcodeFormats(ScanOptions.QR_CODE)
                            .setPrompt("Scan pairing QR")
                            .setBeepEnabled(false)
                            .setOrientationLocked(true)
                        scanLauncher.launch(options)
                    },
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Icon(Icons.Outlined.QrCodeScanner, contentDescription = null)
                    Spacer(Modifier.size(8.dp))
                    Text("Scan QR code")
                }
            }
        }
    }
}

private suspend fun connect(
    repository: PlanningRepository,
    raw: String,
    setBusy: (Boolean) -> Unit,
    setError: (String?) -> Unit,
    onPaired: () -> Unit,
) {
    setBusy(true)
    setError(null)
    try {
        val (endpoint, token) = parsePairingLink(raw)
        repository.pairAndSync(endpoint, token).getOrThrow()
        onPaired()
    } catch (e: Exception) {
        setError(e.message ?: "Connection failed")
    } finally {
        setBusy(false)
    }
}

fun parsePairingLink(raw: String): Pair<String, String> {
    val trimmed = raw.trim()
    val uri = Uri.parse(trimmed)
    val endpoint = uri.getQueryParameter("endpoint").orEmpty()
    val token = uri.getQueryParameter("token").orEmpty()
    require(endpoint.startsWith("http") && token.isNotBlank()) { "Invalid connection link" }
    return endpoint to token
}
