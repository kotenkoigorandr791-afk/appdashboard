package com.stepmonitor.app
import kotlinx.serialization.Serializable
@Serializable
data class LocalFeedbackEntry(
    val id: String,
    val content: String,
    val createdAt: Long,
    val status: String = ""
)
