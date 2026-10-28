<?php
function getAllRecipesFromDB() {
    $db = getDB();
    $recipes = [];
    
    if (!$db) return $recipes;
    
    try {
        $result = $db->query("SELECT d.id, d.title_en, d.title_uk, d.title_ru, d.title_be, d.image,
            COUNT(s.id) AS step_count
            FROM " . RECIPES_DB_TABLE_PREFIX . "data d
            LEFT JOIN " . RECIPES_STEPS_TABLE . " s ON s.recipe_id = d.id
            GROUP BY d.id, d.title_en, d.title_uk, d.title_ru, d.title_be, d.image
            ORDER BY d.title_en");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $recipe = [
                'id' => $row['id'],
                'title' => [
                    'en' => $row['title_en'],
                    'uk' => $row['title_uk'],
                    'ru' => $row['title_ru'],
                    'be' => $row['title_be']
                ],
                'image' => $row['image']
            ];
            
            $recipe['step_count'] = (int)$row['step_count'];
            $recipe['steps'] = []; // Will be loaded on demand
            
            $recipes[] = $recipe;
        }
    } catch (Exception $e) {
        error_log("Failed to get recipes from DB: " . $e->getMessage());
    }
    
    return $recipes;
}

function getAllProductsFromDB() {
    $db = getDB();
    $products = [];
    
    if (!$db) return $products;
    
    try {
        $result = $db->query("SELECT id, name_en, name_uk, name_ru, name_be, category, image, image_preview, updated_at
            FROM " . RECIPES_DB_TABLE_PREFIX . "products ORDER BY name_en");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $products[] = [
                'id' => $row['id'],
                'name' => [
                    'en' => $row['name_en'],
                    'uk' => $row['name_uk'],
                    'ru' => $row['name_ru'],
                    'be' => $row['name_be']
                ],
                'category' => $row['category'] ?? '',
                'image' => $row['image'],
                'image_preview' => $row['image_preview'] ?? null,
                'updated_at' => $row['updated_at'] ?? ''
            ];
        }
    } catch (Exception $e) {
        error_log("Failed to get products from DB: " . $e->getMessage());
    }
    
    return $products;
}
