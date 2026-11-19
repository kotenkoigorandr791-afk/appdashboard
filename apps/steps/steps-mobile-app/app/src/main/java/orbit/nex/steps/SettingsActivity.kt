package orbit.nex.steps

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import orbit.nex.steps.screens.SettingsScreen
import orbit.nex.steps.ui.theme.HCWebhookTheme

/**
 * Dedicated settings screen.
 *
 * Holds the user's personal data (profile name/email/photo and the body
 * parameters stored server side) together with every app setting, so the
 * profile tab stays a light overview and this one screen is the single
 * place where settings live.
 */
class SettingsActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        setContent {
            HCWebhookTheme {
                SettingsScreen(
                    onBack = { finish() },
                    section = runCatching { orbit.nex.steps.screens.SettingsSection.valueOf(intent.getStringExtra("section").orEmpty()) }.getOrDefault(orbit.nex.steps.screens.SettingsSection.PROFILE),
                )
            }
        }
    }
}
