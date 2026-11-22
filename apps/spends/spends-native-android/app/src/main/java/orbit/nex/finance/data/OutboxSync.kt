package orbit.nex.finance.data

import kotlinx.coroutines.flow.first

/**
 * Durable write queue shared by the UI, the notification listener and manual
 * scans. Every charge is stored on disk before it is sent, so losing the
 * connection (or the app process) never loses a transaction. Pending writes are
 * replayed in order on the next refresh or reconnect.
 */
object OutboxSync {
    private const val FLUSH_BATCH_SIZE = 50

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
        // One HTTP round trip used to be made per queued write. After a long
        // offline period that meant hundreds of requests (and hundreds of
        // server transactions). Send bounded batches instead; signatures keep
        // retries idempotent.
        var failed = false
        for (chunk in pending.chunked(FLUSH_BATCH_SIZE)) {
            val expenses = chunk.filter { it.kind != "income" }
                .joinToString(",") { item ->
                    api.expenseJson(
                        Expense(item.date, item.store, item.amount, entrySource = item.source, ocrRawStore = item.raw),
                        mode = item.mode,
                        signature = item.id,
                    )
                }
            val incomes = chunk.filter { it.kind == "income" }
                .joinToString(",") { item ->
                    api.incomeJson(Income(item.date, item.amount), mode = item.mode, signature = item.id)
                }
            val parts = buildList {
                if (expenses.isNotEmpty()) add("\"expenses\":[$expenses]")
                if (incomes.isNotEmpty()) add("\"incomes\":[$incomes]")
            }
            try {
                api.pushJson(session, "{${parts.joinToString(",")}}")
                store.removeOutbox(chunk.map { it.id }.toSet())
            } catch (_: Exception) {
                failed = true
                break
            }
        }
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
