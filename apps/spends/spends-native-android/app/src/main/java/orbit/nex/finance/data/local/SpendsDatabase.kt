package orbit.nex.finance.data.local

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase

@Database(
    entities = [
        ExpenseEntity::class,
        IncomeEntity::class,
        StoreEntity::class,
        LedgerMetaEntity::class,
    ],
    version = 1,
    exportSchema = false,
)
abstract class SpendsDatabase : RoomDatabase() {
    abstract fun ledger(): LedgerDao

    companion object {
        fun create(context: Context): SpendsDatabase =
            Room.databaseBuilder(context.applicationContext, SpendsDatabase::class.java, "spends_ledger.db")
                .fallbackToDestructiveMigration()
                .build()
    }
}