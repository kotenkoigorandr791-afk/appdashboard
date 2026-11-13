package orbit.nex.steps.location

import android.content.ContentValues
import android.content.Context
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper

/** A stored measurement waiting for (or already through) upload. */
data class StoredLocationEvent(
    val id: Long,
    val eventId: String,
    val payload: String,
    val createdAtMs: Long,
    val attempts: Int,
)

/**
 * Durable local outbox for location measurements.
 *
 * Measurements are written here before the uploader is allowed to see them, so
 * a crash, a force-stop or a lost connection cannot drop a point that the OS
 * already delivered. `event_id` is UNIQUE, which also makes re-inserting a
 * measurement harmless.
 */
class LocationEventStore(context: Context) {

    private val helper = Helper(context.applicationContext)

    /** @return true when this event id was not stored before. */
    fun insert(deviceId: String, event: LocationEvent): Boolean {
        val values = ContentValues().apply {
            put(COLUMN_EVENT_ID, event.clientEventId)
            put(COLUMN_PAYLOAD, event.toServerJson(deviceId))
            put(COLUMN_CREATED_AT, event.recordedAtMs)
            put(COLUMN_UPLOADED, 0)
            put(COLUMN_ATTEMPTS, 0)
        }
        return helper.writableDatabase.insertWithOnConflict(
            TABLE,
            null,
            values,
            SQLiteDatabase.CONFLICT_IGNORE,
        ) != -1L
    }

    /** Oldest pending measurements first, so the server sees them in order. */
    fun pending(limit: Int = DEFAULT_BATCH): List<StoredLocationEvent> {
        val out = mutableListOf<StoredLocationEvent>()
        helper.readableDatabase.query(
            TABLE,
            arrayOf(COLUMN_ID, COLUMN_EVENT_ID, COLUMN_PAYLOAD, COLUMN_CREATED_AT, COLUMN_ATTEMPTS),
            "$COLUMN_UPLOADED = 0",
            null,
            null,
            null,
            "$COLUMN_ID ASC",
            limit.toString(),
        ).use { cursor ->
            while (cursor.moveToNext()) {
                out += StoredLocationEvent(
                    id = cursor.getLong(0),
                    eventId = cursor.getString(1),
                    payload = cursor.getString(2),
                    createdAtMs = cursor.getLong(3),
                    attempts = cursor.getInt(4),
                )
            }
        }
        return out
    }

    /** Marks measurements the server accepted (or explicitly rejected) as done. */
    fun markUploaded(ids: List<Long>) {
        if (ids.isEmpty()) return
        val db = helper.writableDatabase
        db.beginTransaction()
        try {
            ids.forEach { id ->
                val values = ContentValues().apply { put(COLUMN_UPLOADED, 1) }
                db.update(TABLE, values, "$COLUMN_ID = ?", arrayOf(id.toString()))
            }
            db.setTransactionSuccessful()
        } finally {
            db.endTransaction()
        }
    }

    /** Records a failed network attempt without losing the measurements. */
    fun markAttempt(ids: List<Long>) {
        if (ids.isEmpty()) return
        helper.writableDatabase.execSQL(
            "UPDATE $TABLE SET $COLUMN_ATTEMPTS = $COLUMN_ATTEMPTS + 1 WHERE $COLUMN_ID IN (${ids.joinToString(",")})",
        )
    }

    fun pendingCount(): Int = helper.readableDatabase.rawQuery(
        "SELECT COUNT(*) FROM $TABLE WHERE $COLUMN_UPLOADED = 0",
        null,
    ).use { cursor -> if (cursor.moveToFirst()) cursor.getInt(0) else 0 }

    /** Drops uploaded rows older than [olderThanMs] so the outbox stays bounded. */
    fun purgeUploaded(olderThanMs: Long = SEVEN_DAYS_MS) {
        helper.writableDatabase.delete(
            TABLE,
            "$COLUMN_UPLOADED = 1 AND $COLUMN_CREATED_AT < ?",
            arrayOf((System.currentTimeMillis() - olderThanMs).toString()),
        )
    }

    private class Helper(context: Context) : SQLiteOpenHelper(context, DB_NAME, null, DB_VERSION) {
        override fun onCreate(db: SQLiteDatabase) {
            db.execSQL(
                """
                CREATE TABLE $TABLE (
                    $COLUMN_ID INTEGER PRIMARY KEY AUTOINCREMENT,
                    $COLUMN_EVENT_ID TEXT NOT NULL UNIQUE,
                    $COLUMN_PAYLOAD TEXT NOT NULL,
                    $COLUMN_CREATED_AT INTEGER NOT NULL,
                    $COLUMN_UPLOADED INTEGER NOT NULL DEFAULT 0,
                    $COLUMN_ATTEMPTS INTEGER NOT NULL DEFAULT 0
                )
                """.trimIndent(),
            )
            db.execSQL("CREATE INDEX ${TABLE}_pending ON $TABLE($COLUMN_UPLOADED, $COLUMN_ID)")
        }

        override fun onUpgrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) = Unit
    }

    companion object {
        const val DEFAULT_BATCH = 200
        private const val DB_NAME = "location_events.db"
        private const val DB_VERSION = 1
        private const val TABLE = "location_events"
        private const val COLUMN_ID = "id"
        private const val COLUMN_EVENT_ID = "event_id"
        private const val COLUMN_PAYLOAD = "payload"
        private const val COLUMN_CREATED_AT = "created_at"
        private const val COLUMN_UPLOADED = "uploaded"
        private const val COLUMN_ATTEMPTS = "attempts"
        private const val SEVEN_DAYS_MS = 7L * 24 * 60 * 60 * 1000
    }
}
