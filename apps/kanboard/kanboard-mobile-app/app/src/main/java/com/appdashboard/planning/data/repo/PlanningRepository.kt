package com.appdashboard.planning.data.repo

import com.appdashboard.planning.data.local.dao.ProjectDao
import com.appdashboard.planning.data.local.dao.TaskDao
import com.appdashboard.planning.data.local.entity.ProjectEntity
import com.appdashboard.planning.data.local.entity.TaskEntity
import com.appdashboard.planning.data.prefs.SessionStore
import com.appdashboard.planning.data.remote.KanboardApi
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.withContext

data class WidgetSnapshot(
    val title: String,
    val tasks: List<TaskEntity>,
)

class PlanningRepository(
    private val api: KanboardApi,
    private val projectDao: ProjectDao,
    private val taskDao: TaskDao,
    private val session: SessionStore,
) {
    /** Called after the local cache changes so home-screen widgets can redraw. */
    var onDataChanged: (suspend () -> Unit)? = null

    fun observeProjects(): Flow<List<ProjectEntity>> = projectDao.observeAll()
    fun observeOpenTasks(): Flow<List<TaskEntity>> = taskDao.observeOpen()
    fun observeProjectTasks(projectId: Int): Flow<List<TaskEntity>> = taskDao.observeByProject(projectId)
    fun observeTask(id: Int): Flow<TaskEntity?> = taskDao.observeById(id)
    fun currentUserId(): Int = session.userId()
    fun lastSyncAt(): Long = session.lastSyncAt()
    fun isSyncStale(): Boolean = session.isSyncStale()

    suspend fun pairAndSync(endpoint: String, token: String) = withContext(Dispatchers.IO) {
        session.pair(endpoint, token)
        syncAll()
    }

    suspend fun syncAll(): Result<Unit> = withContext(Dispatchers.IO) {
        runCatching {
            val me = api.me()
            session.setUser(me.id, me.name ?: me.username ?: me.email, me.email, me.role ?: me.username)
            val projects = api.projects().map {
                ProjectEntity(it.id, it.name, it.description, it.isActive)
            }
            projectDao.upsertAll(projects)
            if (session.selectedProjectId() <= 0 && projects.isNotEmpty()) {
                session.setSelectedProjectId(projects.first().id)
            }
            val myTasks = api.myTasks().map { it.toEntity() }
            taskDao.upsertAll(myTasks)
            val focus = session.selectedProjectId().takeIf { it > 0 } ?: projects.firstOrNull()?.id
            val toSync = buildList {
                if (focus != null) add(focus)
                projects.map { it.id }.filter { it != focus }.take(6).forEach { add(it) }
            }
            for (pid in toSync) {
                syncProjectLocked(pid)
            }
            session.setLastSyncAt()
            notifyChanged()
        }
    }

    suspend fun syncProject(projectId: Int): Result<Unit> = withContext(Dispatchers.IO) {
        runCatching {
            session.setSelectedProjectId(projectId)
            syncProjectLocked(projectId)
            session.setLastSyncAt()
            notifyChanged()
        }
    }

    /**
     * Reconcile one project without emptying the cache first: remote rows are
     * upserted and only tasks that no longer exist on the server are removed.
     * This keeps the list usable while offline and avoids UI flicker.
     */
    private suspend fun syncProjectLocked(projectId: Int) {
        val open = api.projectTasks(projectId, "open").map { it.toEntity() }
        val closed = runCatching { api.projectTasks(projectId, "closed").map { it.toEntity() } }
            .getOrDefault(emptyList())
        val fresh = open + closed
        if (fresh.isNotEmpty()) {
            taskDao.upsertAll(fresh)
            taskDao.deleteStaleByProject(projectId, fresh.map { it.id })
        } else {
            taskDao.deleteByProject(projectId)
        }
    }

    suspend fun loadColumns(projectId: Int): Result<List<KanboardApi.Column>> = withContext(Dispatchers.IO) {
        runCatching { api.columns(projectId) }
    }

    suspend fun loadMembers(projectId: Int): Result<List<KanboardApi.Member>> = withContext(Dispatchers.IO) {
        runCatching { api.members(projectId) }
    }

    suspend fun loadStats(projectId: Int): Result<KanboardApi.ProjectStats> = withContext(Dispatchers.IO) {
        runCatching { api.projectStats(projectId) }
    }

    suspend fun createTask(
        projectId: Int,
        title: String,
        description: String,
        columnId: Int? = null,
        priority: Int? = null,
        due: String? = null,
        colorId: String? = null,
        ownerId: Int? = null,
        comment: String? = null,
    ): Result<TaskEntity> =
        withContext(Dispatchers.IO) {
            runCatching {
                val created = api.createTask(
                    projectId = projectId,
                    title = title,
                    description = description,
                    columnId = columnId,
                    priority = priority,
                    due = due,
                    colorId = colorId,
                    ownerId = ownerId,
                ).toEntity()
                taskDao.upsert(created)
                // A first comment is optional: never fail the task itself if it is rejected.
                val note = comment?.trim().orEmpty()
                if (note.isNotEmpty()) {
                    runCatching { api.createComment(created.id, note) }
                }
                notifyChanged()
                created
            }
        }

    suspend fun updateTask(taskId: Int, patch: KanboardApi.TaskPatch): Result<TaskEntity> =
        withContext(Dispatchers.IO) {
            runCatching {
                val updated = api.updateTask(taskId, patch).toEntity()
                taskDao.upsert(updated)
                notifyChanged()
                updated
            }
        }

    suspend fun deleteTask(taskId: Int): Result<Unit> = withContext(Dispatchers.IO) {
        runCatching {
            api.deleteTask(taskId)
            taskDao.deleteById(taskId)
            notifyChanged()
        }
    }

    suspend fun disconnect() = withContext(Dispatchers.IO) {
        session.clear()
        projectDao.clear()
        taskDao.clear()
        notifyChanged()
    }

    suspend fun widgetSnapshot(limit: Int = 5): WidgetSnapshot = withContext(Dispatchers.IO) {
        val projectId = session.selectedProjectId()
        val tasks = if (projectId > 0) {
            taskDao.getOpen(50).filter { it.projectId == projectId }.take(limit)
        } else {
            taskDao.getOpen(limit)
        }
        val title = if (projectId > 0) {
            projectDao.getById(projectId)?.name ?: tasks.firstOrNull()?.projectName ?: "Open tasks"
        } else {
            "Open tasks"
        }
        WidgetSnapshot(title = title, tasks = tasks)
    }

    /** Kept for callers that only need the tasks (widget refresh action). */
    suspend fun widgetTasks(limit: Int = 5): List<TaskEntity> = widgetSnapshot(limit).tasks

    private suspend fun notifyChanged() {
        runCatching { onDataChanged?.invoke() }
    }

    private fun KanboardApi.Task.toEntity() = TaskEntity(
        id = id,
        projectId = projectId,
        projectName = projectName,
        title = title,
        description = description,
        columnId = columnId,
        columnName = columnName,
        priority = priority,
        status = status,
        due = due,
        assignee = assignee,
        ownerId = ownerId,
        color = color,
    )
}
