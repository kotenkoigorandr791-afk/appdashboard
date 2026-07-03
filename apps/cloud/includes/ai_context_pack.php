<?php
declare(strict_types=1);

const CLOUD_AI_CONTEXT_TOKEN_BUDGET = 200000;
const CLOUD_AI_CONTEXT_PACK_TARGET = 180000;

function cloud_ai_estimate_tokens(string $text): int
{
    return (int)max(1, ceil(mb_strlen($text, 'UTF-8') / 3.5));
}

function cloud_ai_estimate_messages_tokens(array $messages): int
{
    $sum = 0;
    foreach ($messages as $msg) {
        if (!is_array($msg)) {
            continue;
        }
        $sum += cloud_ai_estimate_tokens((string)($msg['content'] ?? ''));
        if (!empty($msg['tool_calls'])) {
            $sum += cloud_ai_estimate_tokens(json_encode($msg['tool_calls'], JSON_UNESCAPED_UNICODE));
        }
    }
    return $sum;
}

function cloud_ai_truncate_text(string $text, int $maxChars, string $label = ''): string
{
    if (mb_strlen($text, 'UTF-8') <= $maxChars) {
        return $text;
    }
    $head = mb_substr($text, 0, (int)($maxChars * 0.65), 'UTF-8');
    $tail = mb_substr($text, - (int)($maxChars * 0.25), null, 'UTF-8');
    $note = $label !== '' ? " [$label truncated for 200k context budget]" : ' [truncated for context budget]';
    return $head . "\n\n...{$note}...\n\n" . $tail;
}

function cloud_ai_summarize_middle_message(array $msg): array
{
    $role = (string)($msg['role'] ?? '');
    $content = (string)($msg['content'] ?? '');
    if ($role === 'tool') {
        $msg['content'] = cloud_ai_truncate_text($content, 3500, 'tool output');
        return $msg;
    }
    if ($role === 'user' && (str_contains($content, 'File content:') || str_contains($content, '[Referenced file:'))) {
        $msg['content'] = cloud_ai_truncate_text($content, 6000, 'file context');
        return $msg;
    }
    if ($role === 'assistant' && mb_strlen($content, 'UTF-8') > 8000) {
        $msg['content'] = cloud_ai_truncate_text($content, 8000, 'assistant');
        return $msg;
    }
    if (mb_strlen($content, 'UTF-8') > 4000) {
        $msg['content'] = cloud_ai_truncate_text($content, 4000, $role);
    }
    return $msg;
}

/**
 * Pack messages to stay under ~200k tokens (target 180k) using server-side truncation/summarization.
 */
function cloud_ai_pack_messages_for_budget(array $messages, int $targetTokens = CLOUD_AI_CONTEXT_PACK_TARGET): array
{
    if ($messages === []) {
        return $messages;
    }
    $total = cloud_ai_estimate_messages_tokens($messages);
    if ($total <= $targetTokens) {
        return $messages;
    }

    $system = [];
    $rest = [];
    foreach ($messages as $i => $msg) {
        if (!is_array($msg)) {
            continue;
        }
        if ($i === 0 && ($msg['role'] ?? '') === 'system') {
            $system[] = $msg;
        } elseif (($msg['role'] ?? '') === 'system') {
            $system[] = $msg;
        } else {
            $rest[] = $msg;
        }
    }

    $keepTail = min(8, count($rest));
    $tail = array_slice($rest, -$keepTail);
    $middle = array_slice($rest, 0, max(0, count($rest) - $keepTail));

    foreach ($middle as $k => $msg) {
        $middle[$k] = cloud_ai_summarize_middle_message($msg);
    }

    $packed = array_merge($system, $middle, $tail);
    $packedTotal = cloud_ai_estimate_messages_tokens($packed);

    if ($packedTotal > $targetTokens) {
        foreach ($packed as $k => $msg) {
            if (($msg['role'] ?? '') === 'system') {
                continue;
            }
            $c = (string)($msg['content'] ?? '');
            if (mb_strlen($c, 'UTF-8') > 2500) {
                $packed[$k]['content'] = cloud_ai_truncate_text($c, 2500, (string)($msg['role'] ?? 'msg'));
            }
        }
    }

    $packedTotal = cloud_ai_estimate_messages_tokens($packed);
    if ($packedTotal > $targetTokens) {
        $note = [
            'role' => 'system',
            'content' => 'Context was compressed server-side to stay within the 200000 token limit. Older details may be truncated; use read_file/search_in_files for full file content.',
        ];
        if ($system !== []) {
            $packed[0]['content'] = (string)$packed[0]['content'] . "\n\n" . $note['content'];
        } else {
            array_unshift($packed, $note);
        }
    }

    return $packed;
}
