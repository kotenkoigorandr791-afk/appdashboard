package xyz.serverkherson.spends.data

import android.content.Context
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import kotlinx.serialization.json.Json

private val Context.spendsDataStore by preferencesDataStore("spends_native")

class SessionStore(private val context: Context) {
    private val json = Json { ignoreUnknownKeys = true }
    private val sessionKey = stringPreferencesKey("session")
    private val snapshotKey = stringPreferencesKey("snapshot")
    private val extrasKey = stringPreferencesKey("extras")
    private val appearanceKey = stringPreferencesKey("appearance")
    private val logsKey = stringPreferencesKey("sync_logs")
    val session: Flow<Session> = context.spendsDataStore.data.map { decode(it, sessionKey, Session.serializer(), Session()) }
    val snapshot: Flow<Snapshot> = context.spendsDataStore.data.map { decode(it, snapshotKey, Snapshot.serializer(), Snapshot()) }
    val extras: Flow<LocalExtras> = context.spendsDataStore.data.map { decode(it, extrasKey, LocalExtras.serializer(), LocalExtras()) }
    val appearance: Flow<Appearance> = context.spendsDataStore.data.map { decode(it, appearanceKey, Appearance.serializer(), Appearance()) }
    val syncLogs: Flow<List<SyncLog>> = context.spendsDataStore.data.map { decodeList(it, logsKey) }
    suspend fun saveSession(value: Session) = save(sessionKey, Session.serializer(), value)
    suspend fun saveSnapshot(value: Snapshot) = save(snapshotKey, Snapshot.serializer(), value)
    suspend fun saveExtras(value: LocalExtras) = save(extrasKey, LocalExtras.serializer(), value)
    suspend fun saveAppearance(value: Appearance) = save(appearanceKey, Appearance.serializer(), value)
    suspend fun addLog(log: SyncLog) {
        val next = (listOf(log) + decodeList(context.spendsDataStore.data.first(), logsKey)).take(200)
        context.spendsDataStore.edit { it[logsKey] = json.encodeToString(kotlinx.serialization.builtins.ListSerializer(SyncLog.serializer()), next) }
    }
    suspend fun clearLogs() { context.spendsDataStore.edit { it.remove(logsKey) } }
    private fun decodeList(prefs: Preferences, key: Preferences.Key<String>): List<SyncLog> =
        runCatching { json.decodeFromString(kotlinx.serialization.builtins.ListSerializer(SyncLog.serializer()), prefs[key].orEmpty()) }.getOrDefault(emptyList())
    suspend fun extrasNow() = extras.first()
    suspend fun clear() { context.spendsDataStore.edit { it.remove(sessionKey); it.remove(snapshotKey); it.remove(extrasKey) } }
    private fun <T> decode(prefs: Preferences, key: Preferences.Key<String>, serializer: kotlinx.serialization.KSerializer<T>, fallback: T): T =
        runCatching { json.decodeFromString(serializer, prefs[key].orEmpty()) }.getOrDefault(fallback)
    private suspend fun <T> save(key: Preferences.Key<String>, serializer: kotlinx.serialization.KSerializer<T>, value: T) {
        context.spendsDataStore.edit { it[key] = json.encodeToString(serializer, value) }
    }
}
