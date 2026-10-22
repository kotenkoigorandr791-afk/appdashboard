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
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
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
import com.appdashboard.planning.data.remote.KanboardApi
import com.appdashboard.planning.data.repo.PlanningRepository
import com.appdashboard.planning.ui.theme.canvasBrush
import com.appdashboard.planning.ui.theme.glassModifier
import com.flyfishxu.vetraui.core.VetraButton
import com.flyfishxu.vetraui.core.VetraChipFilter
import com.flyfishxu.vetraui.core.VetraDangerButton
import com.flyfishxu.vetraui.core.VetraIconButton
import com.flyfishxu.vetraui.core.VetraOutlinedButton
import com.flyfishxu.vetraui.core.VetraTextField
import com.flyfishxu.vetraui.core.VetraTopAppBar
import com.flyfishxu.vetraui.core.theme.VetraTheme
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import kotlinx.coroutines.launch

private val ColorOptions = listOf(
    "yellow", "blue", "green", "purple", "red", "orange", "grey", "brown", "deep_orange", "dark_grey", "pink", "teal", "cyan", "lime", "light_green", "amber",
)

@Composable
fun TaskDetailScreen(
    taskId: Int,
    repository: PlanningRepository,
    onBack: () -> Unit,
) {
    val task by repository.observeTask(taskId).collectAsState(initial = null)
    var title by remember { mutableStateOf("") }
    var description by remember { mutableStateOf("") }
    var columnId by remember { mutableIntStateOf(0) }
    var priority by remember { mutableIntStateOf(0) }
    var due by remember { mutableStateOf("") }
    var color by remember { mutableStateOf("yellow") }
    var status by remember { mutableStateOf("open") }
    var ownerId by remember { mutableIntStateOf(0) }
    var columns by remember { mutableStateOf<List<KanboardApi.Column>>(emptyList()) }
    var members by remember { mutableStateOf<List<KanboardApi.Member>>(emptyList()) }
    var message by remember { mutableStateOf<String?>(null) }
    var busy by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    val colors = VetraTheme.colors

    LaunchedEffect(task?.id) {
        val t = task ?: return@LaunchedEffect
        title = t.title
        description = t.description
        columnId = t.columnId
        priority = t.priority
        due = t.due.orEmpty()
        color = t.color ?: "yellow"
        status = t.status
        ownerId = t.ownerId
        if (t.projectId > 0) {
            columns = repository.loadColumns(t.projectId).getOrDefault(emptyList())
            members = repository.loadMembers(t.projectId).getOrDefault(emptyList())
        }
    }

    Column(
        Modifier
            .fillMaxSize()
            .background(canvasBrush()),
    ) {
        VetraTopAppBar(
            title = { Text("Edit task", color = colors.textPrimary) },
            navigationIcon = {
                VetraIconButton(
                    onClick = onBack,
                    imageVector = Icons.AutoMirrored.Outlined.ArrowBack,
                    contentDescription = "Back",
                )
            },
        )
        if (task == null) {
            Text("Not in cache", Modifier.padding(24.dp), color = colors.textSecondary)
            return
        }
        Column(
            Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            Column(Modifier.then(glassModifier()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                VetraTextField(value = title, onValueChange = { title = it }, label = "Title", modifier = Modifier.fillMaxWidth())
                VetraTextField(
                    value = description,
                    onValueChange = { description = it },
                    label = "Description",
                    modifier = Modifier.fillMaxWidth(),
                    singleLine = false,
                    maxLines = 6,
                )
            }

            SectionLabel("Status / column")
            Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                columns.forEach { col ->
                    VetraChipFilter(
                        label = col.title,
                        selected = columnId == col.id,
                        onClick = { columnId = col.id },
                    )
                }
            }

            SectionLabel("Open / closed")
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                VetraChipFilter(label = "Open", selected = status == "open", onClick = { status = "open" })
                VetraChipFilter(label = "Closed", selected = status == "closed", onClick = { status = "closed" })
            }

            SectionLabel("Priority")
            Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                (0..3).forEach { p ->
                    VetraChipFilter(
                        label = when (p) { 3 -> "High"; 2 -> "Medium"; 1 -> "Low"; else -> "Normal" },
                        selected = priority == p,
                        onClick = { priority = p },
                    )
                }
            }

            SectionLabel("Due date (YYYY-MM-DD)")
            VetraTextField(value = due, onValueChange = { due = it }, label = "Due", modifier = Modifier.fillMaxWidth())

            SectionLabel("Color")
            Row(
                Modifier.horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                ColorOptions.forEach { c ->
                    val tint = com.appdashboard.planning.ui.components.taskColor(c) ?: colors.brand
                    Box(
                        modifier = Modifier
                            .size(34.dp)
                            .clip(CircleShape)
                            .background(tint)
                            .border(
                                width = if (color == c) 3.dp else 1.dp,
                                color = if (color == c) colors.textPrimary else colors.borderSubtle,
                                shape = CircleShape,
                            )
                            .clickable { color = c },
                        contentAlignment = Alignment.Center,
                    ) {
                        if (color == c) {
                            Icon(
                                Icons.Outlined.Check,
                                contentDescription = c,
                                tint = Color.White,
                                modifier = Modifier.size(18.dp),
                            )
                        }
                    }
                }
            }

            if (members.isNotEmpty()) {
                SectionLabel("Assignee")
                Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    VetraChipFilter(label = "Unassigned", selected = ownerId == 0, onClick = { ownerId = 0 })
                    members.forEach { m ->
                        VetraChipFilter(
                            label = m.name,
                            selected = ownerId == m.id,
                            onClick = { ownerId = m.id },
                        )
                    }
                }
            }

            if (message != null) {
                Text(message!!, color = if (message!!.startsWith("Saved")) colors.success else colors.danger)
            }

            VetraButton(
                onClick = {
                    scope.launch {
                        busy = true
                        repository.updateTask(
                            taskId,
                            KanboardApi.TaskPatch(
                                title = title.trim(),
                                description = description,
                                columnId = columnId.takeIf { it > 0 },
                                priority = priority,
                                due = due.trim().ifBlank { null },
                                colorId = color,
                                ownerId = ownerId,
                                status = status,
                            ),
                        ).onSuccess { message = "Saved" }
                            .onFailure { message = it.message }
                        busy = false
                    }
                },
                enabled = !busy,
                modifier = Modifier.fillMaxWidth(),
            ) { Text("Save changes") }

            VetraOutlinedButton(
                onClick = {
                    scope.launch {
                        repository.updateTask(taskId, KanboardApi.TaskPatch(status = "closed"))
                            .onSuccess { onBack() }
                            .onFailure { message = it.message }
                    }
                },
                modifier = Modifier.fillMaxWidth(),
            ) { Text("Close task") }

            VetraDangerButton(
                onClick = {
                    scope.launch {
                        repository.deleteTask(taskId)
                            .onSuccess { onBack() }
                            .onFailure { message = it.message }
                    }
                },
                modifier = Modifier.fillMaxWidth(),
            ) { Text("Delete task") }

            Spacer(Modifier.height(24.dp))
        }
    }
}

@Composable
private fun SectionLabel(text: String) {
    Text(text, color = VetraTheme.colors.textSecondary)
}
