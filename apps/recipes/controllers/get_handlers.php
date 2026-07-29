<?php
// If request to get products
if (isset($_GET['get_products'])) {
    $products = [];
    if ($db) {
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
                    'image' => $row['image'],
                    'image_preview' => $row['image_preview'] ?? null,
                    'category' => $row['category'] ?? '',
                    'updated_at' => $row['updated_at'] ?? ''
                ];
            }
        } catch (Exception $e) {
            error_log("Failed to get products: " . $e->getMessage());
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($products, JSON_UNESCAPED_UNICODE);
    exit;
}

// If request to get recipes
if (isset($_GET['get_recipes'])) {
    $recipes = [];
    $page = isset($_GET['recipe_page']) ? (int)$_GET['recipe_page'] : 1;
    
    if ($db) {
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
                $recipes[] = $recipe;
            }
        } catch (Exception $e) {
            error_log("Failed to get recipes: " . $e->getMessage());
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['recipes' => $recipes, 'page' => $page], JSON_UNESCAPED_UNICODE);
    exit;
}

// If request to get full recipe with steps
if (isset($_GET['get_recipe_full']) && isset($_GET['recipe_id'])) {
    $recipe_id = $_GET['recipe_id'];
    $recipe = null;
    
    if ($db) {
        try {
            // Get recipe
            $stmt = $db->prepare("SELECT * FROM " . RECIPES_DB_TABLE_PREFIX . "data WHERE id = :id");
            $stmt->bindValue(':id', $recipe_id, PDO::PARAM_STR);
            $result = $stmt->execute();
            $recipe_data = $result->fetch(PDO::FETCH_ASSOC);
            
            if ($recipe_data) {
                $recipe = [
                    'id' => $recipe_data['id'],
                    'title' => [
                        'en' => $recipe_data['title_en'],
                        'uk' => $recipe_data['title_uk'],
                        'ru' => $recipe_data['title_ru'],
                        'be' => $recipe_data['title_be']
                    ],
                    'image' => $recipe_data['image'],
                    'steps' => []
                ];
                
                // Get steps
                $stmt = $db->prepare("SELECT * FROM " . RECIPES_STEPS_TABLE . " WHERE recipe_id = :id ORDER BY step_order");
                $stmt->bindValue(':id', $recipe_id, PDO::PARAM_STR);
                $result = $stmt->execute();
                
                while ($step = $result->fetch(PDO::FETCH_ASSOC)) {
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
                    
                    $recipe['steps'][] = $new_step;
                }
            }
        } catch (Exception $e) {
            error_log("Failed to get full recipe: " . $e->getMessage());
        }
    }
    
    header('Content-Type: application/json; charset=utf-8');
    if ($recipe) {
        echo json_encode(['success' => true, 'data' => $recipe], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'message' => 'Recipe not found'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
