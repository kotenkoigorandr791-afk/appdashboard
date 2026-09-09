package com.stepmonitor.app.screens
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.ArrowForward
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.stepmonitor.app.PreferencesManager
import com.stepmonitor.app.R
import com.stepmonitor.app.WebhookConfig
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONObject
import java.time.LocalDate
class StepDay(val steps: Int, val error: String? = null)
@Composable
fun StatisticsScreen(modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val scope = rememberCoroutineScope()
    var offset by remember { mutableIntStateOf(0) }
    var pasted by remember { mutableStateOf("") }
    var day by remember { mutableStateOf(StepDay(0)) }
    val config = prefs.getWebhookConfigs().firstOrNull { it.isEnabled }
    val date = LocalDate.now().plusDays(offset.toLong())
    fun connect(value: String) {
        val uri = android.net.Uri.parse(value.trim())
        val endpoint = uri.getQueryParameter("endpoint").orEmpty()
        val token = uri.getQueryParameter("token").orEmpty()
        if (endpoint.startsWith("http") && token.isNotBlank()) {
            prefs.setWebhookConfigs(listOf(WebhookConfig(endpoint, "StepMonitor", mapOf("Authorization" to "Bearer $token"))))
            pasted = ""
        }
    }
    LaunchedEffect(config?.url, date) {
        day = fetchDay(config, date.toString())
    }
    Column(modifier.fillMaxSize().padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        if (config == null) {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(stringResource(R.string.auth_title), style = MaterialTheme.typography.titleLarge)
                    Text(stringResource(R.string.auth_paste_hint), color = MaterialTheme.colorScheme.onSurfaceVariant)
                    OutlinedTextField(value = pasted, onValueChange = { pasted = it }, modifier = Modifier.fillMaxWidth(), label = { Text(stringResource(R.string.auth_link_label)) })
                    Button(onClick = { connect(pasted) }, modifier = Modifier.fillMaxWidth()) { Text(stringResource(R.string.auth_connect)) }
                }
            }
            return@Column
        }
        Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
            IconButton(onClick = { offset-- }) { Icon(Icons.AutoMirrored.Filled.ArrowBack, null) }
            Spacer(Modifier.width(8.dp))
            Text(date.toString(), style = MaterialTheme.typography.titleLarge, modifier = Modifier.weight(1f))
            IconButton(onClick = { if (offset < 0) offset++ }) { Icon(Icons.AutoMirrored.Filled.ArrowForward, null) }
        }
        Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primaryContainer), modifier = Modifier.fillMaxWidth()) {
            Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(stringResource(R.string.stats_steps), style = MaterialTheme.typography.titleMedium)
                Text(day.error ?: day.steps.toString(), style = MaterialTheme.typography.displayMedium)
            }
        }
        Button(onClick = { scope.launch { day = fetchDay(config, date.toString()) } }, modifier = Modifier.fillMaxWidth()) {
            Text(stringResource(R.string.dashboard_refresh))
        }
    }
}
suspend fun fetchDay(config: WebhookConfig?, date: String): StepDay = withContext(Dispatchers.IO) {
    if (config == null) return@withContext StepDay(0)
    try {
        val requestBuilder = Request.Builder().url("${config.url}?date=$date")
        config.headers.forEach { (key, value) -> requestBuilder.addHeader(key, value) }
        OkHttpClient().newCall(requestBuilder.build()).execute().use { response ->
            if (!response.isSuccessful) return@withContext StepDay(0, "HTTP ${response.code}")
            val json = JSONObject(response.body?.string().orEmpty())
            val rows = json.optJSONArray("steps")
            StepDay(rows?.optJSONObject(0)?.optInt("steps") ?: 0)
        }
    } catch (e: Exception) {
        StepDay(0, e.message)
    }
}
