package xyz.serverkherson.spends.ui

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Typography
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp

object SpendsColors {
    val Income = Color(0xFF1FAF75)
    val Expense = Color(0xFFE24B63)
    val Ink = Color(0xFF16141F)
}

private val Light = lightColorScheme(
    primary = Color(0xFF4F46E5),
    onPrimary = Color.White,
    primaryContainer = Color(0xFFE8E6FF),
    onPrimaryContainer = Color(0xFF1B1464),
    secondary = Color(0xFF7C5CFF),
    tertiary = Color(0xFF0E9AA7),
    background = Color(0xFFF4F2FB),
    surface = Color(0xFFFBF9FF),
    surfaceVariant = Color(0xFFEEEAF8),
    outline = Color(0xFFD5D0E6),
    error = SpendsColors.Expense,
)

private val Dark = darkColorScheme(
    primary = Color(0xFFC4C0FF),
    onPrimary = Color(0xFF1C1656),
    primaryContainer = Color(0xFF3B3488),
    onPrimaryContainer = Color(0xFFEDEBFF),
    secondary = Color(0xFFD4C4FF),
    tertiary = Color(0xFF79D7E2),
    background = Color(0xFF0E0E14),
    surface = Color(0xFF15151E),
    surfaceVariant = Color(0xFF242433),
    outline = Color(0xFF3C3C4F),
    error = Color(0xFFFF8A9A),
)

private val Type = Typography(
    headlineLarge = TextStyle(fontWeight = FontWeight.SemiBold, fontSize = 34.sp, letterSpacing = (-0.6).sp),
    headlineMedium = TextStyle(fontWeight = FontWeight.SemiBold, fontSize = 26.sp, letterSpacing = (-0.3).sp),
    titleLarge = TextStyle(fontWeight = FontWeight.SemiBold, fontSize = 20.sp),
    titleMedium = TextStyle(fontWeight = FontWeight.Medium, fontSize = 16.sp),
    bodyLarge = TextStyle(fontSize = 16.sp, lineHeight = 22.sp),
    labelLarge = TextStyle(fontWeight = FontWeight.Medium, fontSize = 12.sp, letterSpacing = 0.6.sp),
)

@Composable
fun SpendsTheme(content: @Composable () -> Unit) {
    val dark = isSystemInDarkTheme()
    MaterialTheme(colorScheme = if (dark) Dark else Light, typography = Type, content = content)
}

@Composable
fun canvasBrush(): Brush {
    val scheme = MaterialTheme.colorScheme
    return Brush.verticalGradient(listOf(scheme.background, scheme.primaryContainer.copy(alpha = 0.38f), scheme.background))
}

fun money(amount: Double, currency: String) = "${"%,.2f".format(amount)} $currency"
