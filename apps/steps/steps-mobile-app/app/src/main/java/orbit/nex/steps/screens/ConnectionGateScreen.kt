package orbit.nex.steps.screens

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import orbit.nex.steps.HealthDataType
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.R
import orbit.nex.steps.WebhookConfig
import okhttp3.OkHttpClient
import okhttp3.Request

@Composable
fun ConnectionGateScreen(modifier: Modifier = Modifier, onConnected: () -> Unit) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val scope = rememberCoroutineScope()
    NexAuthScreen(deviceId = prefs.getDeviceId()) { origin, token ->
        scope.launch {
            val config = WebhookConfig("$origin/api/steps", "NexSteps", mapOf("Authorization" to "Bearer $token"))
            if (bindDevice(config, prefs)) {
                prefs.setWebhookConfigs(listOf(config))
                prefs.markConnectionEstablished()
                prefs.setEnabledDataTypes(prefs.getEnabledDataTypes().ifEmpty { HealthDataType.values().toSet() })
                prefs.markSyncSuccess()
                prefs.clearAuthError()
                onConnected()
            } else {
                android.widget.Toast.makeText(context, R.string.auth_invalid_link, android.widget.Toast.LENGTH_LONG).show()
            }
        }
    }
}

/** Parses nexsteps://auth and legacy stepmonitor://auth pairing links. */
fun parsePairingLink(value: String): Pair<String, String>? {
    val trimmed = value.trim()
    if (trimmed.isEmpty()) return null
    val uri = runCatching { Uri.parse(trimmed) }.getOrNull() ?: return null
    val scheme = uri.scheme?.lowercase().orEmpty()
    if (scheme != "nexsteps" && scheme != "stepmonitor" && scheme != "spends") {
        // A bare endpoint plus token is also accepted when pasted as query params.
        val endpoint = uri.getQueryParameter("endpoint").orEmpty()
        val token = uri.getQueryParameter("token").orEmpty()
        return if (endpoint.startsWith("http") && token.isNotBlank()) endpoint to token else null
    }
    val endpoint = uri.getQueryParameter("endpoint").orEmpty()
    val token = uri.getQueryParameter("token").orEmpty()
    if (!endpoint.startsWith("http") || token.isBlank()) return null
    return endpoint to token
}

/**
 * Calls the profile endpoint with the phone's device id so the server binds the
 * freshly issued token to this device before the app stores it.
 */
suspend fun bindDevice(config: WebhookConfig, prefs: PreferencesManager): Boolean = withContext(Dispatchers.IO) {
    runCatching {
        val builder = Request.Builder()
            .url(config.url.trimEnd('/') + "/profile")
            .addHeader("Accept", "application/json")
            .addHeader("X-Device-Id", prefs.getDeviceId())
        config.headers.forEach { (key, value) -> builder.addHeader(key, value) }
        OkHttpClient().newCall(builder.build()).execute().use { response ->
            if (response.code == 401 || response.code == 403) prefs.markAuthError(response.code)
            response.isSuccessful
        }
    }.getOrDefault(false)
}
