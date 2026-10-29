package xyz.serverkherson.spends.ui

import android.app.Activity
import android.content.pm.ActivityInfo
import androidx.appcompat.app.AppCompatDelegate
import androidx.compose.foundation.clickable
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ExpandLess
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.RadioButton
import androidx.compose.material3.SegmentedButton
import androidx.compose.material3.SegmentedButtonDefaults
import androidx.compose.material3.SingleChoiceSegmentedButtonRow
import androidx.compose.material3.Surface
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.core.os.LocaleListCompat
import kotlinx.coroutines.launch
import xyz.serverkherson.spends.data.Appearance
import xyz.serverkherson.spends.data.SessionStore

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AppearanceScreen(appearance: Appearance, store: SessionStore) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var accentOpen by remember { mutableStateOf(false) }
    fun save(next: Appearance) {
        scope.launch { store.saveAppearance(next) }
        (context as? Activity)?.requestedOrientation =
            if (next.portraitOnly) ActivityInfo.SCREEN_ORIENTATION_PORTRAIT else ActivityInfo.SCREEN_ORIENTATION_UNSPECIFIED
        if (next.language.isEmpty()) AppCompatDelegate.setApplicationLocales(LocaleListCompat.getEmptyLocaleList())
        else AppCompatDelegate.setApplicationLocales(LocaleListCompat.forLanguageTags(next.language))
    }
    Column(Modifier.fillMaxWidth().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text("Appearance", style = MaterialTheme.typography.headlineMedium)
        Card(colors = CardDefaults.cardColors(MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text("Theme", style = MaterialTheme.typography.titleSmall)
                SingleChoiceSegmentedButtonRow(Modifier.fillMaxWidth()) {
                    listOf("auto" to "Auto", "light" to "Light", "dark" to "Dark").forEachIndexed { index, (value, label) ->
                        SegmentedButton(selected = appearance.theme == value, onClick = { save(appearance.copy(theme = value)) }, shape = SegmentedButtonDefaults.itemShape(index, 3)) {
                            Text(label)
                        }
                    }
                }
                if (appearance.theme == "dark" || (appearance.theme == "auto" && isSystemInDarkTheme())) {
                    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                        Text("AMOLED", modifier = Modifier.weight(1f))
                        Switch(appearance.amoled, { save(appearance.copy(amoled = it)) })
                    }
                }
                Text("Accent color", style = MaterialTheme.typography.titleSmall)
                Box {
                    OutlinedButton({ accentOpen = true }, Modifier.fillMaxWidth(), shape = RoundedCornerShape(8.dp)) {
                        Surface(color = accentColor(appearance.accent, isSystemInDarkTheme() || appearance.theme == "dark"), shape = CircleShape, modifier = Modifier.size(18.dp), content = {})
                        Spacer(Modifier.size(10.dp))
                        Text(accentLabel(appearance.accent))
                        Spacer(Modifier.weight(1f))
                        Icon(if (accentOpen) Icons.Filled.ExpandLess else Icons.Filled.ExpandMore, null)
                    }
                    DropdownMenu(accentOpen, { accentOpen = false }) {
                        listOf("blue", "teal", "orange", "pink", "purple", "gray").forEach { value ->
                            DropdownMenuItem(
                                text = {
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Surface(color = accentColor(value, appearance.theme == "dark" || (appearance.theme == "auto" && isSystemInDarkTheme())), shape = CircleShape, modifier = Modifier.size(18.dp), content = {})
                                        Spacer(Modifier.size(10.dp))
                                        Text(accentLabel(value))
                                    }
                                },
                                onClick = { accentOpen = false; save(appearance.copy(accent = value)) },
                            )
                        }
                    }
                }
                Text("Language", style = MaterialTheme.typography.titleSmall)
                listOf("" to "System default", "en" to "English", "uk" to "Українська", "ru" to "Русский", "pl" to "Polski", "be" to "Беларуская", "cs" to "Čeština", "ar" to "العربية").forEach { (tag, name) ->
                    Row(Modifier.fillMaxWidth().clickable { save(appearance.copy(language = tag)) }.padding(vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
                        RadioButton(appearance.language == tag, onClick = null)
                        Spacer(Modifier.size(10.dp))
                        Text(name)
                    }
                }
                Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                    Text("Portrait only", modifier = Modifier.weight(1f))
                    Switch(appearance.portraitOnly, { save(appearance.copy(portraitOnly = it)) })
                }
            }
        }
    }
}
