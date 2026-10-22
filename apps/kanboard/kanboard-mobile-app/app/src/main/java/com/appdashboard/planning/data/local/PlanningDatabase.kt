package com.appdashboard.planning.data.local

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase
import com.appdashboard.planning.data.local.dao.ProjectDao
import com.appdashboard.planning.data.local.dao.TaskDao
import com.appdashboard.planning.data.local.entity.ProjectEntity
import com.appdashboard.planning.data.local.entity.TaskEntity

@Database(entities = [ProjectEntity::class, TaskEntity::class], version = 2, exportSchema = false)
abstract class PlanningDatabase : RoomDatabase() {
    abstract fun projectDao(): ProjectDao
    abstract fun taskDao(): TaskDao

    companion object {
        @Volatile private var instance: PlanningDatabase? = null

        fun get(context: Context): PlanningDatabase {
            return instance ?: synchronized(this) {
                instance ?: Room.databaseBuilder(
                    context.applicationContext,
                    PlanningDatabase::class.java,
                    "planning_cache.db",
                ).fallbackToDestructiveMigration().build().also { instance = it }
            }
        }
    }
}
