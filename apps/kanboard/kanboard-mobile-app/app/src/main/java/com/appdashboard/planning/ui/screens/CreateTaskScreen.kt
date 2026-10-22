package com.appdashboard.planning.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.Check
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.appdashboard.planning.data.remote.KanboardApi
import com.appdashboard.planning.data.repo.PlanningRepository
import com.appdashboard.planning.ui.components.priorityLabel
import com.appdashboard.planning.ui.theme.canvasBrush
import com.appdashboard.planning.ui.theme.glassModifier
import com.flyfishxu.vetraui.core.VetraButton
import com.flyfishxu.vetraui.core.VetraChipFilter
import com.flyfishxu.vetraui.core.VetraIconButton
import com.flyfishxu.vetraui.core.VetraTextField
import com.flyfishxu.vetraui.core.VetraTopAppBar
import com.flyfishxu.vetraui.core.theme.VetraTheme
import kotlinx.coroutines.launch

private val CreateColorOptions = listOf(
    "yellow", "blue", "green", "purple", "red", "orange", "grey", "brown",
    "deep_orange", "dark_grey", "pink", "teal", "cyan", "lime", "light_green", "amber",
)

@Composable
fun CreateTaskScreen(
    projectId: Int,
    repository: PlanningRepository,
    onBack: () -> Unit,
    onCreated: (Int) -> Unit,
) {
    var title by remember { mutableStateOf("") }
    var description by remember { mutableStateOf("") }
    var comment by remember { mutableStateOf("") }
    var columnId by remember { mutableIntStateOf(0) }
    var priority by remember { mutableIntStateOf(0) }
    var due by remember { mutableStateOf("") }
    var color by remember { mutableStateOf("yellow") }
    var ownerId by remember { mutableIntStateOf(repository.currentUserId()) }
    var columns by remember { mutableStateOf<List<KanboardApi.Column>>(emptyList()) }
    var members by remember { mutableStateOf<List<KanboardApi.Member>>(emptyList()) }
    var error by remember { mutableStateOf<String?>(null) }
    var busy by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    val colors = VetraTheme.colors

    LaunchedEffect(projectId) {
        columns = repository.loadColumns(projectId).getOrDefault(emptyList())
        members = repository.loadMembers(projectId).getOrDefault(emptyList())
    }

    Column(Modifier.fillMaxSize().background(canvasBrush())) {
        VetraTopAppBar(
            title = { Text("New task", color = colors.textPrimary) },
            navigationIcon = {
                VetraIconButton(
                    onClick = onBack,
                    imageVector = Icons.AutoMirrored.Outlined.ArrowBack,
                    contentDescription = "Back",
                )
            },
        )
        Column(
            Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            Column(Modifier.fillMaxWidth().then(glassModifier()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                VetraTextField(
                    value = title,
                    onValueChange = { title = it },
                    label = "Title",
                    modifier = Modifier.fillMaxWidth(),
                )
                VetraTextField(
                    value = description,
                    onValueChange = { description = it },
                    label = "Description",
                    modifier = Modifier.fillMaxWidth(),
                    singleLine = false,
                    maxLines = 6,
                )
            }

            if (columns.isNotEmpty()) {
                SectionLabel("Column")
                Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    VetraChipFilter(label = "Default", selected = columnId == 0, onClick = { columnId = 0 })
                    columns.forEach { col ->
                        VetraChipFilter(
                            label = col.title,
                            selected = columnId == col.id,
                            onClick = { columnId = col.id },
                        )
                    }
                }
            }

            SectionLabel("Priority")
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                (0..3).forEach { p ->
                    VetraChipFilter(
                        label = priorityLabel(p),
                        selected = priority == p,
                        onClick = { priority = p },
                    )
                }
            }

            SectionLabel("Due date (YYYY-MM-DD)")
            VetraTextField(
                value = due,
                onValueChange = { due = it },
                label = "Due",
                modifier = Modifier.fillMaxWidth(),
            )

            SectionLabel("Color")
            Row(
                Modifier.horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                CreateColorOptions.forEach { c -> ColorSwatch(c, selected = color == c, onClick = { color = c }) }
            }

            SectionLabel("Assignee")
            Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                if (repository.currentUserId() > 0) {
                    VetraChipFilter(
                        label = "Me",
                        selected = ownerId == repository.currentUserId(),
                        onClick = { ownerId = repository.currentUserId() },
                    )
                }
                VetraChipFilter(label = "Unassigned", selected = ownerId == 0, onClick = { ownerId = 0 })
                members.forEach { m ->
                    VetraChipFilter(
                        label = m.name,
                        selected = ownerId == m.id,
                        onClick = { ownerId = m.id },
                    )
                }
            }

            SectionLabel("First comment (optional)")
            VetraTextField(
                value = comment,
                onValueChange = { comment = it },
                label = "Comment",
                modifier = Modifier.fillMaxWidth(),
                singleLine = false,
                maxLines = 4,
            )

            if (error != null) {
                Text(error!!, color = colors.danger)
            }

            VetraButton(
                enabled = !busy && title.isNotBlank(),
                onClick = {
                    scope.launch {
                        busy = true
                        error = null
                        repository.createTask(
                            projectId = projectId,
                            title = title.trim(),
                            description = description.trim(),
                            columnId = columnId.takeIf { it > 0 },
                            priority = priority,
                            due = due.trim().ifBlank { null },
                            colorId = color,
                            ownerId = ownerId,
                            comment = comment.trim().ifBlank { null },
                        )
                            .onSuccess { onCreated(it.id) }
                            .onFailure { error = it.message ?: "Could not create task" }
                        busy = false
                    }
                },
                modifier = Modifier.fillMaxWidth(),
            ) { Text(if (busy) "Creating…" else "Create task") }

            Spacer(Modifier.height(24.dp))
        }
    }
}

@Composable
private fun SectionLabel(text: String) {
    Text(text, color = VetraTheme.colors.textSecondary, fontSize = 13.sp)
}

/** Round color swatch with a checkmark — clearer than a text chip for task colors. */
@Composable
private fun ColorSwatch(name: String, selected: Boolean, onClick: () -> Unit) {
    val colors = VetraTheme.colors
    val tint = com.appdashboard.planning.ui.components.taskColor(name) ?: VetraTheme.colors.brand
    Box(
        modifier = Modifier
            .size(34.dp)
            .clip(CircleShape)
            .background(tint)
            .border(
                width = if (selected) 3.dp else 1.dp,
                color = if (selected) colors.textPrimary else colors.borderSubtle,
                shape = CircleShape,
            )
            .clickable(onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        if (selected) {
            Icon(
                Icons.Outlined.Check,
                contentDescription = name,
                tint = Color.White,
                modifier = Modifier.size(18.dp),
            )
        }
    }
}
