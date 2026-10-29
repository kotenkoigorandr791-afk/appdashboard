package xyz.serverkherson.spends.data

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable data class Session(val endpoint: String = "", val token: String = "", val email: String = "", val name: String = "", val imageUrl: String = "")
@Serializable data class Expense(val date: String, val store: String, val amount: Double, val signature: String = "", val entrySource: String = "manual", val ocrRawStore: String = "")
@Serializable data class Income(val date: String, val amount: Double, val signature: String = "")
@Serializable data class Store(val id: String = "", val name: String = "", val category: String = "other", val visible: Boolean = true, val photoUrl: String = "")
@Serializable data class Goal(
    val id: String = "", val name: String = "", val description: String = "",
    val targetAmount: Double = 0.0, val currentAmount: Double = 0.0, val currency: String = "USD",
    val targetDate: String = "", val goalType: String = "savings", val isArchived: Boolean = false,
    val progressPercent: Double = 0.0,
)
@Serializable data class Tag(val id: String = "", val name: String = "", val color: String = "", val usageCount: Int = 0)
@Serializable data class Subscription(
    val id: String = "", val store: String = "", val amount: Double = 0.0, val frequency: String = "monthly",
    val dayOfMonth: Int = 1, val intervalDays: Int = 0, val startDate: String = "", val endDate: String = "", val lastRun: String = "",
)
@Serializable data class BackupFile(val name: String, val size: Long = 0, val mtime: Long = 0)
@Serializable data class PendingCapture(val date: String, val store: String, val amount: Double, val raw: String = "", val at: Long = System.currentTimeMillis())
@Serializable data class SettingsFlags(
    val monthlyBudget: Double = 0.0, val currency: String = "USD",
    val showIncomes: Boolean = true, val showTrend: Boolean = false, val trimEmptyMonths: Boolean = false,
)
@Serializable data class Snapshot(
    val currency: String = "USD",
    val budget: Double = 0.0,
    val expenses: List<Expense> = emptyList(),
    val incomes: List<Income> = emptyList(),
    val stores: List<Store> = emptyList(),
    val storeCategories: List<String> = emptyList(),
    val years: List<Int> = emptyList(),
    val subscriptions: List<Subscription> = emptyList(),
    val goals: List<Goal> = emptyList(),
    val tags: List<Tag> = emptyList(),
    val flags: SettingsFlags = SettingsFlags(),
    val syncedAt: Long = 0,
)
@Serializable data class LocalExtras(val pending: List<PendingCapture> = emptyList(), val trash: List<Expense> = emptyList())
@Serializable data class Rates(
    val base: String = "USD",
    val rates: Map<String, Double> = emptyMap(),
    val updatedAt: Long = 0,
    val history: Map<String, List<Double>> = emptyMap(),
)
@Serializable data class Appearance(
    val theme: String = "auto",
    val amoled: Boolean = false,
    val accent: String = "blue",
    val language: String = "",
    val portraitOnly: Boolean = false,
)
@Serializable data class SyncLog(
    val id: String = java.util.UUID.randomUUID().toString(),
    val timestamp: Long = System.currentTimeMillis(),
    val url: String = "",
    val method: String = "GET",
    val success: Boolean = true,
    val statusCode: Int? = null,
    val errorMessage: String? = null,
    val payload: String? = null,
)
