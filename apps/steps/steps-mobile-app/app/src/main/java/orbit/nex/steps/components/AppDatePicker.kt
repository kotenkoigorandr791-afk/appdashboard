package orbit.nex.steps.components

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material3.Button
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SelectableDates
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.layout.layout
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import orbit.nex.steps.R
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneOffset

private fun LocalDate.toUtcMillis(): Long = atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli()

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AppDatePickerDialog(
    initial: LocalDate?,
    min: LocalDate? = null,
    max: LocalDate? = LocalDate.now(),
    onDismiss: () -> Unit,
    onConfirm: (LocalDate) -> Unit,
) {
    val minMillis = min?.toUtcMillis()
    val maxMillis = max?.toUtcMillis()
    val start = (initial ?: max ?: LocalDate.now()).let { d ->
        when {
            min != null && d.isBefore(min) -> min
            max != null && d.isAfter(max) -> max
            else -> d
        }
    }
    val state = rememberDatePickerState(
        initialSelectedDateMillis = start.toUtcMillis(),
        selectableDates = remember(minMillis, maxMillis) {
            object : SelectableDates {
                override fun isSelectableDate(utcTimeMillis: Long) =
                    (minMillis == null || utcTimeMillis >= minMillis) && (maxMillis == null || utcTimeMillis <= maxMillis)
            }
        },
    )
    Dialog(onDismissRequest = onDismiss, properties = androidx.compose.ui.window.DialogProperties(usePlatformDefaultWidth = false)) {
        androidx.compose.foundation.layout.BoxWithConstraints(Modifier.fillMaxWidth().padding(horizontal = 16.dp)) {
        val factor = (maxWidth / 360.dp).coerceAtMost(1f)
        Surface(shape = RoundedCornerShape(28.dp), color = MaterialTheme.colorScheme.surface, tonalElevation = 6.dp, modifier = Modifier.align(Alignment.Center)) {
            Column(Modifier.padding(bottom = 12.dp).layout { measurable, constraints ->
                val placeable = measurable.measure(androidx.compose.ui.unit.Constraints(minWidth = 360.dp.roundToPx(), maxWidth = 360.dp.roundToPx()))
                layout((placeable.width * factor).toInt(), (placeable.height * factor).toInt()) {
                    placeable.placeWithLayer(0, 0) { scaleX = factor; scaleY = factor; transformOrigin = androidx.compose.ui.graphics.TransformOrigin(0f, 0f) }
                }
            }) {
                DatePicker(
                    state = state,
                    showModeToggle = false,
                    title = null,
                    headline = null,
                    colors = DatePickerDefaults.colors(
                        containerColor = MaterialTheme.colorScheme.surface,
                        selectedDayContainerColor = MaterialTheme.colorScheme.primary,
                        todayDateBorderColor = MaterialTheme.colorScheme.primary,
                        todayContentColor = MaterialTheme.colorScheme.primary,
                    ),
                )
                Row(Modifier.fillMaxWidth().padding(horizontal = 16.dp), horizontalArrangement = Arrangement.spacedBy(8.dp, Alignment.End)) {
                    TextButton(onClick = onDismiss) { Text(stringResource(R.string.action_cancel)) }
                    Button(
                        enabled = state.selectedDateMillis != null,
                        onClick = {
                            state.selectedDateMillis?.let { onConfirm(Instant.ofEpochMilli(it).atZone(ZoneOffset.UTC).toLocalDate()) }
                        },
                        shape = RoundedCornerShape(16.dp),
                    ) { Text(stringResource(R.string.action_ok)) }
                }
            }
        }
        }
    }
}

@Composable
fun AppDateField(label: String, value: LocalDate?, onClick: () -> Unit, modifier: Modifier = Modifier) {
    Surface(
        shape = RoundedCornerShape(16.dp),
        color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant),
        modifier = modifier.clickable(onClick = onClick),
    ) {
        Row(Modifier.padding(horizontal = 12.dp, vertical = 10.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Icon(Icons.Filled.CalendarMonth, null, Modifier.size(18.dp), tint = MaterialTheme.colorScheme.primary)
            Text(
                value?.toString() ?: label,
                style = MaterialTheme.typography.bodyMedium,
                fontWeight = if (value != null) FontWeight.Medium else FontWeight.Normal,
                color = if (value != null) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
        }
    }
}
