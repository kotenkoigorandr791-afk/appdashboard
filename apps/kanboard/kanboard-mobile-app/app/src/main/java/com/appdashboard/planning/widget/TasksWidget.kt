package com.appdashboard.planning.widget

import android.content.Context
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.glance.GlanceId
import androidx.glance.GlanceModifier
import androidx.glance.GlanceTheme
import androidx.glance.action.ActionParameters
import androidx.glance.action.actionStartActivity
import androidx.glance.action.clickable
import androidx.glance.appwidget.GlanceAppWidget
import androidx.glance.appwidget.GlanceAppWidgetReceiver
import androidx.glance.appwidget.action.ActionCallback
import androidx.glance.appwidget.action.actionRunCallback
import androidx.glance.appwidget.cornerRadius
import androidx.glance.appwidget.provideContent
import androidx.glance.background
import androidx.glance.layout.Alignment
import androidx.glance.layout.Column
import androidx.glance.layout.Row
import androidx.glance.layout.Spacer
import androidx.glance.layout.fillMaxSize
import androidx.glance.layout.fillMaxWidth
import androidx.glance.layout.height
import androidx.glance.layout.padding
import androidx.glance.layout.width
import androidx.glance.text.FontWeight
import androidx.glance.text.Text
import androidx.glance.text.TextStyle
import androidx.glance.unit.ColorProvider
import com.appdashboard.planning.MainActivity
import com.appdashboard.planning.PlanningApp
import com.appdashboard.planning.data.repo.WidgetSnapshot
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

private val WIDGET_TASK_LIMIT = 4

class TasksWidget : GlanceAppWidget() {
    override suspend fun provideGlance(context: Context, id: GlanceId) {
        val app = context.applicationContext as PlanningApp
        val paired = app.session.isPaired()
        val snapshot = withContext(Dispatchers.IO) {
            if (!paired) WidgetSnapshot("Tasks", emptyList())
            else runCatching { app.repository.widgetSnapshot(WIDGET_TASK_LIMIT) }
                .getOrDefault(WidgetSnapshot("Open tasks", emptyList()))
        }
        provideContent {
            GlanceTheme {
                WidgetContent(
                    title = if (paired) snapshot.title else "Tasks",
                    tasks = snapshot.tasks.map { it.title to (it.columnName.ifBlank { it.assignee.orEmpty() }) },
                    paired = paired,
                )
            }
        }
    }
}

@Composable
private fun WidgetContent(title: String, tasks: List<Pair<String, String>>, paired: Boolean) {
    val bg = ColorProvider(Color(0xFF18181B))
    val text = ColorProvider(Color(0xFFFAFAFA))
    val muted = ColorProvider(Color(0xFFA1A1AA))
    val accent = ColorProvider(Color(0xFF818CF8))
    val card = ColorProvider(Color(0xFF27272A))

    Column(
        modifier = GlanceModifier
            .fillMaxSize()
            .cornerRadius(22.dp)
            .background(bg)
            .padding(14.dp)
            .clickable(actionStartActivity<MainActivity>()),
    ) {
        Row(
            modifier = GlanceModifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(modifier = GlanceModifier.defaultWeight()) {
                Text(
                    text = title,
                    style = TextStyle(color = text, fontSize = 15.sp, fontWeight = FontWeight.Bold),
                    maxLines = 1,
                )
                Text(
                    text = if (paired) "${tasks.size} open" else "Not connected",
                    style = TextStyle(color = muted, fontSize = 11.sp),
                )
            }
            Text(
                text = "Refresh",
                style = TextStyle(color = accent, fontSize = 12.sp, fontWeight = FontWeight.Bold),
                modifier = GlanceModifier
                    .cornerRadius(10.dp)
                    .background(card)
                    .padding(horizontal = 10.dp, vertical = 6.dp)
                    .clickable(actionRunCallback<WidgetRefreshAction>()),
            )
        }
        Spacer(GlanceModifier.height(10.dp))
        when {
            !paired -> EmptyLine("Open the app and pair your workspace", accent)
            tasks.isEmpty() -> EmptyLine("No open tasks — tap Refresh", muted)
            else -> tasks.forEach { (taskTitle, meta) ->
                Row(
                    modifier = GlanceModifier
                        .fillMaxWidth()
                        .cornerRadius(12.dp)
                        .background(card)
                        .padding(horizontal = 10.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Column(modifier = GlanceModifier.defaultWeight()) {
                        Text(
                            text = taskTitle,
                            style = TextStyle(color = text, fontSize = 13.sp, fontWeight = FontWeight.Medium),
                            maxLines = 1,
                        )
                        if (meta.isNotBlank()) {
                            Text(meta, style = TextStyle(color = muted, fontSize = 11.sp), maxLines = 1)
                        }
                    }
                    Spacer(GlanceModifier.width(6.dp))
                    Text("›", style = TextStyle(color = accent, fontSize = 16.sp))
                }
                Spacer(GlanceModifier.height(6.dp))
            }
        }
    }
}

@Composable
private fun EmptyLine(message: String, color: ColorProvider) {
    Text(
        text = message,
        style = TextStyle(color = color, fontSize = 12.sp),
        modifier = GlanceModifier.padding(vertical = 6.dp),
    )
}

class WidgetRefreshAction : ActionCallback {
    override suspend fun onAction(
        context: Context,
        glanceId: GlanceId,
        parameters: ActionParameters,
    ) {
        val app = context.applicationContext as PlanningApp
        if (app.session.isPaired()) {
            withContext(Dispatchers.IO) { app.repository.syncAll() }
        }
        TasksWidget().update(context, glanceId)
    }
}

class TasksWidgetReceiver : GlanceAppWidgetReceiver() {
    override val glanceAppWidget: GlanceAppWidget = TasksWidget()
}
