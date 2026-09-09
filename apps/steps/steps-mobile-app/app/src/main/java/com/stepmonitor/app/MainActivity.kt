package com.stepmonitor.app
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.widget.Toast
import androidx.activity.compose.setContent
import androidx.appcompat.app.AppCompatActivity
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.Row
import androidx.compose.material3.Icon
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.stepmonitor.app.screens.AboutScreen
import com.stepmonitor.app.screens.ConfigurationScreen
import com.stepmonitor.app.screens.LogsScreen
import com.stepmonitor.app.screens.StatisticsScreen
import com.stepmonitor.app.ui.theme.HCWebhookTheme
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
class MainActivity : AppCompatActivity() {
    private lateinit var preferencesManager: PreferencesManager
    private val permissionLauncher = registerForActivityResult(androidx.health.connect.client.PermissionController.createRequestPermissionResultContract()) {
        preferencesManager.setKnownGrantedPermissions(it)
    }
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        preferencesManager = PreferencesManager(this)
        if (preferencesManager.getWebhookConfigs().any { it.isEnabled } && preferencesManager.getEnabledDataTypes().isEmpty()) {
            preferencesManager.setEnabledDataTypes(HealthDataType.values().toSet())
        }
        handleAuthIntent(intent)
        setContent { HCWebhookTheme { MainScreen() } }
    }
    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        handleAuthIntent(intent)
    }
    private fun handleAuthIntent(intent: Intent?) {
        val uri = intent?.data ?: return
        if (uri.scheme != "stepmonitor" || uri.host != "auth") return
        val endpoint = uri.getQueryParameter("endpoint").orEmpty()
        val token = uri.getQueryParameter("token").orEmpty()
        if (endpoint.startsWith("http") && token.isNotBlank()) {
            preferencesManager.setWebhookConfigs(listOf(WebhookConfig(endpoint, "StepMonitor", mapOf("Authorization" to "Bearer $token"))))
            preferencesManager.setEnabledDataTypes(HealthDataType.values().toSet())
            Toast.makeText(this, getString(R.string.auth_connected), Toast.LENGTH_LONG).show()
        }
    }
    @Composable
    private fun MainScreen() {
        var selected by remember { mutableStateOf<NavigationScreen>(NavigationScreen.Home) }
        var pendingConflict by remember { mutableStateOf(preferencesManager.getPendingConflictJson()) }
        LaunchedEffect(Unit) {
            while (true) {
                pendingConflict = preferencesManager.getPendingConflictJson()
                delay(2_000)
            }
        }
        Scaffold(bottomBar = {
            NavigationBar {
                bottomNavItems.forEach { screen ->
                    NavigationBarItem(
                        selected = selected == screen,
                        onClick = { selected = screen },
                        icon = { Icon(screen.icon, contentDescription = stringResource(screen.titleResId)) },
                        label = { Text(stringResource(screen.titleResId)) }
                    )
                }
            }
        }) { padding ->
            when (selected) {
                NavigationScreen.Home -> ConfigurationScreen(
                    modifier = Modifier.padding(padding),
                    onRequestPermissions = { permissionLauncher.launch(HealthConnectManager.ALL_PERMISSIONS) }
                )
                NavigationScreen.Statistics -> StatisticsScreen(Modifier.padding(padding))
                NavigationScreen.Logs -> LogsScreen(Modifier.padding(padding))
                NavigationScreen.About -> AboutScreen(Modifier.padding(padding))
            }
        }
        pendingConflict?.let { conflictJson ->
            AlertDialog(
                onDismissRequest = {},
                title = { Text(getString(R.string.conflict_title)) },
                text = { Text(conflictSummary(conflictJson)) },
                confirmButton = {
                    Row {
                        TextButton(onClick = {
                            resolveConflict("skip", conflictJson)
                            pendingConflict = null
                        }) { Text(getString(R.string.conflict_skip)) }
                        TextButton(onClick = {
                            resolveConflict("replace", conflictJson)
                            pendingConflict = null
                        }) { Text(getString(R.string.conflict_replace)) }
                        TextButton(onClick = {
                            resolveConflict("add", conflictJson)
                            pendingConflict = null
                        }) { Text(getString(R.string.conflict_add)) }
                    }
                },
                dismissButton = {},
                tonalElevation = 6.dp
            )
        }
    }
    private fun conflictSummary(json: String): String = runCatching {
        val obj = JSONObject(json).optJSONObject("conflict") ?: JSONObject(json)
        val days = obj.optJSONArray("days")
        getString(R.string.conflict_message, days?.length() ?: 0)
    }.getOrDefault(getString(R.string.conflict_message, 0))
    private fun resolveConflict(action: String, json: String) {
        preferencesManager.clearPendingConflictJson()
        kotlinx.coroutines.CoroutineScope(Dispatchers.IO).launchCatching {
            val obj = JSONObject(json).optJSONObject("conflict") ?: JSONObject(json)
            val config = preferencesManager.getWebhookConfigs().firstOrNull { it.isEnabled }
            val rawUrl = obj.optString("resolution_url").ifBlank {
                val id = obj.optString("id")
                "/api/steps/conflicts/$id"
            }
            val url = if (rawUrl.startsWith("http")) rawUrl else {
                val base = Uri.parse(config?.url.orEmpty())
                "${base.scheme}://${base.authority}/${rawUrl.trimStart('/')}"
            }
            val body = JSONObject().put("action", action).toString().toRequestBody("application/json; charset=utf-8".toMediaType())
            val builder = Request.Builder().url(url).post(body)
            config?.headers?.forEach { (key, value) -> builder.addHeader(key, value) }
            OkHttpClient().newCall(builder.build()).execute().close()
        }
    }
}
private fun kotlinx.coroutines.CoroutineScope.launchCatching(block: suspend () -> Unit) {
    launch(Dispatchers.IO) { runCatching { block() } }
}
