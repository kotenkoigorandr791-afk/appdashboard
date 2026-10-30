package orbit.nex.steps.ui.theme

import android.app.Activity
import android.os.Build
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Shapes
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.dynamicDarkColorScheme
import androidx.compose.material3.dynamicLightColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.SideEffect
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.unit.dp
import androidx.core.view.WindowCompat
import orbit.nex.steps.PreferencesManager

/**
 * One corner radius for every surface in the app. Cards, text fields, dropdown
 * menus, dialogs, sheets and buttons all resolve to the same 18 dp rounding,
 * so no screen mixes pill fields with square boxes any more.
 */
val AppCornerRadius = 18.dp

private val AppShapes = Shapes(
    extraSmall = RoundedCornerShape(AppCornerRadius),
    small = RoundedCornerShape(AppCornerRadius),
    medium = RoundedCornerShape(AppCornerRadius),
    large = RoundedCornerShape(AppCornerRadius),
    extraLarge = RoundedCornerShape(AppCornerRadius),
)

private val DarkColorScheme = darkColorScheme(
    primary = Blue80,
    secondary = BlueGrey80,
    tertiary = LightBlue80
)

private val LightColorScheme = lightColorScheme(
    primary = Blue40,
    secondary = BlueGrey40,
    tertiary = LightBlue40
)

@Composable
fun HCWebhookTheme(
    darkTheme: Boolean? = null,
    // Dynamic color is available on Android 12+
    dynamicColor: Boolean = false,
    content: @Composable () -> Unit
) {
    val context = LocalContext.current
    val prefs = PreferencesManager(context)
    val mode = prefs.getThemeMode()
    val resolvedDarkTheme = darkTheme ?: when (mode) {
        "dark" -> true
        "light" -> false
        else -> isSystemInDarkTheme()
    }
    val accent = accentColor(prefs.getAccentColor(), resolvedDarkTheme)
    val amoledEnabled = prefs.isAmoledEnabled()
    val colorScheme = when {
        dynamicColor && Build.VERSION.SDK_INT >= Build.VERSION_CODES.S -> {
            if (resolvedDarkTheme) dynamicDarkColorScheme(context) else dynamicLightColorScheme(context)
        }

        resolvedDarkTheme -> darkColorScheme(primary = accent, secondary = BlueGrey80, tertiary = accent)
        else -> lightColorScheme(primary = accent, secondary = BlueGrey40, tertiary = accent)
    }.let { scheme ->
        if (resolvedDarkTheme && amoledEnabled) {
            scheme.copy(
                background = Color.Black,
                surface = Color.Black,
                surfaceVariant = Color.Black,
                primaryContainer = Color.Black,
                onPrimaryContainer = Color.White
            )
        } else scheme
    }
    val view = LocalView.current
    if (!view.isInEditMode) {
        SideEffect {
            val window = (view.context as Activity).window
            @Suppress("DEPRECATION")
            window.statusBarColor = colorScheme.surface.toArgb()
            WindowCompat.getInsetsController(window, view).isAppearanceLightStatusBars = !resolvedDarkTheme
        }
    }

    MaterialTheme(
        colorScheme = colorScheme,
        typography = Typography,
        shapes = AppShapes,
        content = content
    )
}
private fun accentColor(name: String, dark: Boolean) = when (name) {
    "teal" -> if (dark) androidx.compose.ui.graphics.Color(0xFF78D9C4) else androidx.compose.ui.graphics.Color(0xFF00897B)
    "orange" -> if (dark) androidx.compose.ui.graphics.Color(0xFFFFB86C) else androidx.compose.ui.graphics.Color(0xFFE8710A)
    "pink" -> if (dark) androidx.compose.ui.graphics.Color(0xFFFF8BC7) else androidx.compose.ui.graphics.Color(0xFFD81B60)
    "purple" -> if (dark) androidx.compose.ui.graphics.Color(0xFFD0BCFF) else androidx.compose.ui.graphics.Color(0xFF7E57C2)
    "gray" -> if (dark) androidx.compose.ui.graphics.Color(0xFFCBD5E1) else androidx.compose.ui.graphics.Color(0xFF475569)
    else -> if (dark) Blue80 else Blue40
}
