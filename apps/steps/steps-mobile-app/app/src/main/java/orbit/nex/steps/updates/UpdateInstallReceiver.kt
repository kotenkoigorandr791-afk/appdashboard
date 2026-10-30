package com.stepmonitor.app.updates

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

/**
 * Fires ACTION_INSTALL_PACKAGE after the user taps the "update ready"
 * notification. Using a broadcast keeps the FILE:// provider grant on the
 * PendingIntent without depending on MainActivity lifecycle.
 */
class UpdateInstallReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val apkUri = intent.data ?: return
        val installIntent = Intent(Intent.ACTION_INSTALL_PACKAGE)
            .setDataAndType(apkUri, "application/vnd.android.package-archive")
            .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        runCatching { context.startActivity(installIntent) }
    }
}