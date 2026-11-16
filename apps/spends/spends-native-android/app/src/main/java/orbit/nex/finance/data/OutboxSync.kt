package orbit.nex.finance.data

import kotlinx.coroutines.flow.first

/**
 * Durable write queue shared by the UI, the notification listener and manual
 * scans. Every charge is stored on disk before it is sent, so losing the
 * connection (or the app process) never loses a transaction. Pending writes are
 * replayed in order on the next refresh or reconnect.
 */
object OutboxSync {

    /** Stores a write. The UI overlays the outbox on the server snapshot. */
    suspend fun enqueue(store: SessionStore, item: OutboxItem) {
        store.enqueueOutbox(listOf(item))
    }

    suspend fun enqueueAll(store: SessionStore, items: List<OutboxItem>) {
        store.enqueueOutbox(items)
    }

    /**
     * Pushes every queued write. Returns true when the outbox is empty
     * afterwards. A network failure stops the loop so order is preserved and the
     * remaining items stay queued for the next attempt.
     */
    suspend fun flush(store: SessionStore, api: SpendsApi): Boolean {
        val session = store.session.first()
        if (session.token.isBlank() || session.endpoint.isBlank()) return false
        val pending = store.extrasNow().outbox.sortedBy { it.at }
        if (pending.isEmpty()) return true
        val sent = mutableSetOf<String>()
        var failed = false
        for (item in pending) {
            val body = when (item.kind) {
                "income" -> """{"incomes":[${api.incomeJson(Income(item.date, item.amount), mode = item.mode, signature = item.id)}]}"""
                else -> """{"expenses":[${api.expenseJson(Expense(item.date, item.store, item.amount, entrySource = item.source, ocrRawStore = item.raw), mode = item.mode, signature = item.id)}]}"""
            }
            try {
                api.pushJson(session, body)
                sent += item.id
            } catch (_: Exception) {
                failed = true
                break
            }
        }
        if (sent.isNotEmpty()) store.removeOutbox(sent)
        return !failed
    }

    /** Pulls the server snapshot and stores it untouched; the UI merges the outbox. */
    suspend fun pull(store: SessionStore, api: SpendsApi, previous: Snapshot, force: Boolean = false): Snapshot {
        val session = store.session.first()
        val fresh = api.pull(session, if (force) Snapshot() else previous)
        store.saveSnapshot(fresh)
        return fresh
    }
}

/** Overlays not-yet-synced writes on top of a server snapshot for display. */
fun Snapshot.withOutbox(outbox: List<OutboxItem>): Snapshot {
    if (outbox.isEmpty()) return this
    var nextExpenses = expenses
    var nextIncomes = incomes
    for (item in outbox.sortedBy { it.at }) {
        val delete = item.mode == "delete" || item.amount < 0.005
        if (item.kind == "income") {
            val existing = nextIncomes.firstOrNull { it.date == item.date }
            nextIncomes = when {
                delete -> nextIncomes.filterNot { it.date == item.date }
                item.mode == "add" && existing != null -> nextIncomes.map { if (it.date == item.date) it.copy(amount = it.amount + item.amount) else it }
                else -> listOf(Income(item.date, item.amount)) + nextIncomes.filterNot { it.date == item.date }
            }
        } else {
            val existing = nextExpenses.firstOrNull { it.date == item.date && it.store == item.store }
            nextExpenses = when {
                delete -> nextExpenses.filterNot { it.date == item.date && it.store == item.store }
                item.mode == "add" && existing != null -> nextExpenses.map {
                    if (it.date == item.date && it.store == item.store) it.copy(amount = it.amount + item.amount) else it
                }
                else -> listOf(
                    Expense(item.date, item.store, item.amount, entrySource = item.source, ocrRawStore = item.raw),
                ) + nextExpenses.filterNot { it.date == item.date && it.store == item.store }
            }
        }
    }
    return copy(expenses = nextExpenses, incomes = nextIncomes)
}
