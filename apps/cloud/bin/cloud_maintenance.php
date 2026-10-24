#!/usr/bin/env php
<?php
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}
define('APP_ROOT', dirname(__DIR__, 3));
require APP_ROOT . '/apps/cloud/api.php';
require_once APP_ROOT . '/apps/cloud/includes/cloud_sync_bus.php';

$loop = in_array('--loop', $argv ?? [], true);

$runOnce = static function (): int {
    $db = getDB();
    $stats = [
        'ai_chats' => 0,
        'ai_sessions' => 0,
        'transcription' => 0,
        'tts' => 0,
        'group_history' => 0,
        'group_chat' => 0,
        'shared_files' => 0,
        'sync_bus' => 0,
        'trash' => 0,
    ];
    if ($db) {
        $now = time();
        try {
            $s = $db->prepare('DELETE FROM cloud_ai_assistant_chats WHERE created_at < :t');
            $s->bindValue(':t', $now - 30 * 86400, PDO::PARAM_INT);
            $s->execute();
            $stats['ai_chats'] = (int)$s->rowCount();
        } catch (Throwable $e) {}
        try {
            $s = $db->prepare('DELETE FROM cloud_ai_assistant_chat_sessions WHERE updated_at < :t');
            $s->bindValue(':t', $now - 30 * 86400, PDO::PARAM_INT);
            $s->execute();
            $stats['ai_sessions'] = (int)$s->rowCount();
        } catch (Throwable $e) {}
        try {
            $s = $db->prepare("DELETE FROM cloud_transcription_jobs WHERE updated_at < :t AND status IN ('completed','stopped','error')");
            $s->bindValue(':t', $now - 7 * 86400, PDO::PARAM_INT);
            $s->execute();
            $stats['transcription'] = (int)$s->rowCount();
        } catch (Throwable $e) {}
        try {
            $s = $db->prepare("DELETE FROM cloud_tts_jobs WHERE updated_at < :t AND status IN ('completed','error','stopped')");
            $s->bindValue(':t', $now - 7 * 86400, PDO::PARAM_INT);
            $s->execute();
            $stats['tts'] = (int)$s->rowCount();
        } catch (Throwable $e) {}
        try {
            $s = $db->prepare('DELETE FROM cloud_group_file_history WHERE created_at < :t');
            $s->bindValue(':t', $now - 90 * 86400, PDO::PARAM_INT);
            $s->execute();
            $stats['group_history'] = (int)$s->rowCount();
        } catch (Throwable $e) {}
        try {
            $s = $db->prepare('DELETE FROM cloud_group_chat_messages WHERE created_at < :t');
            $s->bindValue(':t', $now - 90 * 86400, PDO::PARAM_INT);
            $s->execute();
            $stats['group_chat'] = (int)$s->rowCount();
        } catch (Throwable $e) {}
        try {
            $s = $db->prepare('DELETE FROM shared_files WHERE expires IS NOT NULL AND expires > 0 AND expires < :t');
            $s->bindValue(':t', $now, PDO::PARAM_INT);
            $s->execute();
            $stats['shared_files'] = (int)$s->rowCount();
        } catch (Throwable $e) {}
    }
    $busDir = cloud_sync_bus_dir();
    foreach (glob($busDir . '/*.events') ?: [] as $file) {
        $before = (int)@filesize($file);
        cloud_sync_bus_rotate_if_needed($file);
        $after = is_file($file) ? (int)@filesize($file) : 0;
        if ($after < $before) $stats['sync_bus']++;
    }
    if (function_exists('cloud_purge_expired_trash_all_users')) {
        $trash = cloud_purge_expired_trash_all_users();
        $stats['trash'] = (int)($trash['removed'] ?? 0);
    }
    fwrite(STDOUT, date('c') . ' ' . json_encode($stats) . "\n");
    return 0;
};

if (!$loop) {
    exit($runOnce());
}
while (true) {
    $runOnce();
    sleep(3600);
}
