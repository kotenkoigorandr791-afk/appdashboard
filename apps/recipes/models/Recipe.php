<?php
function getRecipeFromDB($recipe_id) {
    $db = getDB();
    if (!$db) return null;
    
    $recipe = null;
    $steps = [];
    
    try {
        // Get recipe
        $stmt = $db->prepare("SELECT * FROM " . RECIPES_DB_TABLE_PREFIX . "data WHERE id = :id");
        $stmt->bindValue(':id', $recipe_id, SQLITE3_TEXT);
        $result = $stmt->execute();
        $recipe_data = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($recipe_data) {
            $recipe = [
                'id' => $recipe_data['id'],
                'title' => [
                    'en' => $recipe_data['title_en'],
                    'uk' => $recipe_data['title_uk'],
                    'ru' => $recipe_data['title_ru'],
                    'be' => $recipe_data['title_be']
                ],
                'image' => $recipe_data['image']
            ];
            
            // Get steps
            $stmt = $db->prepare("SELECT * FROM " . RECIPES_STEPS_TABLE . " WHERE recipe_id = :id ORDER BY step_order");
            $stmt->bindValue(':id', $recipe_id, SQLITE3_TEXT);
            $result = $stmt->execute();
            
            while ($step = $result->fetchArray(SQLITE3_ASSOC)) {
                $new_step = [
                    'type' => $step['step_type'],
                    'timer' => null
                ];
                
                if ($step['step_type'] === 'product') {
                    $new_step['content'] = $step['content'];
                    if ($step['quantity_value'] !== null) {
                        $new_step['quantity'] = [
                            'value' => $step['quantity_value'],
                            'unit' => $step['quantity_unit']
                        ];
                    }
                } else if ($step['step_type'] === 'instruction') {
                    $new_step['content'] = json_decode($step['content'], true);
                }
                
                if ($step['timer_hours'] !== null || $step['timer_minutes'] !== null) {
                    $new_step['timer'] = [
                        'hours' => $step['timer_hours'],
                        'minutes' => $step['timer_minutes']
                    ];
                }
                
                $steps[] = $new_step;
            }
            
            $recipe['steps'] = $steps;
        }
    } catch (Exception $e) {
        error_log("Failed to get recipe from DB: " . $e->getMessage());
        return null;
    }
    
    return $recipe;
}

function getAllRecipesFromDB() {
    $db = getDB();
    $recipes = [];
    
    if (!$db) return $recipes;
    
    try {
        $result = $db->query("SELECT * FROM " . RECIPES_DB_TABLE_PREFIX . "data ORDER BY title_en");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
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
            $stmt->bindValue(':id', $row['id'], SQLITE3_TEXT);
            $step_result = $stmt->execute();
            $step_data = $step_result->fetchArray(SQLITE3_ASSOC);
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
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
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

