package com.stepmonitor.app

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import com.stepmonitor.app.screens.SettingsScreen
import com.stepmonitor.app.ui.theme.HCWebhookTheme

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
                SettingsScreen(onBack = { finish() })
            }
        }
    }
}
