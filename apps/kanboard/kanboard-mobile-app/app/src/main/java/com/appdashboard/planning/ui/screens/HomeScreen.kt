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
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Add
import androidx.compose.material.icons.outlined.CalendarMonth
import androidx.compose.material.icons.outlined.ExpandMore
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material.icons.outlined.Refresh
import androidx.compose.material.icons.outlined.ViewAgenda
import androidx.compose.material.icons.outlined.ViewColumn
import androidx.compose.material.icons.automirrored.outlined.ViewList
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
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
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.appdashboard.planning.data.local.entity.TaskEntity
import com.appdashboard.planning.data.prefs.SessionStore
import com.appdashboard.planning.data.prefs.ViewMode
import com.appdashboard.planning.data.remote.KanboardApi
import com.appdashboard.planning.data.repo.PlanningRepository
import com.appdashboard.planning.ui.components.OfflineBanner
import com.appdashboard.planning.ui.components.ProfileAvatar
import com.appdashboard.planning.ui.components.priorityColor
import com.appdashboard.planning.ui.components.priorityLabel
import com.appdashboard.planning.ui.components.taskColor
import com.appdashboard.planning.ui.theme.canvasBrush
import com.appdashboard.planning.ui.theme.glassModifier
import com.flyfishxu.vetraui.core.VetraChipFilter
import com.flyfishxu.vetraui.core.VetraIconButton
import com.flyfishxu.vetraui.core.VetraTopAppBar
import com.flyfishxu.vetraui.core.theme.VetraTheme
import kotlinx.coroutines.launch
import java.time.LocalDate
import java.time.format.DateTimeParseException

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
    var refreshing by remember { mutableStateOf(false) }
    var syncError by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()
    val colors = VetraTheme.colors
    val glass = session.translucentUi()

    suspend fun refresh() {
        refreshing = true
        val result = if (selectedId > 0) repository.syncProject(selectedId) else repository.syncAll()
        syncError = result.exceptionOrNull()?.message
        refreshing = false
    }

    LaunchedEffect(projects) {
        if (selectedId <= 0 && projects.isNotEmpty()) {
            selectedId = projects.first().id
            session.setSelectedProjectId(selectedId)
        }
    }

    // Warm the cache on launch when it is missing or older than five minutes.
    LaunchedEffect(Unit) {
        if (session.isSyncStale()) refresh()
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
                            Icon(Icons.Outlined.ExpandMore, null, tint = colors.textSecondary, modifier = Modifier.size(18.dp))
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
                        onClick = { scope.launch { refresh() } },
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

            if (syncError != null) {
                Spacer(Modifier.height(10.dp))
                OfflineBanner("Sync failed: ${syncError}", Modifier.padding(horizontal = 16.dp))
            }

            Spacer(Modifier.height(10.dp))
            PullToRefreshBox(
                isRefreshing = refreshing,
                onRefresh = { scope.launch { refresh() } },
                modifier = Modifier.fillMaxSize().padding(horizontal = 16.dp),
            ) {
                when {
                    selectedId <= 0 -> EmptyProjects()
                    openTasks.isEmpty() -> EmptyTasks()
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
                Icon(Icons.Outlined.Add, contentDescription = "New task")
            }
        }
    }
}

@Composable
private fun EmptyProjects() {
    com.appdashboard.planning.ui.components.StateMessage(
        icon = Icons.Outlined.ViewColumn,
        title = "No projects yet",
        subtitle = "Tap Refresh to load your projects from Kanboard.",
    )
}

@Composable
private fun EmptyTasks() {
    com.appdashboard.planning.ui.components.EmptyTasks()
}

/** One task row: color swatch, title, and a meta line with column / assignee / due date. */
@Composable
private fun TaskRow(task: TaskEntity, glass: Boolean, onTask: (Int) -> Unit) {
    val colors = VetraTheme.colors
    val accent = taskColor(task.color) ?: priorityColor(task.priority)
    val overdue = isOverdue(task.due)
    Column(
        Modifier
            .fillMaxWidth()
            .then(glassModifier(glass, RoundedCornerShape(16.dp)))
            .clickable { onTask(task.id) }
            .padding(start = 0.dp, top = 12.dp, end = 14.dp, bottom = 12.dp),
    ) {
        Row {
            Box(
                Modifier
                    .width(4.dp)
                    .height(46.dp)
                    .padding(vertical = 0.dp)
                    .background(accent, RoundedCornerShape(4.dp)),
            )
            Spacer(Modifier.width(10.dp))
            Column(Modifier.weight(1f)) {
                Text(task.title, color = colors.textPrimary, fontWeight = FontWeight.SemiBold, maxLines = 2, overflow = TextOverflow.Ellipsis)
                Spacer(Modifier.height(4.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    if (task.priority > 0) {
                        Box(Modifier.size(8.dp).background(priorityColor(task.priority), RoundedCornerShape(4.dp)))
                        Spacer(Modifier.width(6.dp))
                        Text(
                            priorityLabel(task.priority),
                            color = colors.textTertiary,
                            fontSize = 12.sp,
                        )
                        Spacer(Modifier.width(10.dp))
                    }
                    if (task.columnName.isNotBlank()) {
                        Text(task.columnName, color = colors.textSecondary, fontSize = 12.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    }
                }
                if (!task.assignee.isNullOrBlank() || !task.due.isNullOrBlank()) {
                    Spacer(Modifier.height(4.dp))
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                        if (!task.assignee.isNullOrBlank()) {
                            Icon(Icons.Outlined.Person, null, tint = colors.textTertiary, modifier = Modifier.size(13.dp))
                            Text(task.assignee!!, color = colors.textTertiary, fontSize = 12.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
                        }
                        if (!task.due.isNullOrBlank()) {
                            Icon(
                                Icons.Outlined.CalendarMonth,
                                null,
                                tint = if (overdue) colors.danger else colors.textTertiary,
                                modifier = Modifier.size(13.dp),
                            )
                            Text(
                                if (overdue) "Overdue ${task.due}" else "Due ${task.due}",
                                color = if (overdue) colors.danger else colors.textTertiary,
                                fontSize = 12.sp,
                                textDecoration = if (overdue) TextDecoration.LineThrough else null,
                            )
                        }
                    }
                }
            }
        }
    }
}

private fun isOverdue(due: String?): Boolean {
    val raw = due?.trim().orEmpty()
    if (raw.length < 10) return false
    return try {
        LocalDate.parse(raw.substring(0, 10)).isBefore(LocalDate.now())
    } catch (e: DateTimeParseException) {
        false
    }
}

@Composable
private fun ListView(tasks: List<TaskEntity>, glass: Boolean, onTask: (Int) -> Unit) {
    LazyColumn(
        verticalArrangement = Arrangement.spacedBy(10.dp),
        contentPadding = PaddingValues(top = 2.dp, bottom = 96.dp),
    ) {
        items(tasks, key = { it.id }) { task ->
            TaskRow(task, glass, onTask)
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
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        col.title,
                        color = colors.textPrimary,
                        fontWeight = FontWeight.SemiBold,
                        modifier = Modifier.weight(1f),
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                    Text("${colTasks.size}", color = colors.textSecondary, fontSize = 12.sp)
                }
                Spacer(Modifier.height(10.dp))
                LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    items(colTasks, key = { it.id }) { task ->
                        TaskRow(task, glass, onTask)
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
                .padding(horizontal = 12.dp, vertical = 10.dp),
        ) {
            Text("Title", Modifier.weight(1.3f), color = colors.textSecondary, fontWeight = FontWeight.Bold, fontSize = 12.sp)
            Text("Status", Modifier.weight(0.8f), color = colors.textSecondary, fontWeight = FontWeight.Bold, fontSize = 12.sp)
            Text("Priority", Modifier.weight(0.7f), color = colors.textSecondary, fontWeight = FontWeight.Bold, fontSize = 12.sp)
            Text("Due", Modifier.weight(0.7f), color = colors.textSecondary, fontWeight = FontWeight.Bold, fontSize = 12.sp)
        }
        Spacer(Modifier.height(8.dp))
        LazyColumn(verticalArrangement = Arrangement.spacedBy(6.dp), contentPadding = PaddingValues(bottom = 96.dp)) {
            items(tasks, key = { it.id }) { task ->
                Row(
                    Modifier
                        .fillMaxWidth()
                        .then(glassModifier(glass, RoundedCornerShape(12.dp)))
                        .clickable { onTask(task.id) }
                        .padding(horizontal = 12.dp, vertical = 10.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text(task.title, Modifier.weight(1.3f), maxLines = 1, overflow = TextOverflow.Ellipsis, color = colors.textPrimary, fontSize = 13.sp)
                    Text(task.columnName.ifBlank { "—" }, Modifier.weight(0.8f), maxLines = 1, overflow = TextOverflow.Ellipsis, color = colors.textSecondary, fontSize = 12.sp)
                    Row(Modifier.weight(0.7f), verticalAlignment = Alignment.CenterVertically) {
                        Box(Modifier.size(8.dp).background(priorityColor(task.priority), RoundedCornerShape(4.dp)))
                        Spacer(Modifier.width(6.dp))
                        Text(priorityLabel(task.priority), color = colors.textSecondary, fontSize = 12.sp)
                    }
                    Text(task.due ?: "—", Modifier.weight(0.7f), color = colors.textSecondary, fontSize = 12.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
                }
            }
        }
    }
}
