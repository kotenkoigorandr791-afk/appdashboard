package orbit.nex.finance.data

import java.text.Normalizer
import java.util.Locale
import kotlin.math.max

/**
 * Local fuzzy matching between a merchant string from a wallet notification and the
 * stores known to the app. Merchant strings often carry store numbers, legal suffixes,
 * terminal ids or city names, so the comparison works on normalised words and falls
 * back to edit distance for typos.
 */
object StoreMatcher {
    private val stopWords = setOf(
        "sp", "sa", "sas", "srl", "gmbh", "ltd", "llc", "inc", "ag", "bv", "oy", "ab", "as", "sklep",
        "store", "shop", "market", "supermarket", "hypermarket", "spolka", "spzoo", "zoo", "sro", "kft",
        "the", "and", "co", "company", "group", "polska", "poland", "ukraine", "cyprus", "limited",
    )

    fun normalize(raw: String): String {
        val folded = Normalizer.normalize(raw, Normalizer.Form.NFD).replace("\\p{Mn}+".toRegex(), "")
        val cleaned = folded.lowercase(Locale.ROOT).replace("[^\\p{L}\\s]".toRegex(), " ")
        return cleaned.split(' ')
            .map { it.trim() }
            .filter { it.length > 1 && it !in stopWords }
            .joinToString(" ")
            .trim()
    }

    private fun tokens(value: String) = value.split(' ').filter { it.isNotBlank() }.toSet()

    private fun similarity(a: String, b: String): Double {
        if (a.isEmpty() || b.isEmpty()) return 0.0
        if (a == b) return 1.0
        val distance = levenshtein(a, b)
        return 1.0 - distance.toDouble() / max(a.length, b.length)
    }

    private fun score(merchant: String, store: String): Double {
        if (merchant.isBlank() || store.isBlank()) return 0.0
        if (merchant == store) return 1.0
        // "lidl 4711 berlin" should map to "lidl" without needing the tail.
        if (store.length >= 3 && merchant.contains(store)) return 0.95
        if (merchant.length >= 4 && store.contains(merchant)) return 0.9
        val merchantTokens = tokens(merchant)
        val storeTokens = tokens(store)
        val shared = merchantTokens.intersect(storeTokens)
        if (shared.isNotEmpty()) {
            val smallest = minOf(merchantTokens.size, storeTokens.size)
            val coverage = shared.size.toDouble() / smallest
            // A shared word only counts when it is meaningful, not a one-letter fragment.
            val meaningful = shared.any { it.length >= 4 }
            if (meaningful && coverage >= 0.5) return 0.75 + 0.2 * coverage
        }
        return similarity(merchant, store)
    }

    /** Best store for a merchant string, or null when nothing is close enough. */
    fun match(rawMerchant: String, stores: List<Store>): Store? {
        val merchant = normalize(rawMerchant)
        if (merchant.isBlank()) return null
        var best: Store? = null
        var bestScore = 0.0
        stores.forEach { store ->
            val candidate = score(merchant, normalize(store.name))
            if (candidate > bestScore) {
                bestScore = candidate
                best = store
            }
        }
        return if (bestScore >= 0.72) best else null
    }

    private fun levenshtein(a: String, b: String): Int {
        if (a == b) return 0
        if (a.isEmpty()) return b.length
        if (b.isEmpty()) return a.length
        var previous = IntArray(b.length + 1) { it }
        var current = IntArray(b.length + 1)
        for (i in 1..a.length) {
            current[0] = i
            for (j in 1..b.length) {
                val cost = if (a[i - 1] == b[j - 1]) 0 else 1
                current[j] = minOf(current[j - 1] + 1, previous[j] + 1, previous[j - 1] + cost)
            }
            val swap = previous
            previous = current
            current = swap
        }
        return previous[b.length]
    }
}

data class WalletNotification(val store: String, val amount: Double, val currency: String, val raw: String, val matched: Boolean)

/** Parses a Google Wallet payment notification on the device. */
object WalletParser {
    private val amountPattern = Regex("""(\d{1,3}(?:[ .,\u00a0]\d{3})*(?:[.,]\d{2})|\d+(?:[.,]\d{2}))""")
    private val currencyCodes = setOf("PLN", "EUR", "USD", "UAH", "GBP", "CZK", "CHF", "SEK", "NOK", "BYN", "TRY", "CAD")
    private val merchantPatterns = listOf(
        Regex("""(?i)(?:at|in|w|u|в|у|bei|chez|a)\s+([\p{L}\d][\p{L}\d&'.\- ]{2,40})"""),
        Regex("""(?i)^([\p{L}\d][\p{L}\d&'.\- ]{2,40}?)\s*[·\-–|:]"""),
    )

    fun parse(title: String, text: String, fallbackCurrency: String, stores: List<Store>): WalletNotification? {
        val raw = listOf(title, text).filter { it.isNotBlank() }.joinToString(" · ")
        if (raw.isBlank()) return null
        val amount = amountPattern.findAll(raw)
            .map { match -> match.groupValues[1].replace(" ", "").replace('\u00a0', ' ').replace(',', '.').toDoubleOrNull() }
            .filterNotNull()
            .firstOrNull { it > 0 } ?: return null
        val currency = currencyCodes.firstOrNull { raw.contains(it, ignoreCase = true) }
            ?: Regex("""[€$£₴₽]""").find(raw)?.value?.let {
                when (it) {
                    "€" -> "EUR"
                    "$" -> "USD"
                    "£" -> "GBP"
                    "₴" -> "UAH"
                    "₽" -> "BYN"
                    else -> fallbackCurrency
                }
            } ?: fallbackCurrency
        // Prefer the merchant captured from the notification body, then the title.
        val merchant = (merchantPatterns.asSequence().mapNotNull { pattern -> pattern.find(text)?.groupValues?.getOrNull(1) }.firstOrNull()
            ?: title.takeIf { it.isNotBlank() && !it.any(Char::isDigit) })
            ?.trim()?.trim('·', '-', '|')?.take(60)
            ?: title.take(60)
        val matchedStore = StoreMatcher.match(merchant, stores)
        val storeName = matchedStore?.name ?: merchant.ifBlank { "Wallet" }
        return WalletNotification(storeName, amount, currency, raw, matchedStore != null)
    }
}
