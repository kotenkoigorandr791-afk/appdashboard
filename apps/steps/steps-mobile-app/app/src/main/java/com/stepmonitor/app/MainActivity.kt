package com.stepmonitor.app
import android.content.Intent
import android.content.pm.ActivityInfo
import android.net.Uri
import android.os.Bundle
import android.widget.Toast
import androidx.activity.compose.setContent
import androidx.appcompat.app.AppCompatActivity
import androidx.appcompat.app.AppCompatDelegate
import androidx.core.os.LocaleListCompat
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.material3.Icon
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Checkbox
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
import androidx.compose.runtime.saveable.Saver
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.stepmonitor.app.screens.AboutScreen
import com.stepmonitor.app.screens.ConfigurationScreen
import com.stepmonitor.app.screens.LogsScreen
import com.stepmonitor.app.screens.StatisticsScreen
import com.stepmonitor.app.screens.WhatsNewSheet
import com.stepmonitor.app.components.AppBackground
import com.stepmonitor.app.components.AppTopBar
import com.stepmonitor.app.ui.theme.HCWebhookTheme
import com.stepmonitor.app.updates.AppUpdateManager
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.io.IOException
import java.util.UUID
class MainActivity : AppCompatActivity() {
    private lateinit var preferencesManager: PreferencesManager
    companion object {
        private const val KEY_APP_LANGUAGE = "app_language"
        private const val KEY_PORTRAIT_ONLY = "portrait_only"
    }
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        preferencesManager = PreferencesManager(this)
        if (preferencesManager.getWebhookConfigs().any { it.isEnabled } && preferencesManager.getEnabledDataTypes().isEmpty()) {
            preferencesManager.setEnabledDataTypes(HealthDataType.values().toSet())
        }
        applyLanguage(preferencesManager.getAppLanguage())
        applyPortraitMode(preferencesManager.isPortraitOnly())
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
        val navigationSaver = Saver<NavigationScreen, String>(
            save = { it.route },
            restore = { route -> bottomNavItems.firstOrNull { it.route == route } ?: NavigationScreen.Home }
        )
        var selected by rememberSaveable(stateSaver = navigationSaver) { mutableStateOf(NavigationScreen.Home) }
        var pendingConflict by remember { mutableStateOf(preferencesManager.getPendingConflictJson()) }
        var showWhatsNew by remember { mutableStateOf(preferencesManager.consumeWhatsNewPending()) }
        LaunchedEffect(Unit) {
            while (true) {
                pendingConflict = preferencesManager.getPendingConflictJson()
                delay(2_000)
            }
        }
        LaunchedEffect(Unit) {
            AppUpdateManager.start(applicationContext)
        }
        if (showWhatsNew) {
            WhatsNewSheet(
                visible = true,
                onDismiss = {
                    showWhatsNew = false
                    preferencesManager.setWhatsNewPending(false)
                },
            )
        }
        // Auto-resolve if user saved a preference
        LaunchedEffect(pendingConflict) {
            val conflict = pendingConflict ?: return@LaunchedEffect
            if (preferencesManager.isConflictRememberEnabled()) {
                val action = preferencesManager.getConflictDefaultAction()
                if (action != null) {
                    resolveConflict(action, conflict)
                    pendingConflict = null
                }
            }
        }
        Scaffold(
            topBar = {
                AppTopBar(
                    showTimer = selected != NavigationScreen.Home,
                    onProfileClick = { selected = NavigationScreen.About }
                )
            },
            bottomBar = {
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
            AppBackground {
                when (selected) {
                    NavigationScreen.Home -> ConfigurationScreen(Modifier.padding(padding))
                    NavigationScreen.Statistics -> StatisticsScreen(Modifier.padding(padding))
                    NavigationScreen.Logs -> LogsScreen(Modifier.padding(padding))
                    NavigationScreen.About -> AboutScreen(Modifier.padding(padding))
                }
            }
        }
        val showConflict = pendingConflict != null &&
            !(preferencesManager.isConflictRememberEnabled() && preferencesManager.getConflictDefaultAction() != null)
        if (showConflict) {
            val conflictJson = pendingConflict!!
            var rememberChoice by remember { mutableStateOf(false) }
            AlertDialog(
                onDismissRequest = {},
                title = { Text(getString(R.string.conflict_title)) },
                text = {
                    androidx.compose.foundation.layout.Column {
                        Text(conflictSummary(conflictJson))
                        Spacer(Modifier.height(8.dp))
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Checkbox(
                                checked = rememberChoice,
                                onCheckedChange = { rememberChoice = it }
                            )
                            Text(getString(R.string.conflict_remember))
                        }
                    }
                },
                confirmButton = {
                    Row {
                        TextButton(onClick = {
                            if (rememberChoice) {
                                preferencesManager.setConflictRememberEnabled(true)
                                preferencesManager.setConflictDefaultAction("skip")
                            }
                            resolveConflict("skip", conflictJson)
                            pendingConflict = null
                        }) { Text(getString(R.string.conflict_skip)) }
                        TextButton(onClick = {
                            if (rememberChoice) {
                                preferencesManager.setConflictRememberEnabled(true)
                                preferencesManager.setConflictDefaultAction("replace")
                            }
                            resolveConflict("replace", conflictJson)
                            pendingConflict = null
                        }) { Text(getString(R.string.conflict_replace)) }
                        TextButton(onClick = {
                            if (rememberChoice) {
                                preferencesManager.setConflictRememberEnabled(true)
                                preferencesManager.setConflictDefaultAction("add")
                            }
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
        kotlinx.coroutines.CoroutineScope(Dispatchers.IO).launch {
            val timestamp = System.currentTimeMillis()
            var loggedUrl = ""
            try {
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
                loggedUrl = url
                val body = JSONObject().put("action", action).toString().toRequestBody("application/json; charset=utf-8".toMediaType())
                val builder = Request.Builder().url(url).post(body)
                config?.headers?.forEach { (key, value) -> builder.addHeader(key, value) }
                OkHttpClient().newCall(builder.build()).execute().use { response ->
                    if (response.isSuccessful) {
                        addConflictLog(url, timestamp, response.code, true, getString(R.string.conflict_resolve_success, action, response.code))
                    } else {
                        addConflictLog(url, timestamp, response.code, false, getString(R.string.conflict_resolve_error, "HTTP ${response.code}"))
                    }
                }
            } catch (e: IOException) {
                addConflictLog(loggedUrl, timestamp, null, false, getString(R.string.conflict_resolve_error, e.message ?: "IOException"))
            }
        }
    }
    private fun addConflictLog(url: String, timestamp: Long, statusCode: Int?, success: Boolean, errorMessage: String) {
        preferencesManager.addWebhookLog(
            WebhookLog(
                id = UUID.randomUUID().toString(),
                timestamp = timestamp,
                url = url,
                statusCode = statusCode,
                success = success,
                errorMessage = errorMessage,
                dataType = "steps",
                recordCount = null,
                syncType = "conflict",
                deliveryFormat = WebhookLog.FORMAT_JSON
            )
        )
    }

    private fun applyLanguage(lang: String) {
        if (lang.isEmpty()) {
            AppCompatDelegate.setApplicationLocales(LocaleListCompat.getEmptyLocaleList())
        } else {
            AppCompatDelegate.setApplicationLocales(LocaleListCompat.forLanguageTags(lang))
        }
    }

    private fun applyPortraitMode(portraitOnly: Boolean) {
        requestedOrientation = if (portraitOnly) ActivityInfo.SCREEN_ORIENTATION_PORTRAIT else ActivityInfo.SCREEN_ORIENTATION_UNSPECIFIED
    }
}
