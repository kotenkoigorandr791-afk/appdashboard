package orbit.nex.steps

import org.junit.Assert.assertEquals
import org.junit.Test

class SyncScheduleTest {
    @Test fun keepsDeadlineAcrossRestarts() {
        val anchor = 1_000_000L
        val interval = 3_600_000L
        assertEquals(anchor + interval, nextIntervalSyncAt(anchor + 1_000, anchor, null, interval))
        assertEquals(anchor + interval, nextIntervalSyncAt(anchor + 3_500_000, anchor, null, interval))
    }
    @Test fun retriesFailedAttemptWithoutMovingSuccessfulDeadline() {
        val anchor = 1_000_000L
        val due = anchor + 3_600_000L
        assertEquals(due + 15 * 60_000L, nextIntervalSyncAt(due + 2_000L, anchor, due, 3_600_000L))
        assertEquals(due, nextIntervalSyncAt(due - 1, anchor, anchor + 1, 3_600_000L))
    }
}
