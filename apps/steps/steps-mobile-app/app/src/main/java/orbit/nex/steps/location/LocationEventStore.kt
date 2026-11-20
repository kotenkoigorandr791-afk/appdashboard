package orbit.nex.steps.location

import android.content.ContentValues
import android.content.Context
import android.database.Cursor
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper
import java.time.LocalDate
import java.time.ZoneId
import kotlin.math.min

/** A stored measurement and the state it currently holds. */
data class StoredLocationEvent(
    val id: Long,
    val eventId: String,
    val payload: String,
    val createdAtMs: Long,
    val deviceSequence: Long,
    val attempts: Int,
    val state: String = LocationEventStore.STATE_PENDING,
) {
    /** True while the measurement has not been confirmed by the server. */
    val isPending: Boolean get() = state == LocationEventStore.STATE_PENDING ||
        state == LocationEventStore.STATE_UPLOADING ||
        state == LocationEventStore.STATE_FAILED
}

/**
 * Durable local outbox for location measurements.
 *
 * Every row walks through explicit states:
 * `pending -> uploading -> accepted | duplicate | rejected | failed`, where a
 * failed transport attempt returns it to `pending` with a bounded backoff. The
 * `uploading` state is leased with a deadline, so a row left behind by a killed
 * process is reclaimed on the next claim instead of being stuck forever.
 *
 * `event_id` is UNIQUE: re-inserting a measurement is harmless, and server
 * acknowledgements are reconciled per row, so a retry after process death
 * cannot store the same point twice.
 */
class LocationEventStore(context: Context) {

    private val helper = sharedHelper(context)

    fun insert(deviceId: String, event: LocationEvent): Boolean {
        val values = ContentValues().apply {
            put(COLUMN_EVENT_ID, event.clientEventId)
            put(COLUMN_PAYLOAD, event.toServerJson(deviceId))
            put(COLUMN_CREATED_AT, event.recordedAtMs)
            put(COLUMN_DEVICE_SEQUENCE, event.deviceSequence)
            put(COLUMN_STATE, STATE_PENDING)
            put(COLUMN_ATTEMPTS, 0)
            put(COLUMN_NEXT_ATTEMPT_AT, 0)
        }
        val inserted = helper.writableDatabase.insertWithOnConflict(
            TABLE,
            null,
            values,
            SQLiteDatabase.CONFLICT_IGNORE,
        ) != -1L
        if (inserted) trim()
        return inserted
    }

    /**
     * Atomically leases up to [limit] due rows for upload.
     *
     * Expired leases are reclaimed inside the same transaction, so a batch
     * interrupted by process death is uploaded again by the next worker run.
     */
    fun claim(limit: Int = DEFAULT_BATCH, leaseMs: Long = LEASE_MS): List<StoredLocationEvent> {
        val db = helper.writableDatabase
        val now = System.currentTimeMillis()
        val claimed = mutableListOf<StoredLocationEvent>()
        db.beginTransaction()
        try {
            db.execSQL(
                "UPDATE $TABLE SET $COLUMN_STATE = '$STATE_PENDING' " +
                    "WHERE $COLUMN_STATE = '$STATE_UPLOADING' AND $COLUMN_LEASE_UNTIL <= ?",
                arrayOf(now),
            )
            val ids = mutableListOf<Long>()
            db.rawQuery(
                "SELECT $COLUMN_ID FROM $TABLE WHERE $COLUMN_STATE = '$STATE_PENDING' " +
                    "AND $COLUMN_NEXT_ATTEMPT_AT <= ? ORDER BY $COLUMN_DEVICE_SEQUENCE ASC, $COLUMN_ID ASC LIMIT ?",
                arrayOf(now.toString(), limit.toString()),
            ).use { cursor ->
                while (cursor.moveToNext()) ids += cursor.getLong(0)
            }
            if (ids.isEmpty()) {
                db.setTransactionSuccessful()
                return emptyList()
            }
            val leaseUntil = now + leaseMs
            db.execSQL(
                "UPDATE $TABLE SET $COLUMN_STATE = '$STATE_UPLOADING', $COLUMN_LEASE_UNTIL = ? " +
                    "WHERE $COLUMN_ID IN (${ids.joinToString(",")})",
                arrayOf(leaseUntil),
            )
            readByIds(db, ids).forEach { claimed += it }
            db.setTransactionSuccessful()
        } finally {
            db.endTransaction()
        }
        return claimed
    }

    private fun readByIds(db: SQLiteDatabase, ids: List<Long>): List<StoredLocationEvent> {
        val out = mutableListOf<StoredLocationEvent>()
        db.rawQuery(
            "SELECT $COLUMN_ID, $COLUMN_EVENT_ID, $COLUMN_PAYLOAD, $COLUMN_CREATED_AT, " +
                "$COLUMN_DEVICE_SEQUENCE, $COLUMN_ATTEMPTS, $COLUMN_STATE FROM $TABLE " +
                "WHERE $COLUMN_ID IN (${ids.joinToString(",")}) " +
                "ORDER BY $COLUMN_DEVICE_SEQUENCE ASC, $COLUMN_ID ASC",
            null,
        ).use { cursor -> while (cursor.moveToNext()) out += cursor.toEvent() }
        return out
    }

    /** Terminal state per row, reconciled by client event id. */
    fun settle(eventId: String, state: String, error: String? = null) {
        require(state in SETTLED_STATES) { "not a terminal state: $state" }
        val values = ContentValues().apply {
            put(COLUMN_STATE, state)
            put(COLUMN_SETTLED_AT, System.currentTimeMillis())
            put(COLUMN_LEASE_UNTIL, 0)
            put(COLUMN_NEXT_ATTEMPT_AT, 0)
            if (error != null) put(COLUMN_LAST_ERROR, error.take(240))
        }
        helper.writableDatabase.update(TABLE, values, "$COLUMN_EVENT_ID = ?", arrayOf(eventId))
    }

    /**
     * Returns rows to `pending` with a bounded exponential delay. Used for
     * transport failures, expired server batches and unknown result statuses.
     */
    fun retryLater(ids: List<Long>, error: String?) {
        if (ids.isEmpty()) return
        val db = helper.writableDatabase
        val now = System.currentTimeMillis()
        db.beginTransaction()
        try {
            ids.forEach { id ->
                val attempts = readAttempts(db, id) + 1
                val values = ContentValues().apply {
                    put(COLUMN_STATE, STATE_PENDING)
                    put(COLUMN_ATTEMPTS, attempts)
                    put(COLUMN_NEXT_ATTEMPT_AT, now + backoffMs(attempts))
                    put(COLUMN_LEASE_UNTIL, 0)
                    if (error != null) put(COLUMN_LAST_ERROR, error.take(240))
                }
                db.update(TABLE, values, "$COLUMN_ID = ?", arrayOf(id.toString()))
            }
            db.setTransactionSuccessful()
        } finally {
            db.endTransaction()
        }
    }

    /** Gives a lease back without counting an attempt (e.g. malformed response). */
    fun release(ids: List<Long>) {
        if (ids.isEmpty()) return
        helper.writableDatabase.execSQL(
            "UPDATE $TABLE SET $COLUMN_STATE = '$STATE_PENDING', $COLUMN_LEASE_UNTIL = 0 " +
                "WHERE $COLUMN_ID IN (${ids.joinToString(",")})",
        )
    }

    /** Rows not yet settled: pending plus currently uploading. */
    fun pendingCount(): Int = countWhere(
        "$COLUMN_STATE IN ('$STATE_PENDING','$STATE_UPLOADING')",
    )

    /** Local diagnostics; never contains coordinates. */
    fun counts(): Map<String, Int> {
        val out = mutableMapOf<String, Int>()
        helper.readableDatabase.rawQuery(
            "SELECT $COLUMN_STATE, COUNT(*) FROM $TABLE GROUP BY $COLUMN_STATE",
            null,
        ).use { cursor ->
            while (cursor.moveToNext()) out[cursor.getString(0)] = cursor.getInt(1)
        }
        return out
    }

    fun eventsForDay(day: LocalDate): List<StoredLocationEvent> {
        val zone = ZoneId.systemDefault()
        val from = day.atStartOfDay(zone).toInstant().toEpochMilli()
        val to = day.plusDays(1).atStartOfDay(zone).toInstant().toEpochMilli()
        val out = mutableListOf<StoredLocationEvent>()
        helper.readableDatabase.rawQuery(
            "SELECT $COLUMN_ID, $COLUMN_EVENT_ID, $COLUMN_PAYLOAD, $COLUMN_CREATED_AT, $COLUMN_DEVICE_SEQUENCE, $COLUMN_ATTEMPTS, $COLUMN_STATE FROM $TABLE WHERE $COLUMN_CREATED_AT >= ? AND $COLUMN_CREATED_AT < ? ORDER BY $COLUMN_DEVICE_SEQUENCE ASC, $COLUMN_ID ASC",
            arrayOf(from.toString(), to.toString()),
        ).use { cursor -> while (cursor.moveToNext()) out += cursor.toEvent() }
        return out
    }

    fun trim() {
        val db = helper.writableDatabase
        db.delete(
            TABLE,
            "$COLUMN_STATE IN ($SETTLED_SQL) AND $COLUMN_CREATED_AT < ?",
            arrayOf((System.currentTimeMillis() - RETENTION_MS).toString()),
        )
        db.execSQL(
            "DELETE FROM $TABLE WHERE $COLUMN_ID IN (" +
                "SELECT $COLUMN_ID FROM $TABLE WHERE $COLUMN_STATE IN ($SETTLED_SQL) " +
                "ORDER BY $COLUMN_ID DESC LIMIT -1 OFFSET $MAX_SETTLED_ROWS)",
        )
    }

    /** Drops every queued and settled row; used when the owner resets diagnostics. */
    fun clearAll() {
        helper.writableDatabase.delete(TABLE, null, null)
    }

    private fun readAttempts(db: SQLiteDatabase, id: Long): Int =
        db.rawQuery("SELECT $COLUMN_ATTEMPTS FROM $TABLE WHERE $COLUMN_ID = ?", arrayOf(id.toString()))
            .use { cursor -> if (cursor.moveToFirst()) cursor.getInt(0) else 0 }

    private fun countWhere(where: String): Int =
        helper.readableDatabase.rawQuery("SELECT COUNT(*) FROM $TABLE WHERE $where", null)
            .use { cursor -> if (cursor.moveToFirst()) cursor.getInt(0) else 0 }

    private fun Cursor.toEvent() = StoredLocationEvent(
        id = getLong(0),
        eventId = getString(1),
        payload = getString(2),
        createdAtMs = getLong(3),
        deviceSequence = getLong(4),
        attempts = getInt(5),
        state = getString(6) ?: LocationEventStore.STATE_PENDING,
    )

    private class Helper(context: Context) : SQLiteOpenHelper(context, DB_NAME, null, DB_VERSION) {
        override fun onCreate(db: SQLiteDatabase) {
            db.execSQL(
                """
                CREATE TABLE $TABLE (
                    $COLUMN_ID INTEGER PRIMARY KEY AUTOINCREMENT,
                    $COLUMN_EVENT_ID TEXT NOT NULL UNIQUE,
                    $COLUMN_PAYLOAD TEXT NOT NULL,
                    $COLUMN_CREATED_AT INTEGER NOT NULL,
                    $COLUMN_DEVICE_SEQUENCE INTEGER NOT NULL DEFAULT 0,
                    $COLUMN_STATE TEXT NOT NULL DEFAULT '$STATE_PENDING',
                    $COLUMN_ATTEMPTS INTEGER NOT NULL DEFAULT 0,
                    $COLUMN_NEXT_ATTEMPT_AT INTEGER NOT NULL DEFAULT 0,
                    $COLUMN_LEASE_UNTIL INTEGER NOT NULL DEFAULT 0,
                    $COLUMN_LAST_ERROR TEXT,
                    $COLUMN_SETTLED_AT INTEGER
                )
                """.trimIndent(),
            )
            db.execSQL(
                "CREATE INDEX ${TABLE}_due ON $TABLE($COLUMN_STATE, $COLUMN_NEXT_ATTEMPT_AT, $COLUMN_DEVICE_SEQUENCE)",
            )
        }

        override fun onUpgrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) {
            if (oldVersion < 2) {
                // Version 1 stored only a boolean "uploaded" flag.
                db.execSQL("ALTER TABLE $TABLE ADD COLUMN $COLUMN_DEVICE_SEQUENCE INTEGER NOT NULL DEFAULT 0")
                db.execSQL("ALTER TABLE $TABLE ADD COLUMN $COLUMN_STATE TEXT NOT NULL DEFAULT '$STATE_PENDING'")
                db.execSQL("ALTER TABLE $TABLE ADD COLUMN $COLUMN_NEXT_ATTEMPT_AT INTEGER NOT NULL DEFAULT 0")
                db.execSQL("ALTER TABLE $TABLE ADD COLUMN $COLUMN_LEASE_UNTIL INTEGER NOT NULL DEFAULT 0")
                db.execSQL("ALTER TABLE $TABLE ADD COLUMN $COLUMN_LAST_ERROR TEXT")
                db.execSQL("ALTER TABLE $TABLE ADD COLUMN $COLUMN_SETTLED_AT INTEGER")
                db.execSQL("UPDATE $TABLE SET $COLUMN_STATE = '$STATE_ACCEPTED', $COLUMN_SETTLED_AT = $COLUMN_CREATED_AT WHERE $COLUMN_UPLOADED = 1")
                db.execSQL("UPDATE $TABLE SET $COLUMN_STATE = '$STATE_PENDING' WHERE $COLUMN_UPLOADED = 0")
                db.execSQL(
                    "CREATE INDEX ${TABLE}_due ON $TABLE($COLUMN_STATE, $COLUMN_NEXT_ATTEMPT_AT, $COLUMN_DEVICE_SEQUENCE)",
                )
            }
        }
    }

    companion object {
        @Volatile private var shared: Helper? = null
        private fun sharedHelper(context: Context): Helper = shared ?: synchronized(this) {
            shared ?: Helper(context.applicationContext).also { shared = it }
        }
        const val DEFAULT_BATCH = 50
        private const val MAX_SETTLED_ROWS = 5_000
        private const val RETENTION_DAYS = 7L
        private const val RETENTION_MS = RETENTION_DAYS * 24 * 60 * 60 * 1000
        const val LEASE_MS = 2L * 60 * 1000

        const val STATE_PENDING = "pending"
        const val STATE_UPLOADING = "uploading"
        const val STATE_ACCEPTED = "accepted"
        const val STATE_DUPLICATE = "duplicate"
        const val STATE_REJECTED = "rejected"
        const val STATE_FAILED = "failed"

        val SETTLED_STATES = setOf(STATE_ACCEPTED, STATE_DUPLICATE, STATE_REJECTED, STATE_FAILED)
        private val SETTLED_SQL = listOf(STATE_ACCEPTED, STATE_DUPLICATE).joinToString(",") { "'$it'" }

        private const val DB_NAME = "location_events.db"
        private const val DB_VERSION = 2
        private const val TABLE = "location_events"
        private const val COLUMN_ID = "id"
        private const val COLUMN_EVENT_ID = "event_id"
        private const val COLUMN_PAYLOAD = "payload"
        private const val COLUMN_CREATED_AT = "created_at"
        private const val COLUMN_DEVICE_SEQUENCE = "device_sequence"
        private const val COLUMN_STATE = "state"
        private const val COLUMN_ATTEMPTS = "attempts"
        private const val COLUMN_NEXT_ATTEMPT_AT = "next_attempt_at"
        private const val COLUMN_LEASE_UNTIL = "lease_until"
        private const val COLUMN_LAST_ERROR = "last_error"
        private const val COLUMN_SETTLED_AT = "settled_at"
        private const val COLUMN_UPLOADED = "uploaded"

        /** 15 s, doubling, capped at 30 minutes. */
        fun backoffMs(attempts: Int): Long {
            val exponent = (attempts - 1).coerceIn(0, 10)
            return min(MAX_BACKOFF_MS, BASE_BACKOFF_MS * (1L shl exponent))
        }

        private const val BASE_BACKOFF_MS = 15_000L
        private const val MAX_BACKOFF_MS = 30L * 60 * 1000
    }
}
