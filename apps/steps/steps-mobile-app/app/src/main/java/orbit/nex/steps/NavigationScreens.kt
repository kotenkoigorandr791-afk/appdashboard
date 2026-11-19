package orbit.nex.steps
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.QueryStats
import androidx.compose.material.icons.filled.Settings
import androidx.compose.ui.graphics.vector.ImageVector
sealed class NavigationScreen(val route: String, val titleResId: Int, val icon: ImageVector) {
    object Home : NavigationScreen("home", R.string.nav_home, Icons.Filled.Home)
    object Tracking : NavigationScreen("tracking", R.string.nav_tracking, Icons.Filled.LocationOn)
    object Statistics : NavigationScreen("statistics", R.string.nav_statistics, Icons.Filled.QueryStats)
    object About : NavigationScreen("settings", R.string.nav_about, Icons.Filled.Settings)
}
val bottomNavItems = listOf(NavigationScreen.Home, NavigationScreen.Tracking, NavigationScreen.Statistics, NavigationScreen.About)
