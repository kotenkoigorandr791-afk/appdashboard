package xyz.serverkherson.spends.data.local

import android.content.Context
import androidx.room.Dao
import androidx.room.Database
import androidx.room.Entity
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.PrimaryKey
import androidx.room.Query
import androidx.room.Room
import androidx.room.RoomDatabase
import kotlinx.coroutines.flow.Flow

@Entity(tableName = "accounts") data class AccountEntity(@PrimaryKey val id: String, val name: String, val type: String, val balance: Long, val currencyCode: String, val icon: String? = null, val archived: Boolean = false)
@Entity(tableName = "categories") data class CategoryEntity(@PrimaryKey val id: String, val name: String, val type: String, val icon: String? = null, val color: String? = null)
@Entity(tableName = "transactions") data class TransactionEntity(@PrimaryKey val id: String, val type: String, val amount: Long, val currencyCode: String, val accountId: String, val categoryId: String?, val date: String, val note: String? = null, val pending: Boolean = false, val deleted: Boolean = false, val recurringId: String? = null, val goalId: String? = null, val budgetId: String? = null, val loanId: String? = null)
@Entity(tableName = "tags") data class TagEntity(@PrimaryKey val id: String, val name: String, val type: String, val color: String? = null, val icon: String? = null)
@Entity(tableName = "transaction_tags", primaryKeys = ["transactionId", "tagId"]) data class TransactionTagEntity(val transactionId: String, val tagId: String)
@Entity(tableName = "transfers") data class TransferEntity(@PrimaryKey val id: String, val fromTransactionId: String, val toTransactionId: String, val fromAccountId: String, val toAccountId: String, val conversionRate: Double = 1.0)
@Entity(tableName = "goals") data class GoalEntity(@PrimaryKey val id: String, val name: String, val targetAmount: Long, val currencyCode: String, val targetDate: String?, val type: String, val archived: Boolean = false)
@Entity(tableName = "budgets") data class BudgetEntity(@PrimaryKey val id: String, val name: String, val amount: Long, val currencyCode: String, val period: String, val startDate: String, val endDate: String?, val active: Boolean = true)
@Entity(tableName = "loans") data class LoanEntity(@PrimaryKey val id: String, val name: String, val principalAmount: Long, val currencyCode: String, val type: String, val accountId: String?, val categoryId: String?)
@Entity(tableName = "recurring_transactions") data class RecurringTransactionEntity(@PrimaryKey val id: String, val type: String, val amount: Long, val currencyCode: String, val accountId: String, val categoryId: String?, val startDate: String, val rule: String, val disabled: Boolean = false)

@Dao interface LedgerDao {
    @Query("SELECT * FROM transactions WHERE deleted = 0 ORDER BY date DESC") fun transactions(): Flow<List<TransactionEntity>>
    @Query("SELECT * FROM accounts WHERE archived = 0 ORDER BY name") fun accounts(): Flow<List<AccountEntity>>
    @Query("SELECT * FROM categories ORDER BY name") fun categories(): Flow<List<CategoryEntity>>
    @Query("SELECT * FROM budgets WHERE active = 1 ORDER BY startDate DESC") fun budgets(): Flow<List<BudgetEntity>>
    @Query("SELECT * FROM goals WHERE archived = 0 ORDER BY name") fun goals(): Flow<List<GoalEntity>>
    @Query("SELECT * FROM tags ORDER BY name") fun tags(): Flow<List<TagEntity>>
    @Insert(onConflict = OnConflictStrategy.REPLACE) suspend fun putTransactions(items: List<TransactionEntity>)
    @Insert(onConflict = OnConflictStrategy.REPLACE) suspend fun putAccounts(items: List<AccountEntity>)
    @Insert(onConflict = OnConflictStrategy.REPLACE) suspend fun putCategories(items: List<CategoryEntity>)
}

@Database(entities = [AccountEntity::class, CategoryEntity::class, TransactionEntity::class, TagEntity::class, TransactionTagEntity::class, TransferEntity::class, GoalEntity::class, BudgetEntity::class, LoanEntity::class, RecurringTransactionEntity::class], version = 1, exportSchema = false)
abstract class SpendsDatabase : RoomDatabase() {
    abstract fun ledger(): LedgerDao
    companion object { fun create(context: Context) = Room.databaseBuilder(context, SpendsDatabase::class.java, "spends.db").fallbackToDestructiveMigration().build() }
}
