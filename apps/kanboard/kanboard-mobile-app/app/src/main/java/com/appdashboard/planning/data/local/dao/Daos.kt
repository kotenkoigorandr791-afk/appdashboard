package com.appdashboard.planning.data.local.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import com.appdashboard.planning.data.local.entity.ProjectEntity
import com.appdashboard.planning.data.local.entity.TaskEntity
import kotlinx.coroutines.flow.Flow

@Dao
interface ProjectDao {
    @Query("SELECT * FROM projects ORDER BY name COLLATE NOCASE")
    fun observeAll(): Flow<List<ProjectEntity>>

    @Query("SELECT * FROM projects ORDER BY name COLLATE NOCASE")
    suspend fun getAll(): List<ProjectEntity>

    @Query("SELECT * FROM projects WHERE id = :id LIMIT 1")
    suspend fun getById(id: Int): ProjectEntity?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertAll(items: List<ProjectEntity>)

    @Query("DELETE FROM projects")
    suspend fun clear()
}

@Dao
interface TaskDao {
    @Query("SELECT * FROM tasks WHERE status = 'open' ORDER BY priority DESC, id DESC")
    fun observeOpen(): Flow<List<TaskEntity>>

    @Query("SELECT * FROM tasks WHERE status = 'open' ORDER BY priority DESC, id DESC LIMIT :limit")
    suspend fun getOpen(limit: Int = 20): List<TaskEntity>

    @Query("SELECT * FROM tasks WHERE projectId = :projectId ORDER BY columnId ASC, priority DESC, id DESC")
    fun observeByProject(projectId: Int): Flow<List<TaskEntity>>

    @Query("SELECT * FROM tasks WHERE id = :id LIMIT 1")
    fun observeById(id: Int): Flow<TaskEntity?>

    @Query("SELECT * FROM tasks WHERE id = :id LIMIT 1")
    suspend fun getById(id: Int): TaskEntity?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertAll(items: List<TaskEntity>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(item: TaskEntity)

    @Query("DELETE FROM tasks WHERE projectId = :projectId")
    suspend fun deleteByProject(projectId: Int)

    @Query("DELETE FROM tasks WHERE projectId = :projectId AND id NOT IN (:keepIds)")
    suspend fun deleteStaleByProject(projectId: Int, keepIds: List<Int>)

    @Query("DELETE FROM tasks WHERE id = :id")
    suspend fun deleteById(id: Int)

    @Query("DELETE FROM tasks")
    suspend fun clear()
}
