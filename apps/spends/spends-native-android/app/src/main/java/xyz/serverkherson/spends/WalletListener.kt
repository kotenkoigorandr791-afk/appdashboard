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
import xyz.serverkherson.spends.data.Dispute
import xyz.serverkherson.spends.data.Expense
import xyz.serverkherson.spends.data.PendingCapture
import xyz.serverkherson.spends.data.SessionStore
import xyz.serverkherson.spends.data.SpendsApi
import xyz.serverkherson.spends.data.WalletNotification
import xyz.serverkherson.spends.data.WalletParser
import java.time.LocalDate
import java.util.Locale

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
 * The only packages Spends ever reads. Google Wallet publishes its payment
 * notifications from here; every other notification is ignored untouched.
 */
object WalletPackages {
    private val allowed = setOf(
        "com.google.android.apps.walletnfcrel",
    )

    fun isWallet(packageName: String?): Boolean =
        packageName != null && packageName.trim().lowercase(Locale.ROOT) in allowed
}

/** Outcome of a manual scan, surfaced in Settings. */
data class ScanResult(
    val scanned: Int = 0,
    val written: Int = 0,
    val conflicts: Int = 0,
    /** "notification_access" | "not_paired" | "push_failed" or empty when all good. */
    val error: String = "",
)

/**
 * Background service that only listens to Google Wallet payment notifications.
 * Payment text is parsed locally, matched to a known store and queued for review;
 * nothing is uploaded until the user confirms the capture.
 */
class WalletListener : NotificationListenerService() {
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    override fun onNotificationPosted(sbn: StatusBarNotification) {
        if (!WalletPackages.isWallet(sbn.packageName)) return
        val extras = sbn.notification.extras
        val title = extras.getCharSequence("android.title")?.toString().orEmpty()
        val text = extras.getCharSequence("android.text")?.toString().orEmpty()
        if (title.isBlank() && text.isBlank()) return
        val app = application as? SpendsApplication ?: return
        scope.launch {
            val snapshot = runCatching { app.store.snapshot.first() }.getOrNull() ?: return@launch
            val parsed = WalletParser.parse(title, text, snapshot.currency.ifBlank { "USD" }, snapshot.stores) ?: return@launch
            val extrasNow = app.store.extrasNow()
            if (!parsed.matched) {
                app.store.saveExtras(
                    extrasNow.copy(
                        disputes = addDispute(
                            extrasNow.disputes,
                            disputeOf(parsed, snapshot.currency, title, text),
                        ),
                    ),
                )
                notifyCapture(this@WalletListener, parsed)
                return@launch
            }
            val duplicate = extrasNow.pending.any { it.store == parsed.store && it.amount == parsed.amount && it.date == LocalDate.now().toString() }
            if (!duplicate) {
                val item = PendingCapture(LocalDate.now().toString(), parsed.store, parsed.amount, parsed.raw)
                app.store.saveExtras(extrasNow.copy(pending = listOf(item) + extrasNow.pending))
            }
            notifyCapture(this@WalletListener, parsed)
        }
    }

    override fun onListenerConnected() {
        super.onListenerConnected()
        // Keeps the service alive in the background while notification access is granted.
        instance = this
    }

    override fun onDestroy() {
        if (instance === this) instance = null
        super.onDestroy()
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

    companion object {
        @Volatile private var instance: WalletListener? = null

        /** Currently posted wallet notifications, or null when the service is not connected. */
        fun activeWalletNotifications(): List<StatusBarNotification>? {
            val service = instance ?: return null
            val all = runCatching { service.activeNotifications?.toList() }.getOrNull() ?: return null
            return all.filter { WalletPackages.isWallet(it.packageName) }
        }

        /** Removes a notification after its expense is safely stored. */
        fun cancel(key: String) {
            runCatching { instance?.cancelNotification(key) }
        }
    }
}

/** Builds a dispute record from a parsed but unmatched wallet notification. */
private fun disputeOf(parsed: WalletNotification, fallbackCurrency: String, title: String, text: String): Dispute =
    Dispute(
        source = "wallet",
        rawName = parsed.store.take(80),
        amount = parsed.amount,
        currency = parsed.currency.ifBlank { fallbackCurrency },
        date = LocalDate.now().toString(),
        raw = parsed.raw.ifBlank { listOf(title, text).filter { it.isNotBlank() }.joinToString(" · ") },
        reason = "store_not_found",
    )

/** Appends a dispute unless an identical one is already waiting for review. */
internal fun addDispute(list: List<Dispute>, item: Dispute): List<Dispute> =
    if (list.any { it.source == item.source && it.raw == item.raw && it.amount == item.amount }) list else list + item

/**
 * Scans the notifications that are on screen right now. Only Google Wallet
 * packages are inspected; every successfully stored charge is removed from the
 * notification shade, while unresolved ones turn into disputes.
 */
object WalletScanner {
    suspend fun scan(store: SessionStore, api: SpendsApi): ScanResult {
        val notifications = WalletListener.activeWalletNotifications()
            ?: return ScanResult(error = "notification_access")
        val session = store.session.first()
        val snapshot = store.snapshot.first()
        if (session.token.isBlank()) return ScanResult(error = "not_paired")

        val originalDisputes = store.extrasNow().disputes
        var disputes = originalDisputes
        var scanned = 0
        var written = 0
        var conflicts = 0
        var failed = false
        val toRemove = mutableListOf<String>()
        val today = LocalDate.now().toString()

        for (sbn in notifications) {
            val extras = sbn.notification.extras
            val title = extras.getCharSequence("android.title")?.toString().orEmpty()
            val text = extras.getCharSequence("android.text")?.toString().orEmpty()
            if (title.isBlank() && text.isBlank()) continue
            scanned++
            val parsed = WalletParser.parse(title, text, snapshot.currency.ifBlank { "USD" }, snapshot.stores)
            if (parsed == null) {
                val raw = listOf(title, text).filter { it.isNotBlank() }.joinToString(" · ")
                disputes = addDispute(
                    disputes,
                    Dispute(
                        source = "wallet",
                        rawName = raw.take(80),
                        amount = 0.0,
                        currency = snapshot.currency,
                        date = today,
                        raw = raw,
                        reason = "unreadable",
                    ),
                )
                conflicts++
                continue
            }
            if (!parsed.matched) {
                disputes = addDispute(disputes, disputeOf(parsed, snapshot.currency, title, text))
                conflicts++
                continue
            }
            val stored = runCatching {
                api.pushJson(
                    session,
                    """{"expenses":[${api.expenseJson(Expense(today, parsed.store, parsed.amount, entrySource = "wallet"))}]}""",
                )
            }.isSuccess
            if (stored) {
                written++
                toRemove += sbn.key
            } else {
                failed = true
            }
        }

        if (disputes.size != originalDisputes.size) {
            store.saveExtras(store.extrasNow().copy(disputes = disputes))
        }
        toRemove.forEach { WalletListener.cancel(it) }
        if (written > 0) runCatching { store.saveSnapshot(api.pull(session)) }

        return ScanResult(
            scanned = scanned,
            written = written,
            conflicts = conflicts,
            error = if (failed) "push_failed" else "",
        )
    }

    /** Debug helper: plants one wallet and one OCR conflict so the flow can be tested. */
    suspend fun seedDemoConflicts(store: SessionStore) {
        val extras = store.extrasNow()
        if (extras.disputes.isNotEmpty()) return
        val today = LocalDate.now().toString()
        store.saveExtras(
            extras.copy(
                disputes = listOf(
                    Dispute(
                        source = "wallet",
                        rawName = "SKLEP ZABKA 8421 ?",
                        amount = 24.99,
                        currency = "PLN",
                        date = today,
                        raw = "Zapłacono 24,99 PLN · SKLEP ZABKA 8421 ?",
                        reason = "store_not_found",
                    ),
                    Dispute(
                        source = "ocr",
                        rawName = "SILPO",
                        amount = 0.0,
                        currency = "PLN",
                        date = today,
                        raw = "OCR: SILPO  ·  0,00  ·  nieczytelny paragon",
                        reason = "unreadable",
                    ),
                ),
            ),
        )
    }
}
