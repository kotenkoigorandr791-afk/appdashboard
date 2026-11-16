package orbit.nex.finance.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Typography
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.SideEffect
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp
import androidx.core.view.WindowCompat
import orbit.nex.finance.data.Appearance

object SpendsColors {
    val Income = Color(0xFF1FAF75)
    val Expense = Color(0xFFE24B63)
    val IncomeDark = Color(0xFF5AE0A8)
    val ExpenseDark = Color(0xFFFF8A9B)

    /** Positive amount color that stays legible in both themes. */
    @Composable fun income(): Color = if (isSystemInDarkTheme()) IncomeDark else Color(0xFF0F8A5B)

    /** Negative amount color that stays legible in both themes. */
    @Composable fun expense(): Color = if (isSystemInDarkTheme()) ExpenseDark else Color(0xFFC62442)
}

private val Type = Typography(
    headlineLarge = TextStyle(fontWeight = FontWeight.SemiBold, fontSize = 34.sp, letterSpacing = (-0.6).sp),
    headlineMedium = TextStyle(fontWeight = FontWeight.SemiBold, fontSize = 26.sp, letterSpacing = (-0.3).sp),
    titleLarge = TextStyle(fontWeight = FontWeight.SemiBold, fontSize = 20.sp),
    titleMedium = TextStyle(fontWeight = FontWeight.Medium, fontSize = 16.sp),
    bodyLarge = TextStyle(fontSize = 16.sp, lineHeight = 22.sp),
    labelLarge = TextStyle(fontWeight = FontWeight.Medium, fontSize = 12.sp, letterSpacing = 0.6.sp),
)

fun accentColor(name: String, dark: Boolean): Color = when (name) {
    "teal" -> if (dark) Color(0xFF78D9C4) else Color(0xFF00897B)
    "orange" -> if (dark) Color(0xFFFFB86C) else Color(0xFFE8710A)
    "pink" -> if (dark) Color(0xFFFF8BC7) else Color(0xFFD81B60)
    "purple" -> if (dark) Color(0xFFD0BCFF) else Color(0xFF7E57C2)
    "gray" -> if (dark) Color(0xFFCBD5E1) else Color(0xFF475569)
    else -> if (dark) Color(0xFF8AB4F8) else Color(0xFF2F6FE0)
}

@Composable
fun accentLabel(name: String): String = androidx.compose.ui.res.stringResource(
    when (name) {
        "teal" -> orbit.nex.finance.R.string.accent_teal
        "orange" -> orbit.nex.finance.R.string.accent_orange
        "pink" -> orbit.nex.finance.R.string.accent_pink
        "purple" -> orbit.nex.finance.R.string.accent_purple
        "gray" -> orbit.nex.finance.R.string.accent_gray
        else -> orbit.nex.finance.R.string.accent_blue
    }
)

@Composable
fun SpendsTheme(appearance: Appearance, content: @Composable () -> Unit) {
    val systemDark = isSystemInDarkTheme()
    val dark = when (appearance.theme) {
        "dark" -> true
        "light" -> false
        else -> systemDark
    }
    val accent = accentColor(appearance.accent, dark)
    val scheme = if (dark) {
        darkColorScheme(
            primary = accent,
            onPrimary = Color(0xFF0B0E13),
            primaryContainer = lerp(Color(0xFF15151E), accent, 0.22f),
            onPrimaryContainer = accent,
            secondary = Color(0xFF9AA6B8),
            onSecondary = Color(0xFF0B0E13),
            secondaryContainer = Color(0xFF232C38),
            onSecondaryContainer = Color(0xFFD7DEE8),
            tertiary = accent,
            onTertiary = Color(0xFF0B0E13),
            background = Color(0xFF0E0E14),
            onBackground = Color(0xFFECEEF4),
            surface = Color(0xFF17171F),
            onSurface = Color(0xFFECEEF4),
            surfaceVariant = Color(0xFF23232F),
            onSurfaceVariant = Color(0xFFB9C0CE),
            outline = Color(0xFF50505F),
            outlineVariant = Color(0xFF33333F),
            error = Color(0xFFFF8A9B),
            onError = Color(0xFF2A0710),
            errorContainer = Color(0xFF4A1420),
            onErrorContainer = Color(0xFFFFD9DF),
            inverseSurface = Color(0xFFECEEF4),
            inverseOnSurface = Color(0xFF17171F),
        )
    } else {
        lightColorScheme(
            primary = accent,
            onPrimary = Color.White,
            primaryContainer = lerp(Color.White, accent, 0.20f),
            onPrimaryContainer = lerp(accent, Color.Black, 0.35f),
            secondary = Color(0xFF4B5563),
            onSecondary = Color.White,
            secondaryContainer = Color(0xFFE4E8EF),
            onSecondaryContainer = Color(0xFF1F2733),
            tertiary = accent,
            onTertiary = Color.White,
            background = Color(0xFFF4F2FB),
            onBackground = Color(0xFF171A20),
            surface = Color(0xFFFDFCFF),
            onSurface = Color(0xFF171A20),
            surfaceVariant = Color(0xFFE7ECF4),
            onSurfaceVariant = Color(0xFF49515E),
            outline = Color(0xFF8B93A1),
            outlineVariant = Color(0xFFD3D9E3),
            error = Color(0xFFB3261E),
            onError = Color.White,
            errorContainer = Color(0xFFFFE1DE),
            onErrorContainer = Color(0xFF5F1510),
            inverseSurface = Color(0xFF171A20),
            inverseOnSurface = Color(0xFFF6F7FB),
        )
    }.let { base ->
        if (dark && appearance.amoled) base.copy(background = Color.Black, surface = Color(0xFF0B0B10), surfaceVariant = Color(0xFF16161E))
        else base
    }
    val view = LocalView.current
    SideEffect {
        val window = (view.context as? android.app.Activity)?.window ?: return@SideEffect
        window.decorView.setBackgroundColor(if (dark) 0xFF0E0E14.toInt() else 0xFFF4F2FB.toInt())
        WindowCompat.getInsetsController(window, view).isAppearanceLightStatusBars = !dark
        WindowCompat.getInsetsController(window, view).isAppearanceLightNavigationBars = !dark
    }
    MaterialTheme(colorScheme = scheme, typography = Type, content = content)
}

@Composable
fun canvasBrush(): Brush = Brush.verticalGradient(listOf(MaterialTheme.colorScheme.background, MaterialTheme.colorScheme.background))

/** Flat, animated-free background. Content is drawn over a solid themed surface. */
@Composable
fun AppBackground(modifier: Modifier = Modifier, content: @Composable BoxScope.() -> Unit) {
    // Without this, text inside a transparent Scaffold falls back to black, which is
    // unreadable on dark backgrounds. Every screen inherits this content color.
    androidx.compose.runtime.CompositionLocalProvider(
        androidx.compose.material3.LocalContentColor provides MaterialTheme.colorScheme.onBackground,
    ) {
        Box(modifier.fillMaxSize().background(MaterialTheme.colorScheme.background), content = content)
    }
}

/** Gradient used by the hero card; text on it always uses [MaterialTheme.colorScheme.onPrimary]. */
@Composable
fun heroBrush(): Brush {
    val primary = MaterialTheme.colorScheme.primary
    return Brush.linearGradient(listOf(primary, lerp(primary, Color.Black, 0.32f)))
}

fun money(amount: Double, currency: String) = "${"%,.2f".format(amount)} $currency"
