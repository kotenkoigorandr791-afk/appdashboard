<?php
function getAllRecipesFromDB() {
    $db = getDB();
    $recipes = [];
    
    if (!$db) return $recipes;
    
    try {
        $result = $db->query("SELECT * FROM " . RECIPES_DB_TABLE_PREFIX . "data ORDER BY title_en");
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
            
            // Get step count
            $stmt = $db->prepare("SELECT COUNT(*) as step_count FROM " . RECIPES_STEPS_TABLE . " WHERE recipe_id = :id");
            $stmt->bindValue(':id', $row['id'], PDO::PARAM_STR);
            $step_result = $stmt->execute();
            $step_data = $step_result->fetch(PDO::FETCH_ASSOC);
            $recipe['step_count'] = $step_data['step_count'];
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
        $result = $db->query("SELECT * FROM " . RECIPES_DB_TABLE_PREFIX . "products ORDER BY name_en");
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

