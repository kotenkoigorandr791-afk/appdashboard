package com.appdashboard.planning

import android.app.Application
import com.appdashboard.planning.data.local.PlanningDatabase
import com.appdashboard.planning.data.prefs.SessionStore
import com.appdashboard.planning.data.remote.KanboardApi
import com.appdashboard.planning.data.repo.PlanningRepository

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
    }
}
