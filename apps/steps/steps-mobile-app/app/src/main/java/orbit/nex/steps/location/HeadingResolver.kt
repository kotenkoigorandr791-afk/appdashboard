package orbit.nex.steps.location

/**
 * Pure direction math for the map puck.
 *
 * The travel bearing is only meaningful while the user is actually moving; when
 * they stand still the phone's compass is what tells the arrow which way to
 * point. Keeping this in a small Android-free object makes the rule testable.
 */
object HeadingResolver {

    /** Movement bearing below this speed is treated as noise. */
    const val MOVING_SPEED_MPS = 1.0f

    /** Prefers the travel bearing while moving, then the compass, then bearing. */
    fun resolve(bearingDeg: Float?, speedMps: Float?, azimuthDeg: Float?): Float? = when {
        bearingDeg != null && (speedMps ?: 0f) >= MOVING_SPEED_MPS -> normalize(bearingDeg)
        azimuthDeg != null -> normalize(azimuthDeg)
        bearingDeg != null -> normalize(bearingDeg)
        else -> null
    }

    fun normalize(degrees: Float): Float {
        val normalized = degrees % 360f
        return if (normalized < 0f) normalized + 360f else normalized
    }

    /** Shortest-path low-pass so a jittery compass does not make the arrow shake. */
    fun smooth(previous: Float?, next: Float, factor: Float = 0.18f): Float {
        val current = previous ?: return normalize(next)
        var delta = normalize(next) - normalize(current)
        if (delta > 180f) delta -= 360f
        if (delta < -180f) delta += 360f
        return normalize(current + delta * factor)
    }
}
