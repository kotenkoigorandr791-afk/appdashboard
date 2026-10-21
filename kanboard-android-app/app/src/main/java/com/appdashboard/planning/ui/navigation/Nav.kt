package com.appdashboard.planning.ui.navigation

object Routes {
    const val Pairing = "pairing"
    const val Main = "main"
    const val Task = "task/{taskId}"
    const val TaskCreate = "task-create/{projectId}"

    fun task(id: Int) = "task/$id"
    fun taskCreate(projectId: Int) = "task-create/$projectId"
}

enum class MainTab { Home, Stats, Settings }
