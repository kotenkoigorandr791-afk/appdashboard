package com.appdashboard.planning.ui.theme

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import com.appdashboard.planning.data.prefs.ThemeMode
import com.flyfishxu.vetraui.core.theme.VetraTheme

class ThemeController(initial: ThemeMode) {
    var mode by mutableStateOf(initial)
}

@Composable
fun rememberThemeController(initial: ThemeMode): ThemeController = remember { ThemeController(initial) }

@Composable
fun PlanningTheme(
    themeMode: ThemeMode,
    content: @Composable () -> Unit,
) {
    val dark = when (themeMode) {
        ThemeMode.DARK -> true
        ThemeMode.LIGHT -> false
        ThemeMode.AUTO -> isSystemInDarkTheme()
    }
    VetraTheme(darkMode = dark, content = content)
}

@Composable
fun glassModifier(
    enabled: Boolean = true,
    shape: RoundedCornerShape = RoundedCornerShape(20.dp),
): Modifier {
    val colors = VetraTheme.colors
    val fill = if (enabled) {
        colors.canvasElevated.copy(alpha = 0.55f)
    } else {
        colors.canvasElevated
    }
    val border = colors.borderSubtle.copy(alpha = if (enabled) 0.45f else 0.8f)
    return Modifier
        .clip(shape)
        .background(fill, shape)
        .border(1.dp, border, shape)
}

@Composable
fun canvasBrush(): Brush {
    val c = VetraTheme.colors
    return Brush.verticalGradient(
        listOf(
            c.canvas,
            c.canvasSubtle.copy(alpha = 0.92f),
            c.brandSubtle.copy(alpha = 0.35f),
        ),
    )
}

val ChartPalette = listOf(
    Color(0xFF6366F1),
    Color(0xFF22D3EE),
    Color(0xFF34D399),
    Color(0xFFFBBF24),
    Color(0xFFF472B6),
    Color(0xFFA78BFA),
    Color(0xFFFB7185),
    Color(0xFF60A5FA),
)
