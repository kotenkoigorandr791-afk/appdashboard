package orbit.nex.steps.components

import orbit.nex.steps.ui.theme.Spacing
import orbit.nex.steps.ui.theme.semantic
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccountCircle
import androidx.compose.material.icons.filled.PersonOff
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import coil.compose.AsyncImage
import orbit.nex.steps.BuildConfig
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.R
import orbit.nex.steps.screens.ServerProfile
import orbit.nex.steps.screens.profileFromJson
import kotlinx.coroutines.delay

private val CONTENT_EDGE_PADDING = 10.dp

/**
 * App-level top bar with the profile avatar and name on the left and, on
 * secondary screens, the live sync countdown on the right. Tapping the avatar
 * or the name opens a read-only account dialog.
 *
 * Uses a slightly darker surface in light theme and a slightly lighter one in
 * dark theme than the page background, and aligns its content edge insets with
 * the main content padding so everything lines up vertically.
 */
@Composable
fun AppTopBar(showTimer: Boolean, modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }
    var now by remember { mutableLongStateOf(System.currentTimeMillis()) }
    var showAccount by remember { mutableStateOf(false) }
    // Recomputed on every recomposition (the sync timer ticks each second) so a
    // profile refreshed in Settings shows up here without a restart.
    val profile = profileFromJson(prefs.getProfileCache(Long.MAX_VALUE)) ?: ServerProfile()
    val profileName = profile.name
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
        color = MaterialTheme.colorScheme.surface,
        tonalElevation = orbit.nex.steps.ui.theme.Elevation.raised,
        modifier = modifier.fillMaxWidth()
    ) {
        Row(
            modifier = Modifier
                .statusBarsPadding()
                .padding(horizontal = CONTENT_EDGE_PADDING, vertical = Spacing.md)
                .fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically
        ) {
            val isConnected = prefs.isUserConnected()
            val avatarFile = localAvatarFile(context)
            val avatarModel = if (isConnected) {
                when {
                    avatarFile.exists() -> avatarFile.absolutePath
                    else -> cachedProfilePhotoUrl(context)
                }
            } else null
            if (avatarModel != null) {
                AsyncImage(
                    model = avatarModel,
                    contentDescription = stringResource(R.string.account_title),
                    contentScale = ContentScale.Crop,
                    modifier = Modifier
                        .size(orbit.nex.steps.ui.theme.ComponentHeight.minTouchTarget)
                        .clip(CircleShape)
                        .clickable(onClick = { showAccount = true })
                        .padding(7.dp)
                )
            } else {
                Icon(
                    if (isConnected) Icons.Filled.AccountCircle else Icons.Filled.PersonOff,
                    contentDescription = stringResource(R.string.account_title),
                    modifier = Modifier
                        .size(orbit.nex.steps.ui.theme.ComponentHeight.minTouchTarget)
                        .clip(CircleShape)
                        .clickable(onClick = { showAccount = true })
                        .padding(7.dp),
                    tint = if (isConnected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
            Spacer(Modifier.size(10.dp))
            Text(
                profileName.ifBlank { stringResource(R.string.account_title) },
                style = MaterialTheme.typography.titleSmall.copy(fontWeight = FontWeight.SemiBold),
                color = if (isConnected) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
                modifier = Modifier.clickable { showAccount = true }
            )
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

    if (showAccount) {
        AccountDialog(
            profile = profile,
            connected = prefs.isUserConnected(),
            serverUrl = prefs.getWebhookConfigs().firstOrNull { it.isEnabled }?.url.orEmpty(),
            onDismiss = { showAccount = false }
        )
    }
}

/**
 * Read-only account dialog: name, email and server connection state. Nothing
 * here can be edited, the values are informational only.
 */
@Composable
private fun AccountDialog(
    profile: ServerProfile,
    connected: Boolean,
    serverUrl: String,
    onDismiss: () -> Unit
) {
    val accent = if (connected) MaterialTheme.semantic.success else MaterialTheme.colorScheme.error
    Dialog(onDismissRequest = onDismiss) {
        Surface(
            shape = RoundedCornerShape(24.dp),
            color = MaterialTheme.colorScheme.surface,
            border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant),
            shadowElevation = 14.dp,
            modifier = Modifier.fillMaxWidth(),
        ) {
            Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(Spacing.md)) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(Spacing.md),
                ) {
                    Box(
                        Modifier
                            .size(46.dp)
                            .clip(CircleShape)
                            .background(accent.copy(alpha = 0.14f))
                            .border(1.dp, accent.copy(alpha = 0.45f), CircleShape),
                        contentAlignment = Alignment.Center,
                    ) {
                        Icon(
                            if (connected) Icons.Filled.AccountCircle else Icons.Filled.PersonOff,
                            contentDescription = null,
                            tint = accent,
                        )
                    }
                    Column(Modifier.weight(1f)) {
                        Text(
                            stringResource(R.string.account_title),
                            style = MaterialTheme.typography.titleLarge.copy(fontWeight = FontWeight.Bold),
                        )
                        Text(
                            profile.email.ifBlank { "â€”" },
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }

                HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant)

                AccountField(stringResource(R.string.personal_name), profile.name.ifBlank { "â€”" })
                AccountField(stringResource(R.string.personal_email), profile.email.ifBlank { "â€”" })
                AccountField(
                    stringResource(R.string.account_status),
                    stringResource(
                        if (connected) R.string.account_status_connected
                        else R.string.account_status_disconnected
                    ),
                    valueColor = accent,
                    badgeColor = accent,
                )
                if (serverUrl.isNotBlank()) {
                    AccountField(stringResource(R.string.account_server), serverUrl)
                }

                TextButton(
                    onClick = onDismiss,
                    shape = RoundedCornerShape(14.dp),
                    modifier = Modifier
                        .fillMaxWidth()
                        .border(1.dp, MaterialTheme.colorScheme.outlineVariant, RoundedCornerShape(14.dp)),
                ) {
                    Text(stringResource(R.string.action_close), fontWeight = FontWeight.SemiBold)
                }
            }
        }
    }
}

/** One framed field of the account dialog. */
@Composable
private fun AccountField(
    label: String,
    value: String,
    valueColor: Color? = null,
    badgeColor: Color? = null,
) {
    Surface(
        shape = RoundedCornerShape(14.dp),
        color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.55f),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant),
        modifier = Modifier.fillMaxWidth(),
    ) {
        Row(
            Modifier.padding(horizontal = 14.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            Column(Modifier.weight(1f)) {
                Text(
                    label,
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Text(
                    value,
                    style = MaterialTheme.typography.bodyMedium.copy(fontWeight = FontWeight.Medium),
                    color = valueColor ?: MaterialTheme.colorScheme.onSurface,
                )
            }
            if (badgeColor != null) {
                Box(
                    Modifier
                        .size(10.dp)
                        .clip(CircleShape)
                        .background(badgeColor)
                )
            }
        }
    }
}
