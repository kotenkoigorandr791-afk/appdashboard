package com.stepmonitor.app.components

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp

/**
 * Semi-transparent dot grid in the accent color, used as a screen background.
 */
@Composable
fun Modifier.accentDotGrid(spacing: Dp = 26.dp, dotRadius: Dp = 2.dp, alpha: Float = 0.18f): Modifier {
    val dotColor = MaterialTheme.colorScheme.primary.copy(alpha = alpha)
    return drawBehind {
        val spacingPx = spacing.toPx()
        val radiusPx = dotRadius.toPx()
        var x = spacingPx / 2f
        while (x < size.width) {
            var y = spacingPx / 2f
            while (y < size.height) {
                drawCircle(dotColor, radiusPx, center = Offset(x, y))
                y += spacingPx
            }
            x += spacingPx
        }
    }
}

@Composable
fun AppBackground(modifier: Modifier = Modifier, content: @Composable BoxScope.() -> Unit) {
    Box(modifier.fillMaxSize().then(Modifier.accentDotGrid())) {
        content()
    }
}