package com.stepmonitor.app.screens

import android.content.Intent
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccountCircle
import androidx.compose.material.icons.filled.CloudDone
import androidx.compose.material.icons.filled.CloudOff
import androidx.compose.material.icons.filled.LinkOff
import androidx.compose.material.icons.filled.PersonOff
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.key
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage
import com.stepmonitor.app.HCWebhookApplication
import com.stepmonitor.app.HealthConnectManager
import com.stepmonitor.app.HealthDataType
import com.stepmonitor.app.PreferencesManager
import com.stepmonitor.app.R
import com.stepmonitor.app.SettingsActivity
import com.stepmonitor.app.WebhookConfig
import com.stepmonitor.app.components.absoluteProfileUrl
import com.stepmonitor.app.components.downloadAvatarFile
import com.stepmonitor.app.components.localAvatarFile
import com.journeyapps.barcodescanner.ScanContract
import com.journeyapps.barcodescanner.ScanOptions
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONObject

/**
 * Profile tab: shows who is connected and links into [SettingsActivity], which
 * is the single screen holding the user's personal data and every app setting.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AboutScreen(modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val scope = rememberCoroutineScope()
    var configs by remember { mutableStateOf(prefs.getWebhookConfigs()) }
    var link by remember { mutableStateOf("") }
    var status by remember {
        mutableStateOf(if (prefs.isUserConnected()) context.getString(R.string.auth_connected) else context.getString(R.string.auth_waiting))
    }
    var testingConn by remember { mutableStateOf(false) }
    var connResult by remember { mutableStateOf<Boolean?>(null) }
    var profile by remember { mutableStateOf(profileFromJson(prefs.getProfileCache(Long.MAX_VALUE)) ?: ServerProfile()) }
    var profilePhotoModel by remember {
        mutableStateOf(localAvatarFile(context).takeIf { it.exists() }?.absolutePath ?: profile.photo.takeIf { it.isNotBlank() })
    }

    fun connect(value: String) {
        runCatching {
            val uri = Uri.parse(value.trim())
            val endpoint = uri.getQueryParameter("endpoint").orEmpty()
            val token = uri.getQueryParameter("token").orEmpty()
            require(endpoint.startsWith("http") && token.isNotBlank())
            configs = listOf(WebhookConfig(endpoint, "StepMonitor", mapOf("Authorization" to "Bearer $token")))
            prefs.setWebhookConfigs(configs)
            prefs.setEnabledDataTypes(prefs.getEnabledDataTypes().ifEmpty { HealthDataType.values().toSet() })
            status = context.getString(R.string.auth_connected)
            link = ""
        }.onFailure {
            status = context.getString(R.string.auth_invalid_link)
        }
    }

    val scanner = rememberLauncherForActivityResult(ScanContract()) { result ->
        result.contents?.takeIf { it.isNotBlank() }?.let { connect(it) }
    }

    LaunchedEffect(configs) {
        val config = configs.firstOrNull { it.isEnabled } ?: return@LaunchedEffect
        // Only refetch if cache is older than PROFILE_REFRESH_MAX_AGE_MS (throttle)
        if (prefs.getProfileCache(PROFILE_REFRESH_MAX_AGE_MS) != null) return@LaunchedEffect
        val fetched = fetchProfile(config, prefs)
        if (fetched.error == null) {
            // Download photo to local file for genuine offline caching
            if (fetched.photo.isNotBlank()) {
                downloadAvatarFile(context, fetched.photo)
            }
            profile = fetched
            profilePhotoModel = localAvatarFile(context).takeIf { it.exists() }?.absolutePath
                ?: fetched.photo.takeIf { it.isNotBlank() }
        }
    }

    LazyColumn(modifier.fillMaxSize().padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                        if (prefs.isUserConnected() && profilePhotoModel != null) {
                            key(profilePhotoModel) {
                                AsyncImage(
                                    model = profilePhotoModel,
                                    contentDescription = null,
                                    contentScale = ContentScale.Crop,
                                    modifier = Modifier.size(56.dp).clip(CircleShape)
                                )
                            }
                        } else {
                            val connected = prefs.isUserConnected()
                            Icon(
                                if (connected) Icons.Filled.AccountCircle else Icons.Filled.PersonOff,
                                contentDescription = null,
                                modifier = Modifier.size(56.dp).clip(CircleShape),
                                tint = if (connected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                        Column(Modifier.weight(1f)) {
                            Text(profile.name.ifBlank { stringResource(R.string.app_name) }, style = MaterialTheme.typography.titleMedium)
                            Text(
                                profile.email.ifBlank { profile.error ?: status },
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    }
                    Button(onClick = { context.startActivity(Intent(context, SettingsActivity::class.java)) }, modifier = Modifier.fillMaxWidth()) {
                        Icon(Icons.Filled.Settings, contentDescription = null)
                        Text(stringResource(R.string.settings_title), modifier = Modifier.padding(start = 8.dp))
                    }
                }
            }
        }
        item {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(stringResource(R.string.auth_title), style = MaterialTheme.typography.titleMedium)
                    Text(status, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    if (configs.any { it.isEnabled }) {
                        Button(
                            onClick = {
                                scope.launch {
                                    testingConn = true
                                    connResult = null
                                    connResult = testConnection(configs.firstOrNull { it.isEnabled }, prefs)
                                    testingConn = false
                                }
                            },
                            enabled = !testingConn,
                            colors = ButtonDefaults.buttonColors(
                                containerColor = when (connResult) {
                                    true -> Color(0xFF66BB6A)
                                    false -> Color(0xFF9B1C31)
                                    null -> MaterialTheme.colorScheme.primary
                                }
                            ),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            if (testingConn) {
                                CircularProgressIndicator(modifier = Modifier.size(18.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary)
                                Spacer(Modifier.size(8.dp))
                                Text(stringResource(R.string.settings_test_connection_progress))
                            } else {
                                Icon(Icons.Filled.CloudDone, contentDescription = null)
                                Text(stringResource(R.string.settings_test_connection), modifier = Modifier.padding(start = 8.dp))
                            }
                        }
                        OutlinedButton(onClick = {
                            configs = emptyList()
                            prefs.setWebhookConfigs(emptyList())
                            prefs.clearServerDataCache()
                            localAvatarFile(context).delete()
                            status = context.getString(R.string.auth_waiting)
                            profile = ServerProfile()
                            profilePhotoModel = null
                        }, modifier = Modifier.fillMaxWidth()) {
                            Icon(Icons.Filled.LinkOff, contentDescription = null)
                            Text(stringResource(R.string.auth_disconnect), modifier = Modifier.padding(start = 8.dp))
                        }
                    } else {
                        Column(
                            modifier = Modifier.fillMaxWidth().padding(vertical = 8.dp),
                            horizontalAlignment = Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            Surface(
                                modifier = Modifier.size(56.dp),
                                shape = CircleShape,
                                color = MaterialTheme.colorScheme.errorContainer
                            ) {
                                Icon(
                                    Icons.Filled.CloudOff,
                                    contentDescription = null,
                                    modifier = Modifier.padding(12.dp),
                                    tint = Color(0xFFFFC107)
                                )
                            }
                            Button(onClick = {
                                scanner.launch(ScanOptions().apply {
                                    setDesiredBarcodeFormats(ScanOptions.QR_CODE)
                                    setPrompt(context.getString(R.string.auth_scan_qr))
                                    setBeepEnabled(false)
                                    setOrientationLocked(true)
                                })
                            }, modifier = Modifier.fillMaxWidth()) {
                                Icon(Icons.Filled.QrCodeScanner, contentDescription = null)
                                Text(stringResource(R.string.auth_scan_qr), modifier = Modifier.padding(start = 8.dp))
                            }
                            OutlinedTextField(
                                value = link,
                                onValueChange = { link = it },
                                modifier = Modifier.fillMaxWidth(),
                                label = { Text(stringResource(R.string.auth_link_label)) }
                            )
                            Button(onClick = { connect(link) }, modifier = Modifier.fillMaxWidth()) {
                                Text(stringResource(R.string.auth_connect))
                            }
                        }
                    }
                }
            }
        }
    }
}

private suspend fun testConnection(config: WebhookConfig?, prefs: PreferencesManager): Boolean = withContext(Dispatchers.IO) {
    if (config == null) return@withContext false
    runCatching {
        val builder = Request.Builder().url(config.url.trimEnd('/') + "/profile")
        config.headers.forEach { (key, value) -> builder.addHeader(key, value) }
        builder.addHeader("X-Device-Id", prefs.getDeviceId())
        OkHttpClient().newCall(builder.build()).execute().use { response ->
            if (response.code == 401 || response.code == 403) prefs.markAuthError(response.code)
            if (!response.isSuccessful) error("HTTP ${response.code}")
            prefs.markSyncSuccess()
            true
        }
    }.getOrDefault(false)
}
