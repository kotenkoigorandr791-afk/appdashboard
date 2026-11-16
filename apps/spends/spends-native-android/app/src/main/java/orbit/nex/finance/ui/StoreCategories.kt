package orbit.nex.finance.ui

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import orbit.nex.finance.R

/** Category metadata shared with the web interface (same keys, labels and icons). */
object StoreCategories {
    val keys = listOf(
        "food", "groceries", "cafes", "clothes", "shoes", "home", "apartment", "utilities",
        "transport", "car", "travel", "health", "pharmacy", "beauty", "children", "pets",
        "build", "education", "sports", "electronics", "subscriptions", "entertainment",
        "gifts", "services", "finance", "taxes", "insurance", "charity", "other",
    )

    @StringRes
    fun labelRes(key: String): Int = when (key) {
        "food" -> R.string.store_category_food
        "groceries" -> R.string.store_category_groceries
        "cafes" -> R.string.store_category_cafes
        "clothes" -> R.string.store_category_clothes
        "shoes" -> R.string.store_category_shoes
        "home" -> R.string.store_category_home
        "apartment" -> R.string.store_category_apartment
        "utilities" -> R.string.store_category_utilities
        "transport" -> R.string.store_category_transport
        "car" -> R.string.store_category_car
        "travel" -> R.string.store_category_travel
        "health" -> R.string.store_category_health
        "pharmacy" -> R.string.store_category_pharmacy
        "beauty" -> R.string.store_category_beauty
        "children" -> R.string.store_category_children
        "pets" -> R.string.store_category_pets
        "build" -> R.string.store_category_build
        "education" -> R.string.store_category_education
        "sports" -> R.string.store_category_sports
        "electronics" -> R.string.store_category_electronics
        "subscriptions" -> R.string.store_category_subscriptions
        "entertainment" -> R.string.store_category_entertainment
        "gifts" -> R.string.store_category_gifts
        "services" -> R.string.store_category_services
        "finance" -> R.string.store_category_finance
        "taxes" -> R.string.store_category_taxes
        "insurance" -> R.string.store_category_insurance
        "charity" -> R.string.store_category_charity
        else -> R.string.store_category_other
    }

    @DrawableRes
    fun iconRes(key: String): Int = when (key) {
        "food" -> R.drawable.cat_food
        "groceries" -> R.drawable.cat_groceries
        "cafes" -> R.drawable.cat_cafes
        "clothes" -> R.drawable.cat_clothes
        "shoes" -> R.drawable.cat_shoes
        "home" -> R.drawable.cat_home
        "apartment" -> R.drawable.cat_apartment
        "utilities" -> R.drawable.cat_utilities
        "transport" -> R.drawable.cat_transport
        "car" -> R.drawable.cat_car
        "travel" -> R.drawable.cat_travel
        "health" -> R.drawable.cat_health
        "pharmacy" -> R.drawable.cat_pharmacy
        "beauty" -> R.drawable.cat_beauty
        "children" -> R.drawable.cat_children
        "pets" -> R.drawable.cat_pets
        "build" -> R.drawable.cat_build
        "education" -> R.drawable.cat_education
        "sports" -> R.drawable.cat_sports
        "electronics" -> R.drawable.cat_electronics
        "subscriptions" -> R.drawable.cat_subscriptions
        "entertainment" -> R.drawable.cat_entertainment
        "gifts" -> R.drawable.cat_gifts
        "services" -> R.drawable.cat_services
        "finance" -> R.drawable.cat_finance
        "taxes" -> R.drawable.cat_taxes
        "insurance" -> R.drawable.cat_insurance
        "charity" -> R.drawable.cat_charity
        else -> R.drawable.cat_other
    }
}

@Composable
fun categoryLabel(key: String): String =
    if (key in StoreCategories.keys) stringResource(StoreCategories.labelRes(key)) else key.replaceFirstChar { it.uppercase() }
