<?php
declare(strict_types=1);

function cloud_ai_prompt_file(string $name): string
{
    $path = __DIR__ . '/ai_prompts/' . $name;
    if (!is_file($path)) {
        return '';
    }
    return trim((string)file_get_contents($path));
}

function cloud_ai_agent_file_context(?string $openPath): string
{
    $ext = strtolower(pathinfo((string)$openPath, PATHINFO_EXTENSION));
    if (in_array($ext, ['tex', 'latex'], true)) {
        return 'latex';
    }
    if (in_array($ext, ['md', 'markdown'], true)) {
        return 'markdown';
    }
    if (in_array($ext, ['py', 'c', 'cpp', 'cc', 'cxx', 'java', 'm', 'h', 'hpp'], true)) {
        return 'code';
    }
    return 'general';
}

function cloud_ai_chat_system_prompt(?string $openPath = null): string
{
    $parts = [
        cloud_ai_prompt_file('agent_chat_readonly.txt'),
        cloud_ai_prompt_file('agent_context.txt'),
    ];
    $parts = array_filter($parts);
    return implode("\n\n", $parts) ?: 'You are a helpful read-only assistant. Use tools to inspect files; never modify the workspace.';
}

function cloud_ai_agent_system_prompt(?string $openPath = null): string
{
    $parts = [
        cloud_ai_prompt_file('agent_behavior.txt'),
        cloud_ai_prompt_file('agent_editing.txt'),
        cloud_ai_prompt_file('agent_context.txt'),
        cloud_ai_prompt_file('agent_ask_user.txt'),
        cloud_ai_prompt_file('agent_markdown.txt'),
        cloud_ai_prompt_file('agent_latex.txt'),
        cloud_ai_prompt_file('agent_export.txt'),
        cloud_ai_prompt_file('agent_compile_latex.txt'),
        cloud_ai_prompt_file('agent_compile_code.txt'),
        cloud_ai_prompt_file('agent_security.txt'),
        cloud_ai_prompt_file('agent_speech_tools.txt'),
    ];
    $parts = array_filter($parts);
    return implode("\n\n", $parts) ?: 'You are a coding agent. Use tools and stay on the user task.';
}

function cloud_ai_agent_prompts_payload(?string $openPath = null): array
{
    $ctx = cloud_ai_agent_file_context($openPath);
    return [
        'system' => cloud_ai_agent_system_prompt($openPath),
        'context' => $ctx,
        'open_path' => $openPath ?? '',
        'behavior' => cloud_ai_prompt_file('agent_behavior.txt'),
        'editing' => cloud_ai_prompt_file('agent_editing.txt'),
        'context_budget' => cloud_ai_prompt_file('agent_context.txt'),
        'security' => $ctx === 'code' ? cloud_ai_prompt_file('agent_security.txt') : '',
        'compile' => $ctx === 'latex'
            ? cloud_ai_prompt_file('agent_compile_latex.txt')
            : ($ctx === 'code' ? cloud_ai_prompt_file('agent_compile_code.txt') : ''),
        'speech' => cloud_ai_prompt_file('agent_speech_tools.txt'),
    ];
}
