package orbit.nex.finance.data.local

import androidx.room.Entity
import androidx.room.Index
import orbit.nex.finance.data.Expense
import orbit.nex.finance.data.Income
import orbit.nex.finance.data.Store

@Entity(
    tableName = "expenses",
    primaryKeys = ["date", "store", "signature"],
    indices = [
        Index("date"),
        Index("store"),
        Index(value = ["date", "store"]),
    ],
)
data class ExpenseEntity(
    val date: String,
    val store: String,
    val amount: Double,
    val signature: String = "",
    val entrySource: String = "manual",
    val ocrRawStore: String = "",
)

@Entity(
    tableName = "incomes",
    primaryKeys = ["date", "signature"],
    indices = [Index("date")],
)
data class IncomeEntity(val date: String, val amount: Double, val signature: String = "")

@Entity(
    tableName = "stores",
    primaryKeys = ["name"],
    indices = [Index("visible")],
)
data class StoreEntity(
    val id: String = "",
    val name: String,
    val category: String = "other",
    val visible: Boolean = true,
    val photoUrl: String = "",
)

@Entity(tableName = "ledger_meta")
data class LedgerMetaEntity(
    @androidx.room.PrimaryKey val id: Int = 1,
    val revision: Long = 0L,
)

fun ExpenseEntity.toModel() = Expense(date, store, amount, signature, entrySource, ocrRawStore)
fun Expense.toEntity() = ExpenseEntity(date, store, amount, signature, entrySource, ocrRawStore)
fun IncomeEntity.toModel() = Income(date, amount, signature)
fun Income.toEntity() = IncomeEntity(date, amount, signature)
fun StoreEntity.toModel() = Store(id, name, category, visible, photoUrl)
fun Store.toEntity() = StoreEntity(id, name, category, visible, photoUrl)