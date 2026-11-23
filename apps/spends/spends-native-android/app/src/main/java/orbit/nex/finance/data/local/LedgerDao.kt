package orbit.nex.finance.data.local

import androidx.paging.PagingSource
import androidx.room.Dao
import androidx.room.Query
import androidx.room.Transaction
import androidx.room.Upsert

@Dao
interface LedgerDao {
    @Query("SELECT * FROM expenses ORDER BY date DESC, store ASC")
    fun expenses(): kotlinx.coroutines.flow.Flow<List<ExpenseEntity>>

    @Query("SELECT * FROM expenses ORDER BY date DESC, store ASC LIMIT :limit")
    fun recentExpenses(limit: Int): kotlinx.coroutines.flow.Flow<List<ExpenseEntity>>

    @Query("SELECT * FROM expenses ORDER BY date DESC, store ASC")
    fun expensePages(): PagingSource<Int, ExpenseEntity>

    @Query("SELECT COUNT(*) FROM expenses")
    suspend fun expenseCount(): Int

    @Query("SELECT * FROM incomes ORDER BY date DESC")
    fun incomes(): kotlinx.coroutines.flow.Flow<List<IncomeEntity>>

    @Query("SELECT * FROM stores ORDER BY name COLLATE NOCASE ASC")
    fun stores(): kotlinx.coroutines.flow.Flow<List<StoreEntity>>

    @Query("SELECT revision FROM ledger_meta WHERE id = 1 LIMIT 1")
    fun revision(): kotlinx.coroutines.flow.Flow<Long>

    @Upsert suspend fun putExpenses(items: List<ExpenseEntity>)
    @Upsert suspend fun putIncomes(items: List<IncomeEntity>)
    @Upsert suspend fun putStores(items: List<StoreEntity>)
    @Upsert suspend fun putMeta(meta: LedgerMetaEntity)

    @Query("DELETE FROM expenses") suspend fun clearExpenses()
    @Query("DELETE FROM incomes") suspend fun clearIncomes()
    @Query("DELETE FROM stores") suspend fun clearStores()

    @Transaction
    suspend fun replaceLedger(
        expenses: List<ExpenseEntity>,
        incomes: List<IncomeEntity>,
        stores: List<StoreEntity>,
        revision: Long,
    ) {
        clearExpenses()
        clearIncomes()
        clearStores()
        if (expenses.isNotEmpty()) putExpenses(expenses)
        if (incomes.isNotEmpty()) putIncomes(incomes)
        if (stores.isNotEmpty()) putStores(stores)
        putMeta(LedgerMetaEntity(revision = revision))
    }
}