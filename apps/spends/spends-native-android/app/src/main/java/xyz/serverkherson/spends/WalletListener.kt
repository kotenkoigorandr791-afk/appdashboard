package xyz.serverkherson.spends

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import android.provider.Settings
import android.service.notification.NotificationListenerService
import android.service.notification.StatusBarNotification
import androidx.core.app.NotificationCompat
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import xyz.serverkherson.spends.data.PendingCapture
import xyz.serverkherson.spends.data.WalletNotification
import xyz.serverkherson.spends.data.WalletParser
import java.time.LocalDate

/** True when the system granted Spends access to read notifications. */
object NotificationAccess {
    fun isEnabled(context: Context): Boolean {
        val enabled = runCatching {
            Settings.Secure.getString(context.contentResolver, "enabled_notification_listeners")
        }.getOrNull().orEmpty()
        return enabled.split(':').any { it.contains(context.packageName) }
    }

    fun openSettings(context: Context) {
        val intent = Intent(Settings.ACTION_NOTIFICATION_LISTENER_SETTINGS)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        runCatching { context.startActivity(intent) }
    }
}

/**
 * Background service that only listens to Google Wallet payment notifications.
 * Payment text is parsed locally, matched to a known store and queued for review;
 * nothing is uploaded until the user confirms the capture.
 */
class WalletListener : NotificationListenerService() {
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    override fun onNotificationPosted(sbn: StatusBarNotification) {
        val pkg = sbn.packageName.orEmpty()
        if (!pkg.contains("wallet", true) && pkg != "com.google.android.gms" && !pkg.contains("nfc", true)) return
        val extras = sbn.notification.extras
        val title = extras.getCharSequence("android.title")?.toString().orEmpty()
        val text = extras.getCharSequence("android.text")?.toString().orEmpty()
        if (title.isBlank() && text.isBlank()) return
        val app = application as? SpendsApplication ?: return
        scope.launch {
            val snapshot = runCatching { app.store.snapshot.first() }.getOrNull() ?: return@launch
            val parsed = WalletParser.parse(title, text, snapshot.currency.ifBlank { "USD" }, snapshot.stores) ?: return@launch
            val extrasNow = app.store.extrasNow()
            val duplicate = extrasNow.pending.any { it.store == parsed.store && it.amount == parsed.amount && it.date == LocalDate.now().toString() }
            if (!duplicate) {
                val item = PendingCapture(LocalDate.now().toString(), parsed.store, parsed.amount, parsed.raw)
                app.store.saveExtras(extrasNow.copy(pending = listOf(item) + extrasNow.pending))
            }
            notifyCapture(this@WalletListener, parsed)
        }
    }

    private fun notifyCapture(context: Context, parsed: WalletNotification) {
        val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager ?: return
        val channelId = "wallet_captures"
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && manager.getNotificationChannel(channelId) == null) {
            manager.createNotificationChannel(
                NotificationChannel(channelId, context.getString(R.string.wallet_channel), NotificationManager.IMPORTANCE_DEFAULT),
            )
        }
        val intent = Intent(context, MainActivity::class.java)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP)
            .putExtra(MainActivity.EXTRA_OPEN_PENDING, true)
        val pendingIntent = PendingIntent.getActivity(
            context,
            42,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )
        val notification = NotificationCompat.Builder(context, channelId)
            .setSmallIcon(android.R.drawable.ic_menu_agenda)
            .setContentTitle("${parsed.store} · ${"%.2f".format(parsed.amount)} ${parsed.currency}")
            .setContentText(context.getString(R.string.wallet_notification))
            .setContentIntent(pendingIntent)
            .setAutoCancel(true)
            .build()
        runCatching { manager.notify(4002, notification) }
    }

    override fun onListenerConnected() {
        super.onListenerConnected()
        // Keeps the service alive in the background while notification access is granted.
    }
}
