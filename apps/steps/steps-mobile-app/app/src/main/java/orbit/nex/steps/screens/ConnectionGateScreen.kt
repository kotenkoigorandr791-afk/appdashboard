package orbit.nex.steps.screens

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CloudOff
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.journeyapps.barcodescanner.ScanContract
import com.journeyapps.barcodescanner.ScanOptions
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import orbit.nex.steps.BuildConfig
import orbit.nex.steps.HealthDataType
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.R
import orbit.nex.steps.WebhookConfig
import okhttp3.OkHttpClient
import okhttp3.Request

/**
 * Full-screen gate shown while the phone has no paired server. Mirrors the
 * Spends app: no tabs, no counters and no sync controls are reachable until a
 * device token is paired, so a fresh install cannot show stale or empty data.
 */
@Composable
fun ConnectionGateScreen(
    modifier: Modifier = Modifier,
    onConnected: () -> Unit,
) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val scope = rememberCoroutineScope()
    var link by remember { mutableStateOf("") }
    var busy by remember { mutableStateOf(false) }
    var message by remember { mutableStateOf<String?>(null) }
    var failed by remember { mutableStateOf(false) }

    fun pair(value: String) {
        val parsed = parsePairingLink(value) ?: run {
            failed = true
            message = context.getString(R.string.auth_invalid_link)
            return
        }
        scope.launch {
            busy = true
            message = null
            failed = false
            val config = WebhookConfig(
                parsed.first,
                "NexSteps",
                mapOf("Authorization" to "Bearer ${parsed.second}"),
            )
            // The device id is sent with the first authenticated request, which
            // binds the pairing token to this phone on the server.
            val bound = bindDevice(config, prefs)
            if (bound) {
                prefs.setWebhookConfigs(listOf(config))
                prefs.setEnabledDataTypes(prefs.getEnabledDataTypes().ifEmpty { HealthDataType.values().toSet() })
                prefs.markSyncSuccess()
                prefs.clearAuthError()
                busy = false
                link = ""
                onConnected()
            } else {
                busy = false
                failed = true
                message = context.getString(R.string.auth_invalid_link)
            }
        }
    }

    val scanner = rememberLauncherForActivityResult(ScanContract()) { result ->
        result.contents?.takeIf { it.isNotBlank() }?.let { pair(it) }
    }

    Column(
        modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .navigationBarsPadding()
            .padding(horizontal = 20.dp, vertical = 24.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        // Branding hero: the artwork sits fully inside the frame (Fit, no crop),
        // matching how the Spends welcome card renders its icon.
        Box(
            Modifier
                .fillMaxWidth()
                .height(228.dp)
                .background(
                    Brush.linearGradient(
                        listOf(Color(0xFF022249), Color(0xFF12366A), Color(0xFF3A2470)),
                    ),
                    RoundedCornerShape(18.dp),
                ),
            contentAlignment = Alignment.Center,
        ) {
            Image(
                painterResource(R.drawable.nex_steps_icon),
                contentDescription = stringResource(R.string.app_name),
                modifier = Modifier.size(172.dp),
                contentScale = ContentScale.Fit,
            )
        }
        Text(
            stringResource(R.string.auth_gate_title),
            style = MaterialTheme.typography.headlineMedium,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onBackground,
        )
        Text(
            stringResource(R.string.auth_gate_desc),
            style = MaterialTheme.typography.bodyLarge,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Card(
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
            modifier = Modifier.fillMaxWidth(),
        ) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        Icons.Filled.CloudOff,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.primary,
                    )
                    Spacer(Modifier.size(8.dp))
                    Text(
                        stringResource(R.string.auth_title),
                        style = MaterialTheme.typography.titleMedium,
                    )
                }
                Text(
                    stringResource(R.string.auth_waiting),
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Button(
                    onClick = {
                        scanner.launch(
                            ScanOptions().apply {
                                setDesiredBarcodeFormats(ScanOptions.QR_CODE)
                                setPrompt(context.getString(R.string.auth_scan_qr))
                                setBeepEnabled(false)
                                setOrientationLocked(true)
                            },
                        )
                    },
                    enabled = !busy,
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Icon(Icons.Filled.QrCodeScanner, contentDescription = null)
                    Text(stringResource(R.string.auth_scan_qr), Modifier.padding(start = 8.dp))
                }
                OutlinedTextField(
                    value = link,
                    onValueChange = { link = it },
                    modifier = Modifier.fillMaxWidth(),
                    label = { Text(stringResource(R.string.auth_link_label)) },
                    placeholder = { Text(stringResource(R.string.auth_paste_hint)) },
                    singleLine = true,
                    enabled = !busy,
                )
                Button(
                    onClick = { pair(link) },
                    enabled = !busy && link.isNotBlank(),
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    if (busy) {
                        CircularProgressIndicator(
                            Modifier.size(18.dp),
                            strokeWidth = 2.dp,
                            color = MaterialTheme.colorScheme.onPrimary,
                        )
                        Spacer(Modifier.size(8.dp))
                    }
                    Text(stringResource(R.string.auth_connect))
                }
                message?.let { text ->
                    Text(
                        text,
                        style = MaterialTheme.typography.bodySmall,
                        color = if (failed) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
        }
        Text(
            stringResource(R.string.auth_gate_version, BuildConfig.VERSION_NAME),
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}

/** Parses nexsteps://auth and legacy stepmonitor://auth pairing links. */
fun parsePairingLink(value: String): Pair<String, String>? {
    val trimmed = value.trim()
    if (trimmed.isEmpty()) return null
    val uri = runCatching { Uri.parse(trimmed) }.getOrNull() ?: return null
    val scheme = uri.scheme?.lowercase().orEmpty()
    if (scheme != "nexsteps" && scheme != "stepmonitor" && scheme != "spends") {
        // A bare endpoint plus token is also accepted when pasted as query params.
        val endpoint = uri.getQueryParameter("endpoint").orEmpty()
        val token = uri.getQueryParameter("token").orEmpty()
        return if (endpoint.startsWith("http") && token.isNotBlank()) endpoint to token else null
    }
    val endpoint = uri.getQueryParameter("endpoint").orEmpty()
    val token = uri.getQueryParameter("token").orEmpty()
    if (!endpoint.startsWith("http") || token.isBlank()) return null
    return endpoint to token
}

/**
 * Calls the profile endpoint with the phone's device id so the server binds the
 * freshly issued token to this device before the app stores it.
 */
suspend fun bindDevice(config: WebhookConfig, prefs: PreferencesManager): Boolean = withContext(Dispatchers.IO) {
    runCatching {
        val builder = Request.Builder()
            .url(config.url.trimEnd('/') + "/profile")
            .addHeader("Accept", "application/json")
            .addHeader("X-Device-Id", prefs.getDeviceId())
        config.headers.forEach { (key, value) -> builder.addHeader(key, value) }
        OkHttpClient().newCall(builder.build()).execute().use { response ->
            if (response.code == 401 || response.code == 403) prefs.markAuthError(response.code)
            response.isSuccessful
        }
    }.getOrDefault(false)
}
