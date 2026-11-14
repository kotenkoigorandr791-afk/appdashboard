package orbit.nex.steps

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Button
import androidx.compose.material3.Checkbox
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Delete
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.MainScope
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONArray
import org.json.JSONObject
import orbit.nex.steps.ui.theme.HCWebhookTheme

private data class ManagedPoint(val id: String, val latitude: Double, val longitude: Double, val at: String)

class PointsActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent { HCWebhookTheme { PointsScreen { finish() } } }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PointsScreen(onBack: () -> Unit) {
    val context = androidx.compose.ui.platform.LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    var query by remember { mutableStateOf("") }
    var points by remember { mutableStateOf(emptyList<ManagedPoint>()) }
    var selected by remember { mutableStateOf(emptySet<String>()) }
    var loading by remember { mutableStateOf(true) }
    suspend fun load() {
        loading = true
        points = pointsRequest(prefs, query)
        selected = emptySet()
        loading = false
    }
    LaunchedEffect(query) { load() }
    Scaffold(topBar = { TopAppBar(title = { Text(stringResource(R.string.points_title)) }, navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, null) } }) }) { padding ->
        Column(Modifier.fillMaxSize().padding(padding).padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            OutlinedTextField(query, { query = it }, Modifier.fillMaxWidth(), label = { Text("Координаты") }, singleLine = true)
            if (selected.isNotEmpty()) Button(onClick = {
                val ids = selected
                MainScope().launch(Dispatchers.IO) { deletePoints(prefs, ids); withContext(Dispatchers.Main) { load() } }
            }) { Icon(Icons.Filled.Delete, null); Text("Удалить (${selected.size})") }
            if (loading) Text("Загрузка…")
            LazyColumn(Modifier.fillMaxSize()) {
                items(points, key = { it.id }) { point ->
                    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                        Checkbox(point.id in selected, { checked -> selected = if (checked) selected + point.id else selected - point.id })
                        Column { Text("${point.latitude}, ${point.longitude}"); Text(point.at, style = MaterialTheme.typography.bodySmall) }
                    }
                }
            }
        }
    }
}

private suspend fun pointsRequest(prefs: PreferencesManager, query: String): List<ManagedPoint> = withContext(Dispatchers.IO) {
    val config = prefs.getWebhookConfigs().firstOrNull { it.isEnabled } ?: return@withContext emptyList()
    val url = config.url.trimEnd('/') + "/geolocation?action=points&limit=500&q=" + java.net.URLEncoder.encode(query, "UTF-8")
    val request = Request.Builder().url(url).apply { config.headers.forEach { (k, v) -> addHeader(k, v) }; addHeader("X-Device-Id", prefs.getDeviceId()) }.build()
    OkHttpClient().newCall(request).execute().use { response ->
        if (!response.isSuccessful) return@withContext emptyList()
        val rows = JSONObject(response.body?.string().orEmpty()).optJSONArray("points") ?: JSONArray()
        List(rows.length()) { i -> rows.getJSONObject(i).let { ManagedPoint(it.getString("id"), it.getDouble("latitude"), it.getDouble("longitude"), it.optString("source_at")) } }
    }
}

private fun deletePoints(prefs: PreferencesManager, ids: Set<String>) {
    val config = prefs.getWebhookConfigs().firstOrNull { it.isEnabled } ?: return
    val body = JSONObject().put("ids", JSONArray(ids.toList())).toString().toRequestBody("application/json".toMediaType())
    val request = Request.Builder().url(config.url.trimEnd('/') + "/geolocation?action=delete").post(body).apply { config.headers.forEach { (k, v) -> addHeader(k, v) }; addHeader("X-Device-Id", prefs.getDeviceId()) }.build()
    OkHttpClient().newCall(request).execute().close()
}
