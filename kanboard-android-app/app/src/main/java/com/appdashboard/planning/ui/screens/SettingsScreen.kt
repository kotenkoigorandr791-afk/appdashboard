package com.appdashboard.planning.ui.screens

import android.appwidget.AppWidgetManager
import android.content.ComponentName
import android.os.Build
import android.widget.Toast
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.Logout
import androidx.compose.material.icons.outlined.DarkMode
import androidx.compose.material.icons.outlined.LightMode
import androidx.compose.material.icons.outlined.Link
import androidx.compose.material.icons.outlined.PhoneAndroid
import androidx.compose.material.icons.outlined.Widgets
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.appdashboard.planning.data.prefs.SessionStore
import com.appdashboard.planning.data.prefs.ThemeMode
import com.appdashboard.planning.data.repo.PlanningRepository
import com.appdashboard.planning.ui.components.ProfileAvatar
import com.appdashboard.planning.ui.theme.ThemeController
import com.appdashboard.planning.ui.theme.canvasBrush
import com.appdashboard.planning.ui.theme.glassModifier
import com.appdashboard.planning.widget.TasksWidgetReceiver
import com.flyfishxu.vetraui.core.VetraChipFilter
import com.flyfishxu.vetraui.core.VetraDangerButton
import com.flyfishxu.vetraui.core.VetraOutlinedButton
import com.flyfishxu.vetraui.core.VetraSwitchWithLabel
import com.flyfishxu.vetraui.core.VetraTopAppBar
import com.flyfishxu.vetraui.core.theme.VetraTheme
import kotlinx.coroutines.launch

@Composable
fun SettingsScreen(
    session: SessionStore,
    repository: PlanningRepository,
    themeController: ThemeController,
    onDisconnected: () -> Unit,
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val colors = VetraTheme.colors
    val glass = session.translucentUi()
    val name = session.userName() ?: "User"

    Column(Modifier.fillMaxSize().background(canvasBrush())) {
        VetraTopAppBar(title = { Text("Settings", color = colors.textPrimary, fontWeight = FontWeight.Bold) })
        Column(
            Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            Row(
                Modifier.fillMaxWidth().then(glassModifier(glass)).padding(16.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                ProfileAvatar(name, size = 68.dp)
                Spacer(Modifier.width(16.dp))
                Column {
                    Text(name, color = colors.textPrimary, fontWeight = FontWeight.SemiBold)
                    Text(session.userEmail().orEmpty(), color = colors.textSecondary)
                    Text(session.userRole().orEmpty(), color = colors.brand)
                }
            }

            Text("Personalization", color = colors.textPrimary, fontWeight = FontWeight.SemiBold)
            Column(Modifier.fillMaxWidth().then(glassModifier(glass)).padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text("Theme", color = colors.textSecondary)
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    VetraChipFilter(
                        label = "Light",
                        selected = themeController.mode == ThemeMode.LIGHT,
                        onClick = {
                            themeController.mode = ThemeMode.LIGHT
                            session.setThemeMode(ThemeMode.LIGHT)
                        },
                        leadingIcon = Icons.Outlined.LightMode,
                    )
                    VetraChipFilter(
                        label = "Dark",
                        selected = themeController.mode == ThemeMode.DARK,
                        onClick = {
                            themeController.mode = ThemeMode.DARK
                            session.setThemeMode(ThemeMode.DARK)
                        },
                        leadingIcon = Icons.Outlined.DarkMode,
                    )
                    VetraChipFilter(
                        label = "Auto",
                        selected = themeController.mode == ThemeMode.AUTO,
                        onClick = {
                            themeController.mode = ThemeMode.AUTO
                            session.setThemeMode(ThemeMode.AUTO)
                        },
                        leadingIcon = Icons.Outlined.PhoneAndroid,
                    )
                }
                VetraSwitchWithLabel(
                    checked = glass,
                    onCheckedChange = { session.setTranslucentUi(it) },
                    label = "Glass / translucent surfaces",
                )
                Text(
                    "Auto follows the system appearance. Glass uses semi-transparent rounded cards.",
                    color = colors.textTertiary,
                )
            }

            Text("Connection", color = colors.textPrimary, fontWeight = FontWeight.SemiBold)
            Column(Modifier.fillMaxWidth().then(glassModifier(glass)).padding(16.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    androidx.compose.material3.Icon(Icons.Outlined.Link, null, tint = colors.brand)
                    Spacer(Modifier.width(8.dp))
                    Text("Paired endpoint", color = colors.textPrimary, fontWeight = FontWeight.Medium)
                }
                Spacer(Modifier.height(8.dp))
                Text(
                    session.endpoint().orEmpty(),
                    color = colors.textSecondary,
                    maxLines = 3,
                    overflow = TextOverflow.Ellipsis,
                )
                Spacer(Modifier.height(6.dp))
                Text("Device ${session.deviceId().take(8)}…", color = colors.textTertiary)
            }

            Text("Widgets", color = colors.textPrimary, fontWeight = FontWeight.SemiBold)
            Column(Modifier.fillMaxWidth().then(glassModifier(glass)).padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text(
                    "Pin open tasks to the home screen. The widget only syncs when you tap Refresh.",
                    color = colors.textSecondary,
                )
                VetraOutlinedButton(
                    onClick = {
                        val manager = AppWidgetManager.getInstance(context)
                        val provider = ComponentName(context, TasksWidgetReceiver::class.java)
                        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && manager.isRequestPinAppWidgetSupported) {
                            val ok = manager.requestPinAppWidget(provider, null, null)
                            if (!ok) {
                                Toast.makeText(context, "Could not request widget pin", Toast.LENGTH_SHORT).show()
                            }
                        } else {
                            Toast.makeText(
                                context,
                                "Long-press home → Widgets → Tasks",
                                Toast.LENGTH_LONG,
                            ).show()
                        }
                    },
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    androidx.compose.material3.Icon(Icons.Outlined.Widgets, null)
                    Spacer(Modifier.width(8.dp))
                    Text("Add home screen widget")
                }
            }

            VetraDangerButton(
                onClick = {
                    scope.launch {
                        repository.disconnect()
                        onDisconnected()
                    }
                },
                modifier = Modifier.fillMaxWidth(),
            ) {
                androidx.compose.material3.Icon(Icons.AutoMirrored.Outlined.Logout, null)
                Spacer(Modifier.width(8.dp))
                Text("Disconnect")
            }
            Spacer(Modifier.height(32.dp))
        }
    }
}
