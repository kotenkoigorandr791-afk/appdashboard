package orbit.nex.steps

import android.os.Bundle
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.appcompat.app.AppCompatActivity
import orbit.nex.steps.screens.PersonalizationScreen
import orbit.nex.steps.ui.theme.HCWebhookTheme

/** Theme, accent, language and orientation on their own screen. */
class PersonalizationActivity : AppCompatActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            HCWebhookTheme {
                PersonalizationScreen(onBack = { finish() })
            }
        }
    }
}
