package com.stepmonitor.app.screens
import android.app.Activity
import android.content.Intent
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Download
import androidx.compose.material.icons.filled.ExpandLess
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material.icons.filled.LinkOff
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Checkbox
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.TextButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.core.content.FileProvider
import coil.compose.AsyncImage
import com.stepmonitor.app.HealthDataType
import com.stepmonitor.app.PreferencesManager
import com.stepmonitor.app.R
import com.stepmonitor.app.WebhookConfig
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.encodeToJsonElement
import kotlinx.serialization.json.put
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONObject
import com.journeyapps.barcodescanner.ScanContract
import com.journeyapps.barcodescanner.ScanOptions
import java.io.File
data class ServerProfile(val name: String = "", val email: String = "", val photo: String = "", val error: String? = null)
@Composable
fun AboutScreen(modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val scope = rememberCoroutineScope()
    var configs by remember { mutableStateOf(prefs.getWebhookConfigs()) }
    var enabled by remember { mutableStateOf(prefs.getEnabledDataTypes().ifEmpty { HealthDataType.values().toSet() }) }
    var link by remember { mutableStateOf("") }
    var status by remember { mutableStateOf(if (configs.any { it.isEnabled }) context.getString(R.string.auth_connected) else context.getString(R.string.auth_waiting)) }
    var profile by remember { mutableStateOf(profileFromJson(prefs.getProfileCache(PROFILE_CACHE_MAX_AGE_MS)) ?: ServerProfile()) }
    var metricsExpanded by remember { mutableStateOf(false) }
    var wifiOnly by remember { mutableStateOf(prefs.isWifiOnly()) }
    var themeMode by remember { mutableStateOf(prefs.getThemeMode()) }
    var accentColor by remember { mutableStateOf(prefs.getAccentColor()) }
    fun connect(value: String) {
        runCatching {
            val uri = Uri.parse(value.trim())
            val endpoint = uri.getQueryParameter("endpoint").orEmpty()
            val token = uri.getQueryParameter("token").orEmpty()
            require(endpoint.startsWith("http") && token.isNotBlank())
            configs = listOf(WebhookConfig(endpoint, "StepMonitor", mapOf("Authorization" to "Bearer $token")))
            prefs.setWebhookConfigs(configs)
            prefs.setEnabledDataTypes(enabled)
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
        profile = profileFromJson(prefs.getProfileCache(PROFILE_CACHE_MAX_AGE_MS)) ?: fetchProfile(configs.firstOrNull { it.isEnabled }, prefs)
    }
    LazyColumn(modifier.fillMaxSize().padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Row(Modifier.padding(16.dp), horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                    AsyncImage(model = profile.photo.takeIf { it.isNotBlank() }, contentDescription = null, contentScale = ContentScale.Crop, modifier = Modifier.size(56.dp).clip(CircleShape))
                    Column(Modifier.weight(1f)) {
                        Text(profile.name.ifBlank { stringResource(R.string.app_name) }, style = MaterialTheme.typography.titleMedium)
                        Text(profile.email.ifBlank { profile.error ?: status }, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
            }
        }
        item {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(stringResource(R.string.auth_title), style = MaterialTheme.typography.titleMedium)
                    Text(status, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Button(onClick = {
                        scanner.launch(ScanOptions().apply {
                            setDesiredBarcodeFormats(ScanOptions.QR_CODE)
                            setPrompt(context.getString(R.string.auth_scan_qr))
                            setBeepEnabled(false)
                            setOrientationLocked(false)
                        })
                    }, modifier = Modifier.fillMaxWidth()) {
                        Icon(Icons.Filled.QrCodeScanner, contentDescription = null)
                        Text(stringResource(R.string.auth_scan_qr), modifier = Modifier.padding(start = 8.dp))
                    }
                    OutlinedTextField(value = link, onValueChange = { link = it }, modifier = Modifier.fillMaxWidth(), label = { Text(stringResource(R.string.auth_link_label)) })
                    Button(onClick = { connect(link) }, modifier = Modifier.fillMaxWidth()) { Text(stringResource(R.string.auth_connect)) }
                    OutlinedButton(onClick = {
                        scope.launch {
                            status = testConnection(configs.firstOrNull { it.isEnabled })
                        }
                    }, enabled = configs.any { it.isEnabled }, modifier = Modifier.fillMaxWidth()) { Text(stringResource(R.string.settings_test_connection)) }
                    OutlinedButton(onClick = {
                        configs = emptyList()
                        prefs.setWebhookConfigs(emptyList())
                        status = context.getString(R.string.auth_waiting)
                        profile = ServerProfile()
                    }, enabled = configs.isNotEmpty(), modifier = Modifier.fillMaxWidth()) {
                        Icon(Icons.Filled.LinkOff, contentDescription = null)
                        Text(stringResource(R.string.auth_disconnect), modifier = Modifier.padding(start = 8.dp))
                    }
                }
            }
        }
        item {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(stringResource(R.string.settings_network), style = MaterialTheme.typography.titleMedium)
                    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                        Checkbox(checked = wifiOnly, onCheckedChange = {
                            wifiOnly = it
                            prefs.setWifiOnly(it)
                            (context.applicationContext as? com.stepmonitor.app.HCWebhookApplication)?.scheduleSyncWork()
                        })
                        Text(stringResource(R.string.settings_wifi_only), modifier = Modifier.weight(1f))
                    }
                }
            }
        }
        item {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(stringResource(R.string.settings_personalization), style = MaterialTheme.typography.titleMedium)
                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        listOf("auto" to R.string.theme_auto, "light" to R.string.theme_light, "dark" to R.string.theme_dark).forEach { (value, label) ->
                            TextButton(onClick = {
                                themeMode = value
                                prefs.setThemeMode(value)
                                (context as? Activity)?.recreate()
                            }) { Text((if (themeMode == value) "✓ " else "") + stringResource(label)) }
                        }
                    }
                    listOf("blue", "green", "teal", "orange", "red", "pink", "purple", "gray").chunked(4).forEach { row ->
                        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            row.forEach { value ->
                                TextButton(onClick = {
                                    accentColor = value
                                    prefs.setAccentColor(value)
                                    (context as? Activity)?.recreate()
                                }) { Text((if (accentColor == value) "✓ " else "") + stringResource(accentNameRes(value))) }
                            }
                        }
                    }
                }
            }
        }
        item {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Row(
                        Modifier.fillMaxWidth().clickable { metricsExpanded = !metricsExpanded },
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(stringResource(R.string.settings_metrics), style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
                        Text("${enabled.size}/${HealthDataType.values().size}", color = MaterialTheme.colorScheme.onSurfaceVariant)
                        IconButton(onClick = { metricsExpanded = !metricsExpanded }) {
                            Icon(if (metricsExpanded) Icons.Filled.ExpandLess else Icons.Filled.ExpandMore, contentDescription = null)
                        }
                    }
                    if (metricsExpanded) {
                        HealthDataType.values().forEach { type ->
                            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                Checkbox(checked = type in enabled, onCheckedChange = { checked ->
                                    enabled = if (checked) enabled + type else enabled - type
                                    prefs.setEnabledDataTypes(enabled)
                                })
                                Text(stringResource(type.nameResId), modifier = Modifier.weight(1f))
                            }
                        }
                    }
                }
            }
        }
        item {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(stringResource(R.string.health_export_title), style = MaterialTheme.typography.titleMedium)
                    Button(onClick = {
                        val exportDir = File(context.cacheDir, "exports").apply { mkdirs() }
                        val file = File(exportDir, "stepmonitor-health-export.json")
                        val json = Json { prettyPrint = true }
                        val payload = buildJsonObject {
                            put("exportedAt", java.time.Instant.now().toString())
                            put("enabledDataTypes", JsonArray(enabled.map { json.encodeToJsonElement(it.name) }))
                            put("logs", json.encodeToJsonElement(prefs.getWebhookLogs()))
                        }
                        file.writeText(json.encodeToString(kotlinx.serialization.json.JsonObject.serializer(), payload))
                        val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
                        context.startActivity(Intent.createChooser(Intent(Intent.ACTION_SEND).apply {
                            type = "application/json"
                            putExtra(Intent.EXTRA_STREAM, uri)
                            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                        }, context.getString(R.string.health_export_title)))
                    }, modifier = Modifier.fillMaxWidth()) {
                        Icon(Icons.Filled.Download, contentDescription = null)
                        Text(stringResource(R.string.health_export_title), modifier = Modifier.padding(start = 8.dp))
                    }
                }
            }
        }
    }
}
private suspend fun fetchProfile(config: WebhookConfig?, prefs: PreferencesManager): ServerProfile = withContext(Dispatchers.IO) {
    if (config == null) return@withContext ServerProfile()
    runCatching {
        val profileUrl = config.url.trimEnd('/') + "/profile"
        val builder = Request.Builder().url(profileUrl)
        config.headers.forEach { (key, value) -> builder.addHeader(key, value) }
        OkHttpClient().newCall(builder.build()).execute().use { response ->
            if (!response.isSuccessful) error("HTTP ${response.code}")
            val profile = JSONObject(response.body?.string().orEmpty()).optJSONObject("profile") ?: JSONObject()
            val result = ServerProfile(
                name = profile.optString("name"),
                email = profile.optString("email"),
                photo = absoluteProfileUrl(config.url, profile.optString("photo"))
            )
            prefs.setProfileCache(JSONObject().put("name", result.name).put("email", result.email).put("photo", result.photo).toString())
            result
        }
    }.getOrElse { ServerProfile(error = it.message) }
}
private suspend fun testConnection(config: WebhookConfig?): String = withContext(Dispatchers.IO) {
    if (config == null) return@withContext "Not connected"
    runCatching {
        val builder = Request.Builder().url(config.url.trimEnd('/') + "/profile")
        config.headers.forEach { (key, value) -> builder.addHeader(key, value) }
        OkHttpClient().newCall(builder.build()).execute().use { response ->
            if (!response.isSuccessful) error("HTTP ${response.code}")
            "Connection OK"
        }
    }.getOrElse { "Connection failed: ${it.message}" }
}
private fun profileFromJson(json: String?): ServerProfile? = runCatching {
    if (json.isNullOrBlank()) null else JSONObject(json).let { ServerProfile(it.optString("name"), it.optString("email"), it.optString("photo")) }
}.getOrNull()
private fun accentNameRes(value: String): Int = when (value) {
    "green" -> R.string.accent_green
    "teal" -> R.string.accent_teal
    "orange" -> R.string.accent_orange
    "red" -> R.string.accent_red
    "pink" -> R.string.accent_pink
    "purple" -> R.string.accent_purple
    "gray" -> R.string.accent_gray
    else -> R.string.accent_blue
}
private const val PROFILE_CACHE_MAX_AGE_MS = 24L * 60 * 60 * 1000
private fun absoluteProfileUrl(endpoint: String, photo: String): String {
    if (photo.isBlank() || photo.startsWith("http")) return photo
    val uri = Uri.parse(endpoint)
    return "${uri.scheme}://${uri.authority}/${photo.trimStart('/')}"
}
