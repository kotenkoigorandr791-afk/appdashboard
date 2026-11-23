package orbit.nex.finance.data

import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.combine
import orbit.nex.finance.data.local.LedgerDao
import orbit.nex.finance.data.local.toEntity
import orbit.nex.finance.data.local.toModel

/** Merges slim snapshot metadata (DataStore JSON) with indexed ledger rows (Room). */
object SnapshotStore {
    fun observe(dao: LedgerDao, meta: Flow<Snapshot>): Flow<Snapshot> =
        combine(meta, dao.expenses(), dao.incomes(), dao.stores()) { head, expenses, incomes, stores ->
            head.copy(
                expenses = expenses.map { it.toModel() },
                incomes = incomes.map { it.toModel() },
                stores = stores.map { it.toModel() },
            )
        }

    suspend fun replace(dao: LedgerDao, snapshot: Snapshot) {
        dao.replaceLedger(
            expenses = snapshot.expenses.map { it.toEntity() },
            incomes = snapshot.incomes.map { it.toEntity() },
            stores = snapshot.stores.map { it.toEntity() },
            revision = snapshot.syncedAt.takeIf { it > 0 } ?: System.currentTimeMillis(),
        )
    }

    fun slim(snapshot: Snapshot) = snapshot.copy(expenses = emptyList(), incomes = emptyList(), stores = emptyList())
}