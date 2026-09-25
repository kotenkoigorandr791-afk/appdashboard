package com.stepmonitor.app.screens
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
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
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CloudDone
import androidx.compose.material.icons.filled.Info
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.Update
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
import com.stepmonitor.app.BuildConfig
import com.stepmonitor.app.HealthDataType
import com.stepmonitor.app.PreferencesManager
import com.stepmonitor.app.R
import com.stepmonitor.app.WebhookConfig
import com.stepmonitor.app.components.ManualSyncCard
import com.stepmonitor.app.components.computeNextSyncInfo
import com.stepmonitor.app.components.formatSyncCountdown
import com.stepmonitor.app.updates.AppUpdateChecker
import com.stepmonitor.app.updates.CheckOutcome
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.time.format.FormatStyle
import java.util.Locale

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
        modifier.fillMaxSize().padding(20.dp).verticalScroll(rememberScrollState()),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
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
        UpdateNowCard(prefs = prefs)
        BuildInfoCard()
    }
}

@Composable
private fun UpdateNowCard(prefs: PreferencesManager) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val checker = remember { AppUpdateChecker(context) }
    val connected = prefs.getWebhookConfigs().any { it.isEnabled }
    var busy by remember { mutableStateOf(false) }
    var statusText by remember { mutableStateOf<String?>(null) }
    Card(
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Button(
                onClick = {
                    scope.launch {
                        busy = true
                        statusText = context.getString(R.string.update_checking)
                        val check = checker.checkForUpdate()
                        when (check.outcome) {
                            CheckOutcome.NOT_CONNECTED -> statusText = context.getString(R.string.update_not_connected)
                            CheckOutcome.UP_TO_DATE -> statusText = context.getString(R.string.update_up_to_date)
                            CheckOutcome.ERROR -> statusText = context.getString(R.string.update_check_failed)
                            CheckOutcome.UPDATE_AVAILABLE -> {
                                val info = check.info ?: return@launch
                                statusText = context.getString(R.string.update_downloading)
                                val file = checker.downloadApk(info)
                                statusText = if (file != null) {
                                    checker.install(file)
                                    context.getString(R.string.update_downloaded_text, info.version_name)
                                } else {
                                    context.getString(R.string.update_download_failed)
                                }
                            }
                        }
                        busy = false
                    }
                },
                enabled = connected && !busy,
                modifier = Modifier.fillMaxWidth()
            ) {
                if (busy) {
                    CircularProgressIndicator(modifier = Modifier.size(18.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary)
                    Spacer(Modifier.size(8.dp))
                    Text(stringResource(R.string.update_checking))
                } else {
                    Icon(Icons.Filled.Update, contentDescription = null)
                    Text(stringResource(R.string.update_install_now), modifier = Modifier.padding(start = 8.dp))
                }
            }
            if (!connected) {
                Text(stringResource(R.string.update_not_connected), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            statusText?.let { Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant) }
        }
    }
}

@Composable
private fun BuildInfoCard() {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val checker = remember { AppUpdateChecker(context) }
    var checking by remember { mutableStateOf(false) }
    var downloading by remember { mutableStateOf(false) }
    var statusText by remember { mutableStateOf<String?>(null) }
    var downloadedFile by remember { mutableStateOf(checker.latestDownloaded()) }
    val dateLabel = LocalDate.now().format(
        DateTimeFormatter.ofLocalizedDate(FormatStyle.LONG).withLocale(Locale.getDefault())
    )
    Card(
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.primary),
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    Icons.Filled.Info,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary
                )
                Column {
                    Text(dateLabel, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurface)
                    Text(
                        stringResource(R.string.about_app_version, BuildConfig.VERSION_NAME, BuildConfig.VERSION_CODE),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    if (statusText != null) {
                        Text(statusText!!, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.primary)
                    }
                }
            }
            FilledTonalButton(
                onClick = {
                    scope.launch {
                        checking = true
                        statusText = context.getString(R.string.update_checking)
                        val check = checker.checkForUpdate()
                        when (check.outcome) {
                            CheckOutcome.NOT_CONNECTED -> statusText = context.getString(R.string.update_not_connected)
                            CheckOutcome.UP_TO_DATE -> statusText = context.getString(R.string.update_up_to_date)
                            CheckOutcome.ERROR -> statusText = context.getString(R.string.update_check_failed)
                            CheckOutcome.UPDATE_AVAILABLE -> {
                                val info = check.info ?: return@launch
                                downloading = true
                                statusText = context.getString(R.string.update_downloading)
                                val file = checker.downloadApk(info)
                                downloading = false
                                if (file != null) {
                                    downloadedFile = file
                                    statusText = context.getString(R.string.update_downloaded_text, info.version_name)
                                } else {
                                    statusText = context.getString(R.string.update_download_failed)
                                }
                            }
                        }
                        checking = false
                    }
                },
                enabled = !checking && !downloading,
                modifier = Modifier.fillMaxWidth()
            ) {
                if (checking || downloading) {
                    CircularProgressIndicator(modifier = Modifier.size(18.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.primary)
                    Spacer(Modifier.size(8.dp))
                    Text(stringResource(R.string.update_checking))
                } else {
                    Icon(Icons.Filled.Update, contentDescription = null)
                    Text(stringResource(R.string.update_check_button), modifier = Modifier.padding(start = 8.dp))
                }
            }
            downloadedFile?.let { file ->
                Button(onClick = { checker.install(file) }, modifier = Modifier.fillMaxWidth()) {
                    Text(stringResource(R.string.update_install))
                }
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
