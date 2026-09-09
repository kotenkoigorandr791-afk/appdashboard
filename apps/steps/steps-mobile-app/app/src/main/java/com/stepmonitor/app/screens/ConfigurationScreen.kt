package com.stepmonitor.app.screens
import android.widget.Toast
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Sync
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.stepmonitor.app.PreferencesManager
import com.stepmonitor.app.R
import com.stepmonitor.app.SyncManager
import kotlinx.coroutines.launch
@Composable
fun ConfigurationScreen(modifier: Modifier = Modifier, onRequestPermissions: () -> Unit = {}) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val prefs = PreferencesManager(context)
    val connected = prefs.getWebhookConfigs().any { it.isEnabled }
    Column(modifier.fillMaxSize().padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(stringResource(R.string.home_title), style = MaterialTheme.typography.titleLarge)
                Text(if (connected) stringResource(R.string.auth_connected) else stringResource(R.string.auth_waiting), color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
        Button(onClick = onRequestPermissions, modifier = Modifier.fillMaxWidth()) {
            Text(stringResource(R.string.action_grant_permissions))
        }
        OutlinedButton(
            onClick = {
                scope.launch {
                    val result = SyncManager(context).performSync(timeRangeDays = 2, syncType = "manual")
                    Toast.makeText(context, if (result.isSuccess) context.getString(R.string.sync_success) else result.exceptionOrNull()?.message ?: context.getString(R.string.sync_failed), Toast.LENGTH_LONG).show()
                }
            },
            enabled = connected,
            modifier = Modifier.fillMaxWidth()
        ) {
            Icon(Icons.Filled.Sync, contentDescription = null)
            Text(stringResource(R.string.manual_sync_title), modifier = Modifier.padding(start = 8.dp))
        }
    }
}
