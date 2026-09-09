package com.stepmonitor.app
import android.content.Intent
import android.os.Bundle
import android.widget.Toast
import androidx.activity.compose.setContent
import androidx.appcompat.app.AppCompatActivity
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Icon
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import com.stepmonitor.app.screens.AboutScreen
import com.stepmonitor.app.screens.ConfigurationScreen
import com.stepmonitor.app.screens.LogsScreen
import com.stepmonitor.app.screens.StatisticsScreen
import com.stepmonitor.app.ui.theme.HCWebhookTheme
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
    }
}
