<?php
declare(strict_types=1);

function recipes_ai_stream_headers(): void
{
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-store');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
}

function recipes_ai_stream_event(string $event, array $data): void
{
    echo 'event: ' . $event . "\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}

function recipes_ai_search_products_ranked($db, string $query, int $limit = 8): array
{
    $merged = [];
    $queries = [trim($query)];
    $words = preg_split('/\s+/u', recipes_normalize_product_name($query));
    foreach ($words as $w) {
        if (mb_strlen($w, 'UTF-8') >= 3) {
            $queries[] = $w;
        }
    }
    $queries = array_values(array_unique(array_filter($queries)));
    foreach ($queries as $q) {
        foreach (recipes_ai_search_products_sqlite($db, $q, $limit) as $hit) {
            $merged[$hit['id']] = $hit;
        }
    }
    return array_values($merged);
}

function recipes_ai_agent_system_prompt(string $lang): string
{
    $cats = implode(', ', RECIPES_AI_CATEGORIES);
    $units = implode(', ', RECIPES_AI_UNITS);
    return "You are a recipe assistant. User language: {$lang}.\n"
        . "Product categories: {$cats}. Units: {$units}.\n\n"
        . "Workflow:\n"
        . "1. Parse the recipe request.\n"
        . "2. For EACH ingredient call search_products — pick the best matching product id from results (check all language names).\n"
        . "3. If search returns no suitable match, omit product_id and use product_name only.\n"
        . "4. Call submit_recipe when ready with title, recipe_image_query (English, specific dish photo), ingredients and instructions.\n"
        . "Never invent product_id. Never auto-guess ids without search_products.\n"
        . "Title rules: write a natural dish name ONLY in the user language. Never add translations in parentheses, alternate names, or \"X-style\" prefixes (bad: \"Russian-style Meat Patties (Kotleti)\", good: \"Котлеты\" or \"Meat patties\").\n"
        . "Instructions: provide exactly 2 or 3 cooking steps — combine related actions, never more than 3.\n"
        . "recipe_image_query: English description of the FINISHED dish for photo search (e.g. \"fried meat cutlets kotleti on plate\"), NOT burger/hamburger.";
}

function recipes_ai_agent_tools(): array
{
    $cats = implode(', ', RECIPES_AI_CATEGORIES);
    $units = implode('|', RECIPES_AI_UNITS);
    return [
        [
            'type' => 'function',
            'function' => [
                'name' => 'search_products',
                'description' => 'Search products in database by name. Returns matching products with id and names in all languages.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'limit' => ['type' => 'integer'],
                    ],
                    'required' => ['query'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'submit_recipe',
                'description' => 'Submit final recipe after all ingredients were searched.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'recipe_image_query' => ['type' => 'string'],
                        'ingredients' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'product_id' => ['type' => 'string'],
                                    'product_name' => ['type' => 'string'],
                                    'quantity_value' => ['type' => 'number'],
                                    'quantity_unit' => ['type' => 'string', 'enum' => RECIPES_AI_UNITS],
                                    'category' => ['type' => 'string'],
                                ],
                            ],
                        ],
                        'instructions' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'content' => ['type' => 'string'],
                                    'timer_hours' => ['type' => 'integer'],
                                    'timer_minutes' => ['type' => 'integer'],
                                ],
                            ],
                        ],
                    ],
                    'required' => ['title', 'ingredients', 'instructions'],
                ],
            ],
        ],
    ];
}

function recipes_ai_submit_args_to_json(array $args): array
{
    $ingredients = [];
    foreach ($args['ingredients'] ?? [] as $ing) {
        if (!is_array($ing)) {
            continue;
        }
        $ingredients[] = [
            'name' => (string)($ing['product_name'] ?? ''),
            'product_id' => (string)($ing['product_id'] ?? ''),
            'quantity_value' => (float)($ing['quantity_value'] ?? 100),
            'quantity_unit' => (string)($ing['quantity_unit'] ?? 'grams'),
            'category' => (string)($ing['category'] ?? 'vegetables'),
        ];
    }
    $instructions = [];
    foreach ($args['instructions'] ?? [] as $instr) {
        if (!is_array($instr)) {
            continue;
        }
        $instructions[] = [
            'content' => (string)($instr['content'] ?? ''),
            'timer_hours' => (int)($instr['timer_hours'] ?? 0),
            'timer_minutes' => (int)($instr['timer_minutes'] ?? 0),
        ];
    }
    return [
        'title' => trim((string)($args['title'] ?? '')),
        'recipe_image_query' => trim((string)($args['recipe_image_query'] ?? $args['title'] ?? '')),
        'ingredients' => $ingredients,
        'instructions' => $instructions,
    ];
}

function recipes_ai_build_steps_from_ingredients_json(array $json): array
{
    $steps = [];
    foreach ($json['ingredients'] ?? [] as $ing) {
        if (!is_array($ing)) {
            continue;
        }
        $unit = (string)($ing['quantity_unit'] ?? 'grams');
        if (!in_array($unit, RECIPES_AI_UNITS, true)) {
            $unit = 'grams';
        }
        $pid = (string)($ing['product_id'] ?? '');
        $steps[] = [
            'type' => 'product',
            'product_id' => $pid,
            'product_name' => (string)($ing['name'] ?? $ing['product_name'] ?? ''),
            'category' => (string)($ing['category'] ?? 'vegetables'),
            'quantity_value' => (float)($ing['quantity_value'] ?? 100),
            'quantity_unit' => $unit,
        ];
    }
    foreach ($json['instructions'] ?? [] as $instr) {
        if (!is_array($instr)) {
            continue;
        }
        $steps[] = [
            'type' => 'instruction',
            'content' => (string)($instr['content'] ?? ''),
            'timer_hours' => (int)($instr['timer_hours'] ?? 0),
            'timer_minutes' => (int)($instr['timer_minutes'] ?? 0),
        ];
    }
    return $steps;
}

function recipes_ai_match_draft_products(array $draft, $db, string $lang, ?callable $emit = null): array
{
    $pending = [];
    $resolved = [];
    $pIdx = 0;
    foreach ($draft['steps'] ?? [] as $step) {
        if (($step['type'] ?? '') !== 'product') {
            $resolved[] = $step;
            continue;
        }
        $pid = (string)($step['product_id'] ?? $step['content'] ?? '');
        $name = (string)($step['product_name'] ?? '');
        $qty = $step['quantity'] ?? ['value' => 100, 'unit' => 'grams'];
        if ($pid !== '' && recipes_load_product_row($db, $pid)) {
            $row = recipes_load_product_row($db, $pid);
            $step['product_id'] = $pid;
            $step['content'] = $pid;
            $step['product_name'] = $row['name_' . $lang] ?? $row['name_en'];
            $resolved[] = $step;
            if ($emit) {
                $emit('activity', ['kind' => 'found', 'name' => $step['product_name']]);
            }
            continue;
        }
        $found = $name !== '' ? recipes_ai_find_product_exact($db, $name) : null;
        if ($found) {
            $step['product_id'] = $found['id'];
            $step['content'] = $found['id'];
            $step['product_name'] = $found['name'][$lang] ?? $found['name']['en'];
            $resolved[] = $step;
            if ($emit) {
                $emit('activity', ['kind' => 'found', 'name' => $step['product_name']]);
            }
            continue;
        }
        $candidates = $name !== '' ? recipes_ai_search_products_ranked($db, $name, 6) : [];
        $key = 'p' . $pIdx++;
        $pending[] = [
            'key' => $key,
            'name' => $name,
            'category' => (string)($step['category'] ?? 'vegetables'),
            'quantity' => $qty,
            'candidates' => array_map(static fn($c) => [
                'id' => $c['id'],
                'name' => $c['name'][$lang] ?? $c['name']['en'],
                'names' => $c['name'],
                'image' => $c['image'] ?? null,
                'image_preview' => $c['image_preview'] ?? null,
            ], $candidates),
        ];
        $step['_pending_key'] = $key;
        $step['pending'] = true;
        $resolved[] = $step;
        if ($emit) {
            $emit('activity', ['kind' => 'pending', 'name' => $name]);
        }
    }
    $draft['steps'] = $resolved;
    return ['draft' => $draft, 'pending_products' => $pending];
}

function recipes_ai_find_product_exact($db, string $name): ?array
{
    $hits = recipes_ai_search_products_ranked($db, $name, 10);
    $target = recipes_normalize_product_name($name);
    foreach ($hits as $hit) {
        foreach ($hit['name'] as $n) {
            if (recipes_normalize_product_name((string)$n) === $target) {
                return $hit;
            }
        }
    }
    return null;
}

function recipes_ai_apply_product_decisions(array $draft, array $decisions, $db, string $products_dir, string $lang): array
{
    $map = [];
    foreach ($decisions as $d) {
        if (!is_array($d) || empty($d['key'])) {
            continue;
        }
        $map[(string)$d['key']] = $d;
    }
    $out = [];
    foreach ($draft['steps'] ?? [] as $step) {
        if (($step['type'] ?? '') !== 'product') {
            $out[] = $step;
            continue;
        }
        $key = (string)($step['_pending_key'] ?? '');
        if ($key === '' || empty($step['pending'])) {
            $out[] = $step;
            continue;
        }
        $dec = $map[$key] ?? null;
        if (!$dec) {
            continue;
        }
        $action = (string)($dec['action'] ?? 'skip');
        if ($action === 'skip') {
            continue;
        }
        if ($action === 'link' && !empty($dec['product_id'])) {
            $row = recipes_load_product_row($db, (string)$dec['product_id']);
            if ($row) {
                $step['product_id'] = $row['id'];
                $step['content'] = $row['id'];
                $step['product_name'] = $row['name_' . $lang] ?? $row['name_en'];
                unset($step['pending'], $step['_pending_key']);
                $out[] = $step;
            }
            continue;
        }
        if ($action === 'create') {
            $pname = trim((string)($dec['name'] ?? $step['product_name'] ?? ''));
            $cat = (string)($dec['category'] ?? $step['category'] ?? 'vegetables');
            if ($pname === '') {
                continue;
            }
            $imgUrl = !empty($dec['image_url']) ? trim((string)$dec['image_url']) : null;
            $created = recipes_ai_create_product($db, $products_dir, $pname, $cat, $lang, $imgUrl, recipes_ai_product_image_query($pname, $lang));
            if (!empty($created['ok']) && !empty($created['id'])) {
                $step['product_id'] = $created['id'];
                $step['content'] = $created['id'];
                $step['product_name'] = $created['name'][$lang] ?? $pname;
                unset($step['pending'], $step['_pending_key']);
                $out[] = $step;
            }
        }
    }
    $draft['steps'] = $out;
    return $draft;
}

function recipes_ai_run_agent(string $prompt, string $lang, ?string $model, ?array $currentDraft = null, string $products_dir = 'products', ?callable $emit = null): array
{
    $emitFn = $emit ?? static fn() => null;
    $config = cloud_ai_load_config();
    $model = $model && in_array($model, $config['models'], true) ? $model : $config['default_model'];
    $db = getDB();
    $tools = recipes_ai_agent_tools();
    $messages = [
        ['role' => 'system', 'content' => recipes_ai_agent_system_prompt($lang)],
        ['role' => 'user', 'content' => $prompt],
    ];
    if ($currentDraft) {
        $messages[1]['content'] = "Current recipe:\n" . json_encode($currentDraft, JSON_UNESCAPED_UNICODE) . "\n\nChange request:\n" . $prompt;
    }
    $submitArgs = null;
    $emitFn('status', ['text' => 'ai_thinking']);
    for ($i = 0; $i < RECIPES_AI_MAX_ITERATIONS; $i++) {
        $response = recipes_ai_chat($config, $model, $messages, $tools);
        $choice = $response['choices'][0]['message'] ?? null;
        if (!is_array($choice)) {
            break;
        }
        $toolCalls = $choice['tool_calls'] ?? null;
        if (is_array($toolCalls) && $toolCalls !== []) {
            $messages[] = $choice;
            foreach ($toolCalls as $tc) {
                $fn = (string)($tc['function']['name'] ?? '');
                $args = json_decode($tc['function']['arguments'] ?? '{}', true);
                if (!is_array($args)) {
                    $args = [];
                }
                if ($fn === 'search_products') {
                    $query = (string)($args['query'] ?? '');
                    $hits = recipes_ai_search_products_ranked($db, $query, (int)($args['limit'] ?? 8));
                    $emitFn('activity', ['kind' => 'search', 'query' => $query, 'count' => count($hits)]);
                    $target = recipes_normalize_product_name($query);
                    foreach ($hits as $hit) {
                        foreach ($hit['name'] as $n) {
                            if (recipes_normalize_product_name((string)$n) === $target) {
                                $emitFn('activity', [
                                    'kind' => 'found',
                                    'name' => $hit['name'][$lang] ?? $hit['name']['en'] ?? $query,
                                ]);
                                break 2;
                            }
                        }
                    }
                    $result = ['ok' => true, 'products' => $hits];
                } elseif ($fn === 'submit_recipe') {
                    $submitArgs = $args;
                    $json = recipes_ai_submit_args_to_json($submitArgs);
                    $emitFn('activity', ['kind' => 'recipe', 'title' => $json['title']]);
                    $emitFn('title', ['title' => $json['title']]);
                    $emitFn('recipe_image_query', ['query' => $json['recipe_image_query'] ?? $json['title']]);
                    $provSteps = recipes_ai_build_steps_from_ingredients_json($json);
                    foreach ($provSteps as $idx => $step) {
                        $emitFn('step', ['index' => $idx, 'step' => $step, 'provisional' => true]);
                    }
                    $result = ['ok' => true, 'submitted' => true];
                } else {
                    $result = ['ok' => false, 'error' => 'unknown tool'];
                }
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $tc['id'] ?? ('call_' . uniqid()),
                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }
            if ($submitArgs !== null) {
                break;
            }
            continue;
        }
        $text = recipes_ai_message_text($choice);
        if ($text !== '') {
            $parsed = recipes_ai_extract_json($text);
            if ($parsed && !empty($parsed['title'])) {
                $submitArgs = [
                    'title' => $parsed['title'],
                    'recipe_image_query' => $parsed['recipe_image_query'] ?? $parsed['title'],
                    'ingredients' => $parsed['ingredients'] ?? [],
                    'instructions' => $parsed['instructions'] ?? [],
                ];
                break;
            }
        }
        break;
    }
    if ($submitArgs === null || trim((string)($submitArgs['title'] ?? '')) === '') {
        return ['success' => false, 'message' => 'AI could not produce a recipe', 'model' => $model];
    }
    $json = recipes_ai_submit_args_to_json($submitArgs);
    $raw = [
        'title' => $json['title'],
        'recipe_image_query' => $json['recipe_image_query'],
        'steps' => recipes_ai_build_steps_from_ingredients_json($json),
    ];
    $draft = recipes_ai_normalize_draft($raw, $db, $lang);
    $matched = recipes_ai_match_draft_products($draft, $db, $lang, $emitFn);
    $draft = $matched['draft'];
    $pending = $matched['pending_products'];
    $emitFn('steps_clear', []);
    foreach ($draft['steps'] ?? [] as $idx => $step) {
        $emitFn('step', ['index' => $idx, 'step' => $step]);
    }
    if ($pending !== []) {
        $emitFn('pending_products', ['pending_products' => $pending]);
    }
    $emitFn('done', [
        'draft' => $draft,
        'pending_products' => $pending,
        'model' => $model,
    ]);
    return [
        'success' => true,
        'draft' => $draft,
        'pending_products' => $pending,
        'model' => $model,
    ];
}

function recipes_ai_run_agent_stream(string $prompt, string $lang, ?string $model, ?array $currentDraft, string $products_dir): void
{
    recipes_ai_stream_headers();
    try {
        recipes_ai_run_agent($prompt, $lang, $model, $currentDraft, $products_dir, static function (string $event, array $data) {
            recipes_ai_stream_event($event, $data);
        });
    } catch (Throwable $e) {
        recipes_ai_stream_event('error', ['message' => $e->getMessage()]);
    }
    recipes_ai_stream_event('end', []);
}
