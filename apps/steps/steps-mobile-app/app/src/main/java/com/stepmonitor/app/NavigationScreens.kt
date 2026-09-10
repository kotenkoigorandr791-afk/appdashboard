package com.stepmonitor.app
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.List
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.QueryStats
import androidx.compose.material.icons.filled.Settings
import androidx.compose.ui.graphics.vector.ImageVector
sealed class NavigationScreen(val route: String, val titleResId: Int, val icon: ImageVector) {
    object Home : NavigationScreen("home", R.string.nav_home, Icons.Filled.Home)
    object Statistics : NavigationScreen("statistics", R.string.nav_statistics, Icons.Filled.QueryStats)
    object Logs : NavigationScreen("logs", R.string.nav_logs, Icons.AutoMirrored.Filled.List)
    object About : NavigationScreen("about", R.string.nav_about, Icons.Filled.Settings)
}
val bottomNavItems = listOf(NavigationScreen.Home, NavigationScreen.Statistics, NavigationScreen.Logs, NavigationScreen.About)
