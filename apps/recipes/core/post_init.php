<?php
// Get recipes and products from database
$recipes = getAllRecipesFromDB();
$products = getAllProductsFromDB();

// Get current recipe for display
$current_recipe = null;
if ($recipe_state['current_recipe']) {
    foreach ($recipes as $recipe) {
        if ($recipe['id'] === $recipe_state['current_recipe']) {
            $current_recipe = $recipe;
            break;
        }
    }
}
