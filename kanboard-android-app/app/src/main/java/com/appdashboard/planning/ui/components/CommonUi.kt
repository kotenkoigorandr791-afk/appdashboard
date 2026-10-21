package com.appdashboard.planning.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.material3.Text
import com.flyfishxu.vetraui.core.theme.VetraTheme

@Composable
fun ProfileAvatar(
    name: String?,
    modifier: Modifier = Modifier,
    size: Dp = 40.dp,
) {
    val initial = name?.trim()?.firstOrNull()?.uppercaseChar()?.toString() ?: "?"
    val colors = VetraTheme.colors
    Box(
        modifier = modifier
            .size(size)
            .clip(CircleShape)
            .background(colors.brandSubtle.copy(alpha = 0.7f)),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            text = initial,
            color = colors.onBrandSubtle,
            fontWeight = FontWeight.SemiBold,
            fontSize = (size.value * 0.4f).sp,
        )
    }
}

fun priorityLabel(priority: Int): String = when {
    priority >= 3 -> "High"
    priority == 2 -> "Medium"
    priority == 1 -> "Low"
    else -> "Normal"
}
