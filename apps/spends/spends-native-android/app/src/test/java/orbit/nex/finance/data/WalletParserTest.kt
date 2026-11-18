package orbit.nex.finance.data

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Covers the notification formats that used to fall through to the dispute list
 * even though the store was already known.
 */
class WalletParserTest {
    private val stores = listOf(
        Store(id = "1", name = "Lidl", category = "groceries"),
        Store(id = "2", name = "Silpo", category = "groceries"),
        Store(id = "3", name = "АТБ", category = "groceries"),
        Store(id = "4", name = "Biedronka", category = "groceries"),
        Store(id = "5", name = "Жизнь", category = "other"),
    )

    private fun parse(title: String, text: String) =
        WalletParser.parse(title, text, "USD", stores)

    @Test
    fun matchesMerchantAfterAt() {
        val result = parse("Payment successful", "You paid $12.34 at Lidl")!!
        assertEquals("Lidl", result.store)
        assertTrue(result.matched)
    }

    @Test
    fun matchesMerchantAfterTo() {
        val result = parse("Payment successful", "Payment of 5.00 EUR to Silpo")!!
        assertEquals("Silpo", result.store)
        assertTrue(result.matched)
    }

    @Test
    fun matchesMerchantFromTitle() {
        val result = parse("Biedronka", "12,50 PLN")!!
        assertEquals("Biedronka", result.store)
        assertTrue(result.matched)
    }

    @Test
    fun matchesCyrillicMerchant() {
        val result = parse("Оплата", "Списано 250,00 UAH в АТБ")!!
        assertEquals("АТБ", result.store)
        assertTrue(result.matched)
    }

    @Test
    fun matchesStoreWithoutPreposition() {
        val result = parse("Google Wallet", "Lidl 5.00 USD")!!
        assertEquals("Lidl", result.store)
        assertTrue(result.matched)
    }

    @Test
    fun keepsUnknownStoreUnmatched() {
        val result = parse("Google Wallet", "You paid 5.00 USD at Some Random Shop")!!
        assertFalse(result.matched)
    }

    @Test
    fun readsAmountWithThousandsSeparator() {
        val result = parse("Payment successful", "Paid 1 234,56 UAH at Silpo")
        assertNotNull(result)
        assertEquals(1234.56, result!!.amount, 0.001)
        assertEquals("Silpo", result.store)
    }

    @Test
    fun readsCurrencyCodeNextToAmount() {
        val result = parse("Payment successful", "You paid 12.34 at Lidl in PLN")
        assertNotNull(result)
        assertEquals("PLN", result!!.currency)
    }

    @Test
    fun readsCurrencySymbolBeforeAmount() {
        val result = parse("Payment successful", "€12,34 at Lidl")
        assertNotNull(result)
        assertEquals("EUR", result!!.currency)
    }

    @Test
    fun readsZlotySuffix() {
        val result = parse("Payment successful", "Zapłacono 34,99 zł w Biedronka")
        assertNotNull(result)
        assertEquals("PLN", result!!.currency)
    }

    @Test
    fun readsHryvniaWord() {
        val result = parse("Оплата", "Списано 250,00 грн в АТБ")
        assertNotNull(result)
        assertEquals("UAH", result!!.currency)
    }

    @Test
    fun lowercaseCodeIsRecognised() {
        val result = parse("Payment successful", "Paid 5.00 eur at Silpo")
        assertNotNull(result)
        assertEquals("EUR", result!!.currency)
    }

    @Test
    fun currencyInsideStoreNameIsNotUsed() {
        // "Europa" contains the letters EUR; the charge itself is in dollars.
        val result = parse("Payment successful", "You paid 9.99 USD at Europa Market")
        assertNotNull(result)
        assertEquals("USD", result!!.currency)
    }

    @Test
    fun currencyAfterMerchantNameIsStillRead() {
        val result = parse("Payment successful", "You paid 5,00 at Lidl PLN")
        assertNotNull(result)
        assertEquals("PLN", result!!.currency)
    }

    @Test
    fun storeNamedWithCurrencyKeepsAccountCurrency() {
        // Unknown shop whose name starts with a currency code.
        val result = WalletParser.parse("Payment successful", "You paid 5.00 at EUR Store", "USD", stores)
        assertNotNull(result)
        assertEquals("USD", result!!.currency)
    }

    @Test
    fun currencyCodeInStoreNameFallsBackToAccountCurrency() {
        val storesWithPlName = stores + Store(id = "9", name = "PLN Market", category = "groceries")
        val result = WalletParser.parse("Payment successful", "You paid 5.00 at PLN Market", "EUR", storesWithPlName)
        assertNotNull(result)
        assertEquals("PLN Market", result!!.store)
        assertEquals("EUR", result.currency)
    }

    @Test
    fun dollarSymbolUsesAccountCurrencyByDefault() {
        val result = WalletParser.parse("Payment successful", "You paid $5.00 at Lidl", "CAD", stores)
        assertNotNull(result)
        assertEquals("CAD", result!!.currency)
    }

    @Test
    fun explicitUsDollarWins() {
        val result = WalletParser.parse("Payment successful", "You paid US$5.00 at Lidl", "CAD", stores)
        assertNotNull(result)
        assertEquals("USD", result!!.currency)
    }

    @Test
    fun unknownCurrencyFallsBackToAccountCurrency() {
        val result = parse("Payment successful", "You paid 5.00 at Lidl")
        assertNotNull(result)
        assertEquals("USD", result!!.currency)
    }
}
