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
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
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
val AppCornerRadius = 22.dp

/**
 * Bumped whenever a personalization value changes. [HCWebhookTheme] reads it,
 * so every activity that is still alive - including the ones sitting in the
 * back stack - recomposes with the new palette instead of keeping the colors
 * it was created with. Otherwise a change made on the personalization screen
 * would only be visible on that screen.
 */
object AppThemeRevision {
    var value by mutableStateOf(0)
        private set

    fun bump() {
        value += 1
    }
}

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
    // Subscribing to the revision makes the whole app re-read the personalization
    // values whenever they change on any screen.
    val revision = AppThemeRevision.value
    val mode = prefs.getThemeMode()
    val resolvedDarkTheme = darkTheme ?: when (mode) {
        "dark" -> true
        "light" -> false
        else -> isSystemInDarkTheme()
    }
    val accent = accentColor(prefs.getAccentColor(), resolvedDarkTheme)
    val amoledEnabled = prefs.isAmoledEnabled()
    val colorScheme = remember(revision, resolvedDarkTheme, dynamicColor) {
        when {
        dynamicColor && Build.VERSION.SDK_INT >= Build.VERSION_CODES.S -> {
            if (resolvedDarkTheme) dynamicDarkColorScheme(context) else dynamicLightColorScheme(context)
        }

        resolvedDarkTheme -> darkColorScheme(primary = accent, secondary = BlueGrey80, tertiary = accent)
        else -> lightColorScheme(primary = accent, secondary = BlueGrey40, tertiary = accent)
    }.let { scheme ->
        if (resolvedDarkTheme && amoledEnabled) {
            // Pure white (#FFFFFF) on a pure black panel measures 21:1, which is
            // far above what dark-mode guidance recommends: the edges of glyphs
            // bloom and long sessions strain the eyes. The AMOLED palette below
            // keeps true black for the panel while landing primary text around
            // 14:1, secondary text around 9:1 and accents at 9-12:1, i.e. above
            // WCAG AAA (7:1) without the harsh maximum contrast.
            scheme.copy(
                background = Color(0xFF000000),
                surface = Color(0xFF0A0A0D),
                surfaceVariant = Color(0xFF14141A),
                onBackground = Color(0xFFD2D7E0),
                onSurface = Color(0xFFD2D7E0),
                onSurfaceVariant = Color(0xFFA6AEBD),
                outline = Color(0xFF3A3A45),
                outlineVariant = Color(0xFF232329),
                primaryContainer = Color(0xFF101820),
                onPrimaryContainer = accent,
                secondaryContainer = Color(0xFF1A1A22),
                onSecondaryContainer = Color(0xFFCBD3E0),
                inverseSurface = Color(0xFFD2D7E0),
                inverseOnSurface = Color(0xFF101014)
            )
        } else scheme
    }
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
