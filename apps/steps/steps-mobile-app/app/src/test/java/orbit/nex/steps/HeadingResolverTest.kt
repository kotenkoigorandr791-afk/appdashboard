package orbit.nex.steps

import orbit.nex.steps.location.HeadingResolver
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

/** The puck arrow has to follow movement, then the compass, then a known bearing. */
class HeadingResolverTest {

    @Test
    fun `moving uses the travel bearing instead of the compass`() {
        assertEquals(90f, HeadingResolver.resolve(bearingDeg = 90f, speedMps = 4f, azimuthDeg = 10f)!!, 0.001f)
    }

    @Test
    fun `standing still uses the compass`() {
        assertEquals(270f, HeadingResolver.resolve(bearingDeg = 90f, speedMps = 0f, azimuthDeg = 270f)!!, 0.001f)
        // No speed information at all is the same as standing still.
        assertEquals(12f, HeadingResolver.resolve(bearingDeg = 90f, speedMps = null, azimuthDeg = 12f)!!, 0.001f)
    }

    @Test
    fun `compass is not needed when moving`() {
        assertEquals(180f, HeadingResolver.resolve(bearingDeg = 180f, speedMps = 2f, azimuthDeg = null)!!, 0.001f)
    }

    @Test
    fun `falls back to the last bearing when no compass exists`() {
        assertEquals(45f, HeadingResolver.resolve(bearingDeg = 45f, speedMps = 0f, azimuthDeg = null)!!, 0.001f)
    }

    @Test
    fun `no direction at all stays unknown`() {
        assertNull(HeadingResolver.resolve(bearingDeg = null, speedMps = 1f, azimuthDeg = null))
    }

    @Test
    fun `angles are normalised into zero to three-sixty`() {
        assertEquals(350f, HeadingResolver.normalize(-10f), 0.001f)
        assertEquals(10f, HeadingResolver.normalize(370f), 0.001f)
    }

    @Test
    fun `smoothing takes the short way across north`() {
        // From 350 toward 10 degrees the arrow must move through 0, not backwards.
        val smoothed = HeadingResolver.smooth(previous = 350f, next = 10f, factor = 0.5f)
        assertEquals(0f, smoothed, 0.001f)
        // A small step stays proportional and inside range.
        val tiny = HeadingResolver.smooth(previous = 359f, next = 1f, factor = 0.25f)
        assertEquals(359.5f, tiny, 0.001f)
    }
}
