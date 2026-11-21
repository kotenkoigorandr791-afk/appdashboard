package orbit.nex.steps.components
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import orbit.nex.steps.R
import orbit.nex.steps.ui.theme.semantic
@Composable
fun SavedToast(text: String, modifier: Modifier = Modifier, isError: Boolean = false, onDismiss: () -> Unit = {}) {
    val shape = RoundedCornerShape(14.dp)
    Surface(modifier.fillMaxWidth().padding(horizontal = 12.dp).border(1.dp, MaterialTheme.colorScheme.outlineVariant, shape), shape = shape, color = MaterialTheme.colorScheme.surfaceVariant, shadowElevation = 4.dp) {
        Row(Modifier.padding(start = 16.dp, end = 4.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            Box(Modifier.size(8.dp).clip(CircleShape).background(if (isError) MaterialTheme.colorScheme.error else MaterialTheme.semantic.success))
            Text(text, modifier = Modifier.weight(1f), style = MaterialTheme.typography.bodySmall)
            TextButton(onClick = onDismiss) { Text(stringResource(R.string.action_ok), style = MaterialTheme.typography.labelMedium) }
        }
    }
}
