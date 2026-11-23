package orbit.nex.finance.data

import android.content.Context
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.Json
import orbit.nex.finance.data.local.LedgerDao
import orbit.nex.finance.data.local.toModel

private val Context.spendsDataStore by preferencesDataStore("spends_native")

class SessionStore(private val context: Context, private val ledger: LedgerDao) {
    private val json = Json { ignoreUnknownKeys = true }
    @Volatile private var snapshotWire: String? = null
    @Volatile private var snapshotCache: Snapshot? = null
    /** Serialises read-modify-write cycles on extras (ViewModel + notification service). */
    private val extrasMutex = Mutex()
    private val sessionKey = stringPreferencesKey("session")
    private val snapshotKey = stringPreferencesKey("snapshot")
    private val extrasKey = stringPreferencesKey("extras")
    private val appearanceKey = stringPreferencesKey("appearance")
    private val logsKey = stringPreferencesKey("sync_logs")
    private val secrets by lazy {
        EncryptedSharedPreferences.create(context, "spends_secrets", MasterKey.Builder(context).setKeyScheme(MasterKey.KeyScheme.AES256_GCM).build(), EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV, EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM)
    }
    private val deviceIdKey = "device_id"
    private val tokenKey = "token"
    val session: Flow<Session> = context.spendsDataStore.data.map {
        val value = decode(it, sessionKey, Session.serializer(), Session())
        value.copy(token = secrets.getString(tokenKey, value.token).orEmpty())
    }
    private val snapshotMeta: Flow<Snapshot> = context.spendsDataStore.data.map { prefs ->
        decode(prefs, snapshotKey, Snapshot.serializer(), Snapshot())
    }
    val snapshot: Flow<Snapshot> = SnapshotStore.observe(ledger, snapshotMeta)
    fun recentExpenses(limit: Int = 400): Flow<List<Expense>> =
        ledger.recentExpenses(limit).map { rows -> rows.map { it.toModel() } }
    val extras: Flow<LocalExtras> = context.spendsDataStore.data.map { decode(it, extrasKey, LocalExtras.serializer(), LocalExtras()) }
    val appearance: Flow<Appearance> = context.spendsDataStore.data.map { decode(it, appearanceKey, Appearance.serializer(), Appearance()) }
    val syncLogs: Flow<List<SyncLog>> = context.spendsDataStore.data.map { decodeList(it, logsKey) }
    suspend fun saveSession(value: Session) {
        secrets.edit().putString(tokenKey, value.token).commit()
        save(sessionKey, Session.serializer(), value.copy(token = ""))
    }
    fun deviceId(): String = secrets.getString(deviceIdKey, null) ?: java.util.UUID.randomUUID().toString().replace("-", "").also { secrets.edit().putString(deviceIdKey, it).apply() }

    /** Token for HTTP headers without suspending; the encrypted store is in memory after the first read. */
    fun tokenNow(): String = runCatching { secrets.getString(tokenKey, "").orEmpty() }.getOrDefault("")
    suspend fun saveSnapshot(value: Snapshot) {
        snapshotWire = null
        snapshotCache = value
        withContext(Dispatchers.IO) { SnapshotStore.replace(ledger, value) }
        save(snapshotKey, Snapshot.serializer(), SnapshotStore.slim(value))
    }

    /** One-time move from legacy JSON blob that included the full ledger. */
    suspend fun migrateLegacySnapshotIfNeeded() {
        if (ledger.expenseCount() > 0) return
        val prefs = context.spendsDataStore.data.first()
        val wire = prefs[snapshotKey].orEmpty()
        if (wire.isBlank()) return
        val full = runCatching { json.decodeFromString(Snapshot.serializer(), wire) }.getOrNull() ?: return
        if (full.expenses.isEmpty() && full.incomes.isEmpty() && full.stores.isEmpty()) return
        withContext(Dispatchers.IO) { SnapshotStore.replace(ledger, full) }
        save(snapshotKey, Snapshot.serializer(), SnapshotStore.slim(full))
    }
    suspend fun saveExtras(value: LocalExtras) {
        extrasMutex.lock()
        try {
            save(extrasKey, LocalExtras.serializer(), value)
        } finally {
            extrasMutex.unlock()
        }
    }
    suspend fun saveAppearance(value: Appearance) = save(appearanceKey, Appearance.serializer(), value)

    /** Atomically transforms extras so concurrent writers cannot overwrite each other. */
    suspend fun updateExtras(transform: (LocalExtras) -> LocalExtras): LocalExtras {
        extrasMutex.lock()
        try {
            val current = decode(context.spendsDataStore.data.first(), extrasKey, LocalExtras.serializer(), LocalExtras())
            val next = transform(current)
            save(extrasKey, LocalExtras.serializer(), next)
            return next
        } finally {
            extrasMutex.unlock()
        }
    }

    /** Adds durable writes to the outbox in the order they were captured. */
    suspend fun enqueueOutbox(items: List<OutboxItem>): LocalExtras = updateExtras { extras ->
        if (items.isEmpty()) return@updateExtras extras
        // Capture ids are stable, so a reposted notification cannot queue the
        // same payment twice while the first copy is still waiting to upload.
        val known = extras.outbox.mapTo(mutableSetOf()) { it.id }
        extras.copy(outbox = extras.outbox + items.filter { known.add(it.id) })
    }

    suspend fun removeOutbox(ids: Set<String>): LocalExtras = updateExtras { extras ->
        if (ids.isEmpty()) extras else extras.copy(outbox = extras.outbox.filterNot { it.id in ids })
    }

    /** Synchronous mirror of the chosen language, read from attachBaseContext. */
    fun cacheLanguage(language: String) = orbit.nex.finance.AppLocale.store(context, language)
    suspend fun addLog(log: SyncLog) {
        val next = (listOf(log) + decodeList(context.spendsDataStore.data.first(), logsKey)).take(200)
        context.spendsDataStore.edit { it[logsKey] = json.encodeToString(kotlinx.serialization.builtins.ListSerializer(SyncLog.serializer()), next) }
    }
    suspend fun clearLogs() { context.spendsDataStore.edit { it.remove(logsKey) } }
    private fun decodeList(prefs: Preferences, key: Preferences.Key<String>): List<SyncLog> =
        runCatching { json.decodeFromString(kotlinx.serialization.builtins.ListSerializer(SyncLog.serializer()), prefs[key].orEmpty()) }.getOrDefault(emptyList())
    suspend fun extrasNow() = extras.first()

    // ── Local backups ────────────────────────────────────────────────────────
    private val backupDir = java.io.File(context.filesDir, "backups").apply { if (!exists()) mkdirs() }

    fun localBackups(): List<BackupFile> = backupDir.listFiles()
        ?.filter { it.isFile }
        ?.sortedByDescending { it.lastModified() }
        ?.map { BackupFile(it.name.removeSuffix(".json"), it.length(), it.lastModified()) }
        .orEmpty()

    fun writeLocalBackup(name: String, payload: String): Boolean = runCatching {
        backupDir.mkdirs()
        java.io.File(backupDir, "${name.replace(Regex("[^A-Za-z0-9._-]"), "_")}.json").writeText(payload)
        true
    }.getOrDefault(false)

    fun readLocalBackup(name: String): String? = runCatching {
        java.io.File(backupDir, "${name.removeSuffix(".json")}.json").takeIf { it.isFile }?.readText()
    }.getOrNull()

    fun deleteLocalBackup(name: String) {
        runCatching { java.io.File(backupDir, "${name.removeSuffix(".json")}.json").delete() }
    }

    suspend fun clear() {
        secrets.edit().remove(tokenKey).apply()
        context.spendsDataStore.edit { it.remove(sessionKey); it.remove(snapshotKey); it.remove(extrasKey) }
        withContext(Dispatchers.IO) {
            ledger.clearExpenses()
            ledger.clearIncomes()
            ledger.clearStores()
        }
    }
    private fun <T> decode(prefs: Preferences, key: Preferences.Key<String>, serializer: kotlinx.serialization.KSerializer<T>, fallback: T): T =
        runCatching { json.decodeFromString(serializer, prefs[key].orEmpty()) }.getOrDefault(fallback)
    private suspend fun <T> save(key: Preferences.Key<String>, serializer: kotlinx.serialization.KSerializer<T>, value: T) {
        context.spendsDataStore.edit { it[key] = json.encodeToString(serializer, value) }
    }
}
