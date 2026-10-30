package orbit.nex.finance

import android.content.Context
import android.content.res.Configuration
import java.util.Locale

/**
 * Applies the language picked in Appearance to the activity's resources.
 * The choice is mirrored into SharedPreferences so it is readable synchronously
 * from attachBaseContext, before any resource is resolved.
 */
object AppLocale {
    private const val PREFS = "spends_ui"
    private const val KEY = "language"

    fun store(context: Context, language: String) {
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().putString(KEY, language).apply()
    }

    fun current(context: Context): String =
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getString(KEY, "").orEmpty()

    fun wrap(base: Context): Context {
        val tag = current(base)
        if (tag.isBlank()) return base
        val locale = Locale.forLanguageTag(tag)
        Locale.setDefault(locale)
        val config = Configuration(base.resources.configuration)
        config.setLocale(locale)
        config.setLayoutDirection(locale)
        return base.createConfigurationContext(config)
    }
}
