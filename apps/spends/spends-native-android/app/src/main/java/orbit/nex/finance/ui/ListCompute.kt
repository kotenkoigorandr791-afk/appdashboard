package orbit.nex.finance.ui

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import orbit.nex.finance.data.Expense
import orbit.nex.finance.data.Income
import orbit.nex.finance.data.Store
import java.time.LocalDate
import java.time.YearMonth

data class HeroTotals(
    val net: Double,
    val monthSpent: Double,
    val monthEarned: Double,
    /** Expense change vs previous month, percent; null when both months are zero. */
    val expenseMomPercent: Double?,
)

internal fun expenseMonthOverMonthPercent(current: Double, previous: Double): Double? = when {
    current <= 0.0 && previous <= 0.0 -> null
    previous <= 0.0 -> 100.0
    else -> ((current - previous) / previous) * 100.0
}

enum class StoreSort { DEFAULT, NAME_ASC, NAME_DESC, SPEND_DESC, SPEND_ASC }

@Composable
fun rememberHeroTotals(expenses: List<Expense>, incomes: List<Income>): HeroTotals {
    val month = remember { YearMonth.now().toString() }
    val prevMonth = remember { YearMonth.now().minusMonths(1).toString() }
    var totals by remember { mutableStateOf(HeroTotals(0.0, 0.0, 0.0, null)) }
    LaunchedEffect(expenses, incomes, month, prevMonth) {
        totals = withContext(Dispatchers.Default) {
            val monthSpent = expenses.filter { it.date.startsWith(month) }.sumOf { it.amount }
            val monthEarned = incomes.filter { it.date.startsWith(month) }.sumOf { it.amount }
            val prevSpent = expenses.filter { it.date.startsWith(prevMonth) }.sumOf { it.amount }
            HeroTotals(
                net = monthEarned - monthSpent,
                monthSpent = monthSpent,
                monthEarned = monthEarned,
                expenseMomPercent = expenseMonthOverMonthPercent(monthSpent, prevSpent),
            )
        }
    }
    return totals
}

@Composable
fun rememberExpensesByDate(expenses: List<Expense>): Map<String, List<Expense>> {
    var groups by remember { mutableStateOf(emptyMap<String, List<Expense>>()) }
    LaunchedEffect(expenses) {
        groups = withContext(Dispatchers.Default) {
            expenses.sortedByDescending { it.date }.groupBy { it.date }
        }
    }
    return groups
}

@Composable
fun rememberStoreSpendTotals(expenses: List<Expense>): Map<String, Double> {
    var totals by remember { mutableStateOf(emptyMap<String, Double>()) }
    LaunchedEffect(expenses) {
        totals = withContext(Dispatchers.Default) {
            expenses.groupBy { it.store }.mapValues { entry -> entry.value.sumOf { it.amount } }
        }
    }
    return totals
}

@Composable
fun rememberStorePhotoIndex(stores: List<Store>): Map<String, String> {
    return remember(stores) { stores.associate { it.name to it.photoUrl } }
}

@Composable
fun rememberExpenseSearchResults(query: String, expenses: List<Expense>, limit: Int = 60): List<Expense> {
    var debounced by remember { mutableStateOf(query) }
    LaunchedEffect(query) {
        kotlinx.coroutines.delay(if (query.length < 2) 0L else 220L)
        debounced = query
    }
    var results by remember { mutableStateOf(emptyList<Expense>()) }
    LaunchedEffect(debounced, expenses) {
        results = withContext(Dispatchers.Default) {
            val needle = debounced.trim()
            if (needle.isBlank()) emptyList()
            else expenses.asSequence()
                .filter { expense ->
                    expense.store.contains(needle, ignoreCase = true) ||
                        expense.date.contains(needle) ||
                        expense.amount.toString().contains(needle)
                }
                .sortedByDescending { it.date }
                .take(limit)
                .toList()
        }
    }
    return results
}

@Composable
fun rememberSortedStores(
    stores: List<Store>,
    query: String,
    sort: StoreSort,
    totals: Map<String, Double>,
): List<Store> {
    var ordered by remember { mutableStateOf(stores) }
    LaunchedEffect(stores, query, sort, totals) {
        ordered = withContext(Dispatchers.Default) {
            val filtered = stores.filter { query.isBlank() || it.name.contains(query.trim(), ignoreCase = true) }
            when (sort) {
                StoreSort.DEFAULT -> filtered
                StoreSort.NAME_ASC -> filtered.sortedBy { it.name.lowercase() }
                StoreSort.NAME_DESC -> filtered.sortedByDescending { it.name.lowercase() }
                StoreSort.SPEND_DESC -> filtered.sortedByDescending { totals[it.name] ?: 0.0 }
                StoreSort.SPEND_ASC -> filtered.sortedBy { totals[it.name] ?: 0.0 }
            }
        }
    }
    return ordered
}