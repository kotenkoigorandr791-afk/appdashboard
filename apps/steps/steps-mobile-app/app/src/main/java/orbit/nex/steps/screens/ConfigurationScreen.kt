package orbit.nex.steps.screens
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CloudDone
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.FilledTonalButton
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ProgressIndicatorDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import orbit.nex.steps.BuildConfig
import orbit.nex.steps.HealthDataType
import orbit.nex.steps.MockPayloadBuilder
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.R
import orbit.nex.steps.WebhookConfig
import orbit.nex.steps.WebhookManager
import orbit.nex.steps.components.ManualSyncCard
import orbit.nex.steps.components.computeNextSyncInfo
import orbit.nex.steps.components.formatSyncCountdown
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request

private enum class ConnectionTestResult { SUCCESS, FAILURE }

@Composable
fun ConfigurationScreen(modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val scope = rememberCoroutineScope()
    val connected = prefs.getWebhookConfigs().any { it.isEnabled }
    var testing by remember { mutableStateOf(false) }
    var testResult by remember { mutableStateOf<ConnectionTestResult?>(null) }
    androidx.compose.runtime.LaunchedEffect(connected) {
        if (connected && prefs.getEnabledDataTypes().isEmpty()) {
            prefs.setEnabledDataTypes(HealthDataType.values().toSet())
        }
    }
    Column(
        modifier
            .fillMaxSize()
            .padding(horizontal = 10.dp)
            .verticalScroll(rememberScrollState()),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        // Gap under the top bar that scrolls away with the content.
        Spacer(Modifier.height(12.dp))
        Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(stringResource(R.string.home_title), style = MaterialTheme.typography.titleLarge)
                val userConnected = prefs.isUserConnected()
                Text(if (userConnected) stringResource(R.string.auth_connected) else stringResource(R.string.auth_waiting), color = MaterialTheme.colorScheme.onSurfaceVariant)
                Button(
                    onClick = {
                        scope.launch {
                            testing = true
                            testResult = null
                            testResult = testConnection(context, prefs.getWebhookConfigs().firstOrNull { it.isEnabled })
                            testing = false
                        }
                    },
                    enabled = connected && !testing,
                    colors = ButtonDefaults.buttonColors(
                        containerColor = when (testResult) {
                            ConnectionTestResult.SUCCESS -> Color(0xFF66BB6A)
                            ConnectionTestResult.FAILURE -> Color(0xFF9B1C31)
                            null -> MaterialTheme.colorScheme.primary
                        }
                    ),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    if (testing) {
                        CircularProgressIndicator(modifier = Modifier.size(18.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary)
                        Spacer(Modifier.size(8.dp))
                        Text(stringResource(R.string.settings_test_connection_progress))
                    } else {
                        Icon(Icons.Filled.CloudDone, contentDescription = null)
                        Text(stringResource(R.string.settings_test_connection_home), modifier = Modifier.padding(start = 8.dp))
                    }
                }
            }
        }
        NextSyncCard(prefs = prefs)
        ManualSyncCard()
        LogsEntryCard()
    }
}

/** Quick access to the synchronization log from the home tab. */
@Composable
private fun LogsEntryCard() {
    val context = LocalContext.current
    Card(
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
        modifier = Modifier.fillMaxWidth(),
    ) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Text(stringResource(R.string.home_logs_title), style = MaterialTheme.typography.titleMedium)
            Text(
                stringResource(R.string.home_logs_desc),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Button(
                onClick = { context.startActivity(android.content.Intent(context, orbit.nex.steps.LogsActivity::class.java)) },
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text(stringResource(R.string.home_logs_title))
            }
        }
    }
}

@Composable
private fun NextSyncCard(prefs: PreferencesManager) {
    var now by remember { mutableLongStateOf(System.currentTimeMillis()) }
    LaunchedEffect(Unit) {
        while (true) {
            delay(1_000)
            now = System.currentTimeMillis()
        }
    }
    val info = computeNextSyncInfo(prefs, now) ?: return
    val remainingMs = (info.nextSyncMs - now).coerceAtLeast(0L)
    val fraction = if (info.periodMs > 0) (remainingMs.toFloat() / info.periodMs).coerceIn(0f, 1f) else 0f
    val animatedFraction by animateFloatAsState(
        targetValue = fraction,
        animationSpec = tween(durationMillis = 800, easing = LinearEasing),
        label = "syncProgress"
    )
    val timeStr = formatSyncCountdown(remainingMs)
    val nextSyncLabel = info.label
    Card(
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
        modifier = Modifier.fillMaxWidth()
    ) {
        Row(
            modifier = Modifier.padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            Box(contentAlignment = Alignment.Center, modifier = Modifier.size(64.dp)) {
                CircularProgressIndicator(
                    progress = { animatedFraction },
                    modifier = Modifier.size(64.dp),
                    strokeWidth = 5.dp,
                    trackColor = MaterialTheme.colorScheme.outlineVariant,
                    color = MaterialTheme.colorScheme.primary
                )
                Icon(
                    Icons.Filled.Schedule,
                    contentDescription = null,
                    modifier = Modifier.size(22.dp),
                    tint = MaterialTheme.colorScheme.primary
                )
            }
            Column(Modifier.weight(1f)) {
                Text(
                    stringResource(R.string.next_sync_title),
                    style = MaterialTheme.typography.titleSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                Text(
                    timeStr,
                    style = MaterialTheme.typography.headlineSmall.copy(
                        fontFamily = FontFamily.Monospace,
                        fontWeight = FontWeight.Bold
                    )
                )
                if (nextSyncLabel.isNotBlank()) {
                    Text(
                        stringResource(R.string.next_sync_at, nextSyncLabel),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
        }
    }
}

private suspend fun testConnection(context: android.content.Context, config: WebhookConfig?): ConnectionTestResult = withContext(Dispatchers.IO) {
    if (config == null) return@withContext ConnectionTestResult.FAILURE
    runCatching {
        val prefs = PreferencesManager(context)
        val builder = Request.Builder().url(config.url.trimEnd('/') + "/profile")
        config.headers.forEach { (key, value) -> builder.addHeader(key, value) }
        builder.addHeader("Accept", "application/json")
        if (config.headers.keys.none { it.equals("X-Device-Id", ignoreCase = true) }) {
            builder.addHeader("X-Device-Id", prefs.getDeviceId())
        }
        OkHttpClient().newCall(builder.build()).execute().use { response ->
            if (response.code == 401 || response.code == 403) prefs.markAuthError(response.code)
            if (!response.isSuccessful) error("HTTP ${response.code}")
            prefs.markSyncSuccess()
            ConnectionTestResult.SUCCESS
        }
    }.getOrDefault(ConnectionTestResult.FAILURE)
}
