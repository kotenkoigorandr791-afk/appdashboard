package com.stepmonitor.app.components

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import com.stepmonitor.app.HealthDataType
import com.stepmonitor.app.R

@Composable
fun localizedLogLabel(dataType: String?, syncType: String?): String? {
    if (dataType != null) {
        if (dataType == "all") return stringResource(R.string.logs_filter_all)
        HealthDataType.entries.firstOrNull { it.name.equals(dataType, ignoreCase = true) }
            ?.let { return stringResource(it.nameResId) }
    }
    return when (syncType) {
        "manual" -> stringResource(R.string.logs_sync_manual)
        "auto" -> stringResource(R.string.logs_sync_auto)
        "test" -> stringResource(R.string.logs_sync_test)
        "conflict" -> stringResource(R.string.logs_sync_conflict)
        "notification" -> stringResource(R.string.logs_sync_notification)
        else -> dataType ?: syncType
    }
}