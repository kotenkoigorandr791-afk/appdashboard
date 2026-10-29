package xyz.serverkherson.spends

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch
import xyz.serverkherson.spends.data.BackupEntry
import xyz.serverkherson.spends.data.BackupFile
import xyz.serverkherson.spends.data.LocalBackupPayload
import xyz.serverkherson.spends.data.Expense
import xyz.serverkherson.spends.data.Goal
import xyz.serverkherson.spends.data.Income
import xyz.serverkherson.spends.data.LocalExtras
import xyz.serverkherson.spends.data.PendingCapture
import xyz.serverkherson.spends.data.Rates
import xyz.serverkherson.spends.data.Session
import xyz.serverkherson.spends.data.SessionStore
import xyz.serverkherson.spends.data.Snapshot
import xyz.serverkherson.spends.data.SpendsApi
import java.time.LocalDate

data class AppState(
    val session: Session = Session(),
    val snapshot: Snapshot = Snapshot(),
    val extras: LocalExtras = LocalExtras(),
    val loading: Boolean = true,
    val error: String? = null,
    val rates: Rates = Rates(),
    val backups: List<BackupFile> = emptyList(),
    val localBackups: List<BackupFile> = emptyList(),
) {
    /** Server and device backups merged by name. */
    val allBackups: List<BackupEntry>
        get() {
            val names = (backups.map { it.name } + localBackups.map { it.name }).distinct()
            return names.map { name ->
                val server = backups.firstOrNull { it.name == name }
                val local = localBackups.firstOrNull { it.name == name }
                BackupEntry(
                    name = name,
                    size = server?.size?.takeIf { it > 0 } ?: local?.size ?: 0,
                    mtime = maxOf(server?.mtime ?: 0L, local?.mtime ?: 0L),
                    local = local != null,
                    server = server != null,
                )
            }.sortedByDescending { it.mtime }
        }
}

class SpendsViewModel(private val store: SessionStore, private val api: SpendsApi) : ViewModel() {
    private val loading = MutableStateFlow(false)
    private val error = MutableStateFlow<String?>(null)
    private val rates = MutableStateFlow(Rates())
    private val backups = MutableStateFlow<List<BackupFile>>(emptyList())
    private val localBackups = MutableStateFlow<List<BackupFile>>(emptyList())
    private val json = kotlinx.serialization.json.Json { ignoreUnknownKeys = true; prettyPrint = false }
    val state: StateFlow<AppState> = combine(store.session, store.snapshot, store.extras, loading, error) { session, snapshot, extras, busy, message ->
        AppState(session, snapshot, extras, busy, message, rates.value, backups.value)
    }
        .combine(rates) { s, r -> s.copy(rates = r) }
        .combine(backups) { s, b -> s.copy(backups = b) }
        .combine(localBackups) { s, b -> s.copy(localBackups = b) }
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), AppState())

    init {
        localBackups.value = store.localBackups()
    }

    fun pair(linkOrEndpoint: String, suppliedToken: String = "", done: () -> Unit) = launch {
        val (endpoint, token) = parsePairing(linkOrEndpoint, suppliedToken) ?: return@launch fail("Enter a pairing link or endpoint and token")
        run("Could not pair") { store.saveSession(api.profile(endpoint, token)); refresh(); done() }
    }
    fun refresh() = launch { pull(quiet = false) }

    /** Background pull used by the periodic sync: never shows the loading indicator. */
    fun refreshQuietly() = launch { pull(quiet = true) }

    private suspend fun pull(quiet: Boolean) {
        val session = state.value.session
        if (session.token.isBlank() || session.endpoint.isBlank()) return
        run("Could not refresh", quiet) {
            val fresh = api.pull(session)
            store.saveSnapshot(fresh)
            runCatching { rates.value = api.rates(session, fresh.currency.ifBlank { "USD" }) }
            runCatching { backups.value = api.backups(session) }
        }
    }
    fun addExpense(date: String, storeName: String, amount: String, source: String = "manual", done: () -> Unit = {}) = launch {
        val numeric = amount.replace(',', '.').toDoubleOrNull()
        if (date.isBlank() || storeName.isBlank() || numeric == null || numeric <= 0) return@launch fail("Enter date, merchant, and amount")
        run("Could not save") { api.pushJson(state.value.session, """{"expenses":[${api.expenseJson(Expense(date, storeName, numeric, entrySource = source))}]}"""); refresh(); done() }
    }
    fun addIncome(date: String, amount: String, done: () -> Unit = {}) = launch {
        val numeric = amount.replace(',', '.').toDoubleOrNull()
        if (date.isBlank() || numeric == null || numeric <= 0) return@launch fail("Enter date and amount")
        run("Could not save income") { api.pushJson(state.value.session, """{"incomes":[${api.incomeJson(Income(date, numeric))}]}"""); refresh(); done() }
    }
    fun addTransfer(date: String, from: String, to: String, amount: String, done: () -> Unit = {}) = launch {
        val numeric = amount.replace(',', '.').toDoubleOrNull()
        if (from.isBlank() || to.isBlank() || numeric == null || numeric <= 0 || from == to) return@launch fail("Choose two different stores and an amount")
        run("Could not save transfer") {
            api.pushJson(state.value.session, """{"expenses":[${api.expenseJson(Expense(date, from, numeric, entrySource = "manual"))}]}""")
            api.pushJson(state.value.session, """{"incomes":[${api.incomeJson(Income(date, numeric))}]}""")
            done()
            refresh()
        }
    }
    fun deleteExpense(expense: Expense) = launch {
        run("Could not delete") {
            val extras = store.extrasNow()
            store.saveExtras(extras.copy(trash = (listOf(expense) + extras.trash).take(200)))
            api.pushJson(state.value.session, """{"expenses":[${api.expenseJson(expense.copy(amount = 0.0))}]}""")
            refresh()
        }
    }
    fun restoreTrash(expense: Expense) = launch {
        run("Could not restore") {
            api.pushJson(state.value.session, """{"expenses":[${api.expenseJson(expense)}]}""")
            store.saveExtras(store.extrasNow().copy(trash = store.extrasNow().trash.filterNot { it.signature == expense.signature && it.date == expense.date && it.store == expense.store }))
            refresh()
        }
    }
    fun emptyTrash() = launch { store.saveExtras(store.extrasNow().copy(trash = emptyList())) }
    fun enqueuePending(item: PendingCapture) = launch {
        val extras = store.extrasNow()
        if (extras.pending.any { it.date == item.date && it.store == item.store && it.amount == item.amount }) return@launch
        store.saveExtras(extras.copy(pending = extras.pending + item))
    }
    fun confirmPending(item: PendingCapture) = launch {
        addExpense(item.date, item.store, item.amount.toString(), "wallet")
        store.saveExtras(store.extrasNow().copy(pending = store.extrasNow().pending.filterNot { it.at == item.at && it.store == item.store }))
    }
    fun updatePendingStore(item: PendingCapture, storeName: String) = launch {
        if (storeName.isBlank()) return@launch
        val extras = store.extrasNow()
        store.saveExtras(extras.copy(pending = extras.pending.map {
            if (it.at == item.at && it.store == item.store) it.copy(store = storeName) else it
        }))
    }
    fun dismissPending(item: PendingCapture) = launch {
        store.saveExtras(store.extrasNow().copy(pending = store.extrasNow().pending.filterNot { it.at == item.at && it.store == item.store }))
    }
    fun saveStore(name: String, category: String, photo: ByteArray? = null) = launch {
        if (name.isBlank()) return@launch fail("Store name required")
        run("Could not save store") {
            val result = api.saveStore(
                session = state.value.session,
                id = System.currentTimeMillis().toString(),
                name = name.replace("\"", ""),
                category = category.ifBlank { "other" },
                photo = photo,
            )
            requireOk(result)
            refresh()
        }
    }
    fun saveSettings(budget: String, currency: String, showIncomes: Boolean, showTrend: Boolean, trimEmpty: Boolean) = launch {
        val b = budget.replace(',', '.').toDoubleOrNull() ?: return@launch fail("Budget must be a number")
        run("Could not save settings") {
            api.pushJson(state.value.session, """{"settings":{"monthly_budget":$b,"currency":"$currency","show_incomes":$showIncomes,"show_trend":$showTrend,"trim_empty_months":$trimEmpty}}""")
            refresh()
        }
    }
    fun saveTag(name: String, color: String, id: String = "") = launch {
        run("Could not save tag") { requireOk(api.form(state.value.session, mapOf("save_tag" to "1", "id" to id, "name" to name, "color" to color))); refresh() }
    }
    fun deleteTag(id: String) = launch { run("Could not delete tag") { requireOk(api.form(state.value.session, mapOf("delete_tag" to id))); refresh() } }
    /** Replaces the tags of one payment; tags are attached per date + store like the web UI. */
    fun saveExpenseTags(expense: Expense, tagIds: List<String>) = launch {
        run("Could not tag payment") { api.setExpenseTags(state.value.session, expense.date, expense.store, tagIds); refresh() }
    }
    fun toggleExpenseTag(expense: Expense, tagId: String) = launch {
        val current = state.value.snapshot.expenseTags["${expense.date}\t${expense.store}"].orEmpty()
        val next = if (tagId in current) current - tagId else current + tagId
        run("Could not tag payment") { api.setExpenseTags(state.value.session, expense.date, expense.store, next); refresh() }
    }
    fun saveGoal(goal: Goal) = launch {
        run("Could not save goal") {
            requireOk(api.form(state.value.session, mapOf(
                "save_goal" to "1", "id" to goal.id, "name" to goal.name, "description" to goal.description,
                "target_amount" to goal.targetAmount.toString(), "current_amount" to goal.currentAmount.toString(),
                "currency" to goal.currency, "target_date" to goal.targetDate, "goal_type" to goal.goalType,
                "is_archived" to if (goal.isArchived) "1" else "0",
            )))
            refresh()
        }
    }
    fun contributeGoal(id: String, amount: String) = launch {
        run("Could not contribute") { requireOk(api.form(state.value.session, mapOf("contribute_goal" to id, "amount" to amount))); refresh() }
    }
    fun deleteGoal(id: String) = launch { run("Could not delete goal") { requireOk(api.form(state.value.session, mapOf("delete_goal" to id))); refresh() } }
    fun addSubscription(storeName: String, amount: String, day: String) = launch {
        run("Could not add subscription") {
            requireOk(api.form(state.value.session, mapOf("add_subscription" to "1", "store" to storeName, "amount" to amount, "frequency" to "monthly", "day_of_month" to day, "start_date" to LocalDate.now().toString())))
            refresh()
        }
    }
    fun deleteSubscription(id: String) = launch { run("Could not delete") { requireOk(api.form(state.value.session, mapOf("delete_subscription" to id))); refresh() } }
    /** Saves a backup both on the device and on the paired server. */
    fun saveBackup(name: String) = launch {
        if (name.isBlank()) return@launch fail("Backup name required")
        run("Could not backup") {
            val payload = json.encodeToString(
                LocalBackupPayload.serializer(),
                LocalBackupPayload(state.value.snapshot, state.value.extras),
            )
            store.writeLocalBackup(name, payload)
            localBackups.value = store.localBackups()
            runCatching {
                requireOk(api.form(state.value.session, mapOf("backup_action" to "save", "backup_name" to name)))
                backups.value = api.backups(state.value.session)
            }
        }
    }
    fun restoreBackup(file: String) = launch {
        run("Could not restore") {
            val local = store.readLocalBackup(file)
            if (local != null) {
                val payload = json.decodeFromString(LocalBackupPayload.serializer(), local)
                store.saveSnapshot(payload.snapshot)
                store.saveExtras(payload.extras)
            }
            runCatching { requireOk(api.form(state.value.session, mapOf("backup_action" to "restore", "backup_file" to file))) }
            refresh()
        }
    }
    fun deleteBackup(file: String) = launch {
        run("Could not delete backup") {
            store.deleteLocalBackup(file)
            localBackups.value = store.localBackups()
            runCatching {
                requireOk(api.form(state.value.session, mapOf("backup_action" to "delete", "backup_file" to file)))
                backups.value = api.backups(state.value.session)
            }
        }
    }
    fun testConnection(done: (Boolean) -> Unit) = launch {
        val session = state.value.session
        runCatching { api.profile(session.endpoint, session.token); done(true) }.onFailure { done(false) }
    }
    fun logout() = launch { store.clear() }
    fun dismissError() { error.value = null }
    private fun requireOk(obj: kotlinx.serialization.json.JsonObject) {
        val status = obj["status"]?.toString()?.trim('"') ?: ""
        if (status != "success" && status != "ok") error(obj["message"]?.toString()?.trim('"') ?: "Request failed")
    }
    private fun launch(block: suspend () -> Unit) = viewModelScope.launch { block() }
    private suspend fun run(fallback: String, quiet: Boolean = false, block: suspend () -> Unit) {
        if (!quiet) loading.value = true
        error.value = null
        runCatching { block() }.onFailure { error.value = it.message?.takeIf(String::isNotBlank) ?: fallback }
        if (!quiet) loading.value = false
    }
    private fun fail(message: String) { error.value = message }
    fun parsePairing(value: String, suppliedToken: String): Pair<String, String>? {
        val source = value.trim()
        val query = source.substringAfter('?', "")
        val params = query.split('&').mapNotNull { part -> part.split('=', limit = 2).takeIf { it.size == 2 }?.let { it[0] to java.net.URLDecoder.decode(it[1], "UTF-8") } }.toMap()
        val endpoint = params["endpoint"] ?: source.takeIf { !it.startsWith("spends:") }
        val token = params["token"] ?: suppliedToken.trim()
        return endpoint?.takeIf(String::isNotBlank)?.let { it to token }.takeIf { !it?.second.isNullOrBlank() }
    }
}
