package com.stepmonitor.app.screens
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CloudDone
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.stepmonitor.app.HealthDataType
import com.stepmonitor.app.PreferencesManager
import com.stepmonitor.app.R
import com.stepmonitor.app.WebhookConfig
import com.stepmonitor.app.components.ManualSyncCard
import kotlinx.coroutines.Dispatchers
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
                            ConnectionTestResult.SUCCESS -> Color(0xFF2563EB)
                            ConnectionTestResult.FAILURE -> Color(0xFFF59E0B)
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
        ManualSyncCard()
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
