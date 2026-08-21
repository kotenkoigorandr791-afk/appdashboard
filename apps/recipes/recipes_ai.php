<?php
declare(strict_types=1);
$__d = __DIR__;
for ($__n = 0; $__n < 16; $__n++) {
    if (is_file($__d . '/smart_log_boot.php')) {
        require_once $__d . '/smart_log_boot.php';
        break;
    }
    $__p = dirname($__d);
    if ($__p === $__d) {
        break;
    }
    $__d = $__p;
}
require_once __DIR__ . '/../../lib/app_session.php';
app_session_start();
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/schema.php';
require_once __DIR__ . '/core/image.php';
require_once __DIR__ . '/models/Recipe.php';
require_once __DIR__ . '/core/ai.php';

recipes_ai_require_role(1);

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}
$action = (string)($input['action'] ?? $_GET['action'] ?? '');

if ($action === 'config') {
    recipes_ai_json_out(['success' => true, 'config' => recipes_ai_public_config()]);
}

$lang = isset($input['lang']) && in_array($input['lang'], ['en', 'uk', 'ru', 'be'], true)
    ? $input['lang'] : 'en';
$model = isset($input['model']) ? trim((string)$input['model']) : null;
$products_dir = 'products';

if ($action === 'generate_stream' || $action === 'edit_stream') {
    $prompt = trim((string)($input['prompt'] ?? ''));
    if ($action === 'edit_stream') {
        $instruction = trim((string)($input['instruction'] ?? ''));
        $draft = $input['draft'] ?? null;
        if ($instruction === '' || !is_array($draft)) {
            recipes_ai_json_out(['success' => false, 'message' => 'instruction and draft required'], 400);
        }
        $prompt = "Edit the recipe:\n" . $instruction;
        $currentDraft = $draft;
    } else {
        $currentDraft = null;
    }
    if ($prompt === '') {
        recipes_ai_json_out(['success' => false, 'message' => 'prompt required'], 400);
    }
    recipes_ai_run_agent_stream($prompt, $lang, $model, $currentDraft, $products_dir);
}

if ($action === 'generate' || $action === 'edit') {
    $prompt = trim((string)($input['prompt'] ?? ''));
    $currentDraft = null;
    if ($action === 'edit') {
        $instruction = trim((string)($input['instruction'] ?? ''));
        $currentDraft = $input['draft'] ?? null;
        if ($instruction === '' || !is_array($currentDraft)) {
            recipes_ai_json_out(['success' => false, 'message' => 'instruction and draft required'], 400);
        }
        $prompt = "Edit the recipe:\n" . $instruction;
    }
    if ($prompt === '') {
        recipes_ai_json_out(['success' => false, 'message' => 'prompt required'], 400);
    }
    try {
        $result = recipes_ai_run_agent($prompt, $lang, $model, $currentDraft, $products_dir);
        if ($result['success']) {
            $result['products'] = getAllProductsFromDB();
        }
        recipes_ai_json_out($result);
    } catch (Throwable $e) {
        recipes_ai_json_out(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($action === 'confirm_products') {
    $draft = $input['draft'] ?? null;
    $decisions = $input['decisions'] ?? null;
    if (!is_array($draft) || !is_array($decisions)) {
        recipes_ai_json_out(['success' => false, 'message' => 'draft and decisions required'], 400);
    }
    try {
        $draft = recipes_ai_apply_product_decisions($draft, $decisions, getDB(), $products_dir, $lang);
        $matched = recipes_ai_match_draft_products($draft, getDB(), $lang);
        recipes_ai_json_out([
            'success' => true,
            'draft' => $matched['draft'],
            'pending_products' => $matched['pending_products'],
            'products' => getAllProductsFromDB(),
        ]);
    } catch (Throwable $e) {
        recipes_ai_json_out(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($action === 'save' || $action === 'save_stream') {
    $draft = $input['draft'] ?? null;
    if (!is_array($draft)) {
        recipes_ai_json_out(['success' => false, 'message' => 'draft required'], 400);
    }
    foreach ($draft['steps'] ?? [] as $step) {
        if (($step['type'] ?? '') === 'product' && !empty($step['pending'])) {
            recipes_ai_json_out(['success' => false, 'message' => 'pending_products'], 400);
        }
    }
    if ($action === 'save_stream') {
        recipes_ai_stream_headers();
        try {
            $result = recipes_ai_save_draft($draft, $lang, 'recipes', static function (string $event, array $data) {
                recipes_ai_stream_event($event, $data);
            });
            recipes_ai_stream_event('done', $result);
        } catch (Throwable $e) {
            recipes_ai_stream_event('error', ['message' => $e->getMessage()]);
        }
        recipes_ai_stream_event('end', []);
        exit;
    }
    try {
        $result = recipes_ai_save_draft($draft, $lang, 'recipes');
        recipes_ai_json_out($result);
    } catch (Throwable $e) {
        recipes_ai_json_out(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($action === 'image_options') {
    $title = trim((string)($input['title'] ?? ''));
    $query = trim((string)($input['image_query'] ?? $title));
    $kind = (string)($input['kind'] ?? 'recipe');
    if ($title === '' && $query === '') {
        recipes_ai_json_out(['success' => false, 'message' => 'title required'], 400);
    }
    try {
        $options = recipes_ai_gather_image_options($title, $query, $lang, $kind === 'product' ? 'product' : 'recipe', 4);
        if ($options === []) {
            recipes_ai_json_out(['success' => false, 'message' => 'no image found']);
        }
        recipes_ai_json_out(['success' => true, 'options' => $options]);
    } catch (Throwable $e) {
        recipes_ai_json_out(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($action === 'verify_image') {
    $title = trim((string)($input['title'] ?? ''));
    $query = trim((string)($input['image_query'] ?? $title));
    if ($title === '') {
        recipes_ai_json_out(['success' => false, 'message' => 'title required'], 400);
    }
    try {
        $options = recipes_ai_gather_image_options($title, $query, $lang, 'recipe', 4);
        if ($options === []) {
            recipes_ai_json_out(['success' => false, 'message' => 'no image found']);
        }
        recipes_ai_json_out(['success' => true, 'options' => $options]);
    } catch (Throwable $e) {
        recipes_ai_json_out(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

recipes_ai_json_out(['success' => false, 'message' => 'unknown action'], 400);
