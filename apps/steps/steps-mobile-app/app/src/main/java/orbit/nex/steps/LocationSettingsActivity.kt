package orbit.nex.steps

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import orbit.nex.steps.screens.LocationSettingsScreen
import orbit.nex.steps.ui.theme.HCWebhookTheme

/**
 * Location tracking settings on their own screen.
 *
 * The Tracking tab is the map; accuracy, interval, permissions and the privacy
 * explanation live here so they are one tap away from both the map and the
 * settings list.
 */
class LocationSettingsActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        setContent {
            HCWebhookTheme {
                LocationSettingsScreen(onBack = { finish() })
            }
        }
    }
}
