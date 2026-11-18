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

    /**
     * Finds a known store directly inside the notification text. This is the
     * safety net for wallet formats where the merchant is not introduced by
     * "at/to/в": if every word of a store name is present, the match is
     * considered certain and the charge is booked automatically.
     */
    fun matchInText(rawText: String, stores: List<Store>): Store? {
        val text = normalize(rawText)
        if (text.isBlank()) return null
        val textTokens = tokens(text)
        var best: Store? = null
        var bestScore = 0.0
        stores.forEach { store ->
            val name = normalize(store.name)
            if (name.isBlank()) return@forEach
            val nameTokens = tokens(name)
            val exactTokens = nameTokens.isNotEmpty() && nameTokens.all { it in textTokens }
            val candidate = when {
                exactTokens -> 0.99
                name.length >= 4 && text.contains(name) -> 0.96
                else -> 0.0
            }
            if (candidate > bestScore) {
                bestScore = candidate
                best = store
            }
        }
        return if (bestScore >= 0.9) best else null
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

    /**
     * Currency codes are matched as whole tokens only: a store called "Europa"
     * must not be read as EUR, and "PLN Market" must not turn a dollar charge
     * into zloty.
     */
    private val currencyCodePattern = Regex(
        """(?<![\p{L}])(PLN|EUR|USD|UAH|GBP|CZK|CHF|SEK|NOK|BYN|TRY|CAD|JPY|RUB|RON|HUF|DKK|ILS|KZT|MDL|GEL|AUD|NZD|SGD|BRL|INR)(?![\p{L}])""",
        RegexOption.IGNORE_CASE,
    )
    private val symbolCurrencies = listOf(
        "€" to "EUR",
        "£" to "GBP",
        "₴" to "UAH",
        "₽" to "BYN",
        "₺" to "TRY",
        "₸" to "KZT",
        "₪" to "ILS",
        "₹" to "INR",
        "zł" to "PLN",
        "Kč" to "CZK",
        "грн" to "UAH",
        "руб" to "BYN",
        "$" to "$",
    )
    private val dollarBases = setOf("USD", "CAD", "AUD", "NZD", "SGD", "HKD", "MXN")
    private val merchantPatterns = listOf(
        Regex("""(?i)\b(?:at|in|to|w|u|в|у|bei|chez|à)\s+([\p{L}\d][\p{L}\d&'.\- ]{2,40})"""),
        Regex("""(?i)\b(?:payment to|paid to|sent to|transfer to|оплата|списание)\s+([\p{L}\d][\p{L}\d&'.\- ]{2,40})"""),
        Regex("""(?i)^([\p{L}\d][\p{L}\d&'.\- ]{2,40}?)\s*[·\-–|:]"""),
    )

    fun parse(title: String, text: String, fallbackCurrency: String, stores: List<Store>): WalletNotification? {
        val raw = listOf(title, text).filter { it.isNotBlank() }.joinToString(" · ")
        if (raw.isBlank()) return null
        val amount = amountPattern.findAll(raw)
            .map { match -> match.groupValues[1].replace(" ", "").replace('\u00a0', ' ').replace(',', '.').toDoubleOrNull() }
            .filterNotNull()
            .firstOrNull { it > 0 } ?: return null
        // Prefer the merchant captured from the notification body, then the title.
        val merchant = (merchantPatterns.asSequence().mapNotNull { pattern -> pattern.find(text)?.groupValues?.getOrNull(1) }.firstOrNull()
            ?: title.takeIf { it.isNotBlank() && !it.any(Char::isDigit) })
            ?.trim()?.trim('·', '-', '|')?.take(60)
            ?: title.take(60)
        // 1) fuzzy match the extracted merchant, 2) fall back to a confident
        // whole-word search of the notification text so known stores are never
        // sent to disputes just because the sentence structure changed.
        val matchedStore = StoreMatcher.match(merchant, stores)
            ?: StoreMatcher.matchInText(title, stores)
            ?: StoreMatcher.matchInText(text, stores)
            ?: StoreMatcher.matchInText(raw, stores)
        val storeName = matchedStore?.name ?: merchant.ifBlank { "Wallet" }
        // Store names are removed before reading the currency: "€uro Market" or
        // "EUR Store" describe the shop, not the money. A code that ends the
        // merchant ("... at Lidl PLN") is still the charge currency.
        val currency = merchant.currencySuffix()
            ?: detectCurrency(raw, listOfNotNull(matchedStore?.name, merchant), fallbackCurrency)
        return WalletNotification(storeName, amount, currency, raw, matchedStore != null)
    }

    /**
     * Reads the currency of a charge. The strongest signal is the code or symbol
     * printed next to the amount; codes and symbols found anywhere else in the
     * text are only used when nothing sits close to the number.
     */
    fun detectCurrency(raw: String, ignoredNames: List<String>, fallback: String): String {
        val base = CurrencyConversion.normalize(fallback)
        var text = raw
        ignoredNames.map { it.trim() }.filter { it.length >= 3 }.forEach { name ->
            text = text.replace(Regex(Regex.escape(name), RegexOption.IGNORE_CASE), " ")
        }
        amountPattern.findAll(text).firstOrNull()?.let { match ->
            val start = (match.range.first - 6).coerceAtLeast(0)
            val end = (match.range.last + 7).coerceAtMost(text.length)
            nearestCurrency(text.substring(start, end), base)?.let { return it }
        }
        nearestCurrency(text, base)?.let { return it }
        return base
    }

    /** A standalone code at the end of a merchant string belongs to the charge, not to the shop name. */
    private fun String.currencySuffix(): String? {
        val parts = trim().split(Regex("\\s+")).filter { it.isNotBlank() }
        if (parts.size < 2) return null
        val match = currencyCodePattern.matchEntire(parts.last()) ?: return null
        return match.groupValues[1].uppercase(Locale.ROOT)
    }

    private fun nearestCurrency(text: String, base: String): String? {
        currencyCodePattern.find(text)?.let { return it.groupValues[1].uppercase(Locale.ROOT) }
        val lowered = text.lowercase(Locale.ROOT)
        symbolCurrencies.forEach { (symbol, code) ->
            if (lowered.contains(symbol.lowercase(Locale.ROOT))) {
                return if (code == "$") dollarCurrency(text, base) else code
            }
        }
        return null
    }

    /** `$` is used by several currencies; the account currency wins unless the text is explicit. */
    private fun dollarCurrency(text: String, base: String): String {
        val upper = text.uppercase(Locale.ROOT)
        return when {
            "CA$" in upper -> "CAD"
            "AU$" in upper || "A$" in upper -> "AUD"
            "NZ$" in upper -> "NZD"
            "US$" in upper -> "USD"
            else -> if (base in dollarBases) base else "USD"
        }
    }
}
