package com.stepmonitor.app.screens
import android.app.Activity
import android.content.Intent
import android.net.Uri
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.ArrowForward
import androidx.compose.material.icons.filled.QrCodeScanner
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
import com.stepmonitor.app.HealthConnectManager
import com.stepmonitor.app.HealthData
import com.stepmonitor.app.HealthDataType
import com.stepmonitor.app.PreferencesManager
import com.stepmonitor.app.R
import com.stepmonitor.app.WebhookConfig
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.time.LocalDate
import java.time.ZoneId
data class HealthDay(val rows: List<Pair<HealthDataType, Int>> = emptyList(), val error: String? = null)
@Composable
fun StatisticsScreen(modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    val scope = rememberCoroutineScope()
    var offset by remember { mutableIntStateOf(0) }
    var link by remember { mutableStateOf("") }
    var status by remember { mutableStateOf(if (prefs.getWebhookConfigs().any { it.isEnabled }) context.getString(R.string.auth_connected) else context.getString(R.string.auth_waiting)) }
    var connected by remember { mutableStateOf(prefs.getWebhookConfigs().any { it.isEnabled }) }
    var day by remember { mutableStateOf(HealthDay()) }
    val date = LocalDate.now().plusDays(offset.toLong())
    fun connect(value: String) {
        runCatching {
            val uri = Uri.parse(value.trim())
            val endpoint = uri.getQueryParameter("endpoint").orEmpty()
            val token = uri.getQueryParameter("token").orEmpty()
            require(endpoint.startsWith("http") && token.isNotBlank())
            prefs.setWebhookConfigs(listOf(WebhookConfig(endpoint, "StepMonitor", mapOf("Authorization" to "Bearer $token"))))
            prefs.setEnabledDataTypes(HealthDataType.values().toSet())
            connected = true
            status = context.getString(R.string.auth_connected)
            link = ""
        }.onFailure {
            status = context.getString(R.string.auth_invalid_link)
        }
    }
    val scanner = rememberLauncherForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
        if (result.resultCode == Activity.RESULT_OK) {
            val scanned = result.data?.getStringExtra("SCAN_RESULT").orEmpty()
            if (scanned.isNotBlank()) connect(scanned)
        }
    }
    LaunchedEffect(connected, date) {
        if (!connected) return@LaunchedEffect
        day = readHealthDay(context, prefs, date)
    }
    Column(modifier.fillMaxSize().padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        if (!connected) {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(stringResource(R.string.auth_title), style = MaterialTheme.typography.titleLarge)
                    Text(status, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Button(onClick = {
                        runCatching {
                            scanner.launch(Intent("com.google.zxing.client.android.SCAN").putExtra("SCAN_MODE", "QR_CODE_MODE"))
                        }.onFailure {
                            Toast.makeText(context, context.getString(R.string.auth_camera_unavailable), Toast.LENGTH_LONG).show()
                        }
                    }, modifier = Modifier.fillMaxWidth()) {
                        Icon(Icons.Filled.QrCodeScanner, contentDescription = null)
                        Text(stringResource(R.string.auth_scan_qr), modifier = Modifier.padding(start = 8.dp))
                    }
                    OutlinedTextField(value = link, onValueChange = { link = it }, modifier = Modifier.fillMaxWidth(), label = { Text(stringResource(R.string.auth_link_label)) })
                    Button(onClick = { connect(link) }, modifier = Modifier.fillMaxWidth()) { Text(stringResource(R.string.auth_connect)) }
                }
            }
            return@Column
        }
        Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
            IconButton(onClick = { offset-- }) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = null) }
            Spacer(Modifier.width(8.dp))
            Text(date.toString(), style = MaterialTheme.typography.titleLarge, modifier = Modifier.weight(1f))
            IconButton(onClick = { if (offset < 0) offset++ }) { Icon(Icons.AutoMirrored.Filled.ArrowForward, contentDescription = null) }
        }
        Button(onClick = { scope.launch { day = readHealthDay(context, prefs, date) } }, modifier = Modifier.fillMaxWidth()) {
            Text(stringResource(R.string.dashboard_refresh))
        }
        day.error?.let { Text(it, color = MaterialTheme.colorScheme.error) }
        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            items(day.rows, key = { it.first.name }) { (type, count) ->
                Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                    Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                        Text(stringResource(type.nameResId), style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
                        Text(count.toString(), style = MaterialTheme.typography.headlineSmall)
                    }
                }
            }
        }
    }
}
private suspend fun readHealthDay(context: android.content.Context, prefs: PreferencesManager, date: LocalDate): HealthDay = withContext(Dispatchers.IO) {
    runCatching {
        val zone = ZoneId.systemDefault()
        val start = date.atStartOfDay(zone).toInstant()
        val end = date.plusDays(1).atStartOfDay(zone).toInstant()
        val enabled = prefs.getEnabledDataTypes().ifEmpty { HealthDataType.values().toSet() }
        HealthConnectManager(context).readHealthData(enabled, emptyMap(), start = start, end = end).getOrThrow().toRows()
    }.fold(
        onSuccess = { HealthDay(it) },
        onFailure = { HealthDay(error = it.message ?: context.getString(R.string.sync_failed)) }
    )
}
private fun HealthData.toRows(): List<Pair<HealthDataType, Int>> = listOf(
    HealthDataType.STEPS to steps.size,
    HealthDataType.SLEEP to sleep.size,
    HealthDataType.HEART_RATE to heartRate.size,
    HealthDataType.HEART_RATE_VARIABILITY to heartRateVariability.size,
    HealthDataType.DISTANCE to distance.size,
    HealthDataType.ACTIVE_CALORIES to activeCalories.size,
    HealthDataType.TOTAL_CALORIES to totalCalories.size,
    HealthDataType.WEIGHT to weight.size,
    HealthDataType.HEIGHT to height.size,
    HealthDataType.BLOOD_PRESSURE to bloodPressure.size,
    HealthDataType.BLOOD_GLUCOSE to bloodGlucose.size,
    HealthDataType.OXYGEN_SATURATION to oxygenSaturation.size,
    HealthDataType.BODY_TEMPERATURE to bodyTemperature.size,
    HealthDataType.SKIN_TEMPERATURE to skinTemperature.size,
    HealthDataType.RESPIRATORY_RATE to respiratoryRate.size,
    HealthDataType.RESTING_HEART_RATE to restingHeartRate.size,
    HealthDataType.EXERCISE to exercise.size,
    HealthDataType.HYDRATION to hydration.size,
    HealthDataType.NUTRITION to nutrition.size,
    HealthDataType.BASAL_METABOLIC_RATE to basalMetabolicRate.size,
    HealthDataType.BODY_FAT to bodyFat.size,
    HealthDataType.LEAN_BODY_MASS to leanBodyMass.size,
    HealthDataType.BODY_WATER_MASS to bodyWaterMass.size,
    HealthDataType.VO2_MAX to vo2Max.size,
    HealthDataType.BONE_MASS to boneMass.size,
    HealthDataType.MENSTRUATION_FLOW to menstruationFlow.size,
    HealthDataType.MENSTRUATION_PERIOD to menstruationPeriod.size,
    HealthDataType.INTERMENSTRUAL_BLEEDING to intermenstrualBleeding.size,
    HealthDataType.OVULATION_TEST to ovulationTest.size,
    HealthDataType.CERVICAL_MUCUS to cervicalMucus.size,
    HealthDataType.SEXUAL_ACTIVITY to sexualActivity.size,
    HealthDataType.BASAL_BODY_TEMPERATURE to basalBodyTemperature.size
).filter { it.second > 0 }
