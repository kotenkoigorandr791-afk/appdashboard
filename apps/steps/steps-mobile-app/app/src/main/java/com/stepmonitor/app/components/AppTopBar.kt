package com.stepmonitor.app.components

import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccountCircle
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.stepmonitor.app.BuildConfig
import com.stepmonitor.app.PreferencesManager
import com.stepmonitor.app.R
import kotlinx.coroutines.delay

private val CONTENT_EDGE_PADDING = 20.dp

/**
 * App-level top bar with the profile avatar on the left and, on secondary
 * screens, the live sync countdown on the right.
 *
 * Uses a slightly darker surface in light theme and a slightly lighter one in
 * dark theme than the page background, and aligns its content edge insets with
 * the main content padding so everything lines up vertically.
 */
@Composable
fun AppTopBar(showTimer: Boolean, onProfileClick: () -> Unit, modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    var now by remember { mutableLongStateOf(System.currentTimeMillis()) }
    LaunchedEffect(Unit) {
        while (true) {
            delay(1_000)
            now = System.currentTimeMillis()
        }
    }
    val info = computeNextSyncInfo(prefs, now)
    val remainingMs = if (showTimer && info != null) (info.nextSyncMs - now).coerceAtLeast(0L) else null
    val fraction = if (info != null && info.periodMs > 0) (remainingMs?.toFloat()?.div(info.periodMs))?.coerceIn(0f, 1f) ?: 0f else 0f
    val animatedFraction by animateFloatAsState(
        targetValue = fraction,
        animationSpec = tween(durationMillis = 800, easing = LinearEasing),
        label = "syncProgress"
    )

    Surface(
        color = MaterialTheme.colorScheme.surfaceVariant,
        modifier = modifier.fillMaxWidth()
    ) {
        Row(
            modifier = Modifier
                .statusBarsPadding()
                .padding(horizontal = CONTENT_EDGE_PADDING, vertical = 12.dp)
                .fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically
        ) {
            val avatarFile = localAvatarFile(context)
            val avatarModel = when {
                avatarFile.exists() -> avatarFile.absolutePath
                else -> cachedProfilePhotoUrl(context)
            }
            if (avatarModel != null) {
                AsyncImage(
                    model = avatarModel,
                    contentDescription = stringResource(R.string.nav_about),
                    contentScale = ContentScale.Crop,
                    modifier = Modifier
                        .size(34.dp)
                        .clip(CircleShape)
                        .clickable(onClick = onProfileClick)
                )
            } else {
                Icon(
                    Icons.Filled.AccountCircle,
                    contentDescription = stringResource(R.string.nav_about),
                    modifier = Modifier
                        .size(34.dp)
                        .clip(CircleShape)
                        .clickable(onClick = onProfileClick),
                    tint = MaterialTheme.colorScheme.primary
                )
            }
            Spacer(Modifier.weight(1f))
            if (showTimer && info != null) {
                Box(contentAlignment = Alignment.Center, modifier = Modifier.size(24.dp)) {
                    CircularProgressIndicator(
                        progress = { animatedFraction },
                        modifier = Modifier.size(24.dp),
                        strokeWidth = 2.5.dp,
                        trackColor = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.3f),
                        color = MaterialTheme.colorScheme.primary
                    )
                    Icon(
                        Icons.Filled.Schedule,
                        contentDescription = null,
                        modifier = Modifier.size(13.dp),
                        tint = MaterialTheme.colorScheme.primary
                    )
                }
                Spacer(Modifier.size(8.dp))
                Text(
                    formatSyncCountdown(remainingMs ?: 0L),
                    style = MaterialTheme.typography.titleMedium.copy(
                        fontFamily = FontFamily.Monospace,
                        fontWeight = FontWeight.Bold,
                        fontSize = 18.sp
                    ),
                    color = MaterialTheme.colorScheme.primary
                )
            } else {
                Text(
                    stringResource(R.string.app_version_short, BuildConfig.VERSION_NAME),
                    style = MaterialTheme.typography.titleMedium.copy(
                        fontFamily = FontFamily.Monospace,
                        fontWeight = FontWeight.Bold,
                        fontSize = 18.sp
                    ),
                    color = MaterialTheme.colorScheme.primary
                )
            }
        }
    }
}
