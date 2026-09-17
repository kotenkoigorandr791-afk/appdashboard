<?php
require_once dirname(__DIR__, 3) . '/db.php';
require_once dirname(__DIR__) . '/includes/ai_config_store.php';
cloud_ai_save_config([
    'api_endpoint' => rtrim((getenv('AI_PUBLIC_URL') ?: ''), '/') . '/v1',
    'api_key' => 'sk-bf7d91bad47b790c-a401e9-38706651',
    'default_model' => 'nvidia/openai/gpt-oss-120b',
    'models' => [
        'nvidia/qwen/qwen3.5-397b-a17b',
        'nvidia/openai/gpt-oss-120b',
        'nvidia/nvidia/nemotron-3-super-120b-a12b',
        'kc/anthropic/claude-sonnet-4.6',
    ],
]);
echo json_encode(cloud_ai_load_config(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
