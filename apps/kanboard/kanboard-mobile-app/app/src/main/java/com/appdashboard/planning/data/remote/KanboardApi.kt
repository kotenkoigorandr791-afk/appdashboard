package com.appdashboard.planning.data.remote

import com.appdashboard.planning.data.prefs.SessionStore
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONArray
import org.json.JSONObject
import java.util.concurrent.TimeUnit

class KanboardApi(private val session: SessionStore) {
    private val client = OkHttpClient.Builder()
        .connectTimeout(12, TimeUnit.SECONDS)
        .readTimeout(20, TimeUnit.SECONDS)
        .writeTimeout(20, TimeUnit.SECONDS)
        .callTimeout(25, TimeUnit.SECONDS)
        .build()

    private val jsonMedia = "application/json; charset=utf-8".toMediaType()

    data class User(val id: Int, val username: String?, val name: String?, val email: String?, val role: String?)
    data class Project(val id: Int, val name: String, val description: String, val isActive: Boolean)
    data class Task(
        val id: Int,
        val projectId: Int,
        val projectName: String,
        val title: String,
        val description: String,
        val columnId: Int,
        val columnName: String,
        val priority: Int,
        val status: String,
        val due: String?,
        val assignee: String?,
        val ownerId: Int,
        val color: String?,
    )
    data class Column(val id: Int, val title: String)
    data class Member(val id: Int, val name: String, val username: String?)
    data class NamedCount(val label: String, val count: Int, val percentage: Double = 0.0)
    data class PriorityCount(val priority: Int, val count: Int)
    data class ColumnStat(val columnId: Int, val column: String?, val open: Int, val total: Int)
    data class ProjectStats(
        val projectId: Int,
        val open: Int,
        val closed: Int,
        val overdue: Int,
        val unassigned: Int,
        val members: Int,
        val byColumn: List<ColumnStat>,
        val byPriority: List<PriorityCount>,
        val taskDistribution: List<NamedCount>,
        val userDistribution: List<NamedCount>,
    )

    data class TaskPatch(
        val title: String? = null,
        val description: String? = null,
        val columnId: Int? = null,
        val priority: Int? = null,
        val due: String? = null,
        val colorId: String? = null,
        val ownerId: Int? = null,
        val status: String? = null,
    )

    fun me(): User {
        val o = get("/me").getJSONObject("user")
        return User(
            id = o.optInt("id"),
            username = o.optStringOrNull("username"),
            name = o.optStringOrNull("name"),
            email = o.optStringOrNull("email"),
            role = o.optStringOrNull("role"),
        )
    }

    fun projects(): List<Project> {
        val arr = get("/projects").optJSONArray("projects") ?: JSONArray()
        return (0 until arr.length()).map { i ->
            val o = arr.getJSONObject(i)
            Project(
                id = o.getInt("id"),
                name = o.optString("name"),
                description = o.optString("description"),
                isActive = o.optBoolean("is_active", true),
            )
        }
    }

    fun myTasks(): List<Task> {
        val arr = get("/my-tasks").optJSONArray("tasks") ?: JSONArray()
        return parseTasks(arr)
    }

    fun projectTasks(projectId: Int, status: String = "open"): List<Task> {
        val arr = get("/projects/$projectId/tasks?status=$status").optJSONArray("tasks") ?: JSONArray()
        return parseTasks(arr)
    }

    fun columns(projectId: Int): List<Column> {
        val arr = get("/projects/$projectId/columns").optJSONArray("columns") ?: JSONArray()
        return (0 until arr.length()).map { i ->
            val o = arr.getJSONObject(i)
            Column(id = o.getInt("id"), title = o.optString("title"))
        }
    }

    fun members(projectId: Int): List<Member> {
        val arr = get("/projects/$projectId/members").optJSONArray("members") ?: JSONArray()
        return (0 until arr.length()).map { i ->
            val o = arr.getJSONObject(i)
            Member(
                id = o.optInt("id"),
                name = o.optStringOrNull("name") ?: o.optString("username"),
                username = o.optStringOrNull("username"),
            )
        }
    }

    fun projectStats(projectId: Int): ProjectStats {
        val o = get("/projects/$projectId/stats").getJSONObject("stats")
        val by = o.optJSONArray("by_column") ?: JSONArray()
        val prio = o.optJSONArray("by_priority") ?: JSONArray()
        val dist = o.optJSONArray("task_distribution") ?: JSONArray()
        val users = o.optJSONArray("user_distribution") ?: JSONArray()
        return ProjectStats(
            projectId = o.optInt("project_id", projectId),
            open = o.optInt("open"),
            closed = o.optInt("closed"),
            overdue = o.optInt("overdue"),
            unassigned = o.optInt("unassigned"),
            members = o.optInt("members"),
            byColumn = (0 until by.length()).map { i ->
                val c = by.getJSONObject(i)
                ColumnStat(
                    columnId = c.optInt("column_id"),
                    column = c.optStringOrNull("column"),
                    open = c.optInt("open"),
                    total = c.optInt("total", c.optInt("open")),
                )
            },
            byPriority = (0 until prio.length()).map { i ->
                val c = prio.getJSONObject(i)
                PriorityCount(priority = c.optInt("priority"), count = c.optInt("count"))
            },
            taskDistribution = (0 until dist.length()).map { i ->
                val c = dist.getJSONObject(i)
                NamedCount(
                    label = c.optStringOrNull("column_title") ?: "Column",
                    count = c.optInt("nb_tasks"),
                    percentage = c.optDouble("percentage"),
                )
            },
            userDistribution = (0 until users.length()).map { i ->
                val c = users.getJSONObject(i)
                NamedCount(
                    label = c.optStringOrNull("user") ?: "User",
                    count = c.optInt("nb_tasks"),
                    percentage = c.optDouble("percentage"),
                )
            },
        )
    }

    fun createTask(
        projectId: Int,
        title: String,
        description: String = "",
        columnId: Int? = null,
        priority: Int? = null,
        due: String? = null,
        colorId: String? = null,
        ownerId: Int? = null,
    ): Task {
        val body = JSONObject().put("title", title).put("description", description)
        if (columnId != null && columnId > 0) body.put("column_id", columnId)
        if (priority != null) body.put("priority", priority)
        if (!due.isNullOrBlank()) body.put("date_due", due)
        if (!colorId.isNullOrBlank()) body.put("color_id", colorId)
        if (ownerId != null) body.put("owner_id", ownerId)
        return parseTask(post("/projects/$projectId/tasks", body).getJSONObject("task"))
    }

    fun createComment(taskId: Int, content: String): Int {
        val body = JSONObject().put("content", content)
        return post("/tasks/$taskId/comments", body).optInt("comment_id")
    }

    fun getTask(taskId: Int): Task {
        return parseTask(get("/tasks/$taskId").getJSONObject("task"))
    }

    fun updateTask(taskId: Int, patch: TaskPatch): Task {
        val body = JSONObject()
        patch.title?.let { body.put("title", it) }
        patch.description?.let { body.put("description", it) }
        patch.columnId?.let { body.put("column_id", it) }
        patch.priority?.let { body.put("priority", it) }
        patch.due?.let { body.put("date_due", it) }
        patch.colorId?.let { body.put("color_id", it) }
        patch.ownerId?.let { body.put("owner_id", it) }
        patch.status?.let { body.put("status", it) }
        return parseTask(patch("/tasks/$taskId", body).getJSONObject("task"))
    }

    fun deleteTask(taskId: Int) {
        request("DELETE", "/tasks/$taskId")
    }

    private fun parseTasks(arr: JSONArray): List<Task> =
        (0 until arr.length()).map { parseTask(arr.getJSONObject(it)) }

    private fun parseTask(o: JSONObject): Task = Task(
        id = o.getInt("id"),
        projectId = o.optInt("project_id"),
        projectName = o.optStringOrNull("project") ?: "",
        title = o.optString("title"),
        description = o.optString("description"),
        columnId = o.optInt("column_id"),
        columnName = o.optStringOrNull("column") ?: "",
        priority = o.optInt("priority"),
        status = o.optString("status", "open"),
        due = o.optStringOrNull("due"),
        assignee = o.optStringOrNull("assignee"),
        ownerId = o.optInt("owner_id"),
        color = o.optStringOrNull("color"),
    )

    private fun get(path: String): JSONObject = request("GET", path)
    private fun post(path: String, body: JSONObject): JSONObject = request("POST", path, body.toString())
    private fun patch(path: String, body: JSONObject): JSONObject = request("PATCH", path, body.toString())

    private fun request(method: String, path: String, body: String? = null): JSONObject {
        val base = session.endpoint() ?: throw IllegalStateException("Not paired")
        val token = session.token() ?: throw IllegalStateException("Not paired")
        val url = base.trimEnd('/') + path
        val builder = Request.Builder()
            .url(url)
            .header("Authorization", "Bearer $token")
            .header("X-Device-Id", session.deviceId())
            .header("Accept", "application/json")
        when {
            method == "GET" -> builder.get()
            method == "DELETE" -> builder.delete()
            body != null -> builder.method(method, body.toRequestBody(jsonMedia))
            else -> builder.method(method, ByteArray(0).toRequestBody(null))
        }
        client.newCall(builder.build()).execute().use { resp ->
            val text = resp.body?.string().orEmpty()
            val json = runCatching { JSONObject(text) }.getOrElse {
                throw IllegalStateException("Server returned an invalid response (HTTP ${resp.code})")
            }
            if (!resp.isSuccessful || !json.optBoolean("ok", false)) {
                // org.json renders a JSON null as the literal string "null",
                // so a missing message must be detected with isNull().
                val err = json.optString("error", "http_${resp.code}")
                val msg = if (json.isNull("message")) "" else json.optString("message", "")
                throw IllegalStateException(friendlyError(err, msg, resp.code))
            }
            return json
        }
    }

    /** Turn transport/ACL codes into something a person can act on. */
    private fun friendlyError(error: String, message: String, code: Int): String = when {
        error.contains("403") -> "No access to this project (HTTP 403)"
        error.contains("401") -> "Session expired — reconnect in Settings"
        error == "kanboard_db_unavailable" || error == "kanboard_not_migrated" -> "Server is not ready yet"
        error == "kanboard_user_missing" -> "Account is not provisioned on the server"
        error.startsWith("kanboard_http") -> "Server error ($error)"
        message.isNotBlank() -> message
        else -> "$error (HTTP $code)"
    }
}

private fun JSONObject.optStringOrNull(key: String): String? {
    if (!has(key) || isNull(key)) return null
    val v = optString(key, "")
    return v.ifBlank { null }
}
