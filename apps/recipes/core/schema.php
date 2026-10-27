<?php
require_once __DIR__ . '/seed.php';

function recipes_ensure_products_image_preview_column($db) {
    if (!$db) return;
    try {
        $columns = $db->query("SELECT column_name AS name FROM information_schema.columns WHERE table_schema='public' AND table_name='" . RECIPES_DB_TABLE_PREFIX . "products'");
        $has_image_preview = false;
        while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
            if ($col['name'] === 'image_preview') $has_image_preview = true;
        }
        if (!$has_image_preview) {
            $db->exec("ALTER TABLE " . RECIPES_DB_TABLE_PREFIX . "products ADD COLUMN image_preview TEXT");
        }
    } catch (Exception $e) {
        error_log('recipes image_preview column: ' . $e->getMessage());
    }
}

function recipes_ensure_steps_table($db) {
    if (!$db) return;
    try {
        $legacy_table_exists = $db->querySingle("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public' AND table_name='recipe_steps'") > 0;
        $new_table_exists = $db->querySingle("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public' AND table_name='" . RECIPES_STEPS_TABLE . "'") > 0;

        if ($legacy_table_exists && !$new_table_exists) {
            $db->exec("ALTER TABLE recipe_steps RENAME TO " . RECIPES_STEPS_TABLE);
            $db->exec("DROP INDEX IF EXISTS idx_recipe_steps_recipe_id");
            $db->exec("DROP INDEX IF EXISTS idx_recipe_steps_step_order");
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS " . RECIPES_STEPS_TABLE . " (
                id BIGSERIAL PRIMARY KEY,
                recipe_id TEXT NOT NULL,
                step_order INTEGER NOT NULL,
                step_type TEXT NOT NULL CHECK(step_type IN ('product', 'instruction')),
                content TEXT NOT NULL,
                quantity_value REAL,
                quantity_unit TEXT,
                timer_hours INTEGER,
                timer_minutes INTEGER,
                FOREIGN KEY (recipe_id) REFERENCES " . RECIPES_DB_TABLE_PREFIX . "data(id) ON DELETE CASCADE
            )
        ");

        $db->exec("CREATE INDEX IF NOT EXISTS idx_" . RECIPES_STEPS_TABLE . "_recipe_id ON " . RECIPES_STEPS_TABLE . "(recipe_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_" . RECIPES_STEPS_TABLE . "_step_order ON " . RECIPES_STEPS_TABLE . "(recipe_id, step_order)");
    } catch (Exception $e) {
        error_log('recipes steps table migration: ' . $e->getMessage());
    }
}

// Migration function to transfer data from JSON to SQLite
function recipes_migrate_json_to_sqlite() {
    // The schema migration and the seed are idempotent but not cheap: they run
    // CREATE TABLE IF NOT EXISTS, CREATE INDEX IF NOT EXISTS, ALTER TABLE and
    // several information_schema lookups. They used to run on every single
    // HTTP request because apps/recipes/core/image.php calls this function at
    // file scope, which made the recipes API issue ~20 DDL round trips to
    // PostgreSQL per request (measured: 2880 information_schema lookups and
    // 960 CREATE TABLE / CREATE INDEX statements for a 20 second load test).
    //
    // app_schema_once() runs the body at most once per schema version (the
    // stamp includes the mtime and size of the declaring file) and caches the
    // result in Redis for 24 hours, so a schema change still gets picked up
    // immediately while normal traffic no longer touches the catalog.
    app_schema_once('recipes_schema', __FILE__, static function () {
        $db = getDB();
        if (!$db) return;
        recipes_migrate_json_to_sqlite_impl($db);
        // Seed the default products and recipes so a fresh installation is
        // usable right away. The seed only fills tables that are still empty.
        recipes_seed_ensure($db);
    });
    return true;
}

function recipes_migrate_json_to_sqlite_impl($db) {
    // Check if migration already completed
    $tables = $db->query("SELECT table_name AS name FROM information_schema.tables WHERE table_schema='public' AND table_name LIKE '" . RECIPES_DB_TABLE_PREFIX . "%'");
    $table_count = 0;
    while ($row = $tables->fetch(PDO::FETCH_ASSOC)) {
        $table_count++;
    }
    
    if ($table_count > 0) {
        // Check if category column exists, add if not
        $columns = $db->query("SELECT column_name AS name FROM information_schema.columns WHERE table_schema='public' AND table_name='" . RECIPES_DB_TABLE_PREFIX . "products'");
        $has_category = false;
        while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
            if ($col['name'] === 'category') $has_category = true;
        }
        if (!$has_category) {
            $db->exec("ALTER TABLE " . RECIPES_DB_TABLE_PREFIX . "products ADD COLUMN category TEXT DEFAULT ''");
        }
        recipes_ensure_products_image_preview_column($db);
        recipes_ensure_steps_table($db);
        return true;
    }
    
    // Create schema
    $db->exec("
        CREATE TABLE IF NOT EXISTS " . RECIPES_DB_TABLE_PREFIX . "products (
            id TEXT PRIMARY KEY,
            name_en TEXT NOT NULL DEFAULT '',
            name_uk TEXT NOT NULL DEFAULT '',
            name_ru TEXT NOT NULL DEFAULT '',
            name_be TEXT NOT NULL DEFAULT '',
            category TEXT DEFAULT '',
            image TEXT,
            image_preview TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS " . RECIPES_DB_TABLE_PREFIX . "data (
            id TEXT PRIMARY KEY,
            title_en TEXT NOT NULL DEFAULT '',
            title_uk TEXT NOT NULL DEFAULT '',
            title_ru TEXT NOT NULL DEFAULT '',
            title_be TEXT NOT NULL DEFAULT '',
            image TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    recipes_ensure_steps_table($db);
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS " . RECIPES_DB_TABLE_PREFIX . "config (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS " . RECIPES_DB_TABLE_PREFIX . "state (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Migrate products. Use absolute paths: the relative "products" folder
    // only resolves when the working directory happens to be the app root,
    // which is not the case when this runs from the CLI (first deployment).
    $products_dir = RECIPES_PRODUCTS_DIR;
    foreach (glob($products_dir . '/*/product.json') as $file) {
        $product_data = json_decode(file_get_contents($file), true);
        if ($product_data && isset($product_data['id'])) {
            $stmt = $db->prepare("
                INSERT INTO " . RECIPES_DB_TABLE_PREFIX . "products 
                (id, name_en, name_uk, name_ru, name_be, category, image, image_preview)
                VALUES (:id, :name_en, :name_uk, :name_ru, :name_be, :category, :image, :image_preview)
            ");
            $stmt->bindValue(':id', $product_data['id'], PDO::PARAM_STR);
            $stmt->bindValue(':name_en', $product_data['name']['en'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':name_uk', $product_data['name']['uk'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':name_ru', $product_data['name']['ru'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':name_be', $product_data['name']['be'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':category', $product_data['category'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':image', $product_data['image'] ?? null, PDO::PARAM_STR);
            $ip = $product_data['image_preview'] ?? null;
            $stmt->bindValue(':image_preview', $ip, $ip === null || $ip === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->execute();
        }
    }
    
    // Migrate recipes
    $recipes_dir = RECIPES_RECIPES_DIR;
    foreach (glob($recipes_dir . '/*/recipe.json') as $file) {
        $recipe_data = json_decode(file_get_contents($file), true);
        if ($recipe_data && isset($recipe_data['id'])) {
            $stmt = $db->prepare("
                INSERT INTO " . RECIPES_DB_TABLE_PREFIX . "data 
                (id, title_en, title_uk, title_ru, title_be, image)
                VALUES (:id, :title_en, :title_uk, :title_ru, :title_be, :image)
            ");
            $stmt->bindValue(':id', $recipe_data['id'], PDO::PARAM_STR);
            $stmt->bindValue(':title_en', $recipe_data['title']['en'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':title_uk', $recipe_data['title']['uk'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':title_ru', $recipe_data['title']['ru'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':title_be', $recipe_data['title']['be'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':image', $recipe_data['image'] ?? null, PDO::PARAM_STR);
            $stmt->execute();
            
            // Migrate steps
            if (isset($recipe_data['steps']) && is_array($recipe_data['steps'])) {
                foreach ($recipe_data['steps'] as $step_index => $step) {
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
                        $content = json_encode($step['content'], JSON_UNESCAPED_UNICODE);
                    }
                    
                    if (isset($step['timer']) && is_array($step['timer'])) {
                        $timer_hours = $step['timer']['hours'] ?? null;
                        $timer_minutes = $step['timer']['minutes'] ?? null;
                    }
                    
                    $stmt->bindValue(':recipe_id', $recipe_data['id'], PDO::PARAM_STR);
                    $stmt->bindValue(':step_order', $step_index, PDO::PARAM_INT);
                    $stmt->bindValue(':step_type', $step['type'], PDO::PARAM_STR);
                    $stmt->bindValue(':content', $content, PDO::PARAM_STR);
                    $stmt->bindValue(':quantity_value', $quantity_value, $quantity_value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                    $stmt->bindValue(':quantity_unit', $quantity_unit, $quantity_unit === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                    $stmt->bindValue(':timer_hours', $timer_hours, $timer_hours === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                    $stmt->bindValue(':timer_minutes', $timer_minutes, $timer_minutes === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                    $stmt->execute();
                }
            }
        }
    }
    
    // Migrate config
    if (file_exists('config.json')) {
        $config = json_decode(file_get_contents('config.json'), true);
        foreach ($config as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO " . RECIPES_DB_TABLE_PREFIX . "config (key, value)
                VALUES (:key, :value)
            ");
            $stmt->bindValue(':key', $key, PDO::PARAM_STR);
            $stmt->bindValue(':value', is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value, PDO::PARAM_STR);
            $stmt->execute();
        }
    }
    
    // Migrate state
    if (file_exists('recipe_state.json')) {
        $state = json_decode(file_get_contents('recipe_state.json'), true);
        foreach ($state as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO " . RECIPES_DB_TABLE_PREFIX . "state (key, value)
                VALUES (:key, :value)
            ");
            $stmt->bindValue(':key', $key, PDO::PARAM_STR);
            $stmt->bindValue(':value', is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value, PDO::PARAM_STR);
            $stmt->execute();
        }
    }
    
    return true;
}

const RECIPES_PRODUCT_PREVIEW_MAX_SIDE = 384;
const RECIPES_PRODUCT_PREVIEW_FILE = 'preview.webp';
