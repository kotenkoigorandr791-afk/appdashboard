package com.appdashboard.planning.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Add
import androidx.compose.material.icons.outlined.ExpandMore
import androidx.compose.material.icons.outlined.Refresh
import androidx.compose.material.icons.outlined.ViewAgenda
import androidx.compose.material.icons.outlined.ViewColumn
import androidx.compose.material.icons.automirrored.outlined.ViewList
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.appdashboard.planning.data.local.entity.TaskEntity
import com.appdashboard.planning.data.prefs.SessionStore
import com.appdashboard.planning.data.prefs.ViewMode
import com.appdashboard.planning.data.remote.KanboardApi
import com.appdashboard.planning.data.repo.PlanningRepository
import com.appdashboard.planning.ui.components.ProfileAvatar
import com.appdashboard.planning.ui.components.priorityLabel
import com.appdashboard.planning.ui.theme.canvasBrush
import com.appdashboard.planning.ui.theme.glassModifier
import com.flyfishxu.vetraui.core.VetraChipFilter
import com.flyfishxu.vetraui.core.VetraIconButton
import com.flyfishxu.vetraui.core.VetraTopAppBar
import com.flyfishxu.vetraui.core.theme.VetraTheme
import kotlinx.coroutines.launch

@Composable
fun HomeScreen(
    repository: PlanningRepository,
    session: SessionStore,
    onTask: (Int) -> Unit,
    onCreateTask: (Int) -> Unit,
    onOpenSettings: () -> Unit,
) {
    val projects by repository.observeProjects().collectAsState(initial = emptyList())
    var selectedId by remember { mutableStateOf(session.selectedProjectId()) }
    var viewMode by remember { mutableStateOf(session.viewMode()) }
    var projectMenu by remember { mutableStateOf(false) }
    var columns by remember { mutableStateOf<List<KanboardApi.Column>>(emptyList()) }
    val scope = rememberCoroutineScope()
    val colors = VetraTheme.colors
    val glass = session.translucentUi()

    LaunchedEffect(projects) {
        if (selectedId <= 0 && projects.isNotEmpty()) {
            selectedId = projects.first().id
            session.setSelectedProjectId(selectedId)
        }
    }

    val tasks by repository.observeProjectTasks(selectedId.coerceAtLeast(0))
        .collectAsState(initial = emptyList())
    val openTasks = remember(tasks) { tasks.filter { it.status == "open" } }
    val projectTitle = projects.firstOrNull { it.id == selectedId }?.name
        ?: openTasks.firstOrNull()?.projectName?.takeIf { it.isNotBlank() }
        ?: "Select project"

    LaunchedEffect(selectedId, viewMode) {
        if (selectedId > 0 && viewMode != ViewMode.LIST) {
            columns = repository.loadColumns(selectedId).getOrDefault(emptyList())
        }
    }

    Box(Modifier.fillMaxSize().background(canvasBrush())) {
        Column(Modifier.fillMaxSize()) {
            VetraTopAppBar(
                title = {
                    Column {
                        Text("Tasks", color = colors.textPrimary, fontWeight = FontWeight.Bold)
                        Row(
                            Modifier.clickable { projectMenu = true },
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Text(projectTitle, color = colors.textSecondary, maxLines = 1, overflow = TextOverflow.Ellipsis)
                            androidx.compose.material3.Icon(Icons.Outlined.ExpandMore, null, tint = colors.textSecondary)
                        }
                        DropdownMenu(expanded = projectMenu, onDismissRequest = { projectMenu = false }) {
                            projects.forEach { p ->
                                DropdownMenuItem(
                                    text = { Text(p.name) },
                                    onClick = {
                                        selectedId = p.id
                                        session.setSelectedProjectId(p.id)
                                        projectMenu = false
                                        scope.launch { repository.syncProject(p.id) }
                                    },
                                )
                            }
                        }
                    }
                },
                actions = {
                    VetraIconButton(
                        onClick = {
                            scope.launch {
                                if (selectedId > 0) repository.syncProject(selectedId) else repository.syncAll()
                            }
                        },
                        imageVector = Icons.Outlined.Refresh,
                        contentDescription = "Refresh",
                    )
                    Box(Modifier.padding(end = 8.dp).clickable(onClick = onOpenSettings)) {
                        ProfileAvatar(session.userName(), size = 34.dp)
                    }
                },
            )

            Row(
                Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp)
                    .horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                VetraChipFilter(
                    label = "List",
                    selected = viewMode == ViewMode.LIST,
                    onClick = { viewMode = ViewMode.LIST; session.setViewMode(ViewMode.LIST) },
                    leadingIcon = Icons.Outlined.ViewAgenda,
                )
                VetraChipFilter(
                    label = "Board",
                    selected = viewMode == ViewMode.BOARD,
                    onClick = { viewMode = ViewMode.BOARD; session.setViewMode(ViewMode.BOARD) },
                    leadingIcon = Icons.Outlined.ViewColumn,
                )
                VetraChipFilter(
                    label = "Table",
                    selected = viewMode == ViewMode.TABLE,
                    onClick = { viewMode = ViewMode.TABLE; session.setViewMode(ViewMode.TABLE) },
                    leadingIcon = Icons.AutoMirrored.Outlined.ViewList,
                )
            }

            Spacer(Modifier.height(12.dp))
            Box(Modifier.fillMaxSize().padding(horizontal = 16.dp)) {
                when {
                    selectedId <= 0 -> EmptyState("Choose a project after sync")
                    openTasks.isEmpty() -> EmptyState("No open tasks")
                    viewMode == ViewMode.LIST -> ListView(openTasks, glass, onTask)
                    viewMode == ViewMode.BOARD -> BoardView(openTasks, columns, glass, onTask)
                    viewMode == ViewMode.TABLE -> TableView(openTasks, glass, onTask)
                }
            }
        }
        if (selectedId > 0) {
            FloatingActionButton(
                onClick = { onCreateTask(selectedId) },
                modifier = Modifier.align(Alignment.BottomEnd).padding(20.dp),
                containerColor = colors.brand.copy(alpha = 0.92f),
                contentColor = colors.onBrand,
                shape = RoundedCornerShape(18.dp),
            ) {
                androidx.compose.material3.Icon(Icons.Outlined.Add, contentDescription = "New task")
            }
        }
    }
}

@Composable
private fun EmptyState(message: String) {
    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        Text(message, color = VetraTheme.colors.textSecondary)
    }
}

@Composable
private fun ListView(tasks: List<TaskEntity>, glass: Boolean, onTask: (Int) -> Unit) {
    val colors = VetraTheme.colors
    LazyColumn(
        verticalArrangement = Arrangement.spacedBy(10.dp),
        contentPadding = PaddingValues(bottom = 96.dp),
    ) {
        items(tasks, key = { it.id }) { task ->
            Column(
                Modifier
                    .fillMaxWidth()
                    .then(glassModifier(glass))
                    .clickable { onTask(task.id) }
                    .padding(16.dp),
            ) {
                Text(task.title, color = colors.textPrimary, fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(4.dp))
                Text(
                    listOfNotNull(task.columnName.ifBlank { null }, task.assignee, task.due?.let { "Due $it" })
                        .joinToString(" · ").ifBlank { "#${task.id}" },
                    color = colors.textSecondary,
                )
            }
        }
    }
}

@Composable
private fun BoardView(
    tasks: List<TaskEntity>,
    columns: List<KanboardApi.Column>,
    glass: Boolean,
    onTask: (Int) -> Unit,
) {
    val colors = VetraTheme.colors
    val cols = columns.ifEmpty {
        tasks.map { it.columnId to it.columnName }.distinctBy { it.first }
            .map { KanboardApi.Column(it.first, it.second.ifBlank { "Column" }) }
    }
    Row(
        Modifier.fillMaxSize().horizontalScroll(rememberScrollState()),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        cols.forEach { col ->
            val colTasks = tasks.filter { it.columnId == col.id }
            Column(
                Modifier
                    .width(260.dp)
                    .fillMaxHeight()
                    .then(glassModifier(glass, RoundedCornerShape(22.dp)))
                    .padding(12.dp),
            ) {
                Text("${col.title} (${colTasks.size})", color = colors.textPrimary, fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(8.dp))
                LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    items(colTasks, key = { it.id }) { task ->
                        Column(
                            Modifier
                                .fillMaxWidth()
                                .then(glassModifier(glass, RoundedCornerShape(14.dp)))
                                .clickable { onTask(task.id) }
                                .padding(12.dp),
                        ) {
                            Text(task.title, color = colors.textPrimary, fontWeight = FontWeight.Medium)
                            if (!task.assignee.isNullOrBlank()) {
                                Text(task.assignee!!, color = colors.textTertiary)
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun TableView(tasks: List<TaskEntity>, glass: Boolean, onTask: (Int) -> Unit) {
    val colors = VetraTheme.colors
    Column(Modifier.fillMaxSize()) {
        Row(
            Modifier
                .fillMaxWidth()
                .then(glassModifier(glass, RoundedCornerShape(14.dp)))
                .padding(12.dp),
        ) {
            Text("Title", Modifier.weight(1.3f), color = colors.textSecondary, fontWeight = FontWeight.Bold)
            Text("Status", Modifier.weight(0.8f), color = colors.textSecondary, fontWeight = FontWeight.Bold)
            Text("Priority", Modifier.weight(0.7f), color = colors.textSecondary, fontWeight = FontWeight.Bold)
            Text("Due", Modifier.weight(0.7f), color = colors.textSecondary, fontWeight = FontWeight.Bold)
        }
        Spacer(Modifier.height(8.dp))
        LazyColumn(verticalArrangement = Arrangement.spacedBy(6.dp), contentPadding = PaddingValues(bottom = 96.dp)) {
            items(tasks, key = { it.id }) { task ->
                Row(
                    Modifier
                        .fillMaxWidth()
                        .then(glassModifier(glass, RoundedCornerShape(12.dp)))
                        .clickable { onTask(task.id) }
                        .padding(12.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text(task.title, Modifier.weight(1.3f), maxLines = 1, overflow = TextOverflow.Ellipsis, color = colors.textPrimary)
                    Text(task.columnName.ifBlank { "—" }, Modifier.weight(0.8f), maxLines = 1, overflow = TextOverflow.Ellipsis, color = colors.textSecondary)
                    Text(priorityLabel(task.priority), Modifier.weight(0.7f), color = colors.textSecondary)
                    Text(task.due ?: "—", Modifier.weight(0.7f), color = colors.textSecondary)
                }
            }
        }
    }
}
