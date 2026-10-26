package com.appdashboard.planning

import android.app.Application
import com.appdashboard.planning.data.local.PlanningDatabase
import com.appdashboard.planning.data.prefs.SessionStore
import com.appdashboard.planning.data.remote.KanboardApi
import com.appdashboard.planning.data.repo.PlanningRepository
import com.appdashboard.planning.widget.TasksWidget
import androidx.glance.appwidget.updateAll

class PlanningApp : Application() {
    lateinit var session: SessionStore
        private set
    lateinit var repository: PlanningRepository
        private set

    override fun onCreate() {
        super.onCreate()
        session = SessionStore(this)
        val db = PlanningDatabase.get(this)
        repository = PlanningRepository(
            api = KanboardApi(session),
            projectDao = db.projectDao(),
            taskDao = db.taskDao(),
            session = session,
        )
        repository.onDataChanged = {
            runCatching { TasksWidget().updateAll(this@PlanningApp) }
        }
    }
}
