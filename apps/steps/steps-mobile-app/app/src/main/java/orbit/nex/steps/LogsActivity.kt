package orbit.nex.steps

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import orbit.nex.steps.components.AppBackground
import orbit.nex.steps.screens.LogsScreen
import orbit.nex.steps.ui.theme.HCWebhookTheme

/**
 * Hosts the shared [LogsScreen]. The screen draws its own header row (back,
 * search, sorting, filtering, export and clear), so there is exactly one bar.
 */
class LogsActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            HCWebhookTheme {
                AppBackground {
                    LogsScreen(
                        modifier = Modifier
                            .statusBarsPadding()
                            .navigationBarsPadding()
                            .padding(vertical = 8.dp),
                        onBack = { finish() },
                    )
                }
            }
        }
    }
}
