package xyz.serverkherson.spends.ui

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
import androidx.compose.ui.platform.LocalView
import androidx.core.view.WindowCompat
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp
import xyz.serverkherson.spends.data.Appearance
import kotlin.math.cos
import kotlin.math.sin

object SpendsColors {
    val Income = Color(0xFF1FAF75)
    val Expense = Color(0xFFE24B63)
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
    else -> if (dark) Color(0xFF8AB4F8) else Color(0xFF4285F4)
}

fun accentLabel(name: String) = when (name) {
    "teal" -> "Teal"
    "orange" -> "Orange"
    "pink" -> "Pink"
    "purple" -> "Purple"
    "gray" -> "Gray"
    else -> "Blue"
}

@Composable
fun SpendsTheme(appearance: Appearance, content: @Composable () -> Unit) {
    val dark = when (appearance.theme) {
        "dark" -> true
        "light" -> false
        else -> isSystemInDarkTheme()
    }
    val accent = accentColor(appearance.accent, dark)
    val scheme = if (dark) {
        darkColorScheme(primary = accent, secondary = Color(0xFFB8C5D0), tertiary = accent, background = Color(0xFF0E0E14), surface = Color(0xFF15151E), surfaceVariant = Color(0xFF242433))
    } else {
        lightColorScheme(primary = accent, secondary = Color(0xFF5F6368), tertiary = accent, background = Color(0xFFF4F2FB), surface = Color(0xFFFBF9FF), surfaceVariant = Color(0xFFE8EEF6))
    }.let { base ->
        if (dark && appearance.amoled) base.copy(background = Color.Black, surface = Color.Black, surfaceVariant = Color.Black, primaryContainer = Color.Black)
        else base
    }
    val view = LocalView.current
    SideEffect {
        val window = (view.context as android.app.Activity).window
        window.decorView.setBackgroundColor(if (dark) 0xFF0E0E14.toInt() else 0xFFF4F2FB.toInt())
        WindowCompat.getInsetsController(window, view).isAppearanceLightStatusBars = !dark
        WindowCompat.getInsetsController(window, view).isAppearanceLightNavigationBars = !dark
    }
    MaterialTheme(colorScheme = scheme, typography = Type, content = content)
}

@Composable
fun canvasBrush(): Brush {
    val scheme = MaterialTheme.colorScheme
    return Brush.verticalGradient(listOf(scheme.background, scheme.background))
}

@Composable
fun AppBackground(modifier: Modifier = Modifier, content: @Composable BoxScope.() -> Unit) {
    val bg = MaterialTheme.colorScheme.background
    val ink = MaterialTheme.colorScheme.onBackground
    Box(
        modifier.fillMaxSize().drawBehind {
            drawRect(bg)
            val c = Offset(size.width * 0.82f, size.height * 0.18f)
            val s = size.minDimension * 0.16f
            drawLeafMark(c, s, ink.copy(alpha = 0.06f))
            drawLeafMark(Offset(size.width * 0.16f, size.height * 0.78f), s * 0.85f, ink.copy(alpha = 0.045f))
        },
        content = content,
    )
}

private fun DrawScope.drawLeafMark(center: Offset, scale: Float, color: Color) {
    val leaf = Path().apply {
        moveTo(center.x, center.y - scale * 0.55f)
        cubicTo(center.x + scale * 0.42f, center.y - scale * 0.2f, center.x + scale * 0.38f, center.y + scale * 0.28f, center.x, center.y + scale * 0.52f)
        cubicTo(center.x - scale * 0.38f, center.y + scale * 0.28f, center.x - scale * 0.42f, center.y - scale * 0.2f, center.x, center.y - scale * 0.55f)
        close()
    }
    drawPath(leaf, color, style = Stroke(width = 3.2f, cap = StrokeCap.Round))
    drawLine(color, Offset(center.x, center.y - scale * 0.4f), Offset(center.x, center.y + scale * 0.42f), 2.4f, StrokeCap.Round)
    val radius = scale * 0.92f
    for (i in 0 until 4) {
        val a = Math.toRadians((-40.0 + i * 90.0))
        val tip = Offset(center.x + (cos(a) * radius).toFloat(), center.y + (sin(a) * radius).toFloat())
        rotate((Math.toDegrees(a) + 90).toFloat(), tip) {
            val shaft = scale * 0.18f
            drawLine(color, Offset(tip.x, tip.y + shaft * 0.45f), Offset(tip.x, tip.y - shaft * 0.55f), 2.2f, StrokeCap.Round)
            drawLine(color, Offset(tip.x, tip.y - shaft * 0.55f), Offset(tip.x - shaft * 0.22f, tip.y - shaft * 0.18f), 2.2f, StrokeCap.Round)
            drawLine(color, Offset(tip.x, tip.y - shaft * 0.55f), Offset(tip.x + shaft * 0.22f, tip.y - shaft * 0.18f), 2.2f, StrokeCap.Round)
        }
    }
}

fun money(amount: Double, currency: String) = "${"%,.2f".format(amount)} $currency"
