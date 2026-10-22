package com.appdashboard.planning.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.CloudOff
import androidx.compose.material.icons.outlined.Inbox
import androidx.compose.material.icons.outlined.Refresh
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.appdashboard.planning.ui.theme.glassModifier
import com.flyfishxu.vetraui.core.VetraOutlinedButton
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

/** Dot color for a Kanboard priority value (0 normal, 1 low, 2 medium, 3 high). */
fun priorityColor(priority: Int): Color = when (priority) {
    3 -> Color(0xFFEF5350)
    2 -> Color(0xFFFFA726)
    1 -> Color(0xFF42A5F5)
    else -> Color(0xFF78909C)
}

/** Kanboard task color id → swatch color. */
fun taskColor(id: String?): Color? = when (id?.lowercase()) {
    "yellow" -> Color(0xFFFBC02D)
    "blue" -> Color(0xFF1E88E5)
    "green" -> Color(0xFF43A047)
    "purple" -> Color(0xFF8E24AA)
    "red" -> Color(0xFFE53935)
    "orange" -> Color(0xFFFB8C00)
    "grey", "gray" -> Color(0xFF757575)
    "brown" -> Color(0xFF6D4C41)
    "deep_orange" -> Color(0xFFF4511E)
    "dark_grey", "dark_gray" -> Color(0xFF424242)
    "pink" -> Color(0xFFD81B60)
    "teal" -> Color(0xFF00897B)
    "cyan" -> Color(0xFF00ACC1)
    "lime" -> Color(0xFFC0CA33)
    "light_green" -> Color(0xFF7CB342)
    "amber" -> Color(0xFFFFB300)
    else -> null
}

@Composable
fun SectionHeader(text: String, modifier: Modifier = Modifier) {
    Text(
        text = text,
        modifier = modifier.padding(start = 4.dp, bottom = 2.dp),
        color = VetraTheme.colors.textSecondary,
        fontWeight = FontWeight.SemiBold,
        fontSize = 13.sp,
        letterSpacing = 0.4.sp,
    )
}

/** Full-height placeholder with an icon, a message and an optional retry action. */
@Composable
fun StateMessage(
    icon: ImageVector,
    title: String,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
    actionLabel: String? = null,
    onAction: (() -> Unit)? = null,
) {
    val colors = VetraTheme.colors
    Column(
        modifier = modifier.padding(horizontal = 24.dp, vertical = 32.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Icon(
            icon,
            contentDescription = null,
            modifier = Modifier.size(44.dp),
            tint = colors.textTertiary,
        )
        Spacer(Modifier.height(12.dp))
        Text(
            title,
            color = colors.textSecondary,
            fontWeight = FontWeight.Medium,
            textAlign = TextAlign.Center,
        )
        if (subtitle != null) {
            Spacer(Modifier.height(4.dp))
            Text(
                subtitle,
                color = colors.textTertiary,
                fontSize = 13.sp,
                textAlign = TextAlign.Center,
            )
        }
        if (actionLabel != null && onAction != null) {
            Spacer(Modifier.height(16.dp))
            VetraOutlinedButton(onClick = onAction) {
                Icon(Icons.Outlined.Refresh, contentDescription = null, modifier = Modifier.size(16.dp))
                Spacer(Modifier.size(6.dp))
                Text(actionLabel)
            }
        }
    }
}

@Composable
fun EmptyTasks(message: String = "No open tasks", modifier: Modifier = Modifier) {
    StateMessage(icon = Icons.Outlined.Inbox, title = message, subtitle = "Pull down to refresh", modifier = modifier)
}

@Composable
fun OfflineBanner(message: String, modifier: Modifier = Modifier) {
    val colors = VetraTheme.colors
    Row(
        modifier = modifier
            .fillMaxWidth()
            .then(glassModifier(shape = androidx.compose.foundation.shape.RoundedCornerShape(14.dp)))
            .padding(horizontal = 12.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(Icons.Outlined.CloudOff, contentDescription = null, tint = colors.danger, modifier = Modifier.size(16.dp))
        Spacer(Modifier.size(8.dp))
        Text(message, color = colors.textSecondary, fontSize = 12.sp, modifier = Modifier.weight(1f))
    }
}
