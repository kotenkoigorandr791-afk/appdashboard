package com.appdashboard.planning.data.local.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "projects")
data class ProjectEntity(
    @PrimaryKey val id: Int,
    val name: String,
    val description: String,
    val isActive: Boolean,
)

@Entity(tableName = "tasks")
data class TaskEntity(
    @PrimaryKey val id: Int,
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
    val ownerId: Int = 0,
    val color: String? = null,
)
