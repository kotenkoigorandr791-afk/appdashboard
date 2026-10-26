package com.appdashboard.planning.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.ErrorOutline
import androidx.compose.material.icons.outlined.ExpandMore
import androidx.compose.material.icons.outlined.Group
import androidx.compose.material.icons.outlined.Refresh
import androidx.compose.material.icons.outlined.TaskAlt
import androidx.compose.material.icons.outlined.WarningAmber
import androidx.compose.material.icons.outlined.WorkspacePremium
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
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
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.appdashboard.planning.data.prefs.SessionStore
import com.appdashboard.planning.data.remote.KanboardApi
import com.appdashboard.planning.data.repo.PlanningRepository
import com.appdashboard.planning.ui.components.BarChart
import com.appdashboard.planning.ui.components.ChartSlice
import com.appdashboard.planning.ui.components.DonutChart
import com.appdashboard.planning.ui.components.DualMetricChart
import com.appdashboard.planning.ui.components.FlowAreaChart
import com.appdashboard.planning.ui.components.StateMessage
import com.appdashboard.planning.ui.theme.canvasBrush
import com.appdashboard.planning.ui.theme.glassModifier
import com.flyfishxu.vetraui.core.VetraIconButton
import com.flyfishxu.vetraui.core.VetraTopAppBar
import com.flyfishxu.vetraui.core.theme.VetraTheme
import kotlinx.coroutines.launch

@Composable
fun StatsScreen(
    repository: PlanningRepository,
    session: SessionStore,
) {
    val projects by repository.observeProjects().collectAsState(initial = emptyList())
    var selectedId by remember { mutableStateOf(session.selectedProjectId()) }
    var menu by remember { mutableStateOf(false) }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var stats by remember { mutableStateOf<KanboardApi.ProjectStats?>(null) }
    val scope = rememberCoroutineScope()
    val colors = VetraTheme.colors
    val glass = session.translucentUi()
    val selected = projects.firstOrNull { it.id == selectedId }

    fun reload(id: Int) {
        if (id <= 0) return
        scope.launch {
            loading = true
            repository.loadStats(id)
                .onSuccess {
                    stats = it
                    error = null
                }
                .onFailure { error = it.message ?: "Could not load statistics" }
            loading = false
        }
    }

    LaunchedEffect(projects) {
        if (selectedId <= 0 && projects.isNotEmpty()) {
            selectedId = projects.first().id
            session.setSelectedProjectId(selectedId)
        }
    }
    LaunchedEffect(selectedId) { reload(selectedId) }

    Column(Modifier.fillMaxSize().background(canvasBrush())) {
        VetraTopAppBar(
            title = {
                Column {
                    Text("Statistics", color = colors.textPrimary, fontWeight = FontWeight.Bold)
                    Row(Modifier.clickable { menu = true }, verticalAlignment = Alignment.CenterVertically) {
                        Text(selected?.name ?: "Select project", color = colors.textSecondary)
                        Icon(Icons.Outlined.ExpandMore, null, tint = colors.textSecondary, modifier = Modifier.size(18.dp))
                    }
                    DropdownMenu(expanded = menu, onDismissRequest = { menu = false }) {
                        projects.forEach { p ->
                            DropdownMenuItem(
                                text = { Text(p.name) },
                                onClick = {
                                    selectedId = p.id
                                    session.setSelectedProjectId(p.id)
                                    menu = false
                                },
                            )
                        }
                    }
                }
            },
            actions = {
                VetraIconButton(
                    onClick = { reload(selectedId) },
                    imageVector = Icons.Outlined.Refresh,
                    contentDescription = "Refresh",
                )
            },
        )

        PullToRefreshBox(
            isRefreshing = loading,
            onRefresh = { reload(selectedId) },
            modifier = Modifier.fillMaxSize(),
        ) {
            when {
                selectedId <= 0 -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    StateMessage(
                        icon = Icons.Outlined.Group,
                        title = "Select a project",
                        subtitle = "Pick a project in the menu above to see its statistics.",
                    )
                }
                loading && stats == null -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = colors.brand)
                }
                stats == null -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    StateMessage(
                        icon = Icons.Outlined.ErrorOutline,
                        title = error ?: "No statistics",
                        subtitle = "Pull down or tap Refresh to try again.",
                        actionLabel = "Retry",
                        onAction = { reload(selectedId) },
                    )
                }
                else -> {
                    val s = stats ?: return@PullToRefreshBox
                    LazyColumn(
                        contentPadding = PaddingValues(16.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp),
                    ) {
                        if (error != null) {
                            item {
                                Text(error!!, color = colors.danger, fontSize = 12.sp)
                            }
                        }
                        item {
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                                MetricCard("Open", s.open.toString(), Icons.Outlined.TaskAlt, Modifier.weight(1f), glass)
                                MetricCard("Closed", s.closed.toString(), Icons.Outlined.TaskAlt, Modifier.weight(1f), glass)
                            }
                        }
                        item {
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                                MetricCard("Overdue", s.overdue.toString(), Icons.Outlined.WarningAmber, Modifier.weight(1f), glass)
                                MetricCard("Unassigned", s.unassigned.toString(), Icons.Outlined.Group, Modifier.weight(1f), glass)
                            }
                        }
                        item {
                            MetricCard("Members", s.members.toString(), Icons.Outlined.Group, Modifier.fillMaxWidth(), glass)
                        }
                        item {
                            Column(Modifier.fillMaxWidth().then(glassModifier(glass)).padding(16.dp)) {
                                DualMetricChart(open = s.open.toFloat(), closed = s.closed.toFloat())
                            }
                        }
                        if (s.taskDistribution.isNotEmpty()) {
                            item {
                                Column(Modifier.fillMaxWidth().then(glassModifier(glass)).padding(16.dp)) {
                                    DonutChart(
                                        title = "Task distribution",
                                        slices = s.taskDistribution.map { ChartSlice(it.label, it.count.toFloat()) },
                                    )
                                }
                            }
                        }
                        if (s.byColumn.isNotEmpty()) {
                            item {
                                Column(Modifier.fillMaxWidth().then(glassModifier(glass)).padding(16.dp)) {
                                    BarChart(
                                        title = "By column (open)",
                                        slices = s.byColumn.map { ChartSlice(it.column ?: "#${it.columnId}", it.open.toFloat()) },
                                    )
                                }
                            }
                        }
                        if (s.userDistribution.isNotEmpty()) {
                            item {
                                Column(Modifier.fillMaxWidth().then(glassModifier(glass)).padding(16.dp)) {
                                    BarChart(
                                        title = "User repartition",
                                        slices = s.userDistribution.map { ChartSlice(it.label, it.count.toFloat()) },
                                    )
                                }
                            }
                        }
                        if (s.byPriority.isNotEmpty()) {
                            item {
                                Column(Modifier.fillMaxWidth().then(glassModifier(glass)).padding(16.dp)) {
                                    DonutChart(
                                        title = "Priority distribution",
                                        slices = s.byPriority.map {
                                            ChartSlice(
                                                when (it.priority) {
                                                    3 -> "High"
                                                    2 -> "Medium"
                                                    1 -> "Low"
                                                    else -> "Normal"
                                                },
                                                it.count.toFloat(),
                                            )
                                        },
                                    )
                                }
                            }
                        }
                        if (s.byColumn.isNotEmpty()) {
                            item {
                                Column(Modifier.fillMaxWidth().then(glassModifier(glass)).padding(16.dp)) {
                                    FlowAreaChart(
                                        title = "Workload by column (flow)",
                                        points = s.byColumn.map { (it.column ?: "Col") to it.total.toFloat() },
                                    )
                                }
                            }
                        }
                        item { Spacer(Modifier.height(24.dp)) }
                    }
                }
            }
        }
    }
}

@Composable
private fun MetricCard(
    label: String,
    value: String,
    icon: ImageVector,
    modifier: Modifier,
    glass: Boolean,
) {
    val colors = VetraTheme.colors
    Row(
        modifier
            .then(glassModifier(glass))
            .padding(horizontal = 14.dp, vertical = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(icon, contentDescription = null, tint = colors.brand, modifier = Modifier.size(26.dp))
        Spacer(Modifier.width(12.dp))
        Column {
            Text(value, color = colors.textPrimary, fontWeight = FontWeight.Bold, fontSize = 20.sp)
            Text(label, color = colors.textSecondary, fontSize = 12.sp)
        }
    }
}
