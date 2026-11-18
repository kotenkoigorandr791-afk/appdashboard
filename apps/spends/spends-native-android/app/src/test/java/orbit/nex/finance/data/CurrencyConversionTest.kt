package orbit.nex.finance.data

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class CurrencyConversionTest {
    @Test
    fun keepsBaseCurrencyAmount() {
        assertEquals(12.5, CurrencyConversion.toBase(12.5, "USD", "USD", emptyMap())!!, 0.0001)
    }

    @Test
    fun dividesByRate() {
        // Rates are expressed per one unit of base currency.
        val converted = CurrencyConversion.toBase(100.0, "EUR", "USD", mapOf("EUR" to 0.5))
        assertEquals(200.0, converted!!, 0.0001)
    }

    @Test
    fun roundsToCents() {
        val converted = CurrencyConversion.toBase(10.0, "EUR", "USD", mapOf("EUR" to 3.0))
        assertEquals(3.33, converted!!, 0.0001)
    }

    @Test
    fun missingRateReturnsNull() {
        assertNull(CurrencyConversion.toBase(10.0, "GBP", "USD", mapOf("EUR" to 0.9)))
    }

    @Test
    fun zeroRateReturnsNull() {
        assertNull(CurrencyConversion.toBase(10.0, "EUR", "USD", mapOf("EUR" to 0.0)))
    }

    @Test
    fun optionsPutBaseFirst() {
        assertEquals(listOf("CZK", "USD", "EUR", "PLN", "UAH", "BYN"), CurrencyConversion.options("czk"))
    }
}
