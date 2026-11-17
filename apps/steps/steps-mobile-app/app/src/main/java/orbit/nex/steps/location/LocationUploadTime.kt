package orbit.nex.steps.location

import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/** Wall-clock label for the last successful upload. */
fun formatLocationUploadTime(epochMs: Long): String =
    SimpleDateFormat("HH:mm", Locale.getDefault()).format(Date(epochMs))
