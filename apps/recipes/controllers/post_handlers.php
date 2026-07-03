<?php
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['action'])) {
        $response = ['success' => false, 'message' => ''];
        
        if ($_POST['action'] === 'edit_product_name') {
            $product_id = $_POST['product_id'];
            $clear_product_image = isset($_POST['clear_product_image']) && $_POST['clear_product_image'] === '1';
            
            try {
                $update_fields = "name_en = :name_en, name_uk = :name_uk, name_ru = :name_ru, name_be = :name_be, category = :category, updated_at = CURRENT_TIMESTAMP";
                $params = [
                    ':id' => $product_id,
                    ':name_en' => $_POST['name_en'],
                    ':name_uk' => $_POST['name_uk'],
                    ':name_ru' => $_POST['name_ru'],
                    ':name_be' => $_POST['name_be'],
                    ':category' => $_POST['category'] ?? ''
                ];
                $existing_product = recipes_load_product_row($db, $product_id);

                // Handle image upload if provided
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $product_dir = "$products_dir/$product_id";
                    if (!is_dir($product_dir)) mkdir($product_dir, 0777, true);
                    $image_name = recipes_image_save_optimized_upload($_FILES['image'], $product_dir);
                    if ($image_name === null) {
                        throw new RuntimeException($translations[$lang]['image_url_not_image'] ?? 'Invalid image');
                    }
                    $update_fields .= ", image = :image";
                    $params[':image'] = $image_name;
                    $pv = recipes_product_preview_refresh($products_dir, $product_id, $image_name);
                    $update_fields .= ", image_preview = :image_preview";
                    $params[':image_preview'] = $pv;
                } elseif ($clear_product_image) {
                    $update_fields .= ", image = :image, image_preview = :image_preview";
                    $params[':image'] = null;
                    $params[':image_preview'] = null;
                    $product_dir = "$products_dir/$product_id";
                    if ($existing_product && !empty($existing_product['image'])) {
                        @unlink($product_dir . '/' . $existing_product['image']);
                    }
                    if ($existing_product && !empty($existing_product['image_preview'])) {
                        @unlink($product_dir . '/' . $existing_product['image_preview']);
                    }
                }

                $stmt = $db->prepare("UPDATE " . RECIPES_DB_TABLE_PREFIX . "products SET $update_fields WHERE id = :id");
                foreach ($params as $key => $value) {
                    if (($key === ':image_preview' || $key === ':image') && ($value === null || $value === '')) {
                        $stmt->bindValue($key, null, SQLITE3_NULL);
                    } else {
                        $stmt->bindValue($key, $value, SQLITE3_TEXT);
                    }
                }
                $result = $stmt->execute();
                
                if ($result) {
                    $row = recipes_load_product_row($db, $product_id);
                    if ($row) recipes_save_product_json($products_dir, $row);
                    $response = ['success' => true, 'message' => $translations[$lang]['product_name_updated']];
                } else {
                    $response = ['success' => false, 'message' => $translations[$lang]['product_not_found']];
                }
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        if ($_POST['action'] === 'add_product') {
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $response = [
                    'success' => false,
                    'message' => $translations[$lang]['image_required']
                ];
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                exit;
            }
            $raw_name = trim((string)($_POST['name'] ?? ''));
            if ($raw_name === '') {
                $response = [
                    'success' => false,
                    'message' => 'Product name is required'
                ];
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                exit;
            }

            $product_names = [
                'en' => $raw_name,
                'uk' => $raw_name,
                'ru' => $raw_name,
                'be' => $raw_name
            ];

            $translation_success = true;
            $source_lang = $_POST['lang'];

            foreach (['en', 'uk', 'ru', 'be'] as $l) {
                if ($l !== $source_lang) {
                    $translated = translate_text($_POST['name'], $source_lang, $l);
                    $product_names[$l] = $translated;
                }
            }

            if (recipes_product_name_exists($db, $product_names)) {
                $response = [
                    'success' => false,
                    'message' => 'Product with this name already exists',
                    'duplicate' => true
                ];
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                exit;
            }

            $product_id = uniqid();
            $product_dir = "$products_dir/$product_id";
            mkdir($product_dir, 0777, true);

            $image_name = null;
            $preview_name = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $image_name = recipes_image_save_optimized_upload($_FILES['image'], $product_dir);
                if ($image_name === null) {
                    $response = [
                        'success' => false,
                        'message' => $translations[$lang]['image_url_not_image'] ?? 'Invalid image'
                    ];
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode($response, JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $preview_name = recipes_product_preview_refresh($products_dir, $product_id, $image_name);
            }

            try {
                $stmt = $db->prepare("INSERT INTO " . RECIPES_DB_TABLE_PREFIX . "products 
                    (id, name_en, name_uk, name_ru, name_be, category, image, image_preview)
                    VALUES (:id, :name_en, :name_uk, :name_ru, :name_be, :category, :image, :image_preview)");
                $stmt->bindValue(':id', $product_id, SQLITE3_TEXT);
                $stmt->bindValue(':name_en', $product_names['en'], SQLITE3_TEXT);
                $stmt->bindValue(':name_uk', $product_names['uk'], SQLITE3_TEXT);
                $stmt->bindValue(':name_ru', $product_names['ru'], SQLITE3_TEXT);
                $stmt->bindValue(':name_be', $product_names['be'], SQLITE3_TEXT);
                $stmt->bindValue(':category', $_POST['category'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(':image', $image_name, SQLITE3_TEXT);
                $stmt->bindValue(':image_preview', $preview_name, $preview_name === null ? SQLITE3_NULL : SQLITE3_TEXT);
                $stmt->execute();
                $row = recipes_load_product_row($db, $product_id);
                if ($row) recipes_save_product_json($products_dir, $row);

                $response = [
                    'success' => true,
                    'message' => $translation_success ? $translations[$lang]['product_added_translated'] : $translations[$lang]['product_added']
                ];
            } catch (Exception $e) {
                $response = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($_POST['action'] === 'add_recipe' || $_POST['action'] === 'edit_recipe' ||
            $_POST['action'] === 'add_recipe_pretranslated' || $_POST['action'] === 'edit_recipe_pretranslated') {
            $is_pretranslated = strpos($_POST['action'], '_pretranslated') !== false;
            $base_action = str_replace('_pretranslated', '', $_POST['action']);
            $recipe_id = $base_action === 'edit_recipe' && isset($_POST['recipe_id']) ? $_POST['recipe_id'] : uniqid();
            $recipe_dir = "$recipes_dir/$recipe_id";
            if (!is_dir($recipe_dir)) mkdir($recipe_dir, 0777, true);

            $steps = json_decode($_POST['steps'], true);
            $source_lang = $_POST['lang'];

            // Если редактирование, загружаем существующий рецепт
            if ($base_action === 'edit_recipe') {
                $existing_recipe_file = "$recipe_dir/recipe.json";
                if (file_exists($existing_recipe_file)) {
                    $recipe = json_decode(file_get_contents($existing_recipe_file), true);
                } else {
                    $recipe = [];
                }
            } else {
                $recipe = [];
            }

            // Обновляем id и title
            $recipe['id'] = $recipe_id;
            
            if ($is_pretranslated && isset($_POST['translated_title'])) {
                // Use pretranslated title
                $translated_title = json_decode($_POST['translated_title'], true);
                $recipe['title'] = $translated_title;
                $title_translation_success = true;
            } else {
                // Translate title
                $recipe['title'] = [
                    'en' => $_POST['title'],
                    'uk' => $_POST['title'],
                    'ru' => $_POST['title'],
                    'be' => $_POST['title']
                ];

                // Перевод заголовка
                $title_translation_success = true;
                foreach (['en', 'uk', 'ru', 'be'] as $l) {
                    if ($l !== $source_lang) {
                        $translated = translate_text($_POST['title'], $source_lang, $l);
                        $recipe['title'][$l] = $translated;
                        if (strtolower($translated) === strtolower($_POST['title'])) {
                            $title_translation_success = false;
                        }
                    }
                }
            }

            // Перевод шагов
            $translated_steps = [];
            $steps_translation_success = true;
            foreach ($steps as $step) {
                $new_step = $step;
                if ($step['type'] === 'instruction') {
                    if ($is_pretranslated && is_array($step['content'])) {
                        // Use pretranslated content
                        $new_step['content'] = $step['content'];
                    } else {
                        // Translate step
                        $content_trans = [];
                        $step_all_same = true;
                        foreach (['en', 'uk', 'ru', 'be'] as $l) {
                            if ($l === $source_lang) {
                                $content_trans[$l] = $step['content'];
                            } else {
                                $translated = translate_text($step['content'], $source_lang, $l);
                                $content_trans[$l] = $translated;
                                if (strtolower($translated) === strtolower($step['content'])) {
                                    $step_all_same = false;
                                }
                            }
                        }
                        $new_step['content'] = $content_trans;
                        if ($step_all_same) {
                            $steps_translation_success = false;
                        }
                    }
                }
                $translated_steps[] = $new_step;
            }
            $recipe['steps'] = $translated_steps;

            // Проверка успешности перевода
            $translation_success = $title_translation_success && $steps_translation_success;

            $titles = array_map('strtolower', array_values($recipe['title']));
            $all_same_title = count(array_unique($titles)) === 1;

            // Обработка изображения
            $clear_recipe_image = isset($_POST['clear_recipe_image']) && $_POST['clear_recipe_image'] === '1';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $image_name = recipes_image_save_optimized_upload($_FILES['image'], $recipe_dir);
                if ($image_name === null) {
                    $response = [
                        'success' => false,
                        'message' => $translations[$lang]['image_url_not_image'] ?? 'Invalid image'
                    ];
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode($response, JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $recipe['image'] = $image_name;
            } elseif ($base_action === 'edit_recipe' && $clear_recipe_image) {
                $recipe['image'] = null;
            } elseif ($base_action === 'edit_recipe' && isset($recipe['image']) && file_exists("$recipe_dir/{$recipe['image']}")) {
                // Оставляем существующее изображение
            } else {
                $recipe['image'] = null;
            }
            
            // Сохранение рецепта в базу данных
            try {
                $db->exec("BEGIN TRANSACTION");
                
                // Удаляем старые шаги при редактировании
                if ($base_action === 'edit_recipe') {
                    $stmt = $db->prepare("DELETE FROM " . RECIPES_STEPS_TABLE . " WHERE recipe_id = :recipe_id");
                    $stmt->bindValue(':recipe_id', $recipe_id, SQLITE3_TEXT);
                    $stmt->execute();
                    
                    // Обновляем рецепт
                    $stmt = $db->prepare("UPDATE " . RECIPES_DB_TABLE_PREFIX . "data 
                        SET title_en = :title_en, title_uk = :title_uk, title_ru = :title_ru, title_be = :title_be, image = :image, updated_at = CURRENT_TIMESTAMP
                        WHERE id = :id");
                } else {
                    // Добавляем новый рецепт
                    $stmt = $db->prepare("INSERT INTO " . RECIPES_DB_TABLE_PREFIX . "data 
                        (id, title_en, title_uk, title_ru, title_be, image)
                        VALUES (:id, :title_en, :title_uk, :title_ru, :title_be, :image)");
                }
                
                $stmt->bindValue(':id', $recipe_id, SQLITE3_TEXT);
                $stmt->bindValue(':title_en', $recipe['title']['en'], SQLITE3_TEXT);
                $stmt->bindValue(':title_uk', $recipe['title']['uk'], SQLITE3_TEXT);
                $stmt->bindValue(':title_ru', $recipe['title']['ru'], SQLITE3_TEXT);
                $stmt->bindValue(':title_be', $recipe['title']['be'], SQLITE3_TEXT);
                $stmt->bindValue(':image', $recipe['image'], SQLITE3_TEXT);
                $stmt->execute();
                
                // Сохраняем шаги
                foreach ($translated_steps as $step_index => $step) {
                    $stmt = $db->prepare("
                        INSERT INTO " . RECIPES_STEPS_TABLE . " (
                            recipe_id, step_order, step_type, content,
                            quantity_value, quantity_unit, timer_hours, timer_minutes
                        ) VALUES (
                            :recipe_id, :step_order, :step_type, :content,
                            :quantity_value, :quantity_unit, :timer_hours, :timer_minutes
                        )
                    ");
                    
                    $content = '';
                    $quantity_value = null;
                    $quantity_unit = null;
                    $timer_hours = null;
                    $timer_minutes = null;
                    
                    if ($step['type'] === 'product') {
                        $content = $step['content'];
                        if (isset($step['quantity'])) {
                            $quantity_value = $step['quantity']['value'] ?? null;
                            $quantity_unit = $step['quantity']['unit'] ?? null;
                        }
                    } else if ($step['type'] === 'instruction') {
                        $content = is_array($step['content']) ? json_encode($step['content'], JSON_UNESCAPED_UNICODE) : $step['content'];
                    }
                    
                    if (isset($step['timer']) && is_array($step['timer'])) {
                        $timer_hours = $step['timer']['hours'] ?? null;
                        $timer_minutes = $step['timer']['minutes'] ?? null;
                    }
                    
                    $stmt->bindValue(':recipe_id', $recipe_id, SQLITE3_TEXT);
                    $stmt->bindValue(':step_order', $step_index, SQLITE3_INTEGER);
                    $stmt->bindValue(':step_type', $step['type'], SQLITE3_TEXT);
                    $stmt->bindValue(':content', $content, SQLITE3_TEXT);
                    $stmt->bindValue(':quantity_value', $quantity_value, $quantity_value === null ? SQLITE3_NULL : SQLITE3_FLOAT);
                    $stmt->bindValue(':quantity_unit', $quantity_unit, $quantity_unit === null ? SQLITE3_NULL : SQLITE3_TEXT);
                    $stmt->bindValue(':timer_hours', $timer_hours, $timer_hours === null ? SQLITE3_NULL : SQLITE3_INTEGER);
                    $stmt->bindValue(':timer_minutes', $timer_minutes, $timer_minutes === null ? SQLITE3_NULL : SQLITE3_INTEGER);
                    $stmt->execute();
                }
                
                $db->exec("COMMIT");
                
                $response = [
                    'success' => true,
                    'message' => $all_same_title ? $translations[$lang]['translation_failed'] : ($translation_success ?
                        $translations[$lang][$base_action === 'edit_recipe' ? 'recipe_updated_translated' : 'recipe_added_translated'] :
                        $translations[$lang][$base_action === 'edit_recipe' ? 'recipe_updated' : 'recipe_added'])
                ];
            } catch (Exception $e) {
                $db->exec("ROLLBACK");
                $response = [
                    'success' => false,
                    'message' => 'Database error: ' . $e->getMessage()
                ];
            }
        }
        
        if ($_POST['action'] === 'delete_recipe') {
            $recipe_id = $_POST['recipe_id'];
            
            try {
                // Удаляем из базы данных
                $stmt = $db->prepare("DELETE FROM " . RECIPES_STEPS_TABLE . " WHERE recipe_id = :recipe_id");
                $stmt->bindValue(':recipe_id', $recipe_id, SQLITE3_TEXT);
                $stmt->execute();
                
                $stmt = $db->prepare("DELETE FROM " . RECIPES_DB_TABLE_PREFIX . "data WHERE id = :recipe_id");
                $stmt->bindValue(':recipe_id', $recipe_id, SQLITE3_TEXT);
                $stmt->execute();
                
                // Удаляем файлы
                $recipe_dir = "$recipes_dir/$recipe_id";
                if (is_dir($recipe_dir)) {
                    array_map('unlink', glob("$recipe_dir/*"));
                    rmdir($recipe_dir);
                }
                
                // Сбрасываем состояние если это текущий рецепт
                if ($recipe_state['current_recipe'] === $recipe_id) {
                    $recipe_state['current_recipe'] = null;
                    $recipe_state['completed_steps'] = [];
                    try {
                        $db->exec("DELETE FROM " . RECIPES_DB_TABLE_PREFIX . "state WHERE key IN ('current_recipe', 'completed_steps')");
                    } catch (Exception $e) {
                        error_log("Failed to clear recipe state: " . $e->getMessage());
                    }
                }
                
                $response = ['success' => true, 'message' => $translations[$lang]['recipe_deleted']];
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
        }
        
        if ($_POST['action'] === 'delete_product') {
            $product_id = $_POST['product_id'];
            
            try {
                // Удаляем из базы данных
                $stmt = $db->prepare("DELETE FROM " . RECIPES_DB_TABLE_PREFIX . "products WHERE id = :product_id");
                $stmt->bindValue(':product_id', $product_id, SQLITE3_TEXT);
                $stmt->execute();
                
                // Удаляем файлы
                $product_dir = "$products_dir/$product_id";
                if (is_dir($product_dir)) {
                    array_map('unlink', glob("$product_dir/*"));
                    rmdir($product_dir);
                }
                
                $response = ['success' => true, 'message' => $translations[$lang]['product_deleted']];
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
        }
        
        if ($_POST['action'] === 'save_state') {
            $state = json_decode($_POST['state'], true);
            
            // Save state to database
            if ($db && $state) {
                try {
                    $db->exec("BEGIN TRANSACTION");
                    foreach ($state as $key => $value) {
                        $stmt = $db->prepare("
                            INSERT OR REPLACE INTO " . RECIPES_DB_TABLE_PREFIX . "state (key, value, updated_at)
                            VALUES (:key, :value, CURRENT_TIMESTAMP)
                        ");
                        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
                        $stmt->bindValue(':value', is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value, SQLITE3_TEXT);
                        $stmt->execute();
                    }
                    $db->exec("COMMIT");
                    $response = ['success' => true, 'message' => 'State saved'];
                } catch (Exception $e) {
                    $db->exec("ROLLBACK");
                    $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
                }
            } else {
                $response = ['success' => false, 'message' => 'Invalid state data or database connection'];
            }
        }
        
        if ($_POST['action'] === 'save_config') {
            $config_data = json_decode($_POST['config'], true);
            
            // Save config to database
            if ($db && $config_data) {
                try {
                    $db->exec("BEGIN TRANSACTION");
                    foreach ($config_data as $key => $value) {
                        $stmt = $db->prepare("
                            INSERT OR REPLACE INTO " . RECIPES_DB_TABLE_PREFIX . "config (key, value, updated_at)
                            VALUES (:key, :value, CURRENT_TIMESTAMP)
                        ");
                        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
                        $stmt->bindValue(':value', is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value, SQLITE3_TEXT);
                        $stmt->execute();
                    }
                    $db->exec("COMMIT");
                    $response = ['success' => true, 'message' => $translations[$lang]['config_saved']];
                } catch (Exception $e) {
                    $db->exec("ROLLBACK");
                    $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
                }
            } else {
                $response = ['success' => false, 'message' => 'Invalid config data or database connection'];
            }
        }
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
