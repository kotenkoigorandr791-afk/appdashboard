package com.stepmonitor.app.components

/**
 * The six languages supported by the AppDashboard server, matching
 * `ap_i18n_supported_langs()` (en, ru, uk, be, pl, cs).
 *
 * `""` represents "follow the system locale" and clears any per-app override.
 */
val APP_LANGUAGES: List<Pair<String, String>> = listOf(
    "" to "",
    "en" to "English",
    "ru" to "Русский",
    "uk" to "Українська",
    "be" to "Беларуская",
    "pl" to "Polski",
    "cs" to "Čeština",
)

val DEFAULT_APP_LANGUAGE = "en"

fun languageDisplayName(tag: String): String =
    APP_LANGUAGES.firstOrNull { it.first == tag }?.second ?: tag