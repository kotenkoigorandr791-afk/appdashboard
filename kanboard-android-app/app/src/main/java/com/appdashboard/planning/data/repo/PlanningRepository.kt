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

class PlanningRepository(
    private val api: KanboardApi,
    private val projectDao: ProjectDao,
    private val taskDao: TaskDao,
    private val session: SessionStore,
) {
    fun observeProjects(): Flow<List<ProjectEntity>> = projectDao.observeAll()
    fun observeOpenTasks(): Flow<List<TaskEntity>> = taskDao.observeOpen()
    fun observeProjectTasks(projectId: Int): Flow<List<TaskEntity>> = taskDao.observeByProject(projectId)
    fun observeTask(id: Int): Flow<TaskEntity?> = taskDao.observeById(id)

    suspend fun pairAndSync(endpoint: String, token: String) = withContext(Dispatchers.IO) {
        session.pair(endpoint, token)
        syncAll()
    }

    suspend fun syncAll(): Result<Unit> = withContext(Dispatchers.IO) {
        runCatching {
            val me = api.me()
            session.setUser(me.name ?: me.username ?: me.email, me.email, me.role ?: me.username)
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
                val open = api.projectTasks(pid, "open").map { it.toEntity() }
                val closed = runCatching { api.projectTasks(pid, "closed").map { it.toEntity() } }.getOrDefault(emptyList())
                taskDao.deleteByProject(pid)
                taskDao.upsertAll(open + closed)
            }
        }
    }

    suspend fun syncProject(projectId: Int): Result<Unit> = withContext(Dispatchers.IO) {
        runCatching {
            session.setSelectedProjectId(projectId)
            val open = api.projectTasks(projectId, "open").map { it.toEntity() }
            val closed = runCatching { api.projectTasks(projectId, "closed").map { it.toEntity() } }.getOrDefault(emptyList())
            taskDao.deleteByProject(projectId)
            taskDao.upsertAll(open + closed)
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

    suspend fun createTask(projectId: Int, title: String, description: String, columnId: Int? = null): Result<TaskEntity> =
        withContext(Dispatchers.IO) {
            runCatching {
                val created = api.createTask(projectId, title, description, columnId).toEntity()
                taskDao.upsert(created)
                created
            }
        }

    suspend fun updateTask(taskId: Int, patch: KanboardApi.TaskPatch): Result<TaskEntity> =
        withContext(Dispatchers.IO) {
            runCatching {
                val updated = api.updateTask(taskId, patch).toEntity()
                taskDao.upsert(updated)
                updated
            }
        }

    suspend fun deleteTask(taskId: Int): Result<Unit> = withContext(Dispatchers.IO) {
        runCatching {
            api.deleteTask(taskId)
            taskDao.deleteById(taskId)
        }
    }

    suspend fun disconnect() = withContext(Dispatchers.IO) {
        session.clear()
        projectDao.clear()
        taskDao.clear()
    }

    suspend fun widgetTasks(limit: Int = 5): List<TaskEntity> = withContext(Dispatchers.IO) {
        val projectId = session.selectedProjectId()
        if (projectId > 0) {
            taskDao.getOpen(50).filter { it.projectId == projectId }.take(limit)
        } else {
            taskDao.getOpen(limit)
        }
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
