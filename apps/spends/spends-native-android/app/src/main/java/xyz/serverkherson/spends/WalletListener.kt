package xyz.serverkherson.spends

import android.service.notification.NotificationListenerService
import android.service.notification.StatusBarNotification
import xyz.serverkherson.spends.data.PendingCapture
import xyz.serverkherson.spends.data.SessionStore
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import java.time.LocalDate

class WalletListener : NotificationListenerService() {
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    override fun onNotificationPosted(sbn: StatusBarNotification) {
        val pkg = sbn.packageName.orEmpty()
        if (!pkg.contains("wallet", true) && pkg != "com.google.android.gms" && !pkg.contains("nfc", true)) return
        val extras = sbn.notification.extras
        val title = extras.getCharSequence("android.title")?.toString().orEmpty()
        val text = extras.getCharSequence("android.text")?.toString().orEmpty()
        val raw = "$title $text".trim()
        val amount = Regex("""(\d+[.,]\d{2})""").find(raw)?.groupValues?.get(1)?.replace(',', '.')?.toDoubleOrNull() ?: return
        val store = title.ifBlank { text }.take(80).ifBlank { "Wallet" }
        val app = application as? SpendsApplication ?: return
        scope.launch {
            val extrasNow = app.store.extrasNow()
            val item = PendingCapture(LocalDate.now().toString(), store, amount, raw)
            if (extrasNow.pending.any { it.store == item.store && it.amount == item.amount && it.date == item.date }) return@launch
            app.store.saveExtras(extrasNow.copy(pending = extrasNow.pending + item))
        }
    }
}
