package orbit.nex.finance.data

import java.util.Locale
import kotlin.math.round

/**
 * Currency handling shared by manual entry, subscriptions and wallet captures.
 *
 * The ledger always stores amounts in the account's base currency (that is what
 * the server expects), so a charge captured or typed in another currency is
 * converted through the exchange rates before it is written.
 */
object CurrencyConversion {
    /** Same shortlist the web manual-entry picker offers. */
    val inputCurrencies = listOf("USD", "EUR", "PLN", "UAH", "BYN")

    fun normalize(code: String, fallback: String = "USD"): String {
        val value = code.trim().uppercase(Locale.ROOT)
        return if (value.length == 3) value else fallback.trim().uppercase(Locale.ROOT).ifBlank { "USD" }
    }

    /** Base currency first, then the common choices, so the picker always opens on the ledger currency. */
    fun options(base: String): List<String> {
        val normalized = normalize(base)
        return (listOf(normalized) + inputCurrencies).distinct()
    }

    /** Amount in [base], or null when the rate for [from] is missing or invalid. */
    fun toBase(amount: Double, from: String, base: String, rates: Map<String, Double>): Double? {
        if (amount <= 0.0) return null
        val target = normalize(base)
        val source = normalize(from, target)
        if (source == target) return amount
        val rate = rates.entries.firstOrNull { it.key.uppercase(Locale.ROOT) == source }?.value ?: return null
        if (rate <= 0.0) return null
        return round(amount / rate * 100.0) / 100.0
    }
}
