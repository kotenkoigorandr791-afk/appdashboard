package com.appdashboard.planning.ui.screens

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.BarChart
import androidx.compose.material.icons.outlined.Home
import androidx.compose.material.icons.outlined.Settings
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.navigationBarsPadding
import com.appdashboard.planning.PlanningApp
import com.appdashboard.planning.ui.navigation.MainTab
import com.appdashboard.planning.ui.theme.ThemeController
import com.flyfishxu.vetraui.core.VetraNavigationBar
import com.flyfishxu.vetraui.core.VetraNavigationBarItem

@Composable
fun MainShell(
    app: PlanningApp,
    themeController: ThemeController,
    onTask: (Int) -> Unit,
    onCreateTask: (Int) -> Unit,
    onDisconnected: () -> Unit,
) {
    var tab by rememberSaveable { mutableStateOf(MainTab.Home.name) }
    val current = runCatching { MainTab.valueOf(tab) }.getOrDefault(MainTab.Home)

    Column(Modifier.fillMaxSize()) {
        Box(Modifier.weight(1f)) {
            when (current) {
                MainTab.Home -> HomeScreen(
                    repository = app.repository,
                    session = app.session,
                    onTask = onTask,
                    onCreateTask = onCreateTask,
                    onOpenSettings = { tab = MainTab.Settings.name },
                )
                MainTab.Stats -> StatsScreen(
                    repository = app.repository,
                    session = app.session,
                )
                MainTab.Settings -> SettingsScreen(
                    session = app.session,
                    repository = app.repository,
                    themeController = themeController,
                    onDisconnected = onDisconnected,
                )
            }
        }
        VetraNavigationBar(modifier = Modifier.navigationBarsPadding()) {
            VetraNavigationBarItem(
                selected = current == MainTab.Home,
                onClick = { tab = MainTab.Home.name },
                icon = Icons.Outlined.Home,
                label = "Home",
            )
            VetraNavigationBarItem(
                selected = current == MainTab.Stats,
                onClick = { tab = MainTab.Stats.name },
                icon = Icons.Outlined.BarChart,
                label = "Stats",
            )
            VetraNavigationBarItem(
                selected = current == MainTab.Settings,
                onClick = { tab = MainTab.Settings.name },
                icon = Icons.Outlined.Settings,
                label = "Settings",
            )
        }
    }
}
