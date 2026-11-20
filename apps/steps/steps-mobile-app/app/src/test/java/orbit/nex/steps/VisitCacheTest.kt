package orbit.nex.steps

import orbit.nex.steps.location.LibraryEntry
import orbit.nex.steps.location.LibrarySnapshot
import orbit.nex.steps.location.VisitCache
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.io.File

class VisitCacheTest {
    @Test
    fun savedVisitsAreReadBackOfflineWithoutCoordinates() {
        val file = File.createTempFile("visits", ".json")
        try {
            val entry = LibraryEntry(id = "v1", title = "Kawiarnia Rynek", subtitle = "10:00 — 11:00", meta = "cafe · 82%", status = "confirmed")
            VisitCache.write(file, LibrarySnapshot(entries = listOf(entry, entry.copy(id = "v2", title = "", status = "unidentified"))))
            assertFalse(file.readText().contains("lat"))
            val restored = VisitCache.read(file)!!
            assertTrue(restored.offline)
            assertEquals(listOf("v1", "v2"), restored.entries.map { it.id })
            assertEquals("Kawiarnia Rynek", restored.entries[0].title)
            assertEquals(true, restored.entries[0].active)
            assertEquals(false, restored.entries[1].active)
            assertTrue(restored.entries[1].actions.isEmpty())
        } finally {
            file.delete()
        }
    }

    @Test
    fun missingOrCorruptCacheYieldsNothing() {
        assertNull(VisitCache.read(null))
        assertNull(VisitCache.read(File("/nonexistent/visits.json")))
        val file = File.createTempFile("visits", ".json")
        try {
            file.writeText("not json")
            assertNull(VisitCache.read(file))
        } finally {
            file.delete()
        }
    }
}
