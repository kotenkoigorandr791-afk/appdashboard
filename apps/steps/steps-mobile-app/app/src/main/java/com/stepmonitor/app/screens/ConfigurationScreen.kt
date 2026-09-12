package com.stepmonitor.app.screens
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
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
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CloudDone
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
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
import com.stepmonitor.app.HealthDataType
import com.stepmonitor.app.PreferencesManager
import com.stepmonitor.app.R
import com.stepmonitor.app.ScheduledSync
import com.stepmonitor.app.SyncMode
import com.stepmonitor.app.WebhookConfig
import com.stepmonitor.app.components.ManualSyncCard
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import java.util.Calendar

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
    Column(modifier.fillMaxSize().padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(stringResource(R.string.home_title), style = MaterialTheme.typography.titleLarge)
                Text(if (connected) stringResource(R.string.auth_connected) else stringResource(R.string.auth_waiting), color = MaterialTheme.colorScheme.onSurfaceVariant)
                Button(
                    onClick = {
                        scope.launch {
                            testing = true
                            testResult = null
                            testResult = testConnection(prefs.getWebhookConfigs().firstOrNull { it.isEnabled })
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
    val syncMode = prefs.getSyncMode()
    val nextSyncMs: Long?
    val periodMs: Long
    val nextSyncLabel: String
    when (syncMode) {
        SyncMode.SCHEDULED -> {
            val syncs = prefs.getScheduledSyncs().filter { it.enabled }
            if (syncs.isEmpty()) return
            var earliest = Long.MAX_VALUE
            var earliestLabel = ""
            for (s in syncs) {
                val cal = Calendar.getInstance().apply {
                    set(Calendar.HOUR_OF_DAY, s.hour)
                    set(Calendar.MINUTE, s.minute)
                    set(Calendar.SECOND, 0)
                    set(Calendar.MILLISECOND, 0)
                    if (timeInMillis <= now) add(Calendar.DAY_OF_YEAR, 1)
                }
                if (cal.timeInMillis < earliest) {
                    earliest = cal.timeInMillis
                    earliestLabel = s.getDisplayTime()
                }
            }
            nextSyncMs = earliest
            periodMs = 24L * 60 * 60 * 1000
            nextSyncLabel = earliestLabel
        }
        SyncMode.INTERVAL -> {
            val intervalMs = prefs.getSyncIntervalMinutes() * 60_000L
            val lastSync = prefs.getLastSyncTime()
            val anchor = if (lastSync != null) lastSync else remember { now }
            nextSyncMs = anchor + intervalMs
            periodMs = intervalMs
            nextSyncLabel = ""
        }
    }
    if (nextSyncMs == null) return
    val remainingMs = (nextSyncMs - now).coerceAtLeast(0L)
    val fraction = if (periodMs > 0) (remainingMs.toFloat() / periodMs).coerceIn(0f, 1f) else 0f
    val animatedFraction by animateFloatAsState(
        targetValue = fraction,
        animationSpec = tween(durationMillis = 800, easing = LinearEasing),
        label = "syncProgress"
    )
    val hours = remainingMs / 3600_000L
    val minutes = (remainingMs % 3600_000L) / 60_000L
    val seconds = (remainingMs % 60_000L) / 1000L
    val timeStr = "%02d:%02d:%02d".format(hours, minutes, seconds)
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

private suspend fun testConnection(config: WebhookConfig?): ConnectionTestResult = withContext(Dispatchers.IO) {
    if (config == null) return@withContext ConnectionTestResult.FAILURE
    runCatching {
        val builder = Request.Builder().url(config.url.trimEnd('/') + "/profile")
        config.headers.forEach { (key, value) -> builder.addHeader(key, value) }
        OkHttpClient().newCall(builder.build()).execute().use { response ->
            if (!response.isSuccessful) error("HTTP ${response.code}")
            ConnectionTestResult.SUCCESS
        }
    }.getOrDefault(ConnectionTestResult.FAILURE)
}
