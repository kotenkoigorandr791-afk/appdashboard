package orbit.nex.steps.screens

import android.content.Intent
import android.net.Uri
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateContentSize
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.animation.togetherWith
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.ui.draw.clip
import androidx.compose.foundation.gestures.detectHorizontalDragGestures
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.platform.LocalContext
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URI
import java.net.URL
import orbit.nex.steps.R

private fun authOrigin(value: String): String? {
    val input = value.trim()
    if (input.isEmpty() || input.any { it.isWhitespace() }) return null
    val candidate = if (input.contains("://")) input else "https://$input"
    val uri = runCatching { URI(candidate) }.getOrNull() ?: return null
    if (uri.scheme?.lowercase() !in listOf("http", "https") || uri.host.isNullOrBlank() || uri.userInfo != null || uri.query != null || uri.fragment != null || (uri.path.isNotEmpty() && uri.path != "/") || uri.port > 65535 || uri.port == 0) return null
    return "${uri.scheme.lowercase()}://${uri.rawAuthority}"
}
private suspend fun authPost(origin: String, path: String, device: String, body: JSONObject, fallbackError: String): JSONObject = withContext(Dispatchers.IO) {
    val connection = URL(origin + path).openConnection() as HttpURLConnection
    try {
        connection.requestMethod = "POST"
        connection.connectTimeout = 15000
        connection.readTimeout = 15000
        connection.instanceFollowRedirects = false
        connection.doOutput = true
        connection.setRequestProperty("Content-Type", "application/json")
        connection.setRequestProperty("Accept", "application/json")
        connection.setRequestProperty("X-Device-Id", device)
        connection.outputStream.use { it.write(body.toString().toByteArray(Charsets.UTF_8)) }
        val response = (if (connection.responseCode in 200..299) connection.inputStream else connection.errorStream)?.bufferedReader()?.use { it.readText() }.orEmpty()
        val result = JSONObject(response)
        if (connection.responseCode !in 200..299 || !result.optBoolean("ok")) error(result.optString("error", fallbackError))
        result
    } finally { connection.disconnect() }
}
@Composable
fun NexAuthScreen(deviceId: String, onToken: (String, String) -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val domainPreferences = remember { context.getSharedPreferences("nex_auth", android.content.Context.MODE_PRIVATE) }
    var domain by remember { mutableStateOf(domainPreferences.getString("domain", "").orEmpty()) }
    var origin by remember { mutableStateOf("") }
    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var showPassword by remember { mutableStateOf(false) }
    var busy by remember { mutableStateOf(false) }
    var authJob by remember { mutableStateOf<Job?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var page by remember { mutableIntStateOf(0) }
    val features = listOf(stringResource(R.string.nex_auth_feature_1), stringResource(R.string.nex_auth_feature_2), stringResource(R.string.nex_auth_feature_3), stringResource(R.string.nex_auth_feature_4))
    val icons = listOf(Icons.Default.DirectionsWalk, Icons.Default.EmojiEvents, Icons.Default.Map, Icons.Default.CloudSync)
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).imePadding().navigationBarsPadding().padding(20.dp), verticalArrangement = Arrangement.Center, horizontalAlignment = Alignment.CenterHorizontally) {
        Card(Modifier.fillMaxWidth().animateContentSize(tween(300)), colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)) {
            Column(Modifier.fillMaxWidth().padding(20.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Image(painterResource(R.drawable.nex_steps_icon), contentDescription = null, modifier = Modifier.size(72.dp).clip(RoundedCornerShape(18.dp)))
                Text("NexSteps", style = MaterialTheme.typography.headlineSmall)
                if (origin.isEmpty()) {
                    AnimatedContent(targetState = page, transitionSpec = {
                        val direction = if (targetState > initialState) 1 else -1
                        (slideInHorizontally(tween(350)) { it * direction } + fadeIn(tween(350))) togetherWith
                            (slideOutHorizontally(tween(350)) { -it * direction } + fadeOut(tween(350)))
                    }, label = "features") { index ->
                        Column(Modifier.fillMaxWidth().height(125.dp).pointerInput(Unit) { var delta = 0f; detectHorizontalDragGestures(onDragEnd = { if (delta < -35) page = (page + 1).coerceAtMost(3) else if (delta > 35) page = (page - 1).coerceAtLeast(0); delta = 0f }, onHorizontalDrag = { _, amount -> delta += amount }) }, horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
                            Surface(shape = CircleShape, color = MaterialTheme.colorScheme.primaryContainer, modifier = Modifier.size(64.dp)) {
                                Box(contentAlignment = Alignment.Center) {
                                    Icon(icons[index], null, Modifier.size(34.dp), tint = MaterialTheme.colorScheme.onPrimaryContainer)
                                }
                            }
                            Text(features[index], textAlign = TextAlign.Center, style = MaterialTheme.typography.bodyMedium)
                        }
                    }
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                        repeat(4) { i ->
                            val width by animateDpAsState(if (i == page) 22.dp else 8.dp, tween(300), label = "dotWidth")
                            val dot by animateColorAsState(if (i == page) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.outlineVariant, tween(300), label = "dotColor")
                            Box(Modifier.height(8.dp).width(width).clip(CircleShape).background(dot).clickable { page = i })
                        }
                    }
                    OutlinedTextField(domain, { domain = it; domainPreferences.edit().putString("domain", it).apply(); error = null }, Modifier.fillMaxWidth(), leadingIcon = { Icon(Icons.Default.Language, null) }, shape = RoundedCornerShape(20.dp), label = { Text(stringResource(R.string.nex_auth_server)) }, placeholder = { Text("nxorbit.top") }, singleLine = true)
                    Button(onClick = { val result = authOrigin(domain); if (result == null) error = context.getString(R.string.nex_auth_invalid_domain) else { origin = result; error = null } }, modifier = Modifier.fillMaxWidth()) { Text(stringResource(R.string.nex_auth_continue)) }
                } else {
                    Text(origin, style = MaterialTheme.typography.bodySmall)
                    Button(onClick = {
                        busy = true; error = null
                        authJob = scope.launch {
                            try {
                                val req = authPost(origin, "/public-api/auth/client_auth/request", deviceId, JSONObject().put("platform", "nexsteps").put("device_name", "NexSteps"), context.getString(R.string.nex_auth_failed))
                                val browser = Uri.parse(req.getString("browser_url"))
                                val browserUri = Uri.parse(origin).buildUpon().encodedPath(browser.encodedPath).encodedQuery(browser.encodedQuery).build()
                                context.startActivity(Intent(Intent.ACTION_VIEW, browserUri))
                                var complete = false
                                repeat(300) {
                                    if (!complete) {
                                        delay(2000)
                                        val result = try { authPost(origin, "/public-api/auth/client_auth/poll", deviceId, JSONObject().put("request_id", req.getString("request_id")).put("poll_token", req.getString("poll_token")), context.getString(R.string.nex_auth_failed)) } catch (_: java.io.IOException) { null }
                                        if (result?.optString("status") == "approved") { complete = true; onToken(origin, result.getString("token")) }
                                        if (result?.optString("status") == "expired") error(context.getString(R.string.nex_auth_expired))
                                    }
                                }
                                if (!complete) error(context.getString(R.string.nex_auth_expired))
                            } catch (e: CancellationException) { throw e } catch (e: Exception) { error = when (e.message) { "invalid_credentials" -> context.getString(R.string.nex_auth_invalid_credentials); "email_not_verified" -> context.getString(R.string.nex_auth_unverified); context.getString(R.string.nex_auth_expired) -> e.message; else -> context.getString(R.string.nex_auth_connection_error) } } finally { busy = false }
                        }
                    }, enabled = !busy, modifier = Modifier.fillMaxWidth()) { Text(stringResource(R.string.nex_auth_nexorbit)) }
                    OutlinedButton(onClick = { showPassword = !showPassword }, enabled = !busy, modifier = Modifier.fillMaxWidth()) { Text(stringResource(R.string.nex_auth_password_login)) }
                    AnimatedVisibility(visible = showPassword) {
                        Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        OutlinedTextField(email, { email = it }, Modifier.fillMaxWidth(), leadingIcon = { Icon(Icons.Default.Email, null) }, shape = RoundedCornerShape(20.dp), label = { Text(stringResource(R.string.nex_auth_email)) }, singleLine = true)
                        OutlinedTextField(password, { password = it }, Modifier.fillMaxWidth(), leadingIcon = { Icon(Icons.Default.Lock, null) }, shape = RoundedCornerShape(20.dp), label = { Text(stringResource(R.string.nex_auth_password)) }, singleLine = true, visualTransformation = PasswordVisualTransformation())
                        Button(onClick = {
                            busy = true; error = null
                            authJob = scope.launch {
                                try {
                                    val result = authPost(origin, "/public-api/auth/login", deviceId, JSONObject().put("email", email.trim()).put("password", password).put("platform", "nexsteps").put("device_name", "NexSteps"), context.getString(R.string.nex_auth_failed))
                                    password = ""
                                    onToken(origin, result.getString("token"))
                                } catch (e: CancellationException) { throw e } catch (e: Exception) { error = when (e.message) { "invalid_credentials" -> context.getString(R.string.nex_auth_invalid_credentials); "email_not_verified" -> context.getString(R.string.nex_auth_unverified); else -> context.getString(R.string.nex_auth_connection_error) } } finally { busy = false }
                            }
                        }, enabled = !busy && email.isNotBlank() && password.isNotEmpty(), modifier = Modifier.fillMaxWidth()) { Text(stringResource(R.string.nex_auth_login)) }
                        }
                    }
                    TextButton(onClick = { origin = ""; password = ""; error = null }, enabled = !busy) { Text(stringResource(R.string.nex_auth_change_domain)) }
                    if (busy) {
                        CircularProgressIndicator(Modifier.size(24.dp))
                        TextButton(onClick = { authJob?.cancel() }) { Text(stringResource(R.string.nex_auth_cancel)) }
                    }
                }
                error?.let { Text(it, color = MaterialTheme.colorScheme.error, textAlign = TextAlign.Center) }
            }
        }
    }
}
