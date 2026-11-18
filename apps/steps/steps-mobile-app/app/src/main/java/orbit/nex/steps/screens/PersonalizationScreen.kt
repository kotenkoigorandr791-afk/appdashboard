package orbit.nex.steps.screens

import android.app.Activity
import android.content.Intent
import android.content.pm.ActivityInfo
import androidx.appcompat.app.AppCompatDelegate
import androidx.compose.foundation.clickable
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Brightness4
import androidx.compose.material.icons.filled.Brightness7
import androidx.compose.material.icons.filled.Contrast
import androidx.compose.material.icons.filled.Palette
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.RadioButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SegmentedButton
import androidx.compose.material3.SegmentedButtonDefaults
import androidx.compose.material3.SingleChoiceSegmentedButtonRow
import androidx.compose.material3.Surface
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.core.os.LocaleListCompat
import orbit.nex.steps.MainActivity
import orbit.nex.steps.PreferencesManager
import orbit.nex.steps.R
import orbit.nex.steps.components.APP_LANGUAGES
import orbit.nex.steps.components.AppBackground
import orbit.nex.steps.location.LocationTrackingService
import orbit.nex.steps.ui.theme.AppThemeRevision

/**
 * Personalization on its own screen: theme, accent color, language and screen
 * orientation. It used to sit at the bottom of the advanced settings list.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PersonalizationScreen(onBack: () -> Unit) {
    val context = LocalContext.current
    val prefs = remember { PreferencesManager(context) }

    var themeMode by remember { mutableStateOf(prefs.getThemeMode()) }
    var amoledEnabled by remember { mutableStateOf(prefs.isAmoledEnabled()) }
    var accentColor by remember { mutableStateOf(prefs.getAccentColor()) }
    var language by remember { mutableStateOf(prefs.getAppLanguage()) }
    var portraitOnly by remember { mutableStateOf(prefs.isPortraitOnly()) }
    var showRestartDialog by remember { mutableStateOf(false) }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(stringResource(R.string.settings_personalization)) },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(
                            imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                            contentDescription = stringResource(R.string.settings_back_cd),
                        )
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.surfaceVariant,
                ),
            )
        },
    ) { padding ->
        AppBackground {
            Box(Modifier.fillMaxSize().padding(padding)) {
                LazyColumn(
                    modifier = Modifier.fillMaxSize().padding(horizontal = 10.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                ) {
                    item { Spacer(Modifier.size(6.dp)) }
                    item {
                        Card(
                            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                            modifier = Modifier.fillMaxWidth(),
                        ) {
                            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Icon(Icons.Filled.Palette, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                                    Spacer(Modifier.size(8.dp))
                                    Text(stringResource(R.string.about_theme), style = MaterialTheme.typography.titleMedium)
                                }
                                SingleChoiceSegmentedButtonRow(modifier = Modifier.fillMaxWidth()) {
                                    listOf(
                                        "auto" to R.string.theme_auto,
                                        "light" to R.string.theme_light,
                                        "dark" to R.string.theme_dark,
                                    ).forEachIndexed { index, (value, label) ->
                                        SegmentedButton(
                                            selected = themeMode == value,
                                            onClick = {
                                                themeMode = value
                                                prefs.setThemeMode(value)
                                                val nightMode = when (value) {
                                                    "light" -> AppCompatDelegate.MODE_NIGHT_NO
                                                    "dark" -> AppCompatDelegate.MODE_NIGHT_YES
                                                    else -> AppCompatDelegate.MODE_NIGHT_FOLLOW_SYSTEM
                                                }
                                                AppCompatDelegate.setDefaultNightMode(nightMode)
                                                // Every open activity picks the new palette up on its own,
                                                // so the whole app changes, not just this screen.
                                                AppThemeRevision.bump()
                                                // The tracking card is drawn outside the app, so it
                                                // needs an explicit refresh to follow the theme.
                                                LocationTrackingService.refreshNotification(context)
                                            },
                                            shape = SegmentedButtonDefaults.itemShape(index, 3),
                                        ) {
                                            Row(
                                                verticalAlignment = Alignment.CenterVertically,
                                                horizontalArrangement = Arrangement.spacedBy(6.dp),
                                            ) {
                                                Icon(
                                                    when (value) {
                                                        "auto" -> Icons.Filled.Contrast
                                                        "light" -> Icons.Filled.Brightness7
                                                        else -> Icons.Filled.Brightness4
                                                    },
                                                    contentDescription = null,
                                                    modifier = Modifier.size(18.dp),
                                                )
                                                Text(stringResource(label))
                                            }
                                        }
                                    }
                                }
                                if (themeMode == "dark" || (themeMode == "auto" && isSystemInDarkTheme())) {
                                    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                        Text(stringResource(R.string.theme_amoled), modifier = Modifier.weight(1f))
                                        Switch(
                                            checked = amoledEnabled,
                                            onCheckedChange = {
                                                amoledEnabled = it
                                                prefs.setAmoledEnabled(it)
                                                AppThemeRevision.bump()
                                            },
                                        )
                                    }
                                }
                            }
                        }
                    }

                    item {
                        Card(
                            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                            modifier = Modifier.fillMaxWidth(),
                        ) {
                            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                Text(stringResource(R.string.about_accent_color), style = MaterialTheme.typography.titleMedium)
                                listOf("blue", "teal", "orange", "pink", "purple", "gray").forEach { value ->
                                    Row(
                                        Modifier
                                            .fillMaxWidth()
                                            .clickable {
                                                accentColor = value
                                                prefs.setAccentColor(value)
                                                AppThemeRevision.bump()
                                            }
                                            .padding(vertical = 8.dp),
                                        verticalAlignment = Alignment.CenterVertically,
                                    ) {
                                        RadioButton(selected = accentColor == value, onClick = null)
                                        Spacer(Modifier.size(12.dp))
                                        Surface(
                                            color = accentColorValue(value),
                                            shape = CircleShape,
                                            modifier = Modifier.size(18.dp),
                                            content = {},
                                        )
                                        Spacer(Modifier.size(10.dp))
                                        Text(stringResource(accentNameRes(value)))
                                    }
                                }
                            }
                        }
                    }

                    item {
                        Card(
                            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                            modifier = Modifier.fillMaxWidth(),
                        ) {
                            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                Text(stringResource(R.string.about_language), style = MaterialTheme.typography.titleMedium)
                                APP_LANGUAGES.forEach { (tag, name) ->
                                    Row(
                                        Modifier
                                            .fillMaxWidth()
                                            .clickable {
                                                language = tag
                                                prefs.setAppLanguage(tag)
                                                if (tag.isEmpty()) {
                                                    AppCompatDelegate.setApplicationLocales(LocaleListCompat.getEmptyLocaleList())
                                                } else {
                                                    AppCompatDelegate.setApplicationLocales(LocaleListCompat.forLanguageTags(tag))
                                                }
                                                showRestartDialog = true
                                            }
                                            .padding(vertical = 8.dp),
                                        verticalAlignment = Alignment.CenterVertically,
                                    ) {
                                        RadioButton(selected = language == tag, onClick = null)
                                        Spacer(Modifier.size(12.dp))
                                        Text(if (tag.isEmpty()) stringResource(R.string.system_default) else name)
                                    }
                                }
                            }
                        }
                    }

                    item {
                        Card(
                            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                            modifier = Modifier.fillMaxWidth(),
                        ) {
                            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                Text(stringResource(R.string.about_screen_orientation), style = MaterialTheme.typography.titleMedium)
                                Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                    Text(stringResource(R.string.about_portrait_only), modifier = Modifier.weight(1f))
                                    Switch(
                                        checked = portraitOnly,
                                        onCheckedChange = {
                                            portraitOnly = it
                                            prefs.setPortraitOnly(it)
                                            (context as? Activity)?.requestedOrientation =
                                                if (it) ActivityInfo.SCREEN_ORIENTATION_PORTRAIT else ActivityInfo.SCREEN_ORIENTATION_UNSPECIFIED
                                        },
                                    )
                                }
                            }
                        }
                    }
                    item { Spacer(Modifier.size(12.dp)) }
                }
            }
        }
    }

    if (showRestartDialog) {
        AlertDialog(
            onDismissRequest = { showRestartDialog = false },
            title = { Text(stringResource(R.string.about_language_changed_title)) },
            text = { Text(stringResource(R.string.about_language_changed_text)) },
            confirmButton = {
                TextButton(onClick = {
                    showRestartDialog = false
                    val launch = Intent(context, MainActivity::class.java)
                        .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK)
                    context.startActivity(launch)
                    (context as? Activity)?.finish()
                }) { Text(stringResource(R.string.about_restart)) }
            },
            dismissButton = {
                TextButton(onClick = { showRestartDialog = false }) {
                    Text(stringResource(R.string.about_later))
                }
            },
        )
    }
}
